@extends('layouts.partner')

@section('content')
@push('styles')
<style>
  .cc-table-scroll {
    max-height: 360px;     /* tamaño medio */
    overflow: auto;        /* scroll vertical */
  }
  .cc-card-toggle {
    cursor: pointer;
    user-select: none;
  }
</style>
@endpush

<div class="container-fluid">
  <h1 class="h3 mb-3">Wallet</h1>

  <div class="row g-3">

    {{-- LEFT: Saldo / acciones --}}
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted">Saldo</div>
          <div class="fs-3 fw-bold">
            ${{ number_format((float)($wallet?->balance ?? 0), 2) }} {{ $wallet?->currency ?? 'MXN' }}
          </div>

          @if(!empty($requiredToAdd))
            <div class="mt-3 small">
              <div class="text-muted">Recarga mínima para activar un vehículo hoy</div>
              <div class="fw-semibold">
                ${{ number_format((float)($requiredToAdd['required_amount'] ?? 0), 2) }} {{ $requiredToAdd['currency'] ?? 'MXN' }}
              </div>

              @if(!empty($requiredToAdd['period_start']) && !empty($requiredToAdd['period_end']))
                <div class="text-muted">
                  Periodo: {{ $requiredToAdd['period_start'] }} → {{ $requiredToAdd['period_end'] }}
                </div>
              @endif
            </div>
          @endif

  

          <div class="mt-3 d-grid gap-2">
            <a class="btn btn-primary" href="{{ route('partner.topups.create') }}">Solicitar recarga</a>
            <a class="btn btn-outline-secondary" href="{{ route('partner.topups.index') }}">Ver recargas</a>
          </div>

          @if(!empty($ui['next_cut_date']))
            <div class="mt-3 small text-muted">
              Próximo corte informativo: <span class="fw-semibold">{{ $ui['next_cut_date'] }}</span>
            </div>
          @endif

              @if(!empty($forecast))
  <div class="alert alert-info mb-3">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <strong>Proyección de saldo y consumo</strong>
        <div class="small mt-1">
          Vehículos hoy: <strong>{{ $forecast['vehicles_today'] }}</strong> |
          Ritmo diario estimado: <strong>${{ number_format($forecast['daily_cost_est'], 2) }} {{ $forecast['currency'] }}/día</strong>
        </div>

        <div class="small mt-2">
          Si no recargas, terminarás el mes con:
          <strong>${{ number_format($forecast['end_month_balance_est'], 2) }} {{ $forecast['currency'] }}</strong>
          (estimado con {{ $forecast['days_left_in_month'] }} día(s) restantes).
        </div>

        <div class="small mt-1">
          Costo estimado del siguiente mes:
          <strong>${{ number_format($forecast['next_month_cost_est'], 2) }} {{ $forecast['currency'] }}</strong>
          ({{ $forecast['days_next_month'] }} días).
        </div>

        <div class="small mt-1">
          Recarga sugerida para cubrir el siguiente mes:
          <strong>${{ number_format($forecast['recommended_topup_for_next_month'], 2) }} {{ $forecast['currency'] }}</strong>
        </div>
      </div>

      
    </div>
  </div>
