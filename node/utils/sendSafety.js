// node/utils/sendSafety.js
// ─────────────────────────────────────────────────────────────────────
// Anti-ban + crash-safety primitives shared across every bulk-send
// service (campaign / broadcast / scheduled). Centralising these here
// means a fix to one service automatically applies to all — and the
// admin-tuneable defaults (`messageSettings.*`) flow through a single
// resolver instead of being re-implemented in three places.
//
// Concerns addressed:
//   • Inter-message jitter (Meta fingerprints uniform timing)
//   • Inter-batch jitter (same problem at the batch boundary)
//   • Baileys socket liveness (don't send into a dead pipe)
//   • Per-device daily volume cap (the #1 ban driver)
//   • Auth-template detection (Meta strict; wrong category = ban risk)
//   • HTTPS-only media for Baileys (Meta rejects http:// URLs)
// ─────────────────────────────────────────────────────────────────────

/**
 * Multiply `baseMs` by a uniform random factor in [1-spread, 1+spread].
 * spread=0.2 ⇒ ±20%. Floor at 1ms so a `setTimeout(fn, 0)` caller still
 * yields to the event loop.
 */
export function withJitter(baseMs, spread = 0.2) {
  const factor = 1 + (Math.random() * 2 - 1) * spread;
  return Math.max(1, Math.round(baseMs * factor));
}

/**
 * Per-message delay in ms, jittered ±20%. Reads `messageSettings.msg_gap`
 * (seconds) which is admin-tuned via /admin/settings/wadesk-message.
 * Default 3s — Baileys community consensus for safe pacing on a warmed
 * number.
 */
export function getJitteredMessageDelay(appLocals, overrideGap = null) {
  // WhatsApp Warmer — a warming number passes its own gap range {min,max}
  // (seconds). Pick a fresh uniform-random value in that range per message so
  // the broadcast paces at the warmer's gap instead of the global msg_gap.
  if (overrideGap && (overrideGap.min != null || overrideGap.max != null)) {
    const min = Math.max(0, Number(overrideGap.min) || 0);
    const max = Math.max(min, Number(overrideGap.max) || min);
    const sec = max > min ? (min + Math.random() * (max - min)) : min;
    return Math.max(1, Math.round(sec * 1000));
  }
  const baseMs = (appLocals?.messageSettings?.msg_gap || 3) * 1000;
  return withJitter(baseMs, 0.2);
}

/**
 * Serialize every outbound send on a single Baileys socket.
 *
 * Why this exists: when several brand-new conversations trigger a flow /
 * keyword reply at the same instant, each inbound handler calls
 * `sock.sendMessage` concurrently. For a recipient WhatsApp hasn't built
 * a Signal session with yet, Baileys must run an encryption handshake
 * first — and concurrent sends to different new recipients RACE that
 * handshake. Some reject ("No session" / "Bad MAC" / decrypt timeout),
 * so only the contact that "won" the handshake receives the reply and
 * the rest fail silently. That is the "flow replies to one number, not
 * the others" bug. Serializing sends per socket removes the race:
 * handshakes happen one at a time.
 *
 * Implementation: a promise chain per socket. Each send waits for the
 * previous to SETTLE, then runs. The stored chain tail swallows errors
 * so one failed send can't poison the next, while the caller still
 * receives the real result/rejection of its own send. Only the discrete
 * `sendMessage` call is chained — the per-node delays a flow inserts
 * between sends run in the caller, so concurrent flows still interleave
 * and no contact is starved.
 *
 * This wraps ONLY the high-level public `sock.sendMessage`. Baileys'
 * internal relay/receipt/retry machinery uses its own lower-level
 * functions, so its behaviour is untouched. Idempotent — re-wrapping an
 * already-wrapped socket is a no-op.
 */
export function serializeSocketSends(sock, devicePhone = '') {
  if (!sock || typeof sock.sendMessage !== 'function' || sock.__sendSerialized) {
    return sock;
  }
  const original = sock.sendMessage.bind(sock);
  let chain = Promise.resolve();
  sock.sendMessage = (...args) => {
    // `chain` is always a RESOLVED promise (see the tail assignment
    // below), so `original` is guaranteed to run for every call — a
    // previous send's failure never blocks the next one.
    const run = chain.then(() => original(...args));
    chain = run.then(() => {}, () => {});
    return run;
  };
  sock.__sendSerialized = true;
  console.log(`[SEND-SERIALIZE] per-device send queue armed for ${devicePhone || '(unknown)'}`);
  return sock;
}

