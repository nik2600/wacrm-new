// services/campaignService.js
// ==============================
// Campaign Service - FIXED VERSION - Matches Broadcast Implementation
// ==============================

import moment from "moment-timezone";
import axios from "axios";
import {
  formatPhoneNumber,
  formatInteractiveButtonsForBaileys,
  downloadAndPrepareMediaBaileys,
  getWhatsAppSettings,
  sendMessageViaFacebookApi,
  sendMessageViaTwilioApi,
  laravelHeaders,
  refreshMessageSettings,
  formatMetaError,
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
  SOCK_DROPPED_ERROR,
} from "../utils/sendSafety.js";
import { sendCarouselMessage } from "../utils/campaignHelpers.js";
import { executeFlowNode } from "./flowService.js";

// Anti-ban + sock-safety primitives live in node/utils/sendSafety.js
// so all three bulk-send services (campaign/broadcast/scheduled) share
// the same logic. See that file for jitter/cap/sock-check internals.

/**
 * Rewrite URLs in message text for click tracking
 * Replaces URLs with tracking redirect: {appDomainName}/c/{tracking_id}?url={base64_original}
 */
function rewriteUrlsForTracking(text, trackingId, appDomainName) {
  if (!text || !trackingId || !appDomainName) return text;
  
  // URL regex pattern
  const urlRegex = /(https?:\/\/[^\s<>"{}|\\^`\[\]]+)/gi;
  
  return text.replace(urlRegex, (originalUrl) => {
    // Encode original URL to base64
    const encodedUrl = Buffer.from(originalUrl).toString('base64');
    // Build tracking URL
    const trackingUrl = `${appDomainName}/c/${trackingId}?url=${encodedUrl}`;
    return trackingUrl;
  });
}

/**
 * Execute campaign schedule with full tracking and Facebook API support
 */
export async function executeCampaignSchedule(nodeScheduleId, app, appDomainName) {

  
  try {
    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === nodeScheduleId
    );

    if (msgIndex === -1) {

      return;
    }

    const campaign = app.locals.scheduledMessages[msgIndex];


    // Check if campaign should run
    if (campaign.status === 'cancelled') {

      return;
    }
    
    // For recurring campaigns, ignore 'paused' or 'stopped' if recurring is active
    if (!campaign.isRecurring && campaign.status === 'paused') {

      return;
    }
    
    // For recurring campaigns, check if recurring is still active
    if (campaign.isRecurring && !campaign.recurringActive) {

      return;
    }

    if (!campaign.targetContacts || campaign.targetContacts.length === 0) {

      campaign.status = "failed";
      await updateCampaignStatus(campaign.campaignId, "failed", campaign.stats, appDomainName);
      return;
    }

    // Get WhatsApp settings
    // 2026-05-24 — pass sender phone so Laravel resolves per-workspace
    // WABA config. Without it, cross-tenant credential leak (see
    // broadcastService change of same date).
    const settings = await getWhatsAppSettings(appDomainName, {
      phone: campaign.senderPhoneNumber || campaign.devicePhone,
      campaign_id: campaign.campaignId,
    });

    // Resolve engine — the row's `provider` (stamped by Laravel at store
    // time from the operator's picker) is AUTHORITATIVE. Mirrors the
    // scheduleService routing contract exactly: an explicit provider wins,
    // an absent/unknown provider falls back to the workspace-wide settings
    // heuristic so single-engine workspaces stay byte-identical. Without
    // this gate a Baileys-picked campaign for a phone that ALSO had a WABA
    // config in the same workspace got silently re-routed through Meta.
    const rowProvider = (campaign.provider || '').toString().toLowerCase().trim();
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
    console.log(`[CAMPAIGN-ROUTE] campaign=${campaign.campaignId} provider=${rowProvider || '(unset)'} useWaba=${useWaba} useTwilio=${useTwilio}`);

    // Get Baileys client only when this run actually routes through Baileys
    // (i.e. neither WABA nor Twilio). WABA/Twilio sends go through their
    // REST APIs and need no sock.
    let sock = null;
    if (!useWaba && !useTwilio) {
      sock = app.locals.clients[campaign.senderPhoneNumber];
      if (!sock) {

        campaign.status = "failed";
        await updateCampaignStatus(campaign.campaignId, "failed", campaign.stats, appDomainName);
        return;
      }

    }

    campaign.status = "sending";

    await updateCampaignStatus(campaign.campaignId, "processing", campaign.stats, appDomainName);

    // Pull the LATEST admin pacing (msg_gap / batches_gap / bw_msg_gap /
    // enable_batches) from Laravel right now, so this run honours whatever
    // the admin last saved even if Node's cached copy was stale (started
    // before the value was set, or the post-save refresh ping never landed).
    // This is the fix for "I set a 120s gap but the campaign sent instantly".
    await refreshMessageSettings(app, appDomainName);

    // Dynamic timing controls — fresh jittered values are computed at
    // each per-message / per-batch yield via getJitteredMessageDelay()
    // and getJitteredBatchGapMs() inside the loop. Only batchSettings
    // is pre-computed because the enabled-flag + batch-size don't vary
    // mid-run.
    const batchSettings = getBatchSettings(app.locals);
    const totalContacts = campaign.targetContacts.length;


    // Load template data
    let templateDataA = null;
    let templateDataB = null;
    let templateData = null;

    if (campaign.campaignType === 'template') {
      if (campaign.isABTest) {
        templateDataA = await fetchTemplateData(campaign.templateIdA, appDomainName);
        templateDataB = await fetchTemplateData(campaign.templateIdB, appDomainName);

      } else {
        templateData = await fetchTemplateData(campaign.templateId, appDomainName);

      }
    }

    // Auth-template anti-ban guard (Baileys + WABA both). Meta marks
    // auth templates as a separate category; sending one outside the
    // auth flow trips policy + tanks the WABA quality rating. Refuse
    // at the Node entry as belt-and-braces backstop to Laravel's PHP
    // gate. Shared helper handles all the type/category synonyms.
    if (isAuthTemplate(templateData) || isAuthTemplate(campaign.templateData)) {
      console.warn(`[CAMPAIGN] refusing auth template — out of scope for campaign sends (campaign ${campaign.campaignId})`);
      campaign.status = 'failed';
      await updateCampaignStatus(campaign.campaignId, 'failed', campaign.stats, appDomainName);
      return;
    }

    // Baileys daily-volume soft cap. WhatsApp's unofficial-stack
    // ban radar fires hard around 5k/day on a fresh number; helper
    // defaults to 4k and admin can tune via messageSettings.baileys_daily_cap.
    // Only meaningful when this run routes through Baileys.
    if (!useWaba && !useTwilio) {
      const remaining = dailyCapRemaining(app.locals, campaign.senderPhoneNumber);
      if (remaining <= 0) {
        const cap = app.locals.messageSettings?.baileys_daily_cap || 4000;
        console.warn(`[CAMPAIGN] Baileys daily cap ${cap} reached for ${campaign.senderPhoneNumber} — campaign deferred`);
        campaign.status = 'paused';
        campaign.lastError = `Daily Baileys send cap (${cap}) reached. Resume after midnight.`;
        await updateCampaignStatus(campaign.campaignId, 'paused', campaign.stats, appDomainName);
        return;
      }
      if (remaining < totalContacts) {
        console.warn(`[CAMPAIGN] only ${remaining} of ${totalContacts} fit under today's cap — will pause when reached`);
      }
    }

    // Shared send handler so batching uses identical logic
    const sendToContact = async (contactData, globalIndex) => {
      // SOCK-LIVENESS CHECK — Baileys only. If the socket dropped
      // mid-campaign (network blip / pair refresh / forced logout) we
      // pause the remaining recipients instead of burning the list
      // firing into a dead pipe. Without this gate every call would
      // throw and mark the recipient `failed`, polluting analytics +
      // wasting the operator's volume budget on the now-recovered
      // session. Pause is recoverable — operator can hit Resume once
      // the device is back.
      if (!useWaba && !useTwilio && !isSockUsable(sock, app.locals, campaign.senderPhoneNumber)) {
        console.warn(`[CAMPAIGN] sock unusable mid-run for ${campaign.senderPhoneNumber} — pausing campaign ${campaign.campaignId}`);
        campaign.status = 'paused';
        campaign.lastError = 'Baileys connection dropped mid-run. Resume after reconnect.';
        await updateCampaignStatus(campaign.campaignId, 'paused', campaign.stats, appDomainName);
        throw new Error(SOCK_DROPPED_ERROR);
      }

      try {


        // FIXED: Use phone field like broadcast does
        if (!contactData.phone) {

          campaign.stats.failed++;
          await updateContactStatus(
            campaign.campaignId,
            contactData.id,
            "failed",
            "No phone number",
            appDomainName
          );
          return;
        }

        // FIXED: Use phone field directly (same as broadcast)
        const phoneNumber = contactData.phone;
        const finalNumber = formatPhoneNumber(phoneNumber);

        console.log(`[CAMPAIGN-NODE] sendToContact #${globalIndex} | campaign=${campaign.campaignId} contact=${contactData.id} name=${contactData.name || ''} phone=${finalNumber} type=${campaign.campaignType} useFb=${!!settings.use_facebook_api}`);


        // Determine variant for A/B testing
        let variant = null;
        let templateToUse = templateData;

        if (campaign.isABTest) {
          const isVariantA = campaign.contactsA.some(
            (c) => c.id === contactData.id
          );
          variant = isVariantA ? "A" : "B";
          templateToUse = isVariantA ? templateDataA : templateDataB;

        }

        let result = { success: false, messageId: null };

        // Send based on resolved engine. The row's `provider` already
        // decided useWaba/useTwilio above; an unset provider falls back
        // to the settings heuristic so single-engine sends are unchanged.
        if (useWaba) {
          result = await sendViaFacebookAPI(
            contactData,
            campaign,
            templateToUse,
            settings
          );
        } else if (useTwilio) {
          // Twilio: ContentSid path for templates (compliant for
          // marketing/utility/auth), plain Body for custom messages.
          // Previously a Twilio workspace fell through to the Baileys
          // branch below and crashed on `sock.sendMessage` (sock=null).
          if (campaign.campaignType === "template") {
            // Flatten variable_map (nested {body:[{num,key}]} OR flat
            // {slot:key}) so a positional {{1}} resolves to the mapped
            // attribute key, then pull from contact fields / custom attrs.
            const twilioFlatMap = normalizeVariableMap(templateToUse?.variable_map) || {};
            const renderedBody = (templateToUse?.template_body || "")
              .replace(/\{\{\s*([^\s{}]+?)\s*\}\}/g, (_m, k) => {
                const named = twilioFlatMap[k] || k;
                const ca = contactData.custom_attributes || {};
                return String(contactData[named] || ca[named] || ca[k] || "");
              });
            result = await sendTwilioBulkMessage(
              phoneNumber,
              templateToUse,
              contactData,
              renderedBody,
              settings,
              sendMessageViaTwilioApi
            );
          } else if (campaign.campaignType === "custom") {
            // Resolve {{name}}/{{promo_key}}/positional vars so attributes
            // personalize on Twilio exactly like Baileys/WABA — without this
            // the literal {{...}} tokens shipped to the customer.
            result = await sendMessageViaTwilioApi(
              phoneNumber,
              { type: "text", body: replaceAttributes(String(campaign.customMessage || ""), contactData) },
              settings
            );
          } else {
            // Flow nodes on Twilio: degrade to plain text — Twilio's
            // free-text path doesn't support interactive list/buttons
            // without a ContentSid, so flow.send-template is the only
            // Twilio-compliant flow path. Operators get a console warn
            // so they know flow on Twilio is degraded.
            console.warn(`[CAMPAIGN-TWILIO] flow type "${campaign.campaignType}" — Twilio cannot replay flow logic, sent as text snapshot.`);
            // Resolve attributes on the degraded snapshot too, using the
            // template's variable_map for positional {{1}} → named tokens.
            result = await sendMessageViaTwilioApi(
              phoneNumber,
              { type: "text", body: replaceAttributes(String(templateToUse?.template_body || ""), contactData, templateToUse?.variable_map) },
              settings
            );
          }
        } else {
          switch (campaign.campaignType) {
            case "custom":
              result = await sendCustomMessage(
                sock,
                finalNumber,
                campaign.customMessage,
                contactData,
                campaign.useAttributes,
                campaign.trackingEnabled ? appDomainName : null
              );
              break;

            case "template":
              result = await sendTemplateMessage(
                sock,
                finalNumber,
                templateToUse,
                contactData,
                campaign.useAttributes,
                campaign.trackingEnabled ? appDomainName : null
              );
              break;

            case "flow":
              result = await sendFlowMessage(
                sock,
                finalNumber,
                campaign.flowId,
                contactData,
                app,
                campaign.senderPhoneNumber
              );
              break;
          }
        }

        if (result.success) {
          console.log(`[CAMPAIGN-NODE] SENT OK | campaign=${campaign.campaignId} contact=${contactData.id} msgId=${result.messageId}`);
          campaign.stats.sent++;

          // Bump per-device daily tally on every Baileys success so the
          // soft cap above (4k/day default) accumulates across sequential
          // campaigns within the same calendar day. Only Baileys sends
          // count toward the unofficial-stack cap.
          if (!useWaba && !useTwilio) {
            bumpDailyTally(app.locals, campaign.senderPhoneNumber);
          }

          if (variant === "A") {
            campaign.stats.variantA.sent++;
          } else if (variant === "B") {
            campaign.stats.variantB.sent++;
          }


          campaign.sentMessages[result.messageId] = {
            contactId: contactData.id,
            contactName: contactData.name,
            phoneNumber: finalNumber,
            variant: variant,
            sentAt: new Date().toISOString(),
            failed: false,
          };

          await updateContactStatus(
            campaign.campaignId,
            contactData.id,
            "sent",
            null,
            appDomainName,
            result.messageId,
            variant,
            new Date().toISOString()
          );
        } else {
          console.warn(`[CAMPAIGN-NODE] SEND FAILED | campaign=${campaign.campaignId} contact=${contactData.id} err=${result.error || 'Failed to send'}`);
          campaign.stats.failed++;


          campaign.sentMessages[`failed_${contactData.id}`] = {
            contactId: contactData.id,
            contactName: contactData.name,
            phoneNumber: finalNumber,
            variant: variant,
            failed: true,
            error: result.error || "Failed to send",
          };

          await updateContactStatus(
            campaign.campaignId,
            contactData.id,
            "failed",
            result.error || "Failed to send",
            appDomainName,
            null,
            variant,
            null
          );
        }
      } catch (error) {
        // Re-throw the sock-drop sentinel so the outer loop bails out
        // cleanly without marking the rest of the recipients failed.
        if (isSockDropError(error)) throw error;

        campaign.stats.failed++;

        await updateContactStatus(
          campaign.campaignId,
          contactData.id,
          "failed",
          error.message,
          appDomainName
        );
      } finally {
        // Re-roll the gap on every iteration so the inter-message
        // timing varies per-send — fresh jittered value each pass.
        await new Promise((resolve) => setTimeout(resolve, getJitteredMessageDelay(app.locals)));
      }
    };

    // Process contacts with optional batching (same as broadcast).
    // Wrap the outer loop in a try/catch keyed on the __SOCK_DROPPED__
    // sentinel so a Baileys disconnect mid-batch unwinds cleanly
    // (campaign already marked paused inside sendToContact).
    try {
    if (
      batchSettings.enabled &&
      totalContacts > batchSettings.messagesPerBatch
    ) {
      const totalBatches = Math.ceil(
        totalContacts / batchSettings.messagesPerBatch
      );


      for (
        let batchStart = 0;
        batchStart < totalContacts;
        batchStart += batchSettings.messagesPerBatch
      ) {
        const batchNumber = Math.floor(
          batchStart / batchSettings.messagesPerBatch
        ) + 1;
        const batchEnd = Math.min(
          batchStart + batchSettings.messagesPerBatch,
          totalContacts
        );
        const batchContacts = campaign.targetContacts.slice(
          batchStart,
          batchEnd
        );


        for (let i = 0; i < batchContacts.length; i++) {
          await sendToContact(batchContacts[i], batchStart + i + 1);
        }

        if (batchEnd < totalContacts) {
          // Re-roll batch gap each cycle — same anti-fingerprint logic.
          await new Promise((resolve) => setTimeout(resolve, getJitteredBatchGapMs(app.locals)));
        }
      }
    } else {

      for (let i = 0; i < campaign.targetContacts.length; i++) {
        await sendToContact(campaign.targetContacts[i], i + 1);
      }
    }
    } catch (loopErr) {
      // Sock dropped mid-run — campaign already marked paused inside
      // sendToContact. Skip the "completed" finaliser below and exit.
      if (isSockDropError(loopErr)) {
        console.log(`[CAMPAIGN] paused after sock drop — campaign ${campaign.campaignId} sent=${campaign.stats.sent}/${totalContacts}`);
        return;
      }
      throw loopErr;
    }

    // Update final status
    if (campaign.isRecurring && campaign.recurringActive) {
      campaign.status = "active";

    } else {
      campaign.status = campaign.stats.failed === campaign.targetContacts.length ? 
        "failed" : "completed";
      campaign.completedAt = moment().format();
    }


    await updateCampaignStatus(
      campaign.campaignId,
      campaign.status,
      campaign.stats,
      appDomainName
    );


  } catch (error) {


    
    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === nodeScheduleId
    );
    
    if (msgIndex !== -1) {
      const campaign = app.locals.scheduledMessages[msgIndex];
      
      if (campaign.isRecurring && campaign.recurringActive) {
        campaign.status = "error";

      } else {
        campaign.status = "failed";
      }
      
      await updateCampaignStatus(
        campaign.campaignId,
        campaign.status,
        campaign.stats,
        appDomainName
      );
    }
  } finally {
    const campaign = app.locals.scheduledMessages.find(msg => msg.id === nodeScheduleId);
    
    if (campaign && !campaign.isRecurring) {
      if (app.locals.scheduledJobs[nodeScheduleId]) {
        app.locals.scheduledJobs[nodeScheduleId].stop();
        delete app.locals.scheduledJobs[nodeScheduleId];
      }
    }
  }
}

