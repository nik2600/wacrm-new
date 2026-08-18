<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — IP not allowed | {{ brand_name() }}</title>
    @vite(['resources/css/app.css'])
</head>

<body class="min-h-screen flex items-center justify-center bg-paper-50 font-sans text-ink-900 p-6">
    <div class="max-w-md w-full bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-8">
        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-accent-coral mb-2">{{ __('Access blocked') }}
        </div>
        <h1 class="font-serif font-normal tracking-[-0.01em] text-[32px] leading-tight">
            {{ __('Your IP is not on the allowlist.') }}</h1>
        <p class="text-[13px] text-ink-600 mt-3">
            {{ __("An administrator has restricted admin-panel access to specific IP ranges, and the address you're connecting from is not allowed.") }}
        </p>
        <div class="mt-4 rounded-xl bg-paper-50 border border-paper-200 p-4 text-[12px]">
            <div class="font-semibold mb-1">{{ __('Your IP') }}</div>
            <div class="font-mono text-ink-700">{{ $ip ?? 'unknown' }}</div>
        </div>
        <div class="mt-5 text-[12.5px] text-ink-600">
            If this is unexpected, ask a super-admin to add your IP at
            <code class="font-mono text-[11px] bg-paper-50 px-1.5 py-0.5 rounded">/admin/security</code> → API and
            webhooks → IP allowlist.
        </div>
        <div class="mt-6 flex flex-wrap items-center gap-2">
            <a href="{{ url('/dashboard') }}"
                class="px-4 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[12.5px] font-semibold">{{ __('Go to dashboard') }}</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">{{ __('Sign out') }}</button>
            </form>
        </div>
    </div>
</body>

</html>
