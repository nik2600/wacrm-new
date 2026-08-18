@php
    // Every value below comes from the controller — no static demo data.
    $row = $row ?? null;
    $recipientSummary = $recipientSummary ?? '—';
    $statusBadge = $statusBadge ?? [
        'cls' => 'bg-paper-100 text-ink-500',
        'dot' => 'bg-ink-500/40',
        'label' => 'Unknown',
    ];
    $previewBody = $previewBody ?? null;
    $recipients = $recipients ?? collect();
    $tabCounts = $tabCounts ?? ['all' => 0, 'sent' => 0, 'delivered' => 0, 'read' => 0, 'failed' => 0, 'pending' => 0];

    if (!$row) {
        abort(404);
    }

    $tz = $row->timezone ?: 'UTC';
    $scheduledLocal = $row->scheduled_time?->copy()->setTimezone($tz);
    $nextRunLocal = $row->next_run_at?->copy()->setTimezone($tz);
    $lastRunLocal = $row->last_run_at?->copy()->setTimezone($tz);
    $completedLocal = $row->completed_at?->copy()->setTimezone($tz);

    $isFinished = in_array($row->status, ['completed', 'cancelled', 'failed'], true);
    $isActive = in_array($row->status, ['scheduled', 'running'], true);
    $isPaused = $row->status === 'paused';

    $totalRecipients = (int) ($row->total_recipients ?? 0) ?: $tabCounts['all'];
    $totalSent = $tabCounts['sent'];
    $totalDelivered = $tabCounts['delivered'];
    $totalRead = $tabCounts['read'];
    $totalFailed = $tabCounts['failed'];
    $totalPending = $tabCounts['pending'];

    $deliveryRate = $totalSent > 0 ? round(($totalDelivered / $totalSent) * 100, 1) : 0;
    $readRate = $totalSent > 0 ? round(($totalRead / $totalSent) * 100, 1) : 0;
    $failRate = $totalRecipients > 0 ? round(($totalFailed / $totalRecipients) * 100, 1) : 0;
    $progressPct = $totalRecipients > 0 ? min(100, round(($totalSent / $totalRecipients) * 100, 1)) : 0;

    $deviceLabel = $row->device?->id
        ? (trim((string) $row->device->device_name) ?:
        'Device #' . $row->device->id)
        : '—';
    $devicePhone = $row->device?->id
        ? '+' . ltrim((string) ($row->device->country_code ?? ''), '+') . ' ' . $row->device->phone_number
        : null;

    $templateName = $row->template?->id ? $row->template->template_name : null;
    $templateCat = $row->template?->id ? ($row->template->category ?: 'Marketing') : null;

    // Donut series — real counters [delivered, sent-not-delivered, failed, pending].
    $donutSeries = [$totalDelivered, max(0, $totalSent - $totalDelivered), $totalFailed, $totalPending];

    // Engagement-curve data: build buckets relative to the first sent_at
    // we see in the pivot. With zero activity yet the chart shows
    // empty-state. Cheap PHP-side rollup (max few hundred recipients).
    $bucketEdges = [0, 5, 15, 30, 60, 120, 240, 480, 720, 1440, 2880, 4320]; // minutes
    $bucketLabels = ['0m', '5m', '15m', '30m', '1h', '2h', '4h', '8h', '12h', '24h', '48h', '72h'];
    $reads = array_fill(0, count($bucketEdges), 0);
    $sentByBucket = array_fill(0, count($bucketEdges), 0);
    $firstSent = $recipients->whereNotNull('sent_at')->sortBy('sent_at')->first()?->sent_at;
    if ($firstSent) {
        foreach ($recipients as $r) {
            if ($r->sent_at) {
                $m = $firstSent->diffInMinutes($r->sent_at);
                for ($i = count($bucketEdges) - 1; $i >= 0; $i--) {
                    if ($m >= $bucketEdges[$i]) {
                        $sentByBucket[$i]++;
                        break;
                    }
                }
            }
            if ($r->read_at) {
                $m = $firstSent->diffInMinutes($r->read_at);
                for ($i = count($bucketEdges) - 1; $i >= 0; $i--) {
                    if ($m >= $bucketEdges[$i]) {
                        $reads[$i]++;
                        break;
                    }
                }
            }
        }
        // Cumulative (each bucket = running total).
        for ($i = 1, $n = count($reads); $i < $n; $i++) {
            $reads[$i] += $reads[$i - 1];
            $sentByBucket[$i] += $sentByBucket[$i - 1];
        }
    }
    $engagementHasData = $totalSent > 0 || $totalRead > 0;
