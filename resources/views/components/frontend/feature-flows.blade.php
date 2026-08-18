{{-- Feature 02 · Visual flow builder + AI Copilot.
 LEFT: AI prompt card with generation indicator + result preview.
 RIGHT: editor canvas with 4 nodes connected by dashed arrows.
 Below: 4-tile capability strip (18 nodes / ∞ branches / 2.4s / version). --}}
<section class="bg-white" data-fc-section="feature-flows">
    <div class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-28">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
            <div class="lg:col-span-2">
                <div class="feature-num text-[80px] sm:text-[110px] lg:text-[140px]">02</div>
            </div>
            <div class="lg:col-span-3 flex flex-col justify-end pb-3">
                <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1"
                    data-fc="feature-flows.eyebrow">{{ fc('feature-flows.eyebrow', __('Feature two')) }}</div>
                <div class="text-[13px] font-semibold" data-fc="feature-flows.label">
                    {{ fc('feature-flows.label', __('Visual flow builder & AI Copilot')) }}</div>
            </div>
            <div class="lg:col-span-7 flex flex-wrap items-end lg:justify-end pb-3 gap-3 text-[11px] mono text-ink-500">
                <span
                    data-fc="feature-flows.meta1">{{ fc('feature-flows.meta1', '18 ' . __('node types')) }}</span><span
                    class="text-ink-400">·</span>
                <span data-fc="feature-flows.meta2">{{ fc('feature-flows.meta2', 'GPT-4 + Claude') }}</span><span
                    class="text-ink-400">·</span>
                <span data-fc="feature-flows.meta3">{{ fc('feature-flows.meta3', __('webhook in/out')) }}</span><span
                    class="text-ink-400">·</span>
                <span class="text-wa-deep"
                    data-fc="feature-flows.meta4">{{ fc('feature-flows.meta4', __('version control')) }}</span>
            </div>
        </div>

        <h2 class="serif text-[44px] sm:text-[64px] lg:text-[88px] leading-[0.92] tracking-[-0.02em] mb-3 reveal" data-fc="feature-flows.headline">
            {!! fc(
                'feature-flows.headline',
                __('Drag, drop, branch. Or describe') .
                    '<br>' .
                    __('a flow to') .
                    ' <span class="italic text-wa-deep">Copilot</span> ' .
                    __('in plain English.'),
            ) !!}
        </h2>
        <p class="text-[15.5px] text-ink-700 max-w-2xl leading-relaxed reveal" style="--d:120ms"
            data-fc="feature-flows.body">
            {{ fc('feature-flows.body', __('Eighteen node types, infinite branches, AI-suggested next steps. Tell Copilot what you want, watch it build a 14-node graph in 2.4 seconds — then tweak by hand.')) }}
        </p>

        <div class="mt-14 grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">

            {{-- LEFT: AI prompt --}}
            <div class="col-span-12 lg:col-span-4 reveal">
                <div class="hairline rounded-3xl bg-paper-50 p-6 h-full flex flex-col">
                    <div class="flex items-center gap-2 mb-4">
                        <span
                            class="w-8 h-8 rounded-lg bg-wa-deep text-paper-0 flex items-center justify-center text-[10px] font-bold">AI</span>
                        <div>
                            <div class="text-[12.5px] font-semibold">{{ __('Copilot · flow generation') }}</div>
                            <div class="mono text-[9.5px] text-ink-500">GPT-4o · Claude 3.5</div>
                        </div>
                        <span class="ml-auto pill bg-wa-bubble text-wa-deep mono"><span
                                class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>{{ __('ready') }}</span>
                    </div>
                    <div class="hairline rounded-xl bg-white p-3 text-[12.5px] leading-relaxed text-ink-800 flex-1">
                        <span
                            class="mono text-[9px] uppercase tracking-widest text-ink-500 block mb-2">{{ __('prompt') }}</span>
                        "{{ __("After a customer abandons cart, wait 1 hour, send the cart back with a 5% off coupon, then follow up at 24h if they haven't bought.") }}"
                    </div>
                    <div class="mt-3 flex items-center justify-center gap-2 mono text-[10px] text-wa-deep">
                        <span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>
                        <span>{{ __('generated in 2.4s') }}</span>
                    </div>
                    <div
                        class="mt-3 hairline border-wa-green/40 rounded-xl bg-wa-bubble p-3 text-[12px] leading-relaxed">
                        <span class="text-wa-deep font-semibold">✓ 9 {{ __('nodes') }}</span> · cart_abandoned
                        {{ __('trigger') }} · {{ __('delay') }} 1h · {{ __('template') }} <span
                            class="mono text-wa-deep">cart_back</span> · {{ __('condition') }} <span
                            class="mono text-wa-deep">paid?</span> · {{ __('delay') }} 23h · {{ __('template') }}
                        <span class="mono text-wa-deep">last_call</span> · …
                    </div>
                    <a href="{{ fc('feature-flows.cta_url', '#') }}"
                        class="mt-4 w-full bg-wa-green text-ink-900 rounded-full text-[12.5px] font-semibold py-2.5 text-center hover:bg-[#1ec05a]"
                        data-fc="feature-flows.cta_label">{{ fc('feature-flows.cta_label', __('Open in editor →')) }}</a>
                </div>
            </div>

            {{-- RIGHT: canvas --}}
            <div class="col-span-12 lg:col-span-8 reveal" style="--d:120ms">
                <div class="hairline rounded-3xl bg-white p-6 h-full">

                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-wa-green pulse-dot"></span>
                            <span class="text-[12px] font-semibold">cart_recover_v3.flow</span>
                            <span class="mono text-[10px] text-ink-500">· {{ __('auto-saved 4s ago') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="pill bg-paper-100 text-ink-700 mono">14 {{ __('nodes') }}</span>
                            <span class="pill bg-paper-100 text-ink-700 mono">3 {{ __('branches') }}</span>
                            <span class="pill bg-wa-bubble text-wa-deep mono">v3 · {{ __('live') }}</span>
                        </div>
                    </div>

                    <div class="hairline rounded-2xl bg-paper-50 grid-bg p-5 relative h-[360px] overflow-hidden">

                        <div class="absolute top-6 left-6 hairline rounded-xl bg-white shadow-sm p-3 w-[180px]">
                            <div class="mono text-[9px] uppercase tracking-widest text-accent-coral">
                                {{ __('trigger') }}</div>
                            <div class="text-[12px] font-semibold mt-1">{{ __('Cart abandoned') }}</div>
                            <div class="text-[10px] text-ink-500 mono mt-0.5">shopify</div>
                        </div>

                        <svg class="absolute top-[60px] left-[170px] w-[140px] h-[60px]" viewBox="0 0 140 60"
                            fill="none">
                            <path d="M0 5 Q 70 5, 70 30 T 140 50" stroke="#3A5A55" stroke-width="1.5"
                                stroke-dasharray="3 3" />
                            <path d="M134 47 L141 50 L134 53" stroke="#3A5A55" stroke-width="1.5" fill="none" />
                        </svg>

                        <div
                            class="absolute top-[100px] left-[260px] hairline rounded-xl bg-white shadow-sm p-3 w-[150px]">
                            <div class="mono text-[9px] uppercase tracking-widest text-accent-amber">
                                {{ __('delay') }}</div>
                            <div class="text-[12px] font-semibold mt-1">{{ __('Wait 1 hour') }}</div>
                            <div class="text-[10px] text-ink-500 mono mt-0.5">3,600 sec</div>
                        </div>

                        <svg class="absolute top-[140px] left-[400px] w-[140px] h-[60px]" viewBox="0 0 140 60"
                            fill="none">
                            <path d="M0 5 Q 70 5, 70 30 T 140 50" stroke="#3A5A55" stroke-width="1.5"
                                stroke-dasharray="3 3" />
                            <path d="M134 47 L141 50 L134 53" stroke="#3A5A55" stroke-width="1.5" fill="none" />
                        </svg>

                        <div
                            class="absolute top-[180px] left-[490px] hairline rounded-xl bg-white shadow-sm p-3 w-[200px]">
                            <div class="mono text-[9px] uppercase tracking-widest text-wa-deep">
                                {{ __('message · template') }}</div>
                            <div class="text-[12px] font-semibold mt-1">{{ __('Send "cart_back"') }}</div>
                            <div class="text-[10px] text-ink-500 mono mt-0.5">+ 5% {{ __('coupon') }}</div>
                        </div>

                        <svg class="absolute top-[200px] left-[700px] w-[120px] h-[120px]" viewBox="0 0 120 120"
                            fill="none">
                            <path d="M0 20 Q 60 20, 60 70 T 110 110" stroke="#3A5A55" stroke-width="1.5"
                                stroke-dasharray="3 3" />
                            <path d="M0 20 Q 60 20, 60 -10 T 110 -20" stroke="#3A5A55" stroke-width="1.5"
                                stroke-dasharray="3 3" />
                        </svg>

                        <div
                            class="absolute top-[130px] right-6 hairline border-wa-green/40 rounded-xl bg-wa-bubble shadow-sm p-3 w-[180px]">
                            <div class="mono text-[9px] uppercase tracking-widest text-wa-deep">
                                {{ __('if paid · exit') }}</div>
                            <div class="text-[12px] font-semibold mt-1">{{ __('Send "thank_you"') }}</div>
                            <div class="text-[10px] text-ink-500 mono mt-0.5">tag: converted</div>
                        </div>

                        <div class="absolute bottom-6 right-6 hairline rounded-xl bg-white shadow-sm p-3 w-[180px]">
                            <div class="mono text-[9px] uppercase tracking-widest text-accent-coral">
                                {{ __('if no purchase · 23h') }}</div>
                            <div class="text-[12px] font-semibold mt-1">"last_call"</div>
                            <div class="text-[10px] text-ink-500 mono mt-0.5">+ 10% {{ __('coupon') }}</div>
                        </div>

                        <div
                            class="absolute bottom-3 left-3 hairline rounded-md bg-white px-2 py-1 mono text-[9px] text-ink-500">
                            <span class="text-accent-coral">●</span> {{ __('trigger') }} · <span
                                class="text-accent-amber">●</span> {{ __('delay') }} · <span
                                class="text-wa-deep">●</span> {{ __('message') }} · <span
                                class="text-wa-green">●</span> {{ __('action') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 4 capability tiles --}}
        <div class="mt-8 grid grid-cols-2 lg:grid-cols-4 gap-4 reveal" style="--d:200ms">
            @foreach ([['18', fc('feature-flows.tile1_label', __('Node types')), fc('feature-flows.tile1_desc', __('Trigger, delay, condition, message, webhook, AI reply, payment, tag, branch, split-test, and more.'))], ['∞', fc('feature-flows.tile2_label', __('Branches')), fc('feature-flows.tile2_desc', __('Nest as deep as you want. Visualised on an infinite canvas with one-click collapse.'))], ['2.4s', fc('feature-flows.tile3_label', __('AI generation')), fc('feature-flows.tile3_desc', __('Median time from prompt to a working draft. Tweak by hand once it lands.'))], ['v', fc('feature-flows.tile4_label', __('Version control')), fc('feature-flows.tile4_desc', __('Every save is a version. Roll back, diff, share previews with a link.'))]] as [$num, $label, $desc])
                <div class="hairline rounded-2xl bg-white p-5">
                    <div class="serif text-[40px] leading-none text-wa-deep">{{ $num }}</div>
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mt-2">{{ $label }}
                    </div>
                    <p class="text-[12px] text-ink-600 mt-2">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
