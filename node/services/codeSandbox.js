// JavaScript sandbox for the flow builder "Code" node. (ESM — node/ is
// "type":"module".)
//
// DESIGN GOAL: works OUT OF THE BOX. The buyer drops the node in, writes
// code, it runs — no npm install, no .env flag. Default engine = Node's
// BUILT-IN `vm` module (always present). If `isolated-vm` happens to be
// installed, we use it automatically for hardened isolation — never
// required.
//
// Hardening that applies to BOTH engines:
//   * User code gets ONLY previousResponse / allResponses / functionArgs,
//     injected as a JSON STRING and re-parsed INSIDE the sandbox — so no
//     host object (and no host constructor chain) is reachable.
//   * No require / process / fs / network / Buffer / global is exposed.
//   * Output returns as a JSON string and is re-parsed host-side, so the
//     sandbox heap never leaks back out.
//   * A wall-clock timeout caps runaway loops.

import { Worker } from 'node:worker_threads';

// Dedicated worker file that runs the node:vm script under a hard heap cap.
const VM_WORKER_URL = new URL('./codeSandboxWorker.js', import.meta.url);

let ivm = null;
let ivmTried = false;
async function loadIvm() {
  if (ivmTried) return ivm;
  ivmTried = true;
  try {
    const m = await import('isolated-vm');   // optional dependency
    ivm = m?.default || m;
  } catch (_) {
    ivm = null;
  }
  return ivm;
}

function buildWrapped(code) {
  // Returns a JSON string of the user's return value (or null).
  return `
    (function () {
      var __ctx = JSON.parse(__inputs);
      var previousResponse = __ctx.previousResponse;
      var allResponses     = __ctx.allResponses;
      var functionArgs     = __ctx.functionArgs;
      var __run = function () { ${code}
      };
      var __out = __run();
      return JSON.stringify(__out === undefined ? null : __out);
    })()
  `;
}

// --- Stronger engine: isolated-vm (used only if installed) ---------------
async function runWithIvm(mod, code, inputsJson, timeoutMs, memoryMb) {
  const isolate = new mod.Isolate({ memoryLimit: memoryMb });
  try {
    const context = await isolate.createContext();
    await context.global.set('__inputs', String(inputsJson));
    const script = await isolate.compileScript(buildWrapped(code));
    return await script.run(context, { timeout: timeoutMs });
  } finally {
    try { isolate.dispose(); } catch (_) { /* already gone */ }
  }
}

// --- Default engine: built-in node:vm, run inside a resource-capped worker ---
// node:vm alone has NO memory bound (finding #34); a worker_thread with
// resourceLimits.maxOldGenerationSizeMb gives a hard heap cap so a runaway
// allocation kills only the worker, never the shared main process. The vm
// timeout still caps synchronous CPU; the parent adds a kill-timer as backstop.
function runWithVm(code, inputsJson, timeoutMs, memoryMb) {
  return new Promise((resolve, reject) => {
    let settled = false;
    let worker;
    const finish = (fn, val) => {
      if (settled) return;
      settled = true;
      clearTimeout(killTimer);
      try { if (worker) worker.terminate(); } catch (_) { /* already gone */ }
      fn(val);
    };
    // Backstop: if the vm's own timeout somehow doesn't unwind (e.g. code
    // pinned in a native op), forcibly terminate the worker shortly after.
    const killTimer = setTimeout(
      () => finish(reject, new Error('Code execution exceeded time limit')),
      timeoutMs + 1000
    );
    try {
      worker = new Worker(VM_WORKER_URL, {
        workerData: { wrapped: buildWrapped(code), inputsJson: String(inputsJson), timeoutMs },
        resourceLimits: {
          maxOldGenerationSizeMb: memoryMb,
          maxYoungGenerationSizeMb: Math.max(2, Math.floor(memoryMb / 4)),
        },
      });
    } catch (e) {
      return finish(reject, e instanceof Error ? e : new Error(String(e)));
    }
    worker.on('message', (m) => {
      if (m && m.ok) finish(resolve, m.out);
      else finish(reject, new Error((m && m.error) || 'sandbox error'));
    });
    worker.on('error', (e) => finish(reject, e instanceof Error ? e : new Error(String(e))));
    worker.on('exit', (codeExit) => {
      if (settled) return;
      // Non-message exit → almost always ERR_WORKER_OUT_OF_MEMORY (the heap
      // cap fired) or a forced terminate.
      finish(reject, new Error(`Code sandbox terminated (exit ${codeExit}) — memory limit exceeded`));
    });
  });
}

/**
 * Run user JS. Always available (no env flag, no required install).
 * @returns {Promise<{ok:boolean, error:string|null, result:any, engine:string}>}
 */
export async function runUserCode(code, inputs = {}, opts = {}) {
  const timeoutMs = Math.min(Math.max(parseInt(opts.timeoutMs || 2000, 10) || 2000, 100), 10000);
  const memoryMb  = Math.min(Math.max(parseInt(opts.memoryMb  || 16,   10) || 16,   8),   128);

  if (typeof code !== 'string' || code.trim() === '') {
    return { ok: true, error: null, result: null, engine: 'noop' };
  }

  const inputsJson = String(JSON.stringify(inputs || {}));
  const mod = await loadIvm();
  const engine = mod ? 'isolated-vm' : 'vm';

  try {
    const raw = mod
      ? await runWithIvm(mod, code, inputsJson, timeoutMs, memoryMb)
      : await runWithVm(code, inputsJson, timeoutMs, memoryMb);

    let result = null;
    try { result = JSON.parse(raw); } catch (_) { result = raw; }
    return { ok: true, error: null, result, engine };
  } catch (e) {
    const msg = String((e && e.message) || e || 'sandbox error');
    return { ok: false, error: msg.slice(0, 300), result: null, engine };
  }
}
