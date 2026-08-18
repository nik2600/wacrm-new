// services/instagramFlowService.js
// ================================
// Instagram flow engine — the Node counterpart of flowService.js.
//
// WHY THIS EXISTS
// ---------------
// Instagram flows used to run in PHP (App\Services\Instagram\IgFlowRunner)
// inside Meta's webhook request. That works, but a webhook handler cannot
// sleep, so a "Wait 5 min" node had to park the session in the DB and rely on
// a later request to sweep it — meaning the wait fired late on a quiet
// account. Baileys flows never had that problem because Node is a long-lived
// process and can simply `await` a timer.
//
// This module gives Instagram the SAME model as WhatsApp: Laravel hands the
// flow off, Node walks it in the background, real awaits for delays, and the
// customer's next DM resumes a parked node.
//
// PURELY ADDITIVE. Nothing here is imported by flowService.js or the Baileys
// client manager — the WhatsApp path is untouched. Mirrors the precedent set
// by providerFlowController.js, which added WABA/Twilio the same way.
//
// DIVISION OF LABOUR
//   Node    → walks the graph, times the delays, calls the Graph API to send
//   Laravel → business logic that needs DB/keys (AI reply, catalog carousel,
//             lead capture) and all message logging, reached over
//             /api/instagram/flow-node and /api/instagram/flow-log.
import axios from "axios";

const IG_SESSIONS = new Map(); // `${accountId}_${igsid}` → session

const nodeHeaders = () => ({
  "X-Node-Token": process.env.NODE_WEBHOOK_TOKEN || "",
  Accept: "application/json",
});

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const sessionKeyFor = (accountId, igsid) => `${accountId}_${igsid}`;

// ---------------------------------------------------------------------------
// Graph API sends. Deliberately NOT reusing igGraphClient.js — that file is
// shared with the scheduler/reposter and this build must not modify it. These
// are the four Send-API shapes an Instagram flow needs.
// ---------------------------------------------------------------------------
async function graphSend(auth, message) {
  const base = String(auth?.base || "").replace(/\/+$/, "");
  const ig = String(auth?.ig || auth?.igUserId || "");
  const token = String(auth?.token || "");
  if (!base || !ig || !token) throw new Error("instagram auth incomplete (base/ig/token)");

  // FULL request logging — the exact JSON body sent to Meta (token redacted).
  const kind = message?.message?.attachment?.payload?.template_type
    ? `template:${message.message.attachment.payload.template_type}`
    : message?.message?.attachment?.type
      ? `attachment:${message.message.attachment.type}`
      : message?.message?.quick_replies
        ? "quick_replies"
        : "text";
  console.log(`[IG-SEND→] host=${base} ig=${ig} kind=${kind} body=${JSON.stringify(message)}`);

  const r = await axios.post(
    `${base}/${ig}/messages`,
    message,
    { params: { access_token: token }, timeout: 30000, validateStatus: () => true }
  );
  if (r.status >= 400 || r.data?.error) {
    const e = r.data?.error || { message: `HTTP ${r.status}` };
    console.error(`[IG-SEND✗] kind=${kind} HTTP ${r.status} error=${JSON.stringify(r.data?.error || r.data)}`);
    throw new Error(`${e.code || r.status}/${e.error_subcode || 0}: ${e.message || "graph error"}`);
  }
  console.log(`[IG-SEND✓] kind=${kind} HTTP ${r.status} resp=${JSON.stringify(r.data)}`);
  return r.data;
}

const sendText = (auth, igsid, text) =>
  graphSend(auth, { recipient: { id: igsid }, message: { text: String(text || "") } });

const sendAttachment = (auth, igsid, type, url) =>
  graphSend(auth, {
    recipient: { id: igsid },
    message: { attachment: { type, payload: { url: String(url), is_reusable: true } } },
  });

