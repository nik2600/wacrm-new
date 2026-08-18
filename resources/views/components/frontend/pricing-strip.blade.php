@props([
    /** Show the section's own kicker + headline. Pass false to embed inside a page that already has its own. */
    'showHeader' => true,
    /**
     * Plans: each is ['name', 'tagline', 'price', 'period', 'overage', 'cta_label', 'cta_href',
     * 'highlighted' (bool), 'volume' (array), 'features' (array of ['label', 'included' bool]),
     * 'support' (array), 'plan_num' (string)].
     * If null, the prototype's Starter / Pro / Scale shape ships so the
     * component renders out of the box on the public landing surface.
     * For the auth dashboard /pricing page, pass real $packages data instead.
     */
    'plans' => null,
])

@php
    // Hardcoded prototype tiers — used only as a fallback when no plans were
    // passed AND the admin hasn't created any packages yet.
    $__fcDefaults = [
        [
            'plan_num' => fc('pricing-strip.tier1_num', 'Plan · 01'),
            'name' => fc('pricing-strip.tier1_name', 'Starter'),
            'badge' => fc('pricing-strip.tier1_badge', 'free'),
            'tagline' => fc(
                'pricing-strip.tier1_tagline',
                __('Founders testing the waters. Live in 4 minutes — no card required.'),
            ),
            'price' => fc('pricing-strip.tier1_price', '$0'),
            'period' => fc('pricing-strip.tier1_period', __('/month · forever')),
            'overage' => fc('pricing-strip.tier1_overage', __('+ $0.005 / msg after 1k')),
            'cta_label' => fc('pricing-strip.tier1_cta_label', __('Start free →')),
            'cta_href' => Route::has('register') ? route('register') : url('/'),
            'highlighted' => false,
            'volume' => [
                ['label' => fc('pricing-strip.tier1_volume1', __('1 connected device')), 'included' => true],
                ['label' => fc('pricing-strip.tier1_volume2', __('1,000 conversations / mo')), 'included' => true],
                ['label' => fc('pricing-strip.tier1_volume3', __('2 agent seats')), 'included' => true],
            ],
            'features' => [
                ['label' => fc('pricing-strip.tier1_feature1', __('10 templates · basic flows')), 'included' => true],
                ['label' => fc('pricing-strip.tier1_feature2', __('Shared inbox · 1 channel')), 'included' => true],
                ['label' => fc('pricing-strip.tier1_feature3', __('Webhooks · public API')), 'included' => true],
                ['label' => fc('pricing-strip.tier1_feature4', __('AI Copilot')), 'included' => false],
                ['label' => fc('pricing-strip.tier1_feature5', __('A/B variants')), 'included' => false],
            ],
            'support' => [
                ['label' => fc('pricing-strip.tier1_support1', __('Community · docs')), 'included' => true],
                ['label' => fc('pricing-strip.tier1_support2', __('Email & chat')), 'included' => false],
            ],
        ],
        [
            'plan_num' => fc('pricing-strip.tier2_num', 'Plan · 02'),
            'name' => fc('pricing-strip.tier2_name', 'Pro'),
            'badge' => fc('pricing-strip.tier2_badge', __('Most picked')),
            'tagline' => fc(
                'pricing-strip.tier2_tagline',
                __('Teams growing on WhatsApp. Everything unlocked, one bill.'),
            ),
            'price' => fc('pricing-strip.tier2_price', '$49'),
            'period' => fc('pricing-strip.tier2_period', __('/month')),
            'overage' => fc('pricing-strip.tier2_overage', __('+ $0.003 / msg after 10k')),
            'cta_label' => fc('pricing-strip.tier2_cta_label', __('Start 14-day trial →')),
            'cta_href' => Route::has('register') ? route('register') : url('/'),
            'highlighted' => true,
            'volume' => [
                ['label' => fc('pricing-strip.tier2_volume1', __('3 connected devices')), 'included' => true],
                ['label' => fc('pricing-strip.tier2_volume2', __('Unlimited conversations')), 'included' => true],
                ['label' => fc('pricing-strip.tier2_volume3', __('Unlimited agent seats')), 'included' => true],
            ],
            'features' => [
                ['label' => fc('pricing-strip.tier2_feature1', __('Unlimited templates · flows')), 'included' => true],
                [
                    'label' => fc('pricing-strip.tier2_feature2', __('AI Copilot · all integrations')),
                    'included' => true,
                ],
                ['label' => fc('pricing-strip.tier2_feature3', __('A/B variants · auto-winner')), 'included' => true],
                ['label' => fc('pricing-strip.tier2_feature4', __('Multi-channel inbox')), 'included' => true],
                ['label' => fc('pricing-strip.tier2_feature5', __('Payments · 10 gateways')), 'included' => true],
            ],
            'support' => [
                ['label' => fc('pricing-strip.tier2_support1', __('Email + chat · 4h SLA')), 'included' => true],
                ['label' => fc('pricing-strip.tier2_support2', __('Onboarding · white-glove')), 'included' => true],
            ],
        ],
        [
            'plan_num' => fc('pricing-strip.tier3_num', 'Plan · 03'),
            'name' => fc('pricing-strip.tier3_name', 'Scale'),
            'badge' => fc('pricing-strip.tier3_badge', __('custom')),
            'tagline' => fc(
                'pricing-strip.tier3_tagline',
                __('High-volume teams. Regulated industries. Custom contracts.'),
            ),
            'price' => fc('pricing-strip.tier3_price', '$299'),
            'period' => fc('pricing-strip.tier3_period', __('/month · from')),
            'overage' => fc('pricing-strip.tier3_overage', __('volume discount available')),
            'cta_label' => fc('pricing-strip.tier3_cta_label', __('Talk to sales →')),
            'cta_href' => '#',
            'highlighted' => false,
            'volume' => [
                ['label' => fc('pricing-strip.tier3_volume1', __('Unlimited devices')), 'included' => true],
                ['label' => fc('pricing-strip.tier3_volume2', __('Unlimited everything')), 'included' => true],
                ['label' => fc('pricing-strip.tier3_volume3', __('Custom rate limits')), 'included' => true],
            ],
            'features' => [
                ['label' => fc('pricing-strip.tier3_feature1', __('SAML SSO · SCIM')), 'included' => true],
                ['label' => fc('pricing-strip.tier3_feature2', __('7-year audit retention')), 'included' => true],
                ['label' => fc('pricing-strip.tier3_feature3', __('EU · US · IN data residency')), 'included' => true],
                ['label' => fc('pricing-strip.tier3_feature4', __('Custom DPA · MSA')), 'included' => true],
                ['label' => fc('pricing-strip.tier3_feature5', __('HIPAA · PCI · GDPR')), 'included' => true],
            ],
            'support' => [
                ['label' => fc('pricing-strip.tier3_support1', __('Dedicated CSM · Slack')), 'included' => true],
                ['label' => fc('pricing-strip.tier3_support2', __('99.95% uptime SLA')), 'included' => true],
            ],
        ],
    ];

    // Real, admin-managed plans drive the cards. Only fall back to the
    // prototype tiers when no $plans prop was passed and there are no packages.
    if ($plans === null) {
        $__dbCards = \App\Models\Package::publicPricingCards();
        $plans = count($__dbCards) ? $__dbCards : $__fcDefaults;
    }

    // Yearly/monthly toggle config — mirrors /account/plans so the public page
    // and the client dashboard show identical prices for the same period. Only
    // appears when the admin enabled it AND a plan carries a distinct yearly price.
    $fcYearlyEnabled = (bool) \App\Models\SystemSetting::get('pricing.yearly_toggle_enabled', true);
    $fcYearlyPct     = (int) \App\Models\SystemSetting::get('pricing.yearly_discount_pct', 20);
    $fcHasYearly     = $fcYearlyEnabled && collect($plans)->contains(
        fn ($p) => !empty($p['price_yearly']) && ($p['price_yearly'] !== ($p['price'] ?? null)),
    );
