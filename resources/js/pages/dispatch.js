
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const centerDefault = [19.1738, -96.1342];
const defaultZoom = 14;

// OSM estable
const OSM = {
  url:  'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
  attr: '&copy; OpenStreetMap contributors'
};

function safeLog(...args){ try{ console.debug('[DISPATCH]', ...args);}catch{} }

document.addEventListener('DOMContentLoaded', async () => {
  const mapEl = document.getElementById('map');
  if (!mapEl) { safeLog('No #map'); return; }

  // asegúrate de que el contenedor tenga altura (tu layout ya usa calc(100vh - ...))
  if (mapEl.getBoundingClientRect().height < 50) {
    safeLog('WARNING: #map muy bajo, revisa altura CSS');
  }
// Icono PNG para paraderos (ajusta el nombre del archivo a tu gusto)


  // Modo dark inicial: aplica clase al contenedor (para el filtro CSS)
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  mapEl.classList.toggle('map-dark', isDark);

  const map = L.map('map', { worldCopyJump:false, maxBoundsViscosity:1.0 })
               .setView(centerDefault, defaultZoom);

  // Tile layer OSM + fallback
  const baseOSM = L.tileLayer(OSM.url, { attribution: OSM.attr });
  baseOSM.on('tileerror', (e) => safeLog('tileerror OSM', e?.error || e));
  baseOSM.addTo(map);


const STAND_ICON_URL = '/images/marker-parqueo5.png'; // <-- pon tu PNG aquí (public/images/stand.png)


const StandIcon = L.icon({
  iconUrl: STAND_ICON_URL,
  iconSize: [28, 28],     // ajusta al tamaño de tu PNG
  iconAnchor: [14, 28],   // base del pin (x,y)
  popupAnchor: [0, -24],  // dónde abre el popup respecto al icono
});


  // Capas de negocio
  const sectoresPane = map.createPane('sectoresPane'); sectoresPane.style.zIndex = 350;
  const layerSectores = L.layerGroup().addTo(map);
  const layerStands   = L.layerGroup().addTo(map);

  const fmtCoord = (n) => Number(n).toFixed(6);

  function sectorStyle() {
    return isDarkMode()
      ? { color:'#56B3F6', fillColor:'#56B3F6', fillOpacity:.12, weight:2, pane:'sectoresPane' }
      : { color:'#2A9DF4', fillColor:'#2A9DF4', fillOpacity:.18, weight:2, pane:'sectoresPane' };
  }
  function isDarkMode(){ return document.documentElement.getAttribute('data-theme') === 'dark'; }

  function standDivIcon(text = 'S') {
    return L.divIcon({
      className: '',
      html: `<div class="cc-stand-badge" title="Stand">${text}</div>`,
      iconSize: [26,26],
      iconAnchor: [13,13],
      popupAnchor: [0,-14]
    });
  }

  // ===== Sectores =====
 // ===== Sectores =====
async function loadSectores() {
  try {
    const r = await fetch('/api/sectores', { headers: { 'Accept':'application/json' } });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const res = await r.json();

    layerSectores.clearLayers();
    let fc;

    if (res && res.type === 'FeatureCollection') {
      // El API ya trae FC
      fc = res;
    } else if (Array.isArray(res)) {
      // El API trae filas con 'area' (string o objeto)
      const features = [];
      for (const row of res) {
        let area = row.area;
        if (typeof area === 'string') {
          try { area = JSON.parse(area); } catch { area = null; }
        }
        if (!area) continue;

        // Asegura Feature
        let feat = area;
        if ((area.type ?? '') === 'Polygon' || (area.type ?? '') === 'MultiPolygon') {
          feat = { type:'Feature', geometry: area, properties: { id: row.id, nombre: row.nombre } };
        } else if ((area.type ?? '') === 'Feature') {
          feat.properties = { ...(feat.properties||{}), id: row.id, nombre: row.nombre };
        } else {
          continue; // inválido
        }
        features.push(feat);
      }
      fc = { type:'FeatureCollection', features };
    } else {
      console.debug('[DISPATCH] /api/sectores formato no reconocido:', res);
      return;
    }

    if (!fc.features?.length) return;

    // IMPORTANTE: pane va en las opciones del geoJSON, no dentro del style
    const layer = L.geoJSON(fc, {
      pane: 'sectoresPane',
      style: () => (isDarkMode()
        ? { color:'#56B3F6', fillColor:'#56B3F6', fillOpacity:.12, weight:2 }
        : { color:'#2A9DF4', fillColor:'#2A9DF4', fillOpacity:.18, weight:2 }
      ),
      onEachFeature: (feature, l) => {
        const nombre = feature?.properties?.nombre ?? 'Sector';
        l.bindTooltip(`<strong>${nombre}</strong>`, {
          direction:'top', offset:[0,-4], className:'sector-tip'
        });
      }
    }).addTo(layerSectores);

    try { map.fitBounds(layer.getBounds().pad(0.15), { padding:[40,40] }); } catch {}
  } catch (e) {
    console.error('loadSectores error:', e);
  }
}


  // ===== Taxi Stands =====
 async function loadStands() {
  try {
    const r = await fetch('/api/taxistands', { headers:{ 'Accept':'application/json' } });
    if (!r.ok) throw new Error('HTTP '+r.status);
    const list = await r.json();

    layerStands.clearLayers();

    list.forEach((z) => {
      const lat = Number(z.latitud), lng = Number(z.longitud);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

      L.marker([lat, lng], {
        icon: StandIcon,     // <-- usamos el PNG
        zIndexOffset: 20
      })
      .bindTooltip(
        `
          <div><strong>${z.nombre}</strong></div>
          <div class="text-muted">Lat/Lng: ${fmtCoord(lat)}, ${fmtCoord(lng)}</div>
          <div class="text-muted">Sector: ${z?.sector?.nombre ?? '—'}</div>
          <div>Capacidad: <strong>${z.capacidad ?? 0}</strong></div>
        `,
        { direction:'top', offset:[0,-14], className:'stand-tip' }
      )
      .bindPopup(`
        <div><strong>${z.nombre}</strong></div>
        <div class="small text-muted mb-2">(${fmtCoord(lat)}, ${fmtCoord(lng)})</div>
        <div class="d-grid gap-2">
          <button class="btn btn-sm btn-outline-primary" data-stand-id="${z.id}"
            onclick="window.ccAssignFromStand && window.ccAssignFromStand(${z.id})">
            Asignar desde este paradero
          </button>
        </div>
      `)
      .addTo(layerStands);
    });
  } catch (e) { safeLog('loadStands error', e); }
}


  // Cargar capas (sin bloquear el render si falla una)
  await Promise.allSettled([loadSectores(), loadStands()]);

  // Toggles
  document.getElementById('toggle-sectores')?.addEventListener('change', (e) => {
    e.target.checked ? layerSectores.addTo(map) : map.removeLayer(layerSectores);
  });
  document.getElementById('toggle-stands')?.addEventListener('change', (e) => {
    e.target.checked ? layerStands.addTo(map) : map.removeLayer(layerStands);
  });

  // Pick origen/destino rápido
  let pickMode = null;
  document.getElementById('btnPickFrom')?.addEventListener('click', () => pickMode = 'from');
  document.getElementById('btnPickTo')?.addEventListener('click', () => pickMode = 'to');
  map.on('click', (ev) => {
    if (!pickMode) return;
    const v = `${fmtCoord(ev.latlng.lat)}, ${fmtCoord(ev.latlng.lng)}`;
    (pickMode === 'from' ? document.getElementById('inFrom') : document.getElementById('inTo')).value = v;
    pickMode = null;
  });

  // Reaccionar al toggle del tema → agregar/quitar clase al contenedor
  window.addEventListener('theme:changed', (e) => {
    mapEl.classList.toggle('map-dark', e.detail?.theme === 'dark');
    // Ajuste por si cambia layout/altura de forma perezosa
    setTimeout(() => { try { map.invalidateSize(); } catch {} }, 150);
  });

  // Primer invalidate por si el layout tarda en medir
  setTimeout(() => { try { map.invalidateSize(); } catch {} }, 200);
});
