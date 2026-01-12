@extends('layouts.sysadmin')
@section('title','Proveedor')

@section('content')
<div class="container-xl">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-0">Proveedor</h1>
      <div class="text-muted">Datos globales usados en términos, pagos y facturación.</div>
    </div>
    <a href="{{ route('sysadmin.provider-profiles.create') }}" class="btn btn-primary">
      <i class="ti ti-plus"></i> Nuevo
    </a>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif

  <div class="card">
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Activo</th>
            <th>Display</th>
            <th>Contacto</th>
            <th>Soporte</th>
            <th class="w-1"></th>
          </tr>
        </thead>
        <tbody>
          @foreach($items as $it)
          <tr>
            <td>{{ $it->id }}</td>
            <td>
              @if($it->active)
                <span class="badge bg-success-lt text-success">Activo</span>
              @else
                <span class="badge bg-secondary-lt text-secondary">No</span>
              @endif
            </td>
            <td class="fw-semibold">{{ $it->display_name }}</td>
            <td>{{ $it->contact_name }}</td>
            <td>{{ $it->email_support ?? '—' }}</td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="{{ route('sysadmin.provider-profiles.edit',$it) }}">
                <i class="ti ti-edit"></i> Editar
              </a>
              <form method="POST" action="{{ route('sysadmin.provider-profiles.destroy',$it) }}" class="d-inline"
                    onsubmit="return confirm('¿Eliminar este proveedor?');">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">
                  <i class="ti ti-trash"></i>
                </button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="card-footer">
      {{ $items->links() }}
    </div>
  </div>
</div>
@endsection
