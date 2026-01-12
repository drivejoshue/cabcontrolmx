@extends('layouts.sysadmin')

@section('title','Revisar transferencia')

@php
  $badge = function ($status) {
    return match($status) {
      'pending_review' => 'bg-warning-lt text-warning',
      'credited'       => 'bg-success-lt text-success',
      'approved'       => 'bg-success-lt text-success',
      'rejected'       => 'bg-danger-lt text-danger',
      default          => 'bg-secondary-lt text-secondary',
    };
  };

  $statusLabel = function ($status) {
    return match($status) {
      'pending_review' => 'Pendiente',
      'credited'       => 'Acreditado',
      'approved'       => 'Aprobado',
      'rejected'       => 'Rechazado',
      default          => $status ?: '—',
    };
  };

  $meta = (array)($topup->meta ?? []);
  $transfer = (array)($meta['transfer'] ?? []);
  $acc = (array)($transfer['account_snapshot'] ?? []);
  $submitted = (array)($meta['submitted_by'] ?? []);

  // --- PROOF: normalizar path para disk('public') ---
  $rawProofPath = (string)($topup->proof_path ?? '');
  $proofPath = ltrim($rawProofPath, '/');

  // Si guardaste por error con prefijos, los quitamos:
  // - "public/xxx" (muy común si se usa store('public/...') en vez de store(...,'public'))
  // - "storage/xxx" (si alguien guardó ya con /storage/)
  $proofPath = preg_replace('#^(public/|storage/)#i', '', $proofPath);

  $hasProof = $proofPath !== '' && \Illuminate\Support\Facades\Storage::disk('public')->exists($proofPath);
$proofUrl = $hasProof ? asset('storage/' . ltrim($topup->proof_path, '/')) : null;

  $ext = $hasProof ? strtolower(pathinfo($proofPath, PATHINFO_EXTENSION)) : null;
  $isImg = $hasProof && in_array($ext, ['jpg','jpeg','png','webp','gif']);
  $isPdf = $hasProof && $ext === 'pdf';

  $slot = $transfer['account_slot'] ?? ($topup->provider_account_slot ?? null);
  $isPending = $topup->status === 'pending_review';
@endphp

