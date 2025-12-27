@extends('layouts.admin')
@section('title','Usuarios')

@section('content')
<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title">Usuarios del tenant</h2>
        <div class="text-muted mt-1">Admins y Dispatchers (staff interno)</div>
      </div>
      <div class="col-auto ms-auto">
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
          <i class="ti ti-user-plus me-1"></i> Nuevo usuario
        </a>
      </div>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
  @endif

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Listado</h3>
    </div>

    <div class="table-responsive">
      <table class="table card-table table-vcenter">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Email</th>
            <th>Rol</th>
            <th class="text-muted">Creado</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($items as $u)
            @php
              $isMe = auth()->check() && (int)auth()->id() === (int)$u->id;
            @endphp

            <tr>
              <td class="fw-medium">
                {{ $u->name }}
                @if($isMe)
                  <span class="badge bg-secondary-lt ms-2">Tú</span>
                @endif
              </td>

              <td class="text-muted">{{ $u->email }}</td>

              <td>
                @if(!empty($u->is_admin))
                  <span class="badge bg-indigo-lt">
                    <i class="ti ti-shield me-1"></i> Admin
                  </span>
                @else
                  <span class="badge bg-azure-lt">
                    <i class="ti ti-headset me-1"></i> Dispatcher
                  </span>
                @endif
              </td>

              <td class="text-muted">{{ optional($u->created_at)->format('Y-m-d H:i') }}</td>

              <td class="text-end">
                <div class="btn-list flex-nowrap justify-content-end">
                  <a class="btn btn-sm btn-outline-primary"
                     href="{{ route('admin.users.edit', $u) }}">
                    <i class="ti ti-pencil me-1"></i> Editar
                  </a>

                  {{-- Acceso rápido a Dispatch: admin y dispatcher --}}
                  @if(!empty($u->is_admin) || !empty($u->is_dispatcher))
                    <a class="btn btn-sm btn-outline-secondary"
                       href="{{ route('dispatch') }}"
                       title="Abrir Dispatch">
                      <i class="ti ti-route me-1"></i> Ir a Dispatch
                    </a>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted py-4">No hay usuarios.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
