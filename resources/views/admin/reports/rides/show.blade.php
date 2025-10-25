@extends('layouts.admin')
@section('title','Detalle del viaje #'.$ride->id)

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
  /* Mapa */
  #map{height:440px;border-radius:.75rem}

  /* ---------- Timeline horizontal ---------- */
  .timeline-steps{display:flex;flex-wrap:nowrap;gap:1rem;overflow-x:auto;padding:.25rem 0;margin:0}
  .timeline-steps .step{min-width:220px;position:relative;flex:0 0 auto}
  .timeline-steps .step:after{
    content:""; position:absolute; top:14px; left:calc(100% + .5rem); height:2px; width:2rem;
    background:var(--bs-border-color);
  }
  .timeline-steps .step:last-child:after{display:none}
  [data-theme="dark"] .timeline-steps .step:after{background:rgba(255,255,255,.12)}

  .step-card{background:var(--bs-body-bg);border:1px solid var(--bs-border-color);border-radius:.5rem;padding:.6rem .7rem}
  .step-head{display:flex;align-items:center;gap:.5rem;margin-bottom:.25rem}

  /* code box compacto si lo muestras luego */
  pre.codebox{background:var(--bs-tertiary-bg);border-radius:.5rem;padding:.75rem}
</style>
@endpush


@section('content')
<div class="row g-3">
  {{-- Col izquierda: mapa --}}
  <div class="col-lg-8">
    <div class="card shadow-sm border-0">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Mapa del viaje</h5>
        <span class="small text-muted">
          {{ $timeWindow['start']?->format('Y-m-d H:i') ?? '—' }} —
          {{ $timeWindow['end']?->format('Y-m-d H:i') ?? '—' }}
        </span>
      </div>
      <div class="card-body">
        <div id="map"
             data-origin="{{ $ride->origin_lat }},{{ $ride->origin_lng }}"
             data-dest="{{ $ride->dest_lat }},{{ $ride->dest_lng }}"
             data-polyline="{{ $ride->route_polyline ? base64_encode($ride->route_polyline) : '' }}"
             data-crumbs-pickup='@json($crumbsToPickup)'
             data-crumbs-trip='@json($crumbsOnTrip)'>
        </div>
      </div>
    </div>
  </div>

  {{-- Col derecha: resumen --}}
  <div class="col-lg-4">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div>
            <div class="text-muted small">Estado</div>
            <div class="fw-semibold">{{ $ride->status }}</div>
          </div>
          <div class="text-end">
            <div class="text-muted small">Total</div>
            <div class="fw-semibold">{{ $ride->total_amount ? number_format($ride->total_amount,2).' '.$ride->currency : '—' }}</div>
          </div>
        </div>
        <hr>
        <div class="small"><strong>Origen:</strong> {{ $ride->origin_label ?? '—' }}</div>
        <div class="small"><strong>Destino:</strong> {{ $ride->dest_label ?? '—' }}</div>
        <div class="small mt-2"><strong>Duración:</strong> {{ $ride->duration_s ? gmdate('H:i:s',$ride->duration_s) : '—' }}</div>
        <div class="small"><strong>Distancia:</strong> {{ $ride->distance_m ? number_format($ride->distance_m/1000,2).' km' : '—' }}</div>
        <hr>
        <div class="small">
          <div><strong>Solicitado:</strong> {{ $ride->requested_at?->format('Y-m-d H:i') ?? '—' }}</div>
          <div><strong>Aceptado:</strong>   {{ $ride->accepted_at?->format('Y-m-d H:i') ?? '—' }}</div>
          <div><strong>Arribo:</strong>     {{ $ride->arrived_at?->format('Y-m-d H:i')   ?? '—' }}</div>
          <div><strong>Onboard:</strong>    {{ $ride->onboard_at?->format('Y-m-d H:i')  ?? '—' }}</div>
          <div><strong>Finalizado:</strong> {{ $ride->finished_at?->format('Y-m-d H:i') ?? '—' }}</div>
          @if($ride->canceled_at)
            <div class="mt-2 text-danger"><strong>Cancelado:</strong> {{ $ride->canceled_at->format('Y-m-d H:i') }}</div>
            <div class="small text-muted">By: {{ $ride->canceled_by ?? '—' }} | {{ $ride->cancel_reason ?? '' }}</div>
          @endif
        </div>

        <div class="small mt-2">
          <strong>Pasajero:</strong>
          {{ $ride->passenger_name ?? '—' }}
          @if($ride->passenger_phone) <span class="text-muted">({{ $ride->passenger_phone }})</span>@endif
        </div>
        <div class="small"><strong>Canal:</strong> {{ $ride->requested_channel ?? '—' }}</div>
        <div class="small">
          <strong>Tipo:</strong>
          @if($ride->scheduled_for) <span class="badge text-bg-info">Programado</span> @else <span class="badge text-bg-secondary">Inmediato</span> @endif
          @if($viaWave) <span class="badge text-bg-primary ms-1">Por ola</span> @else <span class="badge text-bg-warning ms-1">Asignado</span> @endif
        </div>
      </div>
    </div>

    <div class="card shadow-sm border-0 mt-3">
      <div class="card-header"><h6>Conductor & Vehículo</h6></div>
      <div class="card-body small">
        <div class="mb-2">
          <div class="text-muted">Conductor</div>
          <div class="fw-semibold">
            {{ $driver->name ?? '—' }}
            @if(($driver->phone ?? null)) <span class="text-muted"> · {{ $driver->phone }}</span> @endif
          </div>
        </div>
        <div>
          <div class="text-muted">Vehículo</div>
          <div class="fw-semibold d-flex flex-wrap gap-2 align-items-center">
            @php
              $eco = $vehicle->economico ?? ($fallbackVehicle['economico'] ?? null);
              $plt = $vehicle->plate     ?? ($fallbackVehicle['plate']     ?? null);
            @endphp
            @if($eco)<span class="badge text-bg-dark">Eco {{ $eco }}</span>@endif
            @if($plt)<span class="badge text-bg-secondary">{{ $plt }}</span>@endif
          </div>
          @if(isset($vehicle))
            <div class="text-muted mt-1">
              {{ trim(implode(' ', array_filter([$vehicle->brand ?? null, $vehicle->model ?? null, $vehicle->year ?? null]))) ?: '—' }}
              @if($vehicle->color) · {{ $vehicle->color }} @endif
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Timeline: debajo del mapa, ancho completo --}}
 {{-- Timeline HORIZONTAL de estados --}}
