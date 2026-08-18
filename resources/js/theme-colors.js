/**
 * Runtime access to the admin-controlled theme tokens.
 *
 * Charts and any other JS that needs a brand colour must read it from here
 * rather than hardcoding a hex. The admin recolours the app by overriding
 * `--color-*` custom properties at :root (see theme_css() in
 * app/Support/fc_helpers.php) — a literal '#25D366' in a chart config is
 * invisible to that override, which is exactly how a "navy" dashboard ends up
 * with green charts.
 *
 * Values are read from the live computed style, so they already reflect the
 * admin's override, the active data-theme, and any @media adjustments.
 *
 *   import { themeColor, themeAlpha, chartPalette } from '../theme-colors.js';
 *   borderColor: themeColor('wa-deep')
 *   backgroundColor: themeAlpha('wa-deep', 0.12)
 */

const FALLBACKS = {
    'wa-deep': '#075E54',
    'wa-teal': '#128C7E',
    'wa-green': '#25D366',
    'wa-mint': '#DCF8C6',
    'wa-bubble': '#E7FFDB',
    'paper-0': '#FBFAF6',
    'paper-50': '#F5F3EC',
    'paper-100': '#EFEBE0',
    'paper-200': '#E5DFD0',
    'ink-500': '#6B807C',
    'ink-700': '#1F4540',
    'ink-900': '#0B1F1C',
    'accent-coral': '#E87A5D',
    'accent-amber': '#E5A04E',
    'accent-plum': '#5B3D8A',
    'accent-sky': '#3E7AA1',
};

/** Effective hex for a theme token, e.g. themeColor('wa-deep'). */
export function themeColor(token) {
    const raw = getComputedStyle(document.documentElement)
        .getPropertyValue('--color-' + token)
        .trim();
    return raw || FALLBACKS[token] || '#000000';
}

/**
 * Same token as an rgba() at the given alpha — for chart fills that need to
 * sit translucently over the page. Handles #rgb and #rrggbb; anything else
 * (a named colour, an oklch(), a color-mix()) is handed to color-mix() so the
 * browser resolves it rather than us guessing.
 */
export function themeAlpha(token, alpha = 0.15) {
    const hex = themeColor(token);
    const m = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(hex);
    if (!m) return `color-mix(in srgb, ${hex} ${Math.round(alpha * 100)}%, transparent)`;

    let h = m[1];
    if (h.length === 3) h = h.split('').map((c) => c + c).join('');
    const n = parseInt(h, 16);
    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
}

/**
 * Ordered series colours for multi-dataset charts. Brand tokens first so the
 * primary series always carries the admin's colour, then the accents — which
 * are themselves admin-editable, so a fully recoloured dashboard stays
 * internally consistent instead of mixing a custom brand with stock greens.
 */
export function chartPalette() {
    return [
        themeColor('wa-deep'),
        themeColor('wa-green'),
        themeColor('accent-sky'),
        themeColor('accent-amber'),
        themeColor('accent-plum'),
        themeColor('accent-coral'),
        themeColor('wa-teal'),
    ];
}

/** Axis/grid/label colours so charts stay legible in every theme. */
export function chartInk() {
    return {
        text: themeColor('ink-500'),
        grid: themeAlpha('ink-500', 0.14),
        border: themeColor('paper-200'),
    };
}
