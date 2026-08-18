@php
    // Surface the workspace's existing WA Store shops to the
// integrations JS so the WhatsApp Store card can swap "Connect
// now" for "View shops / + Add shop" once one or more exist.
$waShops = collect();
if ($wsId = auth()->user()?->current_workspace_id) {
    $waShops = \App\Models\WaStorefront::where('workspace_id', $wsId)
        ->orderByDesc('id')
        ->get(['id', 'shop_name', 'slug', 'enabled', 'theme_key', 'custom_domain', 'custom_domain_verified']);
    }
@endphp

<x-layouts.user :title="__('Integrations')" nav-key="more" page="user-integrations-index">

    <!-- Sub header -->
    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/more') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to More') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg></a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('More / Integrations') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate"><span
                            class="italic text-wa-deep">{{ __('Integrations') }}</span> &amp; {{ __('connected apps') }}
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono">{{ __('6 connected / 18 available') }}</span>
                <a href="{{ url('/guidebook') }}"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('View docs') }}</a>
                <button
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    {{ __('Custom integration') }}
                </button>
            </div>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6 space-y-5">

        <!-- Header band: title + tabs + search -->
        <section class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="min-w-0">
                    <h1 class="font-serif text-[28px] leading-tight tracking-[-0.01em]">
                        {{ __('Supercharge your workflow') }}</h1>
                    <p class="mt-1 text-[13px] text-ink-500">
                        {{ __('Connect the tools you already use — orders, AI assistants, sheets, payments, and more.') }}
                    </p>
                </div>
                <div class="relative flex-1 min-w-[260px] max-w-[420px]">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                        fill="none" stroke="currentColor" stroke-width="1.6">
                        <circle cx="7" cy="7" r="5" />
                        <path d="m11 11 3 3" />
                    </svg>
                    <input id="search" type="search" placeholder="{{ __('Search integrations…') }}"
                        class="w-full pl-9 pr-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                </div>
            </div>

            <div id="cat-tabs" class="mt-4 flex items-center gap-1 flex-wrap">
                <button class="cat-tab px-3.5 py-1.5 rounded-full text-[12px] font-semibold bg-wa-deep text-paper-0"
                    data-cat="all">{{ __('All') }} <span
                        class="ml-1 font-mono text-[10px] opacity-80">10</span></button>
                <button
                    class="cat-tab px-3.5 py-1.5 rounded-full text-[12px] font-semibold text-ink-600 hover:bg-paper-100"
                    data-cat="ecom">{{ __('E-commerce') }} <span
                        class="ml-1 font-mono text-[10px] opacity-80">4</span></button>
                <button
                    class="cat-tab px-3.5 py-1.5 rounded-full text-[12px] font-semibold text-ink-600 hover:bg-paper-100"
                    data-cat="crm">{{ __('CRM') }} <span
                        class="ml-1 font-mono text-[10px] opacity-80">1</span></button>
                <button
                    class="cat-tab px-3.5 py-1.5 rounded-full text-[12px] font-semibold text-ink-600 hover:bg-paper-100"
                    data-cat="productivity">{{ __('Productivity') }} <span
                        class="ml-1 font-mono text-[10px] opacity-80">5</span></button>
            </div>
        </section>

        <!-- Connected first row (highlight) -->
        <section>
            <div class="flex items-center justify-between mb-3">
                <h2 id="grid-title" class="font-serif text-[20px] leading-tight">{{ __('All integrations') }}</h2>
                <span id="grid-count" class="font-mono text-[10.5px] text-ink-500">{{ __('18 apps') }}</span>
            </div>
            <div id="grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4"></div>
            @include('user.partials.empty-state', [
                'id' => 'empty',
                'class' => 'hidden',
                'message' =>
                    'No integrations match the current filters. Try a different keyword or clear the filter.',
            ])
        </section>

        <!-- Tip -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('What gets synced') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Each integration in one line') }}
                </h3>
                <div class="grid grid-cols-1 gap-x-6 gap-y-2 text-[12.5px]">
                    <div class="flex items-center justify-between"><span
                            class="text-ink-700">{{ __('Shopify') }}</span><span
                            class="font-mono text-[10.5px] text-ink-500">{{ __('orders, customers, abandoned carts') }}</span>
                    </div>
                    <div class="flex items-center justify-between"><span
                            class="text-ink-700">{{ __('WooCommerce') }}</span><span
                            class="font-mono text-[10.5px] text-ink-500">{{ __('orders, refunds, product updates') }}</span>
                    </div>
                    <div class="flex items-center justify-between"><span
                            class="text-ink-700">{{ __('WhatsApp Catalog') }}</span><span
                            class="font-mono text-[10.5px] text-ink-500">{{ __('products, prices, stock') }}</span>
                    </div>
                    <div class="flex items-center justify-between"><span
                            class="text-ink-700">{{ __('WhatsApp Store') }}</span><span
                            class="font-mono text-[10.5px] text-ink-500">{{ __('storefront, in-chat checkout') }}</span>
                    </div>
                    <div class="flex items-center justify-between"><span
                            class="text-ink-700">{{ __('Google Sheets') }}</span><span
                            class="font-mono text-[10.5px] text-ink-500">{{ __('contacts in / messages out') }}</span>
                    </div>
                    <div class="flex items-center justify-between"><span
                            class="text-ink-700">{{ __('HubSpot CRM') }}</span><span
                            class="font-mono text-[10.5px] text-ink-500">{{ __('contacts upserted, deals created') }}</span>
                    </div>
                    <div class="flex items-center justify-between"><span
                            class="text-ink-700">{{ __('Google Calendar') }}</span><span
                            class="font-mono text-[10.5px] text-ink-500">{{ __('free/busy reads, bookings written') }}</span>
                    </div>
                </div>
            </div>
            <div class="bg-wa-deep rounded-[14px] p-5 shadow-soft text-paper-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/60">{{ __('Tip') }}
                </div>
                <div class="font-serif text-[22px] leading-tight mt-1">{{ __('Need something else?') }}</div>
                <p class="mt-2 text-[12px] text-paper-0/80 leading-relaxed">
                    {{ __('Use a webhook to push events from any other system into :app — orders, signups, payments, anything HTTP.', ['app' => brand_name()]) }}
                </p>
                <a href="{{ url('/webhooks/create') }}"
                    class="mt-4 inline-flex items-center gap-2 rounded-full bg-paper-0 text-wa-deep px-4 py-2 text-[12px] font-semibold">{{ __('Build a webhook') }}<svg
                        viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M6 3l5 5-5 5" />
                    </svg></a>
            </div>
        </section>

    </main>

    <script>
        // Hand off the workspace's existing storefronts to the integrations
        // JS so the WhatsApp Store card can flip "Connect now" → "View
        // shops + Add shop" once at least one exists.
        window.WA_STORE_SHOPS = @json($waShops);

        // Google Sheets connection state — drives the "Connected" badge on
        // the Sheets card. Real state now: true only when the user has
        // generated a Sheets API key on /account.
        window.GSHEETS_CONNECTED = @json((bool) auth()->user()?->sheets_api_key_hash);

        // WhatsApp Catalog connection state — true only when the workspace
        // has actually linked a Meta Commerce catalog at /store/catalog.
        window.WA_CATALOG_CONNECTED = @json(auth()->user()?->current_workspace_id
                ? \App\Models\WaCatalog::where('workspace_id', auth()->user()->current_workspace_id)->exists()
                : false);

        // Shopify connection state — true only when an active integration row
        // exists for this workspace.
        window.SHOPIFY_CONNECTED = @json(auth()->user()?->current_workspace_id
                ? \App\Models\ShopifyIntegration::where('workspace_id', auth()->user()->current_workspace_id)->where('status', 'active')->exists()
                : false);
        window.SHOPIFY_ENABLED = @json((bool) \App\Models\SystemSetting::get('shopify_enabled', false));

        // WooCommerce connection state — same pattern.
        window.WOOCOMMERCE_CONNECTED = @json(auth()->user()?->current_workspace_id
                ? \App\Models\WoocommerceIntegration::where('workspace_id', auth()->user()->current_workspace_id)->where('status', 'active')->exists()
                : false);
        window.WOOCOMMERCE_ENABLED = @json((bool) \App\Models\SystemSetting::get('woocommerce_enabled', false));

        // HubSpot connection state — true only when an active integration row
        // exists for this workspace.
        window.HUBSPOT_CONNECTED = @json(auth()->user()?->current_workspace_id
                ? \App\Models\HubspotIntegration::where('workspace_id', auth()->user()->current_workspace_id)->where('status', 'active')->exists()
                : false);
        window.HUBSPOT_ENABLED = @json((bool) \App\Models\SystemSetting::get('hubspot_enabled', false));

        // Google Calendar connection state — true when the current workspace
        // has a valid access_token + chosen calendar_id stashed in
        // appointment_settings JSON. Read live so this stays accurate after
        // connect / disconnect.
        @php
            $gcalConnected = false;
            if ($wsId2 = auth()->user()?->current_workspace_id) {
                $ws = \App\Models\Workspace::find($wsId2);
                $oauth = $ws?->appointment_settings['google_oauth'] ?? [];
                $gcalConnected = !empty($oauth['access_token'] ?? null) && !empty($oauth['calendar_id'] ?? null);
            }
        @endphp
        window.GCAL_CONNECTED = @json($gcalConnected);
        window.GCAL_ENABLED = @json((bool) \App\Models\SystemSetting::get('google_calendar_enabled', false));

        // Slack + Trello connection state — active integration row for this workspace.
        window.SLACK_CONNECTED = @json(auth()->user()?->current_workspace_id
                ? \App\Models\SlackIntegration::where('workspace_id', auth()->user()->current_workspace_id)->where('status', 'active')->exists()
                : false);
        window.SLACK_ENABLED = @json((bool) \App\Models\SystemSetting::get('slack_enabled', true));
        window.TRELLO_CONNECTED = @json(auth()->user()?->current_workspace_id
                ? \App\Models\TrelloIntegration::where('workspace_id', auth()->user()->current_workspace_id)->where('status', 'active')->exists()
                : false);
        window.TRELLO_ENABLED = @json((bool) \App\Models\SystemSetting::get('trello_enabled', true));
    </script>

</x-layouts.user>
