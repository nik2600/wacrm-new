// classes/BaileysClientManager.js
// ==============================
// Baileys Client Manager - Complete with QR & Pairing Code Support
// ==============================

import {
  makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  Browsers,
  makeInMemoryStore,
  fetchLatestBaileysVersion,
  fetchLatestWaWebVersion,
  downloadMediaMessage
} from "@itsukichan/baileys";
import P from "pino";
import { Boom } from "@hapi/boom";
import fs from "fs-extra";
import path from "path";
import { fileURLToPath } from "url";
import { randomBytes } from "crypto";
import axios from "axios";
import qrcode from "qrcode-terminal";
import moment from "moment-timezone";
// Per-number proxy / IP isolation. Each Baileys number can egress through its
// own proxy so accounts on one server don't share a single IP (lowers the
// "association ban" risk for bulk sending). Both agents already in package.json.
import { HttpsProxyAgent } from "https-proxy-agent";
import { SocksProxyAgent } from "socks-proxy-agent";
import { updateStatusInLaravel, formatPhoneNumber, laravelHeaders } from "../utils/helpers.js";
// laravelHeaders() returns {'X-Node-Token': <env shared secret>} — every
// outbound call from this file to ${appDomainName} must spread it into
// its `headers` so the Laravel route's X-Node-Token gate (added 2026)
// doesn't 401 us.
import { executeFlowNode, handleFlowResponse } from "../services/flowService.js";
import { handleCampaignMessageUpdate, trackCampaignResponse, fetchWorkspaceAttributes, mergeFlowVariables } from '../services/campaignService.js';
// Per-device send serialization. Wrapping the socket here makes EVERY
// sock.sendMessage call on this device run one-at-a-time, which stops
// concurrent flow / keyword-reply triggers from racing Baileys' Signal
// handshake (the cause of "flow replies to only one number when several
// message at once"). See serializeSocketSends() for the full rationale.
import { serializeSocketSends } from "../utils/sendSafety.js";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

let cachedWaVersion = null;
let cachedWaVersionAt = 0;
async function getWaVersion() {
  const now = Date.now();
  if (cachedWaVersion && (now - cachedWaVersionAt) < 60 * 60 * 1000) {
    return cachedWaVersion;
  }
  try {
    const r = await fetchLatestWaWebVersion();
    if (r?.version) {
      cachedWaVersion = r.version;
      cachedWaVersionAt = now;
      console.log(`[Baileys] Using WhatsApp Web version ${r.version.join('.')} (latest=${r.isLatest !== false})`);
      return cachedWaVersion;
    }
  } catch (e) {
    console.warn(`[Baileys] fetchLatestWaWebVersion failed:`, e?.message || e);
  }
  try {
    const r = await fetchLatestBaileysVersion();
    if (r?.version) {
      cachedWaVersion = r.version;
      cachedWaVersionAt = now;
      console.log(`[Baileys] Using bundled WA version ${r.version.join('.')} (isLatest=${r.isLatest})`);
      return cachedWaVersion;
    }
  } catch (e) {
    console.warn(`[Baileys] fetchLatestBaileysVersion failed:`, e?.message || e);
  }
  return null;
}

// The @itsukichan/baileys fork hardcodes requestPairingCode()'s default code to
// "SUK1CH4N" when no code is passed (lib/Socket/socket.js), so EVERY number got
// the exact same pairing code. We generate our own instead — the SAME way stock
// baileys does its random default (Crockford base32 of 5 random bytes → 8 chars)
// — and pass it in, so each request yields a unique code.
const CROCKFORD_CHARACTERS = "123456789ABCDEFGHJKLMNPQRSTVWXYZ";
function randomPairingCode() {
  const buffer = randomBytes(5);
  let value = 0, bitCount = 0, out = "";
  for (const element of buffer) {
    value = (value << 8) | (element & 0xff);
    bitCount += 8;
    while (bitCount >= 5) {
      out += CROCKFORD_CHARACTERS.charAt((value >>> (bitCount - 5)) & 31);
      bitCount -= 5;
    }
  }
  if (bitCount > 0) out += CROCKFORD_CHARACTERS.charAt((value << (5 - bitCount)) & 31);
  return out;
}

export class BaileysClientManager {
  constructor(phoneNumber, appLocals, appDomainName) {
    this.phoneNumber = phoneNumber;
    this.sock = null;
    this.logger = P({ level: process.env.BAILEYS_LOG_LEVEL || "warn" });
    this.appLocals = appLocals;
    this.appDomainName = appDomainName;
    this.authFolder = path.join(
      __dirname,
      "..",
      "baileys_auth",
      `session_${phoneNumber}`
    );
    // Per-number proxy / IP isolation. `this.proxy` = {enabled,type,host,port,
    // username,password} (set by setProxy() from Laravel's connect payload, OR
    // loaded from <authFolder>/proxy.json on auto-reconnect). `this.proxyAgent`
    // is the built agent passed to makeWASocket({agent, fetchAgent}).
    this.proxy = null;
    this.proxyAgent = null;
    this.qrCode = null;
    this.pairingCode = null;  // For pairing code
    this.usePairingCode = false;  // Flag for pairing code mode
    this.connectionRetries = 0;
    // Bumped from 10 → 50. A long-running session naturally accumulates
    // transient closes (network blips, keepalive misses, WhatsApp side
    // restarts). Hitting the cap means a paired phone goes offline
    // permanently until manual reconnect — bad UX. Real "permanent"
    // failures (loggedOut, 401, badSession) bypass the counter via
    // shouldStopReconnecting, so a high cap only helps recoverable cases.
    this.maxRetries = 50;
    // Last reconnect attempt timestamp — used for exponential backoff
    // with jitter so we don't hammer WhatsApp on a flapping link.
    this.lastReconnectAt = 0;
    this.isQRScanned = false;
    this.lastQRTime = 0;
    this.nextConnectStatus = null;
    this.isReconnecting = false;
    this.shouldStayConnected = true;
    this.isConnecting = false;
    this.isConnected = false;
    // Has this manager ever reached `connection: open` since the
    // current start() call? Lets the close handler distinguish a
    // failed first-time pair (don't retry — just give up loudly)
    // from a normal post-pair disconnect (do try to reconnect).
    this.hasEverConnected = false;
    // Last QR shown to the user. We log it (digest only, not the
    // full payload) on close so it's clear when a close fired
    // because a QR was never scanned vs. because pairing was
    // rejected after scanning.
    this.qrShownAt = 0;
    // FIRST QR of the current pair attempt. The stall watchdog measures age
    // from THIS — not qrShownAt, which is overwritten on every ~20s rotation,
    // so age never crossed the 90s threshold and the QR looped forever.
    this.qrFirstShownAt = 0;

    // Store setup
    this.store = makeInMemoryStore({
      logger: this.logger
    });

    this.storeFile = path.join(this.authFolder, 'baileys_store.json');

    if (!this.appLocals.userCooldowns) {
      this.appLocals.userCooldowns = {};
    }

    this.storeInterval = null; // Track store save interval
  }

  /**
   * Truncate a status string to fit Laravel's 32-char cap on the
   * `/api/update-status` payload. Without this, descriptive reason
   * labels like "Pair failed: connection replaced — …" cause a 422
   * even though they'd be useful in the Node terminal log.
   */
  shortStatus(s) {
    s = String(s || '');
    return s.length <= 32 ? s : s.slice(0, 29) + '...';
  }

  /**
   * Decode the Boom statusCode + message Baileys produces on close
   * into a human-readable reason. Used by the logger so we don't
   * have to manually look up Baileys' DisconnectReason enum every
   * time a pair fails.
   */
  decodeCloseReason(statusCode, message) {
    const msg = String(message || '').toLowerCase();
    switch (statusCode) {
      case 401: return 'unauthorized — device unlinked on WhatsApp or session invalidated';
      case 403: return 'forbidden — WhatsApp refused the link (account flagged / version too old / region restricted)';
      case 408: return 'timeout — handshake stalled (phone offline or network slow)';
      case 428: return 'connection replaced — another WhatsApp Web session took over';
      case 440: return 'session conflict — same device id paired elsewhere';
      case 500: return 'WhatsApp internal error — retry usually helps';
      case 515: return 'stream restart required (post-pair sync) — Baileys reconnects automatically';
      case DisconnectReason?.loggedOut: return 'WhatsApp logged this device out';
      case DisconnectReason?.badSession: return 'corrupted session files — auth folder needs wipe';
      case DisconnectReason?.connectionClosed: return 'connection closed cleanly';
      case DisconnectReason?.connectionLost: return 'connection lost (network)';
      case DisconnectReason?.restartRequired: return 'restart required (515 stream error)';
      case DisconnectReason?.multideviceMismatch: return 'multi-device mismatch — WhatsApp refused the link';
    }
    if (msg.includes('couldn\'t link') || msg.includes('couldnt link')) return 'WhatsApp refused to link — likely 4-device limit reached on this number';
    if (msg.includes('qr ref')) return 'QR refreshed before scan completed';
    if (msg.includes('timed out')) return 'pair handshake timed out (phone slow or never scanned)';
    return `unrecognised close reason: ${message || 'unknown'}`;
  }

  /**
   * Tear down the current sock + its event listeners cleanly. Called
   * before `start()` / `startWithPairingCode()` when the operator clicks
   * Reconnect / Get pairing code while a stale sock is in memory.
   *
   * Without this, a second call reuses the old socket: Baileys's
   * `requestPairingCode()` returns the SAME 8-char code it already
   * minted (one-shot per socket lifetime), and the connection.update
   * listener's `pairingCodeRequested` closure-flag stays true so the
   * code never re-fires. Same root cause makes Reconnect → QR show the
   * blank/broken QR — no new QR event ever fires from the live socket.
   *
   * Safe to call when `this.sock` is null/undefined.
   */
  async resetForFreshAuth() {
    try {
      if (this.sock) {
        // Strip all listeners so the in-flight handler doesn't fire
        // when we end the socket below (which emits 'close').
        try { this.sock.ev.removeAllListeners(); } catch (e) {}
        try { await this.sock.end(undefined); } catch (e) {}
        try { this.sock.ws?.close?.(); } catch (e) {}
      }
    } finally {
      this.sock = null;
      this.qrCode = null;
      this.pairingCode = null;
      this.isQRScanned = false;
      this.isConnecting = false;
      this.isConnected = false;
      this.lastQRTime = 0;
      this.qrShownAt = 0;
      this.qrFirstShownAt = 0;
      if (this.qrStallTimer) { clearInterval(this.qrStallTimer); this.qrStallTimer = null; }
      if (this.storeInterval) { clearInterval(this.storeInterval); this.storeInterval = null; }
    }
  }

  // ── Per-number proxy / IP isolation ──────────────────────────────────────
  // Build a proxy agent from {type,host,port,username,password}. socks5 →
  // SocksProxyAgent, otherwise an HTTPS proxy. Creds are URL-encoded so
  // passwords with special chars don't break the URL.
  buildProxyAgent(p) {
    const auth = p.username
      ? `${encodeURIComponent(p.username)}:${encodeURIComponent(p.password || '')}@`
      : '';
    const scheme = p.type === 'socks5' ? 'socks5' : 'http';
    const url = `${scheme}://${auth}${p.host}:${p.port}`;
    return p.type === 'socks5' ? new SocksProxyAgent(url) : new HttpsProxyAgent(url);
  }

  // Persist + remember the device's proxy. Called from the connect endpoint
  // with Laravel's payload. Writing proxy.json lets auto-reconnect (Node boot)
  // load the same proxy without a Laravel round-trip. null/disabled → cleared.
  async setProxy(proxy) {
    const f = path.join(this.authFolder, 'proxy.json');
    try {
      await fs.ensureDir(this.authFolder);
      if (proxy && proxy.enabled) {
        this.proxy = proxy;
        await fs.writeJson(f, proxy);
      } else {
        this.proxy = null;
        if (await fs.pathExists(f)) await fs.remove(f);
      }
    } catch (e) {
      this.proxy = (proxy && proxy.enabled) ? proxy : null;
      console.warn(`[${this.phoneNumber}] [PROXY] setProxy persist failed: ${e?.message || e}`);
    }
  }

