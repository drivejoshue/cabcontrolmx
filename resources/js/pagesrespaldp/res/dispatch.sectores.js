// resources/js/pages/dispatch.sectores.js

import { jsonHeaders, escapeHtml } from './dispatch.core.js';


export function sectorStyle() {
  // deja tu implementación actual aquí
  // return {...}
  return window.sectorStyle?.() || { weight: 1, opacity: 0.8 };
}

export async function loadSectores(ctx = {}) {
  const { layerSectores } = ctx;

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
          if (area.type === 'Feature') {
            area.properties = { ...(area.properties||{}), nombre: row.nombre };
            return area;
          }
          return { type:'Feature', properties:{ nombre: row.nombre }, geometry: area };
        }).filter(Boolean) }
      : data;

    L.geoJSON(fc, {
      pane:'sectoresPane',
      style: sectorStyle,
      interactive:false,
      onEachFeature:(f,l)=> l.bindTooltip(
        `<strong>${f?.properties?.nombre || 'Sector'}</strong>`,
        { direction:'top', offset:[0,-4], className:'sector-tip' }
      )
    }).addTo(layerSectores);
  } catch (e) {
    console.warn('sectores error', e);
  }
}
