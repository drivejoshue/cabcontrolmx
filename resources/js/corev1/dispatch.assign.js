/* resources/js/pages/dispatch/assign.js */
import { qs, jsonHeaders, escapeHtml } from './core.js';
import { layerRoute } from './map.js';
import { driverPins, statusLabel } from './drivers.js';
import { _distKm } from './utils.js';
import { renderRideCard } from './rides.js';

let _assignPanel, _assignSelected = null, _assignRide = null;
let _assignOriginPin = null, _assignPickupMarker = null;
let _assignPreviewLine = null;

const assignmentLines = new Map();

export function suggestedLineStyle() {
  return isDarkMode()
    ? { color: '#4DD9FF', weight: 5, opacity: .95, dashArray: '6,8' }
    : { color: '#0D6EFD', weight: 4, opacity: .90, dashArray: '6,8' };
}

export function suggestedLineSelectedStyle() {
  return isDarkMode()
    ? { color: '#22CC88', weight: 6, opacity: .95 }
    : { color: '#16A34A', weight: 5, opacity: .95 };
}

export async function ensureDriverPreviewLine(driverId, ride) {
  const e = driverPins.get(driverId);
  if (!e) return null;

  if (e.previewLine) return e.previewLine;

  const line = await drawSuggestedRoute(
    e.marker.getLatLng(),
    L.latLng(ride.origin_lat, ride.origin_lng)
  );
  line.setStyle(suggestedLineStyle());
  line.options.className = 'cc-suggested';
  e.previewLine = line.addTo(layerRoute);
  return e.previewLine;
}

export async function ensurePreviewLineForCandidate(candidate, ride) {
  const id = candidate.id || candidate.driver_id;
  const e = driverPins.get(Number(id));

  let fromLL = null;
  if (e?.marker) {
    fromLL = e.marker.getLatLng();
  } else if (Number.isFinite(candidate.lat) && Number.isFinite(candidate.lng)) {
    fromLL = L.latLng(candidate.lat, candidate.lng);
  } else {
    return null;
  }
  const toLL = L.latLng(ride.origin_lat, ride.origin_lng);

  const line = await drawSuggestedRoute(fromLL, toLL);
  line.setStyle(suggestedLineStyle());
  line.options.className = 'cc-suggested';

  if (e) e.previewLine = line;
  line.addTo(layerRoute);
  return line;
}

export function clearAllPreviews() {
  driverPins.forEach(e => {
    if (e.previewLine) { try { layerRoute.removeLayer(e.previewLine); } catch { } }
    e.previewLine = null;
  });
}

export async function drawSuggestedRoute(fromLL, toLL) {
  if (window.google?.maps && gDirService) {
    try {
      const res = await new Promise((resolve, reject) => {
        gDirService.route({
          origin: { lat: fromLL.lat, lng: fromLL.lng },
          destination: { lat: toLL.lat, lng: toLL.lng },
          travelMode: google.maps.TravelMode.DRIVING,
          region: 'MX',
          drivingOptions: { departureTime: new Date(), trafficModel: 'bestguess' }
        }, (r, s) => s === 'OK' ? resolve(r) : reject(s));
      });

      const route = res.routes?.[0];
      const leg = route?.legs?.[0];
      const pts = (route?.overview_path || []).map(p => [p.lat(), p.lng()]);

      const line = L.polyline(pts, { pane: 'routePane', className: 'cc-suggested', ...suggestedLineStyle() });
      line._meta = {
        distance: leg?.distance?.text || '—',
        duration: (leg?.duration_in_traffic || leg?.duration)?.text || '—'
      };
      return line;
    } catch { }
  }

  const url = `https://router.project-osrm.org/route/v1/driving/${fromLL.lng},${fromLL.lat};${toLL.lng},${toLL.lat}?overview=full&geometries=geojson`;
  const r = await fetch(url); const j = await r.json();
  const coords = j?.routes?.[0]?.geometry?.coordinates || [];
  const latlngs = coords.map(c => [c[1], c[0]]);
  return L.polyline(latlngs, { pane: 'routePane', className: 'cc-suggested', ...suggestedLineStyle() });
}

