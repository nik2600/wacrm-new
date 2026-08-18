<?php

namespace App\Http\Controllers;

use App\Services\UnifiedMessageStream;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * /message-history — single pane of glass over EVERY message in the
 * workspace. Pulls from 7 source tables (team inbox, legacy direct,
 * auto-reply fires, campaign sends, broadcasts, scheduled jobs)
 * through UnifiedMessageStream and presents them in the same shape
 * the prototype UI expects.
 *
 * The UI itself is unchanged from the prototype — same KPI strip,
 * volume chart, direction donut, filter bar, paginated table with
 * side detail panel, top-conversations card, type mix chart.
 */
class MessageHistoryController extends Controller
{
    public function __construct(private readonly UnifiedMessageStream $stream) {}

    /**
     * Resolved display timezone for this request. Set once in index()/export()
     * from the same chain the rest of the app uses (workspace → user → admin
     * default) so every rendered time here matches Team Inbox / Campaigns —
     * not raw UTC. Read by toDisplayTz().
     */
    private ?string $displayTz = null;

    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $ws   = $user?->currentWorkspace;
        if (!$ws) abort(404, 'No workspace.');
        $this->displayTz = $this->resolveDisplayTz($ws, $user);

        // Default to 30 days, not 7 — operators use this page to look up
        // scheduled / broadcast / campaign sends from up to a few weeks
        // back, and the 7-day default was silently hiding everything
        // more than a week old (the "0 results but I sent these last
        // week" report). Date dropdown still offers 24h / 7d / 90d.
        $range    = $this->resolveRange($request->string('range')->toString() ?: '30d');
        $dir      = $request->string('dir')->toString() ?: 'all';
        $type     = $request->string('type')->toString() ?: 'all';
        $deviceId = $request->integer('device_id');
        $q        = trim((string) $request->string('q')->toString());
        $bucket   = $request->string('bucket')->toString() ?: 'daily';
        $page     = max(1, $request->integer('page') ?: 1);
        $perPage  = 25;

        // Hydrate ALL messages in range — UnifiedMessageStream caps each
        // source at PER_SOURCE_LIMIT (200) so the merge is bounded.
        $paginatorAll = $this->stream->paginate([
            'workspace_id' => $ws->id,
            'sources'      => array_keys(UnifiedMessageStream::SOURCES),
            'direction'    => 'all',
            'from'         => $range['from'],
            'to'           => $range['to'],
            'q'            => '',
            'page'         => 1,
            'per_page'     => 10000,    // full hydrate for charts + KPIs
        ]);
        $rows = collect($paginatorAll->items());

        // Direction filter
        if ($dir === 'out' || $dir === 'in') {
            $rows = $rows->where('direction', $dir);
        } elseif ($dir === 'fail') {
            $rows = $rows->where('direction', 'out')
                ->filter(fn ($r) => in_array($r['status'] ?? '', ['failed', 'error'], true));
        }

        // Type filter (template / image / video / document / location / text / source-pill)
        if ($type !== 'all') {
            $rows = $rows->filter(fn ($r) => $this->classifyType($r) === $type)->values();
        }

