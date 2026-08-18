/*
 * /devices page — AJAX glue + dynamic country picker.
 *
 * Filter rail (status/region) and live search fire fetch() against
 * /devices?...&partial=1 and swap just the table body. Toggle /
 * delete / connectivity-check use the global toaster for feedback.
 *
 * The Add-device modal swaps the static country <select> for an
 * intl-tel-input field — full ITU country list with flags + search,
 * not the 8 hardcoded options. We push the resolved dial code +
 * national number into hidden fields so the controller's existing
 * (country_code, phone_number) signature still works.
 */

// v28+ ships utilities (validation + E.164 formatter) bundled
// already, and the CSS is exposed via the package's `./styles`
// export — no CDN fallback or separate utils script needed.
import intlTelInput from 'intl-tel-input/intlTelInputWithUtils';
import 'intl-tel-input/styles';
// Local QR renderer — no external service. Node returns the raw
// WhatsApp pairing string; we draw it to a data URL in-browser.
import QRCode from 'qrcode';
// /devices is the unified provider-connection hub. The shared connect
// JS (used by /connect?platform=wa-store too) is loaded here so the
// WABA Embedded Signup, Baileys QR, and Twilio form all work on this
// page without duplicating logic.
import initProviderConnect from './user-connect-wa-store.js';

/**
 * Render a raw QR payload (or pre-built data URL) to a data: URL we
 * can drop into <img src>. Local — no external API. Returns a Promise.
 */
async function renderQrDataUrl(raw, size = 320) {
    const s = String(raw || '').trim();
    if (!s) return null;
    if (s.startsWith('data:image')) return s;
    if (s.startsWith('http://') || s.startsWith('https://')) return s;
    if (s.startsWith('<svg')) {
        return URL.createObjectURL(new Blob([s], { type: 'image/svg+xml' }));
    }
    try {
        return await QRCode.toDataURL(s, { width: size, margin: 2, errorCorrectionLevel: 'M' });
    } catch (e) {
        return null;
    }
}

const $ = (id) => document.getElementById(id);

