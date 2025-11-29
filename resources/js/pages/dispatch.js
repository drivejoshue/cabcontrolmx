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
  stand:   '/images/marker-parqueo5.png',
   stop:   '/images/stopride.png',
};

/* =========================
 *  HELPERs
 * ========================= */



// ===== DEBUG RIDE LIST/CARDS =====
window.__DISPATCH_DEBUG__ = false; // ponlo en false para apagar

function escapeHtml(s){
  return String(s)
    .replace(/&/g,"&amp;").replace(/</g,"&lt;")
    .replace(/>/g,"&gt;").replace(/"/g,"&quot;")
    .replace(/'/g,"&#039;");
}

function debugBrief(ride){
  return {
    id: ride.id,
    hasStopsProp: Array.isArray(ride.stops),
    stopsLen: Array.isArray(ride.stops) ? ride.stops.length : null,
    typeofStopsJson: typeof ride.stops_json,
    stopsJsonIsArray: Array.isArray(ride.stops_json),
    stopsJsonStrPrefix: (typeof ride.stops_json === 'string' ? ride.stops_json.slice(0,80) : null),
    stops_count: ride.stops_count ?? null,
    stop_index: ride.stop_index ?? null,
  };
}

function logListDebug(list, tag='[dispatch] list'){
  if (!window.__DISPATCH_DEBUG__) return;
  try {
    console.groupCollapsed(tag, 'n=', list.length);
    console.table(list.map(debugBrief));
    const withStops = list.find(r => (r.stops_count ?? 0) > 0 || (Array.isArray(r.stops) && r.stops.length));
    console.log('sample ride (con paradas si existe):', withStops || list[0]);
    console.groupEnd();
  } catch(e){ console.warn('debug error', e); }
}




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
let stop1Marker=null, stop2Marker=null; // NUEVO
let acStop1=null, acStop2=null;         // NUEVO


const IconOrigin = L.icon({ iconUrl:TENANT_ICONS.origin, iconSize:[30,30], iconAnchor:[15,30], popupAnchor:[0,-26] });
const IconDest   = L.icon({ iconUrl:TENANT_ICONS.dest,   iconSize:[30,30], iconAnchor:[15,30], popupAnchor:[0,-26] });
const IconStand  = L.icon({ iconUrl:TENANT_ICONS.stand,  iconSize:[28,28], iconAnchor:[14,28], popupAnchor:[0,-24] });
const IconStop   = L.icon({ iconUrl:TENANT_ICONS.stop,  iconSize:[28,28], iconAnchor:[14,28], popupAnchor:[0,-24] }); // NUEVO

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

function getStops(){
  const s1=[parseFloat(qs('#stop1Lat')?.value), parseFloat(qs('#stop1Lng')?.value)];
  const s2=[parseFloat(qs('#stop2Lat')?.value), parseFloat(qs('#stop2Lng')?.value)];
  const arr=[];
  if (Number.isFinite(s1[0]) && Number.isFinite(s1[1])) arr.push(s1);
  if (Number.isFinite(s2[0]) && Number.isFinite(s2[1])) arr.push(s2);
  return arr;
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

        const stops = getStops(); // NUEVO
        const waypts = stops.map(s => ({ location:{lat:s[0], lng:s[1]} }));

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
            }, waypoints: waypts.length ? waypts : undefined 
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
          await drawRouteWithOSRM(a,b,stops,{quiet:true});
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
    await drawRouteWithOSRM(a,b,stops,{quiet:true});

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

function setStop1(latlng, label){
  if (stop1Marker) stop1Marker.remove();
  stop1Marker = L.marker(latlng, { draggable:true, icon:IconStop, zIndexOffset:900 })
    .addTo(map).bindTooltip('Parada 1');

  qs('#stop1Lat').value = latlng[0]; qs('#stop1Lng').value = latlng[1];
  if (label) qs('#inStop1').value = label; else reverseGeocode(latlng, '#inStop1');

  stop1Marker.on('dragstart', ()=> map.dragging.disable());
  stop1Marker.on('dragend', (e)=>{
    map.dragging.enable();
    const ll = e.target.getLatLng();
    qs('#stop1Lat').value = ll.lat; qs('#stop1Lng').value = ll.lng;
    reverseGeocode([ll.lat,ll.lng], '#inStop1');
    drawRoute({quiet:true}); autoQuoteIfReady();
  });

  // Al tener stop1, permitimos mostrar stop2
  document.getElementById('stop2Row')?.style.setProperty('display','');
  drawRoute({quiet:true}); autoQuoteIfReady();
}

function setStop2(latlng, label){
  // Solo si existe stop1
  if (!Number.isFinite(parseFloat(qs('#stop1Lat')?.value))) return;

  if (stop2Marker) stop2Marker.remove();
  stop2Marker = L.marker(latlng, { draggable:true, icon:IconStop, zIndexOffset:900 })
    .addTo(map).bindTooltip('Parada 2');

  qs('#stop2Lat').value = latlng[0]; qs('#stop2Lng').value = latlng[1];
  if (label) qs('#inStop2').value = label; else reverseGeocode(latlng, '#inStop2');

  stop2Marker.on('dragstart', ()=> map.dragging.disable());
  stop2Marker.on('dragend', (e)=>{
    map.dragging.enable();
    const ll = e.target.getLatLng();
    qs('#stop2Lat').value = ll.lat; qs('#stop2Lng').value = ll.lng;
    reverseGeocode([ll.lat,ll.lng], '#inStop2');
    drawRoute({quiet:true}); autoQuoteIfReady();
  });

  drawRoute({quiet:true}); autoQuoteIfReady();
}


// Mostrar/ocultar fila de schedule
qs('#when-now')?.addEventListener('change', ()=> {
  const row = qs('#scheduleRow'); if (!row) return;
  row.style.display = qs('#when-now').checked ? 'none' : '';
});
qs('#when-later')?.addEventListener('change', ()=> {
  const row = qs('#scheduleRow'); if (!row) return;
  row.style.display = qs('#when-later').checked ? '' : 'none';
});

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
    const stops = getStops();
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
        stops: stops.map(s => ({lat:s[0], lng:s[1]})), 
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



async function drawRouteWithOSRM(a, b, stops = [], { quiet = false } = {}) {
  // a/b son [lat,lng]; stops es [[lat,lng], ...]
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

  // limpia línea previa
  try { window.__routeLine?.remove(); } catch {}
  window.__routeLine = L.polyline(latlngs, routeStyle()).addTo(layerRoute);

  if (!quiet) {
    const rs = qs('#routeSummary');
    if (rs) {
      const km = (route.distance/1000).toFixed(1)+' km';
      const min = Math.round(route.duration/60)+' min';
      rs.innerText = `Ruta: ${km} · ${min}`;
    }
  }
  map.fitBounds(window.__routeLine.getBounds(), { padding:[20,20] });
  return { distance_m: route.distance|0, duration_s: route.duration|0, polyline: poly };
}


async function refreshDispatch(){
  try{
    const [a,b] = await Promise.all([
      fetch('/api/dispatch/active',{headers:{Accept:'application/json'}}),
      fetch('/api/dispatch/drivers',{headers:{Accept:'application/json'}}),
    ]);

      const data    = a.ok ? await a.json() : (console.error('[active]', await a.text()), {});
    const drivers = b.ok ? await b.json() : (console.error('[drivers]', await b.text()), []);

    const rides  = Array.isArray(data.rides)  ? data.rides  : [];
    const queues = Array.isArray(data.queues) ? data.queues : [];
    const driverList = Array.isArray(drivers) ? drivers     : [];

    // caches globales
    window._lastActiveRides = rides;
    window._ridesIndex      = new Map(rides.map(r => [r.id, r]));
    window._lastDrivers     = driverList;
    window._lastQueues      = queues;

    // Paneles
    renderQueues(queues);
    renderRightNowCards(rides);         // solo requested/offered
    renderRightScheduledCards(rides);   // solo scheduled
    renderDockActive(rides);            // solo activos
    renderDrivers(driverList);
    await updateSuggestedRoutes(rides);

  }catch(e){
    console.warn('refreshDispatch error', e);
  }
}
function normalizeStops(ride){
  if (Array.isArray(ride.stops)) return ride.stops;
  if (ride.stops_json) {
    try {
      const a = JSON.parse(ride.stops_json);
      return Array.isArray(a) ? a : [];
    } catch {}
  }
  return [];
}

let pollTimer=null;
function startPolling(){
  clearInterval(pollTimer);
  pollTimer = setInterval(refreshDispatch, 3000);
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



async function highlightRideOnMap(ride) {
  try {
    // 0) Asegura capas/estado
    if (!window.layerRoute && map) {
      layerRoute = L.layerGroup().addTo(map);
      window.layerRoute = layerRoute;
    }

    // 1) Limpieza visual previa
    if (layerRoute?.clearLayers) layerRoute.clearLayers();
    if (fromMarker) { try { fromMarker.remove(); } catch {} fromMarker = null; }
    if (toMarker)   { try { toMarker.remove();   } catch {} toMarker   = null; }

    if (Array.isArray(window._stopMarkers)) {
      window._stopMarkers.forEach(m => { try { m.remove(); } catch {} });
    }
    window._stopMarkers = [];

    // 2) Datos base
    const from = (Number.isFinite(+ride.origin_lat) && Number.isFinite(+ride.origin_lng))
      ? { lat: +ride.origin_lat, lng: +ride.origin_lng } : null;
    const to   = (Number.isFinite(+ride.dest_lat)   && Number.isFinite(+ride.dest_lng))
      ? { lat: +ride.dest_lat,   lng: +ride.dest_lng   } : null;

    let stops = normalizeStops(ride); // ya tenías este helper

    // 3) Marcadores O / STOPS / D
    if (from) {
      fromMarker = L.marker([from.lat, from.lng], { 
        icon: (typeof IconOrigin !== 'undefined' ? IconOrigin : undefined) 
      }).addTo(layerRoute);
    }

    stops.forEach((s, i) => {
      const lt = +s.lat, lg = +s.lng;
      if (!Number.isFinite(lt) || !Number.isFinite(lg)) return;
      const mk = L.marker([lt, lg], { 
        icon: (typeof IconStop !== 'undefined' ? IconStop : undefined), 
        title: `Parada ${i + 1}` 
      });
      mk.addTo(layerRoute);
      window._stopMarkers.push(mk);
    });

    if (to) {
      toMarker = L.marker([to.lat, to.lng], { 
        icon: (typeof IconDest !== 'undefined' ? IconDest : undefined) 
      }).addTo(layerRoute);
    }

    // 4) Ruta principal (igual que antes, pero ordenado)
    let latlngs = null;

    // 4.1) Usa polyline guardada si existe
    if (ride.route_polyline) {
      try {
        const arr = decodePolyline(ride.route_polyline) || [];
        if (Array.isArray(arr) && arr.length >= 2) latlngs = arr;
      } catch {}
    }

    // 4.2) Si no hay polyline, pide al backend INCLUYENDO STOPS
    if ((!latlngs || !latlngs.length) && from && to) {
      try {
        const body = { from, to, mode: 'driving' };
        if (Array.isArray(stops) && stops.length > 0) body.stops = stops;

        const r = await fetch('/api/geo/route', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(body)
        });

        if (r.ok) {
          const j = await r.json();
          if (j?.polyline) {
            latlngs = decodePolyline(j.polyline);
          } else if (Array.isArray(j?.points)) {
            latlngs = j.points.map(p => [ +p[0], +p[1] ]);
          }
        }
      } catch {
        // sigue al fallback
      }
    }

    // 4.3) Fallback final: O -> stops -> D
    if ((!latlngs || !latlngs.length) && from && to) {
      const seq = [from, ...stops, to];
      latlngs = seq
        .filter(p => Number.isFinite(+p.lat) && Number.isFinite(+p.lng))
        .map(p => [ +p.lat, +p.lng ]);
    }

    if (latlngs && latlngs.length >= 2) {
      const pl = L.polyline(latlngs, { 
        className: 'cc-route', 
        weight: 5, 
        opacity: 0.9,
        color: isDarkMode() ? '#943DD4' : '#0717F0'
      });
      pl.addTo(layerRoute);
      window.__routeLine = pl;
    }

    // 5) Incluir al DRIVER si el ride está activo
    let driverLL = null;
    let driverMarker = null;

    if (ride.driver_id && typeof driverPins !== 'undefined') {
      const pin = driverPins.get(Number(ride.driver_id));
      if (pin?.marker && typeof pin.marker.getLatLng === 'function') {
        driverMarker = pin.marker;
        driverLL = driverMarker.getLatLng();

        // resaltamos un poco el pin del driver
        try {
          // reset anterior
          if (window.__lastHighlightedDriver && window.__lastHighlightedDriver !== driverMarker) {
            setMarkerScale(window.__lastHighlightedDriver, scaleForZoom(map.getZoom()));
          }
          setMarkerScale(driverMarker, 1.35);
          driverMarker.setZIndexOffset(1200);
          window.__lastHighlightedDriver = driverMarker;
        } catch {}
      }
    }

    // 6) Fit bounds según estado (driver vs origen/destino)
    const bounds = [];
    const push = ll => {
      if (!ll) return;
      if (Array.isArray(ll)) {
        bounds.push(ll);
      } else if (typeof ll.lat === 'number' && typeof ll.lng === 'number') {
        bounds.push([ll.lat, ll.lng]);
      }
    };

    const st = (typeof _canonStatus === 'function')
      ? _canonStatus(ride.status)
      : String(ride.status || '').toLowerCase();

    if (driverLL && (st === 'accepted' || st === 'assigned' || st === 'en_route' || st === 'arrived')) {
      // Driver + pickup
      push(driverLL);
      if (from) push([from.lat, from.lng]);
    } else if (driverLL && st === 'on_board') {
      // Driver + destino
      push(driverLL);
      if (to) push([to.lat, to.lng]);
    } else {
      // Modo viejo: O + STOPS + D
      if (from) push([from.lat, from.lng]);
      stops.forEach(s => {
        const lt = +s.lat, lg = +s.lng;
        if (Number.isFinite(lt) && Number.isFinite(lg)) push([lt, lg]);
      });
      if (to) push([to.lat, to.lng]);
    }

    // Si por alguna razón no se armaron bounds pero tenemos driver
    if (!bounds.length && driverLL) {
      push(driverLL);
    }

    if (bounds.length === 1) {
      map.setView(bounds[0], Math.max(map.getZoom(), 16));
    } else if (bounds.length > 1) {
      map.fitBounds(bounds, { padding: [40, 40] });
    }
  } catch (e) {
    console.warn('[map] highlightRideOnMap error', e);
  }
}





// ===== CANCELACIÓN CON SWEETALERT2 (event delegation + no duplicar handler) =====
// Bind único
// ====== REFERENCIAS GLOBALES (si aún no existen) ======
window.__originMarker = window.__originMarker || null;
window.__destMarker   = window.__destMarker   || null;
window.__routeLine    = window.__routeLine    || null;
window.__stopMarkers = window.__stopMarkers || [];

// ====== LIMPIAR ORIGEN/DESTINO + RUTA ======
function clearOriginDest() {
  try {
    const layer = (typeof layerSuggested !== 'undefined') ? layerSuggested : layerRoute;

    if (window.__originMarker) {
      try { layer.removeLayer(window.__originMarker); } catch {}
      window.__originMarker = null;
    }
    if (window.__destMarker) {
      try { layer.removeLayer(window.__destMarker); } catch {}
      window.__destMarker = null;
    }

  if (stop1Marker) { stop1Marker.remove(); stop1Marker = null; }
    if (stop2Marker) { stop2Marker.remove(); stop2Marker = null; }
    if (window.__stopMarkers) {
      window.__stopMarkers.forEach(m => { try { m.remove(); } catch {} });
      window.__stopMarkers = [];
    }
   

    if (window.__stopMarkers) {
    
    window.__stopMarkers.forEach(marker => { try { marker.remove(); } catch {} });
    window.__stopMarkers = [];
    }

    // además, borra cualquier polyline con className cc-route (por si no guardamos referencia)
    try {
      const toRemove = [];
      layerRoute.eachLayer(l => {
        try {
          if (l instanceof L.Polyline) {
            const cls = String(l.options?.className || '');
            if (cls.includes('cc-route')) toRemove.push(l);
          }
        } catch {}
      });
      toRemove.forEach(l => { try { layerRoute.removeLayer(l); } catch {} });
      // al final de clearOriginDest():

    } catch {}
  } catch (err) {
    console.warn('[map] clearOriginDest error', err);
  }
}

// ====== LIMPIAR PREVIEWS/LÍNEAS SUGERIDAS (asignación) ======
// --- Utils de limpieza ---
// Limpia solo lo del viaje actual (ruta principal + markers origen/destino + previews de asignación)
// ===== LIMPIEZA TOTAL DE GRAFICOS DE VIAJE (rutas + origen/destino + previews) =====
function clearAssignArtifacts() {
  try {
    // marker de pickup del panel / preview de asignación
    if (typeof _assignPickupMarker !== 'undefined' && _assignPickupMarker) {
      const lyr = (typeof layerSuggested !== 'undefined') ? layerSuggested : layerRoute;
      try { lyr.removeLayer(_assignPickupMarker); } catch {}
      _assignPickupMarker = null;
    }
    if (typeof _assignPreviewLine !== 'undefined' && _assignPreviewLine) {
      try { layerRoute.removeLayer(_assignPreviewLine); } catch {}
      _assignPreviewLine = null;
    }
  } catch {}

  // líneas cc-suggested / cc-preview (del flujo de asignación)
  try {
    const toRemove = [];
    layerRoute.eachLayer(l => {
      try {
        if (l instanceof L.Polyline) {
          const cls = String(l.options?.className || '');
          if (cls.includes('cc-suggested') || cls.includes('cc-preview')) toRemove.push(l);
        }
      } catch {}
    });
    toRemove.forEach(l => { try { layerRoute.removeLayer(l); } catch {} });
  } catch {}
}

function clearTripGraphicsHard() {
  // 1) previews de asignación
  try {
    if (typeof clearAllPreviews === 'function') clearAllPreviews();
    if (typeof clearSuggestedLines === 'function') clearSuggestedLines();
  } catch {}
  clearAssignArtifacts();

  // 2) ruta principal del formulario
  try { layerRoute?.clearLayers(); } catch {}

  // 3) pines de origen/destino del formulario
  try { if (window.fromMarker) { fromMarker.remove(); fromMarker=null; } } catch {}
  try { if (window.toMarker)   { toMarker.remove();   toMarker=null;   } } catch {}

  // 4) por si alguien usó estas refs globales
  try {
    const lyr = (typeof layerSuggested !== 'undefined') ? layerSuggested : layerRoute;
    if (window.__originMarker) { lyr.removeLayer(window.__originMarker); window.__originMarker = null; }
    if (window.__destMarker)   { lyr.removeLayer(window.__destMarker);   window.__destMarker   = null; }
    if (window.__routeLine)    { layerRoute.removeLayer(window.__routeLine); window.__routeLine = null; }
  } catch {}
}

// Borra también lo dibujado por highlightRideOnMap(), si pasas rideId
function removeRideGraphics(rideId) {
  try {
    if (window.rideMarkers && rideMarkers.has(rideId)) {
      const g = rideMarkers.get(rideId);
      try { g.remove(); } catch {}
      rideMarkers.delete(rideId);
    }
  } catch {}
}




if (!window.__cancelHandlerBound) {
  document.addEventListener('click', async (e) => {
    // Soporta ambos: data-action="cancel-ride" o clase .btn-cancel
    const btn = e.target.closest('[data-action="cancel-ride"], .btn-cancel');
    if (!btn) return;
    e.preventDefault();

    // Obtén rideId desde el botón o desde el contenedor cercano
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

    // Confirmación
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

    // (Opcional) Motivo
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
      if (reasonId === undefined) return; // canceló
      const item = window.cancelReasons.find(r => String(r.id) === String(reasonId));
      chosenReason = item ? item.label : null;
    }

    // UI: evitar doble click
    const prevText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Cancelando…';

    try {
      // Llamada AJAX sin refrescar la página
      const res = await fetch(`/api/dispatch/rides/${rideId}/cancel`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
          'X-Tenant-ID': (window.currentTenantId || document.querySelector('meta[name="tenant-id"]')?.content || '1')
        },
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
  try {
      ['inFrom','inTo','pass-name','pass-phone','pass-account','ride-notes','fareAmount'].forEach(id=>{ const el = qs('#'+id); if (el) el.value=''; });
      ['fromLat','fromLng','toLat','toLng'].forEach(id=>{ const el = qs('#'+id); if (el) el.value=''; });
      layerRoute.clearLayers();
      if (fromMarker){ fromMarker.remove(); fromMarker=null; }
      if (toMarker){   toMarker.remove();   toMarker=null;  }
      if (stop1Marker) { stop1Marker.remove(); stop1Marker = null; }
      if (stop2Marker) { stop2Marker.remove(); stop2Marker = null; }
      const s1Lat = qs('#stop1Lat'), s1Lng = qs('#stop1Lng'), s2Lat = qs('#stop2Lat'), s2Lng = qs('#stop2Lng');
      const inS1 = qs('#inStop1'), inS2 = qs('#inStop2');
      const row1 = qs('#stop1Row'), row2 = qs('#stop2Row');
      if (s1Lat) s1Lat.value=''; if (s1Lng) s1Lng.value='';
      if (s2Lat) s2Lat.value=''; if (s2Lng) s2Lng.value='';
      if (inS1) inS1.value='';   if (inS2) inS2.value='';
      if (row1) row1.style.display='none';
      if (row2) row2.style.display='none';
      const rs = document.getElementById('routeSummary');
      if (rs) rs.innerText = 'Ruta: — · Zona: — · Cuando: ahora';
      resetWhenNow();
    } catch {}
      await Swal.fire({ icon: 'success', title: 'Cancelado', timer: 900, showConfirmButton: false });
    
     
      // ✅ Sin refrescar toda la página:
      // 1) saca la card del DOM si existe
      const card = btn.closest('.cc-ride-card');
      if (card) card.remove();

      // 2) baja contador del badge
      const badge = document.getElementById('badgeActivos');
      if (badge) {
        const n = Math.max(0, (parseInt(badge.textContent,10) || 0) - 1);
        badge.textContent = n;
      }

    

      // 4) refresca SOLO el panel de activos si tienes esa función
      if (typeof renderActiveRides === 'function') {
        await renderActiveRides();
      } else if (typeof refreshDispatch === 'function') {
        await refreshDispatch();
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


async function focusRideOnMap(rideId){
  // intenta cache primero
  const cached = window._ridesIndex?.get?.(rideId);
  if (cached) {
    // Asegurar que el ride del cache tiene stops
    const rideWithStops = await hydrateRideStops(cached);
    return highlightRideOnMap(rideWithStops);
  }

  // ruta correcta del show:
  const r = await fetch(`/api/rides/${rideId}`, { headers:{ Accept:'application/json' } });
  if (!r.ok) {
    console.error('GET /api/rides/{id} →', r.status, await r.text().catch(()=>'')); 
    alert('No se pudo cargar el viaje.');
    return;
  }
  const ride = await r.json();
  
  // Asegurar stops
  const rideWithStops = await hydrateRideStops(ride);
  
  if (window.__DISPATCH_DEBUG__) {
    console.log('[focusRideOnMap] GET /api/rides/'+rideId, debugBrief(rideWithStops), rideWithStops);
  }
  return highlightRideOnMap(rideWithStops);
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

function isScheduledStatus(ride){
  const st = _norm(ride?.status);
  // además de status, acepta que venga solamente el campo de fecha
  const hasSchedField = !!(ride?.scheduled_for || ride?.scheduledFor ||
                           ride?.scheduled_at  || ride?.scheduledAt);
  return st === 'scheduled' || hasSchedField;
}

function shouldHideRideCard(ride) {
  const st = String(ride.status || '').toLowerCase();
  return st === 'completed' || st === 'canceled';
}

// Intenta tomar resumen de ofertas (si tu API lo trae en el ride)
function deriveRideChannel(ride) {
  const raw = String(
    ride.requested_channel ||
    ride.channel ||
    ride.request_source ||
    ''
  ).toLowerCase().trim();

  if (!raw) {
    return { code: 'panel', label: 'Panel' };
  }

  if (['passenger_app', 'passenger', 'app', 'app_pasajero'].includes(raw)) {
    return { code: 'passenger', label: 'App pasajero' };
  }

  if (['driver_app', 'driver', 'app_conductor'].includes(raw)) {
    return { code: 'driver', label: 'App conductor' };
  }

  if (['central', 'dispatcher', 'panel', 'web'].includes(raw)) {
    return { code: 'panel', label: 'Central' };
  }

  if (['phone', 'telefono', 'callcenter', 'call_center'].includes(raw)) {
    return { code: 'phone', label: 'Teléfono' };
  }

  if (['corp', 'corporate', 'empresa', 'business'].includes(raw)) {
    return { code: 'corp', label: 'Corporativo' };
  }

  // fallback genérico
  return {
    code: raw,
    label: raw.charAt(0).toUpperCase() + raw.slice(1)
  };
}


function summarizeOffers(ride) {
  const offers = Array.isArray(ride.offers) ? ride.offers : [];
  const anyAccepted = offers.some(o => o.status === 'accepted');
  const anyOffered  = offers.some(o => o.status === 'offered');
  const rejectedBy  = offers.filter(o => o.status === 'rejected')
                            .map(o => o.driver_name || `#${o.driver_id}`);
  return { offers, anyAccepted, anyOffered, rejectedBy };
}

function deriveRideUi(ride) {
  const rawStatus = String(ride.status || '').toLowerCase().trim();

  // Canonizamos el estado para que onboard/enroute → on_board/en_route
  const status = (typeof _canonStatus === 'function')
    ? _canonStatus(rawStatus)
    : (rawStatus || 'unknown');

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
      showRelease = false;
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

  // 🚫 Para rides que vienen de Passenger:
  //   - NO mostramos Asignar ni Re-ofertar (lo maneja el flujo de bidding/autodespacho)
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



// === LEE TAL CUAL DE LA DB (YYYY-MM-DD HH:mm:ss o YYYY-MM-DDTHH:mm:ss) ===
function extractPartsFromDbTs(s) {
  if (!s) return null;
  // normaliza separador
  const t = s.replace('T', ' ');
  // yyyy-mm-dd hh:mm(:ss)
  const m = t.match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})(?::(\d{2}))?$/);
  if (!m) return null;
  return {
    y: +m[1], mo: +m[2], d: +m[3],
    H: +m[4], M: +m[5], S: +(m[6] ?? 0),
    raw: t
  };
}

function fmtHM12_fromDb(s) {
  const p = extractPartsFromDbTs(s);
  if (!p) return '—';
  let h = p.H % 12; if (h === 0) h = 12;
  const mm = String(p.M).padStart(2, '0');
  const ampm = p.H < 12 ? 'a.m.' : 'p.m.';
  return `${h}:${mm} ${ampm}`;
}

function fmtShortDay_fromDb(s) {
  const p = extractPartsFromDbTs(s);
  if (!p) return '';
  // dd Mon (abreviado en español a mano para no tocar zonas)
  const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
  return `${String(p.d).padStart(2,'0')} ${meses[p.mo-1]}`;
}

// Muestra hora y, si no es “hoy” según la cadena misma, añade dd-Mon
function fmtWhen_db(s) {
  const p = extractPartsFromDbTs(s);
  if (!p) return '—';
  
  // SIEMPRE retornar hora + fecha
  return `${fmtHM12_fromDb(s)} · ${fmtShortDay_fromDb(s)}`;
}



// === Ride Card: estilos pro (se insertan una sola vez) ======================
(function injectRideCardStyles(){
  if (window.__RIDE_CARD_STYLES__) return;
  window.__RIDE_CARD_STYLES__ = true;

  const css = `
  /* ===== Theme-aware tokens (toman los de Bootstrap 5.3) ===== */
  :root{
    --cc-card-bg: var(--bs-card-bg, var(--bs-body-bg));
    --cc-text: var(--bs-body-color);
    --cc-muted: var(--bs-secondary-color);
    --cc-border: var(--bs-border-color);
    --cc-soft-bg: var(--bs-tertiary-bg);
  }

  /* ===== Card ===== */
  .cc-ride-card{
    background: var(--cc-card-bg);
    color: var(--cc-text);
    border: 1px solid var(--cc-border);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(16,24,40,.04);
  }
  .cc-ride-card.is-scheduled{ border-left: 4px solid var(--bs-danger); }
  .cc-ride-card .card-body{ padding:14px 16px; }

  .cc-ride-header{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
  .cc-ride-title{ font-weight:700; font-size:14px; letter-spacing:.2px; }

  .cc-ride-badge .badge{ font-weight:600; padding:.35rem .5rem; border-radius:8px; }

  .cc-stats{ text-align:right; }
  .cc-amount{ font-weight:800; font-size:16px; line-height:1; }
  .cc-meta{ font-size:12px; color: var(--cc-muted); margin-top:4px; }
  .cc-divider{ height:1px; background: var(--cc-border); margin:10px 0; }

  /* ===== Itinerario (línea vertical + pines) ===== */
  .cc-legs{ position:relative; margin:0; padding-left:22px; list-style:none; }
  .cc-legs::before{
    content:""; position:absolute; left:7px; top:10px; bottom:10px;
    width:2px; background: var(--cc-border); border-radius:1px;
  }
  .cc-leg{ display:flex; align-items:flex-start; gap:8px; margin:6px 0; }
  .cc-pin{ width:12px; height:12px; border-radius:50%; margin-top:2px; flex:0 0 12px; }
  .cc-pin--o{ background:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.18); }
  .cc-pin--s{ background:#9aa0a6; }
  .cc-pin--d{ background:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.18); }

  .cc-leg .cc-label{ font-size:13px; color: var(--cc-text); }
  .cc-leg .cc-sub{ font-size:12px; color: var(--cc-muted); }

  .cc-stops-title{ font-size:12px; color: var(--bs-primary); font-weight:700; margin:6px 0 4px; }

  .cc-chip{
    font-size:12px; border-radius:999px; padding:.25rem .5rem;
    background: var(--cc-soft-bg); color: var(--cc-text); display:inline-block;
    border: 1px solid var(--cc-border);
  }

  .cc-actions .btn{ border-radius:10px; }
  .cc-actions .btn-outline-secondary{
    color: var(--cc-text);
    border-color: var(--cc-border);
    background: transparent;
  }

  /* ===== Light mode override: forzar fondo blanco en modo claro ===== */
  html:not([data-theme="dark"]) .cc-ride-card{
    background: #ffffff !important;          /* <- blanco real */
    border-color: #e9eef5;                   /* borde suave */
  }
  html:not([data-theme="dark"]) .cc-divider{ background: #eef2f7; }
  html:not([data-theme="dark"]) .cc-chip{
    background: #f3f4f6;
    border-color: #e9eef5;
  }

  /* ===== Dark mode overrides ===== */
  html[data-theme="dark"] .cc-ride-card{ box-shadow: 0 10px 24px rgba(0,0,0,.45); }
  html[data-theme="dark"] .cc-legs::before{ background: var(--cc-border); }
  html[data-theme="dark"] .cc-divider{ background: var(--cc-border); }
  html[data-theme="dark"] .cc-chip{ background: var(--cc-soft-bg); border-color: var(--cc-border); }

  /* Fallback si usas prefers-color-scheme sin data-theme */
  @media (prefers-color-scheme: dark){
    html:not([data-theme]) .cc-ride-card{ box-shadow: 0 10px 24px rgba(0,0,0,.45); }
    html:not([data-theme]) .cc-legs::before{ background: var(--cc-border); }
    html:not([data-theme]) .cc-divider{ background: var(--cc-border); }
    html:not([data-theme]) .cc-chip{ background: var(--cc-soft-bg); }
  }
  `;

  const style = document.createElement('style');
  style.id = 'cc-ride-card-styles';
  style.textContent = css;
  document.head.appendChild(style);
})();



function renderRideCard(ride) {
  if (shouldHideRideCard(ride)) return '';

  const ui = deriveRideUi(ride);
  const ch = ui.channel || deriveRideChannel(ride);

  // --- helpers ---
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => (
    {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]
  ));
  const fmtC = (v) => Number.isFinite(+v) ? (+v).toFixed(5) : '—';
  const parseStops = () => {
    if (Array.isArray(ride.stops)) return ride.stops;
    if (Array.isArray(ride.stops_json)) return ride.stops_json; // cast Eloquent
    if (typeof ride.stops_json === 'string' && ride.stops_json.trim()!=='') {
      try { const a = JSON.parse(ride.stops_json); return Array.isArray(a) ? a : []; } catch {}
    }
    return [];
  };

  // --- números/top-right ---
  const km  = (ride.km != null && !isNaN(ride.km)) ? Number(ride.km).toFixed(1)
            : (ride.distance_m ? (ride.distance_m/1000).toFixed(1) : '-');
  const min = (ride.min != null) ? ride.min
            : (ride.duration_s ? Math.round(ride.duration_s/60) : '-');
  const amt = (ride.quoted_amount != null) ? Number(ride.quoted_amount) : Number(ride.amount ?? NaN);
  const amtTxt = Number.isFinite(amt) ? `$${amt.toFixed(2)}` : '—';

  const stops = parseStops();
  const stopsCount = stops.length || (ride.stops_count ? Number(ride.stops_count) : 0);

  // --- labels O/D ---
  const passName  = ride.passenger_name || '—';
  const passPhone = ride.passenger_phone || '';
  const originLbl = ride.origin_label
                 || (Number.isFinite(ride.origin_lat) ? `${fmtC(ride.origin_lat)}, ${fmtC(ride.origin_lng)}` : '—');
  const destLbl   = ride.dest_label
                 || (Number.isFinite(ride.dest_lat)   ? `${fmtC(ride.dest_lat)}, ${fmtC(ride.dest_lng)}`     : '—');
const channelBadge = ch
  ? `<span class="badge bg-light text-secondary border ms-2">${esc(ch.label)}</span>`
  : '';  // 👈 parte “false” del ternario + termina la expresión

  const scheduled    = isScheduledStatus(ride);
  const schedRaw     = scheduled ? (ride.scheduled_for || ride.scheduled_for_fmt) : null;
  const requestedRaw = (ride.requested_at || ride.created_at) || null;
  const schedTxt     = scheduled ? fmtWhen_db(schedRaw) : '';
  const requestedTxt = requestedRaw ? fmtWhen_db(requestedRaw) : '—';

  const stateBadge = scheduled
    ? `<span class="badge bg-danger-subtle text-danger border border-danger">PROGRAMADO</span>`
    : ui.badge;


  // --- itinerary (O -> stops -> D) ---
  const stopsTitle = stopsCount ? `<div class="cc-stops-title small text-muted fw-semibold mt-2 mb-1">Paradas (${stopsCount})</div>` : '';
  const stopItems = stops.map((s,i) => {
    const txt = (s && typeof s.label === 'string' && s.label.trim()!=='')
      ? esc(s.label.trim())
      : `${fmtC(s.lat)}, ${fmtC(s.lng)}`;
    const title = `S${i+1}: ${fmtC(s.lat)}, ${fmtC(s.lng)}`;
    return `<li class="cc-leg" title="${esc(title)}">
              <span class="cc-pin cc-pin--s"></span>
              <div class="small">${txt}</div>
            </li>`;
  }).join('');

  // --- debug toggle (apagado por default) ---
  const showDebug = !!window.__DISPATCH_DEBUG__;
  const debugBlock = showDebug ? `
    <details class="mt-2"><summary class="text-muted small">debug</summary>
      <pre class="small text-muted" style="white-space:pre-wrap;max-height:180px;overflow:auto">${
        esc(JSON.stringify({
          id: ride.id,
          stops_count: ride.stops_count,
          stop_index: ride.stop_index,
          stops: ride.stops,
          stops_json: ride.stops_json
        }, null, 2))
      }</pre>
    </details>
  ` : '';

  // --- render ---
  return `
  <div class="card cc-ride-card ${scheduled ? 'is-scheduled' : ''} mb-2 border-0 shadow-sm" data-ride-id="${ride.id}">
    <div class="card-body p-3">
      <div class="cc-ride-header d-flex justify-content-between align-items-start mb-2">
        <div class="d-flex align-items-center gap-2">
          <div class="cc-ride-title fw-bold text-primary">#${ride.id}</div>
          <div class="cc-ride-badge">${stateBadge}</div>
        </div>
        <div class="cc-stats text-end">
          <div class="cc-amount fw-bold fs-5 text-success">${amtTxt}</div>
          <div class="cc-meta small text-muted">${km} km · ${min} min</div>
        </div>
      </div>

         <!-- Información del pasajero + canal de origen -->
      <div class="cc-passenger-info mb-2 p-2 bg-light rounded small d-flex justify-content-between align-items-center">
        <div>
          <i class="bi bi-person me-1"></i> 
          <span class="fw-semibold">${esc(passName)}</span>
          ${passPhone ? `<span class="text-muted ms-2"><i class="bi bi-telephone me-1"></i>${esc(passPhone)}</span>` : ''}
        </div>
        ${channelBadge}
      </div>

      <ul class="cc-legs list-unstyled mb-2">
        <li class="cc-leg d-flex align-items-start mb-1">
          <span class="cc-pin cc-pin--o me-2 mt-1"></span>
          <div class="flex-grow-1">
            <div class="small text-muted">Origen</div>
            <div class="fw-semibold small">${esc(originLbl)}</div>
          </div>
        </li>

        ${stopsTitle}
        ${stopItems}

        ${destLbl !== '—' ? `
        <li class="cc-leg d-flex align-items-start mb-1">
          <span class="cc-pin cc-pin--d me-2 mt-1"></span>
          <div class="flex-grow-1">
            <div class="small text-muted">Destino</div>
            <div class="fw-semibold small">${esc(destLbl)}</div>
          </div>
        </li>` : '' }
      </ul>

      <div class="cc-footer mt-2 pt-2 border-top">
        <div class="d-flex justify-content-between align-items-center">
          <div class="cc-meta small text-muted">
            <i class="bi bi-clock me-1"></i>
            ${scheduled ? `Prog: ${esc(schedTxt)}` : `Creado: ${esc(requestedTxt)}`}
          </div>
          ${stopsCount ? `<div class="cc-stops-badge small badge bg-secondary">${stopsCount} parada${stopsCount>1?'s':''}</div>` : ''}
        </div>
      </div>

      ${debugBlock}

      <div class="d-flex justify-content-end cc-actions mt-3">
        <div class="btn-group btn-group-sm">
          ${ui.showAssign  ? `<button class="btn btn-primary" data-act="assign">Asignar</button>` : ''}
          ${ui.showReoffer ? `<button class="btn btn-outline-primary" data-act="reoffer">Re-ofertar</button>` : ''}
          ${ui.showRelease ? `<button class="btn btn-warning" data-act="release">Liberar</button>` : ''}
          ${ui.showCancel  ? `<button class="btn btn-outline-danger btn-cancel" data-ride-id="${ride.id}">Cancelar</button>` : ''}
          <button class="btn btn-outline-secondary" data-act="view">Ver</button>
        </div>
      </div>
    </div>
  </div>`;
}



async function postJSON(url, body) {
  dbg('POST', url, body);
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
    console.error('POST FAIL', url, res.status, res.statusText, text);
    throw new Error(`HTTP ${res.status}: ${text || res.statusText}`);
  }
  const json = await res.json();
  dbg('POST OK', url, json);
  return json;
}


function dbg(...args){ try{ console.debug('[rides]', ...args); }catch{} }

async function onRideAction(e) {
  const btn  = e.target.closest('[data-act]');
  if (!btn) return;
  const wrap = btn.closest('[data-ride-id]');
  if (!wrap) return;

  const rideId = Number(wrap.dataset.rideId);
  const act    = btn.dataset.act;

  try {
    if (act === 'assign') {
      const ride = getRideById?.(rideId);
      openAssignFlow?.(ride || { id: rideId });
      return;
    }

    if (act === 'view') {
      await focusRideOnMap(rideId);
      return;
    }

    if (act === 'reoffer') {
      await postJSON('/api/dispatch/tick', { ride_id: rideId });
    }

    if (act === 'release') {
      // ⛔ Esta ruta no existe. Opciones:
      // 1) Oculta el botón hasta crear la API
      // 2) O crea la ruta /api/dispatch/release en el backend
      alert('Acción "Liberar" no disponible (endpoint faltante).');
      return;
    }

    if (act === 'cancel') {
      await postJSON(`/api/dispatch/rides/${rideId}/cancel`);
    }

    await refreshDispatch?.();
  } catch (err) {
    console.error(err);
    alert('Acción fallida: ' + (err.message || err));
  }
}
if (!window.__rides_actions_wired__) {
  document.addEventListener('click', onRideAction);
  window.__rides_actions_wired__ = true;
}




  
function renderAssignPanel(ride, candidates){
  _assignRide = ride; _assignSelected = null;

 try {
    // limpiar pickup anterior, esté donde esté
    if (window._assignPickupMarker) {
      try { (layerSuggested || layerRoute || map).removeLayer(window._assignPickupMarker); } catch {}
      try { map.removeLayer(window._assignPickupMarker); } catch {}
      window._assignPickupMarker = null;
    }

    // ✅ convertir a número antes de validar
    const lat = Number(ride?.origin_lat);
    const lng = Number(ride?.origin_lng);

    if (Number.isFinite(lat) && Number.isFinite(lng)) {
      const targetLayer = (typeof layerSuggested !== 'undefined' && map.hasLayer(layerSuggested))
        ? layerSuggested
        : (layerRoute || map);

      // icono (fallback si no existen los PNG)
      const ic = (typeof IconOrigin !== 'undefined') ? IconOrigin
               : (typeof IconDest   !== 'undefined') ? IconDest
               : L.divIcon({className:'cc-pin-fallback', html:'<div style="width:16px;height:16px;border-radius:50%;background:#2F6FED;border:2px solid #fff"></div>', iconSize:[16,16], iconAnchor:[8,8]});

      // ❗ pane: markerPane por encima de polylines
      window._assignPickupMarker = L.marker([lat, lng], {
        icon: ic,
        pane: (map.getPanes()?.markerPane ? 'markerPane' : undefined),
        zIndexOffset: 950,
        riseOnHover: true
      })
      .bindTooltip('Pasajero', { offset:[0,-26] })
      .addTo(targetLayer);
    }
  } catch (err) {
    console.warn('[assignPanel] no se pudo pintar pickup:', err);
  }

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
    try {
  if (window._assignPickupMarker) {
    try { (layerSuggested || layerRoute || map).removeLayer(window._assignPickupMarker); } catch {}
    try { map.removeLayer(window._assignPickupMarker); } catch {}
    window._assignPickupMarker = null;
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
   const lat = Number(ride?.origin_lat), lng = Number(ride?.origin_lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    alert('Este servicio no tiene origen válido.');
    return;
  }
  fetch(`/api/dispatch/nearby-drivers?lat=${ride.origin_lat}&lng=${ride.origin_lng}&km=3`,
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
        candidates = candidates.filter(c => c.distance_km <= 4);
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

// ==== Cargar settings desde Laravel (model/service) ====
async function loadDispatchSettings() {
  const tenantId = (typeof getTenantId === 'function' ? getTenantId() : (window.currentTenantId || ''));

  try {
    // mandamos tenant_id también en query para que el backend tenga fallback
    const r = await fetch(`/api/dispatch/settings?tenant_id=${encodeURIComponent(tenantId)}`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'X-Tenant-ID': tenantId || ''   // fallback si tu middleware usa header
      },
      credentials: 'same-origin'
    });

    // si no es 2xx, intenta leer texto para log
    if (!r.ok) {
      const txt = await r.text().catch(() => '');
      console.warn('[settings] HTTP ' + r.status, txt);
      throw new Error('HTTP ' + r.status);
    }

    const json = await r.json();
    window.ccDispatchSettings = {
      auto_dispatch_enabled:           !!json.auto_dispatch_enabled,
      auto_dispatch_delay_s:           Number(json.auto_dispatch_delay_s ?? 20),
      auto_dispatch_preview_n:         Number(json.auto_dispatch_preview_n ?? 12),
      auto_dispatch_preview_radius_km: Number(json.auto_dispatch_preview_radius_km ?? 5),
      offer_expires_sec:               Number(json.offer_expires_sec ?? 180),
      auto_assign_if_single:           !!json.auto_assign_if_single,
      allow_fare_bidding:              !!json.allow_fare_bidding,   // <- importante
    };
    console.debug('[settings] OK', window.ccDispatchSettings);
  } catch (e) {
    console.warn('[settings] error; defaults', e);
    window.ccDispatchSettings = window.ccDispatchSettings || {
      auto_dispatch_enabled: true,
      auto_dispatch_delay_s: 20,
      auto_dispatch_preview_n: 12,
      auto_dispatch_preview_radius_km: 5,
      offer_expires_sec: 180,
      auto_assign_if_single: false,
      allow_fare_bidding: false,
    };
  }
}
window.loadDispatchSettings = loadDispatchSettings;

// ==== Cancelar timers ====
// --- Auto-dispatch helpers ---
function cancelCompoundAutoDispatch(){
  if (!window.__compoundTimers) return;
  try {
    if (window.__compoundTimers.tEnd)     clearTimeout(window.__compoundTimers.tEnd);
    if (window.__compoundTimers.interval) clearInterval(window.__compoundTimers.interval);
  } catch {}
  window.__compoundTimers = null;
  try { hideBubble?.(); } catch {}
}

function startCompoundAutoDispatch(ride) {
  // limpia cualquier timer previo
  cancelCompoundAutoDispatch();

  const ds = window.ccDispatchSettings || {};
  const enabled = ds.auto_dispatch_enabled !== false;

  // delay configurable: usa auto_dispatch_delay_s o, si no viene, auto_delay_sec; default 20s
  const total = Number.isFinite(+ds.auto_dispatch_delay_s)
    ? Math.max(0, Math.floor(+ds.auto_dispatch_delay_s))
    : (Number.isFinite(+ds.auto_delay_sec) ? Math.max(0, Math.floor(+ds.auto_delay_sec)) : 20);

  // no continues si está deshabilitado o sin ride
  if (!enabled || !ride) return;

  // seguridad extra: no dispares si es programado
  if (typeof isScheduledStatus === 'function' && isScheduledStatus(ride)) return;
  if (!isScheduledStatus && ride?.scheduled_for) return;

  // feedback visual (opcional)
  try { showBubble?.('Servicio detectado…'); } catch {}

  window.__compoundTimers = window.__compoundTimers || {};

  // disparo final: POST /api/dispatch/tick con ride_id
  window.__compoundTimers.tEnd = setTimeout(async () => {
    try { hideBubble?.(); } catch {}
    try {
      const tenantId = (typeof getTenantId === 'function' ? getTenantId() : (window.currentTenantId || ''));
      const resp = await fetch('/api/dispatch/tick', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
          'X-Tenant-ID': tenantId
        },
        body: JSON.stringify({ ride_id: ride.id })
      });
      if (!resp.ok) {
        const txt = await resp.text().catch(()=> '');
        console.warn('[auto] tick 500:', txt);
      }
    } catch (e) {
      console.warn('[auto] tick error:', e);
    }
  }, total * 1000);

  // cuenta regresiva (opcional)
  let left = total;
  window.__compoundTimers.interval = setInterval(() => {
    left -= 1;
    if (left <= 0) { clearInterval(window.__compoundTimers.interval); return; }
    try { updateBubble?.(`Asignando en ${left}s`); } catch {}
  }, 1000);
}
window.startCompoundAutoDispatch = startCompoundAutoDispatch;
window.cancelCompoundAutoDispatch = cancelCompoundAutoDispatch;


//barra derecha lateral 

const _norm = s => String(s || '').toLowerCase().trim();

// Canoniza alias comunes (onboard/enroute, etc.)
function _canonStatus(s){
  const k = _norm(s);
  if (k === 'onboard') return 'on_board';
  if (k === 'enroute') return 'en_route';
  return k;
}

// Conjuntos ya en canónico
const _SET_WAITING = new Set(['requested','pending','new','offered','offering']);
const _SET_ACTIVE  = new Set(['accepted','assigned','en_route','arrived','boarding','on_board']);
const _SET_SCHED   = new Set(['scheduled']);

const _isWaiting   = r => _SET_WAITING.has(_canonStatus(r?.status));
const _isActive    = r => _SET_ACTIVE.has(_canonStatus(r?.status));
const _isScheduled = r => _SET_SCHED.has(_canonStatus(r?.status));



// Badge de color por estado (acepta alias)
function statusBadgeClass(s){
  const k = _canonStatus(s);
  if (k === 'en_route') return 'bg-primary-subtle text-primary';
  if (k === 'arrived')  return 'bg-warning-subtle text-warning';
  if (k === 'on_board') return 'bg-success-subtle text-success';
  if (k === 'assigned' || k === 'accepted' || k === 'boarding')
    return 'bg-secondary-subtle text-secondary';
  return 'bg-light text-body';
}


/* ------- Render genérico en cualquier contenedor ------- */
function renderActiveRidesInto(containerSel, badgeSel, rides){
  const el = document.querySelector(containerSel); if(!el) return;
  el.innerHTML = '';

  const b = document.querySelector(badgeSel);
  if (b) b.innerText = (rides||[]).length;

  // índice rápido para acciones (assign/view) sin pedir el detalle
  window._ridesIndex = new Map();

  (rides||[]).forEach(r=>{
    try { window._ridesIndex.set(r.id, r); } catch {}

    // usa tu helper existente (//----helpers card------)
    const html = renderRideCard(r);
    if (!html) return; // oculto solo si terminal

    const wrapper = document.createElement('div');
    wrapper.innerHTML = html;
    const card = wrapper.firstElementChild;

    // wire de acciones mínimas
    card.querySelector('[data-act="view"]')
        ?.addEventListener('click', ()=> highlightRideOnMap?.(r));

    // cancel por data-action (si tu card ya lo trae)
    card.querySelector('[data-action="cancel-ride"]')
        ?.addEventListener('click', (e)=> e.stopPropagation()); // el handler global escucha el click

    el.appendChild(card);
  });
}

/* ------- Mantén tu función original pero redirígela a la genérica ------- */
function renderActiveRides(rides){
  renderActiveRidesInto('#panel-active', '#badgeActivos', rides);
}

/* ------- Versión espejo para el panel izquierdo ------- */
function renderActiveRidesLeft(rides){
  renderActiveRidesInto('#left-active', '#badgeActivosLeft', rides);
}

/* ------- Carga por AJAX y pinta ambos paneles ------- */

// --- helpers para asegurar que la card tenga paradas ---
async function hydrateRideStops(ride) {
  // si ya vienen, no hacemos nada
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

  // cargar detalle del ride (incluye stops) y mezclar
  try {
    const r = await fetch(`/api/rides/${ride.id}`, { headers: { Accept: 'application/json' } });
    if (r.ok) {
      const d = await r.json();
      ride.stops        = Array.isArray(d.stops) ? d.stops : [];
      ride.stops_json   = ride.stops;
      ride.stops_count  = d.stops_count ?? ride.stops.length;
      ride.stop_index   = d.stop_index ?? 0;
      // por si quieres que el monto/dist/dur se actualicen con el detalle
      ride.distance_m   = d.distance_m ?? ride.distance_m;
      ride.duration_s   = d.duration_s ?? ride.duration_s;
      ride.quoted_amount= d.quoted_amount ?? ride.quoted_amount;
    }
  } catch (e) {
    console.warn('hydrateRideStops fallo', e);
  }
  return ride;
}



async function loadActiveRides() {
  const panel = document.getElementById('panel-active');
  try {
    const r = await fetch('/api/rides?status=active', { headers: { Accept: 'application/json' } });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    let list = await r.json();

    // 💧 hidratar con paradas cuando falten
    list = await Promise.all(list.map(hydrateRideStops));

    // render
    panel.innerHTML = list.map(renderRideCard).join('') || '<div class="text-muted small">Sin viajes</div>';

    // opcional: badge
    const b = document.getElementById('badgeActivos');
    if (b) b.textContent = list.length;

  } catch (e) {
    console.error('loadActiveRides fallo:', e);
    panel.innerHTML = `<div class="text-danger small">Error cargando activos</div>`;
  }
}


/* =========================
 *  INVERTIR RUTA (REGreso)
 * ========================= */
function invertRoute() {
  // Guardar valores actuales
  const fromLat = qs('#fromLat').value;
  const fromLng = qs('#fromLng').value;
  const fromAddr = qs('#inFrom').value;
  
  const toLat = qs('#toLat').value;
  const toLng = qs('#toLng').value;
  const toAddr = qs('#inTo').value;

  // Validar que tenemos ambos puntos
  if (!fromLat || !fromLng || !toLat || !toLng) {
    showToast('Se necesitan origen y destino para invertir', 'warning');
    return;
  }

  // Intercambiar valores
  qs('#fromLat').value = toLat;
  qs('#fromLng').value = toLng;
  qs('#inFrom').value = toAddr;

  qs('#toLat').value = fromLat;
  qs('#toLng').value = fromLng;
  qs('#inTo').value = fromAddr;

  // Limpiar paradas automáticamente en regreso
  clearAllStops();
  
  // Redibujar ruta
  drawRoute({ quiet: true });
  autoQuoteIfReady();
  
  showToast('Ruta invertida - Paradas limpiadas', 'success');
}

// Añadir al DOMContentLoaded
qs('#btnInvertRoute')?.addEventListener('click', invertRoute);

// Pinta tarjetas de "Ahora" (esperando/ofertados) en #panel-active
function renderRightNowCards(rides){
  const host = document.getElementById('panel-active');
  if (!host) return;
  const waiting = (rides || []).filter(_isWaiting);

  // índice solo de waiting (para los botones Ver)
  window._ridesIndex = new Map(waiting.map(r => [r.id, r]));

  host.innerHTML = waiting.length
    ? waiting.map(r => renderRideCard(r) || '').join('')
    : `<div class="text-muted px-2 py-2">Sin solicitudes.</div>`;

  host.querySelectorAll('[data-act="view"]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const card = btn.closest('[data-ride-id]');
      const id   = Number(card?.dataset?.rideId);
      const r    = window._ridesIndex.get(id);
      if (r) highlightRideOnMap(r);
    });
  });

  // badge de la pestaña
  const b = document.querySelector('#tab-active-cards .badge');
  if (b) b.textContent = String(waiting.length);
}

