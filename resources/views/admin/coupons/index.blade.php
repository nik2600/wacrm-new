<x-layouts.admin :title="__('Admin · Coupons')" admin-key="coupons" page="admin-coupons-index">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Coupons') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Billing & plans · Coupons') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[40px] leading-[1.0]">{{ __('Discount') }}
                    <span class="italic text-wa-deep">{{ __('coupons') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __("Codes customers apply at checkout. Percent or fixed amount, time-bounded, plan-scoped, capped redemptions — admin's choice.") }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ route('admin.coupons.create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    New coupon
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total coupons') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['total']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('in catalog') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['active']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('accepting redemptions') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Expired') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['expired']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('past expiry date') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total redemptions') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['redeemed']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('all-time uses') }}</div>
            </div>
        </section>

        {{-- Filter / search bar — matches users/workspaces pattern. --}}
        <form method="get" action="{{ route('admin.coupons.index') }}"
            class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex items-center gap-1 shadow-card flex-wrap">
            @php $statusPills = ['all' => 'All', 'active' => 'Active', 'disabled' => 'Disabled', 'expired' => 'Expired']; @endphp
            @foreach ($statusPills as $k => $label)
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
                    @elseif ($k === 'expired')
                        <span
                            class="font-mono text-[11px] {{ $statusF === 'expired' ? '' : 'text-accent-amber' }}">{{ $stats['expired'] }}</span>
                    @endif
                </button>
            @endforeach
            <div class="flex-1"></div>
            <div class="relative">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                    fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="7" cy="7" r="5" />
                    <path d="m11 11 3 3" />
                </svg>
                <input name="q" value="{{ $q }}"
                    placeholder="{{ __('Search code or description...') }}"
                    class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-72 focus:outline-none focus:border-wa-deep">
            </div>
        </form>

        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] text-[12.5px] table-fixed">
                <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-4 py-2.5">{{ __('Code') }}</th>
                        <th class="text-left px-3 py-2.5 w-[160px]">{{ __('Discount') }}</th>
                        <th class="text-left px-3 py-2.5 w-[130px]">{{ __('Uses') }}</th>
                        <th class="text-left px-3 py-2.5 w-[200px]">{{ __('Validity') }}</th>
                        <th class="text-center px-3 py-2.5 w-[100px]">{{ __('Status') }}</th>
                        <th class="text-center px-3 py-2.5 w-[44px]"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    @forelse ($coupons as $c)
                        @php $expired = $c->expires_at && $c->expires_at->isPast(); @endphp
                        <tr class="hover:bg-paper-50/60 {{ $expired ? 'opacity-60' : '' }}">
                            <td class="px-4 py-3 min-w-0">
                                <div class="font-semibold font-mono text-[13px]">{{ $c->code }}</div>
                                @if ($c->description)
                                    <div class="text-[10.5px] text-ink-500 mt-0.5 truncate">{{ $c->description }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-3 font-mono">
                                @if ($c->type === 'percent')
                                    {{ rtrim(rtrim(number_format((float) $c->amount, 2), '0'), '.') }}%
                                @else
                                    {!! \App\Support\FormatSettings::currency((float) $c->amount) !!}
                                @endif
                            </td>
                            <td class="px-3 py-3 font-mono text-[11.5px] text-ink-700">
                                {{ number_format($c->uses_count) }}{{ $c->max_uses ? ' / ' . number_format($c->max_uses) : ' / ∞' }}
                            </td>
                            <td class="px-3 py-3 font-mono text-[11px] text-ink-600">
                                @if ($c->starts_at || $c->expires_at)
                                    {{ $c->starts_at?->format('M j, Y') ?: 'now' }} →
                                    {{ $c->expires_at?->format('M j, Y') ?: 'forever' }}
                                @else
                                    <span class="text-ink-500">{{ __('forever') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">
                                <form method="POST" action="{{ route('admin.coupons.toggle', $c->id) }}"
                                    class="inline">
                                    @csrf
                                    <label class="relative inline-block w-9 h-5 align-middle cursor-pointer">
                                        <input type="checkbox" class="peer opacity-0 w-0 h-0"
                                            @checked($c->is_active) onchange="this.form.submit()">
                                        <span
                                            class="absolute inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[16px]"></span>
                                    </label>
                                </form>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <div class="relative inline-block" data-row-menu>
                                    <button type="button"
                                        class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center mx-auto"
                                        title="{{ __('Actions') }}" data-row-menu-toggle>
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-600" fill="currentColor">
                                            <circle cx="3" cy="8" r="1.2" />
                                            <circle cx="8" cy="8" r="1.2" />
                                            <circle cx="13" cy="8" r="1.2" />
                                        </svg>
                                    </button>
                                    <div data-row-menu-panel
                                        class="hidden absolute right-0 top-full mt-1 z-50 w-[180px] bg-paper-0 border border-paper-200 rounded-xl shadow-soft py-1 text-left">
                                        <a href="{{ route('admin.coupons.edit', $c->id) }}"
                                            class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <path d="M9.5 3.5 12.5 6.5 6 13H3v-3z" />
                                            </svg>{{ __('Edit') }}
                                        </a>
                                        <div class="border-t border-paper-200 my-1"></div>
                                        <form method="POST" action="{{ route('admin.coupons.destroy', $c->id) }}"
                                            data-confirm="Delete coupon {{ addslashes($c->code) }}? Workspaces that already redeemed it keep their discount."
                                            data-confirm-title="{{ __('Delete coupon') }}"
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
                            <td colspan="6" class="px-4 py-10 text-center text-ink-500">
                                <div class="font-serif text-[20px] mb-1">{{ __('No coupons match.') }}</div>
                                <p class="text-[12.5px]">{{ __('Try clearing the filters or') }} <a
                                        href="{{ route('admin.coupons.create') }}"
                                        class="text-wa-deep font-semibold hover:underline">{{ __('create the first one') }}</a>.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            <div
                class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 flex flex-wrap gap-2 items-center justify-between rounded-b-2xl">
                <div class="text-[11px] font-mono text-ink-500">
                    Showing {{ $coupons->firstItem() ?? 0 }}–{{ $coupons->lastItem() ?? 0 }} of
                    {{ number_format($coupons->total()) }} {{ __('coupons') }}
                </div>
                <div>{{ $coupons->onEachSide(1)->links() }}</div>
            </div>
        </div>
    </main>

</x-layouts.admin>
