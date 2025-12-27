<?php /* Login Orbana (AdminKit) */ ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Iniciar sesión · Orbana</title>

  <style>body{opacity:0}</style>

  <link id="theme-light" rel="stylesheet" href="<?php echo e(Vite::asset('resources/css/adminkit/light.css')); ?>">
  <link id="theme-dark"  rel="stylesheet" href="<?php echo e(Vite::asset('resources/css/adminkit/dark.css')); ?>" disabled>

  <script>
    (function(){
      var t = localStorage.getItem('theme') || 'dark'; // Orbana default: dark
      document.documentElement.setAttribute('data-theme', t);
      window.addEventListener('DOMContentLoaded', function(){
        var l=document.getElementById('theme-light'), d=document.getElementById('theme-dark');
        if(l&&d){ l.disabled = (t!=='light'); d.disabled = (t!=='dark'); }
        document.body.style.opacity='1';

        // UI labels del toggle
        var btn = document.getElementById('themeToggle');
        if(btn){
          var lightLabel = btn.querySelector('.light-label');
          var darkLabel  = btn.querySelector('.dark-label');
          if(t === 'dark'){ lightLabel?.classList.add('d-none'); darkLabel?.classList.remove('d-none'); }
          else { darkLabel?.classList.add('d-none'); lightLabel?.classList.remove('d-none'); }
        }
      });
    })();
  </script>

  <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css','resources/js/app.js']); ?>

  <style>
    :root{
      --orb-cyan:#0dcaf0;
      --orb-blue:#0a58ca;
      --orb-ink:#0b1220;
      --orb-ink2:#0f172a;
      --orb-text:rgba(233,238,247,.92);
      --orb-muted:rgba(233,238,247,.72);
      --orb-border:rgba(255,255,255,.12);
    }

    /* Fondo Orbana, respeta AdminKit pero le damos “marca” */
    body.orb-login{
      background:
        radial-gradient(1200px 600px at 45% -15%, rgba(13,202,240,.22), transparent 60%),
        radial-gradient(900px 520px at 85% 10%, rgba(10,88,202,.18), transparent 55%),
        linear-gradient(180deg, var(--orb-ink) 0%, var(--orb-ink2) 100%);
      color: var(--orb-text);
    }

    /* Contenedor del login */
    .orb-wrap{
      min-height: 100vh;
      padding: 28px 0;
    }

    /* Marca */
    .orb-brand{
      display:flex;
      align-items:center;
      justify-content:center;
      gap:12px;
      margin-bottom: 10px;
      text-decoration:none;
    }
    .orb-brand .orb-mark{
      width: 64px;
      height: 64px;
      border-radius: 18px;
      display:grid;
      place-items:center;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.12);
      backdrop-filter: blur(10px);
      overflow:hidden;
    }
    .orb-brand .orb-mark img{
      width: 46px; height:auto; display:block;
    }

    .orb-title{
      font-weight: 800;
      letter-spacing: -.02em;
      color: #fff;
      margin: 10px 0 6px;
    }
    .orb-subtitle{
      color: var(--orb-muted);
      margin: 0 auto;
      max-width: 420px;
    }

    /* Card tipo glass */
    .orb-card{
      border-radius: 18px;
      border: 1px solid var(--orb-border);
      background: rgba(255,255,255,.05);
      backdrop-filter: blur(12px);
      box-shadow: 0 22px 60px rgba(0,0,0,.28);
    }
    .orb-card .card-body{
      padding: 26px;
    }

    /* Inputs */
    .orb-card .form-label{
      color: rgba(233,238,247,.85);
      font-weight: 600;
    }
    .orb-card .form-control,
    .orb-card .form-control:focus{
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.14);
      color: rgba(233,238,247,.92);
      border-radius: 12px;
      box-shadow: none;
    }
    .orb-card .form-control::placeholder{
      color: rgba(233,238,247,.45);
    }
    .orb-card .form-check-label{
      color: rgba(233,238,247,.75);
    }

    /* Links */
    .orb-link, .orb-card a{
      color: rgba(13,202,240,.92);
      text-decoration: none;
    }
    .orb-link:hover, .orb-card a:hover{
      color: #ffffff;
      text-decoration: none;
    }

    /* Botón principal Orbana */
    .btn-orbana{
      border-radius: 14px;
      font-weight: 700;
      padding: 12px 14px;
      border: none;
      background: linear-gradient(135deg, var(--orb-cyan), var(--orb-blue));
      box-shadow: 0 14px 34px rgba(13,202,240,.18);
    }
    .btn-orbana:hover{
      filter: brightness(1.04);
      transform: translateY(-1px);
    }

    /* Botón toggle tema */
    .orb-toggle{
      border-radius: 12px;
      border: 1px solid rgba(255,255,255,.18);
      background: rgba(255,255,255,.04);
      color: rgba(233,238,247,.9);
    }
    .orb-toggle:hover{
      border-color: rgba(255,255,255,.32);
      background: rgba(255,255,255,.06);
      color: #fff;
    }

    /* Texto extra abajo */
    .orb-foot{
      color: rgba(233,238,247,.55);
      font-size: .9rem;
      margin-top: 16px;
    }

    @media (max-width: 576px){
      .orb-card .card-body{ padding: 20px; }
      .orb-brand .orb-mark{ width:58px; height:58px; }
      .orb-brand .orb-mark img{ width:42px; }
    }
  </style>
