<?php
/**
 * Variables requeridas:
 * - $action (string) ruta del form
 * - $method ('POST'|'PUT')
 * - $stand (stdClass|null)
 * - $sectores (Collection id=>nombre)
 */
$stand     = $stand ?? null;
$tenantLoc = $tenantLoc ?? null;

$method    = $method ?? 'POST';
$nombre    = old('nombre',    $stand->nombre    ?? '');
$latitud   = old('latitud',   $stand->latitud   ?? '');
$longitud  = old('longitud',  $stand->longitud  ?? '');
$capacidad = old('capacidad', $stand->capacidad ?? 0);
$sector_id = old('sector_id', $stand->sector_id ?? '');
$activo    = (int)old('activo', (string)($stand->activo ?? 1));
$centerLat = $latitud  !== '' ? (float)$latitud  : 19.1738;
$centerLng = $longitud !== '' ? (float)$longitud : -96.1342;


/**
 * Centro del mapa:
 * 1) Si el form trae lat/lng -> esas
 * 2) Si no, usa lat/lng del tenant (si existen)
 * 3) Fallback Veracruz (como tienes ahora)
 */
if ($latitud !== '' && $longitud !== '') {
    $centerLat = (float)$latitud;
    $centerLng = (float)$longitud;
    $centerSource = 'form';
} elseif ($tenantLoc && $tenantLoc->latitud !== null && $tenantLoc->longitud !== null) {
    $centerLat = (float)$tenantLoc->latitud;
    $centerLng = (float)$tenantLoc->longitud;
    $centerSource = 'tenant';
} else {
    $centerLat = 19.1738;
    $centerLng = -96.1342;
    $centerSource = 'fallback';
}
$centerZoom = 13;



?>
<form action="{{ $action }}" method="POST" novalidate>
  @csrf
  @if($method === 'PUT') @method('PUT') @endif

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card">
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Nombre *</label>
            <input name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ $nombre }}" required>
            @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="mb-3">
            <label class="form-label">Sector *</label>
            <select name="sector_id" class="form-select @error('sector_id') is-invalid @enderror" required>
              <option value="">— Seleccionar —</option>
              @foreach($sectores as $id => $n)
                <option value="{{ $id }}" @selected((string)$sector_id === (string)$id)>{{ $n }}</option>
              @endforeach
            </select>
            @error('sector_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="row g-2 mb-3">
            <div class="col">
              <label class="form-label">Latitud *</label>
              <input name="latitud" id="inLat" class="form-control @error('latitud') is-invalid @enderror"
                     value="{{ $latitud }}" required>
              @error('latitud') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col">
              <label class="form-label">Longitud *</label>
              <input name="longitud" id="inLng" class="form-control @error('longitud') is-invalid @enderror"
                     value="{{ $longitud }}" required>
              @error('longitud') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Capacidad</label>
            <input type="number" min="0" name="capacidad" class="form-control @error('capacidad') is-invalid @enderror"
                   value="{{ $capacidad }}">
            @error('capacidad') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="activo" id="inActivo" value="1" @checked($activo===1)>
            <label class="form-check-label" for="inActivo">Activo</label>
          </div>

          <div class="d-grid gap-2">
            <button class="btn btn-success" type="submit">
              <i data-feather="save"></i> Guardar
            </button>
            <a href="{{ route('admin.taxistands.index') }}" class="btn btn-outline-secondary">Volver</a>
          </div>
        </div>
      </div>

      @if ($errors->any())
        <div class="alert alert-danger mt-3">
          <strong>Revisa los campos:</strong>
          <ul class="mb-0">
            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
          </ul>
        </div>
      @endif
    </div>

    <div class="col-12 col-lg-8" id="preview">
      <div class="card">
        <div class="card-body p-2">
          <div class="d-flex justify-content-between align-items-center px-1">
            <div class="small text-muted">
              Click en el mapa para colocar el marcador, o arrástralo.
            </div>
            <div>
              <button class="btn btn-sm btn-outline-secondary" type="button" id="btnCenter">
                <i data-feather="crosshair"></i> Centrar
              </button>
            </div>
          </div>
          <div id="mapStand" style="height: calc(100vh - 220px); min-height: 420px;"></div>
        </div>
      </div>
    </div>
  </div>
</form>

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
  /* badge de stands por si luego usas divIcon */
  .cc-stand-badge {
    background:#ffc107; color:#222; font-weight:700; width:26px; height:26px;
    border-radius:50%; display:flex; align-items:center; justify-content:center;
    box-shadow:0 1px 3px rgba(0,0,0,.25);
  }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
  // === DEBUG TAXISTANDS CENTER ===
  window.__DBG_TAXISTAND__ = {
    tenantId   : @json($tenantId ?? null),
    tenantLoc  : @json($tenantLoc ?? null),
    formLat    : @json($latitud),
    formLng    : @json($longitud),
    chosen     : { lat: {{ $centerLat }}, lng: {{ $centerLng }}, zoom: {{ $centerZoom }} },
    source     : @json($centerSource)
  };
  console.log('[TaxiStands] Center debug →', window.__DBG_TAXISTAND__);
</script>



<script>
(function(){
  const centerDefault = [{{ $centerLat }}, {{ $centerLng }}];
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

  // Capas base
  const baseLight = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
    attribution: '&copy; OpenStreetMap contributors'
  });
  const baseDark  = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',{
    attribution: '&copy; OSM & Carto'
  });

