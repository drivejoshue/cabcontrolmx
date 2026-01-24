import L from 'leaflet';
import { toNum, pickLatLng, escapeHtml } from './net';
import {
  iconUrl, makeCarIcon, scaleForZoom, smoothBearing,
  setMarkerBearing, setMarkerScale, setCarSprite
} from './icons';

export function createDriversController(ctx) {
  const driverPins = new Map(); // driverId -> { marker, seenAtMs }
  let followDriverId = null;
  let followOn = true;

  // throttles (evita re-render por cada ping)
  let hudScheduled = false;
  let listScheduled = false;

let activeFilter = 'all'; // all | free | busy | queue | off

function matchFilter(row, filter) {
  const v = visualState(row);

  if (filter === 'all') return true;
  if (filter === 'free') return v === 'free';

  // "busy" incluye todos los estados operativos no libres
  if (filter === 'busy') return ['busy','accepted','en_route','arrived','on_board'].includes(v);

  // "queue" lo usamos para cosas tipo offered/requested/scheduled (ajústalo a tu criterio)
  if (filter === 'queue') return ['offered','requested','scheduled'].includes(v);

  if (filter === 'off') return v === 'offline';
  return true;
}

function syncMarkerVisibility(marker) {
  const row = marker.__pmRow || {};
  const show = matchFilter(row, activeFilter);

  const isOn = ctx.layerDrivers.hasLayer(marker);
  if (show && !isOn) ctx.layerDrivers.addLayer(marker);
  if (!show && isOn) ctx.layerDrivers.removeLayer(marker);
}

function applyDriverFilter(filter) {
  activeFilter = filter || 'all';
  for (const { marker } of driverPins.values()) {
    syncMarkerVisibility(entry.marker);

  }
}

// Opcional: contadores para HUD (del estado real de cada marker)
function getDriverCounts() {
  const c = { total: 0, free: 0, busy: 0, queue: 0, off: 0 };
  for (const { marker } of driverPins.values()) {
    const v = visualState(marker.__pmRow || {});
    c.total++;
    if (v === 'free') c.free++;
    else if (v === 'offline') c.off++;
    else if (['offered','requested','scheduled'].includes(v)) c.queue++;
    else c.busy++;
  }
  return c;
}

  function visualState(row) {
    const r = String(row.ride_status || '').toLowerCase();
    const ds = String(row.driver_status || row.status || '').toLowerCase();
    const shiftOpen = row.shift_open === 1 || row.shift_open === true;
    const fresh = row.is_fresh === 1 || row.is_fresh === true;

    if (!shiftOpen) return 'offline';
    if (!fresh) return 'offline';
    if (ds === 'offline') return 'offline';

    if (r === 'on_board') return 'on_board';
    if (r === 'arrived') return 'arrived';
    if (r === 'en_route') return 'en_route';
    if (r === 'accepted') return 'accepted';
    if (r === 'offered') return 'offered';
    if (ds === 'busy') return 'busy';
    return 'free';
  }

  function isInQueue(row) {
    // deja varios alias por si backend cambia
    const v =
      row.in_queue ?? row.is_in_queue ?? row.on_queue ??
      row.stand_in_queue ?? row.queue ?? row.queue_state;
    if (v === 1 || v === true) return true;
    const s = String(v || '').toLowerCase();
    return s === 'in_queue' || s === 'queue' || s === 'en_cola';
  }

  function stateLabel(vstate, row) {
    if (vstate === 'offline') return { text: 'OFF', cls: 'pm-s-off' };
    if (isInQueue(row)) return { text: 'COLA', cls: 'pm-s-queue' };

    if (vstate === 'free') return { text: 'LIBRE', cls: 'pm-s-free' };
    if (vstate === 'busy') return { text: 'OCUP', cls: 'pm-s-busy' };

    // rides (más específicos)
    if (vstate === 'offered') return { text: 'OFERTA', cls: 'pm-s-offered' };
    if (vstate === 'accepted') return { text: 'ACEPT', cls: 'pm-s-accepted' };
    if (vstate === 'en_route') return { text: 'EN RUTA', cls: 'pm-s-enroute' };
    if (vstate === 'arrived') return { text: 'LLEGÓ', cls: 'pm-s-arrived' };
    if (vstate === 'on_board') return { text: 'A BORDO', cls: 'pm-s-onboard' };

    return { text: '—', cls: 'pm-s-free' };
  }

  function buildDriverTooltip(row) {
    const eco = row.vehicle_economico ?? row.economico ?? row.code ?? '';
    const plate = row.vehicle_plate ?? row.plate ?? row.placa ?? '';
    const name = row.driver_name ?? row.name ?? '';
    const status = row.driver_status ?? row.status ?? '';
    const rideStatus = row.ride_status ?? '';
    const updated = row.updated_at ?? row.last_seen_at ?? '';

    const parts = [];
    if (eco) parts.push(`<b>${escapeHtml(eco)}</b>`);
    if (name) parts.push(escapeHtml(name));
    if (plate) parts.push(`Placa: ${escapeHtml(plate)}`);
    if (rideStatus) parts.push(`Ride: ${escapeHtml(rideStatus)}`);
    if (status) parts.push(`Estado: ${escapeHtml(status)}`);
    if (updated) parts.push(`<span style="opacity:.75">${escapeHtml(updated)}</span>`);
    return parts.join('<br>');
  }

  function applyDriverVisual(marker, row) {
    const vstate = visualState(row);
    const vtype = String(row.vehicle_type || 'sedan').toLowerCase();
    const desiredSrc = iconUrl(vtype, vstate);

    const bearingRaw = toNum(row.bearing ?? row.heading) ?? 0;
    const prev = marker.__pmBearing;
    const bearing = smoothBearing(prev, bearingRaw);
    marker.__pmBearing = bearing;

    setMarkerBearing(marker, bearing);
    setMarkerScale(marker, scaleForZoom(ctx.map.getZoom()));

    if (marker.__pmIconSrc !== desiredSrc) {
      marker.__pmIconSrc = desiredSrc;
      setCarSprite(marker, desiredSrc);
    }
  }

  function removeDriverPin(id) {
    const entry = driverPins.get(Number(id));
    if (!entry) return;

    ctx.layerDrivers.removeLayer(entry.marker);
    driverPins.delete(Number(id));

    if (followDriverId === Number(id)) {
      followDriverId = null;
      followOn = false;
    }

    scheduleHudUpdate();
    scheduleListUpdate();
  }

  function startFollow(id) {
    const driverId = Number(id);
    if (!driverId) return;
    followDriverId = driverId;
    followOn = true;
  }

  function stopFollow() {
    followOn = false;
    followDriverId = null;
  }

  function buildSelectionPayload(id, row, latlng) {
    const eco = row.vehicle_economico ?? row.economico ?? row.code ?? `#${id}`;
    const plate = row.vehicle_plate ?? row.plate ?? row.placa ?? '';
    const name = row.driver_name ?? row.name ?? '';
    const vstate = visualState(row);
    const st = stateLabel(vstate, row);

    const html = `
      <div class="pm-sel-grid">
        <div><span class="pm-muted">Econ:</span> <b>${escapeHtml(String(eco))}</b></div>
        ${name ? `<div><span class="pm-muted">Conductor:</span> ${escapeHtml(String(name))}</div>` : ''}
        ${plate ? `<div><span class="pm-muted">Placa:</span> ${escapeHtml(String(plate))}</div>` : ''}
        <div><span class="pm-muted">Estado:</span> <span class="pm-pill ${st.cls}">${escapeHtml(st.text)}</span></div>
      </div>
    `;

    return {
      kind: 'driver',
      driverId: id,
      latlng,
      title: `${eco}`,
      html,
    };
  }

  function onSelectDriver(id) {
    const entry = driverPins.get(Number(id));
    if (!entry) return;

    const row = entry.marker.__pmRow || {};
    const ll = entry.marker.getLatLng();

    // card selección (si ya la montaste)
    ctx.sel?.show?.(buildSelectionPayload(Number(id), row, ll));

    // highlight fila
    ctx.selectedDriverId = Number(id);
    scheduleListUpdate();
  }

  function upsertDriver(row) {
    const id = toNum(row.driver_id ?? row.id);
    const ll = pickLatLng(row);
    if (!id || !ll) return;

    const shiftOpen = row.shift_open === 1 || row.shift_open === true;
    if (!shiftOpen) {
      removeDriverPin(id);
      return;
    }

    const nowMs = Date.now();
    let entry = driverPins.get(id);

    const vstate = visualState(row);
    const vtype = String(row.vehicle_type || 'sedan').toLowerCase();

    if (!entry) {
      const m = L.marker([ll.lat, ll.lng], {
        icon: makeCarIcon(vtype, vstate),
        interactive: true,
        pane: 'pmDriversPane',
      });

      m.__pmDriverId = id;
      m.__pmRow = row;
      m.__pmIconSrc = iconUrl(vtype, vstate);

      m.bindTooltip('', { direction: 'top', opacity: 0.92, offset: [0, -12] });

      m.on('add', () => {
        applyDriverVisual(m, row);
        m.setTooltipContent(buildDriverTooltip(row));
      });

      m.on('click', () => {
        startFollow(id);
        focusDriverById(id);
        onSelectDriver(id);
      });

      m.addTo(ctx.layerDrivers);

      entry = { marker: m, seenAtMs: nowMs };
      driverPins.set(id, entry);
    } else {
      entry.marker.setLatLng([ll.lat, ll.lng]);
      entry.seenAtMs = nowMs;
      entry.marker.__pmRow = row;

      applyDriverVisual(entry.marker, row);
      entry.marker.setTooltipContent(buildDriverTooltip(row));
    }

    // follow suave
    if (followOn && followDriverId === id) {
      ctx.map.panTo([ll.lat, ll.lng], { animate: true, duration: 0.25 });
    }

    // aplica filtro si existe
    applyDriverFilter(ctx.driverFilter);

    scheduleHudUpdate();
    scheduleListUpdate();
  }

  function focusDriverById(id) {
    const entry = driverPins.get(Number(id));
    if (!entry) return;
    const ll = entry.marker.getLatLng();
    const targetZoom = Math.max(ctx.map.getZoom(), 15);
    ctx.map.flyTo(ll, targetZoom, { duration: 0.45 });
    entry.marker.openTooltip();
  }

  function markStaleDrivers(visibleIds) {
    const now = Date.now();
    const STALE_MS = 90_000;

    for (const [id, entry] of driverPins.entries()) {
      if (visibleIds.has(id)) continue;
      if (now - entry.seenAtMs >= STALE_MS) {
        const row = { ...(entry.marker.__pmRow || {}), driver_status: 'offline', status: 'offline' };
        entry.marker.__pmRow = row;
        applyDriverVisual(entry.marker, row);
        entry.marker.setTooltipContent(buildDriverTooltip(row));
      }
    }

    applyDriverFilter(ctx.driverFilter);
    scheduleHudUpdate();
    scheduleListUpdate();
  }

  function fitDrivers() {
    const pts = [];
    for (const { marker } of driverPins.values()) {
      const ll = marker.getLatLng();
      pts.push([ll.lat, ll.lng]);
    }
    if (!pts.length) {
      ctx.map.setView(ctx.initialCenter, ctx.initialZoom);
      return;
    }
    const b = L.latLngBounds(pts);
    ctx.map.fitBounds(b.pad(0.25));
  }

  function relativeAgeShort(msAgo) {
    const s = Math.max(0, Math.floor(msAgo / 1000));
    if (s < 60) return `${s}s`;
    const m = Math.floor(s / 60);
    if (m < 60) return `${m}m`;
    const h = Math.floor(m / 60);
    return `${h}h`;
  }

  function computeHudStats() {
    let total = 0, idle = 0, busy = 0, queue = 0, offline = 0;

    for (const { marker } of driverPins.values()) {
      total++;
      const row = marker.__pmRow || {};
      const v = visualState(row);

      if (v === 'offline') { offline++; continue; }
      if (isInQueue(row)) { queue++; continue; }

      if (v === 'free') idle++;
      else busy++; // busy + offered/accepted/en_route/arrived/on_board
    }

    return { total, idle, busy, queue, offline };
  }

  function scheduleHudUpdate() {
    if (hudScheduled) return;
    hudScheduled = true;
    requestAnimationFrame(() => {
      hudScheduled = false;
      const stats = computeHudStats();
      ctx.hud?.setDriverStats?.(stats);

      // badges del dock si los quieres mantener
      const badge = document.getElementById('pmDriversBadge');
      if (badge) badge.textContent = String(stats.total ?? 0);
    });
  }

  function sortKeyForRow(row) {
    const v = visualState(row);
    if (v === 'offline') return 90;
    if (isInQueue(row)) return 10;
    if (v === 'on_board') return 20;
    if (v === 'arrived') return 25;
    if (v === 'en_route') return 30;
    if (v === 'accepted') return 35;
    if (v === 'offered') return 40;
    if (v === 'busy') return 50;
    if (v === 'free') return 60;
    return 70;
  }

  function renderDriversList() {
    const root = document.getElementById('pmDriversList');
    if (!root) return;

    const items = [];
    for (const [id, entry] of driverPins.entries()) {
      const row = entry.marker.__pmRow || {};
      items.push({ id, entry, row });
    }

    items.sort((a, b) => {
      const ka = sortKeyForRow(a.row);
      const kb = sortKeyForRow(b.row);
      if (ka !== kb) return ka - kb;

      const ea = String(a.row.vehicle_economico ?? a.row.economico ?? a.row.code ?? a.id);
      const eb = String(b.row.vehicle_economico ?? b.row.economico ?? b.row.code ?? b.id);
      return ea.localeCompare(eb);
    });

    const now = Date.now();
    const selId = Number(ctx.selectedDriverId || 0);

    root.innerHTML = items.map(({ id, entry, row }) => {
      const eco = row.vehicle_economico ?? row.economico ?? row.code ?? `#${id}`;
      const plate = row.vehicle_plate ?? row.plate ?? row.placa ?? '';
      const name = row.driver_name ?? row.name ?? '';
      const v = visualState(row);
      const st = stateLabel(v, row);
      const age = relativeAgeShort(now - entry.seenAtMs);

      const clsSel = (selId === id) ? ' is-sel' : '';

      return `
        <div class="pm-row${clsSel}" data-id="${id}">
          <div class="pm-row-top">
            <div class="pm-row-eco">${escapeHtml(String(eco))}</div>
            <div class="pm-pill ${st.cls}">${escapeHtml(st.text)}</div>
          </div>
          <div class="pm-row-sub">
            ${name ? `<span>${escapeHtml(String(name))}</span> · ` : ''}
            ${plate ? `<span>${escapeHtml(String(plate))}</span> · ` : ''}
            <span class="pm-muted">hace ${age}</span>
          </div>
        </div>
      `;
    }).join('');

    // bind clicks
    root.querySelectorAll('.pm-row').forEach(el => {
      el.addEventListener('click', () => {
        const id = Number(el.getAttribute('data-id') || 0);
        if (!id) return;

        onSelectDriver(id);
        focusDriverById(id);
      });
    });
  }

  function scheduleListUpdate() {
    if (listScheduled) return;
    listScheduled = true;
    requestAnimationFrame(() => {
      listScheduled = false;
      renderDriversList();
    });
  }

  function matchesFilter(row, filter) {
    const f = String(filter || '').toLowerCase();
    if (!f) return true;

    const v = visualState(row);
    const q = isInQueue(row);

    if (f === 'idle' || f === 'free') return v === 'free' && !q;
    if (f === 'busy') return v !== 'offline' && v !== 'free' && !q;
    if (f === 'queue') return q;
    if (f === 'offline') return v === 'offline';

    return true;
  }

  function applyDriverFilter(filter) {
    ctx.driverFilter = filter || '';

    for (const { marker } of driverPins.values()) {
      const row = marker.__pmRow || {};
      const ok = matchesFilter(row, ctx.driverFilter);

      // add/remove sin destruir marker
      const has = ctx.layerDrivers.hasLayer(marker);
      if (ok && !has) ctx.layerDrivers.addLayer(marker);
      if (!ok && has) ctx.layerDrivers.removeLayer(marker);
    }
  }

  ctx.map.on('zoomend', () => {
    for (const { marker } of driverPins.values()) {
      applyDriverVisual(marker, marker.__pmRow || {});
    }
  });

  return {
    driverPins,
    upsertDriver,
    removeDriverPin,
    markStaleDrivers,
    focusDriverById,
    fitDrivers,

    // follow API (para Selection Card / botones)
    startFollow,
    stopFollow,
    isFollowing: () => followOn && !!followDriverId,
    getFollowDriverId: () => followDriverId,

    // filtros desde HUD chips
    applyDriverFilter,      // ✅
  getDriverCounts,        // ✅
  };
}
