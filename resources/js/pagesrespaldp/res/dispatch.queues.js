// resources/js/pages/dispatch.queues.js

export function renderQueues(ctx, queues){
  const acc        = document.getElementById('panel-queue');
  const compact    = document.getElementById('panel-queue-compact');
  const badge      = document.getElementById('badgeColas');
  const badgeFull  = document.getElementById('badgeColasFull');

  const list = Array.isArray(queues) ? queues : [];

  if (badge)     badge.textContent     = list.length;
  if (badgeFull) badgeFull.textContent = list.length;

  // === Overlay grande: acordeón completo ===
  if (acc) {
    acc.innerHTML = '';

    if (!list.length){
      acc.innerHTML = `<div class="text-muted small p-2">Sin información de colas.</div>`;
    } else {
      (list || []).forEach((s, idx) => {
        const drivers = Array.isArray(s.drivers) ? s.drivers : [];
        const toEco = d => (d.eco || d.callsign || d.number || d.id || '?');

        const item   = document.createElement('div');
        item.className = 'accordion-item';

        const standId = s.id || s.stand_id || idx;
        const headId  = `qhead-${standId}`;
        const colId   = `qcol-${standId}`;

        item.innerHTML = `
          <h2 class="accordion-header" id="${headId}">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#${colId}"
                    aria-expanded="false" aria-controls="${colId}">
              <div class="d-flex w-100 justify-content-between align-items-center">
                <div>
                  <div class="fw-semibold">${s.nombre || s.name || ('Base '+(s.id||''))}</div>
                  <div class="small text-muted">${
                    [s.latitud||s.lat, s.longitud||s.lng].filter(Boolean).join(', ')
                  }</div>
                </div>
                <span class="badge bg-secondary">${drivers.length}</span>
              </div>
            </button>
          </h2>
          <div id="${colId}" class="accordion-collapse collapse" data-bs-parent="#panel-queue">
            <div class="accordion-body">
              ${
                drivers.length
                  ? `<div class="queue-eco-grid">${
                      drivers.map(d => `<span class="eco">${toEco(d)}</span>`).join('')
                    }</div>`
                  : `<div class="text-muted">Sin unidades en cola.</div>`
              }
            </div>
          </div>
        `;
        acc.appendChild(item);
      });
    }
  }

  // === Vista compacta: chips ===
  if (compact){
    if (!list.length){
      compact.innerHTML = `<span class="text-muted small">Sin información de colas.</span>`;
    } else {
      const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
      }[m]));

      compact.innerHTML = list.map((s, idx) => {
        const standId = s.id || s.stand_id || idx;
        const name    = s.nombre || s.name || ('Base '+(s.id||''));
        const count   = Array.isArray(s.drivers) ? s.drivers.length : 0;

        return `
          <button type="button"
                  class="queues-compact-chip"
                  data-stand-id="${standId}">
            <strong>${esc(name)}</strong>
            <span class="badge bg-light text-secondary border ms-1">${count}</span>
          </button>
        `;
      }).join('');

      compact.querySelectorAll('.queues-compact-chip').forEach(btn => {
        btn.addEventListener('click', () => {
          const standId = btn.getAttribute('data-stand-id');
          if (window.openQueuesOverlay) window.openQueuesOverlay();
          if (!standId) return;

          const col = document.getElementById(`qcol-${standId}`);
          if (col && window.bootstrap?.Collapse) {
            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(col, { toggle: false });
            bsCollapse.show();
            col.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        });
      });
    }
  }
}

// compat
window.renderQueues = (queues) => renderQueues(null, queues);
