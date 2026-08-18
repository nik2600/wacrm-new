// controllers/scheduleController.js
// ==============================
// Schedule Controller
// ==============================

import cron from "node-cron";
import moment from "moment-timezone";
import axios from "axios";
import { executeBulkScheduledMessage, executeRecurringSchedule } from "../services/scheduleService.js";
import {
  formatPhoneNumber,
  logLaravelError,
  // Multi-engine: a single scheduled message pinned to WABA/Twilio must route
  // through these helpers instead of the Baileys sock (which doesn't exist on
  // those workspaces). Mirrors the bulk scheduler's engine branches.
  getWhatsAppSettings,
  sendMessageViaFacebookApi,
  sendMessageViaTwilioApi,
} from "../utils/helpers.js";

// Pause schedule
export function pauseSchedule(req, res, app) {
  const { scheduleId } = req.params;

  try {


    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === scheduleId
    );

    if (msgIndex === -1) {
      return res.status(404).json({ error: "Schedule not found" });
    }

    app.locals.scheduledMessages[msgIndex].status = "paused";

    if (app.locals.scheduledJobs[scheduleId]) {
      app.locals.scheduledJobs[scheduleId].stop();
    }

    res.json({ success: true, message: "Schedule paused" });
  } catch (error) {

    res.status(500).json({ error: "Failed to pause schedule" });
  }
}

// Resume schedule
export function resumeSchedule(req, res, app) {
  const { scheduleId } = req.params;

  try {


    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === scheduleId
    );

    if (msgIndex === -1) {
      return res.status(404).json({ error: "Schedule not found" });
    }

    const schedule = app.locals.scheduledMessages[msgIndex];
    schedule.status = "running";

    if (app.locals.scheduledJobs[scheduleId]) {
      app.locals.scheduledJobs[scheduleId].start();
    }

    res.json({ success: true, message: "Schedule resumed" });
  } catch (error) {

    res.status(500).json({ error: "Failed to resume schedule" });
  }
}

// Cancel schedule
export function cancelSchedule(req, res, app) {
  const { scheduleId } = req.params;

  try {


    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === scheduleId
    );

    if (msgIndex === -1) {
      return res.status(404).json({ error: "Schedule not found" });
    }

    app.locals.scheduledMessages[msgIndex].status = "cancelled";

    if (app.locals.scheduledJobs[scheduleId]) {
      app.locals.scheduledJobs[scheduleId].stop();
      delete app.locals.scheduledJobs[scheduleId];
    }

    // Remove from scheduled messages array
    app.locals.scheduledMessages.splice(msgIndex, 1);

    res.json({ success: true, message: "Schedule cancelled" });
  } catch (error) {

    res.status(500).json({ error: "Failed to cancel schedule" });
  }
}

// Update schedule
export function updateSchedule(req, res, app) {
  const { scheduleId } = req.params;
  const { scheduleDateTime, timezone, message } = req.body;

  try {


    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === scheduleId
    );

    if (msgIndex === -1) {
      return res.status(404).json({ error: "Schedule not found" });
    }

    const schedule = app.locals.scheduledMessages[msgIndex];

    // Update schedule details
    if (scheduleDateTime) {
      schedule.scheduleDateTime = scheduleDateTime;
    }
    if (timezone) {
      schedule.timezone = timezone;
    }
    if (message) {
      schedule.message = message;
    }

    // Stop existing job
    if (app.locals.scheduledJobs[scheduleId]) {
      app.locals.scheduledJobs[scheduleId].stop();
      delete app.locals.scheduledJobs[scheduleId];
    }

    // Reschedule with new time
    const scheduledMoment = moment.tz(schedule.scheduleDateTime, schedule.timezone);
    const cronExpression = `${scheduledMoment.minutes()} ${scheduledMoment.hours()} ${scheduledMoment.date()} ${scheduledMoment.month() + 1} *`;

    const job = cron.schedule(
      cronExpression,
      () => {
        if (schedule.type === "bulk") {
          executeBulkScheduledMessage(scheduleId, app, process.env.APP_DOMAIN_NAME || "http://localhost:8000");
        } else if (schedule.type === "recurring") {
          executeRecurringSchedule(scheduleId, app, process.env.APP_DOMAIN_NAME || "http://localhost:8000");
        }
      },
      {
        timezone: schedule.timezone,
      }
    );

    app.locals.scheduledJobs[scheduleId] = job;

    res.json({ success: true, message: "Schedule updated", scheduleId });
  } catch (error) {

    res.status(500).json({ error: "Failed to update schedule" });
  }
}

