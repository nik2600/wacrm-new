@php
    $__brandName = (string) brand_name();
    $__authVariant = (int) \App\Models\SystemSetting::get('auth.variant', '1');
    if ($__authVariant < 1 || $__authVariant > 5) $__authVariant = 1;
@endphp

@if ($__authVariant !== 1)
    <x-auth-shell page="forgot" :title="__('Reset password')">
        @include('auth._forgot_form')
    </x-auth-shell>
@else
    <x-layouts.guest :title="__('Reset password')" page="auth-forgot-password">

        <div class="grid lg:grid-cols-[1fr_540px] {{ fc_editing() ? 'min-h-screen' : 'h-screen overflow-hidden' }}">

            <!-- LEFT: visual showcase (mirrors login page) -->
            <aside class="auth-art relative hidden lg:flex flex-col p-10 text-paper-0 overflow-hidden" data-ae-media="forgot">
                <div class="blob bg-wa-green w-[300px] h-[300px] -top-12 -left-12"></div>
                <div class="blob bg-accent-amber w-[260px] h-[260px] bottom-12 right-12"></div>
                @php $__authMedia = auth_cfg('forgot', 'media_url', ''); $__authMediaType = auth_cfg('forgot', 'media_type', ''); @endphp
                @if ($__authMedia)
                    @if ($__authMediaType === 'video')
                        <video src="{{ asset($__authMedia) }}" autoplay muted loop playsinline class="absolute inset-0 w-full h-full object-cover"></video>
                    @else
                        <img src="{{ asset($__authMedia) }}" class="absolute inset-0 w-full h-full object-cover" alt="">
                    @endif
                    <div class="absolute inset-0 bg-ink-950/55"></div>
                @endif

                <div class="relative z-10 flex-1 flex flex-col justify-center w-full">
                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-5 mb-4">
                        <div class="flex items-start gap-3">
                            <span class="w-10 h-10 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center shrink-0">
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 8a5 5 0 1 1 10 0v3H3V8z" /><path d="M6 11v-3a2 2 0 1 1 4 0v3" /></svg>
                            </span>
                            <div>
                                <div class="text-[14px] font-semibold leading-tight">{{ __('Forgot password?') }}</div>
                                <div class="text-[12px] text-paper-0/75 leading-snug mt-1">{{ __("No problem. We'll mail you a fresh single-use link.") }}</div>
                            </div>
                        </div>
                    </div>

                    @php $__accent = auth_cfg('forgot', 'accent', '#25D366'); @endphp
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-paper-0/70 mb-3" data-fc="forgot.eyebrow">{{ auth_cfg('forgot', 'eyebrow', __('Account recovery')) }}</div>
                    <h1 class="font-serif text-[42px] leading-[1.05] tracking-[-0.01em]"><span data-fc="forgot.heading">{{ auth_cfg('forgot', 'heading', __('Get back in,')) }}</span>
                        <span class="italic" style="color: {{ $__accent }}" data-fc="forgot.heading_accent">{{ auth_cfg('forgot', 'heading_accent', __('fast')) }}</span>.</h1>
                    <p class="mt-3 text-[13px] text-paper-0/85 leading-relaxed" data-fc="forgot.subheading">{{ auth_cfg('forgot', 'subheading', __("Enter the email you signed up with. If we have an account on file, we'll send a reset link that's valid for 60 minutes.")) }}</p>
                </div>

                <div class="relative z-10 text-[11px] text-paper-0/60 font-mono mt-6 text-right" data-fc="forgot.footer">{{ auth_cfg('forgot', 'footer', '2026 ' . $__brandName . ' / ' . app_default_country_name()) }}</div>
            </aside>

            <!-- RIGHT: form -->
            <main class="flex flex-col justify-center px-6 py-10 lg:px-14">
                <div class="w-full max-w-[400px] mx-auto">
                    <a href="{{ route('login') }}" class="lg:hidden inline-flex items-center gap-2 mb-8">
                        <span class="w-8 h-8 rounded-md bg-wa-deep text-paper-0 grid place-items-center"><svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12c0 1.96.57 3.79 1.55 5.34L2 22l4.78-1.5A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Z" /></svg></span>
                        <span class="font-serif text-[22px] tracking-[-0.01em]">{{ $__brandName }}</span>
                    </a>

                    @include('auth._forgot_form')
                </div>
            </main>

        </div>

    </x-layouts.guest>
@endif
