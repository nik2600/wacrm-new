export default function init() {
    const tabs = document.querySelectorAll('[data-engine]');
      const panes = document.querySelectorAll('[data-engine-pane]');
      const guides = document.querySelectorAll('[data-guide]');
      const guideTitle = document.querySelector('[data-guide-title]');
      const titles = { 'twilio':'Twilio service', 'wa-api':'WhatsApp API', 'business-api':'Business API' };

      function activate(key) {
        tabs.forEach(t => {
          const on = t.dataset.engine === key;
          if (on) t.dataset.active = 'true'; else delete t.dataset.active;
          // NOTE: the "active" marker is server-rendered (.engine-optlabel shows
          // "• ACTIVE" on the default engine). This switcher only highlights the
          // VIEWED card + swaps its config pane/guide — it must NOT rewrite any
          // label text (that injected the stale "option N · active" string).
          t.classList.toggle('border-wa-deep/40', on);
          t.classList.toggle('border-paper-200', !on);
        });
        panes.forEach(p => p.hidden = p.dataset.enginePane !== key);
        guides.forEach(g => g.hidden = g.dataset.guide !== key);
        if (guideTitle) guideTitle.textContent = titles[key] || '';
      }

      tabs.forEach(t => t.addEventListener('click', () => activate(t.dataset.engine)));
      const initial = document.querySelector('[data-engine][data-active="true"]');
      if (initial) activate(initial.dataset.engine);
}
