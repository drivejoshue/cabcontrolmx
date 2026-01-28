@extends('layouts.partner')

@section('title', 'Dashboard')

@section('content')
@php
    /** @var \App\Models\Partner $partner */
    $s  = $stats ?? [];
    $ui = $ui ?? [];

    $money = function($n){
        return number_format((float)($n ?? 0), 2);
    };

    $dt = function($v){
        if (!$v) return '—';
        try {
            return \Illuminate\Support\Carbon::parse($v)->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            return (string)$v;
        }
    };
      $forecastLabels = [
    'balance_now' => 'Saldo actual',
    'vehicles_today' => 'Vehículos hoy',
    'today' => 'Fecha',
    'daily_rate_this_month' => 'Tarifa diaria (mes actual)',
    'daily_cost_est' => 'Costo diario estimado',
    'days_left_in_month' => 'Días restantes del mes',
    'remaining_cost_est' => 'Costo restante estimado (mes)',
    'end_month_balance_est' => 'Saldo estimado fin de mes',
    'next_month_start' => 'Inicio del siguiente mes',
    'days_next_month' => 'Días del siguiente mes',
    'daily_rate_next_month' => 'Tarifa diaria (siguiente mes)',
    'next_month_cost_est' => 'Costo estimado (siguiente mes)',
    'recommended_topup_for_next_month' => 'Recarga recomendada (siguiente mes)',
    'currency' => 'Moneda',
  ];

  $formatAsMoney = [
    'balance_now',
    'daily_cost_est',
    'remaining_cost_est',
    'end_month_balance_est',
    'next_month_cost_est',
    'recommended_topup_for_next_month',
  ];
@endphp

<div class="d-flex align-items-start justify-content-between mb-3">
    <div>
        <h1 class="h3 mb-0">{{ $partner->name }}</h1>
        <div class="text-muted small">
            Tenant #{{ $partner->tenant_id }} · Partner #{{ $partner->id }}
        </div>
    </div>

    <div class="d-flex gap-2">
        <a href="{{ route('partner.wallet.index') }}" class="btn btn-primary">
            Wallet
        </a>
        <a href="{{ route('partner.vehicles.index') }}" class="btn btn-outline-secondary">
            Vehículos
        </a>
        <a href="{{ route('partner.drivers.index') }}" class="btn btn-outline-secondary">
            Conductores
        </a>
    </div>
</div>

{{-- ALERT / ESTADO (UI State) --}}
@if(($ui['level'] ?? null) === 'danger')
    <div class="alert alert-danger">
        <div class="d-flex">
            <div>
                <strong>{{ $ui['title'] ?? 'Atención' }}</strong>
                <div class="text-muted">{{ $ui['message'] ?? '' }}</div>
            </div>
        </div>
    </div>
@elseif(($ui['level'] ?? null) === 'warning')
    <div class="alert alert-warning">
        <strong>{{ $ui['title'] ?? 'Aviso' }}</strong>
        <div class="text-muted">{{ $ui['message'] ?? '' }}</div>
    </div>
@elseif(($ui['level'] ?? null) === 'success')
    <div class="alert alert-success">
        <strong>{{ $ui['title'] ?? 'OK' }}</strong>
        <div class="text-muted">{{ $ui['message'] ?? '' }}</div>
    </div>
@endif

