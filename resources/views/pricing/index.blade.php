<x-layouts.user :title="__('Pricing')" nav-key="more" page="pricing-index">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-10 max-w-[1240px]">

        {{-- Hero --}}
        <div class="text-center mb-8">
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-3">{{ __('Pricing & plans') }}
            </div>
            <h1 class="font-serif text-[32px] sm:text-[40px] lg:text-[52px] leading-[1.05] tracking-[-0.01em]">{{ __('Pricing that') }} <span
                    class="italic text-wa-deep">{{ __('grows with you') }}</span></h1>
            <p class="text-[14px] text-ink-600 mt-3 max-w-xl mx-auto">
                {{ __('Pick the plan that fits today. Upgrade, downgrade or pause any time — no contracts, no surprises.') }}
            </p>

            {{-- billing toggle. Admin can hide the whole switcher via
 SystemSetting('pricing.yearly_toggle_enabled'). --}}
            @if ($yearlyEnabled)
                <div class="inline-flex items-center gap-1 bg-paper-0 border border-paper-200 rounded-full p-1 mt-6 shadow-card"
                    data-yearly-pct="{{ $yearlyDiscountPct }}">
                    <button id="bill-monthly"
                        class="px-4 py-1.5 rounded-full text-[12px] font-semibold text-ink-600">{{ __('Monthly') }}</button>
                    <button id="bill-yearly"
                        class="px-4 py-1.5 rounded-full text-[12px] font-semibold bg-wa-deep text-paper-0">{{ __('Yearly') }}
                        <span class="ml-1 text-[10px] font-mono opacity-90">save
                            {{ $yearlyDiscountPct }}%</span></button>
                </div>
            @endif
        </div>

        @if (session('success'))
            <div
                class="max-w-3xl mx-auto mb-6 bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div
                class="max-w-3xl mx-auto mb-6 bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-2 text-[12.5px] text-[#A1431F]">
                {{ session('error') }}</div>
        @endif

        {{-- Plan validity banner. plan_ends_at is set on checkout from the package's
 admin-configured duration (plan_duration × plan_unit); null = no expiry
 (free / lifetime / enterprise). --}}
        @if (!empty($planExpired))
            <div
                class="max-w-3xl mx-auto mb-6 bg-accent-coral/10 border border-accent-coral/40 rounded-xl px-4 py-3 text-[13px] text-[#A1431F] text-center">
                {{ __('Your plan expired on') }} <span
                    class="font-semibold">{{ optional($currentPlanEndsAt)->format('M j, Y') }}</span>.
                {{ __('Paid features are paused — renew below to restore them.') }}
            </div>
        @elseif (!empty($currentPlanEndsAt))
            <div
                class="max-w-3xl mx-auto mb-6 bg-wa-mint border border-wa-green/30 rounded-xl px-4 py-3 text-[13px] text-wa-deep text-center">
                @if (!empty($activeSubscription))
                    <span class="inline-flex items-center gap-1.5 justify-center">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M23 4v6h-6" />
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
                        </svg>
                        {{ __('Your plan auto-renews on') }} <span
                            class="font-semibold">{{ $currentPlanEndsAt->format('M j, Y') }}</span>
                        ({{ $currentPlanEndsAt->diffForHumans() }}).
                    </span>
                    <form method="POST" action="{{ route('account.subscription.cancel') }}" class="block mt-1.5"
                        onsubmit="return confirm('{{ __('Cancel auto-renew? Your plan stays active until it expires, then will not renew.') }}');">
                        @csrf
                        <button type="submit"
                            class="text-[12px] underline text-[#A1431F] hover:opacity-80">{{ __('Cancel auto-renew') }}</button>
                    </form>
                @else
                    {{ __('Your plan is active until') }} <span
                        class="font-semibold">{{ $currentPlanEndsAt->format('M j, Y') }}</span>
                    ({{ $currentPlanEndsAt->diffForHumans() }}).
                @endif
            </div>
        @elseif (!empty($trialEndsAt))
            <div
                class="max-w-3xl mx-auto mb-6 bg-[#FFF4E0] border border-[#E8C77A] rounded-xl px-4 py-3 text-[13px] text-[#7B5A14] text-center">
                {{ __('Free trial ends on') }} <span class="font-semibold">{{ $trialEndsAt->format('M j, Y') }}</span>
                ({{ $trialEndsAt->diffForHumans() }}). {{ __('Choose a plan to keep your workspace active.') }}
            </div>
        @endif

        @if ($packages->isEmpty())
            <div class="text-center py-20 text-ink-500">
                {{ __('No plans configured yet — ask your admin to create one at') }} <span
                    class="font-mono">/admin/packages/create</span>.</div>
        @else
            @php
                $cols = min(4, max(1, $packages->count()));
                $gridCls =
                    ['', 'md:grid-cols-1', 'md:grid-cols-2', 'md:grid-cols-3', 'md:grid-cols-2 lg:grid-cols-4'][
                        $cols
                    ] ?? 'md:grid-cols-2 lg:grid-cols-4';
            @endphp

            {{-- Plan cards. items-start so expanding one card's "Show all features"
 only grows THAT card — without it the grid stretches every card in
 the row to match the tallest. --}}
            <div class="grid grid-cols-1 {{ $gridCls }} gap-4 items-start">
                @foreach ($packages as $p)
                    @php
                        $isFree = $p->free || (float) $p->plan_amount === 0.0;
                        $isCustom = $p->is_custom_quote;
                        $highlighted = $p->is_highlighted;
                        // Honour the discounted offer price for the DISPLAYED price
                        // (the up/downgrade comparison below intentionally stays on
                        // plan_amount as the stable tier ranking).
                        // Show each plan in its OWN currency (what the admin priced it
                        // in) — do NOT convert to the viewer's workspace currency. A
                        // deliberate plan price (e.g. 120000 IDR) is charged in its set
                        // currency, so converting it only lost the exact price AND, when
                        // the workspace currency differed from the plan's, printed a $
                        // amount for an IDR plan. Display now matches checkout exactly.
                        $planCurrency = $p->currency ?: $currency;
                        $amount = $p->chargeableAmount();
                        // Only multiply a price quoted PER MONTH. A package the admin
                        // authored with plan_unit = years already holds the yearly
                        // figure, so x12 would advertise ~9.6x — and disagree with
                        // what checkout actually charges.
                        $yearlyAmount = $p->isNativelyYearly()
                            ? round($amount, 2)
                            : round($amount * 12 * (1 - $yearlyDiscountPct / 100), 2);
                        $monthlyHuman = $isCustom
                            ? 'Custom'
                            : ($isFree
                                ? 'Free'
                                : \App\Support\FormatSettings::formatIn($amount, $planCurrency));
                        $yearlyHuman = $isCustom
                            ? 'Custom'
                            : ($isFree
                                ? 'Free'
                                : \App\Support\FormatSettings::formatIn($yearlyAmount / 12, $planCurrency));
                        // Set only on the in-app /account/plans render (PricingController).
                        $isCurrent = ($currentPackageId ?? null) && (int) $p->id === (int) $currentPackageId;
                        // Upgrade / downgrade gating: you can move UP to a pricier plan, but
                        // the current plan + any cheaper paid plan are not directly buyable.
                        $curAmt = $currentPlanAmount ?? null; // null = no active plan (free/trial)
                        $isDowngrade =
                            !$isCurrent &&
                            !$isCustom &&
                            !$isFree &&
                            $curAmt !== null &&
                            (float) $p->plan_amount < (float) $curAmt;
                        $isUpgrade =
                            !$isCurrent &&
                            !$isCustom &&
                            !$isFree &&
                            $curAmt !== null &&
                            (float) $p->plan_amount > (float) $curAmt;
                    @endphp

                    @if ($highlighted)
                        {{-- Highlighted dark card (mirrors prototype's Pro plan) --}}
                        <div class="bg-wa-deep text-paper-0 rounded-2xl p-6 shadow-soft flex flex-col relative">
                            <span
                                class="absolute top-3 right-3 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-0/15 text-[10px] font-mono">
                                <span class="w-1.5 h-1.5 rounded-full bg-wa-green animate-pulse"></span>MOST POPULAR
                            </span>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/70">
                                {{ $p->pname }}</div>
                            <div class="mt-3 flex items-baseline gap-1.5">
                                <span class="font-serif text-[44px] leading-none price"
                                    data-monthly="{!! $monthlyHuman !!}"
                                    data-yearly="{!! $yearlyHuman !!}">{!! $yearlyEnabled ? $yearlyHuman : $monthlyHuman !!}</span>
                                @if (!$isFree && !$isCustom)
                                    <span class="text-[12px] text-paper-0/70">/ month</span>
                                @endif
                            </div>
                            @if ($p->subtitle)
                                <p class="text-[12px] text-paper-0/80 mt-2">{{ $p->subtitle }}</p>
                            @endif
                            @if ($isCurrent)
                                <span
                                    class="mt-5 px-4 py-2 rounded-full bg-paper-0/15 text-paper-0 text-center text-[12px] font-semibold inline-flex items-center justify-center gap-1.5 cursor-default"><svg
                                        viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M3 8l3 3 7-7" />
                                    </svg>{{ __('Current plan') }}</span>
                            @elseif ($isCustom)
                                <a href="{{ url('/support') }}"
                                    class="mt-5 px-4 py-2 rounded-full bg-paper-0 text-wa-deep hover:bg-paper-50 text-center text-[12px] font-semibold">{{ $p->cta_label ?: 'Talk to sales' }}</a>
                            @elseif ($isFree)
                                <a href="{{ url('/team-inbox') }}"
                                    class="mt-5 px-4 py-2 rounded-full bg-paper-0 text-wa-deep hover:bg-paper-50 text-center text-[12px] font-semibold">{{ $p->cta_label ?: 'Continue free' }}</a>
                            @elseif ($isDowngrade)
                                <span
                                    title="{{ __('Downgrades are not available online. Contact support to move to a lower plan.') }}"
                                    class="mt-5 px-4 py-2 rounded-full bg-paper-0/10 text-paper-0/50 border border-paper-0/20 text-center text-[12px] font-semibold inline-flex items-center justify-center gap-1.5 cursor-not-allowed">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="1.8">
                                        <rect x="3.5" y="7" width="9" height="6" rx="1.2" />
                                        <path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2" />
                                    </svg>{{ __('Downgrade unavailable') }}
                                </span>
                            @else
                                <a href="{{ route('user.checkout.show', $p->id) }}{{ $yearlyEnabled ? '?period=yearly' : '' }}"
                                    data-checkout-base="{{ route('user.checkout.show', $p->id) }}"
                                    class="plan-cta mt-5 px-4 py-2 rounded-full bg-paper-0 text-wa-deep hover:bg-paper-50 text-center text-[12px] font-semibold">{{ $isUpgrade ? __('Upgrade to :plan', ['plan' => $p->pname]) : ($p->cta_label ?: 'Choose ' . $p->pname) }}</a>
                            @endif
                            <div class="mt-5 flex-1">
                                @include('pricing._features', [
                                    'p' => $p,
                                    'iconCls' => 'text-wa-green',
                                    'textCls' => 'text-paper-0/90',
                                    'moreCls' => 'text-wa-green',
                                ])
                            </div>
                        </div>
                    @else
                        {{-- Plain card --}}
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card flex flex-col">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ $p->pname }}</div>
                            <div class="mt-3 flex items-baseline gap-1.5">
                                <span class="font-serif text-[44px] leading-none price"
                                    data-monthly="{!! $monthlyHuman !!}"
                                    data-yearly="{!! $yearlyHuman !!}">{!! $yearlyEnabled ? $yearlyHuman : $monthlyHuman !!}</span>
                                @if (!$isFree && !$isCustom)
                                    <span class="text-[12px] text-ink-500">/ month</span>
                                @endif
                            </div>
                            @if ($p->subtitle)
                                <p class="text-[12px] text-ink-500 mt-2">{{ $p->subtitle }}</p>
                            @endif
                            @if ($isCurrent)
                                <span
                                    class="mt-5 px-4 py-2 rounded-full bg-wa-mint text-wa-deep border border-wa-green/40 text-center text-[12px] font-semibold inline-flex items-center justify-center gap-1.5 cursor-default"><svg
                                        viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M3 8l3 3 7-7" />
                                    </svg>{{ __('Current plan') }}</span>
                            @elseif ($isCustom)
                                <a href="{{ url('/support') }}"
                                    class="mt-5 px-4 py-2 rounded-full bg-paper-50 border border-paper-200 hover:bg-paper-100 text-center text-[12px] font-semibold">{{ $p->cta_label ?: 'Talk to sales' }}</a>
                            @elseif ($isFree)
                                <a href="{{ url('/team-inbox') }}"
                                    class="mt-5 px-4 py-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-center text-[12px] font-semibold">{{ $p->cta_label ?: 'Continue free' }}</a>
                            @elseif ($isDowngrade)
                                <span
                                    title="{{ __('Downgrades are not available online. Contact support to move to a lower plan.') }}"
                                    class="mt-5 px-4 py-2 rounded-full bg-paper-100 text-ink-400 border border-paper-200 text-center text-[12px] font-semibold inline-flex items-center justify-center gap-1.5 cursor-not-allowed">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="1.8">
                                        <rect x="3.5" y="7" width="9" height="6" rx="1.2" />
                                        <path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2" />
                                    </svg>{{ __('Downgrade unavailable') }}
                                </span>
                            @else
                                <a href="{{ route('user.checkout.show', $p->id) }}{{ $yearlyEnabled ? '?period=yearly' : '' }}"
                                    data-checkout-base="{{ route('user.checkout.show', $p->id) }}"
                                    class="plan-cta mt-5 px-4 py-2 rounded-full bg-paper-50 border border-paper-200 hover:bg-paper-100 text-center text-[12px] font-semibold">{{ $isUpgrade ? __('Upgrade to :plan', ['plan' => $p->pname]) : ($p->cta_label ?: 'Choose ' . $p->pname) }}</a>
                            @endif
                            <div class="mt-5 flex-1">
                                @include('pricing._features', [
                                    'p' => $p,
                                    'iconCls' => 'text-wa-deep',
                                    'textCls' => 'text-ink-700',
                                    'moreCls' => 'text-wa-deep',
                                ])
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- ADD-ONS — à-la-carte feature packs bought ON TOP of the active
                 plan. Their toggles/limits merge in via effectiveLimit(). --}}
            @if (!empty($addons) && $addons->count())
                @php $addonCatalog = \App\Models\Package::featureCatalog(); @endphp
                <div class="mt-10">
                    <div class="flex items-end justify-between mb-3">
                        <h2 class="font-serif text-[22px] leading-tight">{{ __('Add-ons') }}</h2>
                        <span class="font-mono text-[10.5px] text-ink-500">{{ __('Unlock extra features without changing your plan') }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($addons as $a)
                            @php
                                // Add-ons also display in their own set currency (no
                                // conversion to the viewer's workspace currency).
                                $aAmount = $a->chargeableAmount();
                                $aPrice  = ($a->free || (float) $a->plan_amount === 0.0) ? __('Free') : \App\Support\FormatSettings::formatIn($aAmount, $a->currency ?: $currency);
                                $aPeriod = $a->lifetime ? __('one-time') : ('/ ' . ($a->plan_unit ?: 'month'));
                                // What this add-on grants — capabilities turned on + limits it adds.
                                $grants = [];
                                foreach ($addonCatalog['capabilities'] as $k => $l) { if ($a->grantsFeature($k)) $grants[] = $l; }
                                foreach ($addonCatalog['limits'] as $k => $l) { $d = $a->grantLimit($k); if ($d !== null) $grants[] = ($d === -1 ? __('Unlimited') . ' ' . $l : '+' . $d . ' ' . $l); }
                            @endphp
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card flex flex-col">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Add-on') }}</div>
                                <div class="font-serif text-[18px] mt-1">{{ $a->pname }}</div>
                                @if ($a->subtitle)<div class="text-[12px] text-ink-600 mt-0.5">{{ $a->subtitle }}</div>@endif
                                <div class="mt-3 flex items-baseline gap-1.5">
                                    <span class="font-serif text-[28px] leading-none">{{ $aPrice }}</span>
                                    <span class="text-[11px] text-ink-500 font-mono">{{ $aPeriod }}</span>
                                </div>
                                @if (!empty($grants))
                                    <ul class="mt-3 space-y-1.5 text-[12px] text-ink-700">
                                        @foreach (array_slice($grants, 0, 6) as $g)
                                            <li class="flex items-start gap-2">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 mt-0.5 text-wa-green shrink-0" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8.5l3.5 3.5L13 5" /></svg>
                                                <span>{{ $g }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                <a href="{{ route('user.checkout.show', $a->id) }}"
                                    class="mt-5 px-4 py-2 rounded-full bg-wa-deep text-paper-0 hover:bg-wa-teal text-center text-[12px] font-semibold">{{ $a->cta_label ?: __('Buy add-on') }}</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Comparison table (only when 2+ plans, otherwise it's noise) --}}
            @if ($packages->count() >= 2)
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden mt-10">
                    <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                        <h2 class="font-serif text-[22px] leading-tight">{{ __('Compare features') }}</h2>
                        <span
                            class="font-mono text-[10.5px] text-ink-500">{{ __('All plans include unlimited campaigns & templates') }}</span>
                    </div>
                    @php
                        // Every feature, straight from the single catalog — numeric limits
                        // first, then capabilities. A row is hidden only if NO plan has it,
                        // so the table never shows an all-empty line.
                        $catalog = \App\Models\Package::featureCatalog();
                        $compareRows = [];
                        foreach ($catalog['limits'] as $k => $l) {
                            if ($packages->contains(fn($p) => ($p->{$k} ?? null) !== null)) {
                                $compareRows[] = ['key' => $k, 'label' => $l];
                            }
                        }
                        foreach ($catalog['capabilities'] as $k => $l) {
                            if ($packages->contains(fn($p) => (bool) ($p->{$k} ?? false))) {
                                $compareRows[] = ['key' => $k, 'label' => $l, 'boolean' => true];
                            }
                        }
                        $highlightedId = optional($packages->firstWhere('is_highlighted', true))->id;
                        $cmpCollapse = count($compareRows) > 12; // collapse long tables behind "Show all"
                    @endphp
                    <input type="checkbox" id="cmp-toggle" class="peer sr-only" aria-hidden="true">
                    <div
                        class="overflow-x-auto {{ $cmpCollapse ? 'max-h-[460px] peer-checked:max-h-none transition-[max-height] duration-500' : '' }}">
                        <table class="w-full text-[12.5px]">
                            <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                                <tr>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-[280px]">
                                        {{ __('Feature') }}</th>
                                    @foreach ($packages as $p)
                                        <th
                                            class="text-center font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-2.5 {{ $p->id === $highlightedId ? 'bg-wa-mint/30 text-wa-deep' : '' }}">
                                            {{ $p->pname }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-paper-200">
                                @foreach ($compareRows as $row)
                                    <tr>
                                        <td class="px-4 py-2.5 font-medium">{{ $row['label'] }}</td>
                                        @foreach ($packages as $p)
                                            @php
                                                $val = $p->{$row['key']} ?? null;
                                                $isHi = $p->id === $highlightedId;
                                            @endphp
                                            <td
                                                class="text-center px-3 py-2.5 {{ $isHi ? 'bg-wa-mint/20' : '' }} font-mono">
                                                @if (!empty($row['boolean']))
                                                    @if ($val)
                                                        <svg viewBox="0 0 16 16"
                                                            class="w-3.5 h-3.5 inline text-wa-deep" fill="none"
                                                            stroke="currentColor" stroke-width="2">
                                                            <path d="M3 8l3 3 7-7" />
                                                        </svg>
                                                    @else
                                                        <span class="text-ink-500">—</span>
                                                    @endif
                                                @else
                                                    @if ($val === null)
                                                        <span class="text-ink-500">—</span>
                                                    @elseif ((int) $val === 0)
                                                        <span class="text-ink-500">{{ __('unlimited') }}</span>
                                                    @else
                                                        {{ $val >= 1000 ? number_format($val / 1000, $val >= 100000 ? 0 : 1) . 'k' : number_format($val) }}
                                                    @endif
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($cmpCollapse)
                        <label for="cmp-toggle"
                            class="peer-checked:hidden flex items-center justify-center gap-1.5 cursor-pointer py-3 border-t border-paper-200 text-[12px] font-semibold text-wa-deep hover:bg-paper-50">
                            {{ __('Show all :n features', ['n' => count($compareRows)]) }}
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M4 6l4 4 4-4" />
                            </svg>
                        </label>
                        <label for="cmp-toggle"
                            class="hidden peer-checked:flex items-center justify-center gap-1.5 cursor-pointer py-3 border-t border-paper-200 text-[12px] font-semibold text-wa-deep hover:bg-paper-50">
                            {{ __('Show less') }}
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M4 10l4-4 4 4" />
                            </svg>
                        </label>
                    @endif
                </div>
            @endif

            {{-- FAQ — admin-editable rows from pricing_faqs. Falls back gracefully
 to nothing when the table is empty (CheckoutDefaultsSeeder
 seeds a sensible set on a fresh install). --}}
            @if ($faqs->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-10">
                    @foreach ($faqs as $i => $faq)
                        <details class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card group"
                            @if ($i === 0) open @endif>
                            <summary class="cursor-pointer font-serif text-[16px] flex items-center justify-between">
                                <span>{{ $faq->question }}</span>
                                <svg viewBox="0 0 16 16"
                                    class="w-3 h-3 text-ink-500 transition-transform group-open:rotate-180"
                                    fill="none" stroke="currentColor" stroke-width="1.6">
                                    <path d="M4 6l4 4 4-4" />
                                </svg>
                            </summary>
                            <p class="mt-2 text-[12.5px] text-ink-600 leading-relaxed whitespace-pre-line">
                                {{ $faq->answer }}</p>
                        </details>
                    @endforeach
                </div>
            @endif
        @endif

    </main>

    {{-- The billing switcher lives in resources/js/charts/pricing-index.js,
         loaded via the page="pricing-index" key on the layout above. An inline
         copy used to sit here too, so both bound click handlers and every
         toggle ran twice. --}}

</x-layouts.user>
