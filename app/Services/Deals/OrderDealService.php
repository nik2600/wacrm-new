<?php

namespace App\Services\Deals;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\WaOrder;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;

/**
 * Opt-in auto-creation of a pipeline deal from a new order. No-op unless the
 * workspace enabled it (deals_auto_from_orders) and the order clears the
 * optional minimum-value floor. Idempotent — one deal per order, stamped in
 * the deal's meta.
 */
class OrderDealService
{
    public function maybeCreateFromOrder(WaOrder $order): ?Deal
    {
        try {
            $ws = Workspace::find($order->workspace_id);
            if (!$ws || !$ws->deals_auto_from_orders) return null;

            $min = $ws->deals_auto_min_minor;
            if ($min !== null && (int) $order->total_minor < (int) $min) return null;

            // One deal per order.
            $exists = Deal::where('workspace_id', $order->workspace_id)
                ->where('source', 'order')
                ->whereJsonContains('meta->order_id', (int) $order->id)
                ->exists();
            if ($exists) return null;

            $pipeline = Pipeline::ensureDefaultForWorkspace((int) $order->workspace_id, $order->currency_code ?: null);
            $stage    = $pipeline->stages()->orderBy('sort_order')->first();
            if (!$stage) return null;

            $title = trim(($order->customer_name ?: 'Order') . ' — #' . $order->id);

            return Deal::create([
                'workspace_id' => $order->workspace_id,
                'pipeline_id'  => $pipeline->id,
                'stage_id'     => $stage->id,
                'contact_id'   => $this->resolveContactId((int) $order->workspace_id, (string) $order->customer_phone),
                'title'        => mb_substr($title, 0, 191),
                'value_minor'  => (int) $order->total_minor,
                'currency'     => $order->currency_code ?: $pipeline->currency,
                'source'       => 'order',
                'meta'         => ['order_id' => (int) $order->id],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[DEAL] auto-create from order failed: ' . $e->getMessage());
            return null;
        }
    }

    private function resolveContactId(int $workspaceId, string $phone): ?int
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '' || !$workspaceId) return null;
        $contact = Contact::where('workspace_id', $workspaceId)->get()->first(function ($c) use ($digits) {
            $stored = preg_replace('/\D+/', '', (string) ($c->country_code . $c->mobile));
            return $stored !== '' && $stored === $digits;
        });
        return $contact?->id;
    }
}
