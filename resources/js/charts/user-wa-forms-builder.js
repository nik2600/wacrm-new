// WhatsApp Forms builder — drag-reorder fields, edit per-field config,
// live WhatsApp-bubble preview, save draft + publish via Meta API.
//
// State is canonical in `state`. All inputs 2-way bound by `data-wf`
// for top-level fields; per-screen / per-field state lives nested.
// Save POSTs the entire snapshot.

export default function init() {
    const root = document.querySelector('[data-builder-state]');
    if (!root) return;

    const initial = window.WA_FORM_PAYLOAD || {};
    const state = {
        id:                 initial.id ?? null,
        title:              initial.title ?? '',
        purpose:            initial.purpose ?? '',
        audience_type:      initial.audience_type ?? 'lead_capture',
        submission_cap:     initial.submission_cap ?? 0,
        cap_reached_note:   initial.cap_reached_note ?? '',
        send_button_label:  initial.send_button_label ?? 'Send',
        thank_you_note:     initial.thank_you_note ?? '',
        status:             initial.status ?? 'draft',
        definition:         JSON.parse(JSON.stringify(initial.definition || { screens: [] })),
    };
    if (!state.definition.screens?.length) {
        state.definition.screens = [{ id: 'screen_1', label: 'Step 1', fields: [] }];
    }

    // ── flat-field binding ───────────────────────────────────────
    document.querySelectorAll('[data-wf]').forEach(el => {
        const key = el.dataset.wf;
        el.value = state[key] ?? '';
        const evt = el.tagName === 'SELECT' ? 'change' : 'input';
        el.addEventListener(evt, () => {
            state[key] = (el.type === 'number') ? parseInt(el.value || '0', 10) : el.value;
            renderPreview();
        });
    });

    // ── add-field buttons ────────────────────────────────────────
    document.querySelectorAll('[data-add-kind]').forEach(btn => {
        btn.addEventListener('click', () => addField(btn.dataset.addKind));
    });
    document.querySelector('[data-add-screen]')?.addEventListener('click', () => {
        const next = state.definition.screens.length + 1;
        state.definition.screens.push({ id: `screen_${next}`, label: `Step ${next}`, fields: [] });
        renderScreens();
        renderPreview();
    });

    function uid(prefix) {
        return prefix + '_' + Math.random().toString(36).slice(2, 8);
    }

    function defaultFieldFor(kind) {
        const base = { id: uid('fld'), kind, label: '', hint: '', required: false };
        switch (kind) {
            case 'heading':   return { ...base, label: 'Section heading' };
            case 'text':      return { ...base, label: 'Your name', required: true };
            case 'long_text': return { ...base, label: 'Tell us more' };
            case 'email':     return { ...base, label: 'Email address', required: true };
            case 'phone':     return { ...base, label: 'Phone' };
            case 'number':    return { ...base, label: 'Number' };
            case 'dropdown':  return { ...base, label: 'Choose one', options: ['Option A', 'Option B'] };
            case 'choice':    return { ...base, label: 'Pick one',   options: ['Yes', 'No'] };
            case 'multi':     return { ...base, label: 'Pick any',   options: ['Email', 'SMS', 'Call'] };
            case 'date':      return { ...base, label: 'Pick a date' };
            default:          return { ...base, label: 'Field' };
        }
    }

    function addField(kind) {
        // Drop onto the LAST screen by default — operators usually add
        // to the active step.
        const screen = state.definition.screens[state.definition.screens.length - 1];
        screen.fields.push(defaultFieldFor(kind));
        renderScreens();
        renderPreview();
    }

    // ── render screens + fields ──────────────────────────────────
    function renderScreens() {
        const wrap = document.querySelector('[data-screens]');
        wrap.innerHTML = '';
        state.definition.screens.forEach((screen, si) => wrap.appendChild(renderScreenCard(screen, si)));
        updateFieldsCount();
    }

    function renderScreenCard(screen, si) {
        const card = document.createElement('div');
        card.className = 'border border-paper-200 rounded-xl bg-paper-50/40';
        card.innerHTML = `
            <div class="px-4 py-2.5 border-b border-paper-200 bg-paper-50 flex items-center gap-2">
                <span class="font-mono text-[10px] text-ink-500 uppercase tracking-[0.14em]">Screen ${si + 1}</span>
                <input data-screen-label type="text" maxlength="80" class="flex-1 px-2 py-1 border border-paper-200 rounded bg-paper-0 text-[12px]"/>
                ${state.definition.screens.length > 1 ? `<button data-screen-del class="w-7 h-7 rounded hover:bg-accent-coral/15 text-accent-coral text-[12px]">×</button>` : ''}
            </div>
            <div data-fields class="p-3 space-y-2"></div>
        `;
        card.querySelector('[data-screen-label]').value = screen.label || '';
        card.querySelector('[data-screen-label]').addEventListener('input', e => {
            screen.label = e.target.value;
            renderPreview();
        });
        card.querySelector('[data-screen-del]')?.addEventListener('click', () => {
            state.definition.screens.splice(si, 1);
            renderScreens();
            renderPreview();
        });
        const fieldsWrap = card.querySelector('[data-fields]');
        screen.fields.forEach((f, fi) => fieldsWrap.appendChild(renderFieldRow(screen, f, si, fi)));
        if (screen.fields.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'px-3 py-6 text-center text-[11.5px] text-ink-500 italic border border-dashed border-paper-300 rounded';
            empty.textContent = 'Drop a field from the left rail';
            fieldsWrap.appendChild(empty);
        }
        return card;
    }

    function renderFieldRow(screen, field, si, fi) {
        const row = document.createElement('div');
        row.className = 'border border-paper-200 rounded-lg bg-paper-0 p-3';
        const hasOptions = ['dropdown', 'choice', 'multi'].includes(field.kind);
        row.innerHTML = `
            <div class="flex items-center gap-2 mb-2">
                <button type="button" data-up   title="Move up"   class="w-6 h-6 rounded hover:bg-paper-50 text-ink-500"><svg viewBox="0 0 16 16" class="w-3 h-3 inline" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10l4-4 4 4"/></svg></button>
                <button type="button" data-down title="Move down" class="w-6 h-6 rounded hover:bg-paper-50 text-ink-500"><svg viewBox="0 0 16 16" class="w-3 h-3 inline" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg></button>
                <span class="px-1.5 py-0.5 rounded font-mono text-[9.5px] uppercase tracking-[0.14em] bg-paper-100 text-ink-700">${field.kind}</span>
                <span class="font-mono text-[10px] text-ink-500 truncate">${field.id}</span>
                <span class="flex-1"></span>
                <label class="inline-flex items-center gap-1 text-[10.5px] text-ink-700"><input data-req type="checkbox" class="w-3 h-3 accent-wa-deep"/> required</label>
                <button type="button" data-del class="w-6 h-6 rounded hover:bg-accent-coral/15 text-accent-coral"><svg viewBox="0 0 16 16" class="w-3 h-3 inline" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9"/></svg></button>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input data-label type="text" placeholder="Label shown to customer" class="px-2 py-1.5 border border-paper-200 rounded bg-paper-0 text-[12px]"/>
                <input data-hint  type="text" placeholder="Hint / placeholder (optional)" class="px-2 py-1.5 border border-paper-200 rounded bg-paper-0 text-[12px]"/>
            </div>
            ${hasOptions ? `
            <div class="mt-2 p-2 rounded bg-paper-50 border border-paper-200">
                <div class="flex items-center justify-between mb-1.5"><span class="font-mono text-[10px] text-ink-500 uppercase tracking-[0.14em]">Options</span>
                    <button type="button" data-add-option class="px-2 py-0.5 rounded-full bg-wa-deep text-paper-0 text-[10.5px]">+ Add</button>
                </div>
                <div data-options class="space-y-1"></div>
            </div>` : ''}
        `;
        row.querySelector('[data-label]').value = field.label || '';
        row.querySelector('[data-hint]').value  = field.hint || '';
        row.querySelector('[data-req]').checked = !!field.required;
        row.querySelector('[data-label]').addEventListener('input', e => { field.label = e.target.value; renderPreview(); });
        row.querySelector('[data-hint]').addEventListener('input',  e => { field.hint  = e.target.value; renderPreview(); });
        row.querySelector('[data-req]').addEventListener('change',  e => { field.required = e.target.checked; });
        row.querySelector('[data-up]').addEventListener('click', () => {
            if (fi <= 0) return;
            [screen.fields[fi - 1], screen.fields[fi]] = [screen.fields[fi], screen.fields[fi - 1]];
            renderScreens(); renderPreview();
        });
        row.querySelector('[data-down]').addEventListener('click', () => {
            if (fi >= screen.fields.length - 1) return;
            [screen.fields[fi + 1], screen.fields[fi]] = [screen.fields[fi], screen.fields[fi + 1]];
            renderScreens(); renderPreview();
        });
        row.querySelector('[data-del]').addEventListener('click', () => {
            screen.fields.splice(fi, 1); renderScreens(); renderPreview();
        });
        if (hasOptions) {
            const optsWrap = row.querySelector('[data-options]');
            (field.options || []).forEach((opt, oi) => {
                const r = document.createElement('div'); r.className = 'flex gap-1.5';
                r.innerHTML = `<input class="flex-1 px-2 py-1 border border-paper-200 rounded bg-paper-0 text-[11.5px]"/><button type="button" class="w-6 h-6 rounded hover:bg-accent-coral/15 text-accent-coral text-[11px]">×</button>`;
                const [inp, del] = r.children;
                inp.value = opt;
                inp.addEventListener('input', () => { field.options[oi] = inp.value; renderPreview(); });
                del.addEventListener('click', () => { field.options.splice(oi, 1); renderScreens(); renderPreview(); });
                optsWrap.appendChild(r);
            });
            row.querySelector('[data-add-option]').addEventListener('click', () => {
                field.options = field.options || [];
                field.options.push('New option');
                renderScreens(); renderPreview();
            });
        }
        return row;
    }

    function updateFieldsCount() {
        const total = state.definition.screens.reduce((sum, s) => sum + (s.fields || []).length, 0);
        const el = document.querySelector('[data-fields-count]');
        if (el) el.textContent = `${total} field${total === 1 ? '' : 's'} · ${state.definition.screens.length} screen${state.definition.screens.length === 1 ? '' : 's'}`;
    }

    // ── live preview (WhatsApp bubble) ───────────────────────────
    function renderPreview() {
        const greetEl = document.querySelector('[data-preview-greet]');
        if (greetEl) greetEl.textContent = state.purpose ? state.purpose : `Hi! Please tap below to ${state.audience_type.replace('_', ' ')}.`;
        const openLbl = document.querySelector('[data-preview-open]');
        if (openLbl) openLbl.textContent = state.title || 'Open form';
        const btn = document.querySelector('[data-preview-button]');
        if (btn) btn.textContent = state.send_button_label || 'Send';

        const screen = state.definition.screens[0];
        const lblEl = document.querySelector('[data-preview-screen-label]');
        if (lblEl) lblEl.textContent = screen?.label || 'Step 1';

        const wrap = document.querySelector('[data-preview-fields]');
        if (!wrap) return;
        wrap.innerHTML = '';
        (screen?.fields || []).forEach(f => {
            const el = document.createElement('div');
            el.className = 'text-[11.5px]';
            switch (f.kind) {
                case 'heading':
                    el.innerHTML = `<div class="font-semibold text-ink-900 mt-1">${escapeHtml(f.label || 'Section')}</div>`;
                    break;
                case 'long_text':
                    el.innerHTML = `<div class="text-ink-700 mb-0.5">${escapeHtml(f.label)}${f.required ? ' <span class="text-accent-coral">*</span>' : ''}</div><textarea rows="2" class="w-full px-2 py-1 border border-paper-200 rounded bg-paper-50 text-[11px]" placeholder="${escapeHtml(f.hint || '')}"></textarea>`;
                    break;
                case 'dropdown':
                    el.innerHTML = `<div class="text-ink-700 mb-0.5">${escapeHtml(f.label)}${f.required ? ' <span class="text-accent-coral">*</span>' : ''}</div><select class="w-full px-2 py-1 border border-paper-200 rounded bg-paper-50 text-[11px]">${(f.options || []).map(o => `<option>${escapeHtml(o)}</option>`).join('')}</select>`;
                    break;
                case 'choice':
                    el.innerHTML = `<div class="text-ink-700 mb-0.5">${escapeHtml(f.label)}${f.required ? ' <span class="text-accent-coral">*</span>' : ''}</div>${(f.options || []).map(o => `<label class="block text-[11px]"><input type="radio" class="mr-1 accent-wa-deep"/>${escapeHtml(o)}</label>`).join('')}`;
                    break;
                case 'multi':
                    el.innerHTML = `<div class="text-ink-700 mb-0.5">${escapeHtml(f.label)}${f.required ? ' <span class="text-accent-coral">*</span>' : ''}</div>${(f.options || []).map(o => `<label class="block text-[11px]"><input type="checkbox" class="mr-1 accent-wa-deep"/>${escapeHtml(o)}</label>`).join('')}`;
                    break;
                case 'date':
                    el.innerHTML = `<div class="text-ink-700 mb-0.5">${escapeHtml(f.label)}${f.required ? ' <span class="text-accent-coral">*</span>' : ''}</div><input type="date" class="w-full px-2 py-1 border border-paper-200 rounded bg-paper-50 text-[11px]"/>`;
                    break;
                default:
                    el.innerHTML = `<div class="text-ink-700 mb-0.5">${escapeHtml(f.label)}${f.required ? ' <span class="text-accent-coral">*</span>' : ''}</div><input type="text" class="w-full px-2 py-1 border border-paper-200 rounded bg-paper-50 text-[11px]" placeholder="${escapeHtml(f.hint || '')}"/>`;
            }
            wrap.appendChild(el);
        });
    }

    // ── save + publish ───────────────────────────────────────────
    async function save() {
        const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
        const res = await fetch('/wa-forms/api/save', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                id: state.id,
                title: state.title,
                purpose: state.purpose,
                audience_type: state.audience_type,
                submission_cap: state.submission_cap,
                cap_reached_note: state.cap_reached_note,
                send_button_label: state.send_button_label,
                thank_you_note: state.thank_you_note,
                definition: state.definition,
            }),
        });
        const j = await res.json().catch(() => null);
        if (!res.ok || !j?.ok) {
            window.toast?.(j?.message || `Save failed (${res.status})`, 'error');
            return null;
        }
        state.id = j.id;
        if (!window.location.pathname.includes('/edit')) {
            window.history.replaceState({}, '', j.redirect_to);
        }
        window.toast?.('Draft saved', 'success');
        return j;
    }

    document.querySelector('[data-save-btn]')?.addEventListener('click', save);
    document.querySelector('[data-publish-btn]')?.addEventListener('click', async () => {
        if (!state.title.trim()) { window.toast?.('Form title is required.', 'error'); return; }
        const saved = await save();
        if (!saved) return;
        // POST publish via a tiny form so Laravel can redirect-with-flash.
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = `/wa-forms/${saved.id}/publish`;
        const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
        f.innerHTML = `<input type="hidden" name="_token" value="${csrf}"/>`;
        document.body.appendChild(f);
        f.submit();
    });

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    renderScreens();
    renderPreview();
}
