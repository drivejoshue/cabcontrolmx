@extends('layouts.sysadmin_tabler')

@section('title','Verificación adicional')

@section('content')
@push('styles')
<style>
  .mfa-shell{
    max-width: 760px;
    margin: 18px auto 0;
  }
  .mfa-card{
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 16px;
    padding: 22px;
    box-shadow: 0 10px 30px rgba(0,0,0,.25);
  }
  .mfa-title{ font-size:18px; font-weight:700; margin:0 0 6px; }
  .mfa-sub{ font-size:13px; color:rgba(230,237,246,.75); margin:0 0 18px; line-height:1.35; }
  .mfa-row{ display:flex; gap:18px; align-items:flex-start; flex-wrap:wrap; }
  .mfa-qrbox{ width:260px; background:#fff; border-radius:12px; padding:10px; }
  .mfa-hint{ font-size:12px; color:rgba(230,237,246,.75); line-height:1.35; }
  .mfa-label{ display:block; font-size:12px; color:rgba(230,237,246,.75); margin:14px 0 6px; }
  .mfa-input{
    width:100%;
    padding:12px 12px;
    border-radius:12px;
    border:1px solid rgba(255,255,255,.14);
    background:rgba(255,255,255,.06);
    color:#e6edf6;
    outline:none;
    font-size:16px;
    letter-spacing:.08em;
  }
  .mfa-input:focus{
    border-color: rgba(0,204,255,.55);
    box-shadow:0 0 0 3px rgba(0,204,255,.15);
  }
  .mfa-btn{
    width:100%;
    margin-top:14px;
    padding:12px 14px;
    border-radius:12px;
    border:0;
    background:#00CCFF;
    color:#02131a;
    font-weight:800;
    cursor:pointer;
  }
  .mfa-btn:hover{ filter:brightness(1.05); }
  .mfa-err{
    background:rgba(255,80,80,.12);
    border:1px solid rgba(255,80,80,.25);
    color:#ffd1d1;
    padding:10px 12px;
    border-radius:12px;
    font-size:13px;
    margin-bottom:12px;
  }
  .mfa-pill{
    display:inline-block;
    font-size:11px;
    padding:4px 10px;
    border-radius:999px;
    border:1px solid rgba(255,255,255,.12);
    color:rgba(230,237,246,.8);
  }

  /* Timer */
  .mfa-timer{ display:flex; align-items:center; gap:10px; margin-top:12px; }
  .mfa-bar{ flex:1; height:8px; background:rgba(255,255,255,.10); border-radius:999px; overflow:hidden; }
  .mfa-bar > div{ height:100%; width:0%; background:rgba(0,204,255,.9); }
  .mfa-txt{ font-size:12px; color:rgba(230,237,246,.75); white-space:nowrap; }

  @media (max-width: 640px){
    .mfa-qrbox{ width:100%; max-width:320px; }
  }
</style>
@endpush

<div class="mfa-shell">
  <div class="mfa-card">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
      <div>
        <h1 class="mfa-title">Verificación adicional</h1>
        <p class="mfa-sub">
          Para acceder al panel SysAdmin se requiere un segundo factor (código de tu app Authenticator).
        </p>
      </div>
      <span class="mfa-pill">{{ $issuer }}</span>
    </div>

    @if ($errors->any())
      <div class="mfa-err">{{ $errors->first() }}</div>
    @endif

    @if ($mode === 'enroll')
      <div class="mfa-row">
        <div class="mfa-qrbox">
          @if(!empty($qrDataUri))
            <img src="{{ $qrDataUri }}" alt="QR TOTP" style="display:block;width:100%;height:auto;">
          @else
            <div style="padding:18px;color:#111;">No se pudo generar QR.</div>
          @endif
        </div>

        <div style="flex:1; min-width:260px;">
          <div class="mfa-hint">
            1) Abre Google Authenticator / Authy / Microsoft Authenticator.<br>
            2) Escanea el QR.<br>
            3) Ingresa el código de 6 dígitos para confirmar.
          </div>

          <div class="mfa-timer" aria-label="TOTP timer">
            <div class="mfa-bar"><div id="totpBar"></div></div>
            <div class="mfa-txt">cambia en <span id="totpSec">--</span>s</div>
          </div>

          <div class="mfa-hint" style="margin-top:8px;">
            El QR se muestra solo durante el enrolamiento.
          </div>
        </div>
      </div>
    @else
      <p class="mfa-sub" style="margin-top:-6px;">
        Cuenta: <strong>{{ $label }}</strong>
      </p>

      <div class="mfa-timer" aria-label="TOTP timer">
        <div class="mfa-bar"><div id="totpBar"></div></div>
        <div class="mfa-txt">cambia en <span id="totpSec">--</span>s</div>
      </div>
    @endif

    <form method="POST" action="{{ route('sysadmin.stepup.verify') }}" autocomplete="off">
      @csrf

      <label class="mfa-label" for="code">Código (6 dígitos)</label>
      <input
        id="code"
        name="code"
        class="mfa-input"
        inputmode="numeric"
        pattern="[0-9]*"
        maxlength="10"
        placeholder="123456"
        value="{{ old('code') }}"
        autofocus
        required
      >

      <button type="submit" class="mfa-btn">Continuar</button>

      <div class="mfa-sub" style="margin-top:10px;">
        Si falla, revisa que el reloj del dispositivo esté en automático.
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
(function () {
  // Solo números
  const el = document.getElementById('code');
  if (el) {
    el.addEventListener('input', () => {
      el.value = el.value.replace(/[^\d]/g, '').slice(0, 10);
    });
  }

  // Timer 30s sync
  const secEl = document.getElementById('totpSec');
  const barEl = document.getElementById('totpBar');

  function tick() {
    const now = Date.now();
    const stepMs = 30000;
    const inStep = now % stepMs;
    const leftMs = stepMs - inStep;
    const leftSec = Math.ceil(leftMs / 1000);

    if (secEl) secEl.textContent = String(leftSec);

    const pct = Math.min(100, Math.max(0, (inStep / stepMs) * 100));
    if (barEl) barEl.style.width = pct.toFixed(2) + '%';
  }

  tick();
  setInterval(tick, 250);
})();
</script>
@endpush
@endsection
