<?php

namespace App\Services\Inbox;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Thin wrapper over AuditLog::create that captures IP/UA from the current
 * request automatically. Use ::workspace() and ::platform() instead of
 * remembering which `layer` string to pass.
 */
class AuditLogger
{
    public static function workspace(string $action, ?int $actorUserId, ?int $workspaceId, ?string $subjectType = null, ?int $subjectId = null, array $payload = [], string $result = 'success'): void
    {
        self::write('workspace', $action, $actorUserId, $workspaceId, $subjectType, $subjectId, $payload, $result);
    }

    public static function platform(string $action, ?int $actorUserId, ?int $workspaceId = null, ?string $subjectType = null, ?int $subjectId = null, array $payload = [], string $result = 'success'): void
    {
        self::write('platform', $action, $actorUserId, $workspaceId, $subjectType, $subjectId, $payload, $result);
    }

    private static function write(string $layer, string $action, ?int $actorUserId, ?int $workspaceId, ?string $subjectType, ?int $subjectId, array $payload, string $result = 'success'): void
    {
        try {
            $req = request();
            AuditLog::create([
                'layer'         => $layer,
                'workspace_id'  => $workspaceId,
                'actor_user_id' => $actorUserId,
                'action'        => $action,
                'subject_type'  => $subjectType,
                'subject_id'    => $subjectId,
                'payload'       => $payload,
                'ip'            => $req?->ip(),
                'user_agent'    => $req?->userAgent() ? mb_substr($req->userAgent(), 0, 500) : null,
                'result'        => in_array($result, ['success', 'failure', 'warning'], true) ? $result : 'success',
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            error_log('[AuditLogger] failed to write ' . $action . ': ' . $e->getMessage());
        }
    }
}
