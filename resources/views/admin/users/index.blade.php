<x-layouts.admin :title="__('Admin · Users')" admin-key="users" page="users-index">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Users') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Platform users') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('All') }}
                    <span class="italic text-wa-deep">{{ __('users') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Manage every account on the platform: roles, login-as, status, and trash.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0 pb-1">
                <a href="{{ route('admin.users.trash') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M3 4h10M6 4V2.8h4V4M5 6v8h6V6" />
                    </svg>
                    Trash
                    <span
                        class="px-1.5 py-0.5 rounded-full bg-paper-100 text-ink-600 text-[10px] font-mono">{{ $stats['trashed'] }}</span>
                </a>
                <a href="{{ route('admin.users.import') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M8 12V4m0 0L5 7m3-3 3 3M3 14h10" />
                    </svg>
                    Import CSV
                </a>
                <a href="{{ route('admin.users.create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    Add user
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif

        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total users') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['total']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">+{{ $stats['thisMonth'] }} {{ __('this month') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['active']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">
                    {{ $stats['total'] > 0 ? round(($stats['active'] / $stats['total']) * 100, 1) : 0 }}% active</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Suspended') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['suspended']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('policy violations') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Owners') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['owners']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('workspace owners') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Trashed') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1 text-accent-coral">
                    {{ number_format($stats['trashed']) }}</div>
                <div class="text-[11px] text-accent-coral mt-2">{{ __('recoverable 30d') }}</div>
            </div>
        </section>

        {{-- Filter / search bar — links carry filters back through pagination. --}}
        <form method="get" action="{{ route('admin.users.index') }}"
            class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-2 flex flex-wrap items-center gap-1 shadow-card">
            @php
                $pills = [
                    'all' => 'All',
                    'admin' => 'Admins',
                    'owner' => 'Owners',
                    'agent' => 'Agents',
                    'suspended' => 'Suspended',
                ];
                $countsByRole = [
                    'all' => $stats['total'],
                    'admin' => $stats['admin'],
                    'owner' => $stats['owners'],
                    'agent' => null,
                    'suspended' => $stats['suspended'],
                ];
            @endphp
            @foreach ($pills as $k => $label)
                <button type="submit" name="role" value="{{ $k }}" @class([
                    'filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] cursor-pointer transition',
                    'bg-ink-900 text-paper-0' => $role === $k,
                    'text-ink-600 hover:bg-paper-50' => $role !== $k,
                ])>
                    {{ $label }}
                    @if ($countsByRole[$k] !== null)
                        <span class="font-mono text-[11px] opacity-80">({{ number_format($countsByRole[$k]) }})</span>
                    @endif
                </button>
            @endforeach
            <div class="flex-1 min-w-0"></div>
            <div class="flex flex-wrap items-center gap-1.5">
                <select name="workspace_id" onchange="this.form.submit()"
                    class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 hover:bg-paper-50 focus:outline-none focus:border-wa-deep">
                    <option value="">{{ __('All workspaces') }}</option>
                    @foreach ($workspaces as $w)
                        <option value="{{ $w->id }}" @selected((string) $wsId === (string) $w->id)>{{ $w->name }}</option>
                    @endforeach
                </select>
                <div class="relative">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                        fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="7" cy="7" r="5" />
                        <path d="m11 11 3 3" />
                    </svg>
                    <input name="q" value="{{ $q }}"
                        placeholder="{{ __('Search name, email, phone...') }}"
                        class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-full max-w-72 sm:w-72 focus:outline-none focus:border-wa-deep" />
                </div>
            </div>
        </form>

        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] table-fixed min-w-[780px]">
                <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-3 py-2.5 w-[48px]"></th>
                        <th class="text-left px-2 py-2.5">{{ __('Name') }}</th>
                        <th class="text-left px-2 py-2.5 w-[160px]">{{ __('Mobile No') }}</th>
                        <th class="text-left px-2 py-2.5 w-[210px]">{{ __('Email id') }}</th>
                        <th class="text-left px-2 py-2.5 w-[160px]">{{ __('Workspace') }}</th>
                        <th class="text-left px-2 py-2.5 w-[110px]">{{ __('Joined') }}</th>
                        <th class="text-center px-2 py-2.5 w-[60px]" title="{{ __('Verification') }}">
                            {{ __('Vfd') }}</th>
                        <th class="text-center px-2 py-2.5 w-[70px]">{{ __('Login') }}</th>
                        <th class="text-center px-2 py-2.5 w-[70px]">{{ __('Status') }}</th>
                        <th class="text-center px-2 py-2.5 w-[44px]"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    @forelse ($users as $u)
                        @php
                            $initials = collect(explode(' ', trim((string) $u->name)))
                                ->map(fn($p) => mb_substr($p, 0, 1))
                                ->take(2)
                                ->implode('');
                            $initials = $initials !== '' ? mb_strtoupper($initials) : '?';
                            $verified = !is_null($u->email_verified_at);
                            $suspended = $u->role === 'suspended';
                            $ws = $u->currentWorkspaceRel;
                            $impersonateUrl = $ws ? route('admin.impersonate.start', $ws->id) : null;
                        @endphp
                        <tr class="hover:bg-paper-50/60 {{ $suspended ? 'bg-accent-coral/5' : '' }}">
                            <td class="px-2 py-2"><span
                                    class="w-9 h-9 rounded-full bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 grid place-items-center text-[11.5px] font-bold">{{ $initials }}</span>
                            </td>
                            <td class="px-2 py-2 min-w-0">
                                <div class="font-semibold leading-tight text-[12.5px] truncate">{{ $u->name }}
                                </div>
                            </td>
                            {{-- Mobile No — full number WITH country code, UNMASKED (this is the
                                 admin's own user list; the sales team needs the real number to
                                 call + WhatsApp). tel: dials it; wa.me opens WhatsApp. --}}
                            @php
                                $ucc  = preg_replace('/\D+/', '', (string) $u->country_code);
                                $umob = preg_replace('/\D+/', '', (string) $u->mobile);
                                $ue164 = $umob !== '' ? ($ucc !== '' ? $ucc . ltrim($umob, '0') : $umob) : '';
                                $udisp = $umob !== '' ? ('+' . ($ucc !== '' ? $ucc . ' ' : '') . $u->mobile) : '';
                            @endphp
                            <td class="px-2 py-2 font-mono text-[11px] whitespace-nowrap">
                                @if ($ue164 !== '')
                                    <span class="inline-flex items-center gap-1.5">
                                        <a href="tel:+{{ $ue164 }}" class="text-ink-800 hover:text-wa-deep"
                                            title="{{ __('Call') }}">{{ $udisp }}</a>
                                        <a href="https://wa.me/{{ $ue164 }}" target="_blank" rel="noopener"
                                            class="text-wa-deep/70 hover:text-wa-deep" title="{{ __('WhatsApp') }}">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                                                <path
                                                    d="M8 0a8 8 0 0 0-6.9 12l-1 3.6 3.7-1A8 8 0 1 0 8 0zm0 14.5a6.5 6.5 0 0 1-3.3-.9l-.24-.14-2.2.58.6-2.14-.16-.25A6.5 6.5 0 1 1 8 14.5zm3.6-4.9c-.2-.1-1.17-.58-1.35-.64-.18-.07-.31-.1-.44.1-.13.2-.5.64-.62.77-.11.13-.23.15-.43.05a5.3 5.3 0 0 1-2.6-2.28c-.2-.34.2-.31.56-1.05.06-.13.03-.24-.02-.34-.05-.1-.44-1.07-.6-1.46-.16-.38-.32-.33-.44-.34h-.38c-.13 0-.34.05-.52.25-.18.2-.68.67-.68 1.63s.7 1.9.8 2.03c.1.13 1.38 2.1 3.34 2.95 1.24.53 1.73.58 2.35.49.38-.06 1.17-.48 1.33-.94.16-.46.16-.86.12-.94-.05-.08-.18-.13-.38-.23z" />
                                            </svg>
                                        </a>
                                    </span>
                                @else
                                    <span class="text-ink-400">—</span>
                                @endif
                            </td>
                            {{-- Email id — dedicated column, clickable to mail the customer. --}}
                            <td class="px-2 py-2 text-[11px] min-w-0">
                                <a href="mailto:{{ $u->email }}"
                                    class="text-ink-700 hover:text-wa-deep font-mono block truncate"
                                    title="{{ $u->email }}">{{ $u->email }}</a>
                            </td>
                            <td class="px-2 py-2">
                                <div class="text-[12px] font-semibold leading-tight truncate">{{ $ws?->name ?? '—' }}
                                </div>
                                <div class="text-[9.5px] text-ink-500 font-mono uppercase tracking-[0.12em] mt-0.5">
                                    {{ ucfirst($u->role ?? 'user') }}</div>
                            </td>
                            <td class="px-2 py-2 font-mono text-[10.5px] text-ink-600 whitespace-nowrap">
                                {{ $u->created_at?->diffForHumans() }}</td>
                            <td class="px-2 py-2 text-center">
                                @if ($suspended)
                                    <span
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-accent-coral/15 text-accent-coral"
                                        title="{{ __('Suspended') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                                            fill="none" stroke="currentColor" stroke-width="1.8">
                                            <circle cx="8" cy="8" r="6" />
                                            <path d="M5 5l6 6" />
                                        </svg></span>
                                @elseif ($verified)
                                    <span
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-wa-bubble text-wa-deep"
                                        title="{{ __('Email verified') }}"><svg viewBox="0 0 16 16"
                                            class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                            stroke-width="2">
                                            <path d="M3.5 8l3 3 6-6" />
                                        </svg></span>
                                @else
                                    <span
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-accent-amber/15 text-accent-amber"
                                        title="{{ __('Email pending') }}"><svg viewBox="0 0 16 16"
                                            class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                            stroke-width="1.8">
                                            <circle cx="8" cy="8" r="6" />
                                            <path d="M8 5v3.5M8 11h.01" />
                                        </svg></span>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-center">
                                @if ($impersonateUrl && !$suspended)
                                    <form action="{{ $impersonateUrl }}" method="POST" class="inline-block"
                                        data-prompt-reason="Reason for logging in as {{ addslashes($u->name) }}{{ $ws ? ' (' . addslashes($ws->name) . ')' : '' }}? This is logged for audit."
                                        data-prompt-title="{{ __('Login as this user') }}"
                                        data-prompt-placeholder="{{ __('e.g. troubleshooting ticket #1234') }}" data-min-length="8">
                                        @csrf
                                        <input type="hidden" name="reason">
                                        <button type="submit"
                                            class="w-8 h-8 rounded-full border border-paper-200 hover:border-wa-deep hover:bg-wa-bubble grid place-items-center mx-auto transition"
                                            title="{{ __('Login as this user') }}">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                                stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path d="M9 3h3a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H9" />
                                                <path d="M2 8h8" />
                                                <path d="M7 5l3 3-3 3" />
                                            </svg>
                                        </button>
                                    </form>
                                @else
                                    <span
                                        class="inline-block w-8 h-8 rounded-full border border-paper-200 opacity-40 grid place-items-center"
                                        title="{{ __('Unavailable') }}">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                            stroke="currentColor" stroke-width="1.7">
                                            <path d="M9 3h3a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H9" />
                                            <path d="M2 8h8" />
                                            <path d="M7 5l3 3-3 3" />
                                        </svg>
                                    </span>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-center">
                                <form action="{{ route('admin.users.toggle', $u->id) }}" method="POST"
                                    class="inline">
                                    @csrf
                                    <label class="relative inline-block w-9 h-5 align-middle cursor-pointer">
                                        <input type="checkbox" class="peer opacity-0 w-0 h-0"
                                            @checked(!$suspended) onchange="this.form.submit()">
                                        <span
                                            class="absolute inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[16px]"></span>
                                    </label>
                                </form>
                            </td>
                            <td class="px-2 py-2 text-center">
                                <div class="relative inline-block" data-row-menu>
                                    <button type="button"
                                        class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center mx-auto"
                                        title="{{ __('Actions') }}" data-row-menu-toggle>
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-600"
                                            fill="currentColor">
                                            <circle cx="3" cy="8" r="1.2" />
                                            <circle cx="8" cy="8" r="1.2" />
                                            <circle cx="13" cy="8" r="1.2" />
                                        </svg>
                                    </button>
                                    <div data-row-menu-panel
                                        class="hidden absolute right-0 top-full mt-1 z-50 w-[210px] bg-paper-0 border border-paper-200 rounded-xl shadow-soft py-1 text-left">
                                        <a href="{{ route('admin.users.edit', $u->id) }}"
                                            class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <circle cx="8" cy="6" r="3" />
                                                <path d="M2 14c0-3 2.5-5 6-5s6 2 6 5" />
                                            </svg>{{ __('View profile') }}
                                        </a>
                                        <a href="{{ route('admin.users.edit', $u->id) }}"
                                            class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <path d="M9.5 3.5 12.5 6.5 6 13H3v-3z" />
                                                <path d="M8.5 4.5 11.5 7.5" />
                                            </svg>{{ __('Edit user') }}
                                        </a>
                                        <form action="{{ route('admin.users.reset', $u->id) }}" method="POST">
                                            @csrf
                                            <button type="submit"
                                                class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500"
                                                    fill="none" stroke="currentColor" stroke-width="1.6">
                                                    <rect x="3" y="7" width="10" height="7" rx="1.5" />
                                                    <path d="M5 7V5a3 3 0 0 1 6 0v2" />
                                                </svg>{{ __('Send reset email') }}
                                            </button>
                                        </form>
                                        @if ($impersonateUrl)
                                            <form action="{{ $impersonateUrl }}" method="POST"
                                                data-prompt-reason="Reason for logging in as {{ addslashes($u->name) }}{{ $ws ? ' (' . addslashes($ws->name) . ')' : '' }}? This is logged for audit."
                                                data-prompt-title="{{ __('Login as this user') }}"
                                                data-prompt-placeholder="{{ __('e.g. troubleshooting ticket #1234') }}" data-min-length="8">
                                                @csrf
                                                <input type="hidden" name="reason">
                                                <button type="submit"
                                                    class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50">
                                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep"
                                                        fill="none" stroke="currentColor" stroke-width="1.7">
                                                        <path d="M9 3h3a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H9" />
                                                        <path d="M2 8h8" />
                                                        <path d="M7 5l3 3-3 3" />
                                                    </svg>{{ __('Login as user') }}
                                                </button>
                                            </form>
                                        @endif
                                        <div class="border-t border-paper-200 my-1"></div>
                                        <form action="{{ route('admin.users.toggle', $u->id) }}" method="POST">
                                            @csrf
                                            <button type="submit"
                                                class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] {{ $suspended ? 'text-wa-deep hover:bg-wa-bubble/40' : 'text-accent-amber hover:bg-accent-amber/10' }}">
                                                @if ($suspended)
                                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                        stroke="currentColor" stroke-width="1.7">
                                                        <path d="M3 8l3 3 7-7" />
                                                    </svg>Reactivate
                                                @else
                                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                        stroke="currentColor" stroke-width="1.7">
                                                        <circle cx="8" cy="8" r="6" />
                                                        <path d="M5 8h6" />
                                                    </svg>Suspend
                                                @endif
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.users.destroy', $u->id) }}" method="POST"
                                            data-confirm="Move {{ addslashes($u->name) }} to trash? Recoverable for 30 days."
                                            data-confirm-title="{{ __('Move to trash') }}"
                                            data-confirm-text="Yes, move to trash" data-danger="1">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-accent-coral hover:bg-accent-coral/10">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                    stroke="currentColor" stroke-width="1.6">
                                                    <path d="M3 4h10M6 4V2.8h4V4M5 6v8h6V6" />
                                                </svg>{{ __('Move to trash') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-ink-500">
                                {{ __('No users match your filters.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            <div
                class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 flex flex-wrap gap-2 items-center justify-between rounded-b-2xl">
                <div class="text-[11px] font-mono text-ink-500">
                    Showing {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} of
                    {{ number_format($users->total()) }} {{ __('users') }}
                </div>
                <div>
                    {{ $users->onEachSide(1)->links() }}
                </div>
            </div>
        </div>
    </main>

</x-layouts.admin>
