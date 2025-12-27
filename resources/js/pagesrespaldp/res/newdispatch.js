import 'leaflet/dist/leaflet.css';
import '../dispatch/chat_inbox';

import { ensureTenantGlobals, qs, jsonHeaders } from './dispatch.core.js';
import { loadDispatchSettings } from './dispatch.settings.js';
import { wireDispatchDomReady } from './dispatch.bootstrap.js';

import { wireCreateRide } from './dispatch.create_ride.js';
import { loadStopsFromLastRide } from './dispatch.stops.js';

import { startCompoundAutoDispatch, cancelCompoundAutoDispatch } from './dispatch.autodispatch.js';
import { initChatInbox } from './dispatch.chat.js';

import { wirePassengerLastRide } from './dispatch.passenger_autofill.js';
import { recalcQuoteUI } from './dispatch.form_when.js';
import { FleetMetrics } from './dispatch.metrics.js';

document.addEventListener('DOMContentLoaded', async () => {
  const tenantId = ensureTenantGlobals();
  console.log('[dispatch] TENANT_ID=', tenantId);

  initChatInbox({ exposeGlobal: true });

  await loadDispatchSettings();

  // map + capas + wiring
  await wireDispatchDomReady();

  // autofill pasajero
  wirePassengerLastRide({ qs, jsonHeaders, recalcQuoteUI });

  // create ride + stops
  window.loadStopsFromLastRide = loadStopsFromLastRide;
  wireCreateRide();

  // metrics (una sola instancia)
  new FleetMetrics(window.ccDispatchCtx || null);

  // compat globals
  window.startCompoundAutoDispatch = (ride) => startCompoundAutoDispatch(null, ride);
  window.cancelCompoundAutoDispatch = () => cancelCompoundAutoDispatch(null);
});
