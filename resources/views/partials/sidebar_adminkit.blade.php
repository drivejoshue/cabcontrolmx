<?php
$is = fn($r) => request()->routeIs($r) ? 'active' : '';
?>
<nav id="sidebar" class="sidebar js-sidebar">
  <div class="sidebar-content js-simplebar">
    <a class="sidebar-brand" href="{{ route('admin.dashboard') }}">
      <span class="sidebar-brand-text align-middle">{{ config('app.name') }}</span>
    </a>

    <ul class="sidebar-nav">
      <li class="sidebar-header">Panel</li>

      <li class="sidebar-item {{ $is('admin.dashboard') }}">
        <a class="sidebar-link" href="{{ route('admin.dashboard') }}">
          <i class="align-middle" data-feather="home"></i>
          <span class="align-middle">Dashboard</span>
        </a>
      </li>

      <li class="sidebar-item {{ $is('admin.dispatch') }}">
        <a class="sidebar-link" href="{{ route('admin.dispatch') }}">
          <i class="align-middle" data-feather="map"></i>
          <span class="align-middle">Dispatch / Mapa</span>
        </a>
      </li>
    </ul>
  </div>
</nav>
