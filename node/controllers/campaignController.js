// controllers/campaignController.js
// ==============================
// Campaign Controller - Full A/B Testing & Detailed Tracking
// ==============================

import axios from "axios";
import moment from "moment-timezone";
import cron from "node-cron";
import { executeCampaignSchedule } from "../services/campaignService.js";
import { laravelHeaders, logLaravelError } from "../utils/helpers.js";

/**
 * Send campaign immediately with A/B testing support
 */
export async function sendCampaignImmediate(req, res, app) {
  const { phoneNumber } = req.params;
  const {
    campaignId,
    campaignName,
    targetContacts,
    campaignType,
    customMessage,
    templateId,
    templateIdA,
    templateIdB,
    abSplit,
    flowId,
    isABTest,
    useAttributes,
    trackingEnabled,
    isRecurring, // NEW: Check if this is a recurring campaign
    recurringTime, // NEW: Time to send daily (HH:mm format)
  } = req.body;



  try {
    const contacts = targetContacts || [];

    if (contacts.length === 0) {
      return res.status(400).json({
        success: false,
        message: "No contacts provided"
      });
    }

    const nodeScheduleId = `campaign_${campaignId}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

    // Split contacts for A/B testing if enabled
    let contactsA = [];
    let contactsB = [];

    if (isABTest && abSplit && templateIdA && templateIdB) {
      const splitPercentage = parseInt(abSplit) || 50;
      const splitIndex = Math.floor((contacts.length * splitPercentage) / 100);
      
      contactsA = contacts.slice(0, splitIndex);
      contactsB = contacts.slice(splitIndex);


    }

    const campaignData = {
      id: nodeScheduleId,
      campaignId: campaignId,
      campaignName: campaignName,
      senderPhoneNumber: phoneNumber,
      // Multi-engine: per-record engine PHP stamped (wpcampaigns.provider).
      // campaignService routes by it; absent => legacy settings heuristic.
      provider: req.body.provider || null,
      campaignType: campaignType,

      // All contacts
      targetContacts: contacts,
      
      // A/B Testing
      isABTest: isABTest || false,
      abSplit: abSplit || 50,
      contactsA: contactsA,
      contactsB: contactsB,
      
      // Content based on campaign type
      customMessage: customMessage || null,
      templateId: templateId || null,
      templateIdA: templateIdA || null,
      templateIdB: templateIdB || null,
      flowId: flowId || null,
      
      useAttributes: useAttributes || false,
      trackingEnabled: trackingEnabled || false,
      
      // NEW: Recurring settings
      isRecurring: isRecurring || false,
      recurringTime: recurringTime || null,
      recurringActive: isRecurring || false, // Track if recurring is active
      
      status: "sending",
      type: "campaign",
      createdAt: moment().format(),
      
      // Detailed tracking
      sentMessages: {},
      deliveryStatus: {},
      readStatus: {},
      clickTracking: {},
      responseTracking: {},
      
      // Statistics
      stats: {
        total: contacts.length,
        sent: 0,
        failed: 0,
        delivered: 0,
        read: 0,
        responded: 0,
        clicked: 0,
        
        // A/B specific stats
        variantA: {
          sent: 0,
          delivered: 0,
          read: 0,
          responded: 0,
          clicked: 0
        },
        variantB: {
          sent: 0,
          delivered: 0,
          read: 0,
          responded: 0,
          clicked: 0
        }
      }
    };

    // Prevent duplicates: If campaignId already exists in scheduledMessages, remove it first
    const existingIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.campaignId === campaignId && msg.status !== 'completed'
    );
    if (existingIndex !== -1) {
      console.log(`[Campaign] Removing existing active instance of campaign ${campaignId} before re-sending.`);
      const existingMsg = app.locals.scheduledMessages[existingIndex];
      // Stop any existing jobs
      if (app.locals.scheduledJobs[existingMsg.id]) {
        app.locals.scheduledJobs[existingMsg.id].stop();
        delete app.locals.scheduledJobs[existingMsg.id];
      }
      if (app.locals.scheduledJobs[`recurring_${existingMsg.id}`]) {
        app.locals.scheduledJobs[`recurring_${existingMsg.id}`].stop();
        delete app.locals.scheduledJobs[`recurring_${existingMsg.id}`];
      }
      app.locals.scheduledMessages.splice(existingIndex, 1);
    }

    app.locals.scheduledMessages.push(campaignData);



    // Setup recurring schedule if enabled
    if (isRecurring && recurringTime) {
      // For recurring campaigns, set initial status to 'scheduled' not 'sending'
      campaignData.status = "scheduled";
      // Ensure we have a timezone, default to UTC if missing
      const tz = timezone || "UTC"; 
      campaignData.timezone = tz;
      
      setupRecurringSchedule(nodeScheduleId, recurringTime, tz, app);
      console.log(`[Recurring] Campaign ${campaignId} scheduled to run daily at ${recurringTime} (${tz}). Will NOT send now.`);
    } else {
      // Execute immediately ONLY for non-recurring campaigns
      executeCampaignSchedule(
        nodeScheduleId,
        app,
        process.env.APP_DOMAIN_NAME || "http://localhost:8000"
      );
    }

    res.json({
      success: true,
      message: isRecurring ? "Recurring campaign started" : "Campaign is being sent",
      scheduleId: nodeScheduleId,
      recipientCount: contacts.length,
      abTestEnabled: isABTest,
      isRecurring: isRecurring,
      recurringTime: recurringTime,
      timezone: timezone || "UTC",
      splitCounts: isABTest ? {
        variantA: contactsA.length,
        variantB: contactsB.length
      } : null
    });

  } catch (error) {

    res.status(500).json({
      success: false,
      message: error.message
    });
  }
}


function setupRecurringSchedule(nodeScheduleId, recurringTime, timezone, app) {

  const [hours, minutes] = recurringTime.split(':').map(Number);
  
  // Create cron expression for daily execution
  // Format: "minutes hours * * *" (every day at specified time)
  const cronExpression = `${minutes} ${hours} * * *`;
  
  console.log(`[Cron] Setting up recurring job for ${nodeScheduleId} at ${recurringTime} ${timezone} (Cron: ${cronExpression})`);
  
  const job = cron.schedule(cronExpression, () => {
    console.log(`[Recurring] Cron triggered for ${nodeScheduleId} at ${moment().format()}`);
    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === nodeScheduleId
    );

    if (msgIndex === -1) {
      job.stop();
      return;
    }

    const campaign = app.locals.scheduledMessages[msgIndex];
    
    // Check if recurring is still active
    if (!campaign.recurringActive) {
      job.stop();
      delete app.locals.scheduledJobs[`recurring_${nodeScheduleId}`];
      return;
    }
    
    // Reset stats for this daily run
    campaign.stats.sent = 0;
    campaign.stats.failed = 0;
    campaign.stats.delivered = 0;
    campaign.stats.read = 0;
    campaign.stats.responded = 0;
    campaign.stats.clicked = 0;
    
    campaign.stats.variantA = {
      sent: 0, delivered: 0, read: 0, responded: 0, clicked: 0
    };
    
    campaign.stats.variantB = {
      sent: 0, delivered: 0, read: 0, responded: 0, clicked: 0
    };
    
    // Clear previous tracking
    campaign.sentMessages = {};
    campaign.deliveryStatus = {};
    campaign.readStatus = {};
    campaign.clickTracking = {};
    campaign.responseTracking = {};
    
    campaign.status = "sending";
    
    console.log(`[Recurring] Executing recurring run for campaign ${campaign.campaignId}`);

    // Execute campaign
    executeCampaignSchedule(
      nodeScheduleId,
      app,
      process.env.APP_DOMAIN_NAME || "http://localhost:8000"
    );
  }, {
    timezone: timezone || "UTC"
  });

  // Store the recurring job
  app.locals.scheduledJobs[`recurring_${nodeScheduleId}`] = job;
}


/**
 * Schedule campaign for later
 */
export async function scheduleCampaign(req, res, app) {
  const { phoneNumber } = req.params;
  const {
    campaignId,
    campaignName,
    targetContacts,
    campaignType,
    customMessage,
    templateId,
    templateIdA,
    templateIdB,
    abSplit,
    flowId,
    isABTest,
    useAttributes,
    trackingEnabled,
    scheduleDateTime,
    timezone,
  } = req.body;



  try {
    const contacts = targetContacts || [];

    if (contacts.length === 0) {
      return res.status(400).json({
        success: false,
        message: "No contacts provided"
      });
    }

    const nodeScheduleId = `campaign_${campaignId}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

    // Split contacts for A/B testing
    let contactsA = [];
    let contactsB = [];

    if (isABTest && abSplit && templateIdA && templateIdB) {
      const splitPercentage = parseInt(abSplit) || 50;
      const splitIndex = Math.floor((contacts.length * splitPercentage) / 100);
      
      contactsA = contacts.slice(0, splitIndex);
      contactsB = contacts.slice(splitIndex);
    }

    const campaignData = {
      id: nodeScheduleId,
      campaignId: campaignId,
      campaignName: campaignName,
      senderPhoneNumber: phoneNumber,
      // Multi-engine: per-record engine (wpcampaigns.provider); absent => legacy heuristic.
      provider: req.body.provider || null,
      campaignType: campaignType,

      targetContacts: contacts,

      isABTest: isABTest || false,
      abSplit: abSplit || 50,
      contactsA: contactsA,
      contactsB: contactsB,
      
      customMessage: customMessage || null,
      templateId: templateId || null,
      templateIdA: templateIdA || null,
      templateIdB: templateIdB || null,
      flowId: flowId || null,
      
      useAttributes: useAttributes || false,
      trackingEnabled: trackingEnabled || false,
      
      scheduleDateTime: scheduleDateTime,
      timezone: timezone || "UTC",
      
      status: "scheduled",
      type: "campaign",
      createdAt: moment().format(),
      
      sentMessages: {},
      deliveryStatus: {},
      readStatus: {},
      clickTracking: {},
      responseTracking: {},
      
      stats: {
        total: contacts.length,
        sent: 0,
        failed: 0,
        delivered: 0,
        read: 0,
        responded: 0,
        clicked: 0,
        variantA: {
          sent: 0,
          delivered: 0,
          read: 0,
          responded: 0,
          clicked: 0
        },
        variantB: {
          sent: 0,
          delivered: 0,
          read: 0,
          responded: 0,
          clicked: 0
        }
      }
    };

    app.locals.scheduledMessages.push(campaignData);

    // Create cron job
    const scheduledMoment = moment.tz(scheduleDateTime, timezone || "UTC");
    const cronExpression = `${scheduledMoment.minutes()} ${scheduledMoment.hours()} ${scheduledMoment.date()} ${scheduledMoment.month() + 1} *`;

    console.log(`[Schedule] Creating one-time job for ${nodeScheduleId}`);
    console.log(`[Schedule] Target Time: ${scheduledMoment.format()} (${timezone})`);
    console.log(`[Schedule] Cron Expression: ${cronExpression}`);

    const job = cron.schedule(
      cronExpression,
      () => {
        console.log(`[Schedule] Cron triggered for ${nodeScheduleId} at ${moment().format()}`);
        executeCampaignSchedule(
          nodeScheduleId,
          app,
          process.env.APP_DOMAIN_NAME || "http://localhost:8000"
        );
      },
      { timezone: timezone || "UTC" }
    );

    app.locals.scheduledJobs[nodeScheduleId] = job;
    console.log(`[Schedule] Job registered successfully in app.locals.scheduledJobs`);



    res.json({
      success: true,
      message: "Campaign scheduled successfully",
      scheduleId: nodeScheduleId,
      scheduledFor: scheduledMoment.format(),
      recipientCount: contacts.length,
      abTestEnabled: isABTest
    });

  } catch (error) {

    res.status(500).json({
      success: false,
      message: error.message
    });
  }
}

