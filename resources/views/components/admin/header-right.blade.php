{{-- Admin header right-side controls.
 The admin layout renders this once and a small inline script
 moves it into every page's [data-admin-header-right] slot.
 Same control set as the user dashboard header so admin gets
 theme toggle, notifications, account menu, and a quick "Back
 to app" shortcut without each admin view having to inline them. --}}
@php
    $au = auth()->user();
    $base = $au ? ($au->name ?: $au->email) : '';
    $parts = preg_split('/\s+/', trim((string) $base));
    $initials = $au ? strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1)) : 'WA';

    // Role label shown under the user's name in the avatar button.
// Tries Spatie roles first (Super Admin / Admin) and falls back to
// the legacy users.role string — never blank, never hardcoded.
$roleLabel = 'Member';
if ($au) {
    try {
        if (method_exists($au, 'getRoleNames')) {
            $names = $au->getRoleNames();
            if ($names && $names->count()) {
                $roleLabel = (string) $names->first();
            }
        }
    } catch (\Throwable $e) {
    }
    if ($roleLabel === 'Member' && !empty($au->role)) {
        $roleLabel = ucwords(str_replace(['_', '-'], ' ', (string) $au->role));
    }
}

// Lightweight platform-health probe powering the "All systems normal"
// pill. DB ping + queue freshness — no remote calls, no slow checks.
// Status: 'ok' | 'warn' | 'down'.
$sysStatus = 'ok';
$sysLabel = __('All systems normal');
try {
    \DB::connection()->getPdo();
} catch (\Throwable $e) {
    $sysStatus = 'down';
    $sysLabel = __('Database unreachable');
}
if ($sysStatus === 'ok') {
    try {
        $stuck = \DB::table('jobs')
            ->where('reserved_at', '<', now()->subMinutes(10)->timestamp)
            ->count();
        if ($stuck > 0) {
            $sysStatus = 'warn';
            $sysLabel = $stuck . ' ' . __('stuck jobs');
        }
    } catch (\Throwable $e) {
        /* no jobs table — fine */
    }
}
$sysDot = $sysStatus === 'ok' ? 'bg-wa-green' : ($sysStatus === 'warn' ? 'bg-accent-amber' : 'bg-accent-coral');
@endphp

