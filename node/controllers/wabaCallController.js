// controllers/wabaCallController.js
// ==================================
// WABA Calling — Live AI pickup
//
// Laravel calls /api/waba-call/answer when an incoming WABA call lands
// AND a voice assistant is configured. This module:
//
//   1. Validates the X-Node-Token shared secret.
//   2. Hands off to wabaCallBridge.openSession() which (in Phase 2)
//      will negotiate WebRTC SDP with Meta + run the STT → LLM → TTS
//      audio loop.
//
// Phase-1 (this session): the route + auth + session bookkeeping ship.
// The real-time audio loop is documented in
//   D:\Vault\kapil\WaDesk - WABA Calls AI Pickup.md
// and will need the `wrtc` npm dep + Meta's ICE servers configured.
// ==================================

import { openSession, closeSession } from "../services/wabaCallBridge.js";
import { timingSafeEqualStr } from "../utils/helpers.js";

function authed(req) {
  const expected = process.env.NODE_WEBHOOK_TOKEN || "";
  // Accept the shared secret from the header OR the request body — Laravel
  // sends it both ways (X-Node-Token header + `node_token` in the body), so a
  // proxy that strips custom headers can't lock the bridge out.
  // Constant-time compare (finding #50) — mirrors Laravel's hash_equals().
  const token = req.headers["x-node-token"] || (req.body && req.body.node_token) || "";
  return expected !== "" && timingSafeEqualStr(token, expected);
}

/**
 * POST /api/waba-call/answer
 * Body: {
 *   wa_call_id, meta_call_id, workspace_id, assistant_id,
 *   caller_phone, callee_phone, sdp_offer
 * }
 *
 * Returns 202 immediately (audio negotiation is async). Laravel doesn't
 * block waiting for us — Meta has its own 10s deadline on the connect
 * event which the existing webhook handler already meets.
 */
export const answer = async (req, res, app) => {
  if (!authed(req)) {
    console.warn("[WABA-CALL][trace] /answer UNAUTHORIZED — X-Node-Token/node_token mismatch. Check node_webhook_token matches between Laravel + Node .env.");
    return res.status(401).send({ ok: false, error: "unauthorized" });
  }
  const b = req.body || {};
  console.log("[WABA-CALL][trace] /answer received", {
    wa_call_id: b.wa_call_id, meta_call_id: b.meta_call_id, workspace_id: b.workspace_id,
    assistant_id: b.assistant_id, has_sdp_offer: !!b.sdp_offer,
    has_meta_token: !!b.meta_token, has_phone_id: !!b.phone_number_id,
  });
  const required = ["wa_call_id", "meta_call_id", "workspace_id", "assistant_id"];
  for (const k of required) {
    if (!b[k]) {
      console.warn(`[WABA-CALL][trace] /answer rejected — missing ${k}`);
      return res.status(400).send({ ok: false, error: "missing_" + k });
    }
  }

  // Kick off the bridge in the background — every WABA call gets its
  // own session keyed by meta_call_id. Idempotent: if a session is
  // already open for this id, openSession() returns the existing one.
  try {
    openSession(app, {
      waCallId:      b.wa_call_id,
      metaCallId:    b.meta_call_id,
      workspaceId:   b.workspace_id,
      assistantId:   b.assistant_id,
      callerPhone:   b.caller_phone || "",
      calleePhone:   b.callee_phone || "",
      sdpOffer:      b.sdp_offer || null,
      // Every value below comes from Laravel's DB (system_settings +
      // wa_provider_configs.credentials_json). No env fallback — the
      // admin manages everything at /admin/settings.
      metaToken:     b.meta_token || "",
      phoneNumberId: b.phone_number_id || "",
      graphVersion:  b.graph_version || "v23.0",
      nodeToken:     b.node_token || "",
    });
    return res.status(202).send({ ok: true, status: "bridging" });
  } catch (e) {
    console.error("[WABA-CALL] answer failed:", e?.message);
    return res.status(500).send({ ok: false, error: e?.message || "bridge_failed" });
  }
};

/**
 * POST /api/waba-call/terminate
 * Hard-close a session — used when Laravel's terminate webhook lands
 * before the bridge has noticed Meta hung up. Idempotent.
 */
export const terminate = async (req, res, app) => {
  if (!authed(req)) {
    return res.status(401).send({ ok: false, error: "unauthorized" });
  }
  const metaCallId = req.body?.meta_call_id;
  if (!metaCallId) return res.status(400).send({ ok: false, error: "missing_meta_call_id" });
  try {
    closeSession(app, metaCallId);
    return res.status(200).send({ ok: true });
  } catch (e) {
    console.error("[WABA-CALL] terminate failed:", e?.message);
    return res.status(500).send({ ok: false, error: e?.message || "close_failed" });
  }
};
