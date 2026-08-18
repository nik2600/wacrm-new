<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Broadcast;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a broadcast in the customer API. Scramble reads this to
 * document the response. Wraps an App\Models\Broadcast and exposes the
 * cascading status counts (sent/delivered/read/failed/clicked/pending) plus
 * totals and timestamps.
 */
class BroadcastResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Broadcast $b */
        $b = $this->resource;

        $counts = $b->status_counts;

        return [
            'id'               => $b->id,
            'name'             => $b->name,
            'status'           => $b->status,
            'template_id'      => $b->template_id,
            'device_id'        => $b->device_id,
            'total_recipients' => (int) $b->total_recipients,
            'counts'           => [
                'sent'      => (int) ($counts['sent']      ?? 0),
                'delivered' => (int) ($counts['delivered'] ?? 0),
                'read'      => (int) ($counts['read']      ?? 0),
                'failed'    => (int) ($counts['failed']    ?? 0),
                'clicked'   => (int) ($counts['clicked']   ?? 0),
                'pending'   => (int) ($counts['pending']   ?? 0),
            ],
            'scheduled_at'     => optional($b->scheduled_at)?->toIso8601String(),
            'completed_at'     => optional($b->completed_at)?->toIso8601String(),
            'created_at'       => optional($b->created_at)?->toIso8601String(),
            'updated_at'       => optional($b->updated_at)?->toIso8601String(),
        ];
    }
}
