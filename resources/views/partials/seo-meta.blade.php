{{--
 Emits the full SEO meta block: description/keywords/robots, OpenGraph,
 Twitter Card, canonical, search-engine site-verification tags, and
 optional author meta.

 Pulls everything from App\Support\Seo::meta() which reads the
 system_settings rows that /admin/settings/seo writes — single source
 of truth. Pages can override per-page by passing $seoOverrides to
 the layout (handled at the include site, not here).

 Empty values are skipped so we never emit <meta content=""> spam.
--}}
@php $__seo = \App\Support\Seo::meta($seoOverrides ?? []); @endphp

@if (!empty($__seo['description']))
    <meta name="description" content="{{ $__seo['description'] }}">
@endif
@if (!empty($__seo['keywords']))
    <meta name="keywords" content="{{ $__seo['keywords'] }}">
@endif
@if (!empty($__seo['author']))
    <meta name="author" content="{{ $__seo['author'] }}">
@endif
@if (!empty($__seo['robots']))
    <meta name="robots" content="{{ $__seo['robots'] }}">
@endif
@if (!empty($__seo['canonical']))
    <link rel="canonical" href="{{ $__seo['canonical'] }}">
@endif

{{-- OpenGraph (Facebook, LinkedIn, WhatsApp link previews) --}}
<meta property="og:site_name" content="{{ $__seo['brand'] }}">
<meta property="og:title" content="{{ $__seo['og_title'] }}">
@if (!empty($__seo['og_description']))
    <meta property="og:description" content="{{ $__seo['og_description'] }}">
@endif
<meta property="og:type" content="{{ $__seo['og_type'] }}">
@if (!empty($__seo['og_url']))
    <meta property="og:url" content="{{ $__seo['og_url'] }}">
@endif
@if (!empty($__seo['og_image']))
    <meta property="og:image" content="{{ $__seo['og_image'] }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
@endif

{{-- Twitter Card --}}
<meta name="twitter:card" content="{{ $__seo['twitter_card'] }}">
<meta name="twitter:title" content="{{ $__seo['og_title'] }}">
@if (!empty($__seo['og_description']))
    <meta name="twitter:description" content="{{ $__seo['og_description'] }}">
@endif
@if (!empty($__seo['og_image']))
    <meta name="twitter:image" content="{{ $__seo['og_image'] }}">
@endif
@if (!empty($__seo['twitter_site']))
    <meta name="twitter:site" content="{{ \Illuminate\Support\Str::start($__seo['twitter_site'], '@') }}">
@endif
@if (!empty($__seo['twitter_creator']))
    <meta name="twitter:creator" content="{{ \Illuminate\Support\Str::start($__seo['twitter_creator'], '@') }}">
@endif

{{-- Search-engine site verification --}}
@if (!empty($__seo['google_verification']))
    <meta name="google-site-verification" content="{{ $__seo['google_verification'] }}">
@endif
@if (!empty($__seo['bing_verification']))
    <meta name="msvalidate.01" content="{{ $__seo['bing_verification'] }}">
@endif
