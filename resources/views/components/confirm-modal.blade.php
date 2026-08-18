@props([
    'id' => 'confirmModal',
    'title' => 'Are you sure?',
    'message' => 'This action cannot be undone.',
    'confirmText' => 'Delete',
    'cancelText' => 'Cancel',
    'eyebrow' => 'Confirm',
    'tone' => 'danger', // danger | warning | info
])

{{--
 Reusable confirm modal — visually mirrors the chat page's
 #confirm-modal block (resources/views/user/chat/index.blade.php).
 The markup below is a copy-paste of that block with the static
 title/message/buttons swapped for slot props, and a couple of
 data-* hooks added so window.confirmDialog() (in app.js) can
 bind cancel + accept handlers.

 Usage:
 <x-confirm-modal id="globalConfirmModal" />
 // …then in JS:
 window.confirmDialog({ title: 'Delete contact?', onConfirm: () => … });
--}}
<div id="{{ $id }}" data-confirm-modal
    class="modal hidden fixed inset-0 z-[60] items-center justify-center p-5 bg-[rgba(11,31,28,0.46)] [&.open]:flex">
    <div
        class="modal-panel w-full max-w-md bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] overflow-hidden">
        <div class="px-5 py-4 flex items-start gap-3 border-b border-paper-200">
            <div data-confirm-icon
                class="w-10 h-10 rounded-2xl grid place-items-center shrink-0 {{ $tone === 'danger' ? 'bg-accent-coral/15 text-accent-coral' : 'bg-wa-mint text-wa-deep' }}">
                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M3 4h10M5 4V2.5h6V4M4 4l1 10h6l1-10" />
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <div data-confirm-eyebrow class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ $eyebrow }}</div>
                <h3 data-confirm-title class="font-serif text-[20px] leading-tight tracking-[-0.01em] mt-0.5">
                    {{ $title }}</h3>
                <p data-confirm-message class="mt-1.5 text-[13px] text-ink-600 leading-relaxed">{{ $message }}</p>
            </div>
        </div>
        <div class="px-5 py-3 flex justify-end gap-2 bg-paper-50/60">
            <button type="button" data-confirm-cancel
                class="px-4 py-2 rounded-full border border-paper-200 bg-white text-[12px] font-semibold hover:border-wa-deep">{{ $cancelText }}</button>
            <button type="button" data-confirm-accept
                class="px-4 py-2 rounded-full text-paper-0 text-[12px] font-semibold {{ $tone === 'danger' ? 'bg-accent-coral hover:bg-[#A1431F]' : 'bg-wa-deep hover:bg-wa-teal' }}">{{ $confirmText }}</button>
        </div>
    </div>
</div>
