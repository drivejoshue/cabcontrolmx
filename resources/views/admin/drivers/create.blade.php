@extends('layouts.admin')
@section('title','Nuevo conductor')
@section('content')
<h3 class="mb-3">Nuevo conductor</h3>
@if ($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
<form method="post" action="{{ route('drivers.store') }}" enctype="multipart/form-data">
  @include('admin.drivers._form', ['driver' => null])
</form>
@endsection
