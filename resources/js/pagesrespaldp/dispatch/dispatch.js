/* resources/js/pages/dispatch.js */
import './dispatch/core.js';
import './dispatch/utils.js';
import './dispatch/map.js';
import './dispatch/google.js';
import './dispatch/routing.js';
import './dispatch/rides.js';
import './dispatch/drivers.js';
import './dispatch/assign.js';
import './dispatch/ui.js';
import './dispatch/chat.js';
import './dispatch/autodispatch.js';

import { qs } from './dispatch/core.js';
import { initMap, setFrom, setTo, setStop1, setStop2 } from './dispatch/map.js';
import { initGoogleWidgets, reverseGeocode } from './dispatch/google.js';
import { drawRoute, autoQuoteIfReady, recalcQuoteUI } from './dispatch/routing.js';
import { loadDispatchSettings, startCompoundAutoDispatch } from './dispatch/autodispatch.js';
import { loadActiveRides, setupUIEvents, startPolling } from './dispatch/ui.js';

// Inicialización principal
document.addEventListener('DOMContentLoaded', async () => {
  // Cargar settings
  await loadDispatchSettings();

  // Inicializar chat
  if (window.ChatInbox && typeof window.ChatInbox.init === 'function') {
    window.ChatInbox.init();
  }

  // Inicializar mapa
  initMap();

  // Inicializar Google widgets
  initGoogleWidgets();

  // Configurar eventos UI
  setupUIEvents();

  // Setup de eventos específicos
  setupEventHandlers();

  // Cargar datos iniciales
  await loadActiveRides();

  // Iniciar polling
  startPolling();

  // Setup de WebSocket si existe Echo
  setupWebSockets();


  /* resources/js/pages/dispatch.js */
console.log('=== DISPATCH DEBUG START ===');

// Verifica dependencias
console.log('L:', typeof L);
console.log('google:', typeof google);
console.log('bootstrap:', typeof bootstrap);
console.log('Swal:', typeof Swal);

try {
  // Importaciones dinámicas para debug
  import('./dispatch/core.js').then(() => {
    console.log('core.js cargado');
  }).catch(err => {
    console.error('Error cargando core.js:', err);
  });
  
  // Repite para cada módulo...
  
} catch (error) {
  console.error('Error en dispatch.js:', error);
}

console.log('=== DISPATCH DEBUG END ===');
});

