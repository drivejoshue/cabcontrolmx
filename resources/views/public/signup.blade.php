{{-- resources/views/public/signup.blade.php --}}
@extends('layouts.public')

@section('title', 'Crear tu central')

@section('content')
@php
  // Helpers visuales
  $heroBadge = 'Registro de central · Orbana Dispatch';
@endphp

<section class="py-5" style="background: linear-gradient(135deg, rgba(13,202,240,.10), rgba(10,88,202,.04) 60%, transparent 100%); border-bottom:1px solid rgba(0,0,0,.06);">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-9 col-xl-8 text-center">

        <span class="badge rounded-pill text-bg-light border px-3 py-2">
          <i class="bi bi-building me-1"></i> {{ $heroBadge }}
        </span>

        <h1 class="mt-3 mb-1 fw-bold" style="letter-spacing:-.02em;">
          Crear tu central
        </h1>

        <p class="text-muted mb-0">
          Registra tu tenant y crea el usuario administrador en un solo paso.
          Listo para operar en tiempo real.
        </p>

      </div>
    </div>
  </div>
</section>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-9 col-xl-8">

      {{-- Alerts --}}
      @if(session('status'))
        <div class="alert alert-success d-flex align-items-start gap-2">
          <i class="bi bi-check-circle mt-1"></i>
          <div>{{ session('status') }}</div>
        </div>
      @endif

      @if($errors->any())
        <div class="alert alert-danger">
          <div class="fw-semibold mb-1">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Revisa los campos marcados:
          </div>
          <ul class="mb-0">
            @foreach($errors->all() as $e)
              <li>{{ $e }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="card shadow-sm border-0" style="border-radius: 18px;">
        <div class="card-body p-4 p-md-5">

          {{-- Stepper --}}
          <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-4">
            <div class="d-flex align-items-center gap-2">
              <div class="rounded-circle d-flex align-items-center justify-content-center"
                   style="width:34px;height:34px;background:rgba(13,202,240,.12);color:#0aa2c0;">
                <i class="bi bi-1-circle"></i>
              </div>
              <div>
                <div class="fw-semibold">Datos de la central</div>
                <div class="small text-muted">Nombre, zona horaria y contacto</div>
              </div>
            </div>

            <div class="d-flex align-items-center gap-2">
              <div class="rounded-circle d-flex align-items-center justify-content-center"
                   style="width:34px;height:34px;background:rgba(10,88,202,.08);color:#0a58ca;">
                <i class="bi bi-2-circle"></i>
              </div>
              <div>
                <div class="fw-semibold">Usuario administrador</div>
                <div class="small text-muted">Cuenta principal del panel</div>
              </div>
            </div>

            <div class="d-flex align-items-center gap-2">
              <div class="rounded-circle d-flex align-items-center justify-content-center"
                   style="width:34px;height:34px;background:rgba(0,0,0,.06);color:#495057;">
                <i class="bi bi-3-circle"></i>
              </div>
              <div>
                <div class="fw-semibold">Activación</div>
                <div class="small text-muted">Verificación de correo</div>
              </div>
            </div>
          </div>

          <form method="POST" action="{{ route('public.signup.store') }}" id="signupForm" novalidate>
            @csrf

            {{-- ===================== Datos de la central ===================== --}}
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h2 class="h6 mb-0">Datos de la central</h2>
              <span class="badge bg-light text-dark border">Tenant</span>
            </div>

            <div class="row g-3 mb-4">
              <div class="col-12">
                <label class="form-label">Nombre de la central <span class="text-danger">*</span></label>
                <input type="text" name="central_name"
                  class="form-control @error('central_name') is-invalid @enderror"
                  value="{{ old('central_name') }}"
                  placeholder="Ej. Central Veracruz" required maxlength="150">
                @error('central_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Este nombre se verá en tu panel y facturación.</div>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Ciudad (opcional)</label>
                <input type="text" name="city"
                  class="form-control @error('city') is-invalid @enderror"
                  value="{{ old('city') }}"
                  placeholder="Ej. Veracruz, Ver." maxlength="120">
                @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Zona horaria</label>
                <select name="timezone" class="form-select @error('timezone') is-invalid @enderror">
                  @php
                    $tzOld = old('timezone', 'America/Mexico_City');
                    $tzOptions = [
                      'America/Mexico_City' => 'México (CDMX) — America/Mexico_City',
                      'America/Cancun'      => 'Quintana Roo — America/Cancun',
                      'America/Monterrey'   => 'Monterrey — America/Monterrey',
                      'America/Chihuahua'   => 'Chihuahua — America/Chihuahua',
                      'America/Hermosillo'  => 'Sonora — America/Hermosillo',
                      'America/Tijuana'     => 'Baja California — America/Tijuana',
                    ];
                  @endphp

                  @foreach($tzOptions as $k => $label)
                    <option value="{{ $k }}" @selected($tzOld === $k)>{{ $label }}</option>
                  @endforeach

                  @if(!array_key_exists($tzOld, $tzOptions))
                    <option value="{{ $tzOld }}" selected>{{ $tzOld }}</option>
                  @endif
                </select>
                @error('timezone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">
                  Se usará para horarios, programación de rides y reportes.
                </div>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Teléfono público (opcional)</label>
                <input type="text" name="phone"
                  class="form-control @error('phone') is-invalid @enderror"
                  value="{{ old('phone') }}"
                  placeholder="Ej. +52 229 123 4567" maxlength="30">
                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Email de notificaciones (opcional)</label>
                <input type="email" name="notification_email"
                  class="form-control @error('notification_email') is-invalid @enderror"
                  value="{{ old('notification_email') }}"
                  placeholder="Ej. alertas@tucentral.com" maxlength="190">
                @error('notification_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">
                  Si lo dejas vacío, se usará el email del administrador.
                </div>
              </div>
            </div>

            <hr class="my-4">

            {{-- ===================== Datos del dueño (usuario admin) ===================== --}}
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h2 class="h6 mb-0">Usuario administrador</h2>
              <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Admin</span>
            </div>

            <div class="row g-3 mb-4">
              <div class="col-12">
                <label class="form-label">Nombre del dueño <span class="text-danger">*</span></label>
                <input type="text" name="owner_name"
                  class="form-control @error('owner_name') is-invalid @enderror"
                  value="{{ old('owner_name') }}"
                  placeholder="Ej. Juan Pérez" required maxlength="150">
                @error('owner_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-12">
                <label class="form-label">Email de acceso <span class="text-danger">*</span></label>
                <input type="email" name="owner_email"
                  class="form-control @error('owner_email') is-invalid @enderror"
                  value="{{ old('owner_email') }}"
                  placeholder="Ej. admin@tucentral.com"
                  required maxlength="190" autocomplete="email">
                @error('owner_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                <input type="password" name="password"
                  class="form-control @error('password') is-invalid @enderror"
                  placeholder="Mínimo 8 caracteres"
                  required minlength="8" maxlength="72" autocomplete="new-password">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                <input type="password" name="password_confirmation"
                  class="form-control"
                  placeholder="Repite la contraseña"
                  required minlength="8" maxlength="72" autocomplete="new-password">
              </div>
            </div>

            {{-- Trial --}}
            <div class="alert alert-info mb-4 d-flex gap-2 align-items-start">
              <i class="bi bi-hourglass-split mt-1"></i>
              <div>
                <div class="fw-semibold">Trial automático</div>
                <div class="small text-muted">
                  Al registrarte se creará un periodo de prueba según tu configuración actual de facturación.
                </div>
              </div>
            </div>

            {{-- Turnstile --}}
            @php $siteKey = config('services.turnstile.site_key'); @endphp

            @if(empty($siteKey))
              <div class="alert alert-warning">
                <div class="fw-semibold">Turnstile no configurado</div>
                <div class="small mb-0">
                  Revisa <code>TURNSTILE_SITE_KEY</code> en <code>.env</code> y <code>config/services.php</code>.
                </div>
              </div>
            @else
              <div class="mb-3">
                <div class="cf-turnstile" data-sitekey="{{ $siteKey }}"></div>
                @error('cf-turnstile-response')
                  <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </div>
            @endif

            <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-stretch align-items-md-center">
              <a href="{{ route('public.landing') }}" class="btn btn-light border">
                <i class="bi bi-arrow-left me-1"></i> Volver
              </a>

              <button type="submit" class="btn btn-primary px-4" id="submitBtn">
                <i class="bi bi-check2-circle me-1"></i> Crear central
              </button>
            </div>

            <div class="text-muted small mt-3">
              Al continuar aceptas los términos del servicio y políticas de privacidad de tu plataforma.
            </div>
          </form>

        </div>
      </div>

      <div class="text-center mt-4 small text-muted">
        ¿Ya tienes cuenta? <a href="{{ route('login') }}">Inicia sesión</a>
      </div>

    </div>
  </div>
</div>

{{-- Anti doble-submit --}}
<script>
  (function () {
    const form = document.getElementById('signupForm');
    const btn  = document.getElementById('submitBtn');
    if (!form || !btn) return;

    let locked = false;
    const originalHtml = btn.innerHTML;

    function unlock() {
      locked = false;
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }

    form.addEventListener('submit', function (e) {
      // Validar token Turnstile
      const tokenEl = form.querySelector('input[name="cf-turnstile-response"]');
      const token   = tokenEl && tokenEl.value ? tokenEl.value.trim() : '';

      if (!token) {
        e.preventDefault();
        unlock();
        alert('Completa la verificación anti-bot antes de continuar.');
        return;
      }

      if (locked) {
        e.preventDefault();
        return;
      }

      locked = true;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Creando...';
    });
  })();
</script>

@endsection

@push('scripts')
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endpush
