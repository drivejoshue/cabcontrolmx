// resources/js/pages/dispatch.polling.js
import { jsonHeaders } from './dispatch.core.js';
import { renderQueues } from './dispatch.queues.js';
import { renderDrivers, updateSuggestedRoutes } from './dispatch.drivers.js';
import { renderRightPanels } from './dispatch.rides_panel.js';
import { renderDockActive } from './dispatch.dock.js';

let pollTimer = null;

export async function refreshDispatch(ctx) {
  try {
    const [a, b] = await Promise.all([
      fetch('/api/dispatch/active',  { headers: jsonHeaders() }),
      fetch('/api/dispatch/drivers', { headers: jsonHeaders() }),
    ]);

    if (!a.ok) throw new Error('active HTTP ' + a.status);
    if (!b.ok) throw new Error('drivers HTTP ' + b.status);

    const active  = await a.json(); // suele traer rides + queues, depende tu endpoint
    const drivers = await b.json();

    // Normaliza según tu payload real:
    const rides  = active?.rides  ?? active?.active_rides ?? active ?? [];
    const queues = active?.queues ?? [];

    // “Inyección” de dock para que renderRightPanels lo encuentre
    ctx.dock = ctx.dock || {};
    ctx.dock.renderDockActive = renderDockActive;

    renderQueues(ctx, queues);
    renderRightPanels(ctx, rides);
    renderDrivers(drivers, ctx);
    updateSuggestedRoutes(rides, ctx);

    window._lastActiveRides = rides;
    return { rides, queues, drivers };

  } catch (e) {
    console.error('refreshDispatch error', e);
  }
}

export function startPolling(ctx, ms = 5000){
  stopPolling();
  window.refreshDispatch = () => refreshDispatch(ctx);

  refreshDispatch(ctx);
  pollTimer = setInterval(() => refreshDispatch(ctx), ms);

  console.log('[polling] started', ms);
}

export function stopPolling(){
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = null;
}
