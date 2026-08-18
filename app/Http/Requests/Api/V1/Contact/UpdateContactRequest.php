<?php

namespace App\Http\Requests\Api\V1\Contact;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update an existing contact in the current workspace. Every field is
 * optional — only the keys present in the request body are changed. Scramble
 * reads these rules to document the request body.
 */
class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // api-key middleware already authenticated the workspace
    }

    public function rules(): array
    {
        return [
            // Contact's full display name.
            'name'            => ['nullable', 'string', 'max:191'],
            // Optional name parts.
            'first_name'      => ['nullable', 'string', 'max:191'],
            'middle_name'     => ['nullable', 'string', 'max:191'],
            'last_name'       => ['nullable', 'string', 'max:191'],
            // Optional salutation/title (e.g. Mr, Dr).
            'title'           => ['nullable', 'string', 'max:50'],
            // Phone number in international format (digits, E.164).
            'phone'           => ['nullable', 'string', 'max:32'],
            // Country dial code (e.g. +91). Prefixed onto the phone if missing.
            'country_code'    => ['nullable', 'string', 'max:10'],
            // Email address.
            'email'           => ['nullable', 'email', 'max:191'],
            // Preferred language / locale.
            'language'        => ['nullable', 'string', 'max:80'],
            // Postal address.
            'address'         => ['nullable', 'string', 'max:1000'],
            // Free-text note attached to the contact.
            'note'            => ['nullable', 'string', 'max:2000'],
            // Group ids the contact belongs to (replaces the existing set).
            'group_ids'       => ['nullable', 'array'],
            // Scope the existence check to the caller's workspace so a foreign
            // group id is rejected (was a global `exists` → cross-tenant ref).
            'group_ids.*'     => ['integer', \Illuminate\Validation\Rule::exists('contact_groups', 'id')->where('workspace_id', $this->user()?->current_workspace_id)],
            // Free-form key/value attributes stored against the contact.
            'attributes'      => ['nullable', 'array'],
            // Whether the contact has opted out of messages.
            'is_unsubscribed' => ['nullable', 'boolean'],
        ];
    }
}
