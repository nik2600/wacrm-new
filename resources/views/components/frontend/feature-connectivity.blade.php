{{-- Feature 05 · Three channels / one inbox.
 Three side-by-side cards (Cloud API / Twilio / Baileys) — each with
 a brand-colored 14px tile, channel number, headline, description,
 and a 4-row "spec sheet" rail at the bottom. --}}
<section class="bg-paper-50 hairline-t hairline-b" data-fc-section="feature-connectivity">
    <div class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-28">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
            <div class="lg:col-span-2">
                <div class="feature-num text-[80px] sm:text-[110px] lg:text-[140px]">05</div>
            </div>
            <div class="lg:col-span-3 flex flex-col justify-end pb-3">
                <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1"
                    data-fc="feature-connectivity.eyebrow">{{ fc('feature-connectivity.eyebrow', __('Feature five')) }}
                </div>
                <div class="text-[13px] font-semibold" data-fc="feature-connectivity.label">
                    {{ fc('feature-connectivity.label', __('Connectivity · 3 channels')) }}</div>
            </div>
            <div class="lg:col-span-7 flex flex-wrap items-end lg:justify-end pb-3 gap-3 text-[11px] mono text-ink-500">
                <span data-fc="feature-connectivity.meta1">{{ fc('feature-connectivity.meta1', 'WABA') }}</span><span
                    class="text-ink-400">·</span>
                <span data-fc="feature-connectivity.meta2">{{ fc('feature-connectivity.meta2', 'Twilio') }}</span><span
                    class="text-ink-400">·</span>
                <span
                    data-fc="feature-connectivity.meta3">{{ fc('feature-connectivity.meta3', 'Unofficial API') }}</span><span
                    class="text-ink-400">·</span>
                <span class="text-wa-deep"
                    data-fc="feature-connectivity.meta4">{{ fc('feature-connectivity.meta4', __('one inbox')) }}</span>
            </div>
        </div>

        <h2 class="serif text-[44px] sm:text-[64px] lg:text-[88px] leading-[0.92] tracking-[-0.02em] mb-3 reveal"
            data-fc="feature-connectivity.headline">
            {!! fc(
                'feature-connectivity.headline',
                __('Three ways to connect.') .
                    '<br><span class="italic text-wa-deep">' .
                    __('One') .
                    '</span> ' .
                    __('inbox at the end.'),
            ) !!}
        </h2>
        <p class="text-[15.5px] text-ink-700 max-w-2xl leading-relaxed reveal" style="--d:120ms"
            data-fc="feature-connectivity.body">
            {{ fc('feature-connectivity.body', __("Plug in WhatsApp Cloud API for official scale, Twilio if that's where your stack lives, or scan a QR with the Unofficial API to test in two minutes. Same flows, same templates, same agents.")) }}
        </p>

        <div class="mt-14 grid grid-cols-1 lg:grid-cols-3 gap-5 reveal" style="--d:160ms">

            {{-- WABA · Cloud API --}}
            <div class="hairline rounded-3xl bg-white p-7 relative overflow-hidden hover:border-wa-deep transition">
                <div class="absolute top-5 right-5"><span
                        class="pill bg-wa-bubble text-wa-deep border border-wa-green/40"
                        data-fc="feature-connectivity.card1_badge">{{ fc('feature-connectivity.card1_badge', __('Recommended')) }}</span>
                </div>
                <span
                    class="w-14 h-14 rounded-2xl bg-wa-deep text-paper-0 flex items-center justify-center font-bold text-[18px]">WA</span>
                <div class="mt-5">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500"
                        data-fc="feature-connectivity.card1_channel">
                        {{ fc('feature-connectivity.card1_channel', __('channel · 01')) }}</div>
                    <div class="serif text-[36px] leading-none mt-1" data-fc="feature-connectivity.card1_name">
                        {{ fc('feature-connectivity.card1_name', 'Cloud API') }}</div>
                </div>
                <h3 class="text-[15px] font-semibold mt-4" data-fc="feature-connectivity.card1_headline">
                    {{ fc('feature-connectivity.card1_headline', __('WhatsApp Business API, via Meta directly.')) }}
                </h3>
                <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="feature-connectivity.card1_desc">
                    {{ fc('feature-connectivity.card1_desc', __('Embedded Facebook signup, automatic phone verification, business detail fetch, one-tap disconnect. We can provision the WABA for you.')) }}
                </p>
                <ul class="mt-5 space-y-1.5 hairline-t pt-4">
                    <li class="flex items-center justify-between text-[12px]"><span class="text-ink-700"
                            data-fc="feature-connectivity.card1_spec1_label">{{ fc('feature-connectivity.card1_spec1_label', __('Setup time')) }}</span><span
                            class="mono text-[10.5px] text-wa-deep font-semibold"
                            data-fc="feature-connectivity.card1_spec1_value">{{ fc('feature-connectivity.card1_spec1_value', '~4 min') }}</span>
                    </li>
                    <li class="flex items-center justify-between text-[12px]"><span class="text-ink-700"
                            data-fc="feature-connectivity.card1_spec2_label">{{ fc('feature-connectivity.card1_spec2_label', __('Rate tier')) }}</span><span
                            class="mono text-[10.5px] text-wa-deep font-semibold"
                            data-fc="feature-connectivity.card1_spec2_value">{{ fc('feature-connectivity.card1_spec2_value', __('Unlimited')) }}</span>
                    </li>
                    <li class="flex items-center justify-between text-[12px]"><span class="text-ink-700"
                            data-fc="feature-connectivity.card1_spec3_label">{{ fc('feature-connectivity.card1_spec3_label', __('Green tick')) }}</span><span
                            class="mono text-[10.5px] text-wa-deep font-semibold"
                            data-fc="feature-connectivity.card1_spec3_value">{{ fc('feature-connectivity.card1_spec3_value', __('Eligible')) }}</span>
                    </li>
                    <li class="flex items-center justify-between text-[12px]"><span class="text-ink-700"
                            data-fc="feature-connectivity.card1_spec4_label">{{ fc('feature-connectivity.card1_spec4_label', __('Best for')) }}</span><span
                            class="mono text-[10.5px] text-ink-500"
                            data-fc="feature-connectivity.card1_spec4_value">{{ fc('feature-connectivity.card1_spec4_value', __('production · scale')) }}</span>
                    </li>
                </ul>
            </div>

            {{-- Twilio --}}
            <div class="hairline rounded-3xl bg-white p-7 relative overflow-hidden hover:border-wa-deep transition">
                <span
                    class="w-14 h-14 rounded-2xl bg-[#F22F46] text-paper-0 flex items-center justify-center font-bold text-[18px]">Tw</span>
                <div class="mt-5">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500"
                        data-fc="feature-connectivity.card2_channel">
                        {{ fc('feature-connectivity.card2_channel', __('channel · 02')) }}</div>
                    <div class="serif text-[36px] leading-none mt-1" data-fc="feature-connectivity.card2_name">
                        {{ fc('feature-connectivity.card2_name', 'Twilio') }}</div>
                </div>
                <h3 class="text-[15px] font-semibold mt-4" data-fc="feature-connectivity.card2_headline">
                    {{ fc('feature-connectivity.card2_headline', __('Plug your Twilio credentials in.')) }}</h3>
                <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="feature-connectivity.card2_desc">
                    {{ fc('feature-connectivity.card2_desc', __('Drop in your account SID + auth token. Sandbox for testing, Studio Senders for production. Everything routes through your existing Twilio account.')) }}
                </p>
                <ul class="mt-5 space-y-1.5 hairline-t pt-4">
                    <li class="flex items-center justify-between text-[12px]"><span class="text-ink-700"
                            data-fc="feature-connectivity.card2_spec1_label">{{ fc('feature-connectivity.card2_spec1_label', __('Setup time')) }}</span><span
                            class="mono text-[10.5px] text-wa-deep font-semibold"
                            data-fc="feature-connectivity.card2_spec1_value">{{ fc('feature-connectivity.card2_spec1_value', '~2 min') }}</span>
                    </li>
                    <li class="flex items-center justify-between text-[12px]"><span class="text-ink-700"
                            data-fc="feature-connectivity.card2_spec2_label">{{ fc('feature-connectivity.card2_spec2_label', __('Modes')) }}</span><span
                            class="mono text-[10.5px] text-wa-deep font-semibold"
                            data-fc="feature-connectivity.card2_spec2_value">{{ fc('feature-connectivity.card2_spec2_value', __('Sandbox + Prod')) }}</span>
                    </li>
                    <li class="flex items-center justify-between text-[12px]"><span class="text-ink-700"
                            data-fc="feature-connectivity.card2_spec3_label">{{ fc('feature-connectivity.card2_spec3_label', __('Templates')) }}</span><span
                            class="mono text-[10.5px] text-wa-deep font-semibold"
                            data-fc="feature-connectivity.card2_spec3_value">{{ fc('feature-connectivity.card2_spec3_value', __('Auto-sync')) }}</span>
                    </li>
                    <li class="flex items-center justify-between text-[12px]"><span class="text-ink-700"
                            data-fc="feature-connectivity.card2_spec4_label">{{ fc('feature-connectivity.card2_spec4_label', __('Best for')) }}</span><span
                            class="mono text-[10.5px] text-ink-500"
                            data-fc="feature-connectivity.card2_spec4_value">{{ fc('feature-connectivity.card2_spec4_value', __('Twilio-first stacks')) }}</span>
                    </li>
                </ul>
            </div>

            {{-- Baileys QR --}}
            <div class="hairline rounded-3xl bg-white p-7 relative overflow-hidden hover:border-wa-deep transition">
                <div class="absolute top-5 right-5"><span class="pill bg-paper-50 text-ink-700 hairline"
                        data-fc="feature-connectivity.card3_badge">{{ fc('feature-connectivity.card3_badge', __('No approval')) }}</span>
                </div>
                <span
                    class="w-14 h-14 rounded-2xl bg-ink-950 text-paper-0 flex items-center justify-center font-bold text-[16px]">QR</span>
                <div class="mt-5">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500"
                        data-fc="feature-connectivity.card3_channel">
                        {{ fc('feature-connectivity.card3_channel', __('channel · 03')) }}</div>
                    <div class="serif text-[36px] leading-none mt-1" data-fc="feature-connectivity.card3_name">
                        {{ fc('feature-connectivity.card3_name', 'Unofficial API') }}</div>
                </div>
                <h3 class="text-[15px] font-semibold mt-4" data-fc="feature-connectivity.card3_headline">
                    {!! fc(
                        'feature-connectivity.card3_headline',
                        __('Scan a QR.') . ' <span class="italic text-wa-deep">' . __("That's it.") . '</span>',
                    ) !!}</h3>
                <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="feature-connectivity.card3_desc">
                    {{ fc('feature-connectivity.card3_desc', __('Pair any WhatsApp number with no Meta approval. Real-time status monitoring, auto-reconnect, ban detection. Sandbox flows before going official.')) }}
                </p>
                <ul class="mt-5 space-y-1.5 hairline-t pt-4">
                    <li class="flex items-center justify-between text-[12px]"><span class="text-ink-700"
                            data-fc="feature-connectivity.card3_spec1_label">{{ fc('feature-connectivity.card3_spec1_label', __('Setup time')) }}</span><span
                            class="mono text-[10.5px] text-wa-deep font-semibold"
                            data-fc="feature-connectivity.card3_spec1_value">{{ fc('feature-connectivity.card3_spec1_value', '~30 sec') }}</span>
                    </li>
                    <li class="flex items-center justify-between text-[12px]"><span class="text-ink-700"
                            data-fc="feature-connectivity.card3_spec2_label">{{ fc('feature-connectivity.card3_spec2_label', __('Verification')) }}</span><span
                            class="mono text-[10.5px] text-wa-deep font-semibold"
                            data-fc="feature-connectivity.card3_spec2_value">{{ fc('feature-connectivity.card3_spec2_value', __('None needed')) }}</span>
                    </li>
                    <li class="flex items-center justify-between text-[12px]"><span class="text-ink-700"
                            data-fc="feature-connectivity.card3_spec3_label">{{ fc('feature-connectivity.card3_spec3_label', __('Auto-reconnect')) }}</span><span
                            class="mono text-[10.5px] text-wa-deep font-semibold"
                            data-fc="feature-connectivity.card3_spec3_value">{{ fc('feature-connectivity.card3_spec3_value', __('Yes')) }}</span>
                    </li>
                    <li class="flex items-center justify-between text-[12px]"><span class="text-ink-700"
                            data-fc="feature-connectivity.card3_spec4_label">{{ fc('feature-connectivity.card3_spec4_label', __('Best for')) }}</span><span
                            class="mono text-[10.5px] text-ink-500"
                            data-fc="feature-connectivity.card3_spec4_value">{{ fc('feature-connectivity.card3_spec4_value', __('test · solo · MVP')) }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>
