<?php

namespace App\Http\Requests\Api\V1\Template;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update a WhatsApp message template. Scramble reads these rules to document
 * the request body. Mirrors the existing TemplateController::update validation.
 */
class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // api-key middleware already authenticated the workspace
    }

    public function rules(): array
    {
        return [
            // Template name. Required.
            'template_name' => ['required', 'string', 'max:191'],
            // Template type. Defaults to the existing value.
            'template_type' => ['nullable', 'in:standard,carousel,media,auth'],
            // Category — Meta numeric id (1/2/3), meta slug, or industry bucket. Required.
            'category'      => ['required'],
            // Optional header text (max 255 chars).
            'header'        => ['nullable', 'string', 'max:255'],
            // Body text. Required (max 4096 chars).
            'template_body' => ['required', 'string', 'max:4096'],
            // Optional footer text (max 255 chars).
            'footer'        => ['nullable', 'string', 'max:255'],
            // Language code (e.g. en_US). Defaults to the existing value.
            'language'      => ['nullable', 'string', 'max:16'],
            // Buttons — CTA / quick-reply array (or parallel-array form).
            'buttons'       => ['nullable'],
            // Quick-reply buttons (folded into buttons[]).
            'quick_replies' => ['nullable'],
            // Carousel cards payload (for carousel templates).
            'carousel_data' => ['nullable'],
            // LOCATION header — send a map pin. Provide latitude + longitude
            // (both required together) with an optional name + address.
            'latitude'         => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude'        => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'location_name'    => ['nullable', 'string', 'max:191'],
            'location_address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
