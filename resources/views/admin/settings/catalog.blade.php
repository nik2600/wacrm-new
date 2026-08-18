{{--
 /admin/settings/catalog — platform-level WhatsApp Catalog knobs.

 IMPORTANT: per-merchant Catalog ID, WABA ID, Phone Number ID, and the
 permanent access token live on each workspace's /catalog page —
 Meta requires the catalog to be connected to the merchant's own
 Commerce Account, so there is no single platform-wide catalog.

 What lives HERE (admin one-time):
 • Feature enable toggle (gates the user-side /catalog page)
 • Graph API version (defaults to current v23.0)
 • Optional Meta App ID + App Secret (used for app-level webhooks
 + signed OAuth flows we add later — not required for the basic
 "merchant pastes their own system-user token" flow)
 • Default currency for new catalog product feeds
 • Commerce Account toggle (whether the platform supports
 in-WhatsApp checkout via Commerce; off = catalog-browse only)
--}}
<x-layouts.admin :title="__('Catalog settings')" admin-key="settings">

 <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
 <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
 <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
 <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3"/></svg>
 <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
 <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3"/></svg>
 <span class="text-ink-900 normal-case tracking-normal">{{ __('Catalog') }}</span>
 </div>
 <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
 </header>

 <form method="POST" action="{{ route('admin.settings.catalog.update') }}" class="contents">
 @csrf @method('PATCH')

 <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">
 <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
 <div>
 <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Project settings') }}</div>
 <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('WhatsApp') }} <span class="italic text-wa-deep">{{ __('Catalog') }}</span>.</h1>
 <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Platform-level controls for the WABA Catalog feature. Each merchant connects their own Meta Commerce Account catalog from the workspace-side') }} <a href="{{ url('/catalog') }}" class="text-wa-deep underline">{{ __('/catalog') }}</a> {{ __('page — this screen sets the defaults every workspace inherits.') }}</p>
 </div>
 <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
 <a href="{{ url('/admin/settings') }}" class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
 <a href="{{ url('/catalog') }}" class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Open user catalog') }}</a>
 <button type="reset" class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Reset draft') }}</button>
 <button type="submit" class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
 </div>
 </div>

 @if (session('success'))<div class="rounded-xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-3 text-[12.5px] font-medium">{{ session('success') }}</div>@endif
 @if ($errors->any())<div class="rounded-xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]"><div class="font-semibold mb-1">{{ __('Please fix the highlighted fields:') }}</div><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

 {{-- Usage stats --}}
 <section class="grid grid-cols-1 sm:grid-cols-3 gap-3">
 <div class="rounded-2xl border border-paper-200 bg-paper-0 p-4 shadow-card">
 <div class="text-[11px] text-ink-600 font-medium">{{ __('Workspaces connected') }}</div>
 <div class="font-serif text-[28px] leading-none mt-2 tabular-nums">{{ number_format($stats['connected']) }}</div>
 <div class="text-[11px] text-ink-500 mt-1">{{ __('with a Meta Catalog ID saved') }}</div>
 </div>
 <div class="rounded-2xl border border-paper-200 bg-paper-0 p-4 shadow-card">
 <div class="text-[11px] text-ink-600 font-medium">{{ __('Products synced') }}</div>
 <div class="font-serif text-[28px] leading-none mt-2 tabular-nums">{{ number_format($stats['products']) }}</div>
 <div class="text-[11px] text-ink-500 mt-1">{{ __('across all workspace catalogs') }}</div>
 </div>
 <div class="rounded-2xl border border-paper-200 bg-paper-0 p-4 shadow-card">
 <div class="text-[11px] text-ink-600 font-medium">{{ __('Catalog messages sent') }}</div>
 <div class="font-serif text-[28px] leading-none mt-2 tabular-nums">{{ number_format($stats['sends']) }}</div>
 <div class="text-[11px] text-ink-500 mt-1">{{ __('SPM + MPM + catalog CTA') }}</div>
 </div>
 </section>

 <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
 <div class="space-y-5 min-w-0">

 {{-- Feature gate --}}
 <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
 <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
 <div>
 <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Feature gate') }}</div>
 <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('Catalog availability') }}</h2>
 </div>
 <label class="flex items-center gap-2 cursor-pointer">
 <span class="text-[12px] text-ink-700">{{ __('Enabled') }}</span>
 <span class="relative inline-flex items-center w-10 h-5 shrink-0">
 <input type="checkbox" name="catalog_enabled" value="1" @checked(old('catalog_enabled', $catalog['enabled'])) class="sr-only peer">
 <span class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
 <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
 </span>
 </label>
 </div>
 <div class="p-5 text-[12.5px] text-ink-700 leading-relaxed space-y-3">
 <p>
 {{ __('When enabled, workspace owners can connect their Meta Commerce catalog at') }}
 <a href="{{ url('/catalog') }}" class="text-wa-deep underline font-medium">/catalog</a>
 {{ __('and send Single Product, Multi-Product, and Catalog-CTA messages from the chat + flow builder. Switch off to hide the menu entry across all workspaces (existing connections stay intact but become read-only).') }}
 </p>
 <label class="rounded-xl border border-paper-200 px-4 py-3 flex items-center justify-between cursor-pointer">
 <div>
 <div class="text-[12.5px] font-semibold">{{ __('Allow Commerce checkout') }}</div>
 <div class="text-[11px] text-ink-500 mt-0.5">{{ __('Lets merchants accept in-WhatsApp payment via a connected Commerce Account. Requires Meta-approved Commerce Account + supported region.') }}</div>
 </div>
 <span class="relative inline-flex items-center w-10 h-5 shrink-0">
 <input type="checkbox" name="catalog_commerce_enabled" value="1" @checked(old('catalog_commerce_enabled', $catalog['commerce_enabled'])) class="sr-only peer">
 <span class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
 <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
 </span>
 </label>
 </div>
 </section>

 {{-- Graph API --}}
 <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
 <div class="px-5 py-4 border-b border-paper-200">
 <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Cloud API') }}</div>
 <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Graph API version') }}</h2>
 <p class="text-[11.5px] text-ink-500 mt-1">{{ __('Every catalog operation (send, sync, push) goes through this Graph API version. Meta ships a new version every ~3 months and supports the previous one for ~2 years.') }}</p>
 </div>
 <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
 <label class="space-y-1.5">
 <span class="text-[11.5px] font-semibold">{{ __('Graph API version') }}</span>
 <input name="catalog_graph_api_version" value="{{ old('catalog_graph_api_version', $catalog['graph_api_version']) }}"
 placeholder="v23.0" pattern="v\d{1,2}\.\d{1,2}"
 class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
 <span class="text-[11px] text-ink-500">{{ __('Current stable as of May 2026:') }} <span class="font-mono">v23.0</span>. {{ __('Format:') }} <span class="font-mono">v{major}.{minor}</span>.</span>
 </label>
 <label class="space-y-1.5">
 <span class="text-[11.5px] font-semibold">{{ __('Default currency') }}</span>
 <input name="catalog_default_currency" value="{{ old('catalog_default_currency', $catalog['default_currency'] ?: 'USD') }}"
 placeholder="{{ __('USD') }}" maxlength="3"
 class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono uppercase focus:outline-none focus:border-wa-deep">
 <span class="text-[11px] text-ink-500">{{ __('ISO-4217 code (3 letters). Used as the default for new catalogs — each Meta Commerce catalog still locks to a single currency.') }}</span>
 </label>
 </div>
 </section>

 {{-- Optional platform Meta app credentials --}}
 <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
 <div class="px-5 py-4 border-b border-paper-200">
 <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Optional · Meta App') }}</div>
 <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Platform-wide Meta app') }}</h2>
 <p class="text-[11.5px] text-ink-500 mt-1">{{ __('Only needed if you plan to ship an "Install with Meta" OAuth experience. The default flow has each merchant paste their own system-user token at') }} <a href="{{ url('/catalog') }}" class="text-wa-deep underline">{{ __('/catalog') }}</a> {{ __(' — no app credentials required.') }}</p>
 </div>
 <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
 <label class="space-y-1.5">
 <span class="text-[11.5px] font-semibold">{{ __('Meta App ID') }}</span>
 <input name="catalog_meta_app_id" value="{{ old('catalog_meta_app_id', $catalog['meta_app_id']) }}"
 placeholder="123456789012345" maxlength="60"
 class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
 <span class="text-[11px] text-ink-500">{{ __('From developers.facebook.com → your app → Settings → Basic.') }}</span>
 </label>
 <label class="space-y-1.5">
 <span class="text-[11.5px] font-semibold">{{ __('Meta App Secret') }}</span>
 <input type="password" name="catalog_meta_app_secret"
 value="" autocomplete="new-password"
 placeholder="{{ $catalog['meta_app_secret'] ? __('•••••••••••• (saved — leave blank to keep)') : __('App secret') }}"
 class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
 <span class="text-[11px] text-ink-500">{{ __('Encrypted at rest. Used to verify Webhook payload signatures (X-Hub-Signature-256).') }}</span>
 </label>
 </div>
 </section>

 {{-- Setup walkthrough merchants follow --}}
 <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
 <div class="px-5 py-4 border-b border-paper-200">
 <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Merchant walkthrough') }}</div>
 <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('How a workspace connects its catalog') }}</h2>
 <p class="text-[11.5px] text-ink-500 mt-1">{{ __('Shown to merchants on /catalog. Reproduced here so you can sanity-check the flow.') }}</p>
 </div>
 <ol class="divide-y divide-paper-100">
 <li class="px-5 py-4 flex items-start gap-4">
 <span class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">1</span>
 <div class="min-w-0 flex-1">
 <div class="font-semibold text-[13px]">{{ __('Have an active WABA + Phone Number ID') }}</div>
 <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">{{ __('The merchant needs a verified WhatsApp Business Account in Meta Business Suite with at least one approved phone number. Without this, no catalog message can send.') }}</p>
 </div>
 </li>
 <li class="px-5 py-4 flex items-start gap-4">
 <span class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">2</span>
 <div class="min-w-0 flex-1">
 <div class="font-semibold text-[13px]">{{ __('Create a catalog in Meta Commerce Manager') }}</div>
 <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">{{ __('At') }} <a href="https://business.facebook.com/commerce" target="_blank" rel="noopener" class="text-wa-deep underline">{{ __('business.facebook.com/commerce') }}</a> {{ __(' → Create catalog → pick e-commerce. Add products manually, via CSV, or pull from Shopify/Woo. Catalog must be Public and locked to one currency.') }}</p>
 </div>
 </li>
 <li class="px-5 py-4 flex items-start gap-4">
 <span class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">3</span>
 <div class="min-w-0 flex-1">
 <div class="font-semibold text-[13px]">{{ __('Connect the catalog to the WABA') }}</div>
 <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">{{ __('In Business Suite → WhatsApp Manager → Catalog tab → "Choose a catalog" → pick the one created in Step 2. This is what enables Cloud API product messages.') }}</p>
 </div>
 </li>
 <li class="px-5 py-4 flex items-start gap-4">
 <span class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">4</span>
 <div class="min-w-0 flex-1">
 <div class="font-semibold text-[13px]">{{ __('Generate a System User token') }}</div>
 <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">{{ __('Business Settings → System Users → Add → Admin. Generate a token with') }} <span class="font-mono">{{ __('whatsapp_business_messaging') }}</span>, <span class="font-mono">{{ __('whatsapp_business_management') }}</span>, <span class="font-mono">{{ __('catalog_management') }}</span>, <span class="font-mono">{{ __('business_management') }}</span>. {{ __('Pick "Never" for expiry to avoid quarterly re-auth.') }}</p>
 </div>
 </li>
 <li class="px-5 py-4 flex items-start gap-4">
 <span class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">5</span>
 <div class="min-w-0 flex-1">
 <div class="font-semibold text-[13px]">{{ __('Paste credentials at /catalog') }}</div>
 <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">{{ __('WABA ID, Catalog ID, Phone Number ID, and the System User token go on the workspace-side') }} <a href="{{ url('/catalog') }}" class="text-wa-deep underline">{{ __('/catalog') }}</a> {{ __('page. :app then runs a test', ['app' => brand_name()]) }} <span class="font-mono">GET /{CATALOG_ID}?fields=name,product_count</span> {{ __('to verify the token has') }} <span class="font-mono">{{ __('catalog_management') }}</span> {{ __('on that catalog.') }}</p>
 </div>
 </li>
 </ol>
 </section>
 </div>

 {{-- Sticky reference sidebar --}}
 <aside class="space-y-4 lg:sticky lg:top-[88px]">
 <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
 <div class="px-4 py-3 border-b border-paper-200">
 <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Architecture') }}</div>
 <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Per-merchant vs platform') }}</h3>
 </div>
 <div class="p-4 space-y-2.5 text-[12px] text-ink-700">
 <div>
 <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Here (admin, one-time)') }}</div>
 <p class="text-ink-600 mt-0.5">{{ __('Feature toggle, Graph API version, default currency, optional Meta App credentials.') }}</p>
 </div>
 <div>
 <div class="font-semibold text-[12.5px] text-ink-900">{{ __('On /catalog (per merchant)') }}</div>
 <p class="text-ink-600 mt-0.5">{{ __('WABA ID, Catalog ID, Phone Number ID, System User token. Meta requires the catalog be owned by the merchant\'s Commerce Account, so there is no shared catalog.') }}</p>
 </div>
 </div>
 </div>

 <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
 <div class="px-4 py-3 border-b border-paper-200">
 <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Cloud API payloads') }}</div>
 <h3 class="font-serif text-[16px] leading-tight mt-0.5">{{ __('Three message types we send') }}</h3>
 </div>
 <div class="p-4 space-y-3 text-[11.5px] text-ink-700">
 <div>
 <div class="font-semibold text-[12px] text-ink-900">{{ __('SPM · Single Product') }}</div>
 <div class="font-mono text-[10.5px] text-ink-500 mt-0.5">{{ __('interactive.type = "product"') }}</div>
 </div>
 <div>
 <div class="font-semibold text-[12px] text-ink-900">{{ __('MPM · Multi-Product') }}</div>
 <div class="font-mono text-[10.5px] text-ink-500 mt-0.5">{{ __('interactive.type = "product_list" · max 10 sections, 30 items') }}</div>
 </div>
 <div>
 <div class="font-semibold text-[12px] text-ink-900">{{ __('Catalog CTA') }}</div>
 <div class="font-mono text-[10.5px] text-ink-500 mt-0.5">{{ __('interactive.type = "catalog_message"') }}</div>
 </div>
 </div>
 </div>

 <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
 <div class="px-4 py-3 border-b border-paper-200">
 <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Official docs') }}</div>
 <h3 class="font-serif text-[16px] leading-tight mt-0.5">{{ __('Reference') }}</h3>
 </div>
 <div class="p-4 space-y-1.5 text-[11.5px]">
 <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/guides/sell-products-and-services" target="_blank" rel="noopener" class="block text-wa-deep hover:underline">{{ __('Sell products and services →') }}</a>
 <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages" target="_blank" rel="noopener" class="block text-wa-deep hover:underline">{{ __('Cloud API messages reference →') }}</a>
 <a href="https://developers.facebook.com/docs/commerce-platform/catalog/batch-api" target="_blank" rel="noopener" class="block text-wa-deep hover:underline">{{ __('Catalog Batch API (push products) →') }}</a>
 <a href="https://developers.facebook.com/docs/graph-api/changelog" target="_blank" rel="noopener" class="block text-wa-deep hover:underline">{{ __('Graph API changelog →') }}</a>
 <a href="https://www.facebook.com/commerce/policies" target="_blank" rel="noopener" class="block text-wa-deep hover:underline">{{ __('Commerce policies →') }}</a>
 </div>
 </div>

 <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
 <div class="font-semibold text-[12.5px]">{{ __('Constraints') }}</div>
 <ul class="text-[11.5px] text-ink-600 mt-1 space-y-1 list-disc pl-4">
 <li>{{ __('Catalog must be Public') }}</li>
 <li>{{ __('One currency per catalog') }}</li>
 <li>{{ __('Images ≥ 500×500 px (JPG / PNG)') }}</li>
 <li>{{ __('No alcohol / adult / regulated goods') }}</li>
 <li>{{ __('Business Verification required for production') }}</li>
 </ul>
 </div>
 </aside>
 </section>
 </main>

 </form>

</x-layouts.admin>
