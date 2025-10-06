<?php /* resources/views/layouts/dispatch.blade.php */ ?>
<!doctype html>
<html lang="es" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','Dispatch') · CabcontrolMx</title>
{{-- Feather por CDN (iconos) --}}
 <!-- choose one -->
<script src="https://unpkg.com/feather-icons"></script>
<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>

  {{-- Hoja dinámica (light/dark) de AdminKit --}}
  <link id="themeStylesheet" rel="stylesheet" href="{{ Vite::asset('resources/css/adminkit/light.css') }}">
  @vite('resources/css/app.css')
  @stack('styles')
</head>
<body>

  {{-- Topbar compacta para Dispatch --}}
  <nav class="navbar navbar-expand px-3 border-bottom bg-body">
    <a href="{{ route('admin.dashboard') }}" class="navbar-brand fw-semibold me-3">CabcontrolMx</a>

    <ul class="navbar-nav me-auto">
      <li class="nav-item">
        <a href="{{ route('admin.dispatch') }}" class="nav-link active">Dispatch</a>
      </li>
      <li class="nav-item">
        <a href="{{ route('admin.dashboard') }}" class="nav-link">Admin</a>
      </li>
    </ul>

    <div class="d-none d-lg-flex align-items-center gap-3">
      <div class="text-center">
        <div class="fw-bold" id="kpi-free">0</div>
        <small class="text-muted">libres</small>
      </div>
      <div class="text-center">
        <div class="fw-bold text-success" id="kpi-busy">0</div>
        <small class="text-muted">ocupados</small>
      </div>
      <div class="text-center">
        <div class="fw-bold text-danger" id="kpi-onhold">0</div>
        <small class="text-muted">en cola</small>
      </div>
    </div>

    <div class="ms-3 d-flex align-items-center gap-2">
      <button id="themeToggle" class="btn btn-sm btn-outline-secondary">
        <span class="light-label d-none"><i data-feather="sun"></i></span>
        <span class="dark-label"><i data-feather="moon"></i></span>
      </button>

      <div class="dropdown">
        <a class="d-flex align-items-center text-decoration-none" href="#" data-bs-toggle="dropdown">
          <img src="{{ asset('images/avatar.jpg') }}" class="rounded-circle me-2" width="28" height="28" alt="user">
          <span>{{ auth()->user()->name ?? 'Admin' }}</span>
        </a>
        <div class="dropdown-menu dropdown-menu-end">
          <a class="dropdown-item" href="{{ route('profile.edit') }}">Perfil</a>
          <form method="POST" action="{{ route('logout') }}">@csrf
            <button class="dropdown-item">Cerrar sesión</button>
          </form>
        </div>
      </div>
    </div>
  </nav>

  {{-- Contenido plano sin sidebar (tipo TaxiCaller) --}}
  <main class="dispatch-wrapper">
    @yield('content')
  </main>

  @vite('resources/js/adminkit.js')
  @stack('scripts')
</body>
</html>
