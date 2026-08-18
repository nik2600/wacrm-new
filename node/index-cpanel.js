import "./log-gate.js"; // global console gate — keep FIRST (see log-gate.js)
import express from "express";
import cors from "cors";
import fs from "fs-extra";
import { BaileysClientManager } from "./classes/BaileysClientManager.js";
import axios from "axios";
import config from "./config/config.js";
import { initializeRoutes } from "./routes/index.js";
import path from "path";
import { fileURLToPath } from "url";
import { performClientCleanup } from "./utils/cleanup.js";
import { laravelHeaders, logLaravelError } from "./utils/helpers.js";
import { syncCampaignSchedules } from "./controllers/campaignController.js";
import { syncScheduledMessages } from "./controllers/scheduleController.js";
import { startInstagramScheduler } from "./services/instagram/igScheduler.js";


const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const port = config.application.port || 3000;
const appDomainName = config.application.appDomainName || "localhost";

// ── cPanel / Passenger base-path normalize ────────────────────────────
// On cPanel "Setup Node.js App" the bridge is served under a sub-path
// (e.g. https://client.com/node). Some Passenger setups STRIP that "/node"
// prefix before the request reaches Express, some DON'T — when they don't,
// every route 404s ("Cannot GET /node/health"). Strip ONE leading "/node"
// segment here so EVERY route (/, /health, /api/...) works whether the app
// is mounted under "/node" or at the root, and direct port access
// (Laravel -> Node on the LAN/localhost) keeps working unchanged.
app.use((req, res, next) => {
  if (req.url === '/node' || req.url === '/node/') {
    req.url = '/';
  } else if (req.url.startsWith('/node/')) {
    req.url = req.url.slice(5); // drop the leading "/node"
  }
  next();
});

// ── DYNAMIC CORS ──────────────────────────────────────────────────────
// The bridge is deployed to many hosts/IPs, so the allow-list is NEVER
// hardcoded — it derives from the env the installer writes:
//   • APP_DOMAIN_NAME  (node/.env — the Laravel app URL)        → always allowed
//   • ALLOWED_ORIGINS  (node/.env — comma-separated extra hosts) → allowed
//   • any chrome-extension:// or moz-extension:// origin (the WaDesk
//     browser extension)                                         → always allowed
//   • localhost / 127.0.0.1 on any port (local dev)              → always allowed
//   • requests with no Origin (server-to-server / curl)          → allowed
// DEFAULT (no ALLOWED_ORIGINS set, or set to "*"): allow EVERY origin, so a
// fresh deploy on a new URL works immediately without editing this file. The
// X-Node-Token shared secret is the real security boundary — CORS is a
// convenience guard. Set ALLOWED_ORIGINS=https://a.com,https://b.com in
// node/.env to lock it down to an explicit list.
const envOrigins = String(process.env.ALLOWED_ORIGINS || '')
  .split(',').map((s) => s.trim()).filter(Boolean);
const restrictOrigins = envOrigins.length > 0 && !envOrigins.includes('*');
const staticAllow = new Set(
  [appDomainName, ...envOrigins].filter(Boolean).map((o) => String(o).replace(/\/+$/, ''))
);

function corsOriginCheck(origin, cb) {
  if (!origin) return cb(null, true);                                  // server-to-server / curl
  const o = String(origin).replace(/\/+$/, '');
  if (o.startsWith('chrome-extension://') || o.startsWith('moz-extension://')) return cb(null, true);
  if (!restrictOrigins) return cb(null, true);                         // permissive default — token-protected
  if (/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/i.test(o)) return cb(null, true);
  if (staticAllow.has(o)) return cb(null, true);
  console.warn(`[cors] blocked origin: ${origin} (set ALLOWED_ORIGINS / APP_DOMAIN_NAME in node/.env to allow it)`);
  return cb(null, false);
}

