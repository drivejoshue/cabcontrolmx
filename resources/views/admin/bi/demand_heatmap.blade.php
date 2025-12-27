@extends('layouts.admin')
@section('title','BI · Mapa de demanda')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
  #biMap { height: calc(100vh - 220px); min-height: 520px; border-radius: 12px; }
  .bi-kpi { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; padding: 10px 12px; }
  [data-bs-theme="dark"] .bi-kpi { border-color: rgba(255,255,255,.10); }
  .bi-toolbar .form-label { margin-bottom: .25rem; font-size: .8rem; opacity: .8; }

  /* Floating legend */
  .bi-legend {
    position: absolute;
    right: 14px;
    bottom: 14px;
    z-index: 800;
    width: 290px;
    border-radius: 12px;
    padding: 10px 12px;
    backdrop-filter: blur(6px);
    background: rgba(255,255,255,.92);
    border: 1px solid rgba(0,0,0,.10);
    box-shadow: 0 10px 22px rgba(0,0,0,.12);
  }
  [data-bs-theme="dark"] .bi-legend {
    background: rgba(24,28,36,.78);
    border-color: rgba(255,255,255,.10);
    box-shadow: 0 10px 22px rgba(0,0,0,.35);
  }
  .bi-legend .t { font-weight: 600; font-size: .9rem; }
  .bi-legend .s { font-size: .8rem; opacity: .85; }
  .bi-legend .bar {
    height: 10px; border-radius: 999px;
    background: linear-gradient(90deg,
      #3b82f6 0%,
      #22c55e 35%,
      #f59e0b 70%,
      #ef4444 100%
    );
    border: 1px solid rgba(0,0,0,.10);
  }
  [data-bs-theme="dark"] .bi-legend .bar { border-color: rgba(255,255,255,.10); }

  .bi-legend .row2 { display:flex; justify-content:space-between; font-size:.78rem; opacity:.85; margin-top:6px; }
  .bi-legend .meta { font-size:.78rem; opacity:.75; margin-top:6px; }

  /* Hotspot markers */
  .bi-hotspot {
    width: 14px; height: 14px; border-radius: 999px;
    border: 2px solid rgba(255,255,255,.95);
    background: rgba(239,68,68,.95);
    box-shadow: 0 6px 14px rgba(0,0,0,.25);
  }
</style>
@endpush

