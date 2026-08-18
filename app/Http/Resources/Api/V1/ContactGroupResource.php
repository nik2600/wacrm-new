<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a contact group in the customer API. Scramble reads this to
 * document the response. `contacts_count` is the number of contacts whose
 * encrypted `contact_group` array references this group (computed in PHP by
 * the ContactGroup model's accessor).
 */
class ContactGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->user_group,
            'note'           => $this->note,
            'color'          => $this->color,
            'contacts_count' => isset($this->resource['contacts_count'])
                ? (int) $this->resource['contacts_count']
                : (int) $this->contacts_count,
            'created_at'     => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