/**
 * Send message via Facebook WhatsApp Business API - FIXED VERSION
 */
async function sendViaFacebookAPI(contactData, campaign, templateData, settings) {
  try {
    // Extract phone number - digits only (no + prefix per official API spec)
    let phoneNumber;
    if (contactData.country_code && contactData.mobile) {
      phoneNumber = String(contactData.country_code).replace(/\D/g, '') + String(contactData.mobile).replace(/\D/g, '');
    } else if (contactData.phone) {
      phoneNumber = String(contactData.phone).replace(/\D/g, '');
    } else {
      throw new Error('No phone number found in contact data');
    }

    // Build only the message-type fields (helper adds messaging_product, recipient_type, to)
    let messageData = {};

    // Build message based on campaign type
    if (campaign.campaignType === 'custom') {
      // Custom message - Build with header, body, footer
      let messageText = "";

      if (campaign.customMessage.custom_header || campaign.customMessage.header) {
        let header = campaign.customMessage.custom_header || campaign.customMessage.header;
        if (campaign.useAttributes) {
          header = replaceAttributes(header, contactData);
        }
        messageText += `*${header}*\n\n`;
      }

      let bodyText = campaign.customMessage.message || campaign.customMessage.custom_message || "";
      if (campaign.useAttributes) {
        bodyText = replaceAttributes(bodyText, contactData);
      }
      messageText += bodyText;

      if (campaign.customMessage.custom_footer || campaign.customMessage.footer) {
        let footer = campaign.customMessage.custom_footer || campaign.customMessage.footer;
        if (campaign.useAttributes) {
          footer = replaceAttributes(footer, contactData);
        }
        messageText += `\n\n_${footer}_`;
      }

      messageData.type = "text";
      messageData.text = {
        preview_url: false,
        body: messageText
      };

      // Add buttons if present
      if (campaign.customMessage.custom_buttons) {
        const buttons = JSON.parse(campaign.customMessage.custom_buttons);
        if (buttons && buttons.length > 0) {
          delete messageData.text; // Remove orphan text property
          messageData.type = "interactive";
          messageData.interactive = {
            type: "button",
            body: { text: messageText },
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
        }
      }

    } else if (campaign.campaignType === 'template') {
       // 2026-05-24 update — PHP now pre-builds the full Meta payload
       // per recipient (with buttons / carousel / media / LinkTracker
       // URLs / auth OTP) and ships it under
       // `templateData.meta_payloads[contactId]`. Same fast-path the
       // broadcast service uses; same reason — the partial builder
       // below only handles header+body text params and silently
       // dropped every button/carousel/media template, which led to
       // Meta error 132000 in production.
       const prebuilt = templateData?.meta_payloads
         ? (templateData.meta_payloads[contactData.id] || templateData.meta_payloads[String(contactData.id)])
         : null;
       if (prebuilt && prebuilt.name) {
         console.log(`[CAMPAIGN-WABA] using PHP-prebuilt meta_payload for contact ${contactData.id} (${(prebuilt.components || []).length} components)`);
         messageData = {
           type: 'template',
           template: prebuilt,
         };
         return await sendMessageViaFacebookApi(phoneNumber, messageData, settings);
       }

       // === LEGACY PARTIAL BUILDER — kept for back-compat with
       // pre-2026-05-24 campaigns whose payload doesn't include
       // meta_payloads. Header+body text params only. Missing
       // buttons/carousel/media is a KNOWN limitation of this branch.
       const variableMap = templateData?.variable_map;

       messageData.type = "template";
       messageData.template = {
         name: templateData.template_name,
         language: {
           code: templateData.language || "en_US"
         },
         components: []
       };

       if (variableMap) {
         // Use stored variable_map for correct positional parameter mapping
         if (variableMap.header && variableMap.header.length > 0) {
           const headerParams = variableMap.header.map(v => ({
             type: "text",
             text: String(contactData[v.key] || contactData.custom_attributes?.[v.key] || "")
           }));
           messageData.template.components.push({ type: "header", parameters: headerParams });
         }

         if (variableMap.body && variableMap.body.length > 0) {
           const bodyParams = variableMap.body.map(v => {
             let value = contactData[v.key] || contactData.custom_attributes?.[v.key] || "";
             if (v.key === 'name' && !value) value = contactData.name || contactData.first_name || "Customer";
             return { type: "text", text: String(value) };
           });
           messageData.template.components.push({ type: "body", parameters: bodyParams });
         }
       } else {
         // Fallback: extract variables from template text using single-brace regex
         if (templateData.header) {
           const headerParams = [];
           const variableRegex = /\{\{?\s*([a-zA-Z0-9_]+)\s*\}?\}/g;
           let match;
           while ((match = variableRegex.exec(templateData.header)) !== null) {
             const varName = match[1];
             headerParams.push({
               type: "text",
               text: String(contactData[varName] || contactData.custom_attributes?.[varName] || "")
             });
           }
           if (headerParams.length > 0) {
             messageData.template.components.push({ type: "header", parameters: headerParams });
           }
         }

         if (templateData.template_body) {
           const bodyParams = [];
           const variableRegex = /\{\{?\s*([a-zA-Z0-9_]+)\s*\}?\}/g;
           let match;
           while ((match = variableRegex.exec(templateData.template_body)) !== null) {
             const varName = match[1];
             let value = contactData[varName] || contactData.custom_attributes?.[varName] || "";
             if (varName === 'name' && !value) value = contactData.name || contactData.first_name || "Customer";
             bodyParams.push({ type: "text", text: String(value) });
           }
           if (bodyParams.length > 0) {
             messageData.template.components.push({ type: "body", parameters: bodyParams });
           }
         }
       }

    } else if (campaign.campaignType === 'flow') {
      throw new Error('Flow messages are not supported via Facebook API');
    }

    // Use centralized helper for actual sending (it adds messaging_product, recipient_type, to)
    return await sendMessageViaFacebookApi(phoneNumber, messageData, settings);

  } catch (error) {

    
    if (error.response?.data?.error) {

    }

    return {
      success: false,
      messageId: null,
      error: formatMetaError(error),   // Meta's REAL error words + code + trace
    };
  }
}

/**
 * Send custom message via Baileys - FIXED VERSION (matches broadcast)
 */
async function sendCustomMessage(sock, phoneNumber, messageData, contactData, useAttributes, appDomainName = null) {
  try {

    
    // Build complete message text with header, body, footer
    let messageText = "";
    
    // Add header if present
    if (messageData.custom_header || messageData.header) {
      let header = messageData.custom_header || messageData.header;
      if (useAttributes) {
        header = replaceAttributes(header, contactData);
      }
      messageText += `*${header}*\n\n`;

    }
    
    // Add body (main message)
    let bodyText = messageData.message || messageData.custom_message || "";
    if (useAttributes) {
      bodyText = replaceAttributes(bodyText, contactData);
    }
    messageText += bodyText;
    
    // Add footer if present
    if (messageData.custom_footer || messageData.footer) {
      let footer = messageData.custom_footer || messageData.footer;
      if (useAttributes) {
        footer = replaceAttributes(footer, contactData);
      }
      messageText += `\n\n_${footer}_`;

    }

    // Apply click tracking URL rewriting if tracking enabled
    if (appDomainName && contactData.tracking_id) {
      messageText = rewriteUrlsForTracking(messageText, contactData.tracking_id, appDomainName);
    }

    const message = { text: messageText };

    // Add media if present (media comes FIRST with body as caption)
    if (messageData.custom_image) {
      try {
        const mediaBuffer = await downloadAndPrepareMediaBaileys(messageData.custom_image);
        if (mediaBuffer && mediaBuffer.buffer) {
          message.image = mediaBuffer.buffer;
          // For images: header + body + footer becomes the caption
          message.caption = messageText;
          delete message.text;

        }
      } catch (mediaError) {
        console.error(`[CAMPAIGN] image media download failed url=${messageData.custom_image}: ${mediaError?.message}`);
      }
    } else if (messageData.custom_video) {
      try {
        const mediaBuffer = await downloadAndPrepareMediaBaileys(messageData.custom_video);
        if (mediaBuffer && mediaBuffer.buffer) {
          message.video = mediaBuffer.buffer;
          // For videos: header + body + footer becomes the caption
          message.caption = messageText;
          delete message.text;

        }
      } catch (mediaError) {
        console.error(`[CAMPAIGN] video media download failed url=${messageData.custom_video}: ${mediaError?.message}`);
      }
    } else if (messageData.custom_document) {
      try {
        const mediaBuffer = await downloadAndPrepareMediaBaileys(messageData.custom_document);
        if (mediaBuffer && mediaBuffer.buffer) {
          message.document = mediaBuffer.buffer;
          message.fileName = messageData.custom_document_name || "document.pdf";

        }
      } catch (mediaError) {
        console.error(`[CAMPAIGN] document media download failed url=${messageData.custom_document}: ${mediaError?.message}`);
      }
    }

    // Add buttons if present
    if (messageData.custom_buttons) {
      try {
        const buttons = JSON.parse(messageData.custom_buttons);
        if (buttons && buttons.length > 0) {
          // Pass tracking info to button formatter
          message.interactiveButtons = formatInteractiveButtonsForBaileys(
            buttons, 
            contactData.tracking_id, 
            appDomainName
          );
        }
      } catch (buttonError) {
        console.error('Error parsing custom buttons:', buttonError);
      }
    }

    const result = await sock.sendMessage(phoneNumber, message);


    return { success: true, messageId: result.key.id };

  } catch (error) {


    return { success: false, error: error.message };
  }
}

/**
 * Send a LOCATION pin after the template body (Unofficial API has no template
 * location header). Maps Meta-style {latitude, longitude, name, address} to
 * Baileys' degrees* fields.
 */
async function sendCampaignLocationPin(sock, phoneNumber, location) {
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
    console.log(`[CAMPAIGN-NODE] location pin sent (${lat}, ${lng}) → ${phoneNumber}`);
  } catch (e) {
    console.error(`[CAMPAIGN-NODE] location pin send failed: ${e?.message}`);
  }
}

