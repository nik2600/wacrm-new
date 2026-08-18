/**
 * /admin/support inbox — slide-in ticket detail panel + quick actions.
 *
 * UX:
 *   - Click row → fetch /admin/support/{id} → render thread + meta
 *   - Quick-action forms (status / priority / assign / reply) submit
 *     natively, then the page reload appends ?open={id} so the panel
 *     re-opens on the same ticket without an SPA layer.
 */
export default function init() {
    const panel = document.querySelector('[data-support-panel]');
    if (!panel) return;
    const subjectEl = panel.querySelector('[data-panel-subject]');
    const numberEl  = panel.querySelector('[data-panel-number]');
    const metaEl    = panel.querySelector('[data-panel-meta]');
    const threadEl  = panel.querySelector('[data-panel-thread]');
    const replyEl   = panel.querySelector('[data-panel-reply]');
    const closeBtn  = panel.querySelector('[data-panel-close]');
    const miniForms = panel.querySelectorAll('[data-panel-mini-form]');
    const preselected = parseInt(panel.getAttribute('data-open-id') || '0', 10);

    let currentId = null;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g,
        (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    function setFormActions(id) {
        // Every form inside the panel posts to /admin/support/{id}/<verb>
        replyEl?.setAttribute('action', `/admin/support/${id}/reply`);
        miniForms.forEach((f) => {
            const verb = f.getAttribute('data-action'); // status | priority | assign
            f.setAttribute('action', `/admin/support/${id}/${verb}`);
        });
        // Playbook runner — action is set on submit (depends on pick).
        const pbForm = panel.querySelector('[data-panel-playbook]');
        if (pbForm) {
            pbForm.onsubmit = (e) => {
                const pid = pbForm.querySelector('select[name="playbook_id"]')?.value;
                if (!pid) { e.preventDefault(); return false; }
                pbForm.setAttribute('action', `/admin/support/${id}/run-playbook/${pid}`);
                return true;
            };
        }
    }

    function open(id) {
        currentId = id;
        setFormActions(id);
        panel.classList.remove('translate-x-full');
        threadEl.innerHTML = '<div class="text-ink-500 text-[12px]">Loading…</div>';
        // Reflect open ticket in the URL so a reload re-opens the panel.
        const u = new URL(window.location.href);
        u.searchParams.set('open', String(id));
        history.replaceState(null, '', u.toString());

        fetch(`/admin/support/${id}`, { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status))))
            .then((d) => render(d))
            .catch((e) => {
                threadEl.innerHTML = `<div class="text-accent-coral text-[12px]">Failed to load: ${esc(e?.message)}</div>`;
            });
    }

    function close() {
        panel.classList.add('translate-x-full');
        currentId = null;
        const u = new URL(window.location.href);
        u.searchParams.delete('open');
        history.replaceState(null, '', u.toString());
    }

    function render(d) {
        const t  = d.ticket || {};
        const ws = d.workspace;
        const ag = d.assigned_agent;
        subjectEl.textContent = t.subject || '(no subject)';
        numberEl.textContent  = t.ticket_number ? `#${t.ticket_number}` : '';
        const created = t.created_at ? new Date(t.created_at).toLocaleString() : '—';
        const first   = t.first_response_at ? new Date(t.first_response_at).toLocaleString() : '—';
        const wsHtml  = ws ? `<a href="/admin/workspaces/${ws.id}" class="text-wa-deep hover:underline">${esc(ws.name)}</a>` : '—';
        metaEl.innerHTML = `
            <div><span class="text-ink-500">Status:</span> <span class="font-mono">${esc(t.status)}</span></div>
            <div><span class="text-ink-500">Priority:</span> <span class="font-mono">${esc(t.priority)}</span></div>
            <div><span class="text-ink-500">Customer:</span> ${esc(t.name || t.email || '—')}</div>
            <div><span class="text-ink-500">Workspace:</span> ${wsHtml}</div>
            <div><span class="text-ink-500">Assigned:</span> ${ag ? esc(ag.name) : 'unassigned'}</div>
            <div><span class="text-ink-500">1st reply:</span> ${esc(first)}</div>
            <div class="col-span-2"><span class="text-ink-500">Opened:</span> ${esc(created)}</div>
        `;
        // Highlight which status / priority is current.
        panel.querySelectorAll('[data-panel-action-group="status"] form').forEach((f) => {
            const v = f.querySelector('input[name="status"]')?.value;
            f.querySelector('button')?.classList.toggle('ring-2', v === t.status);
            f.querySelector('button')?.classList.toggle('ring-wa-deep', v === t.status);
        });
        panel.querySelectorAll('[data-panel-action-group="priority"] form').forEach((f) => {
            const v = f.querySelector('input[name="priority"]')?.value;
            f.querySelector('button')?.classList.toggle('ring-2', v === t.priority);
            f.querySelector('button')?.classList.toggle('ring-wa-deep', v === t.priority);
        });
        // Pre-select the assignee in the dropdown.
        const sel = panel.querySelector('select[name="agent_user_id"]');
        if (sel) sel.value = ag?.id ? String(ag.id) : '';

        // SLA pills — show "12m left" or "8m over (breach)" per timer.
        const slaFmt = (info) => {
            if (!info) return '<span class="text-ink-500">no policy</span>';
            if (info.minutes_over != null) {
                return `<span class="text-accent-coral font-mono">${info.minutes_over}m over · ${esc(info.severity)}</span>`;
            }
            if (info.minutes_remaining != null) {
                const cls = info.severity === 'warn' ? 'text-accent-amber' : 'text-wa-deep';
                return `<span class="${cls} font-mono">${info.minutes_remaining}m left · ${esc(info.severity)}</span>`;
            }
            return '<span class="text-wa-deep font-mono">met</span>';
        };
        const slaFirst = panel.querySelector('[data-sla-first]');
        const slaRes   = panel.querySelector('[data-sla-resolution]');
        if (slaFirst) slaFirst.innerHTML = slaFmt(d.sla?.first_response);
        if (slaRes)   slaRes.innerHTML   = slaFmt(d.sla?.resolution);

        // Thread
        const initial = t.message ? `
            <div class="rounded-xl bg-paper-50 border border-paper-200 p-3">
                <div class="text-[10.5px] font-mono text-ink-500 mb-1">Customer · initial</div>
                <div class="whitespace-pre-wrap text-[12.5px]">${esc(t.message)}</div>
            </div>` : '';
        const msgs = (d.messages || []).map((m) => {
            const tone = m.role === 'admin'
                ? 'bg-wa-bubble border-wa-green/30'
                : (m.role === 'system' ? 'bg-paper-50 border-paper-200' : 'bg-paper-0 border-paper-200');
            const note = m.note ? '<span class="px-1.5 py-0.5 rounded-full bg-accent-amber/15 text-accent-amber text-[9.5px] font-mono uppercase ml-1">internal</span>' : '';
            const when = m.at ? new Date(m.at).toLocaleString() : '';
            return `<div class="rounded-xl border ${tone} p-3">
                <div class="flex items-center justify-between text-[10.5px] font-mono text-ink-500 mb-1">
                    <span>${esc(m.name)} · ${esc(m.role)}${note}</span><span>${esc(when)}</span>
                </div>
                <div class="whitespace-pre-wrap text-[12.5px]">${esc(m.body)}</div>
            </div>`;
        }).join('');
        threadEl.innerHTML = initial + msgs;
        // Focus the reply box
        replyEl?.querySelector('textarea')?.focus();
    }

    // Bind row clicks
    document.querySelectorAll('[data-support-row]').forEach((row) => {
        row.addEventListener('click', () => {
            const id = row.getAttribute('data-ticket-id');
            if (id) open(id);
        });
    });

    closeBtn?.addEventListener('click', close);

    // Re-open the panel after a page reload triggered by a panel form
    // submission (controllers return back() and we appended ?open=ID).
    if (preselected > 0) {
        open(preselected);
    }
}
