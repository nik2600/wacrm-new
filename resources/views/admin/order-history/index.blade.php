<x-layouts.admin :title="__('Admin · Order history')" admin-key="order-history">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Order history') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Billing & plans · Order history') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Order') }}
                    <span class="italic text-wa-deep">{{ __('history') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Subscription orders, plan upgrades / downgrades, add-on purchases, and one-time bundles — every commercial decision a workspace has made.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1 flex-wrap">
                <form method="get" action="{{ url()->current() }}" class="inline">
                    @foreach (['type' => $typeF, 'q' => $q] as $k => $v)
                        @if ($v !== null && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <select name="window" onchange="this.form.submit()"
                        class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                        <option value="90d" @selected($window === '90d')>{{ __('Last 90 days') }}</option>
                        <option value="this_year" @selected($window === 'this_year')>{{ __('This year') }}</option>
                        <option value="all" @selected($window === 'all')>{{ __('All time') }}</option>
                    </select>
                </form>
                <a href="{{ url('/admin/order-history/analytics') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                    </svg>
                    Analytics
                </a>
                <button
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
                    </svg>
                    Export CSV
                </button>
            </div>
        </div>

        <!-- KPI strip -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total orders') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['total'] }}</div>
                <div class="text-[11px] {{ $stats['deltaPos'] ? 'text-wa-deep' : 'text-accent-coral' }} mt-2">
                    {{ $stats['delta'] }} QoQ</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Upgrades') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['upgrades'] }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ $stats['upgradesMrr'] }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Downgrades') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['downgrades'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ $stats['downMrr'] }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Add-on bundles') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['addons'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ $stats['addonMrr'] }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Cancellations') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1 text-accent-coral">{{ $stats['cancels'] }}</div>
                <div class="text-[11px] text-accent-coral mt-2">{{ $stats['cancelMrr'] }}</div>
            </div>
        </section>

        <!-- Filter row -->
        <form method="get" action="{{ url()->current() }}"
            class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex items-center gap-1 shadow-card flex-wrap">
            <input type="hidden" name="window" value="{{ $window }}">
            @php $typePills = ['all' => 'All', 'new' => 'New subscription', 'upgrade' => 'Upgrade', 'downgrade' => 'Downgrade', 'addon' => 'Add-on', 'cancel' => 'Cancel']; @endphp
            @foreach ($typePills as $k => $label)
                <button type="submit" name="type" value="{{ $k }}"
                    class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0 {{ $typeF === $k ? 'active' : '' }}">{{ $label }}
                    @if ($k === 'all')
                        <span class="font-mono text-[11px] opacity-80">({{ $stats['total'] }})</span>
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
                    placeholder="{{ __('Search by order #, workspace, plan…') }}"
                    class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-72 focus:outline-none focus:border-wa-deep" />
            </div>
        </form>

        @php
            // Queried independently of the page's window/filter/pagination
            // (controller) so a payment awaiting approval is never hidden.
            $awaiting = collect($awaitingApproval ?? [])->filter(fn($o) => $o->awaitingApproval());
        @endphp
        @if ($awaiting->isNotEmpty())
            <!-- Awaiting approval — offline / bank-transfer orders with submitted proof -->
            <section class="bg-paper-0 border border-accent-amber/50 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-3 bg-accent-amber/10 border-b border-accent-amber/40 flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 text-accent-amber" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <circle cx="8" cy="8" r="6" />
                        <path d="M8 5v3.5l2 1.5" />
                    </svg>
                    <h2 class="text-[13px] font-semibold text-[#7B5A14]">{{ __('Awaiting approval') }}</h2>
                    <span class="font-mono text-[11px] text-[#7B5A14]/80">({{ $awaiting->count() }})</span>
                </div>
                <div class="divide-y divide-paper-200">
                    @foreach ($awaiting as $o)
                        @php $proofUrl = $o->payment_proof_path ? media_url($o->payment_proof_path) : null; @endphp
                        <div class="px-5 py-4 flex flex-wrap items-center gap-4">
                            <div class="min-w-[160px]">
                                <div class="font-mono text-[11px] text-ink-900">{{ $o->order_number }}</div>
                                <div class="text-[11px] text-ink-500">{{ $o->workspace?->name ?? '—' }} ·
                                    {{ ucfirst($o->gateway_slug ?? '—') }}</div>
                            </div>
                            <div class="text-[12px] font-mono text-wa-deep">{!! \App\Support\FormatSettings::formatIn((float) ($o->total_amount ?? $o->amount), $o->currency) !!}</div>
                            <div class="flex items-center gap-3 text-[11px] text-ink-600">
                                @if ($o->payment_reference)
                                    <span><span class="text-ink-500">{{ __('Ref:') }}</span> <span
                                            class="font-mono">{{ $o->payment_reference }}</span></span>
                                @endif
                                @if ($proofUrl)
                                    <a href="{{ $proofUrl }}" target="_blank" rel="noopener"
                                        class="inline-flex items-center gap-1 text-wa-deep hover:underline font-semibold">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M2 8s2.5-4.5 6-4.5S14 8 14 8s-2.5 4.5-6 4.5S2 8 2 8z" />
                                            <circle cx="8" cy="8" r="1.8" />
                                        </svg>
                                        {{ __('View proof') }}
                                    </a>
                                @endif
                                @if ($o->proof_note)
                                    <span class="text-ink-500 italic max-w-[220px] truncate"
                                        title="{{ $o->proof_note }}">{{ $o->proof_note }}</span>
                                @endif
                            </div>
                            <div class="ml-auto flex items-center gap-2">
                                <form method="POST" action="{{ route('admin.order-history.approve', $o->id) }}">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-wa-green hover:bg-wa-deep text-paper-0 text-[11.5px] font-semibold">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.8">
                                            <path d="M3 8.5l3 3 7-7.5" />
                                        </svg>
                                        {{ __('Approve') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.order-history.reject', $o->id) }}"
                                    onsubmit="this.querySelector('[name=review_note]').value = prompt('{{ __('Reason (optional):') }}') ?? '';">
                                    @csrf
                                    <input type="hidden" name="review_note" value="">
                                    <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full hairline border border-accent-coral/50 text-accent-coral hover:bg-accent-coral/10 text-[11.5px] font-semibold">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.8">
                                            <path d="M4 4l8 8M12 4l-8 8" />
                                        </svg>
                                        {{ __('Reject') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- Orders table -->
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] table-fixed min-w-[1080px]">
                <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-4 py-2.5 w-[140px]">{{ __('Order #') }}</th>
                        <th class="text-left px-3 py-2.5 w-[130px]">{{ __('Date') }}</th>
                        <th class="text-left px-3 py-2.5">{{ __('Workspace') }}</th>
                        <th class="text-left px-3 py-2.5 w-[140px]">{{ __('Type') }}</th>
                        <th class="text-left px-3 py-2.5">{{ __('Plan / item') }}</th>
                        <th class="text-right px-3 py-2.5 w-[110px]">{{ __('MRR Δ') }}</th>
                        <th class="text-right px-3 py-2.5 w-[100px]">{{ __('Total') }}</th>
                        <th class="text-center px-3 py-2.5 w-[100px]">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    @forelse ($orders as $o)
                        @php
                            $type = \App\Http\Controllers\Admin\OrderHistoryController::typeFor($o);
                            $package = $o->package_id ? \App\Models\Package::find($o->package_id) : null;
                            $rowBg = match ($o->status) {
                                'failed' => 'bg-accent-amber/5',
                                'refunded' => 'bg-accent-coral/5',
                                default => '',
                            };
                        @endphp
                        <tr class="hover:bg-paper-50/60 {{ $rowBg }}">
                            <td class="px-4 py-3 font-mono text-[11px]">{{ $o->order_number }}</td>
                            <td class="px-3 py-3 font-mono text-[11px]">{{ $o->created_at->format('Y-m-d') }}</td>
                            <td class="px-3 py-3 font-semibold">{{ $o->workspace?->name ?? '—' }}</td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full {{ $type['class'] }} text-[10px] font-semibold">{{ $type['label'] }}</span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="text-[12px]">
                                    {{ $package?->pname ?? ($o->status === 'paid' && !$o->package_id ? 'Add-on' : '—') }}
                                    @if ($package)
                                        · {{ $package->plan_unit }}
                                    @endif
                                </div>
                                <div class="text-[10.5px] text-ink-500">
                                    {{ $package ? 'plan_' . \Illuminate\Support\Str::slug($package->pname) : $o->gateway_slug ?? '—' }}
                                </div>
                            </td>
                            <td
                                class="px-3 py-3 text-right font-mono {{ in_array($o->status, ['failed', 'refunded']) ? 'text-accent-coral' : ($o->status === 'paid' ? 'text-wa-deep' : '') }}">
                                @if ($o->status === 'paid')
                                    +{!! \App\Support\FormatSettings::formatIn((float) ($o->total_amount ?? $o->amount), $o->currency) !!}
                                @elseif (in_array($o->status, ['failed', 'refunded']))
                                    -{!! \App\Support\FormatSettings::formatIn((float) ($o->total_amount ?? $o->amount), $o->currency) !!}
                                @else
                                    —
                                @endif
                            </td>
                            <td
                                class="px-3 py-3 text-right font-mono {{ $o->status === 'paid' ? 'text-wa-deep' : '' }}">
                                {!! \App\Support\FormatSettings::formatIn((float) ($o->total_amount ?? $o->amount), $o->currency) !!}</td>
                            <td class="px-3 py-3 text-center">
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $o->status === 'paid' ? 'bg-wa-mint text-wa-deep' : ($o->status === 'failed' ? 'bg-accent-coral/10 text-accent-coral' : ($o->status === 'refunded' ? 'bg-paper-100 text-ink-700' : 'bg-accent-amber/10 text-accent-amber')) }} text-[10.5px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full {{ $o->status === 'paid' ? 'bg-wa-green' : ($o->status === 'failed' ? 'bg-accent-coral' : 'bg-accent-amber') }}"></span>{{ $o->status }}</span>
                                @if ($o->awaitingApproval())
                                    <div
                                        class="mt-1 text-[9.5px] font-mono uppercase tracking-[0.08em] text-accent-amber">
                                        {{ __('awaiting approval') }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-ink-500">
                                {{ __('No orders in this window.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            <div
                class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 rounded-b-2xl flex items-center justify-between gap-3 flex-wrap">
                <div class="text-[11px] font-mono text-ink-500">Showing
                    {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} of
                    {{ number_format($orders->total()) }} {{ __('orders') }}</div>
                <div>{{ $orders->onEachSide(1)->links() }}</div>
            </div>
        </div>
    </main>

</x-layouts.admin>