</head>

<body data-theme="default" data-layout="fluid" class="d-flex w-100 h-100 orb-login">
  <main class="d-flex w-100 h-100">
    <div class="container d-flex flex-column orb-wrap">
      <div class="row vh-100">
        <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5 mx-auto d-table h-100">
          <div class="d-table-cell align-middle">

            <div class="text-center mt-2 mb-4">
              <a class="orb-brand" href="<?php echo e(route('public.landing')); ?>">
                <span class="orb-mark">
                  <img src="<?php echo e(asset('images/logonf.png')); ?>"
                       alt="Orbana"
                       onerror="this.src='<?php echo e(asset('images/logo.png')); ?>'">
                </span>
              </a>

              <h1 class="h3 orb-title">Bienvenido a Orbana</h1>
              <p class="orb-subtitle">Inicia sesión para continuar en tu panel de operación.</p>
            </div>

            <div class="card orb-card">
              <div class="card-body">
                <div class="m-sm-2">
                  <form method="POST" action="<?php echo e(route('login')); ?>">
                    <?php echo csrf_field(); ?>

                    <div class="mb-3">
                      <label class="form-label">Email</label>
                      <input class="form-control form-control-lg" type="email" name="email"
                             required autofocus value="<?php echo e(old('email')); ?>"
                             placeholder="admin@tucentral.com">
                      <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <div class="text-danger small mt-1"><?php echo e($message); ?></div>
                      <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif; ?>
                    </div>

                    <div class="mb-3">
                      <label class="form-label">Contraseña</label>
                      <input class="form-control form-control-lg" type="password" name="password" required
                             placeholder="Tu contraseña">
                      <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <div class="text-danger small mt-1"><?php echo e($message); ?></div>
                      <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif; ?>

                      <?php if(Route::has('password.request')): ?>
                        <div class="mt-2">
                          <small><a class="orb-link" href="<?php echo e(route('password.request')); ?>">¿Olvidaste tu contraseña?</a></small>
                        </div>
                      <?php endif; ?>
                    </div>

                    <div class="form-check mb-3">
                      <input id="remember" type="checkbox" class="form-check-input" name="remember">
                      <label for="remember" class="form-check-label text-small">Recordarme</label>
                    </div>

                    <div class="d-grid gap-2 mt-3">
                      <button class="btn btn-lg btn-orbana" type="submit">
                        Entrar
                      </button>
                    </div>

                    <div class="orb-foot text-center">
                      Al ingresar aceptas las políticas de tu plataforma.
                    </div>
                  </form>
                </div>
              </div>
            </div>

          

            <div class="text-center">
              <button id="themeToggle" class="btn orb-toggle btn-sm" type="button">
                <span class="light-label"><i class="bi bi-moon"></i></span>
                <span class="dark-label d-none"><i class="bi bi-sun"></i></span>
              </button>
            </div>

            <script>
              (function(){
                var btn = document.getElementById('themeToggle');
                if(!btn) return;

                btn.addEventListener('click', function(){
                  var cur = localStorage.getItem('theme') || 'dark';
                  var next = (cur === 'dark') ? 'light' : 'dark';
                  localStorage.setItem('theme', next);
                  document.documentElement.setAttribute('data-theme', next);

                  var l=document.getElementById('theme-light'), d=document.getElementById('theme-dark');
                  if(l&&d){ l.disabled = (next!=='light'); d.disabled = (next!=='dark'); }

                  var lightLabel = btn.querySelector('.light-label');
                  var darkLabel  = btn.querySelector('.dark-label');
                  if(next === 'dark'){ lightLabel?.classList.add('d-none'); darkLabel?.classList.remove('d-none'); }
                  else { darkLabel?.classList.add('d-none'); lightLabel?.classList.remove('d-none'); }
                });
              })();
            </script>

          </div>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/auth/login.blade.php ENDPATH**/ ?>