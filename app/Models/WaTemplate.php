<?php

namespace App\Models;

use App\Models\Concerns\LogsNotifications;
use App\Services\WorkspaceEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WhatsApp Business template — the Meta-approved kind that gets
 * sent through `WhatsAppDispatcher` and embedded in broadcasts.
 *
 * Distinct from `App\Models\ChatTemplate` (chat snippets the
 * operator picks from the chat-page picker) — that one is local
 * and not Meta-approved. Both can coexist.
 */
class WaTemplate extends Model
{
    use HasFactory, LogsNotifications;

    protected $table = 'wa_templates';

    protected $fillable = [
        'user_id',
        'workspace_id',
        'provider_config_id',
        'meta_template_id',
        'twilio_content_sid',
        'channel',
        'meta_status',
        'quality_score',
        'rejection_reason_code',
        'template_name',
        'category',
        'meta_category',
        'template_type',
        'header',
        'header_location',
        'template_body',
        'footer',
        // Authentication (OTP) templates: how long the code is valid, 1–90 min.
        // Meta shows it to the user + uses it for one-tap autofill. NULL → 10.
        'code_expiration_minutes',
        'buttons',
        'carousel_data',
        'variable_map',
        'attachment_type',
        'attachment_file',
        'header_sample_url',
        'language',
        'parameter_format',
        'status',
        'rejection_reason',
        'approved_at',
        'submitted_at',
        'last_synced_at',
        'paused_until',
    ];

    protected $casts = [
        'template_name'    => 'encrypted',
        'header'           => 'encrypted',
        'header_location'  => 'encrypted:array',
        'template_body'    => 'encrypted',
        'footer'           => 'encrypted',
        'buttons'          => 'encrypted:array',
        'carousel_data'    => 'encrypted:array',
        'variable_map'     => 'encrypted:array',
        'rejection_reason' => 'encrypted',
        'approved_at'      => 'datetime',
        'submitted_at'     => 'datetime',
        'last_synced_at'   => 'datetime',
        'paused_until'     => 'datetime',
    ];

    public const STATUSES   = ['pending', 'approved', 'rejected', 'public'];
    public const CATEGORIES = ['travel', 'healthcare', 'education', 'ecommerce', 'festival', 'finance', 'utility'];
    public const TYPES      = ['standard', 'carousel', 'media', 'auth'];

    /** Meta-side status machine — distinct from the local UI `status`. */
    public const META_STATUSES = [
        'PENDING', 'APPROVED', 'REJECTED', 'IN_APPEAL', 'PENDING_DELETION',
        'DELETED', 'DISABLED', 'PAUSED', 'LIMIT_EXCEEDED', 'FLAGGED',
    ];
    public const META_QUALITY  = ['UNKNOWN', 'GREEN', 'YELLOW', 'RED'];
    public const PARAM_FORMATS = ['POSITIONAL', 'NAMED'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(WaProviderConfig::class, 'provider_config_id');
    }

