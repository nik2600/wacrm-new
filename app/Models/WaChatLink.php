<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WaChatLink extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'wa_chat_links';

    protected $fillable = [
        'workspace_id', 'user_id',
        'name', 'slug',
        'country_code', 'phone_number',
        'welcome_message',
        'utm_source', 'utm_medium', 'utm_campaign',
        'click_count', 'last_clicked_at',
        'expires_at', 'status',
    ];

    protected $casts = [
        'last_clicked_at' => 'datetime',
        'expires_at'      => 'datetime',
        'click_count'     => 'integer',
    ];

    /**
     * Unguessable 8-char slug. The slug is operator-customisable so the
     * uniqueness check happens in the controller; this is just the
     * default for new rows where no slug was supplied.
     */
    public static function freshSlug(): string
    {
        return Str::lower(Str::random(8));
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The full wa.me URL this short link redirects to.
     */
    public function waUrl(): string
    {
        $digits = preg_replace('/\D+/', '', $this->country_code . $this->phone_number);
        $url = 'https://wa.me/' . $digits;
        if (!empty(trim((string) $this->welcome_message))) {
            $url .= '?text=' . rawurlencode($this->welcome_message);
        }
        return $url;
    }

    /**
     * Short-link path served by the public redirect endpoint.
     */
    public function shortPath(): string
    {
        return '/l/' . $this->slug;
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }
}
