<?php

namespace App\Http\Requests\Api\V1\Contact;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create a contact in the current workspace. Scramble reads these rules to
 * document the request body. Mirrors the validation in the existing
 * ContactsController::store.
 */
class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // api-key middleware already authenticated the workspace
    }

    public function rules(): array
    {
        return [
            // Contact's full display name. Required when first_name is omitted.
            'name'            => ['required_without:first_name', 'nullable', 'string', 'max:191'],
            // Optional name parts. Combined into `name` when `name` is blank.
            'first_name'      => ['nullable', 'string', 'max:191'],
            'middle_name'     => ['nullable', 'string', 'max:191'],
            'last_name'       => ['nullable', 'string', 'max:191'],
            // Optional salutation/title (e.g. Mr, Dr).
            'title'           => ['nullable', 'string', 'max:50'],
            // Phone number in international format (digits, E.164). Required.
            'phone'           => ['required', 'string', 'max:32'],
            // Country dial code (e.g. +91). Prefixed onto the phone if missing.
            'country_code'    => ['nullable', 'string', 'max:10'],
            // Optional email address.
            'email'           => ['nullable', 'email', 'max:191'],
            // Optional preferred language / locale.
            'language'        => ['nullable', 'string', 'max:80'],
            // Optional postal address.
            'address'         => ['nullable', 'string', 'max:1000'],
            // Optional free-text note attached to the contact.
            'note'            => ['nullable', 'string', 'max:2000'],
            // Group ids the contact belongs to. Each must exist.
            'group_ids'       => ['nullable', 'array'],
            // Scope the existence check to the caller's workspace so a foreign
            // group id is rejected (was a global `exists` → cross-tenant ref).
            'group_ids.*'     => ['integer', \Illuminate\Validation\Rule::exists('contact_groups', 'id')->where('workspace_id', $this->user()?->current_workspace_id)],
            // Free-form key/value attributes stored against the contact.
            'attributes'      => ['nullable', 'array'],
            // Whether the contact has opted out of messages. Defaults to false.
            'is_unsubscribed' => ['nullable', 'boolean'],
        ];
    }
}
