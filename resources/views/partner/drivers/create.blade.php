@extends('layouts.partner')
@section('title','Nuevo conductor')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Nuevo conductor</h3>
      <div class="text-muted">Captura los datos y después sube documentos para verificación.</div>
    </div>
  </div>

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('partner.drivers.store') }}" enctype="multipart/form-data">
    @include('shared.drivers._form', [
      'driver' => $driver ?? null,
      'method' => null,
      'routePrefix' => 'partner',
      'cancelUrl' => route('partner.drivers.index'),
      'canEditStatus' => false,
    ])
  </form>
</div>
@endsection
