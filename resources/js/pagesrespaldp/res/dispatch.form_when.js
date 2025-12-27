import { qs } from './dispatch.core.js';

export function resetWhenNow(){
  const rNow   = qs('#when-now');
  const rLater = qs('#when-later');
  const sched  = qs('#scheduleAt');
  const row    = qs('#scheduleRow');

  if (rNow)   rNow.checked   = true;
  if (rLater) rLater.checked = false;
  if (sched)  sched.value    = '';
  if (row)    row.style.display = 'none';

  const rs = qs('#routeSummary');
  if (rs) rs.innerText = 'Ruta: — · Zona: — · Cuando: ahora';
}

export function wireAutoFixedWhenTyping(){
  const amt = qs('#fareAmount');
  const fm  = qs('#fareMode');
  if (!amt || !fm) return;

  amt.addEventListener('input', () => {
    const v = Number(amt.value);
    if (Number.isFinite(v) && fm.value !== 'fixed') fm.value = 'fixed';
  });

  fm.addEventListener('change', () => {
    if (fm.value === 'meter' && window.__lastQuote?.amount != null) {
      amt.value = window.__lastQuote.amount;
    }
  });
}



// -----------------------------
// Quote UI (O/D + stops)
// -----------------------------
export async function recalcQuoteUI(){
  try {
    const fromLat = parseFloat(qs('#fromLat')?.value || '');
    const fromLng = parseFloat(qs('#fromLng')?.value || '');
    const toLat   = parseFloat(qs('#toLat')?.value   || '');
    const toLng   = parseFloat(qs('#toLng')?.value   || '');

    if (!Number.isFinite(fromLat) || !Number.isFinite(fromLng) ||
        !Number.isFinite(toLat)   || !Number.isFinite(toLng)) {
      return;
    }

    const stops = [];
    const s1lat = parseFloat(qs('#stop1Lat')?.value || '');
    const s1lng = parseFloat(qs('#stop1Lng')?.value || '');
    if (Number.isFinite(s1lat) && Number.isFinite(s1lng)) stops.push({ lat: s1lat, lng: s1lng });

    const s2lat = parseFloat(qs('#stop2Lat')?.value || '');
    const s2lng = parseFloat(qs('#stop2Lng')?.value || '');
    if (Number.isFinite(s2lat) && Number.isFinite(s2lng)) stops.push({ lat: s2lat, lng: s2lng });

    const body = {
      origin:      { lat: fromLat, lng: fromLng },
      destination: { lat: toLat,   lng: toLng   },
      stops,
      round_to_step: 1,
    };

    const url = window.__QUOTE_URL__ || '/api/dispatch/quote';

    const resp = await fetch(url, {
      method: 'POST',
      headers: jsonHeaders({ 'Content-Type':'application/json' }),
      body: JSON.stringify(body),
    });

    if (!resp.ok) return;

    const data = await resp.json();
    const amount = data?.amount;
    if (amount == null) return;

    // Respeta fixed / locks si existen
    const amt = qs('#fareAmount');
    const fm  = qs('#fareMode');
    const fixedByMode = (fm && fm.value === 'fixed');
    const fixedByLock = !!(qs('#fareFixed')?.checked || qs('#fareLock')?.checked);

    if (amt && !fixedByMode && !fixedByLock) {
      amt.value = amount;
    }

    const rs = qs('#routeSummary');
    if (rs) {
      const km  = ((data.distance_m ?? 0) / 1000).toFixed(2);
      const min = Math.round((data.duration_s ?? 0) / 60);
      const sn  = data.stops_n ?? stops.length;
      rs.innerText = `Ruta: ${km} km · ${min} min · Paradas: ${sn} · Tarifa: $${amount}`;
    }

    // Guarda snapshot completo para create_ride.js
    window.__lastQuote = {
      amount,
      distance_m: data.distance_m ?? null,
      duration_s: data.duration_s ?? null,
      stops_n: data.stops_n ?? stops.length,
      polyline: data.polyline ?? data.route_polyline ?? null,
      tenant_id: data.tenant_id ?? null,
    };

  } catch (e) {
    console.warn('[quote] recalcQuoteUI error', e);
  }
}
