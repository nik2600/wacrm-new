//   // utils/helpers.js
//   // ==============================
//   // Utility Helper Functions
//   // ==============================

//   import axios from "axios";
//   import path from "path";
//   import fs from "fs-extra";
//   import moment from "moment-timezone";
//   import { fileURLToPath } from "url";

//   const __filename = fileURLToPath(import.meta.url);
//   const __dirname = path.dirname(__filename);

//   // Format phone number for Baileys
//   export function formatPhoneNumber(phoneNumber) {
//     const sanitized = phoneNumber.replace(/[- )(]/g, "");
//     return `${sanitized.substring(sanitized.length - 12)}@s.whatsapp.net`;
//   }

//   // Format interactive buttons for Baileys
//   export function formatInteractiveButtonsForBaileys(buttons) {
//   if (!buttons || !Array.isArray(buttons) || buttons.length === 0) return null;

//   const formattedButtons = [];

//   for (let i = 0; i < buttons.length; i++) {
//     const button = buttons[i];

//     switch (button.type) {
//       case 'visit_website':
//         formattedButtons.push({
//           name: 'cta_url',
//           buttonParamsJson: JSON.stringify({
//             display_text: button.text,
//             url: button.url,
//             merchant_url: button.value
//           })
//         });
//         break;

//       case 'call_phone':
//         formattedButtons.push({
//           name: 'cta_call',
//           buttonParamsJson: JSON.stringify({
//             display_text: button.text,
//             phone_number: button.value
//           })
//         });
//         break;

//       case 'copy_code':
//         formattedButtons.push({
//           name: 'cta_copy',
//           buttonParamsJson: JSON.stringify({
//             display_text: button.text,
//             copy_code: button.value
//           })
//         });
//         break;

//       case 'quick_reply':
//       default:
//         formattedButtons.push({
//           name: 'quick_reply',
//           buttonParamsJson: JSON.stringify({
//             display_text: button.text,
//             id: button.value || button.id || `btn_${i}`
//           })
//         });
//         break;
//     }
//   }

//   return formattedButtons.length > 0 ? formattedButtons : null;
// }


//   // Format buttons for Baileys (legacy quick reply)
//   export function formatButtonsForBaileys(buttons) {
//     if (!buttons || !Array.isArray(buttons) || buttons.length === 0) return null;
//     const formattedButtons = [];
//     for (let i = 0; i < Math.min(buttons.length, 3); i++) {
//       const button = buttons[i];
//       formattedButtons.push({
//         buttonId: button.value || `btn_${i}`,
//         buttonText: { displayText: button.text },
//         type: 1
//       });
//     }
//     return formattedButtons.length > 0 ? formattedButtons : null;
//   }

//   // Download and prepare media for Baileys
//   export async function downloadAndPrepareMediaBaileys(mediaUrl) {
//     const localFilePath = path.join(__dirname, `tempMedia_${Date.now()}.jpg`);
//     const response = await axios({
//       url: mediaUrl,
//       responseType: "stream",
//     });
//     const fileExtension = path.extname(mediaUrl) || ".jpg";
//     const fullPath = localFilePath + fileExtension;
//     const writer = fs.createWriteStream(fullPath);
//     response.data.pipe(writer);
//     return new Promise((resolve, reject) => {
//       writer.on("finish", () => {
//         const buffer = fs.readFileSync(fullPath);
//         fs.unlinkSync(fullPath);
//         resolve(buffer);
//       });
//       writer.on("error", reject);
//     });
//   }

//   // Update status in Laravel
//   export async function updateStatusInLaravel(status, percent, wid, appDomainName, qrCode = null) {
//     try {
//       const payload = {
//         status: status,
//         progress: percent,
//         wid: wid,
//       };
//       if (qrCode) {
//         payload.qr = qrCode;
//       }
//       await axios.post(appDomainName + "/api/update-status", payload);
//       void(0);
//     } catch (error) {
//       void(0);
//     }
//   }

//   // Update schedule status in Laravel
//   export async function updateScheduleStatusInLaravel(scheduleId, status, phoneNumber) {
//     try {
//       await axios.post(appDomainName + "/api/update-schedule-status", {
//         scheduleId: scheduleId,
//         status: status,
//         phoneNumber: phoneNumber,
//         timestamp: moment().format(),
//       });
//       console.log(
//         `Schedule status updated in Laravel: ${scheduleId} - ${status}`
//       );
//     } catch (error) {
//       void(0);
//     }
//   }

//   // Update bulk schedule status
//   export async function updateBulkScheduleStatus(
//     scheduleId,
//     status,
//     sentCount,
//     failedCount,
//     appDomainName
//   ) {
//     try {
//       await axios.post(appDomainName + "/api/update-schedule-status", {
//         scheduleId: scheduleId,
//         status: status,
//         totalSent: sentCount,
//         totalDelivered: sentCount,
//         timestamp: moment().format(),
//       });
//       void(0);
//     } catch (error) {
//       void(0);
//     }
//   }

//   // Fetch queue recipients from Laravel
//   export async function fetchQueueRecipients(queueIds, appDomainName) {
//     try {
//       const response = await axios.post(
//         appDomainName + "/api/get-queue-recipients",
//         {
//           queueIds: queueIds,
//         }
//       );
//       return response.data.recipients || [];
//     } catch (error) {
//       void(0);
//       return [];
//     }
//   }

//   // Get next node from _1 output
//   export function getNextNode(currentNodeId, flowData) {
//     const nextEdge = flowData.flowEdges.find(
//       (edge) => edge.sourceNodeId === `${currentNodeId}_1`
//     );
//     if (nextEdge) {
//       const nextNodeId = nextEdge.targetNodeId.split("_")[0];
//       return flowData.flowNodes.find((n) => n.id === nextNodeId);
//     }
//     return null;
//   }

