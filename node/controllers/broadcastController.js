// controllers/broadcastController.js
// ==============================
// Broadcast Controller - Handles immediate and scheduled broadcasts
// ==============================

import moment from "moment-timezone";
import cron from "node-cron";
import { executeBroadcastSchedule } from "../services/broadcastService.js";

/**
 * Send broadcast immediately
 */
export function sendBroadcastImmediate(req, res, app) {
  const { phoneNumber } = req.params;
  const {
    broadcastId,
    targetPhoneNumbers, // OLD
    targetContacts,     // NEW
    templateData,
    isTemplate,
    message,
  } = req.body;
  console.log('[BCAST-NODE] send-immediate ENTER', JSON.stringify({
    broadcastId,
    phoneNumber,
    targetContactsCount: Array.isArray(targetContacts) ? targetContacts.length : 0,
    isTemplate: !!isTemplate,
    template_name: templateData?.template_name || null,
    template_body_len: (templateData?.template_body || '').length,
    message_len: (message || '').length,
    clientReady: !!app.locals.client_ready?.[phoneNumber],
    clientExists: !!app.locals.clients?.[phoneNumber],
  }));

  try {
    // 🔍 Log extracted values


    // FIXED: Prefer targetContacts, use phone field
    const contacts =
      targetContacts ||
      (targetPhoneNumbers?.map(phone => ({
        phone,
      })) || []);

    // 🔍 Log processed contacts


    if (contacts.length === 0) {
      console.warn('[BCAST-NODE] aborted — no contacts in payload', { broadcastId, phoneNumber });
      return res.status(400).json({
        error: "No contacts provided",
        details: "Either targetContacts or targetPhoneNumbers must be provided"
      });
    }

    if (!app.locals.client_ready?.[phoneNumber] || !app.locals.clients?.[phoneNumber]) {
      console.warn('[BCAST-NODE] CLIENT NOT READY — broadcast will fail every recipient', {
        broadcastId,
        phoneNumber,
        ready: !!app.locals.client_ready?.[phoneNumber],
        clientExists: !!app.locals.clients?.[phoneNumber],
        knownClients: Object.keys(app.locals.clients || {}),
      });
      // Continue to schedule it anyway so the per-recipient loop
      // logs each failure with context; the operator sees the cause
      // upstream in both Node terminal AND laravel.log.
    }

    const nodeScheduleId = `broadcast_immediate_${Date.now()}_${Math.random()
      .toString(36)
      .substr(2, 9)}`;
    console.log('[BCAST-NODE] queueing for immediate dispatch', {
      nodeScheduleId, broadcastId, phoneNumber, contactCount: contacts.length,
    });


    const broadcastData = {
      id: nodeScheduleId,
      broadcastId,
      senderPhoneNumber: phoneNumber,
      // Multi-engine: the per-record engine PHP stamped on this broadcast
      // slice (broadcasts.provider). broadcastService routes by it; absent
      // => legacy settings heuristic (single-engine unchanged).
      provider: req.body.provider || null,
      // WhatsApp Warmer per-number send-gap {min,max} seconds (Baileys only).
      // Absent => broadcastService falls back to the global msg_gap.
      warmerGap: req.body.warmerGap || null,
      targetContacts: contacts,
      message: message || (templateData ? templateData.template_body : ""),
      isTemplate: isTemplate || false,
      templateData: templateData || null,
      status: "sending",
      type: "broadcast",
      createdAt: moment().format(),
    };

    // 🔍 Log final broadcast payload


    app.locals.scheduledMessages.push(broadcastData);



    // Execute immediately

    executeBroadcastSchedule(
      nodeScheduleId,
      app,
      process.env.APP_DOMAIN_NAME || "http://localhost:8000"
    );


    console.log('[BCAST-NODE] send-immediate accepted — recipients will dispatch async', {
      nodeScheduleId, broadcastId, recipientCount: contacts.length,
    });
    res.json({
      success: true,
      message: "Broadcast is being sent",
      scheduleId: nodeScheduleId,
      recipientCount: contacts.length,
    });

  } catch (error) {
    console.error('[BCAST-NODE] send-immediate THREW', {
      broadcastId, phoneNumber, err: error?.message, stack: error?.stack,
    });
    res.status(500).json({
      error: "Failed to send broadcast",
      details: error.message
    });
  }
}

