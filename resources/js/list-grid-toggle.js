const interactiveSelector = 'button, input, select, textarea, form, [data-modal-open], [role="button"]';

function cleanClone(node) {
    node.querySelectorAll('[id]').forEach((el) => el.removeAttribute('id'));
    return node;
}

function cloneCellContent(cell, className = '') {
    const wrap = document.createElement('div');
    wrap.className = className;
    Array.from(cell.childNodes).forEach((child) => {
        wrap.appendChild(child.cloneNode(true));
    });
    cleanClone(wrap);
    return wrap;
}

function textOf(node) {
    return (node?.textContent || '').replace(/\s+/g, ' ').trim();
}

function headerLabels(table) {
    return Array.from(table?.querySelectorAll('thead th') || []).map((th) => textOf(th));
}

function directRows(source) {
    if (!source) return [];
    if (source.tagName === 'TBODY') return Array.from(source.children).filter((el) => el.tagName === 'TR');
    const tbody = source.querySelector('tbody');
    if (tbody) return Array.from(tbody.children).filter((el) => el.tagName === 'TR');
    return Array.from(source.children).filter((el) => !el.matches('[data-list-grid-ignore]'));
}

function cellsFor(row) {
    if (row.tagName === 'TR') return Array.from(row.children).filter((el) => el.matches('td, th'));
    return Array.from(row.children);
}

function emptyStateFor(row) {
    if (row.matches?.('[data-list-grid-empty]')) return row;
    return row.querySelector?.('[data-list-grid-empty]') || null;
}

function buildEmptyStateCard(row) {
    const source = emptyStateFor(row);
    const clone = source.cloneNode(true);
    cleanClone(clone);
    clone.classList.remove('hidden');
    clone.classList.add('col-span-full');
    return clone;
}

function actionCellIndex(cells, labels) {
    let found = -1;
    cells.forEach((cell, index) => {
        const label = (labels[index] || '').toLowerCase();
        if (label === '' || label.includes('action') || cell.querySelector('button, a, form')) found = index;
    });
    return found;
}

function proxyActionClones(container, clone, originCell) {
    const cloneItems = [
        ...(clone.matches?.(interactiveSelector) ? [clone] : []),
        ...Array.from(clone.querySelectorAll(interactiveSelector)),
    ];
    const originItems = [
        ...(originCell.matches?.(interactiveSelector) ? [originCell] : []),
        ...Array.from(originCell.querySelectorAll(interactiveSelector)),
    ];
    cloneItems.forEach((el, index) => {
        const origin = originItems[index];
        if (!origin) return;
        const proxyIndex = container.__listGridProxies.length;
        container.__listGridProxies.push(origin);
        el.dataset.listGridProxy = String(proxyIndex);
    });
}

function buildTableCard(container, row, labels, rowIndex) {
    if (emptyStateFor(row)) return buildEmptyStateCard(row);

    const cells = cellsFor(row);
    const actionIndex = actionCellIndex(cells, labels);
    const dataCells = cells
        .map((cell, index) => ({ cell, index, label: labels[index] || '' }))
        .filter(({ cell, index, label }) => {
            if (index === actionIndex) return false;
            if (label === '' && cell.querySelector('input[type="checkbox"]')) return false;
            return textOf(cell) !== '' || cell.children.length > 0;
        });

    if (dataCells.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'lg:col-span-2 2xl:col-span-3 border border-dashed border-paper-200 rounded-2xl bg-paper-0 py-10 px-4 text-center text-[13px] text-ink-500';
        empty.textContent = textOf(row) || 'No rows to show.';
        return empty;
    }

    const card = document.createElement('article');
    card.className = 'bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card hover:border-wa-deep/40 transition';
    card.dataset.listGridCard = String(rowIndex);
    const rowProxyIndex = container.__listGridRowProxies.length;
    container.__listGridRowProxies.push(row);
    card.dataset.listGridRowProxy = String(rowProxyIndex);

    const titleWrap = document.createElement('div');
    titleWrap.className = 'flex items-start justify-between gap-3';
    const title = document.createElement('div');
    title.className = 'min-w-0 flex-1';
    const titleContent = cloneCellContent(dataCells[0].cell, 'min-w-0');
    title.appendChild(titleContent);
    titleWrap.appendChild(title);

    if (actionIndex >= 0 && cells[actionIndex]) {
        const actions = cloneCellContent(cells[actionIndex], 'shrink-0 flex items-center justify-end gap-1');
        actions.className = 'shrink-0 flex items-center justify-end gap-1';
        proxyActionClones(container, actions, cells[actionIndex]);
        titleWrap.appendChild(actions);
    }
    card.appendChild(titleWrap);

    if (dataCells.length > 1) {
        const grid = document.createElement('div');
        grid.className = 'mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-[12px]';
        dataCells.slice(1).forEach(({ cell, label }) => {
            const item = document.createElement('div');
            const labelNode = document.createElement('div');
            labelNode.className = 'font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-1';
            labelNode.textContent = label || 'Detail';
            const value = cloneCellContent(cell, 'text-ink-800 min-w-0 [&_*]:max-w-full');
            item.appendChild(labelNode);
            item.appendChild(value);
            grid.appendChild(item);
        });
        card.appendChild(grid);
    }

    return card;
}

