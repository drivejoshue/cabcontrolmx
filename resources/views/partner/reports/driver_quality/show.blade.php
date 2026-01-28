{{-- resources/views/partner/reports/driver_quality/show.blade.php --}}
@extends('layouts.partner')

@section('title','Reportes · Calidad del conductor')

@push('head')
<style>
  /* Charts: contenedor fijo para evitar “infinito” */
  .chart-box{
    position: relative;
    height: 260px;
    min-height: 220px;
  }
  .chart-box.chart-sm{ height: 200px; min-height: 180px; }
  .chart-box.chart-lg{ height: 320px; min-height: 260px; }
  .chart-box canvas{
    width: 100% !important;
    height: 100% !important;
    display: block !important;
  }

  /* Chips pequeños */
  .kv-chip{
    display:inline-flex; align-items:center; gap:.35rem;
    padding:.1rem .5rem; border-radius:999px;
    border:1px solid rgba(255,255,255,.10);
    background: rgba(255,255,255,.04);
    font-size:.78rem;
  }
  [data-theme="light"] .kv-chip{
    border-color: rgba(15,23,42,.10);
    background: rgba(15,23,42,.03);
  }

  /* Estrellas */
  .stars{ letter-spacing:.06rem; white-space:nowrap; }
  .stars .on{ opacity:1; }
  .stars .off{ opacity:.25; }
</style>
@endpush

