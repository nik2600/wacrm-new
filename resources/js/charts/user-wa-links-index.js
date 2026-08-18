// WhatsApp Links list — icon-button actions matching /chatbot-widgets
// and /devices. Search filters rows by name/slug haystack. All popups
// use window.toast / window.confirmDialog from app.js.
export default function init() {
  const csrf  = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const toast = (m, kind = 'success') => (window.toast ? window.toast(m, kind) : null);
  const confirmDialog = (opts) => {
    if (window.confirmDialog) return window.confirmDialog(opts);
    if (window.confirm(opts.message || 'Are you sure?')) opts.onConfirm?.();
  };

  document.querySelectorAll('[data-copy-link]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const slug = btn.dataset.slug;
      if (!slug) return;
      const url = `${location.origin}/l/${slug}`;
      try {
        await navigator.clipboard.writeText(url);
        toast('Short link copied to clipboard.', 'success');
      } catch {
        confirmDialog({
          eyebrow: 'Short link',
          title: 'Copy this manually',
          message: url,
          confirmText: 'Got it',
          cancelText: 'Close',
          tone: 'info',
        });
      }
    });
  });

  document.querySelectorAll('[data-delete]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id   = btn.dataset.id;
      const name = btn.dataset.name || 'this link';
      if (!id) return;
      confirmDialog({
        eyebrow: 'Delete link',
        title: `Delete "${name}"?`,
        message: 'The short link will stop working immediately. Click history is removed too.',
        confirmText: 'Delete link',
        cancelText: 'Keep',
        tone: 'danger',
        onConfirm: async () => {
          try {
            const res = await fetch(`/wa-links/${id}`, {
              method: 'DELETE',
              headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('http ' + res.status);
            btn.closest('.link-row')?.remove();
            toast('Link deleted.', 'success');
          } catch (e) {
            toast('Delete failed — ' + (e?.message || 'network error'), 'error');
          }
        },
      });
    });
  });

  const search = document.getElementById('links-search');
  if (search) {
    search.addEventListener('input', () => {
      const q = search.value.trim().toLowerCase();
      document.querySelectorAll('.link-row').forEach((row) => {
        const hay = row.dataset.searchHaystack || '';
        row.classList.toggle('hidden', q !== '' && !hay.includes(q));
      });
    });
  }
}
