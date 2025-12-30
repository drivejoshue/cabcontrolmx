@extends('layouts.sysadmin')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl">
    <h2 class="page-title">Editar ciudad</h2>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    <form method="POST" action="{{ route('sysadmin.cities.update', $city) }}">
      @csrf
      @method('PUT')
      @include('sysadmin.cities._form', ['city' => $city])
    </form>
  </div>
</div>
@endsection
