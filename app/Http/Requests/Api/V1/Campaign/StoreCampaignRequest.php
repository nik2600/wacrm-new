<?php

namespace App\Http\Requests\Api\V1\Campaign;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create a campaign — a multi-recipient send (template, custom message or
 * flow) with optional A/B testing. Scramble reads these rules to document the
 * request body.
 *
 * Mirrors the input keys the existing App\CampaignController::store accepts
 * (campaign_name / device_id / campaign_type / schedule_type / template_id(s) /
 * flow_id / custom_message / send_date+send_time / timezone / ab_* / contacts),
 * exposed under public-facing names. Recipients are supplied as a list of
 * phone numbers in international format.
 */
class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // api-key middleware already authenticated the workspace
    }

    public function rules(): array
    {
        return [
            // Campaign label shown in the dashboard. Required.
            'campaign_name'  => ['required', 'string', 'max:255'],
            // Sending device — the device's phone number or id. Required.
            'device_id'      => ['required'],
            // What the campaign sends. Required.
            'campaign_type'  => ['required', 'in:custom,template,flow'],
            // When to send: now, later (needs send_date+send_time), or recurring. Required.
            'schedule_type'  => ['required', 'in:now,later,recurring'],
            // WaTemplate id (required for non-A/B template campaigns).
            'template_id'    => ['nullable', 'integer'],
            // A/B testing: the two competing WaTemplate ids.
            'template_id_a'  => ['nullable', 'integer'],
            'template_id_b'  => ['nullable', 'integer'],
            // Flow id (required for flow campaigns).
            'flow_id'        => ['nullable', 'integer'],
            // Free-text message body (required for custom campaigns).
            'custom_message' => ['required_if:campaign_type,custom', 'nullable', 'string', 'max:4096'],
            // Date to send on (Y-m-d) — required when schedule_type=later.
            'send_date'      => ['required_if:schedule_type,later', 'nullable', 'date'],
            // Time to send at (HH:MM) — required when schedule_type=later.
            'send_time'      => ['required_if:schedule_type,later', 'nullable', 'string', 'max:16'],
            // IANA timezone for send_date/send_time. Defaults to UTC.
            'timezone'       => ['nullable', 'string', 'max:64'],
            // Enable A/B testing (template campaigns only).
            'ab_testing'     => ['nullable'],
            // Percentage of recipients sent variant A (10-90). Defaults to 50.
            'ab_split'       => ['nullable', 'integer', 'min:10', 'max:90'],
            // Recipient phone numbers in international format. At least one required.
            'contacts'       => ['required', 'array', 'min:1'],
            'contacts.*'     => ['string', 'max:32'],
        ];
    }
}
