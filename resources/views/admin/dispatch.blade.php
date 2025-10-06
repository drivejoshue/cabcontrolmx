<?php /* resources/views/admin/dispatch.blade.php */ ?>
@extends('layouts.dispatch')
@section('title','Dispatch')

@section('content')
<div class="dispatch-grid">

  {{-- Panel izquierdo (form de orden) --}}
  <aside class="dispatch-left">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h6 class="mb-0">Nuevo servicio</h6>
      <div class="btn-group">
        <button class="btn btn-sm btn-outline-secondary" title="Limpiar"><i data-feather="rotate-ccw"></i></button>
        <button class="btn btn-sm btn-outline-primary"  title="Duplicar"><i data-feather="copy"></i></button>
      </div>
    </div>

    <div class="mb-2">
      <label class="form-label">Origen</label>
      <div class="input-group">
        <input id="inFrom" class="form-control" placeholder="Calle, número...">
        <button class="btn btn-outline-secondary" title="Punto en mapa" id="btnPickFrom"><i data-feather="map-pin"></i></button>
      </div>
    </div>

    <div class="mb-2">
      <label class="form-label">Destino</label>
      <div class="input-group">
        <input id="inTo" class="form-control" placeholder="Calle, número...">
        <button class="btn btn-outline-secondary" title="Punto en mapa" id="btnPickTo"><i data-feather="map-pin"></i></button>
      </div>
    </div>

    <div class="small text-muted mb-2" id="routeSummary">Ruta: —  ·  Zona: —  ·  Cuando: ahora</div>

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
        <input class="form-control form-control-sm" id="pass-phone">
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
          <option>Efectivo</option>
          <option>Crédito (cuenta)</option>
        </select>
      </div>
      <div class="col-6">
        <label class="form-label small">Notas</label>
        <input class="form-control form-control-sm" id="job-notes" placeholder="Discapacidad, mascota, etc.">
      </div>
    </div>

    <h6 class="mt-3">Info del viaje</h6>
    <div class="row g-2">
      <div class="col-4">
        <label class="form-label small"># pax</label>
        <input type="number" min="1" value="1" class="form-control form-control-sm">
      </div>
      <div class="col-4">
        <label class="form-label small">Vehículo</label>
        <select class="form-select form-select-sm">
          <option>No especificado</option>
          <option>Sedán</option>
          <option>Van</option>
          <option>Mototaxi</option>
        </select>
      </div>
      <div class="col-4">
        <label class="form-label small">Extras</label>
        <select class="form-select form-select-sm">
          <option>Normal</option>
          <option>Silla bebé</option>
        </select>
      </div>
    </div>

    <div class="d-grid gap-2 mt-3">
      <button class="btn btn-outline-primary" id="btnQuote"><i data-feather="dollar-sign"></i> Cotizar</button>
      <button class="btn btn-success" id="btnCreate"><i data-feather="check-circle"></i> Crear servicio</button>
      <button class="btn btn-outline-danger" id="btnClear"><i data-feather="trash-2"></i> Limpiar</button>
    </div>

    <hr>
    <h6>Capas</h6>
    <div class="form-check"><input class="form-check-input" id="toggle-sectores" type="checkbox" checked><label class="form-check-label" for="toggle-sectores">Sectores</label></div>
    <div class="form-check"><input class="form-check-input" id="toggle-stands" type="checkbox" checked><label class="form-check-label" for="toggle-stands">Paraderos</label></div>
    <div class="form-check"><input class="form-check-input" id="toggle-drivers" type="checkbox" checked><label class="form-check-label" for="toggle-drivers">Conductores</label></div>
  </aside>

  {{-- Mapa centro --}}
  <section class="dispatch-map">
    <div id="map"></div>
  </section>

  {{-- Panel derecho (colas / activos) --}}
  <aside class="dispatch-right">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h6 class="mb-0">Colas por paradero</h6>
      <span class="badge bg-secondary" id="badgeColas">0</span>
    </div>
    <div id="panel-queue" class="small mb-3">
      {{-- aquí listaremos cada stand con su cola (Car 1, Car 2, ...) --}}
    </div>

    <div class="d-flex align-items-center justify-content-between mb-2">
      <h6 class="mb-0">Servicios activos</h6>
      <span class="badge bg-primary" id="badgeActivos">0</span>
    </div>
    <div id="panel-active" class="small">
      {{-- cards de servicios en curso --}}
    </div>
  </aside>

</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
  /* Layout tipo TaxiCaller */
  .dispatch-wrapper { height: calc(100vh - 58px); }
  .dispatch-grid {
    position: relative; height: 100%; display: grid; gap: 0;
    grid-template-columns: 340px 1fr 360px; /* izq - mapa - der */
  }
  .dispatch-left, .dispatch-right { overflow: auto; padding: 12px; }
  .dispatch-left  { border-right: 1px solid var(--bs-border-color); }
  .dispatch-right { border-left:  1px solid var(--bs-border-color); }
  .dispatch-map #map { position:absolute; inset:0; }
  .dispatch-map { position: relative; }
  @media (max-width: 992px) {
    .dispatch-grid { grid-template-columns: 1fr; grid-template-rows: 300px 400px 300px; }
    .dispatch-left  { border-right: 0; border-bottom: 1px solid var(--bs-border-color); }
    .dispatch-right { border-left: 0; border-top: 1px solid var(--bs-border-color); }
  }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  @vite(['resources/js/app.js', 'resources/js/pages/dispatch.js'])
@endpush
