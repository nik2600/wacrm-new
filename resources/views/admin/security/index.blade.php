<x-layouts.admin :title="__('Security')" admin-key="security" page="admin-security-index">

    @php
        // Helper closures so the markup below stays readable.
        $bool = fn($k) => (bool) ($policy[$k] ?? false);
        $intv = fn($k) => (int) ($policy[$k] ?? 0);
        $strv = fn($k) => (string) ($policy[$k] ?? '');
        $list = fn($k) => is_array($policy[$k] ?? null) ? implode("\n", $policy[$k]) : '';
        $sevPill = function ($sev) {
            return match ($sev) {
                'high' => [
                    'bg' => 'bg-accent-coral/10 text-accent-coral border border-accent-coral/30',
                    'row' => 'bg-accent-coral/5',
                ],
                'medium' => ['bg' => 'bg-accent-amber/15 text-accent-amber', 'row' => 'bg-accent-amber/5'],
                'watch' => ['bg' => 'bg-paper-100 text-ink-700', 'row' => ''],
                default => ['bg' => 'bg-wa-bubble text-wa-deep', 'row' => ''],
            };
        };
    @endphp

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Security') }}</span>
        </div>
        <div class="relative flex-1 max-w-[520px] ml-4">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500"
                fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="7" cy="7" r="5" />
                <path d="m11 11 3 3" />
            </svg>
            <input
                class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition"
                placeholder="{{ __('Search security rules, IPs, actors...') }}" />
            <kbd
                class="absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded-md bg-paper-0 border border-paper-200 text-[10px] font-mono text-ink-500">{{ __('CMD K') }}</kbd>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.security.update') }}" data-security-form>
        @csrf
        @method('PATCH')

        <main class="px-4 sm:px-6 lg:px-7 py-6 space-y-5" data-wa-tab-scope>

            {{-- Save feedback. Without this the page had NO error surface at all:
                 a validation failure or an expired-CSRF bounce both just re-rendered
                 the form unchanged, so every failure looked like "nothing happened".
                 The header flash is inline + easy to miss when scrolled down. --}}
            @if ($errors->any())
                <div class="rounded-xl px-4 py-3 bg-accent-coral/10 border border-accent-coral/30 text-accent-coral text-[12.5px]">
                    <div class="flex items-center gap-2 font-semibold">
                        <svg viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="8" cy="8" r="6" />
                            <path d="M8 5v3.5M8 11v.5" />
                        </svg>
                        {{ __('Nothing was saved — :count field(s) were rejected.', ['count' => $errors->count()]) }}
                    </div>
                    <ul class="mt-2 ml-6 list-disc font-mono text-[11.5px] space-y-0.5">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('status'))
                <div class="rounded-xl px-4 py-3 bg-wa-mint border border-wa-green/30 text-wa-deep text-[12.5px] font-mono flex items-center gap-2.5">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="8" cy="8" r="6" />
                        <path d="M5.5 8.5l2 2 3-4" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif


            <section class="bg-paper-0 border border-paper-200 rounded-2xl px-5 py-4 shadow-card">
                <div class="flex flex-wrap items-center justify-between gap-5">
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-1.5">
                            {{ __('Security center') }}</div>
                        <h1 class="font-serif font-normal tracking-[-0.01em] text-[26px] sm:text-[30px] lg:text-[34px] leading-[1.0]">
                            {{ __('Security and') }} <span class="italic text-wa-deep">{{ __('compliance') }}</span>
                        </h1>
                        <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">
                            {{ __('Protect admin access, WhatsApp sending, devices, webhooks, and user activity. Toggles persist as') }}
                            <span class="font-mono">{{ __('security.*') }}</span> settings — every change is logged to
                            the audit trail.</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ url('/admin/audit-log') }}"
                            class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-semibold tracking-[0.08em] uppercase">{{ __('View audit logs') }}</a>
                        <x-admin.flash inline />
                        <button type="submit"
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold tracking-[0.08em] uppercase">{{ __('Save settings') }}</button>
                    </div>
                </div>
            </section>

            {{-- ── KPI strip (always visible) ── --}}
            <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                    <div class="text-[11px] text-ink-600 font-medium">{{ __('Security score') }}</div>
                    <div class="font-serif text-[31px] leading-none mt-2">{{ $kpis['security_score'] }}</div>
                    <div class="text-[11px] text-wa-deep mt-2">
                        {{ $kpis['security_score'] >= 70 ? 'strong controls active' : 'attention needed' }}</div>
                </div>
                <div
                    class="bg-paper-0 border {{ $kpis['open_risks'] > 0 ? 'border-accent-coral/40' : 'border-paper-200' }} rounded-2xl p-4 shadow-card">
                    <div class="text-[11px] text-ink-600 font-medium">{{ __('Open risks') }}</div>
                    <div
                        class="font-serif text-[31px] leading-none mt-2 {{ $kpis['open_risks'] > 0 ? 'text-accent-coral' : '' }}">
                        {{ $kpis['open_risks'] }}</div>
                    <div class="text-[11px] {{ $kpis['open_risks'] > 0 ? 'text-accent-coral' : 'text-ink-500' }} mt-2">
                        {{ $kpis['high_priority'] }} {{ __('high priority') }}</div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="text-[11px] text-ink-600 font-medium">{{ __('Blocked attempts') }}</div>
                    <div class="font-serif text-[31px] leading-none mt-2">{{ $kpis['blocked_attempts'] }}</div>
                    <div class="text-[11px] text-ink-500 mt-2">{{ __('last 24 hours') }}</div>
                </div>
                <div
                    class="bg-paper-0 border {{ $kpis['campaign_holds'] > 0 ? 'border-accent-amber/40' : 'border-paper-200' }} rounded-2xl p-4 shadow-card">
                    <div class="text-[11px] text-ink-600 font-medium">{{ __('Campaign holds') }}</div>
                    <div class="font-serif text-[31px] leading-none mt-2">{{ $kpis['campaign_holds'] }}</div>
                    <div
                        class="text-[11px] {{ $kpis['campaign_holds'] > 0 ? 'text-accent-amber' : 'text-ink-500' }} mt-2">
                        {{ __('waiting review') }}</div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="text-[11px] text-ink-600 font-medium">{{ __('2FA coverage') }}</div>
                    <div class="font-serif text-[31px] leading-none mt-2">{{ $kpis['tfa_coverage'] }}%</div>
                    <div class="text-[11px] text-ink-500 mt-2">
                        {{ $kpis['tfa_enrolled'] }}/{{ $kpis['tfa_admins_total'] }} {{ __('admins enrolled') }}</div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="text-[11px] text-ink-600 font-medium">{{ __('Webhook failures') }}</div>
                    <div class="font-serif text-[31px] leading-none mt-2">{{ $kpis['webhook_failures'] }}%</div>
                    <div
                        class="text-[11px] {{ $kpis['webhook_failures'] > 1 ? 'text-accent-amber' : 'text-wa-deep' }} mt-2">
                        {{ $kpis['webhook_failures'] > 1 ? 'review' : 'normal' }}</div>
                </div>
            </section>

            {{-- ── Tabs ── --}}
            <section
                class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex items-center gap-1 shadow-card overflow-x-auto"
                data-wa-tabs>
                <button type="button" data-wa-tab="summary"
                    class="shrink-0 inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full bg-wa-deep text-paper-0 text-[13px] font-semibold">{{ __('Summary') }}</button>
                <button type="button" data-wa-tab="login"
                    class="shrink-0 inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Login and MFA') }}</button>
                <button type="button" data-wa-tab="whatsapp"
                    class="shrink-0 inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('WhatsApp guardrails') }}</button>
                <button type="button" data-wa-tab="abuse"
                    class="shrink-0 inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Abuse filters') }}</button>
                <button type="button" data-wa-tab="api"
                    class="shrink-0 inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('API and webhooks') }}</button>
                <button type="button" data-wa-tab="devices"
                    class="shrink-0 inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Devices') }}</button>
                <button type="button"
                    data-wa-tab="incidents" class="shrink-0 inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Danger zone') }}</button>
            </section>

            {{-- ── SUMMARY ── --}}
            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-5 items-start" data-wa-tab-panel="summary">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Priority queue') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Risk items to review') }}</h2>
                        </div>
                        <a href="{{ route('admin.audit-log.index', ['result' => 'failure']) }}"
                            class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-semibold">{{ __('See all in audit log') }}</a>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px] table-fixed">
                        <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                            <tr>
                                <th class="text-left px-4 py-2.5 w-[120px] font-medium">{{ __('Severity') }}</th>
                                <th class="text-left px-3 py-2.5 font-medium">{{ __('Signal') }}</th>
                                <th class="text-left px-3 py-2.5 w-[170px] font-medium">{{ __('Workspace') }}</th>
                                <th class="text-left px-3 py-2.5 w-[150px] font-medium">{{ __('Owner') }}</th>
                                <th class="text-right px-4 py-2.5 w-[120px] font-medium">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200">
                            @forelse ($risks as $r)
                                @php $p = $sevPill($r['severity']); @endphp
                                <tr class="hover:bg-paper-50/60 {{ $p['row'] }}">
                                    <td class="px-4 py-3"><span
                                            class="px-2 py-0.5 rounded-full {{ $p['bg'] }} text-[10px] font-mono">{{ $r['severity'] }}</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="font-semibold truncate">{{ $r['signal'] }}</div>
                                        @if ($r['detail'])
                                            <div class="font-mono text-[10.5px] text-ink-500 truncate">
                                                {{ $r['detail'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 font-semibold truncate">{{ $r['workspace'] }}</td>
                                    <td class="px-3 py-3 text-ink-600 truncate">{{ $r['owner'] }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.audit-log.index', ['q' => $r['signal']]) }}"
                                            class="rounded-full border border-paper-200 px-3 py-1 text-[11px] hover:bg-paper-50">{{ __('Inspect') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-ink-500 text-[13px]">
                                        {{ __('All clear — no failures or warnings in the last 7 days.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>

                <aside class="space-y-5 lg:sticky lg:top-20">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Coverage') }}</div>
                        <h2 class="font-serif text-[20px] leading-tight mt-1 mb-4">{{ __('Controls enabled') }}</h2>
                        <div class="space-y-3 text-[12px]">
                            @foreach ($controls as $c)
                                @php
                                    $col =
                                        $c['pct'] >= 75
                                            ? 'bg-wa-deep text-wa-deep'
                                            : ($c['pct'] >= 50
                                                ? 'bg-wa-teal text-wa-teal'
                                                : ($c['pct'] >= 25
                                                    ? 'bg-accent-amber text-accent-amber'
                                                    : 'bg-accent-coral text-accent-coral'));
                                    [$bg, $fg] = explode(' ', $col);
                                @endphp
                                <div>
                                    <div class="flex justify-between mb-1"><span>{{ $c['label'] }}</span><span
                                            class="font-mono {{ $fg }}">{{ $c['pct'] }}% <span
                                                class="text-ink-400">({{ $c['on'] }}/{{ $c['of'] }})</span></span>
                                    </div>
                                    <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                        <div class="h-full {{ $bg }}" style="width: {{ $c['pct'] }}%">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Quick actions') }}</div>
                        <div class="mt-3 space-y-2">
                            <button type="button"
                                data-confirm="Rotate webhook secrets for ALL workspaces? Existing integrations will need to update."
                                data-confirm-form="rotate-webhooks-form"
                                class="w-full rounded-xl border border-paper-200 px-3 py-2.5 text-left text-[12.5px] font-semibold hover:bg-paper-50 flex items-center justify-between">{{ __('Rotate webhook secrets') }}<span
                                    class="font-mono text-[10px] text-ink-500">{{ __('all') }}</span></button>
                            <button type="button"
                                data-confirm="Force EVERY user to reset their password on next login?"
                                data-confirm-form="force-reset-form"
                                class="w-full rounded-xl border border-paper-200 px-3 py-2.5 text-left text-[12.5px] font-semibold hover:bg-paper-50 flex items-center justify-between">{{ __('Force password reset') }}<span
                                    class="font-mono text-[10px] text-ink-500">{{ __('all users') }}</span></button>
                            @if ($policy['emergency_send_halt'] ?? false)
                                <button type="button" data-confirm="Resume outbound sends?"
                                    data-confirm-form="resume-halt-form"
                                    class="w-full rounded-xl border border-wa-green/30 bg-wa-mint px-3 py-2.5 text-left text-[12.5px] font-semibold text-wa-deep hover:bg-wa-bubble flex items-center justify-between">{{ __('Resume sends') }}<span
                                        class="font-mono text-[10px]">{{ __('currently HALTED') }}</span></button>
                            @else
                                <button type="button"
                                    data-confirm="EMERGENCY STOP all outbound sends across the platform? Nobody can send a message until you resume."
                                    data-confirm-form="halt-form"
                                    class="w-full rounded-xl border border-accent-coral/30 bg-accent-coral/5 px-3 py-2.5 text-left text-[12.5px] font-semibold text-accent-coral hover:bg-accent-coral/10 flex items-center justify-between">{{ __('Emergency stop sends') }}<span
                                        class="font-mono text-[10px]">{{ __('global') }}</span></button>
                            @endif
                        </div>
                    </div>
                </aside>
            </section>

            {{-- ── LOGIN AND MFA ── --}}
            <section class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start hidden" data-wa-tab-panel="login">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Login protection') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Account lockout rules') }}
                            </h2>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="space-y-1.5"><span
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">{{ __('Max login attempts') }}</span><input
                                type="number" name="lockout_after_failures" min="1" max="50"
                                value="{{ $intv('lockout_after_failures') }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-4 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"></label>
                        <label class="space-y-1.5"><span
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">{{ __('Lockout window (min)') }}</span><input
                                type="number" name="lockout_window_minutes" min="1" max="1440"
                                value="{{ $intv('lockout_window_minutes') }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-4 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"></label>
                        <label class="space-y-1.5"><span
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">{{ __('Password age limit (days, 0 = never)') }}</span><input
                                type="number" name="password_max_age_days" min="0" max="3650"
                                value="{{ $intv('password_max_age_days') }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-4 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"></label>
                        <label class="space-y-1.5"><span
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">{{ __('Session timeout (min)') }}</span><input
                                type="number" name="session_timeout_minutes" min="5" max="1440"
                                value="{{ $intv('session_timeout_minutes') }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-4 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"></label>
                    </div>

                    {{-- New-password strength rules — applied at signup + password change
 only, never to an existing login, so tightening them can't lock
 anyone out. --}}
                    <div class="mt-4 rounded-2xl border border-paper-200 p-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600 mb-3">
                            {{ __('New-password rules') }} <span
                                class="text-ink-400 normal-case font-normal">{{ __('(signup + password change only)') }}</span>
                        </div>
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                            <label class="space-y-1.5"><span
                                    class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">{{ __('Min length') }}</span><input
                                    type="number" name="password_min_length" min="6" max="128"
                                    value="{{ $intv('password_min_length') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-4 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"></label>
                            <label
                                class="flex items-center justify-between rounded-xl border border-paper-200 px-3 py-2.5"><span
                                    class="text-[12px] font-semibold">{{ __('Uppercase') }}</span><span
                                    class="toggle"><input type="hidden" name="password_require_upper"
                                        value="0"><input type="checkbox" name="password_require_upper"
                                        value="1" @checked($bool('password_require_upper'))><span class="track"></span><span
                                        class="thumb"></span></span></label>
                            <label
                                class="flex items-center justify-between rounded-xl border border-paper-200 px-3 py-2.5"><span
                                    class="text-[12px] font-semibold">{{ __('Number') }}</span><span
                                    class="toggle"><input type="hidden" name="password_require_number"
                                        value="0"><input type="checkbox" name="password_require_number"
                                        value="1" @checked($bool('password_require_number'))><span class="track"></span><span
                                        class="thumb"></span></span></label>
                            <label
                                class="flex items-center justify-between rounded-xl border border-paper-200 px-3 py-2.5"><span
                                    class="text-[12px] font-semibold">{{ __('Symbol') }}</span><span
                                    class="toggle"><input type="hidden" name="password_require_symbol"
                                        value="0"><input type="checkbox" name="password_require_symbol"
                                        value="1" @checked($bool('password_require_symbol'))><span class="track"></span><span
                                        class="thumb"></span></span></label>
                        </div>
                        <label
                            class="flex items-center justify-between rounded-xl border border-paper-200 px-3 py-2.5 mt-3"><span><span
                                    class="block text-[12px] font-semibold">{{ __('Allow "remember me" at login') }}</span><span
                                    class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Off = sessions expire when the browser closes') }}</span></span><span
                                class="toggle"><input type="hidden" name="remember_me_enabled"
                                    value="0"><input type="checkbox" name="remember_me_enabled" value="1"
                                    @checked($bool('remember_me_enabled'))><span class="track"></span><span
                                    class="thumb"></span></span></label>
                        {{-- The "Mask customer phone numbers" toggle was removed.
                             Numbers now always render in full: a masked number
                             ("+xxxxxxxx9356") gives an agent nothing to recognise,
                             search for, or dial, which made the inbox unusable.
                             See mask_phone() in app/Support/security_helpers.php —
                             it returns its input unchanged. --}}
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between gap-3">
                            <span><span
                                    class="block text-[12.5px] font-semibold">{{ __('Login alerts') }}</span><span
                                    class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Email user on new device') }}</span></span>
                            <span class="toggle"><input type="hidden" name="alert_on_new_device"
                                    value="0"><input type="checkbox" name="alert_on_new_device" value="1"
                                    @checked($bool('alert_on_new_device'))><span class="track"></span><span
                                    class="thumb"></span></span>
                        </label>
                        <label class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between gap-3">
                            <span><span
                                    class="block text-[12.5px] font-semibold">{{ __('Country-change alerts') }}</span><span
                                    class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Email on login from a new country') }}</span></span>
                            <span class="toggle"><input type="hidden" name="alert_on_new_country"
                                    value="0"><input type="checkbox" name="alert_on_new_country" value="1"
                                    @checked($bool('alert_on_new_country'))><span class="track"></span><span
                                    class="thumb"></span></span>
                        </label>
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Multi-factor authentication') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">{{ __('2FA enforcement') }}</h2>
                    <div class="space-y-4">
                        <label
                            class="flex items-center justify-between rounded-2xl border border-wa-green/40 bg-wa-bubble/30 p-4">
                            <span><span
                                    class="block text-[12.5px] font-semibold">{{ __('Require 2FA for admins') }}</span><span
                                    class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Super Admin, Admin, Support Admin') }}</span></span>
                            <span class="toggle"><input type="hidden" name="require_2fa_for_admins"
                                    value="0"><input type="checkbox" name="require_2fa_for_admins"
                                    value="1" @checked($bool('require_2fa_for_admins'))><span class="track"></span><span
                                    class="thumb"></span></span>
                        </label>
                        <label class="flex items-center justify-between rounded-2xl border border-paper-200 p-4">
                            <span><span
                                    class="block text-[12.5px] font-semibold">{{ __('Require 2FA for workspace owners') }}</span><span
                                    class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Anyone with role=owner') }}</span></span>
                            <span class="toggle"><input type="hidden" name="require_2fa_for_owners"
                                    value="0"><input type="checkbox" name="require_2fa_for_owners"
                                    value="1" @checked($bool('require_2fa_for_owners'))><span class="track"></span><span
                                    class="thumb"></span></span>
                        </label>
                        <label class="flex items-center justify-between rounded-2xl border border-paper-200 p-4">
                            <span><span
                                    class="block text-[12.5px] font-semibold">{{ __('Require 2FA for all users') }}</span><span
                                    class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Mandatory across the platform') }}</span></span>
                            <span class="toggle"><input type="hidden" name="require_2fa_for_all"
                                    value="0"><input type="checkbox" name="require_2fa_for_all" value="1"
                                    @checked($bool('require_2fa_for_all'))><span class="track"></span><span
                                    class="thumb"></span></span>
                        </label>
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600 mb-2">
                                {{ __('Allowed 2FA methods') }}</div>
                            @php $methods = is_array($policy['allowed_2fa_methods'] ?? null) ? $policy['allowed_2fa_methods'] : ['totp','email']; @endphp
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-[12.5px]">
                                @foreach (['totp' => 'Authenticator app', 'email' => 'Email code', 'telegram' => 'Telegram bot'] as $val => $lbl)
                                    <label
                                        class="flex items-center gap-2 rounded-xl border border-paper-200 px-3 py-2 cursor-pointer hover:bg-paper-50">
                                        <input type="checkbox" name="allowed_2fa_methods[]"
                                            value="{{ $val }}" @checked(in_array($val, $methods, true))>
                                        <span>{{ $lbl }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ── WHATSAPP GUARDRAILS ── --}}
            <section class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start hidden" data-wa-tab-panel="whatsapp">
                {{-- Master switch — gates EVERYTHING in this card. Off by default so
 nothing changes until an admin opts in. --}}
                @php $waMode = old('wa_guardrails_mode', $policy['wa_guardrails_mode'] ?? 'off'); @endphp
                <div class="lg:col-span-3 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Master switch') }}</div>
                            <h2 class="font-serif text-[20px] leading-tight mt-1">{{ __('Guardrail enforcement') }}
                            </h2>
                            <p class="text-[12px] text-ink-600 mt-1 max-w-2xl">
                                {{ __('The rate caps and content filters below only take effect based on this mode. Off changes nothing. Monitor runs the checks and logs what it would block (to the audit log) without stopping any send — use it to confirm the rules are right before turning them on. Enforce actually blocks matching sends.') }}
                            </p>
                        </div>
                        <label class="space-y-1.5 shrink-0">
                            <span
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">{{ __('Mode') }}</span>
                            <select name="wa_guardrails_mode"
                                class="w-44 rounded-xl border border-paper-200 bg-paper-0 px-4 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                <option value="off" @selected($waMode === 'off')>{{ __('Off (no checks)') }}
                                </option>
                                <option value="monitor" @selected($waMode === 'monitor')>{{ __('Monitor (log only)') }}
                                </option>
                                <option value="enforce" @selected($waMode === 'enforce')>{{ __('Enforce (block)') }}
                                </option>
                            </select>
                        </label>
                    </div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Send rate') }}
                    </div>
                    <h2 class="font-serif text-[20px] leading-tight mt-1 mb-4">{{ __('Hard caps') }}</h2>
                    <div class="space-y-3">
                        <label class="space-y-1.5 block"><span
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">{{ __('Max sends per minute (0 = unlimited)') }}</span><input
                                type="number" name="wa_max_sends_per_minute" min="0" max="10000"
                                value="{{ $intv('wa_max_sends_per_minute') }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-4 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"></label>
                        <label class="space-y-1.5 block"><span
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">{{ __('Max sends per day (0 = unlimited)') }}</span><input
                                type="number" name="wa_max_sends_per_day" min="0" max="1000000"
                                value="{{ $intv('wa_max_sends_per_day') }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-4 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"></label>
                        <label class="space-y-1.5 block"><span
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">{{ __('Hold if links exceed (0 = no limit)') }}</span><input
                                type="number" name="wa_hold_on_links_count" min="0" max="50"
                                value="{{ $intv('wa_hold_on_links_count') }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-4 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"></label>
                    </div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Template gate') }}</div>
                    <h2 class="font-serif text-[20px] leading-tight mt-1 mb-4">{{ __('Anti-spam controls') }}</h2>
                    <div class="space-y-3">
                        <label class="flex items-center justify-between rounded-xl border border-paper-200 p-3">
                            <span
                                class="text-[12.5px] font-semibold">{{ __('Hold sends matching scam patterns') }}</span>
                            <span class="toggle"><input type="hidden" name="wa_hold_on_scam_pattern"
                                    value="0"><input type="checkbox" name="wa_hold_on_scam_pattern"
                                    value="1" @checked($bool('wa_hold_on_scam_pattern'))><span class="track"></span><span
                                    class="thumb"></span></span>
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-paper-200 p-3">
                            <span
                                class="text-[12.5px] font-semibold">{{ __('Require template review before send') }}</span>
                            <span class="toggle"><input type="hidden" name="wa_require_template_review"
                                    value="0"><input type="checkbox" name="wa_require_template_review"
                                    value="1" @checked($bool('wa_require_template_review'))><span class="track"></span><span
                                    class="thumb"></span></span>
                        </label>
                    </div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Trust quality') }}</div>
                    <h2 class="font-serif text-[20px] leading-tight mt-1 mb-4">{{ __('Workspace health') }}</h2>
                    <div class="space-y-3 text-[12.5px]">
                        <div class="rounded-xl border border-paper-200 p-3 text-ink-600">
                            {{ __("Live block-rate health is read from each workspace's send pipeline. Hold + review fires automatically when configured limits are exceeded.") }}
                        </div>
                        <a href="{{ route('admin.workspaces.index') }}"
                            class="block text-center rounded-full border border-paper-200 px-3 py-2 text-[12px] font-semibold hover:bg-paper-50">{{ __('View workspaces') }}</a>
                    </div>
                </div>
            </section>

            {{-- ── ABUSE FILTERS ── --}}
            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start hidden" data-wa-tab-panel="abuse">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Abuse filters') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">{{ __('Illegal-use prevention') }}
                    </h2>
                    <div class="space-y-3">
                        <label class="flex items-center justify-between rounded-xl border border-paper-200 p-3">
                            <span><span
                                    class="block text-[12.5px] font-semibold">{{ __('Block finance / get-rich terms') }}</span><span
                                    class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Scam, fake investment, payment redirection patterns') }}</span></span>
                            <span class="toggle"><input type="hidden" name="abuse_block_finance_terms"
                                    value="0"><input type="checkbox" name="abuse_block_finance_terms"
                                    value="1" @checked($bool('abuse_block_finance_terms'))><span class="track"></span><span
                                    class="thumb"></span></span>
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-paper-200 p-3">
                            <span><span
                                    class="block text-[12.5px] font-semibold">{{ __('Block short / suspicious links') }}</span><span
                                    class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('bit.ly, tinyurl, freshly-registered domains') }}</span></span>
                            <span class="toggle"><input type="hidden" name="abuse_block_short_links"
                                    value="0"><input type="checkbox" name="abuse_block_short_links"
                                    value="1" @checked($bool('abuse_block_short_links'))><span class="track"></span><span
                                    class="thumb"></span></span>
                        </label>
                        <label class="space-y-1.5 block">
                            <span
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">{{ __('Custom keyword blocklist (one per line)') }}</span>
                            <textarea name="abuse_block_keyword_list" rows="6"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-4 py-2.5 text-[12.5px] font-mono resize-none focus:outline-none focus:border-wa-deep"
                                placeholder="{{ __('forex&#10;quick money&#10;guaranteed return') }}">{{ $list('abuse_block_keyword_list') }}</textarea>
                            <span
                                class="text-[10.5px] text-ink-500">{{ __('Outbound messages containing any of these are flagged + held for review.') }}</span>
                        </label>
                    </div>
                </div>
                <aside>
                    <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('Keep this policy strict') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">
                            {{ __('These controls prevent illegal offers, scams, phishing, and harassment from reaching WhatsApp.') }}
                        </p>
                    </div>
                </aside>
            </section>

            {{-- ── API AND WEBHOOKS ── --}}
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 items-start hidden" data-wa-tab-panel="api">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Traffic control') }}</div>
                    <h2 class="font-serif text-[20px] leading-tight mt-1 mb-4">{{ __('Rate limits') }}</h2>
                    <div class="space-y-3">
                        <label class="space-y-1.5 block"><span
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">{{ __('API requests per minute') }}</span><input
                                type="number" name="api_rate_limit_per_minute" min="1" max="100000"
                                value="{{ $intv('api_rate_limit_per_minute') }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-4 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"><span
                                class="text-[10.5px] text-ink-500">{{ __('Default for /api/v1. Each plan can override this in Admin → Packages (set 0 on a plan to use this default). Enforced per workspace API key.') }}</span></label>
                        <label class="space-y-1.5 block"><span
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">{{ __('Webhook replay window (sec)') }}
                                <span
                                    class="text-ink-400 normal-case font-normal">{{ __('· reserved') }}</span></span><input
                                type="number" name="webhook_replay_window_sec" min="10" max="3600"
                                value="{{ $intv('webhook_replay_window_sec') }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-4 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"><span
                                class="text-[10.5px] text-ink-500">{{ __('Saved but not yet enforced — Meta/Twilio inbound webhooks are already signature-verified.') }}</span></label>
                    </div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Webhook defense') }}</div>
                    <h2 class="font-serif text-[20px] leading-tight mt-1 mb-4">{{ __('Integrity') }}</h2>
                    <div class="space-y-3">
                        <label class="flex items-center justify-between rounded-xl border border-paper-200 p-3">
                            <span class="text-[12.5px] font-semibold">{{ __('Require HMAC signatures') }}</span>
                            <span class="toggle"><input type="hidden" name="webhook_signature_required"
                                    value="0"><input type="checkbox" name="webhook_signature_required"
                                    value="1" @checked($bool('webhook_signature_required'))><span class="track"></span><span
                                    class="thumb"></span></span>
                        </label>
                    </div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('IP allowlist') }}</div>
                    <h2 class="font-serif text-[20px] leading-tight mt-1 mb-4">{{ __('Admin panel access') }}</h2>
                    <div class="space-y-3">
                        <label class="flex items-center justify-between rounded-xl border border-paper-200 p-3">
                            <span class="text-[12.5px] font-semibold">{{ __('Enable allowlist') }}</span>
                            <span class="toggle"><input type="hidden" name="ip_allowlist_enabled"
                                    value="0"><input type="checkbox" name="ip_allowlist_enabled" value="1"
                                    @checked($bool('ip_allowlist_enabled'))><span class="track"></span><span
                                    class="thumb"></span></span>
                        </label>
                        <label class="space-y-1.5 block">
                            <span
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">{{ __('CIDRs (one per line)') }}</span>
                            <textarea name="ip_allowlist_cidrs" rows="4"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-4 py-2.5 text-[12.5px] font-mono resize-none focus:outline-none focus:border-wa-deep"
                                placeholder="103.41.25.0/24&#10;49.36.0.0/16">{{ $list('ip_allowlist_cidrs') }}</textarea>
                        </label>
                    </div>
                </div>
            </section>

            {{-- ── DEVICES ── --}}
            <section class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start hidden" data-wa-tab-panel="devices">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Device integrity') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">{{ __('Trust + session policy') }}
                    </h2>
                    <div class="space-y-3">
                        <label class="flex items-center justify-between rounded-xl border border-paper-200 p-3">
                            <span><span class="block text-[12.5px] font-semibold">{{ __('Require trusted browser') }}
                                    <span class="text-ink-400 font-normal">{{ __('· reserved') }}</span></span><span
                                    class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Saved but not yet enforced (no device-trust flow built).') }}</span></span>
                            <span class="toggle"><input type="hidden" name="device_trust_required"
                                    value="0"><input type="checkbox" name="device_trust_required"
                                    value="1" @checked($bool('device_trust_required'))><span class="track"></span><span
                                    class="thumb"></span></span>
                        </label>
                        <label class="space-y-1.5 block">
                            <span
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">{{ __('Auto-logout after inactive (days, 0 = never)') }}</span>
                            <input type="number" name="device_logout_on_inactive_days" min="0"
                                max="3650" value="{{ $intv('device_logout_on_inactive_days') }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-4 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                        </label>
                        <label class="space-y-1.5 block">
                            <span
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">{{ __('Max concurrent sessions per user') }}</span>
                            <input type="number" name="max_concurrent_sessions" min="1" max="50"
                                value="{{ $intv('max_concurrent_sessions') }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-4 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                        </label>
                    </div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Login alerts channel') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">{{ __('Where alerts go') }}</h2>
                    <div class="space-y-2">
                        @foreach (['email' => 'Email', 'whatsapp' => 'WhatsApp', 'both' => 'Email + WhatsApp'] as $val => $lbl)
                            <label
                                class="flex items-center gap-3 rounded-xl border border-paper-200 px-4 py-3 cursor-pointer hover:bg-paper-50">
                                <input type="radio" name="alert_channel" value="{{ $val }}"
                                    @checked(($policy['alert_channel'] ?? 'email') === $val)>
                                <span class="text-[12.5px] font-semibold">{{ $lbl }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ── DANGER ZONE ── --}}
            <section class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start hidden" data-wa-tab-panel="incidents">
                <div class="bg-paper-0 border border-accent-coral/30 bg-accent-coral/5 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-accent-coral">
                        {{ __('Danger zone') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4 text-accent-coral">
                        {{ __('Irreversible platform-wide actions') }}</h2>
                    <div class="space-y-3">
                        <button type="button"
                            data-confirm="Revoke ALL active sessions across the platform? Every user (including you) will be logged out."
                            data-confirm-form="revoke-sessions-form"
                            class="w-full rounded-xl border border-accent-coral/30 bg-paper-0 px-3 py-3 text-left text-[12.5px] font-semibold hover:bg-accent-coral/10 flex items-center justify-between">
                            {{ __('Revoke ALL sessions') }}
                            <span
                                class="font-mono text-[10px] text-accent-coral">{{ __('truncates sessions table') }}</span>
                        </button>
                        <button type="button" data-confirm="Force EVERY user to reset their password on next login?"
                            data-confirm-form="force-reset-form"
                            class="w-full rounded-xl border border-accent-coral/30 bg-paper-0 px-3 py-3 text-left text-[12.5px] font-semibold hover:bg-accent-coral/10 flex items-center justify-between">
                            {{ __('Force password reset for ALL users') }}
                            <span
                                class="font-mono text-[10px] text-accent-coral">{{ __('flips force_password_change') }}</span>
                        </button>
                        <button type="button" data-confirm="Rotate webhook secrets for ALL workspaces?"
                            data-confirm-form="rotate-webhooks-form"
                            class="w-full rounded-xl border border-accent-coral/30 bg-paper-0 px-3 py-3 text-left text-[12.5px] font-semibold hover:bg-accent-coral/10 flex items-center justify-between">
                            {{ __('Rotate ALL webhook secrets') }}
                            <span
                                class="font-mono text-[10px] text-accent-coral">{{ __('existing integrations break') }}</span>
                        </button>
                        @if ($policy['emergency_send_halt'] ?? false)
                            <button type="button" data-confirm="Resume outbound sends platform-wide?"
                                data-confirm-form="resume-halt-form"
                                class="w-full rounded-xl border border-wa-green/30 bg-wa-mint px-3 py-3 text-left text-[12.5px] font-semibold text-wa-deep hover:bg-wa-bubble flex items-center justify-between">
                                {{ __('Resume sends') }}
                                <span class="font-mono text-[10px]">{{ __('currently HALTED') }}</span>
                            </button>
                        @else
                            <button type="button"
                                data-confirm="EMERGENCY STOP all outbound sends across the platform? Nobody can send a message until you resume."
                                data-confirm-form="halt-form"
                                class="w-full rounded-xl border border-accent-coral/50 bg-accent-coral/10 px-3 py-3 text-left text-[12.5px] font-bold text-accent-coral hover:bg-accent-coral/20 flex items-center justify-between">
                                {{ __('EMERGENCY STOP all sends') }}
                                <span class="font-mono text-[10px]">{{ __('platform-wide kill switch') }}</span>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('What gets logged') }}</div>
                    <h2 class="font-serif text-[20px] leading-tight mt-1 mb-4">
                        {{ __('Every action above writes an audit row') }}</h2>
                    <ul class="text-[12.5px] text-ink-700 space-y-2">
                        <li class="flex items-start gap-2"><svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 mt-1 text-wa-deep" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M2 8l5 5 7-9" />
                            </svg><span><code class="font-mono">admin.security.sessions_revoked</code> when sessions
                                are wiped.</span></li>
                        <li class="flex items-start gap-2"><svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 mt-1 text-wa-deep" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M2 8l5 5 7-9" />
                            </svg><span><code class="font-mono">admin.security.force_password_reset_all</code> when
                                reset is forced.</span></li>
                        <li class="flex items-start gap-2"><svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 mt-1 text-wa-deep" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M2 8l5 5 7-9" />
                            </svg><span><code class="font-mono">admin.security.webhook_secrets_rotated</code> when
                                secrets rotate.</span></li>
                        <li class="flex items-start gap-2"><svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 mt-1 text-accent-coral" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <circle cx="8" cy="8" r="6" />
                                <path d="M8 5v3M8 11h.01" />
                            </svg><span><code class="font-mono">admin.security.emergency_halt_engaged</code> when halt
                                fires.</span></li>
                        <li class="flex items-start gap-2"><svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 mt-1 text-wa-deep" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M2 8l5 5 7-9" />
                            </svg><span><code class="font-mono">admin.security.policy_updated</code> on any setting
                                change above, with full before/after diff in payload.</span></li>
                    </ul>
                    <a href="{{ route('admin.audit-log.index', ['q' => 'admin.security']) }}"
                        class="mt-4 inline-block text-[12px] font-semibold text-wa-deep hover:underline">View security
                        audit trail →</a>
                </div>
            </section>

        </main>

    </form>

    {{-- The phone-mask form was removed along with its toggle — numbers now
         always render in full. The route + controller action remain so any
         bookmarked POST 404s rather than fatals, but nothing links to them. --}}

    {{-- Sibling forms for danger-zone buttons. Each is triggered by a
 data-confirm modal (data-confirm-form attribute names the form id). --}}
    <form id="revoke-sessions-form" method="POST" action="{{ route('admin.security.danger.revoke') }}"
        class="hidden">@csrf</form>
    <form id="force-reset-form" method="POST" action="{{ route('admin.security.danger.reset') }}" class="hidden">
        @csrf</form>
    <form id="rotate-webhooks-form" method="POST" action="{{ route('admin.security.danger.rotate-webhooks') }}"
        class="hidden">@csrf</form>
    <form id="halt-form" method="POST" action="{{ route('admin.security.danger.halt') }}" class="hidden">@csrf
    </form>
    <form id="resume-halt-form" method="POST" action="{{ route('admin.security.danger.resume') }}"
        class="hidden">@csrf</form>

</x-layouts.admin>
