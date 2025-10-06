
import feather from 'feather-icons';

// Reemplaza iconos cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
  try { feather.replace(); } catch (e) {}
});

// (Opcional) utilidad para refrescar íconos cuando agregues HTML dinámico
window.featherRefresh = () => { try { feather.replace(); } catch (e) {} };



// ===== Rutas CSS (Vite las resuelve a /build/...) =====
const LIGHT_CSS = new URL('../css/adminkit/light.css', import.meta.url).href;
const DARK_CSS  = new URL('../css/adminkit/dark.css',  import.meta.url).href;

const LS_KEY = 'theme';

// Crea el <link id="themeStylesheet"> si no existe
function ensureThemeLink() {
  let linkEl = document.getElementById('themeStylesheet');
  if (!linkEl) {
    linkEl = document.createElement('link');
    linkEl.id = 'themeStylesheet';
    linkEl.rel = 'stylesheet';
    document.head.appendChild(linkEl);
  }
  return linkEl;
}

function setBtnState(mode) {
  const lightLbl = document.querySelector('.light-label');
  const darkLbl  = document.querySelector('.dark-label');
  if (!lightLbl || !darkLbl) return;
  if (mode === 'dark') {
    lightLbl.classList.add('d-none');  // oculto luna
    darkLbl.classList.remove('d-none');// muestro sol
  } else {
    darkLbl.classList.add('d-none');   // oculto sol
    lightLbl.classList.remove('d-none');// muestro luna
  }
}

function applyTheme(mode, persist = true) {
  const linkEl = ensureThemeLink();
  linkEl.href = (mode === 'dark') ? DARK_CSS : LIGHT_CSS;

  document.documentElement.setAttribute('data-theme', mode);
  setBtnState(mode);

  if (persist) {
    try { localStorage.setItem(LS_KEY, mode); } catch {}
  }
  // Notificar a otros módulos (e.g., mapa Leaflet)
  window.dispatchEvent(new CustomEvent('theme:changed', { detail: { theme: mode }}));
}

function currentTheme() {
  return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
}

function initTheme() {
  let initial = 'light';
  try {
    const saved = localStorage.getItem(LS_KEY);
    if (saved === 'dark' || saved === 'light') {
      initial = saved;
    } else if (window.matchMedia?.('(prefers-color-scheme: dark)').matches) {
      initial = 'dark';
    }
  } catch {}
  applyTheme(initial, false);

  // Solo si el usuario no guardó preferencia, sigue al SO
  try {
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    mq.addEventListener?.('change', (e) => {
      const hasSaved = !!localStorage.getItem(LS_KEY);
      if (!hasSaved) applyTheme(e.matches ? 'dark' : 'light', false);
    });
  } catch {}
}

function initThemeToggleButton() {
  document.getElementById('themeToggle')?.addEventListener('click', () => {
    const next = currentTheme() === 'dark' ? 'light' : 'dark';
    applyTheme(next, true);
  });
}

function initSidebarToggle() {
  document.querySelectorAll('.js-sidebar-toggle').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelector('.js-sidebar')?.classList.toggle('collapsed');
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  // Feather
  try { featherLib?.replace?.(); } catch {}
  // Helper para refrescar iconos después de inyectar HTML
  window.featherRefresh = () => { try { featherLib?.replace?.(); } catch {} };

  // Tema
  initTheme();
  initThemeToggleButton();

  // Sidebar
  initSidebarToggle();
});
