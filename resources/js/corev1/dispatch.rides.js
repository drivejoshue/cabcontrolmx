/* resources/js/pages/dispatch/rides.js */
import { qs, escapeHtml, jsonHeaders, logListDebug } from './core.js';
import { highlightRideOnMap } from './assign.js';

const _norm = s => String(s || '').toLowerCase().trim();

export function _canonStatus(s) {
  const k = _norm(s);
  if (k === 'onboard') return 'on_board';
  if (k === 'enroute') return 'en_route';
  return k;
}

const _SET_WAITING = new Set(['requested', 'pending', 'new', 'offered', 'offering']);
const _SET_ACTIVE = new Set(['accepted', 'assigned', 'en_route', 'arrived', 'boarding', 'on_board']);
const _SET_SCHED = new Set(['scheduled']);

export const _isWaiting = r => _SET_WAITING.has(_canonStatus(r?.status));
export const _isActive = r => _SET_ACTIVE.has(_canonStatus(r?.status));
export const _isScheduled = r => _SET_SCHED.has(_canonStatus(r?.status));

export function isScheduledStatus(ride) {
  const st = _norm(ride?.status);
  const hasSchedField = !!(ride?.scheduled_for || ride?.scheduledFor ||
    ride?.scheduled_at || ride?.scheduledAt);
  return st === 'scheduled' || hasSchedField;
}

export function shouldHideRideCard(ride) {
  const st = String(ride.status || '').toLowerCase();
  return st === 'completed' || st === 'canceled';
}

export function deriveRideChannel(ride) {
  const raw = String(
    ride.requested_channel ||
    ride.channel ||
    ride.request_source ||
    ''
  ).toLowerCase().trim();

  if (!raw) {
    return { code: 'panel', label: 'Panel' };
  }

  if (['passenger_app', 'passenger', 'app', 'app_pasajero'].includes(raw)) {
    return { code: 'passenger', label: 'App pasajero' };
  }

  if (['driver_app', 'driver', 'app_conductor'].includes(raw)) {
    return { code: 'driver', label: 'App conductor' };
  }

  if (['central', 'dispatcher', 'panel', 'web'].includes(raw)) {
    return { code: 'panel', label: 'Central' };
  }

  if (['phone', 'telefono', 'callcenter', 'call_center'].includes(raw)) {
    return { code: 'phone', label: 'Teléfono' };
  }

  if (['corp', 'corporate', 'empresa', 'business'].includes(raw)) {
    return { code: 'corp', label: 'Corporativo' };
  }

  return {
    code: raw,
    label: raw.charAt(0).toUpperCase() + raw.slice(1)
  };
}

export function summarizeOffers(ride) {
  const offers = Array.isArray(ride.offers) ? ride.offers : [];
  const anyAccepted = offers.some(o => o.status === 'accepted');
  const anyOffered = offers.some(o => o.status === 'offered');
  const rejectedBy = offers.filter(o => o.status === 'rejected')
    .map(o => o.driver_name || `#${o.driver_id}`);
  return { offers, anyAccepted, anyOffered, rejectedBy };
}

export function deriveRideUi(ride) {
  const rawStatus = String(ride.status || '').toLowerCase().trim();
  const status = (typeof _canonStatus === 'function')
    ? _canonStatus(rawStatus)
    : (rawStatus || 'unknown');

  const ch = deriveRideChannel(ride);
  const isPassengerApp = ch.code === 'passenger';

  let label = status;
  let colorClass = 'secondary';
  let showAssign = false;
  let showReoffer = false;
  let showRelease = false;
  let showCancel = false;

  switch (status) {
    case 'requested':
    case 'pending':
    case 'queued':
    case 'new':
      label = 'Pendiente';
      colorClass = 'warning';
      showAssign = true;
      showReoffer = true;
      showCancel = true;
      break;

    case 'offered':
    case 'offering':
      label = 'Ofertado';
      colorClass = 'info';
      showAssign = true;
      showReoffer = true;
      showCancel = true;
      break;

    case 'accepted':
    case 'assigned':
      label = 'Asignado';
      colorClass = 'primary';
      showRelease = true;
      showCancel = true;
      break;

    case 'en_route':
      label = 'En camino';
      colorClass = 'primary';
      showRelease = true;
      showCancel = true;
      break;

    case 'arrived':
      label = 'Esperando';
      colorClass = 'warning';
      showRelease = true;
      showCancel = true;
      break;

    case 'on_board':
      label = 'En viaje';
      colorClass = 'success';
      showRelease = false;
      showCancel = true;
      break;

    case 'finished':
      label = 'Finalizado';
      colorClass = 'success';
      break;

    case 'canceled':
      label = 'Cancelado';
      colorClass = 'secondary';
      break;

    case 'no_driver':
      label = 'Sin conductor';
      colorClass = 'danger';
      showAssign = true;
      showReoffer = true;
      break;

    default:
      label = status || 'desconocido';
      colorClass = 'secondary';
  }

  if (isPassengerApp) {
    showAssign = false;
    showReoffer = false;
  }

  const badge = `<span class="badge bg-${colorClass} badge-pill">${label}</span>`;

  return {
    status,
    label,
    badge,
    showAssign,
    showReoffer,
    showRelease,
    showCancel,
    isPassengerApp,
    channel: ch
  };
}

export function extractPartsFromDbTs(s) {
  if (!s) return null;
  const t = s.replace('T', ' ');
  const m = t.match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})(?::(\d{2}))?$/);
  if (!m) return null;
  return {
    y: +m[1], mo: +m[2], d: +m[3],
    H: +m[4], M: +m[5], S: +(m[6] ?? 0),
    raw: t
  };
}

export function fmtHM12_fromDb(s) {
  const p = extractPartsFromDbTs(s);
  if (!p) return '—';
  let h = p.H % 12; if (h === 0) h = 12;
  const mm = String(p.M).padStart(2, '0');
  const ampm = p.H < 12 ? 'a.m.' : 'p.m.';
  return `${h}:${mm} ${ampm}`;
}

export function fmtShortDay_fromDb(s) {
  const p = extractPartsFromDbTs(s);
  if (!p) return '';
  const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
  return `${String(p.d).padStart(2, '0')} ${meses[p.mo - 1]}`;
}

export function fmtWhen_db(s) {
  const p = extractPartsFromDbTs(s);
  if (!p) return '—';
  return `${fmtHM12_fromDb(s)} · ${fmtShortDay_fromDb(s)}`;
}

export function normalizeStops(ride) {
  if (Array.isArray(ride.stops)) return ride.stops;
  if (ride.stops_json) {
    try {
      const a = JSON.parse(ride.stops_json);
      return Array.isArray(a) ? a : [];
    } catch { }
  }
  return [];
}

export async function hydrateRideStops(ride) {
  if (Array.isArray(ride.stops) && ride.stops.length) return ride;
  if (Array.isArray(ride.stops_json) && ride.stops_json.length) {
    ride.stops = ride.stops_json;
    ride.stops_count = ride.stops_json.length;
    ride.stop_index = ride.stop_index ?? 0;
    return ride;
  }
  if (typeof ride.stops_json === 'string' && ride.stops_json.trim() !== '') {
    try {
      const arr = JSON.parse(ride.stops_json);
      if (Array.isArray(arr)) {
        ride.stops = arr;
        ride.stops_count = arr.length;
        ride.stop_index = ride.stop_index ?? 0;
        return ride;
      }
    } catch { }
  }

  try {
    const r = await fetch(`/api/rides/${ride.id}`, {
      headers: jsonHeaders()
    });
    if (r.ok) {
      const d = await r.json();
      ride.stops = Array.isArray(d.stops) ? d.stops : [];
      ride.stops_json = ride.stops;
      ride.stops_count = d.stops_count ?? ride.stops.length;
      ride.stop_index = d.stop_index ?? 0;
      ride.distance_m = d.distance_m ?? ride.distance_m;
      ride.duration_s = d.duration_s ?? ride.duration_s;
      ride.quoted_amount = d.quoted_amount ?? ride.quoted_amount;
    }
  } catch (e) {
    console.warn('hydrateRideStops fallo', e);
  }
  return ride;
}

