@php
    $rows = $rows ?? collect();
    $counts = $counts ?? ['all' => 0, 'active' => 0, 'inactive' => 0];
    $currentStatus = $currentStatus ?? 'all';
    $currentSearch = $currentSearch ?? '';
@endphp

<x-layouts.user :title="__('Attributes')" nav-key="more" page="user-attributes-index">

    @if (session('status') || $errors->any())
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    @if (session('status'))
                        window.WaToaster?.success(@json(session('status')));
                    @endif
                    @foreach ($errors->all() as $err)
                        window.WaToaster?.error(@json($err));
                    @endforeach
                });
            </script>
        @endpush
    @endif

    <main class="max-w-none mx-auto px-4 sm:px-7 py-6 sm:py-7" data-attr-state data-attr-status="{{ $currentStatus }}"
        data-attr-search="{{ $currentSearch }}"
        data-attr-page="{{ method_exists($rows, 'currentPage') ? $rows->currentPage() : 1 }}">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6 items-start">

            <!-- ===== SIDEBAR ===== -->
            {{-- self-start + sticky: the left panel floats/stays in view while the
 attributes list on the right scrolls (matches the meta-ads layout).
 items-start on the grid stops the column from stretching full-height,
 which is what lets sticky engage. --}}
            <aside class="space-y-3 self-start lg:sticky lg:top-[84px]">
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Status') }}</div>
                    @foreach ([
        'all' => ['label' => 'All', 'dot' => null],
        'active' => ['label' => 'Active', 'dot' => 'bg-wa-green'],
        'inactive' => ['label' => 'Inactive', 'dot' => 'bg-paper-200'],
    ] as $key => $row)
                        @php $active = $currentStatus === $key; @endphp
                        <button type="button" data-attr-filter="status" data-attr-value="{{ $key }}"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $active ? 'bg-wa-deep text-paper-0 font-semibold' : 'text-ink-700 hover:bg-paper-50' }}">
                            <span class="flex items-center gap-2">
                                @if ($row['dot'])
                                    <span class="w-2 h-2 rounded-full {{ $row['dot'] }}"></span>
                                @endif
                                {{ $row['label'] }}
                            </span>
                            <span data-attr-status-count="{{ $key }}"
                                class="font-mono text-[11px] {{ $active ? 'opacity-90' : 'text-ink-500' }}">{{ number_format($counts[$key] ?? 0) }}</span>
                        </button>
                    @endforeach
                </div>

                <div
                    class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M8 2v3M8 11v3M2 8h3M11 8h3M4 4l2 2M10 10l2 2M4 12l2-2M10 6l2-2" />
                        </svg>
                        {{ __('How attributes work') }}
                    </div>
                    Type <span class="font-mono px-1 rounded bg-paper-0">/</span> in any message body to open the
                    picker. Picking an attribute inserts the next positional placeholder (<span
                        class="font-mono">@{{ 1 }}</span>, <span
                        class="font-mono">@{{ 2 }}</span>...) so the message stays
                    Meta-template-compliant.
                </div>

                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('Quick reference') }}</div>
                    <div class="space-y-1.5 text-[11.5px]">
                        <div class="flex items-start gap-2">
                            <span class="font-mono px-1.5 py-0.5 rounded bg-wa-bubble text-wa-deep shrink-0">/</span>
                            <span class="text-ink-700">{{ __('Open the attribute picker') }}</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span
                                class="font-mono px-1.5 py-0.5 rounded bg-paper-50 text-ink-700 shrink-0">{{ __('Enter') }}</span>
                            <span class="text-ink-700">{{ __('Insert highlighted attribute') }}</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span
                                class="font-mono px-1.5 py-0.5 rounded bg-paper-50 text-ink-700 shrink-0">{{ __('Esc') }}</span>
                            <span class="text-ink-700">{{ __('Close picker without inserting') }}</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span
                                class="font-mono px-1.5 py-0.5 rounded bg-wa-deep/10 text-wa-deep shrink-0">@{{ 1 }}</span>
                            <span class="text-ink-700">{{ __('Inserted as positional, not name') }}</span>
                        </div>
                    </div>
                </div>

                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('Where to use') }}</div>
                    <div class="space-y-1">
                        <a href="{{ url('/templates/create') }}"
                            class="flex items-center justify-between px-2 py-1.5 rounded-lg hover:bg-paper-50 text-[12px]">
                            <span class="text-ink-700">{{ __('Templates') }}</span>
                            <svg viewBox="0 0 16 16" class="w-3 h-3 text-ink-500" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M6 4l4 4-4 4" />
                            </svg>
                        </a>
                        <a href="{{ url('/broadcasts/create') }}"
                            class="flex items-center justify-between px-2 py-1.5 rounded-lg hover:bg-paper-50 text-[12px]">
                            <span class="text-ink-700">{{ __('Broadcasts') }}</span>
                            <svg viewBox="0 0 16 16" class="w-3 h-3 text-ink-500" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M6 4l4 4-4 4" />
                            </svg>
                        </a>
                        <a href="{{ url('/wa-campaigns/create') }}"
                            class="flex items-center justify-between px-2 py-1.5 rounded-lg hover:bg-paper-50 text-[12px]">
                            <span class="text-ink-700">{{ __('Campaigns') }}</span>
                            <svg viewBox="0 0 16 16" class="w-3 h-3 text-ink-500" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M6 4l4 4-4 4" />
                            </svg>
                        </a>
                        <a href="{{ url('/flows') }}"
                            class="flex items-center justify-between px-2 py-1.5 rounded-lg hover:bg-paper-50 text-[12px]">
                            <span class="text-ink-700">{{ __('Flow builder') }}</span>
                            <svg viewBox="0 0 16 16" class="w-3 h-3 text-ink-500" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M6 4l4 4-4 4" />
                            </svg>
                        </a>
                    </div>
                </div>
            </aside>

            <!-- ===== MAIN ===== -->
            <section class="space-y-5 min-w-0">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            {{ __('More / Attributes') }}</div>
                        <h1 class="font-serif font-normal tracking-tight text-3xl sm:text-[44px] leading-none">{{ __('Template') }}
                            <span class="italic text-wa-deep">{{ __('attributes') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2">
                            {{ __('Variables you can drop into any message body. Meta requires positional placeholders like') }}
                            <span class="font-mono">@{{ 1 }}</span> @{{ 2 }} / not <span
                                class="font-mono">@{{ name }}</span>.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" data-attr-modal-open
                            class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                            New attribute
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Total') }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1" data-attr-stat="all">
                            {{ number_format($counts['all']) }}</div>
                        <div class="text-[11px] text-wa-deep mt-2">{{ __('attributes') }}</div>
                    </div>
                    <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Active') }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1" data-attr-stat="active">
                            {{ number_format($counts['active']) }}</div>
                        <div class="text-[11px] text-wa-deep mt-2">{{ __('show in slash picker') }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Inactive') }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1" data-attr-stat="inactive">
                            {{ number_format($counts['inactive']) }}</div>
                        <div class="text-[11px] text-ink-500 mt-2">{{ __('hidden from picker') }}</div>
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card" data-list-grid
                    data-list-grid-key="attributes">
                    <div class="px-4 py-3 border-b border-paper-200 flex flex-wrap items-center justify-between gap-3">
                        <div id="attr-results-footer"
                            class="text-[12.5px] text-ink-700 {{ (method_exists($rows, 'total') ? $rows->total() : $counts['all'] ?? 0) > 0 ? '' : 'hidden' }}">
                            Showing <b><span data-attr-shown>{{ $rows->count() }}</span></b> of <b
                                data-attr-total>{{ method_exists($rows, 'total') ? number_format($rows->total()) : number_format($counts['all']) }}</b>
                            filtered</div>
                        <div class="flex items-center gap-2 flex-1 justify-end">
                            <div class="relative max-w-[320px] flex-1">
                                <svg viewBox="0 0 16 16"
                                    class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                                    fill="none" stroke="currentColor" stroke-width="1.5">
                                    <circle cx="7" cy="7" r="5" />
                                    <path d="m11 11 3 3" />
                                </svg>
                                <input id="attr-search" type="search" value="{{ $currentSearch }}"
                                    placeholder="{{ __('Search by name, key, description...') }}"
                                    class="w-full pl-9 pr-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                            <x-list-grid-toggle />
                        </div>
                    </div>
                    <div class="overflow-x-auto" data-list-grid-list>
                        <table class="w-full text-[12.5px]" data-list-grid-source>
                            <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                                <tr>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                        {{ __('Name') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                        {{ __('Key') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                        {{ __('Default') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                        {{ __('Description') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                        {{ __('Status') }}</th>
                                    <th
                                        class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-[80px]">
                                        {{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody id="attr-rows" class="divide-y divide-paper-200">
                                @include('user.attributes._rows', ['rows' => $rows])
                            </tbody>
                        </table>
                    </div>
                    <div class="hidden p-4" data-list-grid-grid></div>
                </div>
                <div id="attr-pagination">
                    @include('user.partials.pagination', [
                        'paginator' => $rows,
                        'dataAttr' => 'data-attr-page',
                        'label' => 'attributes',
                    ])
                </div>

                <!-- Examples + best-practice cards to fill the page below the table -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                        <div class="w-9 h-9 rounded-xl bg-wa-mint text-wa-deep grid place-items-center mb-3">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <circle cx="8" cy="8" r="6" />
                                <path d="M5.5 6a2.5 2.5 0 1 1 5 0c0 2-2.5 2-2.5 4" />
                            </svg>
                        </div>
                        <h3 class="font-serif text-[16px] mb-1">{{ __('When to add one') }}</h3>
                        <p class="text-[12px] text-ink-600 leading-relaxed">
                            {{ __("If a value isn't already on the contact (like an order ID, coupon code, or appointment time), add it as an attribute so templates can fill it in.") }}
                        </p>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                        <div class="w-9 h-9 rounded-xl bg-[#FFF4E0] text-[#7B5A14] grid place-items-center mb-3">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M8 1.5L1.5 13.5h13zM8 6v3M8 11.5h.01" />
                            </svg>
                        </div>
                        <h3 class="font-serif text-[16px] mb-1">{{ __('Meta rules') }}</h3>
                        <p class="text-[12px] text-ink-600 leading-relaxed">
                            {{ __("Templates only accept positional placeholders. Don't put attribute keys directly into a template body / use the slash picker so it inserts") }}
                            <span class="font-mono">@{{ N }}</span>.</p>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                        <div class="w-9 h-9 rounded-xl bg-[#F3E9FF] text-[#5B3D8A] grid place-items-center mb-3">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M3 4h10M3 8h10M3 12h6" />
                            </svg>
                        </div>
                        <h3 class="font-serif text-[16px] mb-1">{{ __('Naming tips') }}</h3>
                        <p class="text-[12px] text-ink-600 leading-relaxed">
                            {{ __('Use snake_case for keys. Keep names short and consistent:') }} <span
                                class="font-mono">{{ __('order_id') }}</span>, <span
                                class="font-mono">{{ __('tracking_url') }}</span>, <span
                                class="font-mono">{{ __('discount_code') }}</span>, <span
                                class="font-mono">{{ __('appointment_at') }}</span>.</p>
                    </div>
                </div>

                <div class="bg-wa-deep rounded-[14px] p-5 shadow-soft text-paper-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/60 mb-1">
                        {{ __('Tip') }}</div>
                    <div class="font-serif text-[20px] leading-tight">{{ __('Default values save you') }}</div>
                    <p class="mt-2 text-[12px] text-paper-0/85 leading-relaxed">
                        {{ __('Set a default for every attribute. If a contact is missing that value at send time, the default is used instead of the message failing or showing a blank slot.') }}
                    </p>
                </div>
            </section>
        </div>
    </main>

    <!-- ===== EDIT ATTRIBUTE MODAL ===== -->
    <div id="attr-edit-modal"
        class="hidden fixed inset-0 z-50 items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]">
        <div
            class="w-full max-w-[560px] bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Edit attribute') }}</div>
                    <h3 class="font-serif text-[22px] leading-tight tracking-[-0.01em]" data-attr-edit-title>
                        {{ __('Edit') }}</h3>
                    <div class="mt-0.5 text-[12px] text-ink-500">
                        {{ __("The key can't be changed once created (it's already in use in your templates).") }}
                    </div>
                </div>
                <button type="button" data-attr-edit-close
                    class="w-8 h-8 rounded-full border border-paper-200 bg-white hover:bg-paper-50 grid place-items-center shrink-0"
                    title="{{ __('Close') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>
                </button>
            </div>

            <form id="attr-edit-form" method="POST" action="" class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                @csrf
                @method('PUT')
                <div class="col-span-2">
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Display name') }} <span
                            class="text-accent-coral">*</span></label>
                    <input required type="text" name="attribute_name" data-attr-edit-field="attribute_name"
                        maxlength="120"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Key') }}</label>
                    <input type="text" data-attr-edit-field="attribute_key" disabled
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-50 text-[13px] font-mono text-ink-500 focus:outline-none" />
                    <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Read-only.') }}</div>
                </div>
                <div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Default value') }}</label>
                    <input type="text" name="attribute_value" data-attr-edit-field="attribute_value"
                        maxlength="255"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                </div>
                <div class="col-span-2">
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Description') }}</label>
                    <input type="text" name="description" data-attr-edit-field="description" maxlength="500"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                </div>
                <label class="col-span-2 flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="status" value="1" data-attr-edit-field="status"
                        class="w-4 h-4 accent-wa-deep" />
                    <span class="text-[12.5px] text-ink-700">{{ __('Active / show in the slash-popover') }}</span>
                </label>
                <div class="col-span-2 flex justify-end gap-2 pt-3 mt-1 border-t border-paper-200">
                    <button type="button" data-attr-edit-close
                        class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</button>
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save changes') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== NEW ATTRIBUTE MODAL ===== -->
    <div id="attr-modal" class="hidden fixed inset-0 z-50 items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]">
        <div
            class="w-full max-w-[560px] bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('New attribute') }}</div>
                    <h3 class="font-serif text-[22px] leading-tight tracking-[-0.01em]">{{ __('Create attribute') }}
                    </h3>
                    <div class="mt-0.5 text-[12px] text-ink-500">{{ __('Add a variable like') }} <span
                            class="font-mono">{{ __('order_id') }}</span>, <span
                            class="font-mono">{{ __('tracking_url') }}</span>, or <span
                            class="font-mono">{{ __('discount_code') }}</span>.</div>
                </div>
                <button type="button" data-attr-modal-close
                    class="w-8 h-8 rounded-full border border-paper-200 bg-white hover:bg-paper-50 grid place-items-center shrink-0"
                    title="{{ __('Close') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('user.attributes.store') }}" class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                @csrf
                <div class="col-span-2">
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Display name') }} <span
                            class="text-accent-coral">*</span></label>
                    <input required type="text" name="attribute_name" value="{{ old('attribute_name') }}"
                        maxlength="120" placeholder="{{ __('e.g. Order ID') }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Key') }} <span
                            class="text-accent-coral">*</span></label>
                    <input required type="text" name="attribute_key" value="{{ old('attribute_key') }}"
                        maxlength="64" placeholder="{{ __('order_id') }}" pattern="[a-z0-9_\-]+"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    <div class="text-[10.5px] text-ink-500 mt-1">{{ __('a-z, 0-9, dash, underscore. Unique.') }}</div>
                </div>
                <div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Default value') }}</label>
                    <input type="text" name="attribute_value" value="{{ old('attribute_value') }}"
                        maxlength="255" placeholder="(optional fallback)"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                </div>
                <div class="col-span-2">
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Description') }}</label>
                    <input type="text" name="description" value="{{ old('description') }}" maxlength="500"
                        placeholder="{{ __('What does this attribute mean?') }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                </div>
                <label class="col-span-2 flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="status" value="1" checked class="w-4 h-4 accent-wa-deep" />
                    <span
                        class="text-[12.5px] text-ink-700">{{ __('Active / show in the slash-popover when composing') }}</span>
                </label>
                <div class="col-span-2 flex justify-end gap-2 pt-3 mt-1 border-t border-paper-200">
                    <button type="button" data-attr-modal-close
                        class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</button>
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save attribute') }}</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            const $ = (id) => document.getElementById(id);
            const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
            const debounce = (fn, ms) => {
                let t;
                return (...a) => {
                    clearTimeout(t);
                    t = setTimeout(() => fn(...a), ms);
                };
            };

            function readState() {
                const el = document.querySelector('[data-attr-state]');
                return {
                    status: el?.dataset.attrStatus || 'all',
                    q: el?.dataset.attrSearch || '',
                    page: el?.dataset.attrPage || '1'
                };
            }

            function writeState(s) {
                const el = document.querySelector('[data-attr-state]');
                if (!el) return;
                el.dataset.attrStatus = s.status;
                el.dataset.attrSearch = s.q;
                el.dataset.attrPage = s.page || '1';
            }

            function paint(state) {
                document.querySelectorAll('[data-attr-filter="status"]').forEach((b) => {
                    const active = b.dataset.attrValue === state.status;
                    b.classList.toggle('bg-wa-deep', active);
                    b.classList.toggle('text-paper-0', active);
                    b.classList.toggle('font-semibold', active);
                    b.classList.toggle('text-ink-700', !active);
                    b.classList.toggle('hover:bg-paper-50', !active);
                });
            }
            async function refresh(state, {
                silent = false
            } = {}) {
                const params = new URLSearchParams();
                if (state.status !== 'all') params.append('status', state.status);
                if (state.q) params.append('q', state.q);
                if (Number(state.page || 1) > 1) params.append('page', state.page);
                history.pushState({}, '', '/attributes' + (params.toString() ? '?' + params.toString() : ''));
                params.append('partial', '1');
                try {
                    const res = await fetch('/attributes?' + params.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const data = await res.json();
                    $('attr-rows').innerHTML = data.rows;
                    Object.entries(data.counts || {}).forEach(([k, v]) => {
                        document.querySelectorAll(`[data-attr-status-count="${k}"]`).forEach((el) => el
                            .textContent = Number(v).toLocaleString());
                        document.querySelectorAll(`[data-attr-stat="${k}"]`).forEach((el) => el
                            .textContent = Number(v).toLocaleString());
                    });
                    const resultTotal = Number(data.total ?? 0);
                    document.getElementById('attr-results-footer')?.classList.toggle('hidden', resultTotal <= 0);
                    document.querySelector('[data-attr-shown]').textContent = data.shown;
                    const total = document.querySelector('[data-attr-total]');
                    if (total) total.textContent = resultTotal.toLocaleString();
                    const pager = $('attr-pagination');
                    if (pager) pager.innerHTML = data.pagination || '';
                    if (data.page) {
                        state.page = String(data.page);
                        writeState(state);
                    }
                    wireDelete();
                    wireEdit();
                    wirePagination();
                    if (!silent) window.WaToaster?.info?.('Refreshed', {
                        duration: 1100
                    });
                } catch (e) {
                    window.WaToaster?.error?.('Refresh failed: ' + e.message);
                }
            }
            const debouncedRefresh = debounce((s) => refresh(s, {
                silent: true
            }), 220);

            function wirePagination() {
                document.querySelectorAll('a[data-attr-page]').forEach((link) => {
                    if (link.__wired) return;
                    link.__wired = true;
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        const state = readState();
                        state.page = link.dataset.attrPage || '1';
                        writeState(state);
                        refresh(state, {
                            silent: true
                        });
                    });
                });
            }

            function wireDelete() {
                document.querySelectorAll('[data-attr-delete]').forEach((b) => {
                    if (b.__wired) return;
                    b.__wired = true;
                    b.addEventListener('click', () => {
                        const id = b.dataset.attrDelete;
                        const name = b.dataset.name || 'this attribute';
                        const run = async () => {
                            try {
                                const res = await fetch('/attributes/' + id, {
                                    method: 'DELETE',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': csrf()
                                    },
                                });
                                if (!res.ok) throw new Error('HTTP ' + res.status);
                                window.WaToaster?.success?.('Attribute deleted');
                                refresh(readState(), {
                                    silent: true
                                });
                            } catch (e) {
                                window.WaToaster?.error?.('Delete failed: ' + e.message);
                            }
                        };
                        if (typeof window.confirmDialog === 'function') {
                            window.confirmDialog({
                                title: 'Delete attribute?',
                                message: `Delete "${name}"?`,
                                confirmText: 'Delete',
                                tone: 'danger',
                                onConfirm: run
                            });
                        } else if (window.confirm(`Delete "${name}"?`)) {
                            run();
                        }
                    });
                });
            }

            document.querySelectorAll('[data-attr-filter="status"]').forEach((b) => {
                b.addEventListener('click', (e) => {
                    e.preventDefault();
                    const state = readState();
                    state.status = b.dataset.attrValue;
                    state.page = '1';
                    writeState(state);
                    paint(state);
                    refresh(state, {
                        silent: true
                    });
                });
            });
            $('attr-search')?.addEventListener('input', (e) => {
                const state = readState();
                state.q = e.target.value.trim();
                state.page = '1';
                writeState(state);
                debouncedRefresh(state);
            });

            // Create modal open/close
            const modal = $('attr-modal');

            function openModal() {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                modal.querySelector('input[name=attribute_name]')?.focus();
            }

            function closeModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
            document.querySelectorAll('[data-attr-modal-open]').forEach((b) => b.addEventListener('click', openModal));
            document.querySelectorAll('[data-attr-modal-close]').forEach((b) => b.addEventListener('click',
            closeModal));
            modal?.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
            });
            @if ($errors->any())
                openModal();
            @endif

            // Edit modal
            const editModal = $('attr-edit-modal');
            const editForm = $('attr-edit-form');

            function openEdit(payload) {
                editForm.action = '/attributes/' + payload.id;
                editForm.querySelector('[data-attr-edit-field=\"attribute_name\"]').value = payload.attribute_name ||
                '';
                editForm.querySelector('[data-attr-edit-field=\"attribute_key\"]').value = payload.attribute_key || '';
                editForm.querySelector('[data-attr-edit-field=\"attribute_value\"]').value = payload.attribute_value ||
                    '';
                editForm.querySelector('[data-attr-edit-field=\"description\"]').value = payload.description || '';
                editForm.querySelector('[data-attr-edit-field=\"status\"]').checked = !!payload.status;
                editModal.querySelector('[data-attr-edit-title]').textContent = payload.attribute_name ||
                    'Edit attribute';
                editModal.classList.remove('hidden');
                editModal.classList.add('flex');
            }

            function closeEdit() {
                editModal.classList.add('hidden');
                editModal.classList.remove('flex');
            }
            document.querySelectorAll('[data-attr-edit-close]').forEach((b) => b.addEventListener('click', closeEdit));
            editModal?.addEventListener('click', (e) => {
                if (e.target === editModal) closeEdit();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !editModal.classList.contains('hidden')) closeEdit();
            });

            function wireEdit() {
                document.querySelectorAll('[data-attr-edit]').forEach((b) => {
                    if (b.__wired) return;
                    b.__wired = true;
                    b.addEventListener('click', () => {
                        try {
                            const payload = JSON.parse(b.dataset.attrEditPayload);
                            openEdit(payload);
                        } catch (e) {
                            window.WaToaster?.error?.('Could not load attribute');
                        }
                    });
                });
            }

            wireDelete();
            wireEdit();
            wirePagination();

            window.addEventListener('popstate', () => {
                const params = new URLSearchParams(window.location.search);
                const state = {
                    status: params.get('status') || 'all',
                    q: params.get('q') || '',
                    page: params.get('page') || '1'
                };
                writeState(state);
                paint(state);
                refresh(state, {
                    silent: true
                });
            });
        })();
    </script>

</x-layouts.user>