// Meta caps quick replies at 13 and titles at 20 chars; over either and the
// whole send is rejected, so clamp rather than fail.
const sendQuickReplies = (auth, igsid, text, options) =>
  graphSend(auth, {
    recipient: { id: igsid },
    message: {
      text: String(text || ""),
      quick_replies: options.slice(0, 13).map((o, i) => ({
        content_type: "text",
        title: String(o.title ?? o).slice(0, 20),
        payload: String(o.payload ?? `OPT_${i}`),
      })),
    },
  });

// Instagram does NOT support the Messenger "button" template, and quick-reply
// chips are transient (they vanish once the user sends anything). The reliable
// way to show PERSISTENT, tappable buttons on Instagram is the GENERIC template
// with a single element whose buttons carry the choices. Renders as a card with
// up to 3 tappable buttons that stay in the conversation.
const sendGenericButtons = (auth, igsid, text, buttons, imageUrl) => {
  const el = {
    title: String(text || "Choose one").slice(0, 80) || "Choose one",
    buttons: buttons.slice(0, 3).map((b, i) => (
      String(b.type) === "web_url"
        ? { type: "web_url", url: String(b.url || ""), title: String(b.title || "").slice(0, 20) }
        : { type: "postback", title: String(b.title || b).slice(0, 20), payload: String(b.payload ?? b.title ?? `OPT_${i}`) }
    )),
  };
  // image_url is OPTIONAL on Instagram's generic template — include it only when
  // the flow author supplied one, so a plain button prompt ("What next?") does
  // NOT show a redundant image above the buttons.
  if (imageUrl) el.image_url = String(imageUrl);
  return graphSend(auth, {
    recipient: { id: igsid },
    message: { attachment: { type: "template", payload: { template_type: "generic", elements: [el] } } },
  });
};

// Back-compat alias — ig_buttons used to call sendButtons (button template);
// route it through the generic-template sender so it renders on Instagram.
const sendButtons = sendGenericButtons;

// ---------------------------------------------------------------------------
// Laravel bridges — business logic and logging stay on the PHP side so there is
// exactly ONE implementation of AI / catalog / lead capture in the codebase.
// ---------------------------------------------------------------------------
async function logToLaravel(appDomain, payload) {
  try {
    await axios.post(`${appDomain}/api/instagram/flow-log`, payload,
      { headers: nodeHeaders(), timeout: 15000 });
  } catch (e) {
    // Never let a logging failure break the conversation.
    console.warn(`[IG-FLOW-NODE] flow-log failed: ${e?.message}`);
  }
}

async function askLaravel(appDomain, payload) {
  const r = await axios.post(`${appDomain}/api/instagram/flow-node`, payload,
    { headers: nodeHeaders(), timeout: 60000, validateStatus: () => true });
  if (r.status >= 400) throw new Error(`flow-node HTTP ${r.status}: ${JSON.stringify(r.data)}`);
  return r.data || {};
}

// ---------------------------------------------------------------------------
// Graph helpers
// ---------------------------------------------------------------------------
const nodesOf = (flow) => (flow?.flowNodes || flow?.nodes || []);
const edgesOf = (flow) => (flow?.flowEdges || flow?.edges || []);

function indexNodes(flow) {
  const map = new Map();
  for (const n of nodesOf(flow)) if (n?.id) map.set(String(n.id), n);
  return map;
}

/** Follow the edge leaving nodeId on `port`, falling back to any out edge. */
function nextNode(flow, nodeId, port = "out") {
  let any = null;
  for (const e of edgesOf(flow)) {
    if (String(e?.source) !== String(nodeId)) continue;
    if (any === null) any = String(e?.target || "");
    if (String(e?.sourceHandle || "out") === port) return String(e?.target || "");
  }
  return port === "out" ? any : null;
}

function entryNode(flow) {
  for (const n of nodesOf(flow)) if (String(n?.type) === "trigger") return n;
  return null;
}

const subst = (s, vars) =>
  String(s ?? "").replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (_, k) => String(vars?.[k] ?? ""));

