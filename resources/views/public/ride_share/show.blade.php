<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Orbana · Seguimiento</title>

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <style>
    body { font-family: system-ui, Arial; margin: 0; background:#0b0f14; color:#e7eef8; }
    .wrap { max-width: 980px; margin: 0 auto; padding: 16px; }
    .card { background:#121a24; border:1px solid #223247; border-radius:16px; padding:16px; }
    .grid { display:grid; grid-template-columns: 1fr; gap: 12px; }
    @media(min-width: 900px){ .grid { grid-template-columns: 1.2fr 0.8fr; } }
    #map { width:100%; height: 58vh; border-radius: 14px; overflow:hidden; border:1px solid #223247; }
    .muted { color:#a9b7c6; font-size: 13px; }
    .row { display:flex; justify-content:space-between; gap: 12px; align-items:center; }
    .pill { font-size:12px; padding:6px 10px; border-radius:999px; border:1px solid #223247; background:#0e1520; }
    .kv { margin: 10px 0; }
    .kv b { display:block; font-size:12px; color:#a9b7c6; margin-bottom:4px; }
    .kv div { font-size:14px; }
    .danger { border-color:#6b2a2a; background:#1b1011; }
    .ok { border-color:#2a6b52; background:#0f1b16; }

    .h1 { font-weight:800; font-size:18px; letter-spacing:.2px; margin:0; }
    .sub { margin-top:4px; }
    .note { margin-top:10px; padding:10px 12px; border-radius:12px; border:1px dashed #223247; background:#0e1520; }
    .note b { color:#e7eef8; }
    .small { font-size:12px; }
    .hr { height:1px; background:#223247; opacity:.55; margin:12px 0; border:0; }

    /* Estado de error suave (sin romper UI) */
    .warn { border-color:#6b5a2a; background:#1b1710; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="row" style="margin-bottom:12px;">
      <div>
        <div class="h1">Seguimiento de viaje</div>
        <div class="muted sub">
          Enlace temporal para ver el avance del taxi. Se desactiva automáticamente al finalizar o cancelar el viaje.
        </div>
      </div>
      <div id="statusPill" class="pill">Cargando…</div>
    </div>

    <div class="grid">
      <div class="card">
        <div id="map"></div>

        <div class="muted" style="margin-top:10px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
          <div>Última actualización: <span id="lastTs">—</span></div>
          <div class="small">Si el viaje termina, el seguimiento se detiene automáticamente.</div>
        </div>
      </div>

      <div class="card">
        <div class="kv">
          <b>Conductor</b>
          <div id="driverName">—</div>
        </div>

        <div class="kv">
          <b>Vehículo</b>
          <div id="vehicleLine">—</div>
        </div>

        <div class="kv">
          <b>Origen</b>
          <div id="originLine">—</div>
        </div>

        <div class="kv">
          <b>Destino</b>
          <div id="destLine">—</div>
          <div class="note muted small">
            <b>Privacidad:</b> el destino se muestra únicamente cuando el viaje está en curso.
          </div>
        </div>

        <hr class="hr">

        <div id="endedBox" class="note danger" style="display:none;">
          <b>Viaje finalizado.</b>
          <div class="muted small" style="margin-top:4px;">
            El enlace de seguimiento se desactivó y ya no se actualizará la ubicación.
          </div>
        </div>

        <div id="errorBox" class="note warn" style="display:none;">
          <b>Conexión inestable.</b>
          <div class="muted small" style="margin-top:4px;">
            Estamos intentando reconectar. El último estado mostrado puede no ser el más reciente.
          </div>
        </div>
      </div>
    </div>
  </div>

<script>
  // ===== Variables Blade =====
  const TOKEN   = @json($token);
  const INITIAL = @json($snapshot ?? []);

  // Endpoint público del estado
  const STATE_URL = "{{ url('/ride_share/'.$token.'/state') }}";

  // ===== Leaflet map =====
  const map = L.map('map', { zoomControl: true, attributionControl: false });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

  // Vista inicial (evita 0,0 mientras llega snapshot)
  map.setView([19.2221, -96.1775], 15);

  // Icono coche
  const carIcon = L.icon({
    iconUrl: "{{ asset('images/vehicles/sedan.png') }}",
    iconSize: [36, 36],
    iconAnchor: [18, 18],
  });

  // Markers
  const pickupMarker = L.marker([0,0], { opacity: 0 }).addTo(map);
  const destMarker   = L.marker([0,0], { opacity: 0 }).addTo(map);
  const driverMarker = L.marker([0,0], { opacity: 0, icon: carIcon, zIndexOffset: 1000 }).addTo(map);

  // ===== Ruta fija (pickup -> destino) =====
  let fixedRouteLine = null;
  let fixedRouteSet = false;

  function decodePolyline(encoded) {
    let index = 0, lat = 0, lng = 0, coordinates = [];

    while (index < encoded.length) {
      let b, shift = 0, result = 0;
      do {
        b = encoded.charCodeAt(index++) - 63;
        result |= (b & 0x1f) << shift;
        shift += 5;
      } while (b >= 0x20);
      const dlat = (result & 1) ? ~(result >> 1) : (result >> 1);
      lat += dlat;

      shift = 0;
      result = 0;
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

  function setFixedRoute(points) {
    if (!points || points.length < 2) return;

    if (!fixedRouteLine) {
      fixedRouteLine = L.polyline(points, { weight: 4, opacity: 0.7 }).addTo(map);
    } else {
      fixedRouteLine.setLatLngs(points);
    }
    fixedRouteSet = true;
  }

  async function ensureFixedRoute(snapshot) {
    if (fixedRouteSet) return;

    const ride = snapshot?.ride;
    const o = ride?.origin;
    const d = ride?.destination;

    if (o?.lat == null || o?.lng == null || d?.lat == null || d?.lng == null) return;

    try {
      const res = await fetch("/api/geo/route", {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          from: { lat: o.lat, lng: o.lng },
          to: { lat: d.lat, lng: d.lng },
          mode: "driving",
        })
      });

      if (!res.ok) throw new Error("geo route http " + res.status);

      const r = await res.json();
      if (!r.ok) throw new Error("geo route not ok");

      if (r.polyline) {
        setFixedRoute(decodePolyline(r.polyline));
        return;
      }

      if (r.points && Array.isArray(r.points) && r.points.length >= 2) {
        setFixedRoute(r.points);
        return;
      }

      setFixedRoute([[o.lat, o.lng], [d.lat, d.lng]]);
    } catch (e) {
      setFixedRoute([[o.lat, o.lng], [d.lat, d.lng]]);
    }
  }

  function setMarker(marker, lat, lng, visible) {
    if (lat == null || lng == null) {
      marker.setOpacity(0);
      return;
    }
    marker.setLatLng([lat, lng]);
    marker.setOpacity(visible ? 1 : 0);
  }

  function fitIfPossible(points) {
    const valid = points.filter(p => p && p[0] != null && p[1] != null);
    if (valid.length >= 1) {
      const bounds = L.latLngBounds(valid.map(p => L.latLng(p[0], p[1])));
      map.fitBounds(bounds.pad(0.22));
    }
  }

  function setStatusPill(text, kind) {
    const el = document.getElementById('statusPill');
    el.textContent = text || '—';
    el.classList.remove('danger', 'ok', 'warn');
    if (kind) el.classList.add(kind);
  }

  function safeText(id, text) {
    document.getElementById(id).textContent = (text == null || text === '') ? '—' : text;
  }

  function showEndedUI(labelText = 'FINALIZADO') {
    setStatusPill(labelText, 'danger');
    document.getElementById('endedBox').style.display = 'block';
    document.getElementById('errorBox').style.display = 'none';
  }

  function showErrorUI() {
    // No "matamos" el estado, solo avisamos.
    document.getElementById('errorBox').style.display = 'block';
  }

  function hideErrorUI() {
    document.getElementById('errorBox').style.display = 'none';
  }

  // Etiquetas amigables
  const statusLabel = {
    searching: 'BUSCANDO',
    offered: 'OFERTADO',
    accepted: 'ASIGNADO',
    arrived: 'LLEGÓ',
    on_board: 'EN VIAJE',
    finished: 'FINALIZADO',
    canceled: 'CANCELADO',
    ended: 'FINALIZADO',
    expired: 'EXPIRADO',
    revoked: 'REVOCADO',
  };

  function pillTextFor(stLower) {
    return statusLabel[stLower] || (stLower ? stLower.toUpperCase() : '—');
  }

  // Control de cámara: NO reencuadrar cada poll
  let didInitialFit = false;
  let lastFitMode = null; // 'pickup' | 'dest'

  function render(snapshot, ts) {
    const ride = snapshot.ride || null;
    const driver = snapshot.driver || null;
    const vehicle = snapshot.vehicle || null;
    const loc = snapshot.location || null;

    const st = ride ? (ride.status || '—') : '—';
    const stLower = (st || '').toLowerCase();

    const pill = pillTextFor(stLower);

    // Status pill + ended box
    if (['finished','canceled'].includes(stLower)) {
      showEndedUI(pill);
    } else {
      setStatusPill(pill, 'ok');
      document.getElementById('endedBox').style.display = 'none';
    }

    safeText('driverName', driver?.name || '—');

    if (vehicle) {
      const line = [vehicle.brand, vehicle.model, vehicle.color, vehicle.plate].filter(Boolean).join(' · ');
      safeText('vehicleLine', line);
    } else {
      safeText('vehicleLine', '—');
    }

    safeText('originLine', ride?.origin ? (ride.origin.label || '') : '—');

    // Privacidad: destino solo si va on_board
    const showDest = (stLower === 'on_board');
    const destLabel = ride?.destination ? (ride.destination.label || '') : '';
    safeText('destLine', showDest ? (destLabel || '—') : '—');

    // Markers
    const o = ride?.origin || {};
    const d = ride?.destination || {};

    setMarker(pickupMarker, o.lat, o.lng, true);
    setMarker(destMarker, d.lat, d.lng, showDest);
    setMarker(driverMarker, loc?.lat ?? null, loc?.lng ?? null, !!loc);

    // Cámara: primer encuadre o cuando cambia el modo (pickup vs dest)
    let fitMode = null;
    if (loc && o.lat != null && o.lng != null) {
      if (showDest && d.lat != null && d.lng != null) fitMode = 'dest';
      else fitMode = 'pickup';
    }

    if (!didInitialFit && fitMode) {
      if (fitMode === 'dest') fitIfPossible([[loc.lat, loc.lng], [d.lat, d.lng]]);
      else fitIfPossible([[loc.lat, loc.lng], [o.lat, o.lng]]);
      didInitialFit = true;
      lastFitMode = fitMode;
    } else if (fitMode && fitMode !== lastFitMode) {
      if (fitMode === 'dest') fitIfPossible([[loc.lat, loc.lng], [d.lat, d.lng]]);
      else fitIfPossible([[loc.lat, loc.lng], [o.lat, o.lng]]);
      lastFitMode = fitMode;
    }

    document.getElementById('lastTs').textContent = ts || '—';
  }

  // ===== Stop polling (centralizado) =====
  let polling = true;
  let tickTimer = null;
  const POLL_MS = 2500;

  function stopPolling(reason = 'ENDED') {
    polling = false;
    if (tickTimer) {
      clearTimeout(tickTimer);
      tickTimer = null;
    }

    // UI final
    const lower = (reason || '').toLowerCase();
    const pill = pillTextFor(lower);
    showEndedUI(pill);
  }

  // Primer paint
  render(INITIAL, (new Date()).toLocaleString());
  ensureFixedRoute(INITIAL);

  async function tick() {
    if (!polling) return;

    try {
      const res = await fetch(STATE_URL, { headers: { 'Accept': 'application/json' }});

      // 410 = ended/expired/revoked (tu backend debería usarlo)
      if (res.status === 410) {
        let code = 'ENDED';
        try {
          const j = await res.json();
          code = (j && j.code) ? String(j.code) : 'ENDED';
        } catch (_) {}
        stopPolling(code);
        return;
      }

      if (!res.ok) {
        showErrorUI();
        throw new Error('HTTP ' + res.status);
      }

      hideErrorUI();

      const json = await res.json();

      // Si backend manda ok:false, también paramos
      if (!json || json.ok === false) {
        const code = json?.code ? String(json.code) : 'ENDED';
        stopPolling(code);
        return;
      }

      render(json, json.ts);

      // Si backend marca ended, paramos
      const st = (json.ride && json.ride.status) ? String(json.ride.status).toLowerCase() : '';
      if (json.ended === true || ['finished','canceled'].includes(st)) {
        stopPolling(st || 'ENDED');
        return;
      }
    } catch (e) {
      // Silencioso: mantenemos el último frame; mostramos warning si aplica
      showErrorUI();
    }

    tickTimer = setTimeout(tick, POLL_MS);
  }

  tick();
</script>

</body>
</html>