function setupEventHandlers() {
  // Recordar último ride por teléfono
  qs('#pass-phone')?.addEventListener('blur', async (e) => {
    const phone = (e.target.value || '').trim();
    if (!phone) return;

    const hasA = !!(qs('#fromLat')?.value && qs('#fromLng')?.value);
    const hasB = !!(qs('#toLat')?.value && qs('#toLng')?.value);
    if (hasA || hasB) return;

    try {
      const r = await fetch(`/api/passengers/last-ride?phone=${encodeURIComponent(phone)}`, {
        headers: jsonHeaders()
      });
      if (!r.ok) return;
      const lastRide = await r.json();
      if (!lastRide) return;

      console.log('Last ride with stops data:', lastRide);

      if (lastRide.passenger_name && !qs('#pass-name')?.value) {
        qs('#pass-name').value = lastRide.passenger_name;
      }
      if (lastRide.notes && !qs('#ride-notes')?.value) {
        qs('#ride-notes').value = lastRide.notes;
      }

      try {
        clearAssignArtifacts?.();
        if (window.rideMarkers) {
          window.rideMarkers.forEach(g => { try { g.remove(); } catch { } });
          window.rideMarkers.clear();
        }
      } catch { }

      if (Number.isFinite(lastRide.origin_lat) && Number.isFinite(lastRide.origin_lng)) {
        setFrom([lastRide.origin_lat, lastRide.origin_lng], lastRide.origin_label);
      }
      if (Number.isFinite(lastRide.dest_lat) && Number.isFinite(lastRide.dest_lng)) {
        setTo([lastRide.dest_lat, lastRide.dest_lng], lastRide.dest_label);
      }

      await loadStopsFromLastRide(lastRide);
    } catch (err) {
      console.warn('Error in phone autocomplete:', err);
    }
  });

  // Crear ride
  let isCreatingRide = false;
  qs('#btnCreate')?.addEventListener('click', async () => {
    if (isCreatingRide) {
      console.debug('⏳ Ya hay un create en curso, ignorando click doble');
      return;
    }
    isCreatingRide = true;

    const btn = qs('#btnCreate');
    const originalHtml = btn ? btn.innerHTML : null;
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creando...';
    }

    let scheduled_for = null;
    if (qs('#when-later')?.checked) {
      const scheduleInput = qs('#scheduleAt');
      if (scheduleInput?.value) {
        scheduled_for = scheduleInput.value;
      }
    }

    const fareInput = Number(qs('#fareAmount')?.value);
    const quoted_amount = Number.isFinite(fareInput) ? Math.round(fareInput) : null;
    const userfixed = !!(qs('#fareFixed')?.checked || qs('#fareLock')?.checked);

    const payload = {
      passenger_name: qs('#pass-name')?.value || null,
      passenger_phone: qs('#pass-phone')?.value || null,
      origin_lat: parseFloat(qs('#fromLat')?.value),
      origin_lng: parseFloat(qs('#fromLng')?.value),
      origin_label: qs('#inFrom')?.value || null,
      dest_lat: (qs('#toLat')?.value ? parseFloat(qs('#toLat')?.value) : null),
      dest_lng: (qs('#toLng')?.value ? parseFloat(qs('#toLng')?.value) : null),
      dest_label: qs('#inTo')?.value || null,
      payment_method: qs('#pay-method')?.value || 'cash',
      fare_mode: qs('#fareMode')?.value || 'meter',
      notes: qs('#ride-notes')?.value || null,
      pax: parseInt(qs('#pax')?.value) || 1,
      scheduled_for,
      quoted_amount,
      ...(userfixed ? { userfixed: true } : {}),
      distance_m: (typeof __lastQuote !== 'undefined' ? (__lastQuote?.distance_m ?? null) : null),
      duration_s: (typeof __lastQuote !== 'undefined' ? (__lastQuote?.duration_s ?? null) : null),
      route_polyline: (typeof __lastQuote !== 'undefined' ? (__lastQuote?.polyline ?? null) : null),
      requested_channel: 'dispatch',
    };

    // Adjuntar STOPS
    (() => {
      const s1lat = parseFloat(qs('#stop1Lat')?.value || '');
      const s1lng = parseFloat(qs('#stop1Lng')?.value || '');
      const s2lat = parseFloat(qs('#stop2Lat')?.value || '');
      const s2lng = parseFloat(qs('#stop2Lng')?.value || '');

      const stops = [];
      if (Number.isFinite(s1lat) && Number.isFinite(s1lng)) {
        stops.push({ lat: s1lat, lng: s1lng, label: (qs('#inStop1')?.value || null) });
        if (Number.isFinite(s2lat) && Number.isFinite(s2lng)) {
          stops.push({ lat: s2lat, lng: s2lng, label: (qs('#inStop2')?.value || null) });
        }
      }
      if (stops.length) payload.stops = stops;
    })();

    if (!Number.isFinite(payload.origin_lat) || !Number.isFinite(payload.origin_lng)) {
      alert('Indica un origen válido.');
      if (btn) {
        btn.disabled = false;
        if (originalHtml !== null) btn.innerHTML = originalHtml;
      }
      isCreatingRide = false;
      return;
    }

    try {
      const r = await fetch('/api/rides', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json', 'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
          'X-Tenant-ID': (typeof getTenantId === 'function' ? getTenantId() : (window.currentTenantId || ''))
        },
        body: JSON.stringify(payload)
      });
      if (!r.ok) throw new Error((await r.text().catch(() => '')) || ('HTTP ' + r.status));
      const ride = await r.json();

      try {
        if (!isScheduledStatus?.(ride) && typeof startCompoundAutoDispatch === 'function') {
          startCompoundAutoDispatch(ride);
        }
      } catch (e) { }

      clearRideFormAndMap();

      try {
        if (window._assignPickupMarker) {
          try { (layerSuggested || layerRoute || map).removeLayer(window._assignPickupMarker); } catch { }
          try { map.removeLayer(window._assignPickupMarker); } catch { }
          window._assignPickupMarker = null;
        }
      } catch { }

      if (isScheduledStatus?.(ride)) {
        Swal.fire({ icon: 'success', title: 'Programado creado', text: 'Se disparará a su hora.', timer: 1800, showConfirmButton: false });
        const tabProg = document.getElementById('tab-active-grid');
        if (tabProg && window.bootstrap?.Tab) window.bootstrap.Tab.getOrCreateInstance(tabProg).show();
      } else {
        Swal.fire({ icon: 'success', title: 'Viaje creado', timer: 1200, showConfirmButton: false });
        const tabNow = document.getElementById('tab-active-cards');
        if (tabNow && window.bootstrap?.Tab) window.bootstrap.Tab.getOrCreateInstance(tabNow).show();
      }

      await window.refreshDispatch?.();
    } catch (e) {
      console.error(e);
      alert('No se pudo crear el viaje: ' + (e?.message || e));
    } finally {
      isCreatingRide = false;
      if (btn) {
        btn.disabled = false;
        if (originalHtml !== null) btn.innerHTML = originalHtml;
      }
    }
  });

  // Cotizar
  qs('#btnQuote')?.addEventListener('click', async () => {
    const aLat = parseFloat(qs('#fromLat')?.value);
    const aLng = parseFloat(qs('#fromLng')?.value);
    const bLat = parseFloat(qs('#toLat')?.value);
    const bLng = parseFloat(qs('#toLng')?.value);
    if (!Number.isFinite(aLat) || !Number.isFinite(aLng) ||
      !Number.isFinite(bLat) || !Number.isFinite(bLng)) {
      alert('Indica origen y destino para cotizar.'); return;
    }
    try {
      const r = await fetch('/api/dispatch/quote', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({
          origin: { lat: aLat, lng: aLng },
          destination: { lat: bLat, lng: bLng },
          round_to_step: 1.00
        })
      });
      const j = await r.json();
      if (!r.ok || j.ok === false) throw new Error(j?.msg || ('HTTP ' + r.status));

      const rs = document.getElementById('routeSummary');
      if (rs) {
        const km = (j.distance_m / 1000).toFixed(1) + ' km';
        const min = Math.round(j.duration_s / 60) + ' min';
        rs.innerText = `Ruta: ${km} · ${min} · Tarifa: $${j.amount}`;
      }
      qs('#fareAmount').value = j.amount;
    } catch (e) {
      console.error(e);
      alert('No se pudo cotizar.');
    }
  });

  // Invertir ruta
  qs('#btnInvertRoute')?.addEventListener('click', invertRoute);
}

