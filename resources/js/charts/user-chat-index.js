/*
 * Chat page — replaces the demo arrays from the static prototype
 * with a small client that talks to ChatController. The page-level
 * state (counts/devices/senders/groups/csrf/apiBase) is injected by the
 * blade as a JSON island in <script id="chat-state">.
 *
 * Layout of this file:
 *   - readState()         : parse the JSON island
 *   - api.*               : tiny fetch wrappers, all return JSON
 *   - parseMessage / esc  : safe rendering helpers
 *   - renderQueues etc    : DOM rendering
 *   - mountEmojiPicker()  : lazy-load emoji-picker-element web component
 *   - wireCompose()       : Create-queue modal (pencil button)
 *   - wire()              : bind every other UI control to its handler
 */

const STATUS_CLASSES = {
    sent:      { dot: 'bg-wa-green',      text: 'text-wa-deep' },
    pending:   { dot: 'bg-accent-amber',  text: 'text-[#7B5A14]' },
    scheduled: { dot: 'bg-[#13478A]',     text: 'text-[#13478A]' },
    failed:    { dot: 'bg-accent-coral',  text: 'text-[#A1431F]' },
    archived:  { dot: 'bg-ink-500',       text: 'text-ink-500' },
    read:      { dot: 'bg-wa-teal',       text: 'text-wa-teal' },
    delivered: { dot: 'bg-wa-green',      text: 'text-wa-deep' },
};

// Tom Select gives us click-to-toggle multi-select on the device
// picker without forcing users into Ctrl+click territory. The CSS
// is imported via the auth-register-step2 / user-account-index
// bundles, but importing here too is safe (Vite dedupes).
import TomSelect from 'tom-select';
import { mountPanel as mountTemplateMapping } from './template-live-mapping.js';
import 'tom-select/dist/css/tom-select.css';
import { themeColor } from '../theme-colors.js';

// Palette for the dynamic split-preview bar — one colour per
// device slot, recycled if the operator picks more than five.
const SPLIT_PALETTE = ['#0B4F3F', '#1A8F75', '#FFB347', '#7B61FF', '#A1431F'];

const TEMPLATE_CATEGORY_CLASSES = {
    marketing:      'bg-accent-coral/15 text-[#A1431F] border-accent-coral/30',
    utility:        'bg-wa-green/15 text-wa-deep border-wa-green/30',
    authentication: 'bg-[#7B61FF]/15 text-[#5B3D8A] border-[#7B61FF]/30',
};

const TEMPLATE_CATEGORY_LABELS = {
    marketing:      'Marketing templates',
    utility:        'Utility templates',
    authentication: 'Authentication templates',
    all:            'All templates',
};

const $ = (id) => document.getElementById(id);

function readState() {
    const node = document.getElementById('chat-state');
    if (!node) return { counts: {}, devices: [], csrfToken: '', apiBase: '/chat/api' };
    try { return JSON.parse(node.textContent); }
    catch { return { counts: {}, devices: [], csrfToken: '', apiBase: '/chat/api' }; }
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
}

/* WhatsApp-flavored markdown — *bold*, _italic_, ~strike~, `code`. */
function parseMessage(value) {
    let text = escapeHtml(value);
    text = text.replace(/\*(.+?)\*/g, '<b>$1</b>');
    text = text.replace(/_(.+?)_/g,  '<i>$1</i>');
    text = text.replace(/~(.+?)~/g,  '<s>$1</s>');
    text = text.replace(/`(.+?)`/g,  '<code class="px-1 py-0.5 rounded bg-ink-900/10 font-mono text-[12px]">$1</code>');
    return text.replace(/\n/g, '<br>');
}

function getInitials(name) {
    return String(name || '?').split(/\s+/).slice(0, 2).map((x) => x.charAt(0)).join('').toUpperCase() || '?';
}

function debounce(fn, ms) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), ms);
    };
}

