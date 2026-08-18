{{-- Feature 01 · Broadcasts — full inline section ported from the prototype.
 Editorial header bar (num + label + chip row), 88px serif headline,
 LEFT: campaign mock + per-timezone + sender-rating mini cards,
 RIGHT: 6-item "inside this feature" list with footer rail. --}}
<section class="bg-paper-50 hairline-t hairline-b" data-fc-section="feature-broadcasts">
    <div class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-28">

        {{-- header bar --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
            <div class="col-span-12 lg:col-span-2">
                <div class="feature-num text-[88px] sm:text-[120px] lg:text-[140px]">01</div>
            </div>
            <div class="col-span-12 lg:col-span-3 flex flex-col justify-end pb-3">
                <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1"
                    data-fc="feature-broadcasts.eyebrow">{{ fc('feature-broadcasts.eyebrow', __('Feature one')) }}</div>
                <div class="text-[13px] font-semibold" data-fc="feature-broadcasts.label">
                    {{ fc('feature-broadcasts.label', __('Broadcasts & campaigns')) }}</div>
            </div>
            <div class="col-span-12 lg:col-span-7 flex items-end justify-start lg:justify-end pb-3 gap-3 text-[11px] mono text-ink-500 flex-wrap">
                <span
                    data-fc="feature-broadcasts.meta1">{{ fc('feature-broadcasts.meta1', '48,210 ' . __('recipients')) }}</span><span
                    class="text-ink-400">·</span>
                <span
                    data-fc="feature-broadcasts.meta2">{{ fc('feature-broadcasts.meta2', __('A/B variants')) }}</span><span
                    class="text-ink-400">·</span>
                <span
                    data-fc="feature-broadcasts.meta3">{{ fc('feature-broadcasts.meta3', __('per-timezone')) }}</span><span
                    class="text-ink-400">·</span>
                <span class="text-wa-deep"
                    data-fc="feature-broadcasts.meta4">{{ fc('feature-broadcasts.meta4', __('auto throttle')) }}</span>
            </div>
        </div>

        <h2 class="serif text-[44px] sm:text-[64px] lg:text-[88px] leading-[0.92] tracking-[-0.02em] mb-3 reveal"
            data-fc="feature-broadcasts.headline">
            {!! fc(
                'feature-broadcasts.headline',
                __('Send to') .
                    ' <span class="italic text-wa-deep">48,210</span> ' .
                    __('people') .
                    '<br>' .
                    __('without burning your sender rating.'),
            ) !!}
        </h2>
        <p class="text-[15.5px] text-ink-700 max-w-2xl leading-relaxed reveal" style="--d:120ms"
            data-fc="feature-broadcasts.body">
            {{ fc('feature-broadcasts.body', __('Audience builder with 30+ filters, A/B test variants, recurring sends, throttle-by-quality-rating — and a per-recipient timezone scheduler so your 9am promo lands at 9am, everywhere.')) }}
        </p>

        <div class="mt-14 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            {{-- LEFT: product preview + mini cards --}}
            <div class="col-span-12 lg:col-span-7 reveal">

                {{-- main campaign mock --}}
                <div class="hairline rounded-3xl bg-white p-6 shadow-[0_24px_60px_-30px_rgba(7,94,84,0.25)]">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <span
                                class="w-10 h-10 rounded-xl bg-gradient-to-br from-accent-coral to-accent-amber flex items-center justify-center text-paper-0 text-[11px] font-bold">SP</span>
                            <div>
                                <div class="text-[14px] font-semibold">Spring Promo · Tier-2 EU</div>
                                <div class="mono text-[10px] text-ink-500">spring_promo_v3 · EN/ES/FR · A/B · 2
                                    {{ __('variants') }}</div>
                            </div>
                        </div>
                        <span class="pill bg-wa-green/15 text-wa-deep"><span
                                class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>{{ __('Sending') }}</span>
                    </div>

                    <div class="flex items-center gap-2 mb-4">
                        <div class="flex-1 h-2.5 rounded-full bg-paper-100 overflow-hidden flex">
                            <div class="h-full bg-wa-deep" style="width:55%"></div>
                            <div class="h-full bg-wa-green" style="width:33%"></div>
                        </div>
                        <span class="mono text-[11px] tabular font-semibold">88%</span>
                    </div>

                    <div class="mb-4 flex flex-wrap gap-1.5">
                        <span class="hairline rounded-md bg-paper-50 px-2 py-1 text-[10.5px] mono"><span
                                class="text-ink-500">tag:</span> vip</span>
                        <span class="hairline rounded-md bg-paper-50 px-2 py-1 text-[10.5px] mono"><span
                                class="text-ink-500">geo:</span> EU</span>
                        <span class="hairline rounded-md bg-paper-50 px-2 py-1 text-[10.5px] mono"><span
                                class="text-ink-500">spend &gt;</span> €120</span>
                        <span class="hairline rounded-md bg-paper-50 px-2 py-1 text-[10.5px] mono"><span
                                class="text-ink-500">last_order &lt;</span> 60d</span>
                        <span class="hairline rounded-md bg-paper-50 px-2 py-1 text-[10.5px] mono"><span
                                class="text-ink-500">opted_in:</span> true</span>
                    </div>

                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-2.5 text-center">
                        <div class="hairline rounded-xl bg-paper-50 py-3">
                            <div class="mono text-[9px] text-ink-500">{{ __('SENT') }}</div>
                            <div class="serif text-[24px] mt-0.5">42.1k</div>
                        </div>
                        <div class="hairline rounded-xl bg-paper-50 py-3">
                            <div class="mono text-[9px] text-ink-500">{{ __('DELIV') }}</div>
                            <div class="serif text-[24px] mt-0.5">98.4%</div>
                        </div>
                        <div class="hairline border-wa-green/40 rounded-xl bg-wa-bubble py-3">
                            <div class="mono text-[9px] text-wa-deep">{{ __('READ') }}</div>
                            <div class="serif text-[24px] mt-0.5 text-wa-deep">86%</div>
                        </div>
                        <div class="hairline rounded-xl bg-paper-50 py-3">
                            <div class="mono text-[9px] text-ink-500">{{ __('CTR') }}</div>
                            <div class="serif text-[24px] mt-0.5">11.4%</div>
                        </div>
                        <div class="hairline rounded-xl bg-paper-50 py-3">
                            <div class="mono text-[9px] text-ink-500">{{ __('REV') }}</div>
                            <div class="serif text-[24px] mt-0.5">$84k</div>
                        </div>
                    </div>

                    <div class="mt-5 hairline-t pt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="hairline rounded-xl bg-paper-50 p-3">
                            <div class="flex items-center justify-between mb-1">
                                <span
                                    class="text-[11.5px] font-semibold">{{ __('Variant A · "🌸 Mother\'s Day"') }}</span>
                                <span class="pill bg-wa-deep text-paper-0 text-[10px]">{{ __('Winner') }}</span>
                            </div>
                            <div class="text-[10.5px] mono text-ink-500">CTR 13.2% · CVR 4.1%</div>
                            <div class="mt-2 h-1.5 rounded-full bg-paper-100 overflow-hidden">
                                <div class="h-full bg-wa-deep" style="width:78%"></div>
                            </div>
                        </div>
                        <div class="hairline rounded-xl bg-white p-3">
                            <div class="flex items-center justify-between mb-1">
                                <span
                                    class="text-[11.5px] font-semibold">{{ __('Variant B · "Mom deserves it"') }}</span>
                                <span class="pill bg-paper-100 text-ink-500 text-[10px]">{{ __('Lost') }}</span>
                            </div>
                            <div class="text-[10.5px] mono text-ink-500">CTR 9.6% · CVR 2.8%</div>
                            <div class="mt-2 h-1.5 rounded-full bg-paper-100 overflow-hidden">
                                <div class="h-full bg-ink-400" style="width:52%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- mini cards: per-timezone + sender rating --}}
                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="hairline rounded-3xl bg-white p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="mono text-[10px] uppercase tracking-widest text-ink-500">
                                {{ __('Per-timezone send') }}</div>
                            <span class="pill bg-wa-bubble text-wa-deep text-[10px]">{{ __('live') }}</span>
                        </div>
                        <div class="serif text-[28px] leading-none mb-3">9<span class="text-ink-400">:</span>00<span
                                class="text-[14px] text-ink-500 ml-1">am · {{ __('local') }}</span></div>
                        <ul class="space-y-2 text-[11.5px]">
                            <li class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                        class="w-1.5 h-1.5 rounded-full bg-wa-deep"></span>Madrid · ES</span><span
                                    class="mono text-ink-500">09:00 · {{ __('sent') }} 12.4k</span></li>
                            <li class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Berlin · DE</span><span
                                    class="mono text-ink-500">09:00 · {{ __('sent') }} 8.9k</span></li>
                            <li class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                        class="w-1.5 h-1.5 rounded-full bg-accent-amber"></span>Lisbon · PT</span><span
                                    class="mono text-ink-500">09:00 · {{ __('queued') }}</span></li>
                            <li class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                        class="w-1.5 h-1.5 rounded-full bg-ink-400"></span>Athens · GR</span><span
                                    class="mono text-ink-500">+2h · {{ __('queued') }}</span></li>
                        </ul>
                        <div class="hairline-t mt-4 pt-3 text-[11px] text-ink-600">
                            {{ __('Quiet-hours respected per market.') }}</div>
                    </div>

                    <div class="hairline rounded-3xl bg-white p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="mono text-[10px] uppercase tracking-widest text-ink-500">
                                {{ __('Sender rating · 7d') }}</div>
                            <span class="pill bg-wa-green/15 text-wa-deep text-[10px]"><span
                                    class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>{{ __('healthy') }}</span>
                        </div>
                        <div class="serif text-[28px] leading-none mb-3">{{ __('High') }} <span
                                class="text-[14px] text-ink-500 ml-1">· {{ __('no throttle') }}</span></div>
                        <div class="flex items-end gap-1.5 h-16 mb-3">
                            <div class="flex-1 bg-wa-deep/30 rounded-sm" style="height:62%"></div>
                            <div class="flex-1 bg-wa-deep/40 rounded-sm" style="height:70%"></div>
                            <div class="flex-1 bg-wa-deep/50 rounded-sm" style="height:55%"></div>
                            <div class="flex-1 bg-wa-deep/60 rounded-sm" style="height:78%"></div>
                            <div class="flex-1 bg-wa-deep/70 rounded-sm" style="height:84%"></div>
                            <div class="flex-1 bg-wa-deep/80 rounded-sm" style="height:92%"></div>
                            <div class="flex-1 bg-wa-deep rounded-sm" style="height:96%"></div>
                        </div>
                        <ul class="space-y-1.5 text-[11.5px]">
                            <li class="flex items-center justify-between"><span
                                    class="text-ink-700">{{ __('Block rate') }}</span><span
                                    class="mono text-wa-deep">0.04%</span></li>
                            <li class="flex items-center justify-between"><span
                                    class="text-ink-700">{{ __('Read rate') }}</span><span
                                    class="mono text-wa-deep">86.2%</span></li>
                            <li class="flex items-center justify-between"><span
                                    class="text-ink-700">{{ __('Auto-pause') }}</span><span
                                    class="mono text-ink-500">{{ __('armed') }}</span></li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- RIGHT: 6-item capability list --}}
            <div class="col-span-12 lg:col-span-5 reveal" style="--d:200ms">
                <div class="space-y-0 hairline rounded-3xl bg-white overflow-hidden">
                    <div class="px-6 py-4 hairline-b mono text-[10.5px] uppercase tracking-widest text-ink-500 bg-paper-50"
                        data-fc="feature-broadcasts.rail_title">
                        {{ fc('feature-broadcasts.rail_title', __('Inside this feature')) }}</div>
                    <ul class="divide-y divide-paper-200">
                        @foreach ([
        ['01', fc('feature-broadcasts.rail1_title', __('Audience builder · 30+ filters')), fc('feature-broadcasts.rail1_desc', __('Stack tags, geos, order history, custom attributes, opt-in source. Saved segments refresh in real-time.'))],
        ['02', fc('feature-broadcasts.rail2_title', __('A/B variants · auto-winner')), fc('feature-broadcasts.rail2_desc', __('Test up to 4 variants. :brand promotes the winner once statistical significance is hit.', ['brand' => brand_name()]))],
        ['03', fc('feature-broadcasts.rail3_title', __('Per-recipient timezones')), fc('feature-broadcasts.rail3_desc', __('9am promo lands at 9am everywhere. Quiet-hours respected per market & per region.'))],
        ['04', fc('feature-broadcasts.rail4_title', __('Quality-rating protection')), fc('feature-broadcasts.rail4_desc', __('Automatic throttle if Meta downgrades you. We pause, retry, and re-warm in the background.'))],
        ['05', fc('feature-broadcasts.rail5_title', __('Click-tracked URLs · UTM in/out')), fc('feature-broadcasts.rail5_desc', __('Every link shortened, signed, and attributable. UTMs auto-passed to Stripe & GA4.'))],
        ['06', fc('feature-broadcasts.rail6_title', __('Recurring & trigger-based')), fc('feature-broadcasts.rail6_desc', __('Weekly newsletters, monthly digests, or fire-on-event from a Stripe webhook.'))],
    ] as [$num, $title, $desc])
                            <li class="px-6 py-4 grid grid-cols-[28px_1fr] gap-3 items-start">
                                <span
                                    class="serif text-[20px] text-wa-deep leading-none mt-0.5">{{ $num }}</span>
                                <div>
                                    <div class="text-[14px] font-semibold mb-1">{{ $title }}</div>
                                    <p class="text-[12.5px] text-ink-600 leading-relaxed">{{ $desc }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="px-6 py-4 bg-paper-50 flex items-center justify-between">
                        <span class="text-[12px] text-ink-700"
                            data-fc="feature-broadcasts.footer_note">{!! fc(
                                'feature-broadcasts.footer_note',
                                __('Used by') . ' <b>3,128 ' . __('teams') . '</b> · 240M ' . __('messages/mo'),
                            ) !!}</span>
                        <a href="{{ fc('feature-broadcasts.cta_url', '#') }}"
                            class="text-[12.5px] text-wa-deep font-semibold"
                            data-fc="feature-broadcasts.cta_label">{{ fc('feature-broadcasts.cta_label', __('Explore →')) }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
