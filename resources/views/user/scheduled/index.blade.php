@php
    use Illuminate\Support\Carbon;

    /** @var \Illuminate\Support\Collection $rows */
    /** @var \Illuminate\Support\Collection $upcoming */
    /** @var array $totals */
    $rows = $rows ?? collect();
    $upcoming = $upcoming ?? collect();
    $totals = $totals ?? [
        'total' => 0,
        'active' => 0,
        'paused' => 0,
        'completed' => 0,
        'failed' => 0,
        'cancelled' => 0,
        'total_sent' => 0,
        'total_delivered' => 0,
        'avg_delivery' => 0,
    ];

    /** Map a recipient_type + counts blob to a one-line label like "847 / Group". */
    $describeRecipients = function ($r) {
        $count = (int) ($r->total_recipients ?? 0);
        $tag = match ($r->recipient_type) {
            'group' => 'Group',
            'queue' => 'Queue',
            'number' => 'Numbers',
            default => '—',
        };
        return number_format($count) . ' / ' . $tag;
    };

    /** Pretty status pill class + dot color. */
    $statusPill = function (string $status) {
        return match ($status) {
            'scheduled' => ['cls' => 'bg-wa-mint text-wa-deep', 'dot' => 'bg-wa-green', 'label' => 'Queued'],
            'running' => ['cls' => 'bg-[#D9E5F2] text-[#13478A]', 'dot' => 'bg-[#13478A]', 'label' => 'Running'],
            'paused' => ['cls' => 'bg-accent-amber/20 text-[#7B5A14]', 'dot' => 'bg-accent-amber', 'label' => 'Paused'],
            'completed' => ['cls' => 'bg-paper-100 text-ink-700', 'dot' => 'bg-ink-500', 'label' => 'Done'],
            'failed' => [
                'cls' => 'bg-accent-coral/15 text-accent-coral',
                'dot' => 'bg-accent-coral',
                'label' => 'Failed',
            ],
            'cancelled' => ['cls' => 'bg-paper-100 text-ink-500', 'dot' => 'bg-ink-500/40', 'label' => 'Cancelled'],
            default => ['cls' => 'bg-paper-100 text-ink-700', 'dot' => 'bg-ink-500/40', 'label' => $status],
        };
    };

    /** Pretty template-type pill — colors match the original demo. */
    $typePill = function (string $type) {
        return match ($type) {
            'template' => ['cls' => 'bg-wa-deep/10 text-wa-deep', 'icon' => 'M2.5 3.5h11v9h-11zM5 6h6M5 9h6'],
            'media' => ['cls' => 'bg-accent-amber/20 text-[#7B5A14]', 'icon' => 'M2 3h12v10H2zM5 7l3 3 5-5'],
            'location' => [
                'cls' => 'bg-[#F3E9FF] text-[#5B3D8A]',
                'icon' => 'M8 1.5C5.5 1.5 3.5 3.4 3.5 5.7c0 3 4.5 8.8 4.5 8.8s4.5-5.8 4.5-8.8C12.5 3.4 10.5 1.5 8 1.5z',
            ],
            default => ['cls' => 'bg-paper-100 text-ink-700', 'icon' => 'M3 4h10M3 8h10M3 12h7'],
        };
    };
@endphp

