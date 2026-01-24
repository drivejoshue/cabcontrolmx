// resources/js/partner-monitor/map.js
import L from 'leaflet';
import { OSM, DEFAULT_CENTER, DEFAULT_ZOOM, isDarkMode } from './net';

export function createMapAndLayers() {
  const mapEl = document.getElementById('pmMap');
  if (!mapEl) {
    console.warn('[PartnerMonitor] Falta #pmMap');
    return null;
  }

  const initialCenter =
    window.ccTenant?.map?.lat && window.ccTenant?.map?.lng
      ? [Number(window.ccTenant.map.lat), Number(window.ccTenant.map.lng)]
      : DEFAULT_CENTER;

  const initialZoom = Number(window.ccTenant?.map?.zoom ?? DEFAULT_ZOOM);

  const map = L.map('pmMap', { zoomControl: true, preferCanvas: true })
    .setView(initialCenter, initialZoom);

  // ✅ OSM siempre (el dark lo hace el filtro CSS)
  L.tileLayer(OSM.url, { attribution: OSM.attr, maxZoom: 19 }).addTo(map);

  // panes
  map.createPane('pmSectorsPane'); map.getPane('pmSectorsPane').style.zIndex = 330;
  map.createPane('pmStandsPane');  map.getPane('pmStandsPane').style.zIndex  = 650;
  map.getPane('pmStandsPane').style.pointerEvents = 'auto';
  map.createPane('pmDriversPane'); map.getPane('pmDriversPane').style.zIndex = 660;
  map.getPane('pmDriversPane').style.pointerEvents = 'auto';

  // layers
  const layerSectors = L.geoJSON([], { pane:'pmSectorsPane', style: sectorStyle }).addTo(map);
  const layerStands  = L.layerGroup().addTo(map);
  const layerDrivers = L.layerGroup().addTo(map);

  function sectorStyle() {
    return isDarkMode()
      ? { color:'#FF6B6B', fillColor:'#FF6B6B', fillOpacity:0.12, weight:2 }
      : { color:'#2A9DF4', fillColor:'#2A9DF4', fillOpacity:0.18, weight:2 };
  }

  function setBaseTheme(dark) {
    mapEl.classList.toggle('pm-dark-tiles', !!dark);
  }

  function invalidate() {
    setTimeout(() => map.invalidateSize(true), 180);
  }

  // ✅ Map se sincroniza solo con el tema (sin app.js)
  (function bindMapTheme() {
    const apply = (themeStr) => {
      const t = String(themeStr || '').toLowerCase();
      const dark = themeStr ? (t === 'dark') : isDarkMode();
      setBaseTheme(dark);
    };

    // 1) apply inicial
    apply();

    // 2) evento como Dispatch
    window.addEventListener('theme:changed', (e) => apply(e?.detail?.theme));

    // 3) fallback por si solo cambian attrs
    const obs = new MutationObserver(() => apply());
    obs.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['data-theme', 'data-bs-theme', 'class'],
    });
  })();

  return {
    map,
    initialCenter,
    initialZoom,
    layerSectors,
    layerStands,
    layerDrivers,
    sectorStyle,
    setBaseTheme,
    invalidate,
  };
}
