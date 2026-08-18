<?php

namespace App\Services;

use App\Models\InboxMessage;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "what plan is this workspace on, how much of
 * it have they used this month, and which features are unlocked". Used by
 * the dashboard plan card and the /account profile usage panel so both
 * always agree (no hardcoded plan names or counts anywhere).
 *
 * Everything is resolved live: plan name via Workspace::billingPackage(),
 * limits via effectiveLimit() (respects admin plan_overrides), usage by
 * counting real rows for the current calendar month, scoped to the
 * workspace and its active engine.
 */
class PlanUsage
{
    /**
     * Human-readable labels for the plan feature flags we surface. Keys are
     * the exact Package columns; only these are shown (curated, not every
     * raw column) so the UI stays readable.
     */
    public const FEATURE_LABELS = [
        'broadcast'                 => 'Broadcasts',
        'campaign'                  => 'Campaigns',
        'autoflow'                  => 'Flow automations',
        'schedulemessage'           => 'Scheduled messages',
        'autoreply'                 => 'Auto-replies',
        'access_keyword_replies'    => 'Keyword replies',
        'template'                  => 'Message templates',
        'access_carousel_templates' => 'Carousel templates',
        'access_drip_campaigns'     => 'Drip campaigns',
        'access_ctwa'               => 'Click-to-WhatsApp ads',
        'access_analytics'          => 'Advanced analytics',
        'access_kanban_view'        => 'Kanban team inbox',
        'access_routing_rules'      => 'Auto-assign routing',
        'access_business_hours'     => 'Business hours / SLA',
        'access_sla_policies'       => 'SLA policies',
        'access_appointment_booking'=> 'Appointment booking',
        'access_ai_agents'          => 'AI agents',
        'access_ai_chat_assistant'  => 'AI chat assistant',
        'access_ai_voice_agent'     => 'AI voice agent',
        'access_ai_training'        => 'AI training',
        'access_waba_calling'       => 'WhatsApp calling',
        'access_call_recording'     => 'Call recording',
        'access_wa_storefront'      => 'WhatsApp storefront',
        'access_flows_commerce'     => 'Commerce flows',
        'access_chatbot_widgets'    => 'Website chat widgets',
        'access_outbound_webhooks'  => 'Outbound webhooks',
        'access_translation'        => 'Auto-translation',
        'integration_shopify'       => 'Shopify integration',
        'integration_woocommerce'   => 'WooCommerce integration',
        'integration_hubspot'       => 'HubSpot integration',
        'integration_google_calendar' => 'Google Calendar',
    ];

    /** Numeric limit meters we surface (column => label). */
    public const LIMIT_METERS = [
        'monthly_messages_limit' => 'Messages this month',
        'contacts_limit'         => 'Contacts',
        'device_limit'           => 'Connected numbers',
        'user_seat_limit'        => 'Team seats',
        'flow_limit'             => 'Flows',
    ];

