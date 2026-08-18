{{-- Feature 03 · Shared inbox.
 Full 4-pane inbox mock (channels · threads · chat · customer-360),
 plus a 6-tile capabilities row below. --}}
<section class="bg-paper-50 hairline-t hairline-b" data-fc-section="feature-inbox">
    <div class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-28">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
            <div class="lg:col-span-2">
                <div class="feature-num text-[80px] sm:text-[110px] lg:text-[140px]">03</div>
            </div>
            <div class="lg:col-span-3 flex flex-col justify-end pb-3">
                <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1"
                    data-fc="feature-inbox.eyebrow">{{ fc('feature-inbox.eyebrow', __('Feature three')) }}</div>
                <div class="text-[13px] font-semibold" data-fc="feature-inbox.label">
                    {{ fc('feature-inbox.label', __('Shared inbox & service desk')) }}</div>
            </div>
            <div class="lg:col-span-7 flex flex-wrap items-end lg:justify-end pb-3 gap-3 text-[11px] mono text-ink-500">
                <span data-fc="feature-inbox.meta1">{{ fc('feature-inbox.meta1', __('round-robin')) }}</span><span
                    class="text-ink-400">·</span>
                <span data-fc="feature-inbox.meta2">{{ fc('feature-inbox.meta2', __('SLA timers')) }}</span><span
                    class="text-ink-400">·</span>
                <span data-fc="feature-inbox.meta3">{{ fc('feature-inbox.meta3', __('internal notes')) }}</span><span
                    class="text-ink-400">·</span>
                <span class="text-wa-deep"
                    data-fc="feature-inbox.meta4">{{ fc('feature-inbox.meta4', __('42s first response')) }}</span>
            </div>
        </div>

        <h2 class="serif text-[44px] sm:text-[64px] lg:text-[88px] leading-[0.92] tracking-[-0.02em] mb-3 reveal" data-fc="feature-inbox.headline">
            {!! fc(
                'feature-inbox.headline',
                __('Slack-fast.') . '<br>' . __('For') . ' <span class="italic text-wa-deep">WhatsApp</span>.',
            ) !!}
        </h2>
        <p class="text-[15.5px] text-ink-700 max-w-2xl leading-relaxed reveal" style="--d:120ms"
            data-fc="feature-inbox.body">
            {{ fc('feature-inbox.body', __("A shared inbox your agents won't quit over. Assign, escalate, snooze, resolve. Customer history, order data, and tags side-by-side. Keyboard-first.")) }}
        </p>

        {{-- inbox layout --}}
        <div class="mt-14 hairline rounded-3xl bg-white overflow-x-auto lg:overflow-hidden reveal" style="--d:160ms">
            <div class="grid grid-cols-12 min-w-[900px] lg:min-w-0 min-h-[520px]">

                {{-- channels list --}}
                <div class="col-span-2 hairline-r p-4 bg-paper-50">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">{{ __('Views') }}</div>
                    <div class="space-y-1 text-[12.5px]">
                        <div
                            class="px-2.5 py-1.5 rounded-lg bg-wa-deep text-paper-0 font-semibold flex items-center justify-between">
                            {{ __('All open') }} <span class="mono text-[10px] bg-paper-0/15 px-1.5 rounded">14</span>
                        </div>
                        <div class="px-2.5 py-1.5 rounded-lg flex items-center justify-between text-ink-700">
                            {{ __('Mine') }} <span class="mono text-[10px] text-ink-500">3</span></div>
                        <div class="px-2.5 py-1.5 rounded-lg flex items-center justify-between text-ink-700">
                            {{ __('Unassigned') }} <span class="mono text-[10px] text-accent-coral">5</span></div>
                        <div class="px-2.5 py-1.5 rounded-lg flex items-center justify-between text-ink-700">
                            {{ __('SLA breach') }} <span class="mono text-[10px] text-accent-coral">2</span></div>
                        <div class="px-2.5 py-1.5 rounded-lg flex items-center justify-between text-ink-700">
                            {{ __('Snoozed') }} <span class="mono text-[10px] text-ink-500">8</span></div>
                        <div class="px-2.5 py-1.5 rounded-lg flex items-center justify-between text-ink-700">
                            {{ __('Closed today') }} <span class="mono text-[10px] text-ink-500">42</span></div>
                    </div>
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mt-6 mb-3">{{ __('Tags') }}
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <span class="pill bg-paper-100 text-ink-700">vip</span>
                        <span class="pill bg-accent-coral/15 text-accent-coral">refund</span>
                        <span class="pill bg-accent-amber/20 text-[#8B5A14]">delivery</span>
                        <span class="pill bg-wa-bubble text-wa-deep">loyal</span>
                    </div>
                </div>

                {{-- thread list --}}
                <div class="col-span-3 hairline-r">
                    <div class="px-4 py-3 hairline-b bg-paper-50 flex items-center justify-between">
                        <span class="text-[12px] font-semibold">{{ __('All open · 14') }}</span>
                        <span class="mono text-[10px] text-ink-500">SLA: 2m</span>
                    </div>
                    <div class="divide-y divide-paper-200">
                        <div class="px-4 py-3 bg-wa-bubble/30 cursor-pointer">
                            <div class="flex items-center justify-between text-[12.5px] font-medium">Maya R.<span
                                    class="mono text-[10px] text-wa-deep">2m</span></div>
                            <div class="text-[11px] text-ink-500 truncate mt-0.5">my order #4218 says delivered…</div>
                            <div class="flex items-center gap-1 mt-1.5"><span
                                    class="pill bg-paper-50 text-ink-700 text-[9.5px]">vip</span><span
                                    class="pill bg-accent-coral/15 text-accent-coral text-[9.5px]">delivery</span></div>
                        </div>
                        <div class="px-4 py-3 cursor-pointer">
                            <div class="flex items-center justify-between text-[12.5px] font-medium">Anish K.<span
                                    class="mono text-[10px] text-accent-coral">8m · {{ __('breach') }}</span></div>
                            <div class="text-[11px] text-ink-500 truncate mt-0.5">extend my Pro plan?</div>
                        </div>
                        <div class="px-4 py-3 cursor-pointer">
                            <div class="flex items-center justify-between text-[12.5px] font-medium">Tomás S.<span
                                    class="mono text-[10px] text-ink-500">14m</span></div>
                            <div class="text-[11px] text-ink-500 truncate mt-0.5">📎 receipt.pdf · refund</div>
                        </div>
                        <div class="px-4 py-3 cursor-pointer">
                            <div class="flex items-center justify-between text-[12.5px] font-medium">Sara K.<span
                                    class="mono text-[10px] text-ink-500">22m</span></div>
                            <div class="text-[11px] text-ink-500 truncate mt-0.5">do you ship to Berlin?</div>
                        </div>
                        <div class="px-4 py-3 cursor-pointer">
                            <div class="flex items-center justify-between text-[12.5px] font-medium">Lina O.<span
                                    class="mono text-[10px] text-ink-500">38m</span></div>
                            <div class="text-[11px] text-ink-500 truncate mt-0.5">missing 1 stem 🌷</div>
                        </div>
                    </div>
                </div>

                {{-- chat pane --}}
                <div class="col-span-5 hairline-r chat-grid p-4 relative">
                    <div class="bg-white hairline rounded-lg px-3 py-2 mb-4 flex items-center gap-3">
                        <span
                            class="w-9 h-9 rounded-full bg-gradient-to-br from-accent-coral to-accent-amber flex items-center justify-center text-paper-0 text-[11px] font-semibold">MR</span>
                        <div class="flex-1">
                            <div class="text-[12.5px] font-semibold">Maya Ramaswamy</div>
                            <div class="mono text-[10px] text-ink-500">+91 98xx · VIP · 12 {{ __('orders') }} · LTV
                                $1,840</div>
                        </div>
                        <span class="pill bg-wa-bubble text-wa-deep"><span
                                class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('online') }}</span>
                    </div>

                    <div class="space-y-2">
                        <div class="flex">
                            <div class="bg-white rounded-lg rounded-tl-sm px-3 py-2 max-w-[78%] shadow-sm">
                                <div class="text-[11.5px]">order #4218 says delivered but I haven't received it 😕</div>
                                <div class="text-[9px] text-ink-500 mono text-right mt-0.5">14:08</div>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <div class="bg-wa-bubble rounded-lg rounded-tr-sm px-3 py-2 max-w-[78%] shadow-sm">
                                <div
                                    class="text-[10px] mono text-wa-deep mb-0.5 font-semibold uppercase tracking-wider">
                                    ↳ {{ __('flow') }} · order_lookup</div>
                                <div class="text-[11.5px]">{{ __('Checking #4218 with our courier…') }}</div>
                                <div class="text-[9px] text-wa-deep/60 mono text-right mt-0.5">14:08 ✓✓</div>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <div class="bg-wa-bubble rounded-lg rounded-tr-sm px-3 py-2 max-w-[78%] shadow-sm">
                                <div class="text-[11.5px]">
                                    {{ __('Left at apt 4B per courier note. Want me to ping them?') }}</div>
                                <div class="text-[9px] text-wa-deep/60 mono text-right mt-0.5">14:09 ✓✓</div>
                            </div>
                        </div>
                        <div class="flex justify-center">
                            <div
                                class="hairline border-accent-amber/30 bg-accent-amber/10 rounded-lg px-3 py-2 max-w-[88%]">
                                <div class="mono text-[9.5px] uppercase tracking-widest text-[#8B5A14] mb-0.5">
                                    {{ __('internal note · @sara') }}</div>
                                <div class="text-[11.5px] text-ink-800">
                                    {{ __("VIP – let's offer a 10% credit just in case.") }}</div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="absolute bottom-4 left-4 right-4 bg-white hairline rounded-xl p-2 flex items-center gap-2">
                        <span
                            class="w-7 h-7 rounded-md hairline flex items-center justify-center text-ink-500">+</span>
                        <div class="flex-1 text-[11.5px] text-ink-400">{{ __('Reply to Maya · / for canned') }}</div>
                        <span class="pill bg-wa-bubble text-wa-deep">⌘↵</span>
                    </div>
                </div>

                {{-- side panel · customer 360 --}}
                <div class="col-span-2 p-4">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">{{ __('Customer') }}
                    </div>
                    <div class="space-y-2.5 text-[11.5px]">
                        <div class="flex justify-between"><span class="text-ink-500">{{ __('Status') }}</span><span
                                class="font-semibold text-wa-deep">VIP</span></div>
                        <div class="flex justify-between"><span class="text-ink-500">{{ __('Orders') }}</span><span
                                class="font-semibold tabular">12</span></div>
                        <div class="flex justify-between"><span class="text-ink-500">LTV</span><span
                                class="font-semibold tabular">$1,840</span></div>
                        <div class="flex justify-between"><span
                                class="text-ink-500">{{ __('First seen') }}</span><span class="font-semibold">Apr
                                2024</span></div>
                        <div class="flex justify-between"><span
                                class="text-ink-500">{{ __('Sentiment') }}</span><span
                                class="font-semibold text-accent-coral">↓ {{ __('falling') }}</span></div>
                    </div>
                    <div class="hairline-t mt-4 pt-4 mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">SLA
                    </div>
                    <div class="hairline rounded-lg bg-wa-bubble/40 border-wa-green/30 p-2.5">
                        <div class="flex items-center justify-between text-[10.5px] mono"><span
                                class="text-wa-deep">{{ __('first response') }}</span><span
                                class="font-semibold">42s</span></div>
                        <div class="flex items-center justify-between text-[10.5px] mono mt-1"><span
                                class="text-wa-deep">{{ __('resolution') }}</span><span class="font-semibold">2m
                                14s</span></div>
                    </div>
                    <div class="hairline-t mt-4 pt-4 mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">
                        {{ __('Assigned') }}</div>
                    <div class="flex items-center gap-2"><span
                            class="w-7 h-7 rounded-full bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 text-[10px] font-semibold flex items-center justify-center">SK</span><span
                            class="text-[11.5px]">Sara K.</span></div>
                </div>
            </div>
        </div>

        {{-- 6 capability tiles --}}
        <div class="mt-12 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 reveal" style="--d:240ms">
            @foreach ([['01', fc('feature-inbox.tile1_title', __('Round-robin')), fc('feature-inbox.tile1_desc', __('Per-team rules, skill tags, capacity caps.'))], ['02', fc('feature-inbox.tile2_title', __('SLA timers')), fc('feature-inbox.tile2_desc', __('First response & resolution targets per channel.'))], ['03', fc('feature-inbox.tile3_title', __('Internal notes')), fc('feature-inbox.tile3_desc', __('@mention teammates without the customer seeing.'))], ['04', fc('feature-inbox.tile4_title', __('Canned replies')), fc('feature-inbox.tile4_desc', __('Variables, snippets, per-team libraries.'))], ['05', fc('feature-inbox.tile5_title', __('Customer 360')), fc('feature-inbox.tile5_desc', __('Orders, LTV, sentiment, tags side-by-side.'))], ['06', fc('feature-inbox.tile6_title', __('CSAT on resolve')), fc('feature-inbox.tile6_desc', __('In-chat survey, dashboard breakdown by agent.'))]] as [$num, $title, $desc])
                <div class="hairline rounded-2xl bg-white p-4">
                    <div class="serif text-[64px] leading-[0.85] tracking-[-0.04em] text-wa-deep/40">
                        {{ $num }}</div>
                    <div class="text-[13px] font-semibold mt-2">{{ $title }}</div>
                    <p class="text-[11.5px] text-ink-600 mt-1">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
