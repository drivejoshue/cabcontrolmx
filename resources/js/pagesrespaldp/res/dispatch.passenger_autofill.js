// resources/js/pages/dispatch.passenger_autofill.js

function _toNum(v) {
  const n = typeof v === 'number' ? v : parseFloat(String(v ?? '').trim());
  return Number.isFinite(n) ? n : null;
}

export function wirePassengerLastRide({ qs, jsonHeaders, recalcQuoteUI }) {
  const phoneEl = qs('#pass-phone');
  if (!phoneEl) return;

  let _abort = null;
  let _lastPhone = null;
  let _timer = null;

  phoneEl.addEventListener('blur', () => {
    const phone = (phoneEl.value || '').trim();
    if (!phone) return;

    // Si ya hay origen o destino, no autocompletar (respeta lo que ya capturó el despachador)
    const hasA = !!(qs('#fromLat')?.value && qs('#fromLng')?.value);
    const hasB = !!(qs('#toLat')?.value   && qs('#toLng')?.value);
    if (hasA || hasB) return;

    // evita spam si blur repetido con mismo valor
    if (_lastPhone === phone) return;
    _lastPhone = phone;

    // debounce ligero (por si blur disparado por UI)
    if (_timer) clearTimeout(_timer);
    _timer = setTimeout(async () => {
      try {
        if (_abort) { try { _abort.abort(); } catch {} }
        _abort = new AbortController();

        const r = await fetch(`/api/passengers/last-ride?phone=${encodeURIComponent(phone)}`, {
          headers: jsonHeaders(),
          signal: _abort.signal
        });
        if (!r.ok) return;

        const lastRide = await r.json().catch(() => null);
        if (!lastRide) return;

        // Solo rellenar datos “vacíos” (no pisar)
        const nameEl = qs('#pass-name');
        if (lastRide.passenger_name && nameEl && !nameEl.value) nameEl.value = lastRide.passenger_name;

        const notesEl = qs('#ride-notes');
        if (lastRide.notes && notesEl && !notesEl.value) notesEl.value = lastRide.notes;

        // Limpieza previa visual (si existen)
        try {
          if (typeof window.clearAssignArtifacts === 'function') window.clearAssignArtifacts();
          if (window.rideMarkers?.forEach && window.rideMarkers?.clear) {
            window.rideMarkers.forEach(g => { try { g.remove(); } catch {} });
            window.rideMarkers.clear();
          }
        } catch {}

        const oLat = _toNum(lastRide.origin_lat);
        const oLng = _toNum(lastRide.origin_lng);
        const dLat = _toNum(lastRide.dest_lat);
        const dLng = _toNum(lastRide.dest_lng);

        if (oLat !== null && oLng !== null && typeof window.setFrom === 'function') {
          window.setFrom([oLat, oLng], lastRide.origin_label || null);
        }
        if (dLat !== null && dLng !== null && typeof window.setTo === 'function') {
          window.setTo([dLat, dLng], lastRide.dest_label || null);
        }

        // stops (si existe helper)
        if (typeof window.loadStopsFromLastRide === 'function') {
          await window.loadStopsFromLastRide(lastRide);
        }

        // recalcula quote con O/D(+stops)
        if (typeof recalcQuoteUI === 'function') {
          await recalcQuoteUI();
        }
      } catch (err) {
        // Abort no es “error real”
        if (String(err?.name) === 'AbortError') return;
        console.warn('Error in phone autocomplete:', err);
      }
    }, 120);
  });
}
