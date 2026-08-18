// Edit-campaign page behaviour. The edit form is a plain server-rendered
// PUT (no wizard, no AJAX) so this module only wires the two interactive
// widgets the compose section needs: the attachment-type pane toggle and
// the CTA button rows (kind dropdown + add / remove, capped at 3). Logic
// mirrors user-wa-campaigns-create.js but stands alone so the create
// wizard's stepper / preview code never runs on the edit page.
export default function init() {
  // -----------------------------------------------------------------
  // Attachment type → reveal the matching upload pane.
  // -----------------------------------------------------------------
  const attachType = document.querySelector('[data-edit-attach-type]');
  const panes = document.querySelectorAll('[data-edit-media-pane]');
  function syncMediaPane() {
    const sel = attachType ? attachType.value : 'none';
    panes.forEach((pane) => {
      const isActive = pane.getAttribute('data-edit-media-pane') === sel;
      pane.classList.toggle('hidden', !isActive);
      // Clear a stale file input on a pane the user switched away from so
      // it can never ride along on submit.
      if (!isActive) {
        pane.querySelectorAll('input[type="file"]').forEach((inp) => { inp.value = ''; });
      }
    });
  }
  if (attachType) {
    attachType.addEventListener('change', syncMediaPane);
    syncMediaPane();
  }

  // -----------------------------------------------------------------
  // CTA button rows — kind dropdown toggles url vs value, add caps at 3.
  // -----------------------------------------------------------------
  const list = document.getElementById('cc-btn-list');
  const addBtn = document.getElementById('cc-btn-add');

  function rows() { return list ? list.querySelectorAll('.cc-btn-row') : []; }

  function syncAddBtn() {
    if (!addBtn) return;
    addBtn.classList.toggle('hidden', rows().length >= 3);
  }

  function syncCtaKind(row) {
    const sel = row.querySelector('.cc-cta-type');
    if (!sel) return;
    const url = row.querySelector('.cc-cta-url');
    const val = row.querySelector('.cc-cta-value');
    const isUrl = sel.value === 'visit_website';
    if (url) url.classList.toggle('hidden', !isUrl);
    if (val) {
      val.classList.toggle('hidden', isUrl);
      val.placeholder = sel.value === 'call_phone' ? '+15551234567' : 'PROMO50';
    }
  }

  function wire(row) {
    syncCtaKind(row);
    row.querySelector('.cc-cta-type')?.addEventListener('change', () => syncCtaKind(row));
    row.querySelector('[data-cc-remove]')?.addEventListener('click', () => {
      row.remove();
      reindex();
      syncAddBtn();
    });
  }

  // Keep custom_buttons[i][...] indices contiguous after a removal so the
  // server's array binding stays clean.
  function reindex() {
    rows().forEach((row, i) => {
      row.querySelectorAll('select, input').forEach((el) => {
        const name = el.getAttribute('name');
        if (name) el.setAttribute('name', name.replace(/custom_buttons\[\d+\]/, `custom_buttons[${i}]`));
      });
    });
  }

  if (list) {
    rows().forEach(wire);
    syncAddBtn();
  }

  if (addBtn) {
    addBtn.addEventListener('click', () => {
      if (rows().length >= 3) return;
      const i = rows().length;
      const row = document.createElement('div');
      row.className = 'cc-btn-row grid grid-cols-[130px_1fr_1fr_28px] gap-1.5 items-center';
      row.setAttribute('data-kind', 'cta');
      row.innerHTML =
        `<select name="custom_buttons[${i}][type]" class="cc-cta-type w-full px-2 py-2 border border-paper-200 rounded-lg bg-white text-[12px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">` +
          `<option value="visit_website">Visit website</option>` +
          `<option value="copy_code">Copy code</option>` +
          `<option value="call_phone">Call phone</option>` +
        `</select>` +
        `<input type="text" name="custom_buttons[${i}][text]" maxlength="25" placeholder="Button text" class="cc-cta-text w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">` +
        `<input type="text" name="custom_buttons[${i}][url]" placeholder="https://..." class="cc-cta-url w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">` +
        `<input type="text" name="custom_buttons[${i}][value]" placeholder="PROMO50" class="cc-cta-value w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 hidden">` +
        `<span class="w-7 h-7 rounded-[7px] inline-flex items-center justify-center text-ink-500 cursor-pointer transition hover:bg-[#FFEDE8] hover:text-accent-coral" data-cc-remove title="Remove"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4l8 8M12 4l-8 8"/></svg></span>`;
      list.appendChild(row);
      wire(row);
      syncAddBtn();
    });
  }
}
