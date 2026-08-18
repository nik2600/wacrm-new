@php
    $a = $announcement ?? null;
    $action = $a ? route('admin.announcements.update', $a->id) : route('admin.announcements.store');
@endphp
<form id="annForm" method="POST" action="{{ $action }}" class="grid grid-cols-1 xl:grid-cols-3 gap-5">
    @csrf
    @if ($a)
        @method('PATCH')
    @endif

    {{-- ── Section 1: Message ── --}}
    <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <span
                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
            <span class="font-serif text-[18px] leading-none">{{ __('Message') }}</span>
        </div>
        <div class="space-y-3">
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="an-text">{{ __('Text') }}
                    <span class="text-accent-coral">*</span></label>
                <textarea id="an-text" name="text" rows="3" required maxlength="500"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] resize-none focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                    placeholder="{{ __('e.g. New: AI voice agents now live in Pro and Enterprise plans') }}">{{ old('text', $a?->text) }}</textarea>
                <div class="text-[10.5px] text-ink-500 mt-1">
                    {{ __('One short sentence works best. Visible on every authenticated page.') }}</div>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                    for="an-tone">{{ __('Tone') }}</label>
                @php $tones = ['info' => 'Info (dark ink)', 'promo' => 'Promo (dark wood)', 'warning' => 'Warning (amber)', 'success' => 'Success (green)']; @endphp
                <select id="an-tone" name="tone"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    @foreach ($tones as $k => $label)
                        <option value="{{ $k }}" @selected(old('tone', $a?->tone ?? 'info') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- ── Section 2: Link & visibility ── --}}
    <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <span
                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
            <span class="font-serif text-[18px] leading-none">{{ __('Link & visibility') }}</span>
        </div>
        <div class="space-y-3">
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                    for="an-url">{{ __('Link URL') }}</label>
                <input id="an-url" type="text" name="link_url" maxlength="500"
                    value="{{ old('link_url', $a?->link_url) }}"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                    placeholder="/pricing or https://...">
                <div class="text-[10.5px] text-ink-500 mt-1">
                    {{ __('Optional. Whole bar becomes a clickable link when set.') }}</div>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                    for="an-label">{{ __('Link label') }}</label>
                <input id="an-label" type="text" name="link_label" maxlength="64"
                    value="{{ old('link_label', $a?->link_label) }}"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                    placeholder="{{ __('Learn more →') }}">
                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Optional CTA chip shown next to the message.') }}
                </div>
            </div>
            <label
                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                <span>
                    <span class="block text-[12.5px] font-semibold">{{ __('Active') }}</span>
                    <span
                        class="block text-[10.5px] text-ink-500">{{ __('Show in the marquee right now (if inside window)') }}</span>
                </span>
                <input type="hidden" name="is_active" value="0">
                <span class="relative inline-block w-[34px] h-5 shrink-0">
                    <input class="peer opacity-0 w-0 h-0" type="checkbox" name="is_active" value="1"
                        @checked(old('is_active', $a?->is_active ?? 1))>
                    <span
                        class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                </span>
            </label>
            <label
                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                <span>
                    <span class="block text-[12.5px] font-semibold">{{ __('Dismissible') }}</span>
                    <span
                        class="block text-[10.5px] text-ink-500">{{ __('User can X-out the bar (saved in their browser)') }}</span>
                </span>
                <input type="hidden" name="dismissible" value="0">
                <span class="relative inline-block w-[34px] h-5 shrink-0">
                    <input class="peer opacity-0 w-0 h-0" type="checkbox" name="dismissible" value="1"
                        @checked(old('dismissible', $a?->dismissible ?? 1))>
                    <span
                        class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                </span>
            </label>
        </div>
    </div>

    {{-- ── Section 3: Scheduling ── --}}
    <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <span
                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
            <span class="font-serif text-[18px] leading-none">{{ __('Scheduling') }}</span>
        </div>
        <div class="space-y-3">
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                    for="an-tz">{{ __('Timezone for the times below') }}</label>
                <select id="an-tz" name="input_timezone"
                    data-value="{{ old('input_timezone', config('app.timezone')) }}"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    {{-- Filled in by admin-announcements-form.js using Intl.supportedValuesOf('timeZone'). --}}
                    <option value="{{ config('app.timezone') }}">{{ config('app.timezone') }}</option>
                </select>
                <div class="text-[10.5px] text-ink-500 mt-1">
                    {{ __('All times below are interpreted in this timezone, then stored.') }}</div>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                    for="an-start">{{ __('Starts at') }}</label>
                <input id="an-start" type="datetime-local" name="starts_at"
                    value="{{ old('starts_at', $a?->starts_at?->format('Y-m-d\TH:i')) }}"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Blank = visible from now.') }}</div>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                    for="an-exp">{{ __('Expires at') }}</label>
                <input id="an-exp" type="datetime-local" name="expires_at"
                    value="{{ old('expires_at', $a?->expires_at?->format('Y-m-d\TH:i')) }}"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Blank = never expires.') }}</div>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                    for="an-sort">{{ __('Sort order') }}</label>
                <input id="an-sort" type="number" name="sort_order" min="0" max="9999"
                    value="{{ old('sort_order', $a?->sort_order ?? 0) }}"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Lower numbers appear first in the marquee.') }}
                </div>
            </div>
        </div>
    </div>

    <div class="xl:col-span-3 flex justify-end gap-2 pt-1">
        <a href="{{ route('admin.announcements.index') }}"
            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12.5px] font-medium">{{ __('Cancel') }}</a>
        <button type="submit"
            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold flex items-center gap-2">
            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 8l5 5 7-9" />
            </svg>
            {{ $a ? 'Save changes' : 'Create announcement' }}
        </button>
    </div>
</form>