@endphp

<x-layouts.user :title="__('Scheduled Message — ') . ($row->schedule_name ?: '#' . $row->id)" nav-key="more" page="user-scheduled-detail">

    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/scheduled') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg></a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Scheduled / Analytics') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Schedule') }} <span
                            class="italic text-wa-deep">{{ __('analytics') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $statusBadge['cls'] }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $statusBadge['dot'] }}"></span>{{ $statusBadge['label'] }}
                </span>
            </div>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6 space-y-6" data-sched-id="{{ $row->id }}"
        data-donut='@json($donutSeries)' data-engagement-categories='@json($bucketLabels)'
        data-engagement-reads='@json($reads)' data-engagement-sent='@json($sentByBucket)'
        data-engagement-has-data="{{ $engagementHasData ? '1' : '0' }}">

        {{-- ─────────── Header card ─────────── --}}
        <section class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
            <div class="flex items-start justify-between gap-6 flex-wrap">
                <div class="flex items-start gap-4 min-w-0 flex-1">
                    <span class="w-12 h-12 rounded-xl bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <rect x="2.5" y="3.5" width="11" height="10" rx="1.5" />
                            <path d="M2.5 6.5h11M5 2v3M11 2v3" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Scheduled message') }}</div>
                        <h1 class="font-serif text-[28px] leading-tight tracking-[-0.01em] mt-0.5 truncate">
                            {{ $row->schedule_name ?: 'Untitled' }}</h1>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            @if ($templateName)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-deep/10 text-wa-deep text-[10.5px] font-mono">Template
                                    · {{ $templateName }}</span>
                            @else
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ ucfirst($row->template_type ?: 'text') }}</span>
                            @endif
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ ucfirst($row->recipient_type) }}
                                · {{ $recipientSummary }}</span>
                            @if ($scheduledLocal)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ $scheduledLocal->format('M j · H:i') }}
                                    {{ $tz }}</span>
                            @endif
                            @if ($row->is_recurring)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">Recurs
                                    · {{ $row->repeat_interval }} × {{ $row->repeat_every ?: 1 }}</span>
                            @endif
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ $deviceLabel }}
                                @if ($devicePhone)
                                    · <span class="text-ink-500">{{ $devicePhone }}</span>
                                @endif
                            </span>
                            @if ($isActive && $nextRunLocal)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Fires
                                    {{ $nextRunLocal->diffForHumans() }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-1.5 shrink-0">
                    @if ($isActive)
                        <button data-sched-action="pause" data-sched-id="{{ $row->id }}"
                            class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M5 3v10M11 3v10" />
                            </svg>{{ __('Pause') }}
                        </button>
                    @elseif ($isPaused)
                        <button data-sched-action="resume" data-sched-id="{{ $row->id }}"
                            class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M5 3l8 5-8 5z" />
                            </svg>{{ __('Resume') }}
                        </button>
                    @endif
                    @if (!$isFinished)
                        <button data-sched-action="run-now" data-sched-id="{{ $row->id }}"
                            class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M2 14l13-6L2 2v5l9 1-9 1z" />
                            </svg>{{ __('Run now') }}
                        </button>
                        <button data-sched-action="cancel" data-sched-id="{{ $row->id }}"
                            class="px-3 py-1.5 rounded-full border border-accent-coral/40 hover:bg-accent-coral/10 text-accent-coral text-[12px] font-medium inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M4 4l8 8M12 4l-8 8" />
                            </svg>{{ __('Cancel') }}
                        </button>
                    @endif
                    <button data-sched-action="destroy" data-sched-id="{{ $row->id }}"
                        class="px-3 py-1.5 rounded-full border border-accent-coral/40 hover:bg-accent-coral/10 text-accent-coral text-[12px] font-medium inline-flex items-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" />
                        </svg>{{ __('Delete') }}
                    </button>
                </div>
            </div>
        </section>

        {{-- ─────────── KPI strip — every cell driven by the pivot ─────────── --}}
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Recipients') }}</span>
                    <span class="text-[10px] text-ink-500 font-mono">{{ __('target') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none">{{ number_format($totalRecipients) }}</span>
                    <span class="text-[11px] text-ink-500">{{ ucfirst($row->recipient_type) }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Delivered') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono">{{ $deliveryRate }}%</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none">{{ number_format($totalDelivered) }}</span>
                    <span class="text-[11px] text-ink-500">{{ number_format($totalFailed) }}
                        {{ __('failed') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Read') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono">{{ $readRate }}%</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none">{{ number_format($totalRead) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('of sent') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sent') }}</span>
                    <span class="text-[10px] text-ink-500 font-mono">{{ $progressPct }}%</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none">{{ number_format($totalSent) }}</span>
                    <span class="text-[11px] text-ink-500">{{ number_format($totalPending) }}
                        {{ __('pending') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Status') }}</span>
                    @if ($row->is_recurring)
                        <span class="text-[10px] text-wa-deep font-mono">{{ __('recurring') }}</span>
                    @endif
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[22px] leading-none">{{ $statusBadge['label'] }}</span>
                </div>
                <div class="text-[10.5px] text-ink-500 mt-1 truncate">
                    @if ($isActive && $nextRunLocal)
                        fires {{ $nextRunLocal->diffForHumans() }}
                    @elseif ($isPaused)
                        paused
                    @elseif ($completedLocal)
                        done {{ $completedLocal->diffForHumans() }}
                    @else—
                    @endif
                </div>
            </div>
        </section>

        {{-- ─────────── Engagement curve + Delivery funnel ─────────── --}}
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Engagement curve') }}</div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Reads & sends after fire') }}
                        </h3>
                    </div>
                    <div class="flex items-center gap-1 text-[11px] font-mono text-ink-500">
                        <button class="px-2.5 py-1 rounded-full bg-wa-deep text-paper-0">{{ __('Reads') }}</button>
                        <button class="px-2.5 py-1 rounded-full hover:bg-paper-100">{{ __('Sent') }}</button>
                    </div>
                </div>
                @if ($engagementHasData)
                    <div id="chart-engage" class="h-[260px]"></div>
                @else
                    <div class="h-[260px] grid place-items-center text-center">
                        <div>
                            <div class="font-mono text-[11px] text-ink-500">{{ __('No engagement data yet') }}</div>
                            <div class="text-[11px] text-ink-500 mt-1">
                                {{ __('Curve fills in once the bot starts firing.') }}</div>
                        </div>
                    </div>
                @endif
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Delivery funnel') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('From send to action') }}</h3>
                <div class="space-y-3">
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1">
                            <span>{{ __('Recipients') }}</span><span
                                class="font-mono text-ink-900">{{ number_format($totalRecipients) }}</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-deep" style="width:100%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1">
                            <span>{{ __('Sent') }}</span><span
                                class="font-mono text-ink-900">{{ number_format($totalSent) }} /
                                {{ $progressPct }}%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-deep" style="width:{{ $progressPct }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1">
                            <span>{{ __('Delivered') }}</span><span
                                class="font-mono text-ink-900">{{ number_format($totalDelivered) }} /
                                {{ $deliveryRate }}%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-deep"
                                style="width:{{ $totalSent > 0 ? round(($totalDelivered / $totalSent) * 100, 1) : 0 }}%">
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1">
                            <span>{{ __('Read') }}</span><span
                                class="font-mono text-ink-900">{{ number_format($totalRead) }} /
                                {{ $readRate }}%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-deep"
                                style="width:{{ $totalSent > 0 ? round(($totalRead / $totalSent) * 100, 1) : 0 }}%">
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1"><span
                                class="{{ $totalFailed > 0 ? 'text-accent-coral' : '' }}">Failed</span><span
                                class="font-mono text-ink-900">{{ number_format($totalFailed) }} /
                                {{ $failRate }}%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-accent-coral"
                                style="width:{{ $totalRecipients > 0 ? round(($totalFailed / $totalRecipients) * 100, 1) : 0 }}%">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ─────────── Status mix + Tabbed Recipient table ─────────── --}}
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Status mix') }}
                </div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Delivery status') }}</h3>
                @if (array_sum($donutSeries) === 0)
                    <div class="h-[200px] grid place-items-center text-center">
                        <div>
                            <div class="font-mono text-[11px] text-ink-500">{{ __('No send activity yet') }}</div>
                            <div class="text-[11px] text-ink-500 mt-1">{{ __('Donut fills in once the bot fires.') }}
                            </div>
                        </div>
                    </div>
                @else
                    <div id="chart-status" class="h-[200px]"></div>
                @endif
                <div class="mt-3 space-y-1.5 text-[12px]">
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-deep"></span>Delivered</span><span
                            class="font-mono text-ink-700">{{ number_format($totalDelivered) }}</span></div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-teal"></span>Sent · not delivered</span><span
                            class="font-mono text-ink-700">{{ number_format(max(0, $totalSent - $totalDelivered)) }}</span>
                    </div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-accent-coral"></span>Failed</span><span
                            class="font-mono text-ink-700">{{ number_format($totalFailed) }}</span></div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-paper-200"></span>Pending</span><span
                            class="font-mono text-ink-700">{{ number_format($totalPending) }}</span></div>
                </div>
            </div>

            {{-- Tabbed recipient table. Server renders all rows; JS filters by
 status. data-status maps the tabs (sent includes delivered/read;
 delivered includes read). Tabs match the KPI strip's semantics. --}}
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] shadow-card min-w-0">
                <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Recipients') }}</div>
                        <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Who got what') }}</h3>
                    </div>
                    <div class="flex items-center gap-1 bg-paper-50 rounded-full p-1 max-w-full overflow-x-auto" id="rec-tabs">
                        <button data-rec-tab="all"
                            class="px-2.5 py-1 rounded-full text-[11.5px] font-semibold bg-wa-deep text-paper-0 whitespace-nowrap shrink-0">{{ __('All') }}
                            <span class="opacity-70">· {{ number_format($tabCounts['all']) }}</span></button>
                        <button data-rec-tab="sent"
                            class="px-2.5 py-1 rounded-full text-[11.5px] font-semibold text-ink-600 hover:bg-paper-100 whitespace-nowrap shrink-0">{{ __('Sent') }}
                            <span class="opacity-70">· {{ number_format($tabCounts['sent']) }}</span></button>
                        <button data-rec-tab="delivered"
                            class="px-2.5 py-1 rounded-full text-[11.5px] font-semibold text-ink-600 hover:bg-paper-100 whitespace-nowrap shrink-0">{{ __('Delivered') }}
                            <span class="opacity-70">· {{ number_format($tabCounts['delivered']) }}</span></button>
                        <button data-rec-tab="read"
                            class="px-2.5 py-1 rounded-full text-[11.5px] font-semibold text-ink-600 hover:bg-paper-100 whitespace-nowrap shrink-0">{{ __('Read') }}
                            <span class="opacity-70">· {{ number_format($tabCounts['read']) }}</span></button>
                        <button data-rec-tab="failed"
                            class="px-2.5 py-1 rounded-full text-[11.5px] font-semibold text-ink-600 hover:bg-paper-100 whitespace-nowrap shrink-0">{{ __('Failed') }}
                            <span class="opacity-70">· {{ number_format($tabCounts['failed']) }}</span></button>
                        <button data-rec-tab="pending"
                            class="px-2.5 py-1 rounded-full text-[11.5px] font-semibold text-ink-600 hover:bg-paper-100 whitespace-nowrap shrink-0">{{ __('Pending') }}
                            <span class="opacity-70">· {{ number_format($tabCounts['pending']) }}</span></button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    @if ($recipients->isEmpty())
                        <div class="text-center py-10 text-[12.5px] text-ink-500">
                            {{ __('No recipients on file for this schedule.') }}</div>
                    @else
                        <table class="w-full text-[12.5px]">
                            <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                                <tr>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                        {{ __('Recipient') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                        {{ __('Phone') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                        {{ __('Status') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                        {{ __('When') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                        {{ __('Note') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-paper-200" id="rec-tbody">
                                @foreach ($recipients as $rec)
                                    @php
                                        $contact = $rec->contact?->id ? $rec->contact : null;
                                        $name = $contact?->name ?: ($contact?->first_name ?: '—');
                                        $statusBadgePill = match ($rec->status) {
                                            'pending' => ['cls' => 'bg-paper-100 text-ink-600', 'label' => 'Pending'],
                                            'sent' => ['cls' => 'bg-accent-amber/15 text-[#7B5A14]', 'label' => 'Sent'],
                                            'delivered' => [
                                                'cls' => 'bg-wa-teal/15 text-wa-teal',
                                                'label' => 'Delivered',
                                            ],
                                            'read' => ['cls' => 'bg-wa-deep/10 text-wa-deep', 'label' => 'Read'],
                                            'failed' => [
                                                'cls' => 'bg-accent-coral/15 text-accent-coral',
                                                'label' => 'Failed',
                                            ],
                                            default => ['cls' => 'bg-paper-100 text-ink-500', 'label' => $rec->status],
                                        };
                                        $when =
                                            $rec->failed_at ?? ($rec->read_at ?? ($rec->delivered_at ?? $rec->sent_at));
                                    @endphp
                                    <tr class="rec-row" data-status="{{ $rec->status }}">
                                        <td class="px-4 py-2.5">
                                            <div class="font-medium truncate">{{ $name }}</div>
                                        </td>
                                        <td class="px-2 py-2.5 font-mono text-[11px] text-ink-700">
                                            +{{ mask_phone($rec->phone) }}</td>
                                        <td class="px-2 py-2.5">
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-mono {{ $statusBadgePill['cls'] }}">{{ $statusBadgePill['label'] }}</span>
                                        </td>
                                        <td class="px-2 py-2.5 font-mono text-[11px] text-ink-700">
                                            {{ $when ? $when->copy()->setTimezone($tz)->format('M j · H:i') : '—' }}
                                        </td>
                                        <td class="px-2 py-2.5 text-[11px] text-ink-700 truncate max-w-[260px]">
                                            {{ $rec->error_message ?: ($rec->wa_message_id ? 'msg ' . substr($rec->wa_message_id, 0, 8) . '…' : '—') }}
                                        </td>
                                    </tr>
                                @endforeach
                                <tr id="rec-empty-row" class="hidden">
                                    <td colspan="5" class="px-4 py-10 text-center text-[12.5px] text-ink-500">
                                        {{ __('No recipients in this tab yet.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </section>

        {{-- ─────────── Schedule details + Message preview ─────────── --}}
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Schedule details') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Configuration') }}</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 text-[12.5px]">
                    <div class="flex items-center justify-between border-b border-paper-100 py-1.5">
                        <dt class="text-ink-500">{{ __('Schedule type') }}</dt>
                        <dd class="font-mono text-ink-900">{{ ucfirst($row->schedule_type ?: 'once') }}</dd>
                    </div>
                    <div class="flex items-center justify-between border-b border-paper-100 py-1.5">
                        <dt class="text-ink-500">{{ __('Message type') }}</dt>
                        <dd class="font-mono text-ink-900">{{ ucfirst($row->template_type ?: 'text') }}</dd>
                    </div>
                    @if ($scheduledLocal)
                        <div class="flex items-center justify-between border-b border-paper-100 py-1.5">
                            <dt class="text-ink-500">{{ __('Scheduled at') }}</dt>
                            <dd class="font-mono text-ink-900">{{ $scheduledLocal->format('Y-m-d H:i') }}
                                {{ $tz }}</dd>
                        </div>
                    @endif
                    @if ($nextRunLocal)
                        <div class="flex items-center justify-between border-b border-paper-100 py-1.5">
                            <dt class="text-ink-500">{{ __('Next run') }}</dt>
                            <dd class="font-mono text-ink-900">{{ $nextRunLocal->format('Y-m-d H:i') }}
                                {{ $tz }}</dd>
                        </div>
                    @endif
                    @if ($lastRunLocal)
                        <div class="flex items-center justify-between border-b border-paper-100 py-1.5">
                            <dt class="text-ink-500">{{ __('Last run') }}</dt>
                            <dd class="font-mono text-ink-900">{{ $lastRunLocal->format('Y-m-d H:i') }}
                                {{ $tz }}</dd>
                        </div>
                    @endif
                    @if ($row->is_recurring)
                        <div class="flex items-center justify-between border-b border-paper-100 py-1.5">
                            <dt class="text-ink-500">{{ __('Repeats every') }}</dt>
                            <dd class="font-mono text-ink-900">{{ $row->repeat_every ?: 1 }}
                                {{ $row->repeat_interval ?: 'day' }}(s)</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between border-b border-paper-100 py-1.5">
                        <dt class="text-ink-500">{{ __('From device') }}</dt>
                        <dd class="font-mono text-ink-900 truncate ml-2">{{ $deviceLabel }}</dd>
                    </div>
                    @if ($devicePhone)
                        <div class="flex items-center justify-between border-b border-paper-100 py-1.5">
                            <dt class="text-ink-500">{{ __('Sender phone') }}</dt>
                            <dd class="font-mono text-ink-900">{{ $devicePhone }}</dd>
                        </div>
                    @endif
                    @if ($row->node_schedule_id)
                        <div class="flex items-center justify-between border-b border-paper-100 py-1.5 md:col-span-2">
                            <dt class="text-ink-500">{{ __('Node schedule id') }}</dt>
                            <dd class="font-mono text-[11px] text-ink-700 truncate ml-2">{{ $row->node_schedule_id }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
            <div class="bg-wa-deep rounded-[14px] p-5 shadow-soft text-paper-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/60">
                    {{ __('Message preview') }}</div>
                <div class="font-serif text-[20px] leading-tight mt-1">{{ __('What recipients see') }}</div>
                <div
                    class="mt-4 bg-paper-0/10 border border-paper-0/15 rounded-lg p-3 text-[12.5px] leading-snug whitespace-pre-wrap break-words">
                    {{ $previewBody ?: '(empty body)' }}</div>
                @if ($templateName)
                    <div class="mt-3 text-[10.5px] font-mono text-paper-0/70">Template ·
                        {{ $templateName }}{{ $templateCat ? ' · ' . $templateCat : '' }}</div>
                @endif
                @if (!$isFinished)
                    <a href="{{ url('/scheduled/create') }}"
                        class="mt-4 inline-flex items-center gap-2 rounded-full bg-paper-0 text-wa-deep px-4 py-2 text-[12px] font-semibold">{{ __('Schedule another') }}
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M6 3l5 5-5 5" />
                        </svg>
                    </a>
                @endif
            </div>
        </section>

    </main>

    {{-- Confirmation modal — shared with index page. --}}
    <div id="sched-confirm" class="hidden fixed inset-0 z-50 items-center justify-center p-5"
        style="background:rgba(11,31,28,0.55);">
        <div class="bg-paper-0 rounded-2xl w-full max-w-md shadow-soft border border-paper-200">
            <div class="px-5 py-4 border-b border-paper-200 flex items-start gap-3">
                <span id="sched-confirm-icon"
                    class="w-9 h-9 rounded-full bg-accent-coral/15 text-accent-coral grid place-items-center shrink-0">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path d="M8 4v5M8 12h.01" />
                        <circle cx="8" cy="8" r="6.5" />
                    </svg>
                </span>
                <div class="flex-1 min-w-0">
                    <h3 id="sched-confirm-title" class="font-serif text-[18px] leading-tight">
                        {{ __('Confirm action') }}</h3>
                    <p id="sched-confirm-body" class="mt-1 text-[12.5px] text-ink-700 leading-snug">
                        {{ __('Are you sure?') }}</p>
                </div>
            </div>
            <div class="px-5 py-3 bg-paper-50/40 flex items-center justify-end gap-2 rounded-b-2xl">
                <button type="button" id="sched-confirm-cancel"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Keep it') }}</button>
                <button type="button" id="sched-confirm-ok"
                    class="px-3.5 py-1.5 rounded-full bg-accent-coral hover:opacity-90 text-paper-0 text-[12px] font-semibold">{{ __('Yes, proceed') }}</button>
            </div>
        </div>
    </div>

</x-layouts.user>