//   // Move to next node in flow
//   export async function moveToNextNode(
//     currentNodeId,
//     flowData,
//     targetPhoneNumber,
//     senderPhoneNumber,
//     sock,
//     appLocals
//   ) {
//     const nextEdge = flowData.flowEdges.find(
//       (edge) => edge.sourceNodeId === `${currentNodeId}_1`
//     );
//     if (nextEdge) {
//       const nextNodeId = nextEdge.targetNodeId.split("_")[0];
//       const nextNode = flowData.flowNodes.find((n) => n.id === nextNodeId);
//       if (nextNode) {
//         await new Promise((resolve) => setTimeout(resolve, 1500));
//         const session = appLocals.activeFlowSessions[targetPhoneNumber];
//         const waited = await handlePostExecution(
//           currentNodeId,
//           session,
//           targetPhoneNumber,
//           senderPhoneNumber,
//           sock
//         );
//         if (!waited) {
//           await executeFlowNode(
//             nextNode,
//             targetPhoneNumber,
//             senderPhoneNumber,
//             sock,
//             appLocals
//           );
//         }
//       }
//     } else {
//       void(0);
//       if (appLocals.activeFlowSessions[targetPhoneNumber]) {
//         appLocals.activeFlowSessions[targetPhoneNumber].status = "completed";
//       }
//     }
//   }

//   // Handle post execution (set wait if needed)
//   export async function handlePostExecution(
//     currentNodeId,
//     session,
//     targetPhoneNumber,
//     senderPhoneNumber,
//     sock
//   ) {
//     const nextNode = getNextNode(currentNodeId, session.flowData);
//     if (nextNode && nextNode.flowNodeType === "Condition") {
//       let waitVar = nextNode.conditions[0]?.variable || "user_message";
//       session.waitingForInput = {
//         nodeId: nextNode.id,
//         variable: waitVar,
//         answerItems: [],
//         nextNodeType: "Condition",
//       };
//       console.log(
//         `Set waiting for ${nextNode.flowNodeType} input: var ${waitVar}`
//       );
//       return true;
//     }
//     return false;
//   }


// utils/helpers.js
// ==============================
// Utility Helper Functions
// ==============================

import axios from "axios";
import path from "path";
import fs from "fs-extra";
import moment from "moment-timezone";
import crypto from "crypto";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Format phone number for Baileys. Preserve the full E.164 digit string —
// the prior `substring(length - 12)` clobber truncated any 13-digit MSISDN
// (Argentina `5491156789012`, Mexico, Brazil) into a wrong destination,
// silently misrouting Baileys sends. We strip whitespace + punctuation but
// keep the leading digits intact. The optional leading `+` is also dropped
// because Baileys expects bare digits.
export function formatPhoneNumber(phoneNumber) {
  const sanitized = String(phoneNumber || "").replace(/\D+/g, "");
  return `${sanitized}@s.whatsapp.net`;
}

// Format interactive buttons for Baileys
export function formatInteractiveButtonsForBaileys(buttons, trackingId = null, appDomainName = null) {
  if (!buttons || !Array.isArray(buttons) || buttons.length === 0) {
    console.log(`[BTN-FMT] no buttons to format (in=${JSON.stringify(buttons)})`);
    return null;
  }

  // Hard cap: WhatsApp reliably renders only 3 interactive buttons. Anything
  // beyond 3 is silently dropped by the client or breaks the button block, so
  // we cap here — the single chokepoint every send path funnels through — to
  // guarantee at most 3 buttons leave the system in any condition.
  if (buttons.length > 3) {
    console.log(`[BTN-FMT] capping ${buttons.length} buttons to 3 (WhatsApp limit)`);
    buttons = buttons.slice(0, 3);
  }

  // Normalise every button-type alias the app/template editor might use to
  // the 4 canonical kinds. Without this, "copy_text"/"call"/"url" fell to the
  // default quick_reply case and rendered broken/empty.
  const TYPE_ALIASES = {
    // canonical Baileys cta_* names (legacy/already-formatted rows, carousel
    // data copied from old "working format" code) — accept them too so the
    // formatter is a strict superset of the old per-path switches.
    cta_url: 'visit_website', cta_call: 'call_phone', cta_copy: 'copy_code',
    // editor / Meta-style names
    visit_website: 'visit_website', url: 'visit_website', website: 'visit_website', link: 'visit_website',
    call_phone: 'call_phone', call: 'call_phone', phone: 'call_phone', phone_number: 'call_phone',
    copy_code: 'copy_code', copy: 'copy_code', copy_text: 'copy_code', coupon: 'copy_code',
    quick_reply: 'quick_reply', reply: 'quick_reply', 'quick reply': 'quick_reply',
  };

  const formattedButtons = [];
  const trace = [];

  for (let i = 0; i < buttons.length; i++) {
    const button = buttons[i] || {};
    const rawType = String(button.type || '').toLowerCase().trim();
    const type = TYPE_ALIASES[rawType] || 'quick_reply';
    const text = button.text || button.display_text || button.title || 'Button';
    // URL/value may live under several keys depending on source (web stores it
    // in `value`; the mobile-app API sends `url` / `phone` / `code`).
    const val = button.value ?? button.url ?? button.phone_number ?? button.phone ?? button.copy_code ?? button.code ?? '';

    if (type === 'visit_website') {
      let finalUrl = button.url || button.value || '';
      if (finalUrl && !/^https?:\/\//i.test(finalUrl)) finalUrl = 'https://' + finalUrl;
      if (!finalUrl) { trace.push(`#${i} ${rawType}→url DROPPED(empty url)`); continue; }
      if (trackingId && appDomainName && finalUrl) {
        try {
          const encodedUrl = Buffer.from(finalUrl).toString('base64');
          finalUrl = `${appDomainName}/c/${trackingId}?url=${encodedUrl}`;
        } catch (e) { console.error('[BTN-FMT] url tracking rewrite failed', e); }
      }
      formattedButtons.push({ name: 'cta_url', buttonParamsJson: JSON.stringify({ display_text: text, url: finalUrl, merchant_url: button.value || finalUrl }) });
      trace.push(`#${i} ${rawType}→cta_url ok`);
    } else if (type === 'call_phone') {
      const phone = button.value || button.phone_number || button.phone || button.url || '';
      if (!phone) { trace.push(`#${i} ${rawType}→call DROPPED(empty phone)`); continue; }
      formattedButtons.push({ name: 'cta_call', buttonParamsJson: JSON.stringify({ display_text: text, phone_number: phone }) });
      trace.push(`#${i} ${rawType}→cta_call ok`);
    } else if (type === 'copy_code') {
      const code = button.value || button.copy_code || button.code || '';
      if (!code) { trace.push(`#${i} ${rawType}→copy DROPPED(empty code)`); continue; }
      formattedButtons.push({ name: 'cta_copy', buttonParamsJson: JSON.stringify({ display_text: text, copy_code: code }) });
      trace.push(`#${i} ${rawType}→cta_copy ok`);
    } else {
      // quick_reply — always valid (id falls back to a synthetic value).
      formattedButtons.push({ name: 'quick_reply', buttonParamsJson: JSON.stringify({ display_text: text, id: val || button.id || `btn_${i}` }) });
      trace.push(`#${i} ${rawType}→quick_reply ok`);
    }
  }

  console.log(`[BTN-FMT] in=${buttons.length} out=${formattedButtons.length} | ${trace.join(' | ')}`);
  return formattedButtons.length > 0 ? formattedButtons : null;
}

// Format buttons for Baileys (legacy quick reply)
export function formatButtonsForBaileys(buttons) {
  if (!buttons || !Array.isArray(buttons) || buttons.length === 0) return null;
  const formattedButtons = [];
  for (let i = 0; i < Math.min(buttons.length, 3); i++) {
    const button = buttons[i];
    formattedButtons.push({
      buttonId: button.value || `btn_${i}`,
      buttonText: { displayText: button.text },
      type: 1
    });
  }
  return formattedButtons.length > 0 ? formattedButtons : null;
}

// SECURITY: allow-list of base directories a local (non-http) media path may
// resolve inside. Any `file` value that isn't an http(s) URL is treated as a
// local path, so without containment an attacker could ask Node to read the
// Laravel .env, session creds, /etc/passwd, etc. and deliver them over
// WhatsApp (arbitrary file read + exfiltration). Legit local media only ever
// lives under the Laravel `public/` (uploads) and `storage/` trees, or Node's
// own temp dir. Operators can extend via MEDIA_READ_BASES (comma-separated
// absolute paths). Defaults are fully secure.
function getAllowedMediaBases() {
  const projectRoot = path.resolve(__dirname, "..", "..");
  const bases = [
    path.join(projectRoot, "public"),
    path.join(projectRoot, "storage"),
    path.resolve(__dirname),
  ];
  const extra = String(process.env.MEDIA_READ_BASES || "")
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean)
    .map((s) => path.resolve(s));
  return bases.concat(extra);
}

