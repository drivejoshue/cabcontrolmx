/* resources/js/pages/dispatch.assign.js */
import L from 'leaflet';
import { jsonHeaders, distKm as distKmCore } from './dispatch.core.js';

// =========================
// Estado interno (módulo)
// =========================
let _assignRide = null;
let _assignSelected = null;
let _assignPanel = null;

// Helpers para ctx con fallback a window.*
function getMap(ctx){ return ctx?.map || window.map; }
function getLayerRoute(ctx){ return ctx?.layers?.route || ctx?.layerRoute || window.layerRoute; }
function getLayerSuggested(ctx){ return ctx?.layers?.suggested || ctx?.layerSuggested || window.layerSuggested; }
function getDriverPins(ctx){ return ctx?.driverPins || window.driverPins; }

function getDistKm(ctx){
  return ctx?.geo?.distKm || window.distKm || distKmCore || null;
}

// =====================================================
// Limpia todas las previsualizaciones (líneas sugeridas)
// =====================================================
export function clearSuggestedLines(ctx) {
  try {
    const layerRoute = getLayerRoute(ctx);
    const layerSuggested = getLayerSuggested(ctx);
    const map = getMap(ctx);
    const driverPins = getDriverPins(ctx);

    if (!layerRoute) return;

    // 1) Remueve TODAS las líneas preview por className
    const toRemove = [];
    layerRoute.eachLayer(l => {
      try {
        if (l instanceof L.Polyline) {
          const cls = (l.options && l.options.className) || '';
          if (String(cls).includes('cc-suggested')) toRemove.push(l);
        }
      } catch {}
    });
    toRemove.forEach(l => { try { layerRoute.removeLayer(l); } catch {} });

    // 2) Limpia referencias por driver
    if (driverPins?.forEach) {
      driverPins.forEach(e => {
        if (e?.previewLine) {
          try { layerRoute.removeLayer(e.previewLine); } catch {}
          e.previewLine = null;
        }
      });
    }

    // 3) Limpia preview general
    if (ctx && ctx._assignPreviewLine) {
      try { layerRoute.removeLayer(ctx._assignPreviewLine); } catch {}
      ctx._assignPreviewLine = null;
    }

    // 4) Limpia pickup marker
    try {
      if (window._assignPickupMarker) {
        try { (layerSuggested || layerRoute || map)?.removeLayer?.(window._assignPickupMarker); } catch {}
        try { map?.removeLayer?.(window._assignPickupMarker); } catch {}
        window._assignPickupMarker = null;
      }
    } catch {}
  } catch (err) {
    console.warn('[clearSuggestedLines] error', err);
  }
}

// =====================================================
// Hook cuando se asigna/cierra panel (post-cleanup)
// =====================================================
export function onRideAssigned(ctx, ride) {
  clearSuggestedLines(ctx);

  const showDriverToPickup = ctx?.mapFx?.showDriverToPickup || window.showDriverToPickup;

  if (ride?.driver_id && Number.isFinite(+ride.origin_lat) && Number.isFinite(+ride.origin_lng)) {
    try { showDriverToPickup?.(ride.driver_id, +ride.origin_lat, +ride.origin_lng); } catch {}
  }
}

