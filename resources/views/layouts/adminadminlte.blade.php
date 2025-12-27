@push('styles')
<style>
/* ===== Only DARK theme (compat: tu estilo original) ===== */
[data-theme="dark"]{
  color-scheme: dark;
}

/* Sidebar Tabler: usa navbar-vertical */
[data-theme="dark"] .navbar-vertical{
  background: #0b1220 !important;
  border-right: 1px solid #111827 !important;
}

/* Branding (tu look) */
[data-theme="dark"] .navbar-brand{
  color: #a5b4fc !important;
  font-weight: 800;
}
[data-theme="dark"] .navbar-brand small{
  color: rgba(125, 211, 252, .9) !important;
  letter-spacing: .06em;
}

/* Links */
[data-theme="dark"] .navbar-vertical .nav-link{
  color: #93c5fd !important;
  opacity: 1 !important;
  background: transparent !important;
}
[data-theme="dark"] .navbar-vertical .nav-link:hover{
  color: #bfdbfe !important;
  background: rgba(29, 78, 216, .12) !important;
  border-radius: .5rem;
}

/* Active */
[data-theme="dark"] .navbar-vertical .nav-item.active > .nav-link{
  color: #60a5fa !important;
  background: rgba(37, 99, 235, .18) !important;
  font-weight: 600;
  box-shadow: inset 0 0 0 1px rgba(59,130,246,.25);
  border-radius: .5rem;
}

/* Iconos: feather/FA/BI/tabler-icons heredan color */
[data-theme="dark"] .navbar-vertical .nav-link [data-feather],
[data-theme="dark"] .navbar-vertical .nav-link i,
[data-theme="dark"] .navbar-vertical .nav-link svg.feather{
  color: currentColor !important;
  stroke: currentColor !important;
  opacity: 1 !important;
}

/* Quitar “huecos”/separaciones: container del header sin padding lateral extra */
header.navbar > .container-xl,
header.navbar > .container-fluid{
  padding-left: 0 !important;
  padding-right: 0 !important;
}
</style>
@endpush

<!doctype html>
<html lang="es"
      data-theme="dark"
      data-bs-theme="dark"
      data-layout="fluid">

<head>
  <meta charset="utf-8">
  <meta name="page-id" content="@yield('page-id','admin')">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>{{ config('app.name') }} · @yield('title','Admin')</title>

  {{-- Fonts --}}
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

  {{-- Icon packs (compatibilidad total) --}}
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        referrerpolicy="no-referrer" />
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />

  {{-- Tabler (fijo, NO latest) --}}
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/css/tabler.min.css" />
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.2.0/tabler-icons.min.css" />

  {{-- Importante: NO cargar Tailwind CSS del app en Admin --}}
  @vite(['resources/js/app.js'])

  @stack('styles')
</head>

<body style="opacity:0;">
  <div class="page">
    {{-- Sidebar --}}
    @include('partials.sidebar_tabler')

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
          <div class="row text-muted align-items-center">
            <div class="col-6 text-start">
              <p class="mb-0"><strong>{{ config('app.name') }}</strong> &copy; {{ date('Y') }}</p>
            </div>
            <div class="col-6 text-end"><small class="text-muted">v1</small></div>
          </div>
        </div>
      </footer>
    </div>
  </div>

  {{-- Tabler JS (incluye Bootstrap bundle) --}}
  <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/js/tabler.min.js"></script>

  {{-- Feather --}}
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    // Siempre visible (evitar flashes)
    document.body.style.opacity = '1';

    // Feather icons
    if (window.feather) window.feather.replace();

    // Inyectar estilos anti-transiciones + pagination base
    const style = document.createElement('style');
    style.textContent = `
      body, .page-body, .card, .table, .fade { transition:none !important; animation:none !important; }
      body { opacity:1 !important; visibility:visible !important; }

      /* Paginación base compacta */
      .pagination { font-size: 14px !important; }
      .page-link { padding: 6px 12px !important; font-size: 14px !important; line-height: 1.5 !important; }
      .page-link i.bi { font-size: 12px !important; }
    `;
    document.head.appendChild(style);

    // Click en paginación: evitar “flash” sin romper ctrl/⌘ click, middle click, target=_blank, etc.
    document.addEventListener('click', function (e) {
      const link = e.target.closest('.page-link, .pagination a');
      if (!link || !link.href) return;
      if (e.defaultPrevented) return;
      if (e.button !== 0) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      if (link.target && link.target !== '_self') return;

      e.preventDefault();
      const contentArea = document.querySelector('.page-body');
      if (contentArea) contentArea.style.opacity = '0.95';
      window.location.assign(link.href);
    }, false);
  });
  </script>

  @stack('scripts')
</body>
</html>
