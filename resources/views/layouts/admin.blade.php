<?php
/** Layout base: sidebar + topbar + content */
?>
@push('styles')
<style>
/* ===== Only DARK theme ===== */
[data-theme="dark"] .sidebar{
  background: #0b1220 !important;           /* base slate casi negro */
  border-right: 1px solid #111827 !important;
}

/* Marca / título de la app */
[data-theme="dark"] .sidebar .sidebar-brand-text{
  color: #a5b4fc !important;                /* indigo-300 */
  font-weight: 700;
}

/* Secciones (encabezados “Panel”, “Operación”…) */
[data-theme="dark"] .sidebar .sidebar-header{
  color: #7dd3fc !important;                /* sky-300 */
  letter-spacing: .06em;
}

/* Links del sidebar (texto azul sobrio) */
[data-theme="dark"] .sidebar .sidebar-item .sidebar-link{
  color: #93c5fd !important;                /* sky-300 */
  opacity: 1 !important;
  background: transparent !important;
}

/* Hover: azul un poco más brillante */
[data-theme="dark"] .sidebar .sidebar-item .sidebar-link:hover{
  color: #bfdbfe !important;                /* sky-200 */
  background: rgba(29, 78, 216, .12) !important; /* indigo-600 @ 12% */
}

/* Activo: resalte azul + fondo sutil */
[data-theme="dark"] .sidebar .sidebar-item.active > .sidebar-link{
  color: #60a5fa !important;                /* blue-400 */
  background: rgba(37, 99, 235, .18) !important; /* blue-600 @ 18% */
  font-weight: 600;
  box-shadow: inset 0 0 0 1px rgba(59,130,246,.25);
  border-radius: .5rem;
}

/* Íconos (feather) que hereden el color del link */
[data-theme="dark"] .sidebar .sidebar-item .sidebar-link [data-feather],
[data-theme="dark"] .sidebar .sidebar-item .sidebar-link i,
[data-theme="dark"] .sidebar .sidebar-item .sidebar-link svg.feather{
  color: currentColor !important;
  stroke: currentColor !important;
  opacity: 1 !important;
}

/* Logo oscuro por si usas versiones light/dark */
[data-theme="dark"] .logo-dark  { display: inline !important; }
[data-theme="dark"] .logo-light { display: none  !important; }
</style>
@endpush

<!doctype html>
<html lang="es" data-theme="default" data-layout="fluid" data-sidebar-position="left" data-sidebar-layout="default">
<head>
  <meta charset="utf-8">
  <meta name="page-id" content="dashboard">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ config('app.name') }} · @yield('title','Admin') </title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
<script src="https://unpkg.com/feather-icons"></script> 
   <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>

   <link
  rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
/>

  <!-- Tema AdminKit: servido estático desde /public -->
 <link
  id="themeStylesheet"
  rel="stylesheet"
  href="{{ asset('assets/adminkit/light.css') }}"
  data-light="{{ asset('assets/adminkit/light.css') }}"
  data-dark="{{ asset('assets/adminkit/dark.css') }}"
>

@vite(['resources/css/app.css','resources/js/app.js','resources/js/adminkit.js'])

  @stack('styles')
</head>
<body>
<div class="wrapper">
  {{-- SIDEBAR --}}
  @include('partials.sidebar_adminkit')

  <div class="main">
    {{-- TOPBAR --}}
    @include('partials.topbar_adminkit')

    <main class="content">
      <div class="container-fluid p-0">
        @yield('content')
      </div>
    </main>

    <footer class="footer">
      <div class="container-fluid">
        <div class="row text-muted">
          <div class="col-6 text-start">
            <p class="mb-0"><strong>{{ config('app.name') }}</strong> &copy; {{ date('Y') }}</p>
          </div>
          <div class="col-6 text-end"><small class="text-muted">v1</small></div>
        </div>
      </div>
    </footer>
  </div>
</div>

@stack('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
