/* resources/js/pages/dispatch.js */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/* =========================
 *  CONFIG
 * ========================= */
const CENTER_DEFAULT = [19.1738, -96.1342];
const DEFAULT_ZOOM   = 14;
const OSM = {
  url:  'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
  attr: '&copy; OpenStreetMap contributors'
};
const TENANT_ICONS = (window.ccTenant && window.ccTenant.map_icons) || {
  origin:  '/images/origen.png',
  dest:    '/images/destino.png',
  stand:   '/images/marker-parqueo5.png'
};

/* =========================
 *  HELPERs
 * ========================= */
const qs  = (sel) => document.querySelector(sel);
const fmt = (n) => Number(n).toFixed(6);
const isDarkMode = () => document.documentElement.getAttribute('data-theme') === 'dark';

function decodePolyline(str){
  let index=0,lat=0,lng=0,coords=[];
  while(index<str.length){
    let b,shift=0,result=0;
    do{ b=str.charCodeAt(index++)-63; result|=(b&0x1f)<<shift; shift+=5; }while(b>=0x20);
    const dlat=((result&1)?~(result>>1):(result>>1)); lat+=dlat;
    shift=0;result=0;
    do{ b=str.charCodeAt(index++)-63; result|=(b&0x1f)<<shift; shift+=5; }while(b>=0x20);
    const dlng=((result&1)?~(result>>1):(result>>1)); lng+=dlng;
    coords.push([lat*1e-5, lng*1e-5]);
  }
  return coords;
}


// ADD: distancia simple (km)
function haversineKm(a, b){
  const R=6371, dLat=(b.lat-a.lat)*Math.PI/180, dLng=(b.lng-a.lng)*Math.PI/180;
  const s1=Math.sin(dLat/2), s2=Math.sin(dLng/2);
  const c=Math.cos(a.lat*Math.PI/180)*Math.cos(b.lat*Math.PI/180);
  return 2*R*Math.asin(Math.sqrt(s1*s1 + c*s2*s2));
}

// ADD: ETA entre 2 puntos (segundos) - Google primero, OSRM fallback
async function etaSeconds(fromLL, toLL){
  // Google
  if (window.google?.maps?.DirectionsService) {
    try{
      const dir = new google.maps.DirectionsService();
      const res = await new Promise((resolve,reject)=>{
        dir.route({
          origin: fromLL, destination: toLL,
          travelMode: google.maps.TravelMode.DRIVING
        }, (r,s)=> s==='OK'?resolve(r):reject(s));
      });
      const leg = res.routes?.[0]?.legs?.[0];
      if (leg?.duration?.value) return Number(leg.duration.value); // segundos
    }catch{}
  }
  // OSRM
  try{
    const u = `https://router.project-osrm.org/route/v1/driving/${fromLL.lng},${fromLL.lat};${toLL.lng},${toLL.lat}?overview=false&alternatives=false&steps=false`;
    const r = await fetch(u); const j = await r.json();
    const sec = j?.routes?.[0]?.duration; // segundos
    if (Number.isFinite(sec)) return sec;
  }catch{}
  return null;
}

function _distKm(aLat, aLng, bLat, bLng){
  const toRad = d => d * Math.PI / 180;
  const R = 6371;
  const dLat = toRad(bLat - aLat);
  const dLng = toRad(bLng - aLng);
  const s1 = Math.sin(dLat/2), s2 = Math.sin(dLng/2);
  const aa = s1*s1 + Math.cos(toRad(aLat)) * Math.cos(toRad(bLat)) * s2*s2;
  return 2 * R * Math.asin(Math.sqrt(aa));
}



/* =========================
 *  GOOGLE LOADER (async)
 * ========================= */
