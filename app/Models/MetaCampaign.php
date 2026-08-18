<?php

namespace App\Models;

use App\Models\Concerns\LogsNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Meta-Ads campaign. PII columns (name, creative copy, CTWA
 * phone/message, link URL, targeting) are encrypted-at-rest so
 * the database file is unreadable on its own. The Contact model
 * uses the same pattern.
 *
 * Filtering/search needs to hydrate-then-filter for any encrypted
 * column — see `searchByName()` for the pattern. Plain-text
 * categorical columns (status, optimization_goal, objective)
 * stay queryable through scopes.
 */
class MetaCampaign extends Model
{
    use HasFactory, LogsNotifications;

    protected $table = 'meta_campaigns';

    protected $fillable = [
        'user_id',
        'workspace_id',
        'facebook_id',
        // Full CTWA tree — each Meta entity has its own id so we can
        // toggle/edit/delete the whole hierarchy. See migration
        // 2026_05_24_070000_meta_ads_full_ctwa_flow.
        'meta_adset_id',
        'meta_creative_id',
        'meta_ad_id',
        'meta_image_hash',
        'meta_last_error',
        'meta_synced_at',
        'name',
        'objective',
        'optimization_goal',
        'status',
        'type',
        // Instagram Ads (on top of CTWA). ad_type drives the destination/
        // creative shape; publisher_platforms + instagram_positions drive
        // placement; instagram_user_id is the IG identity on the creative.
        'ad_type',
        'publisher_platforms',
        'instagram_positions',
        'instagram_user_id',
        'daily_budget',
        'lifetime_budget',
        // Advanced: Campaign Budget Optimization + bidding + ad categories.
        'budget_level',
        'bid_strategy',
        'bid_amount',
        'special_ad_categories',
        'creative_title',
        'creative_body',
        'creative_link_url',
        'creative_image',
        'ctwa_enabled',
        'ctwa_phone',
        'ctwa_message',
        'ctwa_cta',
        'targeting',
        'insights',
        'ad_set_count',
        'ad_count',
    ];

    protected $casts = [
        // Encrypted-at-rest. The DB stores ciphertext; Eloquent
        // decrypts on attribute access. Don't try to LIKE/ORDER
        // BY these columns at the SQL layer — see the helper
        // methods (searchByName, sortByName) that work in PHP
        // after hydration.
        'name'              => 'encrypted',
        'creative_title'    => 'encrypted',
        'creative_body'     => 'encrypted',
        'creative_link_url' => 'encrypted',
        'ctwa_phone'        => 'encrypted',
        'ctwa_message'      => 'encrypted',
        'targeting'         => 'encrypted:array',

        'insights'          => 'array',
        'publisher_platforms' => 'array',
        'instagram_positions' => 'array',
        'ctwa_enabled'      => 'boolean',
        'daily_budget'      => 'decimal:2',
        'lifetime_budget'   => 'decimal:2',
        'bid_amount'            => 'integer',
        'special_ad_categories' => 'array',
        'ad_set_count'      => 'integer',
        'ad_count'          => 'integer',
        'meta_synced_at'    => 'datetime',
        'meta_last_error'   => 'encrypted',
    ];

    public const STATUSES = ['ACTIVE', 'PAUSED', 'SCHEDULED', 'DRAFT', 'FAILED'];

    /**
     * ad_type — how the ad is built:
     *   ctwa      Click-to-WhatsApp (default + legacy behaviour)
     *   link      standard link ad (traffic/sales/awareness) to a website,
     *             used for plain Instagram feed/story/reel ads
     *   ig_direct Click-to-Instagram-Direct (opens an IG DM thread)
     */
    public const AD_TYPE_CTWA      = 'ctwa';
    public const AD_TYPE_LINK      = 'link';
    public const AD_TYPE_IG_DIRECT = 'ig_direct';
    public const AD_TYPES = [self::AD_TYPE_CTWA, self::AD_TYPE_LINK, self::AD_TYPE_IG_DIRECT];

