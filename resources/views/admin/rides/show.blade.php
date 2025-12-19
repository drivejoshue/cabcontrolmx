@extends('layouts.admin')
@section('title','Detalle de corrida')

@section('content')
@php
  $st = $ride->status ?? 'unknown';
  $badge = match($st) {
    'requested' => 'bg-secondary',
    'accepted'  => 'bg-info',
    'en_route'  => 'bg-warning text-dark',
    'arrived'   => 'bg-warning text-dark',
    'on_board'  => 'bg-primary',
    'scheduled' => 'bg-dark',
    'finished'  => 'bg-success',
    'canceled'  => 'bg-danger',
    default     => 'bg-light text-dark',
  };
  $amt = $ride->total_amount ?? $ride->quoted_amount ?? 0;
@endphp

<div class="row g-3">

  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
        <div>
          <h3 class="card-title mb-1">Corrida #{{ $ride->id }}</h3>
          <div class="text-muted">
            <span class="badge {{ $badge }}">{{ strtoupper($st) }}</span>
            <span class="ms-2">Creado: {{ \Carbon\Carbon::parse($ride->created_at)->format('d M Y H:i') }}</span>
          </div>
        </div>
        <div class="d-flex gap-2">
          <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.rides.index') }}">
            <i class="bi bi-arrow-left me-1"></i> Volver
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- RESUMEN --}}
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-0 fw-semibold">Resumen</div>
      <div class="card-body">
        <div class="mb-2"><span class="text-muted">Pasajero:</span> <strong>{{ $ride->passenger_name ?? 'N/A' }}</strong></div>
        <div class="mb-2"><span class="text-muted">Teléfono:</span> {{ $ride->passenger_phone ?? '—' }}</div>
        <div class="mb-2"><span class="text-muted">Conductor:</span> {{ $ride->driver_name ?? '—' }}</div>
        <div class="mb-2"><span class="text-muted">Vehículo:</span>
          @php
            $veh = trim(($ride->vehicle_brand ?? '').' '.($ride->vehicle_model ?? ''));
          @endphp
          {{ $veh !== '' ? $veh : '—' }}
          @if(!empty($ride->vehicle_plate)) <span class="text-muted">({{ $ride->vehicle_plate }})</span> @endif
        </div>

        <hr>

        <div class="mb-2"><span class="text-muted">Monto:</span> <strong>${{ number_format((float)$amt, 2) }}</strong></div>
        <div class="mb-2"><span class="text-muted">Pago:</span> {{ $ride->payment_method ?? '—' }}</div>
        <div class="mb-2"><span class="text-muted">Canal:</span> {{ $ride->requested_channel ?? '—' }}</div>
        <div class="mb-2"><span class="text-muted">Tarifa:</span> {{ $ride->fare_mode ?? '—' }}</div>
      </div>
    </div>
  </div>

  {{-- RUTA --}}
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-0 fw-semibold">Ruta</div>
      <div class="card-body">
        <div class="mb-2">
          <div class="text-muted small mb-1">Origen</div>
          <div class="fw-semibold">{{ $ride->origin_label ?? '—' }}</div>
        </div>
        <div class="mb-2">
          <div class="text-muted small mb-1">Destino</div>
          <div class="fw-semibold">{{ $ride->dest_label ?? '—' }}</div>
        </div>

        @if(!empty($ride->stops) && is_array($ride->stops) && count($ride->stops) > 0)
          <hr>
          <div class="text-muted small mb-2">Paradas</div>
          <ol class="mb-0">
            @foreach($ride->stops as $s)
              <li>{{ $s['label'] ?? ($s['address'] ?? 'Parada') }}</li>
            @endforeach
          </ol>
        @endif

        <hr>

        <div class="row g-2">
          <div class="col-md-4">
            <div class="text-muted small">Distancia</div>
            <div class="fw-semibold">
              @if(isset($ride->distance_m))
                {{ number_format($ride->distance_m / 1000, 2) }} km
              @else — @endif
            </div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Duración</div>
            <div class="fw-semibold">
              @if(isset($ride->duration_s))
                {{ gmdate('H:i:s', (int)$ride->duration_s) }}
              @else — @endif
            </div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Programado</div>
            <div class="fw-semibold">
              @if(!empty($ride->scheduled_for))
                {{ \Carbon\Carbon::parse($ride->scheduled_for)->format('d M Y H:i') }}
              @else — @endif
            </div>
          </div>
        </div>

        @if(!empty($ride->notes))
          <hr>
          <div class="text-muted small mb-1">Notas</div>
          <div>{{ $ride->notes }}</div>
        @endif

        @if($st === 'canceled')
          <hr>
          <div class="text-muted small mb-1">Cancelación</div>
          <div><span class="text-muted">Motivo:</span> {{ $ride->cancel_reason ?? '—' }}</div>
          <div><span class="text-muted">Por:</span> {{ $ride->canceled_by ?? '—' }}</div>
        @endif

      </div>
    </div>
  </div>

</div>
@endsection
