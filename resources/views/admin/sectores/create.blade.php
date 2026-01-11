@extends('layouts.admin')
@section('title','Nuevo sector')

@section('content')
<div class="container-fluid px-0">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h3 class="mb-0">Nuevo sector</h3>
      <div class="text-muted" style="font-size:.85rem;">Dibuja el polígono y guarda los detalles.</div>
    </div>
    <a href="{{ route('admin.sectores.index') }}" class="btn btn-outline-secondary">
      <i data-feather="arrow-left"></i> Volver
    </a>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">Corrige los siguientes campos:</div>
      <ul class="mb-0">
        @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  <form method="post" action="{{ route('admin.sectores.store') }}" id="formSector">
    @csrf

    <div class="row g-3">
      <div class="col-12 col-xl-8">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title mb-0">Área del sector</h3>
          </div>
          <div class="card-body">
            {{-- Mapa + textarea[name="area"] --}}
            @include('admin.sectores._form', ['sector' => null])
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-4">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title mb-0">Detalles</h3>
          </div>

          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Nombre del sector</label>
              <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required>
            </div>

            <div class="alert alert-info py-2" style="font-size:.85rem;">
              Puedes crear varios paraderos dentro de este sector. El radio de alcance se define en el core.
            </div>
          </div>

          <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('admin.sectores.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-primary" type="submit">
              <i data-feather="save"></i> Guardar sector
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection
