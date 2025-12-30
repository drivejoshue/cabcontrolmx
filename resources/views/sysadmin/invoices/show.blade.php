@extends('layouts.sysadmin')

@section('title', 'Factura #'.$invoice->id.' – '.$invoice->tenant->name)

@php
    $status = strtolower($invoice->status ?? 'pending');
    $badgeClass = match ($status) {
        'paid'      => 'success',
        'canceled'  => 'secondary',
        'overdue'   => 'danger',
        default     => 'warning',
    };
@endphp

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">
                Factura #{{ $invoice->id }}
                <span class="badge bg-{{ $badgeClass }} align-middle">{{ strtoupper($status) }}</span>
            </h3>
            <div class="text-muted small">
                Tenant: #{{ $invoice->tenant_id }} · {{ $invoice->tenant->name ?? '—' }}
            </div>
            <div class="text-muted small">
                Periodo: {{ $invoice->period_start?->toDateString() }} → {{ $invoice->period_end?->toDateString() }}
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('sysadmin.invoices.index') }}" class="btn btn-outline-secondary btn-sm">Volver a facturas</a>
            <a href="{{ route('sysadmin.tenants.billing.show', $invoice->tenant_id) }}" class="btn btn-outline-primary btn-sm">Ver billing del tenant</a>
            <a href="{{ route('sysadmin.invoices.pdf', $invoice) }}" class="btn btn-primary btn-sm">Descargar PDF</a>

            @if($invoice->status === 'pending')
                <form method="POST" action="{{ route('sysadmin.invoices.mark-paid', $invoice) }}"
                      onsubmit="return confirm('¿Marcar esta factura como pagada?');">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">Marcar como pagada</button>
                </form>

                <form method="POST" action="{{ route('sysadmin.invoices.mark-canceled', $invoice) }}"
                      onsubmit="return confirm('¿Marcar esta factura como cancelada?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">Cancelar factura</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('ok'))
        <div class="alert alert-success">{{ session('ok') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header"><strong>Datos de la factura</strong></div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-5">Tenant</dt>
                        <dd class="col-7">#{{ $invoice->tenant_id }} – {{ $invoice->tenant->name ?? '—' }}</dd>

                        <dt class="col-5">Periodo</dt>
                        <dd class="col-7">
                            {{ $invoice->period_start?->toDateString() }} → {{ $invoice->period_end?->toDateString() }}
                        </dd>

                        <dt class="col-5">Emitida</dt>
                        <dd class="col-7">{{ $invoice->issue_date?->toDateString() ?? '—' }}</dd>

                        <dt class="col-5">Vence</dt>
                        <dd class="col-7">{{ $invoice->due_date?->toDateString() ?? '—' }}</dd>

                        <dt class="col-5">Estatus</dt>
                        <dd class="col-7"><span class="badge bg-{{ $badgeClass }}">{{ $status }}</span></dd>

                        <dt class="col-5">Moneda</dt>
                        <dd class="col-7">{{ $invoice->currency ?? 'MXN' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header"><strong>Importes</strong></div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-6">Vehículos facturados</dt>
                        <dd class="col-6">{{ $invoice->vehicles_count ?? 0 }}</dd>

                        <dt class="col-6">Base mensual</dt>
                        <dd class="col-6">
                            ${{ number_format($invoice->base_fee ?? 0, 2) }} {{ $invoice->currency ?? 'MXN' }}
                        </dd>

                        <dt class="col-6">Vehículos extra</dt>
                        <dd class="col-6">
                            ${{ number_format($invoice->vehicles_fee ?? 0, 2) }} {{ $invoice->currency ?? 'MXN' }}
                        </dd>

                        <dt class="col-6">Total</dt>
                        <dd class="col-6 fw-semibold">
                            ${{ number_format($invoice->total ?? 0, 2) }} {{ $invoice->currency ?? 'MXN' }}
                        </dd>
                    </dl>

                    <hr>

                    <div class="text-muted">
                        <div class="small">Perfil de billing:</div>
                        @if($invoice->billingProfile)
                            <div class="small">
                                Plan: <code>{{ $invoice->billingProfile->plan_code ?? '—' }}</code><br>
                                Modelo: {{ $invoice->billingProfile->billing_model === 'commission' ? 'Comisión por viaje' : 'Por vehículo' }}
                            </div>
                        @else
                            <div class="small text-muted">(Sin perfil asociado)</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header"><strong>Notas internas</strong></div>
                <div class="card-body">
                    <p class="small mb-0">{{ $invoice->notes ?: 'Sin notas registradas.' }}</p>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
