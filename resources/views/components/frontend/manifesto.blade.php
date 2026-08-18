{{--
 Editorial manifesto section — left column carries a big serif
 headline, hand-drawn squiggle, and a founder pull-quote with
 attribution. Right column carries the manifesto paragraph and
 three stat tiles (12 products / $0 fees / 1 invoice).
--}}
<section class="max-w-[1360px] mx-auto px-4 sm:px-7 py-28 reveal" data-fc-section="manifesto">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        <div class="col-span-12 lg:col-span-3">
            <span class="badge-num" data-fc="manifesto.eyebrow">— {{ fc('manifesto.eyebrow', __('Manifesto')) }}</span>

            <h2 class="serif text-[44px] sm:text-[68px] leading-[0.92] tracking-[-0.02em] mt-8 text-ink-900"
                data-fc="manifesto.headline">
                {!! fc(
                    'manifesto.headline',
                    __('Why') .
                        ' <span class="italic text-wa-deep">' .
                        __('one') .
                        '</span><br>
                 ' .
                        __('workspace') .
                        '<br>
                 ' .
                        __('beats') .
                        ' <span class="italic">' .
                        __('five.') .
                        '</span>',
                ) !!}
            </h2>

            <svg viewBox="0 0 240 18" class="w-44 mt-5 text-wa-green" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round">
                <path d="M2 12 Q 60 2, 120 8 T 238 6" />
            </svg>

            <div class="mt-10 hairline-l border-wa-deep/40 pl-4">
                <p class="serif italic text-[16px] leading-snug text-ink-700" data-fc="manifesto.quote">
                    “{{ fc('manifesto.quote', __('We stopped paying five vendors. Started shipping conversations.')) }}”
                </p>
                <div class="mt-4 flex items-center gap-3">
                    <span
                        class="w-9 h-9 rounded-full bg-gradient-to-br from-wa-deep to-wa-teal text-paper-0 text-[11px] font-semibold flex items-center justify-center"
                        data-fc="manifesto.author_initials">{{ fc('manifesto.author_initials', 'PR') }}</span>
                    <div class="leading-tight">
                        <div class="text-[12.5px] font-semibold" data-fc="manifesto.author">
                            {{ fc('manifesto.author', 'Priya R.') }}</div>
                        <div class="mono text-[10px] text-ink-500" data-fc="manifesto.author_role">
                            {{ fc('manifesto.author_role', __('co-founder · Bengaluru')) }}</div>
                    </div>
                </div>
            </div>

            <div class="mt-8 mono text-[10px] uppercase tracking-[0.22em] text-ink-500 flex items-center gap-3">
                <span data-fc="manifesto.est_label">{{ fc('manifesto.est_label', __('est.') . ' 2024') }}</span>
                <span class="flex-1 h-px bg-paper-200"></span>
                <span>{{ config('app.version', 'v 4.2') }}</span>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-9">
            <p class="serif text-[28px] sm:text-[44px] leading-[1.18] tracking-[-0.01em] text-ink-900" data-fc="manifesto.body">
                {!! fc(
                    'manifesto.body',
                    __('Most platforms make you stitch five tools together — and then charge you per seat.') .
                        '
                 ' .
                        __('We built') .
                        ' <span class="italic text-wa-deep">' .
                        __('one workspace') .
                        '</span> ' .
                        __(
                            'where broadcasts know your inbox, flows know your catalog, and templates know your analytics. One bill. Pay only for what you ship.',
                        ),
                ) !!}
            </p>

            <div class="mt-10 grid grid-cols-3 gap-0 hairline-t hairline-b py-6">
                <div class="hairline-r pr-3 sm:pr-6 min-w-0">
                    <div class="serif text-[40px] sm:text-[64px] leading-none tabular text-wa-deep" data-fc="manifesto.stat1_value">
                        {{ fc('manifesto.stat1_value', '12') }}</div>
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mt-2"
                        data-fc="manifesto.stat1_label">
                        {{ fc('manifesto.stat1_label', __('products · one workspace')) }}</div>
                </div>
                <div class="hairline-r px-3 sm:px-6 min-w-0">
                    <div class="serif text-[40px] sm:text-[64px] leading-none tabular text-wa-deep" data-fc="manifesto.stat2_value">
                        {{ fc('manifesto.stat2_value', '$0') }}</div>
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mt-2"
                        data-fc="manifesto.stat2_label">{{ fc('manifesto.stat2_label', __('per-agent fees · ever')) }}
                    </div>
                </div>
                <div class="pl-3 sm:pl-6 min-w-0">
                    <div class="serif text-[40px] sm:text-[64px] leading-none tabular text-wa-deep" data-fc="manifesto.stat3_value">
                        {{ fc('manifesto.stat3_value', '1') }}</div>
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mt-2"
                        data-fc="manifesto.stat3_label">{{ fc('manifesto.stat3_label', __('invoice · no surprises')) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