<div class="col-12">
 <div class="row g-3">
  {{-- Col izquierda: mapa --}}
  <div class="col-lg-8"> ... </div>

  {{-- Col derecha: resumen --}}
  <div class="col-lg-4"> ... </div>

  {{-- 👇 Timeline: una sola columna de ancho completo --}}
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Timeline de estados</h6>
        <span class="small text-muted">
          {{ $timeWindow['start']?->format('Y-m-d H:i') ?? '—' }} — {{ $timeWindow['end']?->format('Y-m-d H:i') ?? '—' }}
        </span>
      </div>
      <div class="card-body">
        @if($history->isEmpty())
          <div class="text-muted">Sin eventos</div>
        @else
          <ol class="timeline-steps">
            @foreach($history as $h)
              @php
                $status = (string)($h->new_status ?? '—');
                $badge  = match($status){
                  'requested'=>'secondary','offered'=>'info','accepted'=>'primary',
                  'arrived'=>'warning','onboard'=>'dark','finished'=>'success','canceled'=>'danger', default=>'secondary'
                };
                $meta = is_string($h->meta) ? (json_decode($h->meta,true) ?: []) : ($h->meta ?? []);
                // Busca lat/lng en varias claves posibles
                $lat = $meta['driver_last_lat'] ?? $meta['lat'] ?? ($meta['location']['lat'] ?? null);
                $lng = $meta['driver_last_lng'] ?? $meta['lng'] ?? ($meta['location']['lng'] ?? null);
                $gUrl = ($lat && $lng) ? "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}" : null;
              @endphp
              <li class="step">
                <div class="step-card">
                  <div class="step-head">
                    <span class="badge text-bg-{{ $badge }} text-uppercase">{{ $status }}</span>
                    <span class="small text-muted">{{ $h->created_at?->format('Y-m-d H:i:s') }}</span>
                  </div>
                  @if($gUrl)
                    <div class="d-flex flex-wrap gap-2 small">
                      <a href="{{ $gUrl }}" target="_blank" rel="noopener">📍 {{ number_format((float)$lat,6) }}, {{ number_format((float)$lng,6) }}</a>
                      <button type="button" class="btn btn-xs btn-outline-secondary"
                        data-pan-lat="{{ $lat }}" data-pan-lng="{{ $lng }}">
                        Ver en mapa
                      </button>
                    </div>
                  @endif
                </div>
              </li>
            @endforeach
          </ol>
        @endif
      </div>
    </div>
  </div>
