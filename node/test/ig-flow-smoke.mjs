// End-to-end smoke test of the ported Instagram flow runtime.
// Runs the REAL instagramFlowService.js walker. A local mock server plays BOTH
// the Graph API (captures every /{ig}/messages send) AND the Laravel callbacks
// (flow-log / flow-node) — so NO real Meta send ever leaves the box.
//
//   node node/test/ig-flow-smoke.mjs
import http from "node:http";
import {
  runFlow, resumeFlow, hasSession, pruneSessions, delayMsOf,
} from "../services/instagramFlowService.js";

// ---- mock server: Graph sends + Laravel callbacks -------------------------
const SENT = [];      // every graphSend message object
const LOGGED = [];    // every flow-log payload
let askHandler = () => ({}); // flow-node responder (per-test)

const server = http.createServer((req, res) => {
  let raw = "";
  req.on("data", (c) => (raw += c));
  req.on("end", () => {
    let body = {};
    try { body = raw ? JSON.parse(raw) : {}; } catch {}
    res.setHeader("content-type", "application/json");
    const path = req.url.split("?")[0];   // drop ?access_token=… so matching works
    if (path.endsWith("/messages")) {
      SENT.push(body.message || body);
      return res.end(JSON.stringify({ message_id: "mid_" + SENT.length, recipient_id: "r" }));
    }
    if (path.endsWith("/api/instagram/flow-log")) {
      LOGGED.push(body);
      return res.end(JSON.stringify({ ok: true }));
    }
    if (path.endsWith("/api/instagram/flow-node")) {
      return res.end(JSON.stringify(askHandler(body) || {}));
    }
    res.statusCode = 404; res.end("{}");
  });
});

// ---- tiny assert harness --------------------------------------------------
let P = 0, F = 0;
const ok = (label, cond, detail = "") => {
  cond ? P++ : F++;
  console.log(`  [${cond ? "PASS" : "FAIL"}] ${label}${detail ? "  — " + detail : ""}`);
};
const textsSent = () => SENT.map((m) => m?.text || (m?.attachment ? `[${m.attachment.type}]` : (m?.quick_replies ? "[qr]" : JSON.stringify(m)))).filter(Boolean);
const reset = () => { SENT.length = 0; LOGGED.length = 0; askHandler = () => ({}); };

// ---- flow fixtures --------------------------------------------------------
const AUTH = () => ({ base: `http://127.0.0.1:${PORT}`, ig: "IG123", token: "tok" });
const APP = () => `http://127.0.0.1:${PORT}`;
const ACC = 42, WS = 5, IGSID = "cust999";

// trigger → message → condition(contains "buy") → yes:msg / no:msg → end
const conditionFlow = {
  flowNodes: [
    { id: "t", type: "trigger", data: {} },
    { id: "m1", type: "message", data: { text: "Hi {{text}}" } },
    { id: "c", type: "condition", data: { variable: "{{text}}", operator: "contains", value: "buy" } },
    { id: "myes", type: "message", data: { text: "You want to BUY" } },
    { id: "mno", type: "message", data: { text: "Just browsing" } },
    { id: "e", type: "end", data: {} },
  ],
  flowEdges: [
    { source: "t", target: "m1", sourceHandle: "out" },
    { source: "m1", target: "c", sourceHandle: "out" },
    { source: "c", target: "myes", sourceHandle: "yes" },
    { source: "c", target: "mno", sourceHandle: "no" },
    { source: "myes", target: "e", sourceHandle: "out" },
    { source: "mno", target: "e", sourceHandle: "out" },
  ],
};

// trigger → buttons(Red/Blue) [park] → p0:msg / p1:msg → end
const buttonsFlow = {
  flowNodes: [
    { id: "t", type: "trigger", data: {} },
    { id: "b", type: "buttons", data: { prompt: "Pick a colour", options: ["Red", "Blue"] } },
    { id: "mr", type: "message", data: { text: "RED chosen" } },
    { id: "mb", type: "message", data: { text: "BLUE chosen" } },
    { id: "e", type: "end", data: {} },
  ],
  flowEdges: [
    { source: "t", target: "b", sourceHandle: "out" },
    { source: "b", target: "mr", sourceHandle: "p0" },
    { source: "b", target: "mb", sourceHandle: "p1" },
    { source: "mr", target: "e", sourceHandle: "out" },
    { source: "mb", target: "e", sourceHandle: "out" },
  ],
};

