/*
 * /auto-reply index page.
 * Server-rendered list/grid fragments are refreshed via ?partial=1 so
 * filtering, pagination, delete, toggle, and CSV import stay fast.
 */

const $ = (id) => document.getElementById(id);

function csrf() {
    return document.querySelector('meta[name=csrf-token]')?.content || '';
}

function notify(type, message, options = {}) {
    const toaster = window.WaToaster;
    if (toaster?.[type]) {
        return toaster[type](message, options);
    }
    if (window.toast) {
        return window.toast(message, type === 'error' ? 'error' : 'success');
    }
    console[type === 'error' ? 'error' : 'log'](message);
    return null;
}

function debounce(fn, ms) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), ms);
    };
}

function buildQuery(state) {
    const params = new URLSearchParams();
    Object.entries(state).forEach(([key, value]) => {
        if (key === 'page' && Number(value || 1) <= 1) return;
        if (key === 'view' && (!value || value === 'list')) return;
        if (value === undefined || value === null || value === '' || value === 'all') return;
        params.set(key, value);
    });
    return params.toString();
}

function readState() {
    const root = document.querySelector('[data-ar-state]');
    return {
        q: root?.dataset.arSearch || '',
        device: root?.dataset.arDevice || 'all',
        status: root?.dataset.arStatus || 'all',
        type: root?.dataset.arType || 'all',
        view: root?.dataset.arView || 'list',
        page: root?.dataset.arPage || '1',
    };
}

function writeState(state) {
    const root = document.querySelector('[data-ar-state]');
    if (!root) return;
    root.dataset.arSearch = state.q || '';
    root.dataset.arDevice = state.device || 'all';
    root.dataset.arStatus = state.status || 'all';
    root.dataset.arType = state.type || 'all';
    root.dataset.arView = state.view || 'list';
    root.dataset.arPage = state.page || '1';
}

function updateUrl(state, replace = false) {
    const query = buildQuery(state);
    const url = query ? `/auto-reply?${query}` : '/auto-reply';
    if (replace) {
        history.replaceState({}, '', url);
    } else {
        history.pushState({}, '', url);
    }
}

function paintInputs(state) {
    const search = $('ar-search');
    if (search && document.activeElement !== search) search.value = state.q || '';
    const device = $('ar-device-filter');
    if (device) device.value = state.device || 'all';
    const status = $('ar-status-filter');
    if (status) status.value = state.status || 'all';
    const type = $('ar-type-filter');
    if (type) type.value = state.type || 'all';
}

function showPanel(panel) {
    if (!panel) return;
    clearTimeout(panel.__arHideTimer);
    panel.classList.remove('hidden');
    panel.classList.add('opacity-0');
    requestAnimationFrame(() => {
        panel.classList.remove('opacity-0');
        panel.classList.add('opacity-100');
    });
}

function hidePanel(panel) {
    if (!panel) return;
    clearTimeout(panel.__arHideTimer);
    panel.classList.remove('opacity-100');
    panel.classList.add('opacity-0');
    panel.__arHideTimer = setTimeout(() => panel.classList.add('hidden'), 150);
}

function paintView(view) {
    const list = $('ar-list-view');
    const grid = $('ar-grid-view');
    if (view === 'grid') {
        showPanel(grid);
        hidePanel(list);
    } else {
        showPanel(list);
        hidePanel(grid);
    }

    document.querySelectorAll('[data-ar-view-button]').forEach((button) => {
        const active = button.dataset.arViewButton === view;
        button.classList.toggle('bg-wa-deep', active);
        button.classList.toggle('text-paper-0', active);
        button.classList.toggle('text-ink-600', !active);
        button.classList.toggle('hover:bg-paper-50', !active);
    });
}

function syncToggleLabel(input) {
    const label = input.closest('label');
    const text = label?.querySelector('[data-toggle-text]');
    if (!text) return;
    const on = text.getAttribute('data-on') || 'On';
    const off = text.getAttribute('data-off') || 'Off';
    const onClass = text.getAttribute('data-on-class');
    const offClass = text.getAttribute('data-off-class');
    text.textContent = input.checked ? on : off;
    if (onClass) text.classList.toggle(onClass, input.checked);
    if (offClass) text.classList.toggle(offClass, !input.checked);
}