</div>

</div>

</div>
@endsection
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
/* ---------- Helpers ---------- */
function decodePolyline(str){
  let i=0,lat=0,lng=0,c=[];
  while(i<str.length){
    let b,sh=0,re=0;
    do{ b=str.charCodeAt(i++)-63; re|=(b&0x1f)<<sh; sh+=5 }while(b>=0x20);
    let dlat=(re&1)?~(re>>1):(re>>1); lat+=dlat;
    sh=0; re=0;
    do{ b=str.charCodeAt(i++)-63; re|=(b&0x1f)<<sh; sh+=5 }while(b>=0x20);
    let dlng=(re&1)?~(re>>1):(re>>1); lng+=dlng;
    c.push([lat/1e5,lng/1e5]);
  }
  return c;
}
function toLatLngPair(arr){
  const a=Array.isArray(arr)?arr:[]; const la=parseFloat(a[0]), ln=parseFloat(a[1]);
  return (Number.isFinite(la)&&Number.isFinite(ln))?[la,ln]:null;
}
async function fetchPolylineIfMissing(origin,dest){
  try{
    const r=await fetch("{{ route('panel.geo.route') }}",{
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
      body:JSON.stringify({from:{lat:origin[0],lng:origin[1]},to:{lat:dest[0],lng:dest[1]},mode:'driving'})
    });
    const d=await r.json(); return d&&d.polyline?d.polyline:null;
  }catch(e){ return null; }
}

