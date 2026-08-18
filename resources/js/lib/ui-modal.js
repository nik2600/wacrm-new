/**
 * Shared modal system — replaces the ugly browser-native alert / confirm / prompt.
 *
 * Each function returns a Promise:
 *   - uiAlert({title, message, okText})           → Promise<void>
 *   - uiConfirm({title, message, confirmText, danger}) → Promise<bool>
 *   - uiPrompt({title, message, placeholder, minLength, okText}) → Promise<string|null>
 *
 * The modal renders directly into <body>, locks scroll, supports Esc + click-outside,
 * and uses the project's design tokens (paper / ink / wa-deep) so it looks native to
 * the admin theme. No HTML changes needed beyond optional data-attribute helpers.
 *
 * Global delegation also enables drop-in replacement on existing forms:
 *   <form onsubmit="return confirm('…')">    →    <form data-confirm="…">
 *   <form onsubmit="…prompt('…')…">          →    <form data-prompt-reason="…" data-min-length="8">
 *
 * Both attributes auto-intercept the submit, show a custom modal, and resume the
 * submit only if the user confirms / provides a valid value.
 */

const ICONS = {
    warning: '<svg viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 9v4M12 17.5v.01"/><path d="M3.5 19.5h17a1.5 1.5 0 0 0 1.3-2.25l-8.5-14.5a1.5 1.5 0 0 0-2.6 0l-8.5 14.5a1.5 1.5 0 0 0 1.3 2.25Z"/></svg>',
    danger:  '<svg viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg>',
    info:    '<svg viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.01"/></svg>',
    prompt:  '<svg viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 5h16M4 12h10M4 19h7"/></svg>',
};

let activeModal = null;

function lockBody() {
    document.body.style.overflow = 'hidden';
}
function unlockBody() {
    document.body.style.overflow = '';
}

function buildShell({ tone = 'info', title, message, body, footer }) {
    if (activeModal) closeModal(false);
    const toneIconColor = {
        warning: 'text-accent-amber bg-accent-amber/10',
        danger:  'text-accent-coral bg-accent-coral/10',
        info:    'text-wa-deep bg-wa-bubble',
        prompt:  'text-wa-deep bg-wa-bubble',
    }[tone] || 'text-wa-deep bg-wa-bubble';

    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 z-[2147483646] flex items-center justify-center p-4 bg-ink-900/40';
    overlay.style.backdropFilter = 'blur(2px)';
    overlay.innerHTML = `
      <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-xl max-w-[440px] w-full overflow-hidden ui-modal-card">
        <div class="px-6 pt-6 pb-2 flex gap-4 items-start">
          <span class="w-12 h-12 rounded-xl grid place-items-center shrink-0 ${toneIconColor}">${ICONS[tone] || ICONS.info}</span>
          <div class="min-w-0 flex-1">
            <h3 class="font-serif text-[20px] leading-tight">${escapeHtml(title)}</h3>
            ${message ? `<p class="text-[13px] text-ink-600 mt-2 leading-snug">${escapeHtml(message)}</p>` : ''}
          </div>
        </div>
        ${body ? `<div class="px-6 pb-2">${body}</div>` : ''}
        <div class="px-6 py-4 mt-2 bg-paper-50/50 border-t border-paper-200 flex items-center justify-end gap-2">
          ${footer}
        </div>
      </div>
    `;
    document.body.appendChild(overlay);
    lockBody();
    activeModal = overlay;
    // Click-outside closes.
    overlay.addEventListener('mousedown', (e) => { if (e.target === overlay) closeModal(false); });
    return overlay;
}

function closeModal(result) {
    if (!activeModal) return;
    const overlay = activeModal;
    overlay.classList.add('ui-modal-fade');
    overlay.style.opacity = '0';
    overlay.style.transition = 'opacity .12s';
    setTimeout(() => { overlay.remove(); }, 120);
    activeModal = null;
    unlockBody();
    document.removeEventListener('keydown', onEsc);
    if (typeof currentResolver === 'function') {
        const r = currentResolver;
        currentResolver = null;
        r(result);
    }
}

let currentResolver = null;
function onEsc(e) {
    if (e.key === 'Escape') closeModal(false);
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

export function uiAlert({ title = 'Heads up', message = '', okText = 'OK' } = {}) {
    return new Promise((resolve) => {
        currentResolver = resolve;
        const overlay = buildShell({
            tone: 'info', title, message,
            footer: `<button type="button" data-ui-ok class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">${escapeHtml(okText)}</button>`,
        });
        const ok = overlay.querySelector('[data-ui-ok]');
        ok.addEventListener('click', () => closeModal(true));
        ok.focus();
        document.addEventListener('keydown', onEsc);
    });
}

export function uiConfirm({ title = 'Are you sure?', message = '', confirmText = 'Confirm', cancelText = 'Cancel', danger = false } = {}) {
    return new Promise((resolve) => {
        currentResolver = resolve;
        const tone = danger ? 'danger' : 'warning';
        const btnCls = danger ? 'bg-accent-coral hover:bg-accent-coral/80' : 'bg-wa-deep hover:bg-wa-teal';
        const overlay = buildShell({
            tone, title, message,
            footer: `
              <button type="button" data-ui-cancel class="px-4 py-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12.5px] font-medium">${escapeHtml(cancelText)}</button>
              <button type="button" data-ui-ok class="px-4 py-2 rounded-full ${btnCls} text-paper-0 text-[12.5px] font-semibold">${escapeHtml(confirmText)}</button>
            `,
        });
        overlay.querySelector('[data-ui-cancel]').addEventListener('click', () => closeModal(false));
        const ok = overlay.querySelector('[data-ui-ok]');
        ok.addEventListener('click', () => closeModal(true));
        ok.focus();
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); closeModal(true); }
        }, { once: true });
        document.addEventListener('keydown', onEsc);
    });
}

