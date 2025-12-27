/* resources/js/pages/dispatch/drivers.js */
import { qs, fmtAgo } from './core.js';
import { layerDrivers } from './map.js';

const CAR_SPRITES = {
  sedan: {
    free: '/images/vehicles/sedan-free.png',
    offered: '/images/vehicles/sedan.png',
    accepted: '/images/vehicles/sedan-acepted.png',
    en_route: '/images/vehicles/sedan-assigned.png',
    arrived: '/images/vehicles/sedan-busy.png',
    on_board: '/images/vehicles/sedan-assigned.png',
    busy: '/images/vehicles/sedan-busy.png',
    offline: '/images/vehicles/sedan-offline.png',
  },
  van: {
    free: '/images/vehicles/van-free.png',
    offered: '/images/vehicles/van-assigned.png',
    accepted: '/images/vehicles/van-assigned.png',
    en_route: '/images/vehicles/van-assigned.png',
    arrived: '/images/vehicles/van-assigned.png',
    on_board: '/images/vehicles/van-onboard.png',
    busy: '/images/vehicles/van-busy.png',
    offline: '/images/vehicles/van-offline.png',
  },
  vagoneta: {
    free: '/images/vehicles/vagoneta-free.png',
    offered: '/images/vehicles/vagoneta-assigned.png',
    accepted: '/images/vehicles/vagoneta-assigned.png',
    en_route: '/images/vehicles/vagoneta-assigned.png',
    arrived: '/images/vehicles/vagoneta-assigned.png',
    on_board: '/images/vehicles/vagoneta-onboard.png',
    busy: '/images/vehicles/vagoneta-busy.png',
    offline: '/images/vehicles/vagoneta-offline.png',
  },
  premium: {
    free: '/images/vehicles/premium-free.png',
    offered: '/images/vehicles/premium-assigned.png',
    accepted: '/images/vehicles/premium-assigned.png',
    en_route: '/images/vehicles/premium-assigned.png',
    arrived: '/images/vehicles/premium-assigned.png',
    on_board: '/images/vehicles/premium-onboard.png',
    busy: '/images/vehicles/premium-busy.png',
    offline: '/images/vehicles/premium-offline.png',
  },
};

export const driverPins = new Map();
const CAR_W = 48, CAR_H = 40;

export function visualState(d) {
  const r = String(d.ride_status || '').toLowerCase();
  const ds = String(d.driver_status || '').toLowerCase();
  const shiftOpen = d.shift_open === 1 || d.shift_open === true;

  if (!shiftOpen || ds === 'offline') return 'offline';
  if (r === 'on_board') return 'on_board';
  if (r === 'arrived') return 'arrived';
  if (r === 'en_route') return 'en_route';
  if (r === 'accepted') return 'accepted';
  if (r === 'offered') return 'offered';
  if (ds === 'busy') return 'busy';
  return 'free';
}

export function statusLabel(rideStatus, driverStatus) {
  const x = String(rideStatus || driverStatus || '').toLowerCase();
  switch (x) {
    case 'idle': case 'available': return 'Libre';
    case 'requested': return 'Pedido';
    case 'offered': return 'Ofertado';
    case 'accepted': case 'assigned': return 'Asignado';
    case 'en_route': return 'En ruta';
    case 'arrived': return 'Llegó';
    case 'on_board': case 'onboard': return 'A bordo';
    case 'busy': return 'Ocupado';
    case 'offline': return 'Fuera';
    default: return 'Libre';
  }
}

export function iconUrl(vehicle_type = 'sedan', vstate = 'free') {
  const t = (vehicle_type || 'sedan').toLowerCase();
  return CAR_SPRITES[t]?.[vstate] || CAR_SPRITES.sedan[vstate] || CAR_SPRITES.sedan.free;
}

export function scaleForZoom(z) {
  if (z >= 18) return 1.35;
  if (z >= 16) return 1.20;
  if (z >= 14) return 1.00;
  return 0.85;
}

export function makeCarIcon(type, state) {
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
    iconAnchor: [CAR_W / 2, CAR_H / 2],
    tooltipAnchor: [0, -CAR_H / 2]
  });
}

export function setMarkerBearing(marker, bearingDeg) {
  const el = marker.getElement();
  if (!el) return;
  const box = el.querySelector('.cc-car-box');
  if (!box) return;
  const b = ((Number(bearingDeg) || 0) % 360 + 360) % 360;
  box.style.setProperty('--car-rot', `${b}deg`);
}

export function setMarkerScale(marker, scale) {
  const el = marker.getElement();
  if (!el) return;
  el.querySelector('.cc-car-box')?.style.setProperty('--car-scale', String(scale));
}

