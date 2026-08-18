/* WaDesk shared bootstrap.
   - Tailwind theme tokens (single source of truth)
   - Global header behaviour: profile dropdown, search modal, settings link
   The canonical <header> markup is inlined into every page; this file
   attaches the interactive bits so every page works the same way. */

import { themeColor, themeAlpha } from './theme-colors.js';

/* ── Active currency symbol, exposed for chart formatters ───────
   Read from the <meta name="currency-symbol"> the layout prints from
   FormatSettings::symbol(). Charts use (window.WA_CURRENCY || '$') so
   axes/tooltips follow the admin's default_currency instead of a
   hardcoded dollar sign. */
window.WA_CURRENCY = (function () {
  try { return document.querySelector('meta[name="currency-symbol"]')?.content || '$'; }
  catch (e) { return '$'; }
})();

/* ── 0. Apply saved theme as early as possible (before paint) ──── */
(function applySavedTheme() {
  try {
    // The server renders the SAVED theme (user.theme_preference, set by
    // Settings → Appearance) onto <body data-theme>. That DB value is the
    // source of truth — localStorage/cookie are only pre-paint caches. Prefer
    // the server value so the Appearance picker actually sticks on reload;
    // fall back to the cache only if <body> isn't parsed yet.
    var serverTheme = (document.body && document.body.getAttribute('data-theme')) || '';
    var t = serverTheme || localStorage.getItem('wa-theme') || 'paper';
    if (t !== 'paper') document.documentElement.setAttribute('data-theme', t);
    else document.documentElement.removeAttribute('data-theme');
    // Keep the caches in sync so the next early paint + the server-rendered
    // brand logo both match the saved theme.
    try { localStorage.setItem('wa-theme', t); } catch (e) {}
    document.cookie = 'wa-theme=' + t + '; path=/; max-age=31536000; SameSite=Lax';
  } catch (e) {}
})();

