@extends('layouts.admin')
@section('title','Nuevo conductor')

@section('content')
<h3 class="mb-3">Nuevo conductor</h3>

@if ($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="post" action="{{ route('admin.drivers.store') }}" enctype="multipart/form-data" class="needs-validation" novalidate>
  @include('admin.drivers._fields', [
    'driver' => (object)[],   // o null; el partial usa $driver->
    'method' => null          // no hace falta @method
  ])
</form>
@endsection
