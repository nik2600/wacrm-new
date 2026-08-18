<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\App\QuickMessageController;
use App\Http\Requests\Api\V1\SendMessageRequest;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Messages — send a single WhatsApp message and read status/history.
 *
 * Reuses the tested single-send pipeline (QuickMessageController →
 * Conversation + Message + WhatsAppDispatcher → workspace engine), wrapping
 * the result in the public { data } envelope.
 */
class MessageController extends V1Controller
{
    /** POST /api/v1/messages — send one message (text / location). */
    public function store(SendMessageRequest $request): JsonResponse
    {
        $type = $request->input('type', 'text');

        // If a file (or media_url) is attached but `type` is still the default
        // "text", INFER the media type from the upload instead of silently
        // sending text and dropping the file — the #1 reason "the image didn't
        // send": the caller attached `media` but forgot `type=image`. Explicit
        // media types are always respected; we only fill in a missing one.
        if (in_array($type, ['text', '', null], true) && ($request->hasFile('media') || $request->filled('media_url'))) {
            $mime = $request->hasFile('media')
                ? (string) $request->file('media')->getMimeType()
                : '';
            $type = match (true) {
                str_starts_with($mime, 'image/') => 'image',
                str_starts_with($mime, 'video/') => 'video',
                str_starts_with($mime, 'audio/') => 'audio',
                $mime !== ''                     => 'document',
                // media_url with no local file to sniff — default to image, the
                // most common case; the caller can still pass type explicitly.
                default                          => 'image',
            };
        }

        $params = [
            'to_number'    => $request->input('to'),
            'device_id'    => $request->input('device_id'),
            'message_text' => (string) ($request->input('text') ?? ''),
        ];

        // Media single-send: image / video / document (+ audio). Two ways —
        //   1. attach the file directly: multipart `media` field (one call,
        //      no hosting needed), OR
        //   2. pass a public `media_url` we download for you.
        // The file ships through the workspace engine (base64 for Unofficial,
        // URL for WABA/Twilio). `text`, if present, becomes the caption.
        if (in_array($type, ['image', 'video', 'document', 'audio'], true)) {
            if ($request->hasFile('media')) {
                $file = $request->file('media');
                $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $file->getClientOriginalName()) ?: 'file';
                // Store once, then hand the dispatcher the local path.
                $params['media_path'] = $file->storeAs('chat-media', \Illuminate\Support\Str::random(10) . '__' . $safe, media_disk());
                $params['media_kind'] = $type;
            } elseif ($request->filled('media_url')) {
                $params['media_url']  = $request->input('media_url');
                $params['media_kind'] = $type;
            } else {
                return $this->fail(
                    'media_required',
                    'Attach a `media` file (multipart) or provide a `media_url` when type is image, video, document or audio.',
                    422
                );
            }
        }

        if ($type === 'location') {
            $params['latitude']     = $request->input('latitude');
            $params['longitude']    = $request->input('longitude');
            $params['message_text'] = $params['message_text'] ?: ' ';
        }

        $internal = Request::create('/api/app/send-quick-message', 'POST', $params);
        $internal->setUserResolver(fn () => $request->user());

        // The quick-message controller returns `success` as a human STRING
        // ("Message sent successfully.") on a 200, never boolean true — so the
        // real success signal is the HTTP status + absence of an `error` key.
        $response = app(QuickMessageController::class)->sendQuickMessage($internal);
        $payload  = $response->getData(true);

        if ($response->getStatusCode() >= 300 || isset($payload['error'])) {
            return $this->fail(
                'send_failed',
                $payload['error'] ?? $payload['server_msg'] ?? 'Message could not be sent.',
                $response->getStatusCode() >= 400 ? $response->getStatusCode() : 422
            );
        }

        // Report the REAL status from the dispatched row (sent / failed /
        // scheduled / pending) instead of a hardcoded "queued" — the send is
        // synchronous, so by here it has actually been handed to the channel.
        $sent = ($payload['message_id'] ?? null) ? Message::find($payload['message_id']) : null;

        return $this->created((new MessageResource([
            'id'         => $payload['message_id'] ?? null,
            'to'         => $request->input('to'),
            'type'       => $type,
            'status'     => $sent->status ?? 'sent',
            'body'       => $request->input('text'),
            'media_url'  => ($sent?->media_path ? media_url($sent->media_path) : $request->input('media_url')),
            'created_at' => optional($sent?->created_at)->toIso8601String() ?? now()->toIso8601String(),
        ]))->resolve());
    }

    /** GET /api/v1/messages/{id} — delivery status of one message. */
    public function show(int $id): JsonResponse
    {
        $msg = Message::query()->whereKey($id)
            ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $this->workspaceId()))
            ->first();

        if (!$msg) {
            return $this->fail('not_found', 'Message not found.', 404);
        }

        return $this->ok((new MessageResource($msg))->resolve());
    }

    /** GET /api/v1/messages — recent message history. */
    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->input('limit', 50), 1), 200);

        $items = Message::query()
            ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $this->workspaceId()))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($m) => (new MessageResource($m))->resolve())
            ->values();

        return $this->ok($items, ['count' => $items->count()]);
    }
}
