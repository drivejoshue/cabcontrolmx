@extends('layouts.admin')

@section('title','Detalle de cliente')

@section('content')
<div class="container-fluid p-0">

  <div class="d-flex align-items-start justify-content-between mb-3">
    <div>
      <div class="text-muted small">Cliente Ref: <code>{{ $ref }}</code></div>
      <h1 class="h3 mb-1">{{ $client->passenger_name ?? 'Sin nombre' }}</h1>
      <div class="text-muted">
        {{ $client->passenger_phone ?? 'Sin teléfono' }}
        @if(!empty($client->passenger_email)) · {{ $client->passenger_email }} @endif
        @if(!empty($client->is_corporate)) · <span class="badge bg-info">Corporativo</span> @endif
      </div>
    </div>

    <div class="text-end">
      @php
        $cls = $tier==='gold' ? 'bg-warning text-dark' : ($tier==='silver' ? 'bg-secondary' : 'bg-dark');
      @endphp
      <div class="mb-2">
        <span class="badge {{ $cls }} px-3 py-2">{{ strtoupper($tier) }}</span>
      </div>
      <a href="{{ route('admin.reports.clients') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
    </div>
  </div>

  {{-- Summary cards --}}
  <div class="row g-3 mb-3">
    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Rides</div>
          <div class="h4 mb-0">{{ number_format((int)$client->total_rides) }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Finalizados</div>
          <div class="h4 mb-0">{{ number_format((int)$client->finished_rides) }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Cancelados</div>
          <div class="h4 mb-0">{{ number_format((int)$client->canceled_rides) }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Consumo histórico</div>
          <div class="h4 mb-0">${{ number_format((float)$client->lifetime_spent, 2) }}</div>
          <div class="text-muted small">Último: {{ $client->last_ride_at ? \Carbon\Carbon::parse($client->last_ride_at)->format('d M Y H:i') : '-' }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Top by vehicle/driver --}}
  <div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <h6 class="mb-2">Top taxis (vehículos) por consumo</h6>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Taxi</th>
                  <th class="text-center">Rides</th>
                  <th class="text-center">Finalizados</th>
                  <th class="text-end">Consumo</th>
                </tr>
              </thead>
              <tbody>
                @forelse($byVehicle as $v)
                  <tr>
                    <td>
                      <div class="fw-semibold">{{ $v->economico ? 'Econ. '.$v->economico : 'Vehículo #'.$v->vehicle_id }}</div>
                      <div class="text-muted small">{{ $v->brand }} {{ $v->model }} · {{ $v->plate }}</div>
                    </td>
                    <td class="text-center">{{ (int)$v->rides_total }}</td>
                    <td class="text-center">{{ (int)$v->rides_finished }}</td>
                    <td class="text-end">${{ number_format((float)$v->spent, 2) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-center text-muted py-3">Sin datos.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <h6 class="mb-2">Top conductores por consumo</h6>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Conductor</th>
                  <th class="text-center">Rides</th>
                  <th class="text-center">Finalizados</th>
                  <th class="text-end">Consumo</th>
                </tr>
              </thead>
              <tbody>
                @forelse($byDriver as $d)
                  <tr>
                    <td class="fw-semibold">{{ $d->name ?? ('Driver #'.$d->driver_id) }}</td>
                    <td class="text-center">{{ (int)$d->rides_total }}</td>
                    <td class="text-center">{{ (int)$d->rides_finished }}</td>
                    <td class="text-end">${{ number_format((float)$d->spent, 2) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-center text-muted py-3">Sin datos.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Rides list --}}
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <h6 class="mb-2">Historial de rides (tenant actual)</h6>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Estado</th>
              <th>Taxi</th>
              <th>Conductor</th>
              <th>Origen</th>
              <th>Destino</th>
              <th class="text-end">Dist (km)</th>
              <th class="text-end">Dur (min)</th>
              <th class="text-end">Monto</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rides as $r)
              @php
                $amount = $r->agreed_amount ?? $r->total_amount ?? $r->quoted_amount ?? 0;
                $km = $r->distance_m ? ($r->distance_m / 1000) : null;
                $min = $r->duration_s ? ($r->duration_s / 60) : null;
                $dt = $r->finished_at ?? $r->requested_at ?? null;
              @endphp
              <tr>
                <td class="fw-semibold">#{{ $r->id }}</td>
                <td><span class="badge bg-secondary">{{ $r->status }}</span></td>
                <td>
                  <div class="fw-semibold">{{ $r->vehicle_economico ? 'Econ. '.$r->vehicle_economico : '-' }}</div>
                  <div class="text-muted small">{{ $r->vehicle_brand }} {{ $r->vehicle_model }} · {{ $r->vehicle_plate }}</div>
                </td>
                <td>{{ $r->driver_name ?? '-' }}</td>
                <td class="text-truncate" style="max-width:240px;">{{ $r->origin_label ?? '-' }}</td>
                <td class="text-truncate" style="max-width:240px;">{{ $r->dest_label ?? '-' }}</td>
                <td class="text-end">{{ $km !== null ? number_format($km, 2) : '-' }}</td>
                <td class="text-end">{{ $min !== null ? number_format($min, 0) : '-' }}</td>
                <td class="text-end">${{ number_format((float)$amount, 2) }} {{ $r->currency ?? 'MXN' }}</td>
                <td class="text-muted small">{{ $dt ? \Carbon\Carbon::parse($dt)->format('d M Y H:i') : '-' }}</td>
              </tr>
            @empty
              <tr><td colspan="10" class="text-center text-muted py-4">Sin rides.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">
        {{ $rides->links() }}
      </div>
    </div>
  </div>

</div>
@endsection
