<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Models\Playbook;
use App\Models\SupportTicket;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlaybooksController extends Controller
{
    public const ACTIONS = [
        'set_status'   => ['label' => 'Set status',   'params' => ['status']],
        'set_priority' => ['label' => 'Set priority', 'params' => ['priority']],
        'add_tag'      => ['label' => 'Add tag',      'params' => ['tag']],
        'reply'        => ['label' => 'Send reply',   'params' => ['body']],
        'assign'       => ['label' => 'Assign agent', 'params' => ['agent_user_id']],
        'note'         => ['label' => 'Add internal note', 'params' => ['body']],
    ];

    public function index(): View
    {
        $playbooks = Playbook::orderByDesc('is_active')->orderByDesc('use_count')->get();
        $kpi = [
            'total'  => $playbooks->count(),
            'active' => $playbooks->where('is_active', true)->count(),
            'uses'   => (int) $playbooks->sum('use_count'),
        ];
        $actions = self::ACTIONS;
        return view('admin.support.playbooks', compact('playbooks', 'kpi', 'actions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:120',
            'trigger_type'  => 'required|in:manual,status_change,tag_added',
            'trigger_value' => 'nullable|string|max:120',
            'steps'         => 'nullable|array',
            'steps.*.action'=> 'required_with:steps|string|in:' . implode(',', array_keys(self::ACTIONS)),
            'steps.*.value' => 'nullable|string|max:8000',
        ]);
        Playbook::create([
            'name'          => $data['name'],
            'slug'          => Str::slug($data['name']) . '-' . substr(md5(uniqid()), 0, 6),
            'trigger_type'  => $data['trigger_type'],
            'trigger_value' => $data['trigger_value'] ?? null,
            'steps'         => array_values($data['steps'] ?? []),
            'is_active'     => true,
            'use_count'     => 0,
        ]);
        Audit::log('support.playbook_created', ['meta' => ['name' => $data['name']]]);
        return back()->with('success', 'Playbook created.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $p = Playbook::findOrFail($id);
        $p->is_active = ! $p->is_active;
        $p->save();
        return back()->with('success', 'Playbook ' . ($p->is_active ? 'activated' : 'paused') . '.');
    }

    public function destroy(int $id): RedirectResponse
    {
        Playbook::findOrFail($id)->delete();
        return back()->with('success', 'Playbook deleted.');
    }

    /** Manually run a playbook against a ticket. */
    public function runOnTicket(Request $request, int $ticketId, int $playbookId): RedirectResponse
    {
        $ticket   = SupportTicket::findOrFail($ticketId);
        $playbook = Playbook::findOrFail($playbookId);
        // Execute each step in order; the actual action handlers live
        // in services/listeners for richer integrations (send WhatsApp
        // template, etc.). For Phase 1 we apply lightweight ones inline.
        $validStatuses   = ['open', 'in_progress', 'pending', 'resolved', 'closed'];
        $validPriorities = ['low', 'normal', 'high', 'urgent'];
        foreach ((array) $playbook->steps as $step) {
            $action = $step['action'] ?? '';
            $value  = $step['value']  ?? '';
            switch ($action) {
                case 'set_status':
                    // Guard against arbitrary strings written by a careless
                    // playbook author from poisoning the enum.
                    if (in_array($value, $validStatuses, true)) $ticket->status = $value;
                    break;
                case 'set_priority':
                    if (in_array($value, $validPriorities, true)) $ticket->priority = $value;
                    break;
                case 'add_tag':
                    $tags = is_array($ticket->tags) ? $ticket->tags : [];
                    if ($value && ! in_array($value, $tags, true)) $tags[] = $value;
                    $ticket->tags = $tags;
                    break;
                case 'assign':       $ticket->assigned_agent_id = (int) $value ?: null; break;
                case 'reply':
                case 'note':
                    \App\Models\SupportMessage::create([
                        'ticket_id'        => $ticket->id,
                        'author_user_id'   => auth()->id(),
                        'author_role'      => 'admin',
                        'body'             => $value,
                        'is_internal_note' => $action === 'note',
                        'created_at'       => now(),
                    ]);
                    break;
            }
        }
        $ticket->save();
        $playbook->increment('use_count');
        Audit::log('support.playbook_run', [
            'subject_type' => 'support_ticket',
            'subject_id'   => $ticket->id,
            'meta'         => ['playbook_id' => $playbook->id],
        ]);
        return back()->with('success', "Playbook \"{$playbook->name}\" executed.");
    }
}
