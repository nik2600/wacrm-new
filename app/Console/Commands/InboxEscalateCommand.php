<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Services\Inbox\RoutingEngine;
use Illuminate\Console\Command;

/**
 * Walks conversations with an escalation_due_at <= now() and runs the
 * stashed escalation_action through the RoutingEngine. Used by routing
 * rules that set up "if unanswered > X minutes, reassign" patterns.
 *
 * Scheduled in app/Console/Kernel.php to fire every minute. Idempotent —
 * applyEscalation() clears the slot after firing.
 */
class InboxEscalateCommand extends Command
{
    protected $signature = 'inbox:escalate {--limit=200}';
    protected $description = 'Fire pending time-based routing escalations.';

    public function handle(RoutingEngine $engine): int
    {
        $now = now();
        $convs = Conversation::query()
            ->whereNotNull('escalation_due_at')
            ->where('escalation_due_at', '<=', $now)
            // Don't escalate already-resolved or already-replied threads —
            // those are handled (the operator beat the clock).
            ->whereIn('inbox_status', ['open', 'pending'])
            ->whereNull('first_response_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $this->info("Processing {$convs->count()} escalations…");
        foreach ($convs as $conv) {
            try {
                $engine->applyEscalation($conv);
                $this->line("  ✓ conv {$conv->id} escalated");
            } catch (\Throwable $e) {
                $this->error("  ✗ conv {$conv->id}: " . $e->getMessage());
                // Clear the slot so we don't loop forever on a bad action.
                $conv->forceFill(['escalation_due_at' => null, 'escalation_action' => null])->save();
            }
        }
        return self::SUCCESS;
    }
}
