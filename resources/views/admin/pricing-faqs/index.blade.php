<x-layouts.admin :title="__('Admin · Pricing FAQs')" admin-key="checkout-settings">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/checkout-settings') }}" class="hover:text-ink-900">{{ __('Billing settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Pricing FAQs') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Admin · Billing & plans · FAQs') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Pricing') }} <span
                    class="italic text-wa-deep">{{ __('FAQs') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('The accordion shown at the bottom of the') }} <a
                    href="{{ url('/account/plans') }}" target="_blank"
                    class="text-wa-deep font-semibold hover:underline">{{ __('plans page') }}</a>.
                {{ __('Lowest sort order shows first.') }}</p>
        </div>

        <x-admin.flash />

        {{-- Create form --}}
        <form method="POST" action="{{ route('admin.pricing-faqs.store') }}"
            class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            @csrf
            <h2 class="font-serif text-[20px] mb-3">{{ __('New FAQ') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-[1fr_100px_120px] gap-4">
                <div class="space-y-2">
                    <input required name="question" type="text" placeholder="{{ __('Question') }}"
                        class="w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px]" />
                    <textarea required name="answer" rows="2" placeholder="{{ __('Answer (markdown not supported — plain text)') }}"
                        class="w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px]"></textarea>
                </div>
                <label class="text-[12px] text-ink-700">{{ __('Sort order') }} <input name="sort_order" type="number"
                        min="0" max="9999" placeholder="{{ __('auto') }}"
                        class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono" />
                </label>
                <label class="text-[12px] text-ink-700">{{ __('Shows on') }}
                    <select name="placement" class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px]">
                        <option value="pricing">{{ __('Pricing page') }}</option>
                        <option value="home">{{ __('Homepage slider') }}</option>
                        <option value="both">{{ __('Both') }}</option>
                    </select>
                </label>
                <div class="flex flex-col gap-2">
                    <label class="text-[12px] text-ink-700">{{ __('Active') }} <div class="mt-1"><label
                                class="toggle"><input type="checkbox" name="is_active" value="1" checked><span
                                    class="track"></span><span class="thumb"></span></label></div>
                    </label>
                    <button type="submit"
                        class="px-3 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Add FAQ') }}</button>
                </div>
            </div>
        </form>

        {{-- Existing FAQs --}}
        @forelse ($faqs as $faq)
            <form method="POST" action="{{ route('admin.pricing-faqs.update', $faq->id) }}"
                class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                @csrf @method('PATCH')
                <div class="grid grid-cols-1 sm:grid-cols-[1fr_100px_120px] gap-4">
                    <div class="space-y-2">
                        <input required name="question" type="text" value="{{ $faq->question }}"
                            class="w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px]" />
                        <textarea required name="answer" rows="2"
                            class="w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px]">{{ $faq->answer }}</textarea>
                    </div>
                    <label class="text-[12px] text-ink-700">{{ __('Sort') }} <input name="sort_order" type="number"
                            min="0" max="9999" value="{{ $faq->sort_order }}"
                            class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono" />
                    </label>
                    <label class="text-[12px] text-ink-700">{{ __('Shows on') }}
                        <select name="placement" class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px]">
                            <option value="pricing" @selected(($faq->placement ?? 'pricing') === 'pricing')>{{ __('Pricing page') }}</option>
                            <option value="home" @selected(($faq->placement ?? '') === 'home')>{{ __('Homepage slider') }}</option>
                            <option value="both" @selected(($faq->placement ?? '') === 'both')>{{ __('Both') }}</option>
                        </select>
                    </label>
                    <div class="flex flex-col gap-2">
                        <label class="text-[12px] text-ink-700">{{ __('Active') }} <div class="mt-1"><label
                                    class="toggle"><input type="checkbox" name="is_active" value="1"
                                        @checked($faq->is_active)><span class="track"></span><span
                                        class="thumb"></span></label></div>
                        </label>
                        <div class="flex items-center gap-1">
                            <button type="submit"
                                class="flex-1 px-2 py-1.5 border border-paper-200 rounded-full hover:bg-paper-50 text-[11.5px] font-semibold">{{ __('Save') }}</button>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-paper-100">
                    <span class="font-mono text-[10px] text-ink-500">#{{ $faq->id }} · last updated
                        {{ $faq->updated_at?->diffForHumans() }}</span>
                    <span class="flex-1"></span>
                </div>
            </form>
            <form method="POST" action="{{ route('admin.pricing-faqs.destroy', $faq->id) }}"
                class="-mt-3 text-right pr-2"
                data-confirm="Delete this FAQ? It'll disappear from the public pricing page immediately."
                data-confirm-title="{{ __('Delete FAQ') }}" data-confirm-text="Yes, delete" data-danger="1">@csrf
                @method('DELETE')
                <button class="text-[11px] text-accent-coral font-semibold hover:underline">Delete FAQ
                    #{{ $faq->id }}</button>
            </form>
        @empty
            <div
                class="bg-paper-0 border border-paper-200 rounded-2xl p-8 shadow-card text-center text-[12px] text-ink-500 italic">
                {{ __('No FAQs yet. Add one above.') }}</div>
        @endforelse

    </main>
</x-layouts.admin>
