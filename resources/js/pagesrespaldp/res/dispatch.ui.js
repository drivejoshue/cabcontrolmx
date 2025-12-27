/* resources/js/pages/dispatch.ui.js */

import L from 'leaflet';

import { qs, jsonHeaders, escapeHtml, fmtWhen_db } from './dispatch.core.js';
import { resetWhenNow } from './dispatch.form_when.js';

// Si sectorStyle realmente vive en map.js (según lo que pegaste):
import { routeStyle, sectorStyle } from './dispatch.map.js';

// Si todavía NO exportas suggestedLineStyle desde map.js, lo definimos aquí.
// (Recomendación: exportarlo desde map.js y eliminar esta función duplicada)
function suggestedLineStyle(){
  // Requiere isDarkMode() en core. Si no existe, deja estilo fijo.
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  return isDark
    ? { color:'#4DD9FF', weight:5, opacity:.95, dashArray:'6,8' }
    : { color:'#0D6EFD', weight:4, opacity:.90, dashArray:'6,8' };
}

// -------------------------------------------------------
// State (quote debounce + cache)
// -------------------------------------------------------
let _quoteTimer = null;
let __lastQuote = null;

function _hasAB(){
  const aLat = parseFloat(qs('#fromLat')?.value);
  const aLng = parseFloat(qs('#fromLng')?.value);
  const bLat = parseFloat(qs('#toLat')?.value);
  const bLng = parseFloat(qs('#toLng')?.value);
  return Number.isFinite(aLat) && Number.isFinite(aLng) && Number.isFinite(bLat) && Number.isFinite(bLng);
}

export let isCreatingRide = false;
export function setCreatingRide(v){ isCreatingRide = !!v; }

export function setInput(sel, val){
  const el = qs(sel);
  if (el) el.value = val;
}

export function getAB(){
  const a=[parseFloat(qs('#fromLat')?.value), parseFloat(qs('#fromLng')?.value)];
  const b=[parseFloat(qs('#toLat')?.value),   parseFloat(qs('#toLng')?.value)];
  return {
    a,b,
    hasA:Number.isFinite(a[0])&&Number.isFinite(a[1]),
    hasB:Number.isFinite(b[0])&&Number.isFinite(b[1])
  };
}

export function getStops(){
  const s1=[parseFloat(qs('#stop1Lat')?.value), parseFloat(qs('#stop1Lng')?.value)];
  const s2=[parseFloat(qs('#stop2Lat')?.value), parseFloat(qs('#stop2Lng')?.value)];
  const arr=[];
  if (Number.isFinite(s1[0]) && Number.isFinite(s1[1])) arr.push(s1);
  if (Number.isFinite(s2[0]) && Number.isFinite(s2[1])) arr.push(s2);
  return arr;
}

export function clearQuoteUi(){
  const fa = qs('#fareAmount'); if (fa) fa.value = '';
  const rs = document.getElementById('routeSummary');
  if (rs) rs.innerText = `Ruta: — · Zona: — · Cuando: ${qs('#when-later')?.checked?'después':'ahora'}`;
}

export function bindScheduleRow(){
  qs('#when-now')?.addEventListener('change', ()=> {
    const row = qs('#scheduleRow'); if (!row) return;
    row.style.display = qs('#when-now').checked ? 'none' : '';
  });
  qs('#when-later')?.addEventListener('change', ()=> {
    const row = qs('#scheduleRow'); if (!row) return;
    row.style.display = qs('#when-later').checked ? '' : 'none';
  });
}

// -------------------------------------------------------
// Quote (auto + manual)
// -------------------------------------------------------
async function _doAutoQuote() {
  if (!_hasAB()) return;

  const aLat = parseFloat(qs('#fromLat').value);
  const aLng = parseFloat(qs('#fromLng').value);
  const bLat = parseFloat(qs('#toLat').value);
  const bLng = parseFloat(qs('#toLng').value);

  try {
    const stops = getStops();
    const r = await fetch('/api/dispatch/quote', {
      method: 'POST',
      headers: jsonHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({
        origin:      { lat: aLat, lng: aLng },
        destination: { lat: bLat, lng: bLng },
        stops:       stops.map(s => ({ lat: s[0], lng: s[1] })),
        round_to_step: 1.00
      })
    });

    const j = await r.json().catch(()=> ({}));
    if (!r.ok || j.ok===false) throw new Error(j?.msg || ('HTTP '+r.status));

    __lastQuote = j;

    const fa = qs('#fareAmount');
    if (fa) fa.value = j.amount;

    const rs = document.getElementById('routeSummary');
    if (rs) {
      const km  = (Number(j.distance_m||0)/1000).toFixed(1)+' km';
      const min = Math.round(Number(j.duration_s||0)/60)+' min';
      rs.innerText = `Ruta: ${km} · ${min} · Tarifa: $${j.amount}`;
    }
  } catch (e) {
    console.warn('[quote] fallo', e);
  }
}

