<x-layouts.user :title="__('Analytics')" nav-key="more" page="user-analytics-index">

    <!-- ========== TOP BAR ========== -->


    <!-- ========== HERO BAND ========== -->
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pt-7 pb-4">
        <div class="flex items-end justify-between flex-wrap gap-3">
            <div>
                <div
                    class="flex items-center gap-3 mb-2 text-[11px] mono font-mono uppercase tracking-[0.18em] text-ink-500">
                    <span>{{ __('Analytics · Workspace') }}</span>
                    <span class="w-1 h-1 rounded-full bg-ink-500/50"></span>
                    <span class="flex items-center gap-1.5"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green animate-pulse"></span>{{ __('Live · last refresh') }}
                        {{ now(wa_tz())->format('H:i') }} {{ $heatmapTzLabel ?? 'UTC' }}</span>
                </div>
                @php
                    $totalMessages = $totalMessages ?? 0;
                    $delivered = $delivered ?? 0;
                    $failed = $failed ?? 0;
                    $repliesIn = $repliesIn ?? 0;
                    $uniqueRecipients = $uniqueRecipients ?? 0;
                    $deliverabilityPct = $deliverabilityPct ?? 0;
                    $replyRatePct = $replyRatePct ?? 0;
                @endphp
                <h1
                    class="serif font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[38px] lg:text-[44px] xl:text-[54px] leading-[1.02] tracking-tight">
                    <span class="italic text-wa-deep">{{ number_format($deliverabilityPct, 1) }}%</span> of messages
                    reached their reader this week.
                </h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    @if ($totalMessages)
                        <b class="tabular tabular-nums">{{ number_format($totalMessages) }}</b> messages · <b
                            class="tabular tabular-nums">{{ number_format($uniqueRecipients) }}</b> unique recipients ·
                        <b class="tabular tabular-nums">{{ number_format($replyRatePct, 1) }}%</b> reply rate.
                    @else
                        No outgoing messages yet — once your first queue sends, this dashboard fills in.
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('user.analytics.export', request()->only(['range', 'from', 'to'])) }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.5">
                        <path d="M8 2v8m0 0L5 7m3 3 3-3M3 13h10" />
                    </svg>
                    {{ __('Export CSV') }}
                </a>
            </div>
        </div>
    </section>

    <!-- ========== DATE FILTER ========== -->
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-4">
        <form method="GET" action="{{ url('/analytics') }}"
            class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-3 shadow-card flex items-center gap-2 flex-wrap"
            id="range-bar">
            <input type="hidden" name="range" value="{{ $range }}" id="range-input">
            <div class="flex items-center gap-1 flex-wrap">
                @foreach ([
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        'custom' => 'Custom',
    ] as $key => $label)
                    <button type="button" data-range="{{ $key }}"
                        class="filter-tab inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-[13px] cursor-pointer transition
 {{ $range === $key ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <div class="flex-1 hidden md:block"></div>
            <div class="flex items-center gap-2 flex-wrap">
                <label class="flex items-center gap-1.5 text-[12px] text-ink-500 mono font-mono">
                    <span>{{ __('From') }}</span>
                    <input type="date" name="from" value="{{ $fromDate }}"
                        class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 focus:outline-none focus:border-wa-deep">
                </label>
                <label class="flex items-center gap-1.5 text-[12px] text-ink-500 mono font-mono">
                    <span>To</span>
                    <input type="date" name="to" value="{{ $toDate }}"
                        class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 focus:outline-none focus:border-wa-deep">
                </label>
                <button type="submit"
                    class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] mono font-mono bg-paper-0 hover:bg-paper-50 flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M3 4h10M5 8h6M7 12h2" />
                    </svg>
                    Apply
                </button>
            </div>
        </form>
        <script>
            // Filter pills set the hidden range input + submit the form so
            // the URL ends up as ?range=7d|30d|90d|custom&from=&to=. Apply
            // button submits via its native submit type.
            (function() {
                const form = document.getElementById('range-bar');
                if (!form) return;
                const hidden = form.querySelector('#range-input');
                form.querySelectorAll('button[data-range]').forEach(btn => {
                    btn.addEventListener('click', () => {
                        hidden.value = btn.dataset.range;
                        if (btn.dataset.range !== 'custom') form.submit();
                        else btn.classList.add('bg-wa-deep', 'text-paper-0');
                    });
                });
            })();
        </script>
    </section>

    {{-- Bridge real workspace data to the analytics chart JS. The JS file
 reads window.ANALYTICS_DATA so we don't rely on data-attributes
 per element (cleaner than 6 separate dataset reads). --}}
    <script>
        window.ANALYTICS_DATA = {
            labels: @json($dailyLabels),
            sent: @json($dailySent),
            delivered: @json($dailyDelivered),
            failed: @json($dailyFailed),
            queued: @json($dailyQueued),
            types: {
                labels: @json($typeLabels),
                values: @json($typeValues)
            },
            devices: {
                labels: @json($deviceLabels),
                values: @json($deviceData)
            },
            heatmap: @json($heatmapSeries),
        };
    </script>

    {{-- ========== INSTAGRAM BAND — only when an Instagram account is connected;
         else $igStats is null and this is skipped so the page stays the WhatsApp
         analytics view. ========== --}}
    @if (!empty($igStats))
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3">
        <div class="rounded-2xl border border-paper-200 bg-paper-0 overflow-hidden">
            <div class="h-1" style="background:linear-gradient(90deg,#405DE6,#833AB4,#C13584,#E1306C,#F77737,#FCAF45);"></div>
            <div class="p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg text-white" style="background:linear-gradient(135deg,#833AB4,#E1306C,#F77737);">
                            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"/></svg>
                        </span>
                        <h2 class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Instagram') }}
                            <span class="text-ink-400 normal-case tracking-normal">· {{ trans_choice('{1}:count account|[2,*]:count accounts', $igStats['accounts_count'], ['count' => $igStats['accounts_count']]) }}</span>
                        </h2>
                    </div>
                    <a href="{{ url('/team-inbox') }}" class="text-[11px] font-semibold hover:underline" style="color:#C13584;">{{ __('Open inbox →') }}</a>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div>
                        <div class="font-serif text-[22px] leading-none text-ink-900">{{ number_format($igStats['dms_in_7d']) }}</div>
                        <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1.5">{{ __('DMs in · 7d') }}</div>
                    </div>
                    <div>
                        <div class="font-serif text-[22px] leading-none text-ink-900">{{ number_format($igStats['dms_out_7d']) }}</div>
                        <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1.5">{{ __('DMs sent · 7d') }}</div>
                    </div>
                    <div>
                        <div class="font-serif text-[22px] leading-none text-ink-900">{{ $igStats['reply_rate'] }}%</div>
                        <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1.5">{{ __('Reply rate') }}</div>
                    </div>
                    <div>
                        <div class="font-serif text-[22px] leading-none text-ink-900">{{ number_format($igStats['needs_reply']) }}</div>
                        <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1.5">{{ __('Need reply') }}</div>
                    </div>
                    <div>
                        <div class="font-serif text-[22px] leading-none text-ink-900">{{ number_format($igStats['open']) }}</div>
                        <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1.5">{{ __('Open chats') }}</div>
                    </div>
                    <div>
                        <div class="font-serif text-[22px] leading-none text-ink-900">{{ $igStats['followers'] >= 1000 ? number_format($igStats['followers']/1000, 1).'k' : number_format($igStats['followers']) }}</div>
                        <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1.5">{{ __('Followers') }}</div>
                    </div>
                </div>
                @if (!empty($igStats['accounts']))
                <div class="flex flex-wrap items-center gap-2 mt-4 pt-3 border-t border-paper-100">
                    @foreach ($igStats['accounts'] as $acc)
                    <span class="inline-flex items-center gap-2 rounded-full border border-paper-200 bg-paper-50 pl-1 pr-3 py-1">
                        @if (!empty($acc['avatar']))
                            <img src="{{ $acc['avatar'] }}" alt="" class="w-5 h-5 rounded-full object-cover" onerror="this.style.display='none'">
                        @else
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-white text-[9px] font-bold" style="background:linear-gradient(135deg,#833AB4,#E1306C,#F77737);">{{ strtoupper(substr(ltrim($acc['handle'],'@'),0,1)) }}</span>
                        @endif
                        <span class="text-[11px] font-medium text-ink-800">{{ $acc['handle'] }}</span>
                    </span>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </section>
    @endif

    <!-- ========== HERO KPI ROW ========== -->
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">

            <!-- Hero KPI: dark deep-teal card with sparkline -->
            <div class="lg:col-span-5 bg-wa-deep text-paper-0 rounded-2xl p-5 shadow-soft relative overflow-hidden">
                <div
                    class="absolute inset-0 dot-pattern [background-image:radial-gradient(circle_at_1px_1px,rgba(7,94,84,0.18)_1px,transparent_0)] bg-[length:14px_14px] opacity-15">
                </div>
                <div
                    class="absolute -right-12 -top-12 w-56 h-56 rounded-full bg-[radial-gradient(circle,rgba(37,211,102,0.4)_0%,transparent_60%)]">
                </div>
                <div class="relative">
                    <div class="flex items-start justify-between">
                        <span
                            class="mono font-mono text-[10px] uppercase tracking-widest text-paper-0/60">{{ __('Total messages · all time') }}</span>
                        @if ($totalMessages)
                            <span
                                class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-green/20 text-wa-green border border-wa-green/40">{{ number_format($deliverabilityPct, 1) }}%
                                delivered</span>
                        @endif
                    </div>
                    <div class="mt-4 flex items-end gap-3">
                        <span
                            class="serif font-serif font-normal tracking-[-0.01em] text-[48px] sm:text-[58px] lg:text-[68px] leading-[0.9] tabular tabular-nums">{{ number_format($totalMessages) }}</span>
                        <div class="pb-3 text-[11px] text-paper-0/70 mono font-mono">
                            <div class="flex items-center gap-1"><span
                                    class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ number_format($delivered) }}
                                {{ __('delivered') }}</div>
                            <div class="flex items-center gap-1 mt-0.5"><span
                                    class="w-1.5 h-1.5 rounded-full bg-accent-coral"></span>{{ number_format($failed) }}
                                {{ __('failed') }}</div>
                        </div>
                    </div>
                    <div id="hero-spark" class="mt-3"></div>
                    <div
                        class="mt-3 hairline-t border-t border-paper-200 border-paper-0/15 pt-3 grid grid-cols-3 gap-3 text-[11px] mono font-mono text-paper-0/70">
                        <div><span class="block text-paper-0/50">{{ __('recipients') }}</span><span
                                class="serif font-serif font-normal tracking-[-0.01em] text-[18px] tabular tabular-nums text-paper-0">{{ number_format($uniqueRecipients) }}</span>
                        </div>
                        <div><span class="block text-paper-0/50">{{ __('replies in') }}</span><span
                                class="serif font-serif font-normal tracking-[-0.01em] text-[18px] tabular tabular-nums text-paper-0">{{ number_format($repliesIn) }}</span>
                        </div>
                        <div><span class="block text-paper-0/50">{{ __('reply rate') }}</span><span
                                class="serif font-serif font-normal tracking-[-0.01em] text-[18px] tabular tabular-nums text-wa-green">{{ number_format($replyRatePct, 1) }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Side stats -->
            <div class="lg:col-span-7 grid grid-cols-2 sm:grid-cols-3 gap-3">
                @php
                    $trendPill = function ($delta, $invertGood = false) {
                        $isUp = $delta >= 0;
                        $good = $invertGood ? !$isUp : $isUp;
                        $color = $good
                            ? 'bg-wa-green/10 text-wa-deep border border-wa-green/30'
                            : 'bg-accent-coral/10 text-[#A1431F] border border-accent-coral/35';
                        $arrow = $isUp ? '↑' : '↓';
                        $val = abs((float) $delta);
                        return [$color, $arrow, $val];
                    };
                @endphp
                @php
                    [$cD, $aD, $vD] = $trendPill($deltaDelivered);
                @endphp
                <div
                    class="stat bg-white border border-paper-200 rounded-[14px] px-[18px] py-4 relative overflow-hidden before:content-[''] before:absolute before:left-0 before:top-0 before:bottom-0 before:w-[3px] s-green before:bg-wa-green">
                    <div class="flex items-center justify-between">
                        <div class="stat-label text-[11px] text-ink-600 font-medium flex items-center gap-1.5"><span
                                class="stat-icon w-7 h-7 rounded-lg inline-flex items-center justify-center bg-[rgba(37,211,102,0.18)]"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="currentColor">
                                    <path d="M2 8l8-3.5-3 8z" />
                                </svg></span>Delivered</div>
                        <span
                            class="{{ $cD }} px-[7px] py-0.5 rounded-full text-[11px] font-semibold font-mono">{{ $aD }}
                            {{ $vD }}%</span>
                    </div>
                    <div
                        class="stat-value font-serif text-[36px] leading-none tracking-[-0.02em] mt-1.5 tabular tabular-nums">
                        {{ number_format($delivered) }}</div>
                    <div class="text-[11px] text-ink-500 mt-1 mono font-mono">{{ $deliverabilityPct }}% rate</div>
                </div>
                @php
                    [$cR, $aR, $vR] = $trendPill($deltaRecipients);
                @endphp
                <div
                    class="stat bg-white border border-paper-200 rounded-[14px] px-[18px] py-4 relative overflow-hidden before:content-[''] before:absolute before:left-0 before:top-0 before:bottom-0 before:w-[3px] s-violet before:bg-[#7B61FF]">
                    <div class="flex items-center justify-between">
                        <div class="stat-label text-[11px] text-ink-600 font-medium flex items-center gap-1.5"><span
                                class="stat-icon w-7 h-7 rounded-lg inline-flex items-center justify-center bg-[rgba(123,97,255,0.18)]"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="#7B61FF"
                                    stroke-width="1.6">
                                    <circle cx="8" cy="6" r="3" />
                                    <path d="M2 14c0-3 3-4 6-4s6 1 6 4" />
                                </svg></span>Recipients</div>
                        <span
                            class="{{ $cR }} px-[7px] py-0.5 rounded-full text-[11px] font-semibold font-mono">{{ $aR }}
                            {{ $vR }}%</span>
                    </div>
                    <div
                        class="stat-value font-serif text-[36px] leading-none tracking-[-0.02em] mt-1.5 tabular tabular-nums">
                        {{ number_format($uniqueRecipients) }}</div>
                    <div class="text-[11px] text-ink-500 mt-1 mono font-mono">{{ __('unique numbers') }}</div>
                </div>
                @php
                    $devsHealthy = $devicesTotalCount > 0 && $devicesOnlineCount === $devicesTotalCount;
                    $devsPill = $devsHealthy
                        ? 'bg-wa-mint text-wa-deep border border-wa-green/40'
                        : ($devicesOnlineCount > 0
                            ? 'bg-accent-amber/15 text-[#7B5A14] border border-accent-amber/40'
                            : 'bg-accent-coral/10 text-[#A1431F] border border-accent-coral/35');
                    $devsPillLabel = $devsHealthy ? 'Healthy' : ($devicesOnlineCount > 0 ? 'Degraded' : 'Offline');
                @endphp
                <div
                    class="stat bg-white border border-paper-200 rounded-[14px] px-[18px] py-4 relative overflow-hidden before:content-[''] before:absolute before:left-0 before:top-0 before:bottom-0 before:w-[3px] s-blue before:bg-[#13478A]">
                    <div class="flex items-center justify-between">
                        <div class="stat-label text-[11px] text-ink-600 font-medium flex items-center gap-1.5"><span
                                class="stat-icon w-7 h-7 rounded-lg inline-flex items-center justify-center bg-[#D9E5F2]"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="#13478A"
                                    stroke-width="1.6">
                                    <rect x="2" y="3" width="12" height="9" rx="1.5" />
                                    <path d="M5 15h6" />
                                </svg></span>Devices</div>
                        <span
                            class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $devsPill }} text-[10px]">{{ $devsPillLabel }}</span>
                    </div>
                    <div
                        class="stat-value font-serif text-[36px] leading-none tracking-[-0.02em] mt-1.5 tabular tabular-nums">
                        {{ $devicesOnlineCount }} <span class="text-[14px] text-ink-500 align-middle">/
                            {{ $devicesTotalCount }}</span></div>
                    <div class="text-[11px] {{ $devsHealthy ? 'text-wa-deep' : 'text-ink-500' }} mt-1 mono font-mono">
                        {{ $devsHealthy ? 'all online' : ($devicesOnlineCount > 0 ? $devicesTotalCount - $devicesOnlineCount . ' offline' : 'no devices') }}
                    </div>
                </div>
                @php
                    [$cQ, $aQ, $vQ] = $trendPill($deltaQueued, true);
                @endphp
                <div
                    class="stat bg-white border border-paper-200 rounded-[14px] px-[18px] py-4 relative overflow-hidden before:content-[''] before:absolute before:left-0 before:top-0 before:bottom-0 before:w-[3px] s-amber before:bg-accent-amber">
                    <div class="flex items-center justify-between">
                        <div class="stat-label text-[11px] text-ink-600 font-medium flex items-center gap-1.5"><span
                                class="stat-icon w-7 h-7 rounded-lg inline-flex items-center justify-center bg-[rgba(229,160,78,0.22)]"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="#7B5A14"
                                    stroke-width="1.6">
                                    <circle cx="8" cy="8" r="5" />
                                    <path d="M8 5v3l2 2" />
                                </svg></span>Queued</div>
                        <span
                            class="{{ $cQ }} px-[7px] py-0.5 rounded-full text-[11px] font-semibold font-mono">{{ $aQ }}
                            {{ $vQ }}%</span>
                    </div>
                    <div
                        class="stat-value font-serif text-[36px] leading-none tracking-[-0.02em] mt-1.5 tabular tabular-nums">
                        {{ number_format($queued) }}</div>
                    <div class="text-[11px] text-ink-500 mt-1 mono font-mono">{{ __('in queue') }}</div>
                </div>
                @php
                    [$cF, $aF, $vF] = $trendPill($deltaFailed, true);
                @endphp
                <div
                    class="stat bg-white border border-paper-200 rounded-[14px] px-[18px] py-4 relative overflow-hidden before:content-[''] before:absolute before:left-0 before:top-0 before:bottom-0 before:w-[3px] s-coral before:bg-accent-coral">
                    <div class="flex items-center justify-between">
                        <div class="stat-label text-[11px] text-ink-600 font-medium flex items-center gap-1.5"><span
                                class="stat-icon w-7 h-7 rounded-lg inline-flex items-center justify-center bg-[rgba(232,122,93,0.18)]"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3" fill="#A1431F">
                                    <path d="M8 1.5l7 12.5H1zM8 6v4M8 12.5h.01" />
                                </svg></span>Failed</div>
                        <span
                            class="{{ $cF }} px-[7px] py-0.5 rounded-full text-[11px] font-semibold font-mono">{{ $aF }}
                            {{ $vF }}%</span>
                    </div>
                    <div
                        class="stat-value font-serif text-[36px] leading-none tracking-[-0.02em] mt-1.5 tabular tabular-nums">
                        {{ number_format($failed) }}</div>
                    <div class="text-[11px] text-ink-500 mt-1 mono font-mono">{{ __('carrier errors') }}</div>
                </div>
                @php
                    $rrUp = $deltaReplyRate >= 0;
                    $rrColor = $rrUp
                        ? 'bg-wa-green/10 text-wa-deep border border-wa-green/30'
                        : 'bg-accent-coral/10 text-[#A1431F] border border-accent-coral/35';
                    $rrArrow = $rrUp ? '↑' : '↓';
                @endphp
                <div
                    class="stat bg-white border border-paper-200 rounded-[14px] px-[18px] py-4 relative overflow-hidden before:content-[''] before:absolute before:left-0 before:top-0 before:bottom-0 before:w-[3px] s-teal before:bg-wa-teal">
                    <div class="flex items-center justify-between">
                        <div class="stat-label text-[11px] text-ink-600 font-medium flex items-center gap-1.5"><span
                                class="stat-icon w-7 h-7 rounded-lg inline-flex items-center justify-center bg-[#DFF1ED]"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.6">
                                    <path d="M3 8a5 5 0 1 1 10 0v3l-2-1h-3a5 5 0 0 1-5-5z" />
                                </svg></span>Reply rate</div>
                        <span
                            class="{{ $rrColor }} px-[7px] py-0.5 rounded-full text-[11px] font-semibold font-mono">{{ $rrArrow }}
                            {{ abs($deltaReplyRate) }}pp</span>
                    </div>
                    <div
                        class="stat-value font-serif text-[36px] leading-none tracking-[-0.02em] mt-1.5 tabular tabular-nums">
                        {{ $replyRatePct }}<span class="text-[14px] text-ink-500">%</span></div>
                    <div class="text-[11px] text-ink-500 mt-1 mono font-mono">{{ __('conversation start') }}</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== ROW: Volume area + Rates donut ========== -->
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">

            <div class="lg:col-span-8 panel bg-white border border-paper-200 rounded-[18px] px-5 py-[18px] shadow-card">
                <div class="flex items-start justify-between mb-3 flex-wrap gap-2">
                    <div>
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Volume trend') }}</div>
                        <h3 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                            {{ __('Messages over time') }}</h3>
                    </div>
                    <div class="flex items-center gap-3 text-[11px] text-ink-500">
                        <span class="flex items-center gap-1.5"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-deep"></span>Sent</span>
                        <span class="flex items-center gap-1.5"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-teal"></span>Delivered</span>
                        <span class="flex items-center gap-1.5"><span
                                class="w-2.5 h-2.5 rounded-full bg-accent-coral"></span>Failed</span>
                    </div>
                </div>
                <div id="chart-volume"></div>
            </div>

            <div class="lg:col-span-4 panel bg-white border border-paper-200 rounded-[18px] px-5 py-[18px] shadow-card">
                <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Quality') }}
                </div>
                <h3 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                    {{ __('Delivery rates') }}</h3>
                <div id="chart-rates" class="mt-2"></div>
            </div>
        </div>
    </section>

    <!-- ========== ROW: Throughput stacked bar + Funnel ========== -->
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">

            <div class="lg:col-span-7 panel bg-white border border-paper-200 rounded-[18px] px-5 py-[18px] shadow-card">
                <div class="flex items-start justify-between mb-3 flex-wrap gap-2">
                    <div>
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Daily totals') }}</div>
                        <h3 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                            {{ __('Sent · queued · failed') }}</h3>
                    </div>
                    <span
                        class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 border border-paper-200 mono font-mono">{{ __('stacked') }}</span>
                </div>
                <div id="chart-totals"></div>
            </div>

            <div class="lg:col-span-5 panel bg-white border border-paper-200 rounded-[18px] px-5 py-[18px] shadow-card">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Conversion') }}</div>
                        <h3 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                            {{ __('Engagement funnel') }}</h3>
                    </div>
                    <span
                        class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 border border-paper-200 mono font-mono">{{ $range }}</span>
                </div>
                <div class="space-y-1.5">
                    @php $stepStyles = ['bg-wa-deep text-paper-0', 'bg-wa-teal/15', 'bg-wa-green/20', 'bg-paper-50']; @endphp
                    @foreach ($funnelSteps as $i => $step)
                        @php
                            $isFirst = $i === 0;
                            $widthPct = max(8.0, min(100.0, (float) $step['pct']));
                            $rowStyle = $stepStyles[$i] ?? 'bg-paper-50';
                            $labelMono = $isFirst ? 'text-paper-0/60' : 'text-ink-500';
                            $valColor = $isFirst ? 'text-paper-0/60' : 'text-ink-500';
                        @endphp
                        @if (!$isFirst)
                            <div class="ml-8 mono font-mono text-[10px] text-ink-500">↓ {{ $step['pct'] }}%</div>
                        @endif
                        <div class="hairline border border-paper-200 rounded-xl p-3 {{ $rowStyle }} flex items-center justify-between gap-3 min-w-[240px] max-w-full"
                            style="width: {{ $widthPct }}%">
                            <div class="min-w-0">
                                <div class="mono font-mono text-[10px] {{ $labelMono }} uppercase tracking-widest">
                                    {{ $step['stage'] }}</div>
                                <div class="text-[12.5px] font-medium mt-0.5">{{ $step['label'] }}</div>
                            </div>
                            <div class="text-right shrink-0">
                                <div
                                    class="serif font-serif font-normal tracking-[-0.01em] text-[20px] tabular tabular-nums leading-none">
                                    {{ number_format($step['count']) }}</div>
                                <div class="text-[10px] {{ $valColor }} mono font-mono">
                                    {{ $isFirst ? '100%' : '↘ ' . number_format($step['drop']) }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div
                    class="mt-3 hairline-t border-t border-paper-200 pt-3 flex items-center justify-between text-[11px] mono font-mono">
                    <span class="text-ink-500">{{ __('end-to-end') }}</span>
                    <span
                        class="serif font-serif font-normal tracking-[-0.01em] text-[20px] tabular tabular-nums text-wa-deep">{{ $funnelEndPct }}%</span>
                    @php
                        $fnUp = $funnelDeltaPp >= 0;
                        $fnColor = $fnUp ? 'bg-wa-bubble text-wa-deep' : 'bg-accent-coral/10 text-[#A1431F]';
                        $fnArrow = $fnUp ? '▲' : '▼';
                    @endphp
                    <span
                        class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $fnColor }}">{{ $fnArrow }}
                        {{ abs($funnelDeltaPp) }}pp vs prev</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== ROW: Devices bar + Types donut + Top templates ========== -->
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">

            <div class="lg:col-span-5 panel bg-white border border-paper-200 rounded-[18px] px-5 py-[18px] shadow-card">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Performance') }}</div>
                        <h3 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                            {{ __('By device') }}</h3>
                    </div>
                    <span
                        class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40">{{ $devicesOnlineCount }}/{{ $devicesTotalCount }}
                        {{ __('online') }}</span>
                </div>
                <div id="chart-devices" data-labels='@json($deviceLabels)'
                    data-values='@json($deviceData)'></div>
            </div>

            <div class="lg:col-span-3 panel bg-white border border-paper-200 rounded-[18px] px-5 py-[18px] shadow-card">
                <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Mix') }}
                </div>
                <h3 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                    {{ __('Message types') }}</h3>
                <div id="chart-types" class="mt-2"></div>
            </div>

            <div class="lg:col-span-4 panel bg-white border border-paper-200 rounded-[18px] px-5 py-[18px] shadow-card">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Top performers') }}</div>
                        <h3 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                            {{ __('Templates') }}</h3>
                    </div>
                    <span
                        class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 mono font-mono">{{ __('top 8') }}</span>
                </div>
                <ul class="space-y-0.5 max-h-[300px] overflow-y-auto pr-1">
                    @forelse ($topTemplates as $i => $t)
                        <li class="flex items-center gap-3 py-2 hairline-b border-b border-paper-200 text-[12.5px]">
                            <span
                                class="w-7 h-7 rounded-full hairline border border-paper-200 bg-paper-0 grid place-items-center font-bold text-[11px] mono font-mono">{{ $i + 1 }}</span>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium truncate">{{ $t['name'] }}</div>
                                <div class="text-[10px] text-ink-500 mono font-mono">
                                    {{ ucfirst((string) ($t['category'] ?? 'template')) }} ·
                                    {{ strtoupper((string) ($t['language'] ?? 'en')) }}</div>
                            </div>
                            <span
                                class="mono font-mono text-[11px] text-ink-500 tabular tabular-nums">{{ number_format($t['sends']) }}</span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-[12px] text-ink-500">
                            {{ __('No template sends in this window.') }}</li>
                    @endforelse
                </ul>
            </div>

        </div>
    </section>

    <!-- ========== ROW: Geo distribution + Live event ticker + Power users ========== -->
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">

            <!-- Geo -->
            <div class="lg:col-span-4 panel bg-white border border-paper-200 rounded-[18px] px-5 py-[18px] shadow-card">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Reach') }}</div>
                        <h3 class="serif font-serif font-normal tracking-[-0.01em] text-[22px] leading-tight">
                            {{ __('By country') }}</h3>
                    </div>
                    <span
                        class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 mono font-mono">{{ count($geoBuckets) }}
                        {{ trans_choice('market|markets', count($geoBuckets)) }}</span>
                </div>
                @php $geoMax = collect($geoBuckets)->max('count') ?: 1; @endphp
                <div class="space-y-1.5">
                    @forelse ($geoBuckets as $g)
                        <div class="flex items-center gap-3 text-[12px]">
                            {{-- One label, not two: this printed $g['code'] twice — once in a
                                 w-8 box sized for a 2-letter code. A real region ("MALAYSIA")
                                 overflowed that box and collided with the copy beside it,
                                 rendering as "MALAYSIAMALAYSIA". min-w-0 + truncate keeps a
                                 long name from pushing the bar and count out of the row. --}}
                            <span class="flex-1 min-w-0 truncate"
                                title="{{ $g['code'] === '—' ? __('Unspecified') : $g['code'] }}">{{ $g['code'] === '—' ? __('Unspecified') : $g['code'] }}</span>
                            <div class="w-32 h-1.5 rounded-full bg-paper-100 overflow-hidden">
                                <div class="h-full bg-wa-deep"
                                    style="width:{{ max(2, (int) round(($g['count'] / $geoMax) * 100)) }}%"></div>
                            </div>
                            <span
                                class="tabular tabular-nums font-medium w-14 text-right">{{ number_format($g['count']) }}</span>
                        </div>
                    @empty
                        <div class="py-6 text-center text-[12px] text-ink-500">{{ __('No device regions yet.') }}
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Live event ticker -->
            <div class="lg:col-span-4 panel bg-white border border-paper-200 rounded-[18px] px-5 py-[18px] shadow-card">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div
                            class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-wa-green animate-pulse"></span>Live</div>
                        <h3 class="serif font-serif font-normal tracking-[-0.01em] text-[22px] leading-tight">
                            {{ __('Event stream') }}</h3>
                    </div>
                    <button class="text-[11px] text-wa-deep font-medium hover:underline">{{ __('Pause') }}</button>
                </div>
                <ol class="relative">
                    <span class="absolute left-[15px] top-2 bottom-2 w-px bg-paper-200"></span>
                    @forelse ($eventStream as $i => $e)
                        <li class="relative pl-10 {{ $loop->last ? '' : 'pb-2.5' }}">
                            <span
                                class="absolute left-1.5 top-1 w-7 h-7 rounded-full bg-wa-green text-paper-0 flex items-center justify-center">
                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="currentColor">
                                    <path d="M2 8l8-3.5-3 8z" />
                                </svg>
                            </span>
                            <div class="text-[12px]"><b>{{ $e['title'] }}</b></div>
                            <div class="text-[10px] text-ink-500 mono font-mono mt-0.5">{{ $e['at'] }} ·
                                {{ $e['meta'] }}</div>
                        </li>
                    @empty
                        <li class="pl-10 py-6 text-[12px] text-ink-500">
                            {{ __('No recent activity in this window.') }}</li>
                    @endforelse
                </ol>
            </div>

            <!-- Power users -->
            <div class="lg:col-span-4 panel bg-white border border-paper-200 rounded-[18px] px-5 py-[18px] shadow-card">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Most engaged') }}</div>
                        <h3 class="serif font-serif font-normal tracking-[-0.01em] text-[22px] leading-tight">
                            {{ __('Top contacts') }}</h3>
                    </div>
                    <a href="{{ route('user.analytics.export', request()->only(['range', 'from', 'to'])) }}"
                        class="text-[11px] text-wa-deep font-medium hover:underline">{{ __('Export') }}</a>
                </div>
                @php
                    $contactGradients = [
                        'from-wa-teal to-wa-deep',
                        'from-accent-coral to-[#A1431F]',
                        'from-accent-amber to-[#7B5A14]',
                        'from-[#7B61FF] to-[#5B3D8A]',
                        'from-[#13478A] to-[#0B1F1C]',
                    ];
                @endphp
                <div class="space-y-1">
                    @forelse ($topContacts as $i => $c)
                        @php
                            $initials = strtoupper(
                                substr(preg_replace('/[^a-z]/i', '', (string) $c['title']) ?: 'CO', 0, 2),
                            );
                            $isLast = $loop->last;
                        @endphp
                        <div
                            class="flex items-center gap-3 py-2 {{ $isLast ? '' : 'hairline-b border-b border-paper-200' }}">
                            <span
                                class="w-9 h-9 rounded-full bg-gradient-to-br {{ $contactGradients[$i % count($contactGradients)] }} text-paper-0 text-[11px] font-semibold flex items-center justify-center">{{ $initials }}</span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[12.5px] font-medium truncate">{{ $c['title'] }}</div>
                                <div class="text-[10px] text-ink-500 mono font-mono">{{ $c['phone'] }} ·
                                    {{ $c['last_at'] ?: '—' }}</div>
                            </div>
                            <div class="text-right">
                                <div
                                    class="serif font-serif font-normal tracking-[-0.01em] text-[15px] tabular tabular-nums">
                                    {{ number_format($c['msgs']) }}</div>
                                <div class="text-[9px] mono font-mono text-ink-500">{{ __('msgs') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-[12px] text-ink-500">
                            {{ __('No conversations in this window.') }}</div>
                    @endforelse
                </div>
            </div>

        </div>
    </section>

    {{-- #6 — Team performance section: per-agent rollup of conversations
 handled + response times + CSAT. Only shows when there's at
 least one assigned conversation in the window. --}}
    @if (!empty($teamPerformance))
        <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-6">
            <div class="panel bg-white border border-paper-200 rounded-[18px] px-5 py-[18px] shadow-card">
                <div class="flex items-start justify-between mb-3 flex-wrap gap-2">
                    <div>
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Team performance') }}</div>
                        <h3 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                            {{ __('Agents in this window') }}</h3>
                        <p class="text-[12px] text-ink-500 mt-0.5">
                            {{ __('Conversations handled, response latency, customer satisfaction. Drawn from real workspace data.') }}
                        </p>
                    </div>
                    <span
                        class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 mono font-mono">{{ count($teamPerformance) }}
                        {{ __('agents') }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead
                            class="text-left mono font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 border-b border-paper-200">
                            <tr>
                                <th class="px-3 py-2">{{ __('Agent') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Convos') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Resolved') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Avg 1st resp.') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Avg resolution') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('CSAT') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-100">
                            @foreach ($teamPerformance as $row)
                                @php
                                    $initials = strtoupper(
                                        substr(preg_replace('/[^a-z]/i', '', (string) $row['name']) ?: 'AG', 0, 2),
                                    );
                                    $fr = $row['avg_first_resp_m'];
                                    $rr = $row['avg_resolve_m'];
                                    $fmtMin = fn($m) => $m === null
                                        ? '—'
                                        : ($m < 60
                                            ? round($m) . 'm'
                                            : floor($m / 60) . 'h ' . (int) round($m) % 60 . 'm');
                                @endphp
                                <tr class="hover:bg-paper-50/60">
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center gap-2.5">
                                            <span
                                                class="w-7 h-7 rounded-full bg-wa-deep text-paper-0 text-[10.5px] font-semibold grid place-items-center shrink-0">{{ $initials }}</span>
                                            <div class="min-w-0">
                                                <div class="font-medium truncate">{{ $row['name'] }}</div>
                                                <div class="text-[10px] text-ink-500 mono font-mono truncate">
                                                    {{ $row['email'] }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">
                                        {{ number_format($row['convos']) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-ink-600">
                                        {{ number_format($row['resolved']) }}</td>
                                    <td class="px-3 py-2.5 text-right mono font-mono">{{ $fmtMin($fr) }}</td>
                                    <td class="px-3 py-2.5 text-right mono font-mono">{{ $fmtMin($rr) }}</td>
                                    <td class="px-3 py-2.5 text-right">
                                        @if ($row['csat'] !== null)
                                            <span
                                                class="tabular-nums font-semibold {{ $row['csat'] >= 4 ? 'text-wa-deep' : ($row['csat'] >= 3 ? 'text-accent-amber' : 'text-accent-coral') }}">{{ number_format($row['csat'], 2) }}</span>
                                            <span
                                                class="text-[9px] text-ink-500 mono font-mono ml-1">({{ $row['csat_count'] }})</span>
                                        @else
                                            <span class="text-ink-500">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif

    <!-- ========== ROW: Heatmap-style hour×weekday ========== -->
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-8">
        <div class="panel bg-white border border-paper-200 rounded-[18px] px-5 py-[18px] shadow-card">
            <div class="flex items-start justify-between mb-3 flex-wrap gap-2">
                <div>
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Best hours') }}</div>
                    <h3 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                        {{ __('When your audience is awake') }}</h3>
                    <p class="text-[12px] text-ink-500 mt-0.5">
                        {{ __('Read rate by hour × day · darker = higher engagement') }}</p>
                </div>
                <span
                    class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 mono font-mono">{{ $heatmapTzLabel ?? 'UTC' }}</span>
            </div>
            <div id="chart-heatmap"></div>
        </div>

        <div class="mt-4 text-[11px] text-ink-500 mono font-mono text-center">
            {{ __('Computed from') }}
            <b class="text-ink-900 tabular tabular-nums">{{ number_format($totalMessages) }}</b>
            {{ __('events') }} · {{ \Illuminate\Support\Carbon::parse($fromDate)->format('M j') }} –
            {{ \Illuminate\Support\Carbon::parse($toDate)->format('M j, Y') }} ·
            {{ __('timezone') }} <span class="text-wa-deep">{{ $heatmapTzLabel ?? 'UTC' }}</span>
        </div>
    </section>

</x-layouts.user>
