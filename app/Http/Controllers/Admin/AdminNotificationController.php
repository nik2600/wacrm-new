<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Support\FormatSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-level notification feed for the admin panel.
 *
 * Distinct from the user-side App\Http\Controllers\NotificationsController,
 * which shows a workspace operator their personal in-app notifications. This
 * feed surfaces what is happening across the WHOLE platform — new signups,
 * orders/payments, support tickets, contact-form messages, new workspaces —
 * the things a super-admin actually wants to be alerted about.
 *
 * It is derived LIVE from existing tables (no separate notifications store),
 * every query is guarded with Schema::hasTable so a partial install never
 * errors, and "unread" is tracked per-admin via a last-seen timestamp.
 */
class AdminNotificationController extends Controller
{
    /** Per-admin "last seen" marker key (so unread is personal to each admin). */
    private function seenKey(): string
    {
        return 'admin.notif_seen.' . (Auth::id() ?: 0);
    }

    private function lastSeen(): Carbon
    {
        $raw = SystemSetting::get($this->seenKey(), null);
        try {
            return $raw ? Carbon::parse((string) $raw) : now()->subDays(14);
        } catch (\Throwable $e) {
            return now()->subDays(14);
        }
    }

    private function touchSeen(): void
    {
        SystemSetting::set($this->seenKey(), now()->toIso8601String(), 'string', 'Last time this admin opened the notifications feed.');
    }

    /**
     * Aggregate recent platform events, newest first.
     *
     * @return array<int,array{id:string,title:string,message:?string,severity:string,when:\Illuminate\Support\Carbon,action_url:string}>
     */
    /** The event types this feed surfaces — drives the KPI strip + filter pills. */
    public const TYPES = [
        'signup'    => 'Signups',
        'payment'   => 'Payments',
        'ticket'    => 'Support',
        'contact'   => 'Messages',
        'workspace' => 'Workspaces',
    ];

    private function events(int $limit = 120, string $type = 'all'): array
    {
        $items = [];
        $add = function (string $id, string $type, string $title, ?string $message, string $severity, $when, string $url) use (&$items) {
            if (empty($when)) return;
            try { $w = $when instanceof Carbon ? $when : Carbon::parse($when); }
            catch (\Throwable $e) { return; }
            $items[] = compact('id', 'type', 'title', 'message', 'severity', 'url') + ['when' => $w];
        };
        $want = fn (string $t) => $type === 'all' || $type === $t;

        // New signups
        if ($want('signup') && Schema::hasTable('users')) {
            foreach (DB::table('users')->orderByDesc('created_at')->limit(40)->get() as $u) {
                $add('user-'.$u->id, 'signup', 'New signup', trim(($u->name ?? 'User').' · '.($u->email ?? ''), ' ·'),
                    'info', $u->created_at ?? null, url('/admin/users'));
            }
        }
        // Orders / payments
        if ($want('payment') && Schema::hasTable('orders')) {
            foreach (DB::table('orders')->orderByDesc('created_at')->limit(40)->get() as $o) {
                $amt   = $o->total_amount ?? $o->amount ?? null;
                $paid  = ($o->status ?? '') === 'paid';
                $money = $amt !== null ? FormatSettings::symbol() . number_format((float) $amt, 2) : null;
                $add('order-'.$o->id, 'payment', ($paid ? 'Payment received' : 'New order').' #'.$o->id, $money,
                    $paid ? 'success' : 'info', $o->paid_at ?? $o->created_at ?? null, url('/admin/order-history'));
            }
        }
        // Support tickets
        if ($want('ticket') && Schema::hasTable('support_tickets')) {
            foreach (DB::table('support_tickets')->orderByDesc('created_at')->limit(40)->get() as $t) {
                $add('ticket-'.$t->id, 'ticket', 'New support ticket', $t->subject ?? ('#'.$t->id),
                    'warning', $t->created_at ?? null, url('/admin/support'));
            }
        }
        // Contact-form messages
        if ($want('contact') && Schema::hasTable('contact_messages')) {
            foreach (DB::table('contact_messages')->orderByDesc('created_at')->limit(40)->get() as $m) {
                $add('contact-'.$m->id, 'contact', 'New contact message', trim(($m->name ?? '').' · '.($m->topic ?? ''), ' ·'),
                    'info', $m->created_at ?? null, url('/admin/contact-messages'));
            }
        }
        // New workspaces
        if ($want('workspace') && Schema::hasTable('workspaces')) {
            foreach (DB::table('workspaces')->whereNull('deleted_at')->orderByDesc('created_at')->limit(40)->get() as $w) {
                $add('ws-'.$w->id, 'workspace', 'New workspace', $w->name ?? ('#'.$w->id),
                    'info', $w->created_at ?? null, url('/admin/workspaces'));
            }
        }

        usort($items, fn ($a, $b) => $b['when']->getTimestamp() <=> $a['when']->getTimestamp());
        return array_slice($items, 0, $limit);
    }