// True when `resolved` is the same as, or nested under, `base`. Uses a path
// separator boundary so `/a/storage-evil` is NOT accepted for base `/a/storage`.
function isInsideBase(resolved, base) {
  const rel = path.relative(base, resolved);
  return rel === "" || (!rel.startsWith("..") && !path.isAbsolute(rel));
}

// Resolve a caller-supplied local media path and confirm it is contained
// within an allow-listed base. Throws (fail closed) on traversal / absolute
// paths pointing outside, or symlink escapes. Returns the safe resolved path.
function resolveContainedLocalMedia(mediaUrl) {
  const bases = getAllowedMediaBases();
  const resolved = path.resolve(String(mediaUrl || ""));
  const contained = bases.some((b) => isInsideBase(resolved, b));
  if (!contained) {
    throw new Error("Local media path is outside the allowed media directory");
  }
  // Defeat symlink escapes: if the target exists, its real path must ALSO be
  // contained (a symlink under public/ pointing at /etc/passwd is rejected).
  try {
    const real = fs.realpathSync(resolved);
    if (!bases.some((b) => isInsideBase(real, b))) {
      throw new Error("Local media path escapes the allowed media directory");
    }
    return real;
  } catch (e) {
    if (e && /escapes the allowed/.test(String(e.message))) throw e;
    // realpath failed only because the file doesn't exist yet — the lexical
    // containment check above already passed, so return the resolved path and
    // let the existence check below produce the normal "File not found".
    return resolved;
  }
}

// 🔥 FIXED: Download and prepare media for Baileys with proper mimetype detection
// 🔥 FIXED: Download and prepare media for Baileys with proper mimetype detection and local file support
// SSRF guard for remote media fetches (#17). Refuses private / loopback /
// link-local / cloud-metadata targets so a crafted mediaUrl (e.g.
// http://169.254.169.254/... or http://10.x) cannot reach internal services.
function _mediaIpBlocked(ip) {
  if (!ip) return true;
  ip = String(ip);
  const m = ip.match(/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i);
  if (m) ip = m[1];
  if (ip.includes(":")) {
    const low = ip.toLowerCase();
    if (low === "::1" || low === "::") return true;               // loopback / unspecified
    if (low.startsWith("fe80")) return true;                       // link-local
    if (low.startsWith("fc") || low.startsWith("fd")) return true; // unique-local fc00::/7
    return false;
  }
  const p = ip.split(".").map(Number);
  if (p.length !== 4 || p.some((n) => Number.isNaN(n) || n < 0 || n > 255)) return true;
  const [a, b] = p;
  if (a === 10 || a === 127 || a === 0) return true;
  if (a === 169 && b === 254) return true;                         // link-local incl 169.254.169.254 (IMDS)
  if (a === 172 && b >= 16 && b <= 31) return true;
  if (a === 192 && b === 168) return true;
  if (a === 100 && b >= 64 && b <= 127) return true;               // CGNAT 100.64/10
  if (a >= 224) return true;                                       // multicast / reserved
  return false;
}
async function assertPublicMediaUrl(rawUrl) {
  let u;
  try { u = new URL(String(rawUrl)); } catch { throw new Error("Invalid media URL"); }
  if (u.protocol !== "http:" && u.protocol !== "https:") throw new Error("Blocked media URL scheme");
  if (_mediaIpBlocked(u.hostname)) throw new Error("Blocked internal media host");
  try {
    const dns = await import("node:dns");
    const addrs = await dns.promises.lookup(u.hostname, { all: true });
    for (const addr of addrs) {
      if (_mediaIpBlocked(addr.address)) throw new Error("Blocked internal media host");
    }
  } catch (e) {
    if (String((e && e.message) || "").includes("Blocked")) throw e;
    // DNS resolution failure — let axios surface the network error; do not open a hole.
  }
}

