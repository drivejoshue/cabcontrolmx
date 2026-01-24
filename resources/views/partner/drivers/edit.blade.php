@extends('layouts.partner')
@section('title','Editar conductor')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Editar conductor</h3>
      <div class="text-muted">Puedes actualizar datos y suspender acceso si es necesario.</div>
    </div>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('partner.drivers.update',$driver->id) }}" enctype="multipart/form-data">
    @include('shared.drivers._form', [
      'driver' => $driver,
      'method' => 'PUT',
      'routePrefix' => 'partner',
      'cancelUrl' => route('partner.drivers.index'),
      'canEditStatus' => false,
    ])
  </form>
</div>
@endsection
