/* AgriTrace+ PWA client v2 - REPLACE /assets/js/pwa.js
 * - SW registration, install prompt, offline bar
 * - Encrypted offline store (IndexedDB): page snapshots + request queue
 * - Offline login = PIN unlock (device only; never your server password)
 * - Auto-queues POST forms / AJAX while offline, auto-syncs when online
 */
(function () {
  'use strict';
  var _fetch = window.fetch.bind(window);
  var MAX_FILE = 8 * 1024 * 1024, MAX_FILES = 5, SNAP_TTL = 10 * 60 * 1000;
  var ALLOWED = /^(image\/(jpeg|png|webp|gif)|video\/(mp4|webm|quicktime)|application\/pdf)$/;
  var SKIP = /login|logout|register|forgot|reset|otp|offline-app/i;
  var BLOCK_ROLE = /admin|official|agrida|vet|technician/i;
  var enc = new TextEncoder(), dec = new TextDecoder();
  var SHELL = !!window.AGRI_SHELL;

  function meta(n) { var m = document.querySelector('meta[name="' + n + '"]'); return m ? m.content : ''; }
  var USER = meta('agri-user'), ROLE = meta('agri-role');
  var CAN_OFFLINE = !!USER && !BLOCK_ROLE.test(ROLE);

  /* ---------- helpers ---------- */
  function uuid() { return (crypto.randomUUID && crypto.randomUUID()) || 'id-' + Date.now() + '-' + Math.random().toString(16).slice(2); }
  function b64(buf) {
    var b = new Uint8Array(buf), s = '', i, c = 0x8000;
    for (i = 0; i < b.length; i += c) s += String.fromCharCode.apply(null, b.subarray(i, i + c));
    return btoa(s);
  }
  function unb64(s) { var b = atob(s), u = new Uint8Array(b.length), i; for (i = 0; i < b.length; i++) u[i] = b.charCodeAt(i); return u.buffer; }
  function toast(msg, ok) {
    if (!document.body) return;
    var t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;left:50%;bottom:70px;transform:translateX(-50%);z-index:100001;max-width:90%;padding:12px 18px;border-radius:10px;color:#fff;font:14px system-ui;background:' + (ok === false ? '#c62828' : '#2e7d32');
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 4500);
  }
  function modal(html) {
    var o = document.createElement('div');
    o.style.cssText = 'position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:16px';
    o.innerHTML = '<div style="background:#fff;color:#1b3a1d;border-radius:14px;padding:20px;max-width:380px;width:100%;font:15px system-ui">' + html + '</div>';
    document.body.appendChild(o);
    return o;
  }
  var IN = 'width:100%;padding:12px;margin-bottom:8px;border:1px solid #bbb;border-radius:8px;font-size:16px;box-sizing:border-box';
  var BT = 'flex:1;border:0;border-radius:8px;padding:12px;font-size:15px;min-height:44px;';

  /* ---------- service worker ---------- */
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/service-worker.js', { scope: '/' }).catch(function (e) { console.warn('SW failed', e); });
    });
    navigator.serviceWorker.addEventListener('message', function (e) { if (e.data && e.data.type === 'FLUSH_QUEUE') flush(); });
  }

  /* ---------- offline bar + install prompt ---------- */
  var bar;
  function offlineBar() {
    if (SHELL || !document.body) return;
    if (!bar) {
      bar = document.createElement('div');
      bar.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:99998;background:#ef6c00;color:#fff;text-align:center;padding:8px;font:14px system-ui;display:none';
      bar.textContent = 'You are offline. Changes are saved on this device and will sync when you are back online.';
      document.body.appendChild(bar);
    }
    var off = !navigator.onLine;
    bar.style.display = off ? 'block' : 'none';
    document.querySelectorAll('[data-requires-online]').forEach(function (el) { el.disabled = off; el.style.opacity = off ? '.5' : ''; });
  }
  document.addEventListener('DOMContentLoaded', offlineBar);
  window.addEventListener('offline', function () { offlineBar(); chip(); });
  window.addEventListener('online', function () { offlineBar(); flush(); chip(); });

  var deferred;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); deferred = e;
    if (SHELL || localStorage.getItem('pwa-dismissed') || matchMedia('(display-mode: standalone)').matches) return;
    var b = document.createElement('div');
    b.id = 'pwa-install-banner';
    b.style.cssText = 'position:fixed;left:12px;right:12px;bottom:12px;z-index:99997;background:#fff;color:#222;border:1px solid #c8e6c9;border-radius:14px;padding:12px 14px;display:flex;gap:10px;align-items:center;box-shadow:0 6px 20px rgba(0,0,0,.18);font:14px system-ui';
    b.innerHTML = '<img src="/icons/icon-192.png" width="40" height="40" style="border-radius:10px" alt=""><span style="flex:1"><b>Install AgriTrace+</b><br>Add to your home screen</span><button id="pwa-yes" style="background:#2e7d32;color:#fff;border:0;border-radius:8px;padding:10px 14px;min-height:44px">Install</button><button id="pwa-no" style="background:none;border:0;font-size:20px;min-width:36px;min-height:44px" aria-label="Dismiss">&times;</button>';
    document.body.appendChild(b);
    document.getElementById('pwa-yes').onclick = function () { deferred.prompt(); deferred.userChoice.then(function () { deferred = null; b.remove(); }); };
    document.getElementById('pwa-no').onclick = function () { localStorage.setItem('pwa-dismissed', '1'); b.remove(); };
  });
  window.addEventListener('appinstalled', function () { var b = document.getElementById('pwa-install-banner'); if (b) b.remove(); toast('AgriTrace+ installed'); });

  /* ---------- IndexedDB ---------- */
  var dbp;
  function db() {
    return dbp || (dbp = new Promise(function (res, rej) {
      var r = indexedDB.open('agritrace-offline', 2);
      r.onupgradeneeded = function () {
        var d = r.result;
        [['meta', 'k'], ['pages', 'url'], ['queue', 'id']].forEach(function (s) { if (!d.objectStoreNames.contains(s[0])) d.createObjectStore(s[0], { keyPath: s[1] }); });
      };
      r.onsuccess = function () { res(r.result); };
      r.onerror = function () { rej(r.error); };
    }));
  }
  function tx(store, mode, fn) {
    return db().then(function (d) {
      return new Promise(function (res, rej) {
        var t = d.transaction(store, mode), q = fn(t.objectStore(store));
        t.oncomplete = function () { res(q && q.result); };
        t.onerror = function () { rej(t.error); };
        t.onabort = function () { rej(t.error); };
      });
    });
  }
  function get(s, k) { return tx(s, 'readonly', function (o) { return o.get(k); }); }
  function put(s, v) { return tx(s, 'readwrite', function (o) { return o.put(v); }); }
  function del(s, k) { return tx(s, 'readwrite', function (o) { return o.delete(k); }); }
  function all(s) { return tx(s, 'readonly', function (o) { return o.getAll(); }); }
  function clr(s) { return tx(s, 'readwrite', function (o) { return o.clear(); }); }

  /* ---------- crypto: PIN-protected RSA key; hybrid RSA-OAEP + AES-GCM ---------- */
  var privCache = null;
  async function pinBits(pin, salt) {
    var m = await crypto.subtle.importKey('raw', enc.encode(pin), 'PBKDF2', false, ['deriveBits']);
    return crypto.subtle.deriveBits({ name: 'PBKDF2', salt: salt, iterations: 200000, hash: 'SHA-256' }, m, 256);
  }
  function aesKey(raw, usage) { return crypto.subtle.importKey('raw', raw, 'AES-GCM', false, usage); }
  async function setupPin(pin, userId) {
    var salt = crypto.getRandomValues(new Uint8Array(16));
    var raw = await pinBits(pin, salt);
    var kek = await aesKey(raw, ['encrypt']);
    var kp = await crypto.subtle.generateKey({ name: 'RSA-OAEP', modulusLength: 2048, publicExponent: new Uint8Array([1, 0, 1]), hash: 'SHA-256' }, true, ['encrypt', 'decrypt']);
    var pub = await crypto.subtle.exportKey('spki', kp.publicKey);
    var priv = await crypto.subtle.exportKey('pkcs8', kp.privateKey);
    var iv = crypto.getRandomValues(new Uint8Array(12));
    var wpriv = await crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv }, kek, priv);
    await put('meta', { k: 'profile', salt: salt, pub: pub, iv: iv, wpriv: wpriv, user: String(userId), created: Date.now() });
    sessionStorage.setItem('agri_k', b64(raw));
    privCache = null;
  }
  async function getPriv() {
    if (privCache) return privCache;
    var k = sessionStorage.getItem('agri_k'); if (!k) throw new Error('locked');
    var p = await get('meta', 'profile'); if (!p) throw new Error('noprofile');
    var kek = await aesKey(unb64(k), ['decrypt']);
    var pk8 = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: p.iv }, kek, p.wpriv);
    privCache = await crypto.subtle.importKey('pkcs8', pk8, { name: 'RSA-OAEP', hash: 'SHA-256' }, false, ['decrypt']);
    return privCache;
  }
  async function unlock(pin) {
    try {
      var p = await get('meta', 'profile'); if (!p) return false;
      var raw = await pinBits(pin, p.salt);
      var kek = await aesKey(raw, ['decrypt']);
      await crypto.subtle.decrypt({ name: 'AES-GCM', iv: p.iv }, kek, p.wpriv); // fails if PIN wrong
      sessionStorage.setItem('agri_k', b64(raw)); privCache = null;
      return true;
    } catch (e) { return false; }
  }
  async function tryUnlock() { try { await getPriv(); return true; } catch (e) { return false; } }
  async function seal(buf) {
    var p = await get('meta', 'profile'); if (!p) throw new Error('noprofile');
    var pub = await crypto.subtle.importKey('spki', p.pub, { name: 'RSA-OAEP', hash: 'SHA-256' }, false, ['encrypt']);
    var dk = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt']);
    var iv = crypto.getRandomValues(new Uint8Array(12));
    var ct = await crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv }, dk, buf);
    var w = await crypto.subtle.encrypt({ name: 'RSA-OAEP' }, pub, await crypto.subtle.exportKey('raw', dk));
    return { iv: iv, w: w, ct: ct };
  }
  async function open(rec) {
    var priv = await getPriv();
    var raw = await crypto.subtle.decrypt({ name: 'RSA-OAEP' }, priv, rec.w);
    var dk = await crypto.subtle.importKey('raw', raw, 'AES-GCM', false, ['decrypt']);
    return crypto.subtle.decrypt({ name: 'AES-GCM', iv: rec.iv }, dk, rec.ct);
  }
  async function hasMine() {
    var p = await get('meta', 'profile');
    return !!p && String(p.user) === String(USER);
  }

  /* ---------- page snapshots (encrypted) ---------- */
  function pkey(u) { u = new URL(u, location.href); return u.pathname + u.search; }
  async function snapshot(url) {
    try {
      if (!CAN_OFFLINE || !navigator.onLine || !(await hasMine())) return;
      var key = pkey(url);
      if (SKIP.test(key)) return;
      var ex = await get('pages', key);
      if (ex && Date.now() - ex.ts < SNAP_TTL) return;
      var r = await _fetch(key, { credentials: 'same-origin', headers: { 'X-Offline-Snapshot': '1' } });
      if (!r.ok || (r.redirected && /login/i.test(r.url))) return;
      if (!/text\/html/i.test(r.headers.get('content-type') || '')) return;
      var html = await r.text();
      if (html.indexOf('name="agri-user"') === -1) return; // only logged-in pages
      var s = await seal(enc.encode(html).buffer);
      await put('pages', { url: key, ts: Date.now(), iv: s.iv, w: s.w, ct: s.ct });
    } catch (e) { /* ignore */ }
  }
  async function snapshotAll() {
    await snapshot(location.href);
    var seen = {}, list = [];
    document.querySelectorAll('nav a[href], aside a[href], .sidebar a[href], .navbar a[href]').forEach(function (a) {
      try {
        var u = new URL(a.getAttribute('href'), location.href);
        var k = u.pathname + u.search;
        if (u.origin !== location.origin || !/\.php$/.test(u.pathname) || SKIP.test(k)) return;
        if (/delete|remove|approve|reject|cancel|logout|action=|toggle/i.test(u.search)) return;
        if (!seen[k]) { seen[k] = 1; list.push(k); }
      } catch (e) {}
    });
    for (var i = 0; i < Math.min(list.length, 20); i++) {
      if (!navigator.onLine) break;
      await snapshot(list[i]);
      await new Promise(function (r) { setTimeout(r, 400); });
    }
  }

  /* ---------- offline request queue ---------- */
  async function packForm(url, fd) {
    var fields = [], files = [], n = 0, it = fd.entries(), s;
    while (!(s = it.next()).done) {
      var k = s.value[0], v = s.value[1];
      if (typeof File !== 'undefined' && v instanceof File) {
        if (!v.name || !v.size) continue;
        if (v.size > MAX_FILE || !ALLOWED.test(v.type) || ++n > MAX_FILES) throw new Error('Invalid or too large file: ' + v.name);
        files.push({ k: k, name: v.name, type: v.type, data: b64(await v.arrayBuffer()) });
      } else fields.push([k, v]);
    }
    return { url: url, method: 'POST', kind: 'form', fields: fields, files: files };
  }
  async function enqueue(rec) {
    if (!(await hasMine())) throw new Error('noprofile');
    var s = await seal(enc.encode(JSON.stringify(rec)).buffer);
    await put('queue', { id: uuid(), user: String(USER), ts: Date.now(), fail: 0, iv: s.iv, w: s.w, ct: s.ct });
    chip();
  }

  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!(f instanceof HTMLFormElement) || navigator.onLine) return;
    var method = (f.getAttribute('method') || 'get').toUpperCase();
    if (method !== 'POST') return;
    var action; try { action = new URL(f.getAttribute('action') || location.href, location.href); } catch (x) { return; }
    if (action.origin !== location.origin || SKIP.test(action.pathname)) return;
    e.preventDefault();
    if (!CAN_OFFLINE) { toast('Offline access is not enabled. Connect to the internet.', false); return; }
    var fd = new FormData(f);
    if (e.submitter && e.submitter.name) fd.append(e.submitter.name, e.submitter.value);
    packForm(action.href, fd).then(enqueue).then(function () {
      toast('Saved on this device. It will sync when you are online.');
      f.reset();
    }).catch(function (err) {
      toast(err && err.message === 'noprofile' ? 'Enable offline access first (while online).' : (err.message || 'Could not save offline.'), false);
    });
  }, true);

  // AJAX (fetch) calls made by page scripts
  window.fetch = function (input, init) {
    init = init || {};
    var req = (typeof input === 'string' || (typeof URL !== 'undefined' && input instanceof URL)) ? null : input;
    var method = (init.method || (req && req.method) || 'GET').toUpperCase();
    var url;
    try { url = new URL(req ? req.url : String(input), location.href); } catch (x) { return _fetch(input, init); }
    if (method === 'GET' || method === 'HEAD' || navigator.onLine || url.origin !== location.origin || SKIP.test(url.pathname) || !CAN_OFFLINE) return _fetch(input, init);
    return queueAjax(url.href, method, init, req).catch(function () { return _fetch(input, init); });
  };
  async function queueAjax(url, method, init, req) {
    var b = init.body, rec;
    if (b === undefined && req) b = await req.clone().text();
    var h = new Headers(init.headers || (req && req.headers) || {});
    var extra = {};
    h.forEach(function (v, k) { if (!/^content-(type|length)$/i.test(k)) extra[k] = v; });
    if (typeof FormData !== 'undefined' && b instanceof FormData) { rec = await packForm(url, b); rec.method = method; }
    else if (b == null || typeof b === 'string' || b instanceof URLSearchParams) {
      rec = { url: url, method: method, kind: 'raw', body: b == null ? '' : String(b), ctype: h.get('Content-Type') || (b instanceof URLSearchParams ? 'application/x-www-form-urlencoded;charset=UTF-8' : 'text/plain') };
    } else throw new Error('unsupported body');
    rec.headers = extra;
    await enqueue(rec);
    return new Response(JSON.stringify({ success: true, status: 'success', queued: true, offline: true, message: 'Saved offline. It will sync when you are online.' }), { status: 202, headers: { 'Content-Type': 'application/json' } });
  }

  /* ---------- sync ---------- */
  var flushing = false;
  async function flush() {
    if (flushing || !navigator.onLine || !USER || !sessionStorage.getItem('agri_k')) return;
    flushing = true;
    var sent = 0;
    try {
      var items = (await all('queue')).filter(function (i) { return i.user === String(USER) && (i.fail || 0) < 3; }).sort(function (a, b) { return a.ts - b.ts; });
      for (var n = 0; n < items.length; n++) {
        var it = items[n], rec = JSON.parse(dec.decode(await open(it)));
        var init = { method: rec.method, credentials: 'same-origin', headers: Object.assign({}, rec.headers || {}, { 'X-Client-Request-Id': it.id, 'X-Requested-With': 'AgriTracePWA' }) };
        if (rec.kind === 'form') {
          var fd = new FormData();
          rec.fields.forEach(function (p) { fd.append(p[0], p[1]); });
          rec.files.forEach(function (f) { fd.append(f.k, new Blob([unb64(f.data)], { type: f.type }), f.name); });
          init.body = fd;
        } else { init.body = rec.body; init.headers['Content-Type'] = rec.ctype; }
        var r;
        try { r = await _fetch(rec.url, init); } catch (e) { break; }
        if (r.status === 401 || r.status === 403 || (r.redirected && /login/i.test(r.url))) { toast('Session expired. Log in again to finish syncing.', false); break; }
        if (r.ok) { await del('queue', it.id); sent++; }
        else if (r.status >= 500) break;
        else { it.fail = (it.fail || 0) + 1; await put('queue', it); }
      }
    } catch (e) { /* locked or crypto error: wait for PIN */ }
    flushing = false;
    chip();
    if (sent) { toast(sent + ' offline item(s) synced to the server.'); setTimeout(function () { if (USER && navigator.onLine) location.reload(); }, 1800); }
  }
  window.addEventListener('load', function () { setTimeout(flush, 1500); });

  /* ---------- UI: PIN dialogs + chip ---------- */
  function setupDialog(replace) {
    var o = modal('<h3 style="margin:0 0 8px">Enable offline access</h3><p style="margin:0 0 12px;font-size:14px;line-height:1.4">Create an offline PIN / passphrase (min. 6 characters). Your saved data is encrypted on this device. It cannot be recovered if you forget it.' + (replace ? '<br><b style="color:#c62828">Offline data of another account on this device will be removed.</b>' : '') + '</p><input id="ap1" type="password" autocomplete="off" placeholder="Offline PIN / passphrase" style="' + IN + '"><input id="ap2" type="password" autocomplete="off" placeholder="Confirm" style="' + IN + '"><div id="ape" style="color:#c62828;font-size:13px;min-height:18px"></div><div style="display:flex;gap:8px"><button id="apc" style="' + BT + 'background:#eee">Cancel</button><button id="apo" style="' + BT + 'background:#2e7d32;color:#fff">Enable</button></div>');
    o.querySelector('#apc').onclick = function () { o.remove(); };
    o.querySelector('#apo').onclick = async function () {
      var a = o.querySelector('#ap1').value, b = o.querySelector('#ap2').value, e = o.querySelector('#ape');
      if (a.length < 6) { e.textContent = 'Use at least 6 characters.'; return; }
      if (a !== b) { e.textContent = 'PINs do not match.'; return; }
      e.textContent = 'Setting up…'; this.disabled = true;
      try {
        if (replace) { await clr('pages'); await clr('queue'); await clr('meta'); }
        await setupPin(a, USER);
        o.remove(); toast('Offline access enabled. Saving your pages…'); chip();
        await snapshotAll(); toast('Offline pages saved.');
      } catch (err) { e.textContent = 'Failed: ' + (err.message || err); this.disabled = false; }
    };
  }
  function askPin() {
    return new Promise(function (resolve) {
      var o = modal('<h3 style="margin:0 0 8px">Enter offline PIN</h3><input id="aq" type="password" autocomplete="off" placeholder="Offline PIN / passphrase" style="' + IN + '"><div id="aqe" style="color:#c62828;font-size:13px;min-height:18px"></div><div style="display:flex;gap:8px"><button id="aqc" style="' + BT + 'background:#eee">Cancel</button><button id="aqo" style="' + BT + 'background:#2e7d32;color:#fff">Unlock</button></div>');
      o.querySelector('#aqc').onclick = function () { o.remove(); resolve(false); };
      o.querySelector('#aqo').onclick = async function () {
        var ok = await unlock(o.querySelector('#aq').value);
        if (ok) { o.remove(); resolve(true); } else o.querySelector('#aqe').textContent = 'Wrong PIN / passphrase.';
      };
    });
  }
  async function chip() {
    if (SHELL || !document.body) return;
    var el = document.getElementById('agri-chip');
    if (!el) {
      el = document.createElement('button');
      el.id = 'agri-chip';
      el.style.cssText = 'position:fixed;left:12px;bottom:12px;z-index:99996;background:#2e7d32;color:#fff;border:0;border-radius:22px;padding:10px 16px;font:13px system-ui;box-shadow:0 4px 14px rgba(0,0,0,.25);display:none;min-height:40px';
      el.onclick = chipClick;
      document.body.appendChild(el);
    }
    var txt = '', mode = '';
    try {
      var p = await get('meta', 'profile'), q = await all('queue');
      var mine = USER ? q.filter(function (i) { return i.user === String(USER); }).length : q.length;
      if (CAN_OFFLINE && navigator.onLine && (!p || String(p.user) !== String(USER))) { txt = '🔒 Enable offline access'; mode = p ? 'replace' : 'setup'; }
      else if (mine && p) { var locked = !sessionStorage.getItem('agri_k'); txt = mine + ' pending' + (locked ? ' · unlock & sync' : ' · sync now'); mode = 'sync'; }
    } catch (e) {}
    el.dataset.mode = mode; el.textContent = txt; el.style.display = txt ? 'block' : 'none';
  }
  async function chipClick() {
    var m = this.dataset.mode;
    if (m === 'setup' || m === 'replace') return setupDialog(m === 'replace');
    if (m === 'sync') {
      if (!navigator.onLine) return toast('Waiting for internet connection.', false);
      if (!sessionStorage.getItem('agri_k') && !(await askPin())) return;
      flush();
    }
  }
  document.addEventListener('DOMContentLoaded', function () {
    chip();
    if (navigator.onLine && CAN_OFFLINE) setTimeout(snapshotAll, 2500);
  });
  // lock the device session on logout
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (a && /logout/i.test(a.getAttribute('href'))) { if (navigator.onLine) flush(); sessionStorage.removeItem('agri_k'); privCache = null; }
  }, true);

  /* ---------- public API (used by offline-app.html) ---------- */
  window.AgriPWA = {
    flush: flush,
    hasProfile: async function () { return !!(await get('meta', 'profile')); },
    unlock: unlock,
    tryUnlock: tryUnlock,
    listPages: async function () { return (await all('pages')).map(function (p) { return { url: p.url, ts: p.ts }; }); },
    getPage: async function (url) {
      var rec = await get('pages', pkey(url)); if (!rec) return null;
      return dec.decode(await open(rec));
    },
    pending: async function () { return (await all('queue')).length; },
    wipe: async function () { await clr('pages'); await clr('queue'); await clr('meta'); sessionStorage.removeItem('agri_k'); privCache = null; }
  };
})();