export async function downloadAndPrepareMediaBaileys(mediaUrl) {
  try {
    // Check if it's a remote URL
    const isRemote = mediaUrl.startsWith("http://") || mediaUrl.startsWith("https://");

    if (!isRemote) {
      // HANDLE LOCAL FILE — contain to the allow-listed media directories.
      const safePath = resolveContainedLocalMedia(mediaUrl);
      console.log(`Reading local media: ${safePath}`);

      if (!fs.existsSync(safePath)) {
        throw new Error(`File not found: ${safePath}`);
      }

      const fileExtension = path.extname(safePath) || ".jpg";
      const buffer = fs.readFileSync(safePath);
      const mimetype = getMimeTypeFromExtension(fileExtension);
      
      console.log(`Local file read. Ext: ${fileExtension}, Mime: ${mimetype}`);

      return {
        buffer: buffer,
        mimetype: mimetype,
        extension: fileExtension
      };
    }

    // HANDLE REMOTE URL — SSRF-guard the destination before fetching (#17).
    await assertPublicMediaUrl(mediaUrl);
    const localFilePath = path.join(__dirname, `tempMedia_${Date.now()}.jpg`);

    const response = await axios({
      url: mediaUrl,
      responseType: "stream",
      maxRedirects: 3,
    });
    
    // Get file extension from URL
    const fileExtension = path.extname(mediaUrl) || ".jpg";
    const fullPath = localFilePath + fileExtension;
    
    // Get mimetype from response headers or determine from extension
    let mimetype = response.headers['content-type'];
    
    // If mimetype not in headers, determine from file extension
    if (!mimetype) {
      mimetype = getMimeTypeFromExtension(fileExtension);
    }
    
    console.log(`Downloading media: ${mediaUrl}`);
    console.log(`File extension: ${fileExtension}`);
    console.log(`Detected mimetype: ${mimetype}`);
    
    const writer = fs.createWriteStream(fullPath);
    response.data.pipe(writer);
    
    return new Promise((resolve, reject) => {
      writer.on("finish", () => {
        const buffer = fs.readFileSync(fullPath);
        fs.unlinkSync(fullPath);
        
        // Return buffer with mimetype
        resolve({
          buffer: buffer,
          mimetype: mimetype,
          extension: fileExtension
        });
      });
      writer.on("error", (error) => {
        console.error("Error downloading media:", error);
        reject(error);
      });
    });
  } catch (error) {
    console.error("Error in downloadAndPrepareMediaBaileys:", error.message);
    throw error;
  }
}

// Helper function to get mimetype from file extension
function getMimeTypeFromExtension(extension) {
  const ext = extension.toLowerCase().replace('.', '');
  
  const mimeTypes = {
    // Images
    'jpg': 'image/jpeg',
    'jpeg': 'image/jpeg',
    'png': 'image/png',
    'gif': 'image/gif',
    'webp': 'image/webp',
    'bmp': 'image/bmp',
    'svg': 'image/svg+xml',
    
    // Videos
    'mp4': 'video/mp4',
    'avi': 'video/x-msvideo',
    'mov': 'video/quicktime',
    'wmv': 'video/x-ms-wmv',
    'flv': 'video/x-flv',
    'mkv': 'video/x-matroska',
    '3gp': 'video/3gpp',
    
    // Audio
    'mp3': 'audio/mpeg',
    'wav': 'audio/wav',
    'ogg': 'audio/ogg',
    'aac': 'audio/aac',
    'm4a': 'audio/mp4',
    'opus': 'audio/opus',
    
    // Documents
    'pdf': 'application/pdf',
    'doc': 'application/msword',
    'docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls': 'application/vnd.ms-excel',
    'xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt': 'application/vnd.ms-powerpoint',
    'pptx': 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'txt': 'text/plain',
    'csv': 'text/csv',
    'zip': 'application/zip',
    'rar': 'application/x-rar-compressed',
  };
  
  return mimeTypes[ext] || 'application/octet-stream';
}

/**
 * Headers for every callback into Laravel. The shared NODE_WEBHOOK_TOKEN
 * is the only thing on the Laravel side that distinguishes a real bot
 * call from any random POST to /api/update-schedule-status. If the env
 * var isn't set we still send (legacy installs) — Laravel will then
 * accept any caller, matching the dev-friendly default.
 */
export function laravelHeaders() {
  const token = process.env.NODE_WEBHOOK_TOKEN || "";
  return token ? { "X-Node-Token": token } : {};
}

/**
 * Constant-time comparison of the X-Node-Token shared secret. The Laravel side
 * uses hash_equals() for the identical secret; the Node side historically used
 * plain ===/!== which short-circuits on the first differing byte and leaks a
 * prefix-length timing side-channel (finding #50). This mirrors hash_equals:
 * length-guarded, fails closed on empty, and never throws on mismatched length.
 */
export function timingSafeEqualStr(a, b) {
  const bufA = Buffer.from(String(a == null ? "" : a), "utf8");
  const bufB = Buffer.from(String(b == null ? "" : b), "utf8");
  // Empty expected/candidate → never authorize. timingSafeEqual requires equal
  // lengths, so bail early (the length check is not itself secret-dependent).
  if (bufA.length === 0 || bufB.length === 0) return false;
  if (bufA.length !== bufB.length) return false;
  return crypto.timingSafeEqual(bufA, bufB);
}

/**
 * Pull the latest sender-pacing settings (msg_gap / batches_gap / bw_msg_gap /
 * enable_batches) from Laravel and write them into app.locals.messageSettings.
 *
 * Call this at the START of every bulk run (campaign / broadcast / scheduled)
 * instead of trusting the cached app.locals copy. That cache is only refreshed
 * on Node boot, on the hourly timer, or when an admin save successfully pings
 * /api/refresh-settings — so if Node started before the admin set "120s", or
 * the refresh ping didn't reach Node, the loop would pace with a STALE value.
 * That is the exact "I set a 120s gap but the campaign sent instantly" report:
 * the value was saved in Laravel but Node was still holding the old/default 3s.
 * A fresh pull right before the loop makes the admin's saved value authoritative
 * on every run. Best-effort: on any failure it leaves the existing cache intact.
 */
