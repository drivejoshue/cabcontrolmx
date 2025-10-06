@extends('layouts.admin')
@section('title','Editar sector')

@section('content')
<div class="container-fluid p-0">
  <h3 class="mb-3">Editar sector</h3>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="post" action="{{ route('sectores.update', ['id' => $sector->id]) }}" id="formSector">
    @csrf
    @method('PUT')

    <div class="row g-3">
      <div class="col-12 col-xl-8">
        {{-- Mapa + textarea[name="area"] con precarga --}}
        @include('admin.sectores._form', ['sector' => $sector, 'geojsonUrl' => url('admin/sectores.geojson')])
      </div>

      <div class="col-12 col-xl-4">
        <div class="card">
          <div class="card-body">
            <h5 class="mb-3">Detalles</h5>

            <div class="mb-3">
              <label class="form-label">Nombre del sector</label>
              <input type="text" name="nombre" class="form-control"
                     value="{{ old('nombre', $sector->nombre ?? '') }}" required>
            </div>

            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" role="switch" id="activo"
                     name="activo" value="1" {{ old('activo', $sector->activo ?? 1) ? 'checked' : '' }}>
              <label class="form-check-label" for="activo">Activo</label>
            </div>

            <div class="d-grid gap-2">
              <button class="btn btn-success" type="submit">Actualizar</button>
              <a href="{{ route('sectores.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection
