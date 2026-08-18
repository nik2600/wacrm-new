// controllers/flowController.js
// ==============================
// Flow Controllers
// ==============================

import axios from "axios";
import moment from "moment";
import { advanceFlowToPort, executeFlowNode, resumeGoogleForm } from "../services/flowService.js";
import { seedFlowUserVariables, fetchWorkspaceAttributes, mergeFlowVariables } from "../services/campaignService.js";

// Start flow
export const startFlow = async (req, res, app) => {
  const senderPhoneNumber = req.params.phoneNumber;
  const { flowId, targetPhoneNumber } = req.body;
  // Optional contact fields — callers (e.g. campaign flow launches) may pass
  // name/email/custom attributes in the body so flow nodes can personalize.
  // When absent we still seed the phone so {{phone}}/{{mobile}} resolve.
  const contactData = (req.body && typeof req.body.contact === "object" && req.body.contact)
    ? req.body.contact
    : {
        name:              req.body?.name,
        first_name:        req.body?.first_name,
        last_name:         req.body?.last_name,
        email:             req.body?.email,
        phone:             req.body?.phone || targetPhoneNumber,
        custom_attributes: req.body?.custom_attributes,
      };




  // A live Baileys socket is only expected for the Unofficial API engine.
  // Official numbers (WhatsApp Cloud / Twilio) have NO Baileys socket, so we
  // must NOT bail here — that was rejecting every WABA/Twilio flow launch with
  // "CLIENT NOT FOUND". Run with sock=null and let executeFlowNode resolve the
  // engine from the workspace's provider settings and send via WABA/Twilio
  // (the same path the inbound provider bridge already uses). Baileys with a
  // live socket is completely unchanged.
  const sock = app.locals.clients[senderPhoneNumber] || null;
  if (!sock) {
    console.log(`[FLOW-START] no Baileys socket for ${senderPhoneNumber} — running via provider engine (WhatsApp Cloud / Twilio), flow ${flowId}`);
  }
  if (!flowId || !targetPhoneNumber) {
    return res.status(400).send({ error: "flowId and targetPhoneNumber are required" });
  }

  // Respond IMMEDIATELY, then run the flow fire-and-forget. CRITICAL: starting
  // the flow requires Node to call BACK to Laravel (`/api/flows/:id` +
  // workspace attrs). When the caller is the PHP campaign loop running in
  // afterResponse on a single-worker server, that busy PHP process can't serve
  // the re-entrant callback → it deadlocks and the original POST times out
  // (cURL 28, "0 bytes received" after 15s). Acking first frees PHP so the
  // callback can be served. It also stops a multi-node flow with delays from
  // ever blocking the HTTP caller.
  res.status(202).send({ message: "FLOW START ACCEPTED", flowId, target: targetPhoneNumber });

  (async () => {
    try {
      console.log(`[FLOW-START] begin flow=${flowId} target=${targetPhoneNumber} sender=${senderPhoneNumber} campaign=${req.body?.campaignId ?? '-'}`);
      const flowResponse = await axios.get(
        `${req.app.locals.appDomainName}/api/flows/${flowId}`,
        {
          timeout: 20000,
          // /api/flows/{id} requires the Node token — without it Laravel returns
          // 401, so `flowResponse.data.success` is false and the flow silently
          // "not found" → a flow CAMPAIGN sends nothing. Same fix the sub-flow
          // loader got (flowService.js). Custom/template campaigns don't hit
          // this endpoint, which is why only the flow option was failing.
          headers: { 'X-Node-Token': process.env.NODE_WEBHOOK_TOKEN || '' },
        }
      );
      if (!flowResponse.data?.success) {
        console.warn(`[FLOW-START] flow ${flowId} not found / inactive from Laravel`);
        return;
      }
      const flowData = flowResponse.data.data.flow_data;
      if (!flowData || !Array.isArray(flowData.flowNodes) || flowData.flowNodes.length === 0) {
        console.warn(`[FLOW-START] flow ${flowId} has no nodes — nothing to run`);
        return;
      }
      const sessionKey = `${senderPhoneNumber}_${targetPhoneNumber}`;
      const sessionId = `${targetPhoneNumber}_${Date.now()}`;
      // Seed contact attributes so {{name}}/{{phone}}/{{email}}/custom attrs
      // resolve in flow nodes from the very first node. Workspace attributes
      // ({{promo_key}}, default {{order_id}}, …) merge UNDER the contact attrs
      // (contact wins on collision). workspace_id rides on flow_data.
      const workspaceAttrs = await fetchWorkspaceAttributes(
        req.app.locals.appDomainName,
        flowData?.workspace_id
      );
      app.locals.activeFlowSessions[sessionKey] = {
        sessionId: sessionId,
        flowId: flowId,
        flowData: flowData,
        currentNodeId: null,
        // Order/event variables (e.g. {{invoice_url}}, {{order_number}}) passed
        // by the launcher win over workspace + contact seeds so an order-placed
        // flow can send the invoice for THIS order.
        userVariables: Object.assign(
          mergeFlowVariables(
            workspaceAttrs,
            seedFlowUserVariables(contactData, targetPhoneNumber)
          ),
          (req.body && typeof req.body.variables === "object" && req.body.variables) ? req.body.variables : {}
        ),
        messageHistory: [],
        status: "active",
        startedAt: moment().format(),
      };
      const startNode = flowData.flowNodes[0];
      await executeFlowNode(
        startNode,
        targetPhoneNumber,
        senderPhoneNumber,
        sock,
        app.locals,
        sessionKey
      );
      console.log(`[FLOW-START] first node executed flow=${flowId} target=${targetPhoneNumber} session=${sessionId}`);
    } catch (error) {
      console.error(`[FLOW-START] ERROR flow=${flowId} target=${targetPhoneNumber}: ${error?.message || error}`);
    }
  })();
};

/**
 * POST /api/flow/resume-by-phone/:workspaceId/:customerPhone
 * WABA inbound `type:order` messages don't carry biz_opaque_callback_data,
 * so Laravel can't compute a session key. Instead we scan activeFlowSessions
 * for one paused on a CommerceShop node where the target phone matches —
 * that's the customer who just placed the order — and advance through
 * port 1 (purchased).
 *
 * Auth: X-Node-Token shared secret.
 * Body: { port: 1|2, orderMeta?: {...} }
 */
export const resumeByPhone = async (req, res, app) => {
  const expected = process.env.NODE_WEBHOOK_TOKEN || "";
  const token    = req.headers["x-node-token"] || "";
  if (!expected || token !== expected) {
    return res.status(401).send({ ok: false, error: "unauthorized" });
  }

  const workspaceId = String(req.params.workspaceId || "");
  const phone = String(req.params.customerPhone || "").replace(/\D+/g, "");
  if (!workspaceId || !phone) {
    return res.status(400).send({ ok: false, error: "bad_params" });
  }

  // Scan sessions — keyed by `<sender>_<target>`. Match when the target
  // (customer) phone equals `phone` and the session is paused on a
  // CommerceShop node belonging to the right workspace. We do NOT
  // require linkSent=true; the WABA branch sets waitingForInput.isWaba
  // and never mints a Baileys link.
  const sessions = app.locals.activeFlowSessions || {};
  let matchedKey = null;
  for (const key of Object.keys(sessions)) {
    const sess = sessions[key];
    if (!sess || !sess.waitingForInput) continue;
    if (sess.waitingForInput.nextNodeType !== "CommerceShop") continue;
    if (String(sess.flowData?.workspace_id || "") !== workspaceId) continue;
    // sessionKey is `<sender>_<target>`. Strip non-digits from target
    // before comparing so a stored `+91…` matches a raw `91…`.
    const target = (key.split("_")[1] || "").replace(/\D+/g, "");
    if (target === phone) { matchedKey = key; break; }
  }
  if (!matchedKey) {
    // Customer ordered after the abandon timer fired, or never had a
    // commerce session. Idempotent so retries don't double-log.
    return res.status(200).send({ ok: true, noop: true, reason: "no_matching_session" });
  }

  // Hand off to the existing resume-port logic.
  req.params.sessionKey = matchedKey;
  return resumePort(req, res, app);
};

/**
 * POST /api/flow/resume-form/:sessionKey
 * Laravel's WaFormSubmissionService hits this when a customer submits
 * a WhatsApp Form. We stamp every answer into session.userVariables
 * under both `form_<id>` AND the bare field id so downstream merge
 * tags resolve, then advance through the `out` port.
 */
export const resumeForm = async (req, res, app) => {
  const expected = process.env.NODE_WEBHOOK_TOKEN || "";
  const token    = req.headers["x-node-token"] || "";
  if (!expected || token !== expected) {
    return res.status(401).send({ ok: false, error: "unauthorized" });
  }
  const sessionKey = req.params.sessionKey;
  const answers   = req.body?.form_answers || {};
  const sess = app.locals.activeFlowSessions?.[sessionKey];
  if (!sess) {
    return res.status(200).send({ ok: true, noop: true, reason: "session_not_found" });
  }
  const waiting = sess.waitingForInput;
  if (!waiting || waiting.nextNodeType !== "WaForm") {
    return res.status(200).send({ ok: true, noop: true, reason: "not_waiting_on_form" });
  }

  sess.userVariables = sess.userVariables || {};
  // Flatten a form answer to a readable string. Meta returns strings for
  // text/email fields but OBJECTS/ARRAYS for date pickers, dropdowns,
  // opt-ins and multi-selects — String(obj) => "[object Object]", which is
  // what was leaking into Google Sheets. Extract the human value instead.
  const flattenAnswer = (v) => {
    if (v == null) return "";
    if (Array.isArray(v)) return v.map(flattenAnswer).filter(s => s !== "").join(", ");
    if (typeof v === "object") {
      // common WhatsApp Flow shapes: {title}, {name}, {label}, {value}, {id}
      const pick = v.title ?? v.name ?? v.label ?? v.value ?? v.text ?? v.id;
      if (pick != null && typeof pick !== "object") return String(pick);
      try { return JSON.stringify(v); } catch (_) { return ""; }
    }
    return String(v);
  };
  // Debug: surface the exact keys + value types Meta sent so a bad mapping
  // (wrong {{field_id}} in the sheet node) is diagnosable from the logs.
  try {
    console.log("[FORM-ANSWERS]", sessionKey, Object.entries(answers).map(([k, v]) =>
      `${k}=${Array.isArray(v) ? "array" : typeof v}`).join(" "));
  } catch (_) { /* logging is best-effort */ }
  // Stamp under both the bare field id and a `form_` prefix so flows
  // can use {{name}} or {{form_name}} interchangeably.
  for (const [k, v] of Object.entries(answers)) {
    const safe = flattenAnswer(v);
    sess.userVariables[k] = safe;
    sess.userVariables["form_" + k] = safe;
  }

  const nodeId = waiting.nodeId;
  sess.waitingForInput = null;
  const [senderPhoneNumber, targetPhoneNumber] = sessionKey.split("_");
  const sock = app.locals.clients?.[senderPhoneNumber];

  try {
    // Form node advances through `out` (the only port it has).
    // moveToNextNode in flowService uses `<nodeId>_1` → 'out' is port 1.
    const { executeFlowNode } = await import("../services/flowService.js");
    const nextEdge = sess.flowData.flowEdges.find(e => e.sourceNodeId === `${nodeId}_1`);
    if (!nextEdge) {
      return res.status(200).send({ ok: true, ended: true });
    }
    const nextNodeId = String(nextEdge.targetNodeId).replace(/_\d+$/, "");
    const nextNode = sess.flowData.flowNodes.find(n => n.id === nextNodeId);
    if (!nextNode) return res.status(200).send({ ok: true, ended: true });
    await executeFlowNode(nextNode, targetPhoneNumber, senderPhoneNumber, sock, app.locals, sessionKey);
    return res.status(200).send({ ok: true, nodeId, advanced_to: nextNodeId });
  } catch (e) {
    console.error("[FlowResumeForm] failed:", e?.message);
    return res.status(500).send({ ok: false, error: e?.message || "advance_failed" });
  }
};

/**
 * POST /api/flow-resume
 * Laravel's GoogleFlowNodeController::formResponse fires this when a
 * Google Form submission lands. Body:
 *   { session_id, workspace_id, resume_port, save_variable, answers,
 *     response_id, respondent }
 *
 * Auth: X-Node-Token shared secret.
 */
