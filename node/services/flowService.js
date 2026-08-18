import axios from "axios";
import moment from "moment-timezone";
import dns from "dns";
import net from "net";
import { runUserCode } from "./codeSandbox.js";
import { formatInteractiveButtonsForBaileys, formatPhoneNumber, sendMessageViaFacebookApi, sendMessageViaTwilioApi, getWhatsAppSettings } from "../utils/helpers.js";
import {
  isSockUsable,
  bumpDailyTally,
  dailyCapRemaining,
} from "../utils/sendSafety.js";

// =============================================================
// WABA send helpers — single source of truth for the Meta Cloud
// API payload shapes used by flow nodes. Verified against the
// 2026 Meta spec (interactive button/list/location/cta_url/media
// + template). Every node executor that emits a WhatsApp message
// MUST call one of these helpers when the workspace is on WABA;
// previously each node only had Baileys code, so WABA workspaces
// silently dropped messages.
//
// Each helper returns { success, messageId } shaped identically
// to sendMessageViaFacebookApi so the caller can treat WABA + the
// rest of the helpers polymorphically.
// =============================================================

/** Resolve WABA settings ONCE per flow session — cached on the
 *  session object so 27 nodes don't each round-trip to Laravel. */
async function resolveWabaSettings(session, appDomainName, senderPhone) {
  if (session && session.__wabaSettings) return session.__wabaSettings;
  const settings = await getWhatsAppSettings(appDomainName, { phone: senderPhone });
  // Stash the context the team-inbox mirror needs (the app domain to POST to
  // + this device's own number). Baileys flow replies are mirrored by the
  // manager's fromMe upsert handler, but WABA/Twilio replies go out over HTTP
  // and never produce a socket event — so without this they'd be invisible in
  // /team-inbox (operator can't see what the flow's AI said to the customer).
  if (settings && typeof settings === 'object') {
    settings.__appDomainName = appDomainName;
    settings.__devicePhone   = senderPhone;
  }
  if (session) session.__wabaSettings = settings;
  return settings;
}

/**
 * Record a flow-sent WABA/Twilio reply into the team inbox as a
 * direction='out' message, via the same /api/inbound-message endpoint the
 * manager uses for Baileys outbound-sync. No-ops safely when context is
 * missing. Best-effort — a mirror failure must never break the actual send.
 */
async function mirrorFlowOutboundToInbox(settings, recipientPhone, text, mediaType = null, extra = null) {
  try {
    const appDomain = settings && settings.__appDomainName;
    const device    = settings && settings.__devicePhone;
    if (!appDomain || !device) return;
    const body = String(text || '').trim();
    if (!body && !mediaType) return;
    const recipient = String(recipientPhone || '').replace(/[^\d]/g, '');
    if (!recipient) return;

    await axios.post(`${appDomain}/api/inbound-message`, {
      device_phone:  device,
      sender_phone:  recipient,            // the customer side of the chat
      body,
      media_type:    mediaType || undefined,
      wa_message_id: `flow_out_${Date.now()}_${Math.floor(Math.random() * 1e6)}`,
      direction:     'out',
      source:        'flow',
      timestamp:     Math.floor(Date.now() / 1000),
      // Optional structured extras (e.g. { buttons:[{text}], header, footer })
      // so the team inbox renders a WhatsApp-style card + button, not plain text.
      ...(extra && typeof extra === 'object' ? extra : {}),
    }, {
      timeout: 8000,
      headers: { 'Accept': 'application/json', 'X-Node-Token': process.env.NODE_WEBHOOK_TOKEN || '' },
    });
  } catch (e) {
    console.warn(`[FLOW] inbox-mirror failed: ${e?.response?.status || ''} ${e?.message}`);
  }
}

// Send a Baileys message AND mirror it into the team inbox in one call. Baileys
// sends are NOT mirrored by the manager, so without this the flow's own replies
// (order summary, questions, confirmations, product cards…) never land in the
// team inbox — the operator saw only the customer's inbound side. Every
// customer-facing flow send goes through this instead of raw sock.sendMessage.
async function flowSockSend(sock, settings, jid, payload) {
  const res = await sock.sendMessage(jid, payload);
  try {
    let text = '', mediaType = null;
    if (payload && typeof payload === 'object') {
      if (typeof payload.text === 'string') text = payload.text;
      else if (typeof payload.caption === 'string') text = payload.caption;
      if (payload.image) mediaType = 'image';
      else if (payload.video) mediaType = 'video';
      else if (payload.audio || payload.ptt) mediaType = 'audio';
      else if (payload.document) mediaType = 'document';
      else if (payload.sticker) mediaType = 'sticker';
      else if (payload.location) mediaType = 'location';
    }
    if (text || mediaType) await mirrorFlowOutboundToInbox(settings, jid, text || '', mediaType);
  } catch (_) {}
  return res;
}

/** Branding footer — pulled from settings.branding_footer (resolved
 *  server-side by BrandingFooterService). For plain text we append
 *  `\n\n_<footer>_` to the body. For interactive messages we set the
 *  `footer` field on the interactive object. Templates skip entirely.
 *  Returns the body unchanged when no footer is configured. */
function _appendFooterToText(body, settings) {
  const footer = settings && settings.branding_footer;
  const beforeLen = String(body || '').length;
  if (!footer) {
    console.log(`[FOOTER] flow skip — branding_footer is ${footer === null ? 'null' : 'empty'} (body len=${beforeLen})`);
    return body;
  }
  const needle = `\n\n_${footer}_`;
  const text = String(body || '');
  if (text.endsWith(needle)) {
    console.log(`[FOOTER] flow idempotent — already ends with footer (footer="${footer}")`);
    return text;
  }
  const out = text + needle;
  console.log(`[FOOTER] flow APPENDED footer="${footer}" body ${beforeLen}→${out.length} chars`);
  return out;
}

function _interactiveFooter(settings) {
  const f = settings && settings.branding_footer;
  const v = f ? String(f).slice(0, 60) : null;
  console.log(`[FOOTER] flow interactive resolved=${v === null ? 'null' : `"${v}"`}`);
  return v;
}

/**
 * Returns true iff this settings bundle wants Twilio routing. Used by
 * every helper below to swap backend without touching call sites.
 */
function _isTwilio(settings) {
  return !!(settings && settings.use_twilio && settings.twilio_account_sid && settings.twilio_auth_token && !settings.use_facebook_api);
}

/** Plain text — routes to Twilio when settings.use_twilio is on. */
async function sendWabaText(phone, text, settings) {
  const r = _isTwilio(settings)
    ? await sendTwilioText(phone, text, settings)
    : await sendMessageViaFacebookApi(phone, {
        type: 'text',
        text: { preview_url: false, body: _appendFooterToText(text, settings) },
      }, settings);
  // Mirror into the team inbox (Baileys is mirrored by the manager; WABA/
  // Twilio HTTP sends are not, so without this the flow's AI reply is invisible).
  await mirrorFlowOutboundToInbox(settings, phone, text);
  return r;
}

/** Image / video / audio / document via public URL. */
async function sendWabaMedia(phone, kind, url, caption, filename, settings) {
  const type = ['image', 'video', 'audio', 'document'].includes(kind) ? kind : 'image';
  let r;
  if (_isTwilio(settings)) {
    r = await sendTwilioMedia(phone, kind, url, caption, filename, settings);
  } else {
    const media = { link: url };
    // Caption is allowed on image/video/document only — Meta rejects it
    // on audio with error 100. When present, also carries the branding
    // footer (the caption IS where Meta renders the body for media).
    if (caption && type !== 'audio') media.caption = _appendFooterToText(caption, settings);
    if (type === 'document' && filename) media.filename = String(filename);
    r = await sendMessageViaFacebookApi(phone, { type, [type]: media }, settings);
  }
  await mirrorFlowOutboundToInbox(settings, phone, caption || '', type);
  return r;
}

/** Interactive reply buttons (max 3). Each button has id + title.
 *  Meta cuts titles >20 chars and ids >256 chars — we truncate so
 *  Meta doesn't reject with #131009. Twilio degrades to numbered text. */
async function sendWabaButtons(phone, body, options, settings, opts = {}) {
  // Mirror the interactive STRUCTURE (button labels + header/footer) to the team
  // inbox — not just the body — so a flow / auto-reply buttons message renders as
  // a WhatsApp-style card with button rows, matching campaigns/broadcasts (which
  // already pass this). Without it the inbox showed only the plain body text.
  const _mirrorExtra = {
    buttons: (Array.isArray(options) ? options : []).slice(0, 3)
      .map((o, i) => ({ text: String(o.title ?? o.text ?? o.label ?? `Option ${i + 1}`) })),
    header: opts.header || undefined,
    footer: opts.footer || undefined,
  };
  if (_isTwilio(settings)) {
    const r = await sendTwilioButtons(phone, body, options, settings, opts);
    await mirrorFlowOutboundToInbox(settings, phone, body, null, _mirrorExtra);
    return r;
  }
  const buttons = (Array.isArray(options) ? options : [])
    .slice(0, 3)
    .map((o, i) => ({
      type: 'reply',
      reply: {
        id:    String((o.id ?? o.value ?? o.payload ?? `btn_${i}`)).slice(0, 256),
        title: String((o.title ?? o.text ?? o.label ?? `Option ${i + 1}`)).slice(0, 20),
      },
    }));
  if (buttons.length === 0) {
    // Fall back to plain text — Meta rejects interactive without buttons.
    return sendWabaText(phone, body, settings);
  }
  const interactive = {
    type: 'button',
    body: { text: String(body || '').slice(0, 1024) },
    action: { buttons },
  };
  if (opts.header) interactive.header = { type: 'text', text: String(opts.header).slice(0, 60) };
  // Caller-supplied footer wins; otherwise apply the plan-gated brand footer.
  const _btnFooter = opts.footer || _interactiveFooter(settings);
  if (_btnFooter) interactive.footer = { text: String(_btnFooter).slice(0, 60) };
  const r = await sendMessageViaFacebookApi(phone, { type: 'interactive', interactive }, settings);
  await mirrorFlowOutboundToInbox(settings, phone, body, null, _mirrorExtra);
  return r;
}

/** Interactive list. Meta caps 10 sections × 10 rows total. Twilio
 *  degrades to numbered text — customer types the digit. */
async function sendWabaList(phone, body, buttonLabel, sectionsIn, settings, opts = {}) {
  if (_isTwilio(settings)) return sendTwilioList(phone, body, buttonLabel, sectionsIn, settings, opts);
  // Mirror the list ROWS + header/footer to the team inbox so it renders a
  // structured card, not just the body text (parity with broadcasts).
  const _listMirrorExtra = {
    buttons: (Array.isArray(sectionsIn) ? sectionsIn : [])
      .flatMap((s) => (Array.isArray(s.rows) ? s.rows : []))
      .slice(0, 10)
      .map((r, i) => ({ text: String(r.title ?? r.text ?? r.label ?? `Row ${i + 1}`) })),
    header: opts.header || undefined,
    footer: opts.footer || undefined,
  };
  const sections = (Array.isArray(sectionsIn) ? sectionsIn : [])
    .slice(0, 10)
    .map((s) => ({
      title: String(s.title || 'Options').slice(0, 24),
      rows: (Array.isArray(s.rows) ? s.rows : []).slice(0, 10).map((r, i) => ({
        id:          String(r.id ?? r.value ?? r.payload ?? `row_${i}`).slice(0, 200),
        title:       String(r.title ?? r.text ?? r.label ?? `Row ${i + 1}`).slice(0, 24),
        description: r.description ? String(r.description).slice(0, 72) : undefined,
      })),
    }));
  if (sections.length === 0 || sections.every((s) => s.rows.length === 0)) {
    return sendWabaText(phone, body, settings);
  }
  const interactive = {
    type: 'list',
    body: { text: String(body || '').slice(0, 1024) },
    action: {
      button: String(buttonLabel || 'Select').slice(0, 20),
      sections,
    },
  };
  if (opts.header) interactive.header = { type: 'text', text: String(opts.header).slice(0, 60) };
  const _listFooter = opts.footer || _interactiveFooter(settings);
  if (_listFooter) interactive.footer = { text: String(_listFooter).slice(0, 60) };
  const r = await sendMessageViaFacebookApi(phone, { type: 'interactive', interactive }, settings);
  await mirrorFlowOutboundToInbox(settings, phone, body, null, _listMirrorExtra);
  return r;
}

/** Location pin. lat + lng required; name + address optional. */
async function sendWabaLocation(phone, lat, lng, name, address, settings) {
  if (_isTwilio(settings)) return sendTwilioLocation(phone, lat, lng, name, address, settings);
  return sendMessageViaFacebookApi(phone, {
    type: 'location',
    location: {
      latitude:  Number(lat),
      longitude: Number(lng),
      name:      name ? String(name) : undefined,
      address:   address ? String(address) : undefined,
    },
  }, settings);
}

/** Call-to-action URL button (single URL only — Meta rejects multiple).
 *  Twilio inlines the URL in the body. */
async function sendWabaCtaUrl(phone, body, displayText, url, settings, opts = {}) {
  if (_isTwilio(settings)) {
    const r = await sendTwilioCtaUrl(phone, body, displayText, url, settings, opts);
    await mirrorFlowOutboundToInbox(settings, phone, body);
    return r;
  }
  if (!url || !/^https?:\/\//.test(url)) {
    // No valid URL — degrade to text so the customer isn't silently dropped.
    return sendWabaText(phone, body + (url ? '\n' + url : ''), settings);
  }
  const interactive = {
    type: 'cta_url',
    body: { text: String(body || '').slice(0, 1024) },
    action: {
      name: 'cta_url',
      parameters: {
        display_text: String(displayText || 'Open').slice(0, 20),
        url:          String(url),
      },
    },
  };
  if (opts.header) interactive.header = { type: 'text', text: String(opts.header).slice(0, 60) };
  const _ctaFooter = opts.footer || _interactiveFooter(settings);
  if (_ctaFooter) interactive.footer = { text: String(_ctaFooter).slice(0, 60) };
  const r = await sendMessageViaFacebookApi(phone, { type: 'interactive', interactive }, settings);
  await mirrorFlowOutboundToInbox(settings, phone, body);
  return r;
}

/** Meta-approved template — only sends when templateName + meta_template_id
 *  match a registered template in the customer's WABA. Twilio uses
 *  ContentSid when registered, else degrades to plain text. */
async function sendWabaTemplate(phone, templateName, language, components, settings, twilioContentSid = null) {
  if (_isTwilio(settings)) {
    // Twilio fallback — build positional ContentVariables from the body
    // parameters Meta would have received. Twilio's substitution engine
    // expects `{"1":"x","2":"y"}` (positional indices, string keys).
    const bodyParam = (components || []).find((c) => c.type === 'body');
    const params   = bodyParam ? (bodyParam.parameters || []) : [];
    const contentVariables = {};
    params.forEach((p, i) => {
      contentVariables[String(i + 1)] = String(p.text || '');
    });
    const text = params.map((p) => p.text || '').join(' ').trim();
    return sendTwilioTemplate(phone, templateName, language, text, settings, twilioContentSid, contentVariables);
  }
  return sendMessageViaFacebookApi(phone, {
    type: 'template',
    template: {
      name: String(templateName),
      language: { code: String(language || 'en_US') },
      components: Array.isArray(components) ? components : [],
    },
  }, settings);
}

// =============================================================
// TWILIO SEND HELPERS — mirror the WABA shape so node executors
// can pick the backend with one flag (settings.use_twilio).
//
// Twilio's free-tier WhatsApp REST API supports text + media + location
// natively. Interactive content (buttons / lists / CTA / templates) goes
// through Twilio's Content API which has a separate submit-and-approve
// flow we don't yet support. For those, we degrade to plain text with
// numbered options — Twilio's official "free-form sandbox" pattern —
// and parse the digit reply in extractReplyFromMessage. That keeps
// flows usable on Twilio without forcing every customer through
// Content API setup.
// =============================================================

/** Plain text via Twilio. */
async function sendTwilioText(phone, text, settings) {
  return sendMessageViaTwilioApi(phone, {
    type: 'text',
    body: _appendFooterToText(text, settings),
  }, settings);
}

/** Media (image/video/audio/doc) via Twilio. mediaUrl must be public HTTPS. */
async function sendTwilioMedia(phone, kind, url, caption, filename, settings) {
  // Twilio doesn't expose a separate media-kind selector — it sniffs the
  // mime from the URL. `filename` is ignored on Twilio (the recipient
  // sees the URL's last path segment instead).
  return sendMessageViaTwilioApi(phone, {
    type: 'media',
    mediaUrl: url,
    body: caption ? _appendFooterToText(caption, settings) : undefined,
  }, settings);
}

/** Reply-buttons degraded to numbered text. Customer types "1", "2", "3";
 *  extractReplyFromMessage maps the digit back to the button index. */
async function sendTwilioButtons(phone, body, options, settings, opts = {}) {
  const list = (Array.isArray(options) ? options : []).slice(0, 10);
  if (list.length === 0) return sendTwilioText(phone, body, settings);
  const numbered = list.map((o, i) => {
    const label = String(o.title ?? o.text ?? o.label ?? `Option ${i + 1}`);
    return `${i + 1}. ${label}`;
  }).join('\n');
  const header = opts.header ? `*${opts.header}*\n\n` : '';
  const footer = opts.footer || _interactiveFooter(settings);
  const footerLine = footer ? `\n\n_${footer}_` : '';
  return sendTwilioText(phone, `${header}${body || ''}\n\n${numbered}\n\n_Reply with the number (1-${list.length})_${footerLine}`, settings);
}

/** List rows degraded to numbered text. Same digit-reply convention. */
async function sendTwilioList(phone, body, buttonLabel, sectionsIn, settings, opts = {}) {
  const sections = Array.isArray(sectionsIn) ? sectionsIn : [];
  const allRows = sections.flatMap((s) => Array.isArray(s.rows) ? s.rows : []);
  if (allRows.length === 0) return sendTwilioText(phone, body, settings);
  let n = 1;
  const blocks = sections.map((s) => {
    const rows = (Array.isArray(s.rows) ? s.rows : []).map((r) => {
      const label = String(r.title ?? r.text ?? r.label ?? `Row ${n}`);
      const desc  = r.description ? `\n     ${r.description}` : '';
      return `${n++}. ${label}${desc}`;
    }).join('\n');
    return s.title ? `*${s.title}*\n${rows}` : rows;
  }).filter(Boolean).join('\n\n');
  const header = opts.header ? `*${opts.header}*\n\n` : '';
  const footer = opts.footer || _interactiveFooter(settings);
  const footerLine = footer ? `\n\n_${footer}_` : '';
  return sendTwilioText(phone, `${header}${body || ''}\n\n${blocks}\n\n_Reply with the number (1-${allRows.length})_${footerLine}`, settings);
}

/** Location pin. Twilio supports this natively via PersistentAction geo:. */
async function sendTwilioLocation(phone, lat, lng, name, address, settings) {
  return sendMessageViaTwilioApi(phone, {
    type: 'location',
    latitude: Number(lat),
    longitude: Number(lng),
    name: name || address || '📍 location',
  }, settings);
}

/** CTA URL — Twilio has no native CTA bubble. Inline the URL in the body
 *  so customers can tap it directly (WhatsApp auto-links http/https). */