/**
 * Inter-batch gap in ms, jittered ±20%. Reads
 * `messageSettings.bw_msg_gap` (minutes). Default 5 min.
 */
export function getJitteredBatchGapMs(appLocals) {
  const baseMs = (appLocals?.messageSettings?.bw_msg_gap || 5) * 60 * 1000;
  return withJitter(baseMs, 0.2);
}

/**
 * Batch toggle + size. Not jittered — these are operator-set targets.
 */
export function getBatchSettings(appLocals) {
  // Type-tolerant: Laravel currently sends 1/0 (int) but the value can also
  // arrive as boolean true / string "1" depending on the cache/source. A
  // strict `=== 1` silently fails on those, leaving batching off even when
  // the admin turned it ON — so accept every truthy representation.
  const raw = appLocals?.messageSettings?.enable_batches;
  const enabled = raw === 1 || raw === '1' || raw === true || raw === 'true';
  return {
    enabled,
    messagesPerBatch: Number(appLocals?.messageSettings?.batches_gap) || 50,
    batchGapMinutes: Number(appLocals?.messageSettings?.bw_msg_gap) || 5,
  };
}

/**
 * UTC date key (YYYY-MM-DD) for daily-tally bucketing. UTC so the
 * cap rolls over at the same wall-clock moment globally — local-time
 * keys cause inconsistent reset behaviour across multi-region hosts.
 */
export function todayKey() {
  return new Date().toISOString().slice(0, 10);
}

/**
 * Increment the daily Baileys send counter for `phone` on `appLocals`.
 * Stored as `appLocals._dailyTally[phone][YYYY-MM-DD] = n`. Stale day
 * entries are GC'd lazily inside this helper to keep memory bounded
 * even under long Node uptime.
 */
export function bumpDailyTally(appLocals, phone) {
  if (!appLocals || !phone) return;
  if (!appLocals._dailyTally) appLocals._dailyTally = {};
  const key = todayKey();
  const bucket = appLocals._dailyTally[phone] || (appLocals._dailyTally[phone] = {});
  bucket[key] = (bucket[key] || 0) + 1;
  for (const k of Object.keys(bucket)) {
    if (k < key) {
      const ageDays = (Date.parse(key) - Date.parse(k)) / 86400000;
      if (ageDays > 2) delete bucket[k];
    }
  }
}

/**
 * How many sends `phone` has done today (UTC).
 */
export function getDailyCount(appLocals, phone) {
  return appLocals?._dailyTally?.[phone]?.[todayKey()] || 0;
}

/**
 * Remaining headroom under the Baileys daily volume cap for `phone`.
 * Default cap 4000/day — WhatsApp's unofficial-stack ban radar fires
 * hard around 5k/day on a freshly-paired number, so 4k leaves a safety
 * margin for other surfaces (chat composer, inbox replies, flow
 * automations) sharing the same device. Admin can tune via
 * `messageSettings.baileys_daily_cap`.
 */
export function dailyCapRemaining(appLocals, phone) {
  const cap = appLocals?.messageSettings?.baileys_daily_cap || 4000;
  return Math.max(0, cap - getDailyCount(appLocals, phone));
}

/**
 * Hard `true/false` on whether `phone` can send another message today.
 */
export function canSendUnderCap(appLocals, phone) {
  return dailyCapRemaining(appLocals, phone) > 0;
}

/**
 * Truthy iff the Baileys socket is connected + the bridge marked the
 * phone as ready. Used to short-circuit a bulk send when the underlying
 * socket drops mid-run — without this gate we'd burn the recipient
 * list firing into a dead pipe, marking every row "failed" for no
 * reason AND inflating WhatsApp's spam signal on the recovered
 * session (which sees a flood of failed delivery attempts).
 *
 *   sock      — the Baileys client instance
 *   appLocals — for the `client_ready` registry
 *   phone     — the sender phone (used as registry key)
 */