export function uiPrompt({ title = 'Please confirm', message = '', placeholder = '', defaultValue = '', minLength = 0, okText = 'Submit', cancelText = 'Cancel' } = {}) {
    return new Promise((resolve) => {
        currentResolver = resolve;
        const inputId = 'ui-prompt-input-' + Math.random().toString(36).slice(2, 7);
        const overlay = buildShell({
            tone: 'prompt', title, message,
            body: `
              <input id="${inputId}" type="text" value="${escapeHtml(defaultValue)}" placeholder="${escapeHtml(placeholder)}"
                     class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 mt-2">
              <div data-ui-err class="hidden mt-2 text-[11.5px] text-accent-coral"></div>
            `,
            footer: `
              <button type="button" data-ui-cancel class="px-4 py-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12.5px] font-medium">${escapeHtml(cancelText)}</button>
              <button type="button" data-ui-ok class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">${escapeHtml(okText)}</button>
            `,
        });
        const input = overlay.querySelector('#' + inputId);
        const err   = overlay.querySelector('[data-ui-err]');
        const submit = () => {
            const v = input.value.trim();
            if (minLength > 0 && v.length < minLength) {
                err.textContent = `Need at least ${minLength} characters.`;
                err.classList.remove('hidden');
                input.focus();
                return;
            }
            closeModal(v);
        };
        overlay.querySelector('[data-ui-cancel]').addEventListener('click', () => closeModal(null));
        overlay.querySelector('[data-ui-ok]').addEventListener('click', submit);
        input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); submit(); } });
        setTimeout(() => input.focus(), 30);
        document.addEventListener('keydown', onEsc);
    });
}

/**
 * Global delegation — attach once. Intercepts:
 *   - form[data-confirm]            → uiConfirm before allowing submit
 *   - form[data-prompt-reason]      → uiPrompt for a value, then inject into <input name="reason"> and submit
 *   - button[data-ui-alert]         → uiAlert
 *   - button[data-ui-confirm-action] → uiConfirm before navigating to data-href
 */
function attachDelegation() {
    // button[data-confirm] — a single submit button that needs its OWN warning,
    // distinct from the form's main submit (e.g. a "Disconnect" button that posts
    // to a different route via formaction while "Save" posts to the form action).
    // We confirm, then re-submit THROUGH that button with requestSubmit(button) so
    // its formaction / formmethod are honoured.
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('button[data-confirm]');
        if (!btn || btn.dataset.uiOk === '1') return;
        e.preventDefault();
        const ok = await uiConfirm({
            title: btn.dataset.confirmTitle || 'Are you sure?',
            message: btn.dataset.confirm,
            confirmText: btn.dataset.confirmText || (btn.dataset.danger === '1' ? 'Delete' : 'Confirm'),
            danger: btn.dataset.danger === '1',
        });
        if (!ok) return;
        btn.dataset.uiOk = '1';
        const form = btn.form || btn.closest('form');
        if (form && typeof form.requestSubmit === 'function') form.requestSubmit(btn);
        else btn.click();
    }, true);

    document.addEventListener('submit', async (e) => {
        const form = e.target.closest('form');
        if (!form) return;

        if (form.dataset.confirm && form.dataset.uiOk !== '1') {
            e.preventDefault();
            const ok = await uiConfirm({
                title: form.dataset.confirmTitle || 'Are you sure?',
                message: form.dataset.confirm,
                confirmText: form.dataset.confirmText || (form.dataset.danger === '1' ? 'Delete' : 'Confirm'),
                danger: form.dataset.danger === '1',
            });
            if (ok) { form.dataset.uiOk = '1'; form.submit(); }
            return;
        }
        if (form.dataset.promptReason && form.dataset.uiOk !== '1') {
            e.preventDefault();
            const val = await uiPrompt({
                title: form.dataset.promptTitle || 'Enter reason',
                message: form.dataset.promptReason,
                placeholder: form.dataset.promptPlaceholder || '',
                minLength: Number(form.dataset.minLength || 0),
                okText: form.dataset.confirmText || 'Continue',
            });
            if (val === null) return;
            const target = form.querySelector('[name="reason"]') || form.querySelector('[name="' + (form.dataset.promptField || 'reason') + '"]');
            if (target) target.value = val;
            form.dataset.uiOk = '1';
            form.submit();
        }
    }, true);
}

// Auto-install on first import.
if (typeof window !== 'undefined') {
    if (!window.uiAlert)   window.uiAlert   = uiAlert;
    if (!window.uiConfirm) window.uiConfirm = uiConfirm;
    if (!window.uiPrompt)  window.uiPrompt  = uiPrompt;
    if (document.readyState !== 'loading') attachDelegation();
    else document.addEventListener('DOMContentLoaded', attachDelegation, { once: true });
}