export function scheduleBulkMessage(req, res, app) {
  const { phoneNumber } = req.params;
  const {
    scheduleId,
    provider,
    targetPhoneNumbers,
    message,
    scheduleDateTime,
    timezone,
    messageType,
    mediaUrl,
    latitude,
    longitude,
    isTemplate,
    templateData,
    recipientAttributes,
  } = req.body;

  try {








    const nodeScheduleId = `bulk_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

    // ✅ FIX: Parse the datetime in the user's timezone WITHOUT conversion
    // If user says "2:33 PM Asia/Kolkata", we want 2:33 PM in Kolkata, not converted
    const scheduledMoment = moment.tz(scheduleDateTime, timezone || "UTC");
    
    console.log("✅ Parsed Schedule Time:");
    console.log("   - In User Timezone:", scheduledMoment.format("YYYY-MM-DD HH:mm:ss z"));
    console.log("   - UTC Equivalent:", scheduledMoment.clone().tz("UTC").format("YYYY-MM-DD HH:mm:ss z"));
    console.log("   - Will execute at:", scheduledMoment.format("hh:mm A"), "in", timezone);

    const scheduleData = {
      id: nodeScheduleId,
      scheduleId: scheduleId,
      // Engine authority — preserved across in-memory queue rehydrate.
      // executeBulkScheduledMessage reads bulkSchedule.provider to route
      // Baileys/WABA/Twilio; without this key the schedule would fall
      // back to the workspace-level use_facebook_api heuristic on fire.
      provider: provider || null,
      senderPhoneNumber: phoneNumber,
      targetPhoneNumbers: targetPhoneNumbers,
      message: message || (templateData ? templateData.template_body : ''),
      scheduleDateTime: scheduleDateTime,
      timezone: timezone || "UTC",
      messageType: messageType || (isTemplate ? "template" : "text"),
      mediaUrl: mediaUrl,
      latitude: latitude,
      longitude: longitude,
      isTemplate: isTemplate || false,
      templateData: templateData || null,
      recipientAttributes: recipientAttributes || {},
      status: "scheduled",
      type: "bulk",
      createdAt: moment().format(),
      scheduledFor: scheduledMoment.format("YYYY-MM-DD HH:mm:ss z"), // Store readable format
    };

    app.locals.scheduledMessages.push(scheduleData);

    // "Send now" (and any due/past fire time) must go out IMMEDIATELY. A cron
    // expression only matches at second :00 of its minute, so a job armed for
    // the CURRENT minute never fires once we're past :00 — it would wait until
    // that minute recurs (next year). So when the target time is now or already
    // past, execute right away and skip the cron entirely. 5s grace absorbs the
    // Laravel→Node round-trip + clock skew.
    const secsUntilBulk = scheduledMoment.diff(moment(), 'seconds');
    if (secsUntilBulk <= 5) {
      console.log(`⚡ Firing bulk schedule ${nodeScheduleId} immediately (secsUntil=${secsUntilBulk})`);
      setImmediate(() => executeBulkScheduledMessage(nodeScheduleId, app, process.env.APP_DOMAIN_NAME || "http://localhost:8000"));
      return res.json({
        success: true,
        message: "Sending now",
        scheduleId: nodeScheduleId,
        scheduledFor: moment().format("YYYY-MM-DD hh:mm:ss A z"),
        timezone: timezone,
      });
    }

    // ✅ FIX: Create cron expression using the EXACT time from user's timezone
    // No conversion, no offset addition - just use the time as-is
    const cronExpression = `${scheduledMoment.minutes()} ${scheduledMoment.hours()} ${scheduledMoment.date()} ${scheduledMoment.month() + 1} *`;






    // ✅ FIX: Pass timezone to cron so it runs in user's timezone
    const job = cron.schedule(
      cronExpression,
      () => {
        console.log(`\n⏰ CRON TRIGGERED for ${nodeScheduleId}`);
        console.log(`   - Current time in ${timezone}:`, moment().tz(timezone).format("YYYY-MM-DD HH:mm:ss z"));
        executeBulkScheduledMessage(nodeScheduleId, app, process.env.APP_DOMAIN_NAME || "http://localhost:8000");
      },
      {
        timezone: timezone || "UTC", // ✅ This ensures cron runs in user's timezone
      }
    );

    app.locals.scheduledJobs[nodeScheduleId] = job;





    res.json({
      success: true,
      message: "Bulk message scheduled",
      scheduleId: nodeScheduleId,
      scheduledFor: scheduledMoment.format("YYYY-MM-DD hh:mm:ss A z"),
      timezone: timezone,
    });
  } catch (error) {

    res.status(500).json({ error: "Failed to schedule bulk message" });
  }
}

export function scheduleRecurring(req, res, app) {
  const { phoneNumber } = req.params;
  const {
    scheduleId,
    provider,
    targetQueues,
    // Laravel snapshots group/number recipients into targetPhoneNumbers when
    // it registers the recurring schedule. The runtime executor prefers this
    // list and falls back to fetchQueueRecipients(targetQueues) only if it's
    // empty — that keeps both legacy queue-based and new schema flows working.
    targetPhoneNumbers,
    message,
    scheduleDateTime,
    repeatInterval,
    repeatEvery,
    daysOfWeek,
    endDate,
    timezone,
    messageType,
    mediaUrl,
    latitude,
    longitude,
    isTemplate,
    templateData,
    recipientAttributes,
  } = req.body;

  try {











    const nodeScheduleId = `recurring_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

    // ✅ FIX: Parse in user's timezone
    const scheduledMoment = moment.tz(scheduleDateTime, timezone || "UTC");
    
    console.log("✅ Parsed Start Time:");
    console.log("   - In User Timezone:", scheduledMoment.format("YYYY-MM-DD HH:mm:ss z"));
    console.log("   - Will execute at:", scheduledMoment.format("hh:mm A"), "in", timezone);

    const scheduleData = {
      id: nodeScheduleId,
      scheduleId: scheduleId,
      // Engine authority — see comment in bulk handler. Recurring rows
      // re-fire from the in-memory queue on every interval; losing
      // provider here would silently flip every recurrence to the
      // workspace-default engine.
      provider: provider || null,
      senderPhoneNumber: phoneNumber,
      targetQueues: targetQueues || [],
      targetPhoneNumbers: Array.isArray(targetPhoneNumbers) ? targetPhoneNumbers : [],
      message: message,
      scheduleDateTime: scheduleDateTime,
      repeatInterval: repeatInterval,
      repeatEvery: repeatEvery || 1,
      daysOfWeek: daysOfWeek,
      endDate: endDate,
      timezone: timezone || "UTC",
      messageType: messageType || (isTemplate ? "template" : "text"),
      mediaUrl: mediaUrl,
      latitude: latitude,
      longitude: longitude,
      isTemplate: isTemplate || false,
      templateData: templateData || null,
      recipientAttributes: recipientAttributes || {},
      status: "running",
      type: "recurring",
      totalRuns: 0,
      lastRunAt: null,
      createdAt: moment().format(),
      scheduledFor: scheduledMoment.format("YYYY-MM-DD HH:mm:ss z"),
    };

    app.locals.scheduledMessages.push(scheduleData);

    // ✅ FIX: Build cron expression based on repeat interval
    let cronExpression;

    if (repeatInterval === "daily") {
      // Run daily at specified time
      cronExpression = `${scheduledMoment.minutes()} ${scheduledMoment.hours()} * * *`;
      console.log(`📅 Daily schedule: Every day at ${scheduledMoment.format("hh:mm A")}`);
      
    } else if (repeatInterval === "weekly") {
      // Run on specific days of week at specified time
      // daysOfWeek format: [0, 3] means Sunday and Wednesday
      const days = daysOfWeek && daysOfWeek.length > 0 ? daysOfWeek.join(",") : "*";
      cronExpression = `${scheduledMoment.minutes()} ${scheduledMoment.hours()} * * ${days}`;
      
      const dayNames = daysOfWeek.map(d => 
        ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"][d]
      ).join(", ");
      
      console.log(`📅 Weekly schedule: Every ${dayNames} at ${scheduledMoment.format("hh:mm A")}`);
      
    } else if (repeatInterval === "monthly") {
      // Run on same day of month at specified time
      cronExpression = `${scheduledMoment.minutes()} ${scheduledMoment.hours()} ${scheduledMoment.date()} * *`;
      console.log(`📅 Monthly schedule: Day ${scheduledMoment.date()} of every month at ${scheduledMoment.format("hh:mm A")}`);
    }

    console.log("🔧 Cron Expression:", cronExpression);
    console.log("   - Minutes:", scheduledMoment.minutes());
    console.log("   - Hours:", scheduledMoment.hours());
    console.log("   - Days:", daysOfWeek || "all");

    // ✅ FIX: Create cron job with user's timezone
    const job = cron.schedule(
      cronExpression,
      () => {


        
        // Check if we've passed the end date
        if (endDate) {
          const now = moment().tz(timezone);
          const end = moment.tz(endDate, timezone);
          
          if (now.isAfter(end)) {
            console.log(`🏁 End date reached (${end.format("YYYY-MM-DD")}), stopping recurring schedule`);
            
            const msgIndex = app.locals.scheduledMessages.findIndex(m => m.id === nodeScheduleId);
            if (msgIndex !== -1) {
              app.locals.scheduledMessages[msgIndex].status = "completed";
            }
            
            if (app.locals.scheduledJobs[nodeScheduleId]) {
              app.locals.scheduledJobs[nodeScheduleId].stop();
              delete app.locals.scheduledJobs[nodeScheduleId];
            }
            return;
          }
        }
        
        executeRecurringSchedule(nodeScheduleId, app, process.env.APP_DOMAIN_NAME || "http://localhost:8000");
      },
      {
        timezone: timezone || "UTC", // ✅ This ensures cron runs in user's timezone
      }
    );

    app.locals.scheduledJobs[nodeScheduleId] = job;



    if (endDate) {

    }


    res.json({
      success: true,
      message: "Recurring message scheduled",
      scheduleId: nodeScheduleId,
      cronExpression: cronExpression,
      scheduledFor: scheduledMoment.format("YYYY-MM-DD hh:mm:ss A z"),
      timezone: timezone,
    });
  } catch (error) {

    res.status(500).json({ error: "Failed to schedule recurring message" });
  }
}

