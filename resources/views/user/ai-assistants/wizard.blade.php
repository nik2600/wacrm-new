@php
    $a = $assistant;
    $meta = (array) ($a?->meta_json ?? []);

    // Hydration payload — never echo back BYOK key plaintext; the
    // backend only persists fresh values when the operator types
    // something new, so blank in the UI = "keep what's on file."
    $payload = [
        'id' => $a?->id,
        'name' => $a?->name ?? '',
        'persona' => (string) ($meta['persona'] ?? 'support'),
        'languages' => (array) ($meta['languages'] ?? ['en']),
        'greeting_variations' => (array) ($meta['greeting_variations'] ?? [
            $a?->greeting_text ?? 'Hi! I\'m your AI assistant. How can I help you today?',
        ]),
        'status' => $a?->status ?? 'draft',
        'is_active' => $a?->is_active ?? true,
        'ai_provider' => $a?->ai_provider ?? 'gemini',
        'ai_model' => $a?->ai_model ?? 'gemini-2.5-flash-lite',
        'ai_system_prompt' => $a?->ai_system_prompt ?? '',
        'knowledge_base_url' => $a?->knowledge_base_url ?? '',
        'natural_conciseness' => $a?->natural_conciseness ?? true,
        'personality' => (array) ($meta['personality'] ?? ['warmth' => 60, 'formality' => 50, 'pace' => 50]),
        'voice_provider' => $a?->voice_provider ?? 'elevenlabs',
        'voice_id' => $a?->voice_id ?? '',
        'stt_provider' => $a?->stt_provider ?? 'elevenlabs',
        'noise_suppression' => (bool) ($meta['noise_suppression'] ?? true),
        'record_agent' => $a?->record_agent ?? true,
        'record_user' => $a?->record_user ?? true,
        'auto_logging' => $a?->auto_logging ?? true,
        'voicemail_behavior' => (string) ($meta['voicemail_behavior'] ?? 'leave_message'),
        'human_handoff_team' => (string) ($meta['human_handoff_team'] ?? ''),
        'exit_keywords' => (array) ($a?->exit_keywords_json ?? ['bye', 'goodbye']),
        'last_greeting' => $a?->last_greeting ?? 'Thank you for calling. Goodbye!',
        'device_ids' => array_map('intval', (array) ($a?->device_ids ?? [])),
        'has_ai_key' => !empty($a?->ai_api_key_encrypted),
        'has_voice_key' => !empty($a?->voice_api_key_encrypted),
        'tools' => $tools
            ->map(
                fn($t) => [
                    'function_name' => $t->function_name,
                    'trigger_keywords' => $t->trigger_keywords_json ?? [],
                    'http_method' => $t->http_method,
                    'http_url' => $t->http_url,
                    'headers' => $t->headers_json ?? [],
                    'parameters' => $t->parameters_json ?? [],
                ],
            )
            ->values(),
    ];
@endphp

