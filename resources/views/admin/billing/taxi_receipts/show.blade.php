@extends('layouts.admin')
@section('title','Recibo '.$rc->receipt_number)

@push('styles')
<style>
@media print {
  .no-print { display:none !important; }
  body { background:#fff !important; }
  .card { border:0 !important; box-shadow:none !important; }
}
.receipt-meta { font-size: 12px; color: #6c757d; }
.receipt-title { font-size: 26px; font-weight: 700; letter-spacing: .5px; }
.receipt-total { font-size: 34px; font-weight: 800; }
.hr-soft { opacity:.15; }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">

  <div class="d-flex align-items-start justify-content-between mb-3 no-print">
    <div>
      <h1 class="h3 mb-1">Recibo {{ $rc->receipt_number }}</h1>
      <div class="text-muted">Comprobante interno (no fiscal)</div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary" onclick="window.print()">Imprimir</button>
      <a href="{{ route('admin.taxi_charges') }}" class="btn btn-outline-primary">Volver</a>
    </div>
  </div>

  <div class="card">
    <div class="card-body">

      {{-- Header Central --}}
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="receipt-title">{{ $rc->tenant_name }}</div>
          <div class="receipt-meta">
            @if($rc->tenant_city) {{ $rc->tenant_city }} @endif
            @if($rc->tenant_phone) · Tel. {{ $rc->tenant_phone }} @endif
          </div>
          <div class="receipt-meta mt-1">Recibo informativo (no fiscal)</div>
        </div>

        <div class="text-end">
          <div class="receipt-meta">Folio</div>
          <div class="h3 mb-1">{{ $rc->receipt_number }}</div>
          <div class="receipt-meta">
            Emitido:
            {{ $rc->issued_at ? \Carbon\Carbon::parse($rc->issued_at)->format('d M Y H:i') : '—' }}
          </div>
        </div>
      </div>

      <hr class="hr-soft my-4">

      {{-- Datos del cobro --}}
      <div class="row g-3">
        <div class="col-12 col-md-7">
          <div class="receipt-meta">Detalle</div>

          <div class="mt-2">
            <div class="text-muted small">Taxi</div>
            <div class="fw-semibold">
              {{ $rc->vehicle_economico ? 'Econ '.$rc->vehicle_economico : ($rc->vehicle_id ? 'Vehículo #'.$rc->vehicle_id : '-') }}
              @if($rc->vehicle_plate) · {{ $rc->vehicle_plate }} @endif
            </div>
            <div class="text-muted small">{{ trim(($rc->vehicle_brand ?? '').' '.($rc->vehicle_model ?? '')) }}</div>
          </div>

          <div class="mt-3">
            <div class="text-muted small">Conductor</div>
            <div class="fw-semibold">{{ $rc->driver_name ?? '—' }}</div>
            @if(!empty($rc->driver_phone))
              <div class="text-muted small">Tel. {{ $rc->driver_phone }}</div>
            @endif
          </div>

          <div class="mt-3">
            <div class="text-muted small">Periodo</div>
            <div class="fw-semibold">
              {{ strtoupper($rc->period_type) }} · {{ $rc->period_start }} → {{ $rc->period_end }}
            </div>
          </div>

          <div class="mt-3">
            <div class="text-muted small">Estado</div>
            @php
              $badge = match($rc->status) {
                'paid'     => 'bg-success-lt text-success',
                'pending'  => 'bg-warning-lt text-warning',
                'canceled' => 'bg-secondary-lt text-secondary',
                default    => 'bg-secondary-lt text-secondary',
              };
              $label = match($rc->status) {
                'paid' => 'Pagado',
                'pending' => 'Pendiente',
                'canceled' => 'Cancelado',
                default => strtoupper($rc->status),
              };
            @endphp
            <span class="badge {{ $badge }}">{{ $label }}</span>
            @if(!empty($rc->paid_at))
              <span class="text-muted small ms-2">
                Pagado: {{ \Carbon\Carbon::parse($rc->paid_at)->format('d M Y H:i') }}
              </span>
            @endif
          </div>
        </div>

        <div class="col-12 col-md-5 text-md-end">
          <div class="receipt-meta">Total</div>
          <div class="receipt-total">${{ number_format((float)$rc->amount,2) }}</div>
          <div class="receipt-meta mt-2">Pago manual registrado en panel</div>
        </div>

        @if(!empty($rc->notes))
          <div class="col-12">
            <hr class="hr-soft">
            <div class="text-muted small">Notas</div>
            <div class="border rounded p-2">{{ $rc->notes }}</div>
          </div>
        @endif

        <div class="col-12">
          <hr class="hr-soft">
          <div class="row">
            <div class="col-12 col-md-6">
              <div class="text-muted small">Firma (recibí)</div>
              <div style="border-bottom:1px solid #ddd; height:28px;"></div>
              <div class="text-muted small mt-1">Nombre y firma del conductor</div>
            </div>
            <div class="col-12 col-md-6 mt-3 mt-md-0">
              <div class="text-muted small">Firma (entregué)</div>
              <div style="border-bottom:1px solid #ddd; height:28px;"></div>
              <div class="text-muted small mt-1">Central / Administrador</div>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>
@endsection
