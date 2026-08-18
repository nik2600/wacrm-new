/**
 * admin-audit-log-index.js — row click → fetch /{id} → fill the right-side
 * "Selected event" panel with full payload.
 */
export default function init() {
    const panel = document.querySelector('[data-event-detail]');
    if (!panel) return;

    const elId      = panel.querySelector('[data-d-id]');
    const elPills   = panel.querySelector('[data-d-pills]');
    const elFields  = panel.querySelector('[data-d-fields]');
    const elMetaW   = panel.querySelector('[data-d-meta-wrap]');
    const elMeta    = panel.querySelector('[data-d-meta]');

    const fmt = (s) => (s ?? '—');

    const renderRow = (label, value, opts = {}) => {
        const row = document.createElement('div');
        row.className = 'flex items-start justify-between gap-3';
        row.innerHTML = `<dt class="text-ink-500">${label}</dt><dd class="${opts.mono ? 'font-mono' : 'font-semibold'} text-right max-w-[210px] break-all">${value}</dd>`;
        return row;
    };

    const renderPill = (text, tone) => {
        const cls = tone === 'failure'
            ? 'bg-accent-coral/10 text-accent-coral border border-accent-coral/30'
            : tone === 'warning'
                ? 'bg-accent-amber/15 text-accent-amber'
                : 'bg-wa-mint text-wa-deep border border-wa-green/40';
        const span = document.createElement('span');
        span.className = `px-2 py-0.5 rounded-full ${cls} text-[10.5px] font-mono`;
        span.textContent = text;
        return span;
    };

    async function loadEvent(id) {
        try {
            const r = await fetch(`/admin/audit-log/${id}`, { headers: { 'Accept': 'application/json' } });
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            const d = await r.json();

            elId.textContent = '#' + d.id;
            elPills.innerHTML = '';
            elPills.appendChild(renderPill(d.result || 'success', d.result || 'success'));
            elPills.appendChild(renderPill(d.layer || 'workspace', 'success'));

            elFields.innerHTML = '';
            elFields.appendChild(renderRow('Action', `<span class="font-mono">${fmt(d.action)}</span>`));
            elFields.appendChild(renderRow('When',   `<span class="font-mono">${fmt(d.human_time)}</span>`));
            elFields.appendChild(renderRow('Actor',  d.actor ? `${fmt(d.actor.name)}<div class="text-[10.5px] text-ink-500 font-mono">${fmt(d.actor.email)}</div>` : 'System'));
            elFields.appendChild(renderRow('Workspace', d.workspace ? fmt(d.workspace.name) : '—'));
            elFields.appendChild(renderRow('Subject', `<span class="font-mono">${fmt(d.subject)}</span>`));
            elFields.appendChild(renderRow('IP',       `<span class="font-mono">${fmt(d.ip)}</span>`));
            if (d.user_agent) {
                elFields.appendChild(renderRow('User agent', `<span class="font-mono">${fmt(d.user_agent)}</span>`));
            }

            if (d.payload && Object.keys(d.payload).length) {
                elMetaW.classList.remove('hidden');
                elMeta.textContent = JSON.stringify(d.payload, null, 2);
            } else {
                elMetaW.classList.add('hidden');
                elMeta.textContent = '';
            }
        } catch (e) {
            elId.textContent = 'Error';
            elFields.innerHTML = `<div class="text-[12px] text-accent-coral">Failed to load: ${e.message}</div>`;
        }
    }

    // Row click handler.
    document.querySelectorAll('[data-event-row]').forEach((tr) => {
        tr.addEventListener('click', () => {
            document.querySelectorAll('[data-event-row]').forEach((x) => x.classList.remove('bg-wa-bubble/30'));
            tr.classList.add('bg-wa-bubble/30');
            loadEvent(tr.dataset.eventId);
        });
    });

    // Review-queue button click also opens the panel.
    document.querySelectorAll('[data-event-detail] ~ * [data-event-id], aside [data-event-id]').forEach((btn) => {
        btn.addEventListener('click', () => loadEvent(btn.dataset.eventId));
    });

    // Auto-load the first row if present.
    const first = document.querySelector('[data-event-row]');
    if (first) first.click();
}
