/* resources/js/pages/dispatch.map.js */
import L from 'leaflet';
import { qs, jsonHeaders, isDarkMode, decodePolyline } from './dispatch.core.js';

/**
 * ============================================================
 *  Estado interno (evitar globals implícitos)
 *  - Puedes inyectar map/layers/servicios con setMapRuntime()
 *  - Si NO inyectas, intentamos caer a window.*
 * ============================================================
 */
let map = null;
let layerRoute = null;

// Markers del formulario
let fromMarker = null;
let toMarker   = null;
let stop1Marker = null;
let stop2Marker = null;

// Google Directions (opcional)
let gDirService = null;

// Icons Leaflet (L.Icon)
let IconOrigin = null;
let IconDest   = null;
let IconStop   = null;

// callbacks externos (si quieres desacoplar)
let _callbacks = {
  getAB: null,
  getStops: null,
  setInput: null,
  clearQuoteUi: null,
  autoQuoteIfReady: null,

  // panel refresh (opcional; idealmente esto NO vive en map.js)
  renderQueues: null,
  renderRightNowCards: null,
  renderRightScheduledCards: null,
  renderDockActive: null,
  renderDrivers: null,
  updateSuggestedRoutes: null,

  // dependencias de drivers/map helpers (pueden venir de otros módulos)
  driverPins: null,
  setMarkerScale: null,
  scaleForZoom: null,
};

// Permite inyectar runtime desde dispatch.js (init)
export function setMapRuntime(rt = {}) {
  if (rt.map) map = rt.map;
  if (rt.layerRoute) layerRoute = rt.layerRoute;
  if (rt.gDirService) gDirService = rt.gDirService;

  if (rt.IconOrigin) IconOrigin = rt.IconOrigin;
  if (rt.IconDest)   IconDest   = rt.IconDest;
  if (rt.IconStop)   IconStop   = rt.IconStop;

  if (rt.fromMarker !== undefined) fromMarker = rt.fromMarker;
  if (rt.toMarker   !== undefined) toMarker   = rt.toMarker;
  if (rt.stop1Marker !== undefined) stop1Marker = rt.stop1Marker;
  if (rt.stop2Marker !== undefined) stop2Marker = rt.stop2Marker;

  if (rt.callbacks) {
    _callbacks = { ..._callbacks, ...rt.callbacks };
  }
}

// Accesores seguros (para otras partes si lo requieren)
export function getMap() { return map || window.map || null; }
export function getLayerRoute() { return layerRoute || window.layerRoute || null; }

export const CENTER_DEFAULT = [19.4326, -99.1332];
export const DEFAULT_ZOOM   = 13;

export function getTenantMap(){
  return (window.ccTenant && window.ccTenant.map) || null;
}

export function resolveCenterZoom(){
  const TENANT_MAP = getTenantMap();

  const CENTER = (TENANT_MAP && Number.isFinite(+TENANT_MAP.lat) && Number.isFinite(+TENANT_MAP.lng))
    ? [Number(TENANT_MAP.lat), Number(TENANT_MAP.lng)]
    : CENTER_DEFAULT;

  const MAP_ZOOM = (TENANT_MAP && Number.isFinite(+TENANT_MAP.zoom))
    ? Number(TENANT_MAP.zoom)
    : DEFAULT_ZOOM;

  const COVERAGE_RADIUS_KM = (TENANT_MAP && Number.isFinite(+TENANT_MAP.radius_km))
    ? Number(TENANT_MAP.radius_km)
    : 8;

  return { CENTER, MAP_ZOOM, COVERAGE_RADIUS_KM };
}

export const OSM = {
  url:  'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
  attr: '&copy; OpenStreetMap contributors'
};

export function getTenantIcons(){
  return (window.ccTenant && window.ccTenant.map_icons) || {
    origin: '/images/origen.png',
    dest:   '/images/destino.png',
    stand:  '/images/marker-parqueo5.png',
    stop:   '/images/stopride.png',
  };
}

export function routeStyle(){
  return (isDarkMode()
    ? { color:'#943DD4', weight:5, opacity:.95 }
    : { color:'#0717F0', weight:4, opacity:.95 }
  );
}

export function sectorStyle(){
  return (isDarkMode()
    ? { color:'#FF6B6B', fillColor:'#FF6B6B', fillOpacity:.12, weight:2 }
    : { color:'#2A9DF4', fillColor:'#2A9DF4', fillOpacity:.18, weight:2 }
  );
}

export function pointsFromGoogleRoute(route){
  if (route?.overview_path?.length) {
    return route.overview_path.map(p => [p.lat(), p.lng()]);
  }
  if (route?.overview_polyline?.points) {
    return decodePolyline(route.overview_polyline.points);
  }
  return [];
}

export function reverseGeocode(latlng, inputSel){
  const geocoder = window.google?.maps?.Geocoder ? new window.google.maps.Geocoder() : null;
  if (!geocoder) return;

  geocoder.geocode(
    { location: { lat: latlng[0], lng: latlng[1] } },
    (res, status) => {
      if (status === 'OK' && res?.[0]) {
        const el = qs(inputSel);
        if (el) el.value = res[0].formatted_address;
      }
    }
  );
}



// ==========================
// Public API (exports)
// ==========================

/**
 * updateSuggestedRoutes(ctx, rides)
 * - Mantiene compatibilidad con código viejo que quizá expone window.updateSuggestedRoutes(...)
 * - Si todavía no implementas sugeridas, queda como NO-OP seguro (no rompe polling).
 */
export async function updateSuggestedRoutes(ctx, rides) {
  try {
    // 1) Si existe una implementación global (legacy), úsala.
    if (typeof window.updateSuggestedRoutes === 'function') {
      return await window.updateSuggestedRoutes(rides, ctx);
    }

    // 2) Si tienes una implementación interna no exportada con otro nombre,
    //    llama aquí (ajusta el nombre si aplica).
    // if (typeof _updateSuggestedRoutesInternal === 'function') {
    //   return await _updateSuggestedRoutesInternal(rides, ctx);
    // }

    // 3) No-op: todavía no hay sugeridas (evita romper build).
    return;
  } catch (e) {
    console.warn('[map] updateSuggestedRoutes error', e);
  }
}




// ============================================================
// Wire: mostrar/ocultar fila de schedule (SIN side-effects)
// ============================================================
export function wireScheduleRowToggle(){
  qs('#when-now')?.addEventListener('change', ()=> {
    const row = qs('#scheduleRow'); if (!row) return;
    row.style.display = qs('#when-now').checked ? 'none' : '';
  });

  qs('#when-later')?.addEventListener('change', ()=> {
    const row = qs('#scheduleRow'); if (!row) return;
    row.style.display = qs('#when-later').checked ? '' : 'none';
  });
}

// ============================================================
// Helpers internos con fallback a callbacks/window
// ============================================================

function _ctxLayerRoute() { return getLayerRoute(); }

function _getAB() {
  if (typeof _callbacks.getAB === 'function') return _callbacks.getAB();
  if (typeof window.getAB === 'function') return window.getAB();
  return { a:null, b:null, hasA:false, hasB:false };
}
function _getStops() {
  if (typeof _callbacks.getStops === 'function') return _callbacks.getStops();
  if (typeof window.getStops === 'function') return window.getStops();
  return [];
}
function _setInput(sel, v) {
  if (typeof _callbacks.setInput === 'function') return _callbacks.setInput(sel, v);
  if (typeof window.setInput === 'function') return window.setInput(sel, v);
  const el = qs(sel); if (el) el.value = (v ?? '');
}
function _clearQuoteUi() {
  if (typeof _callbacks.clearQuoteUi === 'function') return _callbacks.clearQuoteUi();
  if (typeof window.clearQuoteUi === 'function') return window.clearQuoteUi();
}
function _autoQuoteIfReady() {
  if (typeof _callbacks.autoQuoteIfReady === 'function') return _callbacks.autoQuoteIfReady();
  if (typeof window.autoQuoteIfReady === 'function') return window.autoQuoteIfReady();
}

// ============================================================
// Ruta principal del formulario (A->B + stops)
// ============================================================
export async function drawRoute({ quiet=false } = {}) {
  try {
    const lr = _ctxLayerRoute();
    const mp = _ctxMap();
    if (!lr || !mp) return;

    lr.clearLayers();

    const { a,b,hasA,hasB } = _getAB();
    const rs = document.getElementById('routeSummary');

    if (!hasA || !hasB) {
      _clearQuoteUi();
      if (rs) {
        rs.innerText = 'Ruta: — · Zona: — · Cuando: ' +
          (qs('#when-later')?.checked ? 'después' : 'ahora');
      }
      return;
    }

    const stops = _getStops();

    // Google Directions (con tráfico) si está disponible
    if (gDirService && window.google?.maps) {
      try {
        const waypts = stops.map(s => ({ location:{lat:s[0], lng:s[1]} }));

        const res = await new Promise((resolve,reject)=>{
          gDirService.route({
            origin: {lat:a[0], lng:a[1]},
            destination: {lat:b[0], lng:b[1]},
            travelMode: google.maps.TravelMode.DRIVING,
            region: 'MX',
            provideRouteAlternatives: false,
            drivingOptions: {
              departureTime: new Date(),
              trafficModel: 'bestguess'
            },
            waypoints: waypts.length ? waypts : undefined,
          }, (r,s)=> s==='OK' ? resolve(r) : reject({status:s, r}));
        });

        const route = res.routes?.[0];
        const leg   = route?.legs?.[0];
        const pts   = pointsFromGoogleRoute(route);

        if (pts.length){
          const poly = L.polyline(
            pts,
            { pane:'routePane', className:'cc-route', ...routeStyle() }
          );
          poly.addTo(lr);
          mp.fitBounds(poly.getBounds().pad(0.15), { padding:[40,40] });
        } else {
          if (!quiet) console.debug('[ROUTE] Directions OK sin polyline → OSRM');
          _autoQuoteIfReady();
          await drawRouteWithOSRM(a,b,stops,{quiet:true});
        }

        if (rs){
          const dist = leg?.distance?.text || '—';
          const dura = (leg?.duration_in_traffic || leg?.duration)?.text || '—';
          rs.innerText = `Ruta: ${dist} · ${dura} · Cuando: `
            + (qs('#when-later')?.checked?'después':'ahora');
        }
        _autoQuoteIfReady();
        return;
      } catch(err) {
        if (!quiet) console.warn('[Directions] fallo, fallback OSRM:', err?.status||err);
      }
    }

    // Fallback OSRM
    await drawRouteWithOSRM(a,b,stops,{quiet:true});

  } catch(err) {
    console.error('drawRoute error', err);
  }
}

// setters exportados (los usa el flujo del formulario/map click)
export function setFrom(latlng, label){
  const mp = _ctxMap(); if (!mp) return;

  if (fromMarker) fromMarker.remove();
  fromMarker = L.marker(latlng, { draggable:true, icon:IconOrigin, zIndexOffset:1000 })
    .addTo(mp).bindTooltip('Origen');

  _setInput('#fromLat', latlng[0]);
  _setInput('#fromLng', latlng[1]);

  if (label) { const el = qs('#inFrom'); if (el) el.value = label; }
  else reverseGeocode(latlng, '#inFrom');

  fromMarker.on('dragstart', ()=> mp.dragging.disable());
  fromMarker.on('dragend', (e)=>{
    mp.dragging.enable();
    const ll = e.target.getLatLng();
    _setInput('#fromLat', ll.lat);
    _setInput('#fromLng', ll.lng);
    reverseGeocode([ll.lat,ll.lng], '#inFrom');
    drawRoute({quiet:true});
    _autoQuoteIfReady();
  });

  drawRoute({quiet:true});
  _autoQuoteIfReady();
}

