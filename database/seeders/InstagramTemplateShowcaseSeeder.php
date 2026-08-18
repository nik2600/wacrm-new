<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WaTemplate;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

/**
 * InstagramTemplateShowcaseSeeder — seeds a set of ready-to-test Instagram
 * message templates (channel=instagram, local/no-Meta, status=approved) that
 * cover every shape: plain text, quick replies, CTA buttons, a CTA+quick-reply
 * mix, and an image-header template.
 *
 * Target: FLOW_SEED_EMAIL's workspace, else FLOW_SEED_WS_ID, else first workspace.
 * Idempotent — replaces its own "🧪 IG ·" templates on re-run.
 *
 * Run: php artisan db:seed --class=Database\\Seeders\\InstagramTemplateShowcaseSeeder
 */
class InstagramTemplateShowcaseSeeder extends Seeder
{
    private const IMG = 'https://upload.wikimedia.org/wikipedia/commons/3/3f/JPEG_example_flower.jpg';

    public function run(): void
    {
        $email = trim((string) env('FLOW_SEED_EMAIL', ''));
        $ws = null; $userId = 0;
        if ($email !== '' && ($u = User::where('email', $email)->first())) {
            $ws = Workspace::find($u->current_workspace_id) ?: Workspace::where('owner_user_id', $u->id)->orderBy('id')->first();
            $userId = (int) $u->id;
        }
        if (! $ws) {
            $wsId = (int) env('FLOW_SEED_WS_ID', 0);
            $ws = $wsId ? Workspace::find($wsId) : Workspace::query()->orderBy('id')->first();
        }
        if (! $ws) { $this->command?->warn('[IG-TPL] No workspace — nothing seeded.'); return; }
        if (! $userId) $userId = (int) ($ws->owner_user_id ?: User::where('current_workspace_id', $ws->id)->value('id') ?: User::query()->orderBy('id')->value('id'));

        $this->command?->info("[IG-TPL] Seeding into workspace #{$ws->id} ({$ws->name})");

        $rows = [
            [
                'name' => '🧪 IG · Welcome (Plain)',
                'body' => "👋 Hi {{name}}! Thanks for reaching out to us on Instagram. How can we help you today?",
                'category' => 'MARKETING',
                'buttons' => [],
            ],
            [
                'name' => '🧪 IG · Menu (Quick Replies)',
                'body' => "What would you like to do? Tap an option below 👇",
                'category' => 'UTILITY',
                'buttons' => [
                    ['type' => 'QUICK_REPLY', 'text' => 'See products'],
                    ['type' => 'QUICK_REPLY', 'text' => 'Talk to support'],
                    ['type' => 'QUICK_REPLY', 'text' => 'Track my order'],
                ],
            ],
            [
                'name' => '🧪 IG · Shop Now (CTA)',
                'body' => "🛍️ Our new collection is live! Browse the catalog or call us to order.",
                'category' => 'MARKETING',
                'buttons' => [
                    ['type' => 'URL', 'text' => 'Shop now', 'url' => 'https://example.com/shop'],
                    ['type' => 'PHONE_NUMBER', 'text' => 'Call us', 'phone_number' => '+15551234567'],
                ],
            ],
            [
                'name' => '🧪 IG · Offer (Mixed)',
                'body' => "🎉 Flat 20% OFF this week only! Use code SAVE20 at checkout.",
                'footer' => 'Offer ends Sunday',
                'category' => 'MARKETING',
                'buttons' => [
                    ['type' => 'URL', 'text' => 'Claim offer', 'url' => 'https://example.com/offer'],
                    ['type' => 'QUICK_REPLY', 'text' => 'Remind me later'],
                    ['type' => 'QUICK_REPLY', 'text' => 'Not interested'],
                ],
            ],
            [
                'name' => '🧪 IG · Promo (Image)',
                'body' => "✨ Introducing our best-seller — now back in stock. Tap below to grab yours!",
                'category' => 'MARKETING',
                'image' => self::IMG,
                'buttons' => [
                    ['type' => 'URL', 'text' => 'Buy now', 'url' => 'https://example.com/buy'],
                    ['type' => 'QUICK_REPLY', 'text' => 'Send me details'],
                ],
            ],
        ];

        // Idempotent: drop prior showcase IG templates (name is encrypted → scan).
        foreach (WaTemplate::where('workspace_id', $ws->id)->where('channel', 'instagram')->get() as $t) {
            if (str_starts_with((string) $t->template_name, '🧪 IG · ')) $t->forceDelete();
        }

        foreach ($rows as $r) {
            $t = new WaTemplate();
            $t->user_id       = $userId;
            $t->workspace_id  = $ws->id;
            $t->channel       = 'instagram';
            $t->status        = 'approved';           // local IG template — ready to use
            $t->template_type = 'standard';
            $t->category      = $r['category'];
            $t->language      = 'en';
            $t->template_name = $r['name'];
            $t->template_body = $r['body'];
            if (! empty($r['footer'])) $t->footer = $r['footer'];
            if (! empty($r['buttons'])) $t->buttons = $r['buttons'];
            if (! empty($r['image'])) {
                $t->attachment_type   = 'image';
                $t->header_sample_url = $r['image'];
            }
            $t->save();
            $this->command?->info("  • #{$t->id}  {$r['name']}  (" . count($r['buttons']) . ' buttons' . (empty($r['image']) ? '' : ', image') . ')');
        }

        $this->command?->info('[IG-TPL] Done — ' . count($rows) . ' templates. Open /templates → Instagram tab.');
    }
}
