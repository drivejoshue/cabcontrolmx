export function injectStyles() {
  const css = `
  /* Car sprite (doble img) */
  .pm-car-icon{ background:transparent; border:0; }
  .pm-car-box{ width:23px; height:36px; position:relative; --pm-rot:0deg; --pm-scale:1; }
  .pm-car-img{ position:absolute; left:0; top:0; width:23px; height:36px;
    transform-origin:50% 50%;
    transform: rotate(var(--pm-rot)) scale(var(--pm-scale));
    transition: opacity 160ms linear, transform 120ms linear;
    image-rendering: -webkit-optimize-contrast;
  }
  .pm-active{ opacity:1; }
  .pm-inactive{ opacity:0; }

  /* Control flotante */
  .pm-float-ctl{ display:flex; flex-direction:column; gap:8px; padding:8px; }
  .pm-float-ctl button{
    width:44px; height:44px;
    border-radius:14px;
    border:1px solid rgba(255,255,255,.18);
    background: rgba(17,24,39,.72);
    color:#fff;
    font-size:16px;
    cursor:pointer;
    touch-action: manipulation;
    user-select:none;
  }
  .pm-float-ctl button.pm-on{ outline:2px solid rgba(56,189,248,.55); }

  /* Stand icon (Leaflet L.icon => la clase cae en el <img> del marker) */
  .leaflet-marker-icon.pm-stand-icon{
    background: transparent !important;
    border: 0 !important;
    opacity: 1 !important;
    filter: drop-shadow(0 2px 6px rgba(0,0,0,.55));
    max-width: none !important;
  } /* ✅ cierre correcto */

  /* ===== DARK MAP (tipo CodePen): invierte SOLO tiles/controles ===== */
  #pmMap.pm-dark-tiles .leaflet-tile-pane{
    filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);
  }
  #pmMap.pm-dark-tiles .leaflet-control-zoom-in,
  #pmMap.pm-dark-tiles .leaflet-control-zoom-out,
  #pmMap.pm-dark-tiles .leaflet-control-attribution{
    filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);
  }

  /* Fullscreen map overlay */
  body.pm-map-fullscreen #pmMap{ position:fixed !important; inset:0 !important; z-index:2000 !important; }
  body.pm-map-fullscreen #pmSidebar{ display:none !important; }
  body.pm-map-fullscreen #pmMapCol{ width:100% !important; max-width:100% !important; flex: 0 0 100% !important; }

.pm-dock.is-closed{ display:none !important; }


    /* ===== Layout base (mapa full + overlays) ===== */
:root { --pm-top-offset: 0px; }

.pm-wrap{
  position: relative;
  height: calc(100vh - var(--pm-top-offset, 0px));
  min-height: 560px;
  border-radius: 18px;
  overflow: hidden;
}

.pm-map{
  position: absolute;
  inset: 0;
}

/* Dock flotante izquierda */
.pm-dock{
  position: absolute;
  left: 14px;
  top: 14px;
  bottom: 14px;
  width: 360px;
  max-width: calc(100vw - 28px);
  overflow: hidden;

  display:flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;

  border-radius: 18px;
  border: 1px solid rgba(255,255,255,.10);
  background: rgba(17,24,39,.58);
  backdrop-filter: blur(10px);
  box-shadow: 0 10px 30px rgba(0,0,0,.30);

  pointer-events: auto;
}

:root[data-bs-theme="light"] .pm-dock{
  background: rgba(255,255,255,.70);
  border: 1px solid rgba(15,23,42,.10);
}

/* Colapsar sidebar */
body.pm-sidebar-collapsed .pm-dock{ display:none !important; }

/* Fullscreen map */
body.pm-map-fullscreen .pm-wrap{
  position: fixed !important;
  inset: 0 !important;
  height: 100vh !important;
  z-index: 2000 !important;
  border-radius: 0 !important;
}
body.pm-map-fullscreen .pm-dock{ display:none !important; }

/* Head del dock */
.pm-dock-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap: 10px;
}
.pm-title{
  font-weight: 700;
  letter-spacing: .2px;
}
.pm-actions{
  display:flex;
  gap: 8px;
}

/* Secciones scrollables internas */
.pm-section{
  border-radius: 14px;
  border: 1px solid rgba(255,255,255,.10);
  background: rgba(0,0,0,.10);
  overflow: hidden;
}
:root[data-bs-theme="light"] .pm-section{
  border: 1px solid rgba(15,23,42,.08);
  background: rgba(255,255,255,.60);
}
.pm-section-title{
  padding: 10px 12px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  font-weight: 600;
}
#pmDriversList, #pmRidesList{
  max-height: 34vh;
  overflow:auto;
  padding: 8px 10px 10px;
}


/* ===== HUD ===== */
.pm-hud{
  min-width: 220px;
  max-width: 280px;
  padding: 10px;
  border-radius: 16px;
  border: 1px solid rgba(255,255,255,.14);
  background: rgba(17,24,39,.66);
  backdrop-filter: blur(8px);
  color: rgba(255,255,255,.92);
  box-shadow: 0 10px 24px rgba(0,0,0,.25);
}
:root[data-bs-theme="light"] .pm-hud{
  border: 1px solid rgba(15,23,42,.10);
  background: rgba(255,255,255,.78);
  color: rgba(15,23,42,.92);
}

.pm-hud-row{ display:flex; align-items:center; gap:10px; }
.pm-hud-title{ justify-content:space-between; margin-bottom:8px; }
.pm-hud-brand{ font-weight: 900; letter-spacing: .2px; }
.pm-hud-btn{
  border-radius: 12px;
  padding: 6px 10px;
  border: 1px solid rgba(255,255,255,.16);
  background: rgba(0,0,0,.10);
  color: inherit;
  cursor: pointer;
}
:root[data-bs-theme="light"] .pm-hud-btn{ border-color: rgba(15,23,42,.12); }

.pm-cam{ font-size: 12px; opacity: .85; margin-bottom: 10px; }
.pm-cam-follow{ opacity: 1; font-weight: 700; }

.pm-hud-stats{ justify-content: space-between; margin-bottom: 10px; }
.pm-stat{ text-align:center; min-width: 46px; }
.pm-stat-n{ font-weight: 900; font-size: 14px; }
.pm-stat-l{ font-size: 11px; opacity:.75; }

.pm-hud-filters{ gap: 6px; flex-wrap: wrap; }
.pm-chip{
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,.16);
  background: rgba(0,0,0,.12);
  color: inherit;
  font-size: 12px;
  cursor: pointer;
}
:root[data-bs-theme="light"] .pm-chip{
  border-color: rgba(15,23,42,.12);
  background: rgba(255,255,255,.55);
}
.pm-chip.is-on{
  outline: 2px solid rgba(56,189,248,.45);
}

.pm-hud-toast{
  margin-top: 8px;
  font-size: 12px;
  opacity: 0;
  transform: translateY(4px);
  transition: opacity 160ms linear, transform 160ms linear;
}
.pm-hud-toast.show{
  opacity: .9;
  transform: translateY(0);
}
.pm-hud .pm-chip:focus { outline: none; box-shadow: none; }
.pm-hud .pm-chip:focus-visible { outline: 2px solid rgba(56,189,248,.45); outline-offset: 2px; }
.pm-hud .pm-chip:focus,
.pm-hud .pm-hud-btn:focus{
  outline: none !important;
  box-shadow: none !important;
}
    .pm-hud .pm-chip:focus-visible,
.pm-hud .pm-hud-btn:focus-visible{
  outline: 2px solid rgba(56,189,248,.55) !important;
  outline-offset: 2px !important;
}
/* ===== Selection Card ===== */
.pm-selcard{
  position: absolute;
  right: 14px;
  bottom: 44px;
  width: 320px;
  max-width: calc(100% - 28px);
  border-radius: 18px;
  border: 1px solid rgba(255,255,255,.14);
  background: rgba(17,24,39,.72);
  backdrop-filter: blur(10px);
  box-shadow: 0 12px 28px rgba(0,0,0,.30);
  color: rgba(255,255,255,.92);
  padding: 10px;
  display: none;
  z-index: 1200;
}
.pm-selcard.show{ display:block; }
:root[data-bs-theme="light"] .pm-selcard{
  border: 1px solid rgba(15,23,42,.10);
  background: rgba(255,255,255,.86);
  color: rgba(15,23,42,.92);
}

.pm-selcard-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; }
.pm-selcard-title{ font-weight: 900; }
.pm-selcard-x{
  border: 0;
  background: transparent;
  color: inherit;
  font-size: 22px;
  line-height: 1;
  cursor: pointer;
  opacity: .75;
}
.pm-selcard-body{ margin-top: 8px; font-size: 13px; }
.pm-selcard-actions{ display:flex; gap: 8px; margin-top: 10px; }

.pm-btn{
  flex: 1;
  border-radius: 14px;
  padding: 10px 12px;
  border: 1px solid rgba(255,255,255,.16);
  background: rgba(0,0,0,.10);
  color: inherit;
  cursor: pointer;
}
:root[data-bs-theme="light"] .pm-btn{ border-color: rgba(15,23,42,.12); }
.pm-btn-primary{
  border-color: rgba(56,189,248,.35);
}
.pm-btn.is-on{
  outline: 2px solid rgba(56,189,248,.45);
}




.pm-tiles-dark .leaflet-tile-pane{
  filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);
}


    /* ===== Lista de drivers (dock) ===== */
.pm-row{
  padding: 10px 10px;
  border-radius: 12px;
  border: 1px solid rgba(255,255,255,.10);
  background: rgba(0,0,0,.10);
  margin-bottom: 8px;
  cursor: pointer;
}
:root[data-bs-theme="light"] .pm-row{
  border: 1px solid rgba(15,23,42,.08);
  background: rgba(255,255,255,.55);
}
.pm-row.is-sel{
  outline: 2px solid rgba(56,189,248,.45);
}
.pm-row-top{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap: 10px;
}
.pm-row-eco{ font-weight: 800; }
.pm-row-sub{ margin-top: 4px; font-size: 12px; opacity:.92; }

.pm-muted{ opacity:.7; }

/* pills estado */
.pm-pill{
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  border: 1px solid rgba(255,255,255,.14);
  background: rgba(0,0,0,.14);
}
:root[data-bs-theme="light"] .pm-pill{
  border: 1px solid rgba(15,23,42,.12);
  background: rgba(255,255,255,.65);
}
.pm-s-free{ border-color: rgba(34,197,94,.35); }
.pm-s-busy{ border-color: rgba(245,158,11,.35); }
.pm-s-queue{ border-color: rgba(59,130,246,.35); }
.pm-s-off{ border-color: rgba(148,163,184,.25); opacity:.8; }
.pm-s-offered{ border-color: rgba(56,189,248,.35); }
.pm-s-accepted{ border-color: rgba(99,102,241,.35); }
.pm-s-enroute{ border-color: rgba(168,85,247,.35); }
.pm-s-arrived{ border-color: rgba(236,72,153,.35); }
.pm-s-onboard{ border-color: rgba(16,185,129,.35); }


/* =========================
   Monitor Panel (sidebar)
   ========================= */
.pm-panel{
  position: relative;
  width: 330px;
  max-width: 86vw;
  margin-top: var(--pm-top-offset, 0px);
  margin-right: 10px;
  border-radius: 16px;
  overflow: hidden;
  backdrop-filter: blur(10px);
  background: rgba(15, 18, 22, 0.78);
  border: 1px solid rgba(255,255,255,.10);
  box-shadow: 0 10px 28px rgba(0,0,0,.35);
  color: rgba(255,255,255,.92);
}

.pm-panel.is-closed .pm-panel-body{ display:none; }
.pm-panel.is-closed{ width:auto; border-radius: 999px; }

.pm-panel-fab{
  display:flex;
  justify-content:flex-end;
  padding: 8px 8px 0 8px;
}

.pm-fab{
  border: 1px solid rgba(255,255,255,.18);
  background: rgba(0,0,0,.18);
  color: rgba(255,255,255,.92);
  border-radius: 999px;
  padding: 8px 12px;
  font-weight: 600;
  cursor:pointer;
}

.pm-panel-body{
  padding: 10px;
  max-height: calc(100vh - var(--pm-top-offset, 0px) - 110px);
  overflow: auto;
}

.pm-sec{
  border: 1px solid rgba(255,255,255,.10);
  border-radius: 14px;
  padding: 8px;
  margin-bottom: 10px;
  background: rgba(0,0,0,.12);
}

.pm-sec-title{
  display:flex;
  align-items:center;
  justify-content:space-between;
  cursor:pointer;
  list-style:none;
  font-weight: 700;
}

.pm-sec-title::-webkit-details-marker{ display:none; }

.pm-sec-count{
  font-size: 12px;
  padding: 2px 8px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,.14);
  opacity: .9;
}

.pm-sec-tools{
  margin-top: 8px;
  margin-bottom: 8px;
}

.pm-search{
  width: 100%;
  border-radius: 12px;
  padding: 10px 12px;
  border: 1px solid rgba(255,255,255,.14);
  background: rgba(0,0,0,.18);
  color: rgba(255,255,255,.92);
  outline: none;
}

.pm-list{
  display:flex;
  flex-direction:column;
  gap: 8px;
}

.pm-row{
  display:flex;
  gap: 10px;
  align-items:center;
  padding: 10px;
  border-radius: 14px;
  border: 1px solid rgba(255,255,255,.10);
  background: rgba(0,0,0,.16);
  cursor:pointer;
  text-align:left;
}

.pm-row:hover{ border-color: rgba(255,255,255,.18); }
.pm-row.is-selected{ border-color: rgba(120,200,255,.60); box-shadow: 0 0 0 3px rgba(120,200,255,.15); }

.pm-av{
  min-width: 48px;
  height: 48px;
  border-radius: 14px;
  display:flex;
  align-items:center;
  justify-content:center;
  font-weight: 800;
  font-size: 12px;
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.10);
  overflow:hidden;
  text-overflow: ellipsis;
  padding: 0 6px;
}

.pm-row-main{ flex: 1; min-width: 0; }
.pm-row-top{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap: 8px;
}

.pm-row-eco{
  font-weight: 800;
  white-space: nowrap;
  overflow:hidden;
  text-overflow: ellipsis;
}

.pm-row-sub{
  margin-top: 4px;
  font-size: 12px;
  opacity: .88;
  display:flex;
  flex-wrap: wrap;
  gap: 6px;
}

.pm-row-sub .dot{ opacity: .6; }

.pm-badge{
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,.14);
  opacity: .95;
  white-space: nowrap;
}

.pm-badge-idle{  background: rgba(50,220,120,.10); }
.pm-badge-busy{  background: rgba(255,180,60,.10); }
.pm-badge-accepted{ background: rgba(90,170,255,.12); }
.pm-badge-offered{  background: rgba(200,140,255,.12); }
.pm-badge-arrived{  background: rgba(255,255,255,.10); }
.pm-badge-en_route{ background: rgba(90,170,255,.12); }
.pm-badge-on_board{ background: rgba(255,90,90,.12); }
.pm-badge-queue{    background: rgba(255,255,255,.08); }

.pm-qp{
  font-weight: 800;
  opacity: .9;
  padding-left: 6px;
}

.pm-muted{
  opacity: .75;
  font-size: 12px;
  padding: 6px 2px;
}




.pm-av { width: 44px; height: 44px; border-radius: 12px; overflow: hidden; display:flex; align-items:center; justify-content:center; flex: 0 0 44px; }
.pm-av-img img { width: 100%; height: 100%; object-fit: cover; display:block; }
.pm-av-fallback { background: rgba(0,0,0,.06); font-weight: 700; }

.pm-row { width: 100%; display:flex; gap: 10px; align-items:flex-start; border:0; background: transparent; padding: 10px; border-radius: 14px; text-align:left; }
.pm-row.is-selected { outline: 2px solid rgba(0,0,0,.12); }

.pm-row-main { flex: 1 1 auto; min-width: 0; }
.pm-row-top { display:flex; align-items:center; justify-content:space-between; gap:10px; }
.pm-row-eco { font-weight: 700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pm-row-sub { font-size: 12px; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.pm-row-sub2 { margin-top: 2px; }
.dot { opacity:.5; }

.pm-dot { width:8px; height:8px; border-radius:99px; display:inline-block; background:#999; }
.pm-dot.on { background:#2ecc71; }
.pm-dot.off { background:#e67e22; }
.pm-time { font-variant-numeric: tabular-nums; }

.pm-qp { margin-left: 6px; font-weight: 800; opacity:.8; }

.pm-stand-row { width:100%; display:flex; gap:10px; align-items:center; justify-content:space-between; border:0; background: transparent; padding: 10px; border-radius: 14px; text-align:left; }
.pm-pill { min-width: 28px; height: 24px; padding: 0 8px; border-radius: 999px; display:flex; align-items:center; justify-content:center; background: rgba(0,0,0,.06); font-weight: 800; }

.pm-ride-card { padding: 10px; border-radius: 14px; background: rgba(0,0,0,.03); margin-bottom: 8px; }
.pm-ride-top { display:flex; align-items:center; justify-content:space-between; gap:10px; }
.pm-ride-title { font-weight: 800; }
.pm-ride-status { font-weight: 600; opacity:.7; margin-left: 6px; }
.pm-ride-amt { font-weight: 900; }
.pm-ride-actions { margin-top: 8px; display:flex; justify-content:flex-end; }
.pm-mini { border: 1px solid rgba(0,0,0,.12); background: transparent; padding: 6px 10px; border-radius: 10px; }



.pm-ridepeek{
  position:absolute;
  left:50%;
  bottom:14px;
  transform:translateX(-50%);
  width:min(960px, calc(100% - 24px));
  background:rgba(18,18,20,.92);
  color:#fff;
  border:1px solid rgba(255,255,255,.10);
  border-radius:16px;
  box-shadow:0 16px 42px rgba(0,0,0,.35);
  backdrop-filter: blur(10px);
  display:none;
  z-index: 1200;
}
.pm-ridepeek.show{ display:block; }

.pm-rp-head{
  display:flex; align-items:center; justify-content:space-between;
  padding:10px 12px;
  border-bottom:1px solid rgba(255,255,255,.08);
}
.pm-rp-title{ font-weight:700; letter-spacing:.2px; }
.pm-rp-actions{ display:flex; gap:8px; }

.pm-rp-body{ display:flex; gap:12px; padding:12px; }
.pm-rp-left{ width:52px; }
.pm-rp-av{
  width:52px; height:52px; border-radius:14px;
  background:rgba(255,255,255,.08);
  display:flex; align-items:center; justify-content:center;
  overflow:hidden;
}
.pm-rp-av.is-img img{ width:100%; height:100%; object-fit:cover; }
.pm-rp-av-fallback{ font-weight:800; opacity:.9; }

.pm-rp-main{ flex:1; min-width:0; }
.pm-rp-line1{ display:flex; align-items:center; justify-content:space-between; gap:10px; }
.pm-rp-pax{ font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pm-rp-amt{ margin-left:10px; opacity:.95; font-weight:700; }
.pm-rp-line2, .pm-rp-line3{ margin-top:6px; font-size:12px; opacity:.92; display:flex; gap:12px; flex-wrap:wrap; }
.pm-rp-route{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width: 48%; }

.pm-tl{ margin-top:10px; display:flex; gap:10px; align-items:flex-start; }
.pm-tl-step{ display:flex; flex-direction:column; align-items:center; gap:6px; min-width:72px; }
.pm-tl-dot{
  width:10px; height:10px; border-radius:999px;
  background:rgba(255,255,255,.22);
  border:2px solid rgba(255,255,255,.15);
}
.pm-tl-step.is-done .pm-tl-dot{ background:rgba(45,215,120,.9); border-color:rgba(45,215,120,.55); }
.pm-tl-step.is-active .pm-tl-dot{ background:rgba(90,160,255,.95); border-color:rgba(90,160,255,.55); }
.pm-tl-lab{ font-size:11px; opacity:.92; }

@media (max-width: 720px){
  .pm-ridepeek{ bottom:10px; }
  .pm-rp-route{ max-width: 100%; }
  .pm-tl-step{ min-width:64px; }
}
/* =========================
   FIXES SOLO PARA PM PANEL
   ========================= */

/* Base panel (dark ya lo tienes). En LIGHT lo hacemos realmente claro y legible */
:root[data-bs-theme="light"] .pm-panel{
  background: rgba(255,255,255,.92);
  border: 1px solid rgba(15,23,42,.12);
  box-shadow: 0 10px 28px rgba(15,23,42,.18);
  color: rgba(15,23,42,.92);
}

/* Secciones dentro del panel */
:root[data-bs-theme="light"] .pm-panel .pm-sec{
  background: rgba(15,23,42,.04);
  border-color: rgba(15,23,42,.10);
}

/* Títulos y contadores */
:root[data-bs-theme="light"] .pm-panel .pm-sec-title{
  color: rgba(15,23,42,.92);
}
:root[data-bs-theme="light"] .pm-panel .pm-sec-count{
  border-color: rgba(15,23,42,.14);
  color: rgba(15,23,42,.82);
  background: rgba(15,23,42,.04);
}

/* Search */
:root[data-bs-theme="light"] .pm-panel .pm-search{
  background: rgba(255,255,255,.86);
  border-color: rgba(15,23,42,.14);
  color: rgba(15,23,42,.92);
}
:root[data-bs-theme="light"] .pm-panel .pm-search::placeholder{
  color: rgba(15,23,42,.55);
}

/* Rows: aquí neutralizamos tus overrides tardíos que las dejan transparentes */
.pm-panel .pm-row{
  border: 1px solid rgba(255,255,255,.10);
  background: rgba(0,0,0,.10);
}
:root[data-bs-theme="light"] .pm-panel .pm-row{
  border: 1px solid rgba(15,23,42,.10);
  background: rgba(255,255,255,.70);
  color: rgba(15,23,42,.92);
}
:root[data-bs-theme="light"] .pm-panel .pm-row:hover{
  border-color: rgba(15,23,42,.18);
}

/* Pills/badges dentro del panel */
:root[data-bs-theme="light"] .pm-panel .pm-badge,
:root[data-bs-theme="light"] .pm-panel .pm-pill{
  border-color: rgba(15,23,42,.14);
  color: rgba(15,23,42,.82);
  background: rgba(15,23,42,.04);
}

/* Si tienes elementos que quedaron con color hardcodeado oscuro por colisión */
:root[data-bs-theme="light"] .pm-panel .pm-muted,
:root[data-bs-theme="light"] .pm-panel .pm-row-sub{
  color: rgba(15,23,42,.70);
}

/* Asegura que links o spans hereden bien */
.pm-panel *{ color: inherit; }

.pm-panel.is-closed .pm-panel-body{ display:none; }
.pm-panel.is-closed{ width:auto; border-radius: 999px; }

  `;




  const el = document.createElement('style');
  el.textContent = css;
  document.head.appendChild(el);
}
