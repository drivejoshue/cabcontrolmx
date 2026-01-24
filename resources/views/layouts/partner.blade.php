

<!doctype html>
<html lang="es" data-bs-theme="dark" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="page-id" content="@yield('page-id','partner')">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>{{ config('app.name') }} · @yield('title','Partner')</title>

  {{-- Inter --}}
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

  {{-- FontAwesome + Bootstrap Icons (compat) --}}
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        referrerpolicy="no-referrer" />
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />

  {{-- Tabler Core (UNA SOLA VEZ) --}}
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/css/tabler.min.css">

        

  {{-- Tabler Icons (iconfont) - para clases: ti ti-... --}}
 <link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css">
<style>
    /* Fuerza el texto blanco en badges con bg-* */
    .badge.bg-primary,
    .badge.bg-secondary,
    .badge.bg-success,
    .badge.bg-danger,
    .badge.bg-warning,
    .badge.bg-info,
    .badge.bg-dark {
      color: #fff !important;
      --tblr-badge-color: #fff;
    }

    /* Si algún badge trae links o iconos dentro */
    .badge.bg-primary *,
    .badge.bg-secondary *,
    .badge.bg-success *,
    .badge.bg-danger *,
    .badge.bg-warning *,
    .badge.bg-info *,
    .badge.bg-dark * {
      color: inherit !important;
    }

    /* Excepción: bg-light debe ser oscuro */
    .badge.bg-light {
      color: #111827 !important;
      --tblr-badge-color: #111827;
    }
    .badge.bg-light * { color: inherit !important; }


    .pm-wrap{ position:relative; height: calc(100vh - 120px); }
.pm-map{ position:absolute; inset:0; border-radius:14px; overflow:hidden; }
.pm-dock{
  position:absolute; top:12px; right:12px; width:360px; max-height: calc(100% - 24px);
  display:flex; flex-direction:column; gap:10px;
  background: rgba(255,255,255,.86);
  border: 1px solid rgba(20,30,40,.10);
  box-shadow: 0 10px 30px rgba(0,0,0,.10);
  border-radius: 14px; padding:12px;
  backdrop-filter: blur(6px);
}
[data-theme="dark"] .pm-dock{
  background: rgba(18,22,28,.72);
  border-color: rgba(255,255,255,.08);
}
.pm-dock-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; }
.pm-title{ font-weight:700; letter-spacing:.2px; }
.pm-section-title{ font-weight:700; font-size:.9rem; display:flex; justify-content:space-between; }
.pm-row{ display:flex; align-items:center; justify-content:space-between; gap:10px; padding:8px 8px; border-radius:12px; }
.pm-row:hover{ background: rgba(0,0,0,.04); }
[data-theme="dark"] .pm-row:hover{ background: rgba(255,255,255,.06); }
.pm-eco{ font-weight:700; }
.pm-sub{ font-size:.82rem; opacity:.85; }
 
  </style>
@stack('head')

  @stack('styles')
</head>

<body class="layout-fluid">
  <div class="page">
    @include('partner.partials.sidebar')

    <div class="page-wrapper">
      @include('partner.partials.nav')

      <div class="page-body">
        <div class="container-xl">
@php($gate = request()->attributes->get('partner_billing_gate'))

@if($gate && ($gate['state'] ?? 'ok') !== 'ok')
  @php($state = $gate['state'])
  <div class="alert {{ $state==='blocked' ? 'alert-danger' : 'alert-warning' }} mb-3">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <strong>
          {{ $state==='blocked'
              ? 'Cuenta bloqueada por adeudo'
              : 'Saldo insuficiente: Porfavor agrega fondos a tu Wallet para continuar. Esta alerta se mostrará durante 5 días; posteriormente tu acceso será restringido de manera automática.' }}
        </strong>

        <div class="small mt-1">
          Primer día no cubierto: {{ $gate['since'] ?? '-' }} |
          Días vencidos: {{ (int)($gate['days_past_due'] ?? 0) }} |
          {{ $state==='blocked'
              ? 'Acceso limitado: solo puedes reportar recargas para desbloquear.'
              : 'Te quedan '.(int)($gate['grace_left'] ?? 0).' día(s) antes del bloqueo.' }}
        </div>
      </div>

      <div class="ms-3">
        @if($state==='blocked')
          <a class="btn btn-sm btn-danger" href="{{ route('partner.topups.create') }}">
            Reportar recarga
          </a>
        @else
          <a class="btn btn-sm btn-warning" href="{{ route('partner.topups.create') }}">
            Recargar ahora
          </a>
        @endif
      </div>
    </div>
  </div>
@endif

@if(session('error'))
  <div class="alert alert-danger mb-3">{{ session('error') }}</div>
@endif

@if(session('status'))
  <div class="alert alert-success mb-3">{{ session('status') }}</div>
@endif





          @yield('content')
        </div>
      </div>

      <footer class="footer footer-transparent d-print-none">
        <div class="container-xl">
          <div class="row text-center align-items-center flex-row-reverse">
            <div class="col-lg-auto ms-lg-auto">
              <ul class="list-inline list-inline-dots mb-0">
                <li class="list-inline-item"><span class="text-muted">v1</span></li>
              </ul>
            </div>
            <div class="col-12 col-lg-auto mt-3 mt-lg-0">
              <ul class="list-inline list-inline-dots mb-0">
                <li class="list-inline-item">
                  <strong>{{ config('app.name') }}</strong> &copy; {{ date('Y') }}
                </li>
              </ul>
            </div>
          </div>
        </div>
      </footer>
    </div>
  </div>

  {{-- Tabler JS (UNA SOLA VEZ) --}}
  <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/js/tabler.min.js"></script>

  {{-- Feather (si aún usas data-feather en algunas vistas) --}}
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (window.feather) window.feather.replace();
    });
  </script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  @stack('scripts')




  @php($gate = request()->attributes->get('partner_billing_gate'))

@if($gate && ($gate['state'] ?? 'ok') !== 'ok')
  @php($state = $gate['state'])
  <div class="alert {{ $state==='blocked' ? 'alert-danger' : 'alert-warning' }} mb-3">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <strong>
          {{ $state==='blocked' ? 'Cuenta bloqueada por adeudo' : 'Saldo insuficiente: Porfavor agrega fondos a tu Wallet para continuar esta alerta se mostrara durante 5 dias, porteriormente tu acceso sera restringido de manera automatica.' }}
        </strong>
        <div class="small mt-1">
          Pendiente desde: {{ $gate['since'] ?? '-' }} |
          Días vencidos: {{ (int)($gate['days_past_due'] ?? 0) }} |
          {{ $state==='blocked'
              ? 'Acceso limitado: solo puedes reportar recargas para desbloquear.'
              : 'Te quedan '.(int)($gate['grace_left'] ?? 0).' día(s) antes del bloqueo.' }}
        </div>
      </div>

      <div class="ms-3">
        @if($state==='blocked')
          <a class="btn btn-sm btn-danger" href="{{ route('partner.topups.create') }}">
            Reportar recarga
          </a>
        @else
          <a class="btn btn-sm btn-warning" href="{{ route('partner.topups.create') }}">
            Recargar ahora
          </a>
        @endif
      </div>
    </div>
  </div>
@endif

@if(session('error'))
  <div class="alert alert-danger mb-3">{{ session('error') }}</div>
@endif
@if(session('status'))
  <div class="alert alert-success mb-3">{{ session('status') }}</div>
@endif
</body>
</html>