export function setTo(latlng, label){
  const mp = _ctxMap(); if (!mp) return;

  if (toMarker) toMarker.remove();
  toMarker = L.marker(latlng, { draggable:true, icon:IconDest, zIndexOffset:1000 })
    .addTo(mp).bindTooltip('Destino');

  _setInput('#toLat', latlng[0]);
  _setInput('#toLng', latlng[1]);

  if (label) { const el = qs('#inTo'); if (el) el.value = label; }
  else reverseGeocode(latlng, '#inTo');

  toMarker.on('dragstart', ()=> mp.dragging.disable());
  toMarker.on('dragend', (e)=>{
    mp.dragging.enable();
    const ll = e.target.getLatLng();
    _setInput('#toLat', ll.lat);
    _setInput('#toLng', ll.lng);
    reverseGeocode([ll.lat,ll.lng], '#inTo');
    drawRoute({quiet:true});
    _autoQuoteIfReady();
  });

  drawRoute({quiet:true});
  _autoQuoteIfReady();
}

export function setStop1(latlng, label){
  const mp = _ctxMap(); if (!mp) return;

  if (stop1Marker) stop1Marker.remove();
  stop1Marker = L.marker(latlng, { draggable:true, icon:IconStop, zIndexOffset:900 })
    .addTo(mp).bindTooltip('Parada 1');

  const s1Lat = qs('#stop1Lat'); if (s1Lat) s1Lat.value = latlng[0];
  const s1Lng = qs('#stop1Lng'); if (s1Lng) s1Lng.value = latlng[1];

  if (label) { const el = qs('#inStop1'); if (el) el.value = label; }
  else reverseGeocode(latlng, '#inStop1');

  stop1Marker.on('dragstart', ()=> mp.dragging.disable());
  stop1Marker.on('dragend', (e)=>{
    mp.dragging.enable();
    const ll = e.target.getLatLng();
    if (s1Lat) s1Lat.value = ll.lat;
    if (s1Lng) s1Lng.value = ll.lng;
    reverseGeocode([ll.lat,ll.lng], '#inStop1');
    drawRoute({quiet:true}); _autoQuoteIfReady();
  });

  document.getElementById('stop2Row')?.style.setProperty('display','');
  drawRoute({quiet:true}); _autoQuoteIfReady();
}

export function setStop2(latlng, label){
  const mp = _ctxMap(); if (!mp) return;

  if (!Number.isFinite(parseFloat(qs('#stop1Lat')?.value))) return;

  if (stop2Marker) stop2Marker.remove();
  stop2Marker = L.marker(latlng, { draggable:true, icon:IconStop, zIndexOffset:900 })
    .addTo(mp).bindTooltip('Parada 2');

  const s2Lat = qs('#stop2Lat'); if (s2Lat) s2Lat.value = latlng[0];
  const s2Lng = qs('#stop2Lng'); if (s2Lng) s2Lng.value = latlng[1];

  if (label) { const el = qs('#inStop2'); if (el) el.value = label; }
  else reverseGeocode(latlng, '#inStop2');

  stop2Marker.on('dragstart', ()=> mp.dragging.disable());
  stop2Marker.on('dragend', (e)=>{
    mp.dragging.enable();
    const ll = e.target.getLatLng();
    if (s2Lat) s2Lat.value = ll.lat;
    if (s2Lng) s2Lng.value = ll.lng;
    reverseGeocode([ll.lat,ll.lng], '#inStop2');
    drawRoute({quiet:true}); _autoQuoteIfReady();
  });

  drawRoute({quiet:true}); _autoQuoteIfReady();
}

// === Auto-cotización (placeholder: en tu 2da parte la completamos) ===
let _quoteTimer = null;
let __lastQuote = null;

function _hasAB() {
  const aLat = parseFloat(qs('#fromLat')?.value);
  const aLng = parseFloat(qs('#fromLng')?.value);
  const bLat = parseFloat(qs('#toLat')?.value);
  const bLng = parseFloat(qs('#toLng')?.value);
  return Number.isFinite(aLat) && Number.isFinite(aLng)
      && Number.isFinite(bLat) && Number.isFinite(bLng);
}

// ============================================================
// OSRM helper exportado (lo usan otros flujos)
// ============================================================
export async function drawRouteWithOSRM(a, b, stops = [], { quiet = false } = {}) {
  const lr = _ctxLayerRoute();
  const mp = _ctxMap();
  if (!lr || !mp) throw new Error('map/layerRoute not ready');

  const coords = [a, ...stops, b];
  const parts = coords.map(c => `${c[1]},${c[0]}`); // lng,lat
  const url = `https://router.project-osrm.org/route/v1/driving/${parts.join(';')}?overview=full&geometries=polyline`;

  const r = await fetch(url);
  if (!r.ok) throw new Error('OSRM 500');
  const j = await r.json();
  if (j.code !== 'Ok' || !j.routes?.length) throw new Error('OSRM bad');

  const route = j.routes[0];
  const poly = route.geometry;
  const latlngs = decodePolyline(poly);

  try { window.__routeLine?.remove(); } catch {}
  window.__routeLine = L.polyline(latlngs, routeStyle()).addTo(lr);

  if (!quiet) {
    const rs = qs('#routeSummary');
    if (rs) {
      const km = (route.distance/1000).toFixed(1)+' km';
      const min = Math.round(route.duration/60)+' min';
      rs.innerText = `Ruta: ${km} · ${min}`;
    }
  }

  mp.fitBounds(window.__routeLine.getBounds(), { padding:[20,20] });
  return { distance_m: route.distance|0, duration_s: route.duration|0, polyline: poly };
}
/* resources/js/pages/dispatch.map.js — PARTE 2 (corregida)
   Reemplaza tu “segunda parte” por este bloque.
   Notas clave:
   - Nada de “globals implícitos”: usamos _ctxMap() / _ctxLayerRoute() y _callbacks.
   - Se eliminan duplicados (clearOriginDest tenía doble borrado de stops).
   - Se exportan helpers que usa dispatch.js (focusRideOnMap, showDriverToPickup, clearDriverRoute, clearAllPreviews, clearTripGraphicsHard, previewCandidatesFor, drawAllPreviewLinesFor, drawPreviewLinesStagger).
   - jsonHeaders se usa con overrides correctamente (no jsonHeaders(fn)).
*/

