<?php
require_once __DIR__ . '/../init.php';
header('Content-Type: text/html; charset=utf-8');

$isLoggedIn = !empty($_SESSION['logged_in']);
$userRole = $_SESSION['user_role'] ?? '';

// Determine smart return destination
$dashUrl = match($userRole) {
    'Admin' => '../pages/admin.php#dashboard',
    'Agriculture Official' => '../pages/agriDA.php',
    'Farmer' => '../pages/farmer.php',
    default => ($isLoggedIn ? '../pages/dashboard.php' : '../pages/index.php')
};
$btnLabel = $isLoggedIn ? 'Dashboard' : 'Home';
$btnIcon = $isLoggedIn ? 'bi-speedometer2' : 'bi-house-door-fill';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AgriTrace+ | GIS Map (Camarines Sur 1st District)</title>
    
    <!-- Typography & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Leaflet & Clustering CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css">
    
    <!-- Modern Clean Light Theme Styles -->
    <link rel="stylesheet" href="../assets/map-styles.css?v=<?= filemtime(__DIR__ . '/../assets/map-styles.css') ?>">
</head>
<body class="gis-light-theme">

    <!-- Edge-to-Edge Map Canvas -->
    <div id="map" aria-label="Camarines Sur 1st District GIS Map"></div>

    <!-- Loading Screen -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner-ring"></div>
            <i class="bi bi-geo-alt-fill spinner-icon"></i>
        </div>
        <h2>Loading District Map</h2>
        <p>Loading Camarines Sur 1st District farms and spatial records...</p>
    </div>

    <!-- Top Floating Header Bar (Glass Island) -->
    <header class="top-nav-island">
        <div class="brand-group">
            <div class="brand-badge" title="AgriTrace+ GIS">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                    <path d="M17 8C8 10 5.9 16.17 3.82 21.34L5.71 22l1-2.3A9.49 9.49 0 0 0 12 21c7.22 0 10-6.19 10-12a9 9 0 0 0-5-8c.28 2.53-.16 6.04-1.92 8.7A8.44 8.44 0 0 1 12 12c-2.4 0-4.4-1.5-5.2-3.6C9.1 7.4 12.3 7 15 7h2V8z"/>
                </svg>
            </div>
            <div>
                <div class="brand-title">Agri<span>Trace+</span> <span class="tag-live">● LIVE GIS</span></div>
                <div class="brand-sub">Camarines Sur 1st District • Cabusao, Del Gallego, Lupi, Ragay, Sipocot</div>
            </div>
        </div>

        <!-- Center Quick Stats HUD -->
        <div class="stats-hud">
            <div class="hud-pill">
                <span class="hud-dot dot-farms"></span>
                <span class="hud-label">Farms</span>
                <strong id="farmCount" class="hud-val">0</strong>
            </div>
            <div class="hud-pill">
                <span class="hud-dot dot-livestock"></span>
                <span class="hud-label">Livestock</span>
                <strong id="livestockCount" class="hud-val">0</strong>
            </div>
            <div class="hud-pill">
                <span class="hud-dot dot-incidents"></span>
                <span class="hud-label">Incidents</span>
                <strong id="incidentCount" class="hud-val">0</strong>
            </div>
        </div>

        <!-- Right Quick Actions & Layer Toggles -->
        <div class="nav-actions">
            <!-- Basemap Pill Switcher -->
            <div class="basemap-switch">
                <button type="button" class="base-btn active" data-layer="street" title="Clean Street Map">
                    <i class="bi bi-map-fill"></i> <span>Street</span>
                </button>
                <button type="button" class="base-btn" data-layer="satellite" title="High-Res Satellite Imagery">
                    <i class="bi bi-globe-americas"></i> <span>Satellite</span>
                </button>
                <button type="button" class="base-btn" data-layer="topo" title="Topographic Terrain">
                    <i class="bi bi-triangle-fill"></i> <span>Terrain</span>
                </button>
            </div>

            <button type="button" id="toggleFilterBtn" class="action-circle-btn" title="Toggle Filters">
                <i class="bi bi-sliders"></i>
            </button>

            <button type="button" id="fullscreenMapBtn" class="action-circle-btn" title="Full Map View (Fullscreen)" onclick="toggleMapFullscreen()">
                <i class="bi bi-arrows-fullscreen"></i>
            </button>

            <a href="<?= htmlspecialchars($dashUrl) ?>" target="_parent" id="backToDashBtn" class="action-home-btn" title="Back to <?= htmlspecialchars($btnLabel) ?>" onclick="handleMapHome(event)">
                <i class="bi <?= htmlspecialchars($btnIcon) ?>"></i>
                <span><?= htmlspecialchars($btnLabel) ?></span>
            </a>
        </div>
    </header>

    <!-- Floating Filter Dock (Left) -->
    <aside id="filterDock" class="filter-dock dock-hidden">
        <div class="dock-header">
            <div class="dock-title">
                <i class="bi bi-funnel-fill"></i>
                <span>Map Filters</span>
            </div>
            <button type="button" id="closeDockBtn" class="dock-close" title="Minimize Dock">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>

        <div class="dock-body">
            <!-- Municipality Buttons -->
            <div class="filter-section">
                <label class="section-label"><i class="bi bi-pin-map"></i> Municipality</label>
                <div class="muni-chips">
                    <button type="button" class="chip-btn active" data-muni="all">All District</button>
                    <button type="button" class="chip-btn" data-muni="Ragay">Ragay</button>
                    <button type="button" class="chip-btn" data-muni="Sipocot">Sipocot</button>
                    <button type="button" class="chip-btn" data-muni="Lupi">Lupi</button>
                    <button type="button" class="chip-btn" data-muni="Del Gallego">Del Gallego</button>
                    <button type="button" class="chip-btn" data-muni="Cabusao">Cabusao</button>
                </div>
                <input type="hidden" id="municipalityFilter" value="all">
            </div>

            <!-- Farm Type & Status -->
            <div class="filter-section grid-2">
                <div>
                    <label class="section-label" for="typeFilter">Farm Type</label>
                    <select id="typeFilter" class="dock-select">
                        <option value="all">All Types</option>
                        <option value="Cattle">Cattle</option>
                        <option value="Swine">Swine</option>
                        <option value="Poultry">Poultry</option>
                        <option value="Goat">Goat</option>
                        <option value="Mixed">Mixed</option>
                    </select>
                </div>
                <div>
                    <label class="section-label" for="statusFilter">Status</label>
                    <select id="statusFilter" class="dock-select">
                        <option value="all">All Statuses</option>
                        <option value="Approved" selected>Approved</option>
                        <option value="Pending">Pending</option>
                    </select>
                </div>
            </div>

            <!-- Range Filter -->
            <div class="filter-section">
                <label class="section-label">Livestock Count</label>
                <div class="range-inputs">
                    <input id="minCount" type="number" min="0" placeholder="Min" class="dock-input">
                    <span class="range-sep">—</span>
                    <input id="maxCount" type="number" min="0" placeholder="Max" class="dock-input">
                </div>
            </div>

            <!-- Visual Toggles -->
            <div class="filter-section">
                <label class="dock-toggle">
                    <input id="showClusters" type="checkbox" checked>
                    <span class="toggle-slider"></span>
                    <span class="toggle-text"><i class="bi bi-bounding-box-circles"></i> Cluster dense markers</span>
                </label>
                <label class="dock-toggle">
                    <input id="showIncidents" type="checkbox" checked>
                    <span class="toggle-slider"></span>
                    <span class="toggle-text"><i class="bi bi-exclamation-triangle-fill" style="color:#ef4444;"></i> Show incident reports</span>
                </label>
                <label class="dock-toggle">
                    <input id="showBufferZones" type="checkbox" checked>
                    <span class="toggle-slider"></span>
                    <span class="toggle-text"><i class="bi bi-shield-shaded" style="color:#ef4444;"></i> BAI Buffer Zones (1km/7km)</span>
                </label>
            </div>

            <!-- Quick Action Buttons -->
            <div class="dock-actions">
                <button type="button" id="resetMapBtn" class="btn-dock-sec">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </button>
                <button type="button" id="fitMapBtn" class="btn-dock-prim">
                    <i class="bi bi-arrows-fullscreen"></i> Fit District
                </button>
            </div>
        </div>
    </aside>

    <!-- Floating Bottom Legend Pill -->
    <div class="bottom-legend-dock">
        <div class="legend-chip"><span class="dot-lg dot-approved"></span> Approved Farm</div>
        <div class="legend-chip"><span class="dot-lg dot-pending"></span> Pending Farm</div>
        <div class="legend-chip"><span class="icon-lg text-red-500">▲</span> Incident Alert</div>
        <div class="legend-chip"><span class="ring-sm ring-quarantine"></span> 1km Quarantine Ring</div>
        <div class="legend-chip"><span class="ring-sm ring-surveillance"></span> 7km Surveillance Zone</div>
        <div class="legend-chip"><span class="dash-lg"></span> 1st District Scope</div>
    </div>

    <!-- Slide-Out Farm Details Inspector (Right) -->
    <aside id="sidebar" class="farm-inspector-pane" aria-label="Farm Inspector">
        <div class="inspector-header">
            <div>
                <span class="inspector-eyebrow">Farm Intelligence</span>
                <h3 id="inspFarmTitle">Farm Details</h3>
            </div>
            <button id="closeSidebarBtn" type="button" class="inspector-close" aria-label="Close Inspector">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div id="farmDetails" class="inspector-content"></div>
    </aside>

    <!-- Leaflet & Clustering Dependencies -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
    
    <!-- Clean Light GIS Engine Script -->
    <script src="../assets/js/farm-map-enhanced.js?v=<?= filemtime(__DIR__ . '/../assets/js/farm-map-enhanced.js') ?>"></script>

    <script>
        // Full Map View / Native Fullscreen Engine
        function toggleMapFullscreen() {
            const doc = window.document;
            const docEl = doc.documentElement;
            const requestFS = docEl.requestFullscreen || docEl.mozRequestFullScreen || docEl.webkitRequestFullScreen || docEl.msRequestFullscreen;
            const cancelFS = doc.exitFullscreen || doc.mozCancelFullScreen || doc.webkitExitFullscreen || doc.msExitFullscreen;

            if (!doc.fullscreenElement && !doc.mozFullScreenElement && !doc.webkitFullscreenElement && !doc.msFullscreenElement) {
                if (requestFS) {
                    requestFS.call(docEl);
                } else if (window.self !== window.top) {
                    window.open(window.location.href, '_blank');
                }
            } else {
                if (cancelFS) cancelFS.call(doc);
            }
        }

        ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'].forEach(ev => {
            document.addEventListener(ev, () => {
                const isFS = !!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement);
                const btn = document.getElementById('fullscreenMapBtn');
                if (btn) {
                    btn.innerHTML = `<i class="bi bi-${isFS ? 'fullscreen-exit' : 'arrows-fullscreen'}"></i>`;
                    btn.setAttribute('title', isFS ? 'Exit Full Map View' : 'Full Map View (Fullscreen)');
                }
                setTimeout(() => { if (typeof map !== 'undefined' && map) map.invalidateSize(); }, 200);
            });
        });

        // Smart Dashboard / Parent Frame Return Engine
        function handleMapHome(e) {
            // Check if map is embedded inside an iframe (like the Admin or DA Official panel)
            if (window.self !== window.top) {
                e.preventDefault();
                
                // Method 1: Send postMessage to parent container
                try {
                    window.parent.postMessage({ action: 'nav', section: 'dashboard' }, '*');
                } catch (err) {}

                // Method 2: Direct call to parent's activateSection function
                try {
                    if (window.parent && typeof window.parent.activateSection === 'function') {
                        window.parent.activateSection('dashboard');
                        return false;
                    }
                } catch (err) {}

                // Method 3: Fallback navigation on parent window
                try {
                    window.parent.location.href = e.currentTarget.getAttribute('href');
                } catch (err) {
                    window.top.location.href = e.currentTarget.getAttribute('href');
                }
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
