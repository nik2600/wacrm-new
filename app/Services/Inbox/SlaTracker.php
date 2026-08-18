<?php

namespace App\Services\Inbox;

use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\SlaPolicy;
use Carbon\Carbon;

/**
 * Decides when first-response and resolution are due, and flips the
 * sla_breached flag when the clock runs out. Lifecycle hooks call:
 *   - applyOnInbound()         when a customer message arrives
 *   - markFirstResponse()      when the first agent reply lands
 *   - applyOnResolution()      when status flips to resolved
 *   - sweepBreaches()          run by scheduler to flag passive breaches
 *
 * Business hours awareness is approximate — we add minutes literally and
 * skip the calendar math. Workspaces that need real BH-aware SLAs can
 * subclass and override `applyDuration`.
 */
class SlaTracker
{
    public function applyOnInbound(Conversation $conv): void
    {
        $policy = $this->resolvePolicy($conv);
        if (!$policy) return;

        $targets = $policy->targetsFor($conv->priority);
        $now     = now();

        $first = $targets['first_response_minutes']
            ? $this->applyDuration($now, $targets['first_response_minutes'], $policy)
            : null;

        $res = $targets['resolution_minutes']
            ? $this->applyDuration($now, $targets['resolution_minutes'], $policy)
            : null;

        $conv->forceFill([
            'sla_policy_id'           => $policy->id,
            'sla_first_response_due'  => $conv->first_response_at ? $conv->sla_first_response_due : $first,
            'sla_resolution_due'      => $res,
            'sla_breached'            => false,
            'last_inbound_at'         => $now,
        ])->save();
    }

    public function markFirstResponse(Conversation $conv): void
    {
        if ($conv->first_response_at) return;
        $now = now();
        $conv->forceFill([
            'first_response_at' => $now,
            'last_outbound_at'  => $now,
        ])->save();
        ConversationEvent::record($conv->id, $conv->workspace_id, null, 'first_response', [
            'at'              => $now->toIso8601String(),
            'within_sla'      => $conv->sla_first_response_due ? $now->lessThanOrEqualTo($conv->sla_first_response_due) : null,
        ], 'system');
    }

    public function applyOnResolution(Conversation $conv): void
    {
        $now = now();
        $within = $conv->sla_resolution_due ? $now->lessThanOrEqualTo($conv->sla_resolution_due) : null;
        if ($within === false) {
            $conv->forceFill(['sla_breached' => true])->save();
        }
    }

    public function sweepBreaches(): int
    {
        $now = now();
        $count = 0;

        $first = Conversation::open()
            ->where('sla_breached', false)
            ->whereNotNull('sla_first_response_due')
            ->whereNull('first_response_at')
            ->where('sla_first_response_due', '<', $now)
            ->get();
        foreach ($first as $c) {
            $c->forceFill(['sla_breached' => true])->save();
            ConversationEvent::record($c->id, $c->workspace_id, null, 'sla_breach', [
                'kind' => 'first_response', 'due_at' => $c->sla_first_response_due?->toIso8601String(),
            ], 'system');
            $count++;
        }

        $res = Conversation::open()
            ->where('sla_breached', false)
            ->whereNotNull('sla_resolution_due')
            ->where('sla_resolution_due', '<', $now)
            ->get();
        foreach ($res as $c) {
            $c->forceFill(['sla_breached' => true])->save();
            ConversationEvent::record($c->id, $c->workspace_id, null, 'sla_breach', [
                'kind' => 'resolution', 'due_at' => $c->sla_resolution_due?->toIso8601String(),
            ], 'system');
            $count++;
        }

        return $count;
    }

    private function resolvePolicy(Conversation $conv): ?SlaPolicy
    {
        // Explicit policy pinned to the conversation always wins —
        // operator or routing rule decided this one.
        if ($conv->sla_policy_id) return SlaPolicy::find($conv->sla_policy_id);
        // Otherwise pick the most-specific policy. A policy whose
        // device_ids matches the conversation's pinned device beats
        // the workspace-wide default — so a "tight" SLA on the
        // support number applies even when a looser "marketing"
        // policy is the workspace default. Falls back to the
        // workspace default if no device-scoped policy matches.
        return SlaPolicy::bestFor((int) $conv->workspace_id, $conv->device_id);
    }

    protected function applyDuration(Carbon $from, int $minutes, SlaPolicy $policy): Carbon
    {
        // V1: literal minutes. Subclasses can override for business-hours math.
        return $from->copy()->addMinutes($minutes);
    }
}
