{{--
 Homepage FAQ — an infinite, auto-scrolling slider (two rows, opposite
 directions, pause on hover). Pulls FAQs flagged for the homepage in
 /admin/pricing-faqs (placement = home | both). Falls back to a default set
 so the section is never empty on a fresh install.
--}}
@php
    try {
        $faqs = \App\Models\PricingFaq::active()->placement('home')->get(['question', 'answer']);
    } catch (\Throwable $e) {
        $faqs = collect();
    }

    if ($faqs->isEmpty()) {
        $faqs = collect([
            ['question' => 'What is the WhatsApp Business API?', 'answer' => 'The official way to send and automate WhatsApp messages at scale — multiple agents, templates, chatbots and analytics on one number.'],
            ['question' => 'Will my number get banned?', 'answer' => 'Not if you respect opt-in, keep a healthy quality rating and pace your sends. We handle warm-up and pacing for you.'],
            ['question' => 'Can I broadcast to thousands of contacts?', 'answer' => 'Yes — to opted-in contacts, with batching and gaps that protect your number and your delivery rate.'],
            ['question' => 'Do I need to write code?', 'answer' => 'No. Build chatbots, flows and campaigns with a drag-and-drop builder — no developers required.'],
            ['question' => 'How does WhatsApp pricing work?', 'answer' => 'WhatsApp bills per 24-hour conversation, by category and country — not per message. We show the cost up front.'],
            ['question' => 'Can I connect my existing number?', 'answer' => 'Yes, via the official Cloud API. Customer-initiated chats stay in one shared team inbox.'],
            ['question' => 'Is there a free trial?', 'answer' => 'Every new workspace starts on a trial so you can test broadcasts, automation and the inbox before you pay.'],
            ['question' => 'Can I run ads that open a WhatsApp chat?', 'answer' => 'Yes — Click-to-WhatsApp ads drop customers straight into a conversation, and we route them into your inbox + pipeline.'],
        ]);
    }

    $rows = $faqs->values();
    $half = (int) ceil($rows->count() / 2);
    $rowA = $rows->slice(0, $half)->values();
    $rowB = $rows->slice($half)->values();
    if ($rowB->isEmpty()) {
        $rowB = $rowA;
    }
@endphp

@props(['kicker' => 'FAQ', 'headline' => null, 'subtitle' => null])

<section class="py-20 sm:py-28 overflow-hidden">
    <div class="max-w-[1360px] mx-auto px-6 text-center mb-12">
        <div class="mono text-[11px] uppercase tracking-[0.22em] text-wa-deep mb-3"
            data-fc="{{ fc_skey('faq-slider.kicker') }}">{{ fcp('faq-slider.kicker', $kicker) }}</div>
        <h2 class="serif text-[34px] sm:text-[46px] leading-[1.05] text-ink-900"
            data-fc="{{ fc_skey('faq-slider.headline') }}">
            {!! fcp('faq-slider.headline', $headline ?: __('Questions, <span class="italic text-wa-deep">answered</span>.')) !!}
        </h2>
        <p class="mt-3 text-[14px] text-ink-600 max-w-xl mx-auto"
            data-fc="{{ fc_skey('faq-slider.subtitle') }}">
            {{ fcp('faq-slider.subtitle', $subtitle ?: __('Everything teams ask before switching to WhatsApp. Hover to pause.')) }}
        </p>
    </div>

    <div class="wd-faq-mask space-y-4">
        @foreach ([['list' => $rowA, 'rev' => false], ['list' => $rowB, 'rev' => true]] as $row)
            <div class="wd-faq-marquee">
                <div class="wd-faq-track {{ $row['rev'] ? 'rev' : '' }}">
                    {{-- rendered twice for a seamless infinite loop --}}
                    @foreach ([1, 2] as $pass)
                        @foreach ($row['list'] as $f)
                            <article class="wd-faq-card" aria-hidden="{{ $pass === 2 ? 'true' : 'false' }}">
                                <div class="flex items-start gap-2.5">
                                    <span class="mt-0.5 shrink-0 w-6 h-6 rounded-full bg-wa-bubble flex items-center justify-center">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none" stroke="currentColor" stroke-width="1.7">
                                            <path d="M6 6a2 2 0 1 1 3 1.7c-.6.4-1 .8-1 1.6M8 12v.01" /><circle cx="8" cy="8" r="6.5" />
                                        </svg>
                                    </span>
                                    <h3 class="font-semibold text-[14px] leading-snug text-ink-900">{{ is_array($f) ? $f['question'] : $f->question }}</h3>
                                </div>
                                <p class="mt-2.5 text-[12.5px] leading-relaxed text-ink-600 wd-faq-answer">{{ is_array($f) ? $f['answer'] : $f->answer }}</p>
                            </article>
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>
