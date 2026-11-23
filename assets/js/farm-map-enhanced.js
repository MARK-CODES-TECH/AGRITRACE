/**
 * AgriTrace+ Google Maps GIS Engine
 * Scope: Camarines Sur 1st District (Cabusao, Del Gallego, Lupi, Ragay, Sipocot)
 * Powered by Google Maps JavaScript API
 */

let map;
let infoWindow;
let districtBoundary;
let markerClustererInstance = null;

// Auto-Healing GIS Engine Fallback State
let leafletMap = null;
let leafletFarmLayer = null;
let leafletIncidentLayer = null;
let leafletBufferLayer = null;
let isFallbackActive = false;

let allFarmsData = [];
let allIncidentsData = [];
let farmMarkers = [];
let incidentMarkers = [];
let quarantineCircles = [];

// Camarines Sur 1st District Coordinates & Bounds
const districtCenter = { lat: 13.803, lng: 122.858 };

const districtPolygonCoords = [
    { lat: 13.943, lng: 122.545 },
    { lat: 13.927, lng: 122.675 },
    { lat: 13.855, lng: 122.835 },
    { lat: 13.804, lng: 122.997 },
    { lat: 13.727, lng: 123.148 },
    { lat: 13.668, lng: 123.105 },
    { lat: 13.684, lng: 122.938 },
    { lat: 13.724, lng: 122.754 },
    { lat: 13.815, lng: 122.575 }
];

const municipalityCenters = {
    Cabusao: { lat: 13.7167, lng: 123.1167 },
    'Del Gallego': { lat: 13.9111, lng: 122.5858 },
    Lupi: { lat: 13.7844, lng: 122.8686 },
    Ragay: { lat: 13.8189, lng: 122.7911 },
    Sipocot: { lat: 13.7636, lng: 122.9733 }
};

// Google Maps Light Emerald Custom Styling
const cleanMapStyles = [
    {
        featureType: "poi",
        elementType: "labels",
        stylers: [{ visibility: "off" }]
    },
    {
        featureType: "transit",
        elementType: "labels",
        stylers: [{ visibility: "off" }]
    },
    {
        featureType: "water",
        elementType: "geometry",
        stylers: [{ color: "#dbeafe" }]
    },
    {
        featureType: "landscape.natural",
        elementType: "geometry",
        stylers: [{ color: "#f1f5f9" }]
    },
    {
        featureType: "road",
        elementType: "geometry",
        stylers: [{ color: "#ffffff" }]
    },
    {
        featureType: "road.highway",
        elementType: "geometry",
        stylers: [{ color: "#cbd5e1" }]
    }
];

// Handle Google Maps Authentication Notice gracefully & Auto-Heal to Resilient GIS
window.gm_authFailure = function() {
    console.warn("AgriTrace+ Google Maps Auth Notice: Key requires Maps JavaScript API activation. Activating auto-healing GIS engine.");
    const banner = document.getElementById('gmapsNoticeBanner');
    if (banner) banner.style.display = 'flex';
    triggerSeamlessFallback();
};

