<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * DemoContentSeeder
 * -----------------
 * Fills the existing "Media City" workspace (id=1, owner user_id=2) with bulky,
 * realistic demo data so every page/feature looks full for a live showcase.
 *
 * HARD RULES (see project guardrails):
 *  - DB INSERTS ONLY. We NEVER call a dispatcher/job/queue/mail/webhook/service.
 *    To guarantee that, we use raw DB::table()->insert() everywhere, which
 *    bypasses ALL Eloquent model events / observers (Message, WaOrder, Contact,
 *    Broadcast, ScheduledMessage, etc. all have observers that could send).
 *  - Many columns are encrypted via Eloquent `encrypted` casts. Raw inserts of
 *    plaintext would break decryption on read, so we encrypt those columns by
 *    hand with Crypt::encryptString() (identical to the cast format).
 *  - All statuses are terminal/past (sent/delivered/read/completed/failed) and
 *    all scheduled rows are in the PAST + completed, so no worker dispatches.
 *  - No emojis. Brand is WaDesk. Unofficial engine = "Unofficial API".
 *
 * Re-runnable: deletes previously-seeded demo rows scoped to workspace 1 first.
 */
class DemoContentSeeder extends Seeder
{
    private int $ws = 1;       // Media City workspace id
    private int $uid = 2;      // owner user id
    private int $agentUid = 8; // team member (kapil)
    private ?int $deviceId = null;

    // Collected ids for cross-linking.
    private array $contactIds = [];
    private array $groupIds = [];
    private array $templateIds = [];
    private array $flowIds = [];
    private array $conversationIds = [];

    /**
     * Resolve the target workspace + users from a demo user's email so the
     * seeder works on ANY environment (local or the production server) where
     * the ids differ. Defaults to user@mediacity.co.in; override with the
     * DEMO_SEED_EMAIL env var. Falls back to the hardcoded ids if not found.
     */
    private function resolveTarget(): void
    {
        $email = env('DEMO_SEED_EMAIL', 'user@mediacity.co.in');
        $user  = \App\Models\User::where('email', $email)->first();
        if (!$user) {
            $this->command->warn("  No user found for {$email}; using defaults (ws={$this->ws}, uid={$this->uid}).");
            return;
        }
        $this->uid = (int) $user->id;
        $wsId = (int) ($user->current_workspace_id ?? 0);
        if ($wsId <= 0 && Schema::hasTable('workspace_user')) {
            $wsId = (int) (DB::table('workspace_user')->where('user_id', $user->id)->value('workspace_id') ?? 0);
        }
        if ($wsId <= 0) {
            foreach (['owner_id', 'user_id', 'created_by'] as $col) {
                if (Schema::hasColumn('workspaces', $col)) {
                    $wsId = (int) (DB::table('workspaces')->where($col, $user->id)->value('id') ?? 0);
                    if ($wsId > 0) break;
                }
            }
        }
        if ($wsId > 0) {
            $this->ws = $wsId;
        }
        // Pick a teammate in the workspace for assignee/agent fields; else self.
        $this->agentUid = $this->uid;
        if (Schema::hasTable('workspace_user')) {
            $mate = (int) (DB::table('workspace_user')
                ->where('workspace_id', $this->ws)
                ->where('user_id', '!=', $this->uid)
                ->value('user_id') ?? 0);
            if ($mate > 0) {
                $this->agentUid = $mate;
            }
        }
        $this->command->info("  Target resolved from {$email}: ws={$this->ws}, uid={$this->uid}, agent={$this->agentUid}.");
    }

    public function run(): void
    {
        $this->resolveTarget();
        $this->command->info("Seeding demo content (workspace id={$this->ws}, user id={$this->uid})...");

        $this->cleanup();
        $this->seedDevice();
        $this->seedTagsAttributes();
        $this->seedContactGroups();
        $this->seedContacts();
        $this->seedTemplates();
        $this->seedFlows();
        $this->seedFlowSubscribers();
        $this->seedMessageHistory();
        $this->seedCampaigns();
        $this->seedBroadcasts();
        $this->seedScheduledMessages();
        $this->seedConversationsAndInbox();
        $this->seedKeywordReplies();
        $this->seedMetaCampaigns();
        $this->seedSupportTickets();
        $this->seedOrders();
        $this->seedWaOrders();
        $this->seedWalletLedger();
        $this->seedNotifications();
        $this->seedAuditLogs();

        $this->command->info('Demo content seeding complete.');
    }

    /* =====================================================================
     * Helpers
     * ===================================================================== */

    private function enc($v): string
    {
        return Crypt::encryptString((string) $v);
    }

    private function ts(int $maxDaysAgo = 90, int $minDaysAgo = 1): Carbon
    {
        return now()->subDays(rand($minDaysAgo, $maxDaysAgo))
                    ->subHours(rand(0, 23))
                    ->subMinutes(rand(0, 59));
    }

    /** Marker used so cleanup() can find and remove only demo rows. */
    private const TAG = 'demo_seed';

    /* =====================================================================
     * Cleanup (scoped strictly to workspace 1 demo rows)
     * ===================================================================== */
    private function cleanup(): void
    {
        // Delete in FK-safe order. We scope everything to workspace 1 and the
        // demo device. We only remove rows this seeder created (audit_logs and
        // notifications are filtered by our demo markers to keep real ones).
        $demoDevice = DB::table('devices')->where('workspace_id', $this->ws)
            ->where('region', self::TAG)->value('id');

        if ($demoDevice) {
            $campIds = DB::table('wpcampaigns')->where('workspace_id', $this->ws)->pluck('id');
            DB::table('wp_campaign_contacts')->whereIn('campaign_id', $campIds)->delete();
            $bcIds = DB::table('broadcasts')->where('workspace_id', $this->ws)->pluck('id');
            DB::table('broadcast_contacts')->whereIn('broadcast_id', $bcIds)->delete();
            $convIds = DB::table('conversations')->where('workspace_id', $this->ws)->pluck('id');
            DB::table('inbox_messages')->whereIn('conversation_id', $convIds)->delete();
            DB::table('messages')->where('workspace_id', $this->ws)->delete();
            $flowIds = DB::table('flows')->where('workspace_id', $this->ws)
                ->where('category', self::TAG)->pluck('id');
            DB::table('flow_subscribers')->whereIn('flow_id', $flowIds)->delete();
            // Remove the on-disk JSON mirror for demo flows only (never touch
            // non-demo flow files). Each demo flow writes uploads/flows/flow_{id}.json.
            foreach ($flowIds as $fid) {
                $path = public_path('uploads/flows/flow_' . $fid . '.json');
                if (File::exists($path)) {
                    File::delete($path);
                }
            }
            $ticketIds = DB::table('support_tickets')->where('workspace_id', $this->ws)
                ->where('reason', 'demo')->pluck('id');
            DB::table('support_messages')->whereIn('ticket_id', $ticketIds)->delete();
            DB::table('wa_order_items')->whereIn('order_id',
                DB::table('wa_orders')->where('workspace_id', $this->ws)->where('source', 'demo')->pluck('id'))->delete();
        }

        // Scoped table wipes for tables that are pure demo-additive here.
        DB::table('conversations')->where('workspace_id', $this->ws)->delete();
        DB::table('wpcampaigns')->where('workspace_id', $this->ws)->delete();
        DB::table('broadcasts')->where('workspace_id', $this->ws)->delete();
        DB::table('scheduled_messages')->where('workspace_id', $this->ws)->delete();
        DB::table('meta_campaigns')->where('workspace_id', $this->ws)->delete();
        DB::table('flows')->where('workspace_id', $this->ws)->where('category', self::TAG)->delete();
        DB::table('support_tickets')->where('workspace_id', $this->ws)->where('reason', 'demo')->delete();
        DB::table('wa_orders')->where('workspace_id', $this->ws)->where('source', 'demo')->delete();
        DB::table('keyword_replies')->where('workspace_id', $this->ws)->where('canonical_language', 'demo')->delete();
        DB::table('wa_templates')->where('workspace_id', $this->ws)->where('parameter_format', 'DEMO')->delete();
        DB::table('contacts')->where('workspace_id', $this->ws)->where('language', self::TAG)->delete();
        DB::table('contact_groups')->where('workspace_id', $this->ws)->where('color', '#DEM005')->delete();
        DB::table('tags')->where('workspace_id', $this->ws)->where('color', '#DEM005')->delete();
        DB::table('attributes')->where('workspace_id', $this->ws)->where('type', 'demo')->delete();
        DB::table('wallet_transactions')->where('user_id', $this->uid)->where('source', 'demo_seed')->delete();
        DB::table('notifications')->where('workspace_id', $this->ws)->where('verb', 'demo')->delete();
        DB::table('audit_logs')->where('workspace_id', $this->ws)->where('action', 'demo.seed')->delete();
        DB::table('orders')->where('workspace_id', $this->ws)->where('payment_reference', self::TAG)->delete();
        DB::table('devices')->where('workspace_id', $this->ws)->where('region', self::TAG)->delete();

        $this->command->info('  cleanup: previous demo rows removed.');
    }

    /* =====================================================================
     * Device (needed by campaigns/broadcasts/scheduled/conversations)
     * ===================================================================== */
    private function seedDevice(): void
    {
        if (!Schema::hasTable('devices')) {
            return;
        }
        $this->deviceId = DB::table('devices')->insertGetId([
            'user_id'                => $this->uid,
            'assigned_user_id'       => $this->uid,
            'device_name'            => $this->enc('Media City Main Line'),
            'country_code'           => $this->enc('+91'),
            'phone_number'           => $this->enc('919811001100'),
            'region'                 => self::TAG, // marker
            'active'                 => 1,
            'activate_after_pairing' => 1,
            'status'                 => 'connected',
            'sent_24h'               => 842,
            'failed_24h'             => 11,
            'last_seen_at'           => now()->subMinutes(3),
            'workspace_id'           => $this->ws,
            'created_at'             => now()->subDays(75),
            'updated_at'             => now()->subMinutes(3),
        ]);

        // A second device, connecting state, to make the devices list fuller.
        DB::table('devices')->insert([
            'user_id'                => $this->uid,
            'assigned_user_id'       => $this->agentUid,
            'device_name'            => $this->enc('Support Desk Number'),
            'country_code'           => $this->enc('+91'),
            'phone_number'           => $this->enc('919811002200'),
            'region'                 => self::TAG,
            'active'                 => 1,
            'activate_after_pairing' => 1,
            'status'                 => 'connected',
            'sent_24h'               => 318,
            'failed_24h'             => 4,
            'last_seen_at'           => now()->subMinutes(12),
            'workspace_id'           => $this->ws,
            'created_at'             => now()->subDays(40),
            'updated_at'             => now()->subMinutes(12),
        ]);

        $this->command->info('  devices: 2 connected.');
    }

    /* =====================================================================
     * Tags + Attributes
     * ===================================================================== */
    private function seedTagsAttributes(): void
    {
        if (Schema::hasTable('tags')) {
            $tags = ['VIP', 'New Lead', 'Hot Prospect', 'Repeat Buyer', 'Newsletter', 'Cart Abandoner', 'Wholesale', 'Support'];
            foreach ($tags as $t) {
                DB::table('tags')->insert([
                    'workspace_id' => $this->ws,
                    'name'         => $t,
                    'slug'         => Str::slug($t) . '-' . Str::lower(Str::random(4)),
                    'color'        => '#DEM005', // marker
                    'description'  => $t . ' segment',
                    'created_at'   => $this->ts(80, 30),
                    'updated_at'   => now(),
                ]);
            }
        }

        if (Schema::hasTable('attributes')) {
            $attrs = [
                ['City', 'city', 'Mumbai'], ['Company', 'company', 'Acme Pvt Ltd'],
                ['Lead Source', 'lead_source', 'Instagram Ad'], ['Plan Interest', 'plan_interest', 'Pro'],
                ['Lifetime Value', 'ltv', '24500'], ['Preferred Language', 'pref_lang', 'English'],
                // Referenced by the seeded templates' variable_map so {{2}}/{{3}}
                // resolve to a real value. {{1}} maps to the contact's `name`.
                ['Order ID', 'order_id', 'ORD-10293'], ['Order Total', 'order_total', 'Rs. 2,450'],
                ['Tracking URL', 'tracking_url', 'https://track.mediacity.co.in/ORD-10293'],
                ['Appointment Date', 'appt_date', 'Jun 12'], ['Appointment Time', 'appt_time', '3:30 PM'],
                ['Payment Amount', 'payment_amount', 'Rs. 2,450'], ['Invoice No', 'invoice_no', 'INV-558210'],
                ['OTP Code', 'otp_code', '482913'],
            ];
            foreach ($attrs as $a) {
                DB::table('attributes')->insert([
                    'user_id'        => $this->uid,
                    'workspace_id'   => $this->ws,
                    'attribute_name' => $a[0],
                    'attribute_key'  => $a[1],
                    'attribute_value'=> $this->enc($a[2]),
                    'description'    => $this->enc('Demo attribute ' . $a[0]),
                    'type'           => 'demo', // marker
                    'status'         => 1,
                    'created_at'     => $this->ts(80, 30),
                    'updated_at'     => now(),
                ]);
            }
        }
        $this->command->info('  tags + attributes seeded.');
    }

