export default function init() {
    const REASON_LABELS = {
        bug: 'Something is broken',
        delivery: 'Message delivery',
        template: 'Template approval',
        billing: 'Billing & plans',
        integration: 'Integration help',
        account: 'Account access',
        other: 'Something else',
      };

      // Reason switching
      document.querySelectorAll('#reasons .reason').forEach(r => r.addEventListener('click', () => {
        document.querySelectorAll('#reasons .reason').forEach(x => {
          x.classList.remove('bg-paper-50/40','border-wa-deep');
          x.classList.add('border-transparent');
        });
        r.classList.add('bg-paper-50/40','border-wa-deep');
        r.classList.remove('border-transparent');
        document.getElementById('cat-label').textContent = REASON_LABELS[r.dataset.reason] || 'Support';
      }));

      // Attachments preview
      function handleAtt(input) {
        const list = document.getElementById('att-list');
        list.classList.remove('hidden');
        [...input.files].forEach(f => {
          const isImg = f.type.startsWith('image/');
          const row = document.createElement('div');
          row.className = 'flex items-center gap-3 border border-paper-200 rounded-lg p-3 bg-paper-50/40';
          row.innerHTML = `
            <span class="w-10 h-10 rounded-lg ${isImg ? 'bg-[#D9E5F2] text-[#13478A]' : 'bg-wa-mint text-wa-deep'} grid place-items-center shrink-0">
              ${isImg
                ? '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="3" width="12" height="10" rx="1.5"/><circle cx="6" cy="7" r="1.2"/><path d="m3 11 3-3 4 4 3-3 0 4"/></svg>'
                : '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 2h6l3 3v9H4z"/><path d="M10 2v3h3"/></svg>'}
            </span>
            <div class="flex-1 min-w-0">
              <div class="text-[12.5px] font-semibold truncate">${f.name}</div>
              <div class="text-[10.5px] text-ink-500 font-mono">${(f.size/1024).toFixed(1)} KB · ${f.type || 'file'}</div>
            </div>
            <button type="button" class="text-[11px] text-accent-coral font-semibold hover:underline">Remove</button>`;
          row.querySelector('button').onclick = () => { row.remove(); if (!list.children.length) list.classList.add('hidden'); };
          list.appendChild(row);
        });
      }
      window.handleAtt = handleAtt;
}
