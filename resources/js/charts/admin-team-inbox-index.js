/*
 * /admin/team-inbox — platform-tier read-mostly view across every workspace.
 *
 * Two responsibilities:
 *   1. surface SaaS-operator signals (SLA breaches, spam, unassigned across
 *      all workspaces) with a workspace picker for drill-down.
 *   2. provide impersonation / spam-flagging / platform-notes — the
 *      moderation surface.
 *
 * No replying or assigning here on purpose. Mutations that touch a
 * customer's workspace happen via impersonation, where the SaaS staffer
 * temporarily becomes a workspace owner and the customer-side audit
 * trail records the action against an actual member.
 */
export default function init() {
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
    const api  = (path, opts = {}) => fetch(path, {
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
            ...(opts.body ? { 'Content-Type': 'application/json' } : {}),
            ...(opts.headers || {}),
        },
        ...opts,
        body: opts.body && typeof opts.body !== 'string' ? JSON.stringify(opts.body) : opts.body,
    }).then(async r => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok) {
            const msg = data?.message || `HTTP ${r.status}`;
            throw new Error(msg);
        }
        return data;
    });

    const $  = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const toast = (msg, kind = 'info') => {
        const el = $('#toast');
        if (!el) return;
        el.textContent = msg;
        el.className = `toast toast-${kind}`;
        el.style.display = 'block';
        clearTimeout(el._t);
        el._t = setTimeout(() => el.style.display = 'none', 3500);
    };

    const state = {
        permissions: {},
        workspaces: [],
        tab: 'sla_breach',
        workspaceFilter: '',
        items: [],
        active: null,
        targetWorkspaceId: null,
    };

    async function bootstrap() {
        try {
            const data = await api('/admin/team-inbox/api/bootstrap');
            state.permissions = data.permissions || {};
            state.workspaces  = data.workspaces  || [];
            renderStats();
            renderWorkspaceList();
            populateWorkspaceFilter();
            await loadQueue();
            loadAudit();
        } catch (e) {
            toast('Bootstrap failed: ' + e.message, 'error');
        }
    }

    function renderStats() {
        const w = state.workspaces;
        $('[data-stat="workspaces"]').textContent = w.length;
        $('[data-stat="open_total"]').textContent = w.reduce((a, x) => a + (x.open_count || 0), 0);
        $('[data-stat="breach_total"]').textContent = w.reduce((a, x) => a + (x.breach_count || 0), 0);
    }

    function renderWorkspaceList() {
        const list = $('#adm-workspace-list');
        if (!list) return;
        list.innerHTML = state.workspaces.slice(0, 30).map(w => `
          <div class="px-5 py-3 border-b border-paper-200 flex items-center gap-3">
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-[12.5px] truncate">${escape(w.name)}</div>
              <div class="text-[10.5px] text-ink-500 font-mono truncate">${escape(w.slug || '')} · ${escape(w.plan || 'starter')}</div>
            </div>
            <div class="text-right text-[11px] text-ink-700">
              <div>${w.open_count || 0} open</div>
              ${w.breach_count > 0 ? `<div class="text-accent-coral">${w.breach_count} breach</div>` : ''}
            </div>
          </div>
        `).join('');
    }

    function populateWorkspaceFilter() {
        const sel = $('#adm-workspace-filter');
        if (!sel) return;
        const opts = ['<option value="">All workspaces</option>']
            .concat(state.workspaces.map(w => `<option value="${w.id}">${escape(w.name)}</option>`));
        sel.innerHTML = opts.join('');
    }

    async function loadQueue() {
        try {
            const params = new URLSearchParams({ tab: state.tab });
            if (state.workspaceFilter) params.set('workspace_id', state.workspaceFilter);
            const data = await api(`/admin/team-inbox/api/queue?${params}`);
            state.items = data.items || [];
            renderQueue();
        } catch (e) {
            toast('Queue: ' + e.message, 'error');
        }
    }

    function renderQueue() {
        const list = $('#adm-conv-list');
        if (!list) return;
        if (state.items.length === 0) {
            list.innerHTML = `<div class="px-5 py-12 text-center text-[12px] text-ink-500">No conversations match this filter.</div>`;
            return;
        }
        list.innerHTML = state.items.map(c => `
          <button data-conv-id="${c.id}" data-workspace-id="${c.workspace_id}" class="adm-row w-full text-left px-5 py-3 border-b border-paper-200 hover:bg-paper-50 flex items-center gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="font-semibold text-[12.5px] truncate">${escape(c.title || 'Untitled')}</span>
                ${c.sla_breached ? `<span class="px-1.5 py-0.5 rounded text-[9.5px] font-mono bg-accent-coral/20 text-accent-coral">SLA</span>` : ''}
                ${c.is_spam ? `<span class="px-1.5 py-0.5 rounded text-[9.5px] font-mono bg-paper-100 text-ink-500">SPAM</span>` : ''}
              </div>
              <div class="text-[11.5px] text-ink-500 truncate">${escape(c.preview || '')}</div>
              <div class="text-[10.5px] font-mono text-ink-500 mt-0.5">${escape(c.workspace_name || '')} · ${escape(c.assignee_name || 'unassigned')} · ${escape(c.team_name || '—')}</div>
            </div>
            <div class="text-right text-[10.5px] font-mono text-ink-500 shrink-0">
              ${formatTime(c.last_message_at)}
            </div>
          </button>
        `).join('');
        list.querySelectorAll('[data-conv-id]').forEach(b => {
            b.addEventListener('click', () => openDrawer(parseInt(b.dataset.convId, 10), parseInt(b.dataset.workspaceId, 10)));
        });
    }

    async function openDrawer(convId, workspaceId) {
        state.targetWorkspaceId = workspaceId;
        $('#adm-drawer').classList.remove('hidden');
        try {
            const data = await api(`/admin/team-inbox/api/conversations/${convId}`);
            state.active = data.conversation;
            state.active.id = convId;
            $('#adm-drawer-title').textContent = data.conversation?.title || 'Conversation';
            $('#adm-drawer-workspace').textContent = data.conversation?.workspace?.name || '';
            renderDrawerThread(data.messages || []);
            renderDrawerNotes(data.notes || [], data.platform_notes || []);
            renderDrawerEvents(data.events || []);
        } catch (e) {
            toast('Conversation: ' + e.message, 'error');
        }
    }

    function renderDrawerThread(messages) {
        const el = $('#adm-drawer-thread');
        if (!el) return;
        el.innerHTML = messages.map(m => {
            const out = m.direction === 'out';
            const bg = out ? 'bg-wa-bubble' : 'bg-paper-50';
            return `<div class="${out ? 'ml-8' : 'mr-8'}">
              <div class="${bg} border border-paper-200 rounded-md px-3 py-1.5 text-[12px] whitespace-pre-wrap break-words">${escape(m.body || '')}</div>
              <div class="text-[9.5px] font-mono text-ink-500 mt-0.5 ${out ? 'text-right' : ''}">${formatTime(m.created_at)} · ${escape(m.direction)}</div>
            </div>`;
        }).join('') || '<div class="text-[11.5px] text-ink-500">No messages yet.</div>';
    }

    function renderDrawerNotes(workspaceNotes, platformNotes) {
        const wEl = $('#adm-drawer-notes');
        if (wEl) {
            wEl.innerHTML = workspaceNotes.map(n => `
              <div class="text-[11.5px] bg-accent-amber/10 border border-accent-amber/30 rounded-md px-2.5 py-1.5">
                <div class="font-mono text-[9.5px] text-ink-700 mb-0.5">${escape(n.author_id || '')} · ${formatTime(n.created_at)}</div>
                <div>${escape(n.body)}</div>
              </div>`).join('') || '<div class="text-[11.5px] text-ink-500">No internal notes.</div>';
        }
        const pEl = $('#adm-drawer-platform-notes');
        if (pEl) {
            pEl.innerHTML = platformNotes.map(n => `
              <div class="text-[11.5px] border-l-2 border-wa-deep pl-2.5 py-1">
                <div class="font-mono text-[9.5px] text-ink-700 mb-0.5">${escape(n.admin_name || '')} · ${escape(n.severity || 'info')} · ${formatTime(n.created_at)}</div>
                <div>${escape(n.body)}</div>
              </div>`).join('') || '<div class="text-[11.5px] text-ink-500">No platform notes yet.</div>';
        }
    }

    function renderDrawerEvents(events) {
        const el = $('#adm-drawer-events');
        if (!el) return;
        el.innerHTML = events.map(e => `
          <div class="flex items-center gap-2">
            <span class="font-mono text-[9.5px] uppercase tracking-wider text-ink-500 w-32 shrink-0">${escape(e.type)}</span>
            <span class="flex-1">${formatTime(e.created_at)}</span>
          </div>
        `).join('') || '<div class="text-ink-500">No events.</div>';
    }

    async function loadAudit() {
        try {
            const items = await api('/admin/team-inbox/api/audit?layer=platform');
            const el = $('#adm-audit-list');
            if (!el) return;
            el.innerHTML = (items || []).slice(0, 50).map(a => `
              <div class="px-5 py-2 border-b border-paper-200 text-[11.5px] flex items-center gap-3">
                <span class="font-mono text-[10px] uppercase tracking-wider text-ink-500 w-40 shrink-0">${escape(a.action)}</span>
                <span class="text-ink-700 flex-1 truncate">workspace_id ${a.workspace_id ?? '—'}</span>
                <span class="font-mono text-[10px] text-ink-500 shrink-0">${formatTime(a.created_at)}</span>
              </div>`).join('') || '<div class="px-5 py-4 text-[11.5px] text-ink-500">No audit events yet.</div>';
        } catch (e) {
            // Auditor role might not have this — silent.
        }
    }

    // ── Wiring ──────────────────────────────────────────────────────────
    $$('#adm-tabs [data-adm-tab]').forEach(b => {
        b.addEventListener('click', () => {
            $$('#adm-tabs .ti-tab').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
            state.tab = b.dataset.admTab;
            loadQueue();
        });
    });
    $('#adm-workspace-filter')?.addEventListener('change', e => {
        state.workspaceFilter = e.target.value;
        loadQueue();
    });

    $$('[data-close-drawer]').forEach(b => b.addEventListener('click', () => {
        $('#adm-drawer')?.classList.add('hidden');
    }));

    $('#adm-spam-btn')?.addEventListener('click', async () => {
        if (!state.active) return;
        if (!confirm('Mark this conversation as spam?')) return;
        try {
            await api(`/admin/team-inbox/api/conversations/${state.active.id}/spam`, { method: 'POST' });
            toast('Flagged as spam.', 'success');
            $('#adm-drawer')?.classList.add('hidden');
            loadQueue();
        } catch (e) { toast('Failed: ' + e.message, 'error'); }
    });

    $('#adm-platform-note-save')?.addEventListener('click', async () => {
        const body = $('#adm-platform-note').value.trim();
        const severity = $('#adm-platform-note-severity').value;
        if (!body || !state.active) return;
        try {
            await api(`/admin/team-inbox/api/conversations/${state.active.id}/note`, {
                method: 'POST', body: { body, severity },
            });
            $('#adm-platform-note').value = '';
            toast('Platform note added.', 'success');
            openDrawer(state.active.id, state.targetWorkspaceId);
            loadAudit();
        } catch (e) { toast('Failed: ' + e.message, 'error'); }
    });

    // Impersonation modal
    $('#adm-impersonate-btn')?.addEventListener('click', () => {
        if (!state.targetWorkspaceId) return toast('No target workspace.', 'error');
        $('#adm-impersonate-modal')?.classList.remove('hidden');
        $('#adm-impersonate-reason').value = '';
        $('#adm-impersonate-reason').focus();
    });
    $('#adm-impersonate-cancel')?.addEventListener('click', () => {
        $('#adm-impersonate-modal')?.classList.add('hidden');
    });
    $('#adm-impersonate-confirm')?.addEventListener('click', () => {
        const reason = $('#adm-impersonate-reason').value.trim();
        if (reason.length < 8) return toast('Reason must be at least 8 characters.', 'error');

        // POST as a form submit so we follow the redirect to /team-inbox
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/admin/impersonate/${state.targetWorkspaceId}`;
        form.style.display = 'none';
        const csrfInput = document.createElement('input');
        csrfInput.name = '_token'; csrfInput.value = csrf();
        const reasonInput = document.createElement('input');
        reasonInput.name = 'reason'; reasonInput.value = reason;
        form.appendChild(csrfInput);
        form.appendChild(reasonInput);
        document.body.appendChild(form);
        form.submit();
    });

    function escape(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    }
    function formatTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        const now = new Date();
        if (d.toDateString() === now.toDateString()) return d.toTimeString().slice(0,5);
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }

    bootstrap();
}
