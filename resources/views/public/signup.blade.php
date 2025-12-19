{{-- resources/views/public/signup.blade.php --}}
@extends('layouts.public')

@section('title', 'Crear tu central')

@section('content')
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-9 col-xl-8">

      <div class="mb-4 text-center">
        <h1 class="h3 mb-1">Crear tu central</h1>
        <p class="text-muted mb-0">Registra tu tenant y crea el usuario administrador en un solo paso.</p>
      </div>

      <div class="card shadow-sm border-0">
        <div class="card-body p-4 p-md-5">

          @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
          @endif

          @if($errors->any())
            <div class="alert alert-danger">
              <div class="fw-semibold mb-1">Revisa los campos marcados:</div>
              <ul class="mb-0">
                @foreach($errors->all() as $e)
                  <li>{{ $e }}</li>
                @endforeach
              </ul>
            </div>
          @endif

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
                <div class="form-text">Se usará para horarios, reportes y programación.</div>
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
                <div class="form-text">Si lo dejas vacío, usaremos el email del dueño.</div>
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

            <div class="alert alert-info mb-4">
              <div class="fw-semibold">Trial automático</div>
              <div class="small text-muted">
                Al registrarte, se creará un periodo de prueba (según tu configuración de billing profile).
              </div>
            </div>

            {{-- ===================== Turnstile ===================== --}}
            @php $siteKey = config('services.turnstile.site_key'); @endphp

            @if(empty($siteKey))
              <div class="alert alert-warning">
                Turnstile no está configurado: revisa <code>TURNSTILE_SITE_KEY</code> en tu <code>.env</code> y <code>config/services.php</code>.
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
                Volver
              </a>

              <button type="submit" class="btn btn-primary px-4" id="submitBtn">
                Crear central
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

{{-- Anti doble-submit (evita 2 tenants por click rápido) --}}
<script>
  (function () {
    const form = document.getElementById('signupForm');
    const btn  = document.getElementById('submitBtn');
    if (!form || !btn) return;

    form.addEventListener('submit', function () {
      btn.disabled = true;
      btn.dataset.originalText = btn.innerHTML;
      btn.innerHTML = 'Creando...';
    });
  })();
</script>
@endsection

@section('scripts')
  @parent
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endsection
