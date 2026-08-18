@php
    // When maintenance is on, a logged-in NON-admin needs to sign out first, then
    // sign back in as an admin to get through; a logged-out visitor just signs in.
    // (Platform staff never reach this page — the Maintenance middleware lets them
    // straight through.)
    $__authed = auth()->check();
@endphp
@include('errors.layout', [
    'title' => 'Down for maintenance',
    'eyebrow' => 'Be right back',
    'code' => '503',
    'headline' =>
        brand_name() . ' is briefly offline.',
    'body' =>
        "We're shipping an update or running scheduled maintenance. The app will be back in a few minutes / no action needed on your end.",
    // Admin entry point — signed-out admins sign in; signed-in users sign out so
    // they can sign back in with an admin account. Both routes stay open while down.
    'ctaUrl' => $__authed ? url('/logout') : url('/login'),
    'ctaLabel' => $__authed ? __('Sign out') : __('Admin sign in'),
])