// Main Entry called by Google Maps JS API callback
window.initGisMap = function() {
    const mapElement = document.getElementById('map');
    if (!mapElement) return;

    // Observe map container for Google Maps error overlays (e.g. ApiTargetBlockedMapError)
    try {
        const errObserver = new MutationObserver(() => {
            if (mapElement.querySelector('.gm-err-container') || mapElement.querySelector('.gm-err-content')) {
                const banner = document.getElementById('gmapsNoticeBanner');
                if (banner) banner.style.display = 'flex';
                triggerSeamlessFallback();
            }
        });
        errObserver.observe(mapElement, { childList: true, subtree: true });
    } catch (e) {}

    // Safety timeout: if Google Maps encounters restriction errors without firing gm_authFailure
    setTimeout(() => {
        if (!isFallbackActive && (mapElement.querySelector('.gm-err-container') || !map)) {
            triggerSeamlessFallback();
        }
    }, 2000);

    try {
        const sw = new google.maps.LatLng(13.645, 122.505);
        const ne = new google.maps.LatLng(13.955, 123.180);
        const districtBounds = new google.maps.LatLngBounds(sw, ne);

        map = new google.maps.Map(mapElement, {
            center: districtCenter,
            zoom: 11,
            minZoom: 9,
            maxZoom: 20,
            mapTypeId: google.maps.MapTypeId.ROADMAP,
            isFractionalZoomEnabled: true,
            gestureHandling: 'greedy',
            scrollwheel: true,
            keyboardShortcuts: true,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false,
            zoomControl: true,
            zoomControlOptions: {
                position: google.maps.ControlPosition.RIGHT_BOTTOM
            },
            styles: cleanMapStyles
        });

        infoWindow = new google.maps.InfoWindow();

        // 1st District Boundary Polygon (Emerald Stroke, Soft Fill)
        districtBoundary = new google.maps.Polygon({
            paths: districtPolygonCoords,
            strokeColor: '#059669',
            strokeOpacity: 0.9,
            strokeWeight: 2.5,
            fillColor: '#10b981',
            fillOpacity: 0.08,
            clickable: false,
            map: map
        });

        bindControls();
        loadDistrictData();

        setTimeout(() => {
            if (map && districtBounds) {
                map.fitBounds(districtBounds);
            }
        }, 300);

    } catch (err) {
        console.error("Failed to initialize Google Maps:", err);
        triggerSeamlessFallback();
    }
};

// Seamless Auto-Healing Fallback
function triggerSeamlessFallback() {
    if (isFallbackActive) return;
    isFallbackActive = true;
    console.warn("AgriTrace+ Auto-Healing: Mounting resilient GIS layer.");

    const mapElement = document.getElementById('map');
    if (!mapElement) return;

    if (typeof L === 'undefined') {
        hideLoading();
        return;
    }

    // Clean Google Maps elements from container
    mapElement.innerHTML = '';
    map = null;

    leafletMap = L.map('map', {
        center: [13.803, 122.858],
        zoom: 11,
        minZoom: 9,
        maxZoom: 19,
        zoomSnap: 0.25,
        zoomDelta: 0.5,
        wheelPxPerZoomLevel: 120,
        zoomAnimation: true,
        fadeAnimation: true,
        markerZoomAnimation: true,
        zoomControl: true
    });

    const streetLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        attribution: '&copy; CARTO &copy; OpenStreetMap'
    }).addTo(leafletMap);

    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        attribution: '&copy; Esri'
    });

    // Basemap Switcher
    document.querySelectorAll('.base-btn').forEach((btn) => {
        btn.onclick = () => {
            const layerKey = btn.dataset.layer;
            document.querySelectorAll('.base-btn').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            if (layerKey === 'satellite') {
                if (leafletMap.hasLayer(streetLayer)) leafletMap.removeLayer(streetLayer);
                satelliteLayer.addTo(leafletMap);
            } else {
                if (leafletMap.hasLayer(satelliteLayer)) leafletMap.removeLayer(satelliteLayer);
                streetLayer.addTo(leafletMap);
            }
        };
    });

    // District Boundary
    L.polygon(districtPolygonCoords.map(c => [c.lat, c.lng]), {
        color: '#059669',
        weight: 2.5,
        fillColor: '#10b981',
        fillOpacity: 0.08
    }).addTo(leafletMap);

    leafletFarmLayer = L.layerGroup().addTo(leafletMap);
    leafletIncidentLayer = L.layerGroup().addTo(leafletMap);
    leafletBufferLayer = L.layerGroup().addTo(leafletMap);

    leafletMap.fitBounds([[13.645, 122.505], [13.955, 123.180]]);

    bindControls();

    if (allFarmsData.length > 0) {
        applyFilters();
        renderIncidents();
    } else {
        loadDistrictData();
    }

    hideLoading();
}

