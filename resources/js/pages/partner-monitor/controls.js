// resources/js/partner-monitor/controls.js
import L from 'leaflet';

export function bindControls(ctx) {

  function toggleFullscreen() {
    document.body.classList.toggle('pm-map-fullscreen');
    // si tu createMapAndLayers expone ctx.invalidate, ok; si no, usa ctx.map.invalidateSize()
    if (typeof ctx.invalidate === 'function') ctx.invalidate();
    else ctx.map?.invalidateSize?.();
  }

  function setLayerVisible(layer, visible) {
    if (!ctx.map || !layer) return;
    if (visible) {
      if (!ctx.map.hasLayer(layer)) layer.addTo(ctx.map);
    } else {
      if (ctx.map.hasLayer(layer)) ctx.map.removeLayer(layer);
    }
  }

  function addFloatingControls() {
    const ctl = L.control({ position: 'topleft' });
    ctl.onAdd = function () {
      const div = L.DomUtil.create('div', 'pm-float-ctl');
      div.innerHTML = `
        <button type="button" data-act="fit" title="Ver todos">&#10530;</button>
        <button type="button" data-act="center" title="Centrar">&#9673;</button>
        <button type="button" data-act="sectors" class="pm-on" title="Sectores">S</button>
        <button type="button" data-act="stands" class="pm-on" title="Bases">B</button>
        <button type="button" data-act="full" title="Pantalla completa">&#9974;</button>
      `;
      L.DomEvent.disableClickPropagation(div);
      L.DomEvent.disableScrollPropagation(div);
      return div;
    };
    ctl.addTo(ctx.map);

    const root = document.querySelector('.pm-float-ctl');
    if (!root) return;

    root.addEventListener('click', async (ev) => {
      const btn = ev.target?.closest('button');
      if (!btn) return;
      const act = btn.getAttribute('data-act');

      if (act === 'full') toggleFullscreen();
      if (act === 'fit') ctx.fitDrivers?.();
      if (act === 'center') ctx.map?.setView?.(ctx.initialCenter, Math.max(ctx.map.getZoom(), ctx.initialZoom));

      if (act === 'sectors') {
        ctx.showSectors = !ctx.showSectors;
        btn.classList.toggle('pm-on', ctx.showSectors);
        setLayerVisible(ctx.layerSectors, ctx.showSectors);
        if (ctx.showSectors && ctx.layerSectors && !ctx.layerSectors.getLayers().length) {
          await ctx.loadSectores?.();
        }
      }

      if (act === 'stands') {
        ctx.showStands = !ctx.showStands;
        btn.classList.toggle('pm-on', ctx.showStands);
        setLayerVisible(ctx.layerStands, ctx.showStands);
        if (ctx.showStands && ctx.layerStands && !ctx.layerStands.getLayers().length) {
          await ctx.loadStands?.();
        }
      }
    });
  }

  // =========================
  // HUD (counters + camera)
  // =========================
  function mountHud() {
    const ctl = L.control({ position: 'topleft' });

    let elRoot = null;

    function applyFilterUi(active) {
      if (!elRoot) return;
      const a = String(active || 'all');
      elRoot.querySelectorAll('[data-filter]').forEach(b => {
        const bf = String(b.getAttribute('data-filter') || 'all');
        b.classList.toggle('is-on', bf === a);
      });
    }

    function computeFollowLabel() {
      const isOn = !!ctx.isFollowing?.();
      const id = ctx.getFollowDriverId?.();
      if (!isOn || !id) return { text: 'Cámara: Libre', cls: 'pm-cam-free' };
      const entry = ctx.driverPins?.get?.(Number(id));
      const row = entry?.marker?.__pmRow || {};
      const eco = row.vehicle_economico ?? row.economico ?? row.code ?? `#${id}`;
      return { text: `Cámara: Siguiendo ${eco}`, cls: 'pm-cam-follow' };
    }

    function refreshFollowLine() {
      if (!elRoot) return;
      const line = elRoot.querySelector('.pm-cam');
      if (!line) return;
      const s = computeFollowLabel();
      line.textContent = s.text;
      line.classList.toggle('pm-cam-follow', s.cls === 'pm-cam-follow');
    }

    function toast(msg) {
      if (!elRoot) return;
      const t = elRoot.querySelector('.pm-hud-toast');
      if (!t) return;
      t.textContent = msg;
      t.classList.add('show');
      clearTimeout(t.__tmr);
      t.__tmr = setTimeout(() => t.classList.remove('show'), 1400);
    }

    ctl.onAdd = function () {
      elRoot = L.DomUtil.create('div', 'pm-hud');
      elRoot.innerHTML = `
        <div class="pm-hud-row pm-hud-title">
          <div class="pm-hud-brand">Monitor</div>
          <button type="button" class="pm-hud-btn" data-act="follow">Seguir</button>
        </div>

        <div class="pm-cam pm-cam-free">Cámara: Libre</div>

        <div class="pm-hud-row pm-hud-stats">
          <div class="pm-stat"><div class="pm-stat-n" id="pmHudTotal">0</div><div class="pm-stat-l">Total</div></div>
          <div class="pm-stat"><div class="pm-stat-n" id="pmHudIdle">0</div><div class="pm-stat-l">Libres</div></div>
          <div class="pm-stat"><div class="pm-stat-n" id="pmHudBusy">0</div><div class="pm-stat-l">Ocup.</div></div>
          <div class="pm-stat"><div class="pm-stat-n" id="pmHudQueue">0</div><div class="pm-stat-l">Cola</div></div>
        </div>

        <div class="pm-hud-row pm-hud-filters">
          <button type="button" class="pm-chip is-on" data-filter="all">Todos</button>
          <button type="button" class="pm-chip" data-filter="idle">Libre</button>
          <button type="button" class="pm-chip" data-filter="busy">Ocup.</button>
          <button type="button" class="pm-chip" data-filter="queue">Cola</button>
        </div>

        <div class="pm-hud-toast"></div>
      `;

      L.DomEvent.disableClickPropagation(elRoot);
      L.DomEvent.disableScrollPropagation(elRoot);

      // filtros
      const filtersRoot = elRoot.querySelector('.pm-hud-filters');
      filtersRoot?.addEventListener('click', (ev) => {
        const btn = ev.target?.closest?.('button[data-filter]');
        if (!btn) return;

        ev.preventDefault();
        ev.stopPropagation();

        btn.blur();

        const f = String(btn.getAttribute('data-filter') || 'all');

        ctx.driverFilter = f;
        applyFilterUi(f);

        requestAnimationFrame(() => {
          ctx.applyDriverFilter?.(f);
        });
      });

      // follow toggle global (requiere selección)
      elRoot.querySelector('[data-act="follow"]')?.addEventListener('click', () => {
        if (ctx.isFollowing?.()) {
          ctx.followOff?.();
          refreshFollowLine();
          toast('Seguimiento: OFF');
          return;
        }
        const id = Number(ctx.selectedDriverId || 0);
        if (!id) {
          toast('Selecciona un taxi primero');
          return;
        }
        ctx.followOn?.(id);
        ctx.focusDriverById?.(id);
        refreshFollowLine();
        toast('Seguimiento: ON');
      });

      refreshFollowLine();
      applyFilterUi(ctx.driverFilter || 'all');

      return elRoot;
    };

    ctl.addTo(ctx.map);

    return {
      setDriverStats(stats) {
        if (!elRoot) return;
        const s = stats || {};
        const set = (id, v) => {
          const el = elRoot.querySelector(id);
          if (el) el.textContent = String(v ?? 0);
        };
        set('#pmHudTotal', s.total);
        set('#pmHudIdle', s.idle);
        set('#pmHudBusy', s.busy);
        set('#pmHudQueue', s.queue);

        refreshFollowLine();
      },
      refreshFollowLine,
      toast,
    };
  }

  // =========================
  // Selection Card (driver)
  // =========================
  function mountSelectionCard() {
    const host = ctx.map.getContainer();
    const card = document.createElement('div');
    card.className = 'pm-selcard';
    card.innerHTML = `
      <div class="pm-selcard-head">
        <div class="pm-selcard-title">—</div>
        <button type="button" class="pm-selcard-x" title="Cerrar">&times;</button>
      </div>
      <div class="pm-selcard-body"></div>
      <div class="pm-selcard-actions">
        <button type="button" class="pm-btn" data-act="center">Centrar</button>
        <button type="button" class="pm-btn pm-btn-primary" data-act="follow">Seguir</button>
      </div>
    `;
    host.appendChild(card);

    let current = null;

    function hide() {
      card.classList.remove('show');
      current = null;
    }

    function refreshFollowButton() {
      const b = card.querySelector('[data-act="follow"]');
      if (!b || !current || current.kind !== 'driver') return;

      const on = !!ctx.isFollowing?.();
      const fid = Number(ctx.getFollowDriverId?.() || 0);
      const isThis = on && fid && fid === Number(current.driverId);

      b.textContent = isThis ? 'Seguir: ON' : 'Seguir';
      b.classList.toggle('is-on', isThis);
    }

    function show(payload) {
      current = payload;
      card.querySelector('.pm-selcard-title').textContent = payload?.title || '—';
      card.querySelector('.pm-selcard-body').innerHTML = payload?.html || '';
      card.classList.add('show');

      if (payload?.kind === 'driver') {
        ctx.selectedDriverId = Number(payload.driverId || 0);
      }

      refreshFollowButton();
      ctx.hud?.refreshFollowLine?.();
    }

    card.querySelector('.pm-selcard-x')?.addEventListener('click', hide);

    card.querySelector('[data-act="center"]')?.addEventListener('click', () => {
      if (!current) return;
      if (current.kind === 'driver' && current.driverId) {
        ctx.focusDriverById?.(Number(current.driverId));
      }
    });

    card.querySelector('[data-act="follow"]')?.addEventListener('click', () => {
      if (!current || current.kind !== 'driver') return;
      const id = Number(current.driverId || 0);
      if (!id) return;

      const on = !!ctx.isFollowing?.();
      const fid = Number(ctx.getFollowDriverId?.() || 0);
      const isThis = on && fid && fid === id;

      if (isThis) {
        ctx.followOff?.();
        ctx.hud?.toast?.('Seguimiento: OFF');
      } else {
        ctx.followOn?.(id);
        ctx.focusDriverById?.(id);
        ctx.hud?.toast?.('Seguimiento: ON');
      }

      refreshFollowButton();
      ctx.hud?.refreshFollowLine?.();
    });

    return { show, hide, refreshFollowButton };
  }

  // =========================
  // Dock buttons (Blade)
  // =========================
  function bindDockButtons() {
    const btnCenter = document.getElementById('pmBtnCenter');
    const btnFollow = document.getElementById('pmBtnFollow');

    btnCenter?.addEventListener('click', (e) => {
      e.preventDefault();
      ctx.map.setView(ctx.initialCenter, Math.max(ctx.map.getZoom(), ctx.initialZoom));
    });

    btnFollow?.addEventListener('click', (e) => {
      e.preventDefault();

      if (ctx.isFollowing?.()) {
        ctx.followOff?.();
        ctx.sel?.refreshFollowButton?.();
        ctx.hud?.refreshFollowLine?.();
        ctx.hud?.toast?.('Seguimiento: OFF');
        return;
      }

      const id = Number(ctx.selectedDriverId || 0);
      if (!id) {
        ctx.hud?.toast?.('Selecciona un taxi primero');
        return;
      }

      ctx.followOn?.(id);
      ctx.focusDriverById?.(id);
      ctx.sel?.refreshFollowButton?.();
      ctx.hud?.refreshFollowLine?.();
      ctx.hud?.toast?.('Seguimiento: ON');
    });
  }


// =========================
// Ride focus layer (route + pins)
// =========================
function ensureRideFocusLayer() {
  if (!ctx.map) return null;
  if (!ctx.layerRideFocus) {
    ctx.layerRideFocus = L.featureGroup();
    ctx.layerRideFocus.addTo(ctx.map);
  }
  return ctx.layerRideFocus;
}

function clearRideFocus() {
  const g = ensureRideFocusLayer();
  if (g) g.clearLayers();
}
ctx.clearRideFocus = clearRideFocus;

// Google polyline decoder (precision 5 default; soporta 6 si lo mandas)
function decodePolyline(encoded, precision = 5) {
  if (!encoded || typeof encoded !== 'string') return [];
  const factor = Math.pow(10, precision);

  let index = 0;
  let lat = 0;
  let lng = 0;
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

    shift = 0;
    result = 0;
    do {
      b = encoded.charCodeAt(index++) - 63;
      result |= (b & 0x1f) << shift;
      shift += 5;
    } while (b >= 0x20);
    const dlng = (result & 1) ? ~(result >> 1) : (result >> 1);
    lng += dlng;

    coordinates.push([lat / factor, lng / factor]);
  }

  return coordinates;
}

