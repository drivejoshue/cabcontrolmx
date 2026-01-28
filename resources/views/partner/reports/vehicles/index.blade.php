@extends('layouts.partner')

@section('title', 'Reportes · Vehículos')
@section('page-id','partner-reports-vehicles')

@php
  $dateFieldOptions = [
    'requested_at' => 'requested_at',
    'created_at' => 'created_at',
    'accepted_at' => 'accepted_at',
    'finished_at' => 'finished_at',
    'canceled_at' => 'canceled_at',
  ];

  $qs = request()->query();
@endphp

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h1 class="h3 mb-0">Reportes · Vehículos</h1>
    <div class="text-muted small">Productividad por vehículo</div>
  </div>

  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary"
       href="{{ route('partner.reports.vehicles.exportPdf', $qs) }}">
      <i class="ti ti-download me-1"></i> Export Pdf
    </a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">

      <div class="col-md-2">
        <label class="form-label">Desde</label>
        <input type="date" class="form-control" name="from" value="{{ request('from') }}">
      </div>

      <div class="col-md-2">
        <label class="form-label">Hasta</label>
        <input type="date" class="form-control" name="to" value="{{ request('to') }}">
      </div>


      <div class="col-md-2">
        <label class="form-label">Activo</label>
        <select class="form-select" name="active">
          <option value="" @selected(request('active','')==='')>Todos</option>
          <option value="1" @selected((string)request('active')==='1')>Sí</option>
          <option value="0" @selected((string)request('active')==='0')>No</option>
        </select>
      </div>

     

      <div class="col-md-4">
        <label class="form-label">Buscar</label>
        <input class="form-control" name="q" value="{{ request('q') }}"
               placeholder="Económico/placa/marca/modelo/color">
      </div>

      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit">
          <i class="ti ti-filter me-1"></i> Filtrar
        </button>
        <a class="btn btn-outline-secondary w-100" href="{{ route('partner.reports.vehicles.index') }}">
          Limpiar
        </a>
      </div>

    </form>
  </div>
</div>



<div class="card">
  <div class="card-header">
    <h5 class="card-title mb-0">Vehículos</h5>
  </div>

  <div class="table-responsive">
    <table class="table table-vcenter mb-0">
      <thead>
      <tr>
        <th>ID</th>
        <th>Económico</th>
        <th>Placa</th>
        <th>Activo</th>
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
      @forelse($vehicles as $v)
        <tr>
          <td class="text-muted">#{{ $v->id }}</td>
          <td>{{ $v->economico ?? '—' }}</td>
          <td class="text-muted">{{ $v->plate ?? '—' }}</td>
          <td>
            @if((int)$v->active === 1)
              <span class="badge bg-success-lt text-success">Sí</span>
            @else
              <span class="badge bg-secondary-lt text-secondary">No</span>
            @endif
          </td>
          <td class="text-end">{{ (int)$v->rides_total }}</td>
          <td class="text-end">{{ (int)$v->rides_finished }}</td>
          <td class="text-end">{{ (int)$v->rides_canceled }}</td>
          <td class="text-end">{{ (int)$v->rides_active }}</td>
          <td class="text-end">{{ number_format((float)$v->amount_sum, 2) }}</td>
          <td class="text-end">{{ $v->avg_distance_m !== null ? number_format((float)$v->avg_distance_m, 0) : '—' }}</td>
          <td class="text-end">{{ $v->avg_duration_s !== null ? number_format((float)$v->avg_duration_s, 0) : '—' }}</td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary"
               href="{{ route('partner.reports.vehicles.show', $v->id) }}">
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
    {{ $vehicles->links() }}
  </div>
</div>
@endsection
