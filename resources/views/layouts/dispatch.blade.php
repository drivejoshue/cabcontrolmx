<!doctype html>
<html lang="es" data-theme="light">


<head>
  <meta charset="utf-8">
  <meta name="page-id" content="dispatch">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','Dispatch') · Athera</title>
{{-- Feather por CDN (iconos) --}}
 <!-- choose one -->
<!-- <script src="https://unpkg.com/feather-icons"></script> -->
   <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>

  {{-- Hoja dinámica (light/dark) de AdminKit --}}
  <link id="themeStylesheet" rel="stylesheet" href="{{ Vite::asset('resources/css/adminkit/light.css') }}">


  
  @vite('resources/css/app.css')
  @stack('styles')

 <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


</head>
<body>

  {{-- Topbar compacta para Dispatch --}}
  <nav class="navbar navbar-expand px-3 border-bottom bg-body">
    <a href="{{ route('admin.dashboard') }}" class="navbar-brand fw-semibold me-3">Athera-Dispatch</a>

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
<div id="ops-clock" class="ms-3 small text-muted">
  <i data-feather="clock"></i> <span id="ops-clock-text">--:--</span>
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

  <script>
  // helper único para todo el JS de Dispatch
  window.getTenantId = function () {
    return {{ (int) (auth()->user()->tenant_id ?? 1) }};
  };
</script>


<script>
(async function setupOpsClock(){
  let serverNowMs = null;
  let deltaMs = 0;
  let tenantTz = null;

  try {
    const r = await fetch('/api/dispatch/runtime', {headers:{Accept:'application/json'}});
    const js = await r.json();
    serverNowMs = js.server_now_ms ?? Date.now();
    tenantTz    = js.tenant_tz || js.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
    deltaMs     = serverNowMs - Date.now();

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
    if (el) {
      const dateStr = now.toLocaleDateString([], {
        timeZone: tenantTz, year:'numeric', month:'short', day:'2-digit'
      });
      const timeStr = now.toLocaleTimeString([], {
        timeZone: tenantTz, hour:'2-digit', minute:'2-digit'
      });
      el.textContent = `${dateStr} · ${timeStr}`;
    }
  }
  tick();
  setInterval(tick, 1000);

  window.__SERVER_CLOCK_DELTA__ = deltaMs;
})();
</script>


</body>
</html>
