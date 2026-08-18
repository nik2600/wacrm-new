<x-layouts.user :title="__('Store')" nav-key="connect" page="user-store-coming-soon">

    <main class="max-w-[760px] mx-auto px-4 sm:px-7 py-12">
        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Store') }}</div>
        <h1 class="font-serif text-[28px] sm:text-[36px] tracking-[-0.02em] leading-tight">{{ __('Your store is being set up') }}</h1>
        <p class="text-[13px] text-ink-600 mt-2 max-w-xl">
            {{ __('Connection saved. The full dashboard (products, orders, theme picker, storefront) ships in the next update — the foundation underneath is already live.') }}
        </p>

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card min-w-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Provider') }}</div>
                <div class="font-serif text-[20px] mt-1">
                    {{ $cfg ? \App\Enums\WaProvider::tryFrom($cfg->provider)?->label() : 'Not connected' }}</div>
                <div class="text-[11.5px] text-ink-500 font-mono mt-1">
                    @if ($cfg)
                        status: {{ $cfg->status }} · {{ $cfg->phone_number ?: $cfg->display_label }}
                    @else
                        Visit <a href="{{ url('/connect?platform=wa-store') }}"
                            class="text-wa-deep font-semibold hover:underline">/connect</a> to set up.
                    @endif
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card min-w-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Public storefront') }}</div>
                <div class="font-serif text-[20px] mt-1">{{ $sf ? $sf->slug : '—' }}</div>
                <div class="text-[11.5px] text-ink-500 font-mono mt-1 break-all">
                    @if ($sf)
                        {{ $sf->public_url }}
                    @else
                        Storefront row will appear once you connect a provider.
                    @endif
                </div>
            </div>
        </div>

        <div
            class="mt-6 bg-paper-50 border border-paper-200 rounded-2xl p-5 text-[12.5px] text-ink-700 leading-relaxed">
            <div class="font-semibold text-ink-900 mb-1">{{ __('What works right now') }}</div>
            <ul class="list-disc pl-5 space-y-1">
                <li>{{ __('Provider abstraction — every send goes through') }} <span
                        class="font-mono">{{ __('WhatsAppDispatcher') }}</span> and reads your workspace's connected
                    provider</li>
                <li>{{ __('Admin can enable any combination of WABA / Unofficial API / Twilio at') }} <a
                        href="{{ url('/admin/settings') }}"
                        class="text-wa-deep font-semibold hover:underline">/admin/settings</a></li>
                <li>{{ __('Workspace picks among the enabled providers at') }} <a
                        href="{{ url('/connect?platform=wa-store') }}"
                        class="text-wa-deep font-semibold hover:underline">/connect</a></li>
                <li>{{ __('Schema is in place:') }} <span class="font-mono">{{ __('wa_provider_configs') }}</span>,
                    <span class="font-mono">{{ __('wa_products') }}</span>, <span
                        class="font-mono">{{ __('wa_orders') }}</span>, <span
                        class="font-mono">{{ __('wa_storefronts') }}</span></li>
            </ul>
            <div class="font-semibold text-ink-900 mt-3 mb-1">{{ __('Coming in the next update') }}</div>
            <ul class="list-disc pl-5 space-y-1">
                <li>{{ __('Full /store dashboard with sidebar tabs (Overview · Orders · Products · Customers · Storefront · Analytics)') }}
                </li>
                <li>{{ __('Product CRUD with image gallery') }}</li>
                <li>{{ __('Theme picker (8 themes)') }}</li>
                <li>{{ __('Public storefront route + custom-domain support') }}</li>
                <li>{{ __('Orders inbox + status timeline + payment-link composer') }}</li>
                <li>{{ __('WABA Embedded Signup + catalog sync + order webhook') }}</li>
            </ul>
        </div>
    </main>

</x-layouts.user>
