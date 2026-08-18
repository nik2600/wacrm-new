// services/scheduleService.js
// ==============================
// Scheduling Service with Complete Attribute Support
// ==============================
import cron from "node-cron";
import moment from "moment-timezone";
import axios from "axios";
import { sendCarouselMessage } from "../utils/campaignHelpers.js";
import {
  formatPhoneNumber,
  downloadAndPrepareMediaBaileys,
  updateBulkScheduleStatus,
  fetchQueueRecipients,
  formatInteractiveButtonsForBaileys,
  laravelHeaders,
  // 2026-05-24 — scheduled messages now support WABA sends via the
  // same PHP-prebuilt meta_payloads fast-path that broadcasts use.
  // Previously scheduleService had ZERO WABA code → every scheduled
  // WABA send failed with "Client not found" (tried Baileys, no
  // session). These two helpers add the WABA branch.
  getWhatsAppSettings,
  sendMessageViaFacebookApi,
  sendMessageViaTwilioApi,
  refreshMessageSettings,
} from "../utils/helpers.js";
import {
  getJitteredMessageDelay,
  getJitteredBatchGapMs,
  getBatchSettings,
  isSockUsable,
  isSockDropError,
  bumpDailyTally,
  dailyCapRemaining,
  isTwilioSettings,
  sendTwilioBulkMessage,
  SOCK_DROPPED_ERROR,
} from "../utils/sendSafety.js";

/**
 * Append the workspace's branded footer to a plain text body. Mirrors
 * flowService._appendFooterToText — same shape so a contact gets the
 * same look whether the send came from a flow or a scheduled batch.
 * Templates handle their own footer (in the Meta-approved template
 * definition), so this is plain-text-only.
 */
function _appendFooterToText(body, settings) {
  const footer = settings && settings.branding_footer;
  if (!footer) return body;
  const needle = `\n\n_${footer}_`;
  const text = String(body || '');
  if (text.endsWith(needle)) return text;
  return text + needle;
}

/**
 * Build a Baileys-shaped media payload from the helper's
 * { buffer, mimetype, extension } return value. Routes by mimetype
 * so PDFs go out as documents, MP4s as videos, etc. — without this
 * everything was being shoved into `image:` as a whole object, which
 * Baileys silently rejected and the bulk loop marked failed.
 *
 *   - image/*       → { image: buffer, caption }
 *   - video/*       → { video: buffer, caption }
 *   - audio/*       → { audio: buffer, mimetype, ptt: false }
 *   - everything    → { document: buffer, mimetype, fileName, caption }
 */
function buildBaileysMediaPayload(media, caption, mediaUrl) {
  if (!media || !media.buffer) {
    // Misshapen helper return — fall back to URL form so Baileys can
    // at least attempt to fetch it itself.
    return { image: { url: mediaUrl }, caption };
  }
  const m = (media.mimetype || '').toLowerCase();
  const fileName = (mediaUrl || '').split('/').pop() || ('file' + (media.extension || ''));
  if (m.startsWith('image/'))  return { image: media.buffer, caption };
  if (m.startsWith('video/'))  return { video: media.buffer, caption };
  if (m.startsWith('audio/'))  return { audio: media.buffer, mimetype: media.mimetype || 'audio/mp4', ptt: false };
  return { document: media.buffer, mimetype: media.mimetype || 'application/octet-stream', fileName, caption };
}

/**
 * POST one per-recipient outcome to Laravel so the /scheduled/{id}
 * detail page can show who sent / failed / read. Best-effort — a
 * failed webhook never breaks the send loop, just gets logged.
 *
 * Endpoint pre-seeds pivot rows at store() time, so the typical
 * payload just updates an existing row's status. Unknown phones
 * (e.g. group grew between store and fire) get a fresh pivot row.
 */
async function postRecipientStatus(appDomainName, scheduleId, phone, status, extra = {}) {
  if (!appDomainName || !scheduleId || !phone) return;
  try {
    await axios.post(
      `${appDomainName}/api/update-scheduled-contact-status`,
      {
        scheduleId,
        phone,
        status,
        error: extra.error || null,
        messageId: extra.messageId || null,
        timestamp: extra.timestamp || new Date().toISOString(),
      },
      { headers: laravelHeaders(), timeout: 5000 }
    );
  } catch (err) {
    console.warn(`[SCHED-RECIPIENT] webhook failed for ${phone}: ${err.message}`);
  }
}

/**
 * Replace placeholders in text with attribute values.
 * Supports two layers:
 *   1. Positional → named: `{{1}}` translated via variableMap to `{{name}}`
 *      so Meta-style templates without explicit named placeholders still
 *      resolve to contact fields (the operator configures variable_map
 *      on the template once).
 *   2. Named → value: `{{name}}` → attributes.name. Falls back to the
 *      literal placeholder so unresolved entries stay visible during QA.
 * Example: "Hello {{name}}" with {name: "John"} => "Hello John"
 *          "Hello {{1}}" with variableMap={1:"name"} + {name:"John"} => "Hello John"
 */
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

function replacePlaceholders(text, attributes, variableMap) {
  if (!text || typeof text !== 'string') return text;
  let newText = text;

  // Positional hop. Walk the supplied variableMap and rewrite
  // `{{N}}` → `{{key}}` so the named pass below resolves it.
  const flatMap = normalizeVariableMap(variableMap);
  if (flatMap) {
    for (const [slot, key] of Object.entries(flatMap)) {
      if (!key) continue;
      const rx = new RegExp('\\{\\{\\s*' + String(slot).replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&') + '\\s*\\}\\}', 'g');
      newText = newText.replace(rx, '{{' + key + '}}');
    }
  }

  if (attributes && typeof attributes === 'object') {
    for (const [key, value] of Object.entries(attributes)) {
      const placeholder = `{{${key}}}`;
      newText = newText.split(placeholder).join(value || "");
    }
  }

  return newText;
}

