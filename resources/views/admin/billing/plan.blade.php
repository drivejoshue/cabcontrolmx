{{-- resources/views/admin/billing/plan.blade.php --}}
@extends('layouts.admin')

@section('title', 'Mi plan y facturación')

@push('styles')
<style>
  .stat-pill { border-radius: 999px; padding: .25rem .7rem; font-size: .75rem; }
  .metric { font-size: 1.35rem; font-weight: 700; letter-spacing: -.02em; }
  .metric-sub { font-size: .85rem; color: rgba(0,0,0,.55); }
  [data-theme="dark"] .metric-sub { color: rgba(255,255,255,.65); }
  .soft-card { border: 1px solid rgba(0,0,0,.06); }
  [data-theme="dark"] .soft-card { border-color: rgba(255,255,255,.10); }
</style>
@endpush

@section('content')
<div class="container-fluid">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Plan y facturación</h3>
      <div class="text-muted small">
        Central: {{ $tenant->name }} · Tenant ID: {{ $tenant->id }}
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('admin.wallet.index') }}" class="btn btn-outline-secondary">
        Wallet
      </a>
      <a href="{{ route('admin.wallet.topup.create') }}" class="btn btn-primary">
        Recargar saldo
      </a>
    </div>
  </div>

  @php
    $st = strtolower($profile->status ?? 'trial');
    $bm = $profile->billing_model ?? 'per_vehicle';

    $badgeSt = match($st) {
      'active'   => 'success',
      'trial'    => 'warning',
      'paused'   => 'secondary',
      'canceled' => 'danger',
      default    => 'secondary',
    };

    $isPerVehicle = ($bm === 'per_vehicle');
    $trialEndsTxt = $trialEndsAt ? $trialEndsAt->toDateString() : '—';
  @endphp

  <div class="row g-3">

    {{-- WALLET --}}
    <div class="col-12 col-lg-4">
      <div class="card h-100 soft-card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <strong>Wallet de la central</strong>
          <span class="badge bg-info stat-pill">Prepaid</span>
        </div>

        <div class="card-body">
          <div class="d-flex align-items-start justify-content-between mb-2">
            <div>
              <div class="metric">${{ number_format($walletBalance ?? 0, 2) }} <span class="fs-6">MXN</span></div>
              <div class="metric-sub">
                Última recarga:
                {{ !empty($wallet->last_topup_at) ? \Carbon\Carbon::parse($wallet->last_topup_at)->toDateTimeString() : '—' }}
              </div>
            </div>

            <div class="text-end">
              <div class="small text-muted">Por pagar (draft/pending)</div>
              <div class="fw-semibold">${{ number_format($openAmountDue ?? 0, 2) }} MXN</div>
            </div>
          </div>

          <hr>

          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="small text-muted">Mínimo sugerido para ponerte al día</div>
              <div class="fw-bold">${{ number_format($minTopupSuggested ?? 0, 2) }} MXN</div>
            </div>
            <div class="d-flex gap-2">
              <a href="{{ route('admin.wallet.movements') }}" class="btn btn-outline-secondary btn-sm">
                Movimientos
              </a>
              <a href="{{ route('admin.wallet.topup.create') }}" class="btn btn-primary btn-sm">
                Recargar
              </a>
            </div>
          </div>

          @if(!empty($nextMonthEstimate) && $isPerVehicle)
            <div class="alert alert-light mt-3 mb-0">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="fw-semibold">Próximo cargo estimado (mes completo)</div>
                  <div class="small text-muted">
                    Basado en {{ $activeVehicles }} vehículos activos hoy.
                  </div>
                </div>
                <div class="fw-bold">${{ number_format($nextMonthEstimate, 2) }} MXN</div>
              </div>
            </div>
          @endif

        </div>
      </div>
    </div>

    {{-- Resumen del plan --}}
    <div class="col-12 col-lg-4">
      <div class="card h-100 soft-card">
        <div class="card-header"><strong>Resumen del plan</strong></div>
        <div class="card-body small">

          <div class="mb-2 d-flex gap-2 flex-wrap">
            <span class="badge bg-{{ $badgeSt }} stat-pill text-uppercase">{{ $st }}</span>

            @if($bm === 'commission')
              <span class="badge bg-info stat-pill">Modelo: comisión</span>
            @else
              <span class="badge bg-primary stat-pill">Modelo: por vehículo</span>
              <span class="badge bg-dark stat-pill">Corte: fin de mes</span>
            @endif
          </div>

          @if($st === 'trial' && !empty($trialEndsAt))
            <div class="alert alert-warning">
              <div class="fw-semibold">Trial activo</div>
              <div class="small">
                Termina el <strong>{{ $trialEndsTxt }}</strong>
                @if($trialDaysLeft !== null)
                  ({{ $trialDaysLeft }} días restantes).
                @endif
              </div>
              <div class="small mt-1">
                Al terminar el trial, el servicio se pausa hasta que recargues el wallet.
              </div>
            </div>
          @endif

          <dl class="row mb-0">
            <dt class="col-5">Código de plan</dt>
            <dd class="col-7">{{ $profile->plan_code ?? '—' }}</dd>

            <dt class="col-5">Trial hasta</dt>
            <dd class="col-7">{{ $trialEndsTxt }}</dd>

            <dt class="col-5">Vehículos en trial</dt>
            <dd class="col-7">{{ $profile->trial_vehicles ?? 0 }}</dd>

            <dt class="col-5">Última factura</dt>
            <dd class="col-7">{{ optional($profile->last_invoice_date)->toDateString() ?? '—' }}</dd>

            <dt class="col-5">Próximo cargo</dt>
            <dd class="col-7">{{ optional($profile->next_invoice_date)->toDateString() ?? '—' }}</dd>

            <dt class="col-5">Notas</dt>
            <dd class="col-7">{{ $profile->notes ?: '—' }}</dd>
          </dl>

        </div>
      </div>
    </div>

    {{-- Vehículos y reglas --}}
    <div class="col-12 col-lg-4">
      <div class="card h-100 soft-card">
        <div class="card-header"><strong>Vehículos y reglas</strong></div>
        <div class="card-body small">

          <dl class="row mb-0">
            <dt class="col-6">Vehículos activos</dt>
            <dd class="col-6">{{ $activeVehicles }}</dd>

            <dt class="col-6">Vehículos totales</dt>
            <dd class="col-6">{{ $totalVehicles }}</dd>

            @if($isPerVehicle)
              <dt class="col-6">Incluidos</dt>
              <dd class="col-6">{{ $profile->included_vehicles ?? 0 }}</dd>

              <dt class="col-6">Máximo</dt>
              <dd class="col-6">{{ $profile->max_vehicles ?? 'Ilimitado' }}</dd>

              <dt class="col-6">Base mensual</dt>
              <dd class="col-6">${{ number_format($profile->base_monthly_fee ?? 0, 2) }} MXN</dd>

              <dt class="col-6">Extra por vehículo</dt>
              <dd class="col-6">${{ number_format($profile->price_per_vehicle ?? 0, 2) }} MXN</dd>
            @else
              <dt class="col-6">% comisión</dt>
              <dd class="col-6">{{ $profile->commission_percent !== null ? $profile->commission_percent.' %' : '—' }}</dd>

              <dt class="col-6">Mínimo mensual</dt>
              <dd class="col-6">${{ number_format($profile->commission_min_fee ?? 0, 2) }} MXN</dd>
            @endif
          </dl>

          <hr>

          @if($canRegisterNewVehicle)
            <div class="alert alert-success mb-0">
              Puedes registrar nuevos vehículos.
            </div>
          @else
            <div class="alert alert-warning mb-0">
              No puedes registrar nuevos vehículos ahora.
              @if($canRegisterReason)
                <div class="small mt-1">{{ $canRegisterReason }}</div>
              @endif
            </div>
          @endif

        </div>
      </div>
    </div>

  </div>

  {{-- Tabla de facturas --}}
  <div class="card mt-4 soft-card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <strong>Facturas de la central</strong>
      <span class="text-muted small">Estados: draft · pending · paid · canceled</span>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead>
          <tr>
            <th>#</th>
            <th>Periodo</th>
            <th>Emitida</th>
            <th>Vence</th>
            <th>Status</th>
            <th class="text-end">Total</th>
            <th class="text-end">Acciones</th>
          </tr>
          </thead>
          <tbody>
          @forelse($invoices as $inv)
            @php
              $badge = match(strtolower($inv->status)) {
                'paid'     => 'success',
                'pending'  => 'warning',
                'draft'    => 'secondary',
                'canceled' => 'dark',
                default    => 'secondary',
              };
            @endphp
            <tr>
              <td>#{{ $inv->id }}</td>
              <td>{{ $inv->period_start?->toDateString() }} → {{ $inv->period_end?->toDateString() }}</td>
              <td>{{ $inv->issue_date?->toDateString() }}</td>
              <td>{{ $inv->due_date?->toDateString() }}</td>
              <td>
                <span class="badge bg-{{ $badge }} text-uppercase">{{ $inv->status }}</span>
              </td>
              <td class="text-end">${{ number_format($inv->total, 2) }} {{ $inv->currency }}</td>
              <td class="text-end">
                <div class="btn-group btn-group-sm" role="group">
                  <a href="{{ route('admin.billing.invoices.show', $inv) }}" class="btn btn-outline-secondary">Ver</a>
                  <a href="{{ route('admin.billing.invoices.csv', $inv) }}" class="btn btn-outline-secondary">CSV</a>
                  <a href="{{ route('admin.billing.invoice_pdf', $inv) }}" class="btn btn-primary">PDF</a>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-3 text-muted">
                Aún no hay facturas generadas para tu central.
              </td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if($invoices instanceof \Illuminate\Pagination\LengthAwarePaginator)
      <div class="card-footer">{{ $invoices->links() }}</div>
    @endif
  </div>

</div>
@endsection
