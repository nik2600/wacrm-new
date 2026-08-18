<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Flow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a chatbot flow in the customer API. Scramble reads this to
 * document the response. Wraps an App\Models\Flow row — `flow_name` is
 * decrypted by the model accessor before it reaches here. `status` is derived
 * from the published/active flags the same way FlowsController::index does
 * (draft → paused → live). `subscribers_count` reads the withCount alias when
 * present, falling back to the relation count.
 */
class FlowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Flow $flow */
        $flow = $this->resource;

        return [
            'id'                => $flow->id,
            'name'              => (string) ($flow->flow_name ?? ''),
            'trigger'           => $flow->trigger_kind,
            'status'            => $this->status($flow),
            'category'          => $flow->category ?: null,
            'subscribers_count' => (int) ($flow->subscribers_count
                ?? $flow->active_subscriber_count
                ?? $flow->subscribers()->count()),
            'created_at'        => optional($flow->created_at)->toIso8601String(),
            'updated_at'        => optional($flow->updated_at)->toIso8601String(),
        ];
    }

    /** Derive the user-facing lifecycle state (draft / paused / live). */
    private function status(Flow $flow): string
    {
        if (!$flow->is_published) return 'draft';
        return $flow->is_active ? 'live' : 'paused';
    }
}