// Middleware
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ limit: '50mb', extended: true }));
app.use(cors({
  origin: corsOriginCheck,
  methods: ["GET", "POST", "PUT", "DELETE"],
  allowedHeaders: ["Content-Type", "Authorization", "X-Node-Token", "X-Requested-With"],
}));

// App locals for shared state with proper isolation
app.locals.scheduledMessages = [];
app.locals.scheduledJobs = {};
app.locals.clients = {}; // { phoneNumber: sock }
app.locals.client_ready = {}; // { phoneNumber: boolean }
app.locals.activeFlowSessions = {}; // { "phoneNumber_userNumber": session }
app.locals.clientManagers = {}; // { phoneNumber: BaileysClientManager }
app.locals.connectionLocks = {}; // Prevent duplicate connections
app.locals.userCooldowns = {}; // User cooldown tracking
app.locals.appDomainName = appDomainName;

// Expose app.locals globally so utility modules (e.g. helpers.js fallback
// branch) can read boot-time settings without an app reference. Used as
// a last-resort source for branding_footer when per-send Laravel calls
// time out (e.g. under XAMPP single-thread contention).
global.appLocals = app.locals;

// WhatsApp Settings Cache
app.locals.whatsappSettings = {
  use_facebook_api: false,
  facebook_phone_id: null,
  facebook_api_token: null,
  branding_footer: null,
  last_fetched: null
};

// 🆕 Message Timing & Batch Settings Cache
app.locals.messageSettings = {
  msg_gap: 3, // seconds between messages (default)
  enable_batches: 0, // batch processing enabled/disabled
  batches_gap: 50, // messages per batch (default)
  bw_msg_gap: 5, // minutes between batches (default)
  last_fetched: null
};

/**
 * Fetch WhatsApp settings from Laravel
 */
async function fetchWhatsAppSettings() {
  try {
    console.log("⚙️ Fetching WhatsApp settings from Laravel...");

    const response = await axios.get(`${appDomainName}/api/whatsapp-settings`, {
      timeout: 50000,
      // /api/whatsapp-settings now requires X-Node-Token (H4 security
      // hardening). Without it Laravel returns 403 and Node falls back
      // to default settings — meaning no WABA creds + no branding_footer
      // get loaded, so footer injection silently no-ops on outbound sends.
      headers: laravelHeaders(),
    });

    // The endpoint doesn't return a `success: true` key — it just returns
    // the settings object directly. Use presence of the known shape to
    // decide "loaded vs unreachable". Empty / null response → defaults.
    if (response.data && typeof response.data === 'object'
        && ('use_facebook_api' in response.data || 'whatsapp_business_api' in response.data)) {
      // AI keys (openai/gemini) used to come through this endpoint but
      // now live in admin_ai_keys per workspace and are fetched on-demand
      // by AiAgentService — kept out of here to avoid stale-cache risks.
      app.locals.whatsappSettings = {
        use_facebook_api: response.data.use_facebook_api || false,
        facebook_phone_id: response.data.facebook_phone_id,
        facebook_api_token: response.data.facebook_api_token,
        branding_footer: response.data.branding_footer || null,
        last_fetched: new Date().toISOString()
      };

      console.log("✅ WhatsApp settings loaded:", {
        use_facebook_api: app.locals.whatsappSettings.use_facebook_api,
        has_credentials: !!(app.locals.whatsappSettings.facebook_phone_id && app.locals.whatsappSettings.facebook_api_token),
        branding_footer: app.locals.whatsappSettings.branding_footer ?? '(none)',
      });
    } else {
      console.warn("⚠️ WhatsApp settings response had unexpected shape, using defaults (Baileys)");
    }
  } catch (error) {
    logLaravelError("WhatsApp settings", `${appDomainName}/api/whatsapp-settings`, error);
    console.log("ℹ️ Using default settings (Baileys API)");
  }
}

/**
 * 🆕 Fetch Message Timing & Batch Settings from Laravel
 */
