<?php

namespace App\Models;

use App\Models\Concerns\HasEngineScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One auto-reply trigger. The bot calls
 *   GET /api/keyword-replies?keyword=…&phone=…
 * on every inbound and we resolve a row + its first selected content variant
 * to feed back. See KeywordReplyController::lookup().
 */
class KeywordReply extends Model
{
    use HasEngineScope, SoftDeletes;

    /**
     * Auto-stamp `provider` on create from the workspace's active engine.
     */
    protected static function booted(): void
    {
        static::creating(function (self $r) {
            if (empty($r->provider) && !empty($r->workspace_id)) {
                try {
                    $r->provider = \App\Services\WorkspaceEngine::for((int) $r->workspace_id);
                } catch (\Throwable $e) {}
            }
        });
    }

    protected $fillable = [
        'user_id', 'workspace_id', 'provider', 'device_id',
        'keyword', 'matching_method', 'is_catch_all', 'fuzzy_similarity',
        'reply_type', 'flow_id', 'target_contact_id', 'target_catalog_id',
        'cooldown', 'timeout',
        'message_type',
        // Marks rows auto-managed by a flow's keyword Trigger node (so the
        // flow-save sync can replace them without touching hand-made replies).
        'is_flow_trigger',
        'status', 'trigger_count', 'last_triggered_at',
        // Sprint 7 — multilingual.
        'keyword_translations', 'canonical_language',
        // Auto Responder / Welcome Message (2026-07-31). All default to the
        // classic keyword behaviour when empty — see the migration.
        'trigger_type', 'ig_trigger', 'priority', 'resend_unit', 'stop_on_agent_reply', 'resume_after',
        'resume_unit', 'working_hours', 'excluded_numbers', 'excluded_group_ids',
    ];

    protected $casts = [
        // keyword stays PLAIN — it's the SQL match key. fuzzy_similarity is a
        // 0–100 percent; cooldown/timeout are seconds.
        'fuzzy_similarity'     => 'integer',
        'cooldown'             => 'integer',
        'timeout'              => 'integer',
        'priority'             => 'integer',
        'status'               => 'boolean',
        'is_flow_trigger'      => 'boolean',
        // The DEFAULT ROUTE flag. True = this rule runs only when no
        // keyword rule matched. See scopeMatchKeyword / the two-pass
        // lookup in AutoReplyController.
        'is_catch_all'         => 'boolean',
        'trigger_count'        => 'integer',
        'last_triggered_at'    => 'datetime',
        // Sprint 7 — multilingual fan-out of the keyword across languages.
        // Shape: { "en": "hello", "hi": "नमस्ते", "ko": "안녕하세요", ... }
        'keyword_translations' => 'array',
        // Auto Responder / Welcome Message casts.
        'stop_on_agent_reply'  => 'boolean',
        'resume_after'         => 'integer',
        'working_hours'        => 'array',
        'excluded_numbers'     => 'array',
        'excluded_group_ids'   => 'array',
    ];

    public const MATCHING_METHODS = ['exact', 'fuzzy', 'contains', 'regex'];
    public const REPLY_TYPES      = ['custom', 'flow', 'share_contact', 'send_catalog', 'request_location'];
    public const MESSAGE_TYPES    = ['text', 'image', 'video', 'document', 'template'];
    // 'keyword' = classic keyword match (default). 'welcome' = fire once on the
    // first message of a new conversation, no keyword needed.
    // 'out_of_hours' fires on ANY inbound that arrives OUTSIDE the rule's
    // working_hours (no keyword). 'away' fires on ANY inbound while the workspace's
    // manual "Away mode" (workspaces.inbox_away) is ON. Both reuse the welcome
    // gates (excluded numbers, agent-pause, resend cooldown).
    public const TRIGGER_TYPES    = ['keyword', 'welcome', 'out_of_hours', 'away'];

    // Instagram-native triggers (provider='instagram'). WHICH Meta event fires
    // the rule. dm_keyword mirrors the WhatsApp keyword path; the rest are IG-
    // only. NULL on WhatsApp rows. keyword-bearing triggers still keyword-match.
    public const IG_TRIGGERS      = ['dm_keyword', 'comment_to_dm', 'story_reply', 'story_mention', 'mention'];
    // IG triggers that fire on ANY inbound of that kind (no keyword text match).
    public const IG_TRIGGERS_NO_KEYWORD = ['story_mention', 'mention'];

    /* ───────── Auto-responder scopes ───────── */

    /** Active welcome-message rules for a workspace + engine (first-message greeters). */
    public function scopeWelcomeFor(Builder $q, int $workspaceId, ?string $provider = null): Builder
    {
        $q->where('workspace_id', $workspaceId)
          ->where('status', true)
          ->where('trigger_type', 'welcome');
        if ($provider !== null) {
            $q->where(function ($w) use ($provider) {
                $w->where('provider', $provider)->orWhereNull('provider')->orWhere('provider', '');
            });
        }
        return $q->orderBy('priority')->orderBy('id');
    }