async function fetchJson(path, options = {}) {
    const response = await fetch(path, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
            ...(options.headers || {}),
        },
        ...options,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || `HTTP ${response.status}`);
    return data;
}

function setBusy(isBusy) {
    $('ar-list-view')?.classList.toggle('opacity-60', isBusy);
    $('ar-grid-view')?.classList.toggle('opacity-60', isBusy);
    $('ar-pagination')?.classList.toggle('opacity-60', isBusy);
}

function applyTotals(totals) {
    if (!totals) return;
    Object.entries(totals).forEach(([key, value]) => {
        document.querySelectorAll(`[data-ar-stat="${key}"]`).forEach((node) => {
            node.textContent = Number(value || 0).toLocaleString();
        });
    });
}

async function fetchPartial(state, push = true) {
    writeState(state);
    paintInputs(state);
    paintView(state.view);
    if (push) updateUrl(state);

    const query = buildQuery(state);
    const url = query ? `/auto-reply?${query}` : '/auto-reply';
    const partialUrl = `${url}${query ? '&' : '?'}partial=1`;

    setBusy(true);
    try {
        const data = await fetchJson(partialUrl, { method: 'GET' });
        const tbody = $('ar-tbody');
        if (tbody) tbody.innerHTML = data.rows || '';
        const grid = $('ar-grid-view');
        if (grid) grid.innerHTML = data.grid || '';
        const pager = $('ar-pagination');
        if (pager) pager.innerHTML = data.pagination || '';

        const totalCount = Number(data.total || 0);
        $('ar-results-footer')?.classList.toggle('hidden', totalCount <= 0);
        const shown = document.querySelector('[data-ar-shown]');
        if (shown) shown.textContent = Number(data.shown || 0).toLocaleString();
        const total = document.querySelector('[data-ar-total]');
        if (total) total.textContent = totalCount.toLocaleString();
        applyTotals(data.totals);

        state.page = String(data.page || state.page || '1');
        writeState(state);
        paintView(state.view);
        wireDynamicActions();
    } catch (error) {
        notify('error', `Could not refresh auto replies: ${error.message}`);
    } finally {
        setBusy(false);
    }
}

const debouncedFetch = debounce((state) => fetchPartial(state), 220);

async function toggleRule(input) {
    const id = input.dataset.toggleRow;
    if (!id) return;
    const previous = !input.checked;
    syncToggleLabel(input);
    input.disabled = true;

    try {
        await fetchJson(`/auto-reply/${id}/toggle`, { method: 'POST' });
        notify('success', input.checked ? 'Auto reply activated.' : 'Auto reply paused.');
        await fetchPartial(readState(), false);
    } catch (error) {
        input.checked = previous;
        syncToggleLabel(input);
        notify('error', `Toggle failed: ${error.message}`);
    } finally {
        input.disabled = false;
    }
}

function deleteRule(id, name) {
    const run = async () => {
        try {
            await fetchJson(`/auto-reply/${id}`, { method: 'DELETE' });
            await fetchPartial(readState(), false);
        } catch (error) {
            notify('error', `Delete failed: ${error.message}`);
        }
    };

    if (typeof window.confirmDialog === 'function') {
        window.confirmDialog({
            title: 'Delete auto reply?',
            message: `Delete "${name || 'this rule'}"? This cannot be undone.`,
            confirmText: 'Delete',
            cancelText: 'Cancel',
            tone: 'danger',
            onConfirm: run,
        });
        return;
    }

    if (window.confirm(`Delete "${name || 'this rule'}"? This cannot be undone.`)) run();
}

function resetFilters() {
    const state = { q: '', device: 'all', status: 'all', type: 'all', view: readState().view || 'list', page: '1' };
    writeState(state);
    paintInputs(state);
    fetchPartial(state);
}

