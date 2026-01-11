<?php
/** @var object $stand */
/** @var \Illuminate\Support\Collection $sectores */
?>
@extends('layouts.admin')
@section('title','Editar paradero')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
  /* Mapa grande a la derecha */
  #map-pick{
    height: 100%;
    min-height: 560px;
    border-radius: .75rem;
  }

  /* Inputs compactos */
  .form-label{ margin-bottom: .25rem; font-size: .825rem; }
  .help-xs{ font-size: .78rem; }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h3 class="mb-0">Editar paradero #{{ $stand->id }}</h3>
      <div class="text-muted help-xs">Actualiza datos básicos y fija coordenadas desde el mapa.</div>
    </div>
    <a href="{{ route('admin.taxistands.index') }}" class="btn btn-outline-secondary">
      <i data-feather="arrow-left"></i> Volver
    </a>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">Corrige los siguientes campos:</div>
      <ul class="mb-0">
        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.taxistands.update',$stand->id) }}" autocomplete="off" class="card">
    @csrf @method('PUT')

    <div class="card-body">
      <div class="row g-3">
        {{-- IZQUIERDA: inputs compactos --}}
        <div class="col-lg-4">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label">Nombre *</label>
              <input type="text" name="nombre" class="form-control form-control-sm"
                     value="{{ old('nombre',$stand->nombre) }}" required>
            </div>

            <div class="col-12">
              <label class="form-label">Sector *</label>
              <select name="sector_id" class="form-select form-select-sm" required>
                @foreach($sectores as $sec)
                  <option value="{{ $sec->id }}" @selected(old('sector_id',$stand->sector_id)==$sec->id)>
                    {{ $sec->nombre }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-6">
              <label class="form-label">Latitud *</label>
              <input id="lat" type="number" step="any" name="latitud" class="form-control form-control-sm"
                     value="{{ old('latitud',$stand->latitud) }}" required>
            </div>

            <div class="col-6">
              <label class="form-label">Longitud *</label>
              <input id="lng" type="number" step="any" name="longitud" class="form-control form-control-sm"
                     value="{{ old('longitud',$stand->longitud) }}" required>
            </div>

            <div class="col-6">
              <label class="form-label">Capacidad</label>
              <input type="number" name="capacidad" class="form-control form-control-sm" min="0"
                     value="{{ old('capacidad',$stand->capacidad ?? 0) }}">
            </div>

            <div class="col-6 d-flex align-items-end">
              <label class="form-check mb-1">
                <input class="form-check-input" type="checkbox" name="activo" id="activo" value="1"
                       @checked(old('activo',$stand->activo ?? $stand->active ?? 1)==1)>
                <span class="form-check-label">Activo</span>
              </label>
            </div>

            <div class="col-12"><hr class="my-2"></div>

            <div class="col-12">
              <label class="form-label">Código</label>
              <input class="form-control form-control-sm" value="{{ $stand->codigo }}" readonly>
              <div class="text-muted help-xs mt-1">Útil para enrolar conductor en cola.</div>
            </div>

            <div class="col-12">
              <label class="form-label">QR Secret</label>
              <input class="form-control form-control-sm" value="{{ $stand->qr_secret }}" readonly>
            </div>

            <div class="col-12">
              <label class="form-label mb-1">QR</label>
              <div id="qr" class="border rounded p-2 d-inline-block bg-white"></div>
              <div class="text-muted help-xs mt-1">
                Apunta a: <code>{{ $stand->qr_secret }}</code> (lo lee la app del chofer).
              </div>
            </div>
          </div>
        </div>

        {{-- DERECHA: mapa amplio --}}
        <div class="col-lg-8">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex align-items-center gap-2">
              <button type="button" id="btnPick" class="btn btn-sm btn-outline-primary">
                <i data-feather="map-pin"></i>
                <span id="btnPickText">Elegir en mapa</span>
              </button>
              <span id="pickStatus" class="text-muted help-xs">Click en “Elegir en mapa” para habilitar selección.</span>
            </div>
            <a href="#preview" class="btn btn-sm btn-outline-secondary">
              <i data-feather="arrow-down"></i> Ir a preview
            </a>
          </div>

          <div id="map-pick" class="mb-3"></div>

          <hr id="preview" class="mt-2 mb-0">
          <div class="text-muted help-xs mt-2">
            Tip: Arrastra el marcador para ajuste fino. El mapa actualiza Lat/Lng automáticamente.
          </div>
        </div>
      </div>
    </div>

    <div class="card-footer d-flex justify-content-end gap-2">
      <a href="{{ route('admin.taxistands.index') }}" class="btn btn-outline-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit">
        <i data-feather="save"></i> Guardar cambios
      </button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const latEl = document.getElementById('lat');
  const lngEl = document.getElementById('lng');

  const btnPick = document.getElementById('btnPick');
  const btnPickText = document.getElementById('btnPickText');
  const pickStatus = document.getElementById('pickStatus');

  const lat0 = Number(latEl.value || 0) || 19.432608;     // fallback CDMX
  const lng0 = Number(lngEl.value || 0) || -99.133209;

  const map = L.map('map-pick', { zoomControl: true }).setView([lat0, lng0], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution:'&copy; OSM'
  }).addTo(map);

  let marker = L.marker([lat0, lng0], { draggable: true }).addTo(map);

  const syncInputs = (p) => {
    latEl.value = Number(p.lat).toFixed(6);
    lngEl.value = Number(p.lng).toFixed(6);
  };

  marker.on('dragend', () => syncInputs(marker.getLatLng()));

  // Estado de picking (sin alerts)
  let picking = false;
  const renderPickUi = () => {
    if (!btnPick) return;
    btnPick.classList.toggle('btn-primary', picking);
    btnPick.classList.toggle('btn-outline-primary', !picking);
    btnPickText.textContent = picking ? 'Click en el mapa…' : 'Elegir en mapa';
    pickStatus.textContent = picking
      ? 'Selecciona un punto (un click) para fijar coordenadas.'
      : 'Click en “Elegir en mapa” para habilitar selección.';
  };

  btnPick?.addEventListener('click', () => {
    picking = !picking;
    renderPickUi();
  });

  map.on('click', (ev) => {
    if (!picking) return;
    marker.setLatLng(ev.latlng);
    syncInputs(ev.latlng);
    picking = false;
    renderPickUi();
  });

  // Si cambian inputs manualmente, mueve marcador
  const onInputChange = () => {
    const la = Number(latEl.value);
    const ln = Number(lngEl.value);
    if (!Number.isFinite(la) || !Number.isFinite(ln)) return;
    marker.setLatLng([la, ln]);
    map.panTo([la, ln], { animate: true });
  };
  latEl.addEventListener('change', onInputChange);
  lngEl.addEventListener('change', onInputChange);

  // Importante: recalcular tamaño (Leaflet en layout responsive)
  setTimeout(() => map.invalidateSize(), 200);

  // QR
  const target = @json($stand->qr_secret);
  const qrEl = document.getElementById('qr');
  if (qrEl) new QRCode(qrEl, { text: target, width: 160, height: 160 });
});
</script>
@endpush
