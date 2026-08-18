<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a WhatsApp message template in the customer API. Scramble
 * reads this to document the response. Reads from the existing
 * TemplateController::present() output array.
 */
class TemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $t = $this->resource;

        return [
            'id'              => $t['id'] ?? null,
            'name'            => $t['template_name'] ?? null,
            'type'            => $t['template_type'] ?? null,
            'category'        => $t['category'] ?? null,
            'language'        => $t['language'] ?? null,
            'header'          => $t['header'] ?? null,
            'header_location' => $t['header_location'] ?? null,
            'body'            => $t['template_body'] ?? null,
            'footer'          => $t['footer'] ?? null,
            'buttons'         => $t['buttons'] ?? [],
            'carousel_data'   => $t['carousel_data'] ?? null,
            'attachment_type' => $t['attachment_type'] ?? null,
            'status'          => $t['status'] ?? null,
            'created_at'      => $t['created_at'] ?? null,
            'updated_at'      => $t['updated_at'] ?? null,
        ];
    }
}