/* =========================
 *  MÉTRICAS EN VIVO DE FLOTA
 * ========================= */
class FleetMetrics {
  constructor() {
    this.metrics = {
      total: 0,
      free: 0,
      busy: 0,
      offline: 0,
      inQueue: 0,
      onTrip: 0
    };
    this.metricsEl = null;
    this.init();
  }

  init() {
    this.createMetricsPanel();
    this.updateLive();
  }

  createMetricsPanel() {
    const html = `
      <div id="fleetMetrics" class="cc-metrics-panel">
        <div class="cc-metrics-header">
          <h6>📊 Métricas Flota</h6>
          <span class="cc-metrics-time" id="metricsTime"></span>
        </div>
        <div class="cc-metrics-grid">
          <div class="cc-metric-item" data-metric="total">
            <div class="cc-metric-value">0</div>
            <div class="cc-metric-label">Total</div>
          </div>
          <div class="cc-metric-item" data-metric="free">
            <div class="cc-metric-value">0</div>
            <div class="cc-metric-label">Libres</div>
          </div>
          <div class="cc-metric-item" data-metric="busy">
            <div class="cc-metric-value">0</div>
            <div class="cc-metric-label">Ocupados</div>
          </div>
          <div class="cc-metric-item" data-metric="onTrip">
            <div class="cc-metric-value">0</div>
            <div class="cc-metric-label">En Viaje</div>
          </div>
          <div class="cc-metric-item" data-metric="inQueue">
            <div class="cc-metric-value">0</div>
            <div class="cc-metric-label">En Cola</div>
          </div>
          <div class="cc-metric-item" data-metric="offline">
            <div class="cc-metric-value">0</div>
            <div class="cc-metric-label">Offline</div>
          </div>
        </div>
      </div>
    `;
    
    // Insertar en algún lugar visible (ej: arriba del mapa)
    const mapContainer = document.querySelector('.map-container');
    if (mapContainer) {
      mapContainer.insertAdjacentHTML('afterbegin', html);
      this.metricsEl = document.getElementById('fleetMetrics');
    }
  }

