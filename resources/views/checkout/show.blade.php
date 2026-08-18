<x-layouts.user :title="__('Checkout')" nav-key="more" page="checkout-show">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-10 max-w-[1100px]">

        @php
            $user = auth()->user();
            $ws = $user?->currentWorkspace;
            $amountRaw = (float) $amount;
            $taxPct = (int) ($taxRate ?? 0);
            $tax = round(($amountRaw * $taxPct) / 100, 2);
            $total = round($amountRaw + $tax, 2);
            $isYearlyOrder = ($isYearly ?? false);
            $billed = $isYearlyOrder
                ? '12 months'
                : ($package->plan_unit && $package->plan_duration
                    ? $package->plan_duration . ' ' . $package->plan_unit
                    : 'one-time');
            // Format strictly in the currency this checkout is denominated in
            // — once the customer hits this page the price is locked, even if
            // they later change their workspace currency.
            $currencyFn = fn($n) => \App\Support\FormatSettings::formatIn($n, $currency ?? null);
        @endphp

        {{-- breadcrumb stepper --}}
        <ol class="flex items-center gap-3 text-[11px] font-mono uppercase tracking-[0.16em] text-ink-500 mb-6">
            <li class="text-wa-deep flex items-center gap-1.5"><span
                    class="w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center text-[10px]">1</span>Choose
                plan</li>
            <li class="w-6 h-px bg-paper-300"></li>
            <li class="text-ink-900 flex items-center gap-1.5"><span
                    class="w-5 h-5 rounded-full bg-ink-900 text-paper-0 grid place-items-center text-[10px]">2</span>Checkout
            </li>
            <li class="w-6 h-px bg-paper-200"></li>
            <li class="flex items-center gap-1.5"><span
                    class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center text-[10px]">3</span>Confirmation
            </li>
        </ol>

        <h1 class="font-serif text-[30px] sm:text-[36px] lg:text-[44px] leading-none tracking-[-0.01em]">{{ __('Complete your') }} <span
                class="italic text-wa-deep">{{ __('order') }}</span></h1>
        <p class="text-[13px] text-ink-600 mt-2 max-w-xl">
            {{ __('7-day money-back guarantee. Cancel any time. Cards, UPI, netbanking, and wallets accepted.') }}</p>

        @if (session('error'))
            <div
                class="mt-5 bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-2 text-[12.5px] text-[#A1431F]">
                {{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ $processUrl ?? route('user.checkout.process', $package->id) }}"
            id="checkout-form" class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-6 mt-6">
            @csrf
            <input type="hidden" name="currency" id="currency-input" value="{{ $currency }}">
            {{-- Billing cycle chosen on the pricing page. Yearly = 12 months up
                 front at the yearly discount; process() re-derives the amount. --}}
            <input type="hidden" name="period" value="{{ $period ?? 'monthly' }}">

            {{-- LEFT: form --}}
            <div class="space-y-5">

                {{-- Step 1 · Account --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Step 1') }}
                    </div>
                    <h2 class="font-serif text-[20px] leading-tight mt-0.5 mb-4">{{ __('Account') }}</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Full name') }}
                                <span class="text-accent-coral">*</span></label>
                            <input required type="text" name="customer_name"
                                value="{{ old('customer_name', $user?->name) }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        </div>
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Email') }}
                                <span class="text-accent-coral">*</span></label>
                            <input required type="email" name="customer_email"
                                value="{{ old('customer_email', $user?->email) }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        </div>
                    </div>
                </div>

                {{-- Step 2 · Billing --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Step 2') }}
                    </div>
                    <h2 class="font-serif text-[20px] leading-tight mt-0.5 mb-4">{{ __('Billing address') }}</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Company / Workspace') }}</label>
                            <input type="text" name="billing_company"
                                value="{{ old('billing_company', $ws?->name) }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                        <div class="col-span-2">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Address') }}</label>
                            <input type="text" name="billing_address" value="{{ old('billing_address') }}"
                                placeholder="{{ __('Street + house number') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('City') }}</label>
                            <input type="text" name="billing_city" value="{{ old('billing_city') }}"
                                placeholder="{{ __('Mumbai') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Postal code') }}</label>
                            <input type="text" name="billing_postal" value="{{ old('billing_postal') }}"
                                placeholder="400001"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Country') }}</label>
                            <select name="billing_country"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                                @foreach ($countries as $country)
                                    <option value="{{ $country }}" @selected(old('billing_country') === $country)>
                                        {{ $country }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Tax ID (optional)') }}</label>
                            <input type="text" name="billing_tax_id" value="{{ old('billing_tax_id') }}"
                                placeholder="{{ __('GSTIN / VAT / EIN') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                        </div>
                    </div>
                </div>

                {{-- Currency — only when more than one is available. Changing it
 re-prices the order + re-filters the gateways live (AJAX), so the
 billing details already typed are never wiped by a reload. --}}
                @if (count($availableCurrencies ?? []) > 1)
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Currency') }}</div>
                        <h2 class="font-serif text-[20px] leading-tight mt-0.5 mb-1">{{ __('Pay in your currency') }}
                        </h2>
                        <p class="text-[12px] text-ink-500 mb-3">
                            {{ __('Prices convert instantly. Only currencies your available payment methods accept are shown.') }}
                        </p>
                        <select id="currency-select"
                            class="w-full max-w-xs px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            @foreach ($availableCurrencies as $code)
                                <option value="{{ strtoupper($code) }}" @selected(strtoupper($code) === strtoupper($currency))>
                                    {{ strtoupper($code) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Step 3 · Payment method --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Step 3') }}
                    </div>
                    <h2 class="font-serif text-[20px] leading-tight mt-0.5 mb-4">{{ __('Payment method') }}</h2>

                    @if ($gateways->isEmpty())
                        <div
                            class="px-4 py-3 rounded-lg bg-accent-coral/10 border border-accent-coral/40 text-[12.5px] text-[#A1431F]">
                            No payment gateway is configured for <strong>{{ $currency }}</strong> yet. Ask your
                            admin to activate one at <span class="font-mono">/admin/payment-gateways</span>.
                        </div>
                    @else
                        {{-- Gateway radio cards --}}
                        <div class="space-y-2" id="gateway-list">
                            @foreach ($gateways as $i => $g)
                                <label
                                    class="flex items-center gap-3 p-3 border border-paper-200 rounded-xl cursor-pointer hover:border-wa-deep transition has-[:checked]:border-wa-deep has-[:checked]:bg-wa-mint/20">
                                    <input type="radio" name="gateway_id" value="{{ $g->id }}"
                                        @checked($i === 0) required class="w-4 h-4 accent-wa-deep">
                                    <span
                                        class="w-10 h-10 rounded-lg bg-paper-50 border border-paper-200 grid place-items-center shrink-0">
                                        <svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-700" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <rect x="2" y="4" width="12" height="9" rx="1.5" />
                                            <path d="M2 7h12" />
                                        </svg>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-[13px]">{{ $g->name }}</div>
                                        @if ($g->description)
                                            <div class="text-[11px] text-ink-500 truncate">{{ $g->description }}</div>
                                        @endif
                                    </div>
                                    <span
                                        class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full {{ $g->mode === 'live' ? 'bg-wa-mint text-wa-deep border border-wa-green/30' : 'bg-accent-amber/20 text-[#8B5A14] border border-accent-amber/30' }}">{{ $g->mode }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div
                            class="flex items-center gap-2 mt-4 px-3 py-2 rounded-lg bg-paper-50/60 border border-paper-200 text-[10.5px] text-ink-700">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                stroke="currentColor" stroke-width="1.6">
                                <rect x="3" y="7" width="10" height="7" rx="1.5" />
                                <path d="M5 7V5a3 3 0 0 1 6 0v2" />
                            </svg>
                            {{ __('Payments are processed over a 256-bit TLS connection. We never see your card details.') }}
                        </div>
                    @endif
                </div>

                {{-- Auto-renew choice — only for recurring-eligible monthly plans
                     ($recurringEligible from the controller). Checked by default,
                     so leaving it as-is keeps today's auto-renew behaviour; the
                     customer UNCHECKS it to buy a single cycle with no future
                     charge. process() reads `auto_renew` and forces the one-time
                     path when it's off. Yearly / free / credit top-ups never
                     render this (they can't recur). --}}
                @if (!empty($recurringEligible))
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="auto_renew" value="1" checked
                                class="mt-0.5 w-4 h-4 rounded border-paper-300 accent-wa-deep shrink-0">
                            <span class="min-w-0">
                                <span class="block text-[13px] font-semibold text-ink-900">{{ __('Auto-renew this plan') }}</span>
                                <span class="block text-[11.5px] text-ink-500 mt-0.5 leading-relaxed">
                                    {{ __('Keep the plan active by renewing automatically each billing cycle. Uncheck to pay once — your plan runs to the end of this cycle and then expires, with no further charge. You can re-subscribe any time.') }}
                                </span>
                            </span>
                        </label>
                    </div>
                @endif

                @if ($allowCoupon ?? true)
                    {{-- Coupon --}}
                    <div
                        class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card flex items-center gap-3">
                        <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep shrink-0" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <path d="M3 3h6l5 5-6 6-5-5z" />
                            <circle cx="6" cy="6" r="1" />
                        </svg>
                        <input id="coupon-input" name="coupon" type="text"
                            placeholder="{{ __('Have a coupon code?') }}" value="{{ old('coupon') }}"
                            class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono uppercase tracking-wider focus:outline-none focus:border-wa-deep" />
                        <button type="button" id="coupon-apply"
                            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Apply') }}</button>
                    </div>
                @endif
            </div>

            {{-- RIGHT: order summary --}}
            <aside class="space-y-3 lg:sticky lg:top-6 self-start">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">
                        {{ __('Order summary') }}</div>

                    <div class="flex items-start gap-3">
                        <span class="w-11 h-11 rounded-xl bg-wa-mint text-wa-deep grid place-items-center">
                            <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                stroke-width="1.5">
                                <path d="M5 3l8 5-8 5z" />
                            </svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-[14px]">{{ $itemName ?? $package->pname }}</div>
                            <div class="text-[11px] text-ink-500">
                                {{ $itemSub ?? 'Billed for ' . $billed . ($package->subtitle ? ' · ' . $package->subtitle : '') }}
                            </div>
                            <a href="{{ $changeUrl ?? route('account.plans') }}"
                                class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('Change') }}</a>
                        </div>
                        <div class="font-serif text-[18px]" id="item-price">{!! $currencyFn($amountRaw) !!}</div>
                    </div>

                    <div class="border-t border-paper-200 mt-4 pt-3 space-y-1.5 text-[12.5px]">
                        <div class="flex items-center justify-between">
                            <span class="text-ink-600">{{ __('Subtotal') }}</span>
                            <span class="font-mono" id="sub-amt"
                                data-base="{{ $amountRaw }}">{!! $currencyFn($amountRaw) !!}</span>
                        </div>
                        <div class="flex items-center justify-between hidden" id="discount-row">
                            <span class="text-wa-deep">{{ __('Coupon ·') }} <span id="coupon-applied"></span></span>
                            <span class="font-mono text-wa-deep" id="discount-amt">−{!! $currencyFn(0) !!}</span>
                        </div>
                        @if ($taxPct > 0)
                            <div class="flex items-center justify-between">
                                <span class="text-ink-600">{{ $taxLabel }} ({{ $taxPct }}%)</span>
                                <span class="font-mono" id="tax-amt">{!! $currencyFn($tax) !!}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between border-t border-paper-200 pt-2 mt-2">
                            <span class="font-semibold">{{ __('Total today') }}</span>
                            <span class="font-serif text-[26px] leading-none"
                                id="total-amt">{!! $currencyFn($total) !!}</span>
                        </div>
                    </div>

                    @if ($gateways->isNotEmpty())
                        <button type="submit" form="checkout-form"
                            class="w-full mt-5 px-4 py-3 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center justify-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <rect x="3" y="7" width="10" height="7" rx="1.5" />
                                <path d="M5 7V5a3 3 0 0 1 6 0v2" />
                            </svg>
                            Pay <span id="cta-amt">{!! $currencyFn($total) !!}</span>
                        </button>
                    @else
                        <button disabled
                            class="w-full mt-5 px-4 py-3 rounded-full bg-paper-100 text-ink-500 text-[13px] font-semibold cursor-not-allowed">{{ __('No gateway configured') }}</button>
                    @endif

                    <p class="text-[10.5px] text-ink-500 mt-3 leading-snug text-center">
                        {{ __('By paying you agree to our') }} <a
                            href="{{ legal_url('terms') }}" target="_blank" rel="noopener"
                            class="text-wa-deep font-semibold hover:underline">{{ __('Terms') }}</a> and <a
                            href="{{ legal_url('privacy') }}" target="_blank" rel="noopener"
                            class="text-wa-deep font-semibold hover:underline">{{ __('Privacy policy') }}</a>.</p>
                </div>

                @if ($refundEnabled && $refundDays > 0)
                    <div
                        class="bg-wa-bubble/40 border border-wa-green/30 rounded-2xl p-4 text-[11.5px] text-ink-700 leading-relaxed">
                        <div class="font-semibold flex items-center gap-1.5 mb-1"><svg viewBox="0 0 16 16"
                                class="w-3 h-3 text-wa-deep" fill="currentColor">
                                <path d="M3 8l3 3 7-7-1.4-1.4L6 8.2 4.4 6.6z" />
                            </svg>{{ $refundDays }}-day money-back</div>
                        Not happy? Email us in the first {{ $refundDays }} days and we refund the full amount — no
                        questions, no forms.
                    </div>
                @endif
            </aside>
        </form>
    </main>

    <script>
        (function() {
            // Live recompute of subtotal / discount / tax / total when the
            // user enters a coupon. Real coupon validation happens server-side
            // — this is just an optimistic preview.
            const subEl = document.getElementById('sub-amt');
            const taxEl = document.getElementById('tax-amt');
            const totalEl = document.getElementById('total-amt');
            const ctaEl = document.getElementById('cta-amt');
            const dRow = document.getElementById('discount-row');
            const dAmt = document.getElementById('discount-amt');
            const dCode = document.getElementById('coupon-applied');
            const couponInp = document.getElementById('coupon-input');
            const couponBtn = document.getElementById('coupon-apply');

            const taxPct = {{ $taxPct }};
            let baseAmt = parseFloat(subEl?.dataset.base || '0');
            let currencyPrefix = (subEl?.textContent || '').replace(/[\d.,\s]+/g, '').trim() || '';

            function fmt(n) {
                return currencyPrefix + (n).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            async function applyCoupon() {
                const code = (couponInp.value || '').trim().toUpperCase();
                if (!code) {
                    applyDiscount(0, null);
                    return;
                }
                try {
                    const resp = await fetch(
                        '{{ $couponUrl ?? route('user.checkout.apply-coupon', $package->id) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                code,
                                amount: baseAmt
                            }),
                        });
                    const j = await resp.json();
                    if (j.ok) {
                        applyDiscount(Number(j.discount || 0), j.code || code);
                        if (window.toast) window.toast(j.message || 'Coupon applied.', 'success');
                    } else {
                        applyDiscount(0, null);
                        if (window.toast) window.toast(j.message || 'Coupon invalid.', 'error');
                    }
                } catch (e) {
                    if (window.toast) window.toast('Could not check coupon. Try again.', 'error');
                }
            }

            // Apply a fixed discount amount (server already calculated it).
            function applyDiscount(discountAmt, code) {
                const sub = baseAmt;
                const off = +discountAmt || 0;
                const tax = +((sub - off) * taxPct / 100).toFixed(2);
                const tot = +((sub - off) + tax).toFixed(2);
                if (off > 0) {
                    dRow.classList.remove('hidden');
                    dRow.classList.add('flex');
                    dAmt.textContent = '−' + fmt(off);
                    dCode.textContent = code || '';
                } else {
                    dRow.classList.add('hidden');
                    dRow.classList.remove('flex');
                }
                if (taxEl) taxEl.textContent = fmt(tax);
                totalEl.textContent = fmt(tot);
                ctaEl.textContent = fmt(tot);
            }

            couponBtn?.addEventListener('click', applyCoupon);
            couponInp?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyCoupon();
                }
            });

            /* ── Live currency switch (AJAX) — re-prices + re-filters gateways
            without a reload so the billing fields the user typed survive. ── */
            const curSel = document.getElementById('currency-select');
            const curInp = document.getElementById('currency-input');
            const itemPrice = document.getElementById('item-price');
            const gwList = document.getElementById('gateway-list');

            function esc(s) {
                return String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;'
                } [c]));
            }

            function setPayEnabled(on) {
                document.querySelectorAll('button[type=submit][form="checkout-form"]').forEach((b) => {
                    b.disabled = !on;
                    b.classList.toggle('opacity-50', !on);
                    b.classList.toggle('cursor-not-allowed', !on);
                });
            }

            function rebuildGateways(list) {
                if (!gwList) return;
                if (!list || !list.length) {
                    gwList.innerHTML =
                        '<div class="px-4 py-3 rounded-lg bg-accent-coral/10 border border-accent-coral/40 text-[12.5px] text-[#A1431F]">No payment method accepts this currency. Pick another, or ask your admin.</div>';
                    setPayEnabled(false);
                    return;
                }
                gwList.innerHTML = list.map((g, i) => `
 <label class="flex items-center gap-3 p-3 border border-paper-200 rounded-xl cursor-pointer hover:border-wa-deep transition has-[:checked]:border-wa-deep has-[:checked]:bg-wa-mint/20">
 <input type="radio" name="gateway_id" value="${g.id}" ${i === 0 ? 'checked' : ''} required class="w-4 h-4 accent-wa-deep">
 <span class="w-10 h-10 rounded-lg bg-paper-50 border border-paper-200 grid place-items-center shrink-0"><svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="4" width="12" height="9" rx="1.5"/><path d="M2 7h12"/></svg></span>
 <div class="flex-1 min-w-0"><div class="font-semibold text-[13px]">${esc(g.name)}</div>${g.description ? `<div class="text-[11px] text-ink-500 truncate">${esc(g.description)}</div>` : ''}</div>
 <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full ${g.mode === 'live' ? 'bg-wa-mint text-wa-deep border border-wa-green/30' : 'bg-accent-amber/20 text-[#8B5A14] border border-accent-amber/30'}">${esc(g.mode)}</span>
 </label>`).join('');
                setPayEnabled(true);
            }

            async function switchCurrency() {
                if (!curSel) return;
                const code = curSel.value;
                curSel.disabled = true;
                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('currency', code);
                    url.searchParams.set('ajax', '1');
                    const r = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const j = await r.json();
                    if (!j || !j.ok) throw new Error('bad response');

                    // New base price + currency symbol for the coupon recompute path.
                    baseAmt = Number(j.amountRaw) || 0;
                    currencyPrefix = (j.amountFmt || '').replace(/[\d.,\s]+/g, '').trim();
                    if (curInp) curInp.value = j.currency;
                    if (itemPrice) itemPrice.innerHTML = j.amountFmt;
                    if (subEl) {
                        subEl.innerHTML = j.amountFmt;
                        subEl.dataset.base = baseAmt;
                    }
                    if (taxEl) taxEl.innerHTML = j.taxFmt;
                    if (totalEl) totalEl.innerHTML = j.totalFmt;
                    if (ctaEl) ctaEl.innerHTML = j.totalFmt;
                    // A coupon's discount was in the old currency — clear it on switch.
                    if (couponInp) couponInp.value = '';
                    if (dRow) {
                        dRow.classList.add('hidden');
                        dRow.classList.remove('flex');
                    }
                    rebuildGateways(j.gateways);

                    // Keep the URL in sync (without ajax flag) so a manual refresh keeps it.
                    const clean = new URL(window.location.href);
                    clean.searchParams.set('currency', j.currency);
                    clean.searchParams.delete('ajax');
                    history.replaceState({}, '', clean.toString());
                } catch (e) {
                    if (window.toast) window.toast('Could not switch currency. Try again.', 'error');
                } finally {
                    curSel.disabled = false;
                }
            }
            curSel?.addEventListener('change', switchCurrency);
        })();
    </script>

</x-layouts.user>
