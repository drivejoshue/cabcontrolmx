<?php
    /** @var \App\Models\User|null $user */
    $user      = auth()->user();
    $name      = $user->name ?? 'Admin';
    // Si algún día tienes columna avatar_url, se usa; si no, cae al avatar por defecto
    $avatarUrl = $user->avatar_url ?? asset('images/avatar.jpg');
?>

<nav class="navbar navbar-expand navbar-light navbar-bg">
  <a class="sidebar-toggle js-sidebar-toggle">
    <i class="hamburger align-self-center"></i>
  </a>

  <ul class="navbar-nav ms-auto">
    <li class="nav-item me-2">
      <button id="themeToggle"
              class="btn btn-outline-secondary btn-sm"
              type="button"
              title="Light/Dark">
        <span class="light-label"><i class="bi bi-moon"></i></span>
        <span class="dark-label d-none"><i class="bi bi-sun"></i></span>
      </button>
    </li>

    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle d-flex align-items-center"
         href="#"
         data-bs-toggle="dropdown">
        {{-- Avatar --}}
        <img src="{{ $avatarUrl }}"
             class="avatar img-fluid rounded me-2"
             alt="user"
             width="32"
             height="32">

        {{-- Nombre (truncado para que quepa) --}}
        <span class="d-none d-sm-inline-block text-truncate fw-semibold"
              style="max-width: 140px;">
          {{ $name }}
        </span>
      </a>

      <div class="dropdown-menu dropdown-menu-end">
        <span class="dropdown-item-text fw-semibold">{{ $name }}</span>
        <div class="dropdown-divider"></div>

        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button class="dropdown-item" type="submit">
            <i class="align-middle me-1" data-feather="log-out"></i>
            Cerrar sesión
          </button>
        </form>
      </div>
    </li>
  </ul>
</nav>
