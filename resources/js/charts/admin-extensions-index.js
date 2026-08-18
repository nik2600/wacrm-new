/*
 * Admin → Settings → Extensions.
 *
 * Verify licence → upload package → install. Step 2 stays locked until step 1
 * passes, purely as a courtesy: the server re-verifies on upload, so this is
 * guidance, never the security boundary.
 */
export default function init() {
    const root = document.getElementById('wd-extensions');
    if (!root) return;

    const $ = (id) => document.getElementById(id);
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
    const toast = (m, k) => (window.toast ? window.toast(m, k === 'error' ? 'error' : 'success') : null);

    const codeInput = $('ext-code');
    const step2 = $('ext-step2');
    const fileInput = $('ext-file');
    const installBtn = $('ext-install');

    /** Inline banner. Colour carries the verdict; the text carries the reason. */
    const say = (el, msg, ok) => {
        if (!el) return;
        el.textContent = msg;
        el.className = 'mt-2 text-[11.5px] rounded-lg px-3 py-2 ' + (ok
            ? 'bg-wa-deep/10 text-wa-deep'
            : 'bg-accent-coral/10 text-accent-coral');
    };

    // ---- Step 1: verify -------------------------------------------------
    $('ext-verify')?.addEventListener('click', async () => {
        const btn = $('ext-verify');
        const msg = $('ext-verify-msg');
        const code = (codeInput?.value || '').trim();
        if (!code) { say(msg, 'Enter your purchase code first.', false); return; }

        btn.disabled = true;
        btn.textContent = 'Verifying…';
        try {
            const r = await fetch('/admin/extensions/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ purchase_code: code }),
            });
            const d = await r.json().catch(() => ({}));
            const ok = r.ok && d.ok;
            say(msg, d.message || (ok ? 'Licence verified.' : 'Could not verify that code.'), ok);

            // Unlock step 2 only on success — and re-lock on a later failure,
            // so an edited-then-rejected code cannot leave the form open.
            step2?.classList.toggle('opacity-50', !ok);
            step2?.classList.toggle('pointer-events-none', !ok);
        } catch (e) {
            say(msg, 'Verification failed: ' + e.message, false);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Verify';
        }
    });

    // Editing the code after verifying invalidates it — re-lock step 2 rather
    // than letting a stale "verified" state carry a different code forward.
    codeInput?.addEventListener('input', () => {
        step2?.classList.add('opacity-50', 'pointer-events-none');
        installBtn.disabled = true;
    });

    // ---- Step 2: choose + install ---------------------------------------
    fileInput?.addEventListener('change', () => {
        const f = fileInput.files?.[0];
        $('ext-file-label').textContent = f ? f.name : 'Choose an extension .zip';
        installBtn.disabled = !f;
    });

    installBtn?.addEventListener('click', async () => {
        const msg = $('ext-install-msg');
        const f = fileInput.files?.[0];
        if (!f) return;

        const fd = new FormData();
        fd.append('extension', f);
        fd.append('purchase_code', (codeInput?.value || '').trim());

        installBtn.disabled = true;
        installBtn.textContent = 'Installing…';
        say(msg, 'Uploading and merging — do not close this tab.', true);

        try {
            const r = await fetch('/admin/extensions/upload', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: fd,
            });
            const d = await r.json().catch(() => ({}));
            if (!r.ok || !d.ok) throw new Error(d.message || `HTTP ${r.status}`);

            say(msg, d.message + ' Reloading…', true);
            toast(d.message, 'success');
            // Reload so the installed list, and any nav the extension added,
            // render from the new code rather than the stale page.
            setTimeout(() => window.location.reload(), 1200);
        } catch (e) {
            say(msg, e.message, false);
            toast('Install failed: ' + e.message, 'error');
            installBtn.disabled = false;
            installBtn.textContent = 'Install';
        }
    });

    // ---- Instagram (Instaflow) connect modal ------------------------------
    // The card's Connect button opens a modal that takes the Instaflow URL +
    // shared secret. The form is a plain POST (saves + runs the handshake, then
    // redirects back with a flash), so JS only has to show/hide the modal —
    // and re-open it after a failed attempt so the operator sees why.
    const ifModal = document.getElementById('instaflow-modal');
    if (ifModal) {
        const openIf = () => { ifModal.classList.remove('hidden'); ifModal.classList.add('flex'); };
        const closeIf = () => { ifModal.classList.add('hidden'); ifModal.classList.remove('flex'); };
        root.querySelectorAll('[data-instaflow-open]').forEach((b) => b.addEventListener('click', openIf));
        ifModal.querySelectorAll('[data-instaflow-close]').forEach((b) => b.addEventListener('click', closeIf));
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !ifModal.classList.contains('hidden')) closeIf();
        });
        // Server flagged an error / validation failure on the last submit — pop
        // the modal open so the reason (already rendered inside it) is visible.
        if (ifModal.dataset.autoopen === '1') openIf();
    }

    // ---- Installed list: enable / disable / remove ------------------------
    root.querySelectorAll('[data-ext-toggle]').forEach((b) => {
        b.addEventListener('click', async () => {
            const id = b.dataset.extToggle;
            b.disabled = true;
            try {
                const r = await fetch(`/admin/extensions/${id}/toggle`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok || !d.ok) throw new Error(d.message || `HTTP ${r.status}`);
                window.location.reload();
            } catch (e) {
                toast('Could not change status: ' + e.message, 'error');
                b.disabled = false;
            }
        });
    });

    root.querySelectorAll('[data-ext-remove]').forEach((b) => {
        b.addEventListener('click', async () => {
            const id = b.dataset.extRemove;
            const name = b.dataset.extName || 'this extension';

            // Deleting files is not undoable from here, so make the operator
            // say yes to the specific extension, not a generic prompt.
            const ok = window.confirmModal
                ? await window.confirmModal({
                    title: 'Remove ' + name + '?',
                    body: 'This deletes the files it installed. Its data stays in the database.',
                    confirm: 'Remove',
                    danger: true,
                })
                : true;
            if (!ok) return;

            b.disabled = true;
            try {
                const r = await fetch(`/admin/extensions/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok || !d.ok) throw new Error(d.message || `HTTP ${r.status}`);
                toast(d.message, 'success');
                window.location.reload();
            } catch (e) {
                toast('Remove failed: ' + e.message, 'error');
                b.disabled = false;
            }
        });
    });

    // In-place module Remove / Re-activate — deactivates the module WITHOUT
    // deleting its files (a hidden marker stops it loading), so it's reversible.
    root.querySelectorAll('[data-mod-toggle]').forEach((b) => {
        b.addEventListener('click', async () => {
            const slug = b.dataset.modToggle;
            const off  = b.dataset.modOff === '1';   // currently deactivated?
            const name = b.dataset.modName || slug;

            const ok = window.confirmModal
                ? await window.confirmModal({
                    title: (off ? 'Re-activate ' : 'Remove ') + name + '?',
                    body: off
                        ? 'This turns the add-on back on. Its files are still on disk, so it starts working again immediately.'
                        : 'This deactivates the add-on — its pages and automations stop running. The FILES are kept, so you can re-activate it any time. Nothing is deleted, and its data stays in the database.',
                    confirm: off ? 'Re-activate' : 'Remove',
                    danger: !off,
                })
                : true;
            if (!ok) return;

            b.disabled = true;
            try {
                const r = await fetch(`/admin/extensions/module/${encodeURIComponent(slug)}/toggle`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok || !d.ok) throw new Error(d.message || `HTTP ${r.status}`);
                toast(d.message, 'success');
                window.location.reload();
            } catch (e) {
                toast((off ? 'Re-activate' : 'Remove') + ' failed: ' + e.message, 'error');
                b.disabled = false;
            }
        });
    });
}