/**
 * Get detailed campaign analytics
 */
export async function getCampaignDetails(req, res, app) {
  const { campaignId } = req.params;

  try {
    const campaign = app.locals.scheduledMessages.find(
      (msg) => msg.campaignId === parseInt(campaignId)
    );

    if (!campaign) {
      return res.status(404).json({
        success: false,
        message: "Campaign not found"
      });
    }

    // Build detailed contact status list
    const contactDetails = campaign.targetContacts.map(contact => {
      const whatsappMsgId = Object.keys(campaign.sentMessages).find(
        msgId => campaign.sentMessages[msgId].contactId === contact.id
      );

      const variant = campaign.isABTest ? 
        (campaign.contactsA.some(c => c.id === contact.id) ? 'A' : 'B') : 
        null;

      return {
        id: contact.id,
        name: contact.name,
        phone: contact.full_phone || contact.phone,
        email: contact.email,
        
        // Variant info
        variant: variant,
        
        // Status tracking
        sent: !!whatsappMsgId,
        sentAt: campaign.sentMessages[whatsappMsgId]?.sentAt || null,
        
        delivered: campaign.deliveryStatus[contact.id]?.delivered || false,
        deliveredAt: campaign.deliveryStatus[contact.id]?.deliveredAt || null,
        
        read: campaign.readStatus[contact.id]?.read || false,
        readAt: campaign.readStatus[contact.id]?.readAt || null,
        
        clicked: campaign.clickTracking[contact.id]?.clicked || false,
        clickedAt: campaign.clickTracking[contact.id]?.clickedAt || null,
        clickCount: campaign.clickTracking[contact.id]?.count || 0,
        
        responded: campaign.responseTracking[contact.id]?.responded || false,
        respondedAt: campaign.responseTracking[contact.id]?.respondedAt || null,
        response: campaign.responseTracking[contact.id]?.response || null,
        
        // Error info
        failed: campaign.sentMessages[whatsappMsgId]?.failed || false,
        errorMessage: campaign.sentMessages[whatsappMsgId]?.error || null,
        
        whatsappMessageId: whatsappMsgId || null
      };
    });

    // Calculate engagement metrics
    const metrics = {
      deliveryRate: campaign.stats.sent > 0 ? 
        (campaign.stats.delivered / campaign.stats.sent * 100).toFixed(2) : 0,
      
      readRate: campaign.stats.delivered > 0 ? 
        (campaign.stats.read / campaign.stats.delivered * 100).toFixed(2) : 0,
      
      clickRate: campaign.stats.sent > 0 ? 
        (campaign.stats.clicked / campaign.stats.sent * 100).toFixed(2) : 0,
      
      responseRate: campaign.stats.sent > 0 ? 
        (campaign.stats.responded / campaign.stats.sent * 100).toFixed(2) : 0,
      
      // A/B test winner
      abWinner: campaign.isABTest ? determineABWinner(campaign.stats) : null
    };

    res.json({
      success: true,
      campaign: {
        id: campaign.campaignId,
        name: campaign.campaignName,
        type: campaign.campaignType,
        status: campaign.status,
        createdAt: campaign.createdAt,
        scheduledFor: campaign.scheduleDateTime || null,
        completedAt: campaign.completedAt || null,
        
        isABTest: campaign.isABTest,
        abSplit: campaign.abSplit,
        
        stats: campaign.stats,
        metrics: metrics,
        
        contactDetails: contactDetails,
        
        // Segmented lists for quick filters
        segments: {
          sent: contactDetails.filter(c => c.sent),
          failed: contactDetails.filter(c => c.failed),
          delivered: contactDetails.filter(c => c.delivered),
          read: contactDetails.filter(c => c.read),
          clicked: contactDetails.filter(c => c.clicked),
          responded: contactDetails.filter(c => c.responded),
          notDelivered: contactDetails.filter(c => c.sent && !c.delivered),
          notRead: contactDetails.filter(c => c.delivered && !c.read),
          notResponded: contactDetails.filter(c => c.read && !c.responded)
        }
      }
    });

  } catch (error) {

    res.status(500).json({
      success: false,
      message: error.message
    });
  }
}

