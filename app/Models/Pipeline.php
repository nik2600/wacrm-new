<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sales pipeline (Kanban board) belonging to a workspace. One is the
 * default; each carries an ordered set of stages and the deals on it.
 */
class Pipeline extends Model
{
    protected $fillable = [
        'workspace_id', 'name', 'is_default', 'currency', 'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** The 6-stage ladder seeded into every workspace's first pipeline. */
    public const DEFAULT_STAGES = [
        ['name' => 'New Lead',     'probability' => 10,  'color' => '#64748B', 'is_won' => false, 'is_lost' => false],
        ['name' => 'Qualified',    'probability' => 25,  'color' => '#0EA5E9', 'is_won' => false, 'is_lost' => false],
        ['name' => 'Proposal',     'probability' => 50,  'color' => '#6366F1', 'is_won' => false, 'is_lost' => false],
        ['name' => 'Negotiation',  'probability' => 75,  'color' => '#F59E0B', 'is_won' => false, 'is_lost' => false],
        ['name' => 'Won',          'probability' => 100, 'color' => '#16A34A', 'is_won' => true,  'is_lost' => false],
        ['name' => 'Lost',         'probability' => 0,   'color' => '#DC2626', 'is_won' => false, 'is_lost' => true],
    ];

    /* -------------------- relations -------------------- */

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('sort_order');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /* -------------------- scopes -------------------- */

    public function scopeForCurrentWorkspace(Builder $q): Builder
    {
        $user = auth()->user();
        if (!$user) return $q->whereRaw('1=0');
        return $q->where('workspace_id', (int) ($user->current_workspace_id ?? 0));
    }

    /* -------------------- seeding -------------------- */

    /**
     * Guarantee the workspace has at least one pipeline (with stages) and
     * return its default. Called on the first /deals visit — cheap existence
     * check, seeds the 6-stage ladder only when the board is empty.
     */
    public static function ensureDefaultForWorkspace(int $workspaceId, ?string $currency = null): self
    {
        // Default the pipeline to the WORKSPACE's own currency (what the client
        // set in settings), NOT a hardcoded 'INR'. The old 'INR' default stored
        // every deal in rupees, so the board CONVERTED it to the real workspace
        // currency (an entered 5,000,000 IDR was saved as ₹5M then shown as
        // Rp927M). Resolve the workspace currency once here.
        if (!$currency) {
            $currency = self::workspaceCurrency($workspaceId);
        }

        $existing = static::where('workspace_id', $workspaceId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
        if ($existing) {
            // Self-heal a pipeline that somehow has no stages.
            if ($existing->stages()->count() === 0) {
                $existing->seedDefaultStages();
            }

            // HEAL the stale 'INR' default across the WHOLE workspace. The old
            // hardcoded 'INR' default made the board CONVERT every amount to the
            // real workspace currency (5,000,000 IDR entered → saved as ₹5M →
            // shown as Rp928M). RELABEL every pipeline AND deal still carrying the
            // old 'INR' code to the workspace currency — NO value conversion, the
            // amounts were always entered in the workspace's own currency. Keyed
            // on the DEAL/pipeline currency (not the default pipeline's), so it
            // fixes a deal stuck on 'INR' even if its pipeline was already
            // relabeled. Only 'INR'-coded rows are touched (a deal explicitly set
            // to another currency is left alone). Runs once, then no-ops.
            if ($currency !== 'INR') {
                try {
                    \App\Models\Deal::where('workspace_id', $workspaceId)
                        ->where('currency', 'INR')
                        ->update(['currency' => $currency]);
                    static::where('workspace_id', $workspaceId)
                        ->where('currency', 'INR')
                        ->update(['currency' => $currency]);
                } catch (\Throwable $e) { /* best-effort heal — never block the board */ }
            }

            return $existing;
        }

        $pipeline = static::create([
            'workspace_id' => $workspaceId,
            'name'         => 'Sales Pipeline',
            'is_default'   => true,
            'currency'     => $currency ?: 'USD',
            'sort_order'   => 0,
        ]);
        $pipeline->seedDefaultStages();

        return $pipeline;
    }

    /** The workspace's own currency, falling back to the platform default. */
    private static function workspaceCurrency(int $workspaceId): string
    {
        try {
            $c = (string) (\App\Models\Workspace::whereKey($workspaceId)->value('currency') ?: '');
            if ($c !== '') return $c;
        } catch (\Throwable $e) { /* fall through to default */ }
        return (string) (\App\Models\SystemSetting::get('default_currency', 'USD'));
    }

    /** Create the default stage ladder for this pipeline. */
    public function seedDefaultStages(): void
    {
        foreach (self::DEFAULT_STAGES as $i => $s) {
            $this->stages()->create([
                'workspace_id' => $this->workspace_id,
                'name'         => $s['name'],
                'sort_order'   => $i,
                'color'        => $s['color'],
                'is_won'       => $s['is_won'],
                'is_lost'      => $s['is_lost'],
                'probability'  => $s['probability'],
            ]);
        }
    }
}
