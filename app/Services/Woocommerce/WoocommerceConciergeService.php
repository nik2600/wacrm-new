<?php

namespace App\Services\Woocommerce;

use App\Models\Contact;
use App\Models\WaOrder;
use App\Models\WaTemplate;
use App\Models\WoocommerceIntegration;
use App\Models\WoocommerceIntegrationEvent;
use App\Models\WoocommerceIntegrationLog;
use App\Services\Commerce\CommerceEventNotifier;
use App\Services\Commerce\WoocommerceCheckoutLinkBuilder;
use App\Services\WhatsAppDispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Inbound self-serve concierge — three WhatsApp-native moonshots that the
 * incumbents don't ship on WooCommerce:
 *
 *   • One-tap reorder   — "reorder" → a fresh checkout link for the customer's
 *                          last order's items (minted via the Store API).
 *   • Review-gated coupon — a positive rating from a recent buyer unlocks the
 *                          merchant's reward template (with their coupon).
 *   • Loyalty / points  — "points" / "balance" → an instant order-count +
 *                          spend summary from the mirrored history.
 *
 * Each returns true once it has handled the message so the caller can stop.
 */
class WoocommerceConciergeService
{
    private const REORDER  = ['reorder', 're-order', 'order again', 'repeat order', 'repeat my order'];
    private const POINTS   = ['points', 'loyalty', 'my balance', 'reward balance', 'my points'];
    private const POSITIVE = ['5', '4', 'great', 'good', 'love it', 'loved it', 'excellent', 'awesome', 'amazing', 'perfect', '⭐', '👍'];
    private const EDIT_ADDR = ['change address', 'update address', 'edit address', 'wrong address', 'change my address', 'update my address', 'change shipping', 'edit my order', 'change delivery address'];

    /** Single entry point; tries each concierge intent in turn. */
    public function handleInbound(int $workspaceId, string $phone, string $text): bool
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if ($phone === '') return false;
        $t = strtolower(trim($text));
        if ($t === '') return false;

        $integration = WoocommerceIntegration::where('workspace_id', $workspaceId)->latest('id')->first();
        if (!$integration || !$integration->isConnected()) return false;

        // Guided address edit: if we asked this customer for a new address in
        // the last 15 min, THIS message is the address — apply it first.
        $editKey = $this->editKey($workspaceId, $phone);
        $pending = Cache::get($editKey);
        if ($pending) return $this->applyAddressEdit($integration, $phone, trim($text), $pending, $editKey);

        if ($this->matches($t, self::EDIT_ADDR)) return $this->requestAddressEdit($integration, $phone, $workspaceId);
        if ($this->matches($t, self::REORDER))   return $this->reorder($integration, $phone);
        if ($this->matches($t, self::POINTS))    return $this->points($integration, $phone);