function bindControls() {
    // Basemap Switcher (Street / Satellite / Terrain)
    document.querySelectorAll('.base-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!map) return;
            const layerKey = btn.dataset.layer;
            document.querySelectorAll('.base-btn').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');

            switch (layerKey) {
                case 'satellite':
                    map.setMapTypeId(google.maps.MapTypeId.HYBRID);
                    break;
                case 'topo':
                    map.setMapTypeId(google.maps.MapTypeId.TERRAIN);
                    break;
                case 'street':
                default:
                    map.setMapTypeId(google.maps.MapTypeId.ROADMAP);
                    break;
            }
        });
    });

    // Municipality Chips
    document.querySelectorAll('.chip-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.chip-btn').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            const muni = btn.dataset.muni;
            const filterEl = document.getElementById('municipalityFilter');
            if (filterEl) filterEl.value = muni;
            applyFilters();
        });
    });

    // Form Filter Listeners
    ['municipalityFilter', 'typeFilter', 'statusFilter', 'minCount', 'maxCount'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', applyFilters);
            el.addEventListener('change', applyFilters);
        }
    });

    // Layer Toggles
    document.getElementById('showClusters')?.addEventListener('change', () => {
        applyFilters();
    });
    document.getElementById('showIncidents')?.addEventListener('change', renderIncidents);
    document.getElementById('showBufferZones')?.addEventListener('change', renderIncidents);

    // Quick Actions
    document.getElementById('resetMapBtn')?.addEventListener('click', resetMap);
    document.getElementById('fitMapBtn')?.addEventListener('click', fitToDistrict);

    // Dock Collapse / Toggle
    const filterDock = document.getElementById('filterDock');
    document.getElementById('toggleFilterBtn')?.addEventListener('click', () => {
        filterDock?.classList.toggle('dock-hidden');
    });
    document.getElementById('closeDockBtn')?.addEventListener('click', () => {
        filterDock?.classList.add('dock-hidden');
    });

    // Inspector Close
    document.getElementById('closeSidebarBtn')?.addEventListener('click', closeInspector);

    // Handle cross-frame messages
    window.addEventListener('message', (event) => {
        if (event.data && (event.data.action === 'resize' || event.data.action === 'refreshMap')) {
            if (map) {
                google.maps.event.trigger(map, 'resize');
                fitToDistrict();
            }
        }
    });
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

function clearFarmMarkers() {
    if (markerClustererInstance) {
        markerClustererInstance.clearMarkers();
    }
    farmMarkers.forEach((m) => m.setMap(null));
    farmMarkers = [];
}

function renderFarms(farms) {
    if (isFallbackActive && leafletMap) {
        if (leafletFarmLayer) leafletFarmLayer.clearLayers();
        farms.forEach((farm) => {
            const lat = parseFloat(farm.latitude);
            const lng = parseFloat(farm.longitude);
            const isApproved = farm.status === 'Approved';
            const color = isApproved ? '#059669' : '#d97706';
            const marker = L.circleMarker([lat, lng], {
                radius: 8,
                color: '#ffffff',
                weight: 2.5,
                fillColor: color,
                fillOpacity: 1
            }).bindPopup(createFarmPopup(farm));
            marker.on('click', () => showFarmInspector(farm));
            leafletFarmLayer.addLayer(marker);
        });
        return;
    }

    if (!map) return;
    clearFarmMarkers();

    const isClustering = document.getElementById('showClusters')?.checked ?? true;

    farms.forEach((farm) => {
        const lat = parseFloat(farm.latitude);
        const lng = parseFloat(farm.longitude);
        const position = { lat, lng };

        const marker = new google.maps.Marker({
            position: position,
            map: isClustering ? null : map,
            title: farm.name || 'Farm Holding',
            icon: createGoogleFarmIcon(farm)
        });

        marker.addListener('click', () => {
            if (infoWindow) {
                infoWindow.setContent(createFarmPopup(farm));
                infoWindow.open(map, marker);
            }
            showFarmInspector(farm);
        });

        farmMarkers.push(marker);
    });

    if (isClustering && typeof markerClusterer !== 'undefined' && markerClusterer.MarkerClusterer) {
        markerClustererInstance = new markerClusterer.MarkerClusterer({
            map: map,
            markers: farmMarkers
        });
    } else {
        farmMarkers.forEach((m) => m.setMap(map));
    }
}

function clearIncidents() {
    incidentMarkers.forEach((m) => m.setMap(null));
    incidentMarkers = [];
    quarantineCircles.forEach((c) => c.setMap(null));
    quarantineCircles = [];
}