        // Free-text search
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $rows = $rows->filter(function ($r) use ($needle) {
                $hay = mb_strtolower(($r['body'] ?? '') . ' ' . ($r['phone'] ?? '') . ' ' . ($r['contact_name'] ?? '') . ' ' . ($r['id'] ?? ''));
                return str_contains($hay, $needle);
            })->values();
        }

        // KPI strip — over RANGE-only universe (so headline numbers
        // don't jiggle when filters change).
        $rangeRows = collect($paginatorAll->items());
        $stats     = $this->kpiStrip($rangeRows, $range, $ws->id);

        // Charts and detail use the FILTERED universe.
        $volume    = $this->volumeSeries($rows, $range, $bucket);
        $direction = $this->directionMix($rows);
        $typeMix   = $this->typeMix($rows);
        $topConvos = $this->topConversations($rangeRows);
        $dirCounts = $this->dirCounts($rangeRows);

        // Pagination over the filtered set.
        $total     = $rows->count();
        $pageCount = max(1, (int) ceil($total / $perPage));
        $page      = min($page, $pageCount);
        $slice     = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        $devices = \App\Models\Device::query()
            ->forCurrentWorkspace()
            ->orderByDesc('id')
            ->get();

        $payload = [
            'stats'      => $stats,
            'volume'     => $volume,
            'direction'  => $direction,
            'typeMix'    => $typeMix,
            'topConvos'  => $topConvos,
            'dirCounts'  => $dirCounts,
            'rows'       => $slice->map(fn ($r) => $this->presentRow($r))->all(),
            'page'       => $page,
            'pageCount'  => $pageCount,
            'total'      => $total,
            'shownFrom'  => $total ? (($page - 1) * $perPage) + 1 : 0,
            'shownTo'    => min($total, $page * $perPage),
            'devices'    => $devices->map(fn ($d) => [
                'id'    => $d->id,
                'phone' => (string) $d->phone_number,
                'label' => $d->display_label ?? trim(((string) $d->device_name) . ' ' . ((string) $d->phone_number)),
            ])->values()->all(),
            'filters'    => compact('range', 'dir', 'type', 'deviceId', 'q', 'bucket'),
        ];

        if ($request->boolean('partial')) {
            $payload['rowsHtml'] = view('user.message-history._rows', ['rows' => $payload['rows']])->render();
            $payload['detail']   = $slice->isNotEmpty() ? $this->presentDetail($slice->first()) : null;
            return response()->json(['ok' => true, 'data' => $payload]);
        }

        $payload['detail'] = $slice->isNotEmpty() ? $this->presentDetail($slice->first()) : null;
        return view('user.message-history.index', $payload);
    }

    public function archive(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Archive is not supported on the unified history view — open the specific source page (Team inbox, Campaigns) to manage individual rows.',
        ], 422);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $ws   = $user?->currentWorkspace;
        if (!$ws) abort(404, 'No workspace.');
        $this->displayTz = $this->resolveDisplayTz($ws, $user);

        $range = $this->resolveRange($request->string('range')->toString() ?: '30d');
        $paginator = $this->stream->paginate([
            'workspace_id' => $ws->id,
            'sources'      => array_keys(UnifiedMessageStream::SOURCES),
            'direction'    => 'all',
            'from'         => $range['from'],
            'to'           => $range['to'],
            'q'            => '',
            'page'         => 1,
            'per_page'     => 10000,
        ]);

        $filename = 'message-history-' . now()->format('Ymd-His') . '.csv';

        return response()->stream(function () use ($paginator) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'source', 'when', 'direction', 'phone', 'contact', 'status', 'type', 'body']);
            // CSV formula-injection guard: Excel / Sheets evaluate any cell
            // whose first char is `=`, `+`, `-`, `@`, TAB, or CR as a
            // formula. A message body of `=cmd|'/c calc'!A1` opens in
            // Excel as a live formula that can spawn processes on click.
            // Prefix dangerous leading chars with a single quote so the
            // value renders literally without sacrificing readability.
            $sanitizeCsv = static function ($v): string {
                $s = (string) $v;
                if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                    return "'" . $s;
                }
                return $s;
            };
            foreach ($paginator->items() as $r) {
                fputcsv($out, [
                    $sanitizeCsv($r['id']),
                    $sanitizeCsv($r['source_label']),
                    optional($r['when'])->toIso8601String() ?? '',
                    $r['direction'],
                    $sanitizeCsv($r['phone'] ?? ''),
                    $sanitizeCsv($r['contact_name'] ?? ''),
                    $sanitizeCsv($r['status'] ?? ''),
                    $this->classifyType($r),
                    $sanitizeCsv($r['body'] ?? ''),
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }

    /* ============================ helpers ============================ */

    private function resolveRange(string $key): array
    {
        $now = now();
        return match ($key) {
            '24h' => ['from' => $now->copy()->subDay(),     'to' => $now->copy()->addSecond(), 'label' => 'Last 24 hours', 'key' => '24h', 'days' => 1],
            '30d' => ['from' => $now->copy()->subDays(30),  'to' => $now->copy()->addSecond(), 'label' => 'Last 30 days',  'key' => '30d', 'days' => 30],
            '90d' => ['from' => $now->copy()->subDays(90),  'to' => $now->copy()->addSecond(), 'label' => 'Last 90 days',  'key' => '90d', 'days' => 90],
            default => ['from' => $now->copy()->subDays(7), 'to' => $now->copy()->addSecond(), 'label' => 'Last 7 days',   'key' => '7d',  'days' => 7],
        };
    }

    private function kpiStrip(Collection $rows, array $range, int $wsId): array
    {
        $total    = $rows->count();
        $sent     = $rows->where('direction', 'out')->filter(fn ($r) => !in_array($r['status'] ?? '', ['failed', 'error'], true))->count();
        $received = $rows->where('direction', 'in')->count();
        $failed   = $rows->where('direction', 'out')->filter(fn ($r) => in_array($r['status'] ?? '', ['failed', 'error'], true))->count();

        $prevFrom = $range['from']->copy()->subDays($range['days']);
        $prevTo   = $range['from']->copy();
        $prevCounts = $this->stream->counts($wsId, $prevFrom, $prevTo);
        $prevTotal = $prevCounts['total'];
        $deltaPct  = $prevTotal > 0
            ? (int) round((($total - $prevTotal) / $prevTotal) * 100)
            : ($total > 0 ? 100 : 0);

        // Avg first-response — group inbox rows by phone/contact, take
        // first inbound + first subsequent outbound, diff timestamps.
        $byPhone = $rows->where('source', 'inbox')->groupBy('phone');
        $diffs = [];
        foreach ($byPhone as $phone => $msgs) {
            $sorted = $msgs->sortBy(fn ($m) => optional($m['when'])->getTimestamp());
            $in  = $sorted->firstWhere('direction', 'in');
            $out = $sorted->firstWhere('direction', 'out');
            if ($in && $out && $in['when'] && $out['when'] && $out['when']->gt($in['when'])) {
                $diffs[] = $out['when']->diffInSeconds($in['when']);
            }
        }
        $avgReplySeconds = count($diffs) ? (int) round(array_sum($diffs) / count($diffs)) : null;

        return [
            'total'        => $total,
            'sent'         => $sent,
            'received'     => $received,
            'failed'       => $failed,
            'sentPct'      => $total ? round($sent / $total * 100) : 0,
            'receivedPct'  => $total ? round($received / $total * 100) : 0,
            'failPct'      => $sent ? round($failed / max(1, $sent) * 100, 1) : 0,
            'deltaPct'     => $deltaPct,
            'avgReplyHuman' => $this->humanDuration($avgReplySeconds),
        ];
    }

    private function dirCounts(Collection $rows): array
    {
        return [
            'all'  => $rows->count(),
            'out'  => $rows->where('direction', 'out')->count(),
            'in'   => $rows->where('direction', 'in')->count(),
            'fail' => $rows->where('direction', 'out')->filter(fn ($r) => in_array($r['status'] ?? '', ['failed', 'error'], true))->count(),
        ];
    }

    private function volumeSeries(Collection $rows, array $range, string $bucket): array
    {
        $bucket = in_array($bucket, ['hourly', 'daily', 'weekly'], true) ? $bucket : 'daily';
        $now = now();

        if ($bucket === 'hourly') {
            $points = 24;
            $keyOf  = fn (Carbon $t) => $t->format('Y-m-d H');
            $labels = collect(range($points - 1, 0))->map(fn ($i) => $now->copy()->subHours($i)->format('H:00'));
            $keys   = collect(range($points - 1, 0))->map(fn ($i) => $now->copy()->subHours($i)->format('Y-m-d H'));
        } elseif ($bucket === 'weekly') {
            $points = max(1, (int) ceil($range['days'] / 7));
            $keyOf  = fn (Carbon $t) => $t->copy()->startOfWeek()->format('Y-m-d');
            $keys   = collect(range($points - 1, 0))->map(fn ($i) => $now->copy()->subWeeks($i)->startOfWeek()->format('Y-m-d'));
            $labels = $keys->map(fn ($k) => Carbon::parse($k)->format('M d'));
        } else {
            $points = $range['days'];
            $keyOf  = fn (Carbon $t) => $t->toDateString();
            $keys   = collect(range($points - 1, 0))->map(fn ($i) => $now->copy()->subDays($i)->toDateString());
            $labels = $keys->map(fn ($k) => $range['days'] <= 7 ? Carbon::parse($k)->format('D') : Carbon::parse($k)->format('M d'));
        }

        $byBucket = $rows->filter(fn ($r) => $r['when'])->groupBy(fn ($r) => $keyOf($r['when']));

        $sent = $keys->map(fn ($k) => ($byBucket->get($k) ?? collect())->where('direction', 'out')->filter(fn ($r) => !in_array($r['status'] ?? '', ['failed', 'error'], true))->count())->all();
        $rcv  = $keys->map(fn ($k) => ($byBucket->get($k) ?? collect())->where('direction', 'in')->count())->all();
        $fail = $keys->map(fn ($k) => ($byBucket->get($k) ?? collect())->where('direction', 'out')->filter(fn ($r) => in_array($r['status'] ?? '', ['failed', 'error'], true))->count())->all();

        return ['labels' => $labels->all(), 'sent' => $sent, 'received' => $rcv, 'failed' => $fail, 'bucket' => $bucket];
    }

    private function directionMix(Collection $rows): array
    {
        $sent = $rows->where('direction', 'out')->filter(fn ($r) => !in_array($r['status'] ?? '', ['failed', 'error'], true))->count();
        $rcv  = $rows->where('direction', 'in')->count();
        $fail = $rows->where('direction', 'out')->filter(fn ($r) => in_array($r['status'] ?? '', ['failed', 'error'], true))->count();
        return ['sent' => $sent, 'received' => $rcv, 'failed' => $fail, 'total' => $sent + $rcv + $fail];
    }

    private function typeMix(Collection $rows): array
    {
        $buckets = ['Plain text' => 0, 'Auto-reply' => 0, 'Campaign' => 0, 'Broadcast' => 0, 'Scheduled' => 0, 'Media' => 0];
        foreach ($rows as $r) {
            $t = $this->classifyType($r);
            $key = match ($t) {
                'auto_reply' => 'Auto-reply',
                'campaign'   => 'Campaign',
                'broadcast'  => 'Broadcast',
                'scheduled'  => 'Scheduled',
                'image', 'video', 'document', 'location' => 'Media',
                default      => 'Plain text',
            };
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }
        return ['labels' => array_keys($buckets), 'data' => array_values($buckets)];
    }

    private function topConversations(Collection $rows): array
    {
        $byPhone = $rows->where('source', 'inbox')->whereNotNull('phone')
            ->groupBy('phone')
            ->map(fn ($items, $phone) => [
                'phone'   => $phone,
                'count'   => $items->count(),
                'last_at' => $items->max(fn ($r) => optional($r['when'])->getTimestamp()),
                'name'    => $items->pluck('contact_name')->filter()->first() ?? $phone,
            ])
            ->sortByDesc('count')
            ->values()
            ->take(4);

        $palette = ['from-wa-teal to-wa-deep', 'from-accent-amber to-accent-coral', 'from-wa-deep to-ink-900', 'from-[#5B3D8A] to-[#13478A]'];

        return $byPhone->values()->map(function ($r, $i) use ($palette) {
            $title = (string) $r['name'];
            return [
                'id'       => 0,
                'title'    => $title,
                'initials' => $this->initials($title),
                'gradient' => $palette[$i % count($palette)],
                'count'    => (int) $r['count'],
                'lastAt'   => $r['last_at'] ? Carbon::createFromTimestamp($r['last_at'])->format('H:i') : '—',
                'delta'    => '',
            ];
        })->all();
    }

    /**
     * Map a UnifiedMessageStream row → the original presentRow() shape
     * that _rows.blade.php expects. Source-specific cells get a small
     * pill in the Type column so admins can tell auto-reply / campaign
     * apart from regular sends without changing the column layout.
     */
    /**
     * Convert a stored timestamp to the workspace display timezone. Timestamps
     * are persisted in UTC; the rest of the app renders them in the admin's
     * `default_timezone` (e.g. Asia/Kolkata). This page was formatting them raw,
     * so India users saw UTC. Accepts a Carbon or a string (treated as UTC).
     */
    private function toDisplayTz($when): ?\Carbon\Carbon
    {
        if (empty($when)) return null;
        // Source timezone: a Carbon from Eloquent already carries the app
        // timezone, so setTimezone() converts it correctly. A raw string is
        // stored in the app timezone (that's what Laravel writes), so parse
        // it in app.timezone — NOT a hard-coded UTC, which double-shifted
        // times on installs where APP_TIMEZONE isn't UTC.
        $appTz     = (string) (config('app.timezone') ?: 'UTC');
        $displayTz = $this->displayTz ?: $appTz;
        $c = $when instanceof \Carbon\Carbon
            ? $when->copy()
            : \Carbon\Carbon::parse((string) $when, $appTz);
        try {
            return $c->setTimezone($displayTz);
        } catch (\Throwable $e) {
            return $c;
        }
    }

    /**
     * Display timezone for the current request. Resolved from the SAME chain
     * the rest of the app uses so this page's times line up with Team Inbox,
     * Campaigns, and Broadcasts instead of showing raw UTC:
     *   workspace.timezone → user.timezone → admin default_timezone → app tz.
     * The admin default fallback matches AdminPagesController (Asia/Kolkata),
     * so a fresh install still lands on a sensible zone rather than UTC.
     */
    private function resolveDisplayTz($ws, $user): string
    {
        $candidates = [
            optional($ws)->timezone,
            optional($user)->timezone,
            \App\Models\SystemSetting::get('default_timezone', null),
            config('app.timezone'),
        ];
        foreach ($candidates as $tz) {
            if (is_string($tz) && trim($tz) !== '') {
                // Guard against a stored garbage zone that setTimezone() would throw on.
                try { (new \DateTimeZone(trim($tz))); return trim($tz); } catch (\Throwable $e) { /* try next */ }
            }
        }
        return 'UTC';
    }

    private function presentRow(array $r): array
    {
        $dir   = $r['direction'] ?? 'in';
        $type  = $this->classifyType($r);
        $phone = (string) ($r['phone'] ?? '');
        $name  = $r['contact_name'] ?: ($phone ?: $r['source_label'] . ' · ' . $r['id']);
        // Convert to the workspace display timezone (same one the rest of the
        // app uses) — timestamps are stored UTC, and this page was formatting
        // them raw, so India users saw UTC instead of IST.
        $when  = $this->toDisplayTz($r['when']);

        return [
            'id'             => $r['id'],
            // Pass source + meta through so the row's "open" eye icon
            // can route to the source-specific detail page
            // (/scheduled/{id}, /broadcasts/{id}, /campaigns/{id})
            // instead of the hardcoded /chat fallback.
            'source'         => $r['source'] ?? '',
            'meta'           => $r['meta'] ?? [],
            'time'           => $when ? $when->format('H:i') : '—',
            'date'           => $this->humanDate($when),
            'isoTime'        => $when ? $when->toIso8601String() : null,
            'direction'      => $dir,
            'directionLabel' => $dir === 'out' ? 'Outgoing' : 'Incoming',
            'status'         => $r['status'] ?? '',
            'statusLabel'    => $this->statusLabel($r),
            'statusBadge'    => $this->statusBadge($r),
            'type'           => $type,
            'typeLabel'      => $this->typeLabel($type),
            'typePill'       => $this->typePill($type),
            'name'           => $name,
            'initials'       => $this->initials($name),
            'avatar'         => $this->avatarGradient(crc32($r['id'])),
            'phone'          => $phone,
            'bodyShort'      => $this->snippet((string) ($r['body'] ?? ''), 100),
            'mediaIcon'      => $this->mediaIconForType($type),
            'mediaLabel'     => $this->mediaLabelFor($r, $type),
        ];
    }

    private function presentDetail(array $r): array
    {
        $row  = $this->presentRow($r);
        return array_merge($row, [
            'body'           => (string) ($r['body'] ?? ''),
            'conversationId' => $r['meta']['conversation_id'] ?? null,
            'sourceLabel'    => $r['source_label'] ?? '',
            'failureReason'  => (string) ($r['meta']['error'] ?? ''),
            'sentAtFull'     => $this->toDisplayTz($r['when'])?->format('M d, Y H:i') ?? '—',
            'timeline'       => $this->timeline($r),
            'metadata'       => [
                ['label' => 'Message ID',   'value' => $r['id']],
                ['label' => 'Source',       'value' => $r['source_label'] ?? '—'],
                ['label' => 'Phone',        'value' => $r['phone'] ?: '—'],
                ['label' => 'Status',       'value' => $r['status'] ?: '—'],
                ['label' => 'Language',     'value' => $r['meta']['language'] ?? '—'],
                ['label' => 'Created',      'value' => $this->toDisplayTz($r['when'])?->format('M d, Y H:i') ?: '—'],
            ],
            'canResend'      => false,    // unified view doesn't expose source-specific resend
        ]);
    }

    private function timeline(array $r): array
    {
        return [
            ['label' => 'Recorded at',
             'time'  => $this->toDisplayTz($r['when'])?->format('H:i') ?? '',
             'state' => 'done'],
        ];
    }

    private function classifyType(array $r): string
    {
        // Source-specific sources surface as their own type so the
        // Type pill in the table makes auto-reply / campaign visible.
        if (in_array($r['source'] ?? '', ['auto_reply', 'campaign', 'broadcast', 'scheduled'], true)) {
            return $r['source'];
        }
        $mt = $r['meta']['media_type'] ?? null;
        if ($mt) {
            $mt = strtolower($mt);
            if (str_starts_with($mt, 'image'))    return 'image';
            if (str_starts_with($mt, 'video'))    return 'video';
            if (str_starts_with($mt, 'audio'))    return 'audio';
            return 'document';
        }
        if (!empty($r['meta']['template_id'])) return 'template';
        return 'text';
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'auto_reply' => 'Auto-reply',
            'campaign'   => 'Campaign',
            'broadcast'  => 'Broadcast',
            'scheduled'  => 'Scheduled',
            'template'   => 'Template',
            'image'      => 'Image',
            'video'      => 'Video',
            'audio'      => 'Audio',
            'document'   => 'Document',
            'location'   => 'Location',
            default      => 'Plain text',
        };
    }

    private function typePill(string $type): string
    {
        return match ($type) {
            'auto_reply' => 'bg-[#F3E9FF] text-[#5B3D8A]',
            'campaign'   => 'bg-accent-amber/20 text-[#7B5A14]',
            'broadcast'  => 'bg-[#D9E5F2] text-[#13478A]',
            'scheduled'  => 'bg-[#FFF4E0] text-[#7B5A14]',
            'template'   => 'bg-wa-deep/10 text-wa-deep',
            'image'      => 'bg-[#D9E5F2] text-[#13478A]',
            'video'      => 'bg-accent-amber/20 text-[#7B5A14]',
            'audio'      => 'bg-[#F3E9FF] text-[#5B3D8A]',
            'document'   => 'bg-accent-amber/20 text-[#7B5A14]',
            'location'   => 'bg-[#D9E5F2] text-[#13478A]',
            default      => 'bg-paper-100 text-ink-700',
        };
    }

    private function statusLabel(array $r): string
    {
        if (($r['direction'] ?? '') === 'in') return 'Inbound';
        return ucfirst((string) ($r['status'] ?? ''));
    }

    private function statusBadge(array $r): array
    {
        if (($r['direction'] ?? '') === 'in') return ['dot' => 'bg-ink-700', 'fg' => 'text-ink-700'];
        $s = $r['status'] ?? '';
        return match (true) {
            in_array($s, ['read'], true)                     => ['dot' => 'bg-wa-green', 'fg' => 'text-wa-deep'],
            in_array($s, ['delivered', 'sent', 'fired', 'paid'], true) => ['dot' => 'bg-wa-green', 'fg' => 'text-wa-deep'],
            in_array($s, ['failed', 'error'], true)           => ['dot' => 'bg-accent-coral', 'fg' => 'text-accent-coral'],
            in_array($s, ['scheduled', 'pending'], true)      => ['dot' => 'bg-paper-200', 'fg' => 'text-ink-500'],
            default                                            => ['dot' => 'bg-paper-200', 'fg' => 'text-ink-500'],
        };
    }

    private function mediaIconForType(string $type): ?string
    {
        $cls = 'inline-block w-3 h-3 align-text-bottom';
        return match ($type) {
            'image'    => '<svg viewBox="0 0 16 16" class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="2" y="3" width="12" height="10" rx="1.5"/><circle cx="6" cy="7" r="1"/><path d="m3 12 3-3 2.5 2.5L11 8l2 2"/></svg>',
            'video'    => '<svg viewBox="0 0 16 16" class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="2" y="3.5" width="9" height="9" rx="1.5"/><path d="m11 6.5 3-1.5v6l-3-1.5z"/></svg>',
            'document' => '<svg viewBox="0 0 16 16" class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M4 2h5l3 3v9H4z"/><path d="M9 2v3h3"/></svg>',
            default    => null,
        };
    }

    private function mediaLabelFor(array $r, string $type): ?string
    {
        if (in_array($type, ['image', 'video', 'audio', 'document'], true) && !empty($r['meta']['media_path'])) {
            return basename((string) $r['meta']['media_path']);
        }
        return null;
    }

    private function avatarGradient(int $seed): string
    {
        $palette = [
            'from-wa-teal to-wa-deep',
            'from-accent-amber to-accent-coral',
            'from-wa-deep to-ink-900',
            'from-[#5B3D8A] to-[#13478A]',
            'from-[#7B5A14] to-accent-amber',
        ];
        return $palette[abs($seed) % count($palette)];
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

    private function snippet(string $s, int $max): string
    {
        $s = trim($s);
        if ($s === '') return '';
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
    }

    private function humanDate(?Carbon $when): string
    {
        if (!$when) return '—';
        if ($when->isToday())     return 'today';
        if ($when->isYesterday()) return 'yesterday';
        return $when->format('M d');
    }

    private function humanDuration(?int $seconds): string
    {
        if ($seconds === null) return '—';
        if ($seconds < 60)     return $seconds . 's';
        if ($seconds < 3600)   return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        return $h . 'h ' . $m . 'm';
    }
}