function getCsrf() {
    return document.querySelector('meta[name=csrf-token]')?.content || '';
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function readState() {
    const el = document.querySelector('[data-devices-state]');
    return {
        status: el?.dataset.devicesStatus || 'all',
        region: el?.dataset.devicesRegion || 'all',
        q:      el?.dataset.devicesSearch || '',
        page:   el?.dataset.devicesPage   || '1',
    };
}

function writeState(s) {
    const el = document.querySelector('[data-devices-state]');
    if (!el) return;
    el.dataset.devicesStatus = s.status;
    el.dataset.devicesRegion = s.region;
    el.dataset.devicesSearch = s.q;
    el.dataset.devicesPage   = s.page || '1';
}

function paintActive(state) {
    document.querySelectorAll('[data-devices-filter]').forEach((el) => {
        const k = el.dataset.devicesFilter;
        const v = el.dataset.devicesValue;
        const active = state[k] === v;
        if (k === 'status') {
            el.classList.toggle('bg-wa-deep',     active);
            el.classList.toggle('text-paper-0',   active);
            el.classList.toggle('font-semibold',  active);
            el.classList.toggle('text-ink-700',   !active);
            el.classList.toggle('hover:bg-paper-50', !active);
        } else {
            el.classList.toggle('bg-paper-50',    active);
            el.classList.toggle('text-ink-900',   active);
            el.classList.toggle('font-medium',    active);
            el.classList.toggle('text-ink-700',   !active);
            el.classList.toggle('hover:bg-paper-50', !active);
        }
    });
}

function applyCounts(counts, totals) {
    Object.entries(counts || {}).forEach(([k, v]) => {
        const el = document.querySelector(`[data-status-count="${k}"]`);
        if (el) el.textContent = v;
    });
    if (totals) {
        document.querySelectorAll('[data-totals="connected"]').forEach((el) => el.textContent = totals.connected);
        document.querySelectorAll('[data-totals="total"]').forEach((el) => el.textContent = totals.total);
        document.querySelectorAll('[data-totals="sent_24h"]').forEach((el) => el.textContent = Number(totals.sent_24h).toLocaleString());
        document.querySelectorAll('[data-totals="failed_24h"]').forEach((el) => el.textContent = Number(totals.failed_24h).toLocaleString());
    }
}

// Base path the page was actually served under — e.g. "/public/devices" or
// "/devices". Captured once at load so the filter pushState + AJAX calls stay
// on whatever prefix the app is mounted at, instead of forcing an absolute
// "/devices" that would bounce the user off a "/public" install.
const DEVICES_PATH = (window.location.pathname.replace(/\/+$/, '') || '/devices');

async function fetchPartial(state) {
    const params = new URLSearchParams();
    if (state.status !== 'all') params.append('status', state.status);
    if (state.region !== 'all') params.append('region', state.region);
    if (state.q) params.append('q', state.q);
    if (Number(state.page || 1) > 1) params.append('page', state.page);
    const visible = DEVICES_PATH + (params.toString() ? '?' + params.toString() : '');
    history.pushState({}, '', visible);

    params.append('partial', '1');
    const list = $('devices-list');
    if (list) list.classList.add('opacity-60');
    try {
        const res = await fetch(DEVICES_PATH + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (list) list.innerHTML = data.cards;
        applyCounts(data.counts, data.totals);
        const totalCount = Number(data.total ?? 0);
        $('devices-results-footer')?.classList.toggle('hidden', totalCount <= 0);
        const shown = document.querySelector('[data-devices-shown]');
        if (shown) shown.textContent = data.shown;
        const total = document.querySelector('[data-devices-total]');
        if (total) total.textContent = totalCount.toLocaleString();
        const pager = $('devices-pagination');
        if (pager) pager.innerHTML = data.pagination || '';
        if (data.page) {
            state.page = String(data.page);
            writeState(state);
        }
        wireRowActions();
        wirePagination();
    } catch (e) {
        window.WaToaster?.error?.('Could not refresh: ' + e.message);
    } finally {
        if (list) list.classList.remove('opacity-60');
    }
}

const debouncedFetch = debounce((s) => fetchPartial(s), 220);

async function toggleDevice(id) {
    try {
        const res = await fetch(`${DEVICES_PATH}/${id}/toggle`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        window.WaToaster?.success?.(`Device ${data.data.active ? 'connected' : 'disconnected'}`);
        await fetchPartial(readState());
    } catch (e) {
        window.WaToaster?.error?.('Toggle failed: ' + e.message);
    }
}

function deleteDevice(id, name) {
    const run = async () => {
        try {
            const res = await fetch(`${DEVICES_PATH}/${id}`, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            // Destructive: no success toast — the row disappearing is the feedback.
            await fetchPartial(readState());
        } catch (e) {
            window.WaToaster?.error?.('Delete failed: ' + e.message);
        }
    };
    if (typeof window.confirmDialog === 'function') {
        window.confirmDialog({
            title: 'Delete device?',
            message: `Delete "${name}"? This can't be undone.`,
            confirmText: 'Delete',
            cancelText: 'Cancel',
            tone: 'danger',
            onConfirm: run,
        });
    } else {
        // Fallback path if app.js hasn't loaded yet — keeps behaviour safe.
        if (window.confirm(`Delete "${name}"? This can't be undone.`)) run();
    }
}

async function disconnectDevice(id) {
    try {
        const res = await fetch(`${DEVICES_PATH}/${id}/kill-session`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        window.WaToaster?.success?.('Device disconnected');
        await fetchPartial(readState());
    } catch (e) {
        window.WaToaster?.error?.('Disconnect failed: ' + e.message);
    }
}

function wireRowActions() {
    document.querySelectorAll('[data-device-toggle]').forEach((b) => {
        if (b.__wired) return;
        b.__wired = true;
        b.addEventListener('click', () => toggleDevice(b.dataset.deviceToggle));
    });
    document.querySelectorAll('[data-device-disconnect]').forEach((b) => {
        if (b.__wired) return;
        b.__wired = true;
        b.addEventListener('click', () => disconnectDevice(b.dataset.deviceDisconnect));
    });
    document.querySelectorAll('[data-device-connect]').forEach((b) => {
        if (b.__wired) return;
        b.__wired = true;
        b.addEventListener('click', () => openConnectModal(b.dataset.deviceConnect, b.dataset.name || ''));
    });
    document.querySelectorAll('[data-device-delete]').forEach((b) => {
        if (b.__wired) return;
        b.__wired = true;
        b.addEventListener('click', () => deleteDevice(b.dataset.deviceDelete, b.dataset.name || ''));
    });
}

function wirePagination() {
    document.querySelectorAll('a[data-devices-page]').forEach((link) => {
        if (link.__wired) return;
        link.__wired = true;
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state.page = link.dataset.devicesPage || '1';
            writeState(state);
            fetchPartial(state);
        });
    });
}

/* ---- Connect modal (QR / pairing-code flow) ---- */
let connectPollTimer = null;
let connectActiveId  = null;
let connectMode      = null;

function openConnectModal(id, name) {
    const modal = $('connect-device-modal');
    connectActiveId = parseInt(id, 10);
    connectMode = null;
    $('connect-device-title').textContent = name || ('Device #' + id);
    $('connect-mode-pick').classList.remove('hidden');
    $('connect-qr-panel').classList.add('hidden');
    $('connect-code-panel').classList.add('hidden');
    $('connect-progress').classList.add('hidden');
    $('connect-back').classList.add('hidden');
    $('connect-status-bar').style.width = '0%';
    $('connect-status-pct').textContent = '0%';
    $('connect-status-label').textContent = 'Waiting for scan…';
    paintConnectSteps('idle');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    // Re-wire the mode buttons every open so even a stale init pass
    // can't leave them dead. Each open replaces the node with a clone
    // (removes prior listeners) and attaches a fresh handler.
    document.querySelectorAll('[data-connect-mode]').forEach((b) => {
        const clone = b.cloneNode(true);
        b.parentNode.replaceChild(clone, b);
        clone.addEventListener('click', () => {
            if (clone.dataset.connectMode === 'qr')   startQrFlow();
            if (clone.dataset.connectMode === 'code') startCodeFlow();
        });
    });
}

function closeConnectModal() {
    const modal = $('connect-device-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    if (connectPollTimer) { clearInterval(connectPollTimer); connectPollTimer = null; }
    connectActiveId = null;
    connectMode = null;
}

function paintConnectSteps(state) {
    const palette = {
        active: ['border-wa-deep', 'text-wa-deep', 'bg-wa-bubble/30'],
        done:   ['border-wa-green/40', 'text-wa-deep', 'bg-wa-mint/40'],
        idle:   ['border-paper-200', 'text-ink-500'],
    };
    const map = {
        idle:      { generated: 'idle',   scanned: 'idle',   ready: 'idle' },
        generated: { generated: 'active', scanned: 'idle',   ready: 'idle' },
        scanned:   { generated: 'done',   scanned: 'active', ready: 'idle' },
        syncing:   { generated: 'done',   scanned: 'done',   ready: 'active' },
        ready:     { generated: 'done',   scanned: 'done',   ready: 'done'  },
    };
    const set = map[state] || map.idle;
    Object.entries(set).forEach(([k, v]) => {
        const el = document.querySelector(`[data-connect-step="${k}"]`);
        if (!el) return;
        el.className = 'rounded-md px-2 py-1.5 border ' + palette[v].join(' ');
    });
}

function classifyStatus(s) {
    if (!s) return 'idle';
    const x = String(s).toLowerCase();
    if (x === 'ready' || x === 'connected') return 'ready';
    if (x.includes('sync')) return 'syncing';
    if (x.includes('scan') || x.includes('entered')) return 'scanned';
    if (x.includes('generated')) return 'generated';
    return 'idle';
}

async function startQrFlow() {
    connectMode = 'qr';
    $('connect-mode-pick').classList.add('hidden');
    $('connect-qr-panel').classList.remove('hidden');
    $('connect-progress').classList.remove('hidden');
    $('connect-back').classList.remove('hidden');
    $('connect-qr-error').classList.add('hidden');
    $('connect-qr-img').removeAttribute('src');

    try {
        const res = await fetch(`${DEVICES_PATH}/${connectActiveId}/qr-code`, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (data.qr) {
            const src = await renderQrDataUrl(data.qr, 320);
            if (src) $('connect-qr-img').src = src;
        } else if (data.error) {
            throw new Error(data.error);
        } else if (data.status === 'connected') {
            await markConnected();
            window.WaToaster?.success?.('Device connected.');
            // When iframed inside the global Connect-device popover, tell the
            // parent so it can close + refresh the picker behind it.
            if (window.parent && window.parent !== window) {
                try { window.parent.postMessage({ type: 'wadesk:device-connected' }, '*'); } catch (e) {}
            }
            setTimeout(() => closeConnectModal(), 700);
            fetchPartial(readState());
            return;
        }
    } catch (e) {
        $('connect-qr-error').textContent = 'QR failed: ' + e.message;
        $('connect-qr-error').classList.remove('hidden');
        window.WaToaster?.error?.('Could not generate QR: ' + e.message);
    }
    pollConnectionStatus();
}

async function startCodeFlow() {
    connectMode = 'code';
    $('connect-mode-pick').classList.add('hidden');
    $('connect-code-panel').classList.remove('hidden');
    $('connect-progress').classList.remove('hidden');
    $('connect-back').classList.remove('hidden');
    $('connect-code-error').classList.add('hidden');
    $('connect-code').textContent = '— — — —';

    try {
        const res = await fetch(`${DEVICES_PATH}/${connectActiveId}/pairing-code`, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (data.success && data.code) {
            // Render as e.g. "AB12-CD34"
            const formatted = String(data.code).match(/.{1,4}/g)?.join('-') || data.code;
            $('connect-code').textContent = formatted;
        } else if (data.already_connected) {
            window.WaToaster?.success?.('Already connected.');
            await markConnected();
            closeConnectModal();
            return;
        } else {
            throw new Error(data.error || 'No code returned');
        }
    } catch (e) {
        $('connect-code-error').textContent = 'Pairing code failed: ' + e.message;
        $('connect-code-error').classList.remove('hidden');
        window.WaToaster?.error?.('Pairing code: ' + e.message);
    }
    pollConnectionStatus();
}

function pollConnectionStatus() {
    if (connectPollTimer) clearInterval(connectPollTimer);
    connectPollTimer = setInterval(async () => {
        if (!connectActiveId) return;
        try {
            const res = await fetch(`${DEVICES_PATH}/${connectActiveId}/connection-status`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            const status   = data.status || data.message || 'Waiting…';
            const progress = Number(data.progress || 0);
            $('connect-status-label').textContent = status;
            $('connect-status-pct').textContent   = progress + '%';
            $('connect-status-bar').style.width   = Math.max(2, progress) + '%';
            paintConnectSteps(classifyStatus(status));

            if (classifyStatus(status) === 'ready') {
                clearInterval(connectPollTimer);
                connectPollTimer = null;
                await markConnected();
                window.WaToaster?.success?.('Device connected.', { title: 'Linked' });
                setTimeout(() => closeConnectModal(), 700);
                fetchPartial(readState());
            }
        } catch (e) {
            // Soft-fail polling — keep trying.
        }
    }, 1500);
}

async function markConnected() {
    try {
        await fetch(`${DEVICES_PATH}/${connectActiveId}/connection`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
            },
            body: JSON.stringify({ status: 1 }),
        });
    } catch { /* ignore */ }
}

function wireConnectModal() {
    const modal = $('connect-device-modal');
    if (!modal) return;
    $('connect-device-close')?.addEventListener('click', closeConnectModal);
    $('connect-cancel')?.addEventListener('click', closeConnectModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeConnectModal(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeConnectModal();
    });
    document.querySelectorAll('[data-connect-mode]').forEach((b) => {
        b.addEventListener('click', () => {
            if (b.dataset.connectMode === 'qr')   startQrFlow();
            if (b.dataset.connectMode === 'code') startCodeFlow();
        });
    });
    $('connect-back')?.addEventListener('click', () => {
        if (connectPollTimer) { clearInterval(connectPollTimer); connectPollTimer = null; }
        $('connect-mode-pick').classList.remove('hidden');
        $('connect-qr-panel').classList.add('hidden');
        $('connect-code-panel').classList.add('hidden');
        $('connect-progress').classList.add('hidden');
        $('connect-back').classList.add('hidden');
    });
}

async function checkStatus() {
    const sticky = window.WaToaster?.info?.('Checking devices…', { duration: 0 });
    try {
        const res = await fetch(DEVICES_PATH + '/check', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        sticky?.dismiss?.();
        if (data.ok)            window.WaToaster?.success?.('All devices reachable');
        else if (data.reset > 0) window.WaToaster?.warn?.(`Bridge offline — ${data.reset} device(s) marked disconnected`);
        else                     window.WaToaster?.warn?.(data.reason || 'Bridge unreachable');
        await fetchPartial(readState());
    } catch (e) {
        sticky?.dismiss?.();
        window.WaToaster?.error?.('Check failed: ' + e.message);
    }
}

/**
 * Silent /devices/check + partial refresh — fires once on page load
 * and every 10s while the tab is visible. No toasts (this is the
 * background freshness loop, not the operator-initiated button), so
 * a disconnected session shows up as "0 connected" without anyone
 * clicking anything. Skipped while the connect modal is open (you
 * don't want the row to flip while you're pairing).
 */
let bgStatusTimer = null;
async function silentStatusRefresh() {
    if (connectActiveId) return; // mid-pair — leave UI alone
    if (document.hidden) return; // tab in background — save the bridge a call
    try {
        const res = await fetch(DEVICES_PATH + '/check', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        });
        if (!res.ok) return;
        await res.json();
        await fetchPartial(readState());
    } catch (e) {
        // Silent — this is a background loop. If the bridge is down,
        // /devices/check itself marks rows disconnected; fetchPartial
        // will then render the truth.
    }
}

function startBackgroundStatusLoop() {
    if (bgStatusTimer) clearInterval(bgStatusTimer);
    // Fire once on load so the badge is correct before the operator
    // can even read the header. Then every 10s.
    silentStatusRefresh();
    bgStatusTimer = setInterval(silentStatusRefresh, 10000);
    // Pause when the tab is hidden (saves the Node bridge ~6 calls/min
    // per inactive tab) and resume on focus.
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) silentStatusRefresh();
    });
}

/**
 * Mount intl-tel-input on the modal's phone field. Replaces the
 * static 8-country <select> with the full ITU country directory
 * + flags + search — and pushes the selected dial code into the
 * existing hidden country_code input on every change.
 */
function wireCountryPicker(modal) {
    const phoneInput = modal.querySelector('input[name="phone_number"]');
    const ccInput    = modal.querySelector('input[name="country_code"]');
    if (!phoneInput || phoneInput.__itiMounted) return;
    phoneInput.__itiMounted = true;

    // Default country from <meta name="default-country-iso"> — admin sets once
    // in /admin/settings/general to flip every phone picker on the platform.
    const _defIso = (document.querySelector('meta[name="default-country-iso"]')?.content || 'in').toLowerCase();
    const iti = intlTelInput(phoneInput, {
        initialCountry: _defIso,
        separateDialCode: true,
        nationalMode: false,
        autoPlaceholder: 'aggressive',
        // Portal the country list into <body> so the picker isn't
        // clipped by the modal's overflow-hidden / max-h-[90vh].
        // Without this the dropdown gets cut off mid-row whenever
        // the modal isn't tall enough to contain it.
        dropdownContainer: document.body,
    });

    const regionInput = modal.querySelector('input[name="region"]');

    function syncCountry() {
        const data = iti.getSelectedCountryData();
        if (!data) return;
        if (data.dialCode) ccInput.value = '+' + data.dialCode;
        // Auto-populate region with the ISO-2 of whichever country
        // the user just picked (IN / US / AE / GB / …). Operators
        // can still type a custom value; we only overwrite when
        // the field is empty or matches a previous auto-fill.
        if (regionInput && (!regionInput.value || regionInput.dataset.autoFilled === '1')) {
            regionInput.value = String(data.iso2 || '').toUpperCase();
            regionInput.dataset.autoFilled = '1';
        }
    }
    if (regionInput) {
        regionInput.addEventListener('input', () => { regionInput.dataset.autoFilled = ''; });
    }
    phoneInput.addEventListener('countrychange', syncCountry);
    syncCountry();

    // On submit, normalise to E.164 so the controller stores a
    // canonical phone_number even if the user typed local digits.
    modal.querySelector('form')?.addEventListener('submit', () => {
        try {
            const e164 = iti.getNumber();
            if (e164) phoneInput.value = e164.replace(/^\+\d+/, ''); // national chunk after dial code
        } catch { /* keep raw input */ }
    });
}

// Lock the add-device fields (after a successful save) so the details
// the operator entered stay readable beside the QR instead of looking
// wiped. Skips the submit button + hidden inputs.
function lockFormFields(form) {
    if (!form) return;
    form.querySelectorAll('input, select, textarea').forEach((el) => {
        if (el.type === 'submit' || el.type === 'hidden') return;
        if (el.tagName === 'SELECT' || el.type === 'checkbox') el.disabled = true;
        else el.setAttribute('readonly', 'readonly');
        el.classList.add('opacity-70', 'cursor-not-allowed', 'pointer-events-none');
    });
}

function unlockFormFields(form) {
    if (!form) return;
    form.querySelectorAll('input, select, textarea').forEach((el) => {
        if (el.type === 'submit' || el.type === 'hidden') return;
        el.disabled = false;
        el.removeAttribute('readonly');
        el.classList.remove('opacity-70', 'cursor-not-allowed', 'pointer-events-none');
    });
}

function wireAddModal() {
    const modal = $('device-modal');
    if (!modal) return;
    const open  = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        wireCountryPicker(modal);
        // Re-enable the form fields in case a prior pairing left them
        // locked (we lock them after a successful save so the entered
        // details stay readable beside the QR — see the submit handler).
        unlockFormFields($('device-form'));
        // Reset QR slot to placeholder state every time we open
        const qrSlot = modal.querySelector('.bg-paper-50.border-paper-200.rounded-2xl > div:first-child');
        if (qrSlot) qrSlot.innerHTML = '<svg viewBox="0 0 16 16" class="w-12 h-12" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M3 3h4v4H3zM9 3h4v4H9zM3 9h4v4H3zM9 9h2v2H9zM13 9v2M9 13h2"/></svg>';
    };
    const close = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        // Stop any active QR poller when modal closes
        if (qrPollTimer) { clearInterval(qrPollTimer); qrPollTimer = null; }
    };
    $('devices-add-btn')?.addEventListener('click', open);
    $('device-modal-close')?.addEventListener('click', close);
    $('device-cancel')?.addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });

    // AJAX form submit — keeps the modal open, swaps the QR placeholder
    // for a loading spinner, then a real QR fetched from Laravel which
    // proxies to Node's /api/initialize-client/<phone>.
    const form = $('device-form');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type=submit]');
            const origLabel = submitBtn?.innerHTML;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Connecting…';
            }
            renderQrSlotLoading('Connecting device…');

            try {
                const fd = new FormData(form);
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });
                const j = await res.json();
                if (!res.ok || !j.ok) {
                    renderQrSlotError(j.message || 'Save failed');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = origLabel; }
                    return;
                }
                if (submitBtn) submitBtn.innerHTML = 'Scan the QR →';
                // Keep the details the operator typed visible (locked, not
                // wiped) right next to the QR — they asked not to have the
                // form cleared the moment the QR appears.
                lockFormFields(form);
                renderQrSlotLoading('Generating QR…');
                startQrPoll(j.device_id, j.qr_url, j.status_url);
            } catch (err) {
                renderQrSlotError('Network error: ' + err.message);
                if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = origLabel; }
            }
        });
    }
}

