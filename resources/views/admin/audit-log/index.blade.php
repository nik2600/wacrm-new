<x-layouts.admin :title="__('Audit Log')" admin-key="audit-log" page="admin-audit-log-index">

    @php
        $tonePill = function ($result) {
            return match ($result) {
                'failure' => [
                    'cls' => 'bg-accent-coral/10 text-accent-coral border border-accent-coral/30',
                    'dot' => 'bg-accent-coral',
                ],
                'warning' => ['cls' => 'bg-accent-amber/15 text-accent-amber', 'dot' => 'bg-accent-amber'],
                default => ['cls' => 'bg-wa-mint text-wa-deep', 'dot' => 'bg-wa-green'],
            };
        };
        $exportQuery = http_build_query(
            array_filter(
                [
                    'q' => $q,
                    'event' => $event,
                    'result' => $result,
                    'layer' => $layer,
                    'from' => $from,
                    'to' => $to,
                ],
                fn($v) => $v !== '' && $v !== null,
            ),
        );
    @endphp

    <form method="GET" action="{{ route('admin.audit-log.index') }}" data-audit-form>

        <header
            class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
            <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
                <a href="{{ url('/admin') }}"
                    class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
                <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M4 3l3 3-3 3" />
                </svg>
                <span class="text-ink-900 normal-case tracking-normal">{{ __('Audit log') }}</span>
            </div>

            <div class="relative flex-1 min-w-0 max-w-[560px] ml-4">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500"
                    fill="none" stroke="currentColor" stroke-width="1.6">
                    <circle cx="7" cy="7" r="5" />
                    <path d="m11 11 3 3" />
                </svg>
                <input name="q" value="{{ $q }}"
                    class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition"
                    placeholder="{{ __('Search action, IP, or payload...') }}" />
            </div>

            <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
        </header>

        <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin - Security & compliance') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Audit') }}
                        <span class="italic text-wa-deep">{{ __('log') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Review administrator, workspace, billing, campaign, device, and security events across the platform.') }}
                    </p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                    <input type="date" name="from" value="{{ $from }}"
                        class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-mono focus:outline-none focus:border-wa-deep"
                        placeholder="{{ __('From') }}">
                    <input type="date" name="to" value="{{ $to }}"
                        class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-mono focus:outline-none focus:border-wa-deep"
                        placeholder="To">
                    <button type="submit"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M3 4h10M5 8h6M7 12h2" />
                        </svg>
                        Apply
                    </button>
                    <a href="{{ route('admin.audit-log.export') }}{{ $exportQuery ? '?' . $exportQuery : '' }}"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
                        </svg>
                        Export CSV
                    </a>
                </div>
            </div>

            <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="text-[11px] text-ink-600 font-medium">{{ __('Events captured') }}</div>
                    <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['total']) }}</div>
                    <div class="text-[11px] text-ink-500 mt-2">{{ __('all time') }}</div>
                </div>
                <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                    <div class="text-[11px] text-ink-600 font-medium">{{ __('Today') }}</div>
                    <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['today']) }}</div>
                    <div class="text-[11px] text-wa-deep mt-2">{{ __('since midnight') }}</div>
                </div>
                <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                    <div class="text-[11px] text-ink-600 font-medium">{{ __('Failures') }}</div>
                    <div class="font-serif text-[34px] leading-none mt-1 text-accent-coral">
                        {{ number_format($stats['failures']) }}</div>
                    <div class="text-[11px] text-accent-coral mt-2">{{ __('all time') }}</div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="text-[11px] text-ink-600 font-medium">{{ __('Platform-level') }}</div>
                    <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['platform']) }}</div>
                    <div class="text-[11px] text-ink-500 mt-2">{{ __('admin actions') }}</div>
                </div>
            </section>

            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex items-center gap-1 shadow-card flex-wrap"
                data-log-filters>
                @php
                    $layerOptions = [
                        '' => ['label' => 'All', 'count' => $stats['total']],
                        'platform' => ['label' => 'Platform', 'count' => $stats['platform']],
                        'workspace' => ['label' => 'Workspace', 'count' => $stats['total'] - $stats['platform']],
                    ];
                @endphp
                @foreach ($layerOptions as $value => $opt)
                    <a href="{{ route('admin.audit-log.index', array_filter(['layer' => $value, 'q' => $q, 'event' => $event, 'result' => $result, 'from' => $from, 'to' => $to])) }}"
                        class="inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] cursor-pointer transition {{ $layer === $value ? 'bg-ink-900 text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">
                        {{ $opt['label'] }} <span
                            class="font-mono text-[11px] opacity-80">({{ number_format($opt['count']) }})</span>
                    </a>
                @endforeach
                <div class="flex-1"></div>
                <select name="event" onchange="this.form.submit()"
                    class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 hover:bg-paper-50 focus:outline-none focus:border-wa-deep">
                    <option value="">{{ __('All events') }}</option>
                    @foreach ($eventOptions as $opt)
                        <option value="{{ $opt }}" {{ $event === $opt ? 'selected' : '' }}>
                            {{ $opt }}</option>
                    @endforeach
                </select>
                <select name="result" onchange="this.form.submit()"
                    class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 hover:bg-paper-50 focus:outline-none focus:border-wa-deep">
                    <option value="">{{ __('All results') }}</option>
                    <option value="success" {{ $result === 'success' ? 'selected' : '' }}>{{ __('Success') }}</option>
                    <option value="failure" {{ $result === 'failure' ? 'selected' : '' }}>{{ __('Failure') }}</option>
                    <option value="warning" {{ $result === 'warning' ? 'selected' : '' }}>{{ __('Warning') }}</option>
                </select>
            </div>

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
                <div class="space-y-5 min-w-0">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-4">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Event stream') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight mt-1">
                                    {{ __('Recent platform activity') }}</h2>
                            </div>
                            <div class="flex items-center gap-2 text-[11px] font-mono text-ink-500">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-wa-mint text-wa-deep border border-wa-green/40"><span
                                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>live</span>
                                <span>{{ $rows->total() }} {{ __('events') }}</span>
                            </div>
                        </div>

                        {{-- Wrapping in overflow-x-auto means the table can scroll on
 tight viewports instead of cells crashing into each other.
 Widths tuned for the ~880px column budget the
 grid-cols-[minmax(0,1fr)_360px] parent gives us. --}}
                        <div class="overflow-x-auto">
                            <table class="w-full text-[12.5px] table-fixed min-w-[880px]">
                                <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                                    <tr>
                                        <th class="text-left px-3 py-2.5 w-[108px] font-medium">{{ __('Time') }}
                                        </th>
                                        <th class="text-left px-3 py-2.5 w-[140px] font-medium">{{ __('Actor') }}
                                        </th>
                                        <th class="text-left px-2 py-2.5 w-[130px] font-medium">{{ __('Event') }}
                                        </th>
                                        <th class="text-left px-2 py-2.5 font-medium">{{ __('Resource') }}</th>
                                        <th class="text-left px-2 py-2.5 w-[120px] font-medium">{{ __('Workspace') }}
                                        </th>
                                        <th class="text-left px-2 py-2.5 w-[100px] font-medium">IP</th>
                                        <th class="text-center px-3 py-2.5 w-[84px] font-medium">{{ __('Result') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-paper-200" data-audit-table>
                                    @forelse ($rows as $r)
                                        @php
                                            $actor = $r->actor_user_id ? $actors[$r->actor_user_id] ?? null : null;
                                            $ws = $r->workspace_id ? $workspaces[$r->workspace_id] ?? null : null;
                                            $tone = $tonePill($r->result);
                                            $rowBg =
                                                $r->result === 'failure'
                                                    ? 'bg-accent-coral/5'
                                                    : ($r->result === 'warning'
                                                        ? 'bg-accent-amber/5'
                                                        : '');
                                            $subj =
                                                $r->subject_type && $r->subject_id
                                                    ? $r->subject_type . '#' . $r->subject_id
                                                    : '';
                                            $label = $r->payload['_label'] ?? $subj;
                                        @endphp
                                        <tr data-event-row data-event-id="{{ $r->id }}"
                                            class="hover:bg-paper-50/60 cursor-pointer {{ $rowBg }}">
                                            <td class="px-3 py-3 font-mono text-[11px] align-top">
                                                {{ $r->created_at?->format('Y-m-d') }}<br><span
                                                    class="text-ink-500">{{ $r->created_at?->format('H:i:s') }}</span>
                                            </td>
                                            <td class="px-3 py-3 align-top">
                                                <div class="font-semibold truncate">
                                                    {{ $actor?->name ?? ($r->actor_user_id ? 'User #' . $r->actor_user_id : 'System') }}
                                                </div>
                                                <div class="text-[10.5px] text-ink-500 font-mono truncate">
                                                    {{ $actor?->email ?? $r->layer }}</div>
                                            </td>
                                            <td class="px-2 py-3 align-top"><span
                                                    class="inline-block max-w-full truncate px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-mono align-middle">{{ $r->action }}</span>
                                            </td>
                                            <td class="px-2 py-3 align-top">
                                                <div class="font-semibold truncate">{{ $label ?: '—' }}</div>
                                                <div class="text-[10.5px] text-ink-500 font-mono truncate">
                                                    {{ $subj ?: '' }}</div>
                                            </td>
                                            <td class="px-2 py-3 font-semibold truncate align-top">
                                                {{ $ws?->name ?? '—' }}</td>
                                            <td
                                                class="px-2 py-3 font-mono text-[10.5px] text-ink-600 truncate align-top">
                                                {{ $r->ip ?? 'internal' }}</td>
                                            <td class="px-3 py-3 text-center align-top"><span
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $tone['cls'] }} text-[10.5px] font-mono"><span
                                                        class="w-1.5 h-1.5 rounded-full {{ $tone['dot'] }}"></span>{{ $r->result }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7"
                                                class="px-4 py-10 text-center text-ink-500 text-[13px]">
                                                {{ __('No audit events match the current filters.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 rounded-b-2xl">
                            {{ $rows->links() }}
                        </div>
                    </div>

                    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Top actors') }}</div>
                            <h2 class="font-serif text-[20px] leading-tight mt-1 mb-4">{{ __('Last 7 days') }}</h2>
                            <div class="space-y-3 text-[12px]">
                                @php $maxActor = max(array_column($topActors, 'count') ?: [1]); @endphp
                                @forelse ($topActors as $a)
                                    @php $pct = max(8, round(($a['count'] / $maxActor) * 100)); @endphp
                                    <div>
                                        <div class="flex justify-between mb-1"><span
                                                class="truncate">{{ $a['name'] }}</span><span
                                                class="font-mono">{{ number_format($a['count']) }}</span></div>
                                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-wa-deep" style="width: {{ $pct }}%"></div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-ink-500 text-[11.5px]">
                                        {{ __('No actor activity in the last 7 days.') }}</div>
                                @endforelse
                            </div>
                        </div>

                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Event mix') }}</div>
                            <h2 class="font-serif text-[20px] leading-tight mt-1 mb-4">{{ __('By module (7d)') }}</h2>
                            <div class="space-y-3 text-[12px]">
                                @forelse ($eventMix as $m)
                                    <div>
                                        <div class="flex justify-between mb-1"><span
                                                class="capitalize">{{ $m['module'] }}</span><span
                                                class="font-mono">{{ $m['pct'] }}%</span></div>
                                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-wa-teal"
                                                style="width: {{ min(100, $m['pct'] * 2) }}%"></div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-ink-500 text-[11.5px]">{{ __('No events in window.') }}</div>
                                @endforelse
                            </div>
                        </div>

                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Retention') }}</div>
                            <h2 class="font-serif text-[20px] leading-tight mt-1 mb-4">{{ __('Policy') }}</h2>
                            <div class="text-[12px] text-ink-600 leading-relaxed">
                                Audit events are kept <span
                                    class="font-semibold text-ink-900">{{ __('indefinitely') }}</span>. Use the export
                                button at the top of this page to download a filtered CSV snapshot for compliance
                                review.
                            </div>
                            <a href="{{ route('admin.audit-log.export') }}{{ $exportQuery ? '?' . $exportQuery : '' }}"
                                class="mt-4 inline-block w-full text-center rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 px-4 py-2 text-[12px] font-semibold">{{ __('Download CSV') }}</a>
                        </div>
                    </section>
                </div>

                <aside class="space-y-5 lg:sticky lg:top-20">
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card" data-event-detail>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Selected event') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1" data-d-id>{{ __('Click a row →') }}</h2>
                        <div class="mt-3 flex flex-wrap gap-1.5" data-d-pills></div>
                        <dl class="mt-4 space-y-2.5 text-[12px]" data-d-fields></dl>
                        <div class="mt-4 pt-4 border-t border-paper-200 hidden" data-d-meta-wrap>
                            <div class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500 mb-2">
                                {{ __('Payload') }}</div>
                            <pre class="rounded-xl bg-paper-50 border border-paper-200 p-3 text-[11px] leading-relaxed text-ink-700 overflow-x-auto max-h-[280px]"
                                data-d-meta></pre>
                        </div>
                    </section>

                    <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Review queue') }}</div>
                                <h2 class="font-serif text-[20px] leading-tight mt-1">{{ __('Needs attention') }}</h2>
                            </div>
                            <span
                                class="px-2 py-0.5 rounded-full bg-accent-coral/10 text-accent-coral text-[10px] font-mono border border-accent-coral/30">{{ $reviewQueue->count() }}
                                {{ __('open') }}</span>
                        </div>
                        <div class="mt-4 space-y-3 text-[12px]">
                            @forelse ($reviewQueue as $rq)
                                @php
                                    $rqWs = $rq->workspace_id ? $workspaces[$rq->workspace_id] ?? null : null;
                                    $isFail = $rq->result === 'failure';
                                @endphp
                                <button type="button" data-event-id="{{ $rq->id }}"
                                    class="w-full text-left rounded-xl border p-3 {{ $isFail ? 'border-accent-coral/30 bg-accent-coral/5 hover:bg-accent-coral/10' : 'border-accent-amber/40 bg-accent-amber/5 hover:bg-accent-amber/10' }}">
                                    <div
                                        class="font-semibold {{ $isFail ? 'text-accent-coral' : 'text-accent-amber' }}">
                                        {{ $rq->action }}</div>
                                    <div class="text-[10.5px] text-ink-500 font-mono mt-1">{{ $rq->ip ?? 'internal' }}
                                        ·
                                        {{ $rq->created_at?->diffForHumans() }}{{ $rqWs ? ' · ' . $rqWs->name : '' }}
                                    </div>
                                </button>
                            @empty
                                <div class="text-ink-500 text-[11.5px]">
                                    {{ __('All clear — no failures or warnings.') }}</div>
                            @endforelse
                        </div>
                    </section>
                </aside>
            </section>

        </main>

    </form>

</x-layouts.admin>