export function isSockUsable(sock, appLocals, phone) {
  if (!sock) return false;
  if (appLocals?.client_ready && appLocals.client_ready[phone] === false) return false;
  try {
    // Baileys exposes the underlying WebSocket on `sock.ws`. readyState
    // 1 === OPEN; anything else means we shouldn't try to send.
    if (sock.ws && typeof sock.ws.readyState === 'number' && sock.ws.readyState !== 1) return false;
  } catch (_) {}
  return !!sock.user;
}

/**
 * Recognise an auth template (Meta's "authentication" category) so the
 * caller can refuse to ship it via a non-auth flow. Sending an auth
 * template as a regular marketing/utility message drops the WABA
 * quality score and trips Meta's category-mismatch policy. The PHP
 * gate already blocks at submit, but mirroring the check here gives
 * defence-in-depth against direct-to-Node API misuse.
 *
 * Accepts the templateData OR a campaign-shape object with
 * `templateData` nested. Looks at `template_type`, `category`, and
 * (Meta's canonical) `category` strings.
 */
export function isAuthTemplate(input) {
  if (!input) return false;
  const t = input.templateData || input;
  const tt = String(t.template_type || '').toLowerCase();
  const cat = String(t.category || t.meta_category || '').toLowerCase();
  return tt === 'auth' || cat === 'auth' || cat === 'authentication';
}

/**
 * Require HTTPS for Baileys media URLs. Meta enforces this on WABA
 * media (rejects with code 131009), and while Baileys itself accepts
 * http://, plain-HTTP image URLs in marketing messages are a known
 * abuse signal — the recipient's WhatsApp client won't expand the
 * preview, hurting engagement + boosting block rate. Treat as a
 * Baileys hygiene rule too. Returns null on pass, error string on fail.
 */
export function assertHttpsMedia(url) {
  if (!url) return null;
  const s = String(url).trim().toLowerCase();
  if (s.startsWith('http://')) return 'Media URL must be HTTPS (Meta rejects http://).';
  if (!s.startsWith('https://') && !s.startsWith('data:') && s.includes('://')) {
    return 'Unsupported media URL scheme — use https://';
  }
  return null;
}

/**
 * Sentinel error used by bulk send loops to bail out cleanly when the
 * Baileys socket disconnects mid-run. Throw this from inside the
 * per-recipient handler; the outer loop catches it and exits without
 * marking remaining recipients as `failed`.
 */
export const SOCK_DROPPED_ERROR = '__SOCK_DROPPED__';

/**
 * Boilerplate: pause-then-return when the sock dies mid-bulk-send.
 * Throws SOCK_DROPPED_ERROR so the outer try/catch in the caller's
 * loop can detect + exit. Marks the campaign/broadcast/schedule as
 * `paused` and PUSHes the status to Laravel via the supplied updater
 * so the operator UI shows the right state.
 */
export async function pauseOnSockDrop(entity, label, statusUpdater) {
  console.warn(`[${label}] sock unusable mid-run for ${entity.senderPhoneNumber} — pausing ${entity.scheduleId || entity.campaignId || entity.broadcastId}`);
  entity.status = 'paused';
  entity.lastError = 'Baileys connection dropped mid-run. Resume after reconnect.';
  if (typeof statusUpdater === 'function') {
    try { await statusUpdater(); } catch (_) {}
  }
  const err = new Error(SOCK_DROPPED_ERROR);
  err.code = SOCK_DROPPED_ERROR;
  throw err;
}

/**
 * Convenience predicate for outer-loop catch blocks: "was this a clean
 * sock-drop bail-out?". Lets the caller `if (isSockDropError(e)) return;`
 * cleanly instead of string-matching on error.message.
 */
export function isSockDropError(err) {
  return err && (err.message === SOCK_DROPPED_ERROR || err.code === SOCK_DROPPED_ERROR);
}

// ─────────────────────────────────────────────────────────────────────
// Twilio bulk-send helpers
// ─────────────────────────────────────────────────────────────────────
// Bulk services (broadcast / campaign / scheduled) previously branched
// only on `settings.use_facebook_api` vs Baileys' `sock.sendMessage`.
// Twilio workspaces fell through to the Baileys path with a null sock
// → crash. These helpers give every bulk service a single source of
// truth for "is this workspace Twilio?" and a uniform sender that
// handles plain / media / location AND ContentSid template sends.
// ─────────────────────────────────────────────────────────────────────

