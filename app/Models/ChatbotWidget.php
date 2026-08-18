<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ChatbotWidget extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'chatbot_widgets';

    protected $fillable = [
        'workspace_id', 'user_id', 'assistant_id',
        'name', 'slug', 'embed_token', 'mode',
        'target_whatsapp_cc', 'target_whatsapp_number', 'prefilled_message',
        'position', 'button_color', 'button_image_url',
        'header_title', 'header_bg', 'header_text_color',
        'welcome_message', 'message_bubble_color', 'message_text_color',
        'body_bg_kind', 'body_bg_color', 'body_bg_image_url', 'auto_open',
        'button_label', 'action_button_bg', 'action_button_text_color',
        'collect_name', 'collect_email', 'collect_phone',
        'status', 'allowed_domains',
    ];

    protected $casts = [
        'auto_open'       => 'boolean',
        'collect_name'    => 'boolean',
        'collect_email'   => 'boolean',
        'collect_phone'   => 'boolean',
        'allowed_domains' => 'array',
    ];

    public static function freshToken(): string
    {
        return Str::random(48);
    }

    /**
     * Check whether a request's Origin header is permitted to call this
     * widget's public endpoints.
     *
     * Behaviour:
     *   - allowed_domains EMPTY / NULL  → allow EVERY origin (default).
     *     This is how a freshly-created widget can be embedded on any
     *     site without per-domain config — the embed_token itself is
     *     the access gate, plus per-IP rate limiting protects abuse.
     *   - allowed_domains NON-EMPTY     → only those origins.
     *     Operators tighten this once they know the live embed hosts.
     *
     * Matching is case-insensitive on host[:port]. Wildcard subdomains
     * are NOT supported — operators add each origin explicitly so
     * "*.acme.com" can't accidentally include "evil.acme.com".
     */
    public function originAllowed(?string $origin): bool
    {
        if (!$origin) return false;

        $allowed = is_array($this->allowed_domains) ? $this->allowed_domains : [];
        // Empty allow-list → public widget, every origin permitted.
        // The CORS middleware will still echo the request Origin back
        // into Access-Control-Allow-Origin (no `*`) so credentials
        // continue to flow.
        if (empty($allowed)) return true;

        // Parse the request Origin (`https://acme.com:8080`) down to
        // host[:port] so the operator can copy/paste from the address
        // bar without worrying about https:// stripping.
        $parsed = parse_url($origin);
        if (!$parsed || empty($parsed['host'])) return false;
        $hostPort = strtolower($parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : ''));

        foreach ($allowed as $entry) {
            $entry = strtolower(trim((string) $entry));
            if ($entry === '') continue;
            // Strip any accidentally-pasted scheme/path so "https://acme.com/"
            // still matches "acme.com" entries.
            $entry = preg_replace('#^https?://#', '', $entry);
            $entry = rtrim(strtok($entry, '/'), '/');
            if ($entry === $hostPort) return true;
        }
        return false;
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(AiChatAssistant::class, 'assistant_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(ChatbotWidgetVisitor::class, 'widget_id');
    }

    public function usesAi(): bool
    {
        return in_array($this->mode, ['ai', 'both'], true) && $this->assistant_id !== null;
    }

    public function usesWhatsApp(): bool
    {
        return in_array($this->mode, ['whatsapp', 'both'], true) && !empty($this->target_whatsapp_number);
    }
}
