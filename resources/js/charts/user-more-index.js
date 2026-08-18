export default function init() {
    initRotatingTips();
    initShortcutCustomizer();
    initToolTabs();
}

// ── Category tab filter for the tools grid ──
// The tabs (All / Automation / Data / Help) filter the tool cards. Each
// card is categorised by its destination path, so no per-card markup is
// needed — add a route here to slot a new tool into a tab.
function initToolTabs() {
    const tabs = Array.from(document.querySelectorAll('[data-more-tab]'));
    const grid = document.querySelector('[data-more-tools]');
    if (!tabs.length || !grid) return;

    const CAT = {
        // automation
        'auto-reply': 'automation', 'auto-replies': 'automation', 'scheduled': 'automation',
        'broadcasts': 'automation', 'campaigns': 'automation', 'flows': 'automation',
        'ai-assistants': 'automation', 'ai-training': 'automation', 'chatbot-widgets': 'automation',
        'wa-forms': 'automation', 'webhooks': 'automation', 'integrations': 'automation',
        'google-account': 'automation', 'call-logs': 'automation', 'templates': 'automation',
        'meta-ads': 'automation', 'wa-links': 'automation',
        // data
        'analytics': 'data', 'contacts': 'data', 'attributes': 'data', 'message-history': 'data',
        'activity-log': 'data', 'devices': 'data', 'affiliate-history': 'data', 'notifications': 'data',
        // help
        'support': 'help', 'guidebook': 'help',
        // everything else (account, pricing, team-inbox, chat, …) → shown only on "All"
    };

    const cards = Array.from(grid.querySelectorAll(':scope > a'));
    cards.forEach((c) => {
        const href = (c.getAttribute('href') || '').replace(/^https?:\/\/[^/]+/, '').replace(/[?#].*$/, '');
        const seg = href.split('/').filter(Boolean)[0] || '';
        c.dataset.moreCat = CAT[seg] || 'other';
    });

    function activate(tab) {
        tabs.forEach((t) => {
            const on = t.dataset.moreTab === tab;
            t.classList.toggle('bg-wa-deep', on);
            t.classList.toggle('text-paper-0', on);
            t.classList.toggle('hover:bg-paper-100', !on);
        });
        cards.forEach((c) => {
            const show = tab === 'all' || c.dataset.moreCat === tab;
            c.style.display = show ? '' : 'none';
        });
    }

    tabs.forEach((t) => t.addEventListener('click', () => activate(t.dataset.moreTab)));
    activate('all');
}

function initRotatingTips() {
    const title = document.getElementById('tip-title');
    const body = document.getElementById('tip-body');
    const cta = document.getElementById('tip-cta');
    const link = document.getElementById('tip-link');
    const dots = document.getElementById('tip-dots');

    if (!title || !body || !cta || !link || !dots) return;

    const tips = [
        {
            title: 'Save canned replies as templates',
            body: 'Type "/" in the team inbox composer to insert a saved reply in one tap. Cuts handle time by about 30%.',
            cta: 'Manage templates ->',
            href: '/templates',
        },
        {
            title: 'Tag VIP customers automatically',
            body: 'Add a Tag-contact node to your welcome flow so big spenders auto-tag as VIP, making broadcasts easier later.',
            cta: 'Open flow builder ->',
            href: '/flows/builder',
        },
        {
            title: 'Use Ctrl K to jump anywhere',
            body: 'Hit Ctrl K to fuzzy-search every page, contact, and setting from the global search box.',
            cta: 'Try it now',
            href: '#',
        },
        {
            title: "Schedule sends in the contact's timezone",
            body: 'Pick recipient timezone when scheduling a campaign so your message arrives at 10am for everyone.',
            cta: 'New scheduled ->',
            href: '/scheduled/create',
        },
        {
            title: 'Auto top-up the wallet',
            body: 'Set a threshold so message credits refill automatically before a broadcast runs out.',
            cta: 'Configure auto top-up ->',
            href: '/account?tab=wallet',
        },
    ];

    let tipIdx = 0;

    function renderTip() {
        const tip = tips[tipIdx];
        title.textContent = tip.title;
        body.textContent = tip.body;
        cta.textContent = tip.cta;
        link.href = tip.href;
        dots.innerHTML = tips.map((_, index) => `
            <button type="button" data-tip-index="${index}" class="w-1.5 h-1.5 rounded-full ${index === tipIdx ? 'bg-wa-deep' : 'bg-paper-200'}" aria-label="Tip ${index + 1}"></button>
        `).join('');
    }

    dots.addEventListener('click', (event) => {
        const button = event.target.closest('[data-tip-index]');
        if (!button) return;
        tipIdx = Number(button.dataset.tipIndex);
        renderTip();
    });

    renderTip();
    window.setInterval(() => {
        tipIdx = (tipIdx + 1) % tips.length;
        renderTip();
    }, 7000);
}

function initShortcutCustomizer() {
    const grid = document.getElementById('more-shortcuts-grid');
    const openButton = document.getElementById('more-shortcuts-customise');
    const modal = document.getElementById('more-shortcuts-modal');
    const closeButton = document.getElementById('more-shortcuts-close');
    const cancelButton = document.getElementById('more-shortcuts-cancel');
    const saveButton = document.getElementById('more-shortcuts-save');
    const resetButton = document.getElementById('more-shortcuts-reset');
    const editorList = document.getElementById('more-shortcut-editor-list');
    const emptyMessage = document.getElementById('more-shortcut-empty');
    const addSelect = document.getElementById('more-shortcut-add-select');
    const addPresetButton = document.getElementById('more-shortcut-add-btn');
    const customTitle = document.getElementById('more-shortcut-custom-title');
    const customSubtitle = document.getElementById('more-shortcut-custom-subtitle');
    const customHref = document.getElementById('more-shortcut-custom-href');
    const customIcon = document.getElementById('more-shortcut-custom-icon');
    const addCustomButton = document.getElementById('more-shortcut-add-custom');

    if (!grid || !openButton || !modal || !editorList || !addSelect) return;

    // Scope the storage key by workspace so a teammate switching
    // between workspaces sees a clean shortcut set per tenant.
    // The grid element carries the current workspace id from the blade.
    const wsId = grid.dataset.workspaceId || '0';
    const storageKey = `wadesk:more-page-shortcuts:v1:ws${wsId}`;
    let currentShortcuts = loadShortcuts(storageKey);
    let draftShortcuts = [];

    renderShortcutGrid(grid, currentShortcuts);

    openButton.addEventListener('click', () => {
        draftShortcuts = currentShortcuts.map(cloneShortcut);
        renderEditor();
        openModal(modal);
    });

    [closeButton, cancelButton].forEach((button) => {
        button?.addEventListener('click', () => closeModal(modal));
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal(modal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) closeModal(modal);
    });

    editorList.addEventListener('click', (event) => {
        const button = event.target.closest('[data-shortcut-action]');
        if (!button) return;

        const index = Number(button.dataset.shortcutIndex);
        if (!Number.isInteger(index) || index < 0 || index >= draftShortcuts.length) return;

        if (button.dataset.shortcutAction === 'up' && index > 0) {
            [draftShortcuts[index - 1], draftShortcuts[index]] = [draftShortcuts[index], draftShortcuts[index - 1]];
        }

        if (button.dataset.shortcutAction === 'down' && index < draftShortcuts.length - 1) {
            [draftShortcuts[index + 1], draftShortcuts[index]] = [draftShortcuts[index], draftShortcuts[index + 1]];
        }

        if (button.dataset.shortcutAction === 'remove') {
            draftShortcuts.splice(index, 1);
        }

        renderEditor();
    });

    addPresetButton?.addEventListener('click', () => {
        const shortcut = shortcutCatalog.find((item) => item.id === addSelect.value);
        if (!shortcut) {
            notify('Choose a shortcut to add.', 'error');
            return;
        }

        if (draftShortcuts.some((item) => item.id === shortcut.id)) {
            notify('That shortcut is already added.', 'error');
            return;
        }

        draftShortcuts.push(cloneShortcut(shortcut));
        addSelect.value = '';
        renderEditor();
    });

    addCustomButton?.addEventListener('click', () => {
        const title = customTitle?.value.trim() || '';
        const hrefRaw = customHref?.value.trim() || '';

        if (!title || !hrefRaw) {
            notify('Add a title and URL for the custom shortcut.', 'error');
            return;
        }

        draftShortcuts.push({
            id: `custom-${Date.now()}`,
            title,
            subtitle: customSubtitle?.value.trim() || 'Custom shortcut',
            href: normaliseHref(hrefRaw),
            icon: customIcon?.value || 'link',
            bg: 'bg-paper-100',
            fg: 'text-ink-700',
            custom: true,
        });

        if (customTitle) customTitle.value = '';
        if (customSubtitle) customSubtitle.value = '';
        if (customHref) customHref.value = '';
        if (customIcon) customIcon.value = 'link';

        renderEditor();
    });

    resetButton?.addEventListener('click', () => {
        draftShortcuts = defaultShortcuts();
        renderEditor();
        notify('Default shortcuts loaded. Save changes to keep them.', 'info');
    });

    saveButton?.addEventListener('click', () => {
        currentShortcuts = draftShortcuts.map(cloneShortcut);
        saveShortcuts(storageKey, currentShortcuts);
        renderShortcutGrid(grid, currentShortcuts);
        closeModal(modal);
        notify('Shortcuts updated.', 'success');
    });

    function renderEditor() {
        editorList.innerHTML = draftShortcuts.map((shortcut, index) => renderEditorRow(shortcut, index, draftShortcuts.length)).join('');
        if (emptyMessage) emptyMessage.classList.toggle('hidden', draftShortcuts.length > 0);
        renderPresetOptions(addSelect, addPresetButton, draftShortcuts);
    }
}

const defaultShortcutIds = [
    'auto-reply-create',
    'scheduled-create',
    'webhooks',
    'message-history',
    'support',
];

const shortcutCatalog = [
    {
        id: 'auto-reply-create',
        title: 'New auto reply',
        subtitle: 'Set up a keyword trigger',
        href: '/auto-reply/create',
        icon: 'plus',
        bg: 'bg-wa-mint',
        fg: 'text-wa-deep',
    },
    {
        id: 'scheduled-create',
        title: 'Schedule send',
        subtitle: 'Pick time + segment',
        href: '/scheduled/create',
        icon: 'clock',
        bg: 'bg-[#D9E5F2]',
        fg: 'text-[#13478A]',
    },
    {
        id: 'webhooks',
        title: 'Test webhook',
        subtitle: 'Fire a sample payload',
        href: '/webhooks',
        icon: 'pulse',
        bg: 'bg-[#E8F5E9]',
        fg: 'text-wa-deep',
    },
    {
        id: 'message-history',
        title: 'Export history',
        subtitle: 'CSV / last 30 days',
        href: '/message-history',
        icon: 'archive',
        bg: 'bg-paper-100',
        fg: 'text-ink-700',
    },
    {
        id: 'support',
        title: 'Contact support',
        subtitle: 'Avg reply 2h 14m',
        href: '/support',
        icon: 'help',
        bg: 'bg-accent-coral/15',
        fg: 'text-[#A1431F]',
    },
    {
        id: 'broadcasts-create',
        title: 'New broadcast',
        subtitle: 'Send a broadcast queue',
        href: '/broadcasts/create',
        icon: 'megaphone',
        bg: 'bg-wa-mint',
        fg: 'text-wa-deep',
    },
    {
        id: 'contacts',
        title: 'Open contacts',
        subtitle: 'Manage people + groups',
        href: '/contacts',
        icon: 'users',
        bg: 'bg-[#E8F5E9]',
        fg: 'text-wa-deep',
    },
    {
        id: 'devices',
        title: 'Pair device',
        subtitle: 'Connect WhatsApp number',
        href: '/devices',
        icon: 'phone',
        bg: 'bg-[#D9E5F2]',
        fg: 'text-[#13478A]',
    },
    {
        id: 'team-inbox',
        title: 'Team inbox',
        subtitle: 'Open shared inbox',
        href: '/team-inbox',
        icon: 'inbox',
        bg: 'bg-wa-deep',
        fg: 'text-paper-0',
    },
    {
        id: 'flows',
        title: 'Create flow',
        subtitle: 'Build automation',
        href: '/flows',
        icon: 'flow',
        bg: 'bg-[#F3E9FF]',
        fg: 'text-[#5B3D8A]',
    },
    {
        id: 'templates-create',
        title: 'New template',
        subtitle: 'Submit Meta template',
        href: '/templates/create',
        icon: 'template',
        bg: 'bg-paper-100',
        fg: 'text-ink-700',
    },
];

function renderShortcutGrid(grid, shortcuts) {
    if (!shortcuts.length) {
        grid.innerHTML = `
            <div class="col-span-full rounded-[12px] border border-dashed border-paper-200 bg-paper-50 px-4 py-8 text-center">
                <div class="font-serif text-[20px] leading-tight">No shortcuts selected</div>
                <p class="mt-1 text-[12px] text-ink-500">Click Customise to add quick actions back.</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = shortcuts.map(renderShortcutCard).join('');
}

function renderShortcutCard(shortcut) {
    const href = escapeHtml(normaliseHref(shortcut.href));
    const bg = escapeHtml(shortcut.bg || 'bg-paper-100');
    const fg = escapeHtml(shortcut.fg || 'text-ink-700');

    return `
        <a href="${href}" class="border border-paper-200 rounded-[10px] p-3 hover:border-wa-deep hover:bg-paper-50 transition flex items-start gap-2.5 min-w-0">
            <span class="w-8 h-8 rounded-lg ${bg} ${fg} grid place-items-center shrink-0">
                ${iconSvg(shortcut.icon)}
            </span>
            <span class="min-w-0">
                <span class="block text-[12px] font-semibold leading-tight truncate">${escapeHtml(shortcut.title)}</span>
                <span class="block text-[10.5px] text-ink-500 mt-0.5 leading-snug line-clamp-2">${escapeHtml(shortcut.subtitle)}</span>
            </span>
        </a>
    `;
}

function renderEditorRow(shortcut, index, total) {
    const bg = escapeHtml(shortcut.bg || 'bg-paper-100');
    const fg = escapeHtml(shortcut.fg || 'text-ink-700');
    const isFirst = index === 0;
    const isLast = index === total - 1;

    return `
        <div class="flex items-center gap-3 rounded-[12px] border border-paper-200 bg-paper-0 p-3">
            <span class="w-9 h-9 rounded-lg ${bg} ${fg} grid place-items-center shrink-0">
                ${iconSvg(shortcut.icon)}
            </span>
            <div class="min-w-0 flex-1">
                <div class="text-[13px] font-semibold leading-tight truncate">${escapeHtml(shortcut.title)}</div>
                <div class="text-[11px] text-ink-500 mt-0.5 truncate">${escapeHtml(shortcut.subtitle)}</div>
                <div class="text-[10px] text-ink-400 mt-0.5 truncate">${escapeHtml(normaliseHref(shortcut.href))}</div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
                <button type="button" data-shortcut-action="up" data-shortcut-index="${index}" ${isFirst ? 'disabled' : ''} class="w-8 h-8 rounded-full border border-paper-200 grid place-items-center hover:border-wa-deep hover:text-wa-deep disabled:opacity-35 disabled:pointer-events-none transition" title="Move up">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 12V4M4.5 7.5L8 4l3.5 3.5"/></svg>
                </button>
                <button type="button" data-shortcut-action="down" data-shortcut-index="${index}" ${isLast ? 'disabled' : ''} class="w-8 h-8 rounded-full border border-paper-200 grid place-items-center hover:border-wa-deep hover:text-wa-deep disabled:opacity-35 disabled:pointer-events-none transition" title="Move down">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 4v8M4.5 8.5L8 12l3.5-3.5"/></svg>
                </button>
                <button type="button" data-shortcut-action="remove" data-shortcut-index="${index}" class="w-8 h-8 rounded-full border border-paper-200 grid place-items-center hover:border-red-300 hover:text-red-600 transition" title="Remove">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                </button>
            </div>
        </div>
    `;
}

function renderPresetOptions(select, button, draftShortcuts) {
    const selectedPresetIds = new Set(draftShortcuts.filter((item) => !item.custom).map((item) => item.id));
    const available = shortcutCatalog.filter((item) => !selectedPresetIds.has(item.id));

    select.innerHTML = [
        `<option value="">${available.length ? 'Choose shortcut' : 'All presets added'}</option>`,
        ...available.map((item) => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.title)}</option>`),
    ].join('');

    if (button) {
        button.disabled = available.length === 0;
        button.classList.toggle('opacity-50', available.length === 0);
        button.classList.toggle('pointer-events-none', available.length === 0);
    }
}

function openModal(modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
}

function closeModal(modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('overflow-hidden');
}

function loadShortcuts(storageKey) {
    try {
        const raw = window.localStorage.getItem(storageKey);
        if (!raw) return defaultShortcuts();

        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) return defaultShortcuts();

        const hydrated = parsed
            .map(hydrateShortcut)
            .filter(Boolean);

        return hydrated;
    } catch (error) {
        return defaultShortcuts();
    }
}

