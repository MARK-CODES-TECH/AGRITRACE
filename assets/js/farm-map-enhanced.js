/**
 * AgriTrace+ Clean GIS Engine (Light Theme)
 * Scope: Camarines Sur 1st District (Cabusao, Del Gallego, Lupi, Ragay, Sipocot)
 */

let map;
let activeBaseLayer;
let baseLayers = {};
let districtBoundary;
let farmClusterGroup;
let farmLayerGroup;
let incidentLayerGroup;
let bufferLayerGroup;
let allFarmsData = [];
let allIncidentsData = [];

// Camarines Sur 1st District Center & Geographical Bounds
const districtCenter = [13.803, 122.858];
const districtBounds = L.latLngBounds([13.645, 122.505], [13.955, 123.18]);

// Accurate 1st District Boundary Polygon Coordinates
const districtPolygon = [
    [13.943, 122.545],
    [13.927, 122.675],
    [13.855, 122.835],
    [13.804, 122.997],
    [13.727, 123.148],
    [13.668, 123.105],
    [13.684, 122.938],
    [13.724, 122.754],
    [13.815, 122.575]
];

const municipalityCenters = {
    Cabusao: [13.7167, 123.1167],
    'Del Gallego': [13.9111, 122.5858],
    Lupi: [13.7844, 122.8686],
    Ragay: [13.8189, 122.7911],
    Sipocot: [13.7636, 122.9733]
};

document.addEventListener('DOMContentLoaded', () => {
    initGisMap();
    bindControls();
    loadDistrictData();
});

function initGisMap() {
    map = L.map('map', {
        center: districtCenter,
        zoom: 11,
        minZoom: 9,
        maxZoom: 18,
        zoomControl: false,
        attributionControl: true,

        // ── HARDWARE-ACCELERATED SILKY SMOOTH ZOOM & DEVICE GESTURES ──────
        preferCanvas: true,            // GPU HTML5 Canvas for all vector paths (polygons, circles)
        zoomSnap: 0.25,                // Fractional zoom: fluid pinch-to-zoom on touchscreens & precision trackpads
        zoomDelta: 0.5,                // Half-step smooth button zoom (no sudden 2x jarring jumps)
        wheelPxPerZoomLevel: 120,      // Controlled mousewheel zoom speed (prevents erratic jumping)
        wheelDebounceTime: 40,         // Debounce wheel scroll events for smooth 60fps frame rate
        zoomAnimation: true,           // Hardware-accelerated CSS3 zoom transitions
        zoomAnimationThreshold: 8,     // Keep zoom animation active even across large zoom level jumps
        fadeAnimation: true,           // Smooth cross-fade transition between tile layers
        markerZoomAnimation: true,     // Animate marker coordinates fluidly during zoom
        inertia: true,                 // Smooth physics momentum panning
        inertiaDeceleration: 3000,     // Natural deceleration curve
        inertiaMaxSpeed: 1800,
        easeLinearity: 0.2,
        bounceAtZoomLimits: true,
        tap: false,                    // Modern touch handling without legacy 300ms tap delay
        touchZoom: true,
        doubleClickZoom: true,
        scrollWheelZoom: true
    });

    // Zoom controls on bottom-left
    L.control.zoom({ position: 'bottomleft' }).addTo(map);

    // Clean, Edge-Cached Fast Basemaps with tile buffering
    baseLayers = {
        street: L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a> | AgriTrace+ GIS',
            subdomains: 'abcd',
            maxZoom: 19,
            keepBuffer: 10,              // Keep 10 rows/cols of cached tiles to prevent grey boxes during zoom
            updateWhenZooming: false,     // Don't thrash tile requests during 60fps zoom animation
            updateWhenIdle: true,         // Wait for smooth pan/zoom to settle before requesting new tiles
            updateInterval: 120
        }),
        satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri, Maxar | AgriTrace+ GIS',
            maxZoom: 19,
            keepBuffer: 8,
            updateWhenZooming: false,
            updateWhenIdle: true,
            updateInterval: 120
        }),
        topo: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri | AgriTrace+ GIS',
            maxZoom: 18,
            keepBuffer: 8,
            updateWhenZooming: false,
            updateWhenIdle: true,
            updateInterval: 120
        })
    };

    // Default to clean, bright Street view
    activeBaseLayer = baseLayers.street;
    activeBaseLayer.addTo(map);

    // District Boundary with Crisp Emerald Line & Soft Green Fill
    districtBoundary = L.polygon(districtPolygon, {
        color: '#059669',
        weight: 3,
        opacity: 0.9,
        fillColor: '#10b981',
        fillOpacity: 0.08,
        dashArray: '8, 6',
        interactive: false
    }).addTo(map);

    // Layer groups for markers & spatial containment
    farmLayerGroup = L.layerGroup();
    incidentLayerGroup = L.layerGroup().addTo(map);
    bufferLayerGroup = L.layerGroup().addTo(map);

    if (typeof L.markerClusterGroup === 'function') {
        farmClusterGroup = L.markerClusterGroup({
            showCoverageOnHover: false,
            maxClusterRadius: 40,
            disableClusteringAtZoom: 15,
            chunkedLoading: true,             // Background processing keeps 60fps frame rate
            chunkInterval: 40,
            chunkDelay: 20,
            removeOutsideVisibleBounds: true, // Only render cluster nodes inside visible bounds
            animate: true,
            animateAddingMarkers: false
        });
        map.addLayer(farmClusterGroup);
    } else {
        farmClusterGroup = L.layerGroup().addTo(map);
    }

    // Automatic Resize & Visibility Engine for embedded iframe/tab integration
    if (window.ResizeObserver) {
        const resizeObserver = new ResizeObserver(() => {
            if (map) map.invalidateSize();
        });
        const mapContainer = document.getElementById('map');
        if (mapContainer) resizeObserver.observe(mapContainer);
    }
    if (window.IntersectionObserver) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting && map) map.invalidateSize();
            });
        });
        const mapContainer = document.getElementById('map');
        if (mapContainer) io.observe(mapContainer);
    }
    window.addEventListener('resize', () => { if (map) map.invalidateSize(); });
    window.addEventListener('focus', () => { if (map) map.invalidateSize(); });
    window.addEventListener('message', (event) => {
        if (event.data && (event.data.action === 'resize' || event.data.action === 'refreshMap')) {
            if (map) {
                map.invalidateSize();
                if (districtBounds) {
                    map.fitBounds(districtBounds, { padding: [30, 30], maxZoom: 13 });
                }
            }
        }
    });

    let resizeAttempts = 0;
    const resizeInterval = setInterval(() => {
        if (map) {
            map.invalidateSize();
            resizeAttempts++;
            if (resizeAttempts > 6) clearInterval(resizeInterval);
        }
    }, 400);
}

