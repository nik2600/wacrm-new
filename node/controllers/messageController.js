import { formatPhoneNumber, formatInteractiveButtonsForBaileys, downloadAndPrepareMediaBaileys, getWhatsAppSettings, sendMessageViaFacebookApi, sendMessageViaTwilioApi } from "../utils/helpers.js";
import { isTwilioSettings } from "../utils/sendSafety.js";
import { sendCarouselMessage } from "../utils/campaignHelpers.js";

/**
 * Best-effort WhatsApp media category (image|video|audio|document) from a
 * file URL's extension. Used as a fallback when Laravel passes a generic
 * `application/octet-stream` filetype (templates are stored without a real
 * mime) — without this the WABA branch would send images/videos as
 * documents (the recipient gets a .BIN file). Returns null when unknown.
 */
function mediaTypeFromUrl(file) {
  const m = String(file || "").toLowerCase().match(/\.(png|jpe?g|gif|webp|bmp|mp4|3gp|mov|mkv|webm|pdf|docx?|xlsx?|pptx?|mp3|ogg|m4a|wav|aac|opus)(?:\?|#|$)/);
  if (!m) return null;
  const ext = m[1];
  if (['png','jpg','jpeg','gif','webp','bmp'].includes(ext)) return 'image';
  if (['mp4','3gp','mov','mkv','webm'].includes(ext)) return 'video';
  if (['mp3','ogg','m4a','wav','aac','opus'].includes(ext)) return 'audio';
  return 'document';
}

/**
 * Append the workspace's plan-gated branding footer to a plain-text body.
 * Mirrors flowService._appendFooterToText so /chat composer sends look
 * identical to flow sends on the customer's phone. Templates skip — the
 * /chat composer doesn't send templates through this endpoint.
 */
function _appendFooterToText(body, settings) {
  const footer = settings && settings.branding_footer;
  const beforeLen = String(body || '').length;
  if (!footer) {
    console.log(`[FOOTER] skip — settings.branding_footer is ${footer === null ? 'null' : 'empty'} (body len=${beforeLen})`);
    return body;
  }
  const needle = `\n\n_${footer}_`;
  const text = String(body || '');
  if (text.endsWith(needle)) {
    console.log(`[FOOTER] idempotent — body already ends with footer (footer="${footer}", body len=${beforeLen})`);
    return text;
  }
  const out = text + needle;
  console.log(`[FOOTER] APPENDED footer="${footer}" body ${beforeLen}→${out.length} chars`);
  return out;
}
function _interactiveFooter(settings) {
  const f = settings && settings.branding_footer;
  const v = f ? String(f).slice(0, 60) : null;
  console.log(`[FOOTER] interactive resolved=${v === null ? 'null' : `"${v}"`}`);
  return v;
}

// Send message (Text or Text + Buttons)
export const sendMessage = async (req, res, app) => {
  const startTime = Date.now();
  const senderPhoneNumber = req.params.phoneNumber;
  const { targetPhoneNumber, targetJid, message, buttons = [], footer = '', title = '', subtitle = '', template_type, carousel_data, location = null } = req.body.json || req.body;
  const appDomainName = process.env.APP_DOMAIN_NAME || "http://localhost:8000";

  // LOCATION header — after the message body sends, ship a separate WhatsApp
  // location pin (the Unofficial API has no template location header, so we
  // send it as its own message). Accepts Meta-style {latitude, longitude,
  // name, address} strings and maps to Baileys' degrees* float fields.
  const sendLocationPin = async (sock, jid) => {
    if (!location || typeof location !== 'object') return;
    const lat = parseFloat(location.latitude ?? location.lat);
    const lng = parseFloat(location.longitude ?? location.lng ?? location.lon);
    if (!isFinite(lat) || !isFinite(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
      console.log(`[NODE-MSG] location pin skipped (invalid/out-of-range coords: ${JSON.stringify(location)})`);
      return;
    }
    try {
      await sock.sendMessage(jid, { location: {
        degreesLatitude:  lat,
        degreesLongitude: lng,
        ...(location.name    ? { name:    String(location.name) }    : {}),
        ...(location.address ? { address: String(location.address) } : {}),
      }});
      console.log(`[NODE-MSG] location pin sent (${lat}, ${lng})`);
    } catch (e) {
      console.error(`[NODE-MSG] location pin send failed: ${e?.message}`);
    }
  };

  console.log(`\n[NODE-MSG] ========== SEND MESSAGE START ==========`);
  console.log(`[NODE-MSG] ts=${new Date().toISOString()}`);
  console.log(`[NODE-MSG] from=${senderPhoneNumber} to=${targetPhoneNumber}`);
  console.log(`[NODE-MSG] message="${message?.substring(0, 80) || ''}" len=${message?.length || 0}`);
  console.log(`[NODE-MSG] buttons=${buttons?.length || 0} footer=${footer ? 'Y' : 'N'}`);
  if (buttons?.length) {
    console.log(`[NODE-MSG] raw buttons:`, JSON.stringify(buttons));
  }
  console.log(`[NODE-MSG] CLIENT STATE: clients=[${Object.keys(app.locals.clients || {}).join(',')}] ready=[${Object.entries(app.locals.client_ready || {}).filter(([,v])=>v).map(([k])=>k).join(',')}]`);

  try {
    // Check if should use WABA
    console.log(`[NODE-MSG] Fetching WhatsApp settings...`);
    const settings = await getWhatsAppSettings(appDomainName, { phone: senderPhoneNumber });
    console.log(`[NODE-MSG] Settings retrieved - use_facebook_api: ${settings.use_facebook_api}`);
    console.log(`[NODE-MSG] [FOOTER-CHECK] resolved branding_footer=${settings.branding_footer === null || settings.branding_footer === undefined ? 'NULL (no footer will apply)' : `"${settings.branding_footer}"`} | cache_last_success=${settings.last_success === true ? 'yes' : 'no/stale'}`);

    // Per-record engine routing (multi-engine). Laravel stamps the
    // operator-chosen engine into the payload's `provider` at dispatch
    // time; that explicit value is AUTHORITATIVE and wins over the
    // workspace-level heuristic. An absent/unknown provider falls back
    // to the legacy heuristic (settings.use_facebook_api for WABA,
    // isTwilioSettings for Twilio) so single-engine workspaces route
    // byte-identically to before. Mirrors node/services/scheduleService.js.
    const rowProvider = (req.body.provider || (req.body.json && req.body.json.provider) || '').toString().toLowerCase().trim();
    let useWaba, useTwilio;
    if (rowProvider === 'baileys') {
      useWaba   = false;
      useTwilio = false;
    } else if (rowProvider === 'waba') {
      useWaba   = !!(settings && settings.use_facebook_api && settings.facebook_phone_id && settings.facebook_api_token);
      useTwilio = false;
    } else if (rowProvider === 'twilio') {
      useWaba   = false;
      useTwilio = isTwilioSettings(settings);
    } else {
      // Unset/unknown provider => legacy heuristic. This MUST equal the
      // old gate (`if (settings.use_facebook_api)`) byte-for-byte so the
      // single-engine customer base routes identically — hence the bare
      // !!settings.use_facebook_api here (no phone_id/token tightening).
      useWaba   = !!(settings && settings.use_facebook_api);
      useTwilio = isTwilioSettings(settings);
    }
    console.log(`[NODE-MSG] [ROUTE] provider=${rowProvider || '(unset)'} useWaba=${useWaba} useTwilio=${useTwilio}`);

    if (useWaba) {
      console.log(`[NODE-MSG] Using Facebook WABA API`);
      // Caller footer (operator-typed) wins; otherwise apply the
      // workspace's plan-gated branding footer to the body.
      const _bodyWithFooter = footer
        ? message + `\n\n${footer}`
        : _appendFooterToText(message, settings);
      const messageData = {
        type: "text",
        text: { preview_url: false, body: _bodyWithFooter }
      };

      if (buttons && buttons.length > 0) {
        console.log(`[NODE-MSG] Formatting ${buttons.length} interactive buttons`);
        delete messageData.text; // Remove orphan text property
        messageData.type = "interactive";
        messageData.interactive = {
          type: "button",
          body: { text: message },
          action: {
            buttons: buttons.slice(0, 3).map((btn, idx) => ({
              type: "reply",
              reply: {
                id: btn.value || `btn_${idx}`,
                title: btn.text.substring(0, 20),
              },
            })),
          },
        };
        // Interactive footer field — caller's footer wins, then plan footer.
        const _intFooter = footer || _interactiveFooter(settings);
        if (_intFooter) messageData.interactive.footer = { text: _intFooter };
      }

      console.log(`[NODE-MSG] Sending via Facebook API...`);
      const result = await sendMessageViaFacebookApi(targetPhoneNumber, messageData, settings);
      const duration = Date.now() - startTime;
      console.log(`[NODE-MSG] Facebook API result: ${result.success ? 'SUCCESS' : 'FAILED'} (${duration}ms)`);
      console.log(`[NODE-MSG] ========== SEND MESSAGE END ==========`);
      return res.status(result.success ? 200 : 500).send(result);
    }

    if (useTwilio) {
      console.log(`[NODE-MSG] Using Twilio API`);
      // Twilio's WhatsApp transport sends plain text only here — it has no
      // free-text interactive button support (those need an approved
      // ContentSid). Mirror campaignService.js: caller footer wins, else
      // the plan-gated branding footer is appended to the body. Buttons,
      // carousel, title/subtitle and the location pin degrade to text on
      // Twilio (parity with the campaign/flow Twilio degrade path).
      const _twilioBody = footer
        ? message + `\n\n${footer}`
        : _appendFooterToText(message, settings);
      if (buttons && buttons.length > 0) {
        console.warn(`[NODE-MSG] [TWILIO] ${buttons.length} button(s) dropped — Twilio free-text has no interactive buttons; sent as text.`);
      }
      const result = await sendMessageViaTwilioApi(targetPhoneNumber, { type: "text", body: _twilioBody }, settings);
      const duration = Date.now() - startTime;
      console.log(`[NODE-MSG] Twilio API result: ${result.success ? 'SUCCESS' : 'FAILED'} (${duration}ms)`);
      console.log(`[NODE-MSG] ========== SEND MESSAGE END ==========`);
      return res.status(result.success ? 200 : 500).send(
        result.success
          ? { message: "MESSAGE SENT SUCCESSFULLY", id: result.messageId || null }
          : result
      );
    }

    console.log(`[NODE-MSG] Using Baileys client`);

    // Wait up to 8s for the sock to become ready. This guards against the
    // race where the user pairs and immediately fires a send while Baileys
    // is still doing its post-pair 515 stream-restart dance — sending into
    // the stale sock would silently drop the message.
    const readyDeadline = Date.now() + 8000;
    while (Date.now() < readyDeadline) {
      if (app.locals.client_ready?.[senderPhoneNumber] && app.locals.clients?.[senderPhoneNumber]) break;
      await new Promise(r => setTimeout(r, 250));
    }
    const sock = app.locals.clients[senderPhoneNumber];
    const isReady = !!app.locals.client_ready?.[senderPhoneNumber];

    if (!sock || !isReady) {
      console.error(`[NODE-MSG] ❌ CLIENT NOT READY for ${senderPhoneNumber} (sock=${!!sock} ready=${isReady})`);
      console.error(`[NODE-MSG] Available clients: ${Object.keys(app.locals.clients || {}).join(', ')}`);
      console.log(`[NODE-MSG] ========== SEND MESSAGE END (NOT READY) ==========`);
      return res.status(503).send({ error: "CLIENT NOT READY", details: "Device is still connecting. Try again in a few seconds." });
    }
    console.log(`[NODE-MSG] Client found, formatting number...`);
    // Prefer the full JID passed by Laravel for LID-routed chats —
    // formatPhoneNumber would otherwise truncate the 15-digit LID to
    // 12 and target a fabricated WhatsApp number.
    let finalNumber;
    if (targetJid && (targetJid.includes('@lid') || targetJid.includes('@s.whatsapp.net') || targetJid.includes('@g.us'))) {
      finalNumber = targetJid;
      console.log(`[NODE-MSG] Using explicit JID: ${finalNumber}`);
    } else {
      finalNumber = formatPhoneNumber(targetPhoneNumber);
      console.log(`[NODE-MSG] Formatted number: ${finalNumber}`);
    }
    // Carousel template (single send) — render the cards the same way the
    // bulk campaign path does. Laravel already resolved placeholders, so no
    // per-recipient substitution is needed here. Must run BEFORE the buttons
    // branch (a carousel template can also carry top-level buttons).
    if (template_type === 'carousel' && carousel_data) {
      try {
        const rawCards = (typeof carousel_data === 'string' ? JSON.parse(carousel_data) : carousel_data) || [];
        const cards = rawCards.map((card) => {
          const pc = { title: card.title || '', body: card.body || '', buttons: card.buttons || [] };
          if (card.footer) pc.footer = card.footer;
          if (card.image_path) pc.image = card.image_path;
          else if (card.image_filename) pc.image = `${appDomainName}/uploads/templates/carousel/${card.image_filename}`;
          else if (card.image) pc.image = String(card.image).startsWith('http') ? card.image : `${appDomainName}/uploads/templates/carousel/${card.image}`;
          return pc;
        });
        const result = await sendCarouselMessage(sock, finalNumber, {
          text: message || '',
          title: title || '',
          footer: footer || '',
          cards,
        });
        const duration = Date.now() - startTime;
        if (result && result.success) {
          console.log(`[NODE-MSG] ✅ carousel sent (${duration}ms) cards=${cards.length} id=${result.messageId || ''}`);
          await sendLocationPin(sock, finalNumber);
          console.log(`[NODE-MSG] ========== SEND MESSAGE END ==========\n`);
          return res.status(200).send({ message: "MESSAGE SENT SUCCESSFULLY", id: result.messageId || null });
        }
        console.error(`[NODE-MSG] ❌ carousel send failed: ${result?.error}`);
        return res.status(500).send({ error: "CAROUSEL SEND FAILED", details: result?.error });
      } catch (e) {
        console.error(`[NODE-MSG] ❌ carousel threw: ${e?.message}`);
        return res.status(500).send({ error: "CAROUSEL SEND THREW", details: e?.message });
      }
    }

    const formattedButtons = formatInteractiveButtonsForBaileys(buttons);

    if (formattedButtons && formattedButtons.length > 0) {
      console.log(`[NODE-MSG] Sending message with ${formattedButtons.length} buttons...`);
      console.log(`[NODE-MSG] formatted buttons:`, JSON.stringify(formattedButtons));
      // Per Itsukichan/Baileys README "Buttons Interactive Message"
      // section: text + optional title + subtitle + footer +
      // interactiveButtons. The title renders as a bold heading above
      // the body and subtitle as muted text below it.
      // Caller-supplied footer wins; otherwise fall back to the plan-
      // gated platform/workspace footer resolved via Laravel.
      const _btnFooter = footer || _interactiveFooter(settings) || '';
      const buttonMessage = {
        text: message,
        ...(title    ? { title }    : {}),
        ...(subtitle ? { subtitle } : {}),
        footer: _btnFooter,
        interactiveButtons: formattedButtons
      };
      let sentBtn;
      try {
        sentBtn = await sock.sendMessage(finalNumber, buttonMessage);
      } catch (e) {
        const duration = Date.now() - startTime;
        console.error(`[NODE-MSG] ❌ button-message send threw (${duration}ms): ${e?.message}`);
        console.error(`[NODE-MSG] stack: ${e?.stack}`);
        console.log(`[NODE-MSG] ========== SEND MESSAGE END (BTN THROW) ==========\n`);
        return res.status(500).send({ error: "BUTTON SEND THREW", details: e?.message });
      }
      const duration = Date.now() - startTime;
      console.log(`[NODE-MSG] ✅ Message with buttons sent (${duration}ms) id=${sentBtn?.key?.id}`);
      await sendLocationPin(sock, finalNumber);
      console.log(`[NODE-MSG] ========== SEND MESSAGE END ==========\n`);
      res.status(200).send({ message: "MESSAGE SENT SUCCESSFULLY", id: sentBtn?.key?.id || null });
    } else {
      // Apply the plan-gated footer to plain text sends from /chat
      // composer. Idempotent — re-sending the same text won't double-stack.
      const _withFooter = _appendFooterToText(message, settings);
      console.log(`[NODE-MSG] → sock.sendMessage(${finalNumber}, {text:"${_withFooter?.substring(0,50)}"}) footer=${settings?.branding_footer ? 'Y' : 'N'}`);
      let sent;
      try {
        sent = await sock.sendMessage(finalNumber, { text: _withFooter });
      } catch (sendErr) {
        const duration = Date.now() - startTime;
        console.error(`[NODE-MSG] ❌ sock.sendMessage threw (${duration}ms): ${sendErr?.message}`);
        console.error(`[NODE-MSG] stack: ${sendErr?.stack}`);
        console.log(`[NODE-MSG] ========== SEND MESSAGE END (THROW) ==========\n`);
        return res.status(500).send({ error: "SEND THREW", details: sendErr?.message });
      }
      const duration = Date.now() - startTime;
      const sentId = sent?.key?.id || null;
      const sentTo = sent?.key?.remoteJid || null;
      const sentStatus = sent?.status ?? null;
      console.log(`[NODE-MSG] ← sock.sendMessage returned: id=${sentId} jid=${sentTo} status=${sentStatus}`);
      console.log(`[NODE-MSG] ✅ Plain text sent (${duration}ms)`);
      await sendLocationPin(sock, finalNumber);
      console.log(`[NODE-MSG] ========== SEND MESSAGE END ==========\n`);
      res.status(200).send({ message: "MESSAGE SENT SUCCESSFULLY", id: sentId, jid: sentTo });
    }
  } catch (error) {
    const duration = Date.now() - startTime;
    console.error(`[NODE-MSG] ❌ ERROR SENDING MESSAGE (${duration}ms)`);
    console.error(`[NODE-MSG] Error type: ${error.name}`);
    console.error(`[NODE-MSG] Error message: ${error.message}`);
    console.error(`[NODE-MSG] Stack trace: ${error.stack}`);
    console.log(`[NODE-MSG] ========== SEND MESSAGE END (ERROR) ==========`);
    res.status(500).send({ error: "ERROR SENDING MESSAGE", details: error.message });
  }
};

// Send media only (Image/Video/Document/Audio without caption/buttons)
export const sendMediaOnly = async (req, res, app) => {
  const senderPhoneNumber = req.params.phoneNumber;
  const { targetPhoneNumber, targetJid, file, file_base64, filetype, fileName, ptt, voice } = req.body.json || req.body;
  const appDomainName = process.env.APP_DOMAIN_NAME || "http://localhost:8000";

  try {
    const settings = await getWhatsAppSettings(appDomainName, { phone: senderPhoneNumber });

    // Multi-engine: honor the per-record engine (req.body.provider) so a
    // Baileys-pinned media send isn't hijacked to Meta Cloud when the workspace
    // also has WABA connected. Absent provider => legacy use_facebook_api gate.
    const rowProvider = (req.body.provider || (req.body.json && req.body.json.provider) || '').toString().toLowerCase().trim();
    const useWaba = (rowProvider === 'baileys' || rowProvider === 'twilio') ? false
      : rowProvider === 'waba' ? !!(settings && settings.use_facebook_api && settings.facebook_phone_id && settings.facebook_api_token)
      : !!(settings && settings.use_facebook_api);

    if (useWaba) {
       // Determine media type
       const urlType = mediaTypeFromUrl(file);
       let mediaType = "image";
       if (filetype && (filetype.includes("mp4") || filetype.includes("3gp"))) {
         mediaType = "video";
       } else if (filetype && (filetype.includes("mp3") || filetype.includes("ogg") || filetype.includes("aac") || filetype.includes("opus"))) {
         mediaType = "audio";
       } else if (filetype && (filetype.includes("jpg") || filetype.includes("jpeg") || filetype.includes("png") || filetype.includes("gif") || filetype.includes("webp"))) {
         mediaType = "image";
       } else if (urlType) {
         // Generic application/octet-stream → trust the file URL extension so
         // images/videos aren't wrongly sent as documents (.BIN).
         mediaType = urlType;
       } else if (filetype && (filetype.includes("application") || filetype.includes("text"))) {
         mediaType = "document";
       } else {
         mediaType = "document";
       }

       // Build clean payload with only the correct media type
       const messageData = { type: mediaType };
       if (mediaType === "image") {
         messageData.image = { link: file };
       } else if (mediaType === "video") {
         messageData.video = { link: file };
       } else if (mediaType === "audio") {
         messageData.audio = { link: file };
       } else if (mediaType === "document") {
         messageData.document = { link: file, filename: file.split('/').pop() };
       }

       const result = await sendMessageViaFacebookApi(targetPhoneNumber, messageData, settings);
       return res.status(result.success ? 200 : 500).send(result);
    }

    const sock = app.locals.clients[senderPhoneNumber];
    if (!sock) {
      console.error(`[NODE-MEDIA-ONLY] ❌ CLIENT NOT FOUND for ${senderPhoneNumber}`);
      return res.status(404).send({ error: "CLIENT NOT FOUND" });
    }
    // Prefer the JID Laravel passed (handles LID-routed chats correctly).
    const finalNumber = targetJid && targetJid.includes('@') ? targetJid : formatPhoneNumber(targetPhoneNumber);

    // Prefer the inlined base64 — Laravel sends file_base64 to avoid
    // the PHP-dev-server deadlock that happens when Node fetches the
    // file from /storage while Laravel is still blocking on this call.
    let mediaBuffer;
    let detectedMime = null; // the REAL mimetype the download/extension resolved
    if (file_base64) {
      mediaBuffer = Buffer.from(file_base64, 'base64');
      console.log(`[NODE-MEDIA-ONLY] decoded base64 → ${mediaBuffer.length} bytes`);
    } else {
      const r = await downloadAndPrepareMediaBaileys(file);
      mediaBuffer = r.buffer;
      detectedMime = r.mimetype || null; // e.g. image/png
    }

    // The passed-in `filetype` is frequently the generic
    // application/octet-stream (templates stored without a real mime), which
    // would wrongly send an image/video as a DOCUMENT → recipient gets a
    // .BIN file. Prefer the download-detected mime, then the URL extension.
    const mimeFromUrl = (() => {
      const m = String(file || "").toLowerCase().match(/\.(png|jpe?g|gif|webp|bmp|mp4|3gp|mov|mkv|webm|pdf|docx?|xlsx?|pptx?|mp3|ogg|m4a|wav|aac)(?:\?|#|$)/);
      if (!m) return null;
      const map = {
        png:'image/png', jpg:'image/jpeg', jpeg:'image/jpeg', gif:'image/gif', webp:'image/webp', bmp:'image/bmp',
        mp4:'video/mp4', '3gp':'video/3gpp', mov:'video/quicktime', mkv:'video/x-matroska', webm:'video/webm',
        pdf:'application/pdf', doc:'application/msword', docx:'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        xls:'application/vnd.ms-excel', xlsx:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ppt:'application/vnd.ms-powerpoint', pptx:'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        mp3:'audio/mpeg', ogg:'audio/ogg', m4a:'audio/mp4', wav:'audio/wav', aac:'audio/aac',
      };
      return map[m[1]] || null;
    })();
    let ft = (filetype || '').toLowerCase();
    if (!ft || ft === 'application/octet-stream') {
      ft = (detectedMime || mimeFromUrl || ft || 'application/octet-stream').toLowerCase();
    }
    let mediaKey = "document";
    if (ft.startsWith("image/"))      mediaKey = "image";
    else if (ft.startsWith("video/")) mediaKey = "video";
    else if (ft.startsWith("audio/")) mediaKey = "audio";

    const mediaMessage = { [mediaKey]: mediaBuffer, mimetype: ft || 'application/octet-stream' };
    if (mediaKey === "document") {
      mediaMessage.fileName = fileName || (file ? file.split('/').pop() : 'file');
    }
    // Voice-note flag — when Laravel sends ptt:true, mark the audio as
    // push-to-talk so WhatsApp renders the round play-button voice-note
    // bubble instead of a generic audio file. Accept both `ptt` and the
    // older alias `voice` so older Laravel builds still work.
    if (mediaKey === "audio" && (ptt === true || ptt === 'true' || voice === true || voice === 'true')) {
      mediaMessage.ptt = true;
    }
    console.log(`[NODE-MEDIA-ONLY] sending as ${mediaKey} mime=${mediaMessage.mimetype} name=${mediaMessage.fileName || '-'} ptt=${!!mediaMessage.ptt}`);
    const sent = await sock.sendMessage(finalNumber, mediaMessage, { mediaUploadTimeoutMs: 180000 });
    res.status(200).send({ message: "MEDIA SENT SUCCESSFULLY", id: sent?.key?.id || null });
  } catch (error) {
    console.error(`[NODE-MEDIA-ONLY] error:`, error?.message);
    res.status(500).send({ error: "ERROR SENDING MEDIA", details: error.message });
  }
};

// Send media message with caption (and optional buttons/footer)
// controllers/messageController.js - FIXED sendMediaMessage
export const sendMediaMessage = async (req, res, app) => {
  const startTime = Date.now();
  const senderPhoneNumber = req.params.phoneNumber;
  const { targetPhoneNumber, file, file_base64, filetype = "image/png", fileName, caption = "", buttons = [], footer = "" } = req.body.json || req.body;
  const appDomainName = process.env.APP_DOMAIN_NAME || "http://localhost:8000";

  console.log(`[NODE-MEDIA] ========== SEND MEDIA MESSAGE START ==========`);
  console.log(`[NODE-MEDIA] Timestamp: ${new Date().toISOString()}`);
  console.log(`[NODE-MEDIA] From: ${senderPhoneNumber}`);
  console.log(`[NODE-MEDIA] To: ${targetPhoneNumber}`);
  console.log(`[NODE-MEDIA] File: ${file?.substring(0, 80)}`);
  console.log(`[NODE-MEDIA] FileType: ${filetype}`);
  console.log(`[NODE-MEDIA] Caption: ${caption?.substring(0, 50)}${caption?.length > 50 ? '...' : ''}`);

  try {
    console.log(`[NODE-MEDIA] Fetching WhatsApp settings...`);
    const settings = await getWhatsAppSettings(appDomainName, { phone: senderPhoneNumber });
    console.log(`[NODE-MEDIA] Settings retrieved - use_facebook_api: ${settings.use_facebook_api}`);

    // Multi-engine: honor the per-record engine (req.body.provider) so a
    // Baileys-pinned media send isn't hijacked to Meta Cloud. Absent => legacy.
    const rowProvider = (req.body.provider || (req.body.json && req.body.json.provider) || '').toString().toLowerCase().trim();
    const useWaba = (rowProvider === 'baileys' || rowProvider === 'twilio') ? false
      : rowProvider === 'waba' ? !!(settings && settings.use_facebook_api && settings.facebook_phone_id && settings.facebook_api_token)
      : !!(settings && settings.use_facebook_api);

    if (useWaba) {
      console.log(`[NODE-MEDIA] Using Facebook WABA API`);
      // Determine the correct media type
      const urlType = mediaTypeFromUrl(file);
      let mediaType = "image";
      if (filetype.includes("video")) {
        mediaType = "video";
      } else if (filetype.includes("audio")) {
        mediaType = "audio";
      } else if (filetype.includes("image")) {
        mediaType = "image";
      } else if (urlType) {
        // Generic application/octet-stream → trust the file URL extension.
        mediaType = urlType;
      } else if (filetype.includes("application") || filetype.includes("text")) {
        mediaType = "document";
      }

      const messageData = { type: mediaType };
      // Build the correct media object (only one, no orphans)
      if (mediaType === "image") {
        messageData.image = { link: file, caption: caption };
      } else if (mediaType === "video") {
        messageData.video = { link: file, caption: caption };
      } else if (mediaType === "audio") {
        messageData.audio = { link: file };
      } else if (mediaType === "document") {
        messageData.document = { link: file, caption: caption, filename: file.split('/').pop() };
      }
      console.log(`[NODE-MEDIA] Message type: ${messageData.type}`);

      if (buttons && buttons.length > 0) {
        console.log(`[NODE-MEDIA] Adding ${buttons.length} interactive buttons`);
        // Save media type before changing to interactive
        const headerType = mediaType === "audio" ? "document" : mediaType;
        // Clean up the simple media properties
        delete messageData.image;
        delete messageData.video;
        delete messageData.audio;
        delete messageData.document;
        messageData.type = "interactive";
        messageData.interactive = {
          type: "button",
          header: {
            type: headerType,
            [headerType]: { link: file }
          },
          body: { text: caption || " " },
          action: {
            buttons: buttons.slice(0, 3).map((btn, idx) => ({
              type: "reply",
              reply: {
                id: btn.value || `btn_${idx}`,
                title: btn.text.substring(0, 20),
              },
            })),
          }
        };
        if (footer) messageData.interactive.footer = { text: footer };
      }

      console.log(`[NODE-MEDIA] Sending via Facebook API...`);
      const result = await sendMessageViaFacebookApi(targetPhoneNumber, messageData, settings);
      const duration = Date.now() - startTime;
      console.log(`[NODE-MEDIA] Facebook API result: ${result.success ? 'SUCCESS' : 'FAILED'} (${duration}ms)`);
      console.log(`[NODE-MEDIA] ========== SEND MEDIA MESSAGE END ==========`);
      return res.status(result.success ? 200 : 500).send(result);
    }

    console.log(`[NODE-MEDIA] Using Baileys client`);
    const sock = app.locals.clients[senderPhoneNumber];
    if (!sock) {
      console.error(`[NODE-MEDIA] ❌ CLIENT NOT FOUND for ${senderPhoneNumber}`);
      console.error(`[NODE-MEDIA] Available clients: ${Object.keys(app.locals.clients || {}).join(', ')}`);
      console.log(`[NODE-MEDIA] ========== SEND MEDIA MESSAGE END (FAILED) ==========`);
      return res.status(404).json({ error: "CLIENT NOT FOUND" });
    }
    console.log(`[NODE-MEDIA] Client found, formatting number...`);
    const jid = formatPhoneNumber(targetPhoneNumber);
    console.log(`[NODE-MEDIA] Formatted number: ${jid}`);
    let buffer;
    let detectedMime = null; // the REAL mimetype the download/extension resolved
    if (file_base64) {
      buffer = Buffer.from(file_base64, 'base64');
      console.log(`[NODE-MEDIA] decoded base64 → ${buffer.length} bytes`);
    } else {
      console.log(`[NODE-MEDIA] Downloading media from: ${file}`);
      const r = await downloadAndPrepareMediaBaileys(file);
      buffer = r.buffer;
      detectedMime = r.mimetype || null; // e.g. image/png
      console.log(`[NODE-MEDIA] Media downloaded, size: ${buffer?.length} bytes`);
    }

    // Guess a mimetype from the file URL's extension as a last resort
    // (covers the base64 path where we never downloaded).
    const mimeFromUrl = (() => {
      const m = String(file || "").toLowerCase().match(/\.(png|jpe?g|gif|webp|bmp|mp4|3gp|mov|mkv|webm|pdf|docx?|xlsx?|pptx?|mp3|ogg|m4a|wav|aac)(?:\?|#|$)/);
      if (!m) return null;
      const map = {
        png:'image/png', jpg:'image/jpeg', jpeg:'image/jpeg', gif:'image/gif', webp:'image/webp', bmp:'image/bmp',
        mp4:'video/mp4', '3gp':'video/3gpp', mov:'video/quicktime', mkv:'video/x-matroska', webm:'video/webm',
        pdf:'application/pdf', doc:'application/msword', docx:'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        xls:'application/vnd.ms-excel', xlsx:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ppt:'application/vnd.ms-powerpoint', pptx:'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        mp3:'audio/mpeg', ogg:'audio/ogg', m4a:'audio/mp4', wav:'audio/wav', aac:'audio/aac',
      };
      return map[m[1]] || null;
    })();

    // The passed-in `filetype` is frequently the generic application/octet-stream
    // (templates are stored without a real mime), which would wrongly route an
    // image/video through the DOCUMENT header → the recipient gets a .BIN file.
    // Prefer the mimetype the download actually detected, then the URL
    // extension, and only fall back to the passed-in filetype.
    let mimetype = filetype && filetype.includes("/") ? filetype : (filetype ? `application/${filetype}` : "");
    if (!mimetype || mimetype === "application/octet-stream") {
      mimetype = detectedMime || mimeFromUrl || mimetype || "application/octet-stream";
    }
    console.log(`[NODE-MEDIA] Mimetype: ${mimetype} (passedIn=${filetype} detected=${detectedMime} fromUrl=${mimeFromUrl})`);

    if (buttons && buttons.length > 0) {
      console.log(`[NODE-MEDIA] Sending media with ${buttons.length} buttons...`);
      // Per Itsukichan/Baileys README — a MEDIA + interactive message is ONE
      // message: the media rides as the interactive HEADER via the top-level
      // `image` / `video` / `document` key, the caption is the body, plus
      // `footer` + `interactiveButtons` + `hasMediaAttachment: true`. The
      // previous hand-rolled `{ interactiveMessage: { header:{image}, ... } }`
      // raw-proto wrapper was undocumented for this fork and silently dropped
      // the image, so the recipient only saw text + buttons. We now mirror the
      // exact convenience shape generateWAMessageContent understands, and route
      // the media key off the real mimetype so video/document headers work too.
      const formattedButtons = formatInteractiveButtonsForBaileys(buttons);
      const interactiveMsg = {
        caption: caption || "",
        footer: footer || "",
        interactiveButtons: formattedButtons,
        hasMediaAttachment: true,
      };
      if (mimetype.startsWith("image/")) {
        interactiveMsg.image = buffer;
      } else if (mimetype.startsWith("video/")) {
        interactiveMsg.video = buffer;
      } else if (mimetype.startsWith("audio/")) {
        // Audio can't be an interactive header — fall back to a document
        // header so the buttons still attach to the media bubble.
        interactiveMsg.document = buffer;
        interactiveMsg.mimetype = mimetype;
        interactiveMsg.fileName = fileName || (file ? file.split("/").pop() : 'audio');
      } else {
        interactiveMsg.document = buffer;
        interactiveMsg.mimetype = mimetype;
        interactiveMsg.fileName = fileName || (file ? file.split("/").pop() : 'file');
      }
      console.log(`[NODE-MEDIA] interactive media header key: ${Object.keys(interactiveMsg).find(k => ['image','video','document'].includes(k))}`);
      // Slow uplinks (e.g. throttled VPS) need far longer than Baileys' ~5s
      // default to push the media to WhatsApp's CDN, else "Upload timeout".
      const sentMedia = await sock.sendMessage(jid, interactiveMsg, { mediaUploadTimeoutMs: 180000 });
      const duration = Date.now() - startTime;
      console.log(`[NODE-MEDIA] ✅ Media with buttons sent successfully (${duration}ms) id=${sentMedia?.key?.id}`);
      console.log(`[NODE-MEDIA] ========== SEND MEDIA MESSAGE END ==========`);
      return res.json({ success: true, message: "Media + Buttons Sent!", id: sentMedia?.key?.id || null });
    }

    console.log(`[NODE-MEDIA] Preparing media message...`);
    const msg = { caption: caption || undefined, mimetype: mimetype };
    if (mimetype.startsWith("image/")) msg.image = buffer;
    else if (mimetype.startsWith("video/")) msg.video = buffer;
    else if (mimetype.startsWith("audio/")) msg.audio = buffer;
    else {
      msg.document = buffer;
      msg.fileName = fileName || (file ? file.split("/").pop() : 'file');
    }
    console.log(`[NODE-MEDIA] Message type: ${Object.keys(msg).filter(k => k !== 'caption' && k !== 'mimetype').join(', ')}`);
    console.log(`[NODE-MEDIA] Sending media message...`);
    await sock.sendMessage(jid, msg, { mediaUploadTimeoutMs: 180000 });
    const duration = Date.now() - startTime;
    console.log(`[NODE-MEDIA] ✅ Media sent successfully (${duration}ms)`);
    console.log(`[NODE-MEDIA] ========== SEND MEDIA MESSAGE END ==========`);
    return res.json({ success: true, message: "Media Sent Successfully!" });
  } catch (error) {
    const duration = Date.now() - startTime;
    console.error(`[NODE-MEDIA] ❌ ERROR SENDING MEDIA MESSAGE (${duration}ms)`);
    console.error(`[NODE-MEDIA] Error type: ${error.name}`);
    console.error(`[NODE-MEDIA] Error message: ${error.message}`);
    console.error(`[NODE-MEDIA] Stack trace: ${error.stack}`);
    console.log(`[NODE-MEDIA] ========== SEND MEDIA MESSAGE END (ERROR) ==========`);
    return res.status(500).json({ success: false, error: error.message });
  }
};


// Send location — exact format the Itsukichan/Baileys README documents:
// sock.sendMessage(jid, { location: { degreesLatitude, degreesLongitude } })
// (See D:/tmp/itsukichan-baileys/README.md "Location Message" section.)
export const sendLocation = async (req, res, app) => {
  const senderPhoneNumber = req.params.phoneNumber;
  const { targetPhoneNumber, targetJid, message, latitude, longitude } = req.body.json || req.body;
  const appDomainName = process.env.APP_DOMAIN_NAME || "http://localhost:8000";

  console.log(`\n[NODE-LOC] ========== SEND LOCATION START ==========`);
  console.log(`[NODE-LOC] from=${senderPhoneNumber} to=${targetPhoneNumber}`);
  console.log(`[NODE-LOC] lat=${latitude} lng=${longitude} caption="${(message || '').substring(0, 40)}"`);

  try {
    const settings = await getWhatsAppSettings(appDomainName, { phone: senderPhoneNumber });

    // Multi-engine: honor the per-record engine (req.body.provider) so a
    // Baileys-pinned location pin isn't hijacked to Meta Cloud. Absent => legacy.
    const rowProvider = (req.body.provider || (req.body.json && req.body.json.provider) || '').toString().toLowerCase().trim();
    const useWaba = (rowProvider === 'baileys' || rowProvider === 'twilio') ? false
      : rowProvider === 'waba' ? !!(settings && settings.use_facebook_api && settings.facebook_phone_id && settings.facebook_api_token)
      : !!(settings && settings.use_facebook_api);

    if (useWaba) {
      const messageData = {
        type: "location",
        location: {
          latitude: parseFloat(latitude),
          longitude: parseFloat(longitude),
          name: message || "Location",
          address: message || "Location",
        },
      };
      const result = await sendMessageViaFacebookApi(targetPhoneNumber, messageData, settings);
      return res.status(result.success ? 200 : 500).send(result);
    }

    const sock = app.locals.clients[senderPhoneNumber];
    const isReady = !!app.locals.client_ready?.[senderPhoneNumber];
    if (!sock || !isReady) {
      console.error(`[NODE-LOC] ❌ CLIENT NOT READY (sock=${!!sock} ready=${isReady})`);
      return res.status(503).send({ error: "CLIENT NOT READY" });
    }
    // Group / LID-routed targets carry the raw jid through targetJid;
    // formatPhoneNumber would otherwise wrap the digits as @s.whatsapp.net
    // and the pin would land on a fabricated user account.
    const finalNumber = (targetJid && (targetJid.includes('@g.us') || targetJid.includes('@lid') || targetJid.includes('@s.whatsapp.net')))
      ? targetJid
      : formatPhoneNumber(targetPhoneNumber);

    // Build a minimal location payload — only include `name` and
    // `address` if they're actually non-empty strings, otherwise
    // some recipients show empty caption stubs.
    const loc = {
      degreesLatitude:  parseFloat(latitude),
      degreesLongitude: parseFloat(longitude),
    };
    if (message && message.trim()) {
      loc.name    = message.trim().substring(0, 200);
      loc.address = message.trim().substring(0, 200);
    }

    const sent = await sock.sendMessage(finalNumber, { location: loc });
    console.log(`[NODE-LOC] ✅ sent id=${sent?.key?.id} jid=${sent?.key?.remoteJid}`);
    console.log(`[NODE-LOC] ========== SEND LOCATION END ==========\n`);
    res.status(200).send({ message: "LOCATION SENT SUCCESSFULLY", id: sent?.key?.id || null });
  } catch (error) {
    console.error(`[NODE-LOC] ❌ error:`, error?.message);
    console.error(`[NODE-LOC] stack:`, error?.stack);
    res.status(500).send({ error: "ERROR SENDING LOCATION", details: error.message });
  }
};

// Send product catalog as carousel (Baileys) or native product message (WABA)
export const sendProductCatalog = async (req, res, app) => {
  const startTime = Date.now();
  const senderPhoneNumber = req.params.phoneNumber;
  const {
    targetPhoneNumber,
    products = [],
    type = 'multi',        // 'single', 'multi', or 'catalog'
    header_text = '',
    body_text = '',
    footer_text = '',
    catalog_id = '',
    retailer_id = '',
    thumbnail_retailer_id = '',
    section_title = 'Products',
    product_retailer_ids = [],
  } = req.body.json || req.body;

  const appDomainName = process.env.APP_DOMAIN_NAME || "http://localhost:8000";

  console.log(`[NODE-CATALOG] ========== SEND PRODUCT CATALOG START ==========`);
  console.log(`[NODE-CATALOG] From: ${senderPhoneNumber}`);
  console.log(`[NODE-CATALOG] To: ${targetPhoneNumber}`);
  console.log(`[NODE-CATALOG] Type: ${type}`);
  console.log(`[NODE-CATALOG] Products count: ${products.length}`);

  try {
    const settings = await getWhatsAppSettings(appDomainName, { phone: senderPhoneNumber });
    console.log(`[NODE-CATALOG] use_facebook_api: ${settings.use_facebook_api}`);

    if (settings.use_facebook_api && catalog_id) {
      // ===== WABA: Send native interactive product messages =====
      console.log(`[NODE-CATALOG] Sending via WABA native product message`);
      let messageData;

      if (type === 'single') {
        messageData = {
          type: 'interactive',
          interactive: {
            type: 'product',
            body: { text: body_text || 'Check out this product!' },
            action: {
              catalog_id: catalog_id,
              product_retailer_id: retailer_id || (products[0]?.retailer_id || ''),
            },
          },
        };
        if (footer_text) messageData.interactive.footer = { text: footer_text };

      } else if (type === 'multi') {
        const items = (product_retailer_ids.length > 0 ? product_retailer_ids : products.map(p => p.retailer_id))
          .filter(Boolean)
          .map(rid => ({ product_retailer_id: rid }));

        messageData = {
          type: 'interactive',
          interactive: {
            type: 'product_list',
            header: { type: 'text', text: header_text || 'Our Products' },
            body: { text: body_text || 'Browse our collection' },
            action: {
              catalog_id: catalog_id,
              sections: [{ title: section_title || 'Products', product_items: items }],
            },
          },
        };
        if (footer_text) messageData.interactive.footer = { text: footer_text };

      } else {
        // catalog type
        const params = {};
        if (thumbnail_retailer_id) params.thumbnail_product_retailer_id = thumbnail_retailer_id;
        messageData = {
          type: 'interactive',
          interactive: {
            type: 'catalog_message',
            body: { text: body_text || 'Browse our full catalog!' },
            action: {
              name: 'catalog_message',
              parameters: Object.keys(params).length > 0 ? params : {},
            },
          },
        };
      }

      const result = await sendMessageViaFacebookApi(targetPhoneNumber, messageData, settings);
      const duration = Date.now() - startTime;
      console.log(`[NODE-CATALOG] WABA result: ${result.success ? 'SUCCESS' : 'FAILED'} (${duration}ms)`);
      console.log(`[NODE-CATALOG] ========== END ==========`);
      return res.status(result.success ? 200 : 500).send(result);
    }

    // ===== BAILEYS: Send products as carousel cards =====
    console.log(`[NODE-CATALOG] Sending via Baileys carousel`);
    const sock = app.locals.clients[senderPhoneNumber];
    if (!sock) {
      console.error(`[NODE-CATALOG] CLIENT NOT FOUND for ${senderPhoneNumber}`);
      return res.status(404).send({ error: "CLIENT NOT FOUND" });
    }

    const finalNumber = formatPhoneNumber(targetPhoneNumber);

    if (!products || products.length === 0) {
      return res.status(400).send({ success: false, error: "No products provided for carousel" });
    }

    // Build carousel cards from products
    const cards = products.map((product, idx) => {
      const priceText = product.price
        ? `${product.currency || 'USD'} ${parseFloat(product.price).toFixed(2)}`
        : '';
      const availText = product.availability === 'out of stock' ? ' (Out of Stock)' : '';

      const card = {
        title: (product.name || 'Product').substring(0, 100),
        body: `${priceText}${availText}\n${(product.description || '').substring(0, 200)}`.trim(),
        footer: footer_text || (product.category || ''),
      };

      // Product image
      if (product.image_url) {
        card.image = product.image_url;
      }

      // Buttons - raw format for campaignHelpers to process
      card.buttons = [];
      card.buttons.push({
        type: 'quick_reply',
        text: 'Order Now',
        value: `order_${product.retailer_id || product.id || idx}`,
      });

      if (product.url) {
        card.buttons.push({
          type: 'visit_website',
          text: 'View Details',
          url: product.url,
          value: product.url,
        });
      }

      return card;
    });

    const carouselContent = {
      text: body_text || 'Browse our products and order directly!',
      title: header_text || '🛍️ Product Catalog',
      footer: footer_text || 'Reply with product name to order',
      cards: cards,
    };

    console.log(`[NODE-CATALOG] Sending carousel with ${cards.length} product cards`);
    const result = await sendCarouselMessage(sock, finalNumber, carouselContent);
    const duration = Date.now() - startTime;

    if (result.success) {
      console.log(`[NODE-CATALOG] ✅ Carousel sent (${duration}ms)`);
      console.log(`[NODE-CATALOG] ========== END ==========`);
      return res.status(200).send({ success: true, message: "Product catalog sent!", messageId: result.messageId });
    } else {
      console.error(`[NODE-CATALOG] ❌ Carousel failed: ${result.error}`);
      console.log(`[NODE-CATALOG] ========== END ==========`);
      return res.status(500).send({ success: false, error: result.error });
    }
  } catch (error) {
    const duration = Date.now() - startTime;
    console.error(`[NODE-CATALOG] ❌ ERROR (${duration}ms): ${error.message}`);
    console.log(`[NODE-CATALOG] ========== END ==========`);
    return res.status(500).send({ success: false, error: error.message });
  }
};

// Send a reaction (emoji) on a recent outbound message. Empty string
// clears the reaction. We don't yet track WA message ids on Laravel's
// side, so we resolve the bubble by scanning the most recent outbound
// messages this client has sent to the target jid.
//
// Per Itsukichan/Baileys README:
//   sock.sendMessage(jid, { react: { text: '👍', key: <originalKey> } })
export const sendReaction = async (req, res, app) => {
  const senderPhoneNumber = req.params.phoneNumber;
  const body = req.body.json || req.body;
  const {
    targetPhoneNumber,
    targetJid,
    targetMessageId,
    fromMe,
    emoji,
  } = body;

  console.log(`[NODE-REACT] ▶ ENTER from=${senderPhoneNumber} payload=`, JSON.stringify({
    targetPhoneNumber, targetJid, targetMessageId, fromMe, emoji,
  }));

  try {
    const sock = app.locals.clients[senderPhoneNumber];
    const ready = app.locals.client_ready?.[senderPhoneNumber];
    console.log(`[NODE-REACT] sock=${!!sock} ready=${ready}`);
    if (!sock || !ready) {
      console.warn(`[NODE-REACT] ✗ CLIENT NOT READY for ${senderPhoneNumber}`);
      return res.status(503).send({ error: "CLIENT NOT READY" });
    }
    // Prefer the JID Laravel sent (handles LID-routed chats correctly);
    // fall back to phone-form for older calls.
    const jid = targetJid && targetJid.includes('@') ? targetJid : formatPhoneNumber(targetPhoneNumber);

    // Build the reaction target key. When Laravel passed us an explicit
    // wa_message_id we use it directly — this is the ONLY way to react
    // to an inbound (customer's) message correctly. Without it we'd
    // have to scan the store and only outbound messages would work.
    let targetKey = null;
    if (targetMessageId) {
      targetKey = {
        remoteJid: jid,
        fromMe:    fromMe === true || fromMe === 'true',
        id:        String(targetMessageId),
      };
    } else {
      // Legacy fallback — most-recent fromMe message in the chat.
      const manager = app.locals.clientManagers?.[senderPhoneNumber];
      const store   = manager?.store;
      try {
        const chat = store?.messages?.[jid];
        if (chat?.array?.length) {
          for (let i = chat.array.length - 1; i >= 0; i--) {
            const msg = chat.array[i];
            if (msg?.key?.fromMe) { targetKey = msg.key; break; }
          }
        }
      } catch (e) { /* ignore */ }
    }

    if (!targetKey) {
      console.warn(`[NODE-REACT] no target key resolved for ${jid}`);
      return res.status(404).send({ error: "No target message to react to" });
    }

    const sent = await sock.sendMessage(jid, {
      react: { text: emoji || '', key: targetKey },
    });
    console.log(`[NODE-REACT] ✅ reaction sent id=${sent?.key?.id}`);
    return res.status(200).send({ ok: true, id: sent?.key?.id || null });
  } catch (error) {
    console.error(`[NODE-REACT] ❌ ${error?.message}`);
    return res.status(500).send({ error: "REACTION FAILED", details: error.message });
  }
};

/**
 * Pin / Unpin a message on the recipient's chat. Per Baileys README:
 *   sendMessage(jid, { pin: { type: 1 | 2, time: <seconds>, key } })
 *   type=1 → pin, type=2 → unpin. time = 86400 (24h) | 604800 (7d) | 2592000 (30d).
 */
export const pinMessage = async (req, res, app) => {
  const senderPhoneNumber = req.params.phoneNumber;
  const body = req.body.json || req.body;
  const { targetPhoneNumber, targetJid, targetMessageId, fromMe, pin, duration } = body;

  console.log(`[NODE-PIN] ▶ ENTER from=${senderPhoneNumber} payload=`, JSON.stringify({
    targetPhoneNumber, targetJid, targetMessageId, fromMe, pin, duration,
  }));

  try {
    const sock = app.locals.clients[senderPhoneNumber];
    const ready = app.locals.client_ready?.[senderPhoneNumber];
    console.log(`[NODE-PIN] sock=${!!sock} ready=${ready}`);
    if (!sock || !ready) {
      console.warn(`[NODE-PIN] ✗ CLIENT NOT READY for ${senderPhoneNumber}`);
      return res.status(503).send({ error: "CLIENT NOT READY" });
    }
    if (!targetMessageId) {
      console.warn(`[NODE-PIN] ✗ no targetMessageId — original message has no wa_message_id stored`);
      return res.status(400).send({ error: "targetMessageId required — message has no WhatsApp id stored" });
    }
    const jid = targetJid && targetJid.includes('@') ? targetJid : formatPhoneNumber(targetPhoneNumber);
    const key = {
      remoteJid: jid,
      fromMe:    fromMe === true || fromMe === 'true',
      id:        String(targetMessageId),
    };
    const wantPin = pin === false || pin === 'false' ? false : true;
    const time    = parseInt(duration, 10) || 86400;
    console.log(`[NODE-PIN] → calling sock.sendMessage jid=${jid} type=${wantPin ? 1 : 2} time=${time} key=`, key);
    const sent = await sock.sendMessage(jid, {
      pin: { type: wantPin ? 1 : 2, time, key },
    });
    console.log(`[NODE-PIN] ✅ ${wantPin ? 'pinned' : 'unpinned'} target=${targetMessageId} jid=${jid} resultId=${sent?.key?.id}`);
    return res.status(200).send({ ok: true, id: sent?.key?.id || null });
  } catch (error) {
    console.error(`[NODE-PIN] ❌ ${error?.message}\n${error?.stack}`);
    return res.status(500).send({ error: "PIN FAILED", details: error.message });
  }
};

/**
 * Star / Unstar a message. Per Baileys README this uses `chatModify`,
 * not `sendMessage`. The star is stored on the user's own WhatsApp
 * client (sync'd via Web), not pushed to the recipient.
 */
export const starMessage = async (req, res, app) => {
  const senderPhoneNumber = req.params.phoneNumber;
  const body = req.body.json || req.body;
  const { targetPhoneNumber, targetJid, targetMessageId, fromMe, star } = body;

  console.log(`[NODE-STAR] ▶ ENTER from=${senderPhoneNumber} payload=`, JSON.stringify({
    targetPhoneNumber, targetJid, targetMessageId, fromMe, star,
  }));

  try {
    const sock = app.locals.clients[senderPhoneNumber];
    const ready = app.locals.client_ready?.[senderPhoneNumber];
    console.log(`[NODE-STAR] sock=${!!sock} ready=${ready}`);
    if (!sock || !ready) {
      console.warn(`[NODE-STAR] ✗ CLIENT NOT READY for ${senderPhoneNumber}`);
      return res.status(503).send({ error: "CLIENT NOT READY" });
    }
    if (!targetMessageId) {
      console.warn(`[NODE-STAR] ✗ no targetMessageId — original message has no wa_message_id stored`);
      return res.status(400).send({ error: "targetMessageId required — message has no WhatsApp id stored" });
    }
    const jid = targetJid && targetJid.includes('@') ? targetJid : formatPhoneNumber(targetPhoneNumber);
    const wantStar = star === false || star === 'false' ? false : true;
    const fm = fromMe === true || fromMe === 'true';
    console.log(`[NODE-STAR] → calling sock.chatModify jid=${jid} fromMe=${fm} star=${wantStar} id=${targetMessageId}`);
    await sock.chatModify({
      star: { messages: [{ id: String(targetMessageId), fromMe: fm }], star: wantStar },
    }, jid);
    console.log(`[NODE-STAR] ✅ ${wantStar ? 'starred' : 'unstarred'} target=${targetMessageId} jid=${jid}`);
    return res.status(200).send({ ok: true });
  } catch (error) {
    console.error(`[NODE-STAR] ❌ ${error?.message}\n${error?.stack}`);
    return res.status(500).send({ error: "STAR FAILED", details: error.message });
  }
};

/**
 * Delete a message for everyone (revoke). Per itsukichan/baileys
 * README § "Deleting Messages (for everyone)":
 *
 *     await sock.sendMessage(jid, { delete: msg.key })
 *
 * where msg.key = { id, remoteJid, fromMe }. Recipient's WhatsApp
 * replaces the bubble with "This message was deleted".
 *
 * Same auth/shape as pin/star: Laravel posts targetMessageId +
 * targetJid + fromMe, we reconstruct the key and call sendMessage.
 */
/**
 * Edit a previously-sent message. Per itsukichan/baileys README §
 * "Editing Messages":
 *
 *   await sock.sendMessage(jid, { text: 'updated text', edit: msg.key })
 *
 * WhatsApp's own client enforces a 15-min edit window — Laravel's
 * messageEdit() already validates that before calling here, so we
 * just forward the payload through Baileys and bubble up the result.
 */
export const editMessage = async (req, res, app) => {
  const senderPhoneNumber = req.params.phoneNumber;
  const body = req.body.json || req.body;
  const { targetPhoneNumber, targetJid, targetMessageId, fromMe, newText } = body;

  console.log(`[NODE-EDIT] ▶ ENTER from=${senderPhoneNumber} payload=`, JSON.stringify({
    targetPhoneNumber, targetJid, targetMessageId, fromMe,
    newTextPreview: typeof newText === 'string' ? newText.slice(0, 80) : null,
  }));

  try {
    const sock  = app.locals.clients[senderPhoneNumber];
    const ready = app.locals.client_ready?.[senderPhoneNumber];
    if (!sock || !ready) {
      console.warn(`[NODE-EDIT] ✗ CLIENT NOT READY for ${senderPhoneNumber}`);
      return res.status(503).send({ error: "CLIENT NOT READY" });
    }
    if (!targetMessageId) {
      return res.status(400).send({ error: "targetMessageId required — message has no WhatsApp id stored" });
    }
    if (typeof newText !== 'string' || newText.trim() === '') {
      return res.status(400).send({ error: "newText required" });
    }
    const jid = targetJid && targetJid.includes('@') ? targetJid : formatPhoneNumber(targetPhoneNumber);
    const key = {
      remoteJid: jid,
      fromMe:    fromMe === true || fromMe === 'true',
      id:        String(targetMessageId),
    };
    console.log(`[NODE-EDIT] → sock.sendMessage(jid, { text, edit: key }) jid=${jid} key=`, key);
    const sent = await sock.sendMessage(jid, { text: newText, edit: key });
    console.log(`[NODE-EDIT] ✅ edited target=${targetMessageId} jid=${jid} editId=${sent?.key?.id}`);
    return res.status(200).send({ ok: true, id: sent?.key?.id || null });
  } catch (error) {
    console.error(`[NODE-EDIT] ❌ ${error?.message}\n${error?.stack}`);
    return res.status(500).send({ error: "EDIT FAILED", details: error.message });
  }
};

export const deleteMessage = async (req, res, app) => {
  const senderPhoneNumber = req.params.phoneNumber;
  const body = req.body.json || req.body;
  const { targetPhoneNumber, targetJid, targetMessageId, fromMe } = body;

  console.log(`[NODE-DELETE] ▶ ENTER from=${senderPhoneNumber} payload=`, JSON.stringify({
    targetPhoneNumber, targetJid, targetMessageId, fromMe,
  }));

  try {
    const sock = app.locals.clients[senderPhoneNumber];
    const ready = app.locals.client_ready?.[senderPhoneNumber];
    console.log(`[NODE-DELETE] sock=${!!sock} ready=${ready}`);
    if (!sock || !ready) {
      console.warn(`[NODE-DELETE] ✗ CLIENT NOT READY for ${senderPhoneNumber}`);
      return res.status(503).send({ error: "CLIENT NOT READY" });
    }
    if (!targetMessageId) {
      console.warn(`[NODE-DELETE] ✗ no targetMessageId`);
      return res.status(400).send({ error: "targetMessageId required — message has no WhatsApp id stored" });
    }
    const jid = targetJid && targetJid.includes('@') ? targetJid : formatPhoneNumber(targetPhoneNumber);
    const key = {
      remoteJid: jid,
      fromMe:    fromMe === true || fromMe === 'true',
      id:        String(targetMessageId),
    };
    console.log(`[NODE-DELETE] → calling sock.sendMessage(jid, { delete: key }) jid=${jid} key=`, key);
    const sent = await sock.sendMessage(jid, { delete: key });
    console.log(`[NODE-DELETE] ✅ revoked target=${targetMessageId} jid=${jid} tombstoneId=${sent?.key?.id}`);
    return res.status(200).send({ ok: true, id: sent?.key?.id || null });
  } catch (error) {
    console.error(`[NODE-DELETE] ❌ ${error?.message}\n${error?.stack}`);
    return res.status(500).send({ error: "DELETE FAILED", details: error.message });
  }
};

/**
 * Send a contact card (vCard) to the recipient. Per Baileys README:
 *   sendMessage(jid, { contacts: { displayName, contacts: [{ vcard }] } })
 *
 * Laravel calls this for forwarded contact cards or operator-initiated sends.
 */
export const sendContact = async (req, res, app) => {
  const senderPhoneNumber = req.params.phoneNumber;
  const { targetPhoneNumber, targetJid, displayName, name, phone, vcard } = req.body.json || req.body;
  try {
    const sock = app.locals.clients[senderPhoneNumber];
    if (!sock || !app.locals.client_ready?.[senderPhoneNumber]) {
      return res.status(503).send({ error: "CLIENT NOT READY" });
    }
    const jid = targetJid && targetJid.includes('@') ? targetJid : formatPhoneNumber(targetPhoneNumber);
    let card = vcard;
    if (!card) {
      // Build a minimal vCard from name + phone when the caller didn't
      // supply a full vcard string.
      const cleanPhone = (phone || '').replace(/[^\d+]/g, '');
      const waid       = cleanPhone.replace(/^\+/, '');
      card = 'BEGIN:VCARD\n' +
             'VERSION:3.0\n' +
             `FN:${(name || displayName || 'Contact').replace(/[\r\n]/g, ' ')}\n` +
             (cleanPhone ? `TEL;type=CELL;type=VOICE;waid=${waid}:${cleanPhone}\n` : '') +
             'END:VCARD';
    }
    const sent = await sock.sendMessage(jid, {
      contacts: {
        displayName: displayName || name || 'Contact',
        contacts: [{ vcard: card }],
      },
    });
    console.log(`[NODE-CONTACT] sent to ${jid} name="${displayName || name}"`);
    return res.status(200).send({ ok: true, id: sent?.key?.id || null });
  } catch (error) {
    console.error(`[NODE-CONTACT] ❌ ${error?.message}`);
    return res.status(500).send({ error: "CONTACT SEND FAILED", details: error.message });
  }
};

// ── Send a message into a GROUP with optional @mentions (Jessica P4) ──
// Used by the order → group notifier. Baileys group send: pass the group jid
// (…@g.us) plus a `mentions` array of participant jids; the text must already
// contain the matching @<number> tokens for WhatsApp to render the mention.
// Unofficial API only — WABA/Cloud cannot post into arbitrary groups.
export const sendGroupMessage = async (req, res, app) => {
  const senderPhoneNumber = req.params.phoneNumber;
  const { group_jid, text = "", mentions = [] } = req.body.json || req.body;
  try {
    const sock = app.locals.clients?.[senderPhoneNumber];
    if (!sock) {
      return res.status(503).send({ error: "CLIENT NOT READY", details: `bot ${senderPhoneNumber} not connected` });
    }
    let jid = String(group_jid || "");
    if (!jid) return res.status(400).send({ error: "group_jid required" });
    if (!jid.endsWith("@g.us")) jid = jid.replace(/[^0-9-]/g, "") + "@g.us";

    const mentionJids = (Array.isArray(mentions) ? mentions : [])
      .map((m) => String(m).replace(/\D+/g, ""))
      .filter(Boolean)
      .map((d) => `${d}@s.whatsapp.net`);

    const sent = await sock.sendMessage(jid, { text: String(text), mentions: mentionJids });
    console.log(`[NODE-GROUP] sent to ${jid} mentions=${mentionJids.length} id=${sent?.key?.id || ""}`);
    return res.status(200).send({ message: "GROUP MESSAGE SENT", id: sent?.key?.id || null });
  } catch (e) {
    console.error(`[NODE-GROUP] send failed: ${e?.message}`);
    return res.status(500).send({ error: "GROUP SEND FAILED", details: e?.message });
  }
};