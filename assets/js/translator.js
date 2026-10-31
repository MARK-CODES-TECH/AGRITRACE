/**
 * AgriTrace+ Bilingual Translation Engine (English <-> Tagalog / Filipino)
 * Handles in-place dynamic DOM translation, persistence across sessions,
 * input placeholders, attributes, and live mutations without page refresh.
 */
(function () {
    'use strict';

    // Comprehensive AgriTrace+ Domain Dictionary (English -> Tagalog/Filipino)
    const DICTIONARY = {
        // --- Branding & System Header ---
        "District Command & Surveillance": "Pamunuan at Pagmamanman ng Distrito",
        "District Command &amp; Surveillance": "Pamunuan at Pagmamanman ng Distrito",
        "Real-Time Provincial Sync Active": "Aktibo ang Real-Time na Provincial Sync",
        "System Active": "Aktibo ang Sistema",
        "Live Map View": "Live na Mapa",
        "Full Map View": "Buong View ng Mapa",
        "Sign Out": "Lumabas",
        "Logout": "Lumabas",
        "Sign In": "Mag-sign In",
        "Log In": "Mag-log In",
        "Login": "Mag-login",
        "Admin Console": "Console ng Admin",
        "System Admin": "Admin ng Sistema",
        "Farmer Portal": "Portal ng Magsasaka",
        "Official DA Portal": "Opisyal na Portal ng DA",
        "Official Portal": "Opisyal na Portal",
        "Department of Agriculture": "Kagawaran ng Pagsasaka (DA)",
        "1st District Jurisdiction": "Hurisdiksyon ng Unang Distrito",
        "1st District": "Unang Distrito",
        "Camarines Sur 1st District Jurisdiction • Cabusao, Del Gallego, Lupi, Ragay, Sipocot": "Hurisdiksyon ng Unang Distrito ng Camarines Sur • Cabusao, Del Gallego, Lupi, Ragay, Sipocot",

        // --- Navigation Sections ---
        "Command Center": "Sentro ng Pamunuan",
        "Dashboard Overview": "Pangkalahatang Dashboard",
        "Dashboard": "Dashboard",
        "GIS Geo-Monitoring": "GIS Geo-Pagmamanman",
        "GIS Geo-Monitoring & Spatial Surveillance": "GIS Geo-Pagmamanman at Pagsubaybay sa Lugar",
        "District Operations": "Operasyon ng Distrito",
        "Farmer Registry": "Talaan ng mga Magsasaka",
        "Farmer Registry & Identity Management": "Talaan at Pagkakakilanlan ng Magsasaka",
        "Farmer Directory & Verification": "Direktoryo at Pagpapatunay ng Magsasaka",
        "Farm Holdings": "Mga Sakahan at Lupa",
        "Farm Holdings & Geographic Parcellary": "Mga Sakahan at Heograpikong Parcellary",
        "Livestock Inventory": "Imbentaryo ng mga Hayop",
        "Livestock Inventory & Biosecurity Trace": "Imbentaryo ng Hayop at Pagsubaybay sa Biosecurity",
        "Livestock Inventory & Traceability": "Imbentaryo at Pagsubaybay ng Hayop",
        "Incident Alerts": "Mga Alerto sa Insidente",
        "Incident Alerts & Biosecurity Reports": "Mga Alerto sa Insidente at Ulat sa Biosecurity",
        "System & Governance": "Sistema at Pamamahala",
        "User Management": "Pamamahala ng Gumagamit",
        "System User & Access Governance": "Pamamahala ng Gumagamit at Access ng Sistema",
        "System User & Role Management": "Pamamahala ng Gumagamit at Tungkulin",
        "Export Reports": "I-export ang mga Ulat",
        "Export Reports & Analytical Summaries": "I-export ang mga Ulat at Buod ng Pagsusuri",
        "System Audit Logs": "Mga Talaan ng Pagsusuri ng Sistema",
        "System Audit Trail & Security Logs": "Audit Trail ng Sistema at Talaan ng Seguridad",
        "Interactive Parcellary Geospatial Registry • Real-Time Spatial Sync": "Interaktibong Rehistro ng Lupa • Real-Time na Spatial Sync",
        "Accredited District Producer Directory • Camarines Sur 1st District": "Direktoryo ng Akreditadong Magsasaka • Unang Distrito ng Camarines Sur",
        "Cadastral Boundaries, Commodity Classifications & Verification": "Mga Hangganan ng Lupa, Uri ng Pananim/Hayop at Pagpapatunay",
        "Ear-Tag Allocation, Species Census & Vaccination Records": "Paglalaan ng Ear-Tag, Sensus ng Hayop at Talaan ng Bakuna",
        "Rapid Outbreak Surveillance & Emergency Incident Response": "Mabilis na Pagmamanman ng Sakit at Pagtugon sa Emergency",
        "Role-Based Access Control, Field Officer & Admin Credentials": "Kontrol sa Access ayon sa Tungkulin, Kredensyal ng Opisyal at Admin",
        "Standardized Department of Agriculture District Reports & CSV Exports": "Pamantayang mga Ulat ng DA sa Distrito at Pag-export sa CSV",
        "Immutable Activity Chronology & Authenticated Operations": "Permanenteng Talaan ng mga Aktibidad at Operasyon ng Sistema",
        "Farm Registration": "Rehistrasyon ng Sakahan",
        "Report Incident": "Mag-ulat ng Insidente",
        "Notifications": "Mga Abiso",
        "Farmer Chat": "Usapan ng Magsasaka",
        "E-Certificate": "E-Sertipiko",
        "My Profile": "Aking Profile",
        "Public Reports": "Pampublikong Ulat",
        "Public Incident Reporting": "Pampublikong Pag-uulat ng Insidente",

        // --- Greetings & Hero Section ---
        "Good morning": "Magandang umaga",
        "Good afternoon": "Magandang hapon",
        "Good evening": "Magandang gabi",
        "Welcome back": "Maligayang pagbabalik",
        "Real-Time Surveillance Active": "Aktibo ang Real-Time na Pagmamanman",
        "Real-Time Biosecurity Surveillance": "Real-Time na Pagmamanman ng Biosecurity",
        "Register New Farm": "Magrehistro ng Bagong Sakahan",
        "Report Incident Alert": "Mag-ulat ng Alerto sa Insidente",
        "Export Summary": "I-export ang Buod",
        "Live Spatial Status": "Kasalukuyang Katayuan sa Mapa",

        // --- KPI Card Titles & Subtitles ---
        "Total Farmers": "Kabuuang Magsasaka",
        "Registered Holdings": "Mga Rehistradong Sakahan",
        "Livestock Census": "Sensus ng mga Hayop",
        "Active Incidents": "Mga Aktibong Insidente",
        "Validated producers": "Mga beripikadong magsasaka",
        "Active district producers": "Mga aktibong magsasaka sa distrito",
        "Parcels mapped in GIS": "Mga parselang nakamapa sa GIS",
        "Documented farmlands": "Mga nakatalang sakahan",
        "Head registered in 1st District": "Ulo na rehistrado sa Unang Distrito",
        "Individual tagged animals": "Mga indibidwal na may-tag na hayop",
        "Under containment / active review": "Kasalukuyang sinisiyasat / minomonitor",
        "Active biosecurity reports": "Mga aktibong ulat sa biosecurity",

        // --- Herd Health & Visual Analytics ---
        "Herd Health & Biosecurity Status": "Kalusugan ng Kawan at Katayuan sa Biosecurity",
        "Herd Health & Biosecurity": "Kalusugan ng Kawan at Biosecurity",
        "Species Distribution": "Pamamahagi ng mga Uri ng Hayop",
        "Livestock by Species": "Mga Hayop ayon sa Uri",
        "Last 7 days": "Huling 7 araw",
        "Healthy": "Malusog",
        "Critical": "Kritikal",
        "Quarantined": "Naka-quarantine",
        "Observation": "Binabantayan",
        "Under Observation": "Binabantayan",
        "Pending Verification": "Naghihintay ng Pagpapatunay",
        "Pending Farm Verifications": "Mga Nakabinbing Pagpapatunay ng Sakahan",
        "Recent Biosecurity Incident Timeline": "Timeline ng Kamakailang Insidente sa Biosecurity",
        "1st District Municipalities": "Mga Munisipyo ng Unang Distrito",
        "View all": "Tingnan lahat",
        "Review": "Suriin",
        "Resolve": "Lutasin",
        "Approve": "Aprubahan",
        "Reject": "Tanggihan",
        "Inspect": "Siyasatin",

        // --- Livestock Species ---
        "Carabao": "Kalabaw",
        "Carabaos": "Mga Kalabaw",
        "Cattle": "Baka",
        "Cattles": "Mga Baka",
        "Swine": "Baboy",
        "Swine / Pig": "Baboy",
        "Pig": "Baboy",
        "Pigs": "Mga Baboy",
        "Goat": "Kambing",
        "Goats": "Mga Kambing",
        "Poultry": "Manukan",
        "Chicken": "Manok",
        "Chickens": "Mga Manok",
        "Duck": "Itik / Pato",
        "Ducks": "Mga Itik / Pato",
        "Sheep": "Tupa",
        "Horse": "Kabayo",
        "Horses": "Mga Kabayo",

        // --- Statuses & Tags ---
        "Pending": "Nakabinbin",
        "Approved": "Aprubado",
        "Rejected": "Tinanggihan",
        "Resolved": "Nalutas",
        "Investigating": "Sinisiyasat",
        "Active": "Aktibo",
        "Inactive": "Di-aktibo",
        "Suspended": "Suspendido",
        "Vaccinated": "Bakunado",
        "Unvaccinated": "Hindi Bakunado",
        "Verified": "Beripikado",
        "Unverified": "Hindi Beripikado",
        "Male": "Lalaki",
        "Female": "Babae",
        "LIVE": "LIVE",

        // --- Common Table Headers & Labels ---
        "Farmer Name": "Pangalan ng Magsasaka",
        "Farm Name": "Pangalan ng Sakahan",
        "Farm Address": "Tirahan ng Sakahan",
        "Municipality": "Munisipyo",
        "Barangay": "Barangay",
        "Contact": "Numero ng Kontak",
        "Contact Number": "Numero ng Kontak",
        "Total Area": "Kabuuang Sukat",
        "Area (Hectares)": "Sukat (Ektarya)",
        "Hectares": "Ektarya",
        "Head Count": "Bilang ng Ulo",
        "Registration Date": "Petsa ng Rehistrasyon",
        "Created At": "Petsa ng Paggawa",
        "Actions": "Mga Aksyon",
        "Action": "Aksyon",
        "Status": "Katayuan",
        "Details": "Mga Detalye",
        "View": "Tingnan",
        "Edit": "I-edit",
        "Delete": "Burahin",
        "Search": "Maghanap",
        "Filter": "Salain",
        "Export CSV": "I-export sa CSV",
        "Download": "I-download",
        "Print": "I-print",
        "Close": "Isara",
        "Cancel": "Kanselahin",
        "Save": "I-save",
        "Save Changes": "I-save ang Pagbabago",
        "Submit": "Isumite",
        "Confirm": "Kumpirmahin",
        "Back": "Bumalik",
        "Next": "Susunod",
        "Yes": "Oo",
        "No": "Hindi",

        // --- Form Inputs & Placeholders ---
        "Search by name, email, or mobile...": "Maghanap ayon sa pangalan, email, o mobile...",
        "Search by farm name, owner, or municipality...": "Maghanap ayon sa pangalan ng sakahan, may-ari, o munisipyo...",
        "Search by ear tag, species, or farm...": "Maghanap ayon sa ear tag, uri ng hayop, o sakahan...",
        "Search by title, location, or reporter...": "Maghanap ayon sa pamagat, lokasyon, o nag-ulat...",
        "Search audit logs...": "Maghanap sa audit logs...",
        "Search farmers, farms, or tag numbers...": "Maghanap ng magsasaka, sakahan, o tag number...",
        "Enter your password": "Ilagay ang iyong password",
        "Enter your email": "Ilagay ang iyong email",

        // --- Public Portal & Auth ---
        "Digital Livestock Registration & Reporting System": "Digital na Sistema sa Rehistrasyon at Pag-uulat ng mga Hayop",
        "Empowering 1st District Farmers with Precision GIS Mapping, Instant Disease Surveillance, and Real-Time Animal Traceability.": "Pinalalakas ang mga Magsasaka sa Unang Distrito sa pamamagitan ng GIS Mapping, Mabilis na Pagmamanman ng Sakit, at Real-Time na Pagsubaybay sa mga Hayop.",
        "Access Portal": "Pumasok sa Portal",
        "Farmer Registration Portal": "Portal sa Rehistrasyon ng Magsasaka",
        "Report an Outbreak": "Mag-ulat ng Paglaganap ng Sakit",
        "Track Incident Report": "Subaybayan ang Ulat ng Insidente",
        "Sign in to AgriTrace+": "Mag-sign in sa AgriTrace+",
        "Enter your credentials to access your account": "Ilagay ang iyong mga kredensyal upang ma-access ang account",
        "Email Address": "Email Address",
        "Password": "Password",
        "Remember Me": "Tandaan Ako",
        "Forgot Password?": "Nakalimutan ang Password?",
        "Don't have an account?": "Wala ka pang account?",
        "Register here": "Magrehistro rito",
        "Already have an account?": "May account ka na?",
        "Sign In here": "Mag-sign in dito",
        "Create an Account": "Gumawa ng Account",

        // --- Incident Reporting ---
        "Public Incident Report": "Pampublikong Ulat ng Insidente",
        "Report Animal Health Incident": "Mag-ulat ng Insidente sa Kalusugan ng Hayop",
        "Incident Title": "Pamagat ng Insidente",
        "Severity Level": "Antas ng Kalubhaan",
        "Location / Address": "Lokasyon / Tirahan",
        "Description of Symptoms": "Paglalarawan ng mga Sintomas",
        "Affected Animals": "Apektadong mga Hayop",
        "Low": "Mababa",
        "Moderate": "Katamtaman",
        "High": "Mataas",
        "Emergency": "Kagyat na Emergency",
        "Attach Photo": "Maglakip ng Larawan",

        // --- Calling & Communication ---
        "Incoming Call": "Papasok na Tawag",
        "Voice Call": "Tawag sa Boses",
        "Video Call": "Tawag sa Video",
        "Answer": "Sagutin",
        "Hang up": "Ibaba",
        "Calling…": "Tumatawag…",
        "Microphone/Camera access denied.": "Tinanggihan ang access sa mikropono/kamera.",
        "Please allow permissions in your browser settings and try again.": "Mangyaring pahintulutan sa browser settings at subukang muli."
    };

    // Build reverse mapping (Tagalog -> English) for clean instant revert
    const REVERSE_DICTIONARY = {};
    for (const [en, tl] of Object.entries(DICTIONARY)) {
        REVERSE_DICTIONARY[tl] = en;
    }

    // Helper to get cookie value
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
        return match ? decodeURIComponent(match[3]) : null;
    }

    // Helper to set cookie value
    function setCookie(name, value, days = 365) {
        const d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${encodeURIComponent(value)}; expires=${d.toUTCString()}; path=/`;
    }

    // Get current language (default 'en')
    function getCurrentLanguage() {
        return localStorage.getItem('agritrace_lang') || getCookie('agritrace_lang') || 'en';
    }

    // Translate a single string
    function translatePhrase(str, toLang) {
        if (!str || typeof str !== 'string') return str;
        const trimmed = str.trim();
        if (!trimmed) return str;

        if (toLang === 'tl') {
            if (DICTIONARY[trimmed]) {
                return str.replace(trimmed, DICTIONARY[trimmed]);
            }
        } else {
            if (REVERSE_DICTIONARY[trimmed]) {
                return str.replace(trimmed, REVERSE_DICTIONARY[trimmed]);
            }
        }
        return str;
    }

    // Check if element should be skipped
    function shouldSkipElement(el) {
        if (!el || el.nodeType !== Node.ELEMENT_NODE) return false;
        const tag = el.tagName.toLowerCase();
        if (tag === 'script' || tag === 'style' || tag === 'svg' || tag === 'code' || tag === 'pre' || tag === 'iframe') {
            return true;
        }
        if (el.classList && (el.classList.contains('no-translate') || el.classList.contains('lang-switch-btn') || el.classList.contains('bi'))) {
            return true;
        }
        return false;
    }

    // Translate all text nodes inside a DOM element
    function translateDOM(root, toLang) {
        if (!root) return;

        // TreeWalker for pure text nodes
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                if (!node.nodeValue || !node.nodeValue.trim()) {
                    return NodeFilter.FILTER_REJECT;
                }
                let parent = node.parentElement;
                while (parent && parent !== root) {
                    if (shouldSkipElement(parent)) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    parent = parent.parentElement;
                }
                return NodeFilter.FILTER_ACCEPT;
            }
        });

        const textNodes = [];
        let currentNode;
        while ((currentNode = walker.nextNode())) {
            textNodes.push(currentNode);
        }

        textNodes.forEach(node => {
            const raw = node.nodeValue;
            const trimmed = raw.trim();
            if (!trimmed) return;

            if (toLang === 'tl') {
                if (DICTIONARY[trimmed]) {
                    if (!node._origText) node._origText = raw;
                    node.nodeValue = raw.replace(trimmed, DICTIONARY[trimmed]);
                }
            } else {
                if (node._origText) {
                    node.nodeValue = node._origText;
                } else if (REVERSE_DICTIONARY[trimmed]) {
                    node.nodeValue = raw.replace(trimmed, REVERSE_DICTIONARY[trimmed]);
                }
            }
        });

        // Translate placeholders
        const inputs = root.querySelectorAll('input[placeholder], textarea[placeholder]');
        inputs.forEach(input => {
            const ph = input.getAttribute('placeholder');
            if (!ph) return;
            if (toLang === 'tl') {
                if (DICTIONARY[ph.trim()]) {
                    if (!input.hasAttribute('data-orig-ph')) {
                        input.setAttribute('data-orig-ph', ph);
                    }
                    input.setAttribute('placeholder', DICTIONARY[ph.trim()]);
                }
            } else {
                if (input.hasAttribute('data-orig-ph')) {
                    input.setAttribute('placeholder', input.getAttribute('data-orig-ph'));
                }
            }
        });

        // Translate titles & aria-labels
        const titled = root.querySelectorAll('[title], [aria-label]');
        titled.forEach(el => {
            const t = el.getAttribute('title');
            if (t && DICTIONARY[t.trim()]) {
                if (toLang === 'tl') {
                    if (!el.hasAttribute('data-orig-title')) el.setAttribute('data-orig-title', t);
                    el.setAttribute('title', DICTIONARY[t.trim()]);
                } else if (el.hasAttribute('data-orig-title')) {
                    el.setAttribute('title', el.getAttribute('data-orig-title'));
                }
            }
        });
    }

    // Update Language UI Switchers on the page
    function updateSwitcherUI(lang) {
        document.documentElement.setAttribute('lang', lang);

        const buttons = document.querySelectorAll('.lang-switch-btn, [data-lang-switcher]');
        buttons.forEach(btn => {
            if (lang === 'tl') {
                btn.innerHTML = `<span class="lang-flag">🇵🇭</span> <span class="lang-label">Tagalog</span>`;
                btn.setAttribute('title', 'Kasalukuyan: Tagalog (I-click para sa English)');
                btn.classList.add('active-tl');
            } else {
                btn.innerHTML = `<span class="lang-flag">🇺🇸</span> <span class="lang-label">English</span>`;
                btn.setAttribute('title', 'Current: English (Click to switch to Tagalog)');
                btn.classList.remove('active-tl');
            }
        });
    }

    // Set active language and apply across DOM
    function applyLanguage(lang, triggerSave = true) {
        const target = (lang === 'tl') ? 'tl' : 'en';

        if (triggerSave) {
            localStorage.setItem('agritrace_lang', target);
            setCookie('agritrace_lang', target, 365);
        }

        translateDOM(document.body, target);
        updateSwitcherUI(target);
    }

    // Global toggle function
    window.toggleLanguage = function () {
        const current = getCurrentLanguage();
        const next = (current === 'tl') ? 'en' : 'tl';
        applyLanguage(next, true);
    };

    window.setAgriTraceLanguage = function (lang) {
        applyLanguage(lang, true);
    };

    window.getAgriTraceLanguage = getCurrentLanguage;

    // Injects Language Switcher button into navigation bars if not present
    function injectLanguageSwitcher() {
        // Check if an explicit switcher already exists
        if (document.querySelector('.lang-switch-btn, [data-lang-switcher]')) {
            updateSwitcherUI(getCurrentLanguage());
            return;
        }

        // Try to find natural top navigation header
        const topbar = document.querySelector('.topbar-actions, .top-right, .topbar-right, .navbar-links, .topbar');
        
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'lang-switch-btn';
        btn.onclick = window.toggleLanguage;
        btn.setAttribute('aria-label', 'Switch Language (English / Tagalog)');

        if (topbar) {
            topbar.insertBefore(btn, topbar.firstChild);
        } else {
            // Floating pill fallback in bottom right corner
            const floatingWrap = document.createElement('div');
            floatingWrap.className = 'lang-floating-wrap';
            floatingWrap.appendChild(btn);
            document.body.appendChild(floatingWrap);
        }

        updateSwitcherUI(getCurrentLanguage());
    }

    // Observe dynamic changes (sections toggling, AJAX, modal popups)
    let observerTimeout = null;
    const observer = new MutationObserver((mutations) => {
        const currentLang = getCurrentLanguage();
        if (currentLang !== 'tl') return;

        if (observerTimeout) clearTimeout(observerTimeout);
        observerTimeout = setTimeout(() => {
            mutations.forEach(m => {
                if (m.type === 'childList') {
                    m.addedNodes.forEach(node => {
                        if (node.nodeType === Node.ELEMENT_NODE && !shouldSkipElement(node)) {
                            translateDOM(node, 'tl');
                        }
                    });
                }
            });
        }, 120);
    });

    // Initialize on DOM ready
    function init() {
        injectLanguageSwitcher();
        const current = getCurrentLanguage();
        if (current === 'tl') {
            applyLanguage('tl', false);
        }

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