console.log('[TaxiStands] init map setView', [{{ $centerLat }}, {{ $centerLng }}], 'z=', {{ $centerZoom }});

const map = L.map('mapStand',{ worldCopyJump:false, maxBoundsViscosity:1.0 })
             .setView([{{ $centerLat }}, {{ $centerLng }}], {{ $centerZoom }});

// (Opcional) marca temporal para validar el centro elegido
L.circleMarker([{{ $centerLat }}, {{ $centerLng }}], {radius:4}).addTo(map)
 .bindTooltip('CENTER: ' + {{ $centerLat }} + ', ' + {{ $centerLng }}).openTooltip();


  let currentBase = isDark ? baseDark : baseLight;
  currentBase.addTo(map);

  const icon = L.icon({
    iconUrl: '/images/stand.png',
    iconSize: [28, 28],
    iconAnchor: [14, 28],
    popupAnchor: [0, -24],
    className: 'cc-stand-icon'
  });

  let marker = null;
  const inLat = document.getElementById('inLat');
  const inLng = document.getElementById('inLng');

  function fmt(n){ const v=Number(n); return Number.isFinite(v)? v.toFixed(6):''; }
  function setInputs(latlng){
    if (inLat) inLat.value = fmt(latlng.lat);
    if (inLng) inLng.value = fmt(latlng.lng);
  }
  function placeMarker(latlng){
    if (!marker) {
      marker = L.marker(latlng, { draggable:true, icon }).addTo(map);
      marker.on('dragend', e => setInputs(e.target.getLatLng()));
    } else {
      marker.setLatLng(latlng);
    }
    setInputs(latlng);
  }

  // Si hay coordenadas, coloca marcador inicial
  if ({{ $latitud !== '' && $longitud !== '' ? 'true' : 'false' }}) {
    placeMarker({ lat: {{ $centerLat }}, lng: {{ $centerLng }} });
  }

  // Click en mapa
  map.on('click', (ev) => placeMarker(ev.latlng));

  // Inputs manuales
  [inLat, inLng].forEach(el => el?.addEventListener('change', () => {
    const lat = parseFloat(inLat.value), lng = parseFloat(inLng.value);
    if (Number.isFinite(lat) && Number.isFinite(lng)) {
      const ll = { lat, lng };
      placeMarker(ll);
      map.setView(ll, map.getZoom());
    }
  }));

  // Botón centrar
  document.getElementById('btnCenter')?.addEventListener('click', () => {
    map.setView(marker ? marker.getLatLng() : centerDefault, 15);
  });

  // Tema dinámico
  window.addEventListener('theme:changed', (e) => {
    map.removeLayer(currentBase);
    currentBase = (e.detail?.theme === 'dark') ? baseDark : baseLight;
    currentBase.addTo(map);
    setTimeout(() => map.invalidateSize(), 100);
  });

  // Invalidate inicial por si el layout cambia tarde
  setTimeout(() => map.invalidateSize(), 150);
})();
</script>
@endpush
