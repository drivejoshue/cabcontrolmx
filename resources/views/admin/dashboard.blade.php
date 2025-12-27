@extends('layouts.admin')

@section('title','Dashboard')
@section('page-id','dashboard')

@section('content')
<div class="row row-cards">

  {{-- HEADER --}}
  <div class="col-12">
    <div class="card">
      <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
        <div>
          <h3 class="card-title mb-1">Dashboard de Operaciones</h3>
          <div class="text-muted">Monitorea el rendimiento de tu flota en tiempo real.</div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-md-end">
          <div class="text-end small text-muted me-2">
            <div>{{ now()->format('d M Y H:i') }}</div>
            <div>Tenant: <strong>{{ auth()->user()->tenant_id ?? '-' }}</strong></div>
          </div>

          @if(Route::has('admin.dispatch.index'))
            <a class="btn btn-primary btn-sm" href="{{ route('admin.dispatch.index') }}">
              <i class="ti ti-broadcast me-1"></i> Abrir despacho
            </a>
          @endif

          @if(Route::has('admin.tenant.edit'))
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.tenant.edit') }}">
              <i class="ti ti-settings me-1"></i> Mi central
            </a>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- KPIs --}}
  <div class="col-12 col-md-6 col-xl-3">
    <div class="card h-100 kpi-card">
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
          <span class="avatar bg-blue-lt text-blue">
            <i class="ti ti-taxi"></i>
          </span>
        </div>

        <div class="mt-3">
          <div class="progress progress-sm">
            <div class="progress-bar" style="width: {{ min(($metrics['completion_rate'] ?? 0), 100) }}%"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6 col-xl-3">
    <div class="card h-100 kpi-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted fw-semibold">Conductores activos</div>
            <div class="kpi-value">{{ $metrics['active_drivers'] ?? 0 }}</div>
            <div class="small text-muted mt-1">
              <i class="ti ti-star text-yellow me-1"></i>
              Rating promedio: <strong>{{ $metrics['average_rating'] ?? 0 }}/5</strong>
            </div>
          </div>
          <span class="avatar bg-green-lt text-green">
            <i class="ti ti-user-check"></i>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6 col-xl-3">
    <div class="card h-100 kpi-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted fw-semibold">Vehículos verificados</div>
            <div class="kpi-value">{{ $metrics['total_vehicles'] ?? 0 }}</div>
            <div class="small text-muted mt-1">
              {{ $metrics['total_passengers'] ?? 0 }} pasajeros registrados
            </div>
          </div>
          <span class="avatar bg-azure-lt text-azure">
            <i class="ti ti-car"></i>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6 col-xl-3">
    <div class="card h-100 kpi-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted fw-semibold">Ingresos hoy</div>
            <div class="kpi-value">${{ number_format($metrics['total_revenue_today'] ?? 0, 2) }}</div>
            <div class="small text-muted mt-1">
              <i class="ti ti-trending-up text-green me-1"></i> Ventana 30 días
            </div>
          </div>
          <span class="avatar bg-yellow-lt text-yellow">
            <i class="ti ti-cash"></i>
          </span>
        </div>
      </div>
    </div>
  </div>

  {{-- CHARTS --}}
  <div class="col-12 col-xl-8">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title mb-0">Tendencia de corridas (7 días)</h3>
        <span class="badge bg-secondary-lt ms-auto">Actualizado</span>
      </div>
      <div class="card-body">
        <div class="chart-wrap chart-lg">
          <canvas id="ridesTrendChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-4">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title mb-0">Estado actual</h3>
      </div>
      <div class="card-body">
        <div class="chart-wrap chart-lg">
          <canvas id="ridesStatusChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-6">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title mb-0">Distribución por horas (hoy)</h3>
      </div>
      <div class="card-body">
        <div class="chart-wrap chart-md">
          <canvas id="hoursDistributionChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-6">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title mb-0">Métodos de pago (30 días)</h3>
      </div>
      <div class="card-body">
        <div class="chart-wrap chart-md">
          <canvas id="paymentMethodsChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  {{-- TOP CONDUCTORES --}}
  <div class="col-12 col-xl-6">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title mb-0">Top conductores (30 días)</h3>
        @if(Route::has('admin.drivers.index'))
          <a href="{{ route('admin.drivers.index') }}" class="btn btn-sm btn-outline-primary ms-auto">
            Ver todos
          </a>
        @endif
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-vcenter mb-0">
            <thead>
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
                      <span class="avatar avatar-sm bg-blue-lt text-blue">
                        <i class="ti ti-user"></i>
                      </span>
                      <div class="fw-semibold">{{ $driver->name }}</div>
                    </div>
                  </td>
                  <td class="text-end">{{ $driver->total_rides }}</td>
                  <td class="text-end">${{ number_format($driver->total_revenue ?? 0, 2) }}</td>
                  <td class="text-end">
                    <span class="badge bg-yellow-lt text-yellow">
                      {{ number_format($driver->avg_rating ?? 0, 1) }}
                      <i class="ti ti-star ms-1"></i>
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

  {{-- PROGRAMADAS --}}
  <div class="col-12 col-xl-6">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title mb-0">Programadas próximas</h3>
        <span class="badge bg-secondary-lt ms-auto">Opcional</span>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-vcenter mb-0">
            <thead>
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
                    <span class="badge bg-azure-lt text-azure">
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
    <div class="card">
      <div class="card-header">
        <h3 class="card-title mb-0">Últimas corridas</h3>

        @if(Route::has('admin.rides.index'))
          <a class="btn btn-sm btn-outline-primary ms-auto" href="{{ route('admin.rides.index') }}">Ver todas</a>
        @endif
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-vcenter mb-0">
            <thead>
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
                    'requested' => 'bg-secondary-lt text-secondary',
                    'accepted'  => 'bg-azure-lt text-azure',
                    'en_route'  => 'bg-yellow-lt text-yellow',
                    'arrived'   => 'bg-yellow-lt text-yellow',
                    'on_board'  => 'bg-blue-lt text-blue',
                    'finished'  => 'bg-green-lt text-green',
                    'canceled'  => 'bg-red-lt text-red',
                    default     => 'bg-secondary-lt text-secondary',
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
    <div class="card">
      <div class="card-header">
        <h3 class="card-title mb-0">Acciones rápidas</h3>
      </div>

      <div class="card-body">
        <div class="row row-cards">

          <div class="col-12 col-md-6 col-lg-3">
            <a class="card card-link" href="{{ Route::has('admin.dispatch_settings.edit') ? route('admin.dispatch_settings.edit') : 'javascript:void(0)' }}">
              <div class="card-body text-center">
                <span class="avatar avatar-lg bg-green-lt text-green mb-3"><i class="ti ti-adjustments"></i></span>
                <div class="fw-semibold">Despacho</div>
                <div class="text-muted small">Radio, olas, auto-assign</div>
              </div>
            </a>
          </div>

          <div class="col-12 col-md-6 col-lg-3">
            <a class="card card-link" href="{{ Route::has('admin.fare_policies.index') ? route('admin.fare_policies.index') : 'javascript:void(0)' }}">
              <div class="card-body text-center">
                <span class="avatar avatar-lg bg-yellow-lt text-yellow mb-3"><i class="ti ti-cash"></i></span>
                <div class="fw-semibold">Tarifas</div>
                <div class="text-muted small">Base, por km/min, nocturno</div>
              </div>
            </a>
          </div>

          <div class="col-12 col-md-6 col-lg-3">
            <a class="card card-link" href="{{ Route::has('admin.drivers.index') ? route('admin.drivers.index') : 'javascript:void(0)' }}">
              <div class="card-body text-center">
                <span class="avatar avatar-lg bg-azure-lt text-azure mb-3"><i class="ti ti-users"></i></span>
                <div class="fw-semibold">Conductores</div>
                <div class="text-muted small">Gestionar flota activa</div>
              </div>
            </a>
          </div>

          <div class="col-12 col-md-6 col-lg-3">
            <a class="card card-link" href="{{ Route::has('admin.vehicles.index') ? route('admin.vehicles.index') : 'javascript:void(0)' }}">
              <div class="card-body text-center">
                <span class="avatar avatar-lg bg-blue-lt text-blue mb-3"><i class="ti ti-car"></i></span>
                <div class="fw-semibold">Vehículos</div>
                <div class="text-muted small">Altas, verificación y docs</div>
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

  .chart-wrap{ position: relative; width: 100%; }
  .chart-lg{ height: 300px; }
  .chart-md{ height: 260px; }
  .chart-wrap canvas{ width: 100% !important; height: 100% !important; }

  .table td, .table th{ vertical-align: middle; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

  function fmtDateLabel(ymd){
    try{
      const [y,m,d] = (ymd || '').split('-').map(Number);
      if(!y || !m || !d) return ymd;
      const dt = new Date(y, m-1, d);
      return dt.toLocaleDateString(undefined, { weekday:'short', day:'2-digit', month:'short' });
    }catch(e){
      return ymd;
    }
  }

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
