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
    assigned: '/images/vehicles/sedan-assigned.png',
    onboard:  '/images/vehicles/sedan-onboard.png',
    busy:     '/images/vehicles/sedan-busy.png',
    offline:  '/images/vehicles/sedan-offline.png',
  },
  van: {
    free:     '/images/vehicles/van-free.png',
    assigned: '/images/vehicles/van-assigned.png',
    onboard:  '/images/vehicles/van-onboard.png',
    busy:     '/images/vehicles/van-busy.png',
    offline:  '/images/vehicles/van-offline.png',
  },
  vagoneta: {
    free:     '/images/vehicles/vagoneta-free.png',
    assigned: '/images/vehicles/vagoneta-assigned.png',
    onboard:  '/images/vehicles/vagoneta-onboard.png',
    busy:     '/images/vehicles/vagoneta-busy.png',
    offline:  '/images/vehicles/vagoneta-offline.png',
  },
  premium: {
    free:     '/images/vehicles/premium-free.png',
    assigned: '/images/vehicles/premium-assigned.png',
    onboard:  '/images/vehicles/premium-onboard.png',
    busy:     '/images/vehicles/premium-busy.png',
    offline:  '/images/vehicles/premium-offline.png',
  },
};

function visualState({ ride_status, driver_status, shift_open }) {
  const r = String(ride_status || '').toLowerCase();
  const d = String(driver_status || '').toLowerCase();

  // 1) Si no tiene turno abierto → offline (no lo ocultes, muéstralo gris si lo estás listando)
  if (shift_open === false) return 'offline';

  // 2) Si el backend/cron lo marcó offline → sprite gris
  if (d === 'offline') return 'offline';

  // 3) Prioridad ride
  if (r === 'on_board' || r === 'onboard') return 'onboard';
  if (['accepted','assigned','en_route','arrived','requested','offered','scheduled'].includes(r)) return 'assigned';

  // 4) Estado del driver
  if (d === 'busy') return 'busy';

  // 5) Default libre
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
      <img class="cc-car-img" src="${src}" alt="${type}"
           width="${CAR_W}" height="${CAR_H}" />
    </div>`;
  return L.divIcon({
    className: 'cc-car-icon',
    html,
    iconSize: [CAR_W, CAR_H],                 // <- contenedor constante
    iconAnchor: [CAR_W/2, CAR_H/2],           // <- ancla centrada (no salta)
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
      transition: transform 120ms linear;  /* suave al rotar/escalar */
    }`;
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

  const STATUS = {
    requested:{label:'Requerido',color:'primary'},
    scheduled:{label:'Programado',color:'info'},
    offered:{label:'Ofertado',color:'purple'},
    accepted:{label:'Aceptado',color:'indigo'},
    assigned:{label:'Asignado',color:'warning'},
    en_route:{label:'En ruta',color:'teal'},
    arrived:{label:'Llegó',color:'orange'},
    boarding:{label:'Abordando',color:'warning'},
    onboard:{label:'A bordo',color:'danger'},
    finished:{label:'Terminado',color:'success'},
    canceled:{label:'Cancelado',color:'secondary'},
    cancelled:{label:'Cancelado',color:'secondary'},
  };

  (rides||[]).forEach(r=>{
    const sRaw = String(r.status||'').toLowerCase();
    const meta = STATUS[sRaw] || {label:(sRaw||'—'), color:'secondary'};

    // 👇 Coerción segura a número
    const dm = Number(r.distance_m);
    const ds = Number(r.duration_s);
    const qa = Number(r.quoted_amount);

    const km  = Number.isFinite(dm) ? (dm/1000).toFixed(1)+' km' : '—';
    const min = Number.isFinite(ds) ? Math.round(ds/60)+' min'     : '—';
    const fare= Number.isFinite(qa) ? ('$'+Math.round(qa))         : '—';

    const when = r.scheduled_for ? `Prog: ${new Date(r.scheduled_for).toLocaleString()}` : 'Ahora';

    const card = document.createElement('div');
    card.className = 'card mb-2 cc-ride-card';
    card.innerHTML = `
      <div class="cc-ride-header bg-${meta.color}">
        <div class="d-flex justify-content-between align-items-center">
          <div class="text-white fw-semibold">#${r.id} · ${meta.label}</div>
          <button class="btn btn-xs btn-light" data-zoom>Ver</button>
        </div>
      </div>
      <div class="card-body py-2">
        <div><b>${r.passenger_name||'-'}</b> <span class="text-muted">(${r.passenger_phone||''})</span></div>
        <div class="small">${r.origin_label||'—'}</div>
        <div class="small">→ ${r.dest_label||'—'}</div>
        <div class="small text-muted mt-1">
          Dist: <b>${km}</b> · Tiempo: <b>${min}</b> · Tarifa: <b>${fare}</b>
        </div>
        <div class="small text-muted">${when}</div>
      </div>
      <div class="card-footer py-2 d-flex justify-content-end gap-2">
        <button class="btn btn-sm btn-outline-success" data-assign>Asignar</button>
        <button class="btn btn-sm btn-outline-danger"  data-cancel>Cancelar</button>
      </div>
    `;
    card.querySelector('[data-zoom]')   .addEventListener('click', ()=> highlightRideOnMap(r));
    card.querySelector('[data-assign]').addEventListener('click', ()=> openAssignFlow(r));
    card.querySelector('[data-cancel]').addEventListener('click', async ()=>{
      if(!confirm('Cancelar este viaje?')) return;
      await fetch('/api/dispatch/cancel',{method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
        body: JSON.stringify({ride_id:r.id})
      });
      refreshDispatch();
    });
    el.appendChild(card);
  });
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


function clearDriverRoute(driver_id){
  const line = assignmentLines.get(driver_id);
  if (line) { try { layerRoute.removeLayer(line); } catch {} }
  assignmentLines.delete(driver_id);
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
let _assignOriginPin = null;

function renderAssignPanel(ride, candidates){
  _assignRide = ride; _assignSelected = null;

  try {
  if (_assignOriginPin) { layerSuggested.removeLayer(_assignOriginPin); _assignOriginPin = null; }
  if (Number.isFinite(ride.origin_lat) && Number.isFinite(ride.origin_lng)) {
    _assignOriginPin = L.marker([ride.origin_lat, ride.origin_lng], {
      icon: IconOrigin, zIndexOffset: 950
    }).addTo(layerSuggested).bindTooltip('Pasajero', {offset:[0,-26]});
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
  document.getElementById('btnDoAssign').onclick = async ()=>{
    if (!_assignSelected || !_assignRide) return;
    try{
      const r = await fetch('/api/dispatch/assign', {
        method:'POST',
        headers:{
          'Content-Type':'application/json',
          'Accept':'application/json',
          'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''
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
    }
  };

  // abrir offcanvas + cleanup al cerrar
  const panelEl = document.getElementById('assignPanel');
  _assignPanel = _assignPanel || new bootstrap.Offcanvas(panelEl, {backdrop:false});

  panelEl.addEventListener('hidden.bs.offcanvas', () => {
    clearAllPreviews();
    if (_assignPickupMarker) { try { layerRoute.removeLayer(_assignPickupMarker); } catch{} _assignPickupMarker=null; }
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
      alert('Viaje creado #'+(ride.id||''));
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



  // ==== Helpers para pruebas en consola ====
function _llFromMap(id){
  const entry = driverPins.get(id);
  if (entry) {
    const ll = entry.marker.getLatLng();
    return { lat: ll.lat, lng: ll.lng };
  }
  // fallback: centro del mapa
  const c = (window.__map || map).getCenter();
  return { lat: c.lat, lng: c.lng };
}
upsertDriver({ id: 1, lat: 19.20, lng: -96.14, vehicle_type:'van', driver_status:'busy', ride_status:'ASSIGNED', bearing: 135, shift_open:true, name:'Demo', vehicle_economico:'A-12' });

// Reemplazo del viejo setDriverState(id, rideStatus, driverStatus, shiftOpen)
function setDriverState(id, rideStatus = null, driverStatus = null, shiftOpen = true){
  const { lat, lng } = _llFromMap(id);
  upsertDriver({ id, lat, lng, ride_status: rideStatus, driver_status: driverStatus, shift_open: shiftOpen });
}

// Cambiar tipo/vehículo del driver
function setDriverType(id, type){
  const { lat, lng } = _llFromMap(id);
  upsertDriver({ id, lat, lng, vehicle_type: String(type||'sedan').toLowerCase() });
}

// Rotar el icono
function setDriverBearing(id, bearingDeg){
  const { lat, lng } = _llFromMap(id);
  upsertDriver({ id, lat, lng, bearing: bearingDeg });
}

// Mover en el mapa
function moveDriver(id, lat, lng){
  upsertDriver({ id, lat, lng });
}

// Crear/insertar rápido un driver de prueba
function addDriver(o = {}){
  const c = (window.__map || map).getCenter();
  upsertDriver({
    id: o.id ?? 1,
    name: o.name ?? 'Conductor ' + (o.id ?? 1),
    vehicle_type: o.type ?? 'sedan',
    lat: o.lat ?? c.lat,
    lng: o.lng ?? c.lng,
    driver_status: o.ds ?? 'idle',
    ride_status: o.rs ?? '',
    bearing: o.b ?? 0,
    vehicle_economico: o.eco ?? '0001',
    vehicle_plate: o.pla ?? 'XYZ-123',
    speed: o.spd ?? 0,
    reported_at: new Date().toISOString(),
    shift_open: (o.shift_open ?? true)
  });
}


// Exponer en window para usarlos desde la consola
window.setDriverState   = setDriverState;
window.setDriverType    = setDriverType;
window.setDriverBearing = setDriverBearing;
window.moveDriver       = moveDriver;
window.addDriver        = addDriver;
window.showDriverToPickup = showDriverToPickup;
window.clearDriverRoute   = clearDriverRoute;
window.openAssignFlow= openAssignFlow;


});


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

  // estado visual base (free/assigned/onboard/busy)
  let vstate = visualState(d);
  // si el backend marca offline (y hay turno), mostrar PNG offline
  if (drvSt === 'offline') vstate = 'offline';

  const icon    = makeCarIcon(type, vstate); // usa tus PNG
  const zScale  = scaleForZoom(map ? map.getZoom() : DEFAULT_ZOOM);
  const bearing = Number(d.bearing ?? d.heading_deg ?? 0);

  const econ  = d.vehicle_economico || '';
  const plate = d.vehicle_plate || '';
  const phone = d.phone || '';
  const name  = d.name || 'Conductor';
  const label = econ ? `${name} (${econ})` : name;

  // etiqueta de estado legible
  const labelSt = statusLabel(d.ride_status, d.driver_status);

  const seenTxt = d.reported_at ? `Visto ${fmtAgo(d.reported_at)}` : '—';
  const tip = `
    <div class="cc-tip">
      <div class="tt-title">${label}</div>
      <div class="tt-sub">${type.toUpperCase()}${plate ? ' · '+plate : ''}</div>
      <div class="tt-meta">${labelSt}${phone ? ' · Tel: '+phone : ''} · ${seenTxt}</div>
    </div>`;

  // z-index: arriba si asignado/onboard, abajo si offline
  const zIdx = (vstate === 'onboard' || vstate === 'assigned') ? 900
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

    driverPins.set(id, { marker, type, vstate });

    setMarkerScale(marker, zScale);
    setMarkerBearing(marker, bearing);

  } else {
    entry.marker.setLatLng([lat, lng]);

    // refrescar sprite solo si cambia tipo/estado (incluye offline)
    if (entry.type !== type || entry.vstate !== vstate) {
      entry.type = type; entry.vstate = vstate;
      entry.marker.setIcon(makeCarIcon(type, vstate));
      setMarkerScale(entry.marker, zScale);
      //setMarkerBearing(entry.marker, bearing);
    } else {
      setMarkerScale(entry.marker, zScale);
    }

    entry.marker.setZIndexOffset(zIdx);

    const tt = entry.marker.getTooltip();
    if (tt) tt.setContent(tip);
  }
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

