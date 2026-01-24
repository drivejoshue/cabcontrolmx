// resources/js/partner-monitor/realtime.js
import { qsMeta } from './net';

export function bindRealtime(ctx) {
  const partnerId = qsMeta('partner-id');
  if (!window.Echo || !partnerId) return;

  window.Echo.private(`partner.${partnerId}.drivers`)
    .listen('.driver.location', (e) => {
      if (e?.driver) ctx.upsertDriver(e.driver);
    })
    .listen('.driver.status', (e) => {
      if (e?.driver) ctx.upsertDriver(e.driver);
    });
}
