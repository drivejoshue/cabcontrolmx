@extends('layouts.admin')

@section('title','Recargar saldo')

@push('styles')
<style>
  .soft-card { border: 1px solid rgba(0,0,0,.06); }
  [data-theme="dark"] .soft-card { border-color: rgba(255,255,255,.10); }

  .metric { font-size: 1.45rem; font-weight: 800; letter-spacing: -.02em; }
  .metric-sub { font-size: .85rem; color: rgba(0,0,0,.55); }
  [data-theme="dark"] .metric-sub { color: rgba(255,255,255,.65); }

  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }

  .nav-pills .nav-link { border-radius: 999px; }
  .hint { font-size: .85rem; }
</style>
@endpush

@section('content')
@php
  $tenant = auth()->user()->tenant;
  $tid = auth()->user()->tenant_id;

  $balance = (float)($wallet->balance ?? 0);
  $lastTopupAt = !empty($wallet->last_topup_at) ? \Carbon\Carbon::parse($wallet->last_topup_at)->toDateTimeString() : '—';

  $minTopup = (int)($minTopup ?? 200);

  // Datos bancarios (para Transferencia)
  $bank = $bank ?? [
    'beneficiary' => config('billing.transfer_beneficiary', '—'),
    'bank'        => config('billing.transfer_bank', '—'),
    'clabe'       => config('billing.transfer_clabe', '—'),
    'account'     => config('billing.transfer_account', null),
    'notes'       => config('billing.transfer_notes', null),
  ];

  // Referencia sugerida
  $suggestedRef = 'ORBANA-T'.$tid;
@endphp

