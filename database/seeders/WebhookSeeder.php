<?php

namespace Database\Seeders;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Database\Seeder;

class WebhookSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            [
                'name'          => 'Production CRM relay',
                'environment'   => 'Production',
                'webhook_url'   => 'https://api.brand.example/wadesk/events',
                'events'        => ['message_delivered', 'message_read', 'message_failed', 'contact_opt_in', 'contact_updated'],
                'success_count' => 3124,
                'failure_count' => 4,
                'last_status_code' => 200,
                'last_latency_ms'  => 218,
                'last_fired_at'    => now()->subMinutes(2),
                'icon_color'    => 'wa-mint',
                'status'        => true,
            ],
            [
                'name'          => 'Zapier / Slack relay',
                'environment'   => 'Zapier',
                'webhook_url'   => 'https://hooks.zapier.com/hooks/catch/8211/abc',
                'events'        => ['message_received', 'contact_opt_in'],
                'success_count' => 1840,
                'failure_count' => 0,
                'last_status_code' => 200,
                'last_latency_ms'  => 486,
                'last_fired_at'    => now()->subMinutes(26),
                'icon_color'    => 'blue',
                'status'        => true,
            ],
            [
                'name'          => 'Shopify order.paid relay',
                'environment'   => 'Shopify',
                'webhook_url'   => 'https://api.shopify.com/orders/wadesk/sync',
                'events'        => ['message_delivered', 'message_failed'],
                'success_count' => 812,
                'failure_count' => 50,
                'retry_count'   => 50,
                'last_status_code' => 503,
                'last_latency_ms'  => 1200,
                'last_error'    => 'Upstream timeout',
                'is_failing'    => true,
                'last_fired_at' => now()->subHours(3),
                'icon_color'    => 'purple',
                'status'        => true,
            ],
            [
                'name'          => 'CRM sync / contacts',
                'environment'   => 'Internal',
                'webhook_url'   => 'https://crm.brand.example/api/v3/wadesk',
                'events'        => ['contact_opt_in', 'contact_updated'],
                'success_count' => 744,
                'failure_count' => 0,
                'last_status_code' => 200,
                'last_latency_ms'  => 156,
                'last_fired_at'    => now()->subHours(2),
                'icon_color'    => 'green',
                'status'        => true,
            ],
            [
                'name'          => 'Staging endpoint',
                'environment'   => 'Staging',
                'webhook_url'   => 'https://staging.brand.example/wadesk',
                'events'        => ['message_delivered', 'message_read', 'message_received'],
                'success_count' => 0,
                'failure_count' => 0,
                'last_fired_at' => now()->subDays(2),
                'icon_color'    => 'paused',
                'status'        => false,
            ],
        ];

        foreach ($samples as $row) {
            Webhook::updateOrCreate(
                ['webhook_url' => $row['webhook_url']],
                $row
            );
        }

        // Sample recent deliveries so the bottom panel + event mix
        // panel + KPI strip have real data on load.
        $hooks = Webhook::all();
        $events = ['message_delivered', 'message_read', 'message_received', 'message_failed', 'contact_opt_in', 'contact_updated'];
        $palette = [200, 200, 200, 200, 200, 503, 200, 200];
        WebhookDelivery::query()->delete();
        foreach ($hooks as $hook) {
            for ($i = 0; $i < 24; $i++) {
                $statusCode = $palette[$i % count($palette)];
                $isOk = $statusCode >= 200 && $statusCode < 300;
                WebhookDelivery::create([
                    'webhook_id'  => $hook->id,
                    'event_name'  => $events[$i % count($events)],
                    'status_code' => $statusCode,
                    'latency_ms'  => $isOk ? rand(120, 480) : 1200,
                    'is_retry'    => !$isOk,
                    'fired_at'    => now()->subMinutes($i * 7 + 2),
                    'payload'     => json_encode(['demo' => true]),
                    'response_body' => $isOk ? 'OK' : 'Upstream timeout',
                ]);
            }
        }
    }
}
