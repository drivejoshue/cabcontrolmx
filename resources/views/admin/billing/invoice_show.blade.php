@extends('layouts.admin')

@section('title', 'Factura #'.$invoice->id.' – '.$tenant->name)

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">
        Factura #{{ $invoice->id }}
        @php
          $st = strtolower($invoice->status);
          $badge = $st === 'paid'
              ? 'success'
              : ($st === 'canceled' ? 'secondary' : 'warning');
        @endphp
        <span class="badge bg-{{ $badge }} align-middle">
          {{ strtoupper($invoice->status) }}
        </span>
      </h3>
      <div class="text-muted small">
        Central: {{ $tenant->name }} · Tenant ID: {{ $tenant->id }}<br>
        Periodo: {{ $invoice->period_start }} → {{ $invoice->period_end }}
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('admin.billing.plan') }}" class="btn btn-outline-secondary">
        Volver a plan y facturación
      </a>
      <a href="{{ route('admin.billing.invoices.csv', $invoice) }}" class="btn btn-outline-primary">
        Descargar CSV
      </a>
      {{-- En una siguiente iteración: botón Descargar PDF --}}
    </div>
  </div>

  <div class="row g-3">

    {{-- Datos de la factura --}}
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <strong>Datos de la factura</strong>
        </div>
        <div class="card-body small">
          <dl class="row mb-0">
            <dt class="col-5">Tenant</dt>
            <dd class="col-7">
              #{{ $tenant->id }} – {{ $tenant->name }}
            </dd>

            <dt class="col-5">Periodo</dt>
            <dd class="col-7">
              {{ $invoice->period_start }} → {{ $invoice->period_end }}
            </dd>

            <dt class="col-5">Emitida el</dt>
            <dd class="col-7">{{ $invoice->issue_date }}</dd>

            <dt class="col-5">Vence el</dt>
            <dd class="col-7">{{ $invoice->due_date }}</dd>

            <dt class="col-5">Status</dt>
            <dd class="col-7">{{ $invoice->status }}</dd>

            <dt class="col-5">Moneda</dt>
            <dd class="col-7">{{ $invoice->currency }}</dd>
          </dl>
        </div>
      </div>
    </div>

    {{-- Importes --}}
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <strong>Importes</strong>
        </div>
        <div class="card-body small">
          <dl class="row mb-0">
            <dt class="col-6">Vehículos facturados</dt>
            <dd class="col-6">{{ $invoice->vehicles_count }}</dd>

            <dt class="col-6">Base mensual</dt>
            <dd class="col-6">
              ${{ number_format($invoice->base_fee, 2) }} {{ $invoice->currency }}
            </dd>

            <dt class="col-6">Cargo por vehículos extra</dt>
            <dd class="col-6">
              ${{ number_format($invoice->vehicles_fee, 2) }} {{ $invoice->currency }}
            </dd>

            <dt class="col-6">Total</dt>
            <dd class="col-6 fw-bold">
              ${{ number_format($invoice->total, 2) }} {{ $invoice->currency }}
            </dd>
          </dl>

          <hr>

          <div class="small text-muted">
            Perfil de billing: {{ $profile->plan_code ?? 'N/D' }}<br>
            Modelo:
            @if(($profile->billing_model ?? 'per_vehicle') === 'per_vehicle')
              Cobro por vehículo
            @else
              Comisión por viaje
            @endif
          </div>
        </div>
      </div>
    </div>

    {{-- Notas --}}
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <strong>Notas</strong>
        </div>
        <div class="card-body small">
          @if($invoice->notes)
            <p class="mb-0">{{ $invoice->notes }}</p>
          @else
            <p class="text-muted mb-0">
              No hay notas registradas para esta factura.
            </p>
          @endif
        </div>
      </div>
    </div>

  </div> {{-- /row --}}
</div>
@endsection
