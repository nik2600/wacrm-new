// routes/index.js
// ==============================
// Route Initialization - WITH BROADCAST ROUTES
// ==============================

import { Router } from "express";
import { initializeClient, getClientStatus, terminateClient, checkConnection, getContacts, getPairingCode } from "../controllers/clientController.js";
import { sendMessage, sendMediaOnly, sendMediaMessage, sendLocation, sendProductCatalog, sendReaction, pinMessage, starMessage, sendContact, deleteMessage, editMessage, sendGroupMessage } from "../controllers/messageController.js";
import { scheduleMessage, getScheduledMessages, scheduleBulkMessage, scheduleRecurring, sendBulkImmediate, pauseSchedule, resumeSchedule, cancelSchedule, updateSchedule } from "../controllers/scheduleController.js";
import { resumeByPhone, resumeForm, resumeGoogleFormEndpoint, resumePort, startFlow } from "../controllers/flowController.js";
import { endFlowSessionsForPhone } from "../services/flowService.js";
import { providerInbound } from "../controllers/providerFlowController.js";
import { answer as wabaCallAnswer, terminate as wabaCallTerminate } from "../controllers/wabaCallController.js";
import { startRecording } from "../services/wabaCallBridge.js";
import { sendBroadcastImmediate, scheduleBroadcast, pauseBroadcast, resumeBroadcast, cancelBroadcast, cancelByBroadcastId, getBroadcasts, getBroadcastStatus } from "../controllers/broadcastController.js";
import {
  groupCreate, groupListAll, groupMetadata, groupParticipantsUpdate,
  groupUpdateSubject, groupUpdateDescription, groupUpdateSetting,
  groupLeave, groupInviteCode, groupRevokeInvite,
} from "../controllers/groupController.js";
import {
  sendCampaignImmediate,
  scheduleCampaign,
  getCampaignDetails,
  pauseCampaign,
  resumeCampaign,
  cancelCampaign
} from '../controllers/campaignController.js';
import { flushSettingsCache, timingSafeEqualStr } from "../utils/helpers.js";

