
<!doctype html>
<html lang="es" data-theme="default" data-layout="fluid">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="page-id" content="@yield('page-id','onboarding')">

  <title>{{ config('app.name') }} · @yield('title','Onboarding')</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        referrerpolicy="no-referrer" />
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"/>

  <link id="themeStylesheet"
        rel="stylesheet"
        href="{{ asset('assets/adminkit/light.css') }}"
        data-light="{{ asset('assets/adminkit/light.css') }}"
        data-dark="{{ asset('assets/adminkit/dark.css') }}">

  @vite(['resources/css/app.css','resources/js/app.js','resources/js/adminkit.js'])

  @stack('styles')

  <style>
    html, body { height: 100%; }
    body { opacity: 1 !important; visibility: visible !important; font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }

    /* Shell full height: footer al fondo */
    .onb-shell{
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: var(--bs-body-bg);
    }

    .onb-topbar{
      position: sticky;
      top: 0;
      z-index: 1020;
      background: var(--bs-body-bg);
      border-bottom: 1px solid rgba(0,0,0,.08);
    }
    [data-theme="dark"] .onb-topbar{ border-bottom-color: rgba(255,255,255,.08); }

    .onb-main{
      flex: 1 1 auto;
      padding: 1.25rem 0;
    }

    /* Contenedor: ya no “boxed” */
    .onb-container{
      width: 100%;
      max-width: 100%;
      padding-left: 1.25rem;
      padding-right: 1.25rem;
    }
    @media (min-width: 1400px){
      .onb-container{ padding-left: 2rem; padding-right: 2rem; }
    }

    /* Footer */
    .onb-footer{
      flex: 0 0 auto;
      border-top: 1px solid rgba(0,0,0,.08);
    }
    [data-theme="dark"] .onb-footer{ border-top-color: rgba(255,255,255,.08); }

    /* Brand */
    .onb-brand{
      display:flex; align-items:center; gap:.6rem;
      font-weight: 700;
      letter-spacing: .02em;
    }
    .onb-logo{
      width: 28px; height: 28px; object-fit: contain;
    }

    /* Evitar que Leaflet se meta debajo de overlays */
    .leaflet-container { font-family: inherit; }
  </style>
</head>

<body class="onboarding-page">
<div class="onb-shell">

  <div class="onb-topbar">
    <div class="onb-container py-2 d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-2">
        <div class="onb-brand">
          <img class="onb-logo" src="{{ asset('images/logo.png') }}" alt="Orbana">
          <span>Orbana</span>
        </div>
        <span class="text-muted small d-none d-md-inline">· Configuración inicial</span>
      </div>

      <div class="small text-muted text-truncate" style="max-width: 45vw;">
        {{ auth()->user()->email ?? '' }}
      </div>
    </div>
  </div>

  <main class="onb-main">
    <div class="onb-container">
      @yield('content')
    </div>
  </main>

  <footer class="onb-footer py-3">
    <div class="onb-container d-flex justify-content-between text-muted small">
      <div><strong>{{ config('app.name') }}</strong> &copy; {{ date('Y') }}</div>
      <div>v1</div>
    </div>
  </footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.feather) window.feather.replace();
});
</script>

@stack('scripts')
</body>
</html>
