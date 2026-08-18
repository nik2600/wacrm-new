<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a webhook endpoint in the customer API. Scramble reads this
 * to document the response. Maps the in-app Webhook model (webhook_url / status)
 * onto the public contract (url / active). The signing secret is never exposed.
 */
class WebhookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'url'        => $this->webhook_url,
            'events'     => is_array($this->events) ? array_values($this->events) : [],
            'active'     => (bool) $this->status,
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
