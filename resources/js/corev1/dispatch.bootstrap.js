// resources/js/pages/dispatch.bootstrap.js

import { qs, jsonHeaders, isDarkMode, loadGoogleMaps } from './dispatch.core.js';
import { loadDispatchSettings } from './dispatch.settings.js';

import { wireAutoFixedWhenTyping, recalcQuoteUI } from './dispatch.form_when.js';
import { wirePassengerLastRide } from './dispatch.passenger_autofill.js';

import { wireUiActions } from './dispatch.ui.js';
import { wireDriverRealtime } from './dispatch.drivers.js';

import { loadSectores } from './dispatch.sectores.js';
import { loadStands } from './dispatch.stands.js';
import { recalcQuoteUI, wireAutoFixedWhenTyping } from './dispatch.form_when.js';

export async function initDispatchPage() {
  await loadDispatchSettings();

  // Chat (si existe global legacy)
 // try { window.ChatInbox?.init?.(); } catch {}

  // Si aún mantienes loadActiveRides global legacy
  try { window.loadActiveRides?.(); } catch {}

  const mapEl = document.getElementById('map');
  if (!mapEl) return;

  mapEl.classList.toggle('map-dark', isDarkMode());

  // Leaflet map (si CENTER/MAP_ZOOM/OSM están en window por blade)
  const map = L.map('map', { worldCopyJump:false, maxBoundsViscosity:1.0 })
              .setView(window.CENTER, window.MAP_ZOOM);

  L.tileLayer(window.OSM.url, { attribution: window.OSM.attr }).addTo(map);

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

  // ctx “canónico” (lo usan los módulos)
  const ctx = {
    map, mapEl,
    layerSectores, layerStands, layerRoute, layerDrivers, layerSuggested,
    qs, jsonHeaders, recalcQuoteUI
  };

  // expone compat si aún hay código legacy que usa window.layerX / window.map
  window.map = map;
  window.layerSectores = layerSectores;
  window.layerStands = layerStands;
  window.layerRoute = layerRoute;
  window.layerDrivers = layerDrivers;
  window.layerSuggested = layerSuggested;

  // UI / realtime
  wireUiActions(ctx);
  wireDriverRealtime(ctx);

  // cargas iniciales
  await loadSectores(ctx);
  await loadStands(ctx);

  // Google autocomplete (usa loader del core)
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

  // wires “form”
  wireAutoFixedWhenTyping();
  wirePassengerLastRide({ qs, jsonHeaders, recalcQuoteUI });

  // layout
  setTimeout(() => { try { map.invalidateSize(); } catch {} }, 200);
}

/**
 * Solo por compat: si quieres seguir llamando wireDispatchDomReady()
 * desde el entry, NO debe volver a registrar DOMContentLoaded.
 */
export function wireDispatchDomReady() {
  return initDispatchPage();
}
