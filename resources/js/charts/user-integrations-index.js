export default function init() {
    // Brand SVG tiles — official-style logos rendered from path data.
      function brandTile(bg, svg) {
        return `<span class="w-12 h-12 rounded-xl grid place-items-center shrink-0" style="background:${bg};">
          <svg viewBox="0 0 32 32" class="w-7 h-7">${svg}</svg>
        </span>`;
      }

      // WooCommerce — purple speech-bubble with double-W
      const WOOCOMMERCE_SVG = `
        <path fill="#7F54B3" d="M2 6.5C2 5.1 3.1 4 4.5 4h23A2.5 2.5 0 0 1 30 6.5v13a2.5 2.5 0 0 1-2.5 2.5H17l-6 5 1.5-5H4.5A2.5 2.5 0 0 1 2 19.5v-13z"/>
        <path fill="#fff" d="M5.4 9.5c.4-.1.8 0 1 .3.2.3.2.7.1 1.1-.5 2-.9 3.7-1.1 5l1.3-2.5c.3-.5.6-.8.9-.8.5-.1.8.2.9.9.1 1 .2 1.8.4 2.6.2-1.7.5-3 .9-3.7.1-.3.4-.5.7-.5.2 0 .5.1.6.2.2.2.3.4.3.6 0 .2 0 .4-.1.5-.3.5-.5 1.4-.7 2.6-.2 1.2-.3 2.1-.3 2.7 0 .2 0 .4-.1.5-.1.2-.3.3-.5.3-.3 0-.5-.1-.8-.4-.8-.8-1.5-2-2-3.6-.6 1.2-1 2.1-1.3 2.7-.5 1-1 1.5-1.4 1.6-.3 0-.5-.2-.7-.7-.5-1.4-1-4-1.6-7.9 0-.4.2-.8.6-.9zm21 1.7c-.4-.7-.9-1.1-1.6-1.3-.2 0-.4-.1-.5-.1-.9 0-1.7.5-2.3 1.4-.5.8-.7 1.7-.7 2.7 0 .7.2 1.4.5 1.9.4.7.9 1.1 1.6 1.3.2 0 .4.1.5.1.9 0 1.7-.5 2.3-1.4.5-.8.7-1.7.7-2.7 0-.8-.2-1.4-.5-1.9zm-1.2 2.6c-.1.7-.4 1.2-.8 1.5-.3.3-.6.4-.9.3-.3-.1-.5-.3-.6-.7-.1-.3-.2-.6-.2-.9 0-.3 0-.5.1-.7.1-.4.3-.8.5-1.2.3-.5.7-.7 1-.6.3.1.5.3.6.7.1.3.2.6.2.9 0 .2 0 .4 0 .7zm-6.4-2.6c-.4-.7-.9-1.1-1.6-1.3-.2 0-.4-.1-.5-.1-.9 0-1.7.5-2.3 1.4-.5.8-.7 1.7-.7 2.7 0 .7.2 1.4.5 1.9.4.7.9 1.1 1.6 1.3.2 0 .4.1.5.1.9 0 1.7-.5 2.3-1.4.5-.8.7-1.7.7-2.7 0-.8-.2-1.4-.5-1.9zm-1.2 2.6c-.1.7-.4 1.2-.8 1.5-.3.3-.6.4-.9.3-.3-.1-.5-.3-.6-.7-.1-.3-.2-.6-.2-.9 0-.3 0-.5.1-.7.1-.4.3-.8.5-1.2.3-.5.7-.7 1-.6.3.1.5.3.6.7.1.3.2.6.2.9 0 .2 0 .4 0 .7z"/>`;

      // Shopify — green shopping bag with cursive S
      const SHOPIFY_SVG = `
        <path fill="#95BF47" d="M22.5 7.6c0-.1-.1-.2-.2-.2L20.5 7l-1.4-1.4c-.1-.1-.4-.1-.5 0L17 6c-.7-2-2.4-2.5-3.6-2.1-2.6.8-3.8 4-4.5 6 0 0-1.7.5-1.8.5-.9.3-1 .3-1.1 1.2L4.5 27.6 19.7 30l8.2-1.8L22.5 7.6zM16.7 6.5l-2 .6c0-.5-.1-1.1-.3-1.7.6.1 1.6.5 2.3 1.1zm-1-1.6c.2.5.3 1 .3 1.5l-2.4.7c.5-1.5 1.4-2.2 2.1-2.2zm-2.6-.4c.1 0 .2 0 .3 0-.7.4-1.7 1.5-2.2 3.6L9.4 8.7c.6-1.7 1.7-4 3.7-4.2z"/>
        <path fill="#5E8E3E" d="M22.3 7.4c-.1 0-1.7-.1-1.7-.1s-1.4-1.3-1.5-1.5c-.1-.1-.2-.1-.2-.1L19.7 30l8.2-1.8L22.5 7.6s-.1-.2-.2-.2z"/>
        <path fill="#fff" d="M16.4 11.7l-.9 3.4s-1-.5-2.2-.4c-1.8.1-1.8 1.2-1.8 1.5.1 1.5 4.1 1.9 4.3 5.5.2 2.8-1.5 4.7-3.9 4.9-2.9.2-4.5-1.5-4.5-1.5l.6-2.6s1.6 1.2 2.9 1.1c.8-.1 1.1-.7 1.1-1.2-.1-2-3.4-1.9-3.6-5.1-.2-2.7 1.6-5.5 5.5-5.7 1.5-.1 2.5.2 2.5.2z"/>`;

      // Google Sheets — green grid
      const GSHEETS_SVG = `
        <path fill="#0F9D58" d="M19 3H7C5.9 3 5 3.9 5 5v22c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V11l-8-8z"/>
        <path fill="#87CEAC" d="M19 3v6c0 1.1.9 2 2 2h6l-8-8z"/>
        <path fill="#fff" d="M22 16H10v9h12v-9zm-1 8h-3.5v-1.5H21V24zm0-2.5h-3.5V20H21v1.5zm0-2.5h-3.5v-1.5H21V19zm-4.5 5H13v-1.5h3.5V24zm0-2.5H13V20h3.5v1.5zm0-2.5H13v-1.5h3.5V19z"/>`;

      // WhatsApp Catalog — green book/list (Meta WhatsApp green)
      const WA_CATALOG_SVG = `
        <path fill="#25D366" d="M16 3C8.8 3 3 8.8 3 16c0 2.6.8 5 2.1 7L3 29l6.2-2c1.9 1 4.1 1.6 6.4 1.6h.4c7.2 0 13-5.8 13-13S23.2 3 16 3z"/>
        <rect x="9" y="9.5" width="14" height="13" rx="1" fill="#fff"/>
        <path stroke="#25D366" stroke-width="1.6" stroke-linecap="round" d="M11.5 13.5h9M11.5 16h9M11.5 18.5h6"/>`;

      // WhatsApp Store — green storefront with chat bubble
      const WA_STORE_SVG = `
        <path fill="#128C7E" d="M16 3C8.8 3 3 8.8 3 16c0 2.6.8 5 2.1 7L3 29l6.2-2c1.9 1 4.1 1.6 6.4 1.6h.4c7.2 0 13-5.8 13-13S23.2 3 16 3z"/>
        <path fill="#fff" d="M9 12h14l-1 2.5c-.2.5-.7.8-1.2.8h-9.6c-.5 0-1-.3-1.2-.8L9 12z"/>
        <rect x="10.5" y="15.8" width="11" height="7.7" rx="0.6" fill="#fff"/>
        <rect x="13" y="18.3" width="3" height="5.2" fill="#128C7E"/>
        <path fill="#fff" d="M9 11.5l1-2.5h12l1 2.5z"/>`;

      // HubSpot — orange sprocket (3 nodes + connectors)
      const HUBSPOT_SVG = `
        <path d="M16 11v6M11 17l5-2 5 2" stroke="#FF7A59" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="16" cy="8"  r="3" fill="#FF7A59"/>
        <circle cx="8"  cy="19" r="3" fill="#FF7A59"/>
        <circle cx="24" cy="19" r="3" fill="#FF7A59"/>`;

      // Google Calendar — blue calendar tile with 31
      const GCAL_SVG = `
        <rect x="6" y="9" width="20" height="17" rx="2" fill="#4285F4"/>
        <rect x="6" y="9" width="20" height="5" fill="#1A73E8"/>
        <rect x="9" y="6" width="2" height="6" rx="1" fill="#1A73E8"/>
        <rect x="21" y="6" width="2" height="6" rx="1" fill="#1A73E8"/>
        <text x="16" y="22" text-anchor="middle" fill="#fff" font-family="Arial" font-size="9" font-weight="bold">31</text>`;

      // Slack — four-colour hash mark
      const SLACK_SVG = `
        <rect x="13" y="5"  width="3" height="9" rx="1.5" fill="#36C5F0"/>
        <rect x="18" y="13" width="9" height="3" rx="1.5" fill="#2EB67D"/>
        <rect x="16" y="18" width="3" height="9" rx="1.5" fill="#ECB22E"/>
        <rect x="5"  y="16" width="9" height="3" rx="1.5" fill="#E01E5A"/>`;

      // Trello — blue board with two list columns
      const TRELLO_SVG = `
        <rect x="6" y="6" width="20" height="20" rx="3" fill="#0079BF"/>
        <rect x="9"  y="9" width="6" height="11" rx="1.5" fill="#fff"/>
        <rect x="17" y="9" width="6" height="7"  rx="1.5" fill="#fff"/>`;

      // Zapier — orange rounded square with the signature burst
      const ZAPIER_SVG = `
        <rect x="4" y="4" width="24" height="24" rx="6" fill="#FF4A00"/>
        <g stroke="#fff" stroke-width="2.4" stroke-linecap="round">
          <path d="M16 8v16"/>
          <path d="M8 16h16"/>
          <path d="M10.3 10.3l11.4 11.4"/>
          <path d="M21.7 10.3 10.3 21.7"/>
        </g>`;

      const APP = (window.WADESK_BRAND && window.WADESK_BRAND.appName) || 'WaDesk';
      const APPS = [
        { id:'shopify',     name:'Shopify',          cat:'ecom',         desc:'Sync orders, customers, abandoned carts and product catalog into ' + APP + ' chats.', tile:brandTile('#F1F9EC', SHOPIFY_SVG),    connected: !!window.SHOPIFY_CONNECTED, official:true },
        { id:'woocommerce', name:'WooCommerce',      cat:'ecom',         desc:'Pull WooCommerce orders into chat threads — confirm, ship, and refund without leaving ' + APP + '.', tile:brandTile('#F3ECFA', WOOCOMMERCE_SVG), connected: !!window.WOOCOMMERCE_CONNECTED, official:true },
        { id:'wa-catalog',  name:'WhatsApp Catalog', cat:'ecom',         desc:'Push products to Meta’s Commerce Catalog so buyers browse them inside WhatsApp — SPM, MPM, carousels, orders.', tile:brandTile('#E7FFDB', WA_CATALOG_SVG),  connected: !!window.WA_CATALOG_CONNECTED,  official:false },
        { id:'wa-store',    name:'WhatsApp Store',   cat:'ecom',         desc:'A full storefront inside WhatsApp — browse, add to cart, and pay without leaving the chat.', tile:brandTile('#E0F4F1', WA_STORE_SVG),    connected:false, official:false, multi:true },
        { id:'gsheets',     name:'Google Sheets',    cat:'productivity', desc:'Edit your shop catalog in a Google Sheet — add a row, tweak a price, click Sync. Every change goes live on your storefront.', tile:brandTile('#E8F5E9', GSHEETS_SVG),     connected: !!window.GSHEETS_CONNECTED,  official:false },
        { id:'hubspot',     name:'HubSpot CRM',      cat:'crm',          desc:'Push contacts and deals into HubSpot whenever a ' + APP + ' conversation triggers an event — new chat, order placed, SKU of interest.', tile:brandTile('#FFE4D6', HUBSPOT_SVG),     connected: !!window.HUBSPOT_CONNECTED,  official:true },
        { id:'gcal',        name:'Google Calendar',  cat:'productivity', desc:'Let customers book appointments inside WhatsApp. ' + APP + ' reads your availability and writes confirmed bookings straight to your calendar.', tile:brandTile('#E8F0FE', GCAL_SVG),        connected: !!window.GCAL_CONNECTED,     official:true },
        { id:'slack',       name:'Slack',            cat:'productivity', desc:'Send a WhatsApp message straight from Slack — type /wa send <name>: <message> and ' + APP + ' delivers it to that contact.', tile:brandTile('#F3ECFA', SLACK_SVG),  connected: !!window.SLACK_CONNECTED,  official:true },
        { id:'trello',      name:'Trello',           cat:'productivity', desc:'When a Trello card is assigned or changes, the right person gets a WhatsApp notification automatically.', tile:brandTile('#E8F0FE', TRELLO_SVG), connected: !!window.TRELLO_CONNECTED, official:true },
        { id:'zapier',      name:'Zapier',           cat:'productivity', desc:'Connect ' + APP + ' to 6,000+ apps with Zapier. Trigger Zaps from inbox and order events, or push data in — powered by webhooks + your API key.', tile:brandTile('#FFE9E0', ZAPIER_SVG), connected:false, official:false },
      ];

      let activeCat = 'all';
      let query = '';

      const CAT_LABELS = { all:'All integrations', ecom:'E-commerce', productivity:'Productivity', crm:'CRM' };

      function filtered() {
        return APPS.filter(a => {
          const inCat = activeCat === 'all' || a.cat === activeCat;
          const q = query.trim().toLowerCase();
          const inQ = !q || a.name.toLowerCase().includes(q) || a.desc.toLowerCase().includes(q);
          return inCat && inQ;
        });
      }

      function render() {
        const list = filtered();
        const root = document.getElementById('grid');
        root.innerHTML = '';
        list.forEach(a => {
          const card = document.createElement('div');
          card.className = 'bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col';
          const connectedHeader = `
              <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-mono bg-wa-mint text-wa-deep border border-wa-green/40">
                <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Connected
              </span>`;
          const manageHref = a.id === 'shopify'     ? '/shopify'
                           : a.id === 'woocommerce' ? '/woocommerce'
                           : a.id === 'hubspot'     ? '/hubspot'
                           : a.id === 'slack'       ? '/slack'
                           : a.id === 'trello'      ? '/trello'
                           : a.id === 'gcal'        ? '/appointments'
                           : a.id === 'gsheets'     ? '/sheets-addon'
                           : a.id === 'wa-catalog'  ? '/catalog'
                           : a.id === 'zapier'      ? '/webhooks'
                           : `/connect?platform=${a.id}&mode=manage`;
          const footerConnected = `
              <a href="${manageHref}" class="px-3 py-1.5 rounded-full bg-paper-0 border border-paper-200 hover:bg-paper-50 text-[11.5px] font-medium inline-flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                Manage
              </a>
              <button class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 grid place-items-center" title="Settings">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="2"/><path d="M13 8a5 5 0 0 0-.1-1.1l1.4-1-1.5-2.6-1.6.7a5 5 0 0 0-1.9-1.1L9 1H7l-.3 1.9a5 5 0 0 0-1.9 1.1l-1.6-.7-1.5 2.6 1.4 1A5 5 0 0 0 3 8c0 .4 0 .7.1 1.1l-1.4 1 1.5 2.6 1.6-.7a5 5 0 0 0 1.9 1.1L7 15h2l.3-1.9a5 5 0 0 0 1.9-1.1l1.6.7 1.5-2.6-1.4-1c.1-.4.1-.7.1-1.1Z"/></svg>
              </button>`;
          const shopCount = (window.WA_STORE_SHOPS && window.WA_STORE_SHOPS.length) || 0;
          const isWaStoreWithShops = a.id === 'wa-store' && shopCount > 0;
          // Some integrations have a dedicated setup page that's used
          // for BOTH "first-time setup" and "manage after connecting".
          // The generic /connect prototype page is the fallback for
          // anything else.
          const setupHref = a.id === 'gsheets'    ? '/sheets-addon'
                          : a.id === 'wa-catalog' ? '/catalog'
                          : a.id === 'shopify'    ? '/shopify'
                          : a.id === 'woocommerce'? '/woocommerce'
                          : a.id === 'hubspot'    ? '/hubspot'
                          : a.id === 'slack'      ? '/slack'
                          : a.id === 'trello'     ? '/trello'
                          : a.id === 'zapier'     ? '/webhooks'
                          : a.id === 'gcal'       ? '/appointments/settings'
                          : `/connect?platform=${a.id}`;
          const footerNotConnected = isWaStoreWithShops ? `
              <a href="/connect?platform=wa-store" class="ml-auto px-3 py-1.5 rounded-full bg-paper-0 border border-paper-200 hover:bg-paper-50 text-[11.5px] font-medium inline-flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                View shops <span class="font-mono text-[10px] opacity-70">(${shopCount})</span>
              </a>
              <a href="/connect?platform=wa-store&action=add" class="px-3 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[11.5px] font-semibold inline-flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>
                Add shop
              </a>` : `
              <a href="${setupHref}" class="ml-auto px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                ${a.id === 'gsheets' || a.id === 'wa-catalog' || a.id === 'zapier' ? 'Set up' : 'Connect now'}
              </a>`;
          card.innerHTML = `
            <div class="flex items-start justify-between gap-3">
              ${a.tile}
              ${a.connected ? connectedHeader : ''}
            </div>
            <div class="mt-3 flex items-center gap-1.5 flex-wrap">
              <h3 class="font-semibold text-[16px] leading-tight">${a.name}</h3>
              ${a.official ? '<span class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-paper-50 text-ink-700 border border-paper-200">OFFICIAL</span>' : ''}
            </div>
            <p class="mt-1.5 text-[12px] text-ink-500 leading-snug flex-1">${a.desc}</p>
            <div class="mt-4 pt-3 border-t border-paper-200 flex items-center justify-between gap-2">
              ${a.connected ? footerConnected : footerNotConnected}
            </div>`;
          root.appendChild(card);
        });
        document.getElementById('empty').classList.toggle('hidden', list.length > 0);
        document.getElementById('grid-title').textContent = CAT_LABELS[activeCat];
        document.getElementById('grid-count').textContent = list.length + ' app' + (list.length === 1 ? '' : 's');
      }

      // Category tabs
      document.querySelectorAll('#cat-tabs .cat-tab').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('#cat-tabs .cat-tab').forEach(x => {
          x.classList.remove('bg-wa-deep','text-paper-0');
          x.classList.add('text-ink-600','hover:bg-paper-100');
        });
        b.classList.add('bg-wa-deep','text-paper-0');
        b.classList.remove('text-ink-600','hover:bg-paper-100');
        activeCat = b.dataset.cat;
        render();
      }));

      // Search
      document.getElementById('search').addEventListener('input', e => { query = e.target.value; render(); });

      render();
}
