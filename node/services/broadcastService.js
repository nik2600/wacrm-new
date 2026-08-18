// services/broadcastService.js - WITH FIXED IMAGE + BUTTONS HANDLING
import axios from "axios";
import moment from "moment-timezone";
import {
  formatPhoneNumber,
  getWhatsAppSettings,
  sendMessageViaFacebookApi,
  sendMessageViaTwilioApi,
  formatInteractiveButtonsForBaileys,
  downloadAndPrepareMediaBaileys,
  laravelHeaders,
  refreshMessageSettings,
} from "../utils/helpers.js";
import {
  getJitteredMessageDelay,
  getJitteredBatchGapMs,
  getBatchSettings,
  isSockUsable,
  isSockDropError,
  isAuthTemplate,
  bumpDailyTally,
  dailyCapRemaining,
  isTwilioSettings,
  sendTwilioBulkMessage,
  assertHttpsMedia,
  SOCK_DROPPED_ERROR,
} from "../utils/sendSafety.js";
import { sendCarouselMessage } from "../utils/campaignHelpers.js";

// Pacing + sock-safety + ban gates live in node/utils/sendSafety.js so
// every bulk-send service shares the same anti-fingerprint logic.

/**
 * Replace attribute placeholders with actual contact data
 */
// Positional → named hop. WhatsApp Meta templates can use either
//   {{1}} {{2}}   — positional, with a `variable_map` JSON on the template
//                   mapping each slot to an attribute key
//   {{name}} {{email}} — named placeholders matching contact fields
// We translate positional into named first (when variable_map is
// supplied) so the named-resolution pass below picks them up.
// Flatten a template variable_map into {slot:key}. Accepts BOTH the flat
// shape {"1":"name"} AND the nested stored shape {header:[{num,key}],
// body:[{num,key}]} that Laravel ships verbatim. Mirrors
// App\Services\AttributeResolver::normalizeVariableMap (PHP). body wins on
// a slot collision. Returns null when not a usable object.
function normalizeVariableMap(map) {
  if (!map || typeof map !== 'object' || Array.isArray(map)) return null;
  if (map.header === undefined && map.body === undefined) return map;
  const flat = {};
  for (const section of ['header', 'body']) {
    const entries = map[section];
    if (!Array.isArray(entries)) continue;
    for (const entry of entries) {
      if (entry && typeof entry === 'object' && entry.num !== undefined && entry.key) {
        flat[String(entry.num)] = String(entry.key);
      }
    }
  }
  return flat;
}

function applyVariableMap(text, variableMap) {
  if (!text) return text;
  const flatMap = normalizeVariableMap(variableMap);
  if (!flatMap) return text;
  let out = text;
  for (const [slot, key] of Object.entries(flatMap)) {
    if (!key) continue;
    // Match `{{1}}`, `{{ 1 }}`, etc. Slot is a string from the map keys.
    const rx = new RegExp('\\{\\{\\s*' + String(slot).replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&') + '\\s*\\}\\}', 'g');
    out = out.replace(rx, '{{' + key + '}}');
  }
  return out;
}

function replaceAttributePlaceholders(text, contactData, variableMap) {
  if (!text) return text;
  // Step 1: positional → named, when the operator configured a map.
  let replacedText = applyVariableMap(String(text), variableMap);
  if (!contactData) return replacedText;
  // Step 2: named → value from contactData (or custom_attributes blob
  // if the contact carries one). Falls back to the literal match so
  // unresolved placeholders are visible in QA.
  const placeholderRegex = /\{\{\s*([\w.-]+)\s*\}\}/g;
  replacedText = replacedText.replace(placeholderRegex, (match, key) => {
    if (contactData[key] !== undefined && contactData[key] !== null && contactData[key] !== '') return contactData[key];
    const custom = contactData.custom_attributes;
    if (custom && typeof custom === 'object' && custom[key] !== undefined && custom[key] !== '') return custom[key];
    return match;
  });
  return replacedText;
}

/**
 * Per-recipient click-tracked body, when PHP shipped one.
 *
 * PHP sends `tracked_bodies` as a contactId → body map where every http(s)
 * URL has been swapped for that recipient's own /r/{token} shortlink. The
 * shortlink has to differ per recipient or a click can't be attributed to
 * anyone, which is why this is a map and not a rewrite of the single shared
 * template_body.
 *
 * Returns null when PHP didn't ship one (link tracking off, no URLs in the
 * body, older Laravel) — callers then use the shared body exactly as before.
 * Placeholder substitution still runs on whatever comes back: wrapping only
 * touches URLs and leaves {{name}} and friends alone.
 */
function trackedBodyFor(broadcast, contactData) {
  const map = broadcast && broadcast.tracked_bodies;
  if (!map || !contactData) return null;
  const id = contactData.id;
  if (id === undefined || id === null) return null;
  const body = map[id] !== undefined ? map[id] : map[String(id)];
  return typeof body === 'string' && body !== '' ? body : null;
}

/**
 * Build proper WABA API payload from template data + contact data
 * Returns: { phoneNumber, messageData } ready for sendMessageViaFacebookApi
 *
 * 2026-05-24 update — PHP now pre-builds the full Meta `type:template`
 * payload per recipient (with buttons / carousel / media / LinkTracker
 * URLs) and ships it under `templateData.meta_payloads[contactId]`.
 * When present we use it verbatim and skip the partial builder below —
 * that branch only handled header+body text params and silently dropped
 * buttons / carousel / media headers, which led to Meta error 132000
 * for any non-trivial template.
 */