async function sendTwilioCtaUrl(phone, body, displayText, url, settings, opts = {}) {
  if (!url || !/^https?:\/\//.test(url)) return sendTwilioText(phone, body, settings);
  const header = opts.header ? `*${opts.header}*\n\n` : '';
  const cta = `${displayText || 'Open'}: ${url}`;
  return sendTwilioText(phone, `${header}${body || ''}\n\n${cta}`, settings);
}

/**
 * Twilio Content Template send. When the caller passes `contentSid` we go
 * through Twilio's approved-template path (compliant for MARKETING /
 * UTILITY / AUTHENTICATION outside the 24h session window). When no
 * ContentSid is available we degrade to plain text using the rendered
 * body — fine for session messages, NOT compliant for cold marketing.
 *
 * Extra args: `contentSid` (HX…) and `contentVariables` (positional map).
 */
async function sendTwilioTemplate(phone, templateName, language, bodyText, settings, contentSid = null, contentVariables = null) {
  if (contentSid) {
    return sendMessageViaTwilioApi(phone, {
      type: 'template',
      contentSid,
      contentVariables: contentVariables || {},
    }, settings);
  }
  console.warn(`[FLOW-TWILIO] template "${templateName}" sent as plain text — no Twilio ContentSid registered on the template.`);
  return sendTwilioText(phone, bodyText || templateName, settings);
}

/**
 * Resolve which engine to use, in priority order. Returns one of
 * 'waba' | 'twilio' | 'baileys'. Cached on the session along with
 * the resolved settings so the lookup runs once per flow session,
 * not once per node.
 */
function resolveEngine(settings) {
  if (settings && settings.use_facebook_api && settings.facebook_phone_id && settings.facebook_api_token) return 'waba';
  if (settings && settings.use_twilio && settings.twilio_account_sid && settings.twilio_auth_token) return 'twilio';
  return 'baileys';
}

/**
 * Single source of truth for "what did the user say".
 *
 * WhatsApp delivers user replies in SIX different shapes depending on
 * the client version, carrier path, and how the message was originally
 * sent. If a handler reads only the first shape, it silently drops
 * every reply that arrives in shape 4/5/6. That bug killed our List,
 * Buttons, Poll, BookAppointment, and Template flows in production.
 *
 * Returns { id, text } — `id` is the option ID we minted (or empty),
 * `text` is the human-visible answer.
 */
function extractReplyFromMessage(message) {
  const m = message?.message || {};
  // Modern interactive (quick_reply / cta_*): paramsJson holds { id, display_text }.
  if (m.interactiveResponseMessage?.nativeFlowResponseMessage?.paramsJson) {
    try {
      const p = JSON.parse(m.interactiveResponseMessage.nativeFlowResponseMessage.paramsJson);
      return {
        id:   String(p?.id || p?.ID || ""),
        text: String(p?.display_text || p?.id || ""),
      };
    } catch { /* malformed JSON */ }
  }
  // Template buttons (some clients/carriers downgrade quick_reply to this).
  if (m.templateButtonReplyMessage) {
    return {
      id:   String(m.templateButtonReplyMessage.selectedId || ""),
      text: String(m.templateButtonReplyMessage.selectedDisplayText || m.templateButtonReplyMessage.selectedId || ""),
    };
  }
  // Legacy listMessage reply.
  if (m.listResponseMessage?.singleSelectReply) {
    return {
      id:   String(m.listResponseMessage.singleSelectReply.selectedRowId || ""),
      text: String(m.listResponseMessage.singleSelectReply.title || m.listResponseMessage.singleSelectReply.selectedRowId || ""),
    };
  }
  // Legacy buttonsMessage reply.
  if (m.buttonsResponseMessage) {
    return {
      id:   String(m.buttonsResponseMessage.selectedButtonId || ""),
      text: String(m.buttonsResponseMessage.selectedDisplayText || m.buttonsResponseMessage.selectedButtonId || ""),
    };
  }
  // Shared location pin (Unofficial/Baileys engine). A pin has NO text body, so
  // an "Ask question" node would otherwise capture the customer's NEXT typed
  // message into the location variable and never satisfy a "did they send a
  // location?" check — the exact loop Abdul Haq hit. Convert the pin to a Google
  // Maps link so the variable is populated as free text, mirroring the WABA
  // bridge (WaWebhookController forwards the same maps link). Covers the live
  // location shape too (liveLocationMessage).
  const _loc = m.locationMessage || m.liveLocationMessage;
  if (_loc && (_loc.degreesLatitude != null || _loc.degreesLongitude != null)) {
    const lat  = _loc.degreesLatitude;
    const lng  = _loc.degreesLongitude;
    const name = String(_loc.name || _loc.address || "").trim();
    const link = `https://www.google.com/maps?q=${lat},${lng}`;
    return { id: "", text: name ? `${name} ${link}` : link };
  }
  // Plain text (incl. text-fallback paths and replies users typed manually).
  return {
    id:   "",
    text: String(m.conversation || m.extendedTextMessage?.text || "").trim(),
  };
}

// ✅ STEP 1: Handle flow response with proper appLocals passing
export async function handleFlowResponse(
  message,
  session,
  userNumber,
  phoneNumber,
  sock,
  appLocals  // ← ADD THIS PARAMETER
) {
  // Hoist the original wait object — CommerceShop reads back items/
  // provider/storeId via session.waitingForInput, so we restore it
  // for that branch (resolveCommerceShopReply manages its own
  // lifecycle: re-prompts on bad input, nulls on a successful pick).
  const originalWait = session.waitingForInput;
  if (!originalWait) {
    console.warn(`[FLOW] handleFlowResponse called but waitingForInput is null — ignoring`);
    return;
  }
  const { nodeId, variable, answerItems, nextNodeType } = originalWait;

  // Centralised extractor handles all 6 reply shapes WhatsApp emits.
  const reply = extractReplyFromMessage(message);
  let userMessage = reply.text || reply.id;

  console.log(`[FLOW] handleFlowResponse entry node=${nodeId} type=${nextNodeType} text="${String(userMessage).substring(0, 40)}" replyId="${reply.id}" msgKeys=[${Object.keys(message.message || {}).join(',')}]`);

  // sessionKey is used by the mid-flow menu re-pick block below (it can restart a
  // branch BEFORE the normal routing runs), so it must be declared up here — not
  // after the reply is consumed. Referencing the old `const sessionKey` further
  // down from this block threw a ReferenceError (temporal dead zone) at runtime,
  // which silently killed every tap on an earlier menu. Declared once, here.
  const sessionKey = `${phoneNumber}_${userNumber}`;

  // ── Button-tap router — route to the EXACT menu the tapped button belongs to ─
  // A button tap must land on the branch wired to THAT button, no matter which
  // node the flow is currently waiting on. WhatsApp keeps EVERY menu's buttons
  // tappable forever, so a customer can (and this client does) tap an OLD menu's
  // button at any time — e.g. tap "No Documents" long after the flow moved to the
  // charges menu. With shared p0/p1/p2 ids that tap was matched against whichever
  // node was waiting and routed down ITS branch → the wrong ("Pricing"/"Meta
  // Charges") message. We identify the source menu two ways, in order:
  //   1) NODE-SCOPED id `b_<nodeId>_p<idx>` — buttons minted AFTER this fix. Exact.
  //   2) UNIQUE button TITLE — covers buttons already sitting in the chat that were
  //      sent BEFORE the fix (still carry the old bare ids) and template buttons.
  //      Only when the title identifies exactly ONE menu; an ambiguous label (same
  //      text in two menus) falls through to the normal path so nothing misroutes.
  // Then handleQuestionResponse id/text-matches against THAT menu's own options and
  // advances its exact edge (and stays put on an unwired option). Typed answers are
  // unaffected — a free-text reply carries no reply.id and rarely a menu title.
  {
    let _srcNode = null;
    const _bm = String(reply.id || "").match(/^b_(n_[a-z0-9]+)_p(\d+)$/i);
    if (_bm) {
      _srcNode = session.flowData.flowNodes.find((n) => n.id === _bm[1]) || null;
    }
    if (!_srcNode) {
      const _t = String(reply.text || "").trim().toLowerCase();
      if (_t !== "") {
        const _hits = session.flowData.flowNodes.filter((n) =>
          n.flowNodeType === "InteractiveButtons"
          && Array.isArray(n.answerItems)
          && n.answerItems.some((it) => {
            // Meta truncates a button TITLE to 20 chars, so the echoed reply text
            // can be shorter than the original option text — compare the first 20.
            const _lbl = String(it.answerText || it.text || "").trim().toLowerCase();
            return _lbl === _t || _lbl.slice(0, 20) === _t;
          })
        );
        if (_hits.length === 1) _srcNode = _hits[0];
      }
    }
    if (_srcNode && Array.isArray(_srcNode.answerItems) && _srcNode.answerItems.length && (reply.id || reply.text)) {
      const _var = _srcNode.variable || _srcNode.var || "choice";
      console.log(`[FLOW] button tap id="${reply.id}" text="${String(reply.text || "").slice(0, 30)}" → menu node=${_srcNode.id}; routing to its wired branch (was waiting node=${nodeId})`);
      session.waitingForInput = null;
      await handleQuestionResponse(reply.text || reply.id, session, _srcNode.id, _srcNode.answerItems, userNumber, phoneNumber, sock, appLocals, sessionKey, reply.id, _var);
      return;
    }
  }

  session.userVariables[variable] = userMessage;
  // Also expose this answer under its NODE ID, so a later node — e.g. a Webhook
  // body — can pull *this specific* question's answer with {{n_xxxxxx}}. Without
  // it, answers only lived under the node's named variable (or the shared
  // "user_message" fallback, which each new question overwrites), so a webhook
  // body of {"type":"{{n_ubiz8k}}", …} resolved every field to "" and POSTed an
  // empty body — even though the same URL worked from Postman (which sends a
  // real body). The builder inserts node-id refs, so this is the intended key.
  if (nodeId) session.userVariables[nodeId] = userMessage;
  session.waitingForInput = null;

  const waitNode = session.flowData.flowNodes.find((n) => n.id === nodeId);

  if (nextNodeType === "List") {
    await handleListResponse(
      message,
      session,
      nodeId,
      userNumber,
      phoneNumber,
      sock,
      appLocals,
      sessionKey,
    );
  } else if (nextNodeType === "Poll" || nextNodeType === "PollFallback") {
    await handlePollResponse(
      message,
      session,
      nodeId,
      answerItems,
      userNumber,
      phoneNumber,
      sock,
      appLocals,
      sessionKey
    );
  } else if (nextNodeType === "Question") {
    await handleQuestionResponse(
      userMessage,
      session,
      nodeId,
      answerItems,
      userNumber,
      phoneNumber,
      sock,
      appLocals,
      sessionKey,
      reply.id,
      variable
    );
    // Persistent template menu: if the branch we just routed to was a leaf
    // (it ended without opening its own prompt → waitingForInput still null),
    // keep the menu OPEN by re-arming the same wait — WITHOUT re-sending the
    // template. The customer taps the remaining buttons on the original
    // message and each tap routes to its branch; we don't spam the template
    // on every tap. To re-run from scratch the customer re-types the trigger
    // keyword (BaileysClientManager restarts the flow). Only for template
    // menus flagged persistent; a branch that opens its own question/list
    // takes over above (waitingForInput would be set, so this is skipped).
    if (originalWait.persistent && waitNode && !session.waitingForInput) {
      session.status = "active";
      session.waitingForInput = originalWait; // re-arm: next tap on the same message still routes
      appLocals.activeFlowSessions[sessionKey] = session; // survive endFlowSession's delayed cleanup
      console.log(`[FLOW] persistent menu — re-armed node=${nodeId} (no re-send), awaiting another tap`);
    }
  } else if (nextNodeType === "Condition") {
    await executeFlowNode(
      waitNode,
      userNumber,
      phoneNumber,
      sock,
      appLocals,
      sessionKey
    );
  } else if (nextNodeType === "BookAppointment") {
    // Restore the wait-state the router just nulled (line above): the handler
    // reads slots / workspaceId / confirmation OFF session.waitingForInput. With
    // it null the slots array was empty, so EVERY tap resolved to slot=null and
    // dead-ended to no_slots BEFORE the booking POST ever ran (same pattern the
    // CommerceShop branch already restores). The handler nulls it again itself.
    session.waitingForInput = originalWait;
    await handleBookAppointmentResponse(
      message,
      session,
      nodeId,
      userNumber,
      phoneNumber,
      sock,
      appLocals,
      sessionKey,
    );
  } else if (nextNodeType === "CommerceShop") {
    // Customer replied to the commerce node's product list — try the
    // numeric/name match, mint the checkout link, send it back.
    // Restore waitingForInput so the resolver can read items/provider/
    // storeId; it nulls the slot itself on a successful pick.
    session.waitingForInput = originalWait;
    await resolveCommerceShopReply(
      message,
      session,
      userNumber,
      phoneNumber,
      sock,
      appLocals,
      sessionKey,
    );
  } else if (nextNodeType === "AiChat") {
    // Conversation mode — the AI node is driving the chat. The exit keyword
    // (if configured) breaks the loop and advances to the wired next node;
    // any other message re-runs the AI node, which answers and re-parks.
    const exitKw = String(originalWait.exitKeyword || "").trim().toLowerCase();
    const um = String(userMessage || "").trim().toLowerCase();
    if (exitKw && um === exitKw) {
      console.log(`[FLOW] ai conversation exit keyword "${exitKw}" — advancing node=${nodeId}`);
      // If this conversational node ALSO has "Extract structured data (JSON)"
      // on, its per-turn replies were natural language, so nothing was ever
      // flattened into {{reply.<field>}}. Run ONE hidden extraction pass over
      // the whole transcript now — on exit — so downstream nodes can read the
      // collected NAME / PHONE / ADDRESS / … before we advance. No-op when the
      // node isn't in extract mode, so plain conversation flows are unchanged.
      await extractFieldsFromTranscriptOnExit(waitNode, session, appLocals);
      await moveToNextNode(nodeId, session.flowData, userNumber, phoneNumber, sock, appLocals, sessionKey);
    } else {
      session.userVariables.user_message = userMessage;
      await executeFlowNode(waitNode, userNumber, phoneNumber, sock, appLocals, sessionKey);
    }
  }
}

/**
 * Conversation-mode + extraction bridge.
 *
 * A conversational AI node ("Conversation mode" ON) replies in natural
 * language every turn, so the per-turn extraction inside executeChatGPTNode
 * never sees a JSON object and {{reply.<field>}} stays empty. When that SAME
 * node also has "Extract structured data (JSON)" ON, we do one final hidden
 * extraction the moment the customer types the exit keyword: feed the whole
 * transcript to the model in strict-JSON mode and flatten each field into
 * `session.userVariables['<saveAs>.<field>']` — exactly the shape the live
 * per-turn path writes — so the wired "Send message" / Sheets / order nodes
 * downstream can read {{reply.NAME}} etc.
 *
 * Silent + additive: returns immediately unless the node is BOTH conversational
 * and in extract mode with at least one field, so every other flow is untouched.
 */
async function extractFieldsFromTranscriptOnExit(node, session, appLocals) {
  if (!node) return;
  const extract = node.extract === true || node.properties?.extract === true;
  const conversational = node.conversational === true || node.chat_mode === true
                      || node.properties?.conversational === true;
  const fields = Array.isArray(node.fields) ? node.fields
               : (Array.isArray(node.properties?.fields) ? node.properties.fields : []);
  if (!extract || !conversational || !fields.length) return;

  const saveAs = String(node.variable || node.properties?.outputVariable || "reply").replace(/^@/, "");
  const model  = String(node.model || node.properties?.model || "gpt-4o-mini");
  const workspaceId = session.flowData?.workspace_id || 0;

  // Rebuild the conversation the node kept while it was driving the chat.
  const histKey = `__aichat_${node.id}`;
  const history = Array.isArray(session.userVariables[histKey]) ? session.userVariables[histKey] : [];
  if (!history.length) {
    console.warn(`[FLOW] ai extract-on-exit node=${node.id} — empty transcript, nothing to extract`);
    return;
  }
  const transcript = history
    .map((h) => `${h.role === "assistant" ? "Assistant" : "Customer"}: ${h.content}`)
    .join("\n");

  const sysPrompt = "You extract structured data from a support/sales chat transcript. "
    + "Read the whole conversation and return ONLY a single JSON object with exactly these keys: "
    + fields.join(", ") + ". Use the values the CUSTOMER provided. If a value is missing, use an empty string. "
    + "Do not invent data. Return JSON only — no prose, no code fences.";

  try {
    const resp = await axios.post(
      `${appLocals.appDomainName}/api/flow-node/ai-call`,
      { workspace_id: workspaceId, model, system_prompt: sysPrompt,
        user_prompt: `Conversation transcript:\n${transcript}`,
        max_tokens: 4000, temperature: 0, json: true, fields },
      { timeout: 30000, headers: { "X-Node-Token": process.env.NODE_WEBHOOK_TOKEN || "" } },
    );
    const raw = String(resp.data?.reply || "");
    console.log(`[FLOWTRACE] ai extract-on-exit node=${node.id} fields=[${fields.join(",")}] raw=${JSON.stringify(raw.slice(0, 600))}`);
    const parsed = _tryParseJson(raw);
    if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
      console.warn(`[FLOW] ai extract-on-exit node=${node.id} — reply was not a JSON object → {{${saveAs}.*}} stay empty`);
      return;
    }
    let n = 0;
    for (const [k, v] of Object.entries(parsed)) {
      session.userVariables[`${saveAs}.${k}`] =
        (v === null || v === undefined) ? "" :
        (typeof v === "object" ? JSON.stringify(v) : String(v));
      n++;
    }
    // Also stash the raw JSON under the bare var, mirroring the live path.
    session.userVariables[saveAs] = raw;
    console.log(`[FLOW] ai extract-on-exit node=${node.id} → ${n} field var(s) under ${saveAs}.*`);
  } catch (e) {
    console.warn(`[FLOW] ai extract-on-exit node=${node.id} FAILED: ${e?.response?.data?.message || e?.message}`);
  }
}

/**
 * Customer just tapped a slot in the list message we sent from
 * executeBookAppointmentNode. Map the rowId → slot index → slot
 * payload, POST it to Laravel, send the confirmation message, and
 * advance through the `booked` port.
 *
 * If the slot index can't be parsed (customer typed text instead of
 * tapping), we still try to interpret a 1-based number ("1", "2"...).
 * Out-of-range or unparseable → branch to no_slots so the flow has a
 * fallback path.
 */
async function handleBookAppointmentResponse(
  message,
  session,
  nodeId,
  userNumber,
  phoneNumber,
  sock,
  appLocals,
  sessionKey,
) {
  const waitState = session.waitingForInput || {};
  const slots         = waitState.slots || [];
  const workspaceId   = waitState.workspaceId;
  const confirmation  = waitState.confirmation || "Booked! See you on {{slot}}.";

  // Decode the customer's pick. Native list reply lands as
  // listResponseMessage.singleSelectReply.selectedRowId ("appt_<nodeId>_<i>").
  // Text fallback: a 1-based number.
  // Use the CENTRAL reply extractor — modern WhatsApp/Meta clients deliver a
  // list tap as interactiveResponseMessage.nativeFlowResponseMessage.paramsJson
  // ({id:"appt_<nodeId>_<i>"}), NOT the legacy listResponseMessage shape this
  // handler used to read. Missing that made every slot tap parse as idx=-1 and
  // dead-end to "Sorry, no slots available." extractReplyFromMessage handles
  // native-flow / template-button / list / buttons / plain-text uniformly.
  const reply    = extractReplyFromMessage(message);
  const rowId    = reply.id || "";
  const userText = String(reply.text || "").trim();
  let idx = -1;
  // 1) Our own row/button id "appt_<nodeId>_<i>".
  if (rowId.startsWith("appt_")) {
    idx = parseInt(rowId.split("_").pop(), 10);
  }
  // 2) Some WhatsApp clients downgrade quick_reply → templateButtonReply and
  //    DROP our "appt_" id, returning only the visible (often truncated) label.
  //    Match the reply text back to a slot by its label — this is why every tap
  //    was dead-ending to no_slots. userText is a prefix of the full label.
  if ((isNaN(idx) || idx < 0 || idx >= slots.length) && userText) {
    idx = slots.findIndex((s) => {
      const lbl = String(s.label || s.start || "");
      return lbl === userText || lbl.startsWith(userText) || lbl.slice(0, 24) === userText;
    });
  }
  // 3) A 1-based number typed manually ("1", "2"…).
  if ((isNaN(idx) || idx < 0) && /^\d+$/.test(userText)) {
    const n = parseInt(userText, 10);
    if (n >= 1 && n <= slots.length) idx = n - 1;
  }

  session.waitingForInput = null;

  const slot = (idx >= 0 && idx < slots.length) ? slots[idx] : null;
  if (!slot) {
    // Unrecognised reply — fall through no_slots branch.
    await advanceToPort(
      nodeId, 2,
      session.flowData, userNumber, phoneNumber,
      sock, appLocals, sessionKey,
    );
    return;
  }

  const appDomain = appLocals.appDomainName;
  const payload   = {
    workspace_id:    workspaceId,
    starts_at:       slot.start,
    ends_at:         slot.end,
    title:           `Booking — ${session.userVariables?.name || "WhatsApp customer"}`,
    customer_name:   session.userVariables?.name || null,
    customer_phone:  userNumber,
    customer_email:  session.userVariables?.email || null,
  };

  let booked = false;
  try {
    // X-Node-Token header — AppointmentController::bookApi rejects requests
    // without it (no Laravel session on the Node runtime). Without this
    // every customer tap on a slot silently 401'd and the flow took the
    // no_slots branch even after the booking call.
    // Accept:json so Laravel renders ANY exception (plan gate, validation…) as a
    // JSON 4xx instead of a paywall REDIRECT — a 302→200 HTML was being read as
    // booked=false and silently dropping the customer to "no slots".
    const r = await axios.post(`${appDomain}/api/appointments/book`, payload, {
      timeout: 12000,
      headers: { 'X-Node-Token': process.env.NODE_WEBHOOK_TOKEN || '', 'Accept': 'application/json' },
    });
    booked = !!(r.data && r.data.ok);
    if (!booked) console.warn(`[${phoneNumber}] BookAppointment book returned not-ok: status=${r.status} body=${JSON.stringify(r.data).slice(0, 300)}`);
  } catch (e) {
    console.warn(`[${phoneNumber}] BookAppointment book POST failed: status=${e?.response?.status || ''} ${e?.message} body=${JSON.stringify(e?.response?.data || '').slice(0, 300)}`);
  }

  if (!booked) {
    await advanceToPort(
      nodeId, 2,
      session.flowData, userNumber, phoneNumber,
      sock, appLocals, sessionKey,
    );
    return;
  }

  // Send the confirmation message with the slot label interpolated.
  // Multi-engine: route the confirmation over the workspace's engine (WABA /
  // Twilio / Baileys) instead of hardcoding the Baileys sock — otherwise a
  // WABA/Twilio workspace books the slot but the customer never gets the
  // confirmation (sock is null there).
  const confirmText = confirmation.replace(/\{\{\s*slot\s*\}\}/gi, slot.label || slot.start);
  try {
    const settings = await resolveWabaSettings(session, appLocals.appDomainName, phoneNumber);
    const engine = resolveEngine(settings); // 'waba' | 'twilio' | 'baileys'
    if (engine === 'waba') {
      await sendWabaText(formatPhoneNumber(userNumber), confirmText, settings);
    } else if (engine === 'twilio') {
      await sendTwilioText(userNumber, confirmText, settings);
    } else if (sock) {
      await sock.sendMessage(formatPhoneNumber(userNumber), { text: confirmText });
    }
    session.messageHistory.push({
      type: "sent", message: confirmText, timestamp: moment().format(),
    });
  } catch (e) {
    console.warn(`[${phoneNumber}] BookAppointment confirmation send failed: ${e?.message}`);
  }

  // Stash the slot label on session vars so downstream nodes can
  // reference {{slot}} too.
  session.userVariables = session.userVariables || {};
  session.userVariables.slot = slot.label || slot.start;
  session.userVariables.appointment_choice = slot.label || slot.start;

  // Take the `booked` branch (port 1).
  await advanceToPort(
    nodeId, 1,
    session.flowData, userNumber, phoneNumber,
    sock, appLocals, sessionKey,
  );
}

// ✅ STEP 2: Handle poll response
async function handlePollResponse(
  message,
  session,
  nodeId,
  answerItems,
  userNumber,
  phoneNumber,
  sock,
  appLocals,
  sessionKey
) {
  const pollUpdate = message.message?.pollUpdateMessage;
  let selectedOptions = [];

  if (pollUpdate) {
    selectedOptions = pollUpdate.votes || [];
  } else {
    const userResponse = message.message?.conversation || "";
    const allowMultiple = session.waitingForInput?.allowMultiple || false;

    if (allowMultiple) {
      const parts = userResponse
        .split(/[,\s]+/)
        .map((p) => p.trim())
        .filter(Boolean);
      selectedOptions = parts
        .map((part) => {
          const num = parseInt(part);
          if (!isNaN(num) && num >= 1 && num <= answerItems.length) {
            const option = answerItems[num - 1];
            return typeof option === "string"
              ? option
              : option.text || option.answerText;
          }
          return null;
        })
        .filter(Boolean);
    } else {
      const num = parseInt(userResponse);
      if (!isNaN(num) && num >= 1 && num <= answerItems.length) {
        const option = answerItems[num - 1];
        selectedOptions = [
          typeof option === "string"
            ? option
            : option.text || option.answerText,
        ];
      } else {
        selectedOptions = [userResponse];
      }
    }
  }

  session.userVariables["poll_response"] = selectedOptions;
  let outputNum = "1";

  if (!session.waitingForInput?.allowMultiple && selectedOptions.length > 0) {
    const selectedIndex = answerItems.findIndex((item) => {
      const itemText =
        typeof item === "string" ? item : item.text || item.answerText;
      return (
        itemText && itemText.toLowerCase() === selectedOptions[0].toLowerCase()
      );
    });
    if (selectedIndex !== -1) {
      outputNum = (selectedIndex + 1).toString();
    }
  }

  const nextEdge = session.flowData.flowEdges.find(
    (edge) => edge.sourceNodeId === `${nodeId}_${outputNum}`
  );

  if (nextEdge) {
    const nextNodeId = String(nextEdge.targetNodeId).replace(/_\d+$/, "");
    const nextNode = session.flowData.flowNodes.find(
      (n) => n.id === nextNodeId
    );
    if (nextNode) {
      await executeFlowNode(
        nextNode,
        userNumber,
        phoneNumber,
        sock,
        appLocals,
        sessionKey
      );
    }
  }
}

/**
 * Group a List node's FLAT items into WhatsApp sections.
 *
 * The item's index in `listItems` IS its output port (p0, p1, …), so
 * grouping must never renumber: every row carries its `portIndex` home.
 * Items sharing a `section` heading merge into one group (first appearance
 * sets the order); items without one — and every flow saved before sections
 * existed — fall into a single group titled with the button text, a short
 * label INSIDE the dropdown rather than the full prompt, which would echo
 * the body a third time.
 *
 * Both the sender and the reply router call this, so the rendered order is
 * identical on the way out and on the way back.
 */
function buildListSections(node) {
  const items = Array.isArray(node.listItems) ? node.listItems : [];
  const sections = [];
  items.slice(0, 10).forEach((it, i) => {
    const heading = String(it.section || '').trim() || String(node.buttonText || 'Options');
    const row = {
      id:          `list_${node.id}_p${i}`,
      title:       String(it.title || `Option ${i + 1}`),
      description: it.description ? String(it.description) : undefined,
      portIndex:   i,
    };
    const existing = sections.find((s) => s.title === heading);
    if (existing) existing.rows.push(row);
    else sections.push({ title: heading, rows: [row] });
  });
  return sections;
}

// ✅ STEP 3: Handle question response
/**
 * List node reply router. Reads the WhatsApp listResponseMessage
 * `selectedRowId` (format "list_<nodeId>_<itemId>"), maps the itemId
 * back to a port number (p0→1, p1→2, …), and routes to the edge
 * with that source port.
 *
 * Falls back to a text-match on the row title if WhatsApp didn't
 * return a rowId (some carriers strip it on poor-quality channels).
 */
async function handleListResponse(message, session, nodeId, userNumber, phoneNumber, sock, appLocals, sessionKey) {
  // Six possible reply shapes WhatsApp can deliver for an interactive
  // button tap — depending on the client + carrier path:
  //   - interactiveResponseMessage.nativeFlowResponseMessage.paramsJson
  //   - templateButtonReplyMessage.selectedId / selectedDisplayText
  //   - listResponseMessage.singleSelectReply.selectedRowId / title
  //   - buttonsResponseMessage.selectedButtonId / selectedDisplayText
  //   - conversation (plain text fallback)
  //   - extendedTextMessage.text (plain text + quote)
  // Parse all six so the user's port choice routes correctly regardless
  // of which shape Baileys hands us.
  let nativeId    = "";
  const native    = message?.message?.interactiveResponseMessage
                       ?.nativeFlowResponseMessage;
  if (native?.paramsJson) {
    try {
      const parsed = JSON.parse(native.paramsJson);
      nativeId = String(parsed?.id || parsed?.ID || "");
    } catch {/* ignore malformed JSON */}
  }
  const tplId    = message?.message?.templateButtonReplyMessage?.selectedId || "";
  const rowId    = message?.message?.listResponseMessage?.singleSelectReply?.selectedRowId || "";
  const btnId    = message?.message?.buttonsResponseMessage?.selectedButtonId || "";
  const text     = String(
    message?.message?.conversation
    || message?.message?.extendedTextMessage?.text
    || message?.message?.templateButtonReplyMessage?.selectedDisplayText
    || message?.message?.listResponseMessage?.singleSelectReply?.title
    || message?.message?.buttonsResponseMessage?.selectedDisplayText
    || ""
  ).toLowerCase().trim();
  const idToken  = nativeId || tplId || rowId || btnId;

  // Resolve which port the user picked.
  //   - From any id token  → "list_<nodeId>_p<index>" → port = index + 1
  //   - From plain text    → "1"/"2"/… → port = number (rescue path)
  //   - Fallback           → text-match against items[].title
  let port = 0;
  const m = idToken.match(/_p(\d+)$/);
  if (m) {
    port = parseInt(m[1], 10) + 1;
  } else if (/^\d+$/.test(text)) {
    // Numbered rescue path — and Twilio's real reply path, since it degrades
    // the list to a numbered text menu. The digit is a position in the
    // RENDERED (section-grouped) order, which only equals the flat order
    // while sections stay contiguous. Map it back through the same grouping
    // so interleaved sections can't route to the wrong branch.
    const waitNode = session.flowData.flowNodes.find((n) => n.id === nodeId);
    const rendered = waitNode ? buildListSections(waitNode).flatMap((s) => s.rows) : [];
    const pick = parseInt(text, 10);
    port = rendered[pick - 1] ? rendered[pick - 1].portIndex + 1 : pick;
  } else {
    // Title match runs against the FLAT items — index is already the port.
    const waitNode = session.flowData.flowNodes.find((n) => n.id === nodeId);
    const items = Array.isArray(waitNode?.listItems) ? waitNode.listItems : [];
    const idx = items.findIndex((it) => String(it.title || "").toLowerCase().trim() === text);
    if (idx >= 0) port = idx + 1;
  }

  console.log(`[FLOW] list response node=${nodeId} nativeId="${nativeId}" tplId="${tplId}" rowId="${rowId}" btnId="${btnId}" text="${text}" port=${port}`);

  if (port <= 0) {
    // No list row matched the reply. Same rule as the buttons node: route to
    // the "else / no match" port (port 0) if wired, otherwise STAY on this
    // node rather than ENDING the session — ending silently dropped the
    // customer out of the whole flow on any stray message.
    const elseEdge = session.flowData.flowEdges.find(
      (edge) => edge.sourceNodeId === `${nodeId}_0`
    );
    if (elseEdge) {
      port = 0;
    } else {
      const waitNode = session.flowData.flowNodes.find((n) => n.id === nodeId);
      session.waitingForInput = {
        nodeId,
        variable: (waitNode && waitNode.variable) || "choice",
        listItems: (waitNode && waitNode.listItems) || [],
        nextNodeType: "List",
      };
      console.warn(`[FLOW] list response node=${nodeId} — reply matched no row and no else port; STAYING on node (not advancing).`);
      return;
    }
  }

  const nextEdge = session.flowData.flowEdges.find(
    (edge) => edge.sourceNodeId === `${nodeId}_${port}`
  );

  if (!nextEdge) {
    console.warn(`[FLOW] list response — no edge from ${nodeId}_${port}. Ending session.`);
    endFlowSession(appLocals, sessionKey);
    return;
  }

  const nextNodeId = String(nextEdge.targetNodeId).replace(/_\d+$/, "");
  const nextNode = session.flowData.flowNodes.find((n) => n.id === nextNodeId);
  if (!nextNode) {
    console.warn(`[FLOW] list response — edge target ${nextNodeId} not found. Ending session.`);
    endFlowSession(appLocals, sessionKey);
    return;
  }

  console.log(`[FLOW] list response → exec node=${nextNodeId} (${nextNode.flowNodeType})`);
  await executeFlowNode(nextNode, userNumber, phoneNumber, sock, appLocals, sessionKey);
}