@section('content')
<div class="container-fluid">

  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Reportes</div>
        <h2 class="page-title">Calidad del conductor</h2>
        <div class="text-muted">
          <span class="fw-semibold">{{ $driver->name }}</span>
          · {{ $driver->phone ?? '—' }}
          @if(!empty($driver->email)) · {{ $driver->email }} @endif
          <span class="text-muted">· #{{ $driver->id }}</span>
        </div>
      </div>
      <div class="col-auto ms-auto d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('partner.reports.driver_quality.index', request()->query()) }}">
          <i class="ti ti-arrow-left me-1"></i> Volver
        </a>
      
      </div>
    </div>
  </div>

  {{-- Filtros superiores (persisten en tabs) --}}
  <div class="card mb-3">
    <div class="card-body">
      <form method="GET"
            action="{{ route('partner.reports.driver_quality.show', $driver->id) }}"
            class="row g-3 align-items-end">

        <input type="hidden" name="tab" value="{{ $tab ?? 'overview' }}">

        <div class="col-md-3">
          <label class="form-label">Desde</label>
          <input type="date" name="from" value="{{ $from ?? request('from') }}" class="form-control">
        </div>

        <div class="col-md-3">
          <label class="form-label">Hasta</label>
          <input type="date" name="to" value="{{ $to ?? request('to') }}" class="form-control">
        </div>

        <div class="col-md-2">
          <label class="form-label">Rating mínimo</label>
          <select name="min_rating" class="form-select">
            <option value="">Sin filtro</option>
            @for($i=1;$i<=5;$i++)
              <option value="{{ $i }}" {{ (string)($filters['min_rating'] ?? request('min_rating'))===(string)$i ? 'selected' : '' }}>
                {{ $i }}+
              </option>
            @endfor
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Issue: estado</label>
          <select name="issue_status" class="form-select">
            <option value="">Todos</option>
            @foreach(['open'=>'Abierto','in_review'=>'En revisión','resolved'=>'Resuelto','closed'=>'Cerrado'] as $k=>$label)
              <option value="{{ $k }}" {{ (string)($filters['issue_status'] ?? request('issue_status'))===$k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Severidad</label>
          <select name="severity" class="form-select">
            <option value="">Todas</option>
            @foreach(['low'=>'Baja','normal'=>'Normal','high'=>'Alta','critical'=>'Crítica'] as $k=>$label)
              <option value="{{ $k }}" {{ (string)($filters['severity'] ?? request('severity'))===$k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Categoría</label>
          <select name="category" class="form-select">
            <option value="">Todas</option>
            @foreach([
              'safety'=>'Seguridad','overcharge'=>'Cobro','route'=>'Ruta','driver_behavior'=>'Conductor',
              'passenger_behavior'=>'Pasajero','vehicle'=>'Vehículo','lost_item'=>'Objeto perdido',
              'payment'=>'Pago','app_problem'=>'App','other'=>'Otro'
            ] as $k=>$label)
              <option value="{{ $k }}" {{ (string)($filters['category'] ?? request('category'))===$k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Escalado</label>
          <select name="forward_to_platform" class="form-select">
            <option value="">Todos</option>
            <option value="1" {{ (string)($filters['forward_to_platform'] ?? request('forward_to_platform'))==='1' ? 'selected' : '' }}>Sí</option>
            <option value="0" {{ (string)($filters['forward_to_platform'] ?? request('forward_to_platform'))==='0' ? 'selected' : '' }}>No</option>
          </select>
        </div>

        <div class="col-md-3 d-flex gap-2">
          <button class="btn btn-primary w-100">
            <i class="ti ti-filter me-1"></i> Aplicar
          </button>
          <a class="btn btn-outline-secondary w-100"
             href="{{ route('partner.reports.driver_quality.show', $driver->id) . '?' . http_build_query(['tab'=>($tab ?? 'overview')]) }}">
            Limpiar
          </a>
        </div>
      </form>
    </div>
  </div>

  {{-- Tabs --}}
  <ul class="nav nav-tabs mb-3">
    @php
      $baseQs = request()->query();
      $mkUrl = function(string $t) use ($driver, $baseQs) {
        $qs = $baseQs;
        $qs['tab'] = $t;
        return route('partner.reports.driver_quality.show', $driver->id) . '?' . http_build_query($qs);
      };
      $tab = $tab ?? 'overview';
    @endphp

    <li class="nav-item">
      <a class="nav-link {{ $tab==='overview' ? 'active' : '' }}" href="{{ $mkUrl('overview') }}">Overview</a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab==='ratings' ? 'active' : '' }}" href="{{ $mkUrl('ratings') }}">Ratings</a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab==='issues' ? 'active' : '' }}" href="{{ $mkUrl('issues') }}">Issues</a>
    </li>
  </ul>

  {{-- OVERVIEW --}}
  @if($tab === 'overview')
    @php
      $rm = $ratingsMetrics ?? null;
      $im = $issuesMetrics ?? null;

      $ratingAvg = $rm && !empty($rm->rating_avg) ? (float)$rm->rating_avg : 0;
      $ratingAvgFmt = $ratingAvg > 0 ? number_format($ratingAvg,2) : '-';
      $ratingsCount = $rm ? (int)($rm->ratings_count ?? 0) : 0;

      $issuesCount = $im ? (int)($im->issues_count ?? 0) : 0;
      $openish = $im ? (int)($im->issues_openish ?? 0) : 0;
      $critical = $im ? (int)($im->sev_critical ?? 0) : 0;

      $avgResolve = $im && $im->avg_resolve_hours !== null ? number_format((float)$im->avg_resolve_hours, 1) : '-';

      $stars = (int) round($ratingAvg);
    @endphp

    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Rating promedio</div>
            <div class="d-flex align-items-baseline gap-2">
              <div class="fs-3 fw-bold">{{ $ratingAvgFmt }}</div>
              <div class="stars text-muted">
                @for($i=1;$i<=5;$i++)
                  <span class="{{ $i <= $stars ? 'on' : 'off' }}">★</span>
                @endfor
              </div>
            </div>
            <div class="text-muted small">Ratings: {{ $ratingsCount }}</div>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Issues (total)</div>
            <div class="fs-3 fw-bold">{{ $issuesCount }}</div>
            <div class="text-muted small">Abiertos / en revisión: {{ $openish }}</div>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Críticos</div>
            <div class="fs-3 fw-bold">{{ $critical }}</div>
            <div class="text-muted small">Severidad critical</div>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Prom. resolución (hrs)</div>
            <div class="fs-3 fw-bold">{{ $avgResolve }}</div>
            <div class="text-muted small">Solo issues resueltos</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">Ratings (promedio y volumen)</div>
          <div class="card-body">
           <div class="chart-box"><canvas id="chartRatingsDaily"></canvas></div>
<div class="text-muted small mt-2">Barras: cantidad diaria.</div>




          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">Issues (total y abiertos)</div>
          <div class="card-body">
            <div class="chart-box"><canvas id="chartIssuesDaily"></canvas></div>
            <div class="text-muted small mt-2">Total y abiertos/en revisión por día.</div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">Issues por categoría</div>
          <div class="card-body">
            <div class="chart-box chart-sm"><canvas id="chartIssuesCategory"></canvas></div>
            <div class="text-muted small mt-2">Distribución por tipo.</div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">Resumen rápido</div>
          <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
              <span class="kv-chip"><span class="text-muted">5★</span> <span class="fw-semibold">{{ (int)($rm->r5 ?? 0) }}</span></span>
              <span class="kv-chip"><span class="text-muted">4★</span> <span class="fw-semibold">{{ (int)($rm->r4 ?? 0) }}</span></span>
              <span class="kv-chip"><span class="text-muted">3★</span> <span class="fw-semibold">{{ (int)($rm->r3 ?? 0) }}</span></span>
              <span class="kv-chip"><span class="text-muted">2★</span> <span class="fw-semibold">{{ (int)($rm->r2 ?? 0) }}</span></span>
              <span class="kv-chip"><span class="text-muted">1★</span> <span class="fw-semibold">{{ (int)($rm->r1 ?? 0) }}</span></span>
            </div>

            <hr class="my-3">

            <div class="row g-3">
              <div class="col-6">
                <div class="text-muted small">Puntualidad</div>
                <div class="fw-bold">{{ $rm && $rm->punctuality_avg!==null ? number_format((float)$rm->punctuality_avg,2) : '-' }}</div>
              </div>
              <div class="col-6">
                <div class="text-muted small">Cortesía</div>
                <div class="fw-bold">{{ $rm && $rm->courtesy_avg!==null ? number_format((float)$rm->courtesy_avg,2) : '-' }}</div>
              </div>
              <div class="col-6">
                <div class="text-muted small">Vehículo</div>
                <div class="fw-bold">{{ $rm && $rm->vehicle_condition_avg!==null ? number_format((float)$rm->vehicle_condition_avg,2) : '-' }}</div>
              </div>
              <div class="col-6">
                <div class="text-muted small">Conducción</div>
                <div class="fw-bold">{{ $rm && $rm->driving_skills_avg!==null ? number_format((float)$rm->driving_skills_avg,2) : '-' }}</div>
              </div>
            </div>

            <div class="text-muted small mt-3">
              Este reporte considera lo filtrado por fecha + filtros. Ratings e issues normalmente pertenecen a rides finalizados/cancelados.
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif

  {{-- RATINGS --}}
  @if($tab === 'ratings')
    @php
      $rm = $ratingsMetrics ?? null;
      $avg = $rm && !empty($rm->rating_avg) ? number_format((float)$rm->rating_avg,2) : '-';
      $cnt = $rm ? (int)($rm->ratings_count ?? 0) : 0;
    @endphp

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Rating promedio</div>
            <div class="fs-3 fw-bold">{{ $avg }}</div>
            <div class="text-muted small">Ratings: {{ $cnt }}</div>
          </div>
        </div>
      </div>

      <div class="col-md-8">
        <div class="card">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-sm-3">
                <div class="text-muted small">Puntualidad</div>
                <div class="fw-bold">{{ $rm && $rm->punctuality_avg!==null ? number_format((float)$rm->punctuality_avg,2) : '-' }}</div>
              </div>
              <div class="col-sm-3">
                <div class="text-muted small">Cortesía</div>
                <div class="fw-bold">{{ $rm && $rm->courtesy_avg!==null ? number_format((float)$rm->courtesy_avg,2) : '-' }}</div>
              </div>
              <div class="col-sm-3">
                <div class="text-muted small">Vehículo</div>
                <div class="fw-bold">{{ $rm && $rm->vehicle_condition_avg!==null ? number_format((float)$rm->vehicle_condition_avg,2) : '-' }}</div>
              </div>
              <div class="col-sm-3">
                <div class="text-muted small">Conducción</div>
                <div class="fw-bold">{{ $rm && $rm->driving_skills_avg!==null ? number_format((float)$rm->driving_skills_avg,2) : '-' }}</div>
              </div>
            </div>

            <hr class="my-3">

            <div class="d-flex flex-wrap gap-2">
              <span class="kv-chip"><span class="text-muted">5★</span> <span class="fw-semibold">{{ (int)($rm->r5 ?? 0) }}</span></span>
              <span class="kv-chip"><span class="text-muted">4★</span> <span class="fw-semibold">{{ (int)($rm->r4 ?? 0) }}</span></span>
              <span class="kv-chip"><span class="text-muted">3★</span> <span class="fw-semibold">{{ (int)($rm->r3 ?? 0) }}</span></span>
              <span class="kv-chip"><span class="text-muted">2★</span> <span class="fw-semibold">{{ (int)($rm->r2 ?? 0) }}</span></span>
              <span class="kv-chip"><span class="text-muted">1★</span> <span class="fw-semibold">{{ (int)($rm->r1 ?? 0) }}</span></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>Ratings</div>
        <div class="text-muted small">Últimos primero</div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
          <thead>
            <tr>
              <th style="width:160px;">Fecha</th>
              <th style="width:120px;">Rating</th>
              <th>Comentario</th>
              <th class="text-muted">Submétricas</th>
              <th style="width:140px;"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($ratings as $ra)
              @php
                $dt = !empty($ra->created_at) ? \Carbon\Carbon::parse($ra->created_at)->format('Y-m-d H:i') : '-';
                $badge = match((int)$ra->rating) {
                  5 => 'bg-green-lt text-green',
                  4 => 'bg-teal-lt text-teal',
                  3 => 'bg-yellow-lt text-yellow',
                  2 => 'bg-orange-lt text-orange',
                  default => 'bg-red-lt text-red',
                };
                $rideId = $ra->ride_id ?? null;

                $starsN = (int)($ra->rating ?? 0);
              @endphp
              <tr>
                <td class="text-muted">{{ $dt }}</td>

                <td>
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge {{ $badge }}">{{ (int)$ra->rating }}</span>
                    <span class="stars text-muted">
                      @for($i=1;$i<=5;$i++)
                        <span class="{{ $i <= $starsN ? 'on' : 'off' }}">★</span>
                      @endfor
                    </span>
                  </div>
                </td>

                <td>{{ $ra->comment ?: '—' }}</td>

                <td class="text-muted small">
                  <span class="kv-chip">punctuality <span class="fw-semibold">{{ $ra->punctuality ?? '-' }}</span></span>
                  <span class="kv-chip">courtesy <span class="fw-semibold">{{ $ra->courtesy ?? '-' }}</span></span>
                  <span class="kv-chip">vehicle <span class="fw-semibold">{{ $ra->vehicle_condition ?? '-' }}</span></span>
                  <span class="kv-chip">driving <span class="fw-semibold">{{ $ra->driving_skills ?? '-' }}</span></span>
                </td>

                <td class="text-end">
                  @if($rideId)
                    <a class="btn btn-outline-secondary btn-sm"
                       href="{{ route('partner.reports.rides.show', $rideId) }}">
                      Ver viaje
                    </a>
                  @else
                    <span class="text-muted small">—</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-muted p-3">Sin ratings con los filtros actuales.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="card-footer">
        {{ $ratings->links() }}
      </div>
    </div>
  @endif

  {{-- ISSUES --}}
  @if($tab === 'issues')
    @php
      $im = $issuesMetrics ?? null;
      $issuesCount = $im ? (int)($im->issues_count ?? 0) : 0;
      $openish = $im ? (int)($im->issues_openish ?? 0) : 0;
      $critical = $im ? (int)($im->sev_critical ?? 0) : 0;
    @endphp

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Issues</div>
            <div class="fs-3 fw-bold">{{ $issuesCount }}</div>
            <div class="text-muted small">Abiertos/en revisión: {{ $openish }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Críticos</div>
            <div class="fs-3 fw-bold">{{ $critical }}</div>
            <div class="text-muted small">Requiere atención inmediata</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Nota</div>
            <div class="text-muted">
              Los issues se pueden registrar y dar seguimiento en el partner.
              Si se marca “escalado”, Orbana puede auditar el caso.
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>Issues</div>
        <div class="text-muted small">Últimos primero</div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
          <thead>
            <tr>
              <th style="width:160px;">Fecha</th>
              <th style="width:120px;">Estado</th>
              <th style="width:120px;">Severidad</th>
              <th style="width:140px;">Categoría</th>
              <th>Título</th>
              <th style="width:120px;">Escalado</th>
              <th style="width:140px;"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($issues as $ri)
              @php
                $dt = !empty($ri->created_at) ? \Carbon\Carbon::parse($ri->created_at)->format('Y-m-d H:i') : '-';

                $stBadge = match((string)$ri->status) {
                  'open'      => 'bg-red-lt text-red',
                  'in_review' => 'bg-yellow-lt text-yellow',
                  'resolved'  => 'bg-green-lt text-green',
                  default     => 'bg-secondary-lt text-secondary',
                };

                $sevBadge = match((string)$ri->severity) {
                  'critical' => 'bg-red-lt text-red',
                  'high'     => 'bg-orange-lt text-orange',
                  'normal'   => 'bg-secondary-lt text-secondary',
                  default    => 'bg-green-lt text-green',
                };

                $rideId = $ri->ride_id ?? null;
              @endphp

              <tr>
                <td class="text-muted">{{ $dt }}</td>

                <td><span class="badge {{ $stBadge }}">{{ $ri->status }}</span></td>
                <td><span class="badge {{ $sevBadge }}">{{ $ri->severity }}</span></td>

                <td class="text-muted">{{ $ri->category }}</td>

                <td>
                  <div class="fw-semibold">{{ $ri->title }}</div>
                  @if(!empty($ri->description))
                    <div class="text-muted small">{{ \Illuminate\Support\Str::limit($ri->description, 140) }}</div>
                  @endif
                </td>

                <td>
                  @if((int)$ri->forward_to_platform === 1)
                    <span class="badge bg-blue-lt text-blue">Sí</span>
                  @else
                    <span class="badge bg-secondary-lt text-secondary">No</span>
                  @endif
                </td>

                <td class="text-end">
                  @if($rideId)
                    <a class="btn btn-outline-secondary btn-sm"
                       href="{{ route('partner.reports.rides.show', $rideId) }}">
                      Ver viaje
                    </a>
                  @else
                    <span class="text-muted small">—</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-muted p-3">Sin issues con los filtros actuales.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="card-footer">
        {{ $issues->links() }}
      </div>
    </div>
  @endif

</div>
@endsection
@push('scripts')
<script>
(() => {
  const charts = {{ \Illuminate\Support\Js::from($charts ?? new \stdClass()) }};
  window.__pq_charts = charts;

  function hasChartJs(){ return !!window.Chart; }

  const __charts = [];

  function destroyAll(){
    while(__charts.length){
      const c = __charts.pop();
      try { c.destroy(); } catch(e) {}
    }
  }

  function isCanvasUsable(el){
    if (!el) return false;
    const box = el.closest('.chart-box');
    if (!box) return false;
    const rect = box.getBoundingClientRect();
    return rect.width > 10 && rect.height > 10;
  }

  function fmtShortDateLabel(ymd){
    // "YYYY-MM-DD" -> "DD/MM"
    if (!ymd || typeof ymd !== 'string') return '';
    const m = ymd.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!m) return ymd;
    const dd = m[3], mm = m[2];
    return `${dd}/${mm}`;
  }

  function baseOpts(){
    return {
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      plugins: {
        legend: { display: true },
        tooltip: { intersect: false, mode: 'index' }
      },
      interaction: { intersect: false, mode: 'index' },
      scales: {
        x: {
          ticks: {
            autoSkip: true,
            maxTicksLimit: 10,
            maxRotation: 0,
            callback: function(value){
              const label = this.getLabelForValue(value);
              return fmtShortDateLabel(label);
            }
          },
          grid: { drawTicks: false }
        }
      }
    };
  }

  // ✅ Ratings: barras (cantidad) + puntos (promedio) SIN LÍNEA
  function buildBarsWithAvgDots(ctx, labels, count, avg){
    // scatter necesita {x: label, y: value}
    const dots = [];
    for (let i = 0; i < labels.length; i++) {
      const v = avg?.[i];
      if (v === null || v === undefined) continue;
      dots.push({ x: labels[i], y: v });
    }

    const c = new Chart(ctx, {
      data: {
        labels,
        datasets: [
          {
            type: 'bar',
            label: 'Cantidad',
            data: count,
            yAxisID: 'y1'
          },
          {
            type: 'scatter',
            label: 'Rating promedio',
            data: dots,
            yAxisID: 'y',
            showLine: false,
            pointRadius: 3,
            pointHoverRadius: 4
          }
        ]
      },
      options: {
        ...baseOpts(),
        scales: {
          ...baseOpts().scales,
          y:  { position:'left', min: 0, max: 5, ticks: { stepSize: 1 } },
          y1: { position:'right', grid:{ drawOnChartArea:false }, beginAtZero:true, ticks:{ precision:0 } }
        }
      }
    });

    __charts.push(c);
  }
function buildRatingsDailyBars(ctx, labels, count){
  const c = new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Cantidad', data: count }] },
    options: {
      ...baseOpts(),
      scales: {
        ...baseOpts().scales,
        y: { beginAtZero:true, ticks:{ precision:0 } }
      }
    }
  });
  __charts.push(c);
}

  function buildIssuesDaily(ctx, labels, total, openish){
    const c = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Issues', data: total },
          { label: 'Abiertos/en revisión', data: openish }
        ]
      },
      options: {
        ...baseOpts(),
        scales: {
          ...baseOpts().scales,
          y: { beginAtZero:true, ticks: { precision: 0 } }
        }
      }
    });
    __charts.push(c);
  }

  function buildDonut(ctx, labels, data){
    const c = new Chart(ctx, {
      type: 'doughnut',
      data: { labels, datasets: [{ label: 'Issues', data }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
      }
    });
    __charts.push(c);
  }

  // ✅ Nuevo: distribución 1..5
  function buildRatingsDist(ctx, labels, count){
    const c = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{ label: 'Cantidad', data: count }]
      },
      options: {
        ...baseOpts(),
        scales: {
          ...baseOpts().scales,
          y: { beginAtZero:true, ticks:{ precision:0 } }
        }
      }
    });
    __charts.push(c);
  }

  function init(){
    if (!hasChartJs()) return;
    destroyAll();

    // Ratings diario (barras + puntos)
    const elR = document.getElementById('chartRatingsDaily');
    if (isCanvasUsable(elR) && charts?.ratings_daily) {
      const labels = charts.ratings_daily.labels || [];
      const avg    = charts.ratings_daily.avg || [];
      const count  = charts.ratings_daily.count || [];
      buildBarsWithAvgDots(elR.getContext('2d'), labels, count, avg);
    }

    // ✅ Distribución ratings (si existe canvas)
    const elRD = document.getElementById('chartRatingsDist');
    if (isCanvasUsable(elRD) && charts?.ratings_dist) {
      const labels = charts.ratings_dist.labels || [];
      const count  = charts.ratings_dist.count || [];
      buildRatingsDist(elRD.getContext('2d'), labels, count);
    }

    const elI = document.getElementById('chartIssuesDaily');
    if (isCanvasUsable(elI) && charts?.issues_daily) {
      const labels = charts.issues_daily.labels || [];
      const total  = charts.issues_daily.count || [];
      const openish= charts.issues_daily.openish || [];
      buildIssuesDaily(elI.getContext('2d'), labels, total, openish);
    }

    const elC = document.getElementById('chartIssuesCategory');
    if (isCanvasUsable(elC) && charts?.issues_by_category) {
      const labels = charts.issues_by_category.labels || [];
      const count  = charts.issues_by_category.count || [];
      if (labels.length && count.length) buildDonut(elC.getContext('2d'), labels, count);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    init();
    window.addEventListener('resize', () => init(), { passive:true });
  });
})();
</script>
@endpush
