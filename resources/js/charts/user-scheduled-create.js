export default function init() {
    // Message-type tile — also swaps the compose pane in step 3
      function applyMsgType(type) {
        document.querySelectorAll('.compose-pane').forEach(p => p.classList.toggle('hidden', p.dataset.compose !== type));
        const pill = document.getElementById('compose-type-pill');
        if (pill) pill.textContent = type === 'plain' ? 'plain text' : type;
        syncPreviewForType(type);
      }
      function syncPreviewForType(type) {
        const body  = document.getElementById('pp-body');
        const img   = document.getElementById('pp-img');
        const map   = document.getElementById('pp-map');
        const eyebr = document.getElementById('pp-tpl-eyebrow');
        if (!body) return;

        // Reset every slot — only the relevant one for this message type
        // gets shown below.
        img?.classList.add('hidden');
        map?.classList.add('hidden');
        eyebr?.classList.add('hidden');

        if (type === 'plain') {
          body.textContent = document.getElementById('msg-content').value || 'your message will appear here…';
        } else if (type === 'template') {
          const sel = document.getElementById('tpl-sel-body');
          body.textContent = sel ? sel.textContent : 'Choose a template…';
          if (eyebr && document.getElementById('tpl-sel-id')?.textContent) {
            eyebr.textContent = document.getElementById('tpl-sel-id').textContent;
            eyebr.classList.remove('hidden');
          }
        } else if (type === 'media') {
          // Image preview: read the picked file as a DataURL and drop it
          // into <img id="pp-img"> above the caption.
          const f = document.getElementById('media-file')?.files?.[0];
          if (f && img) {
            if (f.type.startsWith('image/')) {
              const r = new FileReader();
              r.onload = e => { img.src = e.target.result; img.classList.remove('hidden'); };
              r.readAsDataURL(f);
            } else {
              // non-image (pdf/doc/audio) — show a generic file chip via the
              // "no preview" placeholder. We keep img hidden and let the
              // caption text describe it.
            }
          }
          body.textContent = document.getElementById('media-cap').value || '[ media attached ]';
        } else if (type === 'location') {
          updateLocPreview();
        }
      }
      document.querySelectorAll('#msg-types .msg-type').forEach(t => t.addEventListener('click', () => {
        document.querySelectorAll('#msg-types .msg-type').forEach(x => {
          x.classList.remove('border-wa-deep','bg-[#F0F8F6]');
          x.classList.add('border-paper-200','bg-white','hover:border-wa-deep');
        });
        t.classList.add('border-wa-deep','bg-[#F0F8F6]');
        t.classList.remove('border-paper-200','bg-white','hover:border-wa-deep');
        applyMsgType(t.dataset.type);
        // Composed-body source changes with msg type → placeholder
        // count needs to recompute against the new pane's textarea.
        refreshLiveStats();
      }));

      // Var quick-insert
      document.querySelectorAll('.var-btn').forEach(b => b.addEventListener('click', () => {
        const ta = document.getElementById('msg-content');
        const v = b.dataset.var;
        const start = ta.selectionStart, end = ta.selectionEnd;
        ta.value = ta.value.slice(0, start) + v + ta.value.slice(end);
        ta.dispatchEvent(new Event('input'));
        ta.focus();
        ta.selectionStart = ta.selectionEnd = start + v.length;
      }));

      // Media file pick
      function handleMediaPick(input) {
        if (!input.files || !input.files[0]) return;
        const f = input.files[0];
        document.getElementById('media-name').textContent = f.name;
        document.getElementById('media-size').textContent = (f.size / 1024).toFixed(1) + ' KB · ' + (f.type || 'file');
        document.getElementById('media-preview').classList.remove('hidden');
        // Refresh the right-rail preview now that we have a real image.
        syncPreviewForType('media');
      }
      window.handleMediaPick = handleMediaPick;

      // Location preview — show a real OpenStreetMap embed (no API key,
      // free, no SDK to load) inside the WhatsApp bubble. Same shape
      // WhatsApp shows: small map tile + name underneath.
      function updateLocPreview() {
        const lat = document.getElementById('loc-lat').value.trim();
        const lng = document.getElementById('loc-lng').value.trim();
        const nm  = document.getElementById('loc-name').value.trim();
        const out = document.getElementById('loc-readout');
        if (lat && lng) out.textContent = (nm ? nm + ' · ' : '') + lat + ', ' + lng;
        else out.textContent = 'Enter coordinates to drop a pin';

        // Only refresh the right-rail bubble when the location pane is open.
        if (!document.querySelector('.compose-pane[data-compose="location"]:not(.hidden)')) return;

        const body = document.getElementById('pp-body');
        const img  = document.getElementById('pp-img');
        const map  = document.getElementById('pp-map');
        const eyebr= document.getElementById('pp-tpl-eyebrow');

        img?.classList.add('hidden');
        eyebr?.classList.add('hidden');

        if (lat && lng && !isNaN(parseFloat(lat)) && !isNaN(parseFloat(lng))) {
          const la = parseFloat(lat), ln = parseFloat(lng);
          // bbox = small square around the pin so the marker sits centred.
          const d = 0.005;
          const bbox = `${ln - d},${la - d},${ln + d},${la + d}`;
          if (map) {
            map.src = `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${la},${ln}`;
            map.classList.remove('hidden');
          }
          if (body) body.textContent = nm || `📍 ${la}, ${ln}`;
        } else {
          if (map) { map.removeAttribute('src'); map.classList.add('hidden'); }
          if (body) body.textContent = nm ? `📍 ${nm}` : '[ enter coordinates ]';
        }
      }
      window.updateLocPreview = updateLocPreview;

      // Real templates handed in via data-templates on the form. Falls back
      // to an empty array if the operator hasn't approved any yet, in which
      // case the picker shows "no templates match" with a Manage link.
      let TEMPLATES = [];
      try {
        const raw = document.getElementById('schedForm')?.dataset.templates || '[]';
        TEMPLATES = JSON.parse(raw).map(t => ({
          // id stringified so `.includes(q)` in the search filter never
          // throws — the column is a numeric PK in PHP.
          id:   String(t.id),
          name: t.name || ('Template #' + t.id),
          cat:  t.cat  || 'Marketing',
          body: t.body || '',
          // light meta line — variable count + presence of footer/CTA based
          // on the body text. Best-effort, just for the picker UI.
          meta: ((t.body || '').match(/\{\{\w+\}\}/g) || []).length + ' vars',
        }));
      } catch (e) {
        console.error('Bad templates payload', e);
      }
      let tplCat = 'all';
      function openTplModal() {
        document.getElementById('tpl-modal').classList.remove('hidden');
        document.getElementById('tpl-modal').classList.add('flex');
        renderTplList();
      }
      function closeTplModal() {
        document.getElementById('tpl-modal').classList.add('hidden');
        document.getElementById('tpl-modal').classList.remove('flex');
      }
      function renderTplList() {
        const list = document.getElementById('tpl-list');
        const q = (document.getElementById('tpl-search').value || '').toLowerCase();
        list.innerHTML = '';
        TEMPLATES.filter(t => (tplCat === 'all' || t.cat === tplCat) && (t.name.toLowerCase().includes(q) || t.id.includes(q) || t.body.toLowerCase().includes(q)))
          .forEach(t => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'text-left border border-paper-200 rounded-lg p-3 hover:border-wa-deep transition bg-white';
            card.innerHTML = `
              <div class="flex items-start justify-between gap-2 mb-1.5">
                <div class="min-w-0">
                  <div class="font-mono text-[10px] text-ink-500">${t.id}</div>
                  <div class="font-semibold text-[13px] mt-0.5 truncate">${t.name}</div>
                </div>
                <span class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-wa-mint text-wa-deep">${t.cat.toUpperCase()}</span>
              </div>
              <div class="text-[12px] text-ink-700 leading-snug">${t.body}</div>
              <div class="mt-2 text-[10px] font-mono text-ink-500">${t.meta}</div>
            `;
            card.onclick = () => pickTemplate(t);
            list.appendChild(card);
          });
        if (!list.children.length) {
          list.innerHTML = '<div class="col-span-2 text-center py-6 text-[12px] text-ink-500">No templates match.</div>';
        }
      }
      function pickTemplate(t) {
        document.getElementById('tpl-empty').classList.add('hidden');
        document.getElementById('tpl-selected').classList.remove('hidden');
        const idEl = document.getElementById('tpl-sel-id');
        idEl.textContent = t.name;
        // Real DB id stashed on the dataset — the form submit handler reads
        // this when message_type=template and POSTs as `template_id`.
        idEl.dataset.tplId = t.id;
        // Mirror the pick into the hidden <select> the shared send-time
        // mapping panel listens on. This picker is a card grid with no
        // native select, which is the only reason the panel could not be
        // used here — one hidden field is cheaper than a second panel.
        const mirror = document.getElementById('tlm-template-mirror');
        if (mirror) {
            mirror.value = String(t.id);
            mirror.dispatchEvent(new Event('change', { bubbles: true }));
        }
        document.getElementById('tpl-sel-name').textContent = t.name;
        document.getElementById('tpl-sel-body').textContent = t.body;
        document.getElementById('tpl-sel-meta').textContent = t.meta;
        syncPreviewForType('template');
        // Newly-picked template body changes the placeholder count.
        refreshLiveStats();
        closeTplModal();
      }
      window.openTplModal = openTplModal;
      window.closeTplModal = closeTplModal;

      // Modal category tabs
      document.querySelectorAll('#tpl-cats button').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('#tpl-cats button').forEach(x => {
          x.classList.remove('bg-wa-deep','text-paper-0');
          x.classList.add('text-ink-600','hover:bg-paper-100');
        });
        b.classList.add('bg-wa-deep','text-paper-0');
        b.classList.remove('text-ink-600','hover:bg-paper-100');
        tplCat = b.dataset.cat;
        renderTplList();
      }));

      // Recipient-type tile
      // NOTE: the `.rcp-check` tick and the target panel are addressed with `?.`
      // on purpose. A card that shipped without a tick element (the Tags tile
      // did) made `querySelector('.rcp-check').classList` throw INSIDE this
      // handler, which aborted it before the panel-switching lines below — so
      // NO tab switched at all. Never hard-dereference a per-card sub-element here.
      document.querySelectorAll('#rcp-types .rcp-type').forEach(t => t.addEventListener('click', () => {
        document.querySelectorAll('#rcp-types .rcp-type').forEach(x => {
          x.classList.remove('border-wa-deep','bg-[#F0F8F6]');
          x.classList.add('border-paper-200','bg-white','hover:border-wa-deep');
          x.querySelector('.rcp-check')?.classList.replace('opacity-100','opacity-0');
        });
        t.classList.add('border-wa-deep','bg-[#F0F8F6]');
        t.classList.remove('border-paper-200','bg-white','hover:border-wa-deep');
        t.querySelector('.rcp-check')?.classList.replace('opacity-0','opacity-100');
        document.querySelectorAll('.rcp-panel').forEach(p => p.classList.add('hidden'));
        document.querySelector(`.rcp-panel[data-panel="${t.dataset.rt}"]`)?.classList.remove('hidden');
        refreshRecipientTotal();
      }));

      // Group cards — multi-select via the hidden checkbox inside each label
      // Visual highlight tracks the checkbox state so the user can select
      // multiple groups in one schedule.
      document.querySelectorAll('#group-list .grp-card .q-chk').forEach(chk => {
        chk.addEventListener('change', () => {
          const card = chk.closest('.grp-card');
          if (!card) return;
          if (chk.checked) {
            card.classList.add('border-wa-deep','bg-[#F0F8F6]');
            card.classList.remove('border-paper-200','bg-white','hover:border-wa-deep');
          } else {
            card.classList.remove('border-wa-deep','bg-[#F0F8F6]');
            card.classList.add('border-paper-200','bg-white','hover:border-wa-deep');
          }
          refreshRecipientTotal();
        });
      });

      // Queue rows — wire each checkbox to the total recalc.
      document.querySelectorAll('#queue-list .q-chk').forEach(chk =>
        chk.addEventListener('change', refreshRecipientTotal));

      // Per-message throttle + cost. Hardcoded for now — wallet config
      // will eventually own these (admin > pricing). Keep them in one
      // place so the only thing that changes when we wire those is a
      // single fetch on init.
      const THROTTLE_PER_MIN = 60;
      const COST_PER_MESSAGE = 0.007;

      // Numeric recipient count derived from current selections. We
      // split this off from `refreshRecipientTotal` so syncReview() can
      // call it without re-rendering the chip-style total label too.
      function currentRecipientCount() {
        const active = document.querySelector('#rcp-types .rcp-type.border-wa-deep');
        const rt = active ? active.dataset.rt : 'group';
        if (rt === 'group') {
          // Group counts aren't known client-side (encrypted contact_group);
          // best we can do is count *selected groups*. The server snaps
          // the real total when the schedule fires.
          return document.querySelectorAll('#group-list .grp-card .q-chk:checked').length;
        }
        if (rt === 'queue') {
          let sum = 0;
          document.querySelectorAll('#queue-list .q-chk:checked').forEach(c => sum += +c.dataset.count || 0);
          return sum;
        }
        if (rt === 'tag') {
          // Sum the per-tag member counts. Contacts carrying two selected tags
          // are double-counted here — the server de-duplicates at fire time,
          // so this is an upper bound the operator sees, not the send size.
          let sum = 0;
          document.querySelectorAll('.tag-chk:checked').forEach(c => sum += +c.dataset.count || 0);
          return sum;
        }
        if (rt === 'number') {
          const txt = document.querySelector('.rcp-panel[data-panel="number"] textarea');
          if (!txt) return 0;
          return txt.value.split(/[\s,;]+/).map(s => s.replace(/[^\d+]/g, '')).filter(s => /^\+?\d{8,15}$/.test(s)).length;
        }
        return 0;
      }

      // Format minutes into "~Xh Ym" / "~X min" / "<1 min".
      function fmtMins(mins) {
        if (mins < 1) return '< 1 min';
        if (mins < 60) return '~ ' + Math.ceil(mins) + ' min';
        const h = Math.floor(mins / 60);
        const m = Math.ceil(mins % 60);
        return '~ ' + h + 'h ' + (m > 0 ? m + 'm' : '');
      }

      // Count `{{...}}` placeholders in the composed body. The exact
      // body source depends on the message type — plain reads
      // #msg-content, template reads the selected template body,
      // media reads the caption, location reads the place name.
      function composedBody() {
        const type = (document.querySelector('#msg-types .msg-type.border-wa-deep')?.dataset.type) || 'plain';
        if (type === 'template') return document.getElementById('tpl-sel-body')?.textContent || '';
        if (type === 'media')    return document.getElementById('media-cap')?.value || '';
        if (type === 'location') return document.getElementById('loc-name')?.value || '';
        return document.getElementById('msg-content')?.value || '';
      }
      function countPlaceholders(text) {
        const m = (text || '').match(/\{\{[^{}]+\}\}/g);
        return m ? m.length : 0;
      }

      // The single source of truth for the Step 3 metric tiles. Called
      // by every input that affects audience / message body / msg type.
      function refreshLiveStats() {
        const n   = currentRecipientCount();
        const mins = n > 0 ? n / THROTTLE_PER_MIN : 0;
        const cost = n > 0 ? n * COST_PER_MESSAGE : 0;
        const vars = countPlaceholders(composedBody());

        const $ = id => document.getElementById(id);
        if ($('est-time'))      $('est-time').textContent      = n > 0 ? fmtMins(mins) : '—';
        if ($('est-time-sub'))  $('est-time-sub').textContent  = n > 0 ? ('at ' + THROTTLE_PER_MIN + ' msg/min for ' + n.toLocaleString() + (n === 1 ? ' recipient.' : ' recipients.')) : 'at ' + THROTTLE_PER_MIN + ' msg/min · pick recipients first.';
        if ($('vars-filled'))   $('vars-filled').textContent   = String(vars);
        if ($('vars-filled-sub')) $('vars-filled-sub').textContent = vars === 0 ? 'no placeholders detected.' : vars + ' ' + (vars === 1 ? 'placeholder' : 'placeholders') + ' will resolve at send.';
        if ($('cost-estimate'))     $('cost-estimate').textContent     = n > 0 ? ((window.WA_CURRENCY || '$') + cost.toFixed(2)) : '—';
        if ($('cost-estimate-sub')) $('cost-estimate-sub').textContent = n > 0 ? (n.toLocaleString() + ' × ' + (window.WA_CURRENCY || '$') + COST_PER_MESSAGE.toFixed(3) + ' / msg.') : (window.WA_CURRENCY || '$') + COST_PER_MESSAGE.toFixed(3) + ' per send.';
      }

      // Total is computed per-recipient-type:
      //   group  — we can't decrypt contact_group client-side, so we show
      //             "N groups selected" as a placeholder; server resolves.
      //   queue  — sum of total_recipients across the picked broadcasts.
      //             Real de-dup happens server-side, so this is an upper bound.
      //   number — count of valid lines in the textarea.
      function refreshRecipientTotal() {
        const active = document.querySelector('#rcp-types .rcp-type.border-wa-deep');
        const rt = active ? active.dataset.rt : 'group';
        const out = document.getElementById('rcp-total');
        if (out) {
          if (rt === 'group') {
            const sel = document.querySelectorAll('#group-list .grp-card .q-chk:checked').length;
            out.textContent = sel === 0 ? '0' : sel + (sel === 1 ? ' group' : ' groups') + ' selected';
          } else if (rt === 'queue') {
            let sum = 0;
            document.querySelectorAll('#queue-list .q-chk:checked').forEach(c => sum += +c.dataset.count || 0);
            out.textContent = sum > 0 ? sum.toLocaleString() + ' (max — de-duped at send)' : '0';
          } else if (rt === 'number') {
            const n = currentRecipientCount();
            out.textContent = n.toLocaleString();
          }
        }
        refreshLiveStats();
      }
      document.querySelector('.rcp-panel[data-panel="number"] textarea')?.addEventListener('input', refreshRecipientTotal);
      document.querySelectorAll('.tag-chk').forEach(c => c.addEventListener('change', refreshRecipientTotal));
      // Recompute stats whenever the operator edits the composer body
      // (plain text, caption, place name) so the placeholder count tile
      // updates in real time.
      ['msg-content', 'media-cap', 'loc-name'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', refreshLiveStats);
      });

      // Schedule mode tile
      document.querySelectorAll('#sch-modes .sch-mode').forEach(t => t.addEventListener('click', () => {
        document.querySelectorAll('#sch-modes .sch-mode').forEach(x => {
          x.classList.remove('border-wa-deep','bg-[#F0F8F6]');
          x.classList.add('border-paper-200','bg-white','hover:border-wa-deep');
          x.querySelector('.sch-check')?.classList.replace('opacity-100','opacity-0');
        });
        t.classList.add('border-wa-deep','bg-[#F0F8F6]');
        t.classList.remove('border-paper-200','bg-white','hover:border-wa-deep');
        // `?.` for the same reason as the recipient tiles above — a tile without
        // a tick must not abort the handler before the panel toggle below.
        t.querySelector('.sch-check')?.classList.replace('opacity-0','opacity-100');
        document.getElementById('recurring-opts')?.classList.toggle('hidden', t.dataset.mode !== 'recurring');
      }));

      // Day-of-week toggle
      document.querySelectorAll('.dow').forEach(d => d.addEventListener('click', () => {
        const on = d.classList.contains('bg-wa-deep');
        if (on) {
          d.classList.remove('bg-wa-deep','text-paper-0','border-wa-deep');
          d.classList.add('border-paper-200','hover:border-wa-deep');
        } else {
          d.classList.add('bg-wa-deep','text-paper-0','border-wa-deep');
          d.classList.remove('border-paper-200','hover:border-wa-deep');
        }
      }));

      // Stepper
      const TOTAL = 5;
      let cur = 1;
      const panes = document.querySelectorAll('.step-pane');
      const nodes = document.querySelectorAll('.step-node');
      const btnPrev = document.getElementById('btn-prev');
      const btnNext = document.getElementById('btn-next');
      const btnSubmit = document.getElementById('btn-submit');
      const curLabel = document.getElementById('cur-step');

      function paint(node, state) {
        const dot = node.querySelector('.dot');
        const lab = node.querySelector('.lab');
        const bar = node.querySelector('.bar');
        dot.className = 'dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px]';
        lab.className = 'lab text-[11.5px] whitespace-nowrap';
        if (state === 'active') {
          dot.classList.add('bg-paper-0','border-wa-deep','text-wa-deep','ring-4','ring-wa-deep/10');
          lab.classList.add('font-semibold','text-wa-deep');
        } else if (state === 'done') {
          dot.classList.add('bg-wa-deep','border-wa-deep','text-paper-0');
          lab.classList.add('font-medium','text-ink-700');
        } else {
          dot.classList.add('bg-paper-0','border-paper-200','text-ink-500');
          lab.classList.add('font-medium','text-ink-500');
        }
        if (bar) bar.className = 'bar flex-1 h-[2px] mx-2 rounded ' + (state === 'done' ? 'bg-wa-deep' : 'bg-paper-200');
      }

      function syncReview() {
        document.getElementById('rv-name').textContent = document.getElementById('sch-name').value || '—';
        // Sender summary — count of ticked senders from the unified picker.
        // A single ticked sender shows its label; 2+ roll up to a count so the
        // review cell stays readable.
        const checked = document.querySelectorAll('input[type="checkbox"][name="sender[]"]:checked');
        let dev = '—';
        if (checked.length === 1) {
            // Pull the visible label text from the checkbox's wrapping <label>.
            dev = (checked[0].closest('label')?.querySelector('.block')?.textContent || '1 sender').trim();
        } else if (checked.length > 1) {
            dev = checked.length + ' senders';
        }
        document.getElementById('rv-dev').textContent = dev;
        const t = document.querySelector('#msg-types .msg-type.border-wa-deep');
        document.getElementById('rv-type').textContent = t ? t.dataset.type[0].toUpperCase()+t.dataset.type.slice(1) : '—';
        const m = document.querySelector('#sch-modes .sch-mode.border-wa-deep');
        document.getElementById('rv-mode').textContent = m ? (m.dataset.mode === 'recurring' ? 'Recurring' : 'Once') : '—';
        const r = document.querySelector('#rcp-types .rcp-type.border-wa-deep');
        document.getElementById('rv-rcp').textContent = r ? r.dataset.rt[0].toUpperCase()+r.dataset.rt.slice(1) : '—';

        // Delivery cells — pull from the live stats engine. Recipients
        // and est-time stay in lockstep with Step 3 so the operator
        // sees the same numbers across both screens.
        const n    = currentRecipientCount();
        const mins = n > 0 ? n / THROTTLE_PER_MIN : 0;
        const cost = n > 0 ? n * COST_PER_MESSAGE : 0;
        const $ = id => document.getElementById(id);
        if ($('rv-rcp-count')) $('rv-rcp-count').textContent = n > 0 ? n.toLocaleString() : '—';
        if ($('rv-est-time'))  $('rv-est-time').textContent  = n > 0 ? fmtMins(mins) : '—';
        if ($('rv-cost'))      $('rv-cost').textContent      = n > 0 ? ((window.WA_CURRENCY || '$') + cost.toFixed(2)) : '—';
      }

      function show(n) {
        panes.forEach(p => p.classList.toggle('hidden', +p.dataset.step !== n));
        nodes.forEach(node => {
          const i = +node.dataset.n;
          paint(node, i === n ? 'active' : (i < n ? 'done' : 'todo'));
        });
        curLabel.textContent = n;
        btnPrev.disabled = n === 1;
        btnNext.classList.toggle('hidden', n === TOTAL);
        // Also toggle inline-flex: Tailwind orders .inline-flex after .hidden, so
        // "hidden inline-flex" stays visible. Removing inline-flex lets .hidden win.
        btnNext.classList.toggle('inline-flex', n !== TOTAL);
        btnSubmit.classList.toggle('hidden', n !== TOTAL);
        btnSubmit.classList.toggle('inline-flex', n === TOTAL);
        if (n === TOTAL) syncReview();
        cur = n;
      }
      // ── Per-step validation gate ─────────────────────────────────────
      // Block forward navigation (Next button OR a step-node jump) until
      // the current step's required fields are filled. Backward moves are
      // always allowed. Returns {ok, msg, el} so the caller can toast +
      // highlight the offending field.
      const $id = (id) => document.getElementById(id);
      function flashInvalid(el) {
        if (!el) return;
        el.classList.add('ring-2', 'ring-accent-coral/60', 'border-accent-coral');
        try { el.focus({ preventScroll: false }); } catch (_) {}
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => el.classList.remove('ring-2', 'ring-accent-coral/60', 'border-accent-coral'), 2600);
      }
      function validateStep(n) {
        if (n === 1) {
          const name = $id('sch-name');
          if (!name || !name.value.trim()) return { ok: false, msg: 'Enter a schedule name.', el: name };
          // Sender: the unified picker renders a checkbox list (name="sender[]")
          // of every connected sender across all enabled engines. Zero connected
          // senders renders a CTA instead of any checkbox, so an absent picker
          // means "connect a sender first".
          const senderBoxes = document.querySelectorAll('input[type="checkbox"][name="sender[]"]');
          const picker = document.querySelector('[data-sender-picker]');
          if (senderBoxes.length) {
            if (document.querySelectorAll('input[name="sender[]"]:checked').length === 0) {
              return { ok: false, msg: 'Pick at least one sender.', el: picker || $id('msg-types') };
            }
          } else {
            return { ok: false, msg: 'Connect a sender first.', el: $id('msg-types') };
          }
        }
        if (n === 2) {
          const rt = document.querySelector('#rcp-types .rcp-type.border-wa-deep')?.dataset.rt || 'group';
          if (rt === 'group') {
            if (document.querySelectorAll('#group-list .grp-card .q-chk:checked').length === 0) {
              return { ok: false, msg: 'Pick at least one group.', el: $id('group-list') };
            }
          } else if (rt === 'queue') {
            if (document.querySelectorAll('#queue-list .q-chk:checked').length === 0) {
              return { ok: false, msg: 'Pick at least one broadcast.', el: $id('queue-list') };
            }
          } else if (rt === 'number') {
            const ta = document.querySelector('.rcp-panel[data-panel="number"] textarea');
            if (pastedNumbers().length === 0) {
              return { ok: false, msg: 'Add at least one number.', el: ta };
            }
          }
        }
        if (n === 3) {
          const type = document.querySelector('#msg-types .msg-type.border-wa-deep')?.dataset.type || 'plain';
          if (type === 'plain' && !$id('msg-content')?.value.trim()) {
            return { ok: false, msg: 'Write your message.', el: $id('msg-content') };
          }
          if (type === 'template' && !$id('tpl-sel-id')?.dataset.tplId) {
            return { ok: false, msg: 'Choose a template.', el: $id('tpl-empty') };
          }
          if (type === 'media' && !$id('media-file')?.files?.[0]) {
            return { ok: false, msg: 'Attach a media file.', el: $id('media-drop') };
          }
          if (type === 'location') {
            const lat = $id('loc-lat'), lng = $id('loc-lng');
            if (!lat?.value.trim()) return { ok: false, msg: 'Enter a latitude.', el: lat };
            if (!lng?.value.trim()) return { ok: false, msg: 'Enter a longitude.', el: lng };
          }
        }
        if (n === 4) {
          const date = $id('sch-date');
          if (!date || !date.value) return { ok: false, msg: 'Set a date.', el: date };
          const time = $id('sch-time');
          if (!time || !time.value) return { ok: false, msg: 'Set a time.', el: time };
          // Recurring weekly cadence needs at least one day-of-week ticked.
          const mode = document.querySelector('#sch-modes .sch-mode.border-wa-deep')?.dataset.mode || 'once';
          if (mode === 'recurring') {
            const interval = (document.querySelector('#recurring-opts select')?.value || '').toLowerCase();
            const isWeekly = interval.includes('week');
            if (isWeekly && document.querySelectorAll('#recurring-opts .dow.bg-wa-deep').length === 0) {
              return { ok: false, msg: 'Pick at least one day.', el: $id('dow-row') };
            }
          }
        }
        return { ok: true };
      }
      // Guard a forward move; returns true if it's allowed to proceed.
      function gateForward(from) {
        const v = validateStep(from);
        if (!v.ok) { toast(v.msg, 'error'); flashInvalid(v.el); }
        return v.ok;
      }

      btnPrev.addEventListener('click', () => cur > 1 && show(cur - 1));
      btnNext.addEventListener('click', () => { if (cur < TOTAL && gateForward(cur)) show(cur + 1); });
      nodes.forEach(node => node.addEventListener('click', () => {
        const target = +node.dataset.n;
        // Free to jump backward; forward jumps must pass the current step.
        if (target <= cur || gateForward(cur)) show(target);
      }));
      show(1);

      // One-tap convenience: when the workspace has exactly ONE connected
      // sender, pre-tick it so single-engine/single-sender installs behave
      // exactly as before (the operator never had to pick a device). With 2+
      // senders we leave the choice to the operator.
      (function autoPickSoleSender() {
        const senderBoxes = document.querySelectorAll('input[type="checkbox"][name="sender[]"]');
        if (senderBoxes.length === 1 && !senderBoxes[0].checked) {
          senderBoxes[0].checked = true;
        }
      })();

      // Seed Step 3 stats on first render so the cells aren't blank
      // before the operator types anything.
      refreshLiveStats();

      // ── Submit ────────────────────────────────────────────────────────
      // Gather every field by id (the form was originally a static demo,
      // most inputs don't have name attributes). Build a FormData and POST
      // to /scheduled. On success redirect to the detail page.
      const form = document.getElementById('schedForm');
      const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
      const toast = (m, k = 'info') => window.toast ? window.toast(m, k === 'error' ? 'error' : 'success') : alert(m);

      const $val = id => (document.getElementById(id)?.value ?? '').toString().trim();
      const activeMsgType = () => document.querySelector('#msg-types .msg-type.border-wa-deep')?.dataset.type ?? 'plain';
      const activeMode    = () => document.querySelector('#sch-modes .sch-mode.border-wa-deep')?.dataset.mode ?? 'once';
      const activeRcpType = () => document.querySelector('#rcp-types .rcp-type.border-wa-deep')?.dataset.rt ?? 'group';

      function selectedDays() {
          const labels = ['sun','mon','tue','wed','thu','fri','sat'];
          return Array.from(document.querySelectorAll('#recurring-opts .dow.bg-wa-deep'))
              .map(b => labels.indexOf(b.textContent.trim().toLowerCase()))
              .filter(n => n >= 0);
      }

      function selectedGroups() {
          return Array.from(document.querySelectorAll('[data-panel="group"] .q-chk:checked'))
              .map(cb => cb.dataset.id || cb.value).filter(Boolean);
      }

      function selectedQueues() {
          // Each row in the queue panel is a label wrapping a hidden q-chk
          // with data-id = broadcasts.id.
          return Array.from(document.querySelectorAll('[data-panel="queue"] .q-chk:checked'))
              .map(cb => cb.dataset.id).filter(Boolean);
      }

      function pastedNumbers() {
          const ta = document.querySelector('[data-panel="number"] textarea');
          if (!ta) return [];
          return ta.value.split(/[\s,;]+/).map(s => s.replace(/[^\d+]/g, '')).filter(s => /^\+?\d{8,15}$/.test(s));
      }

      form?.addEventListener('submit', async (e) => {
          e.preventDefault();
          if (cur !== TOTAL) { show(TOTAL); return; }

          const fd = new FormData();
          fd.append('schedule_name', $val('sch-name'));
          // Unified multi-engine sender picker — the blade renders a grouped
          // checkbox list (name="sender[]") of composite engine:id keys inside
          // [data-sender-picker]. Each ticked sender becomes its own schedule
          // row server-side. When senders is empty the component renders a CTA
          // (no checkboxes), so this just sends nothing and the server 422s.
          document.querySelectorAll('input[type="checkbox"][name="sender[]"]:checked')
              .forEach(cb => fd.append('sender[]', cb.value));
          fd.append('send_date',     $val('sch-date'));
          fd.append('send_time',     $val('sch-time'));
          fd.append('timezone',      $val('sch-tz'));
          fd.append('schedule_type', activeMode());
          fd.append('message_type',  activeMsgType());

          const msgType = activeMsgType();
          if (msgType === 'plain')    fd.append('message_content', $val('msg-content'));
          if (msgType === 'template') fd.append('template_id',     document.getElementById('tpl-sel-id')?.dataset.tplId || '');
          if (msgType === 'media') {
              fd.append('message_content', $val('media-cap'));
              const file = document.getElementById('media-file')?.files?.[0];
              if (file) fd.append('media', file);
          }
          if (msgType === 'location') {
              fd.append('latitude',  $val('loc-lat'));
              fd.append('longitude', $val('loc-lng'));
              fd.append('message_content', $val('loc-name'));
          }

          const rcp = activeRcpType();
          fd.append('recipient_type', rcp);
          if (rcp === 'tag')    document.querySelectorAll('.tag-chk:checked')
                                  .forEach(c => fd.append('target_tags[]', c.value));
          if (rcp === 'group')  selectedGroups().forEach(id => fd.append('target_groups[]', id));
          if (rcp === 'queue')  selectedQueues().forEach(id => fd.append('target_queues[]', id));
          if (rcp === 'number') pastedNumbers().forEach(n  => fd.append('contact_numbers[]', n));

          if (activeMode() === 'recurring') {
              const interval = document.querySelector('#recurring-opts select')?.value || 'weekly';
              fd.append('repeat_interval', interval.replace('(s)','').replace(/s\)$/,'').replace(/[()]/g,'').replace('day','daily').replace('week','weekly').replace('month','monthly'));
              fd.append('repeat_every', document.querySelector('#recurring-opts input[type=number]')?.value || '1');
              selectedDays().forEach(d => fd.append('days_of_week[]', d));
          }

          // Step 5 "Send a test to me first". This form is assembled field by
          // field rather than serialized from the DOM, so a checkbox with a
          // name still never reaches the server unless it's appended here —
          // which is why the toggle appeared to do nothing.
          const sendTest = document.getElementById('sched-send-test');
          if (sendTest && sendTest.checked && !sendTest.disabled) {
              fd.append('send_test', '1');
          }

          const submitBtn = document.getElementById('btn-submit');
          submitBtn.disabled = true;
          submitBtn.textContent = 'Scheduling…';
          try {
              const res = await fetch('/scheduled', {
                  method: 'POST',
                  credentials: 'same-origin',
                  headers: {
                      'Accept': 'application/json',
                      'X-Requested-With': 'XMLHttpRequest',
                      'X-CSRF-TOKEN': csrf(),
                  },
                  body: fd,
              });
              const data = await res.json().catch(() => ({}));
              if (!res.ok) {
                  const firstErr = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
                  throw new Error(firstErr || data?.message || `HTTP ${res.status}`);
              }
              // Surface the server's message rather than a fixed "Scheduled." —
              // it carries the test-send outcome and any Node-registration
              // warning, both of which the operator needs to see.
              const test = data?.data?.test_send;
              const testFailed = test && test.ok === false;
              // The local toast() wrapper only distinguishes 'error' from
              // everything-else, so a failed preview uses 'error' to get a
              // colour that reads as "look at this". The message itself makes
              // clear the schedule was still saved.
              toast(data?.message || 'Scheduled.', testFailed ? 'error' : 'success');

              // Redirecting instantly wipes the toast. When the preview didn't
              // go out, hold long enough to read why before leaving the page.
              const go = () => { window.location.href = data?.data?.redirect_url || '/scheduled'; };
              if (testFailed) setTimeout(go, 4000); else go();
          } catch (err) {
              toast('Schedule failed: ' + err.message, 'error');
              submitBtn.disabled = false;
              submitBtn.textContent = 'Schedule message';
          }
      });
}
