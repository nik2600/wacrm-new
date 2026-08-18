// Worker thread that actually runs the flow "Code" node's node:vm script.
// (ESM — node/ is "type":"module".)
//
// WHY A WORKER (finding #34): node:vm enforces ONLY a wall-clock timeout, no
// heap cap. A tenant Code node like `let a=[]; while(true) a.push(new
// Uint8Array(1e7))` can exhaust the V8 heap before the timeout fires and
// OOM-kill the single shared Node bridge, dropping every tenant's WhatsApp
// session. Running the vm inside a worker_thread lets the parent impose a hard
// `resourceLimits.maxOldGenerationSizeMb` bound: on overflow the WORKER dies
// with ERR_WORKER_OUT_OF_MEMORY (surfaced to the parent as an error/exit) and
// the main event-loop process survives. The same timeout still caps CPU.
//
// The parent passes the already-wrapped script + inputs JSON via workerData;
// user code only ever sees the re-parsed JSON inputs (no host objects), exactly
// as before.

import vm from 'node:vm';
import { parentPort, workerData } from 'node:worker_threads';

try {
  // Object.create(null) → context global has no inherited prototype. Expose
  // ONLY the inputs string; the vm context supplies its OWN intrinsics.
  const sandbox = Object.create(null);
  sandbox.__inputs = String(workerData.inputsJson);
  const context = vm.createContext(sandbox, {
    codeGeneration: { strings: true, wasm: false },
  });
  const script = new vm.Script(String(workerData.wrapped), { filename: 'flow-code-node.js' });
  const out = script.runInContext(context, { timeout: workerData.timeoutMs, breakOnSigint: true });
  parentPort.postMessage({ ok: true, out });
} catch (e) {
  parentPort.postMessage({ ok: false, error: String((e && e.message) || e || 'sandbox error') });
}