function buildWabaPayload(contactData, templateData) {
  // Extract phone number from contact
  let phoneNumber;
  if (contactData.country_code && contactData.mobile) {
    phoneNumber = contactData.country_code.toString().replace('+', '') + contactData.mobile;
  } else if (contactData.phone) {
    phoneNumber = contactData.phone.toString().replace(/\D/g, '');
  } else {
    throw new Error('No phone number found in contact data');
  }

  // === FAST PATH — PHP pre-built the Meta payload for this recipient ===
  // The map is keyed by contact id (numeric or stringified). Pass the
  // template object straight to Meta — Graph will validate components.
  const prebuilt = templateData.meta_payloads
    ? (templateData.meta_payloads[contactData.id] || templateData.meta_payloads[String(contactData.id)])
    : null;
  if (prebuilt && prebuilt.name) {
    console.log(`[WABA-PAYLOAD] using PHP-prebuilt meta_payload for contact ${contactData.id} (${(prebuilt.components || []).length} components)`);
    return {
      phoneNumber,
      messageData: { type: 'template', template: prebuilt },
    };
  }

  const body = templateData.template_body || "";
  const header = templateData.header || "";
  const footer = templateData.footer || "";
  const variableMap = templateData.variable_map;

  // If template has a variable_map AND template_name, send as official template message
  // (LEGACY PARTIAL BUILDER — kept for back-compat with pre-2026-05-24
  // broadcasts whose payload doesn't include meta_payloads. Header+body
  // text params only; missing buttons/carousel/media are a KNOWN
  // limitation of this branch — PHP fast path above covers it.)
  if (variableMap && templateData.template_name) {
    const messageData = {
      type: "template",
      template: {
        name: templateData.template_name,
        language: { code: templateData.language || "en_US" },
        components: []
      }
    };

    // Build header parameters using variable_map
    if (variableMap.header && variableMap.header.length > 0) {
      const headerParams = variableMap.header.map(v => ({
        type: "text",
        text: String(contactData[v.key] || contactData.custom_attributes?.[v.key] || "")
      }));
      messageData.template.components.push({ type: "header", parameters: headerParams });
    }

    // Build body parameters using variable_map
    if (variableMap.body && variableMap.body.length > 0) {
      const bodyParams = variableMap.body.map(v => ({
        type: "text",
        text: String(contactData[v.key] || contactData.custom_attributes?.[v.key] || "")
      }));
      messageData.template.components.push({ type: "body", parameters: bodyParams });
    }

    return { phoneNumber, messageData };
  }

  // Fallback: send as plain text (for non-template broadcasts within 24h window)
  let messageText = "";
  if (header) messageText += `*${replaceAttributePlaceholders(header, contactData, variableMap)}*\n\n`;
  messageText += replaceAttributePlaceholders(body, contactData, variableMap);
  if (footer) messageText += `\n\n_${replaceAttributePlaceholders(footer, contactData, variableMap)}_`;

  // Check for attachment
  if (templateData.attachment_type && templateData.attachment_file) {
    // Prefer PHP-built attachment_url (`url('storage/wa-templates/<file>')`);
    // legacy fallback path /uploads/templates/attachments/ doesn't exist in
    // this repo — files live under public/storage/wa-templates/.
    const mediaUrl = templateData.attachment_url
      || `${process.env.APP_DOMAIN_NAME}/storage/wa-templates/${templateData.attachment_file}`;
    const mediaType = templateData.attachment_type === "audio" ? "audio" : templateData.attachment_type;

    const messageData = { type: mediaType };
    messageData[mediaType] = { link: mediaUrl };
    if (mediaType !== "audio") {
      messageData[mediaType].caption = messageText;
    }
    if (mediaType === "document") {
      messageData[mediaType].filename = templateData.attachment_file;
    }
    return { phoneNumber, messageData };
  }

  // Plain text
  const messageData = {
    type: "text",
    text: { preview_url: false, body: messageText }
  };
  return { phoneNumber, messageData };
}



/**
 * Send a LOCATION pin after a template body (Unofficial API has no template
 * location header, so it ships as its own message). Accepts Meta-style
 * {latitude, longitude, name, address} and maps to Baileys' degrees* fields.
 */