async function fetchMessageSettings() {
  try {
    console.log("⚙️ Fetching message timing & batch settings from Laravel...");

    const response = await axios.get(`${appDomainName}/api/whatsapp-message-settings`, {
      timeout: 5000,
      headers: laravelHeaders(),
    });

    if (response.data && response.data.success) {
      app.locals.messageSettings = {
        msg_gap: response.data.msg_gap || 3,
        enable_batches: response.data.enable_batches || 0,
        batches_gap: response.data.batches_gap || 50,
        bw_msg_gap: response.data.bw_msg_gap || 5,
        last_fetched: new Date().toISOString()
      };

      console.log("✅ Message settings loaded:", {
        msg_gap: `${app.locals.messageSettings.msg_gap}s`,
        enable_batches: app.locals.messageSettings.enable_batches === 1 ? 'Yes' : 'No',
        batches_gap: `${app.locals.messageSettings.batches_gap} messages/batch`,
        bw_msg_gap: `${app.locals.messageSettings.bw_msg_gap} min`
      });
    } else {
      console.warn("⚠️ Failed to load message settings, using defaults");
    }
  } catch (error) {
    logLaravelError("Message settings", `${appDomainName}/api/whatsapp-message-settings`, error);
    console.log("ℹ️ Using default message settings");
  }
}

// Initialize routes
initializeRoutes(app);

// Root route
app.get("/", async (req, res) => {
  res.send({
    message: "Server Working Fine with Baileys",
    status: 200,
    version: "@itsukichan/baileys",
    activeClients: Object.keys(app.locals.clients).length,
    activeSessions: Object.keys(app.locals.activeFlowSessions).length,
    connectionLocks: Object.keys(app.locals.connectionLocks).length,
    whatsappApi: app.locals.whatsappSettings.use_facebook_api ? "Facebook" : "Baileys",
    messageSettings: {
      msg_gap: `${app.locals.messageSettings.msg_gap}s`,
      batch_enabled: app.locals.messageSettings.enable_batches === 1,
      messages_per_batch: app.locals.messageSettings.batches_gap,
      batch_gap: `${app.locals.messageSettings.bw_msg_gap}min`
    }
  });
});

// Health check route
app.get("/health", (req, res) => {
  const stats = {
    status: "healthy",
    uptime: process.uptime(),
    timestamp: new Date().toISOString(),
    activeDevices: Object.keys(app.locals.clients).length,
    readyDevices: Object.values(app.locals.client_ready).filter(Boolean).length,
    activeFlowSessions: Object.keys(app.locals.activeFlowSessions).length,
    activeCooldowns: Object.keys(app.locals.userCooldowns).length,
    connectionLocks: Object.keys(app.locals.connectionLocks).length,
    scheduledMessages: app.locals.scheduledMessages.length,
    memoryUsage: process.memoryUsage(),
    whatsappSettings: {
      api: app.locals.whatsappSettings.use_facebook_api ? "Facebook" : "Baileys",
      lastFetched: app.locals.whatsappSettings.last_fetched
    },
    messageSettings: {
      msg_gap: app.locals.messageSettings.msg_gap,
      enable_batches: app.locals.messageSettings.enable_batches,
      batches_gap: app.locals.messageSettings.batches_gap,
      bw_msg_gap: app.locals.messageSettings.bw_msg_gap,
      lastFetched: app.locals.messageSettings.last_fetched
    }
  };
  res.json(stats);
});