/**
 * Determine A/B test winner based on engagement
 */
function determineABWinner(stats) {
  if (!stats.variantA.sent || !stats.variantB.sent) {
    return null;
  }

  const scoreA = (
    (stats.variantA.delivered / stats.variantA.sent) * 0.2 +
    (stats.variantA.read / stats.variantA.sent) * 0.3 +
    (stats.variantA.clicked / stats.variantA.sent) * 0.3 +
    (stats.variantA.responded / stats.variantA.sent) * 0.2
  );

  const scoreB = (
    (stats.variantB.delivered / stats.variantB.sent) * 0.2 +
    (stats.variantB.read / stats.variantB.sent) * 0.3 +
    (stats.variantB.clicked / stats.variantB.sent) * 0.3 +
    (stats.variantB.responded / stats.variantB.sent) * 0.2
  );

  if (scoreA > scoreB) {
    return {
      winner: 'A',
      score: (scoreA * 100).toFixed(2),
      improvement: ((scoreA - scoreB) / scoreB * 100).toFixed(2)
    };
  } else if (scoreB > scoreA) {
    return {
      winner: 'B',
      score: (scoreB * 100).toFixed(2),
      improvement: ((scoreB - scoreA) / scoreA * 100).toFixed(2)
    };
  }

  return { winner: 'tie', score: (scoreA * 100).toFixed(2) };
}