let qrPollTimer = null;

function getQrSlotContainer() {
    const modal = $('device-modal');
    if (!modal) return null;
    return modal.querySelector('.bg-paper-50.border-paper-200.rounded-2xl');
}

function renderQrSlotLoading(msg) {
    const box = getQrSlotContainer();
    if (!box) return;
    box.innerHTML = `
      <div class="w-32 h-32 rounded-2xl border border-dashed border-paper-300 grid place-items-center text-wa-deep">
        <svg class="w-10 h-10 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.2"/><path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
      </div>
      <div class="font-serif text-[16px] mt-4 text-ink-900">${msg || 'Working…'}</div>
      <div class="text-[11px] text-ink-500 mt-1 font-mono">Don't close this window.</div>`;
}

function renderQrSlotError(msg) {
    const box = getQrSlotContainer();
    if (!box) return;
    box.innerHTML = `
      <div class="w-32 h-32 rounded-2xl border border-accent-coral/40 bg-accent-coral/5 grid place-items-center text-accent-coral">
        <svg viewBox="0 0 16 16" class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6.5"/><path d="M8 5v3M8 11h.01"/></svg>
      </div>
      <div class="font-serif text-[16px] mt-4 text-accent-coral">Couldn't generate QR</div>
      <div class="text-[11px] text-ink-500 mt-1 font-mono break-words text-center">${(msg || '').toString().slice(0, 200)}</div>`;
}

