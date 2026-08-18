<?php

namespace App\Services\Inbox;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\OutboundWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fires HMAC-signed POSTs to customer-configured webhook URLs whenever a
 * conversation lifecycle event happens. The whole call is wrapped in
 * try/catch and short-timeout so a slow/dead webhook can't stall the
 * inbox.
 *
 * Each subscriber configures:
 *   - url:      where to POST
 *   - events:   which events to subscribe to (or ["*"] for everything)
 *   - secret:   optional shared secret; when set we add
 *               X-WaDesk-Signature: sha256=<hex> over the request body
 *
 * Wired into TeamInboxController / WaInboundController at trigger points
 * via WebhookDispatcher::fire($event, $conv, $extra = []).
 */
class OutboundWebhookDispatcher
{
    public function fire(string $event, Conversation $conv, array $extra = []): void
    {
        // Pull active hooks for this workspace that subscribe to the event.
        // Single indexed query; we do the JSON `events` filter in PHP so
        // it works on any DB (MySQL JSON_CONTAINS would tie us to MySQL 5.7+).
        $hooks = OutboundWebhook::active()
            ->forWorkspace($conv->workspace_id)
            ->get();

        $hooks = $hooks->filter(fn ($h) => $h->subscribes($event));
        if ($hooks->isEmpty()) return;

        $payload = $this->buildPayload($event, $conv, $extra);
        $body    = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($hooks as $hook) {
            $this->deliver($hook, $body, $event);
        }
    }

    private function deliver(OutboundWebhook $hook, string $body, string $event): void
    {
        // Brand-prefixed headers (white-label): "X-{Brand}-Event" etc.,
        // matching what the API docs show. User-Agent uses the brand too.
        $headers = [
            'Content-Type'                              => 'application/json',
            'User-Agent'                                => \App\Support\Brand::name() . '-Webhook/1.0',
            \App\Support\Brand::webhookEventHeader()    => $event,
            \App\Support\Brand::webhookHookIdHeader()   => (string) $hook->id,
        ];
        if (!empty($hook->secret)) {
            $sig = hash_hmac('sha256', $body, (string) $hook->secret);
            $headers[\App\Support\Brand::webhookSignatureHeader()] = 'sha256=' . $sig;
        }

        try {
            // Short timeout — webhooks are best-effort; we don't block the
            // operator's inbox waiting for a slow CRM. If it fails we just
            // bump the failure counter and let the operator retry/edit.
            $res = Http::withHeaders($headers)
                ->timeout(4)
                ->connectTimeout(2)
                ->withBody($body, 'application/json')
                ->post($hook->url);

            if ($res->successful()) {
                $hook->forceFill([
                    'fired_count'   => $hook->fired_count + 1,
                    'last_fired_at' => now(),
                    'last_error'    => null,
                ])->save();
            } else {
                $hook->forceFill([
                    'failed_count'  => $hook->failed_count + 1,
                    'last_fired_at' => now(),
                    'last_error'    => 'HTTP ' . $res->status() . ' ' . mb_substr($res->body(), 0, 191),
                ])->save();
                Log::warning('[WEBHOOK] non-2xx', ['hook_id' => $hook->id, 'status' => $res->status(), 'body' => mb_substr($res->body(), 0, 500)]);
            }
        } catch (\Throwable $e) {
            $hook->forceFill([
                'failed_count'  => $hook->failed_count + 1,
                'last_fired_at' => now(),
                'last_error'    => mb_substr($e->getMessage(), 0, 191),
            ])->save();
            Log::warning('[WEBHOOK] threw', ['hook_id' => $hook->id, 'err' => $e->getMessage()]);
        }
    }

    private function buildPayload(string $event, Conversation $conv, array $extra): array
    {
        return [
            'event'      => $event,
            'fired_at'   => now()->toIso8601String(),
            'workspace_id' => $conv->workspace_id,
            'conversation' => [
                'id'                => $conv->id,
                'title'             => (string) $conv->title,
                'preview'           => (string) $conv->preview,
                'inbox_status'      => $conv->inbox_status,
                'priority'          => $conv->priority,
                'assignee_user_id'  => $conv->assignee_user_id,
                'assignee_team_id'  => $conv->assignee_team_id,
                'assignee_agent_id' => $conv->assignee_agent_id,
                'raw_jid'           => $conv->raw_jid,
                'last_message_at'   => optional($conv->last_message_at)->toIso8601String(),
            ],
            'data' => $extra,
        ];
    }
}