    /** Normalised ad type — defaults to CTWA for legacy rows. */
    public function adType(): string
    {
        $t = strtolower((string) ($this->ad_type ?: self::AD_TYPE_CTWA));
        return in_array($t, self::AD_TYPES, true) ? $t : self::AD_TYPE_CTWA;
    }

    /** WhatsApp destination ad (uses promoted_object.whatsapp_phone_number). */
    public function isCtwa(): bool
    {
        return $this->adType() === self::AD_TYPE_CTWA;
    }

    /** A messaging ad (CTWA or Instagram-Direct) — uses page_welcome_message. */
    public function isMessagingAd(): bool
    {
        return in_array($this->adType(), [self::AD_TYPE_CTWA, self::AD_TYPE_IG_DIRECT], true);
    }

    /** True when the ad needs an Instagram identity (IG-Direct, or any ad
     *  whose placement explicitly includes Instagram). */
    public function wantsInstagram(): bool
    {
        if ($this->adType() === self::AD_TYPE_IG_DIRECT) return true;
        $p = (array) ($this->publisher_platforms ?? []);
        return in_array('instagram', array_map('strtolower', $p), true);
    }

    public const OPTIMIZATION_GOALS = [
        'MESSAGES', 'LINK_CLICKS', 'CONVERSIONS', 'LEAD_GENERATION',
        'REACH', 'BRAND_AWARENESS', 'VIDEO_VIEWS',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    public function scopeForUser(Builder $q, ?int $userId): Builder
    {
        return $userId ? $q->where('user_id', $userId) : $q;
    }

    /** Workspace-shared visibility — every member sees every campaign. */
    public function scopeForCurrentWorkspace(Builder $q): Builder
    {
        $user = auth()->user();
        if (!$user) return $q->whereRaw('1=0');
        $uId  = (int) $user->id;
        $wsId = (int) ($user->current_workspace_id ?? 0);
        return $q->where(function ($qq) use ($wsId, $uId) {
            $qq->where('workspace_id', $wsId)
               ->orWhere(function ($qqq) use ($uId) {
                   $qqq->whereNull('workspace_id')->where('user_id', $uId);
               });
        });
    }

    public function scopeWithStatus(Builder $q, ?string $status): Builder
    {
        if (!$status || $status === 'all') return $q;
        return $q->where('status', strtoupper($status));
    }

    public function scopeWithObjective(Builder $q, ?string $goal): Builder
    {
        if (!$goal || $goal === 'all') return $q;
        return $q->where('optimization_goal', $goal);
    }

    public function scopeInRange(Builder $q, ?string $range): Builder
    {
        return match ($range) {
            '7d'  => $q->where('created_at', '>=', now()->subDays(7)),
            '30d' => $q->where('created_at', '>=', now()->subDays(30)),
            '90d' => $q->where('created_at', '>=', now()->subDays(90)),
            default => $q,
        };
    }

    /**
     * Filter by encrypted name in PHP — DB-side LIKE on
     * ciphertext matches nothing.
     */
    public static function filterByName($items, ?string $term)
    {
        $term = mb_strtolower(trim((string) $term));
        if ($term === '') return $items;
        return $items->filter(fn ($c) => str_contains(mb_strtolower((string) $c->name), $term))->values();
    }

    /**
     * Convenience accessor for the metrics displayed on the card.
     * Falls back to zeros when the campaign hasn't been synced yet.
     */
    public function getMetricsAttribute(): array
    {
        $i = $this->insights ?? [];
        return [
            'spend'       => (float) ($i['spend']       ?? 0),
            'impressions' => (int)   ($i['impressions'] ?? 0),
            'clicks'      => (int)   ($i['clicks']      ?? 0),
            'reach'       => (int)   ($i['reach']       ?? 0),
            'conversions' => (int)   ($i['conversions'] ?? 0),
            'ctr'         => (float) ($i['ctr']         ?? 0),
            'cpc'         => (float) ($i['cpc']         ?? 0),
            'revenue'     => (float) ($i['revenue']     ?? 0),
        ];
    }
}
