{{-- resources/views/partner/reports/driver_quality/index.blade.php --}}
@extends('layouts.partner')

@section('title','Reportes · Calidad de conductores')

@section('content')
<div class="container-fluid">

  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Reportes</div>
        <h2 class="page-title">Calidad de conductores</h2>
        <div class="text-muted">Ratings e incidencias en rides finalizados/cancelados (flota del partner).</div>
      </div>

      <div class="col-auto ms-auto d-flex gap-2">
        <a class="btn btn-outline-secondary"
           href="{{ route('partner.reports.driver_quality.export', request()->query() + ['type' => 'both']) }}">
          <i class="ti ti-download me-1"></i> Exportar CSV
        </a>
      </div>
    </div>
  </div>

  {{-- Filtros --}}
  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('partner.reports.driver_quality.index') }}" class="row g-3 align-items-end">

        <div class="col-md-3">
          <label class="form-label">Desde</label>
          <input type="date" name="from" value="{{ $from ?? request('from') }}" class="form-control">
        </div>

        <div class="col-md-3">
          <label class="form-label">Hasta</label>
          <input type="date" name="to" value="{{ $to ?? request('to') }}" class="form-control">
        </div>

        <div class="col-md-3">
          <label class="form-label">Conductor</label>
          <select name="driver_id" class="form-select">
            <option value="">Todos</option>
            @foreach(($driversList ?? collect()) as $x)
              <option value="{{ $x->id }}" {{ (string)request('driver_id')===(string)$x->id ? 'selected' : '' }}>
                #{{ $x->id }} · {{ $x->name }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Buscar</label>
          <input type="text" name="q" value="{{ request('q') }}" class="form-control"
                 placeholder="Nombre, teléfono, email">
        </div>

        <div class="col-md-3">
          <label class="form-label">Solo mostrar</label>
          <select name="only_with" class="form-select">
            <option value="">Todos</option>
            <option value="any"     {{ request('only_with')==='any' ? 'selected' : '' }}>Con ratings o issues</option>
            <option value="ratings" {{ request('only_with')==='ratings' ? 'selected' : '' }}>Solo con ratings</option>
            <option value="issues"  {{ request('only_with')==='issues' ? 'selected' : '' }}>Solo con issues</option>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Rating mínimo</label>
          <select name="min_rating" class="form-select">
            <option value="">Sin filtro</option>
            @for($i=1;$i<=5;$i++)
              <option value="{{ $i }}" {{ (string)request('min_rating')===(string)$i ? 'selected' : '' }}>
                {{ $i }}+
              </option>
            @endfor
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Issue: estado</label>
          <select name="issue_status" class="form-select">
            <option value="">Todos</option>
            @foreach(['open'=>'Abierto','in_review'=>'En revisión','resolved'=>'Resuelto','closed'=>'Cerrado'] as $k=>$label)
              <option value="{{ $k }}" {{ request('issue_status')===$k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Issue: severidad</label>
          <select name="severity" class="form-select">
            <option value="">Todas</option>
            @foreach(['low'=>'Baja','normal'=>'Normal','high'=>'Alta','critical'=>'Crítica'] as $k=>$label)
              <option value="{{ $k }}" {{ request('severity')===$k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Issue: categoría</label>
          <select name="category" class="form-select">
            <option value="">Todas</option>
            @foreach([
              'safety'=>'Seguridad','overcharge'=>'Cobro','route'=>'Ruta','driver_behavior'=>'Conductor',
              'passenger_behavior'=>'Pasajero','vehicle'=>'Vehículo','lost_item'=>'Objeto perdido',
              'payment'=>'Pago','app_problem'=>'App','other'=>'Otro'
            ] as $k=>$label)
              <option value="{{ $k }}" {{ request('category')===$k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Escalado a plataforma</label>
          <select name="forward_to_platform" class="form-select">
            <option value="">Todos</option>
            <option value="1" {{ request('forward_to_platform')==='1' ? 'selected' : '' }}>Sí</option>
            <option value="0" {{ request('forward_to_platform')==='0' ? 'selected' : '' }}>No</option>
          </select>
        </div>

        <div class="col-md-3 d-flex gap-2">
          <button class="btn btn-primary w-100">
            <i class="ti ti-filter me-1"></i> Filtrar
          </button>
          <a class="btn btn-outline-secondary w-100" href="{{ route('partner.reports.driver_quality.index') }}">
            Limpiar
          </a>
        </div>

      </form>
    </div>
  </div>

  {{-- KPIs --}}
  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="card">
        <div class="card-body">
          <div class="text-muted small">Conductores (partner)</div>
          <div class="fs-3 fw-bold">{{ (int)($kpi['drivers_total'] ?? 0) }}</div>
          <div class="text-muted small">Registrados</div>
        </div>
      </div>
    </div>
    <div class="col-md-9">
      <div class="card">
        <div class="card-body">
          <div class="text-muted small mb-1">Lectura del reporte</div>
          <div class="text-muted">
            Enfócate en <span class="fw-semibold">issues abiertos</span> y <span class="fw-semibold">críticos</span>.
            El <span class="fw-semibold">promedio</span> sirve, pero el volumen de ratings te dice si es confiable.
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Tabla --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Conductores</div>
      <div class="text-muted small">Orden: abiertos → críticos → volumen de ratings</div>
    </div>

    <div class="table-responsive">
      <table class="table table-striped mb-0 align-middle">
        <thead>
          <tr>
            <th>Conductor</th>
            <th class="text-end">Ratings</th>
            <th class="text-end">Prom.</th>
            <th class="text-end">Issues</th>
            <th class="text-end">Abiertos</th>
            <th class="text-end">Críticos</th>
            <th class="text-muted">Último rating</th>
            <th class="text-muted">Último issue</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($drivers as $d)
            @php
              $avg = (float)($d->rating_avg ?? 0);
              $avgFmt = $avg > 0 ? number_format($avg, 2) : '-';
              $ratingsCount = (int)($d->ratings_count ?? 0);
              $issuesCount = (int)($d->issues_count ?? 0);
              $openish = (int)($d->issues_openish ?? 0);
              $critical = (int)($d->sev_critical ?? 0);

              $lastRating = !empty($d->last_rating_at) ? \Carbon\Carbon::parse($d->last_rating_at)->format('Y-m-d') : '-';
              $lastIssue  = !empty($d->last_issue_at) ? \Carbon\Carbon::parse($d->last_issue_at)->format('Y-m-d') : '-';
            @endphp

            <tr>
              <td>
                <div class="fw-semibold">{{ $d->name }}</div>
                <div class="text-muted small">
                  {{ $d->phone ?? '—' }}
                  @if(!empty($d->email)) · {{ $d->email }} @endif
                </div>
              </td>

              <td class="text-end fw-semibold">{{ $ratingsCount }}</td>
              <td class="text-end fw-semibold">{{ $avgFmt }}</td>

              <td class="text-end">{{ $issuesCount }}</td>
              <td class="text-end">
                @if($openish > 0)
                  <span class="badge bg-warning-lt text-warning">{{ $openish }}</span>
                @else
                  <span class="text-muted">0</span>
                @endif
              </td>
              <td class="text-end">
                @if($critical > 0)
                  <span class="badge bg-red-lt text-red">{{ $critical }}</span>
                @else
                  <span class="text-muted">0</span>
                @endif
              </td>

              <td class="text-muted">{{ $lastRating }}</td>
              <td class="text-muted">{{ $lastIssue }}</td>

              <td class="text-end">
                <a class="btn btn-outline-secondary btn-sm"
                   href="{{ route('partner.reports.driver_quality.show', $d->id) . '?' . http_build_query(request()->query()) }}">
                  Ver
                </a>
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="text-muted p-3">Sin resultados con los filtros actuales.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $drivers->links() }}
    </div>
  </div>

</div>
@endsection
