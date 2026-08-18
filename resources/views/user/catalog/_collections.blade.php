{{-- Collections tab — reusable saved product groups (sets), fired as MPM. --}}
<div class="space-y-5" data-collections data-send-url="{{ route('user.catalog.send-to-number') }}">

    {{-- Intro --}}
    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card flex items-start gap-3 flex-wrap">
        <div class="w-9 h-9 rounded-xl bg-wa-mint text-wa-deep grid place-items-center shrink-0">
            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6">
                <rect x="2" y="2" width="5" height="5" rx="1" />
                <rect x="9" y="2" width="5" height="5" rx="1" />
                <rect x="2" y="9" width="5" height="5" rx="1" />
                <rect x="9" y="9" width="5" height="5" rx="1" />
            </svg>
        </div>
        <div class="flex-1 min-w-[200px]">
            <div class="font-serif text-[17px] leading-tight">{{ __('Collections') }}</div>
            <div class="text-[12px] text-ink-500 mt-0.5">
                {{ __('Save a group of products once, then send it as a Multi-Product Message in one click — no re-picking each time.') }}
            </div>
        </div>
    </div>

    {{-- Create --}}
    <details class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card group" data-create>
        <summary class="cursor-pointer list-none px-5 py-4 flex items-center justify-between gap-3">
            <span class="font-serif text-[16px] inline-flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none" stroke="currentColor"
                    stroke-width="1.8">
                    <path d="M8 3v10M3 8h10" />
                </svg>
                {{ __('New collection') }}
            </span>
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500 transition-transform group-open:rotate-90"
                fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="m6 4 4 4-4 4" />
            </svg>
        </summary>
        <form method="POST" action="{{ route('user.catalog.sets.store') }}"
            class="px-5 pb-5 border-t border-paper-200 pt-4 space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="block">
                    <span
                        class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Name') }}</span>
                    <input type="text" name="name" required maxlength="120"
                        placeholder="{{ __('e.g. Summer Sale') }}"
                        class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep">
                </label>
                <label class="block">
                    <span
                        class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Description (optional)') }}</span>
                    <input type="text" name="description" maxlength="500"
                        class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep">
                </label>
            </div>
            <div>
                <span
                    class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500 block mb-1.5">{{ __('Products') }}
                    <span class="text-ink-400 normal-case tracking-normal">({{ __('up to 30') }})</span></span>
                @include('user.catalog._product-picker', [
                    'pickProducts' => $pickProducts,
                    'selected' => [],
                    'fieldName' => 'product_ids[]',
                ])
            </div>
            <div class="flex justify-end">
                <button
                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">{{ __('Create collection') }}</button>
            </div>
        </form>
    </details>

    {{-- List --}}
    @forelse ($sets as $set)
        @php $members = $set->products(); @endphp
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 flex items-center gap-4 flex-wrap">
                <div class="flex -space-x-2 shrink-0">
                    @foreach ($members->take(4) as $m)
                        <span
                            class="w-9 h-9 rounded-lg border-2 border-paper-0 bg-paper-50 overflow-hidden grid place-items-center">
                            @if ($m->image_url)
                                <img src="{{ $m->image_url }}" class="w-full h-full object-cover" loading="lazy">
                            @else<svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-400" fill="none"
                                    stroke="currentColor" stroke-width="1.4">
                                    <path d="M2 5l6-3 6 3v6l-6 3-6-3z" />
                                    <path d="M2 5l6 3 6-3M8 8v6" />
                                </svg>
                            @endif
                        </span>
                    @endforeach
                </div>
                <div class="flex-1 min-w-[160px]">
                    <div class="font-serif text-[16px] leading-tight">{{ $set->name }}</div>
                    <div class="text-[11.5px] text-ink-500 mt-0.5">
                        {{ trans_choice('{0}empty|{1}:count product|[2,*]:count products', $set->product_count, ['count' => $set->product_count]) }}
                        @if ($set->description)
                            · {{ \Illuminate\Support\Str::limit($set->description, 60) }}
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" data-send-toggle
                        class="px-3 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[11.5px] font-semibold inline-flex items-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M2 8l12-5-5 12-2.5-4.5z" />
                        </svg>
                        {{ __('Send') }}
                    </button>
                    <button type="button" data-edit-toggle
                        class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-medium">{{ __('Edit') }}</button>
                    <form method="POST" action="{{ route('user.catalog.sets.destroy', $set->id) }}"
                        onsubmit="return confirm('{{ __('Delete this collection? Products are not affected.') }}')">
                        @csrf @method('DELETE')
                        <button
                            class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-accent-coral/10 hover:border-accent-coral/40 text-accent-coral text-[11.5px] font-medium">{{ __('Delete') }}</button>
                    </form>
                </div>
            </div>

            {{-- Send panel --}}
            <div data-send-panel class="hidden px-5 pb-5 border-t border-paper-200 pt-4"
                data-product-ids='@json($members->pluck('id')->values())'>
                @if ($members->isEmpty())
                    <div class="text-[12px] text-accent-coral">
                        {{ __('This collection has no live products to send.') }}</div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="block md:col-span-1">
                            <span
                                class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Recipient number') }}</span>
                            <input type="text" data-send-number placeholder="{{ __('Number with country code') }}"
                                class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep">
                        </label>
                        <label class="block">
                            <span
                                class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Header (optional)') }}</span>
                            <input type="text" data-send-header maxlength="60"
                                placeholder="{{ __('Check these out') }}"
                                class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep">
                        </label>
                        <label class="block">
                            <span
                                class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Send from') }}</span>
                            <x-sender-picker :senders="$senders" name="sender" data-send-device
                                :placeholder="__('Auto (active)')"
                                class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep bg-paper-0" />
                        </label>
                    </div>
                    <div class="flex items-center justify-between gap-3 mt-3">
                        <div class="text-[11px] text-ink-500" data-send-status></div>
                        <button type="button" data-send-go
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">{{ __('Send as MPM') }}</button>
                    </div>
                @endif
            </div>

            {{-- Edit panel --}}
            <div data-edit-panel class="hidden px-5 pb-5 border-t border-paper-200 pt-4">
                <form method="POST" action="{{ route('user.catalog.sets.update', $set->id) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="block">
                            <span
                                class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Name') }}</span>
                            <input type="text" name="name" required maxlength="120"
                                value="{{ $set->name }}"
                                class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep">
                        </label>
                        <label class="block">
                            <span
                                class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Description (optional)') }}</span>
                            <input type="text" name="description" maxlength="500"
                                value="{{ $set->description }}"
                                class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep">
                        </label>
                    </div>
                    <div>
                        <span
                            class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500 block mb-1.5">{{ __('Products') }}</span>
                        @include('user.catalog._product-picker', [
                            'pickProducts' => $pickProducts,
                            'selected' => $set->product_ids ?? [],
                            'fieldName' => 'product_ids[]',
                        ])
                    </div>
                    <div class="flex justify-end">
                        <button
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">{{ __('Save changes') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="bg-paper-0 border border-dashed border-paper-200 rounded-2xl p-8 text-center text-ink-500">
            <div class="text-[13px]">{{ __('No collections yet. Create your first one above.') }}</div>
        </div>
    @endforelse
