{{-- resources/views/sysadmin/tenants/billing/partners/topups_show.blade.php --}}
@extends('layouts.sysadmin')

@section('title', 'Partner Topup #'.$topup->id.' · Tenant #'.$tenant->id)

@section('content')
<div class="container-fluid">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Topup #{{ $topup->id }}</h3>
      <div class="text-muted small">
        Tenant: {{ $tenant->name }} ({{ $tenant->id }})
        · Partner: #{{ $topup->partner_id }} {{ $partner?('· '.$partner->name):'' }}
      </div>
    </div>

    <a class="btn btn-outline-secondary btn-sm"
       href="{{ route('sysadmin.tenants.partner_topups.index', $tenant) }}">
      Volver a topups
    </a>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  @php
    $st = strtolower((string)($topup->status ?? 'pending_review'));
    $hasProof = !empty($topup->proof_path);
    $proofUrl = $hasProof ? \Illuminate\Support\Facades\Storage::disk('public')->url($topup->proof_path) : null;
  @endphp

  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="card">
        <div class="card-header"><strong>Detalle</strong></div>
        <div class="card-body small">
          <div><strong>Status:</strong> {{ strtoupper($st) }}</div>
          <div><strong>Monto:</strong> {{ number_format((float)$topup->amount,2) }} {{ $topup->currency }}</div>
          <div><strong>Método:</strong> {{ $topup->method ?? '—' }}</div>
          <div><strong>Ext ref:</strong> {{ $topup->external_reference ?? '—' }}</div>
          <div><strong>Bank ref:</strong> {{ $topup->bank_ref ?? '—' }}</div>
          <hr>
          <div><strong>Payer:</strong> {{ $topup->payer_name ?? '—' }}</div>
          <div><strong>Creado:</strong> {{ $topup->created_at ?? '—' }}</div>
          <div><strong>Revisado:</strong> {{ $topup->reviewed_at ?? '—' }} (by {{ $topup->reviewed_by ?? '—' }})</div>
          <div><strong>Notas:</strong> {{ $topup->review_notes ?? '—' }}</div>
        </div>

        @if(in_array($st, ['pending_review','pending'], true))
          <div class="card-footer d-flex gap-2">
            <form method="POST"
                  action="{{ route('sysadmin.tenants.partner_topups.approve', [$tenant, $topup]) }}"
                  onsubmit="return confirm('¿Aprobar y acreditar este topup?');">
              @csrf
              <input class="form-control form-control-sm mb-2" name="review_notes" placeholder="Notas (opcional)">
              <button class="btn btn-success btn-sm">Approve & Acreditar</button>
            </form>

            <form method="POST"
                  action="{{ route('sysadmin.tenants.partner_topups.reject', [$tenant, $topup]) }}"
                  onsubmit="return confirm('¿Rechazar este topup?');">
              @csrf
              <input class="form-control form-control-sm mb-2" name="review_notes" placeholder="Motivo (obligatorio)" required>
              <button class="btn btn-outline-danger btn-sm">Reject</button>
            </form>
          </div>
        @endif
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card">
        <div class="card-header"><strong>Comprobante</strong></div>
        <div class="card-body">
          @if($hasProof)
            <a href="{{ $proofUrl }}" target="_blank" class="btn btn-outline-primary btn-sm mb-2">Abrir</a>
            <div class="border rounded p-2">
              <img src="{{ $proofUrl }}" alt="Comprobante" style="max-width:100%; height:auto;">
            </div>
          @else
            <div class="text-muted small">No se adjuntó comprobante.</div>
          @endif
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
