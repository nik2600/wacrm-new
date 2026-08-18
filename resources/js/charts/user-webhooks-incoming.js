/**
 * /webhooks/incoming — copy-to-clipboard for each generated webhook URL.
 * Buttons carry data-copy="<input-id>" (mirrors the admin settings pages).
 * Everything else on the page is plain HTML forms, so no other JS is needed.
 */
export default function init() {
  document.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const el = document.getElementById(btn.dataset.copy);
      if (!el) return;
      const value = el.value || el.textContent || '';
      const done = () => {
        const orig = btn.textContent;
        btn.textContent = 'Copied';
        setTimeout(() => { btn.textContent = orig; }, 1200);
      };
      if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(value).then(done).catch(() => { el.select?.(); });
      } else {
        el.select?.();
        try { document.execCommand('copy'); done(); } catch (_) { /* noop */ }
      }
    });
  });
}
