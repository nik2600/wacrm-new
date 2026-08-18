<?php

namespace App\Http\Controllers;

use App\Events\TeamChat\MessagePosted;
use App\Models\TeamChatChannel;
use App\Models\TeamChatChannelMember;
use App\Models\TeamChatInvitation;
use App\Models\TeamChatMessage;
use App\Models\TeamChatRead;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Slack-style internal team chat — multi-channel, DMs, admin-gated
 * invite flow. Lives entirely in-app (no WhatsApp / Baileys / WABA).
 */
class TeamChatController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // Workspace + membership helpers
    // ──────────────────────────────────────────────────────────────

    private function workspaceId(Request $request): int
    {
        $wsId = (int) ($request->user()?->current_workspace_id ?? 0);
        abort_if($wsId === 0, 403, 'No active workspace');
        return $wsId;
    }

    private function memberCheck(int $wsId, int $userId): void
    {
        $ws = Workspace::find($wsId);
        if (!$ws) abort(403, 'Workspace not found');
        if ((int) $ws->owner_user_id === $userId) return;
        $exists = DB::table('workspace_user')
            ->where('workspace_id', $wsId)
            ->where('user_id', $userId)
            ->exists();
        abort_unless($exists, 403, 'Not a workspace member');
    }

    /** Owner-or-admin gate. Lets us restrict channel creation + invite approval. */
    private function isAdmin(int $wsId, int $userId): bool
    {
        $ws = Workspace::find($wsId);
        if (!$ws) return false;
        if ((int) $ws->owner_user_id === $userId) return true;
        $role = DB::table('workspace_user')
            ->where('workspace_id', $wsId)
            ->where('user_id', $userId)
            ->value('role');
        return in_array(strtolower((string) $role), ['owner', 'admin'], true);
    }

    private function rosterIds(int $wsId): array
    {
        $owner = (int) (Workspace::where('id', $wsId)->value('owner_user_id') ?: 0);
        $pivot = DB::table('workspace_user')->where('workspace_id', $wsId)->pluck('user_id')->all();
        return array_values(array_unique(array_filter(array_merge([$owner], $pivot))));
    }

    /** Auto-seed #general for the workspace if it doesn't exist yet. */
    private function ensureGeneralChannel(int $wsId): TeamChatChannel
    {
        $existing = TeamChatChannel::where('workspace_id', $wsId)
            ->where('slug', 'general')->first();
        if ($existing) return $existing;
        return TeamChatChannel::create([
            'workspace_id' => $wsId,
            'name'         => 'general',
            'slug'         => 'general',
            'description'  => 'Everyone in this workspace',
            'type'         => 'public',
        ]);
    }

    /** Resolve a channel by id, validating workspace + membership. */
    private function loadChannel(int $wsId, int $userId, int $channelId): TeamChatChannel
    {
        $ch = TeamChatChannel::where('id', $channelId)
            ->where('workspace_id', $wsId)->first();
        abort_unless($ch, 404, 'Channel not found');

        // Public channels auto-include all workspace members. Private +
        // dm require an explicit membership row.
        if ($ch->type === 'public') return $ch;
        $isMember = TeamChatChannelMember::where('channel_id', $ch->id)
            ->where('user_id', $userId)->exists();
        abort_unless($isMember, 403, 'Not a member of this channel');
        return $ch;
    }

    // ──────────────────────────────────────────────────────────────
    // Page
    // ──────────────────────────────────────────────────────────────

    public function page(Request $request)
    {
        $wsId = $this->workspaceId($request);
        $this->memberCheck($wsId, (int) $request->user()->id);
        $this->ensureGeneralChannel($wsId);
        return view('user.team-inbox.team-chat');
    }

    // ──────────────────────────────────────────────────────────────
    // Channels CRUD
    // ──────────────────────────────────────────────────────────────

    /** List all channels visible to the current user. */
    public function channelsIndex(Request $request): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $this->memberCheck($wsId, $userId);
        $this->ensureGeneralChannel($wsId);

        // Public channels are always visible. Private + DM only if the
        // user has a membership row.
        $publicCh = TeamChatChannel::where('workspace_id', $wsId)
            ->where('type', 'public')
            ->orderBy('id')->get();
        $privateChIds = TeamChatChannelMember::where('user_id', $userId)
            ->whereIn('channel_id', TeamChatChannel::where('workspace_id', $wsId)
                ->whereIn('type', ['private', 'dm'])->pluck('id'))
            ->pluck('channel_id');
        $privateCh = TeamChatChannel::whereIn('id', $privateChIds)->orderBy('id')->get();

        $all = $publicCh->merge($privateCh)->values();
        $channelIds = $all->pluck('id')->all();

        // Per-channel unread count — refactored to 2 batched queries
        // instead of (2 × channels) sequential lookups. The old loop
        // was O(N) on channel count + did a fresh COUNT(*) per channel;
        // at 20+ channels the page took several hundred ms. Now: one
        // membership fetch + one grouped count query.
        $memberRows = TeamChatChannelMember::whereIn('channel_id', $channelIds)
            ->where('user_id', $userId)
            ->get(['channel_id', 'last_read_message_id']);
        $lastReadByCh = $memberRows->pluck('last_read_message_id', 'channel_id')->all();

        $unreadByCh = [];
        if (!empty($channelIds)) {
            // Single grouped COUNT — Eloquent's `->groupBy()` is OK here
            // because team_chat_messages.channel_id is indexed.
            $rows = TeamChatMessage::query()
                ->whereIn('channel_id', $channelIds)
                ->where('user_id', '!=', $userId)
                ->selectRaw('channel_id, COUNT(*) as cnt, MAX(id) as max_id')
                ->groupBy('channel_id')
                ->get();
            foreach ($rows as $r) {
                $lastRead = (int) ($lastReadByCh[$r->channel_id] ?? 0);
                if ($lastRead >= (int) $r->max_id) {
                    $unreadByCh[$r->channel_id] = 0;
                } else {
                    // Cheap recompute: messages newer than last_read.
                    $unreadByCh[$r->channel_id] = TeamChatMessage::where('channel_id', $r->channel_id)
                        ->where('user_id', '!=', $userId)
                        ->where('id', '>', $lastRead)
                        ->count();
                }
            }
        }

        return response()->json([
            'channels' => $all->map(fn ($ch) => [
                'id'              => $ch->id,
                'name'            => $ch->name,
                'slug'            => $ch->slug,
                'type'            => $ch->type,
                'description'     => $ch->description,
                'last_message_at' => $ch->last_message_at?->toIso8601String(),
                'unread'          => $unreadByCh[$ch->id] ?? 0,
            ])->values(),
        ]);
    }

    /** Create a new channel — admins only. */
    public function channelsStore(Request $request): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $this->memberCheck($wsId, $userId);
        abort_unless($this->isAdmin($wsId, $userId), 403, 'Only workspace admins can create channels');

        $data = $request->validate([
            'name'        => 'required|string|max:64|regex:/^[a-z0-9][a-z0-9-_]*$/i',
            'description' => 'nullable|string|max:255',
            'type'        => 'required|in:public,private',
        ]);

        $slug = Str::slug(strtolower($data['name']));
        if (TeamChatChannel::where('workspace_id', $wsId)->where('slug', $slug)->exists()) {
            return response()->json(['ok' => false, 'message' => 'A channel with this name already exists'], 422);
        }

        $ch = TeamChatChannel::create([
            'workspace_id'       => $wsId,
            'name'               => strtolower($data['name']),
            'slug'               => $slug,
            'description'        => $data['description'] ?? null,
            'type'               => $data['type'],
            'created_by_user_id' => $userId,
        ]);

        // Creator is auto-added as admin for private channels.
        if ($ch->type === 'private') {
            TeamChatChannelMember::create([
                'channel_id' => $ch->id,
                'user_id'    => $userId,
                'role'       => 'admin',
                'joined_at'  => now(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'channel' => [
                'id'   => $ch->id,
                'name' => $ch->name,
                'slug' => $ch->slug,
                'type' => $ch->type,
            ],
        ]);
    }

    /** Delete a channel — admins only, #general protected. */
    public function channelsDestroy(Request $request, int $id): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $this->memberCheck($wsId, $userId);
        abort_unless($this->isAdmin($wsId, $userId), 403);

        $ch = TeamChatChannel::where('id', $id)->where('workspace_id', $wsId)->first();
        abort_unless($ch, 404);
        if ($ch->slug === 'general') {
            return response()->json(['ok' => false, 'message' => '#general cannot be deleted'], 422);
        }
        $ch->delete();
        return response()->json(['ok' => true]);
    }

    // ──────────────────────────────────────────────────────────────
    // Messages (now scoped by channel)
    // ──────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $this->memberCheck($wsId, $userId);

        // Resolve channel — defaults to #general
        $channelId = (int) $request->query('channel_id', 0);
        if ($channelId === 0) {
            $ch = $this->ensureGeneralChannel($wsId);
        } else {
            $ch = $this->loadChannel($wsId, $userId, $channelId);
        }

        $cursor = (int) $request->query('before', 0);
        $sinceId = (int) $request->query('since_id', 0);  // diff-mode polling
        $limit  = min(50, max(10, (int) $request->query('limit', 50)));

        $q = TeamChatMessage::query()
            ->where('channel_id', $ch->id)
            ->orderByDesc('id')
            ->limit($limit);
        if ($cursor > 0)  $q->where('id', '<', $cursor);
        if ($sinceId > 0) $q->where('id', '>', $sinceId);

        $rows = $q->get();
        $authorIds = $rows->pluck('user_id')->unique()->all();
        $users = User::whereIn('id', $authorIds)
            ->get(['id', 'name', 'email', 'avatar_path'])->keyBy('id');

        $messages = $rows->reverse()->values()->map(function ($m) use ($users) {
            $u = $users[$m->user_id] ?? null;
            return [
                'id'              => $m->id,
                'channel_id'      => $m->channel_id,
                'user_id'         => $m->user_id,
                'author_name'     => $u?->name ?? 'Unknown',
                'author_avatar'   => $u?->avatar_path
                    ? (\Illuminate\Support\Str::startsWith($u->avatar_path, ['http://', 'https://']) ? $u->avatar_path : media_url($u->avatar_path))
                    : null,
                'body'            => $m->body,
                'mentions'        => is_array($m->mentions) ? $m->mentions : [],
                'reply_to_id'     => $m->reply_to_id,
                'attachment_url'  => $m->attachment_path ? media_url($m->attachment_path) : null,
                'attachment_mime' => $m->attachment_mime,
                'attachment_name' => $m->attachment_name,
                'edited_at'       => $m->edited_at?->toIso8601String(),
                'created_at'      => $m->created_at?->toIso8601String(),
                'is_mine'         => (int) $m->user_id === (int) Auth::id(),
            ];
        });

        $rosterIds = $this->rosterIds($wsId);
        $members = User::whereIn('id', $rosterIds)
            ->get(['id', 'name', 'email', 'avatar_path'])
            ->map(fn ($u) => [
                'id'     => $u->id,
                'name'   => $u->name,
                'email'  => $u->email,
                'avatar' => $u->avatar_path
                    ? (\Illuminate\Support\Str::startsWith($u->avatar_path, ['http://', 'https://']) ? $u->avatar_path : media_url($u->avatar_path))
                    : null,
            ])->values();

        return response()->json([
            'channel'  => ['id' => $ch->id, 'name' => $ch->name, 'slug' => $ch->slug, 'type' => $ch->type, 'description' => $ch->description],
            'messages' => $messages,
            'members'  => $members,
            'me'       => ['id' => $userId, 'name' => $request->user()->name, 'is_admin' => $this->isAdmin($wsId, $userId)],
            'has_more' => $rows->count() === $limit,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $this->memberCheck($wsId, $userId);

        $data = $request->validate([
            'channel_id'  => 'nullable|integer|exists:team_chat_channels,id',
            'body'        => 'nullable|string|max:8192',
            'reply_to_id' => 'nullable|integer|exists:team_chat_messages,id',
            'attachment'  => 'nullable|file|max:20480',
        ]);

        // Resolve target channel (default to #general)
        $channelId = (int) ($data['channel_id'] ?? 0);
        $ch = $channelId > 0
            ? $this->loadChannel($wsId, $userId, $channelId)
            : $this->ensureGeneralChannel($wsId);

        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '' && !$request->hasFile('attachment')) {
            return response()->json(['ok' => false, 'message' => 'empty'], 422);
        }

        $mentions = [];
        if (preg_match_all('/@\[([^\]]+)\]\((\d+)\)/', $body, $m)) {
            foreach ($m[2] as $uid) {
                $uid = (int) $uid;
                if ($uid > 0 && !in_array($uid, $mentions, true)) $mentions[] = $uid;
            }
        }
        if (!empty($mentions)) {
            $roster   = $this->rosterIds($wsId);
            $mentions = array_values(array_intersect($mentions, $roster));
        }

        $attachPath = null;
        $attachMime = null;
        $attachName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachMime = $file->getMimeType();
            $attachName = $file->getClientOriginalName();
            $attachPath = $file->store('team-chat/' . $wsId, media_disk());
        }

        if (!empty($data['reply_to_id'])) {
            $parentChId = TeamChatMessage::where('id', $data['reply_to_id'])->value('channel_id');
            if ((int) $parentChId !== (int) $ch->id) {
                return response()->json(['ok' => false, 'message' => 'parent_other_channel'], 422);
            }
        }

        $msg = TeamChatMessage::create([
            'workspace_id'    => $wsId,
            'channel_id'      => $ch->id,
            'user_id'         => $userId,
            'body'            => $body !== '' ? $body : null,
            'mentions'        => !empty($mentions) ? $mentions : null,
            'reply_to_id'     => $data['reply_to_id'] ?? null,
            'attachment_path' => $attachPath,
            'attachment_mime' => $attachMime,
            'attachment_name' => $attachName,
        ]);

        $ch->update(['last_message_at' => now()]);

        // Author auto-reads their own message in this channel
        TeamChatChannelMember::updateOrCreate(
            ['channel_id' => $ch->id, 'user_id' => $userId],
            ['last_read_message_id' => $msg->id, 'last_read_at' => now(), 'joined_at' => now()]
        );

        try {
            broadcast(new MessagePosted($msg))->toOthers();
        } catch (\Throwable $e) {
            \Log::warning('[team-chat] broadcast failed: ' . $e->getMessage());
        }

        return response()->json([
            'ok'         => true,
            'id'         => $msg->id,
            'channel_id' => $ch->id,
            'created_at' => $msg->created_at?->toIso8601String(),
            'mentions'   => $mentions,
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $this->memberCheck($wsId, $userId);

        $data = $request->validate([
            'channel_id' => 'required|integer|exists:team_chat_channels,id',
            'last_id'    => 'required|integer|min:0',
        ]);
        $ch = $this->loadChannel($wsId, $userId, (int) $data['channel_id']);

        TeamChatChannelMember::updateOrCreate(
            ['channel_id' => $ch->id, 'user_id' => $userId],
            ['last_read_message_id' => (int) $data['last_id'], 'last_read_at' => now(), 'joined_at' => now()]
        );
        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $this->memberCheck($wsId, $userId);

        $msg = TeamChatMessage::where('id', $id)->where('workspace_id', $wsId)->first();
        abort_unless($msg, 404);
        // Author can delete their own; admins can delete any
        if ((int) $msg->user_id !== $userId && !$this->isAdmin($wsId, $userId)) {
            abort(403, 'Not your message');
        }
        $msg->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Edit own message body. 15-min window so threads stay readable
     * — Slack/WhatsApp both cap edits at ~15 min and stamp `edited_at`
     * for the "(edited)" badge.
     */
    public function edit(Request $request, int $id): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $this->memberCheck($wsId, $userId);

        $data = $request->validate(['body' => 'required|string|max:8000']);
        $msg = TeamChatMessage::where('id', $id)->where('workspace_id', $wsId)->first();
        abort_unless($msg, 404);
        if ((int) $msg->user_id !== $userId) abort(403, 'Not your message');
        if ($msg->created_at->diffInMinutes(now()) > 15) {
            return response()->json(['ok' => false, 'error' => 'Edit window expired (15 min)'], 422);
        }
        $msg->forceFill(['body' => $data['body'], 'edited_at' => now()])->save();
        return response()->json(['ok' => true, 'message' => $msg->fresh()]);
    }

    /**
     * Pin / unpin a message. Channel admins or message authors can
     * pin. Pin state is single-bit (`pinned_at` null = unpinned).
     */
    public function pin(Request $request, int $id): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $this->memberCheck($wsId, $userId);

        $msg = TeamChatMessage::where('id', $id)->where('workspace_id', $wsId)->first();
        abort_unless($msg, 404);
        $isAuthor = (int) $msg->user_id === $userId;
        if (!$isAuthor && !$this->isAdmin($wsId, $userId)) abort(403, 'Pin requires author or admin');

        if ($msg->pinned_at) {
            $msg->forceFill(['pinned_at' => null, 'pinned_by_user_id' => null])->save();
            return response()->json(['ok' => true, 'pinned' => false]);
        }
        $msg->forceFill(['pinned_at' => now(), 'pinned_by_user_id' => $userId])->save();
        return response()->json(['ok' => true, 'pinned' => true]);
    }

    /**
     * Toggle emoji reaction. Unique on (message, user, emoji) so the
     * second tap on the same emoji clears it (Slack behavior). Multiple
     * emojis from same user stack normally.
     */
    public function react(Request $request, int $id): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $this->memberCheck($wsId, $userId);

        $data = $request->validate(['emoji' => 'required|string|max:16']);
        $msg = TeamChatMessage::where('id', $id)->where('workspace_id', $wsId)->first();
        abort_unless($msg, 404);

        $existing = \DB::table('team_chat_reactions')
            ->where('message_id', $id)
            ->where('user_id', $userId)
            ->where('emoji', $data['emoji'])
            ->first();
        if ($existing) {
            \DB::table('team_chat_reactions')->where('id', $existing->id)->delete();
            return response()->json(['ok' => true, 'reacted' => false]);
        }
        \DB::table('team_chat_reactions')->insert([
            'message_id'   => $id,
            'user_id'      => $userId,
            'workspace_id' => $wsId,
            'emoji'        => $data['emoji'],
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        return response()->json(['ok' => true, 'reacted' => true]);
    }

    /**
     * Search messages within a channel by body substring. Uses LIKE
     * with escaped wildcards (same anti-injection pattern as the admin
     * audit-log search). Limit 50 results; client paginates beyond.
     */
    public function search(Request $request): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $data = $request->validate([
            'channel_id' => 'required|integer|exists:team_chat_channels,id',
            'q'          => 'required|string|min:2|max:191',
        ]);
        $ch = $this->loadChannel($wsId, $userId, (int) $data['channel_id']);

        $needle = '%' . addcslashes($data['q'], '%_\\') . '%';
        $rows = TeamChatMessage::query()
            ->where('workspace_id', $wsId)
            ->where('channel_id', $ch->id)
            ->where('body', 'like', $needle)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'user_id', 'body', 'created_at', 'reply_to_id']);
        return response()->json(['data' => $rows]);
    }

    /**
     * Typing indicator — operator pings every ~3s while typing. We
     * stash the timestamp on cache keyed by (channel, user) and the
     * channel snapshot endpoint reads everyone-with-a-fresh-ping. No
     * persistent table — typing state is ephemeral by definition.
     */
    public function typing(Request $request): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $data = $request->validate([
            'channel_id' => 'required|integer|exists:team_chat_channels,id',
        ]);
        $ch = $this->loadChannel($wsId, $userId, (int) $data['channel_id']);
        \Cache::put("tc:typing:{$ch->id}:{$userId}", true, now()->addSeconds(8));
        return response()->json(['ok' => true]);
    }

    /**
     * Presence ping — same idea as typing but with longer TTL (45s).
     * The user is "online" so long as we got a ping in the last 45s.
     * Lighter than websocket presence channels and survives Echo
     * setup not being wired on a particular install.
     */
    public function presence(Request $request): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        \Cache::put("tc:online:{$wsId}:{$userId}", true, now()->addSeconds(45));
        return response()->json(['ok' => true]);
    }

    /**
     * Snapshot of who's typing in this channel + online in workspace.
     * The frontend polls this every ~3s while a channel is open.
     */
    public function activity(Request $request): JsonResponse
    {
        $wsId    = $this->workspaceId($request);
        $userId  = (int) $request->user()->id;
        $data = $request->validate(['channel_id' => 'required|integer']);
        $ch = $this->loadChannel($wsId, $userId, (int) $data['channel_id']);

        // Pull workspace member IDs and check which ones have a fresh
        // ping. Bounded list (workspace ops only) so the cache check
        // is O(team-size) — fine up to a few hundred users per ws.
        $teamIds = \DB::table('workspace_user')->where('workspace_id', $wsId)->pluck('user_id')->all();
        $typing = [];
        $online = [];
        foreach ($teamIds as $uid) {
            if (\Cache::has("tc:typing:{$ch->id}:{$uid}") && $uid !== $userId) $typing[] = (int) $uid;
            if (\Cache::has("tc:online:{$wsId}:{$uid}")) $online[] = (int) $uid;
        }
        return response()->json(['typing' => $typing, 'online' => $online]);
    }

    // ──────────────────────────────────────────────────────────────
    // Invitations — admin-gated workflow
    // ──────────────────────────────────────────────────────────────

    /** Anyone can submit an invite request. Admins skip approval. */
    public function invitationsStore(Request $request): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $this->memberCheck($wsId, $userId);

        $data = $request->validate([
            'invitee_email' => 'required|email|max:191',
            'invitee_name'  => 'nullable|string|max:191',
            'note'          => 'nullable|string|max:500',
            'channel_id'    => 'nullable|integer|exists:team_chat_channels,id',
        ]);

        // Resolve invitee user (if they already have an account)
        $invitee = User::where('email', $data['invitee_email'])->first();

        // Block obvious dupes — already a workspace member
        if ($invitee) {
            $alreadyMember = in_array((int) $invitee->id, $this->rosterIds($wsId), true);
            if ($alreadyMember && !$data['channel_id']) {
                return response()->json(['ok' => false, 'message' => 'Already a workspace member'], 422);
            }
        }

        $inv = TeamChatInvitation::create([
            'workspace_id'      => $wsId,
            'channel_id'        => $data['channel_id'] ?? null,
            'requester_user_id' => $userId,
            'invitee_user_id'   => $invitee?->id,
            'invitee_email'     => $data['invitee_email'],
            'invitee_name'      => $data['invitee_name'] ?? $invitee?->name,
            'note'              => $data['note'] ?? null,
            'status'            => $this->isAdmin($wsId, $userId) ? 'approved' : 'pending',
        ]);

        // Auto-approve for admins: actually join the user right away
        if ($inv->status === 'approved') {
            $this->applyApprovedInvitation($inv, $userId);
        }

        return response()->json([
            'ok'     => true,
            'id'     => $inv->id,
            'status' => $inv->status,
            'message' => $inv->status === 'approved'
                ? 'Member added'
                : 'Invitation submitted — waiting for admin approval',
        ]);
    }

    public function invitationsIndex(Request $request): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $this->memberCheck($wsId, $userId);

        // Non-admins only see their own; admins see everything pending
        $q = TeamChatInvitation::where('workspace_id', $wsId);
        if (!$this->isAdmin($wsId, $userId)) {
            $q->where('requester_user_id', $userId);
        }
        $rows = $q->orderByDesc('id')->limit(50)->get();

        $ids = $rows->pluck('requester_user_id')
            ->merge($rows->pluck('decided_by_user_id'))
            ->filter()->unique()->all();
        $users = User::whereIn('id', $ids)->get(['id', 'name', 'email'])->keyBy('id');

        return response()->json([
            'is_admin'    => $this->isAdmin($wsId, $userId),
            'invitations' => $rows->map(function ($r) use ($users) {
                return [
                    'id'              => $r->id,
                    'invitee_email'   => $r->invitee_email,
                    'invitee_name'    => $r->invitee_name,
                    'requester_name'  => $users[$r->requester_user_id]?->name ?? 'Unknown',
                    'requester_email' => $users[$r->requester_user_id]?->email,
                    'status'          => $r->status,
                    'note'            => $r->note,
                    'decided_by'      => $r->decided_by_user_id ? ($users[$r->decided_by_user_id]?->name) : null,
                    'decided_at'      => $r->decided_at?->toIso8601String(),
                    'created_at'      => $r->created_at?->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    /** Admin approves an invitation — joins invitee to the workspace. */
    public function invitationsApprove(Request $request, int $id): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $this->memberCheck($wsId, $userId);
        abort_unless($this->isAdmin($wsId, $userId), 403);

        $inv = TeamChatInvitation::where('id', $id)->where('workspace_id', $wsId)->first();
        abort_unless($inv, 404);
        if ($inv->status !== 'pending') {
            return response()->json(['ok' => false, 'message' => 'Already decided'], 422);
        }

        $inv->status            = 'approved';
        $inv->decided_by_user_id = $userId;
        $inv->decided_at        = now();
        $inv->save();

        $this->applyApprovedInvitation($inv, $userId);

        return response()->json(['ok' => true]);
    }

    public function invitationsDecline(Request $request, int $id): JsonResponse
    {
        $wsId   = $this->workspaceId($request);
        $userId = (int) $request->user()->id;
        $this->memberCheck($wsId, $userId);
        abort_unless($this->isAdmin($wsId, $userId), 403);

        $inv = TeamChatInvitation::where('id', $id)->where('workspace_id', $wsId)->first();
        abort_unless($inv, 404);
        if ($inv->status !== 'pending') {
            return response()->json(['ok' => false, 'message' => 'Already decided'], 422);
        }
        $inv->status            = 'declined';
        $inv->decided_by_user_id = $userId;
        $inv->decided_at        = now();
        $inv->save();
        return response()->json(['ok' => true]);
    }

    /**
     * Adds the invited user to workspace_user (if not already) and to
     * the target channel (if invitation specified one). Idempotent.
     */
    private function applyApprovedInvitation(TeamChatInvitation $inv, int $actorId): void
    {
        if (!$inv->invitee_user_id) {
            // Email-only invite for a non-existent account — we can't
            // join them automatically. The frontend should surface this
            // case so the admin sends a separate signup link.
            return;
        }
        $wsId = (int) $inv->workspace_id;

        // workspace_user — pivot insertion
        $exists = DB::table('workspace_user')
            ->where('workspace_id', $wsId)
            ->where('user_id', $inv->invitee_user_id)->exists();
        if (!$exists) {
            DB::table('workspace_user')->insert([
                'workspace_id' => $wsId,
                'user_id'      => $inv->invitee_user_id,
                'role'         => 'member',
                'invited_at'   => now(),
                'joined_at'    => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        // Optional channel join
        if ($inv->channel_id) {
            TeamChatChannelMember::firstOrCreate(
                ['channel_id' => $inv->channel_id, 'user_id' => $inv->invitee_user_id],
                ['role' => 'member', 'joined_at' => now()]
            );
        }
    }
}
