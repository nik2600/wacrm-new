{{-- Shared theme skeleton. Each theme passes a `theme` array with
 palette + font tokens. The base now ships a richer e-commerce
 shell: search, wishlist + compare + cart icons in the header,
 full footer with shipping/contact/social blocks. Themes override
 visuals via @section('extra-styles') and content via the
 two shared partials (_partials._index, _partials._product). --}}
@php
    $brand = $theme['brand'] ?? ($settings['brand_color'] ?? '#075E54');
    $bg = $theme['bg'] ?? '#FBFAF6';
    $surface = $theme['surface'] ?? '#FFFFFF';
    $text = $theme['text'] ?? '#0B1F1C';
    $muted = $theme['muted'] ?? '#6B807C';
    $border = $theme['border'] ?? '#E5DFD0';
    $accent = $theme['accent'] ?? $brand;
    $serif = $theme['serif'] ?? "'Fraunces',Georgia,serif";
    $sans = $theme['sans'] ?? "'Plus Jakarta Sans',system-ui,sans-serif";
    $mono = $theme['mono'] ?? "'JetBrains Mono',monospace";
    $heroSize = $theme['heroSize'] ?? '46px';
    $cardRadius = $theme['cardRadius'] ?? '14px';
    $logo = $logo ?? ($settings['logo_url'] ?? null);
    $hero = $hero ?? ($settings['hero_text'] ?? '');
    $footer =
        $footer ?? ($settings['footer_text'] ?? '© ' . date('Y') . ' ' . ($shopName ?? ($workspace?->name ?: 'Store')));
    $shopName = $shopName ?? ($workspace?->name ?: 'Store');
