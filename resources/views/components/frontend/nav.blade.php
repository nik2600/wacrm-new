@props([
    /** Which nav item to underline: product | features | pricing | resources | changelog */
    'active' => 'product',
])

@php
    $items = [
        'product' => [
            'label' => fc('nav.link1_label', __('Home')),
            'href' => fc('nav.link1_url', url('/')),
            'key' => 'nav.link1_label',
            'urlKey' => 'nav.link1_url',
        ],
        'features' => [
            'label' => fc('nav.link2_label', __('Features')),
            'href' => fc('nav.link2_url', url('/features')),
            'key' => 'nav.link2_label',
            'urlKey' => 'nav.link2_url',
        ],
        'pricing' => [
            'label' => fc('nav.link3_label', __('Pricing')),
            'href' => fc('nav.link3_url', url('/pricing')),
            'key' => 'nav.link3_label',
            'urlKey' => 'nav.link3_url',
        ],
        'blog' => [
            'label' => __('Blog'),
            'href' => route('frontend.blog'),
            'key' => 'nav.blog_label',
            'urlKey' => 'nav.blog_url',
        ],
        'about' => [
            'label' => fc('nav.link4_label', __('About')),
            'href' => fc('nav.link4_url', url('/about')),
            'key' => 'nav.link4_label',
            'urlKey' => 'nav.link4_url',
        ],
        'contact' => [
            'label' => fc('nav.link5_label', __('Contact')),
            'href' => fc('nav.link5_url', url('/contact')),
            'key' => 'nav.link5_label',
            'urlKey' => 'nav.link5_url',
        ],
    ];

    $brandName = (string) brand_name();
    $appVersion = config('app.version', 'v 4.2');
    $brandLogo = \App\Support\Brand::logoUrl(); // uploaded logo, or null
@endphp