function renderGrid(container) {
    const list = container.querySelector('[data-list-grid-list]');
    const grid = container.querySelector('[data-list-grid-grid]');
    const source = container.querySelector('[data-list-grid-source]') || list;
    if (!list || !grid || !source) return;

    const table = source.matches('table') ? source : source.querySelector('table');
    const labels = table ? headerLabels(table) : (source.dataset.listGridLabels || '').split(',').map((s) => s.trim());
    const rows = directRows(source).filter((row) => !row.classList.contains('hidden'));

    container.__listGridProxies = [];
    container.__listGridRowProxies = [];
    grid.innerHTML = '';
    const wrap = document.createElement('div');
    wrap.className = 'grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-3';
    rows.forEach((row, index) => wrap.appendChild(buildTableCard(container, row, labels, index)));
    grid.appendChild(wrap);
}

function paint(container, view) {
    const list = container.querySelector('[data-list-grid-list]');
    const grid = container.querySelector('[data-list-grid-grid]');
    const next = view === 'grid' ? 'grid' : 'list';
    container.dataset.listGridView = next;

    if (next === 'grid') {
        renderGrid(container);
        list?.classList.add('hidden');
        grid?.classList.remove('hidden');
    } else {
        grid?.classList.add('hidden');
        list?.classList.remove('hidden');
    }

    const key = container.dataset.listGridKey;
    document.querySelectorAll('[data-list-grid-toggle]').forEach((button) => {
        const target = button.dataset.listGridTarget;
        if (target && target !== key) return;
        if (!target && !container.contains(button)) return;
        const active = button.dataset.listGridToggle === next;
        button.classList.toggle('bg-wa-deep', active);
        button.classList.toggle('text-paper-0', active);
        button.classList.toggle('text-ink-600', !active);
        button.classList.toggle('hover:bg-paper-50', !active);
    });
}

function wire(container) {
    if (container.__listGridWired) return;
    container.__listGridWired = true;
    const key = container.dataset.listGridKey;
    const saved = key ? localStorage.getItem(`wadesk:list-grid:${key}`) : null;
    paint(container, saved || container.dataset.listGridView || 'list');

    container.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-list-grid-toggle]:not([data-list-grid-target])');
        if (toggle && container.contains(toggle)) {
            event.preventDefault();
            const view = toggle.dataset.listGridToggle || 'list';
            if (key) localStorage.setItem(`wadesk:list-grid:${key}`, view);
            paint(container, view);
            return;
        }

        const proxy = event.target.closest('[data-list-grid-proxy]');
        if (proxy && container.querySelector('[data-list-grid-grid]')?.contains(proxy)) {
            const origin = container.__listGridProxies?.[Number(proxy.dataset.listGridProxy)];
            if (origin) {
                event.preventDefault();
                origin.click();
            }
            return;
        }

        const card = event.target.closest('[data-list-grid-row-proxy]');
        if (card && container.querySelector('[data-list-grid-grid]')?.contains(card)) {
            if (event.target.closest('a, button, input, select, textarea, form, label')) return;
            const origin = container.__listGridRowProxies?.[Number(card.dataset.listGridRowProxy)];
            origin?.click?.();
        }
    });

    container.addEventListener('submit', (event) => {
        const proxy = event.target.closest('[data-list-grid-proxy]');
        if (proxy) {
            const origin = container.__listGridProxies?.[Number(proxy.dataset.listGridProxy)];
            if (origin?.requestSubmit) {
                event.preventDefault();
                origin.requestSubmit();
            }
        }
    });

    const source = container.querySelector('[data-list-grid-source]') || container.querySelector('[data-list-grid-list]');
    if (source) {
        const observer = new MutationObserver(() => {
            if ((container.dataset.listGridView || 'list') === 'grid') renderGrid(container);
        });
        observer.observe(source, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style'] });
    }
}

export default function initListGridToggles(root = document) {
    root.querySelectorAll('[data-list-grid]').forEach(wire);
    if (document.__listGridGlobalWired) return;
    document.__listGridGlobalWired = true;
    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-list-grid-toggle][data-list-grid-target]');
        if (!toggle) return;
        const container = document.querySelector(`[data-list-grid][data-list-grid-key="${CSS.escape(toggle.dataset.listGridTarget)}"]`);
        if (!container) return;
        event.preventDefault();
        const view = toggle.dataset.listGridToggle || 'list';
        localStorage.setItem(`wadesk:list-grid:${container.dataset.listGridKey}`, view);
        paint(container, view);
    });
}
