{{--
 Team-Inbox PWA <head> block — a SEPARATE installable app scoped to
 /team-inbox (its own icon, opens straight to the shared inbox, push
 notifications). Rendered ONLY on the team-inbox page and ONLY when the admin
 has enabled it (see components/layouts/user.blade.php). Points the browser at
 the scoped manifest; the service worker + push subscription are wired in
 resources/js/charts/user-team-inbox-index.js.
--}}
@php
    $tiTheme = (string) (\App\Models\SystemSetting::get('ti_pwa_theme_color') ?: \App\Models\SystemSetting::get('pwa_theme_color', '#075E54'));
    $tiIcon  = (string) (\App\Models\SystemSetting::get('ti_pwa_icon_192') ?: \App\Models\SystemSetting::get('pwa_icon_192') ?: (\App\Support\Brand::faviconUrl() ?? ''));
    $tiName  = trim((string) \App\Models\SystemSetting::get('ti_pwa_short_name', '')) ?: 'Inbox';
@endphp
<link rel="manifest" href="{{ url('/team-inbox-manifest.json') }}">
<meta name="theme-color" content="{{ $tiTheme }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $tiName }}">
@if ($tiIcon)
    <link rel="apple-touch-icon" href="{{ $tiIcon }}">
@endif
<style>
    /* When launched as the INSTALLED Team-Inbox app (standalone display mode),
       hide the main app header so the window is inbox-only — the user can't
       wander off to Dashboard / Campaigns / etc. In a normal browser tab the
       header stays. This CSS only exists on the team-inbox page (this partial
       renders there only), so it never affects the rest of the app. */
    @media all and (display-mode: standalone) {
        body > header { display: none !important; }
    }
</style>
