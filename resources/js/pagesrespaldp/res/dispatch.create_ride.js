/* resources/js/pages/dispatch.create_ride.js */
import { qs, jsonHeaders } from './dispatch.core.js';
import { resetWhenNow } from './dispatch.form_when.js';

let isCreatingRide = false;

/**
 * Wire del botón Crear. Llamar 1 vez en init.
 */
export function wireCreateRide() {
  qs('#btnCreate')?.addEventListener('click', onCreateRideClick);
}

async function onCreateRideClick() {
  if (isCreatingRide) return;

  const btn = qs('#btnCreate');
  const originalHtml = btn ? btn.innerHTML : null;

  isCreatingRide = true;
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = 'Creando...';
  }

  try {
    // ------------------------------------------------------------
    // WHEN: scheduled_for literal (sin convertir TZ)
    // ------------------------------------------------------------
    let scheduled_for = null;
    const rNow   = document.getElementById('when-now');
    const rLater = document.getElementById('when-later');
    const sched  = document.getElementById('scheduleAt');

    if (rLater?.checked && sched?.value) {
      // input type="datetime-local" => "YYYY-MM-DDTHH:mm"
      scheduled_for = String(sched.value).trim() || null;
    } else if (rNow?.checked) {
      scheduled_for = null;
    }

    // ------------------------------------------------------------
    // Fare
    // ------------------------------------------------------------
    const fareInput = Number(qs('#fareAmount')?.value);
    const quoted_amount = Number.isFinite(fareInput) ? Math.round(fareInput) : null;

    const userfixed = !!(qs('#fareFixed')?.checked || qs('#fareLock')?.checked);

    // ------------------------------------------------------------
    // Payload base
    // ------------------------------------------------------------
    const payload = {
      passenger_name:  qs('#pass-name')?.value || null,
      passenger_phone: qs('#pass-phone')?.value || null,

      origin_lat:   parseFloat(qs('#fromLat')?.value),
      origin_lng:   parseFloat(qs('#fromLng')?.value),
      origin_label: qs('#inFrom')?.value || null,

      dest_lat:     (qs('#toLat')?.value ? parseFloat(qs('#toLat')?.value) : null),
      dest_lng:     (qs('#toLng')?.value ? parseFloat(qs('#toLng')?.value) : null),
      dest_label:   qs('#inTo')?.value || null,

      payment_method: qs('#pay-method')?.value || 'cash',
      fare_mode:      qs('#fareMode')?.value || 'meter',
      notes:          qs('#ride-notes')?.value || null,
      pax:            parseInt(qs('#pax')?.value) || 1,
      scheduled_for,

      quoted_amount,
      ...(userfixed ? { userfixed: true } : {}),

      distance_m:     (typeof window.__lastQuote !== 'undefined' ? (window.__lastQuote?.distance_m ?? null) : null),
      duration_s:     (typeof window.__lastQuote !== 'undefined' ? (window.__lastQuote?.duration_s ?? null) : null),
      route_polyline: (typeof window.__lastQuote !== 'undefined' ? (window.__lastQuote?.polyline   ?? null) : null),

      requested_channel: 'dispatch',
    };

    // ------------------------------------------------------------
    // Stops (máx 2) con label
    // ------------------------------------------------------------
    {
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
    }

    // ------------------------------------------------------------
    // Validación mínima
    // ------------------------------------------------------------
    if (!Number.isFinite(payload.origin_lat) || !Number.isFinite(payload.origin_lng)) {
      alert('Indica un origen válido.');
      return;
    }

    // ------------------------------------------------------------
    // POST
    // ------------------------------------------------------------
    const r = await fetch('/api/rides', {
      method: 'POST',
      headers: jsonHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(payload),
    });

    if (!r.ok) {
      const txt = await r.text().catch(() => '');
      throw new Error(txt || ('HTTP ' + r.status));
    }

    const ride = await r.json();

    // autodispatch solo si no es programado
    try {
      const scheduled = (typeof window.isScheduledStatus === 'function'
        ? window.isScheduledStatus(ride)
        : !!ride?.scheduled_for);

      if (!scheduled && typeof window.startCompoundAutoDispatch === 'function') {
        window.startCompoundAutoDispatch(ride);
      }
    } catch {}

    // limpiar UI
    clearCreateFormUI();

    // feedback + tabs
    try {
      const scheduled = (typeof window.isScheduledStatus === 'function'
        ? window.isScheduledStatus(ride)
        : !!ride?.scheduled_for);

      if (window.Swal?.fire) {
        if (scheduled) {
          Swal.fire({ icon:'success', title:'Programado creado', text:'Se disparará a su hora.', timer:1800, showConfirmButton:false });
          const tabProg = document.getElementById('tab-active-grid');
          if (tabProg && window.bootstrap?.Tab) window.bootstrap.Tab.getOrCreateInstance(tabProg).show();
        } else {
          Swal.fire({ icon:'success', title:'Viaje creado', timer:1200, showConfirmButton:false });
          const tabNow = document.getElementById('tab-active-cards');
          if (tabNow && window.bootstrap?.Tab) window.bootstrap.Tab.getOrCreateInstance(tabNow).show();
        }
      }
    } catch {}

    await window.refreshDispatch?.();

  } catch (e) {
    console.error(e);
    alert('No se pudo crear el viaje: ' + (e?.message || e));
  } finally {
    // restore button + flag
    isCreatingRide = false;
    if (btn) {
      btn.disabled = false;
      if (originalHtml !== null) btn.innerHTML = originalHtml;
    }
  }
}

