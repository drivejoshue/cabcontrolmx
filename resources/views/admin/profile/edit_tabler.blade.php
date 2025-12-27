@extends('layouts.admin_tabler')

@section('title','Mi perfil')
@section('page-id','profile')

@section('content')
<div class="row row-cards">
  <div class="col-12 col-lg-8">
    <form class="card" method="POST" action="{{ route('admin.profile.update') }}">
      @csrf

      <div class="card-header">
        <h3 class="card-title">Datos del usuario</h3>
      </div>

      <div class="card-body">
        @if(session('ok'))
          <div class="alert alert-success">{{ session('ok') }}</div>
        @endif

        @if($errors->any())
          <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Revisa los campos:</div>
            <ul class="mb-0">
              @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
          </div>
        @endif

        <div class="mb-3">
          <label class="form-label">Nombre</label>
          <input name="name" class="form-control" value="{{ old('name', auth()->user()->name) }}" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Email</label>
          <input name="email" type="email" class="form-control" value="{{ old('email', auth()->user()->email) }}" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Nueva contraseña (opcional)</label>
          <input name="password" type="password" class="form-control" autocomplete="new-password">
          <div class="form-hint">Déjalo vacío para no cambiarla.</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Confirmar contraseña</label>
          <input name="password_confirmation" type="password" class="form-control" autocomplete="new-password">
        </div>
      </div>

      <div class="card-footer text-end">
        <button class="btn btn-primary">
          <i class="ti ti-device-floppy me-1"></i> Guardar
        </button>
      </div>
    </form>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Información</h3>
      </div>
      <div class="card-body">
        <div class="text-muted">Esta pantalla es la prueba inicial de migración a Tabler.</div>
        <hr>
        <div class="small text-muted">Tenant</div>
        <div class="fw-semibold">{{ auth()->user()->tenant_id ?? '-' }}</div>
      </div>
    </div>
  </div>
</div>
@endsection
