<x-layouts.user :title="__('Checkout')" page="checkout-index">

    <main class="max-w-none mx-auto px-7 py-10 max-w-[1100px]">

        <!-- breadcrumb stepper -->
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

        <h1 class="font-serif text-[32px] sm:text-[38px] lg:text-[44px] leading-none tracking-[-0.01em]">{{ __('Complete your') }} <span
                class="italic text-wa-deep">{{ __('order') }}</span></h1>
        <p class="text-[13px] text-ink-600 mt-2 max-w-xl">
            {{ __('7-day money-back guarantee. Cancel any time. Cards, UPI, netbanking, and wallets accepted.') }}</p>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-6 mt-6">

            <!-- LEFT: form -->
            <form class="space-y-5" onsubmit="event.preventDefault(); pay()">

                <!-- Account -->
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Step 1') }}
                    </div>
                    <h2 class="font-serif text-[20px] leading-tight mt-0.5 mb-4">{{ __('Account') }}</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Full name') }}
                                <span class="text-accent-coral">*</span></label>
                            <input required type="text" value="Vetrick R."
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        </div>
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Email') }}
                                <span class="text-accent-coral">*</span></label>
                            <input required type="email" value="vetrick@bloomly.in"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        </div>
                    </div>
                </div>

                <!-- Billing -->
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Step 2') }}
                    </div>
                    <h2 class="font-serif text-[20px] leading-tight mt-0.5 mb-4">{{ __('Billing address') }}</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Company / Workspace') }}</label>
                            <input type="text" value="Bloomly"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Address') }}
                                <span class="text-accent-coral">*</span></label>
                            <input required type="text" placeholder="{{ __('Street + house number') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('City') }}
                                <span class="text-accent-coral">*</span></label>
                            <input required type="text" placeholder="{{ __('Mumbai') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Postal code') }}
                                <span class="text-accent-coral">*</span></label>
                            <input required type="text" placeholder="400001"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Country') }}</label>
                            <select
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                                <option>{{ __('India') }}</option>
                                <option>{{ __('United States') }}</option>
                                <option>{{ __('United Kingdom') }}</option>
                                <option>{{ __('UAE') }}</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('GSTIN (optional)') }}</label>
                            <input type="text" placeholder="22AAAAA0000A1Z5"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                        </div>
                    </div>
                </div>

                <!-- Payment -->
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Step 3') }}
                    </div>
                    <h2 class="font-serif text-[20px] leading-tight mt-0.5 mb-4">{{ __('Payment method') }}</h2>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4" id="pay-tabs">
                        <button type="button" data-pm="card"
                            class="px-3 py-2.5 rounded-lg border border-wa-deep bg-wa-mint/30 text-wa-deep text-[12px] font-semibold inline-flex items-center justify-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <rect x="2" y="4" width="12" height="9" rx="1.5" />
                                <path d="M2 7h12" />
                            </svg>{{ __('Card') }}
                        </button>
                        <button type="button" data-pm="upi"
                            class="px-3 py-2.5 rounded-lg border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center justify-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <rect x="3" y="2" width="10" height="12" rx="1.5" />
                                <circle cx="8" cy="12" r="0.8" fill="currentColor" />
                            </svg>{{ __('UPI') }}
                        </button>
                        <button type="button" data-pm="netbank"
                            class="px-3 py-2.5 rounded-lg border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center justify-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M2 6l6-3 6 3v1H2zM3 8v4M6 8v4M10 8v4M13 8v4M2 13h12" />
                            </svg>{{ __('Netbanking') }}
                        </button>
                        <button type="button" data-pm="wallet"
                            class="px-3 py-2.5 rounded-lg border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center justify-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <rect x="2" y="4" width="12" height="9" rx="1.5" />
                                <circle cx="11" cy="9" r="1" />
                            </svg>{{ __('Wallet') }}
                        </button>
                    </div>

                    <!-- Card panel -->
                    <div data-pm-panel="card" class="space-y-4">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Card number') }}
                                <span class="text-accent-coral">*</span></label>
                            <div class="relative">
                                <input required type="text" placeholder="1234 5678 9012 3456"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                                <div
                                    class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1 text-ink-500">
                                    <svg viewBox="0 0 32 20" class="w-7 h-5">
                                        <rect width="32" height="20" rx="3" fill="#1A1F71" /><text
                                            x="16" y="14" text-anchor="middle" fill="#FFB600" font-family="Arial"
                                            font-size="9" font-weight="bold">VISA</text>
                                    </svg>
                                    <svg viewBox="0 0 32 20" class="w-7 h-5">
                                        <rect width="32" height="20" rx="3" fill="#fff"
                                            stroke="#E5DFD0" />
                                        <circle cx="13" cy="10" r="5" fill="#EB001B" />
                                        <circle cx="19" cy="10" r="5" fill="#F79E1B" opacity="0.85" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="sm:col-span-2">
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Cardholder name') }}</label>
                                <input type="text" placeholder="{{ __('As printed on card') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Expiry') }}</label>
                                <input type="text" placeholder="{{ __('MM/YY') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('CVV') }}</label>
                                <input type="password" maxlength="4" placeholder="•••"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                            </div>
                            <label class="sm:col-span-2 flex items-end gap-2 cursor-pointer">
                                <input type="checkbox" class="w-4 h-4 accent-wa-deep" checked />
                                <span
                                    class="text-[12px] text-ink-700">{{ __('Save this card for future renewals') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- UPI panel -->
                    <div data-pm-panel="upi" class="hidden space-y-4">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('UPI ID') }}</label>
                            <input type="text" placeholder="yourname@bank"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                            <p class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('A collect request will be sent to your UPI app.') }}</p>
                        </div>
                    </div>

                    <!-- Netbank panel -->
                    <div data-pm-panel="netbank" class="hidden">
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Choose bank') }}</label>
                        <select
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                            <option>{{ __('HDFC Bank') }}</option>
                            <option>{{ __('State Bank of India') }}</option>
                            <option>{{ __('ICICI Bank') }}</option>
                            <option>{{ __('Axis Bank') }}</option>
                            <option>{{ __('Kotak Mahindra Bank') }}</option>
                            <option>{{ __('Other (search…)') }}</option>
                        </select>
                    </div>

                    <!-- Wallet panel -->
                    <div data-pm-panel="wallet" class="hidden">
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Pay with your message credits') }}</label>
                        <div class="px-3 py-3 rounded-lg bg-paper-50 border border-paper-200 flex items-center gap-3">
                            <span class="w-9 h-9 rounded-lg bg-wa-mint text-wa-deep grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <rect x="2" y="4" width="12" height="9" rx="1.5" />
                                    <circle cx="11" cy="9" r="1" />
                                </svg></span>
                            <div class="flex-1">
                                <div class="font-semibold text-[13px]">{{ __('Credit balance') }}</div>
                                <div class="text-[10.5px] text-ink-500">
                                    {{ __('Earn more via the affiliate program') }}</div>
                            </div>
                            <span class="font-serif text-[20px]">{{ number_format((int) ($credits ?? 0)) }}
                                {{ __('credits') }}</span>
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-2 mt-4 px-3 py-2 rounded-lg bg-paper-50/60 border border-paper-200 text-[10.5px] text-ink-700">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <rect x="3" y="7" width="10" height="7" rx="1.5" />
                            <path d="M5 7V5a3 3 0 0 1 6 0v2" />
                        </svg>
                        {{ __('Payments are processed by Razorpay over a 256-bit TLS connection. We never see your card details.') }}
                    </div>
                </div>

                <!-- Coupon -->
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card flex items-center gap-3">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep shrink-0" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path d="M3 3h6l5 5-6 6-5-5z" />
                        <circle cx="6" cy="6" r="1" />
                    </svg>
                    <input id="coupon" type="text"
                        placeholder="{{ __('Have a coupon code? e.g. VETRICK20') }}"
                        class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono uppercase tracking-wider focus:outline-none focus:border-wa-deep" />
                    <button type="button"
                        class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium"
                        onclick="applyCoupon()">{{ __('Apply') }}</button>
                </div>
            </form>

            <!-- RIGHT: order summary -->
            <aside class="space-y-3 lg:sticky lg:top-6 self-start">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">
                        {{ __('Order summary') }}</div>

                    @php
                        $price = $package?->price_minor ?? 0;
                        $priceMajor = $price / 100;
                        $tax = (int) round($price * 0.18);
                        $taxMajor = $tax / 100;
                        $total = $price + $tax;
                        $totalMajor = $total / 100;
                        // Display the checkout amounts in the package's native currency.