    /**
     * The template's shape, for the send-time mapping panel.
     *
     * Everything the UI needs to render one input per editable slot is
     * derived here, server-side, so all four send surfaces (campaigns,
     * broadcasts, scheduled, chat) render an identical panel from one
     * source of truth instead of each re-deriving placeholder counts.
     *
     * `editable` is the honest answer to "can the operator change this
     * at send time" — false means the panel renders a reason instead of
     * inputs, rather than accepting edits we'd silently drop.
     *
     * @see \App\Services\TemplateOverrideResolver
     * @see resources/js/template-live-mapping.js
     */
    public function sendMeta(): array
    {
        $engine = $this->engineKey();
        $format = strtoupper((string) ($this->attachment_type ?: 'TEXT'));
        if ($format === 'NONE' || $format === '') $format = 'TEXT';

        // Why the panel might be read-only. Order matters — the first
        // true reason is the one shown.
        $lockedReason = null;
        if ($this->template_type === 'auth') {
            $lockedReason = 'Authentication templates send a freshly generated OTP per recipient. A fixed value would be the same code for everyone, so these can\'t be edited at send time.';
        } elseif ($engine === 'twilio') {
            $lockedReason = 'Twilio sends this template by Content SID — the header media and button links are fixed on Twilio\'s side. Variable values can still be edited below.';
        }

        $headerText  = (string) ($this->header ?? '');
        $bodyText    = (string) ($this->template_body ?? '');
        $footerText  = (string) ($this->footer ?? '');
        $vmap        = is_array($this->variable_map) ? $this->variable_map : [];

        return [
            'id'            => $this->id,
            'name'          => (string) ($this->template_name ?? ''),
            'engine'        => $engine,
            'template_type' => (string) ($this->template_type ?? 'standard'),
            'editable'      => $this->template_type !== 'auth',
            // Twilio can still take body variables, just not media/buttons.
            'media_editable'  => $engine !== 'twilio' && in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true),
            'buttons_editable' => $engine !== 'twilio',
            'locked_reason'   => $lockedReason,
            'header' => [
                'format'    => $format,
                'text'      => $headerText,
                'slots'     => $format === 'TEXT' ? $this->countSlots($headerText) : 0,
                'tokens'    => $format === 'TEXT' ? $this->slotTokens($headerText, $vmap['header'] ?? []) : [],
                'media_url' => $this->attachment_file ? (string) media_url($this->attachment_file) : '',
                'defaults'  => $this->slotDefaults($vmap['header'] ?? []),
            ],
            'body' => [
                'text'     => $bodyText,
                'slots'    => $this->countSlots($bodyText),
                'tokens'   => $this->slotTokens($bodyText, $vmap['body'] ?? []),
                'defaults' => $this->slotDefaults($vmap['body'] ?? []),
            ],
            'footer' => [
                'text'   => $footerText,
                'slots'  => $this->countSlots($footerText),
                'tokens' => $this->slotTokens($footerText),
            ],
            'buttons' => $this->buttonMeta(),
        ];
    }

    /**
     * How many placeholders a section carries.
     *
     * Uses the shared TOKEN_RE so spaced/capitalised names like
     * `{{Phone Number}}` count as real variables. They previously did not,
     * which meant the operator was never shown an input for them and the
     * raw braces went out to the customer.
     */
    private function countSlots(string $text): int
    {
        if ($text === '') return 0;
        preg_match_all(\App\Services\TemplateOverrideResolver::TOKEN_RE, $text, $m);
        return count($m[0]);
    }

    /**
     * Per-slot descriptors for the mapping panel.
     *
     * The UI labels each input with the placeholder's OWN name ("Phone
     * Number") instead of a meaningless "Variable 1", and states which
     * attribute it will auto-fill from when left blank — so the operator
     * can see what happens by default rather than guessing.
     *
     * @return array<int, array{name:string,key:string}>
     */
    private function slotTokens(string $text, $sectionMap = []): array
    {
        if ($text === '') return [];
        preg_match_all(\App\Services\TemplateOverrideResolver::TOKEN_RE, $text, $m);

        // Positional templates ({{1}}, {{2}}) carry their meaning in the
        // variable_map, not in the placeholder. Labelling those inputs "1"
        // and "2" would be as useless as the "Variable 1" it replaced — so
        // resolve the mapped attribute and label with THAT.
        $mapByNum = [];
        foreach ((array) $sectionMap as $i => $entry) {
            $key = is_array($entry) ? (string) ($entry['key'] ?? '') : (string) $entry;
            $num = is_array($entry) && isset($entry['num']) ? (int) $entry['num'] : ((int) $i + 1);
            if ($key !== '') $mapByNum[$num] = $key;
        }

        $out = [];
        foreach ($m[1] as $idx => $raw) {
            $raw  = trim((string) $raw);
            $key  = \App\Services\TemplateOverrideResolver::normalizeKey($raw);
            $name = $raw;

            if (ctype_digit($raw)) {
                $mapped = $mapByNum[(int) $raw] ?? ($mapByNum[$idx + 1] ?? '');
                if ($mapped !== '') {
                    $key  = \App\Services\TemplateOverrideResolver::normalizeKey($mapped);
                    $name = ucfirst(str_replace('_', ' ', $key));
                } else {
                    $name = 'Value ' . $raw;   // honest: nothing is mapped to it
                }
            }

            $out[] = ['name' => $name, 'key' => $key, 'raw' => $raw];
        }
        return $out;
    }

    /**
     * Normalise a stored button `type` to the send-time sub_type Meta wants.
     * Returns NULL for buttons that take no send parameter (PHONE_NUMBER) —
     * sending one is rejected with #132000.
     *
     * Templates in the wild carry several aliases for the same button
     * (`call` / `call_phone` / `phone_number`, `copy_text` / `copy_code`)
     * depending on whether the row was hand-built, imported from Meta, or
     * created by an older release. Every consumer must agree, so this is
     * the single place that decides.
     */
    public static function buttonSubType(string $type): ?string
    {
        return match (strtolower(trim($type))) {
            'visit_website', 'url', 'cta_url'                 => 'url',
            'copy_code', 'copy_text', 'coupon_code'           => 'copy_code',
            'call_phone', 'call', 'phone', 'phone_number',
            'cta_call', 'voice_call'                          => null,
            default                                           => 'quick_reply',
        };
    }

    /**
     * Which attribute the template's variable_map already assigns to each
     * slot. The panel pre-fills its inputs with these as {{token}}s, so
     * the operator SEES the current behaviour and edits from there rather
     * than facing blank boxes and guessing.
     */
    private function slotDefaults($section): array
    {
        if (! is_array($section)) return [];
        $out = [];
        foreach (array_values($section) as $i => $entry) {
            $key = is_array($entry) ? (string) ($entry['key'] ?? '') : (string) $entry;
            $out[$i] = $key !== '' ? '{{' . $key . '}}' : '';
        }
        return $out;
    }

    /**
     * Buttons the operator can give a send-time value to. PHONE_NUMBER
     * buttons take no send parameter (Meta #132000 if you send one), so
     * they're reported but flagged non-editable.
     */
    private function buttonMeta(): array
    {
        $out = [];
        foreach ((array) ($this->buttons ?? []) as $idx => $b) {
            if (! is_array($b)) continue;
            $type  = (string) ($b['type'] ?? '');
            $sub   = self::buttonSubType($type);
            $value = (string) ($b['value'] ?? '');
            $out[] = [
                'index'    => (int) $idx,
                'sub_type' => $sub ?? 'phone',
                'label'    => (string) ($b['text'] ?? $b['label'] ?? ('Button ' . ($idx + 1))),
                'value'    => $value,
                // ONLY url + copy_code carry a send-time value. A quick reply's
                // payload IS its own label and a PHONE_NUMBER button takes no
                // parameter at all — offering an input for those asks the
                // operator to type something that can never be sent.
                'editable' => in_array($sub, ['url', 'copy_code'], true),
                // A URL with no placeholder is a fixed link on Meta's side;
                // we surface it read-only so the operator isn't misled into
                // thinking a typed value would take effect.
                'has_slot' => $sub !== null && str_contains($value, '{{'),
            ];
        }
        return $out;
    }

    /**
     * Which engine this template targets: 'baileys' (Unofficial API),
     * 'waba' (Meta Cloud), or 'twilio'. Prefers the stored `channel`;
     * falls back to deriving from the Meta/Twilio fields for rows that
     * pre-date the column.
     */
    public function engineKey(): string
    {
        if (in_array($this->channel, ['baileys', 'waba', 'twilio', 'instagram'], true)) {
            return $this->channel;
        }
        if (!empty($this->twilio_content_sid)) return 'twilio';
        if ($this->meta_template_id || $this->provider_config_id) return 'waba';
        return 'baileys';
    }

    /** Human label for the template's engine (user-facing, never "Baileys"). */
    public function engineLabel(): string
    {
        return [
            'baileys'   => 'Unofficial API',
            'waba'      => 'Meta (WABA)',
            'twilio'    => 'Twilio',
            'instagram' => 'Instagram',
        ][$this->engineKey()] ?? 'Unofficial API';
    }

    /**
     * True if the row is sendable as a Meta-Cloud template. Used by
     * the dispatcher's quality gate — refuses to dispatch unless the
     * template is APPROVED on Meta's side AND not paused.
     */
    public function isMetaApproved(): bool
    {
        if ($this->meta_status !== 'APPROVED') return false;
        if ($this->paused_until && $this->paused_until->isFuture()) return false;
        return true;
    }

    /** Quality-gate floor: RED quality blocks all sends. */
    public function isQualityHealthy(): bool
    {
        return ! in_array($this->quality_score, ['RED'], true);
    }

    public function scopeWithMetaStatus(Builder $q, ?string $status): Builder
    {
        return $status ? $q->where('meta_status', strtoupper($status)) : $q;
    }

    /** Templates that need a Meta GET sweep because the webhook may have missed them. */
    public function scopeStaleSweepTargets(Builder $q): Builder
    {
        return $q->where('meta_status', 'PENDING')
                 ->whereNotNull('meta_template_id')
                 ->where('submitted_at', '<', now()->subHour())
                 ->where(function ($qq) {
                     $qq->whereNull('last_synced_at')
                        ->orWhere('last_synced_at', '<', now()->subMinutes(30));
                 });
    }

    public function scopeForUser(Builder $q, ?int $userId): Builder
    {
        return $userId ? $q->where('user_id', $userId) : $q;
    }

    /**
     * Workspace-shared visibility. ALSO includes admin-seeded global
     * templates (workspace_id IS NULL AND user_id IS NULL) so every
     * workspace can use those without duplicating rows.
     */
    public function scopeForCurrentWorkspace(Builder $q): Builder
    {
        $user = auth()->user();
        if (!$user) return $q->whereRaw('1=0');
        $uId  = (int) $user->id;
        $wsId = (int) ($user->current_workspace_id ?? 0);
        return $q->where(function ($qq) use ($wsId, $uId) {
            // Templates owned by this workspace
            $qq->where('workspace_id', $wsId)
            // Admin-seeded globals — visible to every workspace
               ->orWhere(function ($qqq) {
                   $qqq->whereNull('workspace_id')->whereNull('user_id');
               })
            // Legacy: pre-migration user-owned rows (still NULL workspace_id)
               ->orWhere(function ($qqq) use ($uId) {
                   $qqq->whereNull('workspace_id')->where('user_id', $uId);
               });
        });
    }

    public function scopeWithStatus(Builder $q, ?string $status): Builder
    {
        if (!$status || $status === 'all') return $q;
        return $q->where('status', $status);
    }

    public function scopeOfCategory(Builder $q, ?string $category): Builder
    {
        if (!$category || $category === 'all') return $q;
        return $q->where('category', $category);
    }

    /**
     * "Sendable" templates for the current workspace's engine.
     *
     * WABA workspaces need Meta's verdict — only `meta_status='APPROVED'`
     * counts, and PAUSED/DISABLED/PENDING_DELETION must be excluded
     * because Meta will refuse them at send time. The local `status`
     * column is synthetic on this engine (Baileys flow marks every
     * template 'approved' locally) so it must not be the filter.
     *
     * Baileys/Twilio workspaces have no Meta gate — `status` IN
     * ('approved','public') is the operator-controlled signal.
     *
     * One scope, eleven callers (BroadcastsController, ScheduledController,
     * ChatController, TeamInboxController, TemplatesController, …) all
     * automatically get the right answer for their workspace's engine.
     */
    /**
     * The category to group, filter and label this template by.
     *
     * `meta_category` is only ever populated for templates that came from —
     * or were submitted to — Meta. A locally-created template (every
     * Unofficial-API workspace) stores its category in `category` and leaves
     * `meta_category` NULL. Filtering on `meta_category` alone therefore
     * matches NOTHING for those workspaces, which is how the /chat template
     * picker ended up empty and its category counts stuck at zero while the
     * workspace had ten perfectly good templates.
     *
     * The `category` fallback is deliberately WHITELISTED rather than taken
     * as-is. That column is overloaded: one writer stores a WhatsApp category
     * (marketing|utility|authentication), another stores a business vertical
     * (travel|healthcare|ecommerce|festival|…) used only for AI generation.
     * Trusting it blindly is what once collapsed every library tab into
     * "Utility". A vertical therefore resolves to '' (uncategorised) instead
     * of being filed under a WhatsApp category it never claimed to be.
     *
     * Returns lower-case, or '' when neither column yields a real category.
     */
    public function effectiveCategory(): string
    {
        $meta = mb_strtolower(trim((string) $this->meta_category));
        if ($meta !== '') return $meta;

        $local = mb_strtolower(trim((string) $this->category));
        return in_array($local, ['marketing', 'utility', 'authentication'], true) ? $local : '';
    }

    public function scopeApproved(Builder $q): Builder
    {
        $user = auth()->user();
        $wsId = (int) ($user?->current_workspace_id ?? 0);

        if ($wsId && WorkspaceEngine::isWaba($wsId)) {
            // `meta_template_id` is the ONLY trustworthy signal that
            // this template was actually submitted to and approved by
            // Meta. Migration 2026_05_24_040000 bulk-set
            // meta_status='APPROVED' on every locally-approved row to
            // keep legacy sends working, so filtering only on
            // meta_status would still show Baileys synthetic approvals.
            // Requiring meta_template_id rules those ghosts out.
            $q->whereNotNull('meta_template_id')
              ->where('meta_status', 'APPROVED')
              ->where(function ($qq) {
                  $qq->whereNull('paused_until')
                     ->orWhere('paused_until', '<=', now());
              });
        } else {
            $q->whereIn('status', ['approved', 'public']);
        }
        // A template lives on a specific WABA number. If that number was
        // DISCONNECTED (config kept, status≠connected) or REMOVED (config row
        // deleted), the template can't be sent — so it must disappear from every
        // send picker. providerLive() enforces that. provider_config_id NULL =
        // non-WABA template (Baileys/legacy) → always kept.
        return $q->providerLive();
    }

    /**
     * Only templates whose WABA number is still connected — or that aren't
     * tied to a WABA number at all. Hides templates whose provider config was
     * disconnected or deleted, everywhere this scope is applied.
     */
    public function scopeProviderLive(Builder $q): Builder
    {
        return $q->where(function ($w) {
            $w->whereNull('provider_config_id')
              ->orWhereHas('provider', function ($c) {
                  $c->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED);
              });
        });
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    public function scopeRejected(Builder $q): Builder
    {
        return $q->where('status', 'rejected');
    }

    /**
     * Search template_name in PHP after hydration — `template_name`
     * is encrypted, so LIKE on ciphertext returns nothing. Caller
     * passes the already-fetched collection.
     */
    public static function filterByName($items, ?string $term)
    {
        $term = mb_strtolower(trim((string) $term));
        if ($term === '') return $items;
        return $items->filter(fn ($t) => str_contains(mb_strtolower((string) $t->template_name), $term))->values();
    }

    /**
     * Read a template's stored attachment ONCE and return it base64-inlined
     * so the Node bulk senders never have to download media from a URL per
     * recipient (the old path silently dropped the image whenever Node
     * couldn't reach `APP_DOMAIN_NAME/storage/...` — localhost, no
     * storage:link, private bucket, auth wall, etc.).
     *
     * Files are stored via `$file->store('wa-templates', 'public')`, so the
     * stored value is a public-disk-relative path like `wa-templates/abc.jpg`
     * and the real disk path is `storage/app/public/wa-templates/abc.jpg`.
     *
     * Cheap + safe even at 10k recipients: templateData is built ONCE per bulk
     * job, not per recipient. Never throws — on a missing/unreadable file it
     * returns nulls (callers keep `attachment_url` as the network fallback)
     * and logs so the operator can see why media was inlined-null.
     *
     * @return array{attachment_base64: ?string, attachment_mime: ?string}
     */
    public static function inlineAttachment(?string $attachmentFile): array
    {
        $out = ['attachment_base64' => null, 'attachment_mime' => null];
        if (empty($attachmentFile)) {
            return $out;
        }

        try {
            $disk = media_storage();
            $rel  = ltrim($attachmentFile, '/');

            if (!$disk->exists($rel)) {
                \Illuminate\Support\Facades\Log::warning(
                    '[TEMPLATE-MEDIA] attachment file missing on disk — Node will fall back to URL download',
                    ['attachment_file' => $attachmentFile]
                );
                return $out;
            }

            $bytes = $disk->get($rel);
            if ($bytes === null || $bytes === '') {
                \Illuminate\Support\Facades\Log::warning(
                    '[TEMPLATE-MEDIA] attachment file empty — Node will fall back to URL download',
                    ['attachment_file' => $attachmentFile]
                );
                return $out;
            }

            $out['attachment_base64'] = base64_encode($bytes);
            try {
                $out['attachment_mime'] = $disk->mimeType($rel) ?: null;
            } catch (\Throwable $e) {
                $out['attachment_mime'] = null;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[TEMPLATE-MEDIA] inlineAttachment failed', [
                'attachment_file' => $attachmentFile,
                'error'           => $e->getMessage(),
            ]);
            return ['attachment_base64' => null, 'attachment_mime' => null];
        }

        return $out;
    }

    /**
     * Pretty status label used in card pills + filter tabs.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'Approved',
            'public'   => 'Public',
            'pending'  => 'In review',
            'rejected' => 'Rejected',
            default    => ucfirst((string) $this->status),
        };
    }
}
