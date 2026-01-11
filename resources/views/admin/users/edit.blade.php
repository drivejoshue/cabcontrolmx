@extends('layouts.admin')
@section('title','Editar usuario')

@section('content')
<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title">Editar usuario</h2>
        <div class="text-muted mt-1">{{ $user->email }}</div>
      </div>
      <div class="col-auto ms-auto">
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
          <i class="ti ti-arrow-left me-1"></i> Volver
        </a>
      </div>
    </div>
  </div>

  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  <div class="row g-3">
    <div class="col-lg-7">
      <form method="POST" action="{{ route('admin.users.update', $user) }}" class="card">
        @csrf
        @method('PUT')

        <div class="card-header">
          <h3 class="card-title">Datos</h3>
        </div>

        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre</label>
              <input name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input name="email" type="email" class="form-control" value="{{ old('email', $user->email) }}" required>
            </div>

            <div class="col-12">
              <label class="form-label">Rol</label>
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary-lt text-uppercase">{{ $user->role }}</span>
                <span class="text-muted">El rol no se edita. Para quitar acceso usa “Desactivar”.</span>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label">Estado</label>
              @if($user->active)
                <span class="badge bg-green-lt">Activo</span>
              @else
                <span class="badge bg-red-lt">Desactivado</span>
              @endif
            </div>
          </div>
        </div>

        <div class="card-footer d-flex justify-content-between">
          <div>
            @if($user->active && (int)auth()->id() !== (int)$user->id)
              <form method="POST" action="{{ route('admin.users.deactivate', $user) }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-danger" onclick="return confirm('¿Desactivar este usuario?')">
                  <i class="ti ti-user-off me-1"></i> Desactivar
                </button>
              </form>
            @endif

            @if(!$user->active)
              <form method="POST" action="{{ route('admin.users.reactivate', $user) }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-success" onclick="return confirm('¿Reactivar este usuario?')">
                  <i class="ti ti-user-check me-1"></i> Reactivar
                </button>
              </form>
            @endif
          </div>

          <button class="btn btn-primary">
            <i class="ti ti-device-floppy me-1"></i> Guardar
          </button>
        </div>
      </form>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Password</h3>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('admin.users.set_password', $user) }}" class="mb-3">
            @csrf
            <label class="form-label">Nuevo password</label>
            <input name="password" type="password" class="form-control" required minlength="8">
            <label class="form-label mt-2">Confirmar</label>
            <input name="password_confirmation" type="password" class="form-control" required minlength="8">
            <div class="mt-3">
              <button class="btn btn-outline-primary w-100">
                <i class="ti ti-key me-1"></i> Establecer password
              </button>
            </div>
          </form>

          <form method="POST" action="{{ route('admin.users.send_reset', $user) }}">
            @csrf
            <button class="btn btn-outline-secondary w-100">
              <i class="ti ti-mail me-1"></i> Enviar link de reset (requiere MAIL)
            </button>
          </form>

          <div class="text-muted small mt-2">
            Si no tienes correo configurado, usa “Establecer password”.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
