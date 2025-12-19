<?php
use Illuminate\Support\Facades\Route;

$brandName = 'Orbana Core';
$brandSub  = 'SysAdmin';
$logoCircle = asset('images/logonf.png');   // círculo sin texto
$logoInline = asset('images/logo.png');     // opcional inline (si lo quieres usar luego)

/**
 * Activo para item simple:
 *  - acepta string o array de patterns
 */
$isActive = function ($patterns): string {
    $patterns = is_array($patterns) ? $patterns : [$patterns];
    foreach ($patterns as $p) {
        if (request()->routeIs($p)) return 'active';
    }
    return '';
};
?>

<nav id="sidebar" class="sidebar js-sidebar">
  <div class="sidebar-content js-simplebar">

    {{-- Brand --}}
    <a class="sidebar-brand d-flex align-items-center gap-2 orb-brand"
       href="{{ route('sysadmin.dashboard') }}">

      <img
        src="{{ $logoCircle }}"
        alt="Orbana"
        class="orb-brand-circle"
        width="28"
        height="28"
      >

      <span class="d-flex flex-column lh-sm">
        <span class="orb-brand-title">{{ $brandName }}</span>
        <small class="orb-brand-sub">{{ $brandSub }}</small>
      </span>
    </a>

    <ul class="sidebar-nav">

      {{-- ===== Core ===== --}}
      <li class="sidebar-header">Core</li>

      <li class="sidebar-item {{ $isActive('sysadmin.dashboard') }}">
        <a class="sidebar-link" href="{{ route('sysadmin.dashboard') }}"
           aria-current="{{ request()->routeIs('sysadmin.dashboard') ? 'page' : 'false' }}">
          <i class="align-middle" data-feather="home"></i>
          <span class="align-middle">Dashboard</span>
        </a>
      </li>

      {{-- ===== Tenants ===== --}}
      <li class="sidebar-header">Tenants</li>

      <li class="sidebar-item {{ $isActive('sysadmin.tenants.*') }}">
        <a class="sidebar-link" href="{{ route('sysadmin.tenants.index') }}">
          <i class="align-middle" data-feather="layers"></i>
          <span class="align-middle">Lista de tenants</span>
        </a>
      </li>

      {{-- ===== Facturación ===== --}}
      <li class="sidebar-header">Facturación</li>

      <li class="sidebar-item {{ $isActive('sysadmin.invoices.*') }}">
        <a class="sidebar-link" href="{{ route('sysadmin.invoices.index') }}">
          <i class="align-middle" data-feather="file-text"></i>
          <span class="align-middle">Facturas a tenants</span>
        </a>
      </li>

      {{-- ===== Verificación ===== --}}
      <li class="sidebar-header">Verificación</li>

      <li class="sidebar-item {{ $isActive('sysadmin.verifications.*') }}">
        <a class="sidebar-link" href="{{ route('sysadmin.verifications.index') }}">
          <i class="align-middle" data-feather="check-square"></i>
          <span class="align-middle">Cola de verificación</span>
        </a>
      </li>

      {{-- ===== Reportes ===== --}}
      <li class="sidebar-header">Reportes</li>

      <li class="sidebar-item">
        <a class="sidebar-link disabled" href="javascript:void(0)" aria-disabled="true" tabindex="-1">
          <i class="align-middle" data-feather="bar-chart-2"></i>
          <span class="align-middle">Comisiones (próx.)</span>
        </a>
      </li>

      {{-- ===== Navegación ===== --}}
      <li class="sidebar-header">Navegación</li>

      <li class="sidebar-item">
        <a class="sidebar-link" href="{{ route('admin.dashboard') }}">
          <i class="align-middle" data-feather="corner-up-left"></i>
          <span class="align-middle">Volver a panel tenant</span>
        </a>
      </li>

    </ul>
  </div>
</nav>

@push('styles')
<style>
  /* Branding Orbana Core */
  .orb-brand { padding-top: 1rem; padding-bottom: 1rem; }
  .orb-brand-circle{
    border-radius: 999px;
    object-fit: cover;
    box-shadow: 0 0 0 2px rgba(255,255,255,.06);
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

  /* Dark mode hook (si tu layout ya usa html.theme-dark) */
  html.theme-dark .orb-brand-title { color: #E5E7EB; }
  html.theme-dark .orb-brand-sub   { color: rgba(229,231,235,.72); }

  /* Item "disabled" visible pero no clickable */
  .sidebar-link.disabled { opacity: .55; pointer-events: none; }
</style>
@endpush
