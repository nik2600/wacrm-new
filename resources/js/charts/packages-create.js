export default function init() {
    const TOTAL = 6;
      let cur = 1;
      const nodes = document.querySelectorAll('.step-node');
      const panes = document.querySelectorAll('.step-pane');
      const btnPrev = document.getElementById('btn-prev');
      const btnNext = document.getElementById('btn-next');
      const curLab = document.getElementById('cur-step');

      window.show = function(n) {
        cur = Math.max(1, Math.min(TOTAL, n));
        panes.forEach(p => p.classList.toggle('hidden', String(p.dataset.step) !== String(cur)));
        nodes.forEach(node => {
          const idx = Number(node.dataset.n);
          const dot = node.querySelector('.dot');
          const lab = node.querySelector('.lab');
          const bar = node.querySelector('.bar');
          dot.classList.remove('border-wa-deep','text-wa-deep','ring-wa-deep/10','ring-4','bg-wa-deep','text-paper-0','border-paper-200','text-ink-500');
          lab.classList.remove('text-wa-deep','font-semibold','text-ink-500','font-medium','text-ink-900');
          if (idx < cur) {
            dot.classList.add('bg-wa-deep','text-paper-0','border-wa-deep');
            lab.classList.add('text-wa-deep','font-semibold');
            if (bar) bar.classList.replace('bg-paper-200','bg-wa-deep');
          } else if (idx === cur) {
            dot.classList.add('border-wa-deep','text-wa-deep','ring-4','ring-wa-deep/10');
            lab.classList.add('text-wa-deep','font-semibold');
            if (bar) bar.classList.replace('bg-wa-deep','bg-paper-200');
          } else {
            dot.classList.add('border-paper-200','text-ink-500');
            lab.classList.add('text-ink-500','font-medium');
            if (bar) bar.classList.replace('bg-wa-deep','bg-paper-200');
          }
        });
        curLab.textContent = cur;
        btnPrev.disabled = cur === 1;
        btnNext.classList.toggle('hidden', cur === TOTAL);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      };

      btnPrev.addEventListener('click', () => show(cur - 1));
      btnNext.addEventListener('click', () => show(cur + 1));
      nodes.forEach(node => node.addEventListener('click', () => show(Number(node.dataset.n))));
}
