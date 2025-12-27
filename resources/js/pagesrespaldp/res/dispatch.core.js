/* resources/js/pages/dispatch.core.js */

// ===== DEBUG SWITCH =====
export const DISPATCH_DEBUG = false;

// ===== DOM helpers =====
export const qs  = (sel, root=document) => root.querySelector(sel);
export const qsa = (sel, root=document) => Array.from(root.querySelectorAll(sel));

export function escapeHtml(s){
  return String(s)
    .replace(/&/g,"&amp;").replace(/</g,"&lt;")
    .replace(/>/g,"&gt;")
    .replace(/"/g,"&quot;")
    .replace(/'/g,"&#039;");
}

export const fmt = (n) => Number(n).toFixed(6);
export const isDarkMode = () => document.documentElement.getAttribute('data-theme') === 'dark';

// ===== TENANT HELPERS =====
export function getTenantId() {
  if (window.currentTenantId != null && window.currentTenantId !== '') return String(window.currentTenantId);
  const meta = document.querySelector('meta[name="tenant-id"]')?.content;
  if (meta) return String(meta).trim();
  if (window.__TENANT_ID__ != null && window.__TENANT_ID__ !== '') return String(window.__TENANT_ID__);
  return '';
}

export function ensureTenantGlobals(){
  const tid = Number(getTenantId() || 0);
  if (!tid) console.error('❌ Usuario sin tenant_id, no se puede iniciar Dispatch');

  window.currentTenantId = tid;
  window.__TENANT_ID__   = tid;
  if (typeof window.getTenantId !== 'function') window.getTenantId = getTenantId;

  return tid;
}

export function jsonHeaders(extra = {}) {
  const headers = {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...extra,
  };

  const tokenMeta = document.querySelector('meta[name="csrf-token"]');
  if (tokenMeta?.content) headers['X-CSRF-TOKEN'] = tokenMeta.content;

  const tid = getTenantId();
  if (tid) headers['X-Tenant-ID'] = String(tid);

  return headers;
}

// ===============================
// DB timestamps (NO TZ conversion)
// ===============================
export function extractPartsFromDbTs(s) {
  if (!s) return null;
  const t = String(s).replace('T', ' ');
  const m = t.match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})(?::(\d{2}))?$/);
  if (!m) return null;
  return { y:+m[1], mo:+m[2], d:+m[3], H:+m[4], M:+m[5], S:+(m[6] ?? 0), raw:t };
}

export function fmtHM12_fromDb(s) {
  const p = extractPartsFromDbTs(s);
  if (!p) return '—';
  let h = p.H % 12; if (h === 0) h = 12;
  const mm = String(p.M).padStart(2, '0');
  const ampm = p.H < 12 ? 'a.m.' : 'p.m.';
  return `${h}:${mm} ${ampm}`;
}

export function fmtShortDay_fromDb(s) {
  const p = extractPartsFromDbTs(s);
  if (!p) return '';
  const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
  return `${String(p.d).padStart(2,'0')} ${meses[p.mo-1]}`;
}

export function fmtWhen_db(s) {
  const p = extractPartsFromDbTs(s);
  if (!p) return '—';
  return `${fmtHM12_fromDb(s)} · ${fmtShortDay_fromDb(s)}`;
}

// ===== POLYLINE / DISTANCE =====
export function decodePolyline(str){
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

export function distKm(aLat, aLng, bLat, bLng){
  const toRad = d => d * Math.PI / 180;
  const R = 6371;
  const dLat = toRad(bLat - aLat);
  const dLng = toRad(bLng - aLng);
  const s1 = Math.sin(dLat/2), s2 = Math.sin(dLng/2);
  const aa = s1*s1 + Math.cos(toRad(aLat)) * Math.cos(toRad(bLat)) * s2*s2;
  return 2 * R * Math.asin(Math.sqrt(aa));
}

// ===== ETA (Google -> OSRM) =====
export async function etaSeconds(fromLL, toLL){
  if (window.google?.maps?.DirectionsService) {
    try{
      const dir = new google.maps.DirectionsService();
      const res = await new Promise((resolve,reject)=>{
        dir.route({ origin: fromLL, destination: toLL, travelMode: google.maps.TravelMode.DRIVING },
          (r,s)=> s==='OK'?resolve(r):reject(s)
        );
      });
      const leg = res.routes?.[0]?.legs?.[0];
      if (leg?.duration?.value) return Number(leg.duration.value);
    }catch{}
  }
  try{
    const u = `https://router.project-osrm.org/route/v1/driving/${fromLL.lng},${fromLL.lat};${toLL.lng},${toLL.lat}?overview=false&alternatives=false&steps=false`;
    const r = await fetch(u);
    const j = await r.json();
    const sec = j?.routes?.[0]?.duration;
    if (Number.isFinite(sec)) return sec;
  }catch{}
  return null;
}

// ===== GOOGLE LOADER =====
let gmapsReady = null;

export function haveFullGoogle(){
  return !!(window.google?.maps && window.google.maps.places && window.google.maps.geometry);
}

export function loadGoogleMaps(){
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
// ---- geo helpers ----
export function haversineKm(lat1, lon1, lat2, lon2) {
  const R = 6371;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;

  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(lat1 * Math.PI / 180) *
    Math.cos(lat2 * Math.PI / 180) *
    Math.sin(dLon / 2) ** 2;

  return 2 * R * Math.asin(Math.sqrt(a));
}



export function dbg(...args){
  try { console.debug('[dispatch]', ...args); } catch {}
}

