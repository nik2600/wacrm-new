<x-layouts.admin :title="__('Currencies')" admin-key="currencies" page="admin-currencies-index">


    <header class="h-16 bg-paper-0 border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Currencies') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex items-end justify-between gap-4 flex-wrap">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · System · Localization') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[40px] leading-[1.0]">{{ __('Platform') }}
                    <span class="italic text-wa-deep">{{ __('currencies') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">
                    {{ __('Manage the currencies workspaces can pick. Exchange rates are stored relative to') }} <span
                        class="font-mono">{{ __('USD') }}</span>. Changing the default propagates to every user
                    dashboard, invoice, and wallet display through <code
                        class="font-mono text-[11px] bg-paper-50 px-1 rounded">FormatSettings::currency()</code>.</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <form method="POST" action="{{ route('admin.currencies.fetch-rates') }}" class="inline">
                    @csrf
                    <button
                        class="px-3.5 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M14 8a6 6 0 1 1-1.76-4.24" />
                            <path d="M14 2v3.5h-3.5" />
                        </svg>
                        Fetch live rates
                    </button>
                </form>
                <button type="button" onclick="document.getElementById('cur-form').classList.toggle('hidden')"
                    class="px-3.5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    Add currency
                </button>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div
                class="rounded-2xl border border-accent-coral/30 bg-accent-coral/10 text-[#A1431F] px-4 py-2 text-[12.5px]">
                {{ session('error') }}</div>
        @endif

        {{-- KPI strip --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['total'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('currencies configured') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['active'] }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('visible to workspaces') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('System default') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1 uppercase">{{ $stats['default'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('fallback for new workspaces') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Inactive') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['inactive'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('hidden from workspace settings') }}</div>
            </div>
        </section>

        {{-- Default currency selector --}}
        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('System default') }}</div>
                    <h2 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Platform-wide currency fallback') }}
                    </h2>
                    <p class="text-[12px] text-ink-600 mt-1">
                        {{ __('Workspaces without an explicit currency choice use this one. Saving here flushes the cache instantly — the next page load anywhere in the app picks it up.') }}
                    </p>
                </div>
                <form method="POST" action="{{ route('admin.currencies.default') }}"
                    class="flex items-center gap-2 shrink-0 flex-wrap">
                    @csrf
                    <select name="code"
                        class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        @foreach ($allActive as $c)
                            <option value="{{ $c->code }}" @selected($c->code === $defaultCode)>{{ $c->code }} —
                                {{ $c->name }} {{ $c->symbol ? '(' . $c->symbol . ')' : '' }}</option>
                        @endforeach
                    </select>
                    <button
                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Set default') }}</button>
                </form>
            </div>
        </section>

        {{-- Add-currency form (hidden by default) --}}
        <form id="cur-form" method="POST" action="{{ route('admin.currencies.store') }}"
            class="hidden bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('New entry') }}
                    </div>
                    <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Add a currency') }}</h3>
                </div>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <label class="block">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Name *') }}</span>
                    <input type="text" name="name" required maxlength="120"
                        placeholder="{{ __('Indian Rupee') }}"
                        class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                </label>
                <label class="block">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('ISO code *') }}</span>
                    <input type="text" name="code" required maxlength="10" placeholder="{{ __('INR') }}"
                        class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono uppercase focus:outline-none focus:border-wa-deep">
                </label>
                <label class="block">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Symbol') }}</span>
                    <input type="text" name="symbol" maxlength="20" placeholder="₹"
                        class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                </label>
                <label class="block">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Decimals *') }}</span>
                    <input type="number" name="precision" required min="0" max="6" value="2"
                        class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep">
                </label>
                <label class="block sm:col-span-2">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Exchange rate (1 USD = ?) *') }}</span>
                    <input type="number" name="exchange_rate" required step="0.000001" min="0.000001"
                        value="1"
                        class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep">
                    <span
                        class="block text-[10.5px] text-ink-500 mt-1">{{ __('Hit "Fetch live rates" above to auto-fill from open.er-api.com.') }}</span>
                </label>
                <label class="sm:col-span-2 lg:col-span-3 flex items-center gap-2 text-[12.5px]">
                    <input type="checkbox" name="is_active" value="1" checked
                        class="w-4 h-4 rounded border-paper-200 accent-wa-deep">
                    Active immediately
                </label>
            </div>
            <div class="px-5 py-4 border-t border-paper-200 bg-paper-50/40 flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('cur-form').classList.add('hidden')"
                    class="px-4 py-2 rounded-full border border-paper-200 text-[12px] hover:bg-paper-50">{{ __('Cancel') }}</button>
                <button type="submit"
                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Add currency') }}</button>
            </div>
        </form>

        {{-- Search + currency list --}}
        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Catalog') }}
                    </div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('All currencies') }}</h2>
                </div>
                <form method="GET" action="{{ route('admin.currencies.index') }}" class="relative w-full sm:w-[280px]">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                        fill="none" stroke="currentColor" stroke-width="1.6">
                        <circle cx="7" cy="7" r="5" />
                        <path d="m11 11 3 3" />
                    </svg>
                    <input type="search" name="q" value="{{ $q }}"
                        placeholder="{{ __('Code, name, or symbol…') }}"
                        class="w-full pl-9 pr-3 py-1.5 border border-paper-200 rounded-full bg-paper-50 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                </form>
            </div>

            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px]">
                <thead class="bg-paper-50 text-left font-mono text-[10.5px] uppercase text-ink-500">
                    <tr>
                        <th class="px-4 py-2.5 w-[110px]">{{ __('Code') }}</th>
                        <th class="px-4 py-2.5">{{ __('Name') }}</th>
                        <th class="px-4 py-2.5 w-[70px]">{{ __('Symbol') }}</th>
                        <th class="px-4 py-2.5 w-[150px] text-right">{{ __('Rate (1 USD →)') }}</th>
                        <th class="px-4 py-2.5 w-[80px] text-center">{{ __('Decimals') }}</th>
                        <th class="px-4 py-2.5 w-[100px] text-center">{{ __('Active') }}</th>
                        <th class="px-4 py-2.5 w-[100px] text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-100">
                    @forelse ($currencies as $c)
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-2.5 font-mono font-semibold">
                                {{ $c->code }}
                                @if ($c->code === $defaultCode)
                                    <span
                                        class="ml-1 text-[9px] font-mono uppercase px-1.5 py-0.5 rounded bg-wa-mint text-wa-deep border border-wa-green/40">{{ __('Default') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">{{ $c->name }}</td>
                            <td class="px-4 py-2.5">{{ $c->symbol }}</td>
                            <td class="px-4 py-2.5 text-right font-mono">{{ number_format($c->exchange_rate, 4) }}
                            </td>
                            <td class="px-4 py-2.5 text-center font-mono">{{ $c->precision }}</td>
                            <td class="px-4 py-2.5 text-center">
                                <form method="POST" action="{{ route('admin.currencies.toggle', $c->id) }}"
                                    class="inline">@csrf
                                    <button
                                        class="px-2.5 py-1 rounded-full text-[10.5px] font-mono {{ $c->is_active ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-100 text-ink-600 border border-paper-200' }}">
                                        {{ $c->is_active ? 'On' : 'Off' }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                @if ($c->code !== $defaultCode)
                                    <form method="POST" action="{{ route('admin.currencies.destroy', $c->id) }}"
                                        class="inline"
                                        data-confirm="Delete {{ $c->code }}? Any prices saved in this currency will become orphaned."
                                        data-confirm-title="{{ __('Delete currency') }}"
                                        data-confirm-text="Yes, delete" data-danger="1">
                                        @csrf @method('DELETE')
                                        <button
                                            class="px-2.5 py-1 rounded-full border border-accent-coral/40 text-accent-coral text-[10.5px] font-mono hover:bg-accent-coral/10">{{ __('Delete') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-ink-500 text-[13px]">
                                @if ($q)
                                    No matches for "{{ $q }}".
                                @else
                                    No currencies yet.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            {{-- Pagination footer --}}
            <div class="px-4 py-3 border-t border-paper-200 bg-paper-50/40">
                {{ $currencies->links() }}
            </div>
        </section>

    </main>

</x-layouts.admin>
