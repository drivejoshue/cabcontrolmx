/* resources/js/pages/dispatch.suggested_routes.js */
import L from 'leaflet';

// Guarda polylines por driverId
function _store() {
  if (!window.__ccSuggestedByDriver) window.__ccSuggestedByDriver = new Map();
  return window.__ccSuggestedByDriver;
}

function _layer(ctx) {
  return ctx?.layerSuggested || ctx?.layers?.suggested || window.layerSuggested || null;
}

// Obtiene marker del driver desde driverPins (ctx o window)
function _driverMarker(driverId, ctx) {
  const pins = ctx?.driverPins || window.driverPins || null;
  if (!pins?.get) return null;
  const entry = pins.get(driverId);
  return entry?.marker || null;
}

export function clearDriverRoute(driverId, ctx = {}) {
  const layer = _layer(ctx);
  const st = _store();

  const line = st.get(driverId);
  if (line && layer?.removeLayer) {
    try { layer.removeLayer(line); } catch {}
  }
  st.delete(driverId);
}

/**
 * Dibuja ruta sugerida driver->pickup.
 * Por ahora línea recta (estable). Después puedes conectar /api/geo/route.
 */
export async function showDriverToPickup(driverId, pickupLat, pickupLng, ctx = {}) {
  const layer = _layer(ctx);
  const marker = _driverMarker(driverId, ctx);
  if (!layer || !marker) return;

  const from = marker.getLatLng();
  const toLat = Number(pickupLat), toLng = Number(pickupLng);
  if (!Number.isFinite(toLat) || !Number.isFinite(toLng)) return;

  // limpia ruta previa
  clearDriverRoute(driverId, ctx);

  const line = L.polyline(
    [[from.lat, from.lng], [toLat, toLng]],
    {
      weight: 5,
      opacity: 0.95,
      dashArray: '6,8',
      pane: 'suggestedPane', // debe existir en bootstrap
    }
  ).addTo(layer);

  _store().set(driverId, line);
}