/**
 * Pause campaign
 */
export function pauseCampaign(req, res, app) {
  const { scheduleId } = req.params;

  try {
    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === scheduleId
    );

    if (msgIndex === -1) {
      return res.status(404).json({ 
        success: false, 
        message: "Campaign not found" 
      });
    }

    app.locals.scheduledMessages[msgIndex].status = "paused";

    // Pause normal job
    if (app.locals.scheduledJobs[scheduleId]) {
      app.locals.scheduledJobs[scheduleId].stop();
    }
    
    // Pause recurring job
    if (app.locals.scheduledJobs[`recurring_${scheduleId}`]) {
      app.locals.scheduledJobs[`recurring_${scheduleId}`].stop();
      app.locals.scheduledMessages[msgIndex].recurringActive = false;
    }


    
    res.json({ 
      success: true, 
      message: "Campaign paused" 
    });
  } catch (error) {

    res.status(500).json({ 
      success: false, 
      message: error.message 
    });
  }
}

/**
 * Resume campaign
 */
export function resumeCampaign(req, res, app) {
  const { scheduleId } = req.params;

  try {
    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === scheduleId
    );

    if (msgIndex === -1) {
      return res.status(404).json({ 
        success: false, 
        message: "Campaign not found" 
      });
    }

    app.locals.scheduledMessages[msgIndex].status = "scheduled";

    // Resume normal job
    if (app.locals.scheduledJobs[scheduleId]) {
      app.locals.scheduledJobs[scheduleId].start();
    }
    
    // Resume recurring job
    if (app.locals.scheduledJobs[`recurring_${scheduleId}`]) {
      app.locals.scheduledJobs[`recurring_${scheduleId}`].start();
      app.locals.scheduledMessages[msgIndex].recurringActive = true;
      app.locals.scheduledMessages[msgIndex].status = "active";
    }


    
    res.json({ 
      success: true, 
      message: "Campaign resumed" 
    });
  } catch (error) {

    res.status(500).json({ 
      success: false, 
      message: error.message 
    });
  }
}

