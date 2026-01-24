@php
  use Illuminate\Support\Facades\Storage;

  $proofUrl = $topup->proof_path ? Storage::disk('public')->url($topup->proof_path) : null;
  $ext = $topup->proof_path ? strtolower(pathinfo($topup->proof_path, PATHINFO_EXTENSION)) : '';
  $isImg = in_array($ext, ['jpg','jpeg','png','webp']);
  $isPdf = ($ext === 'pdf');
@endphp

<div class="card">
  <div class="card-body">
    <div><b>ID:</b> #{{ $topup->id }}</div>
    <div><b>Tenant:</b> {{ $topup->tenant_id }}</div>
    <div><b>Partner:</b> {{ $topup->partner_id }}</div>
    <div><b>Estatus:</b> {{ $topup->status }}</div>
    <div><b>Monto:</b> ${{ number_format($topup->amount,2) }} {{ $topup->currency }}</div>
    <div><b>Referencia:</b> {{ $topup->bank_ref ?? $topup->external_reference ?? '-' }}</div>

    <hr>

    <div class="fw-semibold mb-2">Comprobante</div>

    @if($proofUrl)
      <a class="btn btn-outline-primary btn-sm" target="_blank" href="{{ $proofUrl }}">Abrir comprobante</a>

      @if($isImg)
        <div class="mt-3">
          <img src="{{ $proofUrl }}" class="img-fluid rounded border" style="max-width:720px;">
        </div>
      @elseif($isPdf)
        <div class="ratio ratio-16x9 mt-3">
          <iframe src="{{ $proofUrl }}" loading="lazy"></iframe>
        </div>
      @endif
    @else
      <div class="text-muted">Sin comprobante adjunto.</div>
    @endif

    <hr>

    {{-- Acciones --}}
    <form class="d-inline" method="POST" action="{{ route('sysadmin.topups.partner_transfer.approve', $topup) }}">
      @csrf
      <div class="mb-2">
        <label class="form-label">Notas (opcional)</label>
        <textarea name="review_notes" class="form-control" rows="2"></textarea>
      </div>
      <button class="btn btn-success">Aprobar y acreditar</button>
    </form>

    <form class="d-inline ms-2" method="POST" action="{{ route('sysadmin.topups.partner_transfer.reject', $topup) }}">
      @csrf
      <div class="mb-2 mt-3">
        <label class="form-label">Motivo de rechazo (requerido)</label>
        <textarea name="review_notes" class="form-control" rows="2" required></textarea>
      </div>
      <button class="btn btn-danger">Rechazar</button>
    </form>

  </div>
</div>
