<?php

namespace App\Services\Ordering;

use App\Models\WaGroup;
use App\Models\WaGroupMember;
use Illuminate\Support\Collection;

/**
 * Answers "which WhatsApp group should this customer's order be posted into?"
 *
 * Two strategies, in priority order:
 *   1. group_code — a code baked into the wa.me link the customer tapped
 *      (100% accurate, no ambiguity). e.g. wa.me/60x?text=ORDER%20G:ACME
 *   2. membership  — the bot is in the customer's group, so look up which
 *      group(s) the customer's phone belongs to. One match → use it. Several
 *      → flagged 'ambiguous' (caller can ask the customer to pick).
 */
class GroupDirectory
{
    /** @return Collection<int,WaGroup> groups the phone is a member of */
    public function findGroupsForPhone(int $workspaceId, string $phone): Collection
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') return collect();

        $jids = WaGroupMember::where('workspace_id', $workspaceId)
            ->where('phone', $digits)
            ->pluck('group_jid')->all();
        \Log::info('[ORDER-FLOW] 4 · group lookup', [
            'ws'             => $workspaceId,
            'phone'          => $digits,
            'member_of_jids' => count($jids),
        ]);
        if (empty($jids)) return collect();

        $groups = WaGroup::where('workspace_id', $workspaceId)
            ->whereIn('group_jid', $jids)
            ->orderByDesc('synced_at')
            ->get();
        \Log::info('[ORDER-FLOW] 4 · groups matched', [
            'count' => $groups->count(),
            'names' => $groups->pluck('name')->take(5)->all(),
        ]);
        return $groups;
    }

    /**
     * @return array{group: ?WaGroup, reason: string, candidates: int}
     *   reason: code | single | ambiguous | not_member | none
     */
    public function resolveForCustomer(int $workspaceId, string $phone, ?string $groupCode = null): array
    {
        $code = trim((string) $groupCode);
        if ($code !== '') {
            $g = WaGroup::where('workspace_id', $workspaceId)->where('group_code', $code)->first();
            if ($g) return ['group' => $g, 'reason' => 'code', 'candidates' => 1];
        }

        $groups = $this->findGroupsForPhone($workspaceId, $phone);
        if ($groups->isEmpty()) {
            return ['group' => null, 'reason' => 'not_member', 'candidates' => 0];
        }
        if ($groups->count() === 1) {
            return ['group' => $groups->first(), 'reason' => 'single', 'candidates' => 1];
        }
        // Several groups — return the freshest (most recently synced) but flag it
        // so the order flow can disambiguate if it wants to.
        return ['group' => $groups->first(), 'reason' => 'ambiguous', 'candidates' => $groups->count()];
    }
}
