/**
 * /admin/support/team-inbox kanban drag-drop.
 *
 * Each card has data-kanban-card + data-ticket-id, each column has
 * data-kanban-list + data-kanban-col. Drag a card into a column → POST
 * to /admin/support/{id}/move with the column's status value, then
 * page reload (controller returns back()) so the column counts refresh.
 */
export default function init() {
    const lists = document.querySelectorAll('[data-kanban-list]');
    if (!lists.length) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let dragged = null;

    document.querySelectorAll('[data-kanban-card]').forEach((card) => {
        card.addEventListener('dragstart', (e) => {
            dragged = card;
            card.classList.add('opacity-50');
            // Use the card's id as the drag payload — the drop handler
            // reads it from data-ticket-id directly anyway.
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', card.getAttribute('data-ticket-id') || ''); } catch (_) {}
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('opacity-50');
            dragged = null;
            // Clear any lingering target highlights.
            lists.forEach((l) => l.parentElement?.classList.remove('ring-2', 'ring-wa-deep'));
        });
    });

    lists.forEach((list) => {
        const col = list.closest('[data-kanban-col]');
        const targetStatus = col?.getAttribute('data-kanban-col');

        list.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            col?.classList.add('ring-2', 'ring-wa-deep');
        });
        list.addEventListener('dragleave', () => {
            col?.classList.remove('ring-2', 'ring-wa-deep');
        });
        list.addEventListener('drop', async (e) => {
            e.preventDefault();
            col?.classList.remove('ring-2', 'ring-wa-deep');
            if (!dragged || !targetStatus) return;
            const id = dragged.getAttribute('data-ticket-id');
            const currentCol = dragged.closest('[data-kanban-col]')?.getAttribute('data-kanban-col');
            if (!id || currentCol === targetStatus) return;

            // Optimistically move the card so the user gets instant feedback.
            list.appendChild(dragged);

            // Persist via POST + redirect reload.
            try {
                const form = new FormData();
                form.append('_token', csrf);
                form.append('status', targetStatus);
                const r = await fetch(`/admin/support/${id}/move`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'text/html' },
                    body: form,
                });
                // Reload to refresh column counts + resolved_at side-effects.
                if (r.ok || r.status === 302) {
                    window.location.reload();
                } else {
                    console.error('[kanban] move failed: HTTP ' + r.status);
                    window.location.reload();
                }
            } catch (err) {
                console.error('[kanban] move error:', err);
                window.location.reload();
            }
        });
    });
}