<x-layouts.user :title="__('Scheduled Messages')" nav-key="more" page="user-scheduled-index">

    <!-- Sub header / breadcrumb -->
    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/more') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to More') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('More / Scheduled Messages') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Scheduled') }} <span
                            class="italic text-wa-deep">{{ __('messages') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono">{{ $totals['active'] }}
                    {{ __('queued') }}</span>
                <a href="{{ url('/scheduled/create') }}"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    Schedule message
                </a>
            </div>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6 space-y-6" data-scheduled-state>

        <!-- Stats strip -->
        <section class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Total scheduled') }}
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none">{{ number_format($totals['total']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('across all states') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Active queue') }}
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none">{{ number_format($totals['active']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('waiting to fire') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Total sent') }}
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none">{{ number_format($totals['total_sent']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('messages out') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Delivery rate') }}
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none">{{ $totals['avg_delivery'] }}%</span>
                    <span class="text-[11px] text-ink-500">{{ __('across completed') }}</span>
                </div>
            </div>
        </section>

        <!-- Up next + Tip -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Up next') }}
                        </div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Firing in the next 24 hours') }}
                        </h3>
                    </div>
                    <span class="text-[11px] font-mono text-ink-500">{{ $upcoming->count() }}
                        {{ __('pending') }}</span>
                </div>
                @if ($upcoming->isEmpty())
                    <div class="text-[12.5px] text-ink-500 py-6 text-center">
                        Nothing scheduled yet. <a href="{{ url('/scheduled/create') }}"
                            class="text-wa-deep font-semibold hover:underline">{{ __('Create one →') }}</a>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($upcoming as $row)
                            @php
                                $tz = $row->timezone ?: 'UTC';
                                $when = Carbon::parse($row->next_run_at);
                                $whenLocal = $when->copy()->setTimezone($tz);
                                // Carbon 3 returns floats from diffInMinutes — cast to int
                                // or the label renders as "in 3.8237903166667m".
                                $diff = (int) round(now()->diffInMinutes($when, false));
                                $when_lbl =
                                    $diff < 0
                                        ? 'overdue'
                                        : ($diff < 1
                                            ? 'now'
                                            : ($diff < 60
                                                ? "in {$diff}m"
                                                : ($diff < 60 * 24
                                                    ? 'in ' . (int) round($diff / 60) . 'h'
                                                    : ($diff < 60 * 48
                                                        ? 'tomorrow'
                                                        : $whenLocal->format('M j')))));
                            @endphp
                            <a href="{{ url('/scheduled/' . $row->id) }}"
                                class="flex items-center gap-3 hover:bg-paper-50/80 -mx-2 px-2 py-1.5 rounded-md">
                                <div class="w-14 shrink-0 text-center">
                                    <div class="font-mono text-[10px] text-ink-500">{{ $when_lbl }}</div>
                                    <div class="font-serif text-[18px] leading-none mt-0.5">
                                        {{ $whenLocal->format('H:i') }}</div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[12.5px] font-semibold truncate">{{ $row->schedule_name }}</div>
                                    <div class="text-[10.5px] text-ink-500 font-mono">{{ $describeRecipients($row) }} ·
                                        {{ $row->template_type }}{{ $row->is_recurring ? ' · ' . $row->repeat_interval : '' }}
                                    </div>
                                </div>
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono">{{ $row->is_recurring ? 'recurring' : 'queued' }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="bg-wa-deep rounded-[14px] p-5 shadow-soft text-paper-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/60">{{ __('Tip') }}
                </div>
                <div class="font-serif text-[22px] leading-tight mt-1">{{ __('Send when they read') }}</div>
                <p class="mt-2 text-[12px] text-paper-0/80 leading-relaxed">
                    {{ __("Open rates peak between 9–11 AM and 6–8 PM in your contacts' local timezones. Pick a send time that matches the recipient cohort — recurring schedules let you set this once and forget.") }}
                </p>
                <a href="{{ url('/scheduled/create') }}"
                    class="mt-4 inline-flex items-center gap-2 rounded-full bg-paper-0 text-wa-deep px-4 py-2 text-[12px] font-semibold">
                    Schedule one
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M6 3l5 5-5 5" />
                    </svg>
                </a>
            </div>
        </section>

        <!-- Status tabs + table -->
        <section class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card" data-list-grid
            data-list-grid-key="scheduled">
            <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-1 bg-paper-50 rounded-full p-1 max-w-full overflow-x-auto" id="sched-tabs">
                    @php
                        $tabs = [
                            'all' => $totals['total'],
                            'scheduled' => $rows->where('status', 'scheduled')->count(),
                            'running' => $rows->where('status', 'running')->count(),
                            'paused' => $totals['paused'],
                            'completed' => $totals['completed'],
                            'failed' => $totals['failed'],
                            'cancelled' => $totals['cancelled'],
                        ];
                    @endphp
                    @foreach ($tabs as $key => $count)
                        <button data-sched-tab="{{ $key }}"
                            class="status-tab px-3.5 py-1.5 rounded-full text-[12px] font-semibold whitespace-nowrap shrink-0 {{ $key === 'all' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100' }}">
                            {{ ucfirst($key) }}
                            <span class="ml-1 font-mono text-[10px] opacity-80"
                                data-sched-tab-count="{{ $key }}">{{ $count }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="flex items-center gap-2 flex-1 min-w-[260px] justify-end">
                    <div class="relative flex-1 max-w-[320px]">
                        <svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <circle cx="7" cy="7" r="5" />
                            <path d="m11 11 3 3" />
                        </svg>
                        <input id="sched-search" type="search" placeholder="{{ __('Search by name…') }}"
                            class="w-full pl-9 pr-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </div>
                    <x-list-grid-toggle />
                </div>
            </div>

            <div class="overflow-x-auto" data-list-grid-list>
                <table class="w-full text-[12.5px]" data-list-grid-source>
                    <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                        <tr>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                {{ __('Schedule') }}</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                {{ __('Type') }}</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                {{ __('Recipients') }}</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                {{ __('Send time') }}</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                {{ __('Repeat') }}</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                {{ __('Status') }}</th>
                            <th
                                class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-[180px]">
                                {{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    {{-- Rows live in their own partial so the JSON `?partial=1`
 path on the controller can re-render them after silent
 AJAX actions (pause / resume / cancel / run-now / destroy)
 without forcing a full page reload. Same shape /broadcasts
 uses — see resources/js/charts/user-broadcasts-index.js. --}}
                    <tbody id="sched-tbody" class="divide-y divide-paper-200">
                        @include('user.scheduled._rows', [
                            'rows' => $rows,
                            'statusPill' => $statusPill,
                            'typePill' => $typePill,
                            'describeRecipients' => $describeRecipients,
                        ])
                    </tbody>
                </table>
            </div>
            <div class="hidden p-4" data-list-grid-grid></div>
        </section>

    </main>

    <div id="toast"></div>

    {{-- Retry modal — opens when operator clicks retry on a failed row.
 Operator picks a fresh send date + time + timezone; JS POSTs to
 /scheduled/{id}/retry. The endpoint resets the row + pivot and
 re-registers the Node cron with the new ISO time. --}}
    <div id="sched-retry" class="hidden fixed inset-0 z-50 items-center justify-center p-5"
        style="background:rgba(11,31,28,0.55);">
        <div class="bg-paper-0 rounded-2xl w-full max-w-md shadow-soft border border-paper-200">
            <div class="px-5 py-4 border-b border-paper-200 flex items-start gap-3">
                <span class="w-9 h-9 rounded-full bg-wa-deep/10 text-wa-deep grid place-items-center shrink-0">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                    </svg>
                </span>
                <div class="flex-1 min-w-0">
                    <h3 class="font-serif text-[18px] leading-tight">{{ __('Retry with new time') }}</h3>
                    <p class="mt-1 text-[12.5px] text-ink-700 leading-snug">
                        {{ __('Pick a fresh send time. The row will reset to') }} <span
                            class="font-mono">{{ __('scheduled') }}</span> and the Node cron re-registers
                        automatically.</p>
                </div>
            </div>
            <div class="px-5 py-4 space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Send date') }}</label>
                        @php $userTz = optional(auth()->user()?->currentWorkspace)->timezone ?? 'Asia/Kolkata'; @endphp
                        <input id="retry-date" type="date" min="{{ now($userTz)->toDateString() }}"
                            value="{{ now($userTz)->toDateString() }}"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Time') }}</label>
                        <input id="retry-time" type="time"
                            value="{{ now($userTz)->addMinutes(10)->format('H:i') }}"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    </div>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Timezone') }}</label>
                    <select id="retry-tz"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        @foreach (\DateTimeZone::listIdentifiers() as $tz)
                            <option value="{{ $tz }}" {{ $tz === $userTz ? 'selected' : '' }}>
                                {{ $tz }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="retry-error"
                    class="hidden text-[11.5px] text-accent-coral bg-accent-coral/5 border border-accent-coral/30 rounded-lg px-3 py-2">
                </div>
            </div>
            <div class="px-5 py-3 bg-paper-50/40 flex items-center justify-end gap-2 rounded-b-2xl">
                <button type="button" id="retry-cancel"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Keep failed') }}</button>
                <button type="button" id="retry-confirm"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Retry now') }}</button>
            </div>
        </div>
    </div>

    {{-- Confirmation modal shared with the detail page. JS toggles
 `hidden`/`flex` and rewrites the title/body for each action. --}}
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
