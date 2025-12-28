 
<?php $__env->startSection('title','Dispatch'); ?>

<?php $__env->startSection('content'); ?>
<div class="dispatch-grid">

  
  <aside class="dispatch-left">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <h6 class="mb-0">Nuevo viaje</h6>
    <div class="btn-group">
      <button class="btn btn-sm btn-outline-secondary" id="btnReset"   title="Limpiar"><i data-feather="rotate-ccw"></i></button>
      <button class="btn btn-sm btn-outline-primary"  id="btnDuplicate" title="Duplicar"><i data-feather="copy"></i></button>
    </div>
  </div>

  
  <div class="mb-2">
    <label class="form-label">Origen</label>
    <div class="input-group">
      <input id="inFrom" class="form-control" placeholder="Calle, número...">
      <button class="btn btn-outline-secondary" title="Elegir en mapa" id="btnPickFrom"><i data-feather="map-pin"></i></button>
    </div>
    <input type="hidden" id="fromLat"><input type="hidden" id="fromLng">
  </div>

  
  <div class="mb-2">
    <label class="form-label">Destino</label>
    <div class="input-group">
      <input id="inTo" class="form-control" placeholder="Calle, número...">
      <button class="btn btn-outline-secondary" title="Elegir en mapa" id="btnPickTo"><i data-feather="map-pin"></i></button>
    </div>
    <input type="hidden" id="toLat"><input type="hidden" id="toLng">
  </div>

 <!-- Botón “+ parada” -->
<div class="d-flex align-items-center gap-2 mt-2">
  <button class="btn btn-sm btn-outline-secondary" id="btnAddStop1" type="button" title="Agregar parada">
    <i data-feather="plus"></i> Parada
  </button>
  <small class="text-muted">máx. 2</small>
</div>

<!-- Stop 1 (oculto al inicio) -->
<div id="stop1Row" class="mt-2" style="display:none">
  <label class="form-label">Parada 1</label>
  <div class="input-group">
    <input id="inStop1" class="form-control" placeholder="Calle, número...">
    <button class="btn btn-outline-secondary" title="Elegir en mapa" id="btnPickStop1"><i data-feather="map-pin"></i></button>
    <button class="btn btn-outline-danger" title="Quitar" id="btnClearStop1"><i data-feather="x"></i></button>
  </div>
  <input type="hidden" id="stop1Lat"><input type="hidden" id="stop1Lng">
</div>

<!-- Stop 2 (oculto; solo se muestra si Stop 1 existe) -->
<div id="stop2Row" class="mt-2" style="display:none">
  <label class="form-label">Parada 2</label>
  <div class="input-group">
    <input id="inStop2" class="form-control" placeholder="Calle, número...">
    <button class="btn btn-outline-secondary" title="Elegir en mapa" id="btnPickStop2"><i data-feather="map-pin"></i></button>
    <button class="btn btn-outline-danger" title="Quitar" id="btnClearStop2"><i data-feather="x"></i></button>
  </div>
  <input type="hidden" id="stop2Lat"><input type="hidden" id="stop2Lng">
</div>



  <div class="small text-muted mb-2" id="routeSummary">
    Ruta: — · Zona: — · Cuando: ahora
  </div>

  
  <div class="mb-2">
    <div class="form-check form-check-inline">
      <input class="form-check-input" type="radio" name="when" id="when-now" checked>
      <label class="form-check-label" for="when-now">Ahora</label>
    </div>
    <div class="form-check form-check-inline">
      <input class="form-check-input" type="radio" name="when" id="when-later">
      <label class="form-check-label" for="when-later">Después</label>
    </div>
  </div>

  
  <div id="scheduleRow" class="row g-2 mb-2" style="display:none">
    <div class="col-12">
      <label class="form-label small">Programar para</label>
      <input id="scheduleAt" class="form-control form-control-sm" type="datetime-local">
      <div class="form-text">Fecha y hora de inicio del servicio.</div>
    </div>
  </div>

  <h6 class="mt-3">Pasajeros</h6>
  <div class="row g-2">
    <div class="col-6">
      <label class="form-label small">Nombre</label>
      <input class="form-control form-control-sm" id="pass-name">
    </div>
    <div class="col-6">
      <label class="form-label small">Teléfono</label>
      <input class="form-control form-control-sm" id="pass-phone" placeholder="Buscar últimos viajes">
    </div>
  </div>

  <div class="row g-2 mt-2">
    <div class="col-6">
      <label class="form-label small">Método de pago</label>
      <select class="form-select form-select-sm" id="pay-method">
        <option value="cash" selected>Efectivo</option>
        <option value="transfer">Transferencia</option>
        <option value="card">Tarjeta</option>
        <option value="corp">Cuenta</option>
      </select>
    </div>
    <div class="col-6">
      <label class="form-label small">Cuenta</label>
      <input class="form-control form-control-sm" id="pass-account" placeholder="ID / nombre de cuenta">
    </div>
  </div>

  
  <div class="mt-2">
    <label class="form-label small">Notas</label>
    <textarea id="ride-notes" class="form-control" rows="3" placeholder="Discapacidad, mascota, referencia, torre, etc."></textarea>
    <div class="form-text">Puedes escribir libre o usar #etiquetas (ej: #vip #empresa).</div>
  </div>

  <h6 class="mt-3">Info del viaje</h6>
  <div class="row g-2">
    <div class="col-4">
      <label class="form-label small"># pax</label>
      <input id="pax" type="number" min="1" value="1" class="form-control form-control-sm">
    </div>
    <div class="col-4">
      <label class="form-label small">Vehículo</label>
      <select id="vehKind" class="form-select form-select-sm">
        <option>Sedán</option><option>Van</option><option>Mototaxi</option>
      </select>
    </div>
    <div class="col-4">
      <label class="form-label small">Tarifa sugerida</label>
      <div class="input-group input-group-sm">
        <span class="input-group-text">$</span>
        <input id="fareAmount" class="form-control" inputmode="numeric" pattern="[0-9]*" placeholder="0"/>
      </div>
    </div>
  </div>

  
  <div class="row g-2 mt-3">
    <div class="col-4 d-grid">
      <button class="btn btn-outline-danger btn-square" id="btnClear"><i data-feather="trash-2"></i> Limpiar</button>
    </div>
    <div class="col-4 d-grid">
      <button class="btn btn-outline-primary btn-square" id="btnQuote"><i data-feather="dollar-sign"></i> Cotizar</button>
    </div>
    <div class="col-4 d-grid">
      <button type="button" class="btn btn-success btn-square" id="btnCreate"><i data-feather="check-circle"></i> Crear</button>
    </div>
  </div>



  <hr>
  <h6>Capas</h6>
  <div class="form-check"><input class="form-check-input" id="toggle-sectores" type="checkbox" checked><label class="form-check-label" for="toggle-sectores">Sectores</label></div>
  <div class="form-check"><input class="form-check-input" id="toggle-stands" type="checkbox" checked><label class="form-check-label" for="toggle-stands">Paraderos</label></div>
  <div class="form-check"><input class="form-check-input" id="toggle-drivers" type="checkbox" checked><label class="form-check-label" for="toggle-drivers">Conductores</label></div>
