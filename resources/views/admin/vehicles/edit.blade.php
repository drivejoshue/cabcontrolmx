@extends('layouts.admin')
@section('title','Editar vehículo')
@section('content')
<h3 class="mb-3">Editar vehículo</h3>
@if ($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
<form method="post" action="{{ route('vehicles.update',$v->id) }}" enctype="multipart/form-data">
  @method('PUT')
  @include('admin.vehicles._form', ['v' => $v])
</form>
@endsection
