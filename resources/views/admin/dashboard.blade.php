@extends('layouts.admin')
@section('title','Dashboard')

@section('content')
<div class="row g-3">

  {{-- HEADER --}}
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
        <div>
          <h3 class="card-title mb-1">Dashboard de Operaciones</h3>
          <p class="text-muted mb-0">Monitorea el rendimiento de tu flota en tiempo real.</p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-md-end">
          <div class="text-end small text-muted me-2">
            <div>{{ now()->format('d M Y H:i') }}</div>
            <div>Tenant: <strong>{{ auth()->user()->tenant_id ?? '-' }}</strong></div>
          </div>

          @if(Route::has('admin.dispatch.index'))
            <a class="btn btn-primary btn-sm" href="{{ route('admin.dispatch.index') }}">
              <i class="fas fa-broadcast-tower me-1"></i> Abrir despacho
            </a>
          @endif

          @if(Route::has('admin.tenant.edit'))
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.tenant.edit') }}">
              <i class="fas fa-cog me-1"></i> Mi central
            </a>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- KPIs --}}
  <div class="col-xl-3 col-md-6">
    <div class="card border-0 shadow-sm h-100 kpi-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted fw-semibold">Corridas hoy</div>
            <div class="kpi-value">{{ $metrics['total_rides_today'] ?? 0 }}</div>
            <div class="small text-muted mt-1">
              <span class="text-success">{{ $metrics['completion_rate'] ?? 0 }}% completadas</span>
              <span class="float-end">{{ $metrics['cancellation_rate'] ?? 0 }}% canceladas</span>
            </div>
          </div>
          <div class="icon-wrap bg-primary-subtle text-primary">
            <i class="fas fa-taxi"></i>
          </div>
        </div>

        <div class="mt-3">
          <div class="progress progress-sm">
            <div class="progress-bar bg-primary" style="width: {{ min(($metrics['completion_rate'] ?? 0), 100) }}%"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-md-6">
    <div class="card border-0 shadow-sm h-100 kpi-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted fw-semibold">Conductores activos</div>
            <div class="kpi-value">{{ $metrics['active_drivers'] ?? 0 }}</div>
            <div class="small text-muted mt-1">
              <i class="fas fa-star text-warning me-1"></i>
              Rating promedio: <strong>{{ $metrics['average_rating'] ?? 0 }}/5</strong>
            </div>
          </div>
          <div class="icon-wrap bg-success-subtle text-success">
            <i class="fas fa-user-circle"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-md-6">
    <div class="card border-0 shadow-sm h-100 kpi-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted fw-semibold">Vehículos verificados</div>
            <div class="kpi-value">{{ $metrics['total_vehicles'] ?? 0 }}</div>
            <div class="small text-muted mt-1">
              {{ $metrics['total_passengers'] ?? 0 }} pasajeros registrados
            </div>
          </div>
          <div class="icon-wrap bg-info-subtle text-info">
            <i class="fas fa-car"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-md-6">
    <div class="card border-0 shadow-sm h-100 kpi-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted fw-semibold">Ingresos hoy</div>
            <div class="kpi-value">${{ number_format($metrics['total_revenue_today'] ?? 0, 2) }}</div>
            <div class="small text-muted mt-1">
              <i class="fas fa-chart-line text-success me-1"></i> Ventana 30 días
            </div>
          </div>
          <div class="icon-wrap bg-warning-subtle text-warning">
            <i class="fas fa-money-bill-wave"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- CHARTS (misma altura) --}}
  <div class="col-xl-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0">Tendencia de corridas (7 días)</h5>
        <span class="badge bg-light text-muted">Actualizado</span>
      </div>
      <div class="card-body">
        <div class="chart-wrap chart-lg">
          <canvas id="ridesTrendChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-0">
        <h5 class="card-title mb-0">Estado actual</h5>
      </div>
      <div class="card-body">
        <div class="chart-wrap chart-lg">
          <canvas id="ridesStatusChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-0">
        <h5 class="card-title mb-0">Distribución por horas (hoy)</h5>
      </div>
      <div class="card-body">
        <div class="chart-wrap chart-md">
          <canvas id="hoursDistributionChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-0">
        <h5 class="card-title mb-0">Métodos de pago (30 días)</h5>
      </div>
      <div class="card-body">
        <div class="chart-wrap chart-md">
          <canvas id="paymentMethodsChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  {{-- TABLAS --}}
  <div class="col-xl-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Top conductores (30 días)</h5>

        @if(Route::has('admin.drivers.index'))
          <a href="{{ route('admin.drivers.index') }}" class="btn btn-sm btn-outline-primary">
            Ver todos
          </a>
        @endif
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>Conductor</th>
                <th class="text-end">Corridas</th>
                <th class="text-end">Ingresos</th>
                <th class="text-end">Rating</th>
              </tr>
            </thead>
            <tbody>
              @forelse($charts['top_drivers'] ?? [] as $driver)
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="icon-wrap icon-sm bg-primary-subtle text-primary">
                        <i class="fas fa-user"></i>
                      </div>
                      <div class="fw-semibold">{{ $driver->name }}</div>
                    </div>
                  </td>
                  <td class="text-end">{{ $driver->total_rides }}</td>
                  <td class="text-end">${{ number_format($driver->total_revenue ?? 0, 2) }}</td>
                  <td class="text-end">
                    <span class="badge bg-warning-subtle text-warning">
                      {{ number_format($driver->avg_rating ?? 0, 1) }}
                      <i class="fas fa-star ms-1"></i>
                    </span>
                  </td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted py-4">Sin datos todavía</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Programadas próximas</h5>
        <span class="badge bg-secondary">Opcional</span>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>Pasajero</th>
                <th>Origen</th>
                <th>Destino</th>
                <th class="text-end">Hora</th>
              </tr>
            </thead>
            <tbody>
              @forelse($scheduled_rides ?? [] as $ride)
                <tr>
                  <td class="fw-semibold">{{ $ride->passenger_name ?? 'N/A' }}</td>
                  <td><span class="text-truncate d-inline-block" style="max-width: 180px;">{{ $ride->origin_label ?? 'Sin origen' }}</span></td>
                  <td><span class="text-truncate d-inline-block" style="max-width: 180px;">{{ $ride->dest_label ?? 'Sin destino' }}</span></td>
                  <td class="text-end">
                    <span class="badge bg-info-subtle text-info">
                      {{ \Carbon\Carbon::parse($ride->scheduled_for)->format('H:i') }}
                    </span>
                  </td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No hay programadas</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  {{-- ÚLTIMAS CORRIDAS --}}
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Últimas corridas</h5>

        {{-- Solo si existe un listado real --}}
        @if(Route::has('admin.rides.index'))
          <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.rides.index') }}">Ver todas</a>
        @endif
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Pasajero</th>
                <th>Conductor</th>
                <th>Estatus</th>
                <th class="text-end">Monto</th>
                <th class="text-end">Fecha</th>
                <th class="text-end"></th>
              </tr>
            </thead>
            <tbody>
              @forelse($recent_rides ?? [] as $r)
                @php
                  $st = $r->status ?? 'unknown';
                  $badge = match($st) {
                    'requested' => 'bg-secondary',
                    'accepted'  => 'bg-info',
                    'en_route'  => 'bg-warning text-dark',
                    'arrived'   => 'bg-warning text-dark',
                    'on_board'  => 'bg-primary',
                    'finished'  => 'bg-success',
                    'canceled'  => 'bg-danger',
                    default     => 'bg-light text-dark',
                  };
                @endphp
                <tr>
                  <td class="text-muted">#{{ $r->id }}</td>
                  <td class="fw-semibold">{{ $r->passenger_name ?? 'N/A' }}</td>
                  <td>{{ $r->driver_name ?? '—' }}</td>
                  <td><span class="badge {{ $badge }}">{{ strtoupper($st) }}</span></td>
                  <td class="text-end">${{ number_format($r->total_amount ?? 0, 2) }}</td>
                  <td class="text-end text-muted">{{ \Carbon\Carbon::parse($r->created_at)->format('d M H:i') }}</td>
                  <td class="text-end">
                    {{-- No inventamos rutas: solo mostramos si existe --}}
                    @if(Route::has('admin.rides.show'))
                      <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.rides.show', $r->id) }}">
                        Ver
                      </a>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Aún no hay corridas</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  {{-- ACCIONES RÁPIDAS --}}
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0">
        <h5 class="card-title mb-0">Acciones rápidas</h5>
      </div>

      <div class="card-body">
        <div class="row g-3">

          <div class="col-md-3">
            @if(Route::has('admin.dispatch_settings.edit'))
              <a class="text-decoration-none" href="{{ route('admin.dispatch_settings.edit') }}">
            @else
              <a class="text-decoration-none" href="javascript:void(0)">
            @endif
                <div class="card shadow-sm border-0 h-100 action-card">
                  <div class="card-body text-center">
                    <div class="icon-wrap icon-lg bg-success-subtle text-success mx-auto mb-3">
                      <i class="fas fa-broadcast-tower"></i>
                    </div>
                    <div class="fw-semibold">Despacho</div>
                    <div class="text-muted small">Radio, olas, auto-assign</div>
                  </div>
                </div>
              </a>
          </div>

          <div class="col-md-3">
            @if(Route::has('admin.fare_policies.index'))
              <a class="text-decoration-none" href="{{ route('admin.fare_policies.index') }}">
            @else
              <a class="text-decoration-none" href="javascript:void(0)">
            @endif
                <div class="card shadow-sm border-0 h-100 action-card">
                  <div class="card-body text-center">
                    <div class="icon-wrap icon-lg bg-warning-subtle text-warning mx-auto mb-3">
                      <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="fw-semibold">Tarifas</div>
                    <div class="text-muted small">Base, por km/min, nocturno</div>
                  </div>
                </div>
              </a>
          </div>

          <div class="col-md-3">
            @if(Route::has('admin.drivers.index'))
              <a class="text-decoration-none" href="{{ route('admin.drivers.index') }}">
            @else
              <a class="text-decoration-none" href="javascript:void(0)">
            @endif
                <div class="card shadow-sm border-0 h-100 action-card">
                  <div class="card-body text-center">
                    <div class="icon-wrap icon-lg bg-info-subtle text-info mx-auto mb-3">
                      <i class="fas fa-users"></i>
                    </div>
                    <div class="fw-semibold">Conductores</div>
                    <div class="text-muted small">Gestionar flota activa</div>
                  </div>
                </div>
              </a>
          </div>

          <div class="col-md-3">
            @if(Route::has('admin.vehicles.index'))
              <a class="text-decoration-none" href="{{ route('admin.vehicles.index') }}">
            @else
              <a class="text-decoration-none" href="javascript:void(0)">
            @endif
                <div class="card shadow-sm border-0 h-100 action-card">
                  <div class="card-body text-center">
                    <div class="icon-wrap icon-lg bg-primary-subtle text-primary mx-auto mb-3">
                      <i class="fas fa-car"></i>
                    </div>
                    <div class="fw-semibold">Vehículos</div>
                    <div class="text-muted small">Altas, verificación y docs</div>
                  </div>
                </div>
              </a>
          </div>

        </div>
      </div>

    </div>
  </div>

