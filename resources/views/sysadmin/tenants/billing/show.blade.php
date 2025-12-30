@extends('layouts.sysadmin')

@section('title','Billing · Tenant #'.$tenant->id.' · '.$tenant->name)

@push('styles')
<style>
  .stat-pill { border-radius: 999px; padding:.25rem .7rem; font-size:.75rem; }
  /* Montos y fechas alineados mejor */
  .tabular { font-variant-numeric: tabular-nums; }
</style>
@endpush

@section('content')
<div class="container-fluid">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">
        Facturación del tenant: {{ $tenant->name }}
        @php
          $bm = $profile->billing_model ?? 'per_vehicle';
          $st = strtolower($profile->status ?? 'trial');
          $badgeSt = match($st) {
            'active'   => 'success',
            'trial'    => 'warning',
            'paused'   => 'secondary',
            'canceled' => 'danger',
            default    => 'secondary',
          };
        @endphp

        <span class="badge bg-{{ $badgeSt }} stat-pill align-middle">
          {{ strtoupper($st) }}
        </span>

        @if($bm === 'commission')
          <span class="badge bg-info stat-pill align-middle">Modelo: comisión</span>
        @else
          <span class="badge bg-primary stat-pill align-middle">Modelo: por vehículo</span>
        @endif
      </h3>
      <div class="text-muted small">
        Tenant ID: {{ $tenant->id }}
        · Ciudad: {{ $tenant->city ?? '—' }}
        · Dominio: {{ $tenant->domain ?? '—' }}
      </div>
    </div>

   <div class="d-flex gap-2">
  <a href="{{ route('sysadmin.tenants.index') }}" class="btn btn-outline-secondary btn-sm">
    Volver a lista de tenants
  </a>

  <form method="POST" action="{{ route('sysadmin.tenants.billing.runMonthly', $tenant) }}"
        onsubmit="return confirm('¿Correr el ciclo mensual de facturación para este tenant?');">
    @csrf
    <button class="btn btn-primary btn-sm">
      Correr ciclo mensual
    </button>
  </form>