function wireDynamicActions() {
    document.querySelectorAll('input[data-toggle-label]').forEach((input) => {
        syncToggleLabel(input);
        if (input.__arWired) return;
        input.__arWired = true;
        input.addEventListener('change', () => toggleRule(input));
    });

    document.querySelectorAll('[data-row-action="delete"]').forEach((button) => {
        if (button.__arWired) return;
        button.__arWired = true;
        button.addEventListener('click', () => deleteRule(button.dataset.rowId, button.dataset.rowName));
    });

    // Per-row bulk checkbox → update the bulk action bar visibility +
    // selected count. Wires on every partial re-render because rows are
    // replaced inside #ar-tbody when filters change.
    document.querySelectorAll('input[data-bulk-row]').forEach((cb) => {
        if (cb.__arBulkWired) return;
        cb.__arBulkWired = true;
        cb.addEventListener('change', refreshBulkBar);
    });
    refreshBulkBar();

    document.querySelectorAll('a[data-ar-page]').forEach((link) => {
        if (link.__arWired) return;
        link.__arWired = true;
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const state = readState();
            state.page = link.dataset.arPage || '1';
            fetchPartial(state);
        });
    });

    document.querySelectorAll('[data-ar-reset]').forEach((button) => {
        if (button.__arWired) return;
        button.__arWired = true;
        button.addEventListener('click', resetFilters);
    });
}

function openImportModal() {
    const modal = $('ar-import-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    $('ar-import-file')?.focus();
}

function closeImportModal() {
    const modal = $('ar-import-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function resetImportForm() {
    const form = $('ar-import-form');
    form?.reset();
    const label = document.querySelector('[data-ar-file-label]');
    if (label) label.textContent = 'Choose CSV file';
    document.querySelector('[data-ar-file-tile]')?.classList.remove('bg-wa-bubble');
}

function wireImportModal() {
    $('ar-import-open')?.addEventListener('click', openImportModal);

    document.querySelectorAll('[data-ar-import-close]').forEach((button) => {
        button.addEventListener('click', () => {
            closeImportModal();
            resetImportForm();
        });
    });

    $('ar-import-modal')?.addEventListener('click', (event) => {
        if (event.target === event.currentTarget) {
            closeImportModal();
            resetImportForm();
        }
    });

    const file = $('ar-import-file');
    file?.addEventListener('change', () => {
        const label = document.querySelector('[data-ar-file-label]');
        if (label) label.textContent = file.files?.[0]?.name || 'Choose CSV file';
        document.querySelector('[data-ar-file-tile]')?.classList.toggle('bg-wa-bubble', !!file.files?.length);
    });

    const form = $('ar-import-form');
    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        const formData = new FormData(form);
        if (button) {
            button.disabled = true;
            button.classList.add('opacity-60');
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: formData,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message || `HTTP ${response.status}`);
            notify('success', data.message || 'Auto replies imported.');
            closeImportModal();
            resetImportForm();
            const state = readState();
            state.page = '1';
            await fetchPartial(state, false);
        } catch (error) {
            notify('error', `Import failed: ${error.message}`);
        } finally {
            if (button) {
                button.disabled = false;
                button.classList.remove('opacity-60');
            }
        }
    });
}

export default function init() {
    const initialState = readState();
    paintInputs(initialState);
    paintView(initialState.view);

    $('ar-search')?.addEventListener('input', () => {
        const state = readState();
        state.q = $('ar-search')?.value.trim() || '';
        state.page = '1';
        writeState(state);
        debouncedFetch(state);
    });

    $('ar-device-filter')?.addEventListener('change', (event) => {
        const state = readState();
        state.device = event.target.value || 'all';
        state.page = '1';
        fetchPartial(state);
    });

    $('ar-status-filter')?.addEventListener('change', (event) => {
        const state = readState();
        state.status = event.target.value || 'all';
        state.page = '1';
        fetchPartial(state);
    });

    $('ar-type-filter')?.addEventListener('change', (event) => {
        const state = readState();
        state.type = event.target.value || 'all';
        state.page = '1';
        fetchPartial(state);
    });

    document.querySelectorAll('[data-ar-view-button]').forEach((button) => {
        button.addEventListener('click', () => {
            const state = readState();
            state.view = button.dataset.arViewButton || 'list';
            writeState(state);
            paintView(state.view);
            updateUrl(state);
        });
    });

    wireDynamicActions();
    wireImportModal();
    wireBulkBar();

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const state = {
            q: params.get('q') || '',
            device: params.get('device') || 'all',
            status: params.get('status') || 'all',
            type: params.get('type') || 'all',
            view: params.get('view') || 'list',
            page: params.get('page') || '1',
        };
        writeState(state);
        fetchPartial(state, false);
    });
}

// ─── Bulk-select + bulk-action handlers ────────────────────────────
// The endpoint `/auto-reply/bulk` (action=activate|deactivate|delete) was
// implemented server-side but had no UI surface. This wires the header
// "select all" checkbox, per-row checkboxes, the visible bulk-action bar
// (#ar-bulk-bar) and its three action buttons + clear button.