export function initializeRoutes(app) {
  // ── SECURITY (finding #16): gate the ENTIRE /api surface ──────────────
  // Previously only the group routes + /api/cache-bust checked the
  // X-Node-Token shared secret. Every high-impact device route (send /
  // media / get-contacts / terminate / schedule / broadcast / campaign)
  // was reachable with NO auth, so anyone able to reach the Node port
  // off-host could send as a tenant, exfiltrate contacts, or disconnect a
  // device (broken access control). This app-level guard closes that.
  //
  // A request is authorized when EITHER it carries the correct X-Node-Token
  // (constant-time compare, finding #50) OR it originates from loopback —
  // the normal same-host Laravel->Node topology, where the internal send
  // dispatchers call http://localhost:<port> WITHOUT attaching the token.
  //
  // Mode via NODE_API_AUTH (default 'loopback'):
  //   'loopback' — token OR loopback peer. Secure vs the documented off-host
  //                threat while never breaking a co-located deployment.
  //   'token'    — require the token on every /api call (set this once the
  //                Laravel dispatchers are wired to send X-Node-Token, or
  //                when Node runs on a separate host from Laravel).
  //   'off'      — legacy: no gate (explicit escape hatch only).
  const apiAuthMode = String(process.env.NODE_API_AUTH || "loopback").toLowerCase().trim();
  const remoteIsLoopback = (req) => {
    let ip = String((req.socket && req.socket.remoteAddress) || req.ip || "");
    if (ip.startsWith("::ffff:")) ip = ip.slice(7);       // IPv4-mapped IPv6
    return ip === "::1" || ip === "127.0.0.1" || ip.startsWith("127.");
  };
  const apiTokenGuard = (req, res, next) => {
    if (req.method === "OPTIONS") return next();           // CORS preflight
    if (apiAuthMode === "off") return next();
    const expected = process.env.NODE_WEBHOOK_TOKEN || "";
    // Accept the secret from the header OR body node_token (some Laravel
    // bridge calls send it in the body so header-stripping proxies can't
    // lock the bridge out — mirrors wabaCallController).
    const got = req.headers["x-node-token"] || (req.body && req.body.node_token) || "";
    if (expected && timingSafeEqualStr(got, expected)) return next();
    if (apiAuthMode !== "token" && remoteIsLoopback(req)) return next();
    return res.status(401).json({ ok: false, error: "unauthorized" });
  };
  app.use("/api", apiTokenGuard);

  // Cache invalidation — Laravel POSTs here when WABA config, branding
  // footer, or platform footer changes so Node picks up the new value
  // immediately (instead of waiting up to 5min for the TTL). X-Node-Token
  // authed. Body: { phone?: '<digits>' }; omit phone to flush everything.
  app.post("/api/cache-bust", (req, res) => {
    const expected = process.env.NODE_WEBHOOK_TOKEN || "";
    const token    = req.headers["x-node-token"] || "";
    if (!expected || !timingSafeEqualStr(token, expected)) {
      return res.status(401).json({ ok: false, error: "unauthorized" });
    }
    const phone = String(req.body?.phone || "").replace(/\D+/g, "") || null;
    const n = flushSettingsCache(phone);
    return res.json({ ok: true, flushed: n, phone: phone || "(all)" });
  });

  // Client routes
  app.get("/api/initialize-client/:phoneNumber", (req, res) => initializeClient(req, res, app));
  app.get("/api/client-status/:phoneNumber", (req, res) => getClientStatus(req, res, app));
  app.get("/api/terminate-client/:phoneNumber", (req, res) => terminateClient(req, res, app));
  app.get("/api/check-connection/:phoneNumber", (req, res) => checkConnection(req, res, app));
  app.get("/api/get-contacts/:phoneNumber", (req, res) => getContacts(req, res, app));
  app.get("/api/get-pairing-code/:phoneNumber", (req, res) => getPairingCode(req, res, app));


  // Message routes
  app.post("/api/send-message/:phoneNumber", (req, res) => sendMessage(req, res, app));
  app.post("/api/send-group-message/:phoneNumber", (req, res) => sendGroupMessage(req, res, app));
  app.post("/api/send-media-only/:phoneNumber", (req, res) => sendMediaOnly(req, res, app));
  app.post("/api/send-media-message/:phoneNumber", (req, res) => sendMediaMessage(req, res, app));
  app.post("/api/send-location/:phoneNumber", (req, res) => sendLocation(req, res, app));
  app.post("/api/send-product-catalog/:phoneNumber", (req, res) => sendProductCatalog(req, res, app));
  app.post("/api/send-reaction/:phoneNumber", (req, res) => sendReaction(req, res, app));
  app.post("/api/pin-message/:phoneNumber",   (req, res) => pinMessage(req, res, app));
  app.post("/api/star-message/:phoneNumber",  (req, res) => starMessage(req, res, app));
  app.post("/api/send-contact/:phoneNumber",  (req, res) => sendContact(req, res, app));
  app.post("/api/delete-message/:phoneNumber",(req, res) => deleteMessage(req, res, app));
  app.post("/api/edit-message/:phoneNumber",  (req, res) => editMessage(req, res, app));

  // Schedule routes
  app.post("/api/schedule-message/:phoneNumber", (req, res) => scheduleMessage(req, res, app));
  app.get("/api/scheduled-messages/:phoneNumber", (req, res) => getScheduledMessages(req, res, app));
  app.post("/api/schedule-message-bulk/:phoneNumber", (req, res) => scheduleBulkMessage(req, res, app));
  app.post("/api/schedule-recurring/:phoneNumber", (req, res) => scheduleRecurring(req, res, app));
  app.post("/api/send-bulk-immediate/:phoneNumber", (req, res) => sendBulkImmediate(req, res, app));
  app.post("/api/pause-schedule/:scheduleId", (req, res) => pauseSchedule(req, res, app));
  app.post("/api/resume-schedule/:scheduleId", (req, res) => resumeSchedule(req, res, app));
  app.delete("/api/cancel-scheduled-message/:scheduleId", (req, res) => cancelSchedule(req, res, app));
  app.put("/api/update-schedule/:scheduleId", (req, res) => updateSchedule(req, res, app));

  // Broadcast routes (NEW)
  app.post("/api/broadcast/send-immediate/:phoneNumber", (req, res) => sendBroadcastImmediate(req, res, app));
  app.post("/api/broadcast/schedule/:phoneNumber", (req, res) => scheduleBroadcast(req, res, app));
  app.post("/api/broadcast/pause/:scheduleId", (req, res) => pauseBroadcast(req, res, app));
  app.post("/api/broadcast/resume/:scheduleId", (req, res) => resumeBroadcast(req, res, app));
  app.delete("/api/broadcast/cancel/:scheduleId", (req, res) => cancelBroadcast(req, res, app));
  // Wide-net cancel — kills EVERY pending Node job for a Laravel broadcast id.
  // Laravel calls this before every /start-sending re-dispatch to clean up
  // orphans PHP forgot about (fixes the "fires 2-3 times" duplicate bug).
  app.delete("/api/broadcast/cancel-by-broadcast/:broadcastId", (req, res) => cancelByBroadcastId(req, res, app));
  app.get("/api/broadcast/list/:phoneNumber", (req, res) => getBroadcasts(req, res, app));
  app.get("/api/broadcast/status/:scheduleId", (req, res) => getBroadcastStatus(req, res, app));

  // Group routes — Baileys-only group create + manage. The Laravel side
  // (app/Http/Controllers/Api/App/GroupController.php) is the front
  // door; these are the actual Baileys passthroughs.
  //
  // SECURITY: every group route is gated by the X-Node-Token shared
  // secret (same boundary as /api/cache-bust). Without this, anyone
  // who can reach the Node port could leave a tenant's groups, rotate
  // invite codes, or kick participants by guessing the senderPhone in
  // the URL — `senderPhone` is NOT auth on its own.
  const nodeTokenGuard = (req, res, next) => {
    const expected = process.env.NODE_WEBHOOK_TOKEN || "";
    const got      = req.headers["x-node-token"] || "";
    if (!expected || !timingSafeEqualStr(got, expected)) {
      return res.status(401).json({ ok: false, error: "unauthorized" });
    }
    next();
  };
  app.post("/api/groups/create/:phoneNumber",         nodeTokenGuard, (req, res) => groupCreate(req, res, app));
  app.get ("/api/groups/all/:phoneNumber",            nodeTokenGuard, (req, res) => groupListAll(req, res, app));
  app.get ("/api/groups/meta/:phoneNumber",           nodeTokenGuard, (req, res) => groupMetadata(req, res, app));
  app.post("/api/groups/participants/:phoneNumber",   nodeTokenGuard, (req, res) => groupParticipantsUpdate(req, res, app));
  app.post("/api/groups/subject/:phoneNumber",        nodeTokenGuard, (req, res) => groupUpdateSubject(req, res, app));
  app.post("/api/groups/description/:phoneNumber",    nodeTokenGuard, (req, res) => groupUpdateDescription(req, res, app));
  app.post("/api/groups/settings/:phoneNumber",       nodeTokenGuard, (req, res) => groupUpdateSetting(req, res, app));
  app.post("/api/groups/leave/:phoneNumber",          nodeTokenGuard, (req, res) => groupLeave(req, res, app));
  app.get ("/api/groups/invite-code/:phoneNumber",    nodeTokenGuard, (req, res) => groupInviteCode(req, res, app));
  app.post("/api/groups/revoke-invite/:phoneNumber",  nodeTokenGuard, (req, res) => groupRevokeInvite(req, res, app));

  // Campaign routes
app.post('/api/wa/:phoneNumber/campaign/send', (req, res) => 
  sendCampaignImmediate(req, res, app)
);

app.post('/api/wa/:phoneNumber/campaign/schedule', (req, res) => 
  scheduleCampaign(req, res, app)
);

app.get('/api/wa/campaign/:campaignId/details', (req, res) => 
  getCampaignDetails(req, res, app)
);

app.post('/api/wa/campaign/:scheduleId/pause', (req, res) => 
  pauseCampaign(req, res, app)
);

app.post('/api/wa/campaign/:scheduleId/resume', (req, res) => 
  resumeCampaign(req, res, app)
);

app.post('/api/wa/campaign/:scheduleId/cancel', (req, res) => 
  cancelCampaign(req, res, app)
);


  // Flow routes
  app.post("/api/flow/start/:phoneNumber", (req, res) => startFlow(req, res, app));
  // Laravel commerce webhook → resume paused CommerceShop session.
  app.post("/api/flow/resume-port/:sessionKey", (req, res) => resumePort(req, res, app));
  // WABA inbound order webhook (no callback data) → phone-based lookup.
  app.post("/api/flow/resume-by-phone/:workspaceId/:customerPhone", (req, res) => resumeByPhone(req, res, app));
  // WhatsApp Form submission → resume the paused WaForm node, stamp
  // answers into session vars, advance through `out`.
  app.post("/api/flow/resume-form/:sessionKey", (req, res) => resumeForm(req, res, app));
  // Google Form submission (via Apps Script → Laravel → here). Body
  // carries session_id + answers; resumes the paused GoogleForm node.
  app.post("/api/flow-resume", (req, res) => resumeGoogleFormEndpoint(req, res, app));
  // Agent takeover — Laravel calls this when an operator manually attaches an
  // AI agent / assistant to a conversation in the team inbox, so any running
  // flow session for that customer STOPS and the AI cleanly owns the chat
  // (instead of the flow AND the AI both replying). Idempotent; X-Node-Token.
  app.post("/api/flow-end", (req, res) => {
    const expected = process.env.NODE_WEBHOOK_TOKEN || "";
    const got = req.headers["x-node-token"] || (req.body && req.body.node_token) || "";
    if (!expected || got !== expected) {
      return res.status(401).json({ ok: false, error: "unauthorized" });
    }
    const phone = String((req.body && req.body.customer_phone) || "");
    const ended = endFlowSessionsForPhone(app.locals, phone);
    if (ended > 0) {
      console.log(`[FLOW] agent-takeover flow-end for ${phone.replace(/\D+/g, "")} → ended ${ended} session(s)`);
    }
    return res.json({ ok: true, ended });
  });
  // WABA / Twilio inbound → flow engine (start by keyword OR resume an active
  // session) with sock=null. Baileys drives flows from its socket handler; this
  // is the equivalent entry for webhook-delivered providers. Purely additive.
  app.post("/api/flow/provider-inbound", (req, res) => providerInbound(req, res, app));

  // The /api/instagram-flow/* routes belong to the Instagram ADDON, which
  // registers them itself. Their controller ships with that addon, so a static
  // import here killed the entire bridge on installs without it.

  // WABA AI voice-call bridge — Laravel hands off the call when an
  // incoming WABA `connect` event lands AND an AI Voice Assistant
  // is configured for the workspace / conversation.
  app.post("/api/waba-call/answer",    (req, res) => wabaCallAnswer(req, res, app));
  app.post("/api/waba-call/terminate", (req, res) => wabaCallTerminate(req, res, app));
  // Operator pressed Record on the call popup. Recording is NOT automatic —
  // nothing is written to disk until this fires, and only audio from this
  // moment onward is captured.
  app.post("/api/waba-call/record", (req, res) => {
    const id = String(req.body?.meta_call_id || req.body?.call_id || "").trim();
    if (!id) return res.status(422).json({ ok: false, error: "meta_call_id required" });
    const ok = startRecording(id);
    if (!ok) return res.status(404).json({ ok: false, error: "call not active" });
    return res.json({ ok: true, recording: true });
  });
}
