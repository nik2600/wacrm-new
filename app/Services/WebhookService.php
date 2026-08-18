<?php

namespace App\Services;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WebhookService — adapted from old WaDesk WebhookController/WebhookService
 * to fit the new app shape. Encryption-at-rest is handled by the
 * Webhook + WebhookDelivery casts; this service is concerned with
 * the dispatch + signing pipeline.
 */
class WebhookService
{
    /**
     * Available webhook events the user can subscribe to.
     */
    public static function availableEvents(): array
    {
        return [
            'message_received'                  => 'Inbound message received',
            'message_sent'                      => 'Outbound message sent',
            'message_delivered'                 => 'Outbound message delivered',
            'message_read'                      => 'Outbound message read',
            'message_failed'                    => 'Outbound message failed',
            'broadcast_created'                 => 'Broadcast created',
            'broadcast_status_updated'          => 'Broadcast status updated',
            'broadcast_message_status_updated'  => 'Broadcast message status updated',
            'campaign_created'                  => 'Campaign created',
            'campaign_status_updated'           => 'Campaign status updated',
            'campaign_contact_status_updated'   => 'Campaign contact status updated',
            'campaign_contact_clicked'          => 'Campaign contact clicked a link',
            'campaign_contact_replied'          => 'Campaign contact replied',
            'contact_created'                   => 'Contact created',
            'contact_opt_in'                    => 'Contact opted in',
            'contact_updated'                   => 'Contact updated',
            'device_status_updated'             => 'Device status updated',
        ];
    }

    /**
     * Fire-and-forget dispatch used by model observers + lifecycle hooks.
     *
     * Deferred to app()->terminating() so the actual HTTP delivery runs
     * AFTER the response is flushed — two reasons:
     *   1. A slow/blocking subscriber endpoint never delays the user's
     *      request or a Node callback.
     *   2. CRITICAL for observers — an exception thrown inside an Eloquent
     *      `updated`/`created` hook would otherwise abort the model save.
     *      Running outside the save (and inside a try/catch) means a
     *      webhook problem can never break the operation that triggered it.
     *
     * Falls back to an inline (still guarded) call when terminating() isn't
     * available (e.g. tinker / a non-HTTP context).
     */
    public static function emit(string $eventName, array $data, ?int $userId = null): void
    {
        $run = function () use ($eventName, $data, $userId) {
            try {
                self::dispatch($eventName, $data, $userId);
            } catch (\Throwable $e) {
                Log::warning("[webhook] emit {$eventName} failed: " . $e->getMessage());
            }
        };
        try {
            app()->terminating($run);
        } catch (\Throwable $e) {
            $run();
        }
    }

    public static function dispatch(string $eventName, array $data, ?int $userId = null): void
    {
        $userId  = $userId ?? ($data['user_id'] ?? null);
        $wsId    = (int) ($data['workspace_id'] ?? 0);

        // Webhooks fire for the whole workspace — any active webhook in
        // the event's workspace should receive it, regardless of which
        // teammate created it. Fall back to user_id-scoped legacy rows
        // only when no workspace_id is on the event payload.
        $q = Webhook::query()->where('status', true);
        if ($wsId > 0) {
            $q->where(function ($qq) use ($wsId, $userId) {
                $qq->where('workspace_id', $wsId);
                if ($userId) {
                    $qq->orWhere(function ($qqq) use ($userId) {
                        $qqq->whereNull('workspace_id')->where('user_id', $userId);
                    });
                }
            });
        } elseif ($userId) {
            $q->where('user_id', $userId);
        } else {
            return;
        }
        $webhooks = $q->get();
        if ($webhooks->isEmpty()) return;

        $payload = self::formatPayload($eventName, $data);
        foreach ($webhooks as $hook) {
            $events = $hook->events ?? [];
            if (!in_array($eventName, $events, true) && !in_array('*', $events, true)) continue;
            self::deliver($hook, $eventName, $payload);
        }
    }

