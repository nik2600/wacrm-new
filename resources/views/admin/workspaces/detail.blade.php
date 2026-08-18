<x-layouts.admin :title="__('Admin · Workspace · ') . $workspace->name" admin-key="workspaces" page="admin-workspaces-detail">

    <header class="bg-paper-0 hairline-b border-b border-paper-200 flex flex-wrap sm:flex-nowrap items-center px-4 sm:px-6 lg:px-7 gap-x-4 gap-y-2 py-2.5 sm:py-0 sm:h-16 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ route('admin.workspaces.index') }}" class="hover:text-ink-900">{{ __('Workspaces') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal truncate max-w-[150px] sm:max-w-[280px]">{{ $workspace->name }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2 flex-wrap justify-end">
            @if ($workspace->status)
                <span
                    class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono"><span
                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Active</span>
            @else
                <span
                    class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-accent-coral/10 text-accent-coral border border-accent-coral/30 font-mono"><span
                        class="w-1.5 h-1.5 rounded-full bg-accent-coral"></span>Suspended</span>
            @endif

            @if ($workspace->owner)
                <form action="{{ route('admin.impersonate.start', $workspace->id) }}" method="POST"
                    data-prompt-reason="Reason for impersonating {{ addslashes($workspace->name) }}? This is logged for audit."
                    data-prompt-title="{{ __('Login as owner') }}"
                    data-prompt-placeholder="{{ __('e.g. troubleshooting ticket #1234') }}" data-min-length="8"
                    data-confirm-text="Login as owner">
                    @csrf
                    <input type="hidden" name="reason">
                    <button type="submit"
                        class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="none" stroke="currentColor"
                            stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 3h3a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H9" />
                            <path d="M2 8h8" />
                            <path d="M7 5l3 3-3 3" />
                        </svg>
                        Login as owner
                    </button>
                </form>
            @endif

            <form action="{{ route('admin.workspaces.toggle', $workspace->id) }}" method="POST" class="inline-block">
                @csrf
                <button type="submit"
                    class="px-3.5 py-1.5 hairline rounded-full bg-paper-0 hover:bg-{{ $workspace->status ? 'accent-coral/10' : 'wa-bubble' }} text-[12px] font-medium {{ $workspace->status ? 'border border-accent-coral/40 text-accent-coral' : 'border border-wa-green/40 text-wa-deep' }}">
                    {{ $workspace->status ? 'Suspend' : 'Reactivate' }}
                </button>
            </form>
            <button type="button" data-edit-toggle
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M9.5 3.5 12.5 6.5 6 13H3v-3z" />
                </svg>
                Edit
            </button>
        </div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">
        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div
                class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Hero --}}
        @php $initial = mb_strtoupper(mb_substr($workspace->name, 0, 1) ?: '?'); @endphp
        <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-start justify-between gap-6 flex-wrap">
                <div class="flex items-start gap-4 min-w-0">
                    <span
                        class="w-14 h-14 rounded-xl bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 grid place-items-center text-[18px] font-bold shrink-0">{{ $initial }}</span>
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            Workspace #{{ str_pad((string) $workspace->id, 4, '0', STR_PAD_LEFT) }}
                            · created {{ $workspace->created_at?->toFormattedDateString() }}
                        </div>
                        <h1 class="font-serif text-[24px] sm:text-[28px] lg:text-[32px] leading-tight tracking-[-0.01em] mt-0.5">
                            {{ $workspace->name }}</h1>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <span
                                class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10.5px] font-semibold">{{ $package?->pname ?? 'Free' }}</span>
                            @if ($workspace->slug)
                                <span
                                    class="px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ $workspace->slug }}</span>
                            @endif
                            @if ($workspace->custom_domain)
                                <span
                                    class="px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ $workspace->custom_domain }}
                                    @if ($workspace->cname_verified)
                                    <span class="text-wa-deep">{{ __('verified') }}</span>@else<span
                                            class="text-accent-amber">{{ __('pending') }}</span>
                                    @endif
                                </span>
                            @endif
                            @if ($workspace->industry)
                                <span
                                    class="px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ $workspace->industry }}</span>
                            @endif
                            @if ($workspace->country || $workspace->timezone)
                                <span
                                    class="px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ trim(($workspace->country ?? '') . ' · ' . ($workspace->timezone ?? ''), ' ·') }}</span>
                            @endif
                            <span
                                class="px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ $counts['users'] }}
                                users · {{ $counts['devices'] }} {{ __('devices') }}</span>
                            @if ($workspace->owner)
                                <span
                                    class="px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">Owner:
                                    {{ $workspace->owner->name }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2 w-full lg:w-[440px] lg:shrink-0">
                    <div class="rounded-2xl bg-wa-bubble border border-wa-green/30 px-4 py-3 min-w-0">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('MRR') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1 text-wa-deep truncate">{!! \App\Support\FormatSettings::currency($stats['mrr']) !!}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3 min-w-0">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('LTV') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1 truncate">{!! \App\Support\FormatSettings::currency($stats['ltv']) !!}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3 min-w-0">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Health') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1 text-{{ $stats['health']['tone'] }} truncate">
                            {{ $stats['health']['label'] }}</div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Custom-domain DNS panel — only when domain is set + not verified. --}}
        @if ($workspace->custom_domain && !$workspace->cname_verified)
            <section class="rounded-2xl border border-accent-amber/40 bg-accent-amber/5 px-5 py-4 text-[12.5px]">
                <div class="flex items-start gap-3">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 text-accent-amber mt-0.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <circle cx="8" cy="8" r="6" />
                        <path d="M8 5v3.5M8 11h.01" />
                    </svg>
                    <div class="min-w-0">
                        <div class="font-semibold">DNS verification pending for {{ $workspace->custom_domain }}</div>
                        <div class="text-ink-700 mt-1">
                            Add a <code class="bg-paper-100 px-1 rounded">CNAME</code> record pointing to <code
                                class="bg-paper-100 px-1 rounded">cnames.{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code>
                            (subdomain) or an <code class="bg-paper-100 px-1 rounded">A</code> record to this server's
                            IP (apex). Verification runs every 5 min.
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- 6 KPI cards --}}
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Sent (30d)') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">{{ number_format($stats['sent30d']) }}</div>
                @if ($effLimits['monthly_messages_limit'] ?? false)
                    @php $usagePct = $effLimits['monthly_messages_limit'] > 0 ? round($stats['sent30d'] / $effLimits['monthly_messages_limit'] * 100) : 0; @endphp
                    <div class="text-[11px] text-ink-500 mt-2">{{ $usagePct }}% of cap</div>
                @else
                    <div class="text-[11px] text-ink-500 mt-2">{{ __('no cap') }}</div>
                @endif
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Delivered') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">{{ $stats['deliveredPct'] }}%</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ number_format($stats['delivered30d']) }}
                    {{ __('msgs') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Read rate') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">{{ $stats['readPct'] }}%</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ number_format($stats['read30d']) }} {{ __('reads') }}
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Campaigns') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">{{ $counts['campaigns'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ $counts['broadcasts'] }} {{ __('broadcasts') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Devices') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">{{ $counts['devices'] }} @if ($effLimits['device_limit'] ?? false)
                        / {{ $effLimits['device_limit'] }}
                    @endif
                </div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('connected') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Contacts') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">{{ number_format($counts['contacts']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('in CRM') }}</div>
            </div>
        </section>

        {{-- Volume chart + Plan usage --}}
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Last 30 days') }}</div>
                        <h2 class="font-serif text-[24px] leading-tight mt-1">{{ __('Message volume') }}</h2>
                    </div>
                    <div class="flex items-center gap-3 text-[11px] text-ink-500">
                        <span class="flex items-center gap-1.5"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-deep"></span>Sent</span>
                        <span class="flex items-center gap-1.5"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-teal"></span>Delivered</span>
                    </div>
                </div>
                <div id="chart-volume" class="h-[280px]"></div>
            </div>
            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Plan usage') }}
                </div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">vs {{ $package?->pname ?? 'Free' }} caps
                </h2>
                <div class="space-y-3 text-[12px]">
                    @php
                        $progressRows = [
                            [
                                'label' => 'Messages (30d)',
                                'used' => $stats['sent30d'],
                                'cap' => $effLimits['monthly_messages_limit'] ?? null,
                            ],
                            [
                                'label' => 'Devices',
                                'used' => $counts['devices'],
                                'cap' => $effLimits['device_limit'] ?? null,
                            ],
                            [
                                'label' => 'Users',
                                'used' => $counts['users'],
                                'cap' => $effLimits['user_seat_limit'] ?? null,
                            ],
                            [
                                'label' => 'Contacts',
                                'used' => $counts['contacts'],
                                'cap' => $effLimits['contacts_limit'] ?? null,
                            ],
                        ];
                    @endphp
                    @foreach ($progressRows as $r)
                        @php $pct = ($r['cap'] && $r['cap'] > 0) ? min(100, round($r['used'] / $r['cap'] * 100)) : 0; @endphp
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-ink-600">{{ $r['label'] }}</span>
                                <span class="font-mono text-ink-900">{{ number_format($r['used']) }} @if ($r['cap'])
                                        / {{ number_format($r['cap']) }}
                                    @else
                                        <span class="text-ink-500">∞</span>
                                    @endif
                                </span>
                            </div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-{{ $pct > 80 ? 'accent-coral' : ($pct > 50 ? 'accent-amber' : 'wa-deep') }}"
                                    style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Recent orders + Owner block --}}
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold text-[14px]">{{ __('Recent orders') }}</h2>
                        <p class="text-[11px] text-ink-500 mt-1">{{ __('Last 6 from this workspace') }}</p>
                    </div>
                    <a href="{{ route('admin.order-history.index') }}"
                        class="rounded-full border border-paper-200 px-3 py-1.5 text-[11.5px] font-semibold hover:bg-paper-50">{{ __('All orders') }}</a>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] min-w-[640px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-3">{{ __('Order #') }}</th>
                            <th class="text-left px-3 py-3 w-[140px]">{{ __('Plan') }}</th>
                            <th class="text-left px-3 py-3 w-[110px]">{{ __('Amount') }}</th>
                            <th class="text-left px-3 py-3 w-[110px]">{{ __('Status') }}</th>
                            <th class="text-left px-4 py-3 w-[130px]">{{ __('When') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($recentOrders as $o)
                            @php
                                $tone = match ($o->status) {
                                    'paid' => 'bg-wa-bubble text-wa-deep',
                                    'pending' => 'bg-accent-amber/10 text-accent-amber',
                                    'failed' => 'bg-accent-coral/10 text-accent-coral',
                                    'refunded' => 'bg-[#F3E9FF] text-[#5B3D8A]',
                                    default => 'bg-paper-100 text-ink-600',
                                };
                            @endphp
                            <tr class="hover:bg-paper-50/60">
                                <td class="px-4 py-3 font-mono text-[11.5px]">{{ $o->order_number }}</td>
                                <td class="px-3 py-3">{{ \App\Models\Package::find($o->package_id)?->pname ?? '—' }}
                                </td>
                                <td class="px-3 py-3 font-mono">{!! \App\Support\FormatSettings::formatIn((float) ($o->total_amount ?? $o->amount), $o->currency) !!}</td>
                                <td class="px-3 py-3"><span
                                        class="px-2 py-1 rounded-full {{ $tone }} text-[10px] font-semibold">{{ ucfirst($o->status) }}</span>
                                </td>
                                <td class="px-4 py-3 text-ink-500">{{ $o->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-ink-500">
                                    {{ __('No orders yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <h2 class="font-semibold text-[14px] mb-3">{{ __('Owner') }}</h2>
                @if ($workspace->owner)
                    @php
                        $initials = collect(explode(' ', $workspace->owner->name))
                            ->map(fn($p) => mb_substr($p, 0, 1))
                            ->take(2)
                            ->implode('');
                    @endphp
                    <div class="flex items-center gap-3">
                        <span
                            class="w-12 h-12 rounded-full bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 grid place-items-center text-[14px] font-bold">{{ mb_strtoupper($initials) }}</span>
                        <div class="min-w-0">
                            <div class="font-semibold truncate">{{ $workspace->owner->name }}</div>
                            <a href="mailto:{{ $workspace->owner->email }}"
                                class="text-[11.5px] text-wa-deep hover:underline font-mono truncate block">{{ $workspace->owner->email }}</a>
                            @if ($workspace->owner->mobile)
                                <div class="text-[11.5px] text-ink-500 mt-0.5 font-mono">
                                    {{ mask_phone($workspace->owner->mobile) }}</div>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('admin.users.edit', $workspace->owner->id) }}"
                        class="block mt-4 text-center px-3 py-2 rounded-full border border-paper-200 text-[12px] font-semibold hover:bg-paper-50">Open
                        owner profile</a>
                @else
                    <div class="text-[12px] text-ink-500">{{ __('No owner attached.') }}</div>
                @endif

                @if ($workspace->admin_note)
                    <div class="mt-5 rounded-xl border border-paper-200 bg-paper-50 p-3">
                        <div class="text-[10px] uppercase tracking-[0.14em] text-ink-500 font-mono mb-1">
                            {{ __('Admin note') }}</div>
                        <div class="text-[12px] text-ink-700 whitespace-pre-line">{{ $workspace->admin_note }}</div>
                    </div>
                @endif
            </div>
        </section>

        {{-- Edit form — hidden by default, toggled by "Edit" button. Posts PUT to admin.workspaces.update. --}}
        <section id="edit-panel" class="hidden bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-serif text-[20px]">{{ __('Edit workspace') }}</h2>
                <button type="button" data-edit-toggle
                    class="text-[12px] text-ink-500 hover:text-ink-900">{{ __('Close') }}</button>
            </div>
            <form action="{{ route('admin.workspaces.update', $workspace->id) }}" method="POST"
                class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @csrf @method('PUT')
                <div>
                    <label class="text-[11.5px] font-semibold mb-1 block"
                        for="ws-edit-name">{{ __('Name') }}</label>
                    <input id="ws-edit-name" name="name" type="text"
                        value="{{ old('name', $workspace->name) }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]" required>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold mb-1 block"
                        for="ws-edit-slug">{{ __('Slug') }}</label>
                    <input id="ws-edit-slug" name="slug" type="text"
                        value="{{ old('slug', $workspace->slug) }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]">
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold mb-1 block"
                        for="ws-edit-domain">{{ __('Custom domain') }}</label>
                    <input id="ws-edit-domain" name="custom_domain" type="text"
                        value="{{ old('custom_domain', $workspace->custom_domain) }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]"
                        placeholder="{{ __('e.g. crm.acme.com') }}">
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold mb-1 block"
                        for="ws-edit-owner">{{ __('Owner') }}</label>
                    <select id="ws-edit-owner" name="owner_user_id"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]" required>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected(old('owner_user_id', $workspace->owner_user_id) == $u->id)>{{ $u->name }} ·
                                {{ $u->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold mb-1 block"
                        for="ws-edit-plan">{{ __('Plan') }}</label>
                    <select id="ws-edit-plan" name="plan"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]">
                        <option value="">{{ __('No plan / Free') }}</option>
                        @php
                            // `workspaces.plan` holds EITHER the numeric package id OR the
                            // `plan_id` slug ('starter') — Workspace::package() resolves both.
                            // Matching only on id left older slug-stored workspaces showing
                            // "No plan / Free" while they were in fact on a plan, so an admin
                            // opening the form saw the wrong current value.
                            $currentPlan = (string) old('plan', $workspace->plan);
                        @endphp
                        @foreach ($plans as $p)
                            {{-- Price in the PACKAGE's own currency, not a hardcoded '$'.
                                 Each package carries its own currency and is charged in it
                                 (no conversion), so an INR plan was being listed as
                                 "$1,200.00" here while the workspace and the invoice both
                                 said ₹. formatIn() resolves the symbol + precision from the
                                 currencies table, exactly like the orders table above. --}}
                            {{-- Show the price the workspace actually pays: chargeableAmount()
                                 returns the offer (discounted) price when one is set, else the
                                 regular plan_amount — same source of truth checkout uses, so an
                                 offered plan never lists at its struck-through base price here. --}}
                            <option value="{{ $p->id }}" @selected($currentPlan === (string) $p->id || $currentPlan === (string) $p->plan_id)>{{ $p->pname }}
                                ({{ $p->free ? 'free' : \App\Support\FormatSettings::formatIn($p->chargeableAmount(), $p->currency) }})</option>
                        @endforeach
                    </select>
                    <p class="text-[10.5px] text-ink-500 mt-1">
                        {{ __('Changing the plan activates it immediately and clears any expired trial.') }}
                    </p>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold mb-1 block"
                        for="ws-edit-plan-ends">{{ __('Plan valid until') }}</label>
                    <input type="date" id="ws-edit-plan-ends" name="plan_ends_at"
                        value="{{ old('plan_ends_at', $workspace->plan_ends_at?->format('Y-m-d')) }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]">
                    <p class="text-[10.5px] text-ink-500 mt-1">
                        {{ __('Leave blank to use the plan’s own billing cycle. Only applied when the plan changes.') }}
                    </p>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold mb-1 block"
                        for="ws-edit-cycle">{{ __('Billing cycle') }}</label>
                    @php $cycles = ['monthly','quarterly','annual','custom','trial']; @endphp
                    <select id="ws-edit-cycle" name="billing_cycle"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]">
                        @foreach ($cycles as $c)
                            <option value="{{ $c }}" @selected(old('billing_cycle', $workspace->billing_cycle) === $c)>{{ ucfirst($c) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold mb-1 block"
                        for="ws-edit-industry">{{ __('Industry') }}</label>
                    <input id="ws-edit-industry" name="industry" type="text"
                        value="{{ old('industry', $workspace->industry) }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]">
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold mb-1 block"
                        for="ws-edit-country">{{ __('Country (ISO-2)') }}</label>
                    <input id="ws-edit-country" name="country" type="text" maxlength="8"
                        value="{{ old('country', $workspace->country) }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]" placeholder="IN">
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold mb-1 block"
                        for="ws-edit-tz">{{ __('Timezone') }}</label>
                    <select id="ws-edit-tz" name="timezone"
                        data-value="{{ old('timezone', $workspace->timezone ?: 'Asia/Kolkata') }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]">
                        <option value="{{ $workspace->timezone ?: 'Asia/Kolkata' }}">
                            {{ $workspace->timezone ?: 'Asia/Kolkata' }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold mb-1 block"
                        for="ws-edit-currency">{{ __('Currency') }}</label>
                    <input id="ws-edit-currency" name="currency" type="text" maxlength="8"
                        value="{{ old('currency', $workspace->currency) }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]"
                        placeholder="{{ __('INR') }}">
                </div>
                <div class="md:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div><label class="text-[11.5px] font-semibold mb-1 block">{{ __('Monthly cap') }}</label><input
                            name="cap_monthly_messages" type="number"
                            value="{{ old('cap_monthly_messages', $workspace->cap_monthly_messages) }}"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]"
                            placeholder="{{ __('inherit') }}"></div>
                    <div><label class="text-[11.5px] font-semibold mb-1 block">{{ __('Daily cap') }}</label><input
                            name="cap_daily_messages" type="number"
                            value="{{ old('cap_daily_messages', $workspace->cap_daily_messages) }}"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]"
                            placeholder="{{ __('inherit') }}"></div>
                    <div><label class="text-[11.5px] font-semibold mb-1 block">{{ __('Max devices') }}</label><input
                            name="cap_devices" type="number"
                            value="{{ old('cap_devices', $workspace->cap_devices) }}"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]"
                            placeholder="{{ __('inherit') }}"></div>
                    <div><label class="text-[11.5px] font-semibold mb-1 block">{{ __('Max users') }}</label><input
                            name="cap_users" type="number" value="{{ old('cap_users', $workspace->cap_users) }}"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]"
                            placeholder="{{ __('inherit') }}"></div>
                </div>
                <div class="md:col-span-2">
                    <label class="text-[11.5px] font-semibold mb-1 block">{{ __('Admin note (internal)') }}</label>
                    <textarea name="admin_note" rows="3" class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px]">{{ old('admin_note', $workspace->admin_note) }}</textarea>
                </div>
                <div class="md:col-span-2 flex items-center justify-end gap-2">
                    <button type="button" data-edit-toggle
                        class="px-4 py-2 rounded-full border border-paper-200 text-[12.5px] font-medium hover:bg-paper-50">{{ __('Cancel') }}</button>
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </form>
        </section>

        {{-- Danger zone --}}
        <section class="bg-paper-0 border border-accent-coral/30 rounded-2xl p-5 shadow-card">
            <div class="flex items-center gap-2.5 mb-4">
                <span
                    class="w-[23px] h-[23px] rounded-[7px] bg-accent-coral/10 text-accent-coral inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                <span class="font-serif text-[18px] leading-none">{{ __('Danger zone') }}</span>
                <span class="font-mono text-[10px] text-accent-coral ml-auto">{{ __('irreversible') }}</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <form action="{{ route('admin.workspaces.toggle', $workspace->id) }}" method="POST"
                    class="px-3 py-2.5 rounded-lg border border-paper-200 flex items-center justify-between gap-3">
                    @csrf
                    <div>
                        <div class="text-[12.5px] font-semibold">
                            {{ $workspace->status ? 'Suspend workspace' : 'Reactivate workspace' }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-0.5">
                            {{ __('Owner can still log in but all dashboards lock until reactivated') }}</div>
                    </div>
                    <button type="submit"
                        class="px-3 py-1.5 rounded-full border border-paper-200 text-[12px] font-semibold hover:bg-paper-50">{{ $workspace->status ? 'Suspend' : 'Reactivate' }}</button>
                </form>
                <form action="{{ route('admin.workspaces.destroy', $workspace->id) }}" method="POST"
                    data-confirm="Move {{ addslashes($workspace->name) }} to trash? Recoverable for 30 days."
                    data-confirm-title="{{ __('Move workspace to trash') }}" data-confirm-text="Yes, move to trash"
                    data-danger="1"
                    class="px-3 py-2.5 rounded-lg border border-accent-coral/40 bg-accent-coral/5 flex items-center justify-between gap-3">
                    @csrf @method('DELETE')
                    <div>
                        <div class="text-[12.5px] font-semibold text-accent-coral">{{ __('Move to trash') }}</div>
                        <div class="text-[10.5px] text-ink-700 mt-0.5">{{ __('Recoverable for 30 days') }}</div>
                    </div>
                    <button type="submit"
                        class="px-3 py-1.5 rounded-full bg-accent-coral text-paper-0 text-[12px] font-semibold hover:bg-accent-coral/80">{{ __('Trash workspace') }}</button>
                </form>
            </div>
        </section>

        <script>
            window.adminWorkspaceDetail = {
                volume: @json($volume)
            };
        </script>
    </main>

</x-layouts.admin>
