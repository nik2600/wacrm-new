<?php

namespace App\Services\Frontend;

use App\Models\FrontendContent;
use Illuminate\Support\Facades\Cache;

/**
 * Read/write layer for the public marketing site's editable content.
 *
 * The public Blade components call the global `fc('key', 'default')`
 * helper, which delegates to this service. Behaviour:
 *
 *   - Public visitor (not editing): returns the PUBLISHED value, or the
 *     hardcoded default when there is no published override. So an empty
 *     table === the site exactly as shipped.
 *   - Admin in edit mode (?fc_edit=1): returns the DRAFT value (falling
 *     back to published, then the default) so they preview unsaved work.
 *
 * The whole table is tiny (a few hundred rows at most) and is read on
 * every public page, so it is cached forever and busted on any write
 * (see FrontendContent::booted()).
 */
class FrontendContentStore
{
    /** @var array<string,array{type:string,draft:?string,published:?string}>|null */
    private ?array $rows = null;

    /* ───────────────────────────── reads ─────────────────────────── */

    /**
     * Resolve a content value.
     *
     * @param  string     $key      dotted ckey, e.g. "home.hero.headline"
     * @param  mixed      $default  the hardcoded fallback (current copy)
     * @param  bool|null  $draft    force draft/published; null = auto by edit mode
     */
    public function get(string $key, mixed $default = null, ?bool $draft = null): mixed
    {
        $draft ??= $this->editing();
        $row = $this->all()[$key] ?? null;
        if (!$row) {
            return $default;
        }

        $value = $draft
            ? ($row['draft'] ?? $row['published'])
            : $row['published'];

        if ($value === null || $value === '') {
            return $default;
        }

        if (($row['type'] ?? 'text') === 'json') {
            $decoded = json_decode($value, true);
            return $decoded === null && json_last_error() !== JSON_ERROR_NONE ? $default : $decoded;
        }

        return $value;
    }

    /** Type registered for a key (text by default). */
    public function type(string $key): string
    {
        return $this->all()[$key]['type'] ?? 'text';
    }

    /**
     * Are we rendering inside the live editor? True only for an authed
     * platform admin who passed ?fc_edit=1. Keeps draft content invisible
     * to the public even if someone guesses the query string.
     */
    public function editing(): bool
    {
        if (!request()->boolean('fc_edit')) {
            return false;
        }
        $user = auth()->user();
        try {
            return $user && method_exists($user, 'isAdmin') && $user->isAdmin();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /* ──────────────────────────── writes ─────────────────────────── */

    /** Save a draft value for a key (creates the row if missing). */
    public function setDraft(string $key, mixed $value, ?string $type = null): FrontendContent
    {
        if (is_array($value)) {
            $type ??= 'json';
            $value = json_encode($value);
        }
        $row = FrontendContent::firstOrNew(['ckey' => $key]);
        $row->draft = $value;
        if ($type) {
            $row->type = $type;
        }
        $row->updated_by = auth()->id();
        $row->save();
        $this->rows = null;
        return $row;
    }

    /**
     * Promote drafts to live. Pass a dotted prefix to publish one page
     * (e.g. "home."), or null to publish everything.
     */
    public function publish(?string $prefix = null): int
    {
        $q = FrontendContent::query();
        if ($prefix) {
            $q->where('ckey', 'like', addcslashes($prefix, '%_') . '%');
        }
        $n = 0;
        foreach ($q->get() as $row) {
            if ($row->published !== $row->draft) {
                $row->published = $row->draft;
                $row->updated_by = auth()->id();
                $row->save();
                $n++;
            }
        }
        $this->rows = null;
        return $n;
    }

    /**
     * Throw away unpublished drafts, keeping whatever is currently live.
     * Pass a dotted prefix to scope to one page, or null for everything.
     * Rows that were never published (draft-only) are removed so the
     * shipped default shows again.
     */
    public function discard(?string $prefix = null): int
    {
        $q = FrontendContent::query();
        if ($prefix) {
            $q->where('ckey', 'like', addcslashes($prefix, '%_') . '%');
        }
        $n = 0;
        foreach ($q->get() as $row) {
            if ($row->draft === $row->published) {
                continue;
            }
            if ($row->published === null || $row->published === '') {
                $row->delete();   // never went live → back to default
            } else {
                $row->draft = $row->published;
                $row->save();
            }
            $n++;
        }
        $this->rows = null;
        Cache::forget(FrontendContent::CACHE_KEY);
        return $n;
    }

    /** Revert a key to its shipped default by clearing both values. */
    public function reset(string $key): void
    {
        FrontendContent::where('ckey', $key)->delete();
        // A query-builder delete bypasses the model's deleted event, so the
        // table cache isn't auto-busted — forget it explicitly.
        Cache::forget(FrontendContent::CACHE_KEY);
        $this->rows = null;
    }

    /**
     * Drop both the table cache and this request's in-memory copy. Call
     * after a bulk query-builder delete done OUTSIDE this service (those
     * skip the model events and leave $rows stale within the request).
     */
    public function flush(): void
    {
        Cache::forget(FrontendContent::CACHE_KEY);
        $this->rows = null;
    }

    /* ──────────────────────────── internals ──────────────────────── */

    /** @return array<string,array{type:string,draft:?string,published:?string}> */
    private function all(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }
        $this->rows = Cache::rememberForever(FrontendContent::CACHE_KEY, function () {
            return FrontendContent::all(['ckey', 'type', 'draft', 'published'])
                ->keyBy('ckey')
                ->map(fn ($r) => ['type' => $r->type, 'draft' => $r->draft, 'published' => $r->published])
                ->all();
        });
        return $this->rows;
    }
}
