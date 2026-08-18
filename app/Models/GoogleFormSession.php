<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per Google Form send from a flow. Created when the flow node
 * fires, marked submitted when the Apps Script webhook resumes the flow.
 */
class GoogleFormSession extends Model
{
    protected $fillable = [
        'workspace_id',
        'flow_session_id',
        'google_form_id',
        'contact_phone',
        'save_variable',
        'resume_port',
        'webhook_token',
        'answers_json',
        'submitted_at',
        'expires_at',
    ];

    protected $casts = [
        'answers_json' => 'array',
        'submitted_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];
}
