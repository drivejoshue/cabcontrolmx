@extends('layouts.partner')

@section('title', 'Reporte · Conductor')
@section('page-id','partner-reports-driver-show')

@php
  use Carbon\Carbon;

  // Defaults: últimos 3 meses
  $defaultTo   = Carbon::today()->toDateString();
  $defaultFrom = Carbon::today()->subMonths(3)->toDateString();

  // Reporte: solo estados relevantes
  $statusOptions = [
    '' => 'Finalizados + Cancelados',
    'finished' => 'Finalizados',
    'canceled' => 'Cancelados',
  ];

  // Para conductor, lo más lógico es basarse en el evento final
  // (si filtras "Finalizados", usas finished_at; si "Cancelados", canceled_at)
  // Dejamos el select simple: "Fecha final"
  $dateFieldOptions = [
    'finished_at' => 'Fecha final (fin/cancelación)',
  ];

  // Valores efectivos (si vienen vacíos, usamos defaults)
  $fromVal = request('from') ?: $defaultFrom;
  $toVal   = request('to')   ?: $defaultTo;

  $qs = request()->query();
@endphp


@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h1 class="h3 mb-0">Conductor {{ $driver->name }}</h1>
    <div class="text-muted small">{{ $driver->phone ?? '—' }} {{ $driver->email ? '· '.$driver->email : '' }}</div>
  </div>

  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="{{ route('partner.reports.drivers.index') }}">
      <i class="ti ti-arrow-left me-1"></i> Volver
    </a>
    <a class="btn btn-outline-secondary" href="{{ route('partner.reports.drivers.exportCsv', $qs) }}">
      <i class="ti ti-download me-1"></i> Export CSV (lista)
    </a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">

      <div class="col-md-2">
  <label class="form-label">Desde</label>
  <input type="date" class="form-control" name="from" value="{{ $fromVal }}">
</div>

<div class="col-md-2">
  <label class="form-label">Hasta</label>
  <input type="date" class="form-control" name="to" value="{{ $toVal }}">
</div>

<div class="col-md-2">
  <label class="form-label">Tipo</label>
  <select class="form-select" name="status">
    @foreach($statusOptions as $k => $lbl)
      <option value="{{ $k }}" @selected(request('status','')===$k)>{{ $lbl }}</option>
    @endforeach
  </select>
</div>

<div class="col-md-2">
  <label class="form-label">Campo fecha</label>
  <select class="form-select" name="date_field" disabled>
    <option value="finished_at" selected>Fecha final (fin/cancelación)</option>
  </select>
  <input type="hidden" name="date_field" value="finished_at">
</div>

<div class="col-md-2 d-flex gap-2">
  <button class="btn btn-primary w-100" type="submit">
    <i class="ti ti-filter me-1"></i> Filtrar
  </button>
  <a class="btn btn-outline-secondary w-100" href="{{ route('partner.reports.drivers.show', $driver->id) }}">
    Limpiar
  </a>
</div>


    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h5 class="card-title mb-0">Viajes del conductor</h5>
  </div>

  <div class="table-responsive">
    <table class="table table-vcenter mb-0">
      <thead>
      <tr>
        <th>ID</th>
        <th>Status</th>
        <th>Canal</th>
        <th>Fecha</th>
        <th>Monto</th>
        <th>Origen</th>
        <th>Destino</th>
        <th class="text-end">Acciones</th>
      </tr>
      </thead>
      <tbody>
     @forelse($rides as $r)
  @php
    $amount = (float)($r->agreed_amount ?? $r->total_amount ?? $r->quoted_amount ?? 0);

    // Fecha "final" para reporte (sin depender de date_field)
    $when = $r->finished_at ?? $r->canceled_at ?? $r->requested_at ?? $r->created_at;

    // Badge bonito en español
    $statusLabel = $r->status === 'finished' ? 'Finalizado' : ($r->status === 'canceled' ? 'Cancelado' : $r->status);

    $badgeClass = match($r->status) {
      'finished' => 'bg-success-lt text-success',
      'canceled' => 'bg-danger-lt text-danger',
      default => 'bg-secondary-lt text-secondary',
    };
  @endphp

  <tr>
    <td class="text-muted">#{{ $r->id }}</td>
    <td><span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span></td>
    <td class="text-muted">{{ $r->requested_channel }}</td>
    <td class="text-muted small">{{ $when }}</td>
    <td>{{ number_format($amount, 2) }} <span class="text-muted">{{ $r->currency ?? 'MXN' }}</span></td>
    <td class="text-truncate" style="max-width:240px;">{{ $r->origin_label ?? '—' }}</td>
    <td class="text-truncate" style="max-width:240px;">{{ $r->dest_label ?? '—' }}</td>
    <td class="text-end">
      {{-- Si todavía no hay "rides.show" para partner, evita romper --}}
      <a class="btn btn-sm btn-outline-primary"
         href="{{ route('partner.reports.rides.show', $r->id) }}">
        Ver
      </a>
    </td>
  </tr>
@empty
  <tr><td colspan="8" class="text-muted">Sin viajes.</td></tr>
@endforelse

      </tbody>
    </table>
  </div>

  <div class="card-footer">
    {{ $rides->links() }}
  </div>
</div>
@endsection