    public static function summary(Workspace $ws): array
    {
        $pkg = $ws->billingPackage();

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd   = Carbon::now()->endOfMonth();
        $usedMessages = self::messagesThisMonth($ws->id, $monthStart, $monthEnd);

        $msgLimit  = (int) ($ws->effectiveLimit('monthly_messages_limit', 0) ?: 0);
        $unlimited = $msgLimit <= 0;
        $remaining = $unlimited ? null : max(0, $msgLimit - $usedMessages);
        $pct       = ($unlimited || $msgLimit === 0) ? 0 : min(100, (int) round($usedMessages / $msgLimit * 100));

        // Feature flags — split into unlocked / locked using effectiveLimit so
        // admin overrides are respected.
        $unlocked = [];
        $locked   = [];
        foreach (self::FEATURE_LABELS as $key => $label) {
            if ((bool) $ws->effectiveLimit($key, false)) {
                $unlocked[$key] = $label;
            } else {
                $locked[$key] = $label;
            }
        }

        // Numeric limit meters (used / limit). null limit = unlimited.
        $meters = [];
        foreach (self::LIMIT_METERS as $key => $label) {
            $limit = $ws->effectiveLimit($key, 0);
            $limit = is_numeric($limit) ? (int) $limit : 0;
            $used  = match ($key) {
                'monthly_messages_limit' => $usedMessages,
                'contacts_limit'         => self::countModel(\App\Models\Contact::class, $ws->id),
                'device_limit'           => self::countModel(\App\Models\Device::class, $ws->id),
                'user_seat_limit'        => \App\Models\User::where('current_workspace_id', $ws->id)->count(),
                'flow_limit'             => self::countModel(\App\Models\Flow::class, $ws->id),
                default                  => 0,
            };
            $meters[$key] = [
                'label'     => $label,
                'used'      => $used,
                'limit'     => $limit,
                'unlimited' => $limit <= 0,
                'pct'       => $limit > 0 ? min(100, (int) round($used / $limit * 100)) : 0,
            ];
        }

        $ownerCredits = (int) (\App\Models\User::where('id', $ws->owner_user_id)->value('wallet_credits') ?? 0);

        return [
            'plan_name'      => $pkg?->pname ?: 'Free',
            'plan_id'        => $pkg?->id,
            'is_free'        => $pkg === null,
            'messages_used'  => $usedMessages,
            'messages_limit' => $msgLimit,
            'messages_unlimited' => $unlimited,
            'messages_remaining' => $remaining,
            'messages_pct'   => $pct,
            'credits'        => $ownerCredits,
            'unlocked'       => $unlocked,
            'locked'         => $locked,
            'unlocked_count' => count($unlocked),
            'feature_total'  => count(self::FEATURE_LABELS),
            'meters'         => $meters,
            'month_label'    => $monthStart->format('F Y'),
            'cycle_reset'    => $monthEnd->copy()->addDay()->startOfDay()->format('M j'),
            'days_left'      => (int) Carbon::now()->startOfDay()->diffInDays($monthEnd->copy()->startOfDay()) + 1,
            // Plan VALIDITY (distinct from the monthly message cycle above): the
            // date the subscription itself expires. For a yearly plan this is a
            // year out even though the message quota still resets each month.
            // null = free / lifetime / no expiry.
            'valid_until'      => $ws->plan_ends_at ? $ws->plan_ends_at->format('M j, Y') : null,
            'valid_until_days' => $ws->plan_ends_at && $ws->plan_ends_at->isFuture()
                ? (int) Carbon::now()->startOfDay()->diffInDays($ws->plan_ends_at->copy()->startOfDay())
                : null,
        ];
    }

