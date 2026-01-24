@php
  $user = auth()->user();
  $email = $user->email ?? '—';

  /** @var \App\Models\Partner|null $partner */
  $partner = request()->attributes->get('partner') ?? null;

  $title = $partner?->name ? $partner->name : 'Partner';
@endphp
@push('styles')
<style>
  .cc-topbar .btn.position-relative .badge{
    font-size: .68rem;
    padding: .25rem .45rem;
    min-width: 1.4rem;
  }
</style>
@endpush

<header class="navbar navbar-expand-md d-print-none cc-topbar">
  <div class="container-fluid px-3">
    <div class="px-3 d-flex align-items-center w-100">

      {{-- Mobile toggler: apunta al menú partner --}}
      <button class="navbar-toggler" type="button"
              data-bs-toggle="collapse"
              data-bs-target="#sidebar-menu-partner"
              aria-controls="sidebar-menu-partner"
              aria-expanded="false"
              aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="navbar-nav me-auto">
        <div class="nav-item d-flex align-items-center gap-2">
          <div class="d-flex flex-column lh-sm">
            <div class="fw-semibold">{{ $title }}</div>
            <small class="text-muted">Portal de Partners</small>
          </div>
        </div>
      </div>

      <div class="navbar-nav flex-row order-md-last align-items-center">

        {{-- Theme toggle --}}
        <div class="nav-item me-2">
          <button id="ccThemeToggle"
                  class="btn btn-outline-secondary"
                  type="button"
                  title="Light/Dark"
                  aria-label="Toggle theme"
                  style="padding:.35rem .55rem;">
            <i id="ccThemeIcon" class="ti ti-moon"></i>
          </button>
        </div>

        @php
  $badges = $topbarBadges ?? ['inbox_unread'=>0, 'support_unread'=>0];
  $inboxUnread = (int)($badges['inbox_unread'] ?? 0);
  $supportUnread = (int)($badges['support_unread'] ?? 0);
@endphp

{{-- Inbox --}}
<div class="nav-item me-2">
  <a class="btn btn-outline-secondary position-relative"
     href="{{ route('partner.inbox.index') }}"
     title="Inbox">
    <i class="ti ti-bell"></i>

    @if($inboxUnread > 0)
      <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-red">
        {{ $inboxUnread > 99 ? '99+' : $inboxUnread }}
        <span class="visually-hidden">no leídas</span>
      </span>
    @endif
  </a>
</div>

{{-- Soporte --}}
<div class="nav-item me-2">
  <a class="btn btn-outline-secondary position-relative"
     href="{{ route('partner.support.index') }}"
     title="Soporte">
    <i class="ti ti-lifebuoy"></i>

    @if($supportUnread > 0)
      <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-yellow">
        {{ $supportUnread > 99 ? '99+' : $supportUnread }}
        <span class="visually-hidden">tickets con respuesta</span>
      </span>
    @endif
  </a>
</div>


        {{-- User dropdown (placeholder) --}}
        <div class="nav-item dropdown me-2">
          <a href="#" class="nav-link d-flex lh-1 text-reset p-0"
             data-bs-toggle="dropdown" aria-label="Open user menu">
            <span class="avatar avatar-sm" style="background-image: url({{ asset('images/avatar.jpg') }})"></span>
            <div class="d-none d-xl-block ps-2">
              <div class="fw-semibold">{{ $user->name ?? 'Usuario' }}</div>
              <div class="mt-1 small text-muted">{{ $email }}</div>
            </div>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <span class="dropdown-item-text text-muted small">Opciones (próximamente)</span>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item disabled" href="javascript:void(0)">Mi perfil</a>
            <a class="dropdown-item disabled" href="javascript:void(0)">Documentos</a>
          </div>
        </div>

        {{-- Logout --}}
        <div class="nav-item">
          <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button class="btn btn-outline-danger" type="submit" title="Cerrar sesión">
              <i class="ti ti-logout me-1"></i>
              <span class="d-none d-md-inline">Salir</span>
            </button>
          </form>
        </div>

      </div>
    </div>
  </div>
</header>

@push('styles')
<style>
  .cc-topbar{
    background: var(--tblr-bg-surface);
    border-bottom: 1px solid var(--tblr-border-color);
  }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const html = document.documentElement;
  const btn  = document.getElementById('ccThemeToggle');
  const icon = document.getElementById('ccThemeIcon');
  const STORAGE_KEY = 'cc_theme';

  function applyTheme(theme) {
  const t = (theme === 'light') ? 'light' : 'dark';

  html.setAttribute('data-bs-theme', t);
  html.setAttribute('data-theme', t); // opcional, ayuda a compatibilidad

  if (icon) icon.className = (t === 'light') ? 'ti ti-sun' : 'ti ti-moon';
  try { localStorage.setItem(STORAGE_KEY, t); } catch {}

  // ✅ Igual que Dispatch: avisar a todo el frontend
  window.dispatchEvent(new CustomEvent('theme:changed', { detail: { theme: t } }));
}


  function getInitialTheme() {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved === 'light' || saved === 'dark') return saved;
    } catch {}
    const current = html.getAttribute('data-bs-theme');
    return (current === 'light') ? 'light' : 'dark';
  }

  applyTheme(getInitialTheme());

  btn?.addEventListener('click', (e) => {
    e.preventDefault();
    const current = html.getAttribute('data-bs-theme') === 'light' ? 'light' : 'dark';
    applyTheme(current === 'light' ? 'dark' : 'light');
  });
});
</script>
@endpush