export function autoQuoteIfReady() {
  clearTimeout(_quoteTimer);
  _quoteTimer = setTimeout(_doAutoQuote, 450);
}

// -------------------------------------------------------
// CAR ICONS (Leaflet DivIcon)
// -------------------------------------------------------
const CAR_W = 48, CAR_H = 40;

const CAR_SPRITES = {
  sedan: {
    free:     '/images/vehicles/sedan-free.png',
    offered:  '/images/vehicles/sedan.png',
    accepted: '/images/vehicles/sedan-acepted.png',
    en_route: '/images/vehicles/sedan-assigned.png',
    arrived:  '/images/vehicles/sedan-busy.png',
    on_board: '/images/vehicles/sedan-assigned.png',
    busy:     '/images/vehicles/sedan-busy.png',
    offline:  '/images/vehicles/sedan-offline.png',
  },
  van: {
    free:     '/images/vehicles/van-free.png',
    offered:  '/images/vehicles/van-assigned.png',
    accepted: '/images/vehicles/van-assigned.png',
    en_route: '/images/vehicles/van-assigned.png',
    arrived:  '/images/vehicles/van-assigned.png',
    on_board: '/images/vehicles/van-onboard.png',
    busy:     '/images/vehicles/van-busy.png',
    offline:  '/images/vehicles/van-offline.png',
  },
  vagoneta: {
    free:     '/images/vehicles/vagoneta-free.png',
    offered:  '/images/vehicles/vagoneta-assigned.png',
    accepted: '/images/vehicles/vagoneta-assigned.png',
    en_route: '/images/vehicles/vagoneta-assigned.png',
    arrived:  '/images/vehicles/vagoneta-assigned.png',
    on_board: '/images/vehicles/vagoneta-onboard.png',
    busy:     '/images/vehicles/vagoneta-busy.png',
    offline:  '/images/vehicles/vagoneta-offline.png',
  },
  premium: {
    free:     '/images/vehicles/premium-free.png',
    offered:  '/images/vehicles/premium-assigned.png',
    accepted: '/images/vehicles/premium-assigned.png',
    en_route: '/images/vehicles/premium-assigned.png',
    arrived:  '/images/vehicles/premium-assigned.png',
    on_board: '/images/vehicles/premium-onboard.png',
    busy:     '/images/vehicles/premium-busy.png',
    offline:  '/images/vehicles/premium-offline.png',
  },
};

function iconUrl(vehicle_type='sedan', vstate='free'){
  const t = (vehicle_type || 'sedan').toLowerCase();
  return CAR_SPRITES[t]?.[vstate] || CAR_SPRITES.sedan[vstate] || CAR_SPRITES.sedan.free;
}

export function scaleForZoom(z){
  if (z >= 18) return 1.35;
  if (z >= 16) return 1.20;
  if (z >= 14) return 1.00;
  return 0.85;
}

export function makeCarIcon(type, state){
  const src = iconUrl(type, state);
  const html = `
    <div class="cc-car-box" style="width:${CAR_W}px;height:${CAR_H}px;position:relative">
      <img class="cc-car-img cc-a cc-active"   src="${src}" width="${CAR_W}" height="${CAR_H}" alt="${type}">
      <img class="cc-car-img cc-b cc-inactive" src="${src}" width="${CAR_W}" height="${CAR_H}" alt="${type}">
    </div>`;
  return L.divIcon({
    className: 'cc-car-icon',
    html,
    iconSize: [CAR_W, CAR_H],
    iconAnchor: [CAR_W/2, CAR_H/2],
    tooltipAnchor: [0, -CAR_H/2]
  });
}