</div>

@push('scripts')
    <script>
        (function() {
            const root = document.querySelector('[data-collections]');
            if (!root) return;
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
            const sendUrl = root.dataset.sendUrl;

            // Picker: live filter + selection counter (works for create + every edit picker)
            root.querySelectorAll('[data-picker]').forEach(function(picker) {
                const search = picker.querySelector('[data-picker-search]');
                const count = picker.querySelector('[data-picker-count]');
                const cards = picker.querySelectorAll('[data-pick-card]');

                function refreshCount() {
                    const n = picker.querySelectorAll('input[type=checkbox]:checked').length;
                    if (count) count.textContent = n;
                }
                search?.addEventListener('input', function() {
                    const q = this.value.trim().toLowerCase();
                    cards.forEach(function(c) {
                        c.style.display = (!q || c.dataset.name.includes(q)) ? '' : 'none';
                    });
                });
                picker.addEventListener('change', function(e) {
                    if (e.target.type !== 'checkbox') return;
                    if (picker.querySelectorAll('input[type=checkbox]:checked').length > 30) {
                        e.target.checked = false;
                    }
                    refreshCount();
                });
                refreshCount();
            });

            // Per-card toggles (send / edit panels)
            root.querySelectorAll('[data-send-toggle]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const card = btn.closest('.shadow-card');
                    card.querySelector('[data-edit-panel]')?.classList.add('hidden');
                    card.querySelector('[data-send-panel]')?.classList.toggle('hidden');
                });
            });
            root.querySelectorAll('[data-edit-toggle]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const card = btn.closest('.shadow-card');
                    card.querySelector('[data-send-panel]')?.classList.add('hidden');
                    card.querySelector('[data-edit-panel]')?.classList.toggle('hidden');
                });
            });

            // Send a collection as an MPM via the existing send-to-number endpoint.
            root.querySelectorAll('[data-send-go]').forEach(function(btn) {
                btn.addEventListener('click', async function() {
                    const panel = btn.closest('[data-send-panel]');
                    const status = panel.querySelector('[data-send-status]');
                    const number = (panel.querySelector('[data-send-number]')?.value || '').trim();
                    const header = (panel.querySelector('[data-send-header]')?.value || '').trim();
                    const device = panel.querySelector('[data-send-device]')?.value || '';
                    let ids = [];
                    try {
                        ids = JSON.parse(panel.dataset.productIds || '[]');
                    } catch (e) {
                        ids = [];
                    }

                    if (!number) {
                        status.textContent = '{{ __('Enter a recipient number first.') }}';
                        status.className = 'text-[11px] text-accent-coral';
                        return;
                    }
                    if (!ids.length) {
                        status.textContent = '{{ __('This collection has no products.') }}';
                        status.className = 'text-[11px] text-accent-coral';
                        return;
                    }

                    btn.disabled = true;
                    status.className = 'text-[11px] text-ink-500';
                    status.textContent = '{{ __('Sending…') }}';

                    const fd = new FormData();
                    fd.append('manual_numbers', number);
                    fd.append('mode', 'mpm');
                    if (header) fd.append('header', header);
                    if (device) fd.append('sender', device);
                    ids.forEach(function(id) {
                        fd.append('product_ids[]', id);
                    });

                    try {
                        const r = await fetch(sendUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                Accept: 'application/json'
                            },
                            credentials: 'same-origin',
                            body: fd
                        });
                        const j = await r.json().catch(function() {
                            return {};
                        });
                        if (r.ok && j.ok !== false) {
                            status.className = 'text-[11px] text-wa-deep';
                            status.textContent = (j.message || '{{ __('Sent.') }}');
                        } else {
                            status.className = 'text-[11px] text-accent-coral';
                            status.textContent = (j.message || j.error || ('HTTP ' + r.status));
                        }
                    } catch (e) {
                        status.className = 'text-[11px] text-accent-coral';
                        status.textContent = '{{ __('Network error.') }}';
                    } finally {
                        btn.disabled = false;
                    }
                });
            });
        })();
    </script>
@endpush
