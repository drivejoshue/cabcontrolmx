/* resources/js/pages/dispatch/autodispatch.js */
import { getTenantId, jsonHeaders } from './core.js';
import { isScheduledStatus } from './rides.js';

let _bubbleEl = null, _bubbleTimer = null;

export function ensureBubble() {
  if (_bubbleEl) return _bubbleEl;
  _bubbleEl = document.createElement('div');
  _bubbleEl.className = 'cc-bubble';
  _bubbleEl.style.cssText = `
    position:absolute; right:16px; bottom:16px; z-index:10000;
    max-width:280px; background:rgba(0,0,0,.8); color:#fff;
    padding:10px 12px; border-radius:12px; font-size:13px; box-shadow:0 6px 22px rgba(0,0,0,.25)
  `;
  _bubbleEl.textContent = '...';
  document.body.appendChild(_bubbleEl);
  return _bubbleEl;
}

export function showBubble(text) { const el = ensureBubble(); el.style.display = 'block'; el.textContent = text || '...'; }
export function updateBubble(text) { if (_bubbleEl) _bubbleEl.textContent = text || '...'; }
export function hideBubble() { if (_bubbleEl) { _bubbleEl.style.display = 'none'; } }

export function startCountdown(totalSec, onTick, onDone) {
  clearInterval(_bubbleTimer);
  let s = Math.max(0, Math.floor(totalSec || 0));
  (onTick || (() => { }))(s);
  _bubbleTimer = setInterval(() => {
    s -= 1;
    if (s <= 0) {
      clearInterval(_bubbleTimer);
      (onTick || (() => { }))(0);
      (onDone || (() => { }))();
    } else {
      (onTick || (() => { }))(s);
    }
  }, 1000);
}

export function cancelCompoundAutoDispatch() {
  if (!window.__compoundTimers) return;
  try {
    if (window.__compoundTimers.tEnd) clearTimeout(window.__compoundTimers.tEnd);
    if (window.__compoundTimers.interval) clearInterval(window.__compoundTimers.interval);
  } catch { }
  window.__compoundTimers = null;
  try { hideBubble?.(); } catch { }
}

export function startCompoundAutoDispatch(ride) {
  cancelCompoundAutoDispatch();

  const ds = window.ccDispatchSettings || {};
  const enabled = ds.auto_dispatch_enabled !== false;

  const total = Number.isFinite(+ds.auto_dispatch_delay_s)
    ? Math.max(0, Math.floor(+ds.auto_dispatch_delay_s))
    : (Number.isFinite(+ds.auto_delay_sec) ? Math.max(0, Math.floor(+ds.auto_delay_sec)) : 20);

  if (!enabled || !ride) return;

  if (typeof isScheduledStatus === 'function' && isScheduledStatus(ride)) return;
  if (!isScheduledStatus && ride?.scheduled_for) return;

  try { showBubble?.('Servicio detectado…'); } catch { }

  window.__compoundTimers = window.__compoundTimers || {};

  window.__compoundTimers.tEnd = setTimeout(async () => {
    try { hideBubble?.(); } catch { }
    try {
      const tenantId = (typeof getTenantId === 'function' ? getTenantId() : (window.currentTenantId || ''));
      const resp = await fetch('/api/dispatch/tick', {
        method: 'POST',
        headers: jsonHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ ride_id: ride.id })
      });

      if (!resp.ok) {
        const txt = await resp.text().catch(() => '');
        console.warn('[auto] tick 500:', txt);
      }
    } catch (e) {
      console.warn('[auto] tick error:', e);
    }
  }, total * 1000);

  let left = total;
  window.__compoundTimers.interval = setInterval(() => {
    left -= 1;
    if (left <= 0) { clearInterval(window.__compoundTimers.interval); return; }
    try { updateBubble?.(`Asignando en ${left}s`); } catch { }
  }, 1000);
}

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
      auto_dispatch_enabled: !!json.auto_dispatch_enabled,
      auto_dispatch_delay_s: Number(json.auto_dispatch_delay_s ?? 20),
      auto_dispatch_preview_n: Number(json.auto_dispatch_preview_n ?? 12),
      auto_dispatch_preview_radius_km: Number(json.auto_dispatch_preview_radius_km ?? 5),
      offer_expires_sec: Number(json.offer_expires_sec ?? 180),
      auto_assign_if_single: !!json.auto_assign_if_single,
      allow_fare_bidding: !!json.allow_fare_bidding,
    };
    console.debug('[settings] OK', window.ccDispatchSettings);
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
window.startCompoundAutoDispatch = startCompoundAutoDispatch;
window.cancelCompoundAutoDispatch = cancelCompoundAutoDispatch;