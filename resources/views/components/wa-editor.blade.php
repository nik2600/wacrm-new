@props([
    'name' => 'body',
    'value' => '',
    'rows' => 4,
    'required' => false,
    'maxlength' => 4096,
    'placeholder' => '',
    'id' => null,
    'hint' => '*bold* _italic_ ~strike~ `code`',
])

@php
    $editorId = $id ?? 'wa-editor-' . \Illuminate\Support\Str::random(8);
@endphp

{{--
 Reusable WhatsApp-style rich-text editor. Drop into any form:

 <x-wa-editor name="body" :rows="4" required placeholder="…" />

 The visible affordances (bold/italic/strike/code/emoji) wrap the
 selection with WhatsApp's plain-text markdown — the same syntax the
 chat thread already renders. The component is wired by
 resources/js/wa-editor.js, which auto-initialises every
 [data-wa-editor] node on DOMContentLoaded.
--}}
<div data-wa-editor
    class="wa-editor relative rounded-xl border border-paper-200 bg-white overflow-visible focus-within:border-wa-deep focus-within:ring-4 focus-within:ring-wa-deep/10 transition">

    <div
        class="wa-editor-toolbar flex items-center gap-0.5 px-2 py-1.5 border-b border-paper-200 bg-paper-50/60 rounded-t-xl">
        <button type="button" data-wa-cmd="bold" title="{{ __('Bold (*text*)') }}"
            class="wa-editor-btn w-7 h-7 rounded-md grid place-items-center text-[12.5px] font-bold hover:bg-wa-mint">B</button>
        <button type="button" data-wa-cmd="italic" title="{{ __('Italic (_text_)') }}"
            class="wa-editor-btn w-7 h-7 rounded-md grid place-items-center text-[12.5px] italic font-serif hover:bg-wa-mint">I</button>
        <button type="button" data-wa-cmd="strike" title="{{ __('Strikethrough (~text~)') }}"
            class="wa-editor-btn w-7 h-7 rounded-md grid place-items-center text-[12.5px] line-through hover:bg-wa-mint">S</button>
        <button type="button" data-wa-cmd="code" title="{{ __('Monospace (`text`)') }}"
            class="wa-editor-btn w-7 h-7 rounded-md grid place-items-center text-[11px] font-mono hover:bg-wa-mint">{
            }</button>

        <span class="w-px h-4 bg-paper-200 mx-1.5"></span>

        <button type="button" data-wa-cmd="emoji" title="{{ __('Emoji') }}"
            class="wa-editor-btn w-7 h-7 rounded-md grid place-items-center hover:bg-wa-mint text-ink-700">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="8" cy="8" r="5.5" />
                <path d="M5.8 9.5s.8 1.3 2.2 1.3 2.2-1.3 2.2-1.3M6.2 6.4h.01M9.8 6.4h.01" />
            </svg>
        </button>

        <span class="ml-auto text-[10px] font-mono text-ink-500 hidden md:inline">{{ $hint }}</span>
    </div>

    <textarea id="{{ $editorId }}" name="{{ $name }}" rows="{{ $rows }}"
        @if ($required) required @endif maxlength="{{ $maxlength }}" placeholder="{{ $placeholder }}"
        data-attr-input
        class="wa-editor-textarea w-full px-3 py-2 bg-transparent text-[13px] leading-relaxed resize-none focus:outline-none rounded-b-xl">{{ $value }}</textarea>

    {{-- Emoji panel — absolute-positioned so opening it doesn't push
 the surrounding form fields. Populated lazily by
 wa-editor.js on first open. --}}
    <div
        class="wa-editor-emoji hidden absolute z-50 right-0 top-full mt-2 rounded-2xl border border-paper-200 bg-paper-0 shadow-soft overflow-hidden">
    </div>
</div>
