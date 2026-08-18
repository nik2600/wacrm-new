export default function init() {
    const mod = document.getElementById('module');
      const act = document.getElementById('action');
      const previews = ['preview-mod','preview-mod-2','preview-mod-3'];
      const previewActs = ['preview-act','preview-act-2'];
      const full = document.getElementById('preview-full');

      function refresh() {
        const m = (mod.value || 'module').trim();
        const a = (act.value || 'action').trim().toLowerCase().replace(/\s+/g, '_');
        previews.forEach(id => { document.getElementById(id).textContent = m; });
        previewActs.forEach(id => { document.getElementById(id).textContent = a; });
        full.textContent = `${m}.${a}`;
      }
      mod.addEventListener('change', refresh);
      act.addEventListener('input', refresh);
}