async function handleQuestionResponse(
  userMessage,
  session,
  nodeId,
  answerItems,
  userNumber,
  phoneNumber,
  sock,
  appLocals,
  sessionKey,
  replyId = "",
  waitVariable = null
) {
  // null until a specific option is matched. Do NOT default to "1": that was
  // the reported bug — an unmatched random message ("Ciok") took the FIRST
  // button's port and advanced the flow. A choice node must only move on a
  // real choice.
  let outputNum = null;

  if (answerItems.length > 0) {
    let answerIndex = -1;
    // Interactive quick_reply / list taps echo the button/row ID we minted.
    // Match that against the option id FIRST - WhatsApp often returns only
    // { id } (no display_text), so a text-only match silently fell through
    // to port 1 and EVERY button sent the first branch.
    if (replyId) {
      answerIndex = answerItems.findIndex((it) => String(it.id) === String(replyId));
    }
    if (answerIndex === -1) {
      answerIndex = answerItems.findIndex(
        (item) => String(item.answerText || "").toLowerCase() === String(userMessage || "").toLowerCase()
      );
    }
    if (answerIndex === -1) {
      const idNum = String(replyId || "").match(/(\d+)\s*$/);
      if (idNum) { const k = parseInt(idNum[1], 10); if (k >= 0 && k < answerItems.length) answerIndex = k; }
    }
    if (answerIndex === -1) {
      const typed = parseInt(String(userMessage || "").trim(), 10);
      if (!isNaN(typed) && typed >= 1 && typed <= answerItems.length) answerIndex = typed - 1;
    }
    if (answerIndex !== -1) {
      outputNum = (answerIndex + 1).toString();
      // Prefer the option's human label for {{nodeId}} — WhatsApp frequently
      // echoes only the button/row ID (no display text), so userMessage above
      // would otherwise store a machine id like "n_xxx_1" instead of the label
      // the customer actually saw (e.g. "Pickup Request") in the webhook body.
      const _label = String(answerItems[answerIndex].answerText || "").trim();
      if (_label) session.userVariables[nodeId] = _label;
    }
  } else {
    // No preset options → this is a FREE-TEXT question ("Ask" node). Any answer
    // is valid, so it advances through the single output port, as before.
    outputNum = "1";
  }

  // ── Nothing matched a CHOICE node (buttons / quick-replies). ───────────────
  // This is the fix for "flow advances on any incoming message". Instead of
  // silently taking port 1, route unmatched input to the node's "else / no
  // match" port (port 0) when the operator wired one; otherwise STAY on this
  // node — re-arm the wait so the customer's next, real tap still routes, and
  // do NOT advance. The interactive buttons on the original message stay
  // tappable, so nothing needs re-sending.
  if (outputNum === null) {
    const elseEdge = session.flowData.flowEdges.find(
      (edge) => edge.sourceNodeId === `${nodeId}_0`
    );
    if (elseEdge) {
      outputNum = "0";
    } else {
      session.waitingForInput = {
        nodeId,
        variable: waitVariable || "user_message",
        answerItems,
        nextNodeType: "Question",
      };
      console.log(`[FLOW] question node=${nodeId} — reply "${String(userMessage || "").substring(0, 30)}" matched no option and no else port; STAYING on node (not advancing).`);
      return;
    }
  }

  const nextEdge = session.flowData.flowEdges.find(
    (edge) => edge.sourceNodeId === `${nodeId}_${outputNum}`
  );

  if (nextEdge) {
    const nextNodeId = String(nextEdge.targetNodeId).replace(/_\d+$/, "");
    const nextNode = session.flowData.flowNodes.find(
      (n) => n.id === nextNodeId
    );
    if (nextNode) {
      await executeFlowNode(
        nextNode,
        userNumber,
        phoneNumber,
        sock,
        appLocals,
        sessionKey
      );
    } else {

      endFlowSession(appLocals, sessionKey);
    }
  } else {
    // The customer picked a REAL option, but its port isn't wired to any node —
    // the operator left that branch empty (an unconnected button in the builder).
    // Ending the whole flow on a tap is jarring and reads as a "dead" bot. For a
    // CHOICE menu, STAY on it so the customer can pick a wired option; the menu
    // stays tappable and the node-scoped router keeps every button correct.
    // Free-text asks (no options) still end as before.
    const _stayNode = session.flowData.flowNodes.find((n) => n.id === nodeId);
    if (_stayNode && Array.isArray(_stayNode.answerItems) && _stayNode.answerItems.length) {
      session.status = "active";
      session.waitingForInput = {
        nodeId,
        variable: waitVariable || _stayNode.variable || "choice",
        answerItems: _stayNode.answerItems,
        nextNodeType: "Question",
        persistent: true,
      };
      appLocals.activeFlowSessions[sessionKey] = session;
      console.log(`[FLOW] question node=${nodeId} — option port ${outputNum} has no wired branch; staying on the menu (not ending).`);
      return;
    }
    endFlowSession(appLocals, sessionKey);
  }
}