@endif
        </div>


      </div>



    </div>



    {{-- RIGHT: Resumen / proyecciones / detalle / movimientos --}}
    <div class="col-lg-8">

      {{-- Consumo mes actual --}}
      <div class="card mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
              <div class="text-muted">Consumo (mes actual)</div>
              <div class="small text-muted">
                Periodo: {{ $summary['period_start'] ?? '-' }} → {{ $summary['period_end'] ?? '-' }}
                @if(!empty($summary['vehicles_count']))
                  · Vehículos: <span class="fw-semibold">{{ $summary['vehicles_count'] }}</span>
                @endif
              </div>
            </div>
            <div class="text-end">
              <div class="small text-muted">Precio por vehículo</div>
              <div class="fw-semibold">
                ${{ number_format((float)($summary['ppv'] ?? 0), 2) }} {{ $summary['currency'] ?? 'MXN' }}/mes
              </div>
            </div>
          </div>

          <hr class="my-3">

          <div class="row g-3">
            <div class="col-sm-6 col-lg-3">
              <div class="text-muted small">Base del mes (prorrateo)</div>
              <div class="fw-bold">
                ${{ number_format((float)($summary['base_prorated_total'] ?? 0), 2) }} {{ $summary['currency'] ?? 'MXN' }}
              </div>
            </div>

            <div class="col-sm-6 col-lg-3">
              <div class="text-muted small">Consumido (a hoy)</div>
              <div class="fw-bold">
                ${{ number_format((float)($summary['consumed_to_date_total'] ?? 0), 2) }} {{ $summary['currency'] ?? 'MXN' }}
              </div>
            </div>

            <div class="col-sm-6 col-lg-3">
              <div class="text-muted small">Restante (estimado)</div>
              <div class="fw-bold">
                ${{ number_format((float)($summary['remaining_total'] ?? 0), 2) }} {{ $summary['currency'] ?? 'MXN' }}
              </div>
            </div>

            <div class="col-sm-6 col-lg-3">
              <div class="text-muted small">Ritmo diario (estimado)</div>
              <div class="fw-bold">
                ${{ number_format((float)($summary['daily_rate_estimated'] ?? 0), 2) }} {{ $summary['currency'] ?? 'MXN' }}/día
              </div>
            </div>
          </div>

          <div class="mt-3 small text-muted">
            Se descuenta diariamente según vehículos activos. Este resumen es una proyección para planear recargas.
          </div>
        </div>
      </div>

      {{-- Proyección --}}
      {{-- Detalle por vehículo (colapsable + scroll) --}}
