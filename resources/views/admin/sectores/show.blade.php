<?php /** @var object $sector */ ?>
@extends('layouts.admin')
@section('title','Sector #'.$sector->id)

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
  #map-sector{
    height: 100%;
    min-height: 520px;
    border:1px solid rgba(98,105,118,.25);
    border-radius:.75rem;
  }
  .help-xs{ font-size:.85rem; }
  .dl-compact dt{ color:#626976; font-weight:600; }
  .dl-compact dd{ margin-bottom:.5rem; }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h3 class="mb-0">Sector: {{ $sector->nombre }}</h3>
      <div class="text-muted help-xs">Área de referencia/cobertura para paraderos.</div>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.sectores.index') }}" class="btn btn-outline-secondary">
        <i data-feather="arrow-left"></i> Volver
      </a>
      <a href="{{ route('admin.sectores.edit',$sector->id) }}" class="btn btn-primary">
        <i data-feather="edit-2"></i> Editar
      </a>
    </div>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif

  <div class="row g-3">
    {{-- Info + instrucciones --}}
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Información</h3>
        </div>
        <div class="card-body">
          <dl class="row mb-0 dl-compact">
            <dt class="col-5">ID</dt>
            <dd class="col-7">{{ $sector->id }}</dd>

            <dt class="col-5">Nombre</dt>
            <dd class="col-7">{{ $sector->nombre }}</dd>

            <dt class="col-5">Activo</dt>
            <dd class="col-7">
              @if($sector->activo)
                <span class="badge bg-success-lt text-success">Activo</span>
              @else
                <span class="badge bg-secondary-lt text-secondary">Inactivo</span>
              @endif
            </dd>

            <dt class="col-5">Actualizado</dt>
            <dd class="col-7"><span class="text-muted">{{ $sector->updated_at }}</span></dd>
          </dl>

          <hr class="my-3">

          <div class="fw-semibold mb-2">¿Para qué sirve un sector?</div>
          <ul class="mb-0 help-xs">
            <li>Es necesario para definir un <span class="fw-semibold">Paradero (Taxi Stand)</span>.</li>
            <li>Define el <span class="fw-semibold">área de cobertura / referencia</span> del paradero.</li>
            <li>Puedes colocar <span class="fw-semibold">varios paraderos</span> dentro del mismo sector.</li>
            <li>El <span class="fw-semibold">radio de alcance</span> (matching/asignación) se ajusta en el core del sistema.</li>
          </ul>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-header">
          <h3 class="card-title mb-0">GeoJSON</h3>
        </div>
        <div class="card-body">
          <textarea class="form-control" rows="10" readonly>{{ $sector->area }}</textarea>
          <div class="text-muted help-xs mt-2">Este GeoJSON define el polígono del sector.</div>
        </div>
      </div>
    </div>

    {{-- Mapa --}}
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Vista en mapa</h3>
        </div>
        <div class="card-body">
          <div id="map-sector"></div>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const feature = (() => { try { return JSON.parse(@json($sector->area)); } catch { return null; } })();

  const fallbackCenter = [19.1738, -96.1342];
  const map = L.map('map-sector').setView(fallbackCenter, 14);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution:'&copy; OpenStreetMap'
  }).addTo(map);

  if (feature && feature.type === 'Feature') {
    const layer = L.geoJSON(feature, {
      style:{ weight: 2, fillOpacity: .20 } // sin forzar color (Tabler se ve limpio)
    }).addTo(map);

    try { map.fitBounds(layer.getBounds().pad(0.2)); } catch {}

    // Por si el card aparece en layout responsive
    setTimeout(() => map.invalidateSize(), 200);
  } else {
    setTimeout(() => map.invalidateSize(), 200);
  }
});
</script>
@endpush
