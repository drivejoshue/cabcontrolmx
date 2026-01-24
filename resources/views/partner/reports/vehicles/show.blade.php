@extends('layouts.partner')

@section('title', 'Reporte · Vehículo')
@section('page-id','partner-reports-vehicle-show')

@php
 

  $dateFieldOptions = [
    'requested_at' => 'requested_at',
    'created_at' => 'created_at',
    'accepted_at' => 'accepted_at',
    'finished_at' => 'finished_at',
    'canceled_at' => 'canceled_at',
  ];
@endphp

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h1 class="h3 mb-0">Vehículo #{{ $vehicle->id }} · {{ $vehicle->economico ?? '—' }}</h1>
    <div class="text-muted small">{{ $vehicle->plate ?? '—' }} {{ $vehicle->brand ? '· '.$vehicle->brand : '' }} {{ $vehicle->model ? '· '.$vehicle->model : '' }}</div>
  </div>

  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="{{ route('partner.reports.vehicles.index') }}">
      <i class="ti ti-arrow-left me-1"></i> Volver
    </a>
  </div>
</div>

@php
  $statusOptions = [
    'finished' => 'Finalizados',
    'canceled' => 'Cancelados',
    ''         => 'Finalizados + Cancelados',
  ];
@endphp

<div class="card mb-3">
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">

      <div class="col-md-3">
        <label class="form-label">Desde</label>
        <input type="date" class="form-control" name="from" value="{{ $filters['from'] ?? request('from') }}">
      </div>

      <div class="col-md-3">
        <label class="form-label">Hasta</label>
        <input type="date" class="form-control" name="to" value="{{ $filters['to'] ?? request('to') }}">
      </div>

      <div class="col-md-3">
        <label class="form-label">Estado</label>
        <select class="form-select" name="status">
          @foreach($statusOptions as $k => $lbl)
            <option value="{{ $k }}" @selected(($filters['status'] ?? request('status','finished'))===$k)>{{ $lbl }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit">
          <i class="ti ti-filter me-1"></i> Filtrar
        </button>
        <a class="btn btn-outline-secondary w-100" href="{{ route('partner.reports.vehicles.show', $vehicle->id) }}">
          Restablecer
        </a>
      </div>

    </form>
  </div>
</div>

@php
  $km = $metrics?->distance_m_sum ? ($metrics->distance_m_sum / 1000) : 0;

  $fmtSec = function($s) {
    if ($s === null) return '—';
    $s = (int) round($s);
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    $ss = $s % 60;
    return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $ss) : sprintf('%d:%02d', $m, $ss);
  };
@endphp

<div class="row row-cards mb-3">
  <div class="col-md-3">
    <div class="card">
      <div class="card-body">
        <div class="subheader">Rides</div>
        <div class="h2 mb-0">{{ (int)($metrics->rides_total ?? 0) }}</div>
        <div class="mt-2">
          <span class="badge bg-success-lt text-success">Fin: {{ (int)($metrics->rides_finished ?? 0) }}</span>
          <span class="badge bg-danger-lt text-danger ms-1">Canc: {{ (int)($metrics->rides_canceled ?? 0) }}</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card">
      <div class="card-body">
        <div class="subheader">KM recorridos (finished)</div>
        <div class="h2 mb-0">{{ number_format($km, 1) }}</div>
        <div class="text-muted small">Prom. distancia: {{ $metrics->rides_finished ? number_format(($metrics->distance_m_sum/1000)/max(1,$metrics->rides_finished), 2) : '—' }} km</div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card">
      <div class="card-body">
        <div class="subheader">ETA a arribo (prom)</div>
        <div class="h2 mb-0">{{ $fmtSec($metrics->avg_pickup_eta_s ?? null) }}</div>
        <div class="text-muted small">Aceptado → Arrived</div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card">
      <div class="card-body">
        <div class="subheader">Tiempo viaje (prom)</div>
        <div class="h2 mb-0">{{ $fmtSec($metrics->avg_trip_s ?? null) }}</div>
        <div class="text-muted small">Onboard → Finished</div>
      </div>
    </div>
  </div>
