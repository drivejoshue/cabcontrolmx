// resources/js/pages/partner-monitor/rides.js
import L from 'leaflet';
import { getJson } from './net';

export function createRidesController(ctx) {
  // ============
  // Layer focus
  // ============

  const ICONS = {
  pickup: L.icon({
    iconUrl: '/images/origen.png',
    iconSize: [28, 28],
    iconAnchor: [14, 28],
  }),
  drop: L.icon({
    iconUrl: '/images/destino.png',
    iconSize: [28, 28],
    iconAnchor: [14, 28],
  }),
  stop: L.icon({
    iconUrl: '/images/stopride.png',
    iconSize: [22, 22],
    iconAnchor: [11, 22],
  }),
};
const FINAL = new Set(['finished','completed','canceled','cancelled','expired']);

function isFinalStatus(st) {
  return FINAL.has(String(st || '').toLowerCase());
}


  const focusLayer = L.layerGroup();
  focusLayer.addTo(ctx.map);

  let routeLine = null;
  let stopMarkers = [];
  let pickupMarker = null;
  let dropMarker = null;

  let rideBar = null;
  let lastPayload = null;

  // expón limpiador al ctx para que lo use el botón "Limpiar" del card
  ctx.clearRideFocus = clearRideFocus;

  let selectedRideId = null;
let lastSig = null;
let inflight = false;

async function refreshFocusedIfChanged(listRow) {
  if (!selectedRideId) return;

  // 👇 Firma: si cambia status (aunque updated_at no cambie), recargamos
  const sig = `${listRow?.status || ''}|${listRow?.updated_at || ''}`;

  if (sig && sig === lastSig) return;
  lastSig = sig;

  if (inflight) return;
  inflight = true;
  try {
    await focusRideById(selectedRideId, { center: false });
  } finally {
    inflight = false;
  }
}


function syncFromPolling(activeRides) {
  if (!selectedRideId) return;

  const row = (activeRides || []).find(x => Number(x.ride_id ?? x.id) === Number(selectedRideId));
  if (!row) {
    clearRideFocus();
    selectedRideId = null;
    lastListUpdatedAt = null;
    return;
  }

  // refresca solo si cambió algo en snapshot
  refreshFocusedIfChanged(row);
}

  function rideUrl(id) {
    return `/partner/monitor/rides/${Number(id)}`;
  }

  // =========================
  // Ride Peek Card (floating)
  // =========================
  function mountRideCard() {
    if (rideBar) return rideBar;

    const host = ctx.map.getContainer();
    const card = document.createElement('div');
    card.className = 'pm-ridebar';
    card.innerHTML = `
      <div class="pm-ridebar-left">
        <div class="pm-ridebar-av"><img alt="" /></div>
        <div class="pm-ridebar-meta">
          <div class="pm-ridebar-title">—</div>
          <div class="pm-ridebar-sub">—</div>
        </div>
      </div>

      <div class="pm-ridebar-mid">
        <div class="pm-timeline" data-tl></div>
      </div>

      <div class="pm-ridebar-right">
        <button type="button" class="pm-mini" data-act="center">Centrar</button>
        <button type="button" class="pm-mini pm-danger" data-act="clear">Limpiar</button>
      </div>
    `;
    host.appendChild(card);

    let payload = null;

    const esc = (s) => String(s ?? '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');

    function stepIndex(status) {
      const s = String(status || '').toLowerCase();
      if (s === 'requested' || s === 'pending' || s === 'scheduled') return 0;
      if (s === 'offered') return 1;
      if (s === 'accepted') return 2;
      if (s === 'arrived') return 3;
      if (s === 'on_board' || s === 'en_route' || s === 'on_trip') return 4;
      if (s === 'finished' || s === 'completed') return 5;
      if (s === 'canceled' || s === 'cancelled') return 5;
      return 0;
    }

    function renderTimeline(ride, offer) {
      const tl = card.querySelector('[data-tl]');
      if (!tl) return;

      const steps = [
        { t:'Pedido'   },
        { t:'Oferta'   },
        { t:'Asignado' },
        { t:'Llegó'    },
        { t:'Abordado' },
        { t:'Fin'      },
      ];

      const idx = stepIndex(ride.status);

      tl.innerHTML = steps.map((st, i) => {
        const on = i <= idx ? 'on' : '';
        return `
          <div class="pm-step ${on}">
            <div class="pm-dot"></div>
            <div class="pm-step-t">${esc(st.t)}</div>
          </div>
        `;
      }).join('');
    }

    function show(p) {
      payload = p || null;

      const ride = p?.ride || {};
      const offer = p?.offer || null;

      const rideId = Number(ride.ride_id ?? ride.id ?? 0);
      const pax = ride.passenger_name || 'Pasajero';
      const phone = ride.passenger_phone ? ` · ${ride.passenger_phone}` : '';

      const amt = (ride.quoted_amount != null)
        ? `$${Number(ride.quoted_amount).toFixed(2)}`
        : '—';

      const eco = ride.vehicle_economico
        ? String(ride.vehicle_economico)
        : (ride.driver_id ? `#${ride.driver_id}` : '—');

      const plate = ride.vehicle_plate ? ` · ${ride.vehicle_plate}` : '';

      card.querySelector('.pm-ridebar-title').textContent = `Ride #${rideId} · ${pax}`;
      card.querySelector('.pm-ridebar-sub').textContent = `${amt} · ${eco}${plate}${phone}`.trim();

      // avatar pasajero
      const img = card.querySelector('.pm-ridebar-av img');
      const av = ride.passenger_avatar_url || ride.passenger_avatar || '';
      if (img) {
        img.style.display = av ? 'block' : 'none';
        img.src = av || '';
      }

      renderTimeline(ride, offer);

      card.classList.add('show');
    }

    function hide() {
      payload = null;
      card.classList.remove('show');
    }

    card.querySelector('[data-act="clear"]')?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      ctx.clearRideFocus?.();
    });

    card.querySelector('[data-act="center"]')?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const ride = payload?.ride || {};
      fitToRide(ride);
    });

    rideBar = { show, hide };
    return rideBar;
  }

  // =========================
  // Drawing helpers
  // =========================
  function clearMarkers() {
    if (pickupMarker) { focusLayer.removeLayer(pickupMarker); pickupMarker = null; }
    if (dropMarker) { focusLayer.removeLayer(dropMarker); dropMarker = null; }
    for (const m of stopMarkers) focusLayer.removeLayer(m);
    stopMarkers = [];

    if (routeLine) { focusLayer.removeLayer(routeLine); routeLine = null; }
  }

  function toLatLng(lat, lng) {
    const a = lat != null ? Number(lat) : null;
    const b = lng != null ? Number(lng) : null;
    if (!Number.isFinite(a) || !Number.isFinite(b)) return null;
    return [a, b];
  }

  function extractStops(ride) {
    // soporta: stop1_lat/stop1_lng, stop2_lat/stop2_lng o stops/stops_json
    const stops = [];

    const s1 = toLatLng(ride.stop1_lat, ride.stop1_lng);
    const s2 = toLatLng(ride.stop2_lat, ride.stop2_lng);
    if (s1) stops.push(s1);
    if (s2) stops.push(s2);

    const raw = ride.stops ?? ride.stops_json ?? null;
    if (raw && !stops.length) {
      try {
        const arr = typeof raw === 'string' ? JSON.parse(raw) : raw;
        if (Array.isArray(arr)) {
          for (const it of arr) {
            const p = toLatLng(it.lat ?? it.latitude, it.lng ?? it.longitude);
            if (p) stops.push(p);
          }
        }
      } catch {}
    }

    return stops;
  }

  function drawRoute(ride) {
    const pickup = toLatLng(ride.pickup_lat ?? ride.origin_lat, ride.pickup_lng ?? ride.origin_lng);
    const drop = toLatLng(ride.drop_lat ?? ride.dest_lat ?? ride.destination_lat, ride.drop_lng ?? ride.dest_lng ?? ride.destination_lng);
    const stops = extractStops(ride);

    // markers
   if (pickup) pickupMarker = L.marker(pickup, { icon: ICONS.pickup }).addTo(focusLayer);
for (const s of stops) stopMarkers.push(L.marker(s, { icon: ICONS.stop }).addTo(focusLayer));
if (drop) dropMarker = L.marker(drop, { icon: ICONS.drop }).addTo(focusLayer);

    // polyline principal
    const poly = ride.route_polyline ?? ride.polyline ?? ride.route ?? null;
    const pts = decodeAnyPolyline(poly);

    if (pts.length >= 2) {
      routeLine = L.polyline(pts, { weight: 5, opacity: 0.9, className: 'pm-route' }).addTo(focusLayer);
      return true;
    }

    // fallback: línea simple pickup -> stops -> drop
    const chain = [];
    if (pickup) chain.push(pickup);
    for (const s of stops) chain.push(s);
    if (drop) chain.push(drop);

    if (chain.length >= 2) {
      routeLine = L.polyline(chain, { weight: 4, opacity: 0.7, dashArray: '6 8', className: 'pm-route-fallback' }).addTo(focusLayer);
      return true;
    }

    return false;
  }

  function fitToRide(ride) {
    const pts = [];

    const pickup = toLatLng(ride.pickup_lat ?? ride.origin_lat, ride.pickup_lng ?? ride.origin_lng);
    const drop = toLatLng(ride.drop_lat ?? ride.dest_lat ?? ride.destination_lat, ride.drop_lng ?? ride.dest_lng ?? ride.destination_lng);
    const stops = extractStops(ride);

    if (pickup) pts.push(pickup);
    for (const s of stops) pts.push(s);
    if (drop) pts.push(drop);

    // si ya hay routeLine, úsala (mejor bounds)
    if (routeLine) {
      ctx.map.fitBounds(routeLine.getBounds(), { padding: [28, 28] });
      return;
    }

    if (pts.length === 1) ctx.map.setView(pts[0], Math.max(ctx.map.getZoom(), 16));
    if (pts.length >= 2) ctx.map.fitBounds(pts, { padding: [28, 28] });
  }

  // =========================
  // Public API
  // =========================
  function clearRideFocus() {
    clearMarkers();
    lastPayload = null;
    rideBar?.hide?.();
  }

  async function focusRideById(rideId, opts = {}) {
    const id = Number(rideId || 0);
    if (!id) return;
    selectedRideId = id;
    lastSig = null;

    // monta card si no existe
    mountRideCard();

    const j = await getJson(rideUrl(id));
    if (!j?.ok) return;

    const ride = j.ride || j.item || {};
    const offer = j.offer || j.current_offer || null;

    // normaliza ids por si vienen con ride_id
    if (ride && ride.ride_id == null && ride.id != null) ride.ride_id = ride.id;

    clearMarkers();
    lastPayload = { ride, offer };

    drawRoute(ride);

    // muestra card profesional (avatar + timeline)
    rideBar?.show?.(lastPayload);

    // centra si se pide
    const center = (opts.center !== false);
    if (center) fitToRide(ride);

    // opcional: enfocar driver
    if (ride.driver_id && ctx.focusDriverById) {
      // no fuerza cámara, solo selección/centro si ya lo tienes implementado
      ctx.focusDriverById(Number(ride.driver_id));
    }
  }

  return {
    mountRideCard,
    focusRideById,
    clearRideFocus,
    syncFromPolling,
  };
}

