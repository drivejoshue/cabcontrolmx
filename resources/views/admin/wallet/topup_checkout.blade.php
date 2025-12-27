@extends('layouts.admin')

@section('title','Procesar pago')

@push('styles')
<style>
  .mp-wrap { max-width: 780px; margin: 0 auto; }
  .mp-card { border: 1px solid rgba(0,0,0,.08); border-radius: 14px; overflow: hidden; }
  [data-theme="dark"] .mp-card { border-color: rgba(255,255,255,.10); }
  .mp-hero { padding: 18px 18px 6px; }
  .mp-muted { opacity:.75; }
  .mp-actions { display:flex; gap:10px; flex-wrap:wrap; }
  .mp-spinner { width: 18px; height: 18px; border: 2px solid rgba(0,0,0,.15); border-top-color: rgba(0,0,0,.55); border-radius: 50%; animation: spin .8s linear infinite; display:inline-block; vertical-align: -3px; }
  [data-theme="dark"] .mp-spinner { border-color: rgba(255,255,255,.18); border-top-color: rgba(255,255,255,.75); }
  @keyframes spin { to { transform: rotate(360deg); } }
</style>
@endpush

@section('content')
<div class="container-fluid">
  <div class="mp-wrap">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="mb-0">Procesando recarga</h3>
        <div class="text-muted small">
          Recarga #{{ $topup->id }} · Ref:
          <span class="font-monospace">{{ $topup->external_reference }}</span>
        </div>
      </div>
      <a href="{{ route('admin.wallet.topup.create') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="card mp-card shadow-sm">
      <div class="mp-hero">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="fw-semibold">Completa tu pago con Mercado Pago</div>
            <div class="small mp-muted">
              Tu saldo se acreditará automáticamente cuando el pago quede
              <span class="font-monospace">approved</span> (webhook).
            </div>
          </div>
          <div class="text-end">
            <div class="fw-bold">${{ number_format((float)$topup->amount, 2) }} MXN</div>
            <div class="small mp-muted">{{ $topup->provider }}</div>
          </div>
        </div>
      </div>

      <div class="card-body">
        <div class="alert alert-light mb-3">
          <span class="mp-spinner me-2"></span>
          <span id="statusText">Preparando formulario de pago…</span>
          <div class="small text-muted mt-1" id="statusSub">
            En cuanto el formulario esté listo, podrás completar el pago sin salir de esta página.
          </div>
        </div>

        <div class="mp-actions mb-3">
          <button id="btnOpen" class="btn btn-primary">
            Pagar con Mercado Pago
          </button>

          <button id="btnRetry" class="btn btn-outline-primary d-none">
            Reintentar
          </button>

          <a class="btn btn-outline-secondary" href="{{ route('admin.wallet.topup.create') }}">
            Volver a Recargar saldo
          </a>
        </div>

        {{-- Aquí se renderiza el Wallet Brick --}}
        <div id="mp-wallet-container" class="border rounded-3 p-3 bg-body-tertiary"></div>

        <hr class="my-3">

        <div class="small text-muted">
          Estado local: <span class="font-monospace" id="localStatus">{{ $topup->status }}</span>
          · MP: <span class="font-monospace" id="mpStatus">{{ $topup->mp_status ?? '—' }}</span>
          · Acreditado: <span class="font-monospace" id="creditedAt">{{ $topup->credited_at ? $topup->credited_at->toDateTimeString() : '—' }}</span>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
{{-- SDK JS v2 de Mercado Pago --}}
<script src="https://sdk.mercadopago.com/js/v2"></script>

<script>
(function () {
  const mpPublicKey  = @json($mpPublicKey);
  const preferenceId = @json($preferenceId);

  const statusUrl = @json(route('admin.wallet.topup.status', ['topup' => $topup->id]));
  const doneUrl   = @json(route('admin.wallet.topup.create'));

  const $statusText  = document.getElementById('statusText');
  const $statusSub   = document.getElementById('statusSub');
  const $localStatus = document.getElementById('localStatus');
  const $mpStatus    = document.getElementById('mpStatus');
  const $creditedAt  = document.getElementById('creditedAt');

  const $btnOpen  = document.getElementById('btnOpen');
  const $btnRetry = document.getElementById('btnRetry');

  let tries = 0;
  let walletInitialized = false;

  // ==============================
  // Mercado Pago Bricks: Wallet
  // ==============================
  const mp = new MercadoPago(mpPublicKey, {
    locale: 'es-MX'
  });

  const bricksBuilder = mp.bricks();

  function renderWallet() {
    if (walletInitialized) return;

    const container = document.getElementById('mp-wallet-container');
    if (!container) return;

    bricksBuilder.create('wallet', 'mp-wallet-container', {
      initialization: {
        preferenceId: preferenceId
      },
      customization: {
        visual: {
          style: {
            theme: document.documentElement.getAttribute('data-theme') === 'dark'
              ? 'dark'
              : 'default'
          }
        }
      },
      callbacks: {
        onReady: () => {
          walletInitialized = true;
          $statusText.textContent = 'Formulario de pago listo.';
          $statusSub.textContent  = 'Completa el pago en el formulario de Mercado Pago. Esta página se actualizará al confirmarse el pago.';
        },
        onError: (error) => {
          console.error('MP Wallet error', error);
          $statusText.textContent = 'Error al cargar el formulario de pago.';
          $statusSub.textContent  = 'Intenta de nuevo o revisa la configuración de Mercado Pago.';
          $btnRetry.classList.remove('d-none');
        }
      }
    }).catch((e) => {
      console.error('MP Wallet create exception', e);
      $statusText.textContent = 'Error al inicializar el formulario de pago.';
      $statusSub.textContent  = 'Intenta de nuevo. Si persiste, contacta al administrador.';
      $btnRetry.classList.remove('d-none');
    });
  }

  // ==============================
  // Polling de estado en backend
  // ==============================
  async function poll() {
    try {
      const r = await fetch(statusUrl, { credentials: 'same-origin' });
      const j = await r.json();

      if (!j || !j.ok) return;

      $localStatus.textContent = j.status ?? '—';
      $mpStatus.textContent    = j.mp_status ?? '—';
      $creditedAt.textContent  = j.credited_at ?? '—';

      if (j.credited_at) {
        // Pago acreditado: redirige al listado mostrando éxito
        window.location.href = doneUrl + '?credited=1';
        return;
      }

      const mpStatus = (j.mp_status || '').toLowerCase();
      if (mpStatus === 'rejected' || mpStatus === 'cancelled' || mpStatus === 'canceled') {
        $statusText.textContent = 'El pago no se completó.';
        $statusSub.textContent  = 'Puedes intentar nuevamente abriendo el formulario de pago.';
        $btnRetry.classList.remove('d-none');
      }
    } catch (e) {
      console.debug('poll error', e);
    } finally {
      tries++;
      if (tries < 200) setTimeout(poll, 3000); // ~10 minutos
    }
  }

  // ==============================
  // Eventos de UI
  // ==============================
  function openCheckout() {
    renderWallet();

    const container = document.getElementById('mp-wallet-container');
    if (container && typeof container.scrollIntoView === 'function') {
      container.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  if ($btnOpen)  $btnOpen.addEventListener('click', openCheckout);
  if ($btnRetry) $btnRetry.addEventListener('click', openCheckout);

  // Auto inicializar al entrar (como antes auto-abrías el popup)
  setTimeout(openCheckout, 450);
  setTimeout(poll, 1200);

})();
</script>
@endpush
