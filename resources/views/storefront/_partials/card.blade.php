@php
    /** @var \App\Models\WaProduct $p */
    $onSale = $p->compare_price_minor && $p->compare_price_minor > $p->price_minor;
    $isNew = optional($p->created_at)->gt(now()->subDays(14));
    // Theme tokens — self-derived so the card works both inside the shop grid
    // and standalone in the show-more AJAX partial (no base scope there).
    $theme = $theme ?? [];
    $brand = $brand ?? ($theme['brand'] ?? '#075E54');
    $accent = $accent ?? ($theme['accent'] ?? $brand);
@endphp
<div class="card sf-card-pos" data-product-id="{{ $p->id }}" data-product-name="{{ strtolower($p->name) }}"
    data-product-cat="{{ $p->category }}" data-product-price="{{ $p->price_minor }}">
    <a href="{{ url('/s/' . $sf->slug . '/p/' . $p->slug) }}" class="img">
        @php $sfFallbackSvg = '<span class="sf-img-fallback"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/></svg></span>'; @endphp
        @if ($p->image_url)
            <img src="{{ $p->image_url }}" alt="{{ $p->name }}" loading="lazy"
                onerror="this.outerHTML='{{ $sfFallbackSvg }}'">
        @else
            {!! $sfFallbackSvg !!}
        @endif
        <span class="sf-card-badge">
            @if ($onSale)
                <span>−{{ (int) round((($p->compare_price_minor - $p->price_minor) / $p->compare_price_minor) * 100) }}%</span>
            @endif
            @if ($isNew)
                <span style="background:{{ $accent }}">{{ __('New') }}</span>
            @endif
            @if (!$p->in_stock)
                <span class="sf-badge-stock">{{ __('Sold out') }}</span>
            @endif
        </span>
    </a>
    <button class="sf-wish" data-wish-toggle="{{ $p->id }}"
        onclick="STOREFRONT.toggleWish({{ $p->id }})" aria-label="{{ __('Add to wishlist') }}">
        <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6">
            <path d="M8 13.4S2 10 2 6.2A3.2 3.2 0 0 1 8 4.4 3.2 3.2 0 0 1 14 6.2c0 3.8-6 7.2-6 7.2Z" />
        </svg>
    </button>
    <button class="sf-quick" onclick="STOREFRONT.openQuickView({{ $p->id }})"
        aria-label="{{ __('Quick view') }}" title="{{ __('Quick view') }}">
        <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6">
            <path d="M1.5 8s2.5-5 6.5-5 6.5 5 6.5 5-2.5 5-6.5 5-6.5-5-6.5-5z" />
            <circle cx="8" cy="8" r="2" />
        </svg>
    </button>
    <div class="body">
        @if ($p->category)
            <div class="cat">{{ $p->category }}</div>
        @endif
        <h3><a href="{{ url('/s/' . $sf->slug . '/p/' . $p->slug) }}">{{ $p->name }}</a></h3>
        <div class="price">
            <b>{{ $p->price_display }}</b>
            @if ($onSale)
                <span class="sf-card-was">{{ $p->compare_price_display }}</span>
            @endif
        </div>
        <button class="add" @disabled(!$p->in_stock)
            onclick="STOREFRONT.add({{ $p->id }}, {{ json_encode($p->name) }}, {{ $p->price_minor }}, {{ json_encode($p->image_url) }})">
            @if ($p->in_stock)
                Add to cart
            @else
                Sold out
            @endif
        </button>
    </div>
</div>