function asNum(v) {
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}

function ridePointsFromRow(r) {
  // 1) polyline guardada
  const poly = r.route_polyline || r.polyline || r.route_overview_polyline || null;
  if (poly) {
    const prec = Number(r.polyline_precision ?? r.polyline_prec ?? 5) || 5;
    const pts = decodePolyline(String(poly), prec);
    if (pts.length >= 2) return pts;
  }

  // 2) si ya mandas puntos como JSON: [[lat,lng],...]
  const ptsJson = r.route_points || r.route_latlngs || null;
  if (ptsJson) {
    try {
      const arr = typeof ptsJson === 'string' ? JSON.parse(ptsJson) : ptsJson;
      if (Array.isArray(arr) && arr.length >= 2) return arr;
    } catch (_) {}
  }

  return [];
}

function addPin(lat, lng, kind) {
  // kind: 'origin' | 'stop' | 'dest'
  const color = kind === 'origin' ? '#2d7' : (kind === 'dest' ? '#f55' : '#fa0');
  return L.circleMarker([lat, lng], {
    radius: 7,
    weight: 2,
    color,
    fillColor: color,
    fillOpacity: 0.9
  });
}

function showRideOnMap(r, { fit = true } = {}) {
  const g = ensureRideFocusLayer();
  if (!g) return;

  g.clearLayers();

  const oLat = asNum(r.origin_lat ?? r.pickup_lat);
  const oLng = asNum(r.origin_lng ?? r.pickup_lng);
  const dLat = asNum(r.dest_lat ?? r.drop_lat);
  const dLng = asNum(r.dest_lng ?? r.drop_lng);

  // stops opcionales (ajusta nombres si usas stop1/stop2)
  const s1Lat = asNum(r.stop1_lat ?? r.s1_lat);
  const s1Lng = asNum(r.stop1_lng ?? r.s1_lng);
  const s2Lat = asNum(r.stop2_lat ?? r.s2_lat);
  const s2Lng = asNum(r.stop2_lng ?? r.s2_lng);

  // markers
  if (oLat != null && oLng != null) g.addLayer(addPin(oLat, oLng, 'origin'));
  if (s1Lat != null && s1Lng != null) g.addLayer(addPin(s1Lat, s1Lng, 'stop'));
  if (s2Lat != null && s2Lng != null) g.addLayer(addPin(s2Lat, s2Lng, 'stop'));
  if (dLat != null && dLng != null) g.addLayer(addPin(dLat, dLng, 'dest'));

  // route polyline
  const pts = ridePointsFromRow(r);
  if (pts.length >= 2) {
    const pl = L.polyline(pts, { weight: 5, opacity: 0.85 });
    g.addLayer(pl);
  }

  // encuadre
  if (fit) {
    const bounds = g.getBounds?.();
    if (bounds && bounds.isValid && bounds.isValid()) {
      ctx.map.fitBounds(bounds, { padding: [42, 42] });
    } else if (oLat != null && oLng != null) {
      ctx.map.setView([oLat, oLng], Math.max(ctx.map.getZoom(), 16));
    }
  }
}