/**
 * Cancel campaign
 */
export function cancelCampaign(req, res, app) {
  const { scheduleId } = req.params;

  try {
    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === scheduleId
    );

    if (msgIndex === -1) {
      return res.status(404).json({ 
        success: false, 
        message: "Campaign not found" 
      });
    }

    app.locals.scheduledMessages[msgIndex].status = "cancelled";

    // Stop normal job
    if (app.locals.scheduledJobs[scheduleId]) {
      app.locals.scheduledJobs[scheduleId].stop();
      delete app.locals.scheduledJobs[scheduleId];
    }
    
    // Stop recurring job
    if (app.locals.scheduledJobs[`recurring_${scheduleId}`]) {
      app.locals.scheduledJobs[`recurring_${scheduleId}`].stop();
      delete app.locals.scheduledJobs[`recurring_${scheduleId}`];
    }

    app.locals.scheduledMessages.splice(msgIndex, 1);


    
    res.json({ 
      success: true, 
      message: "Campaign cancelled" 
    });
  } catch (error) {

    res.status(500).json({ 
      success: false, 
      message: error.message 
    });
  }
}

/**
 * Get all campaigns for a phone number
 */
export function getCampaigns(req, res, app) {
  const { phoneNumber } = req.params;

  try {
    const campaigns = app.locals.scheduledMessages.filter(
      (msg) => msg.senderPhoneNumber === phoneNumber && msg.type === "campaign"
    );

    res.json({
      success: true,
      count: campaigns.length,
      campaigns: campaigns.map(c => ({
        id: c.id,
        campaignId: c.campaignId,
        name: c.campaignName,
        type: c.campaignType,
        status: c.status,
        isABTest: c.isABTest,
        stats: c.stats,
        createdAt: c.createdAt,
        scheduledFor: c.scheduleDateTime,
        completedAt: c.completedAt
      }))
    });
  } catch (error) {

    res.status(500).json({ 
      success: false, 
      message: error.message 
    });
  }
}