    /** KPI counts per type over the last 30 days (for the stat strip). */
    private function stats(): array
    {
        $since = now()->subDays(30);
        $count = function (string $table, string $col = 'created_at') use ($since): int {
            if (! Schema::hasTable($table)) return 0;
            try { return (int) DB::table($table)->where($col, '>=', $since)->count(); }
            catch (\Throwable $e) { return 0; }
        };
        $s = [
            'signup'    => $count('users'),
            'payment'   => $count('orders'),
            'ticket'    => $count('support_tickets'),
            'contact'   => $count('contact_messages'),
            'workspace' => Schema::hasTable('workspaces')
                ? (int) DB::table('workspaces')->whereNull('deleted_at')->where('created_at', '>=', $since)->count()
                : 0,
        ];
        $s['total'] = array_sum($s);
        return $s;
    }

    /** Bell dropdown feed (JSON). Matches the shape the header JS expects. */
    public function recent(): JsonResponse
    {
        $seen   = $this->lastSeen();
        $unread = 0;
        $items  = array_map(function ($e) use ($seen, &$unread) {
            $isUnread = $e['when']->greaterThan($seen);
            if ($isUnread) $unread++;
            return [
                'id'         => $e['id'],
                'title'      => $e['title'],
                'message'    => $e['message'],
                'severity'   => $e['severity'],
                'unread'     => $isUnread,
                'action_url' => $e['url'],
                'time_ago'   => $e['when']->diffForHumans(),
            ];
        }, $this->events(15));

        return response()->json(['items' => $items, 'unread' => $unread]);
    }

    /** Full notifications page. */
    public function index(\Illuminate\Http\Request $request): View
    {
        $type = (string) $request->query('type', 'all');
        if ($type !== 'all' && ! array_key_exists($type, self::TYPES)) {
            $type = 'all';
        }
        $q = trim((string) $request->query('q', ''));

        $seen   = $this->lastSeen();
        $events = $this->events(120, $type);

        // Free-text filter across title + message.
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $events = array_values(array_filter($events, fn ($e) =>
                str_contains(mb_strtolower($e['title'].' '.($e['message'] ?? '')), $needle)));
        }

        // Opening the page marks everything seen.
        $this->touchSeen();

        return view('admin.notifications.index', [
            'events' => $events,
            'stats'  => $this->stats(),
            'typeF'  => $type,
            'q'      => $q,
            'total'  => count($events),
        ]);
    }

    /** Mark all read = advance the per-admin last-seen marker. */
    public function markAllRead(): JsonResponse
    {
        $this->touchSeen();
        return response()->json(['ok' => true, 'unread' => 0]);
    }

    /** "Clear all" on a live feed just resets the unread marker. */
    public function clearAll(): JsonResponse
    {
        $this->touchSeen();
        return response()->json(['ok' => true, 'unread' => 0]);
    }
}
