@php
    $__brandName = (string) brand_name();
    $__brandLogo = \App\Support\Brand::logoUrl();
    $__authVariant = (int) \App\Models\SystemSetting::get('auth.variant', '1');
    if ($__authVariant < 1 || $__authVariant > 5) $__authVariant = 1;
@endphp

@if ($__authVariant !== 1)
    {{-- Variants 2–5: alternative designs (form-only, chrome from the shell). --}}
    <x-auth-shell page="login" :title="__('Sign in')">
        @include('auth._login_form')
    </x-auth-shell>
@else
    {{-- Variant 1 (default): the original split showcase — full content intact. --}}
    <x-layouts.guest :title="__('Sign in')" page="auth-login">

        <div class="grid lg:grid-cols-[1fr_540px] {{ fc_editing() ? 'min-h-screen' : 'h-screen overflow-hidden' }}">

            <!-- LEFT: visual showcase -->
            <aside class="auth-art relative hidden lg:flex flex-col p-10 text-paper-0 overflow-hidden" data-ae-media="login">
                <div class="blob bg-wa-green w-[300px] h-[300px] -top-12 -left-12"></div>
                <div class="blob bg-accent-amber w-[260px] h-[260px] bottom-12 right-12"></div>
                @php $__authMedia = auth_cfg('login', 'media_url', ''); $__authMediaType = auth_cfg('login', 'media_type', ''); @endphp
                @if ($__authMedia)
                    @if ($__authMediaType === 'video')
                        <video src="{{ asset($__authMedia) }}" autoplay muted loop playsinline class="absolute inset-0 w-full h-full object-cover"></video>
                    @else
                        <img src="{{ asset($__authMedia) }}" class="absolute inset-0 w-full h-full object-cover" alt="">
                    @endif
                    <div class="absolute inset-0 bg-ink-950/55"></div>
                @endif

                <div class="relative z-10 flex-1 flex flex-col justify-center w-full">

                    <!-- Top: hero card -->
                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-5 mb-4">
                        <div class="flex items-start gap-3">
                            <span class="w-10 h-10 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center shrink-0">
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 4h12v8H2zM5 4v8M11 4v8" /></svg>
                            </span>
                            <div>
                                <div class="text-[14px] font-semibold leading-tight" data-fc="login.hero_title">{{ auth_cfg('login', 'hero_title', __('Multi-workspace')) }}</div>
                                <div class="text-[12px] text-paper-0/75 leading-snug mt-1" data-fc="login.hero_desc">{{ auth_cfg('login', 'hero_desc', __('Run agencies, brands or clients side-by-side / each one fully isolated.')) }}</div>
                            </div>
                        </div>
                    </div>

                    @php $__accent = auth_cfg('login', 'accent', '#25D366'); @endphp
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-paper-0/70 mb-3" data-fc="login.eyebrow">{{ auth_cfg('login', 'eyebrow', __('Operator console for WhatsApp')) }}</div>
                    <h1 class="font-serif text-[42px] leading-[1.05] tracking-[-0.01em]"><span data-fc="login.heading">{{ auth_cfg('login', 'heading', __('One place for every')) }}</span>
                        <span class="italic" style="color: {{ $__accent }}" data-fc="login.heading_accent">{{ auth_cfg('login', 'heading_accent', __('conversation')) }}</span>.</h1>
                    <p class="mt-3 text-[13px] text-paper-0/85 leading-relaxed" data-fc="login.subheading">{{ auth_cfg('login', 'subheading', __('Broadcasts, flows, AI assist, shared inbox / all in one workspace your team will actually use.')) }}</p>

                    <!-- Stat pills -->
                    <div class="grid grid-cols-3 gap-3 mt-5">
                        <div class="stat-pill rounded-2xl p-4 text-center"><div class="font-serif text-[24px] leading-none" data-fc="login.stat1_num">{{ auth_cfg('login', 'stat1_num', '42M+') }}</div><div class="text-[10.5px] text-paper-0/70 mt-1" data-fc="login.stat1_label">{{ auth_cfg('login', 'stat1_label', __('messages sent')) }}</div></div>
                        <div class="stat-pill rounded-2xl p-4 text-center"><div class="font-serif text-[24px] leading-none" data-fc="login.stat2_num">{{ auth_cfg('login', 'stat2_num', '99.9%') }}</div><div class="text-[10.5px] text-paper-0/70 mt-1" data-fc="login.stat2_label">{{ auth_cfg('login', 'stat2_label', __('delivery rate')) }}</div></div>
                        <div class="stat-pill rounded-2xl p-4 text-center"><div class="font-serif text-[24px] leading-none" data-fc="login.stat3_num">{{ auth_cfg('login', 'stat3_num', '4.9 *') }}</div><div class="text-[10.5px] text-paper-0/70 mt-1" data-fc="login.stat3_label">{{ auth_cfg('login', 'stat3_label', __('G2 / Capterra')) }}</div></div>
                    </div>

                    <!-- Feature row -->
                    <div class="grid grid-cols-2 gap-3 mt-5">
                        <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                            <div class="flex items-center gap-2 mb-2"><span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 8h3l1.5-4 2 8 1.5-4h2" /></svg></span><div class="text-[12.5px] font-semibold" data-fc="login.feat1_title">{{ auth_cfg('login', 'feat1_title', __('Broadcasts')) }}</div></div>
                            <div class="text-[11px] text-paper-0/70 leading-snug" data-fc="login.feat1_desc">{{ auth_cfg('login', 'feat1_desc', __('Send to thousands at once with smart throttling and per-contact tracking.')) }}</div>
                        </div>
                        <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                            <div class="flex items-center gap-2 mb-2"><span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 3l8 5-8 5z" /></svg></span><div class="text-[12.5px] font-semibold" data-fc="login.feat2_title">{{ auth_cfg('login', 'feat2_title', __('Flow builder')) }}</div></div>
                            <div class="text-[11px] text-paper-0/70 leading-snug" data-fc="login.feat2_desc">{{ auth_cfg('login', 'feat2_desc', __('Trigger / branch / wait / AI assist. Drag-drop the whole conversation.')) }}</div>
                        </div>
                        <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                            <div class="flex items-center gap-2 mb-2"><span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-3Z" /></svg></span><div class="text-[12.5px] font-semibold" data-fc="login.feat3_title">{{ auth_cfg('login', 'feat3_title', __('Team inbox')) }}</div></div>
                            <div class="text-[11px] text-paper-0/70 leading-snug" data-fc="login.feat3_desc">{{ auth_cfg('login', 'feat3_desc', __('Live shared inbox with assignments, internal notes, AI suggestions.')) }}</div>
                        </div>
                        <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                            <div class="flex items-center gap-2 mb-2"><span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 5h8l1 6H4z" /></svg></span><div class="text-[12.5px] font-semibold" data-fc="login.feat4_title">{{ auth_cfg('login', 'feat4_title', __('Shopify + Woo')) }}</div></div>
                            <div class="text-[11px] text-paper-0/70 leading-snug" data-fc="login.feat4_desc">{{ auth_cfg('login', 'feat4_desc', __('Cart recovery, order updates, and catalog sync out of the box.')) }}</div>
                        </div>
                    </div>

                    <!-- What's also inside -->
                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4 mt-4">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/70 mb-2" data-fc="login.inside_heading">{{ auth_cfg('login', 'inside_heading', __("What's also inside")) }}</div>
                        <div class="grid grid-cols-3 gap-x-3 gap-y-1.5 text-[11.5px] text-paper-0/85">
                            @foreach (['Templates', 'Meta Ads / CTWA', 'AI assist', 'Webhooks', 'Auto-replies', 'Encrypted'] as $__i => $__feat)
                                <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-green shrink-0" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l3 3 7-7" /></svg><span data-fc="login.chip{{ $__i + 1 }}">{{ auth_cfg('login', 'chip' . ($__i + 1), __($__feat)) }}</span></span>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="relative z-10 text-[11px] text-paper-0/60 font-mono mt-6 text-right" data-fc="login.footer">{{ auth_cfg('login', 'footer', '2026 ' . $__brandName . ' / ' . app_default_country_name()) }}</div>
            </aside>

            <!-- RIGHT: form -->
            <main class="flex flex-col justify-center px-6 py-10 lg:px-14">
                <div class="w-full max-w-[400px] mx-auto">
                    <a href="{{ url('/dashboard') }}" class="lg:hidden inline-flex items-center gap-2 mb-8">
                        @if ($__brandLogo)
                            <img src="{{ $__brandLogo }}" alt="{{ $__brandName }}" class="h-9 w-auto max-w-[210px] object-contain">
                        @else
                            <span class="w-8 h-8 rounded-md bg-wa-deep text-paper-0 grid place-items-center"><svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12c0 1.96.57 3.79 1.55 5.34L2 22l4.78-1.5A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Z" /></svg></span>
                            <span class="font-serif text-[22px] tracking-[-0.01em]">{{ $__brandName }}</span>
                        @endif
                    </a>

                    @include('auth._login_form')
                </div>
            </main>

        </div>

    </x-layouts.guest>
@endif
