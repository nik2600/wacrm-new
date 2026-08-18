<x-layouts.user :title="__('Smart Agent Builder')" nav-key="more" page="user-ai-training-builder">

    @php
        $a = $assistant;
        $defaults = [
            'id' => $a?->id ?? null,
            'name' => $a?->name ?? '',
            'status' => $a?->status ?? 'active',
            'greeting' => $a?->greeting ?? 'Hi! How can I help today?',
            'system_prompt' =>
                $a?->system_prompt ??
                "You are a helpful website assistant. Be concise, friendly, and accurate. If you don't know something, say so and offer to connect a teammate.",
            'tone' => $a?->tone ?? 'helpful',
            'language' => $a?->language ?? 'en',
            'ai_provider' => $a?->ai_provider ?? 'openai',
            'ai_model' => $a?->ai_model ?? 'gpt-4o-mini',
            'reply_max_tokens' => $a?->reply_max_tokens ?? 400,
            'temperature' => $a ? (float) $a->temperature : 0.7,
            'fallback_message' =>
                $a?->fallback_message ?? 'A teammate will follow up shortly — thanks for your patience.',
            'handoff_enabled' => (bool) ($a?->handoff_enabled ?? true),
            'handoff_keyword' => $a?->handoff_keyword ?? 'talk to human',
            'handoff_message' => $a?->handoff_message ?? 'Sure — pulling in a teammate now.',
        ];
    @endphp

    {{-- Sticky header — same shape as /wa-campaigns/create + /chatbot-widgets/create --}}
    <div class="border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/ai-training') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to AI Training') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">AI Training /
                        {{ $mode === 'edit' ? 'Edit' : 'New' }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">
                        {{ $mode === 'edit' ? 'Edit smart' : 'Build a smart' }} <span
                            class="italic text-wa-deep">{{ __('agent') }}</span></div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span id="ait-state-pill"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 font-mono">
                    {{ $mode === 'edit' ? 'Saved' : 'Draft / unsaved' }}
                </span>
                <button id="ait-save" type="button"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Save draft') }}</button>
            </div>
        </div>
    </div>

    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6">
        <div id="ait-builder" data-mode="{{ $mode }}" data-defaults='@json($defaults)'>

            {{-- ============ MAIN CARD ============ --}}
            <div class="bg-white border border-paper-200 rounded-2xl shadow-card overflow-hidden">

                {{-- ===== Stepper ===== --}}
                <div class="px-5 py-4 border-b border-paper-200 bg-paper-50/40">
                    <div class="flex items-center" id="ait-stepper">
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="1">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-wa-deep text-wa-deep ring-4 ring-wa-deep/10">1</span>
                            <span
                                class="lab text-[11.5px] font-semibold whitespace-nowrap text-wa-deep">{{ __('Identity') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="2">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">2</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Persona') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="3">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">3</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Brain') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="4">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">4</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Safety') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 cursor-pointer" data-n="5">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">5</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Knowledge') }}</span>
                        </div>
                    </div>
                </div>

                {{-- ===== Step panes ===== --}}
                <div class="p-5">

                    {{-- STEP 1: IDENTITY --}}
                    <div class="step-pane" data-step="1">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Agent identity') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Agent display name') }}
                                    <span class="text-accent-coral">*</span></label>
                                <input data-field="name" type="text"
                                    placeholder="{{ __('e.g. Pricing concierge') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __("For your records. Visitors don't see this label.") }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Roll-out status') }}</label>
                                <select data-field="status"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="active">{{ __('Live — pick this agent in widgets') }}</option>
                                    <option value="paused">{{ __('Paused — hide from pickers') }}</option>
                                </select>
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __("Paused agents stay in the list but can't be attached to new widgets.") }}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Preferred language') }}</label>
                                <input data-field="language" type="text" placeholder="en"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 font-mono">
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __("ISO 639-1 code. Auto-matches visitor's language if different.") }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Conversational tone') }}</label>
                                <select data-field="tone"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="helpful">{{ __('Helpful · calm and on-point') }}</option>
                                    <option value="friendly">{{ __('Friendly · warm and casual') }}</option>
                                    <option value="formal">{{ __('Formal · neutral and precise') }}</option>
                                    <option value="playful">{{ __('Playful · light and witty') }}</option>
                                </select>
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Sets writing style independent of the persona prompt.') }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- STEP 2: PERSONA --}}
                    <div class="step-pane hidden" data-step="2">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Persona & opening line') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('content') }}</span>
                        </div>

                        <div class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Opening line') }}
                                <span class="text-accent-coral">*</span></label>
                            <textarea data-field="greeting" rows="2"
                                placeholder="{{ __("Hi! I'm here to help — ask me anything about pricing or plans.") }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></textarea>
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('Visitor-facing. Shown as the first bubble when chat opens.') }}</div>
                        </div>

                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Character brief (system instructions)') }}</label>
                            <textarea data-field="system_prompt" rows="9"
                                placeholder="You are the friendly support agent for Acme Inc, a 12-person hardware startup. Always answer in short sentences, never quote prices above $500 (escalate to a human instead), and politely refuse to discuss competitors."
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 font-mono"></textarea>
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __("Internal — never shown to visitors. Include do's, don'ts, escalation rules.") }}
                            </div>
                        </div>
                    </div>

                    {{-- STEP 3: BRAIN --}}
                    <div class="step-pane hidden" data-step="3">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Model & reply controls') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('brain') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Model provider') }}</label>
                                <select data-field="ai_provider"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="openai">{{ __('OpenAI · GPT family') }}</option>
                                    <option value="anthropic">{{ __('Anthropic · Claude family') }}</option>
                                    <option value="gemini">{{ __('Google · Gemini family') }}</option>
                                </select>
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Admin pre-configures the API keys — no key required from you.') }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Model name') }}</label>
                                <input data-field="ai_model" type="text" placeholder="{{ __('gpt-4o-mini') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 font-mono">
                                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Examples ·') }} <span
                                        class="font-mono">{{ __('gpt-4o-mini') }}</span>, <span
                                        class="font-mono">{{ __('claude-haiku-4-5-20251001') }}</span>, <span
                                        class="font-mono">{{ __('gemini-2.5-flash-lite') }}</span>.</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Reply length budget (tokens)') }}</label>
                                <input data-field="reply_max_tokens" type="number" min="50" max="4000"
                                    placeholder="400"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 font-mono">
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Roughly 1 token ≈ 4 chars. 400 fits a paragraph.') }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Creativity (0 = strict, 2 = wild)') }}</label>
                                <input data-field="temperature" type="number" step="0.1" min="0"
                                    max="2" placeholder="0.7"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 font-mono">
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Lower = consistent and factual. Higher = varied phrasing.') }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- STEP 4: SAFETY --}}
                    <div class="step-pane hidden" data-step="4">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Fallback & human handoff') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('safety') }}</span>
                        </div>

                        <div class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Fallback reply (used when the model errors out)') }}</label>
                            <input data-field="fallback_message" type="text"
                                placeholder="{{ __('A teammate will follow up shortly — thanks for your patience.') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('Sent verbatim if the model returns nothing or the API call fails.') }}</div>
                        </div>

                        <label
                            class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50 mb-4">
                            <span>
                                <span
                                    class="block text-[12.5px] font-semibold">{{ __('Enable handoff to a human') }}</span>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Visitor types the trigger phrase → agent stops replying, team inbox lights up.') }}</span>
                            </span>
                            <span class="relative inline-block w-[34px] h-5 shrink-0">
                                <input data-field="handoff_enabled" class="peer opacity-0 w-0 h-0" type="checkbox">
                                <span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                            </span>
                        </label>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Trigger phrase') }}</label>
                                <input data-field="handoff_keyword" type="text"
                                    placeholder="{{ __('talk to human') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Visitor types this anywhere in a message → handoff fires.') }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Handoff acknowledgement') }}</label>
                                <input data-field="handoff_message" type="text"
                                    placeholder="{{ __('Sure — pulling in a teammate now. Stay on the line.') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Final message before the agent goes silent.') }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- STEP 5: KNOWLEDGE --}}
                    <div class="step-pane hidden" data-step="5">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">05</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Train the agent') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('knowledge') }}</span>
                        </div>

                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 mb-4">
                            <button type="button" data-add-source="url"
                                class="rounded-lg border border-paper-200 hover:border-wa-deep bg-paper-50 p-3 text-left transition">
                                <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.6">
                                    <path d="M6.5 9.5l-2 2a2.5 2.5 0 1 0 3.5 3.5l2-2" />
                                    <path d="M9.5 6.5l2-2a2.5 2.5 0 1 0-3.5-3.5l-2 2" />
                                    <path d="M5 11l6-6" />
                                </svg>
                                <div class="mt-1.5 text-[12.5px] font-semibold text-ink-900">{{ __('Live URL') }}
                                </div>
                                <div class="text-[11px] text-ink-500 leading-snug">
                                    {{ __('Fetch a public page and store the text.') }}</div>
                            </button>
                            <button type="button" data-add-source="text"
                                class="rounded-lg border border-paper-200 hover:border-wa-deep bg-paper-50 p-3 text-left transition">
                                <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.6">
                                    <rect x="3" y="3" width="10" height="10" rx="1.5" />
                                    <path d="M5 6h6M5 8h6M5 10h4" />
                                </svg>
                                <div class="mt-1.5 text-[12.5px] font-semibold text-ink-900">{{ __('Free text') }}
                                </div>
                                <div class="text-[11px] text-ink-500 leading-snug">
                                    {{ __('Paste a help-doc snippet or policy.') }}</div>
                            </button>
                            <button type="button" data-add-source="qa"
                                class="rounded-lg border border-paper-200 hover:border-wa-deep bg-paper-50 p-3 text-left transition">
                                <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.6">
                                    <circle cx="8" cy="8" r="6" />
                                    <path d="M6.5 6.5a1.5 1.5 0 1 1 2.5 1.2c-.6.3-1 .7-1 1.3M8 11.2v.05" />
                                </svg>
                                <div class="mt-1.5 text-[12.5px] font-semibold text-ink-900">{{ __('Q&A pair') }}
                                </div>
                                <div class="text-[11px] text-ink-500 leading-snug">
                                    {{ __('Single question with its canonical answer.') }}</div>
                            </button>
                            <button type="button" data-add-source="file"
                                class="rounded-lg border border-paper-200 hover:border-wa-deep bg-paper-50 p-3 text-left transition">
                                <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.6">
                                    <path d="M4 2h6l2 2v10H4z" />
                                    <path d="M10 2v3h2" />
                                </svg>
                                <div class="mt-1.5 text-[12.5px] font-semibold text-ink-900">PDF, DOCX, TXT &amp; more
                                </div>
                                <div class="text-[11px] text-ink-500 leading-snug">
                                    {{ __('Upload a file — text is extracted automatically.') }}</div>
                            </button>
                        </div>

                        <div id="ait-source-add"
                            class="hidden border border-paper-200 rounded-lg p-3 bg-paper-50 space-y-2 mb-4"></div>

                        <div class="border border-paper-200 rounded-lg overflow-hidden">
                          <div class="overflow-x-auto">
                           <div class="min-w-[420px]">
                            <div
                                class="px-3 py-2 grid grid-cols-[80px_1fr_120px_70px] items-center gap-3 border-b border-paper-200 bg-paper-50 font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                <div>{{ __('Kind') }}</div>
                                <div>{{ __('Label') }}</div>
                                <div>{{ __('State') }}</div>
                                <div class="text-right pr-1">&nbsp;</div>
                            </div>
                            <div id="ait-source-rows"></div>
                           </div>
                          </div>
                        </div>
                    </div>
                </div>

                {{-- ===== Footer nav ===== --}}
                <div class="px-5 py-4 border-t border-paper-200 bg-paper-50/40 flex items-center justify-between">
                    <button id="ait-prev" type="button"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-white text-[12px] font-semibold text-ink-700 disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M10 4l-4 4 4 4" />
                        </svg>
                        Previous
                    </button>
                    <div class="font-mono text-[11px] text-ink-500">{{ __('Step') }} <span id="ait-cur">1</span>
                        of 5</div>
                    <div class="flex items-center gap-2">
                        <button id="ait-next" type="button"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">
                            Next
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M6 4l4 4-4 4" />
                            </svg>
                        </button>
                        <button id="ait-finish" type="button" style="display:none"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-wa-green hover:opacity-90 text-paper-0 text-[12px] font-semibold">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M2 8l5 5 7-9" />
                            </svg>
                            Finish &amp; close
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </section>

</x-layouts.user>
