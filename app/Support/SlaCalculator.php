<?php

namespace App\Support;

use App\Models\SlaPolicy;
use App\Models\SupportTicket;
use App\Models\Workspace;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\DB;

/**
 * SLA engine. Single source of truth for:
 *   - Which policy applies to a given ticket
 *   - Whether first_response or resolution has breached
 *   - How many minutes are left / over by
 *
 * Used by:
 *   - The scheduled `support:sla-scan` command (writes sla_breaches rows)
 *   - The /admin/support inbox view (renders "12m left" / "8m over" pills)
 *   - The SLA board (at-risk + breach lists)
 *
 * NOT business-hours-aware in Phase 4 baseline — that's deferred. The
 * stub method `inBusinessHours()` is present for future hookup; for now
 * it always returns true so the math is 24/7.
 */
class SlaCalculator
{
    /** Resolve the SLA policy applied to a ticket, defaulting to the
     *  is_default=true policy if none is explicitly assigned. */
    public static function policyFor(SupportTicket $ticket): ?SlaPolicy
    {
        if ($ticket->sla_policy_id) {
            return SlaPolicy::find($ticket->sla_policy_id);
        }
        return SlaPolicy::where('is_default', true)->first();
    }

    /**
     * Returns { first_response: {deadline, breached, minutes_remaining|minutes_over, severity},
     *           resolution:     {deadline, breached, minutes_remaining|minutes_over, severity} }
     * Either key may be null if there's no policy or the timer doesn't
     * apply (e.g. resolution deadline irrelevant once resolved).
     */
    public static function status(SupportTicket $ticket): array
    {
        $policy = self::policyFor($ticket);
        if (! $policy) return ['first_response' => null, 'resolution' => null];

        // Business-hours-aware deadline computation. When the policy
        // sets respect_business_hours=true and the workspace has a
        // business_hours JSON config, we add minutes only during open
        // hours — so a ticket opened Friday 5pm doesn't burn the SLA
        // window over the weekend.
        $useBiz = (bool) $policy->respect_business_hours;
        $bh     = $useBiz ? self::businessHoursFor($ticket) : null;

        // First response
        $firstResp = null;
        if ($ticket->first_response_at) {
            $deadline = self::addBusinessMinutes($ticket->created_at, $policy->first_response_minutes, $bh);
            $firstResp = [
                'deadline'         => $deadline->toIso8601String(),
                'breached'         => Carbon::parse($ticket->first_response_at)->gt($deadline),
                'minutes_remaining'=> null,
                'minutes_over'     => null,
                'severity'         => 'met',
            ];
            if ($firstResp['breached']) {
                $firstResp['minutes_over'] = Carbon::parse($ticket->first_response_at)->diffInMinutes($deadline);
                $firstResp['severity']     = 'breach';
            }
        } else {
            $deadline = self::addBusinessMinutes($ticket->created_at, $policy->first_response_minutes, $bh);
            $remaining = now()->diffInMinutes($deadline, false);
            $firstResp = [
                'deadline'          => $deadline->toIso8601String(),
                'breached'          => $remaining < 0,
                'minutes_remaining' => $remaining >= 0 ? $remaining : null,
                'minutes_over'      => $remaining < 0 ? abs($remaining) : null,
                'severity'          => self::severityFor($remaining, $policy->first_response_minutes),
            ];
        }

        // Resolution
        $resolution = null;
        if ($ticket->resolved_at) {
            $deadline = self::addBusinessMinutes($ticket->created_at, $policy->resolution_minutes, $bh);
            $resolved = Carbon::parse($ticket->resolved_at);
            $resolution = [
                'deadline' => $deadline->toIso8601String(),
                'breached' => $resolved->gt($deadline),
                'severity' => 'met',
            ];
            if ($resolution['breached']) {
                $resolution['minutes_over'] = $resolved->diffInMinutes($deadline);
                $resolution['severity']     = 'breach';
            }
        } else {
            $deadline = self::addBusinessMinutes($ticket->created_at, $policy->resolution_minutes, $bh);
            $remaining = now()->diffInMinutes($deadline, false);
            $resolution = [
                'deadline'          => $deadline->toIso8601String(),
                'breached'          => $remaining < 0,
                'minutes_remaining' => $remaining >= 0 ? $remaining : null,
                'minutes_over'      => $remaining < 0 ? abs($remaining) : null,
                'severity'          => self::severityFor($remaining, $policy->resolution_minutes),
            ];
        }

        return ['first_response' => $firstResp, 'resolution' => $resolution];
    }

