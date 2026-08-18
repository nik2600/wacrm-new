<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Workspace suspended') }} · {{ brand_name() }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-paper-50 text-ink-900 antialiased grid place-items-center px-4">
    <div class="w-full max-w-md text-center">
        <div class="mx-auto w-14 h-14 rounded-2xl bg-accent-coral/12 grid place-items-center mb-5">
            <svg viewBox="0 0 24 24" class="w-7 h-7 text-accent-coral" fill="none" stroke="currentColor" stroke-width="1.7">
                <circle cx="12" cy="12" r="9" /><path d="M12 7v6M12 16.5v.5" />
            </svg>
        </div>
        <h1 class="font-serif text-[26px] leading-tight">{{ __('This workspace is suspended') }}</h1>
        <p class="text-[13.5px] text-ink-600 mt-3 leading-relaxed">
            {{ __('Access to') }} <span class="font-semibold">{{ $workspace->name ?? __('your workspace') }}</span>
            {{ __('has been paused by the') }} {{ brand_name() }} {{ __('team. If you think this is a mistake, please contact support to have it reviewed.') }}
        </p>

        <div class="mt-6 flex items-center justify-center gap-3">
            <a href="{{ url('/support') }}"
               class="px-4 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold">
                {{ __('Contact support') }}
            </a>
            <form method="POST" action="{{ url('/logout') }}">
                @csrf
                <button type="submit"
                    class="px-4 py-2.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-100 text-ink-700 text-[13px] font-semibold">
                    {{ __('Sign out') }}
                </button>
            </form>
        </div>

        <p class="text-[11px] text-ink-400 mt-6 font-mono uppercase tracking-wider">{{ brand_name() }}</p>
    </div>
</body>
</html>