async function sendBroadcastLocationPin(sock, phoneNumber, location) {
  if (!location || typeof location !== 'object') return;
  const lat = parseFloat(location.latitude ?? location.lat);
  const lng = parseFloat(location.longitude ?? location.lng ?? location.lon);
  if (!isFinite(lat) || !isFinite(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) return;
  try {
    await sock.sendMessage(phoneNumber, { location: {
      degreesLatitude:  lat,
      degreesLongitude: lng,
      ...(location.name    ? { name:    String(location.name) }    : {}),
      ...(location.address ? { address: String(location.address) } : {}),
    }});
    console.log(`[BCAST-NODE] location pin sent (${lat}, ${lng}) → ${phoneNumber}`);
  } catch (e) {
    console.error(`[BCAST-NODE] location pin send failed: ${e?.message}`);
  }
}

/**
 * Wrapper: send the template body, then (on success) ship the location pin so
 * every send branch is covered from one place.
 */
async function sendTemplateMessage(sock, phoneNumber, templateData, contactData) {
  const result = await sendTemplateMessageCore(sock, phoneNumber, templateData, contactData);
  if (result && result.success && templateData && templateData.location) {
    await sendBroadcastLocationPin(sock, phoneNumber, templateData.location);
  }
  return result;
}

/**
 * Send template message via Baileys - WITH FIXED IMAGE + BUTTONS
 */
async function sendTemplateMessageCore(sock, phoneNumber, templateData, contactData) {
  try {
    const {
      template_type,
      template_body,
      header,
      footer,
      buttons,
      attachment_type,
      attachment_file,
      attachment_url,
      carousel_data,
      variable_map,
    } = templateData;
    // Local alias keeps every replaceAttributePlaceholders call below
    // short while still threading the positional → named hop through.
    const vmap = variable_map || null;

    // PHP builds `attachment_url` from `url('storage/wa-templates/<file>')`
    // (BroadcastsController::buildTemplateData) — use it verbatim. The
    // legacy `${APP_DOMAIN}/uploads/templates/attachments/<file>` fallback
    // points at a directory that DOES NOT EXIST in this repo (files are
    // stored under public/storage/wa-templates), so a fresh send with no
    // attachment_url would 404 and crash. We now fall back to the correct
    // /storage/wa-templates/ path instead.
    const resolveMediaUrl = () => {
      if (attachment_url) return attachment_url;
      if (!attachment_file) return null;
      return `${process.env.APP_DOMAIN_NAME}/storage/wa-templates/${attachment_file}`;
    };

    // Produce the media bytes for this template. PREFER the PHP-inlined
    // base64 (BroadcastsController::buildTemplateData now ships
    // `attachment_base64` + `attachment_mime`) so the image can never be
    // dropped because Node can't reach the storage URL. Fall back to
    // downloading from the URL only when base64 is absent. Throws on a hard
    // failure so the caller marks the recipient failed instead of sending
    // text-only. Decoded fresh per recipient from the already-in-memory
    // string (no network) — cheap even at 10k.
    const resolveMediaBuffer = async () => {
      if (templateData.attachment_base64) {
        const buf = Buffer.from(templateData.attachment_base64, "base64");
        if (!buf || buf.length === 0) {
          throw new Error("inlined attachment_base64 decoded to empty buffer");
        }
        return { buffer: buf, mimetype: templateData.attachment_mime || undefined };
      }
      const mediaUrl = resolveMediaUrl();
      const httpsErr = assertHttpsMedia(mediaUrl);
      if (httpsErr) {
        throw new Error(httpsErr);
      }
      const data = await downloadAndPrepareMediaBaileys(mediaUrl);
      if (!data || !data.buffer || data.buffer.length === 0) {
        throw new Error(`download produced empty buffer for ${mediaUrl}`);
      }
      return data;
    };


    // HANDLE CAROUSEL TEMPLATE
    console.log('[DEBUG-CAROUSEL] template_type:', template_type, 'carousel_data type:', typeof carousel_data, 'carousel_data:', carousel_data);
    if (template_type === 'carousel' && carousel_data) {

      
      const carouselCards = typeof carousel_data === 'string' 
        ? JSON.parse(carousel_data) 
        : carousel_data;

      const processedCards = [];

      for (const [index, card] of carouselCards.entries()) {
        const processedCard = {
          title: replaceAttributePlaceholders(card.title, contactData, vmap),
          body: replaceAttributePlaceholders(card.body, contactData, vmap),
          buttons: card.buttons || []
        };

        if (card.footer) {
          processedCard.footer = replaceAttributePlaceholders(card.footer, contactData, vmap);
        }

        if (card.image_path) {
          processedCard.image = card.image_path;
        } else if (card.image_filename) {
          // Carousel card images now live under /storage/wa-templates/
          // (same dir as standard template attachments). The legacy
          // /uploads/templates/carousel/ path doesn't exist in this
          // repo, so any pre-fix card image silently 404'd.
          processedCard.image = `${process.env.APP_DOMAIN_NAME}/storage/wa-templates/${card.image_filename}`;
        } else if (card.image) {
          if (card.image.startsWith('http')) {
            processedCard.image = card.image;
          } else {
            processedCard.image = `${process.env.APP_DOMAIN_NAME}/storage/wa-templates/${card.image}`;
          }
        }

        processedCards.push(processedCard);
      }

      const carouselContent = {
        text: replaceAttributePlaceholders(template_body || '', contactData, vmap),
        title: replaceAttributePlaceholders(header || '', contactData, vmap),
        footer: replaceAttributePlaceholders(footer || '', contactData, vmap),
        cards: processedCards
      };

      const result = await sendCarouselMessage(sock, phoneNumber, carouselContent);
      
      if (!result.success) {
        throw new Error(result.error || 'Failed to send carousel');
      }

      return { success: true, messageId: result.messageId };
    }

    // 🔥 HANDLE IMAGE/VIDEO/DOCUMENT WITH BUTTONS (Single Combined Message)
    // if (attachment_type && attachment_file && buttons && buttons.length > 0) {
    //   void(0);
      
    //   const mediaUrl = `${process.env.APP_DOMAIN_NAME}/uploads/templates/attachments/${attachment_file}`;
    //   const mediaData = await downloadAndPrepareMediaBaileys(mediaUrl);
      
    //   console.log(`📥 Media downloaded:`, {
    //     mimetype: mediaData.mimetype,
    //     extension: mediaData.extension,
    //     bufferSize: mediaData.buffer.length
    //   });
      
    //   const parsedButtons = typeof buttons === "string" ? JSON.parse(buttons) : buttons;
    //   const formattedButtons = formatInteractiveButtonsForBaileys(parsedButtons);
      
    //   const bodyText = replaceAttributePlaceholders(template_body, contactData);
    //   const footerText = footer ? replaceAttributePlaceholders(footer, contactData) : '';

    //   // 🔥 FIXED: Use proper format for media + buttons in single message
    //   const messageContent = {
    //     text: bodyText,
    //     footer: footerText || undefined,
    //     interactiveButtons: formattedButtons,
    //     hasMediaAttachment: true
    //   };

    //   // Add media based on type
    //   if (attachment_type === "image") {
    //     messageContent.image = mediaData.buffer;
    //     // Baileys auto-detects mimetype for images
    //   } else if (attachment_type === "video") {
    //     messageContent.video = mediaData.buffer;
    //     // Baileys auto-detects mimetype for videos
    //   } else if (attachment_type === "document") {
    //     messageContent.document = mediaData.buffer;
    //     messageContent.mimetype = mediaData.mimetype;  // Documents need explicit mimetype
    //     messageContent.fileName = attachment_file;
    //   }

    //   void(0);
    //   console.log(`📋 Message structure:`, {
    //     hasText: !!messageContent.text,
    //     hasFooter: !!messageContent.footer,
    //     hasMedia: true,
    //     mediaType: attachment_type,
    //     buttonCount: formattedButtons.length
    //   });

    //   const result = await sock.sendMessage(phoneNumber, messageContent);
      
    //   void(0);
    //   return { success: true, messageId: result.key.id };
    // }

    if (attachment_type && attachment_file && buttons && buttons.length > 0) {

      // Prefer PHP-inlined base64; fall back to URL download. A hard failure
      // here marks the recipient failed instead of silently dropping the
      // media — assertHttpsMedia hygiene is enforced inside resolveMediaBuffer
      // for the URL path (Meta rejects http:// media #131009).
      let mediaData;
      try {
        mediaData = await resolveMediaBuffer();
      } catch (e) {
        console.error(`[ATTACHMENT-MISSING] [BCAST-NODE] configured attachment could NOT be sent (media+buttons) — refusing text-only. type=${attachment_type} file=${attachment_file} hadBase64=${!!templateData.attachment_base64}: ${e?.message}`);
        return { success: false, error: `attachment-missing:${e?.message || "unknown"}` };
      }


      const parsedButtons = typeof buttons === "string" ? JSON.parse(buttons) : buttons;
      const formattedButtons = formatInteractiveButtonsForBaileys(parsedButtons);

      const captionText = replaceAttributePlaceholders(template_body, contactData, vmap) || " ";
      const footerText = footer ? replaceAttributePlaceholders(footer, contactData, vmap) : "";

      const messageContent = {
        caption: captionText,
        footer: footerText || " ",
        interactiveButtons: formattedButtons,
        hasMediaAttachment: true
      };

      // Attach media based on type (Baileys will prep the media and then wrap it as interactive)
      if (attachment_type === "image") {
        messageContent.image = mediaData.buffer;
      } else if (attachment_type === "video") {
        messageContent.video = mediaData.buffer;
      } else if (attachment_type === "document") {
        messageContent.document = mediaData.buffer;
        messageContent.mimetype = mediaData.mimetype;
        messageContent.fileName = attachment_file;
      }

      const result = await sock.sendMessage(phoneNumber, messageContent);

      return { success: true, messageId: result.key.id };
    }

    // HANDLE TEXT WITH BUTTONS (No Media)
    if (buttons && buttons.length > 0 && (!attachment_type || !attachment_file)) {

      
      const parsedButtons = typeof buttons === "string" ? JSON.parse(buttons) : buttons;
      const formattedButtons = formatInteractiveButtonsForBaileys(parsedButtons);
      
      const bodyText = replaceAttributePlaceholders(template_body, contactData, vmap);
      const footerText = footer ? replaceAttributePlaceholders(footer, contactData, vmap) : '';

      // Use interactiveButtons for text-only messages with buttons
      const buttonMessage = {
        text: bodyText,
        footer: footerText,
        interactiveButtons: formattedButtons
      };

      const result = await sock.sendMessage(phoneNumber, buttonMessage);

      return { success: true, messageId: result.key.id };
    }

    // HANDLE MEDIA WITHOUT BUTTONS (Plain Media)
    if (attachment_type && attachment_file && (!buttons || buttons.length === 0)) {

      // Prefer PHP-inlined base64; fall back to URL download. A hard failure
      // marks the recipient failed instead of silently dropping the media.
      let mediaData;
      try {
        mediaData = await resolveMediaBuffer();
      } catch (e) {
        console.error(`[ATTACHMENT-MISSING] [BCAST-NODE] configured attachment could NOT be sent (plain media) — refusing text-only. type=${attachment_type} file=${attachment_file} hadBase64=${!!templateData.attachment_base64}: ${e?.message}`);
        return { success: false, error: `attachment-missing:${e?.message || "unknown"}` };
      }


      const caption = replaceAttributePlaceholders(template_body, contactData, vmap) +
                      (footer ? `\n\n${replaceAttributePlaceholders(footer, contactData, vmap)}` : '');

      const mediaMessage = { caption };

      // 🔥 FIX: For images/videos, just pass buffer (Baileys auto-detects mimetype)
      // For documents, we need explicit mimetype and fileName
      if (attachment_type === "image") {
        mediaMessage.image = mediaData.buffer;
        // Don't add mimetype - Baileys auto-detects from buffer
      } else if (attachment_type === "video") {
        mediaMessage.video = mediaData.buffer;
        // Don't add mimetype - Baileys auto-detects from buffer
      } else if (attachment_type === "document") {
        mediaMessage.document = mediaData.buffer;
        mediaMessage.mimetype = mediaData.mimetype;  // Documents need explicit mimetype
        mediaMessage.fileName = attachment_file;
      }

      const result = await sock.sendMessage(phoneNumber, mediaMessage);

      return { success: true, messageId: result.key.id };
    }

    // PLAIN TEXT TEMPLATE (No Media, No Buttons)

    const messageText = replaceAttributePlaceholders(template_body, contactData, vmap) +
                        (footer ? `\n\n${replaceAttributePlaceholders(footer, contactData, vmap)}` : '');
    
    const result = await sock.sendMessage(phoneNumber, { text: messageText });

    return { success: true, messageId: result.key.id };
    
  } catch (error) {


    return { success: false, messageId: null, error: error.message };
  }
}

/**
 * Send plain text message via Baileys
 */
async function sendTextMessage(sock, phoneNumber, message) {
  try {
    const result = await sock.sendMessage(phoneNumber, { text: message });
    return { success: true, messageId: result.key.id };
  } catch (error) {

    return { success: false, messageId: null, error: error.message };
  }
}

/**
 * Execute broadcast schedule with DYNAMIC TIMING & BATCH PROCESSING
 */
export async function executeBroadcastSchedule(nodeScheduleId, app, appDomainName) {
  console.log('[BCAST-NODE] executeBroadcastSchedule START', { nodeScheduleId });

  try {
    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === nodeScheduleId
    );

    if (msgIndex === -1) {
      console.warn('[BCAST-NODE] schedule not found in app.locals.scheduledMessages', { nodeScheduleId });
      return;
    }

    const broadcast = app.locals.scheduledMessages[msgIndex];
    console.log('[BCAST-NODE] resolved broadcast row', {
      nodeScheduleId,
      broadcastId: broadcast.broadcastId,
      sender: broadcast.senderPhoneNumber,
      contactCount: broadcast.targetContacts?.length || 0,
      status: broadcast.status,
    });

    if (!broadcast.sentMessages) {
      broadcast.sentMessages = {};
    }

    // Honour the admin's latest pacing on this run even if Node's cached copy
    // was stale — same fix as the campaign service ("set 120s, sent instantly").
    await refreshMessageSettings(app, appDomainName);

    const batchSettings = getBatchSettings(app.locals);


    // 2026-05-24 — pass sender phone so Laravel can resolve the
    // workspace-specific WABA config. Without it, the controller falls
    // back to platform-default env credentials → wrong workspace's
    // token gets used for sends → cross-tenant credential leak.
    const settings = await getWhatsAppSettings(appDomainName, {
      phone: broadcast.senderPhoneNumber,
      broadcast_id: broadcast.broadcastId,
    });

    // Resolve engine — the row's `provider` (stamped by Laravel at store
    // time from the operator's picker, populated by Phase 3) is
    // AUTHORITATIVE. Mirrors the scheduleService routing contract exactly:
    // an explicit provider wins; an absent/unknown provider falls back to
    // the workspace-wide settings heuristic so single-engine broadcasts
    // stay byte-identical. Without this gate a Baileys-picked broadcast on
    // a phone that ALSO had a WABA config got silently re-routed via Meta.
    const rowProvider = (broadcast.provider || '').toString().toLowerCase().trim();
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
      useWaba   = !!(settings && settings.use_facebook_api && settings.facebook_phone_id && settings.facebook_api_token);
      useTwilio = isTwilioSettings(settings);
    }
    console.log(`[BCAST-ROUTE] broadcast=${broadcast.broadcastId} provider=${rowProvider || '(unset)'} useWaba=${useWaba} useTwilio=${useTwilio}`);

    // Auth-template anti-ban guard. Auth templates are Meta's separate
    // category — sending them via the broadcast flow (no OTP slot, no
    // skip-button copy) trips category-mismatch policy and tanks the
    // WABA quality score. Defence-in-depth to the PHP gate.
    if (isAuthTemplate(broadcast.templateData)) {
      console.warn(`[BCAST] refusing auth template — out of scope for broadcasts (broadcast ${broadcast.broadcastId})`);
      broadcast.status = 'failed';
      await updateBroadcastStatus(broadcast.broadcastId, 'failed', 0, 0, appDomainName);
      return;
    }

    // Baileys daily-volume soft cap. Default 4k/day/device (admin-
    // tunable via messageSettings.baileys_daily_cap). Sending past
    // ~5k/day is the #1 ban driver per Baileys community data.
    //
    // Skip for WABA AND Twilio — both providers have their own server-
    // side rate limits; sharing the per-device Baileys counter would
    // pause a Twilio workspace's broadcast once a prior Baileys run on
    // the same senderPhoneNumber filled the tally. Keyed off the resolved
    // engine so a per-row provider override caps the right path.
    if (!useWaba && !useTwilio) {
      const remaining = dailyCapRemaining(app.locals, broadcast.senderPhoneNumber);
      if (remaining <= 0) {
        const cap = app.locals.messageSettings?.baileys_daily_cap || 4000;
        console.warn(`[BCAST] Baileys daily cap ${cap} reached for ${broadcast.senderPhoneNumber} — broadcast paused`);
        broadcast.status = 'paused';
        broadcast.lastError = `Daily Baileys send cap (${cap}) reached. Resume after midnight.`;
        await updateBroadcastStatus(broadcast.broadcastId, 'paused', 0, 0, appDomainName);
        return;
      }
    }



    if (broadcast.status === "cancelled" || broadcast.status === "paused") {

      return;
    }

    if (!broadcast.targetContacts || broadcast.targetContacts.length === 0) {

      broadcast.status = "failed";
      await updateBroadcastStatus(broadcast.broadcastId, "failed", 0, 0, appDomainName);
      return;
    }

    // Baileys session requirement only applies when we're actually
    // going to use it. WABA + Twilio dispatch via REST APIs and need no
    // sock; failing the broadcast on missing sock would block every
    // Twilio/WABA workspace whose senderPhoneNumber happens to not have
    // an idle Baileys client cached.
    let sock = null;
    if (!useWaba && !useTwilio) {
      sock = app.locals.clients[broadcast.senderPhoneNumber];
      if (!sock) {
        console.error('[BCAST-NODE] sock missing — sender device has no live Baileys session', {
          nodeScheduleId,
          broadcastId: broadcast.broadcastId,
          sender: broadcast.senderPhoneNumber,
          knownClients: Object.keys(app.locals.clients || {}),
          hint: 'Pair the device at /devices first (and wait for "Connection OPEN" log) before sending broadcasts.',
        });
        broadcast.status = "failed";
        await updateBroadcastStatus(broadcast.broadcastId, "failed", 0, 0, appDomainName);
        return;
      }
    }

    broadcast.status = "sending";
    await updateBroadcastStatus(broadcast.broadcastId, "processing", 0, 0, appDomainName);

    let sentCount = 0;
    let failedCount = 0;
    const failedContacts = [];

    const totalContacts = broadcast.targetContacts.length;
    
    // Wrap outer loops so a Baileys sock drop unwinds cleanly via the
    // SOCK_DROPPED sentinel — see catch at end of this block.
    try {
    if (batchSettings.enabled && totalContacts > batchSettings.messagesPerBatch) {


      for (let batchIndex = 0; batchIndex < totalContacts; batchIndex += batchSettings.messagesPerBatch) {
        const batchNumber = Math.floor(batchIndex / batchSettings.messagesPerBatch) + 1;
        const batchEnd = Math.min(batchIndex + batchSettings.messagesPerBatch, totalContacts);
        const batchContacts = broadcast.targetContacts.slice(batchIndex, batchEnd);


        for (let i = 0; i < batchContacts.length; i++) {
          const contactData = batchContacts[i];
          const globalIndex = batchIndex + i + 1;

          // Liveness gate — if Baileys sock dropped mid-run, throw the
          // sentinel so the outer catch can pause cleanly without
          // burning the rest of the list firing into a dead pipe.
          if (!useWaba && !useTwilio && !isSockUsable(sock, app.locals, broadcast.senderPhoneNumber)) {
            broadcast.status = 'paused';
            broadcast.lastError = 'Baileys connection dropped mid-run. Resume after reconnect.';
            await updateBroadcastStatus(broadcast.broadcastId, 'paused', sentCount, failedCount, appDomainName);
            throw new Error(SOCK_DROPPED_ERROR);
          }

          try {


            if (!contactData.phone) {

              failedCount++;
              failedContacts.push(contactData);
              await updateMessageStatus(broadcast.broadcastId, contactData.id, "failed", "No phone number", appDomainName);
              continue;
            }

            const phoneNumber = contactData.phone;
            const finalNumber = formatPhoneNumber(phoneNumber);

            let success = false;
            let whatsappMessageId = null;
            let errorMessage = null;

            let personalizedTemplate = null;
            let personalizedMessage = null;

            // Prefer this recipient's click-tracked body when PHP built one.
            const tracked = trackedBodyFor(broadcast, contactData);

            if (broadcast.isTemplate && broadcast.templateData) {
              const vmap = broadcast.templateData.variable_map || null;
              personalizedTemplate = {
                ...broadcast.templateData,
                template_body: replaceAttributePlaceholders(tracked || broadcast.templateData.template_body, contactData, vmap),
                header: replaceAttributePlaceholders(broadcast.templateData.header, contactData, vmap),
                footer: replaceAttributePlaceholders(broadcast.templateData.footer, contactData, vmap),
              };
            } else if (broadcast.message) {
              personalizedMessage = replaceAttributePlaceholders(tracked || broadcast.message, contactData);
            }

            if (useWaba) {
              const templateToSend = personalizedTemplate || {
                template_body: personalizedMessage,
                footer: "",
                buttons: [],
              };

              const { phoneNumber: wabaPhone, messageData: wabaPayload } = buildWabaPayload(contactData, templateToSend);
              const result = await sendMessageViaFacebookApi(wabaPhone, wabaPayload, settings);
              success = result.success;
              whatsappMessageId = result.messageId;
              errorMessage = result.error;
            } else if (useTwilio) {
              // Twilio path: ContentSid when the template has one
              // registered, plain Body otherwise. Without this branch
              // a Twilio workspace's broadcast hit sock.sendMessage on
              // a null sock and crashed every recipient.
              const tplForTwilio = personalizedTemplate || broadcast.templateData;
              const renderedBody = personalizedMessage
                || (tplForTwilio && (tplForTwilio.template_body || ""));
              const result = await sendTwilioBulkMessage(
                contactData.phone,
                tplForTwilio,
                contactData,
                renderedBody,
                settings,
                sendMessageViaTwilioApi
              );
              success = result.success;
              whatsappMessageId = result.messageId;
              errorMessage = result.error;
            } else {
              if (personalizedTemplate) {
                const result = await sendTemplateMessage(sock, finalNumber, personalizedTemplate, contactData);
                success = result.success;
                whatsappMessageId = result.messageId;
                errorMessage = result.error;
              } else if (personalizedMessage) {
                const result = await sendTextMessage(sock, finalNumber, personalizedMessage);
                success = result.success;
                whatsappMessageId = result.messageId;
                errorMessage = result.error;
              }
            }

            if (success) {
              sentCount++;
              // Bump per-device daily Baileys counter so the cap above
              // accumulates across consecutive broadcasts on the same day.
              // Twilio + WABA have their own provider-side rate limits;
              // we only track the cap for Baileys.
              if (!useWaba && !useTwilio) {
                bumpDailyTally(app.locals, broadcast.senderPhoneNumber);
              }
              broadcast.sentMessages[whatsappMessageId] = {
                contactId: contactData.id,
                contactName: contactData.name,
                phoneNumber: finalNumber,
                sentAt: new Date().toISOString(),
              };
              await updateMessageStatus(broadcast.broadcastId, contactData.id, "sent", null, appDomainName, whatsappMessageId);
            } else {
              failedCount++;
              failedContacts.push(contactData);
              await updateMessageStatus(broadcast.broadcastId, contactData.id, "failed", errorMessage || "Failed to send", appDomainName);
            }

            // Fresh jittered gap each iteration — anti-fingerprint.
            await new Promise((resolve) => setTimeout(resolve, getJitteredMessageDelay(app.locals, broadcast.warmerGap)));

          } catch (error) {
            if (isSockDropError(error)) throw error;
            failedCount++;
            failedContacts.push(contactData);
            await updateMessageStatus(broadcast.broadcastId, contactData.id, "failed", error.message, appDomainName);
          }
        }

        if (batchEnd < totalContacts) {
          // Re-rolled jittered batch gap.
          await new Promise((resolve) => setTimeout(resolve, getJitteredBatchGapMs(app.locals)));
        }
      }
    } else {


      for (let i = 0; i < broadcast.targetContacts.length; i++) {
        const contactData = broadcast.targetContacts[i];

        // Liveness gate — same sock-drop sentinel as the batched branch.
        if (!useWaba && !useTwilio && !isSockUsable(sock, app.locals, broadcast.senderPhoneNumber)) {
          broadcast.status = 'paused';
          broadcast.lastError = 'Baileys connection dropped mid-run. Resume after reconnect.';
          await updateBroadcastStatus(broadcast.broadcastId, 'paused', sentCount, failedCount, appDomainName);
          throw new Error(SOCK_DROPPED_ERROR);
        }

        try {


          if (!contactData.phone) {
            console.warn('[BCAST-NODE] recipient skipped — no phone', { broadcastId: broadcast.broadcastId, contactId: contactData.id });
            failedCount++;
            failedContacts.push(contactData);
            await updateMessageStatus(broadcast.broadcastId, contactData.id, "failed", "No phone number", appDomainName);
            continue;
          }

          const phoneNumber = contactData.phone;
          const finalNumber = formatPhoneNumber(phoneNumber);

          let success = false;
          let whatsappMessageId = null;
          let errorMessage = null;

          let personalizedTemplate = null;
          let personalizedMessage = null;

          // Prefer this recipient's click-tracked body when PHP built one.
          const tracked = trackedBodyFor(broadcast, contactData);

          if (broadcast.isTemplate && broadcast.templateData) {
            const vmap = broadcast.templateData.variable_map || null;
            personalizedTemplate = {
              ...broadcast.templateData,
              template_body: replaceAttributePlaceholders(tracked || broadcast.templateData.template_body, contactData, vmap),
              header: replaceAttributePlaceholders(broadcast.templateData.header, contactData, vmap),
              footer: replaceAttributePlaceholders(broadcast.templateData.footer, contactData, vmap),
            };
          } else if (broadcast.message) {
            personalizedMessage = replaceAttributePlaceholders(tracked || broadcast.message, contactData);
          }

          console.log('[BCAST-NODE] → send', {
            broadcastId: broadcast.broadcastId,
            sender: broadcast.senderPhoneNumber,
            to: finalNumber,
            via: useWaba ? 'WABA' : (useTwilio ? 'Twilio' : 'Baileys'),
            kind: personalizedTemplate ? 'template' : (personalizedMessage ? 'text' : 'EMPTY'),
            body_preview: ((personalizedTemplate?.template_body) || personalizedMessage || '').substring(0, 80),
          });

          if (useWaba) {
            const templateToSend = personalizedTemplate || {
              template_body: personalizedMessage,
              footer: "",
              buttons: [],
            };

            const { phoneNumber: wabaPhone, messageData: wabaPayload } = buildWabaPayload(contactData, templateToSend);
            const result = await sendMessageViaFacebookApi(wabaPhone, wabaPayload, settings);
            success = result.success;
            whatsappMessageId = result.messageId;
            errorMessage = result.error;
          } else if (useTwilio) {
            const tplForTwilio = personalizedTemplate || broadcast.templateData;
            const renderedBody = personalizedMessage
              || (tplForTwilio && (tplForTwilio.template_body || ""));
            const result = await sendTwilioBulkMessage(
              contactData.phone,
              tplForTwilio,
              contactData,
              renderedBody,
              settings,
              sendMessageViaTwilioApi
            );
            success = result.success;
            whatsappMessageId = result.messageId;
            errorMessage = result.error;
          } else {
            if (personalizedTemplate) {
              const result = await sendTemplateMessage(sock, finalNumber, personalizedTemplate, contactData);
              success = result.success;
              whatsappMessageId = result.messageId;
              errorMessage = result.error;
            } else if (personalizedMessage) {
              const result = await sendTextMessage(sock, finalNumber, personalizedMessage);
              success = result.success;
              whatsappMessageId = result.messageId;
              errorMessage = result.error;
            } else {
              errorMessage = 'No template or message body provided';
            }
          }

          if (success) {
            sentCount++;
            // Bump per-device daily Baileys counter on success — only
            // when using Baileys; Twilio + WABA have their own provider-
            // side rate limits which we don't double-count here.
            if (!useWaba && !useTwilio) {
              bumpDailyTally(app.locals, broadcast.senderPhoneNumber);
            }
            broadcast.sentMessages[whatsappMessageId] = {
              contactId: contactData.id,
              contactName: contactData.name,
              phoneNumber: finalNumber,
              sentAt: new Date().toISOString(),
            };
            console.log('[BCAST-NODE] ✓ sent', { broadcastId: broadcast.broadcastId, to: finalNumber, msgId: whatsappMessageId });
            await updateMessageStatus(broadcast.broadcastId, contactData.id, "sent", null, appDomainName, whatsappMessageId);
          } else {
            failedCount++;
            failedContacts.push(contactData);
            console.warn('[BCAST-NODE] ✗ failed', { broadcastId: broadcast.broadcastId, to: finalNumber, err: errorMessage });
            await updateMessageStatus(broadcast.broadcastId, contactData.id, "failed", errorMessage || "Failed to send", appDomainName);
          }

          // Fresh jittered gap each pass.
          await new Promise((resolve) => setTimeout(resolve, getJitteredMessageDelay(app.locals, broadcast.warmerGap)));

        } catch (error) {
          if (isSockDropError(error)) throw error;
          console.error('[BCAST-NODE] recipient THREW', {
            broadcastId: broadcast.broadcastId,
            contactId: contactData?.id,
            err: error?.message,
          });
          failedCount++;
          failedContacts.push(contactData);
          await updateMessageStatus(broadcast.broadcastId, contactData.id, "failed", error.message, appDomainName);
        }
      }
    }
    } catch (loopErr) {
      // Sock dropped mid-broadcast — status already marked `paused`
      // upstream. Skip the "completed" finaliser below + exit clean.
      if (isSockDropError(loopErr)) {
        console.log(`[BCAST-NODE] paused after sock drop — broadcast ${broadcast.broadcastId} sent=${sentCount}/${totalContacts}`);
        return;
      }
      throw loopErr;
    }

    broadcast.status = failedCount === broadcast.targetContacts.length
      ? "failed"
      : (failedCount > 0 ? "completed_with_errors" : "completed");
    broadcast.sentCount = sentCount;
    broadcast.failedCount = failedCount;
    broadcast.failedContacts = failedContacts;
    broadcast.completedAt = moment().format();

    console.log('[BCAST-NODE] executeBroadcastSchedule DONE', {
      nodeScheduleId,
      broadcastId: broadcast.broadcastId,
      sent: sentCount,
      failed: failedCount,
      final_status: broadcast.status,
    });

    await updateBroadcastStatus(broadcast.broadcastId, broadcast.status, sentCount, failedCount, appDomainName);

  } catch (error) {
    console.error('[BCAST-NODE] executeBroadcastSchedule CRASHED', {
      nodeScheduleId,
      err: error?.message,
      stack: error?.stack,
    });
    const msgIndex = app.locals.scheduledMessages.findIndex((msg) => msg.id === nodeScheduleId);
    if (msgIndex !== -1) {
      app.locals.scheduledMessages[msgIndex].status = "failed";
      await updateBroadcastStatus(app.locals.scheduledMessages[msgIndex].broadcastId, "failed", 0, 0, appDomainName);
    }
  } finally {
    if (app.locals.scheduledJobs[nodeScheduleId]) {
      app.locals.scheduledJobs[nodeScheduleId].stop();
      delete app.locals.scheduledJobs[nodeScheduleId];
    }
  }
}

