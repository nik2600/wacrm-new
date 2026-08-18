{{-- Forgot-password form column — shared by the original (variant 1) layout AND
     the <x-auth-shell> variants 2–5. Wrapper supplies chrome + logo. --}}
<div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Forgot password') }}</div>
<h2 class="font-serif text-[34px] leading-tight tracking-[-0.01em]">{{ __('Reset your') }} <span
        class="italic text-wa-deep">{{ __('password') }}</span>.</h2>
<p class="text-[13px] text-ink-600 mt-2">{{ __("Tell us your email and we'll send a one-time reset link.") }}</p>

@if ($errors->any())
    <div class="mt-5 mb-1 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
        @foreach ($errors->all() as $err)
            <div>{{ $err }}</div>
        @endforeach
    </div>
@endif
@if (session('status'))
    <div class="mt-5 mb-1 rounded-lg border border-wa-green/40 bg-wa-mint text-wa-deep px-3 py-2 text-[12.5px]">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('password.email') }}" class="space-y-4 mt-5">
    @csrf
    <div>
        <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Email') }}</label>
        <input name="email" type="email" required value="{{ old('email') }}" autocomplete="email" autofocus placeholder="you@company.com"
            class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
    </div>

    <button type="submit"
        class="w-full px-4 py-3 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center justify-center gap-2 mt-2">
        <span>{{ __('Email reset link') }}</span>
        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 8h10M9 4l4 4-4 4" /></svg>
    </button>

    <p class="text-[12.5px] text-ink-600 text-center mt-2">{{ __('Remembered it?') }}
        <a href="{{ route('login') }}" class="text-wa-deep font-semibold hover:underline">{{ __('Back to sign in') }}</a></p>
</form>