<div class="row g-3">
    {{-- VEHÍCULOS --}}
    <div class="col-12 col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Vehículos</div>

                <div class="d-flex align-items-baseline justify-content-between">
                    <div class="h2 mb-0" id="kpi-vehicles-total">{{ (int)($s['vehicles_total'] ?? 0) }}</div>
                    <div class="text-muted small">Total</div>
                </div>

                <div class="mt-2 d-flex flex-wrap gap-2">
                    <span class="badge bg-success-lt text-success" id="kpi-vehicles-active">
                        Activos: {{ (int)($s['vehicles_active'] ?? 0) }}
                    </span>

                    <span class="badge bg-warning-lt text-warning" id="kpi-vehicles-pending">
                        Pend. verificación: {{ (int)($s['vehicles_pending_verify'] ?? 0) }}
                    </span>
                </div>

                <div class="text-muted small mt-2">
                    Recomendación: mantén verificados para habilitar operación sin fricción.
                </div>
            </div>
        </div>
    </div>

    {{-- CONDUCTORES --}}
    <div class="col-12 col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Conductores</div>

                <div class="d-flex align-items-baseline justify-content-between">
                    <div class="h2 mb-0" id="kpi-drivers-total">{{ (int)($s['drivers_total'] ?? 0) }}</div>
                    <div class="text-muted small">Total</div>
                </div>

                <div class="mt-2 d-flex flex-wrap gap-2">
                    <span class="badge bg-info-lt text-info" id="kpi-drivers-enabled">
                        Habilitados: {{ (int)($s['drivers_enabled'] ?? 0) }}
                    </span>

                    <span class="badge bg-warning-lt text-warning" id="kpi-drivers-pending">
                        Pend. verificación: {{ (int)($s['drivers_pending_verify'] ?? 0) }}
                    </span>
                </div>

                <div class="text-muted small mt-2">
                    “Habilitado” = puede iniciar sesión y operar.
                </div>
            </div>
        </div>
    </div>

    {{-- ASIGNACIONES ABIERTAS --}}
    <div class="col-12 col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Operación</div>

                <div class="d-flex align-items-baseline justify-content-between">
                    <div class="h2 mb-0" id="kpi-open-assignments">{{ (int)($s['open_assignments'] ?? 0) }}</div>
                    <div class="text-muted small">Asignaciones abiertas</div>
                </div>

                <div class="mt-2 text-muted small">
                    Asignación abierta = conductor con vehículo asignado sin <code>end_at</code>.
                </div>
            </div>
        </div>
    </div>
</div>

{{-- WALLET (unificado) + FORECAST + RECARGAS + MOVIMIENTOS --}}
<div class="row g-3 mt-0">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div>
          <h5 class="card-title mb-0">Wallet & Actividad</h5>
          <div class="text-muted small">Saldo, proyección y últimos movimientos</div>
        </div>

        <div class="d-flex gap-2">
          @if(isset($ui['cta_label'], $ui['cta_url']) && $ui['cta_url'])
            <a href="{{ $ui['cta_url'] }}" class="btn btn-sm btn-primary">{{ $ui['cta_label'] }}</a>
          @endif
          <a href="{{ route('partner.wallet.index') }}" class="btn btn-sm btn-outline-secondary">Ver wallet</a>
        </div>
      </div>

      <div class="card-body">
        <div class="row g-3">
          {{-- SALDO + ESTADO --}}
          <div class="col-12 col-lg-4">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small">Saldo disponible</div>
              <div class="d-flex align-items-baseline gap-2">
                <div class="h2 mb-0" id="kpi-wallet-balance">{{ $money($s['wallet_balance'] ?? 0) }}</div>
                <div class="text-muted" id="kpi-wallet-currency">{{ $s['wallet_currency'] ?? 'MXN' }}</div>
              </div>

              <div class="mt-2 d-flex flex-wrap gap-2">
                @if(isset($ui['badge']))
                  <span class="badge bg-secondary-lt text-secondary" id="kpi-wallet-badge">{{ $ui['badge'] }}</span>
                @endif

                @if(($ui['level'] ?? null) === 'danger')
                  <span class="badge bg-danger-lt text-danger">Acción requerida</span>
                @elseif(($ui['level'] ?? null) === 'warning')
                  <span class="badge bg-warning-lt text-warning">Atención</span>
                @elseif(($ui['level'] ?? null) === 'success')
                  <span class="badge bg-success-lt text-success">OK</span>
                @endif
              </div>

              @if(!empty($ui['hint']))
                <div class="text-muted small mt-2">{{ $ui['hint'] }}</div>
              @endif
            </div>
          </div>

          {{-- FORECAST (compacto y útil) --}}
          <div class="col-12 col-lg-8">
            <div class="border rounded p-3 h-100">
              <div class="d-flex align-items-center justify-content-between">
                <div class="text-muted small">Forecast (prepaid)</div>
                <div class="text-muted small">Últimos {{ is_array($forecast ?? null) ? count($forecast) : 0 }} indicadores</div>
              </div>

              <div class="row g-2 mt-1">
                @if(is_array($forecast))
                  @foreach($forecast as $k => $v)
                    <div class="col-6 col-xl-4">
                      <div class="bg-body-tertiary border rounded p-2 h-100">
                      @php
  $label = $forecastLabels[$k] ?? \Illuminate\Support\Str::headline((string)$k);
  $isMoney = in_array($k, $formatAsMoney, true);
