import { jsonHeaders, getTenantId } from './dispatch.core.js';

export async function loadDispatchSettings() {
  const tenantId = getTenantId();

  try {
    const r = await fetch(`/api/dispatch/settings?tenant_id=${encodeURIComponent(tenantId)}`, {
      method: 'GET',
      headers: jsonHeaders(),
      credentials: 'same-origin'
    });

    if (!r.ok) {
      const txt = await r.text().catch(() => '');
      console.warn('[settings] HTTP ' + r.status, txt);
      throw new Error('HTTP ' + r.status);
    }

    const json = await r.json();
    window.ccDispatchSettings = {
      auto_dispatch_enabled:           !!json.auto_dispatch_enabled,
      auto_dispatch_delay_s:           Number(json.auto_dispatch_delay_s ?? 20),
      auto_dispatch_preview_n:         Number(json.auto_dispatch_preview_n ?? 12),
      auto_dispatch_preview_radius_km: Number(json.auto_dispatch_preview_radius_km ?? 5),
      offer_expires_sec:               Number(json.offer_expires_sec ?? 180),
      auto_assign_if_single:           !!json.auto_assign_if_single,
      allow_fare_bidding:              !!json.allow_fare_bidding,
    };
  } catch (e) {
    console.warn('[settings] error; defaults', e);
    window.ccDispatchSettings = window.ccDispatchSettings || {
      auto_dispatch_enabled: true,
      auto_dispatch_delay_s: 20,
      auto_dispatch_preview_n: 12,
      auto_dispatch_preview_radius_km: 5,
      offer_expires_sec: 180,
      auto_assign_if_single: false,
      allow_fare_bidding: false,
    };
  }
}

window.loadDispatchSettings = loadDispatchSettings;