function bindControls() {
    // Basemap Switcher
    document.querySelectorAll('.base-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const layerKey = btn.dataset.layer;
            if (!baseLayers[layerKey] || activeBaseLayer === baseLayers[layerKey]) return;

            map.removeLayer(activeBaseLayer);
            activeBaseLayer = baseLayers[layerKey];
            activeBaseLayer.addTo(map);

            document.querySelectorAll('.base-btn').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
        });
    });

    // Municipality Chips
    document.querySelectorAll('.chip-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.chip-btn').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            const muni = btn.dataset.muni;
            document.getElementById('municipalityFilter').value = muni;
            applyFilters();
        });
    });

    // Form Filter Listeners
    ['typeFilter', 'statusFilter', 'minCount', 'maxCount'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', applyFilters);
            el.addEventListener('change', applyFilters);
        }
    });

    // Layer Toggles
    document.getElementById('showClusters')?.addEventListener('change', () => {
        renderFarms(getFilteredFarms());
    });
    document.getElementById('showIncidents')?.addEventListener('change', renderIncidents);
    document.getElementById('showBufferZones')?.addEventListener('change', renderIncidents);

    // Quick Actions
    document.getElementById('resetMapBtn')?.addEventListener('click', resetMap);
    document.getElementById('fitMapBtn')?.addEventListener('click', fitToDistrict);

    // Dock Collapse / Toggle
    const filterDock = document.getElementById('filterDock');
    document.getElementById('toggleFilterBtn')?.addEventListener('click', () => {
        filterDock.classList.toggle('dock-hidden');
    });
    document.getElementById('closeDockBtn')?.addEventListener('click', () => {
        filterDock.classList.add('dock-hidden');
    });

    // Inspector Close
    document.getElementById('closeSidebarBtn')?.addEventListener('click', closeInspector);
}

