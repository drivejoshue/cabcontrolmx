@extends('layouts.admin')
@section('title','Recibo '.$rc->receipt_number)

@section('content')
<div class="container-fluid p-0">
  <div class="d-flex align-items-start justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-1">Recibo {{ $rc->receipt_number }}</h1>
      <div class="text-muted">Recibo informativo (no fiscal)</div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary" onclick="window.print()">Imprimir</button>
      <a href="{{ route('admin.taxi_charges') }}" class="btn btn-outline-primary">Volver</a>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <h6 class="mb-1">{{ $rc->tenant_name }}</h6>
          <div class="text-muted small">
            @if($rc->tenant_city) {{ $rc->tenant_city }} @endif
            @if($rc->tenant_phone) · {{ $rc->tenant_phone }} @endif
          </div>
        </div>
        <div class="col-12 col-md-6 text-md-end">
          <div class="text-muted small">Emitido: {{ $rc->issued_at ? \Carbon\Carbon::parse($rc->issued_at)->format('d M Y H:i') : '-' }}</div>
          <div class="text-muted small">Estado del cobro: <strong>{{ strtoupper($rc->status) }}</strong></div>
        </div>

        <div class="col-12"><hr></div>

        <div class="col-12 col-md-6">
          <div class="text-muted small">Taxi</div>
          <div class="fw-semibold">
            {{ $rc->vehicle_economico ? 'Econ '.$rc->vehicle_economico : ($rc->vehicle_id ? 'Vehículo #'.$rc->vehicle_id : '-') }}
            @if($rc->vehicle_plate) · {{ $rc->vehicle_plate }} @endif
          </div>
          <div class="text-muted small">{{ trim(($rc->vehicle_brand ?? '').' '.($rc->vehicle_model ?? '')) }}</div>
        </div>

        <div class="col-12 col-md-6">
          <div class="text-muted small">Conductor</div>
          <div class="fw-semibold">{{ $rc->driver_name ?? '-' }}</div>
          <div class="text-muted small">{{ $rc->driver_phone ?? '' }}</div>
        </div>

        <div class="col-12 col-md-6">
          <div class="text-muted small">Periodo</div>
          <div class="fw-semibold">{{ strtoupper($rc->period_type) }} · {{ $rc->period_start }} → {{ $rc->period_end }}</div>
        </div>

        <div class="col-12 col-md-6 text-md-end">
          <div class="text-muted small">Monto</div>
          <div class="display-6 mb-0">${{ number_format((float)$rc->amount,2) }}</div>
        </div>

        @if(!empty($rc->paid_at))
          <div class="col-12">
            <div class="alert alert-success mb-0">
              Pagado el {{ \Carbon\Carbon::parse($rc->paid_at)->format('d M Y H:i') }}
            </div>
          </div>
        @endif

        @if(!empty($rc->notes))
          <div class="col-12">
            <div class="text-muted small">Notas</div>
            <div class="border rounded p-2">{{ $rc->notes }}</div>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
