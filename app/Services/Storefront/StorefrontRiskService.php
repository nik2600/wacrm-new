<?php

namespace App\Services\Storefront;

use App\Models\WaOrder;

/**
 * Return-to-origin (RTO) risk score for a storefront order, 0-100. COD is
 * where RTO bites, so the checkout scores every COD order and the merchant
 * can triage high-risk before shipping. Heuristics mirror the WooCommerce
 * risk model but scoped to storefront/all-channel order history for the
 * customer's phone.
 *
 * @return array{score:int, band:string, reasons:string[]}
 */
class StorefrontRiskService
{
    public function score(int $workspaceId, string $phone, int $totalMinor): array
    {
        $phone   = preg_replace('/\D+/', '', $phone);
        $reasons = [];
        $score   = 0;

        $prior = $phone !== ''
            ? WaOrder::where('workspace_id', $workspaceId)->where('customer_phone', $phone)
            : null;

        $priorCount     = $prior ? (clone $prior)->count() : 0;
        $priorCancelled = $prior ? (clone $prior)->where('status', 'cancelled')->count() : 0;
        $priorDelivered = $prior ? (clone $prior)->whereIn('status', ['shipped', 'paid'])->count() : 0;

        // 1. First-time buyer — strongest single RTO predictor.
        if ($priorCount === 0) {
            $score += 30;
            $reasons[] = 'First-time buyer (no order history)';
        } elseif ($priorDelivered === 0) {
            $score += 18;
            $reasons[] = 'No previously fulfilled order';
        }

        // 2. Prior cancellations / RTOs from this number.
        if ($priorCancelled > 0) {
            $score += min(30, $priorCancelled * 15);
            $reasons[] = $priorCancelled . ' prior cancelled order' . ($priorCancelled === 1 ? '' : 's');
        } elseif ($priorDelivered >= 2) {
            // Repeat, reliable customer — lower the risk.
            $score = max(0, $score - 15);
            $reasons[] = 'Repeat customer with fulfilled orders';
        }

        // 3. High order value vs the store average.
        $aov = $this->workspaceAovMinor($workspaceId);
        if ($aov > 0 && $totalMinor > $aov * 2) {
            $score += 25;
            $reasons[] = 'Order value 2x+ the store average';
        } elseif ($aov > 0 && $totalMinor > $aov * 1.5) {
            $score += 12;
            $reasons[] = 'Order value above average';
        }

        // 4. Implausible phone length.
        if (strlen($phone) < 8) {
            $score += 15;
            $reasons[] = 'Short / suspicious phone number';
        }

        $score = max(0, min(100, $score));
        $band  = $score >= 60 ? 'high' : ($score >= 30 ? 'medium' : 'low');

        return ['score' => $score, 'band' => $band, 'reasons' => $reasons];
    }

    private function workspaceAovMinor(int $workspaceId): int
    {
        $avg = WaOrder::where('workspace_id', $workspaceId)
            ->where('total_minor', '>', 0)
            ->avg('total_minor');
        return (int) round($avg ?: 0);
    }
}
