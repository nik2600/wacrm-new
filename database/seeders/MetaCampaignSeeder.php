<?php

namespace Database\Seeders;

use App\Models\MetaCampaign;
use Illuminate\Database\Seeder;

/**
 * Realistic Meta-Ads campaigns so /meta-ads has data to show
 * before the Graph API sync is wired. Re-running wipes and
 * re-creates so the IDs stay stable for screenshots.
 */
class MetaCampaignSeeder extends Seeder
{
    public function run(): void
    {
        MetaCampaign::query()->delete();

        $specs = [
            [
                'name'              => 'Meta CTWA — Summer sale',
                'optimization_goal' => 'MESSAGES',
                'objective'         => 'OUTCOME_ENGAGEMENT',
                'status'            => 'ACTIVE',
                'daily_budget'      => 25.00,
                'creative_title'    => 'Summer sale — chat with us on WhatsApp',
                'creative_body'     => 'Tap below to chat with our concierge — 25% off ends Sunday.',
                'creative_link_url' => 'https://wa.me/919810411122?text=Summer%20sale',
                'ctwa_enabled'      => true,
                'ctwa_phone'        => '+91 98104 11122',
                'ctwa_message'      => 'Hi Bloomly — tell me about the summer sale!',
                'ctwa_cta'          => 'WHATSAPP_MESSAGE',
                'ad_set_count'      => 2,
                'ad_count'          => 3,
                'targeting'         => ['countries' => ['IN', 'AE'], 'age_min' => 22, 'age_max' => 45],
            ],
            [
                'name'              => "Mother's Day — Link clicks",
                'optimization_goal' => 'LINK_CLICKS',
                'objective'         => 'OUTCOME_TRAFFIC',
                'status'            => 'ACTIVE',
                'daily_budget'      => 18.00,
                'creative_title'    => "Mother's Day gifts — under $50",
                'creative_body'     => 'Hand-curated gift sets, free shipping till May 8.',
                'creative_link_url' => 'https://bloomly.in/mothers-day',
                'ctwa_enabled'      => false,
                'ad_set_count'      => 1,
                'ad_count'          => 2,
                'targeting'         => ['countries' => ['IN'], 'age_min' => 24, 'age_max' => 55, 'gender' => 'all'],
            ],
            [
                'name'              => 'Lead gen — Yoga retreat',
                'optimization_goal' => 'LEAD_GENERATION',
                'objective'         => 'OUTCOME_LEADS',
                'status'            => 'PAUSED',
                'daily_budget'      => 12.00,
                'creative_title'    => 'May yoga retreat — 6 spots left',
                'creative_body'     => '4-day silent retreat in Rishikesh. Reply with your dates.',
                'ctwa_enabled'      => false,
                'ad_set_count'      => 1,
                'ad_count'          => 1,
                'targeting'         => ['countries' => ['IN', 'US'], 'age_min' => 28, 'age_max' => 60],
            ],
            [
                'name'              => 'Brand awareness — Spring',
                'optimization_goal' => 'BRAND_AWARENESS',
                'objective'         => 'OUTCOME_AWARENESS',
                'status'            => 'PAUSED',
                'daily_budget'      => 10.00,
                'creative_title'    => 'Bloomly — fragrance, plants, calm',
                'creative_body'     => 'Discover the seasonal collection.',
                'ctwa_enabled'      => false,
                'ad_set_count'      => 1,
                'ad_count'          => 1,
                'targeting'         => ['countries' => ['IN'], 'age_min' => 18, 'age_max' => 65],
            ],
            [
                'name'              => 'Diwali Drop — Scheduled',
                'optimization_goal' => 'CONVERSIONS',
                'objective'         => 'OUTCOME_SALES',
                'status'            => 'SCHEDULED',
                'daily_budget'      => 30.00,
                'creative_title'    => 'Diwali Drop — early access',
                'creative_body'     => 'VIP-only fragrance drop. Sales open 6 PM IST.',
                'ctwa_enabled'      => true,
                'ctwa_phone'        => '+91 98104 11122',
                'ctwa_message'      => 'I want early access to the Diwali Drop',
                'ctwa_cta'          => 'WHATSAPP_MESSAGE',
                'ad_set_count'      => 1,
                'ad_count'          => 2,
                'targeting'         => ['countries' => ['IN'], 'age_min' => 25, 'age_max' => 55],
            ],
        ];

        foreach ($specs as $spec) {
            $insights = $this->insights($spec['daily_budget'], $spec['status']);
            MetaCampaign::create(array_merge($spec, [
                'user_id'  => null,
                'insights' => $insights,
                'type'     => 'campaign',
            ]));
        }
    }

    private function insights(float $dailyBudget, string $status): array
    {
        // Paused / scheduled / draft campaigns spent some of their
        // budget before being parked; active campaigns burn at
        // ~80% of daily for 30 days. Numbers are deterministic
        // per status so screenshots stay sensible.
        $days        = $status === 'ACTIVE' ? 30 : 14;
        $spend       = round($dailyBudget * 0.78 * $days, 2);
        $clicks      = (int) round($spend / 0.21);
        $impressions = (int) round($clicks / 0.0297);
        $reach       = (int) round($impressions * 0.74);
        $conv        = (int) round($clicks * 0.083);
        $revenue     = round($conv * 17.4, 2);

        return [
            'spend'       => $spend,
            'impressions' => $impressions,
            'clicks'      => $clicks,
            'reach'       => $reach,
            'conversions' => $conv,
            'ctr'         => $impressions ? round($clicks / $impressions * 100, 2) : 0,
            'cpc'         => $clicks      ? round($spend / $clicks, 2)             : 0,
            'revenue'     => $revenue,
        ];
    }
}
