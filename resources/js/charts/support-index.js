export default function init() {
    // filter pills
      document.querySelectorAll('[data-filter]').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('[data-filter]').forEach(x => delete x.dataset.active);
        b.dataset.active = 'true';
      }));

      // composer mode tabs
      document.querySelectorAll('[data-mode-tabs]').forEach(host => {
        host.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-mode]'); if (!btn) return;
          host.querySelectorAll('[data-mode]').forEach(b => delete b.dataset.active);
          btn.dataset.active = 'true';
        });
      });

      // chat drawer
      const drawer = document.querySelector('[data-chat-drawer]');
      const overlay = document.querySelector('[data-chat-overlay]');
      const idEl = document.querySelector('[data-chat-id]');

      function openChat(ticketId) {
        if (idEl) idEl.textContent = ticketId;
        drawer.style.display = 'flex';
        overlay.style.display = 'block';
        document.body.style.overflow = 'hidden';
      }
      function closeChat() {
        drawer.style.display = 'none';
        overlay.style.display = 'none';
        document.body.style.overflow = '';
      }

      document.querySelectorAll('[data-open-chat]').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          openChat(btn.dataset.openChat);
        });
      });
      overlay.addEventListener('click', closeChat);
      document.querySelectorAll('[data-close-chat]').forEach(b => b.addEventListener('click', closeChat));
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && drawer.style.display !== 'none') closeChat(); });

      // Assign menu
      const assignToggle = document.querySelector('[data-assign-toggle]');
      const assignMenu = document.querySelector('[data-assign-menu]');
      if (assignToggle && assignMenu) {
        assignToggle.addEventListener('click', (e) => {
          e.stopPropagation();
          assignMenu.style.display = assignMenu.style.display === 'none' ? 'block' : 'none';
        });
        document.addEventListener('click', (e) => {
          if (!e.target.closest('[data-assign-host]')) assignMenu.style.display = 'none';
        });
      }
}
