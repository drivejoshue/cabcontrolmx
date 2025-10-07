@extends('layouts.admin')
@section('title','Editar conductor')
@section('content')
<h3 class="mb-3">Editar conductor</h3>
@if ($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
<form method="post" action="{{ route('drivers.update',$driver->id) }}" enctype="multipart/form-data">
  @method('PUT')
  @include('admin.drivers._form', ['driver' => $driver])
</form>
@endsection