/**
 * Wrapper: send the template body, then ship the location pin on success so
 * every send branch is covered from one place.
 */
async function sendTemplateMessage(sock, phoneNumber, templateData, contactData, useAttributes, appDomainName = null) {
  const result = await sendTemplateMessageCore(sock, phoneNumber, templateData, contactData, useAttributes, appDomainName);
  if (result && result.success && templateData && templateData.location) {
    await sendCampaignLocationPin(sock, phoneNumber, templateData.location);
  }
  return result;
}

/**
 * Send template message via Baileys - FIXED VERSION (matches broadcast)
 */
async function sendTemplateMessageCore(sock, phoneNumber, templateData, contactData, useAttributes, appDomainName = null) {
  try {

    
    // Extract template data
    const {
      template_type,
      template_body,
      header,
      footer,
      buttons,
      attachment_type,
      attachment_file,
      carousel_data,
      variable_map,
    } = templateData;
    // Local alias so the per-pass calls below stay readable.
    const vmap = variable_map || null;


    // HANDLE CAROUSEL TEMPLATE
    if (template_type === 'carousel' && carousel_data) {

      
      const carouselCards = typeof carousel_data === 'string' 
        ? JSON.parse(carousel_data) 
        : carousel_data;

      const processedCards = [];

      for (const [index, card] of carouselCards.entries()) {
        const processedCard = {
          title: useAttributes ? replaceAttributes(card.title, contactData, vmap) : card.title,
          body: useAttributes ? replaceAttributes(card.body, contactData, vmap) : card.body,
          buttons: card.buttons || []
        };

        if (card.footer) {
          processedCard.footer = useAttributes ? replaceAttributes(card.footer, contactData, vmap) : card.footer;
        }

        if (card.image_path) {
          processedCard.image = card.image_path;
        } else if (card.image_filename) {
          processedCard.image = `${process.env.APP_DOMAIN_NAME}/uploads/templates/carousel/${card.image_filename}`;
        } else if (card.image) {
          if (card.image.startsWith('http')) {
            processedCard.image = card.image;
          } else {
            processedCard.image = `${process.env.APP_DOMAIN_NAME}/uploads/templates/carousel/${card.image}`;
          }
        }

        processedCards.push(processedCard);
      }

      let carouselText = useAttributes ? replaceAttributes(template_body || '', contactData, vmap) : (template_body || '');

      // Apply click tracking URL rewriting for carousel
      if (appDomainName && contactData.tracking_id) {
        carouselText = rewriteUrlsForTracking(carouselText, contactData.tracking_id, appDomainName);
      }

      const carouselContent = {
        text: carouselText,
        title: useAttributes ? replaceAttributes(header || '', contactData, vmap) : (header || ''),
        footer: useAttributes ? replaceAttributes(footer || '', contactData, vmap) : (footer || ''),
        cards: processedCards
      };

      const result = await sendCarouselMessage(sock, phoneNumber, carouselContent);
      
      if (!result.success) {
        throw new Error(result.error || 'Failed to send carousel');
      }

      return { success: true, messageId: result.messageId };
    }

    // HANDLE STANDARD TEMPLATE (non-carousel)
    let messageText = "";

    // Header
    if (header) {
      let headerText = header;
      if (useAttributes) {
        headerText = replaceAttributes(headerText, contactData, vmap);
      }
      messageText += `*${headerText}*\n\n`;
    }

    // Body
    if (template_body) {
      let body = template_body;
      if (useAttributes) {
        body = replaceAttributes(body, contactData, vmap);
      }
      messageText += body;
    }

    // Footer
    if (footer) {
      let footerText = footer;
      if (useAttributes) {
        footerText = replaceAttributes(footerText, contactData, vmap);
      }
      messageText += `\n\n_${footerText}_`;
    }

    // Apply click tracking URL rewriting if tracking enabled
    if (appDomainName && contactData.tracking_id) {
      messageText = rewriteUrlsForTracking(messageText, contactData.tracking_id, appDomainName);
    }

    const message = { text: messageText };

    // Handle attachment.
    //
    // PREFER the PHP-inlined base64 (templates-camp route now ships
    // `attachment_base64` + `attachment_mime`) — no network, so the image
    // CANNOT be dropped just because Node can't reach the storage URL. Fall
    // back to downloading from attachment_url only when base64 is absent.
    if (attachment_type && attachment_file) {
      let mediaBuffer = null;
      try {
        if (templateData.attachment_base64) {
          mediaBuffer = {
            buffer: Buffer.from(templateData.attachment_base64, 'base64'),
            mimetype: templateData.attachment_mime || undefined,
          };
        } else {
          // Fall back to /storage/wa-templates/ (NOT the legacy
          // /uploads/templates/attachments/ path, which doesn't exist here).
          const mediaUrl = templateData.attachment_url
            || `${process.env.APP_DOMAIN_NAME}/storage/wa-templates/${attachment_file}`;
          mediaBuffer = await downloadAndPrepareMediaBaileys(mediaUrl);
        }
      } catch (mediaError) {
        console.error(`[CAMPAIGN] attachment prepare failed type=${attachment_type} file=${attachment_file}: ${mediaError?.message}`);
        mediaBuffer = null;
      }

      if (mediaBuffer && mediaBuffer.buffer && mediaBuffer.buffer.length > 0) {
        if (attachment_type === 'image') {
          message.image = mediaBuffer.buffer;
          message.caption = messageText;
          delete message.text;

        } else if (attachment_type === 'video') {
          message.video = mediaBuffer.buffer;
          message.caption = messageText;
          delete message.text;

        } else if (attachment_type === 'document') {
          message.document = mediaBuffer.buffer;
          message.fileName = attachment_file;
          message.mimetype = mediaBuffer.mimetype;

        }
      } else {
        // A media attachment WAS configured but we could not produce bytes
        // (base64 missing AND download failed/empty). Do NOT silently send
        // text-only as if nothing was attached — fail loudly so the drop is
        // visible and the recipient row is marked failed, not falsely "sent".
        console.error(`[ATTACHMENT-MISSING] [CAMPAIGN] configured attachment could NOT be sent — refusing to send text-only. type=${attachment_type} file=${attachment_file} hadBase64=${!!templateData.attachment_base64} url=${templateData.attachment_url || ''}`);
        return { success: false, error: `attachment-missing:${attachment_type}:${attachment_file}` };
      }
    }

    // Handle buttons
    if (buttons) {
      try {
        const parsedButtons = typeof buttons === 'string' ? 
          JSON.parse(buttons) : buttons;
        
        if (parsedButtons && parsedButtons.length > 0) {
          // Pass tracking info to button formatter
          message.interactiveButtons = formatInteractiveButtonsForBaileys(
            parsedButtons,
            contactData.tracking_id,
            appDomainName
          );
        }
      } catch (buttonError) {
        console.error('Error parsing template buttons:', buttonError);
      }
    }

    const result = await sock.sendMessage(phoneNumber, message);


    return { success: true, messageId: result.key.id };

  } catch (error) {


    return { success: false, error: error.message };
  }
}

