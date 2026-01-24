@extends('layouts.partner')
@section('title','Editar vehículo')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Editar vehículo</h3>
      <div class="text-muted">Puedes actualizar datos y suspender el vehículo si es necesario.</div>
    </div>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('partner.vehicles.update',$v->id) }}" enctype="multipart/form-data">
    @method('PUT')
    @include('shared.vehicles._form', [
      'v' => $v,
      'routePrefix' => 'partner',
      'cancelUrl' => route('partner.vehicles.index'),
      'showBilling' => false,
      'canRegister' => true,
      'vehicleCatalog' => $vehicleCatalog ?? collect(),
    ])
  </form>
</div>
@endsection