@section('content')
<div class="container-xl">
  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title">BI · Mapa de demanda</h2>
        <div class="text-muted">
          Heatmap de orígenes por rango de fechas · Canal (App/Central) · Estado · Horario.
        </div>
      </div>
      <div class="col-auto d-flex gap-2">
        <a href="#" class="btn btn-outline-secondary" id="btnHotspot">Ir al hotspot</a>
        <a href="#" class="btn btn-outline-secondary" id="btnReset">Reset</a>
      </div>
    </div>
  </div>

  {{-- Toolbar filtros --}}
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3 bi-toolbar">
        <div class="col-12 col-md-3">
          <label class="form-label">Fecha inicio</label>
          <input type="date" class="form-control" id="fStart">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Fecha fin</label>
          <input type="date" class="form-control" id="fEnd">
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label">Hora desde</label>
          <select class="form-select" id="fHourFrom"></select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Hora hasta</label>
          <select class="form-select" id="fHourTo"></select>
        </div>

        <div class="col-6 col-md-1">
          <label class="form-label">Canal</label>
          <select class="form-select" id="fChannel">
            <option value="all">Todos</option>
            <option value="app">App</option>
            <option value="dispatch">Central</option>
          </select>
        </div>

        <div class="col-6 col-md-1">
          <label class="form-label">Estado</label>
          <select class="form-select" id="fStatus">
            <option value="all">Todos</option>
            <option value="finished">Finalizados</option>
            <option value="canceled">Cancelados</option>
            <option value="requested">Solicitados</option>
            <option value="accepted">Aceptados</option>
            <option value="on_trip">En viaje</option>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label">Peso del heatmap</label>
          <div class="btn-group w-100" role="group" aria-label="weight">
            <input type="radio" class="btn-check" name="weight" id="wCount" value="count" checked>
            <label class="btn btn-outline-primary" for="wCount">Servicios</label>

            <input type="radio" class="btn-check" name="weight" id="wAmount" value="amount">
            <label class="btn btn-outline-primary" for="wAmount">Dinero</label>

            <input type="radio" class="btn-check" name="weight" id="wCanceled" value="canceled">
            <label class="btn btn-outline-primary" for="wCanceled">Cancelaciones</label>
          </div>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label">Resolución (grid)</label>
          <select class="form-select" id="fGrid">
            <option value="2">Baja (2 dec)</option>
            <option value="3" selected>Media (3 dec)</option>
            <option value="4">Alta (4 dec)</option>
          </select>
        </div>

        {{-- NUEVO: modo visualización --}}
        <div class="col-6 col-md-2">
          <label class="form-label">Visualización</label>
          <select class="form-select" id="fViz">
            <option value="heat" selected>Heatmap</option>
            <option value="hotspots">Hotspots</option>
          </select>
        </div>

        {{-- NUEVO: suavizado --}}
        <div class="col-6 col-md-2">
          <label class="form-label">Suavizado</label>
          <select class="form-select" id="fSmooth">
            <option value="low">Bajo</option>
            <option value="med" selected>Medio</option>
            <option value="high">Alto</option>
          </select>
        </div>

        {{-- NUEVO: opacidad --}}
        <div class="col-6 col-md-2">
          <label class="form-label">Opacidad</label>
          <input type="range" class="form-range" id="fOpacity" min="30" max="90" step="5" value="80">
        </div>

        <div class="col-12 col-md-2 d-grid">
          <label class="form-label">&nbsp;</label>
          <button class="btn btn-primary" id="btnApply">Aplicar</button>
        </div>
      </div>
    </div>
  </div>

  {{-- KPIs --}}
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="bi-kpi">
        <div class="text-muted small">Servicios</div>
        <div class="h2 m-0" id="kpiRides">—</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="bi-kpi">
        <div class="text-muted small">Cancelados</div>
        <div class="h2 m-0" id="kpiCanceled">—</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="bi-kpi">
        <div class="text-muted small">Ingresos</div>
        <div class="h2 m-0" id="kpiIncome">—</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="bi-kpi">
        <div class="text-muted small">Ticket prom.</div>
        <div class="h2 m-0" id="kpiAvg">—</div>
      </div>
    </div>
  </div>

  {{-- Mapa --}}
  <div class="card">
    <div class="card-body p-2 position-relative">
      <div id="biMap"></div>

      {{-- NUEVO: leyenda/guía --}}
      <div class="bi-legend" id="biLegend">
        <div class="t" id="legTitle">Guía</div>
        <div class="s" id="legSubtitle">Interpretación del heatmap</div>
        <div class="bar mt-2"></div>
        <div class="row2">
          <span>Bajo</span><span>Alto</span>
        </div>
        <div class="meta" id="legMeta">—</div>
        <div class="meta" id="legGrid">—</div>

        
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>

