@php
    /** @var \App\Models\WaProduct $product */
    // Self-derive theme tokens — this partial renders in the child theme's
// @section scope and does not inherit the base template's @php locals.
    $theme = $theme ?? [];
    $settings = $settings ?? [];
    $brand = $brand ?? ($theme['brand'] ?? ($settings['brand_color'] ?? '#075E54'));
    $bg = $bg ?? ($theme['bg'] ?? '#FBFAF6');
    $surface = $surface ?? ($theme['surface'] ?? '#FFFFFF');
    $border = $border ?? ($theme['border'] ?? '#E5DFD0');
    $shopName = $shopName ?? ($workspace?->name ?: 'Store');
    $onSale = $product->compare_price_minor && $product->compare_price_minor > $product->price_minor;
    $savings = $onSale ? $product->compare_price_minor - $product->price_minor : 0;
    $images = collect([$product->image_url])
        ->concat(is_array($product->gallery_json) ? $product->gallery_json : [])
        ->filter()
        ->unique()
        ->values();
    $sfFallback =
        '<span class="sf-img-fallback"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/></svg></span>';

    // body_html is stored verbatim (no sanitisation on save/import) and is
    // rendered raw below on this PUBLIC, same-origin checkout surface — an
    // unsanitised value is stored XSS. Allow-list sanitise it here at the
    // render sink so any script/on*/style/iframe payload is stripped while
    // legitimate rich text survives. require_once is idempotent.
    require_once app_path('Support/html_sanitize.php');
    $bodyHtmlSafe = wadesk_sanitize_html($product->body_html);
@endphp

<div class="pd">
    <div class="sf-pd-gallery">
        <div class="sf-pd-thumbs">
            @foreach ($images as $i => $url)
                <button type="button" data-pd-thumb="{{ $i }}" class="{{ $i === 0 ? 'is-on' : '' }}">
                    <img src="{{ $url }}" alt="">
                </button>
            @endforeach
        </div>
        <div class="sf-pd-main" data-pd-main>
            @if ($images->isNotEmpty())
                <img src="{{ $images->first() }}" alt="{{ $product->name }}" data-pd-main-img
                    onerror="this.outerHTML='{{ $sfFallback }}'">
            @else
                {!! $sfFallback !!}
            @endif
        </div>
    </div>

    <div>
        <div class="breadcrumb">
            <a href="{{ $sf->public_url }}">{{ $shopName }}</a>
            <span style="opacity:.5">/</span>
            @if ($product->category)
                <a href="{{ $sf->public_url }}#categories">{{ $product->category }}</a>
                <span style="opacity:.5">/</span>
            @endif
            <span>{{ $product->name }}</span>
        </div>

        <h1>{{ $product->name }}</h1>

        <div class="sf-pd-price">
            <span class="sf-pd-now">{{ $product->price_display }}</span>
            @if ($onSale)
                <span class="sf-pd-was">{{ $product->compare_price_display }}</span>
                <span class="sf-pd-save">Save
                    {{ (int) round(($savings / $product->compare_price_minor) * 100) }}%</span>
            @endif
        </div>

        <div>
            @if ($product->in_stock)
                <span class="sf-pd-stock">
                    <svg viewBox="0 0 16 16" width="11" height="11" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <path d="m3 8 3 3 7-7" />
                    </svg>
                    @if ($product->stock_qty !== null && $product->stock_qty <= 10)
                        Only {{ $product->stock_qty }} left
                    @else
                        In stock · ready to ship
                    @endif
                </span>
            @else
                <span class="sf-pd-stock out">{{ __('Sold out') }}</span>
            @endif
        </div>

        @if ($product->description)
            <p class="desc" style="margin-top:18px">{{ $product->description }}</p>
        @endif

        <div class="sf-pd-actions">
            <div class="sf-qty" style="margin-top:0">
                <button type="button" data-qty-dec aria-label="{{ __('Decrease') }}">−</button>
                <input type="number" min="1" value="1" data-qty-input>
                <button type="button" data-qty-inc aria-label="{{ __('Increase') }}">+</button>
            </div>
            <button class="sf-cta" data-pd-add @disabled(!$product->in_stock)>
                @if ($product->in_stock)
                    Add to cart
                @else
                    Sold out
                @endif
            </button>
            <div class="sf-pd-icons">
                <button type="button" data-wish-toggle="{{ $product->id }}" class="sf-wish-pd"
                    onclick="STOREFRONT.toggleWish({{ $product->id }})" aria-label="{{ __('Wishlist') }}">
                    <svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M8 13.4S2 10 2 6.2A3.2 3.2 0 0 1 8 4.4 3.2 3.2 0 0 1 14 6.2c0 3.8-6 7.2-6 7.2Z" />
                    </svg>
                </button>
                <button type="button" data-compare-toggle="{{ $product->id }}"
                    onclick="STOREFRONT.toggleCompare({{ $product->id }})" aria-label="{{ __('Compare') }}">
                    <svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <rect x="2" y="4" width="5" height="9" rx="1" />
                        <rect x="9" y="4" width="5" height="9" rx="1" />
                    </svg>
                </button>
                <button type="button" onclick="STOREFRONT.share(location.href, {{ json_encode($product->name) }})"
                    aria-label="{{ __('Share') }}" title="{{ __('Share this product') }}">
                    <svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <circle cx="4" cy="8" r="2" />
                        <circle cx="12" cy="4" r="2" />
                        <circle cx="12" cy="12" r="2" />
                        <path d="m6 7 4-2M6 9l4 2" />
                    </svg>
                </button>
            </div>
        </div>

        @if ($waNumber)
            <a href="https://wa.me/{{ preg_replace('/\D+/', '', $waNumber) }}?text={{ urlencode('Hi! I have a question about: ' . $product->name . ' (' . url('/s/' . $sf->slug . '/p/' . $product->slug) . ')') }}"
                target="_blank" class="sf-cta-ghost" style="display:block;text-align:center">Ask a question on
                WhatsApp</a>
        @endif

        <dl class="sf-pd-meta">
            @if ($product->sku)
                <dt>{{ __('SKU') }}</dt>
                <dd>{{ $product->sku }}</dd>
            @endif
            @if ($product->category)
                <dt>{{ __('Category') }}</dt>
                <dd>{{ $product->category }}</dd>
            @endif
            @if ($product->weight_grams)
                <dt>{{ __('Weight') }}</dt>
                <dd>{{ $product->weight_grams }} g</dd>
            @endif
            <dt>{{ __('Delivery') }}</dt>
            <dd>2–5 business days</dd>
            @if (is_array($product->tags_json) && count($product->tags_json))
                <dt>{{ __('Tags') }}</dt>
                <dd>
                    @foreach ($product->tags_json as $t)
                        <span
                            style="display:inline-block;padding:2px 8px;border-radius:9999px;background:{{ $bg }};font-size:11px;margin:0 4px 4px 0">{{ $t }}</span>
                    @endforeach
                </dd>
            @endif
        </dl>
    </div>
