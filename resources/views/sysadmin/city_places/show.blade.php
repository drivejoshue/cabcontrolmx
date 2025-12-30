@extends('layouts.sysadmin')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title">{{ $place->label }}</h2>
        <div class="text-secondary">{{ $place->city?->name }} · {{ $place->category }}</div>
      </div>
      <div class="col-auto ms-auto">
        <a class="btn btn-outline-secondary" href="{{ route('sysadmin.city-places.edit', $place) }}">Editar</a>
        <form class="d-inline" method="POST" action="{{ route('sysadmin.city-places.destroy', $place) }}"
              onsubmit="return confirm('¿Eliminar este lugar?')">
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

    <div class="card">
      <div class="card-body">
        <dl class="row">
          <dt class="col-3 text-secondary">Ciudad</dt><dd class="col-9">{{ $place->city?->name }}</dd>
          <dt class="col-3 text-secondary">Dirección</dt><dd class="col-9">{{ $place->address }}</dd>
          <dt class="col-3 text-secondary">Coordenadas</dt><dd class="col-9">{{ $place->lat }}, {{ $place->lng }}</dd>
          <dt class="col-3 text-secondary">Prioridad</dt><dd class="col-9">{{ $place->priority }}</dd>
          <dt class="col-3 text-secondary">Featured</dt><dd class="col-9">{{ $place->is_featured ? 'Sí' : 'No' }}</dd>
          <dt class="col-3 text-secondary">Activo</dt><dd class="col-9">{{ $place->is_active ? 'Sí' : 'No' }}</dd>
        </dl>

        <a class="btn btn-outline-primary" href="{{ route('sysadmin.city-places.index', ['city_id' => $place->city_id]) }}">
          Ver todos en esta ciudad
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