export function renderRideCard(ride) {
  if (shouldHideRideCard(ride)) return '';

  const ui = deriveRideUi(ride);
  const ch = ui.channel || deriveRideChannel(ride);

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]
  ));
  const fmtC = (v) => Number.isFinite(+v) ? (+v).toFixed(5) : '—';

  const parseStops = () => {
    if (Array.isArray(ride.stops)) return ride.stops;
    if (Array.isArray(ride.stops_json)) return ride.stops_json;
    if (typeof ride.stops_json === 'string' && ride.stops_json.trim() !== '') {
      try { const a = JSON.parse(ride.stops_json); return Array.isArray(a) ? a : []; } catch { }
    }
    return [];
  };

  const km = (ride.km != null && !isNaN(ride.km)) ? Number(ride.km).toFixed(1)
    : (ride.distance_m ? (ride.distance_m / 1000).toFixed(1) : '-');
  const min = (ride.min != null) ? ride.min
    : (ride.duration_s ? Math.round(ride.duration_s / 60) : '-');
  const amt = (ride.quoted_amount != null) ? Number(ride.quoted_amount) : Number(ride.amount ?? NaN);
  const amtTxt = Number.isFinite(amt) ? `$${amt.toFixed(2)}` : '—';

  const stops = parseStops();
  const stopsCount = stops.length || (ride.stops_count ? Number(ride.stops_count) : 0);

  const passName = ride.passenger_name || '—';
  const passPhone = ride.passenger_phone || '';
  const originLbl = ride.origin_label
    || (Number.isFinite(ride.origin_lat) ? `${fmtC(ride.origin_lat)}, ${fmtC(ride.origin_lng)}` : '—');
  const destLbl = ride.dest_label
    || (Number.isFinite(ride.dest_lat) ? `${fmtC(ride.dest_lat)}, ${fmtC(ride.dest_lng)}` : '—');

  const channelBadge = ch
    ? `<span class="badge bg-light text-secondary border ms-2">${esc(ch.label)}</span>`
    : '';

  const scheduled = isScheduledStatus(ride);
  const schedRaw = scheduled ? (ride.scheduled_for || ride.scheduled_for_fmt) : null;
  const requestedRaw = (ride.requested_at || ride.created_at) || null;
  const schedTxt = scheduled ? fmtWhen_db(schedRaw) : '';
  const requestedTxt = requestedRaw ? fmtWhen_db(requestedRaw) : '—';

  const stateBadge = scheduled
    ? `<span class="badge bg-danger-subtle text-danger border border-danger">PROGRAMADO</span>`
    : ui.badge;

  const stopsTitle = stopsCount ? `<div class="cc-stops-title small text-muted fw-semibold mt-2 mb-1">Paradas (${stopsCount})</div>` : '';
  const stopItems = stops.map((s, i) => {
    const txt = (s && typeof s.label === 'string' && s.label.trim() !== '')
      ? esc(s.label.trim())
      : `${fmtC(s.lat)}, ${fmtC(s.lng)}`;
    const title = `S${i + 1}: ${fmtC(s.lat)}, ${fmtC(s.lng)}`;
    return `<li class="cc-leg" title="${esc(title)}">
              <span class="cc-pin cc-pin--s"></span>
              <div class="small">${txt}</div>
            </li>`;
  }).join('');

  const showDebug = !!window.__DISPATCH_DEBUG__;
  const debugBlock = showDebug ? `
    <details class="mt-2"><summary class="text-muted small">debug</summary>
      <pre class="small text-muted" style="white-space:pre-wrap;max-height:180px;overflow:auto">${
    esc(JSON.stringify({
      id: ride.id,
      stops_count: ride.stops_count,
      stop_index: ride.stop_index,
      stops: ride.stops,
      stops_json: ride.stops_json
    }, null, 2))
    }</pre>
    </details>
  ` : '';

  return `
  <div class="card cc-ride-card ${scheduled ? 'is-scheduled' : ''} mb-2 border-0 shadow-sm" data-ride-id="${ride.id}">
    <div class="card-body p-3">
      <div class="cc-ride-header d-flex justify-content-between align-items-start mb-2">
        <div class="d-flex align-items-center gap-2">
          <div class="cc-ride-title fw-bold text-primary">#${ride.id}</div>
          <div class="cc-ride-badge">${stateBadge}</div>
        </div>
        <div class="cc-stats text-end">
          <div class="cc-amount fw-bold fs-5 text-success">${amtTxt}</div>
          <div class="cc-meta small text-muted">${km} km · ${min} min</div>
        </div>
      </div>

      <div class="cc-passenger-info mb-2 p-2 bg-light rounded small d-flex justify-content-between align-items-center">
        <div>
          <i class="bi bi-person me-1"></i> 
          <span class="fw-semibold">${esc(passName)}</span>
          ${passPhone ? `<span class="text-muted ms-2"><i class="bi bi-telephone me-1"></i>${esc(passPhone)}</span>` : ''}
        </div>
        ${channelBadge}
      </div>

      <ul class="cc-legs list-unstyled mb-2">
        <li class="cc-leg d-flex align-items-start mb-1">
          <span class="cc-pin cc-pin--o me-2 mt-1"></span>
          <div class="flex-grow-1">
            <div class="small text-muted">Origen</div>
            <div class="fw-semibold small">${esc(originLbl)}</div>
          </div>
        </li>

        ${stopsTitle}
        ${stopItems}

        ${destLbl !== '—' ? `
        <li class="cc-leg d-flex align-items-start mb-1">
          <span class="cc-pin cc-pin--d me-2 mt-1"></span>
          <div class="flex-grow-1">
            <div class="small text-muted">Destino</div>
            <div class="fw-semibold small">${esc(destLbl)}</div>
          </div>
        </li>` : ''}
      </ul>

      <div class="cc-footer mt-2 pt-2 border-top">
        <div class="d-flex justify-content-between align-items-center">
          <div class="cc-meta small text-muted">
            <i class="bi bi-clock me-1"></i>
            ${scheduled ? `Prog: ${esc(schedTxt)}` : `Creado: ${esc(requestedTxt)}`}
          </div>
          ${stopsCount ? `<div class="cc-stops-badge small badge bg-secondary">${stopsCount} parada${stopsCount > 1 ? 's' : ''}</div>` : ''}
        </div>
      </div>

      ${debugBlock}

      <div class="d-flex justify-content-end cc-actions mt-3">
        <div class="btn-group btn-group-sm">
          ${ui.showAssign ? `<button class="btn btn-primary" data-act="assign">Asignar</button>` : ''}
          ${ui.showReoffer ? `<button class="btn btn-outline-primary" data-act="reoffer">Re-ofertar</button>` : ''}
          ${ui.showRelease ? `<button class="btn btn-warning" data-act="release">Liberar</button>` : ''}
          ${ui.showCancel ? `<button class="btn btn-outline-danger btn-cancel" data-ride-id="${ride.id}">Cancelar</button>` : ''}
          <button class="btn btn-outline-secondary" data-act="view">Ver</button>
        </div>
      </div>
    </div>
  </div>`;
}

