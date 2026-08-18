// utils/campaignHelpers.js - FIXED TO MATCH WORKING FORMAT

import axios from "axios";
import path from "path";
import { fileURLToPath } from "url";
import { formatInteractiveButtonsForBaileys } from "./helpers.js";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Get MIME type from file extension
 */
function getMimeType(filename) {
  const ext = path.extname(filename).toLowerCase();
  const mimeTypes = {
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.png': 'image/png',
    '.gif': 'image/gif',
    '.webp': 'image/webp',
    '.bmp': 'image/bmp'
  };
  return mimeTypes[ext] || 'image/jpeg';
}

/**
 * Download image and return buffer
 */
async function downloadImage(url) {
  try {

    const response = await axios.get(url, {
      responseType: 'arraybuffer',
      timeout: 15000,
      maxContentLength: 10 * 1024 * 1024,
    });

    return Buffer.from(response.data);
  } catch (error) {

    throw error;
  }
}

/**
 * Send carousel message using Baileys - MATCHING WORKING FORMAT
 */
export async function sendCarouselMessage(sock, to, content) {
  try {
    if (!sock) {
      throw new Error('Socket not found');
    }


    // Build cards in the SIMPLE format that works
    const processedCards = [];

    for (const [index, card] of content.cards.entries()) {


      const cardData = {
        title: card.title || '',
        body: card.body || undefined,
        footer: card.footer || undefined
      };

      // Handle image - pass as buffer directly or as {url: ...}
      if (card.image) {
        try {
          if (Buffer.isBuffer(card.image)) {
            cardData.image = card.image;

          } else if (typeof card.image === 'string') {
            if (card.image.startsWith('http')) {
              // Download and pass as buffer
              const imageBuffer = await downloadImage(card.image);
              cardData.image = imageBuffer;

            } else {
              // Local file path - build full URL and download
              const imageUrl = `${process.env.APP_DOMAIN_NAME}/uploads/templates/carousel/${card.image}`;
              const imageBuffer = await downloadImage(imageUrl);
              cardData.image = imageBuffer;

            }
          }
        } catch (error) {

          // Continue without image
        }
      }

      // Process buttons through the shared formatter so a card's URL / call /
      // copy / quick-reply buttons render identically to chat / campaigns /
      // broadcasts — and tolerate every button-type alias (url, call,
      // copy_text, coupon, …), not just the canonical cta_* names.
      if (card.buttons && card.buttons.length > 0) {
        cardData.buttons = formatInteractiveButtonsForBaileys(card.buttons) || [];
      }

      processedCards.push(cardData);
    }

    // Build carousel in SIMPLE format (matching working code)
    const carouselMessage = {
      text: content.text || 'This is Test Message',
      title: content.title || 'This is Test Message',
      footer: content.footer || 'This is Test Message',
      cards: processedCards
    };

    // Log without buffers
    const logMessage = {
      ...carouselMessage,
      cards: carouselMessage.cards.map(card => ({
        ...card,
        image: card.image ? (Buffer.isBuffer(card.image) ? `[Buffer ${card.image.length} bytes]` : card.image) : undefined
      }))
    };


    // Send message
    const result = await sock.sendMessage(to, carouselMessage);


    return {
      success: true,
      messageId: result.key.id,
      timestamp: result.messageTimestamp
    };
  } catch (error) {

    return {
      success: false,
      error: error.message
    };
  }
}