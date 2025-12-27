@extends('layouts.public')
@section('title','Verifica tu correo')

@section('content')
<section class="py-5" style="background: linear-gradient(135deg, rgba(13,202,240,.08), rgba(10,88,202,.03) 60%, transparent 100%); border-bottom:1px solid rgba(0,0,0,.06);">
  <div class="container">
    <div class="text-center">
      <span class="badge rounded-pill text-bg-light border px-3 py-2">
        <i class="bi bi-envelope me-1"></i> Verificación de correo
      </span>
      <h1 class="mt-3 mb-1 fw-bold" style="letter-spacing:-.02em;">Confirma tu correo</h1>
      <p class="text-muted mb-0">
        Necesitamos validar tu email para activar la central y habilitar el panel.
      </p>
    </div>
  </div>
</section>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm border-0" style="border-radius: 18px;">
        <div class="card-body p-4 p-md-5">

          @if (session('status') === 'verification-link-sent')
            <div class="alert alert-success d-flex gap-2 align-items-start">
              <i class="bi bi-check-circle mt-1"></i>
              <div>Enlace de verificación reenviado.</div>
            </div>
          @endif

          <div class="d-flex gap-3 align-items-start">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:44px;height:44px;background:rgba(13,202,240,.14);color:#0aa2c0;">
              <i class="bi bi-envelope-open fs-5"></i>
            </div>

            <div>
              <h2 class="h5 mb-2">Revisa tu bandeja de entrada</h2>
              <p class="text-muted mb-3">
                Abre el correo y da clic en el enlace de confirmación.
                Si no lo encuentras, puedes reenviarlo.
              </p>

              <div class="d-flex flex-column flex-sm-row gap-2">
                <form method="POST" action="{{ route('verification.send') }}" class="m-0">
                  @csrf
                  <button class="btn btn-primary">
                    <i class="bi bi-arrow-repeat me-1"></i> Reenviar enlace
                  </button>
                </form>

                <form method="POST" action="{{ route('logout') }}" class="m-0">
                  @csrf
                  <button class="btn btn-outline-secondary">
                    <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesión
                  </button>
                </form>
              </div>

              <div class="small text-muted mt-3">
                Si ya verificaste, intenta entrar al panel.
              </div>

              <a href="{{ route('go') }}" class="btn btn-link px-0 mt-2">
                <i class="bi bi-arrow-right me-1"></i> Ir al panel
              </a>
            </div>
          </div>

          <hr class="my-4">

          <div class="small text-muted">
            Recomendación: revisa “Spam” y asegúrate de que tu servidor tenga correo saliente configurado (SMTP).
          </div>

        </div>
      </div>

      <div class="text-center small text-muted mt-4">
        ¿Problemas con el correo? <a href="{{ url('/soporte') }}">Soporte</a>
      </div>
    </div>
  </div>
</div>
@endsection
