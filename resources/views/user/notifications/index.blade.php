<x-layouts.user :title="__('Notifications')" nav-key="more" page="user-notifications-index">

    @php
        $stats = $stats ?? [
            'unread' => 0,
            'urgent' => 0,
            'today' => 0,
            'week' => 0,
            'todayDelta' => 0,
            'activeRules' => 0,
        ];
        $categoryCounts = $categoryCounts ?? ['all' => 0];
        $currentCategory = $currentCategory ?? 'all';
        $currentQuery = $currentQuery ?? '';
        $currentPage = $currentPage ?? 1;
        $totalShown = $totalShown ?? 0;
        $totalFiltered = $totalFiltered ?? $categoryCounts['all'];
        $notifications =
            $notifications ??
            new \Illuminate\Pagination\LengthAwarePaginator(collect(), 0, 12, 1, ['path' => url('/notifications')]);
        $cats = [
            'all' => 'All',
            'unread' => 'Unread',
            'mention' => 'Mentions',
            'campaign' => 'Campaigns',
            'system' => 'System',
            'billing' => 'Billing',
            'webhook' => 'Webhooks',
            'broadcast' => 'Broadcasts',
            'template' => 'Templates',
            'device' => 'Devices',
            'contact' => 'Contacts',
        ];
    @endphp

    <!-- Sub header -->
    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-7 py-3 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/more') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to More') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg></a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('More / Notifications') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate"><span
                            class="italic text-wa-deep">{{ __('Notifications') }}</span> &amp; alerts</div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button id="dnd-btn"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <circle cx="8" cy="8" r="6" />
                        <path d="M5 8h6" />
                    </svg>
                    <span id="dnd-label">{{ __('Do not disturb') }}</span>
                </button>
                <button id="mark-all"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M2 8l3 3 6-7M7 11l3 3 5-9" />
                    </svg>
                    Mark all read
                </button>
                <a href="{{ url('/settings') }}"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6">
                        <circle cx="8" cy="8" r="2" />
                        <path
                            d="M13 8a5 5 0 0 0-.1-1.1l1.4-1-1.5-2.6-1.6.7a5 5 0 0 0-1.9-1.1L9 1H7l-.3 1.9a5 5 0 0 0-1.9 1.1l-1.6-.7-1.5 2.6 1.4 1A5 5 0 0 0 3 8c0 .4 0 .7.1 1.1l-1.4 1 1.5 2.6 1.6-.7a5 5 0 0 0 1.9 1.1L7 15h2l.3-1.9a5 5 0 0 0 1.9-1.1l1.6.7 1.5-2.6-1.4-1c.1-.4.1-.7.1-1.1Z" />
                    </svg>
                    Preferences
                </a>
            </div>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-7 py-6 space-y-6" data-notif-state data-notif-category="{{ $currentCategory }}"
        data-notif-search="{{ $currentQuery }}" data-notif-page="{{ $currentPage }}">

        <!-- KPI strip -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Unread') }}</span>
                    <span
                        class="text-[10px] font-mono {{ $stats['urgent'] > 0 ? 'text-accent-coral' : 'text-ink-500' }}">
                        <span data-stat="urgent">{{ $stats['urgent'] }}</span> urgent
                    </span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none"
                        data-stat="unread">{{ number_format($stats['unread']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('across all channels') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Today') }}</span>
                    <span
                        class="text-[10px] font-mono {{ $stats['todayDelta'] >= 0 ? 'text-wa-deep' : 'text-accent-coral' }}">
                        {{ $stats['todayDelta'] >= 0 ? '+' : '' }}<span
                            data-stat="todayDelta">{{ $stats['todayDelta'] }}</span>% vs avg
                    </span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none"
                        data-stat="today">{{ number_format($stats['today']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('events today') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Active categories') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono">{{ __('healthy') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none"
                        data-stat="activeRules">{{ $stats['activeRules'] }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('routing alerts') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Delivery') }}</span>
                    <a href="{{ url('/settings?tab=notifications') }}"
                        class="text-[10px] text-wa-deep font-mono hover:underline">{{ __('manage') }}</a>
                </div>
                @php
                    $wsPrefs = auth()->user()?->currentWorkspace?->notification_prefs ?? [];
                    $slackOn = !empty($wsPrefs['_slack_webhook']);
                    $mailOn = !in_array(config('mail.default'), [null, '', 'array', 'log'], true);
                    $chans = array_values(
                        array_filter([__('In-app'), $mailOn ? __('Email') : null, $slackOn ? __('Slack') : null]),
                    );
                @endphp
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[22px] leading-none">{{ implode(' · ', $chans) }}</span>
                </div>
                <span class="text-[11px] text-ink-500">{{ __('active channels') }}</span>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-5 items-start">

            <!-- Notification feed -->
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card">
                <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between gap-4 flex-wrap">
                    <div class="flex items-center gap-1 bg-paper-50 rounded-full p-1 flex-wrap" id="cat-tabs">
                        @foreach ($cats as $key => $label)
                            <button type="button" data-notif-filter="category" data-notif-value="{{ $key }}"
                                class="cat-tab px-3.5 py-1.5 rounded-full text-[12px] font-semibold {{ $currentCategory === $key ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100' }}">
                                {{ $label }} <span class="ml-1 font-mono text-[10px] opacity-80"
                                    data-notif-cat-count="{{ $key }}">{{ $categoryCounts[$key] ?? 0 }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="relative max-w-[260px] flex-1 min-w-[200px]">
                        <svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <circle cx="7" cy="7" r="5" />
                            <path d="m11 11 3 3" />
                        </svg>
                        <input id="notif-search" type="search" value="{{ $currentQuery }}"
                            placeholder="{{ __('Search notifications...') }}"
                            class="w-full pl-9 pr-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </div>
                </div>

                <div id="notif-feed">
                    @include('user.notifications._feed', ['grouped' => $grouped])
                </div>

                <div id="notif-pagination">
                    @include('user.notifications._pagination', ['notifications' => $notifications])
                </div>

                <div id="notif-results-footer"
                    class="px-4 py-3 border-t border-paper-200 flex items-center justify-between text-[12px] text-ink-500 {{ $totalFiltered > 0 ? '' : 'hidden' }}">
                    <div>{{ __('Showing') }} <span class="font-mono text-ink-900"
                            data-notif-shown>{{ $totalShown }}</span> of <span class="font-mono text-ink-900"
                            data-notif-total>{{ $totalFiltered }}</span></div>
                    <button type="button" id="notif-clear-all"
                        class="text-[11px] text-accent-coral font-semibold hover:underline">{{ __('Clear all') }}</button>
                </div>
            </div>

            <!-- Right column: tip + filter pinning -->
            <aside class="space-y-4">
                <div class="bg-wa-deep rounded-[14px] p-5 shadow-soft text-paper-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/60">{{ __('Tip') }}
                    </div>
                    <div class="font-serif text-[22px] leading-tight mt-1">{{ __('Auto-recorded') }}</div>
                    <p class="mt-2 text-[12px] text-paper-0/80 leading-relaxed">
                        {{ __('Every create / update / delete across your workspace lands here automatically. Tap a category tab to filter to just one source.') }}
                    </p>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('Top sources (this week)') }}</div>
                    <div class="space-y-2">
                        @foreach (collect($categoryCounts)->except(['all', 'unread'])->sortDesc()->take(6) as $cat => $count)
                            @if ($count > 0)
                                <div class="flex items-center justify-between text-[12px]">
                                    <span class="font-mono text-ink-700">{{ ucfirst($cat) }}</span>
                                    <span class="font-mono text-ink-900">{{ number_format($count) }}</span>
                                </div>
                            @endif
                        @endforeach
                        @if (collect($categoryCounts)->except(['all', 'unread'])->filter()->isEmpty())
                            @include('user.partials.empty-state', [
                                'message' =>
                                    'No activity found. Notification sources will appear here once events start arriving.',
                            ])
                        @endif
                    </div>
                </div>
            </aside>

        </section>
    </main>

</x-layouts.user>
