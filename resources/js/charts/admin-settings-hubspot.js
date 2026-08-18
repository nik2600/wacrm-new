/**
 * /admin/settings/hubspot — copy-to-clipboard for the URL hint fields
 * (Redirect URI, OAuth callback). Buttons carry data-copy="<input-id>";
 * the script reads that input's value, copies it, and flashes "Copied!".
 */
export default function init() {
    document.querySelectorAll('[data-copy]').forEach((btn) => {
        if (btn.__hsCopyWired) return;
        btn.__hsCopyWired = true;
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.copy);
            if (!target) return;
            const value = target.value || target.textContent || '';
            navigator.clipboard.writeText(value).then(() => {
                const orig = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(() => { btn.textContent = orig; }, 1200);
            }).catch(() => {
                try {
                    target.select();
                    document.execCommand('copy');
                    const orig = btn.textContent;
                    btn.textContent = 'Copied!';
                    setTimeout(() => { btn.textContent = orig; }, 1200);
                } catch (_) { /* nothing else to do */ }
            });
        });
    });
}
