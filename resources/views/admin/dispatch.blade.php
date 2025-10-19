<?php /* resources/views/admin/dispatch.blade.php */ ?>
@extends('layouts.dispatch')
@section('title','Dispatch')

@section('content')
<div class="dispatch-grid">

  {{-- Panel izquierdo (form de viaje) --}}
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

    <div class="small text-muted mb-2" id="routeSummary">Ruta: — · Zona: — · Cuando: ahora</div>

    <div class="mb-3">
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="when" id="when-now" checked>
        <label class="form-check-label" for="when-now">Ahora</label>
      </div>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="when" id="when-later">
        <label class="form-check-label" for="when-later">Después</label>
      </div>
    </div>

    <h6 class="mt-3">Pasajeros</h6>
    <div class="row g-2">
      <div class="col-4">
        <label class="form-label small">Nombre</label>
        <input class="form-control form-control-sm" id="pass-name">
      </div>
      <div class="col-4">
        <label class="form-label small">Teléfono</label>
        <input class="form-control form-control-sm" id="pass-phone" placeholder="Buscar últimos viajes">
      </div>
      <div class="col-4">
        <label class="form-label small">Cuenta</label>
        <input class="form-control form-control-sm" id="pass-account">
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
        <label class="form-label small">Notas</label>
        <input class="form-control form-control-sm" id="ride-notes" placeholder="Discapacidad, mascota, etc.">
      </div>
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
    <input id="fareAmount" class="form-control" inputmode="numeric" pattern="[0-9]*" placeholder="0" />
  </div>
</div>


    </div>

    <div class="d-grid gap-2 mt-3">
      <button class="btn btn-outline-primary" id="btnQuote"><i data-feather="dollar-sign"></i> Cotizar</button>
      <button class="btn btn-success" id="btnCreate"><i data-feather="check-circle"></i> Crear viaje</button>
      <button class="btn btn-outline-danger" id="btnClear"><i data-feather="trash-2"></i> Limpiar</button>
    </div>

    <hr>
    <h6>Capas</h6>
    <div class="form-check"><input class="form-check-input" id="toggle-sectores" type="checkbox" checked><label class="form-check-label" for="toggle-sectores">Sectores</label></div>
    <div class="form-check"><input class="form-check-input" id="toggle-stands" type="checkbox" checked><label class="form-check-label" for="toggle-stands">Paraderos</label></div>
    <div class="form-check"><input class="form-check-input" id="toggle-drivers" type="checkbox" checked><label class="form-check-label" for="toggle-drivers">Conductores</label></div>
  </aside>

  {{-- Mapa --}}
  <section class="dispatch-map"><div id="map"></div></section>

  {{-- Panel derecho --}}


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



  <aside class="dispatch-right">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h6 class="mb-0">Colas por paradero</h6>
      <span class="badge bg-secondary" id="badgeColas">0</span>
    </div>
    <div id="panel-queue" class="small mb-3"></div>

    <div class="d-flex align-items-center justify-content-between mb-2">
      <h6 class="mb-0">Viajes activos</h6>
      <span class="badge bg-primary" id="badgeActivos">0</span>
    </div>
    <div id="panel-active" class="small"></div>
  </aside>



</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<style>

  


  .dispatch-wrapper { height: calc(100vh - 58px); }
  .dispatch-grid{position:relative;height:100%;display:grid;gap:0;grid-template-columns:340px 1fr 360px}
  .dispatch-left,.dispatch-right{overflow:auto;padding:12px}
  .dispatch-left{border-right:1px solid var(--bs-border-color)}
  .dispatch-right{border-left:1px solid var(--bs-border-color)}
  .dispatch-map{position:relative}
  .dispatch-map #map{position:absolute;inset:0}
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
</style>
@endpush

@push('scripts')
{{-- Leaflet una sola vez (sin duplicar) --}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>


<script>
  window.ccGoogleMapsKey = @json(config('services.google.maps.key', env('GOOGLE_MAPS_KEY','')));
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

        <script>
window.ccDispatchSettings = {
  auto_dispatch_enabled: @json($settings->auto_dispatch_enabled ?? true),
  auto_dispatch_delay_s: @json($settings->auto_dispatch_delay_s ?? 10),
  auto_dispatch_preview_n: @json($settings->auto_dispatch_preview_n ?? 8),
  auto_dispatch_preview_radius_km: @json($settings->auto_dispatch_preview_radius_km ?? 5)
};
</script>

{{-- Tu JS de la página (carga todo, incluido Google dinámico) --}}
@vite(['resources/js/pages/dispatch.js'])
@endpush
