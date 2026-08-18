// controllers/clientController.js
// ==============================
// Client Controller with QR & Pairing Code Support
// ==============================

import { BaileysClientManager } from "../classes/BaileysClientManager.js";
import { updateStatusInLaravel } from "../utils/helpers.js";
import fs from "fs-extra";
import path from "path";
import { fileURLToPath } from "url";

/**
 * Initialize client with QR code
 */
export async function initializeClient(req, res, app) {
  const phoneNumber = req.params.phoneNumber;
  
  try {

    
    // Check if client already exists and is ready
    const existingClient = app.locals.clients[phoneNumber];
    if (existingClient && app.locals.client_ready[phoneNumber]) {

      return res.json({
        message: "Client already ready",
        qr: null,
        status: "connected"
      });
    }
    
    // Check connection lock
    if (app.locals.connectionLocks[phoneNumber]) {
      const lockTime = app.locals.connectionLocks[phoneNumber];
      const timeSinceLock = Date.now() - lockTime;
      
      if (timeSinceLock < 60000) { // 1 minute lock

        return res.status(429).json({
          error: "Connection in progress. Please wait."
        });
      } else {
        // Lock expired, remove it
        delete app.locals.connectionLocks[phoneNumber];
      }
    }
    
    // Set connection lock
    app.locals.connectionLocks[phoneNumber] = Date.now();
    
    // Initialize or get client manager.
    //
    // Reconnect / regenerate-QR must behave EXACTLY like a fresh "Add device".
    // The only difference between the two was that reconnect REUSED the old
    // in-memory manager (with its dead socket, stale flags and old QR), so the
    // QR shown belonged to a dead socket and scanning it never linked. Fix:
    // if the device is NOT currently connected, throw the old manager away and
    // build a brand-new one — the identical path Add device takes. A genuinely
    // connected device is left alone (handled below as "already ready").
    let clientManager = app.locals.clientManagers[phoneNumber];

    if (clientManager && !app.locals.client_ready[phoneNumber]) {
      console.log(`[${phoneNumber}] [QR] reconnect → discarding old manager, rebuilding fresh (same as Add device)`);
      try { await clientManager.resetForFreshAuth(); } catch (e) {}
      if (clientManager.healthTimer)    { clearInterval(clientManager.healthTimer);    clientManager.healthTimer = null; }
      if (clientManager.reconnectTimer) { clearTimeout(clientManager.reconnectTimer);  clientManager.reconnectTimer = null; }
      delete app.locals.clientManagers[phoneNumber];
      delete app.locals.clients[phoneNumber];
      app.locals.client_ready[phoneNumber] = false;
      clientManager = null;
    }

    if (!clientManager) {
      clientManager = new BaileysClientManager(
        phoneNumber,
        app.locals,
        app.locals.appDomainName
      );
      app.locals.clientManagers[phoneNumber] = clientManager;
    }

    // Start client. Per-number proxy / IP isolation is resolved INSIDE start()
    // → loadProxy() fetches the device's proxy from Laravel (DB = source of
    // truth, always fresh) and fail-closes if it's unreachable.
    await clientManager.start();

    // Wait for QR code to be generated
    const maxWaitTime = 30000; // 30 seconds
    const startTime = Date.now();
    
    while (!clientManager.qrCode && (Date.now() - startTime) < maxWaitTime) {
      await new Promise(resolve => setTimeout(resolve, 500));
      
      // Check if client became ready (already connected)
      if (app.locals.client_ready[phoneNumber]) {

        return res.json({
          message: "Client already ready",
          qr: null,
          status: "connected"
        });
      }
    }
    
    if (clientManager.qrCode) {

      res.json({
        qr: clientManager.qrCode,
        message: "QR code generated",
        status: "qr_ready"
      });
    } else {
      throw new Error("QR code not generated within timeout");
    }
    
  } catch (error) {

    
    // Remove connection lock on error
    delete app.locals.connectionLocks[phoneNumber];
    
    res.status(500).json({
      error: "Failed to initialize client",
      details: error.message
    });
  }
}

/**
 * Get pairing code for device connection
 */
