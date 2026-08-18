<x-layouts.user :title="__('Message history')" nav-key="more" page="user-message-history-index">
    @php
        $f = $filters ?? [];
        $rangeKey = $f['range']['key'] ?? '7d';
        $dirCur = $f['dir'] ?? 'all';
        $typeCur = $f['type'] ?? 'all';
        $deviceCur = $f['deviceId'] ?? null;
        $qCur = $f['q'] ?? '';
        $bucketCur = $f['bucket'] ?? 'daily';

        // Avg-response card state — green if we measured a value, neutral if not.
        $hasReplyTime = ($stats['avgReplyHuman'] ?? '—') !== '—';
    @endphp

    {{-- Sub-header strip --}}
    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/more') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to More') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg></a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('More / Message History') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Message') }} <span
                            class="italic text-wa-deep">{{ __('history') }}</span></div>
                </div>
            </div>
            <form method="GET" id="mh-toolbar" class="flex items-center gap-2 flex-wrap">
                <select name="range" onchange="this.form.submit()"
                    class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                    <option value="24h" @selected($rangeKey === '24h')>{{ __('Last 24 hours') }}</option>
                    <option value="7d" @selected($rangeKey === '7d')>{{ __('Last 7 days') }}</option>
                    <option value="30d" @selected($rangeKey === '30d')>{{ __('Last 30 days') }}</option>
                    <option value="90d" @selected($rangeKey === '90d')>{{ __('Last 90 days') }}</option>
                </select>
                {{-- preserve other filter params on submit --}}
                <input type="hidden" name="dir" value="{{ $dirCur }}" />
                <input type="hidden" name="type" value="{{ $typeCur }}" />
                @if ($deviceCur)
                    <input type="hidden" name="device_id" value="{{ $deviceCur }}" />
                @endif
                <input type="hidden" name="q" value="{{ $qCur }}" />
                <input type="hidden" name="bucket" value="{{ $bucketCur }}" />
                <a href="{{ route('user.message-history.export', ['range' => $rangeKey]) }}"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
                    </svg>
                    Export CSV
                </a>
                <button type="button" id="mh-archive-btn"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M8 2v8M3 8l5 5 5-5" />
                    </svg>
                    Archive
                </button>
            </form>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6 space-y-6">

        {{-- KPI strip --}}
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Total messages') }}</span>
                    <span
                        class="text-[10px] {{ ($stats['deltaPct'] ?? 0) >= 0 ? 'text-wa-deep' : 'text-accent-coral' }} font-mono">{{ ($stats['deltaPct'] ?? 0) >= 0 ? '+' : '' }}{{ $stats['deltaPct'] ?? 0 }}%
                        vs last period</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none">{{ number_format($stats['total'] ?? 0) }}</span>
                    <span class="text-[11px] text-ink-500">{{ $rangeKey }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sent') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono">{{ $stats['sentPct'] ?? 0 }}%</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none">{{ number_format($stats['sent'] ?? 0) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('outgoing') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Received') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono">{{ $stats['receivedPct'] ?? 0 }}%</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span
                        class="font-serif text-[28px] leading-none">{{ number_format($stats['received'] ?? 0) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('incoming') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Failed') }}</span>
                    <span
                        class="text-[10px] {{ ($stats['failed'] ?? 0) > 0 ? 'text-accent-coral' : 'text-wa-deep' }} font-mono">{{ $stats['failPct'] ?? 0 }}%</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none">{{ number_format($stats['failed'] ?? 0) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('retry queue') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Avg response') }}</span>
                    <span
                        class="text-[10px] {{ $hasReplyTime ? 'text-wa-deep' : 'text-ink-500' }} font-mono">{{ $hasReplyTime ? 'healthy' : 'n/a' }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none">{{ $stats['avgReplyHuman'] ?? '—' }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('to first reply') }}</span>
                </div>
            </div>
        </section>

        {{-- Volume chart + direction donut --}}
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Volume') }}
                        </div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Messages over time') }}</h3>
                    </div>
                    <div class="flex items-center gap-1 text-[11px] font-mono text-ink-500" id="mh-bucket-tabs">
                        @foreach (['daily', 'hourly', 'weekly'] as $b)
                            <button data-bucket="{{ $b }}"
                                class="mh-bucket px-2.5 py-1 rounded-full {{ $bucketCur === $b ? 'bg-wa-deep text-paper-0' : 'hover:bg-paper-100' }}">{{ ucfirst($b) }}</button>
                        @endforeach
                    </div>
                </div>
                <div id="chart-volume" class="h-[260px]" data-labels='@json($volume['labels'] ?? [])'
                    data-sent='@json($volume['sent'] ?? [])' data-received='@json($volume['received'] ?? [])'
                    data-failed='@json($volume['failed'] ?? [])'></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Direction split') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Sent vs received') }}</h3>
                <div id="chart-direction" class="h-[200px]" data-sent="{{ $direction['sent'] ?? 0 }}"
                    data-received="{{ $direction['received'] ?? 0 }}" data-failed="{{ $direction['failed'] ?? 0 }}"
                    data-total="{{ $direction['total'] ?? 0 }}"></div>
                <div class="mt-3 space-y-1.5 text-[12px]">
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-deep"></span>Sent</span><span
                            class="font-mono text-ink-700">{{ number_format($direction['sent'] ?? 0) }}</span></div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-teal"></span>Received</span><span
                            class="font-mono text-ink-700">{{ number_format($direction['received'] ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-accent-coral"></span>Failed</span><span
                            class="font-mono text-ink-700">{{ number_format($direction['failed'] ?? 0) }}</span></div>
                </div>
            </div>
        </section>

        {{-- Filter bar + table + side detail --}}
        <section class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-4 items-start">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card">
                <form method="GET" id="mh-filter-form"
                    class="px-4 py-3 border-b border-paper-200 flex items-center gap-2 flex-wrap">
                    <input type="hidden" name="range" value="{{ $rangeKey }}" />
                    <input type="hidden" name="bucket" value="{{ $bucketCur }}" />
                    <div class="relative flex-1 min-w-[260px] max-w-[480px]">
                        <svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <circle cx="7" cy="7" r="5" />
                            <path d="m11 11 3 3" />
                        </svg>
                        <input type="search" name="q" value="{{ $qCur }}"
                            placeholder="{{ __('Search by content, contact, phone, or message id...') }}"
                            class="w-full pl-9 pr-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </div>
                    <div class="flex items-center gap-1 bg-paper-50 rounded-full p-1" id="dir-tabs">
                        @foreach (['all' => 'All', 'out' => 'Sent', 'in' => 'Received', 'fail' => 'Failed'] as $k => $label)
                            <button type="button" data-dir="{{ $k }}"
                                class="dir-tab px-3 py-1 rounded-full text-[11.5px] font-semibold {{ $dirCur === $k ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100' }}">
                                {{ $label }} <span
                                    class="ml-1 font-mono text-[10px] opacity-80">{{ number_format($dirCounts[$k] ?? 0) }}</span>
                            </button>
                        @endforeach
                        <input type="hidden" name="dir" value="{{ $dirCur }}" />
                    </div>
                    <select name="device_id" onchange="this.form.submit()"
                        class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        <option value="">{{ __('All devices') }}</option>
                        @foreach ($devices as $d)
                            <option value="{{ $d['id'] }}" @selected($deviceCur && (int) $deviceCur === (int) $d['id'])>
                                {{ $d['label'] ?: $d['phone'] }}</option>
                        @endforeach
                    </select>
                    <select name="type" onchange="this.form.submit()"
                        class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        <option value="all" @selected($typeCur === 'all')>{{ __('All types') }}</option>
                        <option value="text" @selected($typeCur === 'text')>{{ __('Plain text') }}</option>
                        <option value="template" @selected($typeCur === 'template')>{{ __('Template') }}</option>
                        <option value="auto_reply" @selected($typeCur === 'auto_reply')>{{ __('Auto-reply') }}</option>
                        <option value="campaign" @selected($typeCur === 'campaign')>{{ __('Campaign') }}</option>
                        <option value="broadcast" @selected($typeCur === 'broadcast')>{{ __('Broadcast') }}</option>
                        <option value="scheduled" @selected($typeCur === 'scheduled')>{{ __('Scheduled') }}</option>
                        <option value="image" @selected($typeCur === 'image')>{{ __('Image') }}</option>
                        <option value="video" @selected($typeCur === 'video')>{{ __('Video') }}</option>
                        <option value="document" @selected($typeCur === 'document')>{{ __('Document') }}</option>
                        <option value="location" @selected($typeCur === 'location')>{{ __('Location') }}</option>
                    </select>
                    <button type="submit"
                        class="px-3 py-2 rounded-lg bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Apply') }}</button>
                </form>

                <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                            <tr>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-10">
                                    <input type="checkbox" id="msg-pick-all"
                                        class="rounded border-paper-200 text-wa-deep focus:ring-wa-deep" /></th>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5 w-[110px]">
                                    {{ __('When') }}</th>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5 w-12">
                                    {{ __('Dir') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Contact') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Message') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Type') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Status') }}</th>
                                <th
                                    class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-[80px]">
                                    {{ __('Open') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200" id="msg-rows">
                            @include('user.message-history._rows', ['rows' => $rows])
                        </tbody>
                    </table>
                </div>

                <div
                    class="px-4 py-3 border-t border-paper-200 flex items-center justify-between text-[12px] text-ink-500">
                    <div>{{ __('Showing') }} <span
                            class="font-mono text-ink-900">{{ $shownFrom }}–{{ $shownTo }}</span> of <span
                            class="font-mono text-ink-900">{{ number_format($total) }}</span></div>
                    <div class="flex items-center gap-1">
                        @php $base = request()->only(['range','dir','type','device_id','q','bucket']); @endphp
                        @if ($page > 1)
                            <a href="?{{ http_build_query(array_merge($base, ['page' => $page - 1])) }}"
                                class="px-2.5 py-1 rounded-md border border-paper-200 hover:bg-paper-50 text-[11px]">Prev</a>
                        @else
                            <button disabled
                                class="px-2.5 py-1 rounded-md border border-paper-200 text-ink-400 text-[11px] cursor-not-allowed">{{ __('Prev') }}</button>
                        @endif
                        @php
                            $pagesToShow = [];
                            for ($i = max(1, $page - 2); $i <= min($pageCount, $page + 2); $i++) {
                                $pagesToShow[] = $i;
                            }
                            if (!in_array(1, $pagesToShow, true)) {
                                array_unshift($pagesToShow, 1);
                            }
                            if (!in_array($pageCount, $pagesToShow, true)) {
                                $pagesToShow[] = $pageCount;
                            }
                        @endphp
                        @foreach ($pagesToShow as $p)
                            @if ($p === $page)
                                <button
                                    class="px-2.5 py-1 rounded-md bg-wa-deep text-paper-0 text-[11px] font-semibold">{{ $p }}</button>
                            @else
                                <a href="?{{ http_build_query(array_merge($base, ['page' => $p])) }}"
                                    class="px-2.5 py-1 rounded-md hover:bg-paper-50 text-[11px]">{{ $p }}</a>
                            @endif
                        @endforeach
                        @if ($page < $pageCount)
                            <a href="?{{ http_build_query(array_merge($base, ['page' => $page + 1])) }}"
                                class="px-2.5 py-1 rounded-md border border-paper-200 hover:bg-paper-50 text-[11px]">Next</a>
                        @else
                            <button disabled
                                class="px-2.5 py-1 rounded-md border border-paper-200 text-ink-400 text-[11px] cursor-not-allowed">{{ __('Next') }}</button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Detail panel --}}
            <aside
                class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden xl:sticky xl:top-[20px]">
                <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Details') }}</span>
                    <a href="{{ url('/chat') }}" id="detail-open-thread"
                        class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('Open thread →') }}</a>
                </div>
                @if ($detail)
                    <div class="p-4 space-y-3">
                        <div class="flex items-center gap-3">
                            <span id="d-avatar"
                                class="w-10 h-10 rounded-full bg-gradient-to-br {{ $detail['avatar'] }} text-paper-0 grid place-items-center text-[12px] font-semibold">{{ $detail['initials'] }}</span>
                            <div class="min-w-0">
                                <div id="d-name" class="font-semibold truncate">{{ $detail['name'] }}</div>
                                <div id="d-phone" class="text-[10.5px] font-mono text-ink-500 truncate">
                                    {{ $detail['phone'] ?: '—' }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-[11.5px]">
                            <div class="bg-paper-50 rounded-md p-2">
                                <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                                    {{ __('Direction') }}</div>
                                <div id="d-direction" class="font-mono text-ink-900 mt-0.5">
                                    {{ $detail['directionLabel'] }}</div>
                            </div>
                            <div class="bg-paper-50 rounded-md p-2">
                                <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                                    {{ __('Status') }}</div>
                                <div id="d-status" class="font-mono text-ink-900 mt-0.5">
                                    {{ $detail['statusLabel'] }}</div>
                            </div>
                            <div class="bg-paper-50 rounded-md p-2">
                                <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                                    {{ __('Type') }}</div>
                                <div id="d-type" class="font-mono text-ink-900 mt-0.5">{{ $detail['typeLabel'] }}
                                </div>
                            </div>
                            <div class="bg-paper-50 rounded-md p-2">
                                <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                                    {{ __('When') }}</div>
                                <div id="d-time" class="font-mono text-ink-900 mt-0.5">{{ $detail['date'] }}
                                    {{ $detail['time'] }}</div>
                            </div>
                        </div>

                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1.5">
                                {{ __('Content') }}</div>
                            <div id="d-body"
                                class="text-[12.5px] leading-snug bg-paper-0 border border-paper-200 rounded-md p-2.5 max-h-[180px] overflow-y-auto whitespace-pre-wrap break-words">
                                {{ $detail['body'] }}</div>
                        </div>

                        @if (!empty($detail['failureReason']))
                            <div>
                                <div
                                    class="font-mono text-[10px] uppercase tracking-[0.16em] text-accent-coral mb-1.5">
                                    {{ __('Failure') }}</div>
                                <div
                                    class="text-[11.5px] bg-accent-coral/10 border border-accent-coral/30 rounded-md p-2 text-[#A1431F]">
                                    {{ $detail['failureReason'] }}</div>
                            </div>
                        @endif

                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1.5">
                                {{ __('Metadata') }}</div>
                            <dl class="space-y-1 text-[11.5px]">
                                @foreach ($detail['metadata'] ?? [] as $m)
                                    <div class="flex items-center justify-between gap-2">
                                        <dt class="text-ink-500 shrink-0">{{ $m['label'] }}</dt>
                                        <dd class="font-mono text-ink-900 truncate ml-2">{{ $m['value'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    </div>
                    <div class="px-4 py-3 border-t border-paper-200 flex items-center gap-2 bg-paper-50/40">
                        <button type="button"
                            class="flex-1 px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium"
                            data-copy-id="{{ $detail['id'] }}">{{ __('Copy ID') }}</button>
                        <a href="{{ url('/chat') }}"
                            class="flex-1 px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium text-center">{{ __('Open chat') }}</a>
                    </div>
                @else
                    <div class="p-6 text-center text-[12px] text-ink-500 italic">
                        {{ __('Click a row to see its details.') }}</div>
                @endif
            </aside>
        </section>

        {{-- Bottom: top conversations + types breakdown --}}
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Top conversations') }}</div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5">Most active threads
                            ({{ $rangeKey }})</h3>
                    </div>
                    <a href="{{ url('/chat') }}"
                        class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('Open inbox') }}</a>
                </div>
                @if (empty($topConvos))
                    <div
                        class="border border-dashed border-paper-200 rounded-lg p-6 text-center text-[12px] text-ink-500 italic">
                        {{ __('No active threads in this range yet.') }}</div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($topConvos as $c)
                            <a href="{{ url('/chat') }}"
                                class="border border-paper-200 rounded-lg p-3 hover:border-wa-deep transition flex items-center gap-3">
                                <span
                                    class="w-9 h-9 rounded-full bg-gradient-to-br {{ $c['gradient'] }} text-paper-0 grid place-items-center text-[11px] font-semibold shrink-0">{{ $c['initials'] }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[12.5px] font-semibold truncate">{{ $c['title'] }}</div>
                                    <div class="text-[10.5px] text-ink-500 font-mono">{{ $c['count'] }} msgs · last
                                        {{ $c['lastAt'] }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('By message type') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Mix this period') }}</h3>
                <div id="chart-types" class="h-[220px]" data-labels='@json($typeMix['labels'] ?? [])'
                    data-data='@json($typeMix['data'] ?? [])'></div>
            </div>
        </section>

    </main>

    {{-- Charts + interactions handled by resources/js/charts/user-message-history-index.js
 (auto-loaded by app.js via page="user-message-history-index" on the
 layout). Do NOT add another <script> here — duplicating ApexCharts
 causes the volume bar to overflow and the donut to render twice. --}}

</x-layouts.user>
