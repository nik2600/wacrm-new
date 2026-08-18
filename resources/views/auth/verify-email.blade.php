<x-layouts.guest :title="__('Verify your email')">
    <div class="min-h-screen flex items-center justify-center px-6 py-12 bg-paper-50">
        <div class="w-full max-w-md bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-7">
            <div
                class="w-12 h-12 rounded-full bg-accent-amber/10 text-accent-amber grid place-items-center mx-auto mb-4">
                <svg viewBox="0 0 16 16" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.6">
                    <rect x="2" y="3" width="12" height="10" rx="1.5" />
                    <path d="m2.5 4 5.5 4.5L13.5 4" />
                </svg>
            </div>
            <h1 class="font-serif text-[26px] text-center leading-tight">{{ __('Please') }} <span
                    class="italic text-wa-deep">{{ __('verify your email') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-3 text-center">
                We sent a verification link to <b>{{ auth()->user()?->email }}</b>.
                Click it to unlock your dashboard.
            </p>

            @if (session('success'))
                <div class="mt-4 rounded-xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12px]">
                    {{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div
                    class="mt-4 rounded-xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-2 text-[12px]">
                    {{ $errors->first() }}</div>
            @endif

            <div class="mt-5 flex flex-col gap-2">
                <form action="{{ route('verification.send') }}" method="POST">
                    @csrf
                    <button
                        class="w-full px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold hover:bg-wa-teal">{{ __('Resend verification email') }}</button>
                </form>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button
                        class="w-full px-4 py-2 rounded-full border border-paper-200 text-[12.5px] font-medium hover:bg-paper-50">{{ __('Sign out') }}</button>
                </form>
            </div>
            <div class="mt-5 text-[11.5px] text-ink-500 text-center">
                Didn't get it? Check spam, or contact <a class="text-wa-deep font-semibold hover:underline"
                    href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
            </div>
        </div>
    </div>
</x-layouts.guest>
