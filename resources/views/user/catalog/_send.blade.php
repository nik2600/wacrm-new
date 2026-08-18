@if ($senders->isEmpty())
    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card flex items-start gap-5">
        <div class="w-12 h-12 rounded-xl bg-wa-bubble/70 grid place-items-center shrink-0">
            <svg viewBox="0 0 24 24" class="w-6 h-6 text-wa-deep" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M20.5 12A8.5 8.5 0 1 1 4.6 16.3L3.5 20.5l4.3-1.1A8.5 8.5 0 0 0 20.5 12Z" />
            </svg>
        </div>
        <div class="flex-1">
            <div class="font-serif text-[22px] leading-tight">{{ __('Connect a device first') }}</div>
            <p class="text-[12.5px] text-ink-600 mt-1.5">
                {{ __('You need a connected WhatsApp device to send catalog messages.') }}</p>
            <button type="button" data-connect-device
                class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10" /></svg>
                {{ __('Connect a device') }}
            </button>
        </div>
    </div>
@else
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5 items-start" data-send-form>

        <div class="space-y-5">

            {{-- ═══ Step 1: Recipients (multi-source like /wa-campaigns/create) ═══ --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-center gap-2.5 mb-4">
                    <span
                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                    <span class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Recipients') }}</span>
                    <span class="font-mono text-[10px] text-ink-500" data-recipient-count>0</span>
                </div>

                {{-- Mode picker --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-4" data-mode-tabs>
                    <label data-mode-tab="groups"
                        class="recipient-mode-tile border border-wa-deep bg-[#F0F8F6] rounded-xl p-3 cursor-pointer">
                        <input class="sr-only" type="radio" name="recipient_mode" value="groups" checked>
                        <div class="font-serif text-[15px] leading-tight">{{ __('Contact groups') }}</div>
                        <p class="mt-1 text-[11px] text-ink-500">{{ __('Use saved segments.') }}</p>
                    </label>
                    <label data-mode-tab="contacts"
                        class="recipient-mode-tile border border-paper-200 rounded-xl p-3 cursor-pointer hover:bg-paper-50">
                        <input class="sr-only" type="radio" name="recipient_mode" value="contacts">
                        <div class="font-serif text-[15px] leading-tight">{{ __('Individual contacts') }}</div>
                        <p class="mt-1 text-[11px] text-ink-500">{{ __('Pick specific people.') }}</p>
                    </label>
                    <label data-mode-tab="manual"
                        class="recipient-mode-tile border border-paper-200 rounded-xl p-3 cursor-pointer hover:bg-paper-50">
                        <input class="sr-only" type="radio" name="recipient_mode" value="manual">
                        <div class="font-serif text-[15px] leading-tight">{{ __('Manual numbers') }}</div>
                        <p class="mt-1 text-[11px] text-ink-500">{{ __('Paste, one per line.') }}</p>
                    </label>
                </div>

                {{-- Pane: groups --}}
                <div data-mode-pane="groups">
                    @if ($groups->isEmpty())
                        <div
                            class="border border-dashed border-paper-200 rounded-lg p-4 text-center text-[12px] text-ink-500">
                            No contact groups yet. Create one from the <a href="{{ url('/contacts') }}"
                                class="text-wa-deep font-semibold hover:underline">{{ __('Contacts') }}</a> page first.
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach ($groups as $g)
                                @php $memberCount = $groupCounts[$g->id] ?? 0; @endphp
                                <label
                                    class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                                    <span class="min-w-0">
                                        <span
                                            class="block text-[12.5px] font-semibold truncate">{{ $g->user_group }}</span>
                                        <span
                                            class="block text-[10.5px] text-ink-500">{{ number_format($memberCount) }}
                                            {{ __('contacts') }}</span>
                                    </span>
                                    <input type="checkbox" name="group_ids[]" value="{{ $g->id }}"
                                        data-recipient-count="{{ $memberCount }}" class="w-4 h-4 accent-wa-deep">
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Pane: contacts --}}
                <div data-mode-pane="contacts" class="hidden">
                    @if ($contacts->isEmpty())
                        <div
                            class="border border-dashed border-paper-200 rounded-lg p-4 text-center text-[12px] text-ink-500">
                            No contacts yet. Add them in the <a href="{{ url('/contacts') }}"
                                class="text-wa-deep font-semibold hover:underline">{{ __('Contacts') }}</a> page.
                        </div>
                    @else
                        <input type="search" data-contact-search placeholder="{{ __('Search contacts…') }}"
                            class="w-full mb-2 px-3 py-2 border border-paper-200 rounded-lg bg-paper-50 text-[12px] focus:outline-none focus:border-wa-deep">
                        <div class="grid grid-cols-2 lg:grid-cols-3 gap-1 max-h-[220px] overflow-y-auto border border-paper-200 rounded-lg p-2 bg-paper-50/40"
                            data-contact-list>
                            @foreach ($contacts as $contact)
                                <label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-paper-0 cursor-pointer"
                                    data-contact-name="{{ strtolower($contact->name ?: '') }}"
                                    data-contact-mobile="{{ $contact->mobile }}">
                                    <input type="checkbox" name="contact_ids[]" value="{{ $contact->id }}"
                                        class="w-3.5 h-3.5 accent-wa-deep" data-recipient-count="1">
                                    <span
                                        class="text-[12px] text-ink-800 truncate">{{ $contact->name ?: mask_phone($contact->mobile) }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Pane: manual --}}
                <div data-mode-pane="manual" class="hidden">
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Paste phone numbers') }}</label>
                    <textarea name="manual_numbers" rows="6" data-manual
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] leading-relaxed focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                        placeholder="{{ __('One number per line — include the country code') }}"></textarea>
                    <div class="text-[10.5px] text-ink-500 mt-1">
                        {{ __('One per line, comma, or semicolon. Country code required (with or without') }}
                        <code>+</code>).</div>
                </div>
            </section>

            {{-- ═══ Step 2: Format ═══ --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-center gap-2.5 mb-4">
                    <span
                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                    <span
                        class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Format & sender') }}</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-4" data-mode-picker>
                    <label class="block cursor-pointer">
                        <input type="radio" name="mode" value="spm" checked class="sr-only peer">
                        <div
                            class="border peer-checked:border-wa-deep peer-checked:bg-wa-mint/30 peer-checked:ring-2 peer-checked:ring-wa-deep/20 rounded-xl p-3 transition">
                            <div class="font-semibold text-[13px]">{{ __('Single product') }}</div>
                            <div class="text-[11px] text-ink-500 mt-0.5">{{ __('One product card') }}</div>
                        </div>
                    </label>
                    <label class="block cursor-pointer">
                        <input type="radio" name="mode" value="mpm" class="sr-only peer">
                        <div
                            class="border peer-checked:border-wa-deep peer-checked:bg-wa-mint/30 peer-checked:ring-2 peer-checked:ring-wa-deep/20 rounded-xl p-3 transition">
                            <div class="font-semibold text-[13px]">{{ __('Product list (carousel)') }}</div>
                            <div class="text-[11px] text-ink-500 mt-0.5">{{ __('Up to 30 products') }}</div>
                        </div>
                    </label>
                    <label class="block cursor-pointer">
                        <input type="radio" name="mode" value="link" class="sr-only peer">
                        <div
                            class="border peer-checked:border-wa-deep peer-checked:bg-wa-mint/30 peer-checked:ring-2 peer-checked:ring-wa-deep/20 rounded-xl p-3 transition">
                            <div class="font-semibold text-[13px]">{{ __('Catalog link') }}</div>
                            <div class="text-[11px] text-ink-500 mt-0.5">{{ __('Plain text · works anytime') }}</div>
                        </div>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Send from') }}</span>
                        <x-sender-picker :senders="$senders" name="sender" data-sender
                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep" />
                    </label>
                    @if ($shops->isNotEmpty())
                        <label class="block">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('From shop') }} <span
                                    class="text-ink-500 font-normal">(branding context)</span></span>
                            <select name="shop_id" data-shop-id
                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep">
                                @foreach ($shops as $shop)
                                    <option value="{{ $shop->id }}">
                                        {{ $shop->shop_name ?: 'Shop #' . $shop->id }} · {{ $shop->theme_key }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                </div>
            </section>

            {{-- ═══ Step 3: Products (shop-scoped, compact cards) ═══ --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card" data-product-section>
                <div class="flex items-center gap-2.5 mb-4">
                    <span
                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                    <span class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Products') }} <span
                            class="font-mono text-[10px] text-ink-500 font-normal" data-selected-summary>(0
                            selected)</span></span>
                    <div class="relative">
                        <svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <circle cx="7" cy="7" r="5" />
                            <path d="m11 11 3 3" />
                        </svg>
                        <input type="search" data-product-search placeholder="{{ __('Search products…') }}"
                            class="pl-9 pr-3 py-1.5 border border-paper-200 rounded-full bg-paper-50 text-[12px] focus:outline-none focus:border-wa-deep w-52">
                    </div>
                </div>

                <div class="text-[11.5px] text-ink-500 mb-3 flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <rect x="3" y="5" width="10" height="9" rx="1" />
                        <path d="M5 5a3 3 0 1 1 6 0" />
                    </svg>
                    Showing products from <b
                        data-current-shop-name>{{ $shops->firstWhere('id', $selectedShopId)?->shop_name ?: '—' }}</b>.
                    Change the <b>From shop</b> picker above to see another shop's catalog.
                </div>

                <div data-product-render-area>
                    @if ($productsByCategory->isEmpty())
                        <div
                            class="border border-dashed border-paper-200 rounded-lg p-6 text-center text-[12px] text-ink-500">
                            No active products in this shop. <a href="{{ url('/store/products/create') }}"
                                class="text-wa-deep font-semibold hover:underline">{{ __('Add one →') }}</a>
                        </div>
                    @else
                        @include('user.catalog._product-grid', [
                            'productsByCategory' => $productsByCategory,
                        ])
                    @endif
                </div>
            </section>

            {{-- ═══ Step 4: Message + send ═══ --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card space-y-3">
                <div class="flex items-center gap-2.5 mb-1">
                    <span
                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                    <span
                        class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Message & send') }}</span>
                </div>
                <label class="block">
                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Message text') }} <span
                            class="text-ink-500 font-normal">(optional)</span></span>
                    <textarea data-message-body rows="2" maxlength="1024"
                        placeholder="{{ __('e.g. Here are some products you might like — tap any to learn more.') }}"
                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></textarea>
                </label>

                <div data-send-feedback class="hidden rounded-lg px-3 py-2 text-[12px] font-mono"></div>

                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('user.catalog.activity') }}"
                        class="text-[11.5px] text-wa-deep font-semibold hover:underline mr-auto">{{ __('View recent sends →') }}</a>
                    <button type="button" data-send-btn
                        class="px-5 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center gap-2 disabled:opacity-50">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="currentColor">
                            <path d="M2 14l13-6L2 2v5l9 1-9 1z" />
                        </svg>
                        <span data-send-label>{{ __('Send →') }}</span>
                    </button>
                </div>
            </section>
        </div>

        {{-- ═══ Side rail ═══ --}}
        <aside class="space-y-4 lg:sticky lg:top-4">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Summary') }}</div>
                <ul class="mt-2 space-y-1.5 text-[12.5px]">
                    <li class="flex justify-between"><span class="text-ink-500">{{ __('Recipients') }}</span><b
                            data-summary-recipients>0</b></li>
                    <li class="flex justify-between"><span class="text-ink-500">{{ __('Products') }}</span><b
                            data-summary-products>0</b></li>
                    <li class="flex justify-between"><span class="text-ink-500">{{ __('Format') }}</span><b
                            data-summary-format>SPM</b></li>
                </ul>
            </div>

            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Catalog') }}
                </div>
                @if ($catalog)
                    <div class="font-serif text-[15px] leading-tight">{{ $catalog->catalog_name ?: 'Meta Commerce' }}
                    </div>
                    <div class="text-[11px] text-ink-500 mt-0.5">
                        {{ strtoupper(str_replace('_', ' ', $catalog->provider)) }} · {{ $totalProducts }}
                        {{ __('products') }}</div>
                @else
                    <div class="font-serif text-[15px] leading-tight">{{ __('Unofficial API (carousel)') }}</div>
                    <div class="text-[11px] text-ink-500 mt-0.5">{{ $totalProducts }} products · <a
                            href="{{ route('user.catalog.index') }}"
                            class="text-wa-deep font-semibold hover:underline">{{ __('Link Meta →') }}</a></div>
                @endif
            </div>

            @if ($recentSends->isNotEmpty())
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('Recent sends') }}</div>
                    <ul class="space-y-2">
                        @foreach ($recentSends->take(5) as $r)
                            <li class="text-[11.5px] flex items-start gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-wa-green mt-1.5 shrink-0"></span>
                                <div class="min-w-0">
                                    <div class="font-medium truncate">{{ mask_phone($r->to_number) ?: '—' }}</div>
                                    <div class="text-[10px] text-ink-500 font-mono">
                                        {{ $r->created_at->diffForHumans() }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div
                class="bg-wa-bubble/40 border border-wa-green/30 rounded-2xl p-4 text-[11.5px] text-ink-700 leading-relaxed">
                <div class="font-semibold text-ink-900 mb-1">24-hour window</div>
                <p>{{ __('SPM & list mode require the buyer to have messaged you in the last 24h.') }} <b>Catalog
                        link</b> mode works anytime.</p>
            </div>
        </aside>
    </div>

    <style>
        .recipient-mode-tile.is-active {
            border-color: rgb(7 94 84);
            background: #F0F8F6;
        }
    </style>

    <script>
        // Shop → category → product[] map, so swapping shops in the dropdown
        // repaints the picker without a round-trip to the server.
        window.SF_CATALOG_PRODUCTS = @json($productMapForJs ?? []);
        window.SF_SHOPS = @json($shops->pluck('shop_name', 'id'));
    </script>
    <script>
        (function() {
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
            const $root = document.querySelector('[data-send-form]');
            const $tabs = $root.querySelector('[data-mode-tabs]');
            const $panes = $root.querySelectorAll('[data-mode-pane]');
            const $modePicker = $root.querySelector('[data-mode-picker]');
            const $productSection = $root.querySelector('[data-product-section]');
            const $productSearch = $root.querySelector('[data-product-search]');
            const $contactSearch = $root.querySelector('[data-contact-search]');
            const $msg = $root.querySelector('[data-message-body]');
            const $sendBtn = $root.querySelector('[data-send-btn]');
            const $sendLbl = $root.querySelector('[data-send-label]');
            const $feedback = $root.querySelector('[data-send-feedback]');
            const $summary = $root.querySelector('[data-selected-summary]');
            const $sumR = $root.querySelector('[data-summary-recipients]');
            const $sumP = $root.querySelector('[data-summary-products]');
            const $sumF = $root.querySelector('[data-summary-format]');
            const $countLbl = $root.querySelector('[data-recipient-count]');

            let mode = 'spm';

            // ── Tab switcher ──
            $tabs?.querySelectorAll('label[data-mode-tab]').forEach(tile => {
                tile.addEventListener('click', () => {
                    const active = tile.dataset.modeTab;
                    $tabs.querySelectorAll('label[data-mode-tab]').forEach(t => {
                        t.classList.toggle('border-wa-deep', t === tile);
                        t.classList.toggle('bg-[#F0F8F6]', t === tile);
                        t.classList.toggle('border-paper-200', t !== tile);
                        t.classList.toggle('hover:bg-paper-50', t !== tile);
                    });
                    $panes.forEach(p => p.classList.toggle('hidden', p.dataset.modePane !== active));
                });
            });

            // ── Mode picker (spm/mpm/link) ──
            $modePicker.querySelectorAll('input[name=mode]').forEach(r => {
                r.addEventListener('change', () => {
                    mode = r.value;
                    if (mode === 'link') {
                        $productSection.style.display = 'none';
                        $sendLbl.textContent = 'Send catalog link →';
                        $sumF.textContent = 'Catalog link';
                    } else {
                        $productSection.style.display = '';
                        $sendLbl.textContent = mode === 'spm' ? 'Send product →' : 'Send carousel →';
                        $sumF.textContent = mode === 'spm' ? 'Single product' : 'Multi-product list';
                        if (mode === 'spm') {
                            // Keep only first checked product
                            const picks = $root.querySelectorAll('.product-pick:checked');
                            picks.forEach((p, i) => {
                                if (i > 0) p.checked = false;
                            });
                        }
                    }
                    updateSummary();
                });
            });

            // ── Contact search ──
            $contactSearch?.addEventListener('input', () => {
                const q = $contactSearch.value.toLowerCase();
                $root.querySelectorAll('[data-contact-name]').forEach(el => {
                    const hit = !q || el.dataset.contactName.includes(q) || el.dataset.contactMobile
                        .includes(q);
                    el.style.display = hit ? '' : 'none';
                });
            });

            // ── Product search ──
            $productSearch.addEventListener('input', () => {
                const q = $productSearch.value.toLowerCase();
                $root.querySelectorAll('[data-product-card]').forEach(el => {
                    const hit = !q || el.dataset.name.includes(q) || el.dataset.sku.includes(q);
                    el.style.display = hit ? '' : 'none';
                });
            });

            // ── Shop dropdown → repaint product grid ──
            const $shopId = $root.querySelector('[data-shop-id]');
            const $renderArea = $root.querySelector('[data-product-render-area]');
            const $currentShopName = $root.querySelector('[data-current-shop-name]');
            $shopId?.addEventListener('change', () => repaintProductsForShop($shopId.value));

            function repaintProductsForShop(shopId) {
                const byCategory = (window.SF_CATALOG_PRODUCTS || {})[shopId] || {};
                const cats = Object.keys(byCategory);
                if ($currentShopName) $currentShopName.textContent = (window.SF_SHOPS || {})[shopId] || '—';

                if (cats.length === 0) {
                    $renderArea.innerHTML =
                        '<div class="border border-dashed border-paper-200 rounded-lg p-6 text-center text-[12px] text-ink-500">No active products in this shop. <a href="{{ url('/store/products/create') }}" class="text-wa-deep font-semibold hover:underline">Add one →</a></div>';
                    return;
                }

                let html = '<div class="space-y-2" data-product-categories>';
                cats.forEach(catName => {
                    const items = byCategory[catName] || [];
                    html += '<details open class="border border-paper-200 rounded-xl bg-paper-50/30">';
                    html +=
                        '<summary class="cursor-pointer flex items-center justify-between px-3 py-2 list-none">';
                    html += '<span class="font-mono text-[11px] uppercase tracking-[0.14em] text-ink-500">' +
                        esc(catName) + ' <span class="text-ink-700">· ' + items.length + '</span></span>';
                    html += '<span class="text-[10.5px] text-ink-500 font-mono">▾</span></summary>';
                    html +=
                        '<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-1.5 p-2 border-t border-paper-200" data-product-grid>';
                    items.forEach(p => {
                        html += '<label data-product-card data-name="' + esc((p.name || '')
                            .toLowerCase()) + '" data-sku="' + esc((p.sku || '').toLowerCase()) +
                            '" data-retailer-id="' + esc(p.retailer_id) +
                            '" class="relative block cursor-pointer">';
                        html += '<input type="checkbox" name="product_ids[]" value="' + p.id +
                            '" class="sr-only peer product-pick">';
                        html +=
                            '<div class="border peer-checked:border-wa-deep peer-checked:ring-2 peer-checked:ring-wa-deep/20 peer-checked:bg-wa-mint/30 rounded-lg overflow-hidden bg-paper-0 transition">';
                        html +=
                        '<div class="h-20 bg-paper-50 overflow-hidden grid place-items-center">';
                        if (p.image_url) {
                            html += '<img src="' + esc(p.image_url) +
                                '" class="w-full h-full object-cover" loading="lazy" onerror="this.outerHTML=\'<span class=&quot;text-[20px]&quot;>📦</span>\'">';
                        } else {
                            html += '<span class="text-[20px]">📦</span>';
                        }
                        html +=
                            '</div><div class="px-1.5 py-1"><div class="text-[10.5px] font-semibold leading-tight truncate">' +
                            esc(p.name) + '</div>';
                        html += '<div class="text-[9.5px] font-mono text-wa-deep">' + esc(p
                            .price_display) + '</div></div></div></label>';
                    });
                    html += '</div></details>';
                });
                html += '</div>';
                $renderArea.innerHTML = html;
                rebindProductCheckboxes();
            }

            function rebindProductCheckboxes() {
                $root.querySelectorAll('.product-pick').forEach(cb => {
                    if (cb.__bound) return;
                    cb.__bound = true;
                    cb.addEventListener('change', () => {
                        if (mode === 'spm' && cb.checked) {
                            $root.querySelectorAll('.product-pick').forEach(o => {
                                if (o !== cb) o.checked = false;
                            });
                        } else if (mode === 'mpm') {
                            if ($root.querySelectorAll('.product-pick:checked').length > 30) {
                                cb.checked = false;
                                showFeedback('Maximum 30 products per carousel.', 'warn');
                                return;
                            }
                        }
                        updateSummary();
                    });
                });
            }

            // ── Product checkbox handlers (SPM cap) ──
            $root.querySelectorAll('.product-pick').forEach(cb => {
                cb.addEventListener('change', () => {
                    if (mode === 'spm' && cb.checked) {
                        // Uncheck all others
                        $root.querySelectorAll('.product-pick').forEach(o => {
                            if (o !== cb) o.checked = false;
                        });
                    } else if (mode === 'mpm') {
                        const total = $root.querySelectorAll('.product-pick:checked').length;
                        if (total > 30) {
                            cb.checked = false;
                            showFeedback('Maximum 30 products per carousel.', 'warn');
                            return;
                        }
                    }
                    updateSummary();
                });
            });

            // ── Recipient counters ──
            $root.querySelectorAll('input[data-recipient-count]').forEach(cb => {
                cb.addEventListener('change', updateSummary);
            });
            $root.querySelector('[data-manual]')?.addEventListener('input', updateSummary);

            function countManual() {
                const txt = $root.querySelector('[data-manual]')?.value || '';
                if (!txt.trim()) return 0;
                return txt.split(/[\s,;]+/).filter(s => /\d{8,}/.test(s.replace(/\D+/g, ''))).length;
            }

            function activeMode() {
                const r = $root.querySelector('input[name=recipient_mode]:checked');
                return r ? r.value : 'groups';
            }

            function updateSummary() {
                const mp = activeMode();
                let count = 0;
                if (mp === 'manual') {
                    count = countManual();
                } else if (mp === 'contacts') {
                    count = $root.querySelectorAll('input[name="contact_ids[]"]:checked').length;
                } else {
                    $root.querySelectorAll('input[name="group_ids[]"]:checked').forEach(c => {
                        count += parseInt(c.dataset.recipientCount || '0', 10);
                    });
                }
                $sumR.textContent = new Intl.NumberFormat().format(count);
                $countLbl.textContent = count + ' recipients';

                const productCount = $root.querySelectorAll('.product-pick:checked').length;
                $sumP.textContent = mode === 'link' ? '—' : productCount;
                $summary.textContent = '(' + productCount + ' selected' + (mode === 'mpm' ? ' / 30' : '') + ')';
            }
            $tabs?.querySelectorAll('input[name=recipient_mode]').forEach(r => r.addEventListener('change',
                updateSummary));

            function showFeedback(msg, kind) {
                $feedback.classList.remove('hidden');
                $feedback.className = 'rounded-lg px-3 py-2 text-[12px] font-mono ' +
                    (kind === 'ok' ? 'bg-wa-mint border border-wa-green/30 text-wa-deep' :
                        kind === 'err' ? 'bg-accent-coral/10 border border-accent-coral/40 text-[#A1431F]' :
                        'bg-accent-amber/15 text-[#A1431F]');
                $feedback.textContent = msg;
            }

            // ── Send ──
            $sendBtn.addEventListener('click', async () => {
                $sendBtn.disabled = true;
                $sendLbl.textContent = 'Sending…';
                $feedback.classList.add('hidden');

                const fd = new FormData();
                fd.append('_token', csrf);
                fd.append('mode', mode);
                fd.append('body', $msg.value || '');
                const dev = $root.querySelector('[data-sender]')?.value;
                if (dev) fd.append('sender', dev);
                const shop = $root.querySelector('[data-shop-id]')?.value;
                if (shop) fd.append('shop_id', shop);

                const mp = activeMode();
                if (mp === 'manual') {
                    fd.append('manual_numbers', $root.querySelector('[data-manual]').value || '');
                } else if (mp === 'contacts') {
                    $root.querySelectorAll('input[name="contact_ids[]"]:checked').forEach(c => fd.append(
                        'contact_ids[]', c.value));
                } else {
                    $root.querySelectorAll('input[name="group_ids[]"]:checked').forEach(c => fd.append(
                        'group_ids[]', c.value));
                }
                $root.querySelectorAll('.product-pick:checked').forEach(c => fd.append('product_ids[]', c
                    .value));

                try {
                    const r = await fetch(@json(route('user.catalog.send-to-number')), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin',
                        body: fd,
                    });
                    const j = await r.json();
                    if (!r.ok && r.status !== 200) {
                        showFeedback('✗ ' + (j.message || ('HTTP ' + r.status)), 'err');
                        return;
                    }
                    if (j.ok) {
                        showFeedback('✓ Sent to ' + j.sent + ' recipient' + (j.sent === 1 ? '' : 's') +
                            (j.failed ? ' · ' + j.failed + ' failed' : '') + '.', 'ok');
                    } else {
                        showFeedback('✗ ' + (j.message || ('Failed · ' + (j.errors ? j.errors.join(' / ') :
                            ''))), 'err');
                    }
                } catch (e) {
                    showFeedback('✗ ' + e.message, 'err');
                } finally {
                    $sendBtn.disabled = false;
                    $sendLbl.textContent = mode === 'spm' ? 'Send product →' : mode === 'mpm' ?
                        'Send carousel →' : 'Send catalog link →';
                }
            });

            updateSummary();
        })();
    </script>

@endif