function renderIncidents() {
    if (isFallbackActive && leafletMap) {
        if (leafletIncidentLayer) leafletIncidentLayer.clearLayers();
        if (leafletBufferLayer) leafletBufferLayer.clearLayers();
        const showIncidents = document.getElementById('showIncidents')?.checked ?? true;
        const showBuffers = document.getElementById('showBufferZones')?.checked ?? true;
        if (showIncidents) {
            allIncidentsData.filter(isValidCoordinate).forEach((incident) => {
                const lat = parseFloat(incident.latitude);
                const lng = parseFloat(incident.longitude);
                if (showBuffers) {
                    L.circle([lat, lng], { radius: 500, color: '#dc2626', weight: 1.5, fillColor: '#ef4444', fillOpacity: 0.15 }).addTo(leafletBufferLayer);
                    L.circle([lat, lng], { radius: 1000, color: '#d97706', weight: 1, fillColor: '#f59e0b', fillOpacity: 0.06, dashArray: '4,6' }).addTo(leafletBufferLayer);
                }
                L.circleMarker([lat, lng], { radius: 6, color: '#ffffff', weight: 2, fillColor: '#dc2626', fillOpacity: 1 })
                    .bindPopup(createIncidentPopup(incident))
                    .addTo(leafletIncidentLayer);
            });
        }
        updateStats(getFilteredFarms());
        return;
    }

    if (!map) return;
    clearIncidents();

    const showIncidents = document.getElementById('showIncidents')?.checked ?? true;
    const showBuffers = document.getElementById('showBufferZones')?.checked ?? true;

    if (!showIncidents) {
        updateStats(getFilteredFarms());
        return;
    }

    allIncidentsData.filter(isValidCoordinate).forEach((incident) => {
        let lat = parseFloat(incident.latitude);
        let lng = parseFloat(incident.longitude);

        // Slight offset if overlapping farm pin
        const hasOverlappingFarm = allFarmsData.some((f) => {
            const fLat = parseFloat(f.latitude);
            const fLng = parseFloat(f.longitude);
            return Math.abs(fLat - lat) < 0.0003 && Math.abs(fLng - lng) < 0.0003;
        });
        if (hasOverlappingFarm) {
            lng += 0.0008;
            lat -= 0.0002;
        }

        const position = { lat, lng };

        // 500m Quarantine & 1km Surveillance Biosecurity Containment Circles
        if (showBuffers) {
            const quarCircle = new google.maps.Circle({
                map: map,
                center: position,
                radius: 500,
                strokeColor: '#ef4444',
                strokeOpacity: 0.9,
                strokeWeight: 2,
                fillColor: '#ef4444',
                fillOpacity: 0.16,
                clickable: false
            });
            quarantineCircles.push(quarCircle);

            const survCircle = new google.maps.Circle({
                map: map,
                center: position,
                radius: 1000,
                strokeColor: '#f59e0b',
                strokeOpacity: 0.8,
                strokeWeight: 1.5,
                fillColor: '#f59e0b',
                fillOpacity: 0.05,
                clickable: false
            });
            quarantineCircles.push(survCircle);
        }

        const marker = new google.maps.Marker({
            position: position,
            map: map,
            title: incident.title || 'Incident Report',
            icon: createGoogleIncidentIcon(incident)
        });

        marker.addListener('click', () => {
            if (infoWindow) {
                infoWindow.setContent(createIncidentPopup(incident));
                infoWindow.open(map, marker);
            }
        });

        incidentMarkers.push(marker);
    });

    updateStats(getFilteredFarms());
}

