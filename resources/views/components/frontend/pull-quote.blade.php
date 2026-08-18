@props([
    /** The quote body. HTML allowed for italic emphasis spans. */
    'quote' => null,
    'authorName' => 'Priya Ramaswamy',
    'authorRole' => 'Head of CX, Bloomly Flowers · Mumbai',
    'authorInitials' => 'PR',
    /** Two optional stat callouts on the right (each: [label, value]). */
    'stats' => null,
])

@php
    $quote =
        $quote ??
        __(
            'We replaced AiSensy, Klaviyo, Freshdesk, and a Zapier mess. Read rates jumped from <span class="italic">19%</span> on email to <span class="italic text-wa-deep">86% on WhatsApp</span> — and agents reply 3× faster. CFO is happy.',
        );
    $stats = $stats ?? [
        ['label' => __('tools replaced'), 'value' => '4 → 1'],
        ['label' => __('repeat orders'), 'value' => '+38%'],
    ];
@endphp

<section class="bg-paper-50 hairline-t hairline-b" data-fc-section="pull-quote">
    <div class="max-w-[1360px] mx-auto px-4 sm:px-7 py-28">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <div class="col-span-12 lg:col-span-2">
                <svg viewBox="0 0 32 24" class="w-12 h-9 text-wa-deep" fill="currentColor">
                    <path
                        d="M6 0C2.7 0 0 2.7 0 6v6c0 3.3 2.7 6 6 6 1 0 1.5-.5 1.5-1.5S7 15 6 15c-1.7 0-3-1.3-3-3h3c1.7 0 3-1.3 3-3V6c0-3.3-2.7-6-3-6zm20 0c-3.3 0-6 2.7-6 6v6c0 3.3 2.7 6 6 6 1 0 1.5-.5 1.5-1.5S27 15 26 15c-1.7 0-3-1.3-3-3h3c1.7 0 3-1.3 3-3V6c0-3.3-2.7-6-3-6z" />
                </svg>
            </div>

            <div class="col-span-12 lg:col-span-10">
                <p class="serif text-[28px] sm:text-[48px] leading-[1.12] tracking-[-0.01em]" data-fc="pull-quote.quote">
                    {!! fc('pull-quote.quote', $quote) !!}
                </p>

                <div class="mt-8 flex items-center gap-4 hairline-t pt-5 flex-wrap">
                    <span
                        class="w-12 h-12 rounded-full bg-gradient-to-br from-accent-coral to-accent-amber flex items-center justify-center text-paper-0 font-semibold"
                        data-fc="pull-quote.author_initials">{{ fc('pull-quote.author_initials', $authorInitials) }}</span>
                    <div class="flex-1 min-w-0">
                        <div class="text-[14px] font-semibold" data-fc="pull-quote.author">
                            {{ fc('pull-quote.author', $authorName) }}</div>
                        <div class="text-[12px] text-ink-500" data-fc="pull-quote.author_role">
                            {{ fc('pull-quote.author_role', $authorRole) }}</div>
                    </div>

                    @foreach ($stats as $i => $stat)
                        <div class="text-right">
                            <div class="mono text-[10px] uppercase tracking-widest text-ink-500"
                                data-fc="pull-quote.stat{{ $i + 1 }}_label">
                                {{ fc('pull-quote.stat' . ($i + 1) . '_label', $stat['label']) }}</div>
                            <div class="serif text-[28px] text-wa-deep"
                                data-fc="pull-quote.stat{{ $i + 1 }}_value">
                                {{ fc('pull-quote.stat' . ($i + 1) . '_value', $stat['value']) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
