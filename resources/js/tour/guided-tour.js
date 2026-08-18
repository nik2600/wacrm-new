import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { themeColor } from '../theme-colors.js';

/*
 * First-run product tour (Driver.js). SINGLE-PAGE: it runs entirely on the
 * dashboard and highlights the header nav items in place (Campaigns, Flows,
 * Devices, …) plus the dashboard cards — it never navigates the user away.
 *
 * Auto-runs ONCE per user, gated by window.WADESK_TOUR.run (server flag
 * users.has_seen_intro), and POSTs to seenUrl on finish/skip so it never
 * auto-runs again. window.wadeskStartTour() / [data-tour-replay] replays it.
 * Steps whose element is missing OR hidden (e.g. nav collapsed on mobile,
 * pipeline card gated off) are skipped, so it never points at nothing.
 */

function steps() {
    // Brand name comes from the admin system setting (brand_name()),
    // surfaced on window.WADESK_BRAND.appName by the layout head. Never
    // hard-code "WaDesk" in user-facing copy — a white-labelled deploy
    // (ChatDeskly / Chatkar / …) must see its own name in the tour.
    const APP = (window.WADESK_BRAND && window.WADESK_BRAND.appName) || 'WaDesk';
    return [
        { title: 'Welcome to ' + APP, description: 'A quick 30-second look at the essentials. You can skip any time — this only shows once.' },
        { el: '[data-tour="navbar"]', title: 'Your main menu', description: 'Everything lives up here and stays with you on every page. Let’s walk through it.' },
        { el: '[data-tour="nav-metaads"]', title: 'Meta Ads', description: 'Create and track Click-to-WhatsApp ad campaigns without leaving ' + APP + '.' },
        { el: '[data-tour="nav-wa-campaigns"]', title: 'Campaigns', description: 'Send bulk messages to a chosen audience, with sending-speed controls and per-recipient delivery tracking.' },
        { el: '[data-tour="nav-flows"]', title: 'Flows', description: 'Build no-code automations — keyword replies, menus, conditions, AI steps, appointments and more.' },
        { el: '[data-tour="nav-templates"]', title: 'Templates', description: 'Create and submit WhatsApp message templates, then send them with personalised fields.' },
        { el: '[data-tour="nav-devices"]', title: 'Devices', description: 'Connect your WhatsApp numbers — QR pairing, the official Cloud API, or Twilio.' },
        { el: '[data-tour="nav-more"]', title: 'More', description: 'Contacts, Catalog, Storefront, Webhooks, Developers and every other tool live in here.' },
        { el: '[data-tour="kpi"]', title: 'Your key numbers', description: 'Messages sent, read rate, active contacts and your credit balance — at a glance for the selected period.' },
        { el: '[data-tour="new-campaign"]', title: 'Start a campaign', description: 'When you’re ready, launch a bulk message to a segment of your contacts from here.' },
        { el: '[data-tour="pipeline"]', title: 'Sales pipeline', description: 'Track deals and revenue. Open the board to manage your pipeline stage by stage. That’s the tour — enjoy ' + APP + '!' },
    ];
}

const onDashboard = () => /^\/dashboard\/?$/.test(window.location.pathname || '');
const visible = (sel) => { const el = document.querySelector(sel); return el && el.offsetParent !== null; };

function markSeen() {
    const cfg = window.WADESK_TOUR || {};
    if (!cfg.seenUrl) return;
    try {
        fetch(cfg.seenUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': cfg.csrf || '', 'Accept': 'application/json' },
            keepalive: true,
        });
    } catch (e) {}
    cfg.run = false;
}

function runTour() {
    const usable = steps().filter((s) => !s.el || visible(s.el));
    if (!usable.length) return;

    const list = usable.map((s) => {
        const popover = { title: s.title, description: s.description };
        if (s.side) popover.side = s.side;
        if (s.align) popover.align = s.align;
        // IMPORTANT: omit `element` entirely for the intro step. Passing
        // `element: undefined` makes Driver.js try (and fail) to resolve a
        // target, which dumps the popover in the top-left corner; with no
        // element key it renders as a proper centered modal.
        return s.el ? { element: s.el, popover } : { popover };
    });

    const d = driver({
        showProgress: true,
        allowClose: true,
        stagePadding: 8,
        stageRadius: 14,
        overlayColor: '#062019',
        overlayOpacity: 0.55,
        smoothScroll: true,
        popoverClass: 'wadesk-tour',
        progressText: '{{current}} of {{total}}',
        nextBtnText: 'Next',
        prevBtnText: 'Back',
        doneBtnText: 'Done',
        steps: list,
        // Fires on every exit path — Done, the X, Esc, or clicking the overlay.
        // We must call destroy() ourselves once it's defined.
        onDestroyStarted: () => {
            markSeen();
            d.destroy();
        },
    });
    d.drive();
}

window.wadeskStartTour = function () {
    if (onDashboard()) runTour();
    else window.location.assign('/dashboard?tour=1');
};

// Paper / wa-deep brand styling — matches the app's cards, pill buttons and ink palette.
function injectStyles() {
    if (document.getElementById('wadesk-tour-style')) return;
    const css = `
.driver-popover.wadesk-tour{background:#fbfaf7;color:#1b2b27;border:1px solid #e7e4da;border-radius:18px;
  box-shadow:0 24px 60px -16px rgba(6,32,25,.45),0 4px 14px -6px rgba(6,32,25,.18);
  padding:18px 18px 14px;max-width:340px;font-family:inherit;}
.driver-popover.wadesk-tour .driver-popover-title{font-family:'Fraunces',Georgia,serif;font-weight:600;
  font-size:17px;line-height:1.25;color:#062019;margin-bottom:6px;letter-spacing:-.01em;}
.driver-popover.wadesk-tour .driver-popover-description{font-size:13px;line-height:1.55;color:#46554f;}
.driver-popover.wadesk-tour .driver-popover-progress-text{font-size:10.5px;font-weight:600;letter-spacing:.14em;
  text-transform:uppercase;color:#9aa8a2;font-variant-numeric:tabular-nums;}
.driver-popover.wadesk-tour .driver-popover-footer{margin-top:12px;gap:8px;}
.driver-popover.wadesk-tour .driver-popover-footer button{border-radius:999px;font-weight:600;font-size:12.5px;
  padding:7px 16px;text-shadow:none;transition:background .15s,color .15s,border-color .15s;}
.driver-popover.wadesk-tour .driver-popover-next-btn,
.driver-popover.wadesk-tour .driver-popover-footer button.driver-popover-next-btn{background:${themeColor('wa-deep')};color:#fff;
  border:1px solid ${themeColor('wa-deep')};}
.driver-popover.wadesk-tour .driver-popover-next-btn:hover{background:${themeColor('wa-teal')};border-color:${themeColor('wa-teal')};}
.driver-popover.wadesk-tour .driver-popover-prev-btn{background:transparent;color:#46554f;border:1px solid #d8d6cf;}
.driver-popover.wadesk-tour .driver-popover-prev-btn:hover{background:#f0eee7;}
.driver-popover.wadesk-tour .driver-popover-close-btn{color:#9aa8a2;font-size:18px;width:26px;height:26px;
  border-radius:8px;transition:background .15s,color .15s;}
.driver-popover.wadesk-tour .driver-popover-close-btn:hover{background:#f0eee7;color:#46554f;}
.driver-popover.wadesk-tour .driver-popover-arrow{border-color:#fbfaf7;}`;
    const el = document.createElement('style');
    el.id = 'wadesk-tour-style';
    el.textContent = css;
    document.head.appendChild(el);
}

document.addEventListener('DOMContentLoaded', () => {
    injectStyles();
    document.querySelectorAll('[data-tour-replay]').forEach((el) =>
        el.addEventListener('click', (e) => { e.preventDefault(); window.wadeskStartTour(); }));

    const cfg = window.WADESK_TOUR || {};
    const forced = /[?&]tour=1\b/.test(window.location.search);   // replay redirect
    if (!onDashboard()) return;
    if (!cfg.run && !forced) return;

    // Drop ?tour=1 from the URL so a later reload doesn't re-trigger a replay.
    if (forced && window.history?.replaceState) {
        const clean = window.location.pathname + window.location.hash;
        window.history.replaceState({}, '', clean);
    }

    // Let cards/charts paint, then run.
    setTimeout(runTour, 650);
});
