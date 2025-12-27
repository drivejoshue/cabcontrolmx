// resources/js/pages/dispatch.driver_icons.js
import L from 'leaflet';

export function makeCarIcon(type='sedan', vstate='free'){
  const cls = `cc-car cc-${type} cc-${vstate}`;
  return L.divIcon({
    className: cls,
    html: `<div class="cc-car-body"></div>`,
    iconSize: [52, 44],
    iconAnchor: [26, 22],
  });
}

export function setMarkerScale(marker, scale=1){
  const el = marker.getElement?.();
  if (!el) return;
  el.style.transformOrigin = 'center center';
  el.style.transform = `translate3d(0,0,0) scale(${scale})`;
}

export function setMarkerBearing(marker, bearing=0){
  const el = marker.getElement?.();
  if (!el) return;
  const b = Number.isFinite(+bearing) ? +bearing : 0;
  el.style.setProperty('--cc-bearing', `${b}deg`);
}

export function scaleForZoom(z){
  const s = (z >= 16) ? 1.15 : (z >= 14 ? 1.0 : (z >= 12 ? 0.9 : 0.8));
  return Math.max(0.6, Math.min(1.4, s));
}