(function () {
  /* Tailwind theme tokens are configured via @theme in resources/css/app.css. */

  /* ── Sub-folder base ("/public" or "") ───────────────────────
     Every link below is built in JS, so it never passes through Laravel's
     url()/route(). wurl() prefixes the deploy sub-folder (read from
     <meta name="app-base">, also exposed as window.appBase by base-url.js)
     so the dropdown, search palette and redirects work under /public — or at
     the domain root (base = "" → no change). */
  const B = (function () {
    let b = window.appBase;
    if (b == null) {
      try { b = document.querySelector('meta[name="app-base"]')?.getAttribute('content') || ''; }
      catch (e) { b = ''; }
    }
    return String(b).replace(/\/+$/, '');
  })();
  const wurl = function (p) {
    p = String(p == null ? '' : p);
    if (p === '' || p.charAt(0) === '#') return p;          // anchors / empty
    if (/^[a-z]+:\/\//i.test(p) || p.indexOf('//') === 0) return p; // absolute
    if (p.charAt(0) !== '/') return p;                       // relative — leave
    if (B && (p === B || p.indexOf(B + '/') === 0)) return p; // already prefixed
    return B + p;
  };

  /* ── Quick-search index (page-level results) ─────────────── */
  // title/desc/kw are all searched (kw = hidden synonyms so e.g. "blast",
  // "bot", "booking", "qr" surface the right page). Keep hrefs in sync with
  // the real user routes.
  const SEARCH_INDEX = [
    { title:'Dashboard',         desc:'Operator dashboard',                         href:'/dashboard',   tag:'page'    },
    // ── Messaging ──
    { title:'Chat',              desc:'WhatsApp inbox',                             href:'/chat',        tag:'page', kw:'inbox conversations messages' },
    { title:'Team Inbox',        desc:'Shared inbox · assign · notes',              href:'/team-inbox',  tag:'page', kw:'tickets agents assign' },
    { title:'Sales Pipeline',    desc:'Deal CRM · Kanban board',                    href:'/deals',       tag:'page', kw:'deals crm pipeline opportunities kanban leads sales' },
    { title:'Team Chat',         desc:'Internal team channels',                     href:'/team-chat',   tag:'page', kw:'staff slack channels' },
    { title:'Broadcasts',        desc:'Bulk WhatsApp broadcast send',               href:'/broadcasts',  tag:'page', kw:'broadcast blast bulk mass send' },
    { title:'WA Campaigns',      desc:'Campaign & broadcast queues',                href:'/wa-campaigns',tag:'page', kw:'broadcast drip sequence campaign' },
    { title:'Scheduled',         desc:'Queued / scheduled sends',                   href:'/scheduled',   tag:'page', kw:'queue later schedule' },
    { title:'Message history',   desc:'Searchable message archive',                 href:'/message-history', tag:'page', kw:'archive logs sent delivered'},
    { title:'Templates',         desc:'Approved WABA template library',             href:'/templates',   tag:'page', kw:'meta template hsm' },
    { title:'Auto reply',        desc:'Keyword-based auto replies',                 href:'/auto-reply',  tag:'page', kw:'keyword autoresponder bot' },
    // ── Automation & AI ──
    { title:'Flows',             desc:'Automated flow library',                     href:'/flows',       tag:'page', kw:'automation journey workflow' },
    { title:'Flow Builder',      desc:'Visual automation builder',                  href:'/flows/builder',tag:'page', kw:'no-code drag drop nodes' },
    { title:'AI Assistants',     desc:'AI chat agents & bots',                      href:'/ai-assistants',tag:'page', kw:'bot agent gpt chatbot copilot' },
    { title:'AI Training',       desc:'Train AI on your content',                   href:'/ai-training', tag:'page', kw:'knowledge base train documents' },
    { title:'Call logs',         desc:'AI voice calls · recordings · transcripts',  href:'/call-logs',   tag:'page', kw:'wa calling voice phone recording transcript' },
    { title:'Chatbot widgets',   desc:'Website chat widget · embed',                href:'/chatbot-widgets', tag:'page', kw:'website embed widget live chat' },
    { title:'WhatsApp Forms',    desc:'WhatsApp Flows lead forms',                  href:'/wa-forms',    tag:'page', kw:'flow form lead capture' },
    { title:'WhatsApp Links',    desc:'Click-to-chat short links · QR',             href:'/wa-links',    tag:'page', kw:'wa.me click to chat qr short link' },
    // ── Contacts & growth ──
    { title:'Contacts',          desc:'People, groups, imports',                    href:'/contacts',    tag:'page', kw:'people leads audience customers' },
    { title:'Attributes',        desc:'Custom contact fields',                      href:'/attributes',  tag:'page', kw:'custom fields tags variables' },
    { title:'Analytics',         desc:'Charts & exports',                           href:'/analytics',   tag:'page', kw:'reports stats export csv' },
    { title:'Meta Ads',          desc:'Click-to-WhatsApp ad campaigns',             href:'/meta-ads',    tag:'page', kw:'facebook instagram ads ctwa' },
    { title:'Meta Ads analytics', desc:'Spend, ROAS, leads, ad sets',               href:'/meta-ads/analytics', tag:'page', kw:'roas spend ads' },
    // ── Commerce ──
    { title:'Catalog',           desc:'Product catalog · WhatsApp commerce',        href:'/catalog',     tag:'page', kw:'products commerce shop catalogue' },
    { title:'Store',             desc:'WhatsApp storefront & checkout',             href:'/store',       tag:'page', kw:'storefront shop ecommerce checkout' },
    { title:'Store orders',      desc:'Orders & sales',                             href:'/store/orders',tag:'page', kw:'orders sales purchases' },
    { title:'Store products',    desc:'Manage products',                            href:'/store/products', tag:'page', kw:'products inventory items' },
    { title:'Appointments',      desc:'Bookings · Google Calendar slots',           href:'/appointments',tag:'page', kw:'booking calendar slot meeting schedule' },
    // ── Channels & integrations ──
    { title:'Devices',           desc:'Paired WhatsApp numbers',                    href:'/devices',     tag:'page', kw:'numbers pair sessions' },
    { title:'Connect a number',  desc:'Pair / link a WhatsApp number',              href:'/connect',     tag:'page', kw:'pair link qr scan device number' },
    { title:'Integrations',      desc:'Shopify · WooCommerce · Sheets · Catalog',   href:'/integrations',tag:'page', kw:'apps connect' },
    { title:'Shopify',           desc:'Shopify store sync',                         href:'/shopify',     tag:'page', kw:'store ecommerce' },
    { title:'WooCommerce',       desc:'WooCommerce store sync',                     href:'/woocommerce', tag:'page', kw:'wordpress store ecommerce' },
    { title:'HubSpot',           desc:'CRM sync · deals',                           href:'/hubspot',     tag:'page', kw:'crm deals contacts sync' },
    { title:'Google account',    desc:'Calendar · Sheets · Docs · Forms',           href:'/google-account', tag:'page', kw:'google calendar sheets docs forms oauth' },
    { title:'Webhooks',          desc:'Push events to external endpoints',          href:'/webhooks',    tag:'page', kw:'api events callback' },
    { title:'All features',      desc:'Every tool in one place',                    href:'/more',        tag:'page', kw:'more tools hub features' },
    // ── Account ──
    { title:'Account',           desc:'Profile, password, photo',                   href:'/account?tab=profile', tag:'account', kw:'profile settings me' },
    { title:'Wallet',            desc:'Top-up, balance, history',                   href:'/account?tab=wallet',  tag:'account', kw:'credits balance topup billing' },
    { title:'Order history',     desc:'Invoices and receipts',                      href:'/account?tab=orders',  tag:'account', kw:'invoices receipts payments' },
    { title:'Affiliate',         desc:'Code, referrals, commissions',               href:'/account?tab=affiliate',tag:'account', kw:'referral commission earn' },
    { title:'Support tickets',   desc:'Past tickets & live chat',                   href:'/account?tab=support', tag:'account', kw:'help ticket contact' },
    { title:'Change password',   desc:'Rotate password',                            href:'/account?tab=password',tag:'account', kw:'security password 2fa' },
    { title:'Pricing & plans',   desc:'Compare plans & upgrade',                    href:'/account/plans', tag:'plan', kw:'plan upgrade subscription billing pricing' },
    { title:'Notifications',     desc:'Email / Slack / in-app routes',              href:'/notifications', tag:'page', kw:'alerts email slack' },
    { title:'Settings',          desc:'Workspace & system settings',                href:'/settings',    tag:'system', kw:'preferences config' },
    { title:'Guidebook',         desc:'How-to articles',                            href:'/guidebook',   tag:'help', kw:'docs help knowledge how to' },
    { title:'Support',           desc:'Talk to humans',                             href:'/support',     tag:'help', kw:'help contact ticket' },
    // ── Admin (only follow if you have access) ──
    { title:'Admin Console',     desc:'Platform operations dashboard',              href:'/admin',       tag:'system'  },
    { title:'Admin Analytics',   desc:'Platform revenue, usage, health',            href:'/admin/analytics', tag:'system' },
    { title:'Admin Support',     desc:'Ticket queue, customer context, solutions',  href:'/admin/support', tag:'system' },
    { title:'Admin Security',    desc:'Login, abuse prevention, API and WhatsApp guardrails', href:'/admin/security', tag:'system' },
    { title:'Admin Audit Log',   desc:'Security, admin actions, exports',           href:'/admin/audit-log', tag:'system' },
    { title:'Admin Settings',    desc:'Project, messaging, integrations, SEO',      href:'/admin/settings', tag:'system' },
    { title:'Logout',            desc:'Sign out of this workspace',                 href:'#', logout:true, tag:'system' },
  ];

  /* ── 3. Inject overlay markup once ──────────────────────────── */
  function injectOverlays() {
    if (document.getElementById('wa-overlays')) return;
    const u = (window.WADESK_USER || {});
    const userName     = u.name     || 'Guest';
    const userEmail    = u.email    || '';
    const userInitials = u.initials || (userName.charAt(0) || 'G').toUpperCase();
    const userCredits  = Number.isFinite(u.credits) ? u.credits : 0;
    const userCreditsLabel = userCredits.toLocaleString() + ' credits';
    // Plan badge in the dropdown — fully dynamic from WADESK_USER (server
    // resolves it via Workspace::billingPackage()->pname). No hardcoding.
    const userPlanLabel = [ (u.plan || 'Free') + ' plan', u.roleLabel || '' ]
        .filter(Boolean).join(' · ');
    // Platform-admin / super-admin row in the user dropdown. Server sends
    // WADESK_USER.isAdmin = true when auth user is hasRole('Super Admin'/'Admin')
    // or has the legacy users.role = 'admin'. Rendered as the first entry
    // with an amber pill so it's obviously the "out of normal-user" door.
    const adminRowHtml = u.isAdmin ? `
      <a href="${wurl('/admin')}" class="flex items-center gap-2.5 px-4 py-2 text-[12.5px] hover:bg-accent-amber/10 bg-accent-amber/5 border-b border-paper-100">
        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-accent-amber" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 1l6 3v4c0 3.5-2.5 6-6 7-3.5-1-6-3.5-6-7V4l6-3z"/></svg>
        Admin dashboard
        <span class="ml-auto px-1.5 py-0.5 rounded-full bg-accent-amber/15 text-accent-amber text-[9px] font-mono font-bold uppercase tracking-wider">admin</span>
      </a>` : '';
    const wrap = document.createElement('div');
    wrap.id = 'wa-overlays';
    wrap.innerHTML = `
      <!-- profile dropdown -->
      <div id="wa-profile-menu" class="hidden fixed top-[58px] right-4 z-[80] w-[260px] bg-paper-0 border border-paper-200 rounded-2xl shadow-soft overflow-hidden">
        <div class="px-4 pt-4 pb-3 border-b border-paper-200">
          <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-full bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 text-[12px] font-semibold grid place-items-center">${userInitials}</span>
            <div class="min-w-0">
              <div class="font-semibold text-[13px] leading-tight truncate">${userName}</div>
              <div class="text-[11px] text-ink-500 leading-tight truncate">${userEmail}</div>
            </div>
          </div>
          <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono bg-wa-mint text-wa-deep border border-wa-green/40 mt-3">
            <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>${userPlanLabel}
          </span>
        </div>
        <nav class="py-1">
          ${adminRowHtml}
          <a href="${wurl('/account?tab=profile')}" class="flex items-center gap-2.5 px-4 py-2 text-[12.5px] hover:bg-paper-50">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="6" r="3"/><path d="M2 14c0-3 2.5-5 6-5s6 2 6 5"/></svg>
            Profile
          </a>
          <a href="${wurl('/account?tab=wallet')}" class="flex items-center gap-2.5 px-4 py-2 text-[12.5px] hover:bg-paper-50">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="4" width="12" height="9" rx="1.5"/><circle cx="11" cy="9" r="1"/></svg>
            Wallet <span class="ml-auto font-mono text-[11px] text-wa-deep">${userCreditsLabel}</span>
          </a>
          <a href="${wurl('/account?tab=orders')}" class="flex items-center gap-2.5 px-4 py-2 text-[12.5px] hover:bg-paper-50">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 4h2l1.5 8h7l1-5H6"/><circle cx="6" cy="13" r="1"/><circle cx="11" cy="13" r="1"/></svg>
            Order history
          </a>
          <a href="${wurl('/settings')}" class="flex items-center gap-2.5 px-4 py-2 text-[12.5px] hover:bg-paper-50">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="2"/><path d="M13 8a5 5 0 0 0-.1-1.1l1.4-1-1.5-2.6-1.6.7a5 5 0 0 0-1.9-1.1L9 1H7l-.3 1.9a5 5 0 0 0-1.9 1.1l-1.6-.7-1.5 2.6 1.4 1A5 5 0 0 0 3 8c0 .4 0 .7.1 1.1l-1.4 1 1.5 2.6 1.6-.7a5 5 0 0 0 1.9 1.1L7 15h2l.3-1.9a5 5 0 0 0 1.9-1.1l1.6.7 1.5-2.6-1.4-1c.1-.4.1-.7.1-1.1Z"/></svg>
            Settings
          </a>
          <a href="${wurl('/account/plans')}" class="flex items-center gap-2.5 px-4 py-2 text-[12.5px] hover:bg-paper-50">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 8l3-5 8-1-1 8-5 3z"/><circle cx="6" cy="6" r="1"/></svg>
            Pricing & plans
          </a>
          <a href="${wurl('/account?tab=support')}" class="flex items-center gap-2.5 px-4 py-2 text-[12.5px] hover:bg-paper-50">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6"/><path d="M5.5 6a2.5 2.5 0 1 1 5 0c0 2-2.5 2-2.5 4M8 12.5h.01"/></svg>
            Help & support
          </a>
          <div class="border-t border-paper-200 my-1"></div>
          <a href="#" data-logout-link class="flex items-center gap-2.5 px-4 py-2 text-[12.5px] text-accent-coral hover:bg-accent-coral/10">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 3H4v10h5M7 8h8M12 5l3 3-3 3"/></svg>
            Log out
          </a>
        </nav>
        <div class="px-4 py-2.5 bg-paper-50/40 border-t border-paper-200 text-[10.5px] text-ink-500 font-mono">${(u.appName || 'WaDesk')} · v${(u.version || '1.0.0')}</div>
      </div>

      <!-- search modal -->
      <div id="wa-search-modal" class="hidden fixed inset-0 z-[90] flex items-start justify-center pt-[12vh] px-4" style="background:rgba(11,31,28,0.32); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px);">
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-soft w-full max-w-[640px] overflow-hidden" onclick="event.stopPropagation()">
          <div class="px-4 py-3 border-b border-paper-200 flex items-center gap-3">
            <svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="7" cy="7" r="5"/><path d="m11 11 3 3"/></svg>
            <input id="wa-search-input" type="search" placeholder="Search pages, contacts, templates, settings…" class="flex-1 bg-transparent text-[15px] focus:outline-none" autocomplete="off" />
            <kbd class="px-2 py-0.5 rounded-md bg-paper-50 border border-paper-200 text-[10px] font-mono text-ink-500">Esc</kbd>
          </div>
          <div id="wa-search-results" class="max-h-[60vh] overflow-y-auto"></div>
          <div class="px-4 py-2 border-t border-paper-200 bg-paper-50/40 flex items-center justify-between text-[10.5px] font-mono text-ink-500">
            <div class="flex items-center gap-3">
              <span><kbd class="px-1.5 py-px rounded bg-paper-0 border border-paper-200">↑</kbd> <kbd class="px-1.5 py-px rounded bg-paper-0 border border-paper-200">↓</kbd> navigate</span>
              <span><kbd class="px-1.5 py-px rounded bg-paper-0 border border-paper-200">↵</kbd> open</span>
            </div>
            <span><kbd class="px-1.5 py-px rounded bg-paper-0 border border-paper-200">⌘ K</kbd> toggle</span>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(wrap);
  }

  /* ── 4. Wire interactions ──────────────────────────────────── */
  let searchSelected = 0;

  function tagColor(t) {
    return ({
      page:    'bg-wa-mint text-wa-deep',
      account: 'bg-[#FFF4E0] text-[#7B5A14]',
      plan:    'bg-[#F3E9FF] text-[#5B3D8A]',
      help:    'bg-[#D9E5F2] text-[#13478A]',
      system:  'bg-paper-50 text-ink-700',
    })[t] || 'bg-paper-50 text-ink-700';
  }

  function renderSearchResults(query) {
    const q = (query || '').toLowerCase().trim();
    const list = !q
      ? SEARCH_INDEX.slice(0, 8)
      : SEARCH_INDEX.filter(it => it.title.toLowerCase().includes(q) || it.desc.toLowerCase().includes(q) || (it.kw || '').toLowerCase().includes(q)).slice(0, 12);
    const root = document.getElementById('wa-search-results');
    if (!list.length) {
      root.innerHTML = `<div class="text-center text-[12.5px] text-ink-500 py-10">No results for <span class="font-mono text-ink-900">"${q}"</span></div>`;
      return;
    }
    if (searchSelected >= list.length) searchSelected = 0;
    root.innerHTML = list.map((it, i) => `
      <a href="${wurl(it.href)}" data-i="${i}" ${it.logout ? 'data-logout-link' : ''} class="block px-4 py-2.5 border-b border-paper-100 last:border-0 ${i===searchSelected?'bg-paper-50':''} hover:bg-paper-50">
        <div class="flex items-center gap-3">
          <span class="text-[10px] font-mono uppercase tracking-wider px-1.5 py-0.5 rounded ${tagColor(it.tag)}">${it.tag}</span>
          <div class="min-w-0 flex-1">
            <div class="text-[13px] font-semibold leading-tight truncate">${it.title}</div>
            <div class="text-[11px] text-ink-500 leading-tight truncate">${it.desc}</div>
          </div>
          <svg viewBox="0 0 16 16" class="w-3 h-3 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 3l5 5-5 5"/></svg>
        </div>
      </a>
    `).join('');
  }

  function openSearch() {
    document.getElementById('wa-search-modal').classList.remove('hidden');
    const i = document.getElementById('wa-search-input');
    i.value = ''; searchSelected = 0; renderSearchResults('');
    setTimeout(() => i.focus(), 30);
  }
  function closeSearch() { document.getElementById('wa-search-modal').classList.add('hidden'); }
  function toggleProfile() { document.getElementById('wa-profile-menu').classList.toggle('hidden'); }
  function closeProfile()  { document.getElementById('wa-profile-menu').classList.add('hidden'); }

  /* ── theme switcher ─────────────────────────────────────────── */
  const THEMES = [
    { id:'paper',  label:'Paper (default)', sw:'#F5F3EC', icon:'M2 14h12M2 14V4l5 3 7-5v12' },
    { id:'bright', label:'Bright white',    sw:'#FFFFFF', icon:'M8 2v2M8 12v2M2 8h2M12 8h2M3.5 3.5l1.5 1.5M11 11l1.5 1.5M3.5 12.5l1.5-1.5M11 5l1.5-1.5' },
    { id:'dark',   label:'Dark (beta)',     sw:'#0B1F1C', icon:'M8 1a7 7 0 1 0 7 7 5.5 5.5 0 0 1-7-7z' },
    { id:'doodle', label:'Doodle (fancy)',  sw:'#E8F5E9', icon:'M3 11c1-3 3-3 5-1s4 2 5-1M2 5l1.5-1.5L5 5M11 5l1.5-1.5L14 5' },
  ];
  function getTheme()  { try { return localStorage.getItem('wa-theme') || 'paper'; } catch(e) { return 'paper'; } }
  function setTheme(t) {
    if (t === 'paper') document.documentElement.removeAttribute('data-theme');
    else document.documentElement.setAttribute('data-theme', t);
    // The server renders data-theme on <body>; some CSS (e.g. Instaflow) matches
    // [data-theme] on either <html> or <body>, so mirror it onto <body> too or a
    // runtime switch only half-applies until the next reload re-renders <body>.
    if (document.body) document.body.setAttribute('data-theme', t);
    try { localStorage.setItem('wa-theme', t); } catch(e) {}
    // Keep the cookie in sync so the server renders the right logo next load.
    try { document.cookie = 'wa-theme=' + t + '; path=/; max-age=31536000; SameSite=Lax'; } catch(e) {}
    updateThemeIcon();
    swapBrandLogo(t);
  }
  /**
   * Hot-swap the <img data-brand-logo> src to the per-theme logo when
   * the user picks a different theme — no reload needed. Falls back to
   * the paper logo, then leaves the existing src as a last resort.
   * window.WADESK_BRAND.logos is injected by the layout from
   * App\Support\Brand::logoUrl() (admin-uploaded paths).
   */
  function swapBrandLogo(theme) {
    const logos = (window.WADESK_BRAND && window.WADESK_BRAND.logos) || {};
    const url = logos[theme] || logos.paper;
    if (!url) return;
    document.querySelectorAll('[data-brand-logo]').forEach((img) => {
      // A workspace's own white-label logo (data-ws-logo) is one fixed image —
      // never replace it with the platform's per-theme logo on theme change.
      if (img.hasAttribute('data-ws-logo')) return;
      if (img.src !== url) img.src = url;
    });
  }
  function updateThemeIcon() {
    const cur = THEMES.find(x => x.id === getTheme()) || THEMES[0];
    const ic = document.getElementById('wa-theme-icon');
    if (ic) ic.innerHTML = '<path d="' + cur.icon + '"/>';
  }
  function openThemeMenu(btn) {
    closeThemeMenu();
    const cur = getTheme();
    const m = document.createElement('div');
    m.className = 'theme-menu';
    m.id = 'wa-theme-menu';
    m.innerHTML = `
      <div class="px-2 py-1 text-[10px] font-mono uppercase tracking-wider text-ink-500">Theme</div>
      ${THEMES.map(t => `
        <button data-theme="${t.id}" class="${cur===t.id?'active':''}">
          <span class="swatch" style="${t.id==='doodle' ? 'background:linear-gradient(135deg,#E8F5E9 0%,#FFF6E0 60%,#FFC94A 100%);border:1.5px solid #15281F' : 'background:'+t.sw}"></span>
          <span class="flex-1">${t.label}</span>
          ${cur===t.id ? '<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l3 3 7-7"/></svg>' : ''}
        </button>`).join('')}
    `;
    // Append to <body> with FIXED positioning anchored to the button's
    // bounding rect. Previously appended to btn.parentElement, which on
    // the admin header is the wide flex container holding ALL the
    // right-side controls (theme/lang/bell/avatar). The CSS `right:0`
    // on `.theme-menu` then anchored to that container's right edge —
    // so the menu popped open near the avatar, far from the theme
    // button, and clipped past the viewport on narrower widths.
    // getBoundingClientRect + position:fixed guarantees the menu
    // sticks to the button regardless of header structure.
    document.body.appendChild(m);
    const rect = btn.getBoundingClientRect();
    const menuWidth = 220; // matches .theme-menu min-width:200 + padding
    const vw = window.innerWidth;
    // Default: right-align to the button's right edge. If that would
    // clip past the left viewport edge (button very near left), flip
    // to left-align instead.
    let rightOffset = vw - rect.right;
    if (rect.right < menuWidth + 8) rightOffset = Math.max(8, vw - rect.left - menuWidth);
    m.style.position = 'fixed';
    m.style.top      = (rect.bottom + 6) + 'px';
    m.style.right    = rightOffset + 'px';
    m.style.left     = 'auto'; // override the CSS file's right:0 anchor
    m.querySelectorAll('[data-theme]').forEach(b => b.addEventListener('click', () => {
      setTheme(b.dataset.theme);
      closeThemeMenu();
    }));
  }
  function closeThemeMenu() { const m = document.getElementById('wa-theme-menu'); if (m) m.remove(); }

  function wireHeaderControls() {
    /* identify the buttons in the canonical header by title */
    const search   = document.querySelector('header button[title="Search"]');
    const theme    = document.getElementById('wa-theme-btn');
    const settings = document.querySelector('header button[title="Settings"]');

    if (theme) {
      updateThemeIcon();
      theme.addEventListener('click', e => {
        e.preventDefault(); e.stopPropagation();
        const open = document.getElementById('wa-theme-menu');
        if (open) closeThemeMenu();
        else      openThemeMenu(theme);
      });
      document.addEventListener('click', e => {
        if (!e.target.closest('#wa-theme-menu') && !e.target.closest('#wa-theme-btn')) closeThemeMenu();
      });
    }
    /* The avatar pill carries data-user-toggle — match on that, not on looks.
       The old sniff looked for a `.from-wa-teal` gradient (or literal "VR"
       initials) and took the FIRST matching header button. The workspace
       switcher pill also renders that gradient whenever the workspace has no
       custom brand colour, and it sits earlier in the DOM — so the profile
       menu silently bound to the workspace pill instead, and clicking the
       avatar fell through to the server-rendered fallback pane. The attribute
       is unambiguous and survives restyling. */
    const avatar   = document.querySelector('header [data-user-toggle]')
      || Array.from(document.querySelectorAll('header button')).find(b => b.querySelector('.from-wa-teal') || b.querySelector('span.from-wa-teal') || /VR/.test(b.textContent || ''));

    if (search) {
      search.addEventListener('click', e => { e.preventDefault(); openSearch(); });
    }
    if (settings) {
      /* settings → /settings */
      settings.style.cursor = 'pointer';
      settings.addEventListener('click', e => { e.preventDefault(); location.href = wurl('/settings'); });
    }
    if (avatar) {
      // The header also ships a small server-rendered fallback pane
      // ([data-user-pane]) wired to the same button by an inline script, so it
      // still works if this bundle fails to load. Now that the rich menu is
      // live, drop it — otherwise one click opens both, stacked.
      document.querySelector('[data-user-pane]')?.remove();

      avatar.style.cursor = 'pointer';
      avatar.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); toggleProfile(); });
    }

    /* search modal events */
    const modal = document.getElementById('wa-search-modal');
    const input = document.getElementById('wa-search-input');
    if (modal) modal.addEventListener('click', closeSearch);     /* outside click closes */
    if (input) {
      input.addEventListener('input', () => renderSearchResults(input.value));
      input.addEventListener('keydown', e => {
        const items = document.querySelectorAll('#wa-search-results a');
        if (e.key === 'ArrowDown') { e.preventDefault(); searchSelected = Math.min(items.length - 1, searchSelected + 1); renderSearchResults(input.value); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); searchSelected = Math.max(0, searchSelected - 1); renderSearchResults(input.value); }
        else if (e.key === 'Enter') {
          const link = items[searchSelected]; if (link) location.href = link.href;
        } else if (e.key === 'Escape') closeSearch();
      });
    }

    /* close menus on outside click */
    document.addEventListener('click', e => {
      if (!e.target.closest('#wa-profile-menu') && !e.target.closest('header button')) closeProfile();
    });
    /* global ⌘K / Ctrl+K to open search; Esc closes everything */
    document.addEventListener('keydown', e => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openSearch(); }
      else if (e.key === 'Escape') { closeSearch(); closeProfile(); }
    });
  }

  function initTabs() {
    document.querySelectorAll('[data-wa-tabs]').forEach(tabset => {
      const buttons = Array.from(tabset.querySelectorAll('[data-wa-tab]'));
      if (!buttons.length) return;
      const active = (tabset.dataset.activeClasses || 'bg-wa-deep text-paper-0').split(' ');
      const inactive = (tabset.dataset.inactiveClasses || 'text-ink-600 hover:bg-paper-50').split(' ');
      const scope = tabset.closest('[data-wa-tab-scope]') || tabset.closest('main') || document;
      const panels = Array.from(scope.querySelectorAll('[data-wa-tab-panel]'));

      function setState(button, isActive) {
        button.classList.remove(...active, ...inactive);
        button.classList.add(...(isActive ? active : inactive));
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
      }

      function showTab(target) {
        buttons.forEach(button => setState(button, button.dataset.waTab === target));
        if (panels.length) {
          panels.forEach(panel => {
            const keys = (panel.dataset.waTabPanel || '').split(/\s+/).filter(Boolean);
            panel.classList.toggle('hidden', target !== 'overview' && !keys.includes(target));
          });
          window.dispatchEvent(new Event('resize'));
        }
      }

      buttons.forEach(button => {
        button.setAttribute('role', 'tab');
        button.addEventListener('click', event => {
          event.preventDefault();
          showTab(button.dataset.waTab);
        });
      });

      const initial = buttons.find(button => button.classList.contains('bg-wa-deep')) || buttons[0];
      showTab(initial.dataset.waTab);
    });
  }

  function highlightCurrentNav() {
    const path = (location.pathname.split('/').pop() || '/dashboard').toLowerCase();
    document.querySelectorAll('[data-nav]').forEach(a => {
      a.classList.toggle('active', a.dataset.nav.toLowerCase() === path);
    });
  }

  function wireLogoutLinks() {
    document.addEventListener('click', (e) => {
      const trigger = e.target.closest('[data-logout-link]');
      if (!trigger) return;
      e.preventDefault();
      const form = document.getElementById('logoutForm');
      if (form) form.submit();
      else window.location.href = wurl('/login');
    });
  }

  /* ── Top progress bar (YouTube / GitHub style) ───────────────
     A thin brand-gradient bar pinned to the very top of the
     viewport. It flashes to complete on every page load and
     climbs-and-holds the moment a same-origin navigation or a
     non-GET form submit begins, so slow page loads feel instant
     instead of showing a blank flash. No dependency, self-styled. */
  function initTopProgress() {
    if (document.getElementById('wa-topbar')) return;
    // The installer has its own in-step-circle progress ring — don't also
    // draw the global top bar there.
    if (document.body && document.body.dataset.page === 'install-wizard') return;
    const bar = document.createElement('div');
    bar.id = 'wa-topbar';
    bar.style.cssText = [
      'position:fixed', 'top:0', 'left:0', 'height:3px', 'width:0',
      'z-index:99999', 'opacity:0', 'pointer-events:none',
      'border-radius:0 3px 3px 0',
      `background:linear-gradient(90deg,${themeColor('wa-green')},#0b7d68 70%,#0b7d68)`,
      `box-shadow:0 0 10px ${themeAlpha('wa-green', 0.55)}`,
      'transition:width .2s ease,opacity .35s ease',
    ].join(';');
    document.body.appendChild(bar);

    let timer = null, w = 0, awaitingAjax = false, hardTimer = null;
    const setW = (v) => { w = v; bar.style.width = v + '%'; };
    function start() {
      clearInterval(timer);
      bar.style.opacity = '1';
      setW(8);
      timer = setInterval(() => {
        // Ease toward 90% and hold — the real page swap finishes it.
        const next = w + Math.max(0.4, (90 - w) * 0.09);
        setW(next >= 90 ? 90 : next);
        if (w >= 90) clearInterval(timer);
      }, 180);
      // AJAX-handled submits (contact add/edit, group create) update the DOM
      // WITHOUT a page navigation, so a fresh page load never fires done() and
      // the bar sticks at 90%. Arm two completion paths: the next AJAX settle
      // (onAjaxSettle, below) and a hard backstop so it can never hang past 12s.
      awaitingAjax = true;
      clearTimeout(hardTimer);
      hardTimer = setTimeout(() => { if (awaitingAjax) done(); }, 12000);
    }
    function done() {
      clearInterval(timer);
      clearTimeout(hardTimer);
      awaitingAjax = false;
      bar.style.opacity = '1';
      setW(100);
      setTimeout(() => { bar.style.opacity = '0'; setTimeout(() => setW(0), 320); }, 180);
    }
    // Complete the bar the moment an AJAX request settles after a start() —
    // only while a start() is pending, so background polling doesn't flash it.
    function onAjaxSettle() { if (awaitingAjax) done(); }

    // Flash-complete on initial load so every page entry feels finished.
    bar.style.opacity = '1';
    setW(72);
    setTimeout(done, 160);

    // Navigation begins → climb and hold (the new page takes over).
    document.addEventListener('click', (e) => {
      const a = e.target.closest && e.target.closest('a[href]');
      if (!a) return;
      const href = a.getAttribute('href');
      if (!href || href.charAt(0) === '#' || a.target === '_blank' || a.hasAttribute('download')) return;
      if (a.dataset && a.dataset.noprogress != null) return;
      if (/^(mailto:|tel:|javascript:)/i.test(href)) return;
      try {
        const u = new URL(a.href, location.href);
        if (u.origin !== location.origin) return;     // external → real browser nav
        if (u.href === location.href) return;          // same page / pure hash
      } catch (_) { return; }
      start();
    }, true);
    document.addEventListener('submit', (e) => {
      const f = e.target;
      if (f && f.tagName === 'FORM' && (f.method || 'get').toLowerCase() !== 'get') start();
    }, true);
    // bfcache restore (back/forward) → make sure the bar isn't stuck.
    window.addEventListener('pageshow', () => { clearInterval(timer); clearTimeout(hardTimer); awaitingAjax = false; bar.style.opacity = '0'; setW(0); });

    // Drive completion from real network activity. Wrapping fetch + XHR lets any
    // AJAX-handled action (contact add/edit, group create) finish the bar the
    // moment its request settles — without every caller knowing about the bar.
    // onAjaxSettle only fires done() while a start() is pending, so routine
    // background polling never flashes it.
    if (!window.__waProgressHooked) {
      window.__waProgressHooked = true;
      const _fetch = window.fetch;
      if (typeof _fetch === 'function') {
        window.fetch = function () {
          const p = _fetch.apply(this, arguments);
          if (p && typeof p.then === 'function') p.then(onAjaxSettle, onAjaxSettle);
          return p;
        };
      }
      const _send = XMLHttpRequest.prototype.send;
      XMLHttpRequest.prototype.send = function () {
        try { this.addEventListener('loadend', onAjaxSettle); } catch (_) {}
        return _send.apply(this, arguments);
      };
    }
    // Expose so bespoke async flows can drive the bar explicitly.
    window.WaProgress = { start, done };
  }

  function boot() {
    injectOverlays();
    wireHeaderControls();
    wireLogoutLinks();
    initTabs();
    highlightCurrentNav();
    initTopProgress();
    initFileImagePreviews();
  }

  /**
   * Live preview for any image <input type="file"> that carries
   * `data-preview-target` (the id of its preview box) + optional
   * `data-preview-class` for the <img> sizing. Global so every upload field
   * (general settings, PWA icons, …) shows the picked image before saving.
   * Guarded with `data-preview-bound` so it binds exactly once per input.
   */
  function initFileImagePreviews() {
    document.querySelectorAll('input[type="file"][data-preview-target]').forEach(function (input) {
      if (input.dataset.previewBound) return;
      input.dataset.previewBound = '1';
      input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        var box = document.getElementById(input.dataset.previewTarget);
        if (!file || !box || !file.type.startsWith('image/')) return;
        var url = URL.createObjectURL(file);
        var img = box.querySelector('img');
        if (!img) {
          box.innerHTML = '';
          img = document.createElement('img');
          img.className = input.dataset.previewClass || 'max-h-16 max-w-16 object-contain';
          box.appendChild(img);
        }
        var old = img.src;
        img.src = url;
        img.alt = 'preview';
        if (old && old.startsWith('blob:')) URL.revokeObjectURL(old);
      });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
