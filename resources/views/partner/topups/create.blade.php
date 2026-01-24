@extends('layouts.partner')

@section('content')
@push('styles')
<style>


  /* Tabler/Bootstrap: mejora contraste en alerts light */
  :root:not([data-bs-theme="dark"]) .alert.alert-light {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: rgba(15, 23, 42, .85);
  }
</style>
@endpush

<div class="container-fluid">

  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Finanzas</div>
        <h2 class="page-title">Nueva recarga</h2>
        <div class="text-muted">Sube tu comprobante para que Orbana lo valide y acredite saldo.</div>
      </div>
      <div class="col-auto ms-auto">
        <a class="btn btn-outline-secondary" href="{{ route('partner.topups.index') }}">
          <i class="ti ti-arrow-left me-1"></i> Volver
        </a>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">

      {{-- Banner info --}}
      <div class="alert alert-light mb-3">
        <div class="fw-semibold">Transferencia bancaria (SPEI/depósito)</div>
        <div class="text-muted small mb-1">
          1) Realiza la transferencia usando la referencia sugerida.
          2) Selecciona la cuenta destino.
          3) Sube tu comprobante.
          4) Orbana valida y acredita saldo.
        </div>
        @if(!empty($supportEmail))
          <div class="text-muted small">
            Soporte: <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
          </div>
        @endif
      </div>

      @php
        $refFallback = 'ORBANA-P'.(session('partner_id') ?: 'X').'-T'.($tid ?? (auth()->user()->tenant_id ?? 'X'));
        $ref = $suggestedRef ?? $refFallback;
        $accountsList = is_array($accounts ?? null) ? $accounts : [];
        $defaultAccId = $accountsList[0]['id'] ?? 'acc_1';
        $oldAcc = old('provider_account_slot', $defaultAccId);
      @endphp

      <form method="POST" action="{{ route('partner.topups.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="row g-3">

          {{-- Monto --}}
          <div class="col-md-6">
            <label class="form-label">Monto (MXN)</label>
            <input type="number" step="0.01" min="50" max="50000"
                   name="amount" value="{{ old('amount') }}"
                   class="form-control @error('amount') is-invalid @enderror" required>
            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          {{-- Método --}}
          <div class="col-md-6">
            <label class="form-label">Método</label>
            <input type="text" class="form-control" value="Transferencia" disabled>
            <input type="hidden" name="method" value="transfer">
          </div>

          {{-- Referencia sugerida --}}
          <div class="col-12">
            <div class="border rounded p-3">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                  <div class="text-muted small">Referencia sugerida</div>
                  <div class="fw-semibold font-monospace">{{ $ref }}</div>
                  <div class="text-muted small">Inclúyela en el concepto para agilizar la validación.</div>
                </div>
                <button type="button"
                        class="btn btn-outline-secondary btn-sm"
                        data-copy="{{ $ref }}">
                  Copiar referencia
                </button>
              </div>
            </div>
          </div>

          {{-- Selector de cuenta destino (obligatorio) --}}
          <div class="col-12">
            <label class="form-label">¿A qué cuenta depositaste?</label>

            <select name="provider_account_slot"
                    class="form-select @error('provider_account_slot') is-invalid @enderror"
                    required>
              <option value="" disabled {{ $oldAcc ? '' : 'selected' }}>
                Selecciona la cuenta destino
              </option>

              @foreach($accountsList as $i => $acc)
                @php
                  $accId   = $acc['id'] ?? null;      // ✅ acc_1 / acc_2
                  $label   = $acc['label'] ?? ('Cuenta '.($i+1));
                  $bank    = $acc['bank'] ?? '—';
                  $clabe   = $acc['clabe'] ?? '—';
                @endphp
                @if(!empty($accId))
                  <option value="{{ $accId }}" {{ (string)$oldAcc === (string)$accId ? 'selected' : '' }}>
                    {{ $label }} · {{ $bank }} · {{ $clabe }}
                  </option>
                @endif
              @endforeach
            </select>
            @error('provider_account_slot')<div class="invalid-feedback">{{ $message }}</div>@enderror

            {{-- Cards de cuentas (visual + copiar) --}}
            <div class="mt-3 row g-3">
              @forelse($accountsList as $i => $acc)
                @php
                  $accId     = $acc['id'] ?? null;     // ✅ acc_1 / acc_2
                  $accLabel  = $acc['label'] ?? ('Cuenta '.($i+1));
                  $benef     = $acc['beneficiary'] ?? '—';
                  $bankName  = $acc['bank'] ?? '—';
                  $clabe     = $acc['clabe'] ?? '—';
                  $accountNo = $acc['account'] ?? null;
                  $notes     = $acc['notes'] ?? null;

                  $copyText =
                    "Beneficiario: {$benef}\n".
                    "Banco: {$bankName}\n".
                    "CLABE: {$clabe}\n".
                    ($accountNo ? "Cuenta: {$accountNo}\n" : "").
                    "Referencia: {$ref}\n".
                    "Orbana";
                @endphp

                <div class="col-lg-6">
                  <div class="card h-100">
                    <div class="card-body">

                      <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                          <div class="fw-semibold">{{ $accLabel }}</div>
                          <div class="text-muted small">Cuenta destino para recarga.</div>
                        </div>
                        <span class="badge bg-secondary">SPEI</span>
                      </div>

                      <hr class="my-3">

                      <div class="mb-2">
                        <div class="text-muted small">Beneficiario</div>
                        <div class="fw-semibold">{{ $benef }}</div>
                      </div>

                      <div class="mb-2">
                        <div class="text-muted small">Banco</div>
                        <div class="fw-semibold">{{ $bankName }}</div>
                      </div>

                      <div class="mb-2 d-flex justify-content-between align-items-center gap-2">
                        <div>
                          <div class="text-muted small">CLABE</div>
                          <div class="fw-semibold font-monospace">{{ $clabe }}</div>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-copy="{{ $clabe }}">
                          Copiar CLABE
                        </button>
                      </div>

                      @if(!empty($accountNo))
                        <div class="mb-2 d-flex justify-content-between align-items-center gap-2">
                          <div>
                            <div class="text-muted small">Cuenta</div>
                            <div class="fw-semibold font-monospace">{{ $accountNo }}</div>
                          </div>
                          <button class="btn btn-outline-secondary btn-sm" type="button" data-copy="{{ $accountNo }}">
                            Copiar cuenta
                          </button>
                        </div>
                      @endif

                      @if(!empty($notes))
                        <div class="text-muted small mt-2">{{ $notes }}</div>
                      @endif

                      <div class="mt-3 d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-copy="{{ e($copyText) }}">
                          Copiar datos
                        </button>

                        @if(!empty($accId))
                          <button type="button"
                                  class="btn btn-primary btn-sm js-use-account"
                                  data-acc="{{ $accId }}">
                            Usar esta cuenta
                          </button>
                        @endif
                      </div>

                    </div>
                  </div>
                </div>
              @empty
                <div class="col-12">
                  <div class="alert alert-warning mb-0">
                    No hay cuentas de depósito configuradas. Contacta a soporte.
                  </div>
                </div>
              @endforelse
            </div>
          </div>

          {{-- Referencia bancaria opcional del comprobante --}}
          <div class="col-md-6">
            <label class="form-label">Referencia bancaria del comprobante (opcional)</label>
            <input type="text"
                   name="bank_ref"
                   value="{{ old('bank_ref') }}"
                   class="form-control @error('bank_ref') is-invalid @enderror"
                   placeholder="Ej: rastreo SPEI / folio / REF-12345">
            @error('bank_ref')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          {{-- Comprobante --}}
          <div class="col-md-6">
            <label class="form-label">Comprobante (opcional)</label>
            <input type="file"
                   name="proof"
                   class="form-control @error('proof') is-invalid @enderror"
                   accept=".jpg,.jpeg,.png,.pdf">
            <div class="form-hint">Formatos: JPG/PNG/PDF. Máx 4MB.</div>
            @error('proof')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

        </div>

        <div class="mt-4 d-flex gap-2">
          <button class="btn btn-primary">
            <i class="ti ti-send me-1"></i> Enviar a revisión
          </button>
          <a href="{{ route('partner.topups.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>

      </form>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
  // Copy-to-clipboard (sin dependencias)
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-copy]');
    if (btn) {
      const text = btn.getAttribute('data-copy') || '';
      if (!text) return;
      navigator.clipboard.writeText(text).then(() => {
        const old = btn.innerHTML;
        btn.innerHTML = 'Copiado';
        btn.disabled = true;
        setTimeout(() => { btn.innerHTML = old; btn.disabled = false; }, 900);
      }).catch(() => {});
      return;
    }

    // “Usar esta cuenta” -> setea el select y dispara change
    const use = e.target.closest('.js-use-account');
    if (use) {
      const acc = use.getAttribute('data-acc');
      const sel = document.querySelector('[name="provider_account_slot"]');
      if (sel && acc) {
        sel.value = acc;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
        sel.focus();
      }
    }
  });
</script>
@endpush