</aside>


  
  <section class="dispatch-map"><div id="map"></div></section>

  


  <!-- Panel lateral para asignación -->
<div id="assignPanel" class="offcanvas offcanvas-end" data-bs-backdrop="false" tabindex="-1">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title">Asignar conductor</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <div id="assignPanelBody">Cargando…</div>
    <div class="mt-3 d-flex justify-content-end">
      <button id="btnDoAssign" class="btn btn-primary" disabled>Asignar</button>
    </div>
  </div>
</div>

<!-- Panel lateral para CHAT con el driver -->
<div id="chatPanel" class="offcanvas offcanvas-end" data-bs-backdrop="false" tabindex="-1">
  <div class="offcanvas-header">
    <div>
      <h5 class="offcanvas-title">Chat con conductor</h5>
      <div class="small text-muted" id="chatPanelSubtitle">
        Selecciona un viaje con mensajes…
      </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>

  <div class="offcanvas-body d-flex flex-column">
    <div id="chatPanelInfo" class="small text-muted mb-2">
      <!-- aquí pondremos #viaje, nombre del conductor, teléfono, etc. -->
    </div>

    <div id="chatMessages"
         class="flex-grow-1 border rounded small p-2 mb-2 bg-body-tertiary overflow-auto">
      <!-- mensajes -->
    </div>

    <div id="chatQuickReplies"
         class="d-flex flex-wrap gap-1 mb-2">
      <!-- botones de respuesta rápida, los llenamos desde JS -->
    </div>

    <div class="input-group input-group-sm">
      <input type="text"
             id="chatInput"
             class="form-control"
             placeholder="Mensaje para el conductor…"
             maxlength="255">
      <button class="btn btn-primary" type="button" id="chatSendBtn">
        <i data-feather="send"></i>
      </button>
    </div>
  </div>
</div>


 <aside class="dispatch-right">

  
  <div class="queues-shell">

    
    <div class="queues-compact-card mb-2">
      <div class="d-flex align-items-center justify-content-between mb-1">
        <div class="d-flex align-items-center gap-2">
          <h6 class="mb-0">Colas por paradero</h6>
          <span class="badge bg-secondary" id="badgeColas">0</span>
        </div>
        <button class="btn btn-sm btn-outline-secondary" id="btnQueuesExpand">
          Ver detalle
        </button>
      </div>

      
      <div id="panel-queue-compact" class="queues-compact-list small text-muted">
        
        <span class="text-muted small">Sin información de colas.</span>
      </div>
    </div>

    
    <div class="queues-full-overlay" id="queuesOverlay">
      <div class="queues-full-header d-flex align-items-center justify-content-between mb-2">
        <div class="d-flex align-items-center gap-2">
          <h6 class="mb-0">Colas por paradero</h6>
          <span class="badge bg-secondary" id="badgeColasFull">0</span>
        </div>
        <button class="btn btn-sm btn-outline-secondary" id="btnQueuesClose">
          Cerrar
        </button>
      </div>

      <div class="queues-full-body">
        <div class="accordion" id="panel-queue">
          
        </div>
      </div>
    </div>

  </div>


<div class="drivers-accordion mb-3">
  <div class="drivers-accordion-header" id="driversAccordionHeader">
    <div class="d-flex align-items-center gap-2">
      <i data-feather="users" class="feather-sm"></i>
      <h6 class="mb-0">Conductores</h6>
      <span class="badge bg-primary" id="driversCountBadge">0</span>
    </div>
    <i data-feather="chevron-down" id="driversChevron" class="feather-sm"></i>
  </div>
  <div class="drivers-accordion-body" id="driversAccordionBody" style="display: none;">
    <div id="driversList" class="compact-drivers-list"></div>
  </div>
