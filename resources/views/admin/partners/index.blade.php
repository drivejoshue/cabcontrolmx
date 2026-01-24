@extends('layouts.admin')

@section('title', 'Partners')

@section('content')
<div class="container-fluid">

  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 mb-0">Partners</h1>
      <div class="text-muted small">Administración de partners del tenant.</div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('admin.partners.create') }}" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i> Nuevo partner
      </a>
    </div>
  </div>

  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  <div class="card mb-3">
    <div class="card-body">
      <form class="row g-2" method="GET" action="{{ route('admin.partners.index') }}">
        <div class="col-md-8">
          <div class="input-icon">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="text" name="q" class="form-control" value="{{ $q ?? '' }}"
                   placeholder="Buscar por nombre, code, email o teléfono">
          </div>
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button class="btn btn-outline-primary" type="submit">Buscar</button>
          <a class="btn btn-outline-secondary" href="{{ route('admin.partners.index') }}">Limpiar</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Code</th>
            <th>Nombre</th>
            <th>Estado</th>
            <th>Contacto</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($items as $p)
            @php
              $st = $p->status ?? 'active';
              $stClass = match($st) {
                'active' => 'bg-success-lt text-success',
                'suspended' => 'bg-warning-lt text-warning',
                'closed' => 'bg-danger-lt text-danger',
                default => 'bg-secondary-lt text-secondary',
              };
            @endphp
            <tr>
              <td class="text-muted">{{ $p->id }}</td>
              <td><code>{{ $p->code }}</code></td>
              <td class="fw-semibold">{{ $p->name }}</td>
              <td>
                <span class="badge {{ $stClass }}">{{ strtoupper($st) }}</span>
                @if($p->is_active)
                  <span class="badge bg-success-lt text-success ms-1">ACTIVO</span>
                @else
                  <span class="badge bg-secondary-lt text-secondary ms-1">INACTIVO</span>
                @endif
              </td>
              <td class="small">
                <div>{{ $p->contact_name ?: '—' }}</div>
                <div class="text-muted">{{ $p->contact_email ?: '' }} {{ $p->contact_phone ? ' · '.$p->contact_phone : '' }}</div>
              </td>
              <td class="text-end">
                <div class="btn-list justify-content-end">
                  <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.partners.show', $p) }}">
                    <i class="ti ti-eye me-1"></i> Ver
                  </a>
                  <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.partners.edit', $p) }}">
                    <i class="ti ti-edit me-1"></i> Editar
                  </a>
                  <form method="POST" action="{{ route('admin.partners.destroy', $p) }}"
                        onsubmit="return confirm('¿Eliminar partner? (soft delete)')">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm" type="submit">
                      <i class="ti ti-trash me-1"></i> Eliminar
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-4">Sin partners aún.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $items->links() }}
    </div>
  </div>

</div>
@endsection
