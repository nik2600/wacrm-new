<x-layouts.admin :title="__('Admin · New Credit Package')" admin-key="credit-packages" page="credit-packages-create">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ route('admin.credit-packages.index') }}"
                class="hover:text-ink-900">{{ __('Credit packages') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('New') }}</span>
        </div>
    </header>

    <div class="px-4 sm:px-7 pt-7 pb-2">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Admin · Credit packages · New') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[36px] leading-[1.0]">{{ __('New') }} <span
                    class="italic text-wa-deep">{{ __('credit package') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                {{ __("Set price, credits, and an optional badge. Active packages appear on the user's wallet page; disabled ones stay in the DB but are hidden.") }}
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
        @include('admin.credit-packages._form')
    </main>

</x-layouts.admin>