<header data-fc-section="nav" class="sticky top-0 z-40 bg-white/92 backdrop-blur hairline-b">
    <div class="relative max-w-[1360px] mx-auto px-4 sm:px-7 h-[68px] flex items-center gap-8">

        {{-- Logo + brand. If an admin uploaded a site logo (admin → General
 settings), show it; otherwise fall back to the SVG mark + wordmark. --}}
        <a href="{{ url('/') }}" class="flex items-center gap-2.5">
            @if ($brandLogo)
                <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-9 w-auto max-w-[180px] object-contain">
            @else
                <span
                    class="relative inline-flex items-center justify-center w-9 h-9 rounded-xl bg-wa-deep text-paper-0">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                        <path
                            d="M12 2C6.48 2 2 6.48 2 12c0 1.96.57 3.79 1.55 5.34L2 22l4.78-1.5A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Zm5.07 14.07c-.21.6-1.22 1.14-1.7 1.21-.45.07-1.02.1-1.65-.1-.38-.12-.87-.28-1.49-.55-2.62-1.13-4.33-3.77-4.46-3.94-.13-.18-1.07-1.42-1.07-2.71 0-1.29.68-1.92.92-2.18.24-.27.52-.34.7-.34h.5c.16 0 .38-.06.59.45.21.51.71 1.76.77 1.89.06.13.1.28.02.45-.08.18-.12.28-.24.43-.12.15-.26.34-.37.46-.12.12-.25.26-.11.51.14.26.62 1.02 1.33 1.65.91.81 1.68 1.06 1.94 1.18.26.13.41.11.56-.06.15-.18.65-.76.83-1.02.18-.26.36-.21.6-.13.24.09 1.55.73 1.81.86.27.13.45.2.51.31.07.12.07.69-.14 1.29Z" />
                    </svg>
                    <span
                        class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-wa-green ring-2 ring-white"></span>
                </span>
                <div class="leading-none">
                    <div class="serif text-[24px]">{{ $brandName }}</div>
                    <div class="mono text-[8.5px] uppercase tracking-[0.22em] text-ink-500 mt-1">{{ $appVersion }} ·
                        {{ __('platform') }}</div>
                </div>
            @endif
        </a>

        <div class="flex-1"></div>

        {{-- Centered nav --}}
        <nav class="absolute left-1/2 -translate-x-1/2 hidden lg:flex items-center gap-7 text-[13.5px] text-ink-700">
            @foreach ($items as $key => $item)
                @php $isActive = $active === $key; @endphp
                <a href="{{ $item['href'] }}" data-fc="{{ $item['key'] }}" data-fc-url="{{ $item['urlKey'] }}"
                    class="relative {{ $isActive ? 'font-semibold text-wa-deep' : 'hover:text-wa-deep cursor-pointer' }}">
                    {{ $item['label'] }}
                    @if ($isActive)
                        <span class="absolute -bottom-[22px] left-0 right-0 h-[2px] bg-wa-deep"></span>
                    @endif
                </a>
            @endforeach
        </nav>

        {{-- Sign in / Start free. If the visitor is already authed, show
 Dashboard instead so they don't have to log in twice. --}}
        <div class="hidden lg:flex items-center gap-2">
            @auth
                <a href="{{ url('/dashboard') }}" data-fc="nav.cta_dashboard_label"
                    class="px-4 py-2.5 rounded-full bg-wa-deep text-paper-0 text-[13px] font-semibold hover:bg-wa-teal flex items-center gap-1.5">
                    {{ fc('nav.cta_dashboard_label', __('Dashboard')) }}
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 4l4 4-4 4" />
                    </svg>
                </a>
            @else
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" data-fc="nav.signin_label"
                        class="text-[13.5px] font-medium text-ink-700 hover:text-wa-deep px-3 py-2">{{ fc('nav.signin_label', __('Sign in')) }}</a>
                @endif
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" data-fc="nav.cta_label"
                        class="px-4 py-2.5 rounded-full bg-wa-deep text-paper-0 text-[13px] font-semibold hover:bg-wa-teal flex items-center gap-1.5">
                        {{ fc('nav.cta_label', __('Start free')) }}
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 4l4 4-4 4" />
                        </svg>
                    </a>
                @endif
            @endauth
        </div>

        {{-- Mobile Hamburger Toggle --}}
        <button id="fc-mobile-menu-toggle"
            class="lg:hidden p-2 text-ink-700 hover:text-ink-900 focus:outline-none z-50 relative">
            <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>
</header>

{{-- Mobile Menu Overlay --}}
<div id="fc-mobile-menu"
    class="fixed inset-0 bg-white z-40 lg:hidden transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col pt-20 px-7 pb-7 overflow-y-auto">
    <div class="flex flex-col gap-6 text-[18px] font-medium mt-4">
        @foreach ($items as $key => $item)
            <a href="{{ $item['href'] }}"
                class="border-b border-paper-200 pb-4 text-ink-900 hover:text-wa-deep">{{ $item['label'] }}</a>
        @endforeach
    </div>
    <div class="mt-8 flex flex-col gap-4">
        @auth
            <a href="{{ url('/dashboard') }}"
                class="w-full py-4 text-center rounded-full bg-wa-deep text-paper-0 text-[16px] font-semibold hover:bg-wa-teal">
                {{ fc('nav.cta_dashboard_label', __('Dashboard')) }}
            </a>
        @else
            @if (Route::has('register'))
                <a href="{{ route('register') }}"
                    class="w-full py-4 text-center rounded-full bg-wa-deep text-paper-0 text-[16px] font-semibold hover:bg-wa-teal">
                    {{ fc('nav.cta_label', __('Start free')) }}
                </a>
            @endif
            @if (Route::has('login'))
                <a href="{{ route('login') }}"
                    class="w-full py-4 text-center rounded-full bg-paper-100 text-ink-900 text-[16px] font-semibold hover:bg-paper-200">
                    {{ fc('nav.signin_label', __('Sign in')) }}
                </a>
            @endif
        @endauth
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('fc-mobile-menu-toggle');
        const menu = document.getElementById('fc-mobile-menu');
        let isOpen = false;

        if (toggleBtn && menu) {
            toggleBtn.addEventListener('click', function() {
                isOpen = !isOpen;
                if (isOpen) {
                    menu.classList.remove('translate-x-full');
                    toggleBtn.innerHTML =
                        '<svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>';
                    document.body.style.overflow = 'hidden';
                } else {
                    menu.classList.add('translate-x-full');
                    toggleBtn.innerHTML =
                        '<svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>';
                    document.body.style.overflow = '';
                }
            });
        }
    });
</script>
