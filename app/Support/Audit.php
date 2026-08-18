<?php

namespace App\Support;

use App\Services\Inbox\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Audit log facade — ergonomic wrapper over AuditLogger.
 * Routes everything through AuditLogger so one path writes the table.
 *
 *   Audit::log('workspace.created', ['resource' => $workspace]);
 *   Audit::log('user.login_failed', ['result' => 'failure']);
 *   Audit::log('billing.payment.captured', ['resource' => $order, 'meta' => ['amount' => 100]]);
 */
class Audit
{
    public static function log(string $action, array $opts = []): void
    {
        $resource      = $opts['resource'] ?? null;
        $subjectType   = $opts['subject_type'] ?? null;
        $subjectId     = $opts['subject_id'] ?? null;
        $resourceLabel = $opts['resource_label'] ?? null;

        if ($resource instanceof Model) {
            $subjectType   = $subjectType   ?? strtolower(class_basename($resource));
            $subjectId     = $subjectId     ?? (int) $resource->getKey();
            $resourceLabel = $resourceLabel ?? self::guessLabel($resource);
        }

        $actor       = $opts['actor']        ?? Auth::user();
        $workspaceId = $opts['workspace_id'] ?? ($actor?->current_workspace_id ?? null);
        $layer       = $opts['layer']        ?? (str_starts_with($action, 'admin.') ? 'platform' : 'workspace');
        $result      = $opts['result']       ?? 'success';

        // Stuff label + extra meta into payload.
        $payload = $opts['meta'] ?? [];
        if ($resourceLabel && !isset($payload['_label'])) {
            $payload['_label'] = $resourceLabel;
        }

        // Defence-in-depth: callers SHOULD never put secret values in
        // meta (callers pass key names, not values), but if one slips in
        // we redact rather than persist plaintext. This protects against
        // a future refactor accidentally piping a $request->all() into
        // the audit row.
        $payload = self::scrubSecrets($payload);

        if ($layer === 'platform') {
            AuditLogger::platform($action, $actor?->id, $workspaceId, $subjectType, $subjectId, $payload, $result);
        } else {
            AuditLogger::workspace($action, $actor?->id, $workspaceId, $subjectType, $subjectId, $payload, $result);
        }
    }

    private static function guessLabel(Model $m): ?string
    {
        foreach (['name', 'title', 'email', 'label', 'text'] as $col) {
            if (isset($m->$col) && is_string($m->$col)) {
                return mb_substr($m->$col, 0, 200);
            }
        }
        return null;
    }

    /**
     * Recursively redact values whose key name looks secret-ish. Keeps
     * key NAMES intact (so the audit trail still shows what changed)
     * but replaces the value with `[REDACTED]`. Matches the OWASP
     * sensitive-data exposure checklist plus our app-specific names
     * (waba_*, twilio_*, baileys_*).
     *
     * Lists like `credential_keys_set` (which intentionally carry key
     * NAMES, not values) are left alone — the value is a list of
     * already-public field labels, not the secrets themselves.
     */
    private static function scrubSecrets(array $payload): array
    {
        $pattern = '/(password|secret|token|api[_-]?key|credential|private|access[_-]?key|client[_-]?secret|webhook|signing|bearer)/i';
        $whitelistKeys = ['credential_keys_set', 'missing_keys', 'changed_keys'];

        $walk = function ($value, $key = null) use (&$walk, $pattern, $whitelistKeys) {
            if (is_array($value)) {
                $out = [];
                foreach ($value as $k => $v) {
                    $out[$k] = $walk($v, (string) $k);
                }
                return $out;
            }
            if ($key !== null && !in_array($key, $whitelistKeys, true) && preg_match($pattern, $key)) {
                if ($value === null || $value === '' || $value === false) return $value;
                return '[REDACTED]';
            }
            return $value;
        };

        return $walk($payload);
    }
}
