<?php

namespace App\Services\Woocommerce;

use App\Models\WaOrder;

/**
 * RTO / COD-fraud risk scoring. Returns a 0-100 score + reasons for a COD
 * order, computed from the workspace's own mirrored order history (no external
 * service). Merchants use it to branch high-risk orders to stricter
 * verification or a prepaid-only nudge. A differentiator: standalone RTO tools
 * exist, but nobody fuses the score into the WhatsApp confirmation flow.
 */
class WoocommerceRiskService
{
    /** @return array{score:int, level:string, reasons:array<string>} */
    public static function score(array $order, int $workspaceId): array
    {
        $reasons = [];
        $score   = 0;

        $billing = is_array($order['billing'] ?? null) ? $order['billing'] : [];
        $phone   = preg_replace('/\D+/', '', (string) ($billing['phone'] ?? ($order['phone'] ?? '')));
        $total   = (float) ($order['total'] ?? 0);

        // Prior order history for this customer (mirrored WooCommerce orders).
        $prior = $phone !== ''
            ? WaOrder::where('workspace_id', $workspaceId)->where('source', 'woocommerce')
                ->where('customer_phone', $phone)
            : null;

        $priorCount     = $prior ? (clone $prior)->count() : 0;
        $priorCancelled = $prior ? (clone $prior)->where('status', 'cancelled')->count() : 0;
        $priorDelivered = $prior ? (clone $prior)->whereIn('status', ['shipped', 'paid'])->count() : 0;

        // 1. First-time buyer — highest single RTO predictor.
        if ($priorCount === 0) {
            $score += 30;
            $reasons[] = 'First-time buyer (no order history)';
        } elseif ($priorDelivered === 0) {
            $score += 18;
            $reasons[] = 'No previously fulfilled order';
        }

        // 2. Prior cancellations / RTOs from this number.
        if ($priorCancelled > 0) {
            $add = min(30, $priorCancelled * 15);
            $score += $add;
            $reasons[] = $priorCancelled . ' prior cancelled order' . ($priorCancelled === 1 ? '' : 's');
        }

        // 3. High order value relative to the store's average.
        $aov = self::workspaceAov($workspaceId);
        if ($aov > 0 && $total > $aov * 2) {
            $score += 25;
            $reasons[] = 'Order value ' . round($total / $aov, 1) . '× the store average';
        } elseif ($aov > 0 && $total > $aov * 1.5) {
            $score += 12;
            $reasons[] = 'Order value above average';
        }

        // 4. Missing contactable details.
        if (empty($billing['email'])) {
            $score += 8;
            $reasons[] = 'No email on the order';
        }
        if ($phone === '') {
            $score += 15;
            $reasons[] = 'No phone number on the order';
        }

        $score = max(0, min(100, $score));
        $level = $score >= 60 ? 'high' : ($score >= 30 ? 'medium' : 'low');

        return ['score' => $score, 'level' => $level, 'reasons' => $reasons];
    }

    private static function workspaceAov(int $workspaceId): float
    {
        $q = WaOrder::where('workspace_id', $workspaceId)->where('source', 'woocommerce');
        $count = (clone $q)->count();
        if ($count === 0) return 0.0;
        return ((int) (clone $q)->sum('total_minor')) / 100 / $count;
    }
}
