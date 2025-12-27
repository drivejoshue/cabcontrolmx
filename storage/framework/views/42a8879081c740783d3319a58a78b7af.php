<?php
  $user  = auth()->user();
  $email = $user->email ?? '—';
  $logoCircle = asset('images/logonf.png');

  $sidebarTarget = !empty($user?->is_sysadmin) ? '#sidebar-menu-sys' : '#sidebar-menu';
?>

<header class="navbar navbar-expand-md d-print-none cc-topbar">
  <div class="container-fluid px-3">
    <div class="px-3 d-flex align-items-center w-100">

      <button class="navbar-toggler" type="button"
              data-bs-toggle="collapse"
              data-bs-target="<?php echo e($sidebarTarget); ?>"
              aria-controls="<?php echo e(ltrim($sidebarTarget,'#')); ?>"
              aria-expanded="false"
              aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="navbar-nav me-auto">
        <div class="nav-item d-flex align-items-center gap-2">
         
          <div class="d-flex flex-column lh-sm">
            <div class="fw-semibold">Orbana Core</div>
           
          </div>
        </div>
      </div>

      <div class="navbar-nav flex-row order-md-last align-items-center">

        <div class="nav-item me-2">
          <button id="ccThemeToggle"
                  class="btn btn-outline-secondary"
                  type="button"
                  title="Light/Dark"
                  aria-label="Toggle theme"
                  style="padding:.35rem .55rem;">
            <i id="ccThemeIcon" class="ti ti-moon"></i>
          </button>
        </div>

        <div class="nav-item">
          <form method="POST" action="<?php echo e(route('logout')); ?>" class="m-0">
            <?php echo csrf_field(); ?>
            <button class="btn btn-outline-danger" type="submit" title="Cerrar sesión">
              <i class="ti ti-logout me-1"></i>
              <span class="d-none d-md-inline">Salir</span>
            </button>
          </form>
        </div>

      </div>
    </div>
  </div>
</header>

<?php $__env->startPush('styles'); ?>
<style>
  .orb-brand-circle{
    border-radius: 999px;
    object-fit: cover;
    box-shadow: 0 0 0 2px rgba(255,255,255,.08);
  }
   .cc-topbar{
    background: var(--tblr-bg-surface);
    border-bottom: 1px solid var(--tblr-border-color);
  }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const html = document.documentElement;
  const btn  = document.getElementById('ccThemeToggle');
  const icon = document.getElementById('ccThemeIcon');
  const STORAGE_KEY = 'cc_theme';

  function applyTheme(theme) {
    const t = (theme === 'light') ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', t);
    if (icon) icon.className = (t === 'light') ? 'ti ti-sun' : 'ti ti-moon';
    try { localStorage.setItem(STORAGE_KEY, t); } catch {}
  }

  function getInitialTheme() {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved === 'light' || saved === 'dark') return saved;
    } catch {}
    const current = html.getAttribute('data-bs-theme');
    return (current === 'light') ? 'light' : 'dark';
  }

  applyTheme(getInitialTheme());

  btn?.addEventListener('click', (e) => {
    e.preventDefault();
    const current = html.getAttribute('data-bs-theme') === 'light' ? 'light' : 'dark';
    applyTheme(current === 'light' ? 'dark' : 'light');
  });
});
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/partials/topbar_tabler.blade.php ENDPATH**/ ?>