function invertRoute() {
  const fromLat = qs('#fromLat').value;
  const fromLng = qs('#fromLng').value;
  const fromAddr = qs('#inFrom').value;
  const toLat = qs('#toLat').value;
  const toLng = qs('#toLng').value;
  const toAddr = qs('#inTo').value;

  if (!fromLat || !fromLng || !toLat || !toLng) {
    showToast('Se necesitan origen y destino para invertir', 'warning');
    return;
  }

  qs('#fromLat').value = toLat;
  qs('#fromLng').value = toLng;
  qs('#inFrom').value = toAddr;
  qs('#toLat').value = fromLat;
  qs('#toLng').value = fromLng;
  qs('#inTo').value = fromAddr;

  clearAllStops();
  drawRoute({ quiet: true });
  autoQuoteIfReady();
  showToast('Ruta invertida - Paradas limpiadas', 'success');
}

function setupWebSockets() {
  if (window.Echo) {
    const tenantId = (window.ccTenant && window.ccTenant.id) || 1;
    window.Echo.channel(`driver.location.${tenantId}`)
      .listen('.LocationUpdated', async (p) => {
        upsertDriver({ ...p, id: p.driver_id });

        const rs = String(p.ride_status || '').toUpperCase();
        if (['ASSIGNED', 'EN_ROUTE', 'ARRIVED'].includes(rs) && Number.isFinite(p.origin_lat) && Number.isFinite(p.origin_lng)) {
          await showDriverToPickup(p.driver_id, p.origin_lat, p.origin_lng);
        }
        if (['ON_BOARD', 'ONBOARD', 'FINISHED', 'CANCELLED', 'CANCELED'].includes(rs)) {
          clearDriverRoute(p.driver_id);
        }
      });
  }
}

