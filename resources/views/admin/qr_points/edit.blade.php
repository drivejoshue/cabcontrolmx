@extends('layouts.admin')
@section('title','Editar QR Point')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
  #map-pick { height: 420px; border-radius: .5rem; }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h3 class="mb-0">Editar QR Point</h3>
      <div class="text-muted small">Código: <span class="mono">{{ $item->code }}</span></div>
    </div>
    <a href="{{ route('qr-points.index') }}" class="btn btn-outline-secondary">
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

  <form method="POST" action="{{ route('qr-points.update', $item) }}" autocomplete="off" class="card">
    @csrf @method('PUT')
    <div class="card-body">

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nombre *</label>
          <input type="text" name="name" class="form-control" value="{{ old('name', $item->name) }}" required maxlength="120">
        </div>

        <div class="col-md-6">
          <label class="form-label">Dirección (opcional)</label>
          <input type="text" name="address_text" class="form-control" value="{{ old('address_text', $item->address_text) }}" maxlength="255">
        </div>

        <div class="col-md-4">
          <label class="form-label">Latitud *</label>
          <input id="lat" type="number" step="any" name="lat" class="form-control"
                 value="{{ old('lat', $item->lat) }}" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Longitud *</label>
          <input id="lng" type="number" step="any" name="lng" class="form-control"
                 value="{{ old('lng', $item->lng) }}" required>
        </div>

        <div class="col-md-4">
          <label class="form-label d-block">Estado</label>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="active" value="1"
                   {{ old('active', (int)$item->active) ? 'checked' : '' }}>
            <label class="form-check-label">Activo</label>
          </div>
        </div>
      </div>

      <hr>

      <div class="mb-2 d-flex align-items-center gap-2">
        <button type="button" id="btnPick" class="btn btn-sm btn-outline-primary">
          <i data-feather="map-pin"></i> Elegir en mapa
        </button>
        <span class="text-muted small">Click en el mapa para fijar coordenadas. Puedes arrastrar el marcador.</span>
      </div>

      <div id="map-pick" class="mb-3"></div>

      @php $publicUrl = url('/q/'.$item->code); @endphp
      <div class="alert alert-secondary py-2 mb-0">
        <div class="fw-semibold mb-1">Link público</div>
        <div class="d-flex gap-2 align-items-center">
          <input class="form-control form-control-sm mono" value="{{ $publicUrl }}" readonly>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="{{ $publicUrl }}">Copiar</button>
          <a class="btn btn-sm btn-primary" href="{{ $publicUrl }}" target="_blank" rel="noopener">Abrir</a>
        </div>
      </div>

    </div>

    <div class="card-footer d-flex justify-content-end gap-2">
      <a href="{{ route('qr-points.index') }}" class="btn btn-outline-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit">
        <i data-feather="save"></i> Guardar cambios
      </button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Copy
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    const text = btn.getAttribute('data-copy') || '';
    try { await navigator.clipboard.writeText(text); btn.textContent='Copiado'; setTimeout(()=>btn.textContent='Copiar', 900); } catch(_){}
  });

  const latEl = document.getElementById('lat');
  const lngEl = document.getElementById('lng');

  const startLat = Number(latEl.value);
  const startLng = Number(lngEl.value);

  const map = L.map('map-pick').setView([startLat, startLng], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OSM'
  }).addTo(map);

  let marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);

  function syncInputs(lat, lng) {
    latEl.value = Number(lat).toFixed(6);
    lngEl.value = Number(lng).toFixed(6);
  }

  marker.on('dragend', () => {
    const p = marker.getLatLng();
    syncInputs(p.lat, p.lng);
  });

  function moveMarkerFromInputs() {
    const lat = Number(latEl.value);
    const lng = Number(lngEl.value);
    if (!isFinite(lat) || !isFinite(lng)) return;
    marker.setLatLng([lat, lng]);
    map.panTo([lat, lng], { animate: true });
  }
  latEl.addEventListener('change', moveMarkerFromInputs);
  lngEl.addEventListener('change', moveMarkerFromInputs);

  let picking = false;
  const btnPick = document.getElementById('btnPick');
  btnPick?.addEventListener('click', () => {
    picking = !picking;
    btnPick.classList.toggle('btn-outline-primary', !picking);
    btnPick.classList.toggle('btn-primary', picking);
    btnPick.innerHTML = picking
      ? '<i data-feather="x-circle"></i> Cancelar selección'
      : '<i data-feather="map-pin"></i> Elegir en mapa';
    if (window.feather) window.feather.replace();
  });

  map.on('click', (ev) => {
    if (!picking) return;
    const { lat, lng } = ev.latlng;
    marker.setLatLng(ev.latlng);
    syncInputs(lat, lng);
    picking = false;
    btnPick.classList.remove('btn-primary');
    btnPick.classList.add('btn-outline-primary');
    btnPick.innerHTML = '<i data-feather="map-pin"></i> Elegir en mapa';
    if (window.feather) window.feather.replace();
  });
});
</script>
@endpush
