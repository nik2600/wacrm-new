<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Models\SlaPolicy;
use App\Models\SupportTicket;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SlaController extends Controller
{
    public function index(): View
    {
        $policies = SlaPolicy::orderBy('id')->get();
        $breaches = DB::table('sla_breaches')
            ->select('sla_breaches.*', 'support_tickets.subject', 'support_tickets.ticket_number')
            ->join('support_tickets', 'support_tickets.id', '=', 'sla_breaches.ticket_id')
            ->orderByDesc('sla_breaches.breached_at')
            ->limit(50)
            ->get();
        // Tickets currently at risk: open + nearing first-response or resolution deadline.
        $atRisk = SupportTicket::whereIn('status', ['open', 'in_progress', 'pending'])
            ->where('created_at', '>=', now()->subHours(48))
            ->orderBy('created_at')
            ->limit(30)
            ->get();

        $kpi = [
            'policies'      => $policies->count(),
            'open_breaches' => $breaches->count(),
            'at_risk'       => $atRisk->count(),
            'compliance_7d' => $this->complianceRate(7),
        ];

        return view('admin.support.sla', compact('policies', 'breaches', 'atRisk', 'kpi'));
    }

    public function storePolicy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'                              => 'required|string|max:120',
            'first_response_minutes'            => 'required|integer|min:1|max:10080',
            'resolution_minutes'                => 'required|integer|min:1|max:43200',
            'pause_when_waiting_on_customer'    => 'sometimes|boolean',
            'respect_business_hours'            => 'sometimes|boolean',
            'is_default'                        => 'sometimes|boolean',
        ]);
        $data['pause_when_waiting_on_customer'] = (bool) ($data['pause_when_waiting_on_customer'] ?? false);
        $data['respect_business_hours']         = (bool) ($data['respect_business_hours'] ?? true);
        $data['is_default']                     = (bool) ($data['is_default'] ?? false);
        // Platform-wide policy → workspace_id NULL. Column was made
        // nullable by 2026_05_23_160344_support_phase1_fixes migration.
        $data['workspace_id']                   = null;
        if ($data['is_default']) {
            SlaPolicy::where('is_default', true)->update(['is_default' => false]);
        }
        SlaPolicy::create($data);
        Audit::log('support.sla_policy_created', ['meta' => ['name' => $data['name']]]);
        return back()->with('success', 'SLA policy saved.');
    }

    public function updatePolicy(Request $request, int $id): RedirectResponse
    {
        $policy = SlaPolicy::findOrFail($id);
        $data = $request->validate([
            'name'                              => 'required|string|max:120',
            'first_response_minutes'            => 'required|integer|min:1|max:10080',
            'resolution_minutes'                => 'required|integer|min:1|max:43200',
            'pause_when_waiting_on_customer'    => 'sometimes|boolean',
            'respect_business_hours'            => 'sometimes|boolean',
            'is_default'                        => 'sometimes|boolean',
        ]);
        $data['pause_when_waiting_on_customer'] = (bool) ($data['pause_when_waiting_on_customer'] ?? false);
        $data['respect_business_hours']         = (bool) ($data['respect_business_hours'] ?? true);
        $data['is_default']                     = (bool) ($data['is_default'] ?? false);
        if ($data['is_default']) {
            SlaPolicy::where('id', '!=', $id)->update(['is_default' => false]);
        }
        $policy->update($data);
        return back()->with('success', 'Policy updated.');
    }

    public function destroyPolicy(int $id): RedirectResponse
    {
        SlaPolicy::findOrFail($id)->delete();
        return back()->with('success', 'Policy deleted.');
    }

    private function complianceRate(int $days): float
    {
        $resolved = SupportTicket::where('resolved_at', '>=', now()->subDays($days))->count();
        if ($resolved === 0) return 100.0;
        $breached = DB::table('sla_breaches')
            ->where('breached_at', '>=', now()->subDays($days))
            ->distinct('ticket_id')
            ->count('ticket_id');
        return round(max(0, 100 - ($breached / max(1, $resolved)) * 100), 1);
    }
}