</div>

<div class="sf-tabs">
    <div class="sf-tabs-head">
        <button class="is-on" data-tab="desc">{{ __('Description') }}</button>
        <button data-tab="ship">{{ __('Shipping & returns') }}</button>
        <button data-tab="reviews">{{ __('Reviews') }}</button>
    </div>
    <div class="sf-tabs-pane is-on" data-pane="desc">
        {!! $bodyHtmlSafe ?:
            '<p>' .
                e($product->description ?: 'No description available yet — message the seller on WhatsApp to learn more.') .
                '</p>' !!}
    </div>
    <div class="sf-tabs-pane" data-pane="ship">
        @php
            $shipFreeMinor = (int) ($sf->shipping_json['free_threshold'] ?? 99900);
            $shipFreeText = \App\Support\FormatSettings::formatIn($shipFreeMinor / 100, $sf->currency_code ?? 'USD');
        @endphp
        <p><strong>{{ __('Shipping.') }}</strong> Orders are dispatched within 1–2 business days. Delivery times depend
            on your location — typically 2–5 business days. Free shipping on orders above {{ $shipFreeText }}.</p>
        <p style="margin-top:14px"><strong>{{ __('Returns.') }}</strong> Unused items can be returned within 7 days of
            delivery. Message us on WhatsApp to start a return — we'll arrange the pickup.</p>
    </div>
    <div class="sf-tabs-pane" data-pane="reviews">
        @if (($ratingCount ?? 0) > 0)
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
                <span style="font-size:28px;font-weight:700;line-height:1">{{ $ratingAvg }}</span>
                <span style="color:#E8A93B;font-size:15px">{{ str_repeat('★', (int) round($ratingAvg)) }}<span
                        style="color:#d7e0dd">{{ str_repeat('★', 5 - (int) round($ratingAvg)) }}</span></span>
                <span
                    style="color:#6B807C;font-size:13px">{{ trans_choice('{1}:count review|[2,*]:count reviews', $ratingCount, ['count' => $ratingCount]) }}</span>
            </div>
            @foreach ($reviews as $rv)
                <div style="border-top:1px solid #eef2f0;padding:10px 0">
                    <div style="display:flex;align-items:center;gap:8px">
                        <strong style="font-size:13px">{{ $rv->customer_name ?: __('Anonymous') }}</strong>
                        <span style="color:#E8A93B;font-size:12px">{{ str_repeat('★', $rv->rating) }}<span
                                style="color:#d7e0dd">{{ str_repeat('★', 5 - $rv->rating) }}</span></span>
                    </div>
                    @if ($rv->body)
                        <p style="color:#4a5a56;margin-top:4px;font-size:13px">{{ $rv->body }}</p>
                    @endif
                </div>
            @endforeach
        @else
            <p style="color:#6B807C">{{ __('No reviews yet — be the first to review this product.') }}</p>
        @endif

        <div style="border-top:1px solid #eef2f0;margin-top:16px;padding-top:14px;max-width:440px">
            <div style="font-weight:600;margin-bottom:8px;font-size:14px">{{ __('Write a review') }}</div>
            <input id="rv-name" placeholder="{{ __('Your name') }}" maxlength="120"
                style="width:100%;padding:8px 10px;border:1px solid #d9e2df;border-radius:8px;font-size:14px;margin-bottom:8px">
            <select id="rv-rating"
                style="width:100%;padding:8px 10px;border:1px solid #d9e2df;border-radius:8px;font-size:14px;margin-bottom:8px;background:#fff">
                <option value="5">★★★★★ — {{ __('Excellent') }}</option>
                <option value="4">★★★★ — {{ __('Good') }}</option>
                <option value="3">★★★ — {{ __('Okay') }}</option>
                <option value="2">★★ — {{ __('Poor') }}</option>
                <option value="1">★ — {{ __('Bad') }}</option>
            </select>
            <textarea id="rv-body" rows="3" maxlength="1000" placeholder="{{ __('Share your experience (optional)') }}"
                style="width:100%;padding:8px 10px;border:1px solid #d9e2df;border-radius:8px;font-size:14px"></textarea>
            <div id="rv-msg" style="font-size:12.5px;min-height:16px;margin:4px 0"></div>
            <button type="button" class="sf-cta"
                onclick="STOREFRONT.submitProductReview({{ $product->id }})">{{ __('Submit review') }}</button>
        </div>
    </div>
