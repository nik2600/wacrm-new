export default function init() {
    const grand = document.getElementById('grand-select');
      const counter = document.getElementById('perm-count');
      const allPerm = () => Array.from(document.querySelectorAll('.perm'));
      const allRowAll = () => Array.from(document.querySelectorAll('.select-all'));

      function refreshCount() {
        counter.textContent = document.querySelectorAll('.perm:checked').length;
      }
      function refreshGrand() {
        const all = allPerm();
        const checked = all.filter(c => c.checked).length;
        grand.checked = checked === all.length;
        grand.indeterminate = checked > 0 && checked < all.length;
      }
      function refreshRowAll(row) {
        const rowAll = row.querySelector('.select-all');
        const perms = row.querySelectorAll('.perm');
        const checked = Array.from(perms).filter(c => c.checked).length;
        rowAll.checked = checked === perms.length;
        rowAll.indeterminate = checked > 0 && checked < perms.length;
      }

      // Wire row Select All
      allRowAll().forEach(rowAll => {
        rowAll.addEventListener('change', e => {
          const row = e.target.closest('tr');
          row.querySelectorAll('.perm').forEach(c => c.checked = e.target.checked);
          refreshCount(); refreshGrand();
        });
      });

      // Wire individual perms
      allPerm().forEach(c => {
        c.addEventListener('change', () => {
          refreshRowAll(c.closest('tr'));
          refreshCount(); refreshGrand();
        });
      });

      // Wire grand select
      grand.addEventListener('change', e => {
        allPerm().forEach(c => c.checked = e.target.checked);
        allRowAll().forEach(c => { c.checked = e.target.checked; c.indeterminate = false; });
        refreshCount();
      });
}