  calculateMetrics(drivers, queues) {
    const metrics = {
      total: drivers.length,
      free: 0,
      busy: 0,
      offline: 0,
      inQueue: 0,
      onTrip: 0
    };

    drivers.forEach(driver => {
      const state = visualState(driver);
      switch(state) {
        case 'free': metrics.free++; break;
        case 'busy': metrics.busy++; break;
        case 'offline': metrics.offline++; break;
        case 'on_board': metrics.onTrip++; break;
        default: metrics.busy++;
      }
    });

    // Calcular en cola desde queues
    metrics.inQueue = queues.reduce((sum, queue) => 
      sum + (queue.drivers?.length || 0), 0
    );

    this.metrics = metrics;
    this.updateDisplay();
  }

  updateDisplay() {
    Object.keys(this.metrics).forEach(key => {
      const valueEl = this.metricsEl?.querySelector(`[data-metric="${key}"] .cc-metric-value`);
      if (valueEl) {
        valueEl.textContent = this.metrics[key];
        // Animación suave
        valueEl.style.transform = 'scale(1.1)';
        setTimeout(() => valueEl.style.transform = 'scale(1)', 200);
      }
    });

    // Actualizar timestamp
    const timeEl = document.getElementById('metricsTime');
    if (timeEl) {
      timeEl.textContent = new Date().toLocaleTimeString();
    }
  }

