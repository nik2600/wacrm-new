<?php

namespace App\Models;

use App\Models\Concerns\LogsNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ported from D:\wadesk_2806\New folder\app\Models\Contact.php.
 * Stripped: belongsToMany(Broadcast) — broadcasts model not yet ported.
 */
class Contact extends Model
{
    use HasFactory, LogsNotifications;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'name',
        'language',
        'address',
        'contact_group',
        'email',
        'country_code',
        'mobile',
        'mobile_hash',
        'msg',
        'subject',
        'image',
        'is_unsubscribed',
        'unsubscribed_at',
        'custom_attributes',
    ];

        protected $casts = [
        'name'              => 'encrypted',
        'mobile'            => 'encrypted',
        'email'             => 'encrypted',
        'contact_group'     => 'encrypted:array',
        'custom_attributes' => 'array',
        'is_unsubscribed'   => 'boolean',
        'unsubscribed_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault();
    }

    /**
     * Sales Pipeline deals attached to this contact — open ones first so the
     * contact record + the inbox panel show what's actively in play. Drives
     * the "this person already has 2 open deals" context competitors sell.
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class)
            ->orderByRaw("CASE status WHEN 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('id');
    }

    /**
     * Workspace-shared visibility — every member of the current
     * workspace sees every contact in it. Pre-migration rows fall
     * back to the original creator only.
     */
    /**
     * Contacts who have NOT opted out — the audience any bulk send is allowed
     * to touch. Keeps false AND null (never-set) so only an explicit opt-out
     * is dropped; a legacy row with a NULL flag is still a subscriber.
     *
     * Use this on every campaign / broadcast / scheduled audience query. It
     * exists because the same filter was hand-written in two controllers and
     * simply forgotten in a third (/scheduled), which meant a customer who
     * replied STOP kept receiving scheduled messages.
     */
    public function scopeSubscribed(Builder $q): Builder
    {
        return $q->where(function ($w) {
            $w->where('is_unsubscribed', false)->orWhereNull('is_unsubscribed');
        });
    }

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

    /**
     * Resolve display initials for avatar placeholders.
     * Falls back to "?" when name is empty.
     */
    public function getInitialsAttribute(): string
    {
        $source = $this->name ?: trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        if ($source === '') {
            return '?';
        }
        $parts = preg_split('/\s+/', trim($source));
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
        return mb_strtoupper($first . $last) ?: '?';
    }

    /**
     * Flow auto-enrollment trigger — fire FlowEnrollmentService::onGroupJoin
     * whenever a group id is NEWLY added to the contact's group list. Any
     * active+published flow with trigger_kind='group_join' and
     * trigger_value=<gid> picks up the contact.
     *
     * The `contact_group` column is an encrypted JSON array of group ids
     * (denormalized — there's no pivot table). We diff old vs new on
     * every save so every write site (controller create/update, import,
     * bulk move) goes through this single funnel.
     */
    /**
     * Canonicalise a phone to bare digits, country-code-prefixed, so the same
     * number stored as (cc + mobile) OR joined in `mobile` compares equal.
     */
    public static function canonicalizePhone(?string $cc, ?string $mobile): string
    {
        $m = preg_replace('/\D+/', '', (string) $mobile);
        if ($m === '') return '';
        // Strip a leading international dial prefix ("00971…" == "971…") so the
        // same number typed either way dedups to one contact.
        if (strlen($m) > 2 && str_starts_with($m, '00')) {
            $m = substr($m, 2);
        }
        $c = preg_replace('/\D+/', '', (string) $cc);
        if ($c !== '' && !str_starts_with($m, $c)) {
            $m = $c . $m;
        }
        return $m;
    }

    /**
     * The saved contact's display NAME for a phone number (digits, international),
     * or null if no saved contact / no name. Uses the indexed mobile_hash (mobile
     * itself is encrypted), so it's a single fast lookup — safe to call per inbound
     * message. Request-memoised so repeated numbers in one render don't re-query.
     */
    public static function nameForPhone(int $workspaceId, ?string $digits): ?string
    {
        static $cache = [];
        $d = preg_replace('/\D+/', '', (string) $digits);
        if (strlen($d) < 6) return null;
        $key = $workspaceId . ':' . $d;
        if (array_key_exists($key, $cache)) return $cache[$key];

        $name = null;
        try {
            $hash = static::hashPhone(null, $d);
            if ($hash) {
                $found = static::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('mobile_hash', $hash)
                    ->value('name');
                $found = trim((string) $found);
                $name  = $found !== '' ? $found : null;
            }
        } catch (\Throwable $e) { /* lookup non-fatal */ }

        return $cache[$key] = $name;
    }

    /** Deterministic lookup key for a phone (mobile is encrypted → not queryable). */
    public static function hashPhone(?string $cc, ?string $mobile): ?string
    {
        $canon = static::canonicalizePhone($cc, $mobile);
        return $canon === '' ? null : hash('sha256', $canon);
    }

    /**
     * Auto-capture a manually-entered number as a contact (dedup by phone hash
     * within the workspace). Called from every place a raw number is typed to
     * send — broadcasts, campaigns, scheduled, quick-send, chat — so numbers a
     * user messages once are never lost. Returns the existing/created contact,
     * or null when the input isn't a usable phone. Never throws.
     */
    public static function rememberPhone(int $workspaceId, ?int $userId, ?string $rawPhone, ?string $name = null, ?string $countryCode = null): ?self
    {
        try {
            $digits = preg_replace('/\D+/', '', (string) $rawPhone);
            if (strlen($digits) < 6) return null; // not a real phone
            $hash = static::hashPhone($countryCode, $rawPhone);
            if (!$hash) return null;

            $existing = static::query()
                ->where('workspace_id', $workspaceId)
                ->where('mobile_hash', $hash)
                ->first();
            if ($existing) return $existing;

            return static::create([
                'workspace_id' => $workspaceId,
                'user_id'      => $userId,
                'mobile'       => (string) $rawPhone,
                'country_code' => $countryCode ?: null,
                'name'         => ($name !== null && trim($name) !== '') ? trim($name) : null,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[CONTACT-CAPTURE] rememberPhone failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Contact-level tags (contact_tag pivot). Attaching one from /contacts is
     * what fires the flow `tag_added` audience trigger — see
     * ContactsController::attachTag → FlowEnrollmentService::onTagAdded.
     */
    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Tag::class, 'contact_tag')
            ->withTimestamps()
            ->withPivot('added_by');
    }

    protected static function booted(): void
    {
        // Keep the queryable phone hash in lock-step with the encrypted mobile
        // on EVERY write, so dedup + lookups work without decrypting every row.
        static::saving(function (Contact $contact) {
            $contact->mobile_hash = static::hashPhone($contact->country_code, $contact->mobile);
        });

        static::created(function (Contact $contact) {
            // New-contact flow trigger (trigger_kind='contact_created').
            try { app(\App\Services\Flow\FlowEnrollmentService::class)->onContactCreated($contact); }
            catch (\Throwable $e) { \Log::warning('Flow onContactCreated: ' . $e->getMessage()); }

            $groups = is_array($contact->contact_group) ? $contact->contact_group : [];
            foreach ($groups as $gid) {
                $gid = (int) $gid;
                if ($gid > 0) {
                    try { app(\App\Services\Flow\FlowEnrollmentService::class)->onGroupJoin($contact, $gid); }
                    catch (\Throwable $e) { \Log::warning('Flow onGroupJoin (created): ' . $e->getMessage()); }
                }
            }
        });

        static::updated(function (Contact $contact) {
            // Opt-in (re-subscribe) flow trigger: is_unsubscribed true→false.
            // Safe no-op if the column is absent (wasChanged returns false).
            if ($contact->wasChanged('is_unsubscribed') && !$contact->is_unsubscribed && $contact->getOriginal('is_unsubscribed')) {
                try { app(\App\Services\Flow\FlowEnrollmentService::class)->onOptIn($contact); }
                catch (\Throwable $e) { \Log::warning('Flow onOptIn: ' . $e->getMessage()); }
            }

            if (!$contact->wasChanged('contact_group')) return;
            $oldRaw = $contact->getOriginal('contact_group');
            // The original value comes back as the raw (still-encrypted)
            // string when cast=encrypted:array. Decrypt manually so the
            // diff works on plain arrays.
            $old = [];
            if (is_array($oldRaw)) {
                $old = $oldRaw;
            } elseif (is_string($oldRaw) && $oldRaw !== '') {
                try {
                    $decrypted = decrypt($oldRaw);
                    $old = is_array($decrypted)
                        ? $decrypted
                        : (is_string($decrypted) ? (json_decode($decrypted, true) ?: []) : []);
                } catch (\Throwable $e) {
                    $old = [];
                }
            }
            $new = is_array($contact->contact_group) ? $contact->contact_group : [];
            $added = array_diff(array_map('strval', $new), array_map('strval', $old));
            foreach ($added as $gid) {
                $gid = (int) $gid;
                if ($gid > 0) {
                    try { app(\App\Services\Flow\FlowEnrollmentService::class)->onGroupJoin($contact, $gid); }
                    catch (\Throwable $e) { \Log::warning('Flow onGroupJoin (updated): ' . $e->getMessage()); }
                }
            }
        });
    }
}
