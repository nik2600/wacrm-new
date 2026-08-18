/**
 * CommonJS bootstrap for hosts that START the app with require()
 * (LiteSpeed / cPanel / CloudLinux Node Selector / Hostinger).
 *
 * The app (index.js) is an ESM module and its dependency graph uses
 * top-level await, which Node's require() refuses to load
 * (ERR_REQUIRE_ASYNC_MODULE on Node 20.19+/22/24, ERR_REQUIRE_ESM on 18).
 *
 * Dynamic import() CAN load an ESM graph with top-level await — so we just
 * hand off to it. index.js binds its own port via app.listen(), so nothing
 * else is needed here.
 *
 * In the host panel set the "Entry file" to:  server.cjs
 */
import('./index.js').catch((err) => {
  console.error('[bootstrap] Failed to start WaDesk node app:', err);
  process.exit(1);
});
