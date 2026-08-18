// CommonJS launcher for cPanel "Setup Node.js App" (LiteSpeed/Passenger).
//
// WHY: cPanel loads the startup file with require(). Our real entry, index.js,
// is an ES Module ("type":"module", uses import/export), and require() cannot
// load an ES Module -> ERR_REQUIRE_ESM. This .cjs file IS CommonJS (so require()
// loads it fine), and it then dynamic-import()s the ESM app — dynamic import is
// allowed inside CommonJS and runs the ES Module natively on Node 18.
//
// HOW TO USE in cPanel -> Setup Node.js App:
//   1. Set "Application startup file" to:  app.cjs   (instead of index.js)
//   2. Click "Run NPM Install"
//   3. Restart the app.
// index.js still listens on process.env.PORT, which cPanel sets for you.
import('./index.js').catch((err) => {
  console.error('[app.cjs] Failed to start the Node app:', err);
  process.exit(1);
});
