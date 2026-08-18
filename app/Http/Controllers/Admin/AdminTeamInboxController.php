<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\ConversationNote;
use App\Models\Message;
use App\Models\PlatformNote;
use App\Models\Tag;
use App\Models\Workspace;
use App\Models\WorkspaceFlag;
use App\Services\Inbox\AuditLogger;
use App\Support\PlatformPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin team-inbox — the SaaS-operator surface. Read-mostly across all
 * workspaces. Mutations limited to: write platform note, flag/unflag
 * workspace, mark conversation as spam globally, suspend workspace.
 *
 * Replies and assignments happen on the workspace side via impersonation,
 * NOT here. Keeping the admin tool read-only-by-default makes the audit
 * trail simpler — every customer-visible action is attributed to a
 * workspace member, never directly to a SaaS staffer.
 */
class AdminTeamInboxController extends Controller
{
    public function index()
    {
        return view('admin.team-inbox.index');
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $perms = collect(PlatformPermissions::PERMISSIONS)
            ->mapWithKeys(fn ($p) => [$p => PlatformPermissions::userCan($request->user(), $p)])
            ->all();

        $workspaces = Workspace::query()
            ->withCount(['conversations as open_count' => fn ($q) => $q->open()])
            ->withCount(['conversations as breach_count' => fn ($q) => $q->open()->where('sla_breached', true)])
            ->orderByDesc('last_active_at')
            ->limit(200)
            ->get(['id','name','slug','plan','status','last_active_at']);

        return response()->json([
            'permissions' => $perms,
            'workspaces'  => $workspaces->map(fn ($w) => [
                'id' => $w->id, 'name' => $w->name, 'slug' => $w->slug,
                'plan' => $w->plan, 'status' => $w->status,
                'open_count'   => $w->open_count,
                'breach_count' => $w->breach_count,
                'last_active_at' => $w->last_active_at,
            ]),
        ]);
    }

    public function queue(Request $request): JsonResponse
    {
        if (!PlatformPermissions::userCan($request->user(), 'platform.conversation.view_all')) abort(403);

        $tab    = (string) $request->query('tab', 'sla_breach');
        $wsId   = $request->query('workspace_id') ? (int) $request->query('workspace_id') : null;
        $page   = max(1, (int) $request->query('page', 1));
        $perPage = min(100, (int) $request->query('per_page', 30));

        $q = Conversation::query()->open();
        if ($wsId) $q->forWorkspace($wsId);

        $q = match ($tab) {
            'sla_breach'    => $q->where('sla_breached', true),
            'spam'          => Conversation::query()->where('inbox_status', 'spam'),
            'unassigned'    => $q->unassigned(),
            'all'           => $q,
            default         => $q->where('sla_breached', true),
        };

        $items = $q->orderByDesc('last_message_at')->orderByDesc('id')
            ->with(['assignee:id,name', 'team:id,name,color', 'workspace:id,name,slug'])
            ->limit($perPage * 4)->get();

        $paged = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'items' => $paged->map(fn (Conversation $c) => [
                'id' => $c->id,
                'workspace_id' => $c->workspace_id,
                'workspace_name' => optional($c->workspace)->name,
                'title' => $c->title, 'preview' => $c->preview,
                'inbox_status' => $c->inbox_status, 'priority' => $c->priority,
                'channel' => $c->channel,
                'assignee_user_id' => $c->assignee_user_id,
                'assignee_name' => optional($c->assignee)->name,
                'assignee_team_id' => $c->assignee_team_id,
                'team_name' => optional($c->team)->name,
                'team_color' => optional($c->team)->color,
                'last_message_at' => $c->last_message_at,
                'sla_breached' => (bool) $c->sla_breached,
                'sla_first_due' => $c->sla_first_response_due,
                'sla_resolution_due' => $c->sla_resolution_due,
                'is_spam' => (bool) $c->is_spam,
                'flags' => optional($c->workspace)->activeFlags()->pluck('flag') ?? [],
            ]),
            'total' => $items->count(),
            'page'  => $page,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if (!PlatformPermissions::userCan($request->user(), 'platform.conversation.view_all')) abort(403);

        $conv = Conversation::with('workspace:id,name,slug,plan')->findOrFail($id);
        $messages   = $conv->messages()->latest()->limit(80)->get()->reverse()->values();
        $notes      = ConversationNote::where('conversation_id', $conv->id)->orderByDesc('created_at')->limit(80)->get();
        $events     = ConversationEvent::where('conversation_id', $conv->id)->orderByDesc('created_at')->limit(80)->get();
        $platformNotes = PlatformNote::where('conversation_id', $conv->id)->orderByDesc('created_at')->limit(80)->get();

        return response()->json([
            'conversation'   => $conv->makeHidden(['title','preview']) // re-include below to use the encrypted-decrypted plaintext
                                ->toArray() + ['title' => $conv->title, 'preview' => $conv->preview],
            'messages'       => $messages->map(fn (Message $m) => [
                'id' => $m->id, 'direction' => $m->direction, 'body' => $m->body,
                'media_path' => $m->media_path, 'media_type' => $m->media_type,
                'status' => $m->status, 'user_id' => $m->user_id, 'created_at' => $m->created_at,
            ]),
            'notes'          => $notes->map(fn ($n) => [
                'id' => $n->id, 'body' => $n->body, 'mentions' => $n->mentions ?? [],
                'author_id' => $n->user_id, 'created_at' => $n->created_at,
            ]),
            'events'         => $events->map(fn ($e) => [
                'id' => $e->id, 'type' => $e->type, 'actor_user_id' => $e->actor_user_id,
                'payload' => $e->payload, 'created_at' => $e->created_at,
            ]),
            'platform_notes' => $platformNotes->map(fn ($n) => [
                'id' => $n->id, 'body' => $n->body, 'severity' => $n->severity,
                'admin_user_id' => $n->admin_user_id, 'admin_name' => optional($n->admin)->name,
                'created_at' => $n->created_at,
            ]),
        ]);
    }

