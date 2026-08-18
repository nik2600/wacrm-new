<x-layouts.user :title="__('Payment successful')" page="checkout-success">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-14 max-w-[820px]">

        <!-- breadcrumb stepper -->
        <ol
            class="flex items-center gap-3 text-[11px] font-mono uppercase tracking-[0.16em] text-ink-500 mb-8 justify-center">
            <li class="flex items-center gap-1.5"><span
                    class="w-5 h-5 rounded-full bg-wa-mint text-wa-deep grid place-items-center"><svg viewBox="0 0 16 16"
                        class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3">
                        <path d="M3 8l3 3 7-7" />
                    </svg></span>Choose plan</li>
            <li class="w-6 h-px bg-paper-300"></li>
            <li class="flex items-center gap-1.5"><span
                    class="w-5 h-5 rounded-full bg-wa-mint text-wa-deep grid place-items-center"><svg
                        viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3">
                        <path d="M3 8l3 3 7-7" />
                    </svg></span>Checkout</li>
            <li class="w-6 h-px bg-wa-deep"></li>
            <li class="text-wa-deep flex items-center gap-1.5"><span
                    class="w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center text-[10px]">3</span>Confirmation
            </li>
        </ol>

        <div class="text-center">
            <div class="tick-ring relative w-24 h-24 mx-auto rounded-full bg-wa-mint flex items-center justify-center">
                <svg viewBox="0 0 32 32" class="w-12 h-12 text-wa-deep">
                    <circle cx="16" cy="16" r="14" fill="none" stroke="currentColor" stroke-width="1.6"
                        opacity="0.2" />
                    <path class="tick-path" d="M9 17 l5 5 l10 -12" fill="none" stroke="currentColor" stroke-width="3"
                        stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>
            <h1 class="font-serif text-[30px] sm:text-[36px] lg:text-[44px] leading-tight tracking-[-0.01em] mt-6">{{ __('Payment') }} <span
                    class="italic text-wa-deep">{{ __('successful') }}</span></h1>
            @if (session('topup_credits'))
                <p class="text-[14px] text-ink-600 mt-3 max-w-md mx-auto">
                    +<strong>{{ number_format(session('topup_credits')) }} {{ __('credits') }}</strong> added to your
                    wallet. Package: <strong>{{ session('topup_package') }}</strong>. <a
                        href="{{ url('/account?tab=wallet') }}"
                        class="text-wa-deep font-semibold hover:underline">{{ __('View wallet →') }}</a></p>
            @else
                <p class="text-[14px] text-ink-600 mt-3 max-w-md mx-auto">{{ __('Your purchase is complete.') }} <a
                        href="{{ url('/account?tab=wallet') }}"
                        class="text-wa-deep font-semibold hover:underline">{{ __('Open wallet →') }}</a></p>
            @endif
        </div>

        <!-- Receipt card -->
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card mt-8 overflow-hidden">
            <div class="px-6 py-4 border-b border-paper-200 flex items-center justify-between">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Receipt') }}
                    </div>
                    <div class="font-mono text-[13px] text-ink-900 mt-0.5">#INV-<span id="inv-num">2403</span></div>
                </div>
                <div class="text-right">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Paid on') }}
                    </div>
                    <div class="font-mono text-[12.5px] text-ink-900 mt-0.5" id="paid-date">
                        {{ __('Apr 27, 2026 · 14:42 IST') }}</div>
                </div>
            </div>

            <div class="px-6 py-5 space-y-3">
                <div class="flex items-start gap-3">
                    <span class="w-11 h-11 rounded-xl bg-wa-mint text-wa-deep grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M5 3l8 5-8 5z" />
                        </svg></span>
                    <div class="flex-1">
                        <div class="font-semibold text-[14px]" id="line-name">{{ __('Pro plan · Yearly') }}</div>
                        <div class="text-[11px] text-ink-500" id="line-sub">
                            {{ __('12 months · billed annually · auto-renews Apr 27, 2027') }}</div>
                    </div>
                    @php
                        $cur = $order?->currency ?? 'USD';
                        $subtotal = $order
                            ? $order->amount - ($order->tax_amount ?? 0) + ($order->discount_amount ?? 0)
                            : 0;
                        $taxAmt = $order->tax_amount ?? 0;
                        $taxPct =
                            $order && $order->tax_rate
                                ? rtrim(rtrim(number_format($order->tax_rate, 2), '0'), '.')
                                : null;
                        $total = $order?->amount ?? 0;
                        $fmt = fn($n) => \App\Support\FormatSettings::formatIn($n, $cur);
                    @endphp
                    <div class="font-serif text-[18px]" id="line-amount">{!! $fmt($total) !!}</div>
                </div>

                <div class="border-t border-paper-200 pt-3 space-y-1.5 text-[12.5px]">
                    <div class="flex items-center justify-between"><span
                            class="text-ink-600">{{ __('Subtotal') }}</span><span
                            class="font-mono">{!! $fmt($subtotal) !!}</span></div>
                    @if ($taxAmt > 0)
                        <div class="flex items-center justify-between"><span
                                class="text-ink-600">Tax{{ $taxPct ? ' (' . $taxPct . '%)' : '' }}</span><span
                                class="font-mono">{!! $fmt($taxAmt) !!}</span></div>
                    @endif
                    <div class="flex items-center justify-between border-t border-paper-200 pt-2 mt-2">
                        <span class="font-semibold">{{ __('Total paid') }}</span>
                        <span class="font-serif text-[24px] leading-none" id="total-paid">{!! $fmt($total) !!}</span>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 mt-3 text-[11px] text-ink-500">
                    <svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <rect x="2" y="4" width="12" height="9" rx="1.5" />
                        <path d="M2 7h12" />
                    </svg>
                    {{ __('Paid with Visa ···· 4242') }}
                </div>
            </div>

            <div
                class="px-6 py-4 border-t border-paper-200 bg-paper-50/40 flex items-center justify-between gap-3 flex-wrap">
                <a class="text-[12px] font-semibold text-wa-deep hover:underline inline-flex items-center gap-1"><svg
                        viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
                    </svg>{{ __('Download PDF receipt') }}</a>
                <a href="{{ url('/account') }}"
                    class="text-[12px] text-ink-700 hover:text-wa-deep">{{ __('View all orders →') }}</a>
            </div>
        </div>

        <!-- What's next -->
        <div class="mt-8">
            <h2 class="font-serif text-[20px] leading-tight mb-3">{{ __("What's next") }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <a href="{{ url('/devices') }}"
                    class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                    <span class="w-10 h-10 rounded-xl bg-wa-mint text-wa-deep grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <rect x="3.5" y="2" width="9" height="12" rx="1.5" />
                            <circle cx="8" cy="11.5" r="0.8" />
                        </svg></span>
                    <div class="font-semibold text-[14px] mt-3">{{ __('Pair your devices') }}</div>
                    <p class="text-[11.5px] text-ink-500 mt-1 flex-1">
                        {{ __('Connect up to 5 WhatsApp numbers — sales, support, billing.') }}</p>
                    <span
                        class="text-[12px] text-wa-deep font-semibold mt-3 inline-flex items-center gap-1">{{ __('Open devices →') }}</span>
                </a>
                <a href="{{ url('/team-inbox') }}"
                    class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                    <span class="w-10 h-10 rounded-xl bg-[#D9E5F2] text-[#13478A] grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <circle cx="6" cy="6" r="3" />
                            <path d="M2 14c0-3 2.5-5 4-5s4 2 4 5" />
                            <circle cx="11.5" cy="5.5" r="2" />
                        </svg></span>
                    <div class="font-semibold text-[14px] mt-3">{{ __('Invite your team') }}</div>
                    <p class="text-[11.5px] text-ink-500 mt-1 flex-1">
                        {{ __('Up to 10 agents on Pro · assign chats, leave notes, collaborate.') }}</p>
                    <span
                        class="text-[12px] text-wa-deep font-semibold mt-3 inline-flex items-center gap-1">{{ __('Open team inbox →') }}</span>
                </a>
                <a href="{{ url('/integrations') }}"
                    class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                    <span class="w-10 h-10 rounded-xl bg-[#F3E9FF] text-[#5B3D8A] grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <path d="M5.5 4.5 3 7l2.5 2.5M10.5 4.5 13 7l-2.5 2.5M7 12l2-8" />
                        </svg></span>
                    <div class="font-semibold text-[14px] mt-3">{{ __('Connect your store') }}</div>
                    <p class="text-[11.5px] text-ink-500 mt-1 flex-1">
                        {{ __('Shopify, WooCommerce, Google Sheets, WhatsApp Catalog.') }}</p>
                    <span
                        class="text-[12px] text-wa-deep font-semibold mt-3 inline-flex items-center gap-1">{{ __('Open integrations →') }}</span>
                </a>
            </div>
        </div>

        <!-- Footer CTAs -->
        <div class="mt-8 flex items-center justify-center gap-3 flex-wrap">
            <a href="{{ url('/dashboard') }}"
                class="px-5 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center gap-2">
                {{ __('Go to dashboard') }}
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                    stroke-width="1.7">
                    <path d="M3 8h10M9 4l4 4-4 4" />
                </svg>
            </a>
            <a href="{{ url('/support') }}"
                class="px-5 py-2.5 rounded-full bg-paper-0 border border-paper-200 hover:bg-paper-50 text-[13px] font-medium">{{ __('Contact support') }}</a>
        </div>

    </main>

</x-layouts.user>
