<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * /support — submission form + the operator's own ticket list.
 *
 *   GET  /support  → render the form pre-filled with the operator's
 *                    name/email + show their last ~10 tickets below.
 *   POST /support  → validate + create a SupportTicket row, then
 *                    redirect back with a success flash so the new
 *                    ticket shows up immediately in the list.
 *
 * Single-page flow: the operator submits once and sees their own
 * history without a separate ticket-list route. The same tickets
 * also surface on /account?tab=support via AccountController.
 */
class SupportTicketController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $tickets = $user
            ? SupportTicket::where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(10)
                ->get()
            : collect();

        return view('user.support.index', [
            'tickets' => $tickets,
            'prefill' => [
                'name'  => $user?->name  ?? '',
                'email' => $user?->email ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'reason'  => 'required|string|in:bug,delivery,template,billing,integration,account,other',
            'name'    => 'required|string|max:191',
            'email'   => 'required|email|max:191',
            'subject' => 'required|string|max:191',
            'message' => 'required|string|max:20000',
        ]);

        $ticket = SupportTicket::create([
            'user_id'       => $user->id,
            'workspace_id'  => $user->current_workspace_id,
            'ticket_number' => SupportTicket::freshTicketNumber(),
            'reason'        => $data['reason'],
            'name'          => $data['name'],
            'email'         => $data['email'],
            'subject'       => $data['subject'],
            'message'       => $data['message'],
            'status'        => 'open',
        ]);

        return redirect()
            ->route('user.support')
            ->with('success', "Ticket {$ticket->ticket_number} created — we'll follow up shortly.")
            ->with('new_ticket_number', $ticket->ticket_number);
    }

    /**
     * GET /support/{id} — the customer's own ticket detail page with
     * the full admin↔customer thread.
     *
     * Access guard: only the original `user_id` (and admins via the
     * separate admin route) can view their thread.
     */
    public function show(int $id): View|RedirectResponse
    {
        $user = Auth::user();
        if (! $user) return redirect()->route('login');
        $ticket = SupportTicket::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $messages = \App\Models\SupportMessage::where('ticket_id', $id)
            ->where(function ($q) {
                // Customers must never see admin internal notes.
                $q->where('is_internal_note', false)->orWhereNull('is_internal_note');
            })
            ->orderBy('created_at')
            ->get();
        return view('user.support.show', compact('ticket', 'messages'));
    }

    /**
     * POST /support/{id}/reply — customer follow-up on an existing
     * ticket. If the ticket was resolved it auto-reopens to in_progress
     * so the admin queue surfaces it again.
     */
    public function reply(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) return redirect()->route('login');
        $ticket = SupportTicket::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $data = $request->validate(['body' => 'required|string|max:8000']);
        \App\Models\SupportMessage::create([
            'ticket_id'        => $ticket->id,
            'author_user_id'   => $user->id,
            'author_role'      => 'customer',
            'body'             => $data['body'],
            'is_internal_note' => false,
            'created_at'       => now(),
        ]);
        $ticket->last_reply_at = now();
        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            $ticket->status      = 'in_progress';
            $ticket->resolved_at = null;
        }
        $ticket->save();
        return redirect()->route('user.support.show', $ticket->id)->with('success', 'Reply sent.');
    }
}
