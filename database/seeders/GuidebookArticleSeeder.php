<?php

namespace Database\Seeders;

use App\Models\GuidebookArticle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the guidebook with the 29 starter articles that were in the
 * original static prototype. Idempotent — uses updateOrCreate keyed by
 * slug so re-running is safe and doesn't duplicate.
 *
 * Body text is Markdown — the user-side view renders it via
 * league/commonmark (with a plain-text fallback if not installed).
 */
class GuidebookArticleSeeder extends Seeder
{
    public function run(): void
    {
        $i = 0;
        foreach ($this->articles() as $row) {
            $row['sort_order']   = $i++;
            $row['is_published'] = true;
            $row['published_at'] = now();
            $row['slug']         = $row['slug'] ?? Str::slug($row['title']);
            GuidebookArticle::updateOrCreate(
                ['slug' => $row['slug']],
                $row,
            );
        }
    }

    private function articles(): array
    {
        return [
            // ─── Getting started ──────────────────────────────────────
            ['title' => 'Connect your first WhatsApp number', 'category' => 'Getting started',
             'excerpt' => 'Pair a phone, verify with Meta, and send your first test message in under 10 minutes.',
             'body' => <<<MD
Connecting your first WhatsApp number is the foundation of every workflow.

## What you'll need
- A spare phone with **WhatsApp installed** (it doesn't need to stay logged in elsewhere)
- 10 minutes of quiet time

## Steps
1. Open **Devices** → **Connect new**
2. Pick the country code, type the phone number
3. Wait for the QR — scan it from WhatsApp on the phone (Linked devices → Link)
4. Wait ~30 seconds — your device flips to "Connected"
5. Send a test message to yourself from `/chat`

If the QR expires before you scan, just click **Generate new QR**. That's normal — the codes rotate every 60 seconds for security.
MD],

            ['title' => 'Invite teammates and set roles', 'category' => 'Getting started',
             'excerpt' => 'Add seats, scope what each role can see, and pass the keys without giving away the kingdom.',
             'body' => <<<MD
Roles: **Owner** (full control), **Admin** (everything except billing), **Manager** (campaigns + flows + chat), **Agent** (chat + assigned tickets), **Viewer** (read-only).

Invite from `/settings → Team`. They get a magic-link email; on first sign-in they set their own password.

> Tip: most support reps only need **Agent** — they can answer chat tickets without seeing billing or being able to delete templates.
MD],

            ['title' => 'Import your existing contacts (CSV)', 'category' => 'Getting started',
             'excerpt' => 'Map columns, de-duplicate by phone, and dry-run the import before it touches anything.',
             'body' => <<<MD
1. Go to `/contacts → Import`
2. Upload a CSV. First row should be headers.
3. Map columns: phone is **required**; name, email, tags, custom fields are optional
4. Check **De-duplicate by phone** — recommended
5. Click **Dry run** first — see how many rows would be created vs updated
6. Commit the import

Phones get normalised to E.164 automatically. Anything that can't be parsed lands in a separate "needs review" bucket.
MD],

            ['title' => 'A 5-minute tour of the dashboard', 'category' => 'Getting started',
             'excerpt' => 'What every tile means, how to read the daily pulse, and where to drill into the numbers.',
             'body' => <<<MD
Six tiles, one job each.

- **Messages** — total sends in the time window. Click → opens the campaigns + chat breakdown.
- **Delivery rate** — % delivered out of total attempted. Anything under 95% wants attention.
- **Read rate** — depends on template type; a "your order shipped" should hit 80%+, a marketing promo 30-50% is normal.
- **Reply rate** — your secret KPI. Below 2% means the message isn't compelling enough.
- **Active workspaces** — multi-tenant view (admins only).
- **Device health** — green = all paired devices online, amber = at least one disconnected.

Click any tile to drill in.
MD],

            ['title' => 'Switch from another platform without losing data', 'category' => 'Getting started',
             'excerpt' => 'Templates, contacts, message history — all the moving parts you need to migrate.',
             'body' => <<<MD
Migration order matters:

1. **Templates first** — recreate every approved template on WaDesk. Meta caches approval per BSP, so you'll re-submit.
2. **Contacts** — CSV import (see the contacts article).
3. **Active chats** — there's no clean way to import live conversation history; usually you continue old threads in the old tool and start new ones here.
4. **Webhooks** — point your CRM at our webhook URL. Old tool's signing key won't match ours.

Plan ~3 days of overlap. Don't try to switch overnight.
MD],

            // ─── Campaigns ────────────────────────────────────────────
            ['title' => 'Run your first WhatsApp blast', 'category' => 'Campaigns',
             'excerpt' => 'Build a list, pick a template, schedule the send, and read the delivery report.',
             'body' => <<<MD
The four-step ritual:

1. **Audience** — pick a contacts group or build a segment filter
2. **Template** — must be approved + relevant (don't send "abandoned cart" to people who never had a cart)
3. **Variables** — the dropdown shows every field your template references. Use defaults for blanks.
4. **Schedule** — now, or pick a window

After it sends, the campaign detail page shows delivery, read, reply, failed counts in real time. Failures are categorised — capacity, opted-out, invalid number — so you know what to fix.
MD],

            ['title' => 'Why my campaign read rate is low', 'category' => 'Campaigns',
             'excerpt' => 'Common reasons messages get delivered but not opened — and how to fix each.',
             'body' => <<<MD
The five most common reasons:

- **Wrong template category** — Marketing templates get filtered into "promotional" on the recipient side. Use Utility when possible.
- **Bad timing** — sending at 3 am local time? Read rates drop.
- **Fatigued audience** — too many promos in a week. Throttle.
- **Stale segment** — list hasn't been cleaned in 6+ months. Stale numbers, opted-out users.
- **Unclear "from"** — display name not recognised because the account isn't verified.

Each one is fixable. Don't blame WhatsApp.
MD],

            ['title' => 'A/B test two template variants', 'category' => 'Campaigns',
             'excerpt' => 'Split a group, fire both, and let WaDesk pick the winner.',
             'body' => <<<MD
1. Create both templates (variant A and variant B)
2. In campaign builder, enable **A/B test** toggle
3. Pick the % split (usually 50/50)
4. Pick the success metric — read, reply, or click

WaDesk sends both during the same window. Winner is highlighted in the report. Save the winner as your "champion" for future campaigns.
MD],

            ['title' => 'Stop a running campaign cleanly', 'category' => 'Campaigns',
             'excerpt' => 'Pause vs cancel — and what happens to messages already in-flight.',
             'body' => <<<MD
**Pause** — stops new sends from leaving the queue. Already-sent messages stay sent. Resume later from the same place.

**Cancel** — same as pause, but the campaign is marked closed; you can't resume. Reports stay.

Both happen near-instantly. Messages already handed to WhatsApp servers are out of our hands — they'll deliver as normal.
MD],

            // ─── Templates ────────────────────────────────────────────
            ['title' => 'Why was my template rejected?', 'category' => 'Templates',
             'excerpt' => 'The 6 most common rejection reasons and a checklist to avoid them.',
             'body' => <<<MD
Top reasons:
1. **Wrong category** — Marketing content submitted as Utility (or vice versa)
2. **Suggestive promo wording** — "Free!", "Click now!", "Hot deal!"
3. **Missing opt-in context** — "Hi {{1}}" alone, no business name or reason
4. **Bad variables** — `{{1}}` with no example value
5. **Footer disallowed content** — links or phone numbers in the footer
6. **Buttons exceeding limits** — max 3 quick-reply buttons OR 2 CTA buttons

Pre-flight checklist before submitting: business name visible, opt-in implied, no exclamation marks, variable examples set.
MD],

            ['title' => 'How to write a template that gets approved fast', 'category' => 'Templates',
             'excerpt' => 'A working formula: greeting → context → action → footer. With examples.',
             'body' => <<<MD
Formula that gets through Meta review in <24h:

> Hi {{1}}, this is {{2}} from BrandName.
> Your order #{{3}} is on its way — expected by {{4}}.
> Track here: {{5}}
> Reply STOP to opt out.

Why it works:
- Names you (greeting)
- Names yourself (context)
- States the reason (action)
- Has an opt-out (compliance)

Add a button only if it's a real CTA. Decorative buttons get flagged.
MD],

            ['title' => 'Variables, components, and CTAs explained', 'category' => 'Templates',
             'excerpt' => 'When to use a header variable, when to use a button, and when to skip both.',
             'body' => <<<MD
**Header** — used for the order number, customer name, or image. One variable max.

**Body** — up to 7 variables. Use sparingly — every variable is a place to render incorrectly.

**Footer** — static only. No variables, no links.

**Buttons**:
- Quick reply: max 3, for getting structured responses
- CTA (URL): max 2, opens a link
- Phone: max 1, opens dialler

Don't mix quick replies + CTAs in the same template — Meta will reject.
MD],

            ['title' => "Edit an approved template without re-submitting", 'category' => 'Templates',
             'excerpt' => "When you can edit live, when you can't, and how to version safely.",
             'body' => <<<MD
Editable without re-approval: body wording (minor), button label text.

Requires re-approval: header type, button type, variable count, language.

Safest workflow: clone → edit clone → submit → wait for approval → archive the old one. Keeps your campaigns running while review is pending.
MD],

            // ─── Auto-reply ───────────────────────────────────────────
            ['title' => 'Stop auto-reply spam: cooldowns explained', 'category' => 'Auto-reply',
             'excerpt' => 'Why a 60-second cooldown saves you from looking like a robot.',
             'body' => <<<MD
Without a cooldown, "hi" → "Hi! How can I help?" → "hi again" → "Hi! How can I help?" — infinite loop with bots, infinite spam with humans typing rapidly.

Set cooldown to **60 seconds** per contact, per rule. The rule won't re-fire for that contact until the window expires. Saves you from looking like a broken machine.

For high-intent flows (refund request, "speak to human") set cooldown to 0 — fast iteration is more important than spam protection.
MD],

            ['title' => 'Fuzzy match vs exact match — which to use', 'category' => 'Auto-reply',
             'excerpt' => 'When typo-tolerance helps and when it backfires.',
             'body' => <<<MD
**Exact** — keyword is "track" — only fires on the literal word. Safer. Use for:
- Compliance keywords (STOP, START)
- Order-number triggers (#12345)

**Fuzzy** — "track", "trak", "Track my order", "trakc" all match. Use for:
- Customer help requests where typos are common
- Common verbs ("status", "where", "help")

Don't fuzzy-match "stop" — you'll opt people out who type "stopwatch".
MD],

            ['title' => 'Schedule auto-replies to business hours', 'category' => 'Auto-reply',
             'excerpt' => 'Have a different message for nights and weekends.',
             'body' => <<<MD
Per-rule schedule: only fire Mon–Fri, 9am–6pm.

Outside hours, set up a separate "we're closed" rule for the same trigger that promises a reply on the next business day.

Honour the workspace timezone (set at `/settings → Branding`). All cron jobs respect it.
MD],

            // ─── Scheduled sends ──────────────────────────────────────
            ['title' => "Send at the recipient's local time", 'category' => 'Scheduled sends',
             'excerpt' => 'How per-contact timezone delivery works and when to use it.',
             'body' => <<<MD
If your contacts have a timezone field, schedule a "9am local" send. Each contact gets it at their own 9am, not yours.

Without a timezone on the contact, we fall back to the **workspace timezone**.

When to use: birthday wishes, daily quote, time-sensitive offers (lunch deals).
When NOT to use: time-critical updates (a delivery slot, an OTP) — those should send immediately.
MD],

            ['title' => 'Recurring sends — daily, weekly, monthly', 'category' => 'Scheduled sends',
             'excerpt' => 'Birthday wishes, weekly newsletters, monthly invoices on autopilot.',
             'body' => <<<MD
Three knobs:
- **Frequency**: daily / weekly / monthly / cron expression
- **End date**: optional, defaults to never
- **Skip days**: e.g. "skip weekends"

The scheduler computes the next run on every save. Visible in the campaign list as "Next: Tue 09:00".
MD],

            ['title' => 'Pause a recurring send temporarily', 'category' => 'Scheduled sends',
             'excerpt' => 'Going on holiday? Skip a week without losing the schedule.',
             'body' => <<<MD
On the scheduled-send detail page, click **Pause**. State is saved; next-run-at is cleared. When you click **Resume**, we compute the next valid slot from now.

You can also pre-schedule a pause: "Pause from Dec 23 to Jan 2". Useful for office shutdowns.
MD],

            // ─── Webhooks ─────────────────────────────────────────────
            ['title' => 'Verify a webhook signature', 'category' => 'Webhooks',
             'excerpt' => 'How to check the X-WaDesk-Signature header and reject forged requests.',
             'body' => <<<MD
Every webhook carries `X-WaDesk-Signature: sha256=<hex>`.

The hex is `HMAC-SHA256(secret, rawBody)`. Compute the same on your side and compare — use a constant-time string compare to avoid timing attacks.

```php
\$expected = 'sha256=' . hash_hmac('sha256', \$rawBody, \$secret);
if (! hash_equals(\$expected, \$request->header('X-WaDesk-Signature'))) {
    abort(401);
}
```

Your secret lives at `/settings → Webhooks` — never paste it into code.
MD],

            ['title' => 'Why my webhook keeps timing out', 'category' => 'Webhooks',
             'excerpt' => '10-second timeout, 3 retries — what causes it and how to fix.',
             'body' => <<<MD
We give your endpoint **10 seconds** to return a 2xx. If you don't, we retry up to 3 times with backoff (1s, 5s, 30s).

Common causes:
- DB query in the request path that takes 8+ seconds → move to a queue
- External HTTP call (Stripe, Salesforce) blocking → same
- Cold start on a serverless function — first hit hangs

Best practice: respond 200 OK immediately, do the work async. We don't care what your handler does — just that you ack quickly.
MD],

            ['title' => 'Replay a failed delivery', 'category' => 'Webhooks',
             'excerpt' => 'You shipped a fix — now retrigger the events you missed.',
             'body' => <<<MD
On `/settings → Webhooks → Activity`, every delivery has a **Replay** button. Click → we re-fire the original payload to your endpoint, with a fresh signature.

Bulk replay: filter by date range + status=failed, then **Replay all**. Last 30 days of events are kept.
MD],

            // ─── Flows ────────────────────────────────────────────────
            ['title' => 'Build your first flow', 'category' => 'Flows',
             'excerpt' => 'Triggers, actions, branches — connect them with the visual builder.',
             'body' => <<<MD
Three node types:
- **Trigger** — keyword, inbound message, schedule
- **Action** — send template, set tag, update contact, call webhook
- **Branch** — condition (has tag? answered yes?) splits the flow into two paths

Start with a simple flow: trigger on "menu" → send the menu template → branch on the user's reply.

You can drag-drop, save partial drafts, and test with a `/flows test` mode that simulates the trigger without touching real users.
MD],

            ['title' => 'Reading flow logs', 'category' => 'Flows',
             'excerpt' => 'See every step of every run, why a branch was taken, and where it failed.',
             'body' => <<<MD
Each run has a timeline: trigger fired at 12:01, action sent at 12:01 (200ms), branch evaluated at 12:02, …

Hover any step for the full payload. Errors are red — click for the stack snippet (sanitised).

Replay a failed run with **Replay from here** — picks up at the failed step instead of starting over.
MD],

            // ─── Contacts ─────────────────────────────────────────────
            ['title' => 'Build a smart segment', 'category' => 'Contacts',
             'excerpt' => 'Filter contacts by tags, custom fields, last activity, and more.',
             'body' => <<<MD
Segments are saved filters. Build one at `/contacts → Filters → Save as segment`.

Example: "Last message sent > 30 days ago AND tag = customer AND country = IN" → segment named "Indian customers to win back".

Use the segment as the audience for a campaign, or pin it to your dashboard for ongoing visibility.
MD],

            ['title' => 'Bulk-tag from search results', 'category' => 'Contacts',
             'excerpt' => 'Select hundreds of contacts at once and apply tags or move to a group.',
             'body' => <<<MD
1. Search or filter the contacts list
2. Click the checkbox next to "Name" — selects everyone on this page
3. **Select all matching** appears in the toolbar — click it to extend to all filter matches
4. Pick the bulk action: tag, untag, move to group, export, delete

Bulk-tag is the fastest way to clean up imported contacts.
MD],

            // ─── Billing ──────────────────────────────────────────────
            ['title' => 'How metered billing works', 'category' => 'Billing',
             'excerpt' => 'Per-message pricing, what counts as a "message", and where to track usage.',
             'body' => <<<MD
**What counts**:
- Sent: yes (each template send = 1 message)
- Received: no (we never bill for inbound)
- Failed sends: no
- Read receipts: no

**Pricing tiers**: defined per plan at `/admin/packages`. Usage tracked live at `/settings → Billing`.

Overage is billed at the end of the cycle, not on the spot — but the **wallet** mechanism (top-up credits) shows real-time deductions for total transparency.
MD],

            ['title' => 'Change plan without losing data', 'category' => 'Billing',
             'excerpt' => "Upgrade or downgrade is instant — here's what happens to your seats and history.",
             'body' => <<<MD
**Upgrade**: takes effect immediately. We pro-rate the difference and charge it on the next renewal date.

**Downgrade**: takes effect at the end of the current cycle. You keep all your data and seats until then.

If the new plan has fewer seats, the oldest non-admin agents are deactivated (not deleted — you can re-upgrade and re-activate).
MD],

            ['title' => 'Update payment method or VAT info', 'category' => 'Billing',
             'excerpt' => 'Cards, banks, billing address, and tax IDs — all in one place.',
             'body' => <<<MD
At `/settings → Billing`:

- **Payment method**: card / bank / UPI / wallet
- **Billing address**: name, address line, country (VAT calculation depends on this)
- **Tax ID (VAT / GSTIN)**: for B2B invoices

Changes apply to the next invoice; current invoices aren't re-issued automatically.
MD],

            // ─── Troubleshooting ──────────────────────────────────────
            ['title' => 'My message is stuck in queued', 'category' => 'Troubleshooting',
             'excerpt' => "Diagnose why a send hasn't left the queue and how to unstick it.",
             'body' => <<<MD
Check, in order:

1. **Device online?** `/devices` — green dot. If not, scan QR again.
2. **Daily limit hit?** Meta enforces 250 / 1k / 10k / 100k tiers based on your business verification level.
3. **Frozen rate limit?** Bursts above ~80 msg/min get throttled. We auto-retry.
4. **Template approval pending?** Check `/templates`.
5. **Contact opted-out?** Reply STOP at any time opts them out; subsequent sends fail with reason "opted_out".

If none of those, look at the `messages_outbound` row for the `failure_reason` column.
MD],

            ['title' => 'Read receipts not updating', 'category' => 'Troubleshooting',
             'excerpt' => 'When ticks go grey instead of blue and how to refresh them.',
             'body' => <<<MD
Receipts only land if the recipient has read receipts ENABLED in their WhatsApp settings. If they've disabled them, we can never know — show "delivered" forever.

If your status webhook is broken, we never receive the read event. Check `/settings → Webhooks → Activity`.

Pull-to-refresh: open the chat and scroll — we refetch status on demand.
MD],

            ['title' => '2FA is locking me out', 'category' => 'Troubleshooting',
             'excerpt' => 'Lost your authenticator? Recover access via backup codes or support.',
             'body' => <<<MD
Order of escalation:

1. **Backup codes** — you saved 10 when you enabled 2FA. Use one.
2. **Admin reset** — if you're a team member, the workspace owner can reset your 2FA.
3. **Email support** — last resort. Requires ID verification (this is a security feature, not friction). Allow 24h.

Set up 2FA on a NEW phone first before deleting the old one — saves the recovery dance.
MD],
        ];
    }
}