export default function init() {
    const state = readState();

    /* ---- API ---- */
    const api = (() => {
        const base = state.apiBase || '/chat/api';
        const csrf = state.csrfToken || document.querySelector('meta[name=csrf-token]')?.content || '';
        const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

        async function call(method, path, { json, form } = {}) {
            const init = { method, headers: { ...headers } };
            if (method !== 'GET' && method !== 'HEAD') init.headers['X-CSRF-TOKEN'] = csrf;
            if (json) {
                init.headers['Content-Type'] = 'application/json';
                init.body = JSON.stringify(json);
            } else if (form) {
                init.body = form;
            }
            const res = await fetch(base + path, init);
            const body = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = body?.message || body?.error || res.statusText;
                throw new Error(msg);
            }
            return body;
        }

        return {
            call,                       // exposed so feature modules (msg menu) can hit any endpoint
            list:      (params)         => call('GET',    '/conversations?' + new URLSearchParams(params).toString()),
            create:    (payload)        => call('POST',   '/conversations', { json: payload }),
            show:      (id)             => call('GET',    `/conversations/${id}`),
            details:   (id)             => call('GET',    `/conversations/${id}/details`),
            send:      (id, formData)   => call('POST',   `/conversations/${id}/messages`, { form: formData }),
            template:  (id, templateId, overrides) => call('POST', `/conversations/${id}/template`, {
                json: {
                    template_id: templateId,
                    // Omitted when untouched, so an unmapped send posts
                    // exactly what it always did.
                    ...(overrides && Object.keys(overrides).length
                        ? { template_overrides: JSON.stringify(overrides) }
                        : {}),
                },
            }),
            archive:   (id)             => call('POST',   `/conversations/${id}/archive`),
            unarchive: (id)             => call('POST',   `/conversations/${id}/unarchive`),
            sendNow:   (id)             => call('POST',   `/conversations/${id}/send-now`),
            retry:     (id)             => call('POST',   `/conversations/${id}/retry`),
            resend:    (id)             => call('POST',   `/conversations/${id}/resend`),
            destroy:   (id)             => call('DELETE', `/conversations/${id}`),
            templates: (category)       => call('GET',    '/templates?' + new URLSearchParams({ category }).toString()),
        };
    })();

    /* ---- runtime state ---- */
    let activeFilter        = 'all';
    let activeSort          = 'date-desc';
    let activeDeviceId      = '';
    let activeQueueId       = null;
    let conversations       = [];
    let messagesById        = new Map();
    let templates           = [];
    let currentTemplateCat  = 'all';
    let selectedTemplateId  = null;
    // Per-send template overrides; reset whenever the picked template changes.
    let templateOverrides = {};
    let pendingMedia        = null;        // File object queued from the attach menu
    let pendingScheduleAt   = null;        // ISO datetime-local string, set via the schedule modal
    let pendingScheduleTz   = null;        // IANA timezone for pendingScheduleAt

    /* ---- DOM refs ---- */
    const queueList     = $('queue-list');
    const searchInput   = $('queue-search');
    const sortSelect    = $('sort-select');
    const deviceSelect  = $('device-select');
    const composer      = $('composer');
    const sendBtn       = $('send-btn');
    const attachBtn     = $('attach-btn');
    const attachMenu    = $('attach-menu');
    const emojiBtn      = $('emoji-btn');
    const emojiPanel    = $('emoji-panel');
    const mediaInput    = $('media-input');
    const archiveBtn    = $('thread-archive');
    const moreBtn       = $('thread-more');
    // Mobile back chevron — leaves the full-screen thread and returns to
    // the queues list. No-op on desktop (button is display:none there).
    $('chat-thread-back')?.addEventListener('click', () => {
        document.querySelector('.chat-frame')?.classList.remove('mobile-thread-open');
    });
    const templateModal = $('template-modal');
    const templateCardList = $('template-card-list');
    const templatePreview  = $('template-preview');
    const templateSend     = $('template-send');

    /* ---- toast ---- */
    function showToast(text, tone) {
        const t = $('toast');
        if (!t) return;
        // Auto-pick tone from a leading glyph if caller didn't pass one,
        // so the existing call sites don't all need updating.
        if (!tone) {
            if (/^✓✓/.test(text))           tone = 'read';
            else if (/^✓/.test(text))       tone = 'success';
            else if (/^⚠/.test(text))       tone = 'warn';
            else if (/^💬|^🔔/.test(text)) tone = 'info';
        }
        const palette = {
            success: 'border-wa-deep bg-wa-mint text-wa-deep',
            read:    'border-[#53BDEB] bg-[#E5F5FE] text-[#13478A]',
            warn:    'border-accent-coral bg-[#FFEEE7] text-[#8A2A0E]',
            info:    'border-paper-200 bg-paper-0 text-ink-700',
        }[tone] || 'border-wa-deep bg-paper-0 text-ink-900';
        t.className = 'fixed bottom-6 right-7 z-50 rounded-[14px] border shadow-soft px-4 py-3 text-[13px] font-semibold ' + palette;
        t.textContent = text;
        clearTimeout(showToast.timer);
        showToast.timer = setTimeout(() => t.classList.add('hidden'), 3000);
    }

    /**
     * Themed replacement for window.confirm(). Returns a Promise
     * that resolves true if the user clicks the confirm button,
     * false on cancel / Escape / backdrop click. Stylesheet
     * matches the rest of the chat aesthetic — coral accent for
     * destructive actions, wa-deep for everything else.
     */
    function confirmDialog({
        title       = 'Are you sure?',
        message     = '',
        eyebrow     = 'Confirm',
        confirmLbl  = 'Confirm',
        cancelLbl   = 'Cancel',
        danger      = false,
    } = {}) {
        return new Promise((resolve) => {
            const modal   = $('confirm-modal');
            const ok      = $('confirm-ok');
            const cancel  = $('confirm-cancel');
            const icon    = $('confirm-icon');

            $('confirm-eyebrow').textContent = eyebrow;
            $('confirm-title').textContent   = title;
            $('confirm-message').textContent = message;
            ok.textContent                   = confirmLbl;
            cancel.textContent               = cancelLbl;

            // Swap the accent palette based on whether the action is
            // destructive — keeps the dialog visually aligned with the
            // gravity of what's about to happen.
            if (danger) {
                ok.className   = 'px-4 py-2 rounded-full bg-accent-coral text-paper-0 text-[12px] font-semibold hover:bg-[#A1431F]';
                icon.className = 'w-10 h-10 rounded-2xl grid place-items-center bg-accent-coral/15 text-accent-coral shrink-0';
            } else {
                ok.className   = 'px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal';
                icon.className = 'w-10 h-10 rounded-2xl grid place-items-center bg-wa-mint text-wa-deep shrink-0';
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');

            const cleanup = (result) => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                ok.removeEventListener('click', onOk);
                cancel.removeEventListener('click', onCancel);
                modal.removeEventListener('click', onBackdrop);
                document.removeEventListener('keydown', onKey);
                resolve(result);
            };
            const onOk      = () => cleanup(true);
            const onCancel  = () => cleanup(false);
            const onBackdrop= (e) => { if (e.target === modal) cleanup(false); };
            const onKey     = (e) => {
                if (e.key === 'Escape') cleanup(false);
                else if (e.key === 'Enter') cleanup(true);
            };

            ok.addEventListener('click', onOk);
            cancel.addEventListener('click', onCancel);
            modal.addEventListener('click', onBackdrop);
            document.addEventListener('keydown', onKey);
            setTimeout(() => ok.focus(), 30);
        });
    }

    /* ---- counts: drive the badges in the left rail ---- */
    function applyCounts(counts) {
        if (!counts) return;
        ['all', 'scheduled', 'archived', 'sent', 'pending', 'failed'].forEach((key) => {
            const node = document.querySelector(`[data-count="${key}"]`);
            if (!node) return;
            const value = counts[key] ?? 0;
            node.textContent = value;
            node.classList.toggle('hidden', !value);
        });
        const tpl = counts.templates || {};
        ['marketing', 'utility', 'authentication'].forEach((key) => {
            const node = document.querySelector(`[data-tpl-count="${key}"]`);
            if (node) node.textContent = tpl[key] ?? 0;
        });
    }

    /* ---- queue list rendering ---- */
    function renderQueues() {
        queueList.innerHTML = '';
        if (!conversations.length) {
            queueList.innerHTML = `
                <div class="rounded-[14px] border border-dashed border-paper-200 bg-white p-6 text-center">
                    <div class="font-serif text-[20px]">No queues</div>
                    <p class="mt-1 text-[12px] text-ink-500">Try another filter or search.</p>
                </div>`;
            renderThread(null);
            return;
        }

        if (!conversations.find((c) => c.id === activeQueueId)) {
            activeQueueId = conversations[0].id;
            loadThread(activeQueueId);
        }

        conversations.forEach((convo) => {
            const meta   = STATUS_CLASSES[convo.category] || STATUS_CLASSES.archived;
            const active = convo.id === activeQueueId;
            const btn    = document.createElement('button');
            btn.type = 'button';
            btn.className = `w-full text-left rounded-[14px] border p-2.5 transition ${
                active ? 'border-wa-deep bg-wa-mint/60' : 'border-paper-200 bg-white hover:border-wa-deep'
            }`;

            // Archived rows still need to surface the underlying state
            // (failed/pending/scheduled) so the operator can see "this
            // is archived AND it failed" at a glance.
            const showSecondary = convo.archived && convo.status && convo.status !== 'sent';
            const secondary     = showSecondary ? STATUS_CLASSES[convo.status] : null;

            btn.innerHTML = `
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-full border border-ink-900/15 bg-[#FFF6E0] grid place-items-center font-bold text-[12px] shrink-0">${getInitials(convo.title)}</div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <div class="font-semibold truncate">${escapeHtml(convo.title)}</div>
                            <div class="ml-auto flex items-center gap-1.5 shrink-0">
                                <span class="font-mono text-[10px] text-ink-500">${escapeHtml(convo.last_message_lbl || '')}</span>
                                <button data-queue-gear="${convo.id}" type="button"
                                        class="w-6 h-6 rounded-full border border-paper-200 bg-white hover:bg-paper-50 grid place-items-center text-ink-500 hover:text-wa-deep"
                                        title="Queue actions">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <circle cx="8" cy="8" r="1.6"/>
                                        <path d="M13 8a5 5 0 0 0-.1-1l1.4-1-1.5-2.6-1.6.7a5 5 0 0 0-1.9-1.1L9 1H7l-.3 1.9a5 5 0 0 0-1.9 1.1l-1.6-.7L1.7 6l1.4 1A5 5 0 0 0 3 8c0 .4 0 .7.1 1l-1.4 1 1.5 2.6 1.6-.7a5 5 0 0 0 1.9 1.1L7 15h2l.3-1.9a5 5 0 0 0 1.9-1.1l1.6.7L14.3 10l-1.4-1c.1-.3.1-.6.1-1Z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="mt-0.5 text-[12px] text-ink-500 truncate">${escapeHtml(convo.preview || '')}</div>
                        <div class="mt-1.5 flex items-center gap-2 text-[11px]">
                            <span class="inline-flex items-center gap-1 font-semibold ${meta.text}">
                                <span class="w-2 h-2 rounded-full ${meta.dot}"></span>${escapeHtml(convo.category || convo.status)}
                            </span>
                            ${secondary ? `
                                <span class="inline-flex items-center gap-1 font-semibold ${secondary.text}">
                                    <span class="w-1.5 h-1.5 rounded-full ${secondary.dot}"></span>${escapeHtml(convo.status)}
                                </span>` : ''}
                            <span class="ml-auto text-ink-500">${convo.recipients_count || 0} contacts</span>
                        </div>
                    </div>
                </div>`;
            btn.addEventListener('click', (e) => {
                if (e.target.closest('[data-queue-gear]')) return; // gear handles its own click
                activeQueueId = convo.id;
                renderQueues();
                loadThread(convo.id);
                // Mobile (<768px): the queues list and the thread are
                // one-at-a-time panes. Opening a queue slides the thread
                // in full-screen (see .chat-frame CSS); the back chevron
                // in the thread header returns to the list.
                document.querySelector('.chat-frame')?.classList.add('mobile-thread-open');
            });
            const gear = btn.querySelector('[data-queue-gear]');
            if (gear) gear.addEventListener('click', (e) => {
                e.stopPropagation();
                openQueueMenu(convo, gear);
            });
            queueList.appendChild(btn);
        });
    }

    /* ---- thread (right pane) rendering ---- */
    function renderThread(convo) {
        const list = $('message-list');
        if (!convo) {
            $('thread-title').textContent  = 'Select a queue';
            $('thread-meta').textContent   = 'No active thread';
            $('thread-avatar').textContent = '--';
            $('thread-dot').className      = 'w-2 h-2 rounded-full bg-ink-500';
            list.innerHTML = '<div class="text-center text-[13px] text-ink-500 py-16">No active conversation.</div>';
            archiveBtn.disabled = true;
            archiveBtn.title    = 'Archive';
            moreBtn.disabled    = true;
            return;
        }
        const meta = STATUS_CLASSES[convo.category] || STATUS_CLASSES.archived;
        $('thread-title').textContent  = convo.title;
        // Quick Send is a bulk send-out tool — surface ALL recipient numbers in
        // the header, not just "X contacts". Collected from the loaded outbound
        // rows (each recipient is one outbound message); the Show-details drawer
        // still carries the full per-recipient list for large sends.
        const _outNums = [...new Set((messagesById.get(convo.id) || [])
            .filter((m) => m.direction === 'out' && m.to_number)
            .map((m) => String(m.to_number)))];
        let _metaText = `${convo.recipients_count || 0} contacts — ${convo.category || convo.status}`;
        if (_outNums.length > 1) {
            _metaText += ' · ' + _outNums.slice(0, 8).map((n) => '+' + n).join(', ')
                + (_outNums.length > 8 ? ` +${_outNums.length - 8} more` : '');
        }
        $('thread-meta').textContent   = _metaText;
        $('thread-avatar').textContent = getInitials(convo.title);
        $('thread-dot').className      = `w-2 h-2 rounded-full ${meta.dot}`;

        archiveBtn.disabled = false;
        archiveBtn.title    = convo.archived ? 'Unarchive' : 'Archive';
        moreBtn.disabled    = false;

        const msgs = messagesById.get(convo.id) || [];
        list.innerHTML = '';
        if (!msgs.length) {
            list.innerHTML = `<div class="text-center text-[13px] text-ink-500 py-16">${t('No messages yet — type one below.')}</div>`;
            return;
        }
        msgs.forEach((m) => {
            const wrap   = document.createElement('div');
            wrap.className = `flex ${m.direction === 'out' ? 'justify-end' : 'justify-start'} group/msg`;
            const hasLoc = (m.latitude !== null && m.latitude !== undefined && m.longitude !== null && m.longitude !== undefined)
                        || m.media_type === 'location';
            const media  = m.media_url
                ? `<div class="mb-1.5">${renderMedia(m)}</div>`
                : (hasLoc ? `<div class="mb-1.5">${renderLocation(m)}</div>` : '');
            const isLegacyLocBody = hasLoc && m.body && /maps\.google\.com\/\?q=/.test(m.body);
            const ticks = m.direction === 'out' ? renderStatusTick(m.status) : '';
            const pinIcon  = m.pinned  ? `<svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="currentColor"><path d="M5 1l5 0 1 4 2 1-1 4-3 0 0 5-2 0 0-5-3 0-1-4 2-1z"/></svg>` : '';
            const starIcon = m.starred ? `<svg viewBox="0 0 16 16" class="w-3 h-3 text-[#E0B445]" fill="currentColor"><path d="M8 1l2 4.5 5 .5-3.7 3.4 1.1 4.9L8 11.8l-4.4 2.5 1.1-4.9L1 5.5l5-.5z"/></svg>` : '';
            const reaction = m.reaction
                ? `<span class="absolute -bottom-2 ${m.direction === 'out' ? 'left-2' : 'right-2'} px-1.5 py-0.5 rounded-full bg-white border border-paper-200 text-[12px] shadow-sm">${escapeHtml(m.reaction)}</span>`
                : '';
            // Template sends carry their header / footer / buttons on the
            // message meta. Rendering only `body` showed a bare line of text —
            // the operator could not tell a template from a typed message, nor
            // see the buttons the customer actually received.
            const tplButtons = Array.isArray(m.buttons) ? m.buttons : [];
            const tplHeader = m.header
                ? `<div class="text-[13px] font-semibold text-ink-900 leading-snug mb-1 pr-5">${escapeHtml(m.header)}</div>`
                : '';
            const tplFooter = m.footer
                ? `<div class="text-[11px] text-ink-500 mt-1.5 leading-snug">${escapeHtml(m.footer)}</div>`
                : '';
            // Buttons sit edge-to-edge under a divider, the way WhatsApp
            // stacks them. Negative margin cancels the bubble's padding so
            // the separators run the full width of the card.
            const tplButtonsHtml = tplButtons.length
                ? `<div class="-mx-2.5 -mb-2.5 mt-2 border-t border-wa-deep/15">${
                    tplButtons.map((b) => `
                        <div class="px-2.5 py-1.5 flex items-center justify-center gap-1.5 text-[12.5px] font-medium text-wa-deep">
                            ${templateButtonIcon(b.type)}
                            <span class="truncate">${escapeHtml(b.text || b.value || t('Button'))}</span>
                        </div>`).join('<div class="border-t border-wa-deep/10"></div>')
                }</div>`
                : '';
            wrap.innerHTML = `
                <div class="relative max-w-[72%] rounded-2xl p-2.5 shadow-card ${
                    m.direction === 'out' ? 'bg-wa-mint border border-wa-deep/20' : 'bg-white border border-paper-200'
                }" data-msg-id="${m.id}">
                    <button type="button" data-msg-menu="${m.id}" class="absolute top-1 right-1 w-6 h-6 rounded-full bg-white/85 border border-paper-200 grid place-items-center opacity-0 group-hover/msg:opacity-100 transition shadow-sm hover:bg-white" title="Message options">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
                    </button>
                    ${media}
                    ${tplHeader}
                    ${(m.body && !isLegacyLocBody) ? `<div class="text-[13px] leading-relaxed pr-5">${parseMessage(m.body)}</div>` : ''}
                    ${tplFooter}
                    <div class="mt-1 flex items-center justify-end gap-1 text-[10px] text-ink-500">
                        ${pinIcon}${starIcon}
                        <span>${escapeHtml(m.time || '')}</span>
                        ${ticks}
                    </div>
                    ${tplButtonsHtml}
                    ${reaction}
                </div>`;
            list.appendChild(wrap);
        });
        list.scrollTop = list.scrollHeight;
    }

    // Leading glyph for a template button, matching what WhatsApp shows:
    // link-out for URL buttons, handset for call, copy for coupon codes,
    // reply-arrow for quick replies (the value-less default).
    function templateButtonIcon(type) {
        const kind = String(type || '').toLowerCase();
        const attrs = 'viewBox="0 0 16 16" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"';
        if (['url', 'visit_website'].includes(kind)) {
            return `<svg ${attrs}><path d="M9 3h4v4"/><path d="M13 3L7 9"/><path d="M11 9.5V13H3V5h3.5"/></svg>`;
        }
        if (['call', 'call_phone', 'phone_number'].includes(kind)) {
            return `<svg ${attrs}><path d="M3 3.5h2.5l1 3-1.5 1a8 8 0 0 0 3.5 3.5l1-1.5 3 1V13a1 1 0 0 1-1 1A10 10 0 0 1 2 4.5a1 1 0 0 1 1-1Z"/></svg>`;
        }
        if (['copy_code', 'copy_text', 'otp_copy', 'otp_one_tap'].includes(kind)) {
            return `<svg ${attrs}><rect x="5.5" y="5.5" width="8" height="8" rx="1.2"/><path d="M10.5 3.5H3.2A.7.7 0 0 0 2.5 4.2v7.3"/></svg>`;
        }
        return `<svg ${attrs}><path d="M7 4L3 8l4 4"/><path d="M3 8h6a4 4 0 0 1 4 4"/></svg>`;
    }

    // WhatsApp-style status indicators for outbound messages.
    //   pending / scheduled → grey clock
    //   sent                → single grey ✓     (server got it)
    //   delivered           → double grey ✓✓    (recipient phone got it)
    //   read                → double BLUE ✓✓    (recipient opened it)
    //   failed              → red ⚠ + "failed"
    function renderStatusTick(status) {
        if (!status) return '';
        if (status === 'failed') {
            return `<span class="inline-flex items-center gap-1 text-accent-coral font-semibold ml-0.5" title="failed">
                <svg width="11" height="11" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="6.5"/><path d="M8 5v3.5"/><circle cx="8" cy="11" r="0.6" fill="currentColor"/></svg>
                <span class="text-[10px]">failed</span>
            </span>`;
        }
        if (status === 'pending' || status === 'scheduled') {
            return `<span class="inline-flex items-center text-ink-500 ml-0.5" title="${status}">
                <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="6.2"/><path d="M8 4.5v3.8l2.4 1.4"/></svg>
            </span>`;
        }
        const colour = status === 'read' ? '#53BDEB' : '#7B8B86'; // WA blue for read, grey for sent/delivered
        const isSingle = status === 'sent';
        const svg = isSingle
            ? `<svg width="14" height="10" viewBox="0 0 18 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block"><polyline points="2 6.5 6.5 10.5 16 1.5"/></svg>`
            : `<svg width="18" height="10" viewBox="0 0 24 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block"><polyline points="2 6.5 6 10 13 1.5"/><polyline points="9 6.5 13 10 22 1.5"/></svg>`;
        return `<span class="inline-flex items-center ml-0.5" title="${status}" style="color:${colour}">${svg}</span>`;
    }

    // WhatsApp-style location card. We do NOT embed an external static-map
    // image — that domain (staticmap.openstreetmap.de) is unreliable and on a
    // LAN with no internet it fails to resolve, spamming the console. Instead
    // we draw a self-contained pin card (works offline) and link out to Google
    // Maps so the recipient can open the real map when they have a connection.
    function renderLocation(m) {
        const lat = Number(m.latitude);
        const lng = Number(m.longitude);
        const safeLat = isFinite(lat) ? lat.toFixed(5) : '';
        const safeLng = isFinite(lng) ? lng.toFixed(5) : '';
        const href = (isFinite(lat) && isFinite(lng)) ? `https://www.google.com/maps?q=${lat},${lng}` : '#';
        return `<a href="${href}" target="_blank" rel="noopener" class="block rounded-xl overflow-hidden border border-paper-200 bg-white max-w-[280px]">
            <div class="h-[120px] flex items-center justify-center bg-[#cfe0db] [background-image:radial-gradient(rgba(7,94,84,0.12)_1px,transparent_1px)] [background-size:12px_12px]">
                <svg viewBox="0 0 16 16" class="w-7 h-7 text-accent-coral" fill="currentColor"><path d="M8 1.5a4.5 4.5 0 0 0-4.5 4.5c0 3.5 4.5 8.5 4.5 8.5s4.5-5 4.5-8.5A4.5 4.5 0 0 0 8 1.5zm0 6.2a1.7 1.7 0 1 1 0-3.4 1.7 1.7 0 0 1 0 3.4z"/></svg>
            </div>
            <div class="px-2.5 py-2 flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-4 h-4 text-accent-coral flex-shrink-0" fill="currentColor"><path d="M8 1.5a4.5 4.5 0 0 0-4.5 4.5c0 3.5 4.5 8.5 4.5 8.5s4.5-5 4.5-8.5A4.5 4.5 0 0 0 8 1.5zm0 6.2a1.7 1.7 0 1 1 0-3.4 1.7 1.7 0 0 1 0 3.4z"/></svg>
                <div class="min-w-0 flex-1">
                    <div class="text-[12.5px] font-semibold text-ink-900 truncate">Location</div>
                    <div class="text-[10.5px] font-mono text-ink-500 truncate">${safeLat}, ${safeLng}</div>
                </div>
            </div>
        </a>`;
    }

    function renderMedia(m) {
        const url  = m.media_url;
        const name = m.media_name || (url ? url.split('/').pop() : 'file');
        const size = formatBytes(m.media_size || 0);
        const mime = (m.media_mime || '').toLowerCase();
        switch (m.media_type) {
            case 'image':
                return `<a href="${url}" target="_blank" rel="noopener" class="block">
                    <img src="${url}" alt="${escapeHtml(name)}" class="rounded-xl max-h-72 object-cover">
                </a>`;
            case 'video':
                return `<video src="${url}" controls class="rounded-xl max-h-72 w-full"></video>`;
            case 'audio':
                return `<audio src="${url}" controls class="w-full"></audio>`;
            default:
                return renderDocCard(url, name, size, mime);
        }
    }

    function renderDocCard(url, name, size, mime) {
        const ext = (name.split('.').pop() || '').toLowerCase();
        const { tagBg, tagText, label, iconSvg } = docMeta(ext, mime);
        return `<a href="${url}" target="_blank" rel="noopener" download="${escapeHtml(name)}"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-white/70 border border-paper-200 hover:bg-white transition-colors min-w-[240px]">
            <div class="w-11 h-12 rounded-md grid place-items-center text-[10px] font-bold text-white relative flex-shrink-0" style="background:${tagBg}">
                ${iconSvg}
                <span class="absolute bottom-0.5 left-0 right-0 text-center text-[9px] tracking-wide" style="color:${tagText}">${label}</span>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-[13px] font-medium text-ink-900 truncate">${escapeHtml(name)}</div>
                <div class="text-[11px] text-ink-500 mt-0.5 uppercase tracking-wide">${label}${size ? ' · ' + size : ''}</div>
            </div>
        </a>`;
    }

    function docMeta(ext, mime) {
        const M = (k) => mime.includes(k);
        if (ext === 'pdf' || M('pdf'))                                         return { tagBg:'#E94B4B', tagText:'#FFE9E9', label:'PDF',  iconSvg:fileIconSvg('#FFFFFF') };
        if (['doc','docx'].includes(ext) || M('word') || M('officedocument.wordprocessing'))
                                                                               return { tagBg:'#2A6FE0', tagText:'#E8F0FF', label:'DOCX', iconSvg:fileIconSvg('#FFFFFF') };
        if (['xls','xlsx','csv'].includes(ext) || M('spreadsheet') || M('excel'))
                                                                               return { tagBg:'#1F8E4A', tagText:'#E6F6EC', label:ext.toUpperCase()||'XLS', iconSvg:fileIconSvg('#FFFFFF') };
        if (['ppt','pptx'].includes(ext) || M('presentation'))                 return { tagBg:'#D24726', tagText:'#FFE9E0', label:'PPTX', iconSvg:fileIconSvg('#FFFFFF') };
        if (['zip','rar','7z','tar','gz'].includes(ext))                       return { tagBg:'#6B7280', tagText:'#F3F4F6', label:ext.toUpperCase(),  iconSvg:fileIconSvg('#FFFFFF') };
        return { tagBg:themeColor('wa-deep'), tagText:'#DFF1ED', label:(ext||'FILE').toUpperCase(), iconSvg:fileIconSvg('#FFFFFF') };
    }

    function fileIconSvg(stroke) {
        return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="${stroke}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position:absolute;top:5px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>`;
    }

    function formatBytes(n) {
        if (!n || n <= 0) return '';
        const u = ['B','KB','MB','GB'];
        let i = 0; let v = n;
        while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
        return (i === 0 ? v : v.toFixed(v >= 10 ? 0 : 1)) + ' ' + u[i];
    }

    /**
     * Shimmer skeleton for the thread pane while a conversation
     * is loading. Mirrors the bubble shape — outgoing on the
     * right, incoming on the left — so the layout doesn't shift
     * once the real messages render.
     */
    function renderThreadSkeleton() {
        const list = $('message-list');
        list.innerHTML = `
            <div class="flex justify-end">
                <div class="max-w-[60%] rounded-2xl p-2.5 shadow-card bg-wa-mint/40 border border-wa-deep/10 space-y-2 w-64">
                    <div class="skeleton h-3 w-5/6"></div>
                    <div class="skeleton h-3 w-3/5"></div>
                </div>
            </div>
            <div class="flex justify-start">
                <div class="max-w-[60%] rounded-2xl p-2.5 shadow-card bg-white border border-paper-200 space-y-2 w-56">
                    <div class="skeleton h-3 w-4/5"></div>
                </div>
            </div>
            <div class="flex justify-end">
                <div class="max-w-[60%] rounded-2xl p-2.5 shadow-card bg-wa-mint/40 border border-wa-deep/10 space-y-2 w-72">
                    <div class="skeleton h-3 w-5/6"></div>
                    <div class="skeleton h-3 w-2/3"></div>
                    <div class="skeleton h-3 w-1/2"></div>
                </div>
            </div>`;
    }

    /**
     * Skeleton rows for the queue list while conversations.list()
     * is in flight. Same shape (avatar + 3 lines) as the real
     * card so the layout doesn't jump.
     */
    function renderQueueSkeleton(rows = 4) {
        const html = Array.from({ length: rows }, () => `
            <div class="rounded-[14px] border border-paper-200 bg-white p-2.5">
                <div class="flex items-start gap-3">
                    <div class="skeleton w-9 h-9 rounded-full shrink-0"></div>
                    <div class="flex-1 min-w-0 space-y-1.5">
                        <div class="flex items-center gap-2">
                            <div class="skeleton h-3 w-2/5"></div>
                            <div class="skeleton ml-auto h-2.5 w-8"></div>
                        </div>
                        <div class="skeleton h-2.5 w-4/5"></div>
                        <div class="flex items-center gap-2">
                            <div class="skeleton h-2 w-12"></div>
                            <div class="skeleton ml-auto h-2 w-14"></div>
                        </div>
                    </div>
                </div>
            </div>`).join('');
        queueList.innerHTML = html;
    }

    /* ---- data fetchers ---- */
    async function loadQueues() {
        if (!conversations.length) renderQueueSkeleton(); // first paint
        try {
            const params = { filter: activeFilter, sort: activeSort, q: searchInput.value.trim() };
            if (activeDeviceId) params.device_id = activeDeviceId;
            const { data, meta } = await api.list(params);
            conversations = data;
            applyCounts(meta);
            renderQueues();
        } catch (e) {
            showToast(`Failed to load queues: ${e.message}`);
        }
    }

    async function loadThread(id) {
        if (!id) return;
        renderThreadSkeleton();
        try {
            const { data } = await api.show(id);
            messagesById.set(id, data.messages);
            renderThread(data.conversation);
        } catch (e) {
            showToast(`Failed to open thread: ${e.message}`);
        }
    }

    /* ---- queue gear menu (Show details / Retry / Send now / etc.) ---- */
    const queueMenu = $('queue-menu');

    function closeQueueMenu() {
        queueMenu.classList.add('hidden');
        queueMenu.innerHTML = '';
    }

    function buildMenuItems(convo) {
        const items = [{
            label: 'Show details',
            icon: '<path d="M8 2.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z"/><path d="M8 7v3.5M8 5.2h.01"/>',
            run: () => openDetailsModal(convo),
        }];
        if (convo.status === 'failed') {
            items.push({
                label: 'Retry send',
                icon: '<path d="M3 8a5 5 0 0 1 9-3M3 4v3h3M13 8a5 5 0 0 1-9 3M13 12V9h-3"/>',
                run: () => runQueueAction(convo, 'retry'),
            });
        }
        if (convo.status === 'sent') {
            items.push({
                label: 'Send again',
                icon: '<path d="M2 8 14 3 10 14 7 9z"/>',
                run: () => runQueueAction(convo, 'resend'),
            });
        }
        if (convo.status === 'scheduled') {
            items.push({
                label: 'Send now',
                icon: '<path d="M2 8 14 3 10 14 7 9z"/>',
                run: () => runQueueAction(convo, 'sendNow'),
            });
        }
        items.push(convo.archived ? {
            label: 'Unarchive',
            icon: '<path d="M3 6h10v7H3zM2.5 3.5h11V6h-11zM6 9l2-2 2 2"/>',
            run: () => runQueueAction(convo, 'unarchive'),
        } : {
            label: 'Archive',
            icon: '<path d="M3 6h10v7H3zM2.5 3.5h11V6h-11zM6 9h4"/>',
            run: () => runQueueAction(convo, 'archive'),
        });
        items.push({
            label: 'Delete',
            icon: '<path d="M3 4h10M5 4V2.5h6V4M4 4l1 10h6l1-10"/>',
            danger: true,
            run: () => confirmDelete(convo),
        });
        return items;
    }

    function openQueueMenu(convo, anchor) {
        const items = buildMenuItems(convo);
        queueMenu.innerHTML = items.map((item, i) => `
            <button data-menu-i="${i}" type="button" class="w-full px-3 py-2 rounded-xl text-left text-[12.5px] font-medium flex items-center gap-2 hover:bg-wa-mint ${item.danger ? 'text-accent-coral' : ''}">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5">${item.icon}</svg>
                <span>${item.label}</span>
            </button>
        `).join('');

        // Position the popover under the gear, clipped to the viewport edge.
        const r = anchor.getBoundingClientRect();
        queueMenu.classList.remove('hidden');
        const w = queueMenu.offsetWidth;
        let left = r.right - w;
        if (left < 8) left = 8;
        if (left + w > window.innerWidth - 8) left = window.innerWidth - w - 8;
        queueMenu.style.top  = `${r.bottom + window.scrollY + 4}px`;
        queueMenu.style.left = `${left + window.scrollX}px`;

        queueMenu.querySelectorAll('[data-menu-i]').forEach((b) => {
            b.addEventListener('click', () => {
                const idx = parseInt(b.dataset.menuI, 10);
                closeQueueMenu();
                items[idx].run();
            });
        });
    }

    async function runQueueAction(convo, action) {
        try {
            const fn = api[action];
            const { meta, data } = await fn(convo.id);
            applyCounts(meta);
            await loadQueues();
            if (data?.conversation && data.conversation.id === activeQueueId) {
                await loadThread(activeQueueId);
            }
            const labels = {
                retry:     'Retry queued',
                resend:    'Sent again',
                sendNow:   'Sending now',
                archive:   'Archived',
                unarchive: 'Unarchived',
            };
            showToast(labels[action] || 'Done');
        } catch (e) {
            showToast(`Failed: ${e.message}`);
        }
    }

    async function confirmDelete(convo) {
        const ok = await confirmDialog({
            eyebrow:    'Delete queue',
            title:      `Delete "${convo.title}"?`,
            message:    `All ${convo.recipients_count || 0} recipients and the message history for this queue will be removed. This can't be undone.`,
            confirmLbl: 'Delete queue',
            danger:     true,
        });
        if (!ok) return;
        try {
            const { meta } = await api.destroy(convo.id);
            applyCounts(meta);
            if (activeQueueId === convo.id) activeQueueId = null;
            await loadQueues();
            showToast('Queue deleted');
        } catch (e) {
            showToast(`Failed: ${e.message}`);
        }
    }

    /* ---- queue details drawer (slide-in from the right) ---- */
    function statusPill(status) {
        const s = STATUS_CLASSES[status] || STATUS_CLASSES.archived;
        return `<span class="inline-flex items-center gap-1 font-semibold ${s.text}">
                    <span class="w-2 h-2 rounded-full ${s.dot}"></span>${escapeHtml(status || '—')}
                </span>`;
    }

    function statCard(label, value, accent = 'text-ink-900') {
        return `
            <div class="rounded-[14px] border border-paper-200 bg-white p-2.5">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">${label}</div>
                <div class="mt-0.5 font-serif text-[20px] leading-none ${accent}">${value}</div>
            </div>`;
    }

    function renderDetailsOverview(d) {
        const c = d.conversation;
        const s = d.stats;
        const rows = [
            ['Status',         `${statusPill(c.status)}${c.archived ? ' <span class="text-ink-500">· archived</span>' : ''}`],
            ['Platform',       escapeHtml(c.platform || 'W')],
            ['Recipients',     `${c.recipients_count || s.recipients_count || 0}`],
            ['Total messages', `${s.total_messages || 0}`],
            ['Last activity',  c.last_message_at ? new Date(c.last_message_at).toLocaleString() : '—'],
            ['Preview',        escapeHtml(c.preview || '—')],
        ];
        return `
            <div class="grid gap-3">
                ${rows.map(([k, v]) => `
                    <div class="grid grid-cols-[140px_minmax(0,1fr)] gap-3">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 pt-0.5">${k}</div>
                        <div class="text-ink-800">${v}</div>
                    </div>`).join('')}
            </div>`;
    }

    function renderDetailsRecipients(d) {
        if (!d.recipients.length) {
            return `<div class="text-center text-ink-500 text-[13px] py-12">No outgoing messages have been sent yet for this queue.</div>`;
        }
        return `
            <div class="rounded-[14px] border border-paper-200 overflow-hidden">
                <table class="w-full text-[12.5px]">
                    <thead class="bg-paper-50 text-ink-500 font-mono text-[10px] uppercase tracking-[0.16em]">
                        <tr>
                            <th class="text-left px-3 py-2">Number</th>
                            <th class="text-left px-3 py-2">Last status</th>
                            <th class="text-left px-3 py-2">Last sent</th>
                            <th class="text-right px-3 py-2">Msgs</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        ${d.recipients.map((r) => `
                            <tr class="hover:bg-paper-50">
                                <td class="px-3 py-2 font-mono">${escapeHtml(r.to_number)}</td>
                                <td class="px-3 py-2">${statusPill(r.last_status)}${r.failure_reason ? `<div class="text-[11px] text-accent-coral mt-0.5">${escapeHtml(r.failure_reason)}</div>` : ''}</td>
                                <td class="px-3 py-2 text-ink-600">${escapeHtml(r.last_at_lbl || '—')}</td>
                                <td class="px-3 py-2 text-right font-semibold">${r.message_count}</td>
                            </tr>`).join('')}
                    </tbody>
                </table>
            </div>`;
    }

    function renderDetailsMessages(d) {
        if (!d.messages.length) {
            return `<div class="text-center text-ink-500 text-[13px] py-12">${t('No messages logged.')}</div>`;
        }
        return `
            <div class="rounded-[14px] border border-paper-200 overflow-hidden">
                <table class="w-full text-[12.5px]">
                    <thead class="bg-paper-50 text-ink-500 font-mono text-[10px] uppercase tracking-[0.16em]">
                        <tr>
                            <th class="text-left px-3 py-2">When</th>
                            <th class="text-left px-3 py-2">Dir</th>
                            <th class="text-left px-3 py-2">Body</th>
                            <th class="text-left px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        ${d.messages.map((m) => `
                            <tr class="hover:bg-paper-50">
                                <td class="px-3 py-2 text-ink-600 whitespace-nowrap font-mono text-[11px]">${escapeHtml(m.time || '')}</td>
                                <td class="px-3 py-2"><span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold ${m.direction === 'out' ? 'bg-wa-mint text-wa-deep' : 'bg-paper-50 text-ink-700'}">${m.direction === 'out' ? '↑' : '↓'}</span></td>
                                <td class="px-3 py-2"><div class="line-clamp-2">${parseMessage(m.body || (m.media_type ? '[' + m.media_type + ']' : ''))}</div></td>
                                <td class="px-3 py-2">${statusPill(m.status)}</td>
                            </tr>`).join('')}
                    </tbody>
                </table>
            </div>`;
    }

    function renderDetailsAttachments(d) {
        if (!d.attachments.length) {
            return `<div class="text-center text-ink-500 text-[13px] py-12">No attachments in this queue yet.</div>`;
        }
        return `
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                ${d.attachments.map((a) => {
                    const url = a.media_url;
                    const name = a.media_name || (url ? url.split('/').pop() : 'file');
                    const ext  = (name.split('.').pop() || '').toLowerCase();
                    const meta = docMeta(ext, (a.media_mime || '').toLowerCase());
                    const thumb = a.media_type === 'image' ? `<img src="${url}" alt="" class="w-full h-32 object-cover">`
                              : a.media_type === 'video' ? `<video src="${url}" muted class="w-full h-32 object-cover"></video>`
                              : `<div class="w-full h-32 grid place-items-center" style="background:${meta.tagBg}">
                                    <span class="text-[14px] font-bold text-white">${meta.label}</span>
                                  </div>`;
                    return `
                        <a href="${url}" target="_blank" rel="noopener" download="${escapeHtml(name)}" class="block rounded-[14px] border border-paper-200 bg-white overflow-hidden hover:border-wa-deep">
                            ${thumb}
                            <div class="px-2.5 py-1.5 text-[11px]">
                                <div class="font-mono text-[10px] uppercase text-ink-500">${meta.label}${a.media_size ? ' · ' + formatBytes(a.media_size) : ''}</div>
                                <div class="truncate">${escapeHtml(name)}</div>
                            </div>
                        </a>`;
                }).join('')}
            </div>`;
    }

    function renderDetailsStats(d) {
        const s = d.stats;
        return [
            statCard('Total',     s.total_messages || 0),
            statCard('Recipients',s.recipients_count || 0),
            statCard('Sent',      s.sent || 0,     'text-wa-deep'),
            statCard('Delivered', s.delivered || 0,'text-wa-deep'),
            statCard('Failed',    s.failed || 0,   'text-accent-coral'),
            statCard('Scheduled', s.scheduled || 0,'text-[#13478A]'),
        ].join('');
    }

    /**
     * Toggle the active tab and lazy-render its panel.
     * Active state is driven by adding `text-wa-deep` +
     * `border-wa-deep` to the underline, swapping out the muted
     * default `text-ink-500 border-transparent`. Counts beside
     * each label come from the rolled-up details payload.
     */
    function setDetailsTab(tab, data) {
        document.querySelectorAll('.details-tab').forEach((b) => {
            const active = b.dataset.detailsTab === tab;
            b.classList.toggle('text-wa-deep',     active);
            b.classList.toggle('border-wa-deep',   active);
            b.classList.toggle('text-ink-500',     !active);
            b.classList.toggle('border-transparent', !active);
            b.setAttribute('aria-current', String(active));
        });
        document.querySelectorAll('.details-panel').forEach((p) => {
            p.classList.toggle('hidden', p.dataset.detailsPanel !== tab);
        });
        const panel = document.querySelector(`[data-details-panel="${tab}"]`);
        if (!panel) return;
        switch (tab) {
            case 'overview':    panel.innerHTML = renderDetailsOverview(data);    break;
            case 'recipients':  panel.innerHTML = renderDetailsRecipients(data);  break;
            case 'messages':    panel.innerHTML = renderDetailsMessages(data);    break;
            case 'attachments': panel.innerHTML = renderDetailsAttachments(data); break;
        }
    }

    function applyDetailsTabCounts(d) {
        const counts = {
            recipients:  d.recipients?.length || 0,
            messages:    d.messages?.length || 0,
            attachments: d.attachments?.length || 0,
        };
        Object.entries(counts).forEach(([k, v]) => {
            const el = document.querySelector(`[data-details-tab-count="${k}"]`);
            if (el) el.textContent = v ? `· ${v}` : '';
        });
    }

    let detailsCurrent = null;
    async function openDetailsModal(convo) {
        const drawer = $('details-drawer');
        const panel  = $('details-panel');
        $('details-title').textContent = convo.title;
        $('details-subtitle').textContent = `${convo.recipients_count || 0} recipients · ${convo.status}`;
        $('details-stats').innerHTML = '';
        document.querySelectorAll('.details-panel').forEach((p) => p.innerHTML = '<div class="text-center text-[13px] text-ink-500 py-12">Loading…</div>');

        drawer.classList.remove('hidden');
        // small delay so the translate transition runs
        requestAnimationFrame(() => panel.classList.remove('translate-x-full'));

        try {
            const { data } = await api.details(convo.id);
            detailsCurrent = data;
            $('details-stats').innerHTML = renderDetailsStats(data);
            applyDetailsTabCounts(data);
            setDetailsTab('overview', data);
        } catch (e) {
            document.querySelectorAll('.details-panel').forEach((p) => {
                p.innerHTML = `<div class="text-center text-[13px] text-accent-coral py-12">Failed to load details: ${escapeHtml(e.message)}</div>`;
            });
        }
    }
    function closeDetailsDrawer() {
        const drawer = $('details-drawer');
        const panel  = $('details-panel');
        panel.classList.add('translate-x-full');
        setTimeout(() => drawer.classList.add('hidden'), 200);
    }
    function wireDetailsModal() {
        $('details-close').addEventListener('click', closeDetailsDrawer);
        $('details-backdrop').addEventListener('click', closeDetailsDrawer);
        document.querySelectorAll('[data-details-tab]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (!detailsCurrent) return;
                setDetailsTab(btn.dataset.detailsTab, detailsCurrent);
            });
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !$('details-drawer').classList.contains('hidden')) closeDetailsDrawer();
        });
    }

    /* ---- AI assist drawer ---- */
    let aiCurrentTool = 'summary';
    let aiLastOutput  = '';
    function openAiDrawer() {
        if (!state.activeId) {
            window.WaToaster?.warn?.('Pick a thread first to use AI assist.');
            return;
        }
        const drawer = $('ai-drawer'), panel = $('ai-panel');
        drawer.classList.remove('hidden');
        $('ai-thread-name').textContent = $('thread-title')?.textContent || 'Conversation';
        requestAnimationFrame(() => panel.classList.remove('translate-x-full'));
        // default to summarize
        setAiTool(aiCurrentTool);
    }
    function closeAiDrawer() {
        const drawer = $('ai-drawer'), panel = $('ai-panel');
        panel.classList.add('translate-x-full');
        setTimeout(() => drawer.classList.add('hidden'), 200);
    }
    function setAiTool(tool) {
        aiCurrentTool = tool;
        document.querySelectorAll('[data-ai-tool]').forEach((b) => {
            b.classList.toggle('active', b.dataset.aiTool === tool);
        });
        const inputRow   = $('ai-input-row');
        const inputLabel = $('ai-input-label');
        const inputBox   = $('ai-input');
        const needsInput = (tool === 'rewrite' || tool === 'translate');
        inputRow.classList.toggle('hidden', !needsInput);
        if (needsInput) {
            inputLabel.textContent = tool === 'rewrite' ? 'Target tone (e.g. friendly, formal, brief)' : 'Target language (e.g. Hindi, French)';
            inputBox.placeholder   = tool === 'rewrite' ? 'friendly' : 'English';
            inputBox.value         = '';
        }
    }
    async function runAi() {
        if (!state.activeId) return;
        const tool   = aiCurrentTool;
        const model  = $('ai-model').value;
        const input  = $('ai-input').value.trim();
        const status = $('ai-status');
        const out    = $('ai-output');
        status.textContent = 'Thinking...';
        out.innerHTML = '<div class="text-ink-500 text-[12.5px] flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-wa-deep/40 animate-pulse"></span>Calling ' + model + '...</div>';
        $('ai-copy').disabled = true;
        $('ai-use').disabled  = true;
        try {
            const res = await fetch(`/chat/api/conversations/${state.activeId}/ai`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': state.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ tool, model, input }),
            });
            const data = await res.json();
            if (!res.ok || data.ok === false) throw new Error(data.message || ('HTTP ' + res.status));
            aiLastOutput = data.output || '';
            out.textContent = aiLastOutput;
            status.textContent = `${data.message_count} messages used`;
            $('ai-copy').disabled = !aiLastOutput;
            $('ai-use').disabled  = !aiLastOutput;
        } catch (e) {
            status.textContent = 'Error';
            out.innerHTML = '<div class="text-accent-coral text-[12.5px]">' + (e.message || 'Failed') + '</div>';
        }
    }
    function wireAiDrawer() {
        $('thread-ai')?.addEventListener('click', openAiDrawer);
        $('ai-close')?.addEventListener('click', closeAiDrawer);
        $('ai-backdrop')?.addEventListener('click', closeAiDrawer);
        document.querySelectorAll('[data-ai-tool]').forEach((b) => {
            b.addEventListener('click', () => setAiTool(b.dataset.aiTool));
        });
        $('ai-run')?.addEventListener('click', runAi);
        $('ai-copy')?.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(aiLastOutput || '');
                window.WaToaster?.success?.('Copied');
            } catch (e) {
                window.WaToaster?.error?.('Copy failed');
            }
        });
        $('ai-use')?.addEventListener('click', () => {
            if (!aiLastOutput) return;
            const c = $('composer-input') || composer;
            if (c) {
                c.value = aiLastOutput;
                c.focus();
                if (typeof syncSendState === 'function') syncSendState();
            }
            closeAiDrawer();
            window.WaToaster?.info?.('Reply pasted into composer / edit and send when ready');
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !$('ai-drawer').classList.contains('hidden')) closeAiDrawer();
        });
    }

    /* ---- schedule indicator ---- */
    function showScheduleIndicator(value) {
        pendingScheduleAt = value || null;
        const wrap = $('schedule-indicator');
        const text = $('schedule-indicator-text');
        if (!pendingScheduleAt) {
            wrap.classList.add('hidden');
            text.textContent = '';
            return;
        }
        const when = new Date(pendingScheduleAt);
        text.textContent = `Scheduled for ${when.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })}`;
        wrap.classList.remove('hidden');
    }

    /* ---- composer ---- */
    function syncSendState() {
        sendBtn.disabled = composer.value.trim().length === 0 && !pendingMedia;
    }

    /**
     * Show a WhatsApp-style attachment preview above the composer
     * (image thumbnail / video first-frame / document or audio
     * filename + a remove button). Object URLs are revoked on
     * clear so the renderer doesn't leak blob memory.
     */
    let pendingMediaPreviewUrl = null;
    function showMediaPreview(file) {
        const banner = $('media-preview');
        const thumb  = $('media-preview-thumb');
        const name   = $('media-preview-name');
        const meta   = $('media-preview-meta');
        if (!file) return clearMediaPreview();

        if (pendingMediaPreviewUrl) URL.revokeObjectURL(pendingMediaPreviewUrl);
        pendingMediaPreviewUrl = null;

        thumb.innerHTML = '';
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        const sizeKb = file.size / 1024;
        const sizeLbl = sizeKb >= 1024 ? `${(sizeKb / 1024).toFixed(1)} MB` : `${Math.round(sizeKb)} KB`;

        if (file.type.startsWith('image/')) {
            pendingMediaPreviewUrl = URL.createObjectURL(file);
            thumb.innerHTML = `<img src="${pendingMediaPreviewUrl}" alt="" class="w-full h-full object-cover">`;
        } else if (file.type.startsWith('video/')) {
            pendingMediaPreviewUrl = URL.createObjectURL(file);
            thumb.innerHTML = `
                <div class="relative w-full h-full">
                    <video src="${pendingMediaPreviewUrl}" muted class="w-full h-full object-cover"></video>
                    <span class="absolute inset-0 grid place-items-center text-paper-0 bg-ink-900/40">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="currentColor"><path d="M4 3l9 5-9 5z"/></svg>
                    </span>
                </div>`;
        } else if (file.type.startsWith('audio/')) {
            thumb.innerHTML = `
                <svg viewBox="0 0 16 16" class="w-5 h-5 text-wa-deep" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M8 2v12M5 6a3 3 0 0 1 6 0M3 8a5 5 0 0 0 10 0"/>
                </svg>`;
        } else {
            thumb.innerHTML = `
                <div class="grid place-items-center text-wa-deep">
                    <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 2h6l3 3v9H4zM10 2v3h3"/></svg>
                    <span class="font-mono text-[8px] uppercase tracking-wider mt-0.5">${escapeHtml(ext)}</span>
                </div>`;
        }

        name.textContent = file.name;
        meta.textContent = `${file.type || 'file'} · ${sizeLbl}`;
        banner.classList.remove('hidden');
    }
    function clearMediaPreview() {
        if (pendingMediaPreviewUrl) URL.revokeObjectURL(pendingMediaPreviewUrl);
        pendingMediaPreviewUrl = null;
        $('media-preview').classList.add('hidden');
        $('media-preview-thumb').innerHTML = '';
        $('media-preview-name').textContent = '';
        $('media-preview-meta').textContent = '';
        pendingMedia = null;
        mediaInput.value = '';
        syncSendState();
    }

    function closeComposerPanels() {
        attachMenu.classList.add('hidden');
        emojiPanel.classList.add('hidden');
        attachBtn.setAttribute('aria-expanded', 'false');
        emojiBtn.setAttribute('aria-expanded',  'false');
    }

    function togglePanel(panel, trigger) {
        const willOpen = panel.classList.contains('hidden');
        closeComposerPanels();
        if (willOpen) {
            panel.classList.remove('hidden');
            trigger.setAttribute('aria-expanded', 'true');
        }
    }

    function insertComposerText(text) {
        const start = composer.selectionStart;
        const end   = composer.selectionEnd;
        composer.value = composer.value.substring(0, start) + text + composer.value.substring(end);
        const next = start + text.length;
        composer.focus();
        composer.setSelectionRange(next, next);
        syncSendState();
    }

    async function sendMessage() {
        if (!activeQueueId) return showToast('Select a queue first');
        const body = composer.value.trim();
        if (!body && !pendingMedia) return;

        const form = new FormData();
        if (body) form.append('body', body);
        if (pendingMedia) form.append('media', pendingMedia);
        if (pendingScheduleAt) form.append('scheduled_at', pendingScheduleAt);
        if (pendingScheduleAt && pendingScheduleTz) form.append('timezone', pendingScheduleTz);

        try {
            sendBtn.disabled = true;
            const { data, meta } = await api.send(activeQueueId, form);
            const msgs = messagesById.get(activeQueueId) || [];
            msgs.push(data.message);
            messagesById.set(activeQueueId, msgs);
            applyCounts(meta);

            // splice the updated conversation back into the list (so the queue re-sorts)
            const idx = conversations.findIndex((c) => c.id === activeQueueId);
            if (idx >= 0) conversations[idx] = data.conversation;
            renderQueues();
            renderThread(data.conversation);

            const wasScheduled = !!pendingScheduleAt;
            composer.value = '';
            clearMediaPreview();
            showScheduleIndicator(null);
            showToast(wasScheduled ? 'Scheduled' : 'Message sent');
        } catch (e) {
            showToast(`Send failed: ${e.message}`);
        } finally {
            syncSendState();
        }
    }

    /**
     * Send a location pin as a standalone message (no body), using
     * the same /messages endpoint with latitude/longitude form
     * fields that the controller already accepts.
     */
    async function sendLocation(lat, lng) {
        if (!activeQueueId) return showToast('Select a queue first');
        const form = new FormData();
        form.append('latitude',  String(lat));
        form.append('longitude', String(lng));
        // No body — Laravel will build the right Baileys location
        // payload (`/api/send-location`) which sends a real WhatsApp
        // location pin (with native map preview), not a maps.google URL.
        try {
            const { data, meta } = await api.send(activeQueueId, form);
            const msgs = messagesById.get(activeQueueId) || [];
            msgs.push(data.message);
            messagesById.set(activeQueueId, msgs);
            applyCounts(meta);
            const idx = conversations.findIndex((c) => c.id === activeQueueId);
            if (idx >= 0) conversations[idx] = data.conversation;
            renderQueues();
            renderThread(data.conversation);
            showToast('Location sent');
        } catch (e) {
            showToast(`Send failed: ${e.message}`);
        }
    }

    /* ---- archive ---- */
    async function toggleArchive() {
        if (!activeQueueId) return;
        const convo = conversations.find((c) => c.id === activeQueueId);
        if (!convo) return;
        try {
            const { meta } = convo.archived ? await api.unarchive(activeQueueId)
                                             : await api.archive(activeQueueId);
            applyCounts(meta);
            await loadQueues();
            showToast(convo.archived ? 'Unarchived' : 'Archived');
        } catch (e) {
            showToast(`Failed: ${e.message}`);
        }
    }

    /* ---- templates ---- */
    function getCategoryLabel(c) { return TEMPLATE_CATEGORY_LABELS[c] || TEMPLATE_CATEGORY_LABELS.all; }
    function getCategoryClasses(c) { return TEMPLATE_CATEGORY_CLASSES[c] || TEMPLATE_CATEGORY_CLASSES.authentication; }

    function renderTemplatePicker() {
        const convo = conversations.find((c) => c.id === activeQueueId);
        $('template-modal-eyebrow').textContent = getCategoryLabel(currentTemplateCat);
        $('template-target').textContent = convo ? convo.title : 'No queue selected';
        templateSend.disabled = !selectedTemplateId || !convo;

        templateCardList.innerHTML = '';
        templates.forEach((t) => {
            const card = document.createElement('button');
            card.type = 'button';
            card.setAttribute('aria-selected', String(t.id === selectedTemplateId));
            card.className = 'template-card text-left rounded-[14px] border border-paper-200 bg-white p-3 transition hover:border-wa-deep hover:bg-wa-mint/30 [aria-selected=true]:border-wa-deep [aria-selected=true]:bg-wa-mint/60';
            card.innerHTML = `
                <div class="flex items-start gap-3">
                    <span class="w-9 h-9 rounded-xl border grid place-items-center shrink-0 ${getCategoryClasses(t.category)}">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2.5" y="2.5" width="11" height="11" rx="1.5"/><path d="M2.5 6h11M6 13.5V6"/></svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-[13px] truncate">${escapeHtml(t.title)}</span>
                        <span class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold ${getCategoryClasses(t.category)}">${escapeHtml(t.tone || t.category)}</span>
                        <span class="mt-2 block text-[12px] text-ink-500 leading-snug line-clamp-2">${escapeHtml(t.body)}</span>
                    </span>
                </div>`;
            card.addEventListener('click', () => {
                selectedTemplateId = t.id;
                // A different template has different slots — carrying values
                // across would map slot 1 of A onto an unrelated slot 1 of B.
                templateOverrides = {};
                renderTemplatePicker();
            });
            templateCardList.appendChild(card);
        });

        const selected = templates.find((t) => t.id === selectedTemplateId);
        if (!selected) {
            templatePreview.innerHTML = 'Select a template to preview it here.';
            const empty = document.getElementById('chat-tlm-panel');
            if (empty) { empty.innerHTML = ''; empty.classList.add('hidden'); }
            return;
        }
        // Send-time mapping panel — shared renderer, no second implementation.
        mountTemplateMapping(
            document.getElementById('chat-tlm-panel'),
            selected.send_meta || null,
            templateOverrides,
            (next) => { templateOverrides = next; },
        );
        const sel = selected;
        const carouselImg = (card) => {
            if (card.image_url) return card.image_url;
            if (typeof card.image === 'string' && card.image.startsWith('http')) return card.image;
            if (card.image_filename) return '/uploads/templates/carousel/' + card.image_filename;
            if (card.image) return '/uploads/templates/carousel/' + card.image;
            return '';
        };
        let html = `
            <div class="font-semibold text-ink-900">${escapeHtml(sel.title)}</div>
            <div class="mt-2 inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold ${getCategoryClasses(sel.category)}">${escapeHtml(sel.tone || sel.category)}</div>
            <div class="mt-4 rounded-2xl border border-wa-deep/20 bg-wa-mint p-3 text-[13px] leading-relaxed text-ink-800">`;
        // Header media
        if (sel.media_url) {
            const mt = (sel.media_type || '').toLowerCase();
            if (mt === 'image') html += `<img src="${escapeHtml(sel.media_url)}" class="mb-2 w-full rounded-lg object-cover max-h-44" onerror="this.style.display='none'">`;
            else if (mt === 'video') html += `<video src="${escapeHtml(sel.media_url)}" class="mb-2 w-full rounded-lg max-h-44" controls></video>`;
            else html += `<div class="mb-2 inline-flex items-center gap-1.5 rounded-lg bg-white/60 px-2 py-1 text-[11px] text-ink-600"><svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 4.5 5 8.5a1.5 1.5 0 0 0 2 2l4-4a3 3 0 0 0-4-4L3 6.5a4.5 4.5 0 0 0 6 6L12 9.5"/></svg>${escapeHtml(sel.media_type || 'file')}</div>`;
        }
        if (sel.body) html += `<div>${parseMessage(sel.body)}</div>`;
        if (sel.footer) html += `<div class="mt-2 text-[11px] text-ink-400">${escapeHtml(sel.footer)}</div>`;
        html += `</div>`;
        // Top-level buttons — WhatsApp renders at most 3, with a small
        // leading icon per action type so the preview matches the real send.
        if (Array.isArray(sel.buttons) && sel.buttons.length) {
            const btnIcon = (type) => {
                const t = String(type || '').toLowerCase();
                if (t.includes('url') || t.includes('website') || t.includes('link'))
                    return '<svg viewBox="0 0 16 16" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6.5 9.5 9.5 6.5M7 4h3.5V7.5M10 9.5V12H4V6h2.5"/></svg>';
                if (t.includes('call') || t.includes('phone'))
                    return '<svg viewBox="0 0 16 16" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3.5 3h2l1 3-1.5 1a7 7 0 0 0 3 3l1-1.5 3 1v2a1 1 0 0 1-1 1A9.5 9.5 0 0 1 2.5 4a1 1 0 0 1 1-1z"/></svg>';
                if (t.includes('copy') || t.includes('coupon'))
                    return '<svg viewBox="0 0 16 16" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5.5" y="5.5" width="7.5" height="7.5" rx="1.5"/><path d="M3 10.5V4a1 1 0 0 1 1-1h6.5"/></svg>';
                return ''; // quick reply: no icon
            };
            html += `<div class="mt-2 space-y-1">`;
            sel.buttons.slice(0, 3).forEach((b) => {
                const label = b.text || b.title || b.display_text || b.value || 'Button';
                html += `<div class="flex items-center justify-center gap-1.5 rounded-lg border border-wa-deep/20 bg-white px-3 py-1.5 text-center text-[12px] font-semibold text-wa-deep">${btnIcon(b.type)}<span class="truncate">${escapeHtml(label)}</span></div>`;
            });
            html += `</div>`;
        }
        // Carousel cards
        if (sel.template_type === 'carousel' && Array.isArray(sel.carousel_data) && sel.carousel_data.length) {
            html += `<div class="mt-3 flex gap-2 overflow-x-auto pb-1">`;
            sel.carousel_data.forEach((card) => {
                const cimg = carouselImg(card);
                html += `<div class="min-w-[150px] max-w-[160px] shrink-0 rounded-xl border border-paper-200 bg-white p-2">`;
                html += cimg
                    ? `<img src="${escapeHtml(cimg)}" class="mb-1.5 h-20 w-full rounded-lg object-cover" onerror="this.style.display='none'">`
                    : `<div class="mb-1.5 grid h-20 w-full place-items-center rounded-lg bg-paper-50 text-[10px] text-ink-400">image</div>`;
                if (card.title) html += `<div class="text-[11px] font-semibold truncate">${escapeHtml(card.title)}</div>`;
                if (card.body) html += `<div class="mt-0.5 text-[10px] text-ink-500 line-clamp-2">${escapeHtml(card.body)}</div>`;
                (Array.isArray(card.buttons) ? card.buttons : []).forEach((cb) => {
                    const lbl = cb.text || cb.title || cb.value || 'Button';
                    html += `<div class="mt-1 rounded-md border border-wa-deep/20 px-1.5 py-1 text-center text-[10px] font-semibold text-wa-deep">${escapeHtml(lbl)}</div>`;
                });
                html += `</div>`;
            });
            html += `</div>`;
        }
        templatePreview.innerHTML = html;
    }

    function renderTemplateSkeleton() {
        const convo = conversations.find((c) => c.id === activeQueueId);
        $('template-modal-eyebrow').textContent = getCategoryLabel(currentTemplateCat);
        $('template-target').textContent = convo ? convo.title : 'No queue selected';
        templateSend.disabled = true;
        templatePreview.innerHTML = `<div class="text-ink-500">${t('Loading templates…')}</div>`;
        templateCardList.innerHTML = '';
        for (let i = 0; i < 6; i++) {
            const skel = document.createElement('div');
            skel.className = 'rounded-[14px] border border-paper-200 bg-white p-3 animate-pulse';
            skel.innerHTML = `
                <div class="flex items-start gap-3">
                    <span class="w-9 h-9 rounded-xl bg-paper-100"></span>
                    <span class="min-w-0 flex-1 grid gap-2">
                        <span class="h-3 w-2/3 rounded bg-paper-100"></span>
                        <span class="h-2.5 w-1/3 rounded-full bg-paper-100"></span>
                        <span class="h-2 w-full rounded bg-paper-100"></span>
                        <span class="h-2 w-5/6 rounded bg-paper-100"></span>
                    </span>
                </div>`;
            templateCardList.appendChild(skel);
        }
    }

    // Each open() bumps the token; only the most recent fetch is
    // allowed to populate the picker — guards against a stale request
    // (e.g. user reopens with a different category mid-fetch) writing
    // over fresh data.
    let templateFetchToken = 0;

    async function openTemplatePicker(category = 'all') {
        currentTemplateCat = category;
        const token = ++templateFetchToken;

        // Show the modal + skeleton synchronously so the click feels
        // instant — no perceived gap between dropdown-close and
        // modal-open while the fetch is in flight.
        renderTemplateSkeleton();
        templateModal.classList.remove('hidden');
        templateModal.classList.add('flex');

        try {
            const { data } = await api.templates(category);
            if (token !== templateFetchToken) return; // a newer open() superseded us
            templates = data;
            selectedTemplateId = data[0]?.id || null;
            renderTemplatePicker();
        } catch (e) {
            if (token !== templateFetchToken) return;
            templateCardList.innerHTML = `<div class="col-span-2 text-[13px] text-accent-coral">Failed to load templates: ${escapeHtml(e.message || 'unknown error')}</div>`;
            templatePreview.innerHTML = 'Could not load templates.';
        }
    }

    function closeTemplatePicker() {
        templateModal.classList.add('hidden');
        templateModal.classList.remove('flex');
    }

    async function sendSelectedTemplate() {
        if (!selectedTemplateId || !activeQueueId) return showToast('Select a queue and template first');
        try {
            const { data, meta } = await api.template(activeQueueId, selectedTemplateId, templateOverrides);
            const msgs = messagesById.get(activeQueueId) || [];
            msgs.push(data.message);
            messagesById.set(activeQueueId, msgs);

            const idx = conversations.findIndex((c) => c.id === activeQueueId);
            if (idx >= 0) conversations[idx] = data.conversation;

            applyCounts(meta);
            renderQueues();
            renderThread(data.conversation);
            closeTemplatePicker();
            showToast('Template sent');
        } catch (e) {
            showToast(`Failed: ${e.message}`);
        }
    }

    /* ---- emoji picker (lazy-loaded on first open) ---- */
    let emojiMounted = false;
    async function mountEmojiPicker() {
        if (emojiMounted) return;
        emojiMounted = true;
        // Dynamic import keeps the ~600KB emoji-picker-element bundle
        // out of the initial chat page load.
        await import('emoji-picker-element');
        const picker = document.createElement('emoji-picker');
        // `light` overrides the package's prefers-color-scheme dark-mode
        // detection so the picker always renders against the paper palette.
        picker.classList.add('chat-emoji-picker', 'light');
        picker.addEventListener('emoji-click', (event) => {
            const native = event.detail?.unicode || event.detail?.emoji?.unicode;
            if (native) insertComposerText(native);
        });
        $('emoji-mount').appendChild(picker);
    }

    /* ----------------------------------------------------------------
     * Per-message hover menu — WhatsApp-style chevron at the top-right
     * of every bubble. Click opens a floating popover with quick-react
     * emojis and a list of actions: Message info / Reply / React /
     * Forward / Pin / Star / Delete.
     *
     * Menu DOM is created lazily on first click and reused; closes on
     * outside click or Escape, just like the queue gear menu.
     * ---------------------------------------------------------------- */
    let pendingReplyTo = null;        // {id, body, direction} when user clicked Reply
    const QUICK_REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

    function findMessageById(id) {
        const msgs = messagesById.get(activeQueueId) || [];
        return msgs.find((m) => m.id === id) || null;
    }

    function wireMessageMenu() {
        // Create the popover once — we move + repopulate it per click.
        let popover = document.getElementById('msg-menu-pop');
        if (!popover) {
            popover = document.createElement('div');
            popover.id = 'msg-menu-pop';
            popover.className = 'hidden fixed z-[60] w-[200px] rounded-xl border border-paper-200 bg-white shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] overflow-hidden text-[13px]';
            document.body.appendChild(popover);
        }

        function close() {
            popover.classList.add('hidden');
            popover.innerHTML = '';
            popover._activeMessageId = null;
        }

        function open(messageId, anchorEl) {
            const m = findMessageById(messageId);
            if (!m) return;
            popover._activeMessageId = messageId;

            const reactionsRow = QUICK_REACTIONS
                .map((e) => `<button type="button" data-react="${e}" class="w-9 h-9 rounded-full hover:bg-paper-50 grid place-items-center text-[18px] transition">${e}</button>`)
                .join('');

            const item = (action, label, glyph) => `
                <button type="button" data-action="${action}" class="w-full flex items-center gap-3 px-3.5 py-2 hover:bg-paper-50 text-left">
                    <span class="w-4 h-4 text-ink-700">${glyph}</span>
                    <span class="text-[13px] text-ink-900">${label}</span>
                </button>`;

            popover.innerHTML = `
                <div class="px-2 py-2 border-b border-paper-200 flex items-center justify-around gap-0.5">
                    ${reactionsRow}
                    <button type="button" data-action="react-clear" class="w-9 h-9 rounded-full hover:bg-paper-50 grid place-items-center text-ink-500" title="Clear reaction">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                    </button>
                </div>
                <div class="py-1">
                    ${item('info',    'Message info', `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6.4"/><path d="M8 7v4M8 5h.01"/></svg>`)}
                    ${item('reply',   'Reply',        `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M7 4L3 8l4 4M3 8h7a3 3 0 0 1 3 3v1"/></svg>`)}
                    ${item('forward', 'Forward',      `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 4l4 4-4 4M13 8H6a3 3 0 0 0-3 3v1"/></svg>`)}
                    ${item('pin',     m.pinned ? 'Unpin' : 'Pin',  `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 1.5h6l-1 4 2 1-2 1H4l2-1-2-1zM8 7v7"/></svg>`)}
                    ${item('star',    m.starred ? 'Unstar' : 'Star', `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 1.5l2 4.5 5 .5-3.7 3.4 1.1 4.9L8 11.8l-4.4 2.5 1.1-4.9L1 6.5l5-.5z"/></svg>`)}
                </div>
                <div class="border-t border-paper-200 py-1">
                    ${item('delete',  'Delete',       `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" class="text-accent-coral"><path d="M3 4h10M5 4V2.5h6V4M5 4v9a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V4"/></svg>`)}
                </div>`;

            popover.classList.remove('hidden');

            // Position: under the anchor if there's room, else above.
            const r = anchorEl.getBoundingClientRect();
            const popH = popover.offsetHeight;
            const popW = popover.offsetWidth;
            let top = r.bottom + 6;
            if (top + popH > window.innerHeight - 12) top = Math.max(12, r.top - popH - 6);
            let left = r.right - popW;
            if (left < 12) left = 12;
            popover.style.top  = top + 'px';
            popover.style.left = left + 'px';
        }

        // Click delegation for the chevron buttons rendered inside bubbles.
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-msg-menu]');
            if (trigger) {
                e.stopPropagation();
                const id = parseInt(trigger.getAttribute('data-msg-menu'), 10);
                if (popover._activeMessageId === id) { close(); return; }
                open(id, trigger);
                return;
            }
            // Click inside the popover — handle action buttons.
            const inside = e.target.closest('#msg-menu-pop');
            if (inside) {
                const reactBtn  = e.target.closest('[data-react]');
                const actionBtn = e.target.closest('[data-action]');
                const id = popover._activeMessageId;
                if (reactBtn && id) {
                    handleReact(id, reactBtn.getAttribute('data-react'));
                    close();
                    return;
                }
                if (actionBtn && id) {
                    const action = actionBtn.getAttribute('data-action');
                    handleMessageAction(id, action);
                    close();
                    return;
                }
                return;
            }
            // Click anywhere else — close.
            if (!popover.classList.contains('hidden')) close();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !popover.classList.contains('hidden')) close();
        });
    }

    async function handleReact(messageId, emoji) {
        try {
            const r = await api.call('POST', `/conversations/${activeQueueId}/messages/${messageId}/react`, { json: { emoji } });
            const msgs = messagesById.get(activeQueueId) || [];
            const idx = msgs.findIndex((x) => x.id === messageId);
            if (idx >= 0) {
                msgs[idx] = { ...msgs[idx], reaction: r.data.reaction };
                messagesById.set(activeQueueId, msgs);
                const convo = conversations.find((c) => c.id === activeQueueId);
                if (convo) renderThread(convo);
            }
            showToast(emoji ? `Reacted with ${emoji}` : 'Reaction cleared', 'success');
        } catch (e) {
            showToast(`React failed: ${e.message}`, 'warn');
        }
    }

    async function handleMessageAction(id, action) {
        switch (action) {
            case 'react-clear': return handleReact(id, '');
            case 'info':        return openMessageInfo(id);
            case 'reply':       return startReplyTo(id);
            case 'forward':     return openForwardPicker(id);
            case 'pin':         return togglePin(id);
            case 'star':        return toggleStar(id);
            case 'delete':      return deleteMessage(id);
        }
    }

    async function togglePin(id) {
        try {
            const r = await api.call('PATCH', `/conversations/${activeQueueId}/messages/${id}/pin`);
            updateMessageLocal(id, { pinned: r.data.pinned });
            showToast(r.data.pinned ? '📌 Pinned' : 'Unpinned', 'success');
        } catch (e) { showToast(`Pin failed: ${e.message}`, 'warn'); }
    }
    async function toggleStar(id) {
        try {
            const r = await api.call('PATCH', `/conversations/${activeQueueId}/messages/${id}/star`);
            updateMessageLocal(id, { starred: r.data.starred });
            showToast(r.data.starred ? '⭐ Starred' : 'Unstarred', 'success');
        } catch (e) { showToast(`Star failed: ${e.message}`, 'warn'); }
    }
    async function deleteMessage(id) {
        const ok = await confirmAction({ title: 'Delete message?', body: 'This removes it from your queue history. The recipient may already have it.' });
        if (!ok) return;
        try {
            await api.call('DELETE', `/conversations/${activeQueueId}/messages/${id}`);
            const msgs = (messagesById.get(activeQueueId) || []).filter((x) => x.id !== id);
            messagesById.set(activeQueueId, msgs);
            const convo = conversations.find((c) => c.id === activeQueueId);
            if (convo) renderThread(convo);
            showToast('Deleted', 'success');
        } catch (e) { showToast(`Delete failed: ${e.message}`, 'warn'); }
    }

    function updateMessageLocal(id, patch) {
        const msgs = messagesById.get(activeQueueId) || [];
        const idx = msgs.findIndex((x) => x.id === id);
        if (idx < 0) return;
        msgs[idx] = { ...msgs[idx], ...patch };
        messagesById.set(activeQueueId, msgs);
        const convo = conversations.find((c) => c.id === activeQueueId);
        if (convo) renderThread(convo);
    }

    function startReplyTo(id) {
        const m = findMessageById(id);
        if (!m) return;
        pendingReplyTo = { id: m.id, body: m.body || (m.media_type ? `[${m.media_type}]` : ''), direction: m.direction };
        showReplyIndicator();
        const composer = $('composer');
        composer?.focus();
    }
    function clearReplyTo() {
        pendingReplyTo = null;
        const ind = document.getElementById('reply-indicator');
        if (ind) ind.remove();
    }
    function showReplyIndicator() {
        if (!pendingReplyTo) return;
        let ind = document.getElementById('reply-indicator');
        if (!ind) {
            ind = document.createElement('div');
            ind.id = 'reply-indicator';
            ind.className = 'mb-2 flex items-start gap-2 px-3 py-2 rounded-xl bg-wa-mint/40 border-l-4 border-wa-deep text-[12px]';
            const composerWrap = $('composer')?.closest('form, div');
            composerWrap?.parentElement?.insertBefore(ind, composerWrap);
        }
        ind.innerHTML = `
            <div class="flex-1 min-w-0">
                <div class="text-[10.5px] font-mono uppercase tracking-wide text-wa-deep">Replying to ${pendingReplyTo.direction === 'out' ? 'your message' : 'them'}</div>
                <div class="truncate text-ink-700">${escapeHtml((pendingReplyTo.body || '').substring(0, 200))}</div>
            </div>
            <button type="button" id="reply-cancel" class="w-5 h-5 rounded-full border border-paper-300 hover:bg-white grid place-items-center" title="Cancel reply">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4l8 8M12 4l-8 8"/></svg>
            </button>`;
        document.getElementById('reply-cancel')?.addEventListener('click', clearReplyTo);
    }

    async function openForwardPicker(id) {
        const m = findMessageById(id);
        if (!m) return;
        // Quick inline picker: pick from currently-loaded conversations.
        const choices = conversations
            .filter((c) => c.id !== activeQueueId && c.recipients_count > 0)
            .slice(0, 50);
        if (!choices.length) return showToast('No other queue to forward to', 'warn');
        // Build a simple modal.
        let modal = document.getElementById('forward-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'forward-modal';
            modal.className = 'fixed inset-0 z-[55] flex items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]';
            document.body.appendChild(modal);
        }
        modal.innerHTML = `
            <div class="w-full max-w-md bg-paper-0 border border-paper-200 rounded-2xl shadow-soft overflow-hidden">
                <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between">
                    <h3 class="font-serif text-[18px] leading-tight">Forward to…</h3>
                    <button type="button" id="forward-close" class="w-7 h-7 rounded-full hover:bg-paper-50 grid place-items-center text-ink-500"><svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4l8 8M12 4l-8 8"/></svg></button>
                </div>
                <div class="max-h-[60vh] overflow-y-auto divide-y divide-paper-100">
                    ${choices.map((c) => `
                        <button type="button" data-fw-target="${c.id}" class="w-full px-4 py-2.5 text-left hover:bg-paper-50 flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-wa-deep/15 text-wa-deep font-semibold grid place-items-center text-[12px]">${escapeHtml((c.title || '?').substring(0, 2).toUpperCase())}</span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-[13px] font-semibold truncate">${escapeHtml(c.title || 'Queue ' + c.id)}</span>
                                <span class="block text-[11px] text-ink-500">${c.recipients_count} recipient${c.recipients_count === 1 ? '' : 's'}</span>
                            </span>
                        </button>`).join('')}
                </div>
            </div>`;
        const close = () => modal.remove();
        modal.querySelector('#forward-close').addEventListener('click', close);
        modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
        modal.querySelectorAll('[data-fw-target]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const tid = parseInt(btn.getAttribute('data-fw-target'), 10);
                close();
                try {
                    await api.call('POST', `/conversations/${activeQueueId}/messages/${id}/forward`, { json: { target_conversation_id: tid } });
                    showToast('Forwarded ✓', 'success');
                } catch (e) { showToast(`Forward failed: ${e.message}`, 'warn'); }
            });
        });
    }

    async function openMessageInfo(id) {
        try {
            const { data } = await api.call('GET', `/conversations/${activeQueueId}/messages/${id}/info`);
            let modal = document.getElementById('msg-info-modal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'msg-info-modal';
                modal.className = 'fixed inset-0 z-[55] flex items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]';
                document.body.appendChild(modal);
            }
            const fmt = (iso) => iso ? new Date(iso).toLocaleString() : '—';
            const row = (label, value, tone) => `
                <div class="flex items-center justify-between gap-3 px-4 py-2 ${tone || ''}">
                    <span class="text-[11.5px] font-mono uppercase tracking-wide text-ink-500">${label}</span>
                    <span class="text-[12.5px] font-medium text-ink-900 text-right truncate">${escapeHtml(String(value ?? '—'))}</span>
                </div>`;
            modal.innerHTML = `
                <div class="w-full max-w-md bg-paper-0 border border-paper-200 rounded-2xl shadow-soft overflow-hidden">
                    <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between">
                        <h3 class="font-serif text-[18px] leading-tight">Message info</h3>
                        <button type="button" id="msg-info-close" class="w-7 h-7 rounded-full hover:bg-paper-50 grid place-items-center text-ink-500"><svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4l8 8M12 4l-8 8"/></svg></button>
                    </div>
                    <div class="divide-y divide-paper-100">
                        ${row('ID', '#' + data.id)}
                        ${row('Direction', data.direction)}
                        ${row('Status', data.status)}
                        ${row('From', data.from_number || '—')}
                        ${row('To', data.to_number || '—')}
                        ${row('Created', fmt(data.created_at))}
                        ${data.scheduled_at ? row('Scheduled for', fmt(data.scheduled_at)) : ''}
                        ${row('Sent at', fmt(data.sent_at))}
                        ${row('Delivered at', fmt(data.delivered_at))}
                        ${row('Read at', fmt(data.read_at))}
                        ${data.reaction ? row('Reaction', data.reaction) : ''}
                        ${data.media_name ? row('Attachment', data.media_name + (data.media_type ? ' (' + data.media_type + ')' : '')) : ''}
                        ${data.failure_reason ? row('Failure', data.failure_reason, 'bg-accent-coral/5') : ''}
                    </div>
                </div>`;
            const close = () => modal.remove();
            modal.querySelector('#msg-info-close').addEventListener('click', close);
            modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
            const onKey = (e) => { if (e.key === 'Escape') { close(); document.removeEventListener('keydown', onKey); } };
            document.addEventListener('keydown', onKey);
        } catch (e) {
            showToast(`Info load failed: ${e.message}`, 'warn');
        }
    }

    /* ---- schedule modal ---- */
    function wireScheduleModal() {
        const modal = $('schedule-modal');
        const date  = $('schedule-date');
        const time  = $('schedule-time');
        const tzSel = $('schedule-timezone');

        function open() {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            // Pre-fill with the current pending schedule if any.
            if (pendingScheduleAt) {
                const [d, t] = pendingScheduleAt.split('T');
                date.value = d || '';
                time.value = (t || '').slice(0, 5);
                if (pendingScheduleTz && tzSel) tzSel.value = pendingScheduleTz;
            } else {
                date.value = '';
                time.value = '';
                // tzSel keeps its blade-prefilled default
            }
            setTimeout(() => date.focus(), 30);
        }
        function close() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        $('schedule-modal-close').addEventListener('click', close);
        $('schedule-cancel').addEventListener('click', close);
        modal.addEventListener('click', (e) => { if (e.target === modal) close(); });

        $('schedule-clear').addEventListener('click', () => {
            pendingScheduleTz = null;
            showScheduleIndicator(null);
            close();
        });
        $('schedule-save').addEventListener('click', () => {
            if (!date.value || !time.value) return showToast('Pick both date and time');
            const value = `${date.value}T${time.value}`;
            // Validate against current time IN the picked timezone.
            // We can't trust new Date(value).getTime() because that
            // interprets value in the BROWSER's tz, not the picked one.
            const tz = tzSel?.value || null;
            if (!isAtLeastOneMinuteFromNow(value, tz)) {
                return showToast('Schedule a time at least 1 minute in the future');
            }
            pendingScheduleTz = tz;
            showScheduleIndicator(value);
            close();
        });
        $('schedule-indicator-clear').addEventListener('click', () => {
            pendingScheduleTz = null;
            showScheduleIndicator(null);
        });

        return { open };
    }

    // Compare a "YYYY-MM-DDTHH:MM" wall-clock string interpreted in
    // a given IANA tz against the current moment.
    function isAtLeastOneMinuteFromNow(localString, tz) {
        if (!tz) return new Date(localString).getTime() > Date.now() + 60_000;
        try {
            // Parse "now" as a local-time string in the target tz, then
            // back-solve the UTC offset by formatting now via that tz.
            const target = new Date(localString + 'Z'); // naive UTC moment
            // Compute offset (in minutes) of `tz` for the target moment.
            const fmt = new Intl.DateTimeFormat('en-US', {
                timeZone: tz, hour12: false,
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit',
            });
            const parts = Object.fromEntries(fmt.formatToParts(target).map((p) => [p.type, p.value]));
            const tzAsUtc = Date.UTC(+parts.year, +parts.month - 1, +parts.day, +parts.hour, +parts.minute, +parts.second);
            const offsetMs = target.getTime() - tzAsUtc;
            const utcMs = target.getTime() + offsetMs;
            return utcMs > Date.now() + 60_000;
        } catch (e) {
            return new Date(localString).getTime() > Date.now() + 60_000;
        }
    }

    /* ---- location modal ---- */
    function wireLocationModal() {
        const modal = $('location-modal');
        const lat   = $('location-lat');
        const lng   = $('location-lng');
        const error = $('location-error');

        function open() {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            error.classList.add('hidden');
            error.textContent = '';
            lat.value = ''; lng.value = '';
        }
        function close() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        $('location-modal-close').addEventListener('click', close);
        $('location-cancel').addEventListener('click', close);
        modal.addEventListener('click', (e) => { if (e.target === modal) close(); });

        $('location-use-current').addEventListener('click', () => {
            if (!navigator.geolocation) {
                error.textContent = 'Geolocation is not available in this browser.';
                error.classList.remove('hidden');
                return;
            }
            error.classList.add('hidden');
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    lat.value = pos.coords.latitude.toFixed(7);
                    lng.value = pos.coords.longitude.toFixed(7);
                },
                (err) => {
                    error.textContent = `Could not get current location: ${err.message}`;
                    error.classList.remove('hidden');
                },
                { enableHighAccuracy: true, timeout: 10_000 }
            );
        });

        $('location-send').addEventListener('click', async () => {
            const la = parseFloat(lat.value);
            const ln = parseFloat(lng.value);
            if (!Number.isFinite(la) || !Number.isFinite(ln) || la < -90 || la > 90 || ln < -180 || ln > 180) {
                error.textContent = 'Latitude must be ±90 and longitude ±180.';
                error.classList.remove('hidden');
                return;
            }
            close();
            await sendLocation(la, ln);
        });

        return { open };
    }

    /* ---- compose (create new queue) ---- */
    function wireCompose() {
        const modal      = $('compose-modal');
        const form       = $('compose-form');
        const closeBtn   = $('compose-modal-close');
        const cancelBtn  = $('compose-cancel');
        const submitBtn  = $('compose-submit');
        const errorBox   = $('compose-error');
        const composeBtn = $('compose-btn');
        const radios     = form.querySelectorAll('input[name="recipient_type"]');
        const recipients = form.querySelector('textarea[name="recipients"]');
        const groupSel   = form.querySelector('select[name="contact_group_id"]');
        const bodyField  = form.querySelector('textarea[name="body"]');
        const previewBox = $('compose-preview');
        const rcptCount  = $('compose-rcpt-count');
        const schedToggle= $('compose-schedule-on');
        const schedField = $('compose-scheduled-at');
        const schedWrap  = $('compose-schedule-fields');
        const schedTz    = $('compose-timezone');
        // The blade pre-selects the user's workspace timezone. The
        // browser-detected tz is shown as the placeholder hint above
        // the picker so the user can override per-send if needed.

        // Multi-device picker (only rendered when the plan allows it).
        // Tom Select replaces the native <select multiple> with a
        // pill/tag input that doesn't need Ctrl-click.
        const devicesSelect = form.querySelector('select[name="sender_keys[]"]');
        const splitBar      = $('compose-split-bar');
        const splitSegments = $('compose-split-segments');
        const splitSummary  = $('compose-split-summary');
        let devicesTs = null;
        if (devicesSelect && !devicesSelect.__tsMounted) {
            devicesSelect.__tsMounted = true;
            devicesTs = new TomSelect(devicesSelect, {
                plugins: ['remove_button'],
                hideSelected: true,
                persist: false,
                maxItems: null,           // unlimited multi-select
                placeholder: devicesSelect.getAttribute('placeholder') || 'Choose devices…',
                render: {
                    option: (data, escape) => `
                        <div class="flex items-center gap-2 py-1.5">
                            <span class="w-1.5 h-1.5 rounded-full ${data.online === '1' ? 'bg-wa-green' : 'bg-ink-400'}"></span>
                            <span class="flex-1 text-[13px]">${escape(data.text)}</span>
                        </div>`,
                    item: (data, escape) => `<div class="text-[12px]">${escape(data.text)}</div>`,
                },
            });
            devicesTs.on('change', recomputeSplit);
        }

        function recomputeSplit() {
            if (!splitBar || !devicesTs) return;
            const ids = Array.isArray(devicesTs.getValue()) ? devicesTs.getValue() : (devicesTs.getValue() ? [devicesTs.getValue()] : []);
            const total = currentRecipientCount();
            if (ids.length === 0) {
                splitBar.classList.add('hidden');
                splitSegments.innerHTML = '';
                splitSummary.textContent = '';
                return;
            }
            splitBar.classList.remove('hidden');

            // Round-robin distribution mirrors the server logic:
            // recipient i lands on device ids[i % ids.length].
            const shares = ids.map((_, i) => 0);
            for (let i = 0; i < total; i++) shares[i % ids.length]++;

            // Render each segment with width proportional to its
            // share; when total is 0 (no recipients yet) fall back to
            // equal-width placeholder slots so the user can still see
            // which devices they've picked.
            const denom = total > 0 ? total : ids.length;
            splitSegments.innerHTML = ids.map((id, i) => {
                const share  = shares[i];
                const pct    = total > 0 ? (share / denom * 100) : (100 / ids.length);
                const colour = SPLIT_PALETTE[i % SPLIT_PALETTE.length];
                const label  = devicesTs.options[id]?.text || `Device ${id}`;
                const widthPct = Math.max(pct, 6);  // floor so tiny shares stay visible
                return `
                    <div class="flex items-center justify-center px-2 text-[11px] font-mono text-paper-0 truncate" title="${label}: ${share} of ${total}" style="width:${widthPct}%; background:${colour};">
                        ${total > 0 ? `${share}` : '—'}
                    </div>`;
            }).join('');
            splitSummary.textContent = total > 0
                ? `${total} recipient${total === 1 ? '' : 's'} → ${ids.length} device${ids.length === 1 ? '' : 's'}`
                : `Add recipients to see the split`;
        }

        function currentRecipientCount() {
            const mode = form.recipient_type.value;
            if (mode === 'manual') {
                return recipients.value.split(/[,\n]+/).map((s) => s.trim()).filter(Boolean).length;
            }
            const opt = groupSel.options[groupSel.selectedIndex];
            return parseInt(opt?.dataset?.count || '0', 10) || 0;
        }

        function open() {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            errorBox.classList.add('hidden');
            errorBox.textContent = '';
            form.reset();
            applyRecipientMode('manual');
            updatePreview();
            updateRecipientCount();
            schedField.classList.add('hidden');
            schedField.value = '';
            setTimeout(() => form.querySelector('input[name="title"]').focus(), 30);
        }
        function close() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        function applyRecipientMode(mode) {
            recipients.classList.toggle('hidden', mode !== 'manual');
            recipients.required = mode === 'manual';
            groupSel.classList.toggle('hidden', mode !== 'group');
            groupSel.required = mode === 'group';
            updateRecipientCount();
        }

        function updatePreview() {
            const text = bodyField.value.trim();
            if (!text) {
                previewBox.innerHTML = `<span class="text-ink-500">${t('Type a message — preview lands here.')}</span>`;
                return;
            }
            previewBox.innerHTML = `
                <div class="rounded-2xl border border-wa-deep/20 bg-wa-mint p-2.5">
                    <div class="text-[13px] leading-relaxed text-ink-800">${parseMessage(text)}</div>
                </div>`;
        }

        function updateRecipientCount() {
            const mode = form.recipient_type.value;
            let count = 0;
            if (mode === 'manual') {
                count = recipients.value
                    .split(/[,\n]+/)
                    .map((s) => s.trim())
                    .filter(Boolean).length;
            } else {
                const opt = groupSel.options[groupSel.selectedIndex];
                count = parseInt(opt?.dataset?.count || '0', 10) || 0;
            }
            rcptCount.textContent = count ? `${count} recipient${count === 1 ? '' : 's'}` : '';
        }

        composeBtn?.addEventListener('click', open);
        closeBtn.addEventListener('click', close);
        cancelBtn.addEventListener('click', close);
        modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
        radios.forEach((r) => r.addEventListener('change', () => { applyRecipientMode(r.value); recomputeSplit(); }));
        recipients.addEventListener('input', () => { updateRecipientCount(); recomputeSplit(); });
        groupSel.addEventListener('change', () => { updateRecipientCount(); recomputeSplit(); });
        bodyField.addEventListener('input', updatePreview);

        schedToggle.addEventListener('change', () => {
            schedWrap?.classList.toggle('hidden', !schedToggle.checked);
            // datetime-local input no longer needs its own visibility
            // toggle since it's wrapped — but keep value-clear behavior.
            if (!schedToggle.checked) {
                schedField.value = '';
            } else {
                schedField.focus();
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorBox.classList.add('hidden');

            const scheduleEnabled = schedToggle.checked && schedField.value;

            // Multi-engine sender picker. Either a single-select
            // (name="sender") or a Tom-Select-mounted multi-select
            // (name="sender_keys[]") depending on the workspace's plan. Both
            // submit composite "engine:id" keys (NOT bare ids), so the server
            // can stamp the chosen engine on the conversation. Read whichever
            // the form rendered — Tom Select keeps the underlying <select> in
            // sync, so selectedOptions is the source of truth either way.
            const multiSelect = form.querySelector('select[name="sender_keys[]"]');
            const senderKeys = multiSelect
                ? Array.from(multiSelect.selectedOptions).map((o) => o.value).filter(Boolean)
                : [];
            const singleSender = !multiSelect && form.sender ? (form.sender.value || null) : null;

            const payload = {
                title:            form.title.value.trim(),
                recipient_type:   form.recipient_type.value,
                recipients:       recipients.value.trim(),
                contact_group_id: groupSel.value || null,
                body:             bodyField.value.trim(),
                scheduled_at:     scheduleEnabled ? schedField.value : null,
                timezone:         scheduleEnabled ? (schedTz?.value || null) : null,
            };
            if (senderKeys.length) payload.sender_keys = senderKeys;
            else if (singleSender) payload.sender = singleSender;
            if (payload.recipient_type === 'manual') delete payload.contact_group_id;
            else                                     delete payload.recipients;
            if (!payload.scheduled_at) delete payload.scheduled_at;
            if (!payload.timezone)     delete payload.timezone;

            try {
                submitBtn.disabled = true;
                const { data, meta } = await api.create(payload);
                applyCounts(meta);
                close();
                showToast(`Queue created: ${data.conversation.title}`);
                activeQueueId = data.conversation.id;
                await loadQueues();
                await loadThread(activeQueueId);
            } catch (err) {
                errorBox.textContent = err.message || 'Could not create the queue.';
                errorBox.classList.remove('hidden');
            } finally {
                submitBtn.disabled = false;
            }
        });
    }

    /* ---- wire it up ---- */
    function wire() {
        document.querySelectorAll('.filter-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                activeFilter = btn.dataset.filter;
                document.querySelectorAll('.filter-btn').forEach((b) =>
                    b.setAttribute('aria-pressed', String(b === btn)));
                loadQueues();
            });
        });

        document.querySelectorAll('[data-template-category]').forEach((btn) => {
            btn.addEventListener('click', () => openTemplatePicker(btn.dataset.templateCategory));
        });

        const debouncedSearch = debounce(loadQueues, 200);
        searchInput.addEventListener('input', debouncedSearch);
        sortSelect.addEventListener('change', () => { activeSort = sortSelect.value; loadQueues(); });
        deviceSelect.addEventListener('change', () => { activeDeviceId = deviceSelect.value; loadQueues(); });

        composer.addEventListener('input', syncSendState);
        composer.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        });
        sendBtn.addEventListener('click', sendMessage);

        archiveBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleArchive();
        });

        // The 3-dot "More" button reuses the same status-aware popover
        // built for the queue cards — the data and actions are
        // identical, only the anchor changes.
        moreBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const convo = conversations.find((c) => c.id === activeQueueId);
            if (!convo) return showToast('Select a queue first');
            const isOpen = !queueMenu.classList.contains('hidden');
            if (isOpen) closeQueueMenu();
            else openQueueMenu(convo, moreBtn);
        });

        $('send-all').addEventListener('click', () => {
            const convo = conversations.find((c) => c.id === activeQueueId);
            showToast(convo ? `Queued ${convo.recipients_count || 0} messages in ${convo.title}` : 'Select a queue first');
        });
        $('send-selected').addEventListener('click', () => {
            const convo = conversations.find((c) => c.id === activeQueueId);
            // TODO: surface a per-recipient picker once the queue
            // detail panel exposes a selectable contact list.
            showToast(convo ? `Pick recipients in ${convo.title} — coming soon` : 'Select a queue first');
        });

        attachBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            togglePanel(attachMenu, attachBtn);
        });
        emojiBtn.addEventListener('click', async (event) => {
            event.stopPropagation();
            await mountEmojiPicker();
            togglePanel(emojiPanel, emojiBtn);
        });

        document.querySelectorAll('#attach-menu [data-attach]').forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.stopPropagation();
                closeComposerPanels();
                const kind = btn.dataset.attach;
                if (kind === 'Template') return openTemplatePicker('all');
                if (kind === 'Document' || kind === 'Photos & videos' || kind === 'Audio') {
                    mediaInput.accept = kind === 'Photos & videos' ? 'image/*,video/*'
                                       : kind === 'Audio'           ? 'audio/*'
                                       : 'application/pdf,.doc,.docx';
                    mediaInput.click();
                    return;
                }
                if (kind === 'Scheduled time')      return scheduleModal.open();
                if (kind === 'Send your location')  return locationModal.open();
                showToast(`${kind} — coming soon`);
            });
        });

        mediaInput.addEventListener('change', () => {
            pendingMedia = mediaInput.files?.[0] || null;
            if (pendingMedia) {
                showMediaPreview(pendingMedia);
                syncSendState();
            }
        });
        $('media-preview-remove').addEventListener('click', clearMediaPreview);

        document.addEventListener('click', (event) => {
            if (!attachMenu.contains(event.target) && !emojiPanel.contains(event.target)
                && !attachBtn.contains(event.target) && !emojiBtn.contains(event.target)) {
                closeComposerPanels();
            }
        });

        $('format-bold').addEventListener('click', () => {
            const start = composer.selectionStart;
            const end   = composer.selectionEnd;
            const sel   = composer.value.substring(start, end) || 'text';
            composer.value = composer.value.substring(0, start) + '*' + sel + '*' + composer.value.substring(end);
            composer.focus();
            composer.setSelectionRange(start + 1, start + 1 + sel.length);
            syncSendState();
        });

        document.querySelectorAll('#quick-bar [data-template]').forEach((btn) => {
            btn.addEventListener('click', () => {
                composer.value = btn.dataset.template;
                composer.focus();
                syncSendState();
            });
        });

        $('template-modal-close').addEventListener('click', closeTemplatePicker);
        templateModal.addEventListener('click', (event) => {
            if (event.target === templateModal) closeTemplatePicker();
        });
        templateSend.addEventListener('click', sendSelectedTemplate);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeComposerPanels();
                closeTemplatePicker();
            }
        });
    }

    /* ----------------------------------------------------------------
     * Live updates via polling.
     *
     * Every POLL_MS we silently re-fetch:
     *   - the active queue's messages (if one is open) — to catch
     *     status flips (pending → sent → delivered → read) and any
     *     new inbound messages
     *   - the queue list — to catch *other* queues firing in the
     *     background (a scheduled send finishing on a queue you
     *     don't have open)
     *
     * We pause polling when the tab is hidden to save resources, and
     * fire toasts on status transitions so the user can see
     * "scheduled → sent" without staring at the screen.
     * ---------------------------------------------------------------- */
    const POLL_MS = 5000;
    let pollTimer = null;

    async function pollTick() {
        if (document.hidden) return;
        try {
            // 1. Refresh queue list (status counts, sort by recency).
            const params = { filter: activeFilter, sort: activeSort, q: searchInput.value.trim() };
            if (activeDeviceId) params.device_id = activeDeviceId;
            const res = await api.list(params);

            // Diff the queue-level statuses to surface "queue X just
            // finished" as a toast even if user is looking at queue Y.
            const beforeById = new Map(conversations.map((c) => [c.id, c.status]));
            conversations = res.data || [];
            applyCounts(res.meta);
            renderQueues();
            for (const c of conversations) {
                const prev = beforeById.get(c.id);
                if (!prev || prev === c.status) continue;
                if (c.status === 'sent' && prev !== 'sent') {
                    showToast(`✓ "${c.title}" — sent`);
                } else if (c.status === 'failed' && prev !== 'failed') {
                    showToast(`⚠ "${c.title}" — failed`);
                } else if (c.status === 'partial' && prev !== 'partial') {
                    showToast(`⚠ "${c.title}" — partial`);
                }
            }

            // 2. Refresh active thread messages.
            if (activeQueueId) {
                const beforeMsgs = messagesById.get(activeQueueId) || [];
                const beforeStatus = new Map(beforeMsgs.map((m) => [m.id, m.status]));
                const { data } = await api.show(activeQueueId);
                const newMsgs = data.messages || [];
                messagesById.set(activeQueueId, newMsgs);

                // Toast on per-message transitions.
                let hadFlip = false;
                for (const m of newMsgs) {
                    const prev = beforeStatus.get(m.id);
                    if (prev && prev !== m.status) {
                        hadFlip = true;
                        if (m.status === 'sent' && prev === 'pending')        showToast('✓ Message sent');
                        else if (m.status === 'delivered' && prev !== 'read') showToast('✓✓ Delivered');
                        else if (m.status === 'read')                         showToast('✓✓ Read');
                        else if (m.status === 'failed')                       showToast('⚠ Send failed');
                    }
                }
                // New inbound message appeared (id not in beforeStatus).
                const newInbound = newMsgs.filter((m) =>
                    !beforeStatus.has(m.id) && m.direction === 'in'
                );
                if (newInbound.length) {
                    showToast(`💬 ${newInbound.length} new message${newInbound.length === 1 ? '' : 's'}`);
                    hadFlip = true;
                }
                if (hadFlip || newMsgs.length !== beforeMsgs.length) {
                    renderThread(data.conversation);
                }
            }
        } catch (e) {
            // Silent on poll errors — don't toast spam if Laravel is briefly down.
        }
    }

    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(pollTick, POLL_MS);
    }
    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stopPolling();
        else { pollTick(); startPolling(); }
    });
    // Refresh immediately when window regains focus (covers the case
    // where the tab WAS visible but unfocused — the tick interval is
    // still running but user just came back, so kick a fresh fetch).
    window.addEventListener('focus', () => { if (!document.hidden) pollTick(); });

    /* ---- boot ---- */
    applyCounts(state.counts);
    const scheduleModal = wireScheduleModal();
    const locationModal = wireLocationModal();
    wireDetailsModal();
    wireAiDrawer();
    wire();
    wireCompose();
    wireMessageMenu();
    loadQueues();
    startPolling();

    // Outside click + Escape close the gear popover.
    document.addEventListener('click', (e) => {
        if (e.target.closest('#queue-menu')) return;
        if (e.target.closest('[data-queue-gear]')) return;
        if (e.target.closest('#thread-more'))    return;
        closeQueueMenu();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeQueueMenu();
    });
}
