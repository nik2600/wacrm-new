<x-layouts.user :title="__('Bank Transfer')" nav-key="more" page="checkout-bank-transfer">

    <main class="max-w-2xl mx-auto px-7 py-10">
        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
            {{ __('Checkout / Bank transfer') }}</div>
        <h1 class="font-serif text-[28px] sm:text-[32px] lg:text-[36px] leading-tight">{{ __('Wire the payment') }}</h1>
        <p class="text-[12.5px] text-ink-600 mt-2">{{ __('Send') }} <strong>{!! \App\Support\FormatSettings::formatIn($order->amount, $order->currency) !!}</strong> to the
            account below. Use <span class="font-mono">{{ $order->order_number }}</span> as the reference so we can match
            it to your order.</p>

        <div class="mt-6 bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-6 space-y-3">
            @foreach ([
        'beneficiary_name' => 'Beneficiary',
        'bank_name' => 'Bank',
        'account_number' => 'Account number',
        'ifsc_or_swift' => 'IFSC / SWIFT',
        'branch' => 'Branch',
    ] as $key => $label)
                @if (!empty($creds[$key]))
                    <div class="flex items-center justify-between text-[13px]">
                        <span
                            class="font-mono text-[10.5px] uppercase tracking-[0.14em] text-ink-500">{{ $label }}</span>
                        <span class="font-mono">{{ $creds[$key] }}</span>
                    </div>
                @endif
            @endforeach

            @if (!empty($creds['notes']))
                <div class="mt-4 pt-4 border-t border-paper-200 text-[12px] text-ink-600 whitespace-pre-wrap">
                    {{ $creds['notes'] }}</div>
            @endif
        </div>

        <div
            class="mt-6 px-4 py-3 rounded-lg bg-accent-amber/10 border border-accent-amber/40 text-[12px] text-[#7B5A14]">
            <strong>{{ __('Reference:') }}</strong> include <span class="font-mono">{{ $order->order_number }}</span> on
            the transfer, then submit your payment proof below. Your plan activates once our team verifies the payment.
        </div>

        @include('checkout.partials.proof-form', ['order' => $order])

        <div class="mt-6">
            <a href="{{ route('user.account', ['tab' => 'orders']) }}"
                class="text-[12px] font-semibold text-ink-600 hover:text-wa-deep">I've sent the payment — submit proof
                later</a>
        </div>
    </main>

</x-layouts.user>