async function updateBroadcastStatus(broadcastId, status, sentCount, failedCount, appDomainName) {
  const url = `${appDomainName}/api/update-broadcast-status`;
  const payload = {
    broadcast_id: broadcastId,
    status: status,
    success_count: sentCount,
    fail_count: failedCount,
  };
  try {
    const headers = laravelHeaders();
    console.log('[BCAST-NODE] → callback /update-broadcast-status', { url, payload, hasToken: !!headers['X-Node-Token'] });
    const r = await axios.post(url, payload, { headers });
    console.log('[BCAST-NODE] ← /update-broadcast-status', { http: r.status, body: typeof r.data === 'object' ? JSON.stringify(r.data) : r.data });
  } catch (error) {
    console.error('[BCAST-NODE] ✗ /update-broadcast-status FAILED', {
      url,
      err: error?.message,
      http: error?.response?.status,
      body: error?.response?.data,
    });
  }
}

async function updateMessageStatus(
  broadcastId,
  contactId,
  status,
  errorMessage,
  appDomainName,
  whatsappMessageId = null
) {
  try {
    const payload = {
      broadcast_id: broadcastId,
      contact_id: contactId,
      status: status,
      error_message: errorMessage,
      whatsapp_message_id: whatsappMessageId,
    };

    if (status === "sent") {
      payload.sent_at = new Date().toISOString();
    } else if (status === "delivered") {
      payload.delivered_at = new Date().toISOString();
    } else if (status === "read") {
      payload.read_at = new Date().toISOString();
    }

    const url = `${appDomainName}/api/update-message-status`;
    const headers = laravelHeaders();
    const r = await axios.post(url, payload, { headers });
    if (r.status !== 200) {
      console.warn('[BCAST-NODE] /update-message-status non-200', { url, http: r.status, body: r.data });
    }
  } catch (error) {
    console.error('[BCAST-NODE] ✗ /update-message-status FAILED', {
      broadcastId, contactId,
      err: error?.message,
      http: error?.response?.status,
      body: error?.response?.data,
    });
  }
}