@endphp

<div class="text-muted small">{{ $label }}</div>
                        <div class="fw-semibold" id="forecast-{{ \Illuminate\Support\Str::slug((string)$k) }}">
                          @if(is_numeric($v))
                            {{ $money($v) }}
                          @elseif(is_array($v) || is_object($v))
                            <span class="text-muted">—</span>
                          @else
                            {{ (string)$v }}
                          @endif
                        </div>
                      </div>
                    </div>
                  @endforeach
                @else
                  <div class="col-12 text-muted">Sin forecast disponible.</div>
                @endif
              </div>

              <div class="text-muted small mt-2">
                Proyección rápida para anticipar recargas y evitar interrupciones.
              </div>
            </div>
          </div>

          {{-- RECARGAS + MOVIMIENTOS (en la misma card) --}}
          <div class="col-12">
            <div class="row g-3">
              <div class="col-12 col-lg-6">
                <div class="border rounded">
                  <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                      <div class="fw-semibold">Recargas recientes</div>
                      <div class="text-muted small">Últimas 5</div>
                    </div>
                  </div>
                  <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Proveedor</th>
                          <th class="text-end">Monto</th>
                          <th>Status</th>
                          <th class="text-muted">Fecha</th>
                        </tr>
                      </thead>
                      <tbody>
                        @forelse($recentTopups as $t)
                          <tr>
                            <td>{{ $t->id }}</td>
                            <td>{{ $t->provider ?? '—' }}</td>
                            <td class="text-end">{{ $money($t->amount) }} {{ $t->currency ?? ($s['wallet_currency'] ?? 'MXN') }}</td>
                            <td><span class="badge bg-secondary-lt text-secondary">{{ $t->status ?? '—' }}</span></td>
                            <td class="text-muted small">{{ $dt($t->created_at ?? null) }}</td>
                          </tr>
                        @empty
                          <tr><td colspan="5" class="text-muted">Sin recargas todavía.</td></tr>
                        @endforelse
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="col-12 col-lg-6">
                <div class="border rounded">
                  <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                      <div class="fw-semibold">Movimientos recientes</div>
                      <div class="text-muted small">Últimos 8</div>
                    </div>
                  </div>
                  <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Tipo</th>
                          <th class="text-end">Monto</th>
                          <th class="text-muted">Fecha</th>
                        </tr>
                      </thead>
                      <tbody>
                        @forelse($recentMovements as $m)
                          @php
                            $amt = (float)($m->amount ?? 0);
                            $isPos = $amt >= 0;
                          @endphp
                          <tr>
                            <td>{{ $m->id }}</td>
                            <td>
                              <span class="badge {{ $isPos ? 'bg-success-lt text-success' : 'bg-danger-lt text-danger' }}">
                                {{ $m->type ?? '—' }}
                              </span>
                            </td>
                            <td class="text-end">
                              <span class="{{ $isPos ? 'text-success' : 'text-danger' }}">
                                {{ $money($amt) }}
                              </span>
                              {{ $m->currency ?? ($s['wallet_currency'] ?? 'MXN') }}
                            </td>
                            <td class="text-muted small">{{ $dt($m->created_at ?? null) }}</td>
                          </tr>
                        @empty
                          <tr><td colspan="4" class="text-muted">Sin movimientos todavía.</td></tr>
                        @endforelse
                      </tbody>
                    </table>
                  </div>
                  <div class="p-2 text-muted small">
                    Cargos/prepago, recargas y ajustes administrativos.
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>{{-- row --}}
      </div>{{-- card-body --}}
    </div>
  </div>
