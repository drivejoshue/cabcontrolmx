@extends('layouts.sysadmin')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title">{{ $city->name }}</h2>
        <div class="text-secondary">{{ $city->slug }} · {{ $city->timezone }}</div>
      </div>
      <div class="col-auto ms-auto">
        <a class="btn btn-outline-secondary" href="{{ route('sysadmin.cities.edit', $city) }}">Editar</a>
        <form class="d-inline" method="POST" action="{{ route('sysadmin.cities.destroy', $city) }}"
              onsubmit="return confirm('¿Eliminar esta ciudad? Se eliminarán también sus lugares sugeridos.')">
          @csrf
          @method('DELETE')
          <button class="btn btn-outline-danger">Eliminar</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row row-cards">

      <div class="col-md-7">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Detalles</h3>
          </div>
          <div class="card-body">
            <dl class="row">
              <dt class="col-4 text-secondary">ID</dt><dd class="col-8">{{ $city->id }}</dd>
              <dt class="col-4 text-secondary">Nombre</dt><dd class="col-8">{{ $city->name }}</dd>
              <dt class="col-4 text-secondary">Slug</dt><dd class="col-8">{{ $city->slug }}</dd>
              <dt class="col-4 text-secondary">Timezone</dt><dd class="col-8">{{ $city->timezone }}</dd>
              <dt class="col-4 text-secondary">Centro</dt><dd class="col-8">{{ $city->center_lat }}, {{ $city->center_lng }}</dd>
              <dt class="col-4 text-secondary">Radio</dt><dd class="col-8">{{ $city->radius_km }} km</dd>
              <dt class="col-4 text-secondary">Activa</dt><dd class="col-8">{{ $city->is_active ? 'Sí' : 'No' }}</dd>
            </dl>
          </div>
          <div class="card-footer">
            <a class="btn btn-outline-primary" href="{{ route('sysadmin.city_places.index', ['city_id' => $city->id]) }}">
              Ver lugares sugeridos
            </a>
            <a class="btn btn-primary" href="{{ route('sysadmin.city_places.create', ['city_id' => $city->id]) }}">
              Agregar lugar
            </a>
          </div>
        </div>
      </div>

      <div class="col-md-5">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Acciones rápidas</h3>
          </div>
          <div class="card-body d-flex flex-column gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('sysadmin.cities.edit', $city) }}">Editar ciudad</a>
            <a class="btn btn-outline-primary" href="{{ route('sysadmin.city_places.index', ['city_id' => $city->id]) }}">Filtrar lugares por ciudad</a>
            <a class="btn btn-outline-primary" href="{{ route('sysadmin.city_places.create', ['city_id' => $city->id]) }}">Nuevo lugar sugerido</a>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection
