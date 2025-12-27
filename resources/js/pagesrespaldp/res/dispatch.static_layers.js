import L from 'leaflet';
import { jsonHeaders, escapeHtml, getTenantId, fmt } from './dispatch.core.js';

export function sectorStyle() {
  if (typeof window.sectorStyle === 'function') return window.sectorStyle();
  return { weight: 1, opacity: 0.8, fillOpacity: 0.10 };
}

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

export async function loadSectores(ctx = {}) {
  const { layerSectores } = ctx;
  if (!layerSectores) return;

  try {
    const r = await fetch('/api/sectores', { headers: jsonHeaders() });
    if (!r.ok) return;
    const data = await r.json();

    layerSectores.clearLayers();

    const fc = Array.isArray(data)
      ? { type:'FeatureCollection', features: data.map(row=>{
          let area = row.area;
          if (typeof area === 'string') { try { area = JSON.parse(area); } catch { area = null; } }
          if (!area) return null;

          const nombre = escapeHtml(row.nombre || 'Sector');

          if (area.type === 'Feature') {
            area.properties = { ...(area.properties||{}), nombre };
            return area;
          }
          return { type:'Feature', properties:{ nombre }, geometry: area };
        }).filter(Boolean) }
      : data;

    L.geoJSON(fc, {
      pane:'sectoresPane',
      style: sectorStyle,
      interactive:false,
      onEachFeature:(f,l)=> l.bindTooltip(
        `<strong>${escapeHtml(f?.properties?.nombre || 'Sector')}</strong>`,
        { direction:'top', offset:[0,-4], className:'sector-tip' }
      )
    }).addTo(layerSectores);
  } catch (e) {
    console.warn('[sectores] error', e);
  }
}

export async function loadStands(ctx = {}) {
  const { layerStands } = ctx;
  if (!layerStands) return;

  try {
    const tenantId = getTenantId();
    const r = await fetch(`/api/taxistands?tenant_id=${encodeURIComponent(tenantId)}`, {
      headers: jsonHeaders()
    });
    if (!r.ok) return;

    const list = await r.json();
    layerStands.clearLayers();

    const IconStand = defaultStandIcon();

    list.forEach(z=>{
      const lat = Number(z.latitud), lng = Number(z.longitud);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

      L.marker([lat,lng], { icon: IconStand, zIndexOffset: 20 })
        .bindTooltip(
          `<strong>${escapeHtml(z.nombre || 'Base')}</strong>
           <div class="text-muted">(${fmt(lat)}, ${fmt(lng)})</div>`,
          { direction:'top', offset:[0,-12], className:'stand-tip' }
        )
        .addTo(layerStands);
    });
  } catch (e) {
    console.warn('[stands] error', e);
  }
}
