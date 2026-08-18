/*
 * Global toaster — drop-in replacement for the chat-only #toast div.
 * Mounts a single host container the first time anything is shown,
 * then layers toasts inside it and auto-dismisses after 3s.
 *
 * API:
 *   import { showToast } from './wa-toaster';
 *   showToast('Saved.', { variant: 'success' });
 *
 * Or, from anywhere on the page (no bundler needed):
 *   window.WaToaster.success('Saved.');
 *   window.WaToaster.error('Sync failed.');
 *   window.WaToaster.info('Loading…', { duration: 0 }); // sticky
 *
 * Server flash: any blade can drop a <meta name="wa-flash" …> tag and
 * the toaster auto-shows it on page load. See showFlashFromMeta().
 */

const HOST_ID = 'wa-toaster';

const ICONS = {
    success: '<path fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" d="M3 8.5l3 3 7-7"/>',
    error:   '<path fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" d="M8 5v4M8 11.5h.01M8 1.5l7 12H1z"/>',
    warn:    '<path fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" d="M8 5v4M8 11.5h.01M8 1.5l7 12H1z"/>',
    info:    '<path fill="none" stroke="currentColor" stroke-width="1.6" d="M8 7v5M8 4.5h.01"/><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.4"/>',
};

const PALETTE = {
    success: { border: 'border-wa-green/40',    bg: 'bg-wa-mint/70',         text: 'text-wa-deep',     iconBg: 'bg-wa-deep text-paper-0' },
    error:   { border: 'border-accent-coral/40',bg: 'bg-accent-coral/10',    text: 'text-[#A1431F]',   iconBg: 'bg-accent-coral text-paper-0' },
    warn:    { border: 'border-accent-amber/40',bg: 'bg-accent-amber/15',    text: 'text-[#7B5A14]',   iconBg: 'bg-accent-amber text-paper-0' },
    info:    { border: 'border-paper-200',      bg: 'bg-paper-0',            text: 'text-ink-800',     iconBg: 'bg-wa-deep text-paper-0' },
};

function ensureHost() {
    let host = document.getElementById(HOST_ID);
    if (host) return host;
    host = document.createElement('div');
    host.id = HOST_ID;
    host.className = 'fixed bottom-6 right-7 z-[100] flex flex-col items-end gap-2 pointer-events-none';
    document.body.appendChild(host);
    return host;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

/**
 * Show a toast.
 *   message  — string (HTML-escaped before insertion)
 *   options:
 *     variant  → 'success' | 'error' | 'warn' | 'info'  (default: 'info')
 *     duration → ms before auto-dismiss; 0 = sticky    (default: 3000)
 *     title    → optional bold heading above the message
 */
export function showToast(message, options = {}) {
    const variant  = options.variant  || 'info';
    const duration = options.duration ?? 3000;
    const palette  = PALETTE[variant] || PALETTE.info;
    const icon     = ICONS[variant]   || ICONS.info;

    const toast = document.createElement('div');
    toast.className = [
        'pointer-events-auto rounded-[14px] border shadow-soft px-3.5 py-3',
        'flex items-start gap-2.5 max-w-md min-w-[260px] transition-all duration-200',
        'opacity-0 translate-y-2',
        palette.border, palette.bg, palette.text,
    ].join(' ');

    toast.innerHTML = `
        <span class="${palette.iconBg} w-7 h-7 rounded-xl grid place-items-center shrink-0">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5">${icon}</svg>
        </span>
        <div class="flex-1 min-w-0 text-[13px] leading-snug">
            ${options.title ? `<div class="font-semibold mb-0.5">${escapeHtml(options.title)}</div>` : ''}
            <div class="${options.title ? 'opacity-90' : 'font-semibold'}">${escapeHtml(message)}</div>
        </div>
        <button class="opacity-50 hover:opacity-100 text-[14px] leading-none px-1 -mr-1" aria-label="Dismiss">×</button>
    `;

    ensureHost().appendChild(toast);
    requestAnimationFrame(() => toast.classList.remove('opacity-0', 'translate-y-2'));

    let timer;
    const dismiss = () => {
        clearTimeout(timer);
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 220);
    };
    toast.querySelector('button').addEventListener('click', dismiss);
    if (duration > 0) timer = setTimeout(dismiss, duration);

    return { dismiss };
}

/**
 * Read an optional <meta name="wa-flash" content="..."> tag and
 * surface it as a toast. Pair on the server side with:
 *
 *   <meta name="wa-flash" content='{"variant":"success","message":"Saved."}'>
 */
function showFlashFromMeta() {
    const meta = document.querySelector('meta[name="wa-flash"]');
    if (!meta) return;
    try {
        const payload = JSON.parse(meta.content || '{}');
        if (payload && payload.message) {
            showToast(payload.message, { variant: payload.variant, title: payload.title });
        }
    } catch { /* malformed — ignore */ }
}

/* Window helper so non-bundle scripts can toast too. */
if (typeof window !== 'undefined') {
    window.WaToaster = {
        show:    showToast,
        success: (m, o = {}) => showToast(m, { ...o, variant: 'success' }),
        error:   (m, o = {}) => showToast(m, { ...o, variant: 'error'   }),
        warn:    (m, o = {}) => showToast(m, { ...o, variant: 'warn'    }),
        info:    (m, o = {}) => showToast(m, { ...o, variant: 'info'    }),
    };
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showFlashFromMeta);
    } else {
        showFlashFromMeta();
    }
}