@endphp

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ isset($product) ? $product->name . ' · ' . $shopName : $shopName }}</title>
    @php
        $seoDesc = isset($product)
            ? ($product->description ?:
            $shopName)
            : $settings['hero_text'] ?? $shopName . ' on WhatsApp';
        $seoImg = isset($product) ? $product->image_url : $logo ?? null;
        $seoUrl = request()->fullUrl();
    @endphp
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($seoDesc), 160) }}">
    {{-- S9 SEO: Open Graph + Twitter so shared links render a rich card. --}}
    <meta property="og:type" content="{{ isset($product) ? 'product' : 'website' }}">
    <meta property="og:site_name" content="{{ $shopName }}">
    <meta property="og:title" content="{{ isset($product) ? $product->name : $shopName }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($seoDesc), 200) }}">
    <meta property="og:url" content="{{ $seoUrl }}">
    @if ($seoImg)
        <meta property="og:image" content="{{ $seoImg }}">
    @endif
    <meta name="twitter:card" content="{{ $seoImg ? 'summary_large_image' : 'summary' }}">
    @isset($product)
        {{-- JSON-LD Product schema for rich results (built in PHP + null-filtered
 so the directive parser never sees a complex literal). --}}
        @php
            $ld = [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->name,
                'image' => array_values(array_filter([$product->image_url])),
                'description' => \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 500),
                'sku' => $product->sku ?: null,
                'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand] : null,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => number_format(((int) $product->price_minor) / 100, 2, '.', ''),
                    'priceCurrency' => $product->currency_code ?: $sf->currency_code ?? 'INR',
                    'availability' =>
                        $product->effective_availability === 'in stock'
                            ? 'https://schema.org/InStock'
                            : 'https://schema.org/OutOfStock',
                    'url' => $seoUrl,
                ],
                'aggregateRating' =>
                    ($ratingCount ?? 0) > 0
                        ? [
                            '@type' => 'AggregateRating',
                            'ratingValue' => $ratingAvg,
                            'reviewCount' => $ratingCount,
                        ]
                        : null,
            ];
            $ld = array_filter($ld, fn($v) => $v !== null && $v !== []);
        @endphp
        <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endisset
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        html {
            overflow-x: hidden
        }

        body {
            font-family: {!! $sans !!};
            background: {{ $bg }};
            color: {{ $text }};
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
            max-width: 100vw
        }

        a {
            color: inherit;
            text-decoration: none
        }

        img {
            display: block;
            max-width: 100%
        }

        /* Long unbroken strings (a brand/hero typed without spaces) must wrap,
 never push the page wider than the viewport. */
        h1,
        h2,
        h3,
        p {
            overflow-wrap: anywhere;
            word-break: break-word
        }

        .wrap {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px
        }

        /* Theme palette exposed as CSS variables so the shared rich-shop
 components (filters, toolbar, badges, drawers) adapt to EVERY
 theme instead of a hardcoded light look. */
        :root {
            --sf-bg: {{ $bg }};
            --sf-surface: {{ $surface }};
            --sf-text: {{ $text }};
            --sf-muted: {{ $muted }};
            --sf-border: {{ $border }};
            --sf-brand: {{ $brand }};
            --sf-accent: {{ $accent }};
        }

        /* ─── Top utility strip ─── */
        .sf-strip {
            background: {{ $text }};
            color: {{ $bg }};
            font-size: 11.5px;
            padding: 9px 24px;
            text-align: center;
            font-family: {!! $mono !!};
            letter-spacing: .05em
        }

        .sf-strip a {
            text-decoration: underline;
            text-underline-offset: 2px
        }

        /* ─── Header ─── */
        header.sf-head {
            display: flex;
            align-items: center;
            gap: 24px;
            padding: 16px 24px;
            border-bottom: 1px solid {{ $border }};
            background: color-mix(in srgb, {{ $surface }} 88%, transparent);
            backdrop-filter: saturate(1.1) blur(10px);
            position: sticky;
            top: 0;
            z-index: 50;
            transition: box-shadow .2s
        }

        header.sf-head.is-stuck {
            box-shadow: 0 6px 24px -14px rgba(0, 0, 0, 0.28)
        }

        .sf-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: {!! $serif !!};
            font-size: 23px;
            font-weight: 600;
            letter-spacing: -0.015em;
            flex-shrink: 0;
            min-width: 0;
            max-width: 42vw;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap
        }

        .sf-brand img {
            max-height: 34px
        }

        .sf-nav {
            display: flex;
            gap: 22px;
            font-size: 13px;
            font-weight: 500;
            color: {{ $muted }}
        }

        .sf-nav a {
            transition: color .15s
        }

        .sf-nav a:hover {
            color: {{ $text }}
        }

        .sf-search {
            flex: 1;
            max-width: 520px;
            margin-left: auto;
            position: relative
        }

        .sf-search input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            border: 1px solid {{ $border }};
            border-radius: 9999px;
            background: {{ $bg }};
            font-size: 13px;
            color: {{ $text }}
        }

        .sf-search input:focus {
            outline: none;
            border-color: {{ $brand }};
            background: {{ $surface }}
        }

        .sf-search svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: {{ $muted }}
        }

        .sf-actions {
            display: flex;
            align-items: center;
            gap: 6px
        }

        /* ─── Hero ─── */
        .hero {
            padding: 72px 24px 44px;
            text-align: center;
            max-width: 1280px;
            margin: 0 auto
        }

        .hero h1 {
            font-family: {!! $serif !!};
            font-size: clamp(30px, 5.2vw, {{ $heroSize }});
            line-height: 1.06;
            letter-spacing: -0.02em;
            max-width: 18ch;
            margin: 0 auto;
            font-weight: 600
        }

        .hero p {
            margin-top: 18px;
            color: {{ $muted }};
            font-size: 15.5px;
            line-height: 1.6;
            max-width: 560px;
            margin-left: auto;
            margin-right: auto
        }

        .hero .sf-hero-trust {
            margin-top: 30px;
            display: inline-flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            font-family: {!! $mono !!};
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: {{ $muted }}
        }

        .hero .sf-hero-trust span {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 14px;
            border: 1px solid {{ $border }};
            border-radius: 9999px;
            background: {{ $surface }}
        }

        .hero .sf-hero-trust span svg {
            color: {{ $accent }}
        }

        /* ─── Grid + Card ─── */
        .grid {
            display: grid;
            /* min(100%, 248px) so a column NEVER forces itself wider than the
 container — without it the 248px minimum overflows narrow phones (and,
 nested inside the shop layout's 1fr track, pushed the whole page wider
 than the viewport → "grid view won't scroll" while list view was fine). */
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 248px), 1fr));
            gap: 22px;
            padding: 0 24px;
            max-width: 1280px;
            margin: 0 auto;
            min-width: 0
        }

        .card {
            background: {{ $surface }};
            border: 1px solid {{ $border }};
            border-radius: {{ $cardRadius }};
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform .25s cubic-bezier(.2, .7, .3, 1), box-shadow .25s, border-color .25s;
            min-width: 0
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 44px -16px rgba(15, 30, 28, 0.22);
            border-color: {{ $brand }}33
        }

        .card .img {
            aspect-ratio: 1/1;
            background: linear-gradient(135deg, {{ $bg }}, {{ $surface }});
            display: grid;
            place-items: center;
            color: {{ $muted }};
            position: relative;
            overflow: hidden
        }

        .card .img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .5s cubic-bezier(.2, .7, .3, 1)
        }

        .card:hover .img img {
            transform: scale(1.06)
        }

        .card .body {
            padding: 16px 16px 18px;
            flex: 1;
            display: flex;
            flex-direction: column
        }

        .card .cat {
            font-family: {!! $mono !!};
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: .14em;
            color: {{ $muted }};
            margin-bottom: 7px
        }

        .card h3 {
            font-family: {!! $serif !!};
            font-size: 17px;
            line-height: 1.28;
            margin-bottom: 8px;
            font-weight: 600;
            min-width: 0
        }

        .card h3 a {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden
        }

        .card h3 a:hover {
            color: {{ $brand }}
        }

        .card .price {
            font-family: {!! $mono !!};
            font-size: 14px;
            color: {{ $text }};
            margin-bottom: 14px;
            display: flex;
            align-items: baseline;
            gap: 8px;
            flex-wrap: wrap
        }

        .card .price b {
            font-weight: 600;
            font-size: 16px;
            letter-spacing: -.01em
        }

        .card .add {
            margin-top: auto;
            padding: 11px 14px;
            border-radius: 10px;
            background: {{ $brand }};
            border: 1px solid {{ $brand }};
            color: #fff;
            font-weight: 600;
            font-size: 12.5px;
            cursor: pointer;
            font-family: inherit;
            transition: filter .15s, transform .1s;
            letter-spacing: .01em
        }

        .card .add:hover {
            filter: brightness(1.08)
        }

        .card .add:active {
            transform: scale(.98)
        }

        .card .add:disabled {
            background: {{ $border }};
            border-color: {{ $border }};
            color: {{ $muted }};
            cursor: not-allowed;
            filter: none
        }

        /* SVG image fallback (no emoji) — a clean monochrome product glyph */
        .sf-img-fallback {
            display: grid;
            place-items: center;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, {{ $bg }}, {{ $surface }});
            color: {{ $muted }}
        }

        .sf-img-fallback svg {
            width: 34px;
            height: 34px;
            opacity: .5
        }

        /* ─── Footer ─── */
        footer.sf-foot {
            margin-top: 64px;
            background: {{ $surface }};
            border-top: 1px solid {{ $border }};
            padding: 48px 24px 20px
        }

        .sf-foot-grid {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 1fr;
            gap: 36px
        }

        .sf-foot-col h4 {
            font-family: {!! $mono !!};
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .14em;
            color: {{ $muted }};
            margin-bottom: 14px
        }

        .sf-foot-col a {
            display: block;
            padding: 5px 0;
            font-size: 13px;
            color: {{ $text }};
            opacity: .8
        }

        .sf-foot-col a:hover {
            opacity: 1;
            color: {{ $brand }}
        }

        .sf-foot-brand {
            font-family: {!! $serif !!};
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 10px
        }

        .sf-foot-blurb {
            font-size: 13px;
            color: {{ $muted }};
            line-height: 1.6
        }

        .sf-foot-pay {
            display: flex;
            gap: 8px;
            margin-top: 14px;
            flex-wrap: wrap
        }

        .sf-foot-pay span {
            padding: 5px 9px;
            background: {{ $bg }};
            border: 1px solid {{ $border }};
            border-radius: 6px;
            font-family: {!! $mono !!};
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: {{ $muted }}
        }

        .sf-foot-bottom {
            max-width: 1280px;
            margin: 36px auto 0;
            padding-top: 18px;
            border-top: 1px solid {{ $border }};
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: {{ $muted }};
            flex-wrap: wrap;
            gap: 8px
        }

        .empty {
            padding: 96px 24px;
            text-align: center;
            color: {{ $muted }}
        }

        .empty h2 {
            font-family: {!! $serif !!};
            font-size: 30px;
            color: {{ $text }};
            margin-bottom: 8px
        }

        /* ─── Product detail ─── */
        .pd {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 64px;
            padding: 48px 24px;
            max-width: 1200px;
            margin: 0 auto
        }

        .pd .breadcrumb {
            font-family: {!! $mono !!};
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: {{ $muted }};
            margin-bottom: 18px
        }

        .pd .breadcrumb a:hover {
            color: {{ $brand }}
        }

        .pd h1 {
            font-family: {!! $serif !!};
            font-size: 38px;
            line-height: 1.1;
            margin: 8px 0 4px;
            letter-spacing: -0.01em
        }

        .pd .desc {
            color: {{ $muted }};
            line-height: 1.7;
            font-size: 14px
        }

        @media (max-width:760px) {
            .pd {
                grid-template-columns: 1fr;
                padding: 24px;
                gap: 24px
            }

            .hero {
                padding: 36px 16px 24px
            }

            .hero h1 {
                font-size: 32px
            }

            .sf-search {
                display: none
            }

            .sf-nav {
                display: none
            }

            .sf-foot-grid {
                grid-template-columns: 1fr 1fr;
                gap: 24px
            }
        }

        @yield('extra-styles')
    </style>