@endphp

<section class="bg-white" data-fc-section="pricing-strip">
    <div class="max-w-[1360px] mx-auto px-4 sm:px-7 py-28">

        @if ($showHeader)
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
                <div class="col-span-12 lg:col-span-2">
                    <div class="feature-num text-[80px] sm:text-[140px]">10</div>
                </div>
                <div class="col-span-12 lg:col-span-3 flex flex-col justify-end pb-3">
                    <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">— <span
                            data-fc="pricing-strip.eyebrow_text">{{ fc('pricing-strip.eyebrow_text', __('Pricing')) }}</span>
                    </div>
                    <div class="text-[13px] font-semibold" data-fc="pricing-strip.label">
                        {{ fc('pricing-strip.label', __('Pay for conversations, not seats')) }}</div>
                </div>
                <div class="col-span-12 lg:col-span-7 flex flex-wrap items-end lg:justify-end pb-3 gap-3 text-[11px] mono text-ink-500">
                    <span
                        data-fc="pricing-strip.meta1">{{ fc('pricing-strip.meta1', __('14-day free trial')) }}</span><span
                        class="text-ink-400">·</span>
                    <span
                        data-fc="pricing-strip.meta2">{{ fc('pricing-strip.meta2', __('no credit card')) }}</span><span
                        class="text-ink-400">·</span>
                    <span class="text-wa-deep"
                        data-fc="pricing-strip.meta3">{{ fc('pricing-strip.meta3', __('cancel anytime')) }}</span>
                </div>
            </div>

            <h2 class="serif text-[44px] sm:text-[64px] lg:text-[88px] leading-[0.92] tracking-[-0.02em] mb-12 reveal"
                data-fc="pricing-strip.headline">
                {!! fc(
                    'pricing-strip.headline',
                    __('Simple, honest,') . '<br>' . __('and') . ' <span class="italic text-wa-deep">' . __('flat.') . '</span>',
                ) !!}
            </h2>
        @endif

        {{-- Monthly / Yearly billing toggle — same behaviour + default (Yearly)
             as /account/plans, so both pages agree on the displayed price. --}}
        @if ($fcHasYearly)
           <div class="flex justify-center mb-10" data-fc-billing>
    <div class="inline-flex items-center gap-1 bg-paper-50 border border-paper-200 rounded-full p-1">
        <button
            type="button"
            data-fc-bill="monthly"
            class="px-4 py-1.5 rounded-full text-[12px] font-semibold bg-wa-deep text-paper-0">
            {{ __('Monthly') }}
        </button>

        <button
            type="button"
            data-fc-bill="yearly"
            class="px-4 py-1.5 rounded-full text-[12px] font-semibold text-ink-600">
            {{ __('Yearly') }}
            <span class="ml-1 text-[10px] font-mono opacity-90">
                {{ __('save') }} {{ $fcYearlyPct }}%
            </span>
        </button>
    </div>
