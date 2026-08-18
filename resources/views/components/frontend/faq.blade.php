@props([
    /** Eyebrow above the headline. */
    'kicker' => 'FAQ',
    /** Big serif headline (HTML allowed for italic spans). */
    'headline' => null,
    /** Subtitle paragraph below the headline. */
    'subtitle' => null,
    /**
     * Items: array of ['q' => string, 'a' => string, 'open' => bool?].
     * If empty, a sensible default set ships so the component is usable
     * by any page without configuration.
     */
    'items' => null,
    /**
     * Page namespace (e.g. 'features', 'pricing'). When set, this FAQ's
     * kicker/headline/subtitle AND its passed items become live-editable under
     * page-scoped keys (features.faq.*) — independent from the shared home set.
     */
    'scope' => null,
])

@php
    // Key namespace. Scoped pages (features/pricing) get their OWN editable keys;
    // unscoped (home) keeps the original shared faq.* keys so existing edits are
    // preserved.
    $ns = $scope ? ($scope . '.faq') : 'faq';

    // Page-passed items are live-editable TOO now, but only when a scope is set —
    // so their edits save under the page's own keys instead of clashing with the
    // shared set. Without a scope they render static (legacy behavior).
    $itemsAreCustom = $items !== null;
    $itemsEditable = ! $itemsAreCustom || (bool) $scope;

    $kicker = fcp("{$ns}.kicker_text", $kicker);
    $headline = fcp("{$ns}.headline", $headline ?? 'Frequently <span class="italic text-wa-deep">asked.</span>');
    $subtitle = fcp(
        "{$ns}.subtitle",
        $subtitle ?? __('Still unsure? Email :email — a real human replies inside 4 hours.', ['email' => brand_email('support')]),
    );

    // Wire page-passed items to scoped keys so the live editor can edit them.
    if ($itemsAreCustom && $scope) {
        $items = collect($items)->values()->map(function ($it, $i) use ($ns) {
            $n = $i + 1;
            return [
                'q'    => fcp("{$ns}.faq{$n}_q", $it['q'] ?? ''),
                'a'    => fcp("{$ns}.faq{$n}_a", $it['a'] ?? ''),
                'open' => $it['open'] ?? false,
            ];
        })->all();
    }

    $items = $items ?? [
        ['q' => fcp("{$ns}.faq1_q", __('Do I need a WhatsApp Business API account to start?')), 'a' => fcp("{$ns}.faq1_a", __('No — :brand can provision a WABA on your behalf via Meta\'s embedded signup. If you already have one, connect it directly. Twilio and Unofficial API QR-pair are also supported.', ['brand' => brand_name()])), 'open' => true],
        ['q' => fcp("{$ns}.faq2_q", __('How long does template approval take?')), 'a' => fcp("{$ns}.faq2_a", __('Median 18 minutes. We pre-validate so the rejection rate stays under 4%.'))],
        ['q' => fcp("{$ns}.faq3_q", __('Can I migrate from AiSensy, Wati, Interakt, Gupshup?')), 'a' => fcp("{$ns}.faq3_a", __('Yes — one-click importers for all four, plus free white-glove migration on Pro & Scale.'))],
        ['q' => fcp("{$ns}.faq4_q", __('What payment gateways are supported?')), 'a' => fcp("{$ns}.faq4_a", __('22 gateways including Razorpay, Stripe, PayPal, Paystack, Flutterwave, Instamojo.'))],
        ['q' => fcp("{$ns}.faq5_q", __('Where is my data stored?')), 'a' => fcp("{$ns}.faq5_a", __('SOC 2 Type II, ISO 27001, GDPR, HIPAA-eligible. EU, US, or India residency on Scale.'))],
    ];
@endphp

<section class="bg-paper-50 hairline-t hairline-b" data-fc-section="faq">
    <div class="max-w-[1080px] mx-auto px-4 sm:px-6 lg:px-7 py-28">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
            <div class="col-span-12 lg:col-span-4">
                <div class="badge-num">— <span data-fc="{{ fc_skey("{$ns}.kicker_text") }}">{{ $kicker }}</span></div>
                <h2 class="serif text-[40px] sm:text-[52px] lg:text-[64px] leading-[0.95] mt-4" data-fc="{{ fc_skey("{$ns}.headline") }}">
                    {!! $headline !!}</h2>
                <p class="text-[13px] text-ink-600 mt-3" data-fc="{{ fc_skey("{$ns}.subtitle") }}">{{ $subtitle }}</p>
            </div>

            <div class="col-span-12 lg:col-span-8 reveal" style="--d:120ms">
                <div class="hairline rounded-2xl bg-white divide-y divide-paper-200">
                    @foreach ($items as $i => $item)
                        @php $isOpen = $item['open'] ?? false; @endphp
                        <details class="details group p-5" @if ($isOpen) open @endif>
                            <summary class="flex items-center justify-between">
                                <span class="text-[15px] font-medium"
                                    @if ($itemsEditable) data-fc="{{ fc_skey($ns . '.faq' . $loop->iteration . '_q') }}" @endif>{{ $item['q'] }}</span>
                                <span
                                    class="w-7 h-7 rounded-full hairline flex items-center justify-center shrink-0 group-open:bg-wa-deep group-open:text-paper-0 transition">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M3 8h10" />
                                        <path d="M8 3v10" class="group-open:hidden" />
                                    </svg>
                                </span>
                            </summary>
                            <p class="text-[13px] text-ink-600 mt-3 leading-relaxed"
                                @if ($itemsEditable) data-fc="{{ fc_skey($ns . '.faq' . $loop->iteration . '_a') }}" @endif>
                                {{ $item['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