    /** Active OUT-OF-HOURS rules for a workspace + engine (fire outside working hours). */
    public function scopeOutOfHoursFor(Builder $q, int $workspaceId, ?string $provider = null): Builder
    {
        $q->where('workspace_id', $workspaceId)->where('status', true)->where('trigger_type', 'out_of_hours');
        if ($provider !== null) {
            $q->where(fn ($w) => $w->where('provider', $provider)->orWhereNull('provider')->orWhere('provider', ''));
        }
        return $q->orderBy('priority')->orderBy('id');
    }

    /** Active AWAY-mode rules for a workspace + engine (fire while workspace is away). */
    public function scopeAwayFor(Builder $q, int $workspaceId, ?string $provider = null): Builder
    {
        $q->where('workspace_id', $workspaceId)->where('status', true)->where('trigger_type', 'away');
        if ($provider !== null) {
            $q->where(fn ($w) => $w->where('provider', $provider)->orWhereNull('provider')->orWhere('provider', ''));
        }
        return $q->orderBy('priority')->orderBy('id');
    }

    /* ───────── relations ───────── */

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withDefault();
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class)->withDefault();
    }

    public function contents(): HasMany
    {
        return $this->hasMany(KeywordReplyContent::class)->orderBy('sort_order');
    }

    public function selectedContents(): HasMany
    {
        return $this->hasMany(KeywordReplyContent::class)
            ->where('is_selected', true)
            ->orderBy('sort_order');
    }

    /**
     * The "outside working hours" reply variant(s) for this rule (Step 3 of the
     * auto-responder wizard). Stored in the SAME contents table tagged
     * variant_role='outside_hours' + is_selected=false, so the normal reply path
     * (selectedContents, is_selected=true) never returns them — only the
     * evaluator's outside-hours branch reads this relation.
     */
    public function outsideHoursContents(): HasMany
    {
        return $this->hasMany(KeywordReplyContent::class)
            ->where('variant_role', 'outside_hours')
            ->orderBy('sort_order');
    }

    /* ───────── scopes ───────── */

    public function scopeForWorkspace(Builder $q, ?int $workspaceId): Builder
    {
        return $workspaceId ? $q->where('workspace_id', $workspaceId) : $q;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', true);
    }

    /**
     * Lookup-time SQL pre-filter. Narrows the candidate set the controller
     * iterates for fuzzy / contains matching — the actual matching logic
     * stays in PHP because `similar_text` and case-insensitive matching are
     * easier expressed there.
     *
     * Sprint 7 extension: also match against any translation stored in
     * `keyword_translations`. This means a customer typing "नमस्ते"
     * still matches the row whose canonical keyword is "hello", provided
     * we pre-translated it at save time.
     */
    public function scopeMatchKeyword(Builder $q, string $needle): Builder
    {
        $needle = mb_strtolower(trim($needle));

        // The `keyword` column stores ONE or MORE comma-separated
        // phrases (the chip-input UI joins them: "hi, hello, hey").
        // The match SQL has to handle every shape an operator can
        // produce:
        //   single word:   keyword = "hi"
        //   multi exact:   keyword = "hi, hello, hey"      method=exact
        //   multi contains: keyword = "pricing, plan"      method=contains
        //
        // We do a coarse SQL pre-filter (`LIKE '%hi%'`) so multi-keyword
        // rows surface as candidates, then the controller (and the
        // PHP-side fuzzy / token-split logic below) does the real
        // match. False-positives at this stage are fine — the
        // controller filters them out.
        // The DEFAULT ROUTE never competes in the keyword race. It is a
        // separate, lower tier resolved only after every keyword tier has
        // failed (see the two-pass lookup in AutoReplyController). Excluding
        // it here means EVERY caller of this scope gets that guarantee for
        // free, so a catch-all can never outrank a real keyword no matter
        // which row the database happens to return first.
        $q->where('is_catch_all', false);

        return $q->where(function ($w) use ($needle) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $w->whereRaw('LOWER(keyword) LIKE ?', [$like]);
            // Contains-mode also matches when the needle (inbound msg)
            // is the haystack and the stored keyword is the needle —
            // e.g. operator wrote "pricing", customer says "what is
            // your pricing plan" — that should fire too.
            $w->orWhere(function ($ww) use ($needle) {
                $ww->where('matching_method', 'contains')
                   ->whereRaw('LOWER(?) LIKE CONCAT("%", LOWER(keyword), "%")', [$needle]);
            });
            // Regex rows can't be pre-filtered by LIKE (the stored value
            // is a pattern, not literal text), so surface ALL regex rows
            // as candidates and let matchesNeedle() run the real
            // preg_match in the controller's final pass.
            $w->orWhere('matching_method', 'regex');
        });
    }

    /**
     * Final-pass exact / fuzzy / contains match against the stored
     * comma-separated keyword list. Called after `scopeMatchKeyword`
     * returns a candidate row — confirms whether the inbound message
     * actually triggers this rule given the row's `matching_method`.
     *
     * "hi, hello, hey" exact-match → splits on comma, returns true if
     *   any token equals the inbound needle.
     * "pricing" contains-match → returns true if the inbound contains
     *   "pricing" anywhere (case-insensitive).
     * Fuzzy uses similar_text(); threshold = fuzzy_similarity (0-100).
     */
    public function matchesNeedle(string $needle): bool
    {
        $method = $this->matching_method ?? 'exact';

        // Regex matches against the RAW inbound message (no lowercasing,
        // no comma-splitting — a pattern can legitimately contain commas
        // like {2,4}). The whole keyword field is treated as one pattern.
        // Invalid patterns never throw — preg_match returns false, which
        // we coerce to "no match" so a bad rule can't break the bot.
        if ($method === 'regex') {
            return self::regexMatches((string) $this->keyword, $needle);
        }

        $needle = mb_strtolower(trim($needle));
        if ($needle === '') return false;
        $tokens = array_values(array_filter(array_map(
            fn ($t) => mb_strtolower(trim($t)),
            explode(',', (string) $this->keyword)
        )));
        if (empty($tokens)) return false;
        if ($method === 'exact') {
            return in_array($needle, $tokens, true);
        }
        if ($method === 'contains') {
            foreach ($tokens as $t) {
                if ($t === '') continue;
                // WHOLE-WORD match — the keyword must appear as its own word in
                // the message, NOT as a random substring. A raw substring match
                // (the old mb_stripos) made a 2-letter keyword like "hi" fire on
                // "this" / "shipping" / "which" / "Delhi", so a flow or auto-reply
                // restarted on almost any inbound from any number. The Unicode
                // lookarounds treat letters+digits as word chars (so "hi" still
                // matches "hi there" but not "this"); preg_quote makes any keyword
                // safe, and a malformed pattern falls back to substring.
                $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($t, '/') . '(?![\p{L}\p{N}])/u';
                $hit = @preg_match($pattern, $needle);
                if ($hit === 1) return true;
                if ($hit === false && mb_stripos($needle, $t) !== false) return true; // regex failed → old behavior
            }
            return false;
        }
        if ($method === 'fuzzy') {
            $threshold = max(0, min(100, (int) ($this->fuzzy_similarity ?? 80)));
            foreach ($tokens as $t) {
                if ($t === '') continue;
                similar_text($needle, $t, $pct);
                if ($pct >= $threshold) return true;
            }
            return false;
        }
        return false;
    }

    /**
     * Safe regex match for `matching_method = 'regex'`. The operator's
     * pattern is run case-insensitively (i) and in unicode mode (u)
     * against the raw inbound message. We wrap the pattern in `~…~`
     * delimiters (escaping any literal `~` first) so the operator does
     * NOT have to type delimiters, and we suppress + swallow warnings so
     * an invalid pattern can never throw or leak into the response — a
     * broken rule simply never fires.
     */
    public static function regexMatches(string $pattern, string $subject): bool
    {
        $pattern = trim($pattern);
        if ($pattern === '') return false;
        $delimited = '~' . str_replace('~', '\~', $pattern) . '~iu';
        $result = @preg_match($delimited, $subject);
        if ($result === false) {
            // Invalid pattern (or PCRE error). Retry once without the
            // unicode flag in case the subject isn't valid UTF-8, then
            // give up quietly.
            $result = @preg_match('~' . str_replace('~', '\~', $pattern) . '~i', $subject);
        }
        return $result === 1;
    }

    /** True if the given pattern compiles as a valid PCRE regex. */
    public static function isValidRegex(string $pattern): bool
    {
        $pattern = trim($pattern);
        if ($pattern === '') return false;
        return @preg_match('~' . str_replace('~', '\~', $pattern) . '~iu', '') !== false
            || @preg_match('~' . str_replace('~', '\~', $pattern) . '~i', '') !== false;
    }

    /**
     * Iterate the multilingual translations map on a hydrated row and
     * decide whether ANY language form matches the inbound message.
     *
     * Used by the controller as a SECOND pass after `matchKeyword()`
     * misses — JSON_SEARCH proved unreliable across MySQL collations
     * (case folding + Unicode escapes), so we fall back to PHP. The
     * candidate set per device is small (tens of rows), so the cost
     * is negligible and the behaviour is predictable.
     *
     * Returns the matching language code (e.g. "ko"), or null.
     */
    public function matchesTranslation(string $message): ?string
    {
        $map = $this->keyword_translations ?? [];
        if (!is_array($map) || empty($map)) return null;

        $needle = mb_strtolower(trim($message));
        $contains = $this->matching_method === 'contains';

        foreach ($map as $lang => $value) {
            $v = mb_strtolower(trim((string) $value));
            if ($v === '') continue;
            if ($v === $needle) return (string) $lang;
            if ($contains && mb_stripos($needle, $v) !== false) return (string) $lang;
        }
        return null;
    }
}
