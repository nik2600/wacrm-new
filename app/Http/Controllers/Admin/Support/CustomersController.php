<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomersController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        // Group support_tickets by workspace_id, compute stats, join workspace name.
        $base = DB::table('support_tickets')
            ->select(
                'workspace_id',
                DB::raw('COUNT(*) as total_tickets'),
                DB::raw("SUM(CASE WHEN status IN ('open','in_progress','pending') THEN 1 ELSE 0 END) as open_tickets"),
                DB::raw('MAX(created_at) as last_ticket_at'),
                DB::raw("SUM(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, resolved_at) ELSE NULL END) as resolution_minutes_sum"),
                DB::raw("SUM(CASE WHEN resolved_at IS NOT NULL THEN 1 ELSE 0 END) as resolved_count"),
            )
            ->whereNotNull('workspace_id')
            ->groupBy('workspace_id')
            ->orderByDesc('open_tickets')
            ->orderByDesc('total_tickets');

        $rows = $base->get();
        $workspaces = Workspace::whereIn('id', $rows->pluck('workspace_id'))->get()->keyBy('id');

        if ($q !== '') {
            $rows = $rows->filter(function ($r) use ($workspaces, $q) {
                $ws = $workspaces[$r->workspace_id] ?? null;
                return $ws && (stripos($ws->name, $q) !== false || stripos((string) $ws->slug, $q) !== false);
            })->values();
        }

        $kpi = [
            'customers'  => $rows->count(),
            'total'      => $rows->sum('total_tickets'),
            'open'       => $rows->sum('open_tickets'),
            'top'        => $rows->first()?->open_tickets ?? 0,
        ];

        return view('admin.support.customers', compact('rows', 'workspaces', 'kpi', 'q'));
    }

    /**
     * Customer (workspace) detail — every ticket they've ever opened
     * + plan summary + ticket-level stats (avg first response, resolution).
     */
    public function show(int $workspaceId): \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
    {
        $workspace = Workspace::find($workspaceId);
        if (! $workspace) {
            return redirect()->route('admin.support.customers')->with('error', 'Workspace not found.');
        }

        $tickets = \App\Models\SupportTicket::where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $resolved = $tickets->whereNotNull('resolved_at');
        $stats = [
            'total'           => $tickets->count(),
            'open'            => $tickets->whereIn('status', ['open','in_progress','pending'])->count(),
            'resolved'        => $resolved->count(),
            'avg_first_resp'  => $tickets->whereNotNull('first_response_at')
                ->avg(fn ($t) => \Carbon\Carbon::parse($t->created_at)->diffInMinutes(\Carbon\Carbon::parse($t->first_response_at))) ?: 0,
            'avg_resolution'  => $resolved
                ->avg(fn ($t) => \Carbon\Carbon::parse($t->created_at)->diffInMinutes(\Carbon\Carbon::parse($t->resolved_at))) ?: 0,
        ];
        $stats['avg_first_resp'] = (int) round($stats['avg_first_resp']);
        $stats['avg_resolution'] = (int) round($stats['avg_resolution']);

        // Eager-load assignee + ticket-opener names so the table doesn't N+1.
        $userIds = $tickets->pluck('assigned_agent_id')
            ->merge($tickets->pluck('user_id'))
            ->filter()->unique()->values();
        $users = \App\Models\User::whereIn('id', $userIds)->get(['id', 'name', 'email'])->keyBy('id');

        return view('admin.support.customer-show', compact('workspace', 'tickets', 'stats', 'users'));
    }
}
