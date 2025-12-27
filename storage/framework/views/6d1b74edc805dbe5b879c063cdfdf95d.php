<!doctype html>
<html lang="es" class="theme-dark" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="page-id" content="dispatch">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
  <meta name="google-maps-key" content="<?php echo e(config('services.google.key')); ?>">
  <meta name="tenant-id" content="<?php echo e(auth()->user()->tenant_id ?? ''); ?>">

  <title><?php echo $__env->yieldContent('title','Dispatch'); ?> · Orbana Core</title>

  
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>

  
  <link id="themeStylesheet"
        rel="stylesheet"
        href="<?php echo e(asset('assets/adminkit/dark.css')); ?>"
        data-light="<?php echo e(asset('assets/adminkit/light.css')); ?>"
        data-dark="<?php echo e(asset('assets/adminkit/dark.css')); ?>">

  
  

  <?php echo $__env->yieldPushContent('styles'); ?>

  
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

    /* Chat panel base */
#chatPanel .offcanvas-body { padding: 12px 12px 14px; }
#chatMessages { font-size: 14px; line-height: 1.25; }
#chatMessages .msg-bubble { border-radius: 14px; padding: 10px 12px; max-width: 82%; }
#chatMessages .msg-meta { font-size: 12px; opacity: .75; }
#chatMessages .msg-text { font-size: 14px; }

/* Dark theme tweaks */
[data-theme="dark"] #chatMessages { color: rgba(255,255,255,.92); }
[data-theme="dark"] #chatMessages .msg-bubble.bg-light { background: rgba(255,255,255,.08) !important; }
[data-theme="dark"] #chatMessages .date-chip { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.12); }

  </style>

  <script>
    window.getTenantId = function () {
      return <?php echo e((int) (auth()->user()->tenant_id ?? 0)); ?>;
    };
  </script>
</head>

<body>

<?php
  $user = auth()->user();
  $name = $user->name ?? 'Admin';
  $avatarUrl = $user->avatar_url ?? asset('images/avatar.jpg');
?>


<nav class="navbar navbar-expand px-3 border-bottom bg-body">

  
  <a href="<?php echo e(route('admin.dispatch')); ?>" class="orb-topbar-brand me-3">
    <img src="<?php echo e(asset('images/logonf.png')); ?>" alt="Orbana" width="28" height="28">
    <span class="d-flex flex-column lh-sm">
      <span>Orbana Core</span>
      <span class="orb-topbar-sub">Dispatch</span>
    </span>
  </a>

  
  <ul class="navbar-nav me-auto">
    <li class="nav-item">
      <a href="<?php echo e(route('admin.dispatch')); ?>"
         class="nav-link <?php echo e(request()->routeIs('admin.dispatch') ? 'active' : ''); ?>">
        Dispatch
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo e(route('admin.dashboard')); ?>" class="nav-link">
        Admin
      </a>
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

  
  <div id="ops-clock" class="ms-3 small text-muted d-none d-md-block">
    <i data-feather="clock"></i> <span id="ops-clock-text">--:--</span>
  </div>

  <div class="ms-3 d-flex align-items-center gap-3">

    
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

    
    <button id="themeToggle" class="btn btn-sm btn-outline-secondary" type="button">
      <span class="light-label d-none"><i data-feather="sun"></i></span>
      <span class="dark-label"><i data-feather="moon"></i></span>
    </button>

    
    <div class="dropdown">
      <a class="d-flex align-items-center text-decoration-none" href="#" data-bs-toggle="dropdown">
        <img src="<?php echo e($avatarUrl); ?>" class="rounded-circle me-2" width="28" height="28" alt="user">
        <span class="text-truncate" style="max-width:140px;"><?php echo e($name); ?></span>
      </a>
      <div class="dropdown-menu dropdown-menu-end">
        <a class="dropdown-item" href="<?php echo e(route('admin.profile.edit')); ?>">Perfil</a>
        <div class="dropdown-divider"></div>
        <form method="POST" action="<?php echo e(route('logout')); ?>"><?php echo csrf_field(); ?>
          <button class="dropdown-item" type="submit">Cerrar sesión</button>
        </form>
      </div>
    </div>

  </div>
</nav>


<main class="dispatch-wrapper">
  <?php echo $__env->yieldContent('content'); ?>
</main>


<?php echo app('Illuminate\Foundation\Vite')('resources/js/adminkit.js'); ?>
<?php echo $__env->yieldPushContent('scripts'); ?>

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
<?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/layouts/dispatch.blade.php ENDPATH**/ ?>