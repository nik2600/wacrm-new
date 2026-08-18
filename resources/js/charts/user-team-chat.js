/**
 * Team Chat — Slack-style internal channel for workspace teammates.
 *
 * Features:
 *   - Multiple channels per workspace (#general default + admin-created)
 *   - @mention typeahead with keyboard navigation
 *   - Drag-and-drop image attachments + inline previews
 *   - Reply threading
 *   - Member-invite flow with admin approval
 *
 * Endpoints come from window.tcEndpoints (see team-chat.blade.php).
 */
export default function init() {
    const $  = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const ep   = window.tcEndpoints || {};

    const state = {
        channels:    [],
        activeCh:    null,        // current channel object
        messages:    [],
        members:     [],
        me:          window.tcMe || { id: 0, name: '' },
        isAdmin:     false,
        replyTo:     null,
        attach:      null,
        mention:     null,        // { start, prefix }
        mentionIdx:  0,           // highlighted entry in popover
        mentionMatches: [],
    };

    if (!$('#tc-stream')) return; // not on team-chat page

    // ───────────────────────────── helpers
    function escape(s) {
        return String(s ?? '').replace(/[&<>"']/g, (m) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[m]));
    }
    function formatTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        const now = new Date();
        const sameDay = d.toDateString() === now.toDateString();
        return sameDay
            ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            : d.toLocaleDateString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }
    function initials(name) {
        return (name || '?').split(/\s+/).map(s => s[0] || '').slice(0, 2).join('').toUpperCase() || '?';
    }
    function avatarColor(id) {
        const palette = ['#0e7c5f', '#6b46c1', '#dc2626', '#c2410c', '#0369a1', '#a16207', '#0891b2', '#be185d'];
        return palette[(Number(id) || 0) % palette.length];
    }
    function humanSize(bytes) {
        const b = Number(bytes || 0);
        if (b < 1024) return b + ' B';
        if (b < 1024 * 1024) return Math.round(b / 1024) + ' KB';
        return (b / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function renderBody(body) {
        if (!body) return '';
        return escape(body).replace(/@\[([^\]]+)\]\((\d+)\)/g, (_, name, uid) => {
            const me = Number(uid) === Number(state.me.id);
            const cls = me ? 'bg-amber-200 text-amber-900' : 'bg-wa-bubble text-wa-deep';
            return `<span class="px-1 py-0.5 rounded ${cls} font-semibold">@${escape(name)}</span>`;
        }).replace(/\n/g, '<br>');
    }

    // ───────────────────────────── channel sidebar
    function renderChannels() {
        const list = $('#tc-channel-list');
        if (!list) return;
        if (state.channels.length === 0) {
            list.innerHTML = `<div class="text-[11px] text-ink-500 px-2 py-1">No channels yet</div>`;
            return;
        }
        list.innerHTML = state.channels.map(ch => {
            const active = state.activeCh && ch.id === state.activeCh.id;
            const unreadBadge = ch.unread > 0
                ? `<span class="ct tc-unread-pulse">${ch.unread}</span>` : '';
            const icon = ch.type === 'private'
                ? `<svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="7" width="10" height="7" rx="1"/><path d="M5 7V5a3 3 0 0 1 6 0v2"/></svg>`
                : `<span class="text-ink-400 font-mono">#</span>`;
            return `<button type="button" data-channel-id="${ch.id}"
                class="team-nav-btn w-full ${active ? 'active' : ''}">
                ${icon}
                <span class="truncate">${escape(ch.name)}</span>
                ${unreadBadge}
            </button>`;
        }).join('');
    }

    async function loadChannels() {
        try {
            const r = await fetch(ep.channelsIndex, { headers: { 'Accept': 'application/json' } });
            const data = await r.json();
            state.channels = data.channels || [];
            // Default to #general if no active
            if (!state.activeCh && state.channels.length > 0) {
                state.activeCh = state.channels.find(c => c.slug === 'general') || state.channels[0];
            }
            renderChannels();
        } catch (e) {
            console.error('[team-chat] channels load failed', e);
        }
    }

    function switchChannel(chId) {
        const ch = state.channels.find(c => Number(c.id) === Number(chId));
        if (!ch) return;
        state.activeCh = ch;
        renderChannels();
        // Header
        $('#tc-channel-name').textContent = '#' + ch.name;
        $('#tc-channel-type').textContent = ch.type === 'private' ? 'Private' : 'Channel';
        $('#tc-channel-desc').textContent = ch.description || 'Internal workspace chat';
        $('#tc-input').setAttribute('placeholder', `Message #${ch.name}… use @ to mention`);
        loadMessages();
    }

    // On phones/tablets the channel list is the home screen and the chat stream
    // is hidden until a channel is opened. These toggle #tc-frame.mobile-thread-open
    // (a no-op on desktop — the CSS only reacts below 1024px). Opening is driven by
    // a user TAP, not the boot auto-select, so we land on the channel list first.
    function openThreadMobile()  { document.getElementById('tc-frame')?.classList.add('mobile-thread-open'); }
    function closeThreadMobile() { document.getElementById('tc-frame')?.classList.remove('mobile-thread-open'); }

    // ───────────────────────────── messages
    function renderMessage(m) {
        const mine = m.is_mine;
        const align = mine ? 'flex-row-reverse' : '';
        const bubbleBg = mine ? 'bg-wa-mint' : 'bg-paper-0 border border-paper-200';
        const replyContext = (() => {
            if (!m.reply_to_id) return '';
            const parent = state.messages.find(x => x.id === m.reply_to_id);
            const parentName = parent ? parent.author_name : 'message';
            const parentBody = parent ? (parent.body || '').slice(0, 80) : '';
            return `<div class="text-[10.5px] border-l-2 border-wa-deep pl-2 mb-1 text-ink-500 truncate">
                <span class="font-semibold text-wa-deep">${escape(parentName)}</span> · ${escape(parentBody)}
            </div>`;
        })();
        const attach = m.attachment_url ? (() => {
            const isImg = (m.attachment_mime || '').startsWith('image/');
            if (isImg) {
                return `<a href="${m.attachment_url}" target="_blank" rel="noopener" class="block mt-1.5">
                    <img src="${m.attachment_url}" alt="${escape(m.attachment_name || '')}"
                         class="rounded-md max-w-[280px] max-h-[280px] object-cover hover:opacity-95 cursor-zoom-in">
                </a>`;
            }
            return `<a href="${m.attachment_url}" target="_blank" rel="noopener"
                class="flex items-center gap-2 bg-paper-100 rounded-md px-2.5 py-2 mt-1.5 hover:bg-paper-200 max-w-[280px]">
                <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 1H3v14h10V5z M9 1v4h4"/></svg>
                <span class="text-[12px] font-semibold truncate">${escape(m.attachment_name || 'attachment')}</span>
            </a>`;
        })() : '';
        return `<div class="flex items-start gap-2 ${align}" data-msg-id="${m.id}">
            <div class="w-8 h-8 rounded-full grid place-items-center text-[11px] font-semibold text-white shrink-0"
                 style="background:${avatarColor(m.user_id)}">${escape(initials(m.author_name))}</div>
            <div class="max-w-[70%] min-w-0">
                <div class="flex items-baseline gap-2 ${mine ? 'justify-end' : ''}">
                    <span class="text-[12px] font-semibold text-ink-800">${escape(m.author_name)}</span>
                    <span class="text-[10px] font-mono text-ink-400">${formatTime(m.created_at)}</span>
                    ${m.edited_at ? '<span class="text-[10px] font-mono text-ink-400">(edited)</span>' : ''}
                </div>
                <div class="${bubbleBg} rounded-md px-3 py-2 mt-0.5 text-[13px] leading-snug break-words">
                    ${replyContext}
                    ${m.body ? `<div>${renderBody(m.body)}</div>` : ''}
                    ${attach}
                </div>
                <div class="flex items-center gap-1.5 mt-1 ${mine ? 'justify-end' : ''}">
                    <button type="button" data-act="reply" class="text-[10px] text-ink-400 hover:text-wa-deep">Reply</button>
                    ${(mine || state.isAdmin) ? `<button type="button" data-act="delete" class="text-[10px] text-ink-400 hover:text-red-600">Delete</button>` : ''}
                </div>
            </div>
        </div>`;
    }

    function renderStream() {
        const stream = $('#tc-stream');
        if (!stream) return;
        if (state.messages.length === 0) {
            // Centred in the stream rather than pinned to the top — on a phone the
            // panel is full-height, so top-aligned text left a screen of dead space
            // under it. (Also drops a stray emoji; icons here are SVG only.)
            stream.innerHTML = `<div class="h-full min-h-[40vh] flex flex-col items-center justify-center text-center px-6">
                <svg viewBox="0 0 24 24" class="w-8 h-8 text-ink-300 mb-3" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.5 8.5 0 0 1-3.8-.9L3 21l1.9-5.1A8.4 8.4 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5z"/>
                </svg>
                <div class="text-[13px] text-ink-500">No messages in #${escape(state.activeCh?.name || '')} yet</div>
                <div class="text-[11.5px] text-ink-400 mt-1">Say hello to get the channel started.</div>
            </div>`;
            return;
        }
        let lastDate = '';
        const html = state.messages.map(m => {
            const d = new Date(m.created_at);
            const dateLabel = d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
            let sep = '';
            if (dateLabel !== lastDate) {
                sep = `<div class="text-center my-3"><span class="px-2.5 py-0.5 rounded-full bg-paper-100 text-[10.5px] font-mono text-ink-500">${escape(dateLabel)}</span></div>`;
                lastDate = dateLabel;
            }
            return sep + renderMessage(m);
        }).join('');
        stream.innerHTML = html;
        stream.scrollTop = stream.scrollHeight;
    }

    // Full reload — used on channel switch + initial load. Wipes the
    // stream and re-renders from scratch.
    async function loadMessages() {
        if (!state.activeCh) return;
        try {
            const r = await fetch(`${ep.index}?channel_id=${state.activeCh.id}`, { headers: { 'Accept': 'application/json' } });
            const data = await r.json();
            state.messages = data.messages || [];
            state.members  = data.members  || [];
            state.me       = data.me       || state.me;
            state.isAdmin  = !!data.me?.is_admin;
            if (data.channel && state.activeCh) {
                state.activeCh.description = data.channel.description;
                $('#tc-channel-desc').textContent = data.channel.description || 'Internal workspace chat';
            }
            $('#tc-member-count').textContent = state.members.length;
            renderStream();
            renderMembers('');
            const lastId = state.messages.length > 0 ? state.messages[state.messages.length - 1].id : 0;
            if (lastId > 0) markRead(lastId);
        } catch (e) {
            console.error('[team-chat] messages load failed', e);
        }
    }

    // Diff poll — only fetch messages newer than our highest server id.
    // Appends without re-rendering existing bubbles → no flicker, the
    // user's cursor / scroll position / selection stays put.
    async function pollNewMessages() {
        if (!state.activeCh) return;
        // Find the highest *real* (non-temp) message id we have
        const sinceId = state.messages
            .filter(m => !m._temp && typeof m.id === 'number')
            .reduce((max, m) => Math.max(max, m.id), 0);
        try {
            const r = await fetch(`${ep.index}?channel_id=${state.activeCh.id}&since_id=${sinceId}`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await r.json();
            const fresh = data.messages || [];
            if (fresh.length === 0) return;

            // Were we scrolled to bottom before? If yes, follow new messages.
            const stream = $('#tc-stream');
            const wasAtBottom = stream && (stream.scrollHeight - stream.scrollTop - stream.clientHeight < 100);

            for (const m of fresh) {
                // Don't double-add if a temp version of this matches (rare race)
                if (state.messages.some(x => x.id === m.id)) continue;
                state.messages.push(m);
                appendMessage(m);
            }
            if (wasAtBottom) scrollToBottom();

            const lastId = fresh[fresh.length - 1]?.id || 0;
            if (lastId > 0) markRead(lastId);
        } catch (e) { /* silent — next tick retries */ }
    }

    async function markRead(lastId) {
        if (!state.activeCh) return;
        try {
            await fetch(ep.markRead, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ channel_id: state.activeCh.id, last_id: lastId }),
            });
        } catch (e) {}
    }

    // Optimistic send — append the message to the stream immediately
    // with a temp id + "sending" status, then POST in the background.
    // On success: swap temp id with server id. On failure: mark failed
    // with retry button. Mirrors Slack's instant-feedback feel.
    function sendMessage() {
        if (!state.activeCh) return;
        const input = $('#tc-input');
        const body = (input?.value || '').trim();
        if (!body && !state.attach) return;

        const tempId  = 'tmp-' + Date.now() + '-' + Math.random().toString(36).slice(2, 6);
        const attach  = state.attach;
        const replyTo = state.replyTo;

        // Optimistic message — visible to the user the moment they hit Send
        const optimistic = {
            id:              tempId,
            _temp:           true,
            _status:         'sending',
            _attachFile:     attach,
            channel_id:      state.activeCh.id,
            user_id:         state.me.id,
            author_name:     state.me.name,
            author_avatar:   null,
            body:            body || null,
            mentions:        [],
            reply_to_id:     replyTo?.id || null,
            attachment_url:  attach && attach.type.startsWith('image/') ? URL.createObjectURL(attach) : null,
            attachment_mime: attach?.type || null,
            attachment_name: attach?.name || null,
            created_at:      new Date().toISOString(),
            is_mine:         true,
        };
        state.messages.push(optimistic);
        appendMessage(optimistic);
        scrollToBottom();

        // Clear composer state immediately so the user can keep typing
        input.value = '';
        input.style.height = 'auto';
        clearReply();
        clearAttach();

        // Background POST via XMLHttpRequest so we get upload progress
        const fd = new FormData();
        fd.append('channel_id', state.activeCh.id);
        fd.append('body', body);
        if (replyTo) fd.append('reply_to_id', replyTo.id);
        if (attach)  fd.append('attachment', attach);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', ep.store, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf);

        if (attach) {
            xhr.upload.addEventListener('progress', (e) => {
                if (!e.lengthComputable) return;
                const pct = Math.round((e.loaded / e.total) * 100);
                const row = $(`[data-msg-id="${tempId}"]`);
                if (!row) return;
                let bar = row.querySelector('[data-progress]');
                if (!bar) {
                    bar = document.createElement('div');
                    bar.setAttribute('data-progress', '1');
                    bar.className = 'h-1 rounded bg-paper-200 overflow-hidden mt-1';
                    bar.innerHTML = '<div class="h-full bg-wa-deep transition-all" style="width:0%"></div>';
                    row.querySelector('.rounded-md.px-3.py-2')?.appendChild(bar);
                }
                bar.firstElementChild.style.width = pct + '%';
            });
        }

        xhr.onload = () => {
            let data = null;
            try { data = JSON.parse(xhr.responseText); } catch (e) {}
            if (xhr.status >= 200 && xhr.status < 300 && data?.ok) {
                // Find the optimistic message + swap to real id
                const idx = state.messages.findIndex(m => m.id === tempId);
                if (idx !== -1) {
                    state.messages[idx].id      = data.id;
                    state.messages[idx]._temp   = false;
                    state.messages[idx]._status = 'sent';
                }
                const row = $(`[data-msg-id="${tempId}"]`);
                if (row) {
                    row.setAttribute('data-msg-id', data.id);
                    row.querySelector('[data-progress]')?.remove();
                    row.classList.remove('opacity-70');
                }
                // Trickle a refresh to pick up any other new bubbles
                loadChannels();
            } else {
                markMessageFailed(tempId, data?.message || 'Send failed');
            }
        };
        xhr.onerror = () => markMessageFailed(tempId, 'Network error');
        xhr.send(fd);
    }

    function markMessageFailed(tempId, reason) {
        const idx = state.messages.findIndex(m => m.id === tempId);
        if (idx !== -1) state.messages[idx]._status = 'failed';
        const row = $(`[data-msg-id="${tempId}"]`);
        if (row) {
            row.classList.add('opacity-60');
            const bubble = row.querySelector('.rounded-md.px-3.py-2');
            if (bubble && !bubble.querySelector('[data-fail-banner]')) {
                const b = document.createElement('div');
                b.setAttribute('data-fail-banner', '1');
                b.className = 'mt-1 text-[10.5px] text-red-600 flex items-center gap-1';
                b.innerHTML = `<svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="6"/><path d="M8 5v3M8 11v.5"/></svg> ${escape(reason)}`;
                bubble.appendChild(b);
            }
        }
    }

    function appendMessage(m) {
        const stream = $('#tc-stream');
        if (!stream) return;
        // Wipe the "no messages" placeholder on first append
        const placeholder = stream.querySelector('.text-center.text-ink-400');
        if (placeholder && state.messages.length === 1) stream.innerHTML = '';
        const div = document.createElement('div');
        div.innerHTML = renderMessage(m);
        const node = div.firstElementChild;
        if (m._temp) node.classList.add('opacity-70');
        stream.appendChild(node);
    }

    function scrollToBottom() {
        const stream = $('#tc-stream');
        if (stream) stream.scrollTop = stream.scrollHeight;
    }

    function setReply(m) {
        state.replyTo = { id: m.id, body: m.body, author_name: m.author_name };
        $('#tc-reply-name').textContent    = m.author_name || 'them';
        $('#tc-reply-snippet').textContent = (m.body || '').slice(0, 80);
        $('#tc-reply-banner').classList.remove('hidden');
        $('#tc-input')?.focus();
    }
    function clearReply() {
        state.replyTo = null;
        $('#tc-reply-banner')?.classList.add('hidden');
    }

    function setAttach(file) {
        state.attach = file;
        $('#tc-attach-preview').classList.remove('hidden');
        $('#tc-attach-name').textContent = file.name;
        $('#tc-attach-size').textContent = humanSize(file.size);
        const thumb = $('#tc-attach-thumb');
        const icon  = $('#tc-attach-icon');
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                thumb.src = e.target.result;
                thumb.classList.remove('hidden');
                icon.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        } else {
            thumb.classList.add('hidden');
            icon.classList.remove('hidden');
        }
    }
    function clearAttach() {
        state.attach = null;
        $('#tc-attach-preview')?.classList.add('hidden');
        const input = $('#tc-attach');
        if (input) input.value = '';
        $('#tc-attach-thumb')?.classList.add('hidden');
        $('#tc-attach-icon')?.classList.remove('hidden');
    }

    async function deleteMessage(id) {
        if (!confirm('Delete this message?')) return;
        try {
            await fetch(`${ep.destroy}/${id}`, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            await loadMessages();
        } catch (e) {
            alert('Delete failed');
        }
    }

    // ───────────────────────────── members rail
    function renderMembers(filter) {
        const list = $('#tc-members-list');
        if (!list) return;
        const q = (filter || '').toLowerCase().trim();
        const rows = state.members.filter(u =>
            !q || (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q)
        );
        if (rows.length === 0) {
            list.innerHTML = `<div class="text-center text-ink-400 text-[11px] font-mono py-4">No members match</div>`;
            return;
        }
        list.innerHTML = rows.map(u => {
            const mine = Number(u.id) === Number(state.me.id);
            return `<button type="button" data-mention-user="${u.id}"
                class="w-full px-3 py-2 hover:bg-paper-100 flex items-center gap-2.5 text-left border-b border-paper-100">
              <span class="w-8 h-8 rounded-full grid place-items-center text-[11px] font-semibold text-white shrink-0"
                    style="background:${avatarColor(u.id)}">${escape(initials(u.name))}</span>
              <div class="min-w-0 flex-1">
                <div class="text-[12.5px] font-semibold text-ink-800 truncate">${escape(u.name)} ${mine ? '<span class="text-[10px] font-mono text-ink-400">(you)</span>' : ''}</div>
                <div class="text-[10.5px] text-ink-500 truncate">${escape(u.email || '')}</div>
              </div>
            </button>`;
        }).join('');
    }

    // ───────────────────────────── @mention typeahead (positioned via CSS)
    function detectMention() {
        const input = $('#tc-input');
        if (!input) return null;
        const pos = input.selectionStart;
        const before = input.value.slice(0, pos);
        const m = before.match(/(^|\s)@(\w*)$/);
        if (!m) return null;
        return { prefix: m[2].toLowerCase(), start: pos - m[2].length - 1 };
    }

    function renderMentionPop() {
        const pop = $('#tc-mention-pop');
        if (!pop) return;
        const mention = detectMention();
        if (!mention) { pop.classList.add('hidden'); state.mention = null; state.mentionMatches = []; return; }
        state.mention = mention;
        const matches = state.members
            .filter(u => Number(u.id) !== Number(state.me.id))
            .filter(u => u.name?.toLowerCase().includes(mention.prefix) || u.email?.toLowerCase().includes(mention.prefix))
            .slice(0, 6);
        if (matches.length === 0) { pop.classList.add('hidden'); state.mentionMatches = []; return; }
        state.mentionMatches = matches;
        if (state.mentionIdx >= matches.length) state.mentionIdx = 0;
        pop.innerHTML = matches.map((u, i) => `
            <button type="button" data-mention-uid="${u.id}" data-mention-name="${escape(u.name)}"
                    class="flex items-center gap-2 w-full px-3 py-1.5 text-left hover:bg-paper-100 ${i === state.mentionIdx ? 'bg-paper-50' : ''}">
                <span class="w-7 h-7 rounded-full grid place-items-center text-[10px] font-semibold text-white shrink-0"
                      style="background:${avatarColor(u.id)}">${escape(initials(u.name))}</span>
                <div class="min-w-0 flex-1">
                    <div class="text-[12px] font-semibold truncate">${escape(u.name)}</div>
                    <div class="text-[10px] text-ink-500 truncate">${escape(u.email || '')}</div>
                </div>
            </button>
        `).join('');
        pop.classList.remove('hidden');
    }

    function insertMention(uid, name) {
        const input = $('#tc-input');
        if (!input || !state.mention) return;
        const token = `@[${name}](${uid}) `;
        const before = input.value.slice(0, state.mention.start);
        const after  = input.value.slice(input.selectionStart);
        input.value  = before + token + after;
        const caret  = before.length + token.length;
        input.setSelectionRange(caret, caret);
        input.focus();
        state.mention = null;
        state.mentionMatches = [];
        state.mentionIdx = 0;
        $('#tc-mention-pop').classList.add('hidden');
    }

    function autoResize() {
        const input = $('#tc-input');
        if (!input) return;
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 180) + 'px';
    }

    // ───────────────────────────── modals
    function openModal(id) {
        const el = $(`#${id}`);
        if (el) el.classList.remove('hidden');
    }
    function closeModal(id) {
        const el = $(`#${id}`);
        if (el) el.classList.add('hidden');
    }
    document.addEventListener('click', (e) => {
        if (e.target.matches('[data-tc-close]')) {
            const modal = e.target.closest('.fixed.inset-0');
            if (modal) modal.classList.add('hidden');
        }
    });

    // ───────────────────────────── invitations
    async function loadPending() {
        try {
            const r = await fetch(ep.invitationsIndex, { headers: { 'Accept': 'application/json' } });
            const data = await r.json();
            const pendingCount = (data.invitations || []).filter(i => i.status === 'pending').length;
            const badge = $('#tc-pending-count');
            if (badge) {
                badge.textContent = pendingCount;
                badge.style.display = pendingCount > 0 ? '' : 'none';
            }
            return data;
        } catch (e) { return { invitations: [], is_admin: false }; }
    }

    async function renderPendingModal() {
        const list = $('#tc-pending-list');
        if (!list) return;
        list.innerHTML = `<div class="text-center text-ink-400 text-[12px] font-mono py-8">Loading…</div>`;
        const data = await loadPending();
        const rows = data.invitations || [];
        if (rows.length === 0) {
            list.innerHTML = `<div class="text-center text-ink-400 text-[12px] font-mono py-8">No invitations yet</div>`;
            return;
        }
        list.innerHTML = rows.map(r => {
            const statusCls = r.status === 'pending' ? 'bg-amber-100 text-amber-800'
                            : r.status === 'approved' ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-paper-100 text-ink-600';
            const actions = (data.is_admin && r.status === 'pending')
                ? `<div class="flex gap-1.5 mt-2">
                     <button data-inv-approve="${r.id}" class="px-3 py-1 rounded-full bg-wa-deep text-paper-0 text-[11px] font-semibold">Approve</button>
                     <button data-inv-decline="${r.id}" class="px-3 py-1 rounded-full border border-paper-200 text-[11px]">Decline</button>
                   </div>` : '';
            return `<div class="border border-paper-200 rounded-md p-3">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <div class="text-[13px] font-semibold truncate">${escape(r.invitee_name || r.invitee_email)}</div>
                        <div class="text-[11px] text-ink-500 truncate">${escape(r.invitee_email)}</div>
                        <div class="text-[10.5px] text-ink-400 mt-1">Requested by ${escape(r.requester_name)} · ${formatTime(r.created_at)}</div>
                        ${r.note ? `<div class="text-[11.5px] text-ink-600 mt-1.5 italic">"${escape(r.note)}"</div>` : ''}
                        ${r.decided_by ? `<div class="text-[10.5px] text-ink-400 mt-1">${r.status} by ${escape(r.decided_by)} · ${formatTime(r.decided_at)}</div>` : ''}
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono uppercase ${statusCls}">${r.status}</span>
                </div>
                ${actions}
            </div>`;
        }).join('');
    }

    async function decideInvitation(id, action) {
        try {
            const r = await fetch(`${ep.invitationsApprove}/${id}/${action}`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            if (!r.ok) {
                const err = await r.json().catch(() => ({}));
                alert(err.message || 'Action failed');
                return;
            }
            await renderPendingModal();
            // Reload members + channels in case it added someone
            await loadMessages();
        } catch (e) { alert('Network error'); }
    }

    // ───────────────────────────── wire events
    function wireEvents() {
        // Composer
        $('#tc-composer')?.addEventListener('submit', (e) => { e.preventDefault(); sendMessage(); });
        $('#tc-input')?.addEventListener('keydown', (e) => {
            if (state.mention && state.mentionMatches.length > 0) {
                if (e.key === 'ArrowDown') { e.preventDefault(); state.mentionIdx = (state.mentionIdx + 1) % state.mentionMatches.length; renderMentionPop(); return; }
                if (e.key === 'ArrowUp')   { e.preventDefault(); state.mentionIdx = (state.mentionIdx - 1 + state.mentionMatches.length) % state.mentionMatches.length; renderMentionPop(); return; }
                if (e.key === 'Tab' || e.key === 'Enter') {
                    e.preventDefault();
                    const u = state.mentionMatches[state.mentionIdx];
                    if (u) insertMention(u.id, u.name);
                    return;
                }
                if (e.key === 'Escape') { state.mention = null; state.mentionMatches = []; $('#tc-mention-pop')?.classList.add('hidden'); return; }
            }
            if (e.key === 'Escape') { clearReply(); return; }
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        $('#tc-input')?.addEventListener('input', () => { autoResize(); state.mentionIdx = 0; renderMentionPop(); });
        $('#tc-input')?.addEventListener('click', renderMentionPop);
        $('#tc-input')?.addEventListener('blur', () => {
            // Delay so click on popover registers first
            setTimeout(() => $('#tc-mention-pop')?.classList.add('hidden'), 200);
        });

        $('#tc-mention-pop')?.addEventListener('mousedown', (e) => {
            const btn = e.target.closest('button[data-mention-uid]');
            if (btn) {
                e.preventDefault(); // don't blur the input
                insertMention(btn.dataset.mentionUid, btn.dataset.mentionName);
            }
        });

        // Attachment
        $('#tc-attach-btn')?.addEventListener('click', () => $('#tc-attach')?.click());
        $('#tc-attach')?.addEventListener('change', (e) => {
            const f = e.target.files?.[0];
            if (f) setAttach(f);
        });
        $('#tc-attach-clear')?.addEventListener('click', clearAttach);
        $('#tc-reply-cancel')?.addEventListener('click', clearReply);

        // Drag-and-drop on the stream
        const stream = $('#tc-stream');
        if (stream) {
            stream.addEventListener('dragover', (e) => { e.preventDefault(); stream.classList.add('bg-wa-bubble/30'); });
            stream.addEventListener('dragleave', () => stream.classList.remove('bg-wa-bubble/30'));
            stream.addEventListener('drop', (e) => {
                e.preventDefault();
                stream.classList.remove('bg-wa-bubble/30');
                const f = e.dataTransfer?.files?.[0];
                if (f) setAttach(f);
            });
        }

        // Stream actions
        stream?.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-act]');
            if (!btn) return;
            const row = btn.closest('[data-msg-id]');
            const id  = Number(row?.dataset.msgId);
            const m   = state.messages.find(x => x.id === id);
            if (!m) return;
            if (btn.dataset.act === 'reply')  setReply(m);
            if (btn.dataset.act === 'delete') deleteMessage(m.id);
        });

        // Members rail — click to insert @mention
        $('#tc-members-list')?.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-mention-user]');
            if (!btn) return;
            const uid = Number(btn.dataset.mentionUser);
            const u = state.members.find(x => Number(x.id) === uid);
            if (!u || uid === Number(state.me.id)) return;
            // On a phone the composer lives behind the home screen, so inserting
            // a mention without revealing the chat looked like the tap did
            // nothing. Open the thread FIRST, then write into the input — the
            // element has to be visible before focus() will lift the keyboard.
            openThreadMobile();

            const input = $('#tc-input');
            if (!input) return;
            const token = `@[${u.name}](${u.id}) `;
            const pos = input.selectionStart || input.value.length;
            input.value = input.value.slice(0, pos) + token + input.value.slice(pos);
            input.focus();
            const caret = pos + token.length;
            input.setSelectionRange(caret, caret);
            autoResize();
        });

        $('#tc-search')?.addEventListener('input', (e) => renderMembers(e.target.value));

        // Channel switch
        $('#tc-channel-list')?.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-channel-id]');
            if (btn) { switchChannel(btn.dataset.channelId); openThreadMobile(); }
        });

        // Mobile back — return from the chat stream to the channel list
        $('#tc-back')?.addEventListener('click', closeThreadMobile);

        // Create-channel modal
        $('#tc-create-channel-btn')?.addEventListener('click', () => {
            $('#tc-create-error').classList.add('hidden');
            $('#tc-create-form').reset();
            openModal('tc-create-modal');
        });
        $('#tc-create-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.currentTarget);
            const errEl = $('#tc-create-error');
            errEl.classList.add('hidden');
            try {
                const r = await fetch(ep.channelsStore, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: fd,
                });
                const data = await r.json();
                if (!r.ok || !data.ok) {
                    errEl.textContent = data.message || 'Failed to create channel';
                    errEl.classList.remove('hidden');
                    return;
                }
                closeModal('tc-create-modal');
                await loadChannels();
                // Switch into the new channel (and reveal it on mobile)
                switchChannel(data.channel.id);
                openThreadMobile();
            } catch (err) {
                errEl.textContent = 'Network error';
                errEl.classList.remove('hidden');
            }
        });

        // Invite modal
        $('#tc-invite-btn')?.addEventListener('click', () => {
            $('#tc-invite-result').classList.add('hidden');
            $('#tc-invite-form').reset();
            openModal('tc-invite-modal');
        });
        $('#tc-invite-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.currentTarget);
            const resultEl = $('#tc-invite-result');
            resultEl.classList.add('hidden');
            try {
                const r = await fetch(ep.invitationsStore, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: fd,
                });
                const data = await r.json();
                if (!r.ok || !data.ok) {
                    resultEl.className = 'text-[12px] font-medium text-red-600';
                    resultEl.textContent = data.message || 'Failed';
                    resultEl.classList.remove('hidden');
                    return;
                }
                resultEl.className = 'text-[12px] font-medium ' + (data.status === 'approved' ? 'text-emerald-700' : 'text-amber-700');
                resultEl.textContent = data.message;
                resultEl.classList.remove('hidden');
                setTimeout(() => closeModal('tc-invite-modal'), 1500);
                loadPending();
            } catch (err) {
                resultEl.className = 'text-[12px] font-medium text-red-600';
                resultEl.textContent = 'Network error';
                resultEl.classList.remove('hidden');
            }
        });

        // Pending list modal
        $('#tc-pending-btn')?.addEventListener('click', () => {
            openModal('tc-pending-modal');
            renderPendingModal();
        });
        $('#tc-pending-list')?.addEventListener('click', (e) => {
            const a = e.target.closest('[data-inv-approve]');
            const d = e.target.closest('[data-inv-decline]');
            if (a) decideInvitation(a.dataset.invApprove, 'approve');
            if (d) decideInvitation(d.dataset.invDecline, 'decline');
        });
    }

    // ───────────────────────────── bootstrap
    async function start() {
        wireEvents();
        await loadChannels();
        if (state.activeCh) {
            switchChannel(state.activeCh.id);
        }
        loadPending();
        // Diff-poll every 3s — only fetches messages newer than what we
        // have, so the existing stream stays put + no flicker. Channel
        // sidebar refreshes every 15s (slower since it changes less).
        setInterval(() => pollNewMessages(), 3000);
        setInterval(() => loadChannels(), 15000);
    }
    start();
}
