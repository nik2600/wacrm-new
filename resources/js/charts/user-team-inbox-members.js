/*
 * /team-inbox/members — workspace member management.
 *
 * Single-table page: list members with role + status, change role inline,
 * remove a member, invite new ones via the same modal as /team-inbox.
 *
 * Permissions are enforced server-side; UI hides buttons the current user
 * can't trigger by checking the response from the inbox /bootstrap call.
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
        if (!r.ok) throw new Error(data?.message || `HTTP ${r.status}`);
        return data;
    });

    const $  = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const toast = (msg, kind = 'info') => {
        const el = $('#toast');
        if (!el) return;
        el.textContent = msg;
        el.className = `toast toast-${kind}`;
        el.style.display = 'block';
        clearTimeout(el._t);
        el._t = setTimeout(() => el.style.display = 'none', 3500);
    };
    const escape = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));

    const state = { members: [], roles: [], permissions: {}, search: '', roleFilter: 'all' };

    async function load() {
        try {
            const [boot, list] = await Promise.all([
                api('/team-inbox/api/bootstrap'),
                api('/team-inbox/api/members'),
            ]);
            state.permissions = boot.permissions || {};
            state.members     = list.members     || [];
            state.roles       = list.roles       || [];
            render();
        } catch (e) {
            toast('Failed to load members: ' + e.message, 'error');
        }
    }

    function render() {
        const tbody = $('#members-tbody');
        if (!tbody) return;
        const term = state.search.trim().toLowerCase();

        // role-filter pill counts (rendered in the sidebar, including "all")
        const counts = { all: state.members.length };
        for (const r of state.roles) counts[r] = 0;
        for (const m of state.members) {
            const k = (m.role === 'member') ? 'agent' : m.role;
            if (counts[k] !== undefined) counts[k]++;
        }
        $$('[data-role-count]').forEach(el => {
            el.textContent = counts[el.dataset.roleCount] ?? 0;
        });

        let filtered = state.members;
        if (state.roleFilter !== 'all') {
            filtered = filtered.filter(m => {
                const r = (m.role === 'member') ? 'agent' : m.role;
                return r === state.roleFilter;
            });
        }
        if (term) {
            filtered = filtered.filter(m => (m.name || '').toLowerCase().includes(term) || (m.email || '').toLowerCase().includes(term));
        }

        $$('[data-member-count]').forEach(el => el.textContent = filtered.length);

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-5 py-4">
              <div class="bg-paper-0 border border-dashed border-paper-200 rounded-2xl p-8 md:p-10 shadow-card text-center">
                <div class="font-serif text-[22px] md:text-[24px] leading-tight text-ink-900">No data found</div>
                <p class="mt-2 text-[13px] text-ink-500 max-w-2xl mx-auto">No members ${term || state.roleFilter !== 'all' ? 'match this view' : 'found yet'}.</p>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                  <button type="button" onclick="document.getElementById('invite-btn')?.click()" class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">Invite member</button>
                </div>
              </div>
            </td></tr>`;
            return;
        }

        const canChange = !!state.permissions['member.role.assign'];
        const canRemove = !!state.permissions['member.invite'];

        tbody.innerHTML = filtered.map(m => {
            const initials = (m.name || '?').split(/\s+/).map(s => s[0] || '').slice(0,2).join('').toUpperCase();
            const status = m.agent_status || 'offline';
            const dot = status === 'online' ? 'bg-wa-green' : status === 'away' ? 'bg-accent-amber' : status === 'busy' ? 'bg-accent-coral' : 'bg-ink-500/40';
            const joined = m.joined_at ? new Date(m.joined_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '<span class="text-ink-500 italic">pending</span>';
            const roleSelect = canChange
                ? `<select data-role-for="${m.id}" class="px-2.5 py-1 border border-paper-200 rounded-md bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">
                    ${state.roles.map(r => `<option value="${r}" ${r === m.role ? 'selected' : ''}>${escape(r.charAt(0).toUpperCase() + r.slice(1))}</option>`).join('')}
                  </select>`
                : `<span class="px-2 py-0.5 rounded-full text-[10.5px] font-mono uppercase tracking-wide bg-paper-100 text-ink-700">${escape(m.role || 'member')}</span>`;
            const removeBtn = canRemove
                ? `<button data-remove="${m.id}" data-name="${escape(m.name)}" class="w-8 h-8 rounded-full hover:bg-accent-coral/15 text-accent-coral grid place-items-center" title="Remove from workspace">
                     <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9"/></svg>
                   </button>` : '';
            return `<tr>
              <td class="px-5 py-3">
                <div class="flex items-center gap-2.5">
                  <span class="w-9 h-9 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold grid place-items-center shrink-0">${escape(initials)}</span>
                  <div class="min-w-0">
                    <div class="font-semibold text-[12.5px] truncate">${escape(m.name)}</div>
                    <div class="text-[10.5px] font-mono text-ink-500 truncate">${escape(m.email)}</div>
                  </div>
                </div>
              </td>
              <td class="px-3 py-3">${roleSelect}</td>
              <td class="px-3 py-3 hidden sm:table-cell">
                <span class="inline-flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full ${dot}"></span>
                  <span class="text-[11.5px] capitalize">${escape(status)}</span>
                </span>
              </td>
              <td class="px-3 py-3 hidden md:table-cell text-[11.5px] text-ink-700">${joined}</td>
              <td class="pl-3 pr-5 py-3 text-right">${removeBtn}</td>
            </tr>`;
        }).join('');

        // wire role-change selects
        $$('select[data-role-for]').forEach(sel => {
            sel.addEventListener('change', async () => {
                const id = parseInt(sel.dataset.roleFor, 10);
                const role = sel.value;
                try {
                    await api(`/team-inbox/api/members/${id}/role`, { method: 'PATCH', body: { role } });
                    toast('Role updated.', 'success');
                    load();
                } catch (e) {
                    toast('Update failed: ' + e.message, 'error');
                    load();
                }
            });
        });

        // wire remove buttons
        $$('button[data-remove]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = parseInt(btn.dataset.remove, 10);
                if (!confirm(`Remove ${btn.dataset.name} from this workspace? They'll lose access immediately.`)) return;
                try {
                    await api(`/team-inbox/api/members/${id}`, { method: 'DELETE' });
                    toast('Removed.', 'success');
                    load();
                } catch (e) {
                    toast('Remove failed: ' + e.message, 'error');
                }
            });
        });
    }

    // search
    let searchT;
    $('#member-search')?.addEventListener('input', e => {
        clearTimeout(searchT);
        state.search = e.target.value;
        searchT = setTimeout(render, 150);
    });

    // role-filter sidebar buttons. Active state mirrors the devices/attributes
    // pages — selected pill goes deep-green, others fall back to ink-700.
    $$('[data-role-filter]').forEach(btn => {
        btn.addEventListener('click', () => {
            state.roleFilter = btn.dataset.roleFilter;
            $$('[data-role-filter]').forEach(b => {
                const isActive = b.dataset.roleFilter === state.roleFilter;
                b.classList.toggle('bg-wa-deep', isActive);
                b.classList.toggle('text-paper-0', isActive);
                b.classList.toggle('font-semibold', isActive);
                b.classList.toggle('text-ink-700', !isActive);
                b.classList.toggle('hover:bg-paper-50', !isActive);
                // count chip color flip
                const chip = b.querySelector('[data-role-count]');
                if (chip) {
                    chip.classList.toggle('opacity-90', isActive);
                    chip.classList.toggle('text-ink-500', !isActive);
                }
            });
            render();
        });
    });

    // invite modal — same pattern as /team-inbox
    function openInvite() {
        const m = $('#invite-modal');
        if (!m) return;
        m.classList.remove('hidden');
        $('#invite-form')?.reset();
        $('#invite-result')?.classList.add('hidden');
        m.querySelector('input[name="name"]')?.focus();
    }
    function closeInvite() { $('#invite-modal')?.classList.add('hidden'); }

    $('#invite-btn')?.addEventListener('click', openInvite);
    $$('[data-close-invite]').forEach(b => b.addEventListener('click', closeInvite));

    $('#invite-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const body = Object.fromEntries(fd.entries());
        const submitBtn = $('#invite-submit');
        const result = $('#invite-result');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending…';
        try {
            const data = await api('/team-inbox/api/members/invite', { method: 'POST', body });
            result.classList.remove('hidden', 'bg-accent-coral/10', 'text-accent-coral', 'border-accent-coral/20');
            result.classList.add('bg-wa-mint/40', 'text-wa-deep', 'border', 'border-wa-deep/20');
            if (data.temp_password) {
                const tail = data.email_sent
                    ? 'Email sent. As a backup, the temp password is below — share if email doesn’t arrive:'
                    : 'Email couldn’t be sent — share these credentials manually:';
                result.innerHTML = `
                  <div class="font-semibold mb-1">${escape(data.member.name)} added.</div>
                  <div class="text-[11.5px]">${tail}</div>
                  <div class="font-mono text-[11.5px] bg-paper-0 border border-paper-200 rounded px-2 py-1.5 mt-1.5">
                    Email: ${escape(data.member.email)}<br/>
                    Password: ${escape(data.temp_password)}
                  </div>`;
            } else {
                result.textContent = data.message || 'Invited.';
            }
            await load();
            toast('Teammate invited.', 'success');
        } catch (err) {
            result.classList.remove('hidden', 'bg-wa-mint/40', 'text-wa-deep', 'border-wa-deep/20');
            result.classList.add('bg-accent-coral/10', 'text-accent-coral', 'border', 'border-accent-coral/20');
            result.textContent = err.message;
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send invite';
        }
    });

    load();
}
