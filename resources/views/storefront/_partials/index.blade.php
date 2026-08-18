@php
    // Self-derive theme tokens — this partial renders inside the CHILD theme's
// @section scope, which does NOT inherit the base template's local @php
    // vars. Pull them from the passed $theme array (same fallbacks as the base)
    // so it works no matter which theme includes it.
    $theme = $theme ?? [];
    $settings = $settings ?? [];
    $brand = $brand ?? ($theme['brand'] ?? ($settings['brand_color'] ?? '#075E54'));
    $bg = $bg ?? ($theme['bg'] ?? '#FBFAF6');
    $surface = $surface ?? ($theme['surface'] ?? '#FFFFFF');
    $text = $text ?? ($theme['text'] ?? '#0B1F1C');
    $muted = $muted ?? ($theme['muted'] ?? '#6B807C');
    $border = $border ?? ($theme['border'] ?? '#E5DFD0');
    $accent = $accent ?? ($theme['accent'] ?? $brand);
    $shopName = $shopName ?? ($workspace?->name ?: 'Store');
    $hero = $hero ?? ($settings['hero_text'] ?? '');
@endphp
<div class="hero">
    <h1>{!! $hero ?: 'Welcome to ' . e($shopName) . '.' !!}</h1>
    <p>{{ $settings['hero_sub'] ?? 'Hand-picked products. Order any item directly on WhatsApp — no checkout forms, no app installs.' }}
    </p>
    <div class="sf-hero-trust">
        <span><svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="m3 8 3 3 7-7" />
            </svg>{{ __('Cash on delivery') }}</span>
        <span><svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor"
                stroke-width="1.7">
                <path d="m3 8 3 3 7-7" />
            </svg>{{ __('Free chat support') }}</span>
        <span><svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor"
                stroke-width="1.7">
                <path d="m3 8 3 3 7-7" />
            </svg>{{ __('Easy returns') }}</span>
    </div>
</div>

@if ($products->isEmpty())
    <div class="empty">
        <h2>{{ __('No products yet') }}</h2>
        <p>{{ __('The owner is still setting things up — please check back soon.') }}</p>
    </div>