export async function getPairingCode(req, res, app) {
  const phoneNumber = req.params.phoneNumber;
  
  try {

    
    // Check if client already exists and is ready
    const existingClient = app.locals.clients[phoneNumber];
    if (existingClient && app.locals.client_ready[phoneNumber]) {

      return res.json({
        success: false,
        message: "Client already ready",
        already_connected: true
      });
    }
    
    // Check connection lock
    if (app.locals.connectionLocks[phoneNumber]) {
      const lockTime = app.locals.connectionLocks[phoneNumber];
      const timeSinceLock = Date.now() - lockTime;
      
      if (timeSinceLock < 60000) { // 1 minute lock

        return res.status(429).json({
          success: false,
          error: "Connection in progress. Please wait."
        });
      } else {
        // Lock expired, remove it
        delete app.locals.connectionLocks[phoneNumber];
      }
    }
    
    // Set connection lock
    app.locals.connectionLocks[phoneNumber] = Date.now();
    
    // Initialize or get client manager
    let clientManager = app.locals.clientManagers[phoneNumber];

    if (!clientManager) {
      clientManager = new BaileysClientManager(
        phoneNumber,
        app.locals,
        app.locals.appDomainName
      );
      app.locals.clientManagers[phoneNumber] = clientManager;
    } else if (clientManager.sock && !app.locals.client_ready[phoneNumber]) {
      // Stale sock from a previous unfinished pair attempt. Without
      // teardown the same 8-char pairing code is returned every time
      // — requestPairingCode() is one-shot per socket lifetime in
      // @itsukichan/baileys (verified against README: "you must provide
      // country code … const code = await suki.requestPairingCode(number)").
      console.log(`[${phoneNumber}] [PAIR] stale sock detected — tearing down for fresh code`);
      await clientManager.resetForFreshAuth();
    }

    // 🔥 FIX: Clear any existing pairing code before starting
    clientManager.pairingCode = null;

    // Start client with pairing code mode. After resetForFreshAuth
    // sock is null, so this always runs. Proxy is resolved inside
    // startWithPairingCode() → loadProxy() (fetched from Laravel, fail-closed).
    if (!clientManager.sock) {
      await clientManager.startWithPairingCode();
    }
    
    // 🔥 FIX: Increased wait time and better polling
    const maxWaitTime = 45000; // 45 seconds (increased from 30)
    const startTime = Date.now();
    const pollInterval = 300; // Check every 300ms

    
    while (!clientManager.pairingCode && (Date.now() - startTime) < maxWaitTime) {
      await new Promise(resolve => setTimeout(resolve, pollInterval));
      
      // Check if client became ready (already connected)
      if (app.locals.client_ready[phoneNumber]) {

        delete app.locals.connectionLocks[phoneNumber];
        return res.json({
          success: false,
          message: "Client already ready",
          already_connected: true
        });
      }
      
      // Log progress every 5 seconds
      const elapsed = Date.now() - startTime;
      if (elapsed % 5000 < pollInterval) {

      }
    }
    
    // Remove connection lock
    delete app.locals.connectionLocks[phoneNumber];
    
    if (!clientManager.pairingCode) {

      
      // Clean up failed attempt
      if (clientManager.sock) {
        try {
          await clientManager.sock.end();
        } catch (e) {

        }
      }
      
      return res.status(408).json({
        success: false,
        error: "Pairing code generation timeout",
        details: `Code not generated within ${maxWaitTime/1000} seconds. Please try again.`
      });
    }

    
    res.json({
      success: true,
      code: clientManager.pairingCode,
      message: "Pairing code generated successfully",
      expires_in: 180 // Code typically expires in 3 minutes
    });
    
  } catch (error) {

    
    // Remove connection lock on error
    delete app.locals.connectionLocks[phoneNumber];
    
    res.status(500).json({
      success: false,
      error: "Failed to generate pairing code",
      details: error.message
    });
  }
}

/**
 * Get client status
 */
export async function getClientStatus(req, res, app) {
  const phoneNumber = req.params.phoneNumber;
  
  try {
    const client = app.locals.clients[phoneNumber];
    const isReady = app.locals.client_ready[phoneNumber] || false;
    const clientManager = app.locals.clientManagers[phoneNumber];
    
    if (client && isReady) {
      res.json({
        status: "connected",
        phoneNumber: phoneNumber,
        isReady: true,
        user: client.user
      });
    } else if (clientManager && clientManager.qrCode) {
      res.json({
        status: "qr_ready",
        phoneNumber: phoneNumber,
        isReady: false,
        qr: clientManager.qrCode
      });
    } else if (clientManager && clientManager.pairingCode) {
      res.json({
        status: "code_ready",
        phoneNumber: phoneNumber,
        isReady: false,
        code: clientManager.pairingCode
      });
    } else {
      res.json({
        status: "disconnected",
        phoneNumber: phoneNumber,
        isReady: false
      });
    }
  } catch (error) {

    res.status(500).json({
      error: "Failed to get client status",
      details: error.message
    });
  }
}

/**
 * Terminate client connection
 */
export async function terminateClient(req, res, app) {
  const phoneNumber = req.params.phoneNumber;
  
  try {

    
    const clientManager = app.locals.clientManagers[phoneNumber];
    
    if (clientManager) {
      await clientManager.logout();
      delete app.locals.clientManagers[phoneNumber];
      delete app.locals.connectionLocks[phoneNumber];

      
      res.json({
        message: "Client terminated successfully",
        phoneNumber: phoneNumber,
        status: "terminated"
      });
    } else {
      res.json({
        message: "Client not found or already terminated",
        phoneNumber: phoneNumber,
        status: "not_found"
      });
    }
  } catch (error) {

    res.status(500).json({
      error: "Failed to terminate client",
      details: error.message
    });
  }
}