</div>
@endsection

@push('styles')
<style>
  .kpi-card .kpi-value{
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.1;
    margin-top: .15rem;
  }
  .progress-sm { height: 6px; }

  .icon-wrap{
    width: 44px; height: 44px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
  }
  .icon-sm{ width: 34px; height: 34px; font-size: 14px; }
  .icon-lg{ width: 56px; height: 56px; font-size: 22px; }

  .chart-wrap{
    position: relative;
    width: 100%;
  }
  .chart-lg{ height: 300px; }
  .chart-md{ height: 260px; }

  .chart-wrap canvas{
    width: 100% !important;
    height: 100% !important;
  }

  .action-card{ transition: transform .15s ease, box-shadow .15s ease; }
  .action-card:hover{ transform: translateY(-2px); }

  .table td, .table th{ vertical-align: middle; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

  // Helper: labels legibles sin depender de parseo raro
  function fmtDateLabel(ymd){
    // ymd esperado: "YYYY-MM-DD"
    try{
      const [y,m,d] = (ymd || '').split('-').map(Number);
      if(!y || !m || !d) return ymd;
      const dt = new Date(y, m-1, d);
      return dt.toLocaleDateString(undefined, { weekday:'short', day:'2-digit', month:'short' });
    }catch(e){
      return ymd;
    }
  }

  // Tendencia (line)
  const ridesTrendEl = document.getElementById('ridesTrendChart');
  const ridesTrendData = @json($charts['rides_trend'] ?? []);

  if (ridesTrendEl) {
    new Chart(ridesTrendEl.getContext('2d'), {
      type: 'line',
      data: {
        labels: ridesTrendData.map(it => fmtDateLabel(it.date)),
        datasets: [{
          label: 'Corridas',
          data: ridesTrendData.map(it => it.count),
          borderWidth: 2,
          fill: true,
          tension: 0.35
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  // Status (doughnut)
  const ridesStatusEl = document.getElementById('ridesStatusChart');
  const ridesStatusData = @json($charts['rides_by_status'] ?? []);
  const statusLabels = Object.keys(ridesStatusData || {});
  const statusCounts = Object.values(ridesStatusData || {});

  if (ridesStatusEl) {
    new Chart(ridesStatusEl.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: statusLabels.map(s => String(s).toUpperCase()),
        datasets: [{ data: statusCounts, borderWidth: 1 }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: { legend: { position: 'bottom' } }
      }
    });
  }

  // Hours (bar)
  const hoursEl = document.getElementById('hoursDistributionChart');
  const hoursData = @json($charts['ride_hours_distribution'] ?? []);
  if (hoursEl) {
    const hoursLabels = Array.from({length:24}, (_,i)=>i);
    const counts = hoursLabels.map(h=>{
      const f = (hoursData || []).find(x => parseInt(x.hour) === h);
      return f ? f.count : 0;
    });

    new Chart(hoursEl.getContext('2d'), {
      type: 'bar',
      data: {
        labels: hoursLabels.map(h => String(h).padStart(2,'0')+':00'),
        datasets: [{ label:'Corridas', data: counts, borderWidth: 1 }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  // Payment (pie)
  const payEl = document.getElementById('paymentMethodsChart');
  const paymentData = @json($charts['payment_methods_distribution'] ?? []);
  if (payEl) {
    const map = { cash:'Efectivo', card:'Tarjeta', transfer:'Transferencia', corp:'Corporativo' };
    const labels = (paymentData || []).map(x => map[x.payment_method] || x.payment_method || '—');
    const counts = (paymentData || []).map(x => x.count);

    new Chart(payEl.getContext('2d'), {
      type: 'pie',
      data: { labels, datasets: [{ data: counts }] },
      options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } } }
    });
  }
});
</script>
@endpush