@else
    <section style="padding:0 24px 24px" id="categories">
        <div class="sf-toolbar">
            <div class="sf-count" data-result-count>{{ $products->count() }}
                {{ \Illuminate\Support\Str::plural('product', $products->count()) }}</div>
            <div style="margin-left:auto;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <select data-sort>
                    <option value="featured">{{ __('Sort · Featured') }}</option>
                    <option value="price-asc">{{ __('Price · Low to high') }}</option>
                    <option value="price-desc">{{ __('Price · High to low') }}</option>
                    <option value="name-asc">{{ __('Name · A → Z') }}</option>
                    <option value="name-desc">{{ __('Name · Z → A') }}</option>
                </select>
                <div class="sf-view-toggle" data-view-toggle>
                    <button data-view="grid" class="is-on" aria-label="{{ __('Grid view') }}"><svg viewBox="0 0 16 16"
                            width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="2" y="2" width="5" height="5" />
                            <rect x="9" y="2" width="5" height="5" />
                            <rect x="2" y="9" width="5" height="5" />
                            <rect x="9" y="9" width="5" height="5" />
                        </svg></button>
                    <button data-view="list" aria-label="{{ __('List view') }}"><svg viewBox="0 0 16 16" width="13"
                            height="13" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M2 4h12M2 8h12M2 12h12" />
                        </svg></button>
                </div>
            </div>
        </div>

        <div class="sf-shop-grid" style="display:grid;grid-template-columns:240px 1fr;gap:24px">
            {{-- Filter sidebar --}}
            <aside class="sf-filters">
                <h3>{{ __('Filter') }}</h3>

                @if (!empty($categories))
                    <div class="sf-filter-group">
                        <h3>{{ __('Category') }}</h3>
                        @foreach ($categories as $cat)
                            <label><input type="checkbox" data-filter-cat value="{{ $cat }}">
                                {{ $cat }}</label>
                        @endforeach
                    </div>
                @endif

                @if (($priceMax ?? 0) > 0)
                    <div class="sf-filter-group">
                        <h3>{{ __('Price') }}</h3>
                        <div class="sf-price-range">
                            <input type="number" data-filter-min placeholder="{{ __('Min') }}"
                                min="{{ $priceMin }}" max="{{ $priceMax }}">
                            <span style="color:#6B807C">—</span>
                            <input type="number" data-filter-max placeholder="{{ __('Max') }}"
                                min="{{ $priceMin }}" max="{{ $priceMax }}">
                        </div>
                        @php $sfRangeCur = $sf->currency_code ?? 'USD'; @endphp
                        <div
                            style="font-family:'JetBrains Mono',monospace;font-size:10.5px;color:#6B807C;margin-top:6px">
                            Range: {!! \App\Support\FormatSettings::formatIn($priceMin, $sfRangeCur) !!} — {!! \App\Support\FormatSettings::formatIn($priceMax, $sfRangeCur) !!}</div>
                    </div>
                @endif

                <div class="sf-filter-group">
                    <h3>{{ __('Availability') }}</h3>
                    <label><input type="checkbox" data-filter-stock checked> In stock only</label>
                    <label><input type="checkbox" data-filter-sale> On sale</label>
                </div>

                <button type="button" data-filter-reset
                    style="margin-top:14px;width:100%;padding:8px 12px;border-radius:9999px;border:1px solid {{ $border }};background:transparent;font-size:11.5px;cursor:pointer;font-family:inherit">{{ __('Reset filters') }}</button>
            </aside>

            {{-- Product grid --}}
            <div class="grid" data-product-grid style="padding:0">
                @foreach ($products as $p)
                    @include('storefront._partials.card', ['p' => $p])
                @endforeach
            </div>
        </div>

        <div data-no-results style="display:none;padding:60px 24px;text-align:center;color:#6B807C;font-size:14px">
            <div style="font-family:'Fraunces',serif;font-size:22px;color:#0B1F1C;margin-bottom:6px">
                {{ __('Nothing matches those filters') }}</div>
            <button data-filter-reset
                style="margin-top:14px;padding:9px 18px;border-radius:9999px;border:1px solid {{ $border }};background:transparent;font-size:12px;cursor:pointer;font-family:inherit">{{ __('Clear filters') }}</button>
        </div>

        @if (!empty($hasMore))
            <div style="text-align:center;padding:24px" data-show-more-wrap>
                <button data-show-more data-next-page="2" data-shop-url="{{ $sf->public_url }}"
                    style="padding:11px 24px;border-radius:9999px;border:1px solid {{ $border }};background:{{ $surface }};font-size:13px;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:8px">
                    <span data-show-more-text>{{ __('Show more products') }}</span>
                    <svg viewBox="0 0 16 16" width="12" height="12" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M3 6l5 5 5-5" />
                    </svg>
                </button>
                @if (!empty($total))
                    <div style="margin-top:8px;font-family:'JetBrains Mono',monospace;font-size:11px;color:#6B807C">
                        Showing {{ $products->count() }} of {{ $total }} {{ __('products') }}</div>
                @endif
            </div>
        @endif
    </section>
@endif

