// resources/js/pages/dispatch.autodispatch.js
import { jsonHeaders } from './dispatch.core.js';

// Estructura de timers en window para cancelación simple.
// (No la guardo en closure porque necesitas cancelar desde otros módulos)
function ensureTimers() {
  if (!window.__compoundTimers) window.__compoundTimers = {};
  return window.__compoundTimers;
}

export function cancelCompoundAutoDispatch(ctx = null) {
  const t = window.__compoundTimers;
  if (!t) return;

  try { if (t.tEnd) clearTimeout(t.tEnd); } catch {}
  try { if (t.interval) clearInterval(t.interval); } catch {}

  window.__compoundTimers = null;

  const hideBubble = ctx?.ui?.hideBubble || window.hideBubble;
  try { hideBubble?.(); } catch {}
}

export async function fireAutoDispatchTick(rideId) {
  const resp = await fetch('/api/dispatch/tick', {
    method: 'POST',
    headers: jsonHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ ride_id: rideId }),
  });
  return resp;
}

export function startCompoundAutoDispatch(ctx = null, ride) {
  cancelCompoundAutoDispatch(ctx);

  if (!ride?.id) return;

  // settings
  const ds = window.ccDispatchSettings || {};
  const enabled = ds.auto_dispatch_enabled !== false;

  const total = Number.isFinite(+ds.auto_dispatch_delay_s)
    ? Math.max(0, Math.floor(+ds.auto_dispatch_delay_s))
    : (Number.isFinite(+ds.auto_delay_sec) ? Math.max(0, Math.floor(+ds.auto_delay_sec)) : 20);

  if (!enabled) return;

  // no disparar en programados
  const isScheduledStatus = ctx?.rides?.isScheduledStatus || window.isScheduledStatus;
  if (typeof isScheduledStatus === 'function' && isScheduledStatus(ride)) return;
  if (!isScheduledStatus && ride?.scheduled_for) return;

  const showBubble   = ctx?.ui?.showBubble   || window.showBubble;
  const hideBubble   = ctx?.ui?.hideBubble   || window.hideBubble;
  const updateBubble = ctx?.ui?.updateBubble || window.updateBubble;

  try { showBubble?.('Servicio detectado…'); } catch {}

  const timers = ensureTimers();

  // ejecuta tick al final
  timers.tEnd = setTimeout(async () => {
    try { hideBubble?.(); } catch {}
    try {
      const resp = await fireAutoDispatchTick(ride.id);
      if (!resp.ok) {
        const txt = await resp.text().catch(() => '');
        console.warn('[auto] tick HTTP', resp.status, txt);
      }
    } catch (e) {
      console.warn('[auto] tick error:', e);
    }
  }, total * 1000);

  // countdown bubble
  let left = total;
  timers.interval = setInterval(() => {
    left -= 1;
    if (left <= 0) {
      try { clearInterval(timers.interval); } catch {}
      return;
    }
    try { updateBubble?.(`Asignando en ${left}s`); } catch {}
  }, 1000);
}
