@extends('layouts.public')
@section('title','Verifica tu correo')

@section('content')
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          <h1 class="h4 mb-2">Verifica tu correo</h1>
          <p class="text-muted mb-3">
            Te enviamos un enlace de verificación. Abre tu correo y confirma para activar tu central.
          </p>

          @if (session('status') === 'verification-link-sent')
            <div class="alert alert-success">
              Enlace de verificación reenviado.
            </div>
          @endif

          <form method="POST" action="{{ route('verification.send') }}" class="d-inline">
            @csrf
            <button class="btn btn-primary">Reenviar enlace</button>
          </form>

          <form method="POST" action="{{ route('logout') }}" class="d-inline ms-2">
            @csrf
            <button class="btn btn-outline-secondary">Cerrar sesión</button>
          </form>

          <div class="small text-muted mt-3">
            Si ya verificaste, intenta entrar al panel.
          </div>

          <a href="{{ route('go') }}" class="btn btn-link px-0 mt-2">Ir al panel</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
