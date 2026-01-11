<?php
/** @var \Illuminate\Support\Collection $sectores */
?>
@extends('layouts.admin')
@section('title','Crear paradero')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
  /* Alto del mapa: ocupa casi toda la ventana en desktop */
  #map-pick {
    height: calc(100vh - 220px);
    min-height: 420px;
    border-radius: .5rem;
  }
  @media (max-width: 991.98px) { /* < lg */
    #map-pick { height: 420px; }
  }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Nuevo paradero</h3>
    <a href="{{ route('admin.taxistands.index') }}" class="btn btn-outline-secondary">
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

  <form method="POST" action="{{ route('admin.taxistands.store') }}" autocomplete="off">
    @csrf

    <div class="row g-3">
      {{-- Columna izquierda: formulario compacto --}}
      <div class="col-12 col-lg-5">
        <div class="card h-100">
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Nombre *</label>
              <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Sector *</label>
              <select name="sector_id" class="form-select" required>
                <option value="">-- Selecciona --</option>
                @foreach($sectores as $sec)
                  <option value="{{ $sec->id }}" @selected(old('sector_id')==$sec->id)>{{ $sec->nombre }}</option>
                @endforeach
              </select>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label">Latitud *</label>
                <input id="lat" type="number" step="any" name="latitud" class="form-control"
                       value="{{ old('latitud', $tenantLoc->latitud ?? 19.1738) }}" required>
              </div>
              <div class="col-6">
                <label class="form-label">Longitud *</label>
                <input id="lng" type="number" step="any" name="longitud" class="form-control"
                       value="{{ old('longitud', $tenantLoc->longitud ?? -96.1342) }}" required>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Capacidad</label>
              <input type="number" name="capacidad" class="form-control" min="0" value="{{ old('capacidad', 0) }}">
            </div>

            <div class="d-grid gap-2 mt-4">
              <a href="{{ route('admin.taxistands.index') }}" class="btn btn-outline-secondary">Cancelar</a>
              <button class="btn btn-primary" type="submit">
                <i data-feather="save"></i> Guardar
              </button>
            </div>
          </div>
        </div>
      </div>

      {{-- Columna derecha: mapa amplio --}}
      <div class="col-12 col-lg-7">
        <div class="card h-100">
          <div class="card-body p-2">
            <div class="d-flex justify-content-between align-items-center px-1 mb-2">
              <div class="small text-muted">
                Click en el mapa para fijar coordenadas o arrastra el marcador.
              </div>
              <div class="d-flex align-items-center gap-2">
                <button type="button" id="btnPick" class="btn btn-sm btn-outline-primary">
                  <i data-feather="map-pin"></i> Elegir en mapa
                </button>
              </div>
            </div>
            <div id="map-pick"></div>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Debug básico
  window.__DBG_TAXISTAND_CREATE__ = {
    tenantId  : @json($tenantId ?? null),
    tenantLoc : @json($tenantLoc ?? null),
    oldLat    : @json(old('latitud')),
    oldLng    : @json(old('longitud'))
  };
  console.log('[TaxiStands/Create] Debug →', window.__DBG_TAXISTAND_CREATE__);

  const latEl = document.getElementById('lat');
  const lngEl = document.getElementById('lng');

  let lat = Number(latEl.value);
  let lng = Number(lngEl.value);

  // Si los inputs quedaron con fallback y no hay old(), usa tenantLoc
  const isFallback = (lat === 19.1738 && lng === -96.1342);
  const tLoc = window.__DBG_TAXISTAND_CREATE__.tenantLoc;
  const hasTenantLoc = !!(tLoc && tLoc.latitud && tLoc.longitud);

  if (isFallback && hasTenantLoc && window.__DBG_TAXISTAND_CREATE__.oldLat == null && window.__DBG_TAXISTAND_CREATE__.oldLng == null) {
    lat = Number(tLoc.latitud);
    lng = Number(tLoc.longitud);
    latEl.value = lat.toFixed(6);
    lngEl.value = lng.toFixed(6);
    console.warn('[TaxiStands/Create] Corrigiendo fallback → tenantLoc', { lat, lng });
  }

  const zoom = 13;
  console.log('[TaxiStands/Create] setView →', { lat, lng, zoom });

  const map = L.map('map-pick').setView([lat, lng], zoom);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ attribution:'&copy; OSM' }).addTo(map);

  let m = L.marker([lat, lng], { draggable: true }).addTo(map);
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

  // Recalcular tamaño por si el mapa se monta en card alta
  setTimeout(() => map.invalidateSize(), 150);
});
</script>
@endpush
