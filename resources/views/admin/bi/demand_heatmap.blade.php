@extends('layouts.admin')
@section('title','BI · Mapa de demanda')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
/* =========================================================
   BI DEMAND HEATMAP — LAYOUT / DESIGN SYSTEM (EDITABLE)
   ========================================================= */

:root{
  --bi-radius: 12px;
  --bi-border: rgba(0,0,0,.10);
  --bi-border-dark: rgba(255,255,255,.10);
  --bi-shadow: 0 10px 22px rgba(0,0,0,.10);
  --bi-shadow-dark: 0 10px 22px rgba(0,0,0,.35);
}

.bi-kpi{
  border: 1px solid var(--bi-border);
  border-radius: var(--bi-radius);
  padding: 10px 12px;
  background: rgba(255,255,255,.72);
  backdrop-filter: blur(6px);
}
[data-bs-theme="dark"] .bi-kpi{
  border-color: var(--bi-border-dark);
  background: rgba(24,28,36,.55);
}

.bi-kpi .lbl{ font-size: .78rem; opacity:.75; }
.bi-kpi .val{ font-size: 1.55rem; font-weight: 700; line-height: 1.05; margin-top: 2px; }
.bi-kpi .sub{ font-size: .75rem; opacity:.75; margin-top: 2px; }

.bi-panel{
  border: 1px solid var(--bi-border);
  border-radius: var(--bi-radius);
  box-shadow: var(--bi-shadow);
}
[data-bs-theme="dark"] .bi-panel{
  border-color: var(--bi-border-dark);
  box-shadow: var(--bi-shadow-dark);
}

.bi-filters{
  position: sticky;
  top: 84px;
}

.bi-filters .card-header{
  padding: .65rem .75rem;
  border-bottom: 1px solid var(--bi-border);
}
[data-bs-theme="dark"] .bi-filters .card-header{
  border-bottom-color: var(--bi-border-dark);
}

.bi-filters .card-body{
  padding: .75rem;
}

.bi-filters .form-label{
  margin-bottom: .25rem;
  font-size: .76rem;
  opacity: .78;
}

.bi-filters .form-control,
.bi-filters .form-select{
  padding: .35rem .55rem;
  font-size: .85rem;
  border-radius: 10px;
}

.bi-mini-help{
  font-size: .75rem;
  opacity: .70;
}

.bi-chipgroup .btn{
  --bs-btn-padding-y: .25rem;
  --bs-btn-padding-x: .5rem;
  --bs-btn-font-size: .78rem;
}

.bi-chipgroup .btn-outline-secondary{
  border-color: rgba(0,0,0,.12);
}
[data-bs-theme="dark"] .bi-chipgroup .btn-outline-secondary{
  border-color: rgba(255,255,255,.12);
}

.bi-mapwrap{
  border-radius: var(--bi-radius);
  overflow: hidden;
  border: 1px solid var(--bi-border);
  position: relative;
}
[data-bs-theme="dark"] .bi-mapwrap{
  border-color: var(--bi-border-dark);
}
/* --- MAP CARD: que estire hasta abajo y el mapa rellene --- */
.bi-map-card{
  height: calc(100vh - 170px); /* ajusta SOLO este número si tu header/kpis cambian */
  min-height: 680px;
  display: flex;
  flex-direction: column;
}

.bi-map-card .card-body{
  flex: 1;
  display: flex;
  flex-direction: column;
}

/* el contenedor del mapa ocupa todo el alto disponible */
.bi-mapwrap{
  flex: 1;
  display: flex;
  flex-direction: column;
}

/* el DIV de Leaflet rellena el wrap */
#biMap{
  flex: 1;
  height: auto !important;   /* mata el height fijo anterior */
  min-height: 620px;         /* seguridad */
}

/* Mobile: conserva tu comportamiento */
@media (max-width: 991px){
  .bi-map-card{
    height: auto;
    min-height: 0;
  }
  #biMap{
    height: 70vh !important;
    min-height: 520px;
  }
}
@media (min-width: 992px){
  .bi-map-card{
    position: sticky;
    top: 84px; /* igual que tus filtros */
  }
}


/* Floating legend */
.bi-legend{
  position: absolute;
  right: 14px;
  bottom: 14px;
  z-index: 800;
  width: 320px;
  border-radius: 12px;
  padding: 10px 12px;
  backdrop-filter: blur(6px);
  background: rgba(255,255,255,.92);
  border: 1px solid rgba(0,0,0,.10);
  box-shadow: 0 10px 22px rgba(0,0,0,.12);
}
[data-bs-theme="dark"] .bi-legend{
  background: rgba(24,28,36,.78);
  border-color: rgba(255,255,255,.10);
  box-shadow: 0 10px 22px rgba(0,0,0,.35);
}
.bi-legend .t{ font-weight:700; font-size:.92rem; }
.bi-legend .s{ font-size:.80rem; opacity:.85; }
.bi-legend .bar{
  height: 10px;
  border-radius: 999px;
  background: linear-gradient(90deg,#3b82f6 0%,#22c55e 35%,#f59e0b 70%,#ef4444 100%);
  border: 1px solid rgba(0,0,0,.10);
}
[data-bs-theme="dark"] .bi-legend .bar{ border-color: rgba(255,255,255,.10); }

.bi-legend .row2{ display:flex; justify-content:space-between; font-size:.78rem; opacity:.85; margin-top:6px; }
.bi-legend .meta{ font-size:.78rem; opacity:.78; margin-top:6px; }

/* Loading overlay */
.bi-loading{
  position:absolute; inset: 0;
  z-index: 900;
  display:none;
  align-items:center;
  justify-content:center;
  background: rgba(255,255,255,.60);
  backdrop-filter: blur(2px);
}
[data-bs-theme="dark"] .bi-loading{
  background: rgba(0,0,0,.35);
}
.bi-loading .box{
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid var(--bi-border);
  background: rgba(255,255,255,.90);
}
[data-bs-theme="dark"] .bi-loading .box{
  border-color: var(--bi-border-dark);
  background: rgba(24,28,36,.80);
}

/* Small tweaks for leaflet controls on dark */
[data-bs-theme="dark"] .leaflet-control-layers,
[data-bs-theme="dark"] .leaflet-control-zoom,
[data-bs-theme="dark"] .leaflet-control-scale{
  filter: saturate(1.0);
}



/* Orbana dark: tinte gris SIN tocar canvas del heat */
#biMap .leaflet-tint {
  position:absolute;
  inset:0;
  pointer-events:none;
  background: transparent;
  transition: background .15s ease;
}
#biMap.map-orbana-dark .leaflet-tint {
  background: rgba(7, 12, 20, .55); /* gris oscuro Orbana */
}
</style>
@endpush