async function loadDistrictData() {
    showLoading();

    try {
        const [farmsResponse, incidentsResponse] = await Promise.all([
            fetch('../api/db.php?action=getFirstDistrictMapData'),
            fetch('../api/db.php?action=getFirstDistrictIncidents')
        ]);

        const farmsResult = await farmsResponse.json();
        const incidentsResult = await incidentsResponse.json();

        allFarmsData = Array.isArray(farmsResult.data) ? farmsResult.data : [];
        allIncidentsData = Array.isArray(incidentsResult.data) ? incidentsResult.data : [];

        applyFilters();
        renderIncidents();
    } catch (error) {
        console.error('GIS data load failed:', error);
    } finally {
        hideLoading();
    }
}

function getFilteredFarms() {
    const municipality = document.getElementById('municipalityFilter')?.value || 'all';
    const type = document.getElementById('typeFilter')?.value || 'all';
    const status = document.getElementById('statusFilter')?.value || 'all';
    const minCount = parseInt(document.getElementById('minCount')?.value, 10);
    const maxCount = parseInt(document.getElementById('maxCount')?.value, 10);

    return allFarmsData.filter((farm) => {
        const livestock = parseInt(farm.total_livestock, 10) || 0;

        if (municipality !== 'all' && farm.municipality !== municipality) return false;
        if (type !== 'all' && farm.type !== type) return false;
        if (status !== 'all' && farm.status !== status) return false;
        if (!Number.isNaN(minCount) && livestock < minCount) return false;
        if (!Number.isNaN(maxCount) && livestock > maxCount) return false;

        return isValidCoordinate(farm);
    });
}

function applyFilters() {
    const farms = getFilteredFarms();
    renderFarms(farms);
    updateStats(farms);
    focusFilteredArea(farms);
}

function renderFarms(farms) {
    farmClusterGroup.clearLayers();
    farmLayerGroup.clearLayers();

    const isClustering = document.getElementById('showClusters')?.checked ?? true;

    farms.forEach((farm) => {
        const lat = parseFloat(farm.latitude);
        const lng = parseFloat(farm.longitude);
        const marker = L.marker([lat, lng], {
            icon: createModernFarmIcon(farm),
            title: farm.name || 'Farm'
        });

        marker.bindPopup(createFarmPopup(farm), {
            className: 'custom-leaflet-popup',
            maxWidth: 280
        });

        marker.on('click', () => showFarmInspector(farm));

        if (isClustering) {
            farmClusterGroup.addLayer(marker);
        } else {
            farmLayerGroup.addLayer(marker);
        }
    });

    if (isClustering) {
        if (map.hasLayer(farmLayerGroup)) map.removeLayer(farmLayerGroup);
        if (!map.hasLayer(farmClusterGroup)) map.addLayer(farmClusterGroup);
    } else {
        if (map.hasLayer(farmClusterGroup)) map.removeLayer(farmClusterGroup);
        if (!map.hasLayer(farmLayerGroup)) map.addLayer(farmLayerGroup);
    }
}

