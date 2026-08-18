export default function init() {
    const TAB_LABELS = { general:'General', branding:'Branding', team:'Team & roles', notifications:'Notifications', aikeys:'AI keys', security:'Security', api:'API & webhooks', data:'Data & export', appearance:'Appearance' };
      const TAB_TITLES = {
        general:       ['Workspace <span class="italic text-wa-deep">settings</span>',  'Configure your workspace, team, and integrations.'],
        branding:      ['Branding',                                                     'Logo, favicon, colors. Used in invoices and the customer portal.'],
        team:          ['Team &amp; <span class="italic text-wa-deep">roles</span>',    'Members, roles, invitations.'],
        notifications: ['Notifications',                                                'Route alerts to email, Slack, and the in-app bell.'],
        aikeys:        ['AI <span class="italic text-wa-deep">keys</span>',             'Plug in your OpenAI, Gemini, Claude, and Mistral keys for the AI Assist node and Generate-with-AI flows.'],
        security:      ['Security',                                                     'Two-factor, sessions, audit log.'],
        api:           ['API &amp; <span class="italic text-wa-deep">webhooks</span>',  'API keys and event delivery to external endpoints.'],
        data:          ['Data &amp; export',                                            'Export contacts and conversations · delete the workspace.'],
        appearance:    ['Appearance',                                                   'Theme, fonts, density.'],
      };

      /* AI keys helpers */
      function togglePw(btn) {
        const inp = btn.parentElement.querySelector('input');
        inp.type = inp.type === 'password' ? 'text' : 'password';
      }
      function testKey(provider) {
        toast('Testing ' + provider + '…');
        setTimeout(() => toast('✓ ' + provider + ' key works'), 900);
      }
      function getTab() { const m = location.search.match(/tab=([a-z]+)/); return (m ? m[1] : 'general').toLowerCase(); }
      function activate(tab) {
        if (!TAB_TITLES[tab]) tab = 'general';
        document.querySelectorAll('[data-pane]').forEach(p => p.classList.toggle('hidden', p.dataset.pane !== tab));
        document.querySelectorAll('[data-tab]').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
        document.getElementById('bc-tab').textContent = TAB_LABELS[tab];
        const [title, desc] = TAB_TITLES[tab];
        document.getElementById('page-title').innerHTML = title;
        document.getElementById('page-desc').innerHTML  = desc;
      }
      document.querySelectorAll('[data-tab]').forEach(a => a.addEventListener('click', e => { e.preventDefault(); const t = a.dataset.tab; history.pushState(null,'','?tab='+t); activate(t); window.scrollTo({top:0, behavior:'smooth'}); }));
      window.addEventListener('popstate', () => activate(getTab()));
      function toast(msg) { const t = document.getElementById('toast'); t.textContent = msg; t.style.opacity='1'; t.style.transform='translate(-50%,-4px)'; clearTimeout(toast._t); toast._t = setTimeout(()=>{ t.style.opacity='0'; t.style.transform='translateX(-50%)'; }, 1700); }
      activate(getTab());

      // Branding dropzones — drag/drop + live preview after pick.
      document.querySelectorAll('[data-dropzone]').forEach(zone => {
        const kind  = zone.dataset.dropzone;
        const input = zone.querySelector('input[type="file"]');
        if (!input || input.disabled) return;

        const preview     = zone.querySelector(`[data-dz-preview="${kind}"]`);
        const previewImg  = preview?.querySelector('[data-dz-preview-img]');
        const previewName = preview?.querySelector('[data-dz-preview-name]');
        const current     = zone.querySelector(`[data-dz-current="${kind}"]`);

        const showPreview = (file) => {
          if (!file || !previewImg) return;
          const url = URL.createObjectURL(file);
          previewImg.src = url;
          if (previewName) previewName.textContent = file.name + ' · ' + (file.size / 1024 < 1024 ? Math.round(file.size / 1024) + ' KB' : (file.size / 1024 / 1024).toFixed(1) + ' MB');
          preview.classList.remove('hidden');
          current?.classList.add('hidden');
        };

        input.addEventListener('change', () => showPreview(input.files?.[0]));

        ['dragover', 'dragenter'].forEach(ev => zone.addEventListener(ev, e => {
          e.preventDefault();
          zone.classList.add('border-wa-deep', 'bg-paper-50/80');
        }));
        ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, e => {
          e.preventDefault();
          zone.classList.remove('border-wa-deep', 'bg-paper-50/80');
        }));
        zone.addEventListener('drop', e => {
          const file = e.dataTransfer?.files?.[0];
          if (!file) return;
          const dt = new DataTransfer();
          dt.items.add(file);
          input.files = dt.files;
          showPreview(file);
        });
      });

      // Brand color pickers — keep <input type=color> + <input type=text>
      // in sync both ways. Already wired via `oninput` in the markup;
      // this adds the reverse direction (text → swatch).
      document.querySelectorAll('input[name^="brand_"][type="text"]').forEach(text => {
        text.addEventListener('input', () => {
          const swatch = text.previousElementSibling;
          if (!swatch || swatch.type !== 'color') return;
          if (/^#[0-9a-fA-F]{6}$/.test(text.value)) swatch.value = text.value;
        });
      });
}