<script>
(function(){
  const API_URL = @json(route('admin.bi.heat.origins'));

  const today = new Date();
  const pad = n => String(n).padStart(2,'0');
  const toYmd = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

  const dEnd = new Date(today);
  const dStart = new Date(today);
  dStart.setDate(dStart.getDate() - 7);

  const el = (id)=>document.getElementById(id);

  // Hours selects
  const hourFrom = el('fHourFrom');
  const hourTo = el('fHourTo');
  for (let h=0; h<=23; h++) {
    const opt1 = document.createElement('option');
    opt1.value = String(h); opt1.textContent = pad(h)+":00";
    hourFrom.appendChild(opt1);

    const opt2 = document.createElement('option');
    opt2.value = String(h); opt2.textContent = pad(h)+":00";
    hourTo.appendChild(opt2);
  }
  hourFrom.value = "0";
  hourTo.value = "23";

  el('fStart').value = toYmd(dStart);
  el('fEnd').value = toYmd(dEnd);

  // Map init
  const map = L.map('biMap', { zoomControl: true }).setView([19.1738, -96.1342], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);

  // Layers
  let heat = L.heatLayer([], { radius: 28, blur: 20, maxZoom: 18 }).addTo(map);
  const hotspotsLayer = L.layerGroup().addTo(map);

  // State
  let lastPoints = []; // [{lat,lng,v,cnt,amt,canc}]
  let lastMeta = {};
  let lastHotspot = null;

  const fmtMoney = (n)=> {
    const v = Number(n||0);
    return v.toLocaleString('es-MX', { style:'currency', currency:'MXN', maximumFractionDigits: 2 });
  };

  function getWeight(){
    const w = document.querySelector('input[name="weight"]:checked');
    return w ? w.value : 'count';
  }

  function getViz(){ return el('fViz').value || 'heat'; }

  function smoothParams(level){
    // Valores seguros (no “inventan” colores, solo suavizan)
    if (level === 'low')  return { radius: 18, blur: 14 };
    if (level === 'high') return { radius: 40, blur: 28 };
    return { radius: 28, blur: 20 };
  }

  function applyHeatStyle(){
    const { radius, blur } = smoothParams(el('fSmooth').value);
    heat.setOptions({ radius, blur });
    const op = Number(el('fOpacity').value || 80) / 100;

    // Leaflet.heat no expone opacity directo; se aplica al canvas
    // hack seguro: ajustar el estilo del canvas dentro del overlayPane
    const canv = document.querySelector('#biMap canvas.leaflet-heatmap-layer');
    if (canv) canv.style.opacity = String(op);
  }

  function legendText(weight){
    if (weight === 'amount')   return { t:'Modo: Dinero', s:'Densidad ponderada por monto' };
    if (weight === 'canceled') return { t:'Modo: Cancelaciones', s:'Densidad de cancelaciones' };
    return { t:'Modo: Servicios', s:'Densidad de solicitudes' };
  }

  function approxGridMeters(dec){
    // Muy aproximado: 1° lat ~ 111km. 0.001 ~ 111m, 0.01 ~ 1.11km
    if (dec === 2) return '~1.1 km';
    if (dec === 3) return '~110 m';
    if (dec === 4) return '~11 m';
    return '';
  }

  function updateLegend(){
    const w = lastMeta.weight || getWeight();
    const g = Number(lastMeta.grid || el('fGrid').value || 3);
    const { t, s } = legendText(w);
    el('legTitle').textContent = t;
    el('legSubtitle').textContent = s;

    // min/max en unidades reales (antes de normalizar)
    if (lastPoints.length) {
      const vals = lastPoints.map(p => Number(p.v || 0)).filter(v => v > 0);
      const min = Math.min(...vals);
      const max = Math.max(...vals);

      let rangeTxt = '';
      if (w === 'amount') rangeTxt = `Rango aprox: ${fmtMoney(min)} → ${fmtMoney(max)} por celda`;
      else if (w === 'canceled') rangeTxt = `Rango aprox: ${min} → ${max} cancelaciones por celda`;
      else rangeTxt = `Rango aprox: ${min} → ${max} servicios por celda`;

      el('legMeta').textContent = rangeTxt;
    } else {
      el('legMeta').textContent = 'Sin datos en el rango seleccionado.';
    }

    el('legGrid').textContent = `Grid: ${g} dec (${approxGridMeters(g)}) · Visualización: ${getViz()==='heat'?'Heatmap':'Hotspots'}`;
  }

  function clearHotspots(){
    hotspotsLayer.clearLayers();
  }

  function renderHotspots(){
    clearHotspots();
    const top = [...lastPoints].sort((a,b)=> (b.v||0)-(a.v||0)).slice(0, 25);
    top.forEach((p, idx) => {
      const r = idx < 3 ? 12 : (idx < 10 ? 10 : 8);
      const m = L.circleMarker([p.lat, p.lng], {
        radius: r,
        weight: 2,
        color: 'rgba(255,255,255,.9)',
        fillColor: 'rgba(239,68,68,.92)',
        fillOpacity: 0.9
      });

      const html = `
        <div style="min-width:220px">
          <div style="font-weight:600;margin-bottom:4px">Hotspot #${idx+1}</div>
          <div>Servicios: <b>${(p.cnt||0).toLocaleString('es-MX')}</b></div>
          <div>Ingresos: <b>${fmtMoney(p.amt||0)}</b></div>
          <div>Cancelados: <b>${(p.canc||0).toLocaleString('es-MX')}</b></div>
          <div style="opacity:.75;margin-top:6px;font-size:.85em">(${Number(p.lat).toFixed(4)}, ${Number(p.lng).toFixed(4)})</div>
        </div>
      `;
      m.bindPopup(html, { closeButton:true });
      m.addTo(hotspotsLayer);
    });
  }

  function setVisualization(){
    const viz = getViz();
    if (viz === 'hotspots') {
      map.removeLayer(heat);
      if (!map.hasLayer(hotspotsLayer)) hotspotsLayer.addTo(map);
      renderHotspots();
    } else {
      if (!map.hasLayer(heat)) heat.addTo(map);
      clearHotspots();
    }
    updateLegend();
    applyHeatStyle();
  }

  async function load(){
    el('btnApply').disabled = true;

    const params = new URLSearchParams({
      start_date: el('fStart').value,
      end_date: el('fEnd').value,
      hour_from: el('fHourFrom').value,
      hour_to: el('fHourTo').value,
      channel: el('fChannel').value,
      status: el('fStatus').value,
      weight: getWeight(),
      grid: el('fGrid').value,
    });

    const res = await fetch(API_URL + '?' + params.toString(), { headers: { 'Accept':'application/json' } });
    if (!res.ok) {
      console.error('heat api error', res.status);
      el('btnApply').disabled = false;
      return;
    }
    const data = await res.json();

    // KPIs
    el('kpiRides').textContent = (data.kpis?.rides ?? 0).toLocaleString('es-MX');
    el('kpiCanceled').textContent = (data.kpis?.canceled ?? 0).toLocaleString('es-MX');
    el('kpiIncome').textContent = fmtMoney(data.kpis?.income ?? 0);
    el('kpiAvg').textContent = fmtMoney(data.kpis?.avg_ticket ?? 0);

    lastMeta = data.meta || {};
    lastPoints = (data.points || []).map(p => ({
      lat: Number(p.lat), lng: Number(p.lng),
      v: Number(p.v||0), cnt: Number(p.cnt||0), amt: Number(p.amt||0), canc: Number(p.canc||0)
    }));

    // Define hotspot principal
    lastHotspot = null;
    if (lastPoints.length) {
      lastHotspot = [...lastPoints].sort((a,b)=> (b.v||0)-(a.v||0))[0];
    }

    // Heat points (normalización para amount)
    const weight = lastMeta.weight || 'count';
    const pts = lastPoints.map(p => {
      let v = Number(p.v || 0);
      if (weight === 'amount') {
        // normaliza para que no se “queme”; conserva orden relativo
        v = Math.log10(Math.max(1, v));
      }
      return [p.lat, p.lng, v];
    });

    heat.setLatLngs(pts);

    // Auto-fit
    if (pts.length > 0) {
      const b = L.latLngBounds(pts.map(x => [x[0], x[1]]));
      map.fitBounds(b.pad(0.15));
    }

    setVisualization();
    el('btnApply').disabled = false;
  }

  // Events
  el('btnApply').addEventListener('click', (e)=>{ e.preventDefault(); load(); });

  el('btnReset').addEventListener('click', (e)=>{
    e.preventDefault();
    el('fStart').value = toYmd(dStart);
    el('fEnd').value = toYmd(dEnd);
    hourFrom.value = "0";
    hourTo.value = "23";
    el('fChannel').value = "all";
    el('fStatus').value = "all";
    el('wCount').checked = true;
    el('fGrid').value = "3";
    el('fViz').value = "heat";
    el('fSmooth').value = "med";
    el('fOpacity').value = "80";
    load();
  });

  el('btnHotspot').addEventListener('click', (e)=>{
    e.preventDefault();
    if (!lastHotspot) return;
    map.flyTo([lastHotspot.lat, lastHotspot.lng], Math.max(map.getZoom(), 15), { duration: 0.8 });
  });

  document.querySelectorAll('input[name="weight"]').forEach(x => x.addEventListener('change', load));
  el('fViz').addEventListener('change', setVisualization);
  el('fSmooth').addEventListener('change', applyHeatStyle);
  el('fOpacity').addEventListener('input', applyHeatStyle);

  // First load
  load();
})();
</script>
@endpush
