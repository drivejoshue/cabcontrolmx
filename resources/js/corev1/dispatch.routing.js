/* resources/js/pages/dispatch/routing.js */
import { qs, jsonHeaders } from './core.js';
import { layerRoute, routeStyle } from './map.js';
import { gDirService } from './google.js';
import { map } from './map.js';
import { fromMarker, toMarker, stop1Marker, stop2Marker } from './map.js';
import { reverseGeocode } from './google.js';

let _quoteTimer = null;
let __lastQuote = null;

function setInput(sel, val) {
  const el = qs(sel);
  if (el) el.value = val;
}

// Añade la función clearAllStops si se usa en invertRoute
export function clearAllStops() {
  qs('#stop1Lat').value = ''; qs('#stop1Lng').value = '';
  qs('#inStop1').value = ''; qs('#stop1Row').style.display = 'none';
  if (stop1Marker) { stop1Marker.remove(); stop1Marker = null; }
  
  qs('#stop2Lat').value = ''; qs('#stop2Lng').value = '';
  qs('#inStop2').value = ''; qs('#stop2Row').style.display = 'none';
  if (stop2Marker) { stop2Marker.remove(); stop2Marker = null; }
}


export function decodePolyline(str) {
  let index = 0, lat = 0, lng = 0, coords = [];
  while (index < str.length) {
    let b, shift = 0, result = 0;
    do { b = str.charCodeAt(index++) - 63; result |= (b & 0x1f) << shift; shift += 5; } while (b >= 0x20);
    const dlat = ((result & 1) ? ~(result >> 1) : (result >> 1)); lat += dlat;
    shift = 0; result = 0;
    do { b = str.charCodeAt(index++) - 63; result |= (b & 0x1f) << shift; shift += 5; } while (b >= 0x20);
    const dlng = ((result & 1) ? ~(result >> 1) : (result >> 1)); lng += dlng;
    coords.push([lat * 1e-5, lng * 1e-5]);
  }
  return coords;
}

export function pointsFromGoogleRoute(route) {
  if (route?.overview_path?.length) {
    return route.overview_path.map(p => [p.lat(), p.lng()]);
  }
  if (route?.overview_polyline?.points) {
    return decodePolyline(route.overview_polyline.points);
  }
  return [];
}

export function getAB() {
  const a = [parseFloat(qs('#fromLat')?.value), parseFloat(qs('#fromLng')?.value)];
  const b = [parseFloat(qs('#toLat')?.value), parseFloat(qs('#toLng')?.value)];
  return {
    a, b,
    hasA: Number.isFinite(a[0]) && Number.isFinite(a[1]),
    hasB: Number.isFinite(b[0]) && Number.isFinite(b[1])
  };
}

export function getStops() {
  const s1 = [parseFloat(qs('#stop1Lat')?.value), parseFloat(qs('#stop1Lng')?.value)];
  const s2 = [parseFloat(qs('#stop2Lat')?.value), parseFloat(qs('#stop2Lng')?.value)];
  const arr = [];
  if (Number.isFinite(s1[0]) && Number.isFinite(s1[1])) arr.push(s1);
  if (Number.isFinite(s2[0]) && Number.isFinite(s2[1])) arr.push(s2);
  return arr;
}

export function clearQuoteUi() {
  const fa = qs('#fareAmount'); if (fa) fa.value = '';
  const rs = document.getElementById('routeSummary');
  if (rs) rs.innerText = `Ruta: — · Zona: — · Cuando: ${qs('#when-later')?.checked ? 'después' : 'ahora'}`;
}

export async function drawRoute({ quiet = false } = {}) {
  try {
    layerRoute.clearLayers();
    const { a, b, hasA, hasB } = getAB();
    const rs = document.getElementById('routeSummary');

    if (!hasA || !hasB) {
      clearQuoteUi();
      if (rs) {
        rs.innerText = 'Ruta: — · Zona: — · Cuando: ' +
          (qs('#when-later')?.checked ? 'después' : 'ahora');
      }
      return;
    }

    const stops = getStops();

    if (gDirService && window.google?.maps) {
      try {
        const waypts = stops.map(s => ({ location: { lat: s[0], lng: s[1] } }));
        const res = await new Promise((resolve, reject) => {
          gDirService.route({
            origin: { lat: a[0], lng: a[1] },
            destination: { lat: b[0], lng: b[1] },
            travelMode: google.maps.TravelMode.DRIVING,
            region: 'MX',
            provideRouteAlternatives: false,
            drivingOptions: {
              departureTime: new Date(),
              trafficModel: 'bestguess'
            },
            waypoints: waypts.length ? waypts : undefined,
          }, (r, s) => s === 'OK' ? resolve(r) : reject({ status: s, r }));
        });

        const route = res.routes?.[0];
        const leg = route?.legs?.[0];
        const pts = pointsFromGoogleRoute(route);

        if (pts.length) {
          const poly = L.polyline(
            pts,
            { pane: 'routePane', className: 'cc-route', ...routeStyle() }
          );
          poly.addTo(layerRoute);
          map.fitBounds(poly.getBounds().pad(0.15), { padding: [40, 40] });
        } else {
          if (!quiet) console.debug('[ROUTE] Directions OK sin polyline → OSRM');
          await drawRouteWithOSRM(a, b, stops, { quiet: true });
        }

        if (rs) {
          const dist = leg?.distance?.text || '—';
          const dura = (leg?.duration_in_traffic || leg?.duration)?.text || '—';
          rs.innerText = `Ruta: ${dist} · ${dura} · Cuando: ` +
            (qs('#when-later')?.checked ? 'después' : 'ahora');
        }
        autoQuoteIfReady();
        return;
      } catch (err) {
        if (!quiet) console.warn('[Directions] fallo, fallback OSRM:', err?.status || err);
      }
    }

    await drawRouteWithOSRM(a, b, stops, { quiet: true });
  } catch (err) {
    console.error('drawRoute error', err);
  }
}

