<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformNote extends Model
{
    protected $fillable = [
        'conversation_id', 'workspace_id', 'admin_user_id',
        'body', 'severity',
    ];

    protected $casts = [
        // body is internal SaaS-staff commentary about a customer; never
        // surfaced to the workspace UI but still encrypt at rest for parity
        // with conversation_notes.
        'body' => 'encrypted',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id')->withDefault();
    }
}
