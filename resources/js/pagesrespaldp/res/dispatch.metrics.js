// resources/js/pages/dispatch.metrics.js

function _normState(s) {
  const st = String(s || '').toLowerCase().trim();
  if (st === 'idle') return 'free';
  if (st === 'on_ride') return 'on_board';
  if (st === 'onboard') return 'on_board';
  return st;
}

export class FleetMetrics {
  constructor(ctx = null, options = {}) {
    this.ctx = ctx;
    this.options = {
      containerSelector: '.map-container',
      pollMs: 5000,
      ...options
    };

    this.metrics = { total: 0, free: 0, busy: 0, offline: 0, inQueue: 0, onTrip: 0 };
    this.metricsEl = null;
    this._timer = null;

    this.init();
  }

  init() {
    this.createMetricsPanel();
    this.updateLive();
  }

  destroy() {
    try { clearInterval(this._timer); } catch {}
    this._timer = null;
  }

  createMetricsPanel() {
    const html = `
      <div id="fleetMetrics" class="cc-metrics-panel">
        <div class="cc-metrics-header">
          <h6>Métricas flota</h6>
          <span class="cc-metrics-time" id="metricsTime"></span>
        </div>
        <div class="cc-metrics-grid">
          ${this._metric('total','Total')}
          ${this._metric('free','Libres')}
          ${this._metric('busy','Ocupados')}
          ${this._metric('onTrip','En viaje')}
          ${this._metric('inQueue','En cola')}
          ${this._metric('offline','Offline')}
        </div>
      </div>
    `;

    const container = document.querySelector(this.options.containerSelector);
    if (!container) return;

    // evitar duplicados si hot-reload o re-init
    const existing = document.getElementById('fleetMetrics');
    if (existing) {
      this.metricsEl = existing;
      return;
    }

    container.insertAdjacentHTML('afterbegin', html);
    this.metricsEl = document.getElementById('fleetMetrics');
  }

  _metric(key, label) {
    return `
      <div class="cc-metric-item" data-metric="${key}">
        <div class="cc-metric-value">0</div>
        <div class="cc-metric-label">${label}</div>
      </div>
    `;
  }

  calculateMetrics(driversRaw, queuesRaw) {
    const drivers = Array.isArray(driversRaw) ? driversRaw : [];
    const queues  = Array.isArray(queuesRaw)  ? queuesRaw  : [];

    const visualState = this.ctx?.drivers?.visualState || window.visualState;

    const metrics = { total: drivers.length, free: 0, busy: 0, offline: 0, inQueue: 0, onTrip: 0 };

    for (const driver of drivers) {
      const raw = visualState ? visualState(driver) : (driver?.status || 'busy');
      const state = _normState(raw);

      switch (state) {
        case 'free':
          metrics.free++;
          break;
        case 'offline':
          metrics.offline++;
          break;
        case 'on_board':
        case 'ontrip':
          metrics.onTrip++;
          break;
        case 'busy':
        default:
          metrics.busy++;
          break;
      }
    }

    metrics.inQueue = queues.reduce((sum, q) => sum + (q?.drivers?.length || 0), 0);

    this.metrics = metrics;
    this.updateDisplay();
  }

  updateDisplay() {
    if (!this.metricsEl) return;

    for (const key of Object.keys(this.metrics)) {
      const valueEl = this.metricsEl.querySelector(`[data-metric="${key}"] .cc-metric-value`);
      if (!valueEl) continue;

      valueEl.textContent = String(this.metrics[key]);

      // micro-animación segura
      valueEl.style.transform = 'scale(1.08)';
      setTimeout(() => { try { valueEl.style.transform = 'scale(1)'; } catch {} }, 180);
    }

    const timeEl = document.getElementById('metricsTime');
    if (timeEl) timeEl.textContent = new Date().toLocaleTimeString();
  }

  updateLive() {
    this.destroy();

    this._timer = setInterval(() => {
      if (window._lastDrivers && window._lastQueues) {
        this.calculateMetrics(window._lastDrivers, window._lastQueues);
      }
    }, Math.max(1000, Number(this.options.pollMs) || 5000));
  }
}

export class SmartNotifications {
  static show(type, message, options = {}) {
    const config = {
      success: { prefix: 'OK', duration: 3000 },
      warning: { prefix: 'WARN', duration: 5000 },
      error:   { prefix: 'ERR', duration: 7000 },
      info:    { prefix: 'INFO', duration: 4000 }
    }[type] || { prefix: 'INFO', duration: 4000 };

    this.showToast(`[${config.prefix}] ${message}`, config.duration, { type, ...options });
  }

  static rideAssigned(ride, driver) {
    const dName = driver?.name || driver?.display_name || 'conductor';
    this.show('success', `Viaje #${ride?.id} asignado a ${dName}`);
  }

  static driverArrived() {
    this.show('info', 'Conductor llegó al punto de recogida');
  }

  // Puente a tu toast real (si existe)
  static showToast(msg, duration = 4000, options = {}) {
    if (typeof window.showToast === 'function') {
      return window.showToast(msg, options.type || 'info', { duration, ...options });
    }
    console.log('[toast]', msg, { duration, ...options });
  }
}