/**
 * Get campaign status
 */
export function getCampaignStatus(req, res, app) {
  const { scheduleId } = req.params;

  try {
    const campaign = app.locals.scheduledMessages.find(
      (msg) => msg.id === scheduleId
    );

    if (!campaign) {
      return res.status(404).json({ 
        success: false,
        message: "Campaign not found" 
      });
    }

    res.json({
      success: true,
      campaign: {
        id: campaign.id,
        campaignId: campaign.campaignId,
        name: campaign.campaignName,
        status: campaign.status,
        type: campaign.campaignType,
        stats: campaign.stats,
        isABTest: campaign.isABTest,
        abSplit: campaign.abSplit
      }
    });
  } catch (error) {

    res.status(500).json({ 
      success: false,
      message: error.message 
    });
  }
}

export function stopRecurringCampaign(req, res, app) {
  const { scheduleId } = req.params;

  try {
    const msgIndex = app.locals.scheduledMessages.findIndex(
      (msg) => msg.id === scheduleId
    );

    if (msgIndex === -1) {
      return res.status(404).json({ 
        success: false, 
        message: "Campaign not found" 
      });
    }

    const campaign = app.locals.scheduledMessages[msgIndex];
    
    // Stop recurring
    campaign.recurringActive = false;
    campaign.status = "stopped";

    // Stop the cron job
    const recurringJobKey = `recurring_${scheduleId}`;
    if (app.locals.scheduledJobs[recurringJobKey]) {
      app.locals.scheduledJobs[recurringJobKey].stop();
      delete app.locals.scheduledJobs[recurringJobKey];
    }


    
    res.json({ 
      success: true, 
      message: "Recurring campaign stopped successfully" 
    });
  } catch (error) {

    res.status(500).json({ 
      success: false, 
      message: error.message 
    });
  }
}

/**
 * NEW: Get recurring campaign status
 */
