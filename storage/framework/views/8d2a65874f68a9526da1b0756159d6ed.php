
<?php $__env->startSection('title','Orbana Dispatch — Acceso'); ?>


<?php $__env->startSection('nav'); ?>
<header id="header" class="header d-flex align-items-center fixed-top" style="background:#0b1220;">
  <div class="container-fluid container-xl d-flex align-items-center justify-content-between">
    <a href="<?php echo e(route('public.landing')); ?>" class="logo d-flex align-items-center text-decoration-none">
      <img src="<?php echo e(asset('images/landing/logo.png')); ?>"
           alt="Orbana"
           style="height:34px;width:auto;"
           onerror="this.src='<?php echo e(asset('vendor/FlexStart/assets/img/logo.png')); ?>'">
    </a>

    <div class="d-flex align-items-center gap-2">
      <a href="<?php echo e(route('login')); ?>" class="btn btn-sm btn-outline-light">
        <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
      </a>
    </div>
  </div>
</header>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<?php $__env->startPush('styles'); ?>
<style>
  /* Puerta Dispatch (dark hero) */
  .dispatch-gate {
    min-height: calc(100vh - 76px);
    padding-top: 76px; /* respeta header fijo */
    background:
      radial-gradient(1200px 600px at 50% -20%, rgba(13,202,240,.22), transparent 60%),
      radial-gradient(900px 520px at 80% 10%, rgba(10,88,202,.18), transparent 55%),
      linear-gradient(180deg, #0b1220 0%, #0b1220 35%, #0f172a 100%);
    color: #e9eef7;
  }

  .gate-card {
    border-radius: 18px;
    border: 1px solid rgba(255,255,255,.10);
    background: rgba(255,255,255,.04);
    backdrop-filter: blur(10px);
  }

  .gate-muted { color: rgba(233, 238, 247, .75); }

  .gate-logo {
    width: 96px;
    height: 96px;
    border-radius: 22px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.12);
    display: grid;
    place-items: center;
    overflow: hidden;
  }

  .gate-logo img { width: 72px; height: auto; display:block; }

  .btn-orbana {
    border-radius: 12px;
    padding: 12px 16px;
    font-weight: 600;
  }

  .btn-orbana-primary {
    background: linear-gradient(135deg, #0dcaf0, #0a58ca);
    border: none;
    color: #fff;
    box-shadow: 0 10px 30px rgba(13,202,240,.18);
  }
  .btn-orbana-primary:hover { color:#fff; filter: brightness(1.03); }

  .btn-orbana-outline {
    border: 1px solid rgba(255,255,255,.22);
    color: #e9eef7;
    background: rgba(255,255,255,.03);
  }
  .btn-orbana-outline:hover { color:#fff; background: rgba(255,255,255,.06); }

  .gate-linkcard {
    display:block;
    text-decoration:none;
    color: inherit;
    border-radius: 16px;
    border: 1px solid rgba(255,255,255,.10);
    background: rgba(255,255,255,.03);
    padding: 18px;
    transition: .2s ease;
    height: 100%;
  }
  .gate-linkcard:hover {
    transform: translateY(-3px);
    border-color: rgba(13,202,240,.45);
    background: rgba(255,255,255,.05);
  }

  .gate-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display:grid;
    place-items:center;
    background: rgba(13,202,240,.12);
    border: 1px solid rgba(13,202,240,.22);
    color: #0dcaf0;
  }

  .gate-footer {
    color: rgba(233, 238, 247, .55);
  }
</style>
<?php $__env->stopPush(); ?>

<section class="dispatch-gate d-flex align-items-center">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-9 col-xl-8">

        <div class="text-center mb-4">
          <div class="d-flex justify-content-center mb-3">
            <div class="gate-logo">
              
              <img src="<?php echo e(asset('images/landing/logo-symbol.png')); ?>"
                   alt="Orbana"
                   onerror="this.src='<?php echo e(asset('images/landing/logo.png')); ?>'">
            </div>
          </div>

          <div class="d-flex justify-content-center">
            <span class="badge rounded-pill text-bg-light border px-3 py-2">
              Orbana Dispatch
            </span>
          </div>

          <h1 class="mt-3 mb-2 fw-bold" style="letter-spacing:-.02em;">
            Acceso a tu panel
          </h1>

          <p class="gate-muted mb-0">
            Esta página es la puerta de entrada. La información completa está en el sitio principal.
          </p>
        </div>

        <div class="gate-card p-4 p-md-5">
          <div class="row g-3 align-items-center">
            <div class="col-12 col-md-7">
              <div class="fw-semibold mb-1">¿Ya tienes cuenta?</div>
              <div class="gate-muted small">
                Inicia sesión para entrar a tu central y abrir el Dispatch.
              </div>
            </div>
            <div class="col-12 col-md-5 d-grid gap-2">
              <a href="<?php echo e(route('login')); ?>" class="btn btn-orbana btn-orbana-primary">
                <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
              </a>
              <a href="<?php echo e(route('public.signup')); ?>" class="btn btn-orbana btn-orbana-outline">
                <i class="bi bi-lightning-charge me-1"></i> Registrar mi central
              </a>
            </div>
          </div>

          <hr class="my-4" style="border-color: rgba(255,255,255,.10);">

          <div class="row g-3">
            <div class="col-12 col-md-4">
              <a class="gate-linkcard" href="<?php echo e(url('/docs')); ?>">
                <div class="d-flex gap-3">
                  <div class="gate-icon"><i class="bi bi-journal-text"></i></div>
                  <div>
                    <div class="fw-semibold">Documentación</div>
                    <div class="gate-muted small">Guías rápidas y configuración.</div>
                  </div>
                </div>
              </a>
            </div>

            <div class="col-12 col-md-4">
              <a class="gate-linkcard" href="<?php echo e(url('/legal')); ?>">
                <div class="d-flex gap-3">
                  <div class="gate-icon"><i class="bi bi-shield-check"></i></div>
                  <div>
                    <div class="fw-semibold">Políticas</div>
                    <div class="gate-muted small">Privacidad y términos de uso.</div>
                  </div>
                </div>
              </a>
            </div>

            <div class="col-12 col-md-4">
              <a class="gate-linkcard" href="<?php echo e(url('/soporte')); ?>">
                <div class="d-flex gap-3">
                  <div class="gate-icon"><i class="bi bi-headset"></i></div>
                  <div>
                    <div class="fw-semibold">Soporte</div>
                    <div class="gate-muted small">Ayuda y contacto.</div>
                  </div>
                </div>
              </a>
            </div>
          </div>

          <div class="text-center mt-4 gate-footer small">
            © <?php echo e(date('Y')); ?> Orbana · Operación en tiempo real
          </div>
        </div>

      </div>
    </div>
  </div>
</section>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.public', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/public/landing.blade.php ENDPATH**/ ?>