/* ============================================================
 *  Helpers mínimos que esta parte usa
 *  (vienen de la Parte 1 en el mismo archivo)
 * ============================================================ */
function _ctxMap() { return getMap(); }


function _norm(v) { return String(v ?? '').trim().toLowerCase(); }

// Canonizador opcional (si existe en otro módulo, se respeta)
function _canon(v) {
  const raw = _norm(v);
  if (typeof window._canonStatus === 'function') return window._canonStatus(raw);
  if (typeof _canonStatus === 'function') return _canonStatus(raw);
  // fallback simple
  if (raw === 'onboard') return 'on_board';
  if (raw === 'enroute') return 'en_route';
  return raw;
}

/* ============================================================
 *  highlightRideOnMap — cierre de función (tu fragmento)
 *  (esto asume que highlightRideOnMap está definida en la Parte 1
 *   o arriba; aquí solo dejo el tramo final que pegaste, corregido
 *   para usar _ctxMap() y variables locales)
 * ============================================================ */

// ... (aquí va lo previo de highlightRideOnMap) ...

// ESTE BLOQUE ES LA “SEGUNDA PARTE” QUE PEGASTE (corregida)
function _applyHighlightBounds({ ride, from, to, stops, driverLL }) {
  const mp = _ctxMap(); if (!mp) return;

  const st = _canon(ride?.status);

  const bounds = [];
  const push = (ll) => {
    if (!ll) return;
    if (Array.isArray(ll)) bounds.push(ll);
    else if (typeof ll.lat === 'number' && typeof ll.lng === 'number') bounds.push([ll.lat, ll.lng]);
  };

  if (driverLL && (st === 'accepted' || st === 'assigned' || st === 'en_route' || st === 'arrived')) {
    push(driverLL);
    if (from) push([from.lat, from.lng]);
  } else if (driverLL && st === 'on_board') {
    push(driverLL);
    if (to) push([to.lat, to.lng]);
  } else {
    if (from) push([from.lat, from.lng]);
    (stops || []).forEach(s => {
      const lt = +s.lat, lg = +s.lng;
      if (Number.isFinite(lt) && Number.isFinite(lg)) push([lt, lg]);
    });
    if (to) push([to.lat, to.lng]);
  }

  if (!bounds.length && driverLL) push(driverLL);

  if (bounds.length === 1) {
    mp.setView(bounds[0], Math.max(mp.getZoom(), 16));
  } else if (bounds.length > 1) {
    mp.fitBounds(bounds, { padding: [40, 40] });
  }
}



/* ============================================================
 *  Limpieza de gráficos (ruta/origen/destino/stops/previews)
 * ============================================================ */
window.__originMarker = window.__originMarker || null;
window.__destMarker   = window.__destMarker   || null;
window.__routeLine    = window.__routeLine    || null;
window.__stopMarkers  = window.__stopMarkers  || [];

function clearOriginDest() {
  try {
    const lr = _ctxLayerRoute(); if (!lr) return;
    const layer = (typeof window.layerSuggested !== 'undefined' && window.layerSuggested) ? window.layerSuggested : lr;

    if (window.__originMarker) { try { layer.removeLayer(window.__originMarker); } catch {} window.__originMarker = null; }
    if (window.__destMarker)   { try { layer.removeLayer(window.__destMarker);   } catch {} window.__destMarker   = null; }

    // stops del formulario
    try { if (stop1Marker) { stop1Marker.remove(); stop1Marker = null; } } catch {}
    try { if (stop2Marker) { stop2Marker.remove(); stop2Marker = null; } } catch {}

    // stops del highlightRideOnMap
    try {
      (window.__stopMarkers || []).forEach(m => { try { m.remove(); } catch {} });
      window.__stopMarkers = [];
    } catch {}

    // routeLine global si existe
    try {
      if (window.__routeLine) { try { lr.removeLayer(window.__routeLine); } catch {} }
      window.__routeLine = null;
    } catch {}

    // por si quedó alguna polyline con className cc-route
    try {
      const toRemove = [];
      lr.eachLayer(l => {
        try {
          if (l instanceof L.Polyline) {
            const cls = String(l.options?.className || '');
            if (cls.includes('cc-route')) toRemove.push(l);
          }
        } catch {}
      });
      toRemove.forEach(l => { try { lr.removeLayer(l); } catch {} });
    } catch {}
  } catch (err) {
    console.warn('[map] clearOriginDest error', err);
  }
}

let _assignPickupMarker = null;
let _assignPreviewLine  = null;

// Limpia líneas cc-suggested / cc-preview
function clearAssignArtifacts() {
  const lr = _ctxLayerRoute(); if (!lr) return;

  try {
    const lyr = (typeof window.layerSuggested !== 'undefined' && window.layerSuggested) ? window.layerSuggested : lr;
    if (_assignPickupMarker) { try { lyr.removeLayer(_assignPickupMarker); } catch {} _assignPickupMarker = null; }
    if (_assignPreviewLine)  { try { lr.removeLayer(_assignPreviewLine);  } catch {} _assignPreviewLine  = null; }
  } catch {}

  try {
    const toRemove = [];
    lr.eachLayer(l => {
      try {
        if (l instanceof L.Polyline) {
          const cls = String(l.options?.className || '');
          if (cls.includes('cc-suggested') || cls.includes('cc-preview')) toRemove.push(l);
        }
      } catch {}
    });
    toRemove.forEach(l => { try { lr.removeLayer(l); } catch {} });
  } catch {}
}

export function clearAllPreviews() {
  // driverPins vive en otro módulo; aquí toleramos ausencia
  const pins = (typeof driverPins !== 'undefined' ? driverPins : (window.driverPins || null));
  const lr = _ctxLayerRoute(); if (!pins || !lr) return;

  pins.forEach(e => {
    if (e.previewLine) { try { lr.removeLayer(e.previewLine); } catch{} }
    e.previewLine = null;
  });
}