  // Resolve the proxy at start()/reconnect time. Laravel's DB is the source of
  // truth, so we FETCH the device's proxy from Laravel (X-Node-Token gated, creds
  // decrypted server-side just for this call) and cache it to proxy.json. If
  // Laravel is unreachable (e.g. Node booted before the web app), fall back to
  // the last cached proxy.json so a known-good proxy still applies on reconnect.
  async loadProxy() {
    try {
      const resp = await axios.get(
        `${this.appDomainName}/api/devices/proxy-config/${encodeURIComponent(this.phoneNumber)}`,
        { headers: { ...laravelHeaders() }, timeout: 10000 }
      );
      const cfg = resp?.data?.proxy ?? resp?.data ?? null;
      this.proxy = (cfg && cfg.enabled) ? cfg : null;
      // cache for the offline-reconnect fallback
      await this.setProxy(this.proxy);
      return;
    } catch (e) {
      console.warn(`[${this.phoneNumber}] [PROXY] Laravel config fetch failed (${e?.message || e}) — using cached proxy.json`);
    }
    try {
      const f = path.join(this.authFolder, 'proxy.json');
      if (await fs.pathExists(f)) {
        const raw = await fs.readJson(f);
        this.proxy = (raw && raw.enabled) ? raw : null;
      } else {
        this.proxy = null;
      }
    } catch (e) {
      console.warn(`[${this.phoneNumber}] [PROXY] could not read proxy.json: ${e?.message || e}`);
    }
  }

  // Build this.proxyAgent + FAIL-CLOSED pre-flight. If a proxy is enabled we
  // probe ipify THROUGH it to (a) confirm reachability and (b) capture the
  // egress IP. On any failure we throw — start() aborts and the number is NEVER
  // linked through the server's direct IP (the whole point of isolation). No
  // proxy configured → proxyAgent stays null → unchanged direct-IP behaviour.
  async applyProxyAgent() {
    this.proxyAgent = null;
    const p = this.proxy;
    if (!p || !p.enabled) return;

    let agent;
    try {
      agent = this.buildProxyAgent(p);
    } catch (e) {
      await this.reportProxyResult('unreachable', null, `bad config: ${e?.message || e}`);
      throw new Error(`[${this.phoneNumber}] proxy config invalid — fail-closed`);
    }

    try {
      const resp = await axios.get('https://api.ipify.org?format=json', {
        httpAgent: agent, httpsAgent: agent, proxy: false, timeout: 15000,
      });
      const ip = resp?.data?.ip || null;
      this.proxyAgent = agent;
      await this.reportProxyResult('ok', ip, null);
      console.log(`[${this.phoneNumber}] [PROXY] reachable — egress IP ${ip}`);
    } catch (e) {
      await this.reportProxyResult('unreachable', null, e?.message || String(e));
      throw new Error(`[${this.phoneNumber}] proxy unreachable — fail-closed (refusing direct IP)`);
    }
  }

  // Best-effort: tell Laravel the proxy health + egress IP so the UI can show
  // it. Non-critical to the connection itself, so failures are swallowed.
  async reportProxyResult(status, egressIp, error) {
    try {
      await axios.post(`${this.appDomainName}/api/devices/proxy-result`, {
        phone_number: this.phoneNumber,
        proxy_status: status,
        proxy_egress_ip: egressIp,
        error: error || null,
      }, { headers: { ...laravelHeaders() }, timeout: 10000 });
    } catch (e) {
      console.warn(`[${this.phoneNumber}] [PROXY] result report failed: ${e?.message || e}`);
    }
  }
  // ──────────────────────────────────────────────────────────────────────────

  async start() {
    this.isConnecting = true;
    console.log(`[${this.phoneNumber}] Starting connection...`);

    // HEALTH WATCHDOG (created once) — the ultimate "stay connected until
    // logout" safety net. Every 60s: if we're meant to be online but the
    // socket is down and nothing is already bringing it back, kick a
    // reconnect. Recovers from silently-dead sockets and outages that would
    // otherwise leave the device offline until a manual reconnect.
    if (!this.healthTimer) {
      this.healthTimer = setInterval(() => {
        if (this.shuttingDown || !this.shouldStayConnected) return;
        if (this.isConnected || this.isConnecting || this.isReconnecting) return;
        if (this.reconnectTimer) return;                    // a reconnect is already scheduled
        if (this.qrCode && !this.hasEverConnected) return;  // mid first-time pairing (waiting for scan)
        console.log(`[${this.phoneNumber}] [WATCHDOG] socket down + idle → reconnecting`);
        this.start().catch((e) => console.error(`[${this.phoneNumber}] [WATCHDOG] reconnect failed: ${e?.message || e}`));
      }, 60000);
    }

    try {
      await fs.ensureDir(this.authFolder);

      // Load store from file if exists
      if (await fs.pathExists(this.storeFile)) {
        try {
          this.store.readFromFile(this.storeFile);
        } catch (error) {

        }
      }

      // Save store every 10 seconds
      if (this.storeInterval) clearInterval(this.storeInterval);
      this.storeInterval = setInterval(() => {
        try {
          this.store.writeToFile(this.storeFile);
        } catch (error) {

        }
      }, 10_000);

      // QR-stall watchdog: if a QR has been shown for 90s without
      // ever reaching `connection: open`, give up. WhatsApp's QR
      // payload itself rotates every ~20s — three rotations with no
      // scan means the user either isn't trying or WhatsApp is
      // silently refusing the pair. Either way, looping forever
      // just burns terminal real estate.
      if (this.qrStallTimer) clearInterval(this.qrStallTimer);
      this.qrStallTimer = setInterval(async () => {
        if (this.hasEverConnected) {
          clearInterval(this.qrStallTimer);
          this.qrStallTimer = null;
          return;
        }
        const age = this.qrFirstShownAt ? Date.now() - this.qrFirstShownAt : 0;
        if (this.qrFirstShownAt && age > 90_000) {
          console.error(`[${this.phoneNumber}] ⏰ QR stall — no scan in ${Math.round(age/1000)}s, aborting pair attempt.`);
          clearInterval(this.qrStallTimer);
          this.qrStallTimer = null;
          this.shouldStayConnected = false;
          this.connectionRetries = this.maxRetries;
          try { if (this.sock) await this.sock.end?.(); } catch (e) {}
          try { await this.deleteSessionFiles(); } catch (e) {}
          delete this.appLocals.clientManagers[this.phoneNumber];
          delete this.appLocals.connectionLocks[this.phoneNumber];
          await updateStatusInLaravel(this.shortStatus('Pair failed: QR timeout'), 0, this.phoneNumber, this.appDomainName);
        }
      }, 5000);

      const { state, saveCreds } = await useMultiFileAuthState(this.authFolder);
      const waVersion = await getWaVersion();

      // Per-number proxy / IP isolation. Resolve the device's proxy (from the
      // connect payload via setProxy(), or from <authFolder>/proxy.json on
      // auto-reconnect), build the agent + verify egress IP. FAIL-CLOSED: if the
      // proxy is enabled but unreachable this throws so we never link through
      // the server's direct IP (that would break the isolation guarantee).
      await this.loadProxy();
      await this.applyProxyAgent();

      this.sock = makeWASocket({
        version: waVersion || undefined,
        auth: state,
        printQRInTerminal: false,
        logger: this.logger,
        // Route BOTH the WebSocket transport AND media fetch through the same
        // proxy so the number's entire footprint uses the proxy IP (undefined
        // when no proxy → behaves exactly as before, direct server IP).
        agent: this.proxyAgent || undefined,
        fetchAgent: this.proxyAgent || undefined,
        browser: Browsers.macOS("Desktop"),
        connectTimeoutMs: 60000,
        defaultQueryTimeoutMs: 60000,
        // Tightened from 30s → 15s. WhatsApp Web's official client pings
        // every ~10s; 30s often left the server thinking we'd died and
        // closing the socket with statusCode 408 (timeout) right when the
        // operator's phone still showed the device as linked. 15s gives
        // us margin while staying inside the server's keepalive grace.
        keepAliveIntervalMs: 15000,
        retryRequestDelayMs: 3000,
        maxMsgRetryCount: 5,
        qrTimeout: 60000,
        emitOwnEvents: false,
        fireInitQueries: false,
        syncFullHistory: false,
        // Don't broadcast "online" to contacts on connect — keeps the
        // pair behavior closer to a passive linked device.
        markOnlineOnConnect: false,
        // Don't auto-sync chat history on every reconnect — heavy work
        // that delays the open event and often triggers stream resets
        // when there's a lot to sync. Bridge fetches history on demand.
        shouldSyncHistoryMessage: () => false,
        transactionOpts: {
          maxCommitRetries: 10,
          delayBetweenTriesMs: 3000
        },
        // Return null instead of a placeholder. Baileys uses the result
        // to fulfill WhatsApp's "re-send the message you sent earlier"
        // requests; a placeholder string makes WhatsApp think we sent
        // garbage and can trigger a re-decrypt loop that ends in a
        // forced close. Null tells Baileys "I can't help, drop the req".
        getMessage: async (key) => {
          return null;
        },
        generateHighQualityLinkPreview: false,
        shouldIgnoreJid: (jid) => false,
        patchMessageBeforeSending: (message) => {
          return message;
        }
      });

      this.sock.phoneNumber = this.phoneNumber;
      serializeSocketSends(this.sock, this.phoneNumber);
      this.store.bind(this.sock.ev);
      
      this.sock.ev.on("messages.update", async (updates) => {
        await this.handleMessageStatusUpdate(updates);
        await handleCampaignMessageUpdate(updates, this.appLocals, this.appDomainName);
      });

      // Save credentials. We DO NOT debounce here — every creds.update
      // after pair-success carries new key material, and skipping any
      // of them leaves the auth folder incomplete so the next Node
      // restart can't reconnect (folder ends up empty).
      this.sock.ev.on("creds.update", async () => {
        try {
          await saveCreds();
          console.log(`[${this.phoneNumber}] [CREDS] saved → ${this.authFolder}`);
          // First creds.update after pair = QR was successfully
          // scanned. Stop the QR-stall watchdog so the post-pair
          // 515 → reconnect cycle isn't killed mid-flight even if
          // it takes longer than 90s on a slow network.
          if (this.qrStallTimer) { clearInterval(this.qrStallTimer); this.qrStallTimer = null; }
          this.qrShownAt = 0;
          this.qrFirstShownAt = 0;
        } catch (error) {
          console.error(`[${this.phoneNumber}] [CREDS] save FAILED:`, error?.message || error);
          console.error(`[${this.phoneNumber}] [CREDS] target folder: ${this.authFolder}`);
        }
      });

      // Handle connection updates
      this.sock.ev.on("connection.update", (update) =>
        this.handleConnectionUpdate(update, saveCreds)
      );

      // Handle messages
      this.sock.ev.on("messages.upsert", async (m) => {
        const { messages, type } = m;
        try {
          // Track campaign responses for each incoming message first
          for (const message of messages) {
            if (!message.key.fromMe && message.message) {
              await trackCampaignResponse(message, this.appLocals, this.appDomainName);
            }
          }
          // Then handle messages normally (batch)
          await this.handleMessages({ messages, type });
        } catch (error) {
          console.error(`[${this.phoneNumber}] [MSG-UPSERT] failed: ${error?.message}`, {
            stack: error?.stack?.split('\n').slice(0, 3).join(' | '),
          });
        }
      });

      this.appLocals.clients[this.phoneNumber] = this.sock;
      this.appLocals.client_ready[this.phoneNumber] = false;
      this.sock.ev.on("group-participants.update", () => this.scheduleGroupSync(5000));
      // Newly created / newly-joined groups arrive via groups.upsert (NOT
      // participants.update), so without this a group the bot was just added
      // to wouldn't appear in the directory until the next reconnect.
      this.sock.ev.on("groups.upsert", () => this.scheduleGroupSync(5000));

      return this.sock;
    } catch (error) {
      this.isConnecting = false;
      console.error(`[${this.phoneNumber}] start() error:`, error?.message || error);

      if (this.shouldStayConnected && this.connectionRetries < this.maxRetries) {
        this.connectionRetries++;
        setTimeout(() => this.start(), 5000);
      }
    }
  }

