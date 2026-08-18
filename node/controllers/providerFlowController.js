// controllers/providerFlowController.js
// =====================================
// WABA + Twilio inbound → flow engine.
//
// Baileys drives flows from its socket message handler (BaileysClientManager).
// WABA / Twilio messages arrive at Laravel over Meta/Twilio webhooks and never
// enter that handler, so before this endpoint they got routing rules + keyword
// auto-replies + AI — but NEVER started or resumed a flow. This gives them the
// SAME flow behaviour Baileys has:
//
//   1. RESUME — a node is waiting on this customer's reply  → handleFlowResponse
//   2. START  — the inbound keyword matches a flow trigger   → seed + executeFlowNode
//
// Every node runs with sock = null. That is the EXACT signal flowService uses to
// take its engine === 'waba' / 'twilio' send branches (resolveEngine(settings)),
// so the Cloud / Twilio APIs do the sending. The Baileys path is untouched —
// this file is purely additive and never imported by the socket handler.
import axios from "axios";
import moment from "moment";
import { executeFlowNode, handleFlowResponse } from "../services/flowService.js";
import { fetchWorkspaceAttributes, mergeFlowVariables } from "../services/campaignService.js";

const nodeHeaders = () => ({ "X-Node-Token": process.env.NODE_WEBHOOK_TOKEN || "", Accept: "application/json" });

// Build a Baileys-shaped message so flowService's extractReplyFromMessage()
// reads the customer's reply uniformly. `text` = typed text; `replyId` = the id
// of a tapped interactive button / list row (WABA/Twilio interactive replies).
function buildMessage(text, replyId, pushName) {
  const msg = { pushName: String(pushName || "") };
  if (replyId) {
    msg.message = {
      buttonsResponseMessage: {
        selectedButtonId: String(replyId),
        selectedDisplayText: String(text || ""),
      },
    };
  } else {
    msg.message = { conversation: String(text || "") };
  }
  return msg;
}

// Drop an idle session after its timeout — mirrors BaileysClientManager's
// setFlowTimeout effect without depending on the manager instance. Only deletes
// if the SAME session is still parked (no newer activity replaced it).
function clearSessionLater(appLocals, sessionKey, seconds) {
  const sess = appLocals.activeFlowSessions[sessionKey];
  if (!sess) return;
  if (sess.timeoutTimer) clearTimeout(sess.timeoutTimer);
  const secs = Math.min(Math.max(Number(seconds) || 600, 30), 3600);
  sess.timeoutTimer = setTimeout(() => {
    const s = appLocals.activeFlowSessions[sessionKey];
    if (s && s.sessionId === sess.sessionId && !s.waitingForInput) {
      delete appLocals.activeFlowSessions[sessionKey];
    }
  }, secs * 1000);
}

/**
 * POST /api/flow/provider-inbound
 * Body: { deviceNumber, customerPhone, workspaceId?, provider?, text, replyId?, pushName?, flowId? }
 *   - deviceNumber  = OUR WABA/Twilio number that received the message (the flow "sender")
 *   - customerPhone = the customer who wrote in (the flow "target")
 *   - flowId        = optional: a routing-rule-triggered flow id (skips keyword lookup)
 * Auth: X-Node-Token shared secret.
 */