// =====================================================
// Polyline decode (robusto)
// - encoded polyline (Google)
// - JSON [[lat,lng],...]
// - GeoJSON LineString
// =====================================================
function decodeAnyPolyline(input) {
  if (!input) return [];

  // array directo
  if (Array.isArray(input)) {
    return input
      .map(p => Array.isArray(p) ? [Number(p[0]), Number(p[1])] : null)
      .filter(p => p && Number.isFinite(p[0]) && Number.isFinite(p[1]));
  }

  // string: intenta JSON/GeoJSON
  if (typeof input === 'string') {
    const s = input.trim();
    if (!s) return [];

    if (s[0] === '[' || s[0] === '{') {
      try {
        const obj = JSON.parse(s);

        // JSON [[lat,lng],...]
        if (Array.isArray(obj)) {
          return obj
            .map(p => Array.isArray(p) ? [Number(p[0]), Number(p[1])] : null)
            .filter(p => p && Number.isFinite(p[0]) && Number.isFinite(p[1]));
        }

        // GeoJSON
        if (obj && obj.type === 'LineString' && Array.isArray(obj.coordinates)) {
          return obj.coordinates
            .map(c => [Number(c[1]), Number(c[0])])
            .filter(p => Number.isFinite(p[0]) && Number.isFinite(p[1]));
        }
        if (obj && obj.type === 'Feature' && obj.geometry?.type === 'LineString') {
          const coords = obj.geometry.coordinates || [];
          return coords
            .map(c => [Number(c[1]), Number(c[0])])
            .filter(p => Number.isFinite(p[0]) && Number.isFinite(p[1]));
        }
      } catch {
        // si no es JSON válido, cae a decoder encoded polyline
      }
    }

    // encoded polyline Google
    return decodeGooglePolyline(s);
  }

  return [];
}

function decodeGooglePolyline(str) {
  // estándar Google polyline encoding
  let index = 0;
  const len = str.length;
  let lat = 0;
  let lng = 0;
  const out = [];

  while (index < len) {
    let b, shift = 0, result = 0;

    do {
      b = str.charCodeAt(index++) - 63;
      result |= (b & 0x1f) << shift;
      shift += 5;
    } while (b >= 0x20);

    const dlat = (result & 1) ? ~(result >> 1) : (result >> 1);
    lat += dlat;

    shift = 0;
    result = 0;

    do {
      b = str.charCodeAt(index++) - 63;
      result |= (b & 0x1f) << shift;
      shift += 5;
    } while (b >= 0x20);

    const dlng = (result & 1) ? ~(result >> 1) : (result >> 1);
    lng += dlng;

    out.push([lat / 1e5, lng / 1e5]);
  }

  return out;
}