<x-layouts.user :title="$mode === 'edit' ? __('Edit Voice Agent') : __('New Voice Agent')" nav-key="more" page="user-ai-assistants-wizard">

    {{-- Sticky top bar — mirrors /wa-campaigns/create. --}}
    <div class="border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/ai-assistants') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to assistants') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Voice agents /
                        {{ $mode === 'edit' ? 'Edit' : 'New' }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">
                        {{ $mode === 'edit' ? 'Edit voice' : 'Build a voice' }} <span
                            class="italic text-wa-deep">{{ __('agent') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span data-status-badge
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 font-mono">{{ __('Draft / unsaved') }}</span>
                <button type="button" data-save-now
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save') }}</button>
            </div>
        </div>
    </div>

    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6" data-wizard-state>
        <div class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5 items-start">

            {{-- ── Main form card ───────────────────────────────────────── --}}
            <div class="bg-white border border-paper-200 rounded-2xl shadow-card overflow-hidden">

                {{-- Stepper — wa-campaigns dot/label/bar pattern --}}
                <div class="px-5 py-4 border-b border-paper-200 bg-paper-50/40">
                    <div class="flex items-center" id="stepper">
                        @foreach ([['n' => 1, 'lab' => 'Profile'], ['n' => 2, 'lab' => 'Brain'], ['n' => 3, 'lab' => 'Actions'], ['n' => 4, 'lab' => 'Speech'], ['n' => 5, 'lab' => 'Routing']] as $s)
                            <div class="step-node flex items-center gap-2.5 {{ $loop->last ? '' : 'flex-1' }} cursor-pointer"
                                data-n="{{ $s['n'] }}">
                                <span
                                    class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">{{ $s['n'] }}</span>
                                <span
                                    class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ $s['lab'] }}</span>
                                @if (!$loop->last)
                                    <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="p-5">

                    {{-- ── STEP 1 · Identity & Persona ───────────────────────── --}}
                    <div class="step-pane" data-step="1">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Agent profile') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 flex items-center gap-1 mb-1.5">{{ __('Display name') }}
                                    <span class="text-accent-coral">*</span></label>
                                <input data-wf="name" type="text" maxlength="120"
                                    placeholder="{{ __('e.g. Riley · Acme Support') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Shown in call logs and transcripts.') }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Spoken languages') }}</label>
                                <div class="flex flex-wrap gap-1.5 px-3 py-2 border border-paper-200 rounded-lg bg-white min-h-[40px]"
                                    data-lang-chips></div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-2 block">{{ __('Choose a starting style — fine-tune the prompt in the next step') }}</label>
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2" data-persona-cards>
                                @foreach ([['k' => 'support', 'l' => 'Support', 'd' => 'Patient, helpful, resolves issues calmly.', 'icon' => 'M8 1a3 3 0 0 0-3 3v4a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z'], ['k' => 'sales', 'l' => 'Sales', 'd' => 'Friendly, persuasive, qualifies before pitching.', 'icon' => 'M3 9l5-5 4 4 1-1v3l-3-0M3 13l5-5'], ['k' => 'scheduler', 'l' => 'Scheduler', 'd' => 'Crisp, calendar-aware, books slots end-to-end.', 'icon' => 'M3 4h10v9H3zM3 6h10M5 2v3M11 2v3'], ['k' => 'concierge', 'l' => 'Concierge', 'd' => 'Warm, contextual, remembers prior callers.', 'icon' => 'M3 13a5 5 0 0 1 10 0M8 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6z']] as $p)
                                    <button type="button" data-persona="{{ $p['k'] }}"
                                        class="persona-card text-left px-3 py-3 border border-paper-200 rounded-xl hover:border-wa-deep transition">
                                        <span
                                            class="inline-flex w-7 h-7 rounded-lg bg-wa-bubble text-wa-deep items-center justify-center mb-2">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                stroke="currentColor" stroke-width="1.7">
                                                <path d="{{ $p['icon'] }}" />
                                            </svg>
                                        </span>
                                        <div class="font-semibold text-[12.5px]">{{ $p['l'] }}</div>
                                        <div class="text-[10.5px] text-ink-500 leading-snug mt-0.5">{{ $p['d'] }}
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 flex items-center justify-between">
                                <span>{{ __('Opening lines') }} <span class="text-ink-500 font-normal">(one is picked
                                        at random each call so the agent doesn't sound robotic)</span></span>
                                <button type="button" data-add-greeting
                                    class="px-2 py-1 rounded-full bg-paper-50 hover:bg-paper-100 text-[10.5px] font-semibold border border-paper-200 inline-flex items-center gap-1">+
                                    Add line</button>
                            </label>
                            <div data-greetings class="space-y-2"></div>
                        </div>

                        <div class="mt-4">
                            <label
                                class="flex items-center justify-between px-3 py-3 border border-paper-200 rounded-lg bg-paper-50 cursor-pointer">
                                <span class="flex items-center gap-2 text-[12.5px]">
                                    <span data-status-dot class="w-2 h-2 rounded-full bg-wa-green"></span>
                                    <span class="font-semibold"
                                        data-status-label>{{ __('Live — answers calls') }}</span>
                                </span>
                                <input data-wf="status_toggle" type="checkbox" class="sr-only" />
                                <span data-status-track
                                    class="relative inline-block w-10 h-6 rounded-full bg-wa-deep transition">
                                    <span data-status-knob
                                        class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-paper-0 transition translate-x-4"></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    {{-- ── STEP 2 · AI Brain ─────────────────────────────────── --}}
                    <div class="step-pane hidden" data-step="2">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Brain & model') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('multi-provider') }}</span>
                        </div>

                        {{-- 3 provider cards — pick one. Multi-provider, not Gemini-only. --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-4" data-provider-cards>
                            @foreach ([['k' => 'gemini', 'l' => 'Gemini', 's' => 'Google · low latency', 'dot' => '#4285F4', 'models' => 'gemini-2.5-flash-lite, gemini-2.5-flash, gemini-2.5-pro'], ['k' => 'openai', 'l' => 'GPT', 's' => 'OpenAI · best reasoning', 'dot' => '#10A37F', 'models' => 'gpt-4o-mini, gpt-4o, gpt-4.1'], ['k' => 'anthropic', 'l' => 'Claude', 's' => 'Anthropic · steady tone', 'dot' => '#D97757', 'models' => 'claude-haiku-4-5-20251001, claude-sonnet-4-6, claude-opus-4-7']] as $p)
                                <button type="button" data-provider="{{ $p['k'] }}"
                                    class="provider-card text-left px-3 py-3 border border-paper-200 rounded-xl hover:border-wa-deep transition">
                                    <div class="flex items-center gap-2 mb-1.5">
                                        <span class="w-2 h-2 rounded-full"
                                            style="background: {{ $p['dot'] }}"></span>
                                        <span class="font-semibold text-[13px]">{{ $p['l'] }}</span>
                                    </div>
                                    <div class="text-[10.5px] text-ink-500">{{ $p['s'] }}</div>
                                    <div class="text-[10.5px] text-ink-500 font-mono mt-1 truncate"
                                        title="{{ $p['models'] }}">{{ $p['models'] }}</div>
                                </button>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Model') }}</label>
                                <input data-wf="ai_model" type="text" maxlength="80"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep" />
                                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Provider-specific model id.') }}
                                </div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 flex items-center justify-between">
                                    BYOK key
                                    <span data-ai-saved
                                        class="hidden text-[10px] font-mono text-wa-deep">{{ __('saved · leave blank to keep') }}</span>
                                </label>
                                <input data-wf="ai_api_key" type="password" autocomplete="off"
                                    placeholder="{{ __('optional — uses admin key by default') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep" />
                            </div>
                        </div>

                        <div class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Instructions') }}
                                <span class="text-ink-500 font-normal">— the system prompt</span></label>
                            <textarea data-wf="ai_system_prompt" rows="5" maxlength="6000"
                                placeholder="{{ __('You are Riley from Acme. Greet warmly, ask their order number, look it up with the track_order skill, then summarise. Hand off to a human if frustrated.') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></textarea>
                        </div>

                        <div class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Knowledge source (URL — agent crawls + indexes on first call)') }}</label>
                            <input data-wf="knowledge_base_url" type="url" maxlength="500"
                                placeholder="https://docs.acme.com"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep" />
                        </div>

                        {{-- Personality sliders — competitor only has one toggle. --}}
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 flex items-center justify-between">{{ __('Warmth') }}
                                    <span data-pers-val="warmth"
                                        class="font-mono text-[10.5px] text-ink-500">60</span></label>
                                <input data-pers="warmth" type="range" min="0" max="100"
                                    class="w-full accent-wa-deep" />
                                <div class="text-[10.5px] text-ink-500 mt-0.5">{{ __('colder ⟷ warmer') }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 flex items-center justify-between">{{ __('Formality') }}
                                    <span data-pers-val="formality"
                                        class="font-mono text-[10.5px] text-ink-500">50</span></label>
                                <input data-pers="formality" type="range" min="0" max="100"
                                    class="w-full accent-wa-deep" />
                                <div class="text-[10.5px] text-ink-500 mt-0.5">{{ __('casual ⟷ formal') }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 flex items-center justify-between">{{ __('Pace') }}
                                    <span data-pers-val="pace"
                                        class="font-mono text-[10.5px] text-ink-500">50</span></label>
                                <input data-pers="pace" type="range" min="0" max="100"
                                    class="w-full accent-wa-deep" />
                                <div class="text-[10.5px] text-ink-500 mt-0.5">{{ __('verbose ⟷ concise') }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- ── STEP 3 · Skills & Actions ──────────────────────────── --}}
                    <div class="step-pane hidden" data-step="3">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Actions the agent can take') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('optional · max 25') }}</span>
                        </div>
                        <p class="text-[12px] text-ink-500 mb-4">
                            {{ __('An action is a live request the agent can run during a call. List the phrases that should launch it — the agent listens for them and pulls the needed details out of what the caller says.') }}
                        </p>

                        <div data-tools-list class="space-y-4"></div>

                        <button type="button" data-add-tool
                            class="mt-3 w-full px-4 py-3 rounded-2xl border border-dashed border-paper-300 hover:border-wa-deep hover:bg-paper-50 text-[13px] font-semibold inline-flex items-center justify-center gap-2">
                            <span class="w-6 h-6 rounded-full border border-paper-300 grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M8 3v10M3 8h10" />
                                </svg></span>
                            Add an action
                        </button>
                    </div>

                    {{-- ── STEP 4 · Voice ─────────────────────────────────────── --}}
                    <div class="step-pane hidden" data-step="4">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Speech & audio') }}</span>
                        </div>

                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-2 block">{{ __('Text-to-speech engine') }}</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-4" data-voice-cards>
                            @foreach ([['k' => 'elevenlabs', 'l' => 'ElevenLabs', 's' => 'Premium · natural breath', 'dot' => '#15171A'], ['k' => 'openai', 'l' => 'OpenAI TTS', 's' => 'Fast · 6 voices', 'dot' => '#10A37F'], ['k' => 'deepgram', 'l' => 'Deepgram Aura', 's' => 'Ultra-low latency', 'dot' => '#3D7CD3']] as $v)
                                <button type="button" data-voice-card="{{ $v['k'] }}"
                                    class="voice-card text-left px-3 py-3 border border-paper-200 rounded-xl hover:border-wa-deep transition">
                                    <div class="flex items-center gap-2 mb-1"><span class="w-2 h-2 rounded-full"
                                            style="background: {{ $v['dot'] }}"></span><span
                                            class="font-semibold text-[13px]">{{ $v['l'] }}</span></div>
                                    <div class="text-[10.5px] text-ink-500">{{ $v['s'] }}</div>
                                </button>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Voice ID (optional)') }}</label>
                                <input data-wf="voice_id" type="text" maxlength="80"
                                    placeholder="{{ __('provider default if blank') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep" />
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 flex items-center justify-between">
                                    Voice provider BYOK
                                    <span data-voice-saved
                                        class="hidden text-[10px] font-mono text-wa-deep">{{ __('saved · leave blank to keep') }}</span>
                                </label>
                                <input data-wf="voice_api_key" type="password" autocomplete="off"
                                    placeholder="{{ __('optional') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep" />
                            </div>
                        </div>

                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Speech-to-text engine') }}</label>
                        <select data-wf="stt_provider"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] mb-4">
                            <option value="elevenlabs">{{ __('ElevenLabs · Deep Analysis') }}</option>
                            <option value="deepgram">{{ __('Deepgram Nova-2 · fastest') }}</option>
                            <option value="whisper">{{ __('OpenAI Whisper · most accurate') }}</option>
                            <option value="google">{{ __('Google Speech') }}</option>
                        </select>

                        <label
                            class="flex items-center justify-between px-3 py-3 border border-paper-200 rounded-lg bg-paper-50 cursor-pointer">
                            <div>
                                <div class="text-[12.5px] font-semibold">{{ __('Background noise suppression') }}
                                </div>
                                <div class="text-[10.5px] text-ink-500">
                                    {{ __('Strips traffic, fan, keyboard sounds before STT — improves accuracy on noisy lines.') }}
                                </div>
                            </div>
                            <input data-wf="noise_suppression" type="checkbox" class="w-5 h-5 accent-wa-deep" />
                        </label>
                    </div>

                    {{-- ── STEP 5 · Routing & Recording ───────────────────────── --}}
                    <div class="step-pane hidden" data-step="5">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">05</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Routing & recording') }}</span>
                        </div>

                        {{-- Per-number routing — bind this assistant to specific WABA
                             numbers. Empty = catch-all (answers any unassigned number).
                             A number already owned by another assistant is disabled. --}}
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-2 block">{{ __('Answer on these numbers') }}</label>
                        @if (empty($numbers))
                            <div class="mb-4 px-3 py-3 border border-paper-200 rounded-xl bg-paper-50 text-[12px] text-ink-500">
                                {{ __('No WABA numbers connected yet. Connect one under Devices to route calls per number.') }}
                            </div>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
                                @foreach ($numbers as $num)
                                    <label class="flex items-center gap-2.5 px-3 py-2.5 border border-paper-200 rounded-xl {{ $num['taken_by'] ? 'bg-paper-100 opacity-60 cursor-not-allowed' : 'bg-paper-50 cursor-pointer hover:border-wa-deep/40' }}">
                                        <input type="checkbox" data-wf-device value="{{ $num['id'] }}" @disabled($num['taken_by']) class="w-5 h-5 accent-wa-deep shrink-0" />
                                        <span class="min-w-0">
                                            <span class="block text-[12.5px] font-semibold truncate">{{ $num['label'] }}</span>
                                            @if ($num['taken_by'])
                                                <span class="block text-[10.5px] text-ink-500 truncate">{{ __('Used by') }} {{ $num['taken_by'] }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        <div class="text-[10.5px] text-ink-500 mb-4">{{ __('Leave all unchecked = this assistant answers every number not assigned to a specific one (workspace default). A number can belong to only one assistant.') }}</div>

                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-2 block">{{ __('Recording') }}</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-4">
                            <label
                                class="flex items-center justify-between px-3 py-3 border border-paper-200 rounded-xl bg-paper-50 cursor-pointer">
                                <div>
                                    <div class="text-[12.5px] font-semibold">{{ __('Agent audio') }}</div>
                                    <div class="text-[10.5px] text-ink-500">{{ __('Save AI voice') }}</div>
                                </div>
                                <input data-wf="record_agent" type="checkbox" class="w-5 h-5 accent-wa-deep" />
                            </label>
                            <label
                                class="flex items-center justify-between px-3 py-3 border border-paper-200 rounded-xl bg-paper-50 cursor-pointer">
                                <div>
                                    <div class="text-[12.5px] font-semibold">{{ __('Caller audio') }}</div>
                                    <div class="text-[10.5px] text-ink-500">{{ __('Save caller voice') }}</div>
                                </div>
                                <input data-wf="record_user" type="checkbox" class="w-5 h-5 accent-wa-deep" />
                            </label>
                            <label
                                class="flex items-center justify-between px-3 py-3 border border-paper-200 rounded-xl bg-paper-50 cursor-pointer">
                                <div>
                                    <div class="text-[12.5px] font-semibold">{{ __('Auto transcript') }}</div>
                                    <div class="text-[10.5px] text-ink-500">{{ __('Save turn-by-turn') }}</div>
                                </div>
                                <input data-wf="auto_logging" type="checkbox" class="w-5 h-5 accent-wa-deep" />
                            </label>
                        </div>

                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-2 block">{{ __('When caller reaches voicemail or no answer') }}</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-4" data-voicemail-cards>
                            @foreach ([['k' => 'leave_message', 'l' => 'Leave message', 'd' => 'Speaks the greeting and hangs up.'], ['k' => 'retry', 'l' => 'Message + callback', 'd' => 'Leaves the greeting and flags a callback for an operator.'], ['k' => 'silent_log', 'l' => 'Silent log', 'd' => 'Hangs up; only logs the attempt.']] as $v)
                                <button type="button" data-voicemail="{{ $v['k'] }}"
                                    class="voicemail-card text-left px-3 py-3 border border-paper-200 rounded-xl hover:border-wa-deep transition">
                                    <div class="font-semibold text-[12.5px]">{{ $v['l'] }}</div>
                                    <div class="text-[10.5px] text-ink-500 leading-snug mt-0.5">{{ $v['d'] }}
                                    </div>
                                </button>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('End-call keywords') }}</label>
                                <div data-exit-chips
                                    class="px-3 py-2 border border-paper-200 rounded-lg bg-white flex flex-wrap gap-1.5 min-h-[40px]">
                                </div>
                                <input data-exit-input type="text" placeholder="{{ __('add keyword + Enter') }}"
                                    class="mt-2 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12px]" />
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Hand off to team (when AI flags low confidence)') }}</label>
                                <input data-wf="human_handoff_team" type="text" maxlength="80"
                                    placeholder="{{ __('Support / Sales / blank = no handoff') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px]" />
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __("Routes the conversation to /team-inbox under this team's queue.") }}</div>
                            </div>
                        </div>

                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Goodbye line') }}</label>
                        <input data-wf="last_greeting" type="text" maxlength="500"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px]" />
                    </div>

                    <div class="mt-6 pt-5 border-t border-paper-200 flex items-center justify-between">
                        <button type="button" data-prev
                            class="px-4 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[12.5px] inline-flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed"
                            disabled>
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M10 4l-4 4 4 4" />
                            </svg>
                            Previous
                        </button>
                        <div data-wizard-error class="hidden text-[12px] text-accent-coral"></div>
                        <button type="button" data-next
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold inline-flex items-center gap-1.5">
                            <span data-next-label>{{ __('Next step') }}</span>
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M6 4l4 4-4 4" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── Sidebar preview — mimics wa-campaigns' right rail ─── --}}
            <aside class="space-y-4 xl:sticky xl:top-[72px]">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200 bg-paper-50/40 flex items-center justify-between">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Live preview') }}</div>
                        <span class="text-[10px] font-mono text-ink-500">{{ __('how a call sounds') }}</span>
                    </div>
                    <div class="p-4 space-y-2 max-h-[420px] overflow-y-auto bg-paper-50/30">
                        <div data-preview-ring class="text-center py-3 hidden">
                            <div class="font-mono text-[10.5px] text-ink-500 mb-1">{{ __('incoming · 0:00') }}</div>
                            <div class="font-serif text-[13px]"><span data-preview-name>{{ __('Agent') }}</span>
                                picks up…</div>
                        </div>
                        <div data-preview-bubbles class="space-y-2"></div>
                    </div>
                    <div class="px-4 py-3 border-t border-paper-200 text-[11px] text-ink-500 font-mono">
                        <span class="inline-flex items-center gap-1"><span data-preview-provider-dot
                                class="w-1.5 h-1.5 rounded-full bg-paper-200"></span><span
                                data-preview-provider>—</span></span>
                        ·
                        <span data-preview-voice>—</span>
                    </div>
                </div>

                <div
                    class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1">{{ __('Tips') }}</div>
                    <ul class="space-y-1.5 list-disc pl-4">
                        <li>Use {{ '@' }}placeholder for variables (auto-filled from caller history).</li>
                        <li>{{ __('Actions can chain — the agent runs one, reads the response, then runs another in the same turn.') }}
                        </li>
                        <li>{{ __('Test the agent from a real phone before going live; preview is text-only.') }}</li>
                    </ul>
                </div>
            </aside>
        </div>
    </section>

    <script>
        window.WA_AI_ASSISTANT = @json($payload);
    </script>

</x-layouts.user>
