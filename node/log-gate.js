/*
 * ── Global Node log gate ──────────────────────────────────────────────────
 * One switch for every console.log() in the helper service — no need to find
 * or delete the individual calls. It silences console.log / .info / .debug
 * across the WHOLE service in BOTH local and production; console.error and
 * console.warn still print so real problems stay visible.
 *
 * It's imported FIRST in index.js, so it also catches logs emitted while the
 * other modules are loading.
 *
 * To see all logs again, EITHER:
 *   • set  WADESK_LOGS=on  in the environment, OR
 *   • comment out the  import "./log-gate.js";  line at the top of index.js.
 */
// ── TEMPORARILY DISABLED for campaign-send debugging ──────────────────────
// The silencer below is commented out so EVERY console.log/info/debug prints
// (campaign send tracing). To restore the quiet production behaviour, just
// un-comment this block again.
// if (process.env.WADESK_LOGS !== "on") {
//     const noop = () => {};
//     console.log = noop;
//     console.info = noop;
//     console.debug = noop;
// }
