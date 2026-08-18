<?php

namespace App\Services\Hubspot;

use App\Models\HubspotIntegration;
use App\Models\WaOrder;

/**
 * Turns WaDesk commerce events into HubSpot CRM records. This is the
 * piece that makes the connection do something: the OAuth handshake only
 * stores tokens — without a caller, pushDeal() was dead code.
 *
 * syncOrder() is fired from a queued job (App\Jobs\SyncOrderToHubspot)
 * on WaOrder::created, so a slow HubSpot round-trip never blocks the
 * storefront checkout response. It is a no-op (returns false) when the
 * workspace has no active HubSpot integration, so it is safe to call
 * for every order regardless of whether HubSpot is connected.
 */
class HubspotSyncService
{
    public function __construct(private readonly HubspotService $hubspot) {}

    /** The active HubSpot integration for a workspace, or null. */
    public function integrationFor(int $workspaceId): ?HubspotIntegration
    {
        return HubspotIntegration::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->latest('id')
            ->first();
    }

    /** Does this workspace have a live HubSpot link? (cheap existence check) */
    public function isConnected(int $workspaceId): bool
    {
        return HubspotIntegration::where('workspace_id', $workspaceId)
            ->where('status', 'active')->exists();
    }

    /**
     * Push one order to HubSpot as a contact (deduped by email) + an
     * associated deal. Returns the pushDeal() result, or false when the
     * workspace isn't connected / the order has no usable customer handle.
     */
    public function syncOrder(WaOrder $order): array|false
    {
        $integration = $this->integrationFor((int) $order->workspace_id);
        if (!$integration) return false;

        $name  = trim((string) ($order->customer_name ?? ''));
        $email = trim((string) ($order->customer_email ?? ''));
        $phone = preg_replace('/\D+/', '', (string) ($order->customer_phone ?? ''));

        // Need at least an email (dedupe key) or a phone to be worth syncing.
        if ($email === '' && $phone === '') return false;

        [$first, $last] = $this->splitName($name);

        $contactProps = array_filter([
            'email'     => $email ?: null,
            'firstname' => $first ?: null,
            'lastname'  => $last ?: null,
            'phone'     => $phone ? ('+' . $phone) : null,
            // Lifecycle attribution (#9). lifecyclestage is a standard HubSpot
            // property on every portal, so this never 400s. We only ever push
            // it FORWARD — "customer" on a paid/shipped order — and otherwise
            // leave HubSpot's existing value untouched, so a returning buyer is
            // never downgraded back to a lead.
            'lifecyclestage' => in_array($order->status, ['paid', 'shipped'], true) ? 'customer' : null,
        ], fn ($v) => $v !== null && $v !== '');

        $dealProps = $this->dealProps($order);

        $res = $this->hubspot->pushDeal($integration, $contactProps, $dealProps);

        // Remember the HubSpot IDs on the order so a later status change can
        // advance the SAME deal instead of creating a duplicate (the moat —
        // the deal keeps pace with the order).
        if (is_array($res) && !empty($res['deal_id'])) {
            $meta = is_array($order->meta_json) ? $order->meta_json : [];
            $meta['hubspot_deal_id']    = (string) $res['deal_id'];
            $meta['hubspot_contact_id'] = (string) ($res['contact_id'] ?? '');
            $meta['hubspot_synced_at']  = now()->toIso8601String();
            $order->forceFill(['meta_json' => $meta])->saveQuietly();
        }

        return $res;
    }

    /**
     * Advance the existing HubSpot deal when an order's status changes.
     * No-op unless the order was previously synced (has a stored deal id)
     * and the workspace is still connected.
     */
    public function syncOrderStatus(WaOrder $order): array|false
    {
        $dealId = (string) (is_array($order->meta_json) ? ($order->meta_json['hubspot_deal_id'] ?? '') : '');
        if ($dealId === '') return false;

        $integration = $this->integrationFor((int) $order->workspace_id);
        if (!$integration) return false;

        return $this->hubspot->updateDeal($integration, $dealId, [
            'dealstage' => $this->stageForStatus((string) $order->status),
        ]);
    }

    /** Build the standard deal property bag for an order. */
    private function dealProps(WaOrder $order): array
    {
        return array_filter([
            'dealname'    => $this->dealName($order),
            // Internal stage/pipeline IDs — HubSpot's defaults exist on every
            // portal. "appointmentscheduled" is the first stage of the default
            // sales pipeline; mapping an incoming order to it is the safe choice.
            'pipeline'    => 'default',
            'dealstage'   => $this->stageForStatus((string) $order->status),
            'amount'      => number_format(((int) $order->total_minor) / 100, 2, '.', ''),
            'deal_currency_code' => $order->currency_code ?: 'INR',
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function dealName(WaOrder $order): string
    {
        $who = trim((string) ($order->customer_name ?: $order->customer_phone ?: 'WhatsApp customer'));
        $ref = $order->id ? ('#' . $order->id) : '';
        return trim("WaDesk order {$ref} · {$who}");
    }

    /**
     * Map a WaDesk order status to a default-pipeline deal stage. These are
     * the internal IDs HubSpot seeds on every portal's "default" pipeline.
     */
    private function stageForStatus(string $status): string
    {
        return match ($status) {
            'paid', 'shipped' => 'closedwon',
            'cancelled'       => 'closedlost',
            'confirmed'       => 'qualifiedtobuy',
            default           => 'appointmentscheduled',
        };
    }

    /** Split a full name into [first, last] for HubSpot's two fields. */
    private function splitName(string $name): array
    {
        $name = trim($name);
        if ($name === '') return ['', ''];
        $parts = preg_split('/\s+/', $name);
        $first = array_shift($parts);
        return [$first, implode(' ', $parts)];
    }
}
