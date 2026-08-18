/*
 * Shared confirmation modal for /scheduled actions (index + detail).
 *
 * Usage:
 *   import { schedConfirm } from './sched-confirm-modal.js';
 *   const ok = await schedConfirm({
 *     title: 'Cancel this schedule?',
 *     body:  'The Node cron will be removed…',
 *     ok:    'Yes, cancel',
 *     danger: true,
 *   });
 *   if (ok) { … }
 *
 * The modal markup lives in the blade (`<div id="sched-confirm">`) so
 * both pages can drop in their own copy. This helper only handles the
 * promise-based open/close. If the modal node isn't present we fall
 * back to window.confirm() so the action still works.
 */
export function schedConfirm({ title = 'Confirm action', body = 'Are you sure?', ok = 'Yes, proceed', cancel = 'Keep it', danger = true } = {}) {
    return new Promise(resolve => {
        const root = document.getElementById('sched-confirm');
        if (!root) {
            // Defensive fallback for pages that didn't render the modal.
            // Better to ask via browser confirm than silently no-op.
            resolve(window.confirm(`${title}\n\n${body}`));
            return;
        }

        const titleEl  = document.getElementById('sched-confirm-title');
        const bodyEl   = document.getElementById('sched-confirm-body');
        const okBtn    = document.getElementById('sched-confirm-ok');
        const cancelBtn= document.getElementById('sched-confirm-cancel');
        const iconEl   = document.getElementById('sched-confirm-icon');

        if (titleEl)  titleEl.textContent  = title;
        if (bodyEl)   bodyEl.textContent   = body;
        if (okBtn)    okBtn.textContent    = ok;
        if (cancelBtn) cancelBtn.textContent = cancel;
        // Recolour the OK button + icon based on danger level. Default
        // for destructive actions is coral; non-destructive ("Run now")
        // gets the brand teal so it doesn't look like a delete prompt.
        if (okBtn) {
            okBtn.classList.remove('bg-accent-coral', 'bg-wa-deep');
            okBtn.classList.add(danger ? 'bg-accent-coral' : 'bg-wa-deep');
        }
        if (iconEl) {
            iconEl.classList.remove('bg-accent-coral/15', 'text-accent-coral', 'bg-wa-deep/10', 'text-wa-deep');
            iconEl.classList.add(...(danger ? ['bg-accent-coral/15', 'text-accent-coral'] : ['bg-wa-deep/10', 'text-wa-deep']));
        }

        const close = (value) => {
            root.classList.add('hidden');
            root.classList.remove('flex');
            okBtn?.removeEventListener('click', onOk);
            cancelBtn?.removeEventListener('click', onCancel);
            root.removeEventListener('click', onBackdrop);
            document.removeEventListener('keydown', onKey);
            resolve(value);
        };
        const onOk      = () => close(true);
        const onCancel  = () => close(false);
        const onBackdrop= (e) => { if (e.target === root) close(false); };
        const onKey     = (e) => { if (e.key === 'Escape') close(false); else if (e.key === 'Enter') close(true); };

        okBtn?.addEventListener('click', onOk);
        cancelBtn?.addEventListener('click', onCancel);
        root.addEventListener('click', onBackdrop);
        document.addEventListener('keydown', onKey);

        root.classList.remove('hidden');
        root.classList.add('flex');
        // Focus the cancel button so a stray Enter doesn't immediately
        // confirm a destructive action. Enter still confirms — but only
        // intentionally.
        cancelBtn?.focus();
    });
}
