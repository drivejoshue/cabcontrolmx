@extends('layouts.partner')
@section('title','Nuevo vehículo')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Nuevo vehículo</h3>
      <div class="text-muted">Registra el vehículo y luego sube documentos para verificación.</div>
    </div>
  </div>

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('partner.vehicles.store') }}" enctype="multipart/form-data">
    @include('shared.vehicles._form', [
      'v' => $v ?? null,
      'routePrefix' => 'partner',
      'cancelUrl' => route('partner.vehicles.index'),
      'showBilling' => false,
      'canRegister' => $canRegister ?? true,
      'vehicleCatalog' => $vehicleCatalog ?? collect(),
    ])
  </form>
</div>
@endsection
