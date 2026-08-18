<x-layouts.admin :title="$addon ? __('Admin · Edit add-on') : __('Admin · New add-on')" admin-key="addons" page="addons-form">
    @php $isEdit = (bool) $addon; @endphp

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ route('admin.addons.index') }}" class="hover:text-ink-900">{{ __('Add-ons') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ $isEdit ? __('Edit') : __('New') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2 flex-wrap justify-end">
            <a href="{{ route('admin.addons.index') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
            <button type="submit" form="addonForm"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8l5 5 7-9" /></svg>
                {{ $isEdit ? __('Save changes') : __('Create add-on') }}
            </button>
        </div>
    </header>

    <div class="px-4 sm:px-7 pt-7 pb-2">
        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">Admin · Add-ons · {{ $isEdit ? __('Edit') : __('New') }}</div>
        <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[36px] leading-[1.0]">
            {{ $isEdit ? __('Edit') : __('Create an') }} <span class="italic text-wa-deep">{{ __('add-on') }}</span></h1>
        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
            {{ __('An add-on grants ONLY what you switch on and nothing else. A limit left blank is not touched — no accidental "unlimited".') }}</p>
    </div>

    <main class="px-4 sm:px-7 pb-7">
        @if ($errors->any())
            <div class="mb-4 rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">
                <div class="font-semibold mb-1">{{ __('Please fix the following:') }}</div>
                <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form id="addonForm" method="POST" action="{{ $isEdit ? route('admin.addons.update', $addon->id) : route('admin.addons.store') }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="bg-white border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="p-5 space-y-8">

                    {{-- 01 — Basics --}}
                    <div>
                        <div class="flex items-center gap-2.5 mb-4">
                            <span class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                            <span class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Add-on basics') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Name') }}</label>
                                <input required name="pname" value="{{ old('pname', $addon->pname ?? '') }}" placeholder="Campaigns add-on"
                                    class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Subtitle') }}</label>
                                <input name="subtitle" value="{{ old('subtitle', $addon->subtitle ?? '') }}" placeholder="Unlock campaigns"
                                    class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Price') }}</label>
                                <input required type="number" step="0.01" min="0" name="plan_amount" value="{{ old('plan_amount', $addon->plan_amount ?? '') }}"
                                    class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Offer price (optional)') }}</label>
                                <input type="number" step="0.01" min="0" name="offer_price" value="{{ old('offer_price', $addon->offer_price ?? '') }}"
                                    class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Currency') }}</label>
                                <select name="currency" class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                                    @foreach ($currencies as $c)
                                        <option value="{{ $c->code }}" @selected(old('currency', $addon->currency ?? '') === $c->code)>{{ $c->code }} — {{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Duration') }}</label>
                                    <input required type="number" min="1" name="plan_duration" value="{{ old('plan_duration', $addon->plan_duration ?? 1) }}"
                                        class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                </div>
                                <div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Unit') }}</label>
                                    <select name="plan_unit" class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                                        @foreach (['days','weeks','months','years'] as $u)
                                            <option value="{{ $u }}" @selected(old('plan_unit', $addon->plan_unit ?? 'months') === $u)>{{ ucfirst($u) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Sort order') }}</label>
                                <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $addon->sort_order ?? 0) }}"
                                    class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </div>
                            <div class="flex items-end gap-6 pb-1">
                                <label class="inline-flex items-center gap-2 text-[12.5px] cursor-pointer">
                                    <input type="checkbox" name="status" value="1" @checked(old('status', $addon->status ?? true)) class="rounded border-paper-200 text-wa-deep focus:ring-wa-deep"> {{ __('Active') }}
                                </label>
                                <label class="inline-flex items-center gap-2 text-[12.5px] cursor-pointer">
                                    <input type="checkbox" name="lifetime" value="1" @checked(old('lifetime', $addon->lifetime ?? false)) class="rounded border-paper-200 text-wa-deep focus:ring-wa-deep"> {{ __('Lifetime (one-time)') }}
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- 02 — Features it unlocks --}}
                    <div class="pt-2 border-t border-paper-200">
                        <div class="flex items-center gap-2.5 mb-1 mt-4">
                            <span class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                            <span class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Features this add-on unlocks') }}</span>
                        </div>
                        <p class="text-[11.5px] text-ink-500 mb-3 pl-[33px]">{{ __('Tick only what this add-on grants. Everything unticked stays governed by the customer\'s plan.') }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            @foreach ($featureToggles as $f)
                                <label class="inline-flex items-center gap-2 text-[12.5px] cursor-pointer rounded-lg border border-paper-200 px-3 py-2 hover:bg-paper-50">
                                    <input type="checkbox" name="features[]" value="{{ $f }}"
                                        @checked(in_array($f, old('features', $grantFeatures), true))
                                        class="rounded border-paper-200 text-wa-deep focus:ring-wa-deep">
                                    {{ $labels[$f] ?? $f }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- 03 — Limit boosts --}}
                    <div class="pt-2 border-t border-paper-200">
                        <div class="flex items-center gap-2.5 mb-1 mt-4">
                            <span class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                            <span class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Limit boosts') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('optional') }}</span>
                        </div>
                        <p class="text-[11.5px] text-ink-500 mb-3 pl-[33px]">{{ __('Blank = the add-on does not touch this limit. Enter a number to ADD on top of the plan (e.g. +2 devices). Use -1 for unlimited.') }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach ($limitColumns as $l)
                                <div>
                                    <label class="text-[11px] font-semibold text-ink-700 mb-1 block">{{ $labels[$l] ?? $l }}</label>
                                    <input type="number" min="-1" name="limits[{{ $l }}]"
                                        value="{{ old('limits.' . $l, $grantLimits[$l] ?? '') }}" placeholder="—"
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                </div>
                            @endforeach
                        </div>
                    </div>

                </div>

                <div class="px-5 py-4 border-t border-paper-200 bg-paper-50/40 flex items-center justify-end gap-2">
                    <a href="{{ route('admin.addons.index') }}" class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
                    <button type="submit" class="px-5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">
                        {{ $isEdit ? __('Save changes') : __('Create add-on') }}</button>
                </div>
            </div>
        </form>
    </main>
</x-layouts.admin>