ctx.showRideOnMap = showRideOnMap;



// =========================
// Ride Peek Card (floating)
// =========================
function mountRidePeekCard() {
  const host = ctx.map?.getContainer?.();
  if (!host) return null;

  const el = document.createElement('div');
  el.className = 'pm-ridepeek';
  el.innerHTML = `
    <div class="pm-rp-head">
      <div class="pm-rp-title">Ride —</div>
      <div class="pm-rp-actions">
        <button type="button" class="pm-mini" data-act="center">Centrar</button>
        <button type="button" class="pm-mini" data-act="clear">Limpiar</button>
        <button type="button" class="pm-mini" data-act="close">×</button>
      </div>
    </div>

    <div class="pm-rp-body">
      <div class="pm-rp-left">
        <div class="pm-rp-av" data-role="av"></div>
      </div>

      <div class="pm-rp-main">
        <div class="pm-rp-line1">
          <div class="pm-rp-pax" data-role="pax">Pasajero</div>
          <div class="pm-rp-badges" data-role="badges"></div>
        </div>
        <div class="pm-rp-line2" data-role="meta"></div>
        <div class="pm-rp-line3" data-role="route"></div>
        <div class="pm-rp-timeline" data-role="tl"></div>
      </div>
    </div>
  `;

  // evita clicks al mapa
  L.DomEvent.disableClickPropagation(el);
  L.DomEvent.disableScrollPropagation(el);

  host.appendChild(el);

  let openRideId = null;
  let openRide = null;

  const esc = (s) => String(s ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

  const fmtMoney = (r) => {
    const a = r.agreed_amount ?? r.total_amount ?? r.quoted_amount ?? null;
    if (a == null) return '—';
    return `$${Number(a).toFixed(2)}`;
  };

  const statusBadge = (status) => {
    const s = String(status || '').toLowerCase();
    const cls =
      s === 'accepted' ? 'accepted' :
      s === 'arrived' ? 'arrived' :
      (s === 'on_board' || s === 'onboard') ? 'on_board' :
      s === 'en_route' ? 'en_route' :
      s === 'requested' ? 'requested' :
      s === 'offered' ? 'offered' :
      s === 'scheduled' ? 'scheduled' :
      s === 'finished' ? 'finished' :
      s === 'canceled' ? 'canceled' : 'neutral';

    return `<span class="pm-badge pm-badge-${cls}">${esc(s || '—')}</span>`;
  };

  const pickAvatar = (r) =>
    r.passenger_avatar_url || r.avatar_url || r.pax_avatar_url || null;

  const initials = (name) => {
    const n = String(name || '').trim();
    if (!n) return '—';
    const parts = n.split(/\s+/).slice(0, 2);
    return parts.map(p => p[0]?.toUpperCase() || '').join('') || '—';
  };

  const timelineSteps = (r) => {
    // Ajusta keys si en tu rides table se llaman distinto.
    // Regla: si hay ts -> se marca hecho; si status coincide -> activo.
    const steps = [
      { key: 'requested', label: 'Pedido', ts: r.requested_at || r.created_at },
      { key: 'offered',   label: 'Oferta', ts: r.offered_at || r.last_offer_sent_at || r.offer_sent_at },
      { key: 'accepted',  label: 'Asignado', ts: r.accepted_at },
      { key: 'arrived',   label: 'Llegó', ts: r.arrived_at },
      { key: 'on_board',  label: 'Abordó', ts: r.on_board_at || r.boarded_at },
      { key: 'finished',  label: 'Final', ts: r.finished_at || r.canceled_at },
    ];

    const status = String(r.status || '').toLowerCase();
    let activeKey = status;

    // normaliza variantes
    if (activeKey === 'onboard') activeKey = 'on_board';
    if (activeKey === 'completed') activeKey = 'finished';
    if (activeKey === 'cancelled') activeKey = 'canceled';

    // si cancelado, activa "finished" como final pero badge lo muestra
    if (activeKey === 'canceled') activeKey = 'finished';

    const idx = steps.findIndex(s => s.key === activeKey);
    const activeIdx = idx >= 0 ? idx : 0;

    return { steps, activeIdx };
  };

  function render(r) {
    const rideId = Number(r.ride_id ?? r.id ?? 0) || null;
    const paxName = r.passenger_name || r.pax_name || 'Pasajero';
    const paxPhone = r.passenger_phone || r.pax_phone || '';
    const driverName = r.driver_name || (r.driver_id ? `Driver #${r.driver_id}` : '—');
    const eco = r.vehicle_economico || r.economico || '';
    const plate = r.vehicle_plate || r.plate || r.placa || '';
    const veh = (eco || plate) ? `${eco}${eco && plate ? ' · ' : ''}${plate}` : '—';

    el.querySelector('.pm-rp-title').textContent = rideId ? `Ride #${rideId}` : 'Ride —';

    // avatar
    const av = el.querySelector('[data-role="av"]');
    const avUrl = pickAvatar(r);
    if (av) {
      if (avUrl) {
        av.innerHTML = `<img alt="" loading="lazy" src="${esc(avUrl)}" />`;
        av.classList.add('is-img');
      } else {
        av.innerHTML = `<div class="pm-rp-av-fallback">${esc(initials(paxName))}</div>`;
        av.classList.remove('is-img');
      }
    }

    // line1 pax + badges
    const paxEl = el.querySelector('[data-role="pax"]');
    if (paxEl) paxEl.textContent = paxName;

    const badgesEl = el.querySelector('[data-role="badges"]');
    if (badgesEl) {
      badgesEl.innerHTML = `
        ${statusBadge(r.status)}
        <span class="pm-rp-amt">${esc(fmtMoney(r))}</span>
      `;
    }

    // meta
    const metaEl = el.querySelector('[data-role="meta"]');
    if (metaEl) {
      metaEl.innerHTML = `
        <span class="pm-rp-metaitem"><b>Tel:</b> ${esc(paxPhone || '—')}</span>
        <span class="pm-rp-metaitem"><b>Driver:</b> ${esc(driverName)}</span>
        <span class="pm-rp-metaitem"><b>Veh:</b> ${esc(veh)}</span>
      `;
    }

    // route labels
    const routeEl = el.querySelector('[data-role="route"]');
    if (routeEl) {
      const o = r.origin_label || r.pickup_label || r.pickup_address || 'Origen';
      const d = r.dest_label || r.drop_label || r.drop_address || 'Destino';
      const s1 = r.stop1_label || r.s1_label || '';
      const s2 = r.stop2_label || r.s2_label || '';
      routeEl.innerHTML = `
        <span class="pm-rp-route"><b>O:</b> ${esc(o)}</span>
        ${s1 ? `<span class="pm-rp-route"><b>S1:</b> ${esc(s1)}</span>` : ''}
        ${s2 ? `<span class="pm-rp-route"><b>S2:</b> ${esc(s2)}</span>` : ''}
        <span class="pm-rp-route"><b>D:</b> ${esc(d)}</span>
      `;
    }

    // timeline
    const tlEl = el.querySelector('[data-role="tl"]');
    if (tlEl) {
      const { steps, activeIdx } = timelineSteps(r);
      tlEl.innerHTML = `
        <div class="pm-tl">
          ${steps.map((s, i) => {
            const done = !!s.ts && i < activeIdx;
            const active = i === activeIdx;
            const cls = `${done ? 'is-done' : ''} ${active ? 'is-active' : ''}`.trim();
            return `
              <div class="pm-tl-step ${cls}">
                <div class="pm-tl-dot"></div>
                <div class="pm-tl-lab">${esc(s.label)}</div>
              </div>
            `;
          }).join('')}
        </div>
      `;
    }
  }

  function show(r) {
    openRideId = Number(r.ride_id ?? r.id ?? 0) || null;
    openRide = r || null;

    if (openRide) {
      render(openRide);
      el.classList.add('show');

      // dibuja ruta
      ctx.showRideOnMap?.(openRide, { fit: true });
    }
  }

  function hide({ clear = false } = {}) {
    el.classList.remove('show');
    openRideId = null;
    openRide = null;
    if (clear) ctx.clearRideFocus?.();
  }

  // acciones
  el.querySelector('[data-act="close"]')?.addEventListener('click', () => hide({ clear: false }));

  el.querySelector('[data-act="clear"]')?.addEventListener('click', () => {
    ctx.clearRideFocus?.();
    hide({ clear: false });
  });

  el.querySelector('[data-act="center"]')?.addEventListener('click', () => {
    if (openRide) ctx.showRideOnMap?.(openRide, { fit: true });
  });

  // sync para polling (si el ride está abierto y cambia)
  function syncFromRides(ridesArr) {
    if (!openRideId || !Array.isArray(ridesArr)) return;
    const found = ridesArr.find(x => Number(x.ride_id ?? x.id ?? 0) === openRideId);
    if (found) {
      openRide = found;
      render(openRide);
    }
  }

  return { show, hide, syncFromRides, isOpen: () => !!openRideId };
}


  // =========================
  // Sidebar / Monitor Panel (improved)
  // =========================
  function mountMonitorPanel() {
    const ctl = L.control({ position: 'topright' });

    let elRoot = null;
    let isOpen = true;

    let _drivers = [];
    let _rides = [];
    let _stands = [];
    let _standsById = new Map();

    let _search = '';
    let _serverTime = null;

    const esc = (s) => String(s ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    const normDt = (s) => {
      if (!s) return null;
      const iso = String(s).includes('T') ? String(s) : String(s).replace(' ', 'T');
      const d = new Date(iso);
      return isNaN(d.getTime()) ? null : d;
    };

    const ago = (dtStr) => {
      const d = normDt(dtStr);
      if (!d) return '';
      const now = _serverTime ? (normDt(_serverTime) || new Date()) : new Date();
      let sec = Math.floor((now.getTime() - d.getTime()) / 1000);
      if (sec < 0) sec = 0;
      if (sec < 60) return `${sec}s`;
      const min = Math.floor(sec / 60);
      if (min < 60) return `${min}m`;
      const hr = Math.floor(min / 60);
      return `${hr}h`;
    };

    const fmtEco = (row) => {
      const eco = row.vehicle_economico ?? row.economico ?? row.code ?? null;
      const id = Number(row.driver_id ?? row.id ?? 0);
      return (eco && String(eco).trim()) ? String(eco).trim() : (id ? `#${id}` : '—');
    };

    const driverKey = (row) => {
      const eco = row.vehicle_economico ?? row.economico ?? row.code ?? '';
      const plate = row.vehicle_plate ?? row.plate ?? row.placa ?? '';
      const name = row.driver_name ?? row.name ?? '';
      return `${eco} ${plate} ${name} #${row.driver_id ?? row.id ?? ''}`.toLowerCase();
    };

    const getStandName = (standId) => {
      const s = _standsById.get(Number(standId));
      return s?.name || (standId ? `Base #${standId}` : '');
    };

    const pickAvatarUrl = (row) => row.vehicle_photo_url || row.driver_photo_url || null;

    const initials = (row) => {
      const eco = fmtEco(row);
      if (eco && eco !== '—') return eco.length > 4 ? eco.slice(-4) : eco;
      const n = String(row.name || '').trim();
      if (!n) return '—';
      const parts = n.split(/\s+/).slice(0, 2);
      return parts.map(p => p[0]?.toUpperCase() || '').join('') || '—';
    };

    const statusMeta = (row) => {
      const rs = String(row.ride_status || '').toLowerCase();
      const ds = String(row.driver_status || row.status || '').toLowerCase();
      const shiftOpen = Number(row.shift_open ?? 1) === 1;
      const fresh = Number(row.is_fresh ?? 1) === 1;

      if (!shiftOpen) return { text: 'Fuera turno', cls: 'off' };
      if (!fresh) return { text: 'Sin señal', cls: 'stale' };

      if (row.stand_id || row.queue_pos) return { text: 'Cola', cls: 'queue' };

      if (rs === 'on_board') return { text: 'Abordado', cls: 'on_board' };
      if (rs === 'en_route') return { text: 'En ruta', cls: 'en_route' };
      if (rs === 'arrived')  return { text: 'Llegó', cls: 'arrived' };
      if (rs === 'accepted') return { text: 'Asignado', cls: 'accepted' };
      if (rs === 'offered')  return { text: 'Oferta', cls: 'offered' };
      if (rs === 'requested') return { text: 'Pedido', cls: 'requested' };
      if (rs === 'scheduled') return { text: 'Prog.', cls: 'scheduled' };

      if (ds === 'busy' || ds === 'on_ride') return { text: 'Ocup.', cls: 'busy' };
      return { text: 'Libre', cls: 'idle' };
    };

    const setOpen = (open) => {
      isOpen = !!open;
      if (!elRoot) return;
      elRoot.classList.toggle('is-open', isOpen);
      elRoot.classList.toggle('is-closed', !isOpen);
    };

    const renderDrivers = () => {
      if (!elRoot) return;
      const list = elRoot.querySelector('[data-list="drivers"]');
      const cnt  = elRoot.querySelector('[data-count="drivers"]');
      if (!list) return;

      const q = _search.trim().toLowerCase();
      const items = _drivers
        .filter(d => !q || driverKey(d).includes(q))
        .slice()
        .sort((a,b) => {
          const aq = (a.stand_id || a.queue_pos) ? 1 : 0;
          const bq = (b.stand_id || b.queue_pos) ? 1 : 0;
          if (bq !== aq) return bq - aq;

          const af = Number(a.is_fresh ?? 0);
          const bf = Number(b.is_fresh ?? 0);
          if (bf !== af) return bf - af;

          return fmtEco(a).toLowerCase().localeCompare(fmtEco(b).toLowerCase(), 'es');
        });

      if (cnt) cnt.textContent = String(items.length);

      const frag = document.createDocumentFragment();

      for (const row of items) {
        const id = Number(row.driver_id ?? row.id ?? 0);
        if (!id) continue;

        const eco = fmtEco(row);
        const plate = String(row.vehicle_plate ?? row.plate ?? row.placa ?? '').trim();
        const name = String(row.driver_name ?? row.name ?? '').trim();

        const st = statusMeta(row);
        const queuePos = row.queue_pos ?? row.queue_position ?? null;
        const standName = row.stand_id ? getStandName(row.stand_id) : null;

        const avatarUrl = pickAvatarUrl(row);
        const timeAgo = ago(row.updated_at);
        const isFresh = Number(row.is_fresh ?? 0) === 1;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pm-row';
        btn.setAttribute('data-driver-id', String(id));
        if (Number(ctx.selectedDriverId || 0) === id) btn.classList.add('is-selected');

        const avHtml = avatarUrl
          ? `<div class="pm-av pm-av-img"><img alt="" loading="lazy" src="${esc(avatarUrl)}"></div>`
          : `<div class="pm-av pm-av-fallback">${esc(initials(row))}</div>`;

        const rightHtml = `
          <div class="pm-row-main">
            <div class="pm-row-top">
              <div class="pm-row-eco">${esc(eco)}</div>
              <div class="pm-badge pm-badge-${esc(st.cls)}">${esc(st.text)}</div>
            </div>

            <div class="pm-row-sub">
              ${plate ? `<span>Placa: ${esc(plate)}</span>` : `<span class="muted">Sin placa</span>`}
              ${name ? `<span class="dot">·</span><span>${esc(name)}</span>` : ``}
            </div>

            <div class="pm-row-sub pm-row-sub2">
              <span class="pm-dot ${isFresh ? 'on' : 'off'}"></span>
              ${timeAgo ? `<span class="pm-time">hace ${esc(timeAgo)}</span>` : `<span class="pm-time muted">—</span>`}
              ${standName ? `<span class="dot">·</span><span class="pm-stand">${esc(standName)}</span>` : ``}
            </div>
          </div>
        `;

        const qpHtml = queuePos ? `<div class="pm-qp">#${esc(queuePos)}</div>` : ``;

        btn.innerHTML = `${avHtml}${rightHtml}${qpHtml}`;

        const img = btn.querySelector('.pm-av-img img');
        if (img) {
          img.addEventListener('error', () => {
            const av = btn.querySelector('.pm-av-img');
            if (av) {
              av.classList.remove('pm-av-img');
              av.classList.add('pm-av-fallback');
              av.innerHTML = esc(initials(row));
            }
          });
        }

        btn.addEventListener('click', () => {
          ctx.selectedDriverId = id;
          elRoot.querySelectorAll('.pm-row.is-selected').forEach(x => x.classList.remove('is-selected'));
          btn.classList.add('is-selected');
          ctx.focusDriverById?.(id);
        });

        frag.appendChild(btn);
      }

      list.innerHTML = '';
      list.appendChild(frag);
    };

    const renderStands = () => {
      if (!elRoot) return;
      const list = elRoot.querySelector('[data-list="stands"]');
      const cnt  = elRoot.querySelector('[data-count="stands"]');
      if (!list) return;

      const items = (_stands || []).slice().sort((a,b) => {
        const an = String(a.name || '').toLowerCase();
        const bn = String(b.name || '').toLowerCase();
        return an.localeCompare(bn, 'es');
      });

      if (cnt) cnt.textContent = String(items.length);

      const frag = document.createDocumentFragment();

      for (const s of items) {
        const standId = Number(s.stand_id ?? s.id ?? 0);
        if (!standId) continue;

        const name = s.name || `Base #${standId}`;
        const qc = Number(s.queue_count ?? 0);

        const qTop = _drivers
          .filter(d => Number(d.stand_id ?? 0) === standId && Number(d.queue_pos ?? 0) > 0)
          .slice()
          .sort((a,b) => Number(a.queue_pos ?? 9999) - Number(b.queue_pos ?? 9999))
          .slice(0, 3)
          .map(d => `${fmtEco(d)}#${Number(d.queue_pos ?? 0)}`)
          .join(' · ');

        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'pm-stand-row';
        row.innerHTML = `
          <div class="pm-stand-main">
            <div class="pm-stand-name">${esc(name)}</div>
            <div class="pm-stand-sub">
              ${qTop ? `<span class="muted">${esc(qTop)}</span>` : `<span class="muted">—</span>`}
            </div>
          </div>
          <div class="pm-pill">${esc(qc)}</div>
        `;

        row.addEventListener('click', () => {
          if (ctx.focusStandById) {
            ctx.focusStandById(standId);
            return;
          }
          const lat = s.lat != null ? Number(s.lat) : null;
          const lng = s.lng != null ? Number(s.lng) : null;
          if (lat != null && lng != null && ctx.map) {
            ctx.map.setView([lat, lng], Math.max(ctx.map.getZoom(), 16));
          }
        });

        frag.appendChild(row);
      }

      list.innerHTML = '';
      list.appendChild(frag);
    };

    const renderRides = () => {
      if (!elRoot) return;
      const list = elRoot.querySelector('[data-list="rides"]');
      const cnt  = elRoot.querySelector('[data-count="rides"]');
      if (!list) return;

      const items = (_rides || []).slice().sort(
        (a,b) => Number(b.ride_id ?? b.id ?? 0) - Number(a.ride_id ?? a.id ?? 0)
      );
      if (cnt) cnt.textContent = String(items.length);

      if (!items.length) {
        list.innerHTML = `<div class="pm-muted">Sin rides activos</div>`;
        return;
      }

      const frag = document.createDocumentFragment();

      for (const r of items) {
        const rideId = Number(r.ride_id ?? r.id ?? 0);
        if (!rideId) continue;

        const status = String(r.status || '').toLowerCase();
        const pax = r.passenger_name ? String(r.passenger_name) : 'Pasajero';
        const amt = (r.quoted_amount != null) ? `$${Number(r.quoted_amount).toFixed(2)}` : '—';
        const tAgo = ago(r.updated_at);

        const card = document.createElement('div');
        card.className = 'pm-ride-card';
        card.innerHTML = `
          <div class="pm-ride-top">
            <div class="pm-ride-title">Ride #${esc(rideId)} <span class="pm-ride-status">${esc(status || '—')}</span></div>
            <div class="pm-ride-amt">${esc(amt)}</div>
          </div>
          <div class="pm-ride-sub">
            <span>${esc(pax)}</span>
            ${tAgo ? `<span class="dot">·</span><span class="muted">hace ${esc(tAgo)}</span>` : ``}
          </div>
          <div class="pm-ride-actions">
            <button type="button" class="pm-mini" data-act="view">Ver</button>
          </div>
        `;

       card.querySelector('[data-act="view"]')?.addEventListener('click', () => {
  // 1) abre card flotante (timeline + avatar + resumen)
  ctx.ridePeek?.show?.(r);

  // 2) si también tienes hook existente, lo puedes mantener
  ctx.focusRideById?.(rideId);

  // fallback si no existe focusRideById (ya lo tienes)
  if (!ctx.focusRideById && ctx.map) {
    const pts = [];
    if (r.pickup_lat != null && r.pickup_lng != null) pts.push([Number(r.pickup_lat), Number(r.pickup_lng)]);
    if (r.drop_lat != null && r.drop_lng != null) pts.push([Number(r.drop_lat), Number(r.drop_lng)]);
    if (pts.length === 1) ctx.map.setView(pts[0], Math.max(ctx.map.getZoom(), 16));
    if (pts.length === 2) ctx.map.fitBounds(pts, { padding: [24, 24] });
  }
});


        frag.appendChild(card);
      }

      list.innerHTML = '';
      list.appendChild(frag);
    };
ctl.onAdd = function () {
  // ✅ arranca colapsado
  elRoot = L.DomUtil.create('div', 'pm-panel is-closed');

  elRoot.innerHTML = `
    <div class="pm-panel-head">
      <div class="pm-head-title"></div>
      <div class="pm-head-actions">
        <button type="button" class="pm-mini" data-act="pm-toggle">Mostrar</button>
      </div>
    </div>

    <div class="pm-panel-body">
      <details class="pm-sec" open>
        <summary class="pm-sec-title">
          <span>Conductores</span>
          <span class="pm-sec-count" data-count="drivers">0</span>
        </summary>

        <div class="pm-sec-tools">
          <input class="pm-search" type="search" placeholder="Buscar (económico, placa, nombre)" />
        </div>

        <div class="pm-list" data-list="drivers"></div>
      </details>

      <details class="pm-sec">
        <summary class="pm-sec-title">
          <span>Bases</span>
          <span class="pm-sec-count" data-count="stands">0</span>
        </summary>
        <div class="pm-list" data-list="stands"></div>
      </details>

      <details class="pm-sec">
        <summary class="pm-sec-title">
          <span>Rides activos</span>
          <span class="pm-sec-count" data-count="rides">0</span>
        </summary>
        <div class="pm-list" data-list="rides"></div>
      </details>
    </div>
  `;

      L.DomEvent.disableClickPropagation(elRoot);
      L.DomEvent.disableScrollPropagation(elRoot);

      elRoot.querySelector('[data-act="pm-toggle"]')?.addEventListener('click', () => {
        setOpen(!isOpen);
        const btn = elRoot.querySelector('[data-act="pm-toggle"]');
        if (btn) btn.textContent = isOpen ? 'Ocultar' : 'Monitor List';
      });

      elRoot.querySelector('.pm-search')?.addEventListener('input', (ev) => {
        _search = String(ev.target?.value || '');
        renderDrivers();
      });

      setOpen(true);
      return elRoot;
    };

    // ESTE era el crash: ahora ctx viene del closure, no por parámetro
    ctl.addTo(ctx.map);

    return {
      setServerTime(ts) {
        _serverTime = ts || null;
      },
      setDrivers(driversArr) {
        _drivers = Array.isArray(driversArr) ? driversArr : [];
        renderDrivers();
        renderStands();
      },
      setStands(standsArr) {
        _stands = Array.isArray(standsArr) ? standsArr : [];
        _standsById = new Map(_stands.map(s => [Number(s.stand_id ?? s.id ?? 0), s]));
        renderStands();
        renderDrivers();
      },
      setRides(ridesArr) {
        _rides = Array.isArray(ridesArr) ? ridesArr : [];
        renderRides();
      

      },
      open: () => setOpen(true),
      close: () => setOpen(false),
      toggle: () => setOpen(!isOpen),
      isOpen: () => isOpen,
    };
  }

  return {
    addFloatingControls,

    toggleFullscreen,
    setLayerVisible,

    mountHud,
    mountSelectionCard,
    bindDockButtons,
    mountMonitorPanel,
    mountRidePeekCard,
  };
}
