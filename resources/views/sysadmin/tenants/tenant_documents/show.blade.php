@extends('layouts.sysadmin')

@section('title','Validar documentos - SysAdmin')

@section('content')
@php
  $tenant = $tenant ?? null;
  $docs = $docs ?? collect(); // keyBy('type')

  $p = $tenant?->billingProfile ?? null;
  $billingAccepted = !empty($p?->accepted_terms_at);

  $typeOrder = ['id_official','proof_address','tax_certificate'];

  $typeLabel = [
    'id_official'     => 'Identificación oficial',
    'proof_address'   => 'Comprobante de domicilio',
    'tax_certificate' => 'Constancia fiscal (opcional)',
  ];

  $typeIcon = [
    'id_official'     => 'ti ti-id',
    'proof_address'   => 'ti ti-home',
    'tax_certificate' => 'ti ti-receipt-tax',
  ];

  $statusLabel = [
    'pending'  => 'Pendiente',
    'approved' => 'Aprobado',
    'rejected' => 'Rechazado',
  ];

  $badgeStatus = function($st) {
    return match($st) {
      'approved' => 'bg-success-lt text-success',
      'rejected' => 'bg-danger-lt text-danger',
      'pending'  => 'bg-warning-lt text-warning',
      default    => 'bg-secondary-lt text-secondary',
    };
  };

  $fmtDT = fn($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d H:i') : '—';

  // Compleción requerida (ID + Proof)
  $hasId = $docs->has('id_official');
  $hasProof = $docs->has('proof_address');
@endphp

<div class="container-fluid">

  <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap mb-3">
    <div>
      <h1 class="h3 mb-1">Validar documentos</h1>
      <div class="text-muted">
        Tenant <span class="fw-semibold">#{{ $tenant?->id }}</span> — {{ $tenant?->name ?? '—' }}
      </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('sysadmin.tenant-documents.index') }}" class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left"></i> Volver
      </a>
    </div>
  </div>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">Revisa:</div>
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Resumen --}}
  <div class="row g-3 mb-3">
    <div class="col-12 col-lg-8">
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div>
              <div class="fw-semibold">Estado de onboarding</div>
              <div class="text-muted small">
                Onboarding: <span class="fw-semibold">{{ $tenant?->onboarding_done_at ? 'Completado' : 'Pendiente' }}</span>
                · Docs requeridos: <span class="fw-semibold">{{ ($hasId && $hasProof) ? 'Completos' : 'Incompletos' }}</span>
              </div>
            </div>

            <div class="text-end">
              @if($billingAccepted)
                <span class="badge bg-success-lt text-success">
                  <i class="ti ti-check"></i> Billing habilitado
                </span>
                <div class="text-muted small mt-1">{{ $fmtDT($p->accepted_terms_at) }}</div>
              @else
                <span class="badge bg-warning-lt text-warning">
                  <i class="ti ti-alert-triangle"></i> Billing NO aceptado
                </span>
                <div class="text-muted small mt-1">Aún no aceptan términos.</div>
              @endif
            </div>
          </div>

          <hr class="my-3">

          <div class="row g-2 small">
            <div class="col-12 col-md-6">
              <div><span class="text-muted">Email notif:</span> <span class="fw-semibold">{{ $tenant?->notification_email ?? '—' }}</span></div>
              <div><span class="text-muted">Tel:</span> <span class="fw-semibold">{{ $tenant?->public_phone ?? '—' }}</span></div>
              <div><span class="text-muted">Ciudad:</span> <span class="fw-semibold">{{ $tenant?->public_city ?? '—' }}</span></div>
            </div>
            <div class="col-12 col-md-6">
              <div><span class="text-muted">Slug:</span> <span class="fw-semibold">{{ $tenant?->slug ?? '—' }}</span></div>
              <div><span class="text-muted">Actualizado:</span> <span class="fw-semibold">{{ $fmtDT($tenant?->updated_at) }}</span></div>
              <div><span class="text-muted">Creado:</span> <span class="fw-semibold">{{ $fmtDT($tenant?->created_at) }}</span></div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card">
        <div class="card-header">
          <div class="fw-semibold">Acciones rápidas</div>
        </div>
        <div class="card-body">
          <div class="text-muted small">
            Para pedir re-subida: marca como <span class="badge bg-danger-lt text-danger">Rechazado</span> con nota clara.
          </div>
          <div class="mt-3">
            <a class="btn btn-outline-secondary w-100"
               href="{{ route('sysadmin.tenants.show', $tenant->id) }}">
              <i class="ti ti-building"></i> Ir al tenant (SysAdmin)
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Lista de documentos --}}
  <div class="card">
    <div class="card-header">
      <div class="fw-semibold">Documentos</div>
      <div class="text-muted small">Descarga, revisa y valida. Todo queda auditado en tenant_documents.</div>
    </div>

    <div class="card-body">

      <div class="list-group list-group-flush border rounded">
        @foreach($typeOrder as $type)
          @php
            /** @var \App\Models\TenantDocument|null $doc */
            $doc = $docs->get($type);

            $st = $doc->status ?? null;
            $badge = $badgeStatus($st);
            $icon = $typeIcon[$type] ?? 'ti ti-file-text';
            $lbl  = $typeLabel[$type] ?? $type;

            $uploadedAt = $doc?->uploaded_at ? \Illuminate\Support\Carbon::parse($doc->uploaded_at)->format('Y-m-d H:i') : null;
            $sizeKb = $doc?->size_bytes ? round(((int)$doc->size_bytes)/1024) : null;

            $canReview = !empty($doc);
          @endphp

          <div class="list-group-item">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
              <div class="d-flex align-items-start gap-2" style="min-width: 280px;">
                <span class="avatar avatar-sm bg-secondary-lt text-secondary">
                  <i class="{{ $icon }}"></i>
                </span>

                <div>
                  <div class="fw-semibold">{{ $lbl }}</div>

                  <div class="text-muted small">
                    @if($doc)
                      <div>
                        <span class="text-muted">Archivo:</span>
                        <span class="fw-semibold">{{ $doc->original_name ?? '—' }}</span>
                      </div>
                      <div class="d-flex gap-3 flex-wrap">
                        <div><span class="text-muted">Subido:</span> <span>{{ $uploadedAt ?? '—' }}</span></div>
                        <div><span class="text-muted">Mime:</span> <span>{{ $doc->mime ?? '—' }}</span></div>
                        <div><span class="text-muted">Size:</span> <span>{{ $sizeKb ? number_format($sizeKb).' KB' : '—' }}</span></div>
                      </div>

                      @if($st === 'rejected' && !empty($doc->review_notes))
                        <div class="mt-2">
                          <span class="badge bg-danger-lt text-danger">
                            <i class="ti ti-info-circle"></i> Observación
                          </span>
                          <span class="text-danger">{{ $doc->review_notes }}</span>
                        </div>
                      @endif
                    @else
                      <div>No se ha subido.</div>
                    @endif
                  </div>

                  @if($doc)
                    <div class="mt-2 d-flex gap-2 flex-wrap">
                      <a class="btn btn-sm btn-outline-secondary"
                         href="{{ route('sysadmin.tenant-documents.download', $doc->id) }}">
                        <i class="ti ti-download"></i> Descargar
                      </a>
                    </div>
                  @endif
                </div>
              </div>

              <div class="text-end ms-auto">
                <span class="badge {{ $badge }}">
                  {{ $statusLabel[$st] ?? '—' }}
                </span>
              </div>
            </div>

            {{-- Acciones de revisión --}}
            @if($canReview)
              <div class="mt-3">
                <div class="d-flex gap-2 flex-wrap align-items-center justify-content-end">

                  {{-- Aprobar --}}
                  <form method="POST" action="{{ route('sysadmin.tenant-documents.approve', $doc->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success"
                      onclick="return confirm('¿Aprobar este documento?');">
                      <i class="ti ti-check"></i> Aprobar
                    </button>
                  </form>

                  {{-- Rechazar con nota --}}
                  <button type="button"
                          class="btn btn-sm btn-danger"
                          data-bs-toggle="modal"
                          data-bs-target="#rejectModal"
                          data-doc-id="{{ $doc->id }}"
                          data-doc-label="{{ $lbl }}">
                    <i class="ti ti-x"></i> Rechazar
                  </button>

                </div>
              </div>
            @endif

          </div>
        @endforeach
      </div>

    </div>
  </div>

