@extends('layouts.partner')

@section('title', 'Reportes · Conductores')
@section('page-id','partner-reports-drivers')

@php
  $dateFieldOptions = [
    'requested_at' => 'requested_at',
    'created_at' => 'created_at',
    'accepted_at' => 'accepted_at',
    'finished_at' => 'finished_at',
    'canceled_at' => 'canceled_at',
  ];

  $qs = request()->query();

  $badge = function($st){
    return match((string)$st) {
      'idle' => 'bg-success-lt text-success',
      'on_ride' => 'bg-primary-lt text-primary',
      'offline' => 'bg-secondary-lt text-secondary',
      default => 'bg-warning-lt text-warning',
    };
  };
@endphp

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h1 class="h3 mb-0">Reportes · Conductores</h1>
    <div class="text-muted small">Productividad y totales por conductor</div>
  </div>

  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary"
       href="{{ route('partner.reports.drivers.exportCsv', $qs) }}">
      <i class="ti ti-download me-1"></i> Export CSV
    </a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">

    

      <div class="col-md-4">
        <label class="form-label">Buscar</label>
        <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Nombre/teléfono/email">
      </div>

      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit">
          <i class="ti ti-filter me-1"></i> Filtrar
        </button>
        <a class="btn btn-outline-secondary w-100" href="{{ route('partner.reports.drivers.index') }}">
          Limpiar
        </a>
      </div>

    </form>
  </div>
</div>



<div class="card">
  <div class="card-header">
    <h5 class="card-title mb-0">Conductores</h5>
  </div>

  <div class="table-responsive">
    <table class="table table-vcenter mb-0">
      <thead>
      <tr>
        <th>ID</th>
        <th>Nombre</th>
        <th>Status</th>
        <th>Teléfono</th>
        <th class="text-end">Rides</th>
        <th class="text-end">Finished</th>
        <th class="text-end">Canceled</th>
        <th class="text-end">Active</th>
        <th class="text-end">Monto</th>
        <th class="text-end">Avg Dist (m)</th>
        <th class="text-end">Avg Dur (s)</th>
        <th class="text-end">Acciones</th>
      </tr>
      </thead>
      <tbody>
      @forelse($drivers as $d)
        <tr>
          <td class="text-muted">#{{ $d->id }}</td>
          <td>{{ $d->name }}</td>
          <td><span class="badge {{ $badge($d->status) }}">{{ $d->status }}</span></td>
          <td class="text-muted">{{ $d->phone ?? '—' }}</td>
          <td class="text-end">{{ (int)$d->rides_total }}</td>
          <td class="text-end">{{ (int)$d->rides_finished }}</td>
          <td class="text-end">{{ (int)$d->rides_canceled }}</td>
          <td class="text-end">{{ (int)$d->rides_active }}</td>
          <td class="text-end">{{ number_format((float)$d->amount_sum, 2) }}</td>
          <td class="text-end">{{ $d->avg_distance_m !== null ? number_format((float)$d->avg_distance_m, 0) : '—' }}</td>
          <td class="text-end">{{ $d->avg_duration_s !== null ? number_format((float)$d->avg_duration_s, 0) : '—' }}</td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary"
               href="{{ route('partner.reports.drivers.show', $d->id) }}">
              Ver
            </a>
          </td>
        </tr>
      @empty
        <tr><td colspan="12" class="text-muted">Sin resultados.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer">
    {{ $drivers->links() }}
  </div>
</div>
@endsection
