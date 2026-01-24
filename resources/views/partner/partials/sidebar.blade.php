<?php
use Illuminate\Support\Facades\Route;

$u = auth()->user();

/** @var \App\Models\Partner|null $partner */
$partner = request()->attributes->get('partner') ?? null;

// Branding
$brandName  = $partner?->name ? $partner->name : 'Partner';
$brandSub   = 'Portal de Partners';
$logoCircle = asset('images/logonf.png');

// Home partner
$homeUrl = Route::has('partner.dashboard')
  ? route('partner.dashboard')
  : url('/partner/dashboard');

// Activo
$isActive = function ($patterns): bool {
    $patterns = is_array($patterns) ? $patterns : [$patterns];
    foreach ($patterns as $p) {
        if (request()->routeIs($p)) return true;
    }
    return false;
};
?>

<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
  <div class="container-fluid">

    {{-- Brand --}}
    <h1 class="navbar-brand navbar-brand-autodark">
      <a class="d-flex align-items-center gap-2 text-decoration-none" href="{{ $homeUrl }}">
        <img src="{{ $logoCircle }}" alt="Orbana" width="28" height="28" class="orb-brand-circle">
        <span class="d-flex flex-column lh-sm">
          <span class="orb-brand-title">{{ $brandName }}</span>
          <small class="orb-brand-sub">{{ $brandSub }}</small>
        </span>
      </a>
    </h1>

    {{-- Toggler mobile --}}
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu-partner"
            aria-controls="sidebar-menu-partner" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="sidebar-menu-partner">
      <ul class="navbar-nav pt-lg-3">

        <li class="nav-item">
          <div class="nav-link text-uppercase text-muted fw-semibold small">Inicio</div>
        </li>

        <li class="nav-item {{ $isActive('partner.dashboard') ? 'active' : '' }}">
          <a class="nav-link" href="{{ route('partner.dashboard') }}"
             aria-current="{{ $isActive('partner.dashboard') ? 'page' : 'false' }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-home"></i></span>
            <span class="nav-link-title">Dashboard</span>
          </a>
        </li>

        <li class="nav-item mt-3">
          <div class="nav-link text-uppercase text-muted fw-semibold small">Finanzas</div>
        </li>

        

        <li class="nav-item {{ $isActive('partner.drivers.*') ? 'active' : '' }}">
  <a class="nav-link" href="{{ route('partner.drivers.index') }}"
     aria-current="{{ $isActive('partner.drivers.*') ? 'page' : 'false' }}">
    <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-user"></i></span>
    <span class="nav-link-title">Conductores</span>
  </a>
</li>

<li class="nav-item {{ $isActive('partner.vehicles.*') ? 'active' : '' }}">
  <a class="nav-link" href="{{ route('partner.vehicles.index') }}"
     aria-current="{{ $isActive('partner.vehicles.*') ? 'page' : 'false' }}">
    <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-car"></i></span>
    <span class="nav-link-title">Vehículos</span>
  </a>
</li>

<li class="nav-item {{ $isActive('partner.topups.*') ? 'active' : '' }}">
  <a class="nav-link" href="{{ route('partner.topups.index') }}"
     aria-current="{{ $isActive('partner.topups.*') ? 'page' : 'false' }}">
    <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-cash"></i></span>
    <span class="nav-link-title">Recargas</span>
  </a>
</li>
<li class="nav-item {{ $isActive('partner.wallet.*') ? 'active' : '' }}">
          <a class="nav-link" href="{{ route('partner.wallet.index') }}"
             aria-current="{{ $isActive('partner.wallet.*') ? 'page' : 'false' }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-wallet"></i></span>
            <span class="nav-link-title">Wallet</span>
          </a>
        </li>

     {{-- Reportes --}}
<li class="nav-item mt-3">
  <div class="nav-link text-uppercase text-muted fw-semibold small">Reportes</div>
</li>

<li class="nav-item {{ request()->routeIs('partner.reports.rides.*') ? 'active' : '' }}">
  <a class="nav-link" href="{{ route('partner.reports.rides.index') }}">
    <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-road"></i></span>
    <span class="nav-link-title">Viajes</span>
  </a>
</li>

<li class="nav-item {{ request()->routeIs('partner.reports.driver_quality.*') ? 'active' : '' }}">
  <a class="nav-link" href="{{ route('partner.reports.driver_quality.index') }}">
    <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-user"></i></span>
    <span class="nav-link-title">Quality</span>
  </a>
</li>

<li class="nav-item {{ request()->routeIs('partner.reports.vehicles.*') ? 'active' : '' }}">
  <a class="nav-link" href="{{ route('partner.reports.vehicles.index') }}">
    <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-car"></i></span>
    <span class="nav-link-title">Vehículos</span>
  </a>
</li>
<li class="nav-item mt-3">
  <div class="nav-link text-uppercase text-muted fw-semibold small">Monitor</div>
</li>

<li class="nav-item {{ request()->routeIs('partner.monitor.*') ? 'active' : '' }}">
  <a class="nav-link" href="{{ route('partner.monitor.index') }}">
    <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-radar"></i></span>
    <span class="nav-link-title">Monitor</span>
  </a>
</li>



        {{-- Placeholder: documentos --}}
        <li class="nav-item mt-3">
          <div class="nav-link text-uppercase text-muted fw-semibold small">Cuenta</div>
        </li>

      <li class="nav-item">
  <a class="nav-link" href="{{ route('partner.inbox.index') }}">
    <span class="nav-link-icon d-md-none d-lg-inline-block">
      <i class="ti ti-bell"></i>
    </span>
    <span class="nav-link-title">Notificaciones</span>
  </a>
</li>

<li class="nav-item">
  <a class="nav-link" href="{{ route('partner.support.index') }}">
    <span class="nav-link-icon d-md-none d-lg-inline-block">
      <i class="ti ti-help"></i>
    </span>
    <span class="nav-link-title">Soporte</span>
  </a>
</li>


      </ul>
    </div>
  </div>
</aside>

@push('styles')
<style>
  .orb-brand-circle{
    border-radius: 999px;
    object-fit: cover;
    box-shadow: 0 0 0 2px rgba(255,255,255,.08);
  }
  .orb-brand-title{
    font-weight: 800;
    letter-spacing: .02em;
    line-height: 1.05;
  }
  .orb-brand-sub{
    opacity: .65;
    font-weight: 600;
  }
  .navbar-vertical .nav-item.active > .nav-link{
    background: rgba(255,255,255,.06);
    border-radius: .5rem;
  }
  /* deshabilitados visual */
  .navbar-vertical .nav-link[aria-disabled="true"]{
    opacity: .75;
    cursor: default;
  }
</style>
@endpush
