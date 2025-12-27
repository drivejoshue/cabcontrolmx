/* resources/js/pages/dispatch/map.js */
import { qs, isDarkMode, TENANT_ICONS, CENTER, MAP_ZOOM, OSM } from './core.js';

let map;
let layerSectores, layerStands, layerRoute, layerDrivers, layerSuggested;
let fromMarker = null, toMarker = null;
let stop1Marker = null, stop2Marker = null;

export const IconOrigin = L.icon({
  iconUrl: TENANT_ICONS.origin,
  iconSize: [30, 30],
  iconAnchor: [15, 30],
  popupAnchor: [0, -26]
});

export const IconDest = L.icon({
  iconUrl: TENANT_ICONS.dest,
  iconSize: [30, 30],
  iconAnchor: [15, 30],
  popupAnchor: [0, -26]
});

export const IconStand = L.icon({
  iconUrl: TENANT_ICONS.stand,
  iconSize: [28, 28],
  iconAnchor: [14, 28],
  popupAnchor: [0, -24]
});

export const IconStop = L.icon({
  iconUrl: TENANT_ICONS.stop,
  iconSize: [28, 28],
  iconAnchor: [14, 28],
  popupAnchor: [0, -24]
});

export const routeStyle = () => (isDarkMode()
  ? { color: '#943DD4', weight: 5, opacity: .95 }
  : { color: '#0717F0', weight: 4, opacity: .95 }
);

export const sectorStyle = () => (isDarkMode()
  ? { color: '#FF6B6B', fillColor: '#FF6B6B', fillOpacity: .12, weight: 2 }
  : { color: '#2A9DF4', fillColor: '#2A9DF4', fillOpacity: .18, weight: 2 }
);

export const rideMarkers = new Map();

export function initMap() {
  const mapEl = document.getElementById('map');
  if (!mapEl) return;

  mapEl.classList.toggle('map-dark', isDarkMode());

  map = L.map('map', { worldCopyJump: false, maxBoundsViscosity: 1.0 })
    .setView(CENTER, MAP_ZOOM);

  L.tileLayer(OSM.url, { attribution: OSM.attr }).addTo(map);

  // Crear panes
  const sectoresPane = map.createPane('sectoresPane');
  sectoresPane.style.zIndex = 350;

  const routePane = map.createPane('routePane');
  routePane.style.zIndex = 460;
  routePane.style.pointerEvents = 'none';

  const suggestedPane = map.createPane('suggestedPane');
  suggestedPane.style.zIndex = 455;
  suggestedPane.style.pointerEvents = 'none';

  // Inicializar capas
  layerSectores = L.layerGroup().addTo(map);
  layerStands = L.layerGroup().addTo(map);
  layerRoute = L.layerGroup({ pane: 'routePane' }).addTo(map);
  layerDrivers = L.layerGroup().addTo(map);
  layerSuggested = L.layerGroup({ pane: 'suggestedPane' }).addTo(map);

  // Configurar eventos del mapa
  setupMapEvents();

  // Cargar datos iniciales
  loadSectores();
  loadStands();

  setTimeout(() => {
    try { map.invalidateSize(); } catch { }
  }, 200);
}

function setupMapEvents() {
  let pickMode = null;

  qs('#btnPickFrom')?.addEventListener('click', () => {
    pickMode = 'from';
    map.getContainer().style.cursor = 'crosshair';
  });

  qs('#btnPickTo')?.addEventListener('click', () => {
    pickMode = 'to';
    map.getContainer().style.cursor = 'crosshair';
  });

  map.on('click', (ev) => {
    if (!pickMode) return;
    map.getContainer().style.cursor = '';
    if (pickMode === 'from') setFrom([ev.latlng.lat, ev.latlng.lng]);
    else setTo([ev.latlng.lat, ev.latlng.lng]);
    pickMode = null;
  });

  qs('#btnPickStop1')?.addEventListener('click', () => {
    map.once('click', (e) => setStop1([e.latlng.lat, e.latlng.lng]));
  });

  qs('#btnPickStop2')?.addEventListener('click', () => {
    if (!Number.isFinite(parseFloat(qs('#stop1Lat')?.value))) return;
    map.once('click', (e) => setStop2([e.latlng.lat, e.latlng.lng]));
  });
}

// Funciones de marcadores
export function setFrom(latlng, label) {
  if (fromMarker) fromMarker.remove();
  fromMarker = L.marker(latlng, { draggable: true, icon: IconOrigin, zIndexOffset: 1000 })
    .addTo(map).bindTooltip('Origen');
  setInput('#fromLat', latlng[0]); setInput('#fromLng', latlng[1]);
  if (label) qs('#inFrom').value = label; else reverseGeocode(latlng, '#inFrom');
  fromMarker.on('dragstart', () => map.dragging.disable());
  fromMarker.on('dragend', (e) => {
    map.dragging.enable();
    const ll = e.target.getLatLng();
    setInput('#fromLat', ll.lat); setInput('#fromLng', ll.lng);
    reverseGeocode([ll.lat, ll.lng], '#inFrom');
    drawRoute({ quiet: true });
    autoQuoteIfReady();
  });
  drawRoute({ quiet: true });
  autoQuoteIfReady();
}