  updateLive() {
    // Actualizar cada 5 segundos
    setInterval(() => {
      if (window._lastDrivers && window._lastQueues) {
        this.calculateMetrics(window._lastDrivers, window._lastQueues);
      }
    }, 5000);
  }
}

class SmartNotifications {
  static show(type, message, options = {}) {
    const config = {
      success: { icon: '✅', duration: 3000 },
      warning: { icon: '⚠️', duration: 5000 },
      error: { icon: '❌', duration: 7000 },
      info: { icon: 'ℹ️', duration: 4000 }
    }[type];

    // Usar Toast de Bootstrap o librería similar
    this.showToast(`${config.icon} ${message}`, config.duration);
  }

  static rideAssigned(ride, driver) {
    this.show('success', `Viaje #${ride.id} asignado a ${driver.name}`);
  }

  static driverArrived(ride) {
    this.show('info', `Conductor llegó al punto de recogida`);
  }
}


// Pinta tarjetas programadas en #panel-active-scheduled
function renderRightScheduledCards(rides){
  const host = document.getElementById('panel-active-scheduled');
  if (!host) return;
  const scheduled = (rides || []).filter(_isScheduled);

  // índice para "Ver" (solo scheduled aquí)
  window._ridesIndex = new Map(scheduled.map(r => [r.id, r]));

  host.innerHTML = scheduled.length
    ? scheduled.map(r => renderRideCard(r) || '').join('')
    : `<div class="text-muted px-2 py-2">Sin programados.</div>`;

  host.querySelectorAll('[data-act="view"]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const card = btn.closest('[data-ride-id]');
      const id   = Number(card?.dataset?.rideId);
      const r    = window._ridesIndex.get(id);
      if (r) highlightRideOnMap(r);
    });
  });

  // badge de la pestaña
  const b = document.querySelector('#tab-active-grid .badge');
  if (b) b.textContent = String(scheduled.length);
}