// 🆕 Refresh ALL settings endpoint
app.get("/api/refresh-settings", async (req, res) => {
  try {
    await Promise.all([
      fetchWhatsAppSettings(),
      fetchMessageSettings()
    ]);

    res.json({
      success: true,
      message: "All settings refreshed successfully",
      whatsappSettings: {
        use_facebook_api: app.locals.whatsappSettings.use_facebook_api,
        last_fetched: app.locals.whatsappSettings.last_fetched
      },
      messageSettings: {
        msg_gap: app.locals.messageSettings.msg_gap,
        enable_batches: app.locals.messageSettings.enable_batches,
        batches_gap: app.locals.messageSettings.batches_gap,
        bw_msg_gap: app.locals.messageSettings.bw_msg_gap,
        last_fetched: app.locals.messageSettings.last_fetched
      }
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: "Failed to refresh settings",
      error: error.message
    });
  }
});

// 🆕 Get current message settings (for debugging)
app.get("/api/current-message-settings", (req, res) => {
  res.json({
    success: true,
    settings: {
      msg_gap: app.locals.messageSettings.msg_gap,
      enable_batches: app.locals.messageSettings.enable_batches,
      batches_gap: app.locals.messageSettings.batches_gap,
      bw_msg_gap: app.locals.messageSettings.bw_msg_gap,
      bw_msg_gap_ms: app.locals.messageSettings.bw_msg_gap * 60 * 1000, // in milliseconds
      last_fetched: app.locals.messageSettings.last_fetched
    }
  });
});

// Get current WhatsApp settings (for debugging)
app.get("/api/current-settings", (req, res) => {
  res.json({
    success: true,
    settings: {
      use_facebook_api: app.locals.whatsappSettings.use_facebook_api,
      has_facebook_credentials: !!(app.locals.whatsappSettings.facebook_phone_id && app.locals.whatsappSettings.facebook_api_token),
      last_fetched: app.locals.whatsappSettings.last_fetched
    }
  });
});

// Cleanup connection locks every 5 minutes
setInterval(() => {
  const now = Date.now();
  const lockKeys = Object.keys(app.locals.connectionLocks);

  lockKeys.forEach(phoneNumber => {
    const lockTime = app.locals.connectionLocks[phoneNumber];
    if (now - lockTime > 300000) {
      console.log(`🧹 Removing stale connection lock for: ${phoneNumber}`);
      delete app.locals.connectionLocks[phoneNumber];
    }
  });
}, 5 * 60 * 1000);

// Cleanup inactive flow sessions every 30 minutes
setInterval(() => {
  const now = Date.now();
  const sessionKeys = Object.keys(app.locals.activeFlowSessions);

  sessionKeys.forEach(key => {
    const session = app.locals.activeFlowSessions[key];
    const sessionAge = now - new Date(session.startedAt).getTime();

    if (
      sessionAge > 3600000 ||
      (session.status === 'completed' && sessionAge > 300000) ||
      session.status === 'timeout'
    ) {
      console.log(`🧹 Cleaning up old session: ${key} (status: ${session.status})`);

      if (session.timeoutTimer) {
        clearTimeout(session.timeoutTimer);
      }

      delete app.locals.activeFlowSessions[key];
    }
  });

  console.log(`🧹 Session cleanup completed. Active sessions: ${Object.keys(app.locals.activeFlowSessions).length}`);
}, 30 * 60 * 1000);

// Cleanup expired cooldowns every 10 minutes
setInterval(() => {
  const now = Date.now();
  const cooldownKeys = Object.keys(app.locals.userCooldowns);

  cooldownKeys.forEach(key => {
    const cooldown = app.locals.userCooldowns[key];
    if (now > cooldown.endTime) {
      console.log(`🧹 Removing expired cooldown for: ${key}`);
      delete app.locals.userCooldowns[key];
    }
  });

  console.log(`🧹 Cooldown cleanup completed. Active cooldowns: ${Object.keys(app.locals.userCooldowns).length}`);
}, 10 * 60 * 1000);

// 🆕 Refresh both WhatsApp and Message settings every 1 hour
setInterval(async () => {
  console.log("🔄 Auto-refreshing all settings...");
  await fetchWhatsAppSettings();
  await fetchMessageSettings();
}, 60 * 60 * 1000);