function renderIncidents() {
    incidentLayerGroup.clearLayers();
    bufferLayerGroup.clearLayers();

    const showIncidents = document.getElementById('showIncidents')?.checked ?? true;
    const showBuffers = document.getElementById('showBufferZones')?.checked ?? true;

    if (!showIncidents) {
        updateStats(getFilteredFarms());
        return;
    }

    const validFarms = allFarmsData.filter(isValidCoordinate);

    allIncidentsData.filter(isValidCoordinate).forEach((incident) => {
        let lat = parseFloat(incident.latitude);
        let lng = parseFloat(incident.longitude);

        // Check if there is an overlapping farm pin (close within ~30 meters)
        const hasOverlappingFarm = allFarmsData.some((f) => {
            const fLat = parseFloat(f.latitude);
            const fLng = parseFloat(f.longitude);
            return Math.abs(fLat - lat) < 0.0003 && Math.abs(fLng - lng) < 0.0003;
        });

        // Offset slightly eastward so both pins sit side-by-side cleanly
        if (hasOverlappingFarm) {
            lng += 0.0008;
            lat -= 0.0002;
        }

        // Draw Bureau of Animal Industry (BAI) Compliant Containment Buffer Zones
        if (showBuffers) {
            const farms1km = validFarms.filter(f => map.distance([lat, lng], [parseFloat(f.latitude), parseFloat(f.longitude)]) <= 1000);
            const farms7km = validFarms.filter(f => map.distance([lat, lng], [parseFloat(f.latitude), parseFloat(f.longitude)]) <= 7000);

            // 7km Surveillance Zone (Amber dashed ring)
            const survCircle = L.circle([lat, lng], {
                radius: 7000,
                color: '#f59e0b',
                fillColor: '#f59e0b',
                fillOpacity: 0.06,
                weight: 1.5,
                dashArray: '6, 8',
                interactive: true
            });
            survCircle.bindTooltip(`
                <div style="font-family:'DM Sans',sans-serif; padding:2px;">
                    <strong style="color:#d97706; display:flex; align-items:center; gap:4px;">
                        <i class="bi bi-shield-shaded"></i> 7km BAI Surveillance Zone
                    </strong>
                    <div style="font-size:0.75rem; color:#475569; margin-top:2px;">
                        Scope: <strong>${farms7km.length} farm(s)</strong> under clinical observation
                    </div>
                </div>
            `, { sticky: true, opacity: 0.95 });
            bufferLayerGroup.addLayer(survCircle);

            // 1km Quarantine Ring (Red containment ring)
            const quarCircle = L.circle([lat, lng], {
                radius: 1000,
                color: '#ef4444',
                fillColor: '#ef4444',
                fillOpacity: 0.16,
                weight: 2,
                dashArray: '4, 5',
                interactive: true
            });
            quarCircle.bindTooltip(`
                <div style="font-family:'DM Sans',sans-serif; padding:2px;">
                    <strong style="color:#dc2626; display:flex; align-items:center; gap:4px;">
                        <i class="bi bi-exclamation-octagon-fill"></i> 1km Quarantine Containment Ring
                    </strong>
                    <div style="font-size:0.75rem; color:#475569; margin-top:2px;">
                        Perimeter: <strong>${farms1km.length} farm(s)</strong> within movement ban
                    </div>
                </div>
            `, { sticky: true, opacity: 0.95 });
            bufferLayerGroup.addLayer(quarCircle);
        }

        const marker = L.marker([lat, lng], {
            icon: createModernIncidentIcon(incident),
            title: incident.title || 'Incident Report'
        });

        marker.bindPopup(createIncidentPopup(incident), {
            className: 'custom-leaflet-popup',
            maxWidth: 300
        });

        incidentLayerGroup.addLayer(marker);
    });

    updateStats(getFilteredFarms());
}

function createModernFarmIcon(farm) {
    const livestock = parseInt(farm.total_livestock, 10) || 0;
    const isApproved = farm.status === 'Approved';
    const statusClass = isApproved ? 'approved' : 'pending';
    const labelContent = livestock > 0 
        ? (livestock > 99 ? '99+' : livestock) 
        : '<i class="bi bi-house-door-fill" style="font-size:0.75rem;"></i>';

    return L.divIcon({
        className: 'custom-farm-pin',
        html: `
            <div class="pin-anchor">
                <div class="pin-aura ${statusClass}"></div>
                <div class="pin-head ${statusClass}">
                    <span class="pin-label">${labelContent}</span>
                </div>
            </div>
        `,
        iconSize: [36, 44],
        iconAnchor: [18, 42],
        popupAnchor: [0, -36]
    });
}

function createModernIncidentIcon(incident) {
    return L.divIcon({
        className: 'custom-farm-pin',
        html: `
            <div class="incident-hazard-pin">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
        `,
        iconSize: [32, 32],
        iconAnchor: [16, 16],
        popupAnchor: [0, -16]
    });
}

