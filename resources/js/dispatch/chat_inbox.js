// resources/js/pages/chat_inbox.js

(function () {
  // ==========================
  // Helpers
  // ==========================
  function fmtTime(ts) {
    if (!ts) return '';
    const d = new Date(String(ts).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return ts;
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
    function fmtDateLabel(ts) {
    if (!ts) return '';

    const d = new Date(String(ts).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return '';

    const today = new Date();
    const todayKey = today.toISOString().slice(0, 10);

    const yest = new Date();
    yest.setDate(today.getDate() - 1);
    const yestKey = yest.toISOString().slice(0, 10);

    const key = d.toISOString().slice(0, 10);

    if (key === todayKey) return 'Hoy';
    if (key === yestKey) return 'Ayer';

    // dd MMM yyyy
    return d.toLocaleDateString(undefined, {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }
  function appendDateHeaderElement(containerEl, dateKey, label) {
    if (!containerEl) return;

    const row = document.createElement('div');
    row.className = 'd-flex justify-content-center my-2';

    row.innerHTML = `
      <div class="px-3 py-1 rounded-pill bg-light text-muted small border">
        ${escapeHtml(label || dateKey)}
      </div>
    `;

    containerEl.appendChild(row);
  }

  function dateKeyFromTs(ts) {
    if (!ts) return null;
    const d = new Date(String(ts).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return null;
    return d.toISOString().slice(0, 10); // 'YYYY-MM-DD'
  }


  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  const ChatInbox = (function () {
    // Topbar
    let dropdownEl, badgeEl, listEl;

    // Panel lateral
    let messagesEl, quickRepliesEl, inputEl, sendBtnEl, subtitleEl;
    let offcanvasInstance;
    let panelEl = null;

    // Estado
    let tenantId = null;
    let activeDriverId = null;
    let driverChannel = null;
    let tenantChannel = null;
    let isInitialized = false;

    // Estado de UI para badge “pegado”
    let stickyUnread = 0;       // lo que mostramos en el badge
    let firstThreadsLoad = true;
    let dropdownOpen = false;
    let chatPanelOpen = false;

    // Audio de notificación
    let waveAudio = null;

    // Polling
    const POLL_MS = 5000;
    let pollTimer = null;

    // ==========================
    // Init
    // ==========================
    function init() {
      tenantId = window.getTenantId ? window.getTenantId() : null;

      // Topbar
      dropdownEl = document.getElementById('ccMessagesDropdown');
      badgeEl    = document.getElementById('ccMessagesBadge');
      listEl     = document.getElementById('ccMessagesList');

      // Panel lateral
      panelEl       = document.getElementById('chatPanel');
      messagesEl    = document.getElementById('chatMessages');
      quickRepliesEl= document.getElementById('chatQuickReplies');
      inputEl       = document.getElementById('chatInput');
      sendBtnEl     = document.getElementById('chatSendBtn');
      subtitleEl    = document.getElementById('chatPanelSubtitle');

      if (!dropdownEl || !badgeEl || !listEl) {
        console.warn('[ChatInbox] elementos de topbar no encontrados, no inicializa.');
        return;
      }

      // Offcanvas de chat
      if (panelEl && window.bootstrap && window.bootstrap.Offcanvas) {
        offcanvasInstance = new window.bootstrap.Offcanvas(panelEl);

        panelEl.addEventListener('show.bs.offcanvas', () => {
          chatPanelOpen = true;
          // al abrir el panel sincronizamos el badge con el servidor
          // (el siguiente refreshThreads tomará el valor real)
        });

        panelEl.addEventListener('hide.bs.offcanvas', () => {
          chatPanelOpen = false;
        });
      }

      // Dropdown de mensajes (para saber cuándo el usuario lo abre/cierra)
      const dropdownWrapper = dropdownEl.closest('.dropdown');
      if (dropdownWrapper) {
        dropdownWrapper.addEventListener('show.bs.dropdown', () => {
          dropdownOpen = true;
        });
        dropdownWrapper.addEventListener('hide.bs.dropdown', () => {
          dropdownOpen = false;
        });
      }

      // Al hacer clic en el ícono → refrescamos hilos
      dropdownEl.addEventListener('click', () => {
        refreshThreads().catch(console.error);
      });

      // Enviar mensaje
      if (sendBtnEl) {
        sendBtnEl.addEventListener('click', (e) => {
          e.preventDefault();
          sendCurrentMessage().catch(console.error);
        });
      }

      if (inputEl) {
        inputEl.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendCurrentMessage().catch(console.error);
          }
        });
      }

      // Audio de llegada de mensaje
      try {
        waveAudio = new Audio('/sounds/wave_sound.mp3'); // ajusta ruta/extensión si es distinto
        waveAudio.preload = 'auto';
      } catch (e) {
        console.warn('[ChatInbox] no se pudo inicializar audio de notificación', e);
      }

      isInitialized = true;

      // Carga inicial
      refreshThreads().catch(console.error);

      // Realtime si Echo existe
    setupTenantRealtimeIfAvailable().catch(console.error);


      // Fallback: polling periódico
      startPolling();
    }

  // ==========================
// Realtime (si hay Echo)
// ==========================
async function waitForEcho(maxMs = 8000, stepMs = 250) {
  const t0 = Date.now();
  while (Date.now() - t0 < maxMs) {
    if (window.Echo) return true;
    await new Promise(r => setTimeout(r, stepMs));
  }
  return !!window.Echo;
}

function safeStopListening(ch, eventName) {
  if (!ch) return;
  try { ch.stopListening(eventName); } catch (_) {}
}

function safeLeave(channelBaseName) {
  // Echo.leave espera el nombre base SIN prefijo private-/presence-
  // y se encarga de limpiar suscripciones.
  if (!window.Echo || !channelBaseName) return;
  try { window.Echo.leave(channelBaseName); } catch (_) {}
}

async function setupTenantRealtimeIfAvailable() {
  tenantId = window.getTenantId ? window.getTenantId() : tenantId;
  if (!tenantId) return;

  const ok = await waitForEcho(8000, 250);
  if (!ok) return;

  const channelName = `tenant.${tenantId}.dispatch`; // SIN "private-" aquí
  console.log('📡 [ChatInbox] Suscribiéndose a canal inbox:', channelName);

  try {
    if (tenantChannel) window.Echo.leave(`private-${channelName}`); // opcional
  } catch {}

  tenantChannel = window.Echo.private(channelName);

tenantChannel.listen('.driver.message.new', (e) => {
  const driverIdFromEvent = e.driver_id || e.sender_driver_id || null;
  if (!driverIdFromEvent) {
    refreshThreads().catch(console.error);
    return;
  }

  const isHelp = String(e.kind || '').toLowerCase() === 'help';
  const msgId = e.id || null;

  if (isHelp) {
    playHelpAlert3x(msgId);

    // auto-focus discreto (opcional)
    focusDriverFromHelp(driverIdFromEvent);

    // auto-abrir chat (si no está abierto)
    if (!chatPanelOpen && offcanvasInstance) {
      openChatWithDriver(driverIdFromEvent).catch(console.error);
    }
  } else {
    // Mensaje normal: si quieres auto-abrir, hazlo con cuidado:
    // solo cuando no hay panel abierto ni dropdown abierto
    if (!chatPanelOpen && !dropdownOpen && offcanvasInstance) {
      // opcional: auto-abrir solo si no hay chat activo
      if (!activeDriverId) {
        openChatWithDriver(driverIdFromEvent).catch(console.error);
      }
    }
  }

  // Si el panel está abierto y coincide con driver activo, agrega bubble en vivo
  if (activeDriverId && Number(driverIdFromEvent) === Number(activeDriverId) && chatPanelOpen) {
    appendRealtimeMessage(e);
  }

  refreshThreads().catch(console.error);
});


}


function subscribeDriverChannelForChat(driverId) {
  tenantId = window.getTenantId ? window.getTenantId() : tenantId;

  if (!tenantId || !window.Echo) {
    console.warn('[ChatInbox] Echo no disponible para canal driver, seguimos con polling.');
    return;
  }

  // Si ya existía un canal driver, lo limpiamos
  if (driverChannel) {
    safeStopListening(driverChannel, '.driver.message.new');
    safeLeave(`tenant.${tenantId}.driver.${activeDriverId || driverId}`);
    driverChannel = null;
  }

  // Canal driver específico
  const baseName = `tenant.${tenantId}.driver.${driverId}`;
  console.log('📡 [ChatInbox] Suscribiéndose a canal driver:', baseName);

  try {
    driverChannel = window.Echo.private(baseName);

    driverChannel.listen('.driver.message.new', (e) => {
      console.log('💬 [ChatInbox] mensaje realtime en canal driver:', e);
      appendRealtimeMessage(e);
      refreshThreads().catch(console.error);
    });

  } catch (err) {
    console.warn('[ChatInbox] Falló suscripción Echo driver channel, seguimos con polling.', err);
  }
}

    // ==========================
    // Polling con AJAX
    // ==========================
    function startPolling() {
      if (pollTimer) clearInterval(pollTimer);

      pollTimer = setInterval(() => {
        refreshThreads().catch(console.error);

        if (activeDriverId) {
          loadMessagesForDriver(activeDriverId).catch(console.error);
        }
      }, POLL_MS);
    }

    // ==========================
    // Notificación sonora
    // ==========================
    function playWaveIfPossible() {
      if (!waveAudio) return;
      if (chatPanelOpen) return; // si el panel está abierto, no molestamos

      try {
        waveAudio.currentTime = 0;
        waveAudio.play().catch(() => {});
      } catch (e) {
        console.warn('[ChatInbox] error al reproducir sonido', e);
      }
    }

    // ==========================
    // Hilos (lista + badge)
    // ==========================
async function refreshThreads() {
  if (!isInitialized) return;
  if (!window.__CHAT_THREADS_URL__) return;

  const urlObj = new URL(window.__CHAT_THREADS_URL__, window.location.origin);
  if (tenantId) urlObj.searchParams.set('tenant_id', String(tenantId));

  const resp = await fetch(urlObj.toString(), { headers: { Accept: 'application/json' } });
  if (!resp.ok) {
    console.error('[ChatInbox] error cargando threads', resp.status);
    return;
  }

  const data = await resp.json();
  const threads = data.threads || [];

  let serverUnread = 0;
  listEl.innerHTML = '';

  if (!threads.length) {
    listEl.innerHTML = '<div class="text-muted small p-2">Sin mensajes.</div>';
  } else {
    threads.forEach((t) => {
      serverUnread += t.unread_count || 0;

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className =
        'list-group-item list-group-item-action d-flex justify-content-between align-items-start';
      btn.dataset.driverId = String(t.driver_id);

      const lastText = t.last_text || 'Sin mensajes aún';

      // ✅ viene del backend (threads()) como 'econ'
      const econ  = t.econ || t.economic_number || t.vehicle_number || null;
      const plate = t.plate || null;
      const vehicleLabel = t.vehicle_label || '';

      const metaParts = [];
      if (econ)  metaParts.push(`Eco ${econ}`);
      if (plate) metaParts.push(plate);

      // Prioridad: "Eco 23 · ABC-123" (si no, vehicle_label)
      const metaLine = metaParts.join(' · ') || vehicleLabel;

      const driverName = t.driver_name || ('Conductor #' + t.driver_id);

      btn.innerHTML = `
        <div class="me-2" style="min-width:0">
          <div class="d-flex align-items-center gap-2">
            <div class="fw-semibold text-truncate">${escapeHtml(driverName)}</div>
            ${econ ? `<span class="badge bg-secondary rounded-pill">Eco ${escapeHtml(econ)}</span>` : ''}
          </div>

          ${metaLine ? `<div class="small text-muted">${escapeHtml(metaLine)}</div>` : ''}
          <div class="small text-muted text-truncate">${escapeHtml(lastText)}</div>
        </div>

        <div class="text-end">
          <div class="small text-muted">${t.last_at ? fmtTime(t.last_at) : ''}</div>
          ${
            t.unread_count
              ? `<span class="badge bg-danger rounded-pill">${t.unread_count}</span>`
              : ''
          }
        </div>
      `;

      btn.addEventListener('click', () => {
        openChatWithDriver(t.driver_id, t).catch(console.error);
      });

      listEl.appendChild(btn);
    });
  }

  // Sticky unread
  if (firstThreadsLoad) {
    stickyUnread = serverUnread;
    firstThreadsLoad = false;
  } else if (dropdownOpen || chatPanelOpen) {
    stickyUnread = serverUnread;
  } else {
    if (serverUnread > stickyUnread) {
      stickyUnread = serverUnread;
      playWaveIfPossible();
    }
  }

  badgeEl.textContent = String(stickyUnread);
  badgeEl.classList.toggle('bg-danger', stickyUnread > 0);
  badgeEl.classList.toggle('bg-secondary', stickyUnread === 0);
}


    // ==========================
    // Abrir chat
    // ==========================



    // ======= HELP ALERT (dedupe + 3 beeps) =======
const helpAlertedIds = new Set();

function focusDriverFromHelp(driverId) {
  const id = Number(driverId || 0);
  if (!id) return;

  const doFocus = () => {
    if (typeof window.focusDriverByIdSafe === 'function') {
      window.focusDriverByIdSafe(id);
      return true;
    }
    if (typeof window.focusDriverById === 'function') {
      window.focusDriverById(id);
      return true;
    }
    return false;
  };

  // 1) Intento inmediato
  if (doFocus()) return;

  // 2) Si dispatch.js aún no expuso funciones, reintenta breve
  let tries = 0;
  const t = setInterval(() => {
    tries++;
    if (doFocus() || tries >= 10) clearInterval(t); // ~1s
  }, 100);

  // 3) Si el marker no existe todavía, fuerza refresh driversLive y enfoca después
  // Necesitas exponer window.refreshDriversLive en dispatch.js (ver abajo).
  if (typeof window.refreshDriversLive === 'function') {
    window.refreshDriversLive(true);
    setTimeout(() => { doFocus(); }, 600);
    setTimeout(() => { doFocus(); }, 1200);
  }
}




async function playHelpAlert3x(messageId) {
  if (messageId != null && helpAlertedIds.has(messageId)) return;
  if (messageId != null) helpAlertedIds.add(messageId);

  if (!waveAudio) return;

  const playOnce = () => {
    try {
      const a = waveAudio.cloneNode(true);
      a.currentTime = 0;
      a.play().catch(() => {});
    } catch (_) {}
  };

  playOnce();
  setTimeout(playOnce, 900);
  setTimeout(playOnce, 1800);
}





   async function openChatWithDriver(driverId, threadData = null) {
  activeDriverId = driverId;

  const econ =
    threadData?.economico ?? threadData?.econ ?? threadData?.driver_number ?? null;

  if (subtitleEl) {
    const name = threadData?.driver_name || ('Conductor #' + driverId);
    subtitleEl.textContent = econ ? `Eco ${econ} · ${name}` : name;
  }

  await loadMessagesForDriver(driverId);
  subscribeDriverChannelForChat(driverId);

  if (offcanvasInstance) offcanvasInstance.show();
}


    async function loadMessagesForDriver(driverId) {
      if (!messagesEl || !window.__CHAT_MESSAGES_URL__) return;

      const rawUrl = window.__CHAT_MESSAGES_URL__;
      const urlStr = rawUrl.replace('DRIVER_ID', String(driverId));
      const urlObj = new URL(urlStr, window.location.origin);
      if (tenantId) urlObj.searchParams.set('tenant_id', String(tenantId));

      const resp = await fetch(urlObj.toString(), {
        headers: { Accept: 'application/json' },
      });

      if (!resp.ok) {
        console.error('[ChatInbox] error cargando mensajes', resp.status);
        messagesEl.innerHTML =
          '<div class="text-muted small">Error al cargar mensajes.</div>';
        return;
      }

      const data = await resp.json();
      const messages = data.messages || [];

      renderMessages(messages);

      // Después de leer el hilo, refrescamos hilos (servidor actualiza unread_count)
      refreshThreads().catch(console.error);
    }

  function renderMessages(messages) {
  if (!messagesEl) return;

  messagesEl.innerHTML = '';

  if (!messages.length) {
    messagesEl.innerHTML = '<div class="text-muted small">Sin mensajes en este chat.</div>';
    messagesEl.dataset.lastDateKey = '';
    return;
  }

  const sorted = [...messages].sort((a, b) => {
    const da = new Date(String(a.created_at || '').replace(' ', 'T')).getTime();
    const db = new Date(String(b.created_at || '').replace(' ', 'T')).getTime();
    return da - db;
  });

  let lastDateKey = null;

  sorted.forEach((m) => {
    const isDispatch = m.sender_type === 'dispatch';
    const isHelp = String(m.kind || '').toLowerCase() === 'help';
    const driverId = m.driver_id;

    const dateKey = dateKeyFromTs(m.created_at);
    const dateLabel = fmtDateLabel(m.created_at);

    if (dateKey && dateKey !== lastDateKey) {
      appendDateHeaderElement(messagesEl, dateKey, dateLabel);
      lastDateKey = dateKey;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'mb-1';

    // HELP: clickable + estilo
    if (isHelp && driverId) {
      wrapper.style.cursor = 'pointer';
      wrapper.title = 'Clic para ubicar al conductor';
      wrapper.addEventListener('click', (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        focusDriverFromHelp(driverId);
      });
    }

   

  const bubbleClass = isDispatch
  ? 'bg-primary text-white'
  : (isHelp ? 'bg-light border border-warning-subtle' : 'bg-light');

wrapper.innerHTML = `
  <div class="d-flex ${isDispatch ? 'justify-content-end' : 'justify-content-start'}">
    <div class="msg-bubble ${bubbleClass}">
      <div class="msg-meta mb-1">
        ${isDispatch ? 'Tú' : 'Conductor'}
        <span class="ms-2">${fmtTime(m.created_at)}</span>
        ${isHelp ? `<span class="ms-2 badge bg-warning text-dark">Soporte</span>` : ''}
      </div>
      <div class="msg-text">${escapeHtml(m.text || '')}</div>
      ${isHelp && driverId ? `<div class="msg-meta mt-1">Clic para ubicar</div>` : ''}
    </div>
  </div>
`;

    messagesEl.appendChild(wrapper);
  });

  messagesEl.dataset.lastDateKey = lastDateKey || '';
  messagesEl.scrollTop = messagesEl.scrollHeight;
}


    // ==========================
    // Append realtime (cuando sí hay Echo)
    // ==========================
   function appendRealtimeMessage(e) {
  if (!messagesEl) return;

  const isDispatch = e.sender_type === 'dispatch';
  const isHelp = String(e.kind || '').toLowerCase() === 'help';
  const driverId = e.driver_id;
  const msgId = e.id; // si viene en payload

  const dateKey = dateKeyFromTs(e.created_at);
  const dateLabel = fmtDateLabel(e.created_at);
  const lastKey = messagesEl.dataset.lastDateKey || '';

  if (dateKey && dateKey !== lastKey) {
    appendDateHeaderElement(messagesEl, dateKey, dateLabel);
    messagesEl.dataset.lastDateKey = dateKey;
  }

  const wrapper = document.createElement('div');
  wrapper.className = 'mb-1';

  if (isHelp && driverId) {
    wrapper.style.cursor = 'pointer';
    wrapper.title = 'Clic para ubicar al conductor';
    wrapper.addEventListener('click', (ev) => {
      ev.preventDefault();
      ev.stopPropagation();
      focusDriverFromHelp(driverId);
    });

    // 🔊 ALERTA HELP: 3 veces
    playHelpAlert3x(msgId);
  }

  const bubbleClass = isDispatch
    ? 'bg-primary text-white'
    : (isHelp ? 'bg-danger text-white' : 'bg-light');

  wrapper.innerHTML = `
    <div class="d-flex ${isDispatch ? 'justify-content-end' : 'justify-content-start'}">
      <div class="p-2 rounded-3 ${bubbleClass}" style="max-width: 80%;">
        <div class="small mb-1">
          ${isDispatch ? 'Tú' : (isHelp ? 'AYUDA' : 'Conductor')}
          <span class="text-muted ms-2 small">${fmtTime(e.created_at)}</span>
        </div>
        <div class="small">${escapeHtml(e.text || '')}</div>
        ${isHelp && driverId ? `<div class="small mt-1 opacity-75">Ver conductor #${Number(driverId)}</div>` : ''}
      </div>
    </div>
  `;

  messagesEl.appendChild(wrapper);
  messagesEl.scrollTop = messagesEl.scrollHeight;
}



    // ==========================
    // Enviar mensaje
    // ==========================
    async function sendCurrentMessage() {
      if (!activeDriverId || !inputEl || !window.__CHAT_SEND_URL__) return;

      const text = inputEl.value.trim();
      if (!text) return;

      const csrf = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

      const urlStr = window.__CHAT_SEND_URL__.replace(
        'DRIVER_ID',
        String(activeDriverId)
      );

      const resp = await fetch(urlStr, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        },
        body: JSON.stringify({
          text,
          tenant_id: tenantId,
        }),
      });

      if (!resp.ok) {
        const err = await resp.text();
        console.error('[ChatInbox] error al enviar mensaje', resp.status, err);
        return;
      }

      inputEl.value = '';

      await loadMessagesForDriver(activeDriverId);
    }

    // ==========================
    // API pública
    // ==========================
    return {
      init,
      refreshThreads,
      openChatWithDriver,
    };
  })();

  window.ChatInbox = ChatInbox;
})();
