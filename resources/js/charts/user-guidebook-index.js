export default function init() {
    const APP = (window.WADESK_BRAND && window.WADESK_BRAND.appName) || 'WaDesk';
    const CATS = {
        'all': { label: 'All articles', count: 32 },
        'getting-started': { label: 'Getting started', pill: 'Getting started' },
        'campaigns': { label: 'Campaigns', pill: 'Campaigns' },
        'templates': { label: 'Templates', pill: 'Templates' },
        'auto-reply': { label: 'Auto Reply', pill: 'Auto Reply' },
        'scheduled': { label: 'Scheduled Messages', pill: 'Scheduled' },
        'webhooks': { label: 'Webhooks & API', pill: 'Webhooks' },
        'flows': { label: 'Flow Builder', pill: 'Flows' },
        'contacts': { label: 'Contacts & segments', pill: 'Contacts' },
        'billing': { label: 'Billing', pill: 'Billing' },
        'troubleshooting': { label: 'Troubleshooting', pill: 'Troubleshooting' },
      };

      const ARTICLES = [
        { id:'gs-1', cat:'getting-started', title:'Connect your first WhatsApp number', desc:'Pair a phone, verify with Meta, and send your first test message in under 10 minutes.', read:'4 min read',
          body:[
            APP + ' connects to your WhatsApp Business Account through the Cloud API — no QR scanning, no idle phone in a drawer. Once paired, the number stays online 24/7.',
            '<b>Step 1.</b> In the top nav, open <span class="font-mono">Settings → Devices</span> and click <span class="font-mono">+ Add device</span>. Pick the country code and paste the WhatsApp Business number you want to pair.',
            '<b>Step 2.</b> ' + APP + ' will ask Meta for a verification code. Enter the code on the next screen — this is the same flow Meta runs in WhatsApp Manager.',
            '<b>Step 3.</b> Send a test by typing your own number into <span class="font-mono">Chat → New message</span> and firing a one-line "hello". If it lands, you\'re live.',
            '<b>Common gotchas:</b> the number must be verified in Meta Business Manager first; trial accounts are limited to 5 unique recipients per day until you submit business info.'
          ] },
        { id:'gs-2', cat:'getting-started', title:'Invite teammates and set roles', desc:'Add seats, scope what each role can see, and pass the keys without giving away the kingdom.', read:'3 min read',
          body:[
            APP + ' supports four built-in roles: <b>Owner</b>, <b>Admin</b>, <b>Manager</b>, and <b>Agent</b>. Owners can edit billing; Admins can edit everything else; Managers can run campaigns and reply to chats; Agents can only reply.',
            '<b>To invite:</b> open <span class="font-mono">Settings → Team → Invite</span>, paste an email, pick a role, click Send. The invite link expires after 7 days.',
            '<b>Custom roles</b> are available on Growth and above — you pick which modules and which devices the role can access.'
          ] },
        { id:'gs-3', cat:'getting-started', title:'Import your existing contacts (CSV)', desc:'Map columns, de-duplicate by phone, and dry-run the import before it touches anything.', read:'5 min read',
          body:[
            APP + ' accepts CSV files up to 50 MB. The first row is treated as the header — match each column to a contact field (name, phone, email, tags, custom fields).',
            '<b>Tip:</b> phone numbers must include country code without the leading <span class="font-mono">+</span>. <span class="font-mono">919876543210</span> is fine; <span class="font-mono">+91 98765 43210</span> will be cleaned.',
            'Use the <b>Dry run</b> button before committing — it shows you exactly which rows would be created, updated, or skipped, with a per-row reason.'
          ] },
        { id:'gs-4', cat:'getting-started', title:'A 5-minute tour of the dashboard', desc:'What every tile means, how to read the daily pulse, and where to drill into the numbers.', read:'4 min read', body:[
          'The dashboard is split into three bands: top-of-funnel volume, conversation health, and revenue impact.',
          'Hover any KPI to see the comparison window (vs yesterday, vs last week, vs same day last week).',
          'Click any tile to jump straight to the detail view with the same date range applied.'
        ] },
        { id:'gs-5', cat:'getting-started', title:'Switch from another platform without losing data', desc:'Templates, contacts, message history — all the moving parts you need to migrate.', read:'7 min read', body:[
          'Migration usually takes a weekend. Plan in this order: device pairing → contacts → templates → flows → live cutover.',
          'Tip: keep the old platform receive-only for 24 hours after cutover to catch any straggler webhooks.'
        ] },

        { id:'cmp-1', cat:'campaigns', title:'Run your first WhatsApp blast', desc:'Build a list, pick a template, schedule the send, and read the delivery report.', read:'6 min read', body:[
          'A blast is a one-shot send to a saved group. You\'ll need an approved template, a target group of at least one contact, and a sending device.',
          '<b>Step 1.</b> Open <span class="font-mono">Campaigns → New campaign</span>. Name it something specific like "Spring promo / VIP" — future you will thank present you.',
          '<b>Step 2.</b> Pick the template, fill in any variables, choose the group.',
          '<b>Step 3.</b> Set the send time. Per-contact local timezone delivery is available if your group spans regions.'
        ] },
        { id:'cmp-2', cat:'campaigns', title:'Why my campaign read rate is low', desc:'Common reasons messages get delivered but not opened — and how to fix each.', read:'5 min read', body:[
          'Check your send time first. Messages sent between 9–11 AM and 6–8 PM in the recipient\'s timezone open ~40% better than 2–4 PM blasts.',
          'Second, check your subject line — yes, even on WhatsApp. The first 40 characters appear in the lock-screen preview.',
          'Third, look at the audience: VIP segments outperform "all customers" by 2–3×.'
        ] },
        { id:'cmp-3', cat:'campaigns', title:'A/B test two template variants', desc:'Split a group, fire both, and let ' + APP + ' pick the winner.', read:'4 min read', body:[
          'Create two templates — same intent, different copy. In the campaign builder pick the A/B option and set the split (50/50, 70/30, etc.).',
          'After 24 hours ' + APP + ' auto-promotes the winning variant for the rest of the campaign if you have <b>Auto-promote</b> turned on.'
        ] },
        { id:'cmp-4', cat:'campaigns', title:'Stop a running campaign cleanly', desc:'Pause vs cancel — and what happens to messages already in-flight.', read:'2 min read', body:[
          'Pausing keeps in-flight messages going but stops new ones from being queued. Cancelling tries to cancel everything still in queue but lets in-flight ones complete.',
          'Once a message has been handed off to WhatsApp\'s servers we can\'t recall it.'
        ] },

        { id:'tpl-1', cat:'templates', title:'Why was my template rejected?', desc:'The 6 most common rejection reasons and a checklist to avoid them.', read:'6 min read', body:[
          '<b>1. Promotional language without a category.</b> "Buy now! Limited offer!" outside a Marketing template will be rejected.',
          '<b>2. Variables in the wrong place.</b> Variables can\'t open or close a template, and they can\'t sit next to each other without text in between.',
          '<b>3. Duplicate templates.</b> Same body, same name = rejection. Add a version suffix like <span class="font-mono">_v2</span>.',
          '<b>4. Missing opt-in language</b> for marketing templates outside the US.',
          '<b>5. URL shorteners or suspicious links.</b> Use the full URL.',
          '<b>6. Special characters in the name.</b> Stick to lowercase, numbers, and underscores.'
        ] },
        { id:'tpl-2', cat:'templates', title:'How to write a template that gets approved fast', desc:'A working formula: greeting → context → action → footer. With examples.', read:'5 min read', body:[
          'Templates that follow the <b>greeting → context → action</b> pattern approve in under 2 hours, on average.',
          'Example: "Hi {{1}}, your order #{{2}} is ready for pickup. Tap below to confirm."',
          'Avoid: bare variables ("{{1}}"), all caps, multiple exclamation marks, and emoji in the first line.'
        ] },
        { id:'tpl-3', cat:'templates', title:'Variables, components, and CTAs explained', desc:'When to use a header variable, when to use a button, and when to skip both.', read:'4 min read', body:[
          'Header variables are great for personalization but cost an extra approval cycle. Use them only when "Hi {{1}}" isn\'t enough.',
          'Buttons (Quick Reply or URL) almost always lift CTR. Even a single "View order" button beats no button.',
          'Footer is optional but useful for opt-out language: "Reply STOP to unsubscribe".'
        ] },
        { id:'tpl-4', cat:'templates', title:'Edit an approved template without re-submitting', desc:'When you can edit live, when you can\'t, and how to version safely.', read:'3 min read', body:[
          'Approved templates can be edited only once every 24 hours and only for body text — no header, no buttons, no language changes.',
          'For anything bigger, duplicate the template, edit, submit the duplicate, and swap them once approved.'
        ] },

        { id:'ar-1', cat:'auto-reply', title:'Stop auto-reply spam: cooldowns explained', desc:'Why a 60-second cooldown saves you from looking like a robot.', read:'3 min read', body:[
          'Without a cooldown, a chatty user can trigger the same auto-reply 5 times in a minute. That feels broken.',
          'Set the cooldown to 60–120 seconds for greetings, 5–10 minutes for support keywords. Pricing/cost rules can usually go to 24 hours.'
        ] },
        { id:'ar-2', cat:'auto-reply', title:'Fuzzy match vs exact match — which to use', desc:'When typo-tolerance helps and when it backfires.', read:'4 min read', body:[
          'Fuzzy match at 75–80% catches typos like "pricng", "pricng me", "what is the prce". Below 70% you start matching unrelated phrases.',
          'Use exact for opt-in/opt-out keywords (STOP, START) and for legal disclaimers — those need to be precise.'
        ] },
        { id:'ar-3', cat:'auto-reply', title:'Schedule auto-replies to business hours', desc:'Have a different message for nights and weekends.', read:'3 min read', body:[
          'Create one rule for business hours ("Hi! We\'re online — our team will reply in a few minutes.") and a duplicate for off-hours ("Thanks! We\'re closed but will reply at 9am.").',
          'Set the schedule on each rule so they don\'t fight.'
        ] },

        { id:'sc-1', cat:'scheduled', title:'Send at the recipient\'s local time', desc:'How per-contact timezone delivery works and when to use it.', read:'3 min read', body:[
          'When you pick "Each contact\'s local timezone" ' + APP + ' looks up each contact\'s timezone (from their phone country code or a custom field) and times the send so it lands at your chosen clock time in their region.',
          'Caveat: contacts without a known timezone fall back to the workspace default.'
        ] },
        { id:'sc-2', cat:'scheduled', title:'Recurring sends — daily, weekly, monthly', desc:'Birthday wishes, weekly newsletters, monthly invoices on autopilot.', read:'4 min read', body:[
          'Recurring schedules support daily, weekly (with day-of-week selection), and monthly cadences.',
          'Pair with dynamic groups (like "Birthdays this week") for hands-free delivery.'
        ] },
        { id:'sc-3', cat:'scheduled', title:'Pause a recurring send temporarily', desc:'Going on holiday? Skip a week without losing the schedule.', read:'2 min read', body:[
          'Each recurring schedule has a "Pause" button — paused schedules skip until you resume.',
          'You can also "Skip next" if you only want to miss one occurrence.'
        ] },

        { id:'wh-1', cat:'webhooks', title:'Verify a webhook signature', desc:'How to check the X-WaDesk-Signature header and reject forged requests.', read:'5 min read', body:[
          'Every webhook payload includes <span class="font-mono">X-WaDesk-Signature: t=...,v1=...</span>.',
          'To verify: take the raw request body, prepend <span class="font-mono">t.</span> + timestamp, then HMAC-SHA256 with your endpoint secret. Compare against <span class="font-mono">v1</span> using a constant-time equality check.',
          'Reject the request if the signature doesn\'t match or the timestamp is older than 5 minutes (replay protection).'
        ] },
        { id:'wh-2', cat:'webhooks', title:'Why my webhook keeps timing out', desc:'10-second timeout, 3 retries — what causes it and how to fix.', read:'4 min read', body:[
          APP + ' allows a 10-second response window. Heavy synchronous work in your handler is the #1 cause of timeouts.',
          '<b>Fix:</b> respond <span class="font-mono">200 OK</span> immediately, then process the event in a background job.',
          'Failed events are retried up to 3 times with exponential backoff (30s, 2min, 10min).'
        ] },
        { id:'wh-3', cat:'webhooks', title:'Replay a failed delivery', desc:'You shipped a fix — now retrigger the events you missed.', read:'2 min read', body:[
          'Open <span class="font-mono">Webhooks → [endpoint] → Deliveries</span>, filter by failed, click Replay on a row or use the bulk Retry button.',
          'Replays use the original payload — they\'re byte-for-byte identical to the first attempt.'
        ] },

        { id:'fl-1', cat:'flows', title:'Build your first flow', desc:'Triggers, actions, branches — connect them with the visual builder.', read:'7 min read', body:[
          'A flow has three building blocks: <b>triggers</b> (what starts it), <b>conditions</b> (decide which branch), <b>actions</b> (what to do).',
          'Drag a trigger from the left panel onto the canvas, drop an action below, draw a line between them. Hit Publish.'
        ] },
        { id:'fl-2', cat:'flows', title:'Reading flow logs', desc:'See every step of every run, why a branch was taken, and where it failed.', read:'4 min read', body:[
          'Each flow run has a log entry. Click a run to see step-by-step state, including the variables that were evaluated at each branch.',
          'Failed steps show the exact error and a Retry button.'
        ] },

        { id:'co-1', cat:'contacts', title:'Build a smart segment', desc:'Filter contacts by tags, custom fields, last activity, and more.', read:'5 min read', body:[
          'Segments are saved searches — they update automatically as new contacts match the rules.',
          'Combine multiple conditions with AND / OR. Example: "tag = vip AND last_activity within 30 days".'
        ] },
        { id:'co-2', cat:'contacts', title:'Bulk-tag from search results', desc:'Select hundreds of contacts at once and apply tags or move to a group.', read:'3 min read', body:[
          'Run any search or open a segment, click "Select all" in the header, then use the bulk action menu — Tag / Move / Export / Delete.'
        ] },

        { id:'bl-1', cat:'billing', title:'How metered billing works', desc:'Per-message pricing, what counts as a "message", and where to track usage.', read:'4 min read', body:[
          'Each conversation-initiated message charges once at the per-template rate set by Meta for the recipient\'s country.',
          'Service-window replies (within 24h of a user-initiated chat) are free.',
          'Track real-time usage in <span class="font-mono">Settings → Plan & usage</span>.'
        ] },
        { id:'bl-2', cat:'billing', title:'Change plan without losing data', desc:'Upgrade or downgrade is instant — here\'s what happens to your seats and history.', read:'3 min read', body:[
          'Plan changes prorate immediately. Seats above the new plan limit become read-only until removed.',
          'No data is ever deleted on downgrade.'
        ] },
        { id:'bl-3', cat:'billing', title:'Update payment method or VAT info', desc:'Cards, banks, billing address, and tax IDs — all in one place.', read:'2 min read', body:[
          'Open <span class="font-mono">Settings → Billing → Payment method</span> to swap cards. The new card is validated with a $0 auth before being saved.',
          'VAT/GST numbers go under "Billing details" and apply on the next invoice.'
        ] },

        { id:'tr-1', cat:'troubleshooting', title:'My message is stuck in queued', desc:'Diagnose why a send hasn\'t left the queue and how to unstick it.', read:'5 min read', body:[
          'First, check device status in <span class="font-mono">Settings → Devices</span>. A disconnected number won\'t send anything.',
          'Second, check for rate limit errors in the message\'s delivery log. WhatsApp throttles unverified numbers.',
          'Third, confirm the recipient number exists on WhatsApp. We test before sending; numbers that fail the lookup move to "Failed", not "Queued".'
        ] },
        { id:'tr-2', cat:'troubleshooting', title:'Read receipts not updating', desc:'When ticks go grey instead of blue and how to refresh them.', read:'3 min read', body:[
          'WhatsApp doesn\'t send a read receipt if the recipient has read receipts disabled in their privacy settings — this is a hard limit on Meta\'s side.',
          'Force a sync from <span class="font-mono">Settings → Devices → Resync</span> if you suspect a ' + APP + '-side issue.'
        ] },
        { id:'tr-3', cat:'troubleshooting', title:'2FA is locking me out', desc:'Lost your authenticator? Recover access via backup codes or support.', read:'2 min read', body:[
          'Use one of the 10 backup codes you saved at setup. Each works once.',
          'No backup codes? Email <span class="font-mono">recovery@wadesk.app</span> from the email on your account — we\'ll verify identity and reset 2FA within 24 hours.'
        ] },
      ];

      // Compute counts
      const counts = {};
      ARTICLES.forEach(a => { counts[a.cat] = (counts[a.cat] || 0) + 1; });

      let activeCat = 'all';
      let query = '';

      function filtered() {
        return ARTICLES.filter(a => {
          const inCat = activeCat === 'all' || a.cat === activeCat;
          const q = query.trim().toLowerCase();
          const inQ = !q || a.title.toLowerCase().includes(q) || a.desc.toLowerCase().includes(q) || a.body.join(' ').toLowerCase().includes(q);
          return inCat && inQ;
        });
      }

      function renderList() {
        const list = filtered();
        const root = document.getElementById('articles');
        root.innerHTML = '';
        list.forEach(a => {
          const card = document.createElement('a');
          card.href = '#' + a.id;
          card.className = 'block bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card hover:border-wa-deep hover:shadow-soft transition group';
          card.innerHTML = `
            <div class="flex items-center gap-2 mb-2">
              <span class="text-[10.5px] font-mono px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep">${CATS[a.cat]?.pill || a.cat}</span>
              <span class="text-[10.5px] font-mono text-ink-500">${a.read}</span>
            </div>
            <h3 class="font-serif text-[18px] leading-tight mb-1.5">${a.title}</h3>
            <p class="text-[12.5px] text-ink-500 leading-snug">${a.desc}</p>
            <span class="mt-3 inline-flex items-center gap-1 text-[12px] text-wa-deep font-semibold group-hover:underline">Read article
              <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 3l5 5-5 5"/></svg>
            </span>`;
          card.onclick = e => { e.preventDefault(); openArticle(a.id); };
          root.appendChild(card);
        });
        document.getElementById('empty').classList.toggle('hidden', list.length > 0);
        document.getElementById('cat-title').textContent = CATS[activeCat]?.label || 'All articles';
        document.getElementById('cat-count').textContent = list.length + ' article' + (list.length === 1 ? '' : 's');
      }

      function openArticle(id) {
        const a = ARTICLES.find(x => x.id === id); if (!a) return;
        document.getElementById('list-view').classList.add('hidden');
        document.getElementById('article-view').classList.remove('hidden');
        document.getElementById('art-cat').textContent = CATS[a.cat]?.pill || a.cat;
        document.getElementById('art-title').textContent = a.title;
        document.getElementById('art-readtime').textContent = a.read;
        document.getElementById('art-body').innerHTML = a.body.map(p => '<p>' + p + '</p>').join('');
        // related: same category, exclude self, take 2
        const related = ARTICLES.filter(x => x.cat === a.cat && x.id !== a.id).slice(0, 2);
        const rel = document.getElementById('art-related');
        rel.innerHTML = '';
        related.forEach(r => {
          const card = document.createElement('a');
          card.href = '#' + r.id;
          card.className = 'block bg-paper-0 border border-paper-200 rounded-[10px] p-4 hover:border-wa-deep transition';
          card.innerHTML = `
            <div class="flex items-center gap-2 mb-1.5">
              <span class="text-[10.5px] font-mono px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep">${CATS[r.cat]?.pill || r.cat}</span>
              <span class="text-[10.5px] font-mono text-ink-500">${r.read}</span>
            </div>
            <div class="font-serif text-[15px] leading-tight">${r.title}</div>`;
          card.onclick = e => { e.preventDefault(); openArticle(r.id); window.scrollTo({top:0,behavior:'smooth'}); };
          rel.appendChild(card);
        });
        window.scrollTo({top:0,behavior:'smooth'});
      }

      document.getElementById('back-btn').addEventListener('click', () => {
        document.getElementById('article-view').classList.add('hidden');
        document.getElementById('list-view').classList.remove('hidden');
        window.scrollTo({top:0,behavior:'smooth'});
      });

      document.querySelectorAll('#cats .cat').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('#cats .cat').forEach(x => {
          x.classList.remove('bg-wa-deep','text-paper-0');
          x.classList.add('hover:bg-paper-50/60');
        });
        b.classList.add('bg-wa-deep','text-paper-0');
        activeCat = b.dataset.cat;
        document.getElementById('list-view').classList.remove('hidden');
        document.getElementById('article-view').classList.add('hidden');
        renderList();
      }));

      document.getElementById('search').addEventListener('input', e => {
        query = e.target.value;
        renderList();
      });

      // Popular pills
      document.querySelectorAll('#list-view .px-2\\.5.py-1.rounded-full.border').forEach(b => {
        b.addEventListener('click', () => {
          document.getElementById('search').value = b.textContent.trim();
          query = b.textContent.trim();
          renderList();
        });
      });

      renderList();
}
