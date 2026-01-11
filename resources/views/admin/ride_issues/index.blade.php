@extends('layouts.admin')

@section('content')
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="mb-0">Issues de viajes</h1>
      <div class="text-muted">
        En este apartado podrás gestionar los reportes enviados desde <strong>Passenger</strong> y <strong>Driver</strong> (y también reportes internos de la central),
        darles seguimiento por estado, priorizarlos por severidad, y consultar la información del viaje, pasajero y conductor asociados.
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" class="row g-2">
        <div class="col-md-2">
          <label class="form-label">Estado</label>
          <select name="status" class="form-select">
            <option value="">Todos</option>
            @foreach (['open' => 'Abierto', 'in_review' => 'En revisión', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'] as $key => $label)
              <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Categoría</label>
          <select name="category" class="form-select">
            <option value="">Todas</option>
            @foreach ([
              'safety'            => 'Seguridad',
              'overcharge'        => 'Cobro incorrecto',
              'route'             => 'Ruta',
              'driver_behavior'   => 'Conducta conductor',
              'passenger_behavior'=> 'Conducta pasajero',
              'vehicle'           => 'Vehículo',
              'lost_item'         => 'Objeto perdido',
              'payment'           => 'Pago',
              'app_problem'       => 'App',
              'other'             => 'Otro',
            ] as $key => $label)
              <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Severidad</label>
          <select name="severity" class="form-select">
            <option value="">Todas</option>
            @foreach (['low'=>'Baja','normal'=>'Normal','high'=>'Alta','critical'=>'Crítica'] as $key => $label)
              <option value="{{ $key }}" @selected(request('severity') === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Reportó</label>
          <select name="reporter_type" class="form-select">
            <option value="">Todos</option>
            @foreach (['passenger'=>'Pasajero','driver'=>'Conductor','tenant'=>'Central','system'=>'Sistema'] as $key => $label)
              <option value="{{ $key }}" @selected(request('reporter_type') === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-1">
          <label class="form-label">Escalado</label>
          <select name="forward_to_platform" class="form-select">
            <option value="">—</option>
            <option value="1" @selected(request('forward_to_platform') === '1')>Sí</option>
            <option value="0" @selected(request('forward_to_platform') === '0')>No</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Buscar</label>
          <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="ID, Ride ID, título...">
        </div>

        <div class="col-12 d-flex gap-2 mt-2">
          <button class="btn btn-primary">
            <i class="bi bi-funnel me-1"></i> Filtrar
          </button>
          <a href="{{ route('admin.ride_issues.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-rotate-left me-1"></i> Limpiar
          </a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-vcenter table-striped mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 90px;">Issue</th>
              <th style="width: 160px;">Fecha</th>
              <th>Viaje</th>
              <th style="width: 160px;">Categoría</th>
              <th style="width: 120px;">Severidad</th>
              <th style="width: 140px;">Estado</th>
              <th style="width: 160px;">Reportó</th>
              <th style="width: 170px;">Pasajero</th>
              <th style="width: 170px;">Conductor</th>
              <th style="width: 90px;"></th>
            </tr>
          </thead>
          <tbody>
          @forelse ($issues as $issue)
            <tr>
              <td>
                <div class="fw-bold">#{{ $issue->id }}</div>
                @if($issue->forward_to_platform)
                  <span class="badge bg-warning-lt text-warning rounded-pill">
                    <i class="bi bi-arrow-up-right-square me-1"></i> Escalado
                  </span>
                @endif
              </td>

              <td class="text-muted">
                {{ optional($issue->created_at)->format('Y-m-d H:i') ?? $issue->created_at }}
              </td>

              <td>
                @if ($issue->ride)
                  <div class="fw-semibold">
                    Ride #{{ $issue->ride->id }}
                    <span class="badge bg-secondary-lt text-secondary ms-1 rounded-pill">
                      <i class="bi bi-activity me-1"></i>{{ $issue->ride->status }}
                    </span>
                  </div>
                  <div class="text-muted text-truncate" style="max-width: 520px;">
                    {{ $issue->ride->origin_label ?? '—' }} → {{ $issue->ride->dest_label ?? '—' }}
                  </div>
                @else
                  <span class="text-muted">Sin viaje</span>
                @endif
              </td>

              <td>
                <div class="fw-semibold">{{ $issue->category }}</div>
                <div class="text-muted small">{{ $issue->title }}</div>
              </td>

              <td>
                @php
                  $sev = $issue->severity;
                  $sevClass = match($sev) {
                    'critical' => 'bg-danger-lt text-danger',
                    'high'     => 'bg-warning-lt text-warning',
                    'normal'   => 'bg-azure-lt text-azure',
                    'low'      => 'bg-secondary-lt text-secondary',
                    default    => 'bg-secondary-lt text-secondary',
                  };
                  $sevIcon = match($sev) {
                    'critical' => 'bi-exclamation-octagon',
                    'high'     => 'bi-exclamation-triangle',
                    'normal'   => 'bi-info-circle',
                    'low'      => 'bi-dot',
                    default    => 'bi-dot',
                  };
                @endphp
                <span class="badge {{ $sevClass }} rounded-pill">
                  <i class="bi {{ $sevIcon }} me-1"></i>{{ $sev }}
                </span>
              </td>

              <td>
                @php
                  $st = $issue->status;
                  $stClass = match($st) {
                    'open'      => 'bg-danger-lt text-danger',
                    'in_review' => 'bg-warning-lt text-warning',
                    'resolved'  => 'bg-success-lt text-success',
                    'closed'    => 'bg-secondary-lt text-secondary',
                    default     => 'bg-secondary-lt text-secondary',
                  };
                  $stIcon = match($st) {
                    'open'      => 'bi-dot',
                    'in_review' => 'bi-hourglass-split',
                    'resolved'  => 'bi-check2-circle',
                    'closed'    => 'bi-x-circle',
                    default     => 'bi-dot',
                  };
                @endphp
                <span class="badge {{ $stClass }} rounded-pill">
                  <i class="bi {{ $stIcon }} me-1"></i>{{ $st }}
                </span>
                @if($issue->resolved_at)
                  <div class="text-muted small">resuelto {{ optional($issue->resolved_at)->format('Y-m-d H:i') }}</div>
                @endif
              </td>

              <td>
                @php
                  $rt = $issue->reporter_type;
                  $repClass = match($rt) {
                    'passenger' => 'bg-azure-lt text-azure',
                    'driver'    => 'bg-indigo-lt text-indigo',
                    'tenant'    => 'bg-primary-lt text-primary',
                    'system'    => 'bg-secondary-lt text-secondary',
                    default     => 'bg-secondary-lt text-secondary',
                  };
                  $repIcon = match($rt) {
                    'passenger' => 'bi-person',
                    'driver'    => 'bi-person-badge',
                    'tenant'    => 'bi-building',
                    'system'    => 'bi-cpu',
                    default     => 'bi-person',
                  };
                @endphp
                <span class="badge {{ $repClass }} rounded-pill">
                  <i class="bi {{ $repIcon }} me-1"></i>{{ $rt }}
                </span>
              </td>

              <td>{{ optional($issue->passenger)->name ?? '—' }}</td>
              <td>{{ optional($issue->driver)->name ?? '—' }}</td>

              <td class="text-end">
                <a href="{{ route('admin.ride_issues.show', $issue->id) }}" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-eye me-1"></i> Ver
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="10" class="text-center p-4 text-muted">No hay issues.</td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if ($issues instanceof \Illuminate\Pagination\LengthAwarePaginator)
      <div class="card-footer">
        {{ $issues->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
