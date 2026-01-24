@extends('layouts.admin')

@section('title', 'Recarga de Partner')

@section('content')
@php
  use Illuminate\Support\Facades\Storage;

  $badgeClass = function($s) {
    $s = strtolower((string)$s);
    return match($s) {
      'pending_review' => 'bg-warning-lt text-warning',
      'approved'       => 'bg-info-lt text-info',
      'credited'       => 'bg-success-lt text-success',
      'rejected'       => 'bg-danger-lt text-danger',
      default          => 'bg-secondary-lt text-secondary',
    };
  };
 $proofUrl = !empty($topup->proof_path) ? Storage::url($topup->proof_path) : null;
  $ext = !empty($topup->proof_path) ? strtolower(pathinfo($topup->proof_path, PATHINFO_EXTENSION)) : '';
  $isImg = in_array($ext, ['jpg','jpeg','png','webp','gif']);
@endphp

<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <div class="text-muted small">Recargas · Partners</div>
      <h1 class="h3 mb-0">
        Recarga #{{ $topup->id }}
        <span class="ms-2 badge {{ $badgeClass($topup->status) }}">{{ strtoupper((string)$topup->status) }}</span>
      </h1>
      <div class="text-muted small">
        {{ $topup->partner->name ?? ('Partner #'.$topup->partner_id) }}
        · ${{ number_format((float)$topup->amount,2) }} {{ $topup->currency ?? 'MXN' }}
      </div>
    </div>

    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="{{ route('admin.partner_topups.index') }}">
        <i class="ti ti-arrow-left me-1"></i> Volver
      </a>
      @if($proofUrl)
        <a class="btn btn-outline-primary" target="_blank" href="{{ $proofUrl }}">
          <i class="ti ti-external-link me-1"></i> Abrir comprobante
        </a>
      @endif
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">

      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="ti ti-list-details me-1"></i> Detalle</div>
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-5 text-muted">Partner</dt>
            <dd class="col-7">
              <div class="fw-semibold">{{ $topup->partner->name ?? ('Partner #'.$topup->partner_id) }}</div>
              <div class="text-muted small">{{ $topup->partner->code ?? '' }}</div>
            </dd>

            <dt class="col-5 text-muted">Monto</dt>
            <dd class="col-7 fw-semibold">${{ number_format((float)$topup->amount,2) }} {{ $topup->currency ?? 'MXN' }}</dd>

            <dt class="col-5 text-muted">Referencia</dt>
            <dd class="col-7">{{ $topup->bank_ref ?: ($topup->external_reference ?: '—') }}</dd>

            <dt class="col-5 text-muted">Creado</dt>
            <dd class="col-7">{{ optional($topup->created_at)->format('Y-m-d H:i') }}</dd>

            <dt class="col-5 text-muted">Revisado (Orbana)</dt>
            <dd class="col-7">
              {{ $topup->reviewed_at ? \Carbon\Carbon::parse($topup->reviewed_at)->format('Y-m-d H:i') : '—' }}
            </dd>

            @if(!empty($topup->credited_at))
              <dt class="col-5 text-muted">Acreditado</dt>
              <dd class="col-7">{{ \Carbon\Carbon::parse($topup->credited_at)->format('Y-m-d H:i') }}</dd>
            @endif
          </dl>

          @if(!empty($topup->review_notes))
            <hr class="my-3">
            <div class="text-muted small mb-1">Mensaje de Orbana</div>
            <div class="fw-semibold">{{ $topup->review_notes }}</div>
          @endif
        </div>
      </div>

    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Comprobante</span>
          @if($topup->proof_path)
            <span class="badge bg-azure-lt">{{ strtoupper($ext ?: 'FILE') }}</span>
          @endif
        </div>

        <div class="card-body">
          @if($proofUrl)
            <a class="btn btn-outline-primary w-100 mb-3" target="_blank" href="{{ $proofUrl }}">
              <i class="ti ti-external-link me-1"></i> Abrir comprobante
            </a>

            @if($isImg)
              <a href="{{ $proofUrl }}" target="_blank" class="d-inline-block">
                <img
                  src="{{ $proofUrl }}"
                  class="rounded border"
                  style="max-height:220px;width:auto;max-width:100%;object-fit:contain;"
                  alt="Comprobante">
              </a>
              <div class="text-muted small mt-2">Toca la imagen para abrirla.</div>
            @else
              <div class="alert alert-info mb-0">
                Documento adjunto. Usa <b>Abrir comprobante</b>.
              </div>
            @endif

          @else
            <div class="text-muted">No se adjuntó comprobante.</div>
          @endif
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
