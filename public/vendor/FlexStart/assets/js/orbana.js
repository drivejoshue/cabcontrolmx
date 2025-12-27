// =========================
// ORBANA - MEJORAS DE JAVASCRIPT
// =========================

document.addEventListener('DOMContentLoaded', function() {
  // =========================
  // TEMA DARK/LIGHT
  // =========================
  function initTheme() {
    const root = document.documentElement;
    const key = 'orbana_theme';
    const saved = localStorage.getItem(key);
    
    // Si no hay tema guardado, detectar preferencia del sistema
    if (!saved) {
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      root.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
      localStorage.setItem(key, prefersDark ? 'dark' : 'light');
    } else {
      root.setAttribute('data-theme', saved);
    }

    const btn = document.getElementById('themeToggle');
    if (btn) {
      // Actualizar icono según tema actual
      updateThemeIcon();
      
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        const current = root.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        
        // Cambiar tema
        root.setAttribute('data-theme', next);
        localStorage.setItem(key, next);
        
        // Actualizar icono
        updateThemeIcon();
        
        // Añadir efecto visual
        document.body.style.transition = 'background-color 0.5s ease';
        setTimeout(() => {
          document.body.style.transition = '';
        }, 500);
      });
    }
  }

  function updateThemeIcon() {
    const btn = document.getElementById('themeToggle');
    if (!btn) return;
    
    const icon = btn.querySelector('i');
    const text = btn.querySelector('span');
    const current = document.documentElement.getAttribute('data-theme');
    
    if (current === 'dark') {
      icon.className = 'bi bi-sun';
      text.textContent = 'Light';
    } else {
      icon.className = 'bi bi-moon-stars';
      text.textContent = 'Dark';
    }
  }

  // =========================
  // FAQ TOGGLE
  // =========================
  function initFAQ() {
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
      const toggle = item.querySelector('.faq-toggle');
      const content = item.querySelector('.faq-content');
      
      // Cerrar todos los FAQ al inicio
      if (content) {
        content.style.display = 'none';
      }
      
      item.addEventListener('click', function() {
        const isActive = this.classList.contains('faq-active');
        
        // Cerrar todos los FAQ
        faqItems.forEach(faq => {
          faq.classList.remove('faq-active');
          const faqContent = faq.querySelector('.faq-content');
          if (faqContent) {
            faqContent.style.display = 'none';
          }
        });
        
        // Abrir el FAQ clickeado si no estaba activo
        if (!isActive) {
          this.classList.add('faq-active');
          if (content) {
            content.style.display = 'block';
          }
        }
      });
    });
  }

  // =========================
  // SMOOTH SCROLL
  // =========================
  function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
          window.scrollTo({
            top: targetElement.offsetTop - 80,
            behavior: 'smooth'
          });
        }
      });
    });
  }

  // =========================
  // ANIMACIONES AL SCROLL
  // =========================
  function initScrollAnimations() {
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('aos-animate');
        }
      });
    }, observerOptions);
    
    // Observar elementos con data-aos
    document.querySelectorAll('[data-aos]').forEach(el => {
      observer.observe(el);
    });
  }

  // =========================
  // FORM VALIDATION
  // =========================
  function initForms() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
      form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        
        form.classList.add('was-validated');
      }, false);
    });
  }

  // =========================
  // CONTADOR ESTADÍSTICAS
  // =========================
  function initCounters() {
    const counters = document.querySelectorAll('.counter');
    const speed = 200;
    
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const counter = entry.target;
          const target = +counter.getAttribute('data-target');
          const count = +counter.innerText;
          const increment = target / speed;
          
          if (count < target) {
            counter.innerText = Math.ceil(count + increment);
            setTimeout(() => initCounters(), 1);
          } else {
            counter.innerText = target;
          }
        }
      });
    }, { threshold: 0.5 });
    
    counters.forEach(counter => observer.observe(counter));
  }

  // =========================
  // NAVBAR SCROLL EFFECT
  // =========================
  function initNavbarScroll() {
    const header = document.getElementById('header');
    let lastScroll = 0;
    
    window.addEventListener('scroll', () => {
      const currentScroll = window.pageYOffset;
      
      if (currentScroll <= 0) {
        header.classList.remove('scroll-up');
        return;
      }
      
      if (currentScroll > lastScroll && !header.classList.contains('scroll-down')) {
        // Scroll hacia abajo
        header.classList.remove('scroll-up');
        header.classList.add('scroll-down');
      } else if (currentScroll < lastScroll && header.classList.contains('scroll-down')) {
        // Scroll hacia arriba
        header.classList.remove('scroll-down');
        header.classList.add('scroll-up');
      }
      
      lastScroll = currentScroll;
    });
  }

  // =========================
  // IMAGE LAZY LOADING
  // =========================
  function initLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
      const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const img = entry.target;
            img.src = img.dataset.src;
            img.classList.add('loaded');
            observer.unobserve(img);
          }
        });
      });
      
      images.forEach(img => imageObserver.observe(img));
    }
  }

  // =========================
  // TOOLTIPS Y POPOVERS
  // =========================
  function initTooltips() {
    const tooltipTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );
    tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    const popoverTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="popover"]')
    );
    popoverTriggerList.map(function (popoverTriggerEl) {
      return new bootstrap.Popover(popoverTriggerEl);
    });
  }

  // =========================
  // INICIALIZAR TODO
  // =========================
  function initAll() {
    initTheme();
    initFAQ();
    initSmoothScroll();
    initScrollAnimations();
    initForms();
    initCounters();
    initNavbarScroll();
    initLazyLoading();
    initTooltips();
    
    console.log('Orbana JS inicializado correctamente');
  }

  // Ejecutar inicialización
  initAll();
});

// =========================
// UTILIDADES GLOBALES
// =========================
const OrbanaUtils = {
  // Formatear números
  formatNumber: function(num) {
    return new Intl.NumberFormat('es-MX').format(num);
  },
  
  // Formatear moneda
  formatCurrency: function(amount) {
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN'
    }).format(amount);
  },
  
  // Validar email
  validateEmail: function(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
  },
  
  // Copiar al portapapeles
  copyToClipboard: function(text) {
    navigator.clipboard.writeText(text).then(() => {
      console.log('Texto copiado al portapapeles');
    });
  },
  
  // Mostrar notificación
  showNotification: function(message, type = 'info') {
    // Implementar notificación toast
    console.log(`[${type.toUpperCase()}] ${message}`);
  }
};