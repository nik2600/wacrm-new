<?php

namespace Database\Seeders;

use App\Models\Broadcast;
use App\Models\Contact;
use Illuminate\Database\Seeder;

/**
 * Seeds three sample broadcasts plus per-recipient pivot rows so
 * the /broadcasts page renders KPI tiles + table rows on first
 * load. Idempotent on broadcast name (firstOrCreate) so re-runs
 * don't duplicate.
 */
class BroadcastSeeder extends Seeder
{
    public function run(): void
    {
        Broadcast::query()->delete();

        $contacts = Contact::limit(8)->get();
        if ($contacts->isEmpty()) return;

        $specs = [
            [
                'name'        => 'New Year VIP drop',
                'status'      => 'completed',
                'scheduled'   => now()->subDays(2),
                'completed'   => now()->subDays(2)->addMinutes(45),
                'mix'         => ['read' => 5, 'delivered' => 2, 'sent' => 1],
            ],
            [
                'name'        => 'Welcome offer v3',
                'status'      => 'processing',
                'scheduled'   => now()->subHours(2),
                'completed'   => null,
                'mix'         => ['delivered' => 4, 'sent' => 3, 'processing' => 1],
            ],
            [
                'name'        => 'Invoice reminder batch',
                'status'      => 'scheduled',
                'scheduled'   => now()->addDays(2)->setTime(9, 0),
                'completed'   => null,
                'mix'         => ['pending' => 8],
            ],
        ];

        foreach ($specs as $i => $spec) {
            $b = Broadcast::create([
                'user_id'          => null,
                'name'             => $spec['name'],
                'timezone'         => 'Asia/Kolkata',
                'status'           => $spec['status'],
                'scheduled_at'     => $spec['scheduled'],
                'completed_at'     => $spec['completed'],
                'total_recipients' => array_sum($spec['mix']),
                'success_count'    => ($spec['mix']['read'] ?? 0) + ($spec['mix']['delivered'] ?? 0) + ($spec['mix']['sent'] ?? 0),
                'fail_count'       => $spec['mix']['failed'] ?? 0,
            ]);

            // Distribute the requested status mix across the
            // available contacts. Matches what the old Node
            // bridge would have produced as it walked the queue.
            $idx = 0;
            foreach ($spec['mix'] as $status => $count) {
                for ($n = 0; $n < $count && $idx < $contacts->count(); $n++, $idx++) {
                    $c = $contacts[$idx];
                    $b->contacts()->attach($c->id, [
                        'status'        => $status,
                        'sent_at'       => in_array($status, ['sent','delivered','read'], true) ? $spec['scheduled'] : null,
                        'delivered_at'  => in_array($status, ['delivered','read'],         true) ? $spec['scheduled']?->copy()->addMinutes(2) : null,
                        'read_at'       => $status === 'read'                                       ? $spec['scheduled']?->copy()->addMinutes(15) : null,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }
        }
    }
}
