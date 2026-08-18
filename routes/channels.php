<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Private channels for the Team Inbox real-time feed. A signed-in user may
| only subscribe to a workspace/conversation they actually belong to — the
| callback returning false rejects the /broadcasting/auth request. Events:
| App\Events\Inbox\MessageReceived broadcasts on both.
|
*/

/** Is $user a member (owner or pivot) of workspace $workspaceId? */
$isWorkspaceMember = function ($user, int $workspaceId): bool {
    if (!$user || $workspaceId <= 0) return false;
    $ws = \App\Models\Workspace::find($workspaceId);
    if (!$ws) return false;
    if ((int) $ws->owner_user_id === (int) $user->id) return true;
    // Platform admins can monitor any workspace's inbox.
    if (method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin()) return true;
    return DB::table('workspace_user')
        ->where('workspace_id', $workspaceId)
        ->where('user_id', $user->id)
        ->exists();
};

// The whole-inbox feed for a workspace (new inbound message, status change…).
Broadcast::channel('workspace.{workspaceId}.inbox', function ($user, int $workspaceId) use ($isWorkspaceMember) {
    return $isWorkspaceMember($user, $workspaceId);
});

// A single open conversation — used to live-append messages to the thread.
Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId) use ($isWorkspaceMember) {
    $conv = \App\Models\Conversation::find($conversationId);
    return $conv ? $isWorkspaceMember($user, (int) $conv->workspace_id) : false;
});