function createGoogleFarmIcon(farm) {
    const isApproved = farm.status === 'Approved';
    const fillColor = isApproved ? '#059669' : '#d97706';
    const strokeColor = '#ffffff';

    const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" width="34" height="42" viewBox="0 0 34 42">
            <defs>
                <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
                    <feDropShadow dx="0" dy="3" stdDeviation="3" flood-color="#000" flood-opacity="0.25"/>
                </filter>
            </defs>
            <path d="M17 2 C8.7 2 2 8.7 2 17 C2 27.5 17 40 17 40 C17 40 32 27.5 32 17 C32 8.7 25.3 2 17 2 Z" fill="${fillColor}" stroke="${strokeColor}" stroke-width="2.2" filter="url(#shadow)"/>
            <circle cx="17" cy="17" r="7" fill="#ffffff" />
            <circle cx="17" cy="17" r="4.5" fill="${fillColor}" />
        </svg>
    `;

    return {
        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg.trim()),
        scaledSize: new google.maps.Size(34, 42),
        anchor: new google.maps.Point(17, 40)
    };
}

function createGoogleIncidentIcon(incident) {
    const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 36 36">
            <defs>
                <filter id="alertShadow" x="-20%" y="-20%" width="140%" height="140%">
                    <feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-color="#b91c1c" flood-opacity="0.35"/>
                </filter>
            </defs>
            <polygon points="18,4 33,30 3,30" fill="#dc2626" stroke="#ffffff" stroke-width="2.2" filter="url(#alertShadow)"/>
            <text x="18" y="26" font-family="'DM Sans',sans-serif" font-size="16" font-weight="900" fill="#ffffff" text-anchor="middle">!</text>
        </svg>
    `;

    return {
        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg.trim()),
        scaledSize: new google.maps.Size(34, 34),
        anchor: new google.maps.Point(17, 30)
    };
}

function createFarmPopup(farm) {
    const livestock = parseInt(farm.total_livestock, 10) || 0;
    const owner = farm.ownerName || [farm.firstName, farm.lastName].filter(Boolean).join(' ') || 'Unassigned';

    return `
        <div style="font-family:'DM Sans',sans-serif; min-width:220px; padding:4px;">
            <div style="font-size:0.75rem; color:#059669; font-weight:700; text-transform:uppercase;">${escapeHtml(farm.municipality || 'Camarines Sur')}</div>
            <h4 style="font-size:1rem; font-weight:800; margin:2px 0 8px 0; color:#0f172a;">${escapeHtml(farm.name || 'Unnamed Farm')}</h4>
            <div style="display:grid; grid-template-columns:auto 1fr; gap:4px 10px; font-size:0.8rem;">
                <span style="color:#64748b;">Owner:</span><strong>${escapeHtml(owner)}</strong>
                <span style="color:#64748b;">Type:</span><strong>${escapeHtml(farm.type || 'N/A')}</strong>
                <span style="color:#64748b;">Status:</span><strong style="color:${farm.status === 'Approved' ? '#059669' : '#d97706'}">${escapeHtml(farm.status || 'N/A')}</strong>
                <span style="color:#64748b;">Livestock:</span><strong style="color:#059669;">${livestock} heads</strong>
            </div>
            <button type="button" onclick="showFarmInspectorById(${farm.id})" style="margin-top:10px; width:100%; background:#059669; color:#fff; border:none; padding:6px 12px; border-radius:6px; font-size:0.8rem; font-weight:700; cursor:pointer;">
                View Full Details
            </button>
        </div>
    `;
}

function createIncidentPopup(incident) {
    return `
        <div style="font-family:'DM Sans',sans-serif; min-width:240px; padding:4px;">
            <div style="color:#dc2626; font-size:0.75rem; font-weight:700; text-transform:uppercase;">⚠️ ${escapeHtml(incident.source || 'Incident Alert')}</div>
            <h4 style="font-size:1rem; font-weight:800; margin:2px 0 8px 0; color:#0f172a;">${escapeHtml(incident.title || incident.type || 'Incident Report')}</h4>
            <div style="display:grid; grid-template-columns:auto 1fr; gap:4px 10px; font-size:0.8rem;">
                <span style="color:#64748b;">Status:</span><strong>${escapeHtml(incident.status || 'Pending')}</strong>
                <span style="color:#64748b;">Priority:</span><strong style="color:#dc2626;">${escapeHtml(incident.priority || 'Medium')}</strong>
                <span style="color:#64748b;">Location:</span><strong>${escapeHtml(incident.municipality || '1st District')}</strong>
            </div>
            ${incident.description ? `<p style="margin-top:8px; font-size:0.78rem; color:#475569; border-top:1px solid #e2e8f0; padding-top:6px;">${escapeHtml(incident.description)}</p>` : ''}
        </div>
    `;
}

window.showFarmInspectorById = function(farmId) {
    const farm = allFarmsData.find((f) => parseInt(f.id, 10) === parseInt(farmId, 10));
    if (farm) showFarmInspector(farm);
};

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

