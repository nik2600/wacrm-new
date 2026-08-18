<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Models\Playbook;
use App\Models\SupportAgent;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Audit;
use App\Support\SlaCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function index(Request $request): View
    {
        $status   = (string) $request->query('status', '');
        $priority = (string) $request->query('priority', '');
        $agent    = (string) $request->query('agent', '');
        $q        = trim((string) $request->query('q', ''));

        $base = SupportTicket::query()->orderByDesc('created_at');
        if ($status !== '')   $base->where('status', $status);
        if ($priority !== '') $base->where('priority', $priority);
        if ($agent === 'me')  $base->where('assigned_agent_id', auth()->id());
        if ($agent === 'unassigned') $base->whereNull('assigned_agent_id');
        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('subject', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('ticket_number', 'like', "%{$q}%");
            });
        }
        $tickets = $base->paginate(12)->withQueryString();

        // Eager-load assignees as a [id => User] dictionary — kills the
        // N+1 the row template would otherwise trigger when looking up
        // each ticket's assigned agent name.
        $agentUsers = User::whereIn(
            'id',
            $tickets->getCollection()->pluck('assigned_agent_id')->filter()->unique()->values()->all()
        )->get(['id', 'name', 'email'])->keyBy('id');

        $kpi = [
            'total'        => SupportTicket::count(),
            'open'         => SupportTicket::whereIn('status', ['open', 'pending', 'in_progress'])->count(),
            'unassigned'   => SupportTicket::whereNull('assigned_agent_id')->whereIn('status', ['open', 'pending'])->count(),
            'resolved_24h' => SupportTicket::where('resolved_at', '>=', now()->subDay())->count(),
        ];

        // Agents — used by the side-panel assign dropdown.
        $agents = SupportAgent::with('user:id,name,email')->where('is_active', true)->get();
        // Manual playbooks for the run-on-ticket dropdown in the panel.
        $playbooks = Playbook::where('is_active', true)
            ->where('trigger_type', 'manual')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('admin.support.index', compact('tickets', 'kpi', 'agents', 'agentUsers', 'playbooks', 'status', 'priority', 'agent', 'q'));
    }

    /** Side-panel JSON for ticket detail. */
    public function show(int $id): JsonResponse
    {
        $t = SupportTicket::findOrFail($id);
        $messages = SupportMessage::where('ticket_id', $id)
            ->orderBy('created_at')
            ->with('author:id,name,email')
            ->get(['id', 'author_user_id', 'author_role', 'body', 'is_internal_note', 'created_at']);
        $ws = $t->workspace_id ? Workspace::find($t->workspace_id) : null;
        $assigned = $t->assigned_agent_id ? User::find($t->assigned_agent_id) : null;

        return response()->json([
            'ticket' => $t->only([
                'id', 'ticket_number', 'subject', 'message', 'name', 'email',
                'status', 'priority', 'reason', 'created_at', 'first_response_at',
                'last_reply_at', 'resolved_at',
            ]),
            'workspace' => $ws?->only(['id', 'name', 'slug']),
            'assigned_agent' => $assigned?->only(['id', 'name', 'email']),
            // SLA snapshot — drives the "12m left" / "8m over" pills.
            'sla' => SlaCalculator::status($t),
            'messages' => $messages->map(fn ($m) => [
                'id'       => $m->id,
                'role'     => $m->author_role,
                'name'     => $m->author?->name ?? ($m->author_role === 'system' ? 'System' : 'Customer'),
                'body'     => $m->body,
                'note'     => (bool) $m->is_internal_note,
                'at'       => $m->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'body'              => 'required|string|max:8000',
            'is_internal_note'  => 'sometimes|boolean',
        ]);
        $ticket = SupportTicket::findOrFail($id);
        $isNote = (bool) ($data['is_internal_note'] ?? false);
        $message = SupportMessage::create([
            'ticket_id'        => $ticket->id,
            'author_user_id'   => auth()->id(),
            'author_role'      => 'admin',
            'body'             => $data['body'],
            'is_internal_note' => $isNote,
            'created_at'       => now(),
        ]);
        // First admin reply stops the first_response SLA timer.
        if (! $ticket->first_response_at && ! $isNote) {
            $ticket->first_response_at = now();
        }
        $ticket->last_reply_at = now();
        if ($ticket->status === 'open') $ticket->status = 'in_progress';
        $ticket->save();

        // Notify the customer by email — skip for internal notes + when
        // the ticket has no email address. Wrapped in try so a broken
        // mail config never breaks the reply itself (the message row
        // is already saved by the time we reach here).
        if (! $isNote && ! empty($ticket->email)) {
            try {
                \Illuminate\Support\Facades\Mail::to($ticket->email)
                    ->send(new \App\Mail\Support\AdminReplyToCustomer($ticket, $message));
            } catch (\Throwable $e) {
                \Log::warning('[support.reply] email send failed: ' . $e->getMessage(), [
                    'ticket_id' => $ticket->id,
                    'to'        => $ticket->email,
                ]);
            }
        }

        Audit::log('support.reply', [
            'subject_type' => 'support_ticket',
            'subject_id'   => $ticket->id,
            'meta'         => ['internal_note' => $isNote, 'emailed' => ! $isNote && ! empty($ticket->email)],
        ]);
        return back()->with('success', $isNote ? 'Internal note added.' : 'Reply sent to ' . $ticket->email . '.');
    }

    public function assign(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['agent_user_id' => 'nullable|integer|exists:users,id']);
        $ticket = SupportTicket::findOrFail($id);
        $ticket->assigned_agent_id = $data['agent_user_id'] ?? null;
        $ticket->save();
        Audit::log('support.assign', [
            'subject_type' => 'support_ticket',
            'subject_id'   => $ticket->id,
            'meta'         => ['agent_user_id' => $ticket->assigned_agent_id],
        ]);
        return back()->with('success', $ticket->assigned_agent_id ? 'Assigned.' : 'Unassigned.');
    }

    public function setStatus(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:open,in_progress,pending,resolved,closed']);
        $ticket = SupportTicket::findOrFail($id);
        $wasStatus = $ticket->status;
        $ticket->status = $data['status'];
        // Only stamp resolved_at on the FIRST resolution. Leave the
        // existing timestamp alone if the ticket was already resolved
        // earlier and is just being closed → we want to preserve the
        // original resolution moment for SLA reporting. Reopening
        // (resolved → open/in_progress) clears it so the next resolution
        // gets a fresh timestamp.
        if ($data['status'] === 'resolved' && ! $ticket->resolved_at) {
            $ticket->resolved_at = now();
        } elseif ($wasStatus === 'resolved' && in_array($data['status'], ['open', 'in_progress', 'pending'], true)) {
            $ticket->resolved_at = null;
        }
        $ticket->save();
        Audit::log('support.status_change', [
            'subject_type' => 'support_ticket',
            'subject_id'   => $ticket->id,
            'meta'         => ['status' => $data['status']],
        ]);
        return back()->with('success', 'Status updated.');
    }

    public function setPriority(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['priority' => 'required|in:low,normal,high,urgent']);
        SupportTicket::where('id', $id)->update(['priority' => $data['priority']]);
        Audit::log('support.priority_change', [
            'subject_type' => 'support_ticket',
            'subject_id'   => $id,
            'meta'         => ['priority' => $data['priority']],
        ]);
        return back()->with('success', 'Priority set.');
    }
}
