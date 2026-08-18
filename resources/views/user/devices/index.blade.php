@php
    $devices = $devices ?? collect();
    $counts = $counts ?? ['all' => 0, 'connected' => 0, 'disconnected' => 0, 'needs_pair' => 0, 'failed' => 0];
    $regionCounts = $regionCounts ?? [];
    $totals = $totals ?? ['total' => 0, 'connected' => 0, 'sent_24h' => 0, 'failed_24h' => 0];
    $currentStatus = $currentStatus ?? 'all';
    $currentRegion = $currentRegion ?? 'all';
    $currentSearch = $currentSearch ?? '';

    $statusList = [
        ['key' => 'all', 'label' => 'All devices', 'dot' => null],
        ['key' => 'connected', 'label' => 'Connected', 'dot' => 'bg-wa-green'],
        ['key' => 'disconnected', 'label' => 'Disconnected', 'dot' => 'bg-paper-200'],
        ['key' => 'needs_pair', 'label' => 'Needs re-pair', 'dot' => 'bg-accent-amber'],
        ['key' => 'failed', 'label' => 'Failed', 'dot' => 'bg-accent-coral'],
    ];

    // The aside + help cards + add-device modal are Baileys-only UI
    // (status/region filters, pairing tips, QR pairing flow). When the
    // workspace engine is WABA or Twilio those sections render their
    // own connector partial which spans the whole content area, so we
    // hide the Baileys chrome to avoid mixed metaphors and reclaim the
    // full page width.
    // Multi-engine: render a section per ENABLED engine (not just the default).
    // $activeEngine stays the "default" engine (shown with a badge). The Baileys
    // filter rail / help cards / pair modal stay gated on whether Baileys is on.
    $enabledEngines = $enabledEngines ?? [$activeEngine ?? 'baileys'];
    $hasBaileys = in_array('baileys', $enabledEngines, true);
    $hasWaba    = in_array('waba', $enabledEngines, true);
    $hasTwilio  = in_array('twilio', $enabledEngines, true);
    $multiEngine = count($enabledEngines) > 1;
    $engine = $activeEngine ?? 'baileys';
    $isBaileysView = $hasBaileys;
    $channelStatus = $channelStatus ?? [];

    // Instagram (via the linked Instaflow install). A separate channel type — NOT
    // a WhatsApp send engine — so it lives outside $enabledEngines. $hasInstagram
    // gates the "Add Instagram account" affordances; $instagramAccounts are this
    // workspace's linked mirror rows. When either the workspace runs 2+ WA engines
    // OR Instagram is available, the header "Add device" button opens the channel
    // chooser (so IG has somewhere to be added even on a single-engine workspace).
    $hasInstagram = $hasInstagram ?? false;
    $instagramAccounts = $instagramAccounts ?? collect();
    $instaflowUrl = $instaflowUrl ?? '';
    $hasInstagramRows = $hasInstagram && count($instagramAccounts) > 0;
    $showChooser = $multiEngine || $hasInstagram;

    // Embed mode (?embed=1): this page is iframed inside the GLOBAL "Connect
    // device" popover (x-user.connect-device-sheet). Force the channel chooser
    // to render; the embed CSS below hides the app chrome so only the chooser +
    // connect flow show, and the JS posts a message to the parent on success.
    $embed = request()->boolean('embed');
    if ($embed) { $multiEngine = true; }
@endphp

