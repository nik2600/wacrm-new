<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A customer REST API key. Scoped to one workspace; "acts as" an owner user
 * so existing forCurrentWorkspace()/Auth::id() logic keeps working.
 */
class ApiKey extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id', 'name', 'key_hash', 'prefix',
        'scopes', 'last_used_at', 'expires_at', 'revoked_at', 'created_by',
    ];

    protected $casts = [
        'scopes'       => 'array',
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** True if the key is currently usable. */
    public function isActive(): bool
    {
        if ($this->revoked_at) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }

    /** Does the key hold a scope (null scopes = full access). */
    public function hasScope(string $scope): bool
    {
        return empty($this->scopes) || in_array($scope, $this->scopes, true);
    }

    /**
     * Mint a fresh key for a workspace. Returns [ApiKey $model, string $rawKey].
     * The raw key is shown to the customer ONCE; only its hash is stored.
     */
    public static function mint(int $workspaceId, int $userId, ?string $name = null, ?array $scopes = null, ?int $createdBy = null): array
    {
        $raw    = 'wsk_' . Str::random(40);
        $model  = static::create([
            'workspace_id' => $workspaceId,
            'user_id'      => $userId,
            'name'         => $name,
            'key_hash'     => hash('sha256', $raw),
            'prefix'       => substr($raw, 0, 12),
            'scopes'       => $scopes,
            'created_by'   => $createdBy ?? $userId,
        ]);
        return [$model, $raw];
    }
}
