@extends('layouts.admin')
@section('title','Nuevo vehículo')
@section('content')
<h3 class="mb-3">Nuevo vehículo</h3>
@if ($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
<form method="post" action="{{ route('admin.vehicles.store') }}" enctype="multipart/form-data">
  @include('admin.vehicles._form', ['v' => null])
</form>
@endsection
