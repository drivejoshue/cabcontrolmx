// resources/js/pages/dispatch.drivers.js

import L from 'leaflet';
import { DISPATCH_DEBUG } from './dispatch.core.js';
import { visualState, statusLabel, fmtAgo } from './dispatch.ui_helpers.js';
import { makeCarIcon, setMarkerScale, setMarkerBearing, scaleForZoom } from './dispatch.driver_icons.js';
import { clearDriverRoute, showDriverToPickup } from './dispatch.suggested_routes.js';

export const driverPins = new Map();

const FALLBACK_ZOOM = 14;

function getMap(ctx = {}) {
  return ctx.map || window.map || null;
}

function getLayerDrivers(ctx = {}) {
  // soporta ctx.layers.drivers (recomendado) y globals actuales
  return ctx.layers?.drivers || ctx.layerDrivers || window.layerDrivers || null;
}

function dbg(...args) {
  if (!DISPATCH_DEBUG) return;
  try { console.debug('[drivers]', ...args); } catch {}
}

/**
 * upsertDriver: crea o actualiza marker con histeresis para evitar parpadeo
 */
export function upsertDriver(d, ctx = {}) {
  const map = getMap(ctx);
  const layerDrivers = getLayerDrivers(ctx);

  const id  = d.id || d.driver_id;
  const lat = Number(d.lat ?? d.last_lat);
  const lng = Number(d.lng ?? d.last_lng);
  if (!id || !Number.isFinite(lat) || !Number.isFinite(lng)) return;
  if (!layerDrivers) return;

  const type  = String(d.vehicle_type || 'sedan').toLowerCase();
  const drvSt = String(d.driver_status || '').toLowerCase();

  let vstate = visualState(d);
  if (drvSt === 'offline') vstate = 'offline';

  const icon    = makeCarIcon(type, vstate);
  const zScale  = scaleForZoom(map ? map.getZoom() : FALLBACK_ZOOM);
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

  const zIdx = (['on_board','accepted','en_route','arrived'].includes(vstate)) ? 900
            : (vstate === 'offline' ? 100 : 500);

  let entry = driverPins.get(id);

  if (!entry) {
    const marker = L.marker([lat, lng], {
      icon,
      zIndexOffset: zIdx,
      riseOnHover: true,
    })
      .bindTooltip(tip, { className:'cc-tip', direction:'top', offset:[0,-12], sticky:true })
      .addTo(layerDrivers);

    driverPins.set(id, {
      marker,
      type,
      vstate,
      wantState: vstate,
      wantType: type,
      mismatchCount: 0,
      lastSwapAt: 0,
    });

    setMarkerScale(marker, zScale);
    setMarkerBearing(marker, bearing);
    return;
  }

  // update position + transforms
  entry.marker.setLatLng([lat, lng]);
  setMarkerScale(entry.marker, zScale);
  setMarkerBearing(entry.marker, bearing);
  entry.marker.setZIndexOffset(zIdx);
  entry.marker.getTooltip()?.setContent(tip);

  // histeresis (evita parpadeo de ícono por estados intermitentes)
  const now = Date.now();
  if (entry.type !== type || entry.vstate !== vstate) {
    const wantsChanged = (entry.wantState !== vstate || entry.wantType !== type);
    if (wantsChanged) {
      entry.wantState = vstate;
      entry.wantType  = type;
      entry.mismatchCount = 1;
    } else {
      entry.mismatchCount++;
    }

    const DWELL = 2;     // cuántos ticks seguidos para aceptar cambio
    const MIN_MS = 200;  // mínimo entre swaps
    const timeOk = (now - entry.lastSwapAt) >= MIN_MS;

    if (entry.mismatchCount >= DWELL && timeOk) {
      entry.type   = entry.wantType || type;
      entry.vstate = entry.wantState || vstate;
      entry.marker.setIcon(makeCarIcon(entry.type, entry.vstate));
      setMarkerScale(entry.marker, zScale);
      setMarkerBearing(entry.marker, bearing);
      entry.lastSwapAt = now;
      entry.mismatchCount = 0;
      dbg('swap icon', id, entry.type, entry.vstate);
    }
  } else {
    entry.wantState = vstate;
    entry.wantType  = type;
    entry.mismatchCount = 0;
  }
}

