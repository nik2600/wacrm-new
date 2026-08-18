@props([
    'name' => 'message',
    'value' => '',
    'placeholder' => 'Type a message…',
    'rows' => 5,
    'maxlength' => 1024,
    'id' => null,
    'showCounter' => true,
    'showFormatBar' => true,
    'showVariableInsert' => true,
    'showEmoji' => true,
    'showAi' => true,
    'hint' => null,
    'required' => false,
])

@php
    $composeId = $id ?? 'compose-textarea-' . \Illuminate\Support\Str::random(8);
    // Read once so every compose-textarea on the page picks up the
    // user's "Auto AI summarize" toggle without each caller having to
    // pass it. The wa-editor JS decides whether to debounce-trigger
    // the review automatically based on this flag.
    $autoAiOn = (bool) (auth()->user()?->auto_ai_summarize_enabled ?? false);
@endphp

{{--
 Reusable compose-style textarea — visually mirrors the canonical
 "INITIAL MESSAGE / Reply text" pattern used inline in:
 - resources/views/user/auto-reply/create.blade.php (#reply-text)
 - resources/views/user/scheduled/create.blade.php (#msg-content)
 - resources/views/user/templates/create.blade.php (#tpl-body)
 - resources/views/user/templates/edit.blade.php (#tpl-body)

 Toolbar is on TOP (B / I / S / { } / emoji + format-syntax hint right-aligned).
 Character counter sits at the bottom-right (font-mono, 10.5px, ink-500).

 Drop into any form:

 <x-compose-textarea name="body" :rows="6" :maxlength="1024" />
--}}
{{-- Hooks `wa-editor.js`: [data-wa-editor] is the root, .wa-editor-textarea
 is the input, .wa-editor-emoji is the emoji-panel mount, and each
 toolbar button carries data-wa-cmd="bold|italic|strike|code|emoji"
 so the editor JS finds them and wires them up. --}}
<div data-compose data-wa-editor data-attr-form data-ai-auto="{{ $autoAiOn ? '1' : '0' }}"
    @if ($showAi) data-ai-enabled="1" @endif
    class="border border-paper-200 rounded-lg overflow-visible bg-white focus-within:border-wa-deep focus-within:ring-4 focus-within:ring-wa-deep/10 transition">
    @if ($showFormatBar)
        <div class="flex items-center gap-1 px-2 py-1.5 border-b border-paper-200 bg-paper-50">
            <button type="button" data-wa-cmd="bold"
                class="w-7 h-7 rounded hover:bg-white text-ink-700 inline-flex items-center justify-center text-[11px] font-bold"
                title="{{ __('Bold (Ctrl+B)') }}">B</button>
            <button type="button" data-wa-cmd="italic"
                class="w-7 h-7 rounded hover:bg-white text-ink-700 inline-flex items-center justify-center text-[11px] italic"
                title="{{ __('Italic (Ctrl+I)') }}">I</button>
            <button type="button" data-wa-cmd="strike"
                class="w-7 h-7 rounded hover:bg-white text-ink-700 inline-flex items-center justify-center text-[11px] line-through"
                title="{{ __('Strikethrough (Ctrl+U)') }}">S</button>
            @if ($showVariableInsert)
                <span class="w-px h-4 bg-paper-200 mx-1"></span>
                <button type="button" data-wa-cmd="code"
                    class="px-2 h-7 rounded hover:bg-white text-ink-700 text-[10.5px] font-mono inline-flex items-center gap-1"
                    title="{{ __('Code (`text`)') }}">{ }</button>
            @endif
            @if ($showEmoji)
                {{-- The relative wrapper anchors the popup to the emoji
 button specifically — not the whole compose box —
 so the picker appears right above the icon instead
 of floating in the middle of the screen. --}}
                <div class="relative">
                    <button type="button" data-wa-cmd="emoji"
                        class="w-7 h-7 rounded hover:bg-white text-ink-700 inline-flex items-center justify-center"
                        title="{{ __('Emoji') }}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <circle cx="8" cy="8" r="5.5" />
                            <path d="M5.8 9.5s.8 1.3 2.2 1.3 2.2-1.3 2.2-1.3M6.2 6.4h.01M9.8 6.4h.01" />
                        </svg>
                    </button>
                    <div
                        class="wa-editor-emoji hidden absolute left-0 bottom-full mb-2 z-50 rounded-2xl border border-paper-200 bg-paper-0 shadow-soft overflow-hidden w-[340px]">
                    </div>
                </div>
            @endif
            @if ($showAi)
                <span class="w-px h-4 bg-paper-200 mx-1"></span>
                {{-- AI review trigger — manual fire. When the user has
 "Auto AI summarize" on, wa-editor.js also fires this
 automatically on debounce. The button stays clickable
 either way so the user can force a refresh. --}}
                <button type="button" data-wa-cmd="ai-review"
                    class="w-7 h-7 rounded hover:bg-white text-wa-deep inline-flex items-center justify-center"
                    title="{{ __('Review with AI') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path
                            d="M8 2v3M8 11v3M2 8h3M11 8h3M4.2 4.2l2.1 2.1M9.7 9.7l2.1 2.1M11.8 4.2L9.7 6.3M6.3 9.7l-2.1 2.1" />
                    </svg>
                </button>
            @endif
            <span class="ml-auto font-mono text-[10px] text-ink-500 hidden sm:inline"><span class="font-mono">*bold*</span> <span
                    class="font-mono">_italic_</span> <span class="font-mono">~strike~</span> <span
                    class="font-mono">`code`</span></span>
        </div>
    @endif

    <textarea id="{{ $composeId }}" name="{{ $name }}" rows="{{ $rows }}"
        maxlength="{{ $maxlength }}" placeholder="{{ $placeholder }}"
        @if ($required) required @endif data-compose-input data-attr-input
        {{ $attributes->merge(['class' => 'wa-editor-textarea w-full px-3 py-2.5 text-[12.5px] text-ink-900 leading-relaxed font-sans bg-transparent outline-none resize-none border-0']) }}>{{ $value }}</textarea>

    {{-- Positional-placeholder map. The attribute-picker JS records
 { "1": "order_id", "2": "promo_key" } here whenever the operator
 inserts an attribute via `/`. The reply / template-store handlers
 read this to resolve {{1}} → actual attribute value before
 dispatching the message. --}}
    <input type="hidden" name="{{ $name }}_variable_map" data-attr-map value="{}">
    {{-- The wrapper carries data-attr-form so attribute-picker.js'
 recordMapping() can find the hidden field above. --}}

    @if ($showCounter)
        <div class="flex items-center justify-end px-3 pb-1.5 -mt-1">
            <span class="font-mono text-[10.5px] text-ink-500"><span
                    data-compose-count>{{ strlen($value) }}</span>/{{ $maxlength }}</span>
        </div>
    @endif
</div>

@if ($showAi)
    {{-- AI review panel — wa-editor.js paints into [data-ai-panel]
 whenever a review returns. Hidden by default; shown after the
 first fire. Score chip + good/bad bullets + best-version
 preview with "Append now" button that replaces the textarea
 contents. --}}
    <div data-ai-panel data-target="{{ $composeId }}"
        class="mt-2 hidden rounded-xl border border-paper-200 bg-paper-0 overflow-hidden">
        <div class="px-3 py-2 border-b border-paper-100 bg-paper-50/60 flex items-center gap-2">
            <span class="w-5 h-5 rounded-md bg-wa-mint text-wa-deep inline-flex items-center justify-center shrink-0">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path
                        d="M8 2v3M8 11v3M2 8h3M11 8h3M4.2 4.2l2.1 2.1M9.7 9.7l2.1 2.1M11.8 4.2L9.7 6.3M6.3 9.7l-2.1 2.1" />
                </svg>
            </span>
            <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('AI review') }}</span>
            <span data-ai-score
                class="hidden ml-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-0 border border-paper-200 text-[10.5px] font-mono text-ink-700"></span>
            <span data-ai-status class="ml-auto text-[10.5px] text-ink-500"></span>
            <button type="button" data-ai-close
                class="w-6 h-6 rounded hover:bg-white text-ink-500 inline-flex items-center justify-center"
                title="{{ __('Close review') }}">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M4 4l8 8M12 4l-8 8" />
                </svg>
            </button>
        </div>
        <div class="px-3 py-2.5">
            <div class="grid grid-cols-2 gap-3 mb-2">
                <div>
                    <div class="text-[10.5px] font-mono uppercase tracking-[0.14em] text-[#15803D] mb-1">
                        {{ __('Working well') }}</div>
                    <ul data-ai-good class="space-y-1 text-[11.5px] text-[#15803D] leading-snug"></ul>
                </div>
                <div>
                    <div class="text-[10.5px] font-mono uppercase tracking-[0.14em] text-[#B91C1C] mb-1">
                        {{ __('Needs work') }}</div>
                    <ul data-ai-bad class="space-y-1 text-[11.5px] text-[#B91C1C] leading-snug"></ul>
                </div>
            </div>
            <div data-ai-best-wrap class="hidden border-t border-paper-100 pt-2.5">
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <div class="text-[10.5px] font-mono uppercase tracking-[0.14em] text-ink-500">
                        {{ __('Suggested rewrite') }}</div>
                    <button type="button" data-ai-append
                        class="px-3 py-1 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[11px] font-semibold inline-flex items-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M2 8l5 5 7-9" />
                        </svg>
                        Append now
                    </button>
                </div>
                <div data-ai-best
                    class="rounded-lg bg-paper-50 border border-paper-100 px-2.5 py-2 text-[12px] text-ink-800 leading-snug whitespace-pre-wrap">
                </div>
            </div>
        </div>
    </div>
@endif

@if ($hint)
    <div class="text-[10.5px] text-ink-500 mt-1">{{ $hint }}</div>
@endif
