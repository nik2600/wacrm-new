<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a message in the customer API. Scramble reads this to
 * document the response.
 */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->resource['id'] ?? ($this->id ?? null),
            'to'         => $this->resource['to'] ?? ($this->to_number ?? null),
            'type'       => $this->resource['type'] ?? ($this->media_type ?? 'text'),
            'status'     => $this->resource['status'] ?? ($this->status ?? 'queued'),
            'body'       => $this->resource['body'] ?? ($this->body ?? null),
            'media_url'  => $this->resource['media_url']
                ?? ((!is_array($this->resource) && ($this->media_path ?? null)) ? media_url($this->media_path) : null),
            'created_at' => isset($this->resource['created_at'])
                ? $this->resource['created_at']
                : optional($this->created_at ?? null)?->toIso8601String(),
        ];
    }
}
