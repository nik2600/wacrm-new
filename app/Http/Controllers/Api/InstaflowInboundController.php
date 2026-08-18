<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Instaflow → WaDesk push.
 *
 * The separate Instaflow deployment POSTs new Instagram events here so they
 * surface in WaDesk's unified team-inbox (the SAME Conversation + InboxMessage
 * tables WhatsApp uses, with provider = 'instagram' and channel = 'instagram').
 * Authenticated by the SAME shared secret the admin pasted on the Add-ons
 * "Connect Instagram" card — no Laravel session, so this route is outside the
 * web auth group and self-guards.
 *
 * IG threads are keyed by the Instaflow conversation id (never a phone number),
 * namespaced as raw_jid = "ig:<id>" and channel = 'instagram' (which is in
 * Conversation::ENGINE_AGNOSTIC_CHANNELS, so they always show regardless of the
 * workspace's connected WhatsApp engine and never collide with a phone thread).
 *
 * Body (Instaflow sends):
 *   {
 *     "event":        "message" | "status" | "conversation",
 *     "workspace_id": 12,
 *     "account":      { "id":"...", "username":"...", "name":"...", "avatar":"..." },
 *     "conversation": { "id":"...", "name":"...", "handle":"...", "avatar":"..." },
 *     "message":      { "id":"...", "from_me":false, "type":"text|image|video|audio|share|story_reply",
 *                       "text":"...", "media_url":"https://...", "at":"ISO-8601" }
 *   }
 */
class InstaflowInboundController extends Controller
{
    public function ingest(Request $request): JsonResponse
    {
        // ── Auth: constant-time compare against the stored shared secret ──────
        $sent   = (string) $request->header('X-Instaflow-Secret', '');
        $stored = (string) SystemSetting::get('instaflow_secret', '');
        if ($stored === '' || ! hash_equals($stored, $sent)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $data = $request->validate([
            'event'        => 'required|string|in:message,status,conversation',
            'workspace_id' => 'required|integer',
            'account'      => 'nullable|array',
            'conversation' => 'nullable|array',
            'message'      => 'nullable|array',
        ]);

        // A real push proves the link is live — reflect that on the Add-ons card.
        SystemSetting::set('instaflow_connected', '1', 'bool', 'Instaflow handshake result');
        SystemSetting::set('instaflow_last_inbound', now()->toDateTimeString(), 'string', 'Instaflow last inbound push');

        $wsId = (int) $data['workspace_id'];
        if (! Workspace::whereKey($wsId)->exists()) {
            return response()->json(['ok' => false, 'error' => 'unknown workspace'], 422);
        }

        // Only 'message' events create inbox rows; status/conversation are
        // acknowledged (they'll drive read-state / thread meta in a later pass).
        if ($data['event'] !== 'message') {
            return response()->json(['ok' => true, 'received' => $data['event']]);
        }

        try {
            // Shared with the PULL path (InstaflowInboundPuller) so both behave
            // identically — dedup, auto-reply, unread, real-time fan-out.
            $msg = \App\Services\Instaflow\InstaflowIngestService::ingest($wsId, $data);
        } catch (\Throwable $e) {
            Log::error('[INSTAFLOW] inbound store failed', ['ws' => $wsId, 'err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'store_failed'], 500);
        }
        if (! $msg) {
            return response()->json(['ok' => true, 'received' => $data['event'] ?? 'message']);
        }

        return response()->json(['ok' => true, 'message_id' => $msg->id, 'conversation_id' => $msg->conversation_id]);
    }
}
