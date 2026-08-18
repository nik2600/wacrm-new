<?php

namespace App\Models\Concerns;

use App\Services\WorkspaceEngine;
use Illuminate\Database\Eloquent\Builder;

/**
 * Adds `scopeForCurrentEngine()` to any model that has a `provider`
 * column. Filters rows to the engines the current workspace can use
 * right now — the ENABLED SET (waba / baileys / twilio), not just the
 * single default. No-op when there's no auth context (queue jobs /
 * console commands) so the sweep doesn't accidentally hide cross-engine
 * maintenance work.
 *
 * Multi-engine: a workspace running several engines must SEE the rows of
 * every engine it has enabled — a WABA broadcast and a Baileys broadcast
 * both belong in the list. We therefore scope to
 * WorkspaceEngine::enginesFor() (the enabled set, always non-empty) with
 * whereIn, NOT WorkspaceEngine::for() (the single default). For a
 * single-engine workspace enginesFor() == [for()], so whereIn(['baileys'])
 * is byte-identical to the old where('provider','baileys') — no change.
 *
 * Pattern shared by Conversation, Broadcast, ScheduledMessage,
 * KeywordReply, WpCampaign, Flow, Appointment, WaCall — all of which
 * have a `provider` column and need engine-aware listing.
 */
trait HasEngineScope
{
    public function scopeForCurrentEngine(Builder $q): Builder
    {
        $u    = auth()->user();
        $wsId = (int) ($u?->current_workspace_id ?? 0);
        if (!$wsId) return $q;

        $engines = WorkspaceEngine::enginesFor($wsId);
        return $q->whereIn($q->getModel()->getTable() . '.provider', $engines);
    }
}