function createFarmPopup(farm) {
    const livestock = parseInt(farm.total_livestock, 10) || 0;
    const owner = farm.ownerName || [farm.firstName, farm.lastName].filter(Boolean).join(' ') || 'Unassigned';

    return `
        <div>
            <div class="popup-kicker">${escapeHtml(farm.municipality || 'Camarines Sur')}</div>
            <h4 class="popup-title">${escapeHtml(farm.name || 'Unnamed Farm')}</h4>
            <div class="popup-grid">
                <span>Owner</span><strong>${escapeHtml(owner)}</strong>
                <span>Type</span><strong>${escapeHtml(farm.type || 'N/A')}</strong>
                <span>Status</span><strong style="color:${farm.status === 'Approved' ? '#059669' : '#d97706'}">${escapeHtml(farm.status || 'N/A')}</strong>
                <span>Livestock</span><strong style="color:#059669">${livestock} heads</strong>
            </div>
        </div>
    `;
}

function createIncidentPopup(incident) {
    let bufferStats = '';
    if (isValidCoordinate(incident)) {
        const lat = parseFloat(incident.latitude);
        const lng = parseFloat(incident.longitude);
        const validFarms = allFarmsData.filter(isValidCoordinate);
        const farms1km = validFarms.filter(f => map.distance([lat, lng], [parseFloat(f.latitude), parseFloat(f.longitude)]) <= 1000);
        const farms7km = validFarms.filter(f => map.distance([lat, lng], [parseFloat(f.latitude), parseFloat(f.longitude)]) <= 7000);

        bufferStats = `
            <div style="margin-top:8px; padding-top:6px; border-top:1px solid #e2e8f0; font-size:0.75rem;">
                <div style="color:#b91c1c; font-weight:700; margin-bottom:3px; display:flex; align-items:center; gap:4px;">
                    <i class="bi bi-shield-exclamation"></i> BAI Outbreak Containment:
                </div>
                <div style="display:flex; justify-content:space-between; color:#475569; margin-bottom:2px;">
                    <span>1km Quarantine Ring:</span>
                    <strong style="color:#ef4444;">${farms1km.length} farm(s)</strong>
                </div>
                <div style="display:flex; justify-content:space-between; color:#475569;">
                    <span>7km Surveillance Buffer:</span>
                    <strong style="color:#d97706;">${farms7km.length} farm(s)</strong>
                </div>
            </div>
        `;
    }

    return `
        <div>
            <div class="popup-kicker" style="color:#dc2626;">⚠️ ${escapeHtml(incident.source || 'Incident')}</div>
            <h4 class="popup-title">${escapeHtml(incident.title || incident.type || 'Incident Report')}</h4>
            <div class="popup-grid">
                <span>Status</span><strong>${escapeHtml(incident.status || 'Pending')}</strong>
                <span>Priority</span><strong style="color:#dc2626">${escapeHtml(incident.priority || 'Public')}</strong>
                <span>Location</span><strong>${escapeHtml(incident.municipality || '1st District')}</strong>
            </div>
            ${incident.description ? `<p style="margin-top:8px;font-size:0.78rem;color:#475569;border-top:1px solid #e2e8f0;padding-top:6px;">${escapeHtml(incident.description)}</p>` : ''}
            ${bufferStats}
        </div>
    `;
}

function showFarmInspector(farm) {
    const sidebar = document.getElementById('sidebar');
    const details = document.getElementById('farmDetails');
    const title = document.getElementById('inspFarmTitle');
    if (!sidebar || !details) return;

    const livestock = parseInt(farm.total_livestock, 10) || 0;
    const owner = farm.ownerName || [farm.firstName, farm.lastName].filter(Boolean).join(' ') || 'Unassigned';

    if (title) title.textContent = farm.name || 'Farm Details';

    details.innerHTML = `
        <div class="detail-block">
            <h4>Overview</h4>
            <div class="detail-row"><span>Owner</span><strong>${escapeHtml(owner)}</strong></div>
            <div class="detail-row"><span>Municipality</span><strong>${escapeHtml(farm.municipality || 'Ragay')}</strong></div>
            <div class="detail-row"><span>Farm Type</span><strong>${escapeHtml(farm.type || 'Mixed')}</strong></div>
            <div class="detail-row"><span>Status</span><strong style="color:${farm.status === 'Approved' ? '#059669' : '#d97706'}">${escapeHtml(farm.status || 'Pending')}</strong></div>
            <div class="detail-row"><span>Total Area</span><strong>${farm.area ? Number(farm.area).toFixed(2) + ' ha' : 'N/A'}</strong></div>
        </div>

        <div class="detail-block">
            <h4>Livestock Inventory</h4>
            <div class="detail-row"><span>Total Count</span><strong style="color:#059669;font-size:1.05rem;">${livestock} heads</strong></div>
            <div class="detail-row"><span>Categories</span><strong>${escapeHtml(farm.livestock_types || 'Registered')}</strong></div>
        </div>

        <div class="detail-block">
            <h4>Geographical Coordinates</h4>
            <div class="detail-row"><span>Address</span><strong style="text-align:right;">${escapeHtml(farm.address || 'Camarines Sur')}</strong></div>
            <div class="detail-row"><span>GPS</span><code>${Number(farm.latitude).toFixed(5)}, ${Number(farm.longitude).toFixed(5)}</code></div>
        </div>

        <button type="button" class="btn-focus-farm" onclick="flyToLocation(${Number(farm.latitude)}, ${Number(farm.longitude)})">
            <i class="bi bi-crosshair2"></i> Focus Camera on Farm
        </button>
    `;

    sidebar.classList.add('open');
}

