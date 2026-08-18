{{-- Registration OTP verify — same auth-shell as the register steps. --}}
<x-auth-shell page="register" :title="__('Verify your number')">

    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
        {{ __('Step 1 of 3 / Verify') }}</div>
    <h2 class="font-serif text-[30px] leading-tight tracking-[-0.01em]">{{ __('Verify your') }} <span
            class="italic text-wa-deep">{{ __('WhatsApp') }}</span>.</h2>
    <p class="text-[12.5px] text-ink-600 mt-1.5">
        {{ __('We sent a code to') }} <span class="font-semibold text-ink-900">{{ $masked }}</span>.
        {{ __('Enter it below to finish creating your account.') }}
    </p>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-wa-green/40 bg-wa-mint px-3 py-2 text-[12px] text-wa-deep">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
            @foreach ($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ url('/register/verify') }}" class="space-y-4 mt-5">
        @csrf
        <div>
            <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Verification code') }}</label>
            <input required type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                maxlength="8" autofocus placeholder="123456"
                class="w-full px-3 py-3 border border-paper-200 rounded-lg bg-paper-0 text-center text-[22px] tracking-[0.4em] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
            <div class="text-[10.5px] text-ink-500 mt-1.5">
                {{ __('The code expires in :m minutes.', ['m' => $ttlMinutes]) }}</div>
        </div>

        <button type="submit"
            class="w-full px-4 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center justify-center gap-2">
            {{ __('Verify & continue') }}
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="M3 8h10M9 4l4 4-4 4" />
            </svg>
        </button>
    </form>

    <div class="mt-5 flex items-center justify-between text-[12px]">
        <form method="POST" action="{{ route('register.verify.resend') }}">
            @csrf
            <button type="submit" class="text-wa-deep font-semibold hover:underline">
                {{ __('Resend code') }}</button>
        </form>
        <a href="{{ route('register') }}" class="text-ink-500 hover:text-ink-900">
            {{ __('Wrong number? Go back') }}</a>
    </div>

</x-auth-shell>
