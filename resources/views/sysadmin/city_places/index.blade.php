@extends('layouts.sysadmin')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title">Lugares sugeridos por ciudad</h2>
        <div class="text-secondary">Solo SysAdmin · Alimenta sugerencias para Passenger</div>
      </div>
      <div class="col-auto ms-auto d-print-none">
        <a href="{{ route('sysadmin.city-places.create', ['city_id' => request('city_id')]) }}" class="btn btn-primary">
          Nuevo lugar
        </a>
      </div>
    </div>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form class="card mb-3" method="GET" action="{{ route('sysadmin.city-places.index') }}">
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-3">
            <label class="form-label">Ciudad</label>
            <select name="city_id" class="form-select">
              <option value="">Todas</option>
              @foreach($cities as $c)
                <option value="{{ $c->id }}" @selected((int)$cityId === (int)$c->id)>{{ $c->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label">Categoría</label>
            <select name="category" class="form-select">
              <option value="">Todas</option>
              @foreach($categories as $cat)
                <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label">Activo</label>
            <select name="active" class="form-select">
              <option value="">Todos</option>
              <option value="1" @selected($active==='1')>Sí</option>
              <option value="0" @selected($active==='0')>No</option>
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label">Featured</label>
            <select name="featured" class="form-select">
              <option value="">Todos</option>
              <option value="1" @selected($featured==='1')>Sí</option>
              <option value="0" @selected($featured==='0')>No</option>
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label">Buscar</label>
            <input name="q" value="{{ $q }}" class="form-control" placeholder="Label o dirección">
          </div>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="submit">Filtrar</button>
        <a class="btn btn-outline-secondary" href="{{ route('sysadmin.city-places.index') }}">Limpiar</a>
      </div>
    </form>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead>
            <tr>
              <th>Ciudad</th>
              <th>Lugar</th>
              <th>Categoría</th>
              <th>Prioridad</th>
              <th>Featured</th>
              <th>Activo</th>
              <th class="w-1"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($places as $p)
              <tr>
                <td class="text-secondary">{{ $p->city?->name }}</td>
                <td>
                  <div class="fw-semibold">{{ $p->label }}</div>
                  <div class="text-secondary small">{{ $p->address }}</div>
                  <div class="text-secondary small">{{ $p->lat }}, {{ $p->lng }}</div>
                </td>
                <td class="text-secondary">{{ $p->category }}</td>
                <td>{{ $p->priority }}</td>
                <td>{!! $p->is_featured ? '<span class="badge bg-azure-lt">Sí</span>' : '<span class="badge bg-secondary-lt">No</span>' !!}</td>
                <td>{!! $p->is_active ? '<span class="badge bg-green-lt">Sí</span>' : '<span class="badge bg-red-lt">No</span>' !!}</td>
                <td>
                  <div class="btn-list flex-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('sysadmin.city-places.show', $p) }}">Ver</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('sysadmin.city-places.edit', $p) }}">Editar</a>
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-secondary py-4">Sin registros</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="card-footer">
        {{ $places->links() }}
      </div>
    </div>

  </div>
</div>
@endsection