</div>

<div class="row row-cards mb-3">
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <div class="subheader">Busy (suma)</div>
        <div class="h2 mb-0">{{ $fmtSec($metrics->busy_s_sum ?? null) }}</div>
        <div class="text-muted small">Aceptado → Finished (aprox)</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <div class="subheader">Ingresos (finished)</div>
        <div class="h2 mb-0">{{ number_format((float)($metrics->amount_sum ?? 0), 2) }} <span class="text-muted">MXN</span></div>
        <div class="text-muted small">Agreed/Total/Quoted</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <div class="subheader">Ventana actividad (aprox)</div>
        <div class="text-muted small">Inicio: {{ $metrics->first_activity_at ?? '—' }}</div>
        <div class="text-muted small">Fin: {{ $metrics->last_activity_at ?? '—' }}</div>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">
    <h5 class="card-title mb-0">Rendimiento por chofer (este vehículo)</h5>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter mb-0">
      <thead>
        <tr>
          <th>Chofer</th>
          <th class="text-end">Rides</th>
          <th class="text-end">Finished</th>
          <th class="text-end">KM</th>
          <th class="text-end">Ingreso</th>
          <th class="text-end">ETA arribo</th>
          <th class="text-end">Tiempo viaje</th>
        </tr>
      </thead>
      <tbody>
        @forelse($byDriver as $d)
          @php
            $dkm = ($d->distance_m_sum ?? 0) / 1000;
          @endphp
          <tr>
            <td>{{ $d->driver_name }}</td>
            <td class="text-end">{{ (int)$d->rides_total }}</td>
            <td class="text-end">{{ (int)$d->rides_finished }}</td>
            <td class="text-end">{{ number_format($dkm, 1) }}</td>
            <td class="text-end">{{ number_format((float)$d->amount_sum, 2) }}</td>
            <td class="text-end">{{ $fmtSec($d->avg_pickup_eta_s) }}</td>
            <td class="text-end">{{ $fmtSec($d->avg_trip_s) }}</td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-muted">Sin datos todavía.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h5 class="card-title mb-0">Actividad diaria</h5>
    <div class="card-actions">
      <span class="text-muted small">
        Periodo: {{ $filters['from'] ?? '' }} → {{ $filters['to'] ?? '' }}
      </span>
    </div>
  </div>

  <div class="card-body">
    <div style="height: 320px;">
      <canvas id="vehicleDailyChart"></canvas>
    </div>
    <div class="text-muted small mt-2">
      Nota: La línea de KM considera solo viajes finalizados.
    </div>
  </div>
</div>

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
 <script>
