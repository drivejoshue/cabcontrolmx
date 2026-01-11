@extends('layouts.admin_tabler')

@section('title', 'Dispatch bloqueado')

@section('content')
<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6">
    <div class="card">
      <div class="card-body">
        <h2 class="mb-2">Servicio pausado</h2>
        <p class="text-muted mb-3">
          Este tenant no puede operar Dispatch por facturación.
        </p>

        <div class="alert alert-warning">
          <div><strong>{{ $message }}</strong></div>
          <div class="small text-muted mt-1">Código: {{ $code }}</div>
        </div>

        <div class="d-flex gap-2">
          <a class="btn btn-primary" href="{{ route('admin.billing.plan') }}">
            Ir a facturación
          </a>
          <a class="btn btn-outline-secondary" href="{{ route('admin.dashboard') }}">
            Volver al admin
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