</div>

{{-- GRÁFICA: ACTIVIDAD DIARIA (viajes) --}}
<div class="row g-3 mt-0">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div>
          <h5 class="card-title mb-0">Actividad diaria</h5>
          <div class="text-muted small">Últimos 14 días · Finalizados vs Cancelados</div>
        </div>
      </div>
      <div class="card-body">
        <div style="height: 260px;">
          <canvas id="chart-activity-daily"></canvas>
        </div>
        <div class="text-muted small mt-2">
          Señal rápida para validar tracción y estabilidad operativa.
        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  // =========================
  // Chart: Actividad diaria
  // =========================
  const activity = @json($activityChart ?? ['labels'=>[], 'finished'=>[], 'canceled'=>[]]);

  function mountActivityChart() {
    const el = document.getElementById('chart-activity-daily');
    if (!el || !window.Chart) return;

    const ctx = el.getContext('2d');
    // Si re-renderizas por turbo/nav, limpia el anterior si existe
    if (el._chart) { try { el._chart.destroy(); } catch(e) {} }

    el._chart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: activity.labels || [],
        datasets: [
          { label: 'Finalizados', data: activity.finished || [] },
          { label: 'Cancelados', data: activity.canceled || [] },
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: true },
          tooltip: { enabled: true }
        },
        scales: {
          x: { stacked: true },
          y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });
  }

  mountActivityChart();

  // =========================
  // Polling KPI (tu código actual)
  // =========================
  const url = @json(route('partner.api.dashboard'));
  const range = 'today';

  function setText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  async function tick() {
    try {
      const res = await fetch(url + '?range=' + encodeURIComponent(range), {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      const j = await res.json();
      if (!j || !j.ok) return;

      const k = j.data?.kpi || {};

      if (k.vehicles) {
        setText('kpi-vehicles-total', k.vehicles.total ?? 0);
        setText('kpi-vehicles-active', 'Activos: ' + (k.vehicles.active ?? 0));
        if (document.getElementById('kpi-vehicles-pending')) {
          setText('kpi-vehicles-pending', 'Pend. verificación: ' + (k.vehicles.pending_verify ?? 0));
        }
      }

      if (k.drivers) {
        setText('kpi-drivers-total', k.drivers.total ?? 0);
        const enabled = (k.drivers.enabled ?? k.drivers.active ?? null);
        if (enabled !== null && document.getElementById('kpi-drivers-enabled')) {
          setText('kpi-drivers-enabled', 'Habilitados: ' + enabled);
        }
        if (document.getElementById('kpi-drivers-pending')) {
          setText('kpi-drivers-pending', 'Pend. verificación: ' + (k.drivers.pending_verify ?? 0));
        }
      }

      if (k.assignments && document.getElementById('kpi-open-assignments')) {
        setText('kpi-open-assignments', k.assignments.open ?? 0);
      }

      if (k.wallet) {
        const bal = (k.wallet.balance ?? 0);
        setText('kpi-wallet-balance', Number(bal).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}));
        setText('kpi-wallet-currency', k.wallet.currency ?? 'MXN');
        if (document.getElementById('kpi-wallet-badge') && k.wallet.badge) {
          setText('kpi-wallet-badge', k.wallet.badge);
        }
      }

      if (j.data?.ui?.badge && document.getElementById('kpi-wallet-badge')) {
        setText('kpi-wallet-badge', j.data.ui.badge);
      }

    } catch (e) {}
  }

  tick();
  setInterval(tick, 12000);
})();
</script>
@endpush

