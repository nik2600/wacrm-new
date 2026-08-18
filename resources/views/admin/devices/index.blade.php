<x-layouts.admin :title="__('Admin · Devices')" admin-key="devices" page="devices-index">



    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Devices') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        @php
            $pctOf = fn($n) => $counts['all'] > 0 ? round($n / $counts['all'] * 100, 1) . '%' : '0%';
        @endphp

        @if (session('status'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-mint/60 px-4 py-3 text-[12.5px] text-wa-deep">
                {{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div
                class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-3 text-[12.5px] text-accent-coral">
                {{ $errors->first() }}</div>
        @endif

        <!-- Heading -->
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Platform devices') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[40px] leading-[1.0]">{{ __('All') }}
                    <span class="italic text-wa-deep">{{ __('devices') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Every paired WhatsApp number across the platform. Force re-pair, disconnect, or transfer ownership without leaving the admin console.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1 flex-wrap">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono"><span
                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ number_format($counts['connected']) }} {{ __('connected') }}</span>
                {{-- Bulk re-sync: pulls live status for every device from the
                     bridge. data-confirm is handled by lib/ui-modal (app.js). --}}
                <form method="POST" action="{{ route('admin.devices.bulk-resync') }}"
                    data-confirm="{{ __('Re-sync every device from the bridge? This pulls the live connection status for all numbers.') }}">
                    @csrf
                    <button type="submit"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                        </svg>
                        {{ __('Bulk re-sync') }}
                    </button>
                </form>
                {{-- Export honours the current status + search filters. --}}
                <a href="{{ route('admin.devices.export', array_filter(['status' => $status !== 'all' ? $status : null, 'q' => $q ?: null])) }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
                    </svg>
                    {{ __('Export CSV') }}
                </a>
            </div>
        </div>

        <!-- KPI strip -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Total devices') }}</span><span
                        class="text-[10px] text-wa-deep font-mono">{{ $newThisWeek > 0 ? '+' . number_format($newThisWeek) : '0' }} {{ __('this week') }}</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[30px] leading-none">{{ number_format($counts['all']) }}</span><span
                        class="text-[11px] text-ink-500">{{ __('across :n wks', ['n' => number_format($workspacesWithDevices)]) }}</span></div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Connected') }}</span><span
                        class="text-[10px] text-wa-deep font-mono">{{ $pctOf($counts['connected']) }}</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[30px] leading-none">{{ number_format($counts['connected']) }}</span><span
                        class="text-[11px] text-wa-deep">{{ __('healthy') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Needs re-pair') }}</span><span
                        class="text-[10px] text-accent-amber font-mono">{{ __('action req') }}</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[30px] leading-none">{{ number_format($counts['needs_pair']) }}</span><span
                        class="text-[11px] text-ink-500">{{ __('expired QR') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Disconnected') }}</span><span
                        class="text-[10px] text-accent-coral font-mono">{{ __('offline') }}</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[30px] leading-none">{{ number_format($counts['disconnected']) }}</span><span
                        class="text-[11px] text-ink-500">{{ __('offline') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sent (24h)') }}</span><span
                        class="text-[10px] text-wa-deep font-mono">{{ __('live') }}</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[30px] leading-none">{{ number_format($sent24hTotal) }}</span><span
                        class="text-[11px] text-ink-500">{{ __('across all') }}</span></div>
            </div>
        </section>

        <!-- Filter bar -->
        <form method="GET"
            class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex items-center gap-1 shadow-card flex-wrap">
            @foreach (['all' => __('All'), 'connected' => __('Connected'), 'needs_pair' => __('Needs re-pair'), 'disconnected' => __('Disconnected')] as $k => $label)
                @php $tone = $k === 'needs_pair' ? 'text-accent-amber' : ($k === 'disconnected' ? 'text-accent-coral' : 'opacity-80'); @endphp
                <a href="{{ request()->fullUrlWithQuery(['status' => $k, 'page' => 1]) }}"
                    class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0 {{ $status === $k ? 'active' : '' }}">
                    {{ $label }} <span
                        class="font-mono text-[11px] {{ $status === $k ? 'opacity-80' : $tone }}">{{ $k === 'all' ? '(' . number_format($counts['all']) . ')' : number_format($counts[$k]) }}</span></a>
            @endforeach
            <div class="flex-1"></div>
            <div class="flex items-center gap-1.5 flex-wrap">
                <input type="hidden" name="status" value="{{ $status }}">
                <div class="relative flex-1 min-w-[180px]">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                        fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="7" cy="7" r="5" />
                        <path d="m11 11 3 3" />
                    </svg>
                    <input name="q" value="{{ $q }}" placeholder="{{ __('Search name, number, owner…') }}"
                        class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-full sm:w-72 focus:outline-none focus:border-wa-deep" />
                </div>
                <button
                    class="px-4 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Search') }}</button>
                @if ($q !== '')
                    <a href="{{ route('admin.devices.index', ['status' => $status]) }}"
                        class="px-3 py-1.5 rounded-full border border-paper-200 text-[12px] hover:bg-paper-50">{{ __('Clear') }}</a>
                @endif
            </div>
        </form>

        <!-- Devices table -->
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] table-fixed min-w-[880px]">
                <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-3 py-2.5 w-[34px]"><input type="checkbox"
                                class="rounded border-paper-300"></th>
                        <th class="text-left px-2 py-2.5 w-[44px]"></th>
                        <th class="text-left px-2 py-2.5">{{ __('Device & number') }}</th>
                        <th class="text-left px-2 py-2.5 w-[150px]">{{ __('Workspace') }}</th>
                        <th class="text-left px-2 py-2.5 w-[130px]">{{ __('Owner') }}</th>
                        <th class="text-left px-2 py-2.5 w-[100px]">{{ __('Last sync') }}</th>
                        <th class="text-right px-2 py-2.5 w-[80px]">{{ __('Sent 24h') }}</th>
                        <th class="text-center px-2 py-2.5 w-[110px]">{{ __('Status') }}</th>
                        <th class="text-center px-2 py-2.5 w-[44px]"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">

                    @forelse ($devices as $d)
                        @php
                            $phone = trim((string) $d->country_code . ' ' . (string) $d->phone_number);
                            $ownerNm = (string) optional($d->user)->name;
                            $initials =
                                collect(preg_split('/\s+/', trim($ownerNm)))
                                    ->filter()
                                    ->take(2)
                                    ->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                                    ->implode('') ?:
                                '—';
                            $pill = match ($d->status) {
                                'connected' => ['bg-wa-mint text-wa-deep', 'bg-wa-green', __('Connected')],
                                'needs_pair' => ['bg-accent-amber/15 text-accent-amber', 'bg-accent-amber', __('Re-pair')],
                                'failed' => ['bg-accent-coral/10 text-accent-coral', 'bg-accent-coral', __('Failed')],
                                default => ['bg-paper-100 text-ink-700', 'bg-paper-300', __('Disconnected')],
                            };
                        @endphp
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-3 py-2"><input type="checkbox" class="rounded border-paper-300"></td>
                            <td class="px-2 py-2"><span
                                    class="w-9 h-9 rounded-lg bg-wa-mint text-wa-deep grid place-items-center"><svg
                                        viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <rect x="3.5" y="2" width="9" height="12" rx="1.5" />
                                        <circle cx="8" cy="11.5" r="0.8" />
                                    </svg></span></td>
                        <td class="px-2 py-2 min-w-0">
                                <div class="font-semibold leading-none text-[12px] truncate">
                                    {{ $d->device_name ?: __('Device #:id', ['id' => $d->id]) }}</div>
                                <div class="text-[10px] text-ink-500 mt-1 font-mono leading-none truncate">
                                    {{ $phone !== '' ? mask_phone($phone) : '—' }}{{ $d->region ? ' · ' . $d->region : '' }}
                                </div>
                            </td>
                            <td class="px-2 py-2">
                                <div class="text-[12px] font-semibold leading-none truncate">
                                    {{ optional($d->workspace)->name ?: '—' }}</div>
                                <div class="text-[9.5px] text-ink-500 font-mono uppercase tracking-[0.12em] mt-1">
                                    {{ optional($d->workspace)->plan ?: '—' }}</div>
                            </td>
                            <td class="px-2 py-2">
                                <div class="flex items-center gap-1.5"><span
                                        class="w-5 h-5 rounded-full bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 grid place-items-center text-[8.5px] font-bold">{{ $initials }}</span><span
                                        class="text-[11.5px] truncate">{{ $ownerNm !== '' ? $ownerNm : '—' }}</span>
                                </div>
                            </td>
                            <td
                                class="px-2 py-2 font-mono text-[10.5px] {{ $d->status === 'connected' ? 'text-wa-deep' : 'text-ink-500' }} whitespace-nowrap">
                                {{ $d->last_seen_at ? $d->last_seen_at->diffForHumans(null, true) : '—' }}</td>
                            <td class="px-2 py-2 font-mono text-[11.5px] text-right">
                                {{ number_format((int) $d->sent_24h) }}</td>
                            <td class="px-2 py-2 text-center"><span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $pill[0] }} text-[10.5px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full {{ $pill[1] }}"></span>{{ $pill[2] }}</span></td>
                            <td class="px-2 py-2 text-center">
                                <div class="relative inline-block"><button type="button"
                                        class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center mx-auto"
                                        onclick="toggleDevMenu(event,this)" title="{{ __('Actions') }}"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-600" fill="currentColor">
                                            <circle cx="3" cy="8" r="1.2" />
                                            <circle cx="8" cy="8" r="1.2" />
                                            <circle cx="13" cy="8" r="1.2" />
                                        </svg></button>
                                    <div
                                        class="dev-action-menu hidden absolute right-0 top-full mt-1 z-50 w-[200px] bg-paper-0 border border-paper-200 rounded-xl shadow-soft py-1 text-left">
                                        <a href="{{ route('admin.devices.detail', $d->id) }}"
                                            class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50"><svg
                                                viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                                            </svg>{{ __('View analytics') }}</a>
                                        {{-- Re-sync one device — pulls live status from the bridge, like the
                                             user-side reconnect but admin-scoped. --}}
                                        <form method="POST" action="{{ route('admin.devices.resync', $d->id) }}">
                                            @csrf
                                            <button type="submit"
                                                class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50"><svg
                                                    viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                                    stroke="currentColor" stroke-width="1.6">
                                                    <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                                                </svg>{{ __('Re-sync status') }}</button>
                                        </form>
                                        @if ($d->status === 'connected')
                                            <form method="POST"
                                                action="{{ route('admin.devices.disconnect', $d->id) }}"
                                                data-confirm="{{ __('Force-disconnect this device? Its live WhatsApp session will be terminated.') }}">
                                                @csrf
                                                <button type="submit"
                                                    class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-accent-amber hover:bg-accent-amber/10"><svg
                                                        viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                        stroke="currentColor" stroke-width="1.7">
                                                        <path d="M5 7L3 9l4 4 2-2M11 9l2-2-4-4-2 2" />
                                                    </svg>{{ __('Force disconnect') }}</button>
                                            </form>
                                        @endif
                                        <div class="border-t border-paper-200 my-1"></div>
                                        <form method="POST" action="{{ route('admin.devices.destroy', $d->id) }}"
                                            data-confirm="{{ __('Remove this device permanently? This cannot be undone.') }}"
                                            data-danger="1">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-accent-coral hover:bg-accent-coral/10"><svg
                                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                    stroke="currentColor" stroke-width="1.6">
                                                    <path d="M3 4h10M6 4V2.8h4V4M5 6v8h6V6" />
                                                </svg>{{ __('Remove device') }}</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-12 text-center text-ink-500">
                                {{ $q !== '' || $status !== 'all' ? __('No devices match this filter.') : __('No devices have been paired on the platform yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            <div
                class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 flex items-center justify-between rounded-b-2xl gap-3 flex-wrap">
                <div class="text-[11px] font-mono text-ink-500">{{ __('Showing') }} {{ $devices->firstItem() ?? 0 }}–{{ $devices->lastItem() ?? 0 }} {{ __('of') }} {{ number_format($devices->total()) }} {{ __('devices') }}</div>
                <div>{{ $devices->onEachSide(1)->links() }}</div>
            </div>
        </div>
    </main>

</x-layouts.admin>
