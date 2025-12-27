// resources/js/pages/dispatch.rides_panel.js

import { jsonHeaders } from './dispatch.core.js';

// ---------------------------
// Status canonicalization
// ---------------------------
const _norm = s => String(s || '').toLowerCase().trim();

export function canonStatus(s){
  const k = _norm(s);
  if (k === 'onboard') return 'on_board';
  if (k === 'enroute') return 'en_route';
  return k;
}

const _SET_WAITING = new Set(['requested','pending','new','offered','offering']);
const _SET_ACTIVE  = new Set(['accepted','assigned','en_route','arrived','boarding','on_board']);
const _SET_SCHED   = new Set(['scheduled']);

export const isWaiting   = r => _SET_WAITING.has(canonStatus(r?.status));
export const isActive    = r => _SET_ACTIVE.has(canonStatus(r?.status));
export const isScheduled = r => _SET_SCHED.has(canonStatus(r?.status));

export function statusBadgeClass(s){
  const k = canonStatus(s);
  if (k === 'en_route') return 'bg-primary-subtle text-primary';
  if (k === 'arrived')  return 'bg-warning-subtle text-warning';
  if (k === 'on_board') return 'bg-success-subtle text-success';
  if (k === 'assigned' || k === 'accepted' || k === 'boarding')
    return 'bg-secondary-subtle text-secondary';
  return 'bg-light text-body';
}

// ---------------------------
// Render genérico en contenedor
// ---------------------------
export function renderActiveRidesInto(ctx, containerSel, badgeSel, rides){
  const el = document.querySelector(containerSel); if(!el) return;
  el.innerHTML = '';

  const b = document.querySelector(badgeSel);
  if (b) b.innerText = (rides||[]).length;

  // índice rápido global para acciones
  window._ridesIndex = new Map();

  const renderRideCard = ctx?.rides?.renderRideCard || window.renderRideCard;
  const highlightRideOnMap = ctx?.mapFx?.highlightRideOnMap || window.highlightRideOnMap;

  (rides||[]).forEach(r=>{
    try { window._ridesIndex.set(r.id, r); } catch {}

    const html = renderRideCard?.(r);
    if (!html) return;

    const wrapper = document.createElement('div');
    wrapper.innerHTML = html;
    const card = wrapper.firstElementChild;
    if (!card) return;

    card.querySelector('[data-act="view"]')
      ?.addEventListener('click', ()=> highlightRideOnMap?.(r));

    card.querySelector('[data-action="cancel-ride"]')
      ?.addEventListener('click', (e)=> e.stopPropagation());

    el.appendChild(card);
  });
}

export function renderActiveRides(ctx, rides){
  renderActiveRidesInto(ctx, '#panel-active', '#badgeActivos', rides);
}

export function renderActiveRidesLeft(ctx, rides){
  renderActiveRidesInto(ctx, '#left-active', '#badgeActivosLeft', rides);
}

// ---------------------------
// Stops hydration
// ---------------------------
export async function hydrateRideStops(ride) {
  if (Array.isArray(ride.stops) && ride.stops.length) return ride;

  if (Array.isArray(ride.stops_json) && ride.stops_json.length) {
    ride.stops = ride.stops_json;
    ride.stops_count = ride.stops_json.length;
    ride.stop_index = ride.stop_index ?? 0;
    return ride;
  }

  if (typeof ride.stops_json === 'string' && ride.stops_json.trim() !== '') {
    try {
      const arr = JSON.parse(ride.stops_json);
      if (Array.isArray(arr)) {
        ride.stops = arr;
        ride.stops_count = arr.length;
        ride.stop_index = ride.stop_index ?? 0;
        return ride;
      }
    } catch {}
  }

  // fallback: pedir ride completo
  try {
    const r = await fetch(`/api/rides/${ride.id}`, { headers: jsonHeaders() });
    if (r.ok) {
      const d = await r.json();
      ride.stops         = Array.isArray(d.stops) ? d.stops : [];
      ride.stops_json    = ride.stops;
      ride.stops_count   = d.stops_count ?? ride.stops.length;
      ride.stop_index    = d.stop_index ?? 0;
      ride.distance_m    = d.distance_m ?? ride.distance_m;
      ride.duration_s    = d.duration_s ?? ride.duration_s;
      ride.quoted_amount = d.quoted_amount ?? ride.quoted_amount;
    }
  } catch (e) {
    console.warn('hydrateRideStops fallo', e);
  }
  return ride;
}

// ---------------------------
// Load active rides (si lo sigues usando)
// ---------------------------
export async function loadActiveRides(ctx = null) {
  const panel = document.getElementById('panel-active');
  if (!panel) return;

  const renderRideCard = ctx?.rides?.renderRideCard || window.renderRideCard;

  try {
    const r = await fetch('/api/rides?status=active', { headers: jsonHeaders() });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    let list = await r.json();

    list = await Promise.all(list.map(hydrateRideStops));

    panel.innerHTML = list.map(r0 => renderRideCard?.(r0) || '').join('')
      || '<div class="text-muted small">Sin viajes</div>';

    const b = document.getElementById('badgeActivos');
    if (b) b.textContent = list.length;

  } catch (e) {
    console.error('loadActiveRides fallo:', e);
    panel.innerHTML = `<div class="text-danger small">Error cargando activos</div>`;
  }
}

