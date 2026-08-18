export default function init() {
    function toggleRoleMenu(e, btn) {
        e.stopPropagation();
        const menu = btn.nextElementSibling;
        document.querySelectorAll('.role-action-menu').forEach(m => { if (m !== menu) m.classList.add('hidden'); });
        menu.classList.toggle('hidden');
      }
      document.addEventListener('click', () => {
        document.querySelectorAll('.role-action-menu').forEach(m => m.classList.add('hidden'));
      });
}