@section('content')
<div class="container-fluid">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <div class="d-flex align-items-center gap-2">
        <h3 class="mb-0">Transferencia #{{ $topup->id }}</h3>
        <span class="badge {{ $badge($topup->status) }}">{{ $statusLabel($topup->status) }}</span>
      </div>
      <div class="text-muted small">
        Tenant: <span class="fw-semibold">T{{ $topup->tenant_id }}</span> ·
        External Ref: <span class="fw-semibold mono">{{ $topup->external_reference ?? '—' }}</span>
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('sysadmin.topups.transfer.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
  @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

  @if ($errors->any())
    <div class="alert alert-danger">
      <div class="fw-semibold mb-1">Revisa el formulario</div>
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="row g-3">

    <div class="col-12 col-lg-5">
      <div class="card">
        <div class="card-header"><strong>Datos reportados</strong></div>
        <div class="card-body">

          <div class="text-muted small mb-1">Monto</div>
          <div class="h3 mb-3">${{ number_format((float)$topup->amount, 2) }} <span class="fs-5">MXN</span></div>

          <div class="row g-2">
            <div class="col-12">
              <div class="text-muted small">Referencia / Rastreo</div>
              <div class="fw-semibold mono">{{ $topup->bank_ref ?? '—' }}</div>
            </div>
            <div class="col-12">
              <div class="text-muted small">Fecha reportada</div>
              <div class="fw-semibold">{{ $topup->deposited_at?->format('Y-m-d H:i:s') ?? '—' }}</div>
            </div>
            <div class="col-12">
              <div class="text-muted small">Cuenta destino</div>
              <div class="fw-semibold">Cuenta {{ $slot ? '#'.$slot : '—' }}</div>
            </div>
          </div>

          <hr class="my-3">

          <div class="text-muted small mb-2">Snapshot cuenta destino</div>
          <div class="row g-2 small">
            <div class="col-12">
              <div class="text-muted">Banco</div>
              <div class="fw-semibold">{{ $acc['bank'] ?? '—' }}</div>
            </div>
            <div class="col-12">
              <div class="text-muted">Beneficiario</div>
              <div class="fw-semibold">{{ $acc['beneficiary'] ?? '—' }}</div>
            </div>
            <div class="col-12">
              <div class="text-muted">CLABE</div>
              <div class="fw-semibold mono">{{ $acc['clabe'] ?? '—' }}</div>
            </div>
            @if(!empty($acc['account']))
              <div class="col-12">
                <div class="text-muted">Cuenta</div>
                <div class="fw-semibold mono">{{ $acc['account'] }}</div>
              </div>
            @endif
          </div>

          <hr class="my-3">

          <div class="text-muted small mb-2">Enviado por</div>
          <div class="small">
            <div><span class="text-muted">Email:</span> <span class="fw-semibold">{{ $submitted['email'] ?? '—' }}</span></div>
            <div><span class="text-muted">User ID:</span> <span class="fw-semibold">{{ $submitted['user_id'] ?? '—' }}</span></div>
            <div><span class="text-muted">IP:</span> <span class="fw-semibold mono">{{ $submitted['ip'] ?? '—' }}</span></div>
          </div>

          @if(!empty($topup->review_notes))
            <hr class="my-3">
            <div class="text-muted small mb-1">Notas de revisión</div>
            <div class="small">{{ $topup->review_notes }}</div>
          @endif

        </div>
      </div>

      <div class="card mt-3">
        <div class="card-header"><strong>Acciones</strong></div>
        <div class="card-body">
          @if($isPending)
            <form method="POST" action="{{ route('sysadmin.topups.transfer.approve', $topup->id) }}" class="mb-2">
              @csrf
              <div class="mb-2">
                <label class="form-label">Notas (opcional)</label>
                <input type="text" name="review_notes" class="form-control" maxlength="500"
                       placeholder="Ej. Validado por SPEI / coincide monto y rastreo">
              </div>
              <button class="btn btn-success w-100" type="submit"
                      onclick="return confirm('¿Aprobar y acreditar saldo?');">
                Aprobar y acreditar
              </button>
            </form>

            <button class="btn btn-outline-danger w-100" type="button" data-bs-toggle="modal" data-bs-target="#rejectModal">
              Rechazar
            </button>
          @else
            <div class="text-muted small">Esta transferencia ya no está pendiente.</div>
          @endif
        </div>
      </div>

    </div>

    <div class="col-12 col-lg-7">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <strong>Comprobante</strong>
      

          @if($hasProof)
            <a href="{{ $proofUrl }}" target="_blank" class="btn btn-sm btn-outline-primary">Abrir / Descargar</a>
          @endif
        </div>
        <div class="card-body">
          @if(!$hasProof)
            <div class="alert alert-secondary mb-0">No se adjuntó comprobante.</div>
          @else
           @if($isImg)
  <div class="border rounded p-2 bg-white" style="max-height: 70vh; overflow:auto;">
    <img
      src="{{ $proofUrl }}"
      alt="Comprobante"
      class="d-block mx-auto rounded"
      style="max-width: 100%; height: auto; max-height: 66vh; object-fit: contain;"
    >
  </div>
@elseif($isPdf)
  <iframe src="{{ $proofUrl }}" style="width:100%; height: 70vh;" class="border rounded"></iframe>
@else
  <div class="alert alert-info mb-0">Archivo cargado. Usa “Abrir / Descargar”.</div>
@endif
          @endif
        </div>
      </div>

    <div class="card mt-3">
  <div class="card-header"><strong>Historial</strong></div>
  <div class="card-body small">
    <div><span class="text-muted">Estado:</span> <span class="fw-semibold">{{ $statusLabel($topup->status) }}</span></div>
    <div><span class="text-muted">Revisado por:</span> <span class="fw-semibold">{{ $topup->reviewed_by ?? '—' }}</span></div>
    <div><span class="text-muted">Revisado en:</span> <span class="fw-semibold">{{ $topup->reviewed_at?->format('Y-m-d H:i:s') ?? '—' }}</span></div>
    <div><span class="text-muted">Acreditado en:</span> <span class="fw-semibold">{{ $topup->credited_at?->format('Y-m-d H:i:s') ?? '—' }}</span></div>
  </div>
</div>

    </div>

  </div>
</div>

@if($isPending)
  <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">Rechazar transferencia</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <form method="POST" action="{{ route('sysadmin.topups.transfer.reject', $topup->id) }}">
          @csrf
          <div class="modal-body">
            <div class="mb-2">
              <label class="form-label">Motivo</label>
              <textarea name="review_notes" class="form-control" rows="4" maxlength="500" required
                        placeholder="Ej. Referencia inválida / monto no coincide / comprobante ilegible..."></textarea>
              <div class="form-text">Máximo 500 caracteres.</div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('¿Rechazar esta transferencia?');">
              Rechazar
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>
@endif
@endsection