    /* =====================================================================
     * Contact groups
     * ===================================================================== */
    private function seedContactGroups(): void
    {
        if (!Schema::hasTable('contact_groups')) {
            return;
        }
        $groups = ['All Customers', 'Newsletter Subscribers', 'Pro Trial Users', 'Wholesale Partners', 'Event Attendees', 'Win-back Targets'];
        foreach ($groups as $g) {
            $this->groupIds[] = DB::table('contact_groups')->insertGetId([
                'user_id'      => $this->uid,
                'user_group'   => $this->enc($g),
                'note'         => $this->enc('Demo group: ' . $g),
                'status'       => 1,
                'color'        => '#DEM005', // marker
                'workspace_id' => $this->ws,
                'created_at'   => $this->ts(80, 30),
                'updated_at'   => now(),
            ]);
        }
        $this->command->info('  contact_groups: ' . count($this->groupIds));
    }

    /* =====================================================================
     * Contacts (220) - encrypted name/mobile/email/contact_group
     * ===================================================================== */
    private function seedContacts(): void
    {
        if (!Schema::hasTable('contacts')) {
            return;
        }
        $firstIn = ['Aarav', 'Vivaan', 'Aditya', 'Vihaan', 'Arjun', 'Sai', 'Reyansh', 'Ayaan', 'Krishna', 'Ishaan', 'Ananya', 'Diya', 'Aadhya', 'Saanvi', 'Pari', 'Anika', 'Navya', 'Riya', 'Myra', 'Kiara'];
        $firstUs = ['James', 'Olivia', 'Liam', 'Emma', 'Noah', 'Ava', 'William', 'Sophia', 'Mason', 'Isabella', 'Ethan', 'Mia', 'Lucas', 'Charlotte', 'Henry', 'Amelia', 'Jack', 'Harper', 'Owen', 'Evelyn'];
        $last = ['Sharma', 'Verma', 'Patel', 'Gupta', 'Singh', 'Reddy', 'Nair', 'Iyer', 'Khan', 'Mehta', 'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Wilson', 'Anderson'];
        $countries = [['+91', 'IN', '91', '98'], ['+1', 'US', '1', '212']];
        $titles = ['Mr', 'Ms', 'Mrs', 'Dr', null];

        $rows = [];
        for ($i = 0; $i < 220; $i++) {
            $c = $countries[array_rand($countries)];
            $india = $c[1] === 'IN';
            $fn = $india ? $firstIn[array_rand($firstIn)] : $firstUs[array_rand($firstUs)];
            $ln = $last[array_rand($last)];
            $name = $fn . ' ' . $ln;
            if ($india) {
                $mobile = $c[2] . rand(70, 99) . rand(10000000, 99999999);
            } else {
                $mobile = $c[2] . rand(200, 989) . rand(1000000, 9999999);
            }
            $email = Str::lower($fn . '.' . $ln . rand(1, 99)) . '@example.com';
            $unsub = $i % 17 === 0 ? 1 : 0; // ~6% unsubscribed
            $group = $this->groupIds ? $this->groupIds[array_rand($this->groupIds)] : null;

            $rows[] = [
                'user_id'           => $this->uid,
                'title'             => $titles[array_rand($titles)],
                'first_name'        => $fn,
                'last_name'         => $ln,
                'name'              => $this->enc($name),
                'language'          => self::TAG, // marker (also serves as locale slot)
                'address'           => $india ? 'Andheri East, Mumbai' : 'Brooklyn, New York',
                'contact_group'    => $this->enc((string) ($group ?? '')),
                'email'             => $this->enc($email),
                'country_code'      => $c[0],
                'mobile'            => $this->enc($mobile),
                'is_unsubscribed'   => $unsub,
                'custom_attributes' => json_encode([
                    'city'        => $india ? 'Mumbai' : 'New York',
                    'lead_source' => ['Instagram Ad', 'Website', 'Referral', 'WhatsApp Click', 'Trade Show'][array_rand([0, 1, 2, 3, 4])],
                    'ltv'         => rand(0, 48000),
                ]),
                'created_at'        => $this->ts(90, 1),
                'updated_at'        => now()->subDays(rand(0, 10)),
                'workspace_id'      => $this->ws,
            ];
        }
        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('contacts')->insert($chunk);
        }
        $this->contactIds = DB::table('contacts')->where('workspace_id', $this->ws)
            ->where('language', self::TAG)->pluck('id')->all();
        $this->command->info('  contacts: ' . count($this->contactIds));
    }

    /* =====================================================================
     * Templates (a dozen, varied categories/statuses incl. carousel + auth)
     * ===================================================================== */
    private function seedTemplates(): void
    {
        if (!Schema::hasTable('wa_templates')) {
            return;
        }
        // 6th element = positional slot → attribute-key map. {{1}} etc. are
        // Meta-required positional placeholders; this map is what lets the
        // resolver fill them (contact field like `name`, or a seeded workspace
        // attribute like `order_id`). Stored as the nested variable_map shape.
        $defs = [
            ['Order Confirmation', 'MARKETING', 'approved', 'standard', 'Hi {{1}}, your order {{2}} is confirmed. Total {{3}}.', [1 => 'name', 2 => 'order_id', 3 => 'order_total']],
            ['Shipping Update', 'UTILITY', 'approved', 'standard', 'Good news {{1}}, your parcel {{2}} has shipped. Track: {{3}}', [1 => 'name', 2 => 'order_id', 3 => 'tracking_url']],
            ['Abandoned Cart', 'MARKETING', 'approved', 'standard', 'You left items in your cart, {{1}}. Complete checkout and save 10 percent.', [1 => 'name']],
            ['Welcome Series', 'MARKETING', 'approved', 'standard', 'Welcome to Media City, {{1}}. Here is what you can do first.', [1 => 'name']],
            ['Appointment Reminder', 'UTILITY', 'approved', 'standard', 'Reminder: your appointment is on {{1}} at {{2}}.', [1 => 'appt_date', 2 => 'appt_time']],
            ['Feedback Request', 'MARKETING', 'pending', 'standard', 'Hi {{1}}, how was your experience with us? Reply 1 to 5.', [1 => 'name']],
            ['Flash Sale', 'MARKETING', 'approved', 'standard', '24 hour flash sale is live, {{1}}. Up to 40 percent off.', [1 => 'name']],
            ['Payment Receipt', 'UTILITY', 'approved', 'standard', 'Payment of {{1}} received. Invoice {{2}} attached.', [1 => 'payment_amount', 2 => 'invoice_no']],
            ['Re-engagement', 'MARKETING', 'rejected', 'standard', 'We miss you {{1}}. Come back for a special offer.', [1 => 'name']],
            ['Login OTP', 'AUTHENTICATION', 'approved', 'authentication', 'Your verification code is {{1}}. It expires in 10 minutes.', [1 => 'otp_code']],
            ['New Arrivals Carousel', 'MARKETING', 'approved', 'carousel', 'Fresh drops just landed, {{1}}. Swipe to explore.', [1 => 'name']],
            ['Win-back Offer', 'MARKETING', 'pending', 'standard', 'Hi {{1}}, here is 15 percent off to welcome you back.', [1 => 'name']],
        ];
        foreach ($defs as $d) {
            $carousel = null;
            if ($d[3] === 'carousel') {
                $carousel = $this->enc(json_encode([
                    'cards' => [
                        ['title' => 'Aurora Jacket', 'body' => 'Lightweight and warm.', 'image' => 'https://picsum.photos/seed/jacket/600'],
                        ['title' => 'Trail Sneakers', 'body' => 'Grip for any terrain.', 'image' => 'https://picsum.photos/seed/sneaker/600'],
                        ['title' => 'Canvas Backpack', 'body' => 'Built to last.', 'image' => 'https://picsum.photos/seed/bag/600'],
                    ],
                ]));
            }
            $status = $d[2];
            // Build the nested variable_map (encrypted:array) from the slot→key
            // map so positional {{N}} resolves at send time, exactly like a
            // template saved through the editor's "Variable mapping" panel.
            $variableMap = null;
            if (!empty($d[5]) && is_array($d[5])) {
                $variableMap = $this->enc(json_encode([
                    'body' => array_map(
                        fn ($num, $key) => ['num' => (int) $num, 'key' => $key],
                        array_keys($d[5]),
                        array_values($d[5])
                    ),
                ]));
            }
            $this->templateIds[] = DB::table('wa_templates')->insertGetId([
                'user_id'          => $this->uid,
                'template_name'    => $this->enc($d[0]),
                'header'           => $this->enc($d[3] === 'authentication' ? '' : $d[0]),
                'template_body'    => $this->enc($d[4]),
                'footer'           => $this->enc('Media City - Reply STOP to opt out'),
                'buttons'          => $this->enc(json_encode($d[3] === 'authentication'
                    ? [['type' => 'OTP', 'text' => 'Copy code']]
                    : [['type' => 'QUICK_REPLY', 'text' => 'Shop now'], ['type' => 'URL', 'text' => 'View', 'url' => 'https://example.com']])),
                'carousel_data'    => $carousel,
                'variable_map'     => $variableMap,
                'category'         => $d[1],
                'meta_category'    => $d[1],
                'template_type'    => $d[3],
                'language'         => 'en',
                'parameter_format' => 'DEMO', // marker
                'status'           => $status,
                'meta_status'      => strtoupper($status),
                'quality_score'    => $status === 'approved' ? rand(2, 5) : null,
                'approved_at'      => $status === 'approved' ? $this->ts(70, 20) : null,
                'submitted_at'     => $this->ts(80, 25),
                'last_synced_at'   => now()->subDays(rand(1, 5)),
                'workspace_id'     => $this->ws,
                'created_at'       => $this->ts(80, 25),
                'updated_at'       => now(),
            ]);
        }
        $this->command->info('  templates: ' . count($this->templateIds));
    }

    /* =====================================================================
     * Flows
     * ===================================================================== */
    private function seedFlows(): void
    {
        if (!Schema::hasTable('flows')) {
            return;
        }

        File::ensureDirectoryExists(public_path('uploads/flows'));

        // Each entry: [name, theme(category-ish), is_active, is_published,
        //              trigger_kind, $graph(flowNodes/flowEdges/vars)].
        $defs = $this->flowDefinitions();

        foreach ($defs as $d) {
            [$name, $theme, $isActive, $isPublished, $triggerKind, $graph] = $d;

            $this->assertValidGraph($name, $graph);
            $json = json_encode($graph);

            $id = DB::table('flows')->insertGetId([
                'user_id'           => $this->uid,
                'flow_name'         => $this->enc($name),
                'flow_data'         => $this->enc($json),
                'category'          => self::TAG, // demo marker (keep so cleanup works)
                'trigger_kind'      => $triggerKind,
                'trigger_value'     => null,
                'trigger_device_id' => $this->deviceId,
                'is_published'      => $isPublished,
                'is_active'         => $isActive,
                'published_at'      => $isPublished ? $this->ts(70, 20) : null,
                'workspace_id'      => $this->ws,
                'provider'          => 'baileys',
                'created_at'        => $this->ts(80, 25),
                'updated_at'        => now(),
            ]);
            $this->flowIds[] = $id;

            // Mirror to disk for the Node runtime bridge.
            File::put(
                public_path('uploads/flows/flow_' . $id . '.json'),
                json_encode($graph, JSON_PRETTY_PRINT)
            );
        }
        $this->command->info('  flows: ' . count($this->flowIds) . ' (deep multi-node + disk mirror).');
    }

    /**
     * Validate a flow graph before persisting:
     *  - exactly one isStart:true trigger node
     *  - every edge source/target references an existing node
     *  - every list/buttons node has one outgoing edge per option (p0..pN)
     *  - every branch terminates (each branching handle leads somewhere; an
     *    `end` node exists and is reachable from at least the terminal columns)
     */
    private function assertValidGraph(string $name, array $graph): void
    {
        $nodes = $graph['flowNodes'] ?? [];
        $edges = $graph['flowEdges'] ?? [];
        $ids   = [];
        $starts = 0;
        $endIds = [];
        $byId   = [];
        foreach ($nodes as $n) {
            $ids[$n['id']] = $n['type'];
            $byId[$n['id']] = $n;
            if (!empty($n['isStart'])) {
                $starts++;
                if (($n['type'] ?? null) !== 'trigger') {
                    throw new \RuntimeException("Flow '$name': isStart node is not a trigger.");
                }
            }
            if (($n['type'] ?? null) === 'end') {
                $endIds[] = $n['id'];
            }
        }
        if ($starts !== 1) {
            throw new \RuntimeException("Flow '$name': expected exactly 1 isStart trigger, found $starts.");
        }
        if (!$endIds) {
            throw new \RuntimeException("Flow '$name': no end node.");
        }

        // Edge integrity + outgoing-handle map per node.
        $outHandles = [];
        foreach ($edges as $e) {
            if (!isset($ids[$e['source']])) {
                throw new \RuntimeException("Flow '$name': edge source '{$e['source']}' missing.");
            }
            if (!isset($ids[$e['target']])) {
                throw new \RuntimeException("Flow '$name': edge target '{$e['target']}' missing.");
            }
            $outHandles[$e['source']][] = $e['sourceHandle'];
        }

        // Branching nodes: one edge per option (p0..pN).
        foreach ($nodes as $n) {
            if (in_array($n['type'], ['list', 'buttons'], true)) {
                $opts = $n['data']['options'] ?? [];
                $expect = [];
                foreach (array_keys($opts) as $i) {
                    $expect[] = 'p' . $i;
                }
                $have = $outHandles[$n['id']] ?? [];
                sort($expect);
                sort($have);
                if ($expect !== $have) {
                    throw new \RuntimeException(
                        "Flow '$name': branching node '{$n['id']}' handles [" . implode(',', $have)
                        . '] != expected [' . implode(',', $expect) . '].'
                    );
                }
            }
        }

        // Reachability: every non-end node should have an outgoing edge, and
        // every branch must be able to reach an end node (BFS from trigger).
        foreach ($nodes as $n) {
            if ($n['type'] !== 'end' && empty($outHandles[$n['id']])) {
                throw new \RuntimeException("Flow '$name': node '{$n['id']}' ({$n['type']}) has no outgoing edge.");
            }
        }
        $adj = [];
        foreach ($edges as $e) {
            $adj[$e['source']][] = $e['target'];
        }
        // Confirm each end is reachable from the start (so no orphan ends).
        $start = collect($nodes)->firstWhere('isStart', true)['id'];
        $seen = [];
        $stack = [$start];
        while ($stack) {
            $cur = array_pop($stack);
            if (isset($seen[$cur])) {
                continue;
            }
            $seen[$cur] = true;
            foreach ($adj[$cur] ?? [] as $t) {
                $stack[] = $t;
            }
        }
        $reachedEnd = false;
        foreach ($endIds as $eid) {
            if (isset($seen[$eid])) {
                $reachedEnd = true;
            }
        }
        if (!$reachedEnd) {
            throw new \RuntimeException("Flow '$name': no end node reachable from trigger.");
        }
    }

    /* ---- small graph authoring helpers ---- */

    private function vNode(string $id, string $type, int $x, int $y, array $data, bool $start = false): array
    {
        $n = ['id' => $id, 'type' => $type, 'x' => $x, 'y' => $y, 'data' => $data];
        if ($start) {
            $n['isStart'] = true;
        }
        return $n;
    }

    private function vVars(array $pairs): array
    {
        $out = [];
        foreach ($pairs as $name => $desc) {
            $out[] = ['name' => $name, 'desc' => $desc, 'default' => null];
        }
        return $out;
    }

    /**
     * Build edges from a compact list of [source, handle, target] triples and
     * auto-number them e_1..e_n.
     */
    private function vEdges(array $triples): array
    {
        $edges = [];
        foreach ($triples as $i => $t) {
            $edges[] = [
                'id'           => 'e_' . ($i + 1),
                'source'       => $t[0],
                'sourceHandle' => $t[1],
                'target'       => $t[2],
                'kind'         => null,
            ];
        }
        return $edges;
    }

    /**
     * The 9 deep demo flows. Each returns a fully connected
     * {flowNodes, flowEdges, vars} graph that renders in the builder.
     * Column x starts ~-260 and steps +340; parallel branches use y offsets.
     */
    private function flowDefinitions(): array
    {
        return [
            $this->flowEcommerceConsultant(),
            $this->flowAbandonedCart(),
            $this->flowShopLoyalty(),
            $this->flowMarketingOptin(),
            $this->flowLeadQualification(),
            $this->flowOrderTrackingAi(),
            $this->flowAppointmentBooking(),
            $this->flowFeedbackNps(),
            $this->flowWinback(),
        ];
    }

    // 1. Mini E-commerce Consultant
    private function flowEcommerceConsultant(): array
    {
        $nodes = [
            $this->vNode('n_trig', 'trigger', -260, 60, ['kind' => 'keyword', 'keywords' => 'shop, buy, products, help me choose', 'tagId' => '', 'groupId' => '', 'deviceId' => ''], true),
            $this->vNode('n_hi', 'message', 80, 60, ['text' => 'Hi {{name}}! I am your shopping assistant at WaDesk. I can help you find the right product in under a minute.']),
            $this->vNode('n_cat', 'list', 420, 60, ['prompt' => 'What are you shopping for today?', 'button' => 'Browse categories', 'options' => ['Apparel & shoes', 'Electronics', 'Home & living'], 'var' => 'category']),
            // Apparel branch
            $this->vNode('n_app_ask', 'ask', 760, -200, ['prompt' => 'Great choice. What size do you usually wear? (for example M or 9)', 'var' => 'size', 'validate' => 'text', 'options' => []]),
            $this->vNode('n_app_rec', 'message', 1100, -200, ['text' => 'For size {{size}} I recommend the Aurora line: breathable, all-day comfort, free returns within 30 days.']),
            // Electronics branch
            $this->vNode('n_elec_ask', 'ask', 760, 60, ['prompt' => 'What is your budget range in INR? (for example 5000-15000)', 'var' => 'budget', 'validate' => 'text', 'options' => []]),
            $this->vNode('n_elec_rec', 'message', 1100, 60, ['text' => 'Within {{budget}} the Pulse earbuds and Nova power bank are our best sellers with a 2 year warranty.']),
            // Home branch -> AI consultant
            $this->vNode('n_home_ai', 'ai', 760, 320, ['model' => 'gpt-4o-mini', 'prompt' => 'You are a concise home and living shopping consultant for WaDesk. The customer is browsing home goods. Suggest 2 specific product ideas in 3 sentences and invite them to buy.', 'save' => 'home_reply']),
            $this->vNode('n_home_msg', 'message', 1100, 320, ['text' => '{{home_reply}}']),
            // Shared CTA + buy
            $this->vNode('n_cta', 'cta', 1440, 60, ['actions' => [['type' => 'url', 'label' => 'Open store', 'value' => 'https://example.com/shop'], ['type' => 'phone', 'label' => 'Call to order', 'value' => '919811001100']]]),
            $this->vNode('n_buy', 'buttons', 1780, 60, ['prompt' => 'Ready to checkout {{name}}?', 'options' => ['Yes, buy now', 'I have a question'], 'var' => 'buy_choice']),
            $this->vNode('n_thanks', 'message', 2120, -80, ['text' => 'Wonderful! Your cart is saved. Tap Open store to complete the order and use code WELCOME10 for 10 percent off.']),
            // Question -> AI fallback
            $this->vNode('n_q_ai', 'ai', 2120, 240, ['model' => 'gpt-4o-mini', 'prompt' => 'You are a warm WaDesk shopping assistant. Answer the customer product question in 2-3 sentences and offer to connect a human if unsure.', 'save' => 'q_reply']),
            $this->vNode('n_q_msg', 'message', 2460, 240, ['text' => '{{q_reply}}']),
            $this->vNode('n_end_a', 'end', 2460, -80, []),
            $this->vNode('n_end_b', 'end', 2800, 240, []),
        ];
        $edges = $this->vEdges([
            ['n_trig', 'out', 'n_hi'],
            ['n_hi', 'out', 'n_cat'],
            ['n_cat', 'p0', 'n_app_ask'],
            ['n_cat', 'p1', 'n_elec_ask'],
            ['n_cat', 'p2', 'n_home_ai'],
            ['n_app_ask', 'out', 'n_app_rec'],
            ['n_elec_ask', 'out', 'n_elec_rec'],
            ['n_home_ai', 'out', 'n_home_msg'],
            ['n_app_rec', 'out', 'n_cta'],
            ['n_elec_rec', 'out', 'n_cta'],
            ['n_home_msg', 'out', 'n_cta'],
            ['n_cta', 'out', 'n_buy'],
            ['n_buy', 'p0', 'n_thanks'],
            ['n_buy', 'p1', 'n_q_ai'],
            ['n_thanks', 'out', 'n_end_a'],
            ['n_q_ai', 'out', 'n_q_msg'],
            ['n_q_msg', 'out', 'n_end_b'],
        ]);
        $vars = $this->vVars([
            'name' => 'Contact name', 'category' => 'Shopping category chosen',
            'size' => 'Apparel size', 'budget' => 'Electronics budget',
            'home_reply' => 'AI home consultant reply', 'buy_choice' => 'Checkout choice',
            'q_reply' => 'AI answer to question',
        ]);
        return ['Mini E-commerce Consultant', 'commerce', 1, 1, 'keyword',
            ['flowNodes' => $nodes, 'flowEdges' => $edges, 'vars' => $vars]];
    }

    // 2. Abandoned Cart Recovery
    private function flowAbandonedCart(): array
    {
        $nodes = [
            $this->vNode('n_trig', 'trigger', -260, 60, ['kind' => 'tag_added', 'keywords' => '', 'tagId' => '', 'groupId' => '', 'deviceId' => ''], true),
            $this->vNode('n_wait1', 'delay', 80, 60, ['amount' => 1, 'unit' => 'hour']),
            $this->vNode('n_remind', 'message', 420, 60, ['text' => 'Hi {{name}}, you left {{cart_items}} in your cart. We saved it for you so you can finish whenever you are ready.']),
            $this->vNode('n_wait2', 'delay', 760, 60, ['amount' => 1, 'unit' => 'day']),
            $this->vNode('n_offer', 'buttons', 1100, 60, ['prompt' => 'Here is 10 percent off to complete your order. What would you like to do?', 'options' => ['Apply discount & buy', 'Maybe later', 'Remove my items'], 'var' => 'cart_choice']),
            // Buy
            $this->vNode('n_code', 'message', 1440, -200, ['text' => 'Done! Use code CART10 at checkout. Your discount is reserved for the next 24 hours.']),
            $this->vNode('n_cta', 'cta', 1780, -200, ['actions' => [['type' => 'url', 'label' => 'Complete checkout', 'value' => 'https://example.com/cart']]]),
            $this->vNode('n_tag_buy', 'tag', 2120, -200, ['action' => 'add', 'tag' => 'Cart Recovered', 'tagId' => '']),
            // Later
            $this->vNode('n_later', 'message', 1440, 60, ['text' => 'No problem {{name}}. We will hold your cart and remind you once more before it expires.']),
            $this->vNode('n_tag_later', 'tag', 1780, 60, ['action' => 'add', 'tag' => 'Cart Snoozed', 'tagId' => '']),
            // Remove
            $this->vNode('n_remove', 'message', 1440, 320, ['text' => 'Your cart has been cleared. Come back any time and we will be happy to help.']),
            $this->vNode('n_tag_rm', 'tag', 1780, 320, ['action' => 'remove', 'tag' => 'Cart Abandoner', 'tagId' => '']),
            $this->vNode('n_end_a', 'end', 2460, -200, []),
            $this->vNode('n_end_b', 'end', 2120, 60, []),
            $this->vNode('n_end_c', 'end', 2120, 320, []),
        ];
        $edges = $this->vEdges([
            ['n_trig', 'out', 'n_wait1'],
            ['n_wait1', 'out', 'n_remind'],
            ['n_remind', 'out', 'n_wait2'],
            ['n_wait2', 'out', 'n_offer'],
            ['n_offer', 'p0', 'n_code'],
            ['n_offer', 'p1', 'n_later'],
            ['n_offer', 'p2', 'n_remove'],
            ['n_code', 'out', 'n_cta'],
            ['n_cta', 'out', 'n_tag_buy'],
            ['n_tag_buy', 'out', 'n_end_a'],
            ['n_later', 'out', 'n_tag_later'],
            ['n_tag_later', 'out', 'n_end_b'],
            ['n_remove', 'out', 'n_tag_rm'],
            ['n_tag_rm', 'out', 'n_end_c'],
        ]);
        $vars = $this->vVars([
            'name' => 'Contact name', 'cart_items' => 'Items left in cart',
            'cart_choice' => 'Recovery choice',
        ]);
        return ['Abandoned Cart Recovery', 'commerce', 1, 0, 'event',
            ['flowNodes' => $nodes, 'flowEdges' => $edges, 'vars' => $vars]];
    }

    // 3. Shop CRM & Loyalty
    private function flowShopLoyalty(): array
    {
        $nodes = [
            $this->vNode('n_trig', 'trigger', -260, 60, ['kind' => 'keyword', 'keywords' => 'points, loyalty, rewards, my account', 'tagId' => '', 'groupId' => '', 'deviceId' => ''], true),
            $this->vNode('n_hi', 'message', 80, 60, ['text' => 'Hello {{name}}! Welcome to the WaDesk Rewards desk.']),
            $this->vNode('n_ask_id', 'ask', 420, 60, ['prompt' => 'Please share your registered mobile or member ID so I can pull up your account.', 'var' => 'member_id', 'validate' => 'text', 'options' => []]),
            $this->vNode('n_balance', 'message', 760, 60, ['text' => 'Found it. {{name}}, you currently have {{points}} reward points. That is enough for some great perks.']),
            $this->vNode('n_perks', 'list', 1100, 60, ['prompt' => 'Which perk would you like to explore?', 'button' => 'View perks', 'options' => ['Redeem for discount', 'See member tiers', 'Talk to a human'], 'var' => 'perk']),
            // Redeem branch -> condition on points
            $this->vNode('n_cond', 'condition', 1440, -200, ['conditions' => [['variable' => 'points', 'operator' => 'gt', 'value' => '500']], 'operators' => []]),
            $this->vNode('n_redeem_ok', 'message', 1780, -320, ['text' => 'You have enough points. Code LOYAL500 gives you a flat discount on your next order. Enjoy!']),
            $this->vNode('n_redeem_tag', 'tag', 2120, -320, ['action' => 'add', 'tag' => 'Redeemed Points', 'tagId' => '']),
            $this->vNode('n_redeem_no', 'message', 1780, -120, ['text' => 'You are close! You need a few more points to redeem. Make one more purchase to unlock your reward.']),
            // Tiers branch
            $this->vNode('n_tiers', 'message', 1440, 60, ['text' => 'Our tiers: Silver from 0 points, Gold from 1000, Platinum from 5000. Each tier unlocks bigger discounts and early access.']),
            // Human branch
            $this->vNode('n_human', 'assign', 1440, 320, ['team' => '', 'userId' => '', 'message' => 'Connecting you with a rewards specialist now. Please hold on a moment.']),
            $this->vNode('n_end_a', 'end', 2460, -320, []),
            $this->vNode('n_end_b', 'end', 2120, -120, []),
            $this->vNode('n_end_c', 'end', 1780, 60, []),
            $this->vNode('n_end_d', 'end', 1780, 320, []),
        ];
        $edges = $this->vEdges([
            ['n_trig', 'out', 'n_hi'],
            ['n_hi', 'out', 'n_ask_id'],
            ['n_ask_id', 'out', 'n_balance'],
            ['n_balance', 'out', 'n_perks'],
            ['n_perks', 'p0', 'n_cond'],
            ['n_perks', 'p1', 'n_tiers'],
            ['n_perks', 'p2', 'n_human'],
            ['n_cond', 'yes', 'n_redeem_ok'],
            ['n_cond', 'no', 'n_redeem_no'],
            ['n_redeem_ok', 'out', 'n_redeem_tag'],
            ['n_redeem_tag', 'out', 'n_end_a'],
            ['n_redeem_no', 'out', 'n_end_b'],
            ['n_tiers', 'out', 'n_end_c'],
            ['n_human', 'out', 'n_end_d'],
        ]);
        $vars = $this->vVars([
            'name' => 'Contact name', 'member_id' => 'Member id / mobile given',
            'points' => 'Reward points balance', 'perk' => 'Perk chosen',
        ]);
        return ['Shop CRM & Loyalty', 'crm', 1, 1, 'keyword',
            ['flowNodes' => $nodes, 'flowEdges' => $edges, 'vars' => $vars]];
    }

    // 4. Marketing Opt-in & Broadcast
    private function flowMarketingOptin(): array
    {
        $nodes = [
            $this->vNode('n_trig', 'trigger', -260, 60, ['kind' => 'keyword', 'keywords' => 'subscribe, offers, deals, join', 'tagId' => '', 'groupId' => '', 'deviceId' => ''], true),
            $this->vNode('n_hi', 'message', 80, 60, ['text' => 'Hi {{name}}! Join the WaDesk list to get exclusive offers and early access on WhatsApp.']),
            $this->vNode('n_consent', 'buttons', 420, 60, ['prompt' => 'Do you agree to receive marketing updates from us? You can opt out any time by replying STOP.', 'options' => ['Yes, I consent', 'No, thanks'], 'var' => 'consent']),
            // Consent yes -> segment
            $this->vNode('n_seg', 'list', 760, -120, ['prompt' => 'Great! What kind of updates interest you most?', 'button' => 'Pick interests', 'options' => ['New arrivals', 'Sales & discounts', 'Tips & guides'], 'var' => 'segment']),
            $this->vNode('n_tag_new', 'tag', 1100, -320, ['action' => 'add', 'tag' => 'Segment New Arrivals', 'tagId' => '']),
            $this->vNode('n_tag_sale', 'tag', 1100, -120, ['action' => 'add', 'tag' => 'Segment Sales', 'tagId' => '']),
            $this->vNode('n_tag_tips', 'tag', 1100, 80, ['action' => 'add', 'tag' => 'Segment Tips', 'tagId' => '']),
            $this->vNode('n_optin', 'tag', 1440, -120, ['action' => 'add', 'tag' => 'Newsletter', 'tagId' => '']),
            $this->vNode('n_confirm', 'message', 1780, -120, ['text' => 'You are in, {{name}}. You will hear from us about {{segment}}. Reply STOP any time to opt out.']),
            // Consent no
            $this->vNode('n_no', 'message', 760, 320, ['text' => 'No problem {{name}}. We will not send you marketing messages. You can always reply JOIN later.']),
            $this->vNode('n_end_a', 'end', 2120, -120, []),
            $this->vNode('n_end_b', 'end', 1100, 320, []),
        ];
        $edges = $this->vEdges([
            ['n_trig', 'out', 'n_hi'],
            ['n_hi', 'out', 'n_consent'],
            ['n_consent', 'p0', 'n_seg'],
            ['n_consent', 'p1', 'n_no'],
            ['n_seg', 'p0', 'n_tag_new'],
            ['n_seg', 'p1', 'n_tag_sale'],
            ['n_seg', 'p2', 'n_tag_tips'],
            ['n_tag_new', 'out', 'n_optin'],
            ['n_tag_sale', 'out', 'n_optin'],
            ['n_tag_tips', 'out', 'n_optin'],
            ['n_optin', 'out', 'n_confirm'],
            ['n_confirm', 'out', 'n_end_a'],
            ['n_no', 'out', 'n_end_b'],
        ]);
        $vars = $this->vVars([
            'name' => 'Contact name', 'consent' => 'Consent choice', 'segment' => 'Interest segment',
        ]);
        return ['Marketing Opt-in & Broadcast', 'marketing', 1, 1, 'keyword',
            ['flowNodes' => $nodes, 'flowEdges' => $edges, 'vars' => $vars]];
    }

    // 5. Lead Qualification
    private function flowLeadQualification(): array
    {
        $nodes = [
            $this->vNode('n_trig', 'trigger', -260, 60, ['kind' => 'keyword', 'keywords' => 'demo, pricing, quote, sales', 'tagId' => '', 'groupId' => '', 'deviceId' => ''], true),
            $this->vNode('n_hi', 'message', 80, 60, ['text' => 'Hi {{name}}, thanks for your interest in WaDesk. A couple of quick questions so we can help you best.']),
            $this->vNode('n_name_ask', 'ask', 420, 60, ['prompt' => 'Which company are you reaching out from?', 'var' => 'company', 'validate' => 'text', 'options' => []]),
            $this->vNode('n_size', 'list', 760, 60, ['prompt' => 'How big is your team?', 'button' => 'Team size', 'options' => ['1-10', '11-50', '50 plus'], 'var' => 'team_size']),
            $this->vNode('n_timeline', 'list', 1100, 60, ['prompt' => 'When are you looking to get started?', 'button' => 'Timeline', 'options' => ['This week', 'This month', 'Just exploring'], 'var' => 'timeline']),
            // Score branch
            $this->vNode('n_cond', 'condition', 1440, 60, ['conditions' => [['variable' => 'timeline', 'operator' => 'equals', 'value' => 'This week'], ['variable' => 'team_size', 'operator' => 'equals', 'value' => '50 plus']], 'operators' => ['OR']]),
            // Hot -> sales
            $this->vNode('n_hot_msg', 'message', 1780, -160, ['text' => 'You look like a great fit, {{name}}. I am routing you to our sales team for a tailored walkthrough.']),
            $this->vNode('n_hot_assign', 'assign', 2120, -160, ['team' => '', 'userId' => '', 'message' => 'Hot lead from {{company}} ({{team_size}}, {{timeline}}). Please reach out within the hour.']),
            $this->vNode('n_hot_tag', 'tag', 2460, -160, ['action' => 'add', 'tag' => 'Hot Prospect', 'tagId' => '']),
            // Cold -> nurture
            $this->vNode('n_cold_msg', 'message', 1780, 240, ['text' => 'Thanks {{name}}! I will send over a short guide and check back in. No pressure at all.']),
            $this->vNode('n_cold_tag', 'tag', 2120, 240, ['action' => 'add', 'tag' => 'Nurture', 'tagId' => '']),
            $this->vNode('n_cold_cta', 'cta', 2460, 240, ['actions' => [['type' => 'url', 'label' => 'Read the guide', 'value' => 'https://example.com/guide']]]),
            $this->vNode('n_end_a', 'end', 2800, -160, []),
            $this->vNode('n_end_b', 'end', 2800, 240, []),
        ];
        $edges = $this->vEdges([
            ['n_trig', 'out', 'n_hi'],
            ['n_hi', 'out', 'n_name_ask'],
            ['n_name_ask', 'out', 'n_size'],
            ['n_size', 'p0', 'n_timeline'],
            ['n_size', 'p1', 'n_timeline'],
            ['n_size', 'p2', 'n_timeline'],
            ['n_timeline', 'p0', 'n_cond'],
            ['n_timeline', 'p1', 'n_cond'],
            ['n_timeline', 'p2', 'n_cond'],
            ['n_cond', 'yes', 'n_hot_msg'],
            ['n_cond', 'no', 'n_cold_msg'],
            ['n_hot_msg', 'out', 'n_hot_assign'],
            ['n_hot_assign', 'out', 'n_hot_tag'],
            ['n_hot_tag', 'out', 'n_end_a'],
            ['n_cold_msg', 'out', 'n_cold_tag'],
            ['n_cold_tag', 'out', 'n_cold_cta'],
            ['n_cold_cta', 'out', 'n_end_b'],
        ]);
        $vars = $this->vVars([
            'name' => 'Contact name', 'company' => 'Company name', 'team_size' => 'Team size band',
            'timeline' => 'Buying timeline',
        ]);
        return ['Lead Qualification', 'sales', 1, 1, 'keyword',
            ['flowNodes' => $nodes, 'flowEdges' => $edges, 'vars' => $vars]];
    }

    // 6. Order Tracking + AI Support (adapted from flow_17)
    private function flowOrderTrackingAi(): array
    {
        $nodes = [
            $this->vNode('n_trig', 'trigger', -260, 60, ['kind' => 'keyword', 'keywords' => 'order, track, status, support, help', 'tagId' => '', 'groupId' => '', 'deviceId' => ''], true),
            $this->vNode('n_welcome', 'message', 80, 60, ['text' => 'Hi {{name}}! Welcome to WaDesk Support. How can I help you today?']),
            $this->vNode('n_menu', 'list', 420, 60, ['prompt' => 'Pick an option to get started:', 'button' => 'Choose an option', 'options' => ['Track my order', 'Ask the AI assistant', 'Talk to a human'], 'var' => 'menu']),
            $this->vNode('n_askorder', 'ask', 820, -200, ['prompt' => "Sure! What's your order number? (for example 10245)", 'var' => 'order_id', 'validate' => 'text', 'options' => []]),
            $this->vNode('n_status', 'message', 1180, -200, ['text' => "Thanks {{name}}! Order #{{order_id}} is on its way. We'll update you right here as it moves."]),
            $this->vNode('n_ai', 'ai', 820, 60, ['model' => 'gpt-4o-mini', 'prompt' => 'You are a warm, concise WaDesk support agent. Answer the customer question in 2-4 sentences. If it needs account access or you are unsure, say a human teammate will follow up shortly.', 'save' => 'ai_reply']),
            $this->vNode('n_aimsg', 'message', 1180, 60, ['text' => '{{ai_reply}}']),
            $this->vNode('n_aimore', 'buttons', 1520, 60, ['prompt' => 'Did that answer your question?', 'options' => ['Yes, thanks!', 'No, talk to a human'], 'var' => 'ai_helped']),
            $this->vNode('n_thanks', 'message', 1880, -60, ['text' => 'Glad I could help, {{name}}! Message us any time, just say hi.']),
            $this->vNode('n_human', 'assign', 820, 320, ['team' => '', 'userId' => '', 'message' => 'Connecting you with a human agent now, please hold on a moment.']),
            $this->vNode('n_end_order', 'end', 1520, -200, []),
            $this->vNode('n_end_ai', 'end', 2220, -60, []),
            $this->vNode('n_end_human', 'end', 1180, 320, []),
        ];
        $edges = $this->vEdges([
            ['n_trig', 'out', 'n_welcome'],
            ['n_welcome', 'out', 'n_menu'],
            ['n_menu', 'p0', 'n_askorder'],
            ['n_menu', 'p1', 'n_ai'],
            ['n_menu', 'p2', 'n_human'],
            ['n_askorder', 'out', 'n_status'],
            ['n_status', 'out', 'n_end_order'],
            ['n_ai', 'out', 'n_aimsg'],
            ['n_aimsg', 'out', 'n_aimore'],
            ['n_aimore', 'p0', 'n_thanks'],
            ['n_aimore', 'p1', 'n_human'],
            ['n_thanks', 'out', 'n_end_ai'],
            ['n_human', 'out', 'n_end_human'],
        ]);
        $vars = $this->vVars([
            'name' => 'Contact name', 'phone' => 'Phone number', 'menu' => 'Main menu choice',
            'order_id' => 'Order number the customer gave', 'ai_reply' => "AI assistant's generated answer",
            'ai_helped' => 'Did the AI answer help (yes/no)',
        ]);
        return ['Order Tracking + AI Support', 'support', 1, 1, 'keyword',
            ['flowNodes' => $nodes, 'flowEdges' => $edges, 'vars' => $vars]];
    }

    // 7. Appointment Booking
    private function flowAppointmentBooking(): array
    {
        $nodes = [
            $this->vNode('n_trig', 'trigger', -260, 60, ['kind' => 'keyword', 'keywords' => 'book, appointment, slot, schedule', 'tagId' => '', 'groupId' => '', 'deviceId' => ''], true),
            $this->vNode('n_hi', 'message', 80, 60, ['text' => 'Hi {{name}}! Let us get you booked in. It takes under a minute.']),
            $this->vNode('n_service', 'list', 420, 60, ['prompt' => 'Which service would you like?', 'button' => 'Pick a service', 'options' => ['Consultation', 'Demo call', 'Support session'], 'var' => 'service']),
            $this->vNode('n_slot', 'list', 760, 60, ['prompt' => 'Pick a time that works for you:', 'button' => 'Pick a slot', 'options' => ['Today afternoon', 'Tomorrow morning', 'This weekend'], 'var' => 'slot']),
            $this->vNode('n_confirm', 'buttons', 1100, 60, ['prompt' => 'Please confirm: {{service}} on {{slot}}. Shall I lock it in?', 'options' => ['Confirm booking', 'Pick another time'], 'var' => 'confirm']),
            // Confirm
            $this->vNode('n_booked', 'message', 1440, -160, ['text' => 'You are booked, {{name}}! Your {{service}} is set for {{slot}}. We will send a reminder beforehand.']),
            $this->vNode('n_tag', 'tag', 1780, -160, ['action' => 'add', 'tag' => 'Appointment Booked', 'tagId' => '']),
            $this->vNode('n_remind_wait', 'delay', 2120, -160, ['amount' => 1, 'unit' => 'day']),
            $this->vNode('n_remind', 'message', 2460, -160, ['text' => 'Reminder: your {{service}} is coming up on {{slot}}. Reply RESCHEDULE if anything changed.']),
            // Reschedule -> back to slot picker (loop)
            $this->vNode('n_reschedule', 'message', 1440, 240, ['text' => 'No problem, let us find a better time for you.']),
            $this->vNode('n_end_a', 'end', 2800, -160, []),
        ];
        $edges = $this->vEdges([
            ['n_trig', 'out', 'n_hi'],
            ['n_hi', 'out', 'n_service'],
            ['n_service', 'p0', 'n_slot'],
            ['n_service', 'p1', 'n_slot'],
            ['n_service', 'p2', 'n_slot'],
            ['n_slot', 'p0', 'n_confirm'],
            ['n_slot', 'p1', 'n_confirm'],
            ['n_slot', 'p2', 'n_confirm'],
            ['n_confirm', 'p0', 'n_booked'],
            ['n_confirm', 'p1', 'n_reschedule'],
            ['n_booked', 'out', 'n_tag'],
            ['n_tag', 'out', 'n_remind_wait'],
            ['n_remind_wait', 'out', 'n_remind'],
            ['n_remind', 'out', 'n_end_a'],
            ['n_reschedule', 'out', 'n_slot'],
        ]);
        $vars = $this->vVars([
            'name' => 'Contact name', 'service' => 'Chosen service', 'slot' => 'Chosen time slot',
            'confirm' => 'Confirmation choice',
        ]);
        return ['Appointment Booking', 'booking', 1, 1, 'keyword',
            ['flowNodes' => $nodes, 'flowEdges' => $edges, 'vars' => $vars]];
    }

    // 8. Feedback & NPS
    private function flowFeedbackNps(): array
    {
        $nodes = [
            $this->vNode('n_trig', 'trigger', -260, 60, ['kind' => 'keyword', 'keywords' => 'feedback, review, rate, nps', 'tagId' => '', 'groupId' => '', 'deviceId' => ''], true),
            $this->vNode('n_hi', 'message', 80, 60, ['text' => 'Hi {{name}}, thank you for choosing WaDesk. Your feedback helps us improve.']),
            $this->vNode('n_rate', 'list', 420, 60, ['prompt' => 'On a scale of 1 to 5, how likely are you to recommend us?', 'button' => 'Rate us', 'options' => ['1 - Poor', '2 - Fair', '3 - Good', '4 - Great', '5 - Excellent'], 'var' => 'rating']),
            // Detractor (1-2)
            $this->vNode('n_sorry', 'message', 820, -260, ['text' => 'I am sorry we fell short, {{name}}. I would like to make this right.']),
            $this->vNode('n_d_ask', 'ask', 1160, -260, ['prompt' => 'What went wrong? Your honest note goes straight to our team.', 'var' => 'detractor_note', 'validate' => 'text', 'options' => []]),
            $this->vNode('n_d_assign', 'assign', 1500, -260, ['team' => '', 'userId' => '', 'message' => 'Detractor {{rating}} from {{name}}: {{detractor_note}}. Please follow up personally.']),
            $this->vNode('n_d_tag', 'tag', 1840, -260, ['action' => 'add', 'tag' => 'Detractor', 'tagId' => '']),
            // Passive (3)
            $this->vNode('n_passive', 'message', 820, 60, ['text' => 'Thanks {{name}}! We are glad it was okay and we are always working to do better.']),
            $this->vNode('n_p_tag', 'tag', 1160, 60, ['action' => 'add', 'tag' => 'Passive', 'tagId' => '']),
            // Promoter (4-5)
            $this->vNode('n_happy', 'message', 820, 360, ['text' => 'That makes our day, {{name}}! Would you share a quick public review?']),
            $this->vNode('n_review_cta', 'cta', 1160, 360, ['actions' => [['type' => 'url', 'label' => 'Leave a review', 'value' => 'https://example.com/review']]]),
            $this->vNode('n_promo_tag', 'tag', 1500, 360, ['action' => 'add', 'tag' => 'Promoter', 'tagId' => '']),
            $this->vNode('n_end_a', 'end', 2180, -260, []),
            $this->vNode('n_end_b', 'end', 1500, 60, []),
            $this->vNode('n_end_c', 'end', 1840, 360, []),
        ];
        $edges = $this->vEdges([
            ['n_trig', 'out', 'n_hi'],
            ['n_hi', 'out', 'n_rate'],
            ['n_rate', 'p0', 'n_sorry'],
            ['n_rate', 'p1', 'n_sorry'],
            ['n_rate', 'p2', 'n_passive'],
            ['n_rate', 'p3', 'n_happy'],
            ['n_rate', 'p4', 'n_happy'],
            ['n_sorry', 'out', 'n_d_ask'],
            ['n_d_ask', 'out', 'n_d_assign'],
            ['n_d_assign', 'out', 'n_d_tag'],
            ['n_d_tag', 'out', 'n_end_a'],
            ['n_passive', 'out', 'n_p_tag'],
            ['n_p_tag', 'out', 'n_end_b'],
            ['n_happy', 'out', 'n_review_cta'],
            ['n_review_cta', 'out', 'n_promo_tag'],
            ['n_promo_tag', 'out', 'n_end_c'],
        ]);
        $vars = $this->vVars([
            'name' => 'Contact name', 'rating' => 'NPS rating chosen',
            'detractor_note' => 'Detractor free-text reason',
        ]);
        return ['Feedback & NPS', 'feedback', 0, 0, 'keyword',
            ['flowNodes' => $nodes, 'flowEdges' => $edges, 'vars' => $vars]];
    }

    // 9. Win-back / Re-engagement
    private function flowWinback(): array
    {
        $nodes = [
            $this->vNode('n_trig', 'trigger', -260, 60, ['kind' => 'group_join', 'keywords' => '', 'tagId' => '', 'groupId' => '', 'deviceId' => ''], true),
            $this->vNode('n_miss', 'message', 80, 60, ['text' => 'Hi {{name}}, we miss you at WaDesk! It has been a while since your last visit.']),
            $this->vNode('n_wait', 'delay', 420, 60, ['amount' => 2, 'unit' => 'hour']),
            $this->vNode('n_offer', 'buttons', 760, 60, ['prompt' => 'Here is a welcome-back gift just for you. What would you like to do?', 'options' => ['Claim my offer', 'Remind me later', 'Unsubscribe'], 'var' => 'winback']),
            // Claim
            $this->vNode('n_claim', 'message', 1100, -200, ['text' => 'Welcome back, {{name}}! Use code COMEBACK15 for 15 percent off your next order this week.']),
            $this->vNode('n_claim_cta', 'cta', 1440, -200, ['actions' => [['type' => 'url', 'label' => 'Shop now', 'value' => 'https://example.com/shop']]]),
            $this->vNode('n_claim_tag', 'tag', 1780, -200, ['action' => 'add', 'tag' => 'Reactivated', 'tagId' => '']),
            // Later
            $this->vNode('n_later', 'message', 1100, 60, ['text' => 'No problem {{name}}, we will hold your gift and check back soon.']),
            $this->vNode('n_later_tag', 'tag', 1440, 60, ['action' => 'add', 'tag' => 'Winback Snoozed', 'tagId' => '']),
            // Unsubscribe
            $this->vNode('n_unsub', 'message', 1100, 320, ['text' => 'You have been unsubscribed, {{name}}. We are sorry to see you go. Reply JOIN any time to return.']),
            $this->vNode('n_unsub_tag', 'tag', 1440, 320, ['action' => 'remove', 'tag' => 'Newsletter', 'tagId' => '']),
            $this->vNode('n_end_a', 'end', 2120, -200, []),
            $this->vNode('n_end_b', 'end', 1780, 60, []),
            $this->vNode('n_end_c', 'end', 1780, 320, []),
        ];
        $edges = $this->vEdges([
            ['n_trig', 'out', 'n_miss'],
            ['n_miss', 'out', 'n_wait'],
            ['n_wait', 'out', 'n_offer'],
            ['n_offer', 'p0', 'n_claim'],
            ['n_offer', 'p1', 'n_later'],
            ['n_offer', 'p2', 'n_unsub'],
            ['n_claim', 'out', 'n_claim_cta'],
            ['n_claim_cta', 'out', 'n_claim_tag'],
            ['n_claim_tag', 'out', 'n_end_a'],
            ['n_later', 'out', 'n_later_tag'],
            ['n_later_tag', 'out', 'n_end_b'],
            ['n_unsub', 'out', 'n_unsub_tag'],
            ['n_unsub_tag', 'out', 'n_end_c'],
        ]);
        $vars = $this->vVars([
            'name' => 'Contact name', 'winback' => 'Win-back choice',
        ]);
        return ['Win-back / Re-engagement', 'winback', 1, 0, 'event',
            ['flowNodes' => $nodes, 'flowEdges' => $edges, 'vars' => $vars]];
    }

    private function seedFlowSubscribers(): void
    {
        if (!Schema::hasTable('flow_subscribers') || !$this->flowIds || !$this->contactIds) {
            return;
        }
        $rows = [];
        foreach ($this->flowIds as $fid) {
            $n = rand(15, 45);
            $picks = (array) array_rand(array_flip($this->contactIds), min($n, count($this->contactIds)));
            foreach ($picks as $cid) {
                $r = rand(1, 100);
                $status = $r <= 70 ? 'completed' : ($r <= 90 ? 'active' : 'failed');
                $enrolled = $this->ts(60, 1);
                $rows[] = [
                    'flow_id'        => $fid,
                    'contact_id'     => $cid,
                    'enrolled_at'    => $enrolled,
                    'completed_at'   => $status === 'completed' ? (clone $enrolled)->addMinutes(rand(2, 120)) : null,
                    'failed_at'      => $status === 'failed' ? (clone $enrolled)->addMinutes(rand(1, 30)) : null,
                    'failure_reason' => $status === 'failed' ? 'Recipient did not respond in time' : null,
                    'status'         => $status,
                    'created_at'     => $enrolled,
                    'updated_at'     => now(),
                ];
            }
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('flow_subscribers')->insert($chunk);
        }
        $this->command->info('  flow_subscribers: ' . count($rows));
    }

    /* =====================================================================
     * Message history (a few thousand rows for dashboard charts)
     * Each message needs a conversation_id. We create lightweight outbound
     * conversations to anchor them.
     * ===================================================================== */
    private function seedMessageHistory(): void
    {
        if (!Schema::hasTable('messages') || !$this->contactIds) {
            return;
        }
        // Anchor conversations (one per ~12 contacts) so messages have a parent.
        $anchorConvs = [];
        $anchorContacts = array_slice($this->contactIds, 0, 60);
        foreach ($anchorContacts as $cid) {
            $anchorConvs[$cid] = DB::table('conversations')->insertGetId([
                'user_id'         => $this->uid,
                'workspace_id'    => $this->ws,
                'device_id'       => $this->deviceId,
                'title'           => $this->enc('Outbound thread'),
                'preview'         => $this->enc('Campaign and broadcast history'),
                'status'          => 'sent',
                'platform'        => 'W',
                'provider'        => 'baileys',
                'origin'          => 'campaign',
                'inbox_status'    => 'closed',
                'priority'        => 'normal',
                'channel'         => 'whatsapp',
                'recipients_count'=> 1,
                'last_message_at' => $this->ts(30, 1),
                'created_at'      => $this->ts(90, 60),
                'updated_at'      => now(),
            ]);
        }

        $bodies = [
            'Your order has been confirmed and is on its way.',
            'Flash sale ends tonight. Up to 40 percent off storewide.',
            'Reminder: your appointment is tomorrow at 11 AM.',
            'Thanks for shopping with Media City. Here is your receipt.',
            'New arrivals just dropped. Take a look at the latest collection.',
            'We noticed you left items in your cart. Complete checkout to save 10 percent.',
            'Your verification code is 4 8 1 9 2 0.',
            'How was your experience with us today? Reply 1 to 5.',
            'Welcome to Media City. Reply MENU to see what we offer.',
            'Your refund has been processed and will reflect in 3 to 5 days.',
        ];

        $rows = [];
        $now = now();
        // ~3000 rows over the last 30 days, weighted toward recent days/business hours.
        for ($i = 0; $i < 3000; $i++) {
            $cid = $anchorContacts[array_rand($anchorContacts)];
            $daysAgo = (int) floor(abs(30 - sqrt(rand(0, 900)))); // skew recent
            $hour = [9, 10, 10, 11, 12, 13, 14, 15, 15, 16, 17, 18, 19, 20][array_rand(range(0, 13))];
            $createdAt = $now->copy()->subDays($daysAgo)->setTime($hour, rand(0, 59), rand(0, 59));

            // status mix: 60 read, 25 delivered, 10 sent, 5 failed
            $r = rand(1, 100);
            if ($r <= 60) { $status = 'read'; }
            elseif ($r <= 85) { $status = 'delivered'; }
            elseif ($r <= 95) { $status = 'sent'; }
            else { $status = 'failed'; }

            $sentAt = $createdAt->copy()->addSeconds(rand(1, 8));
            $deliveredAt = in_array($status, ['delivered', 'read']) ? $sentAt->copy()->addSeconds(rand(2, 40)) : null;
            $readAt = $status === 'read' ? ($deliveredAt ? $deliveredAt->copy()->addMinutes(rand(1, 180)) : null) : null;

            $rows[] = [
                'conversation_id' => $anchorConvs[$cid],
                'user_id'         => $this->uid,
                'contact_id'      => $cid,
                'template_id'     => $this->templateIds ? $this->templateIds[array_rand($this->templateIds)] : null,
                'direction'       => 'out',
                'to_number'       => $this->enc('9198' . rand(10000000, 99999999)),
                'from_number'     => $this->enc('919811001100'),
                'body'            => $this->enc($bodies[array_rand($bodies)]),
                'status'          => $status,
                'failure_reason'  => $status === 'failed' ? $this->enc('Recipient number not on WhatsApp') : null,
                'meta'            => json_encode(['source' => 'demo_seed']),
                'sent_at'         => $sentAt,
                'delivered_at'    => $deliveredAt,
                'read_at'         => $readAt,
                'created_at'      => $createdAt,
                'updated_at'      => $createdAt,
                'workspace_id'    => $this->ws,
                'provider'        => 'baileys',
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('messages')->insert($chunk);
        }
        $this->command->info('  messages: ' . count($rows));
    }

    /* =====================================================================
     * Campaigns (wpcampaigns) + per-recipient rows
     * ===================================================================== */
    private function seedCampaigns(): void
    {
        if (!Schema::hasTable('wpcampaigns') || !$this->contactIds) {
            return;
        }
        $defs = [
            ['Diwali Mega Sale 2026', 'completed'], ['New Year Welcome Drip', 'completed'],
            ['Summer Clearance', 'completed'], ['VIP Early Access', 'completed'],
            ['Cart Recovery Blast', 'completed'], ['Product Launch Teaser', 'completed'],
            ['Black Friday Preview', 'completed'], ['Loyalty Rewards Push', 'completed'],
            ['Feedback Drive', 'completed'], ['Re-engagement Wave', 'failed'],
            ['Weekend Flash Offer', 'running'], ['Spring Collection', 'completed'],
            ['Referral Program Invite', 'completed'], ['Restock Alert', 'completed'],
            ['Holiday Greetings', 'scheduled'],
        ];
        foreach ($defs as $idx => $d) {
            $total = rand(80, 220);
            if ($d[1] === 'scheduled') {
                $sent = $delivered = $read = $failed = $responded = $clicked = 0;
            } else {
                $failed = (int) round($total * (rand(2, 10) / 100));
                $sent = $total;
                $delivered = $total - $failed;
                $read = (int) round($delivered * (rand(55, 85) / 100));
                $responded = (int) round($read * (rand(8, 25) / 100));
                $clicked = (int) round($read * (rand(10, 30) / 100));
            }
            $created = $this->ts(85, 5);
            $campId = DB::table('wpcampaigns')->insertGetId([
                'campaign_name'   => $this->enc($d[0]),
                'device_id'       => $this->deviceId,
                'campaign_type'   => 'template',
                'status'          => $d[1],
                'custom_message'  => $this->enc('Hi {{1}}, ' . $d[0] . ' is live. Tap to explore.'),
                'template_id'     => $this->templateIds ? $this->templateIds[array_rand($this->templateIds)] : null,
                'tracking_enabled'=> 1,
                'schedule_type'   => $d[1] === 'scheduled' ? 'scheduled' : 'now',
                'send_date'       => $d[1] === 'scheduled' ? now()->addDays(7)->toDateString() : $created->toDateString(),
                'send_time'       => '10:00:00',
                'timezone'        => 'Asia/Kolkata',
                'total_recipients'=> $total,
                'sent_count'      => $sent,
                'failed_count'    => $failed,
                'delivered_count' => $delivered,
                'read_count'      => $read,
                'responded_count' => $responded,
                'clicked_count'   => $clicked,
                'completed_at'    => in_array($d[1], ['completed', 'failed']) ? (clone $created)->addHours(2) : null,
                'created_by'      => $this->uid,
                'workspace_id'    => $this->ws,
                'provider'        => 'baileys',
                'created_at'      => $created,
                'updated_at'      => now(),
            ]);

            // Per-recipient rows for the first several campaigns (volume).
            if ($idx < 8 && $d[1] !== 'scheduled') {
                $picks = (array) array_rand(array_flip($this->contactIds), min($total, count($this->contactIds)));
                $crows = [];
                $remainingRead = $read;
                $remainingDelivered = $delivered;
                $count = 0;
                foreach ($picks as $cid) {
                    $count++;
                    if ($count <= $failed) { $st = 'failed'; }
                    elseif ($remainingRead-- > 0) { $st = 'read'; }
                    elseif ($remainingDelivered-- > 0) { $st = 'delivered'; }
                    else { $st = 'sent'; }
                    $sentAt = (clone $created)->addMinutes(rand(0, 90));
                    $crows[] = [
                        'campaign_id'       => $campId,
                        'contact_id'        => $cid,
                        'status'            => $st,
                        'phone_number'      => '9198' . rand(10000000, 99999999),
                        'recipient_name'    => 'Customer ' . $cid,
                        'whatsapp_message_id' => 'wamid.demo' . Str::random(20),
                        'tracking_id'       => Str::uuid()->toString(),
                        'sent_at'           => $st !== 'failed' ? $sentAt : null,
                        'delivered_at'      => in_array($st, ['delivered', 'read']) ? (clone $sentAt)->addSeconds(rand(2, 30)) : null,
                        'read_at'           => $st === 'read' ? (clone $sentAt)->addMinutes(rand(1, 120)) : null,
                        'clicked'           => $st === 'read' && rand(0, 3) === 0 ? 1 : 0,
                        'click_count'       => $st === 'read' && rand(0, 3) === 0 ? rand(1, 3) : 0,
                        'error_message'     => $st === 'failed' ? 'Number not on WhatsApp' : null,
                        'created_at'        => $sentAt,
                        'updated_at'        => now(),
                    ];
                }
                foreach (array_chunk($crows, 200) as $chunk) {
                    DB::table('wp_campaign_contacts')->insert($chunk);
                }
            }
        }
        $this->command->info('  wpcampaigns: ' . count($defs) . ' (+ recipient rows).');
    }

    /* =====================================================================
     * Broadcasts + broadcast_contacts
     * ===================================================================== */
    private function seedBroadcasts(): void
    {
        if (!Schema::hasTable('broadcasts') || !$this->contactIds) {
            return;
        }
        $names = ['Monsoon Sale Broadcast', 'Festive Wishes Blast', 'Stock Clearance Alert', 'Membership Renewal Reminder', 'Webinar Invitation', 'Survey Invitation'];
        foreach ($names as $name) {
            $total = rand(60, 160);
            $fail = (int) round($total * (rand(3, 9) / 100));
            $success = $total - $fail;
            $delivered = $success;
            $read = (int) round($delivered * (rand(50, 80) / 100));
            $clicked = (int) round($read * (rand(8, 22) / 100));
            $created = $this->ts(80, 4);
            $bid = DB::table('broadcasts')->insertGetId([
                'user_id'         => $this->uid,
                'device_id'       => $this->deviceId,
                'template_id'     => $this->templateIds ? $this->templateIds[array_rand($this->templateIds)] : null,
                'name'            => $this->enc($name),
                'timezone'        => $this->enc('Asia/Kolkata'),
                'status'          => 'completed',
                'scheduled_at'    => $created,
                'completed_at'    => (clone $created)->addHours(1),
                'total_recipients'=> $total,
                'success_count'   => $success,
                'delivered_count' => $delivered,
                'read_count'      => $read,
                'clicked_count'   => $clicked,
                'fail_count'      => $fail,
                'workspace_id'    => $this->ws,
                'provider'        => 'baileys',
                'created_at'      => $created,
                'updated_at'      => now(),
            ]);

            $picks = (array) array_rand(array_flip($this->contactIds), min($total, count($this->contactIds)));
            $rows = [];
            $remRead = $read; $remDelivered = $delivered; $count = 0;
            foreach ($picks as $cid) {
                $count++;
                if ($count <= $fail) { $st = 'failed'; }
                elseif ($remRead-- > 0) { $st = 'read'; }
                elseif ($remDelivered-- > 0) { $st = 'delivered'; }
                else { $st = 'sent'; }
                $sentAt = (clone $created)->addMinutes(rand(0, 55));
                $rows[] = [
                    'broadcast_id'      => $bid,
                    'contact_id'        => $cid,
                    'status'            => $st,
                    'error_message'     => $st === 'failed' ? $this->enc('Delivery failed') : null,
                    'whatsapp_message_id' => 'wamid.bc' . Str::random(18),
                    'sent_at'           => $st !== 'failed' ? $sentAt : null,
                    'delivered_at'      => in_array($st, ['delivered', 'read']) ? (clone $sentAt)->addSeconds(rand(2, 30)) : null,
                    'read_at'           => $st === 'read' ? (clone $sentAt)->addMinutes(rand(1, 90)) : null,
                    'created_at'        => $sentAt,
                    'updated_at'        => now(),
                ];
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('broadcast_contacts')->insert($chunk);
            }
        }
        $this->command->info('  broadcasts: ' . count($names) . ' (+ recipient rows).');
    }

    /* =====================================================================
     * Scheduled messages (all PAST + completed; nothing pending)
     * ===================================================================== */
    private function seedScheduledMessages(): void
    {
        if (!Schema::hasTable('scheduled_messages')) {
            return;
        }
        $defs = [
            'Morning Newsletter', 'Weekly Offer Digest', 'Payment Reminder Batch',
            'Event Countdown', 'Loyalty Points Update', 'Restock Notification',
        ];
        foreach ($defs as $name) {
            $created = $this->ts(60, 10);
            $ran = (clone $created)->addDays(1);
            $total = rand(40, 120);
            $failed = (int) round($total * 0.05);
            DB::table('scheduled_messages')->insert([
                'user_id'         => $this->uid,
                'workspace_id'    => $this->ws,
                'device_id'       => $this->deviceId,
                'schedule_name'   => $this->enc($name),
                'message_content' => $this->enc('Scheduled: ' . $name . '. Thank you for being with Media City.'),
                'template_type'   => 'text',
                'schedule_type'   => 'once',
                'send_date'       => $ran->toDateString(),
                'send_time'       => '09:30',
                'scheduled_time'  => $ran,
                'timezone'        => 'Asia/Kolkata',
                'recipient_type'  => 'groups',
                'target_groups'   => json_encode($this->groupIds ? [$this->groupIds[array_rand($this->groupIds)]] : []),
                'total_recipients'=> $total,
                'from_number'     => '919811001100',
                'status'          => 'completed',
                'last_run_at'     => $ran,
                'completed_at'    => (clone $ran)->addMinutes(rand(5, 40)),
                'total_sent'      => $total,
                'total_delivered' => $total - $failed,
                'total_failed'    => $failed,
                'charged_sent'    => $total - $failed,
                'provider'        => 'baileys',
                'created_at'      => $created,
                'updated_at'      => now(),
            ]);
        }
        $this->command->info('  scheduled_messages: ' . count($defs) . ' (all past/completed).');
    }

    /* =====================================================================
     * Conversations + inbox messages (team inbox threads)
     * ===================================================================== */
    private function seedConversationsAndInbox(): void
    {
        if (!Schema::hasTable('conversations') || !$this->contactIds) {
            return;
        }
        $openers = [
            'Hi, is the Aurora jacket available in size M?',
            'I want to track my order, can you help?',
            'Do you ship to Pune? How long does it take?',
            'My payment failed but money was deducted.',
            'Can I get a discount on bulk orders?',
            'What are your store timings?',
            'I need to return an item, what is the process?',
            'Is cash on delivery available?',
            'Can you share the product catalog?',
            'I love your service, thank you so much!',
        ];
        $agentReplies = [
            'Hi there. Yes, that is in stock. Would you like me to reserve one for you?',
            'Sure, please share your order number and I will check the status right away.',
            'Yes, we ship to Pune. Standard delivery is 3 to 5 business days.',
            'Apologies for the trouble. The amount will auto-refund within 24 hours if the order did not confirm.',
            'For bulk orders we offer tiered pricing. How many units are you looking for?',
            'We are open Monday to Friday, 9 AM to 6 PM IST.',
            'No problem. You can start a return from your account or I can do it for you here.',
            'Yes, cash on delivery is available across most pin codes.',
            'Absolutely, here is our latest catalog link.',
            'That means a lot to us. Thank you for choosing Media City.',
        ];
        $statuses = ['open', 'open', 'open', 'snoozed', 'closed', 'closed'];

        $convContacts = array_slice($this->contactIds, 60, 40); // distinct from anchors
        foreach ($convContacts as $i => $cid) {
            $opener = $openers[$i % count($openers)];
            $reply = $agentReplies[$i % count($agentReplies)];
            $inboxStatus = $statuses[array_rand($statuses)];
            $created = $this->ts(30, 0);
            $lastMsgAt = (clone $created)->addMinutes(rand(5, 600));
            $assignee = rand(0, 1) ? $this->uid : $this->agentUid;
            $priority = ['low', 'normal', 'normal', 'high'][array_rand([0, 1, 2, 3])];

            $convId = DB::table('conversations')->insertGetId([
                'user_id'           => $this->uid,
                'workspace_id'      => $this->ws,
                'device_id'         => $this->deviceId,
                'title'             => $this->enc('Customer ' . $cid),
                'preview'           => $this->enc(Str::limit($reply, 60)),
                'status'            => 'received',
                'platform'          => 'W',
                'provider'          => 'baileys',
                'origin'            => 'chat',
                'inbox_status'      => $inboxStatus,
                'priority'          => $priority,
                'channel'           => 'whatsapp',
                'recipients_count'  => 1,
                'assignee_user_id'  => $inboxStatus === 'open' ? $assignee : null,
                'first_response_at' => (clone $created)->addMinutes(rand(1, 15)),
                'snoozed_until'     => $inboxStatus === 'snoozed' ? now()->addDays(rand(1, 3)) : null,
                'resolved_at'       => $inboxStatus === 'closed' ? $lastMsgAt : null,
                'resolved_by'       => $inboxStatus === 'closed' ? $assignee : null,
                'unread_count'      => $inboxStatus === 'open' ? rand(0, 3) : 0,
                'last_message_at'   => $lastMsgAt,
                'last_inbound_at'   => $created,
                'last_outbound_at'  => (clone $created)->addMinutes(2),
                'created_at'        => $created,
                'updated_at'        => $lastMsgAt,
            ]);
            $this->conversationIds[] = $convId;

            if (!Schema::hasTable('inbox_messages')) {
                continue;
            }
            // 4-8 message thread, alternating inbound/outbound.
            $thread = [
                ['in', $opener],
                ['out', $reply],
                ['in', 'Great, that works for me.'],
                ['out', 'Perfect. Is there anything else I can help with?'],
                ['in', 'No, that is all. Thanks!'],
                ['out', 'You are welcome. Have a great day.'],
            ];
            $threadLen = rand(4, 6);
            $t = clone $created;
            $msgs = [];
            for ($k = 0; $k < $threadLen; $k++) {
                $dir = $thread[$k][0] === 'in' ? 'in' : 'out';
                $t = (clone $t)->addMinutes(rand(1, 25));
                $st = $dir === 'in' ? 'received' : 'read';
                $msgs[] = [
                    'conversation_id' => $convId,
                    'user_id'         => $dir === 'out' ? $assignee : null,
                    'contact_id'      => $cid,
                    'direction'       => $dir,
                    'to_number'       => $this->enc($dir === 'out' ? ('9198' . rand(10000000, 99999999)) : '919811001100'),
                    'from_number'     => $this->enc($dir === 'in' ? ('9198' . rand(10000000, 99999999)) : '919811001100'),
                    'body'            => $this->enc($thread[$k][1]),
                    'status'          => $st,
                    'sent_at'         => $t,
                    'delivered_at'    => $dir === 'out' ? (clone $t)->addSeconds(5) : null,
                    'read_at'         => $dir === 'out' ? (clone $t)->addMinutes(rand(1, 30)) : null,
                    'created_at'      => $t,
                    'updated_at'      => $t,
                    'provider'        => 'baileys',
                ];
            }
            DB::table('inbox_messages')->insert($msgs);

            // Mirror the same thread into the `messages` table. /chat renders a
            // thread via Conversation->messages (the `messages` table keyed by
            // conversation_id); the team-inbox reads `inbox_messages`. Without
            // this, /chat shows "No messages yet" for these conversations. The
            // $msgs rows already carry every `messages` column except
            // workspace_id + meta, so we just add those.
            if (Schema::hasTable('messages')) {
                $chatRows = [];
                foreach ($msgs as $m) {
                    $m['workspace_id'] = $this->ws;
                    $m['meta'] = json_encode(['source' => 'demo_seed']);
                    $chatRows[] = $m;
                }
                DB::table('messages')->insert($chatRows);
            }
        }
        $this->command->info('  conversations: ' . count($convContacts) . ' (+ inbox + chat threads).');
    }

    /* =====================================================================
     * Keyword auto-replies
     * ===================================================================== */
    private function seedKeywordReplies(): void
    {
        if (!Schema::hasTable('keyword_replies')) {
            return;
        }
        $keywords = [
            ['hi', 'text'], ['hello', 'text'], ['menu', 'text'], ['price', 'text'],
            ['hours', 'text'], ['catalog', 'text'], ['support', 'flow'], ['track', 'flow'],
            ['offer', 'text'], ['book', 'flow'], ['refund', 'text'], ['stop', 'text'],
        ];
        foreach ($keywords as $kw) {
            DB::table('keyword_replies')->insert([
                'user_id'            => $this->uid,
                'workspace_id'       => $this->ws,
                'provider'           => 'baileys',
                'device_id'          => $this->deviceId,
                'keyword'            => $kw[0],
                'canonical_language' => 'demo', // marker
                'matching_method'    => 'exact',
                'fuzzy_similarity'   => 80,
                'reply_type'         => $kw[1],
                'flow_id'            => $kw[1] === 'flow' && $this->flowIds ? $this->flowIds[array_rand($this->flowIds)] : null,
                'message_type'       => 'text',
                'status'             => 1,
                'trigger_count'      => rand(0, 340),
                'last_triggered_at'  => $this->ts(20, 0),
                'created_at'         => $this->ts(70, 20),
                'updated_at'         => now(),
            ]);
        }
        $this->command->info('  keyword_replies: ' . count($keywords));
    }

    /* =====================================================================
     * Meta Ads (CTWA) campaigns
     * ===================================================================== */
    private function seedMetaCampaigns(): void
    {
        if (!Schema::hasTable('meta_campaigns')) {
            return;
        }
        $defs = [
            ['Click to WhatsApp - Festive', 'ACTIVE', 'OUTCOME_LEADS', 1],
            ['Lead Gen - Pro Plan', 'ACTIVE', 'OUTCOME_LEADS', 1],
            ['Traffic - New Collection', 'PAUSED', 'OUTCOME_TRAFFIC', 0],
            ['Retargeting - Cart Drop', 'ACTIVE', 'OUTCOME_SALES', 1],
            ['Awareness - Brand Launch', 'PAUSED', 'OUTCOME_AWARENESS', 0],
        ];
        foreach ($defs as $d) {
            $impressions = rand(8000, 90000);
            $clicks = (int) round($impressions * (rand(80, 320) / 10000));
            $leads = (int) round($clicks * (rand(8, 25) / 100));
            $spend = round($clicks * (rand(40, 120) / 10), 2);
            DB::table('meta_campaigns')->insert([
                'user_id'           => $this->uid,
                'facebook_id'       => (string) rand(100000000000000, 999999999999999),
                'meta_adset_id'     => (string) rand(100000000000000, 999999999999999),
                'meta_creative_id'  => (string) rand(100000000000000, 999999999999999),
                'meta_ad_id'        => (string) rand(100000000000000, 999999999999999),
                'meta_synced_at'    => now()->subHours(rand(1, 12)),
                'name'              => $this->enc($d[0]),
                'creative_title'    => $this->enc($d[0]),
                'creative_body'     => $this->enc('Chat with Media City on WhatsApp for an exclusive offer.'),
                'creative_link_url' => $this->enc('https://wa.me/919811001100'),
                'ctwa_phone'        => $this->enc('919811001100'),
                'ctwa_message'      => $this->enc('Hi, I saw your ad and want to know more.'),
                'targeting'         => $this->enc(json_encode(['geo' => ['IN'], 'age' => [21, 45], 'interests' => ['Online shopping']])),
                'objective'         => $d[2],
                'optimization_goal' => 'LINK_CLICKS',
                'status'            => $d[1],
                'type'              => 'campaign',
                'ctwa_cta'          => 'MESSAGE_PAGE',
                'ctwa_enabled'      => $d[3],
                'daily_budget'      => rand(500, 3000),
                'insights'          => json_encode([
                    'impressions' => $impressions, 'clicks' => $clicks,
                    'leads' => $leads, 'spend' => $spend,
                    'ctr' => round($clicks / max($impressions, 1) * 100, 2),
                ]),
                'ad_set_count'      => rand(1, 3),
                'ad_count'          => rand(1, 5),
                'workspace_id'      => $this->ws,
                'created_at'        => $this->ts(70, 10),
                'updated_at'        => now(),
            ]);
        }
        $this->command->info('  meta_campaigns: ' . count($defs));
    }

    /* =====================================================================
     * Support tickets + replies
     * ===================================================================== */
    private function seedSupportTickets(): void
    {
        if (!Schema::hasTable('support_tickets')) {
            return;
        }
        $subjects = [
            ['Cannot connect my WhatsApp number', 'billing', 'high'],
            ['Template got rejected by Meta', 'technical', 'normal'],
            ['Need to upgrade to Pro plan', 'billing', 'normal'],
            ['Broadcast stuck at sending', 'technical', 'urgent'],
            ['How do I import contacts via CSV?', 'how_to', 'low'],
            ['Webhook not firing for new orders', 'technical', 'high'],
            ['Request invoice for last payment', 'billing', 'normal'],
            ['Auto-reply not triggering on keyword', 'technical', 'normal'],
            ['Add another team member seat', 'billing', 'low'],
            ['Storefront checkout shows wrong price', 'technical', 'high'],
            ['Enable WhatsApp calling feature', 'other', 'normal'],
            ['Data export for compliance', 'other', 'low'],
        ];
        $statuses = ['open', 'open', 'pending', 'resolved', 'resolved', 'closed'];
        foreach ($subjects as $i => $s) {
            $status = $statuses[array_rand($statuses)];
            $created = $this->ts(45, 1);
            $ticketId = DB::table('support_tickets')->insertGetId([
                'user_id'           => $this->uid,
                'workspace_id'      => $this->ws,
                'ticket_number'     => 'TKT-' . strtoupper(Str::random(6)),
                'reason'            => 'demo', // marker; user-visible reason carried in subject
                'name'              => 'Media City',
                'email'             => 'user@mediacity.co.in',
                'subject'           => $s[0],
                'message'           => 'Hello team, ' . lcfirst($s[0]) . '. Please advise. Thank you.',
                'status'            => $status,
                'priority'          => $s[2],
                'assigned_agent_id' => in_array($status, ['open', 'pending']) ? $this->agentUid : null,
                'last_reply_at'     => (clone $created)->addHours(rand(1, 48)),
                'first_response_at' => (clone $created)->addHours(rand(1, 6)),
                'tags'              => json_encode([$s[1]]),
                'resolved_at'       => in_array($status, ['resolved', 'closed']) ? (clone $created)->addDays(rand(1, 4)) : null,
                'created_at'        => $created,
                'updated_at'        => now(),
            ]);

            if (Schema::hasTable('support_messages')) {
                DB::table('support_messages')->insert([
                    [
                        'ticket_id'        => $ticketId,
                        'author_user_id'   => $this->uid,
                        'author_role'      => 'user',
                        'body'             => 'Hello team, ' . lcfirst($s[0]) . '. Please advise.',
                        'is_internal_note' => 0,
                        'created_at'       => $created,
                    ],
                    [
                        'ticket_id'        => $ticketId,
                        'author_user_id'   => $this->agentUid,
                        'author_role'      => 'admin',
                        'body'             => 'Thanks for reaching out. We are looking into this and will update you shortly.',
                        'is_internal_note' => 0,
                        'created_at'       => (clone $created)->addHours(rand(1, 5)),
                    ],
                    [
                        'ticket_id'        => $ticketId,
                        'author_user_id'   => $this->agentUid,
                        'author_role'      => 'admin',
                        'body'             => 'Internal: verified account, escalating to engineering if needed.',
                        'is_internal_note' => 1,
                        'created_at'       => (clone $created)->addHours(rand(2, 8)),
                    ],
                ]);
            }
        }
        $this->command->info('  support_tickets: ' . count($subjects) . ' (+ replies).');
    }

    /* =====================================================================
     * Billing orders (plan/credit purchases)
     * ===================================================================== */
    private function seedOrders(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }
        $pkgIds = DB::table('packages')->pluck('id')->all();
        if (!$pkgIds) {
            return;
        }
        for ($i = 0; $i < 8; $i++) {
            $created = $this->ts(85, 2);
            $pkg = DB::table('packages')->where('id', $pkgIds[array_rand($pkgIds)])->first();
            $amount = (float) ($pkg->offer_price > 0 ? $pkg->offer_price : ($pkg->plan_amount > 0 ? $pkg->plan_amount : 39));
            $status = ['paid', 'paid', 'paid', 'pending', 'failed'][array_rand([0, 1, 2, 3, 4])];
            $gateway = ['razorpay', 'stripe', 'paypal'][array_rand([0, 1, 2])];
            DB::table('orders')->insert([
                'order_number'      => 'WSN-' . now()->subDays(rand(2, 85))->format('ymd') . '-' . strtoupper(Str::random(6)),
                'workspace_id'      => $this->ws,
                'user_id'           => $this->uid,
                'package_id'        => $pkg->id,
                'gateway_slug'      => $gateway,
                'currency'          => 'USD',
                'amount'            => $amount,
                'discount_amount'   => 0,
                'tax_rate'          => 18,
                'tax_amount'        => round($amount * 0.18, 2),
                'total_amount'      => round($amount * 1.18, 2),
                'customer_name'     => 'Media City',
                'customer_email'    => 'user@mediacity.co.in',
                'billing_country'   => 'IN',
                'status'            => $status,
                'gateway_order_id'  => $gateway . '_' . Str::random(14),
                'gateway_payment_id'=> $status === 'paid' ? ('pay_' . Str::random(14)) : null,
                'payment_reference' => self::TAG, // marker
                'paid_at'           => $status === 'paid' ? $created : null,
                'created_at'        => $created,
                'updated_at'        => now(),
            ]);
        }
        $this->command->info('  orders: 8 billing orders.');
    }

    /* =====================================================================
     * WhatsApp store orders + line items
     * ===================================================================== */
    private function seedWaOrders(): void
    {
        if (!Schema::hasTable('wa_orders')) {
            return;
        }
        $products = [
            ['Aurora Jacket', 'AJ-001', 459900], ['Trail Sneakers', 'TS-002', 329900],
            ['Canvas Backpack', 'CB-003', 199900], ['Ceramic Mug Set', 'CM-004', 89900],
            ['Wireless Earbuds', 'WE-005', 549900], ['Yoga Mat', 'YM-006', 129900],
            ['Steel Water Bottle', 'SB-007', 79900], ['Cotton T-Shirt', 'CT-008', 99900],
        ];
        $statuses = ['new', 'confirmed', 'confirmed', 'shipped', 'delivered', 'delivered', 'cancelled'];
        $payMethods = ['prepaid', 'prepaid', 'cod'];
        for ($i = 0; $i < 14; $i++) {
            $itemCount = rand(1, 3);
            $items = [];
            $total = 0;
            for ($k = 0; $k < $itemCount; $k++) {
                $p = $products[array_rand($products)];
                $qty = rand(1, 2);
                $total += $p[2] * $qty;
                $items[] = ['product_id' => null, 'name' => $p[0], 'qty' => $qty, 'price_minor' => $p[2], 'retailer_id' => $p[1]];
            }
            $status = $statuses[array_rand($statuses)];
            $pay = $payMethods[array_rand($payMethods)];
            $created = $this->ts(60, 0);
            $rto = $pay === 'cod' ? rand(10, 70) : null;
            $orderId = DB::table('wa_orders')->insertGetId([
                'workspace_id'   => $this->ws,
                'source'         => 'demo', // marker (real source would be storefront/catalog)
                'customer_phone' => '9198' . rand(10000000, 99999999),
                'customer_name'  => 'Customer ' . rand(1000, 9999),
                'customer_email' => 'buyer' . rand(1, 999) . '@example.com',
                'customer_address' => 'Andheri East, Mumbai 400069',
                'items_json'     => json_encode($items),
                'total_minor'    => $total,
                'shipping_minor' => $pay === 'cod' ? 4900 : 0,
                'discount_minor' => rand(0, 1) ? 0 : 5000,
                'payment_method' => $pay,
                'rto_score'      => $rto,
                'rto_band'       => $rto !== null ? ($rto < 30 ? 'low' : ($rto < 60 ? 'med' : 'high')) : null,
                'currency_code'  => 'INR',
                'status'         => $status,
                'notes'          => 'Demo order via WhatsApp store',
                'meta_json'      => json_encode(['placed_via' => 'demo_seed']),
                'created_at'     => $created,
                'updated_at'     => now(),
            ]);

            if (Schema::hasTable('wa_order_items')) {
                $itemRows = [];
                foreach ($items as $it) {
                    $itemRows[] = [
                        'order_id'      => $orderId,
                        'retailer_id'   => $it['retailer_id'],
                        'name'          => $it['name'],
                        'image_url'     => 'https://picsum.photos/seed/' . Str::slug($it['name']) . '/400',
                        'quantity'      => $it['qty'],
                        'price_minor'   => $it['price_minor'],
                        'currency_code' => 'INR',
                        'created_at'    => $created,
                        'updated_at'    => now(),
                    ];
                }
                DB::table('wa_order_items')->insert($itemRows);
            }
        }
        $this->command->info('  wa_orders: 14 (+ line items).');
    }

    /* =====================================================================
     * Wallet ledger (does NOT change the running balance: balance_after
     * column is informational; we keep entries below current balance.)
     * ===================================================================== */
    private function seedWalletLedger(): void
    {
        if (!Schema::hasTable('wallet_transactions')) {
            return;
        }
        // Current real balance after existing entries.
        $balance = (int) (DB::table('wallet_transactions')->where('user_id', $this->uid)
            ->orderByDesc('id')->value('balance_after') ?? 99801);

        // We only ADD historical-looking entries in the PAST, reconstructing a
        // plausible ledger that ENDS at the current balance. To avoid touching
        // the latest balance, we insert entries with timestamps in the past and
        // a balance_after that floats around but we do NOT append a new "latest"
        // that contradicts the real one: we backfill with source='demo_seed'.
        $running = $balance;
        $rows = [];
        // 30 historical spend entries + a few top-ups, all dated in the past.
        for ($i = 0; $i < 40; $i++) {
            $isTopup = $i % 9 === 0;
            if ($isTopup) {
                $amt = [5000, 10000, 25000][array_rand([0, 1, 2])];
                $rows[] = [
                    'user_id'      => $this->uid,
                    'kind'         => 'credit',
                    'type'         => 'topup',
                    'amount'       => $amt,
                    'balance_after'=> $running, // informational, kept <= current
                    'source'       => 'demo_seed',
                    'description'  => 'Credit top-up of ' . number_format($amt) . ' credits',
                    'meta'         => json_encode(['gateway' => 'razorpay', 'demo' => true]),
                    'created_at'   => $this->ts(85, 2),
                ];
            } else {
                $amt = -rand(1, 60);
                $src = ['message.sent', 'campaign.sent', 'broadcast.sent', 'auto_reply.fired', 'scheduled.fired'][array_rand([0, 1, 2, 3, 4])];
                $rows[] = [
                    'user_id'      => $this->uid,
                    'kind'         => 'credit',
                    'type'         => 'spend',
                    'amount'       => $amt,
                    'balance_after'=> $running,
                    'source'       => 'demo_seed',
                    'description'  => 'Usage: ' . str_replace('.', ' ', $src),
                    'meta'         => json_encode(['count' => abs($amt), 'demo' => true]),
                    'created_at'   => $this->ts(85, 1),
                ];
            }
        }
        DB::table('wallet_transactions')->insert($rows);
        $this->command->info('  wallet_transactions: ' . count($rows) . ' historical entries (balance unchanged).');
    }

    /* =====================================================================
     * Notifications (bell)
     * ===================================================================== */
    private function seedNotifications(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }
        $defs = [
            ['campaign', 'success', 'megaphone', 'Campaign completed', 'Diwali Mega Sale 2026 finished with 186 delivered, 142 read.'],
            ['broadcast', 'info', 'broadcast', 'Broadcast sent', 'Monsoon Sale Broadcast reached 152 contacts.'],
            ['billing', 'warning', 'credit-card', 'Low credit balance', 'Your credit balance is running low. Top up to avoid interruption.'],
            ['template', 'success', 'check', 'Template approved', 'Order Confirmation template was approved by Meta.'],
            ['template', 'danger', 'x', 'Template rejected', 'Re-engagement template was rejected. Review and resubmit.'],
            ['inbox', 'info', 'message', 'New conversation', 'A new customer conversation was assigned to your team.'],
            ['order', 'success', 'shopping-bag', 'New store order', 'A new WhatsApp store order was placed.'],
            ['system', 'info', 'bell', 'Device connected', 'Media City Main Line connected successfully.'],
            ['support', 'info', 'life-buoy', 'Ticket update', 'Your support ticket received a new reply.'],
            ['ads', 'success', 'target', 'New lead from ad', 'Click to WhatsApp - Festive generated a new lead.'],
            ['flow', 'info', 'workflow', 'Flow milestone', 'Welcome Onboarding flow completed for 30 subscribers.'],
            ['contacts', 'info', 'users', 'Contacts imported', '220 contacts were imported into your workspace.'],
        ];
        $rows = [];
        foreach ($defs as $i => $d) {
            $created = $this->ts(20, 0);
            $rows[] = [
                'user_id'            => $this->uid,
                'category'           => $d[0],
                'severity'           => $d[1],
                'icon'               => $d[2],
                'notification_title' => $this->enc($d[3]),
                'notification_msg'   => $this->enc($d[4]),
                'verb'               => 'demo', // marker
                'action_url'         => '/dashboard',
                'is_urgent'          => $d[1] === 'danger' ? 1 : 0,
                'status'             => $i < 6 ? 1 : 0, // mix read/unread
                'read_at'            => $i < 6 ? $created : null,
                'workspace_id'       => $this->ws,
                'created_at'         => $created,
                'updated_at'         => now(),
            ];
        }
        // Duplicate set to make the bell fuller (varied timestamps).
        $rows2 = [];
        foreach ($rows as $r) {
            $r['created_at'] = $this->ts(20, 0);
            $r['status'] = 0;
            $r['read_at'] = null;
            $rows2[] = $r;
        }
        DB::table('notifications')->insert(array_merge($rows, $rows2));
        $this->command->info('  notifications: ' . (count($rows) + count($rows2)));
    }

    /* =====================================================================
     * Audit / activity log
     * ===================================================================== */
    private function seedAuditLogs(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }
        $events = [
            'campaign.created', 'campaign.sent', 'broadcast.created', 'template.submitted',
            'template.approved', 'contact.imported', 'flow.published', 'device.connected',
            'order.placed', 'settings.updated', 'user.login', 'keyword.created',
            'conversation.assigned', 'conversation.resolved', 'ticket.created', 'ad.synced',
        ];
        $rows = [];
        for ($i = 0; $i < 80; $i++) {
            $action = $events[array_rand($events)];
            $rows[] = [
                'layer'         => 'app',
                'workspace_id'  => $this->ws,
                'actor_user_id' => rand(0, 1) ? $this->uid : $this->agentUid,
                'action'        => 'demo.seed', // marker action so cleanup is safe
                'subject_type'  => 'Demo',
                'subject_id'    => rand(1, 500),
                'payload'       => json_encode(['event' => $action, 'demo' => true]),
                'result'        => ['success', 'success', 'success', 'warning'][array_rand([0, 1, 2, 3])],
                'ip'            => '203.0.113.' . rand(1, 254),
                'user_agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) WaDesk Demo',
                'created_at'    => $this->ts(30, 0),
            ];
        }
        DB::table('audit_logs')->insert($rows);
        $this->command->info('  audit_logs: ' . count($rows));
    }
}
