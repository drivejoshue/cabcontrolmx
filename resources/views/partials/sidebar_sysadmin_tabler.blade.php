<?php
use Illuminate\Support\Facades\Route;

$brandName  = 'Orbana Core';
$brandSub   = 'SysAdmin';
$logoCircle = asset('images/logonf.png');

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
    <h1 class="navbar-brand navbar-brand-autodark">
      <a class="d-flex align-items-center gap-2 text-decoration-none" href="{{ route('sysadmin.dashboard') }}">
        <img src="{{ $logoCircle }}" alt="Orbana" width="28" height="28" class="orb-brand-circle">
        <span class="d-flex flex-column lh-sm">
          <span class="orb-brand-title">{{ $brandName }}</span>
          <small class="orb-brand-sub">{{ $brandSub }}</small>
        </span>
      </a>
    </h1>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu-sys"
            aria-controls="sidebar-menu-sys" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="sidebar-menu-sys">
      <ul class="navbar-nav pt-lg-3">
        {{-- CORE --}}
        <li class="nav-item">
          <div class="nav-link text-uppercase text-muted fw-semibold small">Core</div>
        </li>
        <li class="nav-item {{ $isActive('sysadmin.dashboard') ? 'active' : '' }}">
          <a class="nav-link" href="{{ route('sysadmin.dashboard') }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-home"></i></span>
            <span class="nav-link-title">Dashboard</span>
          </a>
        </li>

        {{-- TENANTS --}}
        <li class="nav-item mt-3">
          <div class="nav-link text-uppercase text-muted fw-semibold small">Tenants</div>
        </li>
        <li class="nav-item {{ $isActive(['sysadmin.tenants.index','sysadmin.tenants.create','sysadmin.tenants.edit','sysadmin.tenants.billing.*']) ? 'active' : '' }}">
          <a class="nav-link" href="{{ route('sysadmin.tenants.index') }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-layers"></i></span>
            <span class="nav-link-title">Lista de tenants</span>
          </a>
        </li>
        <li class="nav-item {{ $isActive('sysadmin.tenants.create') ? 'active' : '' }}">
          <a class="nav-link" href="{{ route('sysadmin.tenants.create') }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-square-plus"></i></span>
            <span class="nav-link-title">Crear tenant</span>
          </a>
        </li>

        {{-- FACTURACIÓN --}}
        <li class="nav-item mt-3">
          <div class="nav-link text-uppercase text-muted fw-semibold small">Facturación</div>
        </li>
        <li class="nav-item {{ $isActive(['sysadmin.invoices.*']) ? 'active' : '' }}">
          <a class="nav-link" href="{{ route('sysadmin.invoices.index') }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-file-text"></i></span>
            <span class="nav-link-title">Facturas a tenants</span>
          </a>
        </li>
        {{-- Nota: Billing por tenant requiere ID, se accede desde editar tenant --}}

        {{-- VERIFICACIÓN --}}
        <li class="nav-item mt-3">
          <div class="nav-link text-uppercase text-muted fw-semibold small">Verificación</div>
        </li>
        <li class="nav-item {{ $isActive(['sysadmin.verifications.*','sysadmin.vehicle-documents.*','sysadmin.driver-documents.*']) ? 'active' : '' }}">
          <a class="nav-link" href="{{ route('sysadmin.verifications.index') }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-checkbox"></i></span>
            <span class="nav-link-title">Cola de verificación</span>
          </a>
        </li>

        {{-- LEADS --}}
        <li class="nav-item mt-3">
          <div class="nav-link text-uppercase text-muted fw-semibold small">Leads</div>
        </li>
        <li class="nav-item {{ $isActive(['sysadmin.leads.*']) ? 'active' : '' }}">
          <a class="nav-link" href="{{ route('sysadmin.leads.index') }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-address-book"></i></span>
            <span class="nav-link-title">Contactos / Leads</span>
          </a>
        </li>

        {{-- REPORTES --}}
        <li class="nav-item mt-3">
          <div class="nav-link text-uppercase text-muted fw-semibold small">Reportes</div>
        </li>
        <li class="nav-item">
          <a class="nav-link disabled" href="javascript:void(0)" aria-disabled="true" tabindex="-1">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-chart-bar"></i></span>
            <span class="nav-link-title">Comisiones (próx.)</span>
          </a>
        </li>

       {{-- UBICACIONES (SYSADMIN) --}}
<li class="nav-item mt-3">
  <div class="nav-link text-uppercase text-muted fw-semibold small">Ubicaciones</div>
</li>

<li class="nav-item">
  <a class="nav-link" href="{{ route('sysadmin.cities.index') }}">
    <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-building-community"></i></span>
    <span class="nav-link-title">Ciudades</span>
  </a>
</li>

<li class="nav-item">
  <a class="nav-link" href="{{ route('sysadmin.city-places.index') }}">
    <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-map-pin"></i></span>
    <span class="nav-link-title">Lugares sugeridos</span>
  </a>
</li>
       
      </ul>
    </div>
  </div>
</aside>

@push('styles')
<style>
  .orb-brand-circle{ border-radius:999px; object-fit:cover; box-shadow:0 0 0 2px rgba(255,255,255,.08); }
  .orb-brand-title{ font-weight:800; letter-spacing:.02em; line-height:1.05; }
  .orb-brand-sub{ opacity:.65; font-weight:600; }
  .nav-link.disabled{ opacity:.55; pointer-events:none; }
  .navbar-vertical .nav-item.active > .nav-link{ background:rgba(255,255,255,.06); border-radius:.5rem; }
</style>
@endpush
