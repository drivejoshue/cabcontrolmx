<?php
// resources/views/admin/dashboard.blade.php
?>
@extends('layouts.admin') {{-- tu layout AdminKit actual --}}

@section('title','Dashboard')

@section('content')

<?php /* fragmento en dashboard */ ?>
<div class="d-flex gap-2 mb-3">
  <a href="{{ route('sectores.index') }}" class="btn btn-outline-primary btn-sm">
    <i data-feather="map"></i> Sectores
  </a>
  <a href="{{ route('taxistands.index') }}" class="btn btn-outline-primary btn-sm">
    <i data-feather="map-pin"></i> Paraderos
  </a>
  <a href="{{ route('admin.dispatch') }}" class="btn btn-success btn-sm">
    <i data-feather="activity"></i> Ir a Dispatch
  </a>
</div>


<div class="container-fluid p-0">

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#resumen">Resumen</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#sectores">Sectores</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#paraderos">Paraderos</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#conductores">Conductores</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#vehiculos">Vehículos</a>
    </li>
  </ul>

  <div class="tab-content">

    <div class="tab-pane fade show active" id="resumen">
      <div class="row">
        <div class="col-12 col-md-4 mb-3">
          <div class="card"><div class="card-body">
            <h5 class="card-title mb-2">Conductores activos</h5>
            <div class="display-6" id="kpi-activos">0</div>
          </div></div>
        </div>
        <div class="col-12 col-md-4 mb-3">
          <div class="card"><div class="card-body">
            <h5 class="card-title mb-2">Servicios hoy</h5>
            <div class="display-6" id="kpi-servicios">0</div>
          </div></div>
        </div>
        <div class="col-12 col-md-4 mb-3">
          <div class="card"><div class="card-body">
            <h5 class="card-title mb-2">Última hora</h5>
            <div class="display-6" id="kpi-ultima-hora">0</div>
          </div></div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="sectores">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="card-title mb-0">Sectores</span>
          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalSector">Nuevo</button>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm" id="tbl-sectores">
              <thead><tr>
                <th>ID</th><th>Nombre</th><th>Activo</th><th>Acciones</th>
              </tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
      {{-- Modal Sector (form básico) --}}
      <div class="modal fade" id="modalSector" tabindex="-1">
        <div class="modal-dialog modal-lg"><div class="modal-content">
          <div class="modal-header"><h5 class="modal-title">Nuevo sector</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <form id="formSector">
              <div class="mb-2">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="nombre" required>
              </div>
              <div class="mb-2">
                <label class="form-label">Polígono (GeoJSON)</label>
                <textarea class="form-control" name="area" rows="6" placeholder='{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[...] ]}}'></textarea>
              </div>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="activo" checked>
                <label class="form-check-label">Activo</label>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button class="btn btn-primary" id="btnSaveSector">Guardar</button>
          </div>
        </div></div>
      </div>
    </div>

    <div class="tab-pane fade" id="paraderos">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="card-title mb-0">Paraderos</span>
          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalStand">Nuevo</button>
        </div>
        <div class="card-body">
          <table class="table table-sm" id="tbl-stands">
            <thead><tr>
              <th>ID</th><th>Nombre</th><th>Código</th><th>Sector</th><th>Cap.</th><th>Activo</th><th>Acciones</th>
            </tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      {{-- Modal Stand --}}
      <div class="modal fade" id="modalStand" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
          <div class="modal-header"><h5 class="modal-title">Nuevo paradero</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <form id="formStand">
              <div class="mb-2"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
              <div class="row g-2">
                <div class="col"><label class="form-label">Lat</label><input class="form-control" name="latitud" required></div>
                <div class="col"><label class="form-label">Lng</label><input class="form-control" name="longitud" required></div>
              </div>
              <div class="row g-2 mt-1">
                <div class="col"><label class="form-label">Código</label><input class="form-control" name="codigo" required></div>
                <div class="col"><label class="form-label">Capacidad</label><input class="form-control" name="capacidad" type="number" min="0"></div>
              </div>
              <div class="mb-2 mt-1">
                <label class="form-label">Sector</label>
                <select class="form-select" name="sector_id" id="select-sector"></select>
              </div>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="activo" checked>
                <label class="form-check-label">Activo</label>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button class="btn btn-primary" id="btnSaveStand">Guardar</button>
          </div>
        </div></div>
      </div>
    </div>

    <div class="tab-pane fade" id="conductores">
      <div class="alert alert-info mb-0">En breve: listado y creación de conductores.</div>
    </div>

    <div class="tab-pane fade" id="vehiculos">
      <div class="alert alert-info mb-0">En breve: listado y creación de vehículos.</div>
    </div>

  </div>
</div>
@endsection
