<?php

namespace App\Http\Requests\Api\V1\Broadcast;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create a broadcast — send one template to a list of contacts and/or a
 * contact group. Scramble reads these rules to document the request body.
 *
 * Mirrors the input keys the existing BroadcastsController::store accepts
 * (broadcast_name / template_id / contacts[] / groups[] / device_id /
 * schedule_type+send_date+send_time / timezone), exposed under public-facing
 * names. At least one of `recipients` or `group_id` is required; provide
 * `schedule_at` to schedule for later, omit it to send now.
 */
class StoreBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // api-key middleware already authenticated the workspace
    }

    public function rules(): array
    {
        return [
            // Broadcast label shown in the dashboard. Required.
            'name'          => ['required', 'string', 'max:255'],
            // Approved WaTemplate id to send to every recipient. Required.
            'template_id'   => ['required', 'integer'],
            // Recipient contact ids. Required unless `group_id` is given.
            'recipients'    => ['required_without:group_id', 'nullable', 'array'],
            'recipients.*'  => ['integer'],
            // Contact-group id — every member is added as a recipient.
            'group_id'      => ['required_without:recipients', 'nullable', 'integer'],
            // Sending device id (optional — defaults to the workspace's primary device).
            'device_id'     => ['nullable', 'integer'],
            // When to send — ISO 8601 / parseable datetime. Omit to send immediately.
            'schedule_at'   => ['nullable', 'string', 'max:64'],
            // IANA timezone for schedule_at (e.g. Asia/Kolkata). Defaults to UTC.
            'timezone'      => ['nullable', 'string', 'max:64'],
        ];
    }
}