export async function refreshMessageSettings(app, appDomainName) {
  if (!app || !app.locals || !appDomainName) return;
  try {
    const response = await axios.get(`${appDomainName}/api/whatsapp-message-settings`, {
      timeout: 5000,
      headers: laravelHeaders(),
    });
    if (response.data && response.data.success) {
      app.locals.messageSettings = {
        ...(app.locals.messageSettings || {}),
        msg_gap: response.data.msg_gap || 3,
        enable_batches: response.data.enable_batches || 0,
        batches_gap: response.data.batches_gap || 50,
        bw_msg_gap: response.data.bw_msg_gap || 5,
        last_fetched: new Date().toISOString(),
      };
      console.log(`[PACING] refreshed before run: msg_gap=${app.locals.messageSettings.msg_gap}s enable_batches=${app.locals.messageSettings.enable_batches} batch_size=${app.locals.messageSettings.batches_gap} between_batch=${app.locals.messageSettings.bw_msg_gap}min`);
    }
  } catch (e) {
    console.warn(`[PACING] pre-run refresh failed; using cached msg_gap=${app?.locals?.messageSettings?.msg_gap}s: ${e?.message}`);
  }
}

/**
 * Deep diagnostic for a failed Laravel call — prints exactly why a 401/403
 * happened so you can line up the token without leaking the full secret.
 * Shows the URL hit, whether (and how long) the X-Node-Token was sent, a
 * short non-sensitive preview, the HTTP status and the response body.
 */
export function logLaravelError(label, url, error) {
  const t = process.env.NODE_WEBHOOK_TOKEN || "";
  const status = error?.response?.status;
  let body = error?.response?.data;
  try {
    body = typeof body === "string" ? body : JSON.stringify(body);
  } catch (e) { body = String(body); }
  if (typeof body === "string" && body.length > 300) body = body.slice(0, 300) + "…";

  console.error(`❌ [${label}] ${error?.message || "request failed"}`);
  console.error(`   ↳ URL: ${url}`);
  console.error(
    `   ↳ X-Node-Token sent: ${t ? "yes" : "NO — env NODE_WEBHOOK_TOKEN is empty!"}` +
    (t ? ` (len=${t.length}, preview=${t.slice(0, 4)}…${t.slice(-2)})` : "")
  );
  if (status !== undefined) console.error(`   ↳ HTTP status: ${status}`);
  if (body !== undefined && body !== "undefined") console.error(`   ↳ Response body: ${body}`);
  if (status === 401 || status === 403) {
    console.error(
      "   ↳ HINT: 401/403 here means the X-Node-Token did NOT match. Make Node's " +
      "NODE_WEBHOOK_TOKEN (in node/.env) identical to Laravel's NODE_WEBHOOK_TOKEN " +
      "(in the Laravel .env), then run `php artisan optimize:clear` and `pm2 restart`. " +
      "If a DB row system_settings.node_webhook_token / baileys_callback_token exists it " +
      "OVERRIDES the .env on the Laravel side — it must match too."
    );
  }
}

// Update status in Laravel
export async function updateStatusInLaravel(status, percent, wid, appDomainName, qrCode = null) {
  try {
    const payload = {
      status: status,
      progress: percent,
      wid: wid,
    };
    if (qrCode) {
      payload.qr = qrCode;
    }
    await axios.post(appDomainName + "/api/update-status", payload, { headers: laravelHeaders() });
    console.log(`Status updated: ${status} (${percent}%)`);
  } catch (error) {
    console.error("Error updating status:", error.message);
  }
}

// Update schedule status in Laravel
export async function updateScheduleStatusInLaravel(scheduleId, status, phoneNumber) {
  try {
    await axios.post(appDomainName + "/api/update-schedule-status", {
      scheduleId: scheduleId,
      status: status,
      phoneNumber: phoneNumber,
      timestamp: moment().format(),
    }, { headers: laravelHeaders() });
    console.log(
      `Schedule status updated in Laravel: ${scheduleId} - ${status}`
    );
  } catch (error) {
    console.error("Error updating schedule status in Laravel:", error);
  }
}

// Update bulk schedule status
export async function updateBulkScheduleStatus(
  scheduleId,
  status,
  sentCount,
  failedCount,
  appDomainName
) {
  // Retry the status report instead of fire-and-forget. A swallowed failure
  // here leaves the row stuck at its PREVIOUS status in Laravel (e.g. still
  // 'scheduled' after a send completed), which both shows the wrong state in
  // /scheduled AND is the precondition for a past-due re-fire. 3 attempts with
  // short backoff turn a transient Laravel blip from a lost update into a
  // recovered one. Still best-effort — never throws into the caller.
  const payload = {
    scheduleId: scheduleId,
    status: status,
    totalSent: sentCount,
    // Honesty fix: do NOT fabricate "delivered" as equal to sent. `sentCount` only
    // means the socket accepted the message (single tick), NOT that WhatsApp
    // relayed it to the recipient's device. Report 0 CONFIRMED deliveries — a real
    // count only comes from actual DELIVERY_ACK receipts (see handleMessageStatus
    // Update). This stops the "marked Delivered but never received" false status.
    totalDelivered: 0,
    timestamp: moment().format(),
  };
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      await axios.post(appDomainName + "/api/update-schedule-status", payload, { headers: laravelHeaders() });
      console.log(`Bulk schedule status updated: ${scheduleId} - ${status}${attempt > 1 ? ` (attempt ${attempt})` : ""}`);
      return;
    } catch (error) {
      if (attempt >= 3) {
        console.error(`Error updating bulk schedule status after ${attempt} attempts:`, error.message);
        return;
      }
      await new Promise((r) => setTimeout(r, attempt * 1500));
    }
  }
}

// Fetch queue recipients from Laravel
export async function fetchQueueRecipients(queueIds, appDomainName) {
  try {
    const response = await axios.post(
      appDomainName + "/api/get-queue-recipients",
      {
        queueIds: queueIds,
      },
      { headers: laravelHeaders() }
    );
    return response.data.recipients || [];
  } catch (error) {
    console.error("Error fetching queue recipients:", error);
    return [];
  }
}

