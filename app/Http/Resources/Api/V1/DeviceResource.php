<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a connected WhatsApp device in the customer API. Scramble
 * reads this to document the response. Wraps an App\Models\Device row —
 * device_name + country_code + phone_number are decrypted by the model
 * accessors before they reach here.
 *
 * `phone` is the full digits-only E.164 number (country code + national
 * number), the same shape DeviceController (mobile app) exposes. `engine`
 * is the workspace's messaging engine (unofficial / waba / twilio …); the
 * controller stamps it onto the model as a transient `engine` attribute
 * since the devices table itself has no per-row provider column.
 */
class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Device $device */
        $device = $this->resource;

        return [
            'id'           => $device->id,
            'name'         => (string) ($device->device_name ?? ''),
            'phone'        => $this->fullPhone($device),
            'engine'       => $this->publicEngine($device->engine ?? null),
            'status'       => $device->status,
            'active'       => (bool) $device->active,
            'last_seen_at' => optional($device->last_seen_at)->toIso8601String(),
        ];
    }

    /**
     * Map the internal engine code to a neutral public label so the API never
     * leaks the underlying library name (branding rule — "Unofficial API").
     */
    private function publicEngine(?string $engine): string
    {
        return match (strtolower((string) $engine)) {
            'baileys', 'unofficial', 'web' => 'unofficial',
            'wbiz', 'waba', 'wa_cloud', 'cloud', 'whatsapp_cloud' => 'whatsapp_cloud',
            'twilio' => 'twilio',
            default => $engine ? 'unofficial' : 'unknown',
        };
    }

    /** Full digits-only E.164 phone (country code + national number). */
    private function fullPhone(Device $device): string
    {
        return preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: '';
    }
}