async function renderQrSlotImage(qrData, statusLine) {
    const box = getQrSlotContainer();
    if (!box) return;
    // Local QR render — no external service. Node hands us the raw
    // WhatsApp pairing string; renderQrDataUrl draws it to a PNG
    // data: URL we can drop into <img src>.
    const src = await renderQrDataUrl(qrData, 320);
    if (!src) return;
    box.innerHTML = `
      <img src="${src}" alt="WhatsApp QR" class="w-48 h-48 object-contain rounded-xl bg-white p-2 border border-paper-200" />
      <div class="font-serif text-[16px] mt-3 text-ink-900">Scan with WhatsApp</div>
      <div class="text-[11px] text-ink-500 mt-1 font-mono">${statusLine || 'Waiting for pair…'}</div>`;
}

function renderQrSlotSuccess(phone) {
    const box = getQrSlotContainer();
    if (!box) return;
    box.innerHTML = `
      <div class="w-32 h-32 rounded-2xl bg-wa-mint grid place-items-center text-wa-deep">
        <svg viewBox="0 0 16 16" class="w-12 h-12" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l3 3 7-7"/></svg>
      </div>
      <div class="font-serif text-[16px] mt-4 text-wa-deep">Connected ✓</div>
      <div class="text-[11px] text-ink-500 mt-1 font-mono">${phone || 'Device paired'}</div>`;
}

