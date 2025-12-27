/* resources/js/pages/dispatch.ui_helpers.js
 * Helpers puros (sin DOM) para:
 * - Normalizar estado del driver
 * - Traducir etiqueta para UI
 * - Formatear "hace X tiempo"
 */

// Normaliza strings
const _lc = (s) => String(s ?? '').toLowerCase().trim();

/**
 * Normaliza estado "crudo" del backend/realtime a un estado visual estable.
 * Ajusta aquí según tu backend:
 *  - status: idle|busy|on_ride|offline...
 *  - shift_open: true/false
 *  - last_seen_at / ping_age_s...
 */
export function visualState(driver) {
  if (!driver) return 'unknown';

  const st = _lc(driver.status || driver.state || driver.driver_status);

  // Si tienes flag de turno/cierre
  const shiftOpen =
    driver.shift_open ?? driver.shiftOpen ?? driver.on_shift ?? driver.shift ?? null;

  // Si viene un "is_online" explícito
  const isOnline =
    driver.is_online ?? driver.online ?? driver.isOnline ?? null;

  // Si tienes ping_age_s o last_ping_s
  const pingAge =
    Number(driver.ping_age_s ?? driver.last_ping_s ?? driver.pingAgeS ?? NaN);

  const looksOffline =
    (isOnline === false) ||
    (Number.isFinite(pingAge) && pingAge > 120) || // tu watchdog usa 120s
    st === 'offline';

  if (looksOffline) return 'offline';
  if (shiftOpen === false) return 'offshift';

  // Canonical
  if (st === 'idle' || st === 'free') return 'idle';
  if (st === 'busy') return 'busy';
  if (st === 'on_ride' || st === 'onboard' || st === 'on_board') return 'on_ride';
  if (st === 'assigned') return 'assigned';

  return st || 'unknown';
}

/**
 * Etiqueta humana para UI (chips, tooltips).
 */
export function statusLabel(driverOrState) {
  const st = typeof driverOrState === 'string'
    ? _lc(driverOrState)
    : visualState(driverOrState);

  if (st === 'idle') return 'LIBRE';
  if (st === 'busy') return 'OCUPADO';
  if (st === 'on_ride') return 'EN VIAJE';
  if (st === 'assigned') return 'ASIGNADO';
  if (st === 'offline') return 'OFFLINE';
  if (st === 'offshift') return 'SIN TURNO';
  if (st === 'unknown') return '—';
  return st.toUpperCase();
}

/**
 * Formatea "hace X" a partir de:
 * - last_seen_at (timestamp string DB)
 * - last_seen_ts (epoch seconds/ms)
 * - ping_age_s
 */
export function fmtAgo(driver, nowMs = Date.now()) {
  if (!driver) return '';

  // Caso A: ping_age_s ya viene calculado
  const pingAge = Number(driver.ping_age_s ?? driver.last_ping_s ?? NaN);
  if (Number.isFinite(pingAge)) return humanizeSeconds(pingAge);

  // Caso B: epoch seconds/ms
  const epoch = driver.last_seen_ts ?? driver.lastSeenTs ?? driver.seen_ts ?? null;
  if (epoch != null) {
    const ms = Number(epoch) < 1e12 ? Number(epoch) * 1000 : Number(epoch);
    const diff = Math.max(0, Math.floor((nowMs - ms) / 1000));
    return humanizeSeconds(diff);
  }

  // Caso C: string timestamp tipo "YYYY-MM-DD HH:mm:ss"
  const s = driver.last_seen_at || driver.lastSeenAt || driver.seen_at || driver.last_ping_at;
  if (s) {
    const ms = parseDbTimestampToMs(s);
    if (ms != null) {
      const diff = Math.max(0, Math.floor((nowMs - ms) / 1000));
      return humanizeSeconds(diff);
    }
  }

  return '';
}

function humanizeSeconds(sec) {
  if (!Number.isFinite(sec)) return '';
  if (sec < 10) return 'ahora';
  if (sec < 60) return `hace ${sec}s`;
  const m = Math.floor(sec / 60);
  if (m < 60) return `hace ${m}m`;
  const h = Math.floor(m / 60);
  if (h < 24) return `hace ${h}h`;
  const d = Math.floor(h / 24);
  return `hace ${d}d`;
}

/**
 * Parsea "YYYY-MM-DD HH:mm:ss" sin TZ (tal cual DB local tenant).
 * Devuelve ms o null si falla.
 */
function parseDbTimestampToMs(s) {
  try {
    const t = String(s).replace('T', ' ').trim();
    const m = t.match(/^(\d{4})-(\d{2})-(\d{2})[ ](\d{2}):(\d{2})(?::(\d{2}))?$/);
    if (!m) return null;
    const y = +m[1], mo = +m[2], d = +m[3], H = +m[4], M = +m[5], S = +(m[6] ?? 0);
    // Interpretación local del navegador (tu app trabaja en hora local tenant)
    const dt = new Date(y, mo - 1, d, H, M, S);
    const ms = dt.getTime();
    return Number.isFinite(ms) ? ms : null;
  } catch {
    return null;
  }
}