// ---------------------------
// Invert route (regreso)
// ---------------------------
export function invertRoute(ctx = null) {
  const qs = ctx?.dom?.qs || window.qs || ((s)=>document.querySelector(s));
  const showToast = ctx?.ui?.showToast || window.showToast;

  const clearAllStops = ctx?.rideForm?.clearAllStops || window.clearAllStops;
  const drawRoute = ctx?.mapFx?.drawRoute || window.drawRoute;
  const autoQuoteIfReady = ctx?.rideForm?.autoQuoteIfReady || window.autoQuoteIfReady;

  const fromLat  = qs('#fromLat')?.value;
  const fromLng  = qs('#fromLng')?.value;
  const fromAddr = qs('#inFrom')?.value;

  const toLat  = qs('#toLat')?.value;
  const toLng  = qs('#toLng')?.value;
  const toAddr = qs('#inTo')?.value;

  if (!fromLat || !fromLng || !toLat || !toLng) {
    showToast?.('Se necesitan origen y destino para invertir', 'warning');
    return;
  }

  qs('#fromLat').value = toLat;
  qs('#fromLng').value = toLng;
  qs('#inFrom').value  = toAddr;

  qs('#toLat').value = fromLat;
  qs('#toLng').value = fromLng;
  qs('#inTo').value  = fromAddr;

  try { clearAllStops?.(); } catch {}
  try { drawRoute?.({ quiet: true }); } catch {}
  try { autoQuoteIfReady?.(); } catch {}

  showToast?.('Ruta invertida - Paradas limpiadas', 'success');
}

export function wireInvertRoute(ctx = null) {
  const qs = ctx?.dom?.qs || window.qs || ((s)=>document.querySelector(s));
  qs('#btnInvertRoute')?.addEventListener('click', () => invertRoute(ctx));
}

// ---------------------------
// Render “Ahora” (waiting/offered) en panel derecho
// ---------------------------
export function renderRightNowCards(ctx, rides){
  const host = document.getElementById('panel-active');
  if (!host) return;

  const waiting = (rides || []).filter(isWaiting);

  window._ridesIndex = new Map(waiting.map(r => [r.id, r]));

  const renderRideCard = ctx?.rides?.renderRideCard || window.renderRideCard;
  const highlightRideOnMap = ctx?.mapFx?.highlightRideOnMap || window.highlightRideOnMap;

  host.innerHTML = waiting.length
    ? waiting.map(r => renderRideCard?.(r) || '').join('')
    : `<div class="text-muted px-2 py-2">Sin solicitudes.</div>`;

  host.querySelectorAll('[data-act="view"]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const card = btn.closest('[data-ride-id]');
      const id   = Number(card?.dataset?.rideId);
      const r    = window._ridesIndex.get(id);
      if (r) highlightRideOnMap?.(r);
    });
  });

  const b = document.querySelector('#tab-active-cards .badge');
  if (b) b.textContent = String(waiting.length);
}

// ---------------------------
// Programados (panel derecho)
// ---------------------------
export function renderRightScheduledCards(ctx, rides){
  const host = document.getElementById('panel-active-scheduled');
  if (!host) return;

  const scheduled = (rides || []).filter(isScheduled);

  window._ridesIndex = new Map(scheduled.map(r => [r.id, r]));

  const renderRideCard = ctx?.rides?.renderRideCard || window.renderRideCard;
  const highlightRideOnMap = ctx?.mapFx?.highlightRideOnMap || window.highlightRideOnMap;

  host.innerHTML = scheduled.length
    ? scheduled.map(r => renderRideCard?.(r) || '').join('')
    : `<div class="text-muted px-2 py-2">Sin programados.</div>`;

  host.querySelectorAll('[data-act="view"]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const card = btn.closest('[data-ride-id]');
      const id   = Number(card?.dataset?.rideId);
      const r    = window._ridesIndex.get(id);
      if (r) highlightRideOnMap?.(r);
    });
  });

  const b = document.querySelector('#tab-active-grid .badge');
  if (b) b.textContent = String(scheduled.length);
}

// ---------------------------
// getRideById (reutilizable)
// ---------------------------
export function getRideById(id){
  if (!id) return null;
  const r = window._ridesIndex?.get?.(id);
  if (r) return r;

  const list = Array.isArray(window._lastActiveRides) ? window._lastActiveRides : [];
  return list.find(x => Number(x.id) === Number(id)) || null;
}

// ---------------------------
// Orquestador paneles derecha
// ---------------------------
export function renderRightPanels(ctx, rides){
  const renderDockActive = ctx?.dock?.renderDockActive || window.renderDockActive;

  renderRightNowCards(ctx, rides);
  renderRightScheduledCards(ctx, rides);
  renderDockActive?.(ctx, rides);
}

// ---------------------------
// Aplicar preset de cliente
// ---------------------------
export function applyRidePreset(ctx, preset){
  const qs = ctx?.dom?.qs || window.qs || ((s)=>document.querySelector(s));

  const hasExisting =
    (qs('#fromLat')?.value && qs('#fromLng')?.value) ||
    (qs('#toLat')?.value   && qs('#toLng')?.value);

  if (hasExisting) {
    const ok = confirm('Ya hay una ruta cargada. ¿Quieres reemplazarla por la del cliente seleccionado?');
    if (!ok) return;
  }

  if (preset?.origin) {
    qs('#inFrom').value  = preset.origin.label || '';
    qs('#fromLat').value = preset.origin.lat ?? '';
    qs('#fromLng').value = preset.origin.lng ?? '';
  }
  if (preset?.dest) {
    qs('#inTo').value  = preset.dest.label || '';
    qs('#toLat').value = preset.dest.lat ?? '';
    qs('#toLng').value = preset.dest.lng ?? '';
  }

  const drawRoutePreview = ctx?.mapFx?.drawRoutePreview || window.drawRoutePreview;
  if (typeof drawRoutePreview === 'function') drawRoutePreview();
}
