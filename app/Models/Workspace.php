<?php

namespace App\Models;

use App\Models\Concerns\LogsNotifications;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use HasFactory, SoftDeletes, LogsNotifications;

    protected $fillable = [
        'owner_user_id', 'name', 'slug', 'plan', 'timezone', 'locale',
        // Multi-engine (Phase 0): the subset of platform-allowed engines this
        // workspace runs (NULL = all connected) + the default engine for sends
        // that don't pin a sender. Resolved via WorkspaceEngine.
        'enabled_engines', 'default_engine',
        // Meta Business Agent coexistence — stand our auto-AI down when Meta's agent fronts.
        'meta_agent_enabled', 'ai_responder_mode',
        'brand_color', 'industry', 'size_range', 'status', 'last_active_at',
        'business_hours', 'plan_overrides', 'appointment_settings', 'currency',
        // Sales Pipeline — opt-in auto-create a deal from new orders + min value.
        'deals_auto_from_orders', 'deals_auto_min_minor',
        // Sprint 6 — branding tab.
        'brand_primary', 'brand_accent', 'brand_background',
        'brand_logo_path', 'brand_favicon_path',
        // Plan-gated outbound message footer. Resolved by
        // BrandingFooterService — when the workspace's plan grants
        // remove_branding=true, this string is used; otherwise the
        // platform-fixed footer applies.
        'branding_footer',
        // Sprint 6 — notifications tab.
        'notification_prefs',
        // Sprint 7 — multilingual auto-reply settings.
        'auto_translate_languages', 'default_language',
        // Real-time conversation translation — master toggle for inbox auto-translate.
        'inbox_translate',
        // Auto Responder — manual "Away mode" switch. When ON, `away` trigger-type
        // auto-reply rules fire (like WhatsApp Business away messages).
        'inbox_away',
        // Sprint 9.3 — data residency (any / eu_only / local).
        'data_residency',
        // WABA calling tunables — how long to ring before AI takes
        // over, how long before voicemail, which agent answers.
        'auto_pickup_delay_sec', 'voicemail_delay_sec', 'default_voice_ai_agent_id',
        // Admin-set provisioning fields.
        'custom_domain', 'cname_verified', 'country',
        'billing_cycle', 'trial_ends_at', 'plan_ends_at', 'cap_monthly_messages', 'cap_daily_messages',
        'cap_devices', 'cap_users',
        'skip_onboarding_email', 'bill_to_platform_credit', 'pre_seed_sample_data',
        'admin_note',
    ];

    /** Drivers allowed under each data-residency mode. */
    public const RESIDENCY_ALLOWED_SLUGS = [
        'any'     => null,   // null = no restriction
        'eu_only' => ['deepl', 'libretranslate'],
        'local'   => ['libretranslate'],
    ];

    protected $casts = [
        'status'               => 'boolean',
        'inbox_translate'      => 'boolean',
        'inbox_away'           => 'boolean',
        'deals_auto_from_orders' => 'boolean',
        'deals_auto_min_minor'   => 'integer',
        'meta_agent_enabled'   => 'boolean',
        'last_active_at'       => 'datetime',
        'trial_ends_at'        => 'datetime',
        'plan_ends_at'         => 'datetime',
        'business_hours'       => 'array',
        'plan_overrides'       => 'array',
        'enabled_engines'      => 'array',
        'appointment_settings'    => 'encrypted:array',
        'notification_prefs'      => 'array',
        // Sprint 7 — list of ISO codes the auto-reply translator fans
        // every keyword + reply into at save time.
        'auto_translate_languages' => 'array',
        // WABA calling timers — integers (seconds).
        'auto_pickup_delay_sec'    => 'integer',
        'voicemail_delay_sec'      => 'integer',
        'default_voice_ai_agent_id'=> 'integer',
        'cname_verified'           => 'boolean',
        'skip_onboarding_email'    => 'boolean',
        'bill_to_platform_credit'  => 'boolean',
        'pre_seed_sample_data'     => 'boolean',
        'cap_monthly_messages'     => 'integer',
        'cap_daily_messages'       => 'integer',
        'cap_devices'              => 'integer',
        'cap_users'                => 'integer',
    ];

    /**
     * Per-request cache for package() so effectiveLimit() (called many
     * times per request by the plan gates) doesn't re-query. Keyed by
     * the current `plan` value so it self-invalidates if `plan` changes
     * on the instance mid-request.
     */
    protected $packageCacheKey = false;
    protected $packageCache    = null;

    /**
     * Per-request cache for billingPackage() (the scope-aware resolver).
     * Keyed by the owner id it was resolved for so it self-invalidates.
     */
    protected $billingPackageResolvedFor = false;
    protected $billingPackageCache       = null;

    /**
     * Per-event defaults the notification dispatcher falls back to
     * when a workspace hasn't configured a specific channel.
     *
     *   $channel ∈ {'inapp', 'email', 'slack'}
     *
     * Returns true when the channel is ENABLED for the given event.
     * Used by NotificationHelper::toUser() to skip dispatch when the
     * workspace owner has switched it off in /settings?tab=notifications.
     */
    public function wantsNotification(string $event, string $channel = 'inapp'): bool
    {
        $prefs = $this->notification_prefs ?? [];
        if (!is_array($prefs)) return true;

        // Per-event explicit choice wins.
        if (isset($prefs[$event][$channel])) return (bool) $prefs[$event][$channel];

        // Fall back to the per-category default if the event name
        // matches a category (device, billing, etc.) — saves the user
        // from having to opt every individual sub-event in.
        if (isset($prefs['_categories'][$event][$channel])) {
            return (bool) $prefs['_categories'][$event][$channel];
        }

        return self::DEFAULT_NOTIFICATION_CHANNELS[$event][$channel]
            ?? self::DEFAULT_NOTIFICATION_CHANNELS['_default'][$channel]
            ?? true;
    }

    /**
     * Authoritative event catalog. Keys map 1:1 to NotificationHelper
     * categories where possible; a few are specific high-signal
     * events (wallet_low, plan_changed, device_disconnected, etc.)
     * that the UI tags directly so users can mute the noisy stream
     * without losing the urgent one.
     */
    public const NOTIFICATION_EVENTS = [
        'device_connected'      => 'Device connected',
        'device_disconnected'   => 'Device disconnected',
        'campaign_completed'    => 'Campaign completed',
        'campaign_failed'       => 'Campaign failed',
        'broadcast_completed'   => 'Broadcast completed',
        'new_customer_reply'    => 'New customer reply',
        'mention_in_inbox'      => 'You were @mentioned',
        'conversation_assigned' => 'Conversation assigned to you',
        'sla_breached'          => 'SLA breached',
        'wallet_low_balance'    => 'Wallet low balance',
        'plan_changed'          => 'Plan upgraded / downgraded',
        'payment_failed'        => 'Payment failed',
        'webhook_delivery_failed' => 'Webhook delivery failed',
        'flow_started'          => 'Flow started',
        'flow_failed'           => 'Flow failed',
        'integration_disconnected' => 'Integration disconnected',
        'appointment_booked'    => 'Appointment booked',
        'weekly_summary'        => 'Weekly summary',
    ];

    /**
     * Sensible default toggles applied when the workspace owner
     * hasn't visited /settings?tab=notifications yet. Mirrors what a
     * well-run SaaS sends out of the box — quiet email by default,
     * but every critical event lights up the in-app bell.
     */
    public const DEFAULT_NOTIFICATION_CHANNELS = [
        '_default'                => ['inapp' => true,  'email' => false, 'slack' => false],
        'device_disconnected'     => ['inapp' => true,  'email' => true,  'slack' => false],
        'wallet_low_balance'      => ['inapp' => true,  'email' => true,  'slack' => true],
        'payment_failed'          => ['inapp' => true,  'email' => true,  'slack' => false],
        'sla_breached'            => ['inapp' => true,  'email' => true,  'slack' => true],
        'campaign_failed'         => ['inapp' => true,  'email' => true,  'slack' => false],
        'webhook_delivery_failed' => ['inapp' => true,  'email' => true,  'slack' => false],
        'integration_disconnected'=> ['inapp' => true,  'email' => true,  'slack' => false],
        'weekly_summary'          => ['inapp' => false, 'email' => true,  'slack' => false],
    ];

    /**
     * Resolve a workspace limit. Per-workspace override wins; otherwise
     * we read the value from the assigned package row. `$fallback` is
     * the return when neither the override nor the package defines a
     * value (e.g. a brand-new column the seeded packages haven't been
     * backfilled with yet).
     */
    public function effectiveLimit(string $key, int|string|null $fallback = null): int|string|null
    {
        $overrides = is_array($this->plan_overrides) ? $this->plan_overrides : [];
        if (array_key_exists($key, $overrides)) {
            return $overrides[$key];   // explicit per-workspace override wins outright
        }

        // Platform admins own the install — no plan limit or feature flag ever
        // binds them. Grant every boolean feature (true) and treat every numeric
        // cap as unlimited (-1 = the system-wide "no cap" sentinel, honoured by
        // every limit consumer). Config STRINGS (e.g. data_residency) pass
        // through unchanged so they aren't corrupted into a number.
        if ($this->ownedByPlatformAdmin()) {
            $pkg = $this->billingPackage();
            $declared = ($pkg && isset($pkg->$key)) ? $pkg->$key : $fallback;
            if (is_bool($declared)) return true;
            if (is_int($declared) || $declared === null) return -1;
            return $declared;
        }

        // Scope-aware: in account mode this becomes the owner's best plan.
        $package = $this->billingPackage();
        $base = ($package && isset($package->$key)) ? $package->$key : $fallback;

        // Merge purchased ADD-ONS on top of the base plan. This is what lets a
        // customer whose plan lacks (say) Campaigns buy a "Campaigns add-on"
        // and get it — without switching plans. Booleans: any active add-on
        // that grants the feature flips it on (OR). Numeric limits STACK on the
        // base (sum) so "+1 number" add-ons add up; -1 = unlimited wins.
        $addons = $this->activeAddonPackages();
        if ($addons->isNotEmpty()) {
            if (is_bool($base)) {
                // An add-on grants a feature ONLY when it explicitly lists it
                // (grants_json.features). Blank fields grant nothing.
                foreach ($addons as $a) {
                    if ($a->grantsFeature($key)) return true;
                }
            } elseif (is_int($base)) {
                // Stack ONLY the deltas an add-on explicitly declares. A blank
                // limit returns null here (not 0), so it can never leak an
                // "unlimited" grant onto a cap the add-on never meant to touch.
                $vals = [(int) $base];
                foreach ($addons as $a) {
                    $delta = $a->grantLimit($key);
                    if ($delta !== null) $vals[] = (int) $delta;
                }
                if (in_array(-1, $vals, true)) return -1;        // unlimited anywhere → unlimited
                if (count($vals) > 1) return array_sum($vals);   // stack add-on limits
            }
        }

        return $base;
    }

    /**
     * Is this workspace owned by a platform admin? Such workspaces are never
     * plan-gated — effectiveLimit() grants every feature + unlimited caps, and
     * the trial/plan middleware wave them through. Cached per request by owner.
     */
    public function ownedByPlatformAdmin(): bool
    {
        $ownerId = (int) ($this->owner_user_id ?? 0);
        if ($ownerId <= 0) return false;
        static $cache = [];
        if (array_key_exists($ownerId, $cache)) return $cache[$ownerId];
        try {
            $owner = \App\Models\User::find($ownerId);
            return $cache[$ownerId] = (bool) ($owner && $owner->isAdmin());
        } catch (\Throwable) {
            return $cache[$ownerId] = false;
        }
    }

    /**
     * Active add-on packages bought for THIS workspace (status active +
     * unexpired), as Package models. Request-cached — effectiveLimit() calls
     * this a lot. Empty + safe when the table is absent (pre-migration).
     */
    public function activeAddonPackages(): \Illuminate\Support\Collection
    {
        if (!$this->id) return collect();
        static $cache = [];
        if (array_key_exists($this->id, $cache)) return $cache[$this->id];
        try {
            $pkgs = \App\Models\WorkspaceAddon::query()
                ->active()
                ->where('workspace_id', $this->id)
                ->with('package')
                ->get()
                ->pluck('package')
                ->filter()
                ->values();
        } catch (\Throwable $e) {
            $pkgs = collect();
        }
        return $cache[$this->id] = $pkgs;
    }

    /**
     * Resolve the workspace's Package, tolerant of how `plan` was stored.
     *
     * The column is written two ways across the app: registration stores
     * the slug (`plan_id` e.g. 'starter'), while checkout + the admin
     * editor store the numeric package `id`. So we look up by slug first,
     * then fall back to a numeric-id lookup. Cached per request (keyed by
     * the current `plan`) since the plan gates call this many times.
     */
    /** Meta Business Agent coexistence — the responder modes a workspace can pick. */
    public const RESPONDER_MODES = ['wadesk_only', 'meta_agent_only', 'meta_agent_then_handoff'];

    /**
     * True when Meta's Business Agent is fronting this workspace's WhatsApp, so
     * OUR automated responders (AI agent + keyword auto-reply + flow AI) must
     * stand down — otherwise the customer gets TWO replies. Both meta-agent
     * modes suppress our auto-AI; the difference is only whether escalations
     * land in our Team Inbox (handoff). The inbound thread is captured either way.
     */
    public function suppressesOurAutoReply(): bool
    {
        return (bool) $this->meta_agent_enabled
            && in_array($this->ai_responder_mode, ['meta_agent_only', 'meta_agent_then_handoff'], true);
    }

    /**
     * The inbox_status a new inbound message should land in, given the Meta
     * coexistence mode. This is the ONLY runtime difference between the two
     * meta modes (Meta gives no escalation webhook, so auto-handoff can't be
     * detected — the honest, buildable distinction is queue placement):
     *   - meta_agent_only ("we only log the thread")     → 'closed'  (Meta owns
     *     it fully; keep the human queue clean).
     *   - meta_agent_then_handoff ("lands in your inbox") → 'open'   (surface it
     *     so a human can take over whenever Meta hands off).
     *   - everything else                                 → 'open'   (a new
     *     customer message is an ACTIVE conversation that needs handling; it
     *     shows under the Open queue with an OPEN pill. Previously this was
     *     'pending', which made EVERY inbound thread read "PENDING" and cluttered
     *     the inbox — 'open' matches the standard inbox convention. Both land in
     *     the same queue (scopeOpen = open+pending); only the pill label differs.)
     */
    public function inboundInboxStatus(): string
    {
        if (! $this->meta_agent_enabled) {
            return 'open';
        }
        return match ($this->ai_responder_mode) {
            'meta_agent_only'         => 'closed',
            'meta_agent_then_handoff' => 'open',
            default                   => 'open',
        };
    }

    public function package(): ?\App\Models\Package
    {
        // "Plan over" enforcement: a lapsed PAID plan downgrades to the free
        // package so paid features lock until renewal. Cached under a sentinel
        // key so the downgrade is consistent within the request.
        $expired = $this->planExpired();
        $cacheKey = $expired ? '__expired__' : $this->plan;
        if ($this->packageCacheKey === $cacheKey) {
            return $this->packageCache;
        }
        $this->packageCacheKey = $cacheKey;

        if ($expired) {
            return $this->packageCache = $this->freePackage();
        }

        $plan = $this->plan;
        if ($plan === null || $plan === '') {
            return $this->packageCache = null;
        }

        $pkg = \App\Models\Package::where('plan_id', (string) $plan)->first();
        if (!$pkg && is_numeric($plan)) {
            $pkg = \App\Models\Package::find((int) $plan);
        }
        return $this->packageCache = $pkg;
    }

    /**
     * Scope-aware package used for ENTITLEMENTS (limits, features, trial,
     * the hard gate). Admin chooses the scope at /admin/settings/general:
     *
     *   billing_plan_scope = 'workspace' (default)
     *       → just this workspace's own package(). Each workspace is
     *         billed separately (agency / Slack model). Zero behaviour
     *         change vs. before this feature.
     *
     *   billing_plan_scope = 'account'
     *       → the OWNER's best plan across every workspace they own. If
     *         the owner has bought a paid plan anywhere, all their
     *         workspaces inherit it (no second trial / gate). "Best" =
     *         the highest-priced non-free package; falls back to this
     *         workspace's own package when none is paid.
     *
     * Per-request cached. Never recurses (resolves siblings via the raw
     * package(), not billingPackage()).
     */
    public function billingPackage(): ?\App\Models\Package
    {
        $scope = (string) \App\Models\SystemSetting::get('billing_plan_scope', 'workspace');
        if ($scope !== 'account') {
            return $this->package();
        }

        $ownerId = (int) ($this->owner_user_id ?? 0);
        if ($this->billingPackageResolvedFor === $ownerId) {
            return $this->billingPackageCache;
        }
        $this->billingPackageResolvedFor = $ownerId;

        $own = $this->package();
        if (!$ownerId) {
            return $this->billingPackageCache = $own;
        }

        // Start from this workspace's own package, then let any paid plan
        // on a sibling workspace (same owner) win.
        $best       = $own;
        $bestAmount = ($own && !$own->isFreePlan()) ? (float) $own->plan_amount : -1.0;

        $siblings = static::query()
            ->where('owner_user_id', $ownerId)
            ->where('id', '!=', (int) ($this->id ?? 0))
            ->get(['id', 'plan']);

        foreach ($siblings as $sib) {
            $pkg = $sib->package();
            if ($pkg && !$pkg->isFreePlan()) {
                $amt = (float) $pkg->plan_amount;
                if ($amt > $bestAmount) {
                    $bestAmount = $amt;
                    $best       = $pkg;
                }
            }
        }

        return $this->billingPackageCache = $best;
    }

    /**
     * True when the workspace's (scope-aware) plan is a free plan, or no
     * plan resolves — treated as "free / no entitlements". Drives the
     * trial bar and the trial-expiry gate.
     */
    public function onFreePlan(): bool
    {
        $pkg = $this->billingPackage();
        return $pkg ? $pkg->isFreePlan() : true;
    }

    /** On a free plan AND a trial window was set (trial_ends_at present). */
    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->onFreePlan();
    }

    /**
     * The free trial has run out — the trial-expiry gate blocks feature
     * use until the workspace buys a (paid) plan. Paid plans and free
     * plans with no expiry (trial_ends_at = null) are never "expired".
     */
    public function trialExpired(): bool
    {
        return $this->onTrial() && $this->trial_ends_at->isPast();
    }

    /**
     * A PAID plan whose billing window has lapsed. plan_ends_at is set on
     * checkout (now + the package's billing cycle); null = no expiry
     * (free plans, enterprise/custom contracts, legacy rows) → never expired.
     * When true, package() downgrades to the free plan so paid features lock
     * until the workspace renews — this is the "plan over" enforcement.
     */
    public function planExpired(): bool
    {
        return $this->plan_ends_at !== null && $this->plan_ends_at->isPast();
    }

    /**
     * Is the workspace's plan currently ACTIVE — i.e. is its free monthly
     * message allowance still in force? Mirrors the existing expiry concepts
     * (planExpired / trialExpired) so billing agrees with the trial gate and
     * the package() downgrade:
     *
     *   - A PAID plan with a billing window (plan_ends_at) is active until
     *     that date passes (planExpired() === false).
     *   - A FREE plan inside its trial window is active until the trial runs
     *     out (trialExpired() === false). A free plan with NO trial window
     *     (trial_ends_at = null) never expires → always active.
     *   - Anything with no expiry set (legacy rows, enterprise/custom, free
     *     plans without a trial) is treated as active.
     *
     * When this returns false the free allowance no longer applies: every
     * send falls through to wallet credits (see OverflowBilling::consumeOne).
     */
    public function planIsActive(): bool
    {
        // Paid plan whose billing window has lapsed → not active.
        if ($this->planExpired()) {
            return false;
        }
        // Free plan whose trial window has lapsed → not active.
        if ($this->trialExpired()) {
            return false;
        }
        // Paid plan in window, free plan in trial, or no expiry at all → active.
        return true;
    }

    /** The free/starter package, used as the fall-back when a paid plan lapses. */
    public function freePackage(): ?\App\Models\Package
    {
        return \App\Models\Package::where('free', true)->orderBy('sort_order')->first()
            ?? \App\Models\Package::where('plan_id', 'starter')->first();
    }

    /**
     * 7-day weekly schedule used by the routing engine and by inbox UI to
     * label conversations arriving after-hours. `null` here means "always
     * open" — the engine treats absence as the legacy 24/7 behaviour.
     */
    public const BUSINESS_HOURS_DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public static function defaultBusinessHours(): array
    {
        $days = [];
        foreach (self::BUSINESS_HOURS_DAYS as $d) {
            $days[$d] = [
                'enabled' => !in_array($d, ['sat', 'sun'], true),
                'from'    => '09:00',
                'to'      => '18:00',
            ];
        }
        return [
            'days'                => $days,
            'outside_action'      => 'none',
            'outside_template_id' => null,
        ];
    }

    /**
     * Returns true if the workspace is currently outside the configured
     * business hours. Returns false when no config exists (legacy 24/7).
     */
    public function isOutsideBusinessHours(?\DateTimeInterface $at = null): bool
    {
        $cfg = $this->business_hours;
        if (!is_array($cfg) || empty($cfg['days'])) {
            return false; // no config = always open
        }
        $tz  = $this->timezone ?: config('app.timezone', 'UTC');
        $now = \Carbon\Carbon::instance($at ? \Carbon\Carbon::parse($at) : now())->setTimezone($tz);
        $key = strtolower($now->format('D'));            // Mon → mon
        $key = substr($key, 0, 3);

        $day = $cfg['days'][$key] ?? null;
        if (!$day || empty($day['enabled'])) return true;

        $hhmm = $now->format('H:i');
        return $hhmm < ($day['from'] ?? '00:00') || $hhmm >= ($day['to'] ?? '23:59');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role', 'invited_at', 'joined_at')
            ->withTimestamps();
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function tags()
    {
        return $this->hasMany(Tag::class);
    }

    public function routingRules()
    {
        return $this->hasMany(RoutingRule::class);
    }

    public function slaPolicies()
    {
        return $this->hasMany(SlaPolicy::class);
    }

    public function savedReplies()
    {
        return $this->hasMany(SavedReply::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function flags()
    {
        return $this->hasMany(WorkspaceFlag::class);
    }

    public function activeFlags()
    {
        return $this->flags()->whereNull('cleared_at');
    }

    public function isSuspended(): bool
    {
        return $this->activeFlags()->whereIn('flag', ['frozen', 'tos_violation', 'fraud'])->exists();
    }

    public function scopeForUser($q, ?int $userId)
    {
        return $q->whereHas('members', fn ($w) => $w->where('users.id', $userId));
    }

    public static function generateSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'workspace';
        $candidate = $slug;
        $i = 1;
        while (static::where('slug', $candidate)->exists()) {
            $candidate = $slug . '-' . (++$i);
        }
        return $candidate;
    }

    /**
     * Starter tags every new workspace gets.
     *
     * A fresh workspace used to begin with ZERO tags, which made the contacts
     * tag picker look broken: the suggestion datalist rendered empty, so an
     * operator saw nothing to choose and had no cue that typing a new name
     * creates one. Only workspace 1 (the dev one) had tags, which is why the
     * feature "worked here and not for clients".
     *
     * Kept deliberately short and generic — these are a starting point the
     * operator edits, not a taxonomy we impose.
     */
    public const STARTER_TAGS = [
        ['name' => 'New Lead',     'color' => '#2E7D32'],
        ['name' => 'Interested',   'color' => '#F9A825'],
        ['name' => 'Customer',     'color' => '#075E54'],
        ['name' => 'VIP',          'color' => '#6A1B9A'],
        ['name' => 'Follow Up',    'color' => '#1565C0'],
        ['name' => 'Not Interested','color' => '#9E9E9E'],
    ];

    protected static function booted(): void
    {
        // ONE hook instead of patching all five creation sites (registration,
        // admin create, user create, WorkspaceProvisioner, AdminWorkspaceProvisioner)
        // — a sixth path would otherwise silently miss the seed.
        static::created(function (self $workspace) {
            try {
                foreach (self::STARTER_TAGS as $t) {
                    Tag::firstOrCreate(
                        ['workspace_id' => $workspace->id, 'name' => $t['name']],
                        ['color' => $t['color'], 'slug' => Str::slug($t['name'])],
                    );
                }
            } catch (\Throwable $e) {
                // Seeding is a convenience — never let it block workspace
                // creation (which would break signup entirely).
                \Illuminate\Support\Facades\Log::warning(
                    '[WORKSPACE] starter tag seed failed: ' . $e->getMessage(),
                    ['workspace_id' => $workspace->id]
                );
            }
        });
    }
}
