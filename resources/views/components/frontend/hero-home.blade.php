{{--
 Atmospheric hero — top of the homepage.
 Big serif headline, 4 ambient WhatsApp bubble accents floating at
 the four corners, kicker + value-prop on the right column, then the
 hero canvas (two-device-stage) is mounted by the page underneath.
--}}
<section class="relative overflow-hidden bg-paper-0" data-fc-section="hero-home">
    {{-- background atmosphere --}}
    <div class="absolute inset-0 grid-bg opacity-30 pointer-events-none"></div>
    <div class="absolute -top-32 -right-32 w-[520px] h-[520px] rounded-full bg-wa-mint/50 blur-bub"></div>
    <div class="absolute -bottom-40 -left-32 w-[460px] h-[460px] rounded-full bg-accent-amber/15 blur-bub"></div>

    {{-- 4 ambient bubble accents at the corners --}}
    <div class="absolute inset-0 z-0 pointer-events-none overflow-hidden">
        <div class="absolute top-[4%] left-[2%] opacity-30 rotate-[-8deg]">
            <div
                class="bg-wa-bubble hairline border-wa-green/30 rounded-2xl rounded-tr-sm px-4 py-2.5 shadow-[0_8px_24px_-8px_rgba(7,94,84,0.3)]">
                <div class="text-[12px] font-medium text-wa-deep">🌷 {{ __('Order shipped') }}</div>
                <div class="text-[9px] text-wa-deep/60 mono text-right">14:08 ✓✓</div>
            </div>
        </div>
        <div class="absolute top-[93%] left-[2%] opacity-25 rotate-[5deg]">
            <div
                class="bg-white hairline rounded-2xl rounded-tl-sm px-4 py-2.5 shadow-[0_8px_24px_-8px_rgba(7,94,84,0.3)]">
                <div class="text-[12px] font-medium text-ink-900">{{ __('Out for delivery') }}</div>
                <div class="text-[9px] text-ink-500 mono text-right">14:09</div>
            </div>
        </div>
        <div class="absolute top-[4%] right-[2%] opacity-30 rotate-[7deg]">
            <div
                class="bg-white hairline rounded-2xl rounded-tl-sm px-4 py-2.5 shadow-[0_8px_24px_-8px_rgba(7,94,84,0.3)]">
                <div class="text-[12px] font-medium text-ink-900">{{ __('Track order') }} →</div>
                <div class="text-[9px] text-ink-500 mono text-right">14:10</div>
            </div>
        </div>
        <div class="absolute top-[93%] right-[2%] opacity-25 rotate-[-6deg]">
            <div
                class="bg-wa-bubble hairline border-wa-green/30 rounded-2xl rounded-tr-sm px-4 py-2.5 shadow-[0_8px_24px_-8px_rgba(7,94,84,0.3)]">
                <div class="text-[12px] font-medium text-wa-deep">+10% {{ __('off coupon') }} 🎁</div>
                <div class="text-[9px] text-wa-deep/60 mono text-right">14:11 ✓✓</div>
            </div>
        </div>
    </div>

    <div class="relative max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-28">

        {{-- top kicker --}}
        <div class="flex items-center justify-between mb-10">
            <div
                class="inline-flex items-center gap-2 hairline rounded-full px-3 py-1.5 bg-white text-[11px] mono uppercase tracking-widest text-ink-700">
                <span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>
                <span>{{ config('app.version', 'v4.2') }} · <span
                        data-fc="hero-home.eyebrow">{{ fc('hero-home.eyebrow', __('AI Flow Generation now live')) }}</span></span>
            </div>
            <div class="hidden lg:flex items-center gap-6 text-[11px] mono uppercase tracking-widest text-ink-500">
                <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span><span
                        data-fc="hero-home.stat1_label">{{ fc('hero-home.stat1_label', '240M ' . __('messages / month')) }}</span></span>
                <span class="text-ink-400">/</span>
                <span data-fc="hero-home.stat2_label">{{ fc('hero-home.stat2_label', '4,218 ' . __('teams')) }}</span>
                <span class="text-ink-400">/</span>
                <span data-fc="hero-home.stat3_label">{{ fc('hero-home.stat3_label', '38 ' . __('markets')) }}</span>
            </div>
        </div>

        {{-- headline + supporting --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-end">
            <div class="col-span-12 lg:col-span-9 reveal">
                <h1 class="serif text-[44px] sm:text-[64px] lg:text-[112px] leading-[0.92] tracking-[-0.025em]"
                    data-fc="hero-home.headline">
                    {!! fc(
                        'hero-home.headline',
                        __('The complete WhatsApp') .
                            '<br>
                     ' .
                            __('platform') .
                            '<span class="text-wa-deep">.</span> ' .
                            __('Built for') .
                            '<br>
                     ' .
                            __('teams that') .
                            ' <span class="italic text-wa-deep">' .
                            __('ship.') .
                            '</span>',
                    ) !!}
                </h1>
            </div>

            <div class="col-span-12 lg:col-span-3 reveal" style="--d:120ms">
                <p class="text-[14px] text-ink-700 leading-relaxed border-l-2 border-wa-deep pl-4"
                    data-fc="hero-home.subhead">
                    {{ fc('hero-home.subhead', __('Broadcasts, flows, shared inbox, templates, AI, payments — twelve products under one roof. Pay for conversations, never per-seat. Live in four minutes.')) }}
                </p>
                <div class="mt-6 flex flex-col gap-2.5">
                    <a href="{{ fc('hero-home.cta1_url', Route::has('register') ? route('register') : url('/')) }}"
                        data-fc-url="hero-home.cta1_url"
                        class="w-full px-5 py-3 rounded-full bg-wa-green text-ink-900 text-[13.5px] font-semibold hover:bg-[#1ec05a] flex items-center justify-center gap-2 glow-green">
                        <span
                            data-fc="hero-home.cta1_label">{{ fc('hero-home.cta1_label', __('Start 14-day trial')) }}</span>
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M5 4l4 4-4 4" />
                        </svg>
                    </a>
                    <a href="{{ fc('hero-home.cta2_url', '#') }}"
                        data-fc-url="hero-home.cta2_url"
                        class="w-full px-5 py-3 hairline rounded-full bg-white text-[13.5px] font-medium hover:bg-paper-50 flex items-center justify-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-wa-deep text-paper-0 flex items-center justify-center">
                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="currentColor">
                                <polygon points="5,3 13,8 5,13" />
                            </svg>
                        </span>
                        <span
                            data-fc="hero-home.cta2_label">{{ fc('hero-home.cta2_label', __('Watch 90-sec tour')) }}</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- two-device stage mounts in the slot below --}}
        {{ $slot ?? '' }}
    </div>
</section>
