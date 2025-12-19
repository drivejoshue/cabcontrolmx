<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $__env->yieldContent('title', 'Orbana'); ?></title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

  <style>
    .hero-grad {
      background: radial-gradient(1200px 600px at 20% 10%, rgba(13,110,253,.18), transparent 60%),
                  radial-gradient(900px 500px at 80% 20%, rgba(32,201,151,.16), transparent 55%);
    }
    .card-soft { border: 1px solid rgba(0,0,0,.06); border-radius: 16px; }
  </style>
  
  <?php echo $__env->yieldContent('styles'); ?>
</head>
<body class="bg-light">
  <nav class="navbar navbar-expand-lg bg-white border-bottom">
    <div class="container">
      <a class="navbar-brand fw-bold" href="<?php echo e(route('public.landing')); ?>">Orbana</a>
      <div class="ms-auto d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo e(route('public.signup')); ?>">Registro</a>
        <a class="btn btn-primary btn-sm" href="<?php echo e(route('login')); ?>">Entrar</a>
      </div>
    </div>
  </nav>

  <main>
    <?php echo $__env->yieldContent('content'); ?>
  </main>

  <footer class="py-4 mt-5 border-top bg-white">
    <div class="container small text-muted">
      © <?php echo e(date('Y')); ?> Orbana. Todos los derechos reservados.
    </div>
  </footer>

  <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <?php echo $__env->yieldContent('scripts'); ?>
</body>
</html><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/layouts/public.blade.php ENDPATH**/ ?>