<x-layouts.user :title="__('My Devices')" nav-key="devices" page="user-devices-index">

    @if ($embed)
        {{-- Embedded inside the global Connect-device popover: strip the app
             chrome so only the channel chooser + connect flow show, auto-open
             the chooser, and tell the parent window when a device connects. --}}
        <style>
            header, main, [data-trial-bar], #plan-paywall, #connect-device-sheet { display: none !important; }
            body { background: transparent !important; }
            /* Keep the channel chooser as the base layer. Clicking a card hides
               it (page JS) and opens that engine's connect modal; without this,
               cancelling that modal would leave a blank iframe (main is hidden in
               embed). ID + !important beats the .hidden class, so the chooser is
               always visible underneath — Cancel returns straight to the cards. */
            #add-device-chooser { display: flex !important; }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var chooser = document.getElementById('add-device-chooser');
                if (chooser) { chooser.classList.remove('hidden'); chooser.classList.add('flex'); }
                document.querySelectorAll('[data-chooser-close]').forEach(function (b) {
                    b.addEventListener('click', function () {
                        try { window.parent.postMessage({ type: 'wadesk:connect-close' }, '*'); } catch (e) {}
                    });
                });
            });
        </script>
    @endif

    @if (session('status'))
        {{-- A connect just succeeded server-side. If this page is shown inside
             the global Connect-device popover (iframe) — e.g. Twilio / WABA
             redirect here after a native POST — tell the parent to close +
             refresh the picker. No-op on the real /devices page (not iframed). --}}
        <script>
            if (window.parent && window.parent !== window) {
                try { window.parent.postMessage({ type: 'wadesk:device-connected' }, '*'); } catch (e) {}
            }
        </script>
    @endif

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7" data-devices-state data-devices-status="{{ $currentStatus }}"
        data-devices-region="{{ $currentRegion }}" data-devices-search="{{ $currentSearch }}"
        data-devices-page="{{ method_exists($devices, 'currentPage') ? $devices->currentPage() : 1 }}"
        data-allowed-providers="{{ implode(',', $providerAllowed) }}">

        @if (session('status'))
            <div
                class="mb-4 bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                {{ session('status') }}</div>
        @endif

        {{-- WABA connect (Embedded Signup / manual) errors. Without this, a
             failed save — e.g. Meta returned no phone number, token exchange
             failed, or the Meta app lacks whatsapp_business_management — was
             SILENTLY swallowed: the FB popup "connected" but no device + no
             reason shown. Surface every validation error so the merchant can
             act on it. --}}
        @if ($errors->any())
            <div class="mb-4 bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-3 text-[12.5px] text-accent-coral">
                <div class="font-semibold mb-1 flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7">
                        <circle cx="8" cy="8" r="6" /><path d="M8 5v3.5M8 11h.01" />
                    </svg>
                    {{ __('WhatsApp connection could not be completed') }}
                </div>
                <ul class="list-disc list-inside space-y-0.5 text-ink-700">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
                <div class="text-[11px] text-ink-500 mt-2">
                    {{ __('Tip: if Meta did not return a phone number, finish your WABA setup in Meta Business Suite (add a payment method + select a verified number), or use "Add WABA account → paste credentials manually".') }}
                </div>
            </div>
        @endif

        <div class="{{ $isBaileysView ? 'grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6 items-start' : 'block' }}">

            @if ($isBaileysView)
                {{-- Filter rail floats with the page like the Meta Ads left panel:
 self-start stops it stretching to row height, sticky keeps it in
 view while the device list scrolls. --}}
                <aside class="space-y-3 self-start lg:sticky lg:top-[84px]">
                    <x-side-tip>
                        Pair more than one number so a banned device or flat battery doesn't stall your queue.
                        {{ brand_name() }} balances sends
                        across every active device on the workspace.
                    </x-side-tip>

                    <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                            {{ __('Device status') }}</div>
                        @foreach ($statusList as $s)
                            @php $active = $currentStatus === $s['key']; @endphp
                            <button data-devices-filter="status" data-devices-value="{{ $s['key'] }}" type="button"
                                class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $active ? 'bg-wa-deep text-paper-0 font-semibold' : 'text-ink-700 hover:bg-paper-50' }}">
                                <span class="flex items-center gap-2">
                                    @if ($s['dot'])
                                        <span class="w-2 h-2 rounded-full {{ $s['dot'] }}"></span>
                                    @endif
                                    {{ $s['label'] }}
                                </span>
                                <span data-status-count="{{ $s['key'] }}"
                                    class="font-mono text-[11px] {{ $active ? 'opacity-90' : 'text-ink-500' }}">{{ $counts[$s['key']] ?? 0 }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                            {{ __('Region') }}</div>
                        @php $allRegion = $currentRegion === 'all'; @endphp
                        <button data-devices-filter="region" data-devices-value="all" type="button"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $allRegion ? 'bg-paper-50 text-ink-900 font-medium' : 'text-ink-700 hover:bg-paper-50' }}">
                            <span>{{ __('All regions') }}</span>
                            <span class="font-mono text-[11px] text-ink-500">{{ $counts['all'] }}</span>
                        </button>
                        @foreach ($regionCounts as $region => $count)
                            @if (!$region)
                                @continue
                            @endif
                            @php $active = $currentRegion === $region; @endphp
                            <button data-devices-filter="region" data-devices-value="{{ $region }}"
                                type="button"
                                class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $active ? 'bg-paper-50 text-ink-900 font-medium' : 'text-ink-700 hover:bg-paper-50' }}">
                                <span>{{ $region }}</span>
                                <span class="font-mono text-[11px] text-ink-500">{{ $count }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div
                        class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                        <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-wa-green"></span>Pairing tip
                        </div>
                        {{ __('Use a dedicated phone — linked devices stay online when the paired phone is off, but the phone has to log in once every 14 days.') }}
                    </div>
                </aside>
            @endif

            <section class="space-y-5" data-devices-root
                data-device-used="{{ (int) ($totals['total'] ?? 0) }}"
                data-device-limit="{{ (isset($deviceLimit) && (int) $deviceLimit > 0) ? (int) $deviceLimit : 0 }}">
                @php
                    // Each engine renders its own section now — independent, not
                    // mutually exclusive. (Kept as locals for the header + buttons.)
                    $isWaba = $hasWaba;
                    $isTwilio = $hasTwilio;
                @endphp

                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            {{ $multiEngine ? 'Workspace · Channels' : ($isWaba ? 'Workspace · WABA accounts' : ($isTwilio ? 'Workspace · Twilio account' : 'Workspace · ' . (auth()->user()?->currentWorkspace?->name ?: 'Workspace'))) }}
                        </div>
                        <h1 class="font-serif font-normal tracking-tight text-[32px] sm:text-[38px] lg:text-[44px] leading-none">
                            @if ($multiEngine)
                                My <span class="italic text-wa-deep">{{ __('channels') }}</span>
                            @elseif ($isWaba)
                                My <span class="italic text-wa-deep">{{ __('WABA accounts') }}</span>
                            @else
                                My <span class="italic text-wa-deep">{{ __('devices') }}</span>
                            @endif
                        </h1>
                        <p class="text-[13px] text-ink-600 mt-2">
                            @if ($multiEngine)
                                {{ __('Connect and manage every WhatsApp channel for this workspace — Unofficial API, Meta (WABA), and Twilio — side by side.') }}
                            @elseif ($isWaba)
                                Connect WhatsApp Business Accounts from Meta Business Suite. Multiple numbers per
                                workspace are supported.
                            @else
                                Pair, monitor, and rotate WhatsApp numbers across regions and agents.
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap shrink-0">
                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono">
                            <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>
                            <span data-totals="connected">{{ $totals['connected'] }}</span> connected
                        </span>
                        <button id="devices-check-btn" type="button"
                            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10" />
                                <path d="M13 3v3h-3M3 13v-3h3" />
                            </svg>
                            {{ __('Check status') }}
                        </button>
                        {{-- Multi-engine OR Instagram available: one "Add device" button
 opens the channel chooser MODAL (cards → each opens that channel's connect
 flow). Single-engine with no extra channel: the original per-engine buttons. --}}
                        @if ($showChooser)
                            <button type="button" data-open-add-chooser
                                class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M8 3v10M3 8h10" />
                                </svg>
                                {{ __('Add device') }}
                            </button>
                        @else
                            @if ($hasWaba)
                                <button data-waba-connect="{{ $embeddedSignupReady ? 'embedded' : 'manual' }}"
                                    type="button"
                                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M8 3v10M3 8h10" />
                                    </svg>
                                    {{ $embeddedSignupReady ? __('Continue with Facebook') : __('Add WABA account') }}
                                </button>
                            @endif
                            @if ($hasBaileys)
                                <button id="devices-add-btn" type="button"
                                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M8 3v10M3 8h10" />
                                    </svg>
                                    {{ __('Add device') }}
                                </button>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="flex items-center justify-between"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Total devices') }}</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2"><span class="font-serif text-[30px] leading-none"
                                data-totals="total">{{ $totals['total'] }}</span><span
                                class="text-[11px] text-ink-500"><span
                                    data-totals="connected">{{ $totals['connected'] }}</span> active</span></div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="flex items-center justify-between"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sent (24h)') }}</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2"><span class="font-serif text-[30px] leading-none"
                                data-totals="sent_24h">{{ number_format($totals['sent_24h']) }}</span><span
                                class="text-[11px] text-ink-500">{{ __('across all') }}</span></div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="flex items-center justify-between"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Failed (24h)') }}</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2"><span
                                class="font-serif text-[30px] leading-none {{ $totals['failed_24h'] > 0 ? 'text-accent-coral' : '' }}"
                                data-totals="failed_24h">{{ number_format($totals['failed_24h']) }}</span></div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="flex items-center justify-between"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Health') }}</span><span
                                class="text-[10px] text-wa-deep font-mono">{{ $totals['total'] ? round(($totals['connected'] / max($totals['total'], 1)) * 100) : 0 }}%</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2"><span
                                class="font-serif text-[30px] leading-none">{{ $totals['connected'] === $totals['total'] && $totals['total'] > 0 ? 'healthy' : 'attention' }}</span>
                        </div>
                    </div>
                </div>

                @php
                    // Per-section label (engine NAME only — no "default" badge,
                    // which confused operators). Shown above each panel when the
                    // workspace runs more than one engine.
                    $engLabel = function ($eng, $name) use ($multiEngine) {
                        if (!$multiEngine) return '';
                        return '<div class="flex items-center gap-2 pt-1"><span class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">' . e($name) . '</span></div>';
                    };
                @endphp

                @unless ($multiEngine)
                @if ($hasWaba)
                    {{-- Meta (WABA) — one card per connected WABA number. --}}
                    {!! $engLabel('waba', __('Meta (WABA)')) !!}
                    @include('user.devices._waba_section')
                @endif

                @if ($hasTwilio)
                    {{-- Twilio — account card once connected. The connect form lives
 in a modal opened from "Add device" → Twilio (see below). --}}
                    {!! $engLabel('twilio', __('Twilio')) !!}
                    @include('user.devices._twilio_section')
                @endif
                @endunless

                @if ($hasBaileys || $multiEngine || $hasInstagramRows)
                    {{-- The device table is the ONE table. In multi-engine it doubles
 as the "Connected channels" table: Baileys devices render via
 _table, and the connected WABA + Twilio accounts are appended as
 rows below (same columns). Renders in multi-engine even WITHOUT
 Baileys — otherwise a WABA+Twilio-only workspace loses the whole
 table and its connected channels never show. --}}
                    @if ($multiEngine)
                        <div class="flex items-center gap-2 pt-1"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Connected channels') }}</span>
                        </div>
                    @else
                        {!! $engLabel('baileys', __('Unofficial API')) !!}
                    @endif
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden"
                        data-list-grid data-list-grid-key="devices">
                        {{-- Top bar: quick status tabs on the left, search on the right --}}
                        <div
                            class="px-4 py-3 border-b border-paper-200 flex items-center justify-between gap-4 flex-wrap">
                            <div class="flex items-center gap-1 bg-paper-50 rounded-full p-1">
                                <button data-devices-filter="status" data-devices-value="all" type="button"
                                    class="status-tab px-3 py-1.5 rounded-full text-[12px] font-semibold {{ $currentStatus === 'all' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100' }}">
                                    All <span class="ml-1 font-mono text-[10px] opacity-80"
                                        data-status-count="all">{{ $counts['all'] ?? 0 }}</span>
                                </button>
                                <button data-devices-filter="status" data-devices-value="connected" type="button"
                                    class="status-tab px-3 py-1.5 rounded-full text-[12px] font-semibold {{ $currentStatus === 'connected' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100' }}">
                                    Connected <span class="ml-1 font-mono text-[10px] opacity-80"
                                        data-status-count="connected">{{ $counts['connected'] ?? 0 }}</span>
                                </button>
                                <button data-devices-filter="status" data-devices-value="disconnected" type="button"
                                    class="status-tab px-3 py-1.5 rounded-full text-[12px] font-semibold {{ $currentStatus === 'disconnected' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100' }}">
                                    Disconnected <span class="ml-1 font-mono text-[10px] opacity-80"
                                        data-status-count="disconnected">{{ $counts['disconnected'] ?? 0 }}</span>
                                </button>
                                @if (($counts['needs_pair'] ?? 0) > 0)
                                    <button data-devices-filter="status" data-devices-value="needs_pair"
                                        type="button"
                                        class="status-tab px-3 py-1.5 rounded-full text-[12px] font-semibold {{ $currentStatus === 'needs_pair' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100' }}">
                                        Needs re-pair <span class="ml-1 font-mono text-[10px] opacity-80"
                                            data-status-count="needs_pair">{{ $counts['needs_pair'] }}</span>
                                    </button>
                                @endif
                                @if (($counts['failed'] ?? 0) > 0)
                                    <button data-devices-filter="status" data-devices-value="failed" type="button"
                                        class="status-tab px-3 py-1.5 rounded-full text-[12px] font-semibold {{ $currentStatus === 'failed' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100' }}">
                                        Failed <span class="ml-1 font-mono text-[10px] opacity-80"
                                            data-status-count="failed">{{ $counts['failed'] }}</span>
                                    </button>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 w-full sm:w-auto">
                                <div class="relative flex-1 sm:flex-none">
                                    <svg viewBox="0 0 16 16"
                                        class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                                        fill="none" stroke="currentColor" stroke-width="1.5">
                                        <circle cx="7" cy="7" r="5" />
                                        <path d="m11 11 3 3" />
                                    </svg>
                                    <input id="devices-search" type="search" value="{{ $currentSearch }}"
                                        placeholder="{{ __('Search by name, number, or user…') }}"
                                        class="hairline border border-paper-200 rounded-lg pl-9 pr-3 py-2 text-[12.5px] bg-white w-full sm:w-72 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </div>
                                <x-list-grid-toggle />
                            </div>
                        </div>
                        <div data-list-grid-list class="overflow-x-auto">
                            {{-- Column header strip — matches the row grid template below --}}
                            <div class="px-4 py-2.5 min-w-[900px] hidden md:grid grid-cols-[40px_1.4fr_150px_140px_120px_90px_140px_220px] items-center gap-3 border-b border-paper-200 bg-paper-50 font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500"
                                data-list-grid-ignore>
                                <div><input type="checkbox" data-device-select-all
                                        class="rounded border-paper-200 text-wa-deep focus:ring-wa-deep"></div>
                                <div>{{ __('Device') }}</div>
                                <div>{{ __('Mobile number') }}</div>
                                <div>{{ __('User') }}</div>
                                <div>{{ __('Last active') }}</div>
                                <div>{{ __('Sent 24h') }}</div>
                                <div>{{ __('Status') }}</div>
                                <div class="text-right pr-2">{{ __('Actions') }}</div>
                            </div>
                            <div id="devices-list" class="transition-opacity" data-list-grid-source
                                {{-- Leading EMPTY label = the 40px checkbox column. Labels pair
                                     with cells positionally, and the row has 8 cells; without the
                                     empty slot every label shifted one cell left in card view
                                     ("User" over the phone, "Actions" over the status badge).
                                     list-grid-toggle.js drops an empty-labelled checkbox cell by
                                     design — it expects this slot to be here. --}}
                                data-list-grid-labels=",Device,Mobile,User,Last active,Sent 24h,Status,Actions">
                                {{-- Render the Baileys device rows (with their empty-state) only when
                                     there are Baileys devices, OR in single-engine mode where the
                                     "No devices" empty-state is the right prompt. In multi-engine with
                                     0 Baileys devices, the connected WABA/Twilio rows below carry the
                                     table — so we skip the empty Baileys "No data found". --}}
                                @if ($hasBaileys && ($devices->count() > 0 || !$multiEngine))
                                    @include('user.devices._table', ['devices' => $devices, 'channelTag' => $multiEngine, 'hideEmpty' => $multiEngine])
                                @endif
                            @include('user.devices._channel_rows')
                            {{-- Closes #devices-list. It used to close ABOVE the WABA/Twilio
                                 loop, which left those rows as SIBLINGS of the grid source
                                 rather than children — and list-grid-toggle.js reads only
                                 source.children. So card view cloned the Baileys rows alone
                                 ("1 of 1") while list view still showed all three, since the
                                 rows render identically either way. --}}
                            </div>
                        </div>
                        <div class="hidden p-4" data-list-grid-grid></div>
                        {{-- Footer: counts + plan limit (matches mockup) --}}
                        <div
                            class="px-4 py-3 border-t border-paper-200 flex items-center justify-between text-[12px] text-ink-500">
                            @php
                                // In multi-engine the table also appends the connected WABA/Twilio
                                // rows (not paginated), so "Showing X of Y" must count them too —
                                // else a WABA-only workspace reads "Showing 0 of 0" under 1 visible row.
                                $extraChannels = ($multiEngine && isset($connectedChannels))
                                    ? $connectedChannels->where('engine', '!=', 'baileys')->count()
                                    : 0;
                                // Instagram mirror rows are appended below too (independent of engine).
                                $extraChannels += count($instagramAccounts ?? []);
                                $shownCount = $devices->count() + $extraChannels;
                                $totalCount = (method_exists($devices, 'total') ? $devices->total() : ($counts['all'] ?? 0)) + $extraChannels;
                            @endphp
                            <div>{{ __('Showing') }} <span class="font-mono text-ink-900"
                                    data-devices-shown>{{ $shownCount }}</span> of <span
                                    class="font-mono text-ink-900"
                                    data-devices-total>{{ number_format($totalCount) }}</span>
                            </div>
                            <div class="font-mono text-[10.5px]">{{ __('Plan limit:') }} <span
                                    class="text-ink-900">{{ $totals['total'] ?? 0 }} / {{ (isset($deviceLimit) && (int) $deviceLimit > 0) ? (int) $deviceLimit : '∞' }} {{ __('numbers') }}</span></div>
                        </div>
                    </div>
                    <div id="devices-pagination">
                        @include('user.partials.pagination', [
                            'paginator' => $devices,
                            'dataAttr' => 'data-devices-page',
                            'label' => 'devices',
                        ])
                    </div>
                @endif {{-- /hasBaileys — Baileys device-table block ends here --}}

                @if ($isBaileysView)
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                            <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                                {{ __('Help - 01') }}</div>
                            <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                                {{ __('What is a device?') }}</div>
                            <p class="text-[12.5px] text-ink-600 leading-relaxed">
                                {{ __('A paired WhatsApp number that sends campaigns, broadcasts, flows, and replies through your workspace.') }}
                            </p>
                        </div>
                        <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                            <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                                {{ __('Help - 02') }}</div>
                            <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                                {{ __('How do I keep it online?') }}</div>
                            <p class="text-[12.5px] text-ink-600 leading-relaxed">
                                {{ __('Keep the phone logged into WhatsApp, avoid battery restrictions, and check status before scheduled sends.') }}
                            </p>
                        </div>
                        <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                            <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                                {{ __('Help - 03') }}</div>
                            <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                                {{ __('When should I re-pair?') }}</div>
                            <p class="text-[12.5px] text-ink-600 leading-relaxed">
                                {{ __('Re-pair when a device is disconnected, failed, or has not synced recently before you start another send.') }}
                            </p>
                        </div>
                    </div>
                @endif

                {{-- Footer "Showing X of Y" is now rendered INSIDE the table card --}}
            </section>
        </div>
    </main>

    {{-- Add-device chooser modal (multi-engine, or when Instagram is available).
 The header "Add device" button opens this; each card then opens that
 channel's own connect modal. --}}
    @if ($showChooser)
        <div id="add-device-chooser"
            class="hidden fixed inset-0 z-50 items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]">
            <div
                class="w-full max-w-xl bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Add device') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight">{{ __('Pick a channel to connect') }}</h2>
                    </div>
                    <button type="button" data-chooser-close
                        class="w-8 h-8 grid place-items-center rounded-full hover:bg-paper-50 text-ink-500">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 4l8 8M12 4l-8 8" />
                        </svg>
                    </button>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @if ($hasBaileys)
                        <button id="devices-add-btn" data-add-card type="button"
                            class="text-left rounded-2xl border border-paper-200 bg-paper-0 p-4 hover:border-wa-deep hover:shadow-card transition">
                            <span class="w-9 h-9 rounded-xl bg-wa-mint grid place-items-center text-wa-deep">
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M2.6 11.2 2 14l2.9-.6A6 6 0 1 0 2.6 11.2Z" />
                                </svg>
                            </span>
                            <div class="font-serif text-[16px] mt-3 leading-tight">{{ __('Unofficial API') }}</div>
                            <div class="text-[11.5px] text-ink-600 mt-0.5">{{ __('Scan a QR with your phone') }}</div>
                        </button>
                    @endif
                    @if ($hasWaba)
                        <button data-waba-connect="{{ $embeddedSignupReady ? 'embedded' : 'manual' }}" data-add-card
                            type="button"
                            class="text-left rounded-2xl border border-paper-200 bg-paper-0 p-4 hover:border-wa-deep hover:shadow-card transition">
                            <span class="w-9 h-9 rounded-xl bg-wa-mint grid place-items-center text-wa-deep">
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M3 7l5-3 5 3-5 3-5-3zm0 4l5 3 5-3" />
                                </svg>
                            </span>
                            <div class="font-serif text-[16px] mt-3 leading-tight">{{ __('Meta (WABA)') }}</div>
                            <div class="text-[11.5px] text-ink-600 mt-0.5">{{ __('Business API number') }}</div>
                        </button>
                    @endif
                    @if ($hasTwilio)
                        <button data-twilio-connect data-add-card type="button"
                            class="text-left rounded-2xl border border-paper-200 bg-paper-0 p-4 hover:border-wa-deep hover:shadow-card transition">
                            <span class="w-9 h-9 rounded-xl bg-wa-mint grid place-items-center text-wa-deep">
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <circle cx="8" cy="8" r="6" />
                                    <path d="M8 5v3l2 2" />
                                </svg>
                            </span>
                            <div class="font-serif text-[16px] mt-3 leading-tight">{{ __('Twilio') }}</div>
                            <div class="text-[11.5px] text-ink-600 mt-0.5">{{ __('Twilio WhatsApp sender') }}</div>
                        </button>
                    @endif
                    @if ($hasInstagram)
                        @php $instagramNative = (bool) \App\Models\SystemSetting::get('instagram_enabled', false); @endphp
                        {{-- Native add-on → go straight to the local OAuth connect.
                             Remote Instaflow → keep the existing "link account" popup. --}}
                        <button type="button"
                            @if ($instagramNative)
                                onclick="window.location.href='{{ url('/instagram/connect') }}'"
                            @else
                                data-instagram-connect data-add-card
                            @endif
                            class="text-left rounded-2xl border border-paper-200 bg-paper-0 p-4 hover:border-wa-deep hover:shadow-card transition">
                            <span class="w-9 h-9 rounded-xl bg-wa-mint grid place-items-center text-wa-deep">
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.4">
                                    <rect x="2.2" y="2.2" width="11.6" height="11.6" rx="3.4" />
                                    <circle cx="8" cy="8" r="2.9" />
                                    <circle cx="11.3" cy="4.7" r="0.7" fill="currentColor" stroke="none" />
                                </svg>
                            </span>
                            <div class="font-serif text-[16px] mt-3 leading-tight">{{ __('Instagram') }}</div>
                            <div class="text-[11.5px] text-ink-600 mt-0.5">{{ __('Connect a Business/Creator account') }}</div>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Instagram connect modal — "link an account already on Instaflow" (the
 primary path) plus "connect a new account" (popup OAuth). Opened from the
 chooser card or a channel row's Manage button (both data-instagram-connect). --}}
    @if ($hasInstagram)
        <div id="instagram-connect-modal"
            class="hidden fixed inset-0 z-50 items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]">
            <div
                class="w-full max-w-lg max-h-[92vh] overflow-y-auto bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)]">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between sticky top-0 bg-paper-0">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Instagram') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight">{{ __('Add an Instagram account') }}</h2>
                    </div>
                    <button type="button" data-ig-modal-close
                        class="w-8 h-8 grid place-items-center rounded-full hover:bg-paper-50 text-ink-500">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 4l8 8M12 4l-8 8" />
                        </svg>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    {{-- Plain-language account model so the connection isn't
                         confusing: one account, connected once via Instaflow,
                         surfaced in WaDesk. --}}
                    <div class="text-[11.5px] text-ink-600 bg-paper-50 border border-paper-200 rounded-lg px-3 py-2.5 leading-relaxed">
                        {{ __("Your Instagram connects once through :igbrand (it holds the login + webhook). WaDesk links to it and shows its inbox, flows and ads here — it's always one account, not two.", ['igbrand' => ig_brand_name()]) }}
                    </div>

                    {{-- ONE smart button: links instantly when the account is
                         already on Instaflow (matched by your email), otherwise
                         opens the sign-in popup. Several accounts → a picker. --}}
                    <button type="button" data-ig-smart-connect
                        class="w-full px-4 py-3 rounded-xl bg-wa-deep text-paper-0 text-[13px] font-semibold hover:bg-wa-teal inline-flex items-center justify-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="2.2" y="2.2" width="11.6" height="11.6" rx="3.4" /><circle cx="8" cy="8" r="2.9" /><circle cx="11.4" cy="4.6" r=".8" fill="currentColor" stroke="none" />
                        </svg>
                        {{ __('Connect Instagram') }}
                    </button>
                    <p class="text-[11px] text-ink-500 text-center -mt-1.5">
                        {{ __('Links instantly if you already connected it on :igbrand — otherwise a quick sign-in popup opens.', ['igbrand' => ig_brand_name()]) }}</p>

                    {{-- ManyChat-style embedded signup — Facebook-Login-for-Business
                         JS SDK popup with the business-asset picker (portfolio +
                         Instagram account). Shown only when the platform admin set
                         a Login config ID and login type = Facebook. --}}
                    @php
                        $igFbAppId   = (string) (\App\Models\SystemSetting::get('instagram_app_id', '') ?: \App\Models\SystemSetting::get('waba_app_id', ''));
                        $igConfigId  = (string) \App\Models\SystemSetting::get('instagram_config_id', '');
                        $igGraphV    = (string) \App\Models\SystemSetting::get('instagram_graph_version', 'v25.0');
                        $igLoginType = (string) \App\Models\SystemSetting::get('instagram_login_type', 'facebook');
                        $igEmbeddable = $igLoginType === 'facebook' && $igFbAppId !== '' && $igConfigId !== '';
                    @endphp
                    @if ($igEmbeddable)
                        <div class="relative flex items-center gap-2 py-1">
                            <span class="flex-1 h-px bg-paper-200"></span>
                            <span class="text-[10px] font-mono uppercase tracking-[0.16em] text-ink-400">{{ __('or') }}</span>
                            <span class="flex-1 h-px bg-paper-200"></span>
                        </div>
                        <div id="ig-fb-embed"
                            data-app-id="{{ $igFbAppId }}"
                            data-config-id="{{ $igConfigId }}"
                            data-graph-version="{{ $igGraphV }}"
                            data-endpoint="{{ url('/instagram/connect/embedded') }}"></div>
                        <button type="button" data-ig-embedded-connect
                            class="w-full px-4 py-3 rounded-xl border border-[#1877F2] text-[#1877F2] text-[13px] font-semibold hover:bg-[#1877F2]/5 inline-flex items-center justify-center gap-2">
                            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
                            {{ __('Connect via Meta') }}
                        </button>
                        <p class="text-[11px] text-ink-500 text-center -mt-1.5">
                            {{ __('Opens the Meta business login — pick your business portfolio + Instagram account, like ManyChat.') }}</p>
                        <div data-ig-embed-status class="hidden mt-1 rounded-lg border px-3 py-2 text-[12px] font-mono"></div>
                    @endif

                    {{-- Revealed only when you have MORE than one account on
                         Instaflow, so you can choose which to link. --}}
                    <div data-ig-picker class="hidden pt-3 border-t border-paper-200 space-y-2">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Pick an account to link') }}</div>
                        <div data-ig-available class="space-y-2"></div>
                        <button type="button" data-ig-connect-new
                            class="text-[11.5px] text-wa-deep font-semibold hover:underline">
                            + {{ __('Connect a different account') }}</button>
                    </div>

                    {{-- Internal state placeholders (kept for the JS handles). --}}
                    <div data-ig-loading class="hidden"></div>
                    <div data-ig-empty class="hidden"></div>
                </div>
            </div>
        </div>
    @endif

    {{-- Twilio connect modal (multi-engine only). Opened from the chooser card
 or the Twilio section's "Connect Twilio" button (both data-twilio-connect). --}}
    @if ($hasTwilio && $multiEngine)
        <div id="twilio-connect-modal"
            class="hidden fixed inset-0 z-50 items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]">
            <div
                class="w-full max-w-2xl max-h-[92vh] overflow-y-auto bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)]">
                <div
                    class="px-5 py-4 border-b border-paper-200 flex items-center justify-between sticky top-0 bg-paper-0">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Twilio · WhatsApp') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight">{{ __('Connect your Twilio account') }}</h2>
                    </div>
                    <button type="button" data-twilio-modal-close
                        class="w-8 h-8 grid place-items-center rounded-full hover:bg-paper-50 text-ink-500">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 4l8 8M12 4l-8 8" />
                        </svg>
                    </button>
                </div>
                <div class="p-5 md:p-7">
                    @include('user.devices._twilio_form')
                </div>
            </div>
        </div>
    @endif

    {{-- WABA connect modals for multi-engine (single-engine gets them via
 _waba_section, which isn't rendered in the unified-table view). --}}
    @if ($hasWaba && $multiEngine)
        @include('user.devices._waba_modals', [
            'embeddedSignupReady' => $embeddedSignupReady ?? false,
            'embeddedSignupConfigId' => $embeddedSignupConfigId ?? '',
            'wabaAppId' => $wabaAppId ?? '',
        ])
    @endif

    {{-- Add-device modal — same modal pattern the contacts page uses. --}}
    {{-- Add-device modal. If admin enabled multiple providers, the modal
 shows tabs (Baileys / WABA / Twilio) and each tab renders its own
 setup form. If only one provider is enabled, only that tab's
 contents render — no tabs visible, just the form. --}}
    {{-- Add-device modal — matches the legacy 2-column design.
 Left: device details (name + mobile + assign-to + activate toggle).
 Right: QR placeholder (real QR appears after Save → row's
 "Connect" button — admin-set Node URL is used automatically;
 the user never enters it). --}}
    @if ($isBaileysView)
        <div id="device-modal"
            class="hidden fixed inset-0 z-50 items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]">
            <form id="device-form" method="POST" action="{{ url('/devices') }}"
                class="w-full max-w-3xl max-h-[92vh] overflow-hidden flex flex-col bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)]">
                @csrf
                <div class="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-3">
                    <div>
                        <div class="font-mono text-[10px] tracking-[0.16em] text-ink-500 italic">
                            {{ __('new device') }}</div>
                        <h3 class="font-serif text-[22px] leading-tight">{{ __('Pair a WhatsApp number') }}</h3>
                    </div>
                    <button id="device-modal-close" type="button"
                        class="w-8 h-8 rounded-full border border-paper-200 bg-white hover:bg-paper-50 grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M4 4l8 8M12 4l-8 8" />
                        </svg></button>
                </div>

                <div class="overflow-y-auto p-5">
                    <div class="grid md:grid-cols-2 gap-6">
                        {{-- LEFT: device details --}}
                        <div class="space-y-4">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">1. Device
                                details</div>

                            <label class="block">
                                <span
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Device name') }}
                                    <span class="text-accent-coral">*</span></span>
                                <input name="device_name" required minlength="2" maxlength="191"
                                    class="w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="{{ __('e.g. Sales line') }}" />
                            </label>

                            <label class="block">
                                <span
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Mobile number') }}
                                    <span class="text-accent-coral">*</span></span>
                                <div class="wa-iti-wrap">
                                    <input name="phone_number" required minlength="5" type="tel"
                                        class="w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                        placeholder="{{ __('Your number without country code') }}" />
                                </div>
                                <input type="hidden" name="country_code" value="{{ app_default_country()['code'] }}" />
                            </label>

                            <label class="block">
                                <span
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Assign to') }}</span>
                                <select name="assigned_user_id"
                                    class="w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="{{ auth()->id() }}">{{ auth()->user()->name ?? 'You' }} (you)
                                    </option>
                                    @foreach ($workspaceMembers ?? collect() as $member)
                                        @if ($member->id !== auth()->id())
                                            <option value="{{ $member->id }}">
                                                {{ $member->name }}{{ $member->email ? ' · ' . $member->email : '' }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            </label>

                            <div
                                class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl border border-paper-200">
                                <div>
                                    <div class="text-[12.5px] font-semibold">{{ __('Activate after pairing') }}</div>
                                    <div class="text-[11px] text-ink-500 mt-0.5 leading-snug">
                                        {{ __('Routes new sends to this device immediately.') }}</div>
                                </div>
                                <input type="hidden" name="activate_after_pairing" value="0" />
                                <input type="checkbox" name="activate_after_pairing" value="1" checked
                                    class="w-4 h-4 accent-wa-deep" />
                            </div>
                        </div>

                        {{-- RIGHT: QR placeholder (real QR is on the connect-modal after save) --}}
                        <div class="space-y-4">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">2. Scan QR
                            </div>

                            <div
                                class="bg-paper-50 border border-paper-200 rounded-2xl p-6 flex flex-col items-center justify-center min-h-[260px]">
                                <div
                                    class="w-32 h-32 rounded-2xl border border-dashed border-paper-300 grid place-items-center text-paper-300">
                                    <svg viewBox="0 0 16 16" class="w-12 h-12" fill="none" stroke="currentColor"
                                        stroke-width="1.2">
                                        <path d="M3 3h4v4H3zM9 3h4v4H9zM3 9h4v4H3zM9 9h2v2H9zM13 9v2M9 13h2" />
                                    </svg>
                                </div>
                                <div class="font-serif text-[16px] mt-4 text-ink-900">
                                    {{ __('Connect to see the QR') }}</div>
                                <div class="text-[11px] text-ink-500 mt-1 font-mono">
                                    {{ __('QR appears once you click Connect') }}</div>
                            </div>

                            <ol class="list-decimal pl-5 space-y-1 text-[11.5px] text-ink-700 leading-relaxed">
                                <li>{{ __('Open WhatsApp on your phone.') }}</li>
                                <li>{{ __('Tap') }} <strong>{{ __('Settings') }}</strong> →
                                    <strong>{{ __('Linked devices') }}</strong>.</li>
                                <li>{{ __('Tap') }} <strong>{{ __('Link a device') }}</strong> and scan this QR.
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div
                    class="px-5 py-3 border-t border-paper-200 flex items-center justify-between gap-2 bg-paper-50/60">
                    <a href="{{ url('/guidebook') }}"
                        class="text-[11.5px] text-wa-deep font-semibold hover:underline">{{ __('Pairing troubleshooting →') }}</a>
                    <div class="flex items-center gap-2">
                        <button id="device-cancel" type="button"
                            class="px-4 py-2 rounded-full border border-paper-200 bg-white text-[12px] font-semibold hover:border-wa-deep">{{ __('Cancel') }}</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal inline-flex items-center gap-2">
                            {{ __('Connect & show QR') }}
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M3 8h10M9 4l4 4-4 4" />
                            </svg>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{--
 Connect-device modal — ports the QR / pairing-code flow from the
 old project's deviceadd.js without changing the existing add-
 device modal above. The mode-picker step shows two big tiles
 (Scan QR / Use pairing code); after the user picks one, the
 matching panel renders with a polling progress bar that drives
 itself from /devices/{id}/connection-status.
--}}
        <div id="connect-device-modal"
            class="hidden fixed inset-0 z-50 items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]">
            <div
                class="w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)]">

                <div class="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Connect device') }}</div>
                        <h3 id="connect-device-title" class="font-serif text-[22px] leading-tight truncate">—</h3>
                    </div>
                    <button id="connect-device-close" type="button"
                        class="w-8 h-8 rounded-full border border-paper-200 bg-white hover:bg-paper-50 grid place-items-center"
                        title="{{ __('Close') }}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M4 4l8 8M12 4l-8 8" />
                        </svg>
                    </button>
                </div>

                {{-- Step 1: choose connection mode --}}
                <div id="connect-mode-pick" class="p-5 grid grid-cols-2 gap-3">
                    <button data-connect-mode="qr" type="button"
                        class="rounded-2xl border border-paper-200 bg-paper-0 hover:border-wa-deep hover:bg-wa-bubble/40 px-4 py-6 text-center transition">
                        <div class="w-12 h-12 mx-auto rounded-2xl bg-wa-deep text-paper-0 grid place-items-center">
                            <svg viewBox="0 0 16 16" class="w-6 h-6" fill="none" stroke="currentColor"
                                stroke-width="1.5">
                                <rect x="2" y="2" width="5" height="5" />
                                <rect x="9" y="2" width="5" height="5" />
                                <rect x="2" y="9" width="5" height="5" />
                                <path d="M9 9h2v2H9zM12 9h2M9 12v2M12 14h2M14 12h-2" />
                            </svg>
                        </div>
                        <div class="mt-3 font-serif text-[18px]">{{ __('Scan QR code') }}</div>
                        <p class="mt-1 text-[11.5px] text-ink-500">{{ __('Open WhatsApp → Linked devices.') }}</p>
                    </button>
                    <button data-connect-mode="code" type="button"
                        class="rounded-2xl border border-paper-200 bg-paper-0 hover:border-wa-deep hover:bg-wa-bubble/40 px-4 py-6 text-center transition">
                        <div class="w-12 h-12 mx-auto rounded-2xl bg-wa-teal text-paper-0 grid place-items-center">
                            <svg viewBox="0 0 16 16" class="w-6 h-6" fill="none" stroke="currentColor"
                                stroke-width="1.5">
                                <path d="M5 8h6M2 8h1M13 8h1M8 5v6" />
                                <circle cx="8" cy="8" r="6" />
                            </svg>
                        </div>
                        <div class="mt-3 font-serif text-[18px]">{{ __('Use pairing code') }}</div>
                        <p class="mt-1 text-[11.5px] text-ink-500">{{ __('Enter the 8-digit code on the phone.') }}
                        </p>
                    </button>
                </div>

                {{-- Step 2 (QR mode): show the QR image + status progress --}}
                <div id="connect-qr-panel" class="hidden p-5">
                    <div class="rounded-2xl border border-paper-200 bg-paper-50 p-4 flex items-center gap-4">
                        <div
                            class="w-44 h-44 rounded-xl bg-white border border-paper-200 grid place-items-center overflow-hidden shrink-0">
                            <img id="connect-qr-img" alt="{{ __('QR code') }}"
                                class="w-full h-full object-contain">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-serif text-[18px]">{{ __('Scan with WhatsApp') }}</div>
                            <ol class="mt-2 text-[12px] text-ink-600 space-y-1 list-decimal pl-4">
                                <li>{{ __('Open WhatsApp on the phone you want to link.') }}</li>
                                <li>{{ __('Tap') }} <b>Settings → Linked devices → Link a device</b>.</li>
                                <li>{{ __('Point the phone at this QR code.') }}</li>
                            </ol>
                            <div id="connect-qr-error" class="hidden mt-2 text-[11.5px] text-accent-coral"></div>
                        </div>
                    </div>
                </div>

                {{-- Step 2 (pairing code mode): show the 8-digit code --}}
                <div id="connect-code-panel" class="hidden p-5">
                    <div class="rounded-2xl border border-wa-green/30 bg-wa-bubble/40 p-5 text-center">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Pairing code') }}</div>
                        <div id="connect-code" class="mt-2 font-serif text-[32px] sm:text-[44px] tracking-[0.18em] text-wa-deep">— —
                            — —</div>
                        <p class="mt-2 text-[12px] text-ink-600">{{ __('Open WhatsApp →') }} <b>Linked devices → Link
                                with phone number</b>, then enter this code.</p>
                        <div id="connect-code-error" class="hidden mt-2 text-[11.5px] text-accent-coral"></div>
                    </div>
                </div>

                {{-- Progress strip — same for both modes --}}
                <div id="connect-progress" class="hidden px-5 pb-5">
                    <div class="rounded-xl border border-paper-200 bg-paper-0 p-3">
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <div id="connect-status-label" class="text-[12px] font-semibold text-ink-700">
                                {{ __('Waiting for scan…') }}</div>
                            <div id="connect-status-pct" class="font-mono text-[11px] text-ink-500">0%</div>
                        </div>
                        <div class="h-1.5 bg-paper-100 rounded-full overflow-hidden">
                            <div id="connect-status-bar" class="h-full bg-wa-deep transition-all" style="width:0%">
                            </div>
                        </div>
                        <div id="connect-status-steps"
                            class="mt-3 grid grid-cols-3 gap-2 text-[11px] font-mono text-ink-500">
                            <div data-connect-step="generated" class="rounded-md border border-paper-200 px-2 py-1.5">
                                1 · Code generated</div>
                            <div data-connect-step="scanned" class="rounded-md border border-paper-200 px-2 py-1.5">2
                                · Scanned</div>
                            <div data-connect-step="ready" class="rounded-md border border-paper-200 px-2 py-1.5">3 ·
                                Ready</div>
                        </div>
                    </div>
                </div>

                <div
                    class="px-5 py-3 border-t border-paper-200 flex justify-between items-center gap-2 bg-paper-50/60">
                    <button id="connect-back" type="button"
                        class="hidden px-4 py-2 rounded-full border border-paper-200 bg-white text-[12px] font-semibold hover:border-wa-deep">{{ __('Back') }}</button>
                    <div class="flex-1"></div>
                    <button id="connect-cancel" type="button"
                        class="px-4 py-2 rounded-full border border-paper-200 bg-white text-[12px] font-semibold hover:border-wa-deep">{{ __('Cancel') }}</button>
                </div>
            </div>
        </div>
    @endif {{-- /isBaileysView — modals are Baileys-only --}}

</x-layouts.user>