/**
 * Send flow message (trigger flow)
 */
async function sendFlowMessage(sock, phoneNumber, flowId, contactData, app, senderPhoneNumber) {
  try {

    
    const flowResponse = await axios.get(
      `${process.env.APP_DOMAIN_NAME}/api/flows/${flowId}`
    );

    if (!flowResponse.data.success) {
      throw new Error('Flow not found');
    }

    const flowData = flowResponse.data.data.flow_data;
    const userNumber = phoneNumber.replace('@s.whatsapp.net', '');
    const sessionKey = `${senderPhoneNumber}_${userNumber}`;

    // Seed the session with the contact's attributes so {{name}}/{{phone}}/
    // {{email}}/custom attributes resolve inside flow nodes — without this the
    // session started with userVariables:{} and every merge tag rendered blank.
    // Workspace attributes ({{promo_key}}, default {{order_id}}, …) are merged
    // UNDER the contact attrs (contact wins on collision). workspace_id rides
    // on flow_data from /api/flows/:id (nodeShow). Best-effort + cached.
    const workspaceAttrs = await fetchWorkspaceAttributes(
      process.env.APP_DOMAIN_NAME,
      flowData?.workspace_id
    );
    app.locals.activeFlowSessions[sessionKey] = {
      sessionId: `${sessionKey}_${Date.now()}`,
      flowId: flowId,
      flowData: flowData,
      currentNodeId: null,
      userVariables: mergeFlowVariables(
        workspaceAttrs,
        seedFlowUserVariables(contactData, userNumber)
      ),
      messageHistory: [],
      status: "active",
      startedAt: moment().format(),
      phoneNumber: senderPhoneNumber,
    };

    const startNode = flowData.flowNodes[0];
    await executeFlowNode(
      startNode,
      userNumber,
      senderPhoneNumber,
      sock,
      app.locals,
      sessionKey
    );

    return { success: true, messageId: `flow_${Date.now()}` };

  } catch (error) {

    return { success: false, error: error.message };
  }
}