  async startWithPairingCode() {
  this.usePairingCode = true;
  this.pairingCode = null;
  
  // Prevent duplicate connection attempts
  if (this.isConnecting || this.isConnected) {

    return this.sock;
  }

  this.isConnecting = true;

  
  try {
    await fs.ensureDir(this.authFolder);
    
    // Load store from file if exists
    if (await fs.pathExists(this.storeFile)) {
      try {
        this.store.readFromFile(this.storeFile);
      } catch (error) {

      }
    }
    
    // Save store every 10 seconds
    if (this.storeInterval) clearInterval(this.storeInterval);
    this.storeInterval = setInterval(() => {
      try {
        this.store.writeToFile(this.storeFile);
      } catch (error) {

      }
    }, 10_000);

    const { state, saveCreds } = await useMultiFileAuthState(this.authFolder);
    const waVersion = await getWaVersion();

    // Per-number proxy / IP isolation (reconnect path) — same fail-closed
    // resolution as start(); never reconnect through the direct server IP
    // when a per-number proxy is configured.
    await this.loadProxy();
    await this.applyProxyAgent();

    this.sock = makeWASocket({
      version: waVersion || undefined,
      auth: state,
      printQRInTerminal: false,
      logger: this.logger,
      agent: this.proxyAgent || undefined,
      fetchAgent: this.proxyAgent || undefined,
      browser: Browsers.macOS("Desktop"),
      connectTimeoutMs: 60000,
      defaultQueryTimeoutMs: 60000,
      keepAliveIntervalMs: 30000,
      retryRequestDelayMs: 3000,
      maxMsgRetryCount: 5,
      qrTimeout: 60000,
      emitOwnEvents: false,
      fireInitQueries: true,
      syncFullHistory: false,
      markOnlineOnConnect: false,
      transactionOpts: {
        maxCommitRetries: 10,
        delayBetweenTriesMs: 3000
      },
      getMessage: async (key) => ({ conversation: "Message not found" }),
      generateHighQualityLinkPreview: false,
    });

    this.sock.phoneNumber = this.phoneNumber;
    serializeSocketSends(this.sock, this.phoneNumber);
    this.store.bind(this.sock.ev);

    // 🔥 FIX 1: Wait for connection to be established
    let connectionEstablished = false;
    let pairingCodeRequested = false;

    // Handle connection updates BEFORE requesting pairing code
    this.sock.ev.on("connection.update", async (update) => {
      const { connection, isNewLogin } = update;
      
      // 🔥 FIX 2: Request pairing code when connection is ready
      if (connection === "open" && isNewLogin) {
        connectionEstablished = true;
      } else if (connection === "connecting" && !pairingCodeRequested && !state.creds?.registered) {
        // Wait a bit for socket to stabilize
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        try {
          // 🔥 FIX 3: Format phone number correctly (remove all non-digits)
          const cleanNumber = this.phoneNumber.replace(/\D/g, "");
          
          if (cleanNumber.length === 0) {
            throw new Error("Invalid phone number for pairing code");
          }

          
          // 🔥 FIX 4: Use the correct method and await it. Pass our OWN random
          // code as the 2nd arg — the fork otherwise defaults every code to a
          // hardcoded "SUK1CH4N" (see randomPairingCode() above).
          if (typeof this.sock.requestPairingCode === "function") {
            const code = await this.sock.requestPairingCode(cleanNumber, randomPairingCode());
            this.pairingCode = code;
            pairingCodeRequested = true;

            await updateStatusInLaravel(
              "Code generated", 
              0, 
              this.phoneNumber, 
              this.appDomainName
            );
          } else {

          }
        } catch (err) {

        }
      }
      
      // Continue with normal connection handling
      await this.handleConnectionUpdateWithPairing(update, saveCreds);
    });

    // 🔥 FIX 5: Also listen for pairing-code event (if emitted by socket)
    this.sock.ev.on("pairing-code", (code) => {

      this.pairingCode = code;
    });

    this.sock.ev.on("messages.update", async (updates) => {
      await this.handleMessageStatusUpdate(updates);
      await handleCampaignMessageUpdate(updates, this.appLocals, this.appDomainName);
    });

    this.sock.ev.on("creds.update", async () => {
      try {
        await saveCreds();
        console.log(`[${this.phoneNumber}] [CREDS-PAIR] saved → ${this.authFolder}`);
      } catch (error) {
        console.error(`[${this.phoneNumber}] [CREDS-PAIR] save FAILED:`, error?.message || error);
      }
    });

    // Handle messages
    this.sock.ev.on("messages.upsert", async (m) => {
      const { messages, type } = m;
      try {
        for (const message of messages) {
          if (!message.key.fromMe && message.message) {
            await trackCampaignResponse(message, this.appLocals, this.appDomainName);
          }
        }
        await this.handleMessages({ messages, type });
      } catch (error) {
        console.error(`[${this.phoneNumber}] [MSG-UPSERT-PAIRCODE] failed: ${error?.message}`, {
          stack: error?.stack?.split('\n').slice(0, 3).join(' | '),
        });
      }
    });

    this.appLocals.clients[this.phoneNumber] = this.sock;
    this.appLocals.client_ready[this.phoneNumber] = false;
    this.sock.ev.on("group-participants.update", () => this.scheduleGroupSync(5000));


    return this.sock;
    
  } catch (error) {
    this.isConnecting = false;

    throw error;
  }
}

  // ── WhatsApp group directory sync (Jessica P4, Unofficial API only) ──
  // Mirror the groups this bot belongs to (+ participants) into Laravel so the
  // ordering flow can find a customer's group and @mention them on confirm. We
  // only sync membership METADATA — group chat messages are NOT pulled into the
  // inbox (that filter stays in place). Debounced so a burst of participant
  // changes collapses into a single push.
  scheduleGroupSync(delayMs = 6000) {
    try {
      if (this._groupSyncTimer) clearTimeout(this._groupSyncTimer);
      this._groupSyncTimer = setTimeout(() => { this.syncGroups(); }, delayMs);
    } catch (_) { /* timer best-effort */ }
  }

  async syncGroups() {
    try {
      const sock = this.sock || this.appLocals?.clients?.[this.phoneNumber];
      if (!sock || !this.appLocals?.client_ready?.[this.phoneNumber]) return;
      const all = await sock.groupFetchAllParticipating();
      const groups = Object.values(all || {}).map((g) => ({
        jid: g.id,
        subject: g.subject || "",
        size: Array.isArray(g.participants) ? g.participants.length : 0,
        participants: (g.participants || [])
          .map((p) => ({
            phone: String(p.id || "").split("@")[0].split(":")[0].replace(/\D+/g, ""),
            admin: !!(p.admin),
          }))
          .filter((p) => p.phone),
      }));
      if (!groups.length) return;
      await axios.post(
        `${this.appDomainName}/api/groups/sync`,
        { device_phone: this.phoneNumber, groups },
        { timeout: 20000, headers: { "X-Node-Token": process.env.NODE_WEBHOOK_TOKEN || "" } }
      );
      console.log(`[${this.phoneNumber}] [GROUP-SYNC] pushed ${groups.length} groups`);
    } catch (e) {
      console.warn(`[${this.phoneNumber}] [GROUP-SYNC] failed: ${e?.message}`);
    }
  }

