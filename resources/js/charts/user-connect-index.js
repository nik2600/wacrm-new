export default function init() {
    const APP = (window.WADESK_BRAND && window.WADESK_BRAND.appName) || 'WaDesk';
    const PLATFORMS = {
        shopify: {
          name: 'Shopify',
          desc: 'Sync Shopify orders, customers, abandoned carts and product catalog into WhatsApp chats.',
          placeholderUrl: 'https://yourstore.myshopify.com',
          tile: { bg:'#F1F9EC', svg: `
            <path fill="#95BF47" d="M22.5 7.6c0-.1-.1-.2-.2-.2L20.5 7l-1.4-1.4c-.1-.1-.4-.1-.5 0L17 6c-.7-2-2.4-2.5-3.6-2.1-2.6.8-3.8 4-4.5 6 0 0-1.7.5-1.8.5-.9.3-1 .3-1.1 1.2L4.5 27.6 19.7 30l8.2-1.8L22.5 7.6z"/>
            <path fill="#5E8E3E" d="M22.3 7.4c-.1 0-1.7-.1-1.7-.1s-1.4-1.3-1.5-1.5c-.1-.1-.2-.1-.2-.1L19.7 30l8.2-1.8L22.5 7.6s-.1-.2-.2-.2z"/>
            <path fill="#fff" d="M16.4 11.7l-.9 3.4s-1-.5-2.2-.4c-1.8.1-1.8 1.2-1.8 1.5.1 1.5 4.1 1.9 4.3 5.5.2 2.8-1.5 4.7-3.9 4.9-2.9.2-4.5-1.5-4.5-1.5l.6-2.6s1.6 1.2 2.9 1.1c.8-.1 1.1-.7 1.1-1.2-.1-2-3.4-1.9-3.6-5.1-.2-2.7 1.6-5.5 5.5-5.7 1.5-.1 2.5.2 2.5.2z"/>` },
          fields: [
            { name:'access_token', label:'Admin API access token', type:'password', placeholder:'shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxx', required:true,
              hint:'Found under Apps and sales channels → Develop apps → API credentials.' }
          ],
          steps: [
            ['Open app settings',  'Settings → Apps and sales channels → Develop apps.'],
            ['Create a custom app','Configure scopes: read_orders, read_customers, read_products.'],
            ['Install &amp; get token','Install the app and copy the Admin API access token. Shown only once.'],
            ['Paste &amp; connect', 'Paste the URL and token, then click Save &amp; connect.'],
          ],
          faqs: [
            ['How do I create a custom app?', 'In Shopify admin go to Settings → Apps and sales channels → Develop apps. Click Create an app, name it, then configure the Admin API scopes.'],
            ['What API scopes do I need?', 'read_orders, read_customers, read_products. These let ' + APP + ' read order and customer data.'],
            ['Where is my access token?', 'After installing your custom app, open the API credentials tab and click "Reveal token once". Copy it immediately — shown only once.'],
            ['Is my data secure?', 'We only request read-only access. Credentials are encrypted at rest. We never modify your store data.'],
            ['Can I disconnect later?', 'Yes — disconnect any time from Integrations. All webhooks are removed automatically.'],
          ],
        },
        woocommerce: {
          name: 'WooCommerce',
          desc: 'Pull WooCommerce orders into chat threads — confirm, ship, and refund without leaving ' + APP + '.',
          placeholderUrl: 'https://yourstore.com',
          tile: { bg:'#F3ECFA', svg: `
            <path fill="#7F54B3" d="M2 6.5C2 5.1 3.1 4 4.5 4h23A2.5 2.5 0 0 1 30 6.5v13a2.5 2.5 0 0 1-2.5 2.5H17l-6 5 1.5-5H4.5A2.5 2.5 0 0 1 2 19.5v-13z"/>
            <path fill="#fff" d="M5.4 9.5c.4-.1.8 0 1 .3.2.3.2.7.1 1.1-.5 2-.9 3.7-1.1 5l1.3-2.5c.3-.5.6-.8.9-.8.5-.1.8.2.9.9.1 1 .2 1.8.4 2.6.2-1.7.5-3 .9-3.7.1-.3.4-.5.7-.5.5 0 .9.4.9.9 0 .2 0 .4-.1.5-.3.5-.5 1.4-.7 2.6-.2 1.2-.3 2.1-.3 2.7 0 .5-.2.7-.6.7-.3 0-.5-.1-.8-.4-.8-.8-1.5-2-2-3.6-.6 1.2-1 2.1-1.3 2.7-.5 1-1 1.5-1.4 1.6-.3 0-.5-.2-.7-.7-.5-1.4-1-4-1.6-7.9 0-.4.2-.8.6-.9z"/>` },
          fields: [
            { name:'api_key',    label:'Consumer key',    type:'text',     placeholder:'ck_xxxxxxxxxxxxxxxxxxxxxxxx', required:true,
              hint:'Generated in WooCommerce → Settings → Advanced → REST API.' },
            { name:'api_secret', label:'Consumer secret', type:'password', placeholder:'cs_xxxxxxxxxxxxxxxxxxxxxxxx', required:true,
              hint:'Shown only once when the key is created.' },
          ],
          steps: [
            ['Open REST API settings','WooCommerce → Settings → Advanced → REST API.'],
            ['Create an API key','Click Add key, enter a description, choose Read permissions.'],
            ['Copy credentials','Copy Consumer key &amp; secret. The secret is shown only once.'],
            ['Paste &amp; connect','Paste credentials in the form and click Save &amp; connect.'],
          ],
          faqs: [
            ['Where do I find my API keys?', 'WordPress admin → WooCommerce → Settings → Advanced → REST API. Click Add key.'],
            ['What permissions should I select?', 'Read permission is enough — ' + APP + ' only reads order and customer data.'],
            ['Does my store need SSL?', 'Yes. The WooCommerce REST API requires HTTPS to work securely.'],
            ['What events can I automate?', 'New orders, order status updates, shipping notifications, abandoned carts, review requests, and new customer registrations.'],
            ['Can I disconnect later?', 'Yes — disconnect any time from Integrations. All webhooks are removed automatically.'],
          ],
        },
        'wa-catalog': {
          name: 'WhatsApp Catalog',
          desc: 'Manage your in-app catalog — sync product cards, prices, and stock to share in chat.',
          placeholderUrl: 'https://business.facebook.com/commerce/catalogs/<id>',
          tile: { bg:'#E7FFDB', svg: `
            <path fill="#25D366" d="M16 3C8.8 3 3 8.8 3 16c0 2.6.8 5 2.1 7L3 29l6.2-2c1.9 1 4.1 1.6 6.4 1.6h.4c7.2 0 13-5.8 13-13S23.2 3 16 3z"/>
            <rect x="9" y="9.5" width="14" height="13" rx="1" fill="#fff"/>
            <path stroke="#25D366" stroke-width="1.6" stroke-linecap="round" d="M11.5 13.5h9M11.5 16h9M11.5 18.5h6"/>` },
          fields: [
            { name:'catalog_id',   label:'Catalog ID',                  type:'text',     placeholder:'1234567890', required:true,
              hint:'Find it in Meta Commerce Manager → Catalogs.' },
            { name:'access_token', label:'WhatsApp Cloud API token',     type:'password', placeholder:'EAAGm0PX4ZCpsBO…', required:true,
              hint:'Permanent system-user token with catalog_management permission.' },
          ],
          steps: [
            ['Create a catalog',         'Open Meta Commerce Manager and create or pick a catalog.'],
            ['Link to WhatsApp Business','In WhatsApp Manager, link the catalog to your business account.'],
            ['Generate token',           'Create a system user with catalog_management and generate a permanent token.'],
            ['Paste &amp; connect',      'Paste the catalog ID and token, then click Save &amp; connect.'],
          ],
          faqs: [
            ['What is a WhatsApp catalog?', 'A list of products shown inside chats. Customers can browse and tap to start a conversation about a product.'],
            ['Where do I get the catalog ID?', 'Commerce Manager → Catalogs → click your catalog. The ID is in the URL and the details panel.'],
            ['Catalog vs Store?', 'Catalog is a product list. Store wraps it with checkout.'],
          ],
        },
        'wa-store': {
          name: 'WhatsApp Store',
          desc: 'A full storefront inside WhatsApp — browse, add to cart, and pay without leaving the chat.',
          placeholderUrl: 'https://business.facebook.com/commerce/<id>',
          tile: { bg:'#E0F4F1', svg: `
            <path fill="#128C7E" d="M16 3C8.8 3 3 8.8 3 16c0 2.6.8 5 2.1 7L3 29l6.2-2c1.9 1 4.1 1.6 6.4 1.6h.4c7.2 0 13-5.8 13-13S23.2 3 16 3z"/>
            <path fill="#fff" d="M9 12h14l-1 2.5c-.2.5-.7.8-1.2.8h-9.6c-.5 0-1-.3-1.2-.8L9 12z"/>
            <rect x="10.5" y="15.8" width="11" height="7.7" rx="0.6" fill="#fff"/>
            <rect x="13" y="18.3" width="3" height="5.2" fill="#128C7E"/>
            <path fill="#fff" d="M9 11.5l1-2.5h12l1 2.5z"/>` },
          fields: [
            { name:'commerce_id',  label:'Commerce account ID',  type:'text',     placeholder:'9876543210', required:true,
              hint:'Found in Meta Commerce Manager.' },
            { name:'access_token', label:'Commerce API token',   type:'password', placeholder:'EAAGm0PX4ZCpsBO…', required:true,
              hint:'System-user token with commerce_account_manage_orders permission.' },
          ],
          steps: [
            ['Set up commerce account','Create a commerce account in Meta Commerce Manager and complete checkout setup.'],
            ['Connect a payment provider','Connect Razorpay, Stripe, or your supported gateway.'],
            ['Generate token','Create a system user with commerce_account_manage_orders and generate a token.'],
            ['Paste &amp; connect','Paste the commerce ID and token, then click Save &amp; connect.'],
          ],
          faqs: [
            ['Which countries support checkout?', 'India, Brazil, Singapore, Indonesia and a few others. Check Meta\'s availability page for the latest.'],
            ['Do I need a payment gateway?', 'Yes — Razorpay, Stripe, or another supported provider must be connected for in-chat checkout.'],
          ],
        },
        gsheets: {
          name: 'Google Sheets',
          desc: 'Two-way sync — import contacts from a sheet, export every conversation as rows.',
          placeholderUrl: 'https://docs.google.com/spreadsheets/d/<sheet-id>/edit',
          tile: { bg:'#E8F5E9', svg: `
            <path fill="#0F9D58" d="M19 3H7C5.9 3 5 3.9 5 5v22c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V11l-8-8z"/>
            <path fill="#87CEAC" d="M19 3v6c0 1.1.9 2 2 2h6l-8-8z"/>
            <path fill="#fff" d="M22 16H10v9h12v-9zm-1 8h-3.5v-1.5H21V24zm0-2.5h-3.5V20H21v1.5zm0-2.5h-3.5v-1.5H21V19zm-4.5 5H13v-1.5h3.5V24zm0-2.5H13V20h3.5v1.5zm0-2.5H13v-1.5h3.5V19z"/>` },
          fields: [
            { name:'sheet_url',  label:'Spreadsheet URL', type:'url',  placeholder:'https://docs.google.com/spreadsheets/d/abc123/edit', required:true,
              hint:'Paste the full URL of the sheet you want to sync.' },
            { name:'oauth',      label:'Google account',  type:'oauth', required:true,
              hint:'You\'ll be redirected to Google to grant ' + APP + ' read/write access.' },
          ],
          steps: [
            ['Pick a sheet',     'Open or create the Google Sheet you want to sync.'],
            ['Share via OAuth',  'No need to share manually — ' + APP + ' uses Google sign-in.'],
            ['Connect Google',   'Click "Connect with Google" and grant access.'],
            ['Paste &amp; connect','Paste the spreadsheet URL and click Save &amp; connect.'],
          ],
          faqs: [
            ['Will ' + APP + ' edit my sheet?', 'Only the rows it adds or updates as part of the sync. Existing data is left alone.'],
            ['What columns do I need?', 'name, country_code, phone are the minimum. Add tags or custom columns — ' + APP + ' preserves them.'],
            ['Can I disconnect later?', 'Yes — revoke access from your Google account or from ' + APP + ' Integrations.'],
          ],
        },
      };

      function qs(name) {
        const m = location.search.match(new RegExp('[?&]' + name + '=([^&]*)'));
        return m ? decodeURIComponent(m[1]) : '';
      }

      function renderPlatform() {
        const key = (qs('platform') || 'shopify').toLowerCase();
        const isManage = qs('mode') === 'manage';
        const p = PLATFORMS[key] || PLATFORMS.shopify;

        const __app = (window.WADESK_BRAND && window.WADESK_BRAND.appName) || 'WaDesk';
        document.title = `${__app} — ${isManage ? 'Manage' : 'Connect'} ${p.name}`;
        document.getElementById('bc-platform').textContent = (isManage ? 'Manage ' : 'Connect ') + p.name;
        document.getElementById('title-platform').textContent = p.name;
        document.getElementById('title-desc').textContent = p.desc;
        document.querySelector('h1.font-serif').firstChild.textContent = (isManage ? 'Manage ' : 'Connect ');
        document.getElementById('store_url').placeholder = p.placeholderUrl;
        document.getElementById('webhook-url').textContent = `https://api.wadesk.app/webhooks/${key}/<your-store>`;
        document.getElementById('aside-platform-name').textContent = p.name;

        // Logo tile in aside
        const tile = document.getElementById('logo-tile');
        tile.style.background = p.tile.bg;
        tile.innerHTML = `<svg viewBox="0 0 32 32" class="w-full h-full p-1.5">${p.tile.svg}</svg>`;

        // Connection status pill
        const status = document.getElementById('aside-status');
        if (isManage) {
          status.className = 'mt-3 inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-mono bg-wa-mint text-wa-deep border border-wa-green/40';
          status.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Connected';
        }

        // Credential fields
        const root = document.getElementById('cred-fields');
        root.innerHTML = '';
        p.fields.forEach(f => {
          if (f.type === 'oauth') {
            root.insertAdjacentHTML('beforeend', `
              <div>
                <label class="block text-[12px] font-semibold text-ink-700 mb-1.5">${f.label} ${f.required ? '<span class="text-accent-coral">*</span>' : ''}</label>
                <button type="button" class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white hover:bg-paper-50 text-[13px] font-medium inline-flex items-center justify-center gap-2">
                  <svg viewBox="0 0 16 16" class="w-4 h-4"><path fill="#4285F4" d="M15.6 8.18c0-.55-.05-1.07-.14-1.58H8v3h4.27c-.18.97-.74 1.79-1.58 2.34v1.94h2.55c1.5-1.38 2.36-3.41 2.36-5.7z"/><path fill="#34A853" d="M8 16c2.13 0 3.92-.71 5.23-1.92l-2.55-1.97c-.71.47-1.61.75-2.68.75-2.06 0-3.81-1.39-4.43-3.27H1v2.05A8 8 0 0 0 8 16z"/><path fill="#FBBC05" d="M3.57 9.59A4.8 4.8 0 0 1 3.32 8c0-.55.09-1.09.25-1.59V4.36H1A8 8 0 0 0 0 8c0 1.29.31 2.51.86 3.59L3.57 9.59z"/><path fill="#EA4335" d="M8 3.16c1.16 0 2.2.4 3.02 1.18L13.27 2.1A8 8 0 0 0 8 0a8 8 0 0 0-7 4.36l2.57 2.05C4.19 4.55 5.94 3.16 8 3.16z"/></svg>
                  Connect with Google
                </button>
                ${f.hint ? `<p class="text-[10.5px] text-ink-500 mt-1">${f.hint}</p>` : ''}
              </div>`);
          } else {
            root.insertAdjacentHTML('beforeend', `
              <div>
                <label class="block text-[12px] font-semibold text-ink-700 mb-1.5">${f.label} ${f.required ? '<span class="text-accent-coral">*</span>' : ''}</label>
                <input type="${f.type}" name="${f.name}" id="${f.name}" ${f.required ? 'required' : ''}
                       class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                       placeholder="${f.placeholder || ''}" />
                ${f.hint ? `<p class="text-[10.5px] text-ink-500 mt-1">${f.hint}</p>` : ''}
              </div>`);
          }
        });

        // Sidebar (compact) steps
        const asideSteps = document.getElementById('aside-steps');
        asideSteps.innerHTML = '';
        p.steps.forEach(([title], i) => {
          asideSteps.insertAdjacentHTML('beforeend', `
            <li class="flex items-center gap-2 px-3 py-2 rounded-xl text-[12.5px] text-ink-700 hover:bg-paper-50">
              <span class="w-5 h-5 rounded-full bg-paper-50 border border-paper-200 grid place-items-center text-[10px] font-mono text-ink-700 shrink-0">${i+1}</span>
              <span class="truncate">${title.replace(/&amp;/g,'&')}</span>
            </li>`);
        });

        // Detailed setup steps in right column
        const stepsRoot = document.getElementById('guide-steps');
        stepsRoot.innerHTML = '';
        p.steps.forEach(([title, body], i) => {
          stepsRoot.insertAdjacentHTML('beforeend', `
            <li class="flex gap-2.5">
              <span class="w-6 h-6 rounded-full bg-wa-mint text-wa-deep grid place-items-center text-[10.5px] font-mono font-semibold shrink-0">${i+1}</span>
              <div>
                <div class="text-[12.5px] font-semibold leading-tight">${title}</div>
                <div class="text-[11px] text-ink-500 mt-0.5 leading-snug">${body}</div>
              </div>
            </li>`);
        });

      }

      function showResult(type, html) {
        const r = document.getElementById('testResult');
        r.classList.remove('hidden');
        r.className = 'text-[12px] px-3 py-2 rounded-lg border ' + (
          type === 'success' ? 'bg-wa-mint/40 border-wa-green/40 text-wa-deep' :
          type === 'error'   ? 'bg-accent-coral/10 border-accent-coral/40 text-accent-coral' :
                               'bg-paper-50 border-paper-200 text-ink-700'
        );
        r.innerHTML = html;
      }

      function gatherPayload() {
        const url = document.getElementById('store_url').value.trim();
        const data = { store_url: url };
        document.querySelectorAll('#cred-fields input').forEach(i => { if (i.value) data[i.id] = i.value.trim(); });
        return data;
      }

      document.getElementById('btnTest').addEventListener('click', () => {
        const data = gatherPayload();
        if (!data.store_url) { showResult('error', 'Please enter your store URL.'); return; }
        showResult('info', '<svg viewBox="0 0 16 16" class="w-3 h-3 inline animate-spin" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="6" stroke-dasharray="20 8"/></svg> Testing connection…');
        setTimeout(() => {
          showResult('success', '✓ Connection successful. Store: <strong>' + (data.store_url.replace(/https?:\/\//,'').replace(/\/.*/,'')) + '</strong>');
        }, 900);
      });

      function saveConnect() {
        const data = gatherPayload();
        if (!data.store_url) { showResult('error', 'Please enter your store URL.'); return; }
        showResult('info', 'Saving…');
        const platform = (qs('platform') || '').toLowerCase();
        setTimeout(() => {
          if (platform === 'shopify')     { window.location = window.appUrl('/shopify'); return; }
          if (platform === 'woocommerce') { window.location = window.appUrl('/woocommerce'); return; }
          window.location = window.appUrl('/integrations?just_connected=' + platform);
        }, 800);
      }

      renderPlatform();
}