    public static function testFire(Webhook $hook): WebhookDelivery
    {
        $appName = \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));
        $payload = self::formatPayload('test_fire', [
            'message' => 'Hello from ' . $appName . '. This is a test fire.',
            'user_id' => $hook->user_id,
        ]);
        return self::deliver($hook, 'test_fire', $payload);
    }

    /**
     * Max delivery attempts (1 initial + retries). Transient failures —
     * a connection error/timeout, HTTP 429, or any 5xx — are retried
     * inline with a short backoff. A clean 2xx stops immediately, and a
     * non-429 4xx is treated as a permanent client error (the endpoint
     * rejected us; retrying won't help) so we don't retry it.
     *
     * This is deliberately synchronous and self-contained: NO queue, NO
     * scheduler. Webhook dispatch already runs inline (typically after
     * the response via app()->terminating), so a sub-second backoff is
     * invisible to the user while making delivery resilient to blips.
     */
    private const MAX_ATTEMPTS = 3;

    /** Backoff before attempt N (index 1 = before 2nd try). Milliseconds. */
    private const RETRY_BACKOFF_MS = [0, 400, 1200];

    private static function deliver(Webhook $hook, string $eventName, array $payload): WebhookDelivery
    {
        $started    = microtime(true);
        $statusCode = null;
        $errorText  = null;
        $body       = null;
        $attempts   = 0;

        $headers = ['Content-Type' => 'application/json'];
        if (!empty($hook->secret)) {
            // Brand-prefixed header (white-label): "X-{Brand}-Signature".
            $headers[\App\Support\Brand::webhookSignatureHeader()] = hash_hmac('sha256', json_encode($payload), $hook->secret);
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $attempts   = $attempt;
            $statusCode = null;
            $errorText  = null;
            $body       = null;

            try {
                $resp = Http::timeout(10)->withHeaders($headers)->post($hook->webhook_url, $payload);
                $statusCode = $resp->status();
                $body       = (string) $resp->body();
            } catch (\Throwable $e) {
                $errorText = $e->getMessage();
            }

            $isOk = $statusCode !== null && $statusCode >= 200 && $statusCode < 300;
            if ($isOk) break;

            // Decide whether THIS failure is worth retrying. A transport
            // exception (no status) or 429/5xx is transient; a 4xx other
            // than 429 is permanent — stop and record it.
            $transient = $statusCode === null
                || $statusCode === 429
                || $statusCode >= 500;
            if (!$transient || $attempt >= self::MAX_ATTEMPTS) {
                if ($transient) {
                    Log::warning("Webhook giving up after {$attempt} attempts (id={$hook->id}): " . ($errorText ?? "HTTP {$statusCode}"));
                }
                break;
            }

            // Backoff before the next attempt (synchronous, sub-second).
            $sleepMs = self::RETRY_BACKOFF_MS[$attempt] ?? 1200;
            if ($sleepMs > 0) usleep($sleepMs * 1000);
        }

        $latencyMs = (int) round((microtime(true) - $started) * 1000);
        $isOk      = $statusCode !== null && $statusCode >= 200 && $statusCode < 300;

        $hook->update([
            'last_fired_at'    => now(),
            'last_status_code' => $statusCode,
            'last_latency_ms'  => $latencyMs,
            'last_error'       => $isOk ? null : (($errorText ?? "HTTP {$statusCode}") . ($attempts > 1 ? " (after {$attempts} attempts)" : '')),
            'success_count'    => $hook->success_count + ($isOk ? 1 : 0),
            'failure_count'    => $hook->failure_count + ($isOk ? 0 : 1),
            'is_failing'       => !$isOk && $hook->failure_count + 1 >= 3,
        ]);

        return WebhookDelivery::create([
            'webhook_id'    => $hook->id,
            'event_name'    => $eventName,
            'status_code'   => $statusCode,
            'latency_ms'    => $latencyMs,
            'is_retry'      => $attempts > 1,
            'attempts'      => $attempts,
            'payload'       => json_encode($payload),
            'response_body' => $body,
            'error'         => $errorText,
            'fired_at'      => now(),
        ]);
    }

    private static function formatPayload(string $eventName, array $data): array
    {
        return [
            'id'        => (string) Str::uuid(),
            'event'     => $eventName,
            'eventType' => $eventName,
            'created'   => now()->toIso8601String(),
            'timestamp' => $data['timestamp'] ?? now()->timestamp,
            'data'      => $data,
        ];
    }
}