@section('content')
<div class="container-xl">

  {{-- =========================================================
     HEADER
     ========================================================= --}}
  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title mb-1">BI · Mapa de demanda</h2>
        <div class="text-muted">
          Heatmap de orígenes (solicitudes) por rango de fechas · canal · estado · horario.
        </div>
      </div>
      <div class="col-auto d-flex gap-2">
        <a href="#" class="btn btn-outline-secondary" id="btnHotspot">Ir al hotspot</a>
        <a href="#" class="btn btn-outline-secondary" id="btnReset">Reset</a>
      </div>
    </div>
  </div>

  {{-- =========================================================
     KPIs (HORIZONTAL, KEEP TOP)
     ========================================================= --}}
  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
      <div class="bi-kpi">
        <div class="lbl">Servicios</div>
        <div class="val" id="kpiRides">—</div>
        <div class="sub" id="kpiRidesSub">—</div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="bi-kpi">
        <div class="lbl">Cancelados</div>
        <div class="val" id="kpiCanceled">—</div>
        <div class="sub" id="kpiCanceledSub">—</div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="bi-kpi">
        <div class="lbl">Ingresos</div>
        <div class="val" id="kpiIncome">—</div>
        <div class="sub" id="kpiIncomeSub">—</div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="bi-kpi">
        <div class="lbl">Ticket prom.</div>
        <div class="val" id="kpiAvg">—</div>
        <div class="sub" id="kpiAvgSub">—</div>
      </div>
    </div>
  </div>

  {{-- =========================================================
     MAIN GRID: FILTERS LEFT (COMPACT) · MAP RIGHT (WIDE)
     ========================================================= --}}
  <div class="row g-3">

    {{-- ------------------ LEFT: FILTERS ------------------ --}}
    <div class="col-12 col-lg-3">
      <div class="card bi-panel bi-filters">
        <div class="card-header">
          <div class="d-flex align-items-center justify-content-between">
            <div class="fw-semibold">Filtros</div>
            <button class="btn btn-sm btn-outline-secondary" id="btnApply">
              Aplicar
            </button>
          </div>
          <div class="bi-mini-help mt-1">
            Consejo: usa “30 días” + “Heat” para ver demanda real sin ruido.
          </div>
        </div>

        <div class="card-body">

          {{-- ====== SECTION: DATE RANGE ====== --}}
          <div class="mb-3">
            <label class="form-label">Rango de fechas</label>
            <div class="d-flex gap-2">
              <input type="date" class="form-control" id="fStart">
              <input type="date" class="form-control" id="fEnd">
            </div>
            <div class="btn-group w-100 mt-2 bi-chipgroup" role="group" aria-label="quick-range">
              <button class="btn btn-outline-secondary" data-range="7">7d</button>
              <button class="btn btn-outline-secondary active" data-range="30">30d</button>
              <button class="btn btn-outline-secondary" data-range="90">90d</button>
            </div>
          </div>

          {{-- ====== SECTION: TIME RANGE ====== --}}
          <div class="mb-3">
            <label class="form-label">Horario</label>

            <div class="btn-group w-100 bi-chipgroup" role="group" aria-label="hour-presets" id="hourPresets">
              <button class="btn btn-outline-secondary active" data-hr="all">24h</button>
              <button class="btn btn-outline-secondary" data-hr="am">AM</button>
              <button class="btn btn-outline-secondary" data-hr="pm">PM</button>
              <button class="btn btn-outline-secondary" data-hr="night">Noche</button>
              <button class="btn btn-outline-secondary" data-hr="custom">Custom</button>
            </div>

            <div class="d-flex gap-2 mt-2" id="hourCustomRow" style="display:none;">
              <select class="form-select" id="fHourFrom"></select>
              <select class="form-select" id="fHourTo"></select>
            </div>
          </div>

          {{-- ====== SECTION: CHANNEL ====== --}}
          <div class="mb-3">
            <label class="form-label">Canal</label>
            <div class="btn-group w-100 bi-chipgroup" role="group" aria-label="channel" id="channelGroup">
              <button class="btn btn-outline-secondary active" data-val="all">Todos</button>
              <button class="btn btn-outline-secondary" data-val="app">App</button>
              <button class="btn btn-outline-secondary" data-val="dispatch">Central</button>
            </div>
          </div>

          {{-- ====== SECTION: STATUS ====== --}}
          <div class="mb-3">
            <label class="form-label">Estado</label>
            <div class="btn-group w-100 bi-chipgroup" role="group" aria-label="status" id="statusGroup">
              <button class="btn btn-outline-secondary active" data-val="all">Todos</button>
              <button class="btn btn-outline-secondary" data-val="finished">Fin</button>
              <button class="btn btn-outline-secondary" data-val="canceled">Canc</button>
              <button class="btn btn-outline-secondary" data-val="requested">Req</button>
              <button class="btn btn-outline-secondary" data-val="on_trip">Trip</button>
            </div>
            <div class="bi-mini-help mt-1">“Req” es demanda bruta; “Fin” es demanda servida.</div>
          </div>

          {{-- ====== SECTION: WEIGHT ====== --}}
          <div class="mb-3">
            <label class="form-label">Peso</label>
            <div class="btn-group w-100 bi-chipgroup" role="group" aria-label="weight" id="weightGroup">
              <button class="btn btn-outline-secondary active" data-val="count">Servicios</button>
              <button class="btn btn-outline-secondary" data-val="amount">Dinero</button>
              <button class="btn btn-outline-secondary" data-val="canceled">Cancel.</button>
            </div>
          </div>

          {{-- ====== SECTION: GRID / ACCURACY ====== --}}
          <div class="mb-3">
            <label class="form-label">Resolución (grid)</label>
            <div class="btn-group w-100 bi-chipgroup" role="group" aria-label="grid" id="gridGroup">
              <button class="btn btn-outline-secondary" data-val="2">Baja</button>
              <button class="btn btn-outline-secondary active" data-val="3">Media</button>
              <button class="btn btn-outline-secondary" data-val="4">Alta</button>
              <button class="btn btn-outline-secondary" data-val="5">Ultra</button>
            </div>
            <div class="bi-mini-help mt-1" id="gridHint">—</div>
          </div>

          {{-- ====== SECTION: VISUAL MODE ====== --}}
          <div class="mb-3">
            <label class="form-label">Visualización</label>
            <div class="btn-group w-100 bi-chipgroup" role="group" aria-label="viz" id="vizGroup">
              <button class="btn btn-outline-secondary active" data-val="heat">Heat</button>
              <button class="btn btn-outline-secondary" data-val="hotspots">Hot</button>
              <button class="btn btn-outline-secondary" data-val="cells">Celdas</button>
              <button class="btn btn-outline-secondary" data-val="points">Puntos</button>
            </div>
            <div class="bi-mini-help mt-1">“Celdas” es lo más exacto para ver buckets.</div>
          </div>

          {{-- ====== SECTION: SMOOTH + OPACITY ====== --}}
          <div class="mb-2">
            <label class="form-label">Suavizado</label>
            <div class="btn-group w-100 bi-chipgroup" role="group" aria-label="smooth" id="smoothGroup">
              <button class="btn btn-outline-secondary" data-val="low">Bajo</button>
              <button class="btn btn-outline-secondary active" data-val="med">Medio</button>
              <button class="btn btn-outline-secondary" data-val="high">Alto</button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Opacidad</label>
            <input type="range" class="form-range" id="fOpacity" min="35" max="95" step="5" value="80">
          </div>

          <div class="d-grid gap-2">
            <button class="btn btn-primary" id="btnApply2">Aplicar</button>
            <button class="btn btn-outline-secondary" id="btnAutofit">Auto-fit: ON</button>
          </div>

        </div>
      </div>
    </div>

    {{-- ------------------ RIGHT: MAP (WIDE) ------------------ --}}
    <div class="col-12 col-lg-9">
     <div class="card bi-panel bi-map-card">

        <div class="card-body p-2">
          <div class="bi-mapwrap">
            <div id="biMap"></div>

            {{-- loading --}}
            <div class="bi-loading" id="biLoading">
              <div class="box">
                <div class="fw-semibold">Cargando datos…</div>
                <div class="bi-mini-help">Aplicando normalización y render…</div>
              </div>
            </div>

            {{-- legend --}}
            <div class="bi-legend" id="biLegend">
              <div class="t" id="legTitle">Guía</div>
              <div class="s" id="legSubtitle">Interpretación de intensidad</div>
              <div class="bar mt-2"></div>
              <div class="row2">
                <span>Bajo</span><span>Alto</span>
              </div>
              <div class="meta" id="legMeta">—</div>
              <div class="meta" id="legGrid">—</div>
              <div class="meta" id="legNotes">—</div>
            </div>

          </div>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>