    public function platformNote(Request $request, int $id): JsonResponse
    {
        if (!PlatformPermissions::userCan($request->user(), 'platform.note.write')) abort(403);
        $conv = Conversation::findOrFail($id);

        $data = $request->validate([
            'body'     => 'required|string|max:8000',
            'severity' => 'nullable|in:info,warn,critical',
        ]);

        $note = PlatformNote::create([
            'conversation_id' => $conv->id,
            'workspace_id'    => $conv->workspace_id,
            'admin_user_id'   => $request->user()->id,
            'body'            => $data['body'],
            'severity'        => $data['severity'] ?? 'info',
        ]);
        AuditLogger::platform('platform_note.added', $request->user()->id, $conv->workspace_id, 'platform_note', $note->id);
        return response()->json(['ok' => true, 'note' => $note]);
    }

    public function flagWorkspace(Request $request, int $workspaceId): JsonResponse
    {
        if (!PlatformPermissions::userCan($request->user(), 'platform.workspace.flag')) abort(403);
        $data = $request->validate([
            'flag'   => 'required|in:' . implode(',', WorkspaceFlag::FLAGS),
            'reason' => 'nullable|string|max:500',
        ]);
        $row = WorkspaceFlag::create([
            'workspace_id'       => $workspaceId,
            'flag'               => $data['flag'],
            'reason'             => $data['reason'] ?? null,
            'flagged_by_user_id' => $request->user()->id,
        ]);
        AuditLogger::platform('workspace.flagged', $request->user()->id, $workspaceId, 'workspace', $workspaceId, [
            'flag' => $data['flag'], 'reason' => $data['reason'] ?? null,
        ]);
        return response()->json(['ok' => true, 'flag' => $row]);
    }

    public function clearFlag(Request $request, int $flagId): JsonResponse
    {
        if (!PlatformPermissions::userCan($request->user(), 'platform.workspace.unflag')) abort(403);
        $flag = WorkspaceFlag::findOrFail($flagId);
        $flag->forceFill([
            'cleared_at'         => now(),
            'cleared_by_user_id' => $request->user()->id,
        ])->save();
        AuditLogger::platform('workspace.unflagged', $request->user()->id, $flag->workspace_id, 'workspace_flag', $flag->id);
        return response()->json(['ok' => true]);
    }

    public function flagSpam(Request $request, int $id): JsonResponse
    {
        if (!PlatformPermissions::userCan($request->user(), 'platform.conversation.flag_spam')) abort(403);
        $conv = Conversation::findOrFail($id);
        $conv->forceFill(['is_spam' => true, 'inbox_status' => 'spam'])->save();
        ConversationEvent::record($conv->id, $conv->workspace_id, $request->user()->id, 'spam_flagged', [], 'platform_admin');
        AuditLogger::platform('conversation.spam_flagged', $request->user()->id, $conv->workspace_id, 'conversation', $conv->id);
        return response()->json(['ok' => true]);
    }

    public function suspendWorkspace(Request $request, int $workspaceId): JsonResponse
    {
        if (!PlatformPermissions::userCan($request->user(), 'platform.workspace.suspend')) abort(403);

        // Validate caller input — without this the reason field accepts
        // unbounded strings (and any payload structure) which would land
        // in the DB unfiltered. Cap length and force string type so the
        // workspace-flag table can't be used as a blob dump.
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $ws = Workspace::findOrFail($workspaceId);
        $ws->forceFill(['status' => false])->save();

        // Also write a "frozen" flag so the UI shows why it's suspended.
        WorkspaceFlag::create([
            'workspace_id'       => $workspaceId,
            'flag'               => 'frozen',
            'reason'             => $data['reason'] ?? null,
            'flagged_by_user_id' => $request->user()->id,
        ]);

        AuditLogger::platform('workspace.suspended', $request->user()->id, $workspaceId, 'workspace', $workspaceId);
        return response()->json(['ok' => true]);
    }

    public function unsuspendWorkspace(Request $request, int $workspaceId): JsonResponse
    {
        if (!PlatformPermissions::userCan($request->user(), 'platform.workspace.unsuspend')) abort(403);
        $ws = Workspace::findOrFail($workspaceId);
        $ws->forceFill(['status' => true])->save();
        WorkspaceFlag::where('workspace_id', $workspaceId)
            ->where('flag', 'frozen')->whereNull('cleared_at')
            ->update(['cleared_at' => now(), 'cleared_by_user_id' => $request->user()->id]);
        AuditLogger::platform('workspace.unsuspended', $request->user()->id, $workspaceId, 'workspace', $workspaceId);
        return response()->json(['ok' => true]);
    }

    public function auditLog(Request $request): JsonResponse
    {
        if (!PlatformPermissions::userCan($request->user(), 'platform.audit.view')) abort(403);
        $layer = $request->query('layer');
        $wsId  = $request->query('workspace_id');

        $q = AuditLog::query()->latest('created_at');
        if ($layer) $q->where('layer', $layer);
        if ($wsId)  $q->where('workspace_id', $wsId);

        return response()->json($q->limit(200)->get());
    }
}
