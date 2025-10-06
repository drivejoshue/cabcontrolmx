<?php /** @var object $sector */ ?>
@extends('layouts.admin')
@section('title','Sector #'.$sector->id)

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>#map-sector{height:60vh;border:1px solid #e5e7eb;border-radius:.5rem;}</style>
@endpush

@section('content')
<div class="container-fluid p-0">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Sector: {{ $sector->nombre }}</h3>
    <div class="d-flex gap-2">
      <a href="{{ route('sectores.edit',$sector->id) }}" class="btn btn-primary"><i data-feather="edit"></i> Editar</a>
      <a href="{{ route('sectores.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <ul class="list-group">
        <li class="list-group-item"><b>ID:</b> {{ $sector->id }}</li>
        <li class="list-group-item"><b>Nombre:</b> {{ $sector->nombre }}</li>
        <li class="list-group-item"><b>Activo:</b> {!! $sector->activo ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>' !!}</li>
        <li class="list-group-item"><b>Actualizado:</b> {{ $sector->updated_at }}</li>
      </ul>
    </div>
    <div class="col-12 col-lg-8">
      <div id="map-sector"></div>
    </div>
    <div class="col-12">
      <label class="form-label mt-3">GeoJSON</label>
      <textarea class="form-control" rows="8" readonly>{{ $sector->area }}</textarea>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const feature = (() => { try { return JSON.parse(@json($sector->area)); } catch { return null; } })();

  const center = [19.1738, -96.1342];
  const map = L.map('map-sector').setView(center, 14);
  const light = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ attribution:'&copy; OpenStreetMap' }).addTo(map);

  if (feature && feature.type === 'Feature') {
    const layer = L.geoJSON(feature, {
      style:{ color:'#2A9DF4', weight:2, fillOpacity: .25 }
    }).addTo(map);
    try { map.fitBounds(layer.getBounds().pad(0.2)); } catch {}
  }
});
</script>
@endpush