/**
 * Build the initial userVariables map for a flow session from a contact.
 *
 * Flow nodes resolve merge tags ({{name}}, {{phone}}, {{email}}, custom
 * attributes, …) against session.userVariables. Sessions used to start
 * with {} so every tag rendered blank. Seed the same keys replaceAttributes
 * exposes — name/first_name/last_name/mobile/phone/email/address/title/
 * language — plus any custom_attributes, so the very first flow node already
 * personalizes. `fallbackPhone` backfills phone when the contact row had none.
 *
 * Workspace-level attributes (e.g. {{promo_key}}, {{order_id}} defaults) are
 * NOT contact-scoped, so they aren't seeded here — they're fetched separately
 * via fetchWorkspaceAttributes() and merged UNDER these contact attrs (contact
 * wins on key collision) by the flow-start callers.
 */
export function seedFlowUserVariables(contactData, fallbackPhone = "") {
  const cd = contactData && typeof contactData === "object" ? contactData : {};
  const vars = {
    name:       cd.name || cd.first_name || "",
    first_name: cd.first_name || cd.name || "",
    last_name:  cd.last_name || "",
    mobile:     cd.phone || cd.mobile || fallbackPhone || "",
    phone:      cd.phone || cd.mobile || fallbackPhone || "",
    email:      cd.email || "",
    address:    cd.address || "",
    title:      cd.title || "",
    language:   cd.language || "",
  };

  let customAttrs = cd.custom_attributes;
  if (typeof customAttrs === "string") {
    try { customAttrs = JSON.parse(customAttrs); } catch (e) { customAttrs = null; }
  }
  if (customAttrs && typeof customAttrs === "object") {
    for (const [k, v] of Object.entries(customAttrs)) {
      if (vars[k] === undefined || vars[k] === "") vars[k] = String(v ?? "");
    }
  }

  return vars;
}