<style>
    .sf-shop-grid {
        display: grid;
        min-width: 0;
    }

    /* Grid tracks default to min-width:auto and expand to fit their content's
 min-content — which let the product grid push the page WIDER than the
 viewport (grid view wouldn't scroll; list view, forced to 1fr, was fine).
 Letting both tracks (filters + products) shrink to 0 fixes it. */
    .sf-shop-grid > * {
        min-width: 0;
    }

    .grid.sf-list-view {
        grid-template-columns: 1fr;
    }

    .grid.sf-list-view .card {
        flex-direction: row;
    }

    .grid.sf-list-view .card .img {
        width: 220px;
        aspect-ratio: 1/1;
    }

    .grid.sf-list-view .card .body {
        flex: 1;
    }

    @media (max-width: 880px) {
        .sf-shop-grid {
            grid-template-columns: 1fr !important;
        }

        .sf-filters {
            order: 2;
        }
    }

    /* Phone: a clean 2-column product grid (compact cards that SHRINK to fit —
 minmax(0,1fr) — so the row never overflows the viewport and the page
 scrolls normally). List view keeps its single column. */
    @media (max-width: 560px) {
        .grid:not(.sf-list-view) {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
    }

    @media (max-width: 340px) {
        .grid:not(.sf-list-view) {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    (function() {
        const root = document.querySelector('[data-product-grid]');
        if (!root) return;
        const cards = Array.from(root.querySelectorAll('.card'));
        const noResults = document.querySelector('[data-no-results]');
        const countEl = document.querySelector('[data-result-count]');
        const sort = document.querySelector('[data-sort]');
        const search = document.querySelector('[data-sf-search]');
        const cats = Array.from(document.querySelectorAll('[data-filter-cat]'));
        const minIn = document.querySelector('[data-filter-min]');
        const maxIn = document.querySelector('[data-filter-max]');
        const stockOnly = document.querySelector('[data-filter-stock]');
        const saleOnly = document.querySelector('[data-filter-sale]');
        const resetBtns = document.querySelectorAll('[data-filter-reset]');
        const viewBtns = document.querySelectorAll('[data-view-toggle] button');

        function apply() {
            const term = (search?.value || '').trim().toLowerCase();
            const selectedCats = cats.filter(c => c.checked).map(c => c.value);
            const min = parseFloat(minIn?.value) * 100;
            const max = parseFloat(maxIn?.value) * 100;
            const inStock = stockOnly?.checked;
            const onSaleOnly = saleOnly?.checked;

            let shown = 0;
            cards.forEach(card => {
                const name = card.dataset.productName || '';
                const cat = card.dataset.productCat || '';
                const price = parseInt(card.dataset.productPrice || '0', 10);
                const hasSale = !!card.querySelector('.sf-card-was');
                const isSold = card.querySelector('.add')?.disabled;

                let visible = true;
                if (term && !name.includes(term)) visible = false;
                if (selectedCats.length && !selectedCats.includes(cat)) visible = false;
                if (!Number.isNaN(min) && price < min) visible = false;
                if (!Number.isNaN(max) && price > max) visible = false;
                if (inStock && isSold) visible = false;
                if (onSaleOnly && !hasSale) visible = false;

                card.style.display = visible ? '' : 'none';
                if (visible) shown++;
            });

            // Sort visible cards in DOM order
            const mode = sort?.value || 'featured';
            if (mode !== 'featured') {
                const sorted = cards.slice().sort((a, b) => {
                    if (mode.startsWith('price')) {
                        const av = parseInt(a.dataset.productPrice || '0', 10);
                        const bv = parseInt(b.dataset.productPrice || '0', 10);
                        return mode.endsWith('asc') ? av - bv : bv - av;
                    }
                    const av = a.dataset.productName || '';
                    const bv = b.dataset.productName || '';
                    return mode.endsWith('asc') ? av.localeCompare(bv) : bv.localeCompare(av);
                });
                sorted.forEach(c => root.appendChild(c));
            }

            if (countEl) countEl.textContent = shown + ' ' + (shown === 1 ? 'product' : 'products');
            if (noResults) noResults.style.display = shown === 0 ? '' : 'none';
        }

        [search, sort, minIn, maxIn, stockOnly, saleOnly, ...cats].forEach(el => {
            if (!el) return;
            el.addEventListener('input', apply);
            el.addEventListener('change', apply);
        });
        resetBtns.forEach(btn => btn.addEventListener('click', () => {
            if (search) search.value = '';
            if (sort) sort.value = 'featured';
            if (minIn) minIn.value = '';
            if (maxIn) maxIn.value = '';
            if (stockOnly) stockOnly.checked = true;
            if (saleOnly) saleOnly.checked = false;
            cats.forEach(c => c.checked = false);
            apply();
        }));

        viewBtns.forEach(btn => btn.addEventListener('click', () => {
            viewBtns.forEach(b => b.classList.toggle('is-on', b === btn));
            root.classList.toggle('sf-list-view', btn.dataset.view === 'list');
        }));

        apply();

        // ───── Show-more lazy-load ─────
        const btn = document.querySelector('[data-show-more]');
        if (btn) {
            btn.addEventListener('click', async () => {
                const next = btn.dataset.nextPage;
                const txt = btn.querySelector('[data-show-more-text]');
                const shopUrl = btn.dataset.shopUrl;
                btn.disabled = true;
                if (txt) txt.textContent = 'Loading…';

                try {
                    const res = await fetch(`${shopUrl}?page=${next}&partial=1`, {
                        headers: {
                            'Accept': 'text/html',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const html = await res.text();
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = html;
                    const meta = JSON.parse(wrapper.querySelector('[data-page-meta]')?.textContent ||
                        '{}');
                    // Append new cards
                    Array.from(wrapper.children).forEach(node => {
                        if (node.classList?.contains('card')) {
                            root.appendChild(node);
                            cards.push(node);
                        }
                    });
                    STOREFRONT.renderAll();
                    apply();
                    if (meta.hasMore) {
                        btn.dataset.nextPage = (parseInt(next, 10) + 1);
                        btn.disabled = false;
                        if (txt) txt.textContent = 'Show more products';
                    } else {
                        document.querySelector('[data-show-more-wrap]')?.remove();
                    }
                } catch (e) {
                    btn.disabled = false;
                    if (txt) txt.textContent = 'Try again';
                }
            });
        }
    })();
</script>