export function setTo(latlng, label) {
  if (toMarker) toMarker.remove();
  toMarker = L.marker(latlng, { draggable: true, icon: IconDest, zIndexOffset: 1000 })
    .addTo(map).bindTooltip('Destino');
  setInput('#toLat', latlng[0]); setInput('#toLng', latlng[1]);
  if (label) qs('#inTo').value = label; else reverseGeocode(latlng, '#inTo');
  toMarker.on('dragstart', () => map.dragging.disable());
  toMarker.on('dragend', (e) => {
    map.dragging.enable();
    const ll = e.target.getLatLng();
    setInput('#toLat', ll.lat); setInput('#toLng', ll.lng);
    reverseGeocode([ll.lat, ll.lng], '#inTo');
    drawRoute({ quiet: true });
    autoQuoteIfReady();
  });
  drawRoute({ quiet: true });
  autoQuoteIfReady();
}

export function setStop1(latlng, label) {
  if (stop1Marker) stop1Marker.remove();
  stop1Marker = L.marker(latlng, { draggable: true, icon: IconStop, zIndexOffset: 900 })
    .addTo(map).bindTooltip('Parada 1');

  qs('#stop1Lat').value = latlng[0]; qs('#stop1Lng').value = latlng[1];
  if (label) qs('#inStop1').value = label; else reverseGeocode(latlng, '#inStop1');

  stop1Marker.on('dragstart', () => map.dragging.disable());
  stop1Marker.on('dragend', (e) => {
    map.dragging.enable();
    const ll = e.target.getLatLng();
    qs('#stop1Lat').value = ll.lat; qs('#stop1Lng').value = ll.lng;
    reverseGeocode([ll.lat, ll.lng], '#inStop1');
    drawRoute({ quiet: true }); autoQuoteIfReady();
  });

  document.getElementById('stop2Row')?.style.setProperty('display', '');
  drawRoute({ quiet: true }); autoQuoteIfReady();
}

export function setStop2(latlng, label) {
  if (!Number.isFinite(parseFloat(qs('#stop1Lat')?.value))) return;

  if (stop2Marker) stop2Marker.remove();
  stop2Marker = L.marker(latlng, { draggable: true, icon: IconStop, zIndexOffset: 900 })
    .addTo(map).bindTooltip('Parada 2');

  qs('#stop2Lat').value = latlng[0]; qs('#stop2Lng').value = latlng[1];
  if (label) qs('#inStop2').value = label; else reverseGeocode(latlng, '#inStop2');

  stop2Marker.on('dragstart', () => map.dragging.disable());
  stop2Marker.on('dragend', (e) => {
    map.dragging.enable();
    const ll = e.target.getLatLng();
    qs('#stop2Lat').value = ll.lat; qs('#stop2Lng').value = ll.lng;
    reverseGeocode([ll.lat, ll.lng], '#inStop2');
    drawRoute({ quiet: true }); autoQuoteIfReady();
  });

  drawRoute({ quiet: true }); autoQuoteIfReady();
}

// Funciones de carga de datos
export async function loadSectores() {
  try {
    const r = await fetch('/api/sectores', {
      headers: jsonHeaders()
    });
    if (!r.ok) return;
    const data = await r.json();
    layerSectores.clearLayers();
    const fc = Array.isArray(data)
      ? {
        type: 'FeatureCollection', features: data.map(row => {
          let area = row.area; if (typeof area === 'string') { try { area = JSON.parse(area); } catch { area = null; } }
          if (!area) return null;
          if (area.type === 'Feature') { area.properties = { ...(area.properties || {}), nombre: row.nombre }; return area; }
          return { type: 'Feature', properties: { nombre: row.nombre }, geometry: area };
        }).filter(Boolean)
      }
      : data;

    L.geoJSON(fc, {
      pane: 'sectoresPane',
      style: sectorStyle,
      interactive: false,
      onEachFeature: (f, l) => l.bindTooltip(`<strong>${f?.properties?.nombre || 'Sector'}</strong>`,
        { direction: 'top', offset: [0, -4], className: 'sector-tip' })
    }).addTo(layerSectores);
  } catch (e) { console.warn('sectores error', e); }
}

export async function loadStands() {
  try {
    const tenantId =
      (typeof getTenantId === 'function'
        ? getTenantId()
        : (window.currentTenantId || ''));

    const r = await fetch(`/api/taxistands?tenant_id=${encodeURIComponent(tenantId)}`, {
      headers: {
        'Accept': 'application/json',
        'X-Tenant-ID': tenantId || ''
      }
    });

    if (!r.ok) return;
    const list = await r.json();

    layerStands.clearLayers();
    list.forEach(z => {
      const lat = Number(z.latitud), lng = Number(z.longitud);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

      L.marker([lat, lng], { icon: IconStand, zIndexOffset: 20 })
        .bindTooltip(
          `<strong>${z.nombre}</strong><div class="text-muted">(${fmt(lat)}, ${fmt(lng)})</div>`,
          { direction: 'top', offset: [0, -12], className: 'stand-tip' }
        )
        .addTo(layerStands);
    });
  } catch (e) {
    console.warn('stands error', e);
  }
}

// Helper para setInput (añadir al archivo si no existe)
export function setInput(sel, val) {
  const el = qs(sel);
  if (el) el.value = val;
}

// Exportar map y layers para uso en otros módulos
export { map, layerSectores, layerStands, layerRoute, layerDrivers, layerSuggested };