export async function drawAllPreviewLinesFor(ride, candidates) {
  const TOP_N = 12;
  const ordered = [...(candidates || [])]
    .sort((a, b) => (a.distance_km ?? 9e9) - (b.distance_km ?? 9e9))
    .slice(0, TOP_N);

  for (let i = 0; i < ordered.length; i++) {
    const c = ordered[i];
    const id = c.id || c.driver_id;
    try { await ensureDriverPreviewLine(id, ride); } catch { }
    await new Promise(res => setTimeout(res, 90));
  }

  const best = ordered[0];
  if (best) {
    const id = best.id || best.driver_id;
    const pin = driverPins.get(id);
    if (pin?.previewLine) {
      try { pin.previewLine.setStyle(suggestedLineSelectedStyle()); pin.previewLine.bringToFront(); } catch { }
    }
  }
}

export async function showDriverToPickup(driver_id, origin_lat = null, origin_lng = null) {
  const e = driverPins.get(driver_id); if (!e) return;

  let lat = Number(origin_lat), lng = Number(origin_lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    const a = Number(document.querySelector('#fromLat')?.value);
    const b = Number(document.querySelector('#fromLng')?.value);
    if (Number.isFinite(a) && Number.isFinite(b)) { lat = a; lng = b; }
    else { const c = map.getCenter(); lat = c.lat; lng = c.lng; }
  }

  const prev = assignmentLines.get(driver_id);
  if (prev) { try { layerRoute.removeLayer(prev); } catch { } }

  const from = e.marker.getLatLng();
  const to = L.latLng(lat, lng);
  try {
    const line = await drawSuggestedRoute(from, to);
    line.addTo(layerRoute);
    try { line.bringToFront(); } catch { }
    assignmentLines.set(driver_id, line);
    e.marker.setZIndexOffset(900);
  } catch (err) { console.warn('No se pudo trazar ruta sugerida', err); }
}

export function clearDriverRoute(driver_id) {
  const line = assignmentLines.get(driver_id);
  if (line) { try { layerRoute.removeLayer(line); } catch { } }
  assignmentLines.delete(driver_id);
}

export function highlightAssignment({ driver_id, origin_lat, origin_lng }) {
  const e = driverPins.get(driver_id); if (!e) return;
  const from = e.marker.getLatLng();
  const to = L.latLng(origin_lat, origin_lng);
  if (e.assignmentLine) { layerRoute.removeLayer(e.assignmentLine); }
  e.assignmentLine = L.polyline([from, to], { color: '#0dcaf0', weight: 4, opacity: .9 })
    .addTo(layerRoute);
  e.marker.setZIndexOffset(900);
}

export async function previewCandidatesFor(ride, limit = 8, radiusKm = 5) {
  try {
    const url = `/api/dispatch/nearby-drivers?lat=${ride.origin_lat}&lng=${ride.origin_lng}&km=${radiusKm}`;
    const r = await fetch(url, { headers: jsonHeaders() });
    const list = r.ok ? await r.json() : [];

    const ordered = (Array.isArray(list) ? list : [])
      .sort((a, b) => (a.distance_km ?? 9e9) - (b.distance_km ?? 9e9))
      .slice(0, Math.max(1, limit | 0));

    for (const c of ordered) {
      try { await ensurePreviewLineForCandidate(c, ride); } catch { }
      await new Promise(res => setTimeout(res, 90));
    }
  } catch (e) {
    console.warn('[previewCandidatesFor] error', e);
  }
}

export async function drawPreviewLinesStagger(ride, candidates, topN = 12) {
  const ordered = [...(candidates || [])]
    .sort((a, b) => (a.distance_km ?? 9e9) - (b.distance_km ?? 9e9))
    .slice(0, topN);

  for (let i = 0; i < ordered.length; i++) {
    const c = ordered[i];
    try { await ensurePreviewLineForCandidate(c, ride); } catch { }
    await new Promise(res => setTimeout(res, 90));
  }

  const best = ordered[0];
  if (best) {
    const id = best.id || best.driver_id;
    const pin = driverPins.get(Number(id));
    if (pin?.previewLine) {
      try { pin.previewLine.setStyle(suggestedLineSelectedStyle()); pin.previewLine.bringToFront(); } catch { }
    }
  }
}

