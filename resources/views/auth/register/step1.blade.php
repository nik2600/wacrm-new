{{-- Step 1 uses the SAME auth-shell as the register page (variant-driven). --}}
<x-auth-shell page="register" :title="__('Create your account / Step 1')">
    @php $__brandName = (string) brand_name(); @endphp

    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
        {{ __('Step 1 of 3 / Account') }}</div>
    <h2 class="font-serif text-[30px] leading-tight tracking-[-0.01em]">{{ __('Create your') }} <span
            class="italic text-wa-deep">{{ __('account') }}</span>.</h2>
    <p class="text-[12.5px] text-ink-600 mt-1.5">{{ __('Free to start. Cancel any time.') }}</p>

    <ol class="flex items-center gap-2 mt-4 mb-5 text-[10.5px] font-mono uppercase tracking-wider">
        <li class="text-wa-deep flex items-center gap-1.5"><span
                class="w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center text-[10px]">1</span>Account
        </li>
        <li class="w-4 h-px bg-paper-300"></li>
        <li class="text-ink-500 flex items-center gap-1.5"><span
                class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center text-[10px]">2</span>Workspace
        </li>
        <li class="w-4 h-px bg-paper-200"></li>
        <li class="text-ink-500 flex items-center gap-1.5"><span
                class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center text-[10px]">3</span>Plan
        </li>
    </ol>

    @if ($errors->any())
        <div class="mb-3 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
            @foreach ($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ url('/register') }}" class="space-y-3">
        @csrf
        <div>
            <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Full name') }}</label>
            <input required type="text" name="name" value="{{ old('name') }}" placeholder="{{ __('Your name') }}"
                class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
        </div>
        <div>
            <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Work email') }}</label>
            <input required type="email" name="email" value="{{ old('email') }}" placeholder="you@company.com"
                class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
        </div>
        <div>
            <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Password') }}</label>
            <input id="pw" required type="password" name="password" placeholder="{{ __('At least 8 characters') }}"
                minlength="8" autocomplete="new-password"
                class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
        </div>
        <div>
            <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Confirm password') }}</label>
            <input required type="password" name="password_confirmation" minlength="8" autocomplete="new-password"
                class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
        </div>
        <label class="flex items-start gap-2 cursor-pointer pt-1">
            <input type="checkbox" name="agree" value="1" required class="w-4 h-4 accent-wa-deep mt-0.5" />
            <span class="text-[11.5px] text-ink-700 leading-snug">{{ __('I agree to') }}
                {{ $__brandName }}{{ __("'s") }} <a class="text-wa-deep font-semibold hover:underline"
                    href="{{ legal_url('terms') }}" target="_blank">{{ __('Terms') }}</a> and <a
                    class="text-wa-deep font-semibold hover:underline" href="{{ legal_url('privacy') }}"
                    target="_blank">{{ __('Privacy policy') }}</a>.</span>
        </label>

        <button type="submit"
            class="w-full px-4 py-3 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center justify-center gap-2 mt-1">
            {{ __('Continue / Set up workspace') }}
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="M3 8h10M9 4l4 4-4 4" />
            </svg>
        </button>

        <p class="text-[12px] text-ink-600 text-center">{{ __('Already have an account?') }} <a href="{{ url('/login') }}"
                class="text-wa-deep font-semibold hover:underline">{{ __('Sign in') }}</a></p>
    </form>
</x-auth-shell>