// trigger → ask("Your name?", var=name, options yes/no) [park] → p0/else → end
const askFlow = {
  flowNodes: [
    { id: "t", type: "trigger", data: {} },
    { id: "a", type: "ask", data: { prompt: "Do you agree? (yes/no)", var: "answer", options: ["yes", "no"] } },
    { id: "myes", type: "message", data: { text: "Great, saved {{answer}}" } },
    { id: "melse", type: "message", data: { text: "No worries" } },
    { id: "e", type: "end", data: {} },
  ],
  flowEdges: [
    { source: "t", target: "a", sourceHandle: "out" },
    { source: "a", target: "myes", sourceHandle: "p0" },
    { source: "a", target: "melse", sourceHandle: "else" },
    { source: "myes", target: "e", sourceHandle: "out" },
    { source: "melse", target: "e", sourceHandle: "out" },
  ],
};

// trigger → ai [askLaravel] → message uses reply → end
const aiFlow = {
  flowNodes: [
    { id: "t", type: "trigger", data: {} },
    { id: "ai", type: "ai", data: { save: "aitext" } },
    { id: "m", type: "message", data: { text: "Echo: {{aitext}}" } },
    { id: "e", type: "end", data: {} },
  ],
  flowEdges: [
    { source: "t", target: "ai", sourceHandle: "out" },
    { source: "ai", target: "m", sourceHandle: "out" },
    { source: "m", target: "e", sourceHandle: "out" },
  ],
};

let PORT = 0;