// Short-TTL per-workspace cache for WORKSPACE attributes so every flow
// start doesn't re-hit Laravel. Workspace attrs (promo codes, default
// order ids, …) change rarely; a 60s window is plenty fresh.
const WORKSPACE_ATTR_TTL_MS = 60 * 1000;
const _workspaceAttrCache = new Map(); // workspaceId -> { at, attrs }

/**
 * Fetch a workspace's saved attributes from Laravel
 * (GET /api/workspace-attributes/:workspaceId, X-Node-Token gated) and
 * return them as a flat { key: value } object. Cached per workspace for
 * WORKSPACE_ATTR_TTL_MS. Never throws — on any failure it returns {} so a
 * flow still starts (workspace tags just render literal, same as before).
 */
export async function fetchWorkspaceAttributes(appDomainName, workspaceId) {
  const wsId = String(workspaceId || "").trim();
  if (!wsId || wsId === "0" || !appDomainName) return {};

  const cached = _workspaceAttrCache.get(wsId);
  if (cached && Date.now() - cached.at < WORKSPACE_ATTR_TTL_MS) {
    return cached.attrs;
  }

  try {
    const url = `${appDomainName}/api/workspace-attributes/${encodeURIComponent(wsId)}`;
    const resp = await axios.get(url, { headers: laravelHeaders(), timeout: 8000 });
    const attrs = (resp.data && resp.data.ok && resp.data.attributes && typeof resp.data.attributes === "object")
      ? resp.data.attributes
      : {};
    _workspaceAttrCache.set(wsId, { at: Date.now(), attrs });
    return attrs;
  } catch (e) {
    // Cache the empty result briefly too so a flapping Laravel doesn't get
    // hammered on every flow start; the next TTL window will retry.
    _workspaceAttrCache.set(wsId, { at: Date.now(), attrs: {} });
    return {};
  }
}

/**
 * Merge workspace defaults UNDER contact attrs (contact wins on key
 * collision) — the canonical precedence used at every flow start.
 */
export function mergeFlowVariables(workspaceAttrs, contactVars) {
  const ws = workspaceAttrs && typeof workspaceAttrs === "object" ? workspaceAttrs : {};
  const cv = contactVars && typeof contactVars === "object" ? contactVars : {};
  const merged = {};
  for (const [k, v] of Object.entries(ws)) merged[k] = String(v ?? "");
  // Contact attrs override workspace defaults; never let a blank contact
  // value clobber a real workspace default.
  for (const [k, v] of Object.entries(cv)) {
    if (v === "" && merged[k] !== undefined && merged[k] !== "") continue;
    merged[k] = v;
  }
  return merged;
}