// Get next node from _1 output
export function getNextNode(currentNodeId, flowData) {
  const nextEdge = flowData.flowEdges.find(
    (edge) => edge.sourceNodeId === `${currentNodeId}_1`
  );
  if (nextEdge) {
    const nextNodeId = nextEdge.targetNodeId.split("_")[0];
    return flowData.flowNodes.find((n) => n.id === nextNodeId);
  }
  return null;
}

// Move to next node in flow
export async function moveToNextNode(
  currentNodeId,
  flowData,
  targetPhoneNumber,
  senderPhoneNumber,
  sock,
  appLocals
) {
  const nextEdge = flowData.flowEdges.find(
    (edge) => edge.sourceNodeId === `${currentNodeId}_1`
  );
  if (nextEdge) {
    const nextNodeId = nextEdge.targetNodeId.split("_")[0];
    const nextNode = flowData.flowNodes.find((n) => n.id === nextNodeId);
    if (nextNode) {
      await new Promise((resolve) => setTimeout(resolve, 1500));
      const session = appLocals.activeFlowSessions[targetPhoneNumber];
      const waited = await handlePostExecution(
        currentNodeId,
        session,
        targetPhoneNumber,
        senderPhoneNumber,
        sock
      );
      if (!waited) {
        await executeFlowNode(
          nextNode,
          targetPhoneNumber,
          senderPhoneNumber,
          sock,
          appLocals
        );
      }
    }
  } else {
    console.log("Flow end");
    if (appLocals.activeFlowSessions[targetPhoneNumber]) {
      appLocals.activeFlowSessions[targetPhoneNumber].status = "completed";
    }
  }
}

// Handle post execution (set wait if needed)
export async function handlePostExecution(
  currentNodeId,
  session,
  targetPhoneNumber,
  senderPhoneNumber,
  sock
) {
  const nextNode = getNextNode(currentNodeId, session.flowData);
  if (nextNode && nextNode.flowNodeType === "Condition") {
    let waitVar = nextNode.conditions[0]?.variable || "user_message";
    session.waitingForInput = {
      nodeId: nextNode.id,
      variable: waitVar,
      answerItems: [],
      nextNodeType: "Condition",
    };
    console.log(
      `Set waiting for ${nextNode.flowNodeType} input: var ${waitVar}`
    );
    return true;
  }
  return false;
}

/**
 * Get WhatsApp settings from Laravel (Centralized)
 */
// Per-phone settings cache — KEYED BY params so each workspace's WABA
// config doesn't poison the next tenant's send. Pre-2026-05-24 this
// was a single shared variable: workspace A's access_token cached
// and got served to every workspace B send for 60 seconds → cross-
// tenant credential leak via cache. Now we key by phone (or '*' if
// no phone) so each tenant has its own slot.
const __settingsCache = new Map(); // key → { data, at }

/**
 * Wipe the per-phone settings cache so the next getWhatsAppSettings()
 * call refetches from Laravel. Called from `POST /api/cache-bust` when
 * the operator changes WABA config / branding footer / platform footer
 * so Node picks up the new value immediately instead of waiting for
 * the 5-min TTL.
 *
 * `phone` is the device phone (matches the cache key). Pass `null` to
 * flush everything.
 */
export function flushSettingsCache(phone = null) {
  if (phone === null || phone === undefined || phone === '') {
    const n = __settingsCache.size;
    __settingsCache.clear();
    console.log(`[CACHE-BUST] flushed entire settings cache (${n} entries)`);
    return n;
  }
  const key = __cacheKey({ phone });
  if (__settingsCache.delete(key)) {
    console.log(`[CACHE-BUST] flushed entry key=${key}`);
    return 1;
  }
  console.log(`[CACHE-BUST] no entry to flush for key=${key}`);
  return 0;
}
// 5 min — paired with the background refresh loop in node/index.js
// (startSettingsRefreshLoop). Each tick pre-populates the cache for
// every connected phone BEFORE the TTL expires, so /chat hot-path sends
// always read from cache and never block on Laravel. If a tick is
// dropped (Laravel timeout under load), the stale fallback kicks in.
const __SETTINGS_TTL_MS = 5 * 60_000;

function __cacheKey(params) {
  // Phone + workspace_id together — phone alone is NOT unique across
  // tenants. If two workspaces ever share a phone (re-pair scenario
  // or rare collision), a phone-only cache key would return one
  // tenant's WABA token to the other tenant on a cache hit. workspace_id
  // closes that door: distinct workspaces never share a cache entry.
  const phone = String(params?.phone || params?.phone_number || '').replace(/\D+/g, '');
  const wsId  = String(params?.workspace_id || params?.ws_id || '').replace(/\D+/g, '');
  return (wsId ? wsId + ':' : '') + (phone || '*');
}

