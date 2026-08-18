{{--
 Global inbox notification widget.

 Sits bottom-right on every authed page (except /team-inbox itself).
 Polls /team-inbox/api/unread-summary every 15s, pauses when the
 browser tab is hidden, exponential-backs-off on 429 / errors.

 States:
 - hidden → total = 0
 - pill → total > 0, popup closed
 - popup open → click pill to expand; shows up to 5 latest unread
 conversations with a "View Inbox" CTA

 Visibility-aware: setTimeout-driven so we never overlap requests
 even on slow networks; on visibilitychange→visible we fire one
 immediate fetch and reset the backoff.
--}}

<div id="ibx-bell" class="group/bell fixed bottom-5 right-5 z-[55] hidden" data-route="{{ url('/team-inbox') }}">
    {{-- Collapsed pill — visible whenever total > 0 --}}
    <button type="button" id="ibx-bell-btn"
        class="relative inline-flex items-center gap-2.5 pl-2.5 pr-4 py-2 rounded-full bg-wa-deep text-paper-0 shadow-[0_14px_32px_-10px_rgba(7,94,84,0.45)] hover:bg-wa-teal transition group touch-none active:cursor-grabbing">
        <span class="relative grid place-items-center w-8 h-8 rounded-full bg-paper-0/15">
            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7">
                <path
                    d="M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-3Z" />
            </svg>
            <span id="ibx-bell-count"
                class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-accent-coral text-paper-0 text-[10px] font-mono font-semibold grid place-items-center border-2 border-wa-deep">0</span>
        </span>
        <span class="text-[12.5px] font-semibold leading-tight">
            <span id="ibx-bell-label">{{ __('New messages') }}</span>
            <span
                class="block text-[10px] font-mono uppercase tracking-[0.14em] opacity-80">{{ __('tap to view') }}</span>
        </span>
    </button>

    {{-- Dismiss ✕ — appears on hover only, so the pill stays clean at rest.
         Hides it for the CURRENT unread set only: the next message that
         arrives brings it straight back. A permanent mute would be a
         footgun — an operator who closed it once would silently stop
         seeing every future customer message. --}}
    <button type="button" id="ibx-bell-dismiss"
        class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-paper-0 border border-paper-200 text-ink-500 hover:text-ink-900 hover:bg-paper-100 grid place-items-center shadow-sm transition-opacity opacity-0 pointer-events-none group-hover/bell:opacity-100 group-hover/bell:pointer-events-auto"
        title="{{ __('Dismiss until the next message') }}" aria-label="{{ __('Dismiss') }}">
        <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.2"
            stroke-linecap="round">
            <path d="M4 4l8 8M12 4l-8 8" />
        </svg>
    </button>

    {{-- Expanded popup — shown when user clicks the pill --}}
    <div id="ibx-bell-popup"
        class="hidden absolute bottom-[calc(100%+10px)] right-0 w-[340px] bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_24px_60px_-18px_rgba(11,31,28,0.35)] overflow-hidden">
        <div class="px-4 py-3 border-b border-paper-200 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-wa-green animate-pulse"></span>
            <span class="font-serif text-[16px] leading-tight">{{ __('New messages') }}</span>
            <span id="ibx-bell-total" class="ml-auto font-mono text-[11px] text-ink-500">—</span>
            <button id="ibx-bell-close"
                class="w-6 h-6 rounded-full hover:bg-paper-50 grid place-items-center text-ink-500"
                title="{{ __('Close (Esc)') }}">
                <svg viewBox="0 0 12 12" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M3 3l6 6M9 3l-6 6" />
                </svg>
            </button>
        </div>
        <div id="ibx-bell-list" class="max-h-[280px] overflow-y-auto divide-y divide-paper-100">
            <div class="px-4 py-6 text-center text-[12px] text-ink-500 italic">{{ __('Loading…') }}</div>
        </div>
        <a href="{{ url('/team-inbox') }}"
            class="block px-4 py-2.5 text-center text-[12px] font-semibold bg-wa-deep hover:bg-wa-teal text-paper-0 inline-flex items-center justify-center gap-2 w-full">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="M3 8h10M9 4l4 4-4 4" />
            </svg>
            View Inbox
        </a>
    </div>
</div>

{{-- Inline script — the layout renders @stack('scripts') BEFORE this
 component, so we can't @push here. Inlining keeps the JS attached
 to the component wherever the layout drops it in the DOM. --}}