// =====================================================
// Panel principal de asignación (Offcanvas)
// =====================================================
export function renderAssignPanel(ctx, ride, candidates) {
  _assignRide = ride;
  _assignSelected = null;

  const map = getMap(ctx);
  const layerRoute = getLayerRoute(ctx);
  const layerSuggested = getLayerSuggested(ctx);

  const IconOrigin = ctx?.icons?.origin || window.IconOrigin;
  const IconDest   = ctx?.icons?.dest   || window.IconDest;

  const driverPins = getDriverPins(ctx);

  const ensureDriverPreviewLine =
    ctx?.mapFx?.ensureDriverPreviewLine || window.ensureDriverPreviewLine;

  const suggestedLineStyle =
    ctx?.mapFx?.suggestedLineStyle || window.suggestedLineStyle;

  const suggestedLineSelectedStyle =
    ctx?.mapFx?.suggestedLineSelectedStyle || window.suggestedLineSelectedStyle;

  const clearAllPreviews = ctx?.mapFx?.clearAllPreviews || window.clearAllPreviews;
  const refreshDispatch  = ctx?.polling?.refresh || window.refreshDispatch;

  // -----------------------------
  // Pintar pickup marker (Pasajero)
  // -----------------------------
  try {
    if (window._assignPickupMarker) {
      try { (layerSuggested || layerRoute || map)?.removeLayer?.(window._assignPickupMarker); } catch {}
      try { map?.removeLayer?.(window._assignPickupMarker); } catch {}
      window._assignPickupMarker = null;
    }

    const lat = Number(ride?.origin_lat);
    const lng = Number(ride?.origin_lng);

    if (Number.isFinite(lat) && Number.isFinite(lng) && map) {
      const targetLayer = (layerSuggested && map.hasLayer?.(layerSuggested))
        ? layerSuggested
        : (layerRoute || map);

      const ic = IconOrigin || IconDest || L.divIcon({
        className:'cc-pin-fallback',
        html:'<div style="width:16px;height:16px;border-radius:50%;background:#2F6FED;border:2px solid #fff"></div>',
        iconSize:[16,16],
        iconAnchor:[8,8]
      });

      window._assignPickupMarker = L.marker([lat, lng], {
        icon: ic,
        zIndexOffset: 950,
        riseOnHover: true
      })
      .bindTooltip('Pasajero', { offset:[0,-26] })
      .addTo(targetLayer);
    }
  } catch (err) {
    console.warn('[assignPanel] pickup marker error:', err);
  }

  // -----------------------------
  // Render lista candidatos
  // -----------------------------
  const el = document.getElementById('assignPanelBody');
  if (!el) return;

  if (!candidates?.length) {
    el.innerHTML = `<div class="text-muted">No hay conductores cercanos.</div>`;
  } else {
    el.innerHTML = `<div class="list-group" id="assignList" style="max-height: 60vh; overflow:auto"></div>`;
    const list = el.querySelector('#assignList');

    const TOP_N = 12;
    const ordered = [...candidates]
      .sort((a,b)=> (a.distance_km ?? 9e9) - (b.distance_km ?? 9e9))
      .slice(0, TOP_N);

    ordered.forEach(c => {
      const id = c.id || c.driver_id;
      const dist = (c.distance_km != null) ? `${c.distance_km.toFixed(2)} km` : '';

      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
      item.dataset.driverId = id;

      item.innerHTML = `
        <div>
          <div><b>${c.name || ('Driver '+id)}</b> <span class="text-muted">(${String(c.vehicle_type||'sedan')})</span></div>
          <div class="small text-muted">${dist}</div>
        </div>
        <span class="badge bg-secondary">${id}</span>
      `;

      item.addEventListener('click', async () => {
        _assignSelected = id;

        list.querySelectorAll('.active').forEach(n => n.classList.remove('active'));
        item.classList.add('active');

        // restilar todas a “normal”
        driverPins?.forEach?.(e => {
          if (e?.previewLine) {
            try { e.previewLine.setStyle(suggestedLineStyle?.()); } catch {}
          }
        });

        // esta a “seleccionado”
        const line = await ensureDriverPreviewLine?.(id, ride);
        if (line) {
          try { line.setStyle(suggestedLineSelectedStyle?.()); line.bringToFront?.(); } catch {}
        }

        const btnAssign = document.getElementById('btnDoAssign');
        if (btnAssign) btnAssign.disabled = !_assignSelected;
      });

      list.appendChild(item);
    });

    // DIBUJAR TODAS LAS LÍNEAS AL ABRIR (stagger)
    (async () => {
      for (let i=0; i<ordered.length; i++) {
        const id = ordered[i].id || ordered[i].driver_id;
        try { await ensureDriverPreviewLine?.(id, ride); } catch {}
        await new Promise(res => setTimeout(res, 90));
      }
      const firstBtn = list.querySelector('.list-group-item');
      if (firstBtn) firstBtn.click();
    })();
  }

  // -----------------------------
  // Botón Asignar
  // -----------------------------
  const btn = document.getElementById('btnDoAssign');
  if (btn) {
    btn.onclick = async () => {
      if (!_assignSelected || !_assignRide) return;
      btn.disabled = true;

      try {
        const r = await fetch('/api/dispatch/assign', {
          method:'POST',
          headers: jsonHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify({ ride_id:_assignRide.id, driver_id:_assignSelected })
        });

        const j = await r.json().catch(()=>({}));
        if (!r.ok || j.ok === false) throw new Error(j?.msg || ('HTTP '+r.status));

        try { clearAllPreviews?.(); } catch {}
        try {
          if (window._assignPickupMarker) {
            try { layerRoute?.removeLayer?.(window._assignPickupMarker); } catch {}
            window._assignPickupMarker = null;
          }
        } catch {}

        try { _assignPanel?.hide?.(); } catch {}
        await refreshDispatch?.();
      } catch(e) {
        alert('No se pudo asignar: ' + (e.message || e));
      } finally {
        btn.disabled = false;
      }
    };
  }

  // -----------------------------
  // Offcanvas open + cleanup
  // -----------------------------
  const panelEl = document.getElementById('assignPanel');
  if (!panelEl) return;

  _assignPanel = _assignPanel || new bootstrap.Offcanvas(panelEl, { backdrop:false });

  // (re)bind: evita acumulación
  panelEl.removeEventListener?.('hidden.bs.offcanvas', panelEl.__onHiddenAssign);
  panelEl.__onHiddenAssign = () => onRideAssigned(ctx, _assignRide);
  panelEl.addEventListener('hidden.bs.offcanvas', panelEl.__onHiddenAssign);

  _assignPanel.show();
}

