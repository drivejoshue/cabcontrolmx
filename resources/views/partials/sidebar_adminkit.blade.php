<?php
// resources/views/partials/sidebar_adminkit.blade.php

use Illuminate\Support\Facades\Route;

$u = auth()->user();
$tenantId = $u?->tenant_id;

// Regla real: tenant admin = tenant_id + is_admin true (ajusta a tu campo real)
$isTenantAdmin = (bool)($tenantId && ($u->is_admin ?? false));

/**
 * Activo para item simple:
 *  - acepta un string o array de patterns
 */
$isActive = function ($patterns): string {
    $patterns = is_array($patterns) ? $patterns : [$patterns];
    foreach ($patterns as $p) {
        if (request()->routeIs($p)) return 'active';
    }
    return '';
};

// Branding (puedes mover a config/branding.php si quieres)
$brandName = 'Orbana Core';
$logoCircle = asset('images/logonf.png');   // círculo sin texto
$logoInline = asset('images/logo.png');     // logo inline (con texto o sin, pero horizontal)
?>
<nav id="sidebar" class="sidebar js-sidebar">
  <div class="sidebar-content js-simplebar">

    {{-- BRAND --}}
    <a class="sidebar-brand d-flex align-items-center gap-2 orb-brand"
       href="{{ Route::has('admin.dashboard') ? route('admin.dashboard') : url('/admin') }}">

      {{-- Círculo (sin texto) --}}
      <img src="{{ $logoCircle }}" alt="Orbana" class="orb-brand-circle" width="28" height="28">

      {{-- Inline + nombre --}}
      <span class="d-flex flex-column lh-sm">
        <span class="orb-brand-title">{{ $brandName }}</span>
        <small class="orb-brand-sub">Dispatch &amp; Operación</small>
      </span>
    </a>

    <ul class="sidebar-nav">

      {{-- =======================
           CORE
      ======================= --}}
      <li class="sidebar-header">Core</li>

      <li class="sidebar-item {{ $isActive('admin.dashboard') }}">
        <a class="sidebar-link"
           href="{{ Route::has('admin.dashboard') ? route('admin.dashboard') : url('/admin') }}"
           aria-current="{{ request()->routeIs('admin.dashboard') ? 'page' : 'false' }}">
          <i class="align-middle" data-feather="home"></i>
          <span class="align-middle">Dashboard</span>
        </a>
      </li>

      <li class="sidebar-item {{ $isActive('admin.dispatch') }}">
        <a class="sidebar-link"
           href="{{ Route::has('admin.dispatch') ? route('admin.dispatch') : url('/admin/dispatch') }}"
           aria-current="{{ request()->routeIs('admin.dispatch') ? 'page' : 'false' }}">
          <i class="align-middle" data-feather="map"></i>
          <span class="align-middle">Mapa (Dispatch)</span>
        </a>
      </li>

      {{-- =======================
           OPERACIÓN (solo tenant admin)
      ======================= --}}
      @if($isTenantAdmin)
        <li class="sidebar-header">Operación</li>

        <li class="sidebar-item {{ $isActive('sectores.*') }}">
          <a class="sidebar-link"
             href="{{ Route::has('sectores.index') ? route('sectores.index') : url('/admin/sectores') }}">
            <i class="align-middle" data-feather="grid"></i>
            <span class="align-middle">Sectores</span>
          </a>
        </li>

        <li class="sidebar-item {{ $isActive('taxistands.*') }}">
          <a class="sidebar-link"
             href="{{ Route::has('taxistands.index') ? route('taxistands.index') : url('/admin/taxistands') }}">
            <i class="align-middle" data-feather="flag"></i>
            <span class="align-middle">Paraderos</span>
          </a>
        </li>

        <li class="sidebar-item {{ $isActive('drivers.*') }}">
          <a class="sidebar-link"
             href="{{ Route::has('drivers.index') ? route('drivers.index') : url('/admin/drivers') }}">
            <i class="align-middle" data-feather="user-check"></i>
            <span class="align-middle">Conductores</span>
          </a>
        </li>

        <li class="sidebar-item {{ $isActive('vehicles.*') }}">
          <a class="sidebar-link"
             href="{{ Route::has('vehicles.index') ? route('vehicles.index') : url('/admin/vehicles') }}">
            <i class="align-middle" data-feather="truck"></i>
            <span class="align-middle">Vehículos</span>
          </a>
        </li>

  

        {{-- =======================
             CONFIGURACIÓN
        ======================= --}}
        <li class="sidebar-header">Configuración</li>

        <li class="sidebar-item {{ $isActive('admin.tenant.edit') }}">
          <a class="sidebar-link"
             href="{{ Route::has('admin.tenant.edit') ? route('admin.tenant.edit') : url('/mi-central') }}">
            <i class="align-middle" data-feather="briefcase"></i>
            <span class="align-middle">Mi central</span>
          </a>
        </li>

        <li class="sidebar-item {{ $isActive(['admin.billing.*','admin.billing.plan']) }}">
          <a class="sidebar-link"
             href="{{ Route::has('admin.billing.plan') ? route('admin.billing.plan') : url('/admin/billing/plan') }}">
            <i class="align-middle" data-feather="credit-card"></i>
            <span class="align-middle">Plan y facturación</span>
          </a>
        </li>

        <li class="sidebar-item {{ $isActive('admin.dispatch_settings.*') }}">
          <a class="sidebar-link"
             href="{{ Route::has('admin.dispatch_settings.edit') ? route('admin.dispatch_settings.edit') : url('/admin/dispatch-settings') }}">
            <i class="align-middle" data-feather="sliders"></i>
            <span class="align-middle">Dispatch Settings</span>
          </a>
        </li>

        <li class="sidebar-item {{ $isActive('admin.fare_policies.*') }}">
          <a class="sidebar-link"
             href="{{ Route::has('admin.fare_policies.index') ? route('admin.fare_policies.index') : url('/admin/fare-policies') }}">
            <i class="align-middle" data-feather="dollar-sign"></i>
            <span class="align-middle">Tarifas</span>
          </a>
        </li>

        <li class="sidebar-header">Cobros</li>

        <li class="sidebar-item {{ $isActive('admin.taxi_fees') }}">
          <a class="sidebar-link" href="{{ route('admin.taxi_fees') }}">
            <i class="align-middle" data-feather="sliders"></i>
            <span class="align-middle">Cuotas por taxi</span>
          </a>
        </li>

        <li class="sidebar-item {{ $isActive('admin.taxi_charges') }}">
          <a class="sidebar-link" href="{{ route('admin.taxi_charges') }}">
            <i class="align-middle" data-feather="file-text"></i>
            <span class="align-middle">Cobros</span>
          </a>
        </li>


        {{-- =======================
             REPORTES
        ======================= --}}
        <li class="sidebar-header">Reportes</li>

      <li class="sidebar-item {{ $isActive('admin.reports.clients*') }}">
      <a class="sidebar-link"
         href="{{ Route::has('admin.reports.clients') ? route('admin.reports.clients') : url('/admin/reportes/clientes') }}">
        <i class="align-middle" data-feather="users"></i>
        <span class="align-middle">Clientes</span>
      </a>
    </li>


        <li class="sidebar-item {{ $isActive('admin.reports.rides*') }}">
          <a class="sidebar-link"
             href="{{ Route::has('admin.reports.rides') ? route('admin.reports.rides') : url('/admin/reportes/viajes') }}">
            <i class="align-middle" data-feather="activity"></i>
            <span class="align-middle">Viajes</span>
          </a>
        </li>

        <li class="sidebar-item {{ $isActive('admin.reports.drivers') }}">
          <a class="sidebar-link"
             href="{{ Route::has('admin.reports.drivers') ? route('admin.reports.drivers') : url('/admin/reportes/conductores') }}">
            <i class="align-middle" data-feather="award"></i>
            <span class="align-middle">Conductores</span>
          </a>
        </li>

        <li class="sidebar-item {{ $isActive('ratings.*') }}">
          <a class="sidebar-link"
             href="{{ Route::has('ratings.index') ? route('ratings.index') : url('/ratings/reports') }}">
            <i class="align-middle" data-feather="star"></i>
            <span class="align-middle">Calificaciones</span>
          </a>
        </li>

        <li class="sidebar-item {{ $isActive('admin.reports.revenue*') }}">
          <a class="sidebar-link"
             href="{{ Route::has('admin.reports.revenue') ? route('admin.reports.revenue') : url('/admin/reportes/ingresos') }}">
            <i class="align-middle" data-feather="bar-chart-2"></i>
            <span class="align-middle">Ingresos</span>
          </a>
        </li>
      @endif

    </ul>
  </div>
</nav>

@push('styles')
<style>
  /* Branding Orbana Core (AdminKit sidebar) */
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

  /* Si estás en dark, mantenemos acento Orbana (sin inventar gradientes) */
  html.theme-dark .orb-brand-title { color: #E5E7EB; }
  html.theme-dark .orb-brand-sub   { color: rgba(229,231,235,.72); }
</style>
@endpush
