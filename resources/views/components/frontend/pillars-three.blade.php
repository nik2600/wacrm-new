{{--
 Three-pillar "why one workspace" section. Sits between the bento
 and the pull-quote on the features page. Pillars are static — if
 you want to swap them per page, expose a $pillars prop on the
 next iteration.
--}}
<section class="bg-white" data-fc-section="pillars-three">
    <div class="max-w-[1360px] mx-auto px-4 sm:px-7 py-28">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <div class="col-span-12 lg:col-span-3">
                <span class="badge-num" data-fc="pillars-three.eyebrow">—
                    {{ fc('pillars-three.eyebrow', __('Why twelve')) }}</span>
                <h2 class="serif text-[40px] sm:text-[56px] leading-[0.92] tracking-[-0.02em] mt-6" data-fc="pillars-three.headline">
                    {!! fc(
                        'pillars-three.headline',
                        __('Built like') .
                            '<br>
                     <span class="italic text-wa-deep">' .
                            __('one') .
                            '</span><br>
                     ' .
                            __('product.'),
                    ) !!}
                </h2>
                <svg viewBox="0 0 240 18" class="w-44 mt-5 text-wa-green" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round">
                    <path d="M2 12 Q 60 2, 120 8 T 238 6" />
                </svg>
            </div>

            <div class="col-span-12 lg:col-span-9">
                <p class="serif text-[26px] sm:text-[36px] leading-[1.18] tracking-[-0.01em] text-ink-900"
                    data-fc="pillars-three.intro">
                    {!! fc(
                        'pillars-three.intro',
                        __(
                            'Most platforms ship features that never meet each other. A broadcast tool that can\'t see the inbox. Flows that don\'t know the catalog. Templates without analytics.',
                        ) .
                            '
                     ' .
                            __('We built features that') .
                            ' <span class="italic text-wa-deep">' .
                            __('share the same workspace') .
                            '</span> — ' .
                            __('broadcasts read replies, flows enrol carts, analytics tag every send.'),
                    ) !!}
                </p>

                <div class="mt-10 grid grid-cols-1 lg:grid-cols-3 gap-5">

                    <div class="hairline rounded-2xl bg-paper-50 p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="badge-num">i</span>
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500"
                                data-fc="pillars-three.pillar1_label">{{ fc('pillars-three.pillar1_label', __('Shared data')) }}</span>
                        </div>
                        <h3 class="serif text-[24px] leading-tight" data-fc="pillars-three.pillar1_title">
                            {{ fc('pillars-three.pillar1_title', __('Every feature reads the same customer record.')) }}
                        </h3>
                        <p class="text-[12.5px] text-ink-600 mt-3" data-fc="pillars-three.pillar1_body">
                            {{ fc('pillars-three.pillar1_body', __('Tag a VIP in inbox · broadcasts respect it · flows route it · analytics segment it. One source of truth, twelve consumers.')) }}
                        </p>
                    </div>

                    <div class="hairline rounded-2xl bg-paper-50 p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="badge-num">ii</span>
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500"
                                data-fc="pillars-three.pillar2_label">{{ fc('pillars-three.pillar2_label', __('Composable')) }}</span>
                        </div>
                        <h3 class="serif text-[24px] leading-tight" data-fc="pillars-three.pillar2_title">
                            {{ fc('pillars-three.pillar2_title', __('Stack features in one click — no plumbing.')) }}
                        </h3>
                        <p class="text-[12.5px] text-ink-600 mt-3" data-fc="pillars-three.pillar2_body">
                            {{ fc('pillars-three.pillar2_body', __('Drop a payment node into a flow. Embed a template in a broadcast. Pipe an inbox reply to a webhook. No connectors. No middleware.')) }}
                        </p>
                    </div>

                    <div class="hairline rounded-2xl bg-paper-50 p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="badge-num">iii</span>
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500"
                                data-fc="pillars-three.pillar3_label">{{ fc('pillars-three.pillar3_label', __('One bill')) }}</span>
                        </div>
                        <h3 class="serif text-[24px] leading-tight" data-fc="pillars-three.pillar3_title">
                            {{ fc('pillars-three.pillar3_title', __('Pay for messages — never per seat, per feature.')) }}
                        </h3>
                        <p class="text-[12.5px] text-ink-600 mt-3" data-fc="pillars-three.pillar3_body">
                            {{ fc('pillars-three.pillar3_body', __('Whether you ship one feature or all twelve, your bill is the same shape. No upgrade nags. No per-agent fees. Ever.')) }}
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>
