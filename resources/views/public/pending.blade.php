@extends('layouts.public')

@section('title','Cuenta pendiente')

@section('content')
<section class="py-5" style="background: linear-gradient(135deg, rgba(13,202,240,.08), rgba(10,88,202,.03) 60%, transparent 100%); border-bottom:1px solid rgba(0,0,0,.06);">
  <div class="container">
    <div class="text-center">
      <span class="badge rounded-pill text-bg-light border px-3 py-2">
        <i class="bi bi-shield-check me-1"></i> Activación requerida
      </span>
      <h1 class="mt-3 mb-1 fw-bold" style="letter-spacing:-.02em;">Tu central está pendiente</h1>
      <p class="text-muted mb-0">
        Para activar el acceso al panel, primero verifica tu correo.
      </p>
    </div>
  </div>
</section>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm border-0" style="border-radius: 18px;">
        <div class="card-body p-4 p-md-5">

          <div class="d-flex gap-3 align-items-start">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:44px;height:44px;background:rgba(255,193,7,.16);color:#b58100;">
              <i class="bi bi-envelope-exclamation fs-5"></i>
            </div>

            <div>
              <h2 class="h5 mb-2">Verifica tu correo para continuar</h2>
              <p class="text-muted mb-3">
                Te enviamos un enlace de verificación. Una vez confirmado, tu central quedará habilitada.
                Si ya verificaste, cierra sesión y entra de nuevo.
              </p>

              <div class="d-flex flex-column flex-sm-row gap-2">
                <a class="btn btn-primary" href="{{ route('verification.notice') }}">
                  <i class="bi bi-envelope-check me-1"></i> Verificar correo
                </a>

                <form method="POST" action="{{ route('logout') }}" class="m-0">
                  @csrf
                  <button class="btn btn-outline-secondary" type="submit">
                    <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesión
                  </button>
                </form>
              </div>

              <div class="small text-muted mt-3">
                Consejo: revisa también “Spam” o “Promociones”.
              </div>
            </div>
          </div>

        </div>
      </div>

      <div class="text-center small text-muted mt-4">
        ¿Necesitas ayuda? <a href="{{ url('/soporte') }}">Contacta soporte</a>
      </div>
    </div>
  </div>
</div>
@endsection
