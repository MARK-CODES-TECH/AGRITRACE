/* AgriTrace+ Service Worker v2 - REPLACE the old /service-worker.js */
const VERSION = 'v2';
const STATIC_CACHE = 'agritrace-static-' + VERSION;
const PRECACHE = ['/offline-app.html', '/offline.html', '/manifest.json', '/assets/js/pwa.js', '/icons/icon-192.png', '/icons/icon-512.png'];
const STATIC_EXT = /\.(?:css|js|png|jpg|jpeg|svg|webp|gif|ico|woff2?|ttf)$/i;
const NEVER_CACHE = /^\/(uploads|api|chat|ajax)\b/i;
const CDN = /^https:\/\/(fonts\.googleapis\.com|fonts\.gstatic\.com|cdn\.jsdelivr\.net|cdnjs\.cloudflare\.com)\//;

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(STATIC_CACHE)
      .then((c) => Promise.all(PRECACHE.map((u) => c.add(u).catch(() => {}))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith('agritrace-') && k !== STATIC_CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

function swr(req) {
  return caches.open(STATIC_CACHE).then((cache) =>
    cache.match(req).then((hit) => {
      const net = fetch(req).then((res) => {
        if (res && (res.status === 200 || res.type === 'opaque')) cache.put(req, res.clone());
        return res;
      }).catch(() => hit);
      return hit || net;
    })
  );
}

function timeoutFetch(req, ms) {
  return new Promise((resolve, reject) => {
    const t = setTimeout(() => reject(new Error('timeout')), ms);
    fetch(req).then((r) => { clearTimeout(t); resolve(r); }, (err) => { clearTimeout(t); reject(err); });
  });
}

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);

  if (url.origin !== self.location.origin) {
    if (CDN.test(req.url)) e.respondWith(swr(req));
    return;
  }

  // Pages: network first. When offline -> offline-app.html (PIN unlock + encrypted cached pages)
  if (req.mode === 'navigate') {
    e.respondWith(timeoutFetch(req, 10000).catch(() => caches.match('/offline-app.html').then((r) => r || caches.match('/offline.html'))));
    return;
  }

  if (NEVER_CACHE.test(url.pathname)) return;
  if (STATIC_EXT.test(url.pathname)) e.respondWith(swr(req));
});

self.addEventListener('sync', (e) => {
  if (e.tag === 'agritrace-sync') {
    e.waitUntil(self.clients.matchAll({ includeUncontrolled: true }).then((cs) => cs.forEach((c) => c.postMessage({ type: 'FLUSH_QUEUE' }))));
  }
});

self.addEventListener('push', (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (_) {}
  e.waitUntil(self.registration.showNotification(d.title || 'AgriTrace+', {
    body: d.body || 'You have a new update.', icon: '/icons/icon-192.png', badge: '/icons/icon-192.png', data: { url: d.url || '/' }
  }));
});
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  e.waitUntil(clients.openWindow((e.notification.data && e.notification.data.url) || '/'));
});