// Helper para cargar stops desde last ride
async function loadStopsFromLastRide(lastRide) {
  try {
    console.log('Loading stops from last ride:', lastRide);
    if (window.loadingStops) return;
    window.loadingStops = true;

    let stops = [];
    if (Array.isArray(lastRide.stops) && lastRide.stops.length > 0) {
      stops = lastRide.stops;
      console.log('Using stops array:', stops);
    } else if (lastRide.stops_json) {
      try {
        const parsed = JSON.parse(lastRide.stops_json);
        if (Array.isArray(parsed)) {
          stops = parsed;
          console.log('Using parsed stops_json:', stops);
        }
      } catch (e) {
        console.warn('Error parsing stops_json:', e);
      }
    }

    if (stops.length > 0) {
      console.log('Setting stops in form:', stops);
      setStopsInForm(stops);
    } else {
      console.log('No stops found in last ride');
    }
  } catch (err) {
    console.warn('Error loading stops from last ride:', err);
  } finally {
    window.loadingStops = false;
  }
}

function setStopsInForm(stops) {
  if (!Array.isArray(stops) || stops.length === 0) return;
  console.log('Setting stops in form:', stops);

  if (stop1Marker) { stop1Marker.remove(); stop1Marker = null; }
  if (stop2Marker) { stop2Marker.remove(); stop2Marker = null; }

  const stopFields = [
    '#stop1Lat', '#stop1Lng', '#inStop1',
    '#stop2Lat', '#stop2Lng', '#inStop2'
  ];

  stopFields.forEach(selector => {
    const el = qs(selector);
    if (el) el.value = '';
  });

  const stop1Row = qs('#stop1Row');
  const stop2Row = qs('#stop2Row');

  if (stop1Row) stop1Row.style.display = 'none';
  if (stop2Row) stop2Row.style.display = 'none';

  stops.forEach((stop, index) => {
    if (index >= 2) return;

    const lat = Number(stop.lat);
    const lng = Number(stop.lng);
    const label = stop.label || stop.address || '';

    if (Number.isFinite(lat) && Number.isFinite(lng)) {
      if (index === 0) {
        if (stop1Row) stop1Row.style.display = '';
        setStop1([lat, lng], label);
        console.log('Set stop1:', lat, lng, label);
      } else if (index === 1) {
        if (stop2Row) stop2Row.style.display = '';
        setStop2([lat, lng], label);
        console.log('Set stop2:', lat, lng, label);
      }
    }
  });

  setTimeout(() => {
    drawRoute({ quiet: true });
    autoQuoteIfReady();
  }, 500);
}

function clearRideFormAndMap() {
  try {
    ['inFrom', 'inTo', 'pass-name', 'pass-phone', 'pass-account', 'ride-notes', 'fareAmount', 'pax',].forEach(id => {
      const el = qs('#' + id); if (el) el.value = '';
    });
    ['fromLat', 'fromLng', 'toLat', 'toLng'].forEach(id => {
      const el = qs('#' + id); if (el) el.value = '';
    });
    layerRoute?.clearLayers?.();
    if (fromMarker) { fromMarker.remove(); fromMarker = null; }
    if (toMarker) { toMarker.remove(); toMarker = null; }
    if (stop1Marker) { stop1Marker.remove(); stop1Marker = null; }
    if (stop2Marker) { stop2Marker.remove(); stop2Marker = null; }
    const s1Lat = qs('#stop1Lat'), s1Lng = qs('#stop1Lng'), s2Lat = qs('#stop2Lat'), s2Lng = qs('#stop2Lng');
    const inS1 = qs('#inStop1'), inS2 = qs('#inStop2');
    const row1 = qs('#stop1Row'), row2 = qs('#stop2Row');
    if (s1Lat) s1Lat.value = ''; if (s1Lng) s1Lng.value = '';
    if (s2Lat) s2Lat.value = ''; if (s2Lng) s2Lng.value = '';
    if (inS1) inS1.value = ''; if (inS2) inS2.value = '';
    if (row1) row1.style.display = 'none';
    if (row2) row2.style.display = 'none';
    const rs = document.getElementById('routeSummary');
    if (rs) rs.innerText = 'Ruta: — · Zona: — · Cuando: ahora';
    resetWhenNow?.();
  } catch { }
  try {
    if (window._assignPickupMarker) {
      try { (layerSuggested || layerRoute || map).removeLayer(window._assignPickupMarker); } catch { }
      try { map.removeLayer(window._assignPickupMarker); } catch { }
      window._assignPickupMarker = null;
    }
  } catch { }
}

qs('#btnClear')?.addEventListener('click', clearRideFormAndMap);
qs('#btnReset')?.addEventListener('click', clearRideFormAndMap);