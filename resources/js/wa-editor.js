/*
 * WhatsApp-style rich-text editor — auto-wires any
 * `[data-wa-editor]` node on the page (rendered by the
 * <x-wa-editor /> Blade component).
 *
 * Toolbar commands wrap the textarea selection with the
 * markdown WhatsApp itself renders:
 *   bold   → *text*
 *   italic → _text_
 *   strike → ~text~
 *   code   → `text`
 *
 * The emoji button lazy-loads emoji-picker-element on first
 * use so the package's ~600 KB bundle never lands on a page
 * that doesn't open it.
 */

let pickerLoaded = false;
async function loadPicker() {
    if (pickerLoaded) return;
    pickerLoaded = true;
    await import('emoji-picker-element');
}

/**
 * Wrap the current selection with `prefix` and `suffix`. If
 * nothing is selected, inserts the literal prefix/suffix so
 * the user can type between them. Fires an `input` event so
 * any listeners (live preview, send-button enable, etc.) see
 * the change.
 */
function wrapSelection(textarea, prefix, suffix) {
    const start = textarea.selectionStart;
    const end   = textarea.selectionEnd;
    const sel   = textarea.value.substring(start, end);
    const inner = sel || '';
    const before = textarea.value.substring(0, start);
    const after  = textarea.value.substring(end);

    textarea.value = before + prefix + inner + suffix + after;
    textarea.focus();

    const cursorStart = start + prefix.length;
    const cursorEnd   = cursorStart + inner.length;
    textarea.setSelectionRange(cursorStart, cursorEnd || cursorStart);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function insertAtCursor(textarea, text) {
    const start = textarea.selectionStart;
    const end   = textarea.selectionEnd;
    textarea.value = textarea.value.substring(0, start) + text + textarea.value.substring(end);
    const next = start + text.length;
    textarea.focus();
    textarea.setSelectionRange(next, next);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function initEditor(root) {
    if (root.__waEditorInitialized) return;
    root.__waEditorInitialized = true;

    const textarea = root.querySelector('.wa-editor-textarea');
    const panel    = root.querySelector('.wa-editor-emoji');
    // Emoji panel can be absent if the consumer turned :show-emoji="false"
    // off. Don't bail in that case — the rest of the editor still wires up.
    if (!textarea) return;

    let pickerMounted = false;

    async function ensurePicker() {
        if (pickerMounted || !panel) return;
        pickerMounted = true;
        await loadPicker();
        const picker = document.createElement('emoji-picker');
        picker.classList.add('chat-emoji-picker', 'light');
        picker.addEventListener('emoji-click', (event) => {
            const native = event.detail?.unicode || event.detail?.emoji?.unicode;
            if (native) insertAtCursor(textarea, native);
        });
        panel.appendChild(picker);
    }

    root.querySelectorAll('[data-wa-cmd]').forEach((btn) => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            switch (btn.dataset.waCmd) {
                case 'bold':   return wrapSelection(textarea, '*', '*');
                case 'italic': return wrapSelection(textarea, '_', '_');
                case 'strike': return wrapSelection(textarea, '~', '~');
                case 'code':   return wrapSelection(textarea, '`', '`');
                case 'emoji':
                    if (!panel) return;
                    await ensurePicker();
                    panel.classList.toggle('hidden');
                    return;
                case 'ai-review':
                    return runAiReview(root, textarea, { manual: true });
            }
        });
    });

    // Keyboard shortcuts inside the textarea — Cmd/Ctrl + B/I/U.
    textarea.addEventListener('keydown', (e) => {
        const meta = e.metaKey || e.ctrlKey;
        if (!meta) return;
        const map = { b: '*', i: '_', u: '~' };
        const wrap = map[e.key.toLowerCase()];
        if (!wrap) return;
        e.preventDefault();
        wrapSelection(textarea, wrap, wrap);
    });

    // Close emoji panel on outside click (only if it exists).
    if (panel) {
        document.addEventListener('click', (e) => {
            if (!root.contains(e.target)) panel.classList.add('hidden');
        });
    }

    // ──────────────────────────────────────────────────────────────
    // AI review — debounced auto-trigger when the user's
    // "auto_ai_summarize_enabled" toggle is on. Manual trigger via
    // the toolbar's ai-review button works either way.
    // ──────────────────────────────────────────────────────────────
    if (root.dataset.aiEnabled === '1' && root.dataset.aiAuto === '1') {
        let timer = null;
        textarea.addEventListener('input', () => {
            const text = (textarea.value || '').trim();
            if (timer) clearTimeout(timer);
            // Quiet zone — don't fire while the user is still
            // typing. Min length filters out short keystrokes; the
            // review is pointless before there's anything to review.
            if (text.length < 12) {
                hideAiPanel(root);
                return;
            }
            timer = setTimeout(() => {
                runAiReview(root, textarea, { manual: false });
            }, 1400);
        });
    }

    // Wire the Append + Close buttons on the review panel.
    const aiPanel = findAiPanel(root, textarea);
    if (aiPanel) {
        aiPanel.querySelector('[data-ai-append]')?.addEventListener('click', () => {
            const best = aiPanel.dataset.aiBest || '';
            if (!best) return;
            textarea.value = best;
            textarea.focus();
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            // Collapse the panel after a successful paste — the user
            // can re-run by typing again or hitting the sparkle.
            hideAiPanel(root);
        });
        aiPanel.querySelector('[data-ai-close]')?.addEventListener('click', () => hideAiPanel(root));
    }
}