export function setMarkerBearing(marker, bearingDeg){
  const el = marker?.getElement?.();
  if (!el) return;
  const box = el.querySelector('.cc-car-box');
  if (!box) return;
  const b = ((Number(bearingDeg)||0) % 360 + 360) % 360;
  box.style.setProperty('--car-rot', `${b}deg`);
}

export function setMarkerScale(marker, scale){
  const el = marker?.getElement?.();
  if (!el) return;
  el.querySelector('.cc-car-box')?.style.setProperty('--car-scale', String(scale));
}

// inject css once
(function ensureCarCss(){
  if (window.__CC_CAR_CSS__) return;
  window.__CC_CAR_CSS__ = true;
  const style = document.createElement('style');
  style.textContent = `
    .cc-car-img{
      position:absolute; left:0; top:0;
      transform-origin:50% 50%;
      transform: rotate(var(--car-rot, 0deg)) scale(var(--car-scale, 1));
      transition: opacity 160ms linear, transform 120ms linear;
    }
    .cc-active  { opacity: 1; }
    .cc-inactive{ opacity: 0; }
  `;
  document.head.appendChild(style);
})();

// preload
(function preloadCarIcons(){
  if (window.__CC_CAR_PRELOAD__) return;
  window.__CC_CAR_PRELOAD__ = true;
  Object.values(CAR_SPRITES).forEach(states=>{
    Object.values(states).forEach(url=>{ const im = new Image(); im.src = url; });
  });
})();

export function setCarSprite(marker, nextSrc){
  const el = marker?.getElement?.();
  if (!el) return;
  const a = el.querySelector('.cc-car-img.cc-a');
  const b = el.querySelector('.cc-car-img.cc-b');
  if (!a || !b) return;

  const active   = el.querySelector('.cc-car-img.cc-active') || a;
  const inactive = active === a ? b : a;
  if (active.getAttribute('src') === nextSrc) return;

  const img = new Image();
  img.onload = () => {
    inactive.setAttribute('src', nextSrc);
    inactive.classList.remove('cc-inactive');
    inactive.classList.add('cc-active');
    active.classList.remove('cc-active');
    active.classList.add('cc-inactive');
  };
  img.src = nextSrc;
}

// -------------------------------------------------------
// Ride cards / actions
// -------------------------------------------------------
export function injectRideCardStyles(){
  if (window.__RIDE_CARD_STYLES__) return;
  window.__RIDE_CARD_STYLES__ = true;

  const style = document.createElement('style');
  style.id = 'cc-ride-card-styles';
  style.textContent = `/* (tu CSS tal cual) */`;
  document.head.appendChild(style);
}

// Nota: renderRideCard depende de shouldHideRideCard/deriveRideUi/isScheduledStatus
// que tú ya tienes hoy en dispatch.map.js (o en otro módulo). Déjalo así por ahora.
export function renderRideCard(ride) {
  // ... tu implementación actual (sin cambios de lógica) ...
  // Solo asegúrate que escapeHtml exista (ya lo importamos).
  return ''; // <- aquí pega tu HTML completo actual
}

export async function onRideAction(e, ctx = null) {
  const btn  = e.target.closest('[data-act]');
  if (!btn) return;
  const wrap = btn.closest('[data-ride-id]');
  if (!wrap) return;

  const rideId = Number(wrap.dataset.rideId);
  const act    = btn.dataset.act;

  try {
    if (act === 'assign') {
      const ride = ctx?.index?.rides?.get?.(rideId) || (typeof getRideById === 'function' ? getRideById(rideId) : null);
      if (typeof openAssignFlow === 'function') openAssignFlow(ride || { id: rideId });
      return;
    }

    if (act === 'view') {
      if (typeof focusRideOnMap === 'function') await focusRideOnMap(rideId);
      return;
    }

    if (act === 'reoffer') {
      // usa tu helper si existe; si no, fetch directo
      if (typeof window.postJSON === 'function') {
        await window.postJSON('/api/dispatch/tick', { ride_id: rideId });
      } else {
        await fetch('/api/dispatch/tick', {
          method:'POST',
          headers: jsonHeaders({ 'Content-Type':'application/json' }),
          body: JSON.stringify({ ride_id: rideId })
        });
      }
    }

    if (act === 'release') {
      alert('Acción "Liberar" no disponible (endpoint faltante).');
      return;
    }

    // cancel lo maneja SweetAlert por delegación aparte (btn-cancel)
    if (ctx?.polling?.refresh) await ctx.polling.refresh();
    else if (typeof refreshDispatch === 'function') await refreshDispatch();
  } catch (err) {
    console.error(err);
    alert('Acción fallida: ' + (err.message || err));
  }
}

