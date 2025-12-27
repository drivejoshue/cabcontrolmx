// resources/js/pages/dispatch.stops.js

import { qs } from './dispatch.core.js';
import { recalcQuoteUI } from './dispatch.form_when.js';

/**
 * Carga stops desde la respuesta /last-ride.
 * Previene doble ejecución con window.loadingStops
 */
export async function loadStopsFromLastRide(lastRide){
  try {
    if (window.loadingStops) return;
    window.loadingStops = true;

    let stops = [];

    if (Array.isArray(lastRide?.stops) && lastRide.stops.length > 0) {
      stops = lastRide.stops;
    } else if (lastRide?.stops_json) {
      try {
        const parsed = typeof lastRide.stops_json === 'string'
          ? JSON.parse(lastRide.stops_json)
          : lastRide.stops_json;
        if (Array.isArray(parsed)) stops = parsed;
      } catch (e) {
        console.warn('Error parsing stops_json:', e);
      }
    }

    if (stops.length > 0) {
      setStopsInForm(stops);
    }
  } catch (err) {
    console.warn('Error loading stops from last ride:', err);
  } finally {
    window.loadingStops = false;
  }
}

/**
 * Establece stops en el formulario (máx 2) y redibuja ruta.
 */
export function setStopsInForm(stops){
  if (!Array.isArray(stops) || stops.length === 0) return;

  // Limpia markers existentes
  try { if (window.stop1Marker) { window.stop1Marker.remove(); window.stop1Marker = null; } } catch {}
  try { if (window.stop2Marker) { window.stop2Marker.remove(); window.stop2Marker = null; } } catch {}

  // Limpia inputs
  ['#stop1Lat','#stop1Lng','#inStop1','#stop2Lat','#stop2Lng','#inStop2'].forEach(sel=>{
    const el = qs(sel);
    if (el) el.value = '';
  });

  const stop1Row = qs('#stop1Row');
  const stop2Row = qs('#stop2Row');
  if (stop1Row) stop1Row.style.display = 'none';
  if (stop2Row) stop2Row.style.display = 'none';

  // Set 1..2 stops
  stops.forEach((stop, index) => {
    if (index >= 2) return;

    const lat = Number(stop?.lat);
    const lng = Number(stop?.lng);
    const label = stop?.label || stop?.address || '';

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

    if (index === 0) {
      if (stop1Row) stop1Row.style.display = '';
      window.setStop1?.([lat, lng], label);
    } else if (index === 1) {
      if (stop2Row) stop2Row.style.display = '';
      window.setStop2?.([lat, lng], label);
    }
  });

  // Redibuja + quote
  setTimeout(() => {
    try { window.drawRoute?.({ quiet:true }); } catch {}
    try { window.autoQuoteIfReady?.(); } catch {}
    try { recalcQuoteUI?.(); } catch {}
  }, 250);
}

/**
 * Limpia SOLO la ruta del flujo dispatch (sin tocar sectores/otras polylines).
 */
export function clearRoute(){
  try {
    // Si usas una polyline suelta
    if (window.routeLine) {
      try { window.routeLine.remove(); } catch {}
      window.routeLine = null;
    }

    // Si tu ruta se dibuja en layerRoute, limpia ahí
    if (window.layerRoute?.clearLayers) {
      window.layerRoute.clearLayers();
    }

    console.debug('[route] cleared');
  } catch (error) {
    console.warn('Error limpiando ruta:', error);
  }
}
