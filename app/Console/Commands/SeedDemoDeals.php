<?php

namespace App\Console\Commands;

use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Populate a workspace's default pipeline with realistic demo deals so the
 * /deals board can be shown to a customer. Idempotent — demo rows are tagged
 * meta->demo=true; re-running with --fresh clears and re-seeds them.
 *
 *   php artisan deals:demo 1
 *   php artisan deals:demo 1 --fresh
 */
class SeedDemoDeals extends Command
{
    protected $signature = 'deals:demo {workspace : Workspace id} {--fresh : Wipe existing demo deals first}';
    protected $description = 'Seed realistic demo deals into a workspace pipeline for demos';

    public function handle(): int
    {
        $wsId = (int) $this->argument('workspace');
        $ws   = Workspace::find($wsId);
        if (!$ws) {
            $this->error("Workspace {$wsId} not found.");
            return self::FAILURE;
        }

        $pipeline = Pipeline::ensureDefaultForWorkspace($wsId);
        $stages   = $pipeline->stages()->orderBy('sort_order')->get()->values(); // 0..5
        if ($stages->count() < 6) {
            $this->error('Pipeline does not have the expected 6 stages.');
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $ids = Deal::where('workspace_id', $wsId)->whereJsonContains('meta->demo', true)->pluck('id');
            \App\Models\DealActivity::whereIn('deal_id', $ids)->delete();
            Deal::whereIn('id', $ids)->delete();
            $this->info("Cleared {$ids->count()} demo deals.");
        }

        if (Deal::where('workspace_id', $wsId)->whereJsonContains('meta->demo', true)->exists()) {
            $this->warn('Demo deals already present — run with --fresh to reseed. Skipping.');
            return self::SUCCESS;
        }

        $owner = (int) ($ws->owner_user_id ?: 0) ?: null;

        // [stageIndex, title, source, value(major ₹), ageHours]
        $rows = [
            [0, 'Bulk roses · corporate gifting',   'inbox',   180000, 2],
            [0, 'Wedding decor enquiry',            'form',    320000, 5],
            [0, 'Monthly subscription · café',      'order',    84000, 26],
            [0, 'Event florals · 200 pax',          'manual',  240000, 48],
            [1, 'Hotel weekly arrangements',        'inbox',   460000, 72],
            [1, 'Anniversary bouquets',             'form',    120000, 74],
            [1, 'Office plants + maintenance',      'shopify', 210000, 96],
            [2, 'Annual retainer · 3 outlets',      'inbox',   680000, 50],
            [2, "Mother's Day bulk order",          'form',    340000, 120],
            [2, 'Restaurant weekly · 12 tables',    'woo',     400000, 168],
            [3, 'Mall kiosk · 6-month deal',        'inbox',   720000, 28],
            [3, 'Spa partnership · monthly',        'manual',  310000, 50],
            [3, 'Boutique standing order',          'shopify', 220000, 70],
            [4, 'Corporate Diwali gifting',         'order',   540000, 24],
            [4, 'Café monthly · 1yr',               'order',   360000, 72],
            [4, 'Wedding · full decor',             'inbox',   380000, 144],
            [5, 'Discount-only enquiry',            'inbox',    90000, 96],
        ];

        $n = 0;
        foreach ($rows as [$si, $title, $source, $value, $ageH]) {
            $stage = $stages[$si];
            $deal  = Deal::create([
                'workspace_id'  => $wsId,
                'pipeline_id'   => $pipeline->id,
                'stage_id'      => $stage->id,
                'title'         => $title,
                'value_minor'   => $value * 100,
                'currency'      => $pipeline->currency,
                'owner_user_id' => $owner,
                'source'        => $source,
                'meta'          => ['demo' => true],
            ]);

            // Backdate created_at + won/lost timestamps via raw update so the
            // card ages read realistically (Eloquent would stamp "now").
            $when = now()->subHours($ageH);
            $patch = ['created_at' => $when, 'updated_at' => $when];
            if ($stage->is_won)  $patch['won_at']  = $when;
            if ($stage->is_lost) $patch['lost_at'] = $when;
            DB::table('deals')->where('id', $deal->id)->update($patch);
            $n++;
        }

        $this->info("Seeded {$n} demo deals into workspace {$wsId} ({$ws->name}).");
        return self::SUCCESS;
    }
}
