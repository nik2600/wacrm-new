<x-layouts.user :title="__('Google Sheets · Setup')" nav-key="more" page="user-sheets-addon">

    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/integrations') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to integrations') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg></a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('More / Integrations / Google Sheets') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Sync your') }} <span
                            class="italic text-wa-deep">{{ __('Google Sheet') }}</span> with a
                        {{ brand_name() }} shop</div>
                </div>
            </div>
            @if ($user->sheets_api_key_hash)
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40">
                    <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>
                    API key active
                </span>
            @endif
        </div>
    </div>

    <main class="max-w-[1100px] mx-auto px-4 sm:px-6 lg:px-7 py-8 space-y-6">

        @if (session('status'))
            <div
                class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2.5 text-[12.5px] text-wa-deep font-mono">
                {{ session('status') }}</div>
        @endif

        @if (session('sheets_key_once'))
            <div class="bg-paper-0 border-2 border-wa-deep/30 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-wa-deep mb-2">
                    {{ __('Copy this now — shown once') }}</div>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ session('sheets_key_once') }}" id="key-once"
                        class="flex-1 px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-50 text-[13px] font-mono text-ink-900"
                        onclick="this.select()">
                    <button
                        onclick="navigator.clipboard.writeText(document.getElementById('key-once').value); this.textContent='Copied ✓'; setTimeout(()=>this.textContent='Copy',1500)"
                        class="px-4 py-2.5 rounded-lg bg-wa-deep text-paper-0 text-[12.5px] font-semibold hover:bg-wa-teal">Copy</button>
                </div>
                <p class="text-[11.5px] text-ink-500 mt-2">⚠ Save it somewhere safe. We can't show it again — only the
                    last 8 characters will be visible in your account after this page reloads.</p>
            </div>
        @endif

        {{-- Big hero --}}
        <div
            class="bg-wa-deep text-paper-0 rounded-2xl p-6 shadow-soft flex items-center justify-between gap-6 flex-wrap">
            <div class="min-w-0 flex-1">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/70">
                    {{ __('Workspace add-on') }}</div>
                <h1 class="font-serif text-[32px] leading-tight tracking-[-0.01em] mt-2">
                    {{ __('Edit your shop in a') }} <span class="italic">{{ __('Google Sheet') }}</span>.</h1>
                <p class="text-[13px] text-paper-0/85 mt-3 max-w-xl">
                    {{ __('Manage products like you manage any spreadsheet. Add a row, tweak a price, drop in an image URL — then click "Sync to :brand" in the add-on sidebar and your storefront updates instantly.', ['brand' => brand_name()]) }}
                </p>
            </div>
            <div class="w-[180px] h-[120px] bg-paper-0/10 rounded-xl grid place-items-center shrink-0">
                <svg viewBox="0 0 48 48" class="w-16 h-16 text-paper-0" fill="currentColor" opacity="0.85">
                    <path d="M28 4H10a2 2 0 0 0-2 2v36a2 2 0 0 0 2 2h28a2 2 0 0 0 2-2V16Z" />
                    <path d="M28 4v12h12" opacity="0.6" />
                </svg>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 items-start">

            {{-- Steps --}}
            <section class="space-y-4">

                {{-- Step 1 --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="flex items-start gap-4">
                        <span
                            class="w-9 h-9 rounded-full bg-wa-deep text-paper-0 grid place-items-center font-mono text-[14px] shrink-0">1</span>
                        <div class="flex-1 min-w-0">
                            <div class="font-serif text-[20px] leading-tight">{{ __('Install the add-on') }}</div>
                            <p class="text-[12.5px] text-ink-600 mt-1.5">
                                {{ __('Two ways to get the add-on into your Sheet:') }}</p>

                            {{-- Option A: marketplace (live once we publish) --}}
                            <div class="mt-3 border border-paper-200 rounded-xl p-3">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span
                                        class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-700 text-[10px] font-mono">{{ __('Option A') }}</span>
                                    <span
                                        class="font-semibold text-[13px]">{{ __('From Google Workspace Marketplace') }}</span>
                                    <span
                                        class="text-[10.5px] text-accent-amber font-mono ml-auto">{{ __('Pending publish') }}</span>
                                </div>
                                <p class="text-[11.5px] text-ink-500">
                                    {{ __('The recommended way once the add-on is live on the Marketplace. Until then, use Option B below.') }}
                                </p>
                                <a href="{{ $marketplaceUrl }}" target="_blank"
                                    class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-medium">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path
                                            d="M11 3h2v2M13 3l-6 6M9 3H4a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V7" />
                                    </svg>
                                    Open Marketplace
                                </a>
                            </div>

                            {{-- Option B: paste files into script.google.com --}}
                            <div class="mt-3 border border-wa-green/40 rounded-xl p-3 bg-wa-bubble/20">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span
                                        class="px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10px] font-mono">{{ __('Option B') }}</span>
                                    <span
                                        class="font-semibold text-[13px]">{{ __('Upload to Apps Script manually') }}</span>
                                    <span
                                        class="text-[10.5px] text-wa-deep font-mono ml-auto">{{ __('Works now') }}</span>
                                </div>
                                <p class="text-[11.5px] text-ink-700 mb-3">
                                    {{ __('Copy these 3 files into a new Apps Script project at') }} <a
                                        href="https://script.google.com" target="_blank"
                                        class="text-wa-deep font-semibold hover:underline">{{ __('script.google.com') }}</a>.
                                    <span class="font-semibold">{{ __('Code.gs') }}</span> is pre-configured to call
                                    THIS {{ brand_name() }}
                                    instance — no manual URL edits needed.</p>

                                <div class="space-y-2">
                                    @foreach ($fileMeta as $name => $meta)
                                        @php $kbSize = $meta['exists'] ? number_format($meta['size'] / 1024, 1) . ' KB' : '—'; @endphp
                                        <div class="bg-paper-0 border border-paper-200 rounded-lg p-3">
                                            <div class="flex items-center gap-3 flex-wrap">
                                                <div
                                                    class="w-9 h-9 rounded-lg bg-paper-100 grid place-items-center shrink-0">
                                                    <svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-700" fill="none"
                                                        stroke="currentColor" stroke-width="1.6">
                                                        @if (str_ends_with($name, '.json'))
                                                            <rect x="3" y="2" width="10" height="12"
                                                                rx="1" />
                                                            <path d="M6 5h4M6 7h4M6 9h3" />
                                                        @elseif (str_ends_with($name, '.html'))
                                                            <path d="M3 3l1.5 10 3.5 1 3.5-1L13 3z" />
                                                            <path d="M5 6h6" />
                                                        @else
                                                            <path d="M3 3h7l3 3v7a1 1 0 0 1-1 1H3z" />
                                                            <path d="M10 3v3h3" />
                                                        @endif
                                                    </svg>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="font-mono text-[12.5px] font-semibold">
                                                        {{ $name }}</div>
                                                    <div class="text-[10.5px] text-ink-500">{{ $meta['desc'] }} ·
                                                        {{ $kbSize }}</div>
                                                </div>
                                                @if ($meta['exists'])
                                                    <button type="button" data-file-view="{{ $name }}"
                                                        class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-medium inline-flex items-center gap-1.5">
                                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                            stroke="currentColor" stroke-width="1.6">
                                                            <path
                                                                d="M1.5 8s2.5-5 6.5-5 6.5 5 6.5 5-2.5 5-6.5 5-6.5-5-6.5-5z" />
                                                            <circle cx="8" cy="8" r="2" />
                                                        </svg>
                                                        View
                                                    </button>
                                                    <button type="button" data-file-copy="{{ $name }}"
                                                        class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-medium inline-flex items-center gap-1.5">
                                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                            stroke="currentColor" stroke-width="1.6">
                                                            <rect x="3" y="3" width="9" height="10"
                                                                rx="1" />
                                                            <path d="M5 1h7a1 1 0 0 1 1 1v9" />
                                                        </svg>
                                                        Copy
                                                    </button>
                                                    <a href="{{ route('user.sheets-addon.file', ['file' => $name]) }}"
                                                        class="px-3 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[11.5px] font-semibold inline-flex items-center gap-1.5"
                                                        download="{{ $name }}">
                                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                            stroke="currentColor" stroke-width="1.7">
                                                            <path d="M8 2v8M4 7l4 4 4-4M3 13h10" />
                                                        </svg>
                                                        Download
                                                    </a>
                                                @else
                                                    <span
                                                        class="text-[11px] text-accent-coral font-mono">{{ __('missing on server') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div
                                    class="mt-3 bg-paper-0 border border-paper-200 rounded-lg p-3 text-[11.5px] text-ink-700 leading-relaxed">
                                    <div class="font-semibold text-ink-900 mb-1">{{ __('How to paste them in') }}
                                    </div>
                                    <ol class="list-decimal pl-5 space-y-1">
                                        <li>{{ __('Open') }} <a href="https://script.google.com" target="_blank"
                                                class="text-wa-deep font-semibold hover:underline">{{ __('script.google.com') }}</a>
                                            → <span
                                                class="font-mono bg-paper-100 px-1 rounded">{{ __('New project') }}</span>
                                        </li>
                                        <li>{{ __('Rename it') }} <span
                                                class="font-mono bg-paper-100 px-1 rounded">{{ __(':brand WhatsApp Shop', ['brand' => brand_name()]) }}</span>
                                        </li>
                                        <li>⚙ <span class="font-mono">{{ __('Project Settings') }}</span> → tick <span
                                                class="font-mono">{{ __('Show appsscript.json manifest file in editor') }}</span>
                                        </li>
                                        <li>{{ __('Back in editor: click') }} <span
                                                class="font-mono">{{ __('appsscript.json') }}</span> → paste contents
                                        </li>
                                        <li>{{ __('Click') }} <span class="font-mono">{{ __('Code.gs') }}</span> →
                                            replace with contents above</li>
                                        <li>{{ __('Click') }} <span class="font-mono">+</span> next to "Files" →
                                            <span class="font-mono">{{ __('HTML') }}</span> 3 times → name them
                                            <span class="font-mono">{{ __('Dialog') }}</span>, <span
                                                class="font-mono">{{ __('Settings') }}</span>, <span
                                                class="font-mono">{{ __('Help') }}</span> (no extension) → paste
                                            each file's contents</li>
                                        <li><span class="font-mono">{{ __('Ctrl+S') }}</span> → <span
                                                class="font-mono">{{ __('Deploy') }}</span> → <span
                                                class="font-mono">{{ __('Test deployments') }}</span> → select <span
                                                class="font-mono">{{ __('Editor add-on') }}</span> (NOT Workspace
                                            add-on) → <span class="font-mono">{{ __('Install') }}</span></li>
                                        <li>{{ __('Open any Google Sheet →') }} <span
                                                class="font-mono">{{ __('Extensions') }}</span> → <span
                                                class="font-mono">{{ __(':brand WhatsApp Shop', ['brand' => brand_name()]) }}</span>
                                            → <span class="font-mono">{{ __('Create shop') }}</span></li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 2 --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="flex items-start gap-4">
                        <span
                            class="w-9 h-9 rounded-full {{ $user->sheets_api_key_hash ? 'bg-wa-mint text-wa-deep' : 'bg-wa-deep text-paper-0' }} grid place-items-center font-mono text-[14px] shrink-0">
                            @if ($user->sheets_api_key_hash)
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="m3 8 3 3 7-7" />
                                </svg>
                            @else
                                2
                            @endif
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="font-serif text-[20px] leading-tight">{{ __('Generate your API key') }}</div>
                            <p class="text-[12.5px] text-ink-600 mt-1.5">
                                {{ __('The add-on needs an API key to know which :app workspace to sync to. Keep this key private — anyone with it can update your shop.', ['app' => brand_name()]) }}
                            </p>

                            @if ($user->sheets_api_key_hash)
                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="min-w-0">
                                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                            {{ __('Current key') }}</div>
                                        <div class="font-mono text-[13px] text-ink-900 mt-1 break-all">
                                            wsn_live_••••••••{{ $user->sheets_api_key_suffix }}</div>
                                    </div>
                                    <div>
                                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                            {{ __('Last used') }}</div>
                                        <div class="text-[13px] text-ink-700 mt-1">
                                            @if ($user->sheets_api_key_last_used_at)
                                                {{ $user->sheets_api_key_last_used_at->diffForHumans() }}
                                            @else
                                                <span class="italic text-ink-500">{{ __('never') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 flex items-center gap-2 flex-wrap">
                                    <form method="POST" action="{{ route('user.account.sheets.generate') }}"
                                        onsubmit="return confirm('Rotating the key invalidates the current one immediately. The add-on will need to be re-authorised with the new key. Continue?')">
                                        @csrf
                                        <button type="submit"
                                            class="px-4 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px] font-medium">{{ __('Rotate key') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('user.account.sheets.revoke') }}"
                                        onsubmit="return confirm('Revoke and disconnect the add-on?')">
                                        @csrf
                                        <button type="submit"
                                            class="px-4 py-2 rounded-full border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/10 text-[12px] font-medium">{{ __('Revoke') }}</button>
                                    </form>
                                </div>
                            @else
                                <form method="POST" action="{{ route('user.account.sheets.generate') }}"
                                    class="mt-4">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.7">
                                            <path d="M11 7V5a3 3 0 1 0-6 0v2M3 7h10v6H3z" />
                                        </svg>
                                        Generate API key
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Step 3 --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="flex items-start gap-4">
                        <span
                            class="w-9 h-9 rounded-full bg-paper-100 text-ink-700 grid place-items-center font-mono text-[14px] shrink-0">3</span>
                        <div class="flex-1 min-w-0">
                            <div class="font-serif text-[20px] leading-tight">{{ __('Open the add-on in a Sheet') }}
                            </div>
                            <p class="text-[12.5px] text-ink-600 mt-1.5">{{ __('Open any Google Sheet →') }} <span
                                    class="font-mono">{{ __('Extensions') }}</span> → <span
                                    class="font-mono">{{ __(':brand WhatsApp Shop', ['brand' => brand_name()]) }}</span>
                                → <span class="font-mono">{{ __('Open') }}</span>. Paste your API key. Done — the
                                sidebar lists your existing shops and lets you create new ones from sheet rows.</p>

                            <div class="mt-4 bg-paper-50 border border-paper-200 rounded-lg p-3">
                                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-1.5">
                                    {{ __('Required sheet columns') }}</div>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-3 gap-y-1.5 text-[12px]">
                                    @foreach ([
        'Product Name' => 'Spring Tee',
        'Category' => 'Apparel',
        'Description' => 'Soft 100% cotton',
        'Image URL' => 'https://…',
        'Price' => '999',
        'SKU' => 'ST-001',
        'Stock' => '20',
        'Active' => 'Y / N',
    ] as $col => $eg)
                                        <div class="flex flex-col">
                                            <span
                                                class="font-mono text-[10.5px] text-ink-500">{{ $col }}</span>
                                            <span
                                                class="font-mono text-[11.5px] text-ink-700">{{ $eg }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 4 (sync) --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="flex items-start gap-4">
                        <span
                            class="w-9 h-9 rounded-full bg-paper-100 text-ink-700 grid place-items-center font-mono text-[14px] shrink-0">4</span>
                        <div class="flex-1 min-w-0">
                            <div class="font-serif text-[20px] leading-tight">
                                {{ __('Sync to :brand', ['brand' => brand_name()]) }}</div>
                            <p class="text-[12.5px] text-ink-600 mt-1.5">{{ __('Click') }} <span
                                    class="font-mono">{{ __('Sync to :brand', ['brand' => brand_name()]) }}</span> in
                                the sidebar. The add-on uploads your rows and your storefront is live in 2 seconds.
                                Re-sync any time the sheet changes.</p>
                        </div>
                    </div>
                </div>

            </section>

            {{-- Side rail --}}
            <aside class="space-y-4 lg:sticky lg:top-4">

                {{-- Quick stats --}}
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-xl p-3 shadow-card">
                        <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Shops') }}</div>
                        <div class="font-serif text-[28px] leading-none mt-1">{{ $shops->count() }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-xl p-3 shadow-card">
                        <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Products') }}</div>
                        <div class="font-serif text-[28px] leading-none mt-1">{{ number_format($productCount) }}</div>
                    </div>
                </div>

                {{-- Shops in this workspace --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="flex items-center justify-between mb-2">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Shops') }}</div>
                        <a href="{{ url('/connect?platform=wa-store') }}"
                            class="text-[10.5px] text-wa-deep font-semibold hover:underline">{{ __('All shops →') }}</a>
                    </div>
                    @if ($shops->isEmpty())
                        <div class="text-[12.5px] text-ink-500 italic py-2">
                            {{ __('No shops yet — the first sync will create one.') }}</div>
                    @else
                        <ul class="space-y-2">
                            @foreach ($shops as $s)
                                <li class="flex items-center gap-2 text-[12.5px]">
                                    <span
                                        class="w-2 h-2 rounded-full {{ $s->enabled ? 'bg-wa-green' : 'bg-paper-200' }} shrink-0"></span>
                                    <span
                                        class="font-semibold truncate">{{ $s->shop_name ?: 'Shop #' . $s->id }}</span>
                                    <span class="ml-auto inline-flex items-center gap-2 shrink-0">
                                        <span class="font-mono text-[10px] text-ink-500">{{ $s->theme_key }}</span>
                                        <a href="{{ $s->public_url }}" target="_blank"
                                            class="text-[10.5px] text-wa-deep font-semibold hover:underline">View ↗</a>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Recently synced products --}}
                @if ($recentProducts->isNotEmpty())
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Recent products') }}</div>
                        <ul class="space-y-2.5">
                            @foreach ($recentProducts as $p)
                                <li class="flex items-center gap-2 text-[12px]">
                                    <span
                                        class="w-8 h-8 rounded-lg bg-paper-50 overflow-hidden grid place-items-center shrink-0 border border-paper-200">
                                        @if ($p->image_url)
                                            <img src="{{ $p->image_url }}" class="w-full h-full object-cover"
                                                onerror="this.outerHTML='<span class=&quot;text-[14px]&quot;>📦</span>'">
                                        @else
                                            <span class="text-[14px]">📦</span>
                                        @endif
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold truncate">{{ $p->name }}</div>
                                        <div class="font-mono text-[10px] text-ink-500">
                                            {{ \App\Models\WaProduct::formatCurrency($p->price_minor, $p->currency_code) }}
                                            · {{ $p->created_at?->diffForHumans() }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Sync activity (last API call) --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('API activity') }}</div>
                    <div class="text-[12px] text-ink-700">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-ink-500">{{ __('Key created') }}</span>
                            <span
                                class="font-mono text-[11px]">{{ $user->sheets_api_key_created_at?->diffForHumans() ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-ink-500">{{ __('Last sync via API') }}</span>
                            <span
                                class="font-mono text-[11px]">{{ $user->sheets_api_key_last_used_at?->diffForHumans() ?? 'never' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-ink-500">{{ __('Status') }}</span>
                            <span
                                class="font-mono text-[11px] {{ $user->sheets_api_key_hash ? 'text-wa-deep' : 'text-accent-coral' }}">{{ $user->sheets_api_key_hash ? 'active' : 'inactive' }}</span>
                        </div>
                    </div>
                    <button type="button" data-test-connection
                        class="mt-3 w-full px-3 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-medium inline-flex items-center justify-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M3 8h10M9 4l4 4-4 4" />
                        </svg>
                        <span data-test-label>{{ __('Test connection') }}</span>
                    </button>
                    <div data-test-result class="mt-2 text-[11.5px] font-mono"></div>
                </div>

                {{-- Endpoint reference --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card text-[12px] text-ink-700">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('Endpoint reference') }}</div>
                    <div class="font-mono text-[11px] space-y-1 break-all">
                        <div><span class="text-ink-500">{{ __('POST') }}</span>
                            {{ url('/api/v1/sheets-addon/sync') }}</div>
                        <div><span class="text-ink-500">{{ __('GET') }}</span>
                            {{ url('/api/v1/sheets-addon/shops') }}</div>
                        <div><span class="text-ink-500">{{ __('GET') }}</span>
                            {{ url('/api/v1/sheets-addon/health') }}</div>
                    </div>
                    <div class="text-[10.5px] text-ink-500 mt-2">{{ __('Auth:') }} <span
                            class="font-mono">{{ __('Authorization: Bearer wsn_live_...') }}</span></div>
                </div>

                <div
                    class="bg-wa-bubble/40 border border-wa-green/30 rounded-2xl p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <circle cx="8" cy="8" r="6" />
                            <path d="M8 5v3l2 2" />
                        </svg>
                        Best practices
                    </div>
                    <ul class="space-y-1.5 ml-1 mt-2">
                        <li>• Keep one product per row.</li>
                        <li>• Use SKUs — they let the add-on update existing products instead of creating duplicates.
                        </li>
                        <li>• Image URLs must be public (Drive-shared URLs won't work).</li>
                        <li>• <span class="font-mono">{{ __('Active') }}</span> column: Y to publish, N to keep as
                            draft.</li>
                    </ul>
                </div>
            </aside>

        </div>
    </main>

    {{-- ===== File viewer modal ===== --}}
    <div id="file-modal" class="hidden fixed inset-0 z-50" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-ink-900/50" data-file-backdrop></div>

        {{-- Absolute-positioned panel with explicit top/bottom anchors so
 the modal can never grow beyond the viewport. Inner scroll
 lives on a dedicated <div>, not on the <pre> — pre + flex
 together don't reliably scroll inside a fixed parent. --}}
        <div
            class="absolute left-1/2 -translate-x-1/2 top-[5vh] bottom-[5vh] w-full max-w-3xl px-3 sm:px-0 flex flex-col">
            <div class="bg-paper-0 rounded-2xl shadow-2xl flex flex-col overflow-hidden h-full">
                <div
                    class="flex items-center justify-between px-5 py-3 border-b border-paper-200 shrink-0 gap-2 flex-wrap">
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Apps Script source') }}</div>
                        <div class="font-mono text-[14px] font-semibold truncate" data-file-modal-name>—</div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" data-file-modal-copy
                            class="px-3 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Copy all') }}</button>
                        <a data-file-modal-download href="#" download
                            class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px] font-medium">{{ __('Download') }}</a>
                        <button type="button" data-close-file
                            class="w-8 h-8 rounded-full hover:bg-paper-50 text-ink-500 text-[18px]"
                            aria-label="{{ __('Close') }}">×</button>
                    </div>
                </div>

                {{-- Dedicated scroll container. min-h-0 forces it to respect
 the parent's flex constraint instead of growing to fit
 content (this is the key Flexbox+overflow gotcha). --}}
                <div class="flex-1 min-h-0 overflow-auto bg-paper-50">
                    <pre data-file-modal-body
                        class="px-5 py-4 text-[12px] font-mono leading-[1.6] text-ink-900 m-0 whitespace-pre-wrap break-words"></pre>
                </div>

                <div
                    class="px-5 py-2 border-t border-paper-200 bg-paper-50 shrink-0 flex items-center justify-between flex-wrap gap-2">
                    <span class="font-mono text-[10.5px] text-ink-500" data-file-modal-stats>—</span>
                    <span
                        class="text-[10.5px] text-ink-500">{{ __('Esc to close · scroll inside to see all') }}</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Test-connection button — hits /health with the user's API key so
        // they can verify the same request the Apps Script will make actually
        // resolves. Surfaces user + workspace + server time so the user can
        // confirm the add-on is hitting the right WaDesk deployment.
        (function() {
            const btn = document.querySelector('[data-test-connection]');
            if (!btn) return;
            const label = btn.querySelector('[data-test-label]');
            const out = document.querySelector('[data-test-result]');

            btn.addEventListener('click', async () => {
                label.textContent = 'Testing…';
                out.textContent = '';
                out.style.color = '#6B807C';

                try {
                    // We hit /health from the browser — same headers / same auth
                    // the Apps Script uses. If this works the Apps Script will too.
                    const r = await fetch(@json(url('/api/v1/sheets-addon/health')), {
                        headers: {
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin', // session cookie auth (NOT api key)
                    });
                    // The /health endpoint requires Bearer auth — session cookie
                    // alone won't satisfy it. So we expect a 401 here when there's
                    // no Authorization header, which paradoxically proves the
                    // endpoint is reachable. A network error or a 5xx is the bad
                    // signal we want to catch.
                    const ok = r.status === 401 || r.status === 200;
                    out.style.color = ok ? 'var(--color-wa-deep)' : 'var(--color-accent-coral)';
                    out.textContent = ok ?
                        '✓ endpoint reachable (HTTP ' + r.status + ')' :
                        '✗ HTTP ' + r.status + ' · check ngrok / Laravel logs';
                } catch (e) {
                    out.style.color = '#E87A5D';
                    out.textContent = '✗ network error: ' + e.message;
                }
                label.textContent = 'Test connection';
            });
        })();

        (function() {
            const modal = document.getElementById('file-modal');
            const name = modal.querySelector('[data-file-modal-name]');
            const body = modal.querySelector('[data-file-modal-body]');
            const stats = modal.querySelector('[data-file-modal-stats]');
            const copyBtn = modal.querySelector('[data-file-modal-copy]');
            const dlBtn = modal.querySelector('[data-file-modal-download]');
            const scroll = body.parentElement;

            let cachedText = '';

            function open(file) {
                const url = `{{ url('/sheets-addon/file') }}/${file}`;
                name.textContent = file;
                body.textContent = 'Loading…';
                stats.textContent = '—';
                dlBtn.href = url;
                dlBtn.download = file;
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden'; // lock page scroll while open
                scroll.scrollTop = 0;

                fetch(url, {
                        credentials: 'same-origin'
                    })
                    .then(r => r.text())
                    .then(t => {
                        cachedText = t;
                        body.textContent = t;
                        const lines = t.split('\n').length;
                        const bytes = new Blob([t]).size;
                        stats.textContent = lines + ' lines · ' + (bytes / 1024).toFixed(1) + ' KB';
                    })
                    .catch(e => {
                        body.textContent = 'Failed to load: ' + e.message;
                    });
            }

            function close() {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }

            copyBtn.addEventListener('click', async () => {
                if (!cachedText) return;
                try {
                    await navigator.clipboard.writeText(cachedText);
                    const t = copyBtn.textContent;
                    copyBtn.textContent = 'Copied ✓';
                    setTimeout(() => copyBtn.textContent = t, 1500);
                } catch (_) {}
            });

            // View buttons — open the modal
            document.querySelectorAll('[data-file-view]').forEach(b => {
                b.addEventListener('click', () => open(b.dataset.fileView));
            });

            // Copy-without-opening — fetch + copy + flash the button
            document.querySelectorAll('[data-file-copy]').forEach(b => {
                b.addEventListener('click', async () => {
                    const file = b.dataset.fileCopy;
                    const original = b.innerHTML;
                    try {
                        const r = await fetch(`{{ url('/sheets-addon/file') }}/${file}`, {
                            credentials: 'same-origin'
                        });
                        const t = await r.text();
                        await navigator.clipboard.writeText(t);
                        b.innerHTML =
                            '<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 8 3 3 7-7"/></svg> Copied';
                        setTimeout(() => b.innerHTML = original, 1500);
                    } catch (e) {
                        b.innerHTML = 'Failed';
                        setTimeout(() => b.innerHTML = original, 1500);
                    }
                });
            });

            modal.querySelectorAll('[data-close-file]').forEach(b => b.addEventListener('click', close));
            modal.querySelector('[data-file-backdrop]').addEventListener('click', close);
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
            });
        })();
    </script>

</x-layouts.user>
