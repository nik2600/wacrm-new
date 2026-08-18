<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiProviderKey extends Model
{
    protected $fillable = ['workspace_id', 'provider', 'api_key', 'is_active'];

    protected $casts = [
        // Custom accessor/mutator below — NOT the 'encrypted' cast, so a
        // stale-encrypted blob (e.g. APP_KEY rotation) returns null instead
        // of throwing through AiKeyResolver and 500'ing every AI feature.
        'is_active' => 'boolean',
    ];

    /** Safe decrypt — returns null on stale APP_KEY / corrupted payload. */
    public function getApiKeyAttribute($value): ?string
    {
        if ($value === null || $value === '') return null;
        try {
            return \Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Encrypt on save. Empty clears. */
    public function setApiKeyAttribute($value): void
    {
        $this->attributes['api_key'] = ($value === null || $value === '')
            ? null
            : \Crypt::encryptString((string) $value);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public static function keyFor(int $workspaceId, string $provider): ?string
    {
        $row = static::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();

        return $row?->api_key;
    }
}
