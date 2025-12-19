@extends('layouts.public')
@section('title','Cuenta pendiente')
@section('content')
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          <h1 class="h4 mb-2">Tu central está pendiente de activación</h1>
          <p class="text-muted mb-3">
            Verifica tu correo para activar el acceso al panel. Si ya lo verificaste, intenta cerrar sesión y entrar de nuevo.
          </p>

          <div class="d-flex gap-2">
            <a class="btn btn-primary" href="{{ route('verification.notice') }}">Verificar correo</a>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button class="btn btn-outline-secondary" type="submit">Cerrar sesión</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
