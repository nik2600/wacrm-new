{{-- Feature 04 · Templates · 18-minute approval.
 3-card preview row (UTL / CRSL / OTP), then "How it works" split:
 LEFT — pre-flight checks card, RIGHT — A/B/C/D ordered steps. --}}
<section class="bg-white" data-fc-section="feature-templates">
    <div class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-28">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
            <div class="lg:col-span-2">
                <div class="feature-num text-[80px] sm:text-[110px] lg:text-[140px]">04</div>
            </div>
            <div class="lg:col-span-3 flex flex-col justify-end pb-3">
                <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1"
                    data-fc="feature-templates.eyebrow">{{ fc('feature-templates.eyebrow', __('Feature four')) }}</div>
                <div class="text-[13px] font-semibold" data-fc="feature-templates.label">
                    {{ fc('feature-templates.label', __('Template library & submission')) }}</div>
            </div>
            <div class="lg:col-span-7 flex flex-wrap items-end lg:justify-end pb-3 gap-3 text-[11px] mono text-ink-500">
                <span
                    data-fc="feature-templates.meta1">{{ fc('feature-templates.meta1', '71 ' . __('starters')) }}</span><span
                    class="text-ink-400">·</span>
                <span
                    data-fc="feature-templates.meta2">{{ fc('feature-templates.meta2', '9 ' . __('industries')) }}</span><span
                    class="text-ink-400">·</span>
                <span
                    data-fc="feature-templates.meta3">{{ fc('feature-templates.meta3', __('pre-validated')) }}</span><span
                    class="text-ink-400">·</span>
                <span class="text-wa-deep"
                    data-fc="feature-templates.meta4">{{ fc('feature-templates.meta4', '18 ' . __('min approval')) }}</span>
            </div>
        </div>

        <h2 class="serif text-[44px] sm:text-[64px] lg:text-[88px] leading-[0.92] tracking-[-0.02em] mb-3 reveal"
            data-fc="feature-templates.headline">
            {!! fc(
                'feature-templates.headline',
                '71 ' .
                    __('starters.') .
                    '<br>' .
                    __('Approved in') .
                    ' <span class="italic text-wa-deep">18 ' .
                    __('minutes.') .
                    '</span>',
            ) !!}
        </h2>
        <p class="text-[15.5px] text-ink-700 max-w-2xl leading-relaxed reveal" style="--d:120ms"
            data-fc="feature-templates.body">
            {{ fc('feature-templates.body', __("Carousel, utility, OTP, marketing — every template pre-validated against Meta's policy before submission. Rejection rate under 4%.")) }}
        </p>

        {{-- 3 live template preview cards --}}
        <div class="mt-14 grid grid-cols-1 lg:grid-cols-12 gap-5 reveal" style="--d:160ms">

            {{-- Card 1 · order_paid (utility) --}}
            <div class="col-span-12 lg:col-span-4 hairline rounded-3xl bg-paper-50 p-5 relative">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span
                            class="w-9 h-9 rounded-lg bg-wa-bubble text-wa-deep flex items-center justify-center mono text-[10px] font-bold">UTL</span>
                        <div>
                            <div class="text-[12.5px] font-semibold">order_paid_v2</div>
                            <div class="mono text-[9.5px] text-ink-500">utility · 8 langs</div>
                        </div>
                    </div>
                    <span class="pill bg-wa-green/15 text-wa-deep">{{ __('approved') }}</span>
                </div>
                <div class="chat-grid rounded-xl p-3">
                    <div class="bg-white rounded-lg rounded-tl-sm px-3 py-2 max-w-full shadow-sm">
                        <div class="text-[11.5px]"><b>{{ __('Hi Maya') }} 👋</b><br>{{ __('Your order') }} <b>#4218</b>
                            {{ __('for ₹1,499 has been paid. Tracking link below.') }}</div>
                        <button
                            class="mt-2 w-full hairline border-wa-deep/30 text-wa-deep text-[10px] font-semibold rounded py-1 bg-white">{{ __('Track order') }}</button>
                        <div class="text-[9px] text-ink-500 mono text-right mt-1">14:08 ✓✓</div>
                    </div>
                </div>
                <div class="hairline-t mt-4 pt-3 flex items-center justify-between text-[11px]">
                    <span class="mono text-ink-500">31,890 {{ __('sent') }}</span>
                    <span class="text-wa-deep font-semibold">CTR 19.2%</span>
                </div>
            </div>

            {{-- Card 2 · spring_promo (carousel) --}}
            <div class="col-span-12 lg:col-span-4 hairline rounded-3xl bg-paper-50 p-5 relative">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span
                            class="w-9 h-9 rounded-lg bg-accent-coral/20 text-accent-coral flex items-center justify-center mono text-[10px] font-bold">CRSL</span>
                        <div>
                            <div class="text-[12.5px] font-semibold">spring_promo_v3</div>
                            <div class="mono text-[9.5px] text-ink-500">marketing · 3 langs</div>
                        </div>
                    </div>
                    <span class="pill bg-wa-green/15 text-wa-deep">{{ __('approved') }}</span>
                </div>
                <div class="chat-grid rounded-xl p-3">
                    <div class="bg-white rounded-lg rounded-tl-sm overflow-hidden shadow-sm">
                        <div class="flex gap-1 p-1">
                            <div
                                class="w-14 h-12 rounded bg-gradient-to-br from-accent-coral via-accent-amber to-wa-green shrink-0">
                            </div>
                            <div
                                class="w-14 h-12 rounded bg-gradient-to-br from-wa-teal via-accent-sand to-wa-deep shrink-0">
                            </div>
                            <div
                                class="w-14 h-12 rounded bg-gradient-to-br from-wa-mint via-wa-green to-wa-teal shrink-0">
                            </div>
                        </div>
                        <div class="px-2.5 pb-1.5">
                            <div class="text-[10.5px] font-semibold">{{ __('Spring picks 🌷') }}</div>
                            <div class="text-[9.5px] text-ink-500">{{ __('Tap to see prices') }}</div>
                        </div>
                        <div class="hairline-t flex">
                            <button
                                class="flex-1 py-1 text-[10px] text-wa-deep font-semibold">{{ __('Browse') }}</button>
                            <button
                                class="flex-1 py-1 text-[10px] text-wa-deep font-semibold hairline-l">{{ __('Chat') }}</button>
                        </div>
                    </div>
                </div>
                <div class="hairline-t mt-4 pt-3 flex items-center justify-between text-[11px]">
                    <span class="mono text-ink-500">48,201 {{ __('sent') }}</span>
                    <span class="text-wa-deep font-semibold">CTR 11.4%</span>
                </div>
            </div>

            {{-- Card 3 · login_otp (auth) --}}
            <div class="col-span-12 lg:col-span-4 hairline rounded-3xl bg-paper-50 p-5 relative">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span
                            class="w-9 h-9 rounded-lg bg-accent-amber/20 text-accent-amber flex items-center justify-center mono text-[10px] font-bold">OTP</span>
                        <div>
                            <div class="text-[12.5px] font-semibold">login_otp_secure</div>
                            <div class="mono text-[9.5px] text-ink-500">authentication · 1 lang</div>
                        </div>
                    </div>
                    <span class="pill bg-wa-green/15 text-wa-deep">{{ __('approved') }}</span>
                </div>
                <div class="chat-grid rounded-xl p-3">
                    <div class="bg-white rounded-lg rounded-tl-sm px-3 py-2 shadow-sm">
                        <div class="text-[11.5px]">{{ __('Your one-time code:') }}</div>
                        <div class="text-center my-2"><span
                                class="mono text-[26px] tracking-[0.4em] text-wa-deep font-bold">4 8 2 1</span></div>
                        <div class="text-[10px] text-ink-500">{{ __('Valid for 5 minutes') }}</div>
                        <button
                            class="mt-2 w-full hairline border-wa-deep/30 text-wa-deep text-[10px] font-semibold rounded py-1 bg-white">{{ __('Copy code') }}</button>
                        <div class="text-[9px] text-ink-500 mono text-right mt-1">14:08 ✓✓</div>
                    </div>
                </div>
                <div class="hairline-t mt-4 pt-3 flex items-center justify-between text-[11px]">
                    <span class="mono text-ink-500">28,402 {{ __('sent') }}</span>
                    <span class="text-wa-deep font-semibold">99.7% {{ __('deliv') }}</span>
                </div>
            </div>
        </div>

        {{-- How it works · pre-flight + 4-step ordered list --}}
        <div class="mt-14 grid grid-cols-1 lg:grid-cols-12 gap-8 reveal" style="--d:240ms">

            <div class="col-span-12 lg:col-span-4">
                <h3 class="serif text-[36px] leading-[1.05]" data-fc="feature-templates.how_title">
                    {{ fc('feature-templates.how_title', __('How it works.')) }}</h3>
                <p class="text-[13px] text-ink-600 mt-3 leading-relaxed" data-fc="feature-templates.how_body">
                    {{ fc('feature-templates.how_body', __('Browse the library, customize variables, hit "submit to Meta." :brand pre-flights against current policy and serves you back any reasons for likely rejection — before you click send.', ['brand' => brand_name()])) }}
                </p>

                <div class="mt-6 hairline rounded-2xl bg-white p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500"
                            data-fc="feature-templates.preflight_title">
                            {{ fc('feature-templates.preflight_title', __('Pre-flight checks')) }}</div>
                        <span class="pill bg-wa-green/15 text-wa-deep text-[10px]"
                            data-fc="feature-templates.preflight_badge">{{ fc('feature-templates.preflight_badge', '12 / 12 ' . __('pass')) }}</span>
                    </div>
                    <ul class="space-y-2 text-[12px]">
                        @foreach ([fc('feature-templates.rule1', __('Category match · utility vs. marketing')), fc('feature-templates.rule2', __('Opt-in language present')), fc('feature-templates.rule3', __('No banned characters · emojis OK')), fc('feature-templates.rule4', __('Variables resolve · @{{ 1 }} … @{{ n }}')), fc('feature-templates.rule5', __('Button URL whitelisted'))] as $rule)
                            <li class="flex items-center gap-2"><span
                                    class="text-wa-deep mono text-[12px]">✓</span><span>{{ $rule }}</span></li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-8">
                <ol class="space-y-3">
                    @foreach ([['A', fc('feature-templates.step1_title', __('Pick a starter or duplicate your own')), fc('feature-templates.step1_desc', __('9 industries, 71 templates. Filter by language, category, or compliance tier.'))], ['B', fc('feature-templates.step2_title', __('Edit variables & preview live')), fc('feature-templates.step2_desc', __('Side-by-side WhatsApp preview updates as you type. Test with sample data.'))], ['C', fc('feature-templates.step3_title', __('Submit · we pre-validate against Meta policy')), fc('feature-templates.step3_desc', __('We flag promotional words in utility templates, missing opt-ins, banned characters — before you send.'))], ['D', fc('feature-templates.step4_title', __('Approved · median 18 minutes')), fc('feature-templates.step4_desc', __('Rejection rate stays under 4%. Use immediately in flows, campaigns, or the API.'))]] as [$letter, $title, $desc])
                        <li class="hairline rounded-2xl bg-white p-4 flex items-start gap-4">
                            <span class="serif text-[34px] text-wa-deep leading-none">{{ $letter }}</span>
                            <div>
                                <div class="text-[14px] font-semibold mb-1">{{ $title }}</div>
                                <p class="text-[12.5px] text-ink-600 leading-relaxed">{{ $desc }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </div>
</section>