export function bindRideActions(ctx = null){
  if (window.__rides_actions_wired__) return;
  document.addEventListener('click', (e)=>onRideAction(e, ctx));
  window.__rides_actions_wired__ = true;
}

// -------------------------------------------------------
// Cleanup helpers
// -------------------------------------------------------
export function removeFromAnyLayer(marker, { layerSuggested, layerRoute } = {}) {
  if (!marker) return;
  try {
    if (layerSuggested?.hasLayer?.(marker)) { layerSuggested.removeLayer(marker); return; }
  } catch {}
  try {
    if (layerRoute?.hasLayer?.(marker)) { layerRoute.removeLayer(marker); return; }
  } catch {}
  try { marker.remove(); } catch {}
}

export function clearRideFormAndMap(ctx = {}) {
  const {
    layerRoute,
    layerSuggested,
    map,
    fromMarkerRef,
    toMarkerRef,
    stop1MarkerRef,
    stop2MarkerRef,
  } = ctx;

  try {
    ['inFrom','inTo','pass-name','pass-phone','pass-account','ride-notes','fareAmount','pax'].forEach(id=>{
      const el = qs('#'+id); if (el) el.value='';
    });
    ['fromLat','fromLng','toLat','toLng'].forEach(id=>{
      const el = qs('#'+id); if (el) el.value='';
    });

    try { layerRoute?.clearLayers?.(); } catch {}

    try { if (fromMarkerRef?.current) { fromMarkerRef.current.remove(); fromMarkerRef.current=null; } } catch {}
    try { if (toMarkerRef?.current)   { toMarkerRef.current.remove();   toMarkerRef.current=null; } } catch {}
    try { if (stop1MarkerRef?.current){ stop1MarkerRef.current.remove();stop1MarkerRef.current=null; } } catch {}
    try { if (stop2MarkerRef?.current){ stop2MarkerRef.current.remove();stop2MarkerRef.current=null; } } catch {}

    const rs = document.getElementById('routeSummary');
    if (rs) rs.innerText = 'Ruta: — · Zona: — · Cuando: ahora';

    try { resetWhenNow?.(); } catch {}
  } catch {}

  try {
    if (window._assignPickupMarker) {
      try { (layerSuggested || layerRoute || map)?.removeLayer?.(window._assignPickupMarker); } catch {}
      window._assignPickupMarker = null;
    }
  } catch {}
}

export function wireUiActions(ctx = {}) {
  qs('#btnClear')?.addEventListener('click', () => clearRideFormAndMap(ctx));
  qs('#btnReset')?.addEventListener('click', () => clearRideFormAndMap(ctx));

  qs('#btnQuote')?.addEventListener('click', async ()=>{
    if (!_hasAB()) { alert('Indica origen y destino para cotizar.'); return; }
    await _doAutoQuote();
  });

  window.addEventListener('theme:changed', (e)=>{
    const dark = e?.detail?.theme === 'dark';
    const mapEl = ctx.mapEl || document.getElementById('map');
    mapEl?.classList?.toggle('map-dark', dark);

    try { ctx.layerSectores?.eachLayer?.(l => { try { l.setStyle?.(sectorStyle()); } catch {} }); } catch {}

    try {
      ctx.layerRoute?.eachLayer?.(l => {
        try {
          if (!(l instanceof L.Polyline)) return;
          const cls = String(l.options?.className || '');
          if (cls.includes('cc-route')) l.setStyle(routeStyle());
          else if (cls.includes('cc-suggested')) l.setStyle(suggestedLineStyle());
        } catch {}
      });
    } catch {}

    setTimeout(()=>{ try { ctx.map?.invalidateSize?.(); } catch {} }, 150);
  });
}
