<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * /activity-log — every action the signed-in user took (or that
 * touched their workspace), pulled from `audit_logs`.
 *
 * Source of truth is the same table the inbox / admin layers
 * already write to via App\Services\Inbox\AuditLogger. We add
 * login/logout/workspace-switch records via auth-event listeners
 * registered in AppServiceProvider.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        $userId = $user?->id;
        $workspaceId = $user?->current_workspace_id;

        $range = $this->resolveRange($request->string('range')->toString() ?: '7d');
        $scope = $request->string('scope')->toString() ?: 'me';        // me | workspace
        $cat   = $request->string('category')->toString() ?: 'all';    // all|auth|conversation|broadcast|...
        $bucket= $request->string('bucket')->toString() ?: 'daily';    // daily | hourly | weekly
        $page  = max(1, $request->integer('page') ?: 1);
        $q     = trim((string) $request->string('q')->toString());
        $perPage = 20;

        $base = AuditLog::query()
            ->where('created_at', '>=', $range['from'])
            ->where('created_at', '<',  $range['to']);

        if ($scope === 'me') {
            $base->where('actor_user_id', $userId);
        } else { // workspace — only allowed if we know the workspace id
            $base->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId));
        }

        if ($cat !== 'all') {
            $base->where('action', 'like', $cat . '.%');
        }

        // Free-text search on `action` (the only plaintext discriminator
        // we can SQL-LIKE — payload is a JSON blob, IP/UA are short).
        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('action', 'like', '%' . $q . '%')
                  ->orWhere('subject_type', 'like', '%' . $q . '%')
                  ->orWhere('ip', 'like', '%' . $q . '%');
            });
        }

        $total = (clone $base)->count();
        $pageCount = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pageCount);

        $rows = (clone $base)
            ->with('actor')
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $stats         = $this->stats($userId, $workspaceId, $range, $scope);
        $categoryCnts  = $this->categoryCounts($userId, $workspaceId, $range, $scope);
        $volume        = $this->volumeSeries($userId, $workspaceId, $range, $scope, $bucket);
        $categoryDonut = $this->categoryDonut($userId, $workspaceId, $range, $scope);
        $topActors     = $this->topActors($userId, $workspaceId, $range, $scope);
        $categoryMix   = $this->categoryMix($categoryCnts);

        $payload = [
            'rows'         => $rows->map(fn ($r) => $this->presentRow($r))->all(),
            'stats'        => $stats,
            'categoryCnts' => $categoryCnts,
            'volume'       => $volume,
            'categoryDonut'=> $categoryDonut,
            'topActors'    => $topActors,
            'categoryMix'  => $categoryMix,
            'page'         => $page,
            'pageCount'    => $pageCount,
            'total'        => $total,
            'shownFrom'    => $total ? (($page - 1) * $perPage) + 1 : 0,
            'shownTo'      => min($total, $page * $perPage),
            'filters'      => compact('range', 'scope', 'cat', 'q', 'bucket'),
            'detail'       => $rows->isNotEmpty() ? $this->presentDetail($rows->first()) : null,
        ];

        if ($request->boolean('partial')) {
            $payload['rowsHtml'] = view('user.activity-log._rows', ['rows' => $payload['rows']])->render();
            return response()->json(['ok' => true, 'data' => $payload]);
        }

        return view('user.activity-log.index', $payload);
    }

    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $row = AuditLog::query()
            ->where(function ($q) use ($user) {
                $q->where('actor_user_id', $user?->id)
                  ->orWhere(function ($w) use ($user) {
                      if ($user?->current_workspace_id) {
                          $w->where('workspace_id', $user->current_workspace_id);
                      } else {
                          $w->whereRaw('1=0');
                      }
                  });
            })
            ->with('actor')
            ->findOrFail($id);

        return response()->json([
            'ok' => true,
            'detail' => $this->presentDetail($row),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = Auth::user();
        $range = $this->resolveRange($request->string('range')->toString() ?: '7d');
        $scope = $request->string('scope')->toString() ?: 'me';

        $q = AuditLog::query()
            ->where('created_at', '>=', $range['from'])
            ->where('created_at', '<',  $range['to'])
            ->orderByDesc('created_at');

        if ($scope === 'me') {
            $q->where('actor_user_id', $user?->id);
        } else {
            $q->when($user?->current_workspace_id, fn ($qq) => $qq->where('workspace_id', $user->current_workspace_id));
        }

        $filename = 'activity-log-' . now()->format('Ymd-His') . '.csv';

        return response()->stream(function () use ($q) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'created_at', 'layer', 'actor_user_id', 'workspace_id', 'action', 'subject_type', 'subject_id', 'ip', 'user_agent']);
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->id,
                        optional($r->created_at)->toIso8601String(),
                        $r->layer,
                        $r->actor_user_id,
                        $r->workspace_id,
                        $r->action,
                        $r->subject_type,
                        $r->subject_id,
                        $r->ip,
                        $r->user_agent,
                    ]);
                }
            });
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    private function resolveRange(string $key): array
    {
        $now = now();
        return match ($key) {
            '24h' => ['from' => $now->copy()->subDay(),    'to' => $now->copy()->addSecond(), 'key' => '24h', 'days' => 1,  'label' => 'Last 24 hours'],
            '30d' => ['from' => $now->copy()->subDays(30), 'to' => $now->copy()->addSecond(), 'key' => '30d', 'days' => 30, 'label' => 'Last 30 days'],
            '90d' => ['from' => $now->copy()->subDays(90), 'to' => $now->copy()->addSecond(), 'key' => '90d', 'days' => 90, 'label' => 'Last 90 days'],
            default => ['from' => $now->copy()->subDays(7), 'to' => $now->copy()->addSecond(), 'key' => '7d', 'days' => 7, 'label' => 'Last 7 days'],
        };
    }

    private function stats(?int $userId, ?int $workspaceId, array $range, string $scope): array
    {
        $base = AuditLog::query()
            ->where('created_at', '>=', $range['from'])
            ->where('created_at', '<',  $range['to']);

        if ($scope === 'me') $base->where('actor_user_id', $userId);
        else                 $base->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId));

        $total = (clone $base)->count();
        $logins = (clone $base)->where('action', 'auth.login')->count();
        $writes = (clone $base)->where(function ($q) {
            $q->where('action', 'like', '%.created')
              ->orWhere('action', 'like', '%.updated')
              ->orWhere('action', 'like', '%.deleted')
              ->orWhere('action', 'like', '%.assigned')
              ->orWhere('action', 'like', '%.replied')
              ->orWhere('action', 'like', '%.resolved');
        })->count();
        $reads = max(0, $total - $writes - $logins);
        $uniqueIps = (clone $base)->whereNotNull('ip')->distinct()->count('ip');

        // delta vs previous window
        $prevFrom = $range['from']->copy()->subDays($range['days']);
        $prevTo   = $range['from']->copy();
        $prev = AuditLog::query()
            ->where('created_at', '>=', $prevFrom)
            ->where('created_at', '<',  $prevTo);
        if ($scope === 'me') $prev->where('actor_user_id', $userId);
        else                 $prev->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId));
        $prevTotal = $prev->count();
        $delta = $prevTotal > 0 ? round((($total - $prevTotal) / $prevTotal) * 100) : ($total > 0 ? 100 : 0);

        return [
            'total'    => $total,
            'logins'   => $logins,
            'writes'   => $writes,
            'reads'    => $reads,
            'uniqueIps'=> $uniqueIps,
            'deltaPct' => $delta,
        ];
    }

    private function categoryCounts(?int $userId, ?int $workspaceId, array $range, string $scope): array
    {
        $base = AuditLog::query()
            ->where('created_at', '>=', $range['from'])
            ->where('created_at', '<',  $range['to']);
        if ($scope === 'me') $base->where('actor_user_id', $userId);
        else                 $base->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId));

        $rows = (clone $base)->select('action', DB::raw('COUNT(*) as c'))->groupBy('action')->get();
        $buckets = ['all' => $rows->sum('c')];
        foreach ($this->categories() as $key => $_) $buckets[$key] = 0;
        foreach ($rows as $r) {
            $cat = explode('.', (string) $r->action, 2)[0] ?? 'other';
            if (!isset($buckets[$cat])) $buckets['other'] = ($buckets['other'] ?? 0) + (int) $r->c;
            else                        $buckets[$cat] += (int) $r->c;
        }
        return $buckets;
    }

    private function volumeSeries(?int $userId, ?int $workspaceId, array $range, string $scope, string $bucket): array
    {
        $base = AuditLog::query()
            ->where('created_at', '>=', $range['from'])
            ->where('created_at', '<',  $range['to']);
        if ($scope === 'me') $base->where('actor_user_id', $userId);
        else                 $base->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId));

        $rows = (clone $base)->get(['created_at', 'action']);
        $bucket = in_array($bucket, ['daily', 'hourly', 'weekly'], true) ? $bucket : 'daily';

        if ($bucket === 'hourly') {
            $points = 24;
            $keyOf  = fn (Carbon $t) => $t->format('Y-m-d H');
            $labels = collect(range($points - 1, 0))->map(fn ($i) => now()->copy()->subHours($i)->format('H:00'));
            $keys   = collect(range($points - 1, 0))->map(fn ($i) => now()->copy()->subHours($i)->format('Y-m-d H'));
        } elseif ($bucket === 'weekly') {
            $points = max(1, (int) ceil($range['days'] / 7));
            $keyOf  = fn (Carbon $t) => $t->copy()->startOfWeek()->format('Y-m-d');
            $keys   = collect(range($points - 1, 0))->map(fn ($i) => now()->copy()->subWeeks($i)->startOfWeek()->format('Y-m-d'));
            $labels = $keys->map(fn ($k) => Carbon::parse($k)->format('M d'));
        } else {
            $points = $range['days'];
            $keyOf  = fn (Carbon $t) => $t->toDateString();
            $keys   = collect(range($points - 1, 0))->map(fn ($i) => now()->copy()->subDays($i)->toDateString());
            $labels = $keys->map(function ($k) use ($range) {
                $d = Carbon::parse($k);
                return $range['days'] <= 7 ? $d->format('D') : $d->format('M d');
            });
        }

        $byBucket = $rows->groupBy(fn ($r) => $keyOf($r->created_at));

        $auth = $keys->map(function ($k) use ($byBucket) {
            return ($byBucket->get($k) ?? collect())->filter(fn ($r) => str_starts_with((string) $r->action, 'auth.'))->count();
        })->all();

        $write = $keys->map(function ($k) use ($byBucket) {
            return ($byBucket->get($k) ?? collect())->filter(function ($r) {
                $a = (string) $r->action;
                return str_ends_with($a, '.created') || str_ends_with($a, '.updated') || str_ends_with($a, '.deleted')
                    || str_ends_with($a, '.assigned') || str_ends_with($a, '.replied') || str_ends_with($a, '.resolved');
            })->count();
        })->all();

        $other = $keys->map(function ($k) use ($byBucket) {
            $rs = ($byBucket->get($k) ?? collect());
            $a = $rs->filter(fn ($r) => str_starts_with((string) $r->action, 'auth.'))->count();
            $w = $rs->filter(function ($r) {
                $a = (string) $r->action;
                return str_ends_with($a, '.created') || str_ends_with($a, '.updated') || str_ends_with($a, '.deleted')
                    || str_ends_with($a, '.assigned') || str_ends_with($a, '.replied') || str_ends_with($a, '.resolved');
            })->count();
            return max(0, $rs->count() - $a - $w);
        })->all();

        return [
            'labels' => $labels->all(),
            'auth'   => $auth,
            'write'  => $write,
            'other'  => $other,
            'bucket' => $bucket,
        ];
    }

    /**
     * Activity-direction donut. Mirrors message-history's "Sent vs received"
     * donut but split by event class: auth / writes / reads / other.
     */
    private function categoryDonut(?int $userId, ?int $workspaceId, array $range, string $scope): array
    {
        $s = $this->stats($userId, $workspaceId, $range, $scope);
        return [
            'auth'   => (int) $s['logins'],
            'writes' => (int) $s['writes'],
            'reads'  => (int) $s['reads'],
            'total'  => (int) $s['total'],
        ];
    }

    /**
     * Top actors by event count — same shape as message-history's
     * topConversations: title, initials, gradient, count, lastAt.
     */
    private function topActors(?int $userId, ?int $workspaceId, array $range, string $scope): array
    {
        $q = AuditLog::query()
            ->where('created_at', '>=', $range['from'])
            ->where('created_at', '<',  $range['to'])
            ->whereNotNull('actor_user_id')
            ->select('actor_user_id', DB::raw('COUNT(*) as c'), DB::raw('MAX(created_at) as last_at'))
            ->groupBy('actor_user_id')
            ->orderByDesc('c')
            ->limit(4);
        if ($scope === 'me') $q->where('actor_user_id', $userId);
        else                 $q->when($workspaceId, fn ($qq) => $qq->where('workspace_id', $workspaceId));

        $rows = $q->get();
        if ($rows->isEmpty()) return [];

        $userIds = $rows->pluck('actor_user_id')->all();
        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        $palette = [
            'from-wa-teal to-wa-deep',
            'from-accent-amber to-accent-coral',
            'from-wa-deep to-ink-900',
            'from-[#5B3D8A] to-[#13478A]',
        ];

        return $rows->values()->map(function ($r, $i) use ($users, $palette) {
            $u = $users->get($r->actor_user_id);
            $name = $u?->name ?: ('User #' . $r->actor_user_id);
            return [
                'id'       => (int) $r->actor_user_id,
                'name'     => $name,
                'email'    => $u?->email ?: '',
                'initials' => $this->initials($name),
                'gradient' => $palette[$i % count($palette)],
                'count'    => (int) $r->c,
                'lastAt'   => Carbon::parse($r->last_at)->format('H:i'),
            ];
        })->all();
    }

    /**
     * Horizontal-bar mix data — labels + counts for the
     * "By category" chart at the bottom of the page.
     *
     * Zero-count categories are dropped so the chart doesn't
     * render a slab of empty rows when only a couple categories
     * have actually been touched. If nothing has events yet, we
     * still emit a single empty bucket so ApexCharts has axes to
     * draw and doesn't look like a render failure.
     */
    private function categoryMix(array $categoryCnts): array
    {
        $cats = $this->categories();
        $labels = [];
        $data   = [];
        foreach ($cats as $key => $label) {
            $count = (int) ($categoryCnts[$key] ?? 0);
            if ($count <= 0) continue;
            $labels[] = $label;
            $data[]   = $count;
        }
        // Sort biggest → smallest. Apex draws horizontal bars top-down,
        // so the largest sits at the top of the chart.
        array_multisort($data, SORT_DESC, $labels);

        if (empty($data)) {
            $labels = ['No events yet'];
            $data   = [0];
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function presentRow(AuditLog $r): array
    {
        $cat = explode('.', (string) $r->action, 2)[0] ?? 'other';
        $palette = $this->categoryPalette($cat);
        $actor = $r->actor;

        return [
            'id'         => $r->id,
            'when'       => optional($r->created_at)->format('H:i'),
            'date'       => $this->humanDate($r->created_at),
            'isoTime'    => optional($r->created_at)->toIso8601String(),
            'action'     => $r->action,
            'actionLabel'=> $this->actionLabel($r->action),
            'category'   => $cat,
            'categoryLabel' => $this->categoryLabel($cat),
            'iconBg'     => $palette['bg'],
            'iconFg'     => $palette['fg'],
            'iconHtml'   => $this->categoryIcon($cat),
            'actorName'  => $actor?->name ?: ($r->actor_user_id ? 'User #' . $r->actor_user_id : 'system'),
            'actorInitials' => $this->initials($actor?->name ?: 'System'),
            'subject'    => $r->subject_type ? $r->subject_type . ($r->subject_id ? ' #' . $r->subject_id : '') : '—',
            'ip'         => $r->ip ?: '—',
            'userAgent'  => $r->user_agent ? mb_substr($r->user_agent, 0, 80) : '—',
            'layer'      => $r->layer,
        ];
    }

    private function presentDetail(AuditLog $r): array
    {
        $row = $this->presentRow($r);
        $payload = is_array($r->payload) ? $r->payload : [];
        return array_merge($row, [
            'subjectType' => $r->subject_type,
            'subjectId'   => $r->subject_id,
            'workspaceId' => $r->workspace_id,
            'fullUserAgent' => $r->user_agent ?: '—',
            'payloadJson' => $payload ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '{}',
            'createdAt'   => optional($r->created_at)->format('M d, Y H:i:s'),
        ]);
    }

    private function categories(): array
    {
        return [
            'auth'         => 'Sign-in & sign-out',
            'conversation' => 'Inbox actions',
            'note'         => 'Internal notes',
            'team'         => 'Team changes',
            'broadcast'    => 'Broadcasts',
            'webhook'      => 'Webhooks',
            'workspace'    => 'Workspace',
            'impersonation'=> 'Impersonation',
            'other'        => 'Other',
        ];
    }

    private function categoryLabel(string $cat): string
    {
        return $this->categories()[$cat] ?? ucfirst($cat);
    }

    private function categoryPalette(string $cat): array
    {
        return match ($cat) {
            'auth'          => ['bg' => 'bg-wa-mint',           'fg' => 'text-wa-deep'],
            'conversation'  => ['bg' => 'bg-[#D9E5F2]',         'fg' => 'text-[#13478A]'],
            'note'          => ['bg' => 'bg-[#F3E9FF]',         'fg' => 'text-[#5B3D8A]'],
            'team'          => ['bg' => 'bg-accent-amber/20',   'fg' => 'text-[#7B5A14]'],
            'broadcast'     => ['bg' => 'bg-[#E8F5E9]',         'fg' => 'text-wa-deep'],
            'webhook'       => ['bg' => 'bg-paper-100',         'fg' => 'text-ink-700'],
            'workspace'     => ['bg' => 'bg-wa-mint',           'fg' => 'text-wa-deep'],
            'impersonation' => ['bg' => 'bg-accent-coral/15',   'fg' => 'text-accent-coral'],
            default         => ['bg' => 'bg-paper-100',         'fg' => 'text-ink-700'],
        };
    }

    private function categoryIcon(string $cat): string
    {
        $cls = 'w-4 h-4';
        return match ($cat) {
            'auth'         => '<svg viewBox="0 0 16 16" class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 3h3v10H9"/><path d="M3 8h7M7 5l3 3-3 3"/></svg>',
            'conversation' => '<svg viewBox="0 0 16 16" class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4h10v7H7l-3 2.5V11H3z"/></svg>',
            'note'         => '<svg viewBox="0 0 16 16" class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 2h6l3 3v9H4z"/><path d="M10 2v3h3M6 9h4M6 11h4"/></svg>',
            'team'         => '<svg viewBox="0 0 16 16" class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="6" cy="6" r="2.2"/><circle cx="11.5" cy="5.5" r="1.8"/><path d="M2 13c0-2.4 1.8-4 4-4s4 1.6 4 4M9.5 12c.4-1.4 1.6-2 3-2 1.5 0 2.5 1 2.5 2.5"/></svg>',
            'broadcast'    => '<svg viewBox="0 0 16 16" class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 8h3l1.5-4 2 8 1.5-4h2"/></svg>',
            'webhook'      => '<svg viewBox="0 0 16 16" class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="4.5" r="1.8"/><path d="M5.5 11.5a3 3 0 1 1 5 0M3 13.2 6 9M13 13.2 10 9"/></svg>',
            'workspace'    => '<svg viewBox="0 0 16 16" class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2.5" y="3.5" width="11" height="9" rx="1.5"/><path d="M2.5 6.5h11"/></svg>',
            'impersonation'=> '<svg viewBox="0 0 16 16" class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="6" r="2.5"/><path d="M3 14c.5-2.5 2.5-4 5-4s4.5 1.5 5 4M5 4l-2-2M11 4l2-2"/></svg>',
            default        => '<svg viewBox="0 0 16 16" class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="5.5"/><path d="M8 5v3M8 11h.01"/></svg>',
        };
    }

    private function actionLabel(string $action): string
    {
        $custom = [
            'auth.login'            => 'Signed in',
            'auth.logout'           => 'Signed out',
            'auth.failed'           => 'Sign-in failed',
            'auth.password_reset'   => 'Reset password',
            'workspace.entered'     => 'Switched workspace',
            'conversation.assigned' => 'Assigned conversation',
            'conversation.unassigned' => 'Unassigned conversation',
            'conversation.resolved' => 'Resolved conversation',
            'conversation.reopened' => 'Reopened conversation',
            'conversation.snoozed'  => 'Snoozed conversation',
            'conversation.replied'  => 'Replied in conversation',
            'note.added'            => 'Added internal note',
            'note.deleted'          => 'Deleted internal note',
            'team.created'          => 'Created team',
            'team.updated'          => 'Updated team',
            'team.deleted'          => 'Deleted team',
            'webhook.fired'         => 'Fired webhook',
            'webhook.test'          => 'Test-fired webhook',
        ];
        if (isset($custom[$action])) return $custom[$action];
        // generic fallback: "broadcasts.created" -> "Broadcasts created"
        $parts = explode('.', $action, 2);
        if (count($parts) === 2) {
            return ucfirst(str_replace('_', ' ', $parts[0])) . ' ' . str_replace('_', ' ', $parts[1]);
        }
        return ucfirst(str_replace('_', ' ', $action));
    }

    private function humanDate(?Carbon $when): string
    {
        if (!$when) return '—';
        if ($when->isToday())     return 'today';
        if ($when->isYesterday()) return 'yesterday';
        if ($when->year === now()->year) return $when->format('M d');
        return $when->format('M d, Y');
    }

    private function initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '··';
        $parts = preg_split('/\s+/', $name);
        $first = mb_substr($parts[0], 0, 1);
        $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
        return mb_strtoupper($first . $last) ?: '··';
    }
}
