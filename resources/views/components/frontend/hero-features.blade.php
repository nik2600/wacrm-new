{{--
 Features page hero — big editorial headline, chip row of the 18
 feature areas, right column with intro + 3-stat band + "jump to"
 anchor card that scrolls to the matching bento card.
--}}
<section data-fc-section="hero-features" class="relative overflow-hidden bg-paper-0">
    <div class="absolute inset-0 grid-bg opacity-30 pointer-events-none"></div>
    <div class="absolute -top-32 -right-32 w-[520px] h-[520px] rounded-full bg-wa-mint/50 blur-bub"></div>
    <div class="absolute -bottom-40 -left-32 w-[460px] h-[460px] rounded-full bg-accent-amber/15 blur-bub"></div>

    {{-- 3 ambient bubble accents (lighter than homepage; just for atmosphere) --}}
    <div class="absolute inset-0 z-0 pointer-events-none overflow-hidden">
        <div class="absolute top-[18%] left-[3%] opacity-30 rotate-[-8deg]">
            <div
                class="bg-wa-bubble hairline border-wa-green/30 rounded-2xl rounded-tr-sm px-4 py-2.5 shadow-[0_8px_24px_-8px_rgba(7,94,84,0.3)]">
                <div class="text-[12px] font-medium text-wa-deep">🌷 {{ __('Order shipped') }}</div>
                <div class="text-[9px] text-wa-deep/60 mono text-right">14:08 ✓✓</div>
            </div>
        </div>
        <div class="absolute top-[72%] left-[5%] opacity-25 rotate-[5deg]">
            <div
                class="bg-white hairline rounded-2xl rounded-tl-sm px-4 py-2.5 shadow-[0_8px_24px_-8px_rgba(7,94,84,0.3)]">
                <div class="text-[12px] font-medium text-ink-900">{{ __('Track order') }} →</div>
                <div class="text-[9px] text-ink-500 mono text-right">14:10</div>
            </div>
        </div>
        <div class="absolute top-[20%] right-[4%] opacity-30 rotate-[7deg]">
            <div
                class="bg-white hairline rounded-2xl rounded-tl-sm px-4 py-2.5 shadow-[0_8px_24px_-8px_rgba(7,94,84,0.3)]">
                <div class="text-[12px] font-medium text-ink-900">+10% {{ __('off coupon') }} 🎁</div>
                <div class="text-[9px] text-ink-500 mono text-right">14:11</div>
            </div>
        </div>
    </div>

    <div class="relative max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-28">
        <div class="flex items-center justify-between mb-10">
            <div
                class="inline-flex items-center gap-2 hairline rounded-full px-3 py-1.5 bg-white text-[11px] mono uppercase tracking-widest text-ink-700">
                <span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>
                <span
                    data-fc="hero-features.eyebrow">{{ fc('hero-features.eyebrow', '18 ' . __('feature areas · all included · all plans')) }}</span>
            </div>
            <div class="hidden lg:flex items-center gap-5 text-[11px] mono uppercase tracking-widest text-ink-500">
                <span>{{ __('updated') }} {{ config('app.version', 'v4.2') }}</span>
                <span class="text-ink-400">/</span>
                <span class="text-wa-deep">{{ __('read time · 4 min') }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-end reveal">
            <div class="col-span-12 lg:col-span-8">
                <h1 data-fc="hero-features.headline" data-fc-type="richtext"
                    class="serif text-[44px] sm:text-[64px] lg:text-[104px] leading-[0.92] tracking-[-0.025em]">
                    {!! fc(
                        'hero-features.headline',
                        __('Every product,') .
                            '<br>
                     ' .
                            __('built to') .
                            ' <span class="italic text-wa-deep">' .
                            __('talk') .
                            '</span><br>
                     ' .
                            __('to each other.'),
                    ) !!}
                </h1>

                <div class="mt-8 flex flex-wrap gap-2">
                    @foreach (['Team Inbox', 'Broadcasts', 'Flow Builder · AI', 'WA Campaigns · A/B', 'Templates', 'Auto-Reply', 'Meta Ads · CTWA', 'AI Agents · RAG', 'Catalog · Storefront', 'Appointments', 'Chatbot Widgets', 'WA Forms', 'Chat Links', '3 Engines · WABA/Unofficial API/Twilio', '36 Integrations', 'WA Calling', 'Webhooks · API'] as $chip)
                        <span
                            class="hairline rounded-full bg-white px-3 py-1.5 text-[11.5px] mono">{{ $chip }}</span>
                    @endforeach
                    <span
                        class="hairline rounded-full bg-wa-deep text-paper-0 px-3 py-1.5 text-[11.5px] mono">{{ __('Security · SOC 2') }}</span>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4 flex flex-col gap-5 reveal" style="--d:120ms">
                <p data-fc="hero-features.subhead"
                    class="text-[15.5px] text-ink-700 leading-relaxed border-l-2 border-wa-deep pl-4">
                    {{ fc('hero-features.subhead', __('Broadcasts know your inbox. Flows know your catalog. Templates know your analytics. Click any card for the full breakdown — what it does, how it works, who it\'s for.')) }}
                </p>

                <div class="grid grid-cols-3 gap-0 hairline-t hairline-b py-4">
                    <div class="hairline-r pr-4">
                        <div class="serif text-[32px] leading-none tabular text-wa-deep">530<span
                                class="text-[18px] text-ink-500">+</span></div>
                        <div class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1.5">
                            {{ __('capabilities') }}</div>
                    </div>
                    <div class="hairline-r px-4">
                        <div class="serif text-[32px] leading-none tabular text-wa-deep">3</div>
                        <div class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1.5">{{ __('engines') }}
                        </div>
                    </div>
                    <div class="pl-4">
                        <div class="serif text-[32px] leading-none tabular text-wa-deep">1</div>
                        <div class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1.5">{{ __('bill') }}
                        </div>
                    </div>
                </div>

                <div class="hairline rounded-2xl bg-white p-4">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">{{ __('Jump to') }}</div>
                    <div class="grid grid-cols-2 gap-1.5 text-[11.5px]">
                        <a href="#feat-1" class="flex items-center gap-1.5 text-ink-700 hover:text-wa-deep"><span
                                class="serif text-wa-deep">01</span>{{ __('Team Inbox') }}</a>
                        <a href="#feat-2" class="flex items-center gap-1.5 text-ink-700 hover:text-wa-deep"><span
                                class="serif text-wa-deep">02</span>{{ __('Broadcasts') }}</a>
                        <a href="#feat-3" class="flex items-center gap-1.5 text-ink-700 hover:text-wa-deep"><span
                                class="serif text-wa-deep">03</span>{{ __('Flow Builder') }}</a>
                        <a href="#feat-7" class="flex items-center gap-1.5 text-ink-700 hover:text-wa-deep"><span
                                class="serif text-wa-deep">07</span>{{ __('Meta Ads CTWA') }}</a>
                        <a href="#feat-8" class="flex items-center gap-1.5 text-ink-700 hover:text-wa-deep"><span
                                class="serif text-wa-deep">08</span>{{ __('AI Agents') }}</a>
                        <a href="#feat-17" class="flex items-center gap-1.5 text-ink-700 hover:text-wa-deep"><span
                                class="serif text-wa-deep">17</span>{{ __('WA Calling') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
