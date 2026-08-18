<?php

namespace App\Helpers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * NotificationHelper — central drop-point for any "something happened" event
 * across the app (controllers create/update/delete, webhooks fire, etc).
 *
 * Two ways to record:
 *
 *   NotificationHelper::toUser(7, 'Title', 'Body', ['category' => 'system'])
 *
 * or, much simpler, let the model itself report what happened:
 *
 *   NotificationHelper::record($model, 'created');
 *
 * The second form is wired to model created/updated/deleted via the
 * `LogsNotifications` trait so controllers don't have to call it
 * manually. It auto-figures out the category, icon, action URL,
 * and a human-readable title from the model class.
 */
class NotificationHelper
{
    /**
     * Record a notification for one user.
     *
     * Honors the recipient workspace's `notification_prefs` (saved in
     * /settings?tab=notifications). If the event is muted on the
     * in-app channel, the row is NOT created.
     *
     *   $opts['event']    string — preference key (e.g. 'wallet_low_balance').
     *                              Falls back to $opts['category'].
     *   $opts['channel']  string — 'inapp' (default), 'email', 'slack'.
     *   $opts['force']    bool   — bypass user-preference muting. Use
     *                              sparingly (security-critical alerts).
     */
    public static function toUser(?int $userId, string $title, string $message, array $opts = []): ?Notification
    {
        $event   = $opts['event']   ?? $opts['category'] ?? 'system';
        $force   = (bool) ($opts['force'] ?? false);

        $user = $userId ? User::query()->find($userId) : null;
        // Preference lookup is against the workspace that OWNS the event when the
        // caller names one (opts['workspace_id']) — otherwise the recipient's
        // current workspace. Without this, an event raised for workspace A would
        // be gated by whatever workspace the recipient happens to be viewing,
        // so e.g. a device-disconnect email checked the wrong tenant's toggle.
        $ws = null;
        if (!empty($opts['workspace_id'])) {
            $ws = \App\Models\Workspace::find((int) $opts['workspace_id']);
        }
        $ws = $ws ?: $user?->currentWorkspace;

        // ── In-app bell row (gated by the inapp preference) ──────────────
        // Auto-stamp workspace_id so notifications never leak across
        // tenants: explicit opt → recipient's current workspace → caller's.
        $row = null;
        if ($force || !$ws || $ws->wantsNotification($event, 'inapp')) {
            $workspaceId = $opts['workspace_id'] ?? null;
            if ($workspaceId === null && $userId) {
                $workspaceId = User::query()->whereKey($userId)->value('current_workspace_id');
            }
            if ($workspaceId === null) {
                $workspaceId = Auth::user()?->current_workspace_id;
            }

            $row = Notification::create(array_merge([
                'user_id'            => $userId,
                'workspace_id'       => $workspaceId,
                'notification_title' => $title,
                'notification_msg'   => $message,
                'category'           => $opts['category']    ?? 'system',
                'severity'           => $opts['severity']    ?? 'info',
                'icon'               => $opts['icon']        ?? null,
                'source_type'        => $opts['source_type'] ?? null,
                'source_id'          => $opts['source_id']   ?? null,
                'verb'               => $opts['verb']        ?? null,
                'action_url'         => $opts['action_url']  ?? null,
                'is_urgent'          => (bool) ($opts['is_urgent'] ?? false),
                'status'             => true,                  // 1 = unread
            ], $opts['extra'] ?? []));
        }

        // ── Out-of-app channels (email + Slack) ──────────────────────────
        // Fan out to whatever channels this event is opted into. Each is
        // DEFERRED (runs after the response) and FAIL-OPEN, so a slow SMTP
        // host or a bad Slack URL can never delay or break the operation
        // that raised the notification. Channels stay off by default —
        // only events the workspace turned on at /settings?tab=notifications
        // (or the sensible owner-alert defaults) ever leave the app.
        if ($ws) {
            if (($force || $ws->wantsNotification($event, 'email'))
                && $user && filter_var((string) $user->email, FILTER_VALIDATE_EMAIL)) {
                $to = (string) $user->email;
                self::deferChannel(fn () => self::deliverEmail($to, $title, $message));
            }
            if ($force || $ws->wantsNotification($event, 'slack')) {
                $hook = trim((string) (($ws->notification_prefs['_slack_webhook'] ?? '')));
                if ($hook !== '') {
                    self::deferChannel(fn () => self::deliverSlack($hook, $title, $message));
                }
            }
        }

        return $row;
    }

    /** Run a channel delivery after the response (fail-open). */
    private static function deferChannel(callable $fn): void
    {
        $safe = function () use ($fn) {
            try { $fn(); }
            catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('[notify] channel delivery failed: ' . $e->getMessage()); }
        };
        try { app()->terminating($safe); }
        catch (\Throwable $e) { $safe(); }   // no terminating support (CLI/tinker) → inline
    }

    /** Email a notification. Skips silently unless a real mailer is set. */
    private static function deliverEmail(string $to, string $title, string $message): void
    {
        $mailer = config('mail.default');
        if (in_array($mailer, [null, '', 'array', 'log'], true)) return;   // not configured → no-op
        \Illuminate\Support\Facades\Mail::raw($message, function ($m) use ($to, $title) {
            $m->to($to)->subject($title);
        });
    }

    /** Post a notification to a Slack (or compatible) incoming webhook. */
    private static function deliverSlack(string $webhook, string $title, string $message): void
    {
        if (!str_starts_with(strtolower($webhook), 'https://')) return;
        \Illuminate\Support\Facades\Http::timeout(8)->post($webhook, [
            'text' => '*' . $title . "*\n" . $message,
        ]);
    }

    public static function toAdmins(string $title, string $message, array $opts = []): void
    {
        $admins = User::query()->where(function ($q) {
            $q->where('role', 'A')->orWhere('role', 'admin');
        })->pluck('id');
        if ($admins->isEmpty()) {
            self::toUser(null, $title, $message, $opts);
            return;
        }
        foreach ($admins as $id) {
            self::toUser($id, $title, $message, $opts);
        }
    }

    public static function success(?int $userId, string $title, string $message, array $opts = []): Notification
    {
        return self::toUser($userId, $title, $message, array_merge(['severity' => 'success'], $opts));
    }

    public static function warning(?int $userId, string $title, string $message, array $opts = []): Notification
    {
        return self::toUser($userId, $title, $message, array_merge(['severity' => 'warning', 'is_urgent' => true], $opts));
    }

    public static function error(?int $userId, string $title, string $message, array $opts = []): Notification
    {
        return self::toUser($userId, $title, $message, array_merge(['severity' => 'danger', 'is_urgent' => true], $opts));
    }

    /**
     * Record an automatic activity-style notification from a model event.
     * Called by the LogsNotifications trait but safe to call manually.
     */
    public static function record(Model $model, string $verb, array $opts = []): ?Notification
    {
        $meta = self::introspect($model);
        $verbLabel = match ($verb) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            default   => $verb,
        };

        $title = ucfirst($meta['noun']) . ' ' . $verbLabel;
        if (!empty($meta['name'])) {
            $title .= ': ' . $meta['name'];
        }

        $message = sprintf(
            '%s %s%s by %s.',
            ucfirst($meta['noun']),
            $verbLabel,
            !empty($meta['name']) ? ' "' . Str::limit($meta['name'], 64) . '"' : '',
            self::actorName(),
        );

        $userId = $opts['user_id']
            ?? ($model->user_id ?? null)
            ?? Auth::id();

        return self::toUser($userId, $title, $message, [
            'category'    => $opts['category']    ?? $meta['category'],
            'severity'    => $opts['severity']    ?? ($verb === 'deleted' ? 'warning' : 'info'),
            'icon'        => $opts['icon']        ?? $meta['icon'],
            'source_type' => get_class($model),
            'source_id'   => $model->getKey(),
            'verb'        => $verb,
            'action_url'  => $opts['action_url']  ?? ($verb === 'deleted' ? null : $meta['url']),
            'is_urgent'   => (bool) ($opts['is_urgent'] ?? false),
        ]);
    }

    /**
     * Map a model to a category / icon / action URL / human noun
     * used by the activity-feed UI.
     */
    private static function introspect(Model $model): array
    {
        $class = class_basename($model);
        $id    = $model->getKey();
        $map = [
            'Device'         => ['noun' => 'Device',         'category' => 'device',   'icon' => 'device',   'url' => '/devices/' . $id],
            'WaTemplate'     => ['noun' => 'Template',       'category' => 'template', 'icon' => 'template', 'url' => '/templates/' . $id . '/edit'],
            'ChatTemplate'   => ['noun' => 'Chat template',  'category' => 'template', 'icon' => 'template', 'url' => '/chat'],
            'MetaCampaign'   => ['noun' => 'Meta ad',        'category' => 'campaign', 'icon' => 'campaign', 'url' => '/meta-ads/' . $id],
            'Broadcast'      => ['noun' => 'Broadcast',      'category' => 'broadcast','icon' => 'broadcast','url' => '/broadcasts/' . $id],
            'BroadcastContact' => ['noun' => 'Broadcast contact', 'category' => 'broadcast', 'icon' => 'broadcast', 'url' => null],
            'Conversation'   => ['noun' => 'Conversation',   'category' => 'chat',     'icon' => 'chat',     'url' => '/chat'],
            'Message'        => ['noun' => 'Message',        'category' => 'chat',     'icon' => 'chat',     'url' => '/chat'],
            'Webhook'        => ['noun' => 'Webhook',        'category' => 'webhook',  'icon' => 'webhook',  'url' => '/webhooks/' . $id],
            'WebhookDelivery'=> ['noun' => 'Webhook delivery','category' => 'webhook', 'icon' => 'webhook',  'url' => '/webhooks'],
            'Package'        => ['noun' => 'Plan',           'category' => 'billing',  'icon' => 'billing',  'url' => '/pricing'],
            'PackageFeature' => ['noun' => 'Plan feature',   'category' => 'billing',  'icon' => 'billing',  'url' => '/pricing'],
            'Contact'        => ['noun' => 'Contact',        'category' => 'contact',  'icon' => 'contact',  'url' => '/contacts'],
            'ContactGroup'   => ['noun' => 'Contact group',  'category' => 'contact',  'icon' => 'contact',  'url' => '/contacts/groups'],
            'WpCampaign'     => ['noun' => 'WhatsApp campaign', 'category' => 'campaign', 'icon' => 'campaign', 'url' => '/wa-campaigns/' . $id],
        ];
        $entry = $map[$class] ?? ['noun' => Str::headline($class), 'category' => 'system', 'icon' => 'system', 'url' => null];
        $entry['name'] = self::displayName($model);
        return $entry;
    }

    /**
     * Find the most useful human-readable label on a model.
     */
    private static function displayName(Model $model): ?string
    {
        foreach (['template_name', 'pname', 'name', 'title', 'campaign_name', 'phone_number', 'webhook_url', 'subject', 'plan_id', 'device_name'] as $candidate) {
            if (!isset($model->{$candidate})) continue;
            $val = (string) $model->{$candidate};
            if ($val !== '') return $val;
        }
        return null;
    }

    private static function actorName(): string
    {
        $u = Auth::user();
        if ($u) return $u->name ?? ($u->email ?? 'a user');
        return 'system';
    }
}