  async handleConnectionUpdate(update, saveCreds) {
    const { connection, lastDisconnect, qr, isNewLogin } = update;

    if (qr) {
      const now = Date.now();
      if (now - this.lastQRTime < 5000) {

        return;
      }
      this.lastQRTime = now;
      this.qrShownAt  = now;
      if (!this.qrFirstShownAt) this.qrFirstShownAt = now; // start the 90s stall clock once
      this.qrCode = qr;
      this.isQRScanned = false;
      this.isConnecting = true;

      console.log(`[${this.phoneNumber}] [QR] generated — waiting for scan (refresh #${this.connectionRetries + 1}). QR will rotate every ~20s; rotate count grows with each Baileys regenerate cycle.`);
      qrcode.generate(qr, { small: true });
      await updateStatusInLaravel("Qr generated", 0, this.phoneNumber, this.appDomainName);
      return;
    }

    if (connection === "open") {
      console.log(`[${this.phoneNumber}] ✅ Connection OPEN — client_ready=true. me=${JSON.stringify(this.sock?.user)}`);

      // Verify the linked WhatsApp account actually matches the
      // phone number this clientManager was created for. The QR
      // pairing handshake doesn't enforce this on its own — if a
      // user scans the QR for 917690059356 with a phone that's
      // logged into WhatsApp as 919145808988, Baileys completes
      // the pairing as 919145808988 and we'd silently save creds
      // for the wrong account into the wrong session folder.
      const linkedId = String(this.sock?.user?.id || '').split('@')[0].split(':')[0];
      const linkedDigits = linkedId.replace(/\D+/g, '');
      const expectedDigits = String(this.phoneNumber || '').replace(/\D+/g, '');
      if (linkedDigits && expectedDigits && linkedDigits !== expectedDigits) {
        console.error(`[${this.phoneNumber}] ❌ MISMATCH — QR was scanned by ${linkedDigits} (expected ${expectedDigits}). Aborting pair.`);
        await updateStatusInLaravel("Mismatch", 0, this.phoneNumber, this.appDomainName);
        try { await this.sock.logout(); } catch (e) {}
        try { await this.deleteSessionFiles(); } catch (e) {}
        this.isConnecting = false;
        this.isConnected = false;
        this.qrCode = null;
        this.shouldStayConnected = false;
        delete this.appLocals.clients[this.phoneNumber];
        this.appLocals.client_ready[this.phoneNumber] = false;
        delete this.appLocals.clientManagers[this.phoneNumber];
        return;
      }

      this.isConnecting = false;
      this.isConnected = true;
      this.hasEverConnected = true;
      this.appLocals.client_ready[this.phoneNumber] = true;
      this.scheduleGroupSync(7000); // first group-directory sync after connect
      this.connectionRetries = 0;
      this.qrCode = null;
      this.isQRScanned = true;
      this.isReconnecting = false;
      // We're connected — cancel any reconnect timer still pending from an
      // earlier close so it can't fire a redundant start() on top of a live
      // socket (#3 orphaned-timer guard).
      if (this.reconnectTimer) { clearTimeout(this.reconnectTimer); this.reconnectTimer = null; }

      await new Promise(resolve => setTimeout(resolve, 2000));

      try {
        await saveCreds();
        console.log(`[${this.phoneNumber}] [CREDS] post-open save OK`);
      } catch (error) {
        console.error(`[${this.phoneNumber}] [CREDS] post-open save FAILED:`, error?.message || error);
      }

      // Verify the auth folder actually has files. If empty, the next
      // restart will lose the session. Log loud so we can chase root
      // cause instead of silently producing an unrestoreable folder.
      try {
        const files = await fs.readdir(this.authFolder);
        console.log(`[${this.phoneNumber}] [CREDS] authFolder=${this.authFolder} has ${files.length} files: ${files.slice(0,5).join(',')}${files.length>5?'…':''}`);
        if (files.length === 0) {
          console.error(`[${this.phoneNumber}] [CREDS] ⚠️  AUTH FOLDER IS EMPTY — next restart will not restore!`);
        }
      } catch (e) {
        console.error(`[${this.phoneNumber}] [CREDS] could not list authFolder:`, e?.message);
      }

      await updateStatusInLaravel("Ready", 100, this.phoneNumber, this.appDomainName);
    }

    if (connection === "close") {
      this.isConnecting = false;
      this.isConnected = false;

      const statusCode = lastDisconnect?.error instanceof Boom
        ? lastDisconnect.error.output?.statusCode
        : null;
      const errorMessage = lastDisconnect?.error?.message || "Unknown";
      const reasonLabel  = this.decodeCloseReason(statusCode, errorMessage);

      console.log(
        `[${this.phoneNumber}] Connection closed. statusCode=${statusCode} reason=${errorMessage} | decoded=${reasonLabel} | hasEverConnected=${this.hasEverConnected} retries=${this.connectionRetries}/${this.maxRetries}`
      );

      this.appLocals.client_ready[this.phoneNumber] = false;
      delete this.appLocals.clients[this.phoneNumber];

      // Graceful shutdown path: the WS got closed because we're going
      // down, NOT because WhatsApp logged us out. Don't wipe files,
      // don't try to reconnect — both would corrupt the pairing.
      if (this.shuttingDown) {
        console.log(`[${this.phoneNumber}] shuttingDown=true → keeping session files, skipping reconnect`);
        return;
      }

      // 515 is the post-pair "stream restart required" handshake step —
      // Baileys ALWAYS fires this after a successful QR scan, before
      // reaching `connection: open`. It is NOT a failure; the existing
      // reconnect logic below handles it. The fail-fast guard must not
      // run for 515 or it would murder every legitimate pairing.
      // 500 is also transient (WhatsApp internal hiccup) — let the
      // existing backoff retry it.
      const isTransientPostPair = (statusCode === 515 || statusCode === 500);

      // Failed first-time pair: the socket never reached `open` AND the
      // close was a hard reject (not the transient 515/500 above). Means
      // the pair was rejected (QR timed out, WhatsApp refused the scan,
      // session conflict, too many linked devices, etc.). Retrying just
      // spawns another QR the user has to look at — useless when the
      // underlying problem is on WhatsApp's side. Stop hard, surface the
      // reason loudly, and let the user retry from /devices.
      if (!this.hasEverConnected && !isTransientPostPair) {
        if (this.qrStallTimer) { clearInterval(this.qrStallTimer); this.qrStallTimer = null; }
        const ageMs = this.qrShownAt ? Date.now() - this.qrShownAt : null;
        console.error(
          `[${this.phoneNumber}] ❌ PAIR FAILED before open — ${reasonLabel}. statusCode=${statusCode}. ` +
          (ageMs !== null ? `QR was on screen for ${Math.round(ageMs/1000)}s before close. ` : '') +
          `Common causes: WhatsApp refused the link (max 4 devices already on that number), ` +
          `QR expired before scan, or phone scanned with a DIFFERENT WhatsApp account ` +
          `(see MISMATCH guard above). Aborting retry loop — click Connect again to try fresh.`
        );
        this.shouldStayConnected = false;
        this.connectionRetries = this.maxRetries;
        try { if (this.sock) await this.sock.end?.(); } catch (e) {}
        try { await this.deleteSessionFiles(); } catch (e) {}
        delete this.appLocals.clientManagers[this.phoneNumber];
        delete this.appLocals.connectionLocks[this.phoneNumber];
        await updateStatusInLaravel(this.shortStatus(`Pair failed: ${reasonLabel}`), 0, this.phoneNumber, this.appDomainName);
        return;
      }

      if (statusCode === 401) {
        console.warn(`[${this.phoneNumber}] 401 — WhatsApp invalidated this device's session (probably unlinked from the phone's Linked Devices screen)`);
        this.shouldStayConnected = false;

        try {
          if (this.sock) {
            await this.sock.logout();
          }
        } catch (e) {

        }

        await this.deleteSessionFiles();
        await updateStatusInLaravel("Logged Out", 0, this.phoneNumber, this.appDomainName);

        return;
      }

      const isLoggedOut = 
        statusCode === DisconnectReason.loggedOut ||
        statusCode === 403;

      const isBadSession = statusCode === DisconnectReason.badSession;

      // STAY CONNECTED UNTIL LOGOUT: only stop auto-reconnect on a genuine
      // logout / auth death (loggedOut / 401 / 403 — handled above + here) or
      // an explicit shutdown. Network, keepalive-miss and WhatsApp-side
      // disconnects retry FOREVER at the capped 30s backoff, so a still-linked
      // device can never silently fall offline "after a few hours". maxRetries
      // is no longer a terminal cap — it only shapes the backoff curve below.
      const shouldStopReconnecting =
        !this.shouldStayConnected ||
        isLoggedOut;

      if (shouldStopReconnecting) {

        
        let logoutStatus = "Connection Failed";
        
        if (isLoggedOut) {
          logoutStatus = "Logged Out";

          try {
            if (this.sock) {
              await this.sock.logout();
            }
          } catch (logoutError) {

          }
          await this.deleteSessionFiles();
        }
        
        await updateStatusInLaravel(logoutStatus, 0, this.phoneNumber, this.appDomainName);
        return;
      }

      if (isBadSession) {

        await this.deleteSessionFiles();
      }

      // Exponential backoff with jitter — prevents reconnect storms
      // when WhatsApp is degrading server-side. Caps at 30s so a long-
      // running offline → online cycle still recovers within a minute.
      // The base delay depends on the close reason: known-transient
      // (515 post-pair, 500 internal) restart fast; keepalive misses
      // (408/440) take a longer break before retry.
      const baseDelay = statusCode === 515 ? 2000
                      : statusCode === 500 ? 4000
                      : statusCode === 408 ? 6000
                      : statusCode === 440 ? 6000
                      : statusCode === 428 ? 4000
                      : 3000;
      const expFactor = Math.min(Math.pow(1.5, this.connectionRetries), 10); // cap multiplier at 10x
      const jitter    = Math.floor(Math.random() * 1500);                    // 0–1500ms
      const delay     = Math.min(baseDelay * expFactor + jitter, 30000);

      if (statusCode === 515) {
        this.nextConnectStatus = { status: "Syncing data", percent: 75 };
        try {
          await saveCreds();
        } catch (e) {}
      } else if (statusCode !== 500 && statusCode !== 408 && statusCode !== 440 && statusCode !== 428) {
        this.nextConnectStatus = { status: "Reconnecting", percent: 50 };
      }

      this.connectionRetries++;
      this.isReconnecting = true;
      this.lastReconnectAt = Date.now();

      console.log(`[${this.phoneNumber}] Scheduling reconnection attempt ${this.connectionRetries}/${this.maxRetries} in ${delay}ms (statusCode=${statusCode}, base=${baseDelay}, exp=${expFactor.toFixed(1)}x)`);

      // #3 — Guard against orphaned reconnect timers. WhatsApp can fire the
      // 'close' event twice in quick succession (e.g. 515 then 428); without
      // this, each schedules its own setTimeout → two concurrent start()
      // calls → duplicate sockets / a reconnect storm. Clear any timer still
      // pending from a previous close before arming a new one, and track the
      // handle so a fresh connect can cancel it.
      if (this.reconnectTimer) { clearTimeout(this.reconnectTimer); this.reconnectTimer = null; }
      this.reconnectTimer = setTimeout(async () => {
        this.reconnectTimer = null;
        try {
          console.log(`[${this.phoneNumber}] Executing reconnection attempt`);
          await this.start();
          this.isReconnecting = false;
        } catch (error) {
          console.error(`[${this.phoneNumber}] Reconnection error:`, error.message);
          this.isReconnecting = false;

          // start() threw — don't leave the device dead. Re-arm another
          // attempt on a capped delay so we keep trying until connected or
          // logged out. The health watchdog is a further backstop.
          if (this.shouldStayConnected && !this.shuttingDown) {
            await updateStatusInLaravel("Reconnecting", 30, this.phoneNumber, this.appDomainName);
            if (this.reconnectTimer) { clearTimeout(this.reconnectTimer); this.reconnectTimer = null; }
            this.reconnectTimer = setTimeout(() => {
              this.reconnectTimer = null;
              this.start().catch(() => {});
            }, 15000);
          }
        }
      }, delay);
    }

    if (connection === "connecting") {
      this.isConnecting = true;
      const statusToSend = this.nextConnectStatus || { status: "Connecting", percent: 25 };
      await updateStatusInLaravel(statusToSend.status, statusToSend.percent, this.phoneNumber, this.appDomainName);
      
      if (this.nextConnectStatus) {
        this.nextConnectStatus = null;
      }

    }
  }

  async handleConnectionUpdateWithPairing(update, saveCreds) {
  const { connection, lastDisconnect, qr, isNewLogin } = update;

  // Ignore QR codes in pairing mode
  if (qr && this.usePairingCode) {

    return;
  }

  // CONNECTION OPEN
  if (connection === "open") {
    console.log(`[${this.phoneNumber}] [Pairing] Connection opened successfully — me=${JSON.stringify(this.sock?.user)}`);

    // Same mismatch guard as the QR flow — pairing codes ARE tied
    // to a phone number, so this should almost never trip, but
    // keeping it symmetric means we can't end up with a stale
    // session folder labelled with the wrong number either way.
    const linkedId = String(this.sock?.user?.id || '').split('@')[0].split(':')[0];
    const linkedDigits = linkedId.replace(/\D+/g, '');
    const expectedDigits = String(this.phoneNumber || '').replace(/\D+/g, '');
    if (linkedDigits && expectedDigits && linkedDigits !== expectedDigits) {
      console.error(`[${this.phoneNumber}] [Pairing] ❌ MISMATCH — paired by ${linkedDigits} (expected ${expectedDigits}). Aborting.`);
      await updateStatusInLaravel("Mismatch", 0, this.phoneNumber, this.appDomainName);
      try { await this.sock.logout(); } catch (e) {}
      try { await this.deleteSessionFiles(); } catch (e) {}
      this.isConnecting = false;
      this.isConnected = false;
      this.pairingCode = null;
      this.shouldStayConnected = false;
      delete this.appLocals.clients[this.phoneNumber];
      this.appLocals.client_ready[this.phoneNumber] = false;
      delete this.appLocals.clientManagers[this.phoneNumber];
      return;
    }

    this.isConnecting = false;
    this.isConnected = true;
    this.appLocals.client_ready[this.phoneNumber] = true;
    this.scheduleGroupSync(7000); // first group-directory sync after connect
    this.connectionRetries = 0;
    this.qrCode = null;
    this.pairingCode = null;
    this.isQRScanned = true;
    this.isReconnecting = false;

    await new Promise(resolve => setTimeout(resolve, 2000));

    try {
      await saveCreds();

    } catch (error) {

    }

    await updateStatusInLaravel("Ready", 100, this.phoneNumber, this.appDomainName);
    return;
  }

  // CONNECTION CLOSED
  if (connection === "close") {
    this.isConnecting = false;
    this.isConnected = false;
    this.pairingCode = null;

    const statusCode = lastDisconnect?.error instanceof Boom
      ? lastDisconnect.error.output?.statusCode
      : null;

    const errorMessage = lastDisconnect?.error?.message || "Unknown";

    console.log(`[${this.phoneNumber}] [Pairing] Connection closed. statusCode=${statusCode} reason=${errorMessage}`);

    this.appLocals.client_ready[this.phoneNumber] = false;
    delete this.appLocals.clients[this.phoneNumber];

    // Handle specific disconnect reasons
    if (statusCode === 401) {

      this.shouldStayConnected = false;
      
      try {
        if (this.sock) {
          await this.sock.logout();
        }
      } catch (e) {

      }
      
      await this.deleteSessionFiles();
      await updateStatusInLaravel("Logged Out", 0, this.phoneNumber, this.appDomainName);
      return;
    }

    const isLoggedOut = 
      statusCode === DisconnectReason.loggedOut ||
      statusCode === 403;

    const isBadSession = statusCode === DisconnectReason.badSession;

    const shouldStopReconnecting = 
      !this.shouldStayConnected ||
      this.connectionRetries >= this.maxRetries ||
      isLoggedOut;

    if (shouldStopReconnecting) {

      
      if (isLoggedOut) {
        try {
          if (this.sock) {
            await this.sock.logout();
          }
        } catch (logoutError) {

        }
        await this.deleteSessionFiles();
      }
      
      await updateStatusInLaravel(
        isLoggedOut ? "Logged Out" : "Connection Failed", 
        0, 
        this.phoneNumber, 
        this.appDomainName
      );
      return;
    }

    if (isBadSession) {

      await this.deleteSessionFiles();
    }

    // Reconnection logic
    let delay = 3000;
    
    if (statusCode === 515) {

      this.nextConnectStatus = { status: "Syncing data", percent: 75 };
      delay = 2000;
      
      try {
        await saveCreds();
      } catch (e) {

      }
    }

    this.connectionRetries++;
    this.isReconnecting = true;

    console.log(`[${this.phoneNumber}] [Pairing] Scheduling reconnection attempt ${this.connectionRetries}/${this.maxRetries} in ${delay}ms`);

    setTimeout(async () => {
      try {
        console.log(`[${this.phoneNumber}] [Pairing] Executing reconnection attempt`);
        await this.start(); // Use regular start for reconnection
        this.isReconnecting = false;
      } catch (error) {
        console.error(`[${this.phoneNumber}] [Pairing] Reconnection error:`, error.message);
        this.isReconnecting = false;

        if (this.connectionRetries >= this.maxRetries) {
          await updateStatusInLaravel(
            "Connection Failed",
            0,
            this.phoneNumber,
            this.appDomainName
          );
        }
      }
    }, delay);
    
    return;
  }

  // CONNECTION IN PROGRESS
  if (connection === "connecting") {
    this.isConnecting = true;

    const statusToSend = this.nextConnectStatus || {
      status: "Connecting",
      percent: 25
    };

    await updateStatusInLaravel(
      statusToSend.status,
      statusToSend.percent,
      this.phoneNumber,
      this.appDomainName
    );

    if (this.nextConnectStatus) {
      this.nextConnectStatus = null;
    }

  }
}


