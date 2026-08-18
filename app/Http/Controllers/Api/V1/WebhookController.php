<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Webhook\StoreWebhookRequest;
use App\Http\Resources\Api\V1\WebhookResource;
use App\Models\Webhook;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhooks — register, list and delete the outbound endpoints the platform
 * POSTs events to for the current workspace.
 *
 * Reuses the in-app webhook storage (the Webhook model + WebhookService
 * dispatch pipeline): rows are scoped via Webhook::forCurrentWorkspace(),
 * the public `url`/`active` fields map onto the model's `webhook_url`/`status`
 * columns, and a delivery is HMAC-signed when a `secret` is set. The list of
 * subscribable event names comes from WebhookService::availableEvents().
 * Results are wrapped in the public { data, meta } envelope.
 */
class WebhookController extends V1Controller
{
    /** GET /api/v1/webhooks — list the workspace's registered webhooks. */
    public function index(Request $request): JsonResponse
    {
        $hooks = Webhook::query()
            ->forCurrentWorkspace()
            ->orderByDesc('id')
            ->get()
            ->map(fn (Webhook $h) => (new WebhookResource($h))->resolve())
            ->values()
            ->all();

        return $this->ok($hooks, [
            'count'             => count($hooks),
            'available_events'  => array_keys(WebhookService::availableEvents()),
        ]);
    }

    /** POST /api/v1/webhooks — register a new outbound webhook endpoint. */
    public function store(StoreWebhookRequest $request): JsonResponse
    {
        // Default to every event when the caller doesn't pick a subset.
        $events = $request->input('events');
        $events = is_array($events) && $events ? array_values($events) : ['*'];

        $hook = Webhook::create([
            'user_id'      => $request->user()?->id,
            'workspace_id' => $this->workspaceId(),
            'name'         => $request->input('name'),
            'environment'  => 'Production',
            'http_method'  => 'POST',
            'webhook_url'  => $request->input('url'),
            'events'       => $events,
            'secret'       => $request->input('secret'),
            'status'       => $request->boolean('active', true),
        ]);

        return $this->created((new WebhookResource($hook))->resolve());
    }

    /** DELETE /api/v1/webhooks/{id} — remove a webhook endpoint. */
    public function destroy(int $id): JsonResponse
    {
        $hook = Webhook::query()->forCurrentWorkspace()->whereKey($id)->first();

        if (!$hook) {
            return $this->fail('not_found', 'Webhook not found.', 404);
        }

        $hook->delete();

        return $this->ok(['id' => $id, 'deleted' => true]);
    }
}