    /** Outbound messages (bulk + inbox) for the workspace this calendar month. */
    private static function messagesThisMonth(int $wsId, Carbon $start, Carbon $end): int
    {
        $bulk = Message::query()
            ->where('workspace_id', $wsId)
            ->forCurrentEngine()
            ->where('direction', 'out')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $inbox = InboxMessage::query()
            ->forCurrentEngine()
            ->where('direction', 'out')
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $wsId))
            ->count();

        return $bulk + $inbox;
    }

    /** Defensive workspace-scoped count — tolerates models lacking the column. */
    private static function countModel(string $class, int $wsId): int
    {
        try {
            return $class::query()->where('workspace_id', $wsId)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // -----------------------------------------------------------------
    // Increment-only usage ledger (plan_usage table)
    //
    // The delete-proof counter behind PlanLimitGuard::checkQuota(). Creating a
    // metered resource bumps `count`; deleting it does NOT decrement — so
    // "create 5, delete 5, create 5 again" can no longer bypass a plan cap. The
    // counter is keyed by billing PERIOD, so buying / renewing a plan starts a
    // fresh allowance with no reset job to run. Counting is wired once via the
    // TracksPlanQuota trait (model created → bump); this is the only place that
    // touches the ledger.
    // -----------------------------------------------------------------

    /**
     * Billing-period key for a workspace. Changes when the plan (package)
     * changes OR the billing window (plan_ends_at) advances — the two signals
     * that a customer bought / renewed a plan — so each new plan cycle starts a
     * fresh quota. Free / lifetime plans (no plan_ends_at) key on '0', a single
     * stable period until a dated plan is purchased.
     */
    public static function period(Workspace $workspace): string
    {
        try {
            $pkgId = (int) (optional($workspace->billingPackage())->id ?? 0);
        } catch (\Throwable $e) {
            $pkgId = 0;
        }
        $ends = $workspace->plan_ends_at ? $workspace->plan_ends_at->format('Ymd') : '0';
        return $pkgId . '|' . $ends;
    }

    /**
     * Fail-open guard: if the plan_usage table hasn't been migrated yet in this
     * environment, the ledger simply no-ops (reads 0, skips writes) so a
     * missing migration NEVER 500s a create — enforcement just reverts to the
     * old behaviour until the table lands. Cached per request.
     */
    private static function ledgerReady(): bool
    {
        static $ready = null;
        if ($ready === null) {
            try { $ready = \Illuminate\Support\Facades\Schema::hasTable('plan_usage'); }
            catch (\Throwable $e) { $ready = false; }
        }
        return $ready;
    }

    /** Current-period usage for a metric (0 when nothing recorded yet). */
    public static function usage(Workspace $workspace, string $metric): int
    {
        if (!$workspace->id || !self::ledgerReady()) return 0;
        return (int) DB::table('plan_usage')
            ->where('workspace_id', $workspace->id)
            ->where('metric', $metric)
            ->where('period', self::period($workspace))
            ->value('count');
    }

    /**
     * Increment the current-period counter by $qty (default 1). Atomic:
     * UPDATE-then-INSERT-on-miss, with a race-safe retry so two concurrent
     * creates never lose a count or collide on the unique key.
     */
    public static function bump(int $workspaceId, string $metric, int $qty = 1): void
    {
        if ($workspaceId <= 0 || $qty === 0 || !self::ledgerReady()) return;
        $workspace = Workspace::find($workspaceId);
        if (!$workspace) return;

        $period = self::period($workspace);

        $affected = DB::table('plan_usage')
            ->where('workspace_id', $workspaceId)
            ->where('metric', $metric)
            ->where('period', $period)
            ->update(['count' => DB::raw('count + ' . (int) $qty), 'updated_at' => now()]);

        if ($affected) return;

        try {
            DB::table('plan_usage')->insert([
                'workspace_id' => $workspaceId,
                'metric'       => $metric,
                'period'       => $period,
                'count'        => max(0, (int) $qty),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            // Lost the insert race to a concurrent request — the row exists now,
            // so a plain increment lands the count correctly.
            DB::table('plan_usage')
                ->where('workspace_id', $workspaceId)
                ->where('metric', $metric)
                ->where('period', $period)
                ->update(['count' => DB::raw('count + ' . (int) $qty), 'updated_at' => now()]);
        }
    }

    /**
     * One-time adoption of pre-existing rows. checkQuota passes the CURRENT
     * live count of a metric; this seeds the counter to that count ONLY the
     * first time this workspace+metric is ever metered — so an install that
     * already has (say) 5 campaigns starts capped at 5, not 5-more.
     *
     * It does NOTHING once any period row exists: a genuinely NEW plan period
     * must start at 0 even though old rows still sit in the DB (that is exactly
     * how the cap re-opens after a plan purchase). So it seeds history once and
     * never re-imports live rows again.
     */
    public static function seedIfFirstEver(Workspace $workspace, string $metric, int $liveCount): void
    {
        if (!$workspace->id || !self::ledgerReady()) return;

        $everTracked = DB::table('plan_usage')
            ->where('workspace_id', $workspace->id)
            ->where('metric', $metric)
            ->exists();
        if ($everTracked) return;   // already metering → new periods legitimately start at 0

        try {
            DB::table('plan_usage')->insert([
                'workspace_id' => $workspace->id,
                'metric'       => $metric,
                'period'       => self::period($workspace),
                'count'        => max(0, $liveCount),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            // Concurrent seed — harmless, the row is there now.
        }
    }
}