  /**
   * Phone-sent outbound sync. Fired for every messages.upsert event
   * with key.fromMe = true (operator replied from their phone or
   * another linked device). We POST it to Laravel with direction=out
   * so the team-inbox thread shows the same conversation both sides
   * are seeing — no more "the customer messaged me but my reply from
   * the phone is missing in the inbox" gap.
   *
   * Skips:
   *  - non-DM JIDs (groups, status, newsletters)
   *  - empty messages (typing indicators, deletes, etc.)
   *  - messages already in Laravel (dedup by wa_message_id, server-side)
   */
  async syncOutboundFromPhone(message) {
    try {
      const rawJid = message.key.remoteJid || '';
      if (!rawJid)                             return;
      if (rawJid === 'status@broadcast')       return;
      if (rawJid.endsWith('@g.us'))            return;
      if (rawJid.endsWith('@newsletter'))      return;
      if (rawJid.endsWith('@broadcast'))       return;

      // Location (static + live) and contact are non-text types the operator
      // can send from /chat. The INBOUND handler already parses them; mirror
      // that here so a fromMe location/contact isn't dropped (or persisted with
      // media_type=null → "Message unavailable" in the team inbox).
      const loc = message.message?.locationMessage
               || message.message?.liveLocationMessage
               || null;
      const contactName =
          message.message?.contactMessage?.displayName
       || message.message?.contactsArrayMessage?.displayName
       || message.message?.contactsArrayMessage?.contacts?.[0]?.displayName
       || '';

      let messageText =
          message.message?.conversation
       || message.message?.extendedTextMessage?.text
       || message.message?.imageMessage?.caption
       || message.message?.videoMessage?.caption
       || message.message?.documentMessage?.caption
       || '';

      const mediaType =
          message.message?.imageMessage    ? 'image'
        : message.message?.videoMessage    ? 'video'
        : message.message?.audioMessage    ? 'audio'
        : message.message?.documentMessage ? 'document'
        : message.message?.stickerMessage  ? 'sticker'
        : loc                              ? 'location'
        : (message.message?.contactMessage
           || message.message?.contactsArrayMessage) ? 'contact'
        : null;

      // Contact cards have no caption — surface the contact's name as the body
      // so it renders as text even if the card itself can't be drawn.
      if (!messageText && mediaType === 'contact' && contactName) messageText = contactName;

      // Skip if there's literally nothing to sync — Baileys also surfaces
      // protocolMessage/receipts/edits as fromMe events that don't have
      // a body or media.
      if (!messageText && !mediaType) return;

      // History-sync on reconnect replays old fromMe messages as 'append'.
      // Only mirror RECENT sends (a live flow / AI reply or a just-typed
      // phone reply is seconds old); skip anything older than 10 min so a
      // reconnect can't re-flood the team inbox with weeks-old outbound.
      const sentTs = typeof message.messageTimestamp === 'number'
          ? message.messageTimestamp
          : (message.messageTimestamp?.toNumber?.() ?? 0);
      if (sentTs && (Math.floor(Date.now() / 1000) - sentTs) > 600) {
        console.log(`[${this.phoneNumber}] [AI-INBOX-TRACE] skip stale outbound (age ${Math.floor(Date.now() / 1000) - sentTs}s) id=${message.key.id}`);
        return;
      }
      console.log(`[${this.phoneNumber}] [AI-INBOX-TRACE] mirroring fromMe send → inbox jid=${rawJid} body="${(messageText || mediaType || '').substring(0, 40)}"`);

      // The "recipient" is the customer side of the chat. Strip JID
      // suffixes to get the bare digits.
      const stripJid = (j) => (j || '').replace(/@s\.whatsapp\.net$/, '').replace(/@lid$/, '').replace(/@g\.us$/, '');
      const remoteAlt = message.key.remoteJidAlt || '';
      const recipientNumber = stripJid(remoteAlt || rawJid).replace(/[^\d]/g, '');
      if (!recipientNumber) return;

      // Pull the binary down, exactly as the INBOUND path does. Without this the
      // mirror posted media_type='image' with no bytes, so Laravel stored the row
      // with media_path=null and the team inbox drew "Photo unavailable / Media
      // could not be downloaded" for every picture the operator sent from their
      // own phone — pressing Retry could not help either, because Retry only
      // re-fetches WABA media by waba_media_id and this row has none.
      //
      // reuploadRequest lets Baileys ask the sending device to re-upload when
      // WhatsApp's CDN has already evicted the blob, which is the usual reason a
      // download fails a few seconds after the send.
      let mediaBase64 = null;
      let mediaMime = '';
      let mediaFilename = '';
      if (mediaType && mediaType !== 'location' && mediaType !== 'contact') {
        try {
          const buf = await downloadMediaMessage(message, 'buffer', {}, {
            reuploadRequest: this.sock.updateMediaMessage,
          });
          if (buf && buf.length <= 16 * 1024 * 1024) {
            mediaBase64 = buf.toString('base64');
            const m = message.message;
            mediaMime =
              m?.imageMessage?.mimetype    ||
              m?.videoMessage?.mimetype    ||
              m?.audioMessage?.mimetype    ||
              m?.documentMessage?.mimetype ||
              m?.stickerMessage?.mimetype  ||
              '';
            mediaFilename =
              m?.documentMessage?.fileName ||
              m?.documentMessage?.title    ||
              '';
          } else if (buf) {
            console.warn(`[${this.phoneNumber}] [OUTBOUND-MEDIA] file too large (${buf.length} bytes), skipping`);
          }
        } catch (mediaErr) {
          console.error(`[${this.phoneNumber}] [OUTBOUND-MEDIA] download failed: ${mediaErr?.message}`);
        }
      }

      const payload = {
        device_phone:   this.phoneNumber,
        sender_phone:   recipientNumber,  // the OTHER side of the chat
        body:           messageText,
        media_type:     mediaType || undefined,
        media_mime:     mediaMime || undefined,
        media_filename: mediaFilename || undefined,
        media_base64:   mediaBase64 || undefined,
        wa_message_id:  message.key.id,
        raw_jid:        rawJid,
        direction:      'out',
        timestamp:      typeof message.messageTimestamp === 'number'
                          ? message.messageTimestamp
                          : (message.messageTimestamp?.toNumber?.() ?? Math.floor(Date.now() / 1000)),
      };
      // Same location keys the INBOUND path sends, so WaInboundController stores
      // media_type='location' + latitude/longitude and the team inbox draws the
      // map tile instead of falling back to "Message unavailable".
      if (loc) {
        payload.location_latitude  = loc.degreesLatitude;
        payload.location_longitude = loc.degreesLongitude;
        if (loc.name)    payload.location_name    = loc.name;
        if (loc.address) payload.location_address = loc.address;
      }

      await axios.post(`${this.appDomainName}/api/inbound-message`, payload, {
        // Matches the inbound path's limits — the 10s timeout and default 10 MB
        // body cap were fine for a text mirror but silently killed any mirror
        // carrying an image.
        timeout: mediaBase64 ? 30000 : 10000,
        headers: { 'Accept': 'application/json', ...laravelHeaders() },
        maxBodyLength: 32 * 1024 * 1024,
        maxContentLength: 32 * 1024 * 1024,
      });
      console.log(`[${this.phoneNumber}] [OUTBOUND-SYNC] from-phone → recipient=${recipientNumber} body="${messageText.substring(0, 40)}"`);
    } catch (err) {
      console.error(`[${this.phoneNumber}] [OUTBOUND-SYNC] failed: ${err?.response?.status || ''} ${err?.message}`);
    }
  }