</head>

<body>

    @if (!empty($settings['top_strip']))
        <div class="sf-strip">{{ $settings['top_strip'] }}</div>
    @else
        <div class="sf-strip">{{ __('Order on WhatsApp · Cash on delivery available · Free chats, fast replies') }}
        </div>
    @endif

    <header class="sf-head">
        <a href="{{ $sf->public_url }}" class="sf-brand">
            @if ($logo)
                <img src="{{ $logo }}" alt="">
            @endif
            {{ $shopName }}
        </a>

        <nav class="sf-nav">
            <a href="{{ $sf->public_url }}">Shop</a>
            <a href="{{ $sf->public_url }}#categories">Categories</a>
            <a href="{{ $sf->public_url }}#about">About</a>
        </nav>

        <div class="sf-search">
            <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor"
                stroke-width="1.7">
                <circle cx="7" cy="7" r="5" />
                <path d="m11 11 3 3" />
            </svg>
            <input type="search" data-sf-search placeholder="{{ __('Search products…') }}" />
        </div>

        <div class="sf-actions">
            <button class="sf-icon-btn" onclick="STOREFRONT.toggleDrawer('wish')" aria-label="{{ __('Wishlist') }}"
                title="{{ __('Wishlist') }}">
                <svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor"
                    stroke-width="1.6">
                    <path d="M8 13.4S2 10 2 6.2A3.2 3.2 0 0 1 8 4.4 3.2 3.2 0 0 1 14 6.2c0 3.8-6 7.2-6 7.2Z" />
                </svg>
                <span data-wish-count class="hidden-count">0</span>
            </button>
            <button class="sf-icon-btn" onclick="STOREFRONT.toggleDrawer('compare')" aria-label="{{ __('Compare') }}"
                title="{{ __('Compare') }}">
                <svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor"
                    stroke-width="1.6">
                    <rect x="2" y="4" width="5" height="9" rx="1" />
                    <rect x="9" y="4" width="5" height="9" rx="1" />
                </svg>
                <span data-compare-count class="hidden-count">0</span>
            </button>
            <button class="sf-icon-btn" onclick="STOREFRONT.toggleDrawer('cart')" aria-label="{{ __('Cart') }}"
                title="{{ __('Cart') }}">
                <svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor"
                    stroke-width="1.7">
                    <path d="M2 3h2l1.5 8h7l1-5H6" />
                    <circle cx="6" cy="13.5" r="1.1" />
                    <circle cx="12" cy="13.5" r="1.1" />
                </svg>
                <span data-cart-count class="hidden-count">0</span>
            </button>
        </div>
    </header>

    @yield('content')

    @isset($product)
        {{-- Reviews (S6) — shared across all themes so every product page shows
 social proof + a submit form without per-theme edits. --}}
        <section style="max-width:1280px;margin:40px auto 0;padding:0 24px">
            <h2 style="font-family:{!! $serif !!};font-size:22px;margin-bottom:14px">{{ __('Reviews') }}</h2>
            <div
                style="background:{{ $surface }};border:1px solid {{ $border }};border-radius:16px;padding:20px">
                @if (($ratingCount ?? 0) > 0)
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
                        <span style="font-size:28px;font-weight:700;line-height:1">{{ $ratingAvg }}</span>
                        <span style="color:#E8A93B;font-size:15px">{{ str_repeat('★', (int) round($ratingAvg)) }}<span
                                style="color:{{ $border }}">{{ str_repeat('★', 5 - (int) round($ratingAvg)) }}</span></span>
                        <span
                            style="color:{{ $muted }};font-size:13px">{{ trans_choice('{1}:count review|[2,*]:count reviews', $ratingCount, ['count' => $ratingCount]) }}</span>
                    </div>
                    @foreach ($reviews as $rv)
                        <div style="border-top:1px solid {{ $border }};padding:10px 0">
                            <div style="display:flex;align-items:center;gap:8px">
                                <strong
                                    style="font-size:13px;color:{{ $text }}">{{ $rv->customer_name ?: __('Anonymous') }}</strong>
                                <span style="color:#E8A93B;font-size:12px">{{ str_repeat('★', $rv->rating) }}<span
                                        style="color:{{ $border }}">{{ str_repeat('★', 5 - $rv->rating) }}</span></span>
                            </div>
                            @if ($rv->body)
                                <p style="color:{{ $muted }};margin-top:4px;font-size:13px">{{ $rv->body }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                @else
                    <p style="color:{{ $muted }};font-size:13px">
                        {{ __('No reviews yet — be the first to review this product.') }}</p>
                @endif

                <div style="border-top:1px solid {{ $border }};margin-top:16px;padding-top:14px;max-width:460px">
                    <div style="font-weight:600;margin-bottom:8px;font-size:14px;color:{{ $text }}">
                        {{ __('Write a review') }}</div>
                    <input id="rv-name" placeholder="{{ __('Your name') }}" maxlength="120"
                        style="width:100%;padding:8px 10px;border:1px solid {{ $border }};border-radius:8px;font-size:14px;margin-bottom:8px;background:{{ $bg }};color:{{ $text }}">
                    <select id="rv-rating"
                        style="width:100%;padding:8px 10px;border:1px solid {{ $border }};border-radius:8px;font-size:14px;margin-bottom:8px;background:{{ $bg }};color:{{ $text }}">
                        <option value="5">★★★★★ — {{ __('Excellent') }}</option>
                        <option value="4">★★★★ — {{ __('Good') }}</option>
                        <option value="3">★★★ — {{ __('Okay') }}</option>
                        <option value="2">★★ — {{ __('Poor') }}</option>
                        <option value="1">★ — {{ __('Bad') }}</option>
                    </select>
                    <textarea id="rv-body" rows="3" maxlength="1000"
                        placeholder="{{ __('Share your experience (optional)') }}"
                        style="width:100%;padding:8px 10px;border:1px solid {{ $border }};border-radius:8px;font-size:14px;background:{{ $bg }};color:{{ $text }}"></textarea>
                    <div id="rv-msg" style="font-size:12.5px;min-height:16px;margin:4px 0"></div>
                    <button type="button" onclick="STOREFRONT.submitProductReview({{ $product->id }})"
                        style="padding:9px 16px;border-radius:9px;background:{{ $brand }};color:#fff;border:0;font-weight:600;font-size:13px;cursor:pointer">{{ __('Submit review') }}</button>
                </div>
            </div>
        </section>
    @endisset

    <footer class="sf-foot">
        <div class="sf-foot-grid">
            <div class="sf-foot-col">
                <div class="sf-foot-brand">{{ $shopName }}</div>
                <p class="sf-foot-blurb">
                    {{ $settings['footer_text'] ?? 'Hand-picked products, ordered on WhatsApp like a phone call. No app needed — just chat with us to buy.' }}
                </p>
                <div class="sf-foot-pay">
                    <span>{{ __('WhatsApp') }}</span><span>{{ __('UPI') }}</span><span>{{ __('Cash on delivery') }}</span><span>{{ __('Bank transfer') }}</span>
                </div>
            </div>
            <div class="sf-foot-col">
                <h4>{{ __('Shop') }}</h4>
                <a href="{{ $sf->public_url }}">All products</a>
                <a href="{{ $sf->public_url }}#categories">Categories</a>
                <a href="javascript:STOREFRONT.toggleDrawer('wish')">{{ __('Wishlist') }}</a>
                <a href="javascript:STOREFRONT.toggleDrawer('compare')">{{ __('Compare') }}</a>
            </div>
            <div class="sf-foot-col">
                <h4>{{ __('Help') }}</h4>
                @php
                    // Free-shipping threshold rendered in the storefront's own
// currency. shipping_json.free_threshold (minor units) wins;
// fall back to 999 in the storefront currency for legacy stores.
$freeMinor = (int) ($sf->shipping_json['free_threshold'] ?? 99900);
$freeMajor = $freeMinor / 100;
$freeText = \App\Support\FormatSettings::formatIn($freeMajor, $sf->currency_code ?? 'USD');
                @endphp
                <a
                    href="javascript:STOREFRONT.modal({title:'Shipping & delivery',body:'Orders are confirmed via WhatsApp. Delivery times depend on your location — typically 2-5 business days. Free shipping above {{ $freeText }}.',confirm:'OK'})">{{ __('Shipping') }}</a>
                <a
                    href="javascript:STOREFRONT.modal({title:'Returns',body:'Unused items can be returned within 7 days. Message us on WhatsApp to start a return.',confirm:'OK'})">{{ __('Returns') }}</a>
                <a
                    href="javascript:STOREFRONT.modal({title:'How to order',body:'Browse the catalog, add to cart, then tap “Order on WhatsApp”. We confirm your order in chat and arrange delivery.',confirm:'OK'})">{{ __('How to order') }}</a>
                <a
                    href="javascript:STOREFRONT.modal({title:'Privacy',body:'We only collect what you share in chat to fulfill your order. We never sell your data.',confirm:'OK'})">{{ __('Privacy') }}</a>
            </div>
            <div class="sf-foot-col">
                <h4>{{ __('Contact') }}</h4>
                @if ($waNumber)
                    <a href="https://wa.me/{{ preg_replace('/\D+/', '', $waNumber) }}" target="_blank">WhatsApp ·
                        {{ $waNumber }}</a>
                @endif
                <a href="javascript:void(0)"
                    onclick="STOREFRONT.flash('Hours: 9am–9pm IST')">{{ __('Mon–Sun · 9am–9pm') }}</a>
            </div>
        </div>
        <div class="sf-foot-bottom">
            <span>{{ $footer }}</span>
        </div>
    </footer>

    {{-- ─────────────── Shop config + catalog handoff to STOREFRONT.* JS ─────────────── --}}
    <script>
        window.SF_SHOP = {
            id: {{ $sf->id }},
            name: {!! json_encode($shopName) !!},
            currency: {!! json_encode($sf->currency_code ?? 'INR') !!},
            shipping: @json($sf->shipping_json ?? null),
            payment: {
                provider: {!! json_encode($sf->payment_provider) !!},
                handle: {!! json_encode($sf->payment_config_json['handle'] ?? null) !!},
            },
        };
        window.SF_CATALOG = (window.SF_CATALOG || {});
        @if (!empty($products))
            @foreach ($products as $p)
                window.SF_CATALOG[{{ $p->id }}] = {
                    name: {!! json_encode($p->name) !!},
                    price: {{ $p->price_minor }},
                    compare: {{ $p->compare_price_minor ?? 'null' }},
                    image: {!! json_encode($p->image_url) !!},
                    url: {!! json_encode(url('/s/' . $sf->slug . '/p/' . $p->slug)) !!},
                    slug: {!! json_encode($p->slug) !!},
                    sku: {!! json_encode($p->sku) !!},
                    category: {!! json_encode($p->category) !!},
                    description: {!! json_encode($p->description) !!},
                    stock_qty: {{ $p->stock_qty ?? 'null' }},
                    currency: {!! json_encode($p->currency_code) !!},
                };
            @endforeach
        @endif
        @if (!empty($product))
            window.SF_CATALOG[{{ $product->id }}] = {
                name: {!! json_encode($product->name) !!},
                price: {{ $product->price_minor }},
                compare: {{ $product->compare_price_minor ?? 'null' }},
                image: {!! json_encode($product->image_url) !!},
                url: {!! json_encode(url('/s/' . $sf->slug . '/p/' . $product->slug)) !!},
                slug: {!! json_encode($product->slug) !!},
                sku: {!! json_encode($product->sku) !!},
                category: {!! json_encode($product->category) !!},
                description: {!! json_encode($product->description) !!},
            };
        @endif
        @if (!empty($related))
            @foreach ($related as $p)
                window.SF_CATALOG[{{ $p->id }}] = {
                    name: {!! json_encode($p->name) !!},
                    price: {{ $p->price_minor }},
                    compare: {{ $p->compare_price_minor ?? 'null' }},
                    image: {!! json_encode($p->image_url) !!},
                    url: {!! json_encode(url('/s/' . $sf->slug . '/p/' . $p->slug)) !!},
                    slug: {!! json_encode($p->slug) !!},
                    sku: {!! json_encode($p->sku) !!},
                    category: {!! json_encode($p->category) !!},
                    description: {!! json_encode($p->description) !!},
                    stock_qty: {{ $p->stock_qty ?? 'null' }},
                    currency: {!! json_encode($p->currency_code) !!},
                };
            @endforeach
        @endif
    </script>

    @include('storefront._shared')

    <script>
        // Premium sticky-header elevation — add a soft shadow once the page scrolls.
        (function() {
            var h = document.querySelector('header.sf-head');
            if (!h) return;
            var onScroll = function() {
                h.classList.toggle('is-stuck', window.scrollY > 8);
            };
            window.addEventListener('scroll', onScroll, {
                passive: true
            });
            onScroll();
        })();
    </script>

</body>

</html>