<div class="flex items-center gap-2" data-admin-header-controls>
    {{-- 0. Platform health pill — single source of truth for "are we
 up?" in the chrome. Clicks through to /admin/security where
 the deeper queue/job stats live. --}}
    <a href="{{ url('/admin/security') }}" title="{{ __('Platform health') }}"
        class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium">
        <span class="w-1.5 h-1.5 rounded-full {{ $sysDot }}"></span>
        <span>{{ $sysLabel }}</span>
    </a>

    {{-- 1. Back-to-app shortcut --}}
    <a href="{{ url('/dashboard') }}"
        class="w-9 h-9 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
        title="{{ __('Back to app') }}">
        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M9 4 5 8l4 4M5 8h8" />
        </svg>
    </a>

    {{-- 2. Theme toggle (uses the same id+listener as user dashboard) --}}
    <button id="wa-theme-btn" type="button"
        class="w-9 h-9 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
        title="{{ __('Theme') }}">
        <svg id="wa-theme-icon" viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none"
            stroke="currentColor" stroke-width="1.5">
            <path d="M8 1a7 7 0 1 0 7 7 5.5 5.5 0 0 1-7-7z" />
        </svg>
    </button>

    {{-- 2a. Language switcher --}}
    <x-locale-switcher />

    {{-- 3. Notification bell + dropdown --}}
    <div class="relative" data-notif-wrap>
        <button id="notif-toggle" type="button"
            class="relative w-9 h-9 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
            title="{{ __('Notifications') }}">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor"
                stroke-width="1.5">
                <path d="M8 1.5a4 4 0 0 0-4 4v2.4L2.7 11h10.6L12 7.9V5.5a4 4 0 0 0-4-4z" />
                <path d="M6.5 12.5a1.5 1.5 0 0 0 3 0" />
            </svg>
            <span id="notif-badge"
                class="hidden absolute -top-0.5 -right-0.5 min-w-[16px] h-[16px] px-1 rounded-full bg-accent-coral text-paper-0 text-[9.5px] font-bold leading-[16px] text-center">0</span>
        </button>
        {{-- Mobile: pin to the viewport (left-3/right-3) so a wide panel can't
             clip off the screen edge. Desktop: the normal anchored dropdown. --}}
        <div id="notif-pane"
            class="hidden fixed left-3 right-3 top-16 w-auto md:absolute md:inset-auto md:right-0 md:left-auto md:top-auto md:mt-2 md:w-[360px] bg-paper-0 border border-paper-200 rounded-2xl shadow-soft overflow-hidden z-50">
            <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between gap-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Inbox') }}
                    </div>
                    <div class="font-serif text-[16px] text-ink-900">{{ __('Notifications') }}</div>
                </div>
                <button id="notif-read-all" type="button"
                    class="text-[11px] font-semibold text-wa-deep hover:underline">{{ __('Mark all read') }}</button>
            </div>
            <div id="notif-list" class="max-h-[420px] overflow-y-auto divide-y divide-paper-100">
                <div class="px-4 py-10 text-center text-[12px] text-ink-500">{{ __('Loading…') }}</div>
            </div>
            <div class="px-4 py-2.5 border-t border-paper-200 flex items-center justify-between bg-paper-50/60">
                <button id="notif-clear" type="button"
                    class="text-[11.5px] font-semibold text-accent-coral hover:underline">{{ __('Clear all') }}</button>
                <a href="{{ url('/admin/notifications') }}"
                    class="text-[11.5px] font-semibold text-wa-deep hover:underline">{{ __('View all →') }}</a>
            </div>
        </div>
    </div>

    {{-- 4. User avatar + menu — name + role stacked next to the
 avatar, matching the prototype's "Vetrick R. / Super admin"
 pattern. Both lines are dynamic. --}}
    <div class="relative ml-1" data-user-menu>
        <button type="button" data-user-toggle
            class="flex items-center gap-2 pl-1 pr-3 py-1 rounded-full hover:bg-paper-50">
            <span
                class="w-9 h-9 rounded-full bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 text-[12px] font-semibold flex items-center justify-center">{{ $initials ?: 'WA' }}</span>
            @if ($au)
                <span class="text-left hidden md:block">
                    <span
                        class="block text-[13px] font-semibold leading-tight">{{ $au->name ?: explode('@', (string) $au->email)[0] }}</span>
                    <span class="block text-[11px] text-ink-500 leading-tight">{{ $roleLabel }}</span>
                </span>
            @endif
            <svg class="w-3 h-3 text-ink-500" viewBox="0 0 12 12" fill="none" stroke="currentColor"
                stroke-width="1.5">
                <path d="M3 5l3 3 3-3" />
            </svg>
        </button>
        @if ($au)
            <div data-user-pane
                class="hidden absolute right-0 mt-2 w-[240px] bg-paper-0 border border-paper-200 rounded-2xl shadow-soft p-2 z-30">
                <div class="px-2 py-1.5 text-[12px] text-ink-500 truncate">{{ $au->email }}</div>
                <a href="{{ url('/account') }}"
                    class="block px-2 py-2 rounded-xl hover:bg-paper-50 text-[13px]">{{ __('My account') }}</a>
                <a href="{{ url('/settings') }}"
                    class="block px-2 py-2 rounded-xl hover:bg-paper-50 text-[13px]">{{ __('Settings') }}</a>
                <a href="{{ url('/admin') }}"
                    class="block px-2 py-2 rounded-xl hover:bg-paper-50 text-[13px]">{{ __('Admin overview') }}</a>
                <div class="border-t border-paper-200 my-1"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="w-full text-left px-2 py-2 rounded-xl hover:bg-accent-coral/10 text-[13px] text-accent-coral font-semibold">{{ __('Sign out') }}</button>
                </form>
            </div>
        @endif
    </div>
</div>