// Error handling middleware
app.use((err, req, res, next) => {
  console.error('❌ Server Error:', err);
  res.status(500).json({
    error: 'Internal Server Error',
    message: err.message
  });
});

// Server shutdown handler.
// Uses softDisconnect (via performClientCleanup) so the WhatsApp
// pairing survives across restarts. We listen on both SIGINT (Ctrl+C)
// and SIGTERM (kill / service stop / nodemon reload).
let __shuttingDown = false;
async function gracefulShutdown(signal) {
  if (__shuttingDown) return;
  __shuttingDown = true;
  console.log(`\n🛑 ${signal} received — shutting down (sessions will be preserved)...`);

  const clientNumbers = Object.keys(app.locals.clients);
  for (const phoneNumber of clientNumbers) {
    try {
      await performClientCleanup(phoneNumber, app.locals);
      console.log(`✅ Soft-disconnected: ${phoneNumber}`);
    } catch (error) {
      console.error(`❌ Error cleaning up ${phoneNumber}:`, error);
    }
  }

  const sessionKeys = Object.keys(app.locals.activeFlowSessions);
  sessionKeys.forEach(key => {
    const session = app.locals.activeFlowSessions[key];
    if (session && session.timeoutTimer) {
      clearTimeout(session.timeoutTimer);
    }
  });

  console.log("👋 Server shut down gracefully — auto-restore on next startup");
  process.exit(0);
}

process.on("SIGINT",  () => gracefulShutdown("SIGINT"));
process.on("SIGTERM", () => gracefulShutdown("SIGTERM"));

process.on('uncaughtException', (error) => {
  console.error('❌ Uncaught Exception:', error);
});

process.on('unhandledRejection', (reason, promise) => {
  console.error('Unhandled Rejection at:', promise, 'reason:', reason);
});