export function clearAssignArtifacts() {
  try {
    if (typeof _assignPickupMarker !== 'undefined' && _assignPickupMarker) {
      const lyr = (typeof layerSuggested !== 'undefined') ? layerSuggested : layerRoute;
      try { lyr.removeLayer(_assignPickupMarker); } catch { }
      _assignPickupMarker = null;
    }
    if (typeof _assignPreviewLine !== 'undefined' && _assignPreviewLine) {
      try { layerRoute.removeLayer(_assignPreviewLine); } catch { }
      _assignPreviewLine = null;
    }
  } catch { }

  try {
    const toRemove = [];
    layerRoute.eachLayer(l => {
      try {
        if (l instanceof L.Polyline) {
          const cls = String(l.options?.className || '');
          if (cls.includes('cc-suggested') || cls.includes('cc-preview')) toRemove.push(l);
        }
      } catch { }
    });
    toRemove.forEach(l => { try { layerRoute.removeLayer(l); } catch { } });
  } catch { }
}

export function clearSuggestedLines() {
  try {
    const toRemove = [];
    layerRoute.eachLayer(l => {
      try {
        if (l instanceof L.Polyline) {
          const cls = (l.options && l.options.className) || '';
          if (String(cls).includes('cc-suggested')) toRemove.push(l);
        }
      } catch { }
    });
    toRemove.forEach(l => { try { layerRoute.removeLayer(l); } catch { } });

    driverPins.forEach(e => {
      if (e.previewLine) {
        try { layerRoute.removeLayer(e.previewLine); } catch { }
        e.previewLine = null;
      }
    });

    try {
      if (typeof _assignPreviewLine !== 'undefined' && _assignPreviewLine) {
        layerRoute.removeLayer(_assignPreviewLine); _assignPreviewLine = null;
      }
    } catch { }
    try {
      if (typeof _assignPickupMarker !== 'undefined' && _assignPickupMarker) {
        (layerSuggested || layerRoute).removeLayer(_assignPickupMarker);
        _assignPickupMarker = null;
      }
    } catch { }
    try {
      if (window._assignPickupMarker) {
        try { (layerSuggested || layerRoute || map).removeLayer(window._assignPickupMarker); } catch { }
        try { map.removeLayer(window._assignPickupMarker); } catch { }
        window._assignPickupMarker = null;
      }
    } catch { }
  } catch (err) {
    console.warn('[clearSuggestedLines] error', err);
  }
}

export function onRideAssigned(ride) {
  clearSuggestedLines();
  if (ride?.driver_id && Number.isFinite(ride.origin_lat) && Number.isFinite(ride.origin_lng)) {
    showDriverToPickup(ride.driver_id, ride.origin_lat, ride.origin_lng);
  }
}