// =====================================================
// Flujo: fetch candidatos y abrir panel
// =====================================================
export function openAssignFlow(ctx, ride) {
  const lat = Number(ride?.origin_lat), lng = Number(ride?.origin_lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    alert('Este servicio no tiene origen válido.');
    return;
  }

  const driverPins = getDriverPins(ctx);
  const distKm = getDistKm(ctx);

  fetch(`/api/dispatch/nearby-drivers?lat=${ride.origin_lat}&lng=${ride.origin_lng}&km=3`, { headers: jsonHeaders() })
    .then(r => r.ok ? r.json() : Promise.reject(r.status))
    .then(list => {
      let candidates = Array.isArray(list) ? list : [];

      // fallback: construir candidatos desde pins
      if (!candidates.length && driverPins?.forEach && typeof distKm === 'function') {
        const tmp = [];
        driverPins.forEach((e, id) => {
          const ll = e.marker?.getLatLng?.();
          if (!ll) return;
          const dk = distKm(Number(ride.origin_lat), Number(ride.origin_lng), ll.lat, ll.lng);
          tmp.push({
            id,
            name: e.name || ('Driver '+id),
            vehicle_type: e.type || 'sedan',
            vehicle_plate: e.plate || '',
            distance_km: dk
          });
        });
        candidates = tmp.filter(c => c.distance_km <= 4);
      }

      renderAssignPanel(ctx, ride, candidates);
    })
    .catch(e => {
      console.warn('[assign] nearby-drivers error', e);
      renderAssignPanel(ctx, ride, []);
    });
}

// =====================================================
// Legacy: confirmAssign (modal viejo) - si aún lo usas
// =====================================================
export async function confirmAssign(ctx, ride, driver) {
  const refreshDispatch = ctx?.polling?.refresh || window.refreshDispatch;
  const showDriverToPickup = ctx?.mapFx?.showDriverToPickup || window.showDriverToPickup;
  const driverPins = getDriverPins(ctx);
  const clearAllPreviews = ctx?.mapFx?.clearAllPreviews || window.clearAllPreviews;

  try {
    const r = await fetch('/api/dispatch/assign', {
      method: 'POST',
      headers: jsonHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ ride_id: ride.id, driver_id: driver.id })
    });

    if (!r.ok) {
      const txt = await r.text().catch(()=> '');
      throw new Error(txt || ('HTTP '+r.status));
    }

    // pinta línea sugerida driver → origen
    try { showDriverToPickup?.(driver.id, ride.origin_lat, ride.origin_lng); } catch {}

    // sube prioridad visual
    const pin = driverPins?.get?.(driver.id);
    if (pin?.marker?.setZIndexOffset) pin.marker.setZIndexOffset(900);

    try { clearAllPreviews?.(); } catch {}
    await refreshDispatch?.();
  } catch(e) {
    console.error(e);
    alert('No se pudo asignar.');
  }
}
