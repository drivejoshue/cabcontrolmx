<!doctype html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="page-id" content="@yield('page-id','admin')">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>{{ config('app.name') }} · @yield('title','Admin')</title>

  {{-- Inter (Tabler usa Inter por defecto, pero lo dejamos explícito) --}}
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

  {{-- Tabler CSS (incluye Bootstrap 5 + estilos Tabler) --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">

  {{-- Tabler Icons (opcional pero recomendado) --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

  {{-- Si quieres mantener FontAwesome por ahora, puedes dejarlo.
       Recomendación: usa 1 set de iconos para no mezclar. --}}
  {{-- <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        referrerpolicy="no-referrer" /> --}}

  @stack('styles')
</head>

<body class="layout-fluid">
  <div class="page">
    {{-- Sidebar --}}
    @include('partials.sidebar_sysadmin_tabler')

    <div class="page-wrapper">
      {{-- Topbar --}}
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

  {{-- Tabler JS (incluye Bootstrap bundle) --}}
  <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>

  {{-- Tu JS propio (si lo necesitas en admin) --}}
  @stack('scripts')
</body>
</html>
