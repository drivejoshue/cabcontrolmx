@extends('layouts.sysadmin')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl">
    <h2 class="page-title">Nuevo lugar sugerido</h2>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    <form method="POST" action="{{ route('sysadmin.city-places.store') }}">
      @csrf
      @include('sysadmin.city_places._form', ['place' => $place, 'cities' => $cities])
    </form>
  </div>
</div>
@endsection
