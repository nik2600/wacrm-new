<x-layouts.user :title="__('More')" nav-key="more" page="user-more-index">

    <main class="max-w-none mx-auto px-4 sm:px-7 py-7">

        <!-- Page header -->
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6 mb-6">
            <div>
                <div class="font-serif text-[22px] leading-tight text-wa-deep italic">{{ __('workspace') }}</div>
                <h1 class="font-serif text-[26px] sm:text-[30px] lg:text-[34px] leading-tight tracking-[-0.02em]">{{ __('More features') }}</h1>
                <p class="mt-1 text-[13px] text-ink-500 max-w-[640px]">
                    {{ __("Tools that don't need to live in the main header — automations, history, integrations, and the bits that keep your workspace humming in the background.") }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div
                    class="flex items-center gap-2 rounded-full border border-paper-200 bg-paper-0 px-3 py-2 text-[12px] text-ink-500">
                    <span class="w-2 h-2 rounded-full bg-wa-green"></span>
                    Sorted by usage
                </div>
                <a href="{{ route('user.developers') }}"
    class="inline-flex items-center gap-2 rounded-full bg-wa-deep text-paper-0 px-4 py-2 text-[12px] font-semibold hover:bg-wa-teal">
    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
        stroke-width="1.7">
        <path d="M6 4L2.5 8 6 12M10 4l3.5 4L10 12" />
    </svg>
    {{ __('Developers / API') }}
</a>
            </div>
        </div>

        <!-- Stats strip -->
        @php
            $s = $stats ?? [];
            $deviceHealthCls = match ($s['deviceHealth'] ?? 'healthy') {
                'healthy' => 'text-wa-deep',
                'partial' => 'text-accent-amber',
                'offline' => 'text-accent-coral',
                default => 'text-ink-500',
            };
        @endphp
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Active rules') }}</span>
                    <span
                        class="text-[10px] text-wa-deep font-mono">{{ ($s['activeRulesNew'] ?? 0) > 0 ? '+' . $s['activeRulesNew'] . ' this week' : 'no new' }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none">{{ number_format($s['activeRules'] ?? 0) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('across all tools') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Queued') }}</span>
                    <span class="text-[10px] text-accent-amber font-mono">{{ $s['nextScheduled'] ?? '—' }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span
                        class="font-serif text-[30px] leading-none">{{ number_format($s['scheduledQueued'] ?? 0) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('scheduled sends') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Connected') }}</span>
                    <span
                        class="text-[10px] {{ $deviceHealthCls }} font-mono">{{ $s['deviceHealth'] ?? 'healthy' }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none">{{ $s['deviceActive'] ?? 0 }} /
                        {{ $s['deviceTotal'] ?? 0 }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('devices live') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Notifications') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono">{{ __('unread') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span
                        class="font-serif text-[30px] leading-none">{{ number_format($s['notifActive'] ?? 0) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('awaiting attention') }}</span>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-6">
            <div>
                @php
                    // Menu-layout awareness. An admin can move the primary header
                    // pages (Dashboard / Campaigns / Flows / Templates / Meta Ads)
                    // down to "More" via /admin/settings/menu-order. Those pages
                    // have NO curated card in the grid below, so a demoted one
                    // would drop out of the header AND be unreachable here — the
                    // "moved a menu to More but it doesn't show in the cards" bug.
                    // Read the saved bar and surface a card for each demoted
                    // primary page so nothing is ever lost. Keys/labels/icons
                    // mirror the header catalogue (components/user/header.blade.php).
                    $__navCfg  = json_decode((string) \App\Models\SystemSetting::get('user_nav_order', '[]'), true);
                    $__barKeys = (is_array($__navCfg) && isset($__navCfg['bar']) && is_array($__navCfg['bar']))
                        ? array_values(array_filter($__navCfg['bar'], 'is_string'))
                        : ['dashboard', 'team-inbox', 'wa-campaigns', 'flows', 'templates', 'devices', 'more'];
                    $__primaryNav = [
                        'dashboard'    => ['label' => __('Dashboard'), 'href' => url('/dashboard'),   'desc' => __('Home & overview'),       'sw' => 1.6, 'icon' => '<rect x="2" y="2" width="5" height="6" rx="1"/><rect x="9" y="2" width="5" height="3" rx="1"/><rect x="9" y="7" width="5" height="7" rx="1"/><rect x="2" y="10" width="5" height="4" rx="1"/>'],
                        'wa-campaigns' => ['label' => __('Campaigns'), 'href' => url('/wa-campaigns'), 'desc' => __('Bulk campaign sends'),   'sw' => 1.5, 'icon' => '<path d="M3 4.5A2.5 2.5 0 0 1 5.5 2h5A2.5 2.5 0 0 1 13 4.5v4A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 2 8.5v-4Z"/><path d="M5.5 6.5h5M5.5 8.5h3"/>'],
                        'flows'        => ['label' => __('Flows'),     'href' => url('/flows'),        'desc' => __('Automation builder'),    'sw' => 1.5, 'icon' => '<circle cx="3.5" cy="8" r="1.8"/><circle cx="12.5" cy="3.5" r="1.8"/><circle cx="12.5" cy="12.5" r="1.8"/><path d="M5 7l6-3M5 9l6 3"/>'],
                        'templates'    => ['label' => __('Templates'), 'href' => url('/templates'),    'desc' => __('Message templates'),     'sw' => 1.5, 'icon' => '<rect x="2.5" y="2.5" width="11" height="11" rx="1.5"/><path d="M2.5 6h11M6 13.5V6"/>'],
                        // Meta Ads is NOT surfaced through the demoted "Navigation"
                        // strip — it has a permanent card in the "All tools" grid
                        // below instead, so it reads like every other tool.
                    ];
                    $__demotedNav = array_filter($__primaryNav, fn ($k) => !in_array($k, $__barKeys, true), ARRAY_FILTER_USE_KEY);
                @endphp
                @if (!empty($__demotedNav))
                    <div class="mb-5">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <h2 class="font-serif text-[20px] leading-tight">{{ __('Navigation') }}</h2>
                            <span class="text-[11px] font-mono text-ink-500">{{ __('moved off the header') }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach ($__demotedNav as $__k => $__n)
                                <a href="{{ $__n['href'] }}"
                                    class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                                    <span class="w-11 h-11 rounded-xl bg-wa-mint text-wa-deep grid place-items-center">
                                        <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                            stroke-width="{{ $__n['sw'] }}">{!! $__n['icon'] !!}</svg>
                                    </span>
                                    <div class="mt-3">
                                        <div class="font-semibold text-[13.5px] text-ink-900">{{ $__n['label'] }}</div>
                                        <div class="text-[11.5px] text-ink-500 mt-0.5">{{ $__n['desc'] }}</div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Section heading -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h2 class="font-serif text-[20px] leading-tight">{{ __('All tools') }}</h2>
                    <div class="flex items-center gap-1 text-[11px] font-mono text-ink-500">
                        <button type="button" data-more-tab="all"
                            class="px-2.5 py-1 rounded-full bg-wa-deep text-paper-0 transition">{{ __('All') }}</button>
                        <button type="button" data-more-tab="automation"
                            class="px-2.5 py-1 rounded-full hover:bg-paper-100 transition">{{ __('Automation') }}</button>
                        <button type="button" data-more-tab="data"
                            class="px-2.5 py-1 rounded-full hover:bg-paper-100 transition">{{ __('Data') }}</button>
                        <button type="button" data-more-tab="help"
                            class="px-2.5 py-1 rounded-full hover:bg-paper-100 transition">{{ __('Help') }}</button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" data-more-tools>
                    {{-- Unified Inbox moved to the main header nav — its hero card
                         used to live here, removed to avoid duplicating the entry. --}}

                    {{-- Meta Ads — moved off the header, now a first-class tool
                         card here (auto-tabs into Automation via its /meta-ads
                         href in user-more-index.js). --}}
                    <a href="{{ url('/meta-ads') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#E7F0FF] text-[#1877F2] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M2 5.5 12.5 2.5v11L2 10.5V5.5Z" /><path d="M2 5.5v5M12.5 6.5h1.5v3h-1.5" />
                                </svg>
                            </span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#E7F0FF] text-[#1877F2] text-[10px] font-mono">ADS</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Meta Ads') }}</h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Create and manage Click-to-WhatsApp ad campaigns on Facebook & Instagram — every click opens a chat on your connected number.') }}
                        </p>
                        <div class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ __('click-to-chat') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open Meta Ads') }}</span>
                        </div>
                    </a>

                    {{-- Instagram Posts — compose + schedule to a connected Instagram
                         account. Shown only when Instagram is set up (native add-on
                         OR a connected remote Instaflow). Route is plan-gated
                         (instagram_posts); the paywall handles plans without it. --}}
                    @php
                        $igAvailable = \App\Services\Instaflow\InstaflowClient::fromSettings()->isConnected()
                            || (bool) \App\Models\SystemSetting::get('instagram_enabled', false);
                    @endphp
                    @if ($igAvailable)
                        <a href="{{ url('/instagram/posts') }}"
                            class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <span class="w-11 h-11 rounded-xl grid place-items-center text-white"
                                    style="background:linear-gradient(135deg,#833AB4,#E1306C,#F77737)">
                                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.9">
                                        <rect x="3" y="3" width="18" height="18" rx="5" /><circle cx="12" cy="12" r="4" /><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />
                                    </svg>
                                </span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono"
                                    style="background:#FCE7F3;color:#9D174D">IG</span>
                            </div>
                            <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Instagram Posts') }}</h2>
                            <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                                {{ __('Compose and schedule Instagram posts — images, reels, stories and carousels. Publish now or queue them to go out automatically.') }}
                            </p>
                            <div class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                                <span class="font-mono">{{ __('compose & schedule') }}</span>
                                <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open composer') }}</span>
                            </div>
                        </a>
                    @endif

                    {{-- Sales Pipeline / Deal CRM — Kanban board of opportunities.
                         Route is plan-gated (access_sales_pipeline); the paywall
                         overlay handles plans without it. --}}
                    <a href="{{ url('/deals') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#EEF2FF] text-[#4F46E5] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <rect x="2" y="2.5" width="3.4" height="11" rx="1" />
                                    <rect x="6.3" y="2.5" width="3.4" height="7" rx="1" />
                                    <rect x="10.6" y="2.5" width="3.4" height="9" rx="1" />
                                </svg>
                            </span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10px] font-mono">NEW</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Sales Pipeline') }}</h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('A Kanban deal board for your team — track every opportunity from lead to won, drag cards between stages, and message customers on WhatsApp from the card.') }}
                        </p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ number_format($s['dealsOpen'] ?? 0) }} {{ __('open') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open board') }}</span>
                        </div>
                    </a>

                    {{-- Quick Send — manual message-queue sender. NOT the 1:1 inbox
 (that's /team-inbox). You compose, pick one or many recipients + a
 device, then send now or schedule, and track per-message delivery. --}}
                    <a href="{{ url('/chat') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#E0F2F1] text-wa-deep grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5" stroke-linejoin="round">
                                    <path d="M14.5 1.5L7 9M14.5 1.5L10 14.5l-3-5.5-5.5-3z" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">C0</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Quick Send') }}</h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Compose a message, pick one or many recipients and a sender, then send now or schedule it — a manual send queue with per-message delivery status.') }}
                        </p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ __('send queue') }}</span>
                            <span
                                class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    {{-- Developers / API — workspace REST API keys for /api/v1.
                         SaaS-standard placement on the user side. --}}


                    <a href="{{ url('/account') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-wa-mint text-wa-deep grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <circle cx="8" cy="6" r="3" />
                                    <path d="M2 14c0-3 2.5-5 6-5s6 2 6 5" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">A1</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Account') }}</h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Profile, photo, password, order history, wallet, affiliate, and support tickets — all in one place.') }}
                        </p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ ucfirst($s['planLabel'] ?? 'Free') }} ·
                                {{ number_format($s['walletCredits'] ?? 0) }} {{ __('credits') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/account/plans') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#FFF4E0] text-[#7B5A14] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M2 8l3-5 8-1-1 8-5 3z" />
                                    <circle cx="6" cy="6" r="1.2" />
                                    <path d="M9 9l4 4" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">A2</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Pricing & plans') }}</h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Compare Starter, Growth, Pro and Enterprise. Switch any time — we prorate to the day.') }}
                        </p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span
                                class="font-mono">{{ $stats['pricingSubtitle'] ?? __('Free plan · upgrade to unlock') }}</span>
                            <span
                                class="text-wa-deep font-semibold group-hover:underline">{{ __('View plans') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/analytics') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#FFF4E0] text-[#7B5A14] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M2 11l3-5 3 3 3-6 3 4" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">06</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Analytics') }}<x-plan-crown
                                feature="access_analytics" :link="false" size="sm" /></h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Delivery, read, and conversion charts across campaigns and devices.') }}</p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ number_format($stats['analytics7d'] ?? 0) }}
                                {{ __('msgs · last 7d') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/attributes') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#F3E9FF] text-[#5B3D8A] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M3 4h10M3 8h10M3 12h6" />
                                    <circle cx="13" cy="12" r="1.5" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">07</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Attributes') }}</h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Variables for WhatsApp templates. Built-ins like') }} <span
                                class="font-mono">{{ __('first_name') }}</span> + your custom ones (<span
                                class="font-mono">{{ __('order_id') }}</span>, etc.). Type <span
                                class="font-mono">/</span> in any message to insert.</p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ number_format($stats['attributesTotal'] ?? 0) }}
                                {{ __('saved') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/contacts') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-wa-mint text-wa-deep grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <circle cx="6" cy="6" r="3" />
                                    <path d="M2 14c0-3 2.5-5 4-5s4 2 4 5" />
                                    <circle cx="11.5" cy="5.5" r="2" />
                                    <path d="M10.5 10c1.9.2 3.5 1.7 3.5 4" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">08</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Contacts') }}</h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('All your contacts and groups in one place. Import from CSV, tag, segment, and search.') }}
                        </p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $s['contactsTotal'] ?? 0 }} people /
                                {{ $s['groupsTotal'] ?? 0 }} groups / {{ $s['tagsTotal'] ?? 0 }}
                                {{ __('tags') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/broadcasts') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-wa-bubble text-wa-deep grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M2 5.5h3l6-2v9l-6-2H2z" />
                                    <path d="M11 6.5h1a2 2 0 0 1 0 4h-1M5 10.5l1 3H4.5l-1-3" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">08</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Broadcasts') }}<x-plan-crown
                                feature="broadcast" :link="false" size="sm" /></h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Template broadcasts to selected contacts with send, delivery, read, failed, and queued tracking.') }}
                        </p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $s['broadcastQueued'] ?? 0 }}
                                queued{{ $s['broadcastPct'] !== null ? ' / ' . $s['broadcastPct'] . '% delivered' : '' }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/auto-reply') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-wa-mint text-wa-deep grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path
                                        d="M3 5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H8l-3 2v-2a2 2 0 0 1-2-2z" />
                                    <path d="M6 6h4M6 8h2" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">01</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Auto Reply') }}<x-plan-crown
                                feature="access_keyword_replies" :link="false" size="sm" /></h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Trigger replies on keywords, business hours, or when an agent is away.') }}</p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $s['autoReplyCount'] ?? 0 }}
                                rules{{ $s['autoReplyMatchPct'] !== null ? ' / ' . $s['autoReplyMatchPct'] . '% match' : '' }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/warmer') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-wa-mint text-wa-deep grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M8 1.5c1.6 2 1 3.5 0 4.5C6.5 7.5 5.5 9 6 10.5 6.4 11.7 8 12 8 12s1.6-.3 2-1.5c.5-1.5-.5-3-2-4.5" />
                                    <path d="M8 12c2.2 0 3.5-1.6 3.5-3.5M8 12c-2.2 0-3.5-1.6-3.5-3.5" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">WARM</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('WhatsApp Warmer') }}<x-plan-crown
                                feature="access_whatsapp_warmer" :link="false" size="sm" /></h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Ramp each number\'s daily send budget with human-like gaps + active hours to reduce ban risk.') }}</p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ __('Unofficial API') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/scheduled') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#D9E5F2] text-[#13478A] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <rect x="2.5" y="3.5" width="11" height="10" rx="1.5" />
                                    <path d="M2.5 6.5h11M5 2v3M11 2v3" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">02</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">
                            {{ __('Scheduled Messages') }}<x-plan-crown feature="schedulemessage" :link="false"
                                size="sm" /></h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Queue sends for the right moment — by timezone, segment, or trigger.') }}</p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $s['scheduledQueued'] ?? 0 }} queued / next
                                {{ $s['nextScheduled'] ?? '—' }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/message-history') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-paper-100 text-ink-700 grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M4 3h8v10H4z" />
                                    <path d="M6 6h4M6 8h4M6 10h2" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">03</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Message History') }}</h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Searchable archive of every message in and out — exportable to CSV.') }}</p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $s['messageHistoryHuman'] ?? '0' }} {{ __('records') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/notifications') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span
                                class="w-11 h-11 rounded-xl bg-accent-amber/20 text-[#7B5A14] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path
                                        d="M8 2a4 4 0 0 0-4 4v2.5L3 11h10l-1-2.5V6a4 4 0 0 0-4-4zM6.5 13a1.7 1.7 0 0 0 3 0" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">04</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Notifications') }}</h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Route alerts to email, Slack, or in-app — set quiet hours per channel.') }}</p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $s['notifActive'] ?? 0 }} {{ __('unread') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/integrations') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#F3E9FF] text-[#5B3D8A] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M5.5 4.5 3 7l2.5 2.5M10.5 4.5 13 7l-2.5 2.5M7 12l2-8" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">05</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Integrations') }}</h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Connect Shopify, HubSpot, Zapier, and more — sync contacts and orders.') }}</p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $s['integrationsConnected'] ?? 0 }} connected /
                                {{ $s['integrationsAvailable'] ?? 0 }} {{ __('available') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>


                    {{-- Google account — central place to connect/disconnect the
 workspace Google. The same OAuth tokens are used by the
 BookAppointment node, the Google Meet flow node, and the
 team-inbox composer's "Send Meet link" button. --}}
                    @php
                        // Safe nested-array access — `?->appointment_settings` may
                        // be null on a fresh workspace, and `null['key']` raises a
                        // PHP 8 warning. data_get() handles the chain.
                        $googleConnected = (bool) data_get(
                            \Illuminate\Support\Facades\Auth::user()?->currentWorkspace,
                            'appointment_settings.google_oauth.access_token',
                            false,
                        );
                    @endphp
                    <a href="{{ url('/google-account') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#E8F0FE] text-[#1A73E8] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M14 8a6 6 0 1 1-2-4.5M14 3v3h-3" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">06</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">
                            {{ __('Google account') }}<x-plan-crown feature="integration_google_calendar"
                                :link="false" size="sm" /></h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Connect once — powers Calendar, Meet links in flows, and the inbox "Send Meet link" composer button.') }}
                        </p>
                        <div class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px]">
                            <span
                                class="font-mono {{ $googleConnected ? 'text-wa-deep font-semibold' : 'text-ink-500' }}">{{ $googleConnected ? 'connected' : 'not connected' }}</span>
                            <span
                                class="text-wa-deep font-semibold group-hover:underline">{{ $googleConnected ? 'Manage' : 'Connect' }}</span>
                        </div>
                    </a>

                    {{-- AI Call Assistant — voice-AI agent that answers phone calls.
 Config wizard lives here; the actual real-time call loop
 runs in the Node bridge. --}}
                    @php
                        $assistantsCount = (int) \App\Models\AiCallAssistant::where(
                            'workspace_id',
                            \Illuminate\Support\Facades\Auth::user()?->current_workspace_id ?? 0,
                        )->count();
                        $assistantsLive = (int) \App\Models\AiCallAssistant::where(
                            'workspace_id',
                            \Illuminate\Support\Facades\Auth::user()?->current_workspace_id ?? 0,
                        )
                            ->where('status', 'live')
                            ->count();
                    @endphp
                    <a href="{{ url('/ai-assistants') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#F3E9FF] text-[#5B3D8A] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M8 1a3 3 0 0 0-3 3v4a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" />
                                    <path d="M3 8a5 5 0 0 0 10 0M8 13v2" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">07</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">
                            {{ __('AI Call Assistant') }}<x-plan-crown feature="access_ai_agents" :link="false"
                                size="sm" /></h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Voice AI that answers phone calls — Gemini brain, ElevenLabs voice, your own tools mid-call.') }}
                        </p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $assistantsCount }} configured / {{ $assistantsLive }}
                                {{ __('live') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    {{-- Call Logs — every voice call's transcript + recording + tool
 calls + cost. Read-only; writes come from Node's Twilio
 webhook handler. --}}
                    @php
                        $callLogsCount = (int) \App\Models\AiCallLog::where(
                            'workspace_id',
                            \Illuminate\Support\Facades\Auth::user()?->current_workspace_id ?? 0,
                        )
                            ->where('started_at', '>=', now()->subDay())
                            ->count();
                        $callLogsMins =
                            (int) (\App\Models\AiCallLog::where(
                                'workspace_id',
                                \Illuminate\Support\Facades\Auth::user()?->current_workspace_id ?? 0,
                            )
                                ->where('started_at', '>=', now()->subDay())
                                ->sum('duration_seconds') / 60);
                    @endphp
                    <a href="{{ url('/call-logs') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#FFF4E0] text-[#7B5A14] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path
                                        d="M3 4a2 2 0 0 1 2-2h2l1.5 3-1.5 1a8 8 0 0 0 4 4l1-1.5 3 1.5v2a2 2 0 0 1-2 2A11 11 0 0 1 3 4z" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">08</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Call Logs') }}<x-plan-crown
                                feature="access_waba_calling" :link="false" size="sm" /></h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Transcripts, recordings, and tool-call timelines for every voice call the AI handled.') }}
                        </p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $callLogsCount }} calls / {{ $callLogsMins }} min ·
                                24h</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    {{-- WhatsApp Forms — Meta-native interactive forms. ONLY shown
 when the workspace is on the WABA engine. Flows are a
 Meta Cloud API exclusive feature: publishing a form
 requires uploading the schema to Graph API and getting
 back a meta_flow_id; sending uses the interactive type
 "flow" message Baileys + Twilio don't support. Hiding the
 card prevents Baileys/Twilio operators from opening the
 builder, only to fail at publish time with "No WABA
 provider configured." --}}
                    @php
                        $_wsId = \Illuminate\Support\Facades\Auth::user()?->current_workspace_id ?? 0;
                        // Multi-engine: show WhatsApp Forms whenever WABA is among the
                        // workspace's enabled engines, not only when it's the default.
                        $_showForms = $_wsId && \App\Services\WorkspaceEngine::isEngineEnabled($_wsId, 'waba');
                        if ($_showForms) {
                            $formsCount = (int) \App\Models\WaForm::where('workspace_id', $_wsId)->count();
                            $formsLive = (int) \App\Models\WaForm::where('workspace_id', $_wsId)
                                ->where('status', 'published')
                                ->count();
                        }
                    @endphp
                    @if ($_showForms)
                        <a href="{{ url('/wa-forms') }}"
                            class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <span class="w-11 h-11 rounded-xl bg-wa-mint text-wa-deep grid place-items-center">
                                    <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                        stroke-width="1.5">
                                        <rect x="3" y="2" width="10" height="12" rx="1.5" />
                                        <path d="M5 5h6M5 8h6M5 11h4" />
                                    </svg>
                                </span>
                                <span class="font-mono text-[10px] text-ink-500">09</span>
                            </div>
                            <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Forms') }}</h2>
                            <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                                {{ __('Native WhatsApp interactive forms — collect leads, surveys, and bookings inside the chat.') }}
                            </p>
                            <div
                                class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                                <span class="font-mono">{{ $formsCount }} drafted / {{ $formsLive }}
                                    {{ __('live') }}</span>
                                <span
                                    class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                            </div>
                        </a>
                    @endif

                    {{-- WhatsApp Link Generator — trackable wa.me deep-links with
 a custom short-slug and click analytics. Plain wa.me use
 case (landing pages, Instagram bios, business cards) —
 separate from the chatbot widget (which runs a real chat
 surface) and wa-forms (Meta Flows). --}}
                    @php
                        $wsForWcl = \Illuminate\Support\Facades\Auth::user()?->current_workspace_id ?? 0;
                        $wclCount = (int) \App\Models\WaChatLink::where('workspace_id', $wsForWcl)->count();
                        $wclClicks = (int) \App\Models\WaChatLink::where('workspace_id', $wsForWcl)->sum('click_count');
                    @endphp
                    <a href="{{ url('/wa-links') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#D9E5F2] text-[#13478A] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M6.5 9.5l-2 2a2.5 2.5 0 1 0 3.5 3.5l2-2" />
                                    <path d="M9.5 6.5l2-2a2.5 2.5 0 1 0-3.5-3.5l-2 2" />
                                    <path d="M5 11l6-6" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">12</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('WhatsApp Link Generator') }}
                        </h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Mint trackable wa.me short links with a custom slug, pre-typed message, QR code, and click analytics. Drop it on a bio, landing page, or business card.') }}
                        </p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $wclCount }} links / {{ number_format($wclClicks) }}
                                {{ __('clicks') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    {{-- Chatbot Widget — embeddable floating chat bubble for the
 operator's own site. Each widget can be backed by an AI
 assistant; conversations land in Team Inbox. --}}
                    @php
                        $wsForCards = \Illuminate\Support\Facades\Auth::user()?->current_workspace_id ?? 0;
                        $widgetsCount = (int) \App\Models\ChatbotWidget::where('workspace_id', $wsForCards)->count();
                        $widgetsLive = (int) \App\Models\ChatbotWidget::where('workspace_id', $wsForCards)
                            ->where('status', 'active')
                            ->count();
                    @endphp
                    <a href="{{ url('/chatbot-widgets') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#E0F2F1] text-wa-deep grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path
                                        d="M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-3Z" />
                                    <circle cx="6" cy="7" r="0.6" fill="currentColor" />
                                    <circle cx="8" cy="7" r="0.6" fill="currentColor" />
                                    <circle cx="10" cy="7" r="0.6" fill="currentColor" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">10</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">
                            {{ __('Chatbot Widget') }}<x-plan-crown feature="access_chatbot_widgets"
                                :link="false" size="sm" /></h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Embed a floating chat bubble on your site. AI replies in-browser, hands off to Unified Inbox when needed.') }}
                        </p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $widgetsCount }} built / {{ $widgetsLive }}
                                {{ __('active') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    {{-- AI Training — chat assistants + their knowledge sources.
 Powers the chatbot widget (and future text channels). --}}
                    @php
                        $aitAssistants = (int) \App\Models\AiChatAssistant::where('workspace_id', $wsForCards)->count();
                        $aitSources = (int) \App\Models\AiTrainingSource::where('workspace_id', $wsForCards)
                            ->where('status', 'ready')
                            ->count();
                    @endphp
                    <a href="{{ url('/ai-training') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#FEF3E2] text-[#7B5A14] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M8 2l1.5 3.2L13 6l-2.5 2.4.6 3.4L8 10.2 4.9 11.8l.6-3.4L3 6l3.5-.8L8 2z" />
                                    <path d="M8 13v1" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">11</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('AI Training') }}<x-plan-crown
                                feature="access_ai_training" :link="false" size="sm" /></h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Train chat assistants on your own content — URLs, text snippets, Q&A pairs, plain-text files.') }}
                        </p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $aitAssistants }} assistants / {{ $aitSources }}
                                {{ __('sources') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/webhooks') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#E8F5E9] text-wa-deep grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M3 8h3l1.5-4 2 8 1.5-4h2" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">10</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Webhooks') }}<x-plan-crown
                                feature="access_outbound_webhooks" :link="false" size="sm" /></h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Push delivery, read, and reply events to any HTTPS endpoint in real time.') }}</p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $s['webhookEndpoints'] ?? 0 }}
                                endpoints{{ $s['webhookUptimePct'] !== null ? ' / ' . $s['webhookUptimePct'] . '% up' : '' }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/support') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span
                                class="w-11 h-11 rounded-xl bg-accent-coral/15 text-[#A1431F] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <circle cx="8" cy="8" r="5.5" />
                                    <path d="M5.5 6.5a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M8 12.5h.01" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">07</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Support') }}</h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Talk to a human, browse known issues, or check service status.') }}</p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $s['supportTotal'] ?? 0 }} tickets /
                                {{ $s['supportOpen'] ?? 0 }} {{ __('open') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/activity-log') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-wa-mint text-wa-deep grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <circle cx="8" cy="8" r="5.5" />
                                    <path d="M8 5v3l2 2" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">08</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Activity Log') }}</h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Every sign-in, workspace switch, inbox action, and admin change — attributed to user, IP, and time.') }}
                        </p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ number_format($stats['activity24h'] ?? 0) }}
                                {{ __('events · 24h') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/guidebook') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span
                                class="w-11 h-11 rounded-xl bg-white text-ink-700 border border-paper-200 grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M3 3.5h7a2 2 0 0 1 2 2v7H5a2 2 0 0 0-2 2z" />
                                    <path d="M5 6h5M5 8h5" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">09</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Guidebook') }}</h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Step-by-step playbooks — from first send to advanced segmentation.') }}</p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ number_format($stats['guidebookArticles'] ?? 0) }}
                                {{ __('articles') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/devices') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#D9E5F2] text-[#13478A] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <rect x="4" y="2" width="8" height="12" rx="1.5" />
                                    <path d="M7 11h2" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">10</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Devices') }}</h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Pair WhatsApp numbers, monitor session health, and rotate devices per workspace.') }}
                        </p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $s['deviceActive'] ?? 0 }} / {{ $s['deviceTotal'] ?? 0 }}
                                {{ __('live') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    <a href="{{ url('/team-inbox/members') }}"
                        class="group bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="w-11 h-11 rounded-xl bg-[#F3E9FF] text-[#5B3D8A] grid place-items-center">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <circle cx="6" cy="6" r="2.5" />
                                    <path d="M2 14c0-2.5 2-4 4-4s4 1.5 4 4" />
                                    <circle cx="11.5" cy="5" r="2" />
                                    <path d="M9.5 12c.4-1.4 1.6-2 3-2 1.5 0 2.5 1 2.5 2.5" />
                                </svg>
                            </span>
                            <span class="font-mono text-[10px] text-ink-500">11</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-semibold leading-tight">{{ __('Team members') }}</h2>
                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">
                            {{ __('Invite teammates, assign roles (owner / admin / member), and scope access per workspace.') }}
                        </p>
                        <div
                            class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[11px] text-ink-500">
                            <span class="font-mono">{{ $s['planSeats'] ?? 1 }}
                                seat{{ ($s['planSeats'] ?? 1) === 1 ? '' : 's' }} {{ __('active') }}</span>
                            <span class="text-wa-deep font-semibold group-hover:underline">{{ __('Open') }}</span>
                        </div>
                    </a>

                    {{-- Affiliate history card was moved into the right-aside hero slot
 below "Verify HMAC every time" — see further down in this view. --}}
                </div>
            </div>

            <aside class="space-y-4">
                <!-- Recent activity -->
                <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                    <div class="flex items-center justify-between mb-3">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Recent activity') }}</span>
                        <a href="{{ url('/activity-log') }}"
                            class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('View all') }}</a>
                    </div>
                    <div class="space-y-3">
                        @forelse ($recentActivity ?? collect() as $r)
                            <a href="{{ url('/activity-log') }}"
                                class="flex items-start gap-2.5 -mx-1 px-1 py-0.5 rounded hover:bg-paper-50">
                                <span
                                    class="w-7 h-7 rounded-lg {{ $r['badgeBg'] }} {{ $r['badgeFg'] }} grid place-items-center shrink-0 text-[10px] font-mono">{{ $r['badge'] }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-[12px] font-medium leading-tight truncate">{{ $r['label'] }}
                                    </div>
                                    <div class="text-[11px] text-ink-500 font-mono mt-0.5 truncate">
                                        {{ ucfirst($r['category']) }} / {{ $r['when'] }}</div>
                                </div>
                            </a>
                        @empty
                            @include('user.partials.empty-state', [
                                'message' =>
                                    'No activity found. Sign-ins, inbox actions, and workspace changes will appear here.',
                            ])
                        @endforelse
                    </div>
                </div>

                {{-- Affiliate history — tall hero tile, same wa-deep treatment as
 the Team Inbox card at the top of the page. Sits right after
 Recent activity in the right rail. --}}
                <a href="{{ url('/affiliate-history') }}"
                    class="group block bg-wa-deep text-paper-0 rounded-[14px] p-5 shadow-soft transition relative overflow-hidden min-h-[420px] flex flex-col">
                    <span
                        class="absolute top-3 right-3 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-0/15 text-[10px] font-mono">
                        <span class="w-1.5 h-1.5 rounded-full bg-wa-green animate-pulse"></span>EARN
                    </span>

                    <span
                        class="w-12 h-12 rounded-xl bg-paper-0/15 text-paper-0 grid place-items-center shrink-0 relative z-10">
                        <svg viewBox="0 0 16 16" class="w-6 h-6" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <path d="M5.5 4.5 3 7l2.5 2.5M10.5 4.5 13 7l-2.5 2.5M7 12l2-8" />
                        </svg>
                    </span>

                    <h2 class="font-serif text-[22px] leading-tight tracking-[-0.01em] mt-4 relative z-10">
                        {{ __('Affiliate history') }}</h2>
                    <p class="mt-1.5 text-[12.5px] text-paper-0/85 leading-snug relative z-10">
                        {{ __("Track every signup that came through your code, the credits earned, and your share link's performance.") }}
                    </p>

                    <div class="mt-4 space-y-2 relative z-10">
                        <div class="flex items-center justify-between text-[11.5px] font-mono">
                            <span class="text-paper-0/70">{{ __('Referrals') }}</span>
                            <span>{{ number_format($s['affiliateReferrals'] ?? 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-[11.5px] font-mono">
                            <span class="text-paper-0/70">{{ __('Credits earned') }}</span>
                            <span>{{ number_format($s['affiliateCredits'] ?? 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-[11.5px] font-mono">
                            <span class="text-paper-0/70">{{ __('Payouts') }}</span>
                            <span class="text-paper-0/60">{{ __('view in /affiliate-history') }}</span>
                        </div>
                    </div>

                    <div
                        class="mt-auto pt-4 border-t border-paper-0/15 flex items-center justify-between text-[11px] text-paper-0/80 relative z-10">
                        <span class="font-mono">{{ __('referrals · credits · payouts') }}</span>
                        <span class="text-paper-0 font-semibold inline-flex items-center gap-1.5">{{ __('Open') }}
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M3 8h10M9 4l4 4-4 4" />
                            </svg>
                        </span>
                    </div>

                    {{-- Decorative bars in the bottom-right corner, same treatment
 as the Team Inbox hero up top. Low-opacity, non-interactive. --}}
                    <svg viewBox="0 0 200 120"
                        class="absolute -right-2 -bottom-2 w-48 h-32 opacity-10 pointer-events-none">
                        <rect x="10" y="40" width="80" height="22" rx="11" fill="#FBFAF6" />
                        <rect x="100" y="68" width="80" height="22" rx="11" fill="#FBFAF6" />
                        <rect x="40" y="96" width="60" height="14" rx="7" fill="#FBFAF6" />
                    </svg>
                </a>

                <!-- Quick access -->
                <div class="bg-wa-deep rounded-[14px] p-4 shadow-soft text-paper-0">
                    <div class="font-serif text-[22px] leading-tight">{{ __('Quick access') }}</div>
                    <p class="mt-2 text-[12px] text-paper-0/75 leading-relaxed">
                        {{ __('Less-used tools live here so the main header stays focused on day-to-day work.') }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <a href="{{ url('/chat') }}"
                            class="rounded-[10px] bg-paper-0/10 hover:bg-paper-0/20 text-[12px] font-semibold px-3 py-2 text-center">{{ __('Open Chat') }}</a>
                        <a href="{{ url('/auto-reply/create') }}"
                            class="rounded-[10px] bg-paper-0/10 hover:bg-paper-0/20 text-[12px] font-semibold px-3 py-2 text-center">{{ __('Add rule') }}</a>
                    </div>
                </div>

                <!-- Plan card -->
                <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                    <div class="flex items-center justify-between">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Workspace plan') }}</span>
                        <span
                            class="text-[10px] font-mono text-wa-deep bg-wa-mint px-1.5 py-0.5 rounded">{{ strtoupper($s['planLabel'] ?? 'FREE') }}</span>
                    </div>
                    <div class="mt-2 font-serif text-[20px]">{{ ucfirst($s['planLabel'] ?? 'Free') }} /
                        {{ $s['planSeats'] ?? 1 }} seat{{ ($s['planSeats'] ?? 1) === 1 ? '' : 's' }}</div>
                    <div class="mt-3">
                        <div class="flex items-center justify-between text-[11px] text-ink-500 font-mono">
                            <span>{{ __('Messages this month') }}</span><span>{{ number_format($s['messagesThisMonth'] ?? 0) }}
                                {{ __('sent') }}</span></div>
                        @php
                            $msgCount = (int) ($s['messagesThisMonth'] ?? 0);
                            // Soft "expected daily volume" bar — fills to 100% over the
                            // course of a normal month. Cosmetic only, not a real cap.
                            $dayOfMonth = now()->day;
                            $daysInMonth = now()->daysInMonth;
                            $expectedPct = max(2, min(100, (int) round(($dayOfMonth / max(1, $daysInMonth)) * 100)));
                        @endphp
                        <div class="mt-1 h-1.5 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-deep" style="width:{{ $msgCount > 0 ? $expectedPct : 0 }}%">
                            </div>
                        </div>
                    </div>
                    <a href="{{ url('/account?tab=wallet') }}"
                        class="mt-3 inline-flex items-center gap-1 text-[12px] text-wa-deep font-semibold hover:underline">{{ __('Manage billing') }}
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M6 3l5 5-5 5" />
                        </svg>
                    </a>
                </div>

                <!-- Pro tip carousel -->
                <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-6 shadow-card">
                    <div class="flex items-center justify-between mb-2">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3 h-3 text-accent-amber" fill="currentColor">
                                <path d="M8 1l1.6 4.2L14 6l-3.5 2.5 1.4 4.3L8 10.5 4.1 12.8l1.4-4.3L2 6l4.4-.8z" />
                            </svg>
                            Pro tip
                        </span>
                        <div class="flex items-center gap-1" id="tip-dots"></div>
                    </div>
                    <h4 id="tip-title" class="font-serif text-[15px] leading-tight"></h4>
                    <p id="tip-body" class="mt-1.5 text-[12px] text-ink-600 leading-relaxed"></p>
                    <a id="tip-link" href="#"
                        class="mt-3 inline-flex items-center gap-1 text-[11.5px] text-wa-deep font-semibold hover:underline">
                        <span id="tip-cta">{{ __('Try it →') }}</span>
                    </a>
                </div>

                <!-- What's new -->
                <div class="bg-wa-deep text-paper-0 rounded-[14px] p-5 shadow-soft relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/70">{{ __("What's new") }}</span>
                        <span class="text-[9.5px] font-mono px-1.5 py-0.5 rounded bg-paper-0/15">v3.2</span>
                    </div>
                    <h4 class="font-serif text-[18px] leading-tight mt-2">{{ __('Unified Inbox + AI assist') }}</h4>
                    <ul class="mt-2.5 space-y-1.5 text-[11.5px] text-paper-0/85">
                        <li class="flex items-center gap-2"><svg viewBox="0 0 16 16"
                                class="w-3 h-3 text-wa-green shrink-0" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Shared inbox with assignment & notes') }}</li>
                        <li class="flex items-center gap-2"><svg viewBox="0 0 16 16"
                                class="w-3 h-3 text-wa-green shrink-0" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('ChatGPT / Gemini / Claude in flows') }}</li>
                        <li class="flex items-center gap-2"><svg viewBox="0 0 16 16"
                                class="w-3 h-3 text-wa-green shrink-0" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Sales forecast in Shopify dashboard') }}</li>
                    </ul>
                    <a href="{{ url('/guidebook') }}"
                        class="mt-3 inline-flex items-center gap-1 text-[11.5px] font-semibold">{{ __('Read changelog') }}
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M6 3l5 5-5 5" />
                        </svg>
                    </a>
                    <svg viewBox="0 0 100 60"
                        class="absolute -right-2 -bottom-2 w-32 h-20 opacity-10 pointer-events-none">
                        <rect x="6" y="20" width="40" height="11" rx="5.5" fill="#FBFAF6" />
                        <rect x="50" y="34" width="40" height="11" rx="5.5" fill="#FBFAF6" />
                    </svg>
                </div>

                <!-- Small tip · keep numbers warm -->
                <div class="bg-paper-0 border border-paper-200 rounded-[12px] p-4 shadow-card flex items-start gap-3">
                    <span class="w-8 h-8 rounded-lg bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path
                                d="M8 2.5c-2.5 3-2.5 5.5 0 7s2.5 4-.5 4M5.5 8c-1 1.5-1 3 .5 4M11 7c1.5 1.5 1.5 3 .5 4.5" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[12.5px] font-semibold leading-tight">{{ __('Keep new numbers warm') }}
                        </div>
                        <p class="mt-0.5 text-[11px] text-ink-500 leading-snug">
                            {{ __('Start with 50 sends/day and double weekly. Sudden bursts get flagged.') }}</p>
                    </div>
                </div>

                <!-- Small tip · tagging -->
                <div class="bg-paper-0 border border-paper-200 rounded-[12px] p-4 shadow-card flex items-start gap-3">
                    <span class="w-8 h-8 rounded-lg bg-[#D9E5F2] text-[#13478A] grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M8 2H3v5l7 7 5-5z" />
                            <circle cx="5.5" cy="4.5" r="0.8" fill="currentColor" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[12.5px] font-semibold leading-tight">{{ __('Tag now, segment later') }}
                        </div>
                        <p class="mt-0.5 text-[11px] text-ink-500 leading-snug">
                            {{ __('Add a tag the moment a contact replies — broadcast filters become trivial later.') }}
                        </p>
                    </div>
                </div>

                <!-- Small tip · use templates -->
                <div class="bg-paper-0 border border-paper-200 rounded-[12px] p-4 shadow-card flex items-start gap-3">
                    <span class="w-8 h-8 rounded-lg bg-[#F3E9FF] text-[#5B3D8A] grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <rect x="3" y="3" width="10" height="10" rx="1.5" />
                            <path d="M3 6h10M6 9h4" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[12.5px] font-semibold leading-tight">{{ __('Templates beat free-text') }}
                        </div>
                        <p class="mt-0.5 text-[11px] text-ink-500 leading-snug">
                            {{ __('Pre-approved templates open the 24h window even when a contact has gone quiet.') }}
                        </p>
                    </div>
                </div>

                <!-- Small tip · webhook signing -->
                <div class="bg-paper-0 border border-paper-200 rounded-[12px] p-4 shadow-card flex items-start gap-3">
                    <span class="w-8 h-8 rounded-lg bg-[#E8F5E9] text-wa-deep grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <rect x="3" y="7" width="10" height="7" rx="1.5" />
                            <path d="M5 7V5a3 3 0 1 1 6 0v2" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[12.5px] font-semibold leading-tight">{{ __('Verify HMAC every time') }}
                        </div>
                        <p class="mt-0.5 text-[11px] text-ink-500 leading-snug">{{ __('Always check') }} <span
                                class="font-mono">{{ \App\Support\Brand::webhookSignatureHeader() }}</span> before processing — one line
                            of code, lots of safety.</p>
                    </div>
                </div>

            </aside>
        </section>

        <!-- Bottom shortcuts strip -->
        <section id="more-shortcuts-section"
            class="mt-6 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Shortcuts') }}
                    </div>
                    <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Jump into common tasks') }}</h3>
                </div>
                <button type="button" id="more-shortcuts-customise"
                    class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('Customise') }}</button>
            </div>
            <div id="more-shortcuts-grid"
                data-workspace-id="{{ (int) (auth()->user()->current_workspace_id ?? 0) }}"
                class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
                <a href="{{ url('/auto-reply/create') }}"
                    class="border border-paper-200 rounded-[10px] p-3 hover:border-wa-deep hover:bg-paper-50 transition flex items-start gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M8 3v10M3 8h10" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="text-[12px] font-semibold leading-tight">{{ __('New auto reply') }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-0.5 leading-snug">
                            {{ __('Set up a keyword trigger') }}</div>
                    </div>
                </a>
                <a href="{{ url('/scheduled/create') }}"
                    class="border border-paper-200 rounded-[10px] p-3 hover:border-wa-deep hover:bg-paper-50 transition flex items-start gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-[#D9E5F2] text-[#13478A] grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <circle cx="8" cy="8" r="5.5" />
                            <path d="M8 5v3l2 2" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="text-[12px] font-semibold leading-tight">{{ __('Schedule send') }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-0.5 leading-snug">{{ __('Pick time + segment') }}
                        </div>
                    </div>
                </a>
                <a href="{{ url('/webhooks') }}"
                    class="border border-paper-200 rounded-[10px] p-3 hover:border-wa-deep hover:bg-paper-50 transition flex items-start gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-[#E8F5E9] text-wa-deep grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M3 8h3l1.5-4 2 8 1.5-4h2" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="text-[12px] font-semibold leading-tight">{{ __('Test webhook') }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-0.5 leading-snug">{{ __('Fire a sample payload') }}
                        </div>
                    </div>
                </a>
                <a href="{{ url('/message-history') }}"
                    class="border border-paper-200 rounded-[10px] p-3 hover:border-wa-deep hover:bg-paper-50 transition flex items-start gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-paper-100 text-ink-700 grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M3 4h10v3H3zM3 9h10v3H3z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="text-[12px] font-semibold leading-tight">{{ __('Export history') }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-0.5 leading-snug">{{ __('CSV / last 30 days') }}
                        </div>
                    </div>
                </a>
                <a href="{{ url('/support') }}"
                    class="border border-paper-200 rounded-[10px] p-3 hover:border-wa-deep hover:bg-paper-50 transition flex items-start gap-2.5">
                    <span
                        class="w-8 h-8 rounded-lg bg-accent-coral/15 text-[#A1431F] grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <circle cx="8" cy="8" r="5.5" />
                            <path d="M5.5 6.5a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M8 12.5h.01" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="text-[12px] font-semibold leading-tight">{{ __('Contact support') }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-0.5 leading-snug">{{ __('Open a help ticket') }}
                        </div>
                    </div>
                </a>
            </div>
        </section>

        <div id="more-shortcuts-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-ink-900/45 p-4"
            aria-hidden="true">
            <div
                class="w-full max-w-4xl rounded-[16px] border border-paper-200 bg-paper-0 shadow-soft overflow-hidden">
                <div class="flex items-start justify-between gap-4 border-b border-paper-200 px-5 py-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Shortcuts') }}</div>
                        <h3 class="font-serif text-[22px] leading-tight mt-1">{{ __('Customise quick actions') }}
                        </h3>
                        <p class="text-[12px] text-ink-500 mt-1">
                            {{ __('Add, remove, and reorder the cards shown on the More page.') }}</p>
                    </div>
                    <button type="button" id="more-shortcuts-close"
                        class="w-9 h-9 rounded-full border border-paper-200 grid place-items-center hover:border-wa-deep hover:text-wa-deep transition"
                        aria-label="{{ __('Close shortcut customizer') }}">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M4 4l8 8M12 4l-8 8" />
                        </svg>
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-[1.2fr_0.8fr] gap-5 p-5">
                    <div>
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <h4 class="text-[12px] font-semibold uppercase tracking-[0.12em] text-ink-500">
                                {{ __('Current cards') }}</h4>
                            <span
                                class="text-[11px] text-ink-500">{{ __('Use the arrow buttons to change position.') }}</span>
                        </div>
                        <div id="more-shortcut-editor-list" class="space-y-2"></div>
                        <p id="more-shortcut-empty"
                            class="hidden rounded-[12px] border border-dashed border-paper-200 bg-paper-50 p-4 text-center text-[12px] text-ink-500">
                            {{ __('No shortcuts selected. Add one from the list or create a custom link.') }}</p>
                    </div>

                    <div class="space-y-4">
                        <div class="rounded-[12px] border border-paper-200 bg-paper-50 p-4">
                            <h4 class="text-[12px] font-semibold uppercase tracking-[0.12em] text-ink-500">
                                {{ __('Add preset') }}</h4>
                            <div class="mt-3 flex gap-2">
                                <select id="more-shortcut-add-select"
                                    class="min-w-0 flex-1 rounded-[10px] border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] outline-none focus:border-wa-deep">
                                    <option value="">{{ __('Choose shortcut') }}</option>
                                </select>
                                <button type="button" id="more-shortcut-add-btn"
                                    class="shrink-0 rounded-full bg-wa-deep px-4 py-2 text-[12px] font-semibold text-paper-0 hover:bg-wa-deep/90 transition">{{ __('Add') }}</button>
                            </div>
                        </div>

                        <div class="rounded-[12px] border border-paper-200 bg-paper-50 p-4">
                            <h4 class="text-[12px] font-semibold uppercase tracking-[0.12em] text-ink-500">
                                {{ __('Add custom link') }}</h4>
                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="text-[11px] font-semibold text-ink-600">{{ __('Title') }}</span>
                                    <input id="more-shortcut-custom-title" type="text" maxlength="40"
                                        class="mt-1 w-full rounded-[10px] border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] outline-none focus:border-wa-deep"
                                        placeholder="{{ __('Open reports') }}">
                                </label>
                                <label class="block">
                                    <span class="text-[11px] font-semibold text-ink-600">{{ __('Icon') }}</span>
                                    <select id="more-shortcut-custom-icon"
                                        class="mt-1 w-full rounded-[10px] border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] outline-none focus:border-wa-deep">
                                        <option value="link">{{ __('Link') }}</option>
                                        <option value="plus">{{ __('Plus') }}</option>
                                        <option value="clock">{{ __('Clock') }}</option>
                                        <option value="inbox">{{ __('Inbox') }}</option>
                                        <option value="users">{{ __('Users') }}</option>
                                        <option value="phone">{{ __('Phone') }}</option>
                                        <option value="template">{{ __('Template') }}</option>
                                    </select>
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="text-[11px] font-semibold text-ink-600">{{ __('Subtitle') }}</span>
                                    <input id="more-shortcut-custom-subtitle" type="text" maxlength="64"
                                        class="mt-1 w-full rounded-[10px] border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] outline-none focus:border-wa-deep"
                                        placeholder="{{ __('Optional helper text') }}">
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="text-[11px] font-semibold text-ink-600">{{ __('URL') }}</span>
                                    <input id="more-shortcut-custom-href" type="text" maxlength="160"
                                        class="mt-1 w-full rounded-[10px] border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] outline-none focus:border-wa-deep"
                                        placeholder="/reports">
                                </label>
                            </div>
                            <button type="button" id="more-shortcut-add-custom"
                                class="mt-3 w-full rounded-full border border-paper-200 bg-paper-0 px-4 py-2 text-[12px] font-semibold text-wa-deep hover:border-wa-deep transition">{{ __('Add custom shortcut') }}</button>
                        </div>
                    </div>
                </div>

                <div
                    class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 border-t border-paper-200 px-5 py-4">
                    <button type="button" id="more-shortcuts-reset"
                        class="rounded-full border border-paper-200 px-4 py-2 text-[12px] font-semibold text-ink-600 hover:border-wa-deep hover:text-wa-deep transition">{{ __('Reset defaults') }}</button>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <button type="button" id="more-shortcuts-cancel"
                            class="rounded-full border border-paper-200 px-4 py-2 text-[12px] font-semibold text-ink-600 hover:border-wa-deep hover:text-wa-deep transition">{{ __('Cancel') }}</button>
                        <button type="button" id="more-shortcuts-save"
                            class="rounded-full bg-wa-deep px-4 py-2 text-[12px] font-semibold text-paper-0 hover:bg-wa-deep/90 transition">{{ __('Save changes') }}</button>
                    </div>
                </div>
            </div>
        </div>

    </main>

</x-layouts.user>