function startQrPoll(deviceId, qrUrl, statusUrl) {
    if (qrPollTimer) clearInterval(qrPollTimer);
    let attempt = 0;
    let qrLoaded = false;

    const tick = async () => {
        attempt++;
        try {
            // First call asks Node to spin up the session and returns the
            // initial QR. Subsequent calls only poll status.
            const url = qrLoaded ? statusUrl : qrUrl;
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            const j = await res.json();

            if (j.status === 'connected' || j.paired) {
                clearInterval(qrPollTimer);
                qrPollTimer = null;
                renderQrSlotSuccess(j.phone);
                // Inside the global Connect-device popover (iframe): tell the
                // parent to close + refresh the picker instead of reloading the
                // iframe. On the real /devices page, reload as before.
                if (window.parent && window.parent !== window) {
                    setTimeout(() => { try { window.parent.postMessage({ type: 'wadesk:device-connected' }, '*'); } catch (e) {} }, 900);
                } else {
                    setTimeout(() => location.reload(), 1500);
                }
                return;
            }

            if (j.qr) {
                qrLoaded = true;
                renderQrSlotImage(j.qr, 'Open WhatsApp → Settings → Linked devices → Link a device');
            } else if (!qrLoaded) {
                renderQrSlotLoading('Generating QR…');
            }

            if (attempt > 60) {
                clearInterval(qrPollTimer);
                qrPollTimer = null;
                renderQrSlotError("Timed out waiting for pair. Check that the Node bridge is running, then try again.");
            }
        } catch (e) {
            // Keep polling on transient errors; only abort if too many.
            if (attempt > 10 && !qrLoaded) {
                clearInterval(qrPollTimer);
                qrPollTimer = null;
                renderQrSlotError(e.message);
            }
        }
    };
    tick();
    qrPollTimer = setInterval(tick, 2000);
}

/* ---- Add-device chooser modal (multi-engine /devices only) ----
 * The engine tables stay visible on the page. The header "Add device"
 * button opens THIS modal — one card per enabled engine. Each card
 * carries the existing per-engine connect trigger (#devices-add-btn for
 * Unofficial, [data-waba-connect] for WABA, [data-twilio-connect] for
 * Twilio), so clicking it opens that engine's own connect modal; we just
 * close the chooser on top. No chooser DOM on a single-engine page → no-op.
 */
function wireAddChooser() {
    const modal = $('add-device-chooser');
    if (!modal) return;
    const open  = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
    const close = () => { modal.classList.add('hidden');    modal.classList.remove('flex'); };

    document.querySelectorAll('[data-open-add-chooser]').forEach((b) =>
        b.addEventListener('click', open));
    modal.querySelectorAll('[data-chooser-close]').forEach((b) =>
        b.addEventListener('click', close));
    // Card click: close the chooser. The card's own trigger (wired by
    // wireAddModal / wireWabaConnectModals / wireTwilioModal) opens the
    // engine connect modal in the same click.
    modal.querySelectorAll('[data-add-card]').forEach((c) =>
        c.addEventListener('click', () => close()));
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });
}