// ✅ STEP 4: Main execute flow node function
export async function executeFlowNode(
  node,
  targetPhoneNumber,
  senderPhoneNumber,
  sock,
  appLocals,
  sessionKey = null
) {
  // Build session key if not provided
  if (!sessionKey) {
    sessionKey = `${senderPhoneNumber}_${targetPhoneNumber}`;
  }


  // ✅ CRITICAL FIX: Check appLocals exists
  if (!appLocals || !appLocals.activeFlowSessions) {

    return;
  }

  const session = appLocals.activeFlowSessions[sessionKey];
  if (!session) {


    return;
  }

  // ── Loop guard ────────────────────────────────────────────────────────────
  // A flow whose graph loops back to an earlier node with no exit — e.g. Abdul
  // Haq's "Ask location → Condition (is it a location?) → Send 'wrong location'
  // → edge back to Ask" — re-executes the SAME node on every customer reply and
  // never terminates. Deleting the flow / clearing the cache can't stop it: the
  // running session holds its own copy of flowData in memory. No legitimate flow
  // runs a single node this many times, so once a node crosses the cap we END
  // the session to break the loop (silently — no extra bubble to the customer).
  // Also stops a tight, no-wait Condition↔Send loop before it can flood or blow
  // the stack. Resets automatically: every flow (re)start mints a fresh session.
  const MAX_NODE_VISITS = 20;
  session.__nodeVisits = session.__nodeVisits || {};
  session.__nodeVisits[node.id] = (session.__nodeVisits[node.id] || 0) + 1;
  if (session.__nodeVisits[node.id] > MAX_NODE_VISITS) {
    console.warn(`[FLOW] loop-guard: node ${node.id} executed ${session.__nodeVisits[node.id]}× in session ${sessionKey} — ending flow to break an infinite loop. Fix the flow: this node's branch loops back to it without an exit (e.g. add a retry cap or an "else" port).`);
    endFlowSession(appLocals, sessionKey);
    return;
  }

  session.currentNodeId = node.id;
  const finalNumber = formatPhoneNumber(targetPhoneNumber);

  // Stash senderPhoneNumber + appDomainName on the session so the
  // 5-arg executors (executeButtonsNode, executeListNode, etc.) can
  // resolve WABA settings without changing every call site. Same
  // session is reused across the flow; we set this once.
  if (!session.__senderPhone)   session.__senderPhone   = senderPhoneNumber;
  if (!session.__appDomainName) session.__appDomainName = appLocals.appDomainName;

  // ── Anti-ban gates (Baileys only — WABA has Meta's own rate-limiting).
  // Flows can fire dozens of nodes per customer trigger; together with
  // campaigns + broadcasts those auto-replies need to share the daily
  // cap budget per device. Without this check a busy flow can blow
  // through the daily ceiling silently. WABA bypasses the gate via
  // session.__useFacebookApi (set inside the WABA-aware executors).
  const _useWaba = !!session.__useFacebookApi;
  if (!_useWaba && sock) {
    // Sock-liveness — if Baileys dropped between flow steps, skip the
    // node instead of throwing (a flow session is recoverable; throwing
    // here would leak unhandled rejections into the express handler).
    if (!isSockUsable(sock, appLocals, senderPhoneNumber)) {
      console.warn(`[FLOW] sock unusable for ${senderPhoneNumber} — node ${node.id} skipped, session ${sessionKey} paused`);
      session.__sockPaused = true;
      return;
    }
    // Daily cap — flows share the per-device budget with campaigns +
    // broadcasts. If the device has hit its 4k/day default, pause the
    // session so the operator notices instead of failing silently.
    if (dailyCapRemaining(appLocals, senderPhoneNumber) <= 0) {
      const cap = appLocals.messageSettings?.baileys_daily_cap || 4000;
      console.warn(`[FLOW] Baileys daily cap ${cap} reached for ${senderPhoneNumber} — flow paused at node ${node.id}`);
      session.__capPaused = true;
      return;
    }
  }

  // Single line per executed node — grep `[FLOW]` in node logs to follow
  // a session through the graph. Includes the type so an "Unknown node
  // type" warning is obvious, plus the resolved sock state which is the
  // most common cause of silent send failures.
  console.log(`[FLOW] exec node=${node.id} type=${node.flowNodeType} session=${sessionKey} sock=${sock ? 'YES' : 'NO'} target=${finalNumber}`);

  switch (node.flowNodeType) {
    case "Message":
      await executeMessageNode(
        node,
        finalNumber,
        sock,
        session,
        targetPhoneNumber,
        senderPhoneNumber,
        appLocals,
        sessionKey
      );
      break;
    case "Question":
      await executeQuestionNode(node, finalNumber, sock, session, appLocals);
      break;
    case "InteractiveButtons":
      await executeButtonsNode(node, finalNumber, sock, session, appLocals);
      break;
    case "List":
      await executeListNode(node, finalNumber, sock, session, appLocals);
      break;
    case "Condition":
      await executeConditionNode(
        node,
        targetPhoneNumber,
        senderPhoneNumber,
        sock,
        session,
        appLocals,
        sessionKey
      );
      break;
    case "TimeDelay":
      await executeTimeDelayNode(
        node,
        targetPhoneNumber,
        senderPhoneNumber,
        sock,
        session,
        appLocals,
        sessionKey
      );
      break;
    case "Poll":
      await executePollNode(node, finalNumber, sock, session, appLocals);
      break;
    case "Template":
      await executeTemplateNode(
        node,
        finalNumber,
        sock,
        session,
        targetPhoneNumber,
        senderPhoneNumber,
        appLocals,
        sessionKey
      );
      break;
    case "ChatGPT":
      await executeChatGPTNode(
        node,
        finalNumber,
        sock,
        session,
        targetPhoneNumber,
        senderPhoneNumber,
        appLocals,
        sessionKey
      );
      break;
    case "CTA":
      await executeCTANode(
        node,
        finalNumber,
        sock,
        session,
        targetPhoneNumber,
        senderPhoneNumber,
        appLocals,
        sessionKey
      );
      break;
    case "Location":
      await executeLocationNode(
        node,
        finalNumber,
        sock,
        session,
        targetPhoneNumber,
        senderPhoneNumber,
        appLocals,
        sessionKey
      );
      break;
    case "Chatbot":
      await executeChatbotNode(
        node,
        finalNumber,
        sock,
        session,
        targetPhoneNumber,
        senderPhoneNumber,
        appLocals,
        sessionKey
      );
      break;
    case "BookAppointment":
      await executeBookAppointmentNode(
        node,
        finalNumber,
        sock,
        session,
        targetPhoneNumber,
        senderPhoneNumber,
        appLocals,
        sessionKey
      );
      break;
    case "CommerceShop":
      await executeCommerceShopNode(
        node,
        finalNumber,
        sock,
        session,
        targetPhoneNumber,
        senderPhoneNumber,
        appLocals,
        sessionKey
      );
      break;
    case "Webhook":
      await executeWebhookNode(
        node, session, targetPhoneNumber, senderPhoneNumber,
        sock, appLocals, sessionKey,
      );
      break;
    case "Code":
      await executeCodeNode(node, session);
      // Code is non-interactive — it only runs JS + saves variables and does
      // NOT wait for a reply, so the flow must advance to the next node here.
      // executeCodeNode(node, session) doesn't receive the routing args, and
      // unlike every other passive node it never called moveToNextNode itself,
      // so the flow halted on it (client: stuck right after "code node ran ok").
      await moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
      break;
    case "MySQL":
      await executeMysqlNode(
        node, session, targetPhoneNumber, senderPhoneNumber,
        sock, appLocals, sessionKey,
      );
      break;
    case "TagContact":
      await executeTagContactNode(
        node, session, targetPhoneNumber, senderPhoneNumber,
        sock, appLocals, sessionKey,
      );
      break;
    case "AssignAgent":
      await executeAssignAgentNode(
        node, session, targetPhoneNumber, senderPhoneNumber,
        sock, appLocals, sessionKey,
      );
      break;
    case "SubFlow":
      // SubFlow is a thin alias on Chatbot — both swap session.flowData
      // to the referenced flow id and recurse. The builder exposes them
      // as separate visual nodes; the runtime handler is the same.
      await executeChatbotNode(
        node, finalNumber, sock, session,
        targetPhoneNumber, senderPhoneNumber, appLocals, sessionKey,
      );
      break;
    case "Media":
      await executeMediaNode(
        node, finalNumber, sock, session,
        targetPhoneNumber, senderPhoneNumber, appLocals, sessionKey,
      );
      break;
    case "GoogleMeet":
      await executeGoogleMeetNode(
        node, finalNumber, sock, session,
        targetPhoneNumber, senderPhoneNumber, appLocals, sessionKey,
      );
      break;
    case "WaForm":
      await executeWaFormNode(
        node, finalNumber, sock, session,
        targetPhoneNumber, senderPhoneNumber, appLocals, sessionKey,
      );
      break;
    case "GoogleSheets":
      await executeGoogleSheetsNode(
        node, finalNumber, sock, session,
        targetPhoneNumber, senderPhoneNumber, appLocals, sessionKey,
      );
      break;
    case "GoogleDocs":
      await executeGoogleDocsNode(
        node, finalNumber, sock, session,
        targetPhoneNumber, senderPhoneNumber, appLocals, sessionKey,
      );
      break;
    case "GoogleForm":
      await executeGoogleFormNode(
        node, finalNumber, sock, session,
        targetPhoneNumber, senderPhoneNumber, appLocals, sessionKey,
      );
      break;
    case "Deal":
      await executeDealNode(
        node, finalNumber, sock, session,
        targetPhoneNumber, senderPhoneNumber, appLocals, sessionKey,
      );
      break;
    // The builder's "End" node is TERMINAL ("Stop the flow here", no output
    // ports). It must END the session immediately — NOT fall through to the
    // default branch, which treats it as an unknown node and calls
    // moveToNextNode (advance). That advance is exactly why a flow "did not stop
    // after an option even with an End step added": reaching End tried to move
    // on instead of terminating. (Match lower-case too, defensively.)
    case "End":
    case "end":
      console.log(`[FLOW] End node=${node.id} — terminating session ${sessionKey}`);
      endFlowSession(appLocals, sessionKey);
      break;
    default:
      console.warn(`Unknown node type: ${node.flowNodeType}`);
      await moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  // Bump the per-device daily Baileys tally once per node dispatched
  // (Baileys path only — WABA path bypasses this). One bump per node
  // is the right granularity: a Message node fires one outbound; a
  // Buttons / List / Template node also fires one interactive bubble.
  // Multi-send nodes (rare) under-count slightly here — acceptable
  // because we'd rather under-count than over-count and falsely cap.
  // Customer-facing impact: flows share the same 4000/day/device
  // budget with campaigns + broadcasts, so a busy flow can't quietly
  // burn through Meta's ban radar.
  if (!_useWaba && !session.__sockPaused && !session.__capPaused) {
    const nodeFires = new Set(['Message','Question','InteractiveButtons','List','Poll','Template','ChatGPT','CTA','Location','Chatbot','BookAppointment','CommerceShop']);
    if (nodeFires.has(node.flowNodeType)) {
      bumpDailyTally(appLocals, senderPhoneNumber);
    }
  }
}

// ✅ STEP — Execute Book Appointment Node
// Fetches free slots from Laravel, renders them as a WhatsApp list
// message, and pauses the session waiting for the customer to pick
// one. When no slots are returned, branches to port 2 (no_slots) so
// the author can fall back to "call us" or similar.
//
// Endpoints (verified live against developers.google.com for the
// underlying Google Calendar calls):
//   GET  /api/appointments/slots?workspace_id=X&limit=N
//        → { ok, slots: [{ start, end, label }, ...] }
//   POST /api/appointments/book
//        body: { workspace_id, starts_at, ends_at, title, conversation_id,
//                customer_name, customer_phone, customer_email }
//        → { ok, appointment_id, status }
async function executeBookAppointmentNode(
  node,
  finalNumber,
  sock,
  session,
  targetPhoneNumber,
  senderPhoneNumber,
  appLocals,
  sessionKey
) {
  const appDomain  = appLocals.appDomainName;
  const workspaceId = session.flowData.workspace_id || node.workspace_id || null;
  const limit       = Math.max(1, Math.min(10, Number(node.slotCount) || 5));
  const prompt      = String(node.prompt || "Pick a time that works for you:");
  const confirmation = String(node.confirmation || "Booked! See you on {{slot}}.");

  let slots = [];
  try {
    // X-Node-Token — slotsApi accepts Node-token OR Laravel session;
    // the Node runtime has no session, so omitting the header returned
    // 401 → empty slots → flow always took the no_slots branch.
    const url = `${appDomain}/api/appointments/slots?workspace_id=${encodeURIComponent(workspaceId || "")}&limit=${limit}`;
    const resp = await axios.get(url, {
      timeout: 10000,
      headers: { 'X-Node-Token': process.env.NODE_WEBHOOK_TOKEN || '' },
    });
    slots = (resp.data && resp.data.slots) || [];
  } catch (e) {
    console.warn(`[${senderPhoneNumber}] BookAppointment slots fetch failed: ${e?.message}`);
  }

  // No slots → fall through the "no_slots" port (sourceNodeId X_2).
  if (!Array.isArray(slots) || slots.length === 0) {
    await advanceToPort(
      node.id, 2,
      session.flowData, targetPhoneNumber, senderPhoneNumber,
      sock, appLocals, sessionKey,
    );
    return;
  }

  // WABA branch — Meta's interactive.list is the right shape for
  // up to 10 slots. Pre-fix this whole node was Baileys-only;
  // WABA workspaces silently dropped the appointment offer.
  const settings = await resolveWabaSettings(session, appDomain, senderPhoneNumber);
  // Engine flag — true for WABA Cloud OR Twilio (both go through their
  // REST APIs, not the local Baileys sock). The sendWaba* helpers
  // dispatch internally based on settings, so we only need a single
  // bool for "use remote API" at each call site. Renamed from useWaba
  // → useRemote in 2026-05-27 audit but kept the var name for ABI
  // compatibility with the 13 existing call sites.
  const useWaba  = settings && (
    (settings.use_facebook_api && settings.facebook_phone_id) ||
    (settings.use_twilio && settings.twilio_account_sid)
  );

  // Send slots — WABA uses interactive.list (up to 10 rows),
  // Baileys uses quick_reply buttons. Cap to 10 either way.
  try {
    if (useWaba) {
      const sections = [{
        title: 'Available slots',
        rows: slots.slice(0, 10).map((s, i) => ({
          id:    `appt_${node.id}_${i}`,
          title: String(s.label || s.start || `Slot ${i + 1}`).slice(0, 24),
          description: s.start && s.end ? `${s.start} → ${s.end}`.slice(0, 72) : undefined,
        })),
      }];
      const r = await sendWabaList(finalNumber, prompt, 'Pick a time', sections, settings, {});
      if (!r.success) throw new Error(r.error || 'WABA list send failed');
      console.log(`[FLOW] book-appointment SENT via WABA (${sections[0].rows.length} slots)`);
    } else {
      const interactiveButtons = slots.slice(0, 10).map((s, i) => ({
        name: 'quick_reply',
        buttonParamsJson: JSON.stringify({
          display_text: String(s.label || s.start || `Slot ${i + 1}`).slice(0, 20),
          id:           `appt_${node.id}_${i}`,
        }),
      }));
      console.log(`[FLOW] book-appointment → interactive(quick_reply x ${interactiveButtons.length})`);
      // Baileys interactive: footer is set via `footer` field per Baileys spec.
      const _aptFooter = _interactiveFooter(settings);
      await flowSockSend(sock, settings, finalNumber, { text: prompt, interactiveButtons, ...(_aptFooter ? { footer: _aptFooter } : {}) });
    }
    session.messageHistory.push({
      type: "sent", message: `${prompt} (${slots.length} slots offered)`,
      timestamp: moment().format(),
    });
  } catch (e) {
    console.warn(`[${senderPhoneNumber}] BookAppointment list send failed: ${e?.message}`);
    // If the send itself failed, treat as no_slots so the customer
    // doesn't get stuck.
    await advanceToPort(
      node.id, 2,
      session.flowData, targetPhoneNumber, senderPhoneNumber,
      sock, appLocals, sessionKey,
    );
    return;
  }

  session.waitingForInput = {
    nodeId:       node.id,
    variable:     "appointment_choice",
    nextNodeType: "BookAppointment",
    slots,
    workspaceId,
    confirmation,
  };
}

/**
 * Commerce Shop — three providers (whatsapp_shop / woocommerce /
 * shopify) all funnel through this one executor. Strategy:
 *
 *   - For every node we send one image+text card per product. Baileys
 *     has no native catalog UI, so the customer sees a stack of cards
 *     they can scroll through.
 *   - After the stack, we send a numbered prompt ("reply 1 to pick…")
 *     and pause the session. When the customer replies with a number
 *     (or product name), the reply handler picks that product, calls
 *     Laravel's /api/commerce/checkout-link with the picked retailer_id,
 *     and shoots the resulting URL back to the customer.
 *   - If the customer doesn't reply within abandonedWaitMinutes, the
 *     session auto-routes through the `abandoned` port (sourceNodeId_2)
 *     so the flow can branch into a drip / follow-up.
 *
 * For WABA conversations (executor sees that via appLocals) we could
 * lift this to a native `product_list` interactive message — left as a
 * follow-up. The card-stack path works for BOTH providers today.
 */
async function executeCommerceShopNode(
  node,
  finalNumber,
  sock,
  session,
  targetPhoneNumber,
  senderPhoneNumber,
  appLocals,
  sessionKey,
) {
  const provider     = String(node.provider || "woocommerce");
  const allItems     = Array.isArray(node.productItems) ? node.productItems : [];
  const workspaceId  = session.flowData?.workspace_id || null;
  const headerText   = String(node.headerText || "").trim();
  const bodyText     = String(node.bodyText   || "Tap a product to see details:").trim();
  const footerText   = String(node.footerText || "").trim();
  const appDomain    = appLocals.appDomainName;
  const nodeToken    = process.env.NODE_WEBHOOK_TOKEN || "";
  // Needed so flowSockSend() can mirror this node's product cards into the inbox.
  const settings     = await resolveWabaSettings(session, appLocals.appDomainName, senderPhoneNumber);

  // Workspace id missing from the normalized flow_data means the owning
  // user has no current_workspace_id — Laravel validates workspace_id as
  // required|integer, so every commerce call would 422. Short-circuit to
  // the abandoned port rather than letting the customer see "sorry,
  // couldn't generate a link" three times in a row.
  if (!workspaceId) {
    console.warn(`[CommerceShop] no workspace_id on flow_data — taking abandoned port`);
    await advanceToPort(
      node.id, 2,
      session.flowData, targetPhoneNumber, senderPhoneNumber,
      sock, appLocals, sessionKey,
    );
    return;
  }

  if (allItems.length === 0) {
    await advanceToPort(
      node.id, 2,
      session.flowData, targetPhoneNumber, senderPhoneNumber,
      sock, appLocals, sessionKey,
    );
    return;
  }

  // ───────── 1. Real-time inventory check ─────────
  // Filter the design-time list down to whatever's actually in stock
  // right now. Fail-open: if the check itself errors we ship the whole
  // list rather than killing the customer's experience.
  let items = allItems;
  try {
    const ids = allItems.map(p => p.retailer_id).filter(Boolean);
    const inv = await axios.post(
      `${appDomain}/api/commerce/check-inventory`,
      { provider, store_id: Number(node.storeId), workspace_id: workspaceId, retailer_ids: ids },
      { timeout: 8000, headers: { "X-Node-Token": nodeToken } },
    );
    if (inv.data?.ok && Array.isArray(inv.data.in_stock) && !inv.data.fallback) {
      const ok = new Set(inv.data.in_stock.map(String));
      items = allItems.filter(p => ok.has(String(p.retailer_id)));
    }
  } catch (e) {
    console.warn(`[CommerceShop] inventory check skipped: ${e?.message}`);
  }
  // Every product OOS → abandoned port (don't send a useless bubble).
  if (items.length === 0) {
    console.log(`[CommerceShop] all products OOS, taking abandoned port`);
    await advanceToPort(
      node.id, 2,
      session.flowData, targetPhoneNumber, senderPhoneNumber,
      sock, appLocals, sessionKey,
    );
    return;
  }

  // ───────── 2. WABA-native fast path ─────────
  // The conversation lives on a WABA device when there's no Baileys
  // sock for this sender. Hand off to Laravel which builds the
  // interactive product_list / product payload and POSTs to Meta.
  // The customer sees a native catalog tile with in-app cart +
  // checkout. We pause the session here too so the order webhook
  // can resume through the `purchased` port.
  const isWaba = !sock; // Baileys sock is keyed by sender device; missing sock = WABA conv
  if (isWaba) {
    try {
      const resp = await axios.post(
        `${appDomain}/api/commerce/waba-send-products`,
        {
          session_id:   sessionKey,
          target_phone: targetPhoneNumber,
          workspace_id: workspaceId,
          items:        items.map(p => ({ retailer_id: p.retailer_id })),
          header:       headerText || undefined,
          body:         bodyText   || undefined,
          footer:       footerText || undefined,
        },
        { timeout: 15000, headers: { "X-Node-Token": nodeToken } },
      );
      if (!resp.data?.ok) {
        console.warn(`[CommerceShop] WABA send failed: ${JSON.stringify(resp.data).slice(0, 200)}`);
        // Fall through to Baileys card stack — except we can't, no sock.
        await advanceToPort(node.id, 2, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
        return;
      }
    } catch (e) {
      console.warn(`[CommerceShop] WABA send exception: ${e?.message}`);
      await advanceToPort(node.id, 2, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
      return;
    }

    // Park the session — the catalog orders webhook will resume us
    // through port 1 (purchased), or the timer fires port 2.
    session.waitingForInput = {
      nodeId:       node.id,
      variable:     "commerce_choice",
      nextNodeType: "CommerceShop",
      provider,
      storeId:      String(node.storeId || ""),
      items,
      abandonedWaitMinutes: Number(node.abandonedWaitMinutes || 5),
      isWaba:       true,
      startedAt:    Date.now(),
    };
    const abandonMs = Math.max(1, Number(node.abandonedWaitMinutes || 5)) * 60 * 1000;
    setTimeout(async () => {
      const sess = appLocals.activeFlowSessions[sessionKey];
      if (!sess || !sess.waitingForInput || sess.waitingForInput.nodeId !== node.id) return;
      sess.waitingForInput = null;
      // Re-resolve sock at fire time — the Baileys client may have
      // reconnected during the wait window, making the captured
      // reference stale. WABA path has no sock anyway.
      const freshSock = appLocals.clients?.[senderPhoneNumber] || null;
      try { await advanceToPort(node.id, 2, sess.flowData, targetPhoneNumber, senderPhoneNumber, freshSock, appLocals, sessionKey); }
      catch (e) { console.warn(`[CommerceShop] WABA abandoned advance failed: ${e?.message}`); }
    }, abandonMs);
    return;
  }

  // ───────── 3. Baileys card-stack path ─────────
  // Optional preamble bubble (header + body) before the cards stream.
  if (headerText || bodyText) {
    const lines = [];
    if (headerText) lines.push("*" + headerText + "*");
    if (bodyText)   lines.push(bodyText);
    await flowSockSend(sock, settings, finalNumber, { text: lines.join("\n\n") });
  }

  // One image + caption per product. We sleep ~700ms between sends so
  // the customer's client doesn't merge them into a single notification.
  for (let i = 0; i < items.length; i++) {
    const p = items[i];
    const caption = formatProductCaption(i + 1, p);
    try {
      if (p.image) {
        await flowSockSend(sock, settings, finalNumber, { image: { url: p.image }, caption });
      } else {
        await flowSockSend(sock, settings, finalNumber, { text: caption });
      }
    } catch (e) {
      console.warn(`[CommerceShop] product send failed: ${e?.message}`);
    }
    await new Promise(r => setTimeout(r, 700));
  }

  // Reply prompt — customer types the number to pick. Include the
  // product name on each line so they don't have to scroll back to
  // remember which is which (the cards are above this bubble).
  const promptLines = [
    "Reply with the number of the product you want to buy:",
    items.map((p, i) => `  ${i + 1}. ${p.name || p.retailer_id}`).join("\n"),
  ];
  if (footerText) promptLines.push("_" + footerText + "_");
  await flowSockSend(sock, settings, finalNumber, { text: promptLines.join("\n") });

  // Pause the session — the customer's next text reply goes through
  // the runtime's input-capture path which checks waitingForInput.
  // When it sees `nextNodeType: "CommerceShop"` it calls back into
  // this file's reply handler (resolveCommerceShopReply, below).
  session.waitingForInput = {
    nodeId:        node.id,
    variable:      "commerce_choice",
    nextNodeType:  "CommerceShop",
    provider,
    storeId:       String(node.storeId || ""),
    items,
    abandonedWaitMinutes: Number(node.abandonedWaitMinutes || 5),
    startedAt:     Date.now(),
  };

  // Auto-abandon timer — same `app()->terminating`-style pattern as
  // the appointment AI fallback. After N minutes with no reply we
  // route through port 2 (abandoned). We DON'T cancel the session;
  // if the customer eventually replies later, the resolveCommerceShopReply
  // handler still mints them a link.
  const abandonMs = Math.max(1, Number(node.abandonedWaitMinutes || 5)) * 60 * 1000;
  setTimeout(async () => {
    const sess = appLocals.activeFlowSessions[sessionKey];
    if (!sess || !sess.waitingForInput) return;
    if (sess.waitingForInput.nodeId !== node.id) return;
    if (sess.waitingForInput.nextNodeType !== "CommerceShop") return;
    sess.waitingForInput = null;
    // Re-resolve sock at fire time — Baileys may have reconnected
    // during the wait window, making the captured sock reference
    // stale. advanceToPort + downstream nodes handle a null sock
    // by skipping sends but still walking the flow graph.
    const freshSock = appLocals.clients?.[senderPhoneNumber] || null;
    try {
      await advanceToPort(
        node.id, 2,
        sess.flowData, targetPhoneNumber, senderPhoneNumber,
        freshSock, appLocals, sessionKey,
      );
    } catch (e) {
      console.warn(`[CommerceShop] abandoned-port advance failed: ${e?.message}`);
    }
  }, abandonMs);
}

/**
 * Walked by the runtime when waitingForInput.nextNodeType === 'CommerceShop'
 * and a customer text reply lands. Returns true if it handled the reply.
 */
async function resolveCommerceShopReply(message, session, userNumber, senderPhoneNumber, sock, appLocals, sessionKey) {
  const w = session.waitingForInput;
  if (!w || w.nextNodeType !== "CommerceShop") return false;

  // WABA conversations land in the catalog cart, not this text-reply
  // path. If we somehow got here without a Baileys sock, bail BEFORE
  // touching session state or sendMessage. Otherwise the re-prompt
  // and link-send below would crash with "sock is null".
  if (!sock) {
    console.warn(`[CommerceShop] resolveReply called without sock — likely WABA conv typing instead of using cart. Skipping.`);
    return false;
  }

  const text = String(
    message.message?.conversation
    || message.message?.extendedTextMessage?.text
    || ""
  ).trim();
  if (text === "") return false;

  // If a link was already minted for this session, the customer is
  // chatting after pickup (e.g. "thanks!"). Don't re-process — wait
  // for the webhook (purchased) or abandon timer (abandoned) to
  // close the loop. Silently swallow so the bot doesn't yap.
  if (w.linkSent) return true;

  // Try numeric pick first, then fall back to fuzzy name match.
  const items = Array.isArray(w.items) ? w.items : [];
  let idx = -1;
  const n = parseInt(text, 10);
  if (!Number.isNaN(n) && n >= 1 && n <= items.length) idx = n - 1;
  if (idx < 0) {
    const lc = text.toLowerCase();
    idx = items.findIndex(p => (p.name || "").toLowerCase().includes(lc));
  }
  if (idx < 0) {
    // Didn't understand — re-prompt and keep waiting.
    await sock.sendMessage(formatPhoneNumber(userNumber), {
      text: `Sorry, I didn't catch that. Reply with a number from 1 to ${items.length}.`,
    });
    return true;
  }

  const picked = items[idx];
  // Stash the choice but DON'T null waitingForInput — the webhook (via
  // resumePort) needs to find nextNodeType === "CommerceShop" to
  // advance the purchased port. Marking linkSent=true makes future
  // text replies a no-op while the abandon timer keeps ticking.
  //
  // Write to userVariables (canonical) AND vars (back-compat) so the
  // downstream merge-tag substitution finds {{commerce_choice}} no
  // matter which getter the executor uses.
  session.userVariables = session.userVariables || {};
  session.userVariables.commerce_choice = picked.retailer_id;
  session.vars = session.vars || {};
  session.vars.commerce_choice = picked.retailer_id;

  // Mint the checkout link via Laravel.
  const appDomain = appLocals.appDomainName;
  const workspaceId = session.flowData?.workspace_id || null;
  let url = null;
  try {
    const resp = await axios.post(
      `${appDomain}/api/commerce/checkout-link`,
      {
        provider:       w.provider,
        store_id:       Number(w.storeId),
        workspace_id:   workspaceId,
        items:          [{ retailer_id: picked.retailer_id, qty: 1 }],
        session_id:     sessionKey,
        customer_phone: userNumber,
      },
      {
        timeout: 15000,
        headers: { "X-Node-Token": process.env.NODE_WEBHOOK_TOKEN || "" },
      },
    );
    if (resp.data && resp.data.ok && resp.data.url) url = resp.data.url;
  } catch (e) {
    console.warn(`[CommerceShop] checkout-link mint failed: ${e?.message}`);
  }

  if (!url) {
    await sock.sendMessage(formatPhoneNumber(userNumber), {
      text: `Sorry, couldn't generate a checkout link right now. Please try again in a moment.`,
    });
    // Don't advance; let the abandon timer handle it.
    return true;
  }

  // Send the link. WhatsApp auto-linkifies https://; the customer taps
  // and lands on the provider's hosted checkout.
  await sock.sendMessage(formatPhoneNumber(userNumber), {
    text: `Thanks! Complete your order here:\n${url}\n\nThis link is single-use.`,
  });

  // Mark the wait as "link sent" so further customer texts get swallowed
  // (see guard at top). The waiting state itself stays in place — that's
  // what lets resumePort advance the purchased port when the order
  // webhook lands, and what keeps the abandoned timer ticking if it
  // never does.
  w.linkSent = true;
  return true;
}

function formatProductCaption(num, p) {
  const lines = [];
  lines.push(`*${num}. ${p.name || p.retailer_id}*`);
  if (p.price_minor) {
    const cur = p.currency || "";
    lines.push(`${cur} ${(p.price_minor / 100).toFixed(2)}`);
  }
  lines.push(`_Reply '${num}' to buy this one._`);
  return lines.join("\n");
}

/**
 * Walk session.flowData.flowEdges looking for the edge that originates
 * at `sourceNodeId_<port>` — used by multi-port nodes (Condition,
 * BookAppointment) to take a specific branch.
 */
// Public alias so flowController (resumePort) can advance a paused
// commerce session through a specific port from outside flowService.
export async function advanceFlowToPort(...args) {
  return advanceToPort(...args);
}

async function advanceToPort(
  sourceNodeId, port,
  flowData, targetPhoneNumber, senderPhoneNumber,
  sock, appLocals, sessionKey,
) {
  const edge = flowData.flowEdges.find(
    (e) => e.sourceNodeId === `${sourceNodeId}_${port}`,
  );
  if (!edge) {
    endFlowSession(appLocals, sessionKey);
    return;
  }
  const nextId   = String(edge.targetNodeId).replace(/_\d+$/, "");
  const nextNode = flowData.flowNodes.find((n) => n.id === nextId);
  if (!nextNode) { endFlowSession(appLocals, sessionKey); return; }
  await executeFlowNode(
    nextNode, targetPhoneNumber, senderPhoneNumber,
    sock, appLocals, sessionKey,
  );
}

// ✅ STEP 5: Execute Message Node
// Replace {{var}} placeholders in a string with the flow session's variables
// (e.g. {{name}}, {{order_id}}). Missing vars render as empty. Case-tolerant on
// the key. Never mutates the source — returns a new string.
/**
 * Resolve a flow variable path against session.userVariables.
 *
 * Nodes that save a record (Google Sheets Read, AI extract) stash BOTH the raw
 * object under `saveAs` AND one-level-flattened `saveAs.column` keys, so the
 * flat hit below covers the common case and keeps the old behaviour byte-for-
 * byte. The nested walk is what makes deeper paths (`customer.address.city`)
 * and object-only saves resolve at all.
 */
export function _flowResolveVar(session, path) {
  const sv = (session && session.userVariables) || {};
  // Own properties ONLY. A plain `cur[seg]` walk reaches Object.prototype, so
  // a condition on `customer.toString` would resolve to a function and report
  // the variable as "set".
  const own = (o, k) => o !== null && typeof o === 'object' && Object.prototype.hasOwnProperty.call(o, k);

  // Authors paste "{{customer.Customer_ID}}" into the Variable box because
  // that is exactly how they reference it in a Message node. Accept it.
  const key = String(path ?? '').trim().replace(/^\{\{\s*/, '').replace(/\s*\}\}$/, '').trim();
  if (key === '') return undefined;

  if (own(sv, key)) return sv[key];
  const lower = key.toLowerCase();
  if (own(sv, lower)) return sv[lower];

  let cur = sv;
  for (const rawSeg of key.split('.')) {
    const seg = rawSeg.trim();   // tolerate "customer . Customer_ID"
    if (cur === null || typeof cur !== 'object') return undefined;
    if (own(cur, seg)) { cur = cur[seg]; continue; }
    const hit = Object.keys(cur).find((k) => k.toLowerCase() === seg.toLowerCase());
    if (hit === undefined) return undefined;
    cur = cur[hit];
  }
  return cur;
}

/** "Is set" semantics: present AND non-empty — string, array and object aware. */
function _flowIsSet(v) {
  if (v === undefined || v === null) return false;
  if (typeof v === 'string') return v.trim() !== '';
  if (Array.isArray(v)) return v.length > 0;
  if (typeof v === 'object') return Object.keys(v).length > 0;
  return true;
}

/**
 * A whole row renders/compares as JSON — never the useless "[object Object]".
 * Arrays keep String()'s comma-join: that is what flows already print today.
 */
function _flowStr(v) {
  if (v === undefined || v === null) return '';
  if (typeof v === 'object' && !Array.isArray(v)) {
    try { return JSON.stringify(v); } catch (e) { return String(v); }
  }
  return String(v);
}

export function _flowSubst(session, s) {
  return String(s ?? '').replace(/\{\{\s*([^{}]+?)\s*\}\}/g, (_, k) =>
    _flowStr(_flowResolveVar(session, k)));
}

async function executeMessageNode(
  node,
  finalNumber,
  sock,
  session,
  targetPhoneNumber,
  senderPhoneNumber,
  appLocals,
  sessionKey
) {
  if (!node.flowReplies || node.flowReplies.length === 0) {
    console.log(`[FLOW] message node=${node.id} has no flowReplies — just advancing`);
    await moveToNextNode(
      node.id,
      session.flowData,
      targetPhoneNumber,
      senderPhoneNumber,
      sock,
      appLocals,
      sessionKey
    );
    return;
  }
  console.log(`[FLOW] message node=${node.id} sending ${node.flowReplies.length} replies`);

  // Resolve provider settings ONCE per session. On a Cloud API or
  // Twilio workspace sock is null — every sock.sendMessage call would
  // silently throw. The three-way router below picks WABA / Twilio /
  // Baileys based on which credentials Laravel returned.
  const settings = await resolveWabaSettings(session, appLocals.appDomainName, senderPhoneNumber);
  const engine = resolveEngine(settings); // 'waba' | 'twilio' | 'baileys'

  for (const reply of node.flowReplies) {
    // Personalize {{name}}, {{order_id}}, … from the session vars. Text uses the
    // substituted body; media URLs are left as-is (no vars) but captions are
    // personalized. Local copies — never mutates the (possibly cached) flow.
    const text    = (typeof reply.data === 'string')    ? _flowSubst(session, reply.data)    : reply.data;
    const caption = (typeof reply.caption === 'string') ? _flowSubst(session, reply.caption) : reply.caption;
    try {
      if (reply.flowReplyType === "Text") {
        console.log(`[FLOW] message → text len=${(text || '').length} via=${engine}`);
        if (engine === 'waba') {
          await sendWabaText(finalNumber, text, settings);
        } else if (engine === 'twilio') {
          await sendTwilioText(targetPhoneNumber, text, settings);
        } else if (sock) {
          // Plain-text Baileys send — apply the plan-gated brand footer
          // here too (WABA path already does via the helper).
          await flowSockSend(sock, settings, finalNumber, { text: _appendFooterToText(text, settings) });
        }
        session.messageHistory.push({ type: "sent", message: text, timestamp: moment().format() });
      } else if (reply.flowReplyType === "Image") {
        if (engine === 'waba') {
          await sendWabaMedia(finalNumber, 'image', reply.data, caption, null, settings);
        } else if (engine === 'twilio') {
          await sendTwilioMedia(targetPhoneNumber, 'image', reply.data, caption, null, settings);
        } else if (sock) {
          const msgPayload = { image: { url: reply.data } };
          if (caption) msgPayload.caption = _appendFooterToText(caption, settings);
          await flowSockSend(sock, settings, finalNumber, msgPayload);
        }
      } else if (reply.flowReplyType === "Video") {
        if (engine === 'waba') {
          await sendWabaMedia(finalNumber, 'video', reply.data, caption, null, settings);
        } else if (engine === 'twilio') {
          await sendTwilioMedia(targetPhoneNumber, 'video', reply.data, caption, null, settings);
        } else if (sock) {
          const msgPayload = { video: { url: reply.data } };
          if (caption) msgPayload.caption = _appendFooterToText(caption, settings);
          await flowSockSend(sock, settings, finalNumber, msgPayload);
        }
      } else if (reply.flowReplyType === "Audio") {
        if (engine === 'waba') {
          await sendWabaMedia(finalNumber, 'audio', reply.data, null, null, settings);
        } else if (engine === 'twilio') {
          await sendTwilioMedia(targetPhoneNumber, 'audio', reply.data, null, null, settings);
        } else if (sock) {
          // Audio has no caption — Baileys can't carry the footer here.
          await flowSockSend(sock, settings, finalNumber, { audio: { url: reply.data }, mimetype: "audio/mpeg" });
        }
      } else if (reply.flowReplyType === "Document") {
        if (engine === 'waba') {
          await sendWabaMedia(finalNumber, 'document', reply.data, caption, reply.filename, settings);
        } else if (engine === 'twilio') {
          await sendTwilioMedia(targetPhoneNumber, 'document', reply.data, caption, reply.filename, settings);
        } else if (sock) {
          const msgPayload = { document: { url: reply.data }, mimetype: reply.mimetype || "application/pdf" };
          if (reply.filename) msgPayload.fileName = reply.filename;
          if (caption) msgPayload.caption = _appendFooterToText(caption, settings);
          await flowSockSend(sock, settings, finalNumber, msgPayload);
        }
      }
    } catch (err) {
      console.error(`[FLOW] send FAILED type=${reply.flowReplyType} to=${finalNumber}: ${err?.message}`);
    }
    await new Promise((resolve) => setTimeout(resolve, 1000));
  }

  await moveToNextNode(
    node.id,
    session.flowData,
    targetPhoneNumber,
    senderPhoneNumber,
    sock,
    appLocals,
    sessionKey
  );
}

// ✅ STEP 6: Move to next node helper
async function moveToNextNode(
  currentNodeId,
  flowData,
  targetPhoneNumber,
  senderPhoneNumber,
  sock,
  appLocals,
  sessionKey
) {
  const sourceKey = `${currentNodeId}_1`;
  const nextEdge = flowData.flowEdges.find(
    (edge) => edge.sourceNodeId === sourceKey
  );

  if (nextEdge) {
    // The normalizer appends `_<port>` to make edge ids (e.g. `n_abc_1`).
    // Node IDs themselves CONTAIN underscores (`n_<random>`), so naive
    // `.split('_')[0]` returns just `n` and the next-node lookup fails.
    // Strip ONLY the trailing port suffix.
    const nextNodeId = String(nextEdge.targetNodeId).replace(/_\d+$/, "");
    const nextNode = flowData.flowNodes.find((n) => n.id === nextNodeId);
    if (nextNode) {
      console.log(`[FLOW] moveToNextNode from=${currentNodeId} via ${sourceKey} → ${nextNodeId} (${nextNode.flowNodeType})`);
      await new Promise((resolve) => setTimeout(resolve, 1500));
      await executeFlowNode(
        nextNode,
        targetPhoneNumber,
        senderPhoneNumber,
        sock,
        appLocals,
        sessionKey
      );
    } else {
      console.warn(`[FLOW] moveToNextNode edge target ${nextNodeId} not found in flowNodes — ending session`);
      endFlowSession(appLocals, sessionKey);
    }
  } else {
    console.log(`[FLOW] moveToNextNode no edge from ${sourceKey} — flow ended`);
    endFlowSession(appLocals, sessionKey);
  }
}

// ✅ STEP 7: End flow session and set cooldown
function endFlowSession(appLocals, sessionKey) {
  if (!appLocals || !appLocals.activeFlowSessions) {

    return;
  }

  const session = appLocals.activeFlowSessions[sessionKey];
  if (!session) {
    console.warn(`⚠️ Session ${sessionKey} already ended`);
    return;
  }


  // Clear timeout timer
  if (session.timeoutTimer) {
    clearTimeout(session.timeoutTimer);
    session.timeoutTimer = null;
  }

  // Set cooldown
  if (session.cooldownSeconds && session.cooldownSeconds > 0) {
    const endTime = Date.now() + (session.cooldownSeconds * 1000);
    appLocals.userCooldowns = appLocals.userCooldowns || {};
    appLocals.userCooldowns[sessionKey] = {
      endTime,
      cooldownSeconds: session.cooldownSeconds
    };

  }

  // Mark as completed
  session.status = "completed";
  session.waitingForInput = null;

  // Clean up session after delay — but only if it's STILL completed. A
  // persistent template menu re-activates this same session (re-shows the
  // template + re-arms waitingForInput) within this window; deleting it then
  // would drop the live menu out from under the customer's next tap.
  setTimeout(() => {
    const s = appLocals.activeFlowSessions[sessionKey];
    if (s && s.status === "completed") {
      delete appLocals.activeFlowSessions[sessionKey];
    }
  }, 5000);
}

/**
 * End every active flow session for a customer phone. Called by Laravel
 * (POST /api/flow-end) when an operator manually attaches an AI agent /
 * assistant to a conversation in the team inbox — so a running flow stops
 * and the AI cleanly takes over (instead of both replying to the customer).
 * The customer's number is one half of the `${phoneNumber}_${userNumber}`
 * sessionKey; match either half, tolerant of country-code prefixes.
 * Returns the count of sessions ended.
 */
export function endFlowSessionsForPhone(appLocals, customerPhone) {
  const phone = String(customerPhone || "").replace(/\D+/g, "");
  if (!phone || !appLocals || !appLocals.activeFlowSessions) return 0;
  let ended = 0;
  for (const key of Object.keys(appLocals.activeFlowSessions)) {
    const parts = String(key).split("_").map((p) => p.replace(/\D+/g, ""));
    const hit = parts.some((p) => p && (p === phone || p.endsWith(phone) || phone.endsWith(p)));
    if (hit) {
      endFlowSession(appLocals, key);
      ended++;
    }
  }
  return ended;
}

// ✅ STEP 8: Execute Time Delay Node
async function executeTimeDelayNode(
  node,
  targetPhoneNumber,
  senderPhoneNumber,
  sock,
  session,
  appLocals,
  sessionKey
) {
  // FlowNormalizer normalizes the builder's `{amount, unit}` into a
  // single `delaySeconds` scalar. The old executor read `node.delay`
  // + `node.unit` — both undefined under the new normalizer → 0ms wait
  // → flow blasted through every delay instantly.
  // Accept the normalized field first; fall back to legacy.
  let delayMs = 0;
  if (typeof node.delaySeconds === "number" && node.delaySeconds > 0) {
    delayMs = node.delaySeconds * 1000;
  } else {
    const delayAmount = node.delay || node.amount || 0;
    const delayUnit   = String(node.unit || "seconds").toLowerCase();
    delayMs = delayAmount * (
      delayUnit === "min" || delayUnit === "minute" || delayUnit === "minutes" ? 60_000 :
      delayUnit === "hour" || delayUnit === "hours" ? 3_600_000 :
      delayUnit === "day"  || delayUnit === "days"  ? 86_400_000 :
      1_000
    );
  }
  console.log(`[FLOW] delay node=${node.id} ${delayMs}ms`);
  await new Promise((resolve) => setTimeout(resolve, delayMs));


  await moveToNextNode(
    node.id,
    session.flowData,
    targetPhoneNumber,
    senderPhoneNumber,
    sock,
    appLocals,
    sessionKey
  );
}

/**
 * Evaluate ONE condition rule against the session's variables.
 *
 * The builder (user-flows-builder.js opChoices) emits `exists`, `gt` and `lt`;
 * this switch only knew `greater_than`/`less_than` and had no `exists` at all,
 * so those three hit `default: false` and could NEVER be true — "Is set" was
 * dead on every flow ever built. Accept both vocabularies.
 */
function _flowEvalCondition(session, c, nodeId) {
  const op = String(c?.operator || "equals").toLowerCase().trim().replace(/\s+/g, "_");
  const resolved = _flowResolveVar(session, c?.variable);

  // Presence tests read the variable ITSELF, skipping the user_message
  // fallback below — otherwise "is it set?" answers about the customer's last
  // message instead of the named variable. A whole Sheets row counts as set
  // when it actually has cells.
  //
  // Only exists/is_set/is_not_set get these semantics. is_empty/is_not_empty
  // stay on the legacy path below with their fallback intact: the builder
  // never offers them, so changing them would only move behaviour under
  // imported/template flows for no gain.
  switch (op) {
    case "exists":
    case "is_set":
      return _flowIsSet(resolved);
    case "not_exists":
    case "is_not_set":
      return !_flowIsSet(resolved);
  }

  // Comparisons keep the historical "fall back to what the customer just typed
  // when the named variable is blank" behaviour — `|| ""` included, so a
  // variable holding 0/false still falls back exactly as it always has.
  let userValue = resolved || "";
  if (!userValue) userValue = (session?.userVariables || {})["user_message"] || "";
  const checkValue = c?.value || "";
  const uRaw = _flowStr(userValue);
  const u = uRaw.toLowerCase().trim();
  const v = String(checkValue).toLowerCase().trim();
  switch (op) {
    case "equals":         return u === v;
    case "not_equals":     return u !== v;
    case "contains":       return u.includes(v);
    case "not_contains":   return !u.includes(v);
    case "gt":
    case "greater_than":   return parseFloat(uRaw) > parseFloat(checkValue);
    case "lt":
    case "less_than":      return parseFloat(uRaw) < parseFloat(checkValue);
    case "is_empty":       return u === "";
    case "is_not_empty":   return u !== "";
    case "starts_with":    return u.startsWith(v);
    case "ends_with":      return u.endsWith(v);
    default:
      console.warn(`[FLOW] condition node=${nodeId} unknown operator "${c?.operator}" → FALSE`);
      return false;
  }
}

/**
 * Walk the AND/OR chain. An earlier executor broke on first match, which
 * silently turned every multi-rule condition into a 1-rule OR chain regardless
 * of what the author selected in the builder.
 *
 * Exported so node/test/flow-condition.mjs can exercise it without a socket.
 */
export function evaluateFlowConditions(session, conditions = [], operators = [], nodeId = "?") {
  if (!Array.isArray(conditions) || conditions.length === 0) return false;
  // A hand-edited/imported flow can carry a null or short joiner list. Reading
  // operators[i-1] off null throws, which kills the whole flow session
  // mid-conversation — default the shape instead.
  const joiners = Array.isArray(operators) ? operators : [];

  const trace = [];
  const evalTraced = (c) => {
    const r = _flowEvalCondition(session, c, nodeId);
    trace.push(`${c?.variable}="${_flowStr(_flowResolveVar(session, c?.variable)).slice(0, 30)}" ${c?.operator}${c?.value ? ` "${c.value}"` : ""} → ${r}`);
    return r;
  };

  let conditionMet = evalTraced(conditions[0]);
  for (let i = 1; i < conditions.length; i++) {
    const op   = String(joiners[i - 1] || "AND").toUpperCase();
    const next = evalTraced(conditions[i]);
    conditionMet = op === "OR" ? (conditionMet || next) : (conditionMet && next);
  }

  console.log(`[FLOW] condition node=${nodeId} → ${conditionMet ? "TRUE (IF port)" : "FALSE (ELSE port)"} · ${trace.join(" · ")}`);
  return conditionMet;
}

// ✅ STEP 9: Execute Condition Node
async function executeConditionNode(
  node,
  targetPhoneNumber,
  senderPhoneNumber,
  sock,
  session,
  appLocals,
  sessionKey
) {
  const conditions = node.conditions || [];
  const operators  = Array.isArray(node.logicOperators) ? node.logicOperators
                   : Array.isArray(node.operators)      ? node.operators
                   : [];

  const conditionMet = evaluateFlowConditions(session, conditions, operators, node.id);

  const outputNum = conditionMet ? "1" : "2";
  const nextEdge = session.flowData.flowEdges.find(
    (edge) => edge.sourceNodeId === `${node.id}_${outputNum}`
  );

  if (nextEdge) {
    const nextNodeId = String(nextEdge.targetNodeId).replace(/_\d+$/, "");
    const nextNode = session.flowData.flowNodes.find(
      (n) => n.id === nextNodeId
    );

    if (nextNode) {
      await executeFlowNode(
        nextNode,
        targetPhoneNumber,
        senderPhoneNumber,
        sock,
        appLocals,
        sessionKey
      );
    } else {

      endFlowSession(appLocals, sessionKey);
    }
  } else {

    endFlowSession(appLocals, sessionKey);
  }
}

// ✅ STEP 10: Execute Question Node
async function executeQuestionNode(node, finalNumber, sock, session, appLocals) {
  try {
    // FlowNormalizer emits `question` + `expectedAnswers` for new flows.
    // Legacy flows may still have `questionText` + `answerItems`. Accept
    // either so the executor isn't tied to one naming era.
    // Substitute {{var}} in the prompt (Send-message nodes already do this) so an
    // Ask Question can echo an earlier captured answer, e.g. "Thanks for your
    // email: {{email}}". Previously sent RAW → the placeholder printed literally.
    const questionText = _flowSubst(session, node.question || node.questionText || "Please provide your answer:");
    const expected     = Array.isArray(node.expectedAnswers) ? node.expectedAnswers : (node.answerItems || []);
    // Coerce string options ("Yes") into the {text, id} shape the
    // interactive-buttons formatter expects.
    const items = expected.map((o, i) => (typeof o === "string"
      ? { text: o, answerText: o, id: `opt_${i}` }
      : { text: o.text || o.answerText || o.title || `Option ${i + 1}`, answerText: o.answerText || o.text || o.title, id: o.id || `opt_${i}` }));
    const hasButtons = items.length > 0;
    node.answerItems = items; // keep for handleQuestionResponse downstream
    console.log(`[FLOW] ask node=${node.id} text_len=${questionText.length} options=${items.length}`);

    // WABA branch — route question buttons through Meta's interactive
    // button / list payloads. Pre-2026-05-24 this whole node was
    // Baileys-only; WABA workspaces silently dropped the buttons and
    // the user could only ever see/answer the text.
    const settings = await resolveWabaSettings(session, session.__appDomainName || appLocals.appDomainName, session.__senderPhone);
    // Engine flag — true for WABA Cloud OR Twilio (both go through their
  // REST APIs, not the local Baileys sock). The sendWaba* helpers
  // dispatch internally based on settings, so we only need a single
  // bool for "use remote API" at each call site. Renamed from useWaba
  // → useRemote in 2026-05-27 audit but kept the var name for ABI
  // compatibility with the 13 existing call sites.
  const useWaba  = settings && (
    (settings.use_facebook_api && settings.facebook_phone_id) ||
    (settings.use_twilio && settings.twilio_account_sid)
  );

    if (useWaba && hasButtons) {
      if (items.length <= 3) {
        const r = await sendWabaButtons(finalNumber, questionText,
          items.map((it) => ({ id: it.id, title: it.answerText || it.text })),
          settings, { footer: node.footer });
        if (!r.success) console.warn(`[FLOW] ask WABA buttons failed: ${r.error}`);
      } else {
        // >3 → use list message (Meta caps quick_reply at 3, but list allows up to 10).
        const sections = [{
          title: 'Options',
          rows: items.slice(0, 10).map((it) => ({ id: it.id, title: String(it.answerText || it.text || '').slice(0, 24) })),
        }];
        const r = await sendWabaList(finalNumber, questionText, 'Select', sections, settings, {});
        if (!r.success) console.warn(`[FLOW] ask WABA list failed: ${r.error}`);
      }
      session.messageHistory.push({ type: 'sent', message: questionText, timestamp: moment().format() });
      session.waitingForInput = {
        nodeId: node.id, variable: node.variable || 'user_message',
        answerItems: items, nextNodeType: 'Question', acceptMedia: node.acceptMedia || false,
      };
      return;
    } else if (useWaba) {
      await sendWabaText(finalNumber, questionText, settings);
      session.messageHistory.push({ type: 'sent', message: questionText, timestamp: moment().format() });
      session.waitingForInput = {
        nodeId: node.id, variable: node.variable || 'user_message',
        answerItems: items, nextNodeType: 'Question', acceptMedia: node.acceptMedia || false,
      };
      return;
    }

    if (hasButtons && items.length <= 3) {
      const messageContent = {
        text: questionText,
        footer: node.footer || " ",
        interactiveButtons: items.map((item, index) => ({
          name: 'quick_reply',
          buttonParamsJson: JSON.stringify({
            display_text: item.answerText || item.text || `Option ${index + 1}`,
            id: item.id || `flow_${node.id}_${index}`
          })
        }))
      };

      await flowSockSend(sock, settings, finalNumber, messageContent);

    }
    else if (hasButtons && items.length > 3) {
      // Modern interactive quick_reply (cap 10). Legacy `sections/buttonText`
      // listMessage gets silently dropped by WA for personal-paired accounts.
      const interactiveButtons = items.slice(0, 10).map((item, i) => ({
        name: 'quick_reply',
        buttonParamsJson: JSON.stringify({
          display_text: String(item.answerText || item.text || `Option ${i + 1}`).slice(0, 20),
          id:           `flow_${node.id}_${i}`,
        }),
      }));
      console.log(`[FLOW] question → interactive(quick_reply x ${interactiveButtons.length})`);
      const _qFooter = _interactiveFooter(settings);
      await flowSockSend(sock, settings, finalNumber, {
        text: questionText,
        interactiveButtons,
        ...(_qFooter ? { footer: _qFooter } : {}),
      });
    }
    else {
      await flowSockSend(sock, settings, finalNumber, { text: _appendFooterToText(questionText, settings) });
    }

    session.messageHistory.push({
      type: "sent",
      message: questionText,
      timestamp: moment().format(),
    });

    session.waitingForInput = {
      nodeId: node.id,
      variable: node.variable || "user_message",
      answerItems: items,
      nextNodeType: "Question",
      acceptMedia: node.acceptMedia || false,
    };
    console.log(`[FLOW] ask SENT, waiting for input on var="${node.variable || 'user_message'}"`);

  } catch (error) {
    console.error(`[FLOW] ask SEND FAILED: ${error?.message}`);
    let fallbackText = (node.question || node.questionText || "Please provide your answer:");
    const items = node.answerItems || [];
    if (items.length > 0) {
      fallbackText += "\n\n";
      items.forEach((item, index) => {
        fallbackText += `${index + 1}. ${item.answerText || item.text || item}\n`;
      });
      fallbackText += "\nReply with the number:";
    }
    await flowSockSend(sock, settings, finalNumber, { text: _appendFooterToText(fallbackText, settings) });
    session.waitingForInput = {
      nodeId: node.id,
      variable: node.variable || "user_message",
      answerItems: items,
      nextNodeType: "Question",
    };
  }
}

// ✅ STEP 11: Execute Buttons Node
async function executeButtonsNode(node, finalNumber, sock, session, appLocals) {
  try {
    // FlowNormalizer emits `bodyText` (the prompt) + `buttons[]`. Legacy
    // flows used `questionText` + `answerItems`. Accept either.
    const buttonText = _flowSubst(session, node.bodyText || node.questionText || node.headerText || "Choose an option:");
    const footer     = node.footerText || node.footer || " ";
    const items      = Array.isArray(node.buttons) ? node.buttons : (node.answerItems || []);
    // Normalize each entry into {text,id} so the formatter helper works.
    // NODE-SCOPED option ids `b_<nodeId>_p<idx>`. Bare p0/p1/p2 are shared by EVERY
    // buttons menu, and WhatsApp keeps old menus tappable — so a tap on one menu's
    // button used to be matched against whichever node was waiting and routed down
    // its p0/p1 branch (the "tapped X, got Y's reply" bug). A node-scoped id makes a
    // tap uniquely identify its source menu + option so it always hits the wired
    // branch. See the button-tap router in handleFlowResponse.
    const itemObjs = items.map((b, i) => {
      // Resolve {{vars}} in the button LABEL — e.g. a Run code node's
      // {{result.day_1}}. Without this the placeholder was sent to WABA
      // literally, producing an invalid/empty title, so the interactive
      // send failed and the flow got stuck (client: "no response" right
      // after the service pick, where the dynamic day buttons should show).
      const rawText = typeof b === "string"
        ? b : (b.title || b.text || b.answerText || `Option ${i + 1}`);
      const text = _flowSubst(session, rawText).trim() || `Option ${i + 1}`;
      return { text, answerText: text, id: (typeof b === "string" ? null : b.id) || `b_${node.id}_p${i}` };
    });
    node.answerItems = itemObjs; // for handleQuestionResponse downstream

    // WABA branch — interactive button payload routes to Meta Cloud API.
    // Pre-2026-05-24 this whole node was Baileys-only; WABA workspaces
    // silently dropped the buttons.
    const settings = await resolveWabaSettings(session, session.__appDomainName || appLocals.appDomainName, session.__senderPhone);
    // Engine flag — true for WABA Cloud OR Twilio (both go through their
  // REST APIs, not the local Baileys sock). The sendWaba* helpers
  // dispatch internally based on settings, so we only need a single
  // bool for "use remote API" at each call site. Renamed from useWaba
  // → useRemote in 2026-05-27 audit but kept the var name for ABI
  // compatibility with the 13 existing call sites.
  const useWaba  = settings && (
    (settings.use_facebook_api && settings.facebook_phone_id) ||
    (settings.use_twilio && settings.twilio_account_sid)
  );

    if (useWaba) {
      // Only pass a header when it DIFFERS from the body. buttonText falls back
      // to headerText when the node has no body (line above), so an
      // unconditional header would render it as the bold header bubble AND
      // repeat it as the body — the same header/body double-send the list node
      // already guards against.
      const _btnHeader = (node.headerText && String(node.headerText).trim() !== String(buttonText).trim())
        ? node.headerText : undefined;
      const result = await sendWabaButtons(finalNumber, buttonText,
        itemObjs.map((it) => ({ id: it.id, title: it.text || it.answerText })),
        settings,
        { header: _btnHeader, footer: node.footerText }
      );
      if (!result.success) console.warn(`[FLOW] buttons WABA send failed: ${result.error}`);
      else console.log(`[FLOW] buttons SENT via WABA`);
      session.messageHistory.push({ type: "sent", message: buttonText, timestamp: moment().format() });
      // persistent: keep this quick-reply menu tappable after the first pick —
      // WhatsApp greys out a tapped button, so once a LEAF branch finishes the
      // re-arm in handleFlowResponse re-opens this same wait and the customer can
      // tap ANOTHER button and get its reply (was one-shot → 2nd tap did nothing).
      // Same behaviour template-node menus already have; keepMenuOpen=false opts out.
      session.waitingForInput = { nodeId: node.id, variable: node.variable || "user_message", answerItems: itemObjs, nextNodeType: "Question", persistent: node.keepMenuOpen !== false };
      return;
    }

    const formattedButtons = formatInteractiveButtonsForBaileys(itemObjs);
    console.log(`[FLOW] buttons node=${node.id} text_len=${buttonText.length} buttons=${itemObjs.length}`);

    if (formattedButtons && formattedButtons.length > 0 && sock) {
      // Caller's node-config footer wins; otherwise apply the plan-gated brand.
      const _bFooter = (footer && footer.trim() && footer !== ' ') ? footer : (_interactiveFooter(settings) || footer);
      const buttonMessage = {
        text: buttonText,
        footer: _bFooter,
        interactiveButtons: formattedButtons
      };
      await flowSockSend(sock, settings, finalNumber, buttonMessage);
      console.log(`[FLOW] buttons SENT ok`);
    } else {
      // Fallback to text-with-numbers for installs where Baileys
      // interactive buttons aren't accepted by the peer device.
      let fallbackText = buttonText + "\n\n";
      itemObjs.forEach((item, index) => {
        fallbackText += `${index + 1}. ${item.text || item.answerText}\n`;
      });
      fallbackText += "\nReply with the number:";
      await flowSockSend(sock, settings, finalNumber, { text: _appendFooterToText(fallbackText, settings) });
      console.log(`[FLOW] buttons fallback text-mode SENT`);
    }

    session.messageHistory.push({
      type: "sent",
      message: buttonText,
      timestamp: moment().format(),
    });
    session.waitingForInput = {
      nodeId: node.id,
      variable: node.variable || "user_message",
      answerItems: itemObjs,
      nextNodeType: "Question",
      // Keep the menu tappable after the first pick (see WABA branch above):
      // after a leaf branch, handleFlowResponse re-arms this wait so tapping a
      // second/third button on the SAME message routes to its branch instead of
      // doing nothing. keepMenuOpen=false makes it one-shot.
      persistent: node.keepMenuOpen !== false,
    };
  } catch (error) {
    console.error(`[FLOW] buttons SEND FAILED: ${error?.message}`);
  }
}

// ✅ STEP 12: Execute List Node
async function executeListNode(node, finalNumber, sock, session, appLocals) {
  const header     = String(node.headerText || "").trim();
  const body       = String(node.bodyText   || node.headerText || "Please select from the list:").trim();
  // Only surface a message header when the operator gave one that DIFFERS from
  // the body. When a List node has just one text (its prompt lives in
  // headerText and body falls back to it), header === body → sending both
  // makes WhatsApp render the SAME line twice (the reported bug). Drop it.
  const showHeader = header !== "" && header !== body;
  const items      = Array.isArray(node.listItems) ? node.listItems : [];

  console.log(`[FLOW] list node=${node.id} header="${header}" body_len=${body.length} items=${items.length} sock=${sock ? 'YES' : 'NO'}`);

  if (items.length === 0) {
    console.warn(`[FLOW] list node=${node.id} has 0 items — skipping.`);
    return;
  }

  // WABA branch — Meta's `interactive.list` payload (with sections + rows)
  // is the only way a list message gets delivered on Cloud API. The
  // legacy Baileys-only path silently dropped on WABA workspaces.
  const settings = await resolveWabaSettings(session, session.__appDomainName || appLocals.appDomainName, session.__senderPhone);
  // Engine flag — true for WABA Cloud OR Twilio (both go through their
  // REST APIs, not the local Baileys sock). The sendWaba* helpers
  // dispatch internally based on settings, so we only need a single
  // bool for "use remote API" at each call site. Renamed from useWaba
  // → useRemote in 2026-05-27 audit but kept the var name for ABI
  // compatibility with the 13 existing call sites.
  const useWaba  = settings && (
    (settings.use_facebook_api && settings.facebook_phone_id) ||
    (settings.use_twilio && settings.twilio_account_sid)
  );

  if (useWaba) {
    const subst = (s) => String(s || "")
      .replace(/\{\{\s*([^{}]+?)\s*\}\}/g, (_, k) => String(session.userVariables?.[k] ?? session.vars?.[k] ?? ""));
    // Meta caps sections at 10 and rows at 10 TOTAL across all sections;
    // sendWabaList re-slices and enforces the per-field char limits
    // (title 24, description 72, section title 24).
    const sections = buildListSections(node);
    const result = await sendWabaList(finalNumber, subst(body), node.buttonText || 'Select', sections, settings, {
      header: showHeader ? node.headerText : undefined, footer: node.footerText,
    });
    if (!result.success) console.warn(`[FLOW] list WABA send failed: ${result.error}`);
    else console.log(`[FLOW] list SENT via WABA (${sections.reduce((n, s) => n + s.rows.length, 0)} rows in ${sections.length} section(s))`);
    session.messageHistory.push({ type: 'sent', message: showHeader ? `${header}\n${body}` : body, timestamp: moment().format() });
    session.waitingForInput = { nodeId: node.id, variable: node.variable || 'choice', listItems: items, nextNodeType: 'List' };
    return;
  }

  if (!sock) {
    console.warn(`[FLOW] list node=${node.id} has NO sock — device offline. Skipping.`);
    return;
  }

  // Use the modern Interactive Message format (interactiveButtons +
  // nativeFlowResponseMessage on the reply path). The legacy
  // `sections / buttonText / listMessage` shape gets silently dropped
  // by WhatsApp's relay for personal-paired numbers (especially in
  // India) — Baileys reports success, no SERVER_ACK arrives, recipient
  // never sees it. Interactive quick_reply buttons go through the same
  // gate that templates use and are universally delivered.

  const subst = (s) => String(s || "")
    .replace(/\{\{\s*([^{}]+?)\s*\}\}/g, (_, k) => String(session.userVariables?.[k] ?? session.vars?.[k] ?? ""));
  const headerText = subst(header);
  const bodyText   = subst(body);

  // Walk the SAME section-grouped order the WABA/Twilio paths render, so a
  // typed digit means the same row on every engine (the reply router maps
  // digits through that order). Quick-reply buttons carry neither a section
  // header nor a description — WhatsApp's button spec has no field for
  // either — so grouped items just land adjacent, titles only. Sections and
  // descriptions are a WABA Cloud capability; this is the documented
  // fallback, not a bug. Spec caps quick_reply at 10 (buildListSections
  // already slices); more than that and the operator should split the node.
  const rows = buildListSections(node).flatMap((s) => s.rows);
  if (rows.length < items.length) {
    console.warn(`[FLOW] list node=${node.id} has ${items.length} items, capping to 10 (WhatsApp spec).`);
  }

  const interactiveButtons = rows.map((row) => ({
    name: 'quick_reply',
    buttonParamsJson: JSON.stringify({
      display_text: String(row.title).slice(0, 20),
      id:           row.id,
    }),
  }));

  console.log(`[FLOW] list → interactive(quick_reply x ${interactiveButtons.length})`);

  try {
    await flowSockSend(sock, settings, finalNumber, {
      text:     bodyText,
      title:    headerText && headerText !== bodyText ? headerText : undefined,
      footer:   node.footerText ? subst(String(node.footerText)) : undefined,
      interactiveButtons,
    });
    console.log(`[FLOW] list (interactive) SENT ok`);

    session.messageHistory.push({
      type: "sent",
      message: `${headerText}\n${bodyText}`,
      timestamp: moment().format(),
    });
    session.waitingForInput = {
      nodeId: node.id,
      variable: node.variable || "choice",
      listItems: items,
      nextNodeType: "List",
    };
  } catch (error) {
    console.error(`[FLOW] list SEND FAILED node=${node.id}: ${error?.message} stack=${error?.stack?.split('\n')[1] || ''}`);
  }
}

// ✅ STEP 13: Execute Poll Node
async function executePollNode(node, finalNumber, sock, session, appLocals) {
  const question = node.question || "Poll Question";
  const options = (node.options || []).map((opt) =>
    typeof opt === "string" ? opt : opt.text || opt.answerText || String(opt)
  );

  if (options.length < 2 || options.length > 12) {
    const errText = "❌ Poll must have 2-12 options";
    if (sock) await flowSockSend(sock, settings, finalNumber, { text: errText });
    return;
  }

  // WABA branch — Meta Cloud API does NOT support native polls.
  // Best fallback is an interactive list (up to 10 rows) which gives
  // the same single-select UX. Multi-select polls degrade to text.
  const settings = await resolveWabaSettings(session, session.__appDomainName || appLocals.appDomainName, session.__senderPhone);
  // Engine flag — true for WABA Cloud OR Twilio (both go through their
  // REST APIs, not the local Baileys sock). The sendWaba* helpers
  // dispatch internally based on settings, so we only need a single
  // bool for "use remote API" at each call site. Renamed from useWaba
  // → useRemote in 2026-05-27 audit but kept the var name for ABI
  // compatibility with the 13 existing call sites.
  const useWaba  = settings && (
    (settings.use_facebook_api && settings.facebook_phone_id) ||
    (settings.use_twilio && settings.twilio_account_sid)
  );

  if (useWaba) {
    if (node.allowMultiple) {
      // No multi-select equivalent on WABA — degrade to text + numbered list.
      let txt = `📊 ${question}\n\n`;
      options.forEach((o, i) => { txt += `${i + 1}. ${o}\n`; });
      txt += "\nReply with the numbers (comma-separated):";
      await sendWabaText(finalNumber, txt, settings);
      console.warn(`[FLOW] poll multi-select on WABA — degraded to text (Meta has no native poll)`);
    } else {
      const sections = [{
        title: 'Options',
        rows: options.slice(0, 10).map((o, i) => ({ id: `poll_${node.id}_${i}`, title: String(o).slice(0, 24) })),
      }];
      const r = await sendWabaList(finalNumber, question, 'Vote', sections, settings, {});
      if (!r.success) console.warn(`[FLOW] poll WABA list send failed: ${r.error}`);
      else console.log(`[FLOW] poll SENT via WABA (as list — Meta has no native poll)`);
    }
    session.messageHistory.push({ type: 'sent', message: `Poll: ${question}`, timestamp: moment().format() });
    session.waitingForInput = {
      nodeId: node.id, variable: 'poll_response', answerItems: node.options || [],
      nextNodeType: 'Poll', allowMultiple: node.allowMultiple,
    };
    return;
  }

  if (!sock) {
    console.warn(`[FLOW] poll node=${node.id} has no sock and no WABA — skipping`);
    return;
  }

  try {
    const pollMessage = {
      poll: {
        name: question,
        values: options,
        selectableCount: node.allowMultiple ? options.length : 1,
      },
    };
    await flowSockSend(sock, settings, finalNumber, pollMessage);
    session.messageHistory.push({ type: "sent", message: `Poll: ${question}`, timestamp: moment().format() });
    session.waitingForInput = {
      nodeId: node.id, variable: "poll_response", answerItems: node.options || [],
      nextNodeType: "Poll", allowMultiple: node.allowMultiple,
    };
  } catch (error) {
    console.error(`[FLOW] poll SEND FAILED node=${node.id}: ${error?.message} stack=${error?.stack?.split('\n')[1] || ''}`);
  }
}

// Flatten a template variable_map into {slot:key}. Accepts BOTH the flat
// shape {"1":"name"} AND the nested stored shape {header:[{num,key}],
// body:[{num,key}]} that FlowNormalizer ships verbatim. Mirrors
// App\Services\AttributeResolver::normalizeVariableMap (PHP). body wins on
// a slot collision. Returns {} when not a usable object.
function normalizeFlowVariableMap(map) {
  if (!map || typeof map !== "object" || Array.isArray(map)) return {};
  if (map.header === undefined && map.body === undefined) return map;
  const flat = {};
  for (const section of ["header", "body"]) {
    const entries = map[section];
    if (!Array.isArray(entries)) continue;
    for (const entry of entries) {
      if (entry && typeof entry === "object" && entry.num !== undefined && entry.key) {
        flat[String(entry.num)] = String(entry.key);
      }
    }
  }
  return flat;
}

// ✅ STEP 14: Execute Template Node
async function executeTemplateNode(
  node,
  finalNumber,
  sock,
  session,
  targetPhoneNumber,
  senderPhoneNumber,
  appLocals,
  sessionKey
) {
  // The Laravel-side FlowNormalizer hydrates `templateBody` + header /
  // footer / buttons / variable_map by looking up wa_templates at fetch
  // time, so the executor doesn't need a round-trip per send. If the
  // builder picked a template name that's no longer in the workspace
  // we degrade gracefully (skip + advance) rather than blasting the
  // customer with the literal string "Template message".
  const tplName = String(node.templateName || node.tpl || "").trim();
  const body    = String(node.templateBody || "").trim();
  const header  = String(node.templateHeader || "").trim();
  const footer  = String(node.templateFooter || "").trim();
  const buttons = Array.isArray(node.templateButtons) ? node.templateButtons : [];
  // Quick-reply buttons are the only ones that send a tap-back, so each can
  // branch the flow to its own next node. The builder exposes one port (p0,
  // p1, …) per quick-reply button in the SAME order. We only switch this node
  // into "wait for the tap" mode when the operator actually wired a button
  // port (a p-edge from this node) — otherwise it auto-advances exactly as
  // before, so existing template flows are unchanged. URL / call / copy-code
  // buttons never branch (they don't reply).
  const _isQrBtn = (b) => {
    const k = String((b && (b.type || b.button_type)) || '').toLowerCase();
    return !(k.includes('url') || k.includes('cta') || k.includes('visit') || k.includes('call') || k.includes('phone') || k.includes('copy'));
  };
  const _qrItems = buttons.filter(_isQrBtn).map((b, i) => {
    const txt = String(b.text || b.title || b.label || ('Button ' + (i + 1)));
    return { text: txt, answerText: txt, id: 'p' + i };
  });
  const _buttonsWired = _qrItems.length > 0 && Array.isArray(session.flowData?.flowEdges)
    && session.flowData.flowEdges.some(e => String(e.source || '') === String(node.id) && /^p\d+$/.test(String(e.sourceHandle || '')));
  // variable_map arrives in EITHER the flat shape {"1":"name"} OR the
  // nested stored shape {header:[{num,key}], body:[{num,key}]} that
  // FlowNormalizer ships verbatim. Flatten to {slot:key} so positional
  // {{1}} lookups below hit. Mirrors AttributeResolver::normalizeVariableMap.
  const varMap  = normalizeFlowVariableMap(node.templateVariableMap);

  console.log(`[FLOW] template node=${node.id} name="${tplName}" id=${node.templateId || 'NULL'} body_len=${body.length} buttons=${buttons.length}`);
  if (!body) {
    console.warn(`[FLOW] template "${tplName}" has NO BODY — either not in wa_templates or not hydrated by FlowNormalizer. Skipping.`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  // WABA branch — for Meta-approved templates we use the proper
  // Meta `type:template` payload (referencing the registered
  // template name + components). Without this WABA workspaces
  // would silently drop the send entirely.
  const settings = await resolveWabaSettings(session, appLocals.appDomainName, senderPhoneNumber);
  // Engine flag — true for WABA Cloud OR Twilio (both go through their
  // REST APIs, not the local Baileys sock). The sendWaba* helpers
  // dispatch internally based on settings, so we only need a single
  // bool for "use remote API" at each call site. Renamed from useWaba
  // → useRemote in 2026-05-27 audit but kept the var name for ABI
  // compatibility with the 13 existing call sites.
  const useWaba  = settings && (
    (settings.use_facebook_api && settings.facebook_phone_id) ||
    (settings.use_twilio && settings.twilio_account_sid)
  );

  // Merge tags. Two flavours coexist:
  //   - Named: {{name}}, {{doctor}}, … resolved from session.userVariables
  //   - Positional: {{1}}, {{2}}, … resolved via variable_map (the same
  //     map the campaign dispatcher uses) — the value can itself be a
  //     named tag the operator wants resolved against userVariables, so
  //     we recurse one level when that happens.
  const subst = (s) => String(s || "")
    .replace(/\{\{\s*([^{}]+?)\s*\}\}/g, (_, k) => {
      // 1) positional → map → maybe a named tag → maybe a literal
      if (/^\d+$/.test(k) && Object.prototype.hasOwnProperty.call(varMap, k)) {
        const mapped = String(varMap[k] ?? "");
        // The mapped value is the attribute key the operator picked. It
        // may be a bare key ("name", from the normalized nested map) OR a
        // wrapped tag ("{{name}}", from a legacy flat map). Resolve either
        // against the session vars; fall back to the literal if it's not a
        // known variable name.
        const m = mapped.match(/^\{\{\s*([^{}]+?)\s*\}\}$/);
        const lookupKey = m ? m[1] : mapped;
        const resolved = session.userVariables?.[lookupKey] ?? session.vars?.[lookupKey];
        if (resolved !== undefined && resolved !== null) return String(resolved);
        // If the mapped value was a literal (not a known var), emit it.
        return m ? "" : mapped;
      }
      // 2) named → session vars
      return String(session.userVariables?.[k] ?? session.vars?.[k] ?? "");
    });

  // Build the WhatsApp bubble — header + body + footer stacked, then
  // emit the (max 3) buttons as a Baileys interactive message when
  // the template defines any. Without buttons it's a plain text send.
  const lines = [];
  if (header) lines.push(subst(header));
  lines.push(subst(body));
  if (footer) lines.push("_" + subst(footer) + "_");
  const text = lines.join("\n\n");

  try {
    if (useWaba) {
      // Two routes for WABA template:
      //   1. If templateName + meta_template_id exist → send proper
      //      Meta type:template message (uses Meta-approved template
      //      with components). FlowNormalizer hydrates these from
      //      wa_templates so the template name is real.
      //   2. Otherwise (custom inline template w/o Meta approval) →
      //      degrade to interactive button or text body. The 24-hour
      //      customer-service window applies — Meta still accepts
      //      free-form messages within it.
      if (tplName && node.templateMetaId) {
        // Build components per Meta's send-template spec.
        const bodyParams = [];
        // Walk positional placeholders in body, push resolved values.
        const positionals = (body.match(/\{\{\s*\d+\s*\}\}/g) || []).length;
        for (let i = 1; i <= positionals; i++) {
          const v = subst('{{' + i + '}}');
          bodyParams.push({ type: 'text', text: String(v) });
        }
        const components = [];
        if (bodyParams.length) components.push({ type: 'body', parameters: bodyParams });
        const r = await sendWabaTemplate(finalNumber, tplName, node.templateLanguage || 'en_US', components, settings);
        if (!r.success) console.warn(`[FLOW] template WABA send failed: ${r.error}`);
        else console.log(`[FLOW] template SENT via WABA (type:template, components=${components.length})`);
      } else if (buttons.length > 0) {
        // Inline (non-Meta-approved) template with buttons → interactive button.
        // Quick-reply buttons carry the SAME p<j> ids as _qrItems so a tap
        // id-matches its branch in handleQuestionResponse; url/call-only
        // templates keep the original mapping.
        const _wbBtns = _qrItems.length
          ? _qrItems.map((it) => ({ id: String(it.id), title: String(it.text || 'Button').slice(0, 20) }))
          : buttons.map((b, i) => ({ id: b.id || ('tpl_' + node.id + '_p' + i), title: b.text || b.title || `Button ${i + 1}` }));
        // Body ONLY — NOT `text` (which stacks header+body+footer). WABA
        // interactive messages carry header/footer in their own slots, so
        // passing the stacked `text` here rendered the header as the bold
        // header bubble AND repeated it as the first body line (and the footer
        // twice) — the "same message showing header + body twice" report.
        const r = await sendWabaButtons(finalNumber, subst(body),
          _wbBtns,
          settings, { header, footer }
        );
        if (!r.success) console.warn(`[FLOW] template WABA interactive failed: ${r.error}`);
        else console.log(`[FLOW] template SENT via WABA (interactive, ${buttons.length} buttons)`);
      } else {
        await sendWabaText(finalNumber, text, settings);
        console.log(`[FLOW] template SENT via WABA (text)`);
      }
    } else if (!sock) {
      console.warn(`[FLOW] template node=${node.id} has NO sock and no WABA — skipping send.`);
      return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
    } else if (buttons.length > 0) {
      // Use the modern interactive quick_reply shape — the legacy
      // `{ text, buttons: [{buttonId, buttonText}] }` payload is silently
      // dropped by WhatsApp's relay for personal-paired numbers (we
      // discovered this on the List node). interactiveButtons is the
      // universally-delivered replacement per Baileys spec.
      // Route through the shared formatter so a template's URL / call / copy
      // buttons render as proper cta_* actions (not all-quick_reply) — same
      // behaviour as chat / campaigns / broadcasts. Substitute placeholders in
      // each button's value/url first.
      // Quick-reply buttons carry their p<j> id (matches _qrItems/answerItems
      // so a tap id-matches its branch in handleQuestionResponse — was
      // b.value/b.id which didn't match, so only the first button routed).
      // URL/call/copy buttons keep their real value/url so the shared formatter
      // still renders them as proper cta_* actions.
      let _qrSeq = 0;
      const tplButtons = buttons.slice(0, 3).map((b, i) => {
        const _isQr = _isQrBtn(b);
        const _qrId = _isQr ? ('p' + (_qrSeq++)) : null;
        return {
          type:  b.type || 'quick_reply',
          text:  String(b.text || b.title || `Button ${i + 1}`),
          value: _isQr ? _qrId : subst(String(b.value ?? b.id ?? ('tpl_' + node.id + '_p' + i))),
          url:   b.url ? subst(String(b.url)) : undefined,
        };
      });
      const interactiveButtons = formatInteractiveButtonsForBaileys(tplButtons) || [];
      console.log(`[FLOW] template → sendMessage(interactive x ${interactiveButtons.length})`);
      await flowSockSend(sock, settings, finalNumber, {
        text,
        interactiveButtons,
      });
    } else {
      console.log(`[FLOW] template → sendMessage(text) text_len=${text.length} preview="${text.substring(0, 60).replace(/\n/g,'|')}"`);
      await flowSockSend(sock, settings, finalNumber, { text });
    }
    console.log(`[FLOW] template SENT ok`);
    session.messageHistory.push({
      type: "sent",
      message: text,
      timestamp: moment().format(),
    });
  } catch (e) {
    console.error(`[FLOW] template SEND FAILED: ${e?.message} stack=${e?.stack?.split('\n')[1] || ''}`);
  }

  // If the operator wired the template's quick-reply buttons to their own next
  // nodes, pause here and route on the customer's tap (same mechanism as the
  // Buttons node). Otherwise auto-advance down the single 'next' edge.
  if (_buttonsWired) {
    node.answerItems = _qrItems;
    session.waitingForInput = {
      nodeId: node.id,
      variable: node.variable || "user_message",
      answerItems: _qrItems,
      nextNodeType: "Question",
      // Persistent menu: WhatsApp disables a quick-reply button once tapped, so
      // after a leaf branch we re-show this template (handleFlowResponse) and
      // the customer can pick another option — the flow stays open instead of
      // ending after one button. Set node.keepMenuOpen=false for a one-shot.
      persistent: node.keepMenuOpen !== false,
    };
    console.log(`[FLOW] template node=${node.id} awaiting button tap → ${_qrItems.length} branchable ports: ${_qrItems.map(i => i.text).join(' | ')}`);
    return;
  }

  await moveToNextNode(
    node.id,
    session.flowData,
    targetPhoneNumber,
    senderPhoneNumber,
    sock,
    appLocals,
    sessionKey
  );
}

// ✅ STEP 15: Execute ChatGPT Node
async function executeChatGPTNode(
  node,
  finalNumber,
  sock,
  session,
  targetPhoneNumber,
  senderPhoneNumber,
  appLocals,
  sessionKey
) {
  // FlowNormalizer ships flat `model`, `prompt`, `variable`. Old shape
  // hung everything under `node.properties` and pulled keys from a
  // /api/whatsapp-settings cache (legacy) — that bypassed the
  // admin_ai_keys store, so the per-workspace AI quota / billing was
  // never enforced. Now we call back to Laravel which goes through
  // AiAgentService::callProvider (workspace BYOK → admin key → env).
  const model         = String(node.model    || node.properties?.model    || "gpt-4o-mini");
  const systemPrompt  = String(node.prompt   || node.properties?.systemMessage || "You are a helpful assistant.");
  const saveAs        = String(node.variable || node.properties?.outputVariable || "reply").replace(/^@/, "");
  const temperature   = typeof node.temperature === "number" ? node.temperature : 0.7;
  // Extraction mode (parse → JSON, used by the farm-record / ordering flows)
  // can emit MANY records in one reply. The old 350-token default truncated
  // the JSON mid-array → JSON.parse failed → blank Sheet rows. Give extract a
  // big budget (Laravel caps at 4000) so the JSON completes. Normal chat
  // replies keep the small default. An explicit node.maxTokens still wins.
  const _extractMode  = node.extract === true || node.properties?.extract === true;
  const maxTokens     = typeof node.maxTokens === "number" ? node.maxTokens : (_extractMode ? 4000 : 350);
  const userMessage   = session.userVariables.user_message || "";
  const workspaceId   = session.flowData?.workspace_id || 0;
  // Structured-extraction mode — when ON, the provider is forced to return a
  // strict JSON object (used by the "parse message → Google Sheet" and the
  // natural-language ordering flows). `fields` lists the keys to emit.
  const extract       = node.extract === true || node.properties?.extract === true;
  const fields        = Array.isArray(node.fields) ? node.fields
                      : (Array.isArray(node.properties?.fields) ? node.properties.fields : []);
  // Optional AI-Training assistant — when the node has one attached, the
  // Laravel side pulls that assistant's knowledge base into the system
  // prompt before calling the model (so flow replies use trained content).
  const assistantId   = parseInt(node.assistant_id || node.properties?.assistant_id || 0, 10) || 0;

  // Conversation mode — when ON, the AI node DRIVES the whole chat: it answers
  // the customer, then parks on itself (waitingForInput) so the next message
  // routes back here instead of advancing the flow. It keeps a running
  // transcript so the model has context, and only exits — advancing to the
  // wired next node — when the customer types the exit keyword (or the flow is
  // otherwise ended). Off by default, so existing one-shot AI nodes are
  // byte-for-byte unchanged.
  const conversational = node.conversational === true || node.chat_mode === true
                      || node.properties?.conversational === true;
  const exitKeyword    = String(node.exit_keyword || node.properties?.exit_keyword || "").trim();
  const histKey        = `__aichat_${node.id}`;
  let history = Array.isArray(session.userVariables[histKey]) ? session.userVariables[histKey] : [];

  // In conversation mode we fold the recent transcript into the prompt so the
  // model answers WITH context (no Laravel change needed — callProvider stays
  // system+user). Last 10 turns keeps tokens bounded.
  let promptForModel = userMessage;
  if (conversational && history.length) {
    const lines = history.slice(-10).map(h => `${h.role === "assistant" ? "Assistant" : "Customer"}: ${h.content}`);
    promptForModel = `Conversation so far:\n${lines.join("\n")}\n\nCustomer: ${userMessage}\n\nReply as the assistant to the latest customer message. Be natural, helpful, and concise.`;
  }

  console.log(`[FLOW] ai node=${node.id} model=${model} sys_len=${systemPrompt.length} user_len=${userMessage.length} convo=${conversational}`);

  let aiResponse = "";
  try {
    const resp = await axios.post(
      `${appLocals.appDomainName}/api/flow-node/ai-call`,
      { workspace_id: workspaceId, model, system_prompt: systemPrompt, user_prompt: promptForModel,
        max_tokens: maxTokens, temperature,
        ...(assistantId ? { assistant_id: assistantId } : {}),
        ...(extract ? { json: true, fields } : {}) },
      { timeout: 30000, headers: { "X-Node-Token": process.env.NODE_WEBHOOK_TOKEN || "" } },
    );
    aiResponse = String(resp.data?.reply || "");
  } catch (e) {
    console.warn(`[FLOW] ai call FAILED: ${e?.response?.data?.message || e?.message}`);
  }

  // DEEP TRACE: exactly what the AI returned. In extract mode this MUST be
  // valid JSON or NO fields get flattened (→ blank Google-Sheet row). The
  // Laravel-side [FLOWTRACE] ai-call lines show the provider + whether the
  // admin AI key was present; this shows the raw reply text.
  console.log(`[FLOWTRACE] ai node=${node.id} extract=${extract} fields=[${(fields || []).join(',')}] reply_len=${aiResponse.length} raw=${JSON.stringify(String(aiResponse).slice(0, 600))}`);

  if (!aiResponse) {
    aiResponse = "Sorry, I couldn't generate a reply right now. Please try again.";
  }

  // WABA branch — AI reply is plain text; route through Cloud API
  // when the workspace is on WABA. Pre-fix this whole node was
  // Baileys-only — WABA workspaces saw AI generate the reply but
  // silently never deliver it.
  const settings = await resolveWabaSettings(session, appLocals.appDomainName, senderPhoneNumber);
  // Engine flag — true for WABA Cloud OR Twilio (both go through their
  // REST APIs, not the local Baileys sock). The sendWaba* helpers
  // dispatch internally based on settings, so we only need a single
  // bool for "use remote API" at each call site. Renamed from useWaba
  // → useRemote in 2026-05-27 audit but kept the var name for ABI
  // compatibility with the 13 existing call sites.
  const useWaba  = settings && (
    (settings.use_facebook_api && settings.facebook_phone_id) ||
    (settings.use_twilio && settings.twilio_account_sid)
  );

  // Extraction mode: when the node is flagged silent / no-reply (used for
  // "parse the incoming message → write to Google Sheet" flows), we do NOT
  // message the customer — we only stash the parsed result for downstream
  // nodes. Default behaviour (a normal AI reply) is unchanged.
  const silent = node.silent === true || node.noReply === true
    || node.properties?.silent === true || node.properties?.noReply === true;

  try {
    if (silent) {
      console.log(`[FLOW] ai node=${node.id} silent — extraction only, no reply sent`);
    } else if (useWaba) {
      const r = await sendWabaText(finalNumber, aiResponse, settings);
      if (!r.success) console.warn(`[FLOW] ai WABA send failed: ${r.error}`);
      else console.log(`[FLOW] ai SENT via WABA reply_len=${aiResponse.length}`);
    } else if (sock) {
      await flowSockSend(sock, settings, finalNumber, { text: _appendFooterToText(aiResponse, settings) });
      console.log(`[FLOW] ai SENT ok reply_len=${aiResponse.length}`);
    } else {
      console.warn(`[FLOW] ai node=${node.id} no transport — skipping send`);
    }
    if (!silent) {
      session.messageHistory.push({ type: "sent", message: aiResponse, timestamp: moment().format() });
    }

    // Always stash the raw reply as `saveAs`.
    session.userVariables[saveAs] = aiResponse;

    // If the reply is JSON (optionally ```fenced```), explode each top-level
    // field into a flat `saveAs.<key>` variable so downstream nodes — Google
    // Sheets columns, text, conditions — can reference {{saveAs.field}}. This
    // mirrors the Sheets-read + Form-node behaviour. Non-JSON replies keep
    // just the single string var, so existing AI flows are unaffected.
    const parsed = _tryParseJson(aiResponse);
    if (extract && (parsed === null || parsed === undefined)) {
      console.warn(`[FLOWTRACE] ai node=${node.id} extract ON but the reply is NOT valid JSON → NO fields flattened → the Sheet row will be EMPTY. Fix the model/prompt (raw reply logged above as [FLOWTRACE] ai node).`);
    } else if (extract) {
      console.log(`[FLOWTRACE] ai node=${node.id} parsed OK type=${Array.isArray(parsed) ? 'array' : 'object'} keys=[${Array.isArray(parsed) ? ('array len ' + parsed.length) : Object.keys(parsed).join(',')}]`);
    }
    if (Array.isArray(parsed)) {
      // Multi-row: the AI returned SEVERAL records in one message. Stash the
      // whole list under `saveAs.__rows` so the Google Sheets node can write
      // one row per record, and explode the FIRST record into `saveAs.<key>`
      // too — so any existing single-row flow keeps working unchanged.
      session.userVariables[`${saveAs}.__rows`] = parsed;
      const first = (parsed.length && parsed[0] && typeof parsed[0] === "object" && !Array.isArray(parsed[0])) ? parsed[0] : {};
      for (const [k, v] of Object.entries(first)) {
        session.userVariables[`${saveAs}.${k}`] =
          (v === null || v === undefined) ? "" :
          (typeof v === "object" ? JSON.stringify(v) : String(v));
      }
      console.log(`[FLOW] ai node=${node.id} JSON array reply → ${parsed.length} row(s) under ${saveAs}.__rows`);
    } else if (parsed && typeof parsed === "object") {
      // json_object mode can't return a TOP-LEVEL array, so a multi-record
      // extraction comes wrapped, e.g. {"rows":[...]} / {"items":[...]}.
      // Promote the first array-of-objects property to `saveAs.__rows` so the
      // Google Sheets node still writes one row per record.
      const rowKey = ["rows", "items", "records", "data", "results"].find(
        (k) => Array.isArray(parsed[k]) && parsed[k].length && typeof parsed[k][0] === "object",
      );
      if (rowKey) {
        session.userVariables[`${saveAs}.__rows`] = parsed[rowKey];
        console.log(`[FLOW] ai node=${node.id} wrapped rows under "${rowKey}" → ${parsed[rowKey].length} row(s)`);
      }
      let n = 0;
      for (const [k, v] of Object.entries(parsed)) {
        session.userVariables[`${saveAs}.${k}`] =
          (v === null || v === undefined) ? "" :
          (typeof v === "object" ? JSON.stringify(v) : String(v));
        n++;
      }
      console.log(`[FLOW] ai node=${node.id} JSON reply → ${n} field var(s) under ${saveAs}.*`);
    }
  } catch (e) {
    console.error(`[FLOW] ai SEND FAILED: ${e?.message}`);
  }

  // Conversation mode — record the turn and PARK on this node so the next
  // customer message comes back to the AI (handled in handleFlowResponse's
  // "AiChat" branch) instead of advancing the flow. The session is pinned into
  // activeFlowSessions so endFlowSession's delayed cleanup can't drop it
  // mid-conversation. Only the exit keyword (or an ended flow) advances on.
  if (conversational && !silent) {
    if (userMessage) history.push({ role: "user", content: String(userMessage).slice(0, 2000) });
    history.push({ role: "assistant", content: String(aiResponse).slice(0, 2000) });
    session.userVariables[histKey] = history.slice(-20);
    session.status = "active";
    session.waitingForInput = {
      nodeId: node.id,
      variable: "user_message",
      nextNodeType: "AiChat",
      exitKeyword,
    };
    appLocals.activeFlowSessions[sessionKey] = session;
    console.log(`[FLOW] ai node=${node.id} conversation parked — awaiting next customer message`);
    return;
  }

  await moveToNextNode(
    node.id,
    session.flowData,
    targetPhoneNumber,
    senderPhoneNumber,
    sock,
    appLocals,
    sessionKey
  );
}

// ✅ STEP 16: Execute CTA Node (Call-to-Action URL button)
async function executeCTANode(
  node,
  finalNumber,
  sock,
  session,
  targetPhoneNumber,
  senderPhoneNumber,
  appLocals,
  sessionKey
) {
  // Multi-action CTA. FlowNormalizer ships `ctaActions[]` with each
  // entry shaped { type, label, value }. Legacy single-action flows
  // (just `ctaType/ctaUrl/...`) are migrated below into the same array
  // so this executor only knows one code path.
  let actions = Array.isArray(node.ctaActions) ? node.ctaActions : null;
  if (!actions) {
    const legacyKind  = (node.ctaType || "url").toLowerCase();
    const legacyLabel = node.ctaLabel || node.buttonText || "Tap";
    const legacyValue =
      legacyKind === "phone" || legacyKind === "call_now"
        ? (node.ctaPhone || (node.ctaUrl || "").replace(/^tel:/i, ""))
        : legacyKind === "copy"
        ? (node.ctaCode || node.ctaUrl || "")
        : (node.ctaUrl || node.buttonUrl || "");
    actions = [{ type: legacyKind, label: legacyLabel, value: legacyValue }];
  }

  const headerText = node.headerText || "";
  const bodyText   = node.valueText  || node.bodyText || "";
  const footerText = node.footerText || "";

  // Baileys has no native CTA tiles, so every button becomes a labelled
  // line in the message. The customer still gets the right affordance
  // because:
  //   - `tel:`-style numbers are dialable in WhatsApp
  //   - codes wrapped in backticks render monospaced and copy on long-press
  //   - bare URLs are auto-linkified
  let messageText = "";
  if (headerText) messageText += `*${headerText}*\n\n`;
  if (bodyText)   messageText += `${bodyText}\n\n`;

  const lines = [];
  for (const a of actions.slice(0, 3)) {
    const t = (a.type || "url").toLowerCase();
    const label = a.label || (t === "copy" ? "Copy" : t === "phone" || t === "call_now" ? "Call" : "Visit");
    const value = (a.value || "").trim();
    if (!value) continue;
    if (t === "phone" || t === "call_now") {
      const dial = value.replace(/^tel:/i, "");
      lines.push(`☏ ${label}: ${dial}`);
    } else if (t === "copy") {
      lines.push(`${label}:\n\`${value}\``);
    } else {
      lines.push(`${label}: ${value}`);
    }
  }
  if (lines.length) messageText += lines.join("\n\n");
  if (footerText) messageText += `\n\n_${footerText}_`;

  // WABA branch — Meta has a native interactive.cta_url message that
  // renders a tap-to-open button instead of a labelled text line. We
  // pick the FIRST url action and emit the proper interactive payload;
  // remaining actions fall back to text so multi-CTA still degrades
  // gracefully (Meta only supports one URL button per cta_url message).
  const settings = await resolveWabaSettings(session, appLocals.appDomainName, senderPhoneNumber);
  // Engine flag — true for WABA Cloud OR Twilio (both go through their
  // REST APIs, not the local Baileys sock). The sendWaba* helpers
  // dispatch internally based on settings, so we only need a single
  // bool for "use remote API" at each call site. Renamed from useWaba
  // → useRemote in 2026-05-27 audit but kept the var name for ABI
  // compatibility with the 13 existing call sites.
  const useWaba  = settings && (
    (settings.use_facebook_api && settings.facebook_phone_id) ||
    (settings.use_twilio && settings.twilio_account_sid)
  );

  if (useWaba) {
    const urlAction = actions.find((a) => {
      const t = (a.type || 'url').toLowerCase();
      return (t === 'url' || t === 'visit') && /^https?:\/\//.test(String(a.value || ''));
    });
    if (urlAction) {
      // Send the cta_url interactive message. Append leftover actions
      // as plain-text suffix so phone/copy CTAs aren't lost.
      const leftover = actions.filter((a) => a !== urlAction);
      let extra = '';
      for (const a of leftover.slice(0, 2)) {
        const t = (a.type || 'url').toLowerCase();
        if (t === 'phone' || t === 'call_now') extra += `\n☏ ${a.label || 'Call'}: ${String(a.value).replace(/^tel:/i, '')}`;
        else if (t === 'copy')                  extra += `\n${a.label || 'Copy'}: \`${a.value}\``;
      }
      const body = ((bodyText ? bodyText + '\n' : '') + extra).trim() || (urlAction.label || 'Visit');
      const r = await sendWabaCtaUrl(finalNumber, body, urlAction.label, urlAction.value, settings, { header: headerText, footer: footerText });
      if (!r.success) console.warn(`[FLOW] cta WABA send failed: ${r.error}`);
      else console.log(`[FLOW] cta SENT via WABA (cta_url)`);
    } else {
      // No URL action — fall back to plain text. Meta has no `cta_phone`
      // or `cta_copy` interactive (those exist as TEMPLATE buttons only).
      await sendWabaText(finalNumber, messageText.trim(), settings);
    }
    session.messageHistory.push({ type: 'sent', message: messageText.trim(), timestamp: moment().format() });
  } else if (messageText.trim() && sock) {
    await flowSockSend(sock, settings, finalNumber, { text: _appendFooterToText(messageText.trim(), settings) });
    session.messageHistory.push({
      type: "sent",
      message: messageText.trim(),
      timestamp: moment().format(),
    });
  }

  await moveToNextNode(
    node.id,
    session.flowData,
    targetPhoneNumber,
    senderPhoneNumber,
    sock,
    appLocals,
    sessionKey
  );
}

// ✅ STEP 17: Execute Location Node
async function executeLocationNode(
  node,
  finalNumber,
  sock,
  session,
  targetPhoneNumber,
  senderPhoneNumber,
  appLocals,
  sessionKey
) {
  // FlowNormalizer emits `title` for the place name (matching the
  // React builder's field). Older flows used `locationName` or `name`.
  const lat = parseFloat(node.latitude) || 0;
  const lng = parseFloat(node.longitude) || 0;
  const name = node.title || node.locationName || node.name || "";
  const address = node.address || "";
  console.log(`[FLOW] location node=${node.id} name="${name}" lat=${lat} lng=${lng}`);

  const settings = await resolveWabaSettings(session, appLocals.appDomainName, senderPhoneNumber);
  // Engine flag — true for WABA Cloud OR Twilio (both go through their
  // REST APIs, not the local Baileys sock). The sendWaba* helpers
  // dispatch internally based on settings, so we only need a single
  // bool for "use remote API" at each call site. Renamed from useWaba
  // → useRemote in 2026-05-27 audit but kept the var name for ABI
  // compatibility with the 13 existing call sites.
  const useWaba  = settings && (
    (settings.use_facebook_api && settings.facebook_phone_id) ||
    (settings.use_twilio && settings.twilio_account_sid)
  );

  try {
    if (useWaba) {
      const r = await sendWabaLocation(finalNumber, lat, lng, name, address, settings);
      if (!r.success) throw new Error(r.error || 'WABA location send failed');
      console.log(`[FLOW] location SENT via WABA`);
    } else if (sock) {
      await flowSockSend(sock, settings, finalNumber, {
        location: {
          degreesLatitude: lat,
          degreesLongitude: lng,
          name: name,
          address: address,
        },
      });
    } else {
      throw new Error('No transport for location send');
    }
    session.messageHistory.push({
      type: "sent",
      message: `Location: ${name || `${lat},${lng}`}`,
      timestamp: moment().format(),
    });
  } catch (error) {
    console.error("❌ Error sending location:", error.message);
    // Fallback to text with Google Maps link.
    const mapsUrl = `https://maps.google.com/?q=${lat},${lng}`;
    let fallback = name ? `📍 *${name}*\n` : "📍 Location\n";
    if (address) fallback += `${address}\n`;
    fallback += mapsUrl;
    if (useWaba) {
      await sendWabaText(finalNumber, fallback, settings);
    } else if (sock) {
      await flowSockSend(sock, settings, finalNumber, { text: _appendFooterToText(fallback, settings) });
    }
  }

  await moveToNextNode(
    node.id,
    session.flowData,
    targetPhoneNumber,
    senderPhoneNumber,
    sock,
    appLocals,
    sessionKey
  );
}

// ✅ STEP 18: Execute Chatbot Node (starts a sub-flow)
async function executeChatbotNode(
  node,
  finalNumber,
  sock,
  session,
  targetPhoneNumber,
  senderPhoneNumber,
  appLocals,
  sessionKey
) {
  // AI-Agent hand-off. The builder's "Chatbot" node attaches a Team-Inbox AI
  // Agent (node.agentId / node.botId — see FlowNormalizer case 'chatbot'). Hand
  // the conversation to that agent: Laravel assigns it + fires the FIRST reply
  // through AiAgentService::respondIfAssigned (the exact path Team Inbox uses,
  // so the agent's persona + knowledge base apply), then the agent answers every
  // following message on the normal inbound path. We end the flow session so the
  // customer's next messages route to the agent, not back into the flow. Falls
  // through to the legacy sub-flow behaviour when no agent id is set.
  const agentId = String(node.agentId || node.botId || "").trim();
  if (agentId && agentId !== "0") {
    console.log(`[FLOW] chatbot node=${node.id} → hand off to AI agent ${agentId}`);
    try {
      const r = await axios.post(
        `${appLocals.appDomainName}/api/flow-node/ai-agent-reply`,
        {
          workspace_id: session.flowData?.workspace_id || 0,
          agent_id: agentId,
          customer_phone: targetPhoneNumber,
        },
        {
          timeout: 30000,
          headers: { "X-Node-Token": process.env.NODE_WEBHOOK_TOKEN || "" },
        }
      );
      console.log(
        `[FLOW] chatbot agent hand-off ok=${r.data?.ok} replied=${r.data?.replied} agent=${r.data?.agent || ""}`
      );
    } catch (e) {
      console.warn(
        `[FLOW] chatbot agent hand-off FAILED: ${e?.response?.data?.error || e?.message}`
      );
    }
    // The agent now owns the conversation (assignee_agent_id set) — end the flow
    // session so the customer's next messages go to the agent via the normal
    // respondIfAssigned inbound path.
    endFlowSession(appLocals, sessionKey);
    return;
  }

  const subFlowId = node.selectedFlowId || node.flowId;

  if (!subFlowId) {
    console.warn("⚠️ Chatbot node has no sub-flow ID, skipping");
    await moveToNextNode(
      node.id,
      session.flowData,
      targetPhoneNumber,
      senderPhoneNumber,
      sock,
      appLocals,
      sessionKey
    );
    return;
  }

  try {
    // Fetch sub-flow data from API
    const flowResponse = await axios.get(
      `${appLocals.appDomainName}/api/flows/${subFlowId}`,
      // /api/flows/{id} requires the Node token — without it Laravel returns
      // 401 and the sub-flow silently "not found" (the bug that made Run
      // sub-flow do nothing).
      { headers: { 'X-Node-Token': process.env.NODE_WEBHOOK_TOKEN || '' } }
    );

    if (!flowResponse.data.success) {
      console.error("❌ Sub-flow not found:", subFlowId);
      await moveToNextNode(
        node.id,
        session.flowData,
        targetPhoneNumber,
        senderPhoneNumber,
        sock,
        appLocals,
        sessionKey
      );
      return;
    }

    const subFlowData = flowResponse.data.data.flow_data;

    // Replace current session's flow data with sub-flow
    session.flowData = subFlowData;
    session.flowId = subFlowId;

    // Start from first node of sub-flow
    const startNode = subFlowData.flowNodes[0];
    if (startNode) {
      await executeFlowNode(
        startNode,
        targetPhoneNumber,
        senderPhoneNumber,
        sock,
        appLocals,
        sessionKey
      );
    } else {
      console.warn("⚠️ Sub-flow has no nodes");
      endFlowSession(appLocals, sessionKey);
    }
  } catch (error) {
    console.error("Error loading sub-flow:", error.message);
    await moveToNextNode(
      node.id,
      session.flowData,
      targetPhoneNumber,
      senderPhoneNumber,
      sock,
      appLocals,
      sessionKey
    );
  }
}

// ✅ Media node — single image/video/audio/document send. Mirrors the
// per-reply branch in executeMessageNode, but for a standalone Media
// node where the builder saved one media item directly on the node.
async function executeMediaNode(
  node, finalNumber, sock, session,
  targetPhoneNumber, senderPhoneNumber, appLocals, sessionKey,
) {
  const kind     = String(node.mediaKind || 'image').toLowerCase();
  let   url      = String(node.mediaUrl || '');
  // Safety net: a relative path ("/storage/..") can't be fetched by Baileys/
  // WABA, so the media silently never sends. FlowNormalizer also absolutises
  // server-side, but this guards old/cached flows + any other field shape.
  if (url && !/^https?:\/\//i.test(url) && !url.startsWith('data:')) {
    const base = String(appLocals.appDomainName || '').replace(/\/+$/, '');
    if (base) url = base + (url.startsWith('/') ? '' : '/') + url;
  }
  const caption  = String(node.mediaCaption || '');
  const filename = String(node.mediaFilename || '');
  const mimetype = String(node.mediaMimetype || '');

  console.log(`[FLOW] media node=${node.id} kind=${kind} url_len=${url.length} caption_len=${caption.length}`);
  if (!url) {
    console.warn(`[FLOW] media node=${node.id} has no URL — skipping`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  const settings = await resolveWabaSettings(session, appLocals.appDomainName, senderPhoneNumber);
  // Engine flag — true for WABA Cloud OR Twilio (both go through their
  // REST APIs, not the local Baileys sock). The sendWaba* helpers
  // dispatch internally based on settings, so we only need a single
  // bool for "use remote API" at each call site. Renamed from useWaba
  // → useRemote in 2026-05-27 audit but kept the var name for ABI
  // compatibility with the 13 existing call sites.
  const useWaba  = settings && (
    (settings.use_facebook_api && settings.facebook_phone_id) ||
    (settings.use_twilio && settings.twilio_account_sid)
  );

  if (useWaba) {
    const r = await sendWabaMedia(finalNumber, kind, url, caption, filename, settings);
    if (!r.success) console.warn(`[FLOW] media WABA send failed: ${r.error}`);
    else console.log(`[FLOW] media SENT via WABA (${kind})`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  if (!sock) {
    console.warn(`[FLOW] media node=${node.id} has no sock — skipping`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  try {
    let payload;
    if (kind === 'image') {
      payload = { image: { url } };
      if (caption) payload.caption = caption;
    } else if (kind === 'video') {
      payload = { video: { url } };
      if (caption) payload.caption = caption;
    } else if (kind === 'audio') {
      payload = { audio: { url }, mimetype: mimetype || 'audio/mpeg' };
    } else {
      // document — pdf, doc, anything else
      payload = { document: { url }, mimetype: mimetype || 'application/pdf' };
      if (filename) payload.fileName = filename;
    }
    console.log(`[FLOW] media → sendMessage(${kind})`);
    await flowSockSend(sock, settings, finalNumber, payload);
    console.log(`[FLOW] media SENT ok`);
    session.messageHistory.push({
      type: 'sent',
      message: '[' + kind + '] ' + (filename || url),
      timestamp: moment().format(),
    });
  } catch (e) {
    console.error(`[FLOW] media SEND FAILED: ${e?.message}`);
  }

  await moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
}

// ✅ WhatsApp Form node — sends a Meta-published interactive form to
// the customer and parks the session waiting for the submission.
//
// Sending happens DIRECTLY from Node via the existing
// `sendMessageViaFacebookApi` helper (the same one campaignService uses
// for WABA broadcasts). Per-workspace credentials are pulled from
// Laravel's /api/waba-creds endpoint (encrypted column, decrypted +
// shipped over X-Node-Token). No Meta-side round-trip through Laravel.
//
// On submission, Laravel's webhook handler pings our
// /api/flow/resume-form endpoint with the answers → resumeForm()
// stamps them into session.userVariables → flow advances.
async function executeWaFormNode(
  node, finalNumber, sock, session,
  targetPhoneNumber, senderPhoneNumber, appLocals, sessionKey,
) {
  const formId     = String(node.formId || '').trim();
  const metaFlowId = String(node.metaFlowId || '').trim();
  const bodyText   = String(node.bodyText || 'Please tap below to fill out our form.');
  const ctaLabel   = String(node.ctaLabel || 'Open form');
  const workspaceId = session.flowData?.workspace_id || 0;

  console.log(`[FLOW] wa_form node=${node.id} form_id=${formId} meta_flow_id=${metaFlowId} ws=${workspaceId}`);

  if (!formId || !metaFlowId) {
    console.warn(`[FLOW] wa_form node=${node.id} not configured or form not published — skipping`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }
  if (!workspaceId) {
    console.warn(`[FLOW] wa_form node=${node.id} no workspace_id — skipping`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  // 1. Pull workspace WABA creds from Laravel (decrypted access_token +
  // phone_number_id). One quick GET; cached by Laravel-side server.
  let creds;
  try {
    const credsRes = await axios.get(
      `${appLocals.appDomainName}/api/waba-creds`,
      {
        params:  { workspace_id: workspaceId },
        timeout: 5000,
        headers: { 'X-Node-Token': process.env.NODE_WEBHOOK_TOKEN || '' },
      },
    );
    creds = credsRes.data;
  } catch (e) {
    console.warn(`[FLOW] wa_form creds fetch failed: ${e?.response?.data?.error || e?.message}`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }
  if (!creds?.ok || !creds.access_token || !creds.phone_number_id) {
    console.warn(`[FLOW] wa_form workspace ${workspaceId} has no WABA creds — skipping`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  // 2. flow_token format mirrors what WaFormSubmissionService expects:
  //    `form-<form_id>-<session_key>` — parsed back out on submission
  //    to find the form + resume the right paused flow.
  const flowToken = `form-${formId}-${sessionKey}`;

  // 3. Send the interactive flow message DIRECTLY to Meta from Node.
  //    Uses the same `sendMessageViaFacebookApi` helper that campaign
  //    sends use, so all Meta API quirks (rate-limit retries, error
  //    logging, etc.) get applied consistently.
  const messageData = {
    type: 'interactive',
    interactive: {
      type: 'flow',
      body: { text: bodyText },
      action: {
        name: 'flow',
        parameters: {
          flow_message_version: '3',
          flow_token:           flowToken,
          flow_id:              metaFlowId,
          flow_cta:             ctaLabel,
          flow_action:          'navigate',
          mode:                 'published',
        },
      },
    },
  };
  const sendSettings = {
    facebook_phone_id:  creds.phone_number_id,
    facebook_api_token: creds.access_token,
  };

  // DIAGNOSTIC — full context of the outgoing flow send so a "form never
  // arrived" can be traced: who it's going to, which Meta flow id, on which
  // phone_number_id, and (on failure) Meta's EXACT error (draft-not-published,
  // bad flow_id, recipient not reachable, flow_message_version, etc.).
  console.log(`[FLOW] wa_form SENDING to=${targetPhoneNumber} metaFlowId=${metaFlowId} phone_id=${sendSettings.facebook_phone_id} mode=published cta="${ctaLabel}" flow_token=${flowToken}`);
  try {
    const result = await sendMessageViaFacebookApi(targetPhoneNumber, messageData, sendSettings);
    if (!result?.success) {
      console.warn(`[FLOW] wa_form Meta send FAILED to=${targetPhoneNumber} metaFlowId=${metaFlowId} :: ` + JSON.stringify({
        error:    result?.error,
        code:     result?.code,
        response: result?.response ?? result?.data ?? result?.meta ?? null,
      }));
      return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
    }
    console.log(`[FLOW] wa_form SENT flow_token=${flowToken} msg_id=${result.messageId}`);
    // Mirror the form send into the team inbox as an outbound message so the
    // operator sees "Open form" in the thread — the node sends the interactive
    // flow message via Meta DIRECTLY, bypassing the normal flow-reply mirror.
    await mirrorFlowOutboundToInbox(
      { __appDomainName: appLocals.appDomainName, __devicePhone: senderPhoneNumber },
      targetPhoneNumber,
      bodyText,
      null,
      { buttons: [{ text: ctaLabel }] },
    );
  } catch (e) {
    console.warn(`[FLOW] wa_form send EXCEPTION to=${targetPhoneNumber} metaFlowId=${metaFlowId}: ${e?.message} :: ` + JSON.stringify(e?.response?.data || {}));
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  // 4. Park the session. Submission webhook resumes us through `out`
  //    once the customer fills + submits the form.
  session.waitingForInput = {
    nodeId:       node.id,
    variable:     'form_submission',
    nextNodeType: 'WaForm',
    formId,
    metaFlowId,
    flowToken,
    startedAt:    Date.now(),
  };
}

// ✅ Google Meet node — mint a Calendar+Meet link via Laravel and ship
// it to the customer. Laravel side uses
// GoogleCalendarService::createMeetEvent which posts an event with
// conferenceData.createRequest + conferenceDataVersion=1, then returns
// hangoutLink. We substitute {{meet_link}} and {{meet_start}} into the
// flow author's message template before sending.
async function executeGoogleMeetNode(
  node, finalNumber, sock, session,
  targetPhoneNumber, senderPhoneNumber, appLocals, sessionKey,
) {
  const title           = String(node.title || 'WhatsApp consultation');
  const durationMinutes = Math.max(5, Number(node.durationMinutes || 30));
  const leadMinutes     = Math.max(0, Number(node.leadMinutes || 5));
  const sendInvite      = !!node.sendCalendarInvite;
  const tpl             = String(node.messageTemplate
    || 'Your meeting link: {{meet_link}}\nStarts at {{meet_start}}');
  const wsId            = session.flowData?.workspace_id || 0;

  // Merge-tag substitution for the title BEFORE we send to Google so
  // operators can use {{name}} / {{user_message}} in the event summary.
  const subst = (s) => String(s || "").replace(/\{\{\s*([^{}]+?)\s*\}\}/g, (_, k) =>
    String(session.userVariables?.[k] ?? session.vars?.[k] ?? ""));

  const startAt = new Date(Date.now() + leadMinutes * 60_000);
  const endAt   = new Date(startAt.getTime() + durationMinutes * 60_000);

  console.log(`[FLOW] google_meet node=${node.id} title="${title}" lead=${leadMinutes}m dur=${durationMinutes}m ws=${wsId}`);
  if (!wsId) {
    console.warn(`[FLOW] google_meet has no workspace_id — skipping`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  let meetUrl = '';
  let eventId = '';
  try {
    const resp = await axios.post(
      `${appLocals.appDomainName}/api/flow-node/google-meet`,
      {
        workspace_id: wsId,
        title:        subst(title),
        start_at:     startAt.toISOString(),
        end_at:       endAt.toISOString(),
        send_invites: sendInvite,
        time_zone:    'UTC',
      },
      { timeout: 15000, headers: { 'X-Node-Token': process.env.NODE_WEBHOOK_TOKEN || '' } },
    );
    if (resp.data?.ok) {
      meetUrl = String(resp.data.meet_url || '');
      eventId = String(resp.data.event_id || '');
    } else {
      console.warn(`[FLOW] google_meet mint failed: ${resp.data?.error || 'unknown'} ${resp.data?.message || ''}`);
    }
  } catch (e) {
    console.warn(`[FLOW] google_meet HTTP failed: ${e?.response?.data?.message || e?.message}`);
  }

  // Resolve WABA settings ONCE before either send path.
  const settings = await resolveWabaSettings(session, appLocals.appDomainName, senderPhoneNumber);
  // Engine flag — true for WABA Cloud OR Twilio (both go through their
  // REST APIs, not the local Baileys sock). The sendWaba* helpers
  // dispatch internally based on settings, so we only need a single
  // bool for "use remote API" at each call site. Renamed from useWaba
  // → useRemote in 2026-05-27 audit but kept the var name for ABI
  // compatibility with the 13 existing call sites.
  const useWaba  = settings && (
    (settings.use_facebook_api && settings.facebook_phone_id) ||
    (settings.use_twilio && settings.twilio_account_sid)
  );

  if (!meetUrl) {
    const errText = "Sorry, couldn't generate a meeting link right now. Please try again later.";
    if (useWaba)      await sendWabaText(finalNumber, errText, settings);
    else if (sock)    await flowSockSend(sock, settings, finalNumber, { text: _appendFooterToText(errText, settings) });
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  // Stash on session vars so downstream nodes can `{{meet_link}}` etc.
  session.userVariables = session.userVariables || {};
  session.userVariables.meet_link  = meetUrl;
  session.userVariables.meet_start = startAt.toISOString().replace('T', ' ').substring(0, 16) + ' UTC';
  session.userVariables.meet_event_id = eventId;

  const body = subst(tpl)
    .replace(/\{\{\s*meet_link\s*\}\}/g, meetUrl)
    .replace(/\{\{\s*meet_start\s*\}\}/g, session.userVariables.meet_start);

  try {
    if (useWaba) {
      const r = await sendWabaText(finalNumber, body, settings);
      if (!r.success) console.warn(`[FLOW] google_meet WABA send failed: ${r.error}`);
      else console.log(`[FLOW] google_meet SENT via WABA url=${meetUrl}`);
    } else if (sock) {
      console.log(`[FLOW] google_meet → sendMessage(text) url=${meetUrl}`);
      await flowSockSend(sock, settings, finalNumber, { text: body });
    }
    session.messageHistory.push({ type: 'sent', message: body, timestamp: moment().format() });
  } catch (e) {
    console.error(`[FLOW] google_meet SEND FAILED: ${e?.message}`);
  }
  await moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
}

// ───────────────────── GOOGLE FLOW NODES ─────────────────────
// All three Google nodes (Sheets / Docs / Form) round-trip through
// Laravel because (1) the OAuth tokens live in workspaces.appointment_settings
// encrypted-at-rest, decryption requires Laravel's APP_KEY, and (2)
// keeping Google API credentials out of the Node env means rotating
// them never requires a Node redeploy.

// Best-effort JSON parse of an AI reply: tolerates ```json fences``` and a
// JSON object embedded in surrounding prose (the AI node uses this to explode
// an extracted record into per-field {{var.key}} variables). Returns null when
// the text isn't JSON, so plain replies stay untouched.
function _tryParseJson(s) {
  if (typeof s !== 'string') return null;
  let t = s.trim();
  const fence = t.match(/^```(?:json)?\s*([\s\S]*?)\s*```$/i);
  if (fence) t = fence[1].trim();
  try { return JSON.parse(t); } catch (_) {}
  const block = t.match(/\{[\s\S]*\}/);
  if (block) { try { return JSON.parse(block[0]); } catch (_) {} }
  return null;
}

// Flatten a resolved variable to a readable cell value. Some variables hold
// objects/arrays (a saved form-submission object, an AI `reply` record, a
// multi-select) — String(obj) => "[object Object]", which was leaking into the
// sheet. Pick the human field or JSON it instead.
const _gFlatten = (v) => {
  if (v === null || v === undefined) return '';
  if (Array.isArray(v)) return v.map(_gFlatten).filter(s => s !== '').join(', ');
  if (typeof v === 'object') {
    const pick = v.title ?? v.name ?? v.label ?? v.value ?? v.text ?? v.email ?? v.id;
    if (pick != null && typeof pick !== 'object') return String(pick);
    try { return JSON.stringify(v); } catch (_) { return ''; }
  }
  return String(v);
};
// System merge tags for Sheets columns. Resolved ONLY when the flow has not
// set a variable of the same name, so an author's own {{timestamp}} still wins.
const _gSystemVar = (k) => {
  const key = String(k).toLowerCase().trim();
  if (!['timestamp', 'execution_timestamp', 'now', 'date', 'time', 'iso_timestamp'].includes(key)) return undefined;
  const now = moment();
  switch (key) {
    case 'date':          return now.format('YYYY-MM-DD');
    case 'time':          return now.format('HH:mm:ss');
    case 'iso_timestamp': return now.toISOString();
    default:              return now.format('YYYY-MM-DD HH:mm:ss'); // timestamp / execution_timestamp / now
  }
};

const _gSubst = (session, s) => {
  // NEVER String(object) — that is what put "[object Object]" in the sheet.
  if (s === null || s === undefined) return '';
  if (typeof s === 'object') return _gFlatten(s);
  return String(s).replace(/\{\{\s*([^{}]+?)\s*\}\}/g, (_, k) => {
    const own = session?.userVariables?.[k] ?? session?.vars?.[k];
    if (own !== undefined && own !== null) return _gFlatten(own);
    const sys = _gSystemVar(k);
    return sys !== undefined ? sys : '';
  });
};

// A column is either a legacy plain string ('{{name}}') or an object
// ({column, value}). The old `c?.value ?? c` fell back to the OBJECT ITSELF
// whenever value was null/undefined — String({column:'Timestamp',value:null})
// === "[object Object]" landed in the cell. Only a NON-object column may stand
// in for its own value; an object with a blank value means an empty cell.
const _gCellSource = (c) => {
  if (c === null || c === undefined) return '';
  if (typeof c === 'object') return Array.isArray(c) ? '' : (c.value ?? '');
  return c;
};

/** Build one sheet row from the node's column list. Exported for regression tests. */
export function buildSheetRowValues(session, cols) {
  return (Array.isArray(cols) ? cols : []).map((c) => _gSubst(session, _gCellSource(c)));
}

// ✅ Google Sheets node — write OR read a row in the workspace's sheet.
// Write mode appends one row; values come from the node's column list
// with merge-tag substitution per column. Read mode looks up a row by
// matchColumn=matchValue and stashes every cell into session vars under
// `saveAs.<header>`.
async function executeGoogleSheetsNode(
  node, finalNumber, sock, session,
  targetPhoneNumber, senderPhoneNumber, appLocals, sessionKey,
) {
  const wsId = session.flowData?.workspace_id || 0;
  const mode = String(node.mode || 'write').toLowerCase();
  const sheetId = String(node.sheetId || '').trim();
  const tabName = String(node.tabName || '').trim();
  if (!wsId || !sheetId) {
    console.warn(`[FLOW] google_sheets node=${node.id} missing workspace or sheetId — skipping`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  if (mode === 'read') {
    const matchColumn = String(node.matchColumn || '');
    const matchValue  = _gSubst(session, node.matchValue || '');
    const saveAs      = String(node.saveAs || 'sheet_row');
    try {
      const r = await axios.post(
        `${appLocals.appDomainName}/api/flow-node/google/sheet-read`,
        { workspace_id: wsId, sheet_id: sheetId, tab_name: tabName,
          match_column: matchColumn, match_value: matchValue, limit: 1 },
        { timeout: 15000, headers: { 'X-Node-Token': process.env.NODE_WEBHOOK_TOKEN || '' } },
      );
      if (r.data?.ok && (r.data.rows || []).length > 0) {
        const row = r.data.rows[0];
        session.userVariables = session.userVariables || {};
        // Stash both as a single object (for {{saveAs}}) AND as flattened
        // dotted keys so flows can pick out individual columns by header.
        session.userVariables[saveAs] = row;
        for (const [k, v] of Object.entries(row)) {
          session.userVariables[`${saveAs}.${k}`] = String(v ?? '');
        }
        console.log(`[FLOW] google_sheets READ matched row, ${Object.keys(row).length} cells → ${saveAs}.*`);
      } else {
        console.log(`[FLOW] google_sheets READ no match for ${matchColumn}=${matchValue}`);
      }
    } catch (e) {
      console.warn(`[FLOW] google_sheets read failed: ${e?.response?.data?.error || e?.message}`);
    }
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  // Write mode (default).
  const cols = Array.isArray(node.columns) ? node.columns : [];
  if (cols.length === 0) {
    console.warn(`[FLOW] google_sheets WRITE node=${node.id} has no columns — skipping`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  // Opt-in header mapping: OFF (default) = append `values` positionally (legacy).
  // ON = also send each column's HEADER label so Laravel writes each value under
  // the sheet column with that header, in any order. Values stay index-aligned
  // to column_headers so Laravel can pair them.
  const matchByHeader = node.matchByHeader === true;
  const columnHeaders = cols.map((c) =>
    (c && typeof c === 'object' && !Array.isArray(c)) ? String(c.header || '') : '');

  // Single helper so the single-row and multi-row paths post identically.
  const _writeRow = async (values) => {
    try {
      const r = await axios.post(
        `${appLocals.appDomainName}/api/flow-node/google/sheet-write`,
        { workspace_id: wsId, sheet_id: sheetId, tab_name: tabName, values,
          column_headers: columnHeaders, match_by_header: matchByHeader },
        { timeout: 15000, headers: { 'X-Node-Token': process.env.NODE_WEBHOOK_TOKEN || '' } },
      );
      if (r.data?.ok) return true;
      console.warn(`[FLOW] google_sheets WRITE failed: ${r.data?.error || 'unknown'}`);
      return false;
    } catch (e) {
      console.warn(`[FLOW] google_sheets WRITE exception: ${e?.response?.data?.error || e?.message}`);
      return false;
    }
  };

  // ADDITIVE multi-row support: if a column references {{prefix.field}} and an
  // AI node stashed `prefix.__rows` (a list of records from one message), write
  // ONE row per record. When there's no such array — i.e. every existing flow —
  // we fall through to the unchanged single-row write below.
  let rowsArray = null, rowPrefix = null;
  for (const c of cols) {
    const m = String(_gCellSource(c)).match(/\{\{\s*([A-Za-z0-9_]+)\.[\w.]+\s*\}\}/);
    if (m) {
      const arr = session.userVariables[`${m[1]}.__rows`];
      if (Array.isArray(arr) && arr.length) { rowsArray = arr; rowPrefix = m[1]; break; }
    }
  }

  if (rowsArray) {
    let ok = 0;
    for (const rec of rowsArray) {
      if (rec && typeof rec === 'object' && !Array.isArray(rec)) {
        for (const [k, v] of Object.entries(rec)) {
          session.userVariables[`${rowPrefix}.${k}`] =
            (v === null || v === undefined) ? '' :
            (typeof v === 'object' ? JSON.stringify(v) : String(v));
        }
      }
      const values = buildSheetRowValues(session, cols);
      console.log(`[FLOWTRACE] sheet multi-row values=${JSON.stringify(values)}`);
      if (await _writeRow(values)) ok++;
    }
    console.log(`[FLOW] google_sheets WRITE appended ${ok}/${rowsArray.length} row(s) to ${sheetId} (${tabName || 'default'})`);
  } else {
    const values = buildSheetRowValues(session, cols);
    console.log(`[FLOWTRACE] sheet single-row values=${JSON.stringify(values)} (if these are blank/"{{reply.X}}", the AI extraction didn't populate reply.* — see the [FLOWTRACE] ai node line)`);
    if (await _writeRow(values)) {
      console.log(`[FLOW] google_sheets WRITE appended to ${sheetId} (${tabName || 'default'})`);
    }
  }

  return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
}

// ✅ Google Docs node — copy a template doc, fill {{placeholders}},
// share anyone-with-link, send the link to the customer. The whole
// thing is one HTTP call to Laravel which orchestrates the 3 Google
// API calls (copy + batchUpdate + permissions).
async function executeGoogleDocsNode(
  node, finalNumber, sock, session,
  targetPhoneNumber, senderPhoneNumber, appLocals, sessionKey,
) {
  const wsId = session.flowData?.workspace_id || 0;
  const templateId = String(node.templateId || '').trim();
  if (!wsId || !templateId) {
    console.warn(`[FLOW] google_docs node=${node.id} missing workspace or templateId — skipping`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  const newTitle = _gSubst(session, node.newTitle || 'Document');
  const placeholders = Array.isArray(node.placeholders) ? node.placeholders : [];
  const vars = {};
  // Always include the contact phone + every flow var as a placeholder,
  // then let explicit `placeholders` overrides win. {key,value} shape.
  for (const [k, v] of Object.entries(session.userVariables || {})) {
    if (typeof v === 'string' || typeof v === 'number') vars[k] = String(v);
  }
  for (const p of placeholders) {
    if (p && p.key) vars[p.key] = _gSubst(session, p.value || '');
  }

  let docUrl = '';
  try {
    const r = await axios.post(
      `${appLocals.appDomainName}/api/flow-node/google/doc-generate`,
      { workspace_id: wsId, template_id: templateId, new_title: newTitle,
        vars, shareable: node.shareable !== false },
      { timeout: 25000, headers: { 'X-Node-Token': process.env.NODE_WEBHOOK_TOKEN || '' } },
    );
    if (r.data?.ok) {
      docUrl = String(r.data.url || '');
    } else {
      console.warn(`[FLOW] google_docs generate failed: ${r.data?.error || 'unknown'}`);
    }
  } catch (e) {
    console.warn(`[FLOW] google_docs exception: ${e?.response?.data?.error || e?.message}`);
  }

  const settings = await resolveWabaSettings(session, appLocals.appDomainName, senderPhoneNumber);
  // Engine flag — true for WABA Cloud OR Twilio (sendWaba* dispatches internally).
  const useWaba = settings && (
    (settings.use_facebook_api && settings.facebook_phone_id) ||
    (settings.use_twilio && settings.twilio_account_sid)
  );

  if (!docUrl) {
    const errText = "Sorry, couldn't generate your document right now. Please try again later.";
    if (useWaba)   await sendWabaText(finalNumber, errText, settings);
    else if (sock) await flowSockSend(sock, settings, finalNumber, { text: _appendFooterToText(errText, settings) });
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  // Stash so downstream nodes can use {{doc_url}}.
  const saveAs = String(node.saveAs || 'doc_url');
  session.userVariables = session.userVariables || {};
  session.userVariables[saveAs] = docUrl;
  session.userVariables.doc_url = docUrl;

  const body = _gSubst(session, node.messageTemplate || "Here's your document: {{doc_url}}")
    .replace(/\{\{\s*doc_url\s*\}\}/g, docUrl);

  try {
    if (useWaba)   await sendWabaText(finalNumber, body, settings);
    else if (sock) await flowSockSend(sock, settings, finalNumber, { text: _appendFooterToText(body, settings) });
    session.messageHistory?.push({ type: 'sent', message: body, timestamp: moment().format() });
    console.log(`[FLOW] google_docs SENT url=${docUrl}`);
  } catch (e) {
    console.error(`[FLOW] google_docs SEND FAILED: ${e?.message}`);
  }
  await moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
}

// ✅ Deal node (CRM) — create a deal or move an existing one to a stage
// when the flow reaches this node, so a chatbot can qualify a lead and
// push it up the pipeline (the flow → deal direction). Laravel resolves
// the contact by phone and owns the create/move; its observer fires the
// `deal_stage_changed` trigger downstream. Routes through the `created`
// port (1) on success, `error` port (2) on any failure.
async function executeDealNode(
  node, finalNumber, sock, session,
  targetPhoneNumber, senderPhoneNumber, appLocals, sessionKey,
) {
  const wsId    = session.flowData?.workspace_id || 0;
  const action  = String(node.action || 'create').toLowerCase() === 'move' ? 'move' : 'create';
  const stageId = String(node.stageId || '').trim();
  const saveAs  = String(node.saveAs || 'deal_id');

  if (!wsId || !stageId) {
    console.warn(`[FLOW] deal node=${node.id} missing workspace or stageId — error port`);
    return advanceToPort(node.id, 2, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  const dealName = _gSubst(session, node.dealName || '{{contact_name}} — deal');
  // value may carry {{vars}}; keep only digits + dot after substitution.
  const valueStr = _gSubst(session, String(node.dealValue || '')).replace(/[^0-9.]/g, '');
  const value    = valueStr === '' ? null : parseFloat(valueStr);

  try {
    const r = await axios.post(
      `${appLocals.appDomainName}/api/flow-node/deal-action`,
      {
        workspace_id:  wsId,
        action,
        stage_id:      stageId,
        deal_name:     dealName,
        value:         (value !== null && !Number.isNaN(value)) ? value : null,
        owner_user_id: String(node.ownerId || '').trim() || null,
        contact_phone: targetPhoneNumber,
      },
      { timeout: 15000, headers: { 'X-Node-Token': process.env.NODE_WEBHOOK_TOKEN || '' } },
    );

    if (r.data?.ok) {
      session.userVariables = session.userVariables || {};
      session.userVariables[saveAs] = String(r.data.deal_id ?? '');
      console.log(`[FLOW] deal ${action} ok deal_id=${r.data.deal_id} created=${r.data.created} → ${saveAs}`);
      return advanceToPort(node.id, 1, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
    }
    console.warn(`[FLOW] deal ${action} failed: ${r.data?.error || 'unknown'}`);
  } catch (e) {
    console.warn(`[FLOW] deal ${action} exception: ${e?.response?.data?.error || e?.message}`);
  }
  return advanceToPort(node.id, 2, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
}

// ✅ Google Form node — send the form's responder URL, park the
// session, resume on submission. Laravel mints a google_form_sessions
// row tied to (workspace, form, sessionKey) and returns the URL +
// per-row webhook token. We don't need the token here — Laravel's
// webhook lookup uses it to find the row to resume.
//
// On submit: Laravel's POST /api/google/form-response writes answers
// onto the row, then POSTs to /api/flow-resume which calls
// resumeGoogleForm() below.
async function executeGoogleFormNode(
  node, finalNumber, sock, session,
  targetPhoneNumber, senderPhoneNumber, appLocals, sessionKey,
) {
  const wsId   = session.flowData?.workspace_id || 0;
  const formId = String(node.formId || '').trim();
  if (!wsId || !formId) {
    console.warn(`[FLOW] google_form node=${node.id} missing workspace or formId — skipping`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  let formUrl = '';
  try {
    const r = await axios.post(
      `${appLocals.appDomainName}/api/flow-node/google/form-send`,
      {
        workspace_id:    wsId,
        google_form_id:  formId,
        flow_session_id: sessionKey,
        contact_phone:   targetPhoneNumber,
        save_variable:   String(node.saveAs || 'google_form'),
        resume_port:     'submitted',
        expires_in_sec:  Number(node.expiresInSec || 86400),
      },
      { timeout: 15000, headers: { 'X-Node-Token': process.env.NODE_WEBHOOK_TOKEN || '' } },
    );
    if (r.data?.ok) {
      formUrl = String(r.data.form_url || '');
    } else {
      console.warn(`[FLOW] google_form send-prep failed: ${r.data?.error || 'unknown'} ${r.data?.message || ''}`);
    }
  } catch (e) {
    console.warn(`[FLOW] google_form prep exception: ${e?.response?.data?.error || e?.message}`);
  }

  const settings = await resolveWabaSettings(session, appLocals.appDomainName, senderPhoneNumber);
  // Engine flag — true for WABA Cloud OR Twilio (sendWaba* dispatches internally).
  const useWaba = settings && (
    (settings.use_facebook_api && settings.facebook_phone_id) ||
    (settings.use_twilio && settings.twilio_account_sid)
  );

  if (!formUrl) {
    const errText = "Sorry, the form is unavailable right now. Please try again later.";
    if (useWaba)   await sendWabaText(finalNumber, errText, settings);
    else if (sock) await flowSockSend(sock, settings, finalNumber, { text: _appendFooterToText(errText, settings) });
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  // Compose body bubble + the link on its own line so WhatsApp's
  // built-in link preview kicks in.
  const body = _gSubst(session, node.bodyText || 'Please fill out this short form:') + '\n\n' + formUrl;
  try {
    if (useWaba)   await sendWabaText(finalNumber, body, settings);
    else if (sock) await flowSockSend(sock, settings, finalNumber, { text: _appendFooterToText(body, settings) });
    session.messageHistory?.push({ type: 'sent', message: body, timestamp: moment().format() });
    console.log(`[FLOW] google_form SENT url=${formUrl} session=${sessionKey}`);
  } catch (e) {
    console.error(`[FLOW] google_form SEND FAILED: ${e?.message}`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }

  // Pause until the Apps Script webhook fires resumeGoogleForm().
  session.waitingForInput = {
    nodeId:       node.id,
    variable:     String(node.saveAs || 'google_form'),
    nextNodeType: 'GoogleForm',
    formId,
    startedAt:    Date.now(),
  };
}

// ✅ Form-submission resume — Laravel calls /api/flow-resume on the
// Node bridge from formResponse(). We stash the answers, then advance
// from the paused google_form node.
export async function resumeGoogleForm({ sessionKey, saveVariable, answers, sock, appLocals }) {
  // Use the canonical session store — same place every other executor
  // reads from. `global.flowSessions` was a fragile bridge.
  const session = appLocals?.activeFlowSessions?.[sessionKey];
  if (!session) {
    console.warn(`[FLOW] google_form resume: session ${sessionKey} not found (already finished?)`);
    return;
  }
  if (!session.waitingForInput || session.waitingForInput.nextNodeType !== 'GoogleForm') {
    console.warn(`[FLOW] google_form resume: session ${sessionKey} not paused on a GoogleForm node (paused on: ${session.waitingForInput?.nextNodeType || 'nothing'})`);
    return;
  }

  session.userVariables = session.userVariables || {};
  const root = String(saveVariable || session.waitingForInput.variable || 'google_form');
  session.userVariables[root] = answers || {};
  // Flatten so flows can pick out individual answers by question title.
  for (const [k, v] of Object.entries(answers || {})) {
    session.userVariables[`${root}.${k}`] = Array.isArray(v) ? v.join(', ') : String(v ?? '');
  }

  const pausedNodeId = session.waitingForInput.nodeId;
  // sessionKey is always `<sender>_<target>` (see startFlow). Derive
  // phones from there rather than trusting transient props the executor
  // happened to stash — same approach moveToNextNode uses everywhere else.
  const [senderPhoneNumber, targetPhoneNumber] = sessionKey.split('_');
  session.waitingForInput = null;

  console.log(`[FLOW] google_form RESUMED session=${sessionKey} answers=${Object.keys(answers || {}).length} → port=submitted`);
  return moveToNextNode(pausedNodeId, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
}

// ✅ Webhook node — fire an outbound HTTP request, save the response
// body into a session variable, advance via the single `out` port.
// Merge tags ({{var}}) get substituted in url + body using the current
// session.userVariables. No queue, no retry — same per-project policy
// as the rest of the runtime; the next-node advance gates on completion.
// Flatten a JSON value into dotted keys under `prefix` so later flow nodes can
// reference individual fields with {{prefix.path}} — e.g. {{response.price}},
// {{response.user.name}}, {{response.items.0.id}} or {{response.items.length}}.
// Depth- and count-capped so a huge API payload can't explode the variable bag.
function flattenJsonInto(target, prefix, value, depth = 0, count = { n: 0 }) {
  if (depth > 6 || count.n > 200) return;
  if (value === null || typeof value !== "object") {
    target[prefix] = value === null ? "" : String(value);
    count.n++;
    return;
  }
  if (Array.isArray(value)) {
    target[`${prefix}.length`] = String(value.length);
    value.forEach((v, i) => flattenJsonInto(target, `${prefix}.${i}`, v, depth + 1, count));
    return;
  }
  for (const [k, v] of Object.entries(value)) {
    flattenJsonInto(target, `${prefix}.${k}`, v, depth + 1, count);
  }
}

// Sandboxed JS "Code" node (n8n-style "Execute Function"). Runs the user's
// JavaScript inside node/services/codeSandbox.js (isolated-vm, env-gated +
// optional dependency — see that file). Whatever the code returns is saved
// to the node's output variable (default {{result}}); each top-level field
// is also exposed as {{result.<key>}} for later nodes, mirroring the AI
// node. This node NEVER sends a message — it's a transform/compute step, so
// the runtime just advances to the 'out' port afterwards.
async function executeCodeNode(node, session) {
  // runUserCode is imported at the TOP of this module (ESM). The old local
  // `require("./codeSandbox")` here threw "require is not defined" in the ES
  // module context, so EVERY Code node failed and the flow stalled on it
  // (client: flow stuck right after the service pick, at the Run code node).
  const code   = String(node.code || node.properties?.code || "");
  const saveAs = String(node.variable || node.properties?.variable || "result").replace(/^@/, "");
  const vars   = session.userVariables || {};
  const inputs = {
    previousResponse: vars[saveAs] ?? null,
    allResponses: vars,
    functionArgs: node.args || node.properties?.args || {},
  };

  let out;
  try {
    const r = await runUserCode(code, inputs, { timeoutMs: 2000, memoryMb: 16 });
    if (!r.ok) {
      console.warn(`[FLOW] code node=${node.id} error (${r.engine}): ${r.error}`);
      out = { ok: false, error: r.error };
    } else {
      out = r.result;
      console.log(`[FLOW] code node=${node.id} ran ok via ${r.engine}`);
    }
  } catch (e) {
    console.warn(`[FLOW] code node=${node.id} threw: ${e?.message || e}`);
    out = { ok: false, error: String(e?.message || e) };
  }

  session.userVariables[saveAs] = out;
  if (out && typeof out === "object" && !Array.isArray(out)) {
    for (const [k, v] of Object.entries(out)) {
      session.userVariables[`${saveAs}.${k}`] = v;
    }
  }
}

// =============================================================
// SSRF guard for outbound HTTP issued from flow nodes (Webhook
// node, remote-media fetch). The URL is fully tenant-controlled
// (built from {{vars}}), so without this a flow could point at
// http://169.254.169.254/... (cloud metadata) or an internal
// service (http://127.0.0.1:PORT/...) and exfiltrate the response
// back to the WhatsApp customer. We resolve the host and refuse
// any private/loopback/link-local/reserved IP and non-http(s)
// scheme. Callers additionally disable redirects + cap size.
// =============================================================
function ssrfIpv4Blocked(ip) {
  const parts = String(ip).split(".").map((n) => Number(n));
  if (parts.length !== 4 || parts.some((n) => Number.isNaN(n) || n < 0 || n > 255)) return true;
  const [a, b] = parts;
  if (a === 0) return true;                         // 0.0.0.0/8
  if (a === 10) return true;                        // 10.0.0.0/8
  if (a === 127) return true;                       // 127.0.0.0/8 loopback
  if (a === 169 && b === 254) return true;          // 169.254.0.0/16 link-local (IMDS)
  if (a === 172 && b >= 16 && b <= 31) return true; // 172.16.0.0/12
  if (a === 192 && b === 168) return true;          // 192.168.0.0/16
  if (a === 192 && b === 0) return true;            // 192.0.0.0/24 + 192.0.2.0/24
  if (a === 100 && b >= 64 && b <= 127) return true; // 100.64.0.0/10 CGNAT
  if (a === 198 && (b === 18 || b === 19)) return true; // 198.18.0.0/15 benchmarking
  if (a >= 224) return true;                        // 224/4 multicast + 240/4 reserved + broadcast
  return false;
}

function ssrfIpBlocked(ip) {
  const raw = String(ip || "").trim().toLowerCase();
  const fam = net.isIP(raw);
  if (fam === 4) return ssrfIpv4Blocked(raw);
  if (fam === 6) {
    // IPv4-mapped / -compatible (::ffff:a.b.c.d) → validate embedded v4.
    const mapped = raw.match(/(?:::ffff:|::)((?:\d{1,3}\.){3}\d{1,3})$/);
    if (mapped) return ssrfIpv4Blocked(mapped[1]);
    const h = raw.split("%")[0]; // strip zone id
    if (h === "::1" || h === "::") return true;      // loopback / unspecified
    if (h.startsWith("fc") || h.startsWith("fd")) return true; // fc00::/7 ULA
    if (/^fe[89ab]/.test(h)) return true;            // fe80::/10 link-local
    if (h.startsWith("ff")) return true;             // ff00::/8 multicast
    if (h.startsWith("2001:db8")) return true;       // documentation
    return false;
  }
  return true; // not a valid IP literal → block (only resolved IPs reach here)
}

async function assertPublicHttpUrl(rawUrl) {
  let u;
  try { u = new URL(String(rawUrl)); } catch { throw new Error("invalid URL"); }
  const scheme = u.protocol.toLowerCase();
  if (scheme !== "http:" && scheme !== "https:") {
    throw new Error(`scheme ${scheme} not allowed`);
  }
  const host = u.hostname.replace(/^\[|\]$/g, "").toLowerCase();
  if (!host) throw new Error("no host");
  if (host === "localhost" || host.endsWith(".localhost") ||
      host.endsWith(".internal") || host.includes("metadata.")) {
    throw new Error("host not allowed");
  }
  let ips = [];
  if (net.isIP(host)) {
    ips = [host];
  } else {
    const recs = await dns.promises.lookup(host, { all: true });
    ips = recs.map((r) => r.address);
  }
  if (!ips.length) throw new Error("host did not resolve");
  for (const ip of ips) {
    if (ssrfIpBlocked(ip)) {
      throw new Error(`host resolves to private/reserved IP (${ip})`);
    }
  }
  return ips;
}

async function executeWebhookNode(
  node, session, targetPhoneNumber, senderPhoneNumber,
  sock, appLocals, sessionKey,
) {
  const subst = (s) => String(s || "").replace(/\{\{\s*([^{}]+?)\s*\}\}/g, (_, k) => {
    return String(session.userVariables?.[k] ?? session.vars?.[k] ?? "");
  });
  // Like subst, but JSON-escapes each value so it is safe INSIDE a "..." string
  // literal in a JSON body. A multi-line order ("order\n5 wings\n3 drumstick")
  // or one with quotes would otherwise inject raw newlines/quotes and break
  // JSON.parse — the webhook then POSTed garbage and order-parse saw no text
  // ("Can't recognise my order"). Used only as a fallback when the raw body
  // fails to parse, so single-line orders are byte-for-byte unchanged.
  const jsonSubst = (s) => String(s || "").replace(/\{\{\s*([^{}]+?)\s*\}\}/g, (_, k) => {
    const v = String(session.userVariables?.[k] ?? session.vars?.[k] ?? "");
    return JSON.stringify(v).slice(1, -1); // drop the wrapping quotes → escaped fragment
  });
  const method      = String(node.method || "POST").toUpperCase();
  const url         = subst(node.url || "");
  const contentType = String(node.contentType || "application/json");
  const saveAs      = String(node.variable || "response");
  if (!url) {
    console.warn(`[Webhook] node ${node.id} has no URL — skipping`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }
  const body = subst(node.body || "");
  // Diagnostic: log EVERY webhook fire so operators can confirm the node ran,
  // see the resolved URL + the substituted body ({{vars}} filled in), and match
  // it to the response/error logged below. Answers the "is my webhook even
  // hit?" question — if there's no [Webhook] FIRING line, the flow never
  // reached this node (check the upstream edges / where the flow stops).
  console.log(`[Webhook] node ${node.id} → FIRING ${method} ${url}`);
  console.log(`[Webhook] node ${node.id} body: ${body.slice(0, 800)}`);
  try {
    // SSRF guard — refuse private/loopback/link-local/reserved targets and
    // non-http(s) schemes before issuing the request. Throws on a bad host.
    await assertPublicHttpUrl(url);
    // Custom headers from the builder (array of {key,value}); values may
    // carry {{vars}}. A custom header can override the default Content-Type.
    const hdrs = { "Content-Type": contentType };
    if (Array.isArray(node.headers)) {
      for (const h of node.headers) {
        const k = String(h?.key || "").trim();
        if (k) hdrs[k] = subst(h?.value || "");
      }
    }
    // maxRedirects:0 fails closed on 3xx so a public host can't 302 us onto an
    // internal one (guard only vetted the initial host). maxContentLength/
    // maxBodyLength cap the response so a giant internal body can't blow memory.
    const cfg  = {
      method, url, timeout: 15000, headers: hdrs,
      maxRedirects: 0,
      maxContentLength: 5 * 1024 * 1024,
      maxBodyLength: 5 * 1024 * 1024,
    };
    if (method === "GET" || method === "HEAD") {
      // no body for GET/HEAD
    } else if (contentType.includes("json")) {
      try { cfg.data = body ? JSON.parse(body) : {}; }
      catch {
        // Raw substitution injected newlines/quotes from a {{var}} (e.g. a
        // multi-line order) that broke the JSON. Retry with values JSON-escaped.
        try {
          const safe = jsonSubst(node.body || "");
          cfg.data = safe ? JSON.parse(safe) : {};
        } catch {
          cfg.data = body; // user really typed non-JSON despite the header — pass raw
        }
      }
    } else {
      cfg.data = body;
    }
    const resp = await axios(cfg);
    const respLen = typeof resp.data === "string" ? resp.data.length : JSON.stringify(resp.data ?? "").length;
    console.log(`[Webhook] node ${node.id} ← HTTP ${resp.status} from ${url} (response ${respLen} bytes)`);
    // Whole response under {{saveAs}} (string), plus every field flattened to
    // dotted keys so {{saveAs.fieldName}} resolves in later message/AI nodes.
    session.userVariables[saveAs] = typeof resp.data === "string"
      ? resp.data
      : JSON.stringify(resp.data);
    if (resp.data && typeof resp.data === "object") {
      flattenJsonInto(session.userVariables, saveAs, resp.data);
    }
  } catch (e) {
    // Enriched failure log: distinguishes an HTTP error (server replied 4xx/5xx
    // — includes the status + error body) from a transport failure (DNS, TLS,
    // timeout, connection refused) or the SSRF guard rejecting a private host.
    const status = e?.response?.status;
    console.warn(`[Webhook] node ${node.id} ${method} ${url} FAILED: ${status ? "HTTP " + status + " — " : ""}${e?.message}`);
    if (e?.response?.data != null) {
      const eb = typeof e.response.data === "string" ? e.response.data : JSON.stringify(e.response.data);
      console.warn(`[Webhook] node ${node.id} error response body: ${String(eb).slice(0, 500)}`);
    }
    session.userVariables[saveAs] = "";
  }
  await moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
}

// ✅ MySQL node — run a READ-ONLY SQL query on an external database. The
// flow engine never opens a DB socket itself (no mysql driver in Node); it
// POSTs the connection config + SQL to Laravel /api/flow-node/mysql-query,
// which runs a SELECT-only query (writes rejected), caps rows, and returns
// JSON. The result is saved into a session variable for later nodes (AI /
// message). {{vars}} in the SQL are substituted first.
async function executeMysqlNode(
  node, session, targetPhoneNumber, senderPhoneNumber,
  sock, appLocals, sessionKey,
) {
  const subst = (s) => String(s || "").replace(/\{\{\s*([^{}]+?)\s*\}\}/g, (_, k) => {
    return String(session.userVariables?.[k] ?? session.vars?.[k] ?? "");
  });
  const saveAs = String(node.variable || "rows");
  const sql    = subst(node.sql || "");
  if (!node.host || !node.database || !sql) {
    console.warn(`[MySQL] node ${node.id} missing host/database/sql — skipping`);
    session.userVariables[saveAs] = "";
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }
  try {
    const resp = await axios.post(`${appLocals.appDomainName}/api/flow-node/mysql-query`, {
      workspace_id: session.flowData?.workspace_id || null,
      host:     node.host,
      port:     node.port || 3306,
      database: node.database,
      username: node.username || "",
      password: node.password || "",
      sql,
    }, {
      timeout: 15000,
      headers: { "X-Node-Token": process.env.NODE_WEBHOOK_TOKEN || "" },
    });
    const rows = resp.data?.rows ?? resp.data ?? [];
    session.userVariables[saveAs] = typeof rows === "string" ? rows : JSON.stringify(rows);
    // Flatten so later nodes can pull a single cell, e.g. {{rows.0.name}} or
    // {{rows.length}} for the row count.
    if (rows && typeof rows === "object") {
      flattenJsonInto(session.userVariables, saveAs, rows);
    }
  } catch (e) {
    console.warn(`[MySQL] query failed: ${e?.response?.data?.error || e?.message}`);
    session.userVariables[saveAs] = "";
  }
  await moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
}

// ✅ Tag node — add/remove a tag on the contact via Laravel.
// Tag rows live workspace-scoped; resolution by id (preferred) or by
// name (created on the fly inside the controller).
async function executeTagContactNode(
  node, session, targetPhoneNumber, senderPhoneNumber,
  sock, appLocals, sessionKey,
) {
  const action = String(node.action || "add").toLowerCase();
  const tagId  = node.tagId  ? String(node.tagId)  : "";
  const tag    = node.tag    ? String(node.tag)    : "";
  const wsId   = session.flowData?.workspace_id || null;
  if (!wsId || (!tagId && !tag)) {
    console.warn(`[Tag] node ${node.id} missing workspace_id or tag — skipping`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }
  try {
    await axios.post(`${appLocals.appDomainName}/api/flow-node/tag`, {
      workspace_id:   wsId,
      customer_phone: targetPhoneNumber,
      action,
      tag_id:         tagId || null,
      tag_name:       tag   || null,
    }, {
      timeout: 8000,
      headers: { "X-Node-Token": process.env.NODE_WEBHOOK_TOKEN || "" },
    });
  } catch (e) {
    console.warn(`[Tag] action=${action} failed: ${e?.message}`);
  }
  await moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
}

// ✅ Assign node — hand the conversation off to a team/agent via
// Laravel. The internal note (if any) becomes a system event on the
// conversation so the receiving agent sees the flow context.
async function executeAssignAgentNode(
  node, session, targetPhoneNumber, senderPhoneNumber,
  sock, appLocals, sessionKey,
) {
  const wsId   = session.flowData?.workspace_id || null;
  const teamId = node.teamId ? String(node.teamId) : "";
  const userId = node.userId ? String(node.userId) : "";
  if (!wsId || (!teamId && !userId)) {
    console.warn(`[Assign] node ${node.id} missing workspace_id + team/user — skipping`);
    return moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
  }
  try {
    await axios.post(`${appLocals.appDomainName}/api/flow-node/assign`, {
      workspace_id:   wsId,
      customer_phone: targetPhoneNumber,
      team_id:        teamId || null,
      user_id:        userId || null,
      note:           node.noteForAgent || "",
    }, {
      timeout: 8000,
      headers: { "X-Node-Token": process.env.NODE_WEBHOOK_TOKEN || "" },
    });
  } catch (e) {
    console.warn(`[Assign] failed: ${e?.message}`);
  }
  await moveToNextNode(node.id, session.flowData, targetPhoneNumber, senderPhoneNumber, sock, appLocals, sessionKey);
}
