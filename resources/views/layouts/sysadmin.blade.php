{{-- resources/views/layouts/sysadmin_tabler.blade.php --}}
<!doctype html>
<html lang="es" data-bs-theme="dark" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="page-id" content="@yield('page-id','sysadmin')">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ config('app.name') }} · @yield('title','SysAdmin')</title>

  {{-- Inter --}}
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

  {{-- Iconos opcionales (FontAwesome / Bootstrap Icons / Tabler Icons) --}}
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css"/>

  {{-- Tabler Core (CSS) --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/css/tabler.min.css"/>

  {{-- Tu build (opcional): --}}
  @vite(['resources/css/app.css','resources/js/app.js'])

  {{-- Ajustes rápidos de estilo para dark + badges --}}
  <style>
    /* Asegurar contraste en badges en tema oscuro */
    .badge.bg-primary,
    .badge.bg-secondary,
    .badge.bg-success,
    .badge.bg-danger,
    .badge.bg-warning,
    .badge.bg-info,
    .badge.bg-dark { color:#fff !important; --tblr-badge-color:#fff; }
    .badge.bg-light { color:#111827 !important; --tblr-badge-color:#111827; }

    /* Marca lateral (si usas logo/brand custom) */
    .sa-brand { font-weight:700; letter-spacing:.02em; }
    /* Contenedor fluido (equivalente a layout fluid del original) */
    body.layout-fluid .container-xl { max-width: 100%; }
  </style>

  @stack('styles')
</head>

<body class="layout-fluid">
<div class="page">
  {{-- SIDEBAR SysAdmin (versión Tabler). Crea este parcial si no existe aún. --}}
  @include('partials.sidebar_sysadmin_tabler')

  <div class="page-wrapper">
    {{-- TOPBAR Tabler (puedes reutilizar uno existente o crear uno específico para SysAdmin) --}}
    @include('partials.topbar_sysadmin_tabler')

    {{-- CONTENIDO --}}
    <div class="page-body">
      <div class="container-xl">
        @yield('content')
      </div>
    </div>

    {{-- FOOTER --}}
    <footer class="footer footer-transparent d-print-none">
      <div class="container-xl">
        <div class="row text-center align-items-center flex-row-reverse">
          <div class="col-lg-auto ms-lg-auto">
            <ul class="list-inline list-inline-dots mb-0">
              <li class="list-inline-item"><span class="text-muted">SysAdmin · v1</span></li>
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

{{-- Tabler JS --}}
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/js/tabler.min.js"></script>

{{-- Feather (si todavía hay vistas con data-feather) --}}
<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.feather) window.feather.replace();
  });
</script>

@stack('scripts')
</body>
</html>
