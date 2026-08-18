/**
 * Sales Pipeline — Kanban board interactions.
 *
 *  - Drag a deal card to another column → PATCH /deals/{id}/stage.
 *  - "New deal" modal → POST /deals, then reload to re-render the board + KPIs.
 *
 * The board is server-rendered (Blade); this only wires the interactions.
 */
export default function userDealsBoard() {
    const board = document.getElementById('dl-board');
    if (!board) return;

    const baseUrl = board.dataset.stageUrl || '/deals';
    const csrf    = board.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';

    const toast = (msg, kind = 'info') => {
        if (window.toast) return window.toast(msg, kind);
        if (kind === 'error') console.error(msg); else console.log(msg);
    };

    /* ───────────────────────── drag + drop ───────────────────────── */

    let dragged = null;

    board.querySelectorAll('.dl-card').forEach(bindCard);

    // Deep-link: /deals?deal=ID (from a notification or "Open in inbox") opens
    // that deal's panel straight away. openPanel is hoisted, so calling it here
    // before its declaration is fine.
    try {
        const wantDeal = new URLSearchParams(window.location.search).get('deal');
        if (wantDeal && /^\d+$/.test(wantDeal)) openPanel(wantDeal);
    } catch (_) { /* no-op */ }

    function bindCard(card) {
        let moved = false;
        card.addEventListener('dragstart', (e) => {
            moved = true;
            dragged = card;
            card.classList.add('dragging');
            // Browsers (esp. Firefox) silently cancel the drop unless drag data
            // is set in dragstart. Mark the effect so the cursor shows "move".
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', card.dataset.dealId || ''); } catch (_) { /* IE guard */ }
            }
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            dragged = null;
            setTimeout(() => { moved = false; }, 0);
        });
        // A plain click (no drag) opens the detail panel.
        card.addEventListener('click', () => {
            if (moved) return;
            openPanel(card.dataset.dealId);
        });
    }

    board.querySelectorAll('.dl-col').forEach((col) => {
        col.addEventListener('dragover', (e) => {
            e.preventDefault();
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
            col.classList.add('dragover');
        });
        col.addEventListener('dragleave', () => col.classList.remove('dragover'));
        col.addEventListener('drop', (e) => {
            e.preventDefault();
            col.classList.remove('dragover');
            if (!dragged) return;

            const fromCol = dragged.closest('.dl-col');
            if (fromCol === col) return;

            const dealId  = dragged.dataset.dealId;
            const stageId = col.dataset.stageId;
            const body    = col.querySelector('[data-drop]');
            const empty   = body.querySelector('[data-empty]');
            if (empty) empty.remove();

            // Optimistic move.
            const card = dragged;
            body.appendChild(card);
            refreshCounts();

            patchStage(dealId, stageId)
                .then((res) => {
                    if (res?.stage?.is_won) toast('Deal marked Won', 'success');
                    else if (res?.stage?.is_lost) toast('Deal marked Lost', 'info');
                    else toast('Deal moved', 'success');
                })
                .catch((err) => {
                    // Revert on failure.
                    fromCol.querySelector('[data-drop]').appendChild(card);
                    refreshCounts();
                    toast(err?.message || 'Could not move the deal.', 'error');
                });
        });
    });

    async function patchStage(dealId, stageId) {
        const r = await fetch(`${baseUrl}/${dealId}/stage`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ stage_id: Number(stageId) }),
        });
        const data = await r.json().catch(() => ({}));
        if (!r.ok || !data.ok) throw new Error(data.message || `HTTP ${r.status}`);
        return data;
    }

    // Live-patch a board card from a serialized deal so panel edits show
    // immediately (title, amount, stage-column) without waiting for the
    // full reload that fires on panel-close. Keeps the board honest.
    function applyDealToCard(deal) {
        if (!deal || !deal.id) return;
        const card = board.querySelector(`.dl-card[data-deal-id="${deal.id}"]`);
        if (!card) return;
        const titleEl = card.querySelector('[data-card-title]');
        if (titleEl && typeof deal.title === 'string') titleEl.textContent = deal.title;
        const amtEl = card.querySelector('.dl-amount');
        if (amtEl && deal.value_display != null) amtEl.textContent = deal.value_display;
        // Move to the new stage column when the stage changed.
        const targetCol = board.querySelector(`.dl-col[data-stage-id="${deal.stage_id}"]`);
        const fromCol   = card.closest('.dl-col');
        if (targetCol && fromCol !== targetCol) {
            const body  = targetCol.querySelector('[data-drop]');
            const empty = body.querySelector('[data-empty]');
            if (empty) empty.remove();
            body.appendChild(card);
            refreshCounts();
        }
    }

    // Re-derive the "· N" card counts in each column header.
    function refreshCounts() {
        board.querySelectorAll('.dl-col').forEach((col) => {
            const n   = col.querySelectorAll('.dl-card').length;
            const lbl = col.querySelector('[data-count]');
            if (lbl) lbl.textContent = `${n}`;
        });
    }

    /* ───────────────────────── new-deal modal ───────────────────────── */

    const modal = document.getElementById('dl-new-modal');
    const form  = document.getElementById('dl-new-form');

    const open  = () => modal?.classList.add('open');
    const close = () => modal?.classList.remove('open');

    document.querySelectorAll('[data-deal-new]').forEach((b) => b.addEventListener('click', open));
    document.querySelectorAll('[data-deal-cancel]').forEach((b) => b.addEventListener('click', close));
    modal?.addEventListener('click', (e) => { if (e.target === modal) close(); });

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.textContent = '…'; }

        const fd = new FormData(form);
        const payload = Object.fromEntries(fd.entries());

        try {
            const r = await fetch(baseUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const data = await r.json().catch(() => ({}));
            if (!r.ok || !data.ok) throw new Error(data.message || `HTTP ${r.status}`);
            toast('Deal created', 'success');
            // Re-render the board (new card + refreshed KPIs) the simple way.
            window.location.reload();
        } catch (err) {
            toast(err?.message || 'Could not create the deal.', 'error');
            if (btn) { btn.disabled = false; btn.textContent = 'Create deal'; }
        }
    });

    /* ───────────────────────── pipeline settings ───────────────────────── */

    const settingsModal = document.getElementById('dl-settings-modal');
    const settingsForm = document.getElementById('dl-settings-form');
    document.querySelectorAll('[data-deal-settings]').forEach((b) => b.addEventListener('click', () => settingsModal?.classList.add('open')));
    document.querySelectorAll('[data-settings-cancel]').forEach((b) => b.addEventListener('click', () => settingsModal?.classList.remove('open')));
    settingsModal?.addEventListener('click', (e) => { if (e.target === settingsModal) settingsModal.classList.remove('open'); });

    settingsForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(settingsForm);
        const payload = { auto_from_orders: fd.get('auto_from_orders') ? 1 : 0, min_value: fd.get('min_value') || null };
        try {
            const r = await fetch(settingsModal.dataset.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await r.json().catch(() => ({}));
            if (!r.ok || !data.ok) throw new Error(data.message || `HTTP ${r.status}`);
            toast('Settings saved', 'success');
            settingsModal.classList.remove('open');
        } catch (err) { toast(err?.message || 'Could not save settings.', 'error'); }
    });

    /* ───────────────────────── detail slide-over ───────────────────────── */

    const panel = document.getElementById('dl-panel');
    const panelBase = panel?.dataset.base || '/deals';
    const chatBase = panel?.dataset.chat || '/chat';
    const panelBody = panel?.querySelector('[data-panel-body]');
    let openId = null;
    let boardDirty = false;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

    function closePanel() {
        panel?.classList.remove('open');
        openId = null;
        if (boardDirty) { boardDirty = false; window.location.reload(); }
    }
    panel?.addEventListener('click', (e) => { if (e.target === panel) closePanel(); });

    async function api(path, opts = {}) {
        const r = await fetch(path, {
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            ...opts,
        });
        const data = await r.json().catch(() => ({}));
        if (!r.ok || data.ok === false) throw new Error(data.message || `HTTP ${r.status}`);
        return data;
    }

    async function openPanel(id) {
        if (!panel) return;
        openId = id;
        panel.classList.add('open');
        panelBody.innerHTML = '<div class="text-center text-ink-400 py-20 text-sm">Loading…</div>';
        try {
            renderPanel(await api(`${panelBase}/${id}`));
        } catch (err) {
            panelBody.innerHTML = `<div class="text-center text-ink-500 py-20 text-sm">${esc(err.message || 'Failed to load')}</div>`;
        }
    }

    function renderActivity(a) {
        const body = a.type === 'stage_change' ? (a.label || 'Stage changed') : (a.body || '');
        const tick = a.type === 'task'
            ? `<button type="button" data-task-done="${a.id}" class="${a.done ? 'text-wa-deep' : 'text-ink-400'} hover:underline">${a.done ? 'Done' : 'Mark done'}</button>`
            : '';
        return `<div class="dl-act">
            <div class="text-[12px] text-ink-900">${esc(body)}</div>
            <div class="flex items-center gap-2 text-[11px] text-ink-400 mt-0.5">
                <span>${esc(a.user_name)}</span><span>·</span><span>${esc(a.created_at)}</span>
                ${a.due_at ? `<span>· due ${esc(a.due_at)}</span>` : ''}${tick}
            </div></div>`;
    }

    function renderPanel(data) {
        const d = data.deal, stages = data.stages || [], members = data.members || [], acts = data.activities || [];
        const badge = d.status === 'won' ? 'dl-badge-won' : (d.status === 'lost' ? 'dl-badge-lost' : 'dl-badge-open');
        const stageOpts = stages.map((s) => `<option value="${s.id}" ${s.id === d.stage_id ? 'selected' : ''}>${esc(s.name)}</option>`).join('');
        const ownerOpts = '<option value="">Unassigned</option>' + members.map((m) => `<option value="${m.id}" ${m.id === d.owner_user_id ? 'selected' : ''}>${esc(m.name)}</option>`).join('');

        // Customer card — the integration hub: links the deal to a contact and
        // surfaces the "Message on WhatsApp" quick action (the Interakt edge).
        const initials = (name) => (String(name || '?').match(/[a-z]+/gi) || ['?']).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
        const contactBlock = d.contact ? `
            <div class="rounded-xl border border-paper-200 p-3">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-full bg-wa-deep text-paper-0 grid place-items-center text-[12px] font-semibold shrink-0">${esc(initials(d.contact.name))}</span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[13px] font-semibold text-ink-900 truncate">${esc(d.contact.name)}</div>
                        <div class="text-[12px] text-ink-500">${esc(d.contact.phone)}</div>
                    </div>
                </div>
                ${d.contact.wa_phone ? `
                <div class="flex gap-2 mt-3">
                    <a href="https://wa.me/${esc(d.contact.wa_phone)}" target="_blank" rel="noopener" class="dl-btn dl-btn-primary text-center flex-1 inline-flex items-center justify-center gap-1.5">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.76.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.9-4.45 9.9-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm5.8 14.06c-.24.68-1.42 1.32-1.95 1.36-.5.04-.5.42-3.16-.66-2.66-1.08-4.32-3.82-4.45-4-.13-.18-1.06-1.41-1.06-2.69 0-1.28.67-1.91.91-2.17.24-.26.52-.32.7-.32l.5.01c.16.01.38-.06.59.45.24.58.81 2 .88 2.14.07.14.12.31.02.49-.09.18-.14.29-.27.45-.14.16-.29.36-.41.48-.14.14-.28.29-.12.57.16.28.71 1.17 1.53 1.9 1.05.94 1.94 1.23 2.22 1.37.28.14.44.12.6-.07.18-.21.69-.81.87-1.09.18-.28.36-.23.6-.14.24.09 1.55.73 1.81.87.27.14.44.21.51.32.07.12.07.66-.17 1.34Z"/></svg>
                        Message
                    </a>
                    <a href="${d.conversation_id ? `/team-inbox?c=${esc(d.conversation_id)}` : `${chatBase}?to=${esc(d.contact.wa_phone)}`}" class="dl-btn dl-btn-ghost text-center">${d.conversation_id ? 'Open in inbox' : 'Open chat'}</a>
                </div>` : ''}
            </div>` : `
            <div class="rounded-xl border border-dashed border-paper-200 p-3 text-[12px] text-ink-500">
                No contact linked yet — search to connect this deal to a person.
                <input type="text" data-contact-search placeholder="Search contacts…" class="dl-field mt-2">
                <div data-contact-results class="mt-1"></div>
            </div>`;

        const timeline = acts.length ? acts.map(renderActivity).join('') : '<div class="text-[12px] text-ink-400 py-3">No activity yet — add a note, log a call, or set a task above.</div>';

        panelBody.innerHTML = `
            <div class="dl-phead">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="dl-eyebrow" style="margin-bottom:3px">Deal · via ${esc(d.source)}</div>
                        <input type="text" data-edit="title" value="${esc(d.title)}" class="dl-title-input" aria-label="Deal name">
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" data-delete class="dl-iconbtn dl-iconbtn-danger" aria-label="Delete deal" title="Delete deal">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v5M14 11v5"/></svg>
                        </button>
                        <button type="button" data-close class="dl-iconbtn" aria-label="Close">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-2.5 mt-2.5">
                    <span class="dl-amount-lg">${esc(d.value_display)}</span>
                    <span class="dl-badge ${badge}">${esc(d.status.toUpperCase())}</span>
                    ${d.stage_name ? `<span class="text-[11px] text-ink-500">${esc(d.stage_name)}</span>` : ''}
                </div>
            </div>

            <div class="dl-pbody">
                <section class="mb-5">
                    <div class="dl-eyebrow">Customer</div>
                    ${contactBlock}
                </section>

                <section class="mb-5">
                    <div class="dl-eyebrow">Deal details</div>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-[11px] font-semibold text-ink-500">Value
                            <input type="number" min="0" step="0.01" data-edit="value" value="${d.value}" class="dl-field mt-1">
                        </label>
                        <label class="text-[11px] font-semibold text-ink-500">Stage
                            <select data-edit="stage_id" class="dl-field mt-1">${stageOpts}</select>
                        </label>
                        <label class="text-[11px] font-semibold text-ink-500">Owner
                            <select data-edit="owner_user_id" class="dl-field mt-1">${ownerOpts}</select>
                        </label>
                        <label class="text-[11px] font-semibold text-ink-500">Expected close
                            <input type="date" data-edit="expected_close_date" value="${esc(d.expected_close_date || '')}" class="dl-field mt-1">
                        </label>
                    </div>
                    <div class="flex gap-2 mt-3">
                        <button type="button" data-won class="dl-btn dl-btn-primary flex-1">Mark Won</button>
                        <button type="button" data-lost class="dl-btn dl-btn-ghost flex-1">Mark Lost</button>
                    </div>
                    ${d.lost_reason ? `<div class="text-[12px] text-ink-500 mt-2">Lost reason: ${esc(d.lost_reason)}</div>` : ''}
                </section>

                <section>
                    <div class="dl-eyebrow">Activity</div>
                    <div class="flex items-center gap-2 mb-2">
                        <select data-act-type class="dl-field" style="max-width:130px">
                            <option value="note">Note</option>
                            <option value="task">Task</option>
                            <option value="call">Call log</option>
                        </select>
                        <input type="date" data-act-due class="dl-field" style="max-width:150px;display:none">
                    </div>
                    <textarea data-act-body rows="2" placeholder="Add a note, log a call, or create a task…" class="dl-field"></textarea>
                    <button type="button" data-act-add class="dl-btn dl-btn-primary mt-2">Add to timeline</button>
                    <div data-timeline class="mt-4">${timeline}</div>
                </section>
            </div>`;
    }

    // Field edits (PATCH) + task-type toggle.
    panelBody?.addEventListener('change', async (e) => {
        const el = e.target;
        if (el.matches('[data-act-type]')) {
            const due = panelBody.querySelector('[data-act-due]');
            if (due) due.style.display = el.value === 'task' ? '' : 'none';
            return;
        }
        if (el.matches('[data-edit]')) {
            const body = {}; body[el.dataset.edit] = el.value;
            try {
                const res = await api(`${panelBase}/${openId}`, { method: 'PATCH', body: JSON.stringify(body) });
                boardDirty = true;
                if (res && res.deal) applyDealToCard(res.deal); // live board repaint
                toast('Saved', 'success');
                // Stage/value changes alter the panel header (badge, amount,
                // stage label) — re-render so the panel matches the card.
                if (el.dataset.edit === 'stage_id' || el.dataset.edit === 'value') openPanel(openId);
            } catch (err) { toast(err.message, 'error'); }
        }
    });

    // Contact link search.
    panelBody?.addEventListener('input', debounce(async (e) => {
        if (!e.target.matches('[data-contact-search]')) return;
        const box = panelBody.querySelector('[data-contact-results]');
        if (!box) return;
        try {
            const res = await api(`${panelBase}/contacts/search?q=${encodeURIComponent(e.target.value.trim())}`);
            box.innerHTML = res.data.map((c) => `<button type="button" data-link-contact="${c.id}" class="block w-full text-left text-[12px] py-1 hover:text-wa-deep">${esc(c.name)} · ${esc(c.phone)}</button>`).join('');
        } catch (_) { /* ignore */ }
    }, 250));

    // Action buttons (event-delegated since the panel re-renders).
    panelBody?.addEventListener('click', async (e) => {
        const el = e.target.closest('[data-close],[data-won],[data-lost],[data-act-add],[data-task-done],[data-delete],[data-link-contact]');
        if (!el) return;

        if (el.hasAttribute('data-close')) return closePanel();

        if (el.hasAttribute('data-won')) {
            try { await api(`${panelBase}/${openId}/won`, { method: 'POST', body: '{}' }); boardDirty = true; toast('Marked Won', 'success'); openPanel(openId); }
            catch (err) { toast(err.message, 'error'); }
            return;
        }
        if (el.hasAttribute('data-lost')) {
            try { await api(`${panelBase}/${openId}/lost`, { method: 'POST', body: '{}' }); boardDirty = true; toast('Marked Lost', 'info'); openPanel(openId); }
            catch (err) { toast(err.message, 'error'); }
            return;
        }
        if (el.hasAttribute('data-act-add')) {
            const type = panelBody.querySelector('[data-act-type]').value;
            const body = panelBody.querySelector('[data-act-body]').value.trim();
            const due = panelBody.querySelector('[data-act-due]').value;
            if (!body) return;
            try {
                const res = await api(`${panelBase}/${openId}/activity`, { method: 'POST', body: JSON.stringify({ type, body, due_at: due || null }) });
                panelBody.querySelector('[data-timeline]').insertAdjacentHTML('afterbegin', renderActivity(res.activity));
                panelBody.querySelector('[data-act-body]').value = '';
                toast('Added', 'success');
            } catch (err) { toast(err.message, 'error'); }
            return;
        }
        if (el.hasAttribute('data-task-done')) {
            try {
                const res = await api(`${panelBase}/${openId}/task/${el.dataset.taskDone}/done`, { method: 'POST', body: '{}' });
                el.textContent = res.done ? 'Done' : 'Mark done';
                el.classList.toggle('text-wa-deep', res.done);
                el.classList.toggle('text-ink-400', !res.done);
            } catch (err) { toast(err.message, 'error'); }
            return;
        }
        if (el.hasAttribute('data-link-contact')) {
            try { await api(`${panelBase}/${openId}`, { method: 'PATCH', body: JSON.stringify({ contact_id: el.dataset.linkContact }) }); boardDirty = true; openPanel(openId); }
            catch (err) { toast(err.message, 'error'); }
            return;
        }
        if (el.hasAttribute('data-delete')) {
            if (!confirm('Delete this deal? This cannot be undone.')) return;
            try { await api(`${panelBase}/${openId}`, { method: 'DELETE' }); toast('Deal deleted', 'success'); boardDirty = true; closePanel(); }
            catch (err) { toast(err.message, 'error'); }
        }
    });
}
