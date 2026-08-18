<x-layouts.guest :title="__('Account suspended')">
    <div class="min-h-screen flex items-center justify-center px-6 py-12 bg-paper-50">
        <div class="w-full max-w-md bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-7">
            <div
                class="w-12 h-12 rounded-full bg-accent-coral/10 text-accent-coral grid place-items-center mx-auto mb-4">
                <svg viewBox="0 0 16 16" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.6">
                    <circle cx="8" cy="8" r="6" />
                    <path d="M5 5l6 6" />
                </svg>
            </div>
            <h1 class="font-serif text-[26px] text-center leading-tight">{{ __('Your account is') }} <span
                    class="italic text-accent-coral">{{ __('currently suspended') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-3 text-center">
                Hi {{ auth()->user()?->name }}, an administrator has suspended this account. You can still sign in,
                but the rest of the app is locked until a {{ brand_name() }} admin reactivates you.
            </p>
            <div class="mt-5 rounded-xl border border-paper-200 bg-paper-50 px-4 py-3 text-[12px] text-ink-700">
                If you believe this is a mistake, please contact your workspace owner or
                <a class="text-wa-deep font-semibold hover:underline"
                    href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
            </div>
            <div class="mt-5 flex items-center justify-center gap-2">
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold hover:bg-wa-teal">{{ __('Sign out') }}</button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.guest>
