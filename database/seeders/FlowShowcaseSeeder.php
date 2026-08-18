<?php

namespace Database\Seeders;

use App\Models\Flow;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * FlowShowcaseSeeder — builds ready-to-open test flows that exercise EVERY
 * flow-builder node type, so the builder + runtime can be tested end to end
 * without hand-drawing each node.
 *
 * It creates two flows in a target workspace:
 *   • "🧪 All Nodes — Chat"  (flow_type=chat) — one of every chat node.
 *   • "🧪 All Nodes — Call"  (flow_type=call) — one of every call (cf_*) node.
 *
 * The node `type` strings + `data` shapes mirror exactly what the React
 * builder saves and what App\Services\Flows\FlowNormalizer consumes, so the
 * flows open cleanly in the builder AND normalize for the Node runtime.
 *
 * Target workspace resolution (first match wins):
 *   1. env FLOW_SEED_WS_ID
 *   2. the first workspace by id
 * The owner (workspaces.owner_user_id) becomes the flow's user_id; every
 * workspace member sees it (Flow::scopeForCurrentWorkspace is workspace-wide).
 *
 * Safe by design: flows are created UNPUBLISHED (is_published=false), and the
 * Trigger node carries NO device — so the managed keyword rule is created
 * INACTIVE and can never hijack a real inbound message. Open them in the
 * builder, pick a device on the Trigger node, publish, then test.
 *
 * Run:  php artisan db:seed --class=Database\\Seeders\\FlowShowcaseSeeder
 *       FLOW_SEED_WS_ID=3 php artisan db:seed --class=Database\\Seeders\\FlowShowcaseSeeder
 */
class FlowShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        // Target resolution: FLOW_SEED_EMAIL (a user → their current/owned
        // workspace) wins, then FLOW_SEED_WS_ID, then the first workspace.
        $email = trim((string) env('FLOW_SEED_EMAIL', ''));
        $ws = null; $userId = 0;

        if ($email !== '') {
            $u = User::where('email', $email)->first();
            if (! $u) {
                $this->command?->warn("[FlowShowcase] No user with email {$email} — nothing seeded.");
                return;
            }
            $ws = Workspace::find($u->current_workspace_id)
                ?: Workspace::where('owner_user_id', $u->id)->orderBy('id')->first();
            $userId = (int) $u->id;
        }

        if (! $ws) {
            $wsId = (int) env('FLOW_SEED_WS_ID', 0);
            $ws   = $wsId ? Workspace::find($wsId) : Workspace::query()->orderBy('id')->first();
        }

        if (! $ws) {
            $this->command?->warn('[FlowShowcase] No workspace found — nothing seeded.');
            return;
        }

        if (! $userId) {
            $userId = (int) ($ws->owner_user_id
                ?: User::where('current_workspace_id', $ws->id)->value('id')
                ?: User::query()->orderBy('id')->value('id'));
        }

        if (! $userId) {
            $this->command?->warn('[FlowShowcase] No user to own the flow — nothing seeded.');
            return;
        }

        $this->command?->info("[FlowShowcase] Seeding into workspace #{$ws->id} ({$ws->name}), owner user #{$userId}");

        // Remove the retired call showcase flow that earlier runs may have made.
        foreach (Flow::where('workspace_id', $ws->id)->where('flow_type', 'call')->where('category', 'Showcase')->get() as $old) {
            $old->forceDelete();
        }

        // Chat showcase — one of every chat node.
        $this->makeFlow($ws->id, $userId, 'chat', '🧪 All Nodes — Chat', $this->chatGraph());

        // Instagram showcase — MULTIPLE flows so each Instagram capability
        // (condition routing, buttons, products+lead, and the full node set)
        // can be tested on its own. Only IG-allowed node types are used.
        foreach ($this->instagramGraphs() as [$name, $graph]) {
            $this->makeFlow($ws->id, $userId, 'instagram', $name, $graph);
        }

        $this->command?->info('[FlowShowcase] Done. Open /flows to test.');
    }

    /**
     * Create (or replace) one showcase flow. Uses the Eloquent model so the
     * encrypted flow_name / flow_data casts apply, then mirrors the JSON to
     * storage/app/flows for the Node bridge.
     */
    private function makeFlow(int $wsId, int $userId, string $type, string $name, array $graph): void
    {
        // Replace a prior copy so re-running the seeder is idempotent. Names are
        // encrypted (no SQL WHERE), so scan this workspace's flows and match.
        foreach (Flow::where('workspace_id', $wsId)->where('flow_type', $type)->get() as $existing) {
            if ((string) $existing->flow_name === $name) {
                $existing->forceDelete();
            }
        }

        $flow = new Flow();
        $flow->user_id          = $userId;
        $flow->workspace_id     = $wsId;
        $flow->flow_type        = $type;
        // Stamp provider so an Instagram flow shows under the IG engine and the
        // booted() hook doesn't default it to the workspace's WhatsApp engine.
        if ($type === 'instagram') $flow->provider = 'instagram';
        $flow->flow_name        = $name;
        $flow->category         = 'Showcase';
        $flow->flow_data        = json_encode($graph, JSON_UNESCAPED_SLASHES);
        // Mirror the Trigger node → columns so it behaves like a builder save.
        $flow->trigger_kind     = 'keyword';
        $flow->trigger_keywords = $type === 'call' ? 'support' : 'demo, start, hi';
        $flow->trigger_device_id = null;   // no device → INACTIVE rule, safe
        $flow->is_published     = false;
        $flow->is_active        = true;
        $flow->save();

        // Mirror to disk so the Node runtime can read the graph if it reads files.
        $flow->saveFlowFile($graph);

        $this->command?->info("  • Created flow #{$flow->id}  [{$type}]  {$name}  — " . count($graph['flowNodes']) . ' nodes');
    }

    // ── Layout helper — auto-grid so nodes never overlap on the canvas.
    //    The builder stores position as FLAT top-level x/y (not position:{x,y}). ──
    private function node(int $i, string $type, string $id, array $data, array $extra = []): array
    {
        $perRow = 5; $dx = 300; $dy = 180; $x0 = 80; $y0 = 60;
        return array_merge([
            'id'   => $id,
            'type' => $type,
            'x'    => $x0 + ($i % $perRow) * $dx,
            'y'    => $y0 + intdiv($i, $perRow) * $dy,
            'data' => $data,
        ], $extra);
    }

    private function edge(string $from, ?string $handle, string $to): array
    {
        $e = ['id' => 'e_' . $from . '_' . ($handle ?: 'out') . '_' . $to, 'source' => $from, 'target' => $to];
        if ($handle !== null) $e['sourceHandle'] = $handle;
        return $e;
    }

    /**
     * Chat showcase — one of every chat-builder node type, wired into a
     * single connected graph so every branch/port is reachable.
     */
    private function chatGraph(): array
    {
        $i = 0;
        $n = [];

        $n[] = $this->node($i++, 'trigger', 'trigger', [
            'kind' => 'keyword', 'keywords' => 'demo, start, hi', 'keywordMode' => 'keywords',
            'channel' => 'chat', 'deviceId' => '',
        ], ['isStart' => true]);

        $n[] = $this->node($i++, 'message', 'message', [
            'text' => "👋 Hi {{name}}! This is the *All Nodes* showcase.\nType anything to walk through it.",
        ]);

        $n[] = $this->node($i++, 'sequence', 'sequence', [
            'replies' => [
                ['type' => 'text',  'text' => 'First bubble in the sequence.'],
                ['type' => 'text',  'text' => 'Second bubble, sent right after.'],
                ['type' => 'image', 'url' => 'https://upload.wikimedia.org/wikipedia/commons/3/3f/JPEG_example_flower.jpg', 'caption' => 'A sequence image.'],
            ],
        ]);

        $n[] = $this->node($i++, 'media', 'media', [
            'kind' => 'image', 'url' => 'https://upload.wikimedia.org/wikipedia/commons/3/3f/JPEG_example_flower.jpg', 'caption' => 'Standalone media node.', 'filename' => 'demo.jpg',
        ]);

        $n[] = $this->node($i++, 'template', 'template', [
            'tpl' => '', 'preview' => 'Pick a WhatsApp template in the builder.',
        ]);

        $n[] = $this->node($i++, 'buttons', 'buttons', [
            'prompt'  => 'Pick a path:',
            'options' => ['Ask me something', 'Show a list', 'Run a poll'],
            'var'     => 'path',
        ]);

        $n[] = $this->node($i++, 'ask', 'ask', [
            'prompt' => 'What is your email?', 'var' => 'email',
            'options' => [],
        ]);

        $n[] = $this->node($i++, 'condition', 'condition', [
            'conditions' => [
                ['variable' => 'email', 'operator' => 'contains', 'value' => '@'],
            ],
            'operators' => [],
        ]);

        $n[] = $this->node($i++, 'ai', 'ai', [
            'model' => 'gpt-4o-mini', 'prompt' => 'Reply helpfully to the customer using their message.',
            'save' => 'ai_reply', 'assistant' => 0, 'extract' => false, 'silent' => false,
            'conversational' => false, 'exit_keyword' => 'bye', 'fields' => '',
        ]);

        $n[] = $this->node($i++, 'chatbot', 'chatbot', ['bot' => '']);

        $n[] = $this->node($i++, 'cta', 'cta', [
            'actions' => [
                ['type' => 'url',   'label' => 'Visit site', 'value' => 'https://example.com'],
                ['type' => 'phone', 'label' => 'Call us',    'value' => '+15551234567'],
                ['type' => 'copy',  'label' => 'Copy code',  'value' => 'SAVE10'],
            ],
            'headerText' => 'Quick actions', 'bodyText' => 'Tap one below:', 'footerText' => 'Powered by WaDesk',
        ]);

        $n[] = $this->node($i++, 'location', 'location', [
            'lat' => 19.0760, 'lng' => 72.8777, 'address' => 'Mumbai, India', 'title' => 'Our office',
        ]);

        $n[] = $this->node($i++, 'delay', 'delay', ['unit' => 'min', 'amount' => 2]);

        $n[] = $this->node($i++, 'webhook', 'webhook', [
            'method' => 'POST', 'url' => 'https://httpbin.org/post', 'body' => '{"email":"{{email}}"}',
            'save' => 'api_response', 'contentType' => 'application/json',
            'headers' => [['key' => 'X-Demo', 'value' => '1']],
        ]);

        $n[] = $this->node($i++, 'code', 'code', [
            'code' => "// Sandboxed JS. `vars` in, return a value out.\nreturn (vars.email || '').toLowerCase();",
            'save' => 'email_lc',
        ]);

        $n[] = $this->node($i++, 'mysql', 'mysql', [
            'host' => 'localhost', 'port' => 3306, 'database' => 'shop', 'username' => 'readonly', 'password' => '',
            'sql' => 'SELECT id, name FROM customers WHERE email = "{{email}}" LIMIT 1', 'save' => 'db_rows',
        ]);

        $n[] = $this->node($i++, 'tag', 'tag', ['action' => 'add', 'tagId' => '', 'tag' => 'Showcase Lead']);

        $n[] = $this->node($i++, 'assign', 'assign', ['team' => '', 'userId' => '', 'message' => 'Assigned by showcase flow.']);

        $n[] = $this->node($i++, 'google_sheets', 'google_sheets', [
            'mode' => 'write', 'sheetId' => '', 'tabName' => 'Leads',
            'columns' => [['header' => 'Name', 'value' => '{{name}}'], ['header' => 'Email', 'value' => '{{email}}']],
            'matchByHeader' => true, 'saveAs' => 'sheet_row',
        ]);

        $n[] = $this->node($i++, 'google_docs', 'google_docs', [
            'templateId' => '', 'newTitle' => 'Welcome {{name}}', 'placeholders' => [['key' => 'name', 'value' => '{{name}}']],
            'shareable' => true, 'messageTemplate' => "Here's your doc: {{doc_url}}", 'saveAs' => 'doc_url',
        ]);

        $n[] = $this->node($i++, 'google_form', 'google_form', [
            'formId' => '', 'bodyText' => 'Please fill this quick form:', 'saveAs' => 'form_reply', 'expiresInSec' => 86400,
        ]);

        $n[] = $this->node($i++, 'google_meet', 'google_meet', [
            'title' => 'Consultation with {{name}}', 'durationMinutes' => 30, 'leadMinutes' => 5,
            'sendCalendarInvite' => false, 'messageTemplate' => "Your meeting link: {{meet_link}}\nStarts {{meet_start}}",
        ]);

        $n[] = $this->node($i++, 'wa_form', 'wa_form', [
            'formId' => '', 'bodyText' => 'Tap below to fill our form.', 'ctaLabel' => 'Open form', 'flowVariable' => 'form_submission',
        ]);

        $n[] = $this->node($i++, 'book_appointment', 'book_appointment', [
            'slotCount' => 5, 'prompt' => 'Pick a time that works:', 'confirmation' => '✅ Booked for {{slot}}!',
            'calendarOverride' => '', 'collectEmail' => true,
        ]);

        $n[] = $this->node($i++, 'deal', 'deal', [
            'action' => 'create', 'dealName' => '{{name}} — showcase deal', 'stageId' => '', 'value' => '1000',
            'ownerId' => '', 'saveAs' => 'deal_id',
        ]);

        $n[] = $this->node($i++, 'whatsapp_shop', 'whatsapp_shop', [
            'storeId' => '', 'headerText' => 'Our picks', 'bodyText' => 'Tap a product:', 'footerText' => '',
            'abandonedWaitMinutes' => 5, 'productItems' => [],
        ]);

        $n[] = $this->node($i++, 'woocommerce', 'woocommerce', [
            'storeId' => '', 'headerText' => 'WooCommerce', 'bodyText' => 'Browse our catalog:', 'footerText' => '',
            'abandonedWaitMinutes' => 5, 'productItems' => [],
        ]);

        $n[] = $this->node($i++, 'shopify', 'shopify', [
            'storeId' => '', 'headerText' => 'Shopify', 'bodyText' => 'Featured items:', 'footerText' => '',
            'abandonedWaitMinutes' => 5, 'productItems' => [],
        ]);

        $n[] = $this->node($i++, 'subflow', 'subflow', ['flow' => '']);

        $n[] = $this->node($i++, 'list', 'list', [
            'prompt' => 'Choose a department:', 'button' => 'View options',
            'options' => [
                ['title' => 'Sales',   'description' => 'Talk to sales',   'section' => 'Teams'],
                ['title' => 'Support', 'description' => 'Get help',        'section' => 'Teams'],
                ['title' => 'Billing', 'description' => 'Payment queries', 'section' => 'Admin'],
            ],
            'var' => 'department',
        ]);

        $n[] = $this->node($i++, 'poll', 'poll', [
            'question' => 'How did we do?', 'options' => ['Great', 'Okay', 'Bad'], 'multi' => false,
        ]);

        $n[] = $this->node($i++, 'end', 'end', ['label' => 'End of showcase']);

        // ── Wiring — every node reachable, every port exercised ──
        $e = [];
        $e[] = $this->edge('trigger', 'out', 'message');
        $e[] = $this->edge('message', 'out', 'sequence');
        $e[] = $this->edge('sequence', 'out', 'media');
        $e[] = $this->edge('media', 'out', 'template');
        $e[] = $this->edge('template', 'out', 'buttons');
        // buttons: p0→ask, p1→list, p2→poll
        $e[] = $this->edge('buttons', 'p0', 'ask');
        $e[] = $this->edge('buttons', 'p1', 'list');
        $e[] = $this->edge('buttons', 'p2', 'poll');
        $e[] = $this->edge('ask', 'out', 'condition');
        // condition: yes→ai, no→chatbot
        $e[] = $this->edge('condition', 'yes', 'ai');
        $e[] = $this->edge('condition', 'no', 'chatbot');
        $e[] = $this->edge('ai', 'out', 'cta');
        $e[] = $this->edge('chatbot', 'out', 'end');
        $e[] = $this->edge('cta', 'out', 'location');
        $e[] = $this->edge('location', 'out', 'delay');
        $e[] = $this->edge('delay', 'out', 'webhook');
        $e[] = $this->edge('webhook', 'out', 'code');
        $e[] = $this->edge('code', 'out', 'mysql');
        $e[] = $this->edge('mysql', 'out', 'tag');
        $e[] = $this->edge('tag', 'out', 'assign');
        $e[] = $this->edge('assign', 'out', 'google_sheets');
        $e[] = $this->edge('google_sheets', 'out', 'google_docs');
        $e[] = $this->edge('google_docs', 'out', 'google_form');
        // google_form: submitted→google_meet, timeout→end
        $e[] = $this->edge('google_form', 'submitted', 'google_meet');
        $e[] = $this->edge('google_form', 'timeout', 'end');
        $e[] = $this->edge('google_meet', 'out', 'wa_form');
        $e[] = $this->edge('wa_form', 'out', 'book_appointment');
        // book_appointment: booked→deal, no_slots→end
        $e[] = $this->edge('book_appointment', 'booked', 'deal');
        $e[] = $this->edge('book_appointment', 'no_slots', 'end');
        // deal: created→whatsapp_shop, error→end
        $e[] = $this->edge('deal', 'created', 'whatsapp_shop');
        $e[] = $this->edge('deal', 'error', 'end');
        // commerce: purchased chains forward, abandoned→end
        $e[] = $this->edge('whatsapp_shop', 'purchased', 'woocommerce');
        $e[] = $this->edge('whatsapp_shop', 'abandoned', 'end');
        $e[] = $this->edge('woocommerce', 'purchased', 'shopify');
        $e[] = $this->edge('woocommerce', 'abandoned', 'end');
        $e[] = $this->edge('shopify', 'purchased', 'subflow');
        $e[] = $this->edge('shopify', 'abandoned', 'end');
        $e[] = $this->edge('subflow', 'out', 'end');
        // list: p0/p1/p2 + else all wind up at end
        $e[] = $this->edge('list', 'p0', 'end');
        $e[] = $this->edge('list', 'p1', 'end');
        $e[] = $this->edge('list', 'p2', 'end');
        $e[] = $this->edge('poll', 'out', 'end');

        return ['flowNodes' => $n, 'flowEdges' => $e, 'vars' => (object) []];
    }

    /** Instagram trigger node (channel=instagram, keyword). */
    private function igTrigger(int $i, string $keywords): array
    {
        return $this->node($i, 'trigger', 'trigger', [
            'kind' => 'keyword', 'keywords' => $keywords, 'keywordMode' => 'keywords',
            'channel' => 'instagram', 'deviceId' => '',
        ], ['isStart' => true]);
    }

    /**
     * MULTIPLE Instagram flows. Only IG-allowed node types are used
     * (trigger, message, media, buttons, ask, condition, delay, webhook, ai,
     * end + ig_buttons / ig_reply_comment / ig_products / ig_lead). Every flow
     * includes at least one Condition node so branch routing can be tested.
     *
     * @return array<int, array{0:string, 1:array}>
     */
    private function instagramGraphs(): array
    {
        return [
            ['🧪 IG — Condition Routing', $this->igCondition()],
            ['🧪 IG — All Conditions',    $this->igAllConditions()],
            ['🧪 IG — Welcome + Buttons', $this->igButtons()],
            ['🧪 IG — Products + Lead',    $this->igProductsLead()],
            ['🧪 IG — All IG Nodes',       $this->igAllNodes()],
        ];
    }

    /**
     * Flow — EVERY condition operator + AND/OR compound logic, one Condition
     * node per operator, chained yes→next so all are reachable. Operators:
     * equals, not_equals, contains, not_contains, gt, lt, exists, plus a
     * two-rule AND and a two-rule OR.
     */
    private function igAllConditions(): array
    {
        $i = 0; $n = [];
        $n[] = $this->igTrigger($i++, 'conditions, test');
        $n[] = $this->node($i++, 'ask', 'ask_name', ['prompt' => 'Your name?', 'var' => 'name', 'options' => []]);
        $n[] = $this->node($i++, 'ask', 'ask_age',  ['prompt' => 'Your age?',  'var' => 'age',  'options' => []]);

        // One Condition per operator. `value` empty for `exists`.
        $cond = function (int $i, string $id, string $var, string $op, string $val) {
            return $this->node($i, 'condition', $id, [
                'conditions' => [['variable' => $var, 'operator' => $op, 'value' => $val]],
                'operators'  => [],
            ]);
        };
        $n[] = $cond($i++, 'c_equals',       'name', 'equals',        'John');
        $n[] = $cond($i++, 'c_not_equals',   'name', 'not_equals',    '');
        $n[] = $cond($i++, 'c_contains',     'name', 'contains',      'a');
        $n[] = $cond($i++, 'c_not_contains', 'name', 'not_contains',  'spam');
        $n[] = $cond($i++, 'c_gt',           'age',  'gt',            '18');
        $n[] = $cond($i++, 'c_lt',           'age',  'lt',            '100');
        $n[] = $cond($i++, 'c_exists',       'name', 'exists',        '');

        // Compound AND — both rules must pass (operators = ['AND']).
        $n[] = $this->node($i++, 'condition', 'c_and', [
            'conditions' => [
                ['variable' => 'age',  'operator' => 'gt',     'value' => '18'],
                ['variable' => 'name', 'operator' => 'exists', 'value' => ''],
            ],
            'operators' => ['AND'],
        ]);
        // Compound OR — either rule passes (operators = ['OR']).
        $n[] = $this->node($i++, 'condition', 'c_or', [
            'conditions' => [
                ['variable' => 'name', 'operator' => 'equals', 'value' => 'VIP'],
                ['variable' => 'age',  'operator' => 'gt',     'value' => '65'],
            ],
            'operators' => ['OR'],
        ]);

        $n[] = $this->node($i++, 'message', 'msg_ok', ['text' => '✅ Passed every condition check!']);
        $n[] = $this->node($i++, 'message', 'msg_no', ['text' => '↩️ A condition did not match — routed to the NO branch.']);
        $n[] = $this->node($i++, 'end', 'end', ['label' => 'End']);

        // Chain: each YES → next condition; each NO → the shared "no" message.
        $chain = ['c_equals', 'c_not_equals', 'c_contains', 'c_not_contains', 'c_gt', 'c_lt', 'c_exists', 'c_and', 'c_or'];
        $e = [];
        $e[] = $this->edge('trigger', 'out', 'ask_name');
        $e[] = $this->edge('ask_name', 'out', 'ask_age');
        $e[] = $this->edge('ask_age', 'out', 'c_equals');
        for ($k = 0; $k < count($chain); $k++) {
            $next = $chain[$k + 1] ?? 'msg_ok';   // last YES → success
            $e[] = $this->edge($chain[$k], 'yes', $next);
            $e[] = $this->edge($chain[$k], 'no',  'msg_no');
        }
        $e[] = $this->edge('msg_ok', 'out', 'end');
        $e[] = $this->edge('msg_no', 'out', 'end');
        return ['flowNodes' => $n, 'flowEdges' => $e, 'vars' => (object) []];
    }

    /** Flow A — the pure Condition test: ask → condition → two branches. */
    private function igCondition(): array
    {
        $i = 0; $n = [];
        $n[] = $this->igTrigger($i++, 'hi, start, hello');
        $n[] = $this->node($i++, 'message', 'message', ['text' => "Hi! 👋 What's your email so we can help?"]);
        $n[] = $this->node($i++, 'ask', 'ask', ['prompt' => 'Type your email address:', 'var' => 'email', 'options' => []]);
        $n[] = $this->node($i++, 'condition', 'condition', [
            'conditions' => [['variable' => 'email', 'operator' => 'contains', 'value' => '@']],
            'operators'  => [],
        ]);
        $n[] = $this->node($i++, 'message', 'msg_ok',  ['text' => '✅ Thanks! We saved {{email}} and will be in touch.']);
        $n[] = $this->node($i++, 'message', 'msg_bad', ['text' => "❌ That doesn't look like an email — please try again."]);
        $n[] = $this->node($i++, 'end', 'end', ['label' => 'End']);

        $e = [];
        $e[] = $this->edge('trigger', 'out', 'message');
        $e[] = $this->edge('message', 'out', 'ask');
        $e[] = $this->edge('ask', 'out', 'condition');
        $e[] = $this->edge('condition', 'yes', 'msg_ok');   // email contains @
        $e[] = $this->edge('condition', 'no',  'msg_bad');
        $e[] = $this->edge('msg_ok',  'out', 'end');
        $e[] = $this->edge('msg_bad', 'out', 'end');
        return ['flowNodes' => $n, 'flowEdges' => $e, 'vars' => (object) []];
    }

    /** Flow B — Quick-reply buttons routing into a Condition. */
    private function igButtons(): array
    {
        $i = 0; $n = [];
        $n[] = $this->igTrigger($i++, 'menu, help');
        $n[] = $this->node($i++, 'message', 'message', ['text' => 'Welcome! 👋 How can we help today?']);
        $n[] = $this->node($i++, 'buttons', 'buttons', [
            'prompt'  => 'Pick one:',
            'options' => ['See products', 'Chat with AI', 'Get updates'],
            'var'     => 'choice',
        ]);
        $n[] = $this->node($i++, 'ai', 'ai', [
            'model' => 'gpt-4o-mini', 'prompt' => 'Answer the customer helpfully.', 'save' => 'reply',
            'assistant' => 0, 'extract' => false, 'silent' => false, 'conversational' => false, 'exit_keyword' => 'bye', 'fields' => '',
        ]);
        $n[] = $this->node($i++, 'ask', 'ask', ['prompt' => 'Drop your email for updates:', 'var' => 'email', 'options' => []]);
        $n[] = $this->node($i++, 'condition', 'condition', [
            'conditions' => [['variable' => 'email', 'operator' => 'exists', 'value' => '']],
            'operators'  => [],
        ]);
        $n[] = $this->node($i++, 'message', 'msg_thanks', ['text' => '🎉 You are subscribed!']);
        $n[] = $this->node($i++, 'ig_products', 'ig_products', [
            'selection' => 'pick', 'productIds' => [], 'limit' => 5, 'intro' => 'Our top picks:',
            'orderButton' => true, 'orderLabel' => 'Order', 'detailsButton' => true, 'detailsLabel' => 'View',
        ]);
        $n[] = $this->node($i++, 'end', 'end', ['label' => 'End']);

        $e = [];
        $e[] = $this->edge('trigger', 'out', 'message');
        $e[] = $this->edge('message', 'out', 'buttons');
        $e[] = $this->edge('buttons', 'p0', 'ig_products');  // See products
        $e[] = $this->edge('buttons', 'p1', 'ai');           // Chat with AI
        $e[] = $this->edge('buttons', 'p2', 'ask');          // Get updates
        $e[] = $this->edge('ask', 'out', 'condition');
        $e[] = $this->edge('condition', 'yes', 'msg_thanks');
        $e[] = $this->edge('condition', 'no',  'end');
        $e[] = $this->edge('ai', 'out', 'end');
        $e[] = $this->edge('ig_products', 'out', 'end');
        $e[] = $this->edge('msg_thanks', 'out', 'end');
        return ['flowNodes' => $n, 'flowEdges' => $e, 'vars' => (object) []];
    }

    /** Flow C — Products carousel → capture Lead → Condition → comment reply. */
    private function igProductsLead(): array
    {
        $i = 0; $n = [];
        $n[] = $this->igTrigger($i++, 'shop, buy, products');
        $n[] = $this->node($i++, 'message', 'message', ['text' => 'Here are some products you might like 👇']);
        $n[] = $this->node($i++, 'ig_products', 'ig_products', [
            'selection' => 'pick', 'productIds' => [], 'limit' => 5, 'intro' => 'Tap a product to order:',
            'orderButton' => true, 'orderLabel' => 'Order now', 'detailsButton' => true, 'detailsLabel' => 'Details',
        ]);
        $n[] = $this->node($i++, 'ig_lead', 'ig_lead', [
            'nameVar' => 'lead_name', 'emailVar' => 'lead_email', 'phoneVar' => 'lead_phone', 'notesVar' => '',
            'createDeal' => true, 'ack' => 'Thanks — a specialist will DM you shortly!',
        ]);
        $n[] = $this->node($i++, 'condition', 'condition', [
            'conditions' => [['variable' => 'lead_email', 'operator' => 'exists', 'value' => '']],
            'operators'  => [],
        ]);
        $n[] = $this->node($i++, 'ig_reply_comment', 'ig_reply_comment', ['message' => 'Thanks for your interest! Check your DMs 📩']);
        $n[] = $this->node($i++, 'message', 'msg_none', ['text' => 'No email captured — we will follow up here.']);
        $n[] = $this->node($i++, 'end', 'end', ['label' => 'End']);

        $e = [];
        $e[] = $this->edge('trigger', 'out', 'message');
        $e[] = $this->edge('message', 'out', 'ig_products');
        $e[] = $this->edge('ig_products', 'out', 'ig_lead');
        $e[] = $this->edge('ig_lead', 'out', 'condition');
        $e[] = $this->edge('condition', 'yes', 'ig_reply_comment');
        $e[] = $this->edge('condition', 'no',  'msg_none');
        $e[] = $this->edge('ig_reply_comment', 'out', 'end');
        $e[] = $this->edge('msg_none', 'out', 'end');
        return ['flowNodes' => $n, 'flowEdges' => $e, 'vars' => (object) []];
    }

    /** Flow D — every IG-allowed node type in one connected graph. */
    private function igAllNodes(): array
    {
        $i = 0; $n = [];
        $n[] = $this->igTrigger($i++, 'demo, everything');
        $n[] = $this->node($i++, 'message', 'message', ['text' => '👋 This flow uses every Instagram node.']);
        $n[] = $this->node($i++, 'media', 'media', ['kind' => 'image', 'url' => 'https://upload.wikimedia.org/wikipedia/commons/3/3f/JPEG_example_flower.jpg', 'caption' => 'A promo image', 'filename' => 'promo.jpg']);
        $n[] = $this->node($i++, 'buttons', 'buttons', ['prompt' => 'What next?', 'options' => ['Ask me', 'Talk to AI', 'Products'], 'var' => 'choice']);
        $n[] = $this->node($i++, 'ask', 'ask', ['prompt' => 'What is your name?', 'var' => 'name', 'options' => []]);
        $n[] = $this->node($i++, 'condition', 'condition', [
            'conditions' => [['variable' => 'name', 'operator' => 'exists', 'value' => '']],
            'operators'  => [],
        ]);
        $n[] = $this->node($i++, 'ai', 'ai', [
            'model' => 'gpt-4o-mini', 'prompt' => 'Greet {{name}} and answer their question.', 'save' => 'reply',
            'assistant' => 0, 'extract' => false, 'silent' => false, 'conversational' => false, 'exit_keyword' => 'bye', 'fields' => '',
        ]);
        $n[] = $this->node($i++, 'delay', 'delay', ['unit' => 'min', 'amount' => 1]);
        $n[] = $this->node($i++, 'webhook', 'webhook', [
            'method' => 'POST', 'url' => 'https://httpbin.org/post', 'body' => '{"name":"{{name}}"}',
            'save' => 'api_response', 'contentType' => 'application/json', 'headers' => [['key' => 'X-Demo', 'value' => '1']],
        ]);
        $n[] = $this->node($i++, 'ig_buttons', 'ig_buttons', [
            'text' => 'Choose an action:',
            'buttons' => [
                ['type' => 'web_url',  'title' => 'Visit site', 'url' => 'https://example.com'],
                ['type' => 'postback', 'title' => 'More info',  'payload' => 'MORE_INFO'],
            ],
        ]);
        $n[] = $this->node($i++, 'ig_products', 'ig_products', [
            'selection' => 'pick', 'productIds' => [], 'limit' => 5, 'intro' => 'Featured items:',
            'orderButton' => true, 'orderLabel' => 'Order', 'detailsButton' => true, 'detailsLabel' => 'View',
        ]);
        $n[] = $this->node($i++, 'ig_lead', 'ig_lead', [
            'nameVar' => 'lead_name', 'emailVar' => 'lead_email', 'phoneVar' => 'lead_phone', 'notesVar' => '',
            'createDeal' => true, 'ack' => 'Saved! 🙌',
        ]);
        $n[] = $this->node($i++, 'ig_reply_comment', 'ig_reply_comment', ['message' => 'Thanks for the comment! DMing you now 📩']);
        $n[] = $this->node($i++, 'end', 'end', ['label' => 'End']);

        $e = [];
        $e[] = $this->edge('trigger', 'out', 'message');
        $e[] = $this->edge('message', 'out', 'media');
        $e[] = $this->edge('media', 'out', 'buttons');
        $e[] = $this->edge('buttons', 'p0', 'ask');          // Ask me
        $e[] = $this->edge('buttons', 'p1', 'ai');           // Talk to AI
        $e[] = $this->edge('buttons', 'p2', 'ig_products');  // Products
        $e[] = $this->edge('ask', 'out', 'condition');
        $e[] = $this->edge('condition', 'yes', 'ig_buttons');
        $e[] = $this->edge('condition', 'no',  'delay');
        $e[] = $this->edge('ig_buttons', 'out', 'ig_reply_comment');
        $e[] = $this->edge('delay', 'out', 'webhook');
        $e[] = $this->edge('webhook', 'out', 'end');
        $e[] = $this->edge('ai', 'out', 'ig_lead');
        $e[] = $this->edge('ig_products', 'out', 'ig_lead');
        $e[] = $this->edge('ig_lead', 'out', 'ig_reply_comment');
        $e[] = $this->edge('ig_reply_comment', 'out', 'end');
        return ['flowNodes' => $n, 'flowEdges' => $e, 'vars' => (object) []];
    }
}