/**
 * Schedule broadcast for later
 */
export function scheduleBroadcast(req, res, app) {
  const { phoneNumber } = req.params;
  const {
    broadcastId,
    targetPhoneNumbers, // OLD: for backward compatibility
    targetContacts, // NEW: preferred method
    templateData,
    isTemplate,
    message,
    scheduleDateTime,
    timezone,
  } = req.body;

  try {
    // FIXED: Use targetContacts if available, fallback to targetPhoneNumbers, use phone field
    const contacts = targetContacts || 
                     (targetPhoneNumbers?.map(phone => ({ phone })) || []);


    if (contacts.length === 0) {

      return res.status(400).json({ 
        error: "No contacts provided",
        details: "Either targetContacts or targetPhoneNumbers must be provided"
      });
    }

    const nodeScheduleId = `broadcast_scheduled_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

    const broadcastData = {
      id: nodeScheduleId,
      broadcastId: broadcastId,
      senderPhoneNumber: phoneNumber,
      // Multi-engine: per-record engine (broadcasts.provider); absent => legacy heuristic.
      provider: req.body.provider || null,
      // WhatsApp Warmer per-number send-gap {min,max} seconds (Baileys only).
      // Absent => broadcastService falls back to the global msg_gap.
      warmerGap: req.body.warmerGap || null,
      targetContacts: contacts, // FIXED: Store contact objects with phone field
      message: message || (templateData ? templateData.template_body : ''),
      scheduleDateTime: scheduleDateTime,
      timezone: timezone || "UTC",
      isTemplate: isTemplate || false,
      templateData: templateData || null,
      status: "scheduled",
      type: "broadcast",
      createdAt: moment().format(),
    };

    app.locals.scheduledMessages.push(broadcastData);

    const scheduledMoment = moment.tz(scheduleDateTime, timezone || "UTC");
    const cronExpression = `${scheduledMoment.minutes()} ${scheduledMoment.hours()} ${scheduledMoment.date()} ${scheduledMoment.month() + 1} *`;

    const job = cron.schedule(
      cronExpression,
      () => {
        executeBroadcastSchedule(nodeScheduleId, app, process.env.APP_DOMAIN_NAME || "http://localhost:8000");
      },
      {
        timezone: timezone || "UTC",
      }
    );

    app.locals.scheduledJobs[nodeScheduleId] = job;



    if (isTemplate) {

    }

    res.json({
      success: true,
      message: "Broadcast scheduled successfully",
      scheduleId: nodeScheduleId,
      scheduledFor: scheduledMoment.format(),
      recipientCount: contacts.length,
    });
  } catch (error) {

    res.status(500).json({ 
      error: "Failed to schedule broadcast",
      details: error.message 
    });
  }
}

/**
 * Pause scheduled broadcast
 */
export function pauseBroadcast(req, res, app) {
  const { scheduleId } = req.params;

  try {
    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === scheduleId
    );

    if (msgIndex === -1) {
      return res.status(404).json({ error: "Broadcast schedule not found" });
    }

    app.locals.scheduledMessages[msgIndex].status = "paused";

    if (app.locals.scheduledJobs[scheduleId]) {
      app.locals.scheduledJobs[scheduleId].stop();
    }

    res.json({ success: true, message: "Broadcast paused" });
  } catch (error) {

    res.status(500).json({ error: "Failed to pause broadcast" });
  }
}

/**
 * Resume paused broadcast
 */
export function resumeBroadcast(req, res, app) {
  const { scheduleId } = req.params;

  try {
    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === scheduleId
    );

    if (msgIndex === -1) {
      return res.status(404).json({ error: "Broadcast schedule not found" });
    }

    const broadcast = app.locals.scheduledMessages[msgIndex];
    broadcast.status = "scheduled";

    if (app.locals.scheduledJobs[scheduleId]) {
      app.locals.scheduledJobs[scheduleId].start();
    }

    res.json({ success: true, message: "Broadcast resumed" });
  } catch (error) {

    res.status(500).json({ error: "Failed to resume broadcast" });
  }
}

/**
 * Cancel scheduled broadcast
 */
export function cancelBroadcast(req, res, app) {
  const { scheduleId } = req.params;

  try {
    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === scheduleId
    );

    if (msgIndex === -1) {
      return res.status(404).json({ error: "Broadcast schedule not found" });
    }

    app.locals.scheduledMessages[msgIndex].status = "cancelled";

    if (app.locals.scheduledJobs[scheduleId]) {
      app.locals.scheduledJobs[scheduleId].stop();
      delete app.locals.scheduledJobs[scheduleId];
    }

    // Remove from scheduled messages array
    app.locals.scheduledMessages.splice(msgIndex, 1);

    res.json({ success: true, message: "Broadcast cancelled" });
  } catch (error) {

    res.status(500).json({ error: "Failed to cancel broadcast" });
  }
}

/**
 * Cancel EVERY pending node-side schedule for a given Laravel broadcast id.
 *
 * Wide-net cleanup PHP calls before any /start-sending re-dispatch — fixes
 * the "broadcast fires 2-3 times" bug where rapid re-schedules left orphaned
 * cron entries Node still held but PHP had forgotten the schedule id of (e.g.
 * a prior dispatch that failed mid-flight without writing node_schedule_id
 * back). Without this, cancelling by stored id missed those orphans and
 * they fired at their original times on top of the new dispatch.
 *
 * Iterates app.locals.scheduledMessages, drops every entry whose
 * `broadcastId` equals the param, and stops the matching cron job from
 * scheduledJobs. Safe to call when there are no pending schedules
 * (responds with cancelled:0).
 */
export function cancelByBroadcastId(req, res, app) {
  const { broadcastId } = req.params;
  try {
    const targetId = String(broadcastId);
    if (!targetId) return res.status(422).json({ error: "broadcastId required" });

    let cancelled = 0;
    // Iterate in reverse so splice() doesn't shift indices under us.
    for (let i = app.locals.scheduledMessages.length - 1; i >= 0; i--) {
      const msg = app.locals.scheduledMessages[i];
      if (String(msg?.broadcastId ?? '') !== targetId) continue;
      // Stop the cron + drop the job ref.
      const sid = msg.id;
      if (sid && app.locals.scheduledJobs[sid]) {
        try { app.locals.scheduledJobs[sid].stop(); } catch (e) { /* swallow */ }
        delete app.locals.scheduledJobs[sid];
      }
      app.locals.scheduledMessages.splice(i, 1);
      cancelled++;
    }
    res.json({ success: true, broadcastId: targetId, cancelled });
  } catch (error) {
    res.status(500).json({ error: "Failed to cancel by broadcast id", details: error?.message });
  }
}

/**
 * Get all broadcasts for a phone number
 */
export function getBroadcasts(req, res, app) {
  const { phoneNumber } = req.params;

  try {
    const broadcasts = app.locals.scheduledMessages.filter(
      (msg) => msg.senderPhoneNumber === phoneNumber && msg.type === "broadcast"
    );

    res.json({
      success: true,
      count: broadcasts.length,
      broadcasts: broadcasts,
    });
  } catch (error) {

    res.status(500).json({ error: "Failed to get broadcasts" });
  }
}

/**
 * Get broadcast status
 */
export function getBroadcastStatus(req, res, app) {
  const { scheduleId } = req.params;

  try {
    const broadcast = app.locals.scheduledMessages.find(
      (msg) => msg.id === scheduleId
    );

    if (!broadcast) {
      return res.status(404).json({ error: "Broadcast not found" });
    }

    res.json({
      success: true,
      broadcast: broadcast,
    });
  } catch (error) {

    res.status(500).json({ error: "Failed to get broadcast status" });
  }
}