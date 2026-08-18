<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tiny façade around the `security_audit_log` table. Every security
 * tab action (2FA enable/disable, session revoke, password change,
 * workspace delete) calls through here so the admin can audit
 * activity later.
 *
 * Failures are swallowed — losing an audit row should never block
 * the actual operation.
 */
class SecurityAuditLogger
{
    public static function log(string $event, string $status, $user, ?Request $request = null, array $payload = []): void
    {
        try {
            DB::table('security_audit_log')->insert([
                'user_id'      => $user?->id,
                'workspace_id' => $user?->currentWorkspace?->id,
                'event'        => $event,
                'status'       => $status,
                'ip'           => $request?->ip(),
                'user_agent'   => $request?->userAgent() ? mb_substr($request->userAgent(), 0, 255) : null,
                'payload'      => !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit failure should never break the calling action.
        }
    }
}
