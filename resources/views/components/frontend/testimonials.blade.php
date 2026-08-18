{{-- Testimonials · big featured quote (Priya · Bloomly) + 2 stacked small
 quotes (G2 / Capterra) + 4-tile ratings strip. --}}
<section class="bg-paper-50 hairline-t hairline-b" data-fc-section="testimonials">
    <div class="max-w-[1360px] mx-auto px-4 sm:px-7 py-28">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
            <div class="col-span-12 lg:col-span-2">
                <div class="feature-num text-[80px] sm:text-[140px]">09</div>
            </div>
            <div class="col-span-12 lg:col-span-3 flex flex-col justify-end pb-3">
                <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">— <span
                        data-fc="testimonials.eyebrow_text">{{ fc('testimonials.eyebrow_text', __('Customers')) }}</span>
                </div>
                <div class="text-[13px] font-semibold" data-fc="testimonials.label">
                    {{ fc('testimonials.label', __('Stories & reviews')) }}</div>
            </div>
            <div class="col-span-12 lg:col-span-7 flex flex-wrap items-end lg:justify-end pb-3 gap-3 text-[11px] mono text-ink-500">
                <span data-fc="testimonials.meta1">{{ fc('testimonials.meta1', 'G2 · 4.9') }}</span><span
                    class="text-ink-400">·</span>
                <span data-fc="testimonials.meta2">{{ fc('testimonials.meta2', 'Capterra · 4.8') }}</span><span
                    class="text-ink-400">·</span>
                <span class="text-wa-deep"
                    data-fc="testimonials.meta3">{{ fc('testimonials.meta3', __('net retention 142%')) }}</span>
            </div>
        </div>

        <h2 class="serif text-[44px] sm:text-[64px] lg:text-[88px] leading-[0.92] tracking-[-0.02em] mb-12 reveal" data-fc="testimonials.headline">
            {!! fc(
                'testimonials.headline',
                __('Teams that') .
                    ' <span class="italic text-wa-deep">' .
                    __('replaced four tools') .
                    '</span><br>
             ' .
                    __('with :brand.', ['brand' => brand_name()]),
            ) !!}
        </h2>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 reveal" style="--d:120ms">

            {{-- featured big quote --}}
            <div class="col-span-12 lg:col-span-7 hairline rounded-3xl bg-white p-9 relative">
                <svg viewBox="0 0 32 24" class="w-12 h-9 text-wa-deep mb-5" fill="currentColor">
                    <path
                        d="M6 0C2.7 0 0 2.7 0 6v6c0 3.3 2.7 6 6 6 1 0 1.5-.5 1.5-1.5S7 15 6 15c-1.7 0-3-1.3-3-3h3c1.7 0 3-1.3 3-3V6c0-3.3-2.7-6-3-6zm20 0c-3.3 0-6 2.7-6 6v6c0 3.3 2.7 6 6 6 1 0 1.5-.5 1.5-1.5S27 15 26 15c-1.7 0-3-1.3-3-3h3c1.7 0 3-1.3 3-3V6c0-3.3-2.7-6-3-6z" />
                </svg>
                <p class="serif text-[26px] sm:text-[36px] leading-[1.18]" data-fc="testimonials.feat_quote">
                    {!! fc(
                        'testimonials.feat_quote',
                        __('We replaced AiSensy, Klaviyo, Freshdesk, and a Zapier mess. Read rates jumped from 19% on email to') .
                            ' <span class="italic text-wa-deep">' .
                            __('86% on WhatsApp') .
                            '</span> — ' .
                            __('and agents reply 3× faster. CFO is happy.'),
                    ) !!}
                </p>
                <div class="mt-8 flex items-center gap-4 hairline-t pt-5">
                    <span
                        class="w-14 h-14 rounded-full bg-gradient-to-br from-accent-coral to-accent-amber flex items-center justify-center text-paper-0 font-semibold"
                        data-fc="testimonials.feat_initials">{{ fc('testimonials.feat_initials', 'PR') }}</span>
                    <div class="flex-1 min-w-0">
                        <div class="text-[14px] font-semibold" data-fc="testimonials.feat_author">
                            {{ fc('testimonials.feat_author', 'Priya Ramaswamy') }}</div>
                        <div class="text-[12px] text-ink-500" data-fc="testimonials.feat_role">
                            {{ fc('testimonials.feat_role', __('Head of CX, Bloomly Flowers · Mumbai')) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="serif text-[24px] text-wa-deep" data-fc="testimonials.feat_stat">
                            {{ fc('testimonials.feat_stat', '+38%') }}</div>
                        <div class="mono text-[10px] text-ink-500" data-fc="testimonials.feat_stat_label">
                            {{ fc('testimonials.feat_stat_label', __('repeat orders')) }}</div>
                    </div>
                </div>
            </div>

            {{-- two stacked small quotes --}}
            <div class="col-span-12 lg:col-span-5 grid grid-rows-2 gap-5">

                <div class="hairline rounded-3xl bg-white p-6">
                    <div class="mono text-[10px] uppercase tracking-widest text-wa-deep mb-2"
                        data-fc="testimonials.quote1_rating">{{ fc('testimonials.quote1_rating', '★★★★★ · G2') }}</div>
                    <p class="text-[15px] leading-snug" data-fc="testimonials.quote1_text">
                        "{{ fc('testimonials.quote1_text', __('AI Flow Generation is unreasonably good. Described our cart-abandon flow in one sentence — got a 14-node graph in 2 seconds. Saved a week.')) }}"
                    </p>
                    <div class="mt-4 flex items-center gap-3">
                        <span
                            class="w-10 h-10 rounded-full bg-gradient-to-br from-wa-deep to-wa-teal text-paper-0 text-[12px] font-semibold flex items-center justify-center"
                            data-fc="testimonials.quote1_initials">{{ fc('testimonials.quote1_initials', 'DK') }}</span>
                        <div>
                            <div class="text-[13px] font-semibold" data-fc="testimonials.quote1_author">
                                {{ fc('testimonials.quote1_author', 'Dario Kowalski') }}</div>
                            <div class="text-[11px] text-ink-500" data-fc="testimonials.quote1_role">
                                {{ fc('testimonials.quote1_role', 'CTO, Ridgewell · Berlin') }}</div>
                        </div>
                    </div>
                </div>

                <div class="hairline rounded-3xl bg-ink-950 text-paper-0 p-6 relative overflow-hidden">
                    <div class="absolute inset-0 dot-pattern opacity-15"></div>
                    <div class="relative">
                        <div class="mono text-[10px] uppercase tracking-widest text-wa-green mb-2"
                            data-fc="testimonials.quote2_rating">
                            {{ fc('testimonials.quote2_rating', '★★★★★ · Capterra') }}</div>
                        <p class="text-[15px] leading-snug" data-fc="testimonials.quote2_text">
                            "{{ fc('testimonials.quote2_text', __('Migrated from Wati in 3 days. White-glove team imported 11k contacts, 38 templates, 22 flows — zero downtime.')) }}"
                        </p>
                        <div class="mt-4 flex items-center gap-3">
                            <span
                                class="w-10 h-10 rounded-full bg-gradient-to-br from-accent-amber to-wa-green text-ink-900 text-[12px] font-semibold flex items-center justify-center"
                                data-fc="testimonials.quote2_initials">{{ fc('testimonials.quote2_initials', 'LO') }}</span>
                            <div>
                                <div class="text-[13px] font-semibold" data-fc="testimonials.quote2_author">
                                    {{ fc('testimonials.quote2_author', 'Lina Okafor') }}</div>
                                <div class="text-[11px] text-paper-0/60" data-fc="testimonials.quote2_role">
                                    {{ fc('testimonials.quote2_role', 'Ops Lead, Marigold · Lagos') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4-tile ratings strip --}}
            <div class="col-span-12 grid grid-cols-2 lg:grid-cols-4 gap-4 mt-2">
                @foreach ([[fc('testimonials.tile1_label', __('G2 rating')), fc('testimonials.tile1_val', '4.9'), fc('testimonials.tile1_suffix', '/5'), fc('testimonials.tile1_sub', '3,128 ' . __('reviews'))], [fc('testimonials.tile2_label', 'Capterra'), fc('testimonials.tile2_val', '4.8'), fc('testimonials.tile2_suffix', '/5'), fc('testimonials.tile2_sub', '1,090 ' . __('reviews'))], [fc('testimonials.tile3_label', 'CSAT'), fc('testimonials.tile3_val', '96'), fc('testimonials.tile3_suffix', '%'), fc('testimonials.tile3_sub', '12,400 ' . __('ratings'))], [fc('testimonials.tile4_label', __('Net retention')), fc('testimonials.tile4_val', '142'), fc('testimonials.tile4_suffix', '%'), fc('testimonials.tile4_sub', '12 ' . __('months'))]] as [$label, $val, $suffix, $sub])
                    <div class="hairline rounded-2xl bg-white p-5">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ $label }}</div>
                        <div class="serif text-[40px] leading-none mt-3">{{ $val }}<span
                                class="text-[20px] text-ink-500">{{ $suffix }}</span></div>
                        <div class="mono text-[10px] text-ink-500 mt-1">{{ $sub }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