</div>

  
  <div class="d-flex align-items-center justify-content-between mb-2 mt-3">
    <h6 class="mb-0">Viajes activos</h6>
    <span class="badge bg-primary" id="badgeActivos">0</span>
  </div>

  <ul class="nav nav-pills nav-fill small mb-2" id="activeTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="tab-active-cards" data-bs-toggle="tab"
              data-bs-target="#pane-active-cards" type="button" role="tab">Ahora</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-active-grid" data-bs-toggle="tab"
              data-bs-target="#pane-active-grid" type="button" role="tab">Programados</button>
    </li>
  </ul>

  <div class="tab-content" id="activeTabsContent">
    <div class="tab-pane fade show active" id="pane-active-cards" role="tabpanel">
      <div id="panel-active" class="small"></div>
    </div>
    <div class="tab-pane fade" id="pane-active-grid" role="tabpanel">
      <div id="panel-active-scheduled"></div>
    </div>
  </div>

</aside>



<div id="dock-active" class="active-dock collapsed">
  <div class="dock-header">
  <div class="left">
    <strong>Viajes activos</strong>
    <span class="badge bg-primary ms-2" id="badgeActivosDock">0</span>
  </div>
  <div class="right">
    <button class="btn btn-sm btn-outline-secondary" id="btnDockToggle">
      <span class="when-collapsed">Expandir</span>
      <span class="when-expanded">Colapsar</span>
    </button>
  </div>
</div>

  <div class="dock-body">
    <div id="dock-active-table" class="small"></div>
  </div>
</div>


</div>
<?php $__env->stopSection(); ?>
<?php echo app('Illuminate\Foundation\Vite')('resources/js/app.js'); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/confirmDate/confirmDate.min.css">

<style>

  .queue-eco-grid{
  display:flex;
  flex-wrap:wrap;
  gap:6px;
}

.eco-btn{
  appearance:none;
  border:1px solid rgba(255,255,255,.14);
  background: rgba(255,255,255,.06);
  color: inherit;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 12px;
  line-height: 1;
  cursor: pointer;
  user-select:none;
}

.eco-btn:hover{
  background: rgba(255,255,255,.10);
}

[data-theme="light"] .eco-btn{
  border-color: rgba(0,0,0,.12);
  background: rgba(0,0,0,.04);
}
[data-theme="light"] .eco-btn:hover{
  background: rgba(0,0,0,.06);
}

/* ===== ACORDEÓN COMPACTO ===== */
.drivers-accordion {
  border-radius: 8px;
  border: 1px solid var(--bs-border-color);
  overflow: hidden;
  background: var(--bs-body-bg);
  transition: border-color 0.2s ease;
}

.drivers-accordion-header {
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--bs-tertiary-bg);
  border-bottom: 1px solid transparent;
  transition: background-color 0.2s ease;
}

.drivers-accordion-header:hover {
  background: var(--bs-secondary-bg);
}

.drivers-accordion-header h6 {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--bs-heading-color);
}

.drivers-accordion-body {
  padding: 0.5rem;
  background: var(--bs-body-bg);
  max-height: 400px;
  overflow-y: auto;
  border-top: 1px solid var(--bs-border-color);
  transition: background-color 0.2s ease, border-color 0.2s ease;
}

/* ===== LISTA COMPACTA DE CONDUCTORES ===== */
.compact-drivers-list {
  font-size: 0.8rem;
}

.driver-card-compact {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.4rem 0.5rem;
  border-radius: 6px;
  border: 1px solid var(--bs-border-color);
  margin-bottom: 0.3rem;
  background: var(--bs-card-bg, var(--bs-body-bg));
  transition: all 0.15s ease;
}

.driver-card-compact:hover {
  border-color: var(--bs-border-color-translucent);
  background: var(--bs-secondary-bg);
}

.driver-card-compact:last-child {
  margin-bottom: 0;
}

.driver-info-compact {
  flex: 1;
  min-width: 0;
}

.driver-economico {
  font-weight: 600;
  color: var(--bs-emphasis-color);
  font-size: 0.85rem;
  margin-bottom: 0.1rem;
}

.driver-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--bs-secondary-color);
  font-size: 0.75rem;
}

/* ===== STATUS DOTS ===== */
.driver-status-dot {
  display: inline-block;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  margin-right: 0.25rem;
}

