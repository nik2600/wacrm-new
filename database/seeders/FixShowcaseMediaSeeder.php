<?php

namespace Database\Seeders;

use App\Models\Flow;
use Illuminate\Database\Seeder;

/**
 * FixShowcaseMediaSeeder — rewrites the placeholder image URL in the seeded
 * showcase flows WITHOUT touching their published / account-binding state.
 *
 * The original seed used https://picsum.photos/... which 302-redirects to a
 * random photo; Meta's Instagram attachment fetch rejects redirect URLs, so the
 * Send-media node silently failed. Swap it for a DIRECT image URL that Meta can
 * fetch as-is.
 *
 * Only rewrites flow_data — leaves is_published, is_active, trigger_device_id,
 * provider, keywords untouched, so an already-published+bound flow keeps working.
 *
 * Run: php artisan db:seed --class=Database\\Seeders\\FixShowcaseMediaSeeder
 */
class FixShowcaseMediaSeeder extends Seeder
{
    // A canonical direct JPEG on a Meta-friendly host (used in Meta's own docs).
    private const DIRECT_IMAGE = 'https://upload.wikimedia.org/wikipedia/commons/3/3f/JPEG_example_flower.jpg';

    public function run(): void
    {
        $flows = Flow::query()->where('category', 'Showcase')->get();
        $fixed = 0;

        foreach ($flows as $flow) {
            $json = json_encode($flow->decoded_flow_data, JSON_UNESCAPED_SLASHES);
            if ($json === false) continue;
            // Rewrite every previously-tried placeholder host Meta rejected.
            if (! str_contains($json, 'picsum.photos') && ! str_contains($json, 'gstatic.com/webp')) continue;

            $json = preg_replace('#https?://picsum\.photos/\d+/\d+#', self::DIRECT_IMAGE, $json);
            $json = str_replace('https://www.gstatic.com/webp/gallery/1.jpg', self::DIRECT_IMAGE, $json);
            $arr  = json_decode($json, true);
            if (! is_array($arr)) continue;

            // Update flow_data ONLY. saveQuietly avoids re-running notification
            // hooks, but we still want the keyword/automation sync — so use a
            // normal save; it re-reads trigger_* from the COLUMNS (unchanged),
            // preserving the published automation binding.
            $flow->flow_data = json_encode($arr, JSON_UNESCAPED_SLASHES);
            $flow->save();
            $flow->saveFlowFile($arr);

            $fixed++;
            $this->command?->info("  • Fixed media URL in flow #{$flow->id} ({$flow->flow_name})");
        }

        $this->command?->info("[FixShowcaseMedia] Done — {$fixed} flow(s) updated.");
    }
}
