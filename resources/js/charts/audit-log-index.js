export default function init() {
    const filters = document.querySelector('[data-log-filters]');
      if (filters) {
        filters.addEventListener('click', (event) => {
          const button = event.target.closest('[data-log-filter]');
          if (!button) return;
          filters.querySelectorAll('[data-log-filter]').forEach(item => item.classList.remove('active'));
          button.classList.add('active');
        });
      }

      const table = document.querySelector('[data-audit-table]');
      if (table) {
        table.addEventListener('click', (event) => {
          const row = event.target.closest('[data-event-row]');
          if (!row) return;
          table.querySelectorAll('[data-event-row]').forEach(item => {
            delete item.dataset.active;
            item.classList.remove('bg-wa-bubble/30');
          });
          row.dataset.active = 'true';
          row.classList.add('bg-wa-bubble/30');
        });
      }
}
