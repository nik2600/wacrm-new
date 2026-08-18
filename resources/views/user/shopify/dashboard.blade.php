<x-layouts.user :title="__('Shopify')" nav-key="more" page="user-shopify-dashboard">

    @php
        $isConnected = $integration && $integration->isConnected();
        $tabs = [
            'overview' => 'Overview',
            'orders' => 'Orders',
            'products' => 'Products',
            'offers' => 'Send Offer',
            'customers' => 'Customers',
            'events' => 'Automations',
            'analytics' => 'Analytics',
            'logs' => 'Activity',
            'settings' => 'Settings',
        ];
        $activeTab = array_key_exists($activeTab ?? 'overview', $tabs) ? $activeTab : 'overview';
        $currency = $currency ?? ($integration?->shop_currency ?? 'USD');
    @endphp

    @if (!$isConnected)
        {{-- ════════════════════════════════════════════════════════════
 NOT CONNECTED — match the /connect page chrome:
 sidebar (logo + setup steps + help) + main (title + form + FAQ).
 ════════════════════════════════════════════════════════════ --}}

        <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
            <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

                <!-- ===== LEFT RAIL ===== -->
                <aside class="space-y-3">
                    <!-- Platform info card -->
                    <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                        <div class="w-12 h-12 rounded-xl mb-3 grid place-items-center" style="background:#F1F9EC">
                            <svg viewBox="0 0 32 32" class="w-8 h-8">
                                <path fill="#95BF47"
                                    d="M22.5 7.6c0-.1-.1-.2-.2-.2L20.5 7l-1.4-1.4c-.1-.1-.4-.1-.5 0L17 6c-.7-2-2.4-2.5-3.6-2.1-2.6.8-3.8 4-4.5 6 0 0-1.7.5-1.8.5-.9.3-1 .3-1.1 1.2L4.5 27.6 19.7 30l8.2-1.8L22.5 7.6z" />
                                <path fill="#5E8E3E"
                                    d="M22.3 7.4c-.1 0-1.7-.1-1.7-.1s-1.4-1.3-1.5-1.5c-.1-.1-.2-.1-.2-.1L19.7 30l8.2-1.8L22.5 7.6s-.1-.2-.2-.2z" />
                                <path fill="#fff"
                                    d="M16.4 11.7l-.9 3.4s-1-.5-2.2-.4c-1.8.1-1.8 1.2-1.8 1.5.1 1.5 4.1 1.9 4.3 5.5.2 2.8-1.5 4.7-3.9 4.9-2.9.2-4.5-1.5-4.5-1.5l.6-2.6s1.6 1.2 2.9 1.1c.8-.1 1.1-.7 1.1-1.2-.1-2-3.4-1.9-3.6-5.1-.2-2.7 1.6-5.5 5.5-5.7 1.5-.1 2.5.2 2.5.2z" />
                            </svg>
                        </div>
                        <div class="font-serif text-[18px] leading-tight">{{ __('Shopify') }}</div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mt-1">
                            {{ __('Integration') }}</div>
                        <div
                            class="mt-3 inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-mono bg-paper-50 border border-paper-200 text-ink-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-paper-200"></span>
                            Not connected
                        </div>
                    </div>

                    <!-- Setup steps -->
                    <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                            {{ __('Setup steps') }}</div>
                        <ol class="px-1 space-y-0.5">
                            <li
                                class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] {{ $appEnabled ? 'text-ink-700' : 'text-ink-500' }}">
                                <span
                                    class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono {{ $appEnabled ? 'bg-wa-mint text-wa-deep' : 'bg-paper-100 text-ink-500' }}">{{ $appEnabled ? '✓' : '1' }}</span>
                                {{ __('Admin enables Shopify app') }}
                            </li>
                            <li
                                class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] {{ $appEnabled ? 'bg-wa-deep/8 text-wa-deep font-semibold' : 'text-ink-500' }}">
                                <span
                                    class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono {{ $appEnabled ? 'bg-wa-deep text-paper-0' : 'bg-paper-100 text-ink-500' }}">2</span>
                                {{ __('Enter your store domain') }}
                            </li>
                            <li class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-500">
                                <span
                                    class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-paper-100 text-ink-500">3</span>
                                {{ __('Approve scopes on Shopify') }}
                            </li>
                            <li class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-500">
                                <span
                                    class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-paper-100 text-ink-500">4</span>
                                {{ __('Map events → WhatsApp templates') }}
                            </li>
                        </ol>
                    </div>

                    <!-- Help card -->
                    <div
                        class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                        <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-wa-green"></span>
                            Need help?
                        </div>
                        Webhooks are auto-installed after connecting — no manual steps. Stuck on credentials? <a
                            href="{{ url('/support') }}"
                            class="text-wa-deep font-semibold underline">{{ __('Contact support') }}</a>.
                    </div>
                </aside>

                <!-- ===== MAIN ===== -->
                <section class="space-y-5">

                    <!-- Title row -->
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                                <a href="{{ url('/integrations') }}"
                                    class="hover:text-wa-deep">{{ __('Integrations') }}</a>
                                <span class="mx-1.5 text-ink-500/60">/</span>
                                <span>{{ __('Shopify') }}</span>
                            </div>
                            <h1 class="font-serif font-normal tracking-tight text-[30px] sm:text-[36px] lg:text-[44px] leading-none">
                                {{ __('Connect') }} <span class="italic text-wa-deep">{{ __('Shopify') }}</span></h1>
                            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                                {{ __('Connect your Shopify store so order, fulfilment, refund, and cart events automatically trigger WhatsApp messages — and the dashboard shows live order/product/customer counts.') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ url('/integrations') }}"
                                class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M10 4l-4 4 4 4" />
                                </svg>
                                Back
                            </a>
                            <a href="{{ url('/guidebook') }}"
                                class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('View docs') }}</a>
                        </div>
                    </div>

                    @if (session('error'))
                        <div
                            class="bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-2 text-[12.5px] text-[#A1431F] inline-flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <path d="M8 5v3M8 11v.01" />
                                <circle cx="8" cy="8" r="6" />
                            </svg>
                            {{ session('error') }}
                        </div>
                    @endif
                    @if (isset($errors) && $errors->any())
                        <div
                            class="bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-2 text-[12.5px] text-[#A1431F]">
                            @foreach ($errors->all() as $e)
                                <div>{{ $e }}</div>
                            @endforeach
                        </div>
                    @endif

                    @if (!$appEnabled)
                        {{-- Admin hasn't configured Shopify yet --}}
                        <div
                            class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card flex items-start gap-5">
                            <div class="w-12 h-12 rounded-xl bg-accent-amber/20 grid place-items-center shrink-0">
                                <svg viewBox="0 0 24 24" class="w-6 h-6 text-accent-amber" fill="none"
                                    stroke="currentColor" stroke-width="1.6">
                                    <path d="M12 9v3M12 16h.01" />
                                    <circle cx="12" cy="12" r="9" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-serif text-[22px] leading-tight">{{ __("Shopify isn't enabled yet") }}
                                </div>
                                <p class="text-[12.5px] text-ink-600 mt-1.5 max-w-2xl">
                                    {{ __("An administrator needs to add the Shopify app credentials before any workspace can connect a store.
                                     Once that's done, the form below will activate.") }}
                                </p>
                            </div>
                        </div>
                    @else
                        <!-- Two-column grid: form (left) + setup guide (right) -->
                        <div class="grid grid-cols-1 lg:grid-cols-[1fr_330px] gap-5 items-start">

                            <!-- Connection form card -->
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Step 2') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight mt-0.5 mb-4">{{ __('Store domain') }}
                                </h2>

                                <form method="POST" action="{{ url('/shopify/connect') }}" class="space-y-4">
                                    @csrf

                                    <div>
                                        <label
                                            class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Shopify store URL') }}
                                            <span class="text-accent-coral">*</span></label>
                                        <div
                                            class="flex items-stretch border border-paper-200 rounded-lg overflow-hidden bg-white focus-within:border-wa-deep focus-within:ring-4 focus-within:ring-wa-deep/10">
                                            <input type="text" name="shop" required autofocus
                                                placeholder="{{ __('my-store') }}"
                                                class="flex-1 px-3 py-2.5 bg-transparent text-[13px] font-mono focus:outline-none">
                                            <span
                                                class="px-3 py-2.5 bg-paper-50 border-l border-paper-200 text-[12.5px] font-mono text-ink-500 flex items-center">.myshopify.com</span>
                                        </div>
                                        <p class="text-[10.5px] text-ink-500 mt-1">
                                            {{ __("Just the store handle — or paste the full URL, we'll normalise it.") }}
                                        </p>
                                    </div>

                                    <!-- Webhook URL preview -->
                                    <div class="rounded-lg bg-paper-50/60 border border-paper-200 p-3">
                                        <div
                                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1">
                                            {{ __('Webhook URL · auto-configured after connect') }}</div>
                                        <div class="font-mono text-[11px] text-ink-700 break-all">
                                            {{ url('/shopify/webhook/{webhook_secret}') }}</div>
                                    </div>

                                    <!-- Buttons -->
                                    <div class="flex items-center gap-2 pt-2">
                                        <a href="{{ url('/integrations') }}"
                                            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
                                        <button type="submit"
                                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5 ml-auto">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                                                <path d="M2 4l12-2v12L2 12V4Z" />
                                            </svg>
                                            Continue to Shopify
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Setup guide column -->
                            <aside class="space-y-4">
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Setup guide') }}</div>
                                    <h3 class="font-serif text-[18px] leading-tight mt-0.5 mb-3">
                                        {{ __('What happens next') }}</h3>
                                    <ol class="space-y-3 text-[12.5px] text-ink-700">
                                        <li class="flex items-start gap-2">
                                            <span
                                                class="w-5 h-5 rounded-full bg-paper-100 text-ink-700 grid place-items-center font-mono text-[10px] shrink-0 mt-0.5">1</span>
                                            <span>{{ __('We redirect you to Shopify to authorize the requested scopes.') }}</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <span
                                                class="w-5 h-5 rounded-full bg-paper-100 text-ink-700 grid place-items-center font-mono text-[10px] shrink-0 mt-0.5">2</span>
                                            <span>{{ __('Shopify sends us back a permanent access token signed with HMAC.') }}</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <span
                                                class="w-5 h-5 rounded-full bg-paper-100 text-ink-700 grid place-items-center font-mono text-[10px] shrink-0 mt-0.5">3</span>
                                            <span>Webhooks for
                                                {{ count(\App\Services\Shopify\ShopifyService::WEBHOOK_TOPICS) }}
                                                events get auto-registered against your store.</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <span
                                                class="w-5 h-5 rounded-full bg-paper-100 text-ink-700 grid place-items-center font-mono text-[10px] shrink-0 mt-0.5">4</span>
                                            <span>{{ __('You land back here to map each event to a WhatsApp template.') }}</span>
                                        </li>
                                    </ol>
                                </div>

                                <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                                    <div class="font-semibold text-[12.5px]">{{ __('Scopes requested') }}</div>
                                    <p class="font-mono text-[11px] text-ink-600 mt-1 leading-relaxed break-words">
                                        {{ \App\Models\SystemSetting::get('shopify_scopes', \App\Services\Shopify\ShopifyService::DEFAULT_SCOPES) }}
                                    </p>
                                </div>
                            </aside>

                        </div>
                    @endif
                </section>
            </div>
        </main>
    @else
        {{-- ════════════════════════════════════════════════════════════
 CONNECTED — original sidebar + main dashboard (preserved).
 ════════════════════════════════════════════════════════════ --}}

        @if (session('success'))
            <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pt-4">
                <div
                    class="px-4 py-2.5 rounded-xl bg-wa-bubble border border-wa-green/30 text-[12.5px] text-wa-deep flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="m4 8 3 3 5-6" />
                    </svg>
                    {{ session('success') }}
                </div>
            </div>
        @endif
        @if (session('error'))
            <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pt-4">
                <div
                    class="px-4 py-2.5 rounded-xl bg-accent-coral/10 border border-accent-coral/30 text-[12.5px] text-accent-coral flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M8 5v3M8 11v.01" />
                        <circle cx="8" cy="8" r="6" />
                    </svg>
                    {{ session('error') }}
                </div>
            </div>
        @endif

        <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
            <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

                <!-- =============== SIDEBAR =============== -->
                <aside class="space-y-3">

                    <!-- Store info card -->
                    <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                        <div class="flex items-start justify-between gap-2">
                            <span class="w-11 h-11 rounded-xl shrink-0 grid place-items-center"
                                style="background:#F1F9EC">
                                <svg viewBox="0 0 32 32" class="w-7 h-7">
                                    <path fill="#95BF47"
                                        d="M22.5 7.6c0-.1-.1-.2-.2-.2L20.5 7l-1.4-1.4c-.1-.1-.4-.1-.5 0L17 6c-.7-2-2.4-2.5-3.6-2.1-2.6.8-3.8 4-4.5 6 0 0-1.7.5-1.8.5-.9.3-1 .3-1.1 1.2L4.5 27.6 19.7 30l8.2-1.8L22.5 7.6z" />
                                    <path fill="#5E8E3E"
                                        d="M22.3 7.4c-.1 0-1.7-.1-1.7-.1s-1.4-1.3-1.5-1.5c-.1-.1-.2-.1-.2-.1L19.7 30l8.2-1.8L22.5 7.6s-.1-.2-.2-.2z" />
                                    <path fill="#fff"
                                        d="M16.4 11.7l-.9 3.4s-1-.5-2.2-.4c-1.8.1-1.8 1.2-1.8 1.5.1 1.5 4.1 1.9 4.3 5.5.2 2.8-1.5 4.7-3.9 4.9-2.9.2-4.5-1.5-4.5-1.5l.6-2.6s1.6 1.2 2.9 1.1c.8-.1 1.1-.7 1.1-1.2-.1-2-3.4-1.9-3.6-5.1-.2-2.7 1.6-5.5 5.5-5.7 1.5-.1 2.5.2 2.5.2z" />
                                </svg>
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono bg-wa-mint text-wa-deep border border-wa-green/40">
                                <span
                                    class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ ucfirst($integration->status) }}
                            </span>
                        </div>
                        <div class="font-serif text-[18px] leading-tight mt-3">
                            {{ $integration->store_name ?: $integration->store_url }}</div>
                        <div class="font-mono text-[10.5px] text-ink-500 mt-0.5 truncate">
                            {{ $integration->store_url }}</div>
                        @if ($integration->shop_plan)
                            <div class="mt-2 font-mono text-[10px] text-ink-500">{{ __('Plan:') }} <span
                                    class="text-ink-700">{{ $integration->shop_plan }}</span></div>
                        @endif
                    </div>

                    <!-- Tabs -->
                    <nav class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card space-y-0.5">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                            {{ __('Store') }}</div>
                        @foreach ($tabs as $tab => $label)
                            @php
                                $isActive = $activeTab === $tab;
                                $badge = match ($tab) {
                                    'orders' => number_format($counts['orders'] ?? 0),
                                    'products' => number_format($counts['products'] ?? 0),
                                    'customers' => number_format($counts['customers'] ?? 0),
                                    'events' => (string) $activeEvents,
                                    'logs' => (string) $logTotal,
                                    default => null,
                                };
                            @endphp
                            <a href="?tab={{ $tab }}"
                                class="ig-tab flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] {{ $isActive ? 'bg-wa-deep/8 text-wa-deep font-semibold' : 'text-ink-700 hover:bg-paper-50' }}">
                                <span class="flex-1">{{ $label }}</span>
                                @if ($badge !== null)
                                    <span
                                        class="font-mono text-[10px] px-1.5 py-0.5 rounded-full {{ $isActive ? 'bg-wa-deep text-paper-0' : 'bg-paper-100 text-ink-600' }}">{{ $badge }}</span>
                                @endif
                            </a>
                        @endforeach

                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-3 pb-1.5">
                            {{ __('Related') }}</div>
                        <a href="{{ url('/integrations') }}"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] text-ink-700 hover:bg-paper-50">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M5.5 4.5 3 7l2.5 2.5M10.5 4.5 13 7l-2.5 2.5M7 12l2-8" />
                            </svg>
                            All integrations
                        </a>
                        <form method="POST" action="{{ url('/shopify/' . $integration->id . '/disconnect') }}"
                            onsubmit="return confirm('Disconnect Shopify? This deletes registered webhooks but keeps your settings.');">
                            @csrf
                            <button type="submit"
                                class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] text-accent-coral hover:bg-accent-coral/10">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M5 7L3 9l4 4 2-2M11 9l2-2-4-4-2 2" />
                                </svg>
                                Disconnect
                            </button>
                        </form>
                    </nav>

                    <!-- Webhook health card -->
                    @php
                        $lastLog = $recentLogs->first();
                        $healthy = $lastLog && $lastLog->created_at?->gt(now()->subDay());
                    @endphp
                    <div
                        class="border {{ $healthy ? 'border-wa-green/30 bg-wa-bubble/50 text-ink-700' : 'border-paper-200 bg-paper-0 text-ink-600' }} rounded-2xl p-4 text-[12px] leading-relaxed">
                        <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full {{ $healthy ? 'bg-wa-green' : 'bg-paper-200' }}"></span>
                            {{ $healthy ? 'Webhooks healthy' : 'No recent events' }}
                        </div>
                        @if ($lastLog)
                            Last event: <span class="font-mono">{{ $lastLog->event_type }}</span> ·
                            {{ $lastLog->created_at->diffForHumans() }}
                        @else
                            Trigger an action in Shopify and it'll appear here.
                        @endif
                    </div>
                </aside>

                <!-- =============== MAIN =============== -->
                <section class="space-y-5">

                    <!-- Page header -->
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                                <a href="{{ url('/integrations') }}"
                                    class="hover:text-wa-deep">{{ __('Integrations') }}</a>
                                <span class="mx-1.5 text-ink-500/60">/</span>
                                <span>{{ __('Shopify') }}</span>
                                <span class="mx-1.5 text-ink-500/60">/</span>
                                <span>{{ $tabs[$activeTab] }}</span>
                            </div>
                            <h1 class="font-serif font-normal tracking-tight text-[30px] sm:text-[36px] lg:text-[44px] leading-none">
                                {{ $integration->store_name ?: $integration->store_url }} <span
                                    class="italic text-wa-deep">{{ strtolower($tabs[$activeTab]) }}</span></h1>
                            <p class="text-[13px] text-ink-600 mt-2">
                                @switch($activeTab)
                                    @case('overview')
                                        Live snapshot of your store, synced from Shopify.
                                    @break

                                    @case('orders')
                                        Orders synced from Shopify.
                                    @break

                                    @case('products')
                                        Browse your synced catalogue — send any product or push an offer over WhatsApp.
                                    @break

                                    @case('offers')
                                        Pick products + a coupon, choose a customer segment, and broadcast the offer over
                                        WhatsApp.
                                    @break

                                    @case('customers')
                                        Customers synced from Shopify into your contacts.
                                    @break

                                    @case('events')
                                        Marketing automations — toggle one on, pick a template, and it fires on the matching
                                        store event.
                                    @break

                                    @case('analytics')
                                        Revenue, order trend and message performance for this store.
                                    @break

                                    @case('logs')
                                        Webhook delivery log for this store.
                                    @break

                                    @case('settings')
                                        Manage the connection — re-sync, view scopes, or disconnect.
                                    @break
                                @endswitch
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button id="shopify-sync-btn" data-id="{{ $integration->id }}"
                                class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path
                                        d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                                </svg>
                                <span>{{ __('Sync now') }}</span>
                            </button>
                        </div>
                    </div>

                    @switch($activeTab)
                        @case('overview')
                            <!-- KPI strip -->
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Revenue (recent)') }}</div>
                                    <div class="font-serif text-[30px] leading-none mt-2 tabular-nums">{{ $currency }}
                                        {{ number_format($revenue30d, 2) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">{{ __('across recent synced orders') }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Orders total') }}</div>
                                    <div class="font-serif text-[30px] leading-none mt-2 tabular-nums">
                                        {{ number_format($counts['orders'] ?? 0) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">{{ __('from Shopify counts API') }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Products') }}</div>
                                    <div class="font-serif text-[30px] leading-none mt-2 tabular-nums">
                                        {{ number_format($counts['products'] ?? 0) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">{{ __('live catalog size') }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Customers') }}</div>
                                    <div class="font-serif text-[30px] leading-none mt-2 tabular-nums">
                                        {{ number_format($counts['customers'] ?? 0) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">{{ $activeEvents }}
                                        {{ __('active automations') }}</div>
                                </div>
                            </div>

                            <!-- Recent orders + Recent activity -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="font-serif text-[20px] leading-tight">{{ __('Recent orders') }}</h3>
                                        <a href="?tab=orders"
                                            class="text-[12px] text-wa-deep font-medium hover:underline">{{ __('View all →') }}</a>
                                    </div>
                                    <div class="space-y-2">
                                        @forelse (collect($orders)->take(5) as $o)
                                            @php
                                                $cust = $o['customer'] ?? [];
                                                $name =
                                                    trim(
                                                        ($cust['first_name'] ?? '') . ' ' . ($cust['last_name'] ?? ''),
                                                    ) ?:
                                                    $o['email'] ?? '—';
                                            @endphp
                                            <div
                                                class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg hover:bg-paper-50">
                                                <div class="min-w-0">
                                                    <div class="text-[12.5px] font-medium truncate">
                                                        {{ $o['name'] ?? '#' . ($o['order_number'] ?? '?') }}</div>
                                                    <div class="font-mono text-[10.5px] text-ink-500 truncate">
                                                        {{ $name }}</div>
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <div class="text-[12.5px] font-semibold tabular-nums">
                                                        {{ $o['currency'] ?? $currency }}
                                                        {{ number_format((float) ($o['total_price'] ?? 0), 2) }}</div>
                                                    <div class="font-mono text-[10px] text-ink-500">
                                                        {{ ucfirst((string) ($o['financial_status'] ?? '—')) }}</div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="text-[12px] text-ink-500 text-center py-6">
                                                {{ __('No orders fetched yet. Hit') }} <em>{{ __('Sync now') }}</em>.</div>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="font-serif text-[20px] leading-tight">{{ __('Recent webhook activity') }}
                                        </h3>
                                        <a href="?tab=logs"
                                            class="text-[12px] text-wa-deep font-medium hover:underline">{{ __('View all →') }}</a>
                                    </div>
                                    <div class="space-y-2">
                                        @forelse ($recentLogs->take(6) as $log)
                                            @php
                                                $statusCss = match ($log->status) {
                                                    'processed', 'sent' => 'bg-wa-green/15 text-wa-deep',
                                                    'failed' => 'bg-accent-coral/15 text-accent-coral',
                                                    'skipped' => 'bg-paper-100 text-ink-700',
                                                    default => 'bg-accent-amber/15 text-[#8B5A14]',
                                                };
                                            @endphp
                                            <div
                                                class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg hover:bg-paper-50">
                                                <div class="min-w-0">
                                                    <div class="text-[12.5px] font-medium truncate font-mono">
                                                        {{ $log->event_type }}</div>
                                                    <div class="text-[10.5px] text-ink-500">
                                                        {{ $log->created_at?->diffForHumans() }}</div>
                                                </div>
                                                <span
                                                    class="font-mono text-[10px] uppercase px-2 py-0.5 rounded-full {{ $statusCss }}">{{ $log->status }}</span>
                                            </div>
                                        @empty
                                            <div class="text-[12px] text-ink-500 text-center py-6">
                                                {{ __('No webhook events received yet.') }}</div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        @break

                        @case('orders')
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                                <div class="overflow-x-auto">
                                <table class="w-full text-[12.5px]">
                                    <thead
                                        class="bg-paper-50 text-left font-mono text-[10.5px] uppercase text-ink-500 tracking-wide">
                                        <tr>
                                            <th class="px-4 py-2.5">{{ __('Order') }}</th>
                                            <th class="px-4 py-2.5">{{ __('Customer') }}</th>
                                            <th class="px-4 py-2.5">{{ __('Status') }}</th>
                                            <th class="px-4 py-2.5 text-right">{{ __('Total') }}</th>
                                            <th class="px-4 py-2.5 text-right">{{ __('Placed') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-paper-100">
                                        @forelse ($orders as $o)
                                            @php
                                                $cust = $o['customer'] ?? [];
                                                $name =
                                                    trim(
                                                        ($cust['first_name'] ?? '') . ' ' . ($cust['last_name'] ?? ''),
                                                    ) ?:
                                                    $o['email'] ?? '—';
                                            @endphp
                                            <tr class="hover:bg-paper-50">
                                                <td class="px-4 py-2.5 font-mono text-ink-700">
                                                    {{ $o['name'] ?? '#' . ($o['order_number'] ?? '?') }}</td>
                                                <td class="px-4 py-2.5">{{ $name }}</td>
                                                <td class="px-4 py-2.5">
                                                    <span
                                                        class="font-mono text-[10px] px-2 py-0.5 rounded-full bg-paper-100 text-ink-700">{{ ucfirst((string) ($o['financial_status'] ?? '—')) }}</span>
                                                </td>
                                                <td class="px-4 py-2.5 text-right tabular-nums font-medium">
                                                    {{ $o['currency'] ?? $currency }}
                                                    {{ number_format((float) ($o['total_price'] ?? 0), 2) }}</td>
                                                <td class="px-4 py-2.5 text-right font-mono text-[11px] text-ink-500">
                                                    {{ \Carbon\Carbon::parse($o['created_at'] ?? null)->diffForHumans() }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-8 text-center text-[12px] text-ink-500">
                                                    {{ __('No orders found.') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        @break

                        @case('products')
                            @php
                                $brands = collect($products)->pluck('vendor')->filter()->unique()->values()->take(8);
                                // Reusable product-card renderer (closure) — storefront style.
                                $card = function ($p) use ($currency) {
                                    $img = $p['image_url'] ?? null;
                                    $price = (float) ($p['price'] ?? 0);
                                    $cmp = $p['compare_price'] ?? null;
                                    $off = (int) ($p['discount_pct'] ?? 0);
                                    $json = e(
                                        json_encode([
                                            'id' => $p['id'],
                                            'title' => $p['title'],
                                            'price' => $price,
                                            'image' => $img,
                                            'url' => $p['product_url'] ?? '',
                                        ]),
                                    );
                                    $title = e($p['title'] ?? '—');
                                    $vendor = e($p['vendor'] ?? '');
                                    $tAttr = e(strtolower($p['title'] ?? ''));
                                    $bAttr = e(strtolower($p['vendor'] ?? ''));
                                    $priceFmt = $currency . ' ' . number_format($price, 2);
                                    $imgHtml = $img
                                        ? '<img src="' . e($img) . '" alt="" class="w-full h-full object-cover">'
                                        : '<div class="w-full h-full grid place-items-center font-mono text-[10px] text-ink-500">no image</div>';
                                    $badge =
                                        $off > 0
                                            ? '<span class="absolute top-2 left-2 px-1.5 py-0.5 rounded bg-accent-coral text-paper-0 text-[9.5px] font-mono font-bold">SAVE ' .
                                                $off .
                                                '%</span>'
                                            : '';
                                    $cmpHtml = $cmp
                                        ? '<span class="text-[11px] text-ink-400 line-through tabular-nums">' .
                                            $currency .
                                            ' ' .
                                            number_format((float) $cmp, 2) .
                                            '</span>'
                                        : '';
                                    return '<div data-prod data-title="' .
                                        $tAttr .
                                        '" data-brand="' .
                                        $bAttr .
                                        '" class="group bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">' .
                                        '<div class="relative h-40 bg-paper-100">' .
                                        $imgHtml .
                                        $badge .
                                        '</div>' .
                                        '<div class="p-3 flex-1 flex flex-col">' .
                                        '<div class="text-[12.5px] font-medium leading-snug line-clamp-2 min-h-[34px]">' .
                                        $title .
                                        '</div>' .
                                        '<div class="font-mono text-[10px] text-ink-500 mt-0.5 truncate">' .
                                        $vendor .
                                        '</div>' .
                                        '<div class="mt-2 flex items-baseline gap-1.5"><span class="text-[13px] font-semibold tabular-nums">' .
                                        $priceFmt .
                                        '</span>' .
                                        $cmpHtml .
                                        '</div>' .
                                        '<div class="mt-3 flex items-center gap-1.5">' .
                                        '<button type="button" data-shopify-send=\'' .
                                        $json .
                                        '\' class="flex-1 px-2.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[11px] font-semibold inline-flex items-center justify-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 8h10M9 4l4 4-4 4"/></svg>Send</button>' .
                                        '<button type="button" data-shopify-offer=\'' .
                                        $json .
                                        '\' class="px-2.5 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[11px] font-semibold inline-flex items-center gap-1" title="Add to offer broadcast"><svg viewBox="0 0 16 16" class="w-3 h-3 text-accent-coral" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 2l1.8 3.7 4 .6-2.9 2.8.7 4L8 11.8 4.4 13l.7-4L2.2 6.3l4-.6z"/></svg>Offer</button>' .
                                        '</div></div></div>';
                                };
                            @endphp

                            {{-- Hero search --}}
                            <div
                                class="rounded-2xl border border-paper-200 bg-paper-0 p-6 flex items-center justify-center shadow-card">
                                <div class="relative w-full max-w-[460px]">
                                    <svg viewBox="0 0 16 16"
                                        class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                                        stroke="currentColor" stroke-width="1.6">
                                        <circle cx="7" cy="7" r="5" />
                                        <path d="m11 11 3 3" />
                                    </svg>
                                    <input id="shopify-prod-search" type="search"
                                        placeholder="{{ __('Search products…') }}"
                                        class="w-full pl-11 pr-4 py-2.5 rounded-full bg-paper-50 border border-paper-200 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </div>
                            </div>

                            {{-- Brand chips --}}
                            @if ($brands->isNotEmpty())
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($brands as $b)
                                        <button type="button" data-shopify-brand="{{ $b }}"
                                            class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:border-wa-deep text-[12px] font-medium">{{ $b }}</button>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Special offers --}}
                            @if (!empty($offers))
                                <div class="flex items-center justify-between">
                                    <h3 class="font-serif text-[20px] leading-tight">{{ __('Special offers') }}</h3>
                                    <a href="?tab=catalog"
                                        class="text-[12px] text-wa-deep font-semibold hover:underline">{{ __('Send a catalog →') }}</a>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3" data-shopify-grid>
                                    @foreach ($offers as $p)
                                        {!! $card($p) !!}
                                    @endforeach
                                </div>
                            @endif

                            {{-- New arrivals --}}
                            @if (!empty($newArrivals))
                                <h3 class="font-serif text-[20px] leading-tight mt-2">{{ __('New arrivals') }}</h3>
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3" data-shopify-grid>
                                    @foreach ($newArrivals as $p)
                                        {!! $card($p) !!}
                                    @endforeach
                                </div>
                            @endif

                            {{-- All products / popular --}}
                            <h3 class="font-serif text-[20px] leading-tight mt-2">{{ __('All products') }}</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3" data-shopify-grid>
                                @forelse ($products as $p)
                                    {!! $card($p) !!}
                                @empty
                                    <div
                                        class="col-span-4 text-[12px] text-ink-500 text-center py-10 bg-paper-0 border border-dashed border-paper-200 rounded-2xl">
                                        {{ __('No products yet. Connect a store or hit') }} <em>{{ __('Sync now') }}</em>.
                                    </div>
                                @endforelse
                            </div>
                        @break

                        @case('offers')
                            <form id="shopify-offer-form" data-id="{{ $integration->id }}"
                                class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5 items-start">
                                @csrf
                                {{-- LEFT: product picker --}}
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="font-serif text-[18px] leading-tight">{{ __('Choose products') }}</h3>
                                        <span class="text-[11.5px] text-ink-500"><span id="offer-picked">0</span>
                                            {{ __('selected') }}</span>
                                    </div>
                                    @if (empty($products))
                                        <div
                                            class="text-[12px] text-ink-500 text-center py-10 border border-dashed border-paper-200 rounded-xl">
                                            {{ __('No products synced yet.') }}</div>
                                    @else
                                        <div
                                            class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-h-[420px] overflow-y-auto pr-1">
                                            @foreach ($products as $p)
                                                <label
                                                    class="relative border border-paper-200 rounded-xl p-2 cursor-pointer hover:border-wa-deep has-[:checked]:border-wa-deep has-[:checked]:ring-2 has-[:checked]:ring-wa-deep/20 transition block">
                                                    <input type="checkbox" name="product_ids[]" value="{{ $p['id'] }}"
                                                        class="sr-only" data-offer-product>
                                                    <div class="h-20 rounded-lg bg-paper-100 overflow-hidden mb-1.5">
                                                        @if (!empty($p['image_url']))
                                                            <img src="{{ $p['image_url'] }}" alt=""
                                                                class="w-full h-full object-cover">
                                                        @endif
                                                    </div>
                                                    <div class="text-[11.5px] font-medium leading-tight line-clamp-2">
                                                        {{ $p['title'] }}</div>
                                                    <div class="text-[11px] font-semibold mt-0.5">{{ $currency }}
                                                        {{ number_format((float) $p['price'], 2) }}</div>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                {{-- RIGHT: offer settings --}}
                                <aside class="space-y-3">
                                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5 space-y-4">
                                        <div>
                                            <label
                                                class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Message template') }}
                                                <span class="text-accent-coral">*</span></label>
                                            <select name="template_id"
                                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                                <option value="">— {{ __('Pick a template') }} —</option>
                                                @foreach ($templates as $tpl)
                                                    <option value="{{ $tpl->id }}">{{ $tpl->template_name }}@if (($tpl->provider ?? null) && method_exists($tpl, 'engineKey') && $tpl->engineKey() === 'waba') · {{ $tpl->provider->display_label ?: $tpl->provider->phone_number }}@endif ·
                                                        {{ strtoupper($tpl->language) }}</option>
                                                @endforeach
                                            </select>
                                            <p class="text-[10.5px] text-ink-500 mt-1">{{ __('Tokens you can use:') }} <code
                                                    class="font-mono">@{{ product_name }}</code>, <code
                                                    class="font-mono">@{{ price }}</code>, <code
                                                    class="font-mono">@{{ coupon_code }}</code></p>
                                        </div>
                                        <div>
                                            <label
                                                class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Send to segment') }}
                                                <span class="text-accent-coral">*</span></label>
                                            <select name="group_id"
                                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                                <option value="">— {{ __('Pick a contact group') }} —</option>
                                                @foreach ($contactGroups as $g)
                                                    <option value="{{ $g->id }}">{{ $g->user_group }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label
                                                class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Coupon (optional)') }}</label>
                                            <select name="coupon_code"
                                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                                <option value="">— {{ __('No coupon') }} —</option>
                                                @foreach ($coupons as $cp)
                                                    <option value="{{ $cp->code }}">{{ $cp->code }}
                                                        ({{ $cp->type === 'percent' ? $cp->amount . '%' : $currency . ' ' . $cp->amount }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" id="offer-send-btn"
                                            class="w-full px-4 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center justify-center gap-2">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                stroke="currentColor" stroke-width="1.7">
                                                <path d="M2 8h10M9 4l4 4-4 4" />
                                            </svg>
                                            {{ __('Send offer') }}
                                        </button>
                                        <div id="offer-status" class="text-[11.5px] text-center text-ink-500"></div>
                                    </div>
                                    <div
                                        class="bg-wa-bubble/40 border border-wa-green/30 rounded-2xl p-4 text-[11.5px] text-ink-700 leading-relaxed">
                                        {{ __('Only opted-in contacts in the segment are messaged. WABA sends require an approved template; the Unofficial API renders it as text + buttons.') }}
                                    </div>
                                </aside>
                            </form>

                            {{-- Win-back lapsed customers (one-click, no schedule needed) --}}
                            <form id="shopify-winback-form" data-id="{{ $integration->id }}"
                                class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5 mt-5">
                                @csrf
                                <div class="flex items-center gap-2 mb-1">
                                    <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none"
                                        stroke="currentColor" stroke-width="1.6">
                                        <path d="M3 8a5 5 0 1 1 1.5 3.5M3 8V5M3 8h3" />
                                    </svg>
                                    <h3 class="font-serif text-[18px] leading-tight">{{ __('Smart segment broadcast') }}</h3>
                                </div>
                                <p class="text-[11.5px] text-ink-500 mb-3">
                                    {{ __('Target customers from order history — recency (win-back), order count and total spend (VIPs). Skips opted-out contacts.') }}
                                </p>
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 items-end">
                                    <label class="block">
                                        <span class="text-[11px] font-semibold text-ink-700">{{ __('Last order') }}</span>
                                        <select name="days"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                            <option value="0">{{ __('Any time') }}</option>
                                            @foreach ([30 => '> 30 days ago', 60 => '> 60 days ago', 90 => '> 90 days ago', 180 => '> 6 months', 365 => '> 1 year'] as $d => $lbl)
                                                <option value="{{ $d }}" @selected($d === 60)>
                                                    {{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-[11px] font-semibold text-ink-700">{{ __('Min orders') }}</span>
                                        <select name="min_orders"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                            @foreach ([0 => 'Any', 1 => '1+', 2 => '2+', 3 => '3+', 5 => '5+', 10 => '10+'] as $v => $lbl)
                                                <option value="{{ $v }}">{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-[11px] font-semibold text-ink-700">{{ __('Min spend') }}
                                            ({{ $currency }})</span>
                                        <input type="number" name="min_spent" min="0" step="1"
                                            placeholder="0"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                    </label>
                                    <label class="block">
                                        <span class="text-[11px] font-semibold text-ink-700">{{ __('Template') }}</span>
                                        <select name="template_id"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                            <option value="">— {{ __('Pick') }} —</option>
                                            @foreach ($templates as $tpl)
                                                <option value="{{ $tpl->id }}">{{ $tpl->template_name }}@if (($tpl->provider ?? null) && method_exists($tpl, 'engineKey') && $tpl->engineKey() === 'waba') · {{ $tpl->provider->display_label ?: $tpl->provider->phone_number }}@endif ·
                                                    {{ strtoupper($tpl->language) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-[11px] font-semibold text-ink-700">{{ __('Coupon') }}</span>
                                        <select name="coupon_code"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                            <option value="">— {{ __('None') }} —</option>
                                            @foreach ($coupons as $cp)
                                                <option value="{{ $cp->code }}">{{ $cp->code }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                                <div class="flex items-center justify-between mt-3">
                                    <span id="winback-status" class="text-[11.5px] text-ink-500"></span>
                                    <button type="submit" id="winback-send-btn"
                                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold inline-flex items-center gap-2">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                            stroke-width="1.7">
                                            <path d="M2 8h10M9 4l4 4-4 4" />
                                        </svg>
                                        {{ __('Send win-back') }}
                                    </button>
                                </div>
                            </form>
                        @break

                        @case('customers')
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                                <div class="overflow-x-auto">
                                <table class="w-full text-[12.5px]">
                                    <thead
                                        class="bg-paper-50 text-left font-mono text-[10.5px] uppercase text-ink-500 tracking-wide">
                                        <tr>
                                            <th class="px-4 py-2.5">{{ __('Name') }}</th>
                                            <th class="px-4 py-2.5">{{ __('Email') }}</th>
                                            <th class="px-4 py-2.5">{{ __('Phone') }}</th>
                                            <th class="px-4 py-2.5 text-right">{{ __('Orders') }}</th>
                                            <th class="px-4 py-2.5 text-right">{{ __('Spent') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-paper-100">
                                        @forelse ($customers as $c)
                                            <tr class="hover:bg-paper-50">
                                                <td class="px-4 py-2.5">
                                                    {{ trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: '—' }}
                                                </td>
                                                <td class="px-4 py-2.5 font-mono text-[11.5px] text-ink-700">
                                                    {{ $c['email'] ?? '—' }}</td>
                                                <td class="px-4 py-2.5 font-mono text-[11.5px] text-ink-700">
                                                    {{ $c['phone'] ?? '—' }}</td>
                                                <td class="px-4 py-2.5 text-right tabular-nums">
                                                    {{ number_format((int) ($c['orders_count'] ?? 0)) }}</td>
                                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $currency }}
                                                    {{ number_format((float) ($c['total_spent'] ?? 0), 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-8 text-center text-[12px] text-ink-500">
                                                    {{ __('No customers fetched yet.') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        @break

                        @case('events')
                            @php
                                // Friendly automation metadata per Shopify webhook topic. The
                                // app/uninstalled cleanup hook is intentionally not shown.
                                $AUTO = [
                                    'orders/create' => [
                                        'Order confirmation',
                                        'Thank the customer the moment they place an order.',
                                        'C',
                                    ],
                                    'orders/paid' => ['Payment received', 'Confirm once payment has cleared.', 'C'],
                                    'orders/fulfilled' => [
                                        'Shipped / Out for delivery',
                                        'Send a dispatch note — use {{ tracking_url }} for the live tracking link.',
                                        'C',
                                    ],
                                    'order/delivered' => [
                                        'Delivered',
                                        'Confirm delivery (asks for a review if your template does). Fires when the courier marks it delivered.',
                                        'C',
                                    ],
                                    'orders/cancelled' => [
                                        'Order cancelled',
                                        'Let the customer know an order was cancelled.',
                                        'C',
                                    ],
                                    'refunds/create' => ['Refund issued', 'Confirm a refund has been processed.', 'C'],
                                    'orders/updated' => [
                                        'Order updated',
                                        'Fire on any change to an existing order.',
                                        'C',
                                    ],
                                    'checkouts/create' => [
                                        'Abandoned cart · step 1',
                                        'Fires right after a checkout is abandoned — include {{ checkout_url }} to bring them back.',
                                        'R',
                                    ],
                                    'cart/step2' => [
                                        'Abandoned cart · step 2',
                                        'Scheduled follow-up (set the delay, e.g. 1 hour). Auto-cancels if they buy.',
                                        'R',
                                    ],
                                    'cart/step3' => [
                                        'Abandoned cart · step 3',
                                        'Final nudge (e.g. 24 hours) — add a coupon in the template to close the sale.',
                                        'R',
                                    ],
                                    'cod/confirm' => [
                                        'COD order confirmation',
                                        'Ask cash-on-delivery customers to confirm with a Yes/No reply — cuts fake orders & returns (RTO).',
                                        'R',
                                    ],
                                    'cod/prepaid' => [
                                        'COD → Prepaid offer',
                                        'Nudge COD customers to pay online now (use {{ order_url }} + a discount in the template) — fewer returns, faster cash.',
                                        'R',
                                    ],
                                    'stock/back' => [
                                        'Back-in-stock alert',
                                        'Customers who message about a sold-out item get notified automatically when it is restocked.',
                                        'R',
                                    ],
                                    'customers/create' => [
                                        'Welcome new customer',
                                        'Greet a first-time customer warmly.',
                                        'M',
                                    ],
                                    'customers/update' => [
                                        'Customer updated',
                                        'Fire when a customer profile changes.',
                                        'M',
                                    ],
                                    'products/update' => [
                                        'Product updated',
                                        'Internal trigger on price / stock change.',
                                        'M',
                                    ],
                                ];
                                $groups = [
                                    'C' => [
                                        'Order lifecycle',
                                        'Transactional updates — fire automatically on store events.',
                                    ],
                                    'R' => ['Revenue recovery', 'Bring back lost sales.'],
                                    'M' => ['Lifecycle marketing', 'Grow and retain customers.'],
                                ];
                                $shown = collect(array_keys($AUTO));
                            @endphp
                            <form id="shopify-events-form" data-id="{{ $integration->id }}" class="space-y-5">
                                @csrf
                                @foreach ($groups as $gKey => $g)
                                    @php $topicsInGroup = $shown->filter(fn ($t) => $AUTO[$t][2] === $gKey); @endphp
                                    @if ($topicsInGroup->isNotEmpty())
                                        <div>
                                            <div class="flex items-baseline gap-2 mb-2">
                                                <h3 class="font-serif text-[18px] leading-tight">{{ $g[0] }}</h3>
                                                <span class="text-[11.5px] text-ink-500">{{ $g[1] }}</span>
                                            </div>
                                            <div class="space-y-2.5">
                                                @foreach ($topicsInGroup as $topic)
                                                    @php
                                                        $ev = $eventsByType[$topic] ?? null;
                                                        $active = (bool) $ev?->is_active;
                                                        $tplId = $ev?->template_id;
                                                        $sendTo = $ev?->send_to ?? 'customer';
                                                        $adminNum = $ev?->admin_number;
                                                        $delay = $ev?->delay_seconds ?? 0;
                                                        $sentCount = (int) ($logsByEvent[$topic] ?? 0);
                                                        [$name, $desc] = $AUTO[$topic];
                                                    @endphp
                                                    <div
                                                        class="bg-paper-0 border {{ $active ? 'border-wa-green/40' : 'border-paper-200' }} rounded-2xl shadow-card overflow-hidden">
                                                        <div class="px-5 py-3.5 flex items-center gap-3">
                                                            <span
                                                                class="w-9 h-9 rounded-xl shrink-0 grid place-items-center {{ $active ? 'bg-wa-mint text-wa-deep' : 'bg-paper-100 text-ink-500' }}">
                                                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none"
                                                                    stroke="currentColor" stroke-width="1.6">
                                                                    <path d="M2 8h12M9 4l4 4-4 4" />
                                                                </svg>
                                                            </span>
                                                            <div class="flex-1 min-w-0">
                                                                <div class="text-[14px] font-semibold leading-tight">
                                                                    {{ $name }}</div>
                                                                <div class="text-[11.5px] text-ink-500 leading-snug">
                                                                    {{ $desc }} <span
                                                                        class="font-mono text-ink-400">·
                                                                        {{ $topic }}</span></div>
                                                            </div>
                                                            <span
                                                                class="font-mono text-[10.5px] text-ink-500 shrink-0">{{ $sentCount }}
                                                                {{ __('fired') }}</span>
                                                            <label class="relative inline-block w-[42px] h-[24px] shrink-0">
                                                                <input type="hidden"
                                                                    name="events[{{ $topic }}][is_active]"
                                                                    value="0">
                                                                <input type="checkbox"
                                                                    name="events[{{ $topic }}][is_active]"
                                                                    value="1" @checked($active)
                                                                    class="peer sr-only">
                                                                <span
                                                                    class="absolute inset-0 rounded-full bg-paper-200 peer-checked:bg-wa-deep transition cursor-pointer"></span>
                                                                <span
                                                                    class="absolute top-[3px] left-[3px] w-[18px] h-[18px] rounded-full bg-paper-0 shadow transition peer-checked:translate-x-[18px] pointer-events-none"></span>
                                                            </label>
                                                        </div>
                                                        <div
                                                            class="px-5 pb-4 pt-1 border-t border-paper-100 grid grid-cols-1 md:grid-cols-[1fr_150px_120px] gap-3">
                                                            <div>
                                                                <span
                                                                    class="font-mono text-[9.5px] uppercase text-ink-500 tracking-wide">{{ __('Template') }}</span>
                                                                <select name="events[{{ $topic }}][template_id]"
                                                                    data-tpl-select data-topic="{{ $topic }}"
                                                                    class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">
                                                                    <option value="">— {{ __('Pick a template') }} —
                                                                    </option>
                                                                    @foreach ($templates as $tpl)
                                                                        <option value="{{ $tpl->id }}"
                                                                            @selected($tplId == $tpl->id)>
                                                                            {{ $tpl->template_name }}@if (($tpl->provider ?? null) && method_exists($tpl, 'engineKey') && $tpl->engineKey() === 'waba') · {{ $tpl->provider->display_label ?: $tpl->provider->phone_number }}@endif ·
                                                                            {{ strtoupper($tpl->language) }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <div class="mt-2 hidden" data-shopify-varmap-row
                                                                    data-topic="{{ $topic }}"
                                                                    data-saved='@json($ev?->var_map ?? [])'></div>
                                                            </div>
                                                            <div>
                                                                <span
                                                                    class="font-mono text-[9.5px] uppercase text-ink-500 tracking-wide">{{ __('Send to') }}</span>
                                                                <select name="events[{{ $topic }}][send_to]"
                                                                    data-shopify-sendto
                                                                    class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">
                                                                    <option value="customer" @selected($sendTo === 'customer')>
                                                                        {{ __('Customer') }}</option>
                                                                    <option value="admin" @selected($sendTo === 'admin')>
                                                                        {{ __('Admin only') }}</option>
                                                                    <option value="both" @selected($sendTo === 'both')>
                                                                        {{ __('Both') }}</option>
                                                                </select>
                                                                <input type="text"
                                                                    name="events[{{ $topic }}][admin_number]"
                                                                    value="{{ $adminNum }}"
                                                                    placeholder="{{ __('Admin number') }}"
                                                                    data-shopify-adminnum
                                                                    class="mt-1.5 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] font-mono focus:outline-none focus:border-wa-deep {{ in_array($sendTo, ['admin', 'both'], true) ? '' : 'hidden' }}">
                                                            </div>
                                                            <div>
                                                                <span
                                                                    class="font-mono text-[9.5px] uppercase text-ink-500 tracking-wide">{{ __('Delay') }}</span>
                                                                <select name="events[{{ $topic }}][delay_seconds]"
                                                                    class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">
                                                                    @foreach ([0 => 'Immediate', 60 => '1 min', 300 => '5 min', 900 => '15 min', 3600 => '1 hr', 86400 => '1 day'] as $sec => $label)
                                                                        <option value="{{ $sec }}"
                                                                            @selected((int) $delay === $sec)>{{ $label }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endforeach

                                <div
                                    class="sticky bottom-0 bg-paper-50/90 backdrop-blur border-t border-paper-200 -mx-1 px-5 py-3 flex items-center justify-between rounded-b-2xl">
                                    <span id="shopify-events-status" class="text-[11.5px] text-ink-500 font-mono"></span>
                                    <button type="submit"
                                        class="px-5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold inline-flex items-center gap-2">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                            stroke-width="1.8">
                                            <path d="m4 8 3 3 5-6" />
                                        </svg>
                                        {{ __('Save automations') }}
                                    </button>
                                </div>
                            </form>
                        @break

                        @case('analytics')
                            @php $a = $analytics ?? ['revenue_total'=>0,'orders_total'=>0,'aov'=>0,'messages_sent'=>0,'offers_sent'=>0,'trend'=>[],'trend_max'=>1]; @endphp
                            {{-- KPI strip --}}
                            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Revenue') }}</div>
                                    <div class="font-serif text-[26px] leading-none mt-2 tabular-nums">{{ $currency }}
                                        {{ number_format($a['revenue_total'], 0) }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Orders') }}</div>
                                    <div class="font-serif text-[26px] leading-none mt-2 tabular-nums">
                                        {{ number_format($a['orders_total']) }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Avg order') }}</div>
                                    <div class="font-serif text-[26px] leading-none mt-2 tabular-nums">{{ $currency }}
                                        {{ number_format($a['aov'], 0) }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Messages sent') }}</div>
                                    <div class="font-serif text-[26px] leading-none mt-2 tabular-nums">
                                        {{ number_format($a['messages_sent']) }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Offers sent') }}</div>
                                    <div class="font-serif text-[26px] leading-none mt-2 tabular-nums">
                                        {{ number_format($a['offers_sent']) }}</div>
                                </div>
                            </div>

                            {{-- Impact / ROI — real attributable numbers --}}
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                <div class="bg-wa-bubble/30 border border-wa-green/30 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('COD revenue protected') }}</div>
                                    <div class="font-serif text-[24px] leading-none mt-2 tabular-nums text-wa-deep">
                                        {{ $currency }} {{ number_format($a['cod_protected'] ?? 0, 0) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">{{ number_format($a['cod_confirmed'] ?? 0) }}
                                        {{ __('COD orders confirmed') }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('RTO avoided') }}</div>
                                    <div class="font-serif text-[24px] leading-none mt-2 tabular-nums">{{ $currency }}
                                        {{ number_format($a['rto_avoided'] ?? 0, 0) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">{{ number_format($a['cod_cancelled'] ?? 0) }}
                                        {{ __('fake/COD orders stopped') }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Recovery messages') }}</div>
                                    <div class="font-serif text-[24px] leading-none mt-2 tabular-nums">
                                        {{ number_format($a['recovery_sends'] ?? 0) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">{{ __('cart / offer / win-back / stock') }}
                                    </div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Avg order value') }}</div>
                                    <div class="font-serif text-[24px] leading-none mt-2 tabular-nums">{{ $currency }}
                                        {{ number_format($a['aov'] ?? 0, 0) }}</div>
                                </div>
                            </div>

                            {{-- Revenue trend (dependency-free CSS bars) --}}
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                                <h3 class="font-serif text-[18px] leading-tight mb-4">{{ __('Revenue · last 14 days') }}</h3>
                                <div class="flex items-end gap-1.5 h-44">
                                    @foreach ($a['trend'] as $t)
                                        @php $h = (int) max(3, round(($t['value'] / $a['trend_max']) * 100)); @endphp
                                        <div class="flex-1 flex flex-col items-center justify-end gap-1 group">
                                            <div class="w-full rounded-t bg-wa-deep/80 group-hover:bg-wa-deep transition-all"
                                                style="height: {{ $h }}%"
                                                title="{{ $currency }} {{ number_format($t['value'], 2) }}"></div>
                                            <span
                                                class="font-mono text-[8.5px] text-ink-400 rotate-0 truncate w-full text-center">{{ $t['label'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Automation performance --}}
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                                <div class="px-5 py-3 border-b border-paper-200">
                                    <h3 class="font-serif text-[18px] leading-tight">{{ __('Automation performance') }}</h3>
                                </div>
                                <div class="overflow-x-auto">
                                <table class="w-full text-[12.5px]">
                                    <thead
                                        class="bg-paper-50 text-left font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                        <tr>
                                            <th class="px-5 py-2.5">{{ __('Event') }}</th>
                                            <th class="px-4 py-2.5 text-right">{{ __('Fired') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-paper-100">
                                        @forelse ($logsByEvent as $event => $n)
                                            <tr class="hover:bg-paper-50">
                                                <td class="px-5 py-2.5 font-mono text-wa-deep">{{ $event }}</td>
                                                <td class="px-4 py-2.5 text-right tabular-nums font-medium">
                                                    {{ number_format($n) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="2" class="px-5 py-8 text-center text-[12px] text-ink-500">
                                                    {{ __('No automation activity yet.') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        @break

                        @case('logs')
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                                <div class="px-5 py-3 border-b border-paper-100 grid grid-cols-2 sm:grid-cols-4 gap-3 text-[12px]">
                                    @foreach (['processed' => 'Processed', 'sent' => 'Sent', 'skipped' => 'Skipped', 'failed' => 'Failed'] as $k => $label)
                                        @php $n = (int) ($logsByStatus[$k] ?? 0); @endphp
                                        <div>
                                            <div class="font-mono text-[10px] uppercase text-ink-500">{{ $label }}
                                            </div>
                                            <div class="font-serif text-[20px] leading-none mt-1 tabular-nums">
                                                {{ number_format($n) }}</div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="overflow-x-auto">
                                <table class="w-full text-[12.5px]">
                                    <thead
                                        class="bg-paper-50 text-left font-mono text-[10.5px] uppercase text-ink-500 tracking-wide">
                                        <tr>
                                            <th class="px-4 py-2.5">{{ __('Event') }}</th>
                                            <th class="px-4 py-2.5">{{ __('Recipient') }}</th>
                                            <th class="px-4 py-2.5">{{ __('Status') }}</th>
                                            <th class="px-4 py-2.5 text-right">{{ __('When') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-paper-100">
                                        @forelse ($recentLogs as $log)
                                            @php
                                                $statusCss = match ($log->status) {
                                                    'processed', 'sent' => 'bg-wa-green/15 text-wa-deep',
                                                    'failed' => 'bg-accent-coral/15 text-accent-coral',
                                                    'skipped' => 'bg-paper-100 text-ink-700',
                                                    default => 'bg-accent-amber/15 text-[#8B5A14]',
                                                };
                                            @endphp
                                            <tr class="hover:bg-paper-50">
                                                <td class="px-4 py-2.5 font-mono">{{ $log->event_type }}</td>
                                                <td class="px-4 py-2.5 font-mono text-[11.5px] text-ink-700">
                                                    {{ $log->recipient ?? '—' }}</td>
                                                <td class="px-4 py-2.5"><span
                                                        class="font-mono text-[10px] uppercase px-2 py-0.5 rounded-full {{ $statusCss }}">{{ $log->status }}</span>
                                                </td>
                                                <td class="px-4 py-2.5 text-right font-mono text-[11px] text-ink-500">
                                                    {{ $log->created_at?->diffForHumans() }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-4 py-8 text-center text-[12px] text-ink-500">
                                                    {{ __('No webhook events have arrived for this store yet.') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        @break

                        @case('settings')
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5 space-y-4">
                                <div>
                                    <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                        {{ __('Store URL') }}</div>
                                    <div class="font-mono text-[13px] mt-1">{{ $integration->store_url }}</div>
                                </div>
                                <div>
                                    <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                        {{ __('Connected at') }}</div>
                                    <div class="text-[13px] mt-1">
                                        {{ $integration->connected_at?->format('M d, Y H:i') ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                        {{ __('Last verified') }}</div>
                                    <div class="text-[13px] mt-1">
                                        {{ $integration->last_verified_at?->diffForHumans() ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                        {{ __('Scopes granted') }}</div>
                                    <div class="font-mono text-[12px] mt-1 text-ink-700 break-all">
                                        {{ $integration->scopes ?: '—' }}</div>
                                </div>
                                <div>
                                    <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                        {{ __('Webhook URL') }}</div>
                                    <div class="font-mono text-[11.5px] mt-1 text-ink-700 break-all">
                                        {{ url('/shopify/webhook/' . $integration->webhook_secret) }}</div>
                                </div>
                                @php $webhookCount = is_array($integration->metadata['webhook_ids'] ?? null) ? count($integration->metadata['webhook_ids']) : 0; @endphp
                                <div>
                                    <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                        {{ __('Registered webhooks') }}</div>
                                    <div class="text-[13px] mt-1">{{ $webhookCount }} active
                                        subscription{{ $webhookCount === 1 ? '' : 's' }}</div>
                                </div>
                            </div>
                        @break

                    @endswitch

                </section>
            </div>
        </main>

        <script>
            (function() {
                const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

                const syncBtn = document.getElementById('shopify-sync-btn');
                if (syncBtn) {
                    syncBtn.addEventListener('click', async () => {
                        const id = syncBtn.dataset.id;
                        syncBtn.disabled = true;
                        syncBtn.querySelector('span').textContent = 'Syncing…';
                        try {
                            const r = await fetch(`/shopify/${id}/sync`, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': CSRF,
                                    'Accept': 'application/json'
                                },
                            });
                            const data = await r.json();
                            if (data.ok) location.reload();
                            else {
                                alert(data.message || 'Sync failed.');
                                syncBtn.disabled = false;
                                syncBtn.querySelector('span').textContent = 'Sync now';
                            }
                        } catch (e) {
                            alert('Sync request failed.');
                            syncBtn.disabled = false;
                            syncBtn.querySelector('span').textContent = 'Sync now';
                        }
                    });
                }

                const form = document.getElementById('shopify-events-form');
                if (form) {
                    // Show the admin-number field only when "Admin only" / "Both" is picked.
                    form.querySelectorAll('[data-shopify-sendto]').forEach((sel) => {
                        const adminInput = sel.parentElement.querySelector('[data-shopify-adminnum]');
                        if (!adminInput) return;
                        sel.addEventListener('change', () => {
                            adminInput.classList.toggle('hidden', !['admin', 'both'].includes(sel.value));
                        });
                    });

                    // Per-event variable mapping. When a template with positional
                    // {{ 1 }}/{{ 2 }}… params is chosen, render one order-field picker per
                    // param so the merchant decides which order field fills each slot.
                    const TPL_PARAMS = @json($templateParamCounts ?? []);
                    const VAR_FIELDS = [
                        ['', '— blank —'],
                        ['name', 'Customer name'],
                        ['first_name', 'First name'],
                        ['order_name', 'Order # (e.g. #1001)'],
                        ['order_number', 'Order number'],
                        ['total', 'Total + currency'],
                        ['total_price', 'Total (amount only)'],
                        ['currency', 'Currency'],
                        ['email', 'Customer email'],
                        ['store_name', 'Store name'],
                        ['financial_status', 'Payment status'],
                        ['fulfillment_status', 'Fulfilment status'],
                    ];
                    const optionsHtml = (selected) => VAR_FIELDS.map(([v, l]) =>
                        `<option value="${v}" ${v === selected ? 'selected' : ''}>${l}</option>`).join('');

                    const renderVarMap = (topic) => {
                        const sel = form.querySelector(`[data-tpl-select][data-topic="${topic}"]`);
                        const row = form.querySelector(`[data-shopify-varmap-row][data-topic="${topic}"]`);
                        if (!sel || !row) return;
                        const count = TPL_PARAMS[sel.value] || 0;
                        if (count < 1) {
                            row.classList.add('hidden');
                            row.innerHTML = '';
                            return;
                        }
                        let saved = [];
                        try {
                            saved = JSON.parse(row.dataset.saved || '[]') || [];
                        } catch (e) {}
                        const pickers = [];
                        const lb = '{' + '{',
                            rb = '}' + '}'; // build literal braces without tripping Blade
                        for (let i = 0; i < count; i++) {
                            pickers.push(
                                `<label class="block">
 <span class="font-mono text-[9.5px] uppercase text-ink-500 tracking-wide">{{ __('Param') }} ${lb}${i + 1}${rb}</span>
 <select name="events[${topic}][var_map][]" class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">
 ${optionsHtml(saved[i] || '')}
 </select>
 </label>`);
                        }
                        row.innerHTML =
                            `<div class="rounded-xl border border-paper-200 bg-paper-50/50 p-3">
 <div class="font-mono text-[9.5px] uppercase text-ink-500 tracking-wide mb-2">{{ __('Map template variables → order fields') }}</div>
 <div class="grid grid-cols-2 md:grid-cols-3 gap-3">${pickers.join('')}</div>
 </div>`;
                        row.classList.remove('hidden');
                    };

                    form.querySelectorAll('[data-tpl-select]').forEach((sel) => {
                        renderVarMap(sel.dataset.topic);
                        sel.addEventListener('change', () => renderVarMap(sel.dataset.topic));
                    });

                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const status = document.getElementById('shopify-events-status');
                        status.textContent = 'Saving…';
                        const fd = new FormData(form);
                        const payload = {
                            events: {}
                        };
                        for (const [k, v] of fd.entries()) {
                            const m = k.match(/^events\[([^\]]+)\]\[([^\]]+)\](\[\])?$/);
                            if (!m) continue;
                            const [, topic, field, isArr] = m;
                            payload.events[topic] = payload.events[topic] || {};
                            if (isArr) {
                                payload.events[topic][field] = payload.events[topic][field] || [];
                                payload.events[topic][field].push(v);
                            } else {
                                payload.events[topic][field] = (field === 'is_active') ? (v === '1') : v;
                            }
                        }
                        try {
                            const r = await fetch(`/shopify/${form.dataset.id}/events`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': CSRF,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify(payload),
                            });
                            const data = await r.json();
                            status.textContent = data.ok ? '✓ Saved' : 'Save failed';
                            if (data.ok) setTimeout(() => {
                                status.textContent = '';
                            }, 1800);
                        } catch (e) {
                            status.textContent = 'Save failed';
                        }
                    });
                }

                // ---- Products tab: search + brand filter + send/offer actions ----
                const prodSearch = document.getElementById('shopify-prod-search');
                const allCards = () => Array.from(document.querySelectorAll('[data-prod]'));
                let activeBrand = '';
                const applyProdFilter = () => {
                    const q = (prodSearch?.value || '').trim().toLowerCase();
                    allCards().forEach((c) => {
                        const okText = !q || (c.dataset.title || '').includes(q);
                        const okBrand = !activeBrand || (c.dataset.brand || '') === activeBrand;
                        c.style.display = (okText && okBrand) ? '' : 'none';
                    });
                };
                if (prodSearch) prodSearch.addEventListener('input', applyProdFilter);
                document.querySelectorAll('[data-shopify-brand]').forEach((b) => {
                    b.addEventListener('click', () => {
                        const v = (b.dataset.shopifyBrand || '').toLowerCase();
                        activeBrand = (activeBrand === v) ? '' : v;
                        document.querySelectorAll('[data-shopify-brand]').forEach((x) => x.classList.remove(
                            'border-wa-deep', 'bg-wa-deep/8'));
                        if (activeBrand) b.classList.add('border-wa-deep', 'bg-wa-deep/8');
                        applyProdFilter();
                    });
                });
                // Send / Offer → carry the product into the catalog-send composer.
                const goWith = (data, intent) => {
                    try {
                        const p = JSON.parse(data);
                        sessionStorage.setItem('shopify_pick', JSON.stringify({
                            ...p,
                            intent
                        }));
                    } catch (e) {}
                    window.location.href = @json(url('/catalog/send')) + '?product=' + encodeURIComponent(JSON.parse(
                        data).id || '');
                };
                document.querySelectorAll('[data-shopify-send]').forEach((btn) =>
                    btn.addEventListener('click', () => goWith(btn.dataset.shopifySend, 'send')));
                document.querySelectorAll('[data-shopify-offer]').forEach((btn) =>
                    btn.addEventListener('click', () => {
                        try {
                            sessionStorage.setItem('shopify_pick', btn.dataset.shopifyOffer);
                        } catch (e) {}
                        window.location.href = '?tab=offers';
                    }));

                // ---- Offer composer: selected count + send ----
                const offerForm = document.getElementById('shopify-offer-form');
                if (offerForm) {
                    const pickedEl = document.getElementById('offer-picked');
                    const recount = () => {
                        if (pickedEl) pickedEl.textContent = offerForm.querySelectorAll('[data-offer-product]:checked')
                            .length;
                    };
                    offerForm.querySelectorAll('[data-offer-product]').forEach((cb) => cb.addEventListener('change',
                        recount));
                    offerForm.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const status = document.getElementById('offer-status');
                        const btn = document.getElementById('offer-send-btn');
                        const fd = new FormData(offerForm);
                        if (!fd.get('template_id')) {
                            status.textContent = 'Pick a template first.';
                            return;
                        }
                        if (!fd.get('group_id')) {
                            status.textContent = 'Pick a segment first.';
                            return;
                        }
                        btn.disabled = true;
                        status.textContent = 'Sending…';
                        const payload = {
                            template_id: fd.get('template_id'),
                            group_id: fd.get('group_id'),
                            coupon_code: fd.get('coupon_code') || '',
                            product_ids: fd.getAll('product_ids[]'),
                        };
                        try {
                            const r = await fetch(`/shopify/${offerForm.dataset.id}/offer`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': CSRF,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify(payload),
                            });
                            const d = await r.json();
                            status.textContent = d.ok ?
                                `✓ Sent to ${d.sent}/${d.total}${d.failed ? ' · ' + d.failed + ' failed' : ''}` :
                                (d.message || 'Send failed');
                        } catch (e) {
                            status.textContent = 'Send failed';
                        }
                        btn.disabled = false;
                    });
                }

                // ---- Win-back composer ----
                const wbForm = document.getElementById('shopify-winback-form');
                if (wbForm) {
                    wbForm.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const status = document.getElementById('winback-status');
                        const btn = document.getElementById('winback-send-btn');
                        const fd = new FormData(wbForm);
                        if (!fd.get('template_id')) {
                            status.textContent = 'Pick a template first.';
                            return;
                        }
                        if (!confirm('Send this broadcast to everyone in the segment?')) return;
                        btn.disabled = true;
                        status.textContent = 'Sending…';
                        try {
                            const r = await fetch(`/shopify/${wbForm.dataset.id}/winback`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': CSRF,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({
                                    template_id: fd.get('template_id'),
                                    days: fd.get('days'),
                                    min_orders: fd.get('min_orders'),
                                    min_spent: fd.get('min_spent') || 0,
                                    coupon_code: fd.get('coupon_code') || ''
                                }),
                            });
                            const d = await r.json();
                            status.textContent = d.ok ?
                                `✓ Sent to ${d.sent}/${d.total} lapsed${d.failed ? ' · ' + d.failed + ' failed' : ''}` :
                                (d.message || 'Send failed');
                        } catch (e) {
                            status.textContent = 'Send failed';
                        }
                        btn.disabled = false;
                    });
                }
            })();
        </script>

    @endif

</x-layouts.user>
