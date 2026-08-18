/*
 * Duplicate-chat cleanup for /team-inbox.
 *
 * Scan-then-merge, never merge-on-click: opening the modal only READS
 * (/team-inbox/api/duplicates) and renders what it found. The merge POST is a
 * second, deliberate action, because it rewrites which thread every message
 * belongs to and there is no undo button for it.
 *
 * Mounted from user-team-inbox-index.js so it shares that page's lifecycle.
 */

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

export function mountMergeDuplicates({ toast, t, onMerged } = {}) {
    const btn = document.getElementById('merge-dupes-btn');
    const modal = document.getElementById('merge-dupes-modal');
    if (!btn || !modal) return;

    const body = modal.querySelector('#dupes-body');
    const mergeBtn = modal.querySelector('#dupes-merge-btn');
    const say = typeof t === 'function' ? t : ((s) => s);
    const notify = typeof toast === 'function' ? toast : (() => {});
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';

    let groups = [];

    const open = () => modal.classList.remove('hidden');
    const close = () => modal.classList.add('hidden');

    /*
     * Single owner of the merge button's label + enabled state.
     *
     * These used to be set independently in five places, and the
     * "nothing found" branch of render() set `disabled` but not the label.
     * So the normal happy path — merge succeeds, rescan finds nothing left —
     * left the button reading "Merging…" forever, which looks like a hung
     * request when the merge had in fact completed. Every path now ends in
     * an explicit call here.
     */
    const idleLabel = () => say('Merge all');
    const setMergeBtn = (label, disabled) => {
        mergeBtn.textContent = label;
        mergeBtn.disabled = disabled;
    };

    const render = () => {
        if (!groups.length) {
            setMergeBtn(idleLabel(), true);
            body.innerHTML = `<div class="text-[12px] text-ink-500 py-6 text-center">
                ${esc(say('No duplicate chats found — your inbox is clean.'))}
            </div>`;
            return;
        }

        const removable = groups.reduce((n, g) => n + (g.thread_count - 1), 0);

        setMergeBtn(`${idleLabel()} (${removable})`, false);

        body.innerHTML = `
            <div class="text-[12px] text-ink-700 mb-2">
                ${esc(say('Found'))} <strong>${groups.length}</strong> ${esc(say('customer(s) with more than one thread'))} —
                <strong>${removable}</strong> ${esc(say('extra thread(s) will be folded in.'))}
            </div>
            <div class="max-h-[240px] overflow-y-auto rounded-xl border border-paper-200 divide-y divide-paper-200">
                ${groups.map((g) => `
                    <div class="px-3 py-2 flex items-center justify-between gap-3 text-[12px]">
                        <div class="min-w-0">
                            <div class="truncate text-ink-900">${esc(g.title || g.phone || say('Unknown contact'))}</div>
                            <div class="font-mono text-[10.5px] text-ink-500 truncate">
                                ${esc(g.phone)} · ${esc(g.engine_label)}
                            </div>
                        </div>
                        <div class="shrink-0 text-right text-[11px] text-ink-500 font-mono">
                            ${g.thread_count} ${esc(say('threads'))}<br>
                            ${g.message_count} ${esc(say('messages'))}
                        </div>
                    </div>
                `).join('')}
            </div>`;
    };

    const scan = async () => {
        setMergeBtn(idleLabel(), true);
        body.innerHTML = `<div class="text-[12px] text-ink-500 py-6 text-center">${esc(say('Scanning…'))}</div>`;

        try {
            const res = await fetch('/team-inbox/api/duplicates', {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const data = await res.json();
            groups = Array.isArray(data.groups) ? data.groups : [];
            render();
        } catch (e) {
            groups = [];
            setMergeBtn(idleLabel(), true);
            body.innerHTML = `<div class="text-[12px] text-red-600 py-6 text-center">
                ${esc(say('Scan failed'))}: ${esc(e.message)}
            </div>`;
        }
    };

    const merge = async () => {
        // Send the exact keys we showed. If new traffic forked another thread
        // between the scan and this click, it is NOT swept up silently — it
        // shows on the next scan instead.
        const keys = groups.map((g) => g.key);
        if (!keys.length) return;

        setMergeBtn(say('Merging…'), true);

        try {
            const res = await fetch('/team-inbox/api/duplicates/merge', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ keys }),
            });

            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.error || 'HTTP ' + res.status);

            const s = data.summary || {};
            notify(
                `${say('Merged')} ${s.groups || 0} ${say('chat(s)')} · ${s.threads_removed || 0} ${say('duplicate thread(s) removed')}`,
                s.failed ? 'error' : 'success',
            );

            if (s.failed) {
                notify(`${s.failed} ${say('group(s) could not be merged — check the logs.')}`, 'error');
            }

            if (typeof onMerged === 'function') onMerged();
            await scan();
        } catch (e) {
            notify(say('Merge failed') + ': ' + e.message, 'error');
            // Re-enabled so the operator can retry — the scan results are
            // still on screen and still valid.
            setMergeBtn(idleLabel(), false);
        }
    };

    btn.addEventListener('click', () => { open(); scan(); });
    mergeBtn.addEventListener('click', merge);
    modal.querySelectorAll('[data-close-dupes]').forEach((el) => el.addEventListener('click', close));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });
}

export default mountMergeDuplicates;
