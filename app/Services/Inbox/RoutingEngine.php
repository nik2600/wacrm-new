<?php

namespace App\Services\Inbox;

use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Flow;
use App\Models\RoutingRule;
use App\Models\Tag;
use App\Models\WaTemplate;
use App\Models\Workspace;
use App\Services\InboxDispatcher;

/**
 * Evaluates routing_rules against an incoming conversation. Conditions are
 * an array of { field, op, value, case? }. Actions are an array of typed
 * dicts. Rules are processed in `sort` order; if `stop_on_match` is true
 * the engine returns after applying that rule.
 *
 * Conditions kept simple by design — anything we'd need a real DSL for
 * (precedence, OR groups, regex captures) would justify swapping this for
 * a proper rule library, not extending these strings.
 */
class RoutingEngine
{
    public function __construct(private AssignmentService $assignment)
    {
    }

    /**
     * Action types that are SAFE to re-fire on every inbound message:
     * tags (idempotent — syncWithoutDetaching), auto-replies (gated by
     * AutoReplyGuard's cooldown), and flow triggers (the flow runtime
     * tracks its own sessions). These are the "per-message" actions.
     */
    private const PER_MESSAGE_ACTIONS = ['add_tag', 'auto_reply', 'trigger_flow'];

    /**
     * Action types that should run ONCE per conversation — re-firing
     * them on follow-ups would clobber an operator's manual changes
     * (e.g. moving a chat to a different agent after the rule auto-
     * assigned it).
     */
    private const ONE_SHOT_ACTIONS = ['assign_team', 'assign_user', 'set_priority'];

