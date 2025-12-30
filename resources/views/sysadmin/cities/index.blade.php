@extends('layouts.sysadmin')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title">Ciudades</h2>
        <div class="text-secondary">Solo SysAdmin · Catálogo base para sugerencias y onboarding</div>
      </div>
      <div class="col-auto ms-auto d-print-none">
        <a href="{{ route('sysadmin.cities.create') }}" class="btn btn-primary">
          Nueva ciudad
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

    <form class="card mb-3" method="GET" action="{{ route('sysadmin.cities.index') }}">
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-8">
            <label class="form-label">Buscar</label>
            <input name="q" value="{{ $q }}" class="form-control" placeholder="Nombre o slug">
          </div>
          <div class="col-md-4 d-flex align-items-end gap-2">
            <button class="btn btn-primary" type="submit">Buscar</button>
            <a class="btn btn-outline-secondary" href="{{ route('sysadmin.cities.index') }}">Limpiar</a>
          </div>
        </div>
      </div>
    </form>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Slug</th>
              <th>Timezone</th>
              <th>Centro</th>
              <th>Radio (km)</th>
              <th>Activo</th>
              <th class="w-1"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($cities as $c)
              <tr>
                <td>
                  <div class="fw-semibold">{{ $c->name }}</div>
                  <div class="text-secondary small">ID: {{ $c->id }}</div>
                </td>
                <td class="text-secondary">{{ $c->slug }}</td>
                <td class="text-secondary">{{ $c->timezone }}</td>
                <td class="text-secondary small">
                  {{ $c->center_lat }}, {{ $c->center_lng }}
                </td>
                <td>{{ $c->radius_km }}</td>
                <td>{!! $c->is_active ? '<span class="badge bg-green-lt">Sí</span>' : '<span class="badge bg-red-lt">No</span>' !!}</td>
                <td>
                  <div class="btn-list flex-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('sysadmin.cities.show', $c) }}">Ver</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('sysadmin.cities.edit', $c) }}">Editar</a>
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
        {{ $cities->links() }}
      </div>
    </div>

  </div>
</div>
@endsection
