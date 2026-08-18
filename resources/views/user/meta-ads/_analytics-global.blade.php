@php
    /** @var array $aggregate */
    /** @var \Illuminate\Support\Collection $picker */
    $a = $aggregate ?? [];
    $kfmt = function ($n) {
        if ($n >= 1_000_000) {
            return number_format($n / 1_000_000, 1) . 'm';
        }
        if ($n >= 1_000) {
            return number_format($n / 1_000, 1) . 'k';
        }
        return number_format($n);
    };
    $fmt = fn($n, $d = 0) => number_format($n, $d);
    $hasData = ($a['total_campaigns'] ?? 0) > 0;
    $goalLabels = [
        'MESSAGES' => 'Messages',
        'LINK_CLICKS' => 'Link clicks',
        'LEAD_GENERATION' => 'Lead gen',
        'CONVERSIONS' => 'Conversions',
        'BRAND_AWARENESS' => 'Brand awareness',
        'REACH' => 'Reach',
        'VIDEO_VIEWS' => 'Video views',
    ];
@endphp

@if (!$hasData)
    <main class="max-w-none mx-auto px-4 sm:px-7 py-12">
        @include('user.partials.empty-state', [
            'class' => 'max-w-2xl mx-auto',
            'message' =>
                'No Meta Ads campaign data found. Create your first campaign and analytics will appear as soon as it starts spending.',
            'resetHref' => url('/meta-ads'),
            'resetLabel' => 'Back to Meta Ads',
            'actionHref' => route('user.meta-ads.create'),
            'actionLabel' => 'Create campaign',
        ])
    </main>