    public function applyToInbound(Conversation $conv, array $context = [], bool $isFollowUp = false): void
    {
        // Meta Business Agent coexistence — when Meta's agent is fronting this
        // workspace, don't run inbound routing at all (auto_reply / trigger_flow /
        // tag→flow / assign→AI all send or start automation) so we never answer
        // alongside it. The thread is still captured for Team-Inbox takeover.
        if ($conv->workspace_id) {
            $ws = Workspace::find($conv->workspace_id);
            if ($ws && $ws->suppressesOurAutoReply()) {
                \Illuminate\Support\Facades\Log::info('[ROUTING] skipped — Meta Business Agent is fronting this workspace', [
                    'workspace_id' => $conv->workspace_id, 'conv_id' => $conv->id,
                ]);
                return;
            }
        }

        // Start the SLA first-response / resolution clock on the FIRST customer
        // inbound of this conversation. This is THE call that makes SLA policies
        // actually do something — without it the policy saved but no due date was
        // ever set, so nothing ever breached (the feature looked wired but wasn't).
        // Guarded to the first inbound so a burst of messages before any reply
        // can't keep pushing the due out, and a later message can't un-flag a
        // breach. No-op when the workspace has no matching SLA policy — the
        // tracker resolves one and returns early if none applies. Both inbound
        // paths (WABA + Unofficial API) funnel through applyToInbound, so this
        // one call covers every channel.
        if (!$conv->first_response_at && !$conv->sla_first_response_due) {
            try {
                app(\App\Services\Inbox\SlaTracker::class)->applyOnInbound($conv);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[SLA] applyOnInbound failed', [
                    'conv_id' => $conv->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        $rules = RoutingRule::active()
            ->forWorkspace($conv->workspace_id)
            ->orderBy('sort')->orderBy('id')
            ->get();

        // Two passes:
        //   1. Run all NON-fallback rules in sort order.
        //   2. If nothing matched in pass 1, run fallback rules (catch-all
        //      "if nothing else matched, assign to Triage" patterns).
        $primary  = $rules->filter(fn ($r) => !$r->is_fallback);
        $fallback = $rules->filter(fn ($r) => (bool) $r->is_fallback);

        $fired = $this->runPass($primary, $conv, $context, $isFollowUp);
        if (empty($fired)) {
            $fired = $this->runPass($fallback, $conv, $context, $isFollowUp);
        }

        if (!empty($fired)) {
            // MERGE with whatever execute() actions stored on the row
            // (e.g. pending_flow_id from trigger_flow). The naive
            // `routing_meta = [fired_rules => …]` was wiping those.
            $conv->refresh();
            $meta = is_array($conv->routing_meta) ? $conv->routing_meta : [];
            $meta['fired_rules'] = $fired;
            $meta['at']          = now()->toIso8601String();
            $conv->forceFill(['routing_meta' => $meta])->save();
            ConversationEvent::record($conv->id, $conv->workspace_id, null, 'routing_rule_fired', [
                'rule_ids' => $fired,
            ], 'rule');
        }
    }

    private function runPass($rules, Conversation $conv, array $context, bool $isFollowUp = false): array
    {
        $fired = [];
        foreach ($rules as $rule) {
            if (!$this->matches($rule->conditions ?? [], $conv, $context)) continue;

            // On follow-up messages, filter out one-shot actions so we
            // don't clobber operator-driven state. The per-message
            // actions (add_tag / auto_reply / trigger_flow) still fire.
            $actions = $rule->actions ?? [];
            if ($isFollowUp) {
                $actions = array_values(array_filter($actions, function ($a) {
                    return in_array($a['type'] ?? null, self::PER_MESSAGE_ACTIONS, true);
                }));
                if (empty($actions)) continue; // rule had no per-message actions
            }
            $this->execute($actions, $conv);

            $rule->forceFill([
                'fired_count'   => ($rule->fired_count ?? 0) + 1,
                'last_fired_at' => now(),
            ])->save();
            $fired[] = $rule->id;

            if ($rule->stop_on_match) break;
        }
        return $fired;
    }

    /**
     * Evaluate a flat list of conditions (top-level: AND) OR a nested group
     * structure. Each entry can be either:
     *   - a leaf condition: { field, op, value }
     *   - a group:          { type: 'group', op: 'and'|'or', conditions: [...] }
     * Groups can nest arbitrarily deep. Top-level array is AND-joined.
     */
    private function matches(array $conditions, Conversation $conv, array $context): bool
    {
        if (empty($conditions)) return false;
        foreach ($conditions as $c) {
            if (!$this->evalNode($c, $conv, $context)) return false;
        }
        return true;
    }

    /** Recursive evaluator that handles both leaf conditions and groups. */
    private function evalNode(array $node, Conversation $conv, array $context): bool
    {
        if (($node['type'] ?? null) === 'group') {
            $sub = $node['conditions'] ?? [];
            if (empty($sub) || !is_array($sub)) return true; // empty group = pass
            $op = strtolower((string) ($node['op'] ?? 'and'));
            if ($op === 'or') {
                foreach ($sub as $child) {
                    if ($this->evalNode($child, $conv, $context)) return true;
                }
                return false;
            }
            // default: AND
            foreach ($sub as $child) {
                if (!$this->evalNode($child, $conv, $context)) return false;
            }
            return true;
        }
        return $this->evalOne($node, $conv, $context);
    }

    private function evalOne(array $c, Conversation $conv, array $context): bool
    {
        $field = $c['field'] ?? null;
        $op    = $c['op']    ?? 'equals';
        $value = $c['value'] ?? null;
        $ci    = ($c['case'] ?? 'i') === 'i';

        $actual = match ($field) {
            'message_text'   => (string) ($context['message_text'] ?? ''),
            'contact_phone'  => (string) ($context['contact_phone'] ?? ''),
            'contact_tag'    => (array)  ($context['contact_tags']  ?? []),
            'channel'        => (string) $conv->channel,
            'priority'       => (string) $conv->priority,
            'time_of_day'    => (int)    now()->hour,
            'day_of_week'    => (int)    now()->dayOfWeek,
            'language'       => (string) ($context['language'] ?? ''),
            'outside_business_hours' => $this->isOutsideBusinessHours($conv),
            // Multi-device operand. `incoming_device` resolves to the
            // device id the customer messaged in on (stamped by the
            // inbound webhook into conversations.device_id). Used with
            // the `in` / `equals` / `not_in` operators so an author can
            // route by which paired number the message landed on:
            //   { field: 'incoming_device', op: 'in', value: [36] }
            // Rules that don't reference this field keep working
            // unchanged (single-device workspaces never set it).
            'incoming_device' => $conv->device_id,
            default          => null,
        };

        return $this->compare($actual, $op, $value, $ci);
    }

    /**
     * Cached per-engine-call lookup so a single inbound message doesn't
     * hit the workspaces table once per condition row.
     */
    private array $bhCache = [];

    private function isOutsideBusinessHours(Conversation $conv): bool
    {
        $wsId = $conv->workspace_id;
        if (!array_key_exists($wsId, $this->bhCache)) {
            $ws = Workspace::find($wsId);
            $this->bhCache[$wsId] = $ws ? $ws->isOutsideBusinessHours() : false;
        }
        return $this->bhCache[$wsId];
    }

    private function compare($actual, string $op, $value, bool $ci): bool
    {
        if ($ci && is_string($actual)) $actual = mb_strtolower($actual);
        if ($ci && is_string($value))  $value  = mb_strtolower($value);

        return match ($op) {
            'equals'      => $actual == $value,
            'not_equals'  => $actual != $value,
            'contains'    => is_string($actual) && str_contains($actual, (string) $value),
            'not_contains'=> is_string($actual) && !str_contains($actual, (string) $value),
            'starts_with' => is_string($actual) && str_starts_with($actual, (string) $value),
            'ends_with'   => is_string($actual) && str_ends_with($actual, (string) $value),
            'in'          => is_array($value)  && in_array($actual, $value, false),
            'not_in'      => is_array($value)  && !in_array($actual, $value, false),
            'gt'          => $actual >  $value,
            'gte'         => $actual >= $value,
            'lt'          => $actual <  $value,
            'lte'         => $actual <= $value,
            'has_any'     => is_array($actual) && is_array($value) && count(array_intersect($actual, $value)) > 0,
            'matches'     => is_string($actual) && @preg_match("#$value#" . ($ci ? 'i' : ''), $actual),
            default       => false,
        };
    }

    private function execute(array $actions, Conversation $conv): void
    {
        foreach ($actions as $a) {
            $type = $a['type'] ?? null;
            switch ($type) {
                case 'assign_team':
                    $tid = (int) ($a['team_id'] ?? 0);
                    if ($tid > 0) {
                        // Per-team device whitelist guard. Auto-assignment
                        // skips teams that don't handle this conversation's
                        // paired device. Manual operator assignment (the
                        // /team-inbox UI's team picker) still works because
                        // it calls AssignmentService::assign() directly and
                        // bypasses the routing engine. This guard only
                        // affects RULE-driven assignment.
                        $team = \App\Models\Team::find($tid);
                        if ($team && !$team->handlesDevice($conv->device_id)) {
                            \Illuminate\Support\Facades\Log::info('[ROUTING] assign_team skipped — team device scope mismatch', [
                                'rule_team_id' => $tid,
                                'conv_id'      => $conv->id,
                                'conv_device'  => $conv->device_id,
                                'team_devices' => is_array($team->device_ids) ? $team->device_ids : [],
                            ]);
                            break;
                        }
                        $this->assignment->assign($conv, null, $tid, 'least_loaded', null);
                    }
                    break;
                case 'assign_user':
                    $this->assignment->assign($conv, (int) ($a['user_id'] ?? 0), $conv->assignee_team_id, 'manual', null);
                    break;
                case 'set_priority':
                    if (in_array($a['value'] ?? null, Conversation::PRIORITIES, true)) {
                        $conv->forceFill(['priority' => $a['value']])->save();
                    }
                    break;
                case 'add_tag':
                    $tag = Tag::firstOrCreate(
                        ['workspace_id' => $conv->workspace_id, 'slug' => \Str::slug($a['name'] ?? 'tag')],
                        ['name' => $a['name'] ?? 'tag', 'color' => $a['color'] ?? '#075E54'],
                    );
                    $conv->tags()->syncWithoutDetaching([$tag->id]);
                    // Flow auto-enrollment — keeps routing-rule add_tag
                    // behaviorally identical to the manual operator tag.
                    try {
                        app(\App\Services\Flow\FlowEnrollmentService::class)
                            ->onConversationTagged($conv, $tag->id);
                    } catch (\Throwable $e) { /* best-effort */ }
                    break;
                case 'mark_spam':
                    $conv->forceFill(['is_spam' => true, 'inbox_status' => 'spam'])->save();
                    break;
                case 'set_sla':
                    $conv->forceFill(['sla_policy_id' => (int) ($a['sla_policy_id'] ?? 0) ?: null])->save();
                    break;
                case 'assign_agent':
                    $agentId = (int) ($a['agent_id'] ?? 0);
                    if ($agentId) {
                        $conv->forceFill(['assignee_agent_id' => $agentId])->save();
                        try {
                            app(\App\Services\AiAgentService::class)->respondIfAssigned($conv->fresh());
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning('[ROUTING] assign_agent respond failed: ' . $e->getMessage());
                        }
                    }
                    break;
                case 'auto_reply':
                    // Send a template/free-text reply on the matching
                    // conversation. We deliberately use InboxDispatcher
                    // (not raw Baileys) so the message lands in the same
                    // inbox_messages table the agent UI reads from.
                    $this->dispatchAutoReply($conv, $a);
                    break;
                case 'trigger_flow':
                    // Flow execution itself lives on the Node bot. We
                    // record the trigger intent on the conversation so
                    // the bot picks it up on the next tick + so the agent
                    // UI shows the flow that fired. The keyword-reply
                    // path uses the same `pending_flow_id` slot.
                    $flowId = (int) ($a['flow_id'] ?? 0);
                    if ($flowId) {
                        // Same workspace-scope guard as auto_reply — the
                        // rule author shouldn't be able to point at a flow
                        // outside their workspace.
                        $flow = Flow::query()
                            ->where('id', $flowId)
                            ->where('is_active', true)
                            ->where('workspace_id', $conv->workspace_id)
                            ->first();
                        if ($flow) {
                            $meta = is_array($conv->routing_meta) ? $conv->routing_meta : [];
                            $meta['pending_flow_id'] = $flow->id;
                            $meta['pending_flow_at'] = now()->toIso8601String();
                            $conv->forceFill(['routing_meta' => $meta])->save();
                            ConversationEvent::record(
                                $conv->id, $conv->workspace_id, null,
                                'flow_triggered', ['flow_id' => $flow->id, 'flow_name' => $flow->flow_name],
                                'rule'
                            );
                        }
                    }
                    break;
                case 'set_escalation':
                    // Schedule a deferred action — if no outbound reply
                    // lands within `minutes`, the inbox:escalate command
                    // will fire `then_action` on this conversation. Used
                    // for "auto-reassign unanswered after 5 minutes"
                    // patterns. then_action mirrors a normal RoutingEngine
                    // action dict (assign_team, assign_agent, set_priority, …).
                    $minutes = (int) ($a['minutes'] ?? 5);
                    $thenAction = is_array($a['then_action'] ?? null) ? $a['then_action'] : null;
                    if ($minutes > 0) {
                        $conv->forceFill([
                            'escalation_due_at' => now()->addMinutes($minutes),
                            'escalation_action' => $thenAction,
                        ])->save();
                    }
                    break;
            }
        }
    }

    /**
     * Inline auto-reply dispatch — picks a body source in priority order:
     *   1. `template_id` → render a WaTemplate's body verbatim
     *   2. `body`        → free text the operator typed in the rule
     * Recipient is the conversation's raw_jid (set by inbound webhook).
     * Mirrors the create-then-send pattern in AiAgentService so the message
     * shows up in the agent UI thread even if Node-side dispatch fails.
     * Failures are logged but never throw — auto-reply is a nice-to-have.
     */
    private function dispatchAutoReply(Conversation $conv, array $a): void
    {
        // Anti-spam: skip if conversation is flagged spam, in cooldown,
        // or currently flooding. Guard sets is_spam itself on flood.
        if (!app(AutoReplyGuard::class)->canAutoReply($conv)) {
            return;
        }

        try {
            $body = null;
            $meta = $conv->raw_jid ? ['target_jid' => $conv->raw_jid] : [];
            if (!empty($a['template_id'])) {
                // Scope to the conversation owner so a malicious rule
                // can't be hand-crafted to leak another workspace's
                // template body.
                $tpl = WaTemplate::query()
                    ->where('id', (int) $a['template_id'])
                    ->where('workspace_id', $conv->workspace_id)
                    ->first();
                if ($tpl) {
                    // template_body stays literal — RoutingEngine has no
                    // {{contact}} / {{order}} context to interpolate at
                    // this stage. Operators who need variables should
                    // trigger a Flow instead.
                    $body = (string) ($tpl->template_body ?? '');
                }
            }
            if (isset($tpl)) {
                $buttons = $tpl->buttons ?? [];
                if (is_array($buttons) && $buttons) {
                    $meta['buttons'] = $buttons;
                }
                if (!empty($tpl->footer)) $meta['footer'] = (string) $tpl->footer;
                if (!empty($tpl->header)) $meta['header'] = (string) $tpl->header;
                
                if (($tpl->template_type ?? '') === 'carousel') {
                    $meta['template_type'] = 'carousel';
                    if (is_array($tpl->carousel_data)) {
                        $meta['carousel_data'] = $tpl->carousel_data;
                    }
                }
            }

            if ($body === null || $body === '') {
                $body = (string) ($a['body'] ?? '');
            }
            if (trim($body) === '') return;

            $device = $conv->device_id
                ? \App\Models\Device::find($conv->device_id)
                : null;
            $fromNumber = $device
                ? preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number))
                : '';

            // Multi-device placeholder expansion — same {{device_phone}}
            // / {{device_name}} substitution the saved-reply picker
            // does on the client side. Keeps rule-fired auto-replies
            // visually identical to operator-typed replies.
            $devicePhoneFmt = $device
                ? trim('+' . ltrim((string) $device->country_code, '+') . ' ' . $device->phone_number)
                : '';
            $deviceName = $device ? (trim((string) $device->device_name) ?: ('Device #' . $device->id)) : '';
            $body = preg_replace('/\{\{\s*device_phone\s*\}\}/', $devicePhoneFmt, $body);
            $body = preg_replace('/\{\{\s*device_name\s*\}\}/',  $deviceName,     $body);
            $toNumber = $conv->raw_jid
                ? preg_replace('/\D+/', '', (string) $conv->raw_jid)
                : '';
            if ($toNumber === '') return;

            $msg = \App\Models\InboxMessage::create([
                'conversation_id' => $conv->id,
                'user_id'         => $conv->user_id,
                'direction'       => 'out',
                'from_number'     => $fromNumber,
                'to_number'       => $toNumber,
                'body'            => $body,
                'meta'            => array_merge($meta, ['origin' => 'routing_rule']),
                'status'          => 'pending',
                'sent_at'         => now(),
            ]);

            $conv->update([
                'preview'          => mb_substr($body, 0, 191),
                'last_message_at'  => now(),
                'last_outbound_at' => now(),
            ]);

            // Mark cooldown BEFORE dispatch so a Node-side failure
            // doesn't allow an immediate retry on the next inbound.
            app(AutoReplyGuard::class)->markReplied($conv);
            app(InboxDispatcher::class)->send($msg, $conv->platform ?? 'W');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                '[ROUTING] auto_reply dispatch failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Called by inbox:escalate Artisan command. Walks the deferred action
     * stored on the conversation and runs it through the regular execute()
     * pipeline, then clears the escalation slot.
     */
    public function applyEscalation(Conversation $conv): void
    {
        $action = $conv->escalation_action;
        if (!is_array($action) || empty($action['type'])) {
            $conv->forceFill(['escalation_due_at' => null, 'escalation_action' => null])->save();
            return;
        }
        $this->execute([$action], $conv);
        $conv->forceFill(['escalation_due_at' => null, 'escalation_action' => null])->save();
        ConversationEvent::record($conv->id, $conv->workspace_id, null, 'escalated', [
            'action' => $action,
        ], 'rule');
    }
}