export function clearTripGraphicsHard() {
  try {
    clearAllPreviews();
  } catch {}
  try {
    if (typeof window.clearSuggestedLines === 'function') window.clearSuggestedLines();
  } catch {}
  try {
    if (typeof window.clearAllPreviews === 'function') window.clearAllPreviews();
  } catch {}

  clearAssignArtifacts();

  // ruta principal del formulario
  try { _ctxLayerRoute()?.clearLayers?.(); } catch {}

  // pines del formulario
  try { if (fromMarker) { fromMarker.remove(); fromMarker = null; } } catch {}
  try { if (toMarker)   { toMarker.remove();   toMarker   = null; } } catch {}

  // refs globales
  try {
    const lr = _ctxLayerRoute(); if (!lr) return;
    const lyr = (typeof window.layerSuggested !== 'undefined' && window.layerSuggested) ? window.layerSuggested : lr;

    if (window.__originMarker) { try { lyr.removeLayer(window.__originMarker); } catch {} window.__originMarker = null; }
    if (window.__destMarker)   { try { lyr.removeLayer(window.__destMarker);   } catch {} window.__destMarker   = null; }
    if (window.__routeLine)    { try { lr.removeLayer(window.__routeLine); } catch {} window.__routeLine = null; }
  } catch {}
}

/* ============================================================
 *  Cancelación — handler único, pero sin “ensuciar” estado
 * ============================================================ */
export function bindCancelRideHandlerOnce() {
  if (window.__cancelHandlerBound) return;

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-action="cancel-ride"], .btn-cancel');
    if (!btn) return;
    e.preventDefault();

    let rideId =
      btn.dataset?.rideId ||
      btn.getAttribute('data-ride-id') ||
      btn.closest('.cc-ride-card')?.dataset?.rideId ||
      btn.closest('[data-ride-id]')?.getAttribute('data-ride-id');

    rideId = Number(String(rideId ?? '').trim());
    if (!Number.isFinite(rideId) || rideId <= 0) {
      console.warn('cancel: rideId inválido en data-ride-id');
      return;
    }

    if (typeof Swal === 'undefined') {
      console.warn('Swal no está disponible');
      return;
    }

    const result = await Swal.fire({
      title: '¿Cancelar el servicio?',
      text: 'Esta acción no se puede deshacer.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, cancelar',
      cancelButtonText: 'No',
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      reverseButtons: true,
      focusCancel: true
    });
    if (!result.isConfirmed) return;

    let chosenReason = null;
    if (Array.isArray(window.cancelReasons) && window.cancelReasons.length > 0) {
      const { value: reasonId } = await Swal.fire({
        title: 'Motivo de cancelación',
        input: 'select',
        inputOptions: window.cancelReasons.reduce((acc, r) => {
          acc[r.id] = r.label; return acc;
        }, {}),
        inputPlaceholder: 'Selecciona un motivo',
        showCancelButton: true,
        confirmButtonText: 'Continuar',
        cancelButtonText: 'Volver',
      });
      if (reasonId === undefined) return;
      const item = window.cancelReasons.find(r => String(r.id) === String(reasonId));
      chosenReason = item ? item.label : null;
    }

    const prevText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Cancelando…';

    try {
      const res = await fetch(`/api/dispatch/rides/${rideId}/cancel`, {
        method: 'POST',
        headers: jsonHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ reason: chosenReason })
      });

      const json = await res.json().catch(() => ({}));
      if (!res.ok || json.ok === false) {
        await Swal.fire({
          icon: 'error',
          title: 'No se pudo cancelar',
          text: json.msg || `HTTP ${res.status}`
        });
        return;
      }

      // Limpia formulario + gráficos
      try {
        ['inFrom','inTo','pass-name','pass-phone','pass-account','ride-notes','fareAmount']
          .forEach(id => { const el = qs('#'+id); if (el) el.value = ''; });
        ['fromLat','fromLng','toLat','toLng']
          .forEach(id => { const el = qs('#'+id); if (el) el.value = ''; });

        clearTripGraphicsHard();

        const s1Lat = qs('#stop1Lat'), s1Lng = qs('#stop1Lng'), s2Lat = qs('#stop2Lat'), s2Lng = qs('#stop2Lng');
        const inS1 = qs('#inStop1'), inS2 = qs('#inStop2');
        const row1 = qs('#stop1Row'), row2 = qs('#stop2Row');
        if (s1Lat) s1Lat.value=''; if (s1Lng) s1Lng.value='';
        if (s2Lat) s2Lat.value=''; if (s2Lng) s2Lng.value='';
        if (inS1)  inS1.value='';  if (inS2)  inS2.value='';
        if (row1) row1.style.display='none';
        if (row2) row2.style.display='none';

        const rs = document.getElementById('routeSummary');
        if (rs) rs.innerText = 'Ruta: — · Zona: — · Cuando: ahora';

        if (typeof window.resetWhenNow === 'function') window.resetWhenNow();
      } catch {}

      await Swal.fire({ icon: 'success', title: 'Cancelado', timer: 900, showConfirmButton: false });

      // Quita card + badge
      const card = btn.closest('.cc-ride-card');
      if (card) card.remove();

      const badge = document.getElementById('badgeActivos');
      if (badge) {
        const n = Math.max(0, (parseInt(badge.textContent,10) || 0) - 1);
        badge.textContent = n;
      }

      // Refresca panel
      if (typeof window.renderActiveRides === 'function') {
        await window.renderActiveRides();
      } else if (typeof window.refreshDispatch === 'function') {
        await window.refreshDispatch();
      }
    } catch (err) {
      console.error('cancel error', err);
      await Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo contactar al servidor.' });
    } finally {
      btn.disabled = false;
      btn.textContent = prevText;
    }
  });

  window.__cancelHandlerBound = true;
}

