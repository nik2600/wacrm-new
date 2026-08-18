<x-layouts.admin :title="__('WooCommerce settings')" admin-key="settings">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('WooCommerce') }}</span>
        </div>
        <div class="relative flex-1 max-w-[520px] ml-4">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500"
                fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="7" cy="7" r="5" />
                <path d="m11 11 3 3" />
            </svg>
            <input
                class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition"
                placeholder="{{ __('Search inside settings...') }}" />
            <kbd
                class="absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded-md bg-paper-0 border border-paper-200 text-[10px] font-mono text-ink-500">{{ __('CMD K') }}</kbd>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">
        @if (session('success'))
            <div
                class="px-4 py-2.5 rounded-xl bg-wa-bubble border border-wa-green/30 text-[12.5px] text-wa-deep flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="m4 8 3 3 5-6" />
                </svg>
                {{ session('success') }}
            </div>
        @endif
        @if (isset($errors) && $errors->any())
            <div
                class="px-4 py-2.5 rounded-xl bg-accent-coral/10 border border-accent-coral/30 text-[12.5px] text-accent-coral">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.woocommerce.update') }}" class="space-y-5">
            @csrf

            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin · Project settings') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">
                        {{ __('WooCommerce') }} <span class="italic"
                            style="color:#7F54B3">{{ __('settings') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __("WooCommerce doesn't need any central app credentials — each merchant generates their own
                         REST API keys inside their own WC admin. Your job here is just to flip the platform-level
                         switch and (optionally) review usage.") }}
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0 pb-1">
                    <a href="{{ url('/admin/settings') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </div>

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
                <div class="space-y-5 min-w-0">

                    {{-- Feature toggle --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('woocommerce · platform toggle') }}</div>
                                <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('Enable WooCommerce') }}
                                </h2>
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <span class="text-[12px] text-ink-700">{{ __('Enable') }}</span>
                                <input type="hidden" name="woocommerce_enabled" value="0">
                                <input type="checkbox" name="woocommerce_enabled" value="1"
                                    @checked($enabled) class="w-5 h-5 accent-wa-deep">
                            </label>
                        </div>
                        <div class="p-5 text-[12.5px] text-ink-700 leading-relaxed">
                            When enabled, workspace owners see the <span
                                class="font-mono text-wa-deep">{{ __('Connect now') }}</span> button on the
                            <a href="{{ url('/integrations') }}"
                                class="text-wa-deep underline">{{ __('Integrations') }}</a> page and can paste their
                            store URL + Consumer Key + Consumer Secret at <span
                                class="font-mono text-wa-deep">/woocommerce</span>.
                            Each connection is workspace-scoped — keys are encrypted at rest and only used to call that
                            store's REST API.
                        </div>
                    </section>

                    {{-- Step-by-step admin guide --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('walkthrough you can share with merchants') }}</div>
                            <h2 class="font-serif text-[25px] leading-tight mt-1">
                                {{ __('How merchants generate their keys') }}</h2>
                            <p class="text-[12.5px] text-ink-600 mt-1.5">
                                {{ __('Every WooCommerce store handles its own auth. Customers walk through these five steps inside their own WC admin — :app never sees these keys until the customer pastes them at', ['app' => brand_name()]) }}
                                <span class="font-mono text-wa-deep">/woocommerce</span>.</p>
                        </div>

                        <ol class="divide-y divide-paper-100">
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full text-paper-0 grid place-items-center font-mono text-[12px] font-semibold shrink-0"
                                    style="background:#7F54B3">1</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">
                                        {{ __("Log into the merchant's WordPress admin") }}</div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        They need <span
                                            class="font-mono text-ink-900">{{ __('manage_options') }}</span> capability
                                        (typically the shop owner / store admin role). HTTPS must be on — Basic Auth is
                                        rejected over plain HTTP.
                                    </p>
                                </div>
                            </li>

                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full text-paper-0 grid place-items-center font-mono text-[12px] font-semibold shrink-0"
                                    style="background:#7F54B3">2</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">{{ __('Open') }} <span
                                            class="font-mono">{{ __('WooCommerce → Settings → Advanced → REST API') }}</span>
                                    </div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        Click <span class="font-mono text-ink-900">{{ __('Add key') }}</span>. The form
                                        asks for a description, a WordPress user, and a permission level.
                                    </p>
                                </div>
                            </li>

                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full text-paper-0 grid place-items-center font-mono text-[12px] font-semibold shrink-0"
                                    style="background:#7F54B3">3</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">{{ __('Fill in the form') }}</div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        <span
                                            class="font-mono text-ink-900">{{ __('Description: :app integration', ['app' => brand_name()]) }}</span>,
                                        <span class="font-mono text-ink-900">{{ __('User: shop owner') }}</span>,
                                        <span
                                            class="font-mono text-ink-900">{{ __('Permissions: Read/Write') }}</span>.
                                        {{ __('Read/Write is required so :app can register webhooks back to the store.', ['app' => brand_name()]) }}
                                    </p>
                                </div>
                            </li>

                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full text-paper-0 grid place-items-center font-mono text-[12px] font-semibold shrink-0"
                                    style="background:#7F54B3">4</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">{{ __('Generate and copy both keys') }}
                                    </div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        Click <span
                                            class="font-mono text-ink-900">{{ __('Generate API key') }}</span>.
                                        WooCommerce shows the
                                        <span class="text-wa-deep font-semibold">{{ __('Consumer Key') }}</span>
                                        (starts with <span class="font-mono">{{ __('ck_') }}</span>) and
                                        <span class="text-wa-deep font-semibold">{{ __('Consumer Secret') }}</span>
                                        (starts with <span class="font-mono">{{ __('cs_') }}</span>).
                                        <strong>{{ __('The secret only displays once') }}</strong> — they must copy it
                                        before leaving the page.
                                    </p>
                                </div>
                            </li>

                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full text-paper-0 grid place-items-center font-mono text-[12px] font-semibold shrink-0"
                                    style="background:#7F54B3">5</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">{{ __('Paste at') }} <a
                                            href="{{ url('/woocommerce') }}" target="_blank"
                                            class="text-wa-deep underline">/woocommerce</a> {{ __('in') }}
                                        {{ brand_name() }}
                                    </div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        Store URL + Consumer Key + Consumer Secret. Hit <span
                                            class="font-mono text-ink-900">{{ __('Test connection') }}</span> first to
                                        verify, then
                                        <span class="font-mono text-ink-900">{{ __('Connect store') }}</span>. We hit
                                        <span class="font-mono">/wp-json/wc/v3/system_status</span> to verify, then
                                        register webhooks for the topics they pick.
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </section>

                    {{-- Usage --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('usage') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Live across workspaces') }}
                            </h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                    {{ __('Stores connected') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 tabular-nums">
                                    {{ number_format($integrationsCount) }}</div>
                            </div>
                            <div>
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                    {{ __('Active automations') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 tabular-nums">
                                    {{ number_format($eventsCount) }}</div>
                            </div>
                            <div>
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                    {{ __('Webhook events received') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 tabular-nums">
                                    {{ number_format($logsCount) }}</div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Why no credentials here?') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __("It's per-store") }}</h3>
                        </div>
                        <div class="p-4 space-y-3 text-[12px] text-ink-700">
                            <p>{{ __("WooCommerce isn't a hosted platform like Shopify — there's no central app to register. Each store runs its own copy of WC on WordPress.") }}
                            </p>
                            <p>{{ __('Auth is per-store via REST API Consumer Keys, generated in') }} <span
                                    class="font-mono">{{ __('WC Settings → Advanced → REST API') }}</span>.
                                {{ __(':app never holds platform-wide credentials; just per-merchant ones.', ['app' => brand_name()]) }}
                            </p>
                        </div>
                    </div>
                    <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('API version pinned') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">{{ __('All calls go to') }} <span
                                class="font-mono">/wp-json/wc/v3/</span>. The v3 namespace is the current stable per
                            WC's May 2026 developer docs.</p>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('Webhook receiver') }}</div>
                        <p class="font-mono text-[10.5px] text-ink-600 mt-1 break-all">
                            {{ url('/woocommerce/webhook/{secret}') }}</p>
                        <p class="text-[11px] text-ink-500 mt-1.5">
                            {{ __('Per-store secret. HMAC-SHA256 verified on every delivery.') }}</p>
                    </div>
                </aside>
            </section>
        </form>
    </main>

</x-layouts.admin>
