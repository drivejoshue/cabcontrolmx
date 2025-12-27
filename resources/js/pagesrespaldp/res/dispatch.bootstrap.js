import L from 'leaflet';
import { qs, jsonHeaders, isDarkMode, loadGoogleMaps } from './dispatch.core.js';
import { loadDispatchSettings } from './dispatch.settings.js';
import { wireAutoFixedWhenTyping, recalcQuoteUI } from './dispatch.form_when.js';
import { wirePassengerLastRide } from './dispatch.passenger_autofill.js';

import { wireUiActions } from './dispatch.ui.js';
import { wireDriverRealtime } from './dispatch.drivers.js';

import { loadSectores, loadStands } from './dispatch.static_layers.js';
import { setMapRuntime, setFrom, setTo, drawRoute } from './dispatch.map.js';

export async function initDispatchPage() {
  await loadDispatchSettings();

window.setFrom   = setFrom;
window.setTo     = setTo;
window.drawRoute = drawRoute;
  function resolveCenter() {
  // 1) CENTER válido (array o {lat,lng})
  const c = window.CENTER;

  // [lat,lng]
  if (Array.isArray(c) && c.length === 2) {
    const lat = Number(c[0]), lng = Number(c[1]);
    if (Number.isFinite(lat) && Number.isFinite(lng)) return [lat, lng];
  }

  // {lat,lng}
  if (c && typeof c === 'object') {
    const lat = Number(c.lat ?? c.latitude);
    const lng = Number(c.lng ?? c.lon ?? c.longitude);
    if (Number.isFinite(lat) && Number.isFinite(lng)) return [lat, lng];
  }

  // 2) intenta ccTenant.map (tu onboarding)
  const t = window.ccTenant?.map;
  if (t) {
    const lat = Number(t.lat), lng = Number(t.lng);
    if (Number.isFinite(lat) && Number.isFinite(lng)) return [lat, lng];
  }

  // 3) fallback duro (Veracruz centro, cámbialo si quieres)
  return [19.1738, -96.1342];
}

function resolveZoom() {
  const z = Number(window.MAP_ZOOM ?? window.ccTenant?.map?.zoom ?? 14);
  return Number.isFinite(z) ? z : 14;
}

function resolveOSM() {
  const o = window.OSM;
  if (o && o.url) return o;
  return {
    url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    attr: '&copy; OpenStreetMap contributors'
  };
}


  const mapEl = document.getElementById('map');
  if (!mapEl) return;

  mapEl.classList.toggle('map-dark', isDarkMode());

  // Leaflet config desde Blade
const CENTER   = resolveCenter();
const MAP_ZOOM = resolveZoom();
const OSM      = resolveOSM();


  const map = L.map('map', { worldCopyJump:false, maxBoundsViscosity:1.0 })
    .setView(CENTER, MAP_ZOOM);

  L.tileLayer(OSM.url, { attribution: OSM.attr }).addTo(map);

  // panes
  const sectoresPane = map.createPane('sectoresPane'); sectoresPane.style.zIndex = 350;
  const routePane    = map.createPane('routePane');    routePane.style.zIndex = 460; routePane.style.pointerEvents='none';
  const suggestedPane= map.createPane('suggestedPane');suggestedPane.style.zIndex = 455; suggestedPane.style.pointerEvents='none';

  // capas
  const layerSectores  = L.layerGroup().addTo(map);
  const layerStands    = L.layerGroup().addTo(map);
  const layerRoute     = L.layerGroup({ pane:'routePane' }).addTo(map);
  const layerDrivers   = L.layerGroup().addTo(map);
  const layerSuggested = L.layerGroup({ pane:'suggestedPane' }).addTo(map);

  // ctx canónico
  const ctx = {
    map, mapEl,
    layerSectores, layerStands, layerRoute, layerDrivers, layerSuggested,
    qs, jsonHeaders, recalcQuoteUI
  };

  // compat legacy
  window.map = map;
  window.layerSectores  = layerSectores;
  window.layerStands    = layerStands;
  window.layerRoute     = layerRoute;
  window.layerDrivers   = layerDrivers;
  window.layerSuggested = layerSuggested;

  // UI + realtime
  wireUiActions(ctx);
  wireDriverRealtime(ctx);

  // capas estáticas
  await loadSectores(ctx);
  await loadStands(ctx);

  // Google widgets (autocomplete)
  loadGoogleMaps().then((google) => {
    window.gDirService = new google.maps.DirectionsService();
    window.gGeocoder   = new google.maps.Geocoder();

    const inFrom = qs('#inFrom');
    const inTo   = qs('#inTo');

    if (inFrom) {
      window.acFrom = new google.maps.places.Autocomplete(inFrom, { fields:['formatted_address','geometry'] });
      window.acFrom.addListener('place_changed', () => {
        const p = window.acFrom.getPlace(); if (!p?.geometry) return;
        window.setFrom?.([p.geometry.location.lat(), p.geometry.location.lng()], p.formatted_address);
      });
    }
    if (inTo) {
      window.acTo = new google.maps.places.Autocomplete(inTo, { fields:['formatted_address','geometry'] });
      window.acTo.addListener('place_changed', () => {
        const p = window.acTo.getPlace(); if (!p?.geometry) return;
        window.setTo?.([p.geometry.location.lat(), p.geometry.location.lng()], p.formatted_address);
      });
    }
  }).catch(e => console.warn('[DISPATCH] Google no cargó', e));

  // wires form
  wireAutoFixedWhenTyping();
  wirePassengerLastRide({ qs, jsonHeaders, recalcQuoteUI });

  setTimeout(() => { try { map.invalidateSize(); } catch {} }, 200);

  // guarda ctx si lo necesitas en otros módulos
  window.ccDispatchCtx = ctx;
  return ctx;
}

export function wireDispatchDomReady() {
  return initDispatchPage();
}