function clearCreateFormUI() {
  // inputs básicos
  ['inFrom','inTo','pass-name','pass-phone','pass-account','ride-notes','fareAmount','pax'].forEach(id => {
    const el = qs('#'+id); if (el) el.value = '';
  });
  ['fromLat','fromLng','toLat','toLng'].forEach(id => {
    const el = qs('#'+id); if (el) el.value = '';
  });

  // mapa/capas
  try { window.layerRoute?.clearLayers?.(); } catch {}
  try { if (window.fromMarker){ window.fromMarker.remove(); window.fromMarker=null; } } catch {}
  try { if (window.toMarker){   window.toMarker.remove();   window.toMarker=null;   } } catch {}

  // stops markers + fields
  try { if (window.stop1Marker){ window.stop1Marker.remove(); window.stop1Marker=null; } } catch {}
  try { if (window.stop2Marker){ window.stop2Marker.remove(); window.stop2Marker=null; } } catch {}

  const s1Lat = qs('#stop1Lat'), s1Lng = qs('#stop1Lng'), s2Lat = qs('#stop2Lat'), s2Lng = qs('#stop2Lng');
  const inS1  = qs('#inStop1'),  inS2  = qs('#inStop2');
  const row1  = qs('#stop1Row'), row2  = qs('#stop2Row');

  if (s1Lat) s1Lat.value=''; if (s1Lng) s1Lng.value='';
  if (s2Lat) s2Lat.value=''; if (s2Lng) s2Lng.value='';
  if (inS1)  inS1.value='';  if (inS2)  inS2.value='';
  if (row1)  row1.style.display='none';
  if (row2)  row2.style.display='none';

  const rs = document.getElementById('routeSummary');
  if (rs) rs.innerText = 'Ruta: — · Zona: — · Cuando: ahora';

  try { resetWhenNow(); } catch {}

  // limpia marker del flujo de asignación
  try {
    if (window._assignPickupMarker) {
      try { (window.layerSuggested || window.layerRoute || window.map).removeLayer(window._assignPickupMarker); } catch {}
      try { window.map.removeLayer(window._assignPickupMarker); } catch {}
      window._assignPickupMarker = null;
    }
  } catch {}
}