// AI panels can live either inside the editor root (most consumers)
// or as a sibling after it. Look both places before giving up.
function findAiPanel(root, textarea) {
    const inside = root.querySelector('[data-ai-panel]');
    if (inside) return inside;
    const targetId = textarea?.id;
    if (!targetId) return null;
    return document.querySelector(`[data-ai-panel][data-target="${targetId}"]`);
}

function hideAiPanel(root) {
    const tex = root.querySelector('.wa-editor-textarea');
    const p = findAiPanel(root, tex);
    if (p) p.classList.add('hidden');
}

let aiReviewInflight = new WeakSet();

async function runAiReview(root, textarea, { manual }) {
    const text = (textarea.value || '').trim();
    if (text.length < 4) return;
    if (aiReviewInflight.has(textarea)) return;
    aiReviewInflight.add(textarea);

    const panel = findAiPanel(root, textarea);
    if (panel) {
        panel.classList.remove('hidden');
        const status = panel.querySelector('[data-ai-status]');
        if (status) status.textContent = manual ? 'Reviewing…' : 'Auto-reviewing…';
    }

    try {
        const csrfEl = document.querySelector('meta[name="csrf-token"]')
            || document.querySelector('input[name="_token"]');
        const csrf = csrfEl ? (csrfEl.getAttribute('content') || csrfEl.value || '') : '';
        const res = await fetch('/ai/review-text', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ text }),
        });
        const json = await res.json();
        if (!res.ok || !json?.ok) {
            renderAiError(panel, json?.message || `Review failed (${res.status}).`);
            return;
        }
        renderAiReview(panel, json);
    } catch (err) {
        renderAiError(panel, 'Network error talking to the AI service.');
    } finally {
        aiReviewInflight.delete(textarea);
    }
}

function renderAiError(panel, msg) {
    if (!panel) return;
    const status = panel.querySelector('[data-ai-status]');
    if (status) {
        status.textContent = msg;
        status.classList.add('text-accent-coral');
        setTimeout(() => status.classList.remove('text-accent-coral'), 4000);
    }
}

function renderAiReview(panel, json) {
    if (!panel) return;
    const analysis = json.analysis || {};
    const good = Array.isArray(analysis.good) ? analysis.good : [];
    const bad  = Array.isArray(analysis.bad)  ? analysis.bad  : [];
    const score = (analysis.score != null) ? String(analysis.score) : '';

    const status = panel.querySelector('[data-ai-status]');
    if (status) { status.textContent = ''; status.classList.remove('text-accent-coral'); }

    const scoreChip = panel.querySelector('[data-ai-score]');
    if (scoreChip) {
        if (score) {
            scoreChip.classList.remove('hidden');
            scoreChip.textContent = `Score ${score}/100`;
        } else {
            scoreChip.classList.add('hidden');
        }
    }

    const goodList = panel.querySelector('[data-ai-good]');
    const badList  = panel.querySelector('[data-ai-bad]');
    if (goodList) {
        goodList.innerHTML = '';
        if (good.length === 0) {
            goodList.innerHTML = '<li class="text-ink-500 italic">No clear strengths yet — refine the hook.</li>';
        } else {
            good.forEach((g) => {
                const li = document.createElement('li');
                li.className = 'flex items-start gap-1.5';
                li.innerHTML = `<svg viewBox="0 0 16 16" class="w-3 h-3 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8l5 5 7-9"/></svg><span></span>`;
                li.querySelector('span').textContent = g;
                goodList.appendChild(li);
            });
        }
    }
    if (badList) {
        badList.innerHTML = '';
        if (bad.length === 0) {
            badList.innerHTML = '<li class="text-ink-500 italic">Nothing major flagged — good job.</li>';
        } else {
            bad.forEach((b) => {
                const li = document.createElement('li');
                li.className = 'flex items-start gap-1.5';
                li.innerHTML = `<svg viewBox="0 0 16 16" class="w-3 h-3 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v6M8 12v.01"/><circle cx="8" cy="8" r="6.5"/></svg><span></span>`;
                li.querySelector('span').textContent = b;
                badList.appendChild(li);
            });
        }
    }

    const bestWrap = panel.querySelector('[data-ai-best-wrap]');
    const bestEl   = panel.querySelector('[data-ai-best]');
    const best     = (typeof json.best_version === 'string') ? json.best_version.trim() : '';
    if (bestWrap && bestEl) {
        if (best) {
            bestWrap.classList.remove('hidden');
            bestEl.textContent = best;
            panel.dataset.aiBest = best;
        } else {
            bestWrap.classList.add('hidden');
            bestEl.textContent = '';
            panel.dataset.aiBest = '';
        }
    }
}

/**
 * Initialise every editor in `scope`. Idempotent — a node already
 * wired keeps its listener and is skipped on re-runs.
 */
export function initWaEditors(scope = document) {
    scope.querySelectorAll('[data-wa-editor]').forEach(initEditor);
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initWaEditors());
    } else {
        initWaEditors();
    }
}