export function removeDriverById(id, ctx = {}) {
  const layerDrivers = getLayerDrivers(ctx);
  const pin = driverPins.get(id);
  if (!pin) return;
  try { layerDrivers?.removeLayer?.(pin.marker); } catch {}
  driverPins.delete(id);
}

export function onSelfLogout(driverId, ctx = {}) {
  removeDriverById(driverId, ctx);
}

/**
 * Render completo: limpia y repinta, filtrando por frescura/offline/ride activo
 */
export function renderDrivers(list, ctx = {}) {
  const layerDrivers = getLayerDrivers(ctx);
  if (!layerDrivers) return;

  layerDrivers.clearLayers?.();
  driverPins.clear();

  const now = Date.now();
  const FRESH_MS = 120 * 1000;

  (Array.isArray(list) ? list : []).forEach(d => {
    const lat = Number(d.lat ?? d.last_lat);
    const lng = Number(d.lng ?? d.last_lng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

    const t = d.reported_at ? new Date(d.reported_at).getTime() : 0;
    const isFresh = t && (now - t) <= FRESH_MS;

    const drvSt  = String(d.driver_status || '').toLowerCase();
    const rideSt = String(d.ride_status   || '').toLowerCase();

    const hasActiveRide = [
      'requested','scheduled','offered','accepted','assigned',
      'en_route','arrived','onboard','on_board','boarding'
    ].includes(rideSt);

    if (isFresh || drvSt === 'offline' || hasActiveRide) {
      upsertDriver(d, ctx);
    }
  });
}

export function reiconAll(ctx = {}) {
  const map = getMap(ctx);
  const zScale = scaleForZoom(map ? map.getZoom() : FALLBACK_ZOOM);
  driverPins.forEach((e) => {
    e.marker.setIcon(makeCarIcon(e.type, e.vstate));
    setMarkerScale(e.marker, zScale);
  });
}

/**
 * Recalcula sugeridas para rides actuales (driver→pickup)
 */
export async function updateSuggestedRoutes(rides, ctx = {}) {
  driverPins.forEach((_, driverId) => { try { clearDriverRoute(driverId, ctx); } catch {} });

  const targetStates = new Set(['ASSIGNED','EN_ROUTE','ARRIVED','REQUESTED','OFFERED','SCHEDULED','ACCEPTED']);
  for (const r of (rides || [])) {
    const st = String(r.status || '').toUpperCase();
    if (!r.driver_id || !targetStates.has(st)) continue;
    if (!Number.isFinite(+r.origin_lat) || !Number.isFinite(+r.origin_lng)) continue;
    await showDriverToPickup(r.driver_id, Number(r.origin_lat), Number(r.origin_lng), ctx);
  }
}

/**
 * Wire realtime Echo channel driver.location.{tenantId}
 */
export function wireDriverRealtime(ctx = {}) {
  const tenantId = ctx.tenantId || window.currentTenantId || (window.ccTenant && window.ccTenant.id) || 1;
  if (!window.Echo) return;

  window.Echo.channel(`driver.location.${tenantId}`)
    .listen('.LocationUpdated', async (p) => {
      upsertDriver({ ...p, id: p.driver_id }, ctx);

      const rs = String(p.ride_status || '').toUpperCase();
      if (['ASSIGNED','EN_ROUTE','ARRIVED'].includes(rs) &&
          Number.isFinite(+p.origin_lat) && Number.isFinite(+p.origin_lng)) {
        await showDriverToPickup(p.driver_id, p.origin_lat, p.origin_lng, ctx);
      }
      if (['ON_BOARD','ONBOARD','FINISHED','CANCELLED','CANCELED'].includes(rs)) {
        clearDriverRoute(p.driver_id, ctx);
      }
    });
}