<script>
    (function() {
        console.log('[ibx-bell] init');
        const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
        const bell = document.getElementById('ibx-bell');
        if (!bell) {
            console.warn('[ibx-bell] no #ibx-bell element — bailing');
            return;
        }
        const btn = document.getElementById('ibx-bell-btn');
        const popup = document.getElementById('ibx-bell-popup');
        const closeBtn = document.getElementById('ibx-bell-close');
        const countEl = document.getElementById('ibx-bell-count');
        const labelEl = document.getElementById('ibx-bell-label');
        const totalEl = document.getElementById('ibx-bell-total');
        const listEl = document.getElementById('ibx-bell-list');
        const dismissBtn = document.getElementById('ibx-bell-dismiss');
        const route = bell.dataset.route || '/team-inbox';

        // ── Position: drag to move, remembered per browser ──────────────
        //
        // The widget ships anchored bottom-right. The moment it is dragged we
        // switch it to left/top coordinates — mixing the two anchor pairs makes
        // the box fight itself when the viewport resizes.
        const POS_KEY = 'ibx-bell-pos';
        let moved = false;

        function applyPos(x, y) {
            bell.classList.remove('bottom-5', 'right-5');
            bell.style.left = x + 'px';
            bell.style.top = y + 'px';
            bell.style.right = 'auto';
            bell.style.bottom = 'auto';
            moved = true;
        }

        function clampPos(x, y) {
            const r = bell.getBoundingClientRect();
            // Fall back to the pill's nominal size while the bell is still
            // hidden (a hidden element measures 0 and would clamp to the corner).
            const w = r.width || 210,
                h = r.height || 52;
            const maxX = Math.max(8, window.innerWidth - w - 8);
            const maxY = Math.max(8, window.innerHeight - h - 8);
            return [Math.min(Math.max(8, x), maxX), Math.min(Math.max(8, y), maxY)];
        }

        // The popup hangs off whichever corner keeps it on screen. Without this
        // the panel would render above the top edge as soon as the pill is
        // dragged into the upper half of the window.
        function positionPopup() {
            if (!popup) return;
            const r = bell.getBoundingClientRect();
            const dropDown = r.top < window.innerHeight / 2;
            popup.classList.toggle('bottom-[calc(100%+10px)]', !dropDown);
            popup.classList.toggle('top-[calc(100%+10px)]', dropDown);
            const onLeft = r.left < window.innerWidth / 2;
            popup.classList.toggle('right-0', !onLeft);
            popup.classList.toggle('left-0', onLeft);
        }

        // The pill is BOTH the open-inbox button and the drag grip. A press
        // only becomes a drag once the pointer travels past a few pixels —
        // below that it is still a click, so a normal tap opens the popup and
        // a shaky hand doesn't nudge the widget across the screen.
        const DRAG_SLOP = 4;
        let armed = false,
            dragging = false,
            grabX = 0,
            grabY = 0,
            startX = 0,
            startY = 0,
            suppressClick = false;

        btn?.addEventListener('pointerdown', (e) => {
            if (e.pointerType === 'mouse' && e.button !== 0) return; // left button only
            const r = bell.getBoundingClientRect();
            grabX = e.clientX - r.left;
            grabY = e.clientY - r.top;
            startX = e.clientX;
            startY = e.clientY;
            armed = true;
            dragging = false;
            // Capture so the drag survives the pointer leaving the small pill.
            try { btn.setPointerCapture(e.pointerId); } catch (_) {}
        });

        btn?.addEventListener('pointermove', (e) => {
            if (!armed) return;
            if (!dragging) {
                if (Math.abs(e.clientX - startX) < DRAG_SLOP && Math.abs(e.clientY - startY) < DRAG_SLOP) return;
                dragging = true;
                popup?.classList.add('hidden'); // don't drag an open panel around
            }
            e.preventDefault();
            const [x, y] = clampPos(e.clientX - grabX, e.clientY - grabY);
            applyPos(x, y);
        });

        function endDrag(e) {
            if (!armed) return;
            armed = false;
            try { btn.releasePointerCapture(e.pointerId); } catch (_) {}
            if (!dragging) return; // never moved — let the click handler open the popup
            dragging = false;
            // The browser still fires a click after this pointerup. Swallow it,
            // otherwise every drop would also toggle the popup open.
            suppressClick = true;
            const r = bell.getBoundingClientRect();
            try { localStorage.setItem(POS_KEY, JSON.stringify({ x: r.left, y: r.top })); } catch (_) {}
            positionPopup();
        }
        btn?.addEventListener('pointerup', endDrag);
        btn?.addEventListener('pointercancel', endDrag);

        // Restore the saved spot, and keep it inside the window when the
        // browser is resized or the device rotated.
        try {
            const saved = JSON.parse(localStorage.getItem(POS_KEY) || 'null');
            if (saved && typeof saved.x === 'number' && typeof saved.y === 'number') {
                applyPos(saved.x, saved.y);
            }
        } catch (_) {}
        window.addEventListener('resize', () => {
            if (!moved) return;
            const r = bell.getBoundingClientRect();
            const [x, y] = clampPos(r.left, r.top);
            applyPos(x, y);
            positionPopup();
        });

        // ── Dismiss until the next message ──────────────────────────────
        //
        // Closing the pill hides it for the CURRENT unread set only. We stamp a
        // watermark (newest message timestamp + the count behind it); the poll
        // brings the pill straight back the moment either moves past it. A
        // permanent mute would be a footgun — an operator who closed it once
        // would silently stop seeing every future customer message.
        const DISMISS_KEY = 'ibx-bell-dismissed';
        let dismissed = null; // {mark, total} or null

        try { dismissed = JSON.parse(sessionStorage.getItem(DISMISS_KEY) || 'null'); } catch (_) {}

        function newestMark(payload) {
            let m = 0;
            (payload?.items || []).forEach(c => {
                const t = Date.parse(c.last_message_at || '');
                if (!isNaN(t) && t > m) m = t;
            });
            return m;
        }

        function clearDismiss() {
            dismissed = null;
            try { sessionStorage.removeItem(DISMISS_KEY); } catch (_) {}
        }

        dismissBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dismissed = { mark: lastMark, total: lastTotal };
            try { sessionStorage.setItem(DISMISS_KEY, JSON.stringify(dismissed)); } catch (_) {}
            popup?.classList.add('hidden');
            bell.classList.add('hidden');
        });

        const BASE_INTERVAL = 15000; // 15s default poll
        const MAX_INTERVAL = 300000; // 5m cap on backoff
        let currentInterval = BASE_INTERVAL;
        let timer = null;
        let lastTotal = 0;
        let lastMark = 0; // newest unread timestamp seen — the dismiss watermark
        let beepArmed = false; // becomes true after the first poll so we don't beep on page load

        // Short "new message" beep via WebAudio (no audio file needed). Muted
        // until the user has interacted with the page (browser autoplay policy).
        let audioCtx = null;
        function playBeep() {
            try {
                audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
                if (audioCtx.state === 'suspended') audioCtx.resume();
                const o = audioCtx.createOscillator();
                const g = audioCtx.createGain();
                o.type = 'sine';
                o.frequency.value = 880;            // a clear, soft ping
                g.gain.setValueAtTime(0.0001, audioCtx.currentTime);
                g.gain.exponentialRampToValueAtTime(0.18, audioCtx.currentTime + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.35);
                o.connect(g); g.connect(audioCtx.destination);
                o.start(); o.stop(audioCtx.currentTime + 0.36);
            } catch (e) { /* autoplay blocked or no audio — silent */ }
        }
        // Prime the audio context on the first user gesture so the beep is allowed.
        ['click', 'keydown'].forEach(ev => window.addEventListener(ev, function prime() {
            try { (audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)()).resume(); } catch (e) {}
            window.removeEventListener(ev, prime);
        }, { once: true }));

        function fmtTime(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            if (isNaN(d.getTime())) return '';
            const now = new Date();
            if (d.toDateString() === now.toDateString()) {
                return d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
            }
            return d.toLocaleDateString(undefined, {
                month: 'short',
                day: 'numeric'
            });
        }
        const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        } [c]));

        function renderPopup(payload) {
            const items = payload.items || [];
            if (!items.length) {
                listEl.innerHTML =
                    '<div class="px-4 py-6 text-center text-[12px] text-ink-500 italic">No unread conversations.</div>';
                return;
            }
            listEl.innerHTML = items.map(c => `
 <a href="${route}#c=${c.id}" class="flex items-start gap-2.5 px-4 py-2.5 hover:bg-paper-50 transition">
 <span class="w-8 h-8 rounded-full bg-wa-deep text-paper-0 text-[10.5px] font-semibold grid place-items-center shrink-0">${esc((c.title || '?').trim().slice(0,2).toUpperCase())}</span>
 <div class="flex-1 min-w-0">
 <div class="flex items-center gap-2">
 <span class="font-semibold text-[12.5px] text-ink-900 truncate">${esc(c.title || '—')}</span>
 <span class="ml-auto font-mono text-[10px] text-ink-500 shrink-0">${esc(fmtTime(c.last_message_at))}</span>
 </div>
 <div class="text-[11.5px] text-ink-600 truncate">${esc(c.preview || '')}</div>
 </div>
 <span class="shrink-0 text-[10px] font-mono bg-wa-deep text-paper-0 rounded-full px-1.5 py-0.5">${c.unread_count}</span>
 </a>
 `).join('');
        }

        function updateBell(total) {
            if (total > 0) {
                countEl.textContent = total > 99 ? '99+' : String(total);
                labelEl.textContent = total === 1 ? '1 new message' : (total + ' new messages');
                totalEl.textContent = total + ' unread';
                if (dismissed) return; // stays closed until something newer arrives
                bell.classList.remove('hidden');
                positionPopup();
            } else {
                bell.classList.add('hidden');
                if (popup) popup.classList.add('hidden');
                // Everything is read — the watermark has nothing left to
                // suppress, so drop it rather than carry it into the next batch.
                if (dismissed) clearDismiss();
            }
        }

        async function poll() {
            if (document.hidden) return scheduleNext(); // skip silently; visibilitychange re-fires
            try {
                const res = await fetch('/team-inbox/api/unread-summary', {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf
                    },
                });
                if (res.status === 429) {
                    currentInterval = Math.min(MAX_INTERVAL, currentInterval * 2);
                    return scheduleNext();
                }
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                currentInterval = BASE_INTERVAL; // success → reset backoff
                const total = (data && typeof data.total === 'number') ? data.total : 0;
                const mark = newestMark(data);
                console.log('[ibx-bell] poll → total=' + total);
                // Something arrived after the operator closed the pill —
                // either a fresher message or a bigger backlog. Bring it back.
                if (dismissed && (mark > dismissed.mark || total > dismissed.total)) clearDismiss();
                updateBell(total);
                if (popup && !popup.classList.contains('hidden')) renderPopup(data);
                // Browser tab title flash on a NEW message (not just non-zero)
                // A new message arrived (count went up) AND we've polled before
                // → flash the tab title AND beep, like WhatsApp. Beep on ANY
                // increase (including 0→1), just not on the very first poll.
                if (beepArmed && total > lastTotal) {
                    flashTitle(total);
                    playBeep();
                }
                beepArmed = true;
                lastTotal = total;
                lastMark = mark;
            } catch (e) {
                currentInterval = Math.min(MAX_INTERVAL, currentInterval * 2);
            } finally {
                scheduleNext();
            }
        }

        function scheduleNext() {
            clearTimeout(timer);
            timer = setTimeout(poll, currentInterval);
        }

        // Flash browser tab title on new inbound (revert when tab is focused).
        const originalTitle = document.title;
        let flashTimer = null;

        function flashTitle(n) {
            clearInterval(flashTimer);
            let toggle = false;
            flashTimer = setInterval(() => {
                document.title = toggle ? originalTitle : '(' + n + ') ' + originalTitle;
                toggle = !toggle;
            }, 1200);
        }
        window.addEventListener('focus', () => {
            clearInterval(flashTimer);
            document.title = originalTitle;
        });

        // Toggle popup on bell click
        btn?.addEventListener('click', () => {
            if (suppressClick) { // this "click" was the end of a drag
                suppressClick = false;
                return;
            }
            if (!popup) return;
            popup.classList.toggle('hidden');
            if (!popup.classList.contains('hidden')) {
                positionPopup(); // drop up or down depending on where the pill sits
                // Force-render the latest data so the popup isn't stale.
                poll();
            }
        });
        closeBtn?.addEventListener('click', () => popup?.classList.add('hidden'));
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') popup?.classList.add('hidden');
        });
        document.addEventListener('click', (e) => {
            if (popup && !popup.classList.contains('hidden')) {
                if (!bell.contains(e.target)) popup.classList.add('hidden');
            }
        });

        // Visibility-aware polling — when tab hidden we cancel, when visible
        // we fetch immediately and reset the backoff so the user sees the
        // freshest count the moment they switch back.
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                currentInterval = BASE_INTERVAL;
                clearTimeout(timer);
                poll();
            }
        });

        // Kick it off — first request after 500ms so initial page paint isn't
        // competing with the bell's network call.
        setTimeout(poll, 500);
    })();
</script>
