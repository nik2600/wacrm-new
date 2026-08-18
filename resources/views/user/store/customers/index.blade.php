<x-layouts.user :title="__('Customers')" nav-key="connect" page="user-store-customers-index">
    @php
        $u = auth()->user();
        $cfg = $u?->current_workspace_id
            ? \App\Models\WaProviderConfig::query()->forWorkspace($u->current_workspace_id)->first()
            : null;
        $sf = $u?->current_workspace_id
            ? \App\Models\WaStorefront::where('workspace_id', $u->current_workspace_id)->first()
            : null;
    @endphp
    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
            @include('user.store._sidebar', ['current' => 'customers', 'cfg' => $cfg, 'sf' => $sf])

            <section class="space-y-5 min-w-0">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">
                        {{ __('Store / Customers') }}</div>
                    <h1 class="font-serif text-[26px] sm:text-[34px] leading-tight tracking-[-0.02em]">{{ __('Saved customers') }}</h1>
                    <p class="text-[13px] text-ink-600 mt-1">
                        {{ __('Pre-set a customer’s delivery address here. When they order on WhatsApp, it shows automatically — they just reply YES, no re-typing.') }}</p>
                </div>

                @if (session('success'))
                    <div class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                        {{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="bg-accent-coral/10 border border-accent-coral/30 rounded-lg px-4 py-2 text-[12.5px] text-accent-coral font-mono">
                        {{ session('error') }}</div>
                @endif

                {{-- Add / edit a customer (one form does both — keyed by phone) --}}
                <form method="POST" action="{{ route('user.store.customers.store') }}"
                      class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-4 sm:p-5">
                    @csrf
                    <input type="hidden" name="phone" id="cust-phone-hidden" value="">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3" id="cust-form-title">
                        {{ __('Add a customer') }}</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="block">
                            <span class="text-[11px] text-ink-600">{{ __('Phone (with country code)') }}</span>
                            <input name="phone" id="cust-phone" type="text" required placeholder="{{ __('Number with country code') }}"
                                   class="mt-1 w-full rounded-lg border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] focus:border-wa-deep focus:ring-0">
                        </label>
                        <label class="block">
                            <span class="text-[11px] text-ink-600">{{ __('Name') }}</span>
                            <input name="name" id="cust-name" type="text" placeholder="{{ __('Customer name') }}"
                                   class="mt-1 w-full rounded-lg border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] focus:border-wa-deep focus:ring-0">
                        </label>
                        <label class="block">
                            <span class="text-[11px] text-ink-600">{{ __('Company (optional)') }}</span>
                            <input name="company" id="cust-company" type="text" placeholder="{{ __('Business name') }}"
                                   class="mt-1 w-full rounded-lg border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] focus:border-wa-deep focus:ring-0">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="text-[11px] text-ink-600">{{ __('Full delivery address') }}</span>
                            <textarea name="address" id="cust-address" rows="2" placeholder="{{ __('Street, city, postcode…') }}"
                                      class="mt-1 w-full rounded-lg border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] focus:border-wa-deep focus:ring-0"></textarea>
                        </label>
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <button type="submit"
                                class="px-4 py-2 rounded-lg bg-wa-deep text-paper-0 text-[12.5px] font-semibold hover:opacity-90">{{ __('Save customer') }}</button>
                        <button type="button" id="cust-reset" class="hidden px-3 py-2 rounded-lg border border-paper-200 text-[12px] text-ink-600">{{ __('Clear') }}</button>
                    </div>
                </form>

                {{-- Search --}}
                <form method="GET">
                    <input name="q" type="search" value="{{ $q }}" placeholder="{{ __('Search name, phone or company…') }}"
                           class="w-full max-w-[420px] rounded-lg border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] focus:border-wa-deep focus:ring-0">
                </form>

                {{-- List --}}
                <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 text-ink-500 font-mono text-[10px] uppercase tracking-wider">
                            <tr>
                                <th class="text-left px-4 py-2.5">{{ __('Customer') }}</th>
                                <th class="text-left px-2 py-2.5">{{ __('Address') }}</th>
                                <th class="px-4 py-2.5 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-100">
                            @forelse ($rows as $c)
                                <tr class="hover:bg-paper-50/60 align-top"
                                    data-phone="{{ $c->phone }}" data-name="{{ $c->name }}"
                                    data-company="{{ $c->company }}" data-address="{{ $c->address }}">
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $c->name ?: '—' }}</div>
                                        @if ($c->company)<div class="text-[11px] text-ink-600">{{ $c->company }}</div>@endif
                                        <div class="text-[10.5px] text-ink-500 font-mono">+{{ $c->phone }}</div>
                                    </td>
                                    <td class="px-2 py-3 text-[11.5px] text-ink-700 whitespace-pre-line leading-relaxed">{{ $c->address ?: '—' }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <button type="button" class="cust-edit text-[11px] text-wa-deep font-semibold hover:underline">{{ __('Edit') }}</button>
                                        <form method="POST" action="{{ route('user.store.customers.destroy', $c->id) }}" class="inline ml-2"
                                              onsubmit="return confirm('{{ __('Remove this customer?') }}')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-[11px] text-accent-coral font-semibold hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-10 text-center text-ink-500">{{ __('No saved customers yet. Add one above.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div>{{ $rows->links() }}</div>
            </section>
        </div>
    </main>

    {{-- Edit prefills the form from the row's data-* (no extra page load). --}}
    <script>
        (function () {
            var f = {
                phone: document.getElementById('cust-phone'),
                name: document.getElementById('cust-name'),
                company: document.getElementById('cust-company'),
                address: document.getElementById('cust-address'),
                title: document.getElementById('cust-form-title'),
                reset: document.getElementById('cust-reset')
            };
            document.querySelectorAll('.cust-edit').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var tr = btn.closest('tr');
                    f.phone.value = tr.dataset.phone || '';
                    f.name.value = tr.dataset.name || '';
                    f.company.value = tr.dataset.company || '';
                    f.address.value = tr.dataset.address || '';
                    f.title.textContent = '{{ __('Edit customer') }}';
                    f.reset.classList.remove('hidden');
                    f.phone.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            });
            if (f.reset) f.reset.addEventListener('click', function () {
                f.phone.value = f.name.value = f.company.value = f.address.value = '';
                f.title.textContent = '{{ __('Add a customer') }}';
                f.reset.classList.add('hidden');
            });
        })();
    </script>
</x-layouts.user>
