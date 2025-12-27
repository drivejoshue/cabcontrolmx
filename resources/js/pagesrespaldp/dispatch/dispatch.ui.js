/* resources/js/pages/dispatch/ui.js */
import { qs, jsonHeaders, escapeHtml } from './core.js';
import { renderRideCard, _isWaiting, _isActive, _isScheduled, _canonStatus, statusBadgeClass } from './rides.js';
import { highlightRideOnMap } from './assign.js';

let pollTimer = null;

export async function refreshDispatch() {
  try {
    const commonHeaders = jsonHeaders();
    const [a, b] = await Promise.all([
      fetch('/api/dispatch/active', { headers: jsonHeaders() }),
      fetch('/api/dispatch/drivers', { headers: jsonHeaders() }),
    ]);

    const data = a.ok
      ? await a.json()
      : (console.error('[active]', await a.text().catch(() => '')), {});

    const drivers = b.ok
      ? await b.json()
      : (console.error('[drivers]', await b.text().catch(() => '')), []);

    const rides = Array.isArray(data.rides) ? data.rides : [];
    const queues = Array.isArray(data.queues) ? data.queues : [];
    const driverList = Array.isArray(drivers) ? drivers : [];

    window._lastActiveRides = rides;
    window._ridesIndex = new Map(rides.map(r => [r.id, r]));
    window._lastDrivers = driverList;
    window._lastQueues = queues;

    renderQueues(queues);
    renderRightNowCards(rides);
    renderRightScheduledCards(rides);
    renderDockActive(rides);
    renderDrivers(driverList);
    await updateSuggestedRoutes(rides);
  } catch (e) {
    console.warn('refreshDispatch error', e);
  }
}

export function startPolling() {
  clearInterval(pollTimer);
  pollTimer = setInterval(refreshDispatch, 3000);
  refreshDispatch();
}

export function renderRightNowCards(rides) {
  const host = document.getElementById('panel-active');
  if (!host) return;
  const waiting = (rides || []).filter(_isWaiting);

  window._ridesIndex = new Map(waiting.map(r => [r.id, r]));

  host.innerHTML = waiting.length
    ? waiting.map(r => renderRideCard(r) || '').join('')
    : `<div class="text-muted px-2 py-2">Sin solicitudes.</div>`;

  host.querySelectorAll('[data-act="view"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const card = btn.closest('[data-ride-id]');
      const id = Number(card?.dataset?.rideId);
      const r = window._ridesIndex.get(id);
      if (r) highlightRideOnMap(r);
    });
  });

  const b = document.querySelector('#tab-active-cards .badge');
  if (b) b.textContent = String(waiting.length);
}

export function renderRightScheduledCards(rides) {
  const host = document.getElementById('panel-active-scheduled');
  if (!host) return;
  const scheduled = (rides || []).filter(_isScheduled);

  window._ridesIndex = new Map(scheduled.map(r => [r.id, r]));

  host.innerHTML = scheduled.length
    ? scheduled.map(r => renderRideCard(r) || '').join('')
    : `<div class="text-muted px-2 py-2">Sin programados.</div>`;

  host.querySelectorAll('[data-act="view"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const card = btn.closest('[data-ride-id]');
      const id = Number(card?.dataset?.rideId);
      const r = window._ridesIndex.get(id);
      if (r) highlightRideOnMap(r);
    });
  });

  const b = document.querySelector('#tab-active-grid .badge');
  if (b) b.textContent = String(scheduled.length);
}

export function renderQueues(queues) {
  const acc = document.getElementById('panel-queue');
  const compact = document.getElementById('panel-queue-compact');
  const badge = document.getElementById('badgeColas');
  const badgeFull = document.getElementById('badgeColasFull');

  const list = Array.isArray(queues) ? queues : [];

  if (badge) badge.textContent = list.length;
  if (badgeFull) badgeFull.textContent = list.length;

  if (acc) {
    acc.innerHTML = '';

    if (!list.length) {
      acc.innerHTML = `<div class="text-muted small p-2">Sin información de colas.</div>`;
    } else {
      (list || []).forEach((s, idx) => {
        const drivers = Array.isArray(s.drivers) ? s.drivers : [];
        const toEco = d => (d.eco || d.callsign || d.number || d.id || '?');

        const item = document.createElement('div');
        item.className = 'accordion-item';
        const standId = s.id || s.stand_id || idx;
        const headId = `qhead-${standId}`;
        const colId = `qcol-${standId}`;

        item.innerHTML = `
          <h2 class="accordion-header" id="${headId}">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#${colId}"
                    aria-expanded="false" aria-controls="${colId}">
              <div class="d-flex w-100 justify-content-between align-items-center">
                <div>
                  <div class="fw-semibold">${s.nombre || s.name || ('Base ' + (s.id || ''))}</div>
                  <div class="small text-muted">${
          [s.latitud || s.lat, s.longitud || s.lng].filter(Boolean).join(', ')
          }</div>
                </div>
                <span class="badge bg-secondary">${drivers.length}</span>
              </div>
            </button>
          </h2>
          <div id="${colId}" class="accordion-collapse collapse" data-bs-parent="#panel-queue">
            <div class="accordion-body">
              ${
          drivers.length
            ? `<div class="queue-eco-grid">${
              drivers.map(d => `<span class="eco">${toEco(d)}</span>`).join('')
              }</div>`
            : `<div class="text-muted">Sin unidades en cola.</div>`
          }
            </div>
          </div>
        `;
        acc.appendChild(item);
      });
    }
  }

  if (compact) {
    if (!list.length) {
      compact.innerHTML = `<span class="text-muted small">Sin información de colas.</span>`;
    } else {
      const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[m]));

      compact.innerHTML = list.map((s, idx) => {
        const standId = s.id || s.stand_id || idx;
        const name = s.nombre || s.name || ('Base ' + (s.id || ''));
        const count = Array.isArray(s.drivers) ? s.drivers.length : 0;

        return `
          <button type="button"
                  class="queues-compact-chip"
                  data-stand-id="${standId}">
            <strong>${esc(name)}</strong>
            <span class="badge bg-light text-secondary border ms-1">${count}</span>
          </button>
        `;
      }).join('');

      compact.querySelectorAll('.queues-compact-chip').forEach(btn => {
        btn.addEventListener('click', () => {
          const standId = btn.getAttribute('data-stand-id');
          if (window.openQueuesOverlay) window.openQueuesOverlay();
          if (!standId) return;

          const col = document.getElementById(`qcol-${standId}`);
          if (col) {
            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(col, { toggle: false });
            bsCollapse.show();
            col.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        });
      });
    }
  }
}

