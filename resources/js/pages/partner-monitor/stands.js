// resources/js/partner-monitor/stands.js
import L from 'leaflet';
import { qsMeta, getJson, toNum, escapeHtml } from './net';

export function createStandsController(ctx) {
  let _pmStandsFittedOnce = false;

  async function loadStands() {
    try {
      const tenantId = qsMeta('tenant-id') || String(window.ccTenant?.id || '');
      const url = tenantId ? `/api/taxistands?tenant_id=${encodeURIComponent(tenantId)}` : '/api/taxistands';
      const j = await getJson(url);
      const arr = Array.isArray(j) ? j : (Array.isArray(j?.items) ? j.items : []);

      console.debug('[PM] taxistands fetched', { url, tenantId, count: arr.length, sample: arr[0] });

      ctx.layerStands.clearLayers();

      if (!arr.length) {
        console.warn('[PM] No taxistands received', { tenantId, url, data: j });
        return;
      }

      const iconUrlStand = window.TENANT_ICONS?.stand || window.ccTenantIcons?.stand || '/images/marker-parqueo5.png';

      // probe
      const probe = new Image();
      probe.onload  = () => console.debug('[PM] stand icon loaded', iconUrlStand);
      probe.onerror = () => console.warn('[PM] stand icon FAILED', iconUrlStand);
      probe.src = iconUrlStand;

      const IconStand = L.icon({
        iconUrl: iconUrlStand,
        iconSize: [28, 28],
        iconAnchor: [14, 28],
        tooltipAnchor: [0, -28],
        className: 'pm-stand-icon',
      });

      const pts = [];

      for (const st of arr) {
        const lat = toNum(st.latitud ?? st.lat ?? st.latitude);
        const lng = toNum(st.longitud ?? st.lng ?? st.longitude);
        if (lat == null || lng == null) continue;

        pts.push([lat, lng]);

        const name = st.nombre || st.name || 'Base';

        const marker = L.marker([lat, lng], {
          icon: IconStand,
          pane: 'pmStandsPane',
          interactive: true,
          zIndexOffset: 10_000,
        });

        marker.bindTooltip(escapeHtml(name), { direction: 'top', opacity: 0.9, offset: [0, -12] });
        marker.addTo(ctx.layerStands);
      }

      console.debug('[PM] taxistands rendered', { markers: ctx.layerStands.getLayers().length });

      // fit opcional (1 vez)
      if (pts.length) {
        const bounds = L.latLngBounds(pts);
        const anyVisible = pts.some(([la, ln]) => ctx.map.getBounds().contains([la, ln]));
        const hasDrivers = (ctx.driverPins?.size ?? 0) > 0;

        if ((!hasDrivers || !anyVisible) && !_pmStandsFittedOnce) {
          _pmStandsFittedOnce = true;
          ctx.map.fitBounds(bounds.pad(0.25));
          console.debug('[PM] fitBounds -> taxistands');
        }
      }

    } catch (e) {
      console.warn('[PartnerMonitor] loadStands fail', e);
    }
  }

  return { loadStands };
}