function selectedBulkIds() {
    return Array.from(document.querySelectorAll('input[data-bulk-row]:checked'))
        .map((el) => parseInt(el.dataset.bulkRow, 10))
        .filter((n) => Number.isFinite(n));
}

function refreshBulkBar() {
    const bar = document.querySelector('[data-bulk-bar]');
    if (!bar) return;
    const ids = selectedBulkIds();
    const countEl = bar.querySelector('[data-bulk-count]');
    if (countEl) countEl.textContent = String(ids.length);
    if (ids.length > 0) {
        bar.classList.remove('hidden');
        bar.classList.add('flex');
    } else {
        bar.classList.add('hidden');
        bar.classList.remove('flex');
    }
    // Sync header "select all" indeterminate / checked state.
    const allRows = document.querySelectorAll('input[data-bulk-row]');
    const allCb = document.querySelector('input[data-bulk-all]');
    if (allCb) {
        if (ids.length === 0)              { allCb.checked = false; allCb.indeterminate = false; }
        else if (ids.length === allRows.length) { allCb.checked = true; allCb.indeterminate = false; }
        else                                 { allCb.checked = false; allCb.indeterminate = true; }
    }
}

function wireBulkBar() {
    const bar = document.querySelector('[data-bulk-bar]');
    if (!bar || bar.__arBulkBound) return;
    bar.__arBulkBound = true;

    // Header "select all" checkbox flips every row.
    document.querySelectorAll('input[data-bulk-all]').forEach((cb) => {
        cb.addEventListener('change', () => {
            document.querySelectorAll('input[data-bulk-row]').forEach((row) => {
                row.checked = cb.checked;
            });
            refreshBulkBar();
        });
    });

    // Clear-selection × button.
    bar.querySelectorAll('[data-bulk-clear]').forEach((b) => {
        b.addEventListener('click', () => {
            document.querySelectorAll('input[data-bulk-row]').forEach((row) => { row.checked = false; });
            refreshBulkBar();
        });
    });

    // Activate / Deactivate / Delete — POSTs to /auto-reply/bulk.
    bar.querySelectorAll('[data-bulk-action]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const action = btn.dataset.bulkAction;
            const ids = selectedBulkIds();
            if (ids.length === 0) return;

            const verbs = { activate: 'Activate', deactivate: 'Pause', delete: 'Delete' };
            const verb = verbs[action] || action;
            // Reuse the project-wide confirmDialog (no native alerts per
            // /admin custom-modal policy). Falls back to window.confirm
            // if the global helper isn't loaded yet.
            const proceed = () => doBulk(action, ids);
            if (window.confirmDialog) {
                window.confirmDialog({
                    title: `${verb} ${ids.length} auto-replies?`,
                    message: action === 'delete'
                        ? 'This deletes the selected auto-reply rules and any uploaded attachments. Cannot be undone.'
                        : `${verb} the selected auto-reply rules.`,
                    confirmText: verb,
                    tone: action === 'delete' ? 'danger' : 'info',
                    onConfirm: proceed,
                });
            } else if (window.confirm(`${verb} ${ids.length} auto-replies?`)) {
                proceed();
            }
        });
    });
}

async function doBulk(action, ids) {
    const bar = document.querySelector('[data-bulk-bar]');
    if (!bar) return;
    const url = bar.dataset.bulkUrl;
    try {
        const r = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
            },
            body: JSON.stringify({ action, ids }),
        });
        const j = await r.json().catch(() => ({}));
        if (r.ok && j.ok) {
            if (window.toast) window.toast(`${j.touched ?? ids.length} auto-replies ${action}d.`, 'success');
            // Reload the partial so the UI reflects the change.
            const params = new URLSearchParams(window.location.search);
            const state = {
                q: params.get('q') || '',
                device: params.get('device') || 'all',
                status: params.get('status') || 'all',
                type: params.get('type') || 'all',
                view: params.get('view') || 'list',
                page: params.get('page') || '1',
            };
            fetchPartial(state, false);
        } else {
            if (window.toast) window.toast(j.message || 'Bulk action failed.', 'error');
        }
    } catch (e) {
        if (window.toast) window.toast('Network error.', 'error');
    }
}