/* ---- Twilio connect modal (multi-engine /devices only) ----
 * Opened by any [data-twilio-connect] trigger (the chooser card or the
 * Twilio section's "Connect Twilio" button). Holds the same form the
 * single-engine page renders inline. No-op when the modal isn't present.
 */
function wireTwilioModal() {
    const modal = $('twilio-connect-modal');
    if (!modal) return;
    const open  = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
    const close = () => { modal.classList.add('hidden');    modal.classList.remove('flex'); };

    document.querySelectorAll('[data-twilio-connect]').forEach((b) =>
        b.addEventListener('click', open));
    modal.querySelectorAll('[data-twilio-modal-close]').forEach((b) =>
        b.addEventListener('click', close));
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });
}

/* ---- Plan-limit pre-flight gate ----
 * Block opening ANY connect popup (WABA Embedded Signup, Baileys QR, Twilio,
 * the add-device chooser) when the workspace is already at its WhatsApp-number
 * cap. Shows a toast INSTEAD of the Meta signup popup, so the user never goes
 * through Facebook only to be rejected server-side. Backend PlanLimitGuard
 * still enforces the cap; this is the UX pre-check. Reads used/limit off
 * [data-devices-root]; data-device-limit="0" = unlimited → never gates.
 */
function wireDeviceLimitGate() {
    // Capture phase → runs BEFORE each trigger's own click handler, so
    // stopImmediatePropagation prevents the modal/popup from opening at all.
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest(
            '[data-open-add-chooser], [data-waba-connect], [data-twilio-connect], #devices-add-btn'
        );
        if (!trigger) return;
        const root = document.querySelector('[data-devices-root]');
        if (!root) return;
        const limit = parseInt(root.dataset.deviceLimit || '0', 10);   // 0 = unlimited
        if (!limit) return;
        const used = parseInt(root.dataset.deviceUsed || '0', 10);
        if (used < limit) return;   // under the cap → open normally
        e.preventDefault();
        e.stopImmediatePropagation();
        window.WaToaster?.error?.(
            `Your plan allows ${limit} WhatsApp number${limit === 1 ? '' : 's'}. Remove one or upgrade to add another.`,
            { title: 'Plan limit reached' }
        );
    }, true);
}

