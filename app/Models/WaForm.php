<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable WhatsApp interactive form (Meta calls these "Flows" in
 * their API, we surface them as "Forms" to operators since that's the
 * customer-facing label on the receiving end).
 *
 * Published forms have a `meta_flow_id` — that's what we reference in
 * the WABA `messages.interactive.action.parameters.flow_id` payload
 * when the flow-builder's `wa_form` node fires.
 */
class WaForm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id', 'user_id', 'title', 'purpose', 'slug', 'audience_type',
        'submission_cap', 'cap_reached_note',
        'send_button_label', 'thank_you_note',
        'definition_json', 'status', 'meta_flow_id', 'published_at',
        'publish_error', 'submission_count',
    ];

    protected $casts = [
        'definition_json' => 'array',
        'published_at'    => 'datetime',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(WaFormSubmission::class, 'form_id');
    }

    public function isLive(): bool
    {
        return $this->status === 'published' && !empty($this->meta_flow_id);
    }

    public function fieldsCount(): int
    {
        $count = 0;
        foreach ((array) ($this->definition_json['screens'] ?? []) as $screen) {
            $count += count((array) ($screen['fields'] ?? []));
        }
        return $count;
    }
}