export async function drawRouteWithOSRM(a, b, stops = [], { quiet = false } = {}) {
  const coords = [a, ...stops, b];
  const parts = coords.map(c => `${c[1]},${c[0]}`);
  const url = `https://router.project-osrm.org/route/v1/driving/${parts.join(';')}?overview=full&geometries=polyline`;

  const r = await fetch(url);
  if (!r.ok) throw new Error('OSRM 500');
  const j = await r.json();
  if (j.code !== 'Ok' || !j.routes?.length) throw new Error('OSRM bad');

  const route = j.routes[0];
  const poly = route.geometry;
  const latlngs = decodePolyline(poly);

  try { window.__routeLine?.remove(); } catch { }
  window.__routeLine = L.polyline(latlngs, routeStyle()).addTo(layerRoute);

  if (!quiet) {
    const rs = qs('#routeSummary');
    if (rs) {
      const km = (route.distance / 1000).toFixed(1) + ' km';
      const min = Math.round(route.duration / 60) + ' min';
      rs.innerText = `Ruta: ${km} · ${min}`;
    }
  }
  map.fitBounds(window.__routeLine.getBounds(), { padding: [20, 20] });
  return { distance_m: route.distance | 0, duration_s: route.duration | 0, polyline: poly };
}

export function _hasAB() {
  const aLat = parseFloat(qs('#fromLat')?.value);
  const aLng = parseFloat(qs('#fromLng')?.value);
  const bLat = parseFloat(qs('#toLat')?.value);
  const bLng = parseFloat(qs('#toLng')?.value);
  return Number.isFinite(aLat) && Number.isFinite(aLng) &&
    Number.isFinite(bLat) && Number.isFinite(bLng);
}

export async function _doAutoQuote() {
  if (!_hasAB()) { return; }
  const aLat = parseFloat(qs('#fromLat').value);
  const aLng = parseFloat(qs('#fromLng').value);
  const bLat = parseFloat(qs('#toLat').value);
  const bLng = parseFloat(qs('#toLng').value);

  try {
    const stops = getStops();
    const r = await fetch('/api/dispatch/quote', {
      method: 'POST',
      headers: jsonHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({
        origin: { lat: aLat, lng: aLng },
        destination: { lat: bLat, lng: bLng },
        stops: stops.map(s => ({ lat: s[0], lng: s[1] })),
        round_to_step: 1.00
      })
    });

    const j = await r.json();
    if (!r.ok || j.ok === false) throw new Error(j?.msg || ('HTTP ' + r.status));

    __lastQuote = j;

    const fa = qs('#fareAmount');
    if (fa) fa.value = j.amount;

    const rs = document.getElementById('routeSummary');
    if (rs) {
      const km = (j.distance_m / 1000).toFixed(1) + ' km';
      const min = Math.round(j.duration_s / 60) + ' min';
      rs.innerText = `Ruta: ${km} · ${min} · Tarifa: $${j.amount}`;
    }
  } catch (e) {
    console.warn('[quote] fallo', e);
  }
}

export function autoQuoteIfReady() {
  clearTimeout(_quoteTimer);
  _quoteTimer = setTimeout(_doAutoQuote, 450);
}

export async function recalcQuoteUI() {
  try {
    const fromLat = parseFloat(qs('#fromLat')?.value || '');
    const fromLng = parseFloat(qs('#fromLng')?.value || '');
    const toLat = parseFloat(qs('#toLat')?.value || '');
    const toLng = parseFloat(qs('#toLng')?.value || '');

    if (!Number.isFinite(fromLat) || !Number.isFinite(fromLng) ||
      !Number.isFinite(toLat) || !Number.isFinite(toLng)) {
      console.warn('[quote] faltan coordenadas de origen/destino');
      return;
    }

    const stops = [];
    const s1lat = parseFloat(qs('#stop1Lat')?.value || '');
    const s1lng = parseFloat(qs('#stop1Lng')?.value || '');
    if (Number.isFinite(s1lat) && Number.isFinite(s1lng)) stops.push({ lat: s1lat, lng: s1lng });

    const s2lat = parseFloat(qs('#stop2Lat')?.value || '');
    const s2lng = parseFloat(qs('#stop2Lng')?.value || '');
    if (Number.isFinite(s2lat) && Number.isFinite(s2lng)) stops.push({ lat: s2lat, lng: s2lng });

    const body = {
      origin: { lat: fromLat, lng: fromLng },
      destination: { lat: toLat, lng: toLng },
      stops,
      round_to_step: 1
    };

    const url = window.__QUOTE_URL__ || '/api/dispatch/quote';
    const resp = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(body)
    });

    if (!resp.ok) {
      console.warn('[quote] HTTP', resp.status);
      return;
    }

    const data = await resp.json();
    const amount = data?.amount;
    if (amount == null) {
      console.warn('[quote] respuesta sin amount', data);
      return;
    }

    const amt = qs('#fareAmount'); if (amt) amt.value = amount;
    const rs = qs('#routeSummary');
    if (rs) {
      const km = ((data.distance_m ?? 0) / 1000).toFixed(2);
      const min = Math.round((data.duration_s ?? 0) / 60);
      const sn = data.stops_n ?? stops.length;
      rs.innerText = `Ruta: ${km} km · ${min} min · Paradas: ${sn} · Tarifa: $${amount}`;
    }
  } catch (e) {
    console.warn('[quote] recalcQuoteUI error', e);
  }
}