// Start server
app.listen(port, async () => {
  console.log(`Baileys Server running on port ${port}`);
  console.log(`Using @itsukichan/baileys for WhatsApp connection`);
  console.log(`Native support for: Buttons, Lists, Polls`);
  console.log(`Multi-user flow sessions enabled with isolation`);
  console.log(`Connection lock system enabled`);
  console.log(`Timeout & Cooldown system active`);
  console.log(`API Domain: ${appDomainName}`);

// Restore sessions function
async function restoreSessions() {
  console.log("\nRestoring active sessions...");
  try {
    const authPath = path.join(__dirname, "baileys_auth");

    // Ensure auth directory exists
    if (!fs.existsSync(authPath)) {
      console.log("No auth directory found. Skipping restoration.");
      return;
    }

    const files = await fs.readdir(authPath);
    const sessionFolders = files.filter(f => f.startsWith("session_"));

    console.log(`Found ${sessionFolders.length} session folders in ${authPath}`);

    // A healthy Baileys multi-file auth has the credentials file + many
    // sender / pre-key / app-state files (typically 5+). Folders with just
    // creds.json (or fewer) mean the previous run was logged out by the
    // phone OR pairing never finished — restoring them would just pop a
    // QR every boot. Skip those and report the device as disconnected to
    // Laravel; QR generation now requires an explicit re-pair from the UI.
    const HEALTHY_MIN_FILES = 3;
    const sessionsToRestore = [];
    const sessionsToMarkDead = [];
    for (const f of sessionFolders) {
      try {
        const inner = await fs.readdir(path.join(authPath, f));
        const phone = f.replace("session_", "");
        if (inner.length < HEALTHY_MIN_FILES) {
          console.log(`  └ ${f} (${inner.length} files — incomplete, will mark disconnected)`);
          sessionsToMarkDead.push(phone);
        } else {
          console.log(`  └ ${f} (${inner.length} files)`);
          sessionsToRestore.push(phone);
        }
      } catch (e) {
        console.log(`  └ ${f} (unreadable: ${e?.message})`);
      }
    }

    // Report incomplete sessions as Disconnected so the /devices UI
    // shows the right state. No QR fires until the operator clicks
    // re-pair (which goes through a different code path entirely).
    const { updateStatusInLaravel } = await import("./utils/helpers.js");
    for (const phone of sessionsToMarkDead) {
      try {
        await updateStatusInLaravel("Disconnected", 0, phone, appDomainName);
        console.log(`[${phone}] marked Disconnected (session needs re-pair from /devices)`);
      } catch (e) {
        console.warn(`[${phone}] couldn't post Disconnected: ${e?.message}`);
      }
    }

    let restoredCount = 0;

    for (const phoneNumber of sessionsToRestore) {
      try {
        // Skip if already initialized (shouldn't happen on startup, but safe)
        if (app.locals.clients[phoneNumber]) continue;

        console.log(`Restoring session for: ${phoneNumber}`);

        const client = new BaileysClientManager(phoneNumber, app.locals, appDomainName);
        await client.start();

        restoredCount++;

        // Stagger connections to prevent CPU spikes (2 seconds delay)
        await new Promise(resolve => setTimeout(resolve, 2000));

      } catch (err) {
        console.error(`Failed to restore session ${phoneNumber}:`, err.message);
      }
    }

    console.log(`Restored ${restoredCount}/${sessionsToRestore.length} healthy sessions (${sessionsToMarkDead.length} marked disconnected).\n`);

    // Pre-warm the per-phone settings cache. Boot runs BEFORE any
    // browser/chat polling, so Laravel responds fast (no Apache worker
    // contention). After this loop, getWhatsAppSettings({phone}) reads
    // from cache for the next 60s — per-send Laravel calls don't block
    // the WhatsApp send even when XAMPP is single-threaded under load.
    if (sessionsToRestore.length > 0) {
      console.log(`Pre-warming settings cache for ${sessionsToRestore.length} device(s)...`);
      const { getWhatsAppSettings } = await import("./utils/helpers.js");
      for (const phone of sessionsToRestore) {
        try {
          const s = await getWhatsAppSettings(appDomainName, { phone });
          console.log(`  └ ${phone}: footer=${s?.branding_footer === null ? 'null' : `"${s?.branding_footer}"`} use_waba=${!!s?.use_facebook_api}`);
        } catch (e) {
          console.warn(`  └ ${phone}: pre-warm failed — ${e?.message || e}`);
        }
      }
    }

  } catch (error) {
    console.error("Critical error during session restoration:", error);
  }
}

// Background refresh — every 5 minutes re-fetch settings for every
// connected phone. Runs lockless from a setInterval, so even if any
// individual call times out under load, the next tick recovers.
function startSettingsRefreshLoop() {
  const REFRESH_MS = 5 * 60 * 1000;
  setInterval(async () => {
    try {
      const phones = Object.keys(app.locals.clients || {});
      if (phones.length === 0) return;
      const { getWhatsAppSettings } = await import("./utils/helpers.js");
      for (const phone of phones) {
        try {
          await getWhatsAppSettings(appDomainName, { phone });
        } catch (e) { /* timeouts return cached stale, no action needed */ }
      }
    } catch (e) {
      console.warn(`[refresh-loop] tick failed: ${e?.message || e}`);
    }
  }, REFRESH_MS);
}

// Heartbeat loop — every 30s, POST the live socket list to Laravel
// so devices.last_seen_at stays fresh and any socket that crashed
// silently in Node (without firing connection.update) gets flipped
// to disconnected on the next tick. Without this, a Node-side crash
// leaves Laravel showing "connected" forever.
function startDeviceHeartbeat() {
  const HEARTBEAT_MS = 30 * 1000;
  const axios = (typeof globalThis.axios !== 'undefined') ? globalThis.axios : null;
  setInterval(async () => {
    try {
      const phones = Object.keys(app.locals.clients || {});
      // NOTE: the heartbeat (and therefore the scheduled-campaign sweeper)
      // only runs when at least one device is CONNECTED. If no device is
      // live, no heartbeat is sent and scheduled campaigns will NOT fire.
      if (phones.length === 0) { console.warn('[heartbeat] skip — no clients registered'); return; }
      const live = phones
        .filter((p) => app.locals.client_ready?.[p])
        .map((p) => ({ wid: p, status: 'connected' }));
      if (live.length === 0) { console.warn(`[heartbeat] skip — ${phones.length} client(s) but none ready/connected`); return; }
      const { default: ax } = await import('axios');
      const { laravelHeaders } = await import('./utils/helpers.js');

      // ----------------------------------------------------------------
      // DEEP HEARTBEAT DEBUG LOGGING — temporary, for diagnosing why
      // scheduled campaigns don't fire. The heartbeat drives the Laravel
      // CampaignScheduleSweeper, so a 403 here means the sweeper never runs.
      // Quiet this block (back to one console.warn) once scheduling is
      // confirmed working.
      // ----------------------------------------------------------------
      const hbHeaders = laravelHeaders();
      const hbToken   = hbHeaders['X-Node-Token'];
      const tokenInfo = hbToken
        ? `${String(hbToken).slice(0, 4)}…(${String(hbToken).length} chars)`
        : 'NONE — env NODE_WEBHOOK_TOKEN is EMPTY on the Node side!';
      console.warn(`[heartbeat] -> POST ${appDomainName}/api/node-heartbeat | devices=${live.length} | X-Node-Token=${tokenInfo}`);

      const hbRes = await ax.post(`${appDomainName}/api/node-heartbeat`,
        { devices: live },
        { headers: hbHeaders, timeout: 5000 }
      );
      console.warn(`[heartbeat] OK ${hbRes.status} | resp=${JSON.stringify(hbRes.data)}`);
    } catch (e) {
      // 404 means the route isn't deployed yet on the Laravel side — ignore.
      if (e?.response?.status === 404) return;
      // Everything else -> console.error so it lands in wadesk-node-error.log
      // with the FULL status + response body for inspection.
      const st   = e?.response?.status;
      const body = e?.response?.data ? JSON.stringify(e.response.data) : '(no response body)';
      console.error(`[heartbeat] FAILED status=${st ?? 'n/a'} | msg=${e?.message || e}`);
      console.error(`[heartbeat]   -> response body: ${body}`);
      if (st === 401 || st === 403) {
        console.error('[heartbeat]   -> 401/403 = X-Node-Token did NOT match Laravel. Set NODE_WEBHOOK_TOKEN to the SAME value in BOTH the Laravel .env and the Node env, then `php artisan config:clear` + `pm2 restart 41 --update-env`. SCHEDULED CAMPAIGNS WILL NOT FIRE until this heartbeat returns 200.');
      }
    }
  }, HEARTBEAT_MS);
}

  // Fetch all settings on startup
  console.log("\nInitializing settings...");
  await fetchWhatsAppSettings();
  await fetchMessageSettings();

  // Restore sessions after settings are loaded
  await restoreSessions();

  // Sync campaign schedules from Laravel
  await syncCampaignSchedules(app, appDomainName);

  // Re-register active scheduled messages from Laravel after a restart.
  // Without this, every pm2 restart silently loses every pending schedule
  // because app.locals.scheduledMessages / scheduledJobs is in-memory only.
  await syncScheduledMessages(app, appDomainName);

  // Background refresh — keeps per-phone settings cache warm so the
  // /chat hot path never blocks on Laravel even under XAMPP contention.
  startSettingsRefreshLoop();

  // Heartbeat — touches devices.last_seen_at every 30s so the team-
  // inbox UI's "device offline" badge reflects reality instead of
  // showing the last known state from when Node crashed.
  startDeviceHeartbeat();

  console.log("\nServer is ready to handle requests\n");
});