</div>
        @endif

        {{-- editorial tall pricing cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 reveal" style="--d:120ms">
            @foreach ($plans as $plan)
                @php $hl = $plan['highlighted'] ?? false; @endphp
                <div
                    class="col-span-12 lg:col-span-4 rounded-3xl p-8 flex flex-col {{ $hl ? 'bg-wa-deep text-paper-0 relative overflow-hidden lg:-mt-4 lg:mb-4 shadow-[0_40px_80px_-40px_rgba(7,94,84,0.6)]' : 'hairline bg-white' }}">
                    @if ($hl)
                        <div class="absolute inset-0 dot-pattern opacity-15"></div>
                        <div
                            class="absolute -top-32 -right-32 w-[300px] h-[300px] rounded-full bg-wa-green/20 blur-bub">
                        </div>
                    @endif

                    <div class="relative flex flex-col flex-1">
                        {{-- header --}}
                        <div class="{{ $hl ? 'border-b border-paper-0/15' : 'hairline-b' }} pb-5">
                            <div class="flex items-center justify-between mb-3">
                                <div
                                    class="mono text-[10px] uppercase tracking-[0.22em] {{ $hl ? 'text-paper-0/60' : 'text-ink-500' }}">
                                    — {{ $plan['plan_num'] }}</div>
                                @if ($hl)
                                    <span class="pill bg-wa-green text-ink-900 text-[10px]"><span
                                            class="w-1.5 h-1.5 rounded-full bg-wa-deep"></span>{{ $plan['badge'] }}</span>
                                @else
                                    <span class="badge-num">{{ $plan['badge'] }}</span>
                                @endif
                            </div>
                            <h3 class="serif {{ $hl ? 'text-[44px]' : 'text-[40px]' }} leading-none mt-3">
                                {{ $plan['name'] }}</h3>
                            <p
                                class="text-[12.5px] {{ $hl ? 'text-paper-0/75' : 'text-ink-600' }} mt-3 leading-relaxed">
                                {{ $plan['tagline'] }}</p>
                        </div>

                        {{-- price --}}
                        <div class="{{ $hl ? 'border-b border-paper-0/15' : 'hairline-b' }} py-6">
                            <div class="flex items-baseline gap-2">
                                <span
                                    class="serif {{ $hl ? 'text-[76px]' : 'text-[72px]' }} leading-none tabular fc-price"
                                    data-monthly="{{ $plan['price'] }}" data-yearly="{{ $plan['price_yearly'] ?? $plan['price'] }}">{{ $fcHasYearly ? ($plan['price_yearly'] ?? $plan['price']) : $plan['price'] }}</span>
                                <span
                                    class="mono text-[11px] {{ $hl ? 'text-paper-0/60' : 'text-ink-500' }}">{{ $plan['period'] }}</span>
                            </div>
                            @if (!empty($plan['overage']))
                                <div class="mt-3">
                                    <span
                                        class="pill {{ $hl ? 'bg-paper-0/10 text-paper-0 border border-paper-0/15' : 'bg-paper-100 text-ink-700' }} text-[10px]">{{ $plan['overage'] }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- categorized features --}}
                        <div class="py-5 space-y-5 flex-1">
                            @foreach (['volume' => __('Volume'), 'features' => __('Features'), 'support' => __('Support')] as $key => $heading)
                                @if (!empty($plan[$key]))
                                    <div>
                                        <div
                                            class="mono text-[9.5px] uppercase tracking-widest {{ $hl ? 'text-wa-green' : 'text-wa-deep' }} mb-2">
                                            {{ $heading }}</div>
                                        <ul
                                            class="space-y-1.5 text-[12.5px] {{ $hl ? 'text-paper-0/90' : 'text-ink-700' }}">
                                            @foreach ($plan[$key] as $row)
                                                @if ($row['included'])
                                                    <li class="flex gap-2"><span
                                                            class="text-wa-green mt-0.5">✓</span>{{ $row['label'] }}
                                                    </li>
                                                @else
                                                    <li class="flex gap-2 text-ink-400"><span
                                                            class="mt-0.5">—</span>{{ $row['label'] }}</li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        {{-- cta --}}
                        <a href="{{ $plan['cta_href'] }}" data-cta-base="{{ $plan['cta_href'] }}"
                            class="fc-cta block text-center w-full rounded-full py-3 text-[13px] font-semibold transition {{ $hl ? 'bg-wa-green text-ink-900 hover:bg-[#1ec05a] glow-green' : 'hairline hover:bg-paper-50 font-medium' }}">
                            {{ $plan['cta_label'] }}
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($showHeader)
            <div class="mt-10 hairline rounded-2xl bg-paper-50 px-6 py-5 grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                <div class="col-span-12 lg:col-span-3 mono text-[10px] uppercase tracking-[0.22em] text-ink-500">— <span
                        data-fc="pricing-strip.honest_label_text">{{ fc('pricing-strip.honest_label_text', __('Honest about pricing')) }}</span>
                </div>
                <div class="col-span-12 lg:col-span-9 grid grid-cols-2 lg:grid-cols-4 gap-4 text-[12px]">
                    <div class="flex gap-2 items-start"><span class="text-wa-deep">✓</span><span class="text-ink-700"
                            data-fc="pricing-strip.honest1">{!! fc(
                                'pricing-strip.honest1',
                                '<b>' . __('Pay for messages') . '</b><br><span class="text-ink-500">' . __('Not per seat. Ever.') . '</span>',
                            ) !!}</span></div>
                    <div class="flex gap-2 items-start"><span class="text-wa-deep">✓</span><span class="text-ink-700"
                            data-fc="pricing-strip.honest2">{!! fc(
                                'pricing-strip.honest2',
                                '<b>' . __('One invoice') . '</b><br><span class="text-ink-500">' . __('All 12 products bundled.') . '</span>',
                            ) !!}</span></div>
                    <div class="flex gap-2 items-start"><span class="text-wa-deep">✓</span><span class="text-ink-700"
                            data-fc="pricing-strip.honest3">{!! fc(
                                'pricing-strip.honest3',
                                '<b>' .
                                    __('Annual = 20% off') .
                                    '</b><br><span class="text-ink-500">' .
                                    __('Pay yearly, save 2.4 months.') .
                                    '</span>',
                            ) !!}</span></div>
                    <div class="flex gap-2 items-start"><span class="text-wa-deep">✓</span><span class="text-ink-700"
                            data-fc="pricing-strip.honest4">{!! fc(
                                'pricing-strip.honest4',
                                '<b>' .
                                    __('Migration · free') .
                                    '</b><br><span class="text-ink-500">' .
                                    __('We move you off AiSensy / Wati.') .
                                    '</span>',
                            ) !!}</span></div>
                </div>
            </div>
        @endif
    </div>

    {{-- Price swap — identical logic to the /account/plans toggle (defaults to
         Yearly on load, matching the dashboard). Self-contained, runs once. --}}
    @if ($fcHasYearly)
        <script>
            (function () {
                const wrap = document.querySelector('[data-fc-billing]');
                if (!wrap) return;
                const btns = wrap.querySelectorAll('[data-fc-bill]');
                // Carry the chosen billing period onto every checkout CTA so the
                // PUBLIC pricing page hands ?period=yearly to /checkout — exactly
                // like /account/plans. Without this the button always went to the
                // monthly checkout even with Yearly selected. Sales / anchor
                // CTAs ('#', register) are left untouched.
                const setCta = (period) => {
                    document.querySelectorAll('.fc-cta').forEach((a) => {
                        const base = a.dataset.ctaBase || a.getAttribute('href') || '';
                        if (!base || base.charAt(0) === '#') return;
                        try {
                            const u = new URL(base, window.location.origin);
                            if (period === 'yearly') u.searchParams.set('period', 'yearly');
                            else u.searchParams.delete('period');
                            a.setAttribute('href', u.pathname + u.search + u.hash);
                        } catch (e) {
                            a.setAttribute('href', period === 'yearly'
                                ? base + (base.indexOf('?') === -1 ? '?' : '&') + 'period=yearly'
                                : base);
                        }
                    });
                };
                const apply = (period) => {
                    document.querySelectorAll('.fc-price').forEach((p) => {
                        if (p.dataset[period]) p.textContent = p.dataset[period];
                    });
                    btns.forEach((b) => {
                        const on = b.dataset.fcBill === period;
                        b.classList.toggle('bg-wa-deep', on);
                        b.classList.toggle('text-paper-0', on);
                        b.classList.toggle('text-ink-600', !on);
                    });
                    setCta(period);
                };
                btns.forEach((b) => b.addEventListener('click', () => apply(b.dataset.fcBill)));
                // Default to Yearly on load — matches the yearly price shown by
                // default + the /account/plans default — so the CTA already
                // carries ?period=yearly before the visitor clicks anything.
                apply('yearly');
            })();
        </script>
    @endif
</section>
