<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'name',
        'display_name',
        'email',
        'password',
        'passcode',
        'site_name',
        'mobile',
        'country_code',
        'role',
        'current_workspace_id',
        'has_seen_intro',
        'avatar_path',
        // Affiliate / wallet
        'referral_code',
        'referred_by_user_id',
        'wallet_credits',
        'wallet_currency_minor',
        'wallet_currency_code',
        // Sprint 6 — 2FA + theme.
        'two_factor_enabled', 'two_factor_secret', 'two_factor_confirmed_at',
        'two_factor_recovery_codes', 'theme_preference',
        // Personal UX prefs.
        'auto_ai_summarize_enabled',
        // Admin-managed profile fields.
        'address', 'city', 'state', 'country', 'zip', 'notes',
        'force_password_change', 'password_changed_at', 'welcome_email_sent_at',
        // Social sign-in (Google / Facebook).
        'social_provider', 'social_provider_id',
    ];

    protected $hidden = [
        'password',
        'passcode',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'           => 'datetime',
            'password'                    => 'hashed',
            'has_seen_intro'              => 'boolean',
            'wallet_credits'              => 'integer',
            'wallet_currency_minor'       => 'integer',
            'sheets_api_key_created_at'   => 'datetime',
            'sheets_api_key_last_used_at' => 'datetime',
            'inbox_last_seen_at'          => 'datetime',
            'quick_access'                => 'array',
            // Sprint 6 — 2FA. Secret + recovery codes are encrypted at
            // rest; only the user model decrypts them. The two_factor
            // service treats the decrypted value as the base32 TOTP
            // secret.
            'two_factor_enabled'          => 'boolean',
            'two_factor_secret'           => 'encrypted',
            'two_factor_recovery_codes'   => 'encrypted',
            'two_factor_confirmed_at'     => 'datetime',
            'auto_ai_summarize_enabled'   => 'boolean',
            'force_password_change'       => 'boolean',
            'password_changed_at'         => 'datetime',
            'welcome_email_sent_at'       => 'datetime',
        ];
    }

    /**
     * Auto-generate a referral_code on first save if none was set.
     * 10 chars from a confusion-free alphabet (no 0/O/1/l) — short
     * enough to fit on a card, long enough to make collisions a
     * lottery rather than a concern.
     */
    protected static function booted(): void
    {
        static::creating(function (self $u) {
            if (empty($u->referral_code)) {
                $u->referral_code = self::generateUniqueReferralCode();
            }
        });
    }

    public static function generateUniqueReferralCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // skip 0,O,1,I,L
        do {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('referral_code', $code)->exists());
        return $code;
    }

    public function referrer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_user_id');
    }

    public function referrals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_user_id');
    }

    public function walletTransactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /** Whether this user is registered as a platform support agent. */
    public function supportAgent()
    {
        return $this->hasOne(\App\Models\SupportAgent::class)->whereNull('workspace_id');
    }

    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role', 'invited_at', 'joined_at')
            ->withTimestamps();
    }

    /**
     * The workspace the user is currently viewing — drives every
     * workspace-scoped query (inbox, billing, devices, etc.). Maps to
     * users.current_workspace_id which is updated on every workspace
     * switch in the header dropdown.
     */
    public function currentWorkspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    public function ownedWorkspaces()
    {
        return $this->hasMany(Workspace::class, 'owner_user_id');
    }

    /**
     * Can this user create ANOTHER workspace under their current plan?
     *
     * Mirrors the PlanLimitGuard check in WorkspacesController::store
     * (`workspaces_per_owner_limit` vs owned-workspace count, admins bypass) so
     * the "Create new workspace" button + the create page can hide/deny BEFORE
     * the form is ever submitted — instead of letting the user fill it in and
     * bounce back with an invisible error.
     */
    public function canCreateWorkspace(): bool
    {
        if ($this->isAdmin()) {
            return true; // admins are never capped — same as PlanLimitGuard::bypass
        }
        $ws = $this->currentWorkspace;
        if (!$ws) {
            return true; // no workspace yet → allow the very first
        }
        $limit = null;
        try {
            $limit = $ws->effectiveLimit('workspaces_per_owner_limit', null);
        } catch (\Throwable $e) {
            return true; // never block on a plan-lookup hiccup
        }
        // null / 0 / negative = unlimited (same convention as PlanLimitGuard).
        if ($limit === null || (int) $limit <= 0) {
            return true;
        }
        return Workspace::where('owner_user_id', $this->id)->count() < (int) $limit;
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->withPivot('is_lead', 'capacity')
            ->withTimestamps();
    }

    public function teamsInWorkspace(int $workspaceId)
    {
        return $this->teams()->where('workspace_id', $workspaceId);
    }

    public function agentStatus(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AgentStatus::class);
    }

    public function agentStatusForCurrent(): ?AgentStatus
    {
        if (!$this->current_workspace_id) return null;
        return AgentStatus::firstOrCreate(
            ['user_id' => $this->id, 'workspace_id' => $this->current_workspace_id],
            ['status' => 'online', 'last_seen_at' => now(), 'counters_date' => now()->toDateString()],
        );
    }

    /**
     * Workspace-scoped role for this user. Reads `workspace_user.role` for
     * the workspace they're currently viewing — orthogonal to platform-level
     * Spatie roles, which live in `model_has_roles`.
     *
     * Returns null if the user is not a member of the workspace.
     */
    public function workspaceRole(?int $workspaceId = null): ?string
    {
        $workspaceId ??= $this->current_workspace_id;
        if (!$workspaceId) return null;
        $row = $this->workspaces()->where('workspaces.id', $workspaceId)->first();
        if ($row?->pivot?->role) return $row->pivot->role;

        // Impersonation grant. A platform admin with an active impersonation
        // session has NO pivot row in the target workspace, so WITHOUT this
        // they'd resolve to null → WorkspacePermissions::userCan() denies every
        // action and the admin can only look, not operate. Grant full Owner so
        // the admin runs the workspace exactly as its owner would — see AND do
        // everything, nothing hidden. Scoped strictly to the impersonation
        // TARGET; the admin's own workspaces resolve via the pivot above.
        if ($this->isImpersonatingWorkspace((int) $workspaceId)) {
            return \App\Support\WorkspacePermissions::ROLE_OWNER;
        }
        return null;
    }

    /**
     * True when this user is a platform admin actively impersonating the given
     * workspace. Memoized per request (one query, all active targets) so the
     * many userCan()/workspaceRole() calls per page don't each hit the DB.
     */
    public function isImpersonatingWorkspace(int $workspaceId): bool
    {
        if ($this->impersonationTargetsCache === null) {
            // Session-scoped, exactly like ImpersonationBanner: only the browser
            // session that started the impersonation carries the key, so the
            // Owner grant never leaks to another admin / tab on the same account.
            $sessId = null;
            try {
                if (app()->bound('session') && session()->isStarted()) {
                    $sessId = session()->get('impersonation_session_id');
                }
            } catch (\Throwable $e) {
                $sessId = null;
            }
            $this->impersonationTargetsCache = $sessId
                ? \App\Models\ImpersonationSession::active()
                    ->forAdmin($this->id)
                    ->whereKey($sessId)
                    ->pluck('target_workspace_id')
                    ->map(fn ($v) => (int) $v)
                    ->all()
                : [];
        }
        return in_array($workspaceId, $this->impersonationTargetsCache, true);
    }

    /** @var array<int>|null per-request cache of active impersonation target workspace ids */
    protected ?array $impersonationTargetsCache = null;

    public function currentWorkspaceRel()
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    public function getCurrentWorkspaceAttribute(): ?Workspace
    {
        if ($this->current_workspace_id) {
            $w = Workspace::find($this->current_workspace_id);
            if ($w) return $w;
        }
        return $this->workspaces()->first();
    }

    public function switchWorkspace(int $workspaceId): bool
    {
        if (!$this->workspaces()->where('workspaces.id', $workspaceId)->exists()) {
            return false;
        }
        $previousId = $this->current_workspace_id;
        $this->forceFill(['current_workspace_id' => $workspaceId])->save();
        Workspace::query()->whereKey($workspaceId)->update(['last_active_at' => now()]);

        // Audit the entry so the activity log can show "user X entered
        // workspace Y" with IP / UA. Skip the no-op (re-entering the
        // same workspace) so login-time fallback resolutions don't
        // generate a useless event.
        if ($previousId !== $workspaceId) {
            \App\Services\Inbox\AuditLogger::workspace(
                'workspace.entered',
                $this->id,
                $workspaceId,
                'workspace',
                $workspaceId,
                ['from' => $previousId]
            );
        }

        return true;
    }

    /**
     * True if this user is a platform admin. Accepts Spatie roles
     * "Super Admin" / "Admin" first, then falls back to the legacy
     * `role` column ('admin' / 'A') so older installs keep working.
     */
    public function isAdmin(): bool
    {
        try {
            if ($this->hasRole('Super Admin') || $this->hasRole('Admin')) {
                return true;
            }
        } catch (\Throwable $e) {
            // Spatie tables may not be migrated yet during very early bootstrapping.
        }

        return in_array($this->role, ['admin', 'A'], true);
    }

    /**
     * Full URL for the user's avatar — or null if they haven't
     * uploaded one. Views fall back to initials when null.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (empty($this->avatar_path)) return null;
        // Social sign-in stores the provider's hosted avatar URL verbatim
        // (Google / Facebook CDN). Pass absolute URLs straight through —
        // only local uploads live under storage/.
        if (Str::startsWith($this->avatar_path, ['http://', 'https://'])) {
            return $this->avatar_path;
        }
        return media_url($this->avatar_path);
    }
}
