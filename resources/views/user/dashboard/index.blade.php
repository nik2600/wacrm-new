<x-layouts.user :title="__('Operator Dashboard')" nav-key="dashboard" page="user-dashboard-index">

    <script>
        window.DASHBOARD_DATA = {
            labels: @json($dailyLabels),
            sent: @json($dailySent),
            delivered: @json($dailyDelivered),
            failed: @json($dailyFailed),
            spark: @json($sparkData),
            readRate: {{ (float) $readRatePct }},
            throughputRanges: @json($throughputRanges),
        };
    </script>

    <!-- ========== TOP BAR ========== -->


    <!-- ========== PAGE HEADER ========== -->
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pt-5 md:pt-7 pb-4">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div class="min-w-0">
                <div
                    class="flex items-center gap-3 mb-2 text-[11px] mono font-mono uppercase tracking-[0.18em] text-ink-500">
                    <span>{{ __('Operator Dashboard') }}</span>
                    <span class="w-1 h-1 rounded-full bg-ink-500/50"></span>
                    <span class="flex items-center gap-1.5"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green animate-pulse"></span>Live · synced 4s ago</span>
                </div>
                <h1
                    class="serif font-serif font-normal tracking-[-0.01em] text-[36px] md:text-[44px] xl:text-[52px] leading-[1.05] tracking-tight">
                    {{ $greeting }}, {{ $userName }}.
                    @if ($broadcastsRunning > 0)
                        <span class="italic text-wa-deep">{{ $broadcastsRunning }}
                            broadcast{{ $broadcastsRunning === 1 ? '' : 's' }}</span> are running.
                    @else
                        <span class="italic text-wa-deep">{{ __('Workspace is calm.') }}</span>
                    @endif
                </h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-xl">{{ $today }} · Last 7 days showing <b
                        class="tabular tabular-nums">{{ number_format($sent24h) }}</b> outbound, <b
                        class="tabular tabular-nums">{{ $deliverabilityPct }}%</b> deliverability across
                    {{ $devicesActive }} connected device{{ $devicesActive === 1 ? '' : 's' }}.</p>
            </div>
            <div class="flex items-center gap-2 mt-2 md:mt-0 flex-wrap">
                {{-- Export — client-side CSV of the daily throughput series
 (sent / delivered / failed) wired in user-dashboard-index.js. --}}
                <button type="button" id="dash-export"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium hover:bg-paper-50 flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.5">
                        <path d="M8 2v8m0 0L5 7m3 3 3-3M3 13h10" />
                    </svg>
                    Export
                </button>
                {{-- Date range — drives the throughput chart range below (same
 datasets as the in-card 24h/7d/30d/QTD toggle). --}}
                <div class="relative" id="dash-range-wrap">
                    <button type="button" id="dash-range-btn"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium hover:bg-paper-50 flex items-center gap-1.5">
                        <span id="dash-range-label">{{ __('Last 7 days') }}</span>
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <path d="M4 6l4 4 4-4" />
                        </svg>
                    </button>
                    <div id="dash-range-menu"
                        class="hidden absolute right-0 mt-1 w-40 z-30 bg-paper-0 border border-paper-200 rounded-xl shadow-soft overflow-hidden py-1">
                        <button type="button"
                            class="dash-range-opt w-full text-left px-3 py-1.5 text-[12px] hover:bg-paper-50"
                            data-range="24h">{{ __('Last 24 hours') }}</button>
                        <button type="button"
                            class="dash-range-opt w-full text-left px-3 py-1.5 text-[12px] hover:bg-paper-50"
                            data-range="7d">{{ __('Last 7 days') }}</button>
                        <button type="button"
                            class="dash-range-opt w-full text-left px-3 py-1.5 text-[12px] hover:bg-paper-50"
                            data-range="30d">{{ __('Last 30 days') }}</button>
                        <button type="button"
                            class="dash-range-opt w-full text-left px-3 py-1.5 text-[12px] hover:bg-paper-50"
                            data-range="qtd">{{ __('Quarter to date') }}</button>
                    </div>
                </div>
                <a href="{{ url('/wa-campaigns/create') }}" data-tour="new-campaign"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-medium hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    New campaign
                </a>
            </div>
        </div>
    </section>

    <!-- ========== STATS ROW ========== -->
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3">
        <div class="grid grid-cols-12 gap-3" data-tour="kpi">
            @php
                $deltaPill = function ($delta, $invertGood = false) {
                    $up = $delta >= 0;
                    $good = $invertGood ? !$up : $up;
                    $css = $good ? 'bg-wa-bubble text-wa-deep' : 'bg-accent-coral/10 text-[#A1431F]';
                    $arrow = $up ? '▲' : '▼';
                    return [$css, $arrow, abs((float) $delta)];
                };
                $kpiPctW = function ($num, $denom, $cap = 100) {
                    if ($denom <= 0) {
                        return 0;
                    }
                    return max(0, min($cap, (int) round(($num / $denom) * 100)));
                };
            @endphp
            @php
                [$sentCss, $sentArrow, $sentVal] = $deltaPill($deltaSent);
            @endphp
            <!-- KPI 1 -->
            <div
                class="col-span-12 md:col-span-6 xl:col-span-3 bg-paper-0 hairline border border-paper-200 rounded-2xl p-5 shadow-card relative overflow-hidden">
                <div
                    class="absolute -right-4 -top-4 w-24 h-24 rounded-full stripe-bg bg-[repeating-linear-gradient(135deg,rgba(7,94,84,0.05)_0_6px,transparent_6px_12px)] opacity-50">
                </div>
                <div class="flex items-start justify-between relative">
                    <span
                        class="mono font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Messages sent · 7d') }}</span>
                    <span
                        class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $sentCss }}">{{ $sentArrow }}
                        {{ $sentVal }}%</span>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span
                        class="serif font-serif font-normal tracking-[-0.01em] text-[40px] md:text-[52px] leading-none tabular tabular-nums">{{ number_format($sent24h) }}</span>
                </div>
                <div class="mt-3 flex items-center gap-3 text-[11px] text-ink-600">
                    <span class="flex items-center gap-1"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ number_format($delivered24h) }}
                        {{ __('delivered') }}</span>
                    <span class="flex items-center gap-1"><span
                            class="w-1.5 h-1.5 rounded-full bg-accent-coral"></span>{{ number_format($failed24h) }}
                        {{ __('failed') }}</span>
                </div>
                <div id="kpi-spark" class="mt-3 -mb-1 w-full h-8"></div>
            </div>

            @php
                $rrUp = $deltaReadRate >= 0;
                $rrCss = $rrUp ? 'bg-paper-100 text-ink-700' : 'bg-accent-coral/10 text-[#A1431F]';
                $rrArr = $rrUp ? '▲' : '▼';
            @endphp
            <!-- KPI 2 -->
            <div
                class="col-span-12 md:col-span-6 xl:col-span-3 bg-paper-0 hairline border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between">
                    <span
                        class="mono font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Read rate') }}</span>
                    <span
                        class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $rrCss }}">{{ $rrArr }}
                        {{ abs($deltaReadRate) }}pp</span>
                </div>
                <div class="mt-2 flex items-end gap-3">
                    <span
                        class="serif font-serif font-normal tracking-[-0.01em] text-[40px] md:text-[52px] leading-none tabular tabular-nums">{{ $readRatePct }}<span
                            class="text-[20px] md:text-[26px] text-ink-500">%</span></span>
                    <div id="kpi-readrate" class="-mb-2 w-16 h-16"></div>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2 text-[10px]">
                    <div class="hairline border border-paper-200 rounded p-2">
                        <div class="text-ink-500 mono font-mono">{{ __('Sent') }}</div>
                        <div class="font-semibold tabular tabular-nums text-[12px]">{{ $sent24h > 0 ? '100%' : '0%' }}
                        </div>
                    </div>
                    <div class="hairline border border-paper-200 rounded p-2">
                        <div class="text-ink-500 mono font-mono">{{ __('Deliv') }}</div>
                        <div class="font-semibold tabular tabular-nums text-[12px]">{{ $deliverabilityPct }}%</div>
                    </div>
                    <div class="hairline border border-paper-200 rounded p-2 bg-wa-bubble border-wa-green/30">
                        <div class="text-wa-deep mono font-mono">{{ __('Read') }}</div>
                        <div class="font-semibold tabular tabular-nums text-[12px] text-wa-deep">{{ $readRatePct }}%
                        </div>
                    </div>
                </div>
            </div>

            @php
                [$cCss, $cArrow, $cVal] = $deltaPill($deltaContacts);
            @endphp
            <!-- KPI 3 -->
            <div
                class="col-span-12 md:col-span-6 xl:col-span-3 bg-paper-0 hairline border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between">
                    <span
                        class="mono font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Active contacts') }}</span>
                    <span
                        class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $cCss }}">{{ $cArrow }}
                        {{ $cVal }}%</span>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span
                        class="serif font-serif font-normal tracking-[-0.01em] text-[40px] md:text-[52px] leading-none tabular tabular-nums">{{ number_format($contactsTotal) }}</span>
                </div>
                <div class="mt-3 space-y-1.5">
                    <div class="flex items-center gap-2 text-[11px]">
                        <span class="w-[68px] shrink-0 whitespace-nowrap text-ink-500">{{ __('Subscribed') }}</span>
                        <div class="flex-1 h-1.5 rounded-full bg-paper-100 overflow-hidden">
                            <div class="h-full bg-wa-green"
                                style="width: {{ $kpiPctW($contactsSubscribed, max($contactsTotal, 1)) }}%"></div>
                        </div>
                        <span
                            class="tabular tabular-nums w-10 text-right font-medium">{{ number_format($contactsSubscribed) }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-[11px]">
                        <span class="w-[68px] shrink-0 whitespace-nowrap text-ink-500">{{ __('Opted-in') }}</span>
                        <div class="flex-1 h-1.5 rounded-full bg-paper-100 overflow-hidden">
                            <div class="h-full bg-wa-teal"
                                style="width: {{ $kpiPctW($contactsOpted, max($contactsTotal, 1)) }}%"></div>
                        </div>
                        <span
                            class="tabular tabular-nums w-10 text-right font-medium">{{ number_format($contactsOpted) }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-[11px]">
                        <span class="w-[68px] shrink-0 whitespace-nowrap text-ink-500">{{ __('Blocked') }}</span>
                        <div class="flex-1 h-1.5 rounded-full bg-paper-100 overflow-hidden">
                            <div class="h-full bg-accent-coral"
                                style="width: {{ $kpiPctW($contactsBlocked, max($contactsTotal, 1)) }}%"></div>
                        </div>
                        <span
                            class="tabular tabular-nums w-10 text-right font-medium">{{ number_format($contactsBlocked) }}</span>
                    </div>
                </div>
            </div>

            <!-- KPI 4 — credit balance. Credits were reverted, so on plan/allowance
                 billing this always reads 0 and only confused users. Show it ONLY when the
                 workspace actually holds top-up credits; hidden otherwise. -->
            @if (($walletCredits ?? 0) > 0)
            <div
                class="col-span-12 md:col-span-6 xl:col-span-3 bg-wa-deep text-paper-0 rounded-2xl p-5 shadow-soft relative overflow-hidden">
                <div
                    class="absolute inset-0 dot-pattern [background-image:radial-gradient(circle_at_1px_1px,rgba(7,94,84,0.18)_1px,transparent_0)] bg-[length:14px_14px] opacity-20">
                </div>
                <div class="relative">
                    <div class="flex items-start justify-between">
                        <span
                            class="mono font-mono text-[10px] uppercase tracking-widest text-paper-0/60">{{ __('Credit balance') }}</span>
                        <span
                            class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-0/15 text-paper-0">{{ $creditsPerMessage ?? 1 }}
                            credit{{ ($creditsPerMessage ?? 1) === 1 ? '' : 's' }} = 1 msg</span>
                    </div>
                    <div class="mt-4 flex items-baseline gap-2">
                        <span
                            class="serif font-serif font-normal tracking-[-0.01em] text-[40px] md:text-[52px] leading-none tabular tabular-nums">{{ number_format($walletCredits ?? 0) }}</span>
                        <span class="text-[12px] text-paper-0/60">{{ __('credits') }}</span>
                    </div>
                    <div class="mt-3 flex items-center justify-between text-[11px] text-paper-0/70">
                        <span>≈ {{ number_format($estMessages ?? 0) }}
                            message{{ ($estMessages ?? 0) === 1 ? '' : 's' }}</span>
                        <a href="{{ url('/account?tab=affiliate') }}"
                            class="hover:underline">{{ __('Earn via affiliate →') }}</a>
                    </div>
                    <div class="mt-3 h-1 rounded-full bg-paper-0/15 overflow-hidden">
                        <div class="h-full bg-wa-green"
                            style="width: {{ ($walletCredits ?? 0) > 0 ? '100' : '0' }}%"></div>
                    </div>
                    <a href="{{ url('/account?tab=wallet') }}"
                        class="mt-3 w-full bg-paper-0 text-wa-deep rounded-full text-[12px] font-medium py-2 hover:bg-paper-50 inline-flex items-center justify-center">{{ __('Top up credits →') }}</a>
                </div>
            </div>
            @else
            {{-- KPI 4 fallback — when there are no top-up credits the balance card is
                 hidden; fill the slot with a useful "automate" card instead of a gap. --}}
            <div
                class="col-span-12 md:col-span-6 xl:col-span-3 bg-wa-deep text-paper-0 rounded-2xl p-5 shadow-soft relative overflow-hidden">
                <div
                    class="absolute inset-0 dot-pattern [background-image:radial-gradient(circle_at_1px_1px,rgba(7,94,84,0.18)_1px,transparent_0)] bg-[length:14px_14px] opacity-20">
                </div>
                <div class="relative flex flex-col h-full">
                    <span class="mono font-mono text-[10px] uppercase tracking-widest text-paper-0/60">{{ __('Get more done') }}</span>
                    <div class="mt-3 serif font-serif font-normal text-[24px] leading-tight">{{ __('Put replies on autopilot') }}</div>
                    <p class="text-[12px] text-paper-0/70 mt-1.5 leading-snug">{{ __('Build a flow or launch a campaign to reach more customers automatically.') }}</p>
                    <div class="mt-auto pt-4 grid grid-cols-2 gap-2">
                        <a href="{{ url('/flows') }}"
                            class="bg-paper-0/15 hover:bg-paper-0/25 text-paper-0 rounded-full text-[12px] font-medium py-2 text-center">{{ __('Build a flow') }}</a>
                        <a href="{{ url('/campaigns') }}"
                            class="bg-paper-0 text-wa-deep rounded-full text-[12px] font-medium py-2 text-center hover:bg-paper-50">{{ __('New campaign') }}</a>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </section>

    {{-- ===== Instagram roll-up — sits directly below the WhatsApp/WaDesk stats
         row (second band on the page). Only when an Instagram account is
         connected; when it isn't, $igStats is null and this whole band is
         skipped, so the dashboard is the WhatsApp view exactly as before. ===== --}}
    @if (!empty($igStats))
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3">
        {{-- Header strip: Instagram label + connected-account chips + Open inbox.
             The metrics below are their OWN KPI tiles in the SAME 12-col grid /
             card chrome as the WhatsApp row, so it reads as a second row of the
             same cards — not a separate wide band. --}}
        <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="mono font-mono text-[10px] uppercase tracking-widest text-ink-500 inline-flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-md text-white shrink-0" style="background:linear-gradient(135deg,#833AB4,#E1306C,#F77737);">
                        <svg viewBox="0 0 24 24" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"/></svg>
                    </span>
                    {{ __('Instagram') }}
                    <span class="text-ink-400 normal-case tracking-normal">· {{ trans_choice('{1}:count account|[2,*]:count accounts', $igStats['accounts_count'], ['count' => $igStats['accounts_count']]) }}</span>
                </span>
                @if (!empty($igStats['accounts']))
                    @foreach ($igStats['accounts'] as $acc)
                    <span class="inline-flex items-center gap-2 rounded-full border border-paper-200 bg-paper-50 pl-1 pr-3 py-1">
                        @if (!empty($acc['avatar']))
                            <img src="{{ $acc['avatar'] }}" alt="" class="w-5 h-5 rounded-full object-cover" onerror="this.style.display='none'">
                        @else
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-white text-[9px] font-bold" style="background:linear-gradient(135deg,#833AB4,#E1306C,#F77737);">{{ strtoupper(substr(ltrim($acc['handle'],'@'),0,1)) }}</span>
                        @endif
                        <span class="text-[11px] font-medium text-ink-800">{{ $acc['handle'] }}</span>
                        @if (($acc['status'] ?? '') && strtolower($acc['status']) !== 'connected')
                            <span class="w-1.5 h-1.5 rounded-full bg-accent-coral" title="{{ ucfirst($acc['status']) }}"></span>
                        @else
                            <span class="w-1.5 h-1.5 rounded-full" style="background:#25D366;" title="{{ __('Connected') }}"></span>
                        @endif
                    </span>
                    @endforeach
                @endif
            </div>
            <a href="{{ url('/team-inbox') }}" class="text-[11px] font-semibold hover:underline shrink-0" style="color:#C13584;">{{ __('Open inbox →') }}</a>
        </div>

        {{-- 5 KPI tiles — identical chrome to the WhatsApp KPI cards above
             (hairline border · rounded-2xl · p-5 · shadow-card · stripe corner ·
             mono label on top · big serif number). Equal 5-up on desktop. --}}
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3">
            @php
                $igTiles = [
                    ['href' => url('/team-inbox'), 'label' => __('Need reply'),   'value' => number_format($igStats['needs_reply'])],
                    ['href' => url('/team-inbox'), 'label' => __('Open chats'),   'value' => number_format($igStats['open'])],
                    ['href' => null,               'label' => __('DMs in · 7d'),  'value' => number_format($igStats['dms_in_7d'])],
                    ['href' => null,               'label' => __('DMs sent · 7d'),'value' => number_format($igStats['dms_out_7d'])],
                    ['href' => null,               'label' => __('Followers'),    'value' => $igStats['followers'] >= 1000 ? number_format($igStats['followers']/1000, 1).'k' : number_format($igStats['followers'])],
                ];
            @endphp
            @foreach ($igTiles as $t)
                @php $igTag = $t['href'] ? 'a' : 'div'; @endphp
                <{{ $igTag }} @if($t['href']) href="{{ $t['href'] }}" @endif
                    class="bg-paper-0 hairline border rounded-2xl p-5 shadow-card relative overflow-hidden block"
                    style="border-color:rgba(225,48,108,0.20);">
                    {{-- Soft Instagram-gradient wash. Opacity is INLINE (not a Tailwind
                         arbitrary class) so it renders correctly without a CSS rebuild
                         and stays light enough to keep the text readable. --}}
                    <div class="absolute inset-0 pointer-events-none" style="background:linear-gradient(135deg,#833AB4,#E1306C,#F77737);opacity:0.09;"></div>
                    <span class="mono font-mono text-[10px] uppercase tracking-widest text-ink-500 relative">{{ $t['label'] }}</span>
                    {{-- Number in the Instagram gradient (purple→magenta→pink→orange). --}}
                    <div class="mt-4 serif font-serif font-normal tracking-[-0.01em] text-[36px] md:text-[44px] leading-none tabular tabular-nums relative"
                        style="background:linear-gradient(120deg,#833AB4,#C13584,#E1306C,#F77737);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent;">{{ $t['value'] }}</div>
                </{{ $igTag }}>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ===== Quick access — user-pinned shortcut tiles (5×2), editable ===== --}}
    @php
        $quickTiles = \App\Support\QuickAccess::forUser(auth()->user());
        $quickCatalog = \App\Support\QuickAccess::catalog();
        $quickSelectedKeys = collect($quickTiles)->pluck('key')->filter()->values()->all();
        $quickCustom = collect($quickTiles)->where('custom', true)->values();
    @endphp
    @php
        // One-line "what it does" copy for the hover card, keyed by tile label.
        $quickDesc = [
            'Unified Inbox' => __('One shared inbox for WhatsApp + Instagram'),
            'Contacts' => __('Your audience list & lightweight CRM'),
            'Campaigns' => __('Send bulk campaigns to many contacts'),
            'Broadcasts' => __('Fire a one-off broadcast blast'),
            'Templates' => __('Manage approved message templates'),
            'Flows' => __('Build automated conversation flows'),
            'Scheduled' => __('Messages queued to send later'),
            'Catalog' => __('Your product catalog'),
            'Store' => __('Your WhatsApp storefront'),
            'Sales Pipeline' => __('Track deals from lead to close'),
            'Analytics' => __('Reports, charts & insights'),
            'Auto-reply' => __('Keyword-triggered auto responses'),
            'Devices' => __('Your connected WhatsApp numbers'),
            'Chat Widgets' => __('Website click-to-chat widget'),
            'AI Assistants' => __('AI agents that reply for you'),
            'Appointments' => __('Bookings & calendar'),
            'Webhooks' => __('Send events to your own systems'),
            'Meta Ads' => __('Click-to-WhatsApp ad campaigns'),
            'WA Links' => __('Shareable click-to-chat links'),
            'AI Training' => __('Teach the AI from your content'),
        ];
    @endphp
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3" data-tour="quick-access">
        <div class="rounded-2xl border border-paper-200 bg-paper-0 p-4 sm:p-5 relative">
            {{-- decorative wash clipped to its OWN layer so it never hides the
                 hover tooltips (the card itself must NOT be overflow-hidden or the
                 popovers get cut off — that was the bug). --}}
            <div class="pointer-events-none absolute inset-0 overflow-hidden rounded-2xl">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-wa-bubble/60 blur-2xl"></div>
            </div>
            <div class="relative flex items-start justify-between gap-3 mb-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">{{ __('Shortcuts') }}</div>
                    <h2 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Quick') }} <span class="italic text-wa-deep">{{ __('access') }}</span></h2>
                    <p class="text-[11.5px] text-ink-500 mt-0.5">{{ __('Your most-used tools — hover to see what each does.') }}</p>
                </div>
                <button type="button" data-qa-open
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:border-wa-deep hover:text-wa-deep text-[11px] font-semibold transition shrink-0">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M11 2.5l2.5 2.5L6 12.5 3 13l.5-3z" /></svg>{{ __('Edit') }}
                </button>
            </div>
            {{-- Compact grid: all 10 shortcuts fit in ONE row on desktop. --}}
            <div class="relative grid grid-cols-3 sm:grid-cols-5 lg:grid-cols-10 gap-2" data-qa-grid>
                @foreach ($quickTiles as $t)
                    @php $__d = $quickDesc[$t['label']] ?? __('Open :x', ['x' => __($t['label'])]); @endphp
                    <a href="{{ $t['url'] }}"
                        class="qa-tile group relative flex flex-col items-center gap-1.5 rounded-xl border border-paper-200 bg-paper-0 px-1.5 py-2.5 text-center hover:border-wa-deep hover:shadow-card hover:-translate-y-0.5 transition">
                        <span class="w-9 h-9 rounded-xl bg-wa-bubble ring-1 ring-wa-green/15 flex items-center justify-center text-wa-deep group-hover:bg-wa-deep group-hover:text-paper-0 group-hover:ring-wa-deep transition">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">{!! $t['icon'] !!}</svg>
                        </span>
                        <span class="text-[10px] font-semibold text-ink-700 leading-tight line-clamp-2">{{ __($t['label']) }}</span>

                        {{-- hover tooltip — opens ABOVE the tile, centered. Uniform for
                             every tile + never clips now that the card isn't overflow-hidden. --}}
                        <span class="qa-pop pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-40 w-44 text-center rounded-xl border border-paper-200 bg-paper-0 shadow-card p-2.5 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition duration-150">
                            <span class="block text-[12px] font-semibold text-ink-900">{{ __($t['label']) }}</span>
                            <span class="block text-[11px] text-ink-500 leading-snug mt-0.5">{{ $__d }}</span>
                        </span>
                    </a>
                @endforeach
                @if (empty($quickTiles))
                    <button type="button" data-qa-open class="col-span-full py-6 text-[12px] text-ink-500 border border-dashed border-paper-300 rounded-2xl hover:border-wa-deep">{{ __('Add quick-access shortcuts →') }}</button>
                @endif
            </div>
        </div>
    </section>

    {{-- The Quick-access editor modal is now global (x-user.quick-access-modal
         in the layout), opened by any [data-qa-open] trigger — including the
         pencil above and the edge-drawer pencil. --}}

    {{-- ===== Sales Pipeline KPIs — only when the plan has the feature ===== --}}
    @if (!empty($dealStats))
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3">
        <div class="rounded-2xl border border-paper-200 bg-paper-0 p-4" data-tour="pipeline">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sales Pipeline') }}</h2>
                <a href="{{ url('/deals') }}" class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('Open board →') }}</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <a href="{{ url('/deals') }}" class="block">
                    <div class="font-serif text-[22px] leading-none text-ink-900">{{ $dealStats['open_value'] }}</div>
                    <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1.5">{{ __('Open pipeline') }}</div>
                </a>
                <a href="{{ url('/deals') }}" class="block">
                    <div class="font-serif text-[22px] leading-none text-ink-900">{{ number_format($dealStats['open_count']) }}</div>
                    <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1.5">{{ __('Open deals') }}</div>
                </a>
                <a href="{{ url('/deals/reports') }}" class="block">
                    <div class="font-serif text-[22px] leading-none text-accent-mint">{{ $dealStats['won_month'] }}</div>
                    <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1.5">{{ __('Won this month') }}</div>
                </a>
                <a href="{{ url('/deals/reports') }}" class="block">
                    <div class="font-serif text-[22px] leading-none text-ink-900">{{ $dealStats['win_rate'] }}%</div>
                    <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1.5">{{ __('Win rate') }}</div>
                </a>
            </div>
        </div>
    </section>
    @endif

    <!-- ========== MAIN GRID ========== -->
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3">
        <div class="grid grid-cols-12 gap-3">

            <!-- ===== COLUMN 1 (8 cols) ===== -->
            <div class="col-span-12 lg:col-span-8 space-y-3">

                <!-- Throughput chart -->
                <div class="bg-paper-0 hairline border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 mb-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                                    {{ __('Message throughput') }}</h2>
                                <span
                                    class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-bubble text-wa-deep mono font-mono">{{ __('UTC+1') }}</span>
                            </div>
                            <p id="throughput-subtitle" class="text-[12px] text-ink-500 mt-0.5">
                                {{ __('Outbound, delivered & failed events — daily, last 7 days.') }}</p>
                        </div>
                        <div
                            class="flex items-center gap-1 hairline border border-paper-200 rounded-full p-1 text-[11px]">
                            <button type="button" data-range="24h"
                                class="px-3 py-1 rounded-full text-ink-600">24h</button>
                            <button type="button" data-range="7d"
                                class="px-3 py-1 rounded-full bg-ink-900 text-paper-0">7d</button>
                            <button type="button" data-range="30d"
                                class="px-3 py-1 rounded-full text-ink-600">30d</button>
                            <button type="button" data-range="qtd"
                                class="px-3 py-1 rounded-full text-ink-600">{{ __('QTD') }}</button>
                        </div>
                    </div>

                    @php
                        $fmt = function ($n) {
                            $n = (int) $n;
                            if ($n >= 1000000) {
                                return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . 'M';
                            }
                            if ($n >= 1000) {
                                return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'k';
                            }
                            return (string) $n;
                        };
                    @endphp
                    <!-- legend -->
                    <div class="flex flex-wrap items-center gap-y-2 gap-x-5 text-[11px] mb-2">
                        <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-wa-deep"></span><span
                                class="text-ink-700">{{ __('Sent') }}</span> <span
                                class="mono font-mono text-ink-500">{{ $fmt($sent24h) }}</span></span>
                        <span class="flex items-center gap-2"><span
                                class="w-3 h-3 rounded-sm bg-wa-green"></span><span
                                class="text-ink-700">{{ __('Delivered') }}</span> <span
                                class="mono font-mono text-ink-500">{{ $fmt($delivered24h) }}</span></span>
                        <span class="flex items-center gap-2"><span
                                class="w-3 h-3 rounded-sm bg-[repeating-linear-gradient(45deg,#E87A5D_0_3px,transparent_3px_6px)]"></span><span
                                class="text-ink-700">{{ __('Failed') }}</span> <span
                                class="mono font-mono text-ink-500">{{ $fmt($failed24h) }}</span></span>
                        <span class="flex-1"></span>
                        @if (($peakHour['sent'] ?? 0) > 0)
                            <span class="text-ink-500">{{ __('Peak hour') }} <b
                                    class="text-ink-900">{{ $peakHour['label'] }}</b> ·
                                {{ number_format((int) $peakHour['sent']) }} {{ __('msg') }}</span>
                        @endif
                    </div>

                    <!-- chart -->
                    <div id="chart-throughput" class="w-full"></div>

                    <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 hairline-t border-t border-paper-200 pt-3">
                        <div>
                            <div class="mono font-mono text-[10px] uppercase tracking-widest text-ink-500">
                                {{ __('Avg / hour') }}</div>
                            <div
                                class="serif font-serif font-normal tracking-[-0.01em] text-[22px] leading-none mt-1 tabular tabular-nums">
                                {{ number_format($avgPerHour) }}</div>
                        </div>
                        <div>
                            <div class="mono font-mono text-[10px] uppercase tracking-widest text-ink-500">
                                {{ __('Replies') }}</div>
                            <div
                                class="serif font-serif font-normal tracking-[-0.01em] text-[22px] leading-none mt-1 tabular tabular-nums">
                                {{ number_format($replies24h) }}</div>
                        </div>
                        <div>
                            <div class="mono font-mono text-[10px] uppercase tracking-widest text-ink-500">
                                {{ __('Reply rate') }}</div>
                            <div
                                class="serif font-serif font-normal tracking-[-0.01em] text-[22px] leading-none mt-1 tabular tabular-nums">
                                {{ $replyRatePct }}<span class="text-[14px] text-ink-500">%</span></div>
                        </div>
                        <div>
                            <div class="mono font-mono text-[10px] uppercase tracking-widest text-ink-500">
                                {{ __('Failed') }}</div>
                            <div
                                class="serif font-serif font-normal tracking-[-0.01em] text-[22px] leading-none mt-1 tabular tabular-nums {{ $failed24h > 0 ? 'text-accent-coral' : '' }}">
                                {{ number_format($failed24h) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Two side-by-side: Active campaigns + Connected devices -->
                <div class="grid grid-cols-12 gap-3">

                    <!-- Active campaigns -->
                    <div
                        class="col-span-12 lg:col-span-7 bg-paper-0 hairline border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                                    {{ __('Active campaigns') }}</h2>
                                <p class="text-[12px] text-ink-500 mt-0.5">{{ $broadcastsRunning }} running ·
                                    {{ $broadcastsScheduled }} scheduled · {{ $broadcastsPaused }}
                                    {{ __('paused') }}</p>
                            </div>
                            <a href="{{ url('/broadcasts') }}"
                                class="text-[12px] text-wa-deep font-medium hover:underline">View all
                                {{ $broadcastsTotal }} →</a>
                        </div>

                        <div class="space-y-2">
                            @forelse ($activeCampaigns as $bc)
                                <div
                                    class="hairline border border-paper-200 rounded-xl p-3 hover:bg-paper-50 transition">
                                    <div class="flex items-center gap-3">
                                        <span class="w-9 h-9 rounded-lg bg-wa-bubble flex items-center justify-center">
                                            <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep"
                                                fill="currentColor">
                                                <path d="M2 4l12-2v12L2 12V4Z" />
                                            </svg>
                                        </span>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="text-[13px] font-medium truncate">{{ $bc['name'] ?: 'Untitled broadcast' }}</span>
                                                <span
                                                    class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $bc['status_css'] }}">
                                                    @if (in_array($bc['status'], ['Sending'], true))
                                                        <span
                                                            class="w-1.5 h-1.5 rounded-full bg-wa-green animate-pulse"></span>
                                                    @endif
                                                    {{ $bc['status'] }}
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-ink-500 mt-0.5 mono font-mono">
                                                {{ $bc['category'] }}{{ $bc['template'] ? ' · ' . $bc['template'] : '' }}
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0 w-28">
                                            <div class="text-[11px] text-ink-500 mono font-mono">{{ __('delivered') }}
                                            </div>
                                            <div class="text-[14px] font-semibold tabular tabular-nums">
                                                {{ number_format($bc['done']) }}<span
                                                    class="text-ink-500 text-[11px]"> /
                                                    {{ number_format($bc['total']) }}</span></div>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="flex-1 h-1.5 rounded-full bg-paper-100 overflow-hidden">
                                            <div class="h-full bg-wa-deep" style="width: {{ $bc['pct'] }}%">
                                            </div>
                                        </div>
                                        <span
                                            class="mono font-mono text-[10px] text-ink-500 tabular tabular-nums">{{ $bc['pct'] }}%</span>
                                    </div>
                                </div>
                            @empty
                                <div class="hairline border border-dashed border-paper-200 rounded-xl p-6 text-center">
                                    <div class="text-[13px] text-ink-700 font-medium">
                                        {{ __('No active broadcasts') }}</div>
                                    <p class="text-[11px] text-ink-500 mt-1">
                                        {{ __('Spin one up to start sending to a contact group.') }}</p>
                                    <a href="{{ url('/broadcasts/create') }}"
                                        class="inline-block mt-3 px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11px] font-medium hover:bg-wa-teal">+
                                        New broadcast</a>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Connected devices -->
                    <div
                        class="col-span-12 lg:col-span-5 bg-paper-0 hairline border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                                    {{ __('Connected devices') }}</h2>
                                <p class="text-[12px] text-ink-500 mt-0.5">{{ $devicesTotal }} of
                                    {{ $deviceSlotCap }} slots used · {{ $devicesActive }} {{ __('online') }}</p>
                            </div>
                            <a href="{{ url('/devices') }}"
                                class="text-[12px] text-wa-deep font-medium hover:underline">+ Connect</a>
                        </div>

                        <div class="space-y-2">
                            @forelse ($devicesList as $i => $dev)
                                @php
                                    $dotColor = $dev['is_online']
                                        ? 'bg-wa-green' .
                                            ($i === 0 ? ' glow-green shadow-[0_0_0_4px_rgba(37,211,102,0.18)]' : '')
                                        : ($dev['status'] === 'connecting'
                                            ? 'bg-accent-amber'
                                            : 'bg-accent-coral');
                                    $delivColor =
                                        $dev['deliv_pct'] >= 95
                                            ? ''
                                            : ($dev['deliv_pct'] >= 85
                                                ? 'text-accent-amber'
                                                : 'text-accent-coral');
                                    $regionLabel = $dev['region'] ?: 'unzoned';
                                @endphp
                                <div
                                    class="hairline border border-paper-200 rounded-xl p-3 {{ $i === 0 ? 'bg-paper-50' : '' }}">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="relative w-10 h-10 rounded-lg {{ $i === 0 ? 'bg-wa-deep' : 'bg-wa-teal' }} text-paper-0 flex items-center justify-center text-[10px] font-bold mono font-mono">WA</span>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[13px] font-medium">{{ $dev['phone'] }}</span>
                                                <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }}"></span>
                                            </div>
                                            <div class="text-[11px] text-ink-500 mono font-mono mt-0.5">
                                                {{ $dev['label'] }} · {{ $regionLabel }} ·
                                                {{ number_format($dev['sent_24h']) }}/24h</div>
                                        </div>
                                        <div class="text-right">
                                            <div
                                                class="text-[14px] font-semibold tabular tabular-nums {{ $delivColor }}">
                                                {{ $dev['deliv_pct'] }}<span
                                                    class="text-[10px] text-ink-500">%</span></div>
                                            <div class="text-[10px] text-ink-500 mono font-mono">{{ __('deliv') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="hairline border border-dashed border-paper-200 rounded-xl p-6 text-center">
                                    <div class="text-[13px] text-ink-700 font-medium">
                                        {{ __('No devices connected') }}</div>
                                    <p class="text-[11px] text-ink-500 mt-1">
                                        {{ __('Pair a WhatsApp number to start sending.') }}</p>
                                    <button type="button" data-connect-device
                                        class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold hover:bg-wa-teal cursor-pointer">
                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10" /></svg>
                                        {{ __('Connect a device') }}
                                    </button>
                                </div>
                            @endforelse
                        </div>

                        @php $slotsLeft = max(0, $deviceSlotCap - $devicesTotal); @endphp
                        @if ($slotsLeft > 0)
                            <div
                                class="hairline border border-paper-200 rounded-xl p-2.5 mt-2 stripe-bg bg-[repeating-linear-gradient(135deg,rgba(7,94,84,0.05)_0_6px,transparent_6px_12px)] flex items-center justify-between">
                                <div>
                                    <div class="text-[12px] font-medium">{{ $slotsLeft }} device
                                        slot{{ $slotsLeft === 1 ? '' : 's' }} {{ __('available') }}</div>
                                    <div class="text-[11px] text-ink-500 mono font-mono">
                                        {{ __('Add') }} {{ \App\Services\WorkspaceEngine::allowedEnginesSentence() }}</div>
                                </div>
                                <a href="{{ url('/devices') }}"
                                    class="px-3 py-1.5 hairline border border-paper-200 rounded-full text-[11px] bg-paper-0 hover:bg-paper-50 font-medium">{{ __('Connect device') }}</a>
                            </div>
                        @endif
                    </div>

                </div>

                <!-- Top templates table -->
                <div class="bg-paper-0 hairline border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                                {{ __('Top performing templates') }}</h2>
                            <p class="text-[12px] text-ink-500 mt-0.5">
                                {{ __('By send volume · last 7 days · Meta-approved only') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-100 text-ink-700 mono font-mono">{{ $templatesCount }}
                                template{{ $templatesCount === 1 ? '' : 's' }}</span>
                            <a href="{{ url('/templates/create') }}"
                                class="px-3 py-1.5 hairline border border-paper-200 rounded-full text-[11px] bg-paper-0 hover:bg-paper-50 font-medium">+
                                New template</a>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-[12px]">
                        <thead>
                            <tr
                                class="text-left mono font-mono text-[10px] uppercase tracking-widest text-ink-500 hairline-b border-b border-paper-200">
                                <th class="py-2 font-normal">{{ __('Template') }}</th>
                                <th class="py-2 font-normal">{{ __('Type') }}</th>
                                <th class="py-2 font-normal">{{ __('Sent') }}</th>
                                <th class="py-2 font-normal">{{ __('Delivered') }}</th>
                                <th class="py-2 font-normal">{{ __('Read') }}</th>
                                <th class="py-2 font-normal">{{ __('CTR') }}</th>
                                <th class="py-2 font-normal">{{ __('Performance') }}</th>
                                <th class="py-2 font-normal text-right">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="text-[12px]">
                            @forelse ($topTemplates as $t)
                                @php
                                    $statusKey = strtolower((string) $t['status']);
                                    $statusPill = match (true) {
                                        in_array($statusKey, ['approved', 'active'], true) => [
                                            'Approved',
                                            'bg-wa-green/15 text-wa-deep',
                                        ],
                                        in_array($statusKey, ['pending', 'submitted', 'in_review'], true) => [
                                            'In review',
                                            'bg-accent-amber/20 text-[#8B5A14]',
                                        ],
                                        in_array($statusKey, ['rejected', 'disabled'], true) => [
                                            'Rejected',
                                            'bg-accent-coral/15 text-accent-coral',
                                        ],
                                        default => [ucfirst($statusKey ?: 'Draft'), 'bg-paper-100 text-ink-700'],
                                    };
                                    $catShort = strtoupper(substr((string) $t['category'], 0, 3)) ?: 'TPL';
                                    $catColor = match ($catShort) {
                                        'MAR', 'UTI' => ['bg-wa-bubble', 'text-wa-deep'],
                                        'AUT' => ['bg-accent-amber/20', 'text-accent-amber'],
                                        default => ['bg-paper-100', 'text-ink-700'],
                                    };
                                @endphp
                                <tr class="hairline-b border-b border-paper-200 hover:bg-paper-50">
                                    <td class="py-2.5">
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="w-8 h-8 rounded-lg {{ $catColor[0] }} {{ $catColor[1] }} flex items-center justify-center mono font-mono text-[10px] font-semibold">{{ $catShort }}</span>
                                            <div>
                                                <div class="font-medium">{{ $t['name'] }}</div>
                                                <div class="text-ink-500 mono font-mono text-[10px]">id:
                                                    tpl_{{ $t['id'] }} ·
                                                    {{ strtoupper((string) $t['language']) ?: '—' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-ink-700">
                                        {{ $t['category'] }}{{ $t['type'] && $t['type'] !== '—' ? ' · ' . $t['type'] : '' }}
                                    </td>
                                    <td class="tabular tabular-nums font-medium">
                                        {{ $t['sends'] > 0 ? number_format($t['sends']) : '—' }}</td>
                                    <td class="tabular tabular-nums">
                                        {{ $t['sends'] > 0 ? number_format($t['delivered']) : '—' }}</td>
                                    <td class="tabular tabular-nums">
                                        {{ $t['sends'] > 0 ? number_format($t['read']) : '—' }}</td>
                                    <td class="tabular tabular-nums font-semibold text-wa-deep">
                                        {{ $t['ctr'] !== null ? $t['ctr'] . '%' : '—' }}</td>
                                    <td>
                                        <div class="flex items-center gap-2 w-32">
                                            <div class="flex-1 h-1.5 rounded-full bg-paper-100 overflow-hidden">
                                                <div class="h-full {{ $t['perf'] >= 80 ? 'bg-wa-green' : ($t['perf'] >= 50 ? 'bg-wa-teal' : 'bg-paper-200') }}"
                                                    style="width: {{ $t['perf'] }}%"></div>
                                            </div>
                                            <span
                                                class="mono font-mono text-[10px] tabular tabular-nums">{{ $t['perf'] > 0 ? $t['perf'] : '—' }}</span>
                                        </div>
                                    </td>
                                    <td class="text-right"><span
                                            class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $statusPill[1] }}">{{ $statusPill[0] }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-6 text-center text-[12px] text-ink-500">
                                        {{ __('No templates yet.') }} <a href="{{ url('/templates/create') }}"
                                            class="text-wa-deep font-medium hover:underline">{{ __('Create your first →') }}</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>

            </div>

            <!-- ===== COLUMN 2 (4 cols) ===== -->
            <div class="col-span-12 lg:col-span-4 flex flex-col gap-3">

                <!-- Live conversation preview -->
                <div class="bg-paper-0 hairline border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 pt-4 pb-2 flex items-start justify-between">
                        <div>
                            <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[22px] leading-tight">
                                {{ __('Live inbox') }}</h2>
                            <p class="text-[11px] text-ink-500 mt-0.5 mono font-mono">
                                {{ $unreadCount }} unread
                                @if ($oldestUnreadAgo)
                                    · oldest {{ $oldestUnreadAgo }}
                                @endif
                            </p>
                        </div>
                        <a href="{{ url('/team-inbox') }}"
                            class="text-[11px] text-wa-deep font-medium hover:underline">{{ __('Open inbox →') }}</a>
                    </div>

                    <div class="hairline-t border-t border-paper-200">
                        <div
                            class="px-5 py-2 mono font-mono text-[10px] uppercase tracking-widest text-ink-500 flex items-center justify-between">
                            <span>{{ __('Recent conversations') }}</span>
                            <span>{{ $unreadCount }} {{ __('unread') }}</span>
                        </div>
                        <div class="divide-y divide-paper-200">
                            @forelse ($liveConvos as $i => $c)
                                @php
                                    $gradients = [
                                        'from-accent-coral to-accent-amber',
                                        'from-wa-teal to-wa-deep',
                                        'from-accent-amber to-accent-coral',
                                    ];
                                    $grad = $gradients[$i % count($gradients)];
                                @endphp
                                <a href="{{ url('/team-inbox') }}"
                                    class="px-5 py-2 flex items-center gap-3 hover:bg-paper-50">
                                    <span
                                        class="w-8 h-8 rounded-full bg-gradient-to-br {{ $grad }} text-paper-0 text-[10px] font-semibold flex items-center justify-center">{{ $c['initials'] }}</span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[12px] font-medium truncate">{{ $c['title'] }}</span>
                                            <span
                                                class="text-[10px] mono font-mono text-ink-500">{{ $c['ago'] }}</span>
                                        </div>
                                        <div class="text-[11px] text-ink-500 truncate">{{ $c['preview'] }}</div>
                                    </div>
                                    @if ($c['unread'] > 0)
                                        <span
                                            class="w-5 h-5 rounded-full bg-wa-green text-paper-0 text-[10px] font-semibold flex items-center justify-center">{{ $c['unread'] }}</span>
                                    @endif
                                </a>
                            @empty
                                <div class="px-5 py-6 text-center text-[12px] text-ink-500">
                                    {{ __('No conversations yet.') }}</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- AI assistant -->
                <div class="bg-ink-900 text-paper-0 rounded-2xl p-5 shadow-soft relative overflow-hidden">
                    <div
                        class="absolute -right-8 -top-8 w-40 h-40 rounded-full bg-[radial-gradient(circle,rgba(37,211,102,0.4)_0%,transparent_60%)]">
                    </div>
                    <div class="relative">
                        <div class="flex items-center gap-2 mb-3">
                            <span
                                class="w-7 h-7 rounded-lg bg-wa-green text-ink-900 flex items-center justify-center text-[10px] font-bold">AI</span>
                            <span
                                class="mono font-mono text-[10px] uppercase tracking-widest text-paper-0/60">{{ brand_name() }}
                                {{ __('Copilot') }}</span>
                            <span
                                class="ml-auto pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-0/15 text-paper-0/80 mono font-mono">{{ __('AI Powered') }}</span>
                        </div>
                        <p class="serif font-serif font-normal tracking-[-0.01em] text-[22px] leading-tight">
                            {{ __('Build a flow from a sentence.') }}</p>
                        <p class="text-[12px] text-paper-0/70 mt-1.5">
                            {{ __('Describe a customer journey and Copilot drafts nodes, branches, and template suggestions.') }}
                        </p>

                        @php $copilotExample = 'When a WooCommerce order is paid, send a thank-you with tracking link. After 24h, ask for a 5-star review and offer a 10% off coupon.'; @endphp
                        <div
                            class="mt-3 hairline border border-paper-200 rounded-xl p-3 bg-paper-0/5 border-paper-0/10">
                            <div class="mono font-mono text-[10px] text-paper-0/50 mb-1">{{ __('try:') }}</div>
                            <div class="text-[12px] leading-snug">"{{ $copilotExample }}"</div>
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <a href="{{ url('/flows/builder') }}?ai_prompt={{ urlencode($copilotExample) }}"
                                class="flex-1 text-center bg-wa-green text-ink-900 rounded-full text-[12px] font-semibold py-2 hover:bg-[#1ec05a]">{{ __('Generate flow ✦') }}</a>
                            <a href="{{ url('/flows/builder') }}?ai=1"
                                class="hairline border border-paper-200 border-paper-0/15 rounded-full text-[12px] py-2 px-3 text-paper-0/80 hover:bg-paper-0/10">{{ __('Examples') }}</a>
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-2 text-[10px] mono font-mono">
                            <div
                                class="hairline border border-paper-200 border-paper-0/10 rounded-lg p-2 bg-paper-0/5">
                                <div class="text-paper-0/50">{{ __('flows') }}</div>
                                <div
                                    class="serif font-serif font-normal tracking-[-0.01em] text-[18px] tabular tabular-nums text-paper-0">
                                    {{ number_format($copilotFlows ?? 0) }}</div>
                            </div>
                            <div
                                class="hairline border border-paper-200 border-paper-0/10 rounded-lg p-2 bg-paper-0/5">
                                <div class="text-paper-0/50">{{ __('active') }}</div>
                                <div
                                    class="serif font-serif font-normal tracking-[-0.01em] text-[18px] tabular tabular-nums text-wa-green">
                                    {{ number_format($copilotActive ?? 0) }}</div>
                            </div>
                            <div
                                class="hairline border border-paper-200 border-paper-0/10 rounded-lg p-2 bg-paper-0/5">
                                <div class="text-paper-0/50">{{ __('subscribers') }}</div>
                                <div
                                    class="serif font-serif font-normal tracking-[-0.01em] text-[18px] tabular tabular-nums text-paper-0">
                                    {{ number_format($copilotSubscribers ?? 0) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Automation flows (replaces the audience-by-country card) -->
                <div
                    class="bg-paper-0 hairline border border-paper-200 rounded-2xl p-6 shadow-card flex-1 flex flex-col">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[22px] leading-tight">
                                {{ __('Automation flows') }}</h2>
                            <p class="text-[11px] text-ink-500 mt-0.5 mono font-mono">
                                {{ number_format($copilotActive ?? 0) }} {{ __('active') }} ·
                                {{ number_format($copilotSubscribers ?? 0) }} {{ __('subscribers') }}</p>
                        </div>
                        <a href="{{ url('/flows') }}"
                            class="text-[11px] font-semibold text-wa-deep hover:underline shrink-0 mt-1">{{ __('Open') }}</a>
                    </div>
                    <div class="flex-1 flex flex-col">
                        @forelse ($recentFlows as $f)
                            <a href="{{ url('/flows') }}"
                                class="flex-1 flex items-center gap-3 text-[13px] border-b border-paper-100 last:border-0 min-h-[52px] -mx-2 px-2 rounded-lg hover:bg-paper-50 transition">
                                <span
                                    class="w-2.5 h-2.5 rounded-full shrink-0 {{ $f['active'] ? 'bg-wa-green' : 'bg-paper-300' }}"></span>
                                <span class="flex-1 min-w-0">
                                    <span class="block truncate font-medium leading-tight">{{ $f['name'] }}</span>
                                    <span
                                        class="block font-mono text-[10px] text-ink-500 mt-0.5 truncate">{{ ucfirst($f['trigger']) }}
                                        {{ __('trigger') }} · {{ $f['steps'] }} {{ __('steps') }} ·
                                        {{ number_format($f['subs']) }} {{ __('subscribers') }}</span>
                                </span>
                                <span
                                    class="font-mono text-[10px] px-2 py-0.5 rounded-full shrink-0 {{ $f['active'] ? 'bg-wa-bubble text-wa-deep' : 'bg-paper-100 text-ink-500' }}">{{ $f['active'] ? __('Active') : __('Paused') }}</span>
                                <span
                                    class="tabular tabular-nums font-semibold w-[58px] text-right shrink-0">{{ number_format($f['subs']) }}
                                    <span
                                        class="text-ink-400 font-normal text-[10px]">{{ __('subs') }}</span></span>
                            </a>
                        @empty
                            <div class="flex-1 grid place-items-center text-center px-4">
                                <div>
                                    <div class="text-[12px] text-ink-500">{{ __('No flows yet.') }}</div>
                                    <a href="{{ url('/flows/builder') }}"
                                        class="text-[12px] font-semibold text-wa-deep hover:underline">{{ __('Build your first flow') }}</a>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ========== BOTTOM ROW ========== -->
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-8">
        <div class="grid grid-cols-12 gap-3">

            <div
                class="col-span-12 lg:col-span-5 bg-paper-0 hairline border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                            {{ __('Engagement funnel') }}</h2>
                        <p class="text-[12px] text-ink-500 mt-0.5">{{ __('Workspace activity · last 7 days') }}</p>
                    </div>
                    <a href="{{ url('/analytics') }}"
                        class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 border border-paper-200 mono font-mono hover:bg-paper-100">{{ __('view all →') }}</a>
                </div>

                @php
                    $stepStyles = ['bg-wa-deep text-paper-0', 'bg-wa-teal/15', 'bg-wa-green/20', 'bg-paper-50'];
                    $barColors = ['bg-paper-0/30', 'bg-wa-deep/30', 'bg-wa-deep/25', 'bg-wa-deep/20'];
                @endphp
                <div class="space-y-1.5">
                    @foreach ($funnelSteps as $i => $step)
                        @php
                            $isFirst = $i === 0;
                            // Internal progress bar — width 100% always so stages
                            // render at equal card width and don't visually collapse
// to 8% when count=0. The bar inside shows the funnel.
$barPct = max(0.0, min(100.0, (float) $step['pct']));
$rowStyle = $stepStyles[$i] ?? 'bg-paper-50';
$barColor = $barColors[$i] ?? 'bg-wa-deep/20';
$labelMono = $isFirst ? 'text-paper-0/60' : 'text-ink-500';
$valColor = $isFirst ? 'text-paper-0/60' : 'text-ink-500';
                        @endphp
                        @if (!$isFirst)
                            <div class="ml-8 mono font-mono text-[10px] text-ink-500">↓ {{ $step['pct'] }}%</div>
                        @endif
                        <div
                            class="hairline border border-paper-200 rounded-xl p-3 {{ $rowStyle }} relative overflow-hidden">
                            <span class="absolute inset-y-0 left-0 {{ $barColor }} transition-all"
                                style="width: {{ $barPct }}%"></span>
                            <div class="relative flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div
                                        class="mono font-mono text-[10px] {{ $labelMono }} uppercase tracking-widest">
                                        {{ $step['stage'] }}</div>
                                    <div class="text-[13px] font-medium mt-0.5 truncate">{{ $step['label'] }}</div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div
                                        class="serif font-serif font-normal tracking-[-0.01em] text-[22px] tabular tabular-nums leading-none">
                                        {{ number_format($step['count']) }}</div>
                                    <div class="text-[10px] {{ $valColor }} mono font-mono">
                                        {{ $isFirst ? '100%' : '↘ ' . number_format($step['drop']) }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div
                    class="mt-3 hairline-t border-t border-paper-200 pt-3 flex items-center justify-between text-[11px] mono font-mono">
                    <span class="text-ink-500">{{ __('end-to-end conversion') }}</span>
                    <span
                        class="serif font-serif font-normal tracking-[-0.01em] text-[20px] tabular tabular-nums text-wa-deep">{{ $funnelEndPct }}%</span>
                    <span
                        class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-bubble text-wa-deep">{{ count($funnelSteps) }}
                        stage{{ count($funnelSteps) === 1 ? '' : 's' }}</span>
                </div>
            </div>

            <div
                class="col-span-12 md:col-span-6 lg:col-span-4 bg-paper-0 hairline border border-paper-200 rounded-2xl p-5 shadow-card flex flex-col">
                <div class="flex items-start justify-between mb-3 shrink-0">
                    <div>
                        <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                            {{ __('Activity log') }}</h2>
                        <p class="text-[12px] text-ink-500 mt-0.5">{{ __('Workspace events · auto-refresh') }}</p>
                    </div>
                    <a href="{{ route('user.activity-log.index') }}"
                       class="text-[11px] text-wa-deep font-medium hover:underline">{{ __('All →') }}</a>
                </div>

                {{-- Events grow to fill the card height; with space-evenly the
 items distribute through the available vertical space so
 short event lists don't leave a big empty area below. --}}
                <ol class="relative flex-1 flex flex-col justify-between min-h-0">
                    @if (count($events))
                        <span class="absolute left-[15px] top-2 bottom-2 w-px bg-paper-200"></span>
                    @endif
                    @forelse ($events as $idx => $ev)
                        @php
                            $initials =
                                strtoupper(
                                    substr(preg_replace('/[^A-Za-z]/', '', (string) ($ev['actor'] ?? 'NA')), 0, 2),
                                ) ?:
                                'NA';
                            $shortSubject = class_basename($ev['subject'] ?? '');
                            $subjectLabel = strtolower(\Illuminate\Support\Str::snake($shortSubject ?: 'event'));
                        @endphp
                        <li class="relative pl-10 min-w-0">
                            <span
                                class="absolute left-1.5 top-1 w-7 h-7 rounded-full bg-paper-100 text-ink-700 flex items-center justify-center text-[10px] font-semibold">{{ $initials }}</span>
                            <div class="text-[12px] truncate"
                                title="{{ $ev['actor'] }} · {{ $ev['action'] }}{{ $shortSubject ? ' — ' . $shortSubject : '' }}">
                                <b>{{ $ev['actor'] }}</b> · <span
                                    class="mono font-mono text-wa-deep">{{ $ev['action'] }}</span>{{ $shortSubject ? ' — ' . $shortSubject : '' }}
                            </div>
                            <div class="text-[10px] text-ink-500 mono font-mono mt-0.5 truncate">{{ $ev['ago'] }}
                                ago{{ $subjectLabel ? ' · ' . $subjectLabel : '' }}</div>
                        </li>
                    @empty
                        <li class="text-[12px] text-ink-500 text-center py-6 m-auto">
                            {{ __('No recent activity logged for this workspace.') }}</li>
                    @endforelse
                </ol>
            </div>

            <div class="col-span-12 md:col-span-6 lg:col-span-3 space-y-3">
                <div class="bg-paper-0 hairline border border-paper-200 rounded-2xl p-5 shadow-card">
                    <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[22px] leading-tight">
                        {{ __('Quick actions') }}</h2>
                    <div class="grid grid-cols-2 gap-2 mt-3">
                        <a href="{{ url('/broadcasts/create') }}"
                            class="block hairline border border-paper-200 rounded-xl p-3 text-left hover:bg-paper-50 group">
                            <span
                                class="w-7 h-7 rounded-lg bg-wa-deep text-paper-0 flex items-center justify-center mb-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                                    <path d="M2 4l12-2v12L2 12V4Z" />
                                </svg>
                            </span>
                            <div class="text-[12px] font-medium">{{ __('New broadcast') }}</div>
                            <div class="text-[10px] text-ink-500">{{ __('to a contact group') }}</div>
                        </a>
                        <a href="{{ url('/flows') }}"
                            class="block hairline border border-paper-200 rounded-xl p-3 text-left hover:bg-paper-50 group">
                            <span
                                class="w-7 h-7 rounded-lg bg-wa-teal text-paper-0 flex items-center justify-center mb-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <circle cx="4" cy="8" r="2" />
                                    <circle cx="12" cy="4" r="2" />
                                    <circle cx="12" cy="12" r="2" />
                                    <path d="M5.5 7l5-2.5M5.5 9l5 2.5" />
                                </svg>
                            </span>
                            <div class="text-[12px] font-medium">{{ __('Build flow') }}</div>
                            <div class="text-[10px] text-ink-500">{{ __('drag & drop') }}</div>
                        </a>
                        <a href="{{ url('/templates/create') }}"
                            class="block hairline border border-paper-200 rounded-xl p-3 text-left hover:bg-paper-50 group">
                            <span
                                class="w-7 h-7 rounded-lg bg-accent-amber text-paper-0 flex items-center justify-center mb-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <rect x="3" y="3" width="10" height="10" rx="1" />
                                    <path d="M3 6h10M6 13V6" />
                                </svg>
                            </span>
                            <div class="text-[12px] font-medium">{{ __('New template') }}</div>
                            <div class="text-[10px] text-ink-500">{{ __('submit to Meta') }}</div>
                        </a>
                        <a href="{{ url('/auto-reply') }}"
                            class="block hairline border border-paper-200 rounded-xl p-3 text-left hover:bg-paper-50 group">
                            <span
                                class="w-7 h-7 rounded-lg bg-accent-coral text-paper-0 flex items-center justify-center mb-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M3 13l3-3 2 2 5-5" />
                                    <path d="M10 5h3v3" />
                                </svg>
                            </span>
                            <div class="text-[12px] font-medium">{{ __('Auto-reply') }}</div>
                            <div class="text-[10px] text-ink-500">{{ __('keyword rule') }}</div>
                        </a>
                    </div>
                </div>

                <div class="bg-paper-0 hairline border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="flex items-center justify-between">
                        <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[22px] leading-tight">
                            {{ __('Integrations') }}</h2>
                        <span
                            class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-100 text-ink-700 mono font-mono">{{ $integrationsConnected }}
                            / {{ $integrationsTotal }}</span>
                    </div>
                    <div class="mt-3 space-y-2">
                        @foreach ($integrations as $intg)
                            <div
                                class="flex items-center gap-3 hairline border border-paper-200 rounded-lg p-2.5 {{ $intg['connected'] ? '' : 'opacity-60' }}">
                                <span
                                    class="w-7 h-7 rounded text-paper-0 flex items-center justify-center text-[9px] font-bold mono font-mono"
                                    style="background-color: {{ $intg['connected'] ? $intg['bg'] : '#E5E2D5' }}; {{ $intg['connected'] ? '' : 'color: #4A4A4A;' }}">{{ $intg['badge'] }}</span>
                                <div class="flex-1">
                                    <div class="text-[12px] font-medium">{{ $intg['name'] }}</div>
                                    <div class="text-[10px] text-ink-500 mono font-mono">{{ $intg['detail'] }}</div>
                                </div>
                                @if ($intg['connected'])
                                    <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>
                                @else
                                    <a href="{{ url('/integrations') }}"
                                        class="text-[10px] text-wa-deep font-medium">{{ __('Connect') }}</a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>
    </section>

</x-layouts.user>
