<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(\Illuminate\Http\Request $request): View
    {
        $days = max(1, min(365, (int) $request->query('days', 30)));
        $now = now();

        // Volume trend — daily counts within the window.
        $volume = SupportTicket::where('created_at', '>=', $now->copy()->subDays($days))
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as n'))
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        // Response & resolution averages (whole minutes).
        $avgFirstResp = (int) round((float) SupportTicket::whereNotNull('first_response_at')
            ->where('created_at', '>=', $now->copy()->subDays($days))
            ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as v'))
            ->value('v'));
        $avgResolution = (int) round((float) SupportTicket::whereNotNull('resolved_at')
            ->where('created_at', '>=', $now->copy()->subDays($days))
            ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) as v'))
            ->value('v'));

        // SLA compliance (resolved within their policy's resolution window).
        $resolvedWindow = SupportTicket::where('resolved_at', '>=', $now->copy()->subDays($days))->count();
        $breachedWindow = DB::table('sla_breaches')
            ->where('breached_at', '>=', $now->copy()->subDays($days))
            ->distinct('ticket_id')
            ->count('ticket_id');
        $compliancePct = $resolvedWindow === 0
            ? 100.0
            : round(max(0, 100 - ($breachedWindow / max(1, $resolvedWindow)) * 100), 1);

        // Top agents by resolved count.
        $topAgents = DB::table('support_tickets')
            ->join('users', 'users.id', '=', 'support_tickets.assigned_agent_id')
            ->whereNotNull('support_tickets.resolved_at')
            ->where('support_tickets.resolved_at', '>=', $now->copy()->subDays($days))
            ->select('users.id', 'users.name', DB::raw('COUNT(*) as resolved'),
                DB::raw('AVG(TIMESTAMPDIFF(MINUTE, support_tickets.created_at, support_tickets.first_response_at)) as avg_first_resp'),
                DB::raw('AVG(TIMESTAMPDIFF(MINUTE, support_tickets.created_at, support_tickets.resolved_at)) as avg_resolution'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('resolved')
            ->limit(8)
            ->get();

        // By status distribution.
        $byStatus = SupportTicket::select('status', DB::raw('COUNT(*) as n'))
            ->groupBy('status')->get();

        $kpi = [
            'tickets_30d'    => SupportTicket::where('created_at', '>=', $now->copy()->subDays($days))->count(),
            'resolved_30d'   => $resolvedWindow,
            'avg_first_resp' => $avgFirstResp,
            'avg_resolution' => $avgResolution,
            'compliance'     => $compliancePct,
        ];

        return view('admin.support.reports', compact('kpi', 'volume', 'topAgents', 'byStatus', 'days'));
    }

    public function exportCsv(): StreamedResponse
    {
        return response()->stream(function () {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['ticket_number', 'subject', 'status', 'priority', 'workspace_id', 'assigned_agent_id', 'created_at', 'first_response_at', 'resolved_at']);
            SupportTicket::orderByDesc('created_at')->chunk(500, function ($rows) use ($h) {
                foreach ($rows as $r) {
                    fputcsv($h, [
                        $r->ticket_number, $r->subject, $r->status, $r->priority,
                        $r->workspace_id, $r->assigned_agent_id,
                        $r->created_at, $r->first_response_at, $r->resolved_at,
                    ]);
                }
            });
            fclose($h);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="support-tickets-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
