// resources/js/pages/dispatch.passenger_autofill.js

export function wirePassengerLastRide({ qs, jsonHeaders, recalcQuoteUI }){
  qs('#pass-phone')?.addEventListener('blur', async (e) => {
    const phone = (e.target.value || '').trim();
    if (!phone) return;

    const hasA = !!(qs('#fromLat')?.value && qs('#fromLng')?.value);
    const hasB = !!(qs('#toLat')?.value   && qs('#toLng')?.value);
    if (hasA || hasB) return;

    try {
      const r = await fetch(`/api/passengers/last-ride?phone=${encodeURIComponent(phone)}`, {
        headers: jsonHeaders()
      });
      if (!r.ok) return;

      const lastRide = await r.json();
      if (!lastRide) return;

      if (lastRide.passenger_name && !qs('#pass-name')?.value) {
        qs('#pass-name').value = lastRide.passenger_name;
      }
      if (lastRide.notes && !qs('#ride-notes')?.value) {
        qs('#ride-notes').value = lastRide.notes;
      }

      try {
        window.clearAssignArtifacts?.();
        if (window.rideMarkers) {
          window.rideMarkers.forEach(g => { try { g.remove(); } catch {} });
          window.rideMarkers.clear();
        }
      } catch {}

      if (Number.isFinite(lastRide.origin_lat) && Number.isFinite(lastRide.origin_lng)) {
        window.setFrom?.([lastRide.origin_lat, lastRide.origin_lng], lastRide.origin_label);
      }
      if (Number.isFinite(lastRide.dest_lat) && Number.isFinite(lastRide.dest_lng)) {
        window.setTo?.([lastRide.dest_lat, lastRide.dest_lng], lastRide.dest_label);
      }

      // si ya tienes este helper implementado
      if (typeof window.loadStopsFromLastRide === 'function') {
        await window.loadStopsFromLastRide(lastRide);
      }

      // recalcula quote con O/D(+stops)
      await recalcQuoteUI?.();

    } catch (err) {
      console.warn('Error in phone autocomplete:', err);
    }
  });
}
