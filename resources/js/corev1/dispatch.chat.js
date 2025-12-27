/* resources/js/pages/dispatch/chat.js */
import { getTenantId, jsonHeaders, escapeHtml } from './core.js';
import { fmtHM12_fromDb } from './rides.js';

const THREADS_URL = window.__CHAT_THREADS_URL__ || '/api/dispatch/chats/threads';
const MESSAGES_URL = window.__CHAT_MESSAGES_URL__ || '/api/dispatch/chats/{driverId}/messages';
const SEND_URL = window.__CHAT_SEND_URL__ || '/api/dispatch/chats/{driverId}/messages';
const READ_URL = window.__CHAT_READ_URL__ || '/api/dispatch/chats/{driverId}/read';

const ChatInbox = (() => {
  const state = {
    inited: false,
    open: false,
    activeDriverId: null,
    threads: [],
    messages: [],
    lastMessageId: null,
    timers: { threads: null, messages: null },
    pollThreadsMs: 6500,
    pollMessagesMs: 2500,
    search: '',
  };

  const els = {
    openers: [],
    badge: null,
    drawer: null,
    backdrop: null,
    panel: null,
    threadsList: null,
    searchInput: null,
    convoHeader: null,
    messages: null,
    form: null,
    input: null,
    sendBtn: null,
    closeBtns: [],
  };

  function withTenant(url) {
    const tid = getTenantId();
    const sep = url.includes('?') ? '&' : '?';
    return `${url}${sep}tenant_id=${encodeURIComponent(tid)}`;
  }

  function urlFor(template, driverId) {
    return template.replace('{driverId}', String(driverId));
  }

  async function apiJson(url, opts = {}) {
    const r = await fetch(url, {
      ...opts,
      headers: { ...(opts.headers || {}), ...jsonHeaders() },
    });
    if (!r.ok) {
      const t = await r.text().catch(() => '');
      throw new Error(`HTTP ${r.status} ${r.statusText} :: ${t.slice(0, 280)}`);
    }
    return r.json();
  }

  function notifyWarn(message) {
    try {
      if (typeof SmartNotifications !== 'undefined' && SmartNotifications?.show) {
        SmartNotifications.show('warning', String(message || ''));
        return;
      }
    } catch (_) { }
    console.warn('[chat]', message);
  }

  function ensureStyles() {
    if (document.getElementById('cc-chat-styles')) return;

    const css = document.createElement('style');
    css.id = 'cc-chat-styles';
    css.textContent = `
      .cc-chat-drawer{position:fixed;inset:0;z-index:9999;display:none}
      .cc-chat-drawer.is-open{display:block}
      .cc-chat-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.35)}
      .cc-chat-panel{position:absolute;top:12px;right:12px;bottom:12px;width:min(980px,calc(100% - 24px));
        background:var(--cc-card-bg,#0f1420);color:var(--cc-text,#e7ecf6);
        border:1px solid var(--cc-border,rgba(255,255,255,.08));
        border-radius:14px;box-shadow:0 12px 30px rgba(0,0,0,.35);
        display:flex;flex-direction:column;overflow:hidden}
      .cc-chat-header{display:flex;align-items:center;justify-content:space-between;
        padding:10px 12px;border-bottom:1px solid var(--cc-border,rgba(255,255,255,.08))}
      .cc-chat-title{font-weight:700;font-size:14px;letter-spacing:.2px}
      .cc-chat-close{border:0;background:transparent;color:inherit;width:34px;height:34px;border-radius:10px}
      .cc-chat-close:hover{background:rgba(255,255,255,.06)}
      .cc-chat-body{display:grid;grid-template-columns:320px 1fr;min-height:0;flex:1}
      .cc-chat-threads{border-right:1px solid var(--cc-border,rgba(255,255,255,.08));min-height:0;display:flex;flex-direction:column}
      .cc-chat-threads-head{padding:10px 10px;border-bottom:1px solid var(--cc-border,rgba(255,255,255,.08))}
      .cc-chat-search{width:100%;padding:9px 10px;border-radius:10px;border:1px solid var(--cc-border,rgba(255,255,255,.10));
        outline:none;background:var(--cc-soft-bg,rgba(255,255,255,.06));color:inherit}
      .cc-chat-threads-list{overflow:auto;min-height:0}
      .cc-chat-thread{padding:10px 10px;cursor:pointer;border-bottom:1px solid rgba(255,255,255,.06)}
      .cc-chat-thread:hover{background:rgba(255,255,255,.04)}
      .cc-chat-thread.is-active{background:rgba(0,184,216,.10)}
      .cc-chat-thread-top{display:flex;justify-content:space-between;gap:10px}
      .cc-chat-thread-name{font-weight:700;font-size:13px}
      .cc-chat-thread-time{font-size:12px;color:var(--cc-muted,#9aa4b5);white-space:nowrap}
      .cc-chat-thread-last{margin-top:3px;font-size:12px;color:var(--cc-muted,#9aa4b5);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
      .cc-chat-unread{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 6px;
        font-size:11px;border-radius:99px;background:#e11d48;color:white;margin-left:8px}
      .cc-chat-convo{min-height:0;display:flex;flex-direction:column}
      .cc-chat-convo-header{padding:10px 12px;border-bottom:1px solid var(--cc-border,rgba(255,255,255,.08));font-weight:700;font-size:13px}
      .cc-chat-messages{padding:12px;overflow:auto;min-height:0;display:flex;flex-direction:column;gap:8px}
      .cc-msg{max-width:78%;padding:10px 10px;border-radius:14px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.05)}
      .cc-msg.me{margin-left:auto;background:rgba(0,184,216,.14);border-color:rgba(0,184,216,.22)}
      .cc-msg .cc-msg-text{white-space:pre-wrap;word-break:break-word;font-size:13px}
      .cc-msg .cc-msg-meta{margin-top:6px;font-size:11px;color:var(--cc-muted,#9aa4b5);text-align:right}
      .cc-chat-send{display:flex;gap:10px;padding:10px 12px;border-top:1px solid var(--cc-border,rgba(255,255,255,.08))}
      .cc-chat-input{flex:1;padding:10px 10px;border-radius:12px;border:1px solid var(--cc-border,rgba(255,255,255,.10));
        outline:none;background:var(--cc-soft-bg,rgba(255,255,255,.06));color:inherit}
      .cc-chat-sendbtn{padding:10px 14px;border-radius:12px;border:0;background:var(--cc-primary,#00B8D8);color:#061018;font-weight:800}
      .cc-chat-sendbtn:disabled{opacity:.55}
      @media (max-width: 860px){
        .cc-chat-body{grid-template-columns:1fr}
        .cc-chat-threads{display:none}
        .cc-chat-panel{left:12px;right:12px;width:auto}
      }
    `;
    document.head.appendChild(css);
  }

  function ensureDom() {
    if (document.getElementById('ccChatDrawer')) return;

    const wrap = document.createElement('div');
    wrap.id = 'ccChatDrawer';
    wrap.className = 'cc-chat-drawer';
    wrap.innerHTML = `
      <div class="cc-chat-backdrop" data-cc-chat-close="1"></div>
      <div class="cc-chat-panel" role="dialog" aria-label="Mensajes">
        <div class="cc-chat-header">
          <div class="cc-chat-title">Mensajes</div>
          <button class="cc-chat-close" type="button" title="Cerrar" data-cc-chat-close="1">✕</button>
        </div>

        <div class="cc-chat-body">
          <div class="cc-chat-threads">
            <div class="cc-chat-threads-head">
              <input class="cc-chat-search" id="ccChatSearch" type="text" placeholder="Buscar conductor…" />
            </div>
            <div id="ccChatThreadsList" class="cc-chat-threads-list"></div>
          </div>

          <div class="cc-chat-convo">
            <div id="ccChatConvoHeader" class="cc-chat-convo-header">Selecciona un chat</div>
            <div id="ccChatMessages" class="cc-chat-messages"></div>
            <form id="ccChatSendForm" class="cc-chat-send" autocomplete="off">
              <input id="ccChatInput" class="cc-chat-input" type="text" placeholder="Escribe un mensaje…" />
              <button id="ccChatSendBtn" class="cc-chat-sendbtn" type="submit" disabled>Enviar</button>
            </form>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(wrap);
  }

  function cacheEls() {
    els.openers = [
      ...document.querySelectorAll('#btnChatInbox, [data-cc-chat-open="1"]')
    ];
    els.badge = document.getElementById('chatUnreadBadge');
    els.drawer = document.getElementById('ccChatDrawer');
    els.backdrop = els.drawer?.querySelector('.cc-chat-backdrop') || null;
    els.panel = els.drawer?.querySelector('.cc-chat-panel') || null;
    els.threadsList = document.getElementById('ccChatThreadsList');
    els.searchInput = document.getElementById('ccChatSearch');
    els.convoHeader = document.getElementById('ccChatConvoHeader');
    els.messages = document.getElementById('ccChatMessages');
    els.form = document.getElementById('ccChatSendForm');
    els.input = document.getElementById('ccChatInput');
    els.sendBtn = document.getElementById('ccChatSendBtn');
    els.closeBtns = [...document.querySelectorAll('[data-cc-chat-close="1"]')];
  }

  function setBadge(n) {
    if (!els.badge) return;
    if (!n) {
      els.badge.style.display = 'none';
      els.badge.textContent = '';
      return;
    }
    els.badge.style.display = '';
    els.badge.textContent = n > 99 ? '99+' : String(n);
  }

  function computeUnreadTotal(threads) {
    return (threads || []).reduce((sum, t) => sum + (Number(t.unread_count || 0) || 0), 0);
  }

  function fmtMsgTime(ts) {
    try {
      if (!ts) return '';
      if (String(ts).includes('T')) {
        const d = new Date(ts);
        return isNaN(d.getTime()) ? '' : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      }
      return fmtHM12_fromDb(String(ts));
    } catch (_) {
      return '';
    }
  }

  function normalizeThread(t) {
    const driverId = t.driver_id ?? t.driverId ?? t.driver?.id;
    return {
      driver_id: driverId,
      driver_name: t.driver_name ?? t.driverName ?? t.driver?.name ?? `Conductor #${driverId}`,
      last_text: t.last_text ?? t.last_message ?? t.lastMessage ?? '',
      last_at: t.last_at ?? t.updated_at ?? t.lastAt ?? null,
      unread_count: Number(t.unread_count ?? t.unread ?? 0) || 0,
      vehicle_label: t.vehicle_label ?? t.vehicleLabel ?? t.driver?.vehicle_label ?? null,
      plate: t.plate ?? t.vehicle_plate ?? t.driver?.plate ?? null,
    };
  }

  function normalizeMsg(m) {
    return {
      id: m.id ?? m.message_id ?? m.messageId ?? null,
      body: m.body ?? m.text ?? m.message ?? '',
      created_at: m.created_at ?? m.sent_at ?? m.at ?? null,
      sender: m.sender ?? m.from ?? (m.is_dispatch ? 'dispatch' : null),
    };
  }

  function renderThreads() {
    if (!els.threadsList) return;
    const q = (state.search || '').trim().toLowerCase();

    const filtered = state.threads.filter(t => {
      if (!q) return true;
      const hay = `${t.driver_name} ${t.vehicle_label || ''} ${t.plate || ''}`.toLowerCase();
      return hay.includes(q);
    });

    if (!filtered.length) {
      els.threadsList.innerHTML = `<div style="padding:12px;color:var(--cc-muted,#9aa4b5);font-size:13px">Sin chats.</div>`;
      return;
    }

    els.threadsList.innerHTML = filtered.map(t => {
      const active = String(t.driver_id) === String(state.activeDriverId);
      const unread = t.unread_count > 0 ? `<span class="cc-chat-unread">${t.unread_count > 99 ? '99+' : t.unread_count}</span>` : '';
      const time = t.last_at ? fmtMsgTime(t.last_at) : '';
      const last = (t.last_text || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      const label = (t.vehicle_label || t.plate) ? ` · ${(t.vehicle_label || t.plate)}` : '';
      return `
        <div class="cc-chat-thread ${active ? 'is-active' : ''}" data-driver-id="${t.driver_id}">
          <div class="cc-chat-thread-top">
            <div class="cc-chat-thread-name">${escapeHtml(t.driver_name)}${escapeHtml(label)}${unread}</div>
            <div class="cc-chat-thread-time">${escapeHtml(time)}</div>
          </div>
          <div class="cc-chat-thread-last">${last || '<span style="opacity:.7">—</span>'}</div>
        </div>
      `;
    }).join('');
  }

  function renderMessages({ sticky = true } = {}) {
    if (!els.messages) return;

    const stick = sticky && isNearBottom(els.messages);
    els.messages.innerHTML = state.messages.map(m => {
      const me = (m.sender === 'dispatch' || m.sender === 'admin' || m.sender === 'panel');
      const t = fmtMsgTime(m.created_at);
      return `
        <div class="cc-msg ${me ? 'me' : ''}">
          <div class="cc-msg-text">${escapeHtml(m.body)}</div>
          <div class="cc-msg-meta">${escapeHtml(t)}</div>
        </div>
      `;
    }).join('');

    if (stick) scrollToBottom(els.messages);
  }

  function isNearBottom(el) {
    const gap = el.scrollHeight - el.scrollTop - el.clientHeight;
    return gap < 80;
  }

  function scrollToBottom(el) {
    el.scrollTop = el.scrollHeight;
  }

  async function refreshThreads({ silent = true } = {}) {
    try {
      const url = withTenant(THREADS_URL);
      const data = await apiJson(url);
      const raw = Array.isArray(data) ? data : (data.threads || data.items || []);
      state.threads = raw.map(normalizeThread).filter(t => t.driver_id != null);

      renderThreads();
      setBadge(computeUnreadTotal(state.threads));
    } catch (e) {
      if (!silent) notifyWarn(`No se pudo cargar mensajes: ${e.message || e}`);
    }
  }

  async function openThread(driverId) {
    state.activeDriverId = driverId;
    state.lastMessageId = null;
    state.messages = [];
    renderThreads();
    if (els.convoHeader) els.convoHeader.textContent = `Chat · Conductor #${driverId}`;

    await refreshMessages({ force: true, silent: true });

    apiJson(withTenant(urlFor(READ_URL, driverId)), { method: 'POST' })
      .then(() => refreshThreads({ silent: true }))
      .catch(() => { });
  }

  async function refreshMessages({ force = false, silent = true } = {}) {
    const driverId = state.activeDriverId;
    if (!driverId) return;

    try {
      let url = withTenant(urlFor(MESSAGES_URL, driverId));
      if (state.lastMessageId && !force) url += `&after_id=${encodeURIComponent(state.lastMessageId)}`;

      const data = await apiJson(url);
      const raw = Array.isArray(data) ? data : (data.messages || data.items || []);

      const msgs = raw.map(normalizeMsg);
      if (force || !state.lastMessageId) {
        state.messages = msgs;
      } else if (msgs.length) {
        state.messages = [...state.messages, ...msgs];
      }

      const last = state.messages[state.messages.length - 1];
      if (last?.id != null) state.lastMessageId = last.id;

      const th = state.threads.find(t => String(t.driver_id) === String(driverId));
      if (els.convoHeader && th) {
        const label = (th.vehicle_label || th.plate) ? ` · ${(th.vehicle_label || th.plate)}` : '';
        els.convoHeader.textContent = `Chat · ${th.driver_name}${label}`;
      }

      renderMessages();
    } catch (e) {
      if (!silent) notifyWarn(`No se pudo cargar chat: ${e.message || e}`);
    }
  }

  async function sendMessage(text) {
    const driverId = state.activeDriverId;
    if (!driverId) return;

    const body = String(text || '').trim();
    if (!body) return;

    try {
      const temp = {
        id: `tmp_${Date.now()}`,
        body,
        created_at: new Date().toISOString(),
        sender: 'dispatch',
      };
      state.messages = [...state.messages, temp];
      renderMessages();

      els.input.value = '';
      els.sendBtn.disabled = true;

      const payload = { body };
      const url = withTenant(urlFor(SEND_URL, driverId));
      const resp = await apiJson(url, { method: 'POST', body: JSON.stringify(payload) });

      const created = resp?.message ? normalizeMsg(resp.message) : null;
      if (created && created.id != null) {
        state.messages = state.messages.map(m => (m.id === temp.id ? created : m));
        state.lastMessageId = created.id;
        renderMessages();
      } else {
        await refreshMessages({ force: true, silent: true });
      }

      refreshThreads({ silent: true });
    } catch (e) {
      notifyWarn(`No se pudo enviar: ${e.message || e}`);
    } finally {
      updateSendBtnState();
    }
  }

  function updateSendBtnState() {
    if (!els.sendBtn || !els.input) return;
    els.sendBtn.disabled = !state.activeDriverId || !String(els.input.value || '').trim();
  }

  function open() {
    if (!els.drawer) return;
    state.open = true;
    els.drawer.classList.add('is-open');
    refreshThreads({ silent: true });
    startTimers();
  }

  function close() {
    if (!els.drawer) return;
    state.open = false;
    els.drawer.classList.remove('is-open');
    stopTimers();
  }

  function toggle() {
    state.open ? close() : open();
  }

  function startTimers() {
    stopTimers();
    state.timers.threads = setInterval(() => refreshThreads({ silent: true }), state.pollThreadsMs);
    state.timers.messages = setInterval(() => {
      if (!state.open || !state.activeDriverId) return;
      refreshMessages({ force: false, silent: true });
    }, state.pollMessagesMs);
  }

  function stopTimers() {
    if (state.timers.threads) clearInterval(state.timers.threads);
    if (state.timers.messages) clearInterval(state.timers.messages);
    state.timers.threads = null;
    state.timers.messages = null;
  }

  function bindEvents() {
    els.openers.forEach(el => {
      el.addEventListener('click', (ev) => {
        ev.preventDefault();
        toggle();
      });
    });

    els.drawer?.addEventListener('click', (ev) => {
      const t = ev.target;
      if (t?.getAttribute?.('data-cc-chat-close') === '1') close();
    });

    document.addEventListener('keydown', (ev) => {
      if (!state.open) return;
      if (ev.key === 'Escape') close();
    });

    els.searchInput?.addEventListener('input', () => {
      state.search = els.searchInput.value || '';
      renderThreads();
    });

    els.threadsList?.addEventListener('click', (ev) => {
      const item = ev.target.closest?.('.cc-chat-thread');
      if (!item) return;
      const did = item.getAttribute('data-driver-id');
      if (!did) return;
      openThread(did);
    });

    els.input?.addEventListener('input', updateSendBtnState);
    els.form?.addEventListener('submit', (ev) => {
      ev.preventDefault();
      sendMessage(els.input.value);
    });
  }

  function init() {
    if (state.inited) return;
    state.inited = true;

    ensureStyles();
    ensureDom();
    cacheEls();
    bindEvents();

    refreshThreads({ silent: true }).catch(() => { });
  }

  return { init, open, close, toggle, refreshThreads };
})();

window.ChatInbox = ChatInbox;