  async handleMessages(messageUpdate) {
    const { messages, type } = messageUpdate;
    // 'notify' = newly-arrived messages. 'append' = messages appended to a
    // chat — INCLUDING this socket's OWN outgoing sends (e.g. a flow / AI
    // reply sent via sock.sendMessage). We must process 'append' too, else
    // the bot's own replies never reach syncOutboundFromPhone and so never
    // appear in the team inbox. Inbound handling (routing / auto-reply /
    // flow) stays 'notify'-only via the per-message guard below.
    if (type !== "notify" && type !== "append") return;

    for (const message of messages) {
      try {
        // Trace EVERY inbound at the entry — gives a single grep target
        // when a customer reply doesn't reach our handlers.
        const msgKind = Object.keys(message.message || {}).slice(0, 3).join(',') || '(none)';
        console.log(`[${this.phoneNumber}] [MSG-IN] fromMe=${!!message.key.fromMe} jid=${message.key.remoteJid || '?'} types=[${msgKind}]`);

        // Phone-sent messages (operator replies from their actual phone or
        // another linked WhatsApp device) come in here with key.fromMe=true.
        // Without this branch they were silently dropped — the team-inbox
        // would show only the customer's side of a conversation the
        // operator was actively answering from their phone. We forward
        // them to Laravel as direction=out and continue (no routing /
        // auto-reply / drip — those are inbound-only concerns).
        if (message.key.fromMe) {
          await this.syncOutboundFromPhone(message);
          continue;
        }

        // Inbound handling (routing / auto-reply / flow) is 'notify'-only.
        // 'append' batches only matter for the fromMe outbound-sync above —
        // never run a customer-message pipeline off a history-replay append.
        if (type !== "notify") continue;

        const rawJid = message.key.remoteJid || '';

        // Hard-filter non-DM JIDs. We only handle 1-on-1 customer chats
        // in /team-inbox. Status updates, groups, newsletters, and the
        // server-status pseudo-JID all produce noise rows otherwise
        // (the "Om Prakash Kachawa" empty thread the user reported was
        // a status@broadcast leaking through).
        if (!rawJid) continue;
        if (rawJid === 'status@broadcast') continue;
        if (rawJid.endsWith('@g.us')) continue;
        if (rawJid.endsWith('@newsletter')) continue;
        if (rawJid.endsWith('@broadcast')) continue;

        // Resolve the sender's real phone number. Modern Baileys routes
        // some chats through Linked-Device IDs (LIDs) — `remoteJid`
        // looks like `<15-digit-lid>@lid` and is NOT a phone. The real
        // phone may live in `key.senderPn`, `key.participantPn`, or
        // `key.remoteJidAlt`, depending on Baileys version.
        const senderPn   = message.key.senderPn     || '';
        const participantPn = message.key.participantPn || '';
        const remoteAlt  = message.key.remoteJidAlt || '';
        const stripJid = (j) => (j || '').replace(/@s\.whatsapp\.net$/, '').replace(/@lid$/, '').replace(/@g\.us$/, '');

        // Pick the best candidate for the real phone AND remember
        // whether we actually have one. `hasRealPhone` is the source
        // of truth — even if Baileys gave us a LID `remoteJid`, if
        // senderPn/participantPn/remoteJidAlt yielded a phone, the
        // chat is NOT effectively a LID-only chat.
        let userNumber = '';
        let hasRealPhone = false;
        if (senderPn) { userNumber = stripJid(senderPn); hasRealPhone = true; }
        else if (participantPn) { userNumber = stripJid(participantPn); hasRealPhone = true; }
        else if (remoteAlt && remoteAlt.endsWith('@s.whatsapp.net')) {
          userNumber = stripJid(remoteAlt);
          hasRealPhone = true;
        } else if (rawJid.endsWith('@s.whatsapp.net')) {
          userNumber = stripJid(rawJid);
          hasRealPhone = true;
        } else {
          // True LID-only chat — no real phone anywhere. Use the LID
          // digits as the identifier and flag the row accordingly.
          userNumber = stripJid(rawJid);
          hasRealPhone = false;
        }

        // Pull the user-visible text from the inbound message. Sources
        // checked, in priority order:
        //   1. conversation                                          — plain text
        //   2. extendedTextMessage.text                              — text + quote
        //   3. buttonsResponseMessage.selectedDisplayText / .Id      — legacy buttons
        //   4. listResponseMessage.singleSelectReply.title/.id       — legacy list
        //   5. templateButtonReplyMessage.selectedDisplayText / .Id  — template buttons
        //   6. interactiveResponseMessage.nativeFlowResponseMessage.paramsJson
        //        →  contains {"id":"...","display_text":"sale deed"}
        //      ← modern interactiveButtons quick_reply landing shape; also
        //        what some WhatsApp clients use for interactive lists.
        // ALL of these are silent-drop hazards: if not parsed, messageText
        // is "" and the message looks empty → routing stage skips it →
        // active flow session never advances.
        let messageText =
          message.message?.conversation ||
          message.message?.extendedTextMessage?.text ||
          message.message?.buttonsResponseMessage?.selectedDisplayText ||
          message.message?.buttonsResponseMessage?.selectedButtonId ||
          message.message?.listResponseMessage?.singleSelectReply?.title ||
          message.message?.listResponseMessage?.singleSelectReply?.selectedRowId ||
          message.message?.templateButtonReplyMessage?.selectedDisplayText ||
          message.message?.templateButtonReplyMessage?.selectedId ||
          "";
        if (!messageText) {
          const native = message.message?.interactiveResponseMessage?.nativeFlowResponseMessage;
          if (native?.paramsJson) {
            try {
              const parsed = JSON.parse(native.paramsJson);
              messageText = String(parsed?.display_text || parsed?.id || "");
            } catch { /* malformed JSON, keep messageText empty */ }
          }
        }

        const senderName = message.pushName || '';
        const mediaType = message.message?.imageMessage     ? 'image'
                       : message.message?.videoMessage      ? 'video'
                       : message.message?.audioMessage      ? 'audio'
                       : message.message?.documentMessage   ? 'document'
                       : message.message?.locationMessage   ? 'location'
                       : null;

        // Location coords — Baileys `locationMessage` carries
        // degreesLatitude / degreesLongitude / name / address. Without
        // forwarding them to Laravel the team-inbox shows a generic
        // "📎 Location" with no map link (audit #4).
        const locMsg = message.message?.locationMessage;
        const locationLat  = locMsg ? Number(locMsg.degreesLatitude  ?? 0) : null;
        const locationLng  = locMsg ? Number(locMsg.degreesLongitude ?? 0) : null;
        const locationName = locMsg ? String(locMsg.name    || '') : '';
        const locationAddr = locMsg ? String(locMsg.address || '') : '';

        // Reaction-message detection. Baileys delivers reactions as a
        // separate message type with its own `reactionMessage` payload.
        // It points at a target key (which of our messages was reacted
        // to) and carries an emoji. We forward it to Laravel as a
        // reaction-only event (no message bubble).
        const reactionMsg = message.message?.reactionMessage;
        if (reactionMsg && reactionMsg.key && reactionMsg.key.id) {
          try {
            await axios.post(`${this.appDomainName}/api/inbound-message`, {
              device_phone:     this.phoneNumber,
              sender_phone:     userNumber,
              sender_name:      senderName,
              reaction_emoji:   reactionMsg.text || '',
              reaction_target:  reactionMsg.key.id,
              timestamp:        typeof message.messageTimestamp === 'number'
                                  ? message.messageTimestamp
                                  : (message.messageTimestamp?.toNumber?.() ?? Math.floor(Date.now() / 1000)),
            }, { timeout: 8000, headers: { 'Accept': 'application/json', ...laravelHeaders() } });
            console.log(`[${this.phoneNumber}] [INBOUND-REACT] from=${userNumber} target=${reactionMsg.key.id} emoji="${reactionMsg.text || '(clear)'}"`);
          } catch (e) {
            console.error(`[${this.phoneNumber}] [INBOUND-REACT] forward failed: ${e?.message}`);
          }
          continue; // reactions are not separate bubbles — done.
        }

        // Contact-card detection — Baileys delivers shared contacts as
        // `contactMessage` (single) or `contactsArrayMessage` (multi).
        // We parse the first contact's vCard for name + phone so the
        // team-inbox renderer can show a contact-card UI.
        let contactName = '';
        let contactPhone = '';
        let contactVcard = '';
        const contactMsg = message.message?.contactMessage
                        || message.message?.contactsArrayMessage?.contacts?.[0];
        if (contactMsg) {
          contactName  = contactMsg.displayName || '';
          contactVcard = contactMsg.vcard || '';
          if (contactVcard) {
            const telMatch = contactVcard.match(/TEL[^:]*:([+\d\s\-()]+)/i);
            if (telMatch) contactPhone = telMatch[1].replace(/[^\d+]/g, '');
            if (!contactName) {
              const fnMatch = contactVcard.match(/^FN:(.+)$/m);
              if (fnMatch) contactName = fnMatch[1].trim();
            }
          }
        }

        // Forwarded-message flag — lives on contextInfo. Pulled from any
        // message type that carries it (text, image, video, etc.).
        const ctxInfo = message.message?.extendedTextMessage?.contextInfo
                     || message.message?.imageMessage?.contextInfo
                     || message.message?.videoMessage?.contextInfo
                     || message.message?.documentMessage?.contextInfo
                     || message.message?.audioMessage?.contextInfo
                     || {};
        const isForwarded  = !!ctxInfo.isForwarded;
        const forwardScore = (typeof ctxInfo.forwardingScore === 'number') ? ctxInfo.forwardingScore : 0;

        // Skip empty system / protocol messages (no body AND no media AND
        // no contact). Reactions are already handled + `continue`d above.
        if (!messageText && !mediaType && !contactName && !contactPhone) continue;

        const isLid = !hasRealPhone;
        // Canonical JID for outbound routing. Prefer a phone-based JID
        // when we have a real phone — replies should hit the real
        // number, not the LID. Only fall back to the @lid form when
        // we genuinely have no phone.
        const canonicalJid = hasRealPhone
          ? `${userNumber}@s.whatsapp.net`
          : (rawJid || `${userNumber}@lid`);

        // ALSO send the LID-form JID whenever Baileys gave us one,
        // even if we already resolved the real phone. Reason: Baileys
        // sometimes delivers later messages from the same person
        // WITHOUT senderPn — only the raw LID. Laravel needs both
        // identifiers to match every inbound back to the same single
        // conversation instead of forking into two threads.
        const lidJid = rawJid.endsWith('@lid') ? rawJid : '';

        // Pull binary for media attachments so the operator sees the
        // doc/image/video in /team-inbox the same as on their phone.
        // Encoded as base64 — the inbound endpoint decodes + writes
        // to storage. We cap at 16 MB to stay under HTTP body limits.
        let mediaBase64 = null;
        let mediaMime = '';
        let mediaFilename = '';
        if (mediaType) {
          try {
            // Pass reuploadRequest so Baileys can ask the SENDER'S
            // device to re-upload when WhatsApp's CDN has already
            // evicted the media. Without it, voice notes that arrive
            // a few seconds late (or any other media older than the
            // CDN window) fail to download. Per Baileys README spec.
            const buf = await downloadMediaMessage(message, 'buffer', {}, {
              reuploadRequest: this.sock.updateMediaMessage,
            });
            if (buf && buf.length <= 16 * 1024 * 1024) {
              mediaBase64 = buf.toString('base64');
              const m = message.message;
              mediaMime =
                m?.imageMessage?.mimetype     ||
                m?.videoMessage?.mimetype     ||
                m?.audioMessage?.mimetype     ||
                m?.documentMessage?.mimetype  ||
                '';
              mediaFilename =
                m?.documentMessage?.fileName  ||
                m?.documentMessage?.title     ||
                '';
            } else if (buf) {
              console.warn(`[${this.phoneNumber}] [INBOUND-MEDIA] file too large (${buf.length} bytes), skipping`);
            }
          } catch (mediaErr) {
            console.error(`[${this.phoneNumber}] [INBOUND-MEDIA] download failed: ${mediaErr?.message}`);
          }
        }

        // Forward every inbound message to Laravel so it surfaces in
        // /team-inbox + /chat. Fire-and-forget — auto-reply / flow
        // logic below still runs on the same message regardless.
        // Laravel's response may include `routing_actions.flow_id` when
        // a routing rule fires `trigger_flow` for a new conversation;
        // we consume it here to launch the same flow the keyword-reply
        // path would, so the global-AJAX pattern stays single-call.
        axios.post(`${this.appDomainName}/api/inbound-message`, {
          device_phone:   this.phoneNumber,
          sender_phone:   userNumber,
          sender_name:    senderName,
          body:           messageText,
          media_type:     mediaType,
          media_mime:     mediaMime,
          media_filename: mediaFilename,
          media_base64:   mediaBase64,
          wa_message_id:  message.key.id,
          is_lid:         isLid,
          raw_jid:        canonicalJid,
          lid_jid:        lidJid,
          // Contact-card payload — WaDesk renders this as a contact-card
          // bubble in the thread instead of a generic media row.
          contact_name:   contactName  || undefined,
          contact_phone:  contactPhone || undefined,
          contact_vcard:  contactVcard || undefined,
          // Forwarded-message metadata — WaDesk shows the "Forwarded" /
          // "Frequently forwarded" italic label above the bubble.
          is_forwarded:   isForwarded || undefined,
          forward_score:  forwardScore || undefined,
          // Location coords (only present when mediaType === 'location').
          // Sent as separate fields so Laravel can store them in meta.
          location_latitude:  locMsg ? locationLat  : undefined,
          location_longitude: locMsg ? locationLng  : undefined,
          location_name:      locationName || undefined,
          location_address:   locationAddr || undefined,
          timestamp:      typeof message.messageTimestamp === 'number'
                            ? message.messageTimestamp
                            : (message.messageTimestamp?.toNumber?.() ?? Math.floor(Date.now() / 1000)),
        }, {
          timeout: 30000, // media payloads can be large; allow time to upload
          headers: { 'Accept': 'application/json', ...laravelHeaders() },
          maxBodyLength: 32 * 1024 * 1024,
          maxContentLength: 32 * 1024 * 1024,
        }).then((res) => {
          console.log(`[${this.phoneNumber}] [INBOUND] forwarded sender=${userNumber} name="${senderName}" lid=${isLid} jid=${canonicalJid} lidJid=${lidJid || '-'} media=${mediaType || '-'} body="${messageText.substring(0, 40)}"`);
          // Routing-rule side effects. Laravel consumes the slot
          // server-side so even if Node crashes here, the trigger
          // won't replay — `pending_flow_id` was already unset.
          const flowId = res?.data?.routing_actions?.flow_id;
          if (flowId) {
            console.log(`[${this.phoneNumber}] [INBOUND] routing rule triggered flow_id=${flowId}`);
            this.handleFlowAutoReply(message, userNumber, flowId, messageText, 600, 0).catch((err) => {
              console.error(`[${this.phoneNumber}] [INBOUND] routing-triggered flow failed: ${err?.message}`);
            });
          }
        }).catch((err) => {
          console.error(`[${this.phoneNumber}] [INBOUND] forward failed: ${err?.response?.status || ''} ${err?.message}`);
        });

        const sessionKey = `${this.phoneNumber}_${userNumber}`;

        const activeSession = this.appLocals.activeFlowSessions[sessionKey];

        if (activeSession && activeSession.status === "active" && activeSession.waitingForInput) {
          console.log(`[${this.phoneNumber}] [FLOW-RESP] active session found key=${sessionKey} nextNodeType=${activeSession.waitingForInput?.nextNodeType}`);
          if (activeSession.timeoutTimer) {
            clearTimeout(activeSession.timeoutTimer);
          }

          // ── KEYWORD RESTART (mid-flow), for ANY waiting node ──────────────
          // A customer who TYPES a flow-trigger keyword again while a flow is
          // already running means "start the flow over" — the standard
          // WhatsApp-bot convention — so we must NOT feed that keyword into the
          // current Ask / Question / List node as its answer. Previously only a
          // *persistent template menu* honoured this; a normal waiting node
          // swallowed the re-typed keyword as input and the flow never restarted
          // (client: "flow stuck mid-way, re-sending the keyword doesn't restart
          // it"). Now EVERY waiting node restarts on a typed keyword. Only TYPED
          // text can restart — a button / list / native-flow TAP is always a
          // node answer, never a keyword, so taps fall straight through to
          // handleFlowResponse below and the flow's own buttons keep working.
          const _wfi = activeSession.waitingForInput;
          const _mm = message.message || {};
          const _isTap = !!(_mm.buttonsResponseMessage || _mm.templateButtonReplyMessage || _mm.listResponseMessage || _mm.interactiveResponseMessage);
          if (!_isTap) {
            const _typed = String(messageText || "").toLowerCase().trim();
            // Fast path — re-typing the SAME text that started this flow restarts
            // it INSTANTLY from the flowId the session already holds: no keyword
            // HTTP lookup, no dependency on the rule still matching.
            if (_typed && activeSession.triggerKeyword && _typed === activeSession.triggerKeyword && activeSession.flowId) {
              console.log(`[${this.phoneNumber}] [FLOW-RESP] mid-flow; re-typed trigger keyword → restarting flow ${activeSession.flowId}`);
              await this.handleFlowAutoReply(message, userNumber, activeSession.flowId, messageText, activeSession.timeoutSeconds || 600, 0);
              continue;
            }
            // Otherwise resolve the typed text against the keyword rules exactly
            // as a no-session inbound would — so a "contains" match on the same
            // trigger, or a DIFFERENT flow's keyword, both (re)start that flow.
            if (_typed) {
              const kw = await this.checkKeywordReply(userNumber, messageText, mediaType);
              if (kw && kw.reply_type === 'flow' && kw.flow_id) {
                console.log(`[${this.phoneNumber}] [FLOW-RESP] mid-flow; typed keyword → restarting flow ${kw.flow_id}`);
                await this.handleFlowAutoReply(message, userNumber, kw.flow_id, messageText, kw.timeout || 600, kw.cooldown ?? 0);
                continue;
              }
            }
          }

          // Persistent template menu + typed NON-keyword text: no keyword matched
          // above, so keep the menu OPEN (the buttons on the original message
          // stay tappable) instead of mis-firing button 1 on stray text.
          if (_wfi && _wfi.persistent && !_isTap) {
            console.log(`[${this.phoneNumber}] [FLOW-RESP] persistent menu open; typed non-keyword text ignored, menu stays open`);
            this.setFlowTimeout(sessionKey, activeSession);
            continue;
          }

          try {
            await handleFlowResponse(
              message,
              activeSession,
              userNumber,
              this.phoneNumber,
              this.sock,
              this.appLocals
            );
          } catch (e) {
            console.error(`[${this.phoneNumber}] [FLOW-RESP] failed: ${e?.message}`, {
              stack: e?.stack?.split('\n').slice(0, 4).join(' | '),
            });
          }

          this.setFlowTimeout(sessionKey, activeSession);
          continue;
        } else if (activeSession) {
          console.log(`[${this.phoneNumber}] [FLOW-RESP] session exists but not waiting (status=${activeSession.status} waitingForInput=${!!activeSession.waitingForInput})`);
        } else {
          // No session for this sender — surface ALL keys so a typo / lid /
          // pn mismatch is visible immediately.
          const keys = Object.keys(this.appLocals.activeFlowSessions || {});
          console.log(`[${this.phoneNumber}] [FLOW-RESP] no session for key=${sessionKey}. activeFlowSessions has ${keys.length} key(s): ${keys.slice(0, 6).join(', ') || '(none)'}`);
        }

        // Cooldown gate — but DON'T drop the message yet. We used to `continue`
        // here for ANY inbound during the post-flow cooldown, which also
        // silently dropped a customer deliberately RE-TYPING the trigger
        // keyword to run the flow again ("flow works once, won't restart").
        // So resolve the keyword FIRST: a flow-trigger keyword is an
        // intentional restart and must bypass the cooldown; non-flow replies
        // still honour it below.
        const inCooldown = this.isInCooldown(sessionKey);

        const keywordData = await this.checkKeywordReply(userNumber, messageText, mediaType);
        // TRACE: every inbound + whether it matched a keyword/flow trigger.
        // If this says "match=NONE", the message NEVER starts a flow — the
        // keyword rule isn't matching (wrong word / flow not published / not
        // the trigger). That is the #1 reason "nothing happens".
        console.log(`[FLOWTRACE] inbound msg="${String(messageText || '').substring(0, 60)}" from=${userNumber} device=${this.phoneNumber} → keyword match=${keywordData ? ('YES type=' + keywordData.reply_type + ' flow_id=' + (keywordData.flow_id || '-')) : 'NONE — no flow will run'}`);

        if (!keywordData) {

          continue;
        }

        const { reply_type, flow_id, reply, timeout, cooldown, contact, catalog, reply_delay } = keywordData;

        if (reply_type === 'flow' && flow_id) {
          // A flow-trigger keyword ALWAYS restarts the flow — even mid-cooldown.
          // The cooldown throttles passive re-firing / spam, NOT a customer who
          // intentionally re-types the keyword to run the flow again. This is
          // the fix for "flow runs once, the same keyword won't start it again".
          //
          // Timeout note: default 30s was too aggressive for any flow with a
          // List / Buttons node — the customer often takes >30s to tap, then
          // the session was already deleted (setFlowTimeout's setTimeout fired)
          // and the tap lands with no session to advance. 600s (10 min) matches
          // the routing-rule path; it RESETS every time handleFlowResponse runs,
          // so multi-step flows still get fresh windows per user reply.
          await this.handleFlowAutoReply(
            message,
            userNumber,
            flow_id,
            messageText,
            timeout || 600,
            cooldown ?? 0
          );
          continue;
        }

        // Everything below is a NON-flow reply (custom text/media, share
        // contact, catalog, location) → respect the cooldown so a spammer
        // can't re-fire the same reply on every inbound.
        if (inCooldown) {
          continue;
        }

        if (reply_type === 'custom') {
          // Cooldown was MISSING here — the share_contact/catalog/location
          // branches below all call setCooldown(), but custom (text/media/
          // template, the most common reply) did not, so the per-rule
          // cooldown was silently ignored and the reply re-fired on every
          // matching inbound. Set it BEFORE the send: custom uniquely has a
          // reply_delay, and arming the cooldown first stops keyword spam
          // during that delay window from queueing duplicate replies.
          this.setCooldown(sessionKey, cooldown ?? 0);
          await this.handleCustomAutoReply(message, reply, reply_delay);
        } else if (reply_type === 'share_contact' && contact?.phone) {
          // #19 — Send a vCard the customer can tap to save/call.
          try {
            const jid = `${userNumber}@s.whatsapp.net`;
            const phone = String(contact.phone).replace(/\D+/g, '');
            const name = (contact.name || phone).trim();
            const vcard =
              'BEGIN:VCARD\n' +
              'VERSION:3.0\n' +
              `FN:${name}\n` +
              `TEL;type=CELL;type=VOICE;waid=${phone}:+${phone}\n` +
              (contact.email ? `EMAIL:${contact.email}\n` : '') +
              'END:VCARD';
            await this.sock.sendMessage(jid, {
              contacts: { displayName: name, contacts: [{ vcard }] },
            });
            // Cooldown — matches the flow/custom paths so a spammer
            // can't keep re-triggering the same keyword on every msg.
            this.setCooldown(sessionKey, cooldown ?? 0);
          } catch (e) {
            console.warn(`[${this.phoneNumber}] share_contact send failed: ${e?.message}`);
          }
        } else if (reply_type === 'send_catalog' && catalog?.catalog_id) {
          // #20 — Catalog deep-link bubble. v1 uses wa.me/c/{id} as a
          // plain-text link; v2 should build a true Baileys Product
          // List message (`productListMessage`) by fetching the
          // selected products from Laravel. TODO when MPM is needed.
          try {
            const jid = `${userNumber}@s.whatsapp.net`;
            await this.sock.sendMessage(jid, {
              text: `Here's our catalog: https://wa.me/c/${catalog.catalog_id}`,
            });
            this.setCooldown(sessionKey, cooldown ?? 0);
          } catch (e) {
            console.warn(`[${this.phoneNumber}] send_catalog failed: ${e?.message}`);
          }
        } else if (reply_type === 'request_location') {
          // #23 — Send WhatsApp's native location-request prompt.
          // Baileys exposes this as `requestLocationMessage`.
          try {
            const jid = `${userNumber}@s.whatsapp.net`;
            await this.sock.sendMessage(jid, {
              requestLocationMessage: { body: 'Please share your location' },
            });
            this.setCooldown(sessionKey, cooldown ?? 0);
          } catch (e) {
            console.warn(`[${this.phoneNumber}] request_location failed: ${e?.message}`);
          }
        }

      } catch (error) {
        // Don't swallow silently — a thrown error here means a single
        // inbound failed to process; subsequent inbounds in the same
        // batch should still get a fair try. Log so ops can diagnose.
        console.error(`[${this.phoneNumber}] [INBOUND] message handler threw: ${error?.message || error}`);
      }
    }
  }

