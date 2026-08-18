{{-- Use cases · 4 vertical cards (E-commerce / D2C / Service / Fintech)
 each with a roman-numeral badge, headline, 4 bullet uses, and an
 "avg uplift" stat at the bottom. --}}
<section class="bg-white" data-fc-section="use-cases">
    <div class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-28">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
            <div class="lg:col-span-2 min-w-0">
                <div class="feature-num text-[64px] sm:text-[96px] lg:text-[140px]">08</div>
            </div>
            <div class="lg:col-span-3 flex flex-col justify-end pb-3 min-w-0">
                <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1" data-fc="use-cases.eyebrow">
                    {{ fc('use-cases.eyebrow', __('By industry')) }}</div>
                <div class="text-[13px] font-semibold" data-fc="use-cases.label">
                    {{ fc('use-cases.label', __('Use cases & outcomes')) }}</div>
            </div>
            <div class="lg:col-span-7 flex flex-wrap items-end lg:justify-end pb-3 gap-3 text-[11px] mono text-ink-500 min-w-0">
                <span data-fc="use-cases.meta1">{{ fc('use-cases.meta1', '4 ' . __('verticals')) }}</span><span
                    class="text-ink-400">·</span>
                <span data-fc="use-cases.meta2">{{ fc('use-cases.meta2', __('customer-tested')) }}</span><span
                    class="text-ink-400">·</span>
                <span class="text-wa-deep"
                    data-fc="use-cases.meta3">{{ fc('use-cases.meta3', __('measurable lift')) }}</span>
            </div>
        </div>

        <h2 class="serif text-[40px] sm:text-[64px] lg:text-[88px] leading-[0.92] tracking-[-0.02em] mb-3 reveal" data-fc="use-cases.headline">
            {!! fc(
                'use-cases.headline',
                __('Built for every') .
                    '<br>' .
                    __('kind of') .
                    ' <span class="italic text-wa-deep">' .
                    __('conversation.') .
                    '</span>',
            ) !!}
        </h2>

        <div class="mt-14 grid grid-cols-2 lg:grid-cols-4 gap-4 reveal" style="--d:120ms">
            @foreach ([
        [
            'roman' => 'i',
            'cat' => fc('use-cases.case1_cat', __('E-commerce')),
            'title' => fc('use-cases.case1_title', 'Shopify & Woo<br>on autopilot'),
            'bullets' => [fc('use-cases.case1_bullet1', __('Cart abandonment recovery')), fc('use-cases.case1_bullet2', __('Order & shipping confirmations')), fc('use-cases.case1_bullet3', __('Catalog browsing in chat')), fc('use-cases.case1_bullet4', __('Razorpay / Stripe checkout'))],
            'uplift' => fc('use-cases.case1_uplift', '+29% ' . __('repeat')),
        ],
        [
            'roman' => 'ii',
            'cat' => fc('use-cases.case2_cat', __('D2C marketing')),
            'title' => fc('use-cases.case2_title', __('Replace your<br>email blast.')),
            'bullets' => [fc('use-cases.case2_bullet1', __('Click-to-WhatsApp Meta ads')), fc('use-cases.case2_bullet2', __('Product launches & restock')), fc('use-cases.case2_bullet3', __('VIP tiers & loyalty broadcasts')), fc('use-cases.case2_bullet4', __('Affiliate referral system'))],
            'uplift' => fc('use-cases.case2_uplift', '8.4× ROI'),
        ],
        [
            'roman' => 'iii',
            'cat' => fc('use-cases.case3_cat', __('Customer service')),
            'title' => fc('use-cases.case3_title', __('Slack-fast,<br>for WhatsApp.')),
            'bullets' => [fc('use-cases.case3_bullet1', __('Shared inbox & SLA timers')), fc('use-cases.case3_bullet2', __('Auto-reply on keywords')), fc('use-cases.case3_bullet3', __('Round-robin assignment')), fc('use-cases.case3_bullet4', __('Internal notes & @mentions'))],
            'uplift' => fc('use-cases.case3_uplift', '3× ' . __('faster')),
        ],
        [
            'roman' => 'iv',
            'cat' => fc('use-cases.case4_cat', __('Fintech & SaaS')),
            'title' => fc('use-cases.case4_title', __('Auth &<br>transactional flows.')),
            'bullets' => [fc('use-cases.case4_bullet1', __('OTP delivery & 2FA')), fc('use-cases.case4_bullet2', __('Payment alerts & receipts')), fc('use-cases.case4_bullet3', __('Trial & renewal reminders')), fc('use-cases.case4_bullet4', __('SOC 2 audit-grade logs'))],
            'uplift' => fc('use-cases.case4_uplift', '99.7% OTP'),
        ],
    ] as $card)
                <div class="hairline rounded-3xl bg-paper-50 p-6 hover:border-wa-deep transition">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="badge-num">{{ $card['roman'] }}</span>
                        <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ $card['cat'] }}</span>
                    </div>
                    <h3 class="serif text-[28px] leading-tight">{!! $card['title'] !!}</h3>
                    <ul class="mt-5 space-y-2 text-[12.5px] text-ink-700">
                        @foreach ($card['bullets'] as $b)
                            <li class="flex gap-2"><span class="text-wa-deep">›</span>{{ $b }}</li>
                        @endforeach
                    </ul>
                    <div class="mt-5 hairline-t pt-3 flex items-center justify-between">
                        <span class="mono text-[10px] text-ink-500">{{ __('avg uplift') }}</span>
                        <span class="serif text-[20px] text-wa-deep">{{ $card['uplift'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