export async function highlightRideOnMap(ride) {
  try {
    if (!window.layerRoute && map) {
      layerRoute = L.layerGroup().addTo(map);
      window.layerRoute = layerRoute;
    }

    if (layerRoute?.clearLayers) layerRoute.clearLayers();
    if (fromMarker) { try { fromMarker.remove(); } catch { } fromMarker = null; }
    if (toMarker) { try { toMarker.remove(); } catch { } toMarker = null; }

    if (Array.isArray(window._stopMarkers)) {
      window._stopMarkers.forEach(m => { try { m.remove(); } catch { } });
    }
    window._stopMarkers = [];

    const from = (Number.isFinite(+ride.origin_lat) && Number.isFinite(+ride.origin_lng))
      ? { lat: +ride.origin_lat, lng: +ride.origin_lng } : null;
    const to = (Number.isFinite(+ride.dest_lat) && Number.isFinite(+ride.dest_lng))
      ? { lat: +ride.dest_lat, lng: +ride.dest_lng } : null;

    let stops = normalizeStops(ride);
    const hasStops = Array.isArray(stops) && stops.length > 0;

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

    let latlngs = null;
    if (!hasStops && ride.route_polyline) {
      try {
        const arr = decodePolyline(ride.route_polyline) || [];
        if (Array.isArray(arr) && arr.length >= 2) {
          const MAX_POINTS_AS_ROUTE = 800;
          latlngs = arr.length > MAX_POINTS_AS_ROUTE ? null : arr;
        }
      } catch { }
    }

    let driverLL = null;
    let driverMarker = null;

    if (ride.driver_id && typeof driverPins !== 'undefined') {
      const pin = driverPins.get(Number(ride.driver_id));
      if (pin?.marker && typeof pin.marker.getLatLng === 'function') {
        driverMarker = pin.marker;
        driverLL = driverMarker.getLatLng();

        try {
          if (window.__lastHighlightedDriver && window.__lastHighlightedDriver !== driverMarker) {
            setMarkerScale(window.__lastHighlightedDriver, scaleForZoom(map.getZoom()));
          }
          setMarkerScale(driverMarker, 1.35);
          driverMarker.setZIndexOffset(1200);
          window.__lastHighlightedDriver = driverMarker;
        } catch { }
      }
    }

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
      push(driverLL);
      if (from) push([from.lat, from.lng]);
    } else if (driverLL && st === 'on_board') {
      push(driverLL);
      if (to) push([to.lat, to.lng]);
    } else {
      if (from) push([from.lat, from.lng]);
      stops.forEach(s => {
        const lt = +s.lat, lg = +s.lng;
        if (Number.isFinite(lt) && Number.isFinite(lg)) push([lt, lg]);
      });
      if (to) push([to.lat, to.lng]);
    }

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

export async function focusRideOnMap(rideId) {
  const cached = window._ridesIndex?.get?.(rideId);
  if (cached) {
    const rideWithStops = await hydrateRideStops(cached);
    return highlightRideOnMap(rideWithStops);
  }

  const r = await fetch(`/api/rides/${rideId}`, {
    headers: jsonHeaders()
  });
  if (!r.ok) {
    console.error('GET /api/rides/{id} →', r.status, await r.text().catch(() => ''));
    alert('No se pudo cargar el viaje.');
    return;
  }
  const ride = await r.json();
  const rideWithStops = await hydrateRideStops(ride);

  if (window.__DISPATCH_DEBUG__) {
    console.log('[focusRideOnMap] GET /api/rides/' + rideId, debugBrief(rideWithStops), rideWithStops);
  }
  return highlightRideOnMap(rideWithStops);
}

export function openAssignFlow(ride) {
  const lat = Number(ride?.origin_lat), lng = Number(ride?.origin_lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    alert('Este servicio no tiene origen válido.');
    return;
  }
  
  fetch(`/api/dispatch/nearby-drivers?lat=${ride.origin_lat}&lng=${ride.origin_lng}&km=3`,
    { headers: jsonHeaders() })
    .then(r => r.ok ? r.json() : Promise.reject(r.status))
    .then(list => {
      let candidates = Array.isArray(list) ? list : [];
      if (!candidates.length) {
        driverPins.forEach((e, id) => {
          const ll = e.marker.getLatLng();
          const dk = _distKm(ride.origin_lat, ride.origin_lng, ll.lat, ll.lng);
          candidates.push({ id, name: e.name || ('Driver ' + id), vehicle_type: e.type || 'sedan', vehicle_plate: e.plate || '', distance_km: dk });
        });
        candidates = candidates.filter(c => c.distance_km <= 4);
      }
      renderAssignPanel(ride, candidates);
    })
    .catch(e => { console.warn('nearby-drivers error', e); renderAssignPanel(ride, []); });
}

export function renderAssignPanel(ride, candidates) {
  _assignRide = ride; _assignSelected = null;

  try {
    if (window._assignPickupMarker) {
      try { (layerSuggested || layerRoute || map).removeLayer(window._assignPickupMarker); } catch { }
      try { map.removeLayer(window._assignPickupMarker); } catch { }
      window._assignPickupMarker = null;
    }

    const lat = Number(ride?.origin_lat);
    const lng = Number(ride?.origin_lng);

    if (Number.isFinite(lat) && Number.isFinite(lng)) {
      const targetLayer = (typeof layerSuggested !== 'undefined' && map.hasLayer(layerSuggested))
        ? layerSuggested
        : (layerRoute || map);

      const ic = (typeof IconOrigin !== 'undefined') ? IconOrigin
        : (typeof IconDest !== 'undefined') ? IconDest
          : L.divIcon({ className: 'cc-pin-fallback', html: '<div style="width:16px;height:16px;border-radius:50%;background:#2F6FED;border:2px solid #fff"></div>', iconSize: [16, 16], iconAnchor: [8, 8] });

      window._assignPickupMarker = L.marker([lat, lng], {
        icon: ic,
        pane: (map.getPanes()?.markerPane ? 'markerPane' : undefined),
        zIndexOffset: 950,
        riseOnHover: true
      })
        .bindTooltip('Pasajero', { offset: [0, -26] })
        .addTo(targetLayer);
    }
  } catch (err) {
    console.warn('[assignPanel] no se pudo pintar pickup:', err);
  }

  const el = document.getElementById('assignPanelBody');
  if (!candidates.length) {
    el.innerHTML = `<div class="text-muted">No hay conductores cercanos.</div>`;
  } else {
    el.innerHTML = `<div class="list-group" id="assignList" style="max-height: 60vh; overflow:auto"></div>`;
    const list = el.querySelector('#assignList');

    const TOP_N = 12;
    const ordered = [...candidates]
      .sort((a, b) => (a.distance_km ?? 9e9) - (b.distance_km ?? 9e9))
      .slice(0, TOP_N);

    ordered.forEach(c => {
      const id = c.id || c.driver_id;
      const dist = (c.distance_km != null) ? `${c.distance_km.toFixed(2)} km` : '';
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
      item.dataset.driverId = id;
      item.innerHTML = `
        <div>
          <div><b>${c.name || ('Driver ' + id)}</b> <span class="text-muted">(${String(c.vehicle_type || 'sedan')})</span></div>
          <div class="small text-muted">${dist}</div>
        </div>
        <span class="badge bg-secondary">${id}</span>
      `;

      item.addEventListener('click', async () => {
        _assignSelected = id;
        list.querySelectorAll('.active').forEach(n => n.classList.remove('active'));
        item.classList.add('active');

        driverPins.forEach(e => {
          if (e.previewLine) try { e.previewLine.setStyle(suggestedLineStyle()); } catch { }
        });
        const line = await ensureDriverPreviewLine(id, ride);
        if (line) try { line.setStyle(suggestedLineSelectedStyle()); line.bringToFront(); } catch { }

        document.getElementById('btnDoAssign').disabled = !_assignSelected;
      });

      list.appendChild(item);
    });

    (async () => {
      for (let i = 0; i < ordered.length; i++) {
        const c = ordered[i];
        const id = c.id || c.driver_id;
        try { await ensureDriverPreviewLine(id, ride); } catch { }
        await new Promise(res => setTimeout(res, 90));
      }
      const firstBtn = list.querySelector('.list-group-item');
      if (firstBtn) firstBtn.click();
    })();
  }

  // botón Asignar
  document.getElementById('btnDoAssign').onclick = async () => {
    if (!_assignSelected || !_assignRide) return;
    const btn = document.getElementById('btnDoAssign');
    btn.disabled = true;
    try {
      const r = await fetch('/api/dispatch/assign', {
        method: 'POST',
        headers: jsonHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ ride_id: _assignRide.id, driver_id: _assignSelected })
      });
      const j = await r.json().catch(() => ({}));
      if (!r.ok || j.ok === false) throw new Error(j?.msg || ('HTTP ' + r.status));
      clearAllPreviews();
      if (_assignPickupMarker) { try { layerRoute.removeLayer(_assignPickupMarker); } catch { } _assignPickupMarker = null; }
      if (_assignPanel) _assignPanel.hide();
      refreshDispatch();
    } catch (e) {
      alert('No se pudo asignar: ' + (e.message || e));
    } finally {
      btn.disabled = false;
    }
  };

  const panelEl = document.getElementById('assignPanel');
  _assignPanel = _assignPanel || new bootstrap.Offcanvas(panelEl, { backdrop: false });

  panelEl.addEventListener('hidden.bs.offcanvas', () => {
    onRideAssigned(_assignRide);
  }, { once: false });

  _assignPanel.show();
}