@extends('layouts.partner')

@section('content')
<div class="container-fluid">

  @php
    $st = strtolower((string)($topup->status ?? ''));
    $badge = 'secondary';
    if (in_array($st, ['pending_review','pending'])) $badge = 'warning';
    if ($st === 'approved') $badge = 'info';
    if ($st === 'credited') $badge = 'success';
    if ($st === 'rejected') $badge = 'danger';
  @endphp

  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Finanzas</div>
        <h2 class="page-title">Recarga #{{ $topup->id }}</h2>
        <div class="text-muted">Detalle de la solicitud</div>
      </div>
      <div class="col-auto ms-auto d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('partner.topups.index') }}">
          <i class="ti ti-arrow-left me-1"></i> Volver
        </a>
        <a class="btn btn-primary" href="{{ route('partner.topups.create') }}">
          <i class="ti ti-plus me-1"></i> Nueva recarga
        </a>
      </div>
    </div>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
  @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

  <div class="row g-3">
    <div class="col-lg-7">

      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
              <div class="text-muted">Estatus</div>
              <div class="mt-1">
                <span class="badge bg-{{ $badge }} fs-6">{{ $topup->status }}</span>
              </div>
              @if(!empty($topup->review_status))
                <div class="text-muted small mt-1">Revisión: {{ $topup->review_status }}</div>
              @endif
            </div>

            <div class="text-end">
              <div class="text-muted">Monto</div>
              <div class="fs-3 fw-bold">
                ${{ number_format($topup->amount, 2) }} {{ $topup->currency ?? 'MXN' }}
              </div>
            </div>
          </div>

          <hr class="my-3">

          <dl class="row mb-0">
            <dt class="col-5 text-muted fw-normal">Método</dt>
            <dd class="col-7 fw-semibold">{{ $topup->method ?? '-' }}</dd>

            <dt class="col-5 text-muted fw-normal">Referencia bancaria</dt>
            <dd class="col-7 fw-semibold">{{ $topup->bank_ref ?? '—' }}</dd>

            <dt class="col-5 text-muted fw-normal">Enviado</dt>
            <dd class="col-7 fw-semibold">{{ optional($topup->created_at)->format('Y-m-d H:i') }}</dd>

            <dt class="col-5 text-muted fw-normal">Actualizado</dt>
            <dd class="col-7 fw-semibold">{{ optional($topup->updated_at)->format('Y-m-d H:i') }}</dd>
          </dl>

          @if(!empty($topup->review_notes))
            <hr class="my-3">
            <div class="text-muted small">Mensaje de Orbana</div>
            <div class="alert alert-{{ $st==='rejected' ? 'danger' : 'info' }} mb-0">
              {{ $topup->review_notes }}
            </div>
          @endif
        </div>
      </div>

      @if($st === 'rejected')
        <div class="card mt-3 border-danger">
          <div class="card-header">
            <h3 class="card-title text-danger mb-0">
              <i class="ti ti-alert-triangle me-1"></i> Acción requerida
            </h3>
          </div>
          <div class="card-body">
            <div class="text-muted mb-3">
              Tu recarga fue rechazada. Sube un nuevo comprobante (y opcionalmente ajusta la referencia) para reenviar la solicitud.
            </div>

            <form method="POST" action="{{ route('partner.topups.resubmit', $topup) }}" enctype="multipart/form-data">
              @csrf

              <div class="mb-3">
                <label class="form-label">Nueva referencia bancaria (opcional)</label>
                <input type="text" name="bank_ref" class="form-control" value="{{ old('bank_ref', $topup->bank_ref) }}">
                @error('bank_ref')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>

              <div class="mb-3">
                <label class="form-label">Nuevo comprobante (requerido)</label>
                <input type="file" name="proof" class="form-control" required accept=".jpg,.jpeg,.png,.pdf">
                @error('proof')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>

              <button class="btn btn-danger">
                <i class="ti ti-upload me-1"></i> Reenviar comprobante
              </button>
            </form>
          </div>
        </div>
      @endif

    </div>

   <div class="col-lg-5">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Comprobante</span>
      @if(!empty($topup->proof_path))
        <span class="badge bg-azure-lt">{{ strtoupper(pathinfo($topup->proof_path, PATHINFO_EXTENSION)) }}</span>
      @endif
    </div>

    <div class="card-body">
      @if(!empty($topup->proof_path))
        <div class="d-flex gap-2">
          <a class="btn btn-outline-primary"
             target="_blank"
             href="{{ asset('storage/' . $topup->proof_path) }}">
            <i class="ti ti-external-link me-1"></i> Abrir
          </a>

          <div class="text-muted small d-flex align-items-center">
            {{ \Carbon\Carbon::parse($topup->updated_at ?? $topup->created_at)->format('Y-m-d H:i') }}
          </div>
        </div>

        @php
          $ext = strtolower(pathinfo($topup->proof_path, PATHINFO_EXTENSION));
          $isImg = in_array($ext, ['jpg','jpeg','png','webp']);
          $url = asset('storage/' . $topup->proof_path);
        @endphp

        <div class="mt-3">
          @if($isImg)
            {{-- Preview pequeña y amigable (no media hoja) --}}
            <a href="{{ $url }}" target="_blank" class="d-inline-block">
              <img
                src="{{ $url }}"
                alt="Comprobante"
                class="rounded border"
                style="max-height: 220px; width: auto; max-width: 100%; object-fit: contain;"
              >
            </a>
            <div class="text-muted small mt-2">Toca la imagen para abrirla.</div>
          @else
            <div class="alert alert-info mb-0">
              Documento adjunto. Usa <b>Abrir</b> para ver el comprobante.
            </div>
          @endif
        </div>
      @else
        <div class="text-muted">No se adjuntó comprobante.</div>
      @endif
    </div>
  </div>
</div>

  </div>

</div>
@endsection
