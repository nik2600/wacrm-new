// controllers/instagramFlowController.js
// ======================================
// Instagram inbound → Node flow engine.
//
// Same shape as providerFlowController.js (which did this for WABA/Twilio):
// Laravel receives Meta's webhook, verifies the signature, then hands the
// message here. We decide SYNCHRONOUSLY whether a flow consumes it, answer
// immediately, and run the flow detached.
//
// The synchronous `consumed` answer matters: Laravel skips its keyword
// auto-reply and AI agent when it is true, so the customer never gets a
// double reply — exactly how the Baileys and WABA paths behave.
//
// PURELY ADDITIVE — nothing here is imported by flowService.js or the Baileys
// manager. The WhatsApp path is untouched.
import { runFlow, resumeFlow, hasSession, pruneSessions } from "../services/instagramFlowService.js";

/**
 * POST /api/instagram-flow/inbound
 *
 * Body:
 *   accountId    (int)    instagram_accounts.id
 *   workspaceId  (int)
 *   igsid        (string) the customer's Instagram-scoped id
 *   text         (string) message text, or the payload of a quick-reply/postback tap
 *   commentId    (string, optional) set when the flow was triggered by a comment
 *   auth         ({base, ig, token}) Graph creds — same shape igScheduler receives
 *   flow         (object, optional) flow_data; required to START, unused to RESUME
 *   flowId       (int, optional)
 *   vars         (object, optional) extra starting variables
 *
 * Auth: X-Node-Token shared secret.
 *
 * Response: { ok, consumed, mode }
 *   consumed=true  → a flow took this message; Laravel must not auto-reply
 *   mode           → 'resume' | 'start' | 'none'
 */
export const instagramInbound = async (req, res) => {
  const expected = process.env.NODE_WEBHOOK_TOKEN || "";
  if (!expected || (req.headers["x-node-token"] || "") !== expected) {
    return res.status(401).send({ ok: false, error: "unauthorized" });
  }

  const accountId   = Number(req.body?.accountId || 0);
  const workspaceId = Number(req.body?.workspaceId || 0);
  const igsid       = String(req.body?.igsid || "");
  const text        = String(req.body?.text || "");
  const commentId   = String(req.body?.commentId || "");
  const auth        = req.body?.auth || null;
  const flow        = req.body?.flow || null;
  const flowId      = req.body?.flowId ? String(req.body.flowId) : "";
  const vars        = req.body?.vars || {};
  const appDomain   = String(req.body?.appDomain || process.env.APP_URL || "").replace(/\/+$/, "");

  if (!accountId || !igsid) {
    return res.status(400).send({ ok: false, error: "accountId and igsid required" });
  }

  // Only a real customer action may resume. Empty payloads (reactions, read
  // receipts, system events) must not re-enter a parked node — that is how the
  // WABA path once re-fired a parked AI node with empty input.
  const hasContent = text.trim() !== "";
  const isResume   = hasSession(accountId, igsid) && hasContent;
  const canStart   = !!(flow && (flow.flowNodes || flow.nodes));

  console.log(`[IG-FLOW-NODE] IN account=${accountId} igsid=${igsid} text="${text.slice(0, 50)}" isResume=${isResume} canStart=${canStart}`);

  if (!isResume && !canStart) {
    return res.status(200).send({ ok: true, consumed: false, mode: "none" });
  }

  // Answer BEFORE running — a flow with a 5-minute Wait must never hold the
  // request open. This is the whole reason Instagram flows moved into Node.
  res.status(202).send({ ok: true, consumed: true, mode: isResume ? "resume" : "start" });

  // Opportunistic housekeeping: drop sessions parked past Meta's 24h window.
  try { pruneSessions(); } catch (_) {}

  (async () => {
    try {
      if (isResume) {
        const done = await resumeFlow({ accountId, igsid, text });
        // resumeFlow returns false when the reply didn't match any branch of
        // the parked node (e.g. free text while we expected a button tap). The
        // session is left intact so the customer can still tap.
        if (!done) console.log(`[IG-FLOW-NODE] resume declined (no matching branch) account=${accountId} igsid=${igsid}`);
        return;
      }
      await runFlow({ auth, flow, igsid, text, commentId, flowId, accountId, workspaceId, appDomain, vars });
    } catch (e) {
      console.error(`[IG-FLOW-NODE] handler crashed account=${accountId} igsid=${igsid}: ${e?.message}`);
    }
  })();
};

/**
 * GET /api/instagram-flow/health
 * Lets Laravel check whether the Node engine is reachable before handing off,
 * so it can fall back to the in-process PHP runner instead of dropping the
 * flow when Node is down.
 */
export const instagramFlowHealth = (req, res) => {
  const expected = process.env.NODE_WEBHOOK_TOKEN || "";
  if (!expected || (req.headers["x-node-token"] || "") !== expected) {
    return res.status(401).send({ ok: false });
  }
  return res.status(200).send({ ok: true, engine: "node", service: "instagramFlowService" });
};

export default { instagramInbound, instagramFlowHealth };