export async function getWhatsAppSettings(appDomainName, params = {}) {
  const now = Date.now();
  const key = __cacheKey(params);
  const cached = __settingsCache.get(key);
  if (cached && (now - cached.at) < __SETTINGS_TTL_MS) {
    return cached.data;
  }

  const startTime = Date.now();
  console.log(`[GET-SETTINGS] Starting request to ${appDomainName}/api/whatsapp-settings (key=${key})`);

  try {
    const response = await axios.get(`${appDomainName}/api/whatsapp-settings`, {
      params,
      // 8s — Laravel resolves the workspace WABA config (decrypts +
      // scan-matches by phone), then BrandingFooterService::resolve
      // (which checks the plan flag). On cold caches this exceeds 3s
      // on slower hosts. Result is cached on Node for 60s, so this
      // only hits hard once per minute per workspace.
      timeout: 8000,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...laravelHeaders(),
      }
    });

    const duration = Date.now() - startTime;
    console.log(`[GET-SETTINGS] Success in ${duration}ms (key=${key})`);

    // Stamp a flag so the error path can tell "real cached data" from
    // "default fallback" — only real responses get reused on subsequent
    // timeouts.
    const enriched = { ...response.data, last_success: true };
    __settingsCache.set(key, { data: enriched, at: now });
    return enriched;
  } catch (error) {
    const duration = Date.now() - startTime;
    console.error(`[GET-SETTINGS] Error after ${duration}ms:`, error.message);

    if (error.code === 'ECONNABORTED') {
      console.error('[GET-SETTINGS] Request timed out after 10 seconds');
    } else if (error.response) {
      console.error(`[GET-SETTINGS] HTTP ${error.response.status}:`, error.response.data);
    } else if (error.request) {
      console.error('[GET-SETTINGS] No response received from Laravel');
    } else {
      console.error('[GET-SETTINGS] Request setup error:', error.message);
    }

    // Return safe defaults — but DON'T cache them. Previously we
    // cached the failed fallback for 60s, which meant a single timeout
    // (e.g. Laravel session-lock contention) blackholed branding_footer
    // + WABA detection for a full minute. Now: if a prior call SUCCEEDED
    // and we have stale-but-real data, return that; otherwise return
    // fallback without caching so the next send retries Laravel.
    const stale = __settingsCache.get(key);
    if (stale && stale.data && stale.data.last_success === true) {
      console.log(`[GET-SETTINGS] Returning stale-but-valid data while Laravel is slow (key=${key})`);
      return stale.data;
    }

    // Last-resort: borrow the boot-time globally-fetched branding_footer
    // from app.locals (populated by fetchWhatsAppSettings in node/index.js).
    // Boot runs BEFORE any browser session lock is in play, so its fetch
    // almost always succeeds. Per-send timeouts can then still apply the
    // footer instead of silently dropping to null.
    let bootFooter = null;
    try {
      // global.appLocals is set by node/index.js so we don't need the
      // app reference here. Falls back gracefully when missing.
      bootFooter = global.appLocals?.whatsappSettings?.branding_footer ?? null;
    } catch (e) {}

    const fallback = {
      use_facebook_api: false,
      facebook_phone_id: null,
      facebook_api_token: null,
      branding_footer: bootFooter,
    };
    console.log(`[GET-SETTINGS] Using default fallback (NOT cached — next send retries) | boot_footer=${bootFooter === null ? 'null' : `"${bootFooter}"`}`);
    return fallback;
  }
}

/**
 * Send message via Facebook WhatsApp Business API (Centralized)
 */
/**
 * Turn an axios error from a Graph API call into META'S REAL human message
 * (error_user_msg / error_data.details) with code/subcode/trace appended —
 * mirrors PHP App\Services\Waba\MetaError::describe(). Falls back to the
 * generic message or the transport error when no Graph error body is present.
 */
export function formatMetaError(error) {
  const g = error?.response?.data?.error;
  if (!g) return error?.message || 'Unknown send error';
  const primary = String(g.error_user_msg || g.error_data?.details || g.message || 'Meta error').trim();
  const title = String(g.error_user_title || '').trim();
  const lead = (title && !primary.toLowerCase().includes(title.toLowerCase())) ? `${title} — ${primary}` : primary;
  const bits = [];
  if (g.code != null) bits.push(`code ${g.code}${g.error_subcode ? '/' + g.error_subcode : ''}`);
  if (g.fbtrace_id) bits.push(`trace ${g.fbtrace_id}`);
  return bits.length ? `${lead}  (${bits.join(', ')})` : lead;
}

export async function sendMessageViaFacebookApi(targetPhoneNumber, messageData, settings) {
  const facebookPhoneId = settings.facebook_phone_id;
  const bearerToken = settings.facebook_api_token;
  const startTime = Date.now();

  console.log(`[SEND-FB-API] Starting Facebook API request`);
  console.log(`[SEND-FB-API] Phone ID: ${facebookPhoneId}`);
  console.log(`[SEND-FB-API] To: ${targetPhoneNumber}`);

  try {
    // Resolution order:
    //   1. settings.facebook_app_version — admin-set via SystemSetting
    //      `waba_graph_api_version`, delivered by /api/whatsapp-settings.
    //   2. FACEBOOK_API_VERSION env — boot fallback for installs that
    //      haven't seeded the system_setting row.
    //   3. v23.0 — stable default. Meta keeps versions live ~2 years,
    //      so a hardcoded floor here protects against the Laravel
    //      response missing the version key entirely.
    // Previously hardcoded "v21.0" which was reaching end-of-life and
    // ignored whatever admin configured.
    const apiVersion = settings.facebook_app_version || process.env.FACEBOOK_API_VERSION || "v23.0";
    const endpoint = `https://graph.facebook.com/${apiVersion}/${facebookPhoneId}/messages`;

    // Ensure targetPhoneNumber is digits only (no + prefix per official API spec)
    let formattedTo = String(targetPhoneNumber).replace(/\D/g, "");

    const payload = {
      messaging_product: "whatsapp",
      recipient_type: "individual",
      to: formattedTo,
      ...messageData
    };

    console.log(`[SEND-FB-API] Payload type: ${messageData.type}`);

    const response = await axios.post(endpoint, payload, {
      headers: {
        Authorization: `Bearer ${bearerToken}`,
        "Content-Type": "application/json",
      },
      timeout: 30000, // 30 seconds timeout for Facebook API
    });

    const duration = Date.now() - startTime;
    console.log(`[SEND-FB-API] ✅ Success in ${duration}ms`);
    console.log(`[SEND-FB-API] Message ID: ${response.data.messages?.[0]?.id}`);

    return {
      success: true,
      messageId: response.data.messages?.[0]?.id || `fb_${Date.now()}`,
    };
  } catch (error) {
    const duration = Date.now() - startTime;
    console.error(`[SEND-FB-API] ❌ Error after ${duration}ms:`, error.message);

    if (error.code === 'ECONNABORTED') {
      console.error('[SEND-FB-API] Request timed out after 30 seconds');
    } else if (error.response) {
      console.error(`[SEND-FB-API] HTTP ${error.response.status}:`, JSON.stringify(error.response.data));
    } else if (error.request) {
      console.error('[SEND-FB-API] No response received from Facebook');
    }

    // Surface META'S REAL error (error_user_msg / error_data.details + code +
    // trace), not just the generic `message`, so the operator sees the exact
    // reason and can act on it. Mirrors the PHP MetaError helper.
    return {
      success: false,
      error: formatMetaError(error),
    };
  }
}

