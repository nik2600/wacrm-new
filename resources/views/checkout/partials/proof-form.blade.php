{{-- Payment-proof submission for offline / bank-transfer orders. The buyer
 uploads a receipt + reference; an admin reviews + activates the plan. --}}
<div class="mt-6 bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-6">
    <div class="flex items-center gap-2 mb-1">
        <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none" stroke="currentColor" stroke-width="1.6">
            <path d="M2 11.5V13a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-1.5M8 2v8M5 6l3-3 3 3" />
        </svg>
        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Submit payment proof') }}
        </div>
    </div>
    <p class="text-[12px] text-ink-600 mb-4">
        {{ __('Already paid? Upload your receipt and reference so we can verify and activate your plan.') }}</p>

    @if (session('error'))
        <div
            class="mb-4 px-4 py-2.5 rounded-lg bg-accent-coral/10 border border-accent-coral/40 text-[12px] text-accent-coral">
            {{ session('error') }}</div>
    @endif
    @error('proof')
        <div
            class="mb-4 px-4 py-2.5 rounded-lg bg-accent-coral/10 border border-accent-coral/40 text-[12px] text-accent-coral">
            {{ $message }}</div>
    @enderror

    <form method="POST" enctype="multipart/form-data" action="{{ route('user.checkout.proof', $order->id) }}"
        class="space-y-4">
        @csrf

        <div>
            <label
                class="block text-[11px] font-semibold text-ink-700 mb-1.5">{{ __('Payment screenshot / receipt') }}</label>
            <input type="file" name="proof" accept=".jpeg,.jpg,.png,.webp,.pdf,image/*,application/pdf" required
                class="block w-full text-[12px] text-ink-700 file:mr-3 file:px-4 file:py-2 file:rounded-full file:border-0 file:bg-wa-deep file:text-paper-0 file:text-[12px] file:font-semibold hover:file:bg-wa-teal file:cursor-pointer" />
            <p class="text-[10.5px] text-ink-500 mt-1">{{ __('JPG, PNG, WEBP or PDF · up to 4 MB') }}</p>
        </div>

        <div>
            <label
                class="block text-[11px] font-semibold text-ink-700 mb-1.5">{{ __('Reference / UTR number') }}</label>
            <input type="text" name="payment_reference" maxlength="120"
                placeholder="{{ __('e.g. UTR / transaction id') }}"
                class="block w-full px-3 py-2 hairline border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
        </div>

        <div>
            <label class="block text-[11px] font-semibold text-ink-700 mb-1.5">{{ __('Note (optional)') }}</label>
            <textarea name="proof_note" rows="2" maxlength="1000"
                placeholder="{{ __('Anything we should know about this payment') }}"
                class="block w-full px-3 py-2 hairline border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep resize-none"></textarea>
        </div>

        <button type="submit"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M2 8l4 4 8-9" />
            </svg>
            {{ __('Submit payment proof') }}
        </button>
    </form>
</div>
