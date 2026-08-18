<?php

namespace App\Http\Requests\Api\V1\AutoReply;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create a keyword auto-reply. Scramble reads these rules to document the
 * request body. Mirrors the input keys the existing
 * AutoreplyController::store accepts, exposed under public-facing names. A
 * "custom" reply carries one or more `replies[]` text variants; a "flow"
 * reply points at a saved flow_id.
 */
class StoreAutoReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // api-key middleware already authenticated the workspace
    }

    public function rules(): array
    {
        return [
            // Trigger keyword(s). Comma-separated phrases are allowed. Required.
            'keyword'          => ['required', 'string', 'max:255'],
            // How the inbound message is matched against the keyword. Defaults to exact.
            'matching_method'  => ['nullable', 'in:fuzzy,exact,contains'],
            // Fuzzy match threshold 0-100 (only used when matching_method=fuzzy).
            'fuzzy_similarity' => ['nullable', 'integer', 'min:0', 'max:100'],
            // Sending device id. Required.
            'device_id'        => ['required'],
            // Whether the rule is active. Defaults to true.
            'status'           => ['nullable', 'boolean'],
            // Reply kind: "custom" (text variants) or "flow" (a saved flow). Defaults to custom.
            'reply_type'       => ['nullable', 'in:custom,flow'],
            // Flow id to run (required when reply_type=flow).
            // Scope to the caller's workspace so an auto-reply can't reference
            // another tenant's flow (was a bare `integer` with no ownership).
            'flow_id'          => ['required_if:reply_type,flow', 'nullable', 'integer', \Illuminate\Validation\Rule::exists('flows', 'id')->where('workspace_id', $this->user()?->current_workspace_id)],
            // Reply text variants (for reply_type=custom). At least one required for custom.
            'replies'          => ['required_if:reply_type,custom', 'nullable', 'array'],
            'replies.*'        => ['string', 'max:4096'],
        ];
    }
}
