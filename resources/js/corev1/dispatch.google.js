/* resources/js/pages/dispatch/google.js */
import { qs } from './core.js';
import { setFrom, setTo, setStop1, setStop2 } from './map.js';

let gmapsReady = null;
let gDirService = null, gGeocoder = null;
let acFrom = null, acTo = null, acStop1 = null, acStop2 = null;

export function haveFullGoogle() {
  return !!(window.google?.maps && window.google.maps.places && window.google.maps.geometry);
}

export function loadGoogleMaps() {
  if (gmapsReady) return gmapsReady;
  gmapsReady = new Promise((resolve, reject) => {
    if (haveFullGoogle()) return resolve(window.google);
    const key =
      document.querySelector('meta[name="google-maps-key"]')?.content ||
      (window.ccGoogleMapsKey || '');
    const cbName = '__gmaps_cb_' + Math.random().toString(36).slice(2);
    window[cbName] = () => {
      if (haveFullGoogle()) resolve(window.google);
      else reject(new Error('Google loaded without Places/Geometry'));
      delete window[cbName];
    };
    const libs = 'places,geometry';
    const s = document.createElement('script');
    s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&libraries=${libs}&callback=${cbName}`;
    s.async = true; s.defer = true; s.onerror = (e) => reject(e);
    document.head.appendChild(s);
  });
  return gmapsReady;
}

export function initGoogleWidgets() {
  loadGoogleMaps().then((google) => {
    gDirService = new google.maps.DirectionsService();
    gGeocoder = new google.maps.Geocoder();

    acFrom = new google.maps.places.Autocomplete(qs('#inFrom'), { fields: ['formatted_address', 'geometry'] });
    acTo = new google.maps.places.Autocomplete(qs('#inTo'), { fields: ['formatted_address', 'geometry'] });
    
    acFrom.addListener('place_changed', () => {
      const p = acFrom.getPlace(); if (!p?.geometry) return;
      setFrom([p.geometry.location.lat(), p.geometry.location.lng()], p.formatted_address);
    });
    
    acTo.addListener('place_changed', () => {
      const p = acTo.getPlace(); if (!p?.geometry) return;
      setTo([p.geometry.location.lat(), p.geometry.location.lng()], p.formatted_address);
    });

    if (window.google?.maps?.places) {
      if (qs('#inStop1')) {
        acStop1 = new google.maps.places.Autocomplete(qs('#inStop1'), { fields: ['geometry', 'formatted_address'] });
        acStop1.addListener('place_changed', () => {
          const p = acStop1.getPlace();
          const ll = p?.geometry?.location;
          if (!ll) return;
          setStop1([ll.lat(), ll.lng()], p.formatted_address || null);
        });
      }
      
      if (qs('#inStop2')) {
        acStop2 = new google.maps.places.Autocomplete(qs('#inStop2'), { fields: ['geometry', 'formatted_address'] });
        acStop2.addListener('place_changed', () => {
          if (!Number.isFinite(parseFloat(qs('#stop1Lat')?.value))) return;
          const p = acStop2.getPlace();
          const ll = p?.geometry?.location;
          if (!ll) return;
          setStop2([ll.lat(), ll.lng()], p.formatted_address || null);
        });
      }
    }
  }).catch(e => console.warn('[DISPATCH] Google no cargó', e));
}

export function reverseGeocode(latlng, inputSel) {
  if (!gGeocoder) return;
  gGeocoder.geocode({ location: { lat: latlng[0], lng: latlng[1] } }, (res, status) => {
    if (status === 'OK' && res?.[0]) qs(inputSel).value = res[0].formatted_address;
  });
}

export { gDirService, gGeocoder };