document.addEventListener('DOMContentLoaded', function () {
  const labels = @json($chart['labels'] ?? []);
  if (!labels.length) return;

  const fin = @json($chart['finished'] ?? []);   // viajes finalizados
  const can = @json($chart['canceled'] ?? []);   // viajes cancelados
  const km  = @json($chart['km'] ?? []);         // km (finalizados)

  const css = getComputedStyle(document.documentElement);

  const pick = (name, fallback) => (css.getPropertyValue(name) || fallback).trim();

  // Colores base Tabler
  const theme = (document.documentElement.getAttribute('data-bs-theme') || '').toLowerCase();
const isLight = theme === 'light';

// En light forzamos texto más oscuro; en dark usamos el var de Tabler
const cText = isLight ? '#0f172a' : pick('--tblr-body-color', '#e5e7eb');
  const cBorder = pick('--tblr-border-color', 'rgba(148,163,184,0.35)');

  const cSuccess = pick('--tblr-success', '#2fb344'); // finalizados
  const cDanger  = pick('--tblr-danger',  '#d63939'); // cancelados
  const cPrimary = pick('--tblr-primary', '#206bc4'); // línea KM

  // Helpers: hex/rgb -> rgba suave
  const hexToRgba = (hex, a) => {
    const h = hex.replace('#', '').trim();
    if (h.length !== 6) return hex;
    const r = parseInt(h.substring(0, 2), 16);
    const g = parseInt(h.substring(2, 4), 16);
    const b = parseInt(h.substring(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${a})`;
  };

  const toRgba = (c, a) => {
    c = (c || '').trim();
    if (c.startsWith('#')) return hexToRgba(c, a);
    if (c.startsWith('rgb(')) return c.replace('rgb(', 'rgba(').replace(')', `, ${a})`);
    if (c.startsWith('rgba(')) return c; // ya viene con alpha
    return c;
  };

  // Estilo “elegante y suave”
  const gridColor = toRgba(cBorder, 0.18);       // rejilla suave
  const axisColor = toRgba(cBorder, 0.28);       // borde ejes suave
  const textColor = cText;                       // texto fuerte
  const mutedText = isLight ? 'rgba(15, 23, 42, 0.81)' : toRgba(cText, 0.70);


  const barFinBg = toRgba(cSuccess, 0.28);
  const barFinBd = toRgba(cSuccess, 0.85);

  const barCanBg = toRgba(cDanger, 0.22);
  const barCanBd = toRgba(cDanger, 0.85);

  const lineKm   = toRgba(cPrimary, 0.95);
  const lineFill = toRgba(cPrimary, 0.08);

  const ctx = document.getElementById('vehicleDailyChart');
  if (!ctx) return;

  new Chart(ctx, {
    data: {
      labels,
      datasets: [
        {
          type: 'bar',
          label: 'Viajes finalizados',
          data: fin,
          yAxisID: 'yRides',
          backgroundColor: barFinBg,
          borderColor: barFinBd,
          borderWidth: 1,
          borderRadius: 8,
          maxBarThickness: 18,
          barPercentage: 0.9,
          categoryPercentage: 0.8,
        },
        {
          type: 'bar',
          label: 'Viajes cancelados',
          data: can,
          yAxisID: 'yRides',
          backgroundColor: barCanBg,
          borderColor: barCanBd,
          borderWidth: 1,
          borderRadius: 8,
          maxBarThickness: 18,
          barPercentage: 0.9,
          categoryPercentage: 0.8,
        },
        {
          type: 'line',
          label: 'KM (finalizados)',
          data: km,
          yAxisID: 'yKm',
          borderColor: lineKm,
          backgroundColor: lineFill,
          fill: true,
          pointRadius: 2,
          pointHoverRadius: 3,
          pointBorderWidth: 0,
          tension: 0.28,
        },
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          labels: {
            color: textColor,
            font: { weight: '600' }
          }
        },
        tooltip: {
          mode: 'index',
          intersect: false,
          titleColor: textColor,
          bodyColor: textColor,
          footerColor: mutedText,
          backgroundColor: toRgba('#0b1220', 0.88),
          borderColor: toRgba(cBorder, 0.35),
          borderWidth: 1
        }
      },
      interaction: { mode: 'index', intersect: false },
      scales: {
        x: {
          ticks: {
            color: mutedText,
            font: { weight: '600' },
            maxRotation: 55,
            minRotation: 55
          },
          grid: { color: gridColor },
          border: { color: axisColor }
        },
        yRides: {
          position: 'left',
          beginAtZero: true,
          ticks: { color: mutedText, font: { weight: '600' } },
          grid: { color: gridColor },
          border: { color: axisColor }
        },
        yKm: {
          position: 'right',
          beginAtZero: true,
          ticks: { color: mutedText, font: { weight: '600' } },
          grid: { drawOnChartArea: false },
          border: { color: axisColor }
        }
      }
    }
  });
});
</script>

@endpush


@endsection