/* ---------- Mapa (tema-aware + capas de realce de calles) ---------- */
(function(){
  const el     = document.getElementById('map');
  const origin = toLatLngPair((el.dataset.origin||'').split(','));
  const dest   = toLatLngPair((el.dataset.dest  ||'').split(','));
  const pickup = JSON.parse(el.dataset.crumbsPickup || '[]');
  const trip   = JSON.parse(el.dataset.crumbsTrip   || '[]');
  let poly64   = el.dataset.polyline || '';

  const map = L.map(el, { zoomControl:false, attributionControl:false, preferCanvas:true, fadeAnimation:true });

  // Capas base/overlays (Carto)
  const urls = {
    light:               'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
    dark:                'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
    voyager_no_labels:   'https://{s}.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}{r}.png',
    dark_only_labels:    'https://{s}.basemaps.cartocdn.com/dark_only_labels/{z}/{x}/{y}{r}.png'
  };

  const tileLight  = L.tileLayer(urls.light, {maxZoom:19, detectRetina:true});
  const tileDark   = L.tileLayer(urls.dark,  {maxZoom:19, detectRetina:true});
  // overlay para realzar calles (ligero contraste sobre dark)
  const streetsOverlay   = L.tileLayer(urls.voyager_no_labels, {maxZoom:19, detectRetina:true, opacity:0.25});
  // labels para dark (para que textos no se pierdan)
  const labelsDark       = L.tileLayer(urls.dark_only_labels, {maxZoom:19, detectRetina:true, opacity:0.85});

  function isDark(){
    return (document.documentElement.getAttribute('data-theme')||'default').toLowerCase()==='dark';
  }
  function applyTheme(){
    const darkWanted = isDark();
    // limpiar
    [tileLight,tileDark,streetsOverlay,labelsDark].forEach(l=>{ if(map.hasLayer(l)) map.removeLayer(l); });
    if (darkWanted){
      tileDark.addTo(map).on('tileerror', ()=>{ if(map.hasLayer(tileDark)){map.removeLayer(tileDark); tileLight.addTo(map);} });
      streetsOverlay.addTo(map);      // realce de calles
      labelsDark.addTo(map);          // labels legibles en dark
    } else {
      tileLight.addTo(map);
      // En light no cargo overlays (no son necesarios); si quisieras más detalle:
      // streetsOverlay.setOpacity(0.15).addTo(map);
    }
  }
  applyTheme();
  new MutationObserver(applyTheme).observe(document.documentElement,{attributes:true,attributeFilter:['data-theme']});

  setTimeout(()=>map.invalidateSize(), 0);

  (async () => {
    if (!poly64 && origin && dest) {
      const poly = await fetchPolylineIfMissing(origin, dest);
      if (poly) poly64 = btoa(poly);
    }

    let bounds = null;

    // Ruta planeada (con halo para contraste)
    if (poly64){
      const coords = decodePolyline(atob(poly64));
      if (coords.length>1){
        // halo debajo
        L.polyline(coords, { weight:8, opacity:.35, color:'#000000' }).addTo(map);
        // línea principal
        const routeLine = L.polyline(coords, { weight:4, opacity:.9, color:'#3b82f6' }).addTo(map);
        bounds = routeLine.getBounds();
      }
    }

    // Breadcrumbs hacia pickup (verde punteado)
    if (pickup && pickup.length>1){
      const p = L.polyline(pickup, {dashArray:'8,8', weight:4, color:'#22c55e'}).addTo(map);
      bounds = bounds ? bounds.extend(p.getBounds()) : p.getBounds();
    }
    // Breadcrumbs con pasajero (ámbar sólido)
    if (trip && trip.length>1){
      const t = L.polyline(trip, {weight:5, color:'#f59e0b'}).addTo(map);
      bounds = bounds ? bounds.extend(t.getBounds()) : t.getBounds();
    }

    // Pines origen/destino
    if (origin){
      L.marker(origin).addTo(map).bindTooltip('Origen',{direction:'top',offset:[0,-8]});
      bounds = bounds ? bounds.extend(L.latLngBounds([origin,origin])) : L.latLngBounds([origin,origin]);
    }
    if (dest){
      // pin rojo pequeño para distinguir destino
      const endIcon = L.divIcon({
        className:'end-dot', iconSize:[14,14], iconAnchor:[7,7],
        html:'<div style="width:14px;height:14px;border-radius:50%;background:#ef4444;box-shadow:0 0 0 3px rgba(0,0,0,.35)"></div>'
      });
      L.marker(dest,{icon:endIcon}).addTo(map).bindTooltip('Destino',{direction:'top',offset:[0,-8]});
      bounds = bounds ? bounds.extend(L.latLngBounds([dest,dest])) : L.latLngBounds([dest,dest]);
    }

    if (bounds) map.fitBounds(bounds.pad(0.25)); else map.setView([19.4326,-99.1332], 11);
  })();

  // Pan/Pin temporal desde el timeline
  window.__rideMap = { map, pin:null };
  function panToOnTimeline(lat,lng){
    const m = window.__rideMap.map;
    if (window.__rideMap.pin){ m.removeLayer(window.__rideMap.pin); window.__rideMap.pin=null; }
    window.__rideMap.pin = L.marker([lat,lng]).addTo(m);
    m.setView([lat,lng], Math.max(m.getZoom(), 16), { animate:true });
  }
  document.addEventListener('click', (ev)=>{
    const btn = ev.target.closest('[data-pan-lat][data-pan-lng]');
    if (!btn) return;
    const lat = parseFloat(btn.getAttribute('data-pan-lat'));
    const lng = parseFloat(btn.getAttribute('data-pan-lng'));
    if (Number.isFinite(lat) && Number.isFinite(lng)) panToOnTimeline(lat,lng);
  });
})();
</script>
@endpush
