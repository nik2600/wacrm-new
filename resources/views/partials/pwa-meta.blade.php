{{--
 PWA <head> block — manifest link + theme color + apple-mobile-web-app
 meta tags. Renders only when admin has enabled PWA at
 /admin/settings/pwa. Pulls theme color + icon URLs from system_settings
 so the chrome (Android URL bar, iOS status bar) matches whatever the
 admin saved.
--}}
@php
    $pwaEnabled = (bool) \App\Models\SystemSetting::get('pwa_enabled', false);
    if ($pwaEnabled) {
        $themeColor = (string) \App\Models\SystemSetting::get('pwa_theme_color', '#075E54');
        $shortName = (string) \App\Models\SystemSetting::get(
            'pwa_short_name',
            brand_name(),
        );
        $iconUrl = (string) \App\Models\SystemSetting::get(
            'pwa_icon_192',
            (string) (\App\Support\Brand::faviconUrl() ?? ''),
        );
    }
@endphp
@if ($pwaEnabled)
    <link rel="manifest" href="{{ url('/manifest.json') }}">
    <meta name="theme-color" content="{{ $themeColor }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $shortName }}">
    @if ($iconUrl)
        <link rel="apple-touch-icon" href="{{ $iconUrl }}">
    @endif
@endif
