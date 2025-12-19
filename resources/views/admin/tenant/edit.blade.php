{{-- resources/views/admin/tenant/edit.blade.php --}}
@extends('layouts.admin')
@section('title','Mi Central')

@section('content')
<div class="container-fluid p-0">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-1">Mi Central</h1>
      <div class="text-muted">Administra los datos públicos y de notificación de tu central.</div>
    </div>
    <div class="text-end small text-muted">
      <div>Tenant ID: <strong>{{ $tenant->id }}</strong></div>
      <div>Onboarding: <strong>{{ $tenant->onboarding_done_at ? 'Completado' : 'Pendiente' }}</strong></div>
    </div>
  </div>

  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">Revisa los campos:</div>
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.tenant.update') }}">
    @csrf

    <div class="row g-3">
      {{-- Datos editables --}}
      <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-transparent border-0 pb-0">
            <h5 class="card-title mb-0">Datos generales</h5>
            <div class="text-muted small">Estos datos puedes cambiarlos cuando gustes.</div>
          </div>

          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Nombre de la central <span class="text-danger">*</span></label>
              <input
                type="text"
                name="name"
                class="form-control @error('name') is-invalid @enderror"
                value="{{ old('name', $tenant->name) }}"
                maxlength="150"
                required
              >
              @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
              <label class="form-label">Email de notificaciones</label>
              <input
                type="email"
                name="notification_email"
                class="form-control @error('notification_email') is-invalid @enderror"
                value="{{ old('notification_email', $tenant->notification_email) }}"
                maxlength="190"
                placeholder="Ej. notificaciones@tucentral.com"
              >
              <div class="form-text">
                Si lo dejas vacío, se usará tu email de usuario: <strong>{{ auth()->user()->email }}</strong>
              </div>
              @error('notification_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="row g-2">
              <div class="col-12 col-md-6">
                <div class="mb-3">
                  <label class="form-label">Teléfono público</label>
                  <input
                    type="text"
                    name="public_phone"
                    class="form-control @error('public_phone') is-invalid @enderror"
                    value="{{ old('public_phone', $tenant->public_phone) }}"
                    maxlength="30"
                    placeholder="Ej. +52 229 123 4567"
                  >
                  @error('public_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
              </div>

              <div class="col-12 col-md-6">
                <div class="mb-3">
                  <label class="form-label">Ciudad pública</label>
                  <input
                    type="text"
                    name="public_city"
                    class="form-control @error('public_city') is-invalid @enderror"
                    value="{{ old('public_city', $tenant->public_city) }}"
                    maxlength="120"
                    placeholder="Ej. Veracruz, Ver."
                  >
                  @error('public_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
              </div>
            </div>

            {{-- Opcional: notas públicas --}}
            {{--
            <div class="mb-3">
              <label class="form-label">Notas públicas</label>
              <textarea
                name="public_notes"
                class="form-control @error('public_notes') is-invalid @enderror"
                rows="3"
                maxlength="500"
                placeholder="Información adicional visible en tu perfil público..."
              >{{ old('public_notes', $tenant->public_notes) }}</textarea>
              @error('public_notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            --}}

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                Guardar cambios
              </button>
              <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                Volver
              </a>
            </div>
          </div>
        </div>
      </div>

      {{-- Datos bloqueados: ubicación/cobertura --}}
      <div class="col-12 col-lg-5">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-transparent border-0 pb-0">
            <h5 class="card-title mb-0">Ubicación y cobertura</h5>
            <div class="text-muted small">
              Las coordenadas no se pueden editar desde aquí.
            </div>
          </div>

          <div class="card-body">
            <div class="alert alert-warning">
              <div class="fw-bold mb-1">Coordenadas bloqueadas</div>
              <div class="small">
                Para cambiar <strong>latitud/longitud</strong> o el <strong>radio</strong>, debes solicitarlo a <strong>SysAdmin</strong>.
              </div>
            </div>

            <div class="row g-2">
              <div class="col-6">
                <label class="form-label">Latitud</label>
                <input type="text" class="form-control" value="{{ $tenant->latitud ?? '-' }}" disabled>
              </div>
              <div class="col-6">
                <label class="form-label">Longitud</label>
                <input type="text" class="form-control" value="{{ $tenant->longitud ?? '-' }}" disabled>
              </div>
              <div class="col-12">
                <label class="form-label">Radio de cobertura (km)</label>
                <input type="text" class="form-control" value="{{ $tenant->coverage_radius_km ?? '-' }}" disabled>
              </div>
            </div>

            <hr class="my-3">

            <div class="row g-2">
              <div class="col-12">
                <label class="form-label">Zona horaria</label>
                <input type="text" class="form-control" value="{{ $tenant->timezone ?? 'America/Mexico_City' }}" disabled>
              </div>
              <div class="col-12">
                <label class="form-label">UTC offset (min)</label>
                <input type="text" class="form-control" value="{{ $tenant->utc_offset_minutes ?? '-' }}" disabled>
              </div>
            </div>

            <hr class="my-3">

            <div class="small text-muted">
              <div><strong>Slug:</strong> {{ $tenant->slug }}</div>
              <div><strong>Última actualización:</strong> {{ $tenant->updated_at }}</div>
              <div><strong>Onboarding done:</strong> {{ $tenant->onboarding_done_at ?? '-' }}</div>
            </div>

         <div class="mt-3">
  <div class="p-3 rounded border bg-light">
    <div class="fw-bold mb-1">Solicitud de cambio de coordenadas</div>
    <div class="small text-muted mb-2">
      Por seguridad, la ubicación y el radio solo pueden modificarse mediante solicitud a soporte (SysAdmin).
      Envía estos datos para que podamos validar y aplicar el cambio:
    </div>

    <div class="small">
      <div><strong>Central:</strong> {{ $tenant->name }}</div>
      <div><strong>Tenant ID:</strong> {{ $tenant->id }}</div>
      <div><strong>Lat/Lng:</strong> {{ $tenant->latitud ?? '-' }}, {{ $tenant->longitud ?? '-' }}</div>
      <div><strong>Radio (km):</strong> {{ $tenant->coverage_radius_km ?? '-' }}</div>
      <div><strong>Ciudad pública:</strong> {{ $tenant->public_city ?? '-' }}</div>
    </div>

    <button type="button" class="btn btn-outline-secondary w-100 mt-2" id="btnCopyTenantLocation">
      Copiar datos para soporte
    </button>
    <div class="form-text">Esto copia al portapapeles un texto listo para pegar en WhatsApp o correo.</div>
  </div>
</div>


          </div>
        </div>
      </div>
    </div>
  </form>

</div>
@endsection
@push('scripts')
<script>
(function () {
  const btn = document.getElementById('btnCopyTenantLocation');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    const text =
`Solicitud de cambio de coordenadas (TENANT)
Tenant ID: {{ $tenant->id }}
Central: {{ addslashes($tenant->name) }}
Ciudad: {{ addslashes($tenant->public_city ?? '-') }}
Lat/Lng actual: {{ $tenant->latitud ?? '-' }}, {{ $tenant->longitud ?? '-' }}
Radio actual (km): {{ $tenant->coverage_radius_km ?? '-' }}

Motivo del cambio:
(NOTA: escribe aquí el motivo y las coordenadas nuevas solicitadas)`;

    try {
      await navigator.clipboard.writeText(text);
      btn.textContent = 'Copiado';
      setTimeout(() => btn.textContent = 'Copiar datos para soporte', 1200);
    } catch (e) {
      // fallback muy simple sin dependencias
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      btn.textContent = 'Copiado';
      setTimeout(() => btn.textContent = 'Copiar datos para soporte', 1200);
    }
  });
})();
</script>
@endpush
