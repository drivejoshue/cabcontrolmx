@php
  /** @var \App\Models\User|null $user */
  $user      = auth()->user();
  $name      = $user->name ?? 'Admin';
  $avatarUrl = $user->avatar_url ?? asset('images/avatar.jpg');
@endphp

<nav class="navbar navbar-expand navbar-light navbar-bg">
  <a class="sidebar-toggle js-sidebar-toggle">
    <i class="hamburger align-self-center"></i>
  </a>

  <ul class="navbar-nav ms-auto align-items-center">

    {{-- Toggle Theme --}}
    <li class="nav-item me-2">
      <button id="themeToggle"
              class="btn btn-outline-secondary btn-sm"
              type="button"
              title="Light/Dark">
        <span class="light-label"><i class="bi bi-moon"></i></span>
        <span class="dark-label d-none"><i class="bi bi-sun"></i></span>
      </button>
    </li>

    {{-- Mensajes / Inbox (Chat) --}}
    <li class="nav-item dropdown me-2">
      <a id="ccMessagesDropdown"
         class="nav-link dropdown-toggle position-relative"
         href="#"
         role="button"
         data-bs-toggle="dropdown"
         aria-expanded="false"
         title="Mensajes">
        <i class="bi bi-chat-dots fs-5"></i>

        <span id="ccMessagesBadge"
              class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-secondary"
              style="font-size:.70rem; min-width: 22px;">
          0
        </span>
      </a>

      <div class="dropdown-menu dropdown-menu-end p-0"
           style="min-width: 360px;">
        <div class="dropdown-header d-flex align-items-center justify-content-between">
          <strong>Mensajes</strong>
          {{-- Botón abrir panel offcanvas (opcional) --}}
          <button type="button"
                  class="btn btn-sm btn-outline-secondary"
                  id="btnOpenChatPanel">
            Abrir panel
          </button>
        </div>

        <div class="dropdown-divider m-0"></div>

        {{-- Lista de hilos (ChatInbox inyecta aquí) --}}
        <div id="ccMessagesList" class="list-group list-group-flush"
             style="max-height: 360px; overflow:auto;">
          <div class="text-muted small p-2">Cargando…</div>
        </div>
      </div>
    </li>

    {{-- Usuario / Logout --}}
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle d-flex align-items-center"
         href="#"
         data-bs-toggle="dropdown"
         aria-expanded="false">
        <img src="{{ $avatarUrl }}"
             class="avatar img-fluid rounded me-2"
             alt="user"
             width="32"
             height="32">
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Botón "Abrir panel" dentro del dropdown
  const btn = document.getElementById('btnOpenChatPanel');
  btn?.addEventListener('click', (e) => {
    e.preventDefault();
    try {
      const panel = document.getElementById('chatPanel');
      if (!panel || !window.bootstrap?.Offcanvas) return;
      const inst = window.bootstrap.Offcanvas.getOrCreateInstance(panel);
      inst.show();
    } catch {}
  });
});
</script>
@endpush