/** Wait node {amount, unit} → ms. Unknown unit falls back to minutes. */
export function delayMsOf(d) {
  const amount = Number(d?.amount ?? d?.delay ?? d?.value ?? 0);
  if (!(amount > 0)) return 0;
  const unit = String(d?.unit || "min").toLowerCase();
  const mult = unit.startsWith("s") ? 1000
    : unit.startsWith("h") ? 3_600_000
      : unit.startsWith("d") ? 86_400_000
        : 60_000;
  return Math.round(amount * mult);
}

/** Chat `buttons` options are plain strings; the Send API wants objects. */
const chatOptionsToQuickReplies = (d) =>
  (d?.options || [])
    .map((o, i) => ({ title: String(typeof o === "object" ? (o.title ?? o.label ?? "") : o).trim(), payload: `OPT_${i}` }))
    .filter((o) => o.title !== "")
    .slice(0, 13);

function evalCondition(d, vars) {
  const left = subst(d?.variable ?? d?.left ?? "{{text}}", vars).toLowerCase().trim();
  const right = subst(d?.value ?? d?.right ?? "", vars).toLowerCase().trim();
  switch (String(d?.operator ?? d?.op ?? "contains")) {
    case "equals": case "=": case "==": return left === right;
    case "not_equals": case "!=": return left !== right;
    case "starts_with": return left.startsWith(right);
    default: return right === "" ? true : left.includes(right);
  }
}

// ---------------------------------------------------------------------------
// The walker
// ---------------------------------------------------------------------------
/**
 * Walk from `startId` until the flow ends or parks on a node awaiting the
 * customer. Delays are REAL awaits — this runs detached from any HTTP request,
 * which is the whole reason Instagram flows moved into Node.
 */
