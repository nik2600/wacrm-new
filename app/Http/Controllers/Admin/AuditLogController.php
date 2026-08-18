<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        // Inline retention sweep — project policy is "no scheduler", so
        // we piggyback on this page (an admin reviewing audit logs is
        // exactly when stale rows matter). Cache-gated to once per
        // 24h so opening the page repeatedly is cheap.
        $this->maybePruneRetention();

        // Validate filter inputs — without this the audit-log page
        // accepted any string for `from/to` (concatenated raw into
        // WHERE) + uncapped `q` (which an attacker could use to spike
        // CPU via huge LIKE patterns). Now from/to must be ISO dates
        // and `q` is capped to 191 chars.
        $data = $request->validate([
            'q'            => 'nullable|string|max:191',
            'event'        => 'nullable|string|max:120',
            'result'       => 'nullable|in:success,failure,warning',
            'layer'        => 'nullable|in:platform,workspace',
            'workspace_id' => 'nullable|integer',
            'from'         => 'nullable|date_format:Y-m-d',
            'to'           => 'nullable|date_format:Y-m-d',
        ]);

        $q       = trim((string) ($data['q']      ?? ''));
        $event   = (string) ($data['event']  ?? '');
        $result  = (string) ($data['result'] ?? '');
        $layer   = (string) ($data['layer']  ?? '');
        $wsId    = (int)    ($data['workspace_id'] ?? 0);
        $from    = (string) ($data['from']   ?? '');
        $to      = (string) ($data['to']     ?? '');

        $query = AuditLog::query()->latest('created_at');

        if ($event)  $query->where('action', $event);
        if ($result) $query->where('result', $result);
        if ($layer)  $query->where('layer', $layer);
        if ($wsId)   $query->where('workspace_id', $wsId);
        if ($from)   $query->where('created_at', '>=', $from . ' 00:00:00');
        if ($to)     $query->where('created_at', '<=', $to   . ' 23:59:59');
        if ($q) {
            // Escape LIKE wildcards so `_` and `%` in the search text
            // match literally instead of acting as SQL wildcards (which
            // would let an attacker craft queries that match anything).
            $needle = '%' . self::escapeLike($q) . '%';
            $query->where(function ($w) use ($needle) {
                $w->where('action', 'like', $needle)
                  ->orWhere('ip', 'like', $needle)
                  ->orWhere('payload', 'like', $needle);
            });
        }

        $rows = $query->paginate(12)->withQueryString();

        // Hydrate actors + workspaces in bulk (relations may be null/withDefault).
        $actorIds = $rows->pluck('actor_user_id')->filter()->unique()->values();
        $wsIds    = $rows->pluck('workspace_id')->filter()->unique()->values();
        $actors   = User::whereIn('id', $actorIds)->get()->keyBy('id');
        $workspaces = Workspace::whereIn('id', $wsIds)->get()->keyBy('id');

        // KPI counts (across full table, not just current page).
        $stats = [
            'total'    => AuditLog::count(),
            'today'    => AuditLog::whereDate('created_at', now()->toDateString())->count(),
            'failures' => AuditLog::where('result', 'failure')->count(),
            'platform' => AuditLog::where('layer', 'platform')->count(),
        ];

        // Distinct event types for the filter dropdown.
        $eventOptions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->take(200);

        // Top actors over the last 7 days (sidebar card).
        $topActorRows = AuditLog::query()
            ->selectRaw('actor_user_id, COUNT(*) as n')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('actor_user_id')
            ->groupBy('actor_user_id')
            ->orderByDesc('n')
            ->limit(4)
            ->get();
        $topActorUserIds = $topActorRows->pluck('actor_user_id')->all();
        $topActorUsers = User::whereIn('id', $topActorUserIds)->get()->keyBy('id');
        $topActors = $topActorRows->map(fn ($r) => [
            'name'  => $topActorUsers[$r->actor_user_id]->name ?? ('User #' . $r->actor_user_id),
            'count' => (int) $r->n,
        ])->all();

        // Event mix — top action prefixes ("workspace", "user", etc.) by share.
        $mixRows = AuditLog::query()
            ->selectRaw('action, COUNT(*) as n')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('action')
            ->get();
        $byModule = [];
        $total7 = 0;
        foreach ($mixRows as $r) {
            $prefix = strtolower(explode('.', $r->action)[0] ?? 'other');
            $byModule[$prefix] = ($byModule[$prefix] ?? 0) + (int) $r->n;
            $total7 += (int) $r->n;
        }
        arsort($byModule);
        $eventMix = [];
        foreach (array_slice($byModule, 0, 4, true) as $module => $n) {
            $eventMix[] = ['module' => $module, 'count' => $n, 'pct' => $total7 ? round($n * 100 / $total7) : 0];
        }

        // Review queue — recent failures/warnings.
        $reviewQueue = AuditLog::query()
            ->whereIn('result', ['failure', 'warning'])
            ->latest('created_at')
            ->limit(3)
            ->get();

        return view('admin.audit-log.index', compact(
            'rows', 'actors', 'workspaces', 'stats', 'eventOptions',
            'q', 'event', 'result', 'layer', 'wsId', 'from', 'to',
            'topActors', 'eventMix', 'reviewQueue'
        ));
    }

    public function show(int $id): JsonResponse
    {
        $row = AuditLog::findOrFail($id);
        $actor = $row->actor_user_id ? User::find($row->actor_user_id) : null;
        $ws    = $row->workspace_id  ? Workspace::find($row->workspace_id) : null;

        return response()->json([
            'id'          => $row->id,
            'layer'       => $row->layer,
            'action'      => $row->action,
            'result'      => $row->result,
            'subject'     => $row->subject_type ? ($row->subject_type . '#' . $row->subject_id) : null,
            'actor'       => $actor ? ['id' => $actor->id, 'name' => $actor->name, 'email' => $actor->email] : null,
            'workspace'   => $ws ? ['id' => $ws->id, 'name' => $ws->name] : null,
            'ip'          => $row->ip,
            'user_agent'  => $row->user_agent,
            'payload'     => $row->payload,
            'created_at'  => $row->created_at?->toIso8601String(),
            'human_time'  => $row->created_at?->diffForHumans(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        // Same input validation as index() so an attacker can't bypass
        // it by going straight to /admin/audit-log/export with crafted
        // params.
        $data = $request->validate([
            'event'  => 'nullable|string|max:120',
            'result' => 'nullable|in:success,failure,warning',
            'from'   => 'nullable|date_format:Y-m-d',
            'to'     => 'nullable|date_format:Y-m-d',
        ]);

        $filename = 'audit-log-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens with correct encoding.
            fwrite($out, "\xEF\xBB\xBF");
            self::putCsvSafe($out, ['Time', 'Layer', 'Actor ID', 'Action', 'Subject', 'Workspace', 'IP', 'Result', 'Payload']);

            AuditLog::query()
                ->when($data['result'] ?? null, fn ($q, $v) => $q->where('result', $v))
                ->when($data['event']  ?? null, fn ($q, $v) => $q->where('action', $v))
                ->when($data['from']   ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v . ' 00:00:00'))
                ->when($data['to']     ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v . ' 23:59:59'))
                ->orderByDesc('created_at')
                ->chunk(500, function ($chunk) use ($out) {
                    foreach ($chunk as $row) {
                        self::putCsvSafe($out, [
                            $row->created_at?->toIso8601String(),
                            $row->layer,
                            $row->actor_user_id,
                            $row->action,
                            $row->subject_type ? ($row->subject_type . '#' . $row->subject_id) : '',
                            $row->workspace_id,
                            $row->ip,
                            $row->result,
                            json_encode($row->payload, JSON_UNESCAPED_SLASHES),
                        ]);
                    }
                });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Escape SQL LIKE wildcards (`%` and `_`) plus the escape char
     * itself so user-supplied search text matches literally. Without
     * this, `?q=admin%` would behave as a wildcard and bypass intent.
     */
    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * Inline retention sweep — runs at most once per 24h regardless of
     * how often the audit-log page is loaded. Reads days from the
     * `security.audit_log_retention_days` system setting (0 = forever).
     * Chunked delete so a backlog can't OOM the request.
     */
    private function maybePruneRetention(): void
    {
        $days = (int) SystemSetting::get('security.audit_log_retention_days', 365);
        if ($days <= 0) return;
        // 24h gate. Cache::add returns false if the key already exists.
        if (!Cache::add('audit:prune:last', now()->toIso8601String(), now()->addDay())) {
            return;
        }
        try {
            $cutoff = now()->subDays($days);
            $query  = AuditLog::query()->where('created_at', '<', $cutoff);
            $loops = 0;
            while ($loops++ < 5) {           // hard cap — 10k rows per request max
                $batch = $query->limit(2000)->delete();
                if ($batch === 0) break;
            }
        } catch (\Throwable $e) {
            \Log::warning('audit prune sweep failed: ' . $e->getMessage());
        }
    }

    /**
     * Write a CSV row with formula-injection guard. Spreadsheet apps
     * (Excel, Sheets, Numbers) execute any cell whose first character
     * is `=`, `+`, `-`, `@`, or a TAB/CR/LF — turning what looks like
     * raw data into an attacker-controlled formula (=cmd|'/c calc'!A0).
     * RFC 4180 doesn't say to do this; OWASP CSV-injection guidance
     * does. We prepend a single quote to defuse the formula while
     * keeping the cell otherwise readable.
     */
    private static function putCsvSafe($handle, array $row): void
    {
        $safe = array_map(static function ($v) {
            $s = (string) $v;
            if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r", "\n"], true)) {
                return "'" . $s;
            }
            return $s;
        }, $row);
        fputcsv($handle, $safe);
    }
}