function smoothZoomAndPan(targetMap, targetLatLng, targetZoom) {
    if (!targetMap || !Number.isFinite(targetZoom)) return;
    targetMap.panTo(targetLatLng);
    const startZoom = targetMap.getZoom() || 11;
    if (Math.abs(startZoom - targetZoom) < 0.2) return;

    const diff = targetZoom - startZoom;
    const steps = 16;
    let step = 0;
    const interval = setInterval(() => {
        step++;
        const progress = step / steps;
        // Cubic ease-out
        const ease = 1 - Math.pow(1 - progress, 3);
        const currentZoom = startZoom + diff * ease;
        targetMap.setZoom(currentZoom);
        if (step >= steps) {
            clearInterval(interval);
            targetMap.setZoom(targetZoom);
        }
    }, 25);
}

window.flyToLocation = function(lat, lng) {
    if (isFallbackActive && leafletMap) {
        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            leafletMap.flyTo([lat, lng], 15.5, {
                animate: true,
                duration: 1.2,
                easeLinearity: 0.25
            });
        }
        return;
    }
    if (map && Number.isFinite(lat) && Number.isFinite(lng)) {
        smoothZoomAndPan(map, { lat, lng }, 15.5);
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
    if (isFallbackActive && leafletMap) {
        const municipality = document.getElementById('municipalityFilter')?.value || 'all';
        if (municipality !== 'all' && municipalityCenters[municipality]) {
            const c = municipalityCenters[municipality];
            leafletMap.setView([c.lat, c.lng], 13);
            return;
        }
        if (farms.length > 1) {
            const bounds = L.latLngBounds(farms.map((f) => [parseFloat(f.latitude), parseFloat(f.longitude)]));
            leafletMap.fitBounds(bounds, { padding: [50, 50], maxZoom: 15 });
        } else if (farms.length === 1) {
            leafletMap.setView([parseFloat(farms[0].latitude), parseFloat(farms[0].longitude)], 14);
        } else {
            leafletMap.fitBounds([[13.645, 122.505], [13.955, 123.180]]);
        }
        return;
    }

    if (!map) return;
    const municipality = document.getElementById('municipalityFilter')?.value || 'all';

    if (municipality !== 'all' && municipalityCenters[municipality]) {
        map.panTo(municipalityCenters[municipality]);
        map.setZoom(13);
        return;
    }

    if (farms.length > 1) {
        const bounds = new google.maps.LatLngBounds();
        farms.forEach((f) => bounds.extend({ lat: Number(f.latitude), lng: Number(f.longitude) }));
        map.fitBounds(bounds);
    } else if (farms.length === 1) {
        map.panTo({ lat: Number(farms[0].latitude), lng: Number(farms[0].longitude) });
        map.setZoom(14);
    } else {
        fitToDistrict();
    }
}

function fitToDistrict() {
    if (isFallbackActive && leafletMap) {
        leafletMap.fitBounds([[13.645, 122.505], [13.955, 123.180]]);
        return;
    }
    if (!map) return;
    const sw = new google.maps.LatLng(13.645, 122.505);
    const ne = new google.maps.LatLng(13.955, 123.180);
    const districtBounds = new google.maps.LatLngBounds(sw, ne);
    map.fitBounds(districtBounds);
}

function resetMap() {
    document.querySelectorAll('.chip-btn').forEach((b) => b.classList.remove('active'));
    document.querySelector('.chip-btn[data-muni="all"]')?.classList.add('active');
    const mF = document.getElementById('municipalityFilter');
    if (mF) mF.value = 'all';
    const tF = document.getElementById('typeFilter');
    if (tF) tF.value = 'all';
    const sF = document.getElementById('statusFilter');
    if (sF) sF.value = 'all';
    const minC = document.getElementById('minCount');
    if (minC) minC.value = '';
    const maxC = document.getElementById('maxCount');
    if (maxC) maxC.value = '';

    const clus = document.getElementById('showClusters');
    if (clus) clus.checked = true;
    const incs = document.getElementById('showIncidents');
    if (incs) incs.checked = true;

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
    return Number.isFinite(lat) && Number.isFinite(lng) && lat >= 13.6 && lat <= 14.1 && lng >= 122.4 && lng <= 123.3;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
