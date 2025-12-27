// resources/js/pages/dispatch.polling.js
import { jsonHeaders } from './dispatch.core.js';
import { renderQueues } from './dispatch.queues.js';
import { renderDrivers } from './dispatch.drivers.js';
import { renderRightNowCards, renderRightScheduledCards, renderRightPanels } from './dispatch.rides_panel.js';

// OJO: este import debe existir realmente como export en ese módulo.
// Si updateSuggestedRoutes vive en otro archivo, cambia la ruta.
import { updateSuggestedRoutes } from './dispatch.map.js';


let pollTimer = null;



export async function refreshDispatch(ctx) {
  if (!ctx) throw new Error('refreshDispatch(ctx): ctx es requerido');

  // Anti-overlap: si ya hay un refresh corriendo, no dispares otro
  if (ctx._polling?.inFlight) return;
  ctx._polling = ctx._polling || {};
  ctx._polling.inFlight = true;

  try {
    const [a, b] = await Promise.all([
      fetch('/api/dispatch/active',  { headers: jsonHeaders() }),
      fetch('/api/dispatch/drivers', { headers: jsonHeaders() }),
    ]);

    const data = a.ok
      ? await a.json()
      : (console.error('[active]', await a.text().catch(() => '')), {});

    const drivers = b.ok
      ? await b.json()
      : (console.error('[drivers]', await b.text().catch(() => '')), []);

    const rides      = Array.isArray(data.rides)  ? data.rides  : [];
    const queues     = Array.isArray(data.queues) ? data.queues : [];
    const driverList = Array.isArray(drivers)     ? drivers     : [];

    // Estado
    ctx.state = ctx.state || {};
    ctx.state.rides   = rides;
    ctx.state.queues  = queues;
    ctx.state.drivers = driverList;

    // Índices/cache (útil para focusRideOnMap)
    ctx.index = ctx.index || {};
    ctx.index.rides = new Map(rides.map(r => [Number(r.id), r]));

    // Si también conservas globals para módulos legacy:
    window._lastActiveRides = rides;
    window._ridesIndex      = ctx.index.rides;
    window._lastDrivers     = driverList;
    window._lastQueues      = queues;

    // Render UI
    renderQueues(ctx, queues);
    renderRightNowCards(ctx, rides);
    renderRightScheduledCards(ctx, rides);
    renderDockActive(ctx, rides);
    renderDrivers(ctx, driverList);

    // Rutas sugeridas (puede ser pesado; decide si lo corres siempre)
    if (typeof updateSuggestedRoutes === 'function') {
      await updateSuggestedRoutes(ctx, rides);
    }

  } catch (e) {
    console.warn('refreshDispatch error', e);
  } finally {
    ctx._polling.inFlight = false;
    ctx._polling.lastAt = Date.now();
  }
}

let _pollTimer = null;

export function startPolling(ctx, options = {}) {
  if (!ctx) throw new Error('startPolling(ctx): ctx es requerido');

  const cfg = {
    intervalMs: 3000,
    pauseWhenHidden: true,
    ...options
  };

  stopPolling();

  // guarda config para uso futuro si quieres
  ctx._polling = ctx._polling || {};
  ctx._polling.intervalMs = cfg.intervalMs;

  const tick = () => {
    if (cfg.pauseWhenHidden && document.hidden) return;
    refreshDispatch(ctx);
  };

  _pollTimer = setInterval(tick, Math.max(1000, Number(cfg.intervalMs) || 3000));

  // primer fetch inmediato
  tick();
}

export function stopPolling() {
  if (_pollTimer) {
    clearInterval(_pollTimer);
    _pollTimer = null;
  }
}
