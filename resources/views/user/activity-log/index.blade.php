@php
    $f = $filters ?? [];
    $rangeKey = $f['range']['key'] ?? '7d';
    $scope = $f['scope'] ?? 'me';
    $cat = $f['cat'] ?? 'all';
    $bucketCur = $f['bucket'] ?? 'daily';
    $qCur = $f['q'] ?? '';
    $deltaPct = $stats['deltaPct'] ?? 0;
    $deltaCls = $deltaPct >= 0 ? 'text-wa-deep' : 'text-accent-coral';
    $deltaSign = $deltaPct > 0 ? '+' : '';
    $rangeLabels = ['24h' => 'Last 24 hours', '7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days'];
@endphp
<x-layouts.user :title="__('Activity Log')" nav-key="more" page="user-activity-log-index">
    <!-- Sub header -->
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
                        {{ __('More / Activity Log') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Activity') }} <span
                            class="italic text-wa-deep">{{ __('log') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <select id="al-range"
                    class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                    @foreach ($rangeLabels as $k => $label)
                        <option value="{{ $k }}" @selected($rangeKey === $k)>{{ $label }}</option>
                    @endforeach
                </select>
                <a id="al-export" href="{{ url('/activity-log/export?range=' . $rangeKey . '&scope=' . $scope) }}"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
                    </svg>
                    {{ __('Export CSV') }}
                </a>
                <button id="al-refresh"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M3 8a5 5 0 0 1 9-3M13 8a5 5 0 0 1-9 3M12 2v3h-3M4 14v-3h3" />
                    </svg>
                    {{ __('Refresh') }}
                </button>
            </div>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6 space-y-6">

        <!-- KPI strip -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Total events') }}</span>
                    <span class="text-[10px] {{ $deltaCls }} font-mono">{{ $deltaSign }}{{ $deltaPct }}%
                        {{ __('vs prev') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none"
                        data-al="total">{{ number_format($stats['total']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ $rangeKey }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sign-ins') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono"
                        data-al="loginsPct">{{ $stats['total'] ? round(($stats['logins'] / $stats['total']) * 100) : 0 }}%</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none"
                        data-al="logins">{{ number_format($stats['logins']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('auth') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Writes') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono"
                        data-al="writesPct">{{ $stats['total'] ? round(($stats['writes'] / $stats['total']) * 100) : 0 }}%</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none"
                        data-al="writes">{{ number_format($stats['writes']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('create / update') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Reads / other') }}</span>
                    <span class="text-[10px] text-ink-500 font-mono"
                        data-al="readsPct">{{ $stats['total'] ? round(($stats['reads'] / $stats['total']) * 100) : 0 }}%</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none"
                        data-al="reads">{{ number_format($stats['reads']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('passive') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Unique IPs') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono">{{ __('distinct') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none"
                        data-al="ips">{{ number_format($stats['uniqueIps']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('addresses') }}</span>
                </div>
            </div>
        </section>

        <!-- Volume chart + category donut -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Volume') }}
                        </div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Events over time') }}</h3>
                    </div>
                    <div class="flex items-center gap-1 text-[11px] font-mono text-ink-500" id="al-bucket-tabs">
                        @foreach (['daily' => 'Daily', 'hourly' => 'Hourly', 'weekly' => 'Weekly'] as $key => $label)
                            <button data-bucket="{{ $key }}"
                                class="px-2.5 py-1 rounded-full {{ $bucketCur === $key ? 'bg-wa-deep text-paper-0' : 'hover:bg-paper-100' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div id="chart-volume" class="h-[260px]" data-labels='@json($volume['labels'])'
                    data-auth='@json($volume['auth'])' data-write='@json($volume['write'])'
                    data-other='@json($volume['other'])'></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Event split') }}
                </div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Auth vs writes vs reads') }}</h3>
                <div id="chart-direction" class="h-[200px]" data-auth="{{ $categoryDonut['auth'] }}"
                    data-writes="{{ $categoryDonut['writes'] }}" data-reads="{{ $categoryDonut['reads'] }}"
                    data-total="{{ $categoryDonut['total'] }}"></div>
                <div class="mt-3 space-y-1.5 text-[12px]">
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-deep"></span>{{ __('Auth') }}</span><span
                            class="font-mono text-ink-700">{{ number_format($categoryDonut['auth']) }}</span></div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-teal"></span>{{ __('Writes') }}</span><span
                            class="font-mono text-ink-700">{{ number_format($categoryDonut['writes']) }}</span></div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-accent-coral"></span>{{ __('Reads / other') }}</span><span
                            class="font-mono text-ink-700">{{ number_format($categoryDonut['reads']) }}</span></div>
                </div>
            </div>
        </section>

        <!-- Filter bar + table + side detail -->
        <section class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-4 items-start">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card flex flex-col min-h-[520px]"
                data-list-grid data-list-grid-key="activity-log">
                <div class="px-4 py-3 border-b border-paper-200 flex items-center gap-2 flex-wrap">
                    <div class="relative flex-1 min-w-[260px] max-w-[480px]">
                        <svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <circle cx="7" cy="7" r="5" />
                            <path d="m11 11 3 3" />
                        </svg>
                        <input id="al-search" type="search" value="{{ $qCur }}"
                            placeholder="{{ __('Search by action, subject, or IP...') }}"
                            class="w-full pl-9 pr-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </div>
                    <div class="flex items-center gap-1 bg-paper-50 rounded-full p-1" id="al-scope-tabs">
                        @foreach (['me' => 'My activity', 'workspace' => 'Workspace'] as $k => $label)
                            <button
                                class="al-scope-tab px-3 py-1 rounded-full text-[11.5px] font-semibold {{ $scope === $k ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100' }}"
                                data-scope="{{ $k }}">{{ $label }}</button>
                        @endforeach
                    </div>
                    <select id="al-category"
                        class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        @php
                            $catOptions = [
                                'all' => 'All categories',
                                'auth' => 'Auth',
                                'conversation' => 'Inbox',
                                'note' => 'Notes',
                                'team' => 'Teams',
                                'broadcast' => 'Broadcasts',
                                'webhook' => 'Webhooks',
                                'workspace' => 'Workspace',
                                'impersonation' => 'Impersonation',
                            ];
                        @endphp
                        @foreach ($catOptions as $key => $label)
                            <option value="{{ $key }}" @selected($cat === $key)>{{ $label }}
                                @if (isset($categoryCnts[$key]))
                                    ({{ number_format($categoryCnts[$key]) }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <button id="al-clear-filters"
                        class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] hover:bg-paper-50 inline-flex items-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M2 4h12M4 8h8M6 12h4" />
                        </svg>
                        {{ __('Reset filters') }}
                    </button>
                    <x-list-grid-toggle />
                </div>

                <div class="overflow-x-auto flex-1" data-list-grid-list>
                    <table class="w-full text-[12.5px]" data-list-grid-source>
                        <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                            <tr>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-10">
                                    <input type="checkbox" id="al-pick-all"
                                        class="rounded border-paper-200 text-wa-deep focus:ring-wa-deep" /></th>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5 w-[110px]">
                                    {{ __('When') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Action') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Category') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Actor') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Subject') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('IP') }}</th>
                                <th
                                    class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-[80px]">
                                    {{ __('Open') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200" id="al-rows">
                            @include('user.activity-log._rows', ['rows' => $rows])
                        </tbody>
                    </table>
                </div>
                <div class="hidden p-4" data-list-grid-grid></div>

                <div id="al-results-footer"
                    class="px-4 py-3 border-t border-paper-200 flex items-center justify-between text-[12px] text-ink-500 {{ $total > 0 ? '' : 'hidden' }}">
                    <div>{{ __('Showing') }} <span class="font-mono text-ink-900"
                            data-al="shownRange">{{ $shownFrom }}–{{ $shownTo }}</span> {{ __('of') }}
                        <span class="font-mono text-ink-900" data-al="totalRows">{{ number_format($total) }}</span>
                    </div>
                    <div class="flex items-center gap-1" id="al-pagination">
                        <button class="px-2.5 py-1 rounded-md border border-paper-200 hover:bg-paper-50 text-[11px]"
                            data-al-page="prev" {{ $page <= 1 ? 'disabled' : '' }}>{{ __('Prev') }}</button>
                        @for ($i = max(1, $page - 2); $i <= min($pageCount, $page + 2); $i++)
                            <button
                                class="px-2.5 py-1 rounded-md {{ $i === $page ? 'bg-wa-deep text-paper-0 font-semibold' : 'hover:bg-paper-50' }} text-[11px]"
                                data-al-page="{{ $i }}">{{ $i }}</button>
                        @endfor
                        <button class="px-2.5 py-1 rounded-md border border-paper-200 hover:bg-paper-50 text-[11px]"
                            data-al-page="next"
                            {{ $page >= $pageCount ? 'disabled' : '' }}>{{ __('Next') }}</button>
                    </div>
                </div>
            </div>

            <!-- Detail panel -->
            <aside
                class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden xl:sticky xl:top-[20px]"
                id="al-detail">
                <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Event detail') }}</span>
                    <span id="al-event-id"
                        class="font-mono text-[10.5px] text-ink-500">{{ $detail ? '#' . $detail['id'] : '' }}</span>
                </div>
                <div class="p-4 space-y-3" id="al-detail-body">
                    @if ($detail)
                        <div class="flex items-center gap-3">
                            <span id="d-icon"
                                class="w-10 h-10 rounded-lg {{ $detail['iconBg'] }} {{ $detail['iconFg'] }} grid place-items-center shrink-0">{!! $detail['iconHtml'] !!}</span>
                            <div class="min-w-0">
                                <div id="d-action" class="font-semibold truncate">{{ $detail['actionLabel'] }}</div>
                                <div id="d-action-key" class="text-[10.5px] font-mono text-ink-500 truncate">
                                    {{ $detail['action'] }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-[11.5px]">
                            <div class="bg-paper-50 rounded-md p-2">
                                <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                                    {{ __('Category') }}</div>
                                <div id="d-category" class="font-mono text-ink-900 mt-0.5">
                                    {{ $detail['categoryLabel'] }}</div>
                            </div>
                            <div class="bg-paper-50 rounded-md p-2">
                                <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                                    {{ __('Layer') }}</div>
                                <div id="d-layer" class="font-mono text-ink-900 mt-0.5">{{ $detail['layer'] }}
                                </div>
                            </div>
                            <div class="bg-paper-50 rounded-md p-2">
                                <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                                    {{ __('Actor') }}</div>
                                <div id="d-actor" class="font-mono text-ink-900 mt-0.5 truncate">
                                    {{ $detail['actorName'] }}</div>
                            </div>
                            <div class="bg-paper-50 rounded-md p-2">
                                <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                                    {{ __('When') }}</div>
                                <div id="d-when" class="font-mono text-ink-900 mt-0.5">{{ $detail['createdAt'] }}
                                </div>
                            </div>
                            <div class="bg-paper-50 rounded-md p-2">
                                <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                                    {{ __('Subject') }}</div>
                                <div id="d-subject" class="font-mono text-ink-900 mt-0.5 truncate">
                                    {{ $detail['subject'] }}</div>
                            </div>
                            <div class="bg-paper-50 rounded-md p-2">
                                <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                                    {{ __('IP') }}</div>
                                <div id="d-ip" class="font-mono text-ink-900 mt-0.5 truncate">
                                    {{ $detail['ip'] }}</div>
                            </div>
                        </div>

                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1.5">
                                {{ __('User agent') }}</div>
                            <div id="d-ua"
                                class="text-[11px] leading-snug bg-paper-0 border border-paper-200 rounded-md p-2.5 break-words font-mono">
                                {{ $detail['fullUserAgent'] }}</div>
                        </div>

                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1.5">
                                {{ __('Payload') }}</div>
                            <pre id="d-payload"
                                class="text-[11px] leading-snug bg-ink-900 text-paper-0 rounded-md p-3 overflow-x-auto font-mono max-h-[260px]">{{ $detail['payloadJson'] }}</pre>
                        </div>
                    @else
                        @include('user.partials.empty-state', [
                            'message' =>
                                'No events found. Sign in, switch workspaces, or work in the inbox and events will appear here.',
                            'resetHref' => url('/activity-log'),
                        ])
                    @endif
                </div>
                <div class="px-4 py-3 border-t border-paper-200 flex items-center gap-2 bg-paper-50/40">
                    <button id="d-copy-id"
                        class="flex-1 px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium"
                        {{ $detail ? '' : 'disabled' }}>{{ __('Copy ID') }}</button>
                    <button id="d-copy-payload"
                        class="flex-1 px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium"
                        {{ $detail ? '' : 'disabled' }}>{{ __('Copy payload') }}</button>
                </div>
            </aside>
        </section>

        <!-- Bottom: top actors + category mix -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Top actors') }}</div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Most active users') }}
                            ({{ $rangeKey }})</h3>
                    </div>
                    <a href="{{ url('/team-inbox/members') }}"
                        class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('View members') }}</a>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @forelse ($topActors as $a)
                        <div class="border border-paper-200 rounded-lg p-3 flex items-center gap-3">
                            <span
                                class="w-9 h-9 rounded-full bg-gradient-to-br {{ $a['gradient'] }} text-paper-0 grid place-items-center text-[11px] font-semibold shrink-0">{{ $a['initials'] }}</span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[12.5px] font-semibold truncate">{{ $a['name'] }}</div>
                                <div class="text-[10.5px] text-ink-500 font-mono">{{ $a['count'] }}
                                    {{ __('events · last') }} {{ $a['lastAt'] }}</div>
                            </div>
                            <span
                                class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep">#{{ $a['id'] }}</span>
                        </div>
                    @empty
                        @include('user.partials.empty-state', [
                            'class' => 'col-span-2',
                            'message' =>
                                'No active users found in this window. As soon as someone signs in or works in the workspace, they will appear here.',
                            'resetHref' => url('/activity-log'),
                        ])
                    @endforelse
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('By category') }}
                </div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Mix this window') }}</h3>
                <div id="chart-categories" class="h-[220px]" data-labels='@json($categoryMix['labels'])'
                    data-data='@json($categoryMix['data'])'></div>
            </div>
        </section>

    </main>

</x-layouts.user>
