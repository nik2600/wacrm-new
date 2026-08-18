<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportAgent;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentsController extends Controller
{
    public function index(): View
    {
        $agents = SupportAgent::with('user:id,name,email')->orderByDesc('is_active')->orderByDesc('current_load')->get();
        // Hydrate live counts (cheap subqueries; agents table rarely exceeds 50 rows)
        $agents = $agents->map(function ($a) {
            $a->open_count = $a->user_id
                ? SupportTicket::where('assigned_agent_id', $a->user_id)
                    ->whereIn('status', ['open', 'in_progress', 'pending'])
                    ->count()
                : 0;
            $a->resolved_30d = $a->user_id
                ? SupportTicket::where('assigned_agent_id', $a->user_id)
                    ->where('resolved_at', '>=', now()->subDays(30))
                    ->count()
                : 0;
            $a->avg_first_response_min = $a->user_id
                ? (int) round((float) SupportTicket::where('assigned_agent_id', $a->user_id)
                    ->whereNotNull('first_response_at')
                    ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as v'))
                    ->value('v'))
                : 0;
            return $a;
        });

        $candidates = User::whereDoesntHave('supportAgent')->orderBy('name')->limit(200)->get(['id', 'name', 'email']);

        $kpi = [
            'total'       => SupportAgent::count(),
            'active'      => SupportAgent::where('is_active', true)->count(),
            'open_total'  => SupportTicket::whereIn('status', ['open', 'in_progress', 'pending'])->count(),
            'unassigned'  => SupportTicket::whereNull('assigned_agent_id')->whereIn('status', ['open', 'pending'])->count(),
        ];

        return view('admin.support.agents', compact('agents', 'candidates', 'kpi'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id'   => 'required|exists:users,id',
            'specialty' => 'nullable|string|max:60',
        ]);
        SupportAgent::firstOrCreate(['user_id' => $data['user_id'], 'workspace_id' => null], [
            'is_active' => true, 'specialty' => $data['specialty'] ?? null, 'current_load' => 0,
        ]);
        Audit::log('support.agent_added', ['meta' => $data]);
        return back()->with('success', 'Agent added.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $a = SupportAgent::findOrFail($id);
        $a->is_active = ! $a->is_active;
        $a->save();
        return back()->with('success', $a->is_active ? 'Activated.' : 'Disabled.');
    }

    public function destroy(int $id): RedirectResponse
    {
        SupportAgent::findOrFail($id)->delete();
        return back()->with('success', 'Agent removed.');
    }
}
