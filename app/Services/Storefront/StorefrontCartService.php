<?php

namespace App\Services\Storefront;

use App\Models\Device;
use App\Models\ScheduledMessage;
use App\Models\StorefrontCartRecovery;
use App\Models\WaProduct;
use App\Models\WaStorefront;
use App\Services\NodeSchedulerClient;
use App\Services\WorkspaceEngine;
use Illuminate\Support\Facades\Log;

/**
 * S3 — storefront abandoned-cart recovery. Mirrors WoocommerceCartService:
 * registers a one-off WhatsApp nudge on the existing Node scheduler (no
 * Laravel cron) when a buyer enters their phone at checkout but doesn't
 * complete, and cancels it the moment they place the order.
 *
 * Off unless the merchant enables it (settings_json.cart_recovery_enabled).
 * The nudge is a plain-text message (no WABA template needed) with a link
 * back to the shop, so it works on the Unofficial API engine too.
 */
class StorefrontCartService
{
    public function __construct(private readonly NodeSchedulerClient $node) {}

    /** Schedule (or refresh) the recovery nudge for an abandoned cart. */
    public function scheduleRecovery(WaStorefront $sf, string $phone, ?string $name, array $items, int $subtotalMinor): bool
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if (strlen($phone) < 7) return false;

        $settings = is_array($sf->settings_json) ? $sf->settings_json : [];
        if (($settings['cart_recovery_enabled'] ?? false) !== true) return false;

        // One active recovery per phone+shop — don't double-schedule.
        if (StorefrontCartRecovery::where('storefront_id', $sf->id)
            ->where('customer_phone', $phone)->where('status', 'active')->exists()) {
            return false;
        }

        // Multi-engine sender: Baileys → connected device; WABA/Twilio → the
        // primary provider-config row (no device, Node routes by provider).
        // (Proactive plain-text recovery delivers on Baileys; on WABA it only
        // sends inside the 24h customer-service window — a Meta policy limit.)
        $engine     = WorkspaceEngine::for((int) $sf->workspace_id);
        $deviceId   = null;
        $fromNumber = '';
        $userId     = $sf->workspace?->owner_user_id;
        if ($engine === WorkspaceEngine::ENGINE_BAILEYS) {
            $device = Device::query()->forWorkspace($sf->workspace_id)
                ->where('status', 'connected')->orderByDesc('active')->orderByDesc('id')->first();
            if (!$device) { Log::info('[SF-CART] no connected Baileys device — skip recovery'); return false; }
            $deviceId   = $device->id;
            $fromNumber = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number));
            $userId     = $device->user_id ?? $device->assigned_user_id ?? $userId;
        } else {
            $cfg = \App\Models\WaProviderConfig::query()->primaryForWorkspace((int) $sf->workspace_id)
                ->where('provider', $engine)->first();
            if (!$cfg) { Log::info('[SF-CART] no ' . $engine . ' sender configured — skip recovery'); return false; }
            $fromNumber = preg_replace('/\D+/', '', (string) $cfg->phone_number);
        }

        $delayMin = max(5, (int) ($settings['cart_recovery_delay_min'] ?? 30));
        $shopName = $sf->shop_name ?: ($sf->workspace?->name ?: 'our store');
        $shopUrl  = $sf->custom_domain_verified && $sf->custom_domain
            ? 'https://' . $sf->custom_domain
            : url('/s/' . $sf->slug);
        $firstName = trim((string) ($name ?: '')) ?: 'there';

        $message = (string) ($settings['cart_recovery_message'] ?? '')
            ?: "Hi {$firstName}! You left some items in your cart at {$shopName}. Complete your order here: {$shopUrl}";
        // Light token substitution so a custom message can use placeholders.
        $message = strtr($message, [
            '{name}' => $firstName, '{shop}' => $shopName, '{url}' => $shopUrl,
            '{total}' => WaProduct::formatCurrency($subtotalMinor, $sf->currency_code ?: 'INR'),
        ]);

        $when = now()->addMinutes($delayMin);
        try {
            $row = ScheduledMessage::create([
                'user_id'          => $userId,
                'workspace_id'     => $sf->workspace_id,
                'provider'         => $engine,
                'device_id'        => $deviceId,
                'schedule_name'    => 'Cart recovery · ' . $shopName,
                'message_content'  => $message,
                'template_type'    => 'plain',
                'schedule_type'    => 'later',
                'send_date'        => $when->format('Y-m-d'),
                'send_time'        => $when->format('H:i'),
                'scheduled_time'   => $when,
                'timezone'         => config('app.timezone', 'UTC'),
                'recipient_type'   => 'number',
                'target_numbers'   => [$phone],
                'total_recipients' => 1,
                'from_number'      => $fromNumber,
                'status'           => 'scheduled',
                'next_run_at'      => $when,
            ]);
            $nodeId = $this->node->registerOneOff($row, null);
            if ($nodeId) $row->forceFill(['node_schedule_id' => $nodeId])->save();

            StorefrontCartRecovery::create([
                'workspace_id'   => $sf->workspace_id,
                'storefront_id'  => $sf->id,
                'customer_phone' => $phone,
                'customer_name'  => $name ?: null,
                'items_json'     => $items,
                'subtotal_minor' => $subtotalMinor,
                'currency_code'  => $sf->currency_code ?: 'INR',
                'scheduled_ids'  => [$row->id],
                'status'         => 'active',
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('[SF-CART] schedule failed: ' . $e->getMessage());
            return false;
        }
    }

    /** The cart converted — cancel any pending nudge for this phone+shop. */
    public function cancelOnOrder(WaStorefront $sf, string $phone): void
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if ($phone === '') return;

        $recs = StorefrontCartRecovery::where('storefront_id', $sf->id)
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
