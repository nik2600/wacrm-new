<?php

namespace App\Services\Flow;

use App\Models\Contact;
use App\Models\Device;
use App\Models\Flow;
use App\Models\FlowSubscriber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Glues an audience trigger (tag attach / group join / manual enrol) to
 * Node's flow runtime. Replaces the old DripEnrollmentService — same
 * public surface, but reads `flows.trigger_kind/value/device_id` instead
 * of a separate `drip_campaigns` row.
 *
 *   - enroll(contact, flow)          → idempotent + POSTs to Node
 *   - onTagAdded(contact, tagId)     → auto-enroll into tag_added flows
 *   - onConversationTagged(conv,id)  → resolves JID → contact → onTagAdded
 *   - onGroupJoin(contact, groupId)  → auto-enroll into group_join flows
 *
 * Step advancement (delays + branching + messages) lives on the Node side
 * via the existing executeFlowNode runtime. No new Laravel cron.
 */
class FlowEnrollmentService
{
    /**
     * Enroll a contact into a flow. Same idempotency contract as before:
     * the UNIQUE(flow_id, contact_id) constraint blocks double-enrollment;
     * already-active subscribers are no-ops; already-failed ones get the
     * Node POST retried.
     */
    public function enroll(Contact $contact, Flow $flow, array $variables = []): FlowSubscriber
    {
        if (!$this->contactBelongsToWorkspace($contact, (int) $flow->workspace_id)) {
            throw new \RuntimeException('Contact not in flow workspace.');
        }

        $sub = FlowSubscriber::firstOrCreate(
            ['flow_id' => $flow->id, 'contact_id' => $contact->id],
            ['enrolled_at' => now(), 'status' => 'active'],
        );

        if ($sub->wasRecentlyCreated || $sub->status === 'failed') {
            $sub->update(['status' => 'active', 'failed_at' => null, 'failure_reason' => null]);
            // Meta Business Agent coexistence — this is the ONE place every flow
            // trigger (tag / group / new-contact / opt-in / keyword / commerce /
            // manual) actually fires + sends. When Meta's agent is fronting the
            // workspace, record the subscriber but DON'T launch (no second reply).
            $ws = \App\Models\Workspace::find((int) $flow->workspace_id);
            if ($ws && $ws->suppressesOurAutoReply()) {
                Log::info('[FLOW-ENROLL] launch suppressed — Meta Business Agent is fronting this workspace', [
                    'flow_id' => $flow->id, 'contact_id' => $contact->id, 'mode' => $ws->ai_responder_mode,
                ]);
            } else {
                $this->launchFlow($contact, $flow, $sub, $variables);
            }
        }

        return $sub;
    }

