<x-layouts.user :title="__('Payment failed')" page="checkout-failed">

    <main class="max-w-none mx-auto px-7 py-14 max-w-[760px]">

        <!-- breadcrumb stepper -->
        <ol
            class="flex items-center gap-3 text-[11px] font-mono uppercase tracking-[0.16em] text-ink-500 mb-8 justify-center">
            <li class="flex items-center gap-1.5"><span
                    class="w-5 h-5 rounded-full bg-wa-mint text-wa-deep grid place-items-center"><svg viewBox="0 0 16 16"
                        class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3">
                        <path d="M3 8l3 3 7-7" />
                    </svg></span>Choose plan</li>
            <li class="w-6 h-px bg-paper-300"></li>
            <li class="text-accent-coral flex items-center gap-1.5"><span
                    class="w-5 h-5 rounded-full bg-accent-coral/15 text-accent-coral grid place-items-center"><svg
                        viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg></span>Checkout</li>
            <li class="w-6 h-px bg-paper-200"></li>
            <li class="flex items-center gap-1.5 opacity-60"><span
                    class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center text-[10px]">3</span>Confirmation
            </li>
        </ol>

        <div class="text-center">
            <div
                class="x-icon w-24 h-24 mx-auto rounded-full bg-accent-coral/10 border-4 border-accent-coral/20 flex items-center justify-center">
                <svg viewBox="0 0 32 32" class="w-12 h-12">
                    <path d="M9 9 l14 14 M23 9 l-14 14" fill="none" stroke="#E87A5D" stroke-width="3"
                        stroke-linecap="round" />
                </svg>
            </div>
            <h1 class="font-serif text-[32px] sm:text-[38px] lg:text-[44px] leading-tight tracking-[-0.01em] mt-6">{{ __('Payment') }} <span
                    class="italic text-accent-coral">{{ __("didn't go through") }}</span></h1>
            <p class="text-[14px] text-ink-600 mt-3 max-w-md mx-auto">{{ __("We weren't able to charge your card.") }}
                <strong>{{ __("You haven't been billed") }}</strong> — try again with a different method or contact your
                bank.</p>
        </div>

        <!-- Reason card -->
        <div class="bg-paper-0 border-2 border-accent-coral/30 rounded-2xl shadow-card mt-8 overflow-hidden">
            <div class="px-6 py-4 border-b border-paper-200 flex items-start gap-3">
                <span
                    class="w-9 h-9 rounded-full bg-accent-coral/15 text-accent-coral grid place-items-center shrink-0 mt-0.5">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M8 2l7 12H1z" />
                        <path d="M8 6.5v3M8 12h.01" />
                    </svg>
                </span>
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-accent-coral">{{ __('Error') }}
                    </div>
                    <h3 class="font-serif text-[18px] leading-tight mt-0.5" id="err-title">
                        {{ __('Card declined by issuing bank') }}</h3>
                    <p class="text-[12.5px] text-ink-700 mt-1 leading-relaxed" id="err-desc">
                        {{ __('Your bank turned down the transaction. This is usually because of insufficient funds, an international-payments block, or a hold on your card.') }}
                    </p>
                </div>
            </div>
            <div class="px-6 py-4 grid grid-cols-2 gap-x-6 gap-y-2 text-[12px]">
                @php
                    $cur = $order?->currency ?? 'USD';
                    $amt = $order?->amount ?? 0;
                    $fmt = fn($n) => \App\Support\FormatSettings::formatIn($n, $cur);
                @endphp
                <div class="text-ink-500">{{ __('Reference ID') }}</div>
                <div class="font-mono text-ink-900 text-right" id="ref-id">{{ $order?->order_number ?? '—' }}</div>
                <div class="text-ink-500">{{ __('Attempted at') }}</div>
                <div class="font-mono text-ink-900 text-right" id="attempt-at">
                    {{ optional($order?->created_at)->format('M j, Y · H:i') ?? '—' }}</div>
                <div class="text-ink-500">{{ __('Amount') }}</div>
                <div class="font-mono text-ink-900 text-right" id="amt">{!! $fmt($amt) !!}</div>
                <div class="text-ink-500">{{ __('Method') }}</div>
                <div class="font-mono text-ink-900 text-right">{{ $order?->payment_gateway_slug ?? '—' }}</div>
            </div>
        </div>

        <!-- Things to try -->
        <div class="mt-8">
            <h2 class="font-serif text-[20px] leading-tight mb-3">{{ __('A few things to try') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <span class="w-9 h-9 rounded-full bg-paper-50 grid place-items-center"><svg viewBox="0 0 16 16"
                            class="w-4 h-4 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="2" y="4" width="12" height="9" rx="1.5" />
                            <path d="M2 7h12" />
                        </svg></span>
                    <div class="font-semibold text-[13px] mt-3">{{ __('Use a different card') }}</div>
                    <p class="text-[11.5px] text-ink-500 mt-1">
                        {{ __("If your bank's blocking it, try another card from a different issuer.") }}</p>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <span class="w-9 h-9 rounded-full bg-paper-50 grid place-items-center"><svg viewBox="0 0 16 16"
                            class="w-4 h-4 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="3" y="2" width="10" height="12" rx="1.5" />
                            <circle cx="8" cy="12" r="0.8" fill="currentColor" />
                        </svg></span>
                    <div class="font-semibold text-[13px] mt-3">{{ __('Try UPI or netbanking') }}</div>
                    <p class="text-[11.5px] text-ink-500 mt-1">
                        {{ __('Often works when card networks reject international gateway charges.') }}</p>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <span class="w-9 h-9 rounded-full bg-paper-50 grid place-items-center"><svg viewBox="0 0 16 16"
                            class="w-4 h-4 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path
                                d="M2.5 3a1 1 0 0 1 1-1H6l1 3-2 1a8 8 0 0 0 4 4l1-2 3 1v2.5a1 1 0 0 1-1 1A11 11 0 0 1 2.5 3z" />
                        </svg></span>
                    <div class="font-semibold text-[13px] mt-3">{{ __('Call your bank') }}</div>
                    <p class="text-[11.5px] text-ink-500 mt-1">Ask them to enable online payments for
                        {!! $fmt($amt) !!} and try once more.</p>
                </div>
            </div>
        </div>

        <!-- CTAs -->
        <div class="mt-8 flex items-center justify-center gap-3 flex-wrap">
            <a href="{{ url('/checkout') }}"
                class="px-5 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                    stroke-width="1.7">
                    <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 3v3h-3" />
                </svg>
                {{ __('Try again') }}
            </a>
            <a href="{{ url('/account/plans') }}"
                class="px-5 py-2.5 rounded-full bg-paper-0 border border-paper-200 hover:bg-paper-50 text-[13px] font-medium">{{ __('Back to plans') }}</a>
            <a href="{{ url('/support') }}?topic=billing"
                class="px-5 py-2.5 rounded-full bg-paper-0 border border-paper-200 hover:bg-paper-50 text-[13px] font-medium">{{ __('Contact support') }}</a>
        </div>

        <!-- Reassurance -->
        <div class="mt-10 mx-auto max-w-md text-center text-[11.5px] text-ink-500 leading-relaxed">
            <svg viewBox="0 0 16 16" class="w-4 h-4 inline text-wa-deep mr-1" fill="none" stroke="currentColor"
                stroke-width="1.6">
                <rect x="3" y="7" width="10" height="7" rx="1.5" />
                <path d="M5 7V5a3 3 0 0 1 6 0v2" />
            </svg>
            Your card was <strong>{{ __('not') }}</strong> charged. If you see a pending hold from your bank, it'll
            drop off automatically within a few business days.
        </div>

    </main>

</x-layouts.user>