window.flyToLocation = function(lat, lng) {
    if (map && Number.isFinite(lat) && Number.isFinite(lng)) {
        map.flyTo([lat, lng], 16, { animate: true, duration: 1.2 });
    }
};

function closeInspector() {
    document.getElementById('sidebar')?.classList.remove('open');
}

function updateStats(farms) {
    const visibleIncidents = document.getElementById('showIncidents')?.checked
        ? allIncidentsData.filter(isValidCoordinate).length
        : 0;
    const totalLivestock = farms.reduce((sum, farm) => sum + (parseInt(farm.total_livestock, 10) || 0), 0);

    const fEl = document.getElementById('farmCount');
    const lEl = document.getElementById('livestockCount');
    const iEl = document.getElementById('incidentCount');
    if (fEl) fEl.textContent = farms.length.toLocaleString();
    if (lEl) lEl.textContent = totalLivestock.toLocaleString();
    if (iEl) iEl.textContent = visibleIncidents.toLocaleString();
}

function focusFilteredArea(farms) {
    const municipality = document.getElementById('municipalityFilter')?.value || 'all';

    if (municipality !== 'all' && municipalityCenters[municipality]) {
        map.flyTo(municipalityCenters[municipality], 13, { animate: true, duration: 1 });
        return;
    }

    if (farms.length > 1) {
        const bounds = L.latLngBounds(farms.map((f) => [Number(f.latitude), Number(f.longitude)]));
        map.fitBounds(bounds.pad(0.2), { padding: [80, 80], maxZoom: 14 });
    } else if (farms.length === 1) {
        map.flyTo([Number(farms[0].latitude), Number(farms[0].longitude)], 14, { animate: true, duration: 1 });
    } else {
        fitToDistrict();
    }
}

function fitToDistrict() {
    map.fitBounds(districtBounds, { padding: [40, 40], animate: true });
}

function resetMap() {
    document.querySelectorAll('.chip-btn').forEach((b) => b.classList.remove('active'));
    document.querySelector('.chip-btn[data-muni="all"]')?.classList.add('active');
    document.getElementById('municipalityFilter').value = 'all';
    document.getElementById('typeFilter').value = 'all';
    document.getElementById('statusFilter').value = 'all';
    document.getElementById('minCount').value = '';
    document.getElementById('maxCount').value = '';
    document.getElementById('showClusters').checked = true;
    document.getElementById('showIncidents').checked = true;

    closeInspector();
    applyFilters();
    renderIncidents();
    fitToDistrict();
}

function showLoading() {
    const el = document.getElementById('loadingOverlay');
    if (el) { el.style.opacity = '1'; el.style.visibility = 'visible'; }
}

function hideLoading() {
    const el = document.getElementById('loadingOverlay');
    if (el) {
        el.style.opacity = '0';
        el.style.visibility = 'hidden';
        el.style.pointerEvents = 'none';
    }
}

function isValidCoordinate(item) {
    const lat = Number(item.latitude);
    const lng = Number(item.longitude);
    return Number.isFinite(lat) && Number.isFinite(lng) && districtBounds.contains([lat, lng]);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
