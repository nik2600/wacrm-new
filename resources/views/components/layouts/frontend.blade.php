@props([
    'title' => brand_name() . ' — The complete WhatsApp business platform',
    'page' => null,
    'navKey' => 'product',
    'showTicker' => true,
    'showFooter' => true,
    // SEO — any public page can override these; sensible site-wide defaults.
    'description' => null,
    'metaKeywords' => null,
    'ogImage' => null,
    'ogType' => 'website',
    'canonical' => null,
    'noindex' => false,
    'jsonLd' => null,
])

{{--
 Public landing layout — direct port of the prototype's <head>.

 Tailwind ships from the CDN with the exact same inline config the
 prototype uses (so the utility classes resolve to the same hex
 values). frontend.css holds the prototype's <style> block verbatim
 and is loaded as a standalone Vite entry — no app.css involvement,
 no dashboard CSS leaks into the public pages.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>

    {{-- SEO meta — the SAME single source of truth as every other layout:
         partials/seo-meta.blade.php reading App\Support\Seo::meta() (admin →
         /admin/settings/seo). This public layout was the only one missing it.
         Per-page props below become $seoOverrides so a page (e.g. a blog post)
         can override title/description/og_image/canonical/robots. --}}
    @include('partials.seo-meta', ['seoOverrides' => array_filter([
        'title'       => $title,
        'description' => $description,
        'keywords'    => $metaKeywords,
        'og_image'    => $ogImage,
        'og_type'     => $ogType,
        'canonical'   => $canonical,
        'robots'      => $noindex ? 'noindex, nofollow' : null,
    ], fn ($v) => $v !== null && $v !== '')])

    {{-- Structured data (JSON-LD) — pages can pass an array via :json-ld
         (e.g. blog posts emit Article schema). The shared partial covers the
         meta/OG/Twitter tags; JSON-LD is page-specific so it lives here. --}}
    @if ($jsonLd)
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif

    {{-- Favicon — same uploaded asset the admin/app uses (Site logo & favicon
 are set in admin → General settings / brand assets). --}}
    @php $fcFavicon = \App\Support\Brand::faviconUrl(); @endphp
    @if ($fcFavicon)
        <link rel="icon" type="image/x-icon" href="{{ $fcFavicon }}">
        <link rel="shortcut icon" href="{{ $fcFavicon }}">
    @endif

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap"
        rel="stylesheet">

    @php
        // Theme palette — editable via the admin Frontend live editor. Each
        // token defaults to its shipped hex, so an un-edited install renders
        // byte-for-byte identical. @json() emits a clean JS object for the
        // Tailwind CDN config below.
        $fcColors = [
            'ink' => [
                950 => fc('theme.ink.950', '#070D0C'),
                900 => fc('theme.ink.900', '#0B1F1C'),
                800 => fc('theme.ink.800', '#13312D'),
                700 => fc('theme.ink.700', '#1F4540'),
                600 => fc('theme.ink.600', '#3A5A55'),
                500 => fc('theme.ink.500', '#6B807C'),
                400 => fc('theme.ink.400', '#9AA8A4'),
                300 => fc('theme.ink.300', '#C3CCC9'),
            ],
            'wa' => [
                'deep' => fc('theme.wa.deep', '#075E54'),
                'teal' => fc('theme.wa.teal', '#128C7E'),
                'green' => fc('theme.wa.green', '#25D366'),
                'mint' => fc('theme.wa.mint', '#DCF8C6'),
                'bubble' => fc('theme.wa.bubble', '#E7FFDB'),
                'chat' => fc('theme.wa.chat', '#ECE5DD'),
            ],
            'paper' => [
                0 => fc('theme.paper.0', '#FBFAF6'),
                50 => fc('theme.paper.50', '#F5F3EC'),
                100 => fc('theme.paper.100', '#EFEBE0'),
                200 => fc('theme.paper.200', '#E5DFD0'),
                300 => fc('theme.paper.300', '#D4CCB6'),
            ],
            'accent' => [
                'coral' => fc('theme.accent.coral', '#E87A5D'),
                'amber' => fc('theme.accent.amber', '#E5A04E'),
                'sand' => fc('theme.accent.sand', '#D9C9A3'),
                'plum' => fc('theme.accent.plum', '#5B3D8A'),
                'sky' => fc('theme.accent.sky', '#3E7AA1'),
            ],
        ];
    @endphp
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        serif: ['"Instrument Serif"', 'serif'],
                        sans: ['Inter', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace']
                    },
                    colors: @json($fcColors)
                }
            }
        }
    </script>

    {{-- Prototype's <style> block — lives in resources/css/frontend.css,
 served directly via its own Vite entry. --}}
    @vite(['resources/css/frontend.css'])
    @include('partials.app-font')

    {{-- Admin → Settings → Custom code (CSS). Platform-admin only; injected
         on public pages when enabled. --}}
    @php($__wdCustomOn = (bool) \App\Models\SystemSetting::get('custom_code_enabled', false))
    @if ($__wdCustomOn && ($__wdCss = (string) \App\Models\SystemSetting::get('custom_css', '')) !== '')
        <style id="wd-custom-css">{!! $__wdCss !!}</style>
    @endif

    {{-- Site-wide analytics (GA4 / GTM / Meta Pixel / Clarity / etc. from
         Admin → Settings → Analytics). Must run on the PUBLIC site — this is
         where visitor/ad tracking matters most. Consent-gated inside. --}}
    @include('partials.site-analytics')
</head>

<body @if ($page) data-page="{{ $page }}" @endif data-nav="{{ $navKey }}" class="overflow-x-clip">

    @if ($showTicker)
        <x-frontend.ticker />
    @endif

    <x-frontend.nav :active="$navKey" />

    {{ $slot }}

    @if ($showFooter)
        <x-frontend.footer />
    @endif

    {{-- Reveal-on-scroll observer — same shape as the prototype's inline
 script. Elements with .reveal fade + slide in once they enter the
 viewport; pages can stagger with style="--d:NNNms". --}}
    <script>
        (function() {
            var els = document.querySelectorAll('.reveal');
            if (!els.length || !('IntersectionObserver' in window)) {
                els.forEach(function(e) {
                    e.classList.add('in');
                });
                return;
            }
            var io = new IntersectionObserver(function(entries) {
                entries.forEach(function(e) {
                    if (e.isIntersecting) {
                        e.target.classList.add('in');
                        io.unobserve(e.target);
                    }
                });
            }, {
                threshold: 0.1
            });
            els.forEach(function(e) {
                io.observe(e);
            });
        })();
    </script>

    @stack('scripts')

    {{-- Live-editor bridge — injected ONLY for an authed platform admin who
 opened this page with ?fc_edit=1 (see FrontendContentStore::editing()).
 Public visitors never receive this script. --}}
    @if (fc_editing())
        <script src="{{ asset('js/frontend-editor-bridge.js') }}" data-save="{{ route('admin.frontend.draft') }}"
            data-csrf="{{ csrf_token() }}"></script>
    @endif

    {{-- Admin → Settings → Custom code (JS). Platform-admin only; injected
         on public pages when enabled. --}}
    @if (($__wdCustomOn ?? false) && ($__wdJs = (string) \App\Models\SystemSetting::get('custom_js', '')) !== '')
        <script id="wd-custom-js">{!! $__wdJs !!}</script>
    @endif

    {{-- GDPR cookie consent — lets PUBLIC visitors opt in so the analytics
         partial above can emit their consented trackers. --}}
    @include('partials.cookie-consent')

</body>

</html>
