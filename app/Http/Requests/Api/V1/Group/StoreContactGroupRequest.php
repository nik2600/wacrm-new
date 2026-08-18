<?php

namespace App\Http\Requests\Api\V1\Group;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create a contact group in the current workspace. Scramble reads these rules
 * to document the request body. Mirrors ContactsController::groupStore.
 */
class StoreContactGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // api-key middleware already authenticated the workspace
    }

    public function rules(): array
    {
        return [
            // Group name. Required.
            'name'  => ['required', 'string', 'max:191'],
            // Optional note/description for the group.
            'note'  => ['nullable', 'string', 'max:500'],
            // Optional UI colour (hex or token, e.g. #2563eb).
            'color' => ['nullable', 'string', 'max:32'],
        ];
    }
}
