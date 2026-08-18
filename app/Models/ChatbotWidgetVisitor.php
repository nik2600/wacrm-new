<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChatbotWidgetVisitor extends Model
{
    use HasFactory;

    protected $table = 'chatbot_widget_visitors';

    protected $fillable = [
        'workspace_id', 'widget_id', 'conversation_id',
        'visitor_uuid', 'name', 'email', 'phone',
        'referrer_url', 'user_agent', 'ip',
        'first_seen_at', 'last_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at'  => 'datetime',
    ];

    public static function freshUuid(): string
    {
        return (string) Str::uuid();
    }

    public function widget(): BelongsTo
    {
        return $this->belongsTo(ChatbotWidget::class, 'widget_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function displayName(): string
    {
        if ($this->name) return $this->name;
        if ($this->email) return $this->email;
        return 'Visitor ' . substr($this->visitor_uuid, 0, 6);
    }
}