/**
 * Check connection status
 */
/**
 * Check connection status and cleanup if disconnected
 */
export async function checkConnection(req, res, app) {
  const phoneNumber = req.params.phoneNumber;
  
  try {

    
    const client = app.locals.clients[phoneNumber];
    const isReady = app.locals.client_ready[phoneNumber] || false;
    const clientManager = app.locals.clientManagers[phoneNumber];
    
    if (client && isReady) {
      // Client is connected and ready
      res.json({
        status: "CONNECTED",
        message: "Device is connected",
        phoneNumber: phoneNumber,
        user: client.user
      });
    } else {
      // Client is NOT connected - perform cleanup

      
      // Disconnect from WhatsApp if client exists
      if (clientManager) {
        try {

          
          // Set flag to prevent reconnection
          clientManager.shouldStayConnected = false;
          clientManager.isConnecting = false;
          clientManager.isConnected = false;
          
          // Logout from WhatsApp
          if (clientManager.sock) {
            try {
              await clientManager.sock.logout();

            } catch (logoutError) {

            }
          }
          
          // Delete session files
          await clientManager.deleteSessionFiles();

          
          // Clean up app locals
          delete app.locals.clients[phoneNumber];
          delete app.locals.client_ready[phoneNumber];
          delete app.locals.clientManagers[phoneNumber];
          delete app.locals.connectionLocks[phoneNumber];
          
          // Clean up active sessions and cooldowns
          const sessionKeys = Object.keys(app.locals.activeFlowSessions || {});
          sessionKeys.forEach(key => {
            if (key.startsWith(`${phoneNumber}_`)) {
              const session = app.locals.activeFlowSessions[key];
              if (session && session.timeoutTimer) {
                clearTimeout(session.timeoutTimer);
              }
              delete app.locals.activeFlowSessions[key];
            }
          });
          
          const cooldownKeys = Object.keys(app.locals.userCooldowns || {});
          cooldownKeys.forEach(key => {
            if (key.startsWith(`${phoneNumber}_`)) {
              delete app.locals.userCooldowns[key];
            }
          });

          
          // Update status in Laravel
          await updateStatusInLaravel("Disconnected", 0, phoneNumber, app.locals.appDomainName);
          
        } catch (cleanupError) {

        }
      } else {

        
        try {
          // Manual cleanup when manager doesn't exist (e.g. after server restart)
          
          // 1. Determine auth folder path (mimicking BaileysClientManager constructor)
          const __filename = fileURLToPath(import.meta.url);
          const __dirname = path.dirname(__filename);
          const authFolder = path.join(
            __dirname,
            "..",
            "baileys_auth",
            `session_${phoneNumber}`
          );
          
          // 2. Check if it exists and remove it
          if (await fs.pathExists(authFolder)) {
            await fs.remove(authFolder);
            console.log(`🗑️ Session files deleted manually for: ${phoneNumber}`);
          } else {
            console.log(`ℹ️ No session files found to delete for: ${phoneNumber}`);
          }
          
          // Also check for the store file separately if it's outside (though logic above says it's inside)
          // Based on BaileysClientManager: this.storeFile = path.join(this.authFolder, 'baileys_store.json');
          // So deleting the folder covers it.

        } catch (cleanupError) {
           console.error(`❌ Error during manual cleanup for ${phoneNumber}:`, cleanupError);
        }
      }
      
      res.json({
        status: "NOT_CONNECTED",
        message: "Device is not connected. Session cleaned up.",
        phoneNumber: phoneNumber,
        cleaned: true
      });
    }
  } catch (error) {
    console.error(`❌ Error checking connection for ${phoneNumber}:`, error);
    res.status(500).json({
      error: "Failed to check connection",
      details: error.message
    });
  }
}

/**
 * Get contacts from WhatsApp
 */
export async function getContacts(req, res, app) {
  const phoneNumber = req.params.phoneNumber;
  
  try {
    console.log(`📇 Get contacts request for: ${phoneNumber}`);
    
    const client = app.locals.clients[phoneNumber];
    const isReady = app.locals.client_ready[phoneNumber];
    
    if (!client || !isReady) {
      return res.status(400).json({
        error: "Client not connected",
        phoneNumber: phoneNumber
      });
    }
    
    // Get contacts from store
    const clientManager = app.locals.clientManagers[phoneNumber];
    if (!clientManager || !clientManager.store) {
      return res.status(400).json({
        error: "Store not available",
        phoneNumber: phoneNumber
      });
    }
    
    const contacts = clientManager.store.contacts;
    
    res.json({
      message: "Contacts retrieved successfully",
      phoneNumber: phoneNumber,
      contacts: contacts || {}
    });
    
  } catch (error) {
    console.error(`❌ Error getting contacts for ${phoneNumber}:`, error);
    res.status(500).json({
      error: "Failed to get contacts",
      details: error.message
    });
  }
}