export function getRecurringStatus(req, res, app) {
  const { scheduleId } = req.params;

  try {
    const campaign = app.locals.scheduledMessages.find(
      (msg) => msg.id === scheduleId
    );

    if (!campaign) {
      return res.status(404).json({ 
        success: false,
        message: "Campaign not found" 
      });
    }

    res.json({
      success: true,
      campaign: {
        id: campaign.id,
        campaignId: campaign.campaignId,
        name: campaign.campaignName,
        isRecurring: campaign.isRecurring,
        recurringActive: campaign.recurringActive,
        recurringTime: campaign.recurringTime,
        status: campaign.status,
        stats: campaign.stats
      }
    });
  } catch (error) {

    res.status(500).json({ 
      success: false,
      message: error.message 
    });
  }
}

/**
 * Sync campaign schedules from Laravel (usually on startup)
 */
export async function syncCampaignSchedules(app, appDomainName) {
  console.log("🔄 Syncing campaign schedules from Laravel...");
  
  try {
    // /api/campaigns/sync is X-Node-Token gated (routes/api.php). Without the
    // header Laravel returns 401 and no scheduled campaigns are ever restored.
    const response = await axios.get(`${appDomainName}/api/campaigns/sync`, {
      timeout: 30000,
      headers: laravelHeaders(),
    });

    if (response.data && response.data.success) {
      const campaigns = response.data.campaigns || [];
      console.log(`✅ Received ${campaigns.length} campaigns to sync.`);

      for (const campaignData of campaigns) {
        try {
          // Check if already in memory
          if (app.locals.scheduledMessages.some(m => m.campaignId === campaignData.campaignId)) {
            continue;
          }

          const nodeScheduleId = `campaign_${campaignData.campaignId}_RECOVERED_${Date.now()}`;
          const campaign = {
            ...campaignData,
            id: nodeScheduleId,
            status: "scheduled",
            type: "campaign",
            createdAt: moment().format(),
            sentMessages: {},
            deliveryStatus: {},
            readStatus: {},
            clickTracking: {},
            responseTracking: {},
            stats: {
              total: campaignData.targetContacts.length,
              sent: 0, failed: 0, delivered: 0, read: 0, responded: 0, clicked: 0,
              variantA: { sent: 0, delivered: 0, read: 0, responded: 0, clicked: 0 },
              variantB: { sent: 0, delivered: 0, read: 0, responded: 0, clicked: 0 }
            }
          };

          // Handle recurring
          if (campaign.isRecurring && campaign.recurringTime) {
            campaign.recurringActive = true;
            app.locals.scheduledMessages.push(campaign);
            // Pass timezone for accurate scheduling
            setupRecurringSchedule(nodeScheduleId, campaign.recurringTime, campaign.timezone || "UTC", app);
            console.log(`   [Sync] Restored RECURRING campaign ${campaign.campaignId} at ${campaign.recurringTime} (${campaign.timezone})`);
          } 
          // Handle one-time scheduled
          else if (campaign.scheduleDateTime) {
            const scheduledMoment = moment.tz(campaign.scheduleDateTime, campaign.timezone || "UTC");
            if (scheduledMoment.isAfter(moment())) {
              app.locals.scheduledMessages.push(campaign);
              
              const cronExpression = `${scheduledMoment.minutes()} ${scheduledMoment.hours()} ${scheduledMoment.date()} ${scheduledMoment.month() + 1} *`;
              const job = cron.schedule(cronExpression, () => {
                executeCampaignSchedule(nodeScheduleId, app, appDomainName);
              }, { timezone: campaign.timezone || "UTC" });
              
              app.locals.scheduledJobs[nodeScheduleId] = job;
              console.log(`   [Sync] Restored SCHEDULED campaign ${campaign.campaignId} for ${scheduledMoment.format()}`);
            }
          }
        } catch (err) {
          console.error(`❌ [Sync] Error restoring campaign ${campaignData.campaignId}:`, err.message);
        }
      }
    }
  } catch (error) {
    logLaravelError("Sync campaigns", `${appDomainName}/api/campaigns/sync`, error);
  }
}