export function renderDockActive(rides) {
  const active = (rides || []).filter(_isActive);

  const b1 = document.getElementById('badgeActivos');
  const b2 = document.getElementById('badgeActivosDock');
  if (b1) b1.textContent = active.length;
  if (b2) b2.textContent = active.length;

  const host = document.getElementById('dock-active-table');
  if (!host) return;

  window._ridesIndex = new Map(active.map(r => [r.id, r]));

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  const fmtKm = (m) => Number.isFinite(m) ? (m / 1000).toFixed(1) : '–';
  const fmtMin = (s) => Number.isFinite(s) ? Math.round(s / 60) : '–';
  const parseStops = (r) => {
    if (Array.isArray(r.stops)) return r.stops;
    if (Array.isArray(r.stops_json)) return r.stops_json;
    if (typeof r.stops_json === 'string' && r.stops_json.trim() !== '') {
      try { const a = JSON.parse(r.stops_json); return Array.isArray(a) ? a : []; } catch { }
    }
    return [];
  };
  const short = (t, max = 60) => (t || '').length > max ? t.slice(0, max - 1) + '…' : (t || '');

  const rows = active.map(r => {
    const km = fmtKm(r.distance_m);
    const min = fmtMin(r.duration_s);
    const st = String(_canonStatus(r.status) || '').toUpperCase();
    const badge = statusBadgeClass(r.status);
    const dvr = r.driver_name || r.driver?.name || '—';
    const unit = r.vehicle_economico || r.vehicle_plate || r.vehicle_id || '—';
    const eta = (r.pickup_eta_min ?? '—');

    const stops = parseStops(r);
    const sc = stops.length || Number(r.stops_count || 0);

    const tipRaw = stops.map((s, i) => {
      const label = (s && typeof s.label === 'string' && s.label.trim() !== '')
        ? s.label.trim()
        : (Number.isFinite(+s.lat) && Number.isFinite(+s.lng) ? `${(+s.lat).toFixed(5)}, ${(+s.lng).toFixed(5)}` : '—');
      return `S${i + 1}: ${label}`;
    }).join('\n');
    const tipHtml = esc(tipRaw).replace(/\n/g, '&#10;');

    const stopsCell = sc
      ? `<span class="badge bg-secondary-subtle text-secondary" title="${tipHtml}">${sc}</span>`
      : '—';

    return `
      <tr class="cc-row" data-ride-id="${r.id}">
        <td class="cc-dock-unit">${esc(unit)}</td>
        <td class="cc-dock-driver">${esc(dvr)}</td>
        <td class="cc-dock-eta">${esc(String(eta))}</td>
        <td class="small" title="${esc(r.origin_label || '')}">${esc(short(r.origin_label, 50))}</td>
        <td class="small" title="${esc(r.dest_label || '')}">${esc(short(r.dest_label, 50))}</td>
        <td class="cc-dock-stops">${stopsCell}</td>
        <td class="cc-dock-num">${km}</td>
        <td class="cc-dock-num">${min}</td>
        <td><span class="badge ${badge}">${st}</span></td>
        <td class="text-end"><button class="btn btn-xs btn-outline-secondary" data-act="view">Ver</button></td>
      </tr>`;
  }).join('');

  host.innerHTML = `
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0 cc-dock-table">
        <thead>
          <tr>
            <th>Unidad</th>
            <th>Conductor</th>
            <th class="cc-dock-eta">ETA</th>
            <th>Origen</th>
            <th>Destino</th>
            <th class="cc-dock-stops">Paradas</th>
            <th class="cc-dock-num">Km</th>
            <th class="cc-dock-num">Min</th>
            <th>Estado</th>
            <th></th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;

  host.querySelectorAll('button[data-act="view"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const tr = btn.closest('tr');
      const id = Number(tr?.dataset?.rideId);
      const r = window._ridesIndex.get(id);
      if (r) {
        highlightRideOnMap(r);
        focusDriverOnMap(r);
      }
    });
  });

  host.querySelectorAll('tr.cc-row').forEach(tr => {
    tr.addEventListener('click', (e) => {
      if (e.target.closest('button')) return;
      const id = Number(tr.dataset.rideId);
      const r = window._ridesIndex.get(id);
      if (r) focusDriverOnMap(r);
    });
  });
}

// Inyectar estilos del dock
(function injectDockTableStyles() {
  if (window.__DOCK_TABLE_STYLES__) return; window.__DOCK_TABLE_STYLES__ = true;
  const css = `
    .cc-dock-table th, .cc-dock-table td { font-size:12px; vertical-align:middle; }
    .cc-dock-table .badge { font-size:11px; }
    .cc-dock-unit, .cc-dock-driver { font-weight:600; }
    .cc-dock-eta { width:54px; }
    .cc-dock-num { text-align:right; width:56px; }
    .cc-dock-stops { text-align:center; width:70px; }
    .cc-dock-table tbody tr:hover { background: #f8fafc; }
  `;
  const s = document.createElement('style'); s.textContent = css; document.head.appendChild(s);
})();

// Setup dock toggle
(function setupDockToggle() {
  const dock = document.querySelector('#dispatchDock');
  const toggle = document.querySelector('#dockToggle, [data-act="dock-toggle"]');
  if (!dock || !toggle) return;

  const saved = localStorage.getItem('dispatchDockExpanded');
  if (saved === '1') dock.classList.add('is-expanded');

  toggle.addEventListener('click', (e) => {
    e.preventDefault();
    dock.classList.toggle('is-expanded');
    localStorage.setItem('dispatchDockExpanded', dock.classList.contains('is-expanded') ? '1' : '0');
  });
})();

export async function loadActiveRides() {
  const panel = document.getElementById('panel-active');
  try {
    const r = await fetch('/api/rides?status=active', { headers: jsonHeaders() });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    let list = await r.json();

    list = await Promise.all(list.map(hydrateRideStops));

    panel.innerHTML = list.map(renderRideCard).join('') || '<div class="text-muted small">Sin viajes</div>';

    const b = document.getElementById('badgeActivos');
    if (b) b.textContent = list.length;

  } catch (e) {
    console.error('loadActiveRides fallo:', e);
    panel.innerHTML = `<div class="text-danger small">Error cargando activos</div>`;
  }
}

export function setupUIEvents() {
  // Mostrar/ocultar fila de schedule
  qs('#when-now')?.addEventListener('change', () => {
    const row = qs('#scheduleRow'); if (!row) return;
    row.style.display = qs('#when-now').checked ? 'none' : '';
  });
  
  qs('#when-later')?.addEventListener('change', () => {
    const row = qs('#scheduleRow'); if (!row) return;
    row.style.display = qs('#when-later').checked ? '' : 'none';
  });

  // Botón +Parada
  qs('#btnAddStop1')?.addEventListener('click', () => {
    const hasS1 = Number.isFinite(parseFloat(qs('#stop1Lat')?.value));
    if (!hasS1) { qs('#stop1Row').style.display = ''; return; }
    const hasS2 = Number.isFinite(parseFloat(qs('#stop2Lat')?.value));
    if (!hasS2) { qs('#stop2Row').style.display = ''; }
  });

  // Quitar paradas
  qs('#btnClearStop1')?.addEventListener('click', () => {
    qs('#stop1Lat').value = ''; qs('#stop1Lng').value = '';
    qs('#inStop1').value = ''; qs('#stop1Row').style.display = 'none';
    if (stop1Marker) { stop1Marker.remove(); stop1Marker = null; }
    qs('#btnClearStop2')?.click();
    drawRoute({ quiet: true }); autoQuoteIfReady();
    recalcQuoteUI();
  });
  
  qs('#btnClearStop2')?.addEventListener('click', () => {
    qs('#stop2Lat').value = ''; qs('#stop2Lng').value = '';
    qs('#inStop2').value = ''; qs('#stop2Row').style.display = 'none';
    if (stop2Marker) { stop2Marker.remove(); stop2Marker = null; }
    drawRoute({ quiet: true }); autoQuoteIfReady();
    recalcQuoteUI();
  });

  // Toggle sectores y stands
  qs('#toggle-sectores')?.addEventListener('change', e => e.target.checked ? layerSectores.addTo(map) : map.removeLayer(layerSectores));
  qs('#toggle-stands')?.addEventListener('change', e => e.target.checked ? layerStands.addTo(map) : map.removeLayer(layerStands));
}

/* resources/js/pages/dispatch/ui.js */
// Añade al archivo existente

export function focusDriverOnMap(ride) {
  if (ride.driver_id && driverPins.has(ride.driver_id)) {
    const pin = driverPins.get(ride.driver_id);
    if (pin?.marker) {
      const latLng = pin.marker.getLatLng();
      map.setView(latLng, Math.max(map.getZoom(), 16));
    }
  }
}

export function getRideById(id) {
  if (!id) return null;
  const r = window._ridesIndex?.get?.(id);
  if (r) return r;
  const list = Array.isArray(window._lastActiveRides) ? window._lastActiveRides : [];
  return list.find(x => Number(x.id) === Number(id)) || null;
}

export async function updateSuggestedRoutes(rides) {
  driverPins.forEach((_, driverId) => clearDriverRoute(driverId));

  const targetStates = new Set(['ASSIGNED', 'EN_ROUTE', 'ARRIVED', 'REQUESTED', 'OFFERED', 'SCHEDULED', 'ACCEPTED']);
  for (const r of (rides || [])) {
    const st = String(r.status || '').toUpperCase();
    if (!r.driver_id || !targetStates.has(st)) continue;
    if (!Number.isFinite(r.origin_lat) || !Number.isFinite(r.origin_lng)) continue;
    await showDriverToPickup(r.driver_id, Number(r.origin_lat), Number(r.origin_lng));
  }
}