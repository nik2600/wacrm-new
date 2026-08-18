<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a scheduled message in the customer API. Scramble reads this
 * to document the response. Reads from a ScheduledMessage model — only the
 * non-PII fields the public API exposes (recipient phone numbers are not
 * returned in the list/detail shape).
 */
class ScheduledResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->schedule_name,
            'type'             => $this->template_type,
            'status'           => $this->status,
            'message'          => $this->message_content,
            'template_id'      => $this->template_id,
            'device_id'        => $this->device_id,
            'recipient_count'  => (int) $this->total_recipients,
            'run_at'           => optional($this->scheduled_time)->toIso8601String(),
            'next_run_at'      => optional($this->next_run_at)->toIso8601String(),
            'timezone'         => $this->timezone,
            'total_sent'       => (int) $this->total_sent,
            'total_delivered'  => (int) $this->total_delivered,
            'total_failed'     => (int) $this->total_failed,
            'created_at'       => optional($this->created_at)->toIso8601String(),
        ];
    }
}
