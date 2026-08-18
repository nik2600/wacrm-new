<?php

namespace Database\Seeders;

use App\Models\Device;
use Illuminate\Database\Seeder;

/**
 * Sample devices so /devices renders meaningful content before
 * the operator pairs anything. Idempotent on phone_number.
 */
class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        Device::query()->delete();

        $rows = [
            ['Bloomly Support',        '+91',  '9810411122', 'IN', 'connected',    true,  3210, 12, 8],
            ['Bloomly Sales',          '+91',  '9810433440', 'IN', 'connected',    true,  2840, 19, 4],
            ['Bloomly Ops',            '+91',  '7410088812', 'IN', 'disconnected', false, 0,    0,  null],
            ['Bloomly US',             '+1',   '4155551234', 'US', 'needs_pair',   false, 411,  3,  120],
            ['Bloomly UAE concierge',  '+971', '581234567',  'AE', 'connected',    true,  582,  1,  2],
        ];

        foreach ($rows as [$name, $cc, $local, $region, $status, $active, $sent24, $fail24, $lastSeenMins]) {
            Device::create([
                'user_id'      => null,
                'device_name'  => $name,
                'country_code' => $cc,
                'phone_number' => $cc . $local,
                'region'       => $region,
                'status'       => $status,
                'active'       => $active,
                'sent_24h'     => $sent24,
                'failed_24h'   => $fail24,
                'last_seen_at' => $lastSeenMins === null ? null : now()->subMinutes($lastSeenMins),
            ]);
        }
    }
}