</div>

  </div>

  {{-- Flash messages --}}
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

    {{-- Resumen / estado actual --}}
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <strong>Resumen de facturación</strong>
        </div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-5">Plan</dt>
            <dd class="col-7">{{ $profile->plan_code ?? '—' }}</dd>

            <dt class="col-5">Modelo</dt>
            <dd class="col-7">
              @if(($profile->billing_model ?? 'per_vehicle') === 'commission')
                Comisión por viaje
              @else
                Cobro por vehículo
              @endif
            </dd>

            <dt class="col-5">Estado</dt>
            <dd class="col-7">{{ $profile->status ?? 'trial' }}</dd>

            <dt class="col-5">Día de corte</dt>
            <dd class="col-7">{{ $profile->invoice_day ?? 1 }}</dd>

            <dt class="col-5">Trial hasta</dt>
            <dd class="col-7">{{ optional($profile->trial_ends_at)->toDateString() ?? '—' }}</dd>

            <dt class="col-5">Vehículos en trial</dt>
            <dd class="col-7">{{ $profile->trial_vehicles ?? 0 }}</dd>

            <dt class="col-5">Última factura</dt>
            <dd class="col-7">
              @if($lastInvoice)
                <div>{{ $lastInvoice->issue_date?->toDateString() }} · {{ number_format($lastInvoice->total,2) }} {{ $lastInvoice->currency }}</div>
                <a href="{{ route('sysadmin.invoices.show', $lastInvoice) }}" class="small">Ver factura</a>
              @else
                <span class="text-muted">Sin facturas todavía</span>
              @endif
            </dd>

            <dt class="col-5">Próx. corte</dt>
            <dd class="col-7">{{ optional($profile->next_invoice_date)->toDateString() ?? '—' }}</dd>

            <dt class="col-5">Notas</dt>
            <dd class="col-7">{{ $profile->notes ?: '—' }}</dd>
          </dl>
        </div>
      </div>
    </div>

    {{-- Vehículos / límites --}}
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <strong>Vehículos y límites</strong>
        </div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-6">Vehículos activos</dt>
            <dd class="col-6">{{ $activeVehicles }}</dd>

            <dt class="col-6">Vehículos totales</dt>
            <dd class="col-6">{{ $totalVehicles }}</dd>

            @if(($profile->billing_model ?? 'per_vehicle') === 'per_vehicle')
              <dt class="col-6">Incluidos en plan</dt>
              <dd class="col-6">{{ $profile->included_vehicles ?? 0 }}</dd>

              <dt class="col-6">Máx. vehículos</dt>
              <dd class="col-6">{{ $profile->max_vehicles ?? 'Ilimitado' }}</dd>

              <dt class="col-6">Base mensual</dt>
              <dd class="col-6">${{ number_format($profile->base_monthly_fee ?? 0,2) }} MXN</dd>

              <dt class="col-6">Precio por vehículo extra</dt>
              <dd class="col-6">${{ number_format($profile->price_per_vehicle ?? 0,2) }} MXN</dd>
            @else
              <dt class="col-6">Comisión %</dt>
              <dd class="col-6">
                {{ $profile->commission_percent !== null ? $profile->commission_percent.' %' : '—' }}
              </dd>

              <dt class="col-6">Mínimo mensual</dt>
              <dd class="col-6">${{ number_format($profile->commission_min_fee ?? 0,2) }} MXN</dd>
            @endif
          </dl>

          <hr>

          @if($canRegisterNewVehicle)
            <div class="alert alert-success mb-0 small">
              Este tenant <strong>puede registrar</strong> nuevos vehículos bajo el esquema actual.
            </div>
          @else
            <div class="alert alert-warning mb-0 small">
              <strong>No puede registrar</strong> nuevos vehículos en este momento.<br>
              <span class="d-block mt-1">{{ $canRegisterReason }}</span>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Formulario de edición del perfil --}}
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <strong>Editar perfil de facturación</strong>
        </div>
        <form method="POST" action="{{ route('sysadmin.tenants.billing.update', $tenant) }}">
          @csrf
          <div class="card-body small">
            <div class="mb-2">
              <label class="form-label">Código de plan</label>
              <input type="text" name="plan_code" class="form-control form-control-sm"
                     value="{{ old('plan_code', $profile->plan_code ?? 'basic-per-vehicle') }}">
            </div>

            <div class="mb-2">
              <label class="form-label">Modelo de cobro</label>
              @php $bmVal = old('billing_model', $profile->billing_model ?? 'per_vehicle'); @endphp
              <select name="billing_model" class="form-select form-select-sm">
                <option value="per_vehicle" @selected($bmVal==='per_vehicle')>Por vehículo</option>
                <option value="commission" @selected($bmVal==='commission')>Comisión por viaje</option>
              </select>
            </div>

            <div class="mb-2">
              <label class="form-label">Estado del perfil</label>
              @php $stVal = old('status', $profile->status ?? 'trial'); @endphp
              <select name="status" class="form-select form-select-sm">
                @foreach(['trial','active','paused','canceled'] as $opt)
                  <option value="{{ $opt }}" @selected($stVal === $opt)>{{ ucfirst($opt) }}</option>
                @endforeach
              </select>
            </div>

            <div class="row g-2 mb-2">
              <div class="col-6">
                <label class="form-label">Trial hasta</label>
                <input type="date" name="trial_ends_at" class="form-control form-control-sm"
                       value="{{ old('trial_ends_at', optional($profile->trial_ends_at)->toDateString()) }}">
              </div>
              <div class="col-6">
                <label class="form-label">Vehículos en trial</label>
                <input type="number" name="trial_vehicles" min="0" class="form-control form-control-sm"
                       value="{{ old('trial_vehicles', $profile->trial_vehicles ?? 5) }}">
              </div>
            </div>

            <div class="mb-2">
              <label class="form-label">Día de corte (1–28)</label>
              <input type="number" name="invoice_day" min="1" max="28"
                     class="form-control form-control-sm"
                     value="{{ old('invoice_day', $profile->invoice_day ?? 1) }}">
            </div>

            {{-- Per Vehicle --}}
            <div class="border rounded p-2 mb-2">
              <div class="form-text mb-1">Si el modelo es <strong>per_vehicle</strong>:</div>
              <div class="row g-2 mb-1">
                <div class="col-6">
                  <label class="form-label">Base mensual (MXN)</label>
                  <input type="number" step="0.01" min="0"
                         name="base_monthly_fee"
                         class="form-control form-control-sm"
                         value="{{ old('base_monthly_fee', $profile->base_monthly_fee ?? 0) }}">
                </div>
                <div class="col-6">
                  <label class="form-label">Incluye vehículos</label>
                  <input type="number" min="0"
                         name="included_vehicles"
                         class="form-control form-control-sm"
                         value="{{ old('included_vehicles', $profile->included_vehicles ?? 0) }}">
                </div>
              </div>
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label">Precio x vehículo extra</label>
                  <input type="number" step="0.01" min="0"
                         name="price_per_vehicle"
                         class="form-control form-control-sm"
                         value="{{ old('price_per_vehicle', $profile->price_per_vehicle ?? 0) }}">
                </div>
                <div class="col-6">
                  <label class="form-label">Máx. vehículos (vacío = ilimitado)</label>
                  <input type="number" min="0"
                         name="max_vehicles"
                         class="form-control form-control-sm"
                         value="{{ old('max_vehicles', $profile->max_vehicles) }}">
                </div>
              </div>
            </div>

            {{-- Comisión --}}
            <div class="border rounded p-2 mb-2">
              <div class="form-text mb-1">Si el modelo es <strong>commission</strong>:</div>
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label">% comisión</label>
                  <input type="number" step="0.1" min="0" max="100"
                         name="commission_percent"
                         class="form-control form-control-sm"
                         value="{{ old('commission_percent', $profile->commission_percent) }}">
                </div>
                <div class="col-6">
                  <label class="form-label">Mínimo mensual (MXN)</label>
                  <input type="number" step="0.01" min="0"
                         name="commission_min_fee"
                         class="form-control form-control-sm"
                         value="{{ old('commission_min_fee', $profile->commission_min_fee ?? 0) }}">
                </div>
              </div>
            </div>

            <div class="mb-2">
              <label class="form-label">Notas internas</label>
              <textarea name="notes" rows="2" class="form-control form-control-sm"
                        placeholder="Comentarios internos sobre este tenant / plan">{{ old('notes', $profile->notes) }}</textarea>
            </div>
          </div>
          <div class="card-footer d-flex justify-content-end">
            <button class="btn btn-primary btn-sm" type="submit">Guardar cambios</button>
          </div>
        </form>
      </div>
    </div>

  </div> {{-- /row --}}

</div>
@endsection