// formatIn => no conversion (customer pays exactly what's shown).
                        $payCur = $package?->currency_code ?? \App\Models\SystemSetting::get('default_currency', 'USD');
                        $fmt = fn($n) => \App\Support\FormatSettings::formatIn($n, $payCur);
                    @endphp

                    <div class="flex items-start gap-3">
                        <span class="w-11 h-11 rounded-xl bg-wa-mint text-wa-deep grid place-items-center"><svg
                                viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                stroke-width="1.5">
                                <path d="M5 3l8 5-8 5z" />
                            </svg></span>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-[14px]" id="item-name">
                                {{ $package?->name ?? 'No package selected' }}</div>
                            <div class="text-[11px] text-ink-500" id="item-sub">
                                {{ $package ? number_format($package->credits) . ' message credits' : 'Pick one from your wallet page' }}
                            </div>
                            <a href="{{ url('/account?tab=wallet') }}"
                                class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('Change') }}</a>
                        </div>
                        <div class="font-serif text-[18px]" id="item-price">{{ $package?->price_display ?? '—' }}
                        </div>
                    </div>

                    <div class="border-t border-paper-200 mt-4 pt-3 space-y-1.5 text-[12.5px]">
                        <div class="flex items-center justify-between"><span
                                class="text-ink-600">{{ __('Subtotal') }}</span><span class="font-mono"
                                id="sub-amt">{!! $fmt($priceMajor) !!}</span></div>
                        <div class="flex items-center justify-between" id="discount-row" style="display:none"><span
                                class="text-wa-deep">{{ __('Coupon ·') }} <span
                                    id="coupon-applied"></span></span><span class="font-mono text-wa-deep"
                                id="discount-amt">−{!! $fmt(0) !!}</span></div>
                        <div class="flex items-center justify-between"><span
                                class="text-ink-600">{{ __('GST (18%)') }}</span><span class="font-mono"
                                id="tax-amt">{!! $fmt($taxMajor) !!}</span></div>
                        <div class="flex items-center justify-between border-t border-paper-200 pt-2 mt-2">
                            <span class="font-semibold">{{ __('Total today') }}</span>
                            <span class="font-serif text-[26px] leading-none"
                                id="total-amt">{!! $fmt($totalMajor) !!}</span>
                        </div>
                        @if ($package)
                            <div
                                class="border-t border-paper-200 pt-2 mt-2 flex items-center justify-between text-[11px] text-ink-500">
                                <span>{{ __('Adds to wallet') }}</span>
                                <span class="font-mono text-wa-deep">+{{ number_format($package->credits) }}
                                    {{ __('credits') }}</span>
                            </div>
                        @endif
                    </div>

                    @if ($package)
                        <form method="POST" action="{{ route('checkout.complete') }}" class="mt-5">
                            @csrf
                            <input type="hidden" name="package_slug" value="{{ $package->slug }}" />
                            <button type="submit"
                                class="w-full px-4 py-3 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center justify-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.7">
                                    <rect x="3" y="7" width="10" height="7" rx="1.5" />
                                    <path d="M5 7V5a3 3 0 0 1 6 0v2" />
                                </svg>
                                Pay {!! $fmt($totalMajor) !!} · add {{ number_format($package->credits) }}
                                {{ __('credits') }}
                            </button>
                        </form>
                    @else
                        <a href="{{ url('/account?tab=wallet') }}"
                            class="w-full mt-5 px-4 py-3 rounded-full bg-paper-100 text-ink-700 text-[13px] font-semibold inline-flex items-center justify-center gap-2">{{ __('Pick a credit package') }}</a>
                    @endif

                    <p class="text-[10.5px] text-ink-500 mt-3 leading-snug text-center">
                        {{ __('By paying you agree to our') }} <a
                            href="{{ legal_url('terms') }}" target="_blank" rel="noopener"
                            class="text-wa-deep font-semibold hover:underline">{{ __('Terms') }}</a> and <a
                            href="{{ legal_url('privacy') }}" target="_blank" rel="noopener"
                            class="text-wa-deep font-semibold hover:underline">{{ __('Privacy policy') }}</a>.</p>
                </div>

                <div
                    class="bg-wa-bubble/40 border border-wa-green/30 rounded-2xl p-4 text-[11.5px] text-ink-700 leading-relaxed">
                    <div class="font-semibold flex items-center gap-1.5 mb-1"><svg viewBox="0 0 16 16"
                            class="w-3 h-3 text-wa-deep" fill="currentColor">
                            <path d="M3 8l3 3 7-7-1.4-1.4L6 8.2 4.4 6.6z" />
                        </svg>7-day money-back</div>
                    {{ __('Not happy? Email us in the first 7 days and we refund the full amount — no questions, no forms.') }}
                </div>
            </aside>
        </div>
    </main>

</x-layouts.user>
