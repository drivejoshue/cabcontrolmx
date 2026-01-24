// resources/js/partner-monitor/icons.js
import L from 'leaflet';

export const CAR_W = 36;
export const CAR_H = 48;

export const CAR_SPRITES = {
  sedan: {
    free:     '/images/vehicles/sedan-free1.png',
    offered:  '/images/vehicles/sedan1.png',
    accepted: '/images/vehicles/sedan-acepted1.png',
    en_route: '/images/vehicles/sedan-assigned1.png',
    arrived:  '/images/vehicles/sedan-busy1.png',
    on_board: '/images/vehicles/sedan-assigned1.png',
    busy:     '/images/vehicles/sedan-busy1.png',
    offline:  '/images/vehicles/sedan-offline1.png',
  },
  van: {
    free:     '/images/vehicles/van-free1.png',
    offered:  '/images/vehicles/van-assigned1.png',
    accepted: '/images/vehicles/van-assigned1.png',
    en_route: '/images/vehicles/van-assigned1.png',
    arrived:  '/images/vehicles/van-assigned1.png',
    on_board: '/images/vehicles/van-onboard1.png',
    busy:     '/images/vehicles/van-busy1.png',
    offline:  '/images/vehicles/van-offline1.png',
  },
  vagoneta: {
    free:     '/images/vehicles/vagoneta-free1.png',
    offered:  '/images/vehicles/vagoneta-assigned1.png',
    accepted: '/images/vehicles/vagoneta-assigned1.png',
    en_route: '/images/vehicles/vagoneta-assigned1.png',
    arrived:  '/images/vehicles/vagoneta-assigned1.png',
    on_board: '/images/vehicles/vagoneta-onboard1.png',
    busy:     '/images/vehicles/vagoneta-busy1.png',
    offline:  '/images/vehicles/vagoneta-offline1.png',
  },
  premium: {
    free:     '/images/vehicles/premium-free1.png',
    offered:  '/images/vehicles/premium-assigned1.png',
    accepted: '/images/vehicles/premium-assigned1.png',
    en_route: '/images/vehicles/premium-assigned1.png',
    arrived:  '/images/vehicles/premium-assigned1.png',
    on_board: '/images/vehicles/premium-onboard1.png',
    busy:     '/images/vehicles/premium-busy1.png',
    offline:  '/images/vehicles/premium-offline1.png',
  },
};

export function iconUrl(vehicleType = 'sedan', vstate = 'free') {
  const t = String(vehicleType || 'sedan').toLowerCase();
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
    <div class="pm-car-box">
      <img class="pm-car-img pm-a pm-active"   src="${src}" alt="car" />
      <img class="pm-car-img pm-b pm-inactive" src="${src}" alt="car" />
    </div>`;

  return L.divIcon({
    className: 'pm-car-icon',
    html,
    iconSize: [CAR_W, CAR_H],
    iconAnchor: [CAR_W / 2, CAR_H / 2],
    tooltipAnchor: [0, -CAR_H / 2],
  });
}

export function setMarkerBearing(marker, bearingDeg) {
  const el = marker.getElement();
  if (!el) return;
  const box = el.querySelector('.pm-car-box');
  if (!box) return;
  const b = ((Number(bearingDeg) || 0) % 360 + 360) % 360;
  box.style.setProperty('--pm-rot', `${b}deg`);
}

export function setMarkerScale(marker, scale) {
  const el = marker.getElement();
  if (!el) return;
  el.querySelector('.pm-car-box')?.style.setProperty('--pm-scale', String(scale));
}

export function smoothBearing(prevDeg, nextDeg, alpha = 0.35) {
  if (prevDeg == null) return nextDeg;
  let d = (nextDeg - prevDeg) % 360;
  if (d > 180) d -= 360;
  if (d < -180) d += 360;
  return (prevDeg + d * alpha + 360) % 360;
}

export function setCarSprite(marker, nextSrc) {
  const el = marker.getElement();
  if (!el) return;
  const a = el.querySelector('.pm-car-img.pm-a');
  const b = el.querySelector('.pm-car-img.pm-b');
  if (!a || !b) return;

  const active = el.querySelector('.pm-car-img.pm-active') || a;
  const inactive = active === a ? b : a;
  if (active.getAttribute('src') === nextSrc) return;

  const swap = () => {
    inactive.setAttribute('src', nextSrc);
    inactive.classList.remove('pm-inactive');
    inactive.classList.add('pm-active');
    active.classList.remove('pm-active');
    active.classList.add('pm-inactive');
  };

  const img = new Image();
  img.onload = swap;
  img.onerror = () => {
    console.warn('[PM] Sprite no carga (posible 404):', nextSrc);
    swap();
  };
  img.src = nextSrc;
}

export function preloadCarIcons() {
  try {
    Object.values(CAR_SPRITES).forEach((states) => {
      Object.values(states).forEach((url) => {
        const im = new Image();
        im.src = url;
      });
    });
  } catch {}
}
