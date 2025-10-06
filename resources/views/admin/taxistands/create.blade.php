<?php
/** @var \Illuminate\Support\Collection $sectores */
?>
@extends('layouts.admin')
@section('title','Crear paradero')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
  #map-pick { height: 420px; border-radius: .5rem; }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Nuevo paradero</h3>
    <a href="{{ route('taxistands.index') }}" class="btn btn-outline-secondary">
      <i data-feather="arrow-left"></i> Volver
    </a>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">Corrige los siguientes campos:</div>
      <ul class="mb-0">
        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('taxistands.store') }}" autocomplete="off" class="card">
    @csrf
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nombre *</label>
          <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Sector *</label>
          <select name="sector_id" class="form-select" required>
            <option value="">-- Selecciona --</option>
            @foreach($sectores as $sec)
              <option value="{{ $sec->id }}" @selected(old('sector_id')==$sec->id)>{{ $sec->nombre }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Latitud *</label>
          <input id="lat" type="number" step="any" name="latitud" class="form-control"
                 value="{{ old('latitud', 19.1738) }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Longitud *</label>
          <input id="lng" type="number" step="any" name="longitud" class="form-control"
                 value="{{ old('longitud', -96.1342) }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Capacidad</label>
          <input type="number" name="capacidad" class="form-control" min="0" value="{{ old('capacidad', 0) }}">
        </div>
      </div>

      <hr>
      <div class="mb-2 d-flex align-items-center gap-2">
        <button type="button" id="btnPick" class="btn btn-sm btn-outline-primary">
          <i data-feather="map-pin"></i> Elegir en mapa
        </button>
        <span class="text-muted small">Click en el mapa para fijar coordenadas.</span>
      </div>
      <div id="map-pick" class="mb-3"></div>
    </div>

    <div class="card-footer d-flex justify-content-end gap-2">
      <a href="{{ route('taxistands.index') }}" class="btn btn-outline-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit">
        <i data-feather="save"></i> Guardar
      </button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const latEl = document.getElementById('lat');
  const lngEl = document.getElementById('lng');
  const map = L.map('map-pick').setView([Number(latEl.value), Number(lngEl.value)], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OSM'}).addTo(map);

  let m = L.marker([Number(latEl.value), Number(lngEl.value)], { draggable: true }).addTo(map);
  m.on('dragend', () => {
    const p = m.getLatLng();
    latEl.value = p.lat.toFixed(6);
    lngEl.value = p.lng.toFixed(6);
  });

  let picking = false;
  document.getElementById('btnPick')?.addEventListener('click', ()=> {
    picking = !picking;
    alert(picking ? 'Click en el mapa para fijar el punto.' : 'Selección por mapa desactivada.');
  });

  map.on('click', (ev) => {
    if (!picking) return;
    const {lat,lng} = ev.latlng;
    m.setLatLng(ev.latlng);
    latEl.value = lat.toFixed(6);
    lngEl.value = lng.toFixed(6);
    picking = false;
  });
});
</script>
@endpush