export const providerInbound = async (req, res, app) => {
  const expected = process.env.NODE_WEBHOOK_TOKEN || "";
  if (!expected || (req.headers["x-node-token"] || "") !== expected) {
    return res.status(401).send({ ok: false, error: "unauthorized" });
  }

  const appLocals     = app.locals;
  const deviceNumber  = String(req.body?.deviceNumber || "").replace(/\D+/g, "");
  const customerPhone = String(req.body?.customerPhone || "").replace(/\D+/g, "");
  const text          = String(req.body?.text || "");
  const replyId       = req.body?.replyId ? String(req.body.replyId) : "";
  const pushName      = String(req.body?.pushName || "");
  const forcedFlowId  = req.body?.flowId ? String(req.body.flowId) : "";
  const provider      = String(req.body?.provider || "waba");
  if (!deviceNumber || !customerPhone) {
    return res.status(400).send({ ok: false, error: "deviceNumber and customerPhone required" });
  }

  const sessionKey = `${deviceNumber}_${customerPhone}`;
  const active = appLocals.activeFlowSessions[sessionKey];
  // An interactive TAP (button / list row) carries a replyId; typed free text
  // does not. A tap is always the current node's ANSWER, so it can never be a
  // keyword; only typed text can restart a flow. Used below both to resume and
  // to detect a mid-flow keyword restart.
  const isTap = replyId !== "";
  const typed = text.trim();
  // Only a real customer action (typed text or an interactive tap) may resume a
  // parked flow. An empty payload (Meta's unsupported/system/reaction/read-state
  // events) must NOT resume — otherwise it re-invokes the parked AI node with
  // empty input and it auto-replies ("Hi! How can I help you today?") with no
  // real activity. START already needs text (keyword lookup can't match empty).
  const hasContent = (typed !== "") || isTap;
  let isResume = !!(active && active.status === "active" && active.waitingForInput) && hasContent;

  console.log(`[PROVIDER-FLOW] IN device=${deviceNumber} customer=${customerPhone} provider=${provider} text="${text.substring(0, 50)}" isResume=${isResume} forcedFlowId=${forcedFlowId || "-"} hasActiveSession=${!!active} key=${sessionKey}`);
  if (deviceNumber === customerPhone) {
    console.warn(`[PROVIDER-FLOW] WARNING device === customer (${deviceNumber}) — you're messaging from a number that is ALSO a connected device (self-send). Test from a DIFFERENT phone.`);
  }

  // Decide SYNCHRONOUSLY whether this message is consumed by a flow (resume an
  // active session, or start one on a keyword match). Laravel awaits `consumed`
  // and — when true — skips its keyword-auto-reply + AI agent so the customer
  // never gets a double reply (this mirrors Baileys' `continue` after a flow).
  let flowId = forcedFlowId;
  let timeoutSeconds = 600;
  let cooldownSeconds = 0;

  // Resolve a flow-trigger keyword from the TYPED text. We run this even while a
  // session is active (isResume) so a customer who RE-TYPES the trigger keyword
  // mid-flow RESTARTS the flow instead of having the keyword swallowed as the
  // waiting node's answer — the same "keyword always (re)starts the flow" rule
  // Baileys enforces (client: "flow stuck mid-way, the keyword won't restart
  // it"). Skipped on a tap (node answer) and when a routing rule already forced
  // a flow id.
  if (!forcedFlowId && !isTap && typed) {
    // Fast path — same trigger text as the active session → its known flowId,
    // no HTTP, no dependency on the rule still matching.
    if (active && active.triggerKeyword && typed.toLowerCase() === active.triggerKeyword && active.flowId) {
      flowId = String(active.flowId);
      timeoutSeconds = active.timeoutSeconds || 600;
      cooldownSeconds = 0;
      console.log(`[PROVIDER-FLOW] mid-flow re-typed trigger keyword → restarting flow ${flowId} key=${sessionKey}`);
    } else {
      try {
        const kwUrl = `${appLocals.appDomainName}/api/keyword-replies?keyword=${encodeURIComponent(typed)}&mobile=${customerPhone}&phone=${deviceNumber}`;
        console.log(`[PROVIDER-FLOW] keyword lookup → ${kwUrl}`);
        const r = await axios.get(kwUrl, { headers: nodeHeaders(), timeout: 15000 });
        // DEEP DIAGNOSTIC — the RAW response is what tells us if Laravel returned
        // the flow (reply_type:'flow') or something else (notallow / plan_limit).
        console.log(`[PROVIDER-FLOW] keyword RAW status=${r?.status} data=${JSON.stringify(r?.data)}`);
        // /api/keyword-replies returns an ARRAY: [{ reply_type, flow_id, ... }]
        // (the Baileys checkKeywordReply consumes it via [0]). Reading r.data
        // directly treated the array as an object, so kw.reply_type was undefined
        // and a matched flow was silently ignored (consumed:false even though
        // Laravel matched the rule). Unwrap the first element.
        const kw = Array.isArray(r?.data) ? (r.data[0] || null) : (r?.data || null);
        console.log(`[PROVIDER-FLOW] keyword PARSED reply_type=${kw?.reply_type} flow_id=${kw?.flow_id} reply=${kw?.reply} reason=${kw?.reason || "-"}`);
        if (kw && kw.reply_type === "flow" && kw.flow_id) {
          flowId = String(kw.flow_id);
          timeoutSeconds = kw.timeout || 600;
          cooldownSeconds = kw.cooldown ?? 0;
        }
      } catch (e) {
        console.warn(`[PROVIDER-FLOW] keyword lookup FAILED: status=${e?.response?.status || ""} ${e?.message} body=${JSON.stringify(e?.response?.data)}`);
      }
    }
  }

  // A typed flow-trigger keyword ALWAYS (re)starts that flow — even mid-flow —
  // so it wins over resuming a parked node: drop the resume so the START branch
  // below seeds a fresh session (and clear the old session's idle timer).
  if (flowId && !forcedFlowId && isResume) {
    console.log(`[PROVIDER-FLOW] typed keyword overrides resume → restarting flow ${flowId} key=${sessionKey}`);
    if (active && active.timeoutTimer) clearTimeout(active.timeoutTimer);
    isResume = false;
  }

  const consumed = isResume || !!flowId;
  console.log(`[PROVIDER-FLOW] DECISION consumed=${consumed} isResume=${isResume} flowId=${flowId || "-"} key=${sessionKey}`);
  res.status(202).send({ ok: true, consumed });
  if (!consumed) {
    console.log(`[PROVIDER-FLOW] not consumed — no active session / flow trigger for "${text.substring(0, 40)}" key=${sessionKey}`);
    return;
  }

  // Run the flow fire-and-forget — a multi-node flow with delays must never
  // block the webhook round-trip (we already responded above).
  (async () => {
    try {
      // 1) RESUME — a node is parked waiting on this customer's reply.
      if (isResume) {
        if (active.timeoutTimer) clearTimeout(active.timeoutTimer);
        const message = buildMessage(text, replyId, pushName);
        console.log(`[PROVIDER-FLOW] resume key=${sessionKey} provider=${provider} nextNodeType=${active.waitingForInput?.nextNodeType}`);
        try {
          await handleFlowResponse(message, active, customerPhone, deviceNumber, null, appLocals);
        } catch (e) {
          console.error(`[PROVIDER-FLOW] resume failed key=${sessionKey}: ${e?.message}`);
        }
        clearSessionLater(appLocals, sessionKey, active.timeoutSeconds || 600);
        return;
      }

      // 2) START — fetch the matched flow definition (same endpoint + token as
      //    the Baileys path) and run its first node.
      let flowData = null;
      try {
        const fr = await axios.get(`${appLocals.appDomainName}/api/flows/${flowId}`, { headers: nodeHeaders(), timeout: 20000 });
        if (fr?.data?.success) flowData = fr.data.data.flow_data;
      } catch (e) {
        console.error(`[PROVIDER-FLOW] /api/flows/${flowId} failed: ${e?.message}`);
      }
      if (!flowData || !Array.isArray(flowData.flowNodes) || flowData.flowNodes.length === 0) {
        console.warn(`[PROVIDER-FLOW] flow ${flowId} missing/empty — abort`);
        return;
      }

      const workspaceAttrs = await fetchWorkspaceAttributes(appLocals.appDomainName, flowData?.workspace_id);
      appLocals.activeFlowSessions[sessionKey] = {
        sessionId: `${sessionKey}_${Date.now()}`,
        flowId,
        triggerKeyword: String(text || "").toLowerCase().trim(),
        flowData,
        currentNodeId: null,
        userVariables: mergeFlowVariables(workspaceAttrs, {
          user_message: text,
          name: pushName,
          first_name: pushName.split(/\s+/)[0] || "",
          pushName,
          phone: customerPhone,
        }),
        messageHistory: [{ type: "received", message: text, timestamp: moment().format() }],
        status: "active",
        startedAt: moment().format(),
        phoneNumber: deviceNumber,
        timeoutSeconds,
        cooldownSeconds,
        timeoutTimer: null,
        provider, // 'waba' | 'twilio' — flowService resolves the real engine from settings
      };

      const startNode = flowData.flowNodes[0];
      console.log(`[PROVIDER-FLOW] START flow=${flowId} provider=${provider} device=${deviceNumber} customer=${customerPhone} node=${startNode.id}`);
      try {
        await executeFlowNode(startNode, customerPhone, deviceNumber, null, appLocals, sessionKey);
      } catch (e) {
        console.error(`[PROVIDER-FLOW] start exec failed flow=${flowId}: ${e?.message}`);
      }
      clearSessionLater(appLocals, sessionKey, timeoutSeconds);
    } catch (e) {
      console.error(`[PROVIDER-FLOW] handler crashed key=${sessionKey}: ${e?.message}`);
    }
  })();
};