// Pacing + sock-safety + ban gates live in node/utils/sendSafety.js so
// every bulk-send service shares the same anti-fingerprint logic.
// getMessageDelay/getBatchGapMs were inlined here pre-2026-05-27.

export async function executeBulkScheduledMessage(
  nodeScheduleId,
  app,
  appDomainName
) {

  try {
    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === nodeScheduleId
    );
    if (msgIndex === -1) {

      return;
    }
    
    const bulkSchedule = app.locals.scheduledMessages[msgIndex];

    
    if (
      bulkSchedule.status === "cancelled" ||
      bulkSchedule.status === "paused"
    ) {

      return;
    }
    
    // Resolve engine — Baileys session OR WABA settings OR Twilio.
    // The row's `provider` (stamped by Laravel at store time from the
    // operator's picker) is AUTHORITATIVE. Without this gate, every
    // scheduled send was routed by a workspace-level heuristic
    // (settings.use_facebook_api) — so a Baileys-picked schedule
    // for a phone that ALSO had a WABA config in the same workspace
    // got silently re-routed through Meta Cloud API. Old rows without
    // `provider` fall back to the settings heuristic for compat.
    const sock = app.locals.clients[bulkSchedule.senderPhoneNumber];
    const settings = await getWhatsAppSettings(appDomainName, { phone: bulkSchedule.senderPhoneNumber });
    const rowProvider = (bulkSchedule.provider || '').toString().toLowerCase().trim();
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
    console.log(`[SCHED-ROUTE] schedule=${bulkSchedule.scheduleId} provider=${rowProvider || '(unset)'} useWaba=${useWaba} useTwilio=${useTwilio}`);

    // A Twilio workspace doesn't need a Baileys sock — its sends go
    // through the Twilio REST API. Same for WABA. Only fail when
    // none of the three engines are reachable.
    if (!sock && !useWaba && !useTwilio) {
      bulkSchedule.status = "failed";
      bulkSchedule.error = "Client not found";
      await updateBulkScheduleStatus(
        bulkSchedule.scheduleId,
        "failed",
        0,
        0,
        appDomainName
      );
      return;
    }
    
    // Validate recipients
    if (
      !bulkSchedule.targetPhoneNumbers ||
      bulkSchedule.targetPhoneNumbers.length === 0
    ) {

      bulkSchedule.status = "failed";
      bulkSchedule.error = "No recipients";
      await updateBulkScheduleStatus(
        bulkSchedule.scheduleId,
        "failed",
        0,
        0,
        appDomainName
      );
      return;
    }

    bulkSchedule.status = "sending";

    // Honour the admin's latest pacing on this run even if Node's cached copy
    // was stale — same fix as the campaign service ("set 120s, sent instantly").
    await refreshMessageSettings(app, appDomainName);

    const batchSettings = getBatchSettings(app.locals);
    const totalRecipients = bulkSchedule.targetPhoneNumbers.length;

    // Anti-ban: Baileys daily-cap pre-check. Same default 4k/day/device,
    // pauses + flags when reached. Skips for WABA + Twilio (Meta and
    // Twilio have their own server-side rate limiting). The legacy
    // `_useWaba` derived only from `bulkSchedule.useFacebookApi`,
    // which doesn't exist on rows polled via /api/scheduled/active
    // and ignored the new row-level provider routing entirely.
    if (!useWaba && !useTwilio) {
      const remaining = dailyCapRemaining(app.locals, bulkSchedule.senderPhoneNumber);
      if (remaining <= 0) {
        const cap = app.locals.messageSettings?.baileys_daily_cap || 4000;
        console.warn(`[SCHED-BULK] Baileys daily cap ${cap} reached for ${bulkSchedule.senderPhoneNumber} — schedule paused`);
        bulkSchedule.status = 'paused';
        bulkSchedule.error = `Daily Baileys send cap (${cap}) reached. Resume after midnight.`;
        await updateBulkScheduleStatus(bulkSchedule.scheduleId, 'paused', 0, 0, appDomainName);
        return;
      }
    }

    let sentCount = 0;
    let failedCount = 0;
    const failedNumbers = [];

    const sendToNumber = async (targetNumber, globalIndex) => {
      // Sock-liveness gate. If Baileys disconnected mid-run, pause the
      // schedule + throw sentinel so the outer loop bails out cleanly.
      // WABA + Twilio don't need a sock, so the gate only fires for
      // Baileys-routed rows.
      if (!useWaba && !useTwilio && !isSockUsable(sock, app.locals, bulkSchedule.senderPhoneNumber)) {
        bulkSchedule.status = 'paused';
        bulkSchedule.error = 'Baileys connection dropped mid-run. Resume after reconnect.';
        await updateBulkScheduleStatus(bulkSchedule.scheduleId, 'paused', sentCount, failedCount, appDomainName);
        throw new Error(SOCK_DROPPED_ERROR);
      }
      try {


        if (!targetNumber) {

          failedCount++;
          failedNumbers.push(targetNumber);
          return;
        }

        const finalNumber = formatPhoneNumber(targetNumber);
        
        // ✅ FIX: Get attributes for this specific recipient
        const attributes = bulkSchedule.recipientAttributes 
          ? bulkSchedule.recipientAttributes[targetNumber] 
          : {};

        let success = false;
        let whatsappMessageId = null;

        // === WABA FAST PATH ============================================
        // If this workspace uses Facebook Cloud API AND the PHP side
        // pre-built a per-phone Meta payload, ship it straight to
        // Graph. Sets `success` + `whatsappMessageId` and falls
        // through to the existing post-loop status callback below
        // — we DO NOT early-return because that skipped the
        // sentCount++ and updateScheduledContactStatus call, so every
        // successful WABA scheduled send was reporting as "failed"
        // to Laravel.
        let wabaErrorMessage = null;
        if (useWaba) {
          const prebuilt = bulkSchedule.templateData?.meta_payloads_by_phone?.[targetNumber]
                        || bulkSchedule.templateData?.meta_payloads_by_phone?.[finalNumber];
          if (prebuilt && prebuilt.name) {
            const result = await sendMessageViaFacebookApi(
              targetNumber,
              { type: 'template', template: prebuilt },
              settings
            );
            success = !!result.success;
            whatsappMessageId = result.messageId || null;
            if (!success) {
              wabaErrorMessage = result.error || 'WABA send returned no success flag';
              console.warn(`[SCHED-WABA] send failed for ${targetNumber}: ${wabaErrorMessage}`);
            }
          } else if (bulkSchedule.messageType === 'text' && bulkSchedule.message) {
            // No prebuilt payload + this is a freeform text send
            // — works inside 24h customer-service window only.
            // Plain text → append the plan-gated brand footer.
            const text = _appendFooterToText((bulkSchedule.message || '').toString(), settings);
            const result = await sendMessageViaFacebookApi(
              targetNumber,
              { type: 'text', text: { preview_url: false, body: text } },
              settings
            );
            success = !!result.success;
            whatsappMessageId = result.messageId || null;
            if (!success) {
              wabaErrorMessage = result.error || 'WABA text send failed';
              console.warn(`[SCHED-WABA] text send failed for ${targetNumber}: ${wabaErrorMessage}`);
            }
          } else {
            // WABA workspace but message type isn't shipped — refuse
            // rather than fall through to a Baileys switch that has no
            // session.
            wabaErrorMessage = 'No Meta payload built for this scheduled send. Use a template OR plain text within the 24h customer-service window.';
            console.warn(`[SCHED-WABA] refused: ${wabaErrorMessage}`);
          }
        }

        // For WABA workspaces, skip the Baileys switch — we already
        // executed (or refused) above. The status reporting below
        // runs identically for both paths.
        if (useWaba) {
          if (success) {
            sentCount++;
          } else {
            failedCount++;
            failedNumbers.push(targetNumber);
          }
          await postRecipientStatus(
            appDomainName,
            bulkSchedule.scheduleId,
            finalNumber,
            success ? 'sent' : 'failed',
            { error: success ? null : wabaErrorMessage, messageId: whatsappMessageId }
          );
          return;
        }

        // Twilio workspaces follow the same shape: handle here then
        // return so we don't fall into the Baileys switch that would
        // crash on null sock. ContentSid path when registered, plain
        // Body / MediaUrl otherwise.
        if (useTwilio) {
          let twilioErrorMessage = null;
          const contactDataForTwilio = {
            phone: targetNumber,
            name: '',
            custom_attributes: {},
          };
          if (bulkSchedule.messageType === 'template' && bulkSchedule.templateData) {
            const result = await sendTwilioBulkMessage(
              targetNumber,
              bulkSchedule.templateData,
              contactDataForTwilio,
              bulkSchedule.templateData.template_body || bulkSchedule.message || bulkSchedule.templateData.template_type === 'carousel' || '',
              settings,
              sendMessageViaTwilioApi
            );
            success = !!result.success;
            whatsappMessageId = result.messageId || null;
            if (!success) twilioErrorMessage = result.error || 'Twilio template send failed';
          } else if (bulkSchedule.messageType === 'media' && bulkSchedule.mediaUrl) {
            const result = await sendMessageViaTwilioApi(
              targetNumber,
              { type: 'media', mediaUrl: bulkSchedule.mediaUrl, body: bulkSchedule.message || '' },
              settings
            );
            success = !!result.success;
            whatsappMessageId = result.messageId || null;
            if (!success) twilioErrorMessage = result.error || 'Twilio media send failed';
          } else if (bulkSchedule.message) {
            const result = await sendMessageViaTwilioApi(
              targetNumber,
              { type: 'text', body: _appendFooterToText(String(bulkSchedule.message), settings) },
              settings
            );
            success = !!result.success;
            whatsappMessageId = result.messageId || null;
            if (!success) twilioErrorMessage = result.error || 'Twilio text send failed';
          } else {
            twilioErrorMessage = 'No template / media / text body provided for Twilio send.';
          }

          if (success) sentCount++; else { failedCount++; failedNumbers.push(targetNumber); }
          await postRecipientStatus(
            appDomainName,
            bulkSchedule.scheduleId,
            finalNumber,
            success ? 'sent' : 'failed',
            { error: success ? null : twilioErrorMessage, messageId: whatsappMessageId }
          );
          return;
        }

        switch (bulkSchedule.messageType) {
          case "text":
            if (
              !bulkSchedule.message ||
              typeof bulkSchedule.message !== "string"
            ) {

              failedCount++;
              failedNumbers.push(targetNumber);
              break;
            }
            
            // ✅ FIX: Replace placeholders in text message
            let textMessage = bulkSchedule.message;
            if (attributes && Object.keys(attributes).length > 0) {
              textMessage = replacePlaceholders(textMessage, attributes);

            }
            
            await sock.sendMessage(finalNumber, { text: _appendFooterToText(textMessage, settings) });
            success = true;

            break;
            
          case "template":
            if (
              bulkSchedule.isTemplate &&
              bulkSchedule.templateData &&
              (bulkSchedule.templateData.template_body || bulkSchedule.message)
            ) {
              // ✅ FIX: Use let instead of const for variables that will be reassigned
              let { template_body, header, footer, buttons } = bulkSchedule.templateData;
              // Normalise buttons - the schedule-store round-trip can hand them back as
              // a JSON string; formatInteractiveButtonsForBaileys needs a real array.
              if (typeof buttons === "string") { try { buttons = JSON.parse(buttons); } catch (_) { buttons = []; } }
              if (!Array.isArray(buttons)) buttons = [];
              console.log(`[SCHED-TPL-BTN] buttons normalizedCount=${buttons.length}`);
              const { attachment_type, attachment_file, attachment_url } =
                bulkSchedule.templateData;
              const vmap = bulkSchedule.templateData.variable_map || null;

                // Handle Carousel
                const { template_type, carousel_data } = bulkSchedule.templateData;
                if (template_type === 'carousel' && carousel_data) {
                  const carouselCards = typeof carousel_data === 'string' ? JSON.parse(carousel_data) : carousel_data;
                  for (const [index, card] of carouselCards.entries()) {
                    let processedCard = { ...card };
                    if (card.image_filename) {
                      processedCard.image = `${process.env.APP_DOMAIN_NAME}/uploads/templates/carousel/${card.image_filename}`;
                    } else if (card.image && !card.image.startsWith('http')) {
                      processedCard.image = `${process.env.APP_DOMAIN_NAME}/uploads/templates/carousel/${card.image}`;
                    }
                    processedCard.title = replacePlaceholders(card.title || '', attributes, vmap);
                    processedCard.body = replacePlaceholders(card.body || '', attributes, vmap);
                    if (card.footer) processedCard.footer = replacePlaceholders(card.footer || '', attributes, vmap);
                    carouselCards[index] = processedCard;
                  }
                  
                  let carouselText = replacePlaceholders(template_body || '', attributes, vmap);
                  const carouselContent = { text: carouselText, cards: carouselCards };
                  
                  const result = await sendCarouselMessage(sock, finalNumber, carouselContent);
                  if (result.error) {
                    throw new Error(result.error || 'Failed to send carousel');
                  }
                  success = true;
                  break;
                }


              // Resolve placeholders in template body, header, and footer —
              // positional {{1}} via vmap, then named {{name}} via the
              // recipient's attribute map.
              template_body = replacePlaceholders(template_body, attributes, vmap);
              if (header) header = replacePlaceholders(header, attributes, vmap);
              if (footer) footer = replacePlaceholders(footer, attributes, vmap);

              // Header text is rendered above the body so the recipient
              // sees the full template — the bulk-Baileys path previously
              // dropped header text entirely.
              const bodyWithHeader = header
                ? `*${header}*\n\n${template_body}`
                : template_body;

              // Header media — send as the message's attachment (image /
              // video / document) with the body+footer as caption. Without
              // this, scheduled bulk Baileys templates with a media header
              // silently dropped the image.
              if (attachment_type && (attachment_file || attachment_url)) {
                // Prefer PHP-inlined base64 (resolveTemplateData now ships
                // attachment_base64 + attachment_mime) so the media can't be
                // dropped because Node can't reach the storage URL. Fall back
                // to URL download only when base64 is absent.
                const td = bulkSchedule.templateData;
                let mediaData = null;
                try {
                  if (td.attachment_base64) {
                    const buf = Buffer.from(td.attachment_base64, "base64");
                    if (buf && buf.length > 0) {
                      mediaData = { buffer: buf, mimetype: td.attachment_mime || undefined };
                    }
                  } else {
                    const mediaUrl =
                      attachment_url ||
                      `${process.env.APP_DOMAIN_NAME}/storage/wa-templates/${attachment_file}`;
                    mediaData = await downloadAndPrepareMediaBaileys(mediaUrl);
                  }
                } catch (e) {
                  console.error(`[ATTACHMENT-MISSING] [SCHEDULE] attachment prepare failed type=${attachment_type} file=${attachment_file} hadBase64=${!!td.attachment_base64}: ${e?.message}`);
                  mediaData = null;
                }

                if (mediaData && mediaData.buffer && mediaData.buffer.length > 0) {
                  const caption =
                    bodyWithHeader + (footer ? `\n\n${footer}` : "");
                  const mediaMsg = {};
                  if (attachment_type === "image") {
                    mediaMsg.image = mediaData.buffer;
                    mediaMsg.caption = caption;
                  } else if (attachment_type === "video") {
                    mediaMsg.video = mediaData.buffer;
                    mediaMsg.caption = caption;
                  } else if (attachment_type === "document") {
                    mediaMsg.document = mediaData.buffer;
                    mediaMsg.fileName = attachment_file || "file";
                    mediaMsg.caption = caption;
                    if (mediaData.mimetype) mediaMsg.mimetype = mediaData.mimetype;
                  } else {
                    mediaMsg.audio = mediaData.buffer;
                  }
                  if (Array.isArray(buttons) && buttons.length > 0 && attachment_type !== "audio") {
                    // Template buttons on a media header were dropped on the scheduled path;
                    // the broadcast path already includes them. Keep footer inside the caption.
                    mediaMsg.interactiveButtons = formatInteractiveButtonsForBaileys(buttons);
                  }
                  await sock.sendMessage(finalNumber, mediaMsg);
                  success = true;
                  break;
                }

                // A media attachment WAS configured but no bytes could be
                // produced. Do NOT fall through to the text-only buttonMessage
                // below as if nothing was attached — leave success=false and
                // break so the post-switch accounting marks this recipient
                // failed (and reports it to Laravel) instead of a false "sent".
                console.error(`[ATTACHMENT-MISSING] [SCHEDULE] configured attachment could NOT be sent — refusing text-only. type=${attachment_type} file=${attachment_file} to=${targetNumber}`);
                break;
              }

              const formattedButtons = formatInteractiveButtonsForBaileys(
                buttons || []
              );

              const buttonMessage = {
                text: bodyWithHeader,
                footer: footer || "",
                interactiveButtons: formattedButtons,
              };

              await sock.sendMessage(finalNumber, buttonMessage);
              success = true;

            } else {

              failedCount++;
              failedNumbers.push(targetNumber);
            }
            break;
            
          case "media":
            if (bulkSchedule.mediaUrl) {
              const media = await downloadAndPrepareMediaBaileys(
                bulkSchedule.mediaUrl
              );

              // Replace placeholders in media caption — positional
              // {{1}} via variable_map first, then named contact attrs.
              let caption = (bulkSchedule.message || "").toString();
              const vmap = bulkSchedule.templateData?.variable_map || null;
              caption = replacePlaceholders(caption, attributes, vmap);

              // Route by mimetype so docs/videos/audio go out in the
              // correct Baileys slot. Previously the whole helper
              // return object was passed as `image:` which Baileys
              // silently rejected → entire media path failed.
              const payload = buildBaileysMediaPayload(media, caption, bulkSchedule.mediaUrl);
              console.log(`[SCHED-MEDIA] sending → ${finalNumber} payloadKeys=${Object.keys(payload).join(',')} bufferSize=${media?.buffer?.length ?? 'n/a'} mimetype=${media?.mimetype ?? 'n/a'}`);
              await sock.sendMessage(finalNumber, payload);
              success = true;

            } else {
              console.warn(`⚠️ No media URL for ${targetNumber}`);
            }
            break;
            
          case "location":
            if (bulkSchedule.latitude && bulkSchedule.longitude) {
              // ✅ FIX: Replace placeholders in location name
              let locationName = (bulkSchedule.message || "Location").toString();
              if (attributes && Object.keys(attributes).length > 0) {
                locationName = replacePlaceholders(locationName, attributes);
              }
              
              await sock.sendMessage(finalNumber, {
                location: {
                  degreesLatitude: parseFloat(bulkSchedule.latitude),
                  degreesLongitude: parseFloat(bulkSchedule.longitude),
                  name: locationName,
                },
              });
              success = true;

            } else {
              console.warn(`⚠️ No coordinates for ${targetNumber}`);
            }
            break;
            
          default:
            console.warn(
              `⚠️ Unknown message type: ${bulkSchedule.messageType}`
            );
        }

        if (success) {
          sentCount++;
          // Bump per-device daily Baileys tally on success — drives the
          // 4k/day cap pre-check above. Only count Baileys sends since
          // WABA/Twilio have their own server-side rate limits.
          if (!useWaba && !useTwilio) {
            bumpDailyTally(app.locals, bulkSchedule.senderPhoneNumber);
          }
        } else {
          failedCount++;
          failedNumbers.push(targetNumber);
        }
        // Per-recipient outcome → Laravel pivot. Always fired, success
        // OR fail, so the detail page can show who got what status.
        // Best-effort: a failed webhook never breaks the send loop.
        await postRecipientStatus(
          appDomainName,
          bulkSchedule.scheduleId,
          finalNumber,
          success ? "sent" : "failed",
          { error: success ? null : "send returned no success flag" }
        );
      } catch (error) {
        // Surface the actual reason — silent failures kept us guessing
        // why media sends were "failed" without any error trail.
        console.error(`[SCHED-SEND] ${finalNumber} → FAIL type=${bulkSchedule.messageType}: ${error.message || error}`);
        if (error.stack) console.error(error.stack);

        failedCount++;
        failedNumbers.push(targetNumber);
        await postRecipientStatus(
          appDomainName,
          bulkSchedule.scheduleId,
          finalNumber,
          "failed",
          { error: error.message || String(error) }
        );
      } finally {
        // Fresh jittered gap each iteration — anti-fingerprint.
        await new Promise((resolve) => setTimeout(resolve, getJitteredMessageDelay(app.locals)));
      }
    };

    // Wrap loops so SOCK_DROPPED sentinel unwinds cleanly without
    // marking remaining recipients failed.
    try {
    // Batch processing or sequential sending
    if (
      batchSettings.enabled &&
      totalRecipients > batchSettings.messagesPerBatch
    ) {
      const totalBatches = Math.ceil(
        totalRecipients / batchSettings.messagesPerBatch
      );


      for (
        let batchStart = 0;
        batchStart < totalRecipients;
        batchStart += batchSettings.messagesPerBatch
      ) {
        const batchNumber = Math.floor(
          batchStart / batchSettings.messagesPerBatch
        ) + 1;
        const batchEnd = Math.min(
          batchStart + batchSettings.messagesPerBatch,
          totalRecipients
        );
        const batchRecipients = bulkSchedule.targetPhoneNumbers.slice(
          batchStart,
          batchEnd
        );


        for (let i = 0; i < batchRecipients.length; i++) {
          await sendToNumber(batchRecipients[i], batchStart + i + 1);
        }

        if (batchEnd < totalRecipients) {
          // Re-rolled jittered batch gap.
          await new Promise((resolve) => setTimeout(resolve, getJitteredBatchGapMs(app.locals)));
        }
      }
    } else {

      for (let i = 0; i < bulkSchedule.targetPhoneNumbers.length; i++) {
        await sendToNumber(bulkSchedule.targetPhoneNumbers[i], i + 1);
      }
    }
    } catch (loopErr) {
      if (isSockDropError(loopErr)) {
        console.log(`[SCHED-BULK] paused after sock drop — schedule ${bulkSchedule.scheduleId} sent=${sentCount}/${totalRecipients}`);
        return;
      }
      throw loopErr;
    }

    // Update final status
    bulkSchedule.status =
      failedCount === bulkSchedule.targetPhoneNumbers.length
        ? "failed"
        : "completed";
    bulkSchedule.sentCount = sentCount;
    bulkSchedule.failedCount = failedCount;
    bulkSchedule.failedNumbers = failedNumbers;
    bulkSchedule.completedAt = moment().format();

    
    // Update Laravel backend
    await updateBulkScheduleStatus(
      bulkSchedule.scheduleId,
      bulkSchedule.status,
      sentCount,
      sentCount,
      appDomainName
    );

  } catch (error) {

    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === nodeScheduleId
    );
    if (msgIndex !== -1) {
      app.locals.scheduledMessages[msgIndex].status = "failed";
      app.locals.scheduledMessages[msgIndex].error = error.message;
      await updateBulkScheduleStatus(
        app.locals.scheduledMessages[msgIndex].scheduleId,
        "failed",
        0,
        0,
        appDomainName
      );
    }
  } finally {
    if (app.locals.scheduledJobs[nodeScheduleId]) {
      app.locals.scheduledJobs[nodeScheduleId].stop();
      delete app.locals.scheduledJobs[nodeScheduleId];

    }
  }
}

