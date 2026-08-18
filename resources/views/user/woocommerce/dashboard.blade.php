<x-layouts.user :title="__('WooCommerce')" nav-key="more" page="user-woocommerce-dashboard">
    @php
        $isConnected = $integration && $integration->isConnected();
        $tabs = [
            'overview' => 'Overview',
            'orders' => 'Orders',
            'products' => 'Products',
            'offers' => 'Offers',
            'customers' => 'Customers',
            'events' => 'Automations',
            'analytics' => 'Analytics',
            'logs' => 'Activity',
            'settings' => 'Settings',
        ];
        $activeTab = array_key_exists($activeTab ?? 'overview', $tabs) ? $activeTab : 'overview';
        $currency = $currency ?? ($integration?->store_currency ?? 'USD');

        // Friendly, grouped automation map — each key is a saveEvents event_type.
        $AUTO = [
            'Order lifecycle' => [
                'order.created' => ['Order placed', 'Sent the moment a new order comes in.'],
                'order.processing' => ['Order confirmed', 'Payment received — the order is being prepared.'],
                'order.completed' => ['Order delivered', 'Order marked complete in WooCommerce.'],
                'order.on-hold' => ['Order on hold', 'Awaiting payment or stock — keep the buyer informed.'],
                'order.cancelled' => ['Order cancelled', 'The order was cancelled.'],
                'order.refunded' => ['Order refunded', 'A refund was issued.'],
            ],
            'Revenue recovery' => [
                'cod/confirm' => [
                    'COD double-confirmation',
                    'Ask cash-on-delivery buyers to confirm Yes / No before you ship — the #1 way to cut RTO.',
                ],
                'cod/prepaid' => [
                    'COD → Prepaid nudge',
                    'Offer COD buyers a pay-online link (template can carry {{ order_url }} + an incentive).',
                ],
                'checkout.created' => [
                    'Abandoned cart · step 1',
                    'First nudge when checkout starts but no order lands. Needs the ' .
                    brand_name() .
                    ' WooCommerce plugin.',
                ],
                'cart/step2' => ['Abandoned cart · step 2', 'Scheduled follow-up after the delay below.'],
                'cart/step3' => ['Abandoned cart · step 3', 'Final reminder — add a discount in the template.'],
                'stock/back' => [
                    'Back in stock',
                    'Message everyone on the waitlist when a sold-out product is restocked.',
                ],
                'subscription.payment_failed' => [
                    'Subscription dunning',
                    'When a subscription renewal payment fails, send a one-tap "update payment" link. Needs the ' .
                    brand_name() .
                    ' WooCommerce plugin.',
                ],
            ],
            'Lifecycle marketing' => [
                'customer.created' => [
                    'Welcome new customer',
                    'Greet a customer the first time they register on the store.',
                ],
                'review/reward' => [
                    'Review-gated coupon',
                    'When a recent buyer replies with a positive rating (4–5 / "great"), auto-send this reward template — put a coupon in it. Pair it with an "Order delivered" template that asks them to rate.',
                ],
            ],
        ];
        // Variable names available to order templates (for the advanced var-map hint).
        $varHint =
            'name, first_name, order_number, order_name, total, currency, order_url, tracking_number, tracking_url';
    @endphp

    <style>
        .woo-accent {
            color: #7F54B3;
        }

        .woo-bg {
            background-color: #7F54B3;
        }

        .woo-bg:hover {
            background-color: #6B469A;
        }
    </style>

    @if (!$isConnected)
        {{-- ════════════════════════════════════════════════════════════
 NOT CONNECTED — /connect-style sidebar + form.
 Matches the Shopify connect screen visual pattern.
 ════════════════════════════════════════════════════════════ --}}

        <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
            <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

                <!-- ===== LEFT RAIL ===== -->
                <aside class="space-y-3">
                    <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                        <div class="w-12 h-12 rounded-xl mb-3 grid place-items-center" style="background:#F3ECFA">
                            <svg viewBox="0 0 32 32" class="w-8 h-8">
                                <path fill="#7F54B3"
                                    d="M2 6.5C2 5.1 3.1 4 4.5 4h23A2.5 2.5 0 0 1 30 6.5v13a2.5 2.5 0 0 1-2.5 2.5H17l-6 5 1.5-5H4.5A2.5 2.5 0 0 1 2 19.5v-13z" />
                                <path fill="#fff"
                                    d="M5.4 9.5c.4-.1.8 0 1 .3.2.3.2.7.1 1.1-.5 2-.9 3.7-1.1 5l1.3-2.5c.3-.5.6-.8.9-.8.5-.1.8.2.9.9.1 1 .2 1.8.4 2.6.2-1.7.5-3 .9-3.7.1-.3.4-.5.7-.5.5 0 .9.4.9.9 0 .2 0 .4-.1.5-.3.5-.5 1.4-.7 2.6-.2 1.2-.3 2.1-.3 2.7 0 .5-.2.7-.6.7-.3 0-.5-.1-.8-.4-.8-.8-1.5-2-2-3.6-.6 1.2-1 2.1-1.3 2.7-.5 1-1 1.5-1.4 1.6-.3 0-.5-.2-.7-.7-.5-1.4-1-4-1.6-7.9 0-.4.2-.8.6-.9z" />
                            </svg>
                        </div>
                        <div class="font-serif text-[18px] leading-tight">{{ __('WooCommerce') }}</div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mt-1">
                            {{ __('Integration') }}</div>
                        <div
                            class="mt-3 inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-mono bg-paper-50 border border-paper-200 text-ink-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-paper-200"></span>
                            Not connected
                        </div>
                    </div>

                    <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                            {{ __('Setup steps') }}</div>
                        <ol class="px-1 space-y-0.5">
                            <li
                                class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] {{ $appEnabled ? 'text-ink-700' : 'text-ink-500' }}">
                                <span
                                    class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono {{ $appEnabled ? 'bg-wa-mint text-wa-deep' : 'bg-paper-100 text-ink-500' }}">{{ $appEnabled ? '✓' : '1' }}</span>
                                {{ __('Admin enables WooCommerce') }}
                            </li>
                            <li
                                class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] {{ $appEnabled ? 'bg-wa-deep/8 text-wa-deep font-semibold' : 'text-ink-500' }}">
                                <span
                                    class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono {{ $appEnabled ? 'bg-wa-deep text-paper-0' : 'bg-paper-100 text-ink-500' }}">2</span>
                                {{ __('Generate API keys in WC admin') }}
                            </li>
                            <li class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-500">
                                <span
                                    class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-paper-100 text-ink-500">3</span>
                                {{ __('Paste store URL + keys below') }}
                            </li>
                            <li class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-500">
                                <span
                                    class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-paper-100 text-ink-500">4</span>
                                {{ __('Map events → WhatsApp templates') }}
                            </li>
                        </ol>
                    </div>

                    <div
                        class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                        <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-wa-green"></span>
                            Need help?
                        </div>
                        Webhooks auto-install after you connect — no manual steps inside WC. Stuck on the keys? <a
                            href="{{ url('/support') }}"
                            class="text-wa-deep font-semibold underline">{{ __('Contact support') }}</a>.
                    </div>
                </aside>

                <!-- ===== MAIN ===== -->
                <section class="space-y-5">

                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                                <a href="{{ url('/integrations') }}"
                                    class="hover:text-wa-deep">{{ __('Integrations') }}</a>
                                <span class="mx-1.5 text-ink-500/60">/</span>
                                <span>{{ __('WooCommerce') }}</span>
                            </div>
                            <h1 class="font-serif font-normal tracking-tight text-[30px] sm:text-[36px] lg:text-[44px] leading-none">
                                {{ __('Connect') }} <span class="italic woo-accent">{{ __('WooCommerce') }}</span>
                            </h1>
                            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                                {{ __('Pull WooCommerce orders, refunds, products, and customer events into chat threads. Confirm orders, ship updates, and recover carts on WhatsApp without leaving :app.', ['app' => brand_name()]) }}
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

                    @if (!$appEnabled)
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
                                <div class="font-serif text-[22px] leading-tight">
                                    {{ __("WooCommerce isn't enabled yet") }}</div>
                                <p class="text-[12.5px] text-ink-600 mt-1.5 max-w-2xl">
                                    {{ __('An administrator must enable the WooCommerce integration before any workspace can connect a store.') }}
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="grid grid-cols-1 lg:grid-cols-[1fr_330px] gap-5 items-start">

                            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Step 2') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight mt-0.5 mb-4">
                                    {{ __('Store credentials') }}</h2>

                                <form id="wc-connect-form" class="space-y-4">
                                    @csrf
                                    <div>
                                        <label
                                            class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Store URL') }}
                                            <span class="text-accent-coral">*</span></label>
                                        <input type="url" name="store_url" required
                                            placeholder="https://your-store.com"
                                            class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                        <p class="text-[10.5px] text-ink-500 mt-1">
                                            {{ __("HTTPS required for Basic Auth. We'll detect WP REST automatically.") }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Consumer Key') }}
                                            <span class="text-accent-coral">*</span></label>
                                        <input type="text" name="consumer_key" required
                                            placeholder="{{ __('ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx') }}"
                                            class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Consumer Secret') }}
                                            <span class="text-accent-coral">*</span></label>
                                        <input type="password" name="consumer_secret" required
                                            placeholder="{{ __('cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx') }}"
                                            class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                        <p class="text-[10.5px] text-ink-500 mt-1">
                                            {{ __("Stored encrypted at rest. Only used to call your store's REST API.") }}
                                        </p>
                                    </div>

                                    <div id="wc-test-result" class="hidden text-[12px] px-3 py-2 rounded-lg border">
                                    </div>

                                    <div class="rounded-lg bg-paper-50/60 border border-paper-200 p-3">
                                        <div
                                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1">
                                            {{ __('Webhook URL · auto-configured after connect') }}</div>
                                        <div class="font-mono text-[11px] text-ink-700 break-all">
                                            {{ url('/woocommerce/webhook/{secret}') }}</div>
                                    </div>

                                    <div class="flex items-center gap-2 pt-2">
                                        <button type="button" id="wc-test-btn"
                                            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <path
                                                    d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                                            </svg>
                                            Test connection
                                        </button>
                                        <button type="submit"
                                            class="px-4 py-2 rounded-full woo-bg text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5 ml-auto">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                stroke="currentColor" stroke-width="1.7">
                                                <path d="M2 8l5 5 7-9" />
                                            </svg>
                                            Connect store
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <aside class="space-y-4">
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('How to generate keys') }}</div>
                                    <h3 class="font-serif text-[18px] leading-tight mt-0.5 mb-3">
                                        {{ __('In your WooCommerce admin') }}</h3>
                                    <ol class="space-y-3 text-[12.5px] text-ink-700">
                                        <li class="flex items-start gap-2"><span
                                                class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center font-mono text-[10px] shrink-0 mt-0.5">1</span><span>{{ __('Open') }}
                                                <span
                                                    class="font-mono text-ink-900">{{ __('WooCommerce → Settings → Advanced → REST API') }}</span>.</span>
                                        </li>
                                        <li class="flex items-start gap-2"><span
                                                class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center font-mono text-[10px] shrink-0 mt-0.5">2</span><span>{{ __('Click') }}
                                                <span class="font-mono text-ink-900">{{ __('Add key') }}</span>.
                                                {{ __('Give it a description like ":app".', ['app' => brand_name()]) }}</span>
                                        </li>
                                        <li class="flex items-start gap-2"><span
                                                class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center font-mono text-[10px] shrink-0 mt-0.5">3</span><span>{{ __('Pick a user (admin works) and set') }}
                                                <span
                                                    class="font-mono text-ink-900">{{ __('Permissions = Read/Write') }}</span>.</span>
                                        </li>
                                        <li class="flex items-start gap-2"><span
                                                class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center font-mono text-[10px] shrink-0 mt-0.5">4</span><span>{{ __('Click') }}
                                                <span
                                                    class="font-mono text-ink-900">{{ __('Generate API key') }}</span>.
                                                Copy <span
                                                    class="text-wa-deep font-semibold">{{ __('both') }}</span> keys
                                                — the secret is only shown once.</span></li>
                                        <li class="flex items-start gap-2"><span
                                                class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center font-mono text-[10px] shrink-0 mt-0.5">5</span><span>{{ __('Paste them in the form on the left and click') }}
                                                <span
                                                    class="font-mono text-ink-900">{{ __('Test connection') }}</span>.</span>
                                        </li>
                                    </ol>
                                </div>
                                <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                                    <div class="font-semibold text-[12.5px]">{{ __('HTTPS only') }}</div>
                                    <p class="text-[11.5px] text-ink-600 mt-1">
                                        {{ __("Basic Auth requires HTTPS. If your store is HTTP-only, install an SSL certificate first (Let's Encrypt is free).") }}
                                    </p>
                                </div>
                            </aside>
                        </div>
                    @endif
                </section>
            </div>
        </main>

        <script>
            (function() {
                const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const form = document.getElementById('wc-connect-form');
                const result = document.getElementById('wc-test-result');
                if (!form) return;

                function show(ok, msg) {
                    result.classList.remove('hidden');
                    result.className = 'text-[12px] px-3 py-2 rounded-lg border ' + (ok ?
                        'bg-wa-mint border-wa-green/30 text-wa-deep' :
                        'bg-accent-coral/10 border-accent-coral/40 text-[#A1431F]');
                    result.textContent = msg;
                }

                function payload() {
                    return new URLSearchParams(new FormData(form));
                }

                document.getElementById('wc-test-btn').addEventListener('click', async () => {
                    show(true, 'Testing…');
                    try {
                        const r = await fetch('/woocommerce/test', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': CSRF,
                                'Accept': 'application/json'
                            },
                            body: payload(),
                        });
                        const data = await r.json();
                        show(!!data.ok, data.message || (data.ok ? 'Connected.' : 'Test failed.'));
                    } catch (e) {
                        show(false, 'Network error.');
                    }
                });

                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    show(true, 'Connecting…');
                    try {
                        const r = await fetch('/woocommerce/connect', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': CSRF,
                                'Accept': 'application/json'
                            },
                            body: payload(),
                        });
                        const data = await r.json();
                        if (data.ok && data.redirect) {
                            location.href = data.redirect;
                            return;
                        }
                        show(false, data.message || 'Connection failed.');
                    } catch (e) {
                        show(false, 'Network error.');
                    }
                });
            })();
        </script>
    @else
        {{-- ════════════════════════════════════════════════════════════
 CONNECTED — preserve the existing sidebar+main+tabs layout.
 The visual chrome is identical to the previous static page;
 only the numbers and tab content are wired to real data now.
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

        <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
            <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

                <!-- ===== SIDEBAR ===== -->
                <aside class="space-y-3">
                    <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                        <div class="flex items-start justify-between gap-2">
                            <span class="w-11 h-11 rounded-xl shrink-0 grid place-items-center"
                                style="background:#F3ECFA">
                                <svg viewBox="0 0 32 32" class="w-7 h-7">
                                    <path fill="#7F54B3"
                                        d="M2 6.5C2 5.1 3.1 4 4.5 4h23A2.5 2.5 0 0 1 30 6.5v13a2.5 2.5 0 0 1-2.5 2.5H17l-6 5 1.5-5H4.5A2.5 2.5 0 0 1 2 19.5v-13z" />
                                    <path fill="#fff"
                                        d="M5.4 9.5c.4-.1.8 0 1 .3.2.3.2.7.1 1.1-.5 2-.9 3.7-1.1 5l1.3-2.5c.3-.5.6-.8.9-.8.5-.1.8.2.9.9.1 1 .2 1.8.4 2.6.2-1.7.5-3 .9-3.7.1-.3.4-.5.7-.5.5 0 .9.4.9.9 0 .2 0 .4-.1.5-.3.5-.5 1.4-.7 2.6-.2 1.2-.3 2.1-.3 2.7 0 .5-.2.7-.6.7-.3 0-.5-.1-.8-.4-.8-.8-1.5-2-2-3.6-.6 1.2-1 2.1-1.3 2.7-.5 1-1 1.5-1.4 1.6-.3 0-.5-.2-.7-.7-.5-1.4-1-4-1.6-7.9 0-.4.2-.8.6-.9z" />
                                </svg>
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono bg-wa-mint text-wa-deep border border-wa-green/40">
                                <span
                                    class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ ucfirst($integration->status) }}
                            </span>
                        </div>
                        <div class="font-serif text-[18px] leading-tight mt-3">
                            {{ $integration->store_name ?: parse_url($integration->store_url, PHP_URL_HOST) }}</div>
                        <div class="font-mono text-[10.5px] text-ink-500 mt-0.5 truncate">
                            {{ parse_url($integration->store_url, PHP_URL_HOST) }} · WooCommerce</div>
                        @if ($integration->store_version)
                            <div class="mt-2 font-mono text-[10px] text-ink-500">WC v{{ $integration->store_version }}
                            </div>
                        @endif
                    </div>

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
                        <form method="POST" action="{{ url('/woocommerce/' . $integration->id . '/disconnect') }}"
                            onsubmit="return confirm('Disconnect WooCommerce? This removes registered webhooks.');">
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
                            Trigger an order in WooCommerce and it'll appear here.
                        @endif
                    </div>
                </aside>

                <!-- ===== MAIN ===== -->
                <section class="space-y-5">

                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                                <a href="{{ url('/integrations') }}"
                                    class="hover:text-wa-deep">{{ __('Integrations') }}</a>
                                <span class="mx-1.5 text-ink-500/60">/</span>
                                <span>{{ __('WooCommerce') }}</span>
                                <span class="mx-1.5 text-ink-500/60">/</span>
                                <span>{{ $tabs[$activeTab] }}</span>
                            </div>
                            <h1 class="font-serif font-normal tracking-tight text-[30px] sm:text-[36px] lg:text-[44px] leading-none">
                                {{ $integration->store_name ?: parse_url($integration->store_url, PHP_URL_HOST) }}
                                <span class="italic woo-accent">{{ strtolower($tabs[$activeTab]) }}</span></h1>
                            <p class="text-[13px] text-ink-600 mt-2">
                                @switch($activeTab)
                                    @case('overview')
                                        Live snapshot pulled from your WooCommerce REST API.
                                    @break

                                    @case('orders')
                                        Last 10 orders fetched from the store.
                                    @break

                                    @case('products')
                                        Most recent products in the catalog.
                                    @break

                                    @case('offers')
                                        Broadcast a product offer or win back lapsed customers.
                                    @break

                                    @case('customers')
                                        Customer accounts ordered by recency.
                                    @break

                                    @case('events')
                                        Turn order, COD, cart & stock automations on or off.
                                    @break

                                    @case('analytics')
                                        Revenue, message volume & COD impact for this store.
                                    @break

                                    @case('logs')
                                        Webhook delivery log for this store.
                                    @break

                                    @case('settings')
                                        Connection details — re-verify or disconnect.
                                    @break
                                @endswitch
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button id="wc-sync-btn" data-id="{{ $integration->id }}"
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
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Revenue · 30d') }}</div>
                                    <div class="font-serif text-[30px] leading-none mt-2 tabular-nums">{{ $currency }}
                                        {{ number_format($revenue30d, 2) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">{{ __('from WC sales report') }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Orders · 30d') }}</div>
                                    <div class="font-serif text-[30px] leading-none mt-2 tabular-nums">
                                        {{ number_format($orders30d) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">{{ number_format($counts['orders'] ?? 0) }}
                                        {{ __('total all-time') }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Customers') }}</div>
                                    <div class="font-serif text-[30px] leading-none mt-2 tabular-nums">
                                        {{ number_format($counts['customers'] ?? 0) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">{{ number_format($customers30d) }} new in 30d
                                    </div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Items sold · 30d') }}</div>
                                    <div class="font-serif text-[30px] leading-none mt-2 tabular-nums">
                                        {{ number_format($itemsSold30d) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">{{ $activeEvents }}
                                        {{ __('active automations') }}</div>
                                </div>
                            </div>

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
                                                $b = $o['billing'] ?? [];
                                                $name =
                                                    trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? '')) ?:
                                                    $b['email'] ?? '—';
                                            @endphp
                                            <div
                                                class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg hover:bg-paper-50">
                                                <div class="min-w-0">
                                                    <div class="text-[12.5px] font-medium truncate">
                                                        #{{ $o['number'] ?? ($o['id'] ?? '?') }}</div>
                                                    <div class="font-mono text-[10.5px] text-ink-500 truncate">
                                                        {{ $name }}</div>
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <div class="text-[12.5px] font-semibold tabular-nums">
                                                        {{ $o['currency'] ?? $currency }}
                                                        {{ number_format((float) ($o['total'] ?? 0), 2) }}</div>
                                                    <div class="font-mono text-[10px] text-ink-500">
                                                        {{ ucfirst((string) ($o['status'] ?? '—')) }}</div>
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
                                                $css = match ($log->status) {
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
                                                    class="font-mono text-[10px] uppercase px-2 py-0.5 rounded-full {{ $css }}">{{ $log->status }}</span>
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
                                                $b = $o['billing'] ?? [];
                                                $name =
                                                    trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? '')) ?:
                                                    $b['email'] ?? '—';
                                            @endphp
                                            <tr class="hover:bg-paper-50">
                                                <td class="px-4 py-2.5 font-mono text-ink-700">
                                                    #{{ $o['number'] ?? ($o['id'] ?? '?') }}</td>
                                                <td class="px-4 py-2.5">{{ $name }}</td>
                                                <td class="px-4 py-2.5"><span
                                                        class="font-mono text-[10px] px-2 py-0.5 rounded-full bg-paper-100 text-ink-700">{{ ucfirst((string) ($o['status'] ?? '—')) }}</span>
                                                </td>
                                                <td class="px-4 py-2.5 text-right tabular-nums font-medium">
                                                    {{ $o['currency'] ?? $currency }}
                                                    {{ number_format((float) ($o['total'] ?? 0), 2) }}</td>
                                                <td class="px-4 py-2.5 text-right font-mono text-[11px] text-ink-500">
                                                    {{ \Carbon\Carbon::parse($o['date_created'] ?? null)->diffForHumans() }}
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
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @forelse ($products as $p)
                                    @php $img = $p['images'][0]['src'] ?? null; @endphp
                                    <div class="bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                                        @if ($img)
                                            <img src="{{ $img }}" alt="" class="w-full h-32 object-cover">
                                        @else
                                            <div
                                                class="w-full h-32 bg-paper-100 grid place-items-center font-mono text-[10px] text-ink-500">
                                                {{ __('no image') }}</div>
                                        @endif
                                        <div class="p-3">
                                            <div class="text-[13px] font-medium leading-tight truncate">
                                                {{ $p['name'] ?? '—' }}</div>
                                            <div class="flex items-center justify-between mt-1">
                                                <span
                                                    class="font-mono text-[10.5px] text-ink-500">{{ ucfirst((string) ($p['status'] ?? '—')) }}</span>
                                                @if (isset($p['price']) && $p['price'] !== '')
                                                    <span
                                                        class="text-[12.5px] font-semibold tabular-nums">{{ $currency }}
                                                        {{ number_format((float) $p['price'], 2) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div
                                        class="col-span-full text-[12px] text-ink-500 text-center py-10 bg-paper-0 border border-dashed border-paper-200 rounded-2xl">
                                        {{ __('No products found.') }}</div>
                                @endforelse
                            </div>
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
                                            <th class="px-4 py-2.5 text-right">{{ __('Joined') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-paper-100">
                                        @forelse ($customers as $c)
                                            @php $b = $c['billing'] ?? []; @endphp
                                            <tr class="hover:bg-paper-50">
                                                <td class="px-4 py-2.5">
                                                    {{ trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: '—' }}
                                                </td>
                                                <td class="px-4 py-2.5 font-mono text-[11.5px] text-ink-700">
                                                    {{ $c['email'] ?? '—' }}</td>
                                                <td class="px-4 py-2.5 font-mono text-[11.5px] text-ink-700">
                                                    {{ $b['phone'] ?? '—' }}</td>
                                                <td class="px-4 py-2.5 text-right tabular-nums">
                                                    {{ number_format((int) ($c['orders_count'] ?? 0)) }}</td>
                                                <td class="px-4 py-2.5 text-right font-mono text-[11px] text-ink-500">
                                                    {{ \Carbon\Carbon::parse($c['date_created'] ?? null)->diffForHumans() }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-8 text-center text-[12px] text-ink-500">
                                                    {{ __('No customers yet.') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        @break

                        @case('events')
                            @if ($templates->isEmpty())
                                <div
                                    class="bg-accent-amber/10 border border-accent-amber/40 rounded-xl px-4 py-3 text-[12.5px] text-[#8B5A14] flex items-center gap-2 mb-4">
                                    <svg viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path d="M8 5v3M8 11v.01" />
                                        <circle cx="8" cy="8" r="6" />
                                    </svg>
                                    <span>{{ ($engineIsWaba ?? true) ? __('You have no approved WhatsApp templates yet. Create one in') : __('You have no message templates yet. Create one in') }} <a
                                            href="{{ url('/templates') }}"
                                            class="font-semibold underline">{{ __('Templates') }}</a>
                                        {{ __('to switch an automation on.') }}</span>
                                </div>
                            @endif

                            <form id="wc-events-form" data-id="{{ $integration->id }}" class="space-y-5">
                                @csrf

                                {{-- Send-from picker: which connected number/engine these
                                     automations send from. Also filters the Template dropdowns
                                     below — a WABA account shows only its approved templates;
                                     Unofficial/Twilio shows their own templates. --}}
                                @if (($senders ?? collect())->isNotEmpty())
                                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card px-5 py-4 flex flex-wrap items-center gap-3">
                                        <div class="min-w-0">
                                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Send from') }}</div>
                                            <div class="text-[11.5px] text-ink-500 mt-0.5">{{ __('Which connected number sends these automations.') }}</div>
                                        </div>
                                        <select id="wc-sender" class="ml-auto min-w-[240px] px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                            <option value="" data-engine="" data-account="">{{ __('Auto — any connected number') }}</option>
                                            @foreach ($senders as $s)
                                                <option value="{{ $s['key'] }}"
                                                    data-engine="{{ $s['engine'] }}"
                                                    data-account="{{ $s['engine'] === 'baileys' ? '' : $s['id'] }}"
                                                    @selected(($senderKey ?? '') === $s['key'])>
                                                    {{ $s['descriptor']['label'] }} · {{ $s['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                @foreach ($AUTO as $groupLabel => $cards)
                                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                                        <div
                                            class="px-5 py-3 border-b border-paper-100 bg-paper-50/60 font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                            {{ $groupLabel }}</div>
                                        <div class="divide-y divide-paper-100">
                                            @foreach ($cards as $key => $meta)
                                                @php
                                                    $ev = $eventsByType[$key] ?? null;
                                                    $active = (bool) $ev?->is_active;
                                                    $tplId = $ev?->template_id;
                                                    $sendTo = $ev?->send_to ?? 'customer';
                                                    $delay = $ev?->delay_seconds ?? 0;
                                                    $vmap = is_array($ev?->var_map) ? implode(', ', $ev->var_map) : '';
                                                    $sentCount = (int) ($logsByEvent[$key] ?? 0);
                                                    $isScheduled = in_array(
                                                        $key,
                                                        ['cart/step2', 'cart/step3', 'checkout.created'],
                                                        true,
                                                    );
                                                @endphp
                                                <div class="px-5 py-4">
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1fr_170px_130px_120px_72px] gap-3 items-start">
                                                        <div class="min-w-0">
                                                            <div class="text-[13px] font-semibold text-ink-900">
                                                                {{ $meta[0] }}</div>
                                                            <div class="text-[11.5px] text-ink-500 mt-0.5 leading-snug">
                                                                {{ $meta[1] }}</div>
                                                            <div class="font-mono text-[10px] text-ink-400 mt-1">
                                                                {{ $key }} · {{ $sentCount }}
                                                                {{ __('logged') }}</div>
                                                        </div>
                                                        <label class="block">
                                                            <span
                                                                class="font-mono text-[9.5px] uppercase text-ink-500 tracking-wide">{{ __('Template') }}</span>
                                                            <select name="events[{{ $key }}][template_id]"
                                                                class="wc-tpl-select mt-1 w-full px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">
                                                                <option value="">— {{ __('none') }} —</option>
                                                                @foreach ($templates as $tpl)
                                                                    <option value="{{ $tpl->id }}"
                                                                        data-engine="{{ $tpl->engineKey() }}"
                                                                        data-account="{{ $tpl->provider_config_id }}"
                                                                        @selected($tplId == $tpl->id)>{{ $tpl->template_name }}
                                                                        · {{ strtoupper($tpl->language) }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <label class="block">
                                                            <span
                                                                class="font-mono text-[9.5px] uppercase text-ink-500 tracking-wide">{{ __('Send to') }}</span>
                                                            <select name="events[{{ $key }}][send_to]"
                                                                class="mt-1 w-full px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">
                                                                <option value="customer" @selected($sendTo === 'customer')>
                                                                    {{ __('Customer') }}</option>
                                                                <option value="admin" @selected($sendTo === 'admin')>
                                                                    {{ __('Admin only') }}</option>
                                                                <option value="both" @selected($sendTo === 'both')>
                                                                    {{ __('Both') }}</option>
                                                            </select>
                                                        </label>
                                                        <label class="block">
                                                            <span
                                                                class="font-mono text-[9.5px] uppercase text-ink-500 tracking-wide">{{ $isScheduled ? __('Delay') : __('Delay') }}</span>
                                                            <select name="events[{{ $key }}][delay_seconds]"
                                                                class="mt-1 w-full px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">
                                                                @foreach ([0 => 'Immediate', 1800 => '30 min', 3600 => '1 hr', 10800 => '3 hr', 86400 => '1 day', 172800 => '2 days'] as $sec => $lbl)
                                                                    <option value="{{ $sec }}"
                                                                        @selected((int) $delay === $sec)>{{ $lbl }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <label class="flex flex-col items-end justify-start pt-4">
                                                            <span
                                                                class="font-mono text-[9.5px] uppercase text-ink-500 tracking-wide mb-1">{{ __('On') }}</span>
                                                            <input type="hidden"
                                                                name="events[{{ $key }}][is_active]"
                                                                value="0">
                                                            <input type="checkbox"
                                                                name="events[{{ $key }}][is_active]" value="1"
                                                                @checked($active) class="w-5 h-5 accent-wa-deep">
                                                        </label>
                                                    </div>
                                                    <details class="mt-2">
                                                        <summary
                                                            class="text-[10.5px] font-mono text-ink-500 cursor-pointer hover:text-wa-deep select-none">
                                                            {{ __('Advanced · variable order') }}</summary>
                                                        <div class="mt-1.5">
                                                            <input type="text"
                                                                name="events[{{ $key }}][var_map_csv]"
                                                                value="{{ $vmap }}"
                                                                placeholder="{{ $varHint }}"
                                                                class="w-full px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[11.5px] font-mono focus:outline-none focus:border-wa-deep">
                                                            <p class="text-[10px] text-ink-400 mt-1">
                                                                {{ __('Comma-separated fields mapped in order to your numbered template placeholders. Leave blank for the smart default: name, order, total.') }}
                                                            </p>
                                                        </div>
                                                    </details>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach

                                <div
                                    class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card px-5 py-4 flex items-center justify-between sticky bottom-3">
                                    <span id="wc-events-status" class="text-[11.5px] text-ink-500 font-mono"></span>
                                    <button type="submit"
                                        class="px-5 py-2 rounded-full woo-bg text-paper-0 text-[12.5px] font-semibold inline-flex items-center gap-2">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                            stroke-width="1.8">
                                            <path d="m4 8 3 3 5-6" />
                                        </svg>
                                        {{ __('Save automations') }}
                                    </button>
                                </div>
                            </form>
                        @break

                        @case('offers')
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                {{-- Offer broadcast --}}
                                <form id="wc-offer-form" data-id="{{ $integration->id }}"
                                    class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5 space-y-4">
                                    @csrf
                                    <div>
                                        <h3 class="font-serif text-[20px] leading-tight">{{ __('Product offer broadcast') }}
                                        </h3>
                                        <p class="text-[12px] text-ink-600 mt-1">
                                            {{ __('Send an approved template to every opted-in contact in a segment. Inject a product + coupon.') }}
                                        </p>
                                    </div>
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Template') }}</span>
                                        <select name="template_id" required
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                            <option value="">— {{ __('choose') }} —</option>
                                            @foreach ($templates as $tpl)
                                                <option value="{{ $tpl->id }}">{{ $tpl->template_name }} ·
                                                    {{ strtoupper($tpl->language) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span
                                            class="text-[11.5px] font-semibold text-ink-700">{{ __('Segment (contact group)') }}</span>
                                        <select name="group_id" required
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                            <option value="">— {{ __('choose') }} —</option>
                                            @foreach ($contactGroups as $g)
                                                <option value="{{ $g->id }}">{{ $g->user_group }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Featured product') }}
                                            <span class="text-ink-400 font-normal">({{ __('optional') }})</span></span>
                                        <select name="product_id"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                            <option value="">— {{ __('none') }} —</option>
                                            @foreach ($offerProducts as $p)
                                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Coupon code') }} <span
                                                class="text-ink-400 font-normal">({{ __('optional') }})</span></span>
                                        <input type="text" name="coupon_code" list="wc-coupon-list" placeholder="SAVE10"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] font-mono focus:outline-none focus:border-wa-deep">
                                        <datalist id="wc-coupon-list">
                                            @foreach ($coupons as $cp)
                                                <option value="{{ $cp->code }}">
                                            @endforeach
                                        </datalist>
                                    </label>
                                    <div id="wc-offer-status" class="hidden text-[12px] px-3 py-2 rounded-lg border"></div>
                                    <button type="submit"
                                        class="w-full px-4 py-2.5 rounded-full woo-bg text-paper-0 text-[12.5px] font-semibold">{{ __('Send offer') }}</button>
                                </form>

                                {{-- Smart-segment win-back --}}
                                <form id="wc-winback-form" data-id="{{ $integration->id }}"
                                    class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5 space-y-4">
                                    @csrf
                                    <div>
                                        <h3 class="font-serif text-[20px] leading-tight">{{ __('Smart-segment broadcast') }}
                                        </h3>
                                        <p class="text-[12px] text-ink-600 mt-1">
                                            {{ __('Target by order history — win back lapsed buyers or reward your best customers.') }}
                                        </p>
                                    </div>
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Template') }}</span>
                                        <select name="template_id" required
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                            <option value="">— {{ __('choose') }} —</option>
                                            @foreach ($templates as $tpl)
                                                <option value="{{ $tpl->id }}">{{ $tpl->template_name }} ·
                                                    {{ strtoupper($tpl->language) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <div class="grid grid-cols-3 gap-2">
                                        <label class="block">
                                            <span
                                                class="text-[10.5px] font-semibold text-ink-700">{{ __('Lapsed > days') }}</span>
                                            <input type="number" name="days" value="60" min="0"
                                                max="365"
                                                class="mt-1 w-full px-2.5 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                        </label>
                                        <label class="block">
                                            <span
                                                class="text-[10.5px] font-semibold text-ink-700">{{ __('Min orders') }}</span>
                                            <input type="number" name="min_orders" value="0" min="0"
                                                max="1000"
                                                class="mt-1 w-full px-2.5 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                        </label>
                                        <label class="block">
                                            <span
                                                class="text-[10.5px] font-semibold text-ink-700">{{ __('Min spend') }}</span>
                                            <input type="number" name="min_spent" value="0" min="0"
                                                step="0.01"
                                                class="mt-1 w-full px-2.5 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                        </label>
                                    </div>
                                    <p class="text-[10.5px] text-ink-400">
                                        {{ __('Days = 0 targets any time (pure segment). Spend is in store currency. Opted-out contacts are always skipped.') }}
                                    </p>
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Coupon code') }} <span
                                                class="text-ink-400 font-normal">({{ __('optional') }})</span></span>
                                        <input type="text" name="coupon_code" list="wc-coupon-list"
                                            placeholder="COMEBACK15"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] font-mono focus:outline-none focus:border-wa-deep">
                                    </label>
                                    <div id="wc-winback-status" class="hidden text-[12px] px-3 py-2 rounded-lg border"></div>
                                    <button type="submit"
                                        class="w-full px-4 py-2.5 rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold">{{ __('Send broadcast') }}</button>
                                </form>
                            </div>
                        @break

                        @case('analytics')
                            @php $A = $analytics ?? []; @endphp
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Revenue · mirrored') }}</div>
                                    <div class="font-serif text-[28px] leading-none mt-2 tabular-nums">{{ $currency }}
                                        {{ number_format((float) ($A['revenue_total'] ?? 0), 2) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">
                                        {{ number_format((int) ($A['orders_total'] ?? 0)) }} {{ __('orders synced') }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Avg order value') }}</div>
                                    <div class="font-serif text-[28px] leading-none mt-2 tabular-nums">{{ $currency }}
                                        {{ number_format((float) ($A['aov'] ?? 0), 2) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">{{ __('per order') }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Messages sent') }}</div>
                                    <div class="font-serif text-[28px] leading-none mt-2 tabular-nums">
                                        {{ number_format((int) ($A['messages_sent'] ?? 0)) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">
                                        {{ number_format((int) ($A['offers_sent'] ?? 0)) }} {{ __('offer broadcasts') }}
                                    </div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Recovery sends') }}</div>
                                    <div class="font-serif text-[28px] leading-none mt-2 tabular-nums">
                                        {{ number_format((int) ($A['recovery_sends'] ?? 0)) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">{{ __('COD / cart / stock / offers') }}</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3">
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('COD revenue protected') }}</div>
                                    <div class="font-serif text-[24px] leading-none mt-2 tabular-nums text-wa-deep">
                                        {{ $currency }} {{ number_format((float) ($A['cod_protected'] ?? 0), 2) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">
                                        {{ number_format((int) ($A['cod_confirmed'] ?? 0)) }}
                                        {{ __('COD orders confirmed') }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('RTO avoided') }}</div>
                                    <div class="font-serif text-[24px] leading-none mt-2 tabular-nums text-accent-coral">
                                        {{ $currency }} {{ number_format((float) ($A['rto_avoided'] ?? 0), 2) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">
                                        {{ number_format((int) ($A['cod_cancelled'] ?? 0)) }}
                                        {{ __('would-be RTOs cancelled early') }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Active automations') }}</div>
                                    <div class="font-serif text-[24px] leading-none mt-2 tabular-nums">
                                        {{ number_format((int) $activeEvents) }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1">{{ __('firing on store events') }}</div>
                                </div>
                            </div>

                            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card mt-3">
                                <h3 class="font-serif text-[18px] leading-tight mb-3">{{ __('Revenue · last 14 days') }}</h3>
                                <div class="flex items-end gap-1.5 h-40">
                                    @foreach ($A['trend'] ?? [] as $pt)
                                        @php $h = (int) round((($pt['value'] ?? 0) / ($A['trend_max'] ?? 1)) * 100); @endphp
                                        <div class="flex-1 flex flex-col items-center justify-end gap-1 group">
                                            <div class="w-full rounded-t woo-bg" style="height: {{ max(2, $h) }}%"
                                                title="{{ $currency }} {{ number_format($pt['value'] ?? 0, 2) }}">
                                            </div>
                                            <span
                                                class="font-mono text-[8.5px] text-ink-400 rotate-0">{{ $pt['label'] ?? '' }}</span>
                                        </div>
                                    @endforeach
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
                                                $css = match ($log->status) {
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
                                                        class="font-mono text-[10px] uppercase px-2 py-0.5 rounded-full {{ $css }}">{{ $log->status }}</span>
                                                </td>
                                                <td class="px-4 py-2.5 text-right font-mono text-[11px] text-ink-500">
                                                    {{ $log->created_at?->diffForHumans() }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-4 py-8 text-center text-[12px] text-ink-500">
                                                    {{ __('No webhook events received yet.') }}</td>
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
                                        {{ __('WooCommerce version') }}</div>
                                    <div class="text-[13px] mt-1">{{ $integration->store_version ?: '—' }}</div>
                                </div>
                                <div>
                                    <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                        {{ __('Webhook URL') }}</div>
                                    <div class="font-mono text-[11.5px] mt-1 text-ink-700 break-all">
                                        {{ url('/woocommerce/webhook/' . $integration->webhook_secret) }}</div>
                                </div>
                                @php $webhookCount = is_array($integration->metadata['webhook_ids'] ?? null) ? count(array_filter($integration->metadata['webhook_ids'])) : 0; @endphp
                                <div>
                                    <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                        {{ __('Registered webhooks') }}</div>
                                    <div class="text-[13px] mt-1">{{ $webhookCount }} active
                                        subscription{{ $webhookCount === 1 ? '' : 's' }}</div>
                                </div>
                            </div>

                            {{-- Companion plugin — unlocks the events the REST API can't emit --}}
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5 mt-3">
                                <div class="flex items-start gap-4">
                                    <div class="w-11 h-11 rounded-xl grid place-items-center shrink-0"
                                        style="background:#F3ECFA">
                                        <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="#7F54B3"
                                            stroke-width="1.6">
                                            <path d="M12 3v18M3 12h18M7 7l10 10M17 7L7 17" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-serif text-[19px] leading-tight">
                                            {{ __(':brand companion plugin', ['brand' => brand_name()]) }} <span
                                                class="font-mono text-[10px] align-middle ml-1 px-1.5 py-0.5 rounded-full bg-wa-mint text-wa-deep border border-wa-green/40">{{ __('optional · unlocks more') }}</span>
                                        </h3>
                                        <p class="text-[12.5px] text-ink-600 mt-1.5 max-w-2xl">
                                            {{ __("WooCommerce's REST webhooks can't see a few high-value moments. Install this tiny plugin on your store to unlock:") }}
                                        </p>
                                        <ul class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5 text-[12px] text-ink-700 max-w-2xl">
                                            <li class="flex items-start gap-1.5"><svg viewBox="0 0 16 16"
                                                    class="w-3.5 h-3.5 mt-0.5 shrink-0 text-wa-deep" fill="none"
                                                    stroke="currentColor" stroke-width="1.8">
                                                    <path d="m4 8 3 3 5-6" />
                                                </svg> {{ __('Abandoned-cart recovery (captures the phone at checkout)') }}
                                            </li>
                                            <li class="flex items-start gap-1.5"><svg viewBox="0 0 16 16"
                                                    class="w-3.5 h-3.5 mt-0.5 shrink-0 text-wa-deep" fill="none"
                                                    stroke="currentColor" stroke-width="1.8">
                                                    <path d="m4 8 3 3 5-6" />
                                                </svg> {{ __('Subscription dunning (failed renewal → pay-now link)') }}</li>
                                        </ul>
                                        <div class="flex items-center gap-2 mt-4 flex-wrap">
                                            <a href="{{ url('/woocommerce/' . $integration->id . '/plugin') }}"
                                                class="px-4 py-2 rounded-full woo-bg text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                    stroke="currentColor" stroke-width="1.7">
                                                    <path d="M8 2v8m0 0 3-3m-3 3L5 7M3 13h10" />
                                                </svg>
                                                {{ __('Download plugin (.zip)') }}
                                            </a>
                                            <span
                                                class="text-[11px] text-ink-500">{{ __('Your store URL + secret are pre-filled. Upload via Plugins → Add New → Upload.') }}</span>
                                        </div>
                                    </div>
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

                const syncBtn = document.getElementById('wc-sync-btn');
                if (syncBtn) {
                    syncBtn.addEventListener('click', async () => {
                        syncBtn.disabled = true;
                        syncBtn.querySelector('span').textContent = 'Syncing…';
                        try {
                            const r = await fetch(`/woocommerce/${syncBtn.dataset.id}/sync`, {
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

                // Filter each Template dropdown to templates usable by the chosen
                // "Send from" sender. WABA account → only its approved templates;
                // Unofficial/Twilio → only that engine's templates; Auto → all.
                const wcSender = document.getElementById('wc-sender');
                function wcFilterTemplates() {
                    const opt = wcSender ? wcSender.options[wcSender.selectedIndex] : null;
                    const eng = opt ? (opt.dataset.engine || '') : '';
                    const acct = opt ? String(opt.dataset.account || '') : '';
                    document.querySelectorAll('.wc-tpl-select').forEach(sel => {
                        let selectedHidden = false;
                        Array.from(sel.options).forEach(o => {
                            if (o.value === '') { o.hidden = false; return; } // keep "— none —"
                            let show = true;
                            if (eng === 'waba') show = (o.dataset.engine === 'waba' && String(o.dataset.account || '') === acct);
                            else if (eng === 'baileys' || eng === 'twilio') show = (o.dataset.engine === eng);
                            // eng === '' (Auto) → show everything
                            o.hidden = !show;
                            if (!show && o.selected) selectedHidden = true;
                        });
                        if (selectedHidden) sel.value = ''; // chosen template no longer valid → reset
                    });
                }
                if (wcSender) { wcSender.addEventListener('change', wcFilterTemplates); wcFilterTemplates(); }

                const form = document.getElementById('wc-events-form');
                if (form) {
                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const status = document.getElementById('wc-events-status');
                        status.textContent = 'Saving…';
                        const fd = new FormData(form);
                        const payload = {
                            events: {},
                            sender_key: document.getElementById('wc-sender')?.value || ''
                        };
                        for (const [k, v] of fd.entries()) {
                            const m = k.match(/^events\[([^\]]+)\]\[([^\]]+)\]$/);
                            if (!m) continue;
                            const [, topic, field] = m;
                            payload.events[topic] = payload.events[topic] || {};
                            if (field === 'var_map_csv') {
                                const arr = String(v).split(',').map(s => s.trim()).filter(Boolean);
                                if (arr.length) payload.events[topic].var_map = arr;
                            } else {
                                payload.events[topic][field] = (field === 'is_active') ? (v === '1') : v;
                            }
                        }
                        try {
                            const r = await fetch(`/woocommerce/${form.dataset.id}/events`, {
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

                // Offer + win-back broadcasts.
                function bindBroadcast(formId, statusId, url) {
                    const f = document.getElementById(formId);
                    if (!f) return;
                    const box = document.getElementById(statusId);
                    const show = (ok, msg) => {
                        box.classList.remove('hidden');
                        box.className = 'text-[12px] px-3 py-2 rounded-lg border ' + (ok ?
                            'bg-wa-mint border-wa-green/30 text-wa-deep' :
                            'bg-accent-coral/10 border-accent-coral/40 text-[#A1431F]');
                        box.textContent = msg;
                    };
                    f.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        show(true, 'Sending…');
                        const btn = f.querySelector('button[type=submit]');
                        if (btn) btn.disabled = true;
                        try {
                            const r = await fetch(url.replace('{id}', f.dataset.id), {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': CSRF,
                                    'Accept': 'application/json'
                                },
                                body: new URLSearchParams(new FormData(f)),
                            });
                            const d = await r.json();
                            if (d.ok) show(true, `Sent to ${d.sent} of ${d.total}.` + (d.failed ?
                                ` ${d.failed} failed.` : ''));
                            else show(false, d.message || 'Send failed.');
                        } catch (err) {
                            show(false, 'Network error.');
                        }
                        if (btn) btn.disabled = false;
                    });
                }
                bindBroadcast('wc-offer-form', 'wc-offer-status', '/woocommerce/{id}/offer');
                bindBroadcast('wc-winback-form', 'wc-winback-status', '/woocommerce/{id}/winback');
            })();
        </script>

    @endif

</x-layouts.user>
