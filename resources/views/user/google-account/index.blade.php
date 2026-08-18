@php
    $profileEmail = (string) ($oauth['profile']['email'] ?? '');
    $profileName = (string) ($oauth['profile']['name'] ?? '');
    $profilePic = (string) ($oauth['profile']['picture'] ?? '');
    $connectedAt = (string) ($oauth['connected_at'] ?? '');
    $scopes = (array) ($oauth['scopes'] ?? []);
    if (empty($scopes) && !empty($oauth['scope'] ?? null)) {
        $scopes = preg_split('/\s+/', (string) $oauth['scope']);
    }
    $calendarId = (string) ($oauth['calendar_id'] ?? '');
    $expiresAt = (string) ($oauth['expires_at'] ?? '');
    $hasRefresh = !empty($oauth['refresh_token'] ?? null);
    $workspaceName = optional($workspace)->name ?: 'Workspace';

    // Scope-by-scope check for the new flow nodes (Sheets / Docs / Forms).
    // Existing Calendar-only connections will be missing these; surface a
    // re-consent banner so the operator knows to click reconnect.
    $integrationScopes = [
        'sheets' => 'https://www.googleapis.com/auth/spreadsheets',
        'docs' => 'https://www.googleapis.com/auth/documents',
        'drive' => 'https://www.googleapis.com/auth/drive',
        'forms' => 'https://www.googleapis.com/auth/forms.body.readonly',
    ];
    $missingScopes = [];
    if ($isConnected) {
        foreach ($integrationScopes as $key => $url) {
            if (!in_array($url, $scopes, true)) {
                $missingScopes[] = $key;
            }
        }
    }
@endphp

