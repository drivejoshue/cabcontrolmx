// resources/js/partner-monitor/app.js
import { injectStyles } from './styles';
import { createMapAndLayers } from './map';

import { preloadCarIcons } from './icons';
import { createDriversController } from './drivers';
import { createSectorsController } from './sectors';
import { createStandsController } from './stands';
import { bindControls } from './controls';
import { bindRealtime } from './realtime';
import { getJson, isDarkMode } from './net';
import { createRidesController } from './rides';

export function startPartnerMonitor() {
  injectStyles();
  preloadCarIcons();

  const base = createMapAndLayers();
  if (!base) return;

   const panel = document.querySelector('.pm-panel');
  if (panel) panel.classList.add('is-closed');

  const topbar = document.querySelector('.cc-topbar');
if (topbar) document.documentElement.style.setProperty('--pm-top-offset', `${topbar.offsetHeight}px`);

  // ctx “global” compartido entre módulos
  const ctx = {
    ...base,

    // toggles
    showSectors: true,
    showStands: true,

    // placeholders (se setean abajo)
    loadSectores: async () => {},
    loadStands: async () => {},
    fitDrivers: () => {},
    upsertDriver: () => {},
    markStaleDrivers: () => {},
    focusDriverById: () => {},
    driverPins: null,
  };


  // ✅ Tema inicial (por si cambias attrs antes de que cargue todo)
  ctx.setBaseTheme(isDarkMode());
  try { ctx.layerSectors.setStyle(ctx.sectorStyle()); } catch {}

  // ✅ Observer global de tema (tiles + sectores)
  (function bindThemeObserver(){
  const el = document.documentElement;

  const apply = (themeStr) => {
    const dark = themeStr
      ? (String(themeStr).toLowerCase() === 'dark')
      : isDarkMode();

    ctx.setBaseTheme(dark);
    try { ctx.layerSectors.setStyle(ctx.sectorStyle()); } catch {}
  };

  window.addEventListener('theme:changed', (e) => apply(e?.detail?.theme));

  const obs = new MutationObserver(() => apply());
  obs.observe(el, { attributes: true, attributeFilter: ['data-theme','data-bs-theme','class'] });

  apply();
})();


 
 // Drivers
const drivers = createDriversController(ctx);
ctx.driverPins = drivers.driverPins;
ctx.upsertDriver = drivers.upsertDriver;
ctx.markStaleDrivers = drivers.markStaleDrivers;
ctx.focusDriverById = drivers.focusDriverById;
ctx.fitDrivers = drivers.fitDrivers;


ctx.followOn = drivers.startFollow;     // (driverId)
ctx.followOff = drivers.stopFollow;

ctx.isFollowing = drivers.isFollowing; // ✅
ctx.getFollowDriverId = drivers.getFollowDriverId; // ✅

ctx.applyDriverFilter = drivers.applyDriverFilter;


  // Sectors
  const sectors = createSectorsController(ctx);
  ctx.loadSectores = sectors.loadSectores;
  sectors.bindThemeObserver();

  // Stands
  const stands = createStandsController(ctx);
  ctx.loadStands = stands.loadStands;

  // Controls
  const controls = bindControls(ctx);
  controls.addFloatingControls();

const rides = createRidesController(ctx);
ctx.focusRideById = rides.focusRideById;
ctx.clearRideFocus = rides.clearRideFocus;

// monta la barra flotante 1 vez
ctx.rideBar = rides.mountRideCard();

ctx.panel = controls.mountMonitorPanel();
ctx.hud = controls.mountHud();
ctx.sel = controls.mountSelectionCard();
ctx.ridePeek = controls.mountRidePeekCard();


controls.bindDockButtons();



   // Bootstrap + polling (snapshot canónico)
  async function loadBootstrap() {
    const j = await getJson('/partner/monitor/bootstrap');
    if (!j?.ok) {
      console.warn('[PartnerMonitor] bootstrap fail', j);
      return;
    }

    // 1) server time (para "hace Xm" consistente)
    ctx.panel?.setServerTime?.(j.server_time || null);

    // 2) DRIVERS (pins + stale)
    const driversArr = j.drivers ?? [];
    const visible = new Set();

    for (const d of driversArr) {
      const id = Number(d.driver_id ?? d.id);
      if (Number.isFinite(id) && id) visible.add(id);
      ctx.upsertDriver(d);
    }
    ctx.markStaleDrivers(visible);

    // 3) STANDS (tu mapa de stands + panel)
    // si tu createStandsController ya maneja pintar stands con data directa, úsalo aquí.
    // Si no, puedes seguir usando ctx.loadStands() como antes.
    const standsArr = j.stands ?? [];
    ctx.panel?.setStands?.(standsArr);

    // OPCIONAL: si tu módulo stands NO tiene método setStands, deja tu loadStands()
    // y solo usa panel.setStands para el listado.
    if (ctx.showStands) {
      // si tu controller soporta setStands:
      ctx.setStands?.(standsArr); // <- si lo implementas en createStandsController
      // fallback al método viejo:
      if (!ctx.setStands) await ctx.loadStands();
    }

    // 4) RIDES (panel)
    const ridesArr = j.active_rides ?? j.items ?? [];
    ctx.panel?.setRides?.(ridesArr);
    ctx.ridesCtl?.syncFromPolling?.(ridesArr);
    // 5) SECTORS (sin cambios)
    if (ctx.showSectors) await ctx.loadSectores();

    // 6) fit inicial
    if ((window.ccTenant?.map?.fitDriversOnLoad ?? true) && driversArr.length > 0) {
      ctx.fitDrivers();
    }
  }

  async function pollSnapshot() {
  const j = await getJson('/partner/monitor/active-rides');
  if (!j?.ok) return;

  ctx.panel?.setServerTime?.(j.server_time || null);

  const driversArr = j.drivers ?? [];
  const visible = new Set();
  for (const d of driversArr) {
    const id = Number(d.driver_id ?? d.id);
    if (Number.isFinite(id) && id) visible.add(id);
    ctx.upsertDriver(d);
  }
  ctx.markStaleDrivers(visible);

  const ridesArr = j.active_rides ?? j.items ?? [];

  ctx.panel?.setDrivers?.(driversArr);
  ctx.panel?.setStands?.(j.stands ?? []);
  ctx.panel?.setRides?.(ridesArr);

  // ✅ ESTO es lo que hará que la barra y los puntos avancen en tiempo real
  ctx.ridesCtl?.syncFromPolling?.(ridesArr);

  if (ctx.showStands && ctx.setStands) {
    ctx.setStands(j.stands ?? []);
  }
}



  bindRealtime(ctx);

  loadBootstrap();
  setInterval(pollSnapshot, 8_000);

  // DEBUG handles
  window.__pm = {
    ...ctx,
    // expón explícitos los más útiles
    map: ctx.map,
    layerStands: ctx.layerStands,
    layerSectors: ctx.layerSectors,
    layerDrivers: ctx.layerDrivers,
    driverPins: ctx.driverPins,
    loadStands: ctx.loadStands,
    loadSectores: ctx.loadSectores,
  };
}
