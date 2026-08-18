<?php

namespace App\Http\Requests\Api\V1\Flow;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Enroll a contact into a flow. Scramble reads these rules to document the
 * request body. Identify the contact by `contact_id` (a contact already in
 * the workspace) OR by `phone` (the contact is looked up by its E.164
 * number). Exactly one is required; the controller resolves it to a Contact
 * and hands it to FlowEnrollmentService::enroll().
 */
class EnrollFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // api-key middleware already authenticated the workspace
    }

    public function rules(): array
    {
        return [
            // Id of an existing contact in the workspace. Required unless `phone` is given.
            'contact_id' => ['required_without:phone', 'nullable', 'integer'],
            // Contact phone in international format (digits, E.164). Used to look up
            // the contact when `contact_id` is omitted. Required unless `contact_id` is given.
            'phone'      => ['required_without:contact_id', 'nullable', 'string', 'max:32'],
        ];
    }
}
