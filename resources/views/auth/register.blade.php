@php
    $__brandName = (string) brand_name();
    $__authVariant = (int) \App\Models\SystemSetting::get('auth.variant', '1');
    if ($__authVariant < 1 || $__authVariant > 5) $__authVariant = 1;
@endphp

@if ($__authVariant !== 1)
    <x-auth-shell page="register" :title="__('Create your account')">
        @include('auth._register_form')
    </x-auth-shell>
@else
    <x-layouts.guest :title="__('Create your account')" page="auth-register">

        <div class="grid lg:grid-cols-[1fr_540px] {{ fc_editing() ? 'min-h-screen' : 'h-screen overflow-hidden' }}">

            <!-- LEFT: visual showcase (mirrors login) -->
            <aside class="auth-art relative hidden lg:flex flex-col p-10 text-paper-0 overflow-hidden" data-ae-media="register">
                <div class="blob bg-wa-green w-[300px] h-[300px] -top-12 -left-12"></div>
                <div class="blob bg-accent-amber w-[260px] h-[260px] bottom-12 right-12"></div>
                @php $__authMedia = auth_cfg('register', 'media_url', ''); $__authMediaType = auth_cfg('register', 'media_type', ''); @endphp
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
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 4h12v8H2zM5 4v8M11 4v8" /></svg>
                            </span>
                            <div>
                                <div class="text-[14px] font-semibold leading-tight">{{ __('Free for 14 days') }}</div>
                                <div class="text-[12px] text-paper-0/75 leading-snug mt-1">{{ __('Every feature unlocked. No card needed. Cancel any time.') }}</div>
                            </div>
                        </div>
                    </div>

                    @php $__accent = auth_cfg('register', 'accent', '#25D366'); @endphp
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-paper-0/70 mb-3" data-fc="register.eyebrow">{{ auth_cfg('register', 'eyebrow', __('Trusted by 4,200+ teams')) }}</div>
                    <h1 class="font-serif text-[42px] leading-[1.05] tracking-[-0.01em]"><span data-fc="register.heading">{{ auth_cfg('register', 'heading', __('Start your')) }}</span>
                        <span class="italic" style="color: {{ $__accent }}" data-fc="register.heading_accent">{{ auth_cfg('register', 'heading_accent', __('workspace')) }}</span>.</h1>
                    <p class="mt-3 text-[13px] text-paper-0/85 leading-relaxed" data-fc="register.subheading">{{ auth_cfg('register', 'subheading', __('Sign up in 30 seconds. Pair your WhatsApp number, set up your first broadcast, and invite your team.')) }}</p>

                    <div class="grid grid-cols-3 gap-3 mt-5">
                        <div class="stat-pill rounded-2xl p-4 text-center"><div class="font-serif text-[24px] leading-none">42M+</div><div class="text-[10.5px] text-paper-0/70 mt-1">{{ __('messages sent') }}</div></div>
                        <div class="stat-pill rounded-2xl p-4 text-center"><div class="font-serif text-[24px] leading-none">99.9%</div><div class="text-[10.5px] text-paper-0/70 mt-1">{{ __('delivery rate') }}</div></div>
                        <div class="stat-pill rounded-2xl p-4 text-center"><div class="font-serif text-[24px] leading-none">4.9 *</div><div class="text-[10.5px] text-paper-0/70 mt-1">{{ __('G2 / Capterra') }}</div></div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mt-5">
                        <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                            <div class="flex items-center gap-2 mb-2"><span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 8h3l1.5-4 2 8 1.5-4h2" /></svg></span><div class="text-[12.5px] font-semibold">{{ __('Broadcasts') }}</div></div>
                            <div class="text-[11px] text-paper-0/70 leading-snug">{{ __('Send to thousands at once with smart throttling and per-contact tracking.') }}</div>
                        </div>
                        <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                            <div class="flex items-center gap-2 mb-2"><span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 3l8 5-8 5z" /></svg></span><div class="text-[12.5px] font-semibold">{{ __('Flow builder') }}</div></div>
                            <div class="text-[11px] text-paper-0/70 leading-snug">{{ __('Trigger / branch / wait / AI assist. Drag-drop the whole conversation.') }}</div>
                        </div>
                        <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                            <div class="flex items-center gap-2 mb-2"><span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-3Z" /></svg></span><div class="text-[12.5px] font-semibold">{{ __('Team inbox') }}</div></div>
                            <div class="text-[11px] text-paper-0/70 leading-snug">{{ __('Live shared inbox with assignments, internal notes, AI suggestions.') }}</div>
                        </div>
                        <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                            <div class="flex items-center gap-2 mb-2"><span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 5h8l1 6H4z" /></svg></span><div class="text-[12.5px] font-semibold">{{ __('Shopify + Woo') }}</div></div>
                            <div class="text-[11px] text-paper-0/70 leading-snug">{{ __('Cart recovery, order updates, and catalog sync out of the box.') }}</div>
                        </div>
                    </div>

                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4 mt-4">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/70 mb-2">{{ __("What's also inside") }}</div>
                        <div class="grid grid-cols-3 gap-x-3 gap-y-1.5 text-[11.5px] text-paper-0/85">
                            @foreach (['Templates', 'Meta Ads / CTWA', 'AI assist', 'Webhooks', 'Auto-replies', 'Encrypted'] as $__feat)
                                <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-green shrink-0" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l3 3 7-7" /></svg>{{ __($__feat) }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="relative z-10 text-[11px] text-paper-0/60 font-mono mt-6 text-right" data-fc="register.footer">{{ auth_cfg('register', 'footer', '2026 ' . $__brandName . ' / ' . app_default_country_name()) }}</div>
            </aside>

            <!-- RIGHT: form -->
            <main class="flex flex-col justify-center px-6 py-6 lg:px-10 overflow-y-auto">
                <div class="w-full max-w-[400px] mx-auto">
                    <a href="{{ url('/') }}" class="lg:hidden inline-flex items-center gap-2 mb-6">
                        <span class="w-8 h-8 rounded-md bg-wa-deep text-paper-0 grid place-items-center"><svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12c0 1.96.57 3.79 1.55 5.34L2 22l4.78-1.5A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Z" /></svg></span>
                        <span class="font-serif text-[22px] tracking-[-0.01em]">{{ $__brandName }}</span>
                    </a>

                    @include('auth._register_form')
                </div>
            </main>

        </div>

    </x-layouts.guest>
@endif
