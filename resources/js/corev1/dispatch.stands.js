import { getTenantId, jsonHeaders, fmt, escapeHtml } from './dispatch.core.js';
import { qs } from './dispatch.core.js';

function defaultStandIcon() {
  if (window.IconStand) return window.IconStand;

  return L.divIcon({
    className: 'cc-stand-icon',
    html: `<div style="
      width:14px;height:14px;border-radius:50%;
      background: var(--bs-warning, #f59e0b);
      border:2px solid rgba(0,0,0,.25);
      box-shadow:0 4px 10px rgba(0,0,0,.18);
    "></div>`,
    iconSize: [14, 14],
    iconAnchor: [7, 7],
  });
}

export async function loadStands(ctx = {}) {
  const { layerStands } = ctx;
  if (!layerStands) return;

  const tenantId = getTenantId();

  try {
    const r = await fetch(`/api/taxistands?tenant_id=${encodeURIComponent(tenantId)}`, {
      headers: jsonHeaders({ 'X-Tenant-ID': tenantId || '' })
    });

    if (!r.ok) return;

    const list = await r.json();
    layerStands.clearLayers();

    const icon = defaultStandIcon();

    (list || []).forEach(z => {
      const lat = Number(z.latitud ?? z.lat);
      const lng = Number(z.longitud ?? z.lng);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

      const name = escapeHtml(z.nombre || z.name || 'Base');

      L.marker([lat, lng], { icon, zIndexOffset: 20 })
        .bindTooltip(
          `<strong>${name}</strong><div class="text-muted">(${fmt(lat)}, ${fmt(lng)})</div>`,
          { direction:'top', offset:[0,-12], className:'stand-tip' }
        )
        .addTo(layerStands);
    });
  } catch (e) {
    console.warn('[stands] error', e);
  }
}
