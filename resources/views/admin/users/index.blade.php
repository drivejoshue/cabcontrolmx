@extends('layouts.admin')
@section('title','Usuarios')

@section('content')
<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title">Usuarios del tenant</h2>
        <div class="text-muted mt-1">Admins / Dispatchers / Drivers</div>
      </div>
      <div class="col-auto ms-auto">
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
          <i class="ti ti-user-plus me-1"></i> Nuevo usuario (staff)
        </a>
      </div>
    </div>
  </div>

  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif

  <div class="card">
    <div class="card-body border-bottom">
      <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 align-items-center">
        <div class="col">
          <input name="q" class="form-control" placeholder="Buscar por nombre o email..." value="{{ $q }}">
        </div>
        <div class="col-auto">
          <button class="btn btn-outline-primary">
            <i class="ti ti-search me-1"></i> Buscar
          </button>
        </div>
        @if($q)
          <div class="col-auto">
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Limpiar</a>
          </div>
        @endif
      </form>
    </div>

    <div class="card-header">
      <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs">
        <li class="nav-item">
          <a href="#tab-admins" class="nav-link active" data-bs-toggle="tab">
            <i class="ti ti-shield me-1"></i> Admins ({{ $admins->count() }})
          </a>
        </li>
        <li class="nav-item">
          <a href="#tab-dispatchers" class="nav-link" data-bs-toggle="tab">
            <i class="ti ti-headset me-1"></i> Dispatchers ({{ $dispatchers->count() }})
          </a>
        </li>
        <li class="nav-item">
          <a href="#tab-drivers" class="nav-link" data-bs-toggle="tab">
            <i class="ti ti-steering-wheel me-1"></i> Drivers ({{ $drivers->count() }})
          </a>
        </li>
       <li class="nav-item">
  <a href="#tab-inactive" class="nav-link" data-bs-toggle="tab">
    <i class="ti ti-user-off me-1"></i> Desactivados ({{ $inactive->count() }})
  </a>
</li>

      </ul>
    </div>

    <div class="tab-content">
      {{-- ADMINS --}}
      <div class="tab-pane active show" id="tab-admins">
        @include('admin.users._table', ['items'=>$admins, 'mode'=>'admin'])
      </div>

      {{-- DISPATCHERS --}}
      <div class="tab-pane" id="tab-dispatchers">
        @include('admin.users._table', ['items'=>$dispatchers, 'mode'=>'dispatcher'])
      </div>

      {{-- DRIVERS --}}
      <div class="tab-pane" id="tab-drivers">
        @include('admin.users._table_drivers', ['items'=>$drivers])
      </div>

      {{-- OTHERS --}}
     <div class="tab-pane" id="tab-inactive">
  @include('admin.users._table', ['items'=>$inactive, 'mode'=>'inactive'])
</div>

    </div>

  </div>
</div>
@endsection
