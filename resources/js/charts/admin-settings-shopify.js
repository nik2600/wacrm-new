/**
 * /admin/settings/shopify — copy-to-clipboard for the URL hint fields
 * (App URL, Allowed redirection URL, Compliance webhook URL, Redirect
 * URI, Webhook endpoint). Buttons carry data-copy="<input-id>" and
 * the script reads that input's current value, copies it, and shows
 * a transient "Copied!" label.
 */
export default function init() {
    document.querySelectorAll('[data-copy]').forEach((btn) => {
        if (btn.__shopifyCopyWired) return;
        btn.__shopifyCopyWired = true;
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.copy);
            if (!target) return;
            const value = target.value || target.textContent || '';
            navigator.clipboard.writeText(value).then(() => {
                const orig = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(() => { btn.textContent = orig; }, 1200);
            }).catch(() => {
                // Fallback for very old browsers / non-HTTPS origins.
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
