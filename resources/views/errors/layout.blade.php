<x-layouts.guest :title="$title ?? 'Error'" page="error-page">
    @php
        // Dynamic brand name, but DB-tolerant: a 500 caused by the database being
        // down must not crash the error page itself, so fall back to the .env name.
        $__brandName = (string) rescue(
            fn() => brand_name(),
            brand_name(),
            false,
        );
    @endphp

    <div class="min-h-screen flex items-center justify-center px-6 py-10 relative overflow-hidden">

        {{-- Soft background blobs / matches the auth-art aesthetic --}}
        <div class="auth-art absolute inset-0 -z-10">
            <div class="blob bg-wa-green w-[520px] h-[520px] -top-48 -left-48"></div>
            <div class="blob bg-accent-amber w-[460px] h-[460px] -bottom-40 -right-40"></div>
        </div>

        <div
            class="bg-paper-0 border border-paper-200 rounded-[18px] shadow-card p-10 max-w-[560px] w-full text-center relative">

            {{-- Top brand --}}
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 mb-7">
                <img src="{{ asset('images/brand-mark.png') }}" alt="{{ $__brandName }}"
                    class="w-9 h-9 rounded-md object-contain" />
                <span class="font-serif text-[24px] tracking-[-0.01em]">{{ $__brandName }}</span>
            </a>

            {{-- Big error code --}}
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-3">
                {{ $eyebrow ?? 'Something went wrong' }}</div>
            <div class="font-serif text-[72px] sm:text-[96px] lg:text-[120px] leading-[0.9] tracking-[-0.02em] text-wa-deep">
                {{ $code }}
            </div>
            <h1 class="font-serif text-[28px] leading-tight tracking-[-0.01em] mt-4">
                {{ $headline }}
            </h1>
            <p class="text-[13px] text-ink-600 leading-relaxed mt-3 max-w-md mx-auto">
                {{ $body }}
            </p>

            {{-- Actions --}}
            <div class="mt-7 flex items-center justify-center gap-2 flex-wrap">
                <a href="{{ url()->previous() }}"
                    class="px-4 py-2.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[13px] font-medium inline-flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                    Go back
                </a>
                @php
                    // Pages may override the primary CTA (e.g. the maintenance 503
                    // points admins at sign-in / sign-out). Falls back to the
                    // dashboard/login default.
                    $__ctaUrl = $ctaUrl ?? (auth()->check() ? url('/dashboard') : url('/login'));
                    $__ctaLabel = $ctaLabel ?? (auth()->check() ? 'Back to dashboard' : 'Sign in');
                @endphp
                <a href="{{ $__ctaUrl }}"
                    class="px-4 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center gap-2">
                    {{ $__ctaLabel }}
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path d="M3 8h10M9 4l4 4-4 4" />
                    </svg>
                </a>
            </div>

            {{-- Optional support footer --}}
            <div
                class="mt-7 pt-5 border-t border-paper-200 text-[11.5px] text-ink-500 font-mono uppercase tracking-[0.14em]">
                Need help? <a href="{{ url('/support') }}"
                    class="text-wa-deep font-semibold hover:underline normal-case tracking-normal">{{ __('Talk to support') }}</a>
            </div>
        </div>
    </div>

</x-layouts.guest>