<script>
/* =========================================================
   BI DEMAND HEATMAP — STABLE MODES (NO JUMPS)
   Objetivo:
   - Cambiar entre Heat / Hotspots / Celdas / Puntos SIN que “brinquen”
   - Heat con jitter determinístico + cacheado (no se mueve al recargar/mode)
   - Radios/estilos consistentes con zoom (suaves, sin saltos grandes)
   - Auto-fit NO vuelve a disparar si el usuario ya movió el mapa
   ========================================================= */
(function(){
  /* -----------------------------
     SECTION A: CONSTANTS / DOM
     ----------------------------- */
  const API_URL = @json(route('admin.bi.heat.origins'));
  const MAP_CENTER = @json($mapCenter); // {lat,lng,zoom}

  const el = (id)=>document.getElementById(id);

  const UI = {
    start: el('fStart'),
    end: el('fEnd'),
    hourFrom: el('fHourFrom'),
    hourTo: el('fHourTo'),
    hourCustomRow: el('hourCustomRow'),
    hourPresets: el('hourPresets'),

    btnApply: el('btnApply'),
    btnApply2: el('btnApply2'),
    btnReset: el('btnReset'),
    btnHotspot: el('btnHotspot'),
    btnAutofit: el('btnAutofit'),

    rangeBtns: document.querySelectorAll('[data-range]'),
    channelGroup: el('channelGroup'),
    statusGroup: el('statusGroup'),
    weightGroup: el('weightGroup'),
    gridGroup: el('gridGroup'),
    vizGroup: el('vizGroup'),
    smoothGroup: el('smoothGroup'),
    opacity: el('fOpacity'),

    kpiRides: el('kpiRides'),
    kpiCanceled: el('kpiCanceled'),
    kpiIncome: el('kpiIncome'),
    kpiAvg: el('kpiAvg'),
    kpiRidesSub: el('kpiRidesSub'),
    kpiCanceledSub: el('kpiCanceledSub'),
    kpiIncomeSub: el('kpiIncomeSub'),
    kpiAvgSub: el('kpiAvgSub'),

    legend: el('biLegend'),
    legTitle: el('legTitle'),
    legSubtitle: el('legSubtitle'),
    legMeta: el('legMeta'),
    legGrid: el('legGrid'),
    legNotes: el('legNotes'),
    gridHint: el('gridHint'),

    loading: el('biLoading'),
  };

  const pad = n => String(n).padStart(2,'0');
  const toYmd = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
  const today = new Date();

  /* -----------------------------
     SECTION B: STATE
     ----------------------------- */
  const state = {
    // data
    lastMeta: {},
    lastPointsRaw: [],     // [{lat,lng,v,cnt,amt,canc}]
    lastPointsScaled: [],  // [[lat,lng,t01], ...]
    lastHotspot: null,

    // ui selections
    channel: 'all',
    status: 'all',
    weight: 'count',
    grid: 3,
    viz: 'heat',          // heat|hotspots|cells|points
    smooth: 'med',        // low|med|high
    opacity: 0.80,
    autofit: true,

    // hour preset
    hourPreset: 'all',    // all|am|pm|night|custom
    hourFrom: 0,
    hourTo: 23,

    // computed normalization info
    norm: { min: 0, max: 1, p95: 1, transform: 'linear' }
  };

  /* -----------------------------
     SECTION C: FORMATTERS
     ----------------------------- */
  const fmtMoney = (n)=> {
    const v = Number(n||0);
    return v.toLocaleString('es-MX', { style:'currency', currency:'MXN', maximumFractionDigits: 2 });
  };
  const fmtInt = (n)=> (Number(n||0)).toLocaleString('es-MX');
  const clamp01 = (x)=> Math.max(0, Math.min(1, x));
  const clamp = (x, a, b)=> Math.max(a, Math.min(b, x));

  function approxGridMeters(dec){
    if (dec === 2) return '~1.1 km';
    if (dec === 3) return '~110 m';
    if (dec === 4) return '~11 m';
    if (dec === 5) return '~1.1 m';
    return '';
  }

  /* -----------------------------
     SECTION D: UI HELPERS
     ----------------------------- */
  function setActive(groupEl, btn){
    if (!groupEl) return;
    groupEl.querySelectorAll('button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  }

  function toggleLoading(on){
    if (!UI.loading) return;
    UI.loading.style.display = on ? 'flex' : 'none';
  }

  function syncGridHint(){
    if (!UI.gridHint) return;
    UI.gridHint.textContent = `Grid ${state.grid} dec (${approxGridMeters(state.grid)}) · Más decimales = más exactitud (pero más ruido).`;
  }

  function disableApply(on){
    if (UI.btnApply) UI.btnApply.disabled = on;
    if (UI.btnApply2) UI.btnApply2.disabled = on;
  }

  /* -----------------------------
     SECTION E: HOUR PRESETS
     ----------------------------- */
  function applyHourPreset(preset){
    state.hourPreset = preset;

    if (preset === 'all'){
      state.hourFrom = 0; state.hourTo = 23;
      if (UI.hourCustomRow) UI.hourCustomRow.style.display = 'none';
    } else if (preset === 'am'){
      state.hourFrom = 6; state.hourTo = 11;
      if (UI.hourCustomRow) UI.hourCustomRow.style.display = 'none';
    } else if (preset === 'pm'){
      state.hourFrom = 12; state.hourTo = 17;
      if (UI.hourCustomRow) UI.hourCustomRow.style.display = 'none';
    } else if (preset === 'night'){
      state.hourFrom = 18; state.hourTo = 23;
      if (UI.hourCustomRow) UI.hourCustomRow.style.display = 'none';
    } else {
      if (UI.hourCustomRow) UI.hourCustomRow.style.display = '';
      state.hourFrom = Number(UI.hourFrom?.value || 0);
      state.hourTo   = Number(UI.hourTo?.value || 23);
    }
  }

  /* -----------------------------
     SECTION F: MAP INIT
     ----------------------------- */
  const cartoLight = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    subdomains: 'abcd',
    maxZoom: 20,
    attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
  });

  const osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  });

  const map = L.map('biMap', { zoomControl: true })
    .setView([MAP_CENTER.lat, MAP_CENTER.lng], MAP_CENTER.zoom || 12);

  cartoLight.addTo(map);
  L.control.scale({ imperial:false }).addTo(map);

  // ✅ Auto-fit se desactiva si el usuario ya movió el mapa (para evitar brincos)
  let userViewportLocked = false;
  let internalMove = false;

  map.on('dragstart', () => { if (!internalMove) userViewportLocked = true; });
  map.on('zoomstart', () => { if (!internalMove) userViewportLocked = true; });

  function goTenantCity(animate=true){
    internalMove = true;
    map.setView([MAP_CENTER.lat, MAP_CENTER.lng], MAP_CENTER.zoom || 12, { animate });
    setTimeout(()=> internalMove = false, 50);
  }

  const CityControl = L.Control.extend({
    options: { position: 'topleft' },
    onAdd: function(){
      const btn = L.DomUtil.create('button', 'btn btn-sm btn-outline-secondary');
      btn.type = 'button';
      btn.style.margin = '8px';
      btn.textContent = 'Ciudad';
      L.DomEvent.on(btn, 'click', (e) => {
        L.DomEvent.stopPropagation(e);
        L.DomEvent.preventDefault(e);
        userViewportLocked = false; // permitir auto-fit de nuevo si quieres
        goTenantCity(true);
      });
      return btn;
    }
  });
  map.addControl(new CityControl());

  /* -----------------------------
     SECTION F2: OVERLAY LAYERS
     ----------------------------- */
  const heatLayer = L.heatLayer([], {
    max: 1.0,
    minOpacity: 0.10,
    radius: 28,
    blur: 20,
    maxZoom: 17,
    gradient: {
      0.00: '#3b82f6',
      0.35: '#22c55e',
      0.70: '#f59e0b',
      1.00: '#ef4444',
    }
  }).addTo(map);

  const hotspotsLayer = L.layerGroup().addTo(map);
  const cellsLayer = L.layerGroup().addTo(map);
  const pointsLayer = L.layerGroup().addTo(map);

  const overlays = {
    'Heat': heatLayer,
    'Hotspots': hotspotsLayer,
    'Celdas': cellsLayer,
    'Puntos': pointsLayer,
  };

  L.control.layers(
    { 'CARTO Light': cartoLight, 'OSM': osm },
    overlays,
    { collapsed: true }
  ).addTo(map);

  /* -----------------------------
     SECTION G: HEAT STYLE (CONSISTENT WITH ZOOM)
     ----------------------------- */
  function smoothBase(level){
    if (level === 'low')  return { radius: 18, blur: 14 };
    if (level === 'high') return { radius: 42, blur: 28 };
    return { radius: 28, blur: 20 };
  }

  function adaptiveHeatOptions(){
    const z = map.getZoom();
    const g = state.grid;

    const base = smoothBase(state.smooth);

    // ✅ cambios suaves (menos “saltos”)
    const zoomFactor = clamp(1.10 - (z - 12) * 0.06, 0.72, 1.25);

    const gridFactor =
      (g >= 5) ? 0.72 :
      (g === 4) ? 0.85 :
      (g === 3) ? 1.00 :
      1.12;

    const radius = Math.round(base.radius * zoomFactor * gridFactor);
    const blur   = Math.round(base.blur   * zoomFactor * gridFactor);

    return {
      radius: clamp(radius, 10, 60),
      blur: clamp(blur, 8, 40),
      max: 1.0,
      minOpacity: 0.10,
      maxZoom: 17
    };
  }

  function applyHeatCanvasOpacity(){
    const canv = document.querySelector('#biMap canvas.leaflet-heatmap-layer');
    if (canv) canv.style.opacity = String(state.opacity);
  }

  function applyHeatStyle(){
    heatLayer.setOptions(adaptiveHeatOptions());
    applyHeatCanvasOpacity();
    heatLayer.redraw();
  }

  /* -----------------------------
     SECTION H: NORMALIZATION (ROBUST)
     ----------------------------- */
  function percentile(sortedVals, p){
    if (!sortedVals.length) return 1;
    const idx = (sortedVals.length - 1) * p;
    const lo = Math.floor(idx);
    const hi = Math.ceil(idx);
    if (lo === hi) return sortedVals[lo];
    const w = idx - lo;
    return sortedVals[lo] * (1 - w) + sortedVals[hi] * w;
  }

  function normalizePoints(pointsRaw){
    const weight = state.weight;

    let vals = pointsRaw.map(p => Number(p.v || 0)).filter(v => v > 0);

    let transform = 'linear';
    if (weight === 'amount'){
      transform = 'log';
      vals = vals.map(v => Math.log10(Math.max(1, v)));
    }

    vals.sort((a,b)=>a-b);

    const min = vals[0] ?? 0;
    const p95 = percentile(vals, 0.95) || 1;
    const max = vals[vals.length - 1] ?? 1;

    state.norm = { min, max, p95, transform };

    return pointsRaw.map(p => {
      let v = Number(p.v || 0);
      if (weight === 'amount') v = Math.log10(Math.max(1, v));
      const capped = Math.min(v, p95);
      const scaled = (p95 > 0) ? (capped / p95) : 0;
      return [Number(p.lat), Number(p.lng), clamp01(scaled)];
    });
  }

  /* -----------------------------
     SECTION H2: HEAT JITTER (DETERMINISTIC + CACHED)
     - Clave: el jitter ya NO se recalcula al cambiar de modo
       y se mantiene estable para la misma celda
     ----------------------------- */
  function xmur3(str) {
    let h = 1779033703 ^ str.length;
    for (let i = 0; i < str.length; i++) {
      h = Math.imul(h ^ str.charCodeAt(i), 3432918353);
      h = (h << 13) | (h >>> 19);
    }
    return function() {
      h = Math.imul(h ^ (h >>> 16), 2246822507);
      h = Math.imul(h ^ (h >>> 13), 3266489909);
      return (h ^= h >>> 16) >>> 0;
    };
  }

  function mulberry32(a) {
    return function() {
      let t = a += 0x6D2B79F5;
      t = Math.imul(t ^ (t >>> 15), t | 1);
      t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }

  // ✅ offsets cacheados por celda
  const heatOffsetsCache = new Map(); // key -> [{dLat,dLng}, ...]

  function jitterOffsetsForCell(key, n, half){
    let offsets = heatOffsetsCache.get(key);
    if (offsets && offsets.length === n) return offsets;

    const seedFn = xmur3(key);
    const rng = mulberry32(seedFn());

    // ✅ jitter MÁS PEQUEÑO para no “salirse” y que el heat coincida mejor con celdas/puntos
    const spread = half * 0.28;     // antes 0.95 => demasiado
    const lim = half * 0.42;        // clamp duro dentro de celda

    offsets = [];
    for (let k = 0; k < n; k++) {
      // triangular => más cerca del centro (menos “mar/bosque”)
      const triLat = (rng() + rng() - 1); // [-1..1]
      const triLng = (rng() + rng() - 1);
      let dLat = triLat * spread;
      let dLng = triLng * spread;
      dLat = clamp(dLat, -lim, lim);
      dLng = clamp(dLng, -lim, lim);
      offsets.push({ dLat, dLng });
    }

    heatOffsetsCache.set(key, offsets);
    return offsets;
  }

  function buildHeatLatLngsFromBuckets(pointsRaw, pointsScaled, gridDec) {
    if (!pointsRaw.length) return [];

    const cell = Math.pow(10, -gridDec);
    const half = cell / 2;

    const out = [];

    for (let i = 0; i < pointsRaw.length; i++) {
      const p = pointsRaw[i];
      const t = pointsScaled[i]?.[2] ?? 0;
      if (t <= 0) continue;

      // ✅ n estable y acotado (menos variación → menos sensación de “cambio”)
      const cnt = Number(p.cnt || 0);
      const n = clamp(Math.floor(Math.log2(cnt + 1) * 2) + 3, 3, 10);

      // ✅ key estable por celda (grid + centro celda)
      const key = `${gridDec}|${Number(p.lat).toFixed(6)}|${Number(p.lng).toFixed(6)}|${n}`;

      const offsets = jitterOffsetsForCell(key, n, half);
      const per = t / offsets.length;

      for (const o of offsets) {
        out.push([p.lat + o.dLat, p.lng + o.dLng, per]);
      }
    }

    return out;
  }

  /* -----------------------------
     SECTION I: RENDERERS (MODES)
     ----------------------------- */
  function clearOverlays(){
    hotspotsLayer.clearLayers();
    cellsLayer.clearLayers();
    pointsLayer.clearLayers();
  }

  function popupHtml(p, idxLabel){
    return `
      <div style="min-width:230px">
        <div style="font-weight:700;margin-bottom:4px">${idxLabel || 'Punto'}</div>
        <div>Servicios: <b>${fmtInt(p.cnt||0)}</b></div>
        <div>Ingresos: <b>${fmtMoney(p.amt||0)}</b></div>
        <div>Cancelados: <b>${fmtInt(p.canc||0)}</b></div>
        <div style="opacity:.75;margin-top:6px;font-size:.85em">
          (${Number(p.lat).toFixed(5)}, ${Number(p.lng).toFixed(5)})
        </div>
      </div>
    `;
  }

  function rampRGBA(t, a){
    const stops = [
      { t:0.00, c:[59,130,246] },
      { t:0.35, c:[34,197,94] },
      { t:0.70, c:[245,158,11] },
      { t:1.00, c:[239,68,68] },
    ];
    const x = clamp01(t);

    let s0 = stops[0], s1 = stops[stops.length-1];
    for (let i=0; i<stops.length-1; i++){
      if (x >= stops[i].t && x <= stops[i+1].t){
        s0 = stops[i]; s1 = stops[i+1]; break;
      }
    }
    const span = (s1.t - s0.t) || 1;
    const k = (x - s0.t) / span;

    const r = Math.round(s0.c[0] + (s1.c[0]-s0.c[0])*k);
    const g = Math.round(s0.c[1] + (s1.c[1]-s0.c[1])*k);
    const b = Math.round(s0.c[2] + (s1.c[2]-s0.c[2])*k);
    return `rgba(${r},${g},${b},${a})`;
  }

  // ✅ radios suaves por zoom (sin saltos bruscos)
  function zoomScale(z){
    // z=11..17 -> 0.85..1.25 aprox
    return clamp(0.70 + (z - 10) * 0.08, 0.80, 1.25);
  }

  function renderHotspots(){
    hotspotsLayer.clearLayers();
    const z = map.getZoom();
    const zs = zoomScale(z);

    const top = [...state.lastPointsRaw]
      .sort((a,b)=> (b.v||0)-(a.v||0))
      .slice(0, 30);

    top.forEach((p, idx) => {
      const rank = idx + 1;

      // ✅ pequeños y consistentes con zoom
      const base =
        (rank <= 3) ? 7 :
        (rank <= 10) ? 5 :
        4;

      const r = clamp(Math.round(base * zs), 3, 12);

      const marker = L.circleMarker([p.lat, p.lng], {
        radius: r,
        weight: 1,
        color: 'rgba(255,255,255,.75)',
        fillColor: 'rgba(239,68,68,.85)',
        fillOpacity: 0.85
      });

      marker.bindPopup(popupHtml(p, `Hotspot #${rank}`));
      marker.addTo(hotspotsLayer);
    });
  }

  function renderCells(){
    cellsLayer.clearLayers();

    const dec = state.grid;
    const cell = Math.pow(10, -dec);
    const half = cell / 2;

    const z = map.getZoom();
    const strokeW = clamp(1.1 - (z - 12) * 0.10, 0.55, 1.05);

    const scaled = state.lastPointsScaled;

    state.lastPointsRaw.forEach((p, i) => {
      const t = scaled[i]?.[2] ?? 0;

      const bounds = [
        [p.lat - half, p.lng - half],
        [p.lat + half, p.lng + half],
      ];

      const rect = L.rectangle(bounds, {
        weight: strokeW,
        color: 'rgba(0,0,0,.10)',
        fillColor: rampRGBA(t, 0.55),
        fillOpacity: 0.70
      });

      rect.bindPopup(popupHtml(p, 'Celda'));
      rect.addTo(cellsLayer);
    });
  }

  function renderPoints(){
    pointsLayer.clearLayers();

    const z = map.getZoom();
    const zs = zoomScale(z);

    const scaled = state.lastPointsScaled;

    state.lastPointsRaw.forEach((p, i) => {
      const t = scaled[i]?.[2] ?? 0;
      if (t <= 0) return;

      // ✅ radio suave por zoom e intensidad (sin brincos)
      const base = 3.2 * zs;
      const radius = clamp(Math.round(base * (0.75 + 0.85 * t)), 2, 14);

      const m = L.circleMarker([p.lat, p.lng], {
        radius,
        weight: 0.8,
        color: 'rgba(255,255,255,.25)',
        fillColor: rampRGBA(t, 0.80),
        fillOpacity: 0.80
      });

      m.bindPopup(popupHtml(p, 'Punto'));
      m.addTo(pointsLayer);
    });
  }

  function setVizMode(){
    // ✅ NO tocar el viewport aquí. Solo apagar/encender capas.
    clearOverlays();

    if (state.viz === 'heat'){
      if (!map.hasLayer(heatLayer)) heatLayer.addTo(map);
    } else {
      if (map.hasLayer(heatLayer)) map.removeLayer(heatLayer);
    }

    if (state.viz === 'hotspots') renderHotspots();
    if (state.viz === 'cells') renderCells();
    if (state.viz === 'points') renderPoints();

    applyHeatStyle();
    updateLegend();
  }

  // ✅ Al cambiar zoom, re-render de lo que depende del zoom (sin mover datos)
  map.on('zoomend', () => {
    applyHeatStyle();
    if (state.viz === 'points') renderPoints();
    if (state.viz === 'cells') renderCells();
    if (state.viz === 'hotspots') renderHotspots();
  });

  /* -----------------------------
     SECTION I2: SAFE AUTO-FIT (NO OUTLIERS)
     ----------------------------- */
  function trimmedBoundsFromPoints(pointsRaw, trimP = 0.02) {
    if (!pointsRaw.length) return null;

    const lats = pointsRaw.map(p => p.lat).slice().sort((a,b)=>a-b);
    const lngs = pointsRaw.map(p => p.lng).slice().sort((a,b)=>a-b);

    const q = (arr, p) => {
      const idx = (arr.length - 1) * p;
      const lo = Math.floor(idx), hi = Math.ceil(idx);
      if (lo === hi) return arr[lo];
      const w = idx - lo;
      return arr[lo]*(1-w) + arr[hi]*w;
    };

    const latMin = q(lats, trimP);
    const latMax = q(lats, 1 - trimP);
    const lngMin = q(lngs, trimP);
    const lngMax = q(lngs, 1 - trimP);

    if (![latMin,latMax,lngMin,lngMax].every(Number.isFinite)) return null;
    return L.latLngBounds([[latMin, lngMin],[latMax, lngMax]]);
  }

  function doAutofit(){
    if (!state.autofit) return;
    if (userViewportLocked) return; // ✅ si el usuario ya movió, no brinca

    if (!state.lastPointsRaw.length){
      goTenantCity(false);
      return;
    }

    const b = trimmedBoundsFromPoints(state.lastPointsRaw, 0.02);
    if (b && b.isValid()){
      internalMove = true;
      map.fitBounds(b.pad(0.12), { maxZoom: 13 });
      if (map.getZoom() < 11) map.setZoom(11);
      setTimeout(()=> internalMove = false, 60);
    } else {
      goTenantCity(false);
    }
  }

  /* -----------------------------
     SECTION J: LEGEND / META
     ----------------------------- */
  function legendText(){
    if (state.weight === 'amount')   return { t:'Modo: Dinero', s:'Intensidad = log10(monto) cap p95 (robusto)' };
    if (state.weight === 'canceled') return { t:'Modo: Cancelaciones', s:'Intensidad = cancelaciones cap p95' };
    return { t:'Modo: Servicios', s:'Intensidad = conteo cap p95' };
  }

  function updateLegend(){
    if (!UI.legTitle) return;

    const { t, s } = legendText();
    UI.legTitle.textContent = t;
    UI.legSubtitle.textContent = s;

    const dec = state.grid;
    UI.legGrid.textContent = `Grid ${dec} dec (${approxGridMeters(dec)}) · Viz: ${state.viz}`;

    if (!state.lastPointsRaw.length){
      UI.legMeta.textContent = 'Sin datos en el rango seleccionado.';
      UI.legNotes.textContent = '';
      return;
    }

    const rawVals = state.lastPointsRaw.map(p => Number(p.v||0)).filter(v=>v>0);
    const rawMin = Math.min(...rawVals);
    const rawMax = Math.max(...rawVals);

    let rangeTxt = '';
    if (state.weight === 'amount'){
      rangeTxt = `Rango celda (raw): ${fmtMoney(rawMin)} → ${fmtMoney(rawMax)} · Normalización: log + p95`;
    } else if (state.weight === 'canceled'){
      rangeTxt = `Rango celda (raw): ${rawMin} → ${rawMax} cancelaciones · Cap: p95=${state.norm.p95.toFixed(2)}`;
    } else {
      rangeTxt = `Rango celda (raw): ${rawMin} → ${rawMax} servicios · Cap: p95=${state.norm.p95.toFixed(2)}`;
    }
    UI.legMeta.textContent = rangeTxt;

    UI.legNotes.textContent = state.autofit
      ? 'Auto-fit activo: ajusta bounds al cargar (hasta que muevas el mapa).'
      : 'Auto-fit apagado: conserva tu vista al refrescar.';
  }

  /* -----------------------------
     SECTION K: API LOAD
     ----------------------------- */
  async function load(){
    toggleLoading(true);
    disableApply(true);

    try{
      if (state.hourPreset === 'custom'){
        state.hourFrom = Number(UI.hourFrom?.value || 0);
        state.hourTo   = Number(UI.hourTo?.value || 23);
      }

      const params = new URLSearchParams({
        start_date: UI.start.value,
        end_date: UI.end.value,
        hour_from: String(state.hourFrom),
        hour_to: String(state.hourTo),
        channel: state.channel,
        status: state.status,
        weight: state.weight,
        grid: String(state.grid),
      });

      const res = await fetch(API_URL + '?' + params.toString(), { headers: { 'Accept':'application/json' } });
      if (!res.ok) throw new Error('heat api error ' + res.status);

      const data = await res.json();

      // KPIs
      const k = data.kpis || {};
      if (UI.kpiRides) UI.kpiRides.textContent = fmtInt(k.rides ?? 0);
      if (UI.kpiCanceled) UI.kpiCanceled.textContent = fmtInt(k.canceled ?? 0);
      if (UI.kpiIncome) UI.kpiIncome.textContent = fmtMoney(k.income ?? 0);
      if (UI.kpiAvg) UI.kpiAvg.textContent = fmtMoney(k.avg_ticket ?? 0);

      // KPI subtexts
      if (UI.kpiRidesSub) UI.kpiRidesSub.textContent = `${UI.start.value} → ${UI.end.value}`;
      if (UI.kpiCanceledSub) UI.kpiCanceledSub.textContent = `Canal: ${state.channel} · Estado: ${state.status}`;
      if (UI.kpiIncomeSub) UI.kpiIncomeSub.textContent = `Peso: ${state.weight} · Grid: ${state.grid}`;
      if (UI.kpiAvgSub) UI.kpiAvgSub.textContent = `Horario: ${pad(state.hourFrom)}-${pad(state.hourTo)}`;

      // Points
      state.lastMeta = data.meta || {};
      state.lastPointsRaw = (data.points || []).map(p => ({
        lat: Number(p.lat), lng: Number(p.lng),
        v: Number(p.v||0),
        cnt: Number(p.cnt||0),
        amt: Number(p.amt||0),
        canc: Number(p.canc||0)
      }));

      // Hotspot principal
      state.lastHotspot = null;
      if (state.lastPointsRaw.length){
        state.lastHotspot = [...state.lastPointsRaw].sort((a,b)=> (b.v||0)-(a.v||0))[0];
      }

      // Normalize to 0..1
      state.lastPointsScaled = normalizePoints(state.lastPointsRaw);

      // Heat (stable)
      const heatLatLngs = buildHeatLatLngsFromBuckets(state.lastPointsRaw, state.lastPointsScaled, state.grid);
      heatLayer.setLatLngs(heatLatLngs);

      // Fit (solo si no moviste el mapa)
      doAutofit();

      // Render viz
      applyHeatStyle();
      setVizMode();

    } catch (err){
      console.error(err);
      goTenantCity(false);
    } finally {
      disableApply(false);
      toggleLoading(false);
    }
  }

  /* -----------------------------
     SECTION L: INIT DEFAULTS (30 DAYS)
     ----------------------------- */
  function initHoursSelects(){
    if (!UI.hourFrom || !UI.hourTo) return;
    UI.hourFrom.innerHTML = '';
    UI.hourTo.innerHTML = '';
    for (let h=0; h<=23; h++) {
      const opt1 = document.createElement('option');
      opt1.value = String(h); opt1.textContent = pad(h)+":00";
      UI.hourFrom.appendChild(opt1);

      const opt2 = document.createElement('option');
      opt2.value = String(h); opt2.textContent = pad(h)+":00";
      UI.hourTo.appendChild(opt2);
    }
    UI.hourFrom.value = "0";
    UI.hourTo.value = "23";
  }

  function initDatesDefault(days){
    const dEnd = new Date(today);
    const dStart = new Date(today);
    dStart.setDate(dStart.getDate() - (days - 1));
    if (UI.start) UI.start.value = toYmd(dStart);
    if (UI.end) UI.end.value = toYmd(dEnd);
  }

  function init(){
    initHoursSelects();
    initDatesDefault(30);
    syncGridHint();

    state.opacity = Number(UI.opacity?.value || 80) / 100;
    applyHourPreset('all');

    const setGroup = (g, val)=> {
      if (!g) return;
      g.querySelectorAll('button').forEach(b => b.classList.toggle('active', b.getAttribute('data-val') === val));
    };
    setGroup(UI.channelGroup, 'all');
    setGroup(UI.statusGroup, 'all');
    setGroup(UI.weightGroup, 'count');
    setGroup(UI.gridGroup, '3');
    setGroup(UI.vizGroup, 'heat');
    setGroup(UI.smoothGroup, 'med');

    if (UI.btnAutofit) UI.btnAutofit.textContent = 'Auto-fit: ON';

    load();
  }

  /* -----------------------------
     SECTION M: EVENTS
     ----------------------------- */
  if (UI.btnApply) UI.btnApply.addEventListener('click', (e)=>{ e.preventDefault(); load(); });
  if (UI.btnApply2) UI.btnApply2.addEventListener('click', (e)=>{ e.preventDefault(); load(); });

  if (UI.btnReset) UI.btnReset.addEventListener('click', (e)=>{
    e.preventDefault();

    state.channel = 'all';
    state.status = 'all';
    state.weight = 'count';
    state.grid = 3;
    state.viz = 'heat';
    state.smooth = 'med';
    state.opacity = 0.80;
    state.autofit = true;

    userViewportLocked = false; // ✅ reset también resetea lock de viewport

    initDatesDefault(30);
    if (UI.opacity) UI.opacity.value = "80";

    applyHourPreset('all');
    if (UI.hourFrom) UI.hourFrom.value = "0";
    if (UI.hourTo) UI.hourTo.value = "23";
    if (UI.hourCustomRow) UI.hourCustomRow.style.display = 'none';

    const setGroup = (g, val)=> {
      if (!g) return;
      g.querySelectorAll('button').forEach(b => b.classList.toggle('active', b.getAttribute('data-val') === val));
    };
    setGroup(UI.channelGroup, 'all');
    setGroup(UI.statusGroup, 'all');
    setGroup(UI.weightGroup, 'count');
    setGroup(UI.gridGroup, '3');
    setGroup(UI.vizGroup, 'heat');
    setGroup(UI.smoothGroup, 'med');

    if (UI.btnAutofit) UI.btnAutofit.textContent = 'Auto-fit: ON';

    syncGridHint();
    goTenantCity(false);
    load();
  });

  if (UI.btnHotspot) UI.btnHotspot.addEventListener('click', (e)=>{
    e.preventDefault();
    if (!state.lastHotspot) return;
    map.flyTo([state.lastHotspot.lat, state.lastHotspot.lng], Math.max(map.getZoom(), 15), { duration: 0.85 });
  });

  if (UI.btnAutofit) UI.btnAutofit.addEventListener('click', (e)=>{
    e.preventDefault();
    state.autofit = !state.autofit;
    UI.btnAutofit.textContent = state.autofit ? 'Auto-fit: ON' : 'Auto-fit: OFF';
    updateLegend();
    if (state.autofit) doAutofit();
  });

  if (UI.rangeBtns) UI.rangeBtns.forEach(btn => {
    btn.addEventListener('click', (e)=>{
      e.preventDefault();
      UI.rangeBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const days = Number(btn.getAttribute('data-range') || 30);
      initDatesDefault(days);
      load();
    });
  });

  if (UI.hourPresets) UI.hourPresets.querySelectorAll('button').forEach(btn => {
    btn.addEventListener('click', (e)=>{
      e.preventDefault();
      setActive(UI.hourPresets, btn);
      const preset = btn.getAttribute('data-hr');
      applyHourPreset(preset);
      load();
    });
  });

  if (UI.hourFrom) UI.hourFrom.addEventListener('change', ()=>{ if (state.hourPreset === 'custom') load(); });
  if (UI.hourTo) UI.hourTo.addEventListener('change', ()=>{ if (state.hourPreset === 'custom') load(); });

  function wireGroup(groupEl, onPick){
    if (!groupEl) return;
    groupEl.querySelectorAll('button').forEach(btn => {
      btn.addEventListener('click', (e)=>{
        e.preventDefault();
        setActive(groupEl, btn);
        onPick(btn.getAttribute('data-val'));
      });
    });
  }

  wireGroup(UI.channelGroup, (v)=>{ state.channel = v || 'all'; load(); });
  wireGroup(UI.statusGroup, (v)=>{ state.status = v || 'all'; load(); });
  wireGroup(UI.weightGroup, (v)=>{ state.weight = v || 'count'; load(); });

  wireGroup(UI.gridGroup, (v)=>{
    state.grid = Number(v || 3);
    syncGridHint();
    load();
  });

  wireGroup(UI.vizGroup, (v)=>{
    state.viz = v || 'heat';
    setVizMode();
  });

  wireGroup(UI.smoothGroup, (v)=>{
    state.smooth = v || 'med';
    applyHeatStyle();
  });

  if (UI.opacity) UI.opacity.addEventListener('input', ()=>{
    state.opacity = Number(UI.opacity.value || 80) / 100;
    applyHeatCanvasOpacity();
    if (state.viz === 'cells') renderCells();
    if (state.viz === 'points') renderPoints();
    if (state.viz === 'hotspots') renderHotspots();
  });

  /* -----------------------------
     SECTION N: BOOT
     ----------------------------- */
  init();

})();
</script>
@endpush
