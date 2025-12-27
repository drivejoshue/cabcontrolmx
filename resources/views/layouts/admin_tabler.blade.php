<!doctype html>
<html lang="es" data-bs-theme="dark" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="page-id" content="@yield('page-id','admin')">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>{{ config('app.name') }} · @yield('title','Admin')</title>

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
  </style>

  @stack('styles')
</head>

<body class="layout-fluid">
  <div class="page">
    @include('partials.sidebar_tabler')

    <div class="page-wrapper">
      @include('partials.topbar_tabler')

      <div class="page-body">
        <div class="container-xl">
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

  @stack('scripts')
</body>
</html>
