<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Local cache of Meta's call-permission grants. Meta caches the user's
 * accept/decline for ~7 days on its side; we mirror locally so the
 * dial UI can decide "do we need to send a fresh permission_request
 * first?" without burning a Meta round-trip on every click.
 *
 *   status = granted   — Meta accepted, dial freely until expires_at
 *   status = declined  — user said no; UI shows a disabled button
 *   status = expired   — TTL elapsed, request again before dial
 */
class WaCallPermission extends Model
{
    protected $fillable = [
        'workspace_id', 'wa_provider_config_id', 'contact_phone',
        'status', 'granted_at', 'expires_at',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function providerConfig(): BelongsTo
    {
        return $this->belongsTo(WaProviderConfig::class, 'wa_provider_config_id');
    }

    public function isUsable(): bool
    {
        if ($this->status !== 'granted') return false;
        if (!$this->expires_at) return true;
        return $this->expires_at->isFuture();
    }
}
