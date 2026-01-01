@extends('layouts.sysadmin')

@section('title', 'SysAdmin – Nuevo tenant')

@section('content')
<div class="container-fluid">

  {{-- Header / Toolbar --}}
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 mb-0">Nuevo tenant</h1>
      <div class="text-muted small">Crea una nueva central y su usuario administrador inicial.</div>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('sysadmin.tenants.index') }}" class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left me-1"></i> Volver a lista
      </a>
    </div>
  </div>

  {{-- Errores --}}
  @if($errors->any())
    <div class="alert alert-danger">
      <div class="fw-semibold mb-1">Revisa los campos:</div>
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('sysadmin.tenants.store') }}">
    @csrf

    <div class="row g-3">
      {{-- Datos del tenant --}}
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header">
            <div class="card-title">
              <i class="ti ti-building-community me-1"></i> Datos del tenant
            </div>
          </div>

          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Nombre</label>
              <div class="input-icon">
                <span class="input-icon-addon"><i class="ti ti-id-badge-2"></i></span>
                <input type="text" name="name" class="form-control"
                       value="{{ old('name') }}" required
                       placeholder="Ej: Radio Taxi Centro">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Slug</label>
              <div class="input-icon">
                <span class="input-icon-addon"><i class="ti ti-link"></i></span>
                <input type="text" name="slug" class="form-control"
                       value="{{ old('slug') }}"
                       placeholder="ej: radiotaxi-centro">
              </div>
              <small class="text-muted">Si lo dejas vacío, se genera a partir del nombre.</small>
            </div>

            <div class="mb-3">
              <label class="form-label">Timezone</label>
              <div class="input-icon">
                <span class="input-icon-addon"><i class="ti ti-world"></i></span>
                <input type="text" name="timezone" class="form-control"
                       value="{{ old('timezone', 'America/Mexico_City') }}" required
                       placeholder="America/Mexico_City">
              </div>
              <small class="text-muted">Ej: <code>America/Mexico_City</code>, <code>America/Monterrey</code>.</small>
            </div>

            <div class="row g-2">
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Latitud</label>
                  <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-map-pin"></i></span>
                    <input type="number" step="0.000001" name="latitud" class="form-control"
                           value="{{ old('latitud') }}" placeholder="19.173800">
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Longitud</label>
                  <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-map-pin"></i></span>
                    <input type="number" step="0.000001" name="longitud" class="form-control"
                           value="{{ old('longitud') }}" placeholder="-96.134200">
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Cobertura (km)</label>
                  <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-target-arrow"></i></span>
                    <input type="number" step="0.1" name="coverage_radius_km" class="form-control"
                           value="{{ old('coverage_radius_km', 30) }}" placeholder="30">
                  </div>
                </div>
              </div>
            </div>

            <label class="form-check">
              <input class="form-check-input" type="checkbox" value="1" name="allow_marketplace"
                     @checked(old('allow_marketplace', 1))>
              <span class="form-check-label">
                Visible en marketplace (Orbana Passenger)
              </span>
              <span class="form-check-description">
                Si está activo, el tenant puede ser resuelto dinámicamente por cobertura en la app Passenger.
              </span>
            </label>
          </div>
        </div>
      </div>

      {{-- Usuario admin --}}
      <div class="col-lg-5">
        <div class="card">
          <div class="card-header">
            <div class="card-title">
              <i class="ti ti-user-shield me-1"></i> Admin del tenant
            </div>
          </div>

          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Nombre</label>
              <div class="input-icon">
                <span class="input-icon-addon"><i class="ti ti-user"></i></span>
                <input type="text" name="admin_name" class="form-control"
                       value="{{ old('admin_name') }}" required
                       placeholder="Ej: Admin Central">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Email</label>
              <div class="input-icon">
                <span class="input-icon-addon"><i class="ti ti-mail"></i></span>
                <input type="email" name="admin_email" class="form-control"
                       value="{{ old('admin_email') }}" required
                       placeholder="admin@central.com">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Contraseña</label>
              <div class="input-icon">
                <span class="input-icon-addon"><i class="ti ti-lock"></i></span>
                <input type="password" name="admin_password" class="form-control" required
                       placeholder="Mínimo 6 caracteres">
              </div>
              <small class="text-muted">Se crea el usuario con email verificado.</small>
            </div>

            <div class="alert alert-info mb-0">
              <div class="fw-semibold mb-1">
                <i class="ti ti-info-circle me-1"></i> Nota
              </div>
              <div class="small">
                Después podrás ajustar billing, documentos y verificación desde SysAdmin.
              </div>
            </div>

          </div>

          <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('sysadmin.tenants.index') }}" class="btn btn-outline-secondary">
              Cancelar
            </a>
            <button class="btn btn-primary" type="submit">
              <i class="ti ti-device-floppy me-1"></i> Guardar tenant
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>

</div>
@endsection
