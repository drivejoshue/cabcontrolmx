/* resources/js/pages/dispatch/core.js */
export const CONFIG = {
  CENTER_DEFAULT: [19.4326, -99.1332],
  DEFAULT_ZOOM: 13,
};

export const TENANT_MAP = (window.ccTenant && window.ccTenant.map) || null;

export const CENTER = (TENANT_MAP && Number.isFinite(+TENANT_MAP.lat) && Number.isFinite(+TENANT_MAP.lng))
  ? [Number(TENANT_MAP.lat), Number(TENANT_MAP.lng)]
  : CONFIG.CENTER_DEFAULT;

export const MAP_ZOOM = (TENANT_MAP && Number.isFinite(+TENANT_MAP.zoom))
  ? Number(TENANT_MAP.zoom)
  : CONFIG.DEFAULT_ZOOM;

export const COVERAGE_RADIUS_KM = (TENANT_MAP && Number.isFinite(+TENANT_MAP.radius_km))
  ? Number(TENANT_MAP.radius_km)
  : 8;

export const OSM = {
  url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
  attr: '&copy; OpenStreetMap contributors'
};

export const TENANT_ICONS = (window.ccTenant && window.ccTenant.map_icons) || {
  origin: '/images/origen.png',
  dest: '/images/destino.png',
  stand: '/images/marker-parqueo5.png',
  stop: '/images/stopride.png',
};

// Selector helpers
export const qs = (sel) => document.querySelector(sel);
export const fmt = (n) => Number(n).toFixed(6);
export const isDarkMode = () => document.documentElement.getAttribute('data-theme') === 'dark';

// HTML escaping
export function escapeHtml(s) {
  return String(s)
    .replace(/&/g, "&amp;").replace(/</g, "&lt;")
    .replace(/>/g, "&gt;").replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// Tenant helpers
export function getTenantId() {
  if (window.currentTenantId != null && window.currentTenantId !== '') {
    return String(window.currentTenantId);
  }

  const meta = document.querySelector('meta[name="tenant-id"]')?.content;
  if (meta) return String(meta).trim();

  if (window.__TENANT_ID__ != null && window.__TENANT_ID__ !== '') {
    return String(window.__TENANT_ID__);
  }

  return '';
}

export const TENANT_ID = Number(getTenantId() || 0);

// Setup global tenant references
if (!TENANT_ID) {
  console.error('❌ Usuario sin tenant_id en layout.dispatch, no se puede iniciar Dispatch');
}

window.currentTenantId = TENANT_ID;
window.__TENANT_ID__ = TENANT_ID;
if (typeof window.getTenantId !== 'function') {
  window.getTenantId = getTenantId;
}

// Headers JSON con tenant
export function jsonHeaders(extra = {}) {
  const headers = {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...extra,
  };

  const tokenMeta = document.querySelector('meta[name="csrf-token"]');
  if (tokenMeta && tokenMeta.content) {
    headers['X-CSRF-TOKEN'] = tokenMeta.content;
  }

  const tid = getTenantId();
  if (tid) {
    headers['X-Tenant-ID'] = String(tid);
  }

  return headers;
}

// Debug utilities
window.__DISPATCH_DEBUG__ = false;

export function debugBrief(ride) {
  return {
    id: ride.id,
    hasStopsProp: Array.isArray(ride.stops),
    stopsLen: Array.isArray(ride.stops) ? ride.stops.length : null,
    typeofStopsJson: typeof ride.stops_json,
    stopsJsonIsArray: Array.isArray(ride.stops_json),
    stopsJsonStrPrefix: (typeof ride.stops_json === 'string' ? ride.stops_json.slice(0, 80) : null),
    stops_count: ride.stops_count ?? null,
    stop_index: ride.stop_index ?? null,
  };
}

export function logListDebug(list, tag = '[dispatch] list') {
  if (!window.__DISPATCH_DEBUG__) return;
  try {
    console.groupCollapsed(tag, 'n=', list.length);
    console.table(list.map(debugBrief));
    const withStops = list.find(r => (r.stops_count ?? 0) > 0 || (Array.isArray(r.stops) && r.stops.length));
    console.log('sample ride (con paradas si existe):', withStops || list[0]);
    console.groupEnd();
  } catch (e) { console.warn('debug error', e); }
}