@if(!empty($summary['items']) && count($summary['items']) > 0)
  @php $vehicleCount = count($summary['items']); @endphp

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="cc-card-toggle"
           data-bs-toggle="collapse"
           data-bs-target="#vehDetailCollapse"
           aria-expanded="true"
           aria-controls="vehDetailCollapse">
        <span class="fw-semibold">Detalle por vehículo (mes actual)</span>
        <span class="text-muted small">· {{ $vehicleCount }} vehículos</span>
      </div>

      <div class="d-flex align-items-center gap-2">
        <div class="text-muted small d-none d-md-inline">Prorrateo desde alta en el mes</div>
        <button class="btn btn-sm btn-outline-secondary"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#vehDetailCollapse"
                aria-expanded="true"
                aria-controls="vehDetailCollapse">
          Mostrar / ocultar
        </button>
      </div>
    </div>

    <div id="vehDetailCollapse" class="collapse show">
      <div class="cc-table-scroll">
        <div class="table-responsive">
          <table class="table table-striped mb-0 align-middle">
            <thead class="sticky-top bg-body">
              <tr>
                <th>Económico</th>
                <th>Placa</th>
                <th>Desde</th>
                <th class="text-end">Base mes</th>
                <th class="text-end">Consumido</th>
                <th class="text-end">Restante</th>
              </tr>
            </thead>
            <tbody>
              @foreach($summary['items'] as $it)
                <tr>
                  <td class="fw-semibold">{{ $it['economico'] }}</td>
                  <td class="text-muted">{{ $it['plate'] }}</td>
                  <td>{{ $it['start_at'] }}</td>
                  <td class="text-end">${{ number_format((float)$it['base'], 2) }} {{ $summary['currency'] ?? 'MXN' }}</td>
                  <td class="text-end">${{ number_format((float)$it['consumed'], 2) }} {{ $summary['currency'] ?? 'MXN' }}</td>
                  <td class="text-end">${{ number_format((float)$it['remaining'], 2) }} {{ $summary['currency'] ?? 'MXN' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endif


    {{-- Movimientos (colapsable + scroll) --}}
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="cc-card-toggle"
         data-bs-toggle="collapse"
         data-bs-target="#movementsCollapse"
         aria-expanded="true"
         aria-controls="movementsCollapse">
      <span class="fw-semibold">Movimientos</span>
      <span class="text-muted small">· últimos {{ $movements->count() }} (página {{ $movements->currentPage() }})</span>
    </div>

    <button class="btn btn-sm btn-outline-secondary"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#movementsCollapse"
            aria-expanded="true"
            aria-controls="movementsCollapse">
      Mostrar / ocultar
    </button>
  </div>

  <div id="movementsCollapse" class="collapse show">
    <div class="cc-table-scroll">
      <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
          <thead class="sticky-top bg-body">
            <tr>
              <th style="width: 140px;">Fecha</th>
              <th style="width: 120px;">Tipo</th>
              <th style="width: 70px;">Dir</th>
              <th style="width: 130px;">Monto</th>
              <th style="width: 130px;">Saldo</th>
              <th>Detalle</th>
            </tr>
          </thead>

          <tbody>
            @forelse($movements as $m)
              @php
                $isCharge = ($m->ref_type === 'partner_daily_charges');
                $isTopup  = ($m->ref_type === 'partner_topups');

                $created = $m->created_at ? \Carbon\Carbon::parse($m->created_at)->format('Y-m-d H:i') : '-';
                $money = '$'.number_format((float)$m->amount, 2).' '.($m->currency ?? 'MXN');
                $balAfter = ($m->balance_after !== null)
                  ? '$'.number_format((float)$m->balance_after, 2).' '.($m->currency ?? 'MXN')
                  : '-';
              @endphp

              <tr>
                <td class="text-muted">{{ $created }}</td>

                <td>
                  @if($isCharge)
                    <span class="fw-semibold">Cargo</span>
                  @elseif($isTopup)
                    <span class="fw-semibold">Recarga</span>
                  @else
                    <span class="fw-semibold">{{ $m->type }}</span>
                  @endif
                </td>

                <td class="text-muted">{{ $m->direction ?? '-' }}</td>
                <td>{{ $money }}</td>
                <td>{{ $balAfter }}</td>

                <td>
                  @if($isCharge)
                    <div class="fw-semibold">Cobro diario por vehículos</div>
                    <div class="text-muted small">
                      {{ $m->charge_date ?? '-' }}
                      · Vehículos: {{ $m->charge_vehicles ?? '-' }}
                      · Tasa: ${{ isset($m->charge_rate) ? number_format((float)$m->charge_rate, 2) : '-' }}/veh/día
                      @if(!empty($m->settled_at))
                        · <span class="badge bg-success-subtle text-success">Aplicado</span>
                      @else
                        · <span class="badge bg-warning-subtle text-warning">Pendiente</span>
                      @endif
                    </div>

                  @elseif($isTopup)
                    @php
                      $topupRef =
                        ($m->topup_bank_ref ?? null)
                        ?: ($m->topup_mp_payment_id ?? null)
                        ?: ($m->topup_external_reference ?? null)
                        ?: ($m->topup_payer_ref ?? null)
                        ?: ($m->external_ref ?? null)
                        ?: ('TOPUP-'.$m->ref_id);

                      $credited = !empty($m->topup_credited_at);
                    @endphp

                    <div class="fw-semibold">Depósito / recarga</div>
                    <div class="text-muted small">
                      {{ $m->topup_method ?? 'depósito' }}
                      @if(!empty($m->topup_provider)) · {{ $m->topup_provider }} @endif
                      · Ref: {{ $topupRef }}
                      @if($credited)
                        · <span class="badge bg-success-subtle text-success">Acreditado</span>
                      @else
                        · <span class="badge bg-warning-subtle text-warning">En revisión</span>
                      @endif
                    </div>

                  @else
                    <div class="text-muted small">{{ $m->ref_type }} #{{ $m->ref_id }}</div>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-muted p-3">Sin movimientos.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- paginación fuera del scroll para que no se pierda --}}
  <div class="card-footer">
    {{ $movements->links() }}
  </div>
</div>

    </div>


  </div>
</div>
@endsection
