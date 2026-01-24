// resources/js/partner-monitor/net.js
export const OSM = {
  url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
  attr: '&copy; OpenStreetMap contributors',
};

export const DEFAULT_CENTER = [19.4326, -99.1332];
export const DEFAULT_ZOOM = 15;

export function qsMeta(name) {
  return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') || '';
}

export function isDarkMode() {
  const html = document.documentElement;
  const dt = (html.getAttribute('data-theme') || '').toLowerCase();
  const bt = (html.getAttribute('data-bs-theme') || '').toLowerCase();
  if (dt) return dt === 'dark';
  if (bt) return bt === 'dark';
  return html.classList.contains('theme-dark') || html.classList.contains('dark');
}

export function toNum(v) {
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}

export function escapeHtml(s) {
  return String(s)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

export function pickLatLng(row) {
  const lat = toNum(row.lat ?? row.latitude ?? row.driver_lat ?? row.pickup_lat ?? row.pickupLat);
  const lng = toNum(row.lng ?? row.longitude ?? row.driver_lng ?? row.pickup_lng ?? row.pickupLng);
  if (lat == null || lng == null) return null;
  return { lat, lng };
}

export function jsonHeaders(extra = {}) {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const tenantId = qsMeta('tenant-id') || String(window.ccTenant?.id || '');
  return {
    Accept: 'application/json',
    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
    ...(tenantId ? { 'X-Tenant-ID': tenantId } : {}),
    ...extra,
  };
}

export async function getJson(url) {
  const r = await fetch(url, { headers: jsonHeaders(), credentials: 'same-origin' });
  const ct = (r.headers.get('content-type') || '').toLowerCase();
  const txt = await r.text();

  if (!ct.includes('application/json')) {
    console.warn('[PM] Non-JSON response', { url, status: r.status, ct, snippet: txt.slice(0, 200) });
    return { ok: false, __non_json: true, __status: r.status, __url: url, raw: txt.slice(0, 400) };
  }

  let js;
  try {
    js = JSON.parse(txt);
  } catch (e) {
    console.warn('[PM] JSON parse failed', { url, status: r.status, err: String(e), snippet: txt.slice(0, 200) });
    return { ok: false, __parse_error: true, __status: r.status, __url: url, raw: txt.slice(0, 400) };
  }

  if (!r.ok) console.warn('[PM] HTTP error', { url, status: r.status, body: js });

  if (js && typeof js === 'object' && !Array.isArray(js)) {
    js.__status = r.status;
    js.__url = url;
  }
  return js;
}