  isInCooldown(sessionKey) {
    const cooldownData = this.appLocals.userCooldowns[sessionKey];
    if (!cooldownData) return false;
    
    const now = Date.now();
    const cooldownEnd = cooldownData.endTime;
    
    if (now < cooldownEnd) {
      const remainingSeconds = Math.ceil((cooldownEnd - now) / 1000);

      return true;
    }
    
    delete this.appLocals.userCooldowns[sessionKey];
    return false;
  }

  setCooldown(sessionKey, cooldownSeconds) {
    const endTime = Date.now() + (cooldownSeconds * 1000);
    this.appLocals.userCooldowns[sessionKey] = {
      endTime,
      cooldownSeconds
    };

  }

  setFlowTimeout(sessionKey, session) {
    // Default 600s (10 min). Any node that waits for user input
    // (List, Buttons, Question, Poll) needs enough time for a human
    // to actually pick + tap. 30s default broke every flow.
    const timeoutSeconds = session.timeoutSeconds || 600;

    if (session.timeoutTimer) {
      clearTimeout(session.timeoutTimer);
    }

    session.timeoutTimer = setTimeout(() => {
      console.log(`[${this.phoneNumber}] [FLOW-TIMEOUT] session=${sessionKey} expired after ${timeoutSeconds}s — deleting`);
      session.status = "timeout";
      session.waitingForInput = null;

      if (session.cooldownSeconds) {
        this.setCooldown(sessionKey, session.cooldownSeconds);
      }

      delete this.appLocals.activeFlowSessions[sessionKey];
    }, timeoutSeconds * 1000);
  }

  async checkKeywordReply(userNumber, messageText, mediaType = null) {
    try {
      const keyword = messageText.toLowerCase().trim();
      // A PHOTO / VOICE order carries no typed text — there's no keyword to
      // match, but the customer still wants to order. Let image/voice through so
      // Laravel can launch the device's order flow (its parse node then reads
      // the image via vision / the voice via transcript). Any other empty-text
      // inbound still no-ops exactly as before.
      const mediaOrder = (mediaType === 'image' || mediaType === 'audio');
      if (!keyword && !mediaOrder) return null;

      const from = `${userNumber}@s.whatsapp.net`;
      const apiUrl = `${this.appDomainName}/api/keyword-replies?keyword=${encodeURIComponent(keyword)}&mobile=${from}&phone=${this.phoneNumber}${mediaOrder ? `&media_type=${encodeURIComponent(mediaType)}` : ''}`;


      const response = await axios.get(apiUrl, { headers: laravelHeaders() });


      if (!response.data || response.data.length === 0) {
        return null;
      }

      const data = response.data[0];
      
      if (data.reply === 'notallow' || data.reply === 'default') {

        return null;
      }

      return {
        reply_type: data.reply_type || 'custom',
        flow_id: data.flow_id || null,
        reply: data.reply || null,
        // Flow-session inactivity window. Was 30s — far too short for a real
        // multi-step flow (the customer has to read the summary, then TYPE their
        // name + full delivery address). If they took >30s the "waiting" session
        // was deleted and their reply landed nowhere (intermittent "nothing
        // happened"). 600s (10 min) gives a human time to answer each step.
        timeout: data.timeout || 600,
        cooldown: data.cooldown ?? 0,
        // Send-delay (the "Reply delay" form field). 0 = send instantly, so
        // a rule with no delay behaves exactly as before. Distinct from the
        // flow-session `timeout` above.
        reply_delay: data.reply_delay || 0,
        // PR 2.4 payloads — only present for the new reply_type values.
        contact: data.contact || null,   // { name, phone, email } for share_contact
        catalog: data.catalog || null,   // { catalog_id, provider, mode } for send_catalog
      };

    } catch (error) {

      return null;
    }
  }

