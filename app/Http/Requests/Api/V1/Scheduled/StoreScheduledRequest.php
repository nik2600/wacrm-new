<?php

namespace App\Http\Requests\Api\V1\Scheduled;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Schedule a WhatsApp send for later. Scramble reads these rules to document
 * the request body. Mirrors the input keys the existing
 * QueueController::scheduleMessage accepts (name / message / template_id /
 * recipients / send_at / timezone / device_id), exposed under public-facing
 * names. A template OR a message body is required.
 */
class StoreScheduledRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // api-key middleware already authenticated the workspace
    }

    public function rules(): array
    {
        return [
            // Schedule label. Required.
            'name'         => ['required', 'string', 'max:255'],
            // Body text (required unless a template_id is given). Max 4096 chars.
            'message'      => ['required_without:template_id', 'nullable', 'string', 'max:4096'],
            // WaTemplate id to send instead of free text (optional).
            'template_id'  => ['nullable', 'integer'],
            // Sending device id (optional — defaults to the workspace's connected device).
            'device_id'    => ['nullable', 'integer'],
            // Recipient phone numbers in international format. At least one required.
            'recipients'   => ['required', 'array', 'min:1'],
            'recipients.*' => ['required', 'string', 'max:32'],
            // When to send — ISO 8601 / parseable datetime interpreted in `timezone`. Required.
            'run_at'       => ['required', 'string', 'max:64'],
            // IANA timezone for run_at (e.g. Asia/Kolkata). Defaults to the workspace timezone.
            'timezone'     => ['nullable', 'string', 'max:64'],
        ];
    }
}
