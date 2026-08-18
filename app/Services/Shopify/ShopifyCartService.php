<?php

namespace App\Services\Shopify;

use App\Models\Device;
use App\Models\ScheduledMessage;
use App\Models\ShopifyCartRecovery;
use App\Models\ShopifyIntegration;
use App\Models\ShopifyIntegrationEvent;
use App\Services\NodeSchedulerClient;
use Illuminate\Support\Facades\Log;

/**
 * Multi-step abandoned-cart recovery.
 *
 * Step 1 fires immediately through the normal `checkouts/create` automation
 * (full cart context incl. {{checkout_url}}). Steps 2 & 3 are *scheduled*
 * follow-ups — configured as the `cart/step2` / `cart/step3` automations with
 * their own template + delay — registered on the existing Node scheduler
 * (no Laravel cron). When the customer completes the order we cancel the
 * pending steps so they never receive a "you forgot something" after buying.
 */
class ShopifyCartService
{
    public function __construct(private readonly NodeSchedulerClient $node) {}

    private static function phone(array $data): string
    {
        $p = $data['customer']['phone'] ?? $data['phone'] ?? ($data['shipping_address']['phone'] ?? ($data['billing_address']['phone'] ?? ''));
        return preg_replace('/\D+/', '', (string) $p);
    }

    /** On checkouts/create — schedule the delayed follow-up steps. */
    public function scheduleSequence(ShopifyIntegration $integration, array $data): void
    {
        $phone = self::phone($data);
        if ($phone === '') return;

        // The follow-up steps the merchant configured (step 2, step 3…).
        $steps = ShopifyIntegrationEvent::where('integration_id', $integration->id)
            ->whereIn('event_type', ['cart/step2', 'cart/step3'])
            ->where('is_active', true)->whereNotNull('template_id')
            ->orderBy('event_type')->get();
        if ($steps->isEmpty()) return;

        // Multi-engine: cart recovery sends on the workspace's engine — Baileys
        // needs a connected device, WABA/Twilio route by provider (no device) and
        // use the configured template. Resolve the sender once for all steps.
        [$engine, $deviceId, $fromNumber] = $this->resolveSender(
            (int) $integration->workspace_id, (int) $integration->user_id
        );
        if ($engine === null) { Log::info('[CART] no connected sender — skip scheduling'); return; }

        $ids = [];
        foreach ($steps as $step) {
            $delay = max(60, (int) ($step->delay_seconds ?: 3600)); // default 1h
            $smId = $this->scheduleStep($integration, $engine, $deviceId, $fromNumber, $phone, (int) $step->template_id, $delay, $step->event_type);
            if ($smId) $ids[] = $smId;
        }
        if (!$ids) return;

        ShopifyCartRecovery::create([
            'integration_id' => $integration->id,
            'workspace_id'   => $integration->workspace_id,
            'checkout_token' => (string) ($data['token'] ?? $data['id'] ?? ''),
            'customer_phone' => $phone,
            'customer_email' => $data['email'] ?? ($data['customer']['email'] ?? null),
            'scheduled_ids'  => $ids,
            'status'         => 'active',
        ]);
    }

    /**
     * Resolve the workspace's send engine + sender for cart recovery.
     * Returns [engine, deviceId|null, fromNumber] — or [null, null, ''] if no
     * sender is connected. Baileys → connected device; WABA/Twilio → the primary
     * provider-config row (no device; Node routes by provider).
     */
    private function resolveSender(int $wsId, ?int $userId): array
    {
        $engine = \App\Services\WorkspaceEngine::for($wsId);
        if ($engine === \App\Services\WorkspaceEngine::ENGINE_BAILEYS) {
            $device = Device::query()
                ->forWorkspace($wsId, $userId)
                ->where('status', 'connected')->orderByDesc('id')->first();
            if (!$device) return [null, null, ''];
            return [$engine, (int) $device->id, preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number))];
        }
        $cfg = \App\Models\WaProviderConfig::query()
            ->primaryForWorkspace($wsId)->where('provider', $engine)->first();
        if (!$cfg) return [null, null, ''];
        return [$engine, null, preg_replace('/\D+/', '', (string) $cfg->phone_number)];
    }

    /** Create + register ONE scheduled follow-up. Returns the ScheduledMessage id. */
    private function scheduleStep(ShopifyIntegration $integration, string $engine, ?int $deviceId, string $fromNumber, string $phone, int $templateId, int $delay, string $label): ?int
    {
        try {
            $when = now()->addSeconds($delay);
            $row  = ScheduledMessage::create([
                'user_id'         => $integration->user_id,
                'workspace_id'    => $integration->workspace_id,
                'provider'        => $engine,
                'device_id'       => $deviceId,
                'schedule_name'   => 'Cart recovery · ' . $label,
                'template_id'     => $templateId,
                'template_type'   => 'standard',
                'schedule_type'   => 'later',
                'send_date'       => $when->format('Y-m-d'),
                'send_time'       => $when->format('H:i'),
                'scheduled_time'  => $when,
                'timezone'        => config('app.timezone', 'UTC'),
                'recipient_type'  => 'number',
                'target_numbers'  => [$phone],
                'total_recipients'=> 1,
                'from_number'     => $fromNumber,
                'status'          => 'scheduled',
                'next_run_at'     => $when,
            ]);
            $nodeId = $this->node->registerOneOff($row, null);
            if ($nodeId) $row->forceFill(['node_schedule_id' => $nodeId])->save();
            return $row->id;
        } catch (\Throwable $e) {
            Log::warning('[CART] schedule step failed: ' . $e->getMessage());
            return null;
        }
    }

    /** On orders/create — the cart converted: cancel the pending follow-ups. */
    public function cancelOnOrder(ShopifyIntegration $integration, array $data): void
    {
        $phone = self::phone($data);
        if ($phone === '') return;

        $recs = ShopifyCartRecovery::where('workspace_id', $integration->workspace_id)
            ->where('customer_phone', $phone)->where('status', 'active')->get();
        foreach ($recs as $rec) {
            foreach ((array) $rec->scheduled_ids as $sid) {
                $sm = ScheduledMessage::find($sid);
                if ($sm && $sm->status === 'scheduled') {
                    try { $this->node->cancel($sm); } catch (\Throwable $e) {}
                    $sm->forceFill(['status' => 'cancelled'])->save();
                }
            }
            $rec->update(['status' => 'recovered']);
        }
    }
}
