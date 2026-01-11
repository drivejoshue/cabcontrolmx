@extends('layouts.admin')

@section('content')
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="mb-0">Issue #{{ $issue->id }}</h1>
      <div class="text-muted">{{ $issue->title }}</div>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.ride_issues.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver
      </a>
    </div>
  </div>

  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  <div class="row">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-body">
          <div class="d-flex flex-wrap gap-2 mb-2">
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

              $cat = $issue->category;
              $catClass = 'bg-secondary-lt text-secondary';
              $catIcon  = 'bi-tag';
            @endphp

            <span class="badge {{ $repClass }} rounded-pill">
              <i class="bi {{ $repIcon }} me-1"></i>{{ $issue->reporter_type }}
            </span>

            <span class="badge {{ $sevClass }} rounded-pill">
              <i class="bi {{ $sevIcon }} me-1"></i>{{ $issue->severity }}
            </span>

            <span class="badge {{ $stClass }} rounded-pill">
              <i class="bi {{ $stIcon }} me-1"></i>{{ $issue->status }}
            </span>

            <span class="badge {{ $catClass }} rounded-pill">
              <i class="bi {{ $catIcon }} me-1"></i>{{ $issue->category }}
            </span>

            @if($issue->forward_to_platform)
              <span class="badge bg-warning-lt text-warning rounded-pill">
                <i class="bi bi-arrow-up-right-square me-1"></i> Escalado a plataforma
              </span>
            @endif
          </div>

          <div class="text-muted mb-2">
            <div><strong>Creado:</strong> {{ optional($issue->created_at)->format('Y-m-d H:i') }}</div>
            @if($issue->resolved_at)
              <div><strong>Resuelto:</strong> {{ optional($issue->resolved_at)->format('Y-m-d H:i') }}</div>
            @endif
            @if(isset($issue->closed_at) && $issue->closed_at)
              <div><strong>Cerrado:</strong> {{ optional($issue->closed_at)->format('Y-m-d H:i') }}</div>
            @endif
          </div>

          @if($issue->description)
            <hr>
            <div class="fw-semibold mb-1">Descripción</div>
            <div class="text-muted" style="white-space: pre-wrap;">{{ $issue->description }}</div>
          @endif

          @if(isset($issue->resolution_notes) && $issue->resolution_notes)
            <hr>
            <div class="fw-semibold mb-1">Notas de resolución</div>
            <div class="text-muted" style="white-space: pre-wrap;">{{ $issue->resolution_notes }}</div>
          @endif
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <i class="bi bi-journal-text me-1"></i> Historial / Notas
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('admin.ride_issues.notes.store', $issue->id) }}" class="mb-3">
            @csrf
            <label class="form-label">Agregar nota</label>
            <textarea name="note" class="form-control" rows="3" placeholder="Nota interna para seguimiento..."></textarea>
            <div class="d-flex justify-content-end mt-2">
              <button class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Guardar nota
              </button>
            </div>
          </form>

          @if($issue->notes && $issue->notes->count())
            <div class="divide-y">
              @foreach($issue->notes as $note)
                @php
                  $vis = $note->visibility ?? 'internal';
                  $visClass = match($vis) {
                    'public'   => 'bg-azure-lt text-azure',
                    'internal' => 'bg-secondary-lt text-secondary',
                    default    => 'bg-secondary-lt text-secondary',
                  };
                  $visIcon = match($vis) {
                    'public'   => 'bi-globe',
                    'internal' => 'bi-shield-lock',
                    default    => 'bi-shield-lock',
                  };
                @endphp
                <div class="py-3">
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="fw-semibold">
                      {{ optional($note->user)->name ?? 'Sistema' }}
                      <span class="badge {{ $visClass }} ms-2 rounded-pill">
                        <i class="bi {{ $visIcon }} me-1"></i>{{ $vis }}
                      </span>
                    </div>
                    <div class="text-muted small">{{ optional($note->created_at)->format('Y-m-d H:i') }}</div>
                  </div>
                  <div class="text-muted mt-1" style="white-space: pre-wrap;">{{ $note->note }}</div>
                </div>
              @endforeach
            </div>
          @else
            <div class="text-muted">Aún no hay notas.</div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header">
          <i class="bi bi-car-front me-1"></i> Viaje
        </div>
        <div class="card-body">
          @if($issue->ride)
            <div class="mb-2">
              <strong>Ride #{{ $issue->ride->id }}</strong>
              <span class="badge bg-secondary-lt text-secondary ms-1 rounded-pill">
                <i class="bi bi-activity me-1"></i>{{ $issue->ride->status }}
              </span>
            </div>
            <div class="text-muted">
              <div><strong>Canal:</strong> {{ $issue->ride->requested_channel ?? '—' }}</div>
              <div><strong>Origen:</strong> {{ $issue->ride->origin_label ?? '—' }}</div>
              <div><strong>Destino:</strong> {{ $issue->ride->dest_label ?? '—' }}</div>
              <div><strong>Pago:</strong> {{ $issue->ride->payment_method ?? '—' }}</div>
              <div><strong>Monto:</strong> {{ $issue->ride->agreed_amount ?? $issue->ride->total_amount ?? $issue->ride->quoted_amount ?? '—' }} {{ $issue->ride->currency ?? '' }}</div>
              @if($issue->ride->canceled_at)
                <div class="mt-2">
                  <div><strong>Cancelado:</strong> {{ $issue->ride->canceled_at }}</div>
                  <div><strong>Por:</strong> {{ $issue->ride->canceled_by ?? '—' }}</div>
                  <div><strong>Motivo:</strong> {{ $issue->ride->cancel_reason ?? '—' }}</div>
                </div>
              @endif
            </div>
          @else
            <div class="text-muted">Sin información de viaje.</div>
          @endif
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <i class="bi bi-person me-1"></i> Pasajero
        </div>
        <div class="card-body text-muted">
          <div><strong>Nombre:</strong> {{ optional($issue->passenger)->name ?? ($issue->ride->passenger_name ?? '—') }}</div>
          <div><strong>Teléfono:</strong> {{ optional($issue->passenger)->phone ?? ($issue->ride->passenger_phone ?? '—') }}</div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <i class="bi bi-person-badge me-1"></i> Conductor
        </div>
        <div class="card-body text-muted">
          <div><strong>Nombre:</strong> {{ optional($issue->driver)->name ?? '—' }}</div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <i class="bi bi-sliders me-1"></i> Actualizar issue
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('admin.ride_issues.update_status', $issue->id) }}">
            @csrf

            <div class="mb-2">
              <label class="form-label">Estado</label>
              <select name="status" class="form-select">
                @foreach (['open' => 'Abierto', 'in_review' => 'En revisión', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'] as $key => $label)
                  <option value="{{ $key }}" @selected($issue->status === $key)>{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="mb-2">
              <label class="form-label">Severidad</label>
              <select name="severity" class="form-select">
                @foreach (['low'=>'Baja','normal'=>'Normal','high'=>'Alta','critical'=>'Crítica'] as $key => $label)
                  <option value="{{ $key }}" @selected($issue->severity === $key)>{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="mb-2">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="forward_to_platform" value="1" @checked($issue->forward_to_platform)>
                <span class="form-check-label">Escalar a plataforma</span>
              </label>
              <div class="text-muted small">Marca esto para indicar que Orbana debe revisar el caso.</div>
            </div>

            <div class="mb-2">
              <label class="form-label">Notas de resolución (opcional)</label>
              <textarea name="resolution_notes" class="form-control" rows="4" placeholder="Conclusión / acciones tomadas...">{{ old('resolution_notes', $issue->resolution_notes) }}</textarea>
            </div>

            <div class="mb-2">
              <label class="form-label">Nota rápida (opcional)</label>
              <textarea name="internal_note" class="form-control" rows="3" placeholder="Se guardará en el historial..."></textarea>
            </div>

            <button class="btn btn-primary w-100">
              <i class="bi bi-check2-circle me-1"></i> Guardar cambios
            </button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection
