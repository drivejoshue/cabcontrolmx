
@extends('layouts.admin')
@section('title','Nuevo sector')

@push('styles')
  <!-- (Opcional) estilos globales extra para esta vista -->
@endpush

@section('content')
<div class="container-fluid p-0">
  <h3 class="mb-3">Nuevo sector</h3>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="post" action="{{ route('sectores.store') }}" id="formSector">
    @csrf

    <div class="row g-3">
      <div class="col-12 col-xl-8">
        {{-- Mapa + textarea[name="area"] --}}
        @include('sectores._form', ['sector' => null])
      </div>

      <div class="col-12 col-xl-4">
        <div class="card">
          <div class="card-body">
            <h5 class="mb-3">Detalles</h5>

            <div class="mb-3">
              <label class="form-label">Nombre del sector</label>
              <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required>
            </div>

            <div class="d-grid gap-2">
              <button class="btn btn-primary" type="submit">Guardar sector</button>
              <a href="{{ route('sectores.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection

@push('scripts')
  <!-- (Opcional) scripts globales extra para esta vista -->
@endpush
