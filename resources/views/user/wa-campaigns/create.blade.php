<x-layouts.user :title="__('New WhatsApp Campaign')" nav-key="wa-campaigns" page="user-wa-campaigns-create">

    @if (session('status') || $errors->any())
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    @if (session('status'))
                        window.toast(@json(session('status')), 'success');
                    @endif
                    @foreach ($errors->all() as $err)
                        window.toast(@json($err), 'error');
                    @endforeach
                });
            </script>
        @endpush
    @endif

    <div class="border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('user.wa-campaigns.index') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center shrink-0"
                    title="{{ __('Back to WhatsApp Campaigns') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('WA Campaigns / New') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Create new WhatsApp') }} <span
                            class="italic text-wa-deep">{{ __('campaign') }}</span></div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 font-mono">{{ __('Draft / unsaved') }}</span>
                <button type="button" id="open-ai-modal"
                    class="btn-ai-shine relative isolate overflow-hidden px-3.5 py-1.5 rounded-full text-[12px] font-semibold flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path
                            d="M8 2v3M8 11v3M2 8h3M11 8h3M4.2 4.2l2.1 2.1M9.7 9.7l2.1 2.1M11.8 4.2L9.7 6.3M6.3 9.7l-2.1 2.1" />
                    </svg>
                    Build with AI
                </button>
                <button type="button"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Save draft') }}</button>
            </div>
        </div>
    </div>

    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6">
        <form id="campaignForm" method="POST" action="{{ route('user.wa-campaigns.store') }}"
            enctype="multipart/form-data" data-ajax="create-campaign"
            class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5 items-start">
            @csrf

            <div class="bg-white border border-paper-200 rounded-2xl shadow-card overflow-hidden">

                <div class="px-5 py-4 border-b border-paper-200 bg-paper-50/40 overflow-x-auto">
                    <div class="flex items-center min-w-[560px]" id="stepper">
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="1">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-wa-deep text-wa-deep ring-4 ring-wa-deep/10">1</span>
                            <span
                                class="lab text-[11.5px] font-semibold whitespace-nowrap text-wa-deep">{{ __('Setup') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="2">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">2</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Compose') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="3">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">3</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Recipients') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="4">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">4</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Schedule') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 cursor-pointer" data-n="5">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">5</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Review') }}</span>
                        </div>
                    </div>
                </div>

                <div class="p-5">
                    <div class="step-pane" data-step="1">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Campaign setup') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-1.5"
                                    for="campaign-name">{{ __('Campaign name') }} <span
                                        class="text-accent-coral">*</span></label>
                                <input id="campaign-name" name="campaign_name" type="text"
                                    value="{{ old('campaign_name') }}"
                                    placeholder="{{ __('e.g. May offer reactivation') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 leading-snug focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    required>
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Internal label for reports and queue history.') }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-1.5"
                                    for="device">{{ __('Sender') }} <span class="text-accent-coral">*</span></label>
                                <x-sender-picker :senders="$senders" name="sender" id="device"
                                    :selected="old('sender')" :required="true"
                                    :placeholder="__('— Select a sender —')" />
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Only connected senders can launch a queue.') }}</div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-2">{{ __('Campaign type') }}
                                <span class="text-accent-coral">*</span></label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <label
                                    class="type-tile cursor-pointer border rounded-2xl p-4 transition border-wa-deep bg-[#F0F8F6]"
                                    data-type="Custom message">
                                    <input class="sr-only" type="radio" name="campaign_type" value="text" checked>
                                    <div class="flex items-start justify-between mb-3">
                                        <span
                                            class="w-10 h-10 rounded-xl bg-wa-mint text-wa-deep grid place-items-center">
                                            <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <path
                                                    d="M3 5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H8l-3 2v-2a2 2 0 0 1-2-2z" />
                                            </svg>
                                        </span>
                                        <span
                                            class="type-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center transition opacity-100">
                                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                stroke="currentColor" stroke-width="2.4">
                                                <path d="M3 8l3 3 7-8" />
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="font-serif text-[18px] leading-tight">{{ __('Custom message') }}</div>
                                    <p class="mt-1.5 text-[12px] text-ink-500 leading-snug">
                                        {{ __('Write text and attach media or buttons for a one-off broadcast.') }}</p>
                                </label>
                                <label
                                    class="type-tile cursor-pointer border rounded-2xl p-4 transition border-paper-200"
                                    data-type="Template">
                                    <input class="sr-only" type="radio" name="campaign_type" value="template">
                                    <div class="flex items-start justify-between mb-3">
                                        <span
                                            class="w-10 h-10 rounded-xl bg-paper-50 text-wa-deep grid place-items-center">
                                            <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <rect x="2.5" y="2.5" width="11" height="11" rx="1.5" />
                                                <path d="M5 6h6M5 8.5h6M5 11h3" />
                                            </svg>
                                        </span>
                                        <span
                                            class="type-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center transition opacity-0">
                                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                stroke="currentColor" stroke-width="2.4">
                                                <path d="M3 8l3 3 7-8" />
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="font-serif text-[18px] leading-tight">{{ __('Use template') }}</div>
                                    <p class="mt-1.5 text-[12px] text-ink-500 leading-snug">
                                        {{ __('Pick an approved Meta template for marketing or utility messages.') }}
                                    </p>
                                </label>
                                <label
                                    class="type-tile cursor-pointer border rounded-2xl p-4 transition border-paper-200"
                                    data-type="Flow">
                                    <input class="sr-only" type="radio" name="campaign_type" value="flow">
                                    <div class="flex items-start justify-between mb-3">
                                        <span
                                            class="w-10 h-10 rounded-xl bg-accent-amber/20 text-wa-deep grid place-items-center">
                                            <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <circle cx="3.5" cy="8" r="1.8" />
                                                <circle cx="12.5" cy="3.5" r="1.8" />
                                                <circle cx="12.5" cy="12.5" r="1.8" />
                                                <path d="M5 7l6-3M5 9l6 3" />
                                            </svg>
                                        </span>
                                        <span
                                            class="type-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center transition opacity-0">
                                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                stroke="currentColor" stroke-width="2.4">
                                                <path d="M3 8l3 3 7-8" />
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="font-serif text-[18px] leading-tight">{{ __('Flow builder') }}</div>
                                    <p class="mt-1.5 text-[12px] text-ink-500 leading-snug">
                                        {{ __('Start a saved flow for every recipient in this queue.') }}</p>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                                <span>
                                    <span class="block text-[12.5px] font-semibold">{{ __('Enable A/B test') }}</span>
                                    <span
                                        class="block text-[10.5px] text-ink-500">{{ __('Split recipients between two message variants.') }}</span>
                                </span>
                                <span class="relative inline-block w-[34px] h-5 shrink-0">
                                    <input id="ab-test" name="ab_testing" value="1"
                                        class="peer opacity-0 w-0 h-0" type="checkbox">
                                    <span
                                        class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                                </span>
                            </label>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-1.5"
                                    for="split">{{ __('Variant A split') }} <span id="split-val"
                                        class="font-mono text-wa-deep">50%</span></label>
                                <input id="split" name="ab_split" type="range" min="10" max="90"
                                    value="50" class="w-full accent-wa-deep">
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Variant B receives the remaining audience. Set Variant B content in the Compose step.') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="step-pane hidden" data-step="2">

                        {{-- ==== CUSTOM MESSAGE surface ====
 Cloned section-for-section from the TEMPLATE BUILDER
 (resources/views/user/templates/create.blade.php) so the
 composer reads identically: numbered sections + a WhatsApp
 phone live preview on the right (the preview markup lives in
 the <aside> below and is driven by user-wa-campaigns-create.js).

 This is a CUSTOM Baileys (Unofficial API) send — NOT a Meta
 positional-template payload. Each section maps to an EXISTING
 campaign field the verified send path already reads, so no
 control is ever ignored:

 Header → custom_header (Baileys title slot)
 Body → custom_message (resolveCampaignBody +
 + variable map → custom_message_variable_map)
 Attachment → custom_image / custom_video / custom_document
 (sendRaw media_path/media_type)
 Footer → custom_footer (Baileys footer slot)
 Buttons → custom_buttons[i][text]/[url] (CTA) and
 custom_quick_replies[i] (quick reply)

 Header Type is None/Text only — an image/video "header" IS
 the Attachment section on this Baileys path, so we don't offer
 a media-header dropdown that the send path can't honour. --}}
                        <div data-surface="custom" class="space-y-0 -mx-5 -mb-5">

                            {{-- Per-engine note: filled by JS when a Twilio/WABA sender is
 picked. Twilio custom = text + media only (buttons need a template);
 WABA custom only reaches 24h-window contacts (cold/bulk → template). --}}
                            <div data-engine-note
                                class="hidden mx-5 mt-4 rounded-xl border border-accent-amber/40 bg-accent-amber/10 px-3 py-2.5 text-[11.5px] text-ink-700"></div>

                            <!-- 02a Header -->
                            <div class="sec px-5 py-4 border-b border-paper-200">
                                <div class="flex items-center gap-2.5 mb-3">
                                    <span
                                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">A</span>
                                    <span
                                        class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Header') }}</span>
                                    <span class="font-mono text-[10px] text-ink-500">{{ __('optional') }}</span>
                                </div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="cc-header">{{ __('Header text') }} <span
                                        class="font-mono text-[10px] text-ink-500">{{ __('max 60') }}</span></label>
                                <input id="cc-header" name="custom_header" type="text"
                                    value="{{ old('custom_header') }}" maxlength="60"
                                    placeholder="{{ __('e.g. May offer is live') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>

                            <!-- 02b Body -->
                            <div class="sec px-5 py-4 border-b border-paper-200">
                                <div class="flex items-center gap-2.5 mb-3">
                                    <span
                                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">B</span>
                                    <span
                                        class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Body') }}</span>
                                    <span class="font-mono text-[10px] text-ink-500"><span
                                            class="text-accent-coral">{{ __('required') }}</span> / <span
                                            id="msg-count">0</span>/4096</span>
                                </div>
                                <x-compose-textarea id="message-body" name="custom_message" :rows="8"
                                    :maxlength="4096" :show-counter="false" :value="old('custom_message', '')" />

                                {{-- A/B test — Variant B message. Shown by JS only when the
                                     A/B toggle is on (the global JS handler toggles every
                                     [data-ab-b] wrapper). Variant A is the message above;
                                     recipients are split per the ab_split slider and the
                                     dispatcher swaps custom_message → custom_message_b per
                                     recipient at send. Uses the same compose-textarea
                                     component as Variant A so the operator gets the
                                     `/`-attribute picker for {{first_name}} etc.,
                                     markdown helpers, and the same character counter. --}}
                                <div data-ab-b class="hidden mt-3 border-t border-dashed border-paper-300 pt-3">
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="message-body-b">
                                        {{ __('Variant B message') }}
                                        <span class="text-ink-400 font-normal">{{ __('(A/B test — Variant A is the message above)') }}</span>
                                    </label>
                                    <x-compose-textarea id="message-body-b" name="custom_message_b" :rows="6"
                                        :maxlength="4096" :show-counter="false"
                                        :value="old('custom_message_b', '')"
                                        :placeholder="__('Alternate message for the Variant B audience…')" />
                                    <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Recipients in the Variant B half receive this message instead of the Variant A one above. Media, buttons, header, and footer are shared across both variants.') }}</div>
                                </div>

                                {{-- Variable mapping — clone of the template builder's panel.
 The `/`-attribute picker inserts {{1}} {{2}} into the body
 and records {slot: attribute_key} into the compose-textarea's
 hidden `custom_message_variable_map` field. The controller
 reads that map and resolves each slot to the real workspace /
 contact attribute value at send time. This panel just shows
 which slot maps to which attribute so the operator can verify
 the personalization before launching. --}}
                                {{-- Hidden: named tokens ({{company}}) already name their attribute,
 so the slot→attribute panel is redundant + confusing. The server
 builds the map from the token names on save. --}}
                                <div
                                    class="hidden mt-2.5 rounded-lg border border-paper-200 bg-paper-50/60 px-3 py-2.5">
                                    <div class="flex items-center gap-2 mb-2">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M3 8h10M9 4l4 4-4 4" />
                                        </svg>
                                        <span
                                            class="text-[11.5px] font-semibold text-ink-700">{{ __('Variable mapping') }}</span>
                                        <span
                                            class="font-mono text-[10px] text-ink-500">{{ __('which attribute fills each slot') }}</span>
                                    </div>
                                    <div id="cc-var-map-rows" class="space-y-1.5"></div>
                                    <div id="cc-var-map-empty" class="text-[11px] text-ink-500 leading-[1.4]">
                                        {{ __('No variables yet. Press / in the body to insert an attribute like') }}
                                        <span class="font-mono">@{{ 1 }}</span> {{ __('and map it.') }}
                                    </div>
                                </div>
                                <div class="text-[10.5px] text-ink-500 mt-1.5 flex flex-wrap items-center gap-1.5">
                                    <span>{{ __('Markdown:') }}</span>
                                    <code class="font-mono px-1.5 py-0.5 bg-paper-50 rounded text-[10px]">*bold*</code>
                                    <code
                                        class="font-mono px-1.5 py-0.5 bg-paper-50 rounded text-[10px]">_italic_</code>
                                    <code
                                        class="font-mono px-1.5 py-0.5 bg-paper-50 rounded text-[10px]">~strike~</code>
                                    <code
                                        class="font-mono px-1.5 py-0.5 bg-paper-50 rounded text-[10px]">```code```</code>
                                </div>
                            </div>

                            <!-- 02c Attachment -->
                            <div class="sec px-5 py-4 border-b border-paper-200">
                                <div class="flex items-center gap-2.5 mb-3">
                                    <span
                                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">C</span>
                                    <span
                                        class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Attachment') }}</span>
                                    <span
                                        class="font-mono text-[10px] text-ink-500">{{ __('optional / image, video, or document') }}</span>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-[160px_1fr] gap-3">
                                    <div>
                                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                            for="cc-attach-type">{{ __('Attachment type') }}</label>
                                        <select id="cc-attach-type"
                                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                            <option value="none">{{ __('None') }}</option>
                                            <option value="Image">{{ __('Image') }}</option>
                                            <option value="Video">{{ __('Video') }}</option>
                                            <option value="Document">{{ __('Document') }}</option>
                                        </select>
                                    </div>
                                    <div class="min-w-0">
                                        {{-- One file tile per type, only the selected one shows.
 All three inputs keep their exact send-path names; an
 unselected pane is hidden + its input cleared so a
 stale upload never rides along on submit. --}}
                                        <div data-media-pane="Image" class="hidden">
                                            <label
                                                class="flex items-center gap-2.5 px-3 py-2.5 border border-dashed border-wa-deep rounded-lg bg-paper-0 cursor-pointer transition hover:bg-wa-bubble"
                                                data-media-tile>
                                                <span
                                                    class="w-9 h-9 rounded-lg bg-wa-bubble text-wa-deep grid place-items-center shrink-0">
                                                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none"
                                                        stroke="currentColor" stroke-width="1.6">
                                                        <rect x="2" y="3" width="12" height="10"
                                                            rx="1.5" />
                                                        <circle cx="6" cy="7" r="1.2" />
                                                        <path d="m3 11 3-3 4 4 3-3 0 4" />
                                                    </svg>
                                                </span>
                                                <span class="flex-1 min-w-0">
                                                    <span class="block text-[12.5px] font-semibold"
                                                        data-media-title>{{ __('Choose an image') }}</span>
                                                    <span class="block text-[10.5px] text-ink-500"
                                                        data-media-sub>{{ __('JPG or PNG, max 2 MB.') }}</span>
                                                </span>
                                                <span
                                                    class="text-[10.5px] font-semibold text-wa-deep px-2.5 py-1 rounded-full bg-white border border-wa-deep shrink-0">{{ __('Browse') }}</span>
                                                <input name="custom_image" class="sr-only" type="file"
                                                    accept="image/*">
                                            </label>
                                        </div>
                                        <div data-media-pane="Video" class="hidden">
                                            <label
                                                class="flex items-center gap-2.5 px-3 py-2.5 border border-dashed border-wa-deep rounded-lg bg-paper-0 cursor-pointer transition hover:bg-wa-bubble"
                                                data-media-tile>
                                                <span
                                                    class="w-9 h-9 rounded-lg bg-accent-amber/20 text-wa-deep grid place-items-center shrink-0">
                                                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none"
                                                        stroke="currentColor" stroke-width="1.6">
                                                        <rect x="2" y="3" width="9" height="10"
                                                            rx="1.5" />
                                                        <path d="m11 6 3-1v6l-3-1z" />
                                                    </svg>
                                                </span>
                                                <span class="flex-1 min-w-0">
                                                    <span class="block text-[12.5px] font-semibold"
                                                        data-media-title>{{ __('Choose a video') }}</span>
                                                    <span class="block text-[10.5px] text-ink-500"
                                                        data-media-sub>{{ __('MP4, max 16 MB.') }}</span>
                                                </span>
                                                <span
                                                    class="text-[10.5px] font-semibold text-wa-deep px-2.5 py-1 rounded-full bg-white border border-wa-deep shrink-0">{{ __('Browse') }}</span>
                                                <input name="custom_video" class="sr-only" type="file"
                                                    accept="video/*">
                                            </label>
                                        </div>
                                        <div data-media-pane="Document" class="hidden">
                                            <label
                                                class="flex items-center gap-2.5 px-3 py-2.5 border border-dashed border-wa-deep rounded-lg bg-paper-0 cursor-pointer transition hover:bg-wa-bubble"
                                                data-media-tile>
                                                <span
                                                    class="w-9 h-9 rounded-lg bg-paper-50 text-wa-deep grid place-items-center shrink-0">
                                                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none"
                                                        stroke="currentColor" stroke-width="1.6">
                                                        <path d="M4 2h6l3 3v9H4z" />
                                                        <path d="M10 2v3h3" />
                                                    </svg>
                                                </span>
                                                <span class="flex-1 min-w-0">
                                                    <span class="block text-[12.5px] font-semibold"
                                                        data-media-title>{{ __('Choose a document') }}</span>
                                                    <span class="block text-[10.5px] text-ink-500"
                                                        data-media-sub>{{ __('PDF or DOC, max 16 MB.') }}</span>
                                                </span>
                                                <span
                                                    class="text-[10.5px] font-semibold text-wa-deep px-2.5 py-1 rounded-full bg-white border border-wa-deep shrink-0">{{ __('Browse') }}</span>
                                                <input name="custom_document" class="sr-only" type="file"
                                                    accept=".pdf,.doc,.docx">
                                            </label>
                                        </div>
                                        <div data-media-pane="none"
                                            class="text-[11px] text-ink-500 leading-[1.4] px-1">
                                            {{ __('No attachment — a plain text message.') }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- 02d Footer -->
                            <div class="sec px-5 py-4 border-b border-paper-200">
                                <div class="flex items-center gap-2.5 mb-3">
                                    <span
                                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">D</span>
                                    <span
                                        class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Footer') }}</span>
                                    <span
                                        class="font-mono text-[10px] text-ink-500">{{ __('optional / max 60') }}</span>
                                </div>
                                <input id="footer" name="custom_footer" type="text"
                                    value="{{ old('custom_footer') }}" maxlength="60"
                                    placeholder="{{ __('e.g. Reply STOP to opt out') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Plain text under the body. No variables.') }}</div>
                            </div>

                            <!-- 02e Buttons -->
                            <div class="sec px-5 py-4" data-custom-buttons>
                                <div class="flex items-center gap-2.5 mb-3">
                                    <span
                                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">E</span>
                                    <span
                                        class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Buttons') }}</span>
                                    <span
                                        class="font-mono text-[10px] text-ink-500">{{ __('optional / up to 3') }}</span>
                                </div>
                                <div class="seg inline-flex max-w-full overflow-x-auto p-[3px] rounded-full bg-paper-50 border border-paper-200 gap-0.5 mb-3"
                                    id="cc-btn-type">
                                    <span
                                        class="seg-btn shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-medium text-ink-600 cursor-pointer transition whitespace-nowrap hover:text-ink-900 [&.active]:bg-ink-900 [&.active]:text-paper-0 active"
                                        data-bt="cta">{{ __('Call to action') }}</span>
                                    <span
                                        class="seg-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-medium text-ink-600 cursor-pointer transition whitespace-nowrap hover:text-ink-900 [&.active]:bg-ink-900 [&.active]:text-paper-0"
                                        data-bt="reply">{{ __('Quick reply') }}</span>
                                    <span
                                        class="seg-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-medium text-ink-600 cursor-pointer transition whitespace-nowrap hover:text-ink-900 [&.active]:bg-ink-900 [&.active]:text-paper-0"
                                        data-bt="mix">{{ __('Mix') }}</span>
                                </div>
                                <div id="cc-btn-list" class="space-y-2">
                                    {{-- Seed CTA row. button index 0 = the original single-button
 field the send path already read. Each CTA row now carries
 a KIND dropdown (Visit website / Copy code / Call phone) that
 submits custom_buttons[i][type]; the matching value lands in
 [url] (visit_website) or [value] (copy_code / call_phone).
 mergeButtonsFooter() reads exactly type/text/value/url. --}}
                                    @php $oldType0 = old('custom_buttons.0.type', 'visit_website'); @endphp
                                    <div class="cc-btn-row grid grid-cols-[130px_1fr_1fr_28px] gap-1.5 items-center"
                                        data-kind="cta">
                                        <select name="custom_buttons[0][type]"
                                            class="cc-cta-type w-full px-2 py-2 border border-paper-200 rounded-lg bg-white text-[12px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                            <option value="visit_website" @selected($oldType0 === 'visit_website')>
                                                {{ __('Visit website') }}</option>
                                            <option value="copy_code" @selected($oldType0 === 'copy_code')>
                                                {{ __('Copy code') }}</option>
                                            <option value="call_phone" @selected($oldType0 === 'call_phone')>
                                                {{ __('Call phone') }}</option>
                                        </select>
                                        <input type="text" name="custom_buttons[0][text]"
                                            value="{{ old('custom_buttons.0.text') }}" maxlength="25"
                                            placeholder="{{ __('Button text') }}"
                                            class="cc-cta-text w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                        <input type="text" name="custom_buttons[0][url]"
                                            value="{{ old('custom_buttons.0.url') }}" placeholder="https://..."
                                            class="cc-cta-url w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 {{ $oldType0 === 'visit_website' ? '' : 'hidden' }}">
                                        <input type="text" name="custom_buttons[0][value]"
                                            value="{{ old('custom_buttons.0.value') }}"
                                            placeholder="{{ $oldType0 === 'call_phone' ? __('Number with country code') : 'PROMO50' }}"
                                            class="cc-cta-value w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 {{ $oldType0 === 'visit_website' ? 'hidden' : '' }}">
                                        <span
                                            class="w-7 h-7 rounded-[7px] inline-flex items-center justify-center text-ink-500 cursor-pointer transition hover:bg-[#FFEDE8] hover:text-accent-coral"
                                            data-cc-remove title="{{ __('Remove') }}"><svg viewBox="0 0 16 16"
                                                class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                stroke-width="1.8">
                                                <path d="M4 4l8 8M12 4l-8 8" />
                                            </svg></span>
                                    </div>
                                </div>
                                <button type="button" id="cc-btn-add"
                                    class="mt-2.5 inline-flex items-center gap-1.5 text-[12px] font-medium text-wa-deep hover:underline">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M8 3v10M3 8h10" />
                                    </svg>
                                    <span data-cc-add-label>{{ __('Add CTA button') }}</span>
                                </button>
                            </div>
                        </div>

                        {{-- ==== TEMPLATE surface ==== --}}
                        @php
                            $tplRequiresApproved = (bool) ($requiresApprovedTemplates ?? true);
                            $tplIntro = $tplRequiresApproved
                                ? 'Pick an approved Meta template. Per-contact variable values are filled at send time from contact attributes.'
                                : 'Pick a template - Unofficial API is active, so any template works (Meta approval not required). Variables are filled at send time from contact attributes.';
                            $tplLabel = $tplRequiresApproved ? 'Approved template' : 'Template';
                        @endphp
                        <div data-surface="template" class="hidden">
                            <div
                                class="bg-wa-bubble/40 border border-paper-200 rounded-lg p-3 text-[12px] text-ink-700 mb-3">
                                {{ $tplIntro }}
                            </div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="template-only">
                                {{ $tplLabel }} <span class="text-accent-coral">*</span>
                            </label>
                            <select id="template-only" name="template_id"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Select a template') }}</option>
                                @foreach ($templates as $t)
                                    @php
                                        $name = $t->template_name ?? ($t->name ?? 'Template #' . $t->id);
                                        $status = $t->status ?? 'pending';
                                        $tag = match ($status) {
                                            'approved' => '/ approved',
                                            'rejected' => '/ rejected',
                                            'pending', 'in_review' => '/ in review',
                                            default => '/ ' . $status,
                                        };
                                        // Each template belongs to one engine (channel). The JS filters
                                        // the list to the picked sender's engine.
                                        $tplChannel = method_exists($t, 'engineKey') ? $t->engineKey() : 'baileys';
                                        $chLabel = ['waba' => 'WABA', 'twilio' => 'Twilio', 'baileys' => 'Unofficial'][$tplChannel] ?? 'Unofficial';
                                        // Only WABA templates need Meta approval to be usable; Twilio
                                        // (Content SID) + Unofficial are usable as saved.
                                        $isUsable = $tplChannel !== 'waba' || in_array($status, ['approved', 'public'], true);
                                    @endphp
                                    @php $acct = $tplChannel === 'waba' && $t->provider ? ($t->provider->display_label ?: $t->provider->phone_number) : null; @endphp
                                    <option value="{{ $t->id }}" data-channel="{{ $tplChannel }}"
                                        data-provider-config="{{ $t->provider_config_id }}" @disabled(!$isUsable)>
                                        {{ $name }} · {{ $chLabel }}@if ($acct) · {{ $acct }}@endif {{ $tag }}</option>
                                @endforeach
                            </select>
                            {{-- Shown by JS when no template matches the picked sender's engine. --}}
                            <div data-tpl-channel-empty
                                class="hidden mt-2 rounded-lg bg-accent-amber/15 border border-accent-amber/40 px-3 py-2 text-[12px] text-[#7B5A14]">
                            </div>
                            @if ($templates->isEmpty())
                                <div
                                    class="mt-2 rounded-lg bg-paper-50 border border-paper-200 px-3 py-2 text-[12px] text-ink-700">
                                    No templates yet. <a href="{{ url('/templates/create') }}"
                                        class="text-wa-deep font-semibold hover:underline">{{ __('Create a template') }}</a>
                                    first.
                                </div>
                            @elseif ($tplRequiresApproved && $templates->where('status', 'approved')->isEmpty())
                                <div
                                    class="mt-2 rounded-lg bg-accent-amber/15 border border-accent-amber/40 px-3 py-2 text-[12px] text-[#7B5A14]">
                                    You have {{ $templates->count() }} template(s) but none are approved by Meta yet.
                                    WABA engine requires approved templates. <a href="{{ url('/templates') }}"
                                        class="font-semibold hover:underline">{{ __('View status') }}</a>.
                                </div>
                            @else
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ $tplRequiresApproved ? 'Greyed-out rows are awaiting Meta review or were rejected. Only approved templates can launch on WABA.' : 'All templates can be used with the Unofficial API.' }}
                                </div>
                            @endif

                            {{-- A/B test — Variant B template. Shown by JS only when the
                                 A/B toggle is on. Variant A is the template selected above;
                                 the engine-filter JS hides options that don't match the
                                 picked sender (so WABA/Twilio only show their own templates). --}}
                            <div data-ab-b class="hidden mt-3 border-t border-dashed border-paper-300 pt-3">
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="template_id_b">
                                    {{ __('Variant B template') }}
                                    <span class="text-ink-400 font-normal">{{ __('(A/B test — Variant A is selected above)') }}</span>
                                </label>
                                <select id="template_id_b" name="template_id_b"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="">{{ __('Select Variant B template') }}</option>
                                    @foreach ($templates as $t)
                                        @php
                                            $nameB = $t->template_name ?? ($t->name ?? 'Template #' . $t->id);
                                            $statusB = $t->status ?? 'pending';
                                            $tagB = match ($statusB) {
                                                'approved' => '/ approved',
                                                'rejected' => '/ rejected',
                                                'pending', 'in_review' => '/ in review',
                                                default => '/ ' . $statusB,
                                            };
                                            $chB = method_exists($t, 'engineKey') ? $t->engineKey() : 'baileys';
                                            $chLabelB = ['waba' => 'WABA', 'twilio' => 'Twilio', 'baileys' => 'Unofficial'][$chB] ?? 'Unofficial';
                                            $usableB = $chB !== 'waba' || in_array($statusB, ['approved', 'public'], true);
                                        @endphp
                                        <option value="{{ $t->id }}" data-channel="{{ $chB }}" @disabled(!$usableB)>
                                            {{ $nameB }} · {{ $chLabelB }} {{ $tagB }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- The selected template's full body / header / footer /
 buttons populate the LIVE PREVIEW pane on the right.
 No second preview lives inside the form column. --}}

                            {{-- Send-time mapping: edit this send's variable values,
 header media, and button links WITHOUT editing the template.
 Renders itself once a template is picked; posts
 `template_overrides`. --}}
                            <x-template-live-mapping :templates="$templates" target="#template-only" />

                            {{-- VARIANT B's own mapping. A/B carries two templates but
                                 used to carry only one override blob, so B was sent with
                                 A's mapping against B's variables — blank or wrong fields.
                                 This panel posts `template_overrides_b`; the send loop
                                 picks whichever blob matches the recipient's variant.
                                 Tagged [data-ab-b] so it reveals with the other Variant B
                                 blocks via the existing syncAbSplit() handler — no new JS,
                                 and it can't drift out of sync with the B template picker
                                 sitting right above it. --}}
                            <div data-ab-b class="hidden mt-4 pt-4 border-t border-dashed border-paper-200">
                                <div class="text-[11.5px] font-semibold text-ink-700 mb-1">
                                    {{ __('Fill in this send — Variant B') }}
                                </div>
                                <div class="text-[10.5px] text-ink-500 mb-2">
                                    {{ __('Variant B uses its own template, so it gets its own values. Leave blank to send Variant B with no overrides.') }}
                                </div>
                                <x-template-live-mapping :templates="$templates" target="#template_id_b"
                                    field="template_overrides_b" />
                            </div>
                        </div>

                        {{-- FLOW surface — campaign type = flow renders here on step 2 (Compose). --}}
                        <div data-surface="flow" class="hidden">
 <div class="bg-wa-bubble/40 border border-paper-200 rounded-lg p-3 text-[12px] text-ink-700 mb-3">
 {{ __("Run a saved flow for every recipient. The flow's first message is the campaign trigger; downstream nodes follow each contact's replies.") }}
 </div>
 <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="flow-only">{{ __('Flow') }} <span class="text-accent-coral">*</span></label>
 <div class="flex items-stretch gap-2">
 <select id="flow-only" name="flow_id" class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
 <option value="">{{ __('Select a flow') }}</option>
 @foreach ($flows ?? [] as $f)
 @php
 $tag = $f->is_published ? '/ live' : '/ draft';
 @endphp
 <option value="{{ $f->id }}" @disabled(!$f->is_published)>{{ $f->flow_name ?? ('Flow #' . $f->id) }} {{ $tag }}</option>
 @endforeach
 </select>
 <a id="flow-test-btn" href="#" target="_blank" class="hidden px-4 py-2 rounded-lg bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold inline-flex items-center gap-2">
 <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 3l8 5-8 5z"/></svg>
 Test flow
 </a>
 </div>
 @if (empty($flows) || $flows->isEmpty())
 <div class="mt-2 rounded-lg bg-paper-50 border border-paper-200 px-3 py-2 text-[12px] text-ink-700">
 No flows yet. <a href="{{ url('/flows/builder') }}" class="text-wa-deep font-semibold hover:underline">{{ __('Create a flow') }}</a> in the builder first.
 </div>
 @elseif ($flows->where('is_published', true)->isEmpty())
 <div class="mt-2 rounded-lg bg-accent-amber/15 border border-accent-amber/40 px-3 py-2 text-[12px] text-[#7B5A14]">
 You have {{ $flows->count() }} flow(s) but none are published yet. <a href="{{ url('/flows') }}" class="font-semibold hover:underline">{{ __('Open the flow list') }}</a> and publish one to use it here.
 </div>
 @else
 <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Greyed-out flows are still drafts.') }} <b>Test flow</b> opens the flow builder's test runner in a new tab.</div>
 @endif

 <div id="flow-preview" class="hidden mt-4">
 <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Selected flow') }}</div>
 <div class="border border-paper-200 rounded-2xl bg-paper-50/60 p-4 max-w-md">
 <div class="flex items-center gap-2.5">
 <span class="w-8 h-8 rounded-md text-paper-0 grid place-items-center text-[10.5px] font-semibold bg-wa-deep">FL</span>
 <div class="min-w-0">
 <div id="flow-preview-name" class="font-semibold text-[13px] text-ink-900 truncate">/</div>
 <div id="flow-preview-meta" class="font-mono text-[10.5px] text-ink-500 truncate">/</div>
 </div>
 </div>
 <div id="flow-preview-trigger" class="mt-3 text-[12px] text-ink-700"></div>
 </div>
 </div>

 {{-- A/B test — Variant B flow. Shown by JS only when the A/B toggle
      is on (the global JS handler toggles every [data-ab-b] wrapper).
      Variant A is the flow selected above; recipients are split per
      the ab_split slider and routed to flow_id vs flow_id_b on send. --}}
 <div data-ab-b class="hidden mt-3 border-t border-dashed border-paper-300 pt-3">
     <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="flow_id_b">
         {{ __('Variant B flow') }}
         <span class="text-ink-400 font-normal">{{ __('(A/B test — Variant A is selected above)') }}</span>
     </label>
     <select id="flow_id_b" name="flow_id_b"
             class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
         <option value="">{{ __('Select Variant B flow') }}</option>
         @foreach ($flows ?? [] as $f)
             @php $tagB = $f->is_published ? '/ live' : '/ draft'; @endphp
             <option value="{{ $f->id }}" @selected((int) old('flow_id_b') === (int) $f->id) @disabled(!$f->is_published)>{{ $f->flow_name ?? ('Flow #' . $f->id) }} {{ $tagB }}</option>
         @endforeach
     </select>
     <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Recipients in the Variant B half run this flow instead of the Variant A one above.') }}</div>
 </div>
 </div>
                    </div>

                    <div class="step-pane hidden" data-step="3">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Recipients') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('audience') }}</span>
                        </div>

                        {{-- Recipient mode picker — three radios that act as tabs.
 Each radio carries a `value` so the form remembers the
 choice, and the JS shows / hides the matching pane below
 so the user only sees inputs for the active mode. --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4" id="recipient-mode-tabs">
                            <label data-mode-tab="groups"
                                class="recipient-mode-tile border border-wa-deep bg-[#F0F8F6] rounded-2xl p-4 cursor-pointer">
                                <input class="sr-only" type="radio" name="recipient_mode" value="groups" checked>
                                <div class="font-serif text-[18px] leading-tight">{{ __('Contact groups') }}</div>
                                <p class="mt-1.5 text-[12px] text-ink-500">{{ __('Use saved segments and tags.') }}
                                </p>
                            </label>
                            <label data-mode-tab="csv"
                                class="recipient-mode-tile border border-paper-200 rounded-2xl p-4 cursor-pointer hover:bg-paper-50">
                                <input class="sr-only" type="radio" name="recipient_mode" value="csv">
                                <div class="font-serif text-[18px] leading-tight">{{ __('Upload CSV') }}</div>
                                <p class="mt-1.5 text-[12px] text-ink-500">{{ __('Import numbers for this queue.') }}
                                </p>
                            </label>
                            <label data-mode-tab="manual"
                                class="recipient-mode-tile border border-paper-200 rounded-2xl p-4 cursor-pointer hover:bg-paper-50">
                                <input class="sr-only" type="radio" name="recipient_mode" value="manual">
                                <div class="font-serif text-[18px] leading-tight">{{ __('Manual numbers') }}</div>
                                <p class="mt-1.5 text-[12px] text-ink-500">{{ __('Paste one number per line.') }}</p>
                            </label>
                        </div>

                        {{-- Pane: Contact groups + individual contacts checkboxes --}}
                        <div data-mode-pane="groups">

                            {{-- Tag audience. The tile above has always PROMISED
 "saved segments and tags", but only groups were ever
 rendered — so there was no way to send to a tag at all.
 Ticking a tag adds every contact carrying it (resolved
 workspace-scoped through the contact_tag pivot). --}}
                            @if (!empty($tags) && count($tags))
                                <div class="mb-4">
                                    <div class="text-[11.5px] font-semibold text-ink-700 mb-2">{{ __('Send to tags') }}
                                        <span class="text-ink-400 font-normal">{{ __('(everyone carrying the tag)') }}</span>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($tags as $tg)
                                            <label
                                                class="inline-flex items-center gap-2 border border-paper-200 rounded-full pl-2 pr-3 py-1.5 cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-[#F0F8F6]">
                                                <input type="checkbox" name="tags[]" value="{{ $tg->id }}"
                                                    class="accent-wa-deep"
                                                    @checked(in_array((string) $tg->id, (array) old('tags', []), true))>
                                                <span class="w-[7px] h-[7px] rounded-full shrink-0"
                                                    style="background: {{ $tg->color ?: 'var(--color-wa-deep)' }}"></span>
                                                <span class="text-[12px] font-medium">{{ $tg->name }}</span>
                                                <span
                                                    class="text-[10px] font-mono text-ink-500">{{ $tagCounts[$tg->id] ?? 0 }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                                @forelse ($groups as $g)
                                    @php $memberCount = $groupCounts[$g->id] ?? 0; @endphp
                                    <label
                                        class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                                        <span><span
                                                class="block text-[12.5px] font-semibold">{{ $g->user_group }}</span><span
                                                class="block text-[10.5px] text-ink-500">{{ number_format($memberCount) }}
                                                {{ __('contacts') }}</span></span>
                                        <input name="groups[]" value="{{ $g->id }}"
                                            data-recipient-count="{{ $memberCount }}"
                                            class="w-4 h-4 accent-wa-deep" type="checkbox">
                                    </label>
                                @empty
                                    <div
                                        class="sm:col-span-2 border border-dashed border-paper-200 rounded-lg p-4 text-center text-[12px] text-ink-500">
                                        {{ __('No contact groups yet. Create one from the Contacts page first.') }}
                                    </div>
                                @endforelse
                            </div>
                            @if ($contacts->isNotEmpty())
                                <div class="mb-2">
                                    <label
                                        class="text-[11.5px] font-semibold text-ink-700 mb-2 block">{{ __('Or pick individual contacts') }}</label>
                                    <div
                                        class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-[180px] overflow-y-auto border border-paper-200 rounded-lg p-2 bg-paper-50/40">
                                        @foreach ($contacts as $contact)
                                            @php
                                                // Auto-captured numbers (manual/CSV) are saved with a
                                                // "Recipient · 1234" placeholder name — useless in the
                                                // picker. Detect it and show the FULL number instead;
                                                // real names show name + number so the operator always
                                                // sees exactly who they're sending to.
                                                $rcpName = trim((string) $contact->name);
                                                $rcpIsPlaceholder = $rcpName === ''
                                                    || \Illuminate\Support\Str::startsWith($rcpName, ['Recipient ·', 'Recipient·']);
                                                $rcpCc  = preg_replace('/\D+/', '', (string) ($contact->country_code ?? ''));
                                                $rcpMob = preg_replace('/\D+/', '', (string) ($contact->mobile ?? ''));
                                                $rcpPhone = $rcpMob === ''
                                                    ? ''
                                                    : (($rcpCc !== '' && strpos($rcpMob, $rcpCc) !== 0) ? $rcpCc . $rcpMob : $rcpMob);
                                            @endphp
                                            <label
                                                class="flex items-center gap-2 px-2 py-1 rounded hover:bg-white cursor-pointer"
                                                title="{{ $rcpIsPlaceholder ? $rcpPhone : trim($rcpName . ' ' . $rcpPhone) }}">
                                                <input type="checkbox" name="recipients[]"
                                                    value="{{ $contact->id }}" class="w-3.5 h-3.5 accent-wa-deep">
                                                <span class="text-[12px] text-ink-800 truncate">
                                                    @if ($rcpIsPlaceholder)
                                                        <span class="font-mono">{{ $rcpPhone !== '' ? $rcpPhone : __('Unknown') }}</span>
                                                    @else
                                                        {{ $rcpName }}@if ($rcpPhone !== '')<span class="text-ink-400 font-mono text-[10px] ml-1">{{ $rcpPhone }}</span>@endif
                                                    @endif
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Pane: CSV upload only --}}
                        <div data-mode-pane="csv" class="hidden">
                            <label
                                class="border-2 border-dashed border-paper-200 rounded-lg p-6 bg-paper-50/40 hover:border-wa-deep cursor-pointer block text-center">
                                <input class="sr-only" type="file" name="csv_file" accept=".csv,text/csv"
                                    id="csv-file-input">
                                <span
                                    class="w-12 h-12 rounded-lg bg-wa-bubble text-wa-deep grid place-items-center mb-3 mx-auto">
                                    <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path d="M8 11V3M5 6l3-3 3 3" />
                                        <path d="M3 12.5h10" />
                                    </svg>
                                </span>
                                <span
                                    class="block text-[14px] font-semibold">{{ __('Click to choose a CSV file') }}</span>
                                <span class="block text-[11.5px] text-ink-500 mt-1">{{ __('Required columns:') }}
                                    <code>name</code> and <code>phone</code>. Country code required.</span>
                                <span id="csv-filename"
                                    class="block text-[11px] text-wa-deep font-mono mt-2 truncate"></span>
                            </label>
                            <div class="mt-2 text-center">
                                <a href="{{ route('user.wa-campaigns.sample-csv') }}"
                                    class="inline-flex items-center gap-1.5 text-[11.5px] font-semibold text-wa-deep hover:text-wa-teal">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path d="M8 2v8M5 7l3 3 3-3M3 13h10" />
                                    </svg>
                                    {{ __('Download sample CSV') }}
                                </a>
                            </div>
                        </div>

                        {{-- Pane: Manual numbers textarea only --}}
                        <div data-mode-pane="manual" class="hidden">
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                for="manual-numbers">{{ __('Manual numbers') }}</label>
                            <textarea id="manual-numbers" name="manual_numbers" rows="8"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] leading-relaxed focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('One number per line — include the country code, no plus sign') }}"></textarea>
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('One per line, comma, or semicolon. Country code required (with or without') }}
                                <code>+</code>).</div>
                        </div>
                    </div>

                    <div class="step-pane hidden" data-step="4">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Schedule & limits') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('queue') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                            <label
                                class="schedule-tile border border-wa-deep bg-[#F0F8F6] rounded-2xl p-4 cursor-pointer"
                                data-schedule="Send now">
                                <input class="sr-only" type="radio" name="schedule_type" value="now" checked>
                                <div class="font-serif text-[18px] leading-tight">{{ __('Send now') }}</div>
                                <p class="mt-1.5 text-[12px] text-ink-500">{{ __('Launch after validation.') }}</p>
                            </label>
                            <label
                                class="schedule-tile border border-paper-200 rounded-2xl p-4 cursor-pointer hover:bg-paper-50"
                                data-schedule="Scheduled">
                                <input class="sr-only" type="radio" name="schedule_type" value="scheduled">
                                <div class="font-serif text-[18px] leading-tight">{{ __('Schedule later') }}</div>
                                <p class="mt-1.5 text-[12px] text-ink-500">{{ __('Pick date and time.') }}</p>
                            </label>
                            <label
                                class="schedule-tile border border-paper-200 rounded-2xl p-4 cursor-pointer hover:bg-paper-50"
                                data-schedule="Recurring">
                                <input class="sr-only" type="radio" name="schedule_type" value="recurring">
                                <div class="font-serif text-[18px] leading-tight">{{ __('Recurring') }}</div>
                                <p class="mt-1.5 text-[12px] text-ink-500">{{ __('Repeat on a schedule.') }}</p>
                            </label>
                        </div>

                        <div data-schedule-field class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="send-date">{{ __('Send date') }}</label>
                                <input id="send-date" name="send_date" type="date"
                                    value="{{ old('send_date', now()->addDay()->toDateString()) }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="send-time">{{ __('Send time') }}</label>
                                <input id="send-time" name="send_time" type="time"
                                    value="{{ old('send_time', '09:30') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="timezone">{{ __('Timezone') }}</label>
                                <select id="timezone" name="timezone"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    @php
                                        $userTz = old(
                                            'timezone',
                                            optional(auth()->user()?->currentWorkspace)->timezone ?: 'Asia/Kolkata',
                                        );
                                        try {
                                            $tzList = \DateTimeZone::listIdentifiers();
                                        } catch (\Throwable $e) {
                                            $tzList = [
                                                'UTC',
                                                'Asia/Kolkata',
                                                'Asia/Dubai',
                                                'Europe/London',
                                                'America/New_York',
                                            ];
                                        }
                                    @endphp
                                    @foreach ($tzList as $tz)
                                        <option value="{{ $tz }}"
                                            {{ $tz === $userTz ? 'selected' : '' }}>{{ $tz }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Optional per-campaign END DATE (auto-stop). Applies to ANY
                             campaign type; blank = the platform default auto-end. --}}
                        <div class="mb-4">
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="expires-at">
                                {{ __('Auto-end date') }} <span class="text-ink-400 font-normal">({{ __('optional') }})</span>
                            </label>
                            <input id="expires-at" name="expires_at" type="datetime-local"
                                value="{{ old('expires_at') }}"
                                class="w-full sm:max-w-[280px] px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('Stop sending after this date & time (in the campaign timezone). Leave blank to use the platform default.') }}
                            </div>
                        </div>

                        {{-- Recurring cadence — only consumed when schedule type is Recurring.
                             Tagged data-recurring-field (NOT data-schedule-field) so it shows
                             ONLY for "Recurring", never for a one-off "Schedule later". --}}
                        <div data-recurring-field class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="repeat-interval">{{ __('Repeat') }} <span
                                        class="text-ink-400 font-normal">({{ __('recurring only') }})</span></label>
                                <select id="repeat-interval" name="repeat_interval"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="weekly" @selected(old('repeat_interval', 'weekly') === 'weekly')>{{ __('Every week') }}
                                    </option>
                                    <option value="daily" @selected(old('repeat_interval') === 'daily')>{{ __('Every day') }}
                                    </option>
                                    <option value="monthly" @selected(old('repeat_interval') === 'monthly')>{{ __('Every month') }}
                                    </option>
                                </select>
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('How often a recurring campaign re-sends.') }}</div>
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="repeat-until">{{ __('Repeat until') }} <span
                                        class="text-ink-400 font-normal">({{ __('optional') }})</span></label>
                                <input id="repeat-until" name="repeat_until" type="date"
                                    value="{{ old('repeat_until') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Leave blank to repeat forever.') }}</div>
                            </div>
                        </div>

                        {{-- Smart delivery (anti-ban). Always visible — the paced loop runs
                             for "Send now" too. Every field optional: blank = the platform
                             default pacing set by the admin. Strongly recommended for 1000+
                             recipients on the Unofficial API. --}}
                        @php
                            // Effective timezone for the active-hours window — the SAME tz the
                            // schedule step submits (defaults to the workspace tz), so the hours
                            // are always interpreted locally, never silently in UTC.
                            $smartTz = old('timezone',
                                optional(auth()->user()?->currentWorkspace)->timezone
                                    ?: (config('app.timezone') ?: 'Asia/Kolkata'));
                        @endphp
                        <div class="rounded-xl border border-paper-200 bg-paper-50/40 p-4">
                            <div class="flex items-center gap-2 flex-wrap">
                                <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.5">
                                    <path d="M8 1.5 2.5 4v3.5c0 3.4 2.3 5.6 5.5 7 3.2-1.4 5.5-3.6 5.5-7V4L8 1.5Z" />
                                    <path d="M5.8 8.1 7.3 9.6 10.4 6.4" />
                                </svg>
                                <span class="font-serif text-[15px] leading-none text-ink-900">{{ __('Smart delivery') }}</span>
                                <span class="font-mono text-[10px] text-ink-500">{{ __('anti-ban · optional') }}</span>
                                <span class="ml-auto inline-flex items-center gap-1 font-mono text-[10px] text-wa-deep bg-wa-bubble/60 border border-wa-green/30 rounded-full px-2 py-0.5">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <circle cx="8" cy="8" r="6" /><path d="M8 5v3l2 1" />
                                    </svg>
                                    {{ __('Times in') }} <span data-smart-tz>{{ $smartTz }}</span>
                                </span>
                            </div>
                            <div class="text-[11px] text-ink-500 mt-1 mb-3">
                                {{ __('Leave blank to use the workspace default pacing. These rules space out sending so a large list looks human and stays under WhatsApp\'s ban radar.') }}
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Delay between messages') }}</label>
                                    <div class="flex items-center gap-2">
                                        <input type="number" name="throttle_min_sec" min="0" max="3600"
                                            value="{{ old('throttle_min_sec') }}" placeholder="{{ __('min') }}"
                                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                        <span class="text-ink-400 text-[12px]">&ndash;</span>
                                        <input type="number" name="throttle_max_sec" min="0" max="3600"
                                            value="{{ old('throttle_max_sec') }}" placeholder="{{ __('max') }}"
                                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                        <span class="text-[11px] text-ink-500 shrink-0">{{ __('sec') }}</span>
                                    </div>
                                    <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Random wait per recipient, e.g. 8–25s. A range beats a fixed gap.') }}</div>
                                </div>
                                <div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                        for="daily-limit">{{ __('Daily send limit') }}</label>
                                    <input id="daily-limit" type="number" name="daily_limit" min="1" max="100000"
                                        value="{{ old('daily_limit') }}" placeholder="{{ __('e.g. 800') }}"
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Max per day — extra recipients auto-resume the next day.') }}</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                        for="batch-size">{{ __('Batch size') }}</label>
                                    <input id="batch-size" type="number" name="batch_size" min="1" max="10000"
                                        value="{{ old('batch_size') }}" placeholder="{{ __('e.g. 50') }}"
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Messages per batch before a longer pause.') }}</div>
                                </div>
                                <div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                        for="batch-pause">{{ __('Pause between batches') }}</label>
                                    <input id="batch-pause" type="number" name="batch_pause_min" min="0" max="1440"
                                        value="{{ old('batch_pause_min') }}" placeholder="{{ __('minutes') }}"
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Minutes to rest after each batch.') }}</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                        for="window-start">{{ __('Active hours — from') }}</label>
                                    <input id="window-start" type="time" name="window_start"
                                        value="{{ old('window_start') }}"
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </div>
                                <div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                        for="window-end">{{ __('Active hours — to') }}</label>
                                    <input id="window-end" type="time" name="window_end"
                                        value="{{ old('window_end') }}"
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </div>
                            </div>
                            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Only send within these hours, in') }} <b data-smart-tz>{{ $smartTz }}</b> {{ __('(the campaign timezone set in the Schedule step). Outside the window the run waits for the next opening. Leave both blank to send any time.') }}</div>
                        </div>

                        <div data-schedule-now-note
                            class="hidden bg-wa-bubble/40 border border-wa-green/30 rounded-lg p-4 text-[12.5px] text-ink-700 leading-relaxed">
                            <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.7">
                                    <path d="M8 2v6l4 2" />
                                    <circle cx="8" cy="8" r="6" />
                                </svg>
                                {{ __('Sending immediately') }}
                            </div>
                            The campaign launches as soon as you click <b>Launch campaign</b>. No date, time, or batch
                            settings needed / those are only for scheduled and recurring runs.
                        </div>
                    </div>

                    <div class="step-pane hidden" data-step="5">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">05</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Review') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('confirm') }}</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
                            <div class="border border-paper-200 rounded-lg p-3 bg-paper-50/40">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Device') }}</div>
                                <div id="review-device" class="font-serif text-[20px] leading-tight mt-1">—</div>
                            </div>
                            <div class="border border-paper-200 rounded-lg p-3 bg-paper-50/40">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Type') }}</div>
                                <div id="review-type" class="font-serif text-[20px] leading-tight mt-1">—</div>
                            </div>
                            <div class="border border-paper-200 rounded-lg p-3 bg-paper-50/40">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Recipients') }}</div>
                                <div id="review-recipients" class="font-serif text-[20px] leading-tight mt-1">0</div>
                            </div>
                            <div class="border border-paper-200 rounded-lg p-3 bg-paper-50/40">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Schedule') }}</div>
                                <div id="review-schedule" class="font-serif text-[20px] leading-tight mt-1">—</div>
                            </div>
                        </div>

                        {{-- Billing is plan-first (0 = unlimited), so the old
 "Cost and health" credit-cost card was removed — it showed
 a misleading "short by N credits, top up wallet" warning.
 A plain recipients summary stays so the operator still sees
 where the audience came from. --}}
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                            <div id="review-checklist"
                                class="border border-wa-green/30 rounded-2xl p-4 bg-wa-bubble/40">
                                <div class="font-serif text-[20px] leading-tight">{{ __('Validation checklist') }}
                                </div>
                                <div class="mt-3 space-y-2 text-[12.5px]">
                                    <div id="check-device" class="flex items-center gap-2 text-ink-500"><span
                                            class="w-5 h-5 rounded-full bg-paper-300 text-paper-0 grid place-items-center text-[10px]">·</span>
                                        Pick a connected device</div>
                                    <div id="check-content" class="flex items-center gap-2 text-ink-500"><span
                                            class="w-5 h-5 rounded-full bg-paper-300 text-paper-0 grid place-items-center text-[10px]">·</span>
                                        Add a message body or template</div>
                                    <div id="check-recipients" class="flex items-center gap-2 text-ink-500"><span
                                            class="w-5 h-5 rounded-full bg-paper-300 text-paper-0 grid place-items-center text-[10px]">·</span>
                                        Add at least one recipient</div>
                                </div>
                            </div>
                            <div class="border border-paper-200 rounded-2xl p-4 bg-paper-50/40">
                                <div class="font-serif text-[20px] leading-tight">{{ __('Recipients summary') }}
                                </div>
                                {{-- Recipient breakdown — split per source so the operator
 can see exactly where the recipient count came from. --}}
                                <div class="mt-3 rounded-lg bg-white border border-paper-200 p-3 text-[12px]">
                                    <div class="space-y-1">
                                        <div class="flex items-center justify-between"><span
                                                class="text-ink-500">{{ __('Contact groups') }}</span><b
                                                id="rev-src-groups">0</b></div>
                                        <div class="flex items-center justify-between"><span
                                                class="text-ink-500">{{ __('Individual contacts') }}</span><b
                                                id="rev-src-contacts">0</b></div>
                                        <div class="flex items-center justify-between"><span
                                                class="text-ink-500">{{ __('Manual / CSV numbers') }}</span><b
                                                id="rev-src-manual">0</b></div>
                                        <div
                                            class="flex items-center justify-between pt-1 border-t border-paper-200 mt-1.5">
                                            <span class="font-semibold">{{ __('Total recipients') }}</span><b
                                                id="rev-src-total" class="text-wa-deep">0</b></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-4 border-t border-paper-200 bg-paper-50/40 flex items-center justify-between">
                    <button id="prevBtn" type="button"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-white text-[12px] font-semibold text-ink-700 disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M10 4l-4 4 4 4" />
                        </svg>
                        Previous
                    </button>
                    <div class="font-mono text-[11px] text-ink-500">{{ __('Step') }} <span
                            id="cur-step">1</span> of 5</div>
                    <div class="flex items-center gap-2">
                        <button id="nextBtn" type="button"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">
                            Next
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M6 4l4 4-4 4" />
                            </svg>
                        </button>
                        <button id="submitBtn" type="submit" style="display:none"
                            class="hidden inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M2 8l5 5 7-9" />
                            </svg>
                            Launch campaign
                        </button>
                    </div>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="bg-white border border-paper-200 rounded-2xl shadow-card p-4 sticky top-[92px]">
                    <div class="flex items-center justify-between mb-3 px-1">
                        <span
                            class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 flex items-center gap-1.5">
                            <span
                                class="w-1.5 h-1.5 rounded-full bg-wa-green animate-pulse"></span>{{ __('Live preview') }}
                        </span>
                        <span id="preview-format"
                            class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep">{{ __('Text') }}</span>
                    </div>
                    {{-- Empty state — shown when no body / no template / no media.
 Toggled by the JS in user-wa-campaigns-create.js. --}}
                    <div id="preview-empty"
                        class="rounded-[24px] border border-paper-200 bg-paper-50/60 p-6 text-center">
                        <div
                            class="w-12 h-12 rounded-full bg-wa-bubble text-wa-deep grid place-items-center mx-auto mb-3">
                            <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M3 5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H8l-3 2v-2a2 2 0 0 1-2-2z" />
                            </svg>
                        </div>
                        <div class="font-serif text-[16px] text-ink-700">{{ __('No preview available') }}</div>
                        <div class="text-[12px] text-ink-500 mt-1 leading-relaxed">
                            {{ __('Pick a device, write your message body, or select a template — the preview lands here.') }}
                        </div>
                    </div>

                    {{-- WhatsApp phone live preview — cloned from the template
 builder's phone-frame (templates/create.blade.php #pp-card).
 IDs are kept as preview-* so the existing campaign preview JS
 (updatePreview / renderTemplatePreview) drives it unchanged. --}}
                    <div id="preview-frame"
                        class="phone-frame bg-ink-900 rounded-[24px] p-[7px] shadow-[0_12px_36px_-16px_rgba(11,31,28,0.4)] max-w-[300px] mx-auto hidden">
                        <div
                            class="phone-screen bg-wa-chat rounded-[18px] min-h-[420px] flex flex-col overflow-hidden">
                            <div
                                class="phone-bar bg-wa-deep text-paper-0 px-3 py-2 flex items-center gap-[7px] text-[11.5px]">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                                    <path d="M9 3l-4 5 4 5V9h5V7H9z" />
                                </svg>
                                <div
                                    class="w-6 h-6 rounded-full bg-wa-mint text-wa-deep flex items-center justify-center text-[9px] font-semibold">
                                    WA</div>
                                <div class="leading-tight min-w-0">
                                    <div id="preview-title" class="text-[11.5px] font-semibold truncate">
                                        {{ __('Untitled campaign') }}</div>
                                    <div id="preview-device" class="text-[9px] opacity-70 truncate">
                                        {{ __('Pick a device') }}</div>
                                </div>
                            </div>
                            <div
                                class="phone-body flex-1 p-3 bg-wa-chat [background-image:radial-gradient(rgba(7,94,84,0.06)_1px,transparent_1px)] bg-[length:14px_14px]">
                                <div
                                    class="pp-bubble bg-paper-0 rounded-[7px] rounded-tl-[2px] px-[9px] py-2 max-w-[88%] shadow-[0_1px_1px_rgba(0,0,0,0.06)] mb-[5px] text-[12px] leading-[1.4] break-words">
                                    <div id="preview-attach"
                                        class="hidden bg-[#DFF1ED] rounded-[5px] h-20 mb-[5px] flex items-center justify-center text-wa-deep text-[10.5px] font-mono">
                                    </div>
                                    <div id="preview-header"
                                        class="hidden font-semibold text-[12px] mb-[3px] text-ink-900"></div>
                                    <div id="preview-body" class="text-ink-800 whitespace-pre-wrap">
                                        {{ __('Type your message body.') }}</div>
                                    <div id="preview-footer" class="hidden text-[10.5px] text-ink-500 mt-[5px]"></div>
                                    <div class="text-[9px] text-ink-500 text-right mt-1 font-mono">--:--</div>
                                </div>
                                <div id="preview-buttons" class="max-w-[88%] flex flex-col gap-[3px] mt-[3px] hidden">
                                    <button id="preview-button" type="button"
                                        class="pp-btn bg-paper-0 rounded-[7px] px-3 py-2 text-center text-[12px] font-semibold text-wa-deep shadow-[0_1px_1px_rgba(0,0,0,0.06)]">{{ __('Button') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <div class="rounded-lg border border-paper-200 bg-paper-50/60 p-3">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Recipients') }}</div>
                            <div id="preview-recipients" class="font-serif text-[20px] leading-tight mt-1">0</div>
                        </div>
                        <div class="rounded-lg border border-paper-200 bg-paper-50/60 p-3">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Schedule') }}</div>
                            <div id="preview-schedule" class="font-serif text-[20px] leading-tight mt-1">—</div>
                        </div>
                        <div class="rounded-lg border border-paper-200 bg-paper-50/60 p-3">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Type') }}</div>
                            <div id="preview-type" class="font-serif text-[20px] leading-tight mt-1">—</div>
                        </div>
                        <div class="rounded-lg border border-paper-200 bg-paper-50/60 p-3">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">A/B</div>
                            <div id="preview-ab" class="font-serif text-[20px] leading-tight mt-1">
                                {{ __('Off') }}</div>
                        </div>
                    </div>
                </div>
            </aside>
        </form>
    </section>

    {{-- Build-with-AI modal — same overlay/panel pattern as the
 templates + meta-ads modals so the visual language stays
 consistent. Opened by #open-ai-modal in the sticky bar above.
 POST submission lives in user-wa-campaigns-create.js. --}}
    <div id="ai-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center px-4"
        style="background-color:rgba(11,31,28,0.45);">
        <div
            class="ai-modal-panel bg-paper-0 rounded-2xl shadow-soft border border-paper-200 w-full max-w-[640px] max-h-[92vh] overflow-hidden flex flex-col">
            <div class="px-5 py-4 border-b border-paper-200 flex items-start gap-3">
                <span
                    class="w-9 h-9 rounded-xl bg-wa-mint text-wa-deep inline-flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path
                            d="M8 2v3M8 11v3M2 8h3M11 8h3M4.2 4.2l2.1 2.1M9.7 9.7l2.1 2.1M11.8 4.2L9.7 6.3M6.3 9.7l-2.1 2.1" />
                    </svg>
                </span>
                <div class="flex-1 min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('WA Campaigns / AI') }}</div>
                    <div class="font-serif text-[18px] leading-tight">{{ __('Build with') }} <span
                            class="italic text-wa-deep">AI</span></div>
                    <div class="text-[11.5px] text-ink-500 mt-0.5">
                        {{ __('Draft a campaign message, footer, CTA, and quick replies in seconds.') }}</div>
                </div>
                <button type="button" id="ai-modal-close"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Close') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>
                </button>
            </div>

            <div class="px-5 py-4 overflow-y-auto flex-1">
                <div id="ai-empty"
                    class="hidden rounded-xl border border-paper-200 bg-paper-50 px-4 py-3 text-[12px] text-ink-700">
                    No AI providers are enabled. Ask an admin to add a key in
                    <span class="font-mono">/admin/api-keys</span> before this can run.
                </div>

                <div id="ai-form" class="space-y-4">
                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                            for="ai-model">{{ __('AI model') }} <span class="text-accent-coral">*</span></label>
                        <select id="ai-model"
                            class="w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <option value="">{{ __('Loading models…') }}</option>
                        </select>
                        <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Models come from') }} <span
                                class="font-mono">/admin/api-keys</span> — admin controls what's available.</div>
                    </div>

                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                            for="ai-business">{{ __('Business name') }} <span
                                class="text-accent-coral">*</span></label>
                        <input id="ai-business" type="text" maxlength="120"
                            class="w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Bloomly Florals') }}">
                    </div>

                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                            for="ai-product">{{ __('Product or service') }}</label>
                        <input id="ai-product" type="text" maxlength="255"
                            class="w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Seasonal bouquets · same-day delivery') }}">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                for="ai-goal">{{ __('Campaign goal') }}</label>
                            <select id="ai-goal"
                                class="w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Select goal') }}</option>
                                <option>{{ __('Drive a purchase') }}</option>
                                <option>{{ __('Drive a booking') }}</option>
                                <option>{{ __('Promote a sale') }}</option>
                                <option>{{ __('Re-engage cold contacts') }}</option>
                                <option>{{ __('Collect feedback') }}</option>
                                <option>{{ __('Announce a launch') }}</option>
                                <option>{{ __('Send a reminder') }}</option>
                                <option>{{ __('Festival greeting') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                for="ai-tone">{{ __('Tone') }}</label>
                            <select id="ai-tone"
                                class="w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Select tone') }}</option>
                                <option>{{ __('Friendly') }}</option>
                                <option>{{ __('Professional') }}</option>
                                <option>{{ __('Urgent') }}</option>
                                <option>{{ __('Playful') }}</option>
                                <option>{{ __('Premium') }}</option>
                                <option>{{ __('Empathetic') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                for="ai-cta-label">{{ __('CTA button label') }}</label>
                            <input id="ai-cta-label" type="text" maxlength="25"
                                class="w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('Shop now') }}">
                        </div>
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                for="ai-cta-url">{{ __('CTA destination URL') }}</label>
                            <input id="ai-cta-url" type="url" maxlength="1024"
                                class="w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="https://yourbrand.com/spring">
                        </div>
                    </div>

                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                            for="ai-audience">{{ __('Target audience') }}</label>
                        <textarea id="ai-audience" rows="2" maxlength="500"
                            class="w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 resize-y"
                            placeholder="{{ __('Repeat customers who bought a bouquet in the last 90 days') }}"></textarea>
                    </div>

                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                            for="ai-offer">{{ __('Offer / hook') }}</label>
                        <textarea id="ai-offer" rows="2" maxlength="500"
                            class="w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 resize-y"
                            placeholder="15% off spring bouquets this weekend, free delivery over $40"></textarea>
                    </div>

                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-[5px] flex items-center justify-between gap-2"
                            for="ai-prompt">
                            Custom prompt
                            <span class="font-mono text-[10px] text-ink-500">{{ __('optional / max 2000') }}</span>
                        </label>
                        <textarea id="ai-prompt" rows="4" maxlength="2000"
                            class="w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 resize-y"
                            placeholder="{{ __('Anything specific — must-have phrases, what to avoid, brand voice cues…') }}"></textarea>
                    </div>

                    <div id="ai-error"
                        class="hidden rounded-xl border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[11.5px] text-[#A1431F]">
                    </div>
                </div>
            </div>

            <div class="px-5 py-3 bg-paper-0 border-t border-paper-200 flex items-center justify-end gap-2">
                <button type="button" id="ai-modal-cancel"
                    class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</button>
                <button type="button" id="ai-generate"
                    class="btn-ai-shine relative isolate overflow-hidden px-5 py-2 rounded-full text-[12px] font-semibold flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg id="ai-generate-icon" viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path
                            d="M8 2v3M8 11v3M2 8h3M11 8h3M4.2 4.2l2.1 2.1M9.7 9.7l2.1 2.1M11.8 4.2L9.7 6.3M6.3 9.7l-2.1 2.1" />
                    </svg>
                    <svg id="ai-generate-spin" viewBox="0 0 16 16" class="w-3.5 h-3.5 hidden animate-spin"
                        fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M8 1.5a6.5 6.5 0 1 1-6.5 6.5" stroke-linecap="round" />
                    </svg>
                    <span id="ai-generate-label">{{ __('Generate campaign') }}</span>
                </button>
            </div>
        </div>
    </div>

</x-layouts.user>
