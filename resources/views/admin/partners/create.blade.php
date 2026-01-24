@extends('layouts.admin')

@section('title', 'Nuevo partner')

@section('content')
<div class="container-fluid">

  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 mb-0">Nuevo partner</h1>
      <div class="text-muted small">Alta de partner para el tenant actual.</div>
    </div>
    <a href="{{ route('admin.partners.index') }}" class="btn btn-outline-secondary">
      <i class="ti ti-arrow-left me-1"></i> Volver
    </a>
  </div>

  @if($errors->any())
    <div class="alert alert-danger">
      <div class="fw-semibold mb-1">Revisa los campos:</div>
      <ul class="mb-0">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.partners.store') }}">
    @csrf

    @include('admin.partners._form')

    <div class="d-flex justify-content-end gap-2 mt-3">
      <a href="{{ route('admin.partners.index') }}" class="btn btn-outline-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit">
        <i class="ti ti-device-floppy me-1"></i> Guardar
      </button>
    </div>
  </form>

</div>
@endsection