    public function onTagAdded(Contact $contact, int $tagId): void
    {
        try {
            foreach ($this->flowsForContact($contact, 'tag_added', $tagId) as $f) {
                try { $this->enroll($contact, $f); }
                catch (\Throwable $e) {
                    Log::warning('[FLOW-ENROLL] enroll failed', [
                        'contact_id' => $contact->id, 'flow_id' => $f->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[FLOW-ENROLL] onTagAdded failed: ' . $e->getMessage());
        }
    }

    /**
     * Conversation-side helper — resolves the JID to a Contact within the
     * conversation's workspace (encrypted-column scan-and-compare) and
     * fires onTagAdded. Called from TeamInboxController + RoutingEngine.
     */
    public function onConversationTagged(\App\Models\Conversation $conv, int $tagId): void
    {
        try {
            $jidDigits = preg_replace('/\D+/', '', (string) $conv->raw_jid);
            if ($jidDigits === '') return;

            $q = Contact::query();
            if ($conv->workspace_id) {
                $q->where('workspace_id', $conv->workspace_id);
            } elseif ($conv->user_id) {
                $q->where('user_id', $conv->user_id);
            } else {
                return;
            }
            $contact = $q->get()->first(function ($c) use ($jidDigits) {
                $stored = preg_replace('/\D+/', '', (string) ($c->country_code . $c->mobile));
                return $stored !== '' && $stored === $jidDigits;
            });
            if ($contact) $this->onTagAdded($contact, $tagId);
        } catch (\Throwable $e) {
            Log::warning('[FLOW-ENROLL] onConversationTagged failed: ' . $e->getMessage());
        }
    }

    public function onGroupJoin(Contact $contact, int $groupId): void
    {
        try {
            foreach ($this->flowsForContact($contact, 'group_join', $groupId) as $f) {
                try { $this->enroll($contact, $f); } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            Log::warning('[FLOW-ENROLL] onGroupJoin failed: ' . $e->getMessage());
        }
    }

    /** New-contact trigger (Contact::created). trigger_value unused (0). */
    public function onContactCreated(Contact $contact): void
    {
        $this->fireForContact($contact, 'contact_created', 0);
    }

    /** Re-subscribe trigger (is_unsubscribed true→false). */
    public function onOptIn(Contact $contact): void
    {
        $this->fireForContact($contact, 'opt_in', 0);
    }

    /**
     * Commerce trigger — a new order. Resolves the order's customer phone to a
     * saved Contact in the order's workspace, then enrolls into order_placed
     * flows. No saved contact → nothing to message, so skip.
     */
    public function onOrderPlaced(\App\Models\WaOrder $order): void
    {
        try {
            $contact = $this->resolveContactByPhone((int) $order->workspace_id, (string) $order->customer_phone);
            if (!$contact) return;
            $vars = $this->orderFlowVariables($order);
            foreach ($this->flowsForWorkspace((int) $order->workspace_id, 'order_placed', 0) as $f) {
                try { $this->enroll($contact, $f, $vars); } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            Log::warning('[FLOW-ENROLL] onOrderPlaced failed: ' . $e->getMessage());
        }
    }

    /**
     * Merge tags an order-placed flow can use — notably {{invoice_url}} (the
     * store's PDF invoice when its meta carries one, else the order/pay page),
     * so a flow can SEND the invoice for THIS order via a Send-media/document
     * node. Empty values are dropped so a missing var resolves to blank.
     */
    private function orderFlowVariables(\App\Models\WaOrder $order): array
    {
        $meta     = is_array($order->meta_json) ? $order->meta_json : [];
        $currency = (string) ($order->currency_code ?? '');
        $total    = number_format(((int) $order->total_minor) / 100, 2);
        $number   = (string) ($meta['order_number'] ?? $order->id);
        $invoice  = (string) ($meta['invoice_url'] ?? ($order->payment_link ?? ''));

        return array_filter([
            'name'         => (string) ($order->customer_name ?? ''),
            'first_name'   => (explode(' ', trim((string) ($order->customer_name ?? '')))[0] ?? ''),
            'email'        => (string) ($order->customer_email ?? ''),
            'order_number' => $number,
            'order_name'   => '#' . $number,
            'total'        => trim($total . ' ' . $currency),
            'total_price'  => $total,
            'currency'     => $currency,
            'status'       => (string) ($order->status ?? ''),
            'invoice_url'  => $invoice,
            'order_url'    => (string) ($meta['order_url'] ?? ''),
            'payment_link' => (string) ($order->payment_link ?? ''),
        ], fn ($v) => $v !== '' && $v !== null);
    }

    /**
     * Sales Pipeline bridge — a deal moved into a stage. Enrolls the deal's
     * linked contact into deal_stage_changed flows whose trigger_value matches
     * the destination stage_id. The unique combo Wati/AiSensy don't have.
     */
    public function onDealStageChanged(\App\Models\Deal $deal): void
    {
        try {
            if (!$deal->contact_id) return;
            $contact = Contact::find($deal->contact_id);
            if (!$contact) return;
            foreach ($this->flowsForWorkspace((int) $deal->workspace_id, 'deal_stage_changed', (int) $deal->stage_id) as $f) {
                try { $this->enroll($contact, $f); } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            Log::warning('[FLOW-ENROLL] onDealStageChanged failed: ' . $e->getMessage());
        }
    }

    /**
     * Inbound-condition triggers — launch `away` / `out_of_hours` flows on ANY
     * inbound message while the workspace is in Away mode / outside its Business
     * Hours. Called from every inbound path (WABA/Twilio/IG dispatcher + Baileys
     * lookup). Enrolment is idempotent (UNIQUE flow_id+contact_id), so each
     * contact launches the flow ONCE — no re-fire on every message.
     *
     * Fast path: the common case (in-hours, not away) resolves the workspace's
     * two booleans and returns BEFORE any flow query or contact scan.
     */
    public function onInboundMessage(int $workspaceId, string $contactPhone): void
    {
        try {
            if ($workspaceId <= 0) return;
            $ws = \App\Models\Workspace::find($workspaceId);
            if (!$ws) return;

            $kinds = $this->inboundFlowKinds($ws);
            if (empty($kinds)) return; // in-hours + not away → nothing to fire

            // Only now (rare) do we pay for flow lookup + contact resolution.
            $flows = collect();
            foreach ($kinds as $kind) {
                $flows = $flows->merge($this->flowsForWorkspace($workspaceId, $kind, 0));
            }
            if ($flows->isEmpty()) return;

            $contact = $this->resolveContactByPhone($workspaceId, $contactPhone)
                ?? $this->makeInboundContact($workspaceId, $contactPhone);
            if (!$contact) return;

            foreach ($flows as $f) {
                try { $this->enroll($contact, $f); }
                catch (\Throwable $e) {
                    Log::warning('[FLOW-ENROLL] inbound-trigger enroll failed', [
                        'flow_id' => $f->id, 'kind' => $f->trigger_kind, 'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[FLOW-ENROLL] onInboundMessage failed: ' . $e->getMessage());
        }
    }

    /**
     * Which inbound-condition trigger kinds are ELIGIBLE right now for a
     * workspace: 'away' when Away mode is on, 'out_of_hours' when outside the
     * workspace's Business Hours. Pure (no side effects) so it is unit-testable
     * without launching a flow. Away reuses AutoResponderEvaluator's source of
     * truth (workspaces.inbox_away); out_of_hours reuses Workspace::isOutsideBusinessHours().
     */
    public function inboundFlowKinds(\App\Models\Workspace $ws): array
    {
        $kinds = [];
        if ((bool) $ws->inbox_away) {
            $kinds[] = 'away';
        }
        try {
            if ($ws->isOutsideBusinessHours()) {
                $kinds[] = 'out_of_hours';
            }
        } catch (\Throwable $e) { /* no business_hours config → treated as open */ }
        return $kinds;
    }

    /**
     * Create a minimal Contact for an inbound phone that has no saved contact yet,
     * so an away/out-of-hours flow can enrol + run. Mirrors the keyword-flow
     * path's findOrMakeContact; the resolve-scan already proved no duplicate.
     */
    private function makeInboundContact(int $workspaceId, string $phone): ?Contact
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '' || !$workspaceId) return null;
        try {
            $owner = \App\Models\Workspace::find($workspaceId)?->owner_user_id;
            return Contact::create([
                'workspace_id' => $workspaceId,
                'user_id'      => $owner,
                'name'         => $digits,
                'mobile'       => $digits,
            ]);
        } catch (\Throwable $e) {
            Log::debug('[FLOW-ENROLL] makeInboundContact failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Shared body for contact-keyed event triggers. */
    private function fireForContact(Contact $contact, string $kind, int $value): void
    {
        try {
            foreach ($this->flowsForContact($contact, $kind, $value) as $f) {
                try { $this->enroll($contact, $f); } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            Log::warning("[FLOW-ENROLL] {$kind} failed: " . $e->getMessage());
        }
    }

    /** Find the saved contact whose stored number matches a raw phone. */
    private function resolveContactByPhone(int $workspaceId, string $phone): ?Contact
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '' || !$workspaceId) return null;
        return Contact::where('workspace_id', $workspaceId)->get()->first(function ($c) use ($digits) {
            $stored = preg_replace('/\D+/', '', (string) ($c->country_code . $c->mobile));
            return $stored !== '' && $stored === $digits;
        });
    }

    /** Active+published flows in a workspace matching kind+value. */
    private function flowsForWorkspace(int $workspaceId, string $triggerKind, int $triggerValue): \Illuminate\Support\Collection
    {
        if (!$workspaceId) return collect();
        return Flow::query()
            ->where('is_active', true)
            ->where('is_published', true)
            ->where('trigger_kind', $triggerKind)
            ->where('trigger_value', $triggerValue)
            ->where('workspace_id', $workspaceId)
            ->get();
    }

    /**
     * Resolve active flows for the contact's workspace(s) that match the
     * given trigger kind + value. Contacts are workspace-scoped via
     * owner.workspaces() so a teammate's contact still triggers an
     * admin-authored flow.
     */
    private function flowsForContact(Contact $contact, string $triggerKind, int $triggerValue): \Illuminate\Support\Collection
    {
        $owner = \App\Models\User::find($contact->user_id);
        if (!$owner) return collect();

        $wsIds = $owner->workspaces()->pluck('workspaces.id');
        if ($owner->current_workspace_id && !$wsIds->contains($owner->current_workspace_id)) {
            $wsIds->push($owner->current_workspace_id);
        }
        if ($wsIds->isEmpty()) return collect();

        return Flow::query()
            ->where('is_active', true)
            ->where('is_published', true)
            ->where('trigger_kind', $triggerKind)
            ->where('trigger_value', $triggerValue)
            ->whereIn('workspace_id', $wsIds)
            ->get();
    }

    /**
     * POST to Node to spawn the flow session. Node's existing flow runtime
     * (executeFlowNode → executeTimeDelayNode) owns delay timing from here.
     */
    private function launchFlow(Contact $contact, Flow $flow, FlowSubscriber $sub, array $variables = []): void
    {
        // Multi-engine: resolve the SENDER PHONE for the workspace's active
        // engine. Baileys → paired device; WABA/Twilio → connected provider
        // config number. The Node flow runtime resolves the engine + creds
        // from this phone (GET /api/whatsapp-settings?phone=…) and each node
        // branches WABA/Twilio vs Baileys accordingly — so a WABA/Twilio-only
        // workspace (no paired Baileys device) can run flows instead of failing
        // on "no connected device".
        $engine = \App\Services\WorkspaceEngine::for($flow->workspace_id);
        $devicePhone = '';
        if ($engine === \App\Services\WorkspaceEngine::ENGINE_BAILEYS) {
            $device = $this->resolveDevice($flow);
            if ($device) {
                $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number));
            }
        } else {
            $cfg = \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $flow->workspace_id)
                ->where('provider', $engine)
                ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                ->orderByDesc('is_primary')
                ->orderByDesc('id')
                ->first();
            $devicePhone = $cfg ? preg_replace('/\D+/', '', (string) $cfg->phone_number) : '';
        }
        if ($devicePhone === '') {
            $sub->update(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => 'No connected ' . $engine . ' sender for this workspace']);
            return;
        }

        $recipient = preg_replace('/\D+/', '', (string) (($contact->country_code ?? '') . $contact->mobile));
        if ($recipient === '') {
            $sub->update(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => 'Contact has no usable mobile number']);
            return;
        }

        // Canonical Laravel→Node URL — matches WaCampaignsController +
        // WaCallingWebhookController. (The old service used NODE_BRIDGE_URL,
        // a one-off that broke installs that only set SERVER_URL.)
        $nodeUrl = (string) (\App\Models\SystemSetting::get('baileys_server_url', '') ?: env('SERVER_URL', ''));
        if ($nodeUrl === '') {
            $sub->update(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => 'Node bridge URL not configured (baileys_server_url / SERVER_URL)']);
            return;
        }

        try {
            $r = Http::withHeaders([
                    'X-Node-Token' => node_token(),
                ])
                ->timeout(15)
                ->acceptJson()
                ->post(rtrim($nodeUrl, '/') . '/api/flow/start/' . rawurlencode($devicePhone), [
                    'flowId'            => $flow->id,
                    'targetPhoneNumber' => $recipient,
                    'flowSubscriberId'  => $sub->id,
                    // Multi-engine hint — the flow runtime also resolves the
                    // engine from the sender phone's settings, but forwarding
                    // it keeps Node's routing explicit for WABA/Twilio flows.
                    'provider'          => $engine,
                    // Personalization — lets {{name}}/{{first_name}}/{{email}}
                    // resolve in message nodes for audience/event triggers.
                    'name'              => trim((string) ($contact->name ?? '')),
                    'first_name'        => (explode(' ', trim((string) ($contact->name ?? '')))[0] ?? ''),
                    'email'             => (string) ($contact->email ?? ''),
                    // Event variables ({{invoice_url}}, {{order_number}}, …) so an
                    // order-placed flow can send THIS order's invoice. Empty for
                    // keyword/tag/group triggers — harmless.
                    'variables'         => (object) $variables,
                ]);

            if (!$r->successful()) {
                $sub->update([
                    'status'         => 'failed',
                    'failed_at'      => now(),
                    'failure_reason' => 'Node ' . $r->status() . ': ' . mb_substr((string) $r->body(), 0, 150),
                ]);
            }
        } catch (\Throwable $e) {
            $sub->update([
                'status'         => 'failed',
                'failed_at'      => now(),
                'failure_reason' => 'Node unreachable: ' . mb_substr($e->getMessage(), 0, 150),
            ]);
        }
    }

    private function resolveDevice(Flow $flow): ?Device
    {
        // trigger_device_id is POLYMORPHIC — it points at `devices` only when
        // the flow's provider is 'unofficial'. For waba/twilio it names a
        // wa_provider_configs row and for instagram an instagram_accounts row;
        // those ids overlap with real `devices` ids, so an unguarded
        // Device::find() would happily return a DIFFERENT, unrelated number.
        // NOTE the key is 'baileys' — "Unofficial API" is only the display
        // label. Every existing row in this table uses 'baileys'/'twilio'.
        if ($flow->trigger_device_id && in_array((string) $flow->provider, [\App\Services\WorkspaceEngine::ENGINE_BAILEYS, ''], true)) {
            $d = Device::find($flow->trigger_device_id);
            if ($d && $d->active && $d->workspace_id === $flow->workspace_id) return $d;
        }
        return Device::query()
            ->where('workspace_id', $flow->workspace_id)
            ->where('active', true)
            ->orderBy('id')
            ->first();
    }

    private function contactBelongsToWorkspace(Contact $contact, int $workspaceId): bool
    {
        if ($contact->workspace_id) {
            return (int) $contact->workspace_id === $workspaceId;
        }
        $contactUser = \App\Models\User::find($contact->user_id);
        if (!$contactUser) return false;
        if ($contactUser->current_workspace_id === $workspaceId) return true;
        return $contactUser->workspaces()->where('workspaces.id', $workspaceId)->exists();
    }
}