// ✅ NEW: Recurring schedule now supports attributes
export async function executeRecurringSchedule(
  nodeScheduleId,
  app,
  appDomainName
) {

  try {
    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === nodeScheduleId
    );
    if (msgIndex === -1) {

      return;
    }
    
    const recurringSchedule = app.locals.scheduledMessages[msgIndex];

    
    if (
      recurringSchedule.status === "cancelled" ||
      recurringSchedule.status === "paused"
    ) {

      return;
    }
    
    // Check end date
    if (
      recurringSchedule.endDate &&
      moment()
        .tz(recurringSchedule.timezone)
        .isAfter(moment(recurringSchedule.endDate))
    ) {

      recurringSchedule.status = "completed";
      if (app.locals.scheduledJobs[nodeScheduleId]) {
        app.locals.scheduledJobs[nodeScheduleId].stop();
        delete app.locals.scheduledJobs[nodeScheduleId];
      }
      await axios.post(appDomainName + "/api/update-schedule-status", {
        scheduleId: recurringSchedule.scheduleId,
        status: "completed",
        phoneNumber: recurringSchedule.senderPhoneNumber,
      }, { headers: laravelHeaders() });

      return;
    }
    
    // Resolve engine. The row's `provider` is AUTHORITATIVE — see the
    // matching block in executeBulkSchedule for the full rationale.
    // Recurring rows that predate the per-row provider stamp fall
    // back to the workspace-settings heuristic.
    const sock = app.locals.clients[recurringSchedule.senderPhoneNumber];
    const settings = await getWhatsAppSettings(appDomainName, { phone: recurringSchedule.senderPhoneNumber });
    const rowProvider = (recurringSchedule.provider || '').toString().toLowerCase().trim();
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
    console.log(`[SCHED-RECURRING-ROUTE] schedule=${recurringSchedule.scheduleId} provider=${rowProvider || '(unset)'} useWaba=${useWaba} useTwilio=${useTwilio}`);

    if (!sock && !useWaba && !useTwilio) {
      return;
    }
    
    // Resolve recipients. New flow: Laravel snapshots the phone list at
    // schedule time and ships it as `targetPhoneNumbers`, so we use that
    // first. Legacy flow used `targetQueues` + a Laravel callback to look
    // up phone numbers per queue id; keep that as a fallback so older
    // schedules registered before this change still fire.
    let recipients = Array.isArray(recurringSchedule.targetPhoneNumbers)
      ? recurringSchedule.targetPhoneNumbers.filter(Boolean)
      : [];

    if (recipients.length === 0 && Array.isArray(recurringSchedule.targetQueues) && recurringSchedule.targetQueues.length > 0) {
      recipients = await fetchQueueRecipients(
        recurringSchedule.targetQueues,
        appDomainName
      );
    }

    if (!recipients || recipients.length === 0) {
      console.warn(
        `⚠️ No recipients found for recurring schedule ${nodeScheduleId}`
      );
      return;
    }


    // Honour the admin's latest pacing on this run even if Node's cached copy
    // was stale — same fix as the campaign service ("set 120s, sent instantly").
    await refreshMessageSettings(app, appDomainName);

    const batchSettings = getBatchSettings(app.locals);
    const totalRecipients = recipients.length;

    // Anti-ban: Baileys daily-cap pre-check (skip for WABA + Twilio
    // — Meta and Twilio have their own rate limiting on the server).
    if (!useWaba && !useTwilio) {
      const remaining = dailyCapRemaining(app.locals, recurringSchedule.senderPhoneNumber);
      if (remaining <= 0) {
        const cap = app.locals.messageSettings?.baileys_daily_cap || 4000;
        console.warn(`[SCHED-RECURRING] Baileys daily cap ${cap} reached for ${recurringSchedule.senderPhoneNumber} — recurring run skipped`);
        return;
      }
    }

    let sentCount = 0;
    let failedCount = 0;

    const sendRecurringMessage = async (targetNumber, globalIndex) => {
      // Sock-liveness gate — skip if sock dropped (recurring schedules
      // can fire while a device is reconnecting; don't burn the list).
      // WABA + Twilio routes don't use sock — skip the gate for them.
      if (!useWaba && !useTwilio && !isSockUsable(sock, app.locals, recurringSchedule.senderPhoneNumber)) {
        throw new Error(SOCK_DROPPED_ERROR);
      }
      // Hoist outside the try so the catch block at the bottom can
      // reference it even if formatPhoneNumber throws — previously a
      // ReferenceError on `finalNumber` would mask the real error.
      let finalNumber = targetNumber;
      try {
        finalNumber = formatPhoneNumber(targetNumber);

        // ✅ NEW: Get attributes for this recipient
        const attributes = recurringSchedule.recipientAttributes
          ? recurringSchedule.recipientAttributes[targetNumber]
          : {};

        let success = false;
        let whatsappMessageId = null;
        let wabaErrorMessage = null;

        // === WABA FAST PATH ===
        // Same pattern as the one-off scheduler. Use PHP-prebuilt
        // meta_payloads_by_phone when present; fall back to plain
        // text for 24h CSW sends; refuse otherwise rather than try
        // a missing Baileys session.
        if (useWaba) {
          const prebuilt = recurringSchedule.templateData?.meta_payloads_by_phone?.[targetNumber]
                        || recurringSchedule.templateData?.meta_payloads_by_phone?.[finalNumber];
          if (prebuilt && prebuilt.name) {
            const result = await sendMessageViaFacebookApi(
              targetNumber,
              { type: 'template', template: prebuilt },
              settings
            );
            success = !!result.success;
            whatsappMessageId = result.messageId || null;
            if (!success) wabaErrorMessage = result.error || 'WABA recurring send failed';
          } else if (recurringSchedule.messageType === 'text' && recurringSchedule.message) {
            const text = (recurringSchedule.message || '').toString();
            const result = await sendMessageViaFacebookApi(
              targetNumber,
              { type: 'text', text: { preview_url: false, body: text } },
              settings
            );
            success = !!result.success;
            whatsappMessageId = result.messageId || null;
            if (!success) wabaErrorMessage = result.error || 'WABA recurring text send failed';
          } else {
            wabaErrorMessage = 'No Meta payload built for this recurring send.';
          }

          if (success) sentCount++; else failedCount++;
          await postRecipientStatus(
            appDomainName,
            recurringSchedule.scheduleId,
            finalNumber,
            success ? 'sent' : 'failed',
            { error: success ? null : wabaErrorMessage, messageId: whatsappMessageId }
          );
          return; // exit try; finally still fires the delay
        }

        // === TWILIO FAST PATH ===
        // Recurring schedules on a Twilio workspace need the same
        // dedicated branch the bulk path has, otherwise they fall
        // into the Baileys switch and crash on a null sock.
        if (useTwilio) {
          let twilioErrorMessage = null;
          const contactDataForTwilio = {
            phone: targetNumber,
            name: '',
            custom_attributes: attributes || {},
          };
          if (recurringSchedule.messageType === 'template' && recurringSchedule.templateData) {
            const result = await sendTwilioBulkMessage(
              targetNumber,
              recurringSchedule.templateData,
              contactDataForTwilio,
              recurringSchedule.templateData.template_body || recurringSchedule.message || '',
              settings,
              sendMessageViaTwilioApi
            );
            success = !!result.success;
            whatsappMessageId = result.messageId || null;
            if (!success) twilioErrorMessage = result.error || 'Twilio recurring template send failed';
          } else if (recurringSchedule.messageType === 'media' && recurringSchedule.mediaUrl) {
            const result = await sendMessageViaTwilioApi(
              targetNumber,
              { type: 'media', mediaUrl: recurringSchedule.mediaUrl, body: recurringSchedule.message || '' },
              settings
            );
            success = !!result.success;
            whatsappMessageId = result.messageId || null;
            if (!success) twilioErrorMessage = result.error || 'Twilio recurring media send failed';
          } else if (recurringSchedule.message) {
            const result = await sendMessageViaTwilioApi(
              targetNumber,
              { type: 'text', body: _appendFooterToText(String(recurringSchedule.message), settings) },
              settings
            );
            success = !!result.success;
            whatsappMessageId = result.messageId || null;
            if (!success) twilioErrorMessage = result.error || 'Twilio recurring text send failed';
          } else {
            twilioErrorMessage = 'No template / media / text body provided for Twilio recurring send.';
          }

          if (success) sentCount++; else failedCount++;
          await postRecipientStatus(
            appDomainName,
            recurringSchedule.scheduleId,
            finalNumber,
            success ? 'sent' : 'failed',
            { error: success ? null : twilioErrorMessage, messageId: whatsappMessageId }
          );
          return;
        }

        switch (recurringSchedule.messageType) {
          case "text":
            // ✅ NEW: Replace placeholders in text
            let textMessage = recurringSchedule.message;
            if (attributes && Object.keys(attributes).length > 0) {
              textMessage = replacePlaceholders(textMessage, attributes);

            }
            
            await sock.sendMessage(finalNumber, { text: _appendFooterToText(textMessage, settings) });
            success = true;
            break;
            
          case "media":
            if (recurringSchedule.mediaUrl) {
              const media = await downloadAndPrepareMediaBaileys(
                recurringSchedule.mediaUrl
              );

              let caption = recurringSchedule.message || "";
              const vmap = recurringSchedule.templateData?.variable_map || null;
              caption = replacePlaceholders(caption, attributes, vmap);

              const payload = buildBaileysMediaPayload(media, caption, recurringSchedule.mediaUrl);
              await sock.sendMessage(finalNumber, payload);
              success = true;
            }
            break;
            
          case "location":
            if (recurringSchedule.latitude && recurringSchedule.longitude) {
              // ✅ NEW: Replace placeholders in location name
              let locationName = recurringSchedule.message || "Location";
              if (attributes && Object.keys(attributes).length > 0) {
                locationName = replacePlaceholders(locationName, attributes);
              }
              
              await sock.sendMessage(finalNumber, {
                location: {
                  degreesLatitude: parseFloat(recurringSchedule.latitude),
                  degreesLongitude: parseFloat(recurringSchedule.longitude),
                  name: locationName,
                },
              });
              success = true;
            }
            break;
        }

        if (success) {
          sentCount++;
          // Recurring Baileys success — bump daily tally so the cap
          // pre-check accumulates across each cron firing within a day.
          if (!useWaba) {
            bumpDailyTally(app.locals, recurringSchedule.senderPhoneNumber);
          }
        } else {
          failedCount++;
        }
        // Same per-recipient pivot update used by the one-off branch
        // — keeps the /scheduled/{id} detail page consistent for
        // recurring jobs too.
        await postRecipientStatus(
          appDomainName,
          recurringSchedule.scheduleId,
          finalNumber,
          success ? "sent" : "failed",
          { error: success ? null : "send returned no success flag" }
        );
      } catch (error) {

        failedCount++;
        await postRecipientStatus(
          appDomainName,
          recurringSchedule.scheduleId,
          finalNumber,
          "failed",
          { error: error.message || String(error) }
        );
      } finally {
        // Fresh jittered gap each pass — anti-fingerprint.
        await new Promise((resolve) => setTimeout(resolve, getJitteredMessageDelay(app.locals)));
      }
    };

    // Wrap loops so a Baileys sock-drop unwinds cleanly via SOCK_DROPPED.
    try {
    if (
      batchSettings.enabled &&
      totalRecipients > batchSettings.messagesPerBatch
    ) {
      const totalBatches = Math.ceil(
        totalRecipients / batchSettings.messagesPerBatch
      );


      for (
        let batchStart = 0;
        batchStart < totalRecipients;
        batchStart += batchSettings.messagesPerBatch
      ) {
        const batchNumber = Math.floor(
          batchStart / batchSettings.messagesPerBatch
        ) + 1;
        const batchEnd = Math.min(
          batchStart + batchSettings.messagesPerBatch,
          totalRecipients
        );
        const batchRecipients = recipients.slice(batchStart, batchEnd);


        for (let i = 0; i < batchRecipients.length; i++) {
          await sendRecurringMessage(batchRecipients[i], batchStart + i + 1);
        }

        if (batchEnd < totalRecipients) {
          // Re-rolled jittered batch gap.
          await new Promise((resolve) => setTimeout(resolve, getJitteredBatchGapMs(app.locals)));
        }
      }
    } else {

      for (let i = 0; i < recipients.length; i++) {
        await sendRecurringMessage(recipients[i], i + 1);
      }
    }
    } catch (loopErr) {
      if (isSockDropError(loopErr)) {
        console.log(`[SCHED-RECURRING] paused mid-run after sock drop — schedule ${recurringSchedule.scheduleId} sent=${sentCount}/${totalRecipients}`);
        return;
      }
      throw loopErr;
    }

    // Update run statistics
    recurringSchedule.totalRuns = (recurringSchedule.totalRuns || 0) + 1;
    recurringSchedule.lastRunAt = moment().format();

    
    await axios.post(appDomainName + "/api/update-schedule-status", {
      scheduleId: recurringSchedule.scheduleId,
      status: "running",
      totalSent: sentCount,
      // Honesty fix: don't fake delivered = sent (socket-accept ≠ WhatsApp relay).
      totalDelivered: 0,
      lastRunAt: recurringSchedule.lastRunAt,
      phoneNumber: recurringSchedule.senderPhoneNumber,
    }, { headers: laravelHeaders() });

  } catch (error) {

  }
}