// Pinta el dock inferior (tipo tabla) con ACTivos
// --- helpers mínimos ---
const _lc = s => String(s||'').toLowerCase();

function _statePill(r){
  const st = _lc(r.status);
  if (st === 'en_route') return { cls:'badge-pill badge-enroute',  label:'EN RUTA' };
  if (st === 'arrived')  return { cls:'badge-pill badge-arrived',  label:'LLEGÓ'   };
  if (st === 'on_board') return { cls:'badge-pill badge-onboard',  label:'A BORDO' };
  return { cls:'badge-pill badge-accepted', label:'ACEPTADO' };
}

function statusBadgeClass(s){
  const k = String(s||'').toLowerCase();
  if (k === 'en_route' || k === 'enroute') return 'bg-primary-subtle text-primary';
  if (k === 'arrived')                      return 'bg-warning-subtle text-warning';
  if (k === 'on_board' || k === 'onboard')  return 'bg-success-subtle text-success';
  if (k === 'accepted')                     return 'bg-secondary-subtle text-secondary';
  return 'bg-light text-body';
}

// === Dock table styles (once) ===============================================
(function injectDockTableStyles(){
  if (window.__DOCK_TABLE_STYLES__) return; window.__DOCK_TABLE_STYLES__ = true;
  const css = `
    .cc-dock-table th, .cc-dock-table td { font-size:12px; vertical-align:middle; }
    .cc-dock-table .badge { font-size:11px; }
    .cc-dock-unit, .cc-dock-driver { font-weight:600; }
    .cc-dock-eta { width:54px; }
    .cc-dock-num { text-align:right; width:56px; }
    .cc-dock-stops { text-align:center; width:70px; }
    .cc-dock-table tbody tr:hover { background: #f8fafc; }
  `;
  const s = document.createElement('style'); s.textContent = css; document.head.appendChild(s);
})();