    /**
     * Read the business_hours JSON off the workspace. Falls back to a
     * sensible default (Mon-Fri 09:00-17:00 in workspace TZ) so a
     * workspace without explicit hours still gets reasonable SLA math.
     *
     * Expected schema on workspaces.business_hours (any shape works,
     * we normalize):
     *   { tz: "Asia/Kolkata",
     *     days: { mon: ["09:00","17:00"], ..., sat: null, sun: null } }
     */
    private static function businessHoursFor(SupportTicket $ticket): array
    {
        $ws = $ticket->workspace_id ? Workspace::find($ticket->workspace_id) : null;
        $tz = $ws?->timezone ?: (config('app.timezone') ?: 'UTC');
        $raw = $ws?->business_hours ?? [];
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }
        $tz = (string) ($raw['tz'] ?? $tz);
        $days = (array) ($raw['days'] ?? []);
        // Default schedule if nothing configured: weekdays 09–17 in workspace TZ.
        $default = [
            'mon' => ['09:00', '17:00'], 'tue' => ['09:00', '17:00'],
            'wed' => ['09:00', '17:00'], 'thu' => ['09:00', '17:00'],
            'fri' => ['09:00', '17:00'], 'sat' => null, 'sun' => null,
        ];
        $hours = [];
        foreach ($default as $k => $def) {
            $hours[$k] = array_key_exists($k, $days) ? $days[$k] : $def;
        }
        return ['tz' => $tz, 'days' => $hours];
    }

    /**
     * Add N business-minutes to a starting timestamp. When $bh is null
     * the math is plain wall-clock (24/7). When provided, we walk the
     * timeline minute-by-minute (well, hour-by-hour in chunks) skipping
     * any time outside the day's open window, until $minutes worth of
     * "inside" time has accumulated.
     *
     * Bounded to 30 days of look-ahead so a misconfigured "0-minute" day
     * doesn't infinite-loop us.
     */
    private static function addBusinessMinutes($start, int $minutes, ?array $bh): Carbon
    {
        $cursor = $start instanceof Carbon ? $start->copy() : Carbon::parse($start);
        if (! $bh || $minutes <= 0) return $cursor->addMinutes($minutes);

        $cursor = $cursor->setTimezone($bh['tz']);
        $remaining = $minutes;
        $safety = 60 * 24 * 30; // 30 days × 24h × 60min of forward seek
        while ($remaining > 0 && $safety-- > 0) {
            $dayKey = strtolower($cursor->format('D')); // mon..sun
            $window = $bh['days'][$dayKey] ?? null;
            if (! is_array($window) || count($window) < 2) {
                // Closed day → skip to start of next day.
                $cursor = $cursor->copy()->addDay()->startOfDay();
                continue;
            }
            [$openStr, $closeStr] = $window;
            $open  = $cursor->copy()->setTimeFromTimeString($openStr);
            $close = $cursor->copy()->setTimeFromTimeString($closeStr);
            if ($cursor->lt($open)) $cursor = $open->copy();
            if ($cursor->gte($close)) {
                $cursor = $cursor->copy()->addDay()->startOfDay();
                continue;
            }
            $availableThisDay = $cursor->diffInMinutes($close);
            if ($availableThisDay <= 0) {
                $cursor = $cursor->copy()->addDay()->startOfDay();
                continue;
            }
            if ($remaining <= $availableThisDay) {
                $cursor = $cursor->copy()->addMinutes($remaining);
                $remaining = 0;
            } else {
                $remaining -= $availableThisDay;
                $cursor = $close->copy();
            }
        }
        return $cursor->setTimezone(config('app.timezone') ?: 'UTC');
    }

    /**
     * Scan all unresolved tickets, compute SLA state, and insert
     * sla_breaches rows for any that crossed their deadline since the
     * last scan. Idempotent — if a breach row for this ticket+type
     * already exists, we don't insert another (one breach per type per
     * ticket lifetime).
     *
     * Returns count of breaches inserted.
     */
    public static function scanAndPersist(): int
    {
        $inserted = 0;
        $cursor = SupportTicket::whereNotIn('status', ['resolved', 'closed'])
            ->select(['id', 'created_at', 'first_response_at', 'sla_policy_id']);
        $cursor->chunk(200, function ($chunk) use (&$inserted) {
            foreach ($chunk as $ticket) {
                $st = self::status($ticket);
                foreach (['first_response', 'resolution'] as $type) {
                    $info = $st[$type] ?? null;
                    if (! $info || ! $info['breached']) continue;
                    $exists = DB::table('sla_breaches')
                        ->where('ticket_id', $ticket->id)
                        ->where('breach_type', $type)
                        ->exists();
                    if ($exists) continue;
                    DB::table('sla_breaches')->insert([
                        'ticket_id'       => $ticket->id,
                        'sla_policy_id'   => $ticket->sla_policy_id,
                        'breach_type'     => $type,
                        'breached_at'     => now(),
                        'severity'        => $info['severity'] === 'breach' ? 'breach' : 'warn',
                        'over_by_minutes' => $info['minutes_over'] ?? 0,
                    ]);
                    $inserted++;
                }
            }
        });
        return $inserted;
    }

    /**
     * Severity bucket from a remaining-minutes value.
     *   > 25% of total window left  → ok
     *   > 0  but in danger zone     → warn
     *   <= 0                        → breach
     */
    private static function severityFor(int $remaining, int $totalMinutes): string
    {
        if ($remaining < 0) return 'breach';
        if ($remaining < max(5, intdiv($totalMinutes, 4))) return 'warn';
        return 'ok';
    }
}
