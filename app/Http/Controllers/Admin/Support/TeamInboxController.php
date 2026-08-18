<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamInboxController extends Controller
{
    public const COLUMNS = ['open', 'in_progress', 'pending', 'resolved'];

    public function index(Request $request): View
    {
        $mineOnly = (bool) $request->boolean('mine');

        $base = SupportTicket::query()->orderByDesc('updated_at');
        if ($mineOnly) $base->where('assigned_agent_id', auth()->id());
        $all = $base->limit(500)->get();

        $columns = [];
        foreach (self::COLUMNS as $col) {
            $columns[$col] = $all->where('status', $col)->values();
        }

        return view('admin.support.team-inbox', compact('columns', 'mineOnly'));
    }

    /** Drag-drop endpoint — flip the status. */
    public function move(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:' . implode(',', self::COLUMNS)]);
        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = $data['status'];
        if ($data['status'] === 'resolved' && ! $ticket->resolved_at) {
            $ticket->resolved_at = now();
        }
        $ticket->save();
        Audit::log('support.kanban_move', [
            'resource' => 'support_ticket',
            'resource_id' => $ticket->id,
            'meta' => ['to' => $data['status']],
        ]);
        return back()->with('success', 'Moved.');
    }
}