/* Light theme status dots */
.status-online { background-color: #28a745; }
.status-busy { background-color: #fd7e14; }
.status-offline { background-color: #6c757d; }
.status-inactive { background-color: #ffc107; }

/* Dark theme status dots */
html[data-theme="dark"] .status-online { background-color: #20c997; }
html[data-theme="dark"] .status-busy { background-color: #ff922b; }
html[data-theme="dark"] .status-offline { background-color: #adb5bd; }
html[data-theme="dark"] .status-inactive { background-color: #ffd43b; }

/* ===== INDICATOR BADGES ===== */
.indicator-badge {
  font-size: 0.65rem;
  padding: 0.1rem 0.3rem;
  border-radius: 4px;
  font-weight: 500;
  transition: all 0.2s ease;
}

/* Light theme badges */
.indicator-badge.in-base {
  background: #e7f5ff;
  color: #0d6efd;
  border: 1px solid #cfe2ff;
}

.indicator-badge.on-trip {
  background: #fff3cd;
  color: #856404;
  border: 1px solid #ffeaa7;
}

.indicator-badge.available {
  background: #d1e7dd;
  color: #0f5132;
  border: 1px solid #badbcc;
}

/* Dark theme badges */
html[data-theme="dark"] .indicator-badge.in-base {
  background: rgba(13, 110, 253, 0.2);
  color: #6ea8fe;
  border: 1px solid rgba(13, 110, 253, 0.3);
}

html[data-theme="dark"] .indicator-badge.on-trip {
  background: rgba(255, 193, 7, 0.2);
  color: #ffda6a;
  border: 1px solid rgba(255, 193, 7, 0.3);
}

html[data-theme="dark"] .indicator-badge.available {
  background: rgba(25, 135, 84, 0.2);
  color: #75b798;
  border: 1px solid rgba(25, 135, 84, 0.3);
}

/* ===== BOTONES ===== */
.btn-focus-driver-compact {
  padding: 0.2rem 0.5rem;
  font-size: 0.7rem;
  border-radius: 4px;
  min-width: 50px;
  transition: all 0.2s ease;
}

.btn-focus-driver-compact:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Light theme buttons */
.btn-focus-driver-compact.btn-outline-primary {
  border-color: #0d6efd;
  color: #0d6efd;
}

.btn-focus-driver-compact.btn-outline-primary:hover:not(:disabled) {
  background: #0d6efd;
  color: white;
}

.btn-focus-driver-compact.btn-outline-secondary {
  border-color: #6c757d;
  color: #6c757d;
}

/* Dark theme buttons */
html[data-theme="dark"] .btn-focus-driver-compact.btn-outline-primary {
  border-color: #6ea8fe;
  color: #6ea8fe;
}

html[data-theme="dark"] .btn-focus-driver-compact.btn-outline-primary:hover:not(:disabled) {
  background: #6ea8fe;
  color: #212529;
}

html[data-theme="dark"] .btn-focus-driver-compact.btn-outline-secondary {
  border-color: #adb5bd;
  color: #adb5bd;
}

/* ===== GRUPOS DE CONDUCTORES ===== */
.drivers-group-title {
  font-size: 0.75rem;
  color: var(--bs-secondary-color);
  text-transform: uppercase;
  font-weight: 600;
  margin: 0.5rem 0 0.25rem 0;
  padding-left: 0.25rem;
  transition: color 0.2s ease;
}

.drivers-group-title:first-child {
  margin-top: 0;
}

/* ===== ESTADO SIN CONDUCTORES ===== */
.compact-drivers-list .text-center {
  color: var(--bs-secondary-color);
}

/* ===== SCROLLBAR ===== */
.drivers-accordion-body::-webkit-scrollbar {
  width: 6px;
}

.drivers-accordion-body::-webkit-scrollbar-track {
  background: var(--bs-secondary-bg);
  border-radius: 3px;
}

.drivers-accordion-body::-webkit-scrollbar-thumb {
  background: var(--bs-border-color);
  border-radius: 3px;
}

.drivers-accordion-body::-webkit-scrollbar-thumb:hover {
  background: var(--bs-border-color-translucent);
}

/* ===== BADGE DEL HEADER ===== */
.drivers-accordion-header .badge {
  font-size: 0.7rem;
  padding: 0.2rem 0.4rem;
}

/* ===== ICONOS ===== */
.drivers-accordion-header i[data-feather] {
  color: var(--bs-secondary-color);
  transition: color 0.2s ease;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
  .drivers-accordion {
    border-radius: 6px;
  }
  
  .driver-card-compact {
    padding: 0.35rem 0.4rem;
  }
  
  .driver-economico {
    font-size: 0.8rem;
  }
  
  .driver-meta {
    font-size: 0.7rem;
  }
}
</style>

<style>


body {
    overflow: hidden !important;
    height: 100vh;
}

.dispatch-grid {
    height: 100vh;
    overflow: hidden;
}

.dispatch-left, .dispatch-right {
    height: 100vh;
    overflow-y: auto;
}

.dispatch-map {
    height: 100vh;
    overflow: hidden;
}
  .queue-eco-grid .eco-btn{
  border: 1px solid rgba(255,255,255,.15);
  background: rgba(255,255,255,.06);
  color: inherit;
  padding: 4px 8px;
  border-radius: 8px;
  font-size: 12px;
  line-height: 1;
}
.queue-eco-grid .eco-btn:hover{
  background: rgba(255,255,255,.12);
}


.driver-bubble {
    display: inline-block;
    padding: 8px 12px;
    background: #edf2f7;
    border-radius: 10px;
    max-width: 75%;
}

.dispatch-bubble {
    display: inline-block;
    padding: 8px 12px;
    background: #2563eb;
    color: #fff;
    border-radius: 10px;
    max-width: 75%;
}

.chat-message .timestamp {
    font-size: 11px;
}

.cc-metrics-panel {
  background: var(--cc-card-bg);
  border: 1px solid var(--cc-border);
  border-radius: 12px;
  padding: 12px;
  margin-bottom: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.cc-metrics-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.cc-metrics-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.cc-metric-item {
  text-align: center;
  padding: 8px;
  border-radius: 8px;
  background: var(--cc-soft-bg);
  transition: all 0.3s ease;
}

.cc-metric-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.cc-metric-value {
  font-size: 1.2em;
  font-weight: bold;
  color: var(--bs-primary);
}

.cc-metric-label {
  font-size: 0.75em;
  color: var(--cc-muted);
}
/* puntito/halo para programados */
.cc-dot-prog{
  display:inline-block; width:.55rem; height:.55rem; margin-right:.25rem;
  border-radius:999px; background:#dc3545; box-shadow:0 0 0 2px rgba(220,53,69,.15);
  vertical-align:middle;
}

/* borde lateral para la card programada */
.cc-ride-card.is-scheduled{ border-left:4px solid var(--bs-danger); }

  /* Dock inferior tipo consola */
#dock-active{
  position: fixed; left: 340px; right: 360px; bottom: 0;
  background:#fff; border-top:1px solid var(--bs-border-color);
  box-shadow:0 -8px 24px rgba(0,0,0,.08);
  transition: height .18s ease, transform .18s ease;
  z-index:600;
}

/* Pills de estado (Bootstrap 5) */
.badge-pill {
  border-radius:999px;
  font-weight:700;
  letter-spacing:.02em;
}

/* Colores por estado (usar con clases utilitarias) */
.badge-accepted { background:#e7f1ff; color:#0d6efd; }
.badge-enroute  { background:#e7fff3; color:#198754; }   /* en_route */
.badge-arrived  { background:#fff3cd; color:#9c6f00; }
.badge-onboard  { background:#fde2e1; color:#b42318; }  


#dock-active.collapsed{ height:94px; overflow:hidden; }
#dock-active:not(.collapsed){ height:294px; }
#dock-active .dock-header{ display:flex; align-items:center; justify-content:space-between; padding:.5rem .75rem; background:#f8f9fa; }
#dock-active .dock-body{ height:calc(100% - 44px); overflow:auto; padding:.5rem; }
#dock-active.collapsed .when-expanded{ display:none; }
#dock-active:not(.collapsed) .when-collapsed{ display:none; }
/* responsive */
@media (max-width: 992px){ #dock-active{ left:0; right:0; } }

/* Badges/eco en paraderos */
.queue-eco-grid{
  display: flex; flex-wrap: wrap; gap: .35rem;
}
.queue-eco-grid .eco{
  display:inline-block;
  padding: .2rem .45rem;
  border-radius: .5rem;
  background: var(--bs-light);
  border: 1px solid var(--bs-border-color);
  font-weight: 600;
}

/* tabla compacta de activos */
#panel-active-grid table{ width:100%; }
#panel-active-grid th, #panel-active-grid td{
  padding:.35rem .5rem; font-size:.85rem; vertical-align:middle;
}
#panel-active-grid thead th{ position:sticky; top:0; background:var(--bs-body-bg); }

  .btn-square{ border-radius:.6rem; }

/* Mejor contraste cuando un item queda “active” en paneles/listas */
.list-group-item.active{
  background-color:#0d6efd;
  border-color:#0d6efd;
  color:#fff !important;
}
.list-group-item.active .text-muted{ color:#e9eef7 !important; }

/* Tarjetitas del panel izquierdo (opcionales) */
#left-active .cc-ride-card{ margin-bottom:.5rem; }
#left-active .cc-ride-header{ padding:.25rem .5rem; border-radius:.4rem .4rem 0 0; }



  .dispatch-wrapper { height: calc(100vh - 58px); }
  .dispatch-grid{position:relative;height:100%;display:grid;gap:0;grid-template-columns:340px 1fr 360px}
  .dispatch-left,
.dispatch-right{
  overflow:auto;
  padding:12px;
}
  .dispatch-left{border-right:1px solid var(--bs-border-color)}
  .dispatch-right{
  border-left:1px solid var(--bs-border-color);
  position: relative;          /* 👈 NUEVO: referencia del overlay */
}
  .dispatch-map{position:relative}
  .dispatch-map #map{position:absolute;inset:0}
  .dispatch-right { font-size: .95rem; }
.dispatch-right .card, .dispatch-right .table { font-size: inherit; }
  /* en tu CSS global o el del dispatch */
.leaflet-pane.routePane svg path.cc-route{
  stroke-linecap: round;
  stroke-linejoin: round;
}


  /* Dark para tiles OSM sin afectar marcadores / polyline */
  .map-dark .leaflet-tile{
    filter: invert(90%) hue-rotate(180deg) saturate(80%) brightness(90%);
  }

  /* Panes para orden de capas */
  .leaflet-pane.sectoresPane { z-index:350; }
  .leaflet-pane.routePane    { z-index:460; }  /* por encima de sectores */
  .leaflet-pane.markerPane   { z-index:470; }  /* marcadores arriba */

  /* No invertir la ruta en dark */
  svg path.cc-route { filter:none !important; }

  .sector-tip{font-weight:600}
  .stand-tip{font-size:.85rem}

/* Imagen del coche en divIcon con rotación */
.cc-car {
  position:absolute; left:0; top:0;
  width:52px; height:26px;           /* nítido en 1x; sube a 96x52 si tienes @2x */
  transform: translate(-50%,-50%);   /* centrado sobre lat/lng */
  transform-origin: 50% 50%;
  image-rendering: -webkit-optimize-contrast;
  image-rendering: crisp-edges;
}

.leaflet-tooltip.cc-tip{ background:#fff;color:#111;border:0;border-radius:10px;
  box-shadow:0 8px 24px rgba(0,0,0,.18);padding:8px 10px;}
.leaflet-tooltip.cc-tip:before{display:none;}
.cc-tip .tt-title{font-weight:700;margin-bottom:2px;}
.cc-tip .tt-sub{font-size:.86rem;color:#6b7280;}
.cc-tip .tt-meta{font-size:.82rem;color:#374151;margin-top:2px;}

/* Icono nítido y estable (no se desplaza ni palpita) */
.cc-car-box{
  position: relative;
  width: 48px;   /* mismo que CAR_W */
  height: 26px;  /* mismo que CAR_H */
}
.cc-car-img{
  position:absolute; left:0; top:0;
  width: 50px;   /* fija el raster, evita reflow */
  height: 44px;
  transform-origin: 50% 50%;
  backface-visibility: hidden;
  will-change: transform;
  image-rendering: -webkit-optimize-contrast;
  image-rendering: crisp-edges;
}


.cc-ride-card .cc-ride-header{
  padding:.35rem .5rem;
  border-top-left-radius:.5rem;
  border-top-right-radius:.5rem;
}
.cc-ride-card .card-body{ padding-top:.5rem; padding-bottom:.25rem; }
.cc-ride-card .card-footer{ background:transparent; border-top:0; }
.btn.btn-xs{ padding:.125rem .35rem; font-size:.75rem; line-height:1; }

/* extras de color si no existen en tu theme */
.bg-purple{ background:#6f42c1; }
.bg-indigo{ background:#6610f2; }
.bg-teal{   background:#20c997; }
.bg-orange{ background:#fd7e14; }


  @media (max-width: 992px){
    .dispatch-grid{grid-template-columns:1fr;grid-template-rows:300px 400px 300px}
    .dispatch-left{border-right:0;border-bottom:1px solid var(--bs-border-color)}
    .dispatch-right{border-left:0;border-top:1px solid var(--bs-border-color)}
  }



/* ===== Dock inferior - Modo Dark (usa html[data-theme="dark"]) ===== */
html[data-theme="dark"] #dock-active{
  background: var(--bs-body-bg) !important;         /* sobreescribe #fff */
  border-top-color: var(--bs-border-color);
  box-shadow: 0 -10px 28px rgba(0,0,0,.55);
}

html[data-theme="dark"] #dock-active .dock-header{
  background: var(--bs-tertiary-bg) !important;     /* sobreescribe #f8f9fa */
  color: var(--bs-body-color);
  border-bottom: 1px solid var(--bs-border-color);
}

html[data-theme="dark"] #dock-active .dock-body{
  background: var(--bs-body-bg);
  color: var(--bs-body-color);
}

/* Tabla en el dock */
html[data-theme="dark"] #dock-active table{
  color: var(--bs-body-color);
  border-color: var(--bs-border-color);
}
html[data-theme="dark"] #dock-active thead th{
  position: sticky; top: 0;
  background: var(--bs-dark);
  color: var(--bs-body-color);
  border-bottom: 1px solid var(--bs-border-color);
}
html[data-theme="dark"] #dock-active tbody tr{ border-color: var(--bs-border-color); }
html[data-theme="dark"] #dock-active tbody tr:hover{ background: rgba(255,255,255,.05); }

/* Badges de estado legibles en dark */
html[data-theme="dark"] #dock-active .badge.bg-primary-subtle{ color: var(--bs-primary) !important; }
html[data-theme="dark"] #dock-active .badge.bg-warning-subtle{ color: var(--bs-warning) !important; }
html[data-theme="dark"] #dock-active .badge.bg-success-subtle{  color: var(--bs-success) !important; }
html[data-theme="dark"] #dock-active .badge.bg-secondary-subtle{color: var(--bs-secondary) !important; }

/* Scrollbar del dock */
html[data-theme="dark"] #dock-active .dock-body::-webkit-scrollbar{ height:8px; width:8px; }
html[data-theme="dark"] #dock-active .dock-body::-webkit-scrollbar-track{ background: rgba(255,255,255,.04); }
html[data-theme="dark"] #dock-active .dock-body::-webkit-scrollbar-thumb{
  background: rgba(255,255,255,.12); border-radius: 8px;
}


/* ===== Colas por paradero: compactas + overlay ===== */

.queues-shell{
 
}

/* Tarjeta compacta que ocupa poco alto */
.queues-compact-card{
  border-radius: .75rem;
  border: 1px solid var(--bs-border-color);
  padding: .5rem .75rem;
  background: var(--bs-body-bg);
}

/* Lista compacta de paraderos (chips) */
.queues-compact-list{
  display: flex;
  flex-wrap: wrap;
  gap: .35rem;
  max-height: 72px;         /* 👈 solo unas 2–3 filas */
  overflow-y: auto;
}

.queues-compact-chip{
  display:inline-flex;
  align-items:center;
  gap:.25rem;
  padding:.15rem .4rem;
  border-radius:999px;
  border:1px solid var(--bs-border-color);
  background: var(--bs-light);
  font-size:.8rem;
  cursor:pointer;
}
.queues-compact-chip strong{
  max-width: 90px;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

/* Overlay que cubre toda la dispatch-right: "segunda sidebar" */
.queues-full-overlay{
  position:absolute;
  inset:0;
  background:var(--bs-body-bg);
  border-left:1px solid var(--bs-border-color);
  box-shadow:-8px 0 24px rgba(0,0,0,.18);
  padding:.75rem;
  display:flex;
  flex-direction:column;
  opacity:0;
  pointer-events:none;
  transform: translateY(8px);
  transition: opacity .18s ease, transform .18s ease;
  z-index: 10;          /* por encima del contenido normal de la derecha */
}

/* cuerpo scrolleable del overlay */
.queues-full-body{
  flex:1;
  overflow:auto;
}

/* Estado expandido */
.queues-shell.queues-expanded .queues-full-overlay{
  opacity:1;
  pointer-events:auto;
  transform: translateY(0);
}

/* Cuando está expandido, atenuamos lo de atrás */
.queues-shell.queues-expanded .queues-compact-card,
.queues-shell.queues-expanded ~ .d-flex,
.queues-shell.queues-expanded ~ .nav,
.queues-shell.queues-expanded ~ .tab-content{
  filter: blur(1px);
  opacity:.4;
  pointer-events:none;
}

/* Dark mode-friendly */
html[data-theme="dark"] .queues-compact-card{
  background: var(--bs-body-bg);
}
html[data-theme="dark"] .queues-full-overlay{
  background: var(--bs-body-bg);
}
html[data-theme="dark"] .queues-compact-chip{
  background: var(--bs-dark);
}


</style>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/confirmDate/confirmDate.min.js"></script>
<script>
  // si existe la ruta nombrada, úsala; de lo contrario, default
  window.__QUOTE_URL__ = "<?php echo e(Route::has('api.dispatch.quote') ? route('api.dispatch.quote') : '/api/dispatch/quote'); ?>";
   window.__GEO_ROUTE_URL__ = "<?php echo e(Route::has('api.geo.route') ? route('api.geo.route') : url('/api/geo/route')); ?>";
window.__CHAT_THREADS_URL__  = "<?php echo e(route('api.dispatch.chats.threads')); ?>";
window.__CHAT_MESSAGES_URL__ = "<?php echo e(route('api.dispatch.chats.messages', ['driverId' => 'DRIVER_ID'])); ?>";
window.__CHAT_SEND_URL__     = "<?php echo e(route('api.dispatch.chats.send', ['driverId' => 'DRIVER_ID'])); ?>";


</script>
<script>
console.log('TENANT(view) id=', <?php echo json_encode($tenant->id ?? null, 15, 512) ?>, 'name=', <?php echo json_encode($tenant->name ?? null, 15, 512) ?>);
</script>

<script>
  window.ccTenant = window.ccTenant || {};
  window.ccTenant.map = {
    lat: <?php echo e((float)($tenant->latitud ?? 19.1738)); ?>,
    lng: <?php echo e((float)($tenant->longitud ?? -96.1342)); ?>,
    zoom: <?php echo e((int)($tenant->map_zoom ?? 14)); ?>, // si no existe, deja 14 fijo
    radius_km: <?php echo e((float)($tenant->coverage_radius_km ?? 8)); ?>,
  };
</script>



<script>
(() => {
  const shell  = document.querySelector('.queues-shell');
  const btnOpen  = document.getElementById('btnQueuesExpand');
  const btnClose = document.getElementById('btnQueuesClose');

  if (!shell || !btnOpen || !btnClose) return;

  btnOpen.addEventListener('click', (e) => {
    e.preventDefault();
    shell.classList.add('queues-expanded');
  });

  btnClose.addEventListener('click', (e) => {
    e.preventDefault();
    shell.classList.remove('queues-expanded');
  });

  // helper global por si quieres abrir desde JS (ej. click sobre chip)
  window.openQueuesOverlay = function(){
    shell.classList.add('queues-expanded');
  };
})();
</script>

<script>
  function isPage(id){ return document.querySelector('meta[name="page-id"]')?.content === id; }

window.getStops = function(){
  const arr = [];
  const s1lat = parseFloat(document.querySelector('#stop1Lat')?.value || '');
  const s1lng = parseFloat(document.querySelector('#stop1Lng')?.value || '');
  if (Number.isFinite(s1lat) && Number.isFinite(s1lng)) {
    arr.push({ lat: s1lat, lng: s1lng, label: (document.querySelector('#inStop1')?.value || '').trim() || null });
  }
  const s2lat = parseFloat(document.querySelector('#stop2Lat')?.value || '');
  const s2lng = parseFloat(document.querySelector('#stop2Lng')?.value || '');
  if (Number.isFinite(s2lat) && Number.isFinite(s2lng)) {
    arr.push({ lat: s2lat, lng: s2lng, label: (document.querySelector('#inStop2')?.value || '').trim() || null });
  }
  return arr;
};
window.clearStops = function(){
  ['#stop1Lat','#stop1Lng','#inStop1','#stop2Lat','#stop2Lng','#inStop2'].forEach(sel=>{
    const el=document.querySelector(sel); if (el) el.value='';
  });
  const r1=document.getElementById('stop1Row'); if (r1) r1.style.display='none';
  const r2=document.getElementById('stop2Row'); if (r2) r2.style.display='none';
};
window.setStopCoords = function(_id, {lat,lng,label}){
  // shim: si alguna parte vieja lo usa, ignoramos id y rellenamos Stop 1 libre
  const target = !document.querySelector('#stop1Lat')?.value ? 1 : 2;
  const latEl = document.querySelector(`#stop${target}Lat`);
  const lngEl = document.querySelector(`#stop${target}Lng`);
  const lblEl = document.querySelector(`#inStop${target}`);
  const row   = document.querySelector(`#stop${target}Row`);
  if (latEl && lngEl){ latEl.value=lat; lngEl.value=lng; }
  if (lblEl && label){ lblEl.value = label; }
  if (row){ row.style.display = 'block'; }
};
</script>


<script>
// Mostrar/ocultar la fila del programado
(function(){
  const rNow   = document.getElementById('when-now');
  const rLater = document.getElementById('when-later');
  const row    = document.getElementById('scheduleRow');

  function apply(){
    const on = !!rLater?.checked;
    if (row) row.style.display = on ? 'flex' : 'none';
  }
  rNow?.addEventListener('change', apply);
  rLater?.addEventListener('change', apply);
  apply();
})();

// Inicializa Flatpickr sobre #scheduleAt
(function(){
  const el = document.getElementById('scheduleAt');
  if (!el) return;

  // función para minDate usando reloj de servidor (delta)
  function minNow(){
    const delta = Number(window.__SERVER_CLOCK_DELTA__ || 0);
    return new Date(Date.now() + delta);
  }

  const fp = flatpickr(el, {
    enableTime: true,
    time_24hr: true,
    minuteIncrement: 5,
    // MUY IMPORTANTE: formateo igual al value de <input type="datetime-local">
    dateFormat: "Y-m-d\\TH:i",
    altInput: true,
    altFormat: "d/M/Y H:i",
    locale: "es",
    defaultHour: new Date().getHours(),
    minDate: minNow(),               // sin pasado
    plugins: [ new confirmDatePlugin({ showAlways:false, theme:"light", confirmText:"Aceptar" }) ],
    onOpen: () => fp.set('minDate', minNow()),
  });

  // utilidad global para resetear a "Ahora"
  window.resetWhenNow = function(){
    const rNow   = document.getElementById('when-now');
    const rLater = document.getElementById('when-later');
    const row    = document.getElementById('scheduleRow');
    if (rNow) rNow.checked = true;
    if (rLater) rLater.checked = false;
    try { fp.clear(); } catch{}
    if (row) row.style.display = 'none';
  };
})();
</script>


<script>
  window.ccGoogleMapsKey = <?php echo json_encode(config('services.google.maps.key', env('GOOGLE_MAPS_KEY', ''))) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

        <script>
window.ccDispatchSettings = {
  auto_dispatch_enabled: <?php echo json_encode($settings->auto_dispatch_enabled ?? true, 15, 512) ?>,
  auto_dispatch_delay_s: <?php echo json_encode($settings->auto_dispatch_delay_s ?? 20, 15, 512) ?>,
  auto_dispatch_preview_n: <?php echo json_encode($settings->auto_dispatch_preview_n ?? 8, 15, 512) ?>,
  auto_dispatch_preview_radius_km: <?php echo json_encode($settings->auto_dispatch_preview_radius_km ?? 5, 15, 512) ?>
};
// ===== CONTROL CON BOOTSTRAP COLLAPSE =====
(function() {
  const accordionBody = document.getElementById('driversAccordionBody');
  const chevron = document.getElementById('driversChevron');
  
  if (!accordionBody || !chevron) return;
  
  // Estado inicial desde localStorage
  const isOpen = localStorage.getItem('driversAccordionOpen') === 'true';
  
  // Crear instancia de Collapse de Bootstrap
  const collapse = new bootstrap.Collapse(accordionBody, {
    toggle: false
  });
  
  // Aplicar estado inicial
  if (isOpen) {
    collapse.show();
    chevron.setAttribute('data-feather', 'chevron-up');
    
    // Cargar conductores
    if (typeof refreshDrivers === 'function') {
      setTimeout(() => refreshDrivers(), 200);
    }
  }
  
  // Actualizar icono cuando cambia el estado
  accordionBody.addEventListener('shown.bs.collapse', function() {
    chevron.setAttribute('data-feather', 'chevron-up');
    feather.replace();
    localStorage.setItem('driversAccordionOpen', 'true');
    
    // Cargar conductores al abrir
    if (typeof refreshDrivers === 'function') {
      setTimeout(() => refreshDrivers(), 100);
    }
  });
  
  accordionBody.addEventListener('hidden.bs.collapse', function() {
    chevron.setAttribute('data-feather', 'chevron-down');
    feather.replace();
    localStorage.setItem('driversAccordionOpen', 'false');
  });
  
  // Actualizar iconos iniciales
  setTimeout(() => {
    if (typeof feather !== 'undefined') {
      feather.replace();
    }
  }, 100);
})();

</script>
<script>
(function(){
  const html = document.documentElement;
  const mapRoot = document.getElementById('map')?.parentElement?.parentElement || document.body;
  const apply = () => {
    const dark = html.getAttribute('data-theme') === 'dark';
    document.getElementById('map')?.classList.toggle('map-dark', dark);
  };
  // primera aplicación y cuando presiones el toggle que ya tienes
  apply();
  const obs = new MutationObserver(apply);
  obs.observe(html, { attributes:true, attributeFilter:['data-theme'] });
})();
</script>

<script>
// === DOCK: expandir/colapsar con persistencia ================================
(() => {
  const dock = document.getElementById('dock-active');
  const btn  = document.getElementById('btnDockToggle');
  if (!dock || !btn) return;

  // estado inicial desde localStorage (1 = colapsado / 0 = expandido)
  const saved = localStorage.getItem('dock:collapsed');
  if (saved === '0') dock.classList.remove('collapsed'); else dock.classList.add('collapsed');

  // toggle
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    dock.classList.toggle('collapsed');
    localStorage.setItem('dock:collapsed', dock.classList.contains('collapsed') ? '1' : '0');
  });

  // helper global para otras partes (ej. al pulsar "Ver" en el dock)
  window.ensureDockExpanded = function(){
    const d = document.getElementById('dock-active');
    if (d && d.classList.contains('collapsed')) {
      d.classList.remove('collapsed');
      localStorage.setItem('dock:collapsed','0');
    }
  };
})();
</script>



<?php echo app('Illuminate\Foundation\Vite')(['resources/js/pages/dispatch.js']); ?>

<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.dispatch', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/dispatch.blade.php ENDPATH**/ ?>