export function setCarSprite(marker, nextSrc) {
  const el = marker.getElement();
  if (!el) return;
  const a = el.querySelector('.cc-car-img.cc-a');
  const b = el.querySelector('.cc-car-img.cc-b');
  if (!a || !b) return;

  const active = el.querySelector('.cc-car-img.cc-active') || a;
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

export function upsertDriver(d) {
  const id = d.id || d.driver_id;
  const lat = Number(d.lat ?? d.last_lat);
  const lng = Number(d.lng ?? d.last_lng);
  if (!id || !Number.isFinite(lat) || !Number.isFinite(lng)) return;

  const type = String(d.vehicle_type || 'sedan').toLowerCase();
  const drvSt = String(d.driver_status || '').toLowerCase();
  let vstate = visualState(d);
  if (drvSt === 'offline') vstate = 'offline';

  const icon = makeCarIcon(type, vstate);
  const zScale = scaleForZoom(map ? map.getZoom() : DEFAULT_ZOOM);
  const bearing = Number(d.bearing ?? d.heading_deg ?? 0);

  const econ = d.vehicle_economico || '';
  const plate = d.vehicle_plate || '';
  const phone = d.phone || '';
  const name = d.name || 'Conductor';
  const label = econ ? `${name} (${econ})` : name;
  const labelSt = statusLabel(d.ride_status, d.driver_status);
  const seenTxt = d.reported_at ? `Visto ${fmtAgo(d.reported_at)}` : '—';

  const tip = `
    <div class="cc-tip">
      <div class="tt-title">${label}</div>
      <div class="tt-sub">${type.toUpperCase()}${plate ? ' · ' + plate : ''}</div>
      <div class="tt-meta">${labelSt}${phone ? ' · Tel: ' + phone : ''} · ${seenTxt}</div>
    </div>`;

  const zIdx = (['on_board', 'accepted', 'en_route', 'arrived'].includes(vstate)) ? 900
    : (vstate === 'offline' ? 100 : 500);

  let entry = driverPins.get(id);
  if (!entry) {
    const marker = L.marker([lat, lng], {
      icon,
      zIndexOffset: zIdx,
      riseOnHover: true
    })
      .bindTooltip(tip, { className: 'cc-tip', direction: 'top', offset: [0, -12], sticky: true })
      .addTo(layerDrivers);

    driverPins.set(id, {
      marker,
      type,
      vstate,
      wantState: vstate,
      mismatchCount: 0,
      lastSwapAt: 0
    });

    setMarkerScale(marker, zScale);
    setMarkerBearing(marker, bearing);
    return;
  }

  entry.marker.setLatLng([lat, lng]);
  setMarkerScale(entry.marker, zScale);
  setMarkerBearing(entry.marker, bearing);
  entry.marker.setZIndexOffset(zIdx);
  const tt = entry.marker.getTooltip(); if (tt) tt.setContent(tip);

  const now = Date.now();
  if (entry.type !== type || entry.vstate !== vstate) {
    const wantsChanged = (entry.wantState !== vstate || entry.type !== type);
    if (wantsChanged) {
      entry.wantState = vstate;
      entry.wantType = type;
      entry.mismatchCount = 1;
    } else {
      entry.mismatchCount++;
    }

    const DWELL = 2;
    const MIN_MS = 200;
    const timeOk = (now - entry.lastSwapAt) >= MIN_MS;

    if (entry.mismatchCount >= DWELL && timeOk) {
      entry.type = entry.wantType || type;
      entry.vstate = entry.wantState || vstate;
      entry.marker.setIcon(makeCarIcon(entry.type, entry.vstate));
      setMarkerScale(entry.marker, zScale);
      setMarkerBearing(entry.marker, bearing);
      entry.lastSwapAt = now;
      entry.mismatchCount = 0;
    }
  } else {
    entry.wantState = vstate;
    entry.wantType = type;
    entry.mismatchCount = 0;
  }
}

export function removeDriverById(id) {
  const pin = driverPins.get(id);
  if (pin) {
    try { layerDrivers.removeLayer(pin.marker); } catch { }
    driverPins.delete(id);
  }
}

export function renderDrivers(list) {
  layerDrivers.clearLayers();
  driverPins.clear();

  const now = Date.now();
  const FRESH_MS = 120 * 1000;

  (Array.isArray(list) ? list : []).forEach(d => {
    const hasLL = Number.isFinite(Number(d.lat)) && Number.isFinite(Number(d.lng));
    if (!hasLL) return;

    const t = d.reported_at ? new Date(d.reported_at).getTime() : 0;
    const isFresh = t && (now - t) <= FRESH_MS;

    const drvSt = String(d.driver_status || '').toLowerCase();
    const rideSt = String(d.ride_status || '').toLowerCase();

    const hasActiveRide = ['requested', 'scheduled', 'offered', 'accepted', 'assigned', 'en_route', 'arrived', 'onboard', 'on_board', 'boarding'].includes(rideSt);

    if (isFresh || drvSt === 'offline' || hasActiveRide) {
      upsertDriver(d);
    }
  });
}

// Inyectar estilos CSS
(function injectCarStyles() {
  if (document.getElementById('cc-car-styles')) return;
  const style = document.createElement('style');
  style.id = 'cc-car-styles';
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