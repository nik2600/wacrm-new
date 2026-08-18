<?php

namespace App\Services\Woocommerce;

use App\Models\Device;
use App\Models\ScheduledMessage;
use App\Models\WoocommerceCartRecovery;
use App\Models\WoocommerceIntegration;
use App\Models\WoocommerceIntegrationEvent;
use App\Services\NodeSchedulerClient;
use Illuminate\Support\Facades\Log;

/**
 * Multi-step abandoned-cart recovery for WooCommerce.
 *
 * Step 1 fires immediately through the `checkout.created` automation (full
 * cart context). Steps 2 & 3 are *scheduled* follow-ups — configured as the
 * `cart/step2` / `cart/step3` automations with their own template + delay —
 * registered on the existing Node scheduler (no Laravel cron). When the
 * customer completes the order we cancel the pending steps.
 *
 * WooCommerce core webhooks don't emit a checkout-abandoned event, so the
 * `checkout.created` trigger is supplied by the WaDesk WooCommerce companion
 * plugin (which captures the phone at checkout). cancelOnOrder still works on
 * the standard order webhooks. WooCommerce twin of ShopifyCartService.
 */
class WoocommerceCartService
{
    public function __construct(private readonly NodeSchedulerClient $node) {}

    private static function phone(array $data): string
    {
        // E.164-normalise using the order's billing country — a local-format
        // checkout phone would otherwise schedule cart-recovery messages to an
        // unreachable number. See App\Support\Woo\WooPhone.
        return \App\Support\Woo\WooPhone::fromOrder($data) ?? '';
    }

    /** On checkout.created — schedule the delayed follow-up steps. */
    public function scheduleSequence(WoocommerceIntegration $integration, array $data): void
    {
        $phone = self::phone($data);
        if ($phone === '') return;

        $steps = WoocommerceIntegrationEvent::where('integration_id', $integration->id)
            ->whereIn('event_type', ['cart/step2', 'cart/step3'])
            ->where('is_active', true)->whereNotNull('template_id')
            ->orderBy('event_type')->get();
        if ($steps->isEmpty()) return;

        // Multi-engine: Baileys needs a connected device, WABA/Twilio route by
        // provider (no device) and use the configured template.
        [$engine, $deviceId, $fromNumber] = $this->resolveSender(
            (int) $integration->workspace_id, (int) $integration->user_id
        );
        if ($engine === null) { Log::info('[WC-CART] no connected sender — skip scheduling'); return; }

        $ids = [];
        foreach ($steps as $step) {
            $delay = max(60, (int) ($step->delay_seconds ?: 3600));
            $smId = $this->scheduleStep($integration, $engine, $deviceId, $fromNumber, $phone, (int) $step->template_id, $delay, $step->event_type);
            if ($smId) $ids[] = $smId;
        }
        if (!$ids) return;

        WoocommerceCartRecovery::create([
            'integration_id' => $integration->id,
            'workspace_id'   => $integration->workspace_id,
            'checkout_token' => (string) ($data['token'] ?? $data['cart_key'] ?? $data['id'] ?? ''),
            'customer_phone' => $phone,
            'customer_email' => $data['email'] ?? ($data['billing']['email'] ?? null),
            'scheduled_ids'  => $ids,
            'status'         => 'active',
        ]);
    }

    /**
     * Resolve the workspace's send engine + sender. Returns [engine, deviceId|null,
     * fromNumber] or [null, null, ''] if none connected. Baileys → connected
     * device; WABA/Twilio → primary provider-config row (no device).
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
    private function scheduleStep(WoocommerceIntegration $integration, string $engine, ?int $deviceId, string $fromNumber, string $phone, int $templateId, int $delay, string $label): ?int
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
            Log::warning('[WC-CART] schedule step failed: ' . $e->getMessage());
            return null;
        }
    }

    /** On a placed order — the cart converted: cancel the pending follow-ups. */
    public function cancelOnOrder(WoocommerceIntegration $integration, array $data): void
    {
        $phone = self::phone($data);
        if ($phone === '') return;

        $recs = WoocommerceCartRecovery::where('workspace_id', $integration->workspace_id)
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
