{{-- Premium-feature badge. Renders a shiny gold crown next to feature
 labels when the current workspace's plan does NOT include the
 feature. Clicking jumps to /pricing.

 Usage:
 <x-plan-crown feature="access_waba_calling" />
 <x-plan-crown :feature="['access_ai_agents','access_ai_voice_agent']" :any="true" />

 Props:
 feature string|array — single flag key OR array of keys
 any bool — when an array is passed: true = require ANY
 of the flags, false = require ALL. Default false.
 size string — sm | md | lg (default md)
 label string|null — optional tooltip override
 link bool — true (default) renders a clickable <a> to
 /account/plans. Pass false when the crown is
 already INSIDE another <a> (e.g. the /more
 feature cards): a link-in-link is invalid HTML
 and the browser hoists the inner one out, so we
 render a plain <span> badge instead — the parent
 card already routes to the feature's paywall.
--}}
@props([
    'feature' => null,
    'any' => false,
    'size' => 'md',
    'label' => null,
    'link' => true,
])

@php
    if ($feature === null || $feature === '') {
        return;
    }

    $workspace = auth()->user()?->currentWorkspace ?? null;
    $flags = is_array($feature) ? $feature : [$feature];

    // Platform admins always see content — they're not "missing" anything.
$isAdmin = false;
try {
    $u = auth()->user();
    if (
        $u &&
        ((method_exists($u, 'hasRole') && ($u->hasRole('Super Admin') || $u->hasRole('Admin'))) ||
            in_array((string) ($u->role ?? ''), ['admin', 'A', 'super-admin', 'platform-admin'], true))
    ) {
        $isAdmin = true;
    }
} catch (\Throwable $e) {
}

if ($isAdmin) {
    return;
}

$checker = fn($key) => \App\Services\PlanLimitGuard::hasFeature($workspace, $key);
$satisfied = $any ? collect($flags)->contains($checker) : collect($flags)->every($checker);
if ($satisfied) {
    return;
}

$dim = match ($size) {
    'sm' => 'w-3 h-3',
    'lg' => 'w-4.5 h-4.5',
    default => 'w-3.5 h-3.5',
};
$tooltip = $label ?: __('Premium feature — upgrade your plan to unlock');
$tag = $link ? 'a' : 'span';
@endphp

<{{ $tag }} @if ($link) href="{{ url('/account/plans') }}" @endif
    title="{{ $tooltip }}" class="plan-crown inline-flex items-center justify-center align-middle ml-1 leading-none"
    aria-label="{{ $tooltip }}">
    <svg viewBox="0 0 16 16" class="{{ $dim }} text-accent-amber plan-crown__shine" fill="currentColor"
        aria-hidden="true">
        <defs>
            {{-- Shiny gold gradient. Wrapped in a unique-id-per-render
 hash so multiple crowns on the same page don't share
 ids across SVGs. --}}
            @php $gradId = 'crown-grad-' . uniqid(); @endphp
            <linearGradient id="{{ $gradId }}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#FFD86B" />
                <stop offset="50%" stop-color="#F5B400" />
                <stop offset="100%" stop-color="#C58300" />
            </linearGradient>
        </defs>
        <path d="M2 5l2.5 3L8 3l3.5 5L14 5v7H2z" fill="url(#{{ $gradId }})" stroke="#B7780A" stroke-width="0.4" />
        <circle cx="2" cy="5" r="1" fill="#FFE9A0" stroke="#B7780A" stroke-width="0.35" />
        <circle cx="8" cy="3" r="1" fill="#FFE9A0" stroke="#B7780A" stroke-width="0.35" />
        <circle cx="14" cy="5" r="1" fill="#FFE9A0" stroke="#B7780A" stroke-width="0.35" />
    </svg>
    </{{ $tag }}>

    @once
        @push('styles')
            <style>
                /* Soft "shine" sweep — a thin highlight slides across the crown
         every few seconds. Keeps the icon visibly premium without
         being noisy. */
                .plan-crown__shine {
                    filter: drop-shadow(0 0 1.5px rgba(245, 180, 0, 0.55));
                    position: relative;
                    animation: plan-crown-glow 2.6s ease-in-out infinite alternate;
                }

                @keyframes plan-crown-glow {
                    0% {
                        filter: drop-shadow(0 0 1.2px rgba(245, 180, 0, 0.35));
                    }

                    100% {
                        filter: drop-shadow(0 0 3.5px rgba(255, 216, 107, 0.85));
                    }
                }

                /* Hover: brighter glow + tiny lift so it feels actionable. */
                .plan-crown:hover .plan-crown__shine {
                    filter: drop-shadow(0 0 5px rgba(255, 216, 107, 0.95));
                    transform: translateY(-0.5px) scale(1.06);
                }

                .plan-crown {
                    transition: transform 120ms ease-out;
                }
            </style>
        @endpush
    @endonce
