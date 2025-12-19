<?php /* Login limpio con AdminKit */ ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Iniciar sesión · CabcontrolMx</title>

  <style>body{opacity:0}</style>

  <link id="theme-light" rel="stylesheet" href="<?php echo e(Vite::asset('resources/css/adminkit/light.css')); ?>">
  <link id="theme-dark"  rel="stylesheet" href="<?php echo e(Vite::asset('resources/css/adminkit/dark.css')); ?>" disabled>
  <script>
    (function(){
      var t = localStorage.getItem('theme') || 'light';
      document.documentElement.setAttribute('data-theme', t);
      window.addEventListener('DOMContentLoaded', function(){
        var l=document.getElementById('theme-light'), d=document.getElementById('theme-dark');
        if(l&&d){ l.disabled = (t!=='light'); d.disabled = (t!=='dark'); }
        document.body.style.opacity='1';
      });
    })();
  </script>

  <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css','resources/js/app.js']); ?>
</head>
<body data-theme="default" data-layout="fluid" class="d-flex w-100 h-100">
  <main class="d-flex w-100 h-100">
    <div class="container d-flex flex-column">
      <div class="row vh-100">
        <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5 mx-auto d-table h-100">
          <div class="d-table-cell align-middle">

            <div class="text-center mt-4">
              <h1 class="h3">Bienvenido</h1>
              <p class="lead">Inicia sesión para continuar</p>
            </div>

            <div class="card">
              <div class="card-body">
                <div class="m-sm-3">
                  <form method="POST" action="<?php echo e(route('login')); ?>">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                      <label class="form-label">Email</label>
                      <input class="form-control form-control-lg" type="email" name="email" required autofocus value="<?php echo e(old('email')); ?>">
                      <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <div class="text-danger small"><?php echo e($message); ?></div>
                      <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif; ?>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Contraseña</label>
                      <input class="form-control form-control-lg" type="password" name="password" required>
                      <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <div class="text-danger small"><?php echo e($message); ?></div>
                      <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif; ?>
                      <?php if(Route::has('password.request')): ?>
                        <small><a href="<?php echo e(route('password.request')); ?>">¿Olvidaste tu contraseña?</a></small>
                      <?php endif; ?>
                    </div>
                    <div class="form-check mb-3">
                      <input id="remember" type="checkbox" class="form-check-input" name="remember">
                      <label for="remember" class="form-check-label text-small">Recordarme</label>
                    </div>
                    <div class="d-grid gap-2 mt-3">
                      <button class="btn btn-lg btn-primary">Entrar</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <?php if(Route::has('register')): ?>
              <div class="text-center mb-3">
                ¿Sin cuenta? <a href="<?php echo e(route('register')); ?>">Regístrate</a>
              </div>
            <?php endif; ?>

            <div class="text-center">
              <button id="themeToggle" class="btn btn-outline-secondary btn-sm">
                <span class="light-label"><i class="bi bi-moon"></i></span>
                <span class="dark-label d-none"><i class="bi bi-sun"></i></span>
              </button>
            </div>

          </div>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/auth/login.blade.php ENDPATH**/ ?>