        // Review reward is the loosest trigger, so it's last + tightly gated.
        return $this->reviewReward($integration, $phone, $t);
    }

    // -----------------------------------------------------------------
    // Two-way edit — guided, confirmation-based shipping-address change.
    // Only address (safe), only still-editable orders, 15-min window.
    // Quantity/variant edits are intentionally excluded (price/stock risk).
    // -----------------------------------------------------------------
    private function editKey(int $ws, string $phone): string
    {
        return "wc_addr_edit:{$ws}:{$phone}";
    }

    private function requestAddressEdit(WoocommerceIntegration $integration, string $phone, int $ws): bool
    {
        // Editable = not yet shipped/cancelled (mirror status new = pending/on-hold, paid = processing).
        $order = WaOrder::where('workspace_id', $ws)->where('source', 'woocommerce')
            ->where('customer_phone', $phone)->whereIn('status', ['new', 'paid'])
            ->latest('id')->first();
        if (!$order) {
            $this->reply($integration, $phone, "We couldn't find an order that's still editable — once an order ships the address is locked. Please contact support if you need help.");
            return true;
        }
        $name = $order->meta_json['name'] ?? ('#' . $order->woo_order_id);
        Cache::put($this->editKey($ws, $phone), ['woo_order_id' => $order->woo_order_id, 'order_name' => $name], now()->addMinutes(15));
        $this->reply($integration, $phone, "Sure — reply with your full updated shipping address for order {$name} in one message (flat/house, street, city, state, pincode).");
        $this->log($integration, 'order/edit-request', 'sent', $phone, ['order' => $name]);
        return true;
    }

    private function applyAddressEdit(WoocommerceIntegration $integration, string $phone, string $address, array $pending, string $key): bool
    {
        if (mb_strlen($address) < 6) {
            $this->reply($integration, $phone, "That looks too short — please send your full shipping address in one message.");
            return true; // keep the pending window open
        }
        $ok   = app(WoocommerceService::class)->updateOrder($integration, $pending['woo_order_id'], [
            'shipping' => ['address_1' => mb_substr($address, 0, 250)],
        ]);
        Cache::forget($key);
        $name = $pending['order_name'] ?? '';
        $this->reply($integration, $phone, $ok
            ? "Done — the shipping address for order {$name} has been updated. Thank you!"
            : "Sorry, we couldn't update order {$name} automatically. Our team has been notified — please contact support.");
        $this->log($integration, 'order/edit-apply', $ok ? 'sent' : 'failed', $phone, ['order' => $name]);
        return true;
    }

    private function reply(WoocommerceIntegration $integration, string $phone, string $body): void
    {
        try {
            app(WhatsAppDispatcher::class)->sendRaw(
                ['to_number' => $phone, 'body' => $body, 'workspace_id' => $integration->workspace_id],
                $integration->user_id, 'W'
            );
        } catch (\Throwable $e) {
            Log::warning('[WC-CONCIERGE] reply failed: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------
    // One-tap reorder
    // -----------------------------------------------------------------
    private function reorder(WoocommerceIntegration $integration, string $phone): bool
    {
        $last = WaOrder::where('workspace_id', $integration->workspace_id)
            ->where('source', 'woocommerce')->where('customer_phone', $phone)
            ->latest('id')->first();
        if (!$last) return false;

        $items = collect($last->items_json ?? [])
            ->map(fn ($i) => ['retailer_id' => (string) ($i['sku'] ?? ''), 'qty' => (int) ($i['qty'] ?? 1)])
            ->filter(fn ($i) => $i['retailer_id'] !== '')
            ->values()->all();
        if (!$items) return false;

        try {
            $r   = app(WoocommerceCheckoutLinkBuilder::class)->mint((int) $integration->id, $items, null);
            $url = (string) ($r['url'] ?? '');
            if ($url === '') return false;
            $body = "Here's your re-order — same items as last time. Tap to checkout:\n" . $url;
            app(WhatsAppDispatcher::class)->sendRaw(
                ['to_number' => $phone, 'body' => $body, 'workspace_id' => $integration->workspace_id],
                $integration->user_id, 'W'
            );
            $this->log($integration, 'reorder', 'sent', $phone, ['order' => $last->meta_json['name'] ?? null]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('[WC-CONCIERGE] reorder failed: ' . $e->getMessage());
            return false;
        }
    }

    // -----------------------------------------------------------------
    // Loyalty / points query
    // -----------------------------------------------------------------
    private function points(WoocommerceIntegration $integration, string $phone): bool
    {
        $q = WaOrder::where('workspace_id', $integration->workspace_id)
            ->where('source', 'woocommerce')->where('customer_phone', $phone);
        $count = (clone $q)->count();
        if ($count === 0) return false; // unknown customer — let other handlers try

        $spentMinor = (int) (clone $q)->sum('total_minor');
        $cur        = $integration->store_currency ?: '';

        // Points balance, if a points plugin has fed it onto the contact.
        $contact = Contact::where('workspace_id', $integration->workspace_id)
            ->get(['mobile', 'custom_attributes'])
            ->first(fn ($c) => preg_replace('/\D+/', '', (string) $c->mobile) === $phone);
        $points = $contact && is_array($contact->custom_attributes)
            ? ($contact->custom_attributes['points_balance'] ?? null) : null;

        $lines = [
            'Here\'s your account summary:',
            '• Orders: ' . $count,
            '• Total spent: ' . trim($cur . ' ' . number_format($spentMinor / 100, 2)),
        ];
        if ($points !== null) $lines[] = '• Points balance: ' . $points;
        try {
            app(WhatsAppDispatcher::class)->sendRaw(
                ['to_number' => $phone, 'body' => implode("\n", $lines), 'workspace_id' => $integration->workspace_id],
                $integration->user_id, 'W'
            );
            $this->log($integration, 'loyalty/query', 'sent', $phone, ['orders' => $count]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('[WC-CONCIERGE] points failed: ' . $e->getMessage());
            return false;
        }
    }

    // -----------------------------------------------------------------
    // Review-gated coupon
    // -----------------------------------------------------------------
    private function reviewReward(WoocommerceIntegration $integration, string $phone, string $t): bool
    {
        if (!$this->matches($t, self::POSITIVE)) return false;

        // Gate 1 — must be a recent buyer with a delivered order (last 30d).
        $recentDelivered = WaOrder::where('workspace_id', $integration->workspace_id)
            ->where('source', 'woocommerce')->where('customer_phone', $phone)
            ->where('status', 'shipped')->where('created_at', '>=', now()->subDays(30))
            ->exists();
        if (!$recentDelivered) return false;

        // Gate 2 — merchant configured a reward automation.
        $event = WoocommerceIntegrationEvent::where('integration_id', $integration->id)
            ->where('event_type', 'review/reward')->where('is_active', true)->first();
        if (!$event || !$event->template_id) return false;

        // Gate 3 — don't reward the same phone twice within 30 days.
        $alreadyRewarded = WoocommerceIntegrationLog::where('integration_id', $integration->id)
            ->where('event_type', 'review/reward')->where('recipient', $phone)
            ->where('created_at', '>=', now()->subDays(30))->exists();
        if ($alreadyRewarded) return false;

        $tpl = WaTemplate::where('workspace_id', $integration->workspace_id)->find($event->template_id);
        if (!$tpl) return false;

        $ctx = [
            'name' => 'there', 'store_name' => (string) ($integration->store_name ?: $integration->store_url),
            '_positional' => ['there', $integration->store_name ?: $integration->store_url, ''],
        ];
        if (is_array($event->var_map) && $event->var_map) {
            $ctx['_positional'] = array_map(fn ($f) => (string) ($ctx[$f] ?? ''), $event->var_map);
        }
        $r = app(CommerceEventNotifier::class)->notify($integration->workspace_id, $integration->user_id, $phone, $tpl, $ctx);
        $this->log($integration, 'review/reward', ($r['ok'] ?? false) ? 'sent' : 'failed', $phone, ['rating' => $t], $r);
        return true;
    }

    // -----------------------------------------------------------------
    private function matches(string $t, array $keywords): bool
    {
        foreach ($keywords as $k) {
            if ($t === $k || str_contains($t, $k)) return true;
        }
        return false;
    }

    private function log(WoocommerceIntegration $i, string $type, string $status, string $phone, array $payload, $resp = null): void
    {
        WoocommerceIntegrationLog::create([
            'integration_id' => $i->id,
            'event_type'     => $type,
            'status'         => $status,
            'recipient'      => $phone,
            'payload'        => $payload,
            'response'       => $resp,
            'created_at'     => now(),
        ]);
    }
}
