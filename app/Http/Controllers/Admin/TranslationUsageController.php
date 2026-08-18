<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TranslationUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin /translation-usage — read-only dashboard showing translation
 * volume + cost-to-date, broken down by provider and workspace.
 *
 * Designed for the agency owner / CFO who wants to know "what's this
 * translation thing costing me this month" without diving into raw
 * tables. The page reads only TranslationUsage rows; nothing here
 * mutates state.
 */
class TranslationUsageController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->query('from')
            ? \Carbon\Carbon::parse($request->query('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->query('to')
            ? \Carbon\Carbon::parse($request->query('to'))->endOfDay()
            : now();

        $base = TranslationUsage::query()
            ->whereBetween('called_at', [$from, $to]);

        // Headline totals.
        $totals = (clone $base)->selectRaw(
            'COUNT(*) AS calls, '
            . 'SUM(chars_in) AS chars_in, '
            . 'SUM(chars_out) AS chars_out, '
            . 'SUM(cost_micros) AS cost_micros, '
            . 'SUM(CASE WHEN was_fallback = 1 THEN 1 ELSE 0 END) AS fallback_calls'
        )->first();

        // Per-provider breakdown.
        $perProvider = (clone $base)
            ->selectRaw('provider_slug, COUNT(*) AS calls, SUM(chars_in) AS chars_in, SUM(cost_micros) AS cost_micros')
            ->groupBy('provider_slug')
            ->orderByDesc('calls')
            ->get();

        // Daily timeline (last 30 days only — keeps the chart tight).
        $timeline = (clone $base)
            ->where('called_at', '>=', now()->subDays(30)->startOfDay())
            ->selectRaw('DATE(called_at) AS day, SUM(chars_in) AS chars_in, SUM(cost_micros) AS cost_micros')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // Top-spending workspaces.
        $topWorkspaces = (clone $base)
            ->selectRaw('workspace_id, COUNT(*) AS calls, SUM(chars_in) AS chars_in, SUM(cost_micros) AS cost_micros')
            ->groupBy('workspace_id')
            ->orderByDesc('cost_micros')
            ->orderByDesc('chars_in')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $row->workspace_name = $row->workspace_id
                    ? optional(\App\Models\Workspace::find($row->workspace_id))->name
                    : null;
                return $row;
            });

        // Cache-hit ratio — we can't see cache hits in the ledger, but
        // we can compare keyword_reply_logs fired count vs translation_usage
        // call count and infer the ratio.
        $repliesFired = \DB::table('keyword_reply_logs')
            ->whereBetween('fired_at', [$from, $to])
            ->count();
        $apiCalls   = (int) ($totals?->calls ?? 0);
        $cacheRatio = $repliesFired > 0
            ? max(0, min(100, (int) round(100 * (1 - ($apiCalls / max(1, $repliesFired))))))
            : null;

        return view('admin.translation-usage.index', compact(
            'totals', 'perProvider', 'timeline', 'topWorkspaces',
            'cacheRatio', 'from', 'to'
        ));
    }
}
