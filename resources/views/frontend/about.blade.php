{{--
 Public About page — editorial, dense, no team cards.

 The hero is pinned to the top. Every other section is captured to a
 string keyed by its slug, then echoed in the admin-defined order
 (fc_section_order) and skipped when hidden (fc_section_visible). With no
 admin edits this renders in the exact shipped order, visibly identical.

 Sections: Hero → Origin story → Values → Timeline → Numbers → Press
 → Backers → Pull quote → CTA.
--}}
<x-layouts.frontend :title="__('About')" nav-key="about" page="frontend-about">

    {{-- ============== HERO (pinned, rendered first) ============== --}}
    <section data-fc-section="hero" class="relative overflow-hidden bg-paper-0">
        <div class="absolute inset-0 grid-bg opacity-30 pointer-events-none"></div>
        <div class="absolute -top-32 -right-32 w-[520px] h-[520px] rounded-full bg-wa-mint/50 blur-bub"></div>
        <div class="absolute -bottom-40 -left-32 w-[460px] h-[460px] rounded-full bg-accent-amber/15 blur-bub"></div>

        <div class="relative max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 items-end">
                <div class="col-span-12 lg:col-span-8 reveal">
                    <span data-fc="about.hero.eyebrow"
                        class="badge-num mb-6 inline-block">{{ fc('about.hero.eyebrow', __('— About us')) }}</span>
                    <h1 data-fc="about.hero.headline" data-fc-type="richtext"
                        class="serif text-[80px] lg:text-[104px] leading-[0.92] tracking-[-0.025em]">
                        {!! fc(
                            'about.hero.headline',
                            __(
                                'We build <span class="italic text-wa-deep">one workspace</span><br>so you can stop<br>stitching <span class="italic">five tools.</span>',
                            ),
                        ) !!}
                    </h1>
                </div>
                <div class="col-span-12 lg:col-span-4 reveal" style="--d:120ms">
                    <p data-fc="about.hero.intro"
                        class="text-[15.5px] text-ink-700 leading-relaxed border-l-2 border-wa-deep pl-4">
                        {{ fc('about.hero.intro', __('Twelve products under one bill. Broadcasts, flows, inbox, templates, AI, payments — built to talk to each other from day one.')) }}
                    </p>
                    {{-- Hero stat trio — value AND label of each are live-editable
 (data-fc + fc()) so a business swaps in its own founded year / market
 count / volume. Defaults keep the shipped copy. --}}
                    <div class="mt-6 grid grid-cols-3 gap-0 hairline-t hairline-b py-4">
                        <div class="hairline-r pr-4">
                            <div data-fc="about.hero.stat1-value" class="serif text-[32px] leading-none tabular text-wa-deep">{{ fc('about.hero.stat1-value', '2024') }}</div>
                            <div data-fc="about.hero.stat1-label" class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1.5">
                                {{ fc('about.hero.stat1-label', __('founded')) }}</div>
                        </div>
                        <div class="hairline-r px-4">
                            <div data-fc="about.hero.stat2-value" class="serif text-[32px] leading-none tabular text-wa-deep">{{ fc('about.hero.stat2-value', '38') }}</div>
                            <div data-fc="about.hero.stat2-label" class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1.5">
                                {{ fc('about.hero.stat2-label', __('markets')) }}</div>
                        </div>
                        <div class="pl-4">
                            <div data-fc="about.hero.stat3-value" class="serif text-[32px] leading-none tabular text-wa-deep">{{ fc('about.hero.stat3-value', '240M') }}</div>
                            <div data-fc="about.hero.stat3-label" class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1.5">
                                {{ fc('about.hero.stat3-label', __('msgs / mo')) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @php $sec = []; @endphp

    {{-- ============== ORIGIN STORY ============== --}}
    @php ob_start(); @endphp
    <section data-fc-section="origin-story" class="bg-white">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2">
                    <div class="feature-num">01</div>
                </div>
                <div class="col-span-3 flex flex-col justify-end pb-3">
                    <div data-fc="about.origin-story.eyebrow"
                        class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">
                        {{ fc('about.origin-story.eyebrow', __('— How we started')) }}</div>
                    <div data-fc="about.origin-story.sublabel" class="text-[13px] font-semibold">
                        {{ fc('about.origin-story.sublabel', __('Origin story')) }}</div>
                </div>
                <div class="col-span-7 flex items-end justify-end pb-3 gap-3 text-[11px] mono text-ink-500">
                    <span data-fc="about.origin-story.location">{{ fc('about.origin-story.location', __('Bengaluru + Berlin')) }}</span><span class="text-ink-300">·</span>
                    <span data-fc="about.origin-story.since">{{ fc('about.origin-story.since', __('shipping since 2024')) }}</span>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-12 items-start">
                <div class="col-span-12 lg:col-span-7 reveal">
                    <p data-fc="about.origin-story.para1"
                        class="serif text-[36px] leading-[1.2] tracking-[-0.01em] text-ink-900">
                        {{ fc('about.origin-story.para1', __('In 2023, our co-founders were running a flower business out of Mumbai. Five different tools. Five different bills. Customer messages arriving at midnight in five different inboxes.')) }}
                    </p>
                    <p data-fc="about.origin-story.para2"
                        class="serif text-[36px] leading-[1.2] tracking-[-0.01em] text-ink-900 mt-6">
                        {{ fc('about.origin-story.para2', __('We built :brand as the workspace we wished we had — broadcasts that read replies, flows that know the catalog, templates that get approved before you send them.', ['brand' => brand_name()])) }}
                    </p>
                    <p data-fc="about.origin-story.para3" data-fc-type="richtext"
                        class="serif text-[36px] leading-[1.2] tracking-[-0.01em] text-ink-900 mt-6">
                        {!! fc(
                            'about.origin-story.para3',
                            __('A year later <span class="italic text-wa-deep">4,218 teams</span> ship 240M messages a month through it.'),
                        ) !!}
                    </p>
                </div>

                <div class="col-span-12 lg:col-span-5 reveal" style="--d:160ms">
                    <div class="hairline rounded-3xl bg-paper-50 p-8 sticky top-28">
                        <div data-fc="about.origin-story.glance-label"
                            class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-4">
                            {{ fc('about.origin-story.glance-label', __('— At a glance')) }}</div>
                        {{-- Every glance row's label AND value is live-editable: each
 span carries data-fc + reads via fc() so the live editor's
 inline bridge can rewrite it (defaults keep the shipped copy). --}}
                        <ul class="space-y-4">
                            <li class="hairline-b pb-4 flex items-baseline justify-between">
                                <span data-fc="about.origin-story.glance-r1-label"
                                    class="text-[13px] text-ink-700">{{ fc('about.origin-story.glance-r1-label', __('Founded')) }}</span>
                                <span data-fc="about.origin-story.glance-r1-value"
                                    class="serif text-[20px] text-wa-deep">{{ fc('about.origin-story.glance-r1-value', __('Mar 2024')) }}</span>
                            </li>
                            <li class="hairline-b pb-4 flex items-baseline justify-between">
                                <span data-fc="about.origin-story.glance-r2-label"
                                    class="text-[13px] text-ink-700">{{ fc('about.origin-story.glance-r2-label', __('Headquarters')) }}</span>
                                <span data-fc="about.origin-story.glance-r2-value"
                                    class="serif text-[20px] text-wa-deep">{{ fc('about.origin-story.glance-r2-value', __('Bengaluru, India')) }}</span>
                            </li>
                            <li class="hairline-b pb-4 flex items-baseline justify-between">
                                <span data-fc="about.origin-story.glance-r3-label"
                                    class="text-[13px] text-ink-700">{{ fc('about.origin-story.glance-r3-label', __('EU office')) }}</span>
                                <span data-fc="about.origin-story.glance-r3-value"
                                    class="serif text-[20px] text-wa-deep">{{ fc('about.origin-story.glance-r3-value', __('Berlin, Germany')) }}</span>
                            </li>
                            <li class="hairline-b pb-4 flex items-baseline justify-between">
                                <span data-fc="about.origin-story.glance-r4-label"
                                    class="text-[13px] text-ink-700">{{ fc('about.origin-story.glance-r4-label', __('Employees')) }}</span>
                                <span data-fc="about.origin-story.glance-r4-value"
                                    class="serif text-[20px] text-wa-deep">{{ fc('about.origin-story.glance-r4-value', '38') }}</span>
                            </li>
                            <li class="hairline-b pb-4 flex items-baseline justify-between">
                                <span data-fc="about.origin-story.glance-r5-label"
                                    class="text-[13px] text-ink-700">{{ fc('about.origin-story.glance-r5-label', __('Customers')) }}</span>
                                <span data-fc="about.origin-story.glance-r5-value"
                                    class="serif text-[20px] text-wa-deep">{{ fc('about.origin-story.glance-r5-value', '4,218') }}</span>
                            </li>
                            <li class="pb-1 flex items-baseline justify-between">
                                <span data-fc="about.origin-story.glance-r6-label"
                                    class="text-[13px] text-ink-700">{{ fc('about.origin-story.glance-r6-label', __('Funding')) }}</span>
                                <span data-fc="about.origin-story.glance-r6-value"
                                    class="serif text-[20px] text-wa-deep">{{ fc('about.origin-story.glance-r6-value', __('Seed · $4.2M')) }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @php $sec['origin-story'] = ob_get_clean(); @endphp

    {{-- ============== VALUES ============== --}}
    @php ob_start(); @endphp
    <section data-fc-section="values" class="bg-paper-50 hairline-t hairline-b">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2">
                    <div class="feature-num">02</div>
                </div>
                <div class="col-span-3 flex flex-col justify-end pb-3">
                    <div data-fc="about.values.eyebrow"
                        class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">
                        {{ fc('about.values.eyebrow', __('— What we believe')) }}</div>
                    <div data-fc="about.values.sublabel" class="text-[13px] font-semibold">
                        {{ fc('about.values.sublabel', __('Five operating principles')) }}</div>
                </div>
                <div class="col-span-7 flex items-end justify-end pb-3 gap-3 text-[11px] mono text-ink-500">
                    <span data-fc="about.values.caption" class="text-wa-deep">{{ fc('about.values.caption', __('printed on every team laptop')) }}</span>
                </div>
            </div>

            <h2 data-fc="about.values.headline" data-fc-type="richtext"
                class="serif text-[88px] leading-[0.92] tracking-[-0.02em] mb-12 reveal">
                {!! fc(
                    'about.values.headline',
                    __('Strong opinions,<br>loosely <span class="italic text-wa-deep">held.</span>'),
                ) !!}
            </h2>

            <div class="grid grid-cols-2 lg:grid-cols-5 gap-5 reveal" style="--d:120ms">
                @foreach ([['i', __('Per-seat is wrong.'), __('You should pay for messages your customers receive — never for chairs in your office.')], ['ii', __('Tools should talk.'), __('Broadcasts read replies. Flows know your catalog. Analytics tag every send.')], ['iii', __('Support is a human.'), __('Email us, get a person inside 4 hours. Free plan included. Yes, really.')], ['iv', __('Ship weekly, polish daily.'), __('We deploy every Tuesday. Nothing fancy — we just refuse to break our promises.')], ['v', __('Default to honest.'), __('No dark patterns. No hidden tiers. No "contact sales" for the listed price. Ever.')]] as [$num, $title, $desc])
                    <div class="hairline rounded-3xl bg-white p-6">
                        <div class="serif text-[48px] leading-none text-wa-deep/40">{{ $num }}</div>
                        <h3 data-fc="about.values.item{{ $loop->iteration }}-title"
                            class="serif text-[24px] leading-tight mt-4">
                            {{ fc("about.values.item{$loop->iteration}-title", $title) }}</h3>
                        <p data-fc="about.values.item{{ $loop->iteration }}-desc"
                            class="text-[12.5px] text-ink-600 mt-3 leading-relaxed">
                            {{ fc("about.values.item{$loop->iteration}-desc", $desc) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @php $sec['values'] = ob_get_clean(); @endphp

    {{-- ============== TIMELINE ============== --}}
    @php ob_start(); @endphp
    <section data-fc-section="timeline" class="bg-white">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2">
                    <div class="feature-num">03</div>
                </div>
                <div class="col-span-3 flex flex-col justify-end pb-3">
                    <div data-fc="about.timeline.eyebrow"
                        class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">
                        {{ fc('about.timeline.eyebrow', __('— Timeline')) }}</div>
                    <div data-fc="about.timeline.sublabel" class="text-[13px] font-semibold">
                        {{ fc('about.timeline.sublabel', __('Two years in')) }}</div>
                </div>
            </div>

            <h2 data-fc="about.timeline.headline" data-fc-type="richtext"
                class="serif text-[88px] leading-[0.92] tracking-[-0.02em] mb-12 reveal">
                {!! fc(
                    'about.timeline.headline',
                    __('From flower shop<br>to <span class="italic text-wa-deep">240M / month.</span>'),
                ) !!}
            </h2>

            <div class="hairline rounded-3xl bg-paper-50 overflow-hidden reveal" style="--d:120ms">
                @foreach ([
        ['Q1 2024', __('Founded'), __('Priya & Dario start building :brand out of a Bengaluru garage. First commit lands on a Tuesday.', ['brand' => brand_name()]), true],
        ['Q2 2024', __('Seed round · $4.2M'), __('Y Combinator + Sequoia Surge back the team. First two engineers join, ship broadcasts module.'), true],
        ['Q3 2024', __('First 100 customers'), __('Bloomly Flowers signs up. Maison & Co. migrates from Wati. Inbox + Flows go live the same week.'), true],
        ['Q4 2024', __('SOC 2 Type II'), __('Independent audit completed. EU office opens in Berlin. Templates library hits 71 starters.'), true],
        ['Q1 2025', __('AI Copilot · v4.0'), __('Describe a flow in plain English, get a 14-node graph in 2.4 seconds. Adoption hits 67% in week one.'), true],
        ['Q2 2025', __('Crossed 1,000 teams'), __('Marigold & Co · Lagos, Pebble · Mumbai, FORMAS · São Paulo all migrate in the same week.'), true],
        ['Q1 2026', __('AI Flow Generation · v4.2'), __('Latest release — full-stack flow generation, version control, public roadmap. 4,218 teams shipping.'), false],
        ['Q2 2026', __('Next: payments · v5.0'), __('In-thread checkout across 22 gateways. Beta opens to Pro plan customers in June.'), false],
    ] as $i => [$qtr, $title, $desc, $shipped])
                    <div class="grid grid-cols-12 gap-6 px-8 py-7 {{ !$loop->last ? 'hairline-b' : '' }}">
                        <div class="col-span-2">
                            <div data-fc="about.timeline.item{{ $loop->iteration }}-qtr"
                                class="mono text-[10px] uppercase tracking-widest text-wa-deep">
                                {{ fc("about.timeline.item{$loop->iteration}-qtr", $qtr) }}</div>
                        </div>
                        <div class="col-span-1 flex justify-center relative">
                            <span
                                class="w-3 h-3 rounded-full {{ $shipped ? 'bg-wa-green' : 'bg-paper-300' }} mt-2 relative z-10"></span>
                            @if (!$loop->last)
                                <div class="absolute top-5 bottom-[-28px] w-px bg-paper-200"></div>
                            @endif
                        </div>
                        <div class="col-span-9">
                            <h3 data-fc="about.timeline.item{{ $loop->iteration }}-title"
                                class="serif text-[28px] leading-tight">
                                {{ fc("about.timeline.item{$loop->iteration}-title", $title) }}</h3>
                            <p data-fc="about.timeline.item{{ $loop->iteration }}-desc"
                                class="text-[13.5px] text-ink-700 mt-2 leading-relaxed max-w-2xl">
                                {{ fc("about.timeline.item{$loop->iteration}-desc", $desc) }}</p>
                            @if ($shipped)
                                <span class="pill bg-wa-bubble text-wa-deep text-[10px] mt-3 inline-flex">
                                    <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('shipped') }}
                                </span>
                            @else
                                <span
                                    class="pill bg-paper-100 text-ink-700 text-[10px] mt-3 inline-flex">{{ __('upcoming') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @php $sec['timeline'] = ob_get_clean(); @endphp

    {{-- ============== NUMBERS ============== --}}
    @php ob_start(); @endphp
    <section data-fc-section="numbers" class="bg-paper-50 hairline-t hairline-b">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2">
                    <div class="feature-num">04</div>
                </div>
                <div class="col-span-3 flex flex-col justify-end pb-3">
                    <div data-fc="about.numbers.eyebrow"
                        class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">
                        {{ fc('about.numbers.eyebrow', __('— By the numbers')) }}</div>
                    <div data-fc="about.numbers.sublabel" class="text-[13px] font-semibold">
                        {{ fc('about.numbers.sublabel', __('Where we are today')) }}</div>
                </div>
                <div class="col-span-7 flex items-end justify-end pb-3 gap-3 text-[11px] mono text-ink-500">
                    <span>{{ __('refreshed') }} {{ now()->format('M Y') }}</span>
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 reveal" style="--d:120ms">
                @foreach ([['240M', __('messages / month'), __('across 38 markets')], ['4,218', __('teams'), __('shipping every day')], ['$0', __('per-agent fees'), __('and never will be')], ['96%', __('CSAT'), '12,400 ' . __('ratings')], ['142%', __('net retention'), __('over 12 months')], ['18m', __('template approval'), __('median, under 4% reject')], ['42s', __('first response'), __('SLA, business hours')], ['99.98%', __('uptime'), __('rolling 90 days')]] as [$big, $label, $sub])
                    <div class="hairline rounded-2xl bg-white p-6">
                        <div data-fc="about.numbers.item{{ $loop->iteration }}-big"
                            class="serif text-[56px] leading-none tabular text-wa-deep">
                            {{ fc("about.numbers.item{$loop->iteration}-big", $big) }}</div>
                        <div data-fc="about.numbers.item{{ $loop->iteration }}-label"
                            class="mono text-[10px] uppercase tracking-widest text-ink-500 mt-3">
                            {{ fc("about.numbers.item{$loop->iteration}-label", $label) }}</div>
                        <div data-fc="about.numbers.item{{ $loop->iteration }}-sub"
                            class="text-[12px] text-ink-600 mt-1">
                            {{ fc("about.numbers.item{$loop->iteration}-sub", $sub) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @php $sec['numbers'] = ob_get_clean(); @endphp

    {{-- ============== PRESS ============== --}}
    @php ob_start(); @endphp
    <section data-fc-section="press" class="bg-white">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2">
                    <div class="feature-num">05</div>
                </div>
                <div class="col-span-3 flex flex-col justify-end pb-3">
                    <div data-fc="about.press.eyebrow"
                        class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">
                        {{ fc('about.press.eyebrow', __('— In the news')) }}</div>
                    <div data-fc="about.press.sublabel" class="text-[13px] font-semibold">
                        {{ fc('about.press.sublabel', __('Press & coverage')) }}</div>
                </div>
            </div>

            <h2 data-fc="about.press.headline" data-fc-type="richtext"
                class="serif text-[88px] leading-[0.92] tracking-[-0.02em] mb-12 reveal">
                {!! fc('about.press.headline', __('What people<br>are <span class="italic text-wa-deep">writing.</span>')) !!}
            </h2>

            <div class="grid grid-cols-2 lg:grid-cols-3 gap-5 reveal" style="--d:120ms">
                @foreach ([['TechCrunch', __('Mar 2026'), __('":brand quietly built the WhatsApp platform every operator wanted — without the per-seat trap."', ['brand' => brand_name()])], ['The Information', __('Feb 2026'), __('"Bengaluru-Berlin SaaS startup hits 240M messages a month with a 38-person team."')], ['Forbes India', __('Jan 2026'), __('"How :brand is replacing four tools at every D2C brand that touches WhatsApp."', ['brand' => brand_name()])], ['YourStory', __('Nov 2025'), __('"The bootstrap-to-Seed story behind India\'s fastest-growing WhatsApp platform."')], ['Sifted', __('Sep 2025'), __('":brand opens Berlin office, signals European push for SOC 2 + GDPR-native CRM."', ['brand' => brand_name()])], ['Product Hunt', __('Aug 2025'), __('"#1 product of the week — AI Flow Generation goes viral, 14k upvotes in 24 hours."')]] as [$outlet, $date, $blurb])
                    <div class="hairline rounded-2xl bg-paper-50 p-6 hover:border-wa-deep transition">
                        <div class="flex items-center justify-between mb-3">
                            <div data-fc="about.press.item{{ $loop->iteration }}-outlet" class="serif text-[20px]">
                                {{ fc("about.press.item{$loop->iteration}-outlet", $outlet) }}</div>
                            <div data-fc="about.press.item{{ $loop->iteration }}-date"
                                class="mono text-[10px] text-ink-500">
                                {{ fc("about.press.item{$loop->iteration}-date", $date) }}</div>
                        </div>
                        <p data-fc="about.press.item{{ $loop->iteration }}-blurb"
                            class="text-[13px] text-ink-700 leading-relaxed">
                            {{ fc("about.press.item{$loop->iteration}-blurb", $blurb) }}</p>
                        <a href="#"
                            class="text-[12px] text-wa-deep font-semibold mt-4 inline-block">{{ __('Read article →') }}</a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @php $sec['press'] = ob_get_clean(); @endphp

    {{-- ============== BACKERS ============== --}}
    @php ob_start(); @endphp
    <section data-fc-section="backers" class="bg-paper-50 hairline-t hairline-b">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2">
                    <div class="feature-num">06</div>
                </div>
                <div class="col-span-3 flex flex-col justify-end pb-3">
                    <div data-fc="about.backers.eyebrow"
                        class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">
                        {{ fc('about.backers.eyebrow', __('— Backed by')) }}</div>
                    <div data-fc="about.backers.sublabel" class="text-[13px] font-semibold">
                        {{ fc('about.backers.sublabel', __('Investors & angels')) }}</div>
                </div>
            </div>

            <h2 data-fc="about.backers.headline" data-fc-type="richtext"
                class="serif text-[88px] leading-[0.92] tracking-[-0.02em] mb-12 reveal">
                {!! fc(
                    'about.backers.headline',
                    __('Funded by people who<br>have <span class="italic text-wa-deep">shipped.</span>'),
                ) !!}
            </h2>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-5 reveal" style="--d:120ms">
                @foreach (['Y Combinator', 'Sequoia Surge', 'Lightspeed', 'Tiger Global', 'Better Capital', 'Peak XV'] as $vc)
                    <div class="hairline rounded-2xl bg-white p-6 flex items-center justify-center min-h-[100px]">
                        <div data-fc="about.backers.vc{{ $loop->iteration }}" class="serif text-[20px] text-center">
                            {{ fc("about.backers.vc{$loop->iteration}", $vc) }}</div>
                    </div>
                @endforeach
            </div>

            <div class="mt-10 hairline rounded-3xl bg-white p-8">
                <div data-fc="about.backers.operators-label"
                    class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-4">
                    {{ fc('about.backers.operators-label', __('— And these operators')) }}</div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-8 gap-y-3 text-[13px]">
                    @foreach ([['Naval Ravikant', __('Founder, AngelList')], ['Patrick Collison', __('Co-founder, Stripe')], ['Kunal Shah', __('Founder, CRED')], ['Sahil Lavingia', __('Founder, Gumroad')], ['Lenny Rachitsky', __('Lenny\'s Newsletter')], ['Calvin French-Owen', __('Co-founder, Segment')], ['Nikita Bier', __('Operator, ex-Meta')], ['Suhail Doshi', __('Co-founder, Mixpanel')]] as [$name, $title])
                        <div>
                            <div data-fc="about.backers.op{{ $loop->iteration }}-name" class="font-semibold">
                                {{ fc("about.backers.op{$loop->iteration}-name", $name) }}</div>
                            <div data-fc="about.backers.op{{ $loop->iteration }}-title"
                                class="mono text-[10px] text-ink-500 mt-0.5">
                                {{ fc("about.backers.op{$loop->iteration}-title", $title) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    @php $sec['backers'] = ob_get_clean(); @endphp

    {{-- ============== PULL QUOTE ============== --}}
    @php ob_start(); @endphp<x-frontend.pull-quote />@php $sec['pull-quote'] = ob_get_clean(); @endphp

    {{-- ============== FINAL CTA ============== --}}
    @php ob_start(); @endphp<x-frontend.cta-final :kicker="__('Come build with us')" :headline="__('Two founders.<br>Now <span class=\'italic text-wa-green\'>4,218</span> teams.')"
        :subtitle="__('Live in 4 minutes. No credit card. Cancel anytime, keep your data.')"
        :secondaryLabel="__('Contact us')" :secondaryHref="url('/contact')" />@php $sec['cta-final'] = ob_get_clean(); @endphp

    @php
        foreach (fc_section_order('about', array_keys($sec)) as $slug) {
            if (fc_section_visible('about', $slug)) {
                echo $sec[$slug];
            }
        }
    @endphp

</x-layouts.frontend>
