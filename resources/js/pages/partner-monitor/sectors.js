// resources/js/partner-monitor/sectors.js
import { qsMeta, getJson } from './net';

function normalizeGeojson(x) {
  if (!x) return null;
  if (typeof x === 'string') {
    try { return JSON.parse(x); } catch { return null; }
  }
  return x;
}

function rowToFeature(row) {
  const geo = normalizeGeojson(row.area ?? row.geojson ?? row.geometry);
  if (!geo || typeof geo !== 'object') return null;

  const type = geo.type ?? null;

  if (type === 'Feature' && geo.geometry) {
    return {
      type: 'Feature',
      geometry: geo.geometry,
      properties: { id: row.id, nombre: row.nombre, ...(geo.properties || {}) },
    };
  }

  if (type === 'Polygon' || type === 'MultiPolygon') {
    return {
      type: 'Feature',
      geometry: geo,
      properties: { id: row.id, nombre: row.nombre },
    };
  }

  return null;
}

export function createSectorsController(ctx) {
  async function loadSectores() {
    try {
      const tenantId = qsMeta('tenant-id') || String(window.ccTenant?.id || '');
      const url = tenantId ? `/api/sectores?tenant_id=${encodeURIComponent(tenantId)}` : '/api/sectores';
      const data = await getJson(url);

      ctx.layerSectors.clearLayers();

      // Caso 1: FeatureCollection
      if (data && data.type === 'FeatureCollection' && Array.isArray(data.features)) {
        if (data.features.length) {
          ctx.layerSectors.addData(data);
          ctx.layerSectors.setStyle(ctx.sectorStyle());
        } else {
          console.warn('[PM] Sectores FeatureCollection vacía', { tenantId, url, data });
        }
        return;
      }

      // Caso 2: array de rows
      const rows = Array.isArray(data)
        ? data
        : (Array.isArray(data?.items) ? data.items : (Array.isArray(data?.data) ? data.data : []));

      const features = [];
      for (const row of rows) {
        const f = rowToFeature(row);
        if (f) features.push(f);
      }

      if (features.length) {
        ctx.layerSectors.addData({ type: 'FeatureCollection', features });
        ctx.layerSectors.setStyle(ctx.sectorStyle());
      } else {
        console.warn('[PM] No sectores received', { tenantId, url, data });
      }
    } catch (e) {
      console.error('[PM] loadSectores failed', e);
    }
  }

  function bindThemeObserver(){
    const apply = () => {
      try { ctx.layerSectors.setStyle(ctx.sectorStyle()); } catch {}
    };

    window.addEventListener('theme:changed', apply);

    const obs = new MutationObserver(apply);
    obs.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['data-theme', 'data-bs-theme', 'class'],
    });

    // opcional: retorna cleanup si algún día desmontas
    return () => {
      window.removeEventListener('theme:changed', apply);
      obs.disconnect();
    };
  }

  return { loadSectores, bindThemeObserver };
}
