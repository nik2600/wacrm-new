// utils/cleanup.js
// ==============================
// Cleanup Utility Functions
// ==============================

import fs from "fs-extra";
import path from "path";
import { fileURLToPath } from "url";
import moment from "moment";
import { updateScheduleStatusInLaravel } from "./helpers.js";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Delete session files only
export async function deleteSessionFilesOnly(phoneNumber) {
  const directory = path.join(
    __dirname,
    "..",
    "baileys_auth",
    `session_${phoneNumber}`
  );
  try {
    await fs.remove(directory);

  } catch (err) {

  }
}

// // Perform complete client cleanup
// export async function performClientCleanup(phoneNumber, app) {
//   try {
//     void(0);
//     const sock = app.locals.clients[phoneNumber];
//     if (sock) {
//       try {
//         void(0);
//         await sock.logout();
//       } catch (clientError) {
//         void(0);
//       }
//       delete app.locals.clients[phoneNumber];
//       delete app.locals.client_ready[phoneNumber];
//       void(0);
//     }
//     // Cancel all scheduled messages for this phone number
//     const scheduledMessages = app.locals.scheduledMessages.filter(
//       (msg) =>
//         msg.senderPhoneNumber === phoneNumber && msg.status === "scheduled"
//     );
//     void(0);
//     for (const msg of scheduledMessages) {
//       if (app.locals.scheduledJobs[msg.id]) {
//         app.locals.scheduledJobs[msg.id].stop();
//         delete app.locals.scheduledJobs[msg.id];
//       }
//       msg.status = "cancelled";
//       msg.cancelledAt = moment().format();
//       try {
//         await updateScheduleStatusInLaravel(msg.id, "cancelled", phoneNumber);
//       } catch (updateError) {
//         console.error(
//           "Error updating schedule status in Laravel:",
//           updateError
//         );
//       }
//     }
//     // Remove cancelled scheduled messages from memory
//     app.locals.scheduledMessages = app.locals.scheduledMessages.filter(
//       (msg) => msg.senderPhoneNumber !== phoneNumber
//     );
//     // Delete session files
//     await deleteSessionFilesOnly(phoneNumber);
//     console.log(
//       `✅ Complete cleanup finished for phone number: ${phoneNumber}`
//     );
//   } catch (error) {
//     void(0);
//   }
// }

// Delete session files (for terminate route)
export async function deleteSessionFiles(phoneNumber, res) {
  const directory = path.join(
    __dirname,
    "..",
    "baileys_auth",
    `session_${phoneNumber}`
  );
  try {
    await fs.remove(directory);

    return res.send({ status: "CLIENT_TERMINATED" });
  } catch (err) {

    res.status(500).send({ message: "error in cache clear! Please try again", status: 500 });
  }
}

/**
 * Graceful in-process cleanup for a single client. Use this from
 * SIGINT / SIGTERM / hot-reload paths — it closes the socket without
 * telling WhatsApp to unlink the device and KEEPS the session files
 * on disk, so the next Node startup can restore the pairing.
 *
 * For user-intent "Disconnect this device" use BaileysClientManager.logout()
 * directly, which sends the unlink RPC and wipes session files.
 */
export async function performClientCleanup(phoneNumber, appLocals) {
  try {
    // Soft-disconnect the manager. The old code called logout() here,
    // which sent WhatsApp's "unlink device" RPC and triggered our
    // close-handler 401 branch to delete session files — bricking
    // every restart.
    if (appLocals.clientManagers[phoneNumber]) {
      try {
        if (typeof appLocals.clientManagers[phoneNumber].softDisconnect === 'function') {
          await appLocals.clientManagers[phoneNumber].softDisconnect();
        } else {
          await appLocals.clientManagers[phoneNumber].logout();
        }
      } catch (error) {
        console.error(`Soft disconnect error for ${phoneNumber}:`, error?.message);
      }
      delete appLocals.clientManagers[phoneNumber];
    }

    // Clean up socket reference
    if (appLocals.clients[phoneNumber]) {
      delete appLocals.clients[phoneNumber];
    }
    
    // Clean up ready status
    if (appLocals.client_ready[phoneNumber]) {
      delete appLocals.client_ready[phoneNumber];
    }
    
    // Clean up all flow sessions for this device
    const sessionKeys = Object.keys(appLocals.activeFlowSessions);
    sessionKeys.forEach(key => {
      if (key.startsWith(`${phoneNumber}_`)) {

        delete appLocals.activeFlowSessions[key];
      }
    });
    
    // Clean up scheduled jobs for this device
    if (appLocals.scheduledJobs[phoneNumber]) {
      const jobs = appLocals.scheduledJobs[phoneNumber];
      Object.keys(jobs).forEach(jobId => {
        if (jobs[jobId] && jobs[jobId].cancel) {
          jobs[jobId].cancel();
        }
      });
      delete appLocals.scheduledJobs[phoneNumber];
    }
    
    // Clean up scheduled messages for this device
    appLocals.scheduledMessages = appLocals.scheduledMessages.filter(
      msg => msg.phoneNumber !== phoneNumber
    );

  } catch (error) {

  }
}

export function getSessionStats(appLocals) {
  const stats = {
    totalSessions: Object.keys(appLocals.activeFlowSessions).length,
    sessionsByDevice: {},
    activeSessions: 0,
    completedSessions: 0,
    waitingSessions: 0
  };
  
  Object.entries(appLocals.activeFlowSessions).forEach(([key, session]) => {
    const deviceNumber = key.split('_')[0];
    
    if (!stats.sessionsByDevice[deviceNumber]) {
      stats.sessionsByDevice[deviceNumber] = 0;
    }
    stats.sessionsByDevice[deviceNumber]++;
    
    if (session.status === 'active') {
      stats.activeSessions++;
      if (session.waitingForInput) {
        stats.waitingSessions++;
      }
    } else if (session.status === 'completed') {
      stats.completedSessions++;
    }
  });
  
  return stats;
}

export function cleanupExpiredSessions(appLocals, maxAgeMs = 3600000) {
  const now = Date.now();
  let cleanedCount = 0;
  
  const sessionKeys = Object.keys(appLocals.activeFlowSessions);
  
  sessionKeys.forEach(key => {
    const session = appLocals.activeFlowSessions[key];
    const sessionAge = now - new Date(session.startedAt).getTime();
    
    if (sessionAge > maxAgeMs) {

      delete appLocals.activeFlowSessions[key];
      cleanedCount++;
    }
  });
  
  if (cleanedCount > 0) {

  }
  
  return cleanedCount;
}