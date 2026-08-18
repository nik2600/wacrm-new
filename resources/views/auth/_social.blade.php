{{--
 Shared social sign-in buttons for the login & register pages.
 Renders a button per enabled provider (Google / Facebook) and the
 "or with email" divider. Renders nothing when no provider is enabled,
 so the email form simply sits at the top.

 Each button is a plain link to the OAuth redirect route — the actual
 consent + callback is handled by SocialAuthController.

 @param bool $compact Tighter padding/sizing for the denser register page.
--}}
@php
    /** @var \App\Services\SocialAuthService $__social */
    $__social = app(\App\Services\SocialAuthService::class);
    $__providers = array_values(
        array_filter(\App\Services\SocialAuthService::PROVIDERS, fn($p) => $__social->enabled($p)),
    );
    $__compact = $compact ?? false;
    $__pad = $__compact ? 'px-3 py-2 text-[12.5px]' : 'px-3 py-2.5 text-[13px]';
    $__icon = $__compact ? 'w-3.5 h-3.5' : 'w-4 h-4';
@endphp

@if (count($__providers))
    <div
        class="grid {{ count($__providers) === 1 ? 'grid-cols-1' : 'grid-cols-2' }} gap-2 {{ $__compact ? '' : 'mt-6' }}">
        @if (in_array('google', $__providers, true))
            <a href="{{ route('social.redirect', 'google') }}"
                class="{{ $__pad }} border border-paper-200 rounded-lg bg-paper-0 hover:bg-paper-50 font-medium inline-flex items-center justify-center gap-2">
                <svg viewBox="0 0 16 16" class="{{ $__icon }}">
                    <path fill="#4285F4"
                        d="M15.6 8.18c0-.55-.05-1.07-.14-1.58H8v3h4.27c-.18.97-.74 1.79-1.58 2.34v1.94h2.55c1.5-1.38 2.36-3.41 2.36-5.7z" />
                    <path fill="#34A853"
                        d="M8 16c2.13 0 3.92-.71 5.23-1.92l-2.55-1.97c-.71.47-1.61.75-2.68.75-2.06 0-3.81-1.39-4.43-3.27H1v2.05A8 8 0 0 0 8 16z" />
                    <path fill="#FBBC05"
                        d="M3.57 9.59A4.8 4.8 0 0 1 3.32 8c0-.55.09-1.09.25-1.59V4.36H1A8 8 0 0 0 0 8c0 1.29.31 2.51.86 3.59L3.57 9.59z" />
                    <path fill="#EA4335"
                        d="M8 3.16c1.16 0 2.2.4 3.02 1.18L13.27 2.1A8 8 0 0 0 8 0a8 8 0 0 0-7 4.36l2.57 2.05C4.19 4.55 5.94 3.16 8 3.16z" />
                </svg>
                {{ __('Google') }}
            </a>
        @endif
        @if (in_array('facebook', $__providers, true))
            <a href="{{ route('social.redirect', 'facebook') }}"
                class="{{ $__pad }} border border-paper-200 rounded-lg bg-paper-0 hover:bg-paper-50 font-medium inline-flex items-center justify-center gap-2">
                <svg viewBox="0 0 16 16" class="{{ $__icon }}" fill="#1877F2">
                    <path
                        d="M16 8a8 8 0 1 0-9.25 7.9v-5.59H4.72V8h2.03V6.24c0-2 1.2-3.11 3.02-3.11.87 0 1.79.16 1.79.16v1.97H10.55c-.99 0-1.3.62-1.3 1.25V8h2.22l-.36 2.31H9.25v5.59A8 8 0 0 0 16 8z" />
                </svg>
                {{ __('Facebook') }}
            </a>
        @endif
    </div>

    <div class="flex items-center gap-3 {{ $__compact ? 'my-3' : 'my-5' }}">
        <div class="flex-1 h-px bg-paper-200"></div>
        <span class="text-[10.5px] font-mono uppercase tracking-wider text-ink-500">{{ __('or with email') }}</span>
        <div class="flex-1 h-px bg-paper-200"></div>
    </div>
@endif
