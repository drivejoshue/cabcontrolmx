@extends('layouts.admin')

@section('title','Recargar saldo')

@push('styles')
<style>
  .soft-card { border: 1px solid rgba(0,0,0,.06); }
  [data-bs-theme="dark"] .soft-card { border-color: rgba(255,255,255,.10); }

  .metric { font-size: 1.45rem; font-weight: 800; letter-spacing: -.02em; }
  .metric-sub { font-size: .85rem; color: rgba(0,0,0,.55); }
  [data-bs-theme="dark"] .metric-sub { color: rgba(255,255,255,.65); }

  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
  .nav-pills .nav-link { border-radius: 999px; }
  .hint { font-size: .85rem; }

  .copy-btn { white-space: nowrap; }
  .kv { font-size: .85rem; }
  .kv .k { color: rgba(0,0,0,.55); }
  [data-bs-theme="dark"] .kv .k { color: rgba(255,255,255,.65); }
  .kv .v { font-weight: 700; }
</style>
@endpush

@section('content')
@php
  $tenant = auth()->user()->tenant;
  $tid = auth()->user()->tenant_id;

  $supportEmail = 'soporte@orbana.mx';

  $balance = (float)($wallet->balance ?? 0);
  $lastTopupAt = !empty($wallet->last_topup_at)
    ? \Carbon\Carbon::parse($wallet->last_topup_at)->toDateTimeString()
    : '—';

  $minTopup = (int)($minTopup ?? 200);

  $fallbackAccount = [
    'id'          => 'acc_1',
    'label'       => 'Cuenta 1',
    'beneficiary' => config('billing.transfer_beneficiary', '—'),
    'bank'        => config('billing.transfer_bank', '—'),
    'clabe'       => config('billing.transfer_clabe', '—'),
    'account'     => config('billing.transfer_account', null),
    'notes'       => config('billing.transfer_notes', null),
  ];

  $bankAccounts = $bankAccounts ?? null;

  $accounts = (is_array($bankAccounts) && count($bankAccounts) > 0)
    ? $bankAccounts
    : [$fallbackAccount];

  $suggestedRef = 'ORBANA-T'.$tid;

  $defaultAccountId = old('account_id') ?? ($accounts[0]['id'] ?? 'acc_1');

  // datetime-local necesita formato Y-m-d\TH:i
  $oldPaidAt = old('paid_at');
  $paidAtValue = $oldPaidAt ? \Carbon\Carbon::parse($oldPaidAt)->format('Y-m-d\TH:i') : '';
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

    {{-- Columna izquierda --}}
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
                  <div class="form-text">Mínimo: ${{ number_format($minTopup, 0) }} MXN.</div>
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
                <div class="fw-semibold">Transferencia bancaria (SPEI/depósito)</div>
                <div class="hint text-muted mb-1">
                  1) Realiza la transferencia con la referencia sugerida. 2) Notifica tu pago aquí. 3) Un administrador valida y acredita saldo.
                </div>
                <div class="hint text-muted">
                  Soporte: <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                </div>
              </div>

              {{-- Datos bancarios --}}
              <div class="d-flex flex-column gap-2 mb-3">
                @foreach($accounts as $acc)
                  @php
                    $accLabel = $acc['label'] ?? 'Cuenta';
                    $benef = $acc['beneficiary'] ?? '—';
                    $bankName = $acc['bank'] ?? '—';
                    $clabe = $acc['clabe'] ?? '—';
                    $accountNo = $acc['account'] ?? null;
                    $notes = $acc['notes'] ?? null;

                    $copyText =
                      "Beneficiario: {$benef}\n".
                      "Banco: {$bankName}\n".
                      "CLABE: {$clabe}\n".
                      ($accountNo ? "Cuenta: {$accountNo}\n" : "").
                      "Referencia: {$suggestedRef}\n".
                      "Tenant: {$tid}";
                  @endphp

                  <div class="card soft-card">
                    <div class="card-body">
                      <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                          <div class="fw-semibold">{{ $accLabel }}</div>
                          <div class="text-muted small">Usa esta cuenta para recargar tu wallet.</div>
                        </div>
                        <span class="badge bg-secondary">SPEI</span>
                      </div>

                      <hr class="my-3">

                      <div class="kv">
                        <div class="mb-2">
                          <div class="k">Beneficiario</div>
                          <div class="v">{{ $benef }}</div>
                        </div>

                        <div class="mb-2">
                          <div class="k">Banco</div>
                          <div class="v">{{ $bankName }}</div>
                        </div>

                        <div class="mb-2 d-flex justify-content-between align-items-center gap-2">
                          <div>
                            <div class="k">CLABE</div>
                            <div class="v mono">{{ $clabe }}</div>
                          </div>
                          <button class="btn btn-outline-secondary btn-sm copy-btn"
                                  type="button"
                                  data-copy="{{ $clabe }}">
                            Copiar CLABE
                          </button>
                        </div>

                        @if(!empty($accountNo))
                          <div class="mb-2 d-flex justify-content-between align-items-center gap-2">
                            <div>
                              <div class="k">Cuenta</div>
                              <div class="v mono">{{ $accountNo }}</div>
                            </div>
                            <button class="btn btn-outline-secondary btn-sm copy-btn"
                                    type="button"
                                    data-copy="{{ $accountNo }}">
                              Copiar cuenta
                            </button>
                          </div>
                        @endif

                        <div class="mb-2 d-flex justify-content-between align-items-center gap-2">
                          <div>
                            <div class="k">Referencia sugerida</div>
                            <div class="v mono">{{ $suggestedRef }}</div>
                          </div>
                          <button class="btn btn-outline-primary btn-sm copy-btn"
                                  type="button"
                                  data-copy="{{ $suggestedRef }}">
                            Copiar referencia
                          </button>
                        </div>

                        @if(!empty($notes))
                          <div class="text-muted small mt-2">{{ $notes }}</div>
                        @endif

                        <div class="mt-3 d-flex gap-2 flex-wrap">
                          <button class="btn btn-outline-secondary btn-sm copy-btn"
                                  type="button"
                                  data-copy="{{ $copyText }}">
                            Copiar datos completos
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                @endforeach
              </div>

              {{-- Notificar pago --}}
              <div class="card soft-card">
                <div class="card-body">
                  <div class="fw-semibold mb-1">Notificar transferencia</div>
                  <div class="text-muted hint mb-3">
                    Tu notificación quedará “en revisión”. Te avisaremos por correo cuando se acredite el saldo.
                  </div>

                  <form
                    id="transferNoticeForm"
                    method="POST"
                    action="{{ route('admin.wallet.transfer.notice.store') }}"
                    enctype="multipart/form-data"
                  >
                    @csrf

                    @if(count($accounts) > 1)
                      <div class="mb-2">
                        <label class="form-label">Cuenta destino</label>
                        <select name="account_id" class="form-select">
                          @foreach($accounts as $acc)
                            @php $accId = $acc['id'] ?? 'acc_1'; @endphp
                            <option value="{{ $accId }}" {{ (string)$defaultAccountId === (string)$accId ? 'selected' : '' }}>
                              {{ $acc['label'] ?? $accId }} — {{ $acc['bank'] ?? 'Banco' }} · {{ $acc['clabe'] ?? '' }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-text">Selecciona la cuenta a la que realizaste la transferencia.</div>
                      </div>
                    @else
                      <input type="hidden" name="account_id" value="{{ $defaultAccountId }}">
                    @endif

                    <div class="mb-2">
                      <label class="form-label">Monto transferido (MXN)</label>
                      <input
                        type="number"
                        name="amount"
                        step="0.01"
                        min="{{ $minTopup }}"
                        max="500000"
                        class="form-control"
                        value="{{ old('amount') }}"
                        required
                      >
                    </div>

                    <div class="mb-2">
                      <label class="form-label">Referencia / Folio / Clave rastreo</label>
                      <input
                        type="text"
                        name="reference"
                        class="form-control"
                        value="{{ old('reference') }}"
                        placeholder="Ej. clave rastreo SPEI, folio, etc."
                        required
                      >
                      <div class="form-text">
                        Sugerencia: incluye también tu referencia <span class="mono">{{ $suggestedRef }}</span> en el concepto si tu banco lo permite.
                      </div>
                    </div>

                    <div class="mb-2">
                      <label class="form-label">Fecha y hora del pago (opcional)</label>
                      <input type="datetime-local" name="paid_at" class="form-control" value="{{ $paidAtValue }}">
                      <div class="form-text">Si no se indica, se tomará como “hoy”.</div>
                    </div>

                    <div class="mb-3">
                      <label class="form-label">Comprobante (opcional)</label>
                      <input type="file" name="proof" class="form-control" accept="image/*,application/pdf">
                      <div class="form-text">JPG/PNG/PDF · recomendado para acelerar validación.</div>
                    </div>

                    <button class="btn btn-outline-primary w-100" type="submit">
                      Notificar transferencia
                    </button>

                    <div class="text-muted hint mt-2">
                      Si pasan más de 24h hábiles sin acreditación, contacta a <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.
                    </div>
                  </form>

                </div>
              </div>
            </div>

          </div>{{-- /tab-content --}}
        </div>{{-- /card-body --}}
      </div>{{-- /card --}}
    </div>{{-- /col-left --}}

    {{-- Columna derecha --}}
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
    </div>{{-- /col-right --}}

  </div>{{-- /row --}}
</div>{{-- /container-fluid --}}
@endsection

@push('scripts')
<script>
(function () {
  function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
  }

  async function copyText(text) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch (e) {
      try { fallbackCopy(text); return true; } catch (_) { return false; }
    }
  }

  document.querySelectorAll('[data-copy]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const text = btn.getAttribute('data-copy') || '';
      const ok = await copyText(text);
      const old = btn.textContent;

      btn.textContent = ok ? 'Copiado' : 'Error';
      setTimeout(() => btn.textContent = old, 900);
    });
  });

  // Debug temporal (quita después): confirma que el submit se dispara
  const f = document.getElementById('transferNoticeForm');
  if (f) {
    f.addEventListener('submit', () => console.log('TRANSFER NOTICE: submit event fired'));
  }
})();
</script>
@endpush
