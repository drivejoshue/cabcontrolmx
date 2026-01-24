export function decodeAnyPolyline(poly) {
  if (!poly) return [];

  const s = String(poly).trim();

  // JSON de puntos: [[lat,lng],...]
  if (s.startsWith('[')) {
    try {
      const arr = JSON.parse(s);
      if (Array.isArray(arr)) {
        return arr
          .map(p => Array.isArray(p) ? [Number(p[0]), Number(p[1])] : [Number(p.lat), Number(p.lng)])
          .filter(p => Number.isFinite(p[0]) && Number.isFinite(p[1]));
      }
    } catch {}
  }

  // Google encoded polyline
  return decodeGooglePolyline(s);
}

function decodeGooglePolyline(encoded) {
  let index = 0, lat = 0, lng = 0;
  const coordinates = [];

  while (index < encoded.length) {
    let b, shift = 0, result = 0;
    do {
      b = encoded.charCodeAt(index++) - 63;
      result |= (b & 0x1f) << shift;
      shift += 5;
    } while (b >= 0x20);
    const dlat = (result & 1) ? ~(result >> 1) : (result >> 1);
    lat += dlat;

    shift = 0; result = 0;
    do {
      b = encoded.charCodeAt(index++) - 63;
      result |= (b & 0x1f) << shift;
      shift += 5;
    } while (b >= 0x20);
    const dlng = (result & 1) ? ~(result >> 1) : (result >> 1);
    lng += dlng;

    coordinates.push([lat / 1e5, lng / 1e5]);
  }

  return coordinates;
}
