import 'leaflet/dist/leaflet.css';

import { ensureTenantGlobals, qs, jsonHeaders } from './dispatch.core.js';
import { loadDispatchSettings } from './dispatch.settings.js';
import { wireDispatchDomReady } from './dispatch.bootstrap.js';

import { initChatInbox } from './dispatch.chat.js';
import { wirePassengerLastRide } from './dispatch.passenger_autofill.js';
import { recalcQuoteUI } from './dispatch.form_when.js';
import { wireCreateRide } from './dispatch.create_ride.js';

import { startPolling } from './dispatch.polling.js';   // <-- asegúrate que exista
import { FleetMetrics } from './dispatch.metrics.js';

document.addEventListener('DOMContentLoaded', async () => {
  try {
    const tenantId = ensureTenantGlobals();
    console.log('[dispatch] boot tenant=', tenantId);

    initChatInbox?.({ exposeGlobal: true });

    await loadDispatchSettings();

    const ctx = await wireDispatchDomReady();
    console.log('[dispatch] ctx ready', ctx);

    // hooks extra
    wirePassengerLastRide({ qs, jsonHeaders, recalcQuoteUI });
    wireCreateRide();

    // polling + métricas
    startPolling(ctx);
    new FleetMetrics(ctx);

  } catch (e) {
    console.error('[dispatch] BOOT ERROR', e);
  }
});
