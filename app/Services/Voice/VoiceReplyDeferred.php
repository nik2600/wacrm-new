<?php

namespace App\Services\Voice;

use App\Models\InboxMessage;
use Illuminate\Support\Facades\Log;

/**
 * Post-response runner for the voice-AI pipeline.
 *
 * The voice pipeline (ASR + LLM + TTS + outbound send) takes 5–15s
 * end-to-end. That's too long to inline into an inbound webhook
 * response — Node's Baileys bridge waits for our 200 OK before
 * forwarding the next message and Meta will time out our response.
 *
 * The traditional fix is a queue worker, but that adds an operational
 * dependency this build deliberately avoids. Instead we use Laravel's
 * `app()->terminating()` hook: a callback registered here runs AFTER
 * the response is fully sent to the client (via PHP-FPM's
 * `fastcgi_finish_request()` when available, or via the Kernel's
 * terminate hook on other SAPIs). Same process, same request — no
 * worker, no cron, no scheduler.
 *
 * Public API:
 *
 *   app(VoiceReplyDeferred::class)->run($inboundMessageId);
 *
 *   - In HTTP context: registers the work as a terminating callback.
 *     Response goes out first, then the pipeline runs.
 *   - In CLI / test context: runs the work inline immediately so
 *     `php artisan tinker` and `php artisan voice:retry` can drive
 *     the pipeline manually.
 *
 * Wrapped in try/catch so a vendor outage during the post-response
 * leg can never crash the parent webhook flow.
 */
class VoiceReplyDeferred
{
    public function __construct(private readonly AiVoiceReplyService $svc) {}

    public function run(int $inboundMessageId): void
    {
        // CLI (`php artisan tinker`, smoke tests, custom commands) doesn't
        // have a response to defer behind — run inline so the operator
        // sees the result synchronously. The terminating() callback is
        // only useful inside an HTTP request lifecycle.
        if (app()->runningInConsole()) {
            $this->process($inboundMessageId);
            return;
        }

        // HTTP context — schedule the work after the response is sent.
        // The closure captures the id (not the model) so we re-read
        // fresh state from the DB in case anything changed between
        // dispatch time and execution.
        app()->terminating(function () use ($inboundMessageId) {
            $this->process($inboundMessageId);
        });
    }

    /**
     * Inner runner. Public so the (rare) caller that wants to bypass
     * terminating() — e.g. a re-process artisan command — can drive
     * the pipeline directly.
     */
    public function process(int $inboundMessageId): void
    {
        try {
            $msg = InboxMessage::find($inboundMessageId);
            if (!$msg) {
                Log::info('[VOICE-AI] message vanished, skipping', ['id' => $inboundMessageId]);
                return;
            }
            if ($msg->ai_processed_at) {
                return; // idempotency — duplicate dispatch
            }

            Log::info('[VOICE-AI] processing', [
                'msg_id'   => $msg->id,
                'convo_id' => $msg->conversation_id,
                'mode'     => app()->runningInConsole() ? 'inline' : 'post-response',
            ]);

            $reply = $this->svc->process($msg);

            Log::info('[VOICE-AI] done', [
                'msg_id'        => $msg->id,
                'reply_msg_id'  => $reply?->id,
                'reply_status'  => $reply?->status,
            ]);
        } catch (\Throwable $e) {
            Log::error('[VOICE-AI] post-response runner threw', [
                'msg_id' => $inboundMessageId,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);
        }
    }
}