/**
 * Detect a Twilio workspace from the standard settings shape that PHP
 * ships via `/api/whatsapp-settings`. We require BOTH the toggle and the
 * creds — neither alone is enough (an admin might have flipped the flag
 * before completing onboarding, in which case we'd send into the void).
 * `use_facebook_api` always wins so a workspace migrating WABA → Twilio
 * with stale flags still ships through Meta until the operator clears it.
 */
export function isTwilioSettings(settings) {
  if (!settings) return false;
  if (settings.use_facebook_api) return false;
  return !!(settings.use_twilio && settings.twilio_account_sid && settings.twilio_auth_token && settings.twilio_from_number);
}

/**
 * Render a templateData + contact pair into the Twilio Content Variables
 * positional map (`{"1":"John","2":"ABC123"}`). Twilio's substitution
 * engine reads positional keys only — named like `"name"` are dropped.
 * Pulls placeholder names from `variable_map.body`, resolves each against
 * the contact's attributes (alias map mirrors what PHP's resolveContact
 * does in ChatController + BroadcastsController).
 */
export function buildTwilioContentVariables(templateData, contactData) {
  if (!templateData || !contactData) return {};
  const bodyMap = (templateData.variable_map && templateData.variable_map.body) || {};
  const otp = contactData.otp_code || templateData.otp_code || null;

  const aliasFor = (key) => {
    const k = String(key || '').toLowerCase();
    if (k === 'name' || k === 'first_name') return contactData.name || '';
    if (k === 'phone' || k === 'mobile' || k === 'number') return contactData.phone || '';
    if (k === 'email') return contactData.email || '';
    if (k === 'company') return contactData.company || '';
    // custom attributes (set by WaDesk's attribute system)
    const ca = contactData.custom_attributes || {};
    return ca[key] != null ? String(ca[key]) : '';
  };

  const out = {};
  // bodyMap can be either an array of {key, ...} objects (legacy) or
  // a positional map { "1": "name", "2": "order_id" } (current). Handle
  // both — bulk services in the wild still see both shapes.
  if (Array.isArray(bodyMap)) {
    bodyMap.forEach((entry, i) => {
      const key = (entry && (entry.key || entry.name)) || String(i + 1);
      out[String(i + 1)] = aliasFor(key);
    });
  } else if (bodyMap && typeof bodyMap === 'object') {
    Object.keys(bodyMap).forEach((pos) => {
      const named = String(bodyMap[pos] || '').trim();
      out[String(pos)] = aliasFor(named || pos);
    });
  }

  // Auth templates: OTP always overrides position "1".
  if (otp && !out['1']) out['1'] = String(otp);
  return out;
}

/**
 * Dispatch one Twilio send (template OR plain text/media). The caller
 * passes `phone` (E.164 digits, no `whatsapp:` prefix — we add it),
 * `templateData` (may be null for non-template campaigns), the per-
 * recipient `contactData`, and the resolved `settings`. Returns the same
 * `{ success, messageId, error }` shape as the Baileys helpers so the
 * outer bulk loops don't need branch-specific success handling.
 *
 * The `sendMessageViaTwilioApi` import is passed in to avoid a circular
 * dep — sendSafety can't import helpers.js without pulling in node-baileys.
 */
export async function sendTwilioBulkMessage(phone, templateData, contactData, plainBody, settings, sendMessageViaTwilioApi) {
  const contentSid = templateData && templateData.twilio_content_sid
    ? String(templateData.twilio_content_sid).trim()
    : '';

  if (contentSid) {
    const contentVariables = buildTwilioContentVariables(templateData, contactData);
    return sendMessageViaTwilioApi(phone, {
      type: 'template',
      contentSid,
      contentVariables,
    }, settings);
  }

  // Plain Body fallback — fine for session messages, lossy for marketing.
  const body = String(plainBody || templateData?.template_body || '');
  if (templateData && templateData.attachment_type && (templateData.attachment_file || templateData.attachment_url)) {
    const mediaUrl = templateData.attachment_url
      || `${process.env.APP_DOMAIN_NAME}/storage/wa-templates/${templateData.attachment_file}`;
    return sendMessageViaTwilioApi(phone, { type: 'media', mediaUrl, body }, settings);
  }
  return sendMessageViaTwilioApi(phone, { type: 'text', body }, settings);
}