</div>

@if (isset($related) && $related->count() > 0)
    <section class="sf-related">
        <h2>{{ __('You may also like') }}</h2>
        <div class="sf-related-grid">
            @foreach ($related as $p)
                @include('storefront._partials.card', ['p' => $p])
            @endforeach
        </div>
    </section>
@endif

<script>
    (function() {
        // Image gallery thumb click
        const thumbs = document.querySelectorAll('[data-pd-thumb]');
        const mainImg = document.querySelector('[data-pd-main-img]');
        const images = @json($images->values()->all());
        thumbs.forEach(btn => {
            btn.addEventListener('click', () => {
                thumbs.forEach(t => t.classList.toggle('is-on', t === btn));
                const idx = parseInt(btn.dataset.pdThumb, 10);
                if (mainImg && images[idx]) mainImg.src = images[idx];
            });
        });

        // Tabs
        document.querySelectorAll('.sf-tabs-head [data-tab]').forEach(b => {
            b.addEventListener('click', () => {
                const target = b.dataset.tab;
                document.querySelectorAll('.sf-tabs-head [data-tab]').forEach(x => x.classList
                    .toggle('is-on', x === b));
                document.querySelectorAll('.sf-tabs-pane').forEach(p => p.classList.toggle('is-on',
                    p.dataset.pane === target));
            });
        });

        // Quantity stepper
        const qty = document.querySelector('[data-qty-input]');
        document.querySelector('[data-qty-dec]')?.addEventListener('click', () => {
            qty.value = Math.max(1, parseInt(qty.value || 1) - 1);
        });
        document.querySelector('[data-qty-inc]')?.addEventListener('click', () => {
            qty.value = parseInt(qty.value || 1) + 1;
        });

        // Add to cart
        document.querySelector('[data-pd-add]')?.addEventListener('click', () => {
            const n = parseInt(qty?.value || 1, 10);
            STOREFRONT.addQty({{ $product->id }}, n);
            STOREFRONT.toggleDrawer('cart');
        });
    })();
</script>
