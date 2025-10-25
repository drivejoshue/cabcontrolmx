@extends('layouts.admin')
@section('title','Reporte de viajes')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
  .map-mini { height: 120px; border-radius: .75rem; }
  .kpi .card { min-height: 110px; }
</style>
@endpush

@section('content')
<div class="card shadow-sm border-0 mb-3">
  <div class="card-body">
    <form class="row g-2" method="GET" action="{{ route('admin.reports.rides') }}">
      <div class="col-sm-3">
        <label class="form-label">Desde</label>
        <input type="date" name="from" class="form-control" value="{{ $from }}">
      </div>
      <div class="col-sm-3">
        <label class="form-label">Hasta</label>
        <input type="date" name="to" class="form-control" value="{{ $to }}">
      </div>
      <div class="col-sm-3">
        <label class="form-label">Estado</label>
        <select name="status" class="form-select">
          <option value="">— Todos —</option>
          @foreach(['finished','canceled','offered','accepted','arrived','onboard'] as $s)
            <option value="{{ $s }}" @selected($status===$s)>{{ ucfirst($s) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-sm-3 d-flex align-items-end gap-2">
        <button class="btn btn-primary shadow">Filtrar</button>
        <a class="btn btn-outline-secondary" href="{{ route('admin.reports.rides.csv', request()->query()) }}">CSV</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 kpi">
  <div class="col-md-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="text-muted small">Total</div>
        <div class="fs-3 fw-bold">{{ $totals['total'] }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="text-muted small">Finalizados</div>
        <div class="fs-3 fw-bold">{{ $totals['finished'] }}</div>
        <div class="small text-muted"> Cobro registrado en {{ $totals['collect_rate_pct'] ?? 0 }}%</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="text-muted small">Cancelados</div>
        <div class="fs-3 fw-bold">{{ $totals['canceled'] }}</div>
        <div class="small text-muted">{{ $totals['cancel_rate'] }}% cancelación</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="text-muted small">Ingresos</div>
        <div class="small text-muted">Cotizado (suma / prom)</div>
<div class="fw-semibold">
  {{ number_format($totals['sum_quote'] ?? 0,2) }}
  <span class="text-muted">/</span>
  {{ number_format($totals['avg_quote'] ?? 0,2) }}
</div>

<div class="small text-muted mt-2">Cobrado (suma / prom)</div>
<div class="fw-semibold">
  {{ number_format($totals['sum_collected'] ?? 0,2) }}
  <span class="text-muted">/</span>
  {{ number_format($totals['avg_collected'] ?? 0,2) }}
</div>

<div class="small mt-2 {{ ($totals['delta_sum'] ?? 0)>=0 ? 'text-success' : 'text-danger' }}">
  Δ suma: {{ number_format($totals['delta_sum'] ?? 0,2) }}


        </div>
      </div>
    </div>
  </div>
</div>


<div class="card shadow-sm border-0 mt-3">
  <div class="card-body">
    <div class="table-responsive">
     <table class="table align-middle">
  <thead>
    <tr>
      <th>ID</th>
      <th>Estado</th>
      <th>Fecha</th>
      <th>Origen → Destino</th>
      <th>Duración</th>
      <th>Distancia</th>
      <th>Quote</th>     {{-- 💡 nuevo --}}
      <th>Cobrado</th>   {{-- 💡 nuevo --}}
      <th></th>
    </tr>
  </thead>
  <tbody>
  @forelse($rides as $r)
    <tr>
      <td>#{{ $r->id }}</td>
      <td>
        @if($r->status==='finished')
          <span class="badge text-bg-success">finished</span>
        @elseif($r->status==='canceled')
          <span class="badge text-bg-danger">canceled</span>
        @else
          <span class="badge text-bg-secondary">{{ $r->status }}</span>
        @endif
      </td>
      <td>
        <div class="small">{{ $r->requested_at?->format('Y-m-d H:i') }}</div>
        @if($r->scheduled_for)
          <div class="small text-muted">Prog: {{ $r->scheduled_for->format('Y-m-d H:i') }}</div>
        @endif
      </td>
      <td>
        <div class="small fw-semibold">{{ $r->origin_label ?? 'Origen' }}</div>
        <div class="small text-muted">→ {{ $r->dest_label ?? 'Destino' }}</div>
        <div class="small text-muted">
          {{ $r->passenger_name ?? '—' }}
          @if($r->passenger_phone) · {{ $r->passenger_phone }} @endif
        </div>
        <div class="small d-flex flex-wrap gap-1">
          @if($r->scheduled_for)
            <span class="badge rounded-pill text-bg-info">Programado</span>
          @endif
          <span class="badge rounded-pill text-bg-light border">{{ $r->requested_channel ?? '—' }}</span>
          @if($r->vehicle_economico)
            <span class="badge rounded-pill text-bg-dark">Eco {{ $r->vehicle_economico }}</span>
          @endif
        </div>
        @if($r->driver_name)
          <div class="small text-muted mt-1">
            Conductor: {{ $r->driver_name }}
            @if($r->driver_phone) · {{ $r->driver_phone }} @endif
          </div>
        @endif
      </td>
      <td class="small">{{ $r->duration_s ? gmdate('H:i:s', $r->duration_s) : '—' }}</td>
      <td class="small">{{ $r->distance_m ? number_format($r->distance_m/1000,2) .' km' : '—' }}</td>

      {{-- Quote calculado por el sistema (siempre viene) --}}
      <td class="small">
        {{ isset($r->quoted_amount) ? number_format($r->quoted_amount,2).' '.$r->currency : '—' }}
      </td>

      {{-- Cobrado (puede ser null) --}}
      <td class="small">
        {{ isset($r->total_amount) && $r->total_amount !== null ? number_format($r->total_amount,2).' '.$r->currency : '—' }}
      </td>

      <td>
        <a href="{{ route('admin.reports.rides.show',$r->id) }}" class="btn btn-sm btn-outline-primary">Ver</a>
      </td>
    </tr>
  @empty
    <tr><td colspan="9" class="text-center text-muted">Sin resultados</td></tr>
  @endforelse
  </tbody>
</table>
    </div>

   <div class="d-flex justify-content-end">
  {{ $rides->onEachSide(1)->links('vendor.pagination.bootstrap-5') }}
</div>
  </div>
</div>
@endsection
@push('styles')
<style>
  .table td, .table th { vertical-align: middle; }
  .table .text-truncate { max-width: 260px; }
  .kpi .card { min-height: 110px; }
  thead.table-light { position: sticky; top: 0; }
  .pagination { margin: .25rem 0 0; }
  .pagination .page-link { padding: .25rem .6rem; line-height: 1.2; }
  .pagination .page-item:first-child .page-link,
  .pagination .page-item:last-child  .page-link { border-radius: .5rem; }
</style>
@endpush
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Decodificador
function decodePolyline(str){let i=0,lat=0,lng=0,c=[];while(i<str.length){let b,sh=0,re=0;do{b=str.charCodeAt(i++)-63;re|=(b&0x1f)<<sh;sh+=5}while(b>=0x20);let dlat=(re&1)?~(re>>1):(re>>1);lat+=dlat;sh=0;re=0;do{b=str.charCodeAt(i++)-63;re|=(b&0x1f)<<sh;sh+=5}while(b>=0x20);let dlng=(re&1)?~(re>>1):(re>>1);lng+=dlng;c.push([lat/1e5,lng/1e5])}return c}
function toLatLngPair(list){const la=parseFloat(list?.[0]), ln=parseFloat(list?.[1]);return (Number.isFinite(la)&&Number.isFinite(ln))?[la,ln]:null}
async function fetchPolylineIfMissing(origin,dest){
  try{
    const r=await fetch("{{ route('panel.geo.route') }}",{
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
      body:JSON.stringify({from:{lat:origin[0],lng:origin[1]},to:{lat:dest[0],lng:dest[1]},mode:'driving'})
    });
    const d=await r.json(); return d&&d.polyline?d.polyline:null;
  }catch(e){return null}
}

document.querySelectorAll('.map-mini').forEach(async function(el){
  const origin = toLatLngPair((el.dataset.origin||'').split(','));
  const dest   = toLatLngPair((el.dataset.dest  ||'').split(','));
  let poly64   = el.dataset.polyline || '';

  const map = L.map(el,{zoomControl:false,attributionControl:false,dragging:false,scrollWheelZoom:false});
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);

  if(!poly64 && origin && dest){
    const poly = await fetchPolylineIfMissing(origin,dest);
    if(poly) poly64 = btoa(poly);
  }

  let bounds = null;

  // Dibujar polyline si existe
  if(poly64){
    const coords = decodePolyline(atob(poly64));
    if(coords.length>1){
      const line = L.polyline(coords,{weight:3}).addTo(map);
      bounds = line.getBounds();
    }
  }

  // 🔴 Agregar SIEMPRE los pines (aunque haya polyline)
  if(origin){
    const o = L.circleMarker(origin,{radius:4,weight:2,fillOpacity:1});
    o.addTo(map);
    bounds = bounds ? bounds.extend(L.latLngBounds([origin,origin])) : L.latLngBounds([origin,origin]);
  }
  if(dest){
    const d = L.circleMarker(dest,{radius:4,weight:2,fillOpacity:1});
    d.addTo(map);
    bounds = bounds ? bounds.extend(L.latLngBounds([dest,dest])) : L.latLngBounds([dest,dest]);
  }

  // Fallback: recta O→D si no hubo polyline ni bounds
  if(!bounds && origin && dest){
    const group = L.layerGroup();
    L.circleMarker(origin,{radius:4,weight:2,fillOpacity:1}).addTo(group);
    L.circleMarker(dest,{radius:4,weight:2,fillOpacity:1}).addTo(group);
    L.polyline([origin,dest],{weight:3}).addTo(group);
    group.addTo(map);
    bounds = L.latLngBounds([origin,dest]);
  }

  if(bounds) map.fitBounds(bounds.pad(0.3));
});
</script>
@endpush
