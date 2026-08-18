<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds 10 real, detailed WhatsApp-business blog posts (+ categories) for the
 * marketing blog. Idempotent — re-running updates by slug, never duplicates.
 *   php artisan db:seed --class=Database\\Seeders\\BlogSeeder
 */
class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $cats = [];
        foreach ([
            'WhatsApp API'  => 'Cloud API, numbers, verification and setup.',
            'Marketing'     => 'Campaigns, broadcasts and growth on WhatsApp.',
            'Automation'    => 'Chatbots, flows, auto-replies and AI.',
            'Guides'        => 'Step-by-step playbooks and best practices.',
            'eCommerce'     => 'Selling, carts and orders over WhatsApp.',
        ] as $name => $desc) {
            $cats[$name] = BlogCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $desc]
            )->id;
        }

        $author = 'WaDesk Team';
        $i = 0;
        foreach ($this->posts() as $p) {
            $i++;
            BlogPost::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'title'            => $p['title'],
                    'excerpt'          => $p['excerpt'],
                    'body'             => $p['body'],
                    'category_id'      => $cats[$p['cat']] ?? null,
                    'tags'             => $p['tags'],
                    'author_name'      => $author,
                    'status'           => 'published',
                    'published_at'     => now()->subDays(30 - $i * 2),
                    'is_featured'      => $i === 1,
                    'meta_title'       => $p['title'] . ' | WaDesk',
                    'meta_description' => $p['excerpt'],
                    'meta_keywords'    => implode(', ', $p['tags']),
                ]
            );
        }
    }

    private function p(string $title, string $cat, string $excerpt, array $tags, string $body): array
    {
        return [
            'title'   => $title,
            'slug'    => Str::slug($title),
            'cat'     => $cat,
            'excerpt' => $excerpt,
            'tags'    => $tags,
            'body'    => $body,
        ];
    }

    private function posts(): array
    {
        return [
            $this->p(
                'WhatsApp Business API vs the WhatsApp Business App: which one is right for you',
                'WhatsApp API',
                'The free Business App suits a single phone and a small team; the Business API (Cloud API) unlocks automation, multiple agents, broadcasts and chatbots at scale. Here is how to choose.',
                ['WhatsApp Business API', 'Cloud API', 'getting started'],
                '<p>WhatsApp gives businesses two very different products, and picking the wrong one slows you down for months.</p>'
                . '<h2>The WhatsApp Business App</h2>'
                . '<p>A free mobile app tied to <strong>one phone number on one phone</strong>. It is perfect for solo founders and small shops: quick replies, labels, a catalog and away messages. But it has no real API, limited team access, and no way to run large broadcasts or connect a chatbot.</p>'
                . '<h2>The WhatsApp Business API (Cloud API)</h2>'
                . '<p>Built for scale. It lets you:</p>'
                . '<ul><li>Connect <strong>multiple agents</strong> to one number through a shared team inbox.</li>'
                . '<li>Send approved <strong>template messages</strong> to opted-in customers at volume.</li>'
                . '<li>Automate with <strong>chatbots, flows and AI</strong>.</li>'
                . '<li>Get a verified green badge and higher messaging limits over time.</li></ul>'
                . '<h2>How to decide</h2>'
                . '<p>If you are one person answering a handful of chats, start with the app. The moment you need a team, automation, or marketing broadcasts, move to the API — a platform like WaDesk connects to the official Cloud API and gives you the inbox, automation and analytics on top.</p>'
            ),
            $this->p(
                'How to get the green tick (verified badge) on WhatsApp Business',
                'WhatsApp API',
                'The green checkmark signals an official business account. It is granted by Meta based on brand notability and correct Business Manager setup — here is exactly what to prepare.',
                ['green tick', 'verification', 'Meta Business'],
                '<p>The green badge tells customers your account is the genuine, official business — not an impersonator. You cannot buy it; Meta grants it.</p>'
                . '<h2>The prerequisites</h2>'
                . '<ul><li>A <strong>verified Meta Business Manager</strong> account.</li>'
                . '<li>Your number connected to the <strong>WhatsApp Business API</strong> (not just the app).</li>'
                . '<li>A complete business profile: logo, description, website and address.</li></ul>'
                . '<h2>What Meta actually evaluates</h2>'
                . '<p>Verification leans heavily on <strong>notability</strong> — whether your brand is widely covered by independent, third-party news sources. Press coverage, a Wikipedia presence and an established website all help.</p>'
                . '<h2>How to apply</h2>'
                . '<p>Submit the Official Business Account request from WhatsApp Manager. Approval can take days to weeks. Until then you can still operate fully — the badge is reputational, not functional. Keep your profile consistent across your website and social channels to strengthen the case.</p>'
            ),
            $this->p(
                'WhatsApp message templates explained: categories, approval and avoiding rejection',
                'Guides',
                'Template messages are how you start conversations with customers. Get the category right, follow the formatting rules, and you will sail through Meta review instead of getting rejected.',
                ['message templates', 'approval', 'ban prevention'],
                '<p>Outside the 24-hour customer service window, you can only message customers with a <strong>pre-approved template</strong>. Templates are reviewed by Meta, so quality matters.</p>'
                . '<h2>The three categories</h2>'
                . '<ul><li><strong>Marketing</strong> — promotions, offers, announcements.</li>'
                . '<li><strong>Utility</strong> — order updates, receipts, appointment reminders.</li>'
                . '<li><strong>Authentication</strong> — one-time passcodes.</li></ul>'
                . '<p>Picking the wrong category is the #1 reason for rejection. An order update is <em>utility</em>, not marketing.</p>'
                . '<h2>Rules that get templates rejected</h2>'
                . '<ul><li>Placeholder abuse — starting or ending with a <code>{{1}}</code> variable, or two variables in a row.</li>'
                . '<li>Spammy language — "guaranteed", "100%", "act now".</li>'
                . '<li>Broken or shortened URLs and grammatical errors.</li></ul>'
                . '<h2>Tips to pass first time</h2>'
                . '<p>Write like a human, add sample values for every variable, and keep marketing opt-out friendly. WaDesk lints templates before you submit so you catch these issues before Meta does.</p>'
            ),
            $this->p(
                'How to send WhatsApp broadcasts at scale without getting banned',
                'Marketing',
                'Mass messaging works on WhatsApp — but only if you respect opt-in, quality ratings and sensible pacing. Follow this playbook to protect your number.',
                ['broadcast', 'bulk messaging', 'quality rating'],
                '<p>WhatsApp broadcasts reach customers where they actually read — open rates dwarf email. But aggressive blasting gets numbers flagged fast.</p>'
                . '<h2>Earn the opt-in first</h2>'
                . '<p>Only message people who agreed to hear from you. Collect consent at checkout, on your website, or via a click-to-WhatsApp ad. Buying lists is the fastest way to a ban.</p>'
                . '<h2>Watch your quality rating</h2>'
                . '<p>Meta scores each number Green/Yellow/Red based on how recipients react. Too many <strong>blocks and "report" taps</strong> drop your rating and cap your daily limits. Relevant, wanted messages keep you green.</p>'
                . '<h2>Pace and warm up</h2>'
                . '<ul><li>Start small and grow volume as your tier increases (1K to 10K to 100K).</li>'
                . '<li>Add a gap between sends instead of firing everything at once.</li>'
                . '<li>Always include a clear opt-out.</li></ul>'
                . '<p>WaDesk handles pacing, batching and per-number warm-up automatically so your broadcasts land without burning the number.</p>'
            ),
            $this->p(
                'Build a WhatsApp chatbot in an afternoon: a no-code flow guide',
                'Automation',
                'A good WhatsApp bot answers FAQs, qualifies leads and books appointments around the clock. With a visual flow builder you can ship one without writing code.',
                ['chatbot', 'flow builder', 'no-code'],
                '<p>Customers expect instant replies. A WhatsApp chatbot handles the repetitive 80% so your team focuses on the conversations that need a human.</p>'
                . '<h2>Start with the questions you already answer</h2>'
                . '<p>Pull your ten most common questions — hours, pricing, order status, returns. Each becomes a branch in your flow.</p>'
                . '<h2>Design the flow</h2>'
                . '<ul><li>A friendly greeting with a <strong>menu of buttons</strong>.</li>'
                . '<li>Branches for each topic, with quick replies to keep it tappable.</li>'
                . '<li>An "talk to a human" exit that hands off to your team inbox.</li></ul>'
                . '<h2>Add intelligence</h2>'
                . '<p>Plug an AI node into the flow so free-text questions get answered from your own knowledge base instead of dead-ending. Then layer in actions: book a slot, create a deal, push to a Google Sheet.</p>'
                . '<p>In WaDesk the whole thing is drag-and-drop — no developers required — and works across the Official API and the Unofficial API engines.</p>'
            ),
            $this->p(
                'Click-to-WhatsApp ads: the complete guide to CTWA campaigns',
                'Marketing',
                'Click-to-WhatsApp ads on Facebook and Instagram drop people straight into a chat with you. They are one of the cheapest, highest-intent lead sources available today.',
                ['CTWA', 'Meta ads', 'lead generation'],
                '<p>A Click-to-WhatsApp (CTWA) ad replaces the landing page with a conversation. The customer taps your Instagram or Facebook ad and a WhatsApp chat opens, pre-filled and ready.</p>'
                . '<h2>Why they convert</h2>'
                . '<p>There is no form to fill, no page to load. You capture the lead the instant they message, and you keep the conversation (and the phone number) forever.</p>'
                . '<h2>Setting one up</h2>'
                . '<ul><li>Connect your WhatsApp number to a Meta Ad Account.</li>'
                . '<li>Choose the <strong>Engagement</strong> or <strong>Sales</strong> objective with WhatsApp as the destination.</li>'
                . '<li>Write a welcome message and, ideally, route new chats into an automated flow that qualifies them.</li></ul>'
                . '<h2>Measure what matters</h2>'
                . '<p>Track cost-per-conversation, not just cost-per-click. Tie each chat to a deal so you can see real revenue per campaign. WaDesk creates the full ad (campaign, ad set, creative) and routes replies into your inbox and pipeline.</p>'
            ),
            $this->p(
                'WhatsApp marketing best practices: opt-in, frequency and compliance',
                'Guides',
                'WhatsApp is a permission-first channel. Respect it and you get unmatched engagement; abuse it and you lose the number. These are the rules that keep you safe and effective.',
                ['best practices', 'opt-in', 'compliance'],
                '<p>WhatsApp is the most personal channel you have. The brands that win treat it like a privilege, not a megaphone.</p>'
                . '<h2>Always get explicit opt-in</h2>'
                . '<p>Consent must be clear and specific. A pre-ticked box is not consent. Tell people what they are signing up for and how often you will message.</p>'
                . '<h2>Respect frequency</h2>'
                . '<p>One welcome message and the occasional, genuinely useful update beats weekly promos. Every irrelevant message risks a block.</p>'
                . '<h2>Make opting out effortless</h2>'
                . '<p>Honour "STOP" instantly and suppress those contacts automatically.</p>'
                . '<h2>Personalise and segment</h2>'
                . '<p>Use names, order history and tags so each message feels one-to-one. Segmented sends to engaged contacts keep your quality rating green and your costs down.</p>'
            ),
            $this->p(
                'Recover abandoned carts on WhatsApp: a 3-message sequence that works',
                'eCommerce',
                'Most online carts are abandoned. A short, well-timed WhatsApp sequence recovers a meaningful slice of that lost revenue — far better than email alone.',
                ['abandoned cart', 'eCommerce', 'recovery'],
                '<p>Shoppers abandon carts constantly — distraction, price hesitation, a slow checkout. WhatsApp brings them back because it actually gets read.</p>'
                . '<h2>The sequence</h2>'
                . '<ul><li><strong>+1 hour</strong> — a friendly nudge: "Still thinking it over? Your cart is saved." Include the product and a one-tap link back.</li>'
                . '<li><strong>+24 hours</strong> — add reassurance: reviews, free shipping, easy returns.</li>'
                . '<li><strong>+48 hours</strong> — a gentle, optional incentive (a small discount) for the fence-sitters.</li></ul>'
                . '<h2>Keep it human</h2>'
                . '<p>Two or three messages, then stop. Always let them reply to a real person if they have a question.</p>'
                . '<h2>Automate it</h2>'
                . '<p>Wire your store (Shopify, WooCommerce) to WaDesk so abandoned carts trigger the sequence automatically, and cancel the moment the order is placed.</p>'
            ),
            $this->p(
                'WhatsApp Business API pricing explained: conversation-based billing',
                'WhatsApp API',
                'WhatsApp does not charge per message — it charges per 24-hour conversation, and the price depends on the category and country. Here is how to read your bill and cut costs.',
                ['pricing', 'conversation pricing', 'costs'],
                '<p>WhatsApp Cloud API billing confuses everyone at first because it is <strong>not per message</strong>. You pay per <em>conversation</em> — a 24-hour window of messaging with one customer.</p>'
                . '<h2>The conversation categories</h2>'
                . '<ul><li><strong>Marketing</strong> — promotional outreach (the priciest).</li>'
                . '<li><strong>Utility</strong> — transactional updates tied to an order or account.</li>'
                . '<li><strong>Authentication</strong> — OTPs.</li>'
                . '<li><strong>Service</strong> — customer-initiated chats (often free up to a monthly allowance).</li></ul>'
                . '<h2>Price varies by country</h2>'
                . '<p>Rates differ a lot between markets — India, Brazil and the US all price differently. Check Meta\'s current rate card for your audience.</p>'
                . '<h2>How to lower costs</h2>'
                . '<p>Let customers start conversations (service messages are cheap or free), bundle updates into one window, and use utility templates instead of marketing where it genuinely applies.</p>'
            ),
            $this->p(
                'Automate WhatsApp with keyword auto-replies and drip campaigns',
                'Automation',
                'Two simple automations cover most of what a small team needs: instant keyword replies for FAQs, and scheduled drip sequences for onboarding and nurture.',
                ['auto-reply', 'drip campaign', 'automation'],
                '<p>You do not need a full chatbot to save hours every week. Two lightweight automations do most of the work.</p>'
                . '<h2>Keyword auto-replies</h2>'
                . '<p>Map common words to instant answers — "hours", "price", "location", "menu". When a customer sends a matching keyword, they get the right reply in milliseconds, day or night. Add a cooldown so the same person is not spammed.</p>'
                . '<h2>Drip campaigns</h2>'
                . '<p>A drip is a pre-planned series sent over days: a welcome on day 0, tips on day 2, a special offer on day 5. Perfect for onboarding new customers or nurturing leads from a CTWA ad.</p>'
                . '<h2>Combine them</h2>'
                . '<p>Trigger a drip when a contact gets a tag or joins from an ad, and let keyword replies handle anything they ask in between. WaDesk runs both on the Official and Unofficial API engines, with full opt-out handling built in.</p>'
            ),
        ];
    }
}
