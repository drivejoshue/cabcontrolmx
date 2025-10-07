@extends('layouts.admin')

@section('title','Dashboard')

@section('content')
<div class="container-fluid p-0">

  {{-- Accesos rápidos --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.dispatch') }}" class="btn btn-success btn-sm">
      <i data-feather="activity"></i> Ir a Dispatch
    </a>
    <a href="{{ route('sectores.index') }}" class="btn btn-outline-primary btn-sm">
      <i data-feather="map"></i> Sectores
    </a>
    <a href="{{ route('taxistands.index') }}" class="btn btn-outline-primary btn-sm">
      <i data-feather="map-pin"></i> Paraderos
    </a>
    <a href="{{ route('drivers.index') }}" class="btn btn-outline-primary btn-sm">
      <i data-feather="user"></i> Conductores
    </a>
    <a href="{{ route('vehicles.index') }}" class="btn btn-outline-primary btn-sm">
      <i data-feather="truck"></i> Vehículos
    </a>
  </div>

  {{-- KPIs simples (placeholder) --}}
  <div class="row">
    <div class="col-12 col-md-4 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title mb-2">Conductores activos</h5>
          <div class="display-6" id="kpi-activos">0</div>
          <small class="text-muted">Con turno abierto en los últimos 15 min</small>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-4 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title mb-2">Servicios hoy</h5>
          <div class="display-6" id="kpi-servicios">0</div>
          <small class="text-muted">Finalizados + en curso</small>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-4 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title mb-2">Última hora</h5>
          <div class="display-6" id="kpi-ultima-hora">0</div>
          <small class="text-muted">Servicios creados</small>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
