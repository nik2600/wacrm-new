<x-layouts.admin :title="__('Admin · Edit coupon')" admin-key="coupons" page="admin-coupons-edit">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ route('admin.coupons.index') }}" class="hover:text-ink-900">{{ __('Coupons') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal truncate max-w-[260px]">{{ $coupon->code }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            @if ($coupon->is_active)
                <span
                    class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono"><span
                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('Active') }}</span>
            @else
                <span
                    class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 border border-paper-200 font-mono">{{ __('Disabled') }}</span>
            @endif
            <a href="{{ route('admin.coupons.index') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
            <button type="submit" form="couponForm"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 8l5 5 7-9" />
                </svg>
                {{ __('Save changes') }}
            </button>
        </div>
    </header>

    <div class="px-4 sm:px-7 pt-7 pb-2">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Admin · Billing & plans · Coupons · Edit') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[36px] leading-[1.0] font-mono break-all">
                {{ $coupon->code }}</h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                Redeemed
                {{ number_format($coupon->uses_count) }}{{ $coupon->max_uses ? ' / ' . number_format($coupon->max_uses) : '' }}
                times
                @if ($coupon->expires_at)
                    · expires {{ $coupon->expires_at->diffForHumans() }}
                @endif
            </p>
        </div>
    </div>

    <main class="px-4 sm:px-7 pb-7">
        @if (session('success'))
            <div class="mb-4 rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div
                class="mb-4 rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">
                <div class="font-semibold mb-1">{{ __('Please fix the following:') }}</div>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @include('admin.coupons._form', ['coupon' => $coupon])
    </main>

</x-layouts.admin>
