<?php

namespace Database\Seeders;

use App\Models\WpCampaign;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds three sample campaigns so the index page renders meaningful KPI
 * tiles + cards immediately after `php artisan db:seed`. These rows live
 * in the encrypted columns so they exercise the encrypted casts.
 */
class WaCampaignSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            [
                'campaign_name'    => 'New Year VIP drop',
                'campaign_type'    => 'template',
                'status'           => 'completed',
                'custom_message'   => 'Happy New Year, {{name}}! Celebrate 2026 with 25% off.',
                'custom_footer'    => 'Reply STOP to opt out',
                'schedule_type'    => 'scheduled',
                'send_date'        => Carbon::now()->subDays(20)->toDateString(),
                'send_time'        => '09:30:00',
                'timezone'         => 'Asia/Kolkata',
                'total_recipients' => 10000,
                'sent_count'       => 9840,
                'delivered_count'  => 9421,
                'read_count'       => 6812,
                'failed_count'     => 48,
                'completed_at'     => Carbon::now()->subDays(20),
            ],
            [
                'campaign_name'    => 'Welcome series v3',
                'campaign_type'    => 'text',
                'status'           => 'running',
                'custom_message'   => 'Hi {{name}}, welcome aboard. Reply HELP anytime.',
                'custom_footer'    => 'Reply STOP to opt out',
                'schedule_type'    => 'now',
                'total_recipients' => 2400,
                'sent_count'       => 2400,
                'delivered_count'  => 2188,
                'read_count'       => 1064,
                'failed_count'     => 21,
            ],
            [
                'campaign_name'    => 'Yoga retreat flow invite',
                'campaign_type'    => 'flow',
                'status'           => 'scheduled',
                'custom_message'   => 'Limited seats — tap below to reserve your spot.',
                'custom_footer'    => 'Reply STOP to opt out',
                'schedule_type'    => 'scheduled',
                'send_date'        => Carbon::now()->addDays(2)->toDateString(),
                'send_time'        => '09:00:00',
                'timezone'         => 'Asia/Kolkata',
                'total_recipients' => 1450,
            ],
        ];

        foreach ($samples as $sample) {
            // Use the campaign_name (post-decrypt) as a dedupe key.
            $exists = WpCampaign::all()->first(fn ($c) => $c->campaign_name === $sample['campaign_name']);
            if ($exists) continue;
            WpCampaign::create($sample);
        }
    }
}
