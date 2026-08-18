<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaFormSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id', 'workspace_id', 'contact_id', 'conversation_id',
        'flow_token', 'caller_phone', 'answers_json', 'meta_payload', 'submitted_at',
    ];

    protected $casts = [
        'answers_json' => 'array',
        'meta_payload' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function form(): BelongsTo { return $this->belongsTo(WaForm::class, 'form_id'); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
}
