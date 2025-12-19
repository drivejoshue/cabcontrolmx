@extends('layouts.sysadmin')

@section('title', 'SysAdmin – Nuevo tenant')

@section('content')
<div class="container-fluid">
    <h1 class="mb-4">Nuevo tenant</h1>

    <form method="POST" action="{{ route('sysadmin.tenants.store') }}">
        @csrf

        <div class="row">
            <div class="col-md-6">
                <h4>Datos del tenant</h4>
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" class="form-control" value="{{ old('slug') }}">
                    <small class="text-muted">Si lo dejas vacío, se genera a partir del nombre.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Timezone</label>
                    <input type="text" name="timezone" class="form-control" value="{{ old('timezone', 'America/Mexico_City') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Latitud</label>
                    <input type="number" step="0.000001" name="latitud" class="form-control" value="{{ old('latitud') }}">
                </div>

                <div class="mb-3">
                    <label class="form-label">Longitud</label>
                    <input type="number" step="0.000001" name="longitud" class="form-control" value="{{ old('longitud') }}">
                </div>

                <div class="mb-3">
                    <label class="form-label">Radio de cobertura (km)</label>
                    <input type="number" step="0.1" name="coverage_radius_km" class="form-control" value="{{ old('coverage_radius_km') }}">
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" value="1" id="allow_marketplace" name="allow_marketplace" checked>
                    <label class="form-check-label" for="allow_marketplace">
                        Visible en marketplace (Orbana Passenger)
                    </label>
                </div>
            </div>

            <div class="col-md-6">
                <h4>Usuario admin del tenant</h4>

                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="admin_name" class="form-control" value="{{ old('admin_name') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="admin_email" class="form-control" value="{{ old('admin_email') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="admin_password" class="form-control" required>
                </div>
            </div>
        </div>

        <button class="btn btn-primary mt-3" type="submit">
            Guardar tenant
        </button>
    </form>
</div>
@endsection