export default function init() {
    // Bootstrap the shared provider-connect logic (Baileys QR + WABA
    // Embedded Signup + Twilio creds) — same one /connect page uses.
    try { initProviderConnect(); } catch (e) { /* don't break devices page */ }

    wireDeviceLimitGate();

    document.querySelectorAll('[data-devices-filter]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state[el.dataset.devicesFilter] = el.dataset.devicesValue;
            state.page = '1';
            writeState(state);
            paintActive(state);
            fetchPartial(state);
        });
    });

    $('devices-search')?.addEventListener('input', (e) => {
        const state = readState();
        state.q = e.target.value.trim();
        state.page = '1';
        writeState(state);
        debouncedFetch(state);
    });

    $('devices-check-btn')?.addEventListener('click', checkStatus);
    wireAddChooser();
    wireTwilioModal();
    wireAddModal();
    wireConnectModal();
    wireRowActions();
    wirePagination();

    // Background auto-refresh: silently re-pull the device list every 15s so
    // connect/disconnect changes (and Sent/Failed counts) appear live without
    // a manual reload — whether or not the user is interacting. Skipped while
    // the connect modal runs its own poll, when the tab is hidden, or while
    // the user is typing in the search box (so it never steals focus or
    // clobbers a half-typed query). fetchPartial re-renders + re-wires rows.
    setInterval(() => {
        if (connectActiveId) return;
        if (document.hidden) return;
        if (document.activeElement === $('devices-search')) return;
        fetchPartial(readState());
    }, 15000);
    wireWabaConnectModals();   // Phase 3 WABA mode — no-op when WABA modals aren't on the page
    // Auto-poll device status (silent) so "X connected" never goes stale.
    startBackgroundStatusLoop();

    /**
     * WABA connect modals — open/close + Meta JS SDK launcher for the
     * "Continue with Facebook" Embedded Signup flow. No-op when the
     * WABA modal DOM isn't present (i.e. /devices in Baileys mode).
     */
    function wireWabaConnectModals() {
        const manual   = document.getElementById('waba-manual-modal');
        const embedded = document.getElementById('waba-embedded-modal');
        if (!manual && !embedded) return;

        const open  = (m) => { if (!m) return; m.classList.remove('hidden'); m.classList.add('flex'); };
        const close = (m) => { if (!m) return; m.classList.add('hidden');    m.classList.remove('flex'); };

        document.querySelectorAll('[data-waba-connect]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const mode = btn.dataset.wabaConnect;
                if (mode === 'embedded' && embedded) open(embedded);
                else open(manual);
            });
        });

        [manual, embedded].forEach((m) => {
            if (!m) return;
            m.addEventListener('click', (e) => { if (e.target === m) close(m); });
            m.querySelectorAll('[data-waba-modal-close]').forEach((b) =>
                b.addEventListener('click', () => close(m)));
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { close(manual); close(embedded); }
        });

        // ── Verify-token self-check (tests the pasted token before saving) ──
        const esc = (s) => { const d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; };
        const renderVerify = (out, allOk, checks) => {
            if (!out) return;
            out.classList.remove('hidden');
            out.className = 'rounded-xl border px-3.5 py-2.5 text-[12px] space-y-1.5 ' +
                (allOk ? 'border-wa-green/40 bg-wa-mint/40' : 'border-accent-coral/40 bg-accent-coral/5');
            const rows = (checks || []).map((c) => {
                const icon = c.ok
                    ? '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0 mt-px text-wa-green" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 8.5 6.5 11.5 12.5 5"/></svg>'
                    : '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0 mt-px text-accent-coral" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 5l6 6M11 5l-6 6"/></svg>';
                const detail = c.detail ? '<div class="text-ink-500 text-[11px] leading-snug">' + esc(c.detail) + '</div>' : '';
                return '<div class="flex items-start gap-2">' + icon + '<div><div class="font-semibold">' + esc(c.title || '') + '</div>' + detail + '</div></div>';
            }).join('');
            out.innerHTML = '<div class="font-semibold ' + (allOk ? 'text-wa-deep' : 'text-accent-coral') + '">' +
                (allOk ? 'Token looks good — safe to connect.' : 'Problems found — fix these before connecting.') + '</div>' + rows;
        };
        const vBtn = manual?.querySelector('[data-waba-verify-token]');
        if (vBtn) {
            const vLabel = vBtn.querySelector('[data-waba-verify-label]');
            const vOut   = manual.querySelector('[data-waba-verify-result]');
            vBtn.addEventListener('click', async () => {
                const form  = manual.querySelector('form');
                const token = form?.querySelector('[name="access_token"]')?.value?.trim() || '';
                const waba  = form?.querySelector('[name="waba_id"]')?.value?.trim() || '';
                const pnid  = form?.querySelector('[name="phone_number_id"]')?.value?.trim() || '';
                if (!token) { renderVerify(vOut, false, [{ ok: false, title: 'Paste an access token first', detail: '' }]); return; }
                const orig = vLabel ? vLabel.textContent : '';
                if (vLabel) vLabel.textContent = 'Checking…';
                vBtn.disabled = true;
                try {
                    const res = await fetch(vBtn.dataset.verifyUrl, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
                        body: JSON.stringify({ access_token: token, waba_id: waba, phone_number_id: pnid }),
                    });
                    const data = await res.json();
                    renderVerify(vOut, !!data.ok, Array.isArray(data.checks) ? data.checks : [{ ok: false, title: 'Verification failed', detail: '' }]);
                } catch (err) {
                    renderVerify(vOut, false, [{ ok: false, title: 'Could not reach the server', detail: String(err) }]);
                } finally {
                    if (vLabel) vLabel.textContent = orig || 'Verify token';
                    vBtn.disabled = false;
                }
            });
        }

        // Copy-to-clipboard for the webhook Callback URL + Verify token chips.
        manual?.querySelectorAll('[data-copy]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                try { await navigator.clipboard.writeText(btn.dataset.copy || ''); } catch (e) { /* noop */ }
                const orig = btn.textContent;
                btn.textContent = 'Copied';
                setTimeout(() => { btn.textContent = orig; }, 1200);
            });
        });

        const launchBtn = document.getElementById('waba-launch-embedded-signup');
        const coexBtn   = document.getElementById('waba-launch-embedded-coex');
        const baseBtn   = launchBtn || coexBtn;
        if (!baseBtn) return;

        let fbReady = false;
        const appId    = baseBtn.dataset.appId;
        const configId = baseBtn.dataset.configId;
        // Graph/SDK version from the admin "Graph API version" setting
        // (data-graph-version) so the embedded-signup dialog matches the
        // server-side REST version instead of a hardcoded one.
        const graphVersion = baseBtn.dataset.graphVersion || 'v23.0';
        // Coexistence vs new-number is now chosen PER CLICK via two explicit
        // buttons: the primary launches normal Cloud-API onboarding; the
        // secondary launches Meta's Business-App onboarding (link an existing
        // Business App number — Meta shows a QR in its popup, app keeps working).
        // Set just before each FB.login().
        let coexMode = false;

        // Meta delivers Embedded Signup result via two separate channels:
        //  1. FB.login() callback → returns response.authResponse.code
        //  2. window.postMessage() → posts { type:'WA_EMBEDDED_SIGNUP',
        //     event:'FINISH', data:{ phone_number_id, waba_id, business_id }}
        // Both must arrive before we submit the form to our backend.
        // Buffer whichever fires first and submit when both are present.
        const pending = { code: null, waba_id: null, phone_number_id: null, business_id: null, submitted: false };

        const trySubmit = () => {
            if (pending.submitted) return;                          // never POST twice
            if (!pending.code) return;                              // wait for FB.login code
            if (!pending.waba_id) return;                           // always need the WABA id
            // Standard Cloud-API onboarding returns the phone number in the
            // postMessage, so wait for it. COEXISTENCE links an existing
            // WhatsApp Business APP number, which Meta registers with Cloud API
            // ASYNCHRONOUSLY — the phone_number_id is usually absent at FINISH,
            // so submit with just the WABA and let the server resolve the
            // number from the WABA's phone_numbers edge.
            if (!coexMode && !pending.phone_number_id) return;
            const form = document.getElementById('waba-embedded-form');
            if (!form) return;
            pending.submitted = true;
            form.querySelector('[name="code"]').value            = pending.code;
            form.querySelector('[name="waba_id"]').value         = pending.waba_id;
            form.querySelector('[name="phone_number_id"]').value = pending.phone_number_id || '';
            form.querySelector('[name="business_id"]').value     = pending.business_id || '';
            // Tell the server this was a coexistence onboard so it skips the
            // /register step (which would migrate the number off the app).
            const coexInput = form.querySelector('[name="coexistence"]');
            if (coexInput) coexInput.value = coexMode ? '1' : '0';
            form.submit();
        };

        // Listen for Meta's postMessage event. Meta emits WA_EMBEDDED_SIGNUP
        // from www.facebook.com OR web.facebook.com (and other *.facebook.com
        // surfaces, depending on the user's session/locale). Per Meta's current
        // docs we match endsWith('facebook.com') — a strict www-only check
        // silently DROPS the message for some users, so phone_number_id/waba_id
        // are never captured and "no device connects". Handles every event:
        // FINISH, FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING (coexistence),
        // FINISH_ONLY_WABA, CANCEL, ERROR.
        window.addEventListener('message', (event) => {
            if (!event.origin || !event.origin.endsWith('facebook.com')) return;
            let data;
            try { data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data; }
            catch (_) { return; }
            if (!data || data.type !== 'WA_EMBEDDED_SIGNUP') return;

            const ev = data.event;
            // FINISH = standard Cloud-API onboarding. COEXISTENCE (link an
            // existing WhatsApp Business APP number) fires a DISTINCT event,
            // FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING — NOT "FINISH" — whose
            // data may carry only waba_id (phone_number_id is resolved
            // server-side). Some flows also send a FINISH with
            // data.is_wa_login_user === true for coexistence. Handle all.
            if ((ev === 'FINISH' || ev === 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING') && data.data) {
                pending.waba_id         = data.data.waba_id         || pending.waba_id;
                pending.phone_number_id = data.data.phone_number_id || pending.phone_number_id;
                pending.business_id     = data.data.business_id     || pending.business_id;
                if (ev === 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING' || data.data.is_wa_login_user) {
                    coexMode = true;   // submit on waba_id alone; server resolves the number
                }
                trySubmit();
            } else if (ev === 'FINISH_ONLY_WABA' && data.data) {
                pending.waba_id     = data.data.waba_id     || pending.waba_id;
                pending.business_id = data.data.business_id || pending.business_id;
                if (coexMode) {
                    // Coexistence: NO phone number in the payload is expected —
                    // the existing Business-App number registers asynchronously.
                    // Submit with the WABA; the server resolves the number from
                    // its phone_numbers edge.
                    trySubmit();
                } else {
                    // Standard flow genuinely needs a number selection.
                    alert('WhatsApp Business Account authorised, but no phone number was selected. Pick one in Meta Business Suite, then click Add WABA → Manual to paste the Phone Number ID.');
                }
            } else if (ev === 'CANCEL') {
                console.warn('[WABA-embedded] user closed the dialog');
            } else if (ev === 'ERROR') {
                console.error('[WABA-embedded] Meta returned ERROR', data.data);
                alert('Meta error during sign-up: ' + (data.data?.error_message || data.data?.error_id || 'unknown'));
            }
        });

        // silent=true is used for the EAGER boot on page load — we must not pop
        // an alert at a user who never opens the WABA modal. The click path calls
        // it non-silent so a genuine launch attempt still surfaces a load failure.
        const bootFb = (silent = false) => {
            if (window.FB) { window.FB.init({ appId, cookie: true, xfbml: true, version: graphVersion }); fbReady = true; return; }
            let tries = 0;
            const t = setInterval(() => {
                tries++;
                if (window.FB) {
                    window.FB.init({ appId, cookie: true, xfbml: true, version: graphVersion });
                    fbReady = true;
                    clearInterval(t);
                } else if (tries > 25) {
                    clearInterval(t);
                    if (!silent) alert('Meta SDK failed to load — refresh and try again.');
                }
            }, 200);
        };

        const runLaunch = () => {
            if (!appId || !configId) {
                alert('Embedded Signup is not configured. Ask your platform admin to fill the Config ID at /admin/settings/wadesk-message.');
                return;
            }
            if (!fbReady) bootFb();
            const launch = () => {
                window.FB.login((response) => {
                    if (response.authResponse && response.authResponse.code) {
                        pending.code = response.authResponse.code;
                        trySubmit();
                        // WABA id + phone-number id arrive via postMessage (usually
                        // within a second). If we got the code but no WABA message
                        // after 10s, tell the user instead of leaving them hanging.
                        setTimeout(() => {
                            if (!pending.submitted) {
                                alert(coexMode
                                    ? 'Signed in, but Meta did not return your WhatsApp Business Account. Make sure you completed the Business-App linking (scan the QR Meta showed in its popup), then try again — or use Add WABA → Manual.'
                                    : 'Signed in, but Meta did not return a WhatsApp number. Make sure you picked a phone number in the Facebook dialog, then try again — or use Add WABA → Manual.');
                            }
                        }, 15000);
                    } else {
                        console.warn('[WABA-embedded] FB.login cancelled or denied', response);
                    }
                }, {
                    config_id: configId,
                    response_type: 'code',
                    override_default_response_type: true,
                    // sessionInfoVersion: '3' is REQUIRED for ES in 2026.
                    // Without it, Meta's postMessage payload defaults to
                    // the legacy v1 shape and waba_id won't be populated.
                    // featureType 'whatsapp_business_app_onboarding' launches
                    // Meta's Coexistence flow (link an existing Business App
                    // number; app keeps working, Cloud API + webhooks run too).
                    extras: {
                        // setup:{} is the empty default Meta documents in every
                        // ES example (a partner Solution ID would go in here).
                        setup: {},
                        sessionInfoVersion: '3',
                        ...(coexMode ? { featureType: 'whatsapp_business_app_onboarding' } : {}),
                    },
                });
            };
            if (fbReady) launch();
            else setTimeout(() => fbReady ? launch() : alert('Meta SDK still loading — try again.'), 500);
        };
        // Primary = brand-new number; secondary = existing Business App (coexistence).
        launchBtn?.addEventListener('click', () => { coexMode = false; runLaunch(); });
        coexBtn?.addEventListener('click',   () => { coexMode = true;  runLaunch(); });

        // Boot the Meta SDK NOW, not on the first tap. THIS is the mobile fix:
        // on a phone, FB.login() only opens its popup if it runs synchronously
        // inside the tap gesture. The old lazy path booted the SDK on click and
        // then called FB.login() from a setTimeout, so on mobile the popup was
        // blocked and the flow silently "went back" (desktop's lenient popup
        // rules hid it). Initialising here means fbReady is already true by tap
        // time, so runLaunch() takes its synchronous branch and the popup opens.
        bootFb(true);
    }

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const state = {
            status: params.get('status') || 'all',
            region: params.get('region') || 'all',
            q:      params.get('q')      || '',
            page:   params.get('page')   || '1',
        };
        writeState(state);
        paintActive(state);
        fetchPartial(state);
    });
}
