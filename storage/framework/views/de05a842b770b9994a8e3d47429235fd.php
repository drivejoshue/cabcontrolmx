

<?php $__env->startPush('styles'); ?>
<style>
/* ===== Only DARK theme ===== */
[data-theme="dark"] .sidebar{
  background: #0b1220 !important;
  border-right: 1px solid #111827 !important;
}
[data-theme="dark"] .sidebar .sidebar-brand-text{
  color: #a5b4fc !important;
  font-weight: 700;
}
[data-theme="dark"] .sidebar .sidebar-header{
  color: #7dd3fc !important;
  letter-spacing: .06em;
}
[data-theme="dark"] .sidebar .sidebar-item .sidebar-link{
  color: #93c5fd !important;
  opacity: 1 !important;
  background: transparent !important;
}
[data-theme="dark"] .sidebar .sidebar-item .sidebar-link:hover{
  color: #bfdbfe !important;
  background: rgba(29, 78, 216, .12) !important;
}
[data-theme="dark"] .sidebar .sidebar-item.active > .sidebar-link{
  color: #60a5fa !important;
  background: rgba(37, 99, 235, .18) !important;
  font-weight: 600;
  box-shadow: inset 0 0 0 1px rgba(59,130,246,.25);
  border-radius: .5rem;
}
[data-theme="dark"] .sidebar .sidebar-item .sidebar-link [data-feather],
[data-theme="dark"] .sidebar .sidebar-item .sidebar-link i,
[data-theme="dark"] .sidebar .sidebar-item .sidebar-link svg.feather{
  color: currentColor !important;
  stroke: currentColor !important;
  opacity: 1 !important;
}
[data-theme="dark"] .logo-dark  { display: inline !important; }
[data-theme="dark"] .logo-light { display: none  !important; }
</style>
<?php $__env->stopPush(); ?>

<!doctype html>
<html lang="es" data-theme="dark" data-layout="fluid" data-sidebar-position="left" data-sidebar-layout="default">
<head>
  <meta charset="utf-8">
  <meta name="page-id" content="<?php echo $__env->yieldContent('page-id','dashboard'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e(config('app.name')); ?> · <?php echo $__env->yieldContent('title','Admin'); ?> </title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        referrerpolicy="no-referrer" />

  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"/>

  <link id="themeStylesheet"
        rel="stylesheet"
        href="<?php echo e(asset('assets/adminkit/dark.css')); ?>"
        data-light="<?php echo e(asset('assets/adminkit/light.css')); ?>"
        data-dark="<?php echo e(asset('assets/adminkit/dark.css')); ?>">

  
  <?php echo app('Illuminate\Foundation\Vite')(['resources/js/app.js','resources/js/adminkit.js']); ?>

  <?php echo $__env->yieldPushContent('styles'); ?>
</head>

<body>
<div class="wrapper">
  <?php echo $__env->make('partials.sidebar_adminkit', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  <div class="main">
    <?php echo $__env->make('partials.topbar_adminkit', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <main class="content">
      <div class="container-fluid p-0">
        <?php echo $__env->yieldContent('content'); ?>
      </div>
    </main>

    <footer class="footer">
      <div class="container-fluid">
        <div class="row text-muted">
          <div class="col-6 text-start">
            <p class="mb-0"><strong><?php echo e(config('app.name')); ?></strong> &copy; <?php echo e(date('Y')); ?></p>
          </div>
          <div class="col-6 text-end"><small class="text-muted">v1</small></div>
        </div>
      </div>
    </footer>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
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
    body, .content, .card, .table, .fade { transition:none !important; animation:none !important; }
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
    if (e.button !== 0) return; // solo click izquierdo
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (link.target && link.target !== '_self') return;

    e.preventDefault();
    const contentArea = document.querySelector('.content');
    if (contentArea) contentArea.style.opacity = '0.95';
    window.location.assign(link.href);
  }, false);
});
</script>

<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/layouts/admin.blade.php ENDPATH**/ ?>