<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\Message;
use App\Services\WhatsAppDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mobile-app Quick Message (B4). The old project's QuickMessageController
 * sent a single WhatsApp message and logged it as a `messages` row keyed by
 * `to_number`, then surfaced "chats" by grouping those rows. Our codebase has
 * no flat `messages.to_number` engine — single sends live in the
 * Conversation + Message inbox model and go out through WhatsAppDispatcher
 * (the SAME pipeline /chat uses). So we map:
 *
 *   old `message_type IN (Quick Message, Quick Message Scheduler)` rows
 *      → our Conversation rows stamped `origin = 'quick'`, one per recipient
 *   old per-recipient chat history (to_number)
 *      → that conversation's Message rows (direction in/out)
 *
 * Response keys are kept compatible with the old contract the Flutter app
 * reads:
 *   sendQuickMessage → {success, result, message_id, execution_time,
 *                        scheduled_time}
 *   getAllChats      → {success, chats:[{to_number, contact_name,
 *                        last_message_time, last_message_text, message_type,
 *                        scheduled_time, status, queue_id}], total_chats}
 *   getChatMessages  → {success, messages, total_messages}
 *   deleteChat       → {success, message, deleted_messages}
 *   archive          → {success, message, archive_status}
 *
 * Every query is scoped to the authed user's current workspace via
 * Conversation::forCurrentWorkspace() so the app only ever sees its own data.
 * The send itself is REAL — it runs through WhatsAppDispatcher::send(), which
 * resolves the workspace engine (Baileys / WABA / Twilio) and hits the Node
 * bridge exactly like the web /chat composer.
 */
class QuickMessageController extends Controller
{
    public function __construct(private readonly WhatsAppDispatcher $dispatcher)
    {
        // Writes its own InboxMessage rows; keep the Message model's
        // inbox mirror off so sends don't appear twice in the thread.
        \App\Models\Message::$skipInboxMirror = true;
    }

