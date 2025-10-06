<?php $user = auth()->user(); ?>
<nav class="navbar navbar-expand navbar-light navbar-bg">
  <a class="sidebar-toggle js-sidebar-toggle"><i class="hamburger align-self-center"></i></a>

  <ul class="navbar-nav ms-auto">
    <li class="nav-item me-2">
      <button id="themeToggle" class="btn btn-outline-secondary btn-sm" type="button" title="Light/Dark">
        <span class="light-label"><i class="bi bi-moon"></i></span>
        <span class="dark-label d-none"><i class="bi bi-sun"></i></span>
      </button>
    </li>

    <li class="nav-item dropdown">
      <a class="nav-icon pe-md-0 dropdown-toggle" href="#" data-bs-toggle="dropdown">
        <img src="{{ asset('img/avatars/avatar.jpg') }}" class="avatar img-fluid rounded" alt="user">
      </a>
      <div class="dropdown-menu dropdown-menu-end">
        <span class="dropdown-item-text">{{ $user->name ?? 'Admin' }}</span>
        <div class="dropdown-divider"></div>
        <form method="POST" action="{{ route('logout') }}">{{ csrf_field() }}
          <button class="dropdown-item" type="submit">
            <i class="align-middle me-1" data-feather="log-out"></i> Cerrar sesión
          </button>
        </form>
      </div>
    </li>
  </ul>
</nav>