/* ============================================================
 *  Estilos sugeridos y rutas sugeridas (driver -> pickup)
 * ============================================================ */
function suggestedLineStyle(){
  return isDarkMode()
    ? { color:'#4DD9FF', weight:5, opacity:.95, dashArray:'6,8' }
    : { color:'#0D6EFD', weight:4, opacity:.90, dashArray:'6,8' };
}
function suggestedLineSelectedStyle() {
  return isDarkMode()
    ? { color:'#22CC88', weight:6, opacity:.95 }
    : { color:'#16A34A', weight:5, opacity:.95 };
}

async function drawSuggestedRoute(fromLL, toLL){
  // 1) Google Directions
  if (window.google?.maps && typeof gDirService !== 'undefined' && gDirService){
    try{
      const res = await new Promise((resolve,reject)=>{
        gDirService.route({
          origin: {lat: fromLL.lat, lng: fromLL.lng},
          destination: {lat: toLL.lat, lng: toLL.lng},
          travelMode: google.maps.TravelMode.DRIVING,
          region: 'MX',
          drivingOptions: { departureTime: new Date(), trafficModel: 'bestguess' }
        }, (r,s)=> s==='OK' ? resolve(r) : reject(s));
      });

      const route = res.routes?.[0];
      const leg   = route?.legs?.[0];
      const pts   = (route?.overview_path || []).map(p => [p.lat(), p.lng()]);
      const line  = L.polyline(pts, { pane:'routePane', className:'cc-suggested', ...suggestedLineStyle() });

      line._meta = {
        distance: leg?.distance?.text || '—',
        duration: (leg?.duration_in_traffic || leg?.duration)?.text || '—'
      };
      return line;
    } catch {/* cae a OSRM */}
  }

  // 2) OSRM fallback
  const url = `https://router.project-osrm.org/route/v1/driving/${fromLL.lng},${fromLL.lat};${toLL.lng},${toLL.lat}?overview=full&geometries=geojson`;
  const r = await fetch(url);
  const j = await r.json().catch(()=>({}));
  const coords  = j?.routes?.[0]?.geometry?.coordinates || [];
  const latlngs = coords.map(c => [c[1], c[0]]);
  return L.polyline(latlngs, { pane:'routePane', className:'cc-suggested', ...suggestedLineStyle() });
}

async function ensureDriverPreviewLine(driverId, ride) {
  const pins = (typeof driverPins !== 'undefined' ? driverPins : (window.driverPins || null));
  const lr = _ctxLayerRoute(); if (!pins || !lr) return null;

  const e = pins.get(driverId);
  if (!e) return null;
  if (e.previewLine) return e.previewLine;

  const line = await drawSuggestedRoute(
    e.marker.getLatLng(),
    L.latLng(ride.origin_lat, ride.origin_lng)
  );
  line.setStyle(suggestedLineStyle());
  line.options.className = 'cc-suggested';
  e.previewLine = line.addTo(lr);
  return e.previewLine;
}

async function ensurePreviewLineForCandidate(candidate, ride) {
  const pins = (typeof driverPins !== 'undefined' ? driverPins : (window.driverPins || null));
  const lr = _ctxLayerRoute(); if (!pins || !lr) return null;

  const id = candidate.id || candidate.driver_id;
  const e  = pins.get(Number(id));

  let fromLL = null;
  if (e?.marker) {
    fromLL = e.marker.getLatLng();
  } else if (Number.isFinite(candidate.lat) && Number.isFinite(candidate.lng)) {
    fromLL = L.latLng(candidate.lat, candidate.lng);
  } else {
    return null;
  }

  const toLL = L.latLng(ride.origin_lat, ride.origin_lng);

  const line = await drawSuggestedRoute(fromLL, toLL);
  line.setStyle(suggestedLineStyle());
  line.options.className = 'cc-suggested';

  if (e) e.previewLine = line;
  line.addTo(lr);
  return line;
}

// Dibuja TODAS las líneas driver->pickup para un ride (sin abrir panel)
export async function drawAllPreviewLinesFor(ride, candidates) {
  const TOP_N = 12;
  const ordered = [...(candidates || [])]
    .sort((a,b)=> (a.distance_km ?? 9e9) - (b.distance_km ?? 9e9))
    .slice(0, TOP_N);

  for (let i=0; i<ordered.length; i++) {
    const c  = ordered[i];
    const id = c.id || c.driver_id;
    try { await ensureDriverPreviewLine(Number(id), ride); } catch {}
    await new Promise(res => setTimeout(res, 90));
  }

  const best = ordered[0];
  if (best) {
    const pins = (typeof driverPins !== 'undefined' ? driverPins : (window.driverPins || null));
    const lr = _ctxLayerRoute();
    if (pins && lr) {
      const id = Number(best.id || best.driver_id);
      const pin = pins.get(id);
      if (pin?.previewLine) {
        try { pin.previewLine.setStyle(suggestedLineSelectedStyle()); pin.previewLine.bringToFront(); } catch {}
      }
    }
  }
}

export async function drawPreviewLinesStagger(ride, candidates, topN = 12) {
  const ordered = [...(candidates||[])]
    .sort((a,b)=>(a.distance_km??9e9)-(b.distance_km??9e9))
    .slice(0, topN);

  for (let i=0; i<ordered.length; i++) {
    const c = ordered[i];
    try { await ensurePreviewLineForCandidate(c, ride); } catch {}
    await new Promise(res => setTimeout(res, 90));
  }

  const best = ordered[0];
  if (best) {
    const pins = (typeof driverPins !== 'undefined' ? driverPins : (window.driverPins || null));
    if (pins) {
      const id = Number(best.id || best.driver_id);
      const pin = pins.get(id);
      if (pin?.previewLine) {
        try { pin.previewLine.setStyle(suggestedLineSelectedStyle()); pin.previewLine.bringToFront(); } catch {}
      }
    }
  }
}

/* ============================================================
 *  showDriverToPickup / clearDriverRoute (EXPORTS)
 * ============================================================ */