@else
    <main class="max-w-none mx-auto px-4 sm:px-7 py-6 space-y-5">

        {{-- Hero band — workspace-wide totals --}}
        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-6 py-5 border-b border-paper-200 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5">
                <div>
                    <div class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-[0.16em] text-ink-500">
                        <span>{{ __('Workspace') }}</span>
                        <span class="w-1 h-1 rounded-full bg-ink-500"></span>
                        <span>{{ $a['total_campaigns'] }} campaign{{ $a['total_campaigns'] === 1 ? '' : 's' }} ·
                            {{ $a['active'] }} {{ __('active') }}</span>
                    </div>
                    <h1 class="font-serif text-[28px] sm:text-[34px] lg:text-[40px] leading-none mt-2">{{ __('Meta Ads') }} <span
                            class="italic text-wa-deep">{{ __('analytics') }}</span></h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Aggregate performance across every campaign in this workspace. Click any row in the table below to drill into a specific campaign.') }}
                    </p>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 w-full lg:w-[520px]">
                    <div class="rounded-2xl bg-wa-bubble border border-wa-green/30 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('ROAS') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1 text-wa-deep">{{ $fmt($a['roas'], 2) }}x
                        </div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Spend') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">{{ $moneyK($a['spend']) }}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Revenue') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">{{ $moneyK($a['revenue']) }}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Leads') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">{{ $fmt($a['conversions']) }}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('CPL') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">{{ $money($a['cpl'], 2) }}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('CTR') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1 text-wa-deep">{{ $fmt($a['ctr'], 2) }}%
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Workspace-wide metric grid --}}
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Impressions') }}</div>
                <div class="font-serif text-[38px] leading-none mt-2">{{ $kfmt($a['impressions']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('across all campaigns') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Reach') }}</div>
                <div class="font-serif text-[38px] leading-none mt-2">{{ $kfmt($a['reach']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">
                    {{ $a['impressions'] && $a['reach'] ? $fmt($a['impressions'] / max($a['reach'], 1), 2) : '0.00' }}×
                    frequency</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Clicks') }}</div>
                <div class="font-serif text-[38px] leading-none mt-2">{{ $fmt($a['clicks']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ $money($a['cpc'], 2) }} CPC</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Active campaigns') }}</div>
                <div class="font-serif text-[38px] leading-none mt-2">{{ $a['active'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ $a['paused'] }} paused · {{ $a['scheduled'] }}
                    {{ __('scheduled') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Qualified leads') }}</div>
                <div class="font-serif text-[38px] leading-none mt-2">{{ $fmt($a['conversions']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ $money($a['cpl'], 2) }} {{ __('per lead') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('ROAS') }}</div>
                <div class="font-serif text-[38px] leading-none mt-2">{{ $fmt($a['roas'], 2) }}x</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ $moneyK($a['revenue']) }} on {{ $moneyK($a['spend']) }}
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            {{-- Top campaigns by ROAS --}}
            <div class="lg:col-span-7 bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Top performers') }}</div>
                        <h2 class="font-serif text-[24px] leading-tight mt-1">{{ __('Highest ROAS campaigns') }}</h2>
                    </div>
                    <a href="{{ url('/meta-ads') }}"
                        class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-semibold">{{ __('All campaigns') }}</a>
                </div>
                @if (count($a['top']))
                    <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead
                            class="bg-paper-50 text-ink-500 font-mono text-[10px] uppercase tracking-[0.16em] border-b border-paper-200">
                            <tr>
                                <th class="text-left px-4 py-2">{{ __('Campaign') }}</th>
                                <th class="text-left px-3 py-2">{{ __('Goal') }}</th>
                                <th class="text-right px-3 py-2">{{ __('Spend') }}</th>
                                <th class="text-right px-3 py-2">{{ __('Leads') }}</th>
                                <th class="text-right px-3 py-2">{{ __('ROAS') }}</th>
                                <th class="text-left px-3 py-2 w-[80px]"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200">
                            @foreach ($a['top'] as $row)
                                <tr class="hover:bg-paper-50">
                                    <td class="px-4 py-2">
                                        <div class="font-semibold truncate">{{ $row['name'] }}</div>
                                        <div class="text-[10.5px] text-ink-500">
                                            {{ ucfirst(strtolower($row['status'])) }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-ink-600">{{ $goalLabels[$row['goal']] ?? $row['goal'] }}
                                    </td>
                                    <td class="px-3 py-2 font-mono text-right">{{ $money($row['spend'], 2) }}</td>
                                    <td class="px-3 py-2 font-mono text-right">{{ $fmt($row['leads']) }}</td>
                                    <td class="px-3 py-2 font-mono text-right text-wa-deep font-semibold">
                                        {{ $fmt($row['roas'], 2) }}x</td>
                                    <td class="px-3 py-2 text-right">
                                        <a href="{{ route('user.meta-ads.analytics', ['id' => $row['id']]) }}"
                                            class="text-[11px] font-semibold text-wa-deep hover:underline">Drill in
                                            →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                @else
                    <div class="p-10 text-center text-[13px] text-ink-500">{{ __('No campaigns have spent yet.') }}
                    </div>
                @endif
            </div>

            {{-- Spend mix by objective --}}
            <div class="lg:col-span-5 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Mix by objective') }}</div>
                <h2 class="font-serif text-[24px] leading-tight mt-1 mb-4">{{ __('Spend share') }}</h2>
                @php $maxSpend = collect($a['by_goal'])->max('spend') ?: 1; @endphp
                <div class="space-y-3">
                    @forelse ($a['by_goal'] as $goal => $row)
                        @php $pct = $maxSpend ? ($row['spend'] / $maxSpend * 100) : 0; @endphp
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1">
                                <span>{{ $goalLabels[$goal] ?? $goal }}</span>
                                <span class="font-mono text-ink-500">{{ $money($row['spend'], 2) }} ·
                                    {{ $row['count'] }} run · {{ $row['leads'] }}
                                    lead{{ $row['leads'] === 1 ? '' : 's' }}</span>
                            </div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-wa-deep" style="width:{{ max(2, $pct) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-[12.5px] text-ink-500">{{ __('No spend yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- Status breakdown --}}
        <section class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div class="bg-paper-0 border border-wa-green/30 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Status') }}</div>
                <h2 class="font-serif text-[24px] leading-tight mt-1">{{ __('Active') }}</h2>
                <div class="mt-2 font-serif text-[44px] leading-none text-wa-deep">{{ $a['active'] }}</div>
                <p class="text-[11.5px] text-ink-500 mt-2">{{ __('campaigns currently delivering') }}</p>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Status') }}</div>
                <h2 class="font-serif text-[24px] leading-tight mt-1">{{ __('Paused') }}</h2>
                <div class="mt-2 font-serif text-[44px] leading-none">{{ $a['paused'] }}</div>
                <p class="text-[11.5px] text-ink-500 mt-2">{{ __('runs you can re-activate') }}</p>
            </div>
            <div class="bg-paper-0 border border-[#13478A]/30 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Status') }}</div>
                <h2 class="font-serif text-[24px] leading-tight mt-1">{{ __('Scheduled') }}</h2>
                <div class="mt-2 font-serif text-[44px] leading-none text-[#13478A]">{{ $a['scheduled'] }}</div>
                <p class="text-[11.5px] text-ink-500 mt-2">{{ __('queued for a future date') }}</p>
            </div>
        </section>
    </main>

@endif
