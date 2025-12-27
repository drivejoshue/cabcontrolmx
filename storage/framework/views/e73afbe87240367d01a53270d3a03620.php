<!doctype html>
<html lang="es" data-bs-theme="dark" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="page-id" content="<?php echo $__env->yieldContent('page-id','admin'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

  <title><?php echo e(config('app.name')); ?> · <?php echo $__env->yieldContent('title','Admin'); ?></title>

  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

  
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        referrerpolicy="no-referrer" />
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />

  
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/css/tabler.min.css">

  
 <link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css">
<style>
    /* Fuerza el texto blanco en badges con bg-* */
    .badge.bg-primary,
    .badge.bg-secondary,
    .badge.bg-success,
    .badge.bg-danger,
    .badge.bg-warning,
    .badge.bg-info,
    .badge.bg-dark {
      color: #fff !important;
      --tblr-badge-color: #fff;
    }

    /* Si algún badge trae links o iconos dentro */
    .badge.bg-primary *,
    .badge.bg-secondary *,
    .badge.bg-success *,
    .badge.bg-danger *,
    .badge.bg-warning *,
    .badge.bg-info *,
    .badge.bg-dark * {
      color: inherit !important;
    }

    /* Excepción: bg-light debe ser oscuro */
    .badge.bg-light {
      color: #111827 !important;
      --tblr-badge-color: #111827;
    }
    .badge.bg-light * { color: inherit !important; }
  </style>

  <?php echo $__env->yieldPushContent('styles'); ?>
</head>

<body class="layout-fluid">
  <div class="page">
    <?php echo $__env->make('partials.sidebar_tabler', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div class="page-wrapper">
      <?php echo $__env->make('partials.topbar_tabler', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

      <div class="page-body">
        <div class="container-xl">
          <?php echo $__env->yieldContent('content'); ?>
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
                  <strong><?php echo e(config('app.name')); ?></strong> &copy; <?php echo e(date('Y')); ?>

                </li>
              </ul>
            </div>
          </div>
        </div>
      </footer>
    </div>
  </div>

  
  <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/js/tabler.min.js"></script>

  
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (window.feather) window.feather.replace();
    });
  </script>

  <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/layouts/admin_tabler.blade.php ENDPATH**/ ?>