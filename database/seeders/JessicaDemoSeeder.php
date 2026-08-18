<?php

namespace Database\Seeders;

use App\Models\Flow;
use App\Models\KeywordReply;
use App\Models\User;
use App\Models\WaProduct;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

/**
 * Jessica Yu — one-click DEMO seeder (Unofficial API / Baileys only).
 *
 * Seeds everything she needs to test all THREE use cases on her own server:
 *   A) Farm record  → AI columns → Google Sheet   (keyword: "farm")
 *   B) AI ordering  → anti-sellout → group @mention (keyword: "order")
 *   C) External DB / API query                      (Make-Request + MySQL nodes — built-in)
 *
 * Portable: it does NOT hard-code workspace/user ids. It targets the workspace
 * in env `JESSICA_DEMO_WS` (else the first workspace) and that workspace's owner.
 * Idempotent: re-running replaces the demo products + demo flows cleanly.
 *
 * Run:   php artisan db:seed --class=Database\\Seeders\\JessicaDemoSeeder
 *
 * Prereqs (printed at the end too): run the 3 migrations 2026_06_19_*, restart
 * the Node bridge, set a real AI key, connect a Baileys number, and (for the
 * farm flow) pick a Google Sheet in the AI/Sheets node.
 */
class JessicaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $currency = strtoupper((string) (env('JESSICA_DEMO_CURRENCY', 'MYR')));

        // ---- Resolve target workspace + owner (no hard-coded ids) ----------
        $wsId = (int) env('JESSICA_DEMO_WS', 0);
        $ws   = $wsId ? Workspace::find($wsId) : Workspace::query()->orderBy('id')->first();
        if (!$ws) {
            $this->command->error('No workspace found. Create a workspace first, then re-run.');
            return;
        }
        $wsId   = (int) $ws->id;
        $userId = (int) ($ws->owner_user_id ?? optional(User::query()->orderBy('id')->first())->id ?? 0);
        if (!$userId) {
            $this->command->error('No user found for the workspace owner. Create a user first.');
            return;
        }

        $this->command->info("Jessica demo → workspace #{$wsId} ({$ws->name}), owner user #{$userId}, currency {$currency}");

        $this->seedProducts($wsId, $userId, $currency);
        $farm  = $this->seedFarmFlow($wsId, $userId);
        $order = $this->seedOrderFlow($wsId, $userId);

        $this->printNextSteps($wsId, $farm, $order);
    }

    /* ─────────────────────────── Use case B — products ─────────────────── */

    private function seedProducts(int $wsId, int $userId, string $currency): void
    {
        // Idempotent: wipe prior demo rows (incl. soft-deleted) for this ws.
        WaProduct::withTrashed()->where('workspace_id', $wsId)
            ->where('sku', 'like', 'TEST-CHK-%')->forceDelete();

        $rows = [
            ['sku' => 'TEST-CHK-DRUM',  'name' => 'Chicken Drumstick',              'slug' => 'test-chicken-drumstick',
             'price_minor' => 600,  'stock_qty' => 50,
             'aliases' => ['drumstick', 'drumsticks', 'chicken drumstick', 'chicken drumsticks', '鸡腿', 'kaki ayam', 'ayam drumstick']],
            ['sku' => 'TEST-CHK-WING',  'name' => 'Chicken Wings',                   'slug' => 'test-chicken-wings',
             'price_minor' => 500,  'stock_qty' => 50,
             'aliases' => ['wing', 'wings', 'chicken wing', 'chicken wings', '鸡翅', 'sayap ayam', 'kepak ayam']],
            ['sku' => 'TEST-CHK-MAR',   'name' => 'Marinated Chicken (Original)',    'slug' => 'test-marinated-chicken-original',
             'price_minor' => 1500, 'stock_qty' => 30,
             'aliases' => ['marinated chicken', 'marinated', 'original marinated', '腌鸡', 'ayam perap', 'ayam marinate']],
            // LOW STOCK on purpose → lets her test anti-sellout (order > 3).
            ['sku' => 'TEST-CHK-SPICY', 'name' => 'Spicy Marinated Chicken',         'slug' => 'test-spicy-marinated-chicken',
             'price_minor' => 1800, 'stock_qty' => 3,
             'aliases' => ['spicy marinated chicken', 'spicy chicken', 'spicy', '辣腌鸡', 'ayam pedas']],
        ];

        foreach ($rows as $r) {
            $p = new WaProduct();
            $p->workspace_id  = $wsId;
            $p->user_id       = $userId;
            $p->sku           = $r['sku'];
            $p->name          = $r['name'];
            $p->slug          = $r['slug'] . '-ws' . $wsId; // slug unique-ish per ws
            $p->description   = 'Demo product for AI ordering (Jessica).';
            $p->price_minor   = $r['price_minor'];
            $p->currency_code = $currency;
            $p->status        = 'active';
            $p->availability  = 'in stock';
            $p->in_stock      = true;
            $p->stock_qty     = $r['stock_qty'];
            $p->reserved_qty  = 0;
            $p->aliases_json  = $r['aliases'];
            $p->save();
        }
        $this->command->info('  ✓ 4 demo products (Spicy Marinated = stock 3 → anti-sellout test)');
    }

    /* ─────────────────────────── Use case A — farm → Sheet ─────────────── */

    private function seedFarmFlow(int $wsId, int $userId): Flow
    {
        $name = 'DEMO — Farm record → Google Sheet';
        $this->wipeFlow($name);

        $prompt =
            "You sort FARM WORK-RECORD messages into columns. One WhatsApp message may "
          . "contain ONE or SEVERAL records. Map each record to these keys:\n"
          . "- FARM: the farm / block code (e.g. M902-B)\n"
          . "- DATE: the date (e.g. 5/5/2026)\n"
          . "- NAME: the worker / supervisor name(s); join multiple names with ' / '\n"
          . "- FIELD_BATCH_RATE: the field or batch number plus any rate / product line (e.g. 'M1124 PRD 2-4P PROTECTION')\n"
          . "- VOLUME: the volume (e.g. '2.26 DRUM /ORG')\n"
          . "- EARNINGS: the earnings amount if stated, otherwise empty\n"
          . "Read the WHOLE message before answering.";

        $flowData = [
            'flowNodes' => [
                ['id' => 'n_trig', 'type' => 'trigger', 'x' => -260, 'y' => 80, 'isStart' => true,
                 'data' => ['kind' => 'keyword', 'keywords' => 'farm', 'tagId' => '', 'groupId' => '', 'deviceId' => '']],
                ['id' => 'n_ai', 'type' => 'ai', 'x' => 120, 'y' => 80,
                 'data' => [
                    'model'   => 'gpt-4o-mini',
                    'prompt'  => $prompt,
                    'save'    => 'reply',
                    'extract' => true,
                    'silent'  => true,
                    'fields'  => 'FARM, DATE, NAME, FIELD_BATCH_RATE, VOLUME, EARNINGS',
                 ]],
                ['id' => 'n_sheet', 'type' => 'google_sheets', 'x' => 520, 'y' => 80,
                 'data' => [
                    'mode'    => 'write',
                    'sheetId' => '', // ← Jessica sets this with the Google picker in the builder
                    'tabName' => '',
                    'columns' => [
                        ['header' => 'FARM',                    'value' => '{{reply.FARM}}'],
                        ['header' => 'DATE',                    'value' => '{{reply.DATE}}'],
                        ['header' => 'NAME',                    'value' => '{{reply.NAME}}'],
                        ['header' => 'FIELD/BATCH NUMBER RATE', 'value' => '{{reply.FIELD_BATCH_RATE}}'],
                        ['header' => 'VOLUME',                  'value' => '{{reply.VOLUME}}'],
                        ['header' => 'EARNINGS',                'value' => '{{reply.EARNINGS}}'],
                    ],
                 ]],
                ['id' => 'n_end', 'type' => 'end', 'x' => 900, 'y' => 80, 'data' => []],
            ],
            'flowEdges' => [
                ['id' => 'e1', 'source' => 'n_trig',  'sourceHandle' => 'out', 'target' => 'n_ai',    'kind' => null],
                ['id' => 'e2', 'source' => 'n_ai',    'sourceHandle' => 'out', 'target' => 'n_sheet', 'kind' => null],
                ['id' => 'e3', 'source' => 'n_sheet', 'sourceHandle' => 'out', 'target' => 'n_end',   'kind' => null],
            ],
            'vars' => [],
        ];

        $flow = $this->makeFlow($wsId, $userId, $name, 'farm', $flowData);
        $this->command->info("  ✓ Flow #{$flow->id}  \"{$name}\"  (keyword: farm)");
        return $flow;
    }

    /* ─────────────────────────── Use case B — ordering ────────────────── */

    private function seedOrderFlow(int $wsId, int $userId): Flow
    {
        $name  = 'DEMO — Place an order (AI ordering)';
        $this->wipeFlow($name);

        $app   = rtrim((string) env('APP_URL', 'http://127.0.0.1:8008'), '/');
        $token = (string) env('NODE_WEBHOOK_TOKEN', '');
        $hdr   = [['key' => 'X-Node-Token', 'value' => $token]];

        $flowData = [
            'flowNodes' => [
                ['id' => 'n_trig', 'type' => 'trigger', 'x' => -300, 'y' => 100, 'isStart' => true,
                 'data' => ['kind' => 'keyword', 'keywords' => 'order', 'tagId' => '', 'groupId' => '', 'deviceId' => '']],

                ['id' => 'n_parse', 'type' => 'webhook', 'x' => 40, 'y' => 100,
                 'data' => ['method' => 'POST', 'contentType' => 'application/json', 'save' => 'parse',
                    'url'  => $app . '/api/flow-node/order-parse',
                    'body' => '{"workspace_id":' . $wsId . ',"customer_phone":"{{phone}}","text":"{{user_message}}"}',
                    'headers' => $hdr]],

                ['id' => 'n_summary', 'type' => 'message', 'x' => 380, 'y' => 100,
                 'data' => ['text' => '{{parse.summary}}']],

                ['id' => 'n_ask', 'type' => 'ask', 'x' => 720, 'y' => 100,
                 'data' => ['prompt' => 'Reply *CONFIRM* to place your order, or *CANCEL* to stop.', 'var' => 'answer', 'validate' => 'text', 'options' => []]],

                ['id' => 'n_cond', 'type' => 'condition', 'x' => 1060, 'y' => 100,
                 'data' => ['conditions' => [['variable' => 'answer', 'operator' => 'contains', 'value' => 'confirm']], 'operators' => []]],

                ['id' => 'n_confirm', 'type' => 'webhook', 'x' => 1400, 'y' => -40,
                 'data' => ['method' => 'POST', 'contentType' => 'application/json', 'save' => 'confirmed',
                    'url'  => $app . '/api/flow-node/order-confirm',
                    'body' => '{"workspace_id":' . $wsId . ',"customer_phone":"{{phone}}","notify_group":true}',
                    'headers' => $hdr]],
                ['id' => 'n_done', 'type' => 'message', 'x' => 1740, 'y' => -40, 'data' => ['text' => '{{confirmed.summary}}']],
                ['id' => 'n_end_ok', 'type' => 'end', 'x' => 2080, 'y' => -40, 'data' => []],

                ['id' => 'n_cancel', 'type' => 'webhook', 'x' => 1400, 'y' => 240,
                 'data' => ['method' => 'POST', 'contentType' => 'application/json', 'save' => 'cancelled',
                    'url'  => $app . '/api/flow-node/order-cancel',
                    'body' => '{"workspace_id":' . $wsId . ',"customer_phone":"{{phone}}"}',
                    'headers' => $hdr]],
                ['id' => 'n_bye', 'type' => 'message', 'x' => 1740, 'y' => 240,
                 'data' => ['text' => 'No problem — your order was cancelled. Send *order* any time to start again.']],
                ['id' => 'n_end_no', 'type' => 'end', 'x' => 2080, 'y' => 240, 'data' => []],
            ],
            'flowEdges' => [
                ['id' => 'e1',  'source' => 'n_trig',    'sourceHandle' => 'out', 'target' => 'n_parse',   'kind' => null],
                ['id' => 'e2',  'source' => 'n_parse',   'sourceHandle' => 'out', 'target' => 'n_summary', 'kind' => null],
                ['id' => 'e3',  'source' => 'n_summary', 'sourceHandle' => 'out', 'target' => 'n_ask',     'kind' => null],
                ['id' => 'e4',  'source' => 'n_ask',     'sourceHandle' => 'out', 'target' => 'n_cond',    'kind' => null],
                ['id' => 'e5',  'source' => 'n_cond',    'sourceHandle' => 'yes', 'target' => 'n_confirm', 'kind' => null],
                ['id' => 'e6',  'source' => 'n_confirm', 'sourceHandle' => 'out', 'target' => 'n_done',    'kind' => null],
                ['id' => 'e7',  'source' => 'n_done',    'sourceHandle' => 'out', 'target' => 'n_end_ok',  'kind' => null],
                ['id' => 'e8',  'source' => 'n_cond',    'sourceHandle' => 'no',  'target' => 'n_cancel',  'kind' => null],
                ['id' => 'e9',  'source' => 'n_cancel',  'sourceHandle' => 'out', 'target' => 'n_bye',     'kind' => null],
                ['id' => 'e10', 'source' => 'n_bye',     'sourceHandle' => 'out', 'target' => 'n_end_no',  'kind' => null],
            ],
            'vars' => [],
        ];

        $flow = $this->makeFlow($wsId, $userId, $name, 'order', $flowData);
        $tok = $token !== '' ? 'baked ✓' : '⚠ NODE_WEBHOOK_TOKEN empty in .env';
        $this->command->info("  ✓ Flow #{$flow->id}  \"{$name}\"  (keyword: order)  [X-Node-Token {$tok}]");
        return $flow;
    }

    /* ─────────────────────────── helpers ──────────────────────────────── */

    private function wipeFlow(string $name): void
    {
        foreach (Flow::withTrashed()->where('flow_name', $name)->get() as $old) {
            KeywordReply::withTrashed()->where('flow_id', $old->id)->forceDelete();
            $old->forceDelete();
        }
    }

    private function makeFlow(int $wsId, int $userId, string $name, string $keyword, array $flowData): Flow
    {
        $flow = new Flow();
        $flow->user_id           = $userId;
        $flow->workspace_id      = $wsId;
        $flow->flow_name         = $name;
        $flow->category          = 'demo';
        $flow->trigger_kind      = 'keyword';
        $flow->trigger_value     = null;
        $flow->trigger_keywords  = $keyword;
        $flow->trigger_device_id = null;            // workspace-wide
        $flow->is_published      = true;
        $flow->is_active         = true;
        $flow->published_at      = now();
        $flow->provider          = 'baileys';       // Unofficial API only (her agreed engine)
        $flow->flow_data         = json_encode($flowData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $flow->save();                              // Flow::saved hook syncs the keyword trigger
        return $flow;
    }

    private function printNextSteps(int $wsId, Flow $farm, Flow $order): void
    {
        // Try to surface a connected bot number for the wa.me ordering link.
        $bot = null;
        try {
            $d = \App\Models\Device::query()
                ->where('workspace_id', $wsId)->where('status', 'connected')
                ->orderByDesc('id')->first();
            if ($d) $bot = preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number));
        } catch (\Throwable $e) {}

        $link = $bot
            ? "https://wa.me/{$bot}?text=order%202%20chicken%20drumstick"
            : "https://wa.me/<your-bot-number>?text=order%202%20chicken%20drumstick";

        $this->command->line('');
        $this->command->info('════════ Jessica demo seeded — how to test ════════');
        $this->command->line("A) FARM → SHEET   : DM the bot the word  farm  then a farm record block.");
        $this->command->line("                    First open Flows → \"{$farm->flow_name}\" → Sheets node → pick a Google Sheet.");
        $this->command->line("B) AI ORDERING    : DM the bot:  {$link}");
        $this->command->line("                    Try \"order 5 spicy marinated chicken\" to see anti-sellout (stock=3).");
        $this->command->line("                    On CONFIRM, the order posts to her WhatsApp group (bot must be a member;");
        $this->command->line("                    set a group code at /store/groups). Status changes re-notify the group.");
        $this->command->line("C) EXTERNAL DB    : use the Make-Request or MySQL node in any flow (built-in).");
        $this->command->line('');
        $this->command->warn('Prereqs: run migrations 2026_06_19_* · restart the Node bridge · set a real AI key · connect a Baileys number.');
        $this->command->line('════════════════════════════════════════════════════');
    }
}
