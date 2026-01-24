@extends('layouts.partner')

@section('title', 'Reporte · Viajes')
@section('page-id','partner-reports-rides-index')

@php
  use Carbon\Carbon;

  $statusOptions = [
    '' => 'Finalizados + Cancelados',
    'finished' => 'Finalizados',
    'canceled' => 'Cancelados',
  ];

  $fromVal = $filters['from'] ?? Carbon::today()->subMonths(3)->toDateString();
  $toVal   = $filters['to'] ?? Carbon::today()->toDateString();
@endphp

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h1 class="h3 mb-0">Reporte de viajes</h1>
    <div class="text-muted small">Fuente: App de pasajero · Fecha: finalización/cancelación</div>
  </div>

  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="{{ route('partner.reports.rides.exportCsv', request()->query()) }}">
      <i class="ti ti-download me-1"></i> Exportar CSV
    </a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">

      <div class="col-md-2">
        <label class="form-label">Desde</label>
        <input type="date" class="form-control" name="from" value="{{ $fromVal }}">
      </div>

      <div class="col-md-2">
        <label class="form-label">Hasta</label>
        <input type="date" class="form-control" name="to" value="{{ $toVal }}">
      </div>

      <div class="col-md-2">
        <label class="form-label">Tipo</label>
        <select class="form-select" name="status">
          @foreach($statusOptions as $k => $lbl)
            <option value="{{ $k }}" @selected(request('status','')===$k)>{{ $lbl }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Conductor</label>
        <select class="form-select" name="driver_id">
          <option value="">Todos</option>
          @foreach($drivers as $d)
            <option value="{{ $d->id }}" @selected((string)request('driver_id','') === (string)$d->id)>{{ $d->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Vehículo</label>
        <select class="form-select" name="vehicle_id">
          <option value="">Todos</option>
          @foreach($vehicles as $v)
            <option value="{{ $v->id }}" @selected((string)request('vehicle_id','') === (string)$v->id)>
              {{ $v->economico }} · {{ $v->plate }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Buscar</label>
        <input class="form-control" name="q" value="{{ request('q','') }}" placeholder="Pasajero, teléfono, origen, destino, notas">
      </div>

      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit">
          <i class="ti ti-filter me-1"></i> Filtrar
        </button>
        <a class="btn btn-outline-secondary w-100" href="{{ route('partner.reports.rides.index') }}">
          Limpiar
        </a>
      </div>

    </form>
  </div>
</div>

@php
  $km = $stats->distance_m_sum ? ($stats->distance_m_sum / 1000) : 0;
@endphp

<div class="row row-cards mb-3">
  <div class="col-md-3">
    <div class="card"><div class="card-body">
      <div class="subheader">Viajes (total)</div>
      <div class="h2 mb-0">{{ (int)$stats->total }}</div>
      <div class="mt-2">
        <span class="badge bg-success-lt text-success">Finalizados: {{ (int)$stats->finished }}</span>
        <span class="badge bg-danger-lt text-danger ms-1">Cancelados: {{ (int)$stats->canceled }}</span>
      </div>
    </div></div>
  </div>

  <div class="col-md-3">
    <div class="card"><div class="card-body">
      <div class="subheader">Ingresos (finalizados)</div>
      <div class="h2 mb-0">{{ number_format((float)$stats->amount_sum, 2) }} <span class="text-muted">MXN</span></div>
      <div class="text-muted small">Solo viajes finalizados</div>
    </div></div>
  </div>

  <div class="col-md-3">
    <div class="card"><div class="card-body">
      <div class="subheader">KM (finalizados)</div>
      <div class="h2 mb-0">{{ number_format($km, 1) }}</div>
      <div class="text-muted small">Ruta guardada en el ride</div>
    </div></div>
  </div>

  <div class="col-md-3">
    <div class="card"><div class="card-body">
      <div class="subheader">Tiempo (finalizados)</div>
      <div class="h2 mb-0">{{ $stats->duration_s_sum ? number_format((int)round($stats->duration_s_sum/60)) : 0 }} <span class="text-muted">min</span></div>
      <div class="text-muted small">Suma aproximada</div>
    </div></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">
    <h5 class="card-title mb-0">Actividad diaria</h5>
    <div class="card-actions">
      <span class="text-muted small">Finalizados vs Cancelados · Ingresos</span>
    </div>
  </div>
  <div class="card-body">
    @if(empty($chart['labels']))
      <div class="text-muted">Sin datos para graficar en el rango seleccionado.</div>
    @else
      <div style="height: 320px;"><canvas id="ridesDailyChart"></canvas></div>
    @endif
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h5 class="card-title mb-0">Viajes</h5>
  </div>

  <div class="table-responsive">
    <table class="table table-vcenter mb-0">
      <thead>
      <tr>
        <th>ID</th>
        <th>Estado</th>
        <th>Fecha final</th>
        <th>Conductor</th>
        <th>Vehículo</th>
        <th>Pasajero</th>
        <th>Monto</th>
        <th>Origen</th>
        <th>Destino</th>
        <th class="text-end">Acciones</th>
      </tr>
      </thead>
      <tbody>
      @forelse($rides as $r)
        @php
          $finalAt = $r->finished_at ?? $r->canceled_at ?? $r->requested_at ?? $r->created_at;
          $amount  = (float)($r->agreed_amount ?? $r->total_amount ?? $r->quoted_amount ?? 0);

          $badge = match($r->status) {
            'finished' => 'bg-success-lt text-success',
            'canceled' => 'bg-danger-lt text-danger',
            default => 'bg-secondary-lt text-secondary',
          };

          $veh = trim(($r->vehicle_economico ?? '').' · '.($r->vehicle_plate ?? ''));
          if ($veh === '·') $veh = '';
          $veh = $veh ?: ($r->vehicle_id ? ('#'.$r->vehicle_id) : '—');
        @endphp
        <tr>
          <td class="text-muted">#{{ $r->id }}</td>
          <td><span class="badge {{ $badge }}">{{ $r->status === 'finished' ? 'Finalizado' : 'Cancelado' }}</span></td>
          <td class="text-muted small">{{ $finalAt }}</td>
          <td>{{ $r->driver_id ? $r->driver_name : '—' }}</td>
          <td class="text-muted">{{ $veh }}</td>
          <td class="text-muted">
            {{ $r->passenger_name ?? '—' }}
            @if(!empty($r->passenger_phone)) <span class="text-muted">· {{ $r->passenger_phone }}</span> @endif
          </td>
          <td>{{ number_format($amount, 2) }} <span class="text-muted">{{ $r->currency ?? 'MXN' }}</span></td>
          <td class="text-truncate" style="max-width:220px;">{{ $r->origin_label ?? '—' }}</td>
          <td class="text-truncate" style="max-width:220px;">{{ $r->dest_label ?? '—' }}</td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('partner.reports.rides.show', $r->id) }}">Ver</a>
          </td>
        </tr>
      @empty
        <tr><td colspan="10" class="text-muted">Sin viajes.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer">
    {{ $rides->links() }}
  </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const labels = @json($chart['labels'] ?? []);
  if (!labels.length) return;

  const finished = @json($chart['rides_finished'] ?? []);
  const canceled = @json($chart['rides_canceled'] ?? []);
  const amount   = @json($chart['amount_finished'] ?? []);

  const isDark =
    document.body.classList.contains('theme-dark') ||
    document.documentElement.getAttribute('data-bs-theme') === 'dark';

  const palette = isDark ? {
    text: '#E5E7EB',
    grid: 'rgba(148, 163, 184, 0.18)',
    barFinished: 'rgba(52, 211, 153, 0.30)',
    barCanceled: 'rgba(251, 113, 133, 0.30)',
    lineAmount: '#60A5FA',
    lineFill: 'rgba(96, 165, 250, 0.12)',
    borderSoft: 'rgba(148, 163, 184, 0.25)',
  } : {
    text: '#0F172A',
    grid: '#E2E8F0',
    barFinished: 'rgba(16, 185, 129, 0.35)',
    barCanceled: 'rgba(244, 63, 94, 0.35)',
    lineAmount: '#2563EB',
    lineFill: 'rgba(37, 99, 235, 0.12)',
    borderSoft: '#CBD5E1',
  };

  const ctx = document.getElementById('ridesDailyChart');
  if (!ctx) return;

  new Chart(ctx, {
    data: {
      labels,
      datasets: [
        {
          type: 'bar',
          label: 'Finalizados',
          data: finished,
          backgroundColor: palette.barFinished,
          borderColor: palette.borderSoft,
          borderWidth: 1,
          borderRadius: 6,
          yAxisID: 'yRides',
          barPercentage: 0.9,
          categoryPercentage: 0.7,
        },
        {
          type: 'bar',
          label: 'Cancelados',
          data: canceled,
          backgroundColor: palette.barCanceled,
          borderColor: palette.borderSoft,
          borderWidth: 1,
          borderRadius: 6,
          yAxisID: 'yRides',
          barPercentage: 0.9,
          categoryPercentage: 0.7,
        },
        {
          type: 'line',
          label: 'Ingresos (MXN)',
          data: amount,
          borderColor: palette.lineAmount,
          backgroundColor: palette.lineFill,
          fill: true,
          pointRadius: 2,
          pointHoverRadius: 3,
          borderWidth: 2,
          tension: 0.25,
          yAxisID: 'yMoney',
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          labels: {
            color: palette.text,
            font: { weight: '600' } // texto fuerte
          }
        },
        tooltip: {
          titleColor: palette.text,
          bodyColor: palette.text
        }
      },
      scales: {
        x: {
          ticks: { color: palette.text, font: { weight: '600' } },
          grid: { color: palette.grid }
        },
        yRides: {
          position: 'left',
          beginAtZero: true,
          ticks: { color: palette.text, font: { weight: '600' }, precision: 0 },
          grid: { color: palette.grid }
        },
        yMoney: {
          position: 'right',
          beginAtZero: true,
          ticks: { color: palette.text, font: { weight: '600' } },
          grid: { drawOnChartArea: false }
        }
      }
    }
  });
});
</script>

@endpush

@endsection