// === Dock expandible =========================================================
(function setupDockToggle(){
  const dock   = document.querySelector('#dispatchDock'); // contenedor dock
  const toggle = document.querySelector('#dockToggle, [data-act="dock-toggle"]'); // botón expandir
  if (!dock || !toggle) return;

  const saved = localStorage.getItem('dispatchDockExpanded');
  if (saved === '1') dock.classList.add('is-expanded');

  toggle.addEventListener('click', (e)=>{
    e.preventDefault();
    dock.classList.toggle('is-expanded');
    localStorage.setItem('dispatchDockExpanded', dock.classList.contains('is-expanded') ? '1' : '0');
  });
})();

function renderDockActive(rides){
  const active = (rides||[]).filter(_isActive);

  const b1 = document.getElementById('badgeActivos');      // badge panel derecho
  const b2 = document.getElementById('badgeActivosDock');  // badge dock
  if (b1) b1.textContent = active.length;
  if (b2) b2.textContent = active.length;

  const host = document.getElementById('dock-active-table');
  if (!host) return;

  // índice por id
  window._ridesIndex = new Map(active.map(r=>[r.id,r]));

  // helpers
  const esc = (s)=>String(s??'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  const fmtKm  = (m)=> Number.isFinite(m) ? (m/1000).toFixed(1) : '–';
  const fmtMin = (s)=> Number.isFinite(s) ? Math.round(s/60) : '–';
  const parseStops = (r)=>{
    if (Array.isArray(r.stops)) return r.stops;
    if (Array.isArray(r.stops_json)) return r.stops_json;
    if (typeof r.stops_json === 'string' && r.stops_json.trim()!==''){
      try { const a = JSON.parse(r.stops_json); return Array.isArray(a) ? a : []; } catch {}
    }
    return [];
  };
  const short = (t,max=60)=> (t||'').length>max ? t.slice(0,max-1)+'…' : (t||'');

  const rows = active.map(r=>{
    const km   = fmtKm(r.distance_m);
    const min  = fmtMin(r.duration_s);
    const st   = String(_canonStatus(r.status) || '').toUpperCase();
    const badge= statusBadgeClass(r.status);
    const dvr  = r.driver_name || r.driver?.name || '—';
    const unit = r.vehicle_economico || r.vehicle_plate || r.vehicle_id || '—';
    const eta  = (r.pickup_eta_min ?? '—');

    const stops = parseStops(r);
    const sc    = stops.length || Number(r.stops_count||0);

    // Tooltip con labels o coords; \n -> &#10; para title HTML
    const tipRaw = stops.map((s,i)=>{
      const label = (s && typeof s.label==='string' && s.label.trim()!=='')
        ? s.label.trim()
        : (Number.isFinite(+s.lat)&&Number.isFinite(+s.lng) ? `${(+s.lat).toFixed(5)}, ${(+s.lng).toFixed(5)}` : '—');
      return `S${i+1}: ${label}`;
    }).join('\n');
    const tipHtml = esc(tipRaw).replace(/\n/g,'&#10;');

    const stopsCell = sc
      ? `<span class="badge bg-secondary-subtle text-secondary" title="${tipHtml}">${sc}</span>`
      : '—';

    return `
      <tr class="cc-row" data-ride-id="${r.id}">
        <td class="cc-dock-unit">${esc(unit)}</td>
        <td class="cc-dock-driver">${esc(dvr)}</td>
        <td class="cc-dock-eta">${esc(String(eta))}</td>
        <td class="small" title="${esc(r.origin_label||'')}">${esc(short(r.origin_label,50))}</td>
        <td class="small" title="${esc(r.dest_label||'')}">${esc(short(r.dest_label,50))}</td>
        <td class="cc-dock-stops">${stopsCell}</td>
        <td class="cc-dock-num">${km}</td>
        <td class="cc-dock-num">${min}</td>
        <td><span class="badge ${badge}">${st}</span></td>
        <td class="text-end"><button class="btn btn-xs btn-outline-secondary" data-act="view">Ver</button></td>
      </tr>`;
  }).join('');

  host.innerHTML = `
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0 cc-dock-table">
        <thead>
          <tr>
            <th>Unidad</th>
            <th>Conductor</th>
            <th class="cc-dock-eta">ETA</th>
            <th>Origen</th>
            <th>Destino</th>
            <th class="cc-dock-stops">Paradas</th>
            <th class="cc-dock-num">Km</th>
            <th class="cc-dock-num">Min</th>
            <th>Estado</th>
            <th></th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;

  // Ver (ruta en mapa) + centrar conductor
  host.querySelectorAll('button[data-act="view"]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const tr = btn.closest('tr');
      const id = Number(tr?.dataset?.rideId);
      const r  = window._ridesIndex.get(id);
      if (r){
        highlightRideOnMap(r); // deja ruta/markers
        focusDriverOnMap(r);   // centra conductor
      }
    });
  });

  // Click en fila -> centra conductor (sin dibujar ruta)
  host.querySelectorAll('tr.cc-row').forEach(tr=>{
    tr.addEventListener('click', (e)=>{
      if (e.target.closest('button')) return;
      const id = Number(tr.dataset.rideId);
      const r  = window._ridesIndex.get(id);
      if (r) focusDriverOnMap(r);
    });
  });
}


// Centra el mapa en la última ubicación conocida del driver (si existe). Mantiene la ruta.
function getRideById(id){
  if (!id) return null;
  const r = window._ridesIndex?.get?.(id);
  if (r) return r;
  const list = Array.isArray(window._lastActiveRides) ? window._lastActiveRides : [];
  return list.find(x => Number(x.id) === Number(id)) || null;
}



// Orquestador: separa y pinta todo
function renderRightPanels(rides){
  renderRightNowCards(rides);
  renderRightScheduledCards(rides);
  renderDockActive(rides);

  // Auto-mostrar pestaña: si hay programados, quédate donde esté el usuario; no forzar
  // (si quisieras forzar, podrías hacer .show() sobre el tab correspondiente)
}

function applyRidePreset(preset){
  const hasExisting =
    (qs('#fromLat')?.value && qs('#fromLng')?.value) ||
    (qs('#toLat')?.value   && qs('#toLng')?.value);

  if (hasExisting) {
    const ok = confirm('Ya hay una ruta cargada. ¿Quieres reemplazarla por la del cliente seleccionado?');
    if (!ok) return;
  }

  // Rellenar campos
  if (preset.origin) {
    qs('#inFrom').value   = preset.origin.label || '';
    qs('#fromLat').value  = preset.origin.lat ?? '';
    qs('#fromLng').value  = preset.origin.lng ?? '';
  }
  if (preset.dest) {
    qs('#inTo').value   = preset.dest.label || '';
    qs('#toLat').value  = preset.dest.lat ?? '';
    qs('#toLng').value  = preset.dest.lng ?? '';
  }

  // Redibujar ruta si tienes helper
  if (typeof drawRoutePreview === 'function') {
    drawRoutePreview();
  }
}


// MANTIENE la firma original
function renderQueues(queues){
  const acc = document.getElementById('panel-queue'); if (!acc) return;
  acc.innerHTML = '';

  const total = (queues || []).length;
  const badge = document.getElementById('badgeColas'); if (badge) badge.textContent = total;

  (queues || []).forEach((s, idx) => {
    const drivers = Array.isArray(s.drivers) ? s.drivers : [];
    const toEco = d => (d.eco || d.callsign || d.number || d.id || '?');

    const item   = document.createElement('div'); item.className = 'accordion-item';
    const headId = `qhead-${s.id||idx}`, colId = `qcol-${s.id||idx}`;

    item.innerHTML = `
      <h2 class="accordion-header" id="${headId}">
        <button class="accordion-button collapsed" type="button"
                data-bs-toggle="collapse" data-bs-target="#${colId}"
                aria-expanded="false" aria-controls="${colId}">
          <div class="d-flex w-100 justify-content-between align-items-center">
            <div>
              <div class="fw-semibold">${s.nombre || s.name || ('Base '+(s.id||''))}</div>
              <div class="small text-muted">${[s.latitud||s.lat, s.longitud||s.lng].filter(Boolean).join(', ')}</div>
            </div>
            <span class="badge bg-secondary">${drivers.length}</span>
          </div>
        </button>
      </h2>
      <div id="${colId}" class="accordion-collapse collapse" data-bs-parent="#panel-queue">
        <div class="accordion-body">
          ${
            drivers.length
              ? `<div class="queue-eco-grid">${
                  drivers.map(d => `<span class="eco">${toEco(d)}</span>`).join('')
                }</div>`
              : `<div class="text-muted">Sin unidades en cola.</div>`
          }
        </div>
      </div>
    `;
    acc.appendChild(item);
  });
}


function resetWhenNow(){
  const rNow   = document.getElementById('when-now');
  const rLater = document.getElementById('when-later');
  const sched  = document.getElementById('scheduleAt');
  const row    = document.getElementById('scheduleRow');

  if (rNow)   rNow.checked   = true;
  if (rLater) rLater.checked = false;
  if (sched)  sched.value    = '';         // limpia datetime-local
  if (row)    row.style.display = 'none';  // oculta la fila
  const rs = document.getElementById('routeSummary');
  if (rs) rs.innerText = 'Ruta: — · Zona: — · Cuando: ahora';
}
(function autoFixedWhenTyping(){
  const amt = document.querySelector('#fareAmount');
  const fm  = document.querySelector('#fareMode');
  if (!amt || !fm) return;

  let lastProgrammatic = null;

  // Marcar 'fixed' al editar manualmente
  amt.addEventListener('input', () => {
    const v = Number(amt.value);
    if (Number.isFinite(v) && fm.value !== 'fixed') {
      fm.value = 'fixed';
      // opcional: pequeño aviso
      // toast('Usando tarifa fija');
    }
  });

  // Si vuelven a 'meter', puedes restaurar el valor de la última cotización
  fm.addEventListener('change', () => {
    if (fm.value === 'meter' && window.__lastQuote?.amount != null) {
      amt.value = window.__lastQuote.amount;
    }
  });
})();

async function recalcQuoteUI() {
  try {
    const fromLat = parseFloat(qs('#fromLat')?.value || '');
    const fromLng = parseFloat(qs('#fromLng')?.value || '');
    const toLat   = parseFloat(qs('#toLat')?.value   || '');
    const toLng   = parseFloat(qs('#toLng')?.value   || '');

    // 1) Validación: O/D completos
    if (!Number.isFinite(fromLat) || !Number.isFinite(fromLng) ||
        !Number.isFinite(toLat)   || !Number.isFinite(toLng)) {
      console.warn('[quote] faltan coordenadas de origen/destino');
      return;
    }

    // 2) Stops (0..2)
    const stops = [];
    const s1lat = parseFloat(qs('#stop1Lat')?.value || '');
    const s1lng = parseFloat(qs('#stop1Lng')?.value || '');
    if (Number.isFinite(s1lat) && Number.isFinite(s1lng)) stops.push({ lat: s1lat, lng: s1lng });

    const s2lat = parseFloat(qs('#stop2Lat')?.value || '');
    const s2lng = parseFloat(qs('#stop2Lng')?.value || '');
    if (Number.isFinite(s2lat) && Number.isFinite(s2lng)) stops.push({ lat: s2lat, lng: s2lng });

    // 3) Arma body
    const body = {
      origin:      { lat: fromLat, lng: fromLng },
      destination: { lat: toLat,   lng: toLng   },
      stops,                // backend usará stop_fee de tenant_fare_policies
      round_to_step: 1       // opcional
    };

    // 4) URL segura (default si no definiste la global)
    const url = window.__QUOTE_URL__ || '/api/dispatch/quote';

    const resp = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type':'application/json',
        'Accept':'application/json'
      },
      body: JSON.stringify(body)
    });

    if (amtInput && (!fm || fm.value !== 'fixed')) {
      amtInput.value = data.amount;
    }

    if (!resp.ok) {
      console.warn('[quote] HTTP', resp.status);
      return;
    }

    const data = await resp.json();

    // Tu controlador responde { ok, amount, distance_m, duration_s, [stops_n] }
    const amount = data?.amount;
    if (amount == null) {
      console.warn('[quote] respuesta sin amount', data);
      return;
    }

    // 5) Actualiza UI
    const amt = qs('#fareAmount'); if (amt) amt.value = amount;

    const rs = qs('#routeSummary');
    if (rs) {
      const km  = ((data.distance_m ?? 0) / 1000).toFixed(2);
      const min = Math.round((data.duration_s ?? 0) / 60);
      const sn  = data.stops_n ?? stops.length; // por si el backend no manda stops_n
      rs.innerText = `Ruta: ${km} km · ${min} min · Paradas: ${sn} · Tarifa: $${amount}`;
    }
  } catch (e) {
    console.warn('[quote] recalcQuoteUI error', e);
  }
}



/* ---------- INIT (cuando el DOM está listo) ---------- */
document.addEventListener('DOMContentLoaded', async () => {
  await loadDispatchSettings();
  //renderQueues(queues); 
  loadActiveRides();
  // refresco suave cada 20–30s (opcional)
  //setInterval(loadActiveRides, 30000);


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
    if (window.google?.maps?.places) {
  if (qs('#inStop1')) {
    acStop1 = new google.maps.places.Autocomplete(qs('#inStop1'), { fields:['geometry','formatted_address'] });
    acStop1.addListener('place_changed', ()=>{
      const p = acStop1.getPlace();
      const ll = p?.geometry?.location;
      if (!ll) return;
      setStop1([ll.lat(), ll.lng()], p.formatted_address || null);
    });
  }
  if (qs('#inStop2')) {
    acStop2 = new google.maps.places.Autocomplete(qs('#inStop2'), { fields:['geometry','formatted_address'] });
    acStop2.addListener('place_changed', ()=>{
      // solo si existe stop1
      if (!Number.isFinite(parseFloat(qs('#stop1Lat')?.value))) return;
      const p = acStop2.getPlace();
      const ll = p?.geometry?.location;
      if (!ll) return;
      setStop2([ll.lat(), ll.lng()], p.formatted_address || null);
    });
  }
}
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

  qs('#btnPickStop1')?.addEventListener('click', ()=>{
  map.once('click', (e)=> setStop1([e.latlng.lat, e.latlng.lng]));
  });
  qs('#btnPickStop2')?.addEventListener('click', ()=>{
    if (!Number.isFinite(parseFloat(qs('#stop1Lat')?.value))) return;
    map.once('click', (e)=> setStop2([e.latlng.lat, e.latlng.lng]));
  });

  // Botón +Parada
  qs('#btnAddStop1')?.addEventListener('click', ()=>{
    const hasS1 = Number.isFinite(parseFloat(qs('#stop1Lat')?.value));
    if (!hasS1) { qs('#stop1Row').style.display=''; return; }
    const hasS2 = Number.isFinite(parseFloat(qs('#stop2Lat')?.value));
    if (!hasS2) { qs('#stop2Row').style.display=''; }
  });

  // Quitar
  qs('#btnClearStop1')?.addEventListener('click', ()=>{
    qs('#stop1Lat').value=''; qs('#stop1Lng').value='';
    qs('#inStop1').value='';  qs('#stop1Row').style.display='none';
    if (stop1Marker){ stop1Marker.remove(); stop1Marker=null; }
    // si quitamos S1, también invalidamos S2
    qs('#btnClearStop2')?.click();
    drawRoute({quiet:true}); autoQuoteIfReady();
    recalcQuoteUI();
  });
  qs('#btnClearStop2')?.addEventListener('click', ()=>{
    qs('#stop2Lat').value=''; qs('#stop2Lng').value='';
    qs('#inStop2').value='';  qs('#stop2Row').style.display='none';
    if (stop2Marker){ stop2Marker.remove(); stop2Marker=null; }
    drawRoute({quiet:true}); autoQuoteIfReady();
    recalcQuoteUI();
  });


  qs('#toggle-sectores')?.addEventListener('change', e=> e.target.checked? layerSectores.addTo(map):map.removeLayer(layerSectores));
  qs('#toggle-stands')?.addEventListener('change',   e=> e.target.checked? layerStands.addTo(map):  map.removeLayer(layerStands));

  // recordar último ride por teléfono
qs('#pass-phone')?.addEventListener('blur', async (e) => {
  const phone = (e.target.value || '').trim();
  if (!phone) return;

  const hasA = !!(qs('#fromLat')?.value && qs('#fromLng')?.value);
  const hasB = !!(qs('#toLat')?.value   && qs('#toLng')?.value);
  if (hasA || hasB) return;

  try {
    const r = await fetch(`/api/passengers/last-ride?phone=${encodeURIComponent(phone)}`);
    if (!r.ok) return;
    const lastRide = await r.json(); 
    if (!lastRide) return;

    console.log('Last ride with stops data:', lastRide);

    // Rellenar datos básicos
    if (lastRide.passenger_name && !qs('#pass-name')?.value) {
      qs('#pass-name').value = lastRide.passenger_name;
    }
    if (lastRide.notes && !qs('#ride-notes')?.value) {
      qs('#ride-notes').value = lastRide.notes;
    }

    // Limpiar mapa
    try { 
      clearAssignArtifacts?.();
      if (window.rideMarkers) {
        window.rideMarkers.forEach(g => { try { g.remove(); } catch {} });
        window.rideMarkers.clear();
      }
    } catch {}

    // Establecer origen y destino
    if (Number.isFinite(lastRide.origin_lat) && Number.isFinite(lastRide.origin_lng)) {
      setFrom([lastRide.origin_lat, lastRide.origin_lng], lastRide.origin_label);
    }
    if (Number.isFinite(lastRide.dest_lat) && Number.isFinite(lastRide.dest_lng)) {
      setTo([lastRide.dest_lat, lastRide.dest_lng], lastRide.dest_label);
    }

    // ✅ CARGAR STOPS DIRECTAMENTE DESDE LA RESPUESTA
    await loadStopsFromLastRide(lastRide);
    
  } catch (err) {
    console.warn('Error in phone autocomplete:', err);
  }
});

// Función para cargar stops desde la respuesta del last-ride
async function loadStopsFromLastRide(lastRide) {
    try {
        console.log('Loading stops from last ride:', lastRide);
        
        // ✅ PREVENIR DOBLE EJECUCIÓN
        if (window.loadingStops) return;
        window.loadingStops = true;
        
        let stops = [];
        
        // Usar el array de stops si está disponible
        if (Array.isArray(lastRide.stops) && lastRide.stops.length > 0) {
            stops = lastRide.stops;
            console.log('Using stops array:', stops);
        }
        // Fallback: usar stops_json si existe
        else if (lastRide.stops_json) {
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
        
        // ✅ SOLO establecer stops si encontramos datos válidos
        if (stops.length > 0) {
            console.log('Setting stops in form:', stops);
            setStopsInForm(stops);
        } else {
            console.log('No stops found in last ride');
        }
        
    } catch (err) {
        console.warn('Error loading stops from last ride:', err);
    } finally {
        // ✅ LIMPIAR FLAG
        window.loadingStops = false;
    }
}

// Función para establecer stops en el formulario
// Función para establecer stops en el formulario - CORREGIDA
function setStopsInForm(stops) {
  if (!Array.isArray(stops) || stops.length === 0) return;
  
  console.log('Setting stops in form:', stops);
  
  // Limpiar stops anteriores PERO mantener las filas visibles según sea necesario
  if (stop1Marker) { stop1Marker.remove(); stop1Marker = null; }
  if (stop2Marker) { stop2Marker.remove(); stop2Marker = null; }
  
  // Limpiar campos pero NO ocultar filas todavía
  const stopFields = [
    '#stop1Lat', '#stop1Lng', '#inStop1',
    '#stop2Lat', '#stop2Lng', '#inStop2'
  ];
  
  stopFields.forEach(selector => {
    const el = qs(selector);
    if (el) el.value = '';
  });
  
  // Mostrar filas según la cantidad de stops
  const stop1Row = qs('#stop1Row');
  const stop2Row = qs('#stop2Row');
  
  // Ocultar ambas filas primero
  if (stop1Row) stop1Row.style.display = 'none';
  if (stop2Row) stop2Row.style.display = 'none';
  
  // Establecer nuevos stops y mostrar filas según corresponda
  stops.forEach((stop, index) => {
    if (index >= 2) return; // Máximo 2 stops
    
    const lat = Number(stop.lat);
    const lng = Number(stop.lng);
    const label = stop.label || stop.address || '';
    
    if (Number.isFinite(lat) && Number.isFinite(lng)) {
      if (index === 0) {
        // Primer stop
        if (stop1Row) stop1Row.style.display = '';
        setStop1([lat, lng], label);
        console.log('Set stop1:', lat, lng, label);
      } else if (index === 1) {
        // Segundo stop
        if (stop2Row) stop2Row.style.display = '';
        setStop2([lat, lng], label);
        console.log('Set stop2:', lat, lng, label);
      }
    }
  });
  
  // Redibujar ruta
  setTimeout(() => {
    drawRoute({quiet: true});
    autoQuoteIfReady();
  }, 500);
}

// Y modifica también la función clearAllStops para que sea más específica:
function clearRoute() {
    try {
        // Limpiar polyline del mapa
        if (window.routeLine) {
            window.routeLine.remove();
            window.routeLine = null;
        }
        
        // Limpiar cualquier otra ruta que pueda estar en el mapa
        if (window.map && window.map._layers) {
            Object.values(window.map._layers).forEach(layer => {
                if (layer instanceof L.Polyline) {
                    window.map.removeLayer(layer);
                }
            });
        }
        
        console.log('Ruta anterior limpiada');
    } catch (error) {
        console.warn('Error limpiando ruta:', error);
    }
}

  // crear ride
  // Crear ride (dispatch)
// normaliza el datetime-local a ISO con zona (evita desfases)
function normalizeScheduledValue(v){
  // v viene tipo "2025-01-31T14:30"
  if (!v) return null;
  const d = new Date(v);
  return Number.isNaN(d.getTime()) ? null : d.toISOString(); // manda UTC ISO
}
qs('#btnCreate')?.addEventListener('click', async () => {
  // ¿programado?
  let scheduled_for = null;
  if (qs('#when-later')?.checked) {
    const scheduleInput = qs('#scheduleAt');
    if (scheduleInput?.value) {
      scheduled_for = scheduleInput.value; // "YYYY-MM-DDTHH:mm"
    }
  }

  // tarifa tomada del input (permitimos override manual)
  const fareInput = Number(qs('#fareAmount')?.value);
  const quoted_amount = Number.isFinite(fareInput) ? Math.round(fareInput) : null;

  // si tienes un checkbox para fijar tarifa manual (#fareFixed o #fareLock), lo usamos; si no existe, no estorba.
  const userfixed = !!(qs('#fareFixed')?.checked || qs('#fareLock')?.checked);

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
    fare_mode:      qs('#fareMode')?.value || 'meter',   // si no existe el select, quedará 'meter'
    notes:          qs('#ride-notes')?.value || null,
    pax:            parseInt(qs('#pax')?.value) || 1,
    scheduled_for,

    quoted_amount,
    // si marcaste “fijar tarifa”, lo mandamos; el servicio ya respeta este flag
    ...(userfixed ? { userfixed: true } : {}),

    distance_m:     (typeof __lastQuote !== 'undefined' ? (__lastQuote?.distance_m ?? null) : null),
    duration_s:     (typeof __lastQuote !== 'undefined' ? (__lastQuote?.duration_s ?? null) : null),
    route_polyline: (typeof __lastQuote !== 'undefined' ? (__lastQuote?.polyline   ?? null) : null),

    requested_channel: 'dispatch',
  };

  // ===== Adjuntar STOPS (con label) si existen en el formulario =====
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
  // ==================================================================

  // Validación mínima de origen
  if (!Number.isFinite(payload.origin_lat) || !Number.isFinite(payload.origin_lng)) {
    alert('Indica un origen válido.');
    return;
  }

  try {
    const r = await fetch('/api/rides', {
      method: 'POST',
      headers: {
        'Content-Type':'application/json','Accept':'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        'X-Tenant-ID': (typeof getTenantId === 'function' ? getTenantId() : (window.currentTenantId || ''))
      },
      body: JSON.stringify(payload)
    });
    if (!r.ok) throw new Error((await r.text().catch(()=>'')) || ('HTTP '+r.status));
    const ride = await r.json();

    // ===== AUTODISPATCH con delay solo si NO es programado =====
    try {
      if (!isScheduledStatus?.(ride) && typeof startCompoundAutoDispatch === 'function') {
        startCompoundAutoDispatch(ride);
      }
    } catch(e){}

    // ===== Limpiar UI (incluye stops) =====
    try {
      ['inFrom','inTo','pass-name','pass-phone','pass-account','ride-notes','fareAmount','pax',].forEach(id=>{
        const el = qs('#'+id); if (el) el.value='';
      });
      ['fromLat','fromLng','toLat','toLng'].forEach(id=>{
        const el = qs('#'+id); if (el) el.value='';
      });
      layerRoute?.clearLayers?.();
      if (fromMarker){ fromMarker.remove(); fromMarker=null; }
      if (toMarker){   toMarker.remove();   toMarker=null;  }
      if (stop1Marker) { stop1Marker.remove(); stop1Marker = null; }
      if (stop2Marker) { stop2Marker.remove(); stop2Marker = null; }
      const s1Lat = qs('#stop1Lat'), s1Lng = qs('#stop1Lng'), s2Lat = qs('#stop2Lat'), s2Lng = qs('#stop2Lng');
      const inS1 = qs('#inStop1'), inS2 = qs('#inStop2');
      const row1 = qs('#stop1Row'), row2 = qs('#stop2Row');
      if (s1Lat) s1Lat.value=''; if (s1Lng) s1Lng.value='';
      if (s2Lat) s2Lat.value=''; if (s2Lng) s2Lng.value='';
      if (inS1) inS1.value='';   if (inS2) inS2.value='';
      if (row1) row1.style.display='none';
      if (row2) row2.style.display='none';
      const rs = document.getElementById('routeSummary');
      if (rs) rs.innerText = 'Ruta: — · Zona: — · Cuando: ahora';
      resetWhenNow?.();

    } catch {}
     try {
  if (window._assignPickupMarker) {
    try { (layerSuggested || layerRoute || map).removeLayer(window._assignPickupMarker); } catch {}
    try { map.removeLayer(window._assignPickupMarker); } catch {}
    window._assignPickupMarker = null;
  }
} catch {}

    // ===== Feedback + Tabs =====
    if (isScheduledStatus?.(ride)) {
      Swal.fire({icon:'success', title:'Programado creado', text:'Se disparará a su hora.', timer:1800, showConfirmButton:false});
      const tabProg = document.getElementById('tab-active-grid');
      if (tabProg && window.bootstrap?.Tab) window.bootstrap.Tab.getOrCreateInstance(tabProg).show();
    } else {
      Swal.fire({icon:'success', title:'Viaje creado', timer:1200, showConfirmButton:false});
      const tabNow = document.getElementById('tab-active-cards');
      if (tabNow && window.bootstrap?.Tab) window.bootstrap.Tab.getOrCreateInstance(tabNow).show();
    }

    await window.refreshDispatch?.();
  } catch (e) {
    console.error(e);
    alert('No se pudo crear el viaje: ' + (e?.message || e));
  }
});


  



  // limpiar
 // util opcional
function removeFromAnyLayer(marker) {
  if (!marker) return;
  try {
    if (typeof layerSuggested !== 'undefined' && layerSuggested?.hasLayer?.(marker)) {
      layerSuggested.removeLayer(marker);
      return;
    }
  } catch {}
  try {
    if (typeof layerRoute !== 'undefined' && layerRoute?.hasLayer?.(marker)) {
      layerRoute.removeLayer(marker);
      return;
    }
  } catch {}
  try { marker.remove(); } catch {}
}

// === Limpia formulario + mapa (crear/editar ride) ===
function clearRideFormAndMap() {
  try {
      ['inFrom','inTo','pass-name','pass-phone','pass-account','ride-notes','fareAmount','pax',].forEach(id=>{
        const el = qs('#'+id); if (el) el.value='';
      });
      ['fromLat','fromLng','toLat','toLng'].forEach(id=>{
        const el = qs('#'+id); if (el) el.value='';
      });
      layerRoute?.clearLayers?.();
      if (fromMarker){ fromMarker.remove(); fromMarker=null; }
      if (toMarker){   toMarker.remove();   toMarker=null;  }
      if (stop1Marker) { stop1Marker.remove(); stop1Marker = null; }
      if (stop2Marker) { stop2Marker.remove(); stop2Marker = null; }
      const s1Lat = qs('#stop1Lat'), s1Lng = qs('#stop1Lng'), s2Lat = qs('#stop2Lat'), s2Lng = qs('#stop2Lng');
      const inS1 = qs('#inStop1'), inS2 = qs('#inStop2');
      const row1 = qs('#stop1Row'), row2 = qs('#stop2Row');
      if (s1Lat) s1Lat.value=''; if (s1Lng) s1Lng.value='';
      if (s2Lat) s2Lat.value=''; if (s2Lng) s2Lng.value='';
      if (inS1) inS1.value='';   if (inS2) inS2.value='';
      if (row1) row1.style.display='none';
      if (row2) row2.style.display='none';
      const rs = document.getElementById('routeSummary');
      if (rs) rs.innerText = 'Ruta: — · Zona: — · Cuando: ahora';
      resetWhenNow?.();

    } catch {}
     try {
  if (window._assignPickupMarker) {
    try { (layerSuggested || layerRoute || map).removeLayer(window._assignPickupMarker); } catch {}
    try { map.removeLayer(window._assignPickupMarker); } catch {}
    window._assignPickupMarker = null;
  }
} catch {}

}

// === Enlazar a ambos botones ===
qs('#btnClear') ?.addEventListener('click', clearRideFormAndMap);
qs('#btnReset') ?.addEventListener('click', clearRideFormAndMap);


   qs('#btnDuplicate')?.addEventListener('click', (e)=>{
  e.preventDefault();
  if (!window.__lastRide) return; // si guardas el ride creado aquí
  // ejemplo rápido:
  qs('#inFrom').value = window.__lastRide.origin_label || '';
  qs('#fromLat').value = window.__lastRide.origin_lat || '';
  qs('#fromLng').value = window.__lastRide.origin_lng || '';
  qs('#inTo').value = window.__lastRide.dest_label || '';
  qs('#toLat').value = window.__lastRide.dest_lat || '';
  qs('#toLng').value = window.__lastRide.dest_lng || '';
  qs('#pass-name').value = window.__lastRide.passenger_name || '';
  qs('#pass-phone').value = window.__lastRide.passenger_phone || '';
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

