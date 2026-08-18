<x-layouts.user :title="__('Contact sales')" nav-key="more" page="checkout-offline">

    <main class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-7 py-10">
        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Checkout / Offline') }}
        </div>
        <h1 class="font-serif text-[26px] sm:text-[30px] lg:text-[36px] leading-tight">{{ __('Contact us to complete this order') }}</h1>
        <p class="text-[12.5px] text-ink-600 mt-2">{{ __('Order') }} <span
                class="font-mono">{{ $order->order_number }}</span> · <strong>{!! \App\Support\FormatSettings::formatIn($order->amount, $order->currency) !!}</strong> · billed
            in {{ $order->currency }}</p>

        <div class="mt-6 bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-6">
            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">{{ __('Instructions') }}
            </div>
            <div class="text-[13px] text-ink-700 whitespace-pre-wrap">{{ $instructions }}</div>
        </div>

        @include('checkout.partials.proof-form', ['order' => $order])

        <div class="mt-6">
            <a href="{{ route('user.account', ['tab' => 'orders']) }}"
                class="text-[12px] font-semibold text-ink-600 hover:text-wa-deep">{{ __('Got it — I\'ll submit proof later') }}</a>
        </div>
    </main>

</x-layouts.user>
