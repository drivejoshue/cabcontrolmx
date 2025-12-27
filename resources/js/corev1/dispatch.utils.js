/* resources/js/pages/dispatch/utils.js */
export function haversineKm(a, b) {
  const R = 6371, dLat = (b.lat - a.lat) * Math.PI / 180, dLng = (b.lng - a.lng) * Math.PI / 180;
  const s1 = Math.sin(dLat / 2), s2 = Math.sin(dLng / 2);
  const c = Math.cos(a.lat * Math.PI / 180) * Math.cos(b.lat * Math.PI / 180);
  return 2 * R * Math.asin(Math.sqrt(s1 * s1 + c * s2 * s2));
}

export async function etaSeconds(fromLL, toLL) {
  if (window.google?.maps?.DirectionsService) {
    try {
      const dir = new google.maps.DirectionsService();
      const res = await new Promise((resolve, reject) => {
        dir.route({
          origin: fromLL, destination: toLL,
          travelMode: google.maps.TravelMode.DRIVING
        }, (r, s) => s === 'OK' ? resolve(r) : reject(s));
      });
      const leg = res.routes?.[0]?.legs?.[0];
      if (leg?.duration?.value) return Number(leg.duration.value);
    } catch { }
  }

  try {
    const u = `https://router.project-osrm.org/route/v1/driving/${fromLL.lng},${fromLL.lat};${toLL.lng},${toLL.lat}?overview=false&alternatives=false&steps=false`;
    const r = await fetch(u); const j = await r.json();
    const sec = j?.routes?.[0]?.duration;
    if (Number.isFinite(sec)) return sec;
  } catch { }
  return null;
}

export function _distKm(aLat, aLng, bLat, bLng) {
  const toRad = d => d * Math.PI / 180;
  const R = 6371;
  const dLat = toRad(bLat - aLat);
  const dLng = toRad(bLng - aLng);
  const s1 = Math.sin(dLat / 2), s2 = Math.sin(dLng / 2);
  const aa = s1 * s1 + Math.cos(toRad(aLat)) * Math.cos(toRad(bLat)) * s2 * s2;
  return 2 * R * Math.asin(Math.sqrt(aa));
}

export function smoothMove(marker, toLL, ms = 350) {
  const from = marker.getLatLng();
  const t0 = performance.now();
  function step(t) {
    const k = Math.min(1, (t - t0) / ms);
    const lat = from.lat + (toLL.lat - from.lat) * k;
    const lng = from.lng + (toLL.lng - from.lng) * k;
    marker.setLatLng([lat, lng]);
    if (k < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

export function bearingBetween(a, b) {
  const dLon = (b.lng - a.lng) * Math.PI / 180;
  const y = Math.sin(dLon) * Math.cos(b.lat * Math.PI / 180);
  const x = Math.cos(a.lat * Math.PI / 180) * Math.sin(b.lat * Math.PI / 180) -
    Math.sin(a.lat * Math.PI / 180) * Math.cos(b.lat * Math.PI / 180) * Math.cos(dLon);
  return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
}

export function normalizeScheduledValue(v) {
  if (!v) return null;
  const d = new Date(v);
  return Number.isNaN(d.getTime()) ? null : d.toISOString();
}

export function setInput(sel, val) { const el = qs(sel); if (el) el.value = val; }

export function clearOriginDest() {
  try {
    const layer = (typeof layerSuggested !== 'undefined') ? layerSuggested : layerRoute;

    if (window.__originMarker) {
      try { layer.removeLayer(window.__originMarker); } catch { }
      window.__originMarker = null;
    }
    if (window.__destMarker) {
      try { layer.removeLayer(window.__destMarker); } catch { }
      window.__destMarker = null;
    }

    if (stop1Marker) { stop1Marker.remove(); stop1Marker = null; }
    if (stop2Marker) { stop2Marker.remove(); stop2Marker = null; }
    if (window.__stopMarkers) {
      window.__stopMarkers.forEach(m => { try { m.remove(); } catch { } });
      window.__stopMarkers = [];
    }

    if (window.__stopMarkers) {
      window.__stopMarkers.forEach(marker => { try { marker.remove(); } catch { } });
      window.__stopMarkers = [];
    }

    try {
      const toRemove = [];
      layerRoute.eachLayer(l => {
        try {
          if (l instanceof L.Polyline) {
            const cls = String(l.options?.className || '');
            if (cls.includes('cc-route')) toRemove.push(l);
          }
        } catch { }
      });
      toRemove.forEach(l => { try { layerRoute.removeLayer(l); } catch { } });
    } catch { }
  } catch (err) {
    console.warn('[map] clearOriginDest error', err);
  }
}

export function clearTripGraphicsHard() {
  try {
    if (typeof clearAllPreviews === 'function') clearAllPreviews();
    if (typeof clearSuggestedLines === 'function') clearSuggestedLines();
  } catch { }
  clearAssignArtifacts();

  try { layerRoute?.clearLayers(); } catch { }
  try { if (window.fromMarker) { fromMarker.remove(); fromMarker = null; } } catch { }
  try { if (window.toMarker) { toMarker.remove(); toMarker = null; } } catch { }

  try {
    const lyr = (typeof layerSuggested !== 'undefined') ? layerSuggested : layerRoute;
    if (window.__originMarker) { lyr.removeLayer(window.__originMarker); window.__originMarker = null; }
    if (window.__destMarker) { lyr.removeLayer(window.__destMarker); window.__destMarker = null; }
    if (window.__routeLine) { layerRoute.removeLayer(window.__routeLine); window.__routeLine = null; }
  } catch { }
}

export function removeRideGraphics(rideId) {
  try {
    if (window.rideMarkers && rideMarkers.has(rideId)) {
      const g = rideMarkers.get(rideId);
      try { g.remove(); } catch { }
      rideMarkers.delete(rideId);
    }
  } catch { }
}

export function resetWhenNow() {
  const rNow = document.getElementById('when-now');
  const rLater = document.getElementById('when-later');
  const sched = document.getElementById('scheduleAt');
  const row = document.getElementById('scheduleRow');

  if (rNow) rNow.checked = true;
  if (rLater) rLater.checked = false;
  if (sched) sched.value = '';
  if (row) row.style.display = 'none';
  const rs = document.getElementById('routeSummary');
  if (rs) rs.innerText = 'Ruta: — · Zona: — · Cuando: ahora';
}

export function showToast(message, type = 'info') {
  // Implementación simple de toast
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  toast.style.cssText = `
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: ${type === 'success' ? '#4CAF50' : type === 'warning' ? '#FF9800' : '#2196F3'};
    color: white;
    padding: 12px 16px;
    border-radius: 4px;
    z-index: 9999;
  `;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}