@extends('layouts.admin')
@section('title','Nuevo usuario')

@section('content')
<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title">Crear usuario</h2>
        <div class="text-muted mt-1">Dispatcher o Admin (del mismo tenant)</div>
      </div>
      <div class="col-auto ms-auto">
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
          <i class="ti ti-arrow-left me-1"></i> Volver
        </a>
      </div>
    </div>
  </div>

  @if($errors->any())
    <div class="alert alert-danger">
      <div class="fw-semibold mb-1">Revisa el formulario</div>
      <ul class="mb-0">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  @php
    $role = old('role');
    if (!$role) $role = old('kind') ?: 'dispatcher';
  @endphp

  <form method="POST" action="{{ route('admin.users.store') }}" class="card" id="userCreateForm">
    @csrf

    {{-- compat con controller (store espera "kind") --}}
    <input type="hidden" name="kind" id="kind" value="{{ $role }}">

    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nombre</label>
          <input name="name" class="form-control" value="{{ old('name') }}" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input name="email" type="email" class="form-control" value="{{ old('email') }}" required>
        </div>

        <div class="col-12">
          <label class="form-label">Rol</label>

          <div class="d-flex flex-wrap gap-3">
            <label class="form-check">
              <input class="form-check-input" type="radio" name="role" value="dispatcher"
                     @checked($role === 'dispatcher')>
              <span class="form-check-label">Dispatcher (solo Dispatch)</span>
            </label>

            <label class="form-check">
              <input class="form-check-input" type="radio" name="role" value="admin"
                     @checked($role === 'admin')>
              <span class="form-check-label">Admin (Admin + Dispatch)</span>
            </label>
          </div>

          <div class="form-hint mt-1">
            Debe elegirse exactamente un rol.
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Password (opcional)</label>
          <input name="password" type="text" class="form-control" value="{{ old('password') }}"
                 placeholder="Si lo dejas vacío se genera uno temporal">
          <div class="form-hint">Mínimo 8 caracteres.</div>
        </div>
      </div>
    </div>

    <div class="card-footer text-end">
      <button class="btn btn-primary">
        <i class="ti ti-device-floppy me-1"></i> Crear
      </button>
    </div>
  </form>
</div>

<script>
(function(){
  function syncRoleToKind() {
    const role = document.querySelector('input[name="role"]:checked')?.value || 'dispatcher';
    const kind = document.getElementById('kind');
    if (kind) kind.value = role;
  }
  document.addEventListener('change', function(e){
    if (e.target && e.target.name === 'role') syncRoleToKind();
  });
  syncRoleToKind();
})();
</script>
@endsection
