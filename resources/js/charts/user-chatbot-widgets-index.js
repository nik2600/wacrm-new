// Chatbot Widgets list — icon-button actions matching /devices.
// Search filters rows by name/slug haystack. All popups use
// window.toast / window.confirmDialog from resources/js/app.js.
export default function init() {
  const csrf  = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const toast = (m, kind = 'success') => (window.toast ? window.toast(m, kind) : null);
  const confirmDialog = (opts) => {
    if (window.confirmDialog) return window.confirmDialog(opts);
    if (window.confirm(opts.message || 'Are you sure?')) opts.onConfirm?.();
  };

  // -------- copy snippet --------
  document.querySelectorAll('[data-copy-snippet]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const token = btn.dataset.token;
      if (!token) return;
      // Extension-less `/embed` (not `/embed.js`): many nginx hosts serve
      // any *.js request off disk before it reaches PHP, so `.js` returned
      // a bare nginx 404 on the customer's site. `/embed` routes through
      // Laravel like every other page. See routes/web.php.
      const url = `${location.origin}/widget/${token}/embed`;
      const snippet = `<script defer src="${url}"><\/script>`;
      try {
        await navigator.clipboard.writeText(snippet);
        toast('Snippet copied — paste before </body> on your site.', 'success');
      } catch {
        confirmDialog({
          eyebrow: 'Embed snippet',
          title: 'Copy this manually',
          message: snippet,
          confirmText: 'Got it',
          cancelText: 'Close',
          tone: 'info',
        });
      }
    });
  });

  // -------- rotate token --------
  document.querySelectorAll('[data-rotate-token]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      if (!id) return;
      confirmDialog({
        eyebrow: 'Rotate token',
        title: 'Rotate this widget\'s embed token?',
        message: 'The current snippet on your site will stop working immediately. You\'ll need to copy and paste the new snippet.',
        confirmText: 'Rotate',
        cancelText: 'Cancel',
        tone: 'danger',
        onConfirm: async () => {
          try {
            const res = await fetch(`/chatbot-widgets/${id}/rotate-token`, {
              method: 'POST',
              headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            const json = await res.json();
            if (!res.ok || !json.ok) throw new Error(json.error || 'rotate failed');
            const row = btn.closest('.widget-row');
            const copyBtn = row?.querySelector('[data-copy-snippet]');
            if (copyBtn) copyBtn.dataset.token = json.embed_token;
            toast('Token rotated — copy the new snippet.', 'success');
          } catch (e) {
            toast('Rotate failed — ' + (e?.message || 'network error'), 'error');
          }
        },
      });
    });
  });

  // -------- delete --------
  document.querySelectorAll('[data-delete]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const name = btn.dataset.name || 'this widget';
      if (!id) return;
      confirmDialog({
        eyebrow: 'Delete widget',
        title: `Delete "${name}"?`,
        message: 'The embed snippet on your site will stop working immediately. Past conversations stay in your team inbox.',
        confirmText: 'Delete widget',
        cancelText: 'Keep',
        tone: 'danger',
        onConfirm: async () => {
          try {
            const res = await fetch(`/chatbot-widgets/${id}`, {
              method: 'DELETE',
              headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('http ' + res.status);
            btn.closest('.widget-row')?.remove();
            toast('Widget deleted.', 'success');
          } catch (e) {
            toast('Delete failed — ' + (e?.message || 'network error'), 'error');
          }
        },
      });
    });
  });

  // -------- client-side search filter --------
  const search = document.getElementById('widgets-search');
  if (search) {
    search.addEventListener('input', () => {
      const q = search.value.trim().toLowerCase();
      document.querySelectorAll('.widget-row').forEach((row) => {
        const hay = row.dataset.searchHaystack || '';
        row.classList.toggle('hidden', q !== '' && !hay.includes(q));
      });
    });
  }
}