// Execute single scheduled message
export async function executeScheduledMessage(scheduleId, app) {

  try {
    const messageIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === scheduleId
    );
    if (messageIndex === -1) {

      return;
    }
    
    const scheduledMsg = app.locals.scheduledMessages[messageIndex];

    
    if (
      scheduledMsg.status === "cancelled" ||
      scheduledMsg.status === "paused"
    ) {

      return;
    }
    
    const sock = app.locals.clients[scheduledMsg.senderPhoneNumber];
    if (!sock) {

      scheduledMsg.status = "failed";
      scheduledMsg.error = "Client not found";
      return;
    }
    
    const finalNumber = formatPhoneNumber(scheduledMsg.targetPhoneNumber);
    let success = false;

    
    switch (scheduledMsg.messageType) {
      case "text":
        await sock.sendMessage(finalNumber, { text: _appendFooterToText(scheduledMsg.message, settings) });
        success = true;

        break;
        
      case "media":
      case "media_with_caption":
        if (scheduledMsg.mediaUrl) {
          const media = await downloadAndPrepareMediaBaileys(
            scheduledMsg.mediaUrl
          );
          const payload = buildBaileysMediaPayload(
            media,
            scheduledMsg.caption || "",
            scheduledMsg.mediaUrl
          );
          await sock.sendMessage(finalNumber, payload);
          success = true;

        }
        break;
        
      case "location":
        if (scheduledMsg.latitude && scheduledMsg.longitude) {
          await sock.sendMessage(finalNumber, {
            location: {
              degreesLatitude: parseFloat(scheduledMsg.latitude),
              degreesLongitude: parseFloat(scheduledMsg.longitude),
              name: scheduledMsg.message || "Location",
            },
          });
          success = true;

        }
        break;
        
      default:
        throw new Error(`Invalid message type: ${scheduledMsg.messageType}`);
    }
    
    if (success) {
      scheduledMsg.status = "sent";
      scheduledMsg.sentAt = moment().format();

    }
  } catch (error) {

    const messageIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === scheduleId
    );
    if (messageIndex !== -1) {
      app.locals.scheduledMessages[messageIndex].status = "failed";
      app.locals.scheduledMessages[messageIndex].error = error.message;
    }
  } finally {
    if (app.locals.scheduledJobs[scheduleId]) {
      app.locals.scheduledJobs[scheduleId].stop();
      delete app.locals.scheduledJobs[scheduleId];

    }
  }
}