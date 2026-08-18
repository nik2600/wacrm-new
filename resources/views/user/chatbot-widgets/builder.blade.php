<x-layouts.user :title="__('Chatbot Widget Builder')" nav-key="more" page="user-chatbot-widgets-builder">

    @php
        $w = $widget;
        $defaults = [
            'id' => $w?->id ?? null,
            'name' => $w?->name ?? '',
            'mode' => $w?->mode ?? 'ai',
            'assistant_id' => $w?->assistant_id ?? null,
            'target_whatsapp_cc' => $w?->target_whatsapp_cc ?? '',
            'target_whatsapp_number' => $w?->target_whatsapp_number ?? '',
            'prefilled_message' => $w?->prefilled_message ?? "Hi, I'd like to know more.",
            'position' => $w?->position ?? 'bottom_right',
            'button_color' => $w?->button_color ?? '#075E54',
            'button_image_url' => $w?->button_image_url ?? '',
            'header_title' => $w?->header_title ?? 'Chat with us',
            'header_bg' => $w?->header_bg ?? '#075E54',
            'header_text_color' => $w?->header_text_color ?? '#FFFFFF',
            'welcome_message' => $w?->welcome_message ?? 'Hi! How can we help today?',
            'message_bubble_color' => $w?->message_bubble_color ?? '#FFFFFF',
            'message_text_color' => $w?->message_text_color ?? '#222222',
            'body_bg_kind' => $w?->body_bg_kind ?? 'color',
            'body_bg_color' => $w?->body_bg_color ?? '#ECE5DD',
            'body_bg_image_url' => $w?->body_bg_image_url ?? '',
            'auto_open' => (bool) ($w?->auto_open ?? false),
            'button_label' => $w?->button_label ?? 'Send message',
            'action_button_bg' => $w?->action_button_bg ?? '#075E54',
            'action_button_text_color' => $w?->action_button_text_color ?? '#FFFFFF',
            'collect_name' => (bool) ($w?->collect_name ?? true),
            'collect_email' => (bool) ($w?->collect_email ?? false),
            'collect_phone' => (bool) ($w?->collect_phone ?? false),
        ];
        $embedToken = $w?->embed_token ?? null;
        $assistantsJs = $assistants->map(fn($a) => ['id' => $a->id, 'name' => $a->name]);
    @endphp

    {{-- Sticky header — same shape as /wa-campaigns/create --}}
    <div class="border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/chatbot-widgets') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to widgets') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Chatbot widgets /
                        {{ $mode === 'edit' ? 'Edit' : 'New' }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">
                        {{ $mode === 'edit' ? 'Edit' : 'Build a' }} chatbot <span
                            class="italic text-wa-deep">{{ __('widget') }}</span></div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span id="cbw-state-pill"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 font-mono">
                    {{ $mode === 'edit' ? 'Saved' : 'Draft / unsaved' }}
                </span>
                <button id="cbw-save" type="button"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Save draft') }}</button>
            </div>
        </div>
    </div>

    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6">
        <div id="cbw-builder" class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5 items-start"
            data-mode="{{ $mode }}" data-defaults='@json($defaults)'
            data-assistants='@json($assistantsJs)' data-token="{{ $embedToken }}">

            {{-- ============ MAIN CARD ============ --}}
            <div class="bg-white border border-paper-200 rounded-2xl shadow-card overflow-hidden">

                {{-- ===== Stepper ===== --}}
                <div class="px-5 py-4 border-b border-paper-200 bg-paper-50/40">
                    <div class="flex items-center" id="cbw-stepper">
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
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Look') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="3">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">3</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Voice') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 cursor-pointer" data-n="4">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">4</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Launch') }}</span>
                        </div>
                    </div>
                </div>

                {{-- ===== Step panes ===== --}}
                {{-- Cap each pane's content at ~880px so 2-column inputs render
 at a comfortable ~420px each instead of stretching way wide. --}}
                <div class="p-5">
                    {{-- STEP 1: SETUP --}}
                    <div class="step-pane" data-step="1">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Widget setup') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-1.5">{{ __('Internal name') }}
                                    <span class="text-accent-coral">*</span></label>
                                <input data-field="name" type="text"
                                    placeholder="{{ __('e.g. Pricing-page widget') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 leading-snug focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __("For your team. Visitors don't see this label.") }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-1.5">{{ __('Launcher placement') }}
                                    <span class="text-accent-coral">*</span></label>
                                <select data-field="position"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 leading-snug focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="bottom_right">{{ __('Bottom right corner') }}</option>
                                    <option value="bottom_left">{{ __('Bottom left corner') }}</option>
                                </select>
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Where the round chat bubble floats on your site.') }}</div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-2">{{ __('Conversation engine') }}
                                <span class="text-accent-coral">*</span></label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                @foreach (['ai' => ['Smart agent', 'Trained AI answers right in the browser.', 'M3 5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H8l-3 2v-2a2 2 0 0 1-2-2z'], 'whatsapp' => ['WhatsApp deep-link', 'Send the visitor into the WhatsApp app to chat.', 'M12 2C6.48 2 2 6.48 2 12c0 1.96.57 3.79 1.55 5.34L2 22l4.78-1.5'], 'both' => ['Smart + WhatsApp', 'Visitor picks — chat here or open WhatsApp.', 'M3 5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H8l-3 2v-2a2 2 0 0 1-2-2z']] as $val => $info)
                                    <label
                                        class="type-tile cursor-pointer border rounded-2xl p-4 transition {{ $defaults['mode'] === $val ? 'border-wa-deep bg-[#F0F8F6]' : 'border-paper-200' }}"
                                        data-mode-tile="{{ $val }}">
                                        <input class="sr-only" type="radio" name="cbw-mode"
                                            value="{{ $val }}" data-field="mode"
                                            {{ $defaults['mode'] === $val ? 'checked' : '' }}>
                                        <div class="flex items-start justify-between mb-3">
                                            <span
                                                class="w-10 h-10 rounded-xl {{ $val === 'ai' ? 'bg-wa-mint text-wa-deep' : ($val === 'whatsapp' ? 'bg-[#D9E5F2] text-[#13478A]' : 'bg-[#F3E9FF] text-[#5B3D8A]') }} grid place-items-center">
                                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none"
                                                    stroke="currentColor" stroke-width="1.6">
                                                    <path d="{{ $info[2] }}" />
                                                </svg>
                                            </span>
                                            <span
                                                class="type-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center transition {{ $defaults['mode'] === $val ? 'opacity-100' : 'opacity-0' }}">
                                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                    stroke="currentColor" stroke-width="2.4">
                                                    <path d="M3 8l3 3 7-8" />
                                                </svg>
                                            </span>
                                        </div>
                                        <div class="font-serif text-[18px] leading-tight">{{ $info[0] }}</div>
                                        <p class="mt-1.5 text-[12px] text-ink-500 leading-snug">{{ $info[1] }}
                                        </p>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div data-show-when-mode="ai,both" class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-1.5">{{ __('Smart agent (the brain)') }}</label>
                            <select data-field="assistant_id"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 leading-snug focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">— Pick a saved smart agent —</option>
                                @foreach ($assistants as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                                @endforeach
                            </select>
                            <div class="text-[10.5px] text-ink-500 mt-1">{{ __("Don't see one?") }} <a
                                    href="{{ url('/ai-training') }}"
                                    class="font-semibold text-wa-deep underline">{{ __('Build a smart agent') }}</a>
                                first.</div>
                        </div>

                        <div data-show-when-mode="whatsapp,both" class="mb-3">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('WhatsApp number') }}
                                <span class="text-accent-coral">*</span></label>
                            {{-- Same intl-tel-input picker /devices uses. The
 `.wa-iti-wrap` wrapper triggers the CSS sizing that
 gives the dial-code badge enough breathing room — see
 resources/css/wadesk.css. --}}
                            <div class="wa-iti-wrap">
                                <input data-field="target_whatsapp_cc" type="hidden">
                                <input data-cbw-phone type="tel" placeholder="{{ __('Your number without country code') }}" autocomplete="off"
                                    class="w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('Pick the country flag — the dial code is added automatically.') }}</div>
                        </div>
                        <div data-show-when-mode="whatsapp,both">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Pre-typed message (lands in WhatsApp)') }}</label>
                            <textarea data-field="prefilled_message" rows="2"
                                placeholder="{{ __("Hi, I'd like to know more about pricing.") }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></textarea>
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('The visitor lands in WhatsApp with this message already typed.') }}</div>
                        </div>
                    </div>

                    {{-- STEP 2: LOOK --}}
                    <div class="step-pane hidden" data-step="2">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Launcher & window styling') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('brand') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Launcher fill') }}</label>
                                <div class="flex items-center gap-2">
                                    <input data-field="button_color" type="color"
                                        class="w-10 h-10 border border-paper-200 rounded-md bg-paper-50">
                                    <input data-field="button_color" type="text"
                                        class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </div>
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Background of the round bubble.') }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Window header tint') }}</label>
                                <div class="flex items-center gap-2">
                                    <input data-field="header_bg" type="color"
                                        class="w-10 h-10 border border-paper-200 rounded-md bg-paper-50">
                                    <input data-field="header_bg" type="text"
                                        class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </div>
                                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Top strip of the chat window.') }}
                                </div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Header ink') }}</label>
                                <div class="flex items-center gap-2">
                                    <input data-field="header_text_color" type="color"
                                        class="w-10 h-10 border border-paper-200 rounded-md bg-paper-50">
                                    <input data-field="header_text_color" type="text"
                                        class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </div>
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Text/icon colour for the header.') }}</div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Window title (shown in the header)') }}</label>
                            <input data-field="header_title" type="text"
                                placeholder="{{ __('Chat with our team') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Keep under ~30 characters.') }}</div>
                        </div>

                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Custom launcher icon URL') }}
                                <span class="text-ink-500 font-normal">(optional)</span></label>
                            <input data-field="button_image_url" type="text"
                                placeholder="https://yoursite.com/logo.png"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('PNG / SVG · 80×80 px recommended · transparent background.') }}</div>
                        </div>
                    </div>

                    {{-- STEP 3: VOICE --}}
                    <div class="step-pane hidden" data-step="3">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Opening message & chat surface') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('content') }}</span>
                        </div>

                        <div class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Opening line') }}
                                <span class="text-accent-coral">*</span></label>
                            <textarea data-field="welcome_message" rows="3" placeholder="{{ __('Hi! How can we help today?') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></textarea>
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('First bubble shown when the chat opens.') }}</div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Incoming bubble fill') }}</label>
                                <div class="flex items-center gap-2">
                                    <input data-field="message_bubble_color" type="color"
                                        class="w-10 h-10 border border-paper-200 rounded-md bg-paper-50">
                                    <input data-field="message_bubble_color" type="text"
                                        class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </div>
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Colour of bubbles from your side.') }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Incoming bubble ink') }}</label>
                                <div class="flex items-center gap-2">
                                    <input data-field="message_text_color" type="color"
                                        class="w-10 h-10 border border-paper-200 rounded-md bg-paper-50">
                                    <input data-field="message_text_color" type="text"
                                        class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </div>
                                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Text colour inside bubbles.') }}
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-2 block">{{ __('Window canvas') }}</label>
                            <div class="flex gap-2 mb-2">
                                <label class="cursor-pointer flex-1">
                                    <input type="radio" name="cbw-bgkind" value="color" class="peer sr-only"
                                        data-field="body_bg_kind">
                                    <div
                                        class="border border-paper-200 rounded-lg p-2 text-[12px] font-semibold text-ink-900 hover:border-wa-deep peer-checked:border-wa-deep peer-checked:bg-wa-mint/40 transition text-center">
                                        {{ __('Solid colour') }}</div>
                                </label>
                                <label class="cursor-pointer flex-1">
                                    <input type="radio" name="cbw-bgkind" value="image" class="peer sr-only"
                                        data-field="body_bg_kind">
                                    <div
                                        class="border border-paper-200 rounded-lg p-2 text-[12px] font-semibold text-ink-900 hover:border-wa-deep peer-checked:border-wa-deep peer-checked:bg-wa-mint/40 transition text-center">
                                        {{ __('Tile image') }}</div>
                                </label>
                            </div>
                            <div data-show-when-bgkind="color" class="flex items-center gap-2">
                                <input data-field="body_bg_color" type="color"
                                    class="w-10 h-10 border border-paper-200 rounded-md bg-paper-50">
                                <input data-field="body_bg_color" type="text"
                                    class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                            <div data-show-when-bgkind="image">
                                <input data-field="body_bg_image_url" type="text"
                                    placeholder="https://yoursite.com/chat-pattern.jpg"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('A tile-able PNG works best.') }}
                                </div>
                            </div>
                        </div>

                        <label
                            class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50 mb-4">
                            <span>
                                <span
                                    class="block text-[12.5px] font-semibold">{{ __('Pop open automatically') }}</span>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Opens the chat ~1.2 s after the page loads.') }}</span>
                            </span>
                            <span class="relative inline-block w-[34px] h-5 shrink-0">
                                <input data-field="auto_open" class="peer opacity-0 w-0 h-0" type="checkbox">
                                <span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                            </span>
                        </label>

                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-2 block">{{ __('Visitor intake') }}</label>
                            <p class="text-[10.5px] text-ink-500 mb-2">
                                {{ __('Pre-chat fields shown before the chat begins. Skip everything for anonymous chat.') }}
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                @foreach (['collect_name' => 'Display name', 'collect_email' => 'Email address', 'collect_phone' => 'Phone number'] as $field => $label)
                                    <label
                                        class="flex items-center gap-2 border border-paper-200 rounded-lg p-2 cursor-pointer hover:border-wa-deep transition">
                                        <input type="checkbox" data-field="{{ $field }}"
                                            class="w-4 h-4 accent-wa-deep">
                                        <span class="text-[12.5px] text-ink-900">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- STEP 4: LAUNCH --}}
                    <div class="step-pane hidden" data-step="4">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Send-button & embed snippet') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('launch') }}</span>
                        </div>

                        <div class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Send-button label') }}</label>
                            <input data-field="button_label" type="text" placeholder="{{ __('Send message') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('Avoid "Submit". Try "Send message" or "Ask the team".') }}</div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Send-button fill') }}</label>
                                <div class="flex items-center gap-2">
                                    <input data-field="action_button_bg" type="color"
                                        class="w-10 h-10 border border-paper-200 rounded-md bg-paper-50">
                                    <input data-field="action_button_bg" type="text"
                                        class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Send-button ink') }}</label>
                                <div class="flex items-center gap-2">
                                    <input data-field="action_button_text_color" type="color"
                                        class="w-10 h-10 border border-paper-200 rounded-md bg-paper-50">
                                    <input data-field="action_button_text_color" type="text"
                                        class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </div>
                            </div>
                        </div>

                        {{-- Snippet block — revealed after the wizard hits "Launch" --}}
                        <div id="cbw-snippet-block"
                            class="hidden border border-wa-green/30 bg-wa-bubble/30 rounded-2xl p-4 mt-4">
                            <div class="font-semibold text-ink-900 text-[13px] mb-2 flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.8">
                                    <path d="M2 8l5 5L14 4" />
                                </svg>
                                Ready. Paste this just before <span class="font-mono">&lt;/body&gt;</span> on the page
                                you want the widget on.
                            </div>
                            <pre id="cbw-snippet"
                                class="bg-ink-900 text-paper-0 font-mono text-[11.5px] rounded-lg p-3 overflow-x-auto whitespace-pre-wrap leading-relaxed"></pre>
                            <div class="flex gap-2 mt-2">
                                <button type="button" id="cbw-snippet-copy"
                                    class="px-3 py-1.5 rounded-md bg-wa-deep text-paper-0 text-[11px] font-semibold hover:bg-wa-teal flex items-center gap-1.5">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                        stroke-width="1.8">
                                        <rect x="4" y="4" width="9" height="9" rx="1.5" />
                                        <path d="M3 3h8M3 3v8" />
                                    </svg>
                                    Copy to clipboard
                                </button>
                                <a id="cbw-preview-link" target="_blank"
                                    class="px-3 py-1.5 rounded-md border border-paper-200 text-[11px] font-semibold text-ink-700 hover:border-wa-deep hover:text-wa-deep flex items-center gap-1.5">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                        stroke-width="1.8">
                                        <path d="M3 13l10-10M6 3h7v7" />
                                    </svg>
                                    Open standalone preview
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===== Footer nav ===== --}}
                <div class="px-5 py-4 border-t border-paper-200 bg-paper-50/40 flex items-center justify-between">
                    <button id="cbw-prev" type="button"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-white text-[12px] font-semibold text-ink-700 disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M10 4l-4 4 4 4" />
                        </svg>
                        Previous
                    </button>
                    <div class="font-mono text-[11px] text-ink-500">{{ __('Step') }} <span id="cbw-cur">1</span>
                        of 4</div>
                    <div class="flex items-center gap-2">
                        <button id="cbw-next" type="button"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">
                            Next
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M6 4l4 4-4 4" />
                            </svg>
                        </button>
                        <button id="cbw-launch" type="button" style="display:none"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-wa-green hover:opacity-90 text-paper-0 text-[12px] font-semibold">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M2 8l5 5 7-9" />
                            </svg>
                            Launch widget
                        </button>
                    </div>
                </div>
            </div>

            {{-- ============ LIVE PREVIEW ASIDE ============ --}}
            <aside class="space-y-4">
                <div class="bg-white border border-paper-200 rounded-2xl shadow-card p-4 sticky top-[92px]">
                    <div class="flex items-center justify-between mb-3">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Live preview') }}</span>
                        <span id="cbw-prev-engine"
                            class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep">{{ __('Smart agent') }}</span>
                    </div>

                    <div class="rounded-[24px] border border-ink-900/10 bg-ink-900 p-2 shadow-soft">
                        <div class="rounded-[19px] overflow-hidden bg-paper-50 flex flex-col" style="height:420px">
                            <div id="cbw-prev-header" class="h-11 px-3 flex items-center text-[12.5px] font-semibold">
                                <span id="cbw-prev-title">{{ __('Chat with us') }}</span>
                            </div>
                            <div id="cbw-prev-body"
                                class="flex-1 p-3 overflow-y-auto space-y-2 bg-[radial-gradient(circle_at_1px_1px,rgba(7,94,84,0.09)_1px,transparent_0)] bg-[length:18px_18px]">
                                <div id="cbw-prev-welcome"
                                    class="max-w-[80%] px-3 py-2 rounded-lg text-[12px] shadow-sm"></div>
                            </div>
                            <div class="p-2.5 border-t border-paper-200 bg-paper-0">
                                <button id="cbw-prev-btn" type="button"
                                    class="w-full py-1.5 rounded-md text-[12px] font-semibold transition"></button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <div class="rounded-lg border border-paper-200 bg-paper-50/60 p-3">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Engine') }}</div>
                            <div id="cbw-prev-engine-label" class="font-serif text-[16px] leading-tight mt-1">AI</div>
                        </div>
                        <div class="rounded-lg border border-paper-200 bg-paper-50/60 p-3">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Placement') }}</div>
                            <div id="cbw-prev-placement" class="font-serif text-[16px] leading-tight mt-1">
                                {{ __('Right') }}</div>
                        </div>
                        <div class="rounded-lg border border-paper-200 bg-paper-50/60 p-3">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Auto-open') }}</div>
                            <div id="cbw-prev-autoopen" class="font-serif text-[16px] leading-tight mt-1">
                                {{ __('Off') }}</div>
                        </div>
                        <div class="rounded-lg border border-paper-200 bg-paper-50/60 p-3">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Intake') }}</div>
                            <div id="cbw-prev-intake" class="font-serif text-[16px] leading-tight mt-1">
                                {{ __('None') }}</div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </section>

</x-layouts.user>
