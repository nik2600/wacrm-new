<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a keyword auto-reply in the customer API. Scramble reads
 * this to document the response. Reads from the array the existing
 * AutoreplyController::transformAutoreply() produces (the `messages` key holds
 * the reply variants), remapping it onto stable public-facing keys.
 */
class AutoReplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        $replies = collect($r['messages'] ?? [])->map(fn ($m) => [
            'id'            => $m['id'] ?? null,
            'type'          => $m['message_type'] ?? 'text',
            'content'       => $m['content'] ?? null,
            'url'           => $m['url'] ?? null,
            'template_id'   => $m['template_id'] ?? null,
            'original_name' => $m['original_name'] ?? null,
            'is_selected'   => (bool) ($m['is_selected'] ?? false),
        ])->values();

        return [
            'id'               => $r['id'] ?? null,
            'keyword'          => $r['keyword'] ?? null,
            'matching_method'  => $r['matching_method'] ?? null,
            'fuzzy_similarity' => $r['fuzzy_similarity'] ?? null,
            'device_id'        => $r['device_id'] ?? null,
            'reply_type'       => $r['reply_type'] ?? null,
            'flow_id'          => $r['flow_id'] ?? null,
            'message_type'     => $r['message_type'] ?? null,
            'status'           => (bool) ($r['status'] ?? false),
            'replies'          => $replies,
            'created_at'       => $r['created_at'] ?? null,
            'updated_at'       => $r['updated_at'] ?? null,
        ];
    }
}
