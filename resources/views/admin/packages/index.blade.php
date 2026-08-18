<x-layouts.admin :title="__('Admin · Packages')" admin-key="packages" page="packages-index">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Packages') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Billing & plans · Packages') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Subscription') }}
                    <span class="italic text-wa-deep">{{ __('packages') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Define every plan offered to workspaces — pricing, message caps, feature flags, and add-ons.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ route('admin.packages.analytics') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                    </svg>
                    Analytics
                </a>
                <a href="{{ route('admin.packages.create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    Add package
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div
                class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-2 text-[12.5px]">
                {{ session('error') }}</div>
        @endif

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active packages') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['active']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ $stats['trial'] }} trial · {{ $stats['archived'] }}
                    {{ __('archived') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Workspaces subscribed') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['subscribed']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('across all plans') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('MRR (active subs)') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{!! \App\Support\FormatSettings::currency($stats['mrr']) !!}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('plan_amount × subscriptions') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total packages') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($packages->count()) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('in catalog') }}</div>
            </div>
        </section>

        {{-- Plan cards — generated from real Package rows. --}}
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @forelse ($packages as $p)
                @php
                    $count = (int) ($wsCounts[$p->id] ?? 0);
                    $bullets = [];
                    if ($p->monthly_messages_limit) {
                        $bullets[] = number_format($p->monthly_messages_limit) . ' messages';
                    }
                    if ($p->device_limit) {
                        $bullets[] = $p->device_limit . ' devices · ' . ($p->user_seat_limit ?? '∞') . ' users';
                    }
                    if ($p->autoreply) {
                        $bullets[] = 'Auto-reply';
                    }
                    if ($p->autoflow) {
                        $bullets[] = 'Flow builder';
                    }
                    if ($p->access_analytics) {
                        $bullets[] = 'Analytics';
                    }
                    if ($p->remove_branding) {
                        $bullets[] = 'Custom branding';
                    }
                @endphp
                <div
                    class="bg-paper-0 border {{ $p->is_highlighted ? 'border-2 border-wa-deep' : 'border-paper-200' }} rounded-2xl p-5 shadow-card relative">
                    @if ($p->is_highlighted)
                        <span
                            class="absolute -top-3 left-5 px-2.5 py-0.5 rounded-full bg-wa-deep text-paper-0 text-[10px] font-semibold uppercase tracking-wider">{{ __('Most popular') }}</span>
                    @endif
                    <div class="flex items-center justify-between mb-3">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">#{{ $p->id }}</span>
                        @if ($p->free)
                            <span
                                class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-700 text-[10px] font-semibold">{{ __('Free') }}</span>
                        @elseif ($p->is_custom_quote)
                            <span
                                class="px-2 py-0.5 rounded-full bg-[#D9E5F2] text-[#13478A] text-[10px] font-semibold">{{ __('Custom') }}</span>
                        @else
                            <span
                                class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ ucfirst($p->plan_unit ?? 'month') }}</span>
                        @endif
                    </div>
                    <h3 class="font-serif text-[26px] leading-none">{{ $p->pname }}</h3>
                    <div class="mt-3 flex items-baseline gap-1">
                        @if ($p->is_custom_quote)
                            <span class="font-serif text-[28px]">{{ __('Custom') }}</span>
                        @elseif ($p->free)
                            <span class="font-serif text-[32px]">{{ __('Free') }}</span>
                            @if ($p->plan_duration > 0)
                                <span class="text-[12px] text-ink-500">/ {{ $p->plan_duration }}
                                    {{ $p->plan_unit }}</span>
                            @endif
                        @else
                            <span class="font-serif text-[32px]">{!! \App\Support\FormatSettings::formatIn($p->chargeableAmount(), $p->currency ?? 'USD') !!}</span>
                            <span class="text-[12px] text-ink-500">/ {{ $p->plan_unit ?? 'month' }}</span>
                        @endif
                    </div>
                    <ul class="mt-4 space-y-1.5 text-[12px] text-ink-700">
                        @foreach (array_slice($bullets, 0, 4) as $b)
                            <li class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep"
                                    fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 8l3 3 7-7" />
                                </svg>{{ $b }}</li>
                        @endforeach
                        @if (empty($bullets))
                            <li class="text-ink-500 italic text-[11.5px]">{{ __('No limits configured yet') }}</li>
                        @endif
                    </ul>
                    <div
                        class="mt-5 pt-4 border-t border-paper-200 flex items-center justify-between text-[11px] font-mono text-ink-500">
                        <span class="{{ $count > 50 ? 'text-wa-deep font-semibold' : '' }}">{{ $count }}
                            workspace{{ $count === 1 ? '' : 's' }}</span>
                        <a href="{{ route('admin.packages.edit', $p->id) }}" class="text-wa-deep hover:underline">Edit
                            →</a>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center text-ink-500 py-10">
                    No packages defined yet. <a href="{{ route('admin.packages.create') }}"
                        class="text-wa-deep hover:underline">{{ __('Create the first one →') }}</a>
                </div>
            @endforelse
        </section>

        {{-- Plan table — full detailed list. --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('All packages') }}</div>
                    <h2 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Detailed list') }}</h2>
                </div>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] table-fixed min-w-[820px]">
                <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-3 py-2.5 w-[40px]">#</th>
                        <th class="text-left px-2 py-2.5">{{ __('Package') }}</th>
                        <th class="text-right px-2 py-2.5 w-[110px]">{{ __('Price') }}</th>
                        <th class="text-left px-2 py-2.5 w-[140px]">{{ __('Duration') }}</th>
                        <th class="text-right px-2 py-2.5 w-[110px]">{{ __('Subscribers') }}</th>
                        <th class="text-right px-2 py-2.5 w-[100px]">{{ __('MRR') }}</th>
                        <th class="text-center px-2 py-2.5 w-[80px]">{{ __('Status') }}</th>
                        <th class="text-center px-2 py-2.5 w-[44px]"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    @forelse ($packages as $p)
                        @php
                            $count = (int) ($wsCounts[$p->id] ?? 0);
                            $mrrRow = $p->free ? 0 : $p->chargeableAmount() * $count;
                            $initials = mb_strtoupper(mb_substr($p->pname, 0, 2));
                        @endphp
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-3 py-2 font-mono text-[10.5px] text-ink-500">{{ $p->id }}</td>
                            <td class="px-2 py-2">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="w-7 h-7 rounded-lg bg-paper-100 text-ink-700 grid place-items-center text-[10px] font-bold">{{ $initials }}</span>
                                    <div>
                                        <div class="font-semibold leading-none text-[12.5px]">
                                            {{ $p->pname }}
                                            @if ($p->is_highlighted)
                                                <span
                                                    class="ml-1 px-1.5 py-0.5 rounded-full bg-wa-deep text-paper-0 text-[9px] font-semibold">{{ __('popular') }}</span>
                                            @endif
                                            @if ($p->is_default)
                                                <span
                                                    class="ml-1 px-1.5 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[9px] font-semibold">{{ __('default') }}</span>
                                            @endif
                                        </div>
                                        <div class="text-[10px] text-ink-500 mt-1 font-mono">
                                            {{ $p->subtitle ?: 'no subtitle' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-2 py-2 text-right font-mono">
                                @if ($p->is_custom_quote)
                                    <span class="text-ink-500">{{ __('Custom') }}</span>
                                @elseif ($p->free)
                                    <span class="text-ink-500">{{ __('Free') }}</span>
                                @else
                                    {!! \App\Support\FormatSettings::formatIn($p->chargeableAmount(), $p->currency ?? 'USD') !!}
                                @endif
                            </td>
                            <td class="px-2 py-2 text-[11.5px]">{{ $p->plan_duration }}
                                {{ ucfirst($p->plan_unit ?? 'month') }}</td>
                            <td class="px-2 py-2 text-right font-mono">{{ $count }}</td>
                            <td
                                class="px-2 py-2 text-right font-mono {{ $mrrRow > 0 ? 'text-wa-deep' : 'text-ink-500' }}">
                                {!! $mrrRow > 0 ? \App\Support\FormatSettings::formatIn($mrrRow, $p->currency ?? 'USD') : '—' !!}</td>
                            <td class="px-2 py-2 text-center">
                                <form action="{{ route('admin.packages.toggle', $p->id) }}" method="POST"
                                    class="inline">
                                    @csrf
                                    <label class="relative inline-block w-9 h-5 align-middle cursor-pointer">
                                        <input type="checkbox" class="peer opacity-0 w-0 h-0"
                                            @checked($p->status) onchange="this.form.submit()">
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
                                        class="hidden absolute right-0 top-full mt-1 z-50 w-[180px] bg-paper-0 border border-paper-200 rounded-xl shadow-soft py-1 text-left">
                                        <a href="{{ route('admin.packages.edit', $p->id) }}"
                                            class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <path d="M9.5 3.5 12.5 6.5 6 13H3v-3z" />
                                            </svg>{{ __('Edit') }}
                                        </a>
                                        <div class="border-t border-paper-200 my-1"></div>
                                        <form action="{{ route('admin.packages.destroy', $p->id) }}" method="POST"
                                            data-confirm="Delete {{ addslashes($p->pname) }}? This cannot be undone. Workspaces currently on this plan will need to be moved manually."
                                            data-confirm-title="{{ __('Delete package') }}"
                                            data-confirm-text="Yes, delete" data-danger="1">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-accent-coral hover:bg-accent-coral/10">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                    stroke="currentColor" stroke-width="1.6">
                                                    <path d="M3 4h10M6 4V2.8h4V4M5 6v8h6V6" />
                                                </svg>{{ __('Delete') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-ink-500">
                                {{ __('No packages defined.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </main>

</x-layouts.admin>
