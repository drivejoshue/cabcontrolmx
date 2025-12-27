<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <meta name="description" content="<?php echo $__env->yieldContent('meta_description', 'Orbana Dispatch — Acceso para centrales y operación en tiempo real.'); ?>">
  <meta name="keywords" content="orbana, dispatch, taxi, central, operación, flota, tiempo real">
  <meta name="author" content="Orbana">
  <meta property="og:title" content="<?php echo $__env->yieldContent('og_title', 'Orbana Dispatch'); ?>">
  <meta property="og:description" content="<?php echo $__env->yieldContent('og_description', 'Ingreso y registro de centrales. Panel de despacho en tiempo real.'); ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?php echo e(url('/')); ?>">

  <title><?php echo $__env->yieldContent('title', 'Orbana Dispatch'); ?></title>

  <?php
    $flex = 'vendor/FlexStart/assets';
  ?>

  
  <link rel="icon" href="<?php echo e(asset($flex.'/img/favicon.png')); ?>">
  <link rel="apple-touch-icon" href="<?php echo e(asset($flex.'/img/apple-touch-icon.png')); ?>">

  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

  
  <link href="<?php echo e(asset($flex.'/vendor/bootstrap/css/bootstrap.min.css')); ?>" rel="stylesheet">
  <link href="<?php echo e(asset($flex.'/vendor/bootstrap-icons/bootstrap-icons.css')); ?>" rel="stylesheet">
  <link href="<?php echo e(asset($flex.'/vendor/aos/aos.css')); ?>" rel="stylesheet">

  
  <link href="<?php echo e(asset($flex.'/css/main.css')); ?>" rel="stylesheet">

  <style>
    :root{
      --orb-primary: #0dcaf0;
      --orb-primary-dark:#0aa2c0;
      --orb-bg:#ffffff;
      --orb-muted:#6c757d;
      --orb-border:#e9ecef;
      --orb-top:#121212;
    }

    body{
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: var(--orb-bg);
      color:#111827;
      padding-top: 76px;
    }

    /* Header minimal */
    .orb-header{
      background: var(--orb-top);
      height: 76px;
      box-shadow: 0 6px 22px rgba(0,0,0,.12);
    }
    .orb-logo{
      height: 36px;
      width:auto;
      display:block;
    }
    .orb-btn{
      display:inline-flex;
      align-items:center;
      gap:.5rem;
      padding:.65rem 1.05rem;
      border-radius:.9rem;
      font-weight:600;
      text-decoration:none;
      transition: .2s ease;
      font-family: Inter, sans-serif;
      border: 1px solid transparent;
      white-space: nowrap;
    }
    .orb-btn-primary{
      background: linear-gradient(135deg, var(--orb-primary), #0a58ca);
      color:#fff;
      box-shadow: 0 10px 24px rgba(13,202,240,.22);
    }
    .orb-btn-primary:hover{ transform: translateY(-1px); color:#fff; }
    .orb-btn-outline{
      background: transparent;
      border-color: rgba(255,255,255,.22);
      color:#fff;
    }
    .orb-btn-outline:hover{ border-color: rgba(255,255,255,.38); color:#fff; transform: translateY(-1px); }

    /* Page */
    .orb-hero{
      background:
        radial-gradient(1200px 600px at 50% 0%, rgba(13,202,240,.14), rgba(10,88,202,.04) 55%, transparent 70%),
        linear-gradient(180deg, rgba(13,202,240,.04), transparent 55%);
      border-bottom: 1px solid var(--orb-border);
    }
    .orb-hero .badge{
      border: 1px solid rgba(13,202,240,.25);
      color:#0b7285;
      background: rgba(13,202,240,.06);
      font-weight:600;
    }
    .orb-hero-title{
      font-family: Poppins, sans-serif;
      font-weight:800;
      letter-spacing:-.02em;
      font-size: clamp(1.65rem, 3.2vw, 2.35rem);
      margin: .75rem 0 .5rem;
    }
    .orb-hero-text{ color: var(--orb-muted); font-size: 1.05rem; max-width: 720px; margin: 0 auto; }

    .orb-cardlink{
      border:1px solid var(--orb-border);
      border-radius: 16px;
      background:#fff;
      padding: 18px 18px;
      height: 100%;
      transition:.2s ease;
      text-decoration:none;
      color: inherit;
      display:block;
    }
    .orb-cardlink:hover{
      transform: translateY(-3px);
      box-shadow: 0 18px 38px rgba(0,0,0,.08);
      border-color: rgba(13,202,240,.45);
    }
    .orb-cardlink .icon{
      width: 44px; height:44px;
      border-radius: 14px;
      display:flex; align-items:center; justify-content:center;
      background: rgba(13,202,240,.10);
      color: #0aa2c0;
      font-size: 1.2rem;
      margin-bottom: 10px;
    }
    .orb-cardlink h3{
      font-family: Poppins, sans-serif;
      font-weight:700;
      font-size: 1.02rem;
      margin: 0 0 6px;
    }
    .orb-cardlink p{ margin:0; color: var(--orb-muted); font-size: .95rem; }

    .orb-footer{
      border-top: 1px solid var(--orb-border);
      color: var(--orb-muted);
      font-size: .9rem;
      padding: 22px 0;
    }
    h1, h2, h3, h4, h5, h6 {
    color: #45bde3;
    font-family: var(--heading-font);
}
/* =========================
   FOOTER DARK (ORBANA)
========================= */
.orb-footer-dark{
  background: #0b1220;
  color: rgba(233,238,247,.80);
  border-top: 1px solid rgba(255,255,255,.10);
  padding: 38px 0 18px;
}

.orb-footer-dark .orb-footer-brand{
  display:flex;
  align-items:center;
  gap:12px;
  text-decoration:none;
  color: #e9eef7;
}

.orb-footer-dark .orb-footer-brand img{
  height: 34px;
  width: auto;
  display:block;
}

.orb-footer-dark .orb-footer-title{
  font-family: Poppins, sans-serif;
  font-weight: 800;
  letter-spacing: -.02em;
  margin: 0;
  line-height: 1.1;
}

.orb-footer-dark .orb-footer-desc{
  margin: 10px 0 0;
  color: rgba(233,238,247,.65);
  max-width: 420px;
  font-size: .95rem;
}

.orb-footer-dark .orb-footer-h{
  font-family: Poppins, sans-serif;
  font-weight: 700;
  color: #e9eef7;
  font-size: .95rem;
  margin: 0 0 12px;
}

.orb-footer-dark .orb-footer-links{
  list-style:none;
  padding:0;
  margin:0;
  display:grid;
  gap:10px;
}

.orb-footer-dark a{
  color: rgba(233,238,247,.72);
  text-decoration:none;
  transition: .2s ease;
}

.orb-footer-dark a:hover{
  color: #ffffff;
}

.orb-footer-dark .orb-footer-chip{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding: 10px 12px;
  border-radius: 14px;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.10);
}

.orb-footer-dark .orb-footer-chip i{
  color: var(--orb-primary);
}

.orb-footer-dark .orb-footer-bottom{
  margin-top: 22px;
  padding-top: 18px;
  border-top: 1px solid rgba(255,255,255,.10);
  color: rgba(233,238,247,.55);
  font-size: .88rem;
}

.orb-footer-dark .orb-footer-social{
  display:flex;
  gap:10px;
}

.orb-footer-dark .orb-footer-social a{
  width: 38px;
  height: 38px;
  border-radius: 12px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.10);
  color: rgba(233,238,247,.78);
}

.orb-footer-dark .orb-footer-social a:hover{
  border-color: rgba(13,202,240,.45);
  background: rgba(13,202,240,.10);
  color: #ffffff;
  transform: translateY(-1px);
}

  </style>

  <?php echo $__env->yieldPushContent('styles'); ?>
</head>

<body>

  
  <header class="orb-header fixed-top d-flex align-items-center">
    <div class="container d-flex align-items-center justify-content-between">
      <a href="<?php echo e(route('public.landing')); ?>" class="d-flex align-items-center text-decoration-none">
        
      </a>

      <div class="d-flex align-items-center gap-2">
        <?php if(auth()->guard()->check()): ?>
          <form method="POST" action="<?php echo e(route('logout')); ?>" class="m-0">
            <?php echo csrf_field(); ?>
            <button type="submit" class="orb-btn orb-btn-outline">
              <i class="bi bi-box-arrow-right"></i>
              <span>Salir</span>
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main>
    <?php echo $__env->yieldContent('content'); ?>
  </main>

 <footer class="orb-footer-dark">
  <div class="container">

    <div class="row gy-4 align-items-start">
      
      <div class="col-12 col-lg-5">
        <a href="<?php echo e(route('public.landing')); ?>" class="orb-footer-brand">
          <img src="<?php echo e(asset('images/landing/logo.png')); ?>"
               alt="Orbana"
               onerror="this.src='<?php echo e(asset($flex.'/img/logo.png')); ?>'">
          <div>
            <div class="orb-footer-title">Orbana</div>
            <div class="small" style="color: rgba(233,238,247,.55);">Dispatch</div>
          </div>
        </a>

        <p class="orb-footer-desc">
          Acceso al panel de operación en tiempo real para centrales y flotas.
        </p>

        
        <div class="d-flex flex-wrap gap-2 mt-3">
          <span class="orb-footer-chip">
            <i class="bi bi-envelope"></i>
            <a href="mailto:contacto@orbana.mx">contacto@orbana.mx</a>
          </span>
          <span class="orb-footer-chip">
            <i class="bi bi-headset"></i>
            <a href="<?php echo e(Route::has('public.support') ? route('public.support') : url('/soporte')); ?>">Soporte</a>
          </span>
        </div>
      </div>

      
      <div class="col-6 col-lg-3">
        <div class="orb-footer-h">Enlaces</div>
        <ul class="orb-footer-links">
          <li><a href="<?php echo e(route('login')); ?>">Entrar</a></li>
          <li><a href="<?php echo e(route('public.signup')); ?>">Registrar central</a></li>
          <li><a href="<?php echo e(route('public.landing')); ?>">Sitio principal</a></li>
        </ul>
      </div>

      
      <div class="col-6 col-lg-4">
        <div class="orb-footer-h">Legal</div>
        <ul class="orb-footer-links">
          <li>
            <a href="<?php echo e(Route::has('public.privacy') ? route('public.privacy') : url('/privacidad')); ?>">
              Privacidad
            </a>
          </li>
          <li>
            <a href="<?php echo e(Route::has('public.terms') ? route('public.terms') : url('/terminos')); ?>">
              Términos
            </a>
          </li>
          <li>
            <a href="<?php echo e(url('/cookies')); ?>">
              Cookies
            </a>
          </li>
        </ul>

        
        <div class="orb-footer-social mt-3">
          <a href="#" aria-label="X"><i class="bi bi-twitter-x"></i></a>
          <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
          <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
          <a href="#" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
        </div>
      </div>
    </div>

    <div class="orb-footer-bottom d-flex flex-column flex-md-row align-items-center justify-content-between gap-2">
      <div>
        © <strong style="color:#fff;">Orbana</strong> <?php echo e(date('Y')); ?> — Todos los derechos reservados.
      </div>
      <div class="d-flex gap-3">
        <a href="<?php echo e(Route::has('public.privacy') ? route('public.privacy') : url('/privacidad')); ?>">Privacidad</a>
        <a href="<?php echo e(Route::has('public.terms') ? route('public.terms') : url('/terminos')); ?>">Términos</a>
        <a href="<?php echo e(Route::has('public.support') ? route('public.support') : url('/soporte')); ?>">Soporte</a>
      </div>
    </div>

  </div>
</footer>


  
  <script src="<?php echo e(asset($flex.'/vendor/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
  <script src="<?php echo e(asset($flex.'/vendor/aos/aos.js')); ?>"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (window.AOS) AOS.init({ duration: 650, once: true, easing: 'ease-out' });
    });
  </script>

  <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/layouts/public.blade.php ENDPATH**/ ?>