async function main() {
  await new Promise((r) => server.listen(0, "127.0.0.1", r));
  PORT = server.address().port;
  console.log(`mock server on :${PORT}\n`);

  // ---- 0. pure helpers ----
  console.log("=== 0. delayMsOf unit mapping ===");
  ok("2 sec → 2000ms", delayMsOf({ amount: 2, unit: "sec" }) === 2000);
  ok("3 min → 180000ms", delayMsOf({ amount: 3, unit: "min" }) === 180000);
  ok("1 hour → 3600000ms", delayMsOf({ amount: 1, unit: "hour" }) === 3600000);
  ok("0 → 0ms (no wait)", delayMsOf({ amount: 0 }) === 0);

  // ---- 1. condition TRUE branch ----
  console.log("\n=== 1. condition — TRUE (contains 'buy') ===");
  reset();
  await runFlow({ auth: AUTH(), flow: conditionFlow, igsid: IGSID, text: "i want to buy", flowId: 1, accountId: ACC, workspaceId: WS, appDomain: APP(), vars: {} });
  const t1 = textsSent();
  ok("greeting substituted {{text}}", t1.includes("Hi i want to buy"), t1[0]);
  ok("YES branch taken", t1.includes("You want to BUY"));
  ok("NO branch NOT taken", !t1.includes("Just browsing"));
  ok("session cleared after end", !hasSession(ACC, IGSID));
  ok("each send logged to Laravel", LOGGED.length === t1.length, `${LOGGED.length} logs / ${t1.length} sends`);

  // ---- 2. condition FALSE branch ----
  console.log("\n=== 2. condition — FALSE (no 'buy') ===");
  reset();
  await runFlow({ auth: AUTH(), flow: conditionFlow, igsid: IGSID, text: "just looking", flowId: 1, accountId: ACC, workspaceId: WS, appDomain: APP(), vars: {} });
  const t2 = textsSent();
  ok("NO branch taken", t2.includes("Just browsing"));
  ok("YES branch NOT taken", !t2.includes("You want to BUY"));

  // ---- 3. buttons park + resume via payload tap ----
  console.log("\n=== 3. buttons — park then resume (tap OPT_1) ===");
  reset();
  await runFlow({ auth: AUTH(), flow: buttonsFlow, igsid: IGSID, text: "", flowId: 2, accountId: ACC, workspaceId: WS, appDomain: APP(), vars: {} });
  ok("quick-reply prompt sent", SENT.some((m) => Array.isArray(m?.quick_replies) && m.quick_replies.length === 2));
  ok("flow PARKED awaiting tap", hasSession(ACC, IGSID));
  const consumed3 = await resumeFlow({ accountId: ACC, igsid: IGSID, text: "OPT_1" });
  ok("resume consumed the tap", consumed3 === true);
  ok("p1 (BLUE) branch taken", textsSent().includes("BLUE chosen"));
  ok("p0 (RED) NOT taken", !textsSent().includes("RED chosen"));
  ok("session cleared after resume→end", !hasSession(ACC, IGSID));

  // ---- 3b. buttons resume by typed label ----
  console.log("\n=== 3b. buttons — resume by typed label 'Red' ===");
  reset();
  await runFlow({ auth: AUTH(), flow: buttonsFlow, igsid: IGSID, text: "", flowId: 2, accountId: ACC, workspaceId: WS, appDomain: APP(), vars: {} });
  await resumeFlow({ accountId: ACC, igsid: IGSID, text: "Red" });
  ok("typed label matched p0 (RED)", textsSent().includes("RED chosen"));

  // ---- 3c. non-matching reply must NOT consume the parked button ----
  console.log("\n=== 3c. buttons — free text does NOT consume the tap ===");
  reset();
  await runFlow({ auth: AUTH(), flow: buttonsFlow, igsid: IGSID, text: "", flowId: 2, accountId: ACC, workspaceId: WS, appDomain: APP(), vars: {} });
  const consumed3c = await resumeFlow({ accountId: ACC, igsid: IGSID, text: "banana" });
  ok("free text declined (returns false)", consumed3c === false);
  ok("session left intact for a real tap", hasSession(ACC, IGSID));
  clearMock(ACC, IGSID);

  // ---- 4. ask park + resume with expected-option branch + var capture ----
  console.log("\n=== 4. ask — park, answer 'yes' → p0 + capture var ===");
  reset();
  await runFlow({ auth: AUTH(), flow: askFlow, igsid: IGSID, text: "", flowId: 3, accountId: ACC, workspaceId: WS, appDomain: APP(), vars: {} });
  ok("question sent", textsSent().some((s) => s.startsWith("Do you agree")));
  ok("flow PARKED awaiting answer", hasSession(ACC, IGSID));
  await resumeFlow({ accountId: ACC, igsid: IGSID, text: "yes" });
  const t4 = textsSent();
  ok("p0 branch taken on 'yes'", t4.some((s) => s.startsWith("Great, saved")));
  ok("answer var captured into {{answer}}", t4.includes("Great, saved yes"));

  // ---- 4b. ask — unexpected answer → else branch ----
  console.log("\n=== 4b. ask — answer 'maybe' → else branch ===");
  reset();
  await runFlow({ auth: AUTH(), flow: askFlow, igsid: IGSID, text: "", flowId: 3, accountId: ACC, workspaceId: WS, appDomain: APP(), vars: {} });
  await resumeFlow({ accountId: ACC, igsid: IGSID, text: "maybe" });
  ok("else branch taken", textsSent().includes("No worries"));

  // ---- 5. AI node delegates to Laravel (askLaravel) ----
  console.log("\n=== 5. ai node — delegates to Laravel flow-node ===");
  reset();
  askHandler = (body) => (body.action === "ai" ? { reply: "hello from laravel" } : {});
  await runFlow({ auth: AUTH(), flow: aiFlow, igsid: IGSID, text: "hey", flowId: 4, accountId: ACC, workspaceId: WS, appDomain: APP(), vars: {} });
  const t5 = textsSent();
  ok("AI reply sent", t5.includes("hello from laravel"));
  ok("AI reply saved + substituted downstream", t5.includes("Echo: hello from laravel"));

  // ---- 6. resume with no session is a clean no-op ----
  console.log("\n=== 6. resume with no parked session ===");
  reset();
  const consumed6 = await resumeFlow({ accountId: ACC, igsid: "nobody", text: "hi" });
  ok("returns false (nothing to resume)", consumed6 === false);

  // ---- 7. prune ----
  console.log("\n=== 7. pruneSessions ===");
  ok("prune runs without throwing", typeof pruneSessions() === "number");

  console.log(`\n================  RESULT: ${P} passed / ${F} failed  ================`);
  server.close();
  process.exit(F === 0 ? 0 : 1);
}

// helper to drop a lingering parked session between tests
function clearMock(acc, igsid) {
  // resume with a matching tap to consume, or just prune-by-force via a dummy tap
  if (hasSession(acc, igsid)) resumeFlow({ accountId: acc, igsid, text: "OPT_0" });
}

main().catch((e) => { console.error(e); process.exit(1); });