const assignmentLines = new Map(); // driver_id -> polyline

export async function showDriverToPickup(driver_id, origin_lat=null, origin_lng=null){
  const pins = (typeof driverPins !== 'undefined' ? driverPins : (window.driverPins || null));
  const lr = _ctxLayerRoute();
  const mp = _ctxMap();
  if (!pins || !lr || !mp) return;

  const e = pins.get(Number(driver_id)); if (!e) return;

  let lat = Number(origin_lat), lng = Number(origin_lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    const a = Number(qs('#fromLat')?.value);
    const b = Number(qs('#fromLng')?.value);
    if (Number.isFinite(a) && Number.isFinite(b)) { lat=a; lng=b; }
    else { const c = mp.getCenter(); lat=c.lat; lng=c.lng; }
  }

  const prev = assignmentLines.get(Number(driver_id));
  if (prev) { try { lr.removeLayer(prev); } catch{} }

  const from = e.marker.getLatLng();
  const to   = L.latLng(lat, lng);

  try{
    const line = await drawSuggestedRoute(from, to);
    line.addTo(lr);
    try { line.bringToFront(); } catch {}
    assignmentLines.set(Number(driver_id), line);
    e.marker.setZIndexOffset(900);
  } catch(err) {
    console.warn('No se pudo trazar ruta sugerida', err);
  }
}

export function clearDriverRoute(driver_id){
  const lr = _ctxLayerRoute(); if (!lr) return;
  const line = assignmentLines.get(Number(driver_id));
  if (line) { try { lr.removeLayer(line); } catch {} }
  assignmentLines.delete(Number(driver_id));
}

/* ============================================================
 *  Bubble (autodespacho) — opcional, pero estable
 * ============================================================ */
let _bubbleEl = null, _bubbleTimer = null;

function ensureBubble(){
  if (_bubbleEl) return _bubbleEl;
  _bubbleEl = document.createElement('div');
  _bubbleEl.className = 'cc-bubble';
  _bubbleEl.style.cssText = `
    position:absolute; right:16px; bottom:16px; z-index:10000;
    max-width:280px; background:rgba(0,0,0,.8); color:#fff;
    padding:10px 12px; border-radius:12px; font-size:13px; box-shadow:0 6px 22px rgba(0,0,0,.25)
  `;
  _bubbleEl.textContent = '...';
  document.body.appendChild(_bubbleEl);
  return _bubbleEl;
}
export function showBubble(text){ const el = ensureBubble(); el.style.display='block'; el.textContent = text||'...'; }
export function updateBubble(text){ if (_bubbleEl) _bubbleEl.textContent = text||'...'; }
export function hideBubble(){ if (_bubbleEl){ _bubbleEl.style.display='none'; } }

export function startCountdown(totalSec, onTick, onDone){
  clearInterval(_bubbleTimer);
  let s = Math.max(0, Math.floor(totalSec||0));
  (onTick||(()=>{}))(s);
  _bubbleTimer = setInterval(()=>{
    s -= 1;
    if (s <= 0) {
      clearInterval(_bubbleTimer);
      (onTick||(()=>{}))(0);
      (onDone||(()=>{}))();
    } else {
      (onTick||(()=>{}))(s);
    }
  }, 1000);
}

/* ============================================================
 *  Previsualizar candidatos alrededor del origen del ride
 * ============================================================ */
export async function previewCandidatesFor(ride, limit = 8, radiusKm = 5){
  try {
    const url = `/api/dispatch/nearby-drivers?lat=${ride.origin_lat}&lng=${ride.origin_lng}&km=${radiusKm}`;
    const r = await fetch(url, { headers: jsonHeaders() });
    const list = r.ok ? await r.json() : [];

    const ordered = (Array.isArray(list) ? list : [])
      .sort((a,b)=> (a.distance_km??9e9) - (b.distance_km??9e9))
      .slice(0, Math.max(1, limit|0));

    for (const c of ordered) {
      try { await ensurePreviewLineForCandidate(c, ride); } catch {}
      await new Promise(res=> setTimeout(res, 90));
    }
  } catch (e) {
    console.warn('[previewCandidatesFor] error', e);
  }
}

/* ============================================================
 *  focusRideOnMap (EXPORT) + hydrate stops (stub seguro)
 * ============================================================ */
async function hydrateRideStops(ride){
  // Si ya trae stops en array, listo
  if (ride && Array.isArray(ride.stops)) return ride;

  // si trae stops_json, parsear
  if (ride?.stops_json) {
    try {
      const arr = JSON.parse(ride.stops_json);
      if (Array.isArray(arr)) return { ...ride, stops: arr };
    } catch {}
  }
  return ride;
}

function debugBrief(ride){
  return {
    id: ride?.id,
    st: ride?.status,
    o: [ride?.origin_lat, ride?.origin_lng],
    d: [ride?.dest_lat, ride?.dest_lng],
    stops: Array.isArray(ride?.stops) ? ride.stops.length : 0
  };
}

export async function focusRideOnMap(rideId){
  // cache
  const cached = window._ridesIndex?.get?.(rideId);
  if (cached) {
    const rideWithStops = await hydrateRideStops(cached);
    return highlightRideOnMap(rideWithStops);
  }

  const r = await fetch(`/api/rides/${rideId}`, { headers: jsonHeaders() });
  if (!r.ok) {
    console.error('GET /api/rides/{id} →', r.status, await r.text().catch(()=>'')); 
    alert('No se pudo cargar el viaje.');
    return;
  }

  const ride = await r.json();
  const rideWithStops = await hydrateRideStops(ride);

  if (window.__DISPATCH_DEBUG__) {
    console.log('[focusRideOnMap] GET /api/rides/'+rideId, debugBrief(rideWithStops), rideWithStops);
  }
  return highlightRideOnMap(rideWithStops);
}

/* ============================================================
 *  Asignación/movimiento — utilidades (se quedan internas)
 * ============================================================ */
function smoothMove(marker, toLL, ms=350){
  const from = marker.getLatLng();
  const t0 = performance.now();
  function step(t){
    const k = Math.min(1, (t - t0)/ms);
    const lat = from.lat + (toLL.lat - from.lat)*k;
    const lng = from.lng + (toLL.lng - from.lng)*k;
    marker.setLatLng([lat,lng]);
    if (k < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

function _clearAssignPreview() {
  const lr = _ctxLayerRoute(); if (!lr) return;
  if (_assignPreviewLine) { try { lr.removeLayer(_assignPreviewLine); } catch{} }
  _assignPreviewLine = null;
}

async function _previewCandidateToPickup(driverId, ride) {
  const pins = (typeof driverPins !== 'undefined' ? driverPins : (window.driverPins || null));
  const lr = _ctxLayerRoute(); if (!pins || !lr) return;

  _clearAssignPreview();
  const e = pins.get(Number(driverId));
  if (!e) return;

  const from = e.marker.getLatLng();
  const to   = L.latLng(ride.origin_lat, ride.origin_lng);

  try {
    const line = await drawSuggestedRoute(from, to);
    line.setStyle(suggestedLineStyle()).addTo(lr);
    _assignPreviewLine = line;
    try { _ctxMap()?.fitBounds(line.getBounds().pad(0.15)); } catch {}
  } catch(err) {
    console.warn('preview route error', err);
  }
}

function bearingBetween(a,b){
  const dLon = (b.lng-a.lng)*Math.PI/180;
  const y = Math.sin(dLon)*Math.cos(b.lat*Math.PI/180);
  const x = Math.cos(a.lat*Math.PI/180)*Math.sin(b.lat*Math.PI/180) -
            Math.sin(a.lat*Math.PI/180)*Math.cos(b.lat*Math.PI/180)*Math.cos(dLon);
  return (Math.atan2(y,x)*180/Math.PI+360)%360;
}

/* ============================================================
 *  deriveRideUi y helpers (tu bloque) — sin cambios lógicos,
 *  solo usa _canon() y _norm() para evitar referencias rotas.
 * ============================================================ */
function isScheduledStatus(ride){
  const st = _norm(ride?.status);
  const hasSchedField = !!(ride?.scheduled_for || ride?.scheduledFor ||
                           ride?.scheduled_at  || ride?.scheduledAt);
  return st === 'scheduled' || hasSchedField;
}

function shouldHideRideCard(ride) {
  const st = _norm(ride?.status);
  return st === 'completed' || st === 'canceled';
}

function deriveRideChannel(ride) {
  const raw = String(
    ride.requested_channel ||
    ride.channel ||
    ride.request_source ||
    ''
  ).toLowerCase().trim();

  if (!raw) return { code: 'panel', label: 'Panel' };

  if (['passenger_app', 'passenger', 'app', 'app_pasajero'].includes(raw)) return { code: 'passenger', label: 'App pasajero' };
  if (['driver_app', 'driver', 'app_conductor'].includes(raw)) return { code: 'driver', label: 'App conductor' };
  if (['central', 'dispatcher', 'panel', 'web'].includes(raw)) return { code: 'panel', label: 'Central' };
  if (['phone', 'telefono', 'callcenter', 'call_center'].includes(raw)) return { code: 'phone', label: 'Teléfono' };
  if (['corp', 'corporate', 'empresa', 'business'].includes(raw)) return { code: 'corp', label: 'Corporativo' };

  return { code: raw, label: raw.charAt(0).toUpperCase() + raw.slice(1) };
}

function summarizeOffers(ride) {
  const offers = Array.isArray(ride.offers) ? ride.offers : [];
  const anyAccepted = offers.some(o => o.status === 'accepted');
  const anyOffered  = offers.some(o => o.status === 'offered');
  const rejectedBy  = offers.filter(o => o.status === 'rejected')
                            .map(o => o.driver_name || `#${o.driver_id}`);
  return { offers, anyAccepted, anyOffered, rejectedBy };
}

export function deriveRideUi(ride) {
  const rawStatus = _norm(ride?.status);
  const status = _canon(rawStatus) || 'unknown';

  const ch = deriveRideChannel(ride);
  const isPassengerApp = ch.code === 'passenger';

  let label = status;
  let colorClass = 'secondary';
  let showAssign = false;
  let showReoffer = false;
  let showRelease = false;
  let showCancel = false;

  switch (status) {
    case 'requested':
    case 'pending':
    case 'queued':
    case 'new':
      label = 'Pendiente';
      colorClass = 'warning';
      showAssign = true;
      showReoffer = true;
      showCancel = true;
      break;

    case 'offered':
    case 'offering':
      label = 'Ofertado';
      colorClass = 'info';
      showAssign = true;
      showReoffer = true;
      showCancel = true;
      break;

    case 'accepted':
    case 'assigned':
      label = 'Asignado';
      colorClass = 'primary';
      showRelease = true;
      showCancel = true;
      break;

    case 'en_route':
      label = 'En camino';
      colorClass = 'primary';
      showRelease = true;
      showCancel = true;
      break;

    case 'arrived':
      label = 'Esperando';
      colorClass = 'warning';
      showRelease = true;
      showCancel = true;
      break;

    case 'on_board':
      label = 'En viaje';
      colorClass = 'success';
      showCancel = true;
      break;

    case 'finished':
      label = 'Finalizado';
      colorClass = 'success';
      break;

    case 'canceled':
      label = 'Cancelado';
      colorClass = 'secondary';
      break;

    case 'no_driver':
      label = 'Sin conductor';
      colorClass = 'danger';
      showAssign = true;
      showReoffer = true;
      break;

    default:
      label = status || 'desconocido';
      colorClass = 'secondary';
  }

  if (isPassengerApp) {
    showAssign = false;
    showReoffer = false;
  }

  const badge = `<span class="badge bg-${colorClass} badge-pill">${label}</span>`;

  return {
    status,
    label,
    badge,
    showAssign,
    showReoffer,
    showRelease,
    showCancel,
    isPassengerApp,
    channel: ch
  };
}
