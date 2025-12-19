@extends('layouts.public')
@section('title','Orbana — Gestión de taxis y despacho')

@section('content')
<section class="hero-grad">
  <div class="container py-5">
    <div class="row align-items-center g-4">
      <div class="col-lg-6">
        <span class="badge text-bg-primary-subtle border border-primary-subtle mb-3">
          Plataforma para Centrales y Flotas
        </span>
        <h1 class="display-6 fw-bold mb-3">
          Despacho moderno para tu central de taxis
        </h1>
        <p class="lead text-muted mb-4">
          Controla viajes, conductores, ofertas y métricas en tiempo real.
          Apps para conductor y pasajero, panel web y operación multi-tenant.
        </p>

        <div class="d-flex flex-column flex-sm-row gap-2">
          <a class="btn btn-primary btn-lg" href="{{ route('public.signup') }}">
            Crear mi central
          </a>
          <a class="btn btn-outline-secondary btn-lg" href="#pricing">
            Ver planes
          </a>
        </div>

        <div class="d-flex gap-3 mt-4 small text-muted">
          <div><i class="bi bi-shield-check me-1"></i> Seguro</div>
          <div><i class="bi bi-lightning-charge me-1"></i> Tiempo real</div>
          <div><i class="bi bi-geo-alt me-1"></i> Mapa operativo</div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card card-soft shadow-sm">
          <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <div class="fw-semibold">Vista previa</div>
              <span class="badge text-bg-light border">Dispatch</span>
            </div>
            <div class="ratio ratio-16x9 bg-body-tertiary rounded-3 border">
              <div class="d-flex align-items-center justify-content-center text-muted">
                Aquí puedes poner screenshot / mock del panel
              </div>
            </div>
            <div class="row g-3 mt-3">
              <div class="col-6">
                <div class="p-3 bg-body-tertiary rounded-3 border">
                  <div class="small text-muted">Rides activos</div>
                  <div class="h5 mb-0">—</div>
                </div>
              </div>
              <div class="col-6">
                <div class="p-3 bg-body-tertiary rounded-3 border">
                  <div class="small text-muted">Conductores online</div>
                  <div class="h5 mb-0">—</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<section class="py-5 bg-white border-top">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="h3 mb-2">Todo lo que necesitas para operar</h2>
      <p class="text-muted mb-0">Sin complicaciones. Enfocado a centrales y flotas reales.</p>
    </div>

    <div class="row g-3">
      @php
        $features = [
          ['bi bi-diagram-3','Despacho inteligente','Olas de ofertas, prioridades por base y control de estados.'],
          ['bi bi-map','Mapa operativo','Visualiza rides, rutas y drivers con movimientos suaves y estados.'],
          ['bi bi-phone','Apps Driver / Passenger','Flujos claros, notificaciones, y experiencia tipo marketplace.'],
          ['bi bi-bar-chart','Reportes','Métricas operativas por rango, conductor y estados de viaje.'],
          ['bi bi-gear','Multi-tenant','Cada central aislada: datos, settings, timezone y facturación.'],
          ['bi bi-lock','Seguridad','Roles, auditoría y verificación para evitar registros basura.'],
        ];
      @endphp

      @foreach($features as [$icon,$title,$desc])
        <div class="col-12 col-md-6 col-lg-4">
          <div class="card card-soft h-100">
            <div class="card-body">
              <div class="d-flex gap-3">
                <div class="fs-4 text-primary"><i class="{{ $icon }}"></i></div>
                <div>
                  <div class="fw-semibold">{{ $title }}</div>
                  <div class="text-muted small">{{ $desc }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

<section class="py-5 bg-light border-top" id="pricing">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="h3 mb-2">Planes</h2>
      <p class="text-muted mb-0">Empieza con prueba y escala cuando estés listo.</p>
    </div>

    <div class="row g-3 justify-content-center">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card card-soft h-100 shadow-sm">
          <div class="card-body p-4">
            <div class="fw-semibold">Starter</div>
            <div class="display-6 fw-bold my-2">$—</div>
            <div class="text-muted small mb-3">Ideal para pruebas o flotillas pequeñas.</div>
            <ul class="small text-muted">
              <li>Incluye X vehículos</li>
              <li>Panel + app driver</li>
              <li>Soporte básico</li>
            </ul>
            <a class="btn btn-primary w-100" href="{{ route('public.signup') }}">Empezar</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-6 col-lg-4">
        <div class="card card-soft h-100 border-primary shadow-sm">
          <div class="card-body p-4">
            <div class="fw-semibold">Pro</div>
            <div class="display-6 fw-bold my-2">$—</div>
            <div class="text-muted small mb-3">Para centrales en operación diaria.</div>
            <ul class="small text-muted">
              <li>Más vehículos</li>
              <li>Reportes avanzados</li>
              <li>Prioridad en soporte</li>
            </ul>
            <a class="btn btn-outline-primary w-100" href="{{ route('public.signup') }}">Crear central</a>
          </div>
        </div>
      </div>
    </div>

    <div class="text-center small text-muted mt-3">
      Los precios y límites se ajustan a tu modelo de facturación real.
    </div>
  </div>
</section>

<section class="py-5 bg-white border-top">
  <div class="container">
    <div class="row g-4 align-items-center">
      <div class="col-lg-7">
        <h2 class="h3 mb-2">¿Listo para operar con Orbana?</h2>
        <p class="text-muted mb-0">
          Registra tu central en minutos y empieza con el onboarding.
        </p>
      </div>
      <div class="col-lg-5 text-lg-end">
        <a class="btn btn-primary btn-lg" href="{{ route('public.signup') }}">
          Crear mi central
        </a>
      </div>
    </div>
  </div>
</section>
@endsection
