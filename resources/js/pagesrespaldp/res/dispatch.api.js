/* resources/js/pages/dispatch.api.js
 * Compat layer: re-exporta helpers desde core.js
 */
export {
  qs,
  qsa,
  escapeHtml,
  fmt,
  isDarkMode,
  getTenantId,
  ensureTenantGlobals,
  jsonHeaders,

  // fechas DB sin TZ
  extractPartsFromDbTs,
  fmtHM12_fromDb,
  fmtShortDay_fromDb,
  fmtWhen_db,

  // google loader
  haveFullGoogle,
  loadGoogleMaps,

  // debug
  DISPATCH_DEBUG,
  dbg,
  logListDebug,
} from './dispatch.core.js';
