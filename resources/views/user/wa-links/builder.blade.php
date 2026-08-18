<x-layouts.user :title="__('WhatsApp Link Builder')" nav-key="more" page="user-wa-links-builder">

    @php
        $l = $link;
        $defaults = [
            'id' => $l?->id ?? null,
            'name' => $l?->name ?? '',
            'country_code' => $l?->country_code ?? app_default_country()['code'],
            'phone_number' => $l?->phone_number ?? '',
            'welcome_message' => $l?->welcome_message ?? "Hi! I'd like to chat about your offer.",
            'slug' => $l?->slug ?? '',
            'utm_source' => $l?->utm_source ?? '',
            'utm_medium' => $l?->utm_medium ?? '',
            'utm_campaign' => $l?->utm_campaign ?? '',
            'expires_at' => $l?->expires_at?->format('Y-m-d') ?? '',
            'status' => $l?->status ?? 'active',
        ];
        $existingSlug = $l?->slug ?? null;
        $existingShort = $l ? url('/l/' . $l->slug) : null;
    @endphp

    <div class="border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/wa-links') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to links') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">WhatsApp links /
                        {{ $mode === 'edit' ? 'Edit' : 'New' }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">
                        {{ $mode === 'edit' ? 'Edit' : 'Mint a' }} <span
                            class="italic text-wa-deep">{{ __('short link') }}</span></div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span id="wcl-state-pill"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 font-mono">
                    {{ $mode === 'edit' ? 'Saved' : 'Draft / unsaved' }}
                </span>
                <button id="wcl-save" type="button"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Save draft') }}</button>
            </div>
        </div>
    </div>

    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6">
        <div id="wcl-builder" class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5 items-start"
            data-mode="{{ $mode }}" data-defaults='@json($defaults)'
            data-existing-slug="{{ $existingSlug }}" data-existing-short="{{ $existingShort }}">

            <div class="bg-white border border-paper-200 rounded-2xl shadow-card overflow-hidden">

                <div class="px-5 py-4 border-b border-paper-200 bg-paper-50/40">
                    <div class="flex items-center overflow-x-auto" id="wcl-stepper">
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="1">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-wa-deep text-wa-deep ring-4 ring-wa-deep/10">1</span>
                            <span
                                class="lab text-[11.5px] font-semibold whitespace-nowrap text-wa-deep">{{ __('Destination') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="2">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">2</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Starter') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 cursor-pointer" data-n="3">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">3</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Tracking') }}</span>
                        </div>
                    </div>
                </div>

                <div class="p-5">

                    {{-- STEP 1: DESTINATION --}}
                    <div class="step-pane" data-step="1">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Where does the link go?') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Internal label') }}
                                    <span class="text-accent-coral">*</span></label>
                                <input data-field="name" type="text"
                                    placeholder="{{ __('e.g. Instagram bio link') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('For your records — visitors never see this.') }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Roll-out status') }}</label>
                                <select data-field="status"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="active">{{ __('Live — link redirects to WhatsApp') }}</option>
                                    <option value="paused">{{ __('Paused — returns "link paused" page') }}</option>
                                </select>
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('You can pause a link without deleting its click history.') }}</div>
                            </div>
                        </div>

                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Destination number') }}
                                <span class="text-accent-coral">*</span></label>
                            <div class="wa-iti-wrap">
                                <input data-field="country_code" type="hidden">
                                <input data-wcl-phone type="tel" placeholder="{{ __('Your number without country code') }}" autocomplete="off"
                                    class="w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <input data-field="phone_number" type="hidden">
                            </div>
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('Pick the country flag — the dial code is added automatically. Number must be active on WhatsApp.') }}
                            </div>
                        </div>
                    </div>

                    {{-- STEP 2: STARTER --}}
                    <div class="step-pane hidden" data-step="2">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('What does the visitor say first?') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('conversation') }}</span>
                        </div>

                        <div class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Conversation starter') }}</label>
                            <textarea data-field="welcome_message" rows="5"
                                placeholder="{{ __("Hi! I'd like to know more about your offer.") }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></textarea>
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __("Pre-typed into the visitor's WhatsApp. Add a UTM-like tag (e.g.") }} <span
                                    class="font-mono">[from: pricing]</span>) so your team knows where they came from.
                            </div>
                        </div>

                        <div class="rounded-lg border border-paper-200 bg-paper-50/60 p-3">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-1.5">
                                {{ __('What lands in WhatsApp') }}</div>
                            <div id="wcl-prev-bubble"
                                class="bg-wa-bubble border border-wa-green/30 rounded-2xl rounded-tr-md px-3 py-2 text-[12.5px] text-ink-800 max-w-[320px] shadow-card whitespace-pre-wrap">
                            </div>
                            <div class="mt-2 text-[10.5px] text-ink-500">
                                {{ __("This is what auto-fills in the visitor's chat input.") }}</div>
                        </div>
                    </div>

                    {{-- STEP 3: TRACKING --}}
                    <div class="step-pane hidden" data-step="3">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Short slug, UTMs, expiry') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('tracking') }}</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-[1fr_180px] gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Short slug') }}</label>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-[12.5px] text-ink-500">{{ url('/l') }}/</span>
                                    <input data-field="slug" type="text"
                                        placeholder="{{ __('leave blank to auto-generate') }}"
                                        class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </div>
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Lowercase letters, numbers, and dashes. Must be unique.') }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Auto-expire on') }}</label>
                                <input data-field="expires_at" type="date"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Leave blank for no expiry.') }}
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-paper-200 pt-4">
                            <div class="font-semibold text-ink-700 text-[12.5px] mb-1.5">{{ __('UTM tagging') }} <span
                                    class="font-normal text-ink-500">(optional, for analytics)</span></div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label
                                        class="text-[11px] font-semibold text-ink-700 mb-1 block">{{ __('Source') }}</label>
                                    <input data-field="utm_source" type="text"
                                        placeholder="{{ __('instagram') }}"
                                        class="w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[12px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </div>
                                <div>
                                    <label
                                        class="text-[11px] font-semibold text-ink-700 mb-1 block">{{ __('Medium') }}</label>
                                    <input data-field="utm_medium" type="text" placeholder="{{ __('bio-link') }}"
                                        class="w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[12px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </div>
                                <div>
                                    <label
                                        class="text-[11px] font-semibold text-ink-700 mb-1 block">{{ __('Campaign') }}</label>
                                    <input data-field="utm_campaign" type="text"
                                        placeholder="{{ __('may-launch') }}"
                                        class="w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[12px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </div>
                            </div>
                            <div class="text-[10.5px] text-ink-500 mt-1.5">
                                {{ __('Stored on the link row so click reports can group by source / medium / campaign.') }}
                            </div>
                        </div>

                        {{-- Generated panel — revealed after Mint --}}
                        <div id="wcl-mint-block"
                            class="hidden border border-wa-green/30 bg-wa-bubble/30 rounded-2xl p-4 mt-5">
                            <div class="font-semibold text-ink-900 text-[13px] mb-2 flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.8">
                                    <path d="M2 8l5 5L14 4" />
                                </svg>
                                {{ __('Ready. Your short link is below.') }}
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-[1fr_140px] gap-4 items-start">
                                <div class="space-y-2">
                                    <label
                                        class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Short link') }}</label>
                                    <pre id="wcl-short"
                                        class="bg-ink-900 text-paper-0 font-mono text-[12px] rounded-lg p-3 overflow-x-auto whitespace-pre-wrap leading-relaxed"></pre>
                                    <div class="flex gap-2">
                                        <button type="button" id="wcl-copy-short"
                                            class="px-3 py-1.5 rounded-md bg-wa-deep text-paper-0 text-[11px] font-semibold hover:bg-wa-teal flex items-center gap-1.5">
                                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <rect x="4" y="4" width="9" height="9" rx="1.5" />
                                                <path d="M3 3h8M3 3v8" />
                                            </svg>
                                            Copy link
                                        </button>
                                        <a id="wcl-open-short" target="_blank" rel="noopener"
                                            class="px-3 py-1.5 rounded-md border border-paper-200 text-[11px] font-semibold text-ink-700 hover:border-wa-deep hover:text-wa-deep flex items-center gap-1.5">
                                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <path d="M3 13l10-10M6 3h7v7" />
                                            </svg>
                                            Open in browser
                                        </a>
                                    </div>
                                    <div class="pt-2 border-t border-paper-200/60">
                                        <label
                                            class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Direct wa.me URL') }}</label>
                                        <pre id="wcl-wa"
                                            class="bg-paper-100 text-ink-800 font-mono text-[11px] rounded-lg p-2 mt-1 overflow-x-auto whitespace-pre-wrap leading-relaxed"></pre>
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500 mb-1.5 block">{{ __('QR code') }}</label>
                                    <img id="wcl-qr" alt="{{ __('QR code') }}"
                                        class="w-full rounded-lg border border-paper-200 bg-white">
                                    <a id="wcl-qr-dl" download="wa-link-qr.png"
                                        class="block text-center mt-2 px-3 py-1.5 rounded-md border border-paper-200 text-[11px] font-semibold text-ink-700 hover:border-wa-deep hover:text-wa-deep">{{ __('Download QR') }}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-4 border-t border-paper-200 bg-paper-50/40 flex items-center justify-between">
                    <button id="wcl-prev" type="button"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-white text-[12px] font-semibold text-ink-700 disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M10 4l-4 4 4 4" />
                        </svg>
                        Previous
                    </button>
                    <div class="font-mono text-[11px] text-ink-500">{{ __('Step') }} <span id="wcl-cur">1</span>
                        of 3</div>
                    <div class="flex items-center gap-2">
                        <button id="wcl-next" type="button"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">
                            Next
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M6 4l4 4-4 4" />
                            </svg>
                        </button>
                        <button id="wcl-mint" type="button" style="display:none"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-wa-green hover:opacity-90 text-paper-0 text-[12px] font-semibold">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M2 8l5 5 7-9" />
                            </svg>
                            Mint short link
                        </button>
                    </div>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="bg-white border border-paper-200 rounded-2xl shadow-card p-4 sticky top-[92px]">
                    <div class="flex items-center justify-between mb-3">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Live preview') }}</span>
                        <span
                            class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep">{{ __('wa.me') }}</span>
                    </div>

                    <div class="rounded-lg border border-paper-200 bg-paper-50/60 p-3 mb-3">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-1.5">
                            {{ __('Short link') }}</div>
                        <div id="wcl-prev-short" class="font-mono text-[12.5px] text-ink-900 break-all">
                            {{ url('/l') }}/—</div>
                    </div>
                    <div class="rounded-lg border border-paper-200 bg-paper-50/60 p-3 mb-3">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-1.5">
                            {{ __('wa.me destination') }}</div>
                        <div id="wcl-prev-wa" class="font-mono text-[11.5px] text-ink-700 break-all">https://wa.me/—
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-lg border border-paper-200 bg-paper-50/60 p-3">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Status') }}</div>
                            <div id="wcl-prev-status" class="font-serif text-[16px] leading-tight mt-1 capitalize">
                                {{ __('Active') }}</div>
                        </div>
                        <div class="rounded-lg border border-paper-200 bg-paper-50/60 p-3">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Expiry') }}</div>
                            <div id="wcl-prev-expiry" class="font-serif text-[14px] leading-tight mt-1 font-mono">
                                {{ __('none') }}</div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </section>

</x-layouts.user>