function saveShortcuts(storageKey, shortcuts) {
    try {
        window.localStorage.setItem(storageKey, JSON.stringify(shortcuts.map(cloneShortcut)));
    } catch (error) {
        notify('Shortcuts saved for this page, but browser storage is blocked.', 'error');
    }
}

function defaultShortcuts() {
    return defaultShortcutIds
        .map((id) => shortcutCatalog.find((item) => item.id === id))
        .filter(Boolean)
        .map(cloneShortcut);
}

function hydrateShortcut(shortcut) {
    if (!shortcut || typeof shortcut !== 'object') return null;

    const preset = shortcutCatalog.find((item) => item.id === shortcut.id);
    if (preset && !shortcut.custom) return cloneShortcut(preset);

    const title = String(shortcut.title || '').trim();
    const href = normaliseHref(shortcut.href);

    if (!title || href === '#') return null;

    return {
        id: String(shortcut.id || `custom-${Date.now()}`),
        title,
        subtitle: String(shortcut.subtitle || 'Custom shortcut').trim(),
        href,
        icon: knownIcon(shortcut.icon) ? shortcut.icon : 'link',
        bg: 'bg-paper-100',
        fg: 'text-ink-700',
        custom: true,
    };
}

function cloneShortcut(shortcut) {
    return {
        id: shortcut.id,
        title: shortcut.title,
        subtitle: shortcut.subtitle,
        href: normaliseHref(shortcut.href),
        icon: knownIcon(shortcut.icon) ? shortcut.icon : 'link',
        bg: shortcut.bg || 'bg-paper-100',
        fg: shortcut.fg || 'text-ink-700',
        custom: Boolean(shortcut.custom),
    };
}

