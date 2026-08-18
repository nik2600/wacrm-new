// Developers / API — copy the freshly minted raw key to the clipboard.
// The key element only exists right after a mint (one-time flash), so
// this is a no-op on a normal page load. All popups use window.toast
// from resources/js/app.js. Revoke uses the global data-confirm-form
// delegation, so no JS is needed for it here.
export default function init() {
    const toast = (m, kind = 'success') => (window.toast ? window.toast(m, kind) : null);

    document.querySelectorAll('[data-copy-key]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const code = document.getElementById('api-key-raw');
            const value = code ? code.textContent.trim() : '';
            if (!value) return;
            try {
                await navigator.clipboard.writeText(value);
                toast('API key copied to clipboard.', 'success');
            } catch {
                toast('Clipboard blocked — select the key and copy it manually.', 'info');
            }
        });
    });

    // Generic copy buttons: data-copy="<element-id>" copies that element's
    // value/text (base URL, quickstart snippet, …). Same pattern the admin
    // integration settings pages use.
    document.querySelectorAll('[data-copy]').forEach((btn) => {
        if (btn.__copyWired) return;
        btn.__copyWired = true;
        btn.addEventListener('click', async () => {
            const target = document.getElementById(btn.dataset.copy);
            if (!target) return;
            const value = (target.value || target.textContent || '').trim();
            if (!value) return;
            try {
                await navigator.clipboard.writeText(value);
                toast('Copied to clipboard.', 'success');
            } catch {
                toast('Clipboard blocked — select and copy manually.', 'info');
            }
        });
    });
}