<div class="container-fluid">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Recargar saldo</h3>
      <div class="text-muted small">
        Central: {{ $tenant?->name ?? '—' }} · Tenant ID: {{ $tid ?? '—' }}
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('admin.wallet.index') }}" class="btn btn-outline-secondary">Wallet</a>
      <a href="{{ route('admin.billing.plan') }}" class="btn btn-outline-secondary">Mi plan</a>
    </div>
  </div>

  {{-- Alerts --}}
  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

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

    {{-- Columna izquierda: métodos de pago --}}
    <div class="col-12 col-lg-5">
      <div class="card soft-card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <strong>Método de recarga</strong>
          <span class="badge bg-info">Prepaid</span>
        </div>

        <div class="card-body">
          <div class="mb-3">
            <div class="metric">
              Saldo actual: ${{ number_format($balance, 2) }} <span class="fs-6">MXN</span>
            </div>
            <div class="metric-sub">
              Última recarga: {{ $lastTopupAt }}
            </div>
          </div>

          {{-- Tabs --}}
          <ul class="nav nav-pills gap-2 mb-3" id="topupTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="tab-mp" data-bs-toggle="pill" data-bs-target="#pane-mp" type="button" role="tab">
                Mercado Pago
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-transfer" data-bs-toggle="pill" data-bs-target="#pane-transfer" type="button" role="tab">
                Transferencia
              </button>
            </li>
          </ul>

          <div class="tab-content" id="topupTabsContent">

            {{-- MercadoPago --}}
            <div class="tab-pane fade show active" id="pane-mp" role="tabpanel" aria-labelledby="tab-mp">
              <div class="alert alert-light mb-3">
                <div class="fw-semibold">Pago inmediato</div>
                <div class="hint text-muted">
                  Generamos un link de pago (Checkout Pro). El saldo se acredita automáticamente cuando el pago quede <span class="mono">approved</span> (webhook).
                </div>
              </div>

              <form method="POST" action="{{ route('admin.wallet.topup.store') }}">
                @csrf

                <div class="mb-3">
                  <label class="form-label">Monto (MXN)</label>
                  <input
                    type="number"
                    name="amount"
                    step="0.01"
                    min="{{ $minTopup }}"
                    max="200000"
                    class="form-control"
                    value="{{ old('amount') }}"
                    placeholder="Ej. 1500"
                    required
                  >
                  <div class="form-text">
                    Mínimo: ${{ number_format($minTopup, 0) }} MXN.
                  </div>
                </div>

                <button class="btn btn-primary w-100" type="submit">
                  Continuar a Mercado Pago
                </button>

                <div class="text-muted hint mt-2">
                  Podrás pagar con tarjeta y, según configuración de Mercado Pago, OXXO/SPEI.
                </div>
              </form>
            </div>

            {{-- Transferencia --}}
            <div class="tab-pane fade" id="pane-transfer" role="tabpanel" aria-labelledby="tab-transfer">
              <div class="alert alert-light mb-3">
                <div class="fw-semibold">Transferencia bancaria</div>
                <div class="hint text-muted">
                  Realiza la transferencia y luego notifica tu pago. Un administrador lo validará y acreditará el saldo.
                </div>
              </div>

              {{-- Datos bancarios --}}
              <div class="card soft-card mb-3">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <div class="text-muted small mb-1">Beneficiario</div>
                      <div class="fw-semibold">{{ $bank['beneficiary'] }}</div>
                    </div>
                    <span class="badge bg-secondary">SPEI</span>
                  </div>

                  <hr class="my-3">

                  <div class="row g-2 small">
                    <div class="col-12">
                      <div class="text-muted">Banco</div>
                      <div class="fw-semibold">{{ $bank['bank'] }}</div>
                    </div>
                    <div class="col-12">
                      <div class="text-muted">CLABE</div>
                      <div class="fw-semibold mono">{{ $bank['clabe'] }}</div>
                    </div>

                    @if(!empty($bank['account']))
                      <div class="col-12">
                        <div class="text-muted">Cuenta</div>
                        <div class="fw-semibold mono">{{ $bank['account'] }}</div>
                      </div>
                    @endif

                    <div class="col-12">
                      <div class="text-muted">Referencia sugerida</div>
                      <div class="fw-semibold mono">{{ $suggestedRef }}</div>
                    </div>

                    @if(!empty($bank['notes']))
                      <div class="col-12 text-muted">
                        {{ $bank['notes'] }}
                      </div>
                    @endif
                  </div>
                </div>
              </div>

              {{-- Notificar pago (placeholder real) --}}
              <form method="POST" action="{{ route('admin.wallet.transfer.notice.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-2">
                  <label class="form-label">Monto transferido (MXN)</label>
                  <input type="number" name="amount" step="0.01" min="{{ $minTopup }}" max="500000"
                         class="form-control" value="{{ old('amount') }}" required>
                </div>

                <div class="mb-2">
                  <label class="form-label">Referencia / Folio / Clave rastreo</label>
                  <input type="text" name="reference" class="form-control" value="{{ old('reference') }}"
                         placeholder="Ej. 1234567890 / rastreo SPEI" required>
                </div>

                <div class="mb-2">
                  <label class="form-label">Fecha de pago</label>
                  <input type="datetime-local" name="paid_at" class="form-control" value="{{ old('paid_at') }}">
                  <div class="form-text">Opcional, si no se indica, se tomará como “hoy”.</div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Comprobante (opcional)</label>
                  <input type="file" name="proof" class="form-control" accept="image/*,application/pdf">
                </div>

                <button class="btn btn-outline-primary w-100" type="submit">
                  Notificar transferencia
                </button>

                <div class="text-muted hint mt-2">
                  El saldo se acreditará cuando un administrador valide el pago.
                </div>
              </form>

            </div>

          </div>
        </div>
      </div>
    </div>

    {{-- Columna derecha: historial --}}
    <div class="col-12 col-lg-7">
      <div class="card soft-card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <strong>Historial de recargas</strong>
          <span class="text-muted small">Últimos 50</span>
        </div>

        <div class="card-body p-0">
          @include('admin.wallet._topups_table', ['topups' => ($topups ?? collect())])
        </div>

        <div class="card-footer text-muted small">
          Mercado Pago: el webhook actualiza <span class="mono">mp_status</span> y marca <span class="mono">credited_at</span> cuando se acredita.
          Transferencia: quedará en <span class="mono">pending_review</span> hasta validación.
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