function normaliseHref(value) {
    const href = String(value || '').trim();
    if (!href) return '#';
    if (href.startsWith('//')) return `/${href.replace(/^\/+/, '')}`;
    if (/^(https?:|mailto:|tel:|\/|#)/i.test(href)) return href;
    return `/${href.replace(/^\/+/, '')}`;
}

function notify(message, type = 'info') {
    if (window.WaToaster?.[type]) {
        window.WaToaster[type](message);
        return;
    }

    if (window.toast) {
        window.toast(message, type === 'error' ? 'error' : 'success');
    }
}

function escapeHtml(value) {
    const lookup = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    };

    return String(value ?? '').replace(/[&<>"']/g, (char) => lookup[char]);
}

function knownIcon(icon) {
    return Object.prototype.hasOwnProperty.call(iconMap, icon);
}

function iconSvg(icon) {
    return iconMap[icon] || iconMap.link;
}

const iconMap = {
    plus: '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 3v10M3 8h10"/></svg>',
    clock: '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="5.5"/><path d="M8 5v3l2 2"/></svg>',
    pulse: '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 8h3l1.5-4 2 8 1.5-4h2"/></svg>',
    archive: '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h10v3H3zM3 9h10v3H3z"/></svg>',
    help: '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="5.5"/><path d="M5.5 6.5a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M8 12.5h.01"/></svg>',
    megaphone: '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 7h3l6-3v8L6 9H3zM6 9v3"/></svg>',
    users: '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="6" cy="6" r="2.2"/><path d="M2.8 13a3.2 3.2 0 0 1 6.4 0M10.5 6.5a2 2 0 1 0 0-4M10.8 10.2A3 3 0 0 1 13.5 13"/></svg>',
    phone: '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="2" width="6" height="12" rx="1.5"/><path d="M7.3 11.8h1.4"/></svg>',
    inbox: '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h10v8H3z"/><path d="M3 9h3l1 2h2l1-2h3"/></svg>',
    flow: '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="4" cy="4" r="1.8"/><circle cx="12" cy="4" r="1.8"/><circle cx="8" cy="12" r="1.8"/><path d="M5.8 4h4.4M4.8 5.5 7 10M11.2 5.5 9 10"/></svg>',
    template: '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="10" height="10" rx="1.5"/><path d="M3 6h10M6 9h4"/></svg>',
    link: '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6.5 5.5 8 4a3 3 0 1 1 4 4l-1.5 1.5M9.5 10.5 8 12a3 3 0 1 1-4-4l1.5-1.5M6.5 9.5l3-3"/></svg>',
};
