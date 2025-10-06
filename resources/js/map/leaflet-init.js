import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

let map, baseLight, baseDark, currentBase;

export function initMap() {
  map = L.map('map', { center: [19.1738, -96.1342], zoom: 13 });

  baseLight = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
    attribution: '&copy; OSM'
  });
  // Ejemplo dark: Carto Dark (revisa términos de uso antes de prod)
  baseDark = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',{
    attribution: '&copy; OSM & Carto'
  });

  const theme = document.documentElement.getAttribute('data-theme') || 'light';
  currentBase = (theme === 'dark') ? baseDark : baseLight;
  currentBase.addTo(map);

  document.getElementById('btnCenter')?.addEventListener('click', () => map.setView([19.1738, -96.1342], 13));

  // Mantén sincronizado con el toggle
  window.addEventListener('theme:changed', (e) => {
    const next = e.detail?.theme || 'light';
    map.removeLayer(currentBase);
    currentBase = (next === 'dark') ? baseDark : baseLight;
    currentBase.addTo(map);
  });

  return map;
}


import { initMap } from './map/leaflet-init';
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('map')) {
    const map = initMap();

    // Escuchar ubicaciones en tiempo real (tenant=1 de demo)
    window.Echo.channel('driver.location.1')
      .listen('.LocationUpdated', (e) => {
        // TODO: actualizar o crear marcador del driver e.driver_id
        console.log('LocationUpdated', e);
      });
  }
});
