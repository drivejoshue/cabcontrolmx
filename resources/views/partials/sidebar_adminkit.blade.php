<?php
// resources/views/partials/sidebar_adminkit.blade.php

use Illuminate\Support\Facades\Route;

// Helper: marcar activo por nombre exacto o comodín
$is = function ($pattern) {
    return request()->routeIs($pattern) ? 'active' : '';
};

// Helper: tenant id seguro
$tenantId = auth()->check() ? (auth()->user()->tenant_id ?? 1) : 1;
?>
<nav id="sidebar" class="sidebar js-sidebar">
  <div class="sidebar-content js-simplebar">

    <a class="sidebar-brand d-flex align-items-center gap-2" href="{{ route('admin.dashboard') }}">
      <img
        src="{{ asset('images/logonf.png') }}"
        alt="logo"
        class="brand-img"
        height="28"
      >
      <span class="sidebar-brand-text">{{ config('app.name') }}</span>
    </a>



    <ul class="sidebar-nav">
      {{-- ===== Panel (todos) ===== --}}
      <li class="sidebar-header">Panel</li>

      <li class="sidebar-item {{ $is('admin.dashboard') }}">
        <a class="sidebar-link" href="{{ route('admin.dashboard') }}" aria-current="{{ request()->routeIs('admin.dashboard') ? 'page' : 'false' }}">
          <i class="align-middle" data-feather="home"></i>
          <span class="align-middle">Dashboard</span>
        </a>
      </li>

      <li class="sidebar-item {{ $is('admin.dispatch') }}">
        <a class="sidebar-link" href="{{ route('admin.dispatch') }}" aria-current="{{ request()->routeIs('admin.dispatch') ? 'page' : 'false' }}">
          <i class="align-middle" data-feather="map"></i>
          <span class="align-middle">Dispatch / Mapa</span>
        </a>
      </li>

      {{-- ===== Operación (solo admin) ===== --}}
      @can('admin')
      <li class="sidebar-header">Operación</li>

      <li class="sidebar-item {{ $is('sectores.*') }}">
        <a class="sidebar-link" href="{{ Route::has('sectores.index') ? route('sectores.index') : url('/admin/sectores') }}">
          <i class="align-middle" data-feather="grid"></i>
          <span class="align-middle">Sectores</span>
        </a>
      </li>

      <li class="sidebar-item {{ $is('taxistands.*') }}">
        <a class="sidebar-link" href="{{ Route::has('taxistands.index') ? route('taxistands.index') : url('/admin/taxistands') }}">
          <i class="align-middle" data-feather="flag"></i>
          <span class="align-middle">Paraderos</span>
        </a>
      </li>

      <li class="sidebar-item {{ $is('drivers.*') }}">
        <a class="sidebar-link" href="{{ Route::has('drivers.index') ? route('drivers.index') : url('/admin/drivers') }}">
          <i class="align-middle" data-feather="user-check"></i>
          <span class="align-middle">Conductores</span>
        </a>
      </li>

      <li class="sidebar-item {{ $is('vehicles.*') }}">
        <a class="sidebar-link" href="{{ Route::has('vehicles.index') ? route('vehicles.index') : url('/admin/vehicles') }}">
          <i class="align-middle" data-feather="truck"></i>
          <span class="align-middle">Vehículos</span>
        </a>
      </li>

      {{-- ===== Ajustes del Tenant (solo admin) ===== --}}
      <li class="sidebar-header">Ajustes del Tenant</li>

      <li class="sidebar-item {{ $is('admin.tenants.*') }}">
        <a class="sidebar-link" href="{{ route('admin.tenants.edit', $tenantId) }}">
          <i class="align-middle" data-feather="settings"></i>
          <span class="align-middle">Tenant Settings</span>
        </a>
      </li>

      <li class="sidebar-item {{ $is('admin.dispatch_settings.*') }}">
        <a class="sidebar-link" href="{{ route('admin.dispatch_settings.edit') }}">
          <i class="align-middle" data-feather="toggle-right"></i>
          <span class="align-middle">Dispatch Settings</span>
        </a>
      </li>

      <li class="sidebar-item {{ $is('admin.fare_policies.*') }}">
        <a class="sidebar-link" href="{{ route('admin.fare_policies.index') }}">
          <i class="align-middle" data-feather="dollar-sign"></i>
          <span class="align-middle">Tarifas</span>
        </a>
      </li>

      {{-- ===== Reportes (solo admin) ===== --}}
      <li class="sidebar-header">Reportes</li>

      <li class="sidebar-item {{ $is('admin.reports.clients*') }}">
        <a class="sidebar-link"
           href="{{ Route::has('admin.reports.clients') ? route('admin.reports.clients') : url('/admin/reportes/clientes') }}">
          <i class="align-middle" data-feather="users"></i>
          <span class="align-middle">Histórico de clientes</span>
        </a>
      </li>

      <li class="sidebar-item {{ $is('admin.reports.rides*') }}">
        <a class="sidebar-link"
           href="{{ Route::has('admin.reports.rides') ? route('admin.reports.rides') : url('/admin/reportes/viajes') }}">
          <i class="align-middle" data-feather="activity"></i>
          <span class="align-middle">Viajes</span>
        </a>
      </li>

      <li class="sidebar-item {{ $is('admin.reports.drivers*') }}">
        <a class="sidebar-link"
           href="{{ Route::has('admin.reports.drivers') ? route('admin.reports.drivers') : url('/admin/reportes/conductores') }}">
          <i class="align-middle" data-feather="award"></i>
          <span class="align-middle">Conductores</span>
        </a>
      </li>
      <li class="sidebar-item {{ $is('ratings.*') }}">
    <a class="sidebar-link" href="{{ Route::has('ratings.index') ? route('ratings.index') : url('/ratings/reports') }}">
        <i class="align-middle" data-feather="star"></i>
        <span class="align-middle">Calificaciones</span>
    </a>
</li>

      <li class="sidebar-item {{ $is('admin.reports.revenue*') }}">
        <a class="sidebar-link"
           href="{{ Route::has('admin.reports.revenue') ? route('admin.reports.revenue') : url('/admin/reportes/ingresos') }}">
          <i class="align-middle" data-feather="bar-chart-2"></i>
          <span class="align-middle">Ingresos</span>
        </a>
      </li>
      @endcan
    </ul>
  </div>
</nav>


