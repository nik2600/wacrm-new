<?php

namespace App\Services\Ordering;

use App\Models\WaProduct;
use App\Models\WaStockReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Anti-sellout inventory engine for the natural-language ordering flow.
 *
 * Stock moves through a ledger so two customers can't oversell the last unit:
 *   hold()    — while the customer is still confirming, lock `qty` (reserved_qty
 *               goes up, a 'held' reservation row is written, expires_at set).
 *   commit()  — on Confirm: stock_qty is decremented, the hold is settled.
 *   release() — on cancel / re-order / abandonment: reserved_qty goes back down.
 *   sweepExpired() — heartbeat-driven: frees holds whose expires_at passed.
 *
 * All mutations run inside a transaction with a row lock on the product so the
 * available-check and the reserve are atomic. NULL stock_qty = unlimited.
 */
class InventoryService
{
    /** Default hold window — a customer has this long to confirm before the
     *  stock is auto-released back to the pool. */
    public const DEFAULT_TTL_SECONDS = 1800; // 30 min

    /** Sellable right now (null = unlimited). */
    public function available(WaProduct $product): ?int
    {
        return $product->availableQty();
    }

    /**
     * Try to hold `qty` of a product for an in-flight order. Atomic: locks the
     * product row, re-reads available, and only reserves if there's enough.
     * Returns the reservation, or null if there isn't enough stock.
     */
    public function hold(WaProduct $product, int $qty, string $ref, ?int $orderId = null, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): ?WaStockReservation
    {
        if ($qty <= 0) return null;

        return DB::transaction(function () use ($product, $qty, $ref, $orderId, $ttlSeconds) {
            /** @var WaProduct $p */
            $p = WaProduct::whereKey($product->id)->lockForUpdate()->first();
            if (!$p) return null;

            // Unlimited stock — reserve without a ceiling check (still ledgered).
            $avail = $p->availableQty();
            if ($avail !== null && $avail < $qty) {
                return null; // not enough — caller tells the customer
            }

            $res = WaStockReservation::create([
                'workspace_id' => $p->workspace_id,
                'product_id'   => $p->id,
                'order_id'     => $orderId,
                'ref'          => mb_substr($ref, 0, 128),
                'qty'          => $qty,
                'status'       => 'held',
                'expires_at'   => now()->addSeconds(max(60, $ttlSeconds)),
            ]);

            $p->forceFill(['reserved_qty' => (int) ($p->reserved_qty ?? 0) + $qty])->saveQuietly();
            return $res;
        });
    }

    /** Settle a hold: decrement real stock, drop the reservation off the books. */
    public function commit(WaStockReservation $res, ?int $orderId = null): void
    {
        if ($res->status !== 'held') return;
        DB::transaction(function () use ($res, $orderId) {
            $p = WaProduct::whereKey($res->product_id)->lockForUpdate()->first();
            if ($p) {
                if ($p->stock_qty !== null) {
                    $p->forceFill(['stock_qty' => max(0, (int) $p->stock_qty - (int) $res->qty)]);
                }
                $p->forceFill(['reserved_qty' => max(0, (int) ($p->reserved_qty ?? 0) - (int) $res->qty)])->saveQuietly();
            }
            $res->forceFill([
                'status'   => 'committed',
                'order_id' => $orderId ?? $res->order_id,
            ])->save();
        });
    }

    /** Free a hold (no sale). */
    public function release(WaStockReservation $res): void
    {
        if ($res->status !== 'held') return;
        DB::transaction(function () use ($res) {
            $p = WaProduct::whereKey($res->product_id)->lockForUpdate()->first();
            if ($p) {
                $p->forceFill(['reserved_qty' => max(0, (int) ($p->reserved_qty ?? 0) - (int) $res->qty)])->saveQuietly();
            }
            $res->forceFill(['status' => 'released'])->save();
        });
    }

    /** Commit every held reservation for a ref (the customer pressed Confirm). */
    public function commitByRef(int $workspaceId, string $ref, ?int $orderId = null): int
    {
        $n = 0;
        WaStockReservation::where('workspace_id', $workspaceId)->where('ref', $ref)
            ->where('status', 'held')->get()
            ->each(function ($r) use (&$n, $orderId) { $this->commit($r, $orderId); $n++; });
        return $n;
    }

    /** Release every held reservation for a ref (cancel / re-order / abandon). */
    public function releaseByRef(int $workspaceId, string $ref): int
    {
        $n = 0;
        WaStockReservation::where('workspace_id', $workspaceId)->where('ref', $ref)
            ->where('status', 'held')->get()
            ->each(function ($r) use (&$n) { $this->release($r); $n++; });
        return $n;
    }

    /**
     * Heartbeat sweep — free holds whose window expired so abandoned carts
     * don't pin stock forever. Returns how many were released.
     */
    public function sweepExpired(int $limit = 1000): int
    {
        $n = 0;
        WaStockReservation::where('status', 'held')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function ($r) use (&$n) {
                try { $this->release($r); $n++; }
                catch (\Throwable $e) { Log::warning('[INVENTORY] sweep release failed: ' . $e->getMessage()); }
            });
        return $n;
    }
}