<x-layouts.user :title="__('Google account')" nav-key="more" page="user-google-account-index">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">

        @if (session('success'))
            <div
                class="mb-4 bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div
                class="mb-4 bg-accent-coral/10 border border-accent-coral/30 rounded-lg px-4 py-2 text-[12.5px] text-accent-coral font-mono">
                {{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            {{-- ── Left aside ───────────────────────────────────────────────── --}}
            <aside class="space-y-3">
                <x-side-tip>
                    {{ __("One Google sign-in unlocks Calendar slot pickers, Google Meet links inside flows, and the team-inbox composer's Send Meet button. Connect once per workspace.") }}
                </x-side-tip>

                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Connection') }}</div>
                    <div
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $isConnected ? 'bg-wa-deep text-paper-0 font-semibold' : 'text-ink-700' }}">
                        <span class="flex items-center gap-2">
                            <span
                                class="w-2 h-2 rounded-full {{ $isConnected ? 'bg-wa-green' : 'bg-paper-200' }}"></span>
                            {{ $isConnected ? __('Connected') : __('Not connected') }}
                        </span>
                    </div>
                </div>

                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Used by') }}</div>
                    <a href="{{ url('/flows/builder') }}"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] text-ink-700 hover:bg-paper-50">
                        <span class="flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-[#1A73E8]" fill="none"
                                stroke="currentColor" stroke-width="1.7">
                                <rect x="3" y="4" width="10" height="9" rx="1.5" />
                                <path d="M3 6h10M5 2v3M11 2v3M6 9h2M9 9h1" />
                            </svg>
                            {{ __('BookAppointment node') }}
                        </span>
                        <span class="font-mono text-[11px] text-ink-500">{{ __('flow') }}</span>
                    </a>
                    <a href="{{ url('/flows/builder') }}"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] text-ink-700 hover:bg-paper-50">
                        <span class="flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-[#1A73E8]" fill="none"
                                stroke="currentColor" stroke-width="1.7">
                                <rect x="2" y="5" width="8" height="6" rx="1" />
                                <path d="M10 7l4-2v6l-4-2z" />
                            </svg>
                            {{ __('Google Meet node') }}
                        </span>
                        <span class="font-mono text-[11px] text-ink-500">{{ __('flow') }}</span>
                    </a>
                    <a href="{{ url('/team-inbox') }}"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] text-ink-700 hover:bg-paper-50">
                        <span class="flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-[#1A73E8]" fill="none"
                                stroke="currentColor" stroke-width="1.7">
                                <rect x="2" y="5" width="8" height="6" rx="1" />
                                <path d="M10 7l4-2v6l-4-2z" />
                            </svg>
                            {{ __('Send Meet link') }}
                        </span>
                        <span class="font-mono text-[11px] text-ink-500">{{ __('inbox') }}</span>
                    </a>
                    <a href="{{ url('/flows/builder') }}"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] text-ink-700 hover:bg-paper-50">
                        <span class="flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-[#137333]" fill="none"
                                stroke="currentColor" stroke-width="1.7">
                                <rect x="3" y="2" width="10" height="12" rx="1" />
                                <path d="M3 5h10M3 8h10M3 11h10M6.5 5v9M9.5 5v9" />
                            </svg>
                            {{ __('Google Sheets node') }}
                        </span>
                        <span class="font-mono text-[11px] text-ink-500">{{ __('flow') }}</span>
                    </a>
                    <a href="{{ url('/flows/builder') }}"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] text-ink-700 hover:bg-paper-50">
                        <span class="flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-[#1A73E8]" fill="none"
                                stroke="currentColor" stroke-width="1.7">
                                <path d="M3 2h7l3 3v9H3zM10 2v3h3M5 8h6M5 10h6M5 12h4" />
                            </svg>
                            {{ __('Google Docs node') }}
                        </span>
                        <span class="font-mono text-[11px] text-ink-500">{{ __('flow') }}</span>
                    </a>
                    <a href="{{ url('/flows/builder') }}"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] text-ink-700 hover:bg-paper-50">
                        <span class="flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-[#7B2CBF]" fill="none"
                                stroke="currentColor" stroke-width="1.7">
                                <rect x="3" y="2" width="10" height="12" rx="1" />
                                <path d="M5 5h2M5 8h2M5 11h2M9 5h2M9 8h2M9 11h2" />
                            </svg>
                            {{ __('Google Forms node') }}
                        </span>
                        <span class="font-mono text-[11px] text-ink-500">{{ __('flow') }}</span>
                    </a>
                </div>

                <div
                    class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-wa-green"></span>{{ __('Privacy tip') }}
                    </div>
                    {{ __(':app requests Calendar, Sheets, Docs, Drive, and Forms scopes. Drive access lets the flow builder list your existing Sheets, Docs, and Forms so you can pick them, and lets the Docs node copy a template you choose.', ['app' => brand_name()]) }}
                </div>
            </aside>

            {{-- ── Right main ──────────────────────────────────────────────── --}}
            <section class="space-y-5">

                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            {{ __('Workspace') }} · {{ $workspaceName }}</div>
                        <h1 class="font-serif font-normal tracking-tight text-[32px] sm:text-[38px] lg:text-[44px] leading-none">{{ __('Google') }}
                            <span class="italic text-wa-deep">{{ __('account') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2">
                            {{ __('Connect once — powers Calendar, Meet link generation in flows, and the inbox composer.') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        @if ($isConnected)
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono">
                                <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>
                                {{ __('connected') }}
                            </span>
                            <form action="{{ route('user.appointments.gcal.disconnect') }}" method="POST"
                                class="inline"
                                onsubmit="return confirm('Disconnect Google? Flow nodes that depend on it (BookAppointment, Google Meet) and the inbox Send Meet button will stop working until you reconnect.');">
                                @csrf
                                <button type="submit"
                                    class="px-4 py-2 border border-accent-coral/40 text-accent-coral rounded-full bg-paper-0 hover:bg-accent-coral/10 text-[12px] font-medium">{{ __('Disconnect') }}</button>
                            </form>
                        @else
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-100 text-ink-500 border border-paper-200 font-mono">
                                <span class="w-1.5 h-1.5 rounded-full bg-paper-200"></span>
                                {{ __('not connected') }}
                            </span>
                            <form action="{{ route('user.appointments.gcal.start') }}" method="POST"
                                class="inline">
                                @csrf
                                <button type="submit" {{ $appReady ? '' : 'disabled' }}
                                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                        stroke="currentColor" stroke-width="2">
                                        <path d="M8 1v6M5 4l3-3 3 3M3 9v4a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9" />
                                    </svg>
                                    {{ __('Connect Google account') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- 4-stat row matching /devices --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="flex items-center justify-between"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Status') }}</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2"><span
                                class="font-serif text-[30px] leading-none {{ $isConnected ? '' : 'text-ink-500' }}">{{ $isConnected ? __('live') : __('idle') }}</span>
                        </div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="flex items-center justify-between"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Calendars') }}</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2"><span
                                class="font-serif text-[30px] leading-none">{{ count($calendars) }}</span><span
                                class="text-[11px] text-ink-500">{{ __('available') }}</span></div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="flex items-center justify-between"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Scopes') }}</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2"><span
                                class="font-serif text-[30px] leading-none">{{ count($scopes) }}</span><span
                                class="text-[11px] text-ink-500">{{ __('granted') }}</span></div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="flex items-center justify-between"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Refresh') }}</span><span
                                class="text-[10px] {{ $hasRefresh ? 'text-wa-deep' : 'text-accent-coral' }} font-mono">{{ $hasRefresh ? __('ready') : __('missing') }}</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2"><span
                                class="font-serif text-[30px] leading-none">{{ $hasRefresh ? __('auto') : __('re-auth') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Admin-side OAuth check banner --}}
                @if (!$appReady)
                    <div
                        class="rounded-[14px] border border-accent-coral/30 bg-accent-coral/10 text-[#A1431F] px-4 py-3 text-[12.5px]">
                        <strong class="font-semibold">{{ __('Admin setup needed:') }}</strong>
                        Platform-level Google OAuth client isn't configured. Ask the admin to set client ID + secret in
                        <code class="px-1 bg-paper-0 rounded font-mono">/admin/settings → Integrations → Google</code>
                        before this page can connect.
                    </div>
                @endif

                {{-- Missing-scope re-consent banner. Existing Calendar-only
 connections need to reconnect once for the new Sheets / Docs
 / Forms flow nodes to work. The same Connect button triggers
 the OAuth flow with all-new scopes; Google will show the user
 what extra access they're granting. --}}
                @if ($isConnected && !empty($missingScopes))
                    <div
                        class="rounded-[14px] border border-[#B45309]/30 bg-[#FFFBEB] text-[#92400E] px-4 py-3 text-[12.5px] flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <strong class="font-semibold">{{ __('Reconnect to unlock new flow nodes:') }}</strong>
                            Your Google account is connected but missing scopes for
                            @foreach ($missingScopes as $i => $m)
                                <span class="font-mono px-1 bg-paper-0 rounded">{{ $m }}</span>
                                @if ($i + 1 < count($missingScopes))
                                    @if ($i + 2 === count($missingScopes))
                                    and @else,
                                    @endif
                                @endif
                            @endforeach.
                            Click
                            <strong>{{ __('Reconnect') }}</strong> to grant them. Existing Calendar/Meet features keep
                            working either way.
                        </div>
                        <form action="{{ route('user.appointments.gcal.start') }}" method="POST"
                            class="inline shrink-0">
                            @csrf
                            <button type="submit"
                                class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold hover:bg-wa-teal whitespace-nowrap">{{ __('Reconnect') }}</button>
                        </form>
                    </div>
                @endif

                {{-- Connection table — matches /devices grid card layout --}}
                <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('workspace google identity') }}</span>
                        </div>
                        @if ($isConnected && $expiresAt)
                            <span class="font-mono text-[10.5px] text-ink-500">token expires
                                {{ \Illuminate\Support\Carbon::parse($expiresAt)->diffForHumans() }}</span>
                        @endif
                    </div>

                    <div class="overflow-x-auto">
                    {{-- Column header strip — matches the row template below --}}
                    <div
                        class="px-4 py-2.5 grid grid-cols-[40px_1.6fr_1.2fr_1.4fr_140px_180px] min-w-[760px] items-center gap-3 border-b border-paper-200 bg-paper-50 font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                        <div></div>
                        <div>{{ __('Account') }}</div>
                        <div>{{ __('Calendar') }}</div>
                        <div>{{ __('Scopes') }}</div>
                        <div>{{ __('Connected') }}</div>
                        <div class="text-right pr-2">{{ __('Actions') }}</div>
                    </div>

                    @if ($isConnected)
                        <div
                            class="px-4 py-3.5 grid grid-cols-[40px_1.6fr_1.2fr_1.4fr_140px_180px] min-w-[760px] items-center gap-3 hover:bg-paper-50 transition border-b border-paper-200">
                            <div>
                                @if ($profilePic)
                                    <img src="{{ $profilePic }}" alt=""
                                        class="w-8 h-8 rounded-full border border-paper-200" />
                                @else
                                    <span
                                        class="w-8 h-8 rounded-full bg-wa-deep text-paper-0 grid place-items-center text-[12px] font-semibold">{{ strtoupper(substr($profileEmail ?: 'G', 0, 1)) }}</span>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="font-semibold text-[13px] text-ink-900 truncate">
                                    {{ $profileName ?: ($profileEmail ?: __('Google user')) }}</div>
                                <div class="font-mono text-[11px] text-ink-500 truncate">{{ $profileEmail ?: '—' }}
                                </div>
                            </div>
                            <div class="min-w-0">
                                @php $picked = collect($calendars)->firstWhere('id', $calendarId); @endphp
                                <div class="text-[12.5px] text-ink-900 truncate">
                                    {{ $picked['summary'] ?? ($calendarId ?: 'primary') }}</div>
                                <div class="font-mono text-[10.5px] text-ink-500 truncate">
                                    {{ $calendarId ?: 'primary' }}</div>
                            </div>
                            <div>
                                @if (!empty($scopes))
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach (array_slice($scopes, 0, 4) as $s)
                                            @php $short = preg_replace('#^https?://www\.googleapis\.com/auth/#', '', (string) $s); @endphp
                                            <span
                                                class="px-1.5 py-0.5 rounded bg-paper-100 text-ink-700 font-mono text-[10px]">{{ $short }}</span>
                                        @endforeach
                                        @if (count($scopes) > 4)
                                            <span
                                                class="px-1.5 py-0.5 rounded bg-paper-100 text-ink-500 font-mono text-[10px]">+{{ count($scopes) - 4 }}</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-ink-500 text-[12px]">—</span>
                                @endif
                            </div>
                            <div class="font-mono text-[11.5px] text-ink-700">
                                {{ $connectedAt ? \Illuminate\Support\Carbon::parse($connectedAt)->diffForHumans() : '—' }}
                            </div>
                            <div class="text-right pr-2 flex items-center justify-end gap-2">
                                @if (count($calendars) > 1)
                                    <a href="{{ route('user.appointments.settings') }}"
                                        class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-medium">{{ __('Change calendar') }}</a>
                                @endif
                                <form action="{{ route('user.appointments.gcal.disconnect') }}" method="POST"
                                    class="inline" onsubmit="return confirm('Disconnect this Google account?');">
                                    @csrf
                                    <button type="submit"
                                        class="px-3 py-1.5 rounded-full border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/10 text-[11.5px] font-semibold">{{ __('Disconnect') }}</button>
                                </form>
                            </div>
                        </div>
                    @else
                        {{-- Empty state row — single Connect CTA spanning the row --}}
                        <div class="px-4 py-10 text-center">
                            <span
                                class="inline-flex w-12 h-12 rounded-2xl bg-[#E8F0FE] text-[#1A73E8] items-center justify-center mb-3">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M14 8a6 6 0 1 1-2-4.5M14 3v3h-3" />
                                </svg>
                            </span>
                            <div class="font-serif text-[18px] leading-tight mb-1">
                                {{ __('No Google account linked') }}</div>
                            <p class="text-[12.5px] text-ink-500 max-w-[420px] mx-auto mb-4">
                                {{ __("Until you connect, BookAppointment and Google Meet nodes can't run and the inbox Send Meet button is hidden.") }}
                            </p>
                            <form action="{{ route('user.appointments.gcal.start') }}" method="POST"
                                class="inline">
                                @csrf
                                <button type="submit" {{ $appReady ? '' : 'disabled' }}
                                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold hover:bg-wa-teal inline-flex items-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                        stroke="currentColor" stroke-width="2">
                                        <path d="M8 1v6M5 4l3-3 3 3M3 9v4a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9" />
                                    </svg>
                                    {{ __('Connect Google account') }}
                                </button>
                            </form>
                        </div>
                    @endif
                    </div>

                    <div
                        class="px-4 py-3 border-t border-paper-200 flex items-center justify-between text-[12px] text-ink-500">
                        <div>{{ __('Showing') }} <span
                                class="font-mono text-ink-900">{{ $isConnected ? 1 : 0 }}</span> of <span
                                class="font-mono text-ink-900">1</span></div>
                        <div class="font-mono text-[10.5px]">{{ __('Workspace cap: 1 Google account per workspace') }}
                        </div>
                    </div>
                </div>

                {{-- 3 help cards — matches /devices footer --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Help - 01') }}</div>
                        <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                            {{ __('What does this unlock?') }}</div>
                        <p class="text-[12.5px] text-ink-600 leading-relaxed">
                            {{ __('Calendar slot pickers for BookAppointment, on-demand Google Meet links for the Meet node and the inbox composer, plus future Google Workspace nodes.') }}
                        </p>
                    </div>
                    <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Help - 02') }}</div>
                        <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                            {{ __('Whose account is used?') }}</div>
                        <p class="text-[12.5px] text-ink-600 leading-relaxed">
                            {{ __("The Google account belongs to whoever clicks Connect first. Calendar events + Meet links appear in that user's Google Calendar — typically the workspace owner.") }}
                        </p>
                    </div>
                    <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Help - 03') }}</div>
                        <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                            {{ __('What if I disconnect?') }}</div>
                        <p class="text-[12.5px] text-ink-600 leading-relaxed">
                            {{ __('Existing flow JSON stays intact, but any in-flight runs hitting a Calendar or Meet node will fail. Reconnect anytime — the same token store + node configs pick up immediately.') }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </main>

</x-layouts.user>