/**
 * Send message via Twilio WhatsApp REST API.
 *
 * Twilio bypasses Node's WhatsApp transport entirely — it's a separate
 * cloud provider with its own auth (basic auth: account_sid + auth_token)
 * and its own message endpoint. Whereas WABA goes through Meta's Graph
 * API and Baileys goes through the local sock, Twilio is HTTP-direct.
 *
 * Mirrors `sendMessageViaFacebookApi` so flow / campaign / broadcast
 * code can swap one for the other based on `settings.use_twilio`.
 *
 * Credentials come from `/api/whatsapp-settings`:
 *   - settings.twilio_account_sid
 *   - settings.twilio_auth_token
 *   - settings.twilio_from_number   (E.164 sender, e.g. "+14155238886")
 *
 * `payload` accepts:
 *   - { type: 'text', body: '...' }
 *   - { type: 'media', mediaUrl: '...', body?: '...' }    (image/video/audio/doc)
 *   - { type: 'location', latitude: 1.23, longitude: 4.56, name?: '...' }
 *   - { type: 'template', contentSid: 'HX...', contentVariables?: {1:'x',2:'y'} }
 *
 * Templates: pass the approved `ContentSid` from Twilio's Content Builder
 * + a positional-keyed variables object. `contentVariables` is serialized
 * to a JSON string before send, matching Twilio's wire format. This is
 * the ONLY way to keep MARKETING/UTILITY/AUTHENTICATION categories
 * compliant on Twilio — plain Body text outside the 24h session window
 * risks Twilio number suspension.
 *
 * Interactive (button/list) sends without a ContentSid are intentionally
 * NOT supported: Twilio routes interactive content through its Content
 * Templates API which requires a submit-and-approve flow. Flow nodes that
 * need interactive replies on Twilio without an approved template should
 * fall back to a plain text prompt with numbered options + parse the
 * reply.
 */
export async function sendMessageViaTwilioApi(targetPhoneNumber, payload, settings) {
  const sid    = settings?.twilio_account_sid;
  const token  = settings?.twilio_auth_token;
  const from   = settings?.twilio_from_number;
  const startTime = Date.now();

  if (!sid || !token || !from) {
    console.error('[SEND-TWILIO] missing creds — sid/token/from required');
    return { success: false, error: 'Twilio creds missing on settings — re-fetch /api/whatsapp-settings.' };
  }

  // Twilio expects digits-only `To` prefixed with `whatsapp:`.
  // Twilio's WhatsApp transport requires `whatsapp:+E164` — the `+` is
  // part of the spec, not optional. Always emit the `+` after stripping
  // any pre-existing prefix or non-digit characters.
  const formattedTo   = 'whatsapp:+' + String(targetPhoneNumber).replace(/\D/g, '');
  const formattedFrom = 'whatsapp:+' + String(from).replace(/^whatsapp:\+?/, '').replace(/\D/g, '');

  const form = new URLSearchParams();
  form.append('From', formattedFrom);
  form.append('To',   formattedTo);

  // Twilio StatusCallback — without this, Twilio never POSTs back
  // delivered/read/failed events and broadcasts stay frozen at
  // status='sent' forever. The receiver is gated on X-Twilio-Signature
  // so the public URL is safe to expose. APP_DOMAIN_NAME comes from
  // env, falls back to settings.app_domain. Skip when neither is set
  // (e.g. local dev without a public tunnel).
  const cbBase = process.env.APP_DOMAIN_NAME || settings?.app_domain || '';
  if (cbBase) {
    form.append('StatusCallback', cbBase.replace(/\/+$/, '') + '/api/twilio/status');
  }

  const type = payload?.type || 'text';
  if (type === 'text') {
    form.append('Body', String(payload.body || ''));
  } else if (type === 'media') {
    if (payload.mediaUrl) form.append('MediaUrl', String(payload.mediaUrl));
    if (payload.body)     form.append('Body',     String(payload.body));
  } else if (type === 'location') {
    // Twilio renders a static map preview via PersistentAction with a
    // geo: scheme. Body is required because location-only sends without
    // a caption don't render on the recipient side. Plain "Location"
    // string (no emoji) keeps the SVG-only project style.
    form.append('PersistentAction', `geo:${payload.latitude},${payload.longitude}`);
    form.append('Body', String(payload.body || payload.name || 'Location'));
  } else if (type === 'template') {
    // Twilio Content Templates — keep MARKETING/UTILITY/AUTHENTICATION
    // sends compliant. The ContentSid (`HX...`) references an approved
    // template in Twilio Content Builder; ContentVariables is a JSON
    // string keyed by positional index (`{"1":"John","2":"ABC123"}`).
    const sidValue = String(payload.contentSid || '').trim();
    if (!sidValue) {
      return { success: false, error: 'Twilio template send needs contentSid (HX...)' };
    }
    form.append('ContentSid', sidValue);
    const vars = payload.contentVariables;
    if (vars && typeof vars === 'object') {
      // Force string keys + scalar values — Twilio's substitution engine
      // ignores integer keys when the payload is rebuilt from JSON.
      const normalized = {};
      Object.keys(vars).forEach((k) => {
        const v = vars[k];
        normalized[String(k)] = (v !== null && typeof v === 'object') ? JSON.stringify(v) : String(v ?? '');
      });
      form.append('ContentVariables', JSON.stringify(normalized));
    }
  } else {
    return { success: false, error: 'Twilio payload type must be text|media|location|template, got ' + type };
  }

  const endpoint = `https://api.twilio.com/2010-04-01/Accounts/${sid}/Messages.json`;
  console.log(`[SEND-TWILIO] → ${formattedTo} type=${type} from=${formattedFrom}`);

  try {
    const response = await axios.post(endpoint, form.toString(), {
      auth: { username: sid, password: token },
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      timeout: 20000,
    });
    const duration = Date.now() - startTime;
    const msgSid = response.data?.sid || null;
    console.log(`[SEND-TWILIO] ✅ ${duration}ms sid=${msgSid}`);
    return { success: true, messageId: msgSid || `tw_${Date.now()}` };
  } catch (error) {
    const duration = Date.now() - startTime;
    const errMsg = error.response?.data?.message || error.response?.data?.error_message || error.message;
    const errCode = error.response?.data?.code || error.response?.status;
    console.error(`[SEND-TWILIO] ❌ ${duration}ms code=${errCode} msg=${errMsg}`);
    return { success: false, error: errMsg, errorCode: errCode };
  }
}

