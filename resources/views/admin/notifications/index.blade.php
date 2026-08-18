<x-layouts.admin :title="__('Admin · Notifications')" admin-key="notifications" page="admin-notifications">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Notifications') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        {{-- Title row --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Platform activity · Notifications') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Admin') }}
                    <span class="italic text-wa-deep">{{ __('notifications') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Everything happening across the platform — new signups, payments, support tickets, contact messages, and new workspaces. Separate from a workspace operator\'s personal notifications.') }}
                </p>
            </div>
            <form method="post" action="{{ route('admin.notifications.read-all') }}" class="shrink-0 pb-1"
                onsubmit="event.preventDefault(); fetch(this.action,{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}}).then(()=>location.reload());">
                @csrf
                <button
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l3 3 7-8" />
                    </svg>
                    {{ __('Mark all read') }}
                </button>
            </form>
        </div>

        {{-- KPI strip — counts over the last 30 days --}}
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @php
                $cards = [
                    ['all', __('All events'), $stats['total'], 'border-paper-200', 'text-ink-900'],
                    ['signup', __('Signups'), $stats['signup'], 'border-paper-200', 'text-ink-900'],
                    ['payment', __('Payments'), $stats['payment'], 'border-wa-green/40', 'text-wa-deep'],
                    ['ticket', __('Support'), $stats['ticket'], 'border-accent-amber/40', 'text-[#7B5A14]'],
                    ['contact', __('Messages'), $stats['contact'], 'border-paper-200', 'text-ink-900'],
                    ['workspace', __('Workspaces'), $stats['workspace'], 'border-paper-200', 'text-ink-900'],
                ];
            @endphp
            @foreach ($cards as [$key, $label, $val, $border, $num])
                <a href="{{ url()->current() }}?type={{ $key }}{{ $q !== '' ? '&q=' . urlencode($q) : '' }}"
                    class="bg-paper-0 border {{ $border }} rounded-2xl p-4 shadow-card transition hover:shadow-soft {{ $typeF === $key ? 'ring-2 ring-wa-deep/30' : '' }}">
                    <div class="text-[11px] text-ink-600 font-medium">{{ $label }}</div>
                    <div class="font-serif text-[34px] leading-none mt-1 {{ $num }}">
                        {{ number_format($val) }}</div>
                    <div class="text-[10.5px] text-ink-500 mt-2 font-mono">{{ __('last 30 days') }}</div>
                </a>
            @endforeach
        </section>

        {{-- Filter row --}}
        <form method="get" action="{{ url()->current() }}"
            class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex items-center gap-1 shadow-card flex-wrap">
            @php $pills = ['all' => __('All')] + collect(\App\Http\Controllers\Admin\AdminNotificationController::TYPES)->map(fn($l)=>__($l))->all(); @endphp
            @foreach ($pills as $k => $label)
                <button type="submit" name="type" value="{{ $k }}"
                    class="inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] cursor-pointer transition {{ $typeF === $k ? 'bg-ink-900 text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">
                    {{ $label }}
                </button>
            @endforeach
            <div class="flex-1"></div>
            <div class="relative">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                    fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="7" cy="7" r="5" />
                    <path d="m11 11 3 3" />
                </svg>
                <input type="hidden" name="type" value="{{ $typeF }}">
                <input name="q" value="{{ $q }}" placeholder="{{ __('Search events…') }}"
                    class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-64 focus:outline-none focus:border-wa-deep" />
            </div>
        </form>

        {{-- Events table --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] table-fixed">
                <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-4 py-2.5 w-[180px]">{{ __('Type') }}</th>
                        <th class="text-left px-3 py-2.5">{{ __('Event') }}</th>
                        <th class="text-left px-3 py-2.5">{{ __('Detail') }}</th>
                        <th class="text-right px-3 py-2.5 w-[150px]">{{ __('When') }}</th>
                        <th class="text-center px-3 py-2.5 w-[90px]">{{ __('Open') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    @php
                        $meta = [
                            'signup' => [
                                'Signup',
                                'bg-paper-100 text-ink-700',
                                '<circle cx="8" cy="6" r="3"/><path d="M2 14c0-3 2.5-5 6-5s6 2 6 5"/>',
                            ],
                            'payment' => [
                                'Payment',
                                'bg-wa-mint text-wa-deep',
                                '<rect x="2" y="4" width="12" height="8" rx="1.5"/><path d="M2 7h12"/>',
                            ],
                            'ticket' => [
                                'Support',
                                'bg-[#FFF4E0] text-[#7B5A14]',
                                '<path d="M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5z"/>',
                            ],
                            'contact' => [
                                'Message',
                                'bg-paper-100 text-ink-700',
                                '<rect x="2.5" y="3.5" width="11" height="9" rx="1.5"/><path d="M2.5 5l5.5 4 5.5-4"/>',
                            ],
                            'workspace' => [
                                'Workspace',
                                'bg-paper-100 text-ink-700',
                                '<rect x="2.5" y="3" width="11" height="10" rx="1.5"/><path d="M2.5 6.5h11"/>',
                            ],
                        ];
                    @endphp
                    @forelse ($events as $e)
                        @php $m = $meta[$e['type']] ?? ['Event','bg-paper-100 text-ink-700','<circle cx="8" cy="8" r="6"/>']; @endphp
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full {{ $m[1] }} text-[10.5px] font-semibold">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                        stroke-width="1.7">{!! $m[2] !!}</svg>{{ $m[0] }}
                                </span>
                            </td>
                            <td class="px-3 py-3 font-semibold text-ink-900">{{ $e['title'] }}</td>
                            <td class="px-3 py-3 text-ink-600 truncate">{{ $e['message'] ?: '—' }}</td>
                            <td class="px-3 py-3 text-right font-mono text-[11px] text-ink-500">
                                {{ $e['when']->diffForHumans() }}</td>
                            <td class="px-3 py-3 text-center">
                                <a href="{{ $e['url'] }}"
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg hairline border border-paper-200 hover:bg-paper-50 text-ink-600"
                                    title="{{ __('Open') }}">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="1.7">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10M9 4l4 4-4 4" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-ink-500">
                                {{ __('No platform activity in this view.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            <div
                class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 rounded-b-2xl flex items-center justify-between gap-3 flex-wrap">
                <div class="text-[11px] font-mono text-ink-500">{{ __('Showing') }} {{ number_format($total) }}
                    {{ __('recent events') }}{{ $typeF !== 'all' ? ' · ' . __(\App\Http\Controllers\Admin\AdminNotificationController::TYPES[$typeF] ?? '') : '' }}
                </div>
                <div class="text-[11px] font-mono text-ink-400">{{ __('Live feed — newest first') }}</div>
            </div>
        </div>
    </main>

</x-layouts.admin>
