<?php

namespace App\Http\Controllers;

use App\Models\AiCallAssistant;
use App\Models\AiCallLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only viewer for voice-call AI logs. Writes come from the Node
 * runtime via the Twilio status webhook, not from this controller.
 */
class CallLogsController extends Controller
{
    public function index(Request $request): View
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);

        $q = AiCallLog::query()->where('workspace_id', $wsId);

        // Filters — all optional, defaults to "all in last 30 days".
        if ($status = $request->string('status')->toString()) {
            $q->where('status', $status);
        }
        if ($assistantId = (int) $request->query('assistant_id', 0)) {
            $q->where('assistant_id', $assistantId);
        }
        if ($search = trim($request->string('q')->toString())) {
            $q->where(function ($s) use ($search) {
                $s->where('caller_phone', 'like', '%' . $search . '%')
                  ->orWhere('callee_phone', 'like', '%' . $search . '%');
            });
        }
        if ($request->query('range', '30d') === '24h') {
            $q->where('started_at', '>=', now()->subDay());
        } elseif ($request->query('range', '30d') === '7d') {
            $q->where('started_at', '>=', now()->subDays(7));
        } else {
            $q->where('started_at', '>=', now()->subDays(30));
        }

        $logs = $q->orderByDesc('started_at')->paginate(25)->withQueryString();

        // Sidebar totals — independent of filters so they reflect the
        // full workspace at a glance.
        $totals = [
            'total'        => AiCallLog::where('workspace_id', $wsId)->count(),
            'last_24h'     => AiCallLog::where('workspace_id', $wsId)->where('started_at', '>=', now()->subDay())->count(),
            'completed'    => AiCallLog::where('workspace_id', $wsId)->where('status', 'completed')->count(),
            'failed'       => AiCallLog::where('workspace_id', $wsId)->whereIn('status', ['failed', 'no-answer'])->count(),
            'minutes_24h'  => (int) (AiCallLog::where('workspace_id', $wsId)
                ->where('started_at', '>=', now()->subDay())
                ->sum('duration_seconds') / 60),
        ];

        $assistants = AiCallAssistant::where('workspace_id', $wsId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('user.call-logs.index', compact('logs', 'totals', 'assistants'));
    }

    public function show(int $id): View
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $log = AiCallLog::where('workspace_id', $wsId)->with(['assistant', 'conversation'])->findOrFail($id);
        return view('user.call-logs.detail', compact('log'));
    }
}