</div>

{{-- Modal Rechazo --}}
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="" id="rejectForm" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Rechazar documento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-2" id="rejectDocLabel">Documento:</div>

        <label class="form-label">Motivo / Observación (requerido)</label>
        <textarea class="form-control" name="review_notes" rows="3" maxlength="400" required
                  placeholder="Ej. Imagen borrosa, no se ve la dirección completa, documento vencido, etc."></textarea>

        <div class="form-text">
          Se marcará como <span class="badge bg-danger-lt text-danger">Rechazado</span> y se mostrará al tenant.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-danger">
          <i class="ti ti-x"></i> Rechazar
        </button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function(){
  const modal = document.getElementById('rejectModal');
  const form  = document.getElementById('rejectForm');
  const label = document.getElementById('rejectDocLabel');

  if(!modal || !form || !label) return;

  modal.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    const docId = btn?.getAttribute('data-doc-id');
    const docLbl = btn?.getAttribute('data-doc-label') || 'Documento';

    label.textContent = 'Documento: ' + docLbl;

    // Set action route dynamically
    // IMPORTANT: tu ruta debe aceptar {doc} como parámetro.
    const base = @json(route('sysadmin.tenant-documents.reject', ['doc' => 0]));
    form.action = base.replace(/0$/, String(docId));
  });
})();
</script>
@endpush
