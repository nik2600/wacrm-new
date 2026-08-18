<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Send a single WhatsApp message. Scramble reads these rules to document the
 * request body. Supports text, media (by URL) and location; template/bulk
 * sends use the /templates + /broadcasts endpoints.
 */
class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // api-key middleware already authenticated the workspace
    }

    /**
     * Normalise device_id to a string BEFORE validation so a JSON number (59)
     * and a string ("cfg_20") both pass the `string` rule. The controller then
     * resolves it across the devices + wa_provider_configs tables.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('device_id') && $this->input('device_id') !== null) {
            $this->merge(['device_id' => (string) $this->input('device_id')]);
        }
    }

    public function rules(): array
    {
        return [
            // Recipient phone in international format (digits, E.164). Required.
            'to'          => ['required', 'string', 'max:32'],
            // Sending sender (optional — defaults to the workspace's primary).
            // Accepts a Baileys device id (57), a WABA/Twilio provider-config id
            // ("cfg_20" or a bare 20), or an "engine:id" key — resolved across
            // both the devices + wa_provider_configs tables. String, not int,
            // so the "cfg_20" form from /api/v1/devices is accepted.
            'device_id'   => ['nullable', 'string', 'max:40'],
            // Message type. Defaults to "text".
            'type'        => ['nullable', 'in:text,image,video,document,audio,location'],
            // Text body (required for text; used as caption for media).
            'text'        => ['nullable', 'string', 'max:4096'],
            // Image / video / document / audio sends — EITHER way:
            //   media     — attach the file directly (multipart upload, max 16 MB), OR
            //   media_url — a public URL we download for you.
            // No need to host the file first; just send `media` in the same call.
            'media'       => ['nullable', 'file', 'max:16384'],
            'media_url'   => ['nullable', 'url', 'max:2048'],
            // Location (when type=location).
            'latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