async function walk(ctx, startId) {
  const { auth, flow, igsid, appDomain, accountId, flowId, workspaceId } = ctx;
  const nodes = indexNodes(flow);
  let current = startId;
  let guard = 0;

  console.log(`[IG-WALK] start flow=${flowId} igsid=${igsid} from=${startId} nodes=${nodes.size} vars=${JSON.stringify(ctx.vars || {})}`);

  while (current && guard++ < 100) {
    const node = nodes.get(String(current));
    if (!node) { console.warn(`[IG-WALK] node id="${current}" NOT FOUND — ending flow=${flowId}`); break; }
    const type = String(node.type || "");
    const d = node.data || {};
    let port = "out";

    console.log(`[IG-NODE] → type=${type} id=${node.id} flow=${flowId} data=${JSON.stringify(d).slice(0, 300)}`);

    try {
      switch (type) {
        // ---- shared nodes (same types the WhatsApp builder uses) ----------
        case "message": {
          const body = subst(d.text, ctx.vars);
          if (body.trim() !== "") {
            const r = await sendText(auth, igsid, body);
            await logToLaravel(appDomain, { accountId, igsid, workspaceId, direction: "out", body, source: "flow", mid: r?.message_id || null });
          }
          break;
        }

        case "media": {
          let url = subst(d.url ?? d.mediaUrl, ctx.vars).trim();
          if (url && !/^https?:\/\//i.test(url) && !url.startsWith("data:")) {
            url = `${String(appDomain).replace(/\/+$/, "")}${url.startsWith("/") ? "" : "/"}${url}`;
          }
          const kind = String(d.kind ?? d.mediaType ?? "image").toLowerCase();
          if (url && ["image", "video", "audio"].includes(kind)) {
            const r = await sendAttachment(auth, igsid, kind, url);
            await logToLaravel(appDomain, { accountId, igsid, workspaceId, direction: "out", body: `[${kind}]`, source: "flow", mid: r?.message_id || null });
          } else if (url) {
            // Instagram has no document type — send the link as text rather
            // than dropping the node silently.
            const r = await sendText(auth, igsid, url);
            await logToLaravel(appDomain, { accountId, igsid, workspaceId, direction: "out", body: url, source: "flow", mid: r?.message_id || null });
          }
          const cap = subst(d.caption, ctx.vars).trim();
          if (cap) {
            const r = await sendText(auth, igsid, cap);
            await logToLaravel(appDomain, { accountId, igsid, workspaceId, direction: "out", body: cap, source: "flow", mid: r?.message_id || null });
          }
          break;
        }

        case "buttons": {
          const body = subst(d.prompt ?? d.text, ctx.vars);
          const opts = chatOptionsToQuickReplies(d);
          // ≤3 options → persistent generic-template buttons (reliably visible on
          // Instagram). >3 → fall back to quick-reply chips (generic caps at 3).
          const mode = (opts.length > 0 && opts.length <= 3) ? "generic-buttons" : "quick-replies";
          const r = mode === "generic-buttons"
            ? await sendGenericButtons(auth, igsid, body, opts)
            : await sendQuickReplies(auth, igsid, body, opts);
          console.log(`[IG-FLOW-NODE] buttons node=${node.id} mode=${mode} opts=${opts.length} resp=${JSON.stringify(r).slice(0, 200)}`);
          // Mirror the buttons into the inbox so the operator sees the same
          // tappable card, not just "What next?" as plain text.
          await logToLaravel(appDomain, { accountId, igsid, workspaceId, direction: "out", body, source: "flow", mid: r?.message_id || null, buttons: opts.map((o) => ({ title: String(o.title ?? o) })) });
          park(ctx, node.id);
          return; // wait for the tap
        }

        case "ask": {
          const q = subst(d.prompt ?? d.question ?? d.text, ctx.vars).trim();
          if (q) {
            const r = await sendText(auth, igsid, q);
            await logToLaravel(appDomain, { accountId, igsid, workspaceId, direction: "out", body: q, source: "flow", mid: r?.message_id || null });
          }
          park(ctx, node.id);
          return; // wait for the answer
        }

        case "delay": {
          // THE POINT OF THIS MODULE. Node is long-lived, so a wait is just a
          // timer — no DB parking, no sweep, no dependence on later traffic.
          const ms = delayMsOf(d);
          if (ms > 0) {
            console.log(`[IG-FLOW-NODE] delay node=${node.id} ${ms}ms flow=${flowId}`);
            await sleep(ms);
          }
          break;
        }

        case "condition":
          port = evalCondition(d, ctx.vars) ? "yes" : "no";
          break;

        case "webhook": {
          // Laravel owns this: it already has the SSRF guard (scheme + public-IP
          // check) that must apply to an operator-supplied URL.
          const out = await askLaravel(appDomain, {
            action: "webhook", node: d, vars: ctx.vars, workspaceId,
          });
          if (out?.vars) Object.assign(ctx.vars, out.vars);
          break;
        }

        // ---- nodes whose logic lives in Laravel (AI keys, catalog, CRM) ----
        case "ai":
        case "ig_ai": {
          const out = await askLaravel(appDomain, {
            action: "ai", node: d, vars: ctx.vars, workspaceId, accountId, igsid,
          });
          const reply = String(out?.reply || "");
          if (reply) {
            const r = await sendText(auth, igsid, reply);
            await logToLaravel(appDomain, { accountId, igsid, workspaceId, direction: "out", body: reply, source: "ai", mid: r?.message_id || null });
          }
          const saveKey = String(d.save || "").trim();
          if (saveKey) ctx.vars[saveKey] = reply;
          break;
        }

        case "ig_gallery":
        case "ig_products":
        case "ig_lead":
        case "ig_reply_comment": {
          // Catalog carousel / lead+deal creation / public comment reply all
          // need DB access — hand back to Laravel, which already implements
          // each one and logs its own outbound message.
          const out = await askLaravel(appDomain, {
            action: type, node: d, vars: ctx.vars, workspaceId, accountId, igsid,
            commentId: ctx.vars.comment_id || "",
          });
          if (out?.vars) Object.assign(ctx.vars, out.vars);
          break;
        }

        case "ig_send_dm": {   // legacy node, still runs on older flows
          const body = subst(d.text, ctx.vars);
          if (body.trim() !== "") {
            const r = await sendText(auth, igsid, body);
            await logToLaravel(appDomain, { accountId, igsid, workspaceId, direction: "out", body, source: "flow", mid: r?.message_id || null });
          }
          break;
        }

        case "ig_quick": {     // legacy node
          const body = subst(d.text, ctx.vars);
          const r = await sendQuickReplies(auth, igsid, body, (d.options || []));
          await logToLaravel(appDomain, { accountId, igsid, workspaceId, direction: "out", body, source: "flow", mid: r?.message_id || null });
          park(ctx, node.id);
          return;
        }

        case "ig_ask": {       // legacy node
          const q = subst(d.question, ctx.vars).trim();
          if (q) {
            const r = await sendText(auth, igsid, q);
            await logToLaravel(appDomain, { accountId, igsid, workspaceId, direction: "out", body: q, source: "flow", mid: r?.message_id || null });
          }
          park(ctx, node.id);
          return;
        }

        case "ig_buttons": {
          const body = subst(d.text, ctx.vars);
          const btns = d.buttons || [];
          const r = await sendButtons(auth, igsid, body, btns);
          await logToLaravel(appDomain, { accountId, igsid, workspaceId, direction: "out", body, source: "flow", mid: r?.message_id || null, buttons: btns.map((b) => ({ title: String(b.title || ""), url: String(b.url || "") })) });
          park(ctx, node.id);
          return;
        }

        case "end":
          clearSession(accountId, igsid);
          console.log(`[IG-FLOW-NODE] end flow=${flowId} igsid=${igsid}`);
          return;

        default:
          // A node this engine doesn't implement must be LOUD, never a silent
          // skip — silent skips are exactly how the old PHP runner hid bugs.
          console.warn(`[IG-FLOW-NODE] node type "${type}" has no executor — skipped (flow=${flowId} node=${node.id})`);
      }
    } catch (e) {
      console.error(`[IG-FLOW-NODE] node ${node.id} (${type}) failed: ${e?.message}`);
      // Keep walking: one bad node shouldn't strand the customer mid-conversation.
    }

    current = nextNode(flow, node.id, port);
  }

  if (guard >= 100) console.warn(`[IG-FLOW-NODE] walk hit the 100-node guard — possible loop (flow=${flowId})`);
  clearSession(accountId, igsid);
}

// ---------------------------------------------------------------------------
// Session state — in memory, like Baileys' activeFlowSessions.
// ---------------------------------------------------------------------------
function park(ctx, nodeId) {
  const key = sessionKeyFor(ctx.accountId, ctx.igsid);
  IG_SESSIONS.set(key, {
    accountId: ctx.accountId, igsid: ctx.igsid, workspaceId: ctx.workspaceId,
    flowId: ctx.flowId, flow: ctx.flow, auth: ctx.auth, appDomain: ctx.appDomain,
    nodeId: String(nodeId), vars: ctx.vars, parkedAt: Date.now(),
  });
  console.log(`[IG-FLOW-NODE] parked at node=${nodeId} key=${key}`);
}

function clearSession(accountId, igsid) {
  IG_SESSIONS.delete(sessionKeyFor(accountId, igsid));
}

export const hasSession = (accountId, igsid) => IG_SESSIONS.has(sessionKeyFor(accountId, igsid));

/** Drop sessions parked longer than `maxAgeMs` (default 24h — Meta's window). */
export function pruneSessions(maxAgeMs = 86_400_000) {
  const cutoff = Date.now() - maxAgeMs;
  let n = 0;
  for (const [k, s] of IG_SESSIONS) if (s.parkedAt < cutoff) { IG_SESSIONS.delete(k); n++; }
  return n;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------
/** Fresh run from the Trigger node. Fire-and-forget — never await in a webhook. */
export async function runFlow({ auth, flow, igsid, text, commentId, flowId, accountId, workspaceId, appDomain, vars }) {
  clearSession(accountId, igsid);
  const start = entryNode(flow);
  if (!start) { console.warn(`[IG-FLOW-NODE] flow ${flowId} has no trigger node`); return false; }

  const ctx = {
    auth, flow, igsid, flowId, accountId, workspaceId, appDomain,
    vars: { text: String(text || ""), igsid: String(igsid), comment_id: String(commentId || ""), ...(vars || {}) },
  };
  console.log(`[IG-FLOW-NODE] START flow=${flowId} account=${accountId} igsid=${igsid}`);
  await walk(ctx, nextNode(flow, start.id, "out"));
  return true;
}

/**
 * Resume a parked flow from the customer's reply.
 * @returns true if a session was found and consumed.
 */
export async function resumeFlow({ accountId, igsid, text }) {
  const key = sessionKeyFor(accountId, igsid);
  const sess = IG_SESSIONS.get(key);
  if (!sess) { console.log(`[IG-RESUME] no session key=${key}`); return false; }

  const nodes = indexNodes(sess.flow);
  const parked = nodes.get(String(sess.nodeId));
  if (!parked) { console.warn(`[IG-RESUME] parked node "${sess.nodeId}" missing — dropping session`); IG_SESSIONS.delete(key); return false; }

  const d = parked.data || {};
  const type = String(parked.type || "");
  const t = String(text || "").toLowerCase().trim();
  let port = "out";

  console.log(`[IG-RESUME] key=${key} parkedNode=${sess.nodeId} type=${type} reply="${t.slice(0, 40)}"`);

  // Ask nodes: the reply IS the answer.
  if (type === "ask" || type === "ig_ask") {
    const saveKey = String(d.var || d.save || "").trim();
    if (saveKey) sess.vars[saveKey] = String(text || "");
  }

  // Expected-answer branching on the shared `ask` node (p0..pN + else).
  if (type === "ask") {
    const expected = (d.options || []).map((o) => String(o).trim()).filter(Boolean);
    if (expected.length) {
      port = "else";
      for (let i = 0; i < expected.length; i++) {
        if (t === expected[i].toLowerCase()) { port = `p${i}`; break; }
      }
    }
  }

  // Quick-reply / button taps arrive as the PAYLOAD, not the visible title —
  // match payload first, then fall back to a typed-out label.
  if (type === "buttons" || type === "ig_quick" || type === "ig_buttons") {
    const opts = type === "buttons"
      ? chatOptionsToQuickReplies(d)
      : (type === "ig_quick" ? (d.options || []) : (d.buttons || []))
        .map((o, i) => ({ title: String(o.title || ""), payload: String(o.payload || `OPT_${i}`) }));
    let idx = null;
    for (let i = 0; i < opts.length; i++) {
      if (t === String(opts[i].payload).toLowerCase() || t === String(opts[i].title).toLowerCase().trim()) { idx = i; break; }
    }
    console.log(`[IG-RESUME] button match reply="${t}" opts=${JSON.stringify(opts)} → idx=${idx}`);
    if (idx === null) return false;   // not a tap — let normal handling take it
    port = `p${idx}`;
    const saveKey = String(d.var || "").trim();
    if (saveKey) sess.vars[saveKey] = String(text || "");
  }

  IG_SESSIONS.delete(key);   // consumed
  sess.vars.text = String(text || "");
  console.log(`[IG-FLOW-NODE] RESUME flow=${sess.flowId} from=${sess.nodeId} port=${port}`);
  await walk({ ...sess, vars: sess.vars }, nextNode(sess.flow, sess.nodeId, port));
  return true;
}

export default { runFlow, resumeFlow, hasSession, pruneSessions, delayMsOf };
