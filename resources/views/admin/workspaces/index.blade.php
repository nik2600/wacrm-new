<x-layouts.admin :title="__('Admin · Workspaces')" admin-key="workspaces" page="workspaces-index">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Workspaces') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div class="flex items-end justify-between gap-4 flex-wrap">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Platform workspaces') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('All') }}
                    <span class="italic text-wa-deep">{{ __('workspaces') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Every customer workspace on the platform. Drill into MRR, message volume, plan caps, payment status, and admin overrides.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ route('admin.workspaces.create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    Create workspace
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif

        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total workspaces') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['total']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">+{{ $stats['thisMonth'] }} {{ __('this month') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['active']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ $stats['retention'] }}% retention</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Trial / free') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['trial']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('no paid plan') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Suspended') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1 text-accent-coral">
                    {{ number_format($stats['suspended']) }}</div>
                <div class="text-[11px] text-accent-coral mt-2">{{ __('action required') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Platform MRR') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['mrr'] }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('active subs') }}</div>
            </div>
        </section>

        <form method="get" action="{{ route('admin.workspaces.index') }}"
            class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex items-center gap-1 shadow-card flex-wrap">
            @php $statusOptions = ['all' => 'All', 'active' => 'Active', 'suspended' => 'Suspended']; @endphp
            @foreach ($statusOptions as $k => $label)
                <button type="submit" name="status" value="{{ $k }}" @class([
                    'inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] cursor-pointer transition',
                    'bg-ink-900 text-paper-0' => $statusF === $k,
                    'text-ink-600 hover:bg-paper-50' => $statusF !== $k,
                ])>
                    {{ $label }}
                    @if ($k === 'all')
                        <span class="font-mono text-[11px] opacity-80">({{ $stats['total'] }})</span>
                    @elseif ($k === 'active')
                        <span class="font-mono text-[11px] opacity-80">{{ $stats['active'] }}</span>
                    @elseif ($k === 'suspended')
                        <span
                            class="font-mono text-[11px] {{ $statusF === 'suspended' ? '' : 'text-accent-coral' }}">{{ $stats['suspended'] }}</span>
                    @endif
                </button>
            @endforeach
            <div class="hidden md:block flex-1"></div>
            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                <select name="plan_id" onchange="this.form.submit()"
                    class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 hover:bg-paper-50 focus:outline-none focus:border-wa-deep">
                    <option value="">{{ __('All plans') }}</option>
                    @foreach ($plans as $p)
                        <option value="{{ $p->id }}" @selected((string) $planF === (string) $p->id)>{{ $p->pname }}</option>
                    @endforeach
                </select>
                <select name="sort" onchange="this.form.submit()"
                    class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 hover:bg-paper-50 focus:outline-none focus:border-wa-deep">
                    <option value="mrr" @selected($sort === 'mrr')>{{ __('Sort: ID ↓') }}</option>
                    <option value="created" @selected($sort === 'created')>{{ __('Sort: Created ↓') }}</option>
                    <option value="lastseen" @selected($sort === 'lastseen')>{{ __('Sort: Last active ↓') }}</option>
                </select>
                <div class="relative w-full md:w-auto">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                        fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="7" cy="7" r="5" />
                        <path d="m11 11 3 3" />
                    </svg>
                    <input name="q" value="{{ $q }}"
                        placeholder="{{ __('Search workspace, owner, slug...') }}"
                        class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-full md:w-72 focus:outline-none focus:border-wa-deep" />
                </div>
            </div>
        </form>

        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="overflow-x-auto rounded-t-2xl">
            <table class="w-full text-[12.5px] table-fixed min-w-[1000px]">
                <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-2 py-2.5 w-[44px]"></th>
                        <th class="text-left px-2 py-2.5">{{ __('Workspace') }}</th>
                        <th class="text-left px-2 py-2.5 w-[180px]">{{ __('Owner') }}</th>
                        <th class="text-left px-2 py-2.5 w-[120px]">{{ __('Plan') }}</th>
                        <th class="text-right px-2 py-2.5 w-[100px]">{{ __('MRR') }}</th>
                        <th class="text-right px-2 py-2.5 w-[110px]">7d msgs</th>
                        <th class="text-center px-2 py-2.5 w-[100px]">{{ __('Status') }}</th>
                        <th class="text-left px-2 py-2.5 w-[110px]">{{ __('Last active') }}</th>
                        <th class="text-center px-2 py-2.5 w-[44px]"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    @forelse ($workspaces as $ws)
                        @php
                            $initial = mb_strtoupper(mb_substr($ws->name, 0, 1) ?: '?');
                            $d = $ws->_decorated;
                        @endphp
                        <tr class="hover:bg-paper-50/60 {{ !$ws->status ? 'bg-accent-coral/5' : '' }}">
                            <td class="px-2 py-2"><span
                                    class="w-9 h-9 rounded-lg bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 grid place-items-center text-[11px] font-bold">{{ $initial }}</span>
                            </td>
                            <td class="px-2 py-2 min-w-0">
                                <a href="{{ route('admin.workspaces.detail', $ws->id) }}"
                                    class="font-semibold leading-none text-[12.5px] truncate hover:text-wa-deep">{{ $ws->name }}</a>
                                <div class="text-[10px] text-ink-500 mt-1 font-mono leading-none truncate">
                                    {{ $ws->slug ?: '—' }} @if ($ws->industry)
                                        · {{ $ws->industry }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-2 py-2 min-w-0">
                                <div class="text-[11.5px] truncate">{{ $ws->owner?->name ?? '—' }}</div>
                                @if ($ws->owner?->email)
                                    <a href="mailto:{{ $ws->owner->email }}"
                                        class="block text-[10px] text-ink-500 hover:text-wa-deep font-mono truncate"
                                        title="{{ $ws->owner->email }}">{{ $ws->owner->email }}</a>
                                @endif
                                {{-- Owner mobile WITH country code, unmasked — so the sales team can
                                     call + WhatsApp the owner (e.g. before a trial expires). --}}
                                @php
                                    $occ   = preg_replace('/\D+/', '', (string) ($ws->owner?->country_code ?? ''));
                                    $omob  = preg_replace('/\D+/', '', (string) ($ws->owner?->mobile ?? ''));
                                    $oe164 = $omob !== '' ? ($occ !== '' ? $occ . ltrim($omob, '0') : $omob) : '';
                                    $odisp = $omob !== '' ? ('+' . ($occ !== '' ? $occ . ' ' : '') . $ws->owner->mobile) : '';
                                @endphp
                                @if ($oe164 !== '')
                                    <span class="inline-flex items-center gap-1.5 mt-0.5 font-mono text-[10px]">
                                        <a href="tel:+{{ $oe164 }}" class="text-ink-700 hover:text-wa-deep"
                                            title="{{ __('Call') }}">{{ $odisp }}</a>
                                        <a href="https://wa.me/{{ $oe164 }}" target="_blank" rel="noopener"
                                            class="text-wa-deep/70 hover:text-wa-deep" title="{{ __('WhatsApp') }}">
                                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="currentColor">
                                                <path
                                                    d="M8 0a8 8 0 0 0-6.9 12l-1 3.6 3.7-1A8 8 0 1 0 8 0zm0 14.5a6.5 6.5 0 0 1-3.3-.9l-.24-.14-2.2.58.6-2.14-.16-.25A6.5 6.5 0 1 1 8 14.5zm3.6-4.9c-.2-.1-1.17-.58-1.35-.64-.18-.07-.31-.1-.44.1-.13.2-.5.64-.62.77-.11.13-.23.15-.43.05a5.3 5.3 0 0 1-2.6-2.28c-.2-.34.2-.31.56-1.05.06-.13.03-.24-.02-.34-.05-.1-.44-1.07-.6-1.46-.16-.38-.32-.33-.44-.34h-.38c-.13 0-.34.05-.52.25-.18.2-.68.67-.68 1.63s.7 1.9.8 2.03c.1.13 1.38 2.1 3.34 2.95 1.24.53 1.73.58 2.35.49.38-.06 1.17-.48 1.33-.94.16-.46.16-.86.12-.94-.05-.08-.18-.13-.38-.23z" />
                                            </svg>
                                        </a>
                                    </span>
                                @endif
                            </td>
                            <td class="px-2 py-2">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                    style="background: {{ $d['plan_tone']['bg'] }}; color: {{ $d['plan_tone']['text'] }}">{{ $d['plan_name'] }}</span>
                                {{-- When the plan/trial is going to expire — days left so the sales
                                     team can call/WhatsApp before it lapses. Trial vs paid labelled;
                                     coral ≤3 days / expired, amber ≤7, muted otherwise. --}}
                                @if (!is_null($d['expiry_days']))
                                    @php
                                        $ed     = (int) $d['expiry_days'];
                                        $etone  = $ed <= 3 ? 'text-accent-coral' : ($ed <= 7 ? 'text-accent-amber' : 'text-ink-500');
                                        $elabel = $ed < 0
                                            ? __('Expired')
                                            : ($ed === 0
                                                ? __('Expires today')
                                                : (($d['is_trial'] ? __('Trial ends') : __('Renews')) . ' · ' . $ed . 'd'));
                                    @endphp
                                    <div class="text-[9.5px] font-mono mt-1 {{ $etone }}"
                                        title="{{ $d['expiry']?->format('Y-m-d') }}">{{ $elabel }}</div>
                                @endif
                            </td>
                            {{-- Admin's configured currency, not a typed "$". This cell hardcoded
                                 the symbol, so the KPI above rendered ₹5.0k while every row read
                                 $0 on the same page. FormatSettings::currency() is what the KPI
                                 and the detail page already use. --}}
                            <td class="px-2 py-2 text-right font-mono text-[11.5px] text-wa-deep">
                                {!! \App\Support\FormatSettings::currency($d['mrr']) !!}</td>
                            <td class="px-2 py-2 text-right font-mono text-[11px]">{{ number_format($d['msgs7d']) }}
                            </td>
                            <td class="px-2 py-2 text-center"><span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-{{ $d['health']['tone'] }}/10 text-{{ $d['health']['tone'] }} text-[10.5px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full bg-{{ $d['health']['tone'] }}"></span>{{ $d['health']['label'] }}</span>
                            </td>
                            <td class="px-2 py-2 font-mono text-[10.5px] text-ink-600 whitespace-nowrap">
                                {{ $ws->last_active_at?->diffForHumans() ?? '—' }}</td>
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
                                        <a href="{{ route('admin.workspaces.detail', $ws->id) }}"
                                            class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                                            </svg>{{ __('Open dashboard') }}
                                        </a>
                                        {{-- Direct route to the plan/limits form. "Open dashboard" already
                                             lands on the same page, but the edit panel is collapsed there,
                                             so there was no visible way to change a workspace's plan from
                                             this list. ?edit=1 opens the panel on arrival. --}}
                                        <a href="{{ route('admin.workspaces.detail', $ws->id) }}?edit=1#edit-panel"
                                            class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                                stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path d="M11.5 2.5l2 2L6 12l-2.5.5L4 10z" />
                                                <path d="M2.5 14h11" />
                                            </svg>{{ __('Edit & change plan') }}
                                        </a>
                                        <form action="{{ route('admin.impersonate.start', $ws->id) }}" method="POST"
                                            data-prompt-reason="Reason for impersonating {{ addslashes($ws->name) }}? This is logged for audit."
                                            data-prompt-title="{{ __('Login as owner') }}"
                                            data-prompt-placeholder="{{ __('e.g. troubleshooting ticket #1234') }}"
                                            data-min-length="8" data-confirm-text="Login as owner">
                                            @csrf
                                            <input type="hidden" name="reason">
                                            <button type="submit"
                                                class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep"
                                                    fill="none" stroke="currentColor" stroke-width="1.7">
                                                    <path d="M9 3h3a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H9" />
                                                    <path d="M2 8h8" />
                                                    <path d="M7 5l3 3-3 3" />
                                                </svg>{{ __('Login as owner') }}
                                            </button>
                                        </form>
                                        <div class="border-t border-paper-200 my-1"></div>
                                        <form action="{{ route('admin.workspaces.toggle', $ws->id) }}"
                                            method="POST">
                                            @csrf
                                            <button type="submit"
                                                class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] {{ $ws->status ? 'text-accent-amber hover:bg-accent-amber/10' : 'text-wa-deep hover:bg-wa-bubble/40' }}">
                                                @if ($ws->status)
                                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                        stroke="currentColor" stroke-width="1.7">
                                                        <circle cx="8" cy="8" r="6" />
                                                        <path d="M5 8h6" />
                                                    </svg>Suspend
                                                @else
                                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                        stroke="currentColor" stroke-width="1.7">
                                                        <path d="M3 8l3 3 7-7" />
                                                    </svg>Reactivate
                                                @endif
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.workspaces.destroy', $ws->id) }}"
                                            method="POST"
                                            data-confirm="Move {{ addslashes($ws->name) }} to trash? Owner will be locked out until it's restored."
                                            data-confirm-title="{{ __('Move workspace to trash') }}"
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
                            <td colspan="9" class="px-4 py-8 text-center text-ink-500">
                                {{ __('No workspaces match your filters.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            <div
                class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 flex flex-wrap gap-2 items-center justify-between rounded-b-2xl">
                <div class="text-[11px] font-mono text-ink-500">
                    Showing {{ $workspaces->firstItem() ?? 0 }}–{{ $workspaces->lastItem() ?? 0 }} of
                    {{ number_format($workspaces->total()) }} {{ __('workspaces') }}
                </div>
                <div>{{ $workspaces->onEachSide(1)->links() }}</div>
            </div>
        </div>
    </main>

</x-layouts.admin>
