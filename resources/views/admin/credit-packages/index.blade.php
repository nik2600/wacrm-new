<x-layouts.admin :title="__('Admin · Credit Packages')" admin-key="credit-packages" page="credit-packages-index">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Credit packages') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Billing & plans · Credit packages') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[40px] leading-[1.0]">{{ __('Credit') }}
                    <span class="italic text-wa-deep">{{ __('packages') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Curated bundles users can buy from the checkout page. Each package converts to a fixed credit count when purchased — independent of the per-paise conversion rate set in') }}
                    <a href="{{ route('admin.settings.index') }}"
                        class="text-wa-deep font-semibold hover:underline">{{ __('Settings') }}</a>.</p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ route('admin.credit-packages.create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    New package
                </a>
            </div>
        </div>

        <x-admin.flash />

        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-[12.5px]">
                <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                    <tr>
                        <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                            {{ __('Name') }}</th>
                        <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                            {{ __('Slug') }}</th>
                        <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                            {{ __('Price') }}</th>
                        <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                            {{ __('Credits') }}</th>
                        <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">Per
                            {{ \App\Support\FormatSettings::currencyFor()?->symbol ?? '$' }}1</th>
                        <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                            {{ __('Badge') }}</th>
                        <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                            {{ __('Status') }}</th>
                        <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-[200px]">
                            {{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    @forelse ($packages as $p)
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold">{{ $p->name }}</div>
                                @if ($p->description)
                                    <div class="text-[10.5px] text-ink-500 mt-0.5 max-w-md truncate">
                                        {{ $p->description }}</div>
                                @endif
                            </td>
                            <td class="px-2 py-3 font-mono text-[11.5px] text-ink-700">{{ $p->slug }}</td>
                            <td class="px-2 py-3 text-right font-semibold">{{ $p->price_display }}</td>
                            <td class="px-2 py-3 text-right font-mono">{{ number_format($p->credits) }}</td>
                            <td class="px-2 py-3 text-right font-mono text-ink-700">
                                {{ number_format($p->credits_per_major, 1) }}</td>
                            <td class="px-2 py-3">
                                @if ($p->badge)
                                    <span
                                        class="text-[10.5px] font-mono px-2 py-0.5 rounded bg-accent-amber/20 text-[#7B5A14]">{{ $p->badge }}</span>
                                @else
                                    <span class="text-[10.5px] text-ink-500">—</span>
                                @endif
                            </td>
                            <td class="px-2 py-3">
                                @if ($p->is_active)
                                    <span
                                        class="inline-flex items-center gap-1 text-[10.5px] font-mono text-wa-deep"><span
                                            class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Active</span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1 text-[10.5px] font-mono text-ink-500"><span
                                            class="w-1.5 h-1.5 rounded-full bg-paper-200"></span>Disabled</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.credit-packages.edit', $p->id) }}"
                                    class="text-[11px] text-wa-deep font-semibold hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.credit-packages.toggle', $p->id) }}"
                                    class="inline-block ml-2">@csrf
                                    <button type="submit"
                                        class="text-[11px] text-ink-700 hover:underline">{{ $p->is_active ? 'Disable' : 'Enable' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.credit-packages.destroy', $p->id) }}"
                                    class="inline-block ml-2"
                                    data-confirm="Delete this package? Existing purchases keep their ledger rows."
                                    data-confirm-title="{{ __('Delete credit package') }}"
                                    data-confirm-text="Yes, delete" data-danger="1">@csrf @method('DELETE')
                                    <button type="submit"
                                        class="text-[11px] text-accent-coral hover:underline">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-ink-500">
                                <div class="font-serif text-[20px]">{{ __('No credit packages yet') }}</div>
                                <p class="mt-1 text-[12.5px]">{{ __("Create the first one — it'll show up at") }} <span
                                        class="font-mono">/checkout?package=&lt;slug&gt;</span> and on the user's wallet
                                    page.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

    </main>

</x-layouts.admin>