// Inyectar estilos de cards
(function injectRideCardStyles() {
  if (window.__RIDE_CARD_STYLES__) return;
  window.__RIDE_CARD_STYLES__ = true;

  const css = `
    :root{
      --cc-card-bg: var(--bs-card-bg, var(--bs-body-bg));
      --cc-text: var(--bs-body-color);
      --cc-muted: var(--bs-secondary-color);
      --cc-border: var(--bs-border-color);
      --cc-soft-bg: var(--bs-tertiary-bg);
    }

    .cc-ride-card{
      background: var(--cc-card-bg);
      color: var(--cc-text);
      border: 1px solid var(--cc-border);
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 1px 2px rgba(16,24,40,.04);
    }
    .cc-ride-card.is-scheduled{ border-left: 4px solid var(--bs-danger); }
    .cc-ride-card .card-body{ padding:14px 16px; }

    .cc-ride-header{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
    .cc-ride-title{ font-weight:700; font-size:14px; letter-spacing:.2px; }
    .cc-ride-badge .badge{ font-weight:600; padding:.35rem .5rem; border-radius:8px; }
    .cc-stats{ text-align:right; }
    .cc-amount{ font-weight:800; font-size:16px; line-height:1; }
    .cc-meta{ font-size:12px; color: var(--cc-muted); margin-top:4px; }
    .cc-divider{ height:1px; background: var(--cc-border); margin:10px 0; }

    .cc-legs{ position:relative; margin:0; padding-left:22px; list-style:none; }
    .cc-legs::before{
      content:""; position:absolute; left:7px; top:10px; bottom:10px;
      width:2px; background: var(--cc-border); border-radius:1px;
    }
    .cc-leg{ display:flex; align-items:flex-start; gap:8px; margin:6px 0; }
    .cc-pin{ width:12px; height:12px; border-radius:50%; margin-top:2px; flex:0 0 12px; }
    .cc-pin--o{ background:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.18); }
    .cc-pin--s{ background:#9aa0a6; }
    .cc-pin--d{ background:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.18); }
    .cc-leg .cc-label{ font-size:13px; color: var(--cc-text); }
    .cc-leg .cc-sub{ font-size:12px; color: var(--cc-muted); }
    .cc-stops-title{ font-size:12px; color: var(--bs-primary); font-weight:700; margin:6px 0 4px; }
    .cc-chip{ font-size:12px; border-radius:999px; padding:.25rem .5rem;
      background: var(--cc-soft-bg); color: var(--cc-text); display:inline-block;
      border: 1px solid var(--cc-border); }

    html:not([data-theme="dark"]) .cc-ride-card{
      background: #ffffff !important;
      border-color: #e9eef5;
    }
    html:not([data-theme="dark"]) .cc-divider{ background: #eef2f7; }
    html:not([data-theme="dark"]) .cc-chip{
      background: #f3f4f6;
      border-color: #e9eef5;
    }
    html[data-theme="dark"] .cc-ride-card{ box-shadow: 0 10px 24px rgba(0,0,0,.45); }
    html[data-theme="dark"] .cc-legs::before{ background: var(--cc-border); }
    html[data-theme="dark"] .cc-divider{ background: var(--cc-border); }
    html[data-theme="dark"] .cc-chip{ background: var(--cc-soft-bg); border-color: var(--cc-border); }

    @media (prefers-color-scheme: dark){
      html:not([data-theme]) .cc-ride-card{ box-shadow: 0 10px 24px rgba(0,0,0,.45); }
      html:not([data-theme]) .cc-legs::before{ background: var(--cc-border); }
      html:not([data-theme]) .cc-divider{ background: var(--cc-border); }
      html:not([data-theme]) .cc-chip{ background: var(--cc-soft-bg); }
    }
  `;

  const style = document.createElement('style');
  style.id = 'cc-ride-card-styles';
  style.textContent = css;
  document.head.appendChild(style);
})();