export function sendBulkImmediate(req, res, app) {
  const { phoneNumber } = req.params;
  const {
    scheduleId,
    provider,
    targetPhoneNumbers,
    message,
    messageType,
    mediaUrl,
    latitude,
    longitude,
    isTemplate,
    templateData,
    recipientAttributes,
  } = req.body;

  try {








    const nodeScheduleId = `immediate_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

    const scheduleData = {
      id: nodeScheduleId,
      scheduleId: scheduleId,
      // Engine authority for immediate-send path (fired-now schedules).
      provider: provider || null,
      senderPhoneNumber: phoneNumber,
      targetPhoneNumbers: targetPhoneNumbers,
      message: message || (templateData ? templateData.template_body : ''),
      messageType: messageType || (isTemplate ? "template" : "text"),
      mediaUrl: mediaUrl,
      latitude: latitude,
      longitude: longitude,
      isTemplate: isTemplate || false,
      templateData: templateData || null,
      recipientAttributes: recipientAttributes || {},
      status: "sending",
      type: "bulk",
      createdAt: moment().format(),
    };

    app.locals.scheduledMessages.push(scheduleData);

    // Execute immediately
    executeBulkScheduledMessage(nodeScheduleId, app, process.env.APP_DOMAIN_NAME || "http://localhost:8000");




    res.json({
      success: true,
      message: "Messages are being sent",
      scheduleId: nodeScheduleId,
    });
  } catch (error) {

    res.status(500).json({ error: "Failed to send messages" });
  }
}

// Get scheduled messages
export function getScheduledMessages(req, res, app) {
  const { phoneNumber } = req.params;

  try {
    const messages = app.locals.scheduledMessages.filter(
      (msg) => msg.senderPhoneNumber === phoneNumber
    );

    res.json({
      success: true,
      count: messages.length,
      messages: messages,
    });
  } catch (error) {

    res.status(500).json({ error: "Failed to get scheduled messages" });
  }
}

// Schedule single message (legacy support)
export function scheduleMessage(req, res, app) {






  const { phoneNumber } = req.params;
  const {
    targetPhoneNumber,
    provider,
    message,
    scheduleDateTime,
    timezone,
    messageType,
    mediaUrl,
    latitude,
    longitude,
  } = req.body;

  console.log(`\n[SCHEDULE] ========== SCHEDULE MESSAGE START ==========`);
  console.log(`[SCHEDULE] from=${phoneNumber} to=${targetPhoneNumber}`);
  console.log(`[SCHEDULE] when=${scheduleDateTime} tz=${timezone || 'UTC'}`);
  console.log(`[SCHEDULE] type=${messageType || 'text'} body="${(message || '').substring(0, 60)}"`);

  try {
    const scheduleId = `single_${Date.now()}_${Math.random()
      .toString(36)
      .substr(2, 9)}`;

    const scheduleData = {
      id: scheduleId,
      // Engine authority — single-target scheduled message.
      provider: provider || null,
      senderPhoneNumber: phoneNumber,
      targetPhoneNumber,
      message,
      scheduleDateTime,
      timezone: timezone || "UTC",
      messageType: messageType || "text",
      mediaUrl,
      latitude,
      longitude,
      status: "scheduled",
      type: "single",
      createdAt: moment().format(),
    };



    // Save to app.locals
    app.locals.scheduledMessages.push(scheduleData);
    console.log(`[SCHEDULE] queued id=${scheduleId} (total in queue: ${app.locals.scheduledMessages.length})`);

    // Parse schedule
    const scheduledMoment = moment.tz(scheduleDateTime, timezone || "UTC");
    const secsUntil = scheduledMoment.diff(moment(), 'seconds');
    console.log(`[SCHEDULE] parsed ${scheduledMoment.format()} → fires in ${secsUntil}s`);

    // Now-or-past fire time (Send-now / due) → send immediately. A cron for the
    // current minute is missed once we're past :00, so never rely on it here.
    if (secsUntil <= 5) {
      console.log(`[SCHEDULE] ⚡ firing ${scheduleId} immediately (secsUntil=${secsUntil})`);
      setImmediate(() => executeScheduledMessage(scheduleId, app));
      return res.json({ success: true, message: "Message sending now", scheduleId, scheduledFor: moment().format() });
    }

    const cronExpression = `${scheduledMoment.minutes()} ${scheduledMoment.hours()} ${scheduledMoment.date()} ${scheduledMoment.month() + 1} *`;
    console.log(`[SCHEDULE] cron expression: ${cronExpression}`);

    // Create cron job
    const job = cron.schedule(
      cronExpression,
      () => {
        console.log(`[SCHEDULE] ⏰ FIRING ${scheduleId} for ${targetPhoneNumber}`);
        executeScheduledMessage(scheduleId, app);
      },
      {
        timezone: timezone || "UTC",
      }
    );

    // Save cron job
    app.locals.scheduledJobs[scheduleId] = job;
    console.log(`[SCHEDULE] ✅ scheduled successfully, job stored. Returning 200.`);
    console.log(`[SCHEDULE] ========== SCHEDULE MESSAGE END ==========\n`);


    res.json({
      success: true,
      message: "Message scheduled",
      scheduleId,
      scheduledFor: scheduledMoment.format(),
    });
  } catch (error) {






    console.error(`[SCHEDULE] ❌ ERROR: ${error?.message}`);
    console.error(`[SCHEDULE] stack: ${error?.stack}`);
    res.status(500).json({ error: "Failed to schedule message", details: error?.message });
  }
}


// Execute scheduled message function with Baileys WhatsApp sending
async function executeScheduledMessage(scheduleId, app) {
  console.log(`\n[EXEC-SCHEDULE] ========== EXECUTING ${scheduleId} ==========`);

  try {
    // Find the scheduled message
    const messageIndex = app.locals.scheduledMessages.findIndex(
      msg => msg.id === scheduleId
    );

    if (messageIndex === -1) {
      console.error(`[EXEC-SCHEDULE] ❌ schedule id not found in queue: ${scheduleId}`);
      return;
    }

    const messageData = app.locals.scheduledMessages[messageIndex];
    console.log(`[EXEC-SCHEDULE] from=${messageData.senderPhoneNumber} to=${messageData.targetPhoneNumber} type=${messageData.messageType}`);

    // Update status to 'executing'
    messageData.status = 'executing';


    // ========================================
    // 🔥 GET BAILEYS CLIENT (WhatsApp Session)
    // ========================================
    
    const { 
      senderPhoneNumber, 
      targetPhoneNumber, 
      message, 
      messageType,
      mediaUrl,
      latitude,
      longitude 
    } = messageData;





    // Multi-engine: a scheduled message pinned to WABA/Twilio must NOT use the
    // Baileys sock (there is none on those workspaces — the old code threw
    // "session not found"). Route text sends via the same helpers the bulk
    // scheduler uses. Media/location single-schedules on WABA/Twilio fail
    // loudly here rather than silently misroute — use a campaign/broadcast for
    // those (the bulk path builds the proper per-engine payloads).
    const _provider = String(messageData.provider || "").toLowerCase();
    if (_provider === "waba" || _provider === "twilio") {
      try {
        const _settings = await getWhatsAppSettings(app.locals.appDomainName, { phone: senderPhoneNumber });
        if (messageType && messageType !== "text") {
          throw new Error(`single scheduled ${messageType} not supported on ${_provider} — send it as a campaign/broadcast`);
        }
        let _res;
        if (_provider === "twilio") {
          _res = await sendMessageViaTwilioApi(targetPhoneNumber, { type: "text", body: message }, _settings);
        } else {
          _res = await sendMessageViaFacebookApi(formatPhoneNumber(targetPhoneNumber), { type: "text", text: { preview_url: false, body: message } }, _settings);
        }
        if (_res && _res.success) {
          messageData.status = "sent";
          messageData.sentAt = new Date().toISOString();
          console.log(`[EXEC-SCHEDULE] sent via ${_provider} to ${targetPhoneNumber}`);
        } else {
          messageData.status = "failed";
          messageData.error = (_res && _res.error) || `${_provider} send failed`;
          console.error(`[EXEC-SCHEDULE] ${_provider} send failed: ${messageData.error}`);
        }
      } catch (err) {
        messageData.status = "failed";
        messageData.error = err?.message;
        console.error(`[EXEC-SCHEDULE] ${_provider} send threw: ${err?.message}`);
      }
      return;
    }

    // Get the Baileys WhatsApp client for this sender
    const client = app.locals.clients[senderPhoneNumber];
    
    if (!client) {
      throw new Error(`❌ WhatsApp session not found for ${senderPhoneNumber}. Please connect the device first.`);
    }

    // Check if client is ready
    const isReady = app.locals.client_ready[senderPhoneNumber];
    if (!isReady) {
      throw new Error(`❌ WhatsApp client for ${senderPhoneNumber} is not ready. Current status: connecting/disconnected`);
    }


    // Build a clean JID. The number coming from Laravel may have a
    // leading `+` (e.g. "+919145808988") — passing that through
    // unchanged makes Baileys' send hang for 60s waiting for an ack
    // that never arrives, because WhatsApp rejects JIDs with a `+`.
    // formatPhoneNumber strips non-digits and keeps the trailing 12.
    const formattedTarget = targetPhoneNumber.includes('@')
      ? targetPhoneNumber
      : formatPhoneNumber(targetPhoneNumber);
    console.log(`[EXEC-SCHEDULE] target jid: ${formattedTarget}`);

    let sendResult;

    // ========================================
    // 🔥 SEND MESSAGE BASED ON TYPE
    // ========================================

    switch (messageType) {
      case 'text':
        console.log(`[EXEC-SCHEDULE] sending text to ${formattedTarget}`);
        sendResult = await client.sendMessage(formattedTarget, {
          text: message
        });
        console.log(`[EXEC-SCHEDULE] send returned id=${sendResult?.key?.id}`);
        break;

      case 'media_with_caption':



        
        // Download media from URL
        const mediaResponse = await axios.get(mediaUrl, { 
          responseType: 'arraybuffer' 
        });
        const mediaBuffer = Buffer.from(mediaResponse.data);
        
        // Detect media type from URL
        const mediaExtension = mediaUrl.split('.').pop().toLowerCase();
        let mediaMessage = {};
        
        if (['jpg', 'jpeg', 'png', 'gif'].includes(mediaExtension)) {
          mediaMessage = {
            image: mediaBuffer,
            caption: message || ''
          };

        } else if (['mp4', 'avi', 'mov'].includes(mediaExtension)) {
          mediaMessage = {
            video: mediaBuffer,
            caption: message || ''
          };

        } else if (['pdf', 'doc', 'docx', 'xls', 'xlsx'].includes(mediaExtension)) {
          const fileName = mediaUrl.split('/').pop();
          mediaMessage = {
            document: mediaBuffer,
            fileName: fileName,
            caption: message || ''
          };

        } else {
          throw new Error(`Unsupported media type: ${mediaExtension}`);
        }
        
        sendResult = await client.sendMessage(formattedTarget, mediaMessage);


        break;

      case 'media_only':


        
        // Download media from URL
        const mediaOnlyResponse = await axios.get(mediaUrl, { 
          responseType: 'arraybuffer' 
        });
        const mediaOnlyBuffer = Buffer.from(mediaOnlyResponse.data);
        
        // Detect media type
        const mediaOnlyExtension = mediaUrl.split('.').pop().toLowerCase();
        let mediaOnlyMessage = {};
        
        if (['jpg', 'jpeg', 'png', 'gif'].includes(mediaOnlyExtension)) {
          mediaOnlyMessage = {
            image: mediaOnlyBuffer
          };

        } else if (['mp4', 'avi', 'mov'].includes(mediaOnlyExtension)) {
          mediaOnlyMessage = {
            video: mediaOnlyBuffer
          };

        } else if (['pdf', 'doc', 'docx', 'xls', 'xlsx'].includes(mediaOnlyExtension)) {
          const fileName = mediaUrl.split('/').pop();
          mediaOnlyMessage = {
            document: mediaOnlyBuffer,
            fileName: fileName
          };

        } else {
          throw new Error(`Unsupported media type: ${mediaOnlyExtension}`);
        }
        
        sendResult = await client.sendMessage(formattedTarget, mediaOnlyMessage);


        break;

      case 'location':




        
        sendResult = await client.sendMessage(formattedTarget, {
          location: {
            degreesLatitude: parseFloat(latitude),
            degreesLongitude: parseFloat(longitude),
            name: message || 'Location',
            address: message || 'Shared Location'
          }
        });


        break;

      default:
        throw new Error(`❌ Unsupported message type: ${messageType}`);
    }

    // ========================================
    // 🔥 UPDATE MESSAGE STATUS AFTER SENDING
    // ========================================

    // After successful sending, update status
    messageData.status = 'sent';
    messageData.sentAt = moment().format();
    messageData.messageId = sendResult.key.id;
    messageData.whatsappMessageId = sendResult.key.id;
    console.log(`[EXEC-SCHEDULE] ✅ SENT id=${sendResult.key.id} jid=${sendResult.key.remoteJid}`);










    // Clean up the cron job
    if (app.locals.scheduledJobs[scheduleId]) {
      app.locals.scheduledJobs[scheduleId].stop();
      delete app.locals.scheduledJobs[scheduleId];

    }

    // Remove from scheduled messages after a delay
    setTimeout(() => {
      const idx = app.locals.scheduledMessages.findIndex(m => m.id === scheduleId);
      if (idx !== -1) {
        app.locals.scheduledMessages.splice(idx, 1);


      }
    }, 60000); // Remove after 1 minute


  } catch (error) {







    console.error(`[EXEC-SCHEDULE] ❌ FAILED ${scheduleId}: ${error?.message}`);
    console.error(`[EXEC-SCHEDULE] stack: ${error?.stack}`);

    // Update status to failed
    const messageIndex = app.locals.scheduledMessages.findIndex(
      msg => msg.id === scheduleId
    );
    if (messageIndex !== -1) {
      app.locals.scheduledMessages[messageIndex].status = 'failed';
      app.locals.scheduledMessages[messageIndex].error = error.message;
      app.locals.scheduledMessages[messageIndex].failedAt = moment().format();
    }

    // Still clean up the cron job even on failure
    if (app.locals.scheduledJobs[scheduleId]) {
      app.locals.scheduledJobs[scheduleId].stop();
      delete app.locals.scheduledJobs[scheduleId];

    }
  }
}

// ==============================
// Bot startup → Laravel sync
// ==============================
// Called once from index.js after sessions restore. Asks Laravel for every
// active schedule (status in scheduled/running) and rebuilds the in-memory
// `scheduledMessages` + `scheduledJobs` tables. Without this, a `pm2 restart`
// silently kills every cron job because the DB rows still exist on the
// Laravel side but the Node process knows nothing about them.
export async function syncScheduledMessages(app, appDomainName) {
  if (!appDomainName) {
    console.warn("⚠️ syncScheduledMessages: APP_DOMAIN_NAME not set, skipping");
    return;
  }

  const token = process.env.NODE_WEBHOOK_TOKEN || "";

  let rows = [];
  try {
    const res = await axios.get(appDomainName + "/api/scheduled/active", {
      headers: token ? { "X-Node-Token": token } : {},
      timeout: 15000,
    });
    rows = (res.data && Array.isArray(res.data.schedules)) ? res.data.schedules : [];
  } catch (e) {
    logLaravelError("Sync scheduled messages", appDomainName + "/api/scheduled/active", e);
    return;
  }

  if (rows.length === 0) {
    console.log("✓ syncScheduledMessages: no active schedules to restore");
    return;
  }

  let bulk = 0, recurring = 0;
  for (const row of rows) {
    try {
      if (row.schedule_type === "recurring") {
        registerRecurringInternal(app, row);
        recurring++;
      } else {
        registerBulkInternal(app, row);
        bulk++;
      }
    } catch (e) {
      console.error(`❌ Failed to restore schedule ${row.id}:`, e.message);
    }
  }
  console.log(`✓ syncScheduledMessages: restored ${bulk} one-off + ${recurring} recurring schedules`);
}

// Internal helper: register a one-off schedule's cron without going through
// the full HTTP handler. Mirrors `scheduleBulkMessage` but skips the request/
// response envelope.
function registerBulkInternal(app, row) {
  const scheduledMoment = moment.tz(row.scheduleDateTime, row.timezone || "UTC");

  // If the time already passed (server was down past the original send time),
  // skip — the schedule will surface in /scheduled with status='scheduled'
  // and the operator can hit run-now to fire it manually.
  //
  // NOTE: this skip is also a DOUBLE-SEND GUARD. A row that already fired but
  // whose "completed" status update to Laravel failed (updateBulkScheduleStatus
  // swallows network errors) stays status='scheduled'; on the next restart it
  // is past-due and skipped here, so it is NOT re-sent. Auto-firing past-due
  // rows for durability is only safe behind a transactional claim (mark
  // 'running' before send + exclude claimed one-offs from /api/scheduled/active
  // + abort-on-claim-failure). Until that lands, keep skipping.
  if (scheduledMoment.isBefore(moment())) {
    console.warn(`⏭️ Skipping past-due schedule ${row.id} (was ${row.scheduleDateTime})`);
    return;
  }

  const nodeId = row.node_schedule_id || `bulk_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

  const scheduleData = {
    id: nodeId,
    scheduleId: row.id,
    // Engine authority — preserved through bot-boot rehydrate from
    // /api/scheduled/active. Without this, schedules silently re-route
    // through the workspace-default engine after a Node restart.
    provider: row.provider || null,
    senderPhoneNumber: row.from_number,
    targetPhoneNumbers: row.targetPhoneNumbers || [],
    message: row.message || (row.templateData ? row.templateData.template_body : ""),
    scheduleDateTime: row.scheduleDateTime,
    timezone: row.timezone || "UTC",
    messageType: row.messageType || (row.isTemplate ? "template" : "text"),
    mediaUrl: row.mediaUrl,
    latitude: row.latitude,
    longitude: row.longitude,
    isTemplate: row.isTemplate || false,
    templateData: row.templateData || null,
    recipientAttributes: row.recipientAttributes || {},
    status: "scheduled",
    type: "bulk",
    createdAt: moment().format(),
    scheduledFor: scheduledMoment.format("YYYY-MM-DD HH:mm:ss z"),
  };

  app.locals.scheduledMessages.push(scheduleData);

  const cronExpression = `${scheduledMoment.minutes()} ${scheduledMoment.hours()} ${scheduledMoment.date()} ${scheduledMoment.month() + 1} *`;
  const job = cron.schedule(cronExpression, () => {
    executeBulkScheduledMessage(nodeId, app, process.env.APP_DOMAIN_NAME || "http://localhost:8000");
  }, { timezone: row.timezone || "UTC" });

  app.locals.scheduledJobs[nodeId] = job;
}

// Internal helper: same idea for recurring schedules.
function registerRecurringInternal(app, row) {
  const scheduledMoment = moment.tz(row.scheduleDateTime, row.timezone || "UTC");
  const nodeId = row.node_schedule_id || `recurring_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

  const scheduleData = {
    id: nodeId,
    scheduleId: row.id,
    // Engine authority — recurring rehydrate path. Same reason as the
    // bulk rehydrate above: a Node restart that re-pulls schedules from
    // /api/scheduled/active would otherwise drop the row provider and
    // silently flip every future recurrence to the workspace default.
    provider: row.provider || null,
    senderPhoneNumber: row.from_number,
    targetQueues: [],
    targetPhoneNumbers: row.targetPhoneNumbers || [],
    message: row.message,
    scheduleDateTime: row.scheduleDateTime,
    repeatInterval: row.repeatInterval,
    repeatEvery: row.repeatEvery || 1,
    daysOfWeek: row.daysOfWeek || [],
    endDate: row.endDate,
    timezone: row.timezone || "UTC",
    messageType: row.messageType || "text",
    mediaUrl: row.mediaUrl,
    latitude: row.latitude,
    longitude: row.longitude,
    isTemplate: row.isTemplate || false,
    templateData: row.templateData || null,
    recipientAttributes: row.recipientAttributes || {},
    status: "running",
    type: "recurring",
    totalRuns: 0,
    lastRunAt: null,
    createdAt: moment().format(),
    scheduledFor: scheduledMoment.format("YYYY-MM-DD HH:mm:ss z"),
  };
  app.locals.scheduledMessages.push(scheduleData);

  let cronExpression;
  if (row.repeatInterval === "daily") {
    cronExpression = `${scheduledMoment.minutes()} ${scheduledMoment.hours()} * * *`;
  } else if (row.repeatInterval === "weekly") {
    const days = (row.daysOfWeek && row.daysOfWeek.length) ? row.daysOfWeek.join(",") : "*";
    cronExpression = `${scheduledMoment.minutes()} ${scheduledMoment.hours()} * * ${days}`;
  } else if (row.repeatInterval === "monthly") {
    cronExpression = `${scheduledMoment.minutes()} ${scheduledMoment.hours()} ${scheduledMoment.date()} * *`;
  } else {
    console.warn(`⚠️ Unknown repeatInterval ${row.repeatInterval} for schedule ${row.id}, skipping`);
    return;
  }

  const job = cron.schedule(cronExpression, () => {
    executeRecurringSchedule(nodeId, app, process.env.APP_DOMAIN_NAME || "http://localhost:8000");
  }, { timezone: row.timezone || "UTC" });

  app.locals.scheduledJobs[nodeId] = job;
}
