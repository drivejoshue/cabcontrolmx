@php
  $docs = $docs ?? collect();

  $docLabel = [
    'id_official'     => 'Identificación oficial',
    'proof_address'   => 'Comprobante de domicilio',
    'tax_certificate' => 'Constancia fiscal (opcional)',
  ];

  $docIcon = [
    'id_official'     => 'ti ti-id',
    'proof_address'   => 'ti ti-home',
    'tax_certificate' => 'ti ti-receipt-tax',
  ];

  $badgeDoc = function($status) {
    return match($status) {
      'approved' => 'bg-success-lt text-success',
      'rejected' => 'bg-danger-lt text-danger',
      'pending'  => 'bg-warning-lt text-warning',
      default    => 'bg-secondary-lt text-secondary',
    };
  };

  $docStatusText = function($status) {
    return match($status) {
      'approved' => 'Aprobado',
      'rejected' => 'Rechazado',
      'pending'  => 'Pendiente',
      default    => 'Sin archivo',
    };
  };
@endphp

@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif

@if ($errors->any())
  <div class="alert alert-danger">
    <div class="fw-bold mb-1">Revisa los campos:</div>
    <ul class="mb-0">
      @foreach($errors->all() as $e)
        <li>{{ $e }}</li>
      @endforeach
    </ul>
  </div>
@endif

<div class="card shadow-sm border-0">
  <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-start justify-content-between flex-wrap gap-2">
    <div>
      <h5 class="card-title mb-0">Documentos del tenant</h5>
      <div class="text-muted small">
        Validación SysAdmin. “Reabrir” permite que el tenant vuelva a subir.
      </div>
    </div>

    <div class="text-muted small text-end">
      <div>Tenant: <strong>{{ $tenant->name }}</strong></div>
      <div>ID: <strong>{{ $tenant->id }}</strong> · Slug: <strong>{{ $tenant->slug }}</strong></div>
    </div>
  </div>

  <div class="card-body">
    <div class="list-group list-group-flush border rounded">
      @foreach(['id_official','proof_address','tax_certificate'] as $type)
        @php
          $doc = $docs->get($type);

          $status = $doc->status ?? null;
          $badgeClass = $badgeDoc($status);

          $original = $doc->original_name ?? null;

          $uploaded = !empty($doc?->uploaded_at)
            ? \Illuminate\Support\Carbon::parse($doc->uploaded_at)->format('Y-m-d H:i')
            : null;

          $reviewedAt = !empty($doc?->reviewed_at)
            ? \Illuminate\Support\Carbon::parse($doc->reviewed_at)->format('Y-m-d H:i')
            : null;

          $hasDoc = (bool) $doc;
        @endphp

        <div class="list-group-item">
          <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div class="d-flex align-items-start gap-2" style="min-width: 320px;">
              <span class="avatar avatar-sm bg-secondary-lt text-secondary">
                <i class="{{ $docIcon[$type] ?? 'ti ti-file-text' }}"></i>
              </span>

              <div>
                <div class="fw-semibold">{{ $docLabel[$type] }}</div>

                <div class="text-muted small mt-1">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge {{ $badgeClass }}">{{ $docStatusText($status) }}</span>
                    <span class="text-muted">
                      @if($type === 'tax_certificate') Opcional @else Requerido @endif
                    </span>
                  </div>

                  @if($hasDoc)
                    <div class="mt-1">
                      <span class="text-muted">Archivo:</span>
                      <span class="fw-semibold">{{ $original }}</span>
                    </div>
                    <div>
                      <span class="text-muted">Subido:</span>
                      <span>{{ $uploaded ?? '—' }}</span>
                    </div>
                    <div>
                      <span class="text-muted">Revisado:</span>
                      <span>{{ $reviewedAt ?? '—' }}</span>
                    </div>

                    @if(!empty($doc->review_notes))
                      <div class="mt-1">
                        <span class="badge bg-danger-lt text-danger">
                          <i class="ti ti-info-circle"></i> Observación
                        </span>
                        <span class="text-danger">{{ $doc->review_notes }}</span>
                      </div>
                    @endif
                  @else
                    <div class="mt-1">Aún no hay archivo subido.</div>
                  @endif
                </div>

                @if($hasDoc)
                  <div class="mt-2 d-flex gap-2 flex-wrap">
                    <a class="btn btn-sm btn-outline-secondary"
                       href="{{ route('sysadmin.tenant-documents.download', $doc->id) }}">
                      <i class="ti ti-download"></i> Descargar
                    </a>

                    @if($status !== 'approved')
                      <form method="POST" action="{{ route('sysadmin.tenant-documents.approve', $doc->id) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success"
                          onclick="return confirm('¿Aprobar este documento?');">
                          <i class="ti ti-check"></i> Aprobar
                        </button>
                      </form>
                    @endif

                    @if($status !== 'pending')
                      <form method="POST" action="{{ route('sysadmin.tenant-documents.reopen', $doc->id) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-warning"
                          onclick="return confirm('¿Reabrir para permitir re-subida del tenant?');">
                          <i class="ti ti-refresh"></i> Reabrir
                        </button>
                      </form>
                    @endif
                  </div>

                  <div class="mt-2">
                    <form method="POST" action="{{ route('sysadmin.tenant-documents.reject', $doc->id) }}" class="d-flex gap-2 flex-wrap">
                      @csrf
                      <input
                        type="text"
                        name="review_notes"
                        class="form-control @error('review_notes') is-invalid @enderror"
                        placeholder="Motivo de rechazo (requerido)"
                        maxlength="400"
                        required
                        style="min-width: 320px;"
                      >
                      <button type="submit" class="btn btn-danger"
                        onclick="return confirm('¿Rechazar este documento?');">
                        <i class="ti ti-x"></i> Rechazar
                      </button>
                    </form>
                  </div>

                  <div class="small text-muted mt-2">
                    Disk: <strong>{{ $doc->disk }}</strong> · Path: <code>{{ $doc->path }}</code>
                  </div>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>