/**
 * Normalize a template variable_map into the FLAT {slot:key} shape the
 * positional→named text rewrite needs. Accepts BOTH shapes:
 *   - flat          {"1":"name","2":"promo_key"}            (passes through)
 *   - nested/stored {header:[{num,key}], body:[{num,key}]}  (Laravel ships this)
 * Mirrors App\Services\AttributeResolver::normalizeVariableMap (PHP). On a
 * header/body slot-number collision, body wins (resolved last). Returns null
 * when the input isn't a usable object.
 */
function normalizeVariableMap(map) {
  if (!map || typeof map !== 'object' || Array.isArray(map)) return null;
  // Already flat — no header/body sections.
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

/**
 * Replace attributes in text. Two-stage:
 *   1. Positional → named via templateData.variable_map (e.g. `{{1}}` →
 *      `{{name}}`) so Meta templates that use positional placeholders
 *      still resolve to the right contact field.
 *   2. Named → contact attribute value. Falls back to literal so QA can
 *      spot unresolved keys.
 */
function replaceAttributes(text, contactData, variableMap) {
  if (!text) return text;

  // Stage 1 — positional rewrite. variable_map may arrive in EITHER the
  // flat shape {"1":"name","2":"promo_key"} OR the nested stored shape
  // {header:[{num,key}], body:[{num,key}]} that Laravel ships verbatim.
  // normalizeVariableMap flattens nested→{slot:key}; flat passes through.
  let result = String(text);
  const flatMap = normalizeVariableMap(variableMap);
  if (flatMap) {
    for (const [slot, key] of Object.entries(flatMap)) {
      if (!key) continue;
      const rx = new RegExp('\\{+\\s*' + String(slot).replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&') + '\\s*\\}+', 'g');
      result = result.replace(rx, '{{' + key + '}}');
    }
  }

  const replacements = {
    name: contactData.name || contactData.first_name || "",
    first_name: contactData.first_name || contactData.name || "",
    last_name: contactData.last_name || "",
    mobile: contactData.phone || contactData.mobile || "",
    phone: contactData.phone || contactData.mobile || "",
    email: contactData.email || "",
    address: contactData.address || "",
    title: contactData.title || "",
    language: contactData.language || "",
  };

  // Merge custom attributes if they exist
  if (contactData.custom_attributes) {
    let customAttrs = contactData.custom_attributes;
    if (typeof customAttrs === 'string') {
        try { customAttrs = JSON.parse(customAttrs); } catch(e) {}
    }
    if (typeof customAttrs === 'object' && customAttrs !== null) {
        Object.assign(replacements, customAttrs);
    }
  }

  let replacedCount = 0;

  for (const [key, value] of Object.entries(replacements)) {
    // Regex matches {key} or {{key}} or {{{key}}}. `key` can be an arbitrary
    // contact custom_attributes JSON key (finding #46), so escape regex
    // metacharacters — an unescaped `[` / `(` throws an uncaught SyntaxError
    // and `(a+)+` causes ReDoS, either of which aborts/hangs the whole send
    // loop. Mirrors the `slot` escaping above. Wrapped in try/catch as a
    // belt-and-braces guard so one bad key can never break personalization.
    const safeKey = String(key).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    let regex;
    try {
        regex = new RegExp(`\\{+\\s*${safeKey}\\s*\\}+`, "gi");
    } catch (e) {
        continue;
    }

    // Ensure value is a string, handle nulls/undefined
    const replacementValue = (value === null || value === undefined) ? "" : String(value);

    if (regex.test(result)) {
        result = result.replace(regex, replacementValue);
        replacedCount++;
    }
  }

  if (replacedCount === 0 && text.includes('{')) {
    console.log(`[Placeholder] No matches found in text: "${text.substring(0, 50)}..."`);
    console.log(`[Placeholder] Available keys: ${Object.keys(replacements).join(', ')}`);
  }

  return result;
}

/**
 * Fetch template data from Laravel
 */
async function fetchTemplateData(templateId, appDomainName) {
  try {
    const response = await axios.get(
      `${appDomainName}/api/templates-camp/${templateId}`,
      // /api/templates-camp/{id} is X-Node-Token gated (routes/api.php) — without
      // the header Laravel returns 401 and the template content comes back empty.
      { headers: laravelHeaders() }
    );
    const templateData = response.data.template || response.data;

    
    return templateData;
  } catch (error) {

    return null;
  }
}

/**
 * Update campaign status in Laravel
 */
async function updateCampaignStatus(campaignId, status, stats, appDomainName) {
  try {
    console.log(`[CAMPAIGN-NODE] -> POST update-status | campaign=${campaignId} status=${status} total=${stats.total} sent=${stats.sent} failed=${stats.failed} delivered=${stats.delivered} read=${stats.read} responded=${stats.responded}`);

    await axios.post(`${appDomainName}/api/campaigns/update-status`, {
      campaign_id: campaignId,
      status: status,
      total_recipients: stats.total,
      sent_count: stats.sent,
      failed_count: stats.failed,
      delivered_count: stats.delivered,
      read_count: stats.read,
      responded_count: stats.responded,
      clicked_count: stats.clicked,
      variant_a_stats: stats.variantA,
      variant_b_stats: stats.variantB,
    }, { headers: laravelHeaders() });

  } catch (error) {
    console.error(`[CAMPAIGN-NODE] update-status FAILED campaign=${campaignId}: ${error?.response?.status || ''} ${error?.message}`);
  }
}

/**
 * Update individual contact status in Laravel
 */
async function updateContactStatus(
  campaignId, 
  contactId, 
  status, 
  errorMessage, 
  appDomainName, 
  whatsappMessageId = null, 
  variant = null,
  timestamp = null
) {
  try {
    const payload = {
      campaign_id: campaignId,
      contact_id: contactId,
      status: status,
      whatsapp_message_id: whatsappMessageId,
      variant: variant
    };
    
    if (errorMessage) {
      payload.error_message = errorMessage;
    }
    
    if (timestamp) {
      if (status === 'sent') {
        payload.sent_at = timestamp;
      } else if (status === 'delivered') {
        payload.delivered_at = timestamp;
      } else if (status === 'read') {
        payload.read_at = timestamp;
      }
    }

    
    console.log(`[CAMPAIGN-NODE] -> POST update-contact-status | campaign=${campaignId} contact=${contactId} status=${status}${whatsappMessageId ? ' msgId=' + whatsappMessageId : ''}${errorMessage ? ' err=' + errorMessage : ''}`);
    await axios.post(
      `${appDomainName}/api/campaigns/update-contact-status`,
      payload,
      { headers: laravelHeaders() }
    );

  } catch (error) {
    console.error(`[CAMPAIGN-NODE] update-contact-status FAILED campaign=${campaignId} contact=${contactId}: ${error?.response?.status || ''} ${error?.message}`);
  }
}

/**
 * Handle message status updates (delivery, read receipts)
 */
/**
 * Handle message status updates (delivery, read receipts)
 */
export async function handleCampaignMessageUpdate(messageUpdate, appLocals, appDomainName) {
  for (const update of messageUpdate) {
    try {
      const messageId = update.key.id;
      const status = update.update.status;

      if (!status || !messageId) continue;

      let statusName;
      const timestamp = new Date().toISOString();
      
      // Baileys WAProto WebMessageInfo.Status (node_modules/baileys/WAProto/index.d.ts):
      //   ERROR=0  PENDING=1  SERVER_ACK=2  DELIVERY_ACK=3  READ=4  PLAYED=5
      //
      // This block previously read `2 = delivered, 3 = read`, which is off by
      // one against that enum: 2 is SERVER_ACK (WhatsApp's server accepted it —
      // the handset may not have it yet) and 3 is DELIVERY_ACK. The real READ
      // (4) fell into `else { continue }` and was dropped.
      //
      // The effect was NOT a missing metric — it was a WRONG one: every
      // campaign recipient was marked "read" the moment the message reached
      // their device, so read counts were inflated and a genuine blue-tick read
      // never registered. Nothing looked broken, which is why it survived.
      //
      // BaileysClientManager.js (the broadcast handler) already had this right;
      // the two disagreed on the same event. Now aligned.
      if (status === 3 || status === "delivery" || status === "delivered") {
         statusName = "delivered";
      } else if (status === 4 || status === "read") {
         statusName = "read";
      } else if (status === 5 || status === "played") {
         // PLAYED (voice note listened to) implies read.
         statusName = "read";
      } else {
        // 0/1/2 — error, pending, server-ack. Not a recipient-side event.
        continue;
      }


      // 1. Try Memory Lookup
      let campaign, messageInfo;
      if (appLocals && appLocals.scheduledMessages) {
        for (const scheduledMsg of appLocals.scheduledMessages) {
            if (scheduledMsg.type === 'campaign' && scheduledMsg.sentMessages[messageId]) {
            campaign = scheduledMsg;
            messageInfo = scheduledMsg.sentMessages[messageId];
            break;
            }
        }
      }

      // 2. If Found in Memory, Use specialized update (increments detailed stats)
      if (campaign && messageInfo) {
          
          if (statusName === "delivered") {
            // Memory Update - ONLY if not already delivered/read
            if (!campaign.deliveryStatus[messageInfo.contactId]) {
                campaign.stats.delivered++;
                campaign.deliveryStatus[messageInfo.contactId] = { delivered: true, deliveredAt: timestamp };
                
                if (messageInfo.variant === 'A') campaign.stats.variantA.delivered++;
                else if (messageInfo.variant === 'B') campaign.stats.variantB.delivered++;
                
                // DB Sync
                await updateContactStatus(campaign.campaignId, messageInfo.contactId, statusName, null, appDomainName, null, null, timestamp);
            }
          
          } else if (statusName === "read") {
             // Memory Update - ONLY if not already read
             if (!campaign.readStatus[messageInfo.contactId]) {
                 campaign.stats.read++;
                 campaign.readStatus[messageInfo.contactId] = { read: true, readAt: timestamp };
                 
                 if (messageInfo.variant === 'A') campaign.stats.variantA.read++;
                 else if (messageInfo.variant === 'B') campaign.stats.variantB.read++;
                 
                 // DB Sync
                 await updateContactStatus(campaign.campaignId, messageInfo.contactId, statusName, null, appDomainName, null, null, timestamp);
             }
          }

          // Always update overall campaign status to keep totals in sync (absolute numbers)
          await updateCampaignStatus(campaign.campaignId, campaign.status, campaign.stats, appDomainName);
      
      } else {
          // 3. Fallback: Update by Message ID directly (Handles restarts/missing memory)

          try {
             await axios.post(`${appDomainName}/api/campaigns/update-status-by-id`, {
                 message_id: messageId,
                 status: statusName
             }, { headers: laravelHeaders() });
          } catch (apiError) {
            console.error(`[CAMPAIGN-STATUS] update-status-by-id failed msg=${messageId} status=${statusName}: ${apiError?.response?.status || ''} ${apiError?.message}`);
          }
      }

    } catch (error) {
      console.error(`[CAMPAIGN-STATUS] outer handler failed: ${error?.message}`, {
        stack: error?.stack?.split('\n').slice(0, 3).join(' | '),
      });
    }
  }
}

/**
 * Track user response to campaign message
 */
export async function trackCampaignResponse(userMessage, appLocals, appDomainName) {
  try {
    if (!appLocals || !appLocals.scheduledMessages) {
      return;
    }

    const userNumber = userMessage.key.remoteJid.replace('@s.whatsapp.net', '');
    const messageText = (userMessage.message?.conversation || 
                       userMessage.message?.extendedTextMessage?.text || '').trim();

    const isUnsubscribe = messageText.toLowerCase() === 'unsubscribe';

    for (const campaign of appLocals.scheduledMessages) {
      if (campaign.type !== 'campaign') continue;

      const contact = campaign.targetContacts.find(c => {
        const contactPhone = c.phone.replace(/[^0-9]/g, '');
        return userNumber.includes(contactPhone);
      });

      if (contact && campaign.sentMessages) {
        // If Unsubscribe
        if (isUnsubscribe) {
            console.log(`[Unsubscribe] User ${userNumber} unsubscribed from campaign ${campaign.campaignId}`);
            
            // Send to Laravel
             await axios.post(`${appDomainName}/api/campaigns/unsubscribe`, {
                campaign_id: campaign.campaignId,
                phone: userNumber,
             }, { headers: laravelHeaders() });
            
             // Update memory
             // Remove from future processing if needed, but usually this is post-send.
             // We just record the status.
             
        } else if (!campaign.responseTracking[contact.id]) {
           // Standard Response Tracking
          campaign.stats.responded++;
          const timestamp = new Date().toISOString();
          
          campaign.responseTracking[contact.id] = {
            responded: true,
            respondedAt: timestamp,
            response: messageText
          };

          const messageEntry = Object.values(campaign.sentMessages).find(
            m => m.contactId === contact.id
          );
          
          if (messageEntry && messageEntry.variant === 'A') {
            campaign.stats.variantA.responded++;
          } else if (messageEntry && messageEntry.variant === 'B') {
            campaign.stats.variantB.responded++;
          }

          await axios.post(`${appDomainName}/api/campaigns/track-response`, {
            campaign_id: campaign.campaignId,
            contact_id: contact.id,
            response: messageText,
            responded_at: timestamp,
          }, { headers: laravelHeaders() });

          await updateCampaignStatus(
            campaign.campaignId,
            campaign.status,
            campaign.stats,
            appDomainName
          );
        }
      }
    }
  } catch (error) {
    console.error('Error tracking campaign response:', error.message);
  }
}