export const resumeGoogleFormEndpoint = async (req, res, app) => {
  const expected = process.env.NODE_WEBHOOK_TOKEN || "";
  const token    = req.headers["x-node-token"] || "";
  if (!expected || token !== expected) {
    return res.status(401).send({ ok: false, error: "unauthorized" });
  }

  const sessionKey   = String(req.body?.session_id   || "");
  const saveVariable = String(req.body?.save_variable || "google_form");
  const answers      = req.body?.answers || {};
  if (!sessionKey) {
    return res.status(400).send({ ok: false, error: "missing_session_id" });
  }
  const sess = app.locals.activeFlowSessions?.[sessionKey];
  if (!sess) {
    return res.status(200).send({ ok: true, noop: true, reason: "session_not_found" });
  }

  // resumeGoogleForm reads the session from app.locals directly and
  // derives target/sender from the sessionKey itself — no extra mirror
  // state to keep in sync.
  const [senderPhoneNumber] = sessionKey.split("_");
  const sock = app.locals.clients?.[senderPhoneNumber];   // may be null on WABA-only workspaces (fine — executor checks useWaba)

  try {
    await resumeGoogleForm({
      sessionKey,
      saveVariable,
      answers,
      sock,
      appLocals: app.locals,
    });
    return res.status(200).send({ ok: true });
  } catch (e) {
    console.error("[FlowResumeGoogleForm] failed:", e?.message);
    return res.status(500).send({ ok: false, error: e?.message || "resume_failed" });
  }
};

/**
 * POST /api/flow/resume-port/:sessionKey
 * Laravel hits this when a webhook (WC/Shopify/WABA order.created) lands
 * for a session paused on a commerce node. We clear the wait, advance
 * the flow through `port` (1=purchased, 2=abandoned), and report.
 *
 * Auth: X-Node-Token shared secret.
 * Body: { port: 1|2, orderMeta?: {...} }
 */
export const resumePort = async (req, res, app) => {
  // Shared-secret check — same token Laravel uses on launchFlow.
  // Refuse outright when NODE_WEBHOOK_TOKEN env is unset, otherwise
  // "" === "" passes and the endpoint becomes anonymous-callable in
  // dev installs that forgot to set the env var.
  const expected = process.env.NODE_WEBHOOK_TOKEN || "";
  const token    = req.headers["x-node-token"] || "";
  if (!expected || token !== expected) {
    return res.status(401).send({ ok: false, error: "unauthorized" });
  }

  const sessionKey = req.params.sessionKey;
  const port = Number(req.body?.port || 1);
  const sess = app.locals.activeFlowSessions?.[sessionKey];
  if (!sess) {
    return res.status(404).send({ ok: false, error: "session_not_found" });
  }
  const waiting = sess.waitingForInput;
  if (!waiting || waiting.nextNodeType !== "CommerceShop") {
    // Session moved on already (e.g. abandoned timer fired). Idempotent
    // no-op so duplicate webhook deliveries don't double-advance.
    return res.status(200).send({ ok: true, noop: true, reason: "not_waiting_on_commerce" });
  }

  // Stash the order meta on session vars so downstream nodes (e.g.
  // "Send thank-you message {{order_id}}") can reference it. Written
  // to BOTH userVariables (canonical, where merge tags look) and vars
  // (back-compat with code that read from there during the commerce
  // build-out).
  if (req.body?.orderMeta && typeof req.body.orderMeta === "object") {
    const stash = {
      order_id:       String(req.body.orderMeta.id ?? ""),
      order_total:    String(req.body.orderMeta.total ?? ""),
      order_currency: String(req.body.orderMeta.currency ?? ""),
    };
    sess.userVariables = sess.userVariables || {};
    Object.assign(sess.userVariables, stash);
    sess.vars = sess.vars || {};
    Object.assign(sess.vars, stash);
  }

  const nodeId = waiting.nodeId;
  sess.waitingForInput = null;

  // Reconstruct sock + target from sessionKey (`<sender>_<target>`).
  const [senderPhoneNumber, targetPhoneNumber] = sessionKey.split("_");
  const sock = app.locals.clients?.[senderPhoneNumber];
  if (!sock) {
    // Customer's device is offline — we still advance the flow (the
    // next node might be a delay or a Laravel-driven hook), but
    // downstream sendMessage calls will fail gracefully.
    console.warn(`[FlowResumePort] sock missing for ${senderPhoneNumber}`);
  }

  try {
    await advanceFlowToPort(
      nodeId,
      port,
      sess.flowData,
      targetPhoneNumber,
      senderPhoneNumber,
      sock,
      app.locals,
      sessionKey,
    );
    return res.status(200).send({ ok: true, port, nodeId });
  } catch (e) {
    console.error("[FlowResumePort] advance failed:", e?.message);
    return res.status(500).send({ ok: false, error: e?.message || "advance_failed" });
  }
};