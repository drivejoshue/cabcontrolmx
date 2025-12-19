<!doctype html>
<html lang="es" class="theme-dark" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="page-id" content="dispatch">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="google-maps-key" content="{{ config('services.google.key') }}">
  <meta name="tenant-id" content="{{ auth()->user()->tenant_id ?? '' }}">

  <title>@yield('title','Dispatch') · Orbana Core</title>

  {{-- Feather --}}
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>

  {{-- AdminKit (arranca en dark para Orbana Core) --}}
  <link id="themeStylesheet"
        rel="stylesheet"
        href="{{ asset('assets/adminkit/dark.css') }}"
        data-light="{{ asset('assets/adminkit/light.css') }}"
        data-dark="{{ asset('assets/adminkit/dark.css') }}">

  {{-- NO cargar Tailwind aquí --}}
  {{-- @vite('resources/css/app.css') --}}

  @stack('styles')

  {{-- SweetAlert --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    /* Topbar branding Orbana Core */
    .orb-topbar-brand{
      display:flex; align-items:center; gap:.6rem;
      font-weight:800; letter-spacing:.02em;
      text-decoration:none;
    }
    .orb-topbar-brand img{
      border-radius:999px; object-fit:cover;
      box-shadow: 0 0 0 2px rgba(255,255,255,.06);
    }
    .orb-topbar-sub{
      font-weight:600;
      opacity:.65;
      font-size:.78rem;
      line-height:1.1;
    }
    .cc-msg-icon-wrapper{
      display:inline-flex; align-items:center; justify-content:center;
      width:34px; height:34px; border-radius:10px;
      border: 1px solid rgba(255,255,255,.08);
    }
    .cc-msg-badge{
      position:absolute; top:-6px; right:-6px;
      font-size: .70rem;
    }

    /* Layout dispatch “plano” */
    .dispatch-wrapper{
      padding: 0;
      min-height: calc(100vh - 56px);
    }
  </style>

  <script>
    window.getTenantId = function () {
      return {{ (int) (auth()->user()->tenant_id ?? 0) }};
    };
  </script>
</head>

<body>

@php
  $user = auth()->user();
  $name = $user->name ?? 'Admin';
  $avatarUrl = $user->avatar_url ?? asset('images/avatar.jpg');
@endphp

{{-- Topbar compacta para Dispatch --}}
<nav class="navbar navbar-expand px-3 border-bottom bg-body">

  {{-- BRAND Orbana Core --}}
  <a href="{{ route('admin.dispatch') }}" class="orb-topbar-brand me-3">
    <img src="{{ asset('images/logonf.png') }}" alt="Orbana" width="28" height="28">
    <span class="d-flex flex-column lh-sm">
      <span>Orbana Core</span>
      <span class="orb-topbar-sub">Dispatch</span>
    </span>
  </a>

  {{-- Links --}}
  <ul class="navbar-nav me-auto">
    <li class="nav-item">
      <a href="{{ route('admin.dispatch') }}"
         class="nav-link {{ request()->routeIs('admin.dispatch') ? 'active' : '' }}">
        Dispatch
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('admin.dashboard') }}" class="nav-link">
        Admin
      </a>
    </li>
  </ul>

  {{-- KPIs --}}
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

  {{-- Reloj --}}
  <div id="ops-clock" class="ms-3 small text-muted d-none d-md-block">
    <i data-feather="clock"></i> <span id="ops-clock-text">--:--</span>
  </div>

  <div class="ms-3 d-flex align-items-center gap-3">

    {{-- Dropdown de MENSAJES / CHAT --}}
    <div class="dropdown">
      <a class="nav-link position-relative p-0" href="#"
         id="ccMessagesDropdown"
         role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="cc-msg-icon-wrapper">
          <i data-feather="message-square"></i>
        </span>
        <span id="ccMessagesBadge" class="badge rounded-pill bg-danger cc-msg-badge">0</span>
      </a>

      <div class="dropdown-menu dropdown-menu-end p-0 shadow"
           aria-labelledby="ccMessagesDropdown"
           style="min-width: 320px; max-height: 380px; overflow-y: auto;">
        <div id="ccMessagesList" class="list-group list-group-flush small">
          <div class="text-muted small p-2">Sin mensajes.</div>
        </div>
      </div>
    </div>

    {{-- Tema --}}
    <button id="themeToggle" class="btn btn-sm btn-outline-secondary" type="button">
      <span class="light-label d-none"><i data-feather="sun"></i></span>
      <span class="dark-label"><i data-feather="moon"></i></span>
    </button>

    {{-- Usuario --}}
    <div class="dropdown">
      <a class="d-flex align-items-center text-decoration-none" href="#" data-bs-toggle="dropdown">
        <img src="{{ $avatarUrl }}" class="rounded-circle me-2" width="28" height="28" alt="user">
        <span class="text-truncate" style="max-width:140px;">{{ $name }}</span>
      </a>
      <div class="dropdown-menu dropdown-menu-end">
        <a class="dropdown-item" href="{{ route('profile.edit') }}">Perfil</a>
        <div class="dropdown-divider"></div>
        <form method="POST" action="{{ route('logout') }}">@csrf
          <button class="dropdown-item" type="submit">Cerrar sesión</button>
        </form>
      </div>
    </div>

  </div>
</nav>

{{-- Contenido plano sin sidebar (tipo TaxiCaller) --}}
<main class="dispatch-wrapper">
  @yield('content')
</main>

{{-- Scripts --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
@vite('resources/js/adminkit.js')
@stack('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.feather) window.feather.replace();
});
</script>

<script>
(async function setupOpsClock(){
  let deltaMs = 0;
  let tenantTz = null;

  try {
    const r = await fetch('/api/dispatch/runtime', {headers:{Accept:'application/json'}});
    const js = await r.json();

    const serverNowMs = js.server_now_ms ?? Date.now();
    tenantTz = js.tenant_tz || js.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
    deltaMs  = serverNowMs - Date.now();

    const drift = Math.abs(deltaMs);
    if (drift > 90_000 && window.Swal) {
      Swal.fire({
        icon:'warning',
        title:'Ajusta tu reloj',
        text:'La hora local no coincide con el servidor. Ajusta la hora del sistema.',
      });
    }
  } catch(e){ /* no rompas UI si falla */ }

  const el = document.getElementById('ops-clock-text');
  function tick(){
    const now = new Date(Date.now()+deltaMs);
    if (!el) return;

    const dateStr = now.toLocaleDateString([], {
      timeZone: tenantTz, year:'numeric', month:'short', day:'2-digit'
    });
    const timeStr = now.toLocaleTimeString([], {
      timeZone: tenantTz, hour:'2-digit', minute:'2-digit'
    });
    el.textContent = `${dateStr} · ${timeStr}`;
  }

  tick();
  setInterval(tick, 1000);
  window.__SERVER_CLOCK_DELTA__ = deltaMs;
})();
</script>

</body>
</html>