  async handleFlowAutoReply(message, userNumber, flowId, messageText, timeoutSeconds, cooldownSeconds) {
    try {



      const sessionKey = `${this.phoneNumber}_${userNumber}`;
      
      if (this.appLocals.activeFlowSessions[sessionKey]) {

        const oldSession = this.appLocals.activeFlowSessions[sessionKey];
        if (oldSession.timeoutTimer) {
          clearTimeout(oldSession.timeoutTimer);
        }
      }

      console.log(`[FLOW] handleFlowAutoReply START sender=${userNumber} device=${this.phoneNumber} flowId=${flowId} keyword="${messageText.substring(0,40)}"`);
      const flowResponse = await axios.get(
        `${this.appDomainName}/api/flows/${flowId}`,
        { headers: laravelHeaders() }
      );

      if (!flowResponse.data.success) {
        console.warn(`[FLOW] /api/flows/${flowId} returned success=false → aborting`);
        return;
      }

      const flowData = flowResponse.data.data.flow_data;
      const nodeCount = (flowData?.flowNodes || []).length;
      const edgeCount = (flowData?.flowEdges || []).length;
      console.log(`[FLOW] fetched flow id=${flowId} nodes=${nodeCount} edges=${edgeCount} workspace_id=${flowData?.workspace_id || 'NULL'}`);
      if (nodeCount === 0) {
        console.warn(`[FLOW] flow ${flowId} has 0 nodes — nothing to execute`);
        return;
      }

      // Workspace attributes ({{promo_key}}, default {{order_id}}, …) are
      // merged UNDER the inbound user_message var (the keyword text always
      // wins). workspace_id rides on flow_data from /api/flows/:id (nodeShow).
      // Best-effort + cached so an inbound keyword burst doesn't refetch.
      const workspaceAttrs = await fetchWorkspaceAttributes(
        this.appDomainName,
        flowData?.workspace_id
      );
      this.appLocals.activeFlowSessions[sessionKey] = {
        sessionId: `${sessionKey}_${Date.now()}`,
        flowId: flowId,
        // Original trigger text — lets a persistent template menu restart THIS
        // flow instantly when the customer re-types the same keyword.
        triggerKeyword: String(messageText || "").toLowerCase().trim(),
        flowData: flowData,
        currentNodeId: null,
        userVariables: mergeFlowVariables(workspaceAttrs, {
          user_message: messageText,
          // Personalization vars so {{name}} etc. resolve in message nodes.
          // Source = the customer's WhatsApp profile name on this inbound.
          name:       String(message?.pushName || '').trim(),
          first_name: (String(message?.pushName || '').trim().split(/\s+/)[0] || ''),
          pushName:   String(message?.pushName || '').trim(),
          phone:      String(userNumber || '').replace(/\D+/g, ''),
        }),
        messageHistory: [
          {
            type: "received",
            message: messageText,
            timestamp: moment().format(),
          },
        ],
        status: "active",
        startedAt: moment().format(),
        phoneNumber: this.phoneNumber,
        timeoutSeconds: timeoutSeconds,
        cooldownSeconds: cooldownSeconds,
        timeoutTimer: null
      };

      const activeSession = this.appLocals.activeFlowSessions[sessionKey];

      const startNode = flowData.flowNodes[0];
      console.log(`[FLOW] starting from node ${startNode.id} type=${startNode.flowNodeType || startNode.type}`);
      await executeFlowNode(
        startNode,
        userNumber,
        this.phoneNumber,
        this.sock,
        this.appLocals,
        sessionKey
      );

      this.setFlowTimeout(sessionKey, activeSession);
      console.log(`[FLOW] handleFlowAutoReply DONE — session=${sessionKey} status=${activeSession.status}`);

    } catch (error) {
      // Previously a silent empty catch — that hid every flow trigger
      // failure (404 from /api/flows, network blip, executor throw).
      console.error(`[FLOW] handleFlowAutoReply THREW: ${error?.message}`);
      console.error(error?.stack || '(no stack)');
    }
  }

  async handleCustomAutoReply(message, replyData, delaySeconds = 0) {
    try {
      const from = message.key.remoteJid;

      // "Reply delay" — wait the configured seconds before sending so the
      // reply feels human. Default 0 = send instantly (unchanged behaviour);
      // capped at 300s so a bad value can't hang the handler.
      const delay = Math.min(Math.max(parseInt(delaySeconds, 10) || 0, 0), 300);
      if (delay > 0) {
        await new Promise(resolve => setTimeout(resolve, delay * 1000));
      }

      // Personalisation: substitute {{name}} / {{first_name}} / {{phone}} in the
      // reply (and media caption) with the customer's WhatsApp details. Auto-reply
      // captions like "Hi {{ name }}!" were previously sent VERBATIM because
      // nothing ever replaced the placeholders. Case-insensitive + space-tolerant
      // so "{{name}}", "{{ name }}", "{{Name}}" all resolve.
      const custName  = String(message?.pushName || '').trim();
      const firstName = custName.split(/\s+/)[0] || custName;
      const custPhone = String(String(from || '').split('@')[0] || '');
      const subst = (s) => String(s ?? '')
        .replace(/\{\{\s*name\s*\}\}/gi, custName)
        .replace(/\{\{\s*first_?name\s*\}\}/gi, firstName)
        .replace(/\{\{\s*phone\s*\}\}/gi, custPhone);

      let parsedReply;
      try {
        parsedReply = JSON.parse(replyData);
      } catch {
        parsedReply = null;
      }

      if (parsedReply && parsedReply.type) {
        // Media reply — personalise the caption before sending.
        if (parsedReply.caption) parsedReply.caption = subst(parsedReply.caption);
        await this.sendMediaReply(from, parsedReply);
      } else {
        // Plain text reply — personalise before sending.
        await this.sock.sendMessage(from, { text: subst(replyData) });
      }

    } catch (error) {
      console.error(`[AUTO-REPLY] custom reply failed: ${error?.message || error}`);
    }
  }

  async sendMediaReply(to, replyData) {
    const { type, filename, mimetype, caption } = replyData;
    let url = String(replyData.url || '');
    // Safety net: absolutise a relative path so axios can fetch it.
    if (url && !/^https?:\/\//i.test(url) && !url.startsWith('data:')) {
      const base = String(this.appDomainName || '').replace(/\/+$/, '');
      if (base) url = base + (url.startsWith('/') ? '' : '/') + url;
    }
    try {
      const response = await axios.get(url, { responseType: 'arraybuffer', timeout: 30000 });
      const buffer = Buffer.from(response.data);

      switch (type) {
        case 'document':
          await this.sock.sendMessage(to, {
            document: buffer,
            fileName: filename || 'document.pdf',
            mimetype: mimetype || 'application/pdf',
            caption: caption || undefined,
          });
          break;

        case 'image':
          await this.sock.sendMessage(to, { image: buffer, caption: caption || '' });
          break;

        case 'video':
          await this.sock.sendMessage(to, { video: buffer, caption: caption || '' });
          break;

        default:
          console.warn(`[AUTO-REPLY] Unsupported media type: ${type}`);
      }
      console.log(`[AUTO-REPLY] media SENT (${type}) to ${to}`);
    } catch (error) {
      // Was a SILENT empty catch — any fetch/send failure dropped the media
      // with no trace ("nothing going"). Log it, and fall back to the caption
      // text so the reply still reaches the customer.
      console.error(`[AUTO-REPLY] media send FAILED (${type}) url=${url}: ${error?.message || error}`);
      if (caption) {
        try { await this.sock.sendMessage(to, { text: caption }); } catch (e) {}
      }
    }
  }

  async updateStatus(status, percent, qrCode = null) {
    try {
      await updateStatusInLaravel(status, percent, this.phoneNumber, this.appDomainName, qrCode);
    } catch (error) {

    }
  }

  async deleteSessionFiles() {
    try {
      if (this.storeInterval) {
        clearInterval(this.storeInterval);
        this.storeInterval = null;
      }
      await fs.remove(this.authFolder);

    } catch (err) {

    }
  }

  /**
   * USER-INTENT logout. Sends WhatsApp's "unlink device" RPC (the
   * phone gets a "logged out" notification) and wipes local session
   * files so a fresh QR is required next time. Only call this from
   * an explicit "Disconnect" button in the UI.
   */
  async logout() {
    try {
      this.shouldStayConnected = false;
      this.isConnecting = false;
      this.isConnected = false;

      if (this.sock) {
        try {
          await this.sock.logout();
        } catch (error) {
          console.error(`[${this.phoneNumber}] sock.logout error:`, error?.message);
        }

        delete this.appLocals.clients[this.phoneNumber];
        delete this.appLocals.client_ready[this.phoneNumber];
      }

      const sessionKeys = Object.keys(this.appLocals.activeFlowSessions);
      sessionKeys.forEach(key => {
        if (key.startsWith(`${this.phoneNumber}_`)) {
          const session = this.appLocals.activeFlowSessions[key];
          if (session && session.timeoutTimer) {
            clearTimeout(session.timeoutTimer);
          }
          delete this.appLocals.activeFlowSessions[key];
          delete this.appLocals.userCooldowns[key];
        }
      });

      await this.deleteSessionFiles();

    } catch (error) {
      console.error(`[${this.phoneNumber}] logout error:`, error?.message);
    }
  }

  /**
   * SHUTDOWN-INTENT disconnect. Closes the websocket without telling
   * WhatsApp to unlink the device, and KEEPS the session files on
   * disk so the next Node restart can auto-restore. Use this from
   * SIGINT/SIGTERM and any other "we're going down, don't break the
   * pairing" path.
   */
  async softDisconnect() {
    this.shuttingDown = true;
    this.shouldStayConnected = false;
    this.isConnecting = false;
    this.isConnected = false;
    if (this.storeInterval) {
      clearInterval(this.storeInterval);
      this.storeInterval = null;
    }
    if (this.healthTimer) {
      clearInterval(this.healthTimer);
      this.healthTimer = null;
    }
    try {
      if (this.store && this.storeFile) {
        try { this.store.writeToFile(this.storeFile); } catch (e) {}
      }
      if (this.sock?.ws?.close) {
        try { this.sock.ws.close(); } catch (e) {}
      } else if (this.sock?.end) {
        try { this.sock.end(undefined); } catch (e) {}
      }
    } finally {
      delete this.appLocals.clients[this.phoneNumber];
      delete this.appLocals.client_ready[this.phoneNumber];
      console.log(`[${this.phoneNumber}] soft-disconnected (session files kept)`);
    }
  }

  getQRCode() {
    return this.qrCode;
  }

  async handleMessageStatusUpdate(updates) {
    for (const update of updates) {
      try {
        const messageId = update.key.id;
        const remoteJid = update.key.remoteJid;
        const status = update.update.status;

        if (status && messageId) {
          const map = { 1: "PENDING", 2: "SERVER_ACK", 3: "DELIVERY_ACK", 4: "READ", 5: "PLAYED" };
          console.log(`[${this.phoneNumber}] [STATUS] msg=${messageId} → ${map[status] || status} (jid=${remoteJid})`);
        }

        if (!status || !messageId) continue;

        // Baileys status enum:
        //   1 ERROR / PENDING (rarely seen on outbound)
        //   2 SERVER_ACK — WhatsApp's server got it, recipient hasn't yet
        //   3 DELIVERY_ACK — recipient device received it (single tick → double tick)
        //   4 READ — recipient opened the chat (blue ticks)
        //   5 PLAYED — voice/video note was played
        // We map only 3/4/5 to forward — 1/2 are transient and would
        // demote a "sent" pivot row back to "pending" needlessly.
        let statusName = null;
        let timestampField = null;
        if (status === 3) {
          statusName = "delivered";
          timestampField = "delivered_at";
        } else if (status === 4) {
          statusName = "read";
          timestampField = "read_at";
        } else if (status === 5) {
          // PLAYED — treat as read for downstream UI purposes.
          statusName = "read";
          timestampField = "read_at";
        }

        if (statusName) {
          const relatedBroadcast = this.appLocals.scheduledMessages.find(b =>
            b.sentMessages && b.sentMessages[messageId]
          );

          if (relatedBroadcast) {
            const contactInfo = relatedBroadcast.sentMessages[messageId];
            const url = `${this.appDomainName}/api/update-message-status`;
            const payload = {
              broadcast_id: relatedBroadcast.broadcastId,
              contact_id: contactInfo.contactId,
              status: statusName,
              whatsapp_message_id: messageId,
              [timestampField]: new Date().toISOString(),
            };
            try {
              const r = await axios.post(url, payload, { headers: laravelHeaders() });
              console.log(`[${this.phoneNumber}] [BCAST-RECEIPT] ${messageId} → ${statusName} (http ${r.status})`);
            } catch (error) {
              console.error(`[${this.phoneNumber}] [BCAST-RECEIPT] ${messageId} → ${statusName} FAILED`, {
                http: error?.response?.status,
                body: error?.response?.data,
                err: error?.message,
              });
            }
          } else {
            // Non-broadcast send (Quick Send / /chat / team-inbox). These were
            // previously ignored, so their ticks never moved past "sent"
            // (Quick Send showed Delivered 0 even after double/blue ticks). Push
            // the receipt to a dedicated endpoint that matches the messages row
            // by its stored WhatsApp message id (messages.meta->wa_message_id).
            try {
              const r = await axios.post(
                `${this.appDomainName}/api/chat/message-status`,
                {
                  whatsapp_message_id: messageId,
                  status: statusName,
                  [timestampField]: new Date().toISOString(),
                },
                { headers: laravelHeaders() }
              );
              console.log(`[${this.phoneNumber}] [CHAT-RECEIPT] ${messageId} → ${statusName} (http ${r.status})`);
            } catch (error) {
              console.error(`[${this.phoneNumber}] [CHAT-RECEIPT] ${messageId} → ${statusName} FAILED`, {
                http: error?.response?.status,
                err: error?.message,
              });
            }
          }
        }
      } catch (error) {
        console.error(`[${this.phoneNumber}] handleMessageStatusUpdate iteration crashed`, error?.message);
      }
    }
  }
}
