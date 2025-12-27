// resources/js/pages/dispatch.dock.js
import { jsonHeaders } from './dispatch.core.js';
import { isActive, canonStatus, statusBadgeClass } from './dispatch.rides_panel.js';

// --- helpers mínimos ---
const _lc = s => String(s||'').toLowerCase();

export function statePill(r){
  const st = _lc(r?.status);
  if (st === 'en_route') return { cls:'badge-pill badge-enroute',  label:'EN RUTA' };
  if (st === 'arrived')  return { cls:'badge-pill badge-arrived',  label:'LLEGÓ'   };
  if (st === 'on_board') return { cls:'badge-pill badge-onboard',  label:'A BORDO' };
  return { cls:'badge-pill badge-accepted', label:'ACEPTADO' };
}

// === Dock table styles (once) ===============================================
export function injectDockTableStyles(){
  if (window.__DOCK_TABLE_STYLES__) return;
  window.__DOCK_TABLE_STYLES__ = true;

  const css = `
    .cc-dock-table th, .cc-dock-table td { font-size:12px; vertical-align:middle; }
    .cc-dock-table .badge { font-size:11px; }
    .cc-dock-unit, .cc-dock-driver { font-weight:600; }
    .cc-dock-eta { width:54px; }
    .cc-dock-num { text-align:right; width:56px; }
    .cc-dock-stops { text-align:center; width:70px; }
    .cc-dock-table tbody tr:hover { background: #f8fafc; }
  `;
  const s = document.createElement('style');
  s.textContent = css;
  document.head.appendChild(s);
}

// === Dock expandible =========================================================
export function setupDockToggle(){
  const dock   = document.querySelector('#dispatchDock');
  const toggle = document.querySelector('#dockToggle, [data-act="dock-toggle"]');
  if (!dock || !toggle) return;

  const saved = localStorage.getItem('dispatchDockExpanded');
  if (saved === '1') dock.classList.add('is-expanded');

  toggle.addEventListener('click', (e)=>{
    e.preventDefault();
    dock.classList.toggle('is-expanded');
    localStorage.setItem('dispatchDockExpanded', dock.classList.contains('is-expanded') ? '1' : '0');
  });
}

export function renderDockActive(ctx, rides){
  injectDockTableStyles();

  const _isActive = ctx?.rides?.isActive || isActive || window._isActive;
  const focusDriverOnMap = ctx?.mapFx?.focusDriverOnMap || window.focusDriverOnMap;
  const highlightRideOnMap = ctx?.mapFx?.highlightRideOnMap || window.highlightRideOnMap;

  const active = (rides||[]).filter(_isActive);

  const b1 = document.getElementById('badgeActivos');
  const b2 = document.getElementById('badgeActivosDock');
  if (b1) b1.textContent = active.length;
  if (b2) b2.textContent = active.length;

  const host = document.getElementById('dock-active-table');
  if (!host) return;

  window._ridesIndex = new Map(active.map(r=>[r.id,r]));

  const esc = (s)=>String(s??'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  const fmtKm  = (m)=> Number.isFinite(+m) ? (+m/1000).toFixed(1) : '–';
  const fmtMin = (s)=> Number.isFinite(+s) ? Math.round(+s/60) : '–';

  const parseStops = (r)=>{
    if (Array.isArray(r.stops)) return r.stops;
    if (Array.isArray(r.stops_json)) return r.stops_json;
    if (typeof r.stops_json === 'string' && r.stops_json.trim()!==''){
      try { const a = JSON.parse(r.stops_json); return Array.isArray(a) ? a : []; } catch {}
    }
    return [];
  };

  const short = (t,max=60)=> (t||'').length>max ? t.slice(0,max-1)+'…' : (t||'');

  const rows = active.map(r=>{
    const km   = fmtKm(r.distance_m);
    const min  = fmtMin(r.duration_s);
    const st   = String(canonStatus(r.status) || '').toUpperCase();
    const badge= statusBadgeClass(r.status);
    const dvr  = r.driver_name || r.driver?.name || '—';
    const unit = r.vehicle_economico || r.vehicle_plate || r.vehicle_id || '—';
    const eta  = (r.pickup_eta_min ?? '—');

    const stops = parseStops(r);
    const sc    = stops.length || Number(r.stops_count||0);

    const tipRaw = stops.map((s,i)=>{
      const label = (s && typeof s.label==='string' && s.label.trim()!=='')
        ? s.label.trim()
        : (Number.isFinite(+s.lat)&&Number.isFinite(+s.lng) ? `${(+s.lat).toFixed(5)}, ${(+s.lng).toFixed(5)}` : '—');
      return `S${i+1}: ${label}`;
    }).join('\n');
    const tipHtml = esc(tipRaw).replace(/\n/g,'&#10;');

    const stopsCell = sc
      ? `<span class="badge bg-secondary-subtle text-secondary" title="${tipHtml}">${sc}</span>`
      : '—';

    return `
      <tr class="cc-row" data-ride-id="${r.id}">
        <td class="cc-dock-unit">${esc(unit)}</td>
        <td class="cc-dock-driver">${esc(dvr)}</td>
        <td class="cc-dock-eta">${esc(String(eta))}</td>
        <td class="small" title="${esc(r.origin_label||'')}">${esc(short(r.origin_label,50))}</td>
        <td class="small" title="${esc(r.dest_label||'')}">${esc(short(r.dest_label,50))}</td>
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

  host.querySelectorAll('button[data-act="view"]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const tr = btn.closest('tr');
      const id = Number(tr?.dataset?.rideId);
      const r  = window._ridesIndex.get(id);
      if (r){
        highlightRideOnMap?.(r);
        focusDriverOnMap?.(r);
      }
    });
  });

  host.querySelectorAll('tr.cc-row').forEach(tr=>{
    tr.addEventListener('click', (e)=>{
      if (e.target.closest('button')) return;
      const id = Number(tr.dataset.rideId);
      const r  = window._ridesIndex.get(id);
      if (r) focusDriverOnMap?.(r);
    });
  });
}

// compat si tu código aún lo llama global
window.renderDockActive = (rides) => renderDockActive(null, rides);
window.setupDockToggle  = () => setupDockToggle();