let gmapsReady = null;
function haveFullGoogle(){
  return !!(window.google?.maps && window.google.maps.places && window.google.maps.geometry);
}
function loadGoogleMaps(){
  if (gmapsReady) return gmapsReady;
  gmapsReady = new Promise((resolve, reject) => {
    if (haveFullGoogle()) return resolve(window.google);
    const key =
      document.querySelector('meta[name="google-maps-key"]')?.content ||
      (window.ccGoogleMapsKey || '');
    const cbName = '__gmaps_cb_' + Math.random().toString(36).slice(2);
    window[cbName] = () => {
      if (haveFullGoogle()) resolve(window.google);
      else reject(new Error('Google loaded without Places/Geometry'));
      delete window[cbName];
    };
    const libs = 'places,geometry';
    const s = document.createElement('script');
    s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&libraries=${libs}&callback=${cbName}`;
    s.async = true; s.defer = true; s.onerror = (e)=>reject(e);
    document.head.appendChild(s);
  });
  return gmapsReady;
}

/* =========================
 *  MÓDULO PRINCIPAL
 * ========================= */
(() => {

let map;
let layerSectores, layerStands, layerRoute, layerDrivers,layerSuggested;
let fromMarker=null, toMarker=null;
let gDirService=null, gGeocoder=null, acFrom=null, acTo=null;

const IconOrigin = L.icon({ iconUrl:TENANT_ICONS.origin, iconSize:[30,30], iconAnchor:[15,30], popupAnchor:[0,-26] });
const IconDest   = L.icon({ iconUrl:TENANT_ICONS.dest,   iconSize:[30,30], iconAnchor:[15,30], popupAnchor:[0,-26] });
const IconStand  = L.icon({ iconUrl:TENANT_ICONS.stand,  iconSize:[28,28], iconAnchor:[14,28], popupAnchor:[0,-24] });

const routeStyle = () => (isDarkMode()
  ? { color:'#943DD4', weight:5, opacity:.95 }
  : { color:'#0717F0', weight:4, opacity:.95 }
);
const sectorStyle = () => (isDarkMode()
  ? { color:'#FF6B6B', fillColor:'#FF6B6B', fillOpacity:.12, weight:2 }
  : { color:'#2A9DF4', fillColor:'#2A9DF4', fillOpacity:.18, weight:2 }
);
const rideMarkers  = new Map(); // ride_id -> layerGroup para resaltar

function setInput(sel, val){ const el = qs(sel); if (el) el.value = val; }
function getAB(){
  const a=[parseFloat(qs('#fromLat')?.value), parseFloat(qs('#fromLng')?.value)];
  const b=[parseFloat(qs('#toLat')?.value),   parseFloat(qs('#toLng')?.value)];
  return { a,b, hasA:Number.isFinite(a[0])&&Number.isFinite(a[1]), hasB:Number.isFinite(b[0])&&Number.isFinite(b[1]) };
}
function pointsFromGoogleRoute(route){
  // 1) Google: overview_path (Array<LatLng>) ya decodificado
  if (route?.overview_path?.length) {
    return route.overview_path.map(p => [p.lat(), p.lng()]);
  }
  // 2) Google: overview_polyline.points (string) -> nuestro decoder
  if (route?.overview_polyline?.points) {
    return decodePolyline(route.overview_polyline.points);
  }
  return [];
}

function clearQuoteUi() {
  const fa = qs('#fareAmount'); if (fa) fa.value = '';
  const rs = document.getElementById('routeSummary');
  if (rs) rs.innerText = `Ruta: — · Zona: — · Cuando: ${qs('#when-later')?.checked?'después':'ahora'}`;
}


function reverseGeocode(latlng, inputSel){
  if(!gGeocoder) return;
  gGeocoder.geocode({ location:{ lat:latlng[0], lng:latlng[1] } }, (res, status)=>{
    if(status==='OK' && res?.[0]) qs(inputSel).value = res[0].formatted_address;
  });
}
async function drawRoute({ quiet=false } = {}){
  try{
    layerRoute.clearLayers();
    const { a,b,hasA,hasB } = getAB();
    const rs = document.getElementById('routeSummary');

   if (!hasA || !hasB) {
  clearQuoteUi(); // ← limpia tarifa y resumen
  if (rs) rs.innerText = 'Ruta: — · Zona: — · Cuando: ' + (qs('#when-later')?.checked?'después':'ahora');
  return;
}


    // Si hay Directions, úsalo con tráfico actual
    if (gDirService && window.google?.maps){
      try{
        const res = await new Promise((resolve,reject)=>{
          gDirService.route({
            origin: {lat:a[0], lng:a[1]},
            destination: {lat:b[0], lng:b[1]},
            travelMode: google.maps.TravelMode.DRIVING,
            region: 'MX',
            provideRouteAlternatives: false,
            drivingOptions: {                       // ← tráfico actual
              departureTime: new Date(),
              trafficModel: 'bestguess'
            }
          }, (r,s)=> s==='OK' ? resolve(r) : reject({status:s, r}));
        });

        const route = res.routes?.[0];
        const leg   = route?.legs?.[0];
        const pts   = pointsFromGoogleRoute(route);

        if (pts.length){
          const poly = L.polyline(pts, { pane:'routePane', className:'cc-route', ...routeStyle() });
          poly.addTo(layerRoute);
          map.fitBounds(poly.getBounds().pad(0.15), {padding:[40,40]});
        } else {
          if(!quiet) console.debug('[ROUTE] Directions OK sin polyline → OSRM');
          autoQuoteIfReady();
          await drawRouteWithOSRM(a,b,{quiet:true});
        }

        if (rs){
          const dist = leg?.distance?.text || '—';
          const dura = (leg?.duration_in_traffic || leg?.duration)?.text || '—';
          rs.innerText = `Ruta: ${dist} · ${dura} · Cuando: ${qs('#when-later')?.checked?'después':'ahora'}`;
        }
        autoQuoteIfReady();
        return; // listo
      }catch(err){
        if(!quiet) console.warn('[Directions] fallo, fallback OSRM:', err?.status||err);
      }
    }

    // Fallback OSRM si no hay Google o falló
    await drawRouteWithOSRM(a,b,{quiet:true});

  }catch(err){
    console.error('drawRoute error', err);
  }
}
function setFrom(latlng, label){
  if (fromMarker) fromMarker.remove();
  fromMarker = L.marker(latlng, { draggable:true, icon:IconOrigin, zIndexOffset:1000 })
    .addTo(map).bindTooltip('Origen');
  setInput('#fromLat', latlng[0]); setInput('#fromLng', latlng[1]);
  if (label) qs('#inFrom').value = label; else reverseGeocode(latlng, '#inFrom');
  fromMarker.on('dragstart', ()=> map.dragging.disable());
  fromMarker.on('dragend', (e)=>{
    map.dragging.enable();
    const ll = e.target.getLatLng();
    setInput('#fromLat', ll.lat); setInput('#fromLng', ll.lng);
    reverseGeocode([ll.lat,ll.lng], '#inFrom');
    drawRoute({quiet:true});
    autoQuoteIfReady();
  });
  drawRoute({quiet:true});
  autoQuoteIfReady();
}
function setTo(latlng, label){
  if (toMarker) toMarker.remove();
  toMarker = L.marker(latlng, { draggable:true, icon:IconDest, zIndexOffset:1000 })
    .addTo(map).bindTooltip('Destino');
  setInput('#toLat', latlng[0]); setInput('#toLng', latlng[1]);
  if (label) qs('#inTo').value = label; else reverseGeocode(latlng, '#inTo');
  toMarker.on('dragstart', ()=> map.dragging.disable());
  toMarker.on('dragend', (e)=>{
    map.dragging.enable();
    const ll = e.target.getLatLng();
    setInput('#toLat', ll.lat); setInput('#toLng', ll.lng);
    reverseGeocode([ll.lat,ll.lng], '#inTo');
    drawRoute({quiet:true});
    autoQuoteIfReady();
  });
  drawRoute({quiet:true});
  autoQuoteIfReady();
}

// === Auto-cotización en cuanto hay ORIGEN y DESTINO ===

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

async function _doAutoQuote() {
  if (!_hasAB()) { return; }
  const aLat = parseFloat(qs('#fromLat').value);
  const aLng = parseFloat(qs('#fromLng').value);
  const bLat = parseFloat(qs('#toLat').value);
  const bLng = parseFloat(qs('#toLng').value);

  try {
    const r = await fetch('/api/dispatch/quote', {
      method: 'POST',
      headers: {
        'Content-Type':'application/json',
        'Accept':'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      body: JSON.stringify({
        origin: {lat:aLat, lng:aLng},
        destination: {lat:bLat, lng:bLng},
        round_to_step: 1.00  // pesos enteros
      })
    });
    const j = await r.json();
    if (!r.ok || j.ok===false) throw new Error(j?.msg || ('HTTP '+r.status));

  __lastQuote = j; // 👈 guarda {amount, distance_m, duration_s}

  const fa = qs('#fareAmount');
  if (fa) fa.value = j.amount;

    // Actualiza el resumen bajo los inputs
    const rs = document.getElementById('routeSummary');
    if (rs) {
      const km  = (j.distance_m/1000).toFixed(1)+' km';
      const min = Math.round(j.duration_s/60)+' min';
      rs.innerText = `Ruta: ${km} · ${min} · Tarifa: $${j.amount}`;
    }
  } catch (e) {
    // Silencioso: no molestamos al operador; deja la ruta y la UI
    console.warn('[quote] fallo', e);
  }
}

function autoQuoteIfReady() {
  clearTimeout(_quoteTimer);
  // debounce para no saturar al mover pines/arrastrar
  _quoteTimer = setTimeout(_doAutoQuote, 450);
}



//*-----------termina quote 



async function drawRouteWithOSRM(a,b,{quiet=false} = {}){
  try{
    const url = `https://router.project-osrm.org/route/v1/driving/${a[1]},${a[0]};${b[1]},${b[0]}?overview=full&geometries=geojson`;
    const r = await fetch(url);
    if(!r.ok) throw new Error('OSRM HTTP '+r.status);
    const j = await r.json();
    const coords = j?.routes?.[0]?.geometry?.coordinates || [];
    if(!coords.length){ if(!quiet) console.warn('[OSRM] sin geometría'); return; }

    const latlngs = coords.map(c => [c[1], c[0]]);
    const poly = L.polyline(latlngs, { pane:'routePane', className:'cc-route', ...routeStyle() });
    poly.addTo(layerRoute);
    map.fitBounds(poly.getBounds().pad(0.15), {padding:[40,40]});

    const rs = document.getElementById('routeSummary');
    const dist = j.routes[0].distance ? (j.routes[0].distance/1000).toFixed(1)+' km' : '—';
    const dura = j.routes[0].duration ? Math.round(j.routes[0].duration/60)+' min' : '—';
    if (rs) rs.innerText = `Ruta: ${dist} · ${dura} · Cuando: ${qs('#when-later')?.checked?'después':'ahora'}`;
     autoQuoteIfReady();
  }catch(e){
    if(!quiet) console.warn('OSRM error', e);
  }
}
async function refreshDispatch(){
  try{
    const [a,b] = await Promise.all([
      fetch('/api/dispatch/active',{headers:{Accept:'application/json'}}),
      fetch('/api/dispatch/drivers',{headers:{Accept:'application/json'}}),
    ]);

    // Manejo robusto de errores (500, HTML, etc.)
    const data    = a.ok ? await a.json() : (console.error('[active]', await a.text()), {});
    const drivers = b.ok ? await b.json() : (console.error('[drivers]', await b.text()), []);

    renderQueues(Array.isArray(data.queues) ? data.queues : []);
    renderActiveRides(Array.isArray(data.rides) ? data.rides : []);
    renderDrivers(Array.isArray(drivers) ? drivers : []);
    await updateSuggestedRoutes(Array.isArray(data.rides) ? data.rides : []);
  }catch(e){
    console.warn('refreshDispatch error', e);
  }
}

let pollTimer=null;
function startPolling(){
  clearInterval(pollTimer);
  pollTimer = setInterval(refreshDispatch, 4000);
  refreshDispatch();
}






/* ---------- Panel derecho: drivers/colas/rides ---------- */





function rideColorByStatus(status){
  const s = String(status||'').toLowerCase();
  switch(s){
    case 'available': 
    case 'idle':       return '#1db954'; // verde
    case 'requested':  return '#0d6efd';
    case 'offered':    return '#6f42c1';
    case 'accepted':   return '#6610f2';
    case 'en_route':   return '#0dcaf0';
    case 'arrived':    return '#fd7e14';
    case 'on_board':   return '#e03131'; // rojo
    case 'finished':   return '#20c997';
    case 'canceled':   return '#6c757d';
    default:           return '#1db954';
  }
}



// === Iconos por tipo/estado (debes tener estos PNG en /public/images/vehicles) ===
const CAR_SPRITES = {
  sedan: {
    free:     '/images/vehicles/sedan-free.png',
    offered:  '/images/vehicles/sedan.png', // puedes renombrar a sedan-offered.png si prefieres
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


// Mapea TODOS los estados posibles a los sprites existentes
function visualState(d) {
  const r  = String(d.ride_status || '').toLowerCase();    // offered, accepted, en_route, arrived, on_board
  const ds = String(d.driver_status || '').toLowerCase();  // offline, idle, busy
  const shiftOpen = d.shift_open === 1 || d.shift_open === true;

  // Sin turno abierto o driver offline -> gris
  if (!shiftOpen || ds === 'offline') return 'offline';

  // Prioridad por ride
  if (r === 'on_board') return 'on_board';
  if (r === 'arrived')  return 'arrived';
  if (r === 'en_route') return 'en_route';
  if (r === 'accepted') return 'accepted';
  if (r === 'offered')  return 'offered';

  // Sin ride: estado del driver
  if (ds === 'busy') return 'busy';
  return 'free';
}



function statusLabel(rideStatus, driverStatus){
  const x = String(rideStatus || driverStatus || '').toLowerCase();
  switch (x) {
    case 'idle': case 'available': return 'Libre';
    case 'requested': return 'Pedido';
    case 'offered':   return 'Ofertado';
    case 'accepted': case 'assigned': return 'Asignado';
    case 'en_route':  return 'En ruta';
    case 'arrived':   return 'Llegó';
    case 'on_board': case 'onboard': return 'A bordo';
    case 'busy':      return 'Ocupado';
    case 'offline':   return 'Fuera';
    default:          return 'Libre';
  }
}

function fmtAgo(iso){
  if(!iso) return '—';
  const dt = new Date(iso);
  const diff = (Date.now() - dt.getTime())/1000;
  if (diff < 90) return 'hace 1 min';
  if (diff < 3600) return `hace ${Math.round(diff/60)} min`;
  if (diff < 86400) return `hace ${Math.round(diff/3600)} h`;
  return dt.toLocaleString();
}

/* ====== Helpers de icono (ÚNICOS) ====== */
// Tamaño base (en px) que coincide con el PNG
const CAR_W = 48, CAR_H = 40;

// Devuelve la URL del sprite por tipo/estado
function iconUrl(vehicle_type='sedan', vstate='free'){
  const t = (vehicle_type || 'sedan').toLowerCase();
  return CAR_SPRITES[t]?.[vstate] || CAR_SPRITES.sedan[vstate] || CAR_SPRITES.sedan.free;
}

// factor de escala por zoom (devuelve un número, no un par)
function scaleForZoom(z){
  if (z >= 18) return 1.35;
  if (z >= 16) return 1.20;
  if (z >= 14) return 1.00;
  return 0.85;
}

// Crea un DivIcon con contenedor fijo y IMG interna escalable/rotable
function makeCarIcon(type, state){
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

// Actualiza solo la rotación (CSS var) - no recrea el icono
function setMarkerBearing(marker, bearingDeg){
  const el = marker.getElement();
  if (!el) return;
  const box = el.querySelector('.cc-car-box');
  if (!box) return;
  const b = ((Number(bearingDeg)||0) % 360 + 360) % 360;
  box.style.setProperty('--car-rot', `${b}deg`);
}

// Actualiza solo la escala (CSS var) - no recrea el icono
function setMarkerScale(marker, scale){
  const el = marker.getElement();
  if (!el) return;
  el.querySelector('.cc-car-box')?.style.setProperty('--car-scale', String(scale));
}

// CSS: rotación y escala por variables (suave, sin “palpitar”)
(() => {
  const style = document.createElement('style');
  style.textContent = `
    .cc-car-img{
      position:absolute; left:0; top:0;
      transform-origin:50% 50%;
      transform: rotate(var(--car-rot, 0deg)) scale(var(--car-scale, 1));
      image-rendering: -webkit-optimize-contrast;
      transition: opacity 160ms linear, transform 120ms linear;
    }
    .cc-active  { opacity: 1; }
    .cc-inactive{ opacity: 0; }
  `;
  document.head.appendChild(style);
})();


// Preload opcional (evita blur al primer swap)
(function preloadCarIcons(){
  const imgs = [];
  Object.values(CAR_SPRITES).forEach(states=>{
    Object.values(states).forEach(url=>{
      const im = new Image(); im.src = url; imgs.push(im);
    });
  });
})();


function setCarSprite(marker, nextSrc){
  const el = marker.getElement();
  if (!el) return;
  const a = el.querySelector('.cc-car-img.cc-a');
  const b = el.querySelector('.cc-car-img.cc-b');
  if (!a || !b) return;

  // la inactiva es la que no tiene .cc-active
  const active   = el.querySelector('.cc-car-img.cc-active') || a;
  const inactive = active === a ? b : a;

  // si ya muestra ese src, no hagas nada
  if (active.getAttribute('src') === nextSrc) return;

  // precargar, luego crossfade
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


//---------------- termina driver  inicia ruta  ----------------------------

function highlightAssignment({driver_id, origin_lat, origin_lng}) {
  const e = driverPins.get(driver_id); if (!e) return;
  const from = e.marker.getLatLng();
  const to   = L.latLng(origin_lat, origin_lng);
  if (e.assignmentLine) { layerRoute.removeLayer(e.assignmentLine); }
  e.assignmentLine = L.polyline([from, to], {color:'#0dcaf0', weight:4, opacity:.9})
    .addTo(layerRoute);
  e.marker.setZIndexOffset(900);
}
function renderQueues(queues){
  const el = document.getElementById('panel-queue'); if(!el) return;
  el.innerHTML = '';
  queues.forEach(q=>{
    const row = document.createElement('div');
    row.className = 'd-flex justify-content-between align-items-center py-1 border-bottom';
    row.innerHTML = `
      <div class="me-2">
        <div><b>${q.nombre}</b></div>
        <div class="text-muted small">${Number(q.latitud).toFixed(5)}, ${Number(q.longitud).toFixed(5)}</div>
      </div>
      <span class="badge bg-secondary">${q.queue_count||0}</span>
    `;
    row.addEventListener('click',()=> map.panTo([q.latitud,q.longitud]));
    el.appendChild(row);
  });
  const b = document.getElementById('badgeColas'); if (b) b.innerText = queues.length;
}
async function highlightRideOnMap(r){
  // limpia anteriores
  rideMarkers.forEach(g=>{ try{ g.remove(); }catch{} });
  rideMarkers.clear();

  const group = L.layerGroup().addTo(map);
  const bounds = L.latLngBounds([]);

  // marcadores
  if (Number.isFinite(r.origin_lat) && Number.isFinite(r.origin_lng)) {
    const m = L.marker([r.origin_lat, r.origin_lng], {icon: IconOrigin}).addTo(group);
    bounds.extend(m.getLatLng());
  }
  if (Number.isFinite(r.dest_lat) && Number.isFinite(r.dest_lng)) {
    const m = L.marker([r.dest_lat, r.dest_lng], {icon: IconDest}).addTo(group);
    bounds.extend(m.getLatLng());
  }

  // 1) Ruta pasajero: ORIGEN -> DESTINO (si hay ambos)
  if (Number.isFinite(r.origin_lat) && Number.isFinite(r.origin_lng) &&
      Number.isFinite(r.dest_lat)   && Number.isFinite(r.dest_lng)) {
    try {
      const trip = await drawSuggestedRoute(
        L.latLng(r.origin_lat, r.origin_lng),
        L.latLng(r.dest_lat,   r.dest_lng)
      );
      // línea sólida (usa colores por tema)
      trip.setStyle(routeStyle());
      // marca clase para que cambie con theme switch
      trip.options.className = 'cc-route';
      trip.addTo(group);
       autoQuoteIfReady();
      try { bounds.extend(trip.getBounds()); } catch {}
    } catch (e) { console.warn('trip suggested route error', e); }
  }

  // 2) Si hay driver asignado: DRIVER -> ORIGEN (punteada)
  if (r.driver_id) {
    const e = driverPins.get(r.driver_id);
    if (e) {
      try {
        const pick = await drawSuggestedRoute(
          e.marker.getLatLng(),
          L.latLng(r.origin_lat, r.origin_lng)
        );
        pick.setStyle(suggestedLineStyle());    // punteada y color por tema
        pick.options.className = 'cc-suggested';
        pick.addTo(group);
        try { bounds.extend(pick.getBounds()); } catch {}
      } catch (e) { console.warn('pickup suggested route error', e); }
    }
  }

  // enfoca todo
  if (bounds.isValid()) {
    try { map.fitBounds(bounds.pad(0.15), { padding:[40,40] }); } catch {}
  }

  rideMarkers.set(r.id, group);
}


function renderActiveRides(rides){
  const el = document.getElementById('panel-active'); if(!el) return;
  el.innerHTML='';
  const b = document.getElementById('badgeActivos'); if (b) b.innerText = (rides||[]).length;

  // índice rápido para acciones (assign/view) sin pedir el detalle
  window._ridesIndex = new Map();

  (rides||[]).forEach(r=>{
    // guarda en índice
    try { window._ridesIndex.set(r.id, r); } catch {}

    // usa las helpers de la sección //----helpers  card------
    const html = renderRideCard(r);
    if (!html) return; // oculto solo si terminal

    const wrapper = document.createElement('div');
    wrapper.innerHTML = html;
    const card = wrapper.firstElementChild;

    // wire del botón "Ver" (si tu renderRideCard no lo enlaza solo)
    card.querySelector('[data-act="view"]')?.addEventListener('click', ()=> highlightRideOnMap(r));

    el.appendChild(card);
  });
}

// ===== CANCELACIÓN CON SWEETALERT2 (event delegation + no duplicar handler) =====
if (!window.__cancelHandlerBound) {
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-cancel');
    if (!btn) return;

    const rideId = btn.getAttribute('data-ride-id');
    if (!rideId) return;

    // 1) Modal de confirmación
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

    // 2) (Opcional) Motivo de cancelación: si tienes una lista en window.cancelReasons, muéstrala.
    //    Si aún no la tienes, omitimos este paso y mandamos 'null'.
    let chosenReason = null;
    if (Array.isArray(window.cancelReasons) && window.cancelReasons.length > 0) {
      const { value: reasonId } = await Swal.fire({
        title: 'Motivo de cancelación',
        input: 'select',
        inputOptions: window.cancelReasons.reduce((acc, r) => {
          acc[r.id] = r.label;
          return acc;
        }, {}),
        inputPlaceholder: 'Selecciona un motivo',
        showCancelButton: true,
        confirmButtonText: 'Continuar',
        cancelButtonText: 'Volver',
      });
      if (reasonId === undefined) return; // canceló el select
      const item = window.cancelReasons.find(r => String(r.id) === String(reasonId));
      chosenReason = item ? item.label : null;
    }

    // 3) Evitar doble click
    const prevText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Cancelando…';

    try {
      const res = await fetch(`/api/dispatch/rides/${rideId}/cancel`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Tenant-ID': window.currentTenantId || 1,
        },
        body: JSON.stringify({ reason: chosenReason }) // null o label del motivo
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

      await Swal.fire({
        icon: 'success',
        title: 'Cancelado',
        timer: 1200,
        showConfirmButton: false
      });

      if (typeof loadActiveRides === 'function') {
        await loadActiveRides();
      } else {
        location.reload();
      }
    } catch (err) {
      console.error('cancel error', err);
      await Swal.fire({
        icon: 'error',
        title: 'Error de red',
        text: 'No se pudo contactar al servidor.'
      });
    } finally {
      btn.disabled = false;
      btn.textContent = prevText;
    }
  });

  window.__cancelHandlerBound = true;
}



function suggestedLineStyle(){
  return isDarkMode()
    ? { color:'#4DD9FF', weight:5, opacity:.95, dashArray:'6,8' } // oscuro
    : { color:'#0D6EFD', weight:4, opacity:.90, dashArray:'6,8' }; // claro
}

function suggestedLineSelectedStyle() {
  return isDarkMode()
    ? { color:'#22CC88', weight:6, opacity:.95 }     // sin guiones, más gruesa
    : { color:'#16A34A', weight:5, opacity:.95 };
}


async function ensureDriverPreviewLine(driverId, ride) {
  const e = driverPins.get(driverId);
  if (!e) return null;

  // Si ya hay línea, la dejamos (la vamos a restilar si hace falta)
  if (e.previewLine) return e.previewLine;

  // Crear nueva línea sugerida DRIVER -> ORIGEN
  const line = await drawSuggestedRoute(
    e.marker.getLatLng(),
    L.latLng(ride.origin_lat, ride.origin_lng)
  );
  line.setStyle(suggestedLineStyle());
  line.options.className = 'cc-suggested';
  e.previewLine = line.addTo(layerRoute);
  return e.previewLine;
}

// Traza DRIVER->ORIGEN usando el pin si existe; si no, usa lat/lng del candidato
async function ensurePreviewLineForCandidate(candidate, ride) {
  const id = candidate.id || candidate.driver_id;
  const e  = driverPins.get(Number(id));

  let fromLL = null;
  if (e?.marker) {
    fromLL = e.marker.getLatLng();
  } else if (Number.isFinite(candidate.lat) && Number.isFinite(candidate.lng)) {
    fromLL = L.latLng(candidate.lat, candidate.lng);
  } else {
    return null; // sin pin ni coords del candidato
  }
  const toLL = L.latLng(ride.origin_lat, ride.origin_lng);

  const line = await drawSuggestedRoute(fromLL, toLL);
  line.setStyle(suggestedLineStyle());
  line.options.className = 'cc-suggested';

  if (e) e.previewLine = line; // recuerda la línea en el pin si existe
  line.addTo(layerRoute);
  return line;
}


function clearAllPreviews() {
  driverPins.forEach(e => {
    if (e.previewLine) { try { layerRoute.removeLayer(e.previewLine); } catch{} }
    e.previewLine = null;
  });
}

async function drawSuggestedRoute(fromLL, toLL){
  // 1) Google Directions (con tráfico)
  if (window.google?.maps && gDirService){
    try{
      const res = await new Promise((resolve,reject)=>{
        gDirService.route({
          origin: {lat: fromLL.lat, lng: fromLL.lng},
          destination: {lat: toLL.lat, lng: toLL.lng},
          travelMode: google.maps.TravelMode.DRIVING,
          region: 'MX',
          drivingOptions: { departureTime: new Date(), trafficModel: 'bestguess' } // ← tráfico
        }, (r,s)=> s==='OK' ? resolve(r) : reject(s));
      });

      const route = res.routes?.[0];
      const leg   = route?.legs?.[0];
      const pts   = (route?.overview_path || []).map(p => [p.lat(), p.lng()]);

      const line =  L.polyline(pts, { pane:'routePane', className:'cc-suggested', ...suggestedLineStyle() });


      // info útil para tooltip/labels si la quieres mostrar
      line._meta = {
        distance: leg?.distance?.text || '—',
        duration: (leg?.duration_in_traffic || leg?.duration)?.text || '—'
      };
      return line;
    }catch{/* cae a OSRM */}
  }

  // 2) Fallback OSRM (sin tráfico)
  const url = `https://router.project-osrm.org/route/v1/driving/${fromLL.lng},${fromLL.lat};${toLL.lng},${toLL.lat}?overview=full&geometries=geojson`;
  const r = await fetch(url); const j = await r.json();
  const coords = j?.routes?.[0]?.geometry?.coordinates || [];
  const latlngs = coords.map(c => [c[1], c[0]]);
  return L.polyline(latlngs, { pane:'routePane', className:'cc-suggested', ...suggestedLineStyle() });

}

// Dibuja TODAS las líneas driver->pickup para un ride (sin abrir panel)
async function drawAllPreviewLinesFor(ride, candidates) {
  // ordena por distancia y limita a TOP_N
  const TOP_N = 12;
  const ordered = [...(candidates || [])]
    .sort((a,b)=> (a.distance_km??9e9) - (b.distance_km??9e9))
    .slice(0, TOP_N);

  // dibuja con stagger para no saturar Directions/OSRM
  for (let i=0; i<ordered.length; i++) {
    const c = ordered[i];
    const id = c.id || c.driver_id;
    try { await ensureDriverPreviewLine(id, ride); } catch {}
    await new Promise(res => setTimeout(res, 90));
  }

  // (opcional) resaltar el más cercano
  const best = ordered[0];
  if (best) {
    const id = best.id || best.driver_id;
    const pin = driverPins.get(id);
    if (pin?.previewLine) {
      try { pin.previewLine.setStyle(suggestedLineSelectedStyle()); pin.previewLine.bringToFront(); } catch {}
    }
  }
}


const assignmentLines = new Map(); // driver_id -> polyline

async function showDriverToPickup(driver_id, origin_lat=null, origin_lng=null){
  const e = driverPins.get(driver_id); if (!e) return;

  // destino: params → inputs → centro de mapa
  let lat = Number(origin_lat), lng = Number(origin_lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    const a = Number(document.querySelector('#fromLat')?.value);
    const b = Number(document.querySelector('#fromLng')?.value);
    if (Number.isFinite(a) && Number.isFinite(b)) { lat=a; lng=b; }
    else { const c = map.getCenter(); lat=c.lat; lng=c.lng; }
  }

  // borrar anterior
  const prev = assignmentLines.get(driver_id);
  if (prev) { try { layerRoute.removeLayer(prev); } catch{} }

  // pedir ruta y dibujar
  const from = e.marker.getLatLng();
  const to   = L.latLng(lat, lng);
  try{
    const line = await drawSuggestedRoute(from, to);
    line.addTo(layerRoute);
    try { line.bringToFront(); } catch {}
    assignmentLines.set(driver_id, line);
    e.marker.setZIndexOffset(900);
    // Si quieres ver la info de tráfico:
    // console.log('Ruta sugerida:', line._meta);
  }catch(err){ console.warn('No se pudo trazar ruta sugerida', err); }
}

function clearDriverRoute(driver_id){
  const line = assignmentLines.get(driver_id);
  if (line) { try { layerRoute.removeLayer(line); } catch {} }
  assignmentLines.delete(driver_id);
}


// === Bubble discreta de estado (autodespacho) ===
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
function showBubble(text){ const el = ensureBubble(); el.style.display='block'; el.textContent = text||'...'; }
function updateBubble(text){ if (_bubbleEl) _bubbleEl.textContent = text||'...'; }
function hideBubble(){ if (_bubbleEl){ _bubbleEl.style.display='none'; } }

// Cuenta regresiva con callbacks
function startCountdown(totalSec, onTick, onDone){
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


// Previsualiza hasta N candidatos alrededor del origen del ride
async function previewCandidatesFor(ride, limit = 8, radiusKm = 5){
  try {
    const url = `/api/dispatch/nearby-drivers?lat=${ride.origin_lat}&lng=${ride.origin_lng}&km=${radiusKm}`;
    const r = await fetch(url, { headers:{ Accept:'application/json' } });
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
async function drawPreviewLinesStagger(ride, candidates, topN = 12) {
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
    const id = best.id || best.driver_id;
    const pin = driverPins.get(Number(id));
    if (pin?.previewLine) {
      try { pin.previewLine.setStyle(suggestedLineSelectedStyle()); pin.previewLine.bringToFront(); } catch {}
    }
  }
}

// Dibuja TODAS las líneas driver->pickup (stagger) SIN DOM del panel
async function drawPreviewLinesStagger(ride, candidates, topN = 12) {
  const ordered = [...(candidates||[])]
    .sort((a,b)=>(a.distance_km??9e9)-(b.distance_km??9e9))
    .slice(0, topN);

  for (let i=0; i<ordered.length; i++) {
    const c = ordered[i];
    const id = c.id || c.driver_id;
    try { await ensureDriverPreviewLine(id, ride); } catch {}
    await new Promise(res => setTimeout(res, 90)); // reparte llamadas a Directions
  }

  // Resalta el más cercano (opcional)
  const best = ordered[0];
  if (best) {
    const id = best.id || best.driver_id;
    const pin = driverPins.get(id);
    if (pin?.previewLine) {
      try { pin.previewLine.setStyle(suggestedLineSelectedStyle()); pin.previewLine.bringToFront(); } catch {}
    }
  }
}
async function focusRideOnMap(rideId){
  const ride = window._ridesIndex?.get?.(rideId)
    || (await fetch(`/api/dispatch/rides/${rideId}`, { headers:{ Accept:'application/json' } }).then(r=>r.json()));
  return highlightRideOnMap(ride);
}


//-----------------asignacion y movimiento  -----------------------

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

let _assignPreviewLine = null;

function _clearAssignPreview() {
  if (_assignPreviewLine) { try { layerRoute.removeLayer(_assignPreviewLine); } catch{} }
  _assignPreviewLine = null;
}

async function _previewCandidateToPickup(driverId, ride) {
  _clearAssignPreview();
  const e = driverPins.get(Number(driverId));
  if (!e) return;
  const from = e.marker.getLatLng();
  const to   = L.latLng(ride.origin_lat, ride.origin_lng);
  try {
    const line = await drawSuggestedRoute(from, to);   // usa Google y cae a OSRM
    line.setStyle(suggestedLineStyle()).addTo(layerRoute);
    _assignPreviewLine = line;
    try { map.fitBounds(line.getBounds().pad(0.15)); } catch {}
  } catch(err) { console.warn('preview route error', err); }
}


function bearingBetween(a,b){
  const dLon = (b.lng-a.lng)*Math.PI/180;
  const y = Math.sin(dLon)*Math.cos(b.lat*Math.PI/180);
  const x = Math.cos(a.lat*Math.PI/180)*Math.sin(b.lat*Math.PI/180) -
            Math.sin(a.lat*Math.PI/180)*Math.cos(b.lat*Math.PI/180)*Math.cos(dLon);
  return (Math.atan2(y,x)*180/Math.PI+360)%360;
}





// ADD: util para crear/actualizar modal
let _assignPanel, _assignSelected = null, _assignRide = null;
let _assignOriginPin = null;let _assignPickupMarker = null;

//----helpers  card------

function shouldHideRideCard(ride) {
  const st = String(ride.status || '').toLowerCase();
  return st === 'completed' || st === 'canceled';
}

// Intenta tomar resumen de ofertas (si tu API lo trae en el ride)
function summarizeOffers(ride) {
  const offers = Array.isArray(ride.offers) ? ride.offers : [];
  const anyAccepted = offers.some(o => o.status === 'accepted');
  const anyOffered  = offers.some(o => o.status === 'offered');
  const rejectedBy  = offers.filter(o => o.status === 'rejected')
                            .map(o => o.driver_name || `#${o.driver_id}`);
  return { offers, anyAccepted, anyOffered, rejectedBy };
}

function deriveRideUi(ride) {
  const st = String(ride.status || '').toLowerCase();
  const { anyAccepted, anyOffered, rejectedBy } = summarizeOffers(ride);

  let badge = '';
  let showAssign = false;
  let showReoffer = false;
  let showRelease = false;
  let showCancel = true; // casi siempre, salvo terminal

  if (st === 'requested') {
    badge = 'Pendiente';
    showAssign  = true;
    showReoffer = true;
  } else if (st === 'offered' || (anyOffered && !anyAccepted)) {
    badge = 'Oferta enviada';
    showAssign  = false; // <- lo importante: NO ocultar la card, solo el botón
    showReoffer = true;
  } else if (st === 'accepted' || st === 'assigned') {
    badge = 'Aceptada';
    showRelease = true;
  } else if (st === 'en_route') {
    badge = 'En ruta';
    showRelease = true;
  } else if (st === 'arrived') {
    badge = 'Llegó al punto';
    showRelease = true;
  } else if (st === 'on_board' || st === 'onboard') {
    badge = 'En viaje';
    showRelease = false;
  } else if (st === 'completed') {
    badge = 'Completada'; showCancel = false;
  } else if (st === 'canceled') {
    badge = 'Cancelada';  showCancel = false;
  } else {
    badge = st; // fallback
  }

  return { badge, showAssign, showReoffer, showRelease, showCancel, rejectedBy };
}


function renderRideCard(ride) {
  if (shouldHideRideCard(ride)) return '';

  const ui = deriveRideUi(ride);

  const rejectedHint = ui.rejectedBy?.length
    ? `<div class="text-muted small mt-1">Rechazada por: ${ui.rejectedBy.join(', ')}</div>`
    : '';

  const km  = (ride.km != null && !isNaN(ride.km)) ? Number(ride.km).toFixed(1)
            : (ride.distance_m ? (ride.distance_m / 1000).toFixed(1) : '-');

  const min = (ride.min != null) ? ride.min
            : (ride.duration_s ? Math.round(ride.duration_s / 60) : '-');

  const amt = (ride.quoted_amount != null) ? ride.quoted_amount
            : (ride.amount ?? '-');

  const passName  = ride.passenger_name || '—';
  const passPhone = ride.passenger_phone || '';
  const originLbl = ride.origin_label
                 || (Number.isFinite(ride.origin_lat) ? `${ride.origin_lat.toFixed(5)}, ${ride.origin_lng.toFixed(5)}` : '—');
  const destLbl   = ride.dest_label
                 || (Number.isFinite(ride.dest_lat)   ? `${ride.dest_lat.toFixed(5)}, ${ride.dest_lng.toFixed(5)}`     : '—');

  return `
  <div class="card mb-2" data-ride-id="${ride.id}">
    <div class="card-body">
      <!-- Header -->
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
          <div><strong>Ride #${ride.id}</strong> • <span class="badge bg-info">${ui.badge}</span></div>
          <div class="small mt-1">
            <span class="me-2"><i class="bi bi-person"></i> ${passName}</span>
            ${passPhone ? `<span class="text-muted">${passPhone}</span>` : ''}
          </div>
        </div>
        <div class="text-end small text-muted">
          ${km} km · ${min} min · $${amt}
        </div>
      </div>

      <!-- Origen/Destino -->
      <div class="small mb-2">
        <div><span class="text-muted">Origen:</span> ${originLbl}</div>
        ${destLbl !== '—' ? `<div><span class="text-muted">Destino:</span> ${destLbl}</div>` : ''}
        ${rejectedHint}
      </div>

      <!-- Botonera -->
      <div class="d-flex justify-content-end">
        <div class="btn-group">
          ${ui.showAssign  ? `<button class="btn btn-sm btn-primary" data-act="assign">Asignar</button>` : ''}
          ${ui.showReoffer ? `<button class="btn btn-sm btn-outline-primary" data-act="reoffer">Re-ofertar</button>` : ''}
          ${ui.showRelease ? `<button class="btn btn-sm btn-warning" data-act="release">Liberar</button>` : ''}
          ${ui.showCancel  ? `<button class="btn btn-outline-danger btn-sm btn-cancel" data-ride-id="${ride.id}">Cancelar</button>` : ''}
          <button class="btn btn-sm btn-outline-secondary" data-act="view">Ver</button>
        </div>
      </div>
    </div>
  </div>`;
}



async function postJSON(url, body) {
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type':'application/json',
      'Accept':'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      'Authorization': localStorage.getItem('auth_token') || ''
    },
    body: JSON.stringify(body || {})
  });
 if (!res.ok) {
    const text = await res.text().catch(()=> '');
    console.error('POST', url, '→', res.status, res.statusText, text);
    throw new Error(`HTTP ${res.status}: ${text || res.statusText}`);
  }
  return await res.json();
}



async function onRideAction(e) {
  const btn = e.target.closest('[data-act]');
  if (!btn) return;
  const card = btn.closest('[data-ride-id]');
  if (!card) return;
  const rideId = Number(card.dataset.rideId);
  const act = btn.dataset.act;

  try {
    if (act === 'assign') {
      // Abre tu flujo actual de asignación (offcanvas)
      const ride = window._ridesIndex?.get?.(rideId) || (await fetch(`/api/dispatch/rides/${rideId}`, {headers:{Accept:'application/json'}}).then(r=>r.json()));
      openAssignFlow(ride);
      return; // no refresques todavía
    }
    if (act === 'reoffer') {
      await postJSON('/api/dispatch/tick', { ride_id: rideId });
    }
    if (act === 'release') {
      await postJSON('/api/dispatch/release', { ride_id: rideId });
    }
    if (act === 'cancel') {
     await postJSON(`/api/dispatch/rides/${rideId}/cancel`);
    }
    if (act === 'view') {
      focusRideOnMap(rideId); // tu helper para centrar/pintar
      return; // sin refresh
    }

    // Tras acciones que cambian estado, refresca la lista
    await refreshDispatch();
  } catch (err) {
    console.error(err);
    alert('Acción fallida: ' + (err.message||err));
  }
}
document.addEventListener('click', onRideAction);
  
  
function renderAssignPanel(ride, candidates){
  _assignRide = ride; _assignSelected = null;

  try {
 if (_assignPickupMarker) { layerSuggested.removeLayer(_assignPickupMarker); _assignPickupMarker = null; }
    if (Number.isFinite(ride.origin_lat) && Number.isFinite(ride.origin_lng)) {
      const layer = (typeof layerSuggested !== 'undefined') ? layerSuggested : layerRoute;
      const ic    = (typeof IconOrigin !== 'undefined') ? IconOrigin : (typeof IconDest !== 'undefined' ? IconDest : undefined);
      _assignPickupMarker = L.marker([ride.origin_lat, ride.origin_lng], {
        icon: ic, zIndexOffset: 950
      }).addTo(layer).bindTooltip('Pasajero', {offset:[0,-26]});
    }
  } catch {}

  const el = document.getElementById('assignPanelBody');
  if (!candidates.length){
    el.innerHTML = `<div class="text-muted">No hay conductores cercanos.</div>`;
  } else {
    el.innerHTML = `<div class="list-group" id="assignList" style="max-height: 60vh; overflow:auto"></div>`;
    const list = el.querySelector('#assignList');

    // ORDENAR por distancia y limitar a N para no saturar (ajusta N)
    const TOP_N = 12;
    const ordered = [...candidates]
      .sort((a,b)=> (a.distance_km??9e9) - (b.distance_km??9e9))
      .slice(0, TOP_N);

    // 1) Render de la lista + click handler
    ordered.forEach(c=>{
      const id = c.id || c.driver_id;
      const dist = (c.distance_km!=null) ? `${c.distance_km.toFixed(2)} km` : '';
      const item = document.createElement('button');
      item.type='button';
      item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
      item.dataset.driverId = id;
      item.innerHTML = `
        <div>
          <div><b>${c.name||('Driver '+id)}</b> <span class="text-muted">(${String(c.vehicle_type||'sedan')})</span></div>
          <div class="small text-muted">${dist}</div>
        </div>
        <span class="badge bg-secondary">${id}</span>
      `;

      item.addEventListener('click', async ()=>{
        _assignSelected = id;
        // activar visualmente
        list.querySelectorAll('.active').forEach(n=> n.classList.remove('active'));
        item.classList.add('active');

        // restilar todas a estilo “normal”
        driverPins.forEach(e=>{
          if (e.previewLine) try { e.previewLine.setStyle(suggestedLineStyle()); } catch{}
        });
        // y esta a “seleccionado”
        const line = await ensureDriverPreviewLine(id, ride);
        if (line) try { line.setStyle(suggestedLineSelectedStyle()); line.bringToFront(); } catch{}

        document.getElementById('btnDoAssign').disabled = !_assignSelected;
      });

      list.appendChild(item);
    });

    // 2) DIBUJAR TODAS LAS LÍNEAS AL ABRIR (stagger para no saturar)
    (async () => {
      for (let i=0; i<ordered.length; i++) {
        const c = ordered[i];
        const id = c.id || c.driver_id;
        try { await ensureDriverPreviewLine(id, ride); } catch {}
        // pequeñísimo delay para repartir llamadas a Directions/OSRM
        await new Promise(res => setTimeout(res, 90));
      }
      // Selecciona por defecto al más cercano (primero de la lista)
      const firstBtn = list.querySelector('.list-group-item');
      if (firstBtn) firstBtn.click();
    })();
  }

  // botón Asignar
// Helper para tenant (pon <meta name="tenant-id" content="1"> en tu layout)
function getTenantId(){
  return document.querySelector('meta[name="tenant-id"]')?.content
      || window.__TENANT_ID__
      || 1;
}

// Limpia todas las previsualizaciones
function clearSuggestedLines() {
  try {
    // 1) Remueve TODAS las líneas de preview del layer (por className)
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
    driverPins.forEach(e => {
      if (e.previewLine) {
        try { layerRoute.removeLayer(e.previewLine); } catch {}
        e.previewLine = null;
      }
    });

    // 3) Limpia cualquier “preview” general/pickup marker del flujo de asignación
    try {
      if (typeof _assignPreviewLine !== 'undefined' && _assignPreviewLine) {
        layerRoute.removeLayer(_assignPreviewLine); _assignPreviewLine = null;
      }
    } catch {}
    try {
      if (typeof _assignPickupMarker !== 'undefined' && _assignPickupMarker) {
        (layerSuggested || layerRoute).removeLayer(_assignPickupMarker);
        _assignPickupMarker = null;
      }
    } catch {}

  } catch (err) {
    console.warn('[clearSuggestedLines] error', err);
  }
}

function onRideAssigned(ride) {
  // 1) limpia todas las previsualizaciones
  clearSuggestedLines();

  // 2) dibuja la línea “real” driver→pickup (si ya hay driver_id)
  if (ride?.driver_id && Number.isFinite(ride.origin_lat) && Number.isFinite(ride.origin_lng)) {
    showDriverToPickup(ride.driver_id, ride.origin_lat, ride.origin_lng);
  }
}


// botón Asignar
document.getElementById('btnDoAssign').onclick = async ()=>{
  if (!_assignSelected || !_assignRide) return;
  const btn = document.getElementById('btnDoAssign');
  btn.disabled = true;
  try{
    const r = await fetch('/api/dispatch/assign', {
      method:'POST',
      headers:{
        'Content-Type':'application/json',
        'Accept':'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        'X-Tenant-ID': document.querySelector('meta[name="tenant-id"]')?.content || '1'
      },
      body: JSON.stringify({ ride_id:_assignRide.id, driver_id:_assignSelected })
    });
    const j = await r.json().catch(()=>({}));
    if (!r.ok || j.ok===false) throw new Error(j?.msg || ('HTTP '+r.status));
    clearAllPreviews();
    if (_assignPickupMarker) { try { layerRoute.removeLayer(_assignPickupMarker); } catch{} _assignPickupMarker=null; }
    if (_assignPanel) _assignPanel.hide();
    refreshDispatch();
  }catch(e){
    alert('No se pudo asignar: '+(e.message||e));
  } finally {
    btn.disabled = false;
  }
};


  // abrir offcanvas + cleanup al cerrar
  const panelEl = document.getElementById('assignPanel');
  _assignPanel = _assignPanel || new bootstrap.Offcanvas(panelEl, {backdrop:false});

  panelEl.addEventListener('hidden.bs.offcanvas', () => {
  onRideAssigned(_assignRide);
     }, { once:false });

  _assignPanel.show();
}

function openAssignFlow(ride){
  if (!ride || !Number.isFinite(ride.origin_lat) || !Number.isFinite(ride.origin_lng)) {
    alert('Este ride no tiene origen válido'); return;
  }
  fetch(`/api/dispatch/nearby-drivers?lat=${ride.origin_lat}&lng=${ride.origin_lng}&km=5`,
        { headers:{Accept:'application/json'} })
    .then(r => r.ok ? r.json() : Promise.reject(r.status))
    .then(list => {
      let candidates = Array.isArray(list) ? list : [];
      if (!candidates.length){
        // fallback local si el endpoint devuelve vacío
        driverPins.forEach((e, id) => {
          const ll = e.marker.getLatLng();
          const dk = _distKm(ride.origin_lat, ride.origin_lng, ll.lat, ll.lng);
          candidates.push({ id, name:e.name||('Driver '+id), vehicle_type:e.type||'sedan', vehicle_plate:e.plate||'', distance_km: dk });
        });
        candidates = candidates.filter(c => c.distance_km <= 7);
      }
      renderAssignPanel(ride, candidates);
    })
    .catch(e => { console.warn('nearby-drivers error', e); renderAssignPanel(ride, []); });
}


// ADD: POST de asignación + UI
async function confirmAssign(ride, driver){
  try{
    await fetch('/api/dispatch/assign', {
      method:'POST',
      headers:{
        'Content-Type':'application/json',
        'Accept':'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      body: JSON.stringify({ ride_id: ride.id, driver_id: driver.id })
    });

    // cierra modal
    document.getElementById('assignModal')._inst?.hide();

    // pinta línea sugerida driver → origen
    showDriverToPickup(driver.id, ride.origin_lat, ride.origin_lng);

    // sube prioridad visual del driver y refresca panel
    const pin = driverPins.get(driver.id);
    if (pin) pin.marker.setZIndexOffset(900);
    clearAllPreviews();
    refreshDispatch();
  }catch(e){
    console.error(e);
    alert('No se pudo asignar.');
  }
}





/* ---------- INIT (cuando el DOM está listo) ---------- */
document.addEventListener('DOMContentLoaded', async () => {
  const mapEl = document.getElementById('map');
  if (!mapEl) return;
  mapEl.classList.toggle('map-dark', isDarkMode());

  map = L.map('map', { worldCopyJump:false, maxBoundsViscosity:1.0 })
          .setView(CENTER_DEFAULT, DEFAULT_ZOOM);
  L.tileLayer(OSM.url, { attribution: OSM.attr }).addTo(map);

  // panes
  const sectoresPane = map.createPane('sectoresPane'); sectoresPane.style.zIndex = 350;
  const routePane    = map.createPane('routePane');    routePane.style.zIndex = 460; routePane.style.pointerEvents='none';
  const suggestedPane= map.createPane('suggestedPane');suggestedPane.style.zIndex = 455; suggestedPane.style.pointerEvents='none';

  // capas
  layerSectores = L.layerGroup().addTo(map);
  layerStands   = L.layerGroup().addTo(map);
  layerRoute    = L.layerGroup({ pane:'routePane' }).addTo(map);
  layerDrivers  = L.layerGroup().addTo(map);
  layerSuggested= L.layerGroup({ pane:'suggestedPane' }).addTo(map);


       

  // google widgets
  loadGoogleMaps().then((google)=>{
    gDirService = new google.maps.DirectionsService();
    gGeocoder   = new google.maps.Geocoder();

    acFrom = new google.maps.places.Autocomplete(qs('#inFrom'), { fields:['formatted_address','geometry'] });
    acTo   = new google.maps.places.Autocomplete(qs('#inTo'),   { fields:['formatted_address','geometry'] });
    acFrom.addListener('place_changed', ()=>{
      const p = acFrom.getPlace(); if(!p?.geometry) return;
      setFrom([p.geometry.location.lat(), p.geometry.location.lng()], p.formatted_address);
    });
    acTo.addListener('place_changed', ()=>{
      const p = acTo.getPlace(); if(!p?.geometry) return;
      setTo([p.geometry.location.lat(), p.geometry.location.lng()], p.formatted_address);
    });
  }).catch(e=> console.warn('[DISPATCH] Google no cargó', e));

  // toggles + pick
  let pickMode=null;
  qs('#btnPickFrom')?.addEventListener('click', ()=>{ pickMode='from'; map.getContainer().style.cursor='crosshair'; });
  qs('#btnPickTo')?.addEventListener('click',   ()=>{ pickMode='to';   map.getContainer().style.cursor='crosshair'; });
  map.on('click', (ev)=>{
    if(!pickMode) return;
    map.getContainer().style.cursor='';
    if(pickMode==='from') setFrom([ev.latlng.lat, ev.latlng.lng]);
    else setTo([ev.latlng.lat, ev.latlng.lng]);
    pickMode=null;
  });
  qs('#toggle-sectores')?.addEventListener('change', e=> e.target.checked? layerSectores.addTo(map):map.removeLayer(layerSectores));
  qs('#toggle-stands')?.addEventListener('change',   e=> e.target.checked? layerStands.addTo(map):  map.removeLayer(layerStands));

  // recordar último ride por teléfono
  qs('#pass-phone')?.addEventListener('blur', async (e)=>{
    const phone = (e.target.value||'').trim(); if(!phone) return;
    try{
      const r = await fetch(`/api/passengers/last-ride?phone=${encodeURIComponent(phone)}`);
      if(!r.ok) return;
      const j = await r.json(); if(!j) return;
      if(j.passenger_name && !qs('#pass-name').value) qs('#pass-name').value = j.passenger_name;
      if(j.notes && !qs('#ride-notes').value) qs('#ride-notes').value = j.notes;
      if(Number.isFinite(j.origin_lat) && Number.isFinite(j.origin_lng)) setFrom([j.origin_lat, j.origin_lng], j.origin_label);
      if(Number.isFinite(j.dest_lat)   && Number.isFinite(j.dest_lng))   setTo([j.dest_lat,   j.dest_lng],   j.dest_label);
    }catch{}
  });

  // crear ride
  qs('#btnCreate')?.addEventListener('click', async ()=>{
  const payload = {
  passenger_name:  qs('#pass-name')?.value || null,
  passenger_phone: qs('#pass-phone')?.value || null,

  origin_lat: parseFloat(qs('#fromLat')?.value),
  origin_lng: parseFloat(qs('#fromLng')?.value),
  origin_label: qs('#inFrom')?.value || null,

  dest_lat: parseFloat(qs('#toLat')?.value) || null,
  dest_lng: parseFloat(qs('#toLng')?.value) || null,
  dest_label: qs('#inTo')?.value || null,

  payment_method: qs('#pay-method')?.value || 'cash',
  fare_mode: (qs('#fareMode')?.value || 'meter'), // si lo usas

  notes: qs('#ride-notes')?.value || null,
  pax: parseInt(qs('#pax')?.value)||1,
  scheduled_for: null,

  quoted_amount: (() => {
    const v = Number(qs('#fareAmount')?.value);
    return Number.isFinite(v) ? Math.round(v) : null; // enteros
  })(),
  distance_m: __lastQuote?.distance_m ?? null,
  duration_s: __lastQuote?.duration_s ?? null,
  route_polyline: __lastQuote?.polyline ?? null,   // si lo guardas
  requested_channel: 'dispatch',
};

    if(!Number.isFinite(payload.origin_lat) || !Number.isFinite(payload.origin_lng)){
      alert('Indica un origen válido.'); return;
    }
    try{
      const r = await fetch('/api/rides', {
        method:'POST',
        headers:{
          'Content-Type':'application/json',
          'Accept':'application/json',
          'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify(payload)
      });
      if(!r.ok){ const t=await r.text(); throw new Error(t||('HTTP '+r.status)); }
      const ride = await r.json();

      // === Autodespacho visual opcional (delay) ===
      // lee settings expuestos por el backend en el layout (o defaults)
      const ds = (window.ccDispatchSettings || {});
      const enabled   = ds.auto_dispatch_enabled ?? true;
      const delaySec  = Number(ds.auto_dispatch_delay_s ?? 0);
      const prevN     = Number(ds.auto_dispatch_preview_n ?? 8);
      const radiusKm  = Number(ds.auto_dispatch_preview_radius_km ?? 5);

      if (enabled && delaySec > 0 && Number.isFinite(ride.origin_lat) && Number.isFinite(ride.origin_lng)) {
      showBubble('Servicio detectado · buscando candidatos...');
      previewCandidatesFor(ride, prevN, radiusKm);

      // <<< NUEVO: dibuja líneas un poco antes del tick, sin abrir panel >>>
      const PAD_MS = 1200; // 1.2s antes del tick
      setTimeout(async () => {
        try {
          const candidates = await getCandidatesFor(ride, radiusKm);
          await drawPreviewLinesStagger(ride, candidates, prevN);
        } catch(e) { console.warn('auto preview lines error', e); }
      }, Math.max(0, delaySec*1000 - PAD_MS));

      startCountdown(delaySec, (s)=> {
        updateBubble(`Asignando en ${s}s...`);
      }, async ()=> {
        hideBubble();
        try {
          await fetch('/api/dispatch/tick', {
            method:'POST',
            headers:{
              'Content-Type':'application/json',
              'Accept':'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
              'X-Tenant-ID': getTenantId()
            },
            body: JSON.stringify({ ride_id: ride.id })
          });
        } catch(e) { console.warn('tick error', e); }
      });
}


// feedback al operador
//alert('Viaje creado #'+(ride.id||''));

// refresca panel/mapa
refreshDispatch();
    }catch(e){
      console.error(e); alert('No se pudo crear el viaje.');
    }
  });

  // limpiar
  qs('#btnClear')?.addEventListener('click', ()=>{
    ['inFrom','inTo','pass-name','pass-phone','pass-account','ride-notes'].forEach(id=>{ const el = qs('#'+id); if (el) el.value=''; });
    ['fromLat','fromLng','toLat','toLng'].forEach(id=>{ const el = qs('#'+id); if (el) el.value=''; });
    layerRoute.clearLayers();
    if (fromMarker){ fromMarker.remove(); fromMarker=null; }
    if (toMarker){   toMarker.remove();   toMarker=null;  }
    const rs = document.getElementById('routeSummary');
    if (rs) rs.innerText = 'Ruta: — · Zona: — · Cuando: ahora';
  });


  qs('#btnQuote')?.addEventListener('click', async () => {
  const aLat = parseFloat(qs('#fromLat')?.value);
  const aLng = parseFloat(qs('#fromLng')?.value);
  const bLat = parseFloat(qs('#toLat')?.value);
  const bLng = parseFloat(qs('#toLng')?.value);
  if (!Number.isFinite(aLat) || !Number.isFinite(aLng)
   || !Number.isFinite(bLat) || !Number.isFinite(bLng)) {
    alert('Indica origen y destino para cotizar.'); return;
  }
  try {
    const r = await fetch('/api/dispatch/quote', {
      method: 'POST',
      headers: {
        'Content-Type':'application/json',
        'Accept':'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      body: JSON.stringify({
        origin: {lat:aLat, lng:aLng},
        destination: {lat:bLat, lng:bLng},
        round_to_step: 1.00      // pesos enteros
      })
    });
    const j = await r.json();
    if (!r.ok || j.ok===false) throw new Error(j?.msg || ('HTTP '+r.status));

    // Pinta resumen y pone la tarifa sugerida editable
    const rs = document.getElementById('routeSummary');
    if (rs) {
      const km = (j.distance_m/1000).toFixed(1)+' km';
      const min = Math.round(j.duration_s/60)+' min';
      rs.innerText = `Ruta: ${km} · ${min} · Tarifa: $${j.amount}`;
    }
    qs('#fareAmount').value = j.amount;

    // Si quieres dejar los campos ocultos listos:
    // (ya tienes hidden lat/lng; no toco más)

  } catch(e) {
    console.error(e);
    alert('No se pudo cotizar.');
  }
});

  // modo oscuro dinámico
 window.addEventListener('theme:changed', (e)=>{
  const dark = e?.detail?.theme === 'dark';
  mapEl.classList.toggle('map-dark', dark);

  // Sectores
  layerSectores.eachLayer(l => {
    try { l.setStyle && l.setStyle(sectorStyle()); } catch {}
  });

  // Rutas (normales y sugeridas)
  layerRoute.eachLayer(l => {
    try {
      if (!(l instanceof L.Polyline)) return;
      const cls = l.options && l.options.className;
      if (cls === 'cc-route') {
        l.setStyle(routeStyle());
      } else if (cls === 'cc-suggested') {
        l.setStyle(suggestedLineStyle());
      }
    } catch {}
  });

  // Recalcular layout
  setTimeout(()=>{ try{ map.invalidateSize(); }catch{} },150);
});


  // cargas iniciales
  loadSectores(); loadStands();
  setTimeout(()=>{ try{ map.invalidateSize(); }catch{} }, 200);

  // polling
  startPolling();



 
});

// Evita ReferenceError si todavía no definiste el módulo de drivers
window.getCandidatesFor ||= function _noopGetCandidatesFor() { return []; };

// dentro del IIFE de dispatch.js, cerca de donde declaras capas:
// driverPins: Map<driver_id, { marker, type, vstate }>
const driverPins = new Map();

function upsertDriver(d) {
  const id  = d.id || d.driver_id;
  const lat = Number(d.lat ?? d.last_lat);
  const lng = Number(d.lng ?? d.last_lng);
  if (!id || !Number.isFinite(lat) || !Number.isFinite(lng)) return;

  const type    = String(d.vehicle_type || 'sedan').toLowerCase();
  const drvSt   = String(d.driver_status || '').toLowerCase();

  let vstate = visualState(d);
  if (drvSt === 'offline') vstate = 'offline';

  const icon    = makeCarIcon(type, vstate);
  const zScale  = scaleForZoom(map ? map.getZoom() : DEFAULT_ZOOM);
  const bearing = Number(d.bearing ?? d.heading_deg ?? 0);

  const econ  = d.vehicle_economico || '';
  const plate = d.vehicle_plate || '';
  const phone = d.phone || '';
  const name  = d.name || 'Conductor';
  const label = econ ? `${name} (${econ})` : name;
  const labelSt = statusLabel(d.ride_status, d.driver_status);
  const seenTxt = d.reported_at ? `Visto ${fmtAgo(d.reported_at)}` : '—';

  const tip = `
    <div class="cc-tip">
      <div class="tt-title">${label}</div>
      <div class="tt-sub">${type.toUpperCase()}${plate ? ' · '+plate : ''}</div>
      <div class="tt-meta">${labelSt}${phone ? ' · Tel: '+phone : ''} · ${seenTxt}</div>
    </div>`;

  // ↑ prioridad visual si está en servicio
  const zIdx = (['on_board','accepted','en_route','arrived'].includes(vstate)) ? 900
            : (vstate === 'offline' ? 100 : 500);

  let entry = driverPins.get(id);
  if (!entry) {
    const marker = L.marker([lat, lng], {
      icon,
      zIndexOffset: zIdx,
      riseOnHover: true
    })
    .bindTooltip(tip, { className:'cc-tip', direction:'top', offset:[0,-12], sticky:true })
    .addTo(layerDrivers);

    // ⬇️ estado “estable” + buffers para histeresis
    driverPins.set(id, {
      marker,
      type,
      vstate,             // estado aplicado
      wantState: vstate,  // último estado solicitado
      mismatchCount: 0,   // cuántas veces seguidas se repite wantState
      lastSwapAt: 0       // timestamp del último swap de icono
    });

    setMarkerScale(marker, zScale);
    setMarkerBearing(marker, bearing);
    return;
  }

  // mover/actualizar
  entry.marker.setLatLng([lat, lng]);
  setMarkerScale(entry.marker, zScale);
  setMarkerBearing(entry.marker, bearing);
  entry.marker.setZIndexOffset(zIdx);
  const tt = entry.marker.getTooltip(); if (tt) tt.setContent(tip);

  // === HISTERESIS DE ICONO (evita parpadeo) ===
  const now = Date.now();
  if (entry.type !== type || entry.vstate !== vstate) {
    // memoriza intención y cuenta repeticiones
    const wantsChanged = (entry.wantState !== vstate || entry.type !== type);
    if (wantsChanged) {
      entry.wantState = vstate;
      entry.wantType  = type;
      entry.mismatchCount = 1;
    } else {
      entry.mismatchCount++;
    }

    const DWELL = 2;      // lecturas consecutivas necesarias
    const MIN_MS = 200;   // tiempo mínimo entre swaps
    const timeOk = (now - entry.lastSwapAt) >= MIN_MS;

    if (entry.mismatchCount >= DWELL && timeOk) {
      entry.type = entry.wantType || type;
      entry.vstate = entry.wantState || vstate;
      entry.marker.setIcon(makeCarIcon(entry.type, entry.vstate)); // recrea ya estable
      setMarkerScale(entry.marker, zScale); // re-aplica escala
      setMarkerBearing(entry.marker, bearing);
      entry.lastSwapAt = now;
      entry.mismatchCount = 0;
    }
  } else {
    // estado estable, limpia contadores
    entry.wantState = vstate;
    entry.wantType  = type;
    entry.mismatchCount = 0;
  }
}

function removeDriverById(id) {
  const pin = driverPins.get(id);
  if (pin) {
    try { layerDrivers.removeLayer(pin.marker); } catch {}
    driverPins.delete(id);
  }
}

// Llama esto al confirmar logout del propio chofer
// (después de POST /api/auth/logout 200 OK)
function onSelfLogout(driverId) {
  removeDriverById(driverId);
}

function renderDrivers(list){
  layerDrivers.clearLayers();
  driverPins.clear();

  const now = Date.now();
  const FRESH_MS = 120 * 1000;

  (Array.isArray(list) ? list : []).forEach(d=>{
    const hasLL = Number.isFinite(Number(d.lat)) && Number.isFinite(Number(d.lng));
    if (!hasLL) return; // sin ubicación, no se puede dibujar

    const t = d.reported_at ? new Date(d.reported_at).getTime() : 0;
    const isFresh = t && (now - t) <= FRESH_MS;

    const drvSt  = String(d.driver_status || '').toLowerCase();
    const rideSt = String(d.ride_status   || '').toLowerCase();

    const hasActiveRide = ['requested','scheduled','offered','accepted','assigned','en_route','arrived','onboard','on_board','boarding'].includes(rideSt);

    // Regla: pintamos si es fresh, o si está offline (para mostrar gris),
    // o si tiene ride activo (no perderlo por ping “viejo”)
    if (isFresh || drvSt === 'offline' || hasActiveRide) {
      upsertDriver(d);
    }
    // Si quieres que los NO fresh/NO offline/NO ride activo se oculten, no hagas nada aquí.
  });
}




async function updateSuggestedRoutes(rides){
  // limpia todas primero (evita “fantasmas”)
  driverPins.forEach((_, driverId) => clearDriverRoute(driverId));

  // vuelve a crear solo para rides con driver y en “pre-pasajero”
  const targetStates = new Set(['ASSIGNED','EN_ROUTE','ARRIVED','REQUESTED','OFFERED','SCHEDULED','ACCEPTED']);
  for (const r of (rides||[])){
    const st = String(r.status||'').toUpperCase();
    if (!r.driver_id || !targetStates.has(st)) continue;
    if (!Number.isFinite(r.origin_lat) || !Number.isFinite(r.origin_lng)) continue;
    await showDriverToPickup(r.driver_id, Number(r.origin_lat), Number(r.origin_lng));
  }
}


function reiconAll() {
  const z = map.getZoom();
  driverPins.forEach((e) => {
    const ic = makeCarIcon(e.type, e.vstate);
    e.marker.setIcon(ic);
  });
}




// …dentro del DOMContentLoaded, después de instanciar el mapa y capas:
const tenantId = (window.ccTenant && window.ccTenant.id) || 1;

// Tiempo real de ubicaciones
if (window.Echo) {
  const tenantId = (window.ccTenant && window.ccTenant.id) || 1;
 window.Echo.channel(`driver.location.${tenantId}`)
  .listen('.LocationUpdated', async (p) => {
    upsertDriver({ ...p, id:p.driver_id });

    const rs = String(p.ride_status||'').toUpperCase();
    if (['ASSIGNED','EN_ROUTE','ARRIVED'].includes(rs) && Number.isFinite(p.origin_lat) && Number.isFinite(p.origin_lng)) {
      await showDriverToPickup(p.driver_id, p.origin_lat, p.origin_lng);
    }
    if (['ON_BOARD','ONBOARD','FINISHED','CANCELLED','CANCELED'].includes(rs)) {
      clearDriverRoute(p.driver_id);
    }
  });

}



/* ---------- Datos para mapa (sectores / stands) ---------- */
async function loadSectores(){
  try{
    const r = await fetch('/api/sectores', { headers:{Accept:'application/json'} });
    if(!r.ok) return;
    const data = await r.json();
    layerSectores.clearLayers();
    const fc = Array.isArray(data)
      ? { type:'FeatureCollection', features: data.map(row=>{
          let area = row.area; if(typeof area==='string'){ try{ area=JSON.parse(area);}catch{area=null;} }
          if(!area) return null;
          if(area.type==='Feature'){ area.properties = {...(area.properties||{}), nombre: row.nombre}; return area; }
          return { type:'Feature', properties:{ nombre:row.nombre }, geometry: area };
        }).filter(Boolean) }
      : data;

    L.geoJSON(fc, {
      pane:'sectoresPane',
      style: sectorStyle,
      interactive: false,
      onEachFeature: (f,l)=> l.bindTooltip(`<strong>${f?.properties?.nombre||'Sector'}</strong>`,
                     {direction:'top',offset:[0,-4],className:'sector-tip'})
    }).addTo(layerSectores);
  }catch(e){ console.warn('sectores error', e); }
}
async function loadStands(){
  try{
    const r = await fetch('/api/taxistands', { headers:{Accept:'application/json'} });
    if(!r.ok) return;
    const list = await r.json();
    layerStands.clearLayers();
    list.forEach(z=>{
      const lat=Number(z.latitud), lng=Number(z.longitud);
      if(!Number.isFinite(lat)||!Number.isFinite(lng)) return;
      L.marker([lat,lng], {icon:IconStand, zIndexOffset:20})
        .bindTooltip(`<strong>${z.nombre}</strong><div class="text-muted">(${fmt(lat)}, ${fmt(lng)})</div>`,
                     {direction:'top',offset:[0,-12],className:'stand-tip'})
        .addTo(layerStands);
    });
  }catch(e){ console.warn('stands error', e); }
}

})(); // FIN IIFE