    /**
     * Download a PUBLIC media URL into the workspace media disk so the
     * dispatcher can ship it (base64 for Unofficial, public URL for WABA/Twilio).
     * SSRF-guarded: only http(s) to a public host, 16 MB cap. Returns
     * [storagePath, kind] where kind ∈ image|video|document|audio.
     *
     * @return array{0:string,1:string}
     */
    private function fetchRemoteMediaToStorage(string $url, ?string $declaredKind): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host   = $parts['host'] ?? '';
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \RuntimeException('media_url must be a public http(s) URL.');
        }
        // Block private / loopback / reserved ranges (SSRF).
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new \RuntimeException('media_url host is not allowed.');
        }

        $resp = \Illuminate\Support\Facades\Http::timeout(25)
            ->withHeaders(['User-Agent' => 'WaDesk-API/1'])
            ->get($url);
        if (!$resp->successful()) {
            throw new \RuntimeException('Could not download media_url (HTTP ' . $resp->status() . ').');
        }
        $bytes = (string) $resp->body();
        if ($bytes === '') {
            throw new \RuntimeException('media_url returned an empty file.');
        }
        if (strlen($bytes) > 16 * 1024 * 1024) {
            throw new \RuntimeException('Media exceeds the 16 MB limit.');
        }

        $mime = strtolower(trim(explode(';', (string) ($resp->header('Content-Type') ?: ''))[0]));
        $kind = $declaredKind ?: (
            str_starts_with($mime, 'image/') ? 'image'
            : (str_starts_with($mime, 'video/') ? 'video'
            : (str_starts_with($mime, 'audio/') ? 'audio' : 'document'))
        );

        // Keep the original filename when present (the dispatcher reads
        // basename(media_path) for the WhatsApp document filename).
        $base = basename((string) parse_url($url, PHP_URL_PATH));
        $base = preg_replace('/[^A-Za-z0-9._-]/', '_', $base) ?: 'file';
        if (!str_contains($base, '.')) {
            $ext  = [
                'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
                'video/mp4' => 'mp4', 'audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg',
                'application/pdf' => 'pdf',
            ][$mime] ?? 'bin';
            $base .= '.' . $ext;
        }

        $path = 'chat-media/' . \Illuminate\Support\Str::random(10) . '__' . $base;
        media_storage()->put($path, $bytes);

        return [$path, $kind];
    }

    /**
     * POST /send-quick-message — send ONE message to a number.
     *
     * Old params (QuickMessageController::sendQuickMessage): to_number,
     * from_number, message_text, template_type, message_type, set_date,
     * set_time, c_name, latitude, longitude. We honour the same inputs.
     * `from_number` selects the sending device (by its phone digits or row
     * id); `message_type = 'Quick Message Scheduler'` + set_date/set_time
     * schedules the send instead of firing it immediately.
     */
    public function sendQuickMessage(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $validated = $request->validate([
                'to_number'    => 'required|string|max:32',
                'from_number'  => 'nullable|string|max:32',
                'device_id'    => 'nullable',
                // Text is the body for text sends, the CAPTION for media sends.
                // Optional now so a media-only send (no caption) is allowed.
                'message_text' => 'nullable|string|max:4096',
                'message_type' => 'nullable|string',
                'set_date'     => 'nullable|date_format:Y-m-d',
                'set_time'     => 'nullable|date_format:H:i',
                'c_name'       => 'nullable|string|max:191',
                'latitude'     => 'nullable|numeric|between:-90,90',
                'longitude'    => 'nullable|numeric|between:-180,180',
                // Single media send. EITHER a PUBLIC url we download (media_url),
                // OR an already-stored path under chat-media/ (media_path — used
                // by the REST API when the caller uploads the file directly).
                'media_url'    => 'nullable|url|max:2048',
                'media_path'   => 'nullable|string|max:255',
                'media_kind'   => 'nullable|in:image,video,document,audio',
            ]);

            $user  = $request->user();
            $wsId  = (int) ($user->current_workspace_id ?? 0);

            $toNumber = preg_replace('/\D+/', '', $validated['to_number']);
            if ($toNumber === '') {
                return response()->json([
                    'error'      => 'Invalid recipient number.',
                    'server_msg' => 'to_number must contain digits.',
                ], 422);
            }

            // Scheduling — old contract used message_type='Quick Message
            // Scheduler' + set_date/set_time. Keep that trigger.
            $messageType = $validated['message_type'] ?? 'Quick Message';
            $isScheduled = $messageType === 'Quick Message Scheduler';
            $tz = optional($user->currentWorkspace)->timezone
                ?? $user->timezone
                ?? config('app.timezone', 'UTC');

            $scheduledAt = null;
            if ($isScheduled) {
                if (empty($validated['set_date']) || empty($validated['set_time'])) {
                    return response()->json([
                        'error' => 'set_date and set_time are required for scheduled messages.',
                    ], 422);
                }
                try {
                    $parsed = Carbon::createFromFormat('Y-m-d H:i', $validated['set_date'] . ' ' . $validated['set_time'], $tz);
                } catch (\Throwable $e) {
                    return response()->json(['error' => 'Could not parse the scheduled date/time.'], 422);
                }
                if ($parsed->lt(now()->addMinute())) {
                    return response()->json([
                        'error' => 'Scheduled time must be at least 1 minute in the future (in ' . $tz . ').',
                    ], 422);
                }
                $scheduledAt = $parsed->copy()->setTimezone('UTC');
            }

            // Resolve the chosen sender across BOTH channels — the `devices`
            // table (Unofficial/Baileys) AND `wa_provider_configs` (WABA + Twilio).
            // `device_id` accepts a Baileys device id (57), a provider-config id
            // ("cfg_20", "waba:20" or a bare 20), or an "engine:id" key;
            // from_number still matches a Baileys phone; nothing → the workspace's
            // default connected sender. Engine + from-number + the provider pin
            // all FOLLOW whatever sender is found, so a WABA/Twilio pick actually
            // sends on that channel instead of the workspace default.
            $sender      = $this->resolveSender($validated['device_id'] ?? null, $validated['from_number'] ?? null, $wsId);
            $device      = $sender['device'];
            $engine      = $sender['engine'];
            $devicePhone = $sender['from'];
            $legacy = \App\Enums\WaProvider::tryFrom($engine)?->legacyCode() ?? 'W';

            // Find or open the quick-message thread for this recipient so the
            // history endpoints below can group by to_number the same way the
            // old engine grouped `messages.to_number`.
            $conversation = $this->findOrCreateQuickThread(
                $user->id,
                $wsId,
                $toNumber,
                $validated['c_name'] ?? null,
                $device?->id,
                $legacy,
                $engine,
                $isScheduled,
                $scheduledAt,
                $tz
            );

            $hasLocation = !empty($validated['latitude']) && !empty($validated['longitude']);

            // Single media send: download the public media_url into storage so
            // the dispatcher can ship it (base64 for Unofficial, URL for
            // WABA/Twilio). The text body becomes the caption.
            $mediaPath = null;
            $mediaKind = null;
            if (!empty($validated['media_path'])) {
                // Already-stored upload (REST API direct file). Trust ONLY
                // existing chat-media/ paths — never an arbitrary disk path.
                $p = ltrim((string) $validated['media_path'], '/');
                if (str_starts_with($p, 'chat-media/') && !str_contains($p, '..') && media_storage()->exists($p)) {
                    $mediaPath = $p;
                    $mediaKind = $validated['media_kind'] ?? 'document';
                } else {
                    return response()->json([
                        'error'      => 'invalid_media',
                        'server_msg' => 'media_path was not found in storage.',
                    ], 422);
                }
            } elseif (!empty($validated['media_url'])) {
                try {
                    [$mediaPath, $mediaKind] = $this->fetchRemoteMediaToStorage(
                        $validated['media_url'],
                        $validated['media_kind'] ?? null
                    );
                } catch (\Throwable $e) {
                    return response()->json([
                        'error'      => 'media_fetch_failed',
                        'server_msg' => $e->getMessage() ?: 'Could not fetch media_url.',
                    ], 422);
                }
            }

            $body = trim((string) ($validated['message_text'] ?? ''));
            // A text send needs a body; media + location carry their own payload.
            if ($body === '' && !$mediaPath && !$hasLocation) {
                return response()->json([
                    'error'      => 'empty_message',
                    'server_msg' => 'Provide message_text, media_url, or a location.',
                ], 422);
            }

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'user_id'         => $user->id,
                'workspace_id'    => $wsId ?: null,
                'direction'       => 'out',
                'from_number'     => $devicePhone,
                'to_number'       => $toNumber,
                'body'            => $body,
                'media_path'      => $mediaPath,
                'media_type'      => $hasLocation ? 'location' : $mediaKind,
                'latitude'        => $validated['latitude']  ?? null,
                'longitude'       => $validated['longitude'] ?? null,
                'status'          => $isScheduled ? 'scheduled' : 'pending',
                'scheduled_at'    => $scheduledAt,
            ]);

            // Hand off to the real dispatcher — same pipeline /chat uses.
            // Route by the SELECTED sender's engine. When a device is picked,
            // use ITS engine ($legacy) and realign the thread — otherwise a
            // thread first opened under the workspace's Twilio/WABA default keeps
            // routing there even though the user picked a Baileys device, so the
            // message "sends" on the wrong channel and never reaches WhatsApp.
            // Route by the resolved sender's engine across ALL channels
            // (Unofficial / WABA / Twilio). PIN msg->provider so the dispatcher's
            // resolveProvider() honours the chosen engine instead of falling back
            // to the workspace's primary config, and realign the thread to it so a
            // thread first opened on another engine doesn't keep routing there.
            $sendPlatform = $legacy;
            if ($conversation->platform !== $legacy) {
                $conversation->forceFill(['platform' => $legacy, 'device_id' => $device?->id])->saveQuietly();
            }
            $message->provider = $engine;
            $result   = null;
            $dispatch = null;
            try {
                $dispatch = $isScheduled
                    ? $this->dispatcher->schedule($message, $sendPlatform)
                    : $this->dispatcher->send($message,     $sendPlatform);

                $ok = (bool) ($dispatch['ok'] ?? false);
                $message->status = $ok
                    ? ($isScheduled ? 'scheduled' : 'sent')
                    : 'failed';
                if ($ok && !$isScheduled) {
                    $message->sent_at = now();
                }
                if (!$ok && !empty($dispatch['error'])) {
                    $message->failure_reason = mb_substr((string) $dispatch['error'], 0, 191);
                }
                $message->save();
                $result = $dispatch;
            } catch (\App\Exceptions\PlanLimitReachedException $e) {
                $message->update([
                    'status'          => 'failed',
                    'failure_reason'  => mb_substr($e->getMessage(), 0, 191),
                ]);
                $this->refreshThread($conversation, $message);

                return response()->json([
                    'error'      => 'out_of_credits',
                    'server_msg' => $e->getMessage() ?: 'Out of message credits.',
                ], 402);
            }

            $this->refreshThread($conversation, $message);

            $executionTime = round(microtime(true) - $startTime, 2);

            return response()->json([
                'success'        => $isScheduled ? 'Message scheduled successfully.' : 'Message sent successfully.',
                'result'         => $result,
                'message_id'     => $message->id,
                'queue_id'       => $conversation->id,
                'execution_time' => $executionTime,
                'scheduled_time' => $scheduledAt?->toDateTimeString(),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error'      => 'Validation failed.',
                'server_msg' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('WaDesk app sendQuickMessage failed: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'error'      => 'Failed to process message.',
                'server_msg' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /quick-message/chats — list chat threads.
     *
     * Old contract grouped `messages` by to_number; we list the workspace's
     * quick-message conversations (origin='quick', not archived) and shape
     * each into the old chat row. `queue_id` maps to our conversation id so
     * the app's tap-through (/quick-message/chat/{toNumber}) still resolves.
     */
    public function getAllChats(Request $request): JsonResponse
    {
        try {
            $threads = Conversation::query()
                ->forCurrentWorkspace()
                ->where('origin', 'quick')
                ->notArchived()
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->get();

            $chats = $threads->map(function (Conversation $c) {
                $last = $c->messages()->latest('id')->first();
                $toNumber = $this->threadRecipient($c, $last);

                return [
                    'to_number'         => $toNumber,
                    'contact_name'      => (string) ($c->title ?: $toNumber),
                    'last_message_time' => optional($c->last_message_at)->toDateTimeString(),
                    'last_message_text' => $last?->body,
                    'message_type'      => $c->status === 'scheduled' ? 'Quick Message Scheduler' : 'Quick Message',
                    'scheduled_time'    => optional($c->scheduled_at)->toDateTimeString(),
                    'status'            => $last?->status,
                    'template_id'       => $last?->template_id,
                    'queue_id'          => $c->id,
                ];
            })->values();

            return response()->json([
                'success'     => true,
                'chats'       => $chats,
                'total_chats' => $chats->count(),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('WaDesk app getAllChats failed: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'error'   => 'Failed to fetch chats.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /quick-message/chat/{toNumber} — messages in a thread.
     *
     * Old contract returned all `messages` rows for a to_number; we return
     * the Message rows of the quick-message conversation(s) for that
     * recipient, newest first, in the old shape.
     */
    public function getChatMessages(Request $request, string $toNumber): JsonResponse
    {
        try {
            $digits = preg_replace('/\D+/', '', $toNumber);

            $conversationIds = $this->quickThreadIdsForRecipient($digits);

            $messages = Message::query()
                ->whereIn('conversation_id', $conversationIds)
                ->orderByDesc('id')
                ->get()
                ->map(fn (Message $m) => $this->messagePayload($m))
                ->values();

            return response()->json([
                'success'        => true,
                'messages'       => $messages,
                'total_messages' => $messages->count(),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('WaDesk app getChatMessages failed: ' . $e->getMessage(), [
                'user_id'   => $request->user()?->id,
                'to_number' => $toNumber,
            ]);

            return response()->json([
                'error'   => 'Failed to fetch chat messages.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /quick-message/chat/{toNumber} — delete a chat.
     *
     * Removes the quick-message conversation(s) for that recipient and their
     * messages (workspace-scoped). Mirrors the old per-to_number delete.
     */
    public function deleteChat(Request $request, string $toNumber): JsonResponse
    {
        try {
            $digits          = preg_replace('/\D+/', '', $toNumber);
            $conversationIds = $this->quickThreadIdsForRecipient($digits);

            if (empty($conversationIds)) {
                return response()->json([
                    'error' => 'Chat not found or already deleted.',
                ], 404);
            }

            $deletedMessages = DB::transaction(function () use ($conversationIds) {
                $count = Message::whereIn('conversation_id', $conversationIds)->count();
                Message::whereIn('conversation_id', $conversationIds)->delete();
                Conversation::whereIn('id', $conversationIds)->delete();

                return $count;
            });

            return response()->json([
                'success'          => true,
                'message'          => 'Chat deleted successfully.',
                'deleted_messages' => $deletedMessages,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('WaDesk app deleteChat failed: ' . $e->getMessage(), [
                'user_id'   => $request->user()?->id,
                'to_number' => $toNumber,
            ]);

            return response()->json([
                'error'   => 'Failed to delete chat.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /quick-message/archive — toggle archive on a chat thread.
     *
     * Old contract (archiveQueue) flipped `messages.archive` for a to_number.
     * We toggle the conversation's `archived` flag. Accepts `to_number` (old
     * key) or `queue_id` (our conversation id).
     */
    public function archive(Request $request): JsonResponse
    {
        try {
            $queueId  = $request->input('queue_id');
            $toNumber = $request->input('to_number');

            $conversations = collect();
            if ($queueId) {
                $c = Conversation::query()->forCurrentWorkspace()
                    ->where('origin', 'quick')->find((int) $queueId);
                if ($c) $conversations->push($c);
            } elseif ($toNumber) {
                $ids = $this->quickThreadIdsForRecipient(preg_replace('/\D+/', '', $toNumber));
                $conversations = Conversation::query()->whereIn('id', $ids)->get();
            }

            if ($conversations->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Chat not found.',
                ], 404);
            }

            // Toggle based on the first thread's current state, apply to all.
            $newStatus = $conversations->first()->archived ? 0 : 1;
            foreach ($conversations as $c) {
                $c->update(['archived' => (bool) $newStatus]);
            }

            return response()->json([
                'success'        => true,
                'message'        => $newStatus ? 'Quick Chat archived successfully' : 'Quick Chat unarchived successfully',
                'archive_status' => $newStatus,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('WaDesk app quick archive failed: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to toggle archive status: ' . $e->getMessage(),
            ], 500);
        }
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Resolve the existing quick-message thread for a recipient or open a
     * new one. Threads are keyed by recipient: we look for the most recent
     * quick conversation in the workspace whose last/first message targets
     * this number. `to_number` is encrypted-at-rest so we match in PHP.
     */
    private function findOrCreateQuickThread(
        int $userId,
        int $wsId,
        string $toNumber,
        ?string $contactName,
        ?int $deviceId,
        string $legacy,
        string $engine,
        bool $isScheduled,
        ?Carbon $scheduledAt,
        string $tz
    ): Conversation {
        // 1) Reuse an EXISTING thread for this number on the SAME engine —
        //    inbox / chatbot / quick alike — matched by raw_jid/alt_jid. Without
        //    this, an API/Quick send to a number that already had an inbound
        //    (team-inbox) thread spawned a SECOND, separate chat for the same
        //    number. Mirrors captureOutboundEcho + the team-inbox reply resolver:
        //    same workspace, same provider (so a WABA thread and a Baileys thread
        //    for one number stay distinct), match on the stored digits.
        if ($wsId && $toNumber !== '') {
            // WABA/Twilio inbound store raw_jid as PURE DIGITS; Baileys stores
            // the full JID (digits@s.whatsapp.net / @lid). Match every form so
            // the merge works whichever engine opened the existing thread.
            // ONE THREAD PER NUMBER - see ConversationResolver.
            $existing = \App\Services\Inbox\ConversationResolver::find($wsId, $toNumber);
            if ($existing) {
                return $existing;
            }
        }

        // 2) Legacy fallback — older quick threads created before we stamped
        //    raw_jid, matched by their message to_number.
        $existingIds = $this->quickThreadIdsForRecipient($toNumber);
        if (!empty($existingIds)) {
            $existing = Conversation::query()->whereIn('id', $existingIds)
                ->orderByDesc('id')->first();
            if ($existing) {
                return $existing;
            }
        }

        // 3) New thread — now WITH raw_jid so future sends AND any later inbound
        //    for this number resolve to THIS conversation instead of duplicating.
        return Conversation::create([
            'user_id'            => $userId,
            'workspace_id'       => $wsId ?: null,
            'device_id'          => $deviceId,
            'title'              => $contactName ?: $toNumber,
            'raw_jid'            => $toNumber,
            'preview'            => null,
            'status'             => $isScheduled ? 'scheduled' : 'pending',
            'platform'           => $legacy,
            'provider'           => $engine,
            'origin'             => 'quick',
            'recipients_count'   => 1,
            'last_message_at'    => now(),
            'scheduled_at'       => $scheduledAt,
            'scheduled_timezone' => $isScheduled ? $tz : null,
        ]);
    }

    /**
     * IDs of the workspace's quick-message conversations whose outbound or
     * inbound messages target a given recipient. `to_number` / `from_number`
     * are encrypted (non-deterministic) so the match runs in PHP after
     * hydration — same pattern ChatController@searchNumbers uses.
     */
    private function quickThreadIdsForRecipient(string $digits): array
    {
        if ($digits === '') {
            return [];
        }

        $threads = Conversation::query()
            ->forCurrentWorkspace()
            ->where('origin', 'quick')
            ->pluck('id');

        if ($threads->isEmpty()) {
            return [];
        }

        return Message::query()
            ->whereIn('conversation_id', $threads)
            ->get(['id', 'conversation_id', 'to_number', 'from_number'])
            ->filter(function (Message $m) use ($digits) {
                $to   = preg_replace('/\D+/', '', (string) $m->to_number);
                $from = preg_replace('/\D+/', '', (string) $m->from_number);
                return $to === $digits || $from === $digits;
            })
            ->pluck('conversation_id')
            ->unique()
            ->values()
            ->all();
    }

    /** Best-effort recipient number for a thread (from its last message). */
    private function threadRecipient(Conversation $c, ?Message $last): string
    {
        if ($last && $last->to_number) {
            return preg_replace('/\D+/', '', (string) $last->to_number);
        }
        $out = $c->messages()->where('direction', 'out')->whereNotNull('to_number')->latest('id')->first();
        if ($out && $out->to_number) {
            return preg_replace('/\D+/', '', (string) $out->to_number);
        }
        if ($c->raw_jid) {
            return preg_replace('/\D+/', '', explode('@', (string) $c->raw_jid)[0]);
        }
        return '';
    }

    /** Shape one Message into the app's chat-message payload. */
    private function messagePayload(Message $m): array
    {
        return [
            'id'             => $m->id,
            'queue_id'       => $m->conversation_id,
            'to_number'      => $m->to_number ? preg_replace('/\D+/', '', (string) $m->to_number) : null,
            'from_number'    => $m->from_number ? preg_replace('/\D+/', '', (string) $m->from_number) : null,
            'direction'      => $m->direction,
            'temp_caption'   => $m->body,
            'message_text'   => $m->body,
            'template_type'  => $m->media_type === 'location' ? 'Text-With-Location' : ($m->media_path ? 'Text-With-Media' : 'Plane-Text'),
            'template_id'    => $m->template_id,
            'media'          => $m->media_path ? media_url($m->media_path) : null,
            'latitude'       => $m->latitude,
            'longitude'      => $m->longitude,
            'status'         => $m->status,
            'scheduled_time' => optional($m->scheduled_at)->toDateTimeString(),
            'created_at'     => optional($m->created_at)->toDateTimeString(),
            'sent_at'        => optional($m->sent_at)->toDateTimeString(),
        ];
    }

    /** Refresh the thread's preview + status after a send. */
    private function refreshThread(Conversation $conversation, Message $message): void
    {
        $conversation->forceFill([
            'preview'         => $message->body,
            'last_message_at' => now(),
            'status'          => $message->status === 'scheduled' ? 'scheduled' : ($message->status === 'failed' ? 'failed' : 'sent'),
            'last_outbound_at'=> now(),
        ])->save();

        $this->mirrorOutboundToInbox($conversation, $message);
    }

    /**
     * Mirror a Quick Send / REST-API outbound into the Team Inbox thread.
     *
     * The single-send path records its message in the `messages` table, but the
     * Team Inbox thread view reads ONLY `inbox_messages` (TeamInboxController's
     * $conv->inboxMessages()). So an API-sent chat showed a preview in the LIST
     * yet opened to a BLANK thread, and the operator couldn't see what went out.
     * We write a matching InboxMessage (direction='out') so the sent bubble —
     * text OR media — renders there and the operator can reply on the same
     * thread.
     *
     * Safe by construction:
     *   - Idempotent: one mirror per source Message (dedupe on meta->mirror_of),
     *     so re-entry / retries never double the bubble; a second call just
     *     re-syncs the status.
     *   - The `messages` row is left untouched, and /chat reads `messages` while
     *     /team-inbox reads `inbox_messages` — different tables, so nothing is
     *     shown twice on either surface.
     *   - Scheduled sends are skipped: they fire later through the Node
     *     scheduler, which never calls this path, so a mirrored 'scheduled'
     *     bubble would be orphaned and never flip to 'sent'.
     *   - Wrapped in try/catch: inbox mirroring must never break the send.
     */
    private function mirrorOutboundToInbox(Conversation $conversation, Message $message): void
    {
        if (($message->status ?? '') === 'scheduled') {
            return;
        }

        try {
            $srcMeta = is_array($message->meta ?? null) ? $message->meta : [];
            $meta    = ['mirror_of' => $message->id];
            // Carry the provider id + JID so delivery/read webhooks can match
            // this row too, keeping the inbox bubble's status in lock-step.
            if (!empty($srcMeta['wa_message_id'])) $meta['wa_message_id'] = $srcMeta['wa_message_id'];
            if (!empty($srcMeta['target_jid']))    $meta['target_jid']    = $srcMeta['target_jid'];

            $attrs = [
                'user_id'        => $message->user_id,
                'provider'       => $message->provider ?? $conversation->provider,
                'direction'      => 'out',
                'to_number'      => $message->to_number,
                'from_number'    => $message->from_number,
                'body'           => $message->body,
                'media_path'     => $message->media_path,
                'media_type'     => $message->media_type,
                'latitude'       => $message->latitude,
                'longitude'      => $message->longitude,
                'status'         => $message->status,
                'failure_reason' => $message->failure_reason,
                'sent_at'        => $message->sent_at,
                'meta'           => $meta,
            ];

            $already = \App\Models\InboxMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('meta->mirror_of', $message->id)
                ->first();

            if ($already) {
                $already->forceFill($attrs)->save();
            } else {
                \App\Models\InboxMessage::create(array_merge(['conversation_id' => $conversation->id], $attrs));
            }

            // Make the thread reliably surface in the Team Inbox list. Only when
            // the conversation has no inbox state yet — never override an
            // operator's resolved/snoozed state. An outbound-initiated thread
            // opens as 'open'.
            if (\Illuminate\Support\Facades\Schema::hasColumn($conversation->getTable(), 'inbox_status')
                && in_array($conversation->inbox_status, [null, ''], true)) {
                $conversation->forceFill(['inbox_status' => 'open'])->saveQuietly();
            }
        } catch (\Throwable $e) {
            Log::warning('[QUICK-MIRROR] inbox mirror failed: ' . $e->getMessage(), [
                'conversation_id' => $conversation->id,
                'message_id'      => $message->id,
            ]);
        }
    }

    /** Resolve the sending device by row id or phone digits, workspace-scoped. */
    private function resolveSenderDevice($deviceId, $fromNumber): ?Device
    {
        if ($deviceId) {
            $byId = Device::query()->forCurrentWorkspace()->find((int) $deviceId);
            if ($byId) {
                return $byId;
            }
        }
        $target = preg_replace('/\D+/', '', (string) $fromNumber);
        if ($target !== '') {
            $match = Device::query()
                ->forCurrentWorkspace()
                ->get()
                ->first(fn (Device $d) => $this->fullPhone($d) === $target);
            if ($match) {
                return $match;
            }
        }

        // No device_id / from_number (or neither matched) — default to the
        // workspace's sender so the REST API works with just { to, text }.
        // Prefer a CONNECTED device (active first), newest first. Without this
        // the API 422'd with "from number missing" / required a device_id.
        return Device::query()
            ->forCurrentWorkspace()
            ->orderByDesc('active')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Resolve the chosen sender across BOTH channels: the `devices` table
     * (Unofficial/Baileys) and `wa_provider_configs` (WABA + Twilio). Accepts a
     * Baileys device id, a provider-config id ("cfg_20", "waba:20" or a bare 20),
     * a phone in $fromNumber, or nothing (→ workspace default sender).
     *
     * @return array{engine:string, device:?Device, from:?string}
     */
    private function resolveSender($idOrKey, $fromNumber, int $wsId): array
    {
        $raw   = trim((string) $idOrKey);
        $cfgId = null;
        if (preg_match('/^cfg[_-]?(\d+)$/i', $raw, $m)) {
            $cfgId = (int) $m[1];
        } elseif (preg_match('/^(?:waba|twilio|whatsapp_cloud|wbiz|cloud):(\d+)$/i', $raw, $m)) {
            $cfgId = (int) $m[1];
        }
        // Explicit WABA/Twilio provider-config pick.
        if ($cfgId && ($cfg = $this->findProviderConfig($cfgId, $wsId))) {
            return $this->configSender($cfg);
        }

        $numeric = ($raw !== '' && ctype_digit($raw)) ? (int) $raw : null;
        if ($numeric) {
            // Baileys device first…
            if ($d = Device::query()->forCurrentWorkspace()->find($numeric)) {
                return ['engine' => \App\Services\WorkspaceEngine::ENGINE_BAILEYS, 'device' => $d, 'from' => $this->fullPhone($d)];
            }
            // …then a WABA/Twilio config with that id (callers may send bare 20 for cfg_20).
            if ($cfg = $this->findProviderConfig($numeric, $wsId)) {
                return $this->configSender($cfg);
            }
        }

        // Phone match → Baileys device.
        $target = preg_replace('/\D+/', '', (string) $fromNumber);
        if ($target !== '') {
            $match = Device::query()->forCurrentWorkspace()->get()
                ->first(fn (Device $d) => $this->fullPhone($d) === $target);
            if ($match) {
                return ['engine' => \App\Services\WorkspaceEngine::ENGINE_BAILEYS, 'device' => $match, 'from' => $this->fullPhone($match)];
            }
        }

        // Nothing explicit → default to the workspace's connected Baileys device
        // so the API works with just { to, text }.
        if ($d = Device::query()->forCurrentWorkspace()->orderByDesc('active')->orderByDesc('id')->first()) {
            return ['engine' => \App\Services\WorkspaceEngine::ENGINE_BAILEYS, 'device' => $d, 'from' => $this->fullPhone($d)];
        }

        // No Baileys device → fall back to the workspace's primary WABA/Twilio
        // sender (a real provider-config row) so the send routes to a connected
        // engine, not just the bare default engine string with no sender.
        $cfg = \App\Models\WaProviderConfig::query()
            ->primaryForWorkspace($wsId ?: null)
            ->whereIn('provider', ['waba', 'twilio'])->first();
        if ($cfg) return $this->configSender($cfg);

        // Truly nothing configured → the workspace default engine, no device row.
        return ['engine' => \App\Services\WorkspaceEngine::for($wsId ?: null), 'device' => null, 'from' => $target !== '' ? $target : null];
    }

    /** A connected WABA/Twilio provider-config in this workspace, or null. */
    private function findProviderConfig(int $id, int $wsId)
    {
        return \App\Models\WaProviderConfig::query()
            ->where('workspace_id', $wsId)
            ->whereIn('provider', ['waba', 'twilio'])
            ->find($id);
    }

    /** Map a WaProviderConfig row to the resolveSender() return shape. */
    private function configSender($cfg): array
    {
        return [
            'engine' => (string) $cfg->provider,
            'device' => null,
            'from'   => preg_replace('/\D+/', '', (string) $cfg->phone_number) ?: null,
        ];
    }

    /** Full digits-only E.164 phone (country code + national number). */
    private function fullPhone(Device $d): string
    {
        return preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number)) ?: '';
    }
}
