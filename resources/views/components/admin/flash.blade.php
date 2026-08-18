{{--
 Shared admin flash banner.
 Renders styled success/error/warning callouts for `session('status')`,
 `session('error')`, and `session('warning')`. Drop one of these at the
 top of every admin page that handles a POST so save confirmations
 look consistent instead of bare-span "text only" leakage.

 Usage:
 <x-admin.flash />

 Optional props:
 :inline=true compact (single-line) — used in tight header bars
--}}
@props([
    'inline' => false,
])

@php
    // Accept either `status` (Laravel convention) or `success` (used by
    // a few older admin controllers). Both render as the green banner.
    $status = session('status') ?: session('success');
    $error = session('error');
    $warning = session('warning');
    $base = $inline
        ? 'rounded-lg px-3 py-1.5 text-[11.5px] font-mono inline-flex items-center gap-2'
        : 'rounded-xl px-4 py-3 text-[12.5px] font-mono flex items-center gap-2.5 mb-4';
@endphp

@if ($status)
    <div class="{{ $base }} bg-wa-mint border border-wa-green/30 text-wa-deep">
        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8">
            <circle cx="8" cy="8" r="6" />
            <path d="M5.5 8.5l2 2 3-4" />
        </svg>
        <span>{{ $status }}</span>
    </div>
@endif

@if ($error)
    <div class="{{ $base }} bg-accent-coral/10 border border-accent-coral/30 text-accent-coral">
        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8">
            <circle cx="8" cy="8" r="6" />
            <path d="M8 5v3.5M8 11v.5" />
        </svg>
        <span>{{ $error }}</span>
    </div>
@endif

@if ($warning)
    <div class="{{ $base }} bg-accent-amber/15 border border-accent-amber/40 text-[#7B5A14]">
        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M8 2.5L14 13H2L8 2.5z" />
            <path d="M8 7v3M8 11.5v.5" />
        </svg>
        <span>{{ $warning }}</span>
    </div>
@endif
