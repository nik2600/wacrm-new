<?php

namespace App\Services;

use App\Models\Device;
use App\Models\WaProviderConfig;
use Illuminate\Support\Carbon;

/**
 * WhatsApp Warmer (Unofficial-API / "devices" only).
 *
 * Per-number warm-up the USER configures: a ramping daily send budget, a
 * randomised gap between sends, active-hours/rest windows, optional spintax
 * variety, and a per-number health score. It is RISK-REDUCTION, not ban-proof
 * — high-volume senders should still move to official WABA.
 *
 * Enforcement is opt-in per number (config.enabled) and applies to BULK sends
 * (campaigns / broadcasts / scheduled). It deliberately does NOT block 1:1
 * team-inbox replies — a human answering a customer must never be rate-capped.
 */
class WarmerService
{
    /** The shipped defaults — a conservative 4-week-ish ramp. */
    public const DEFAULTS = [
        'enabled'      => false,
        'daily_base'   => 20,    // day-one send budget
        'step_pct'     => 20,    // grow the budget by this % each step
        'step_days'    => 3,     // ...every N days
        'max_daily'    => 500,   // hard ceiling the ramp never exceeds
        'gap_min'      => 15,    // min seconds between sends
        'gap_max'      => 45,    // max seconds between sends
        'active_start' => 9,     // local hour sends may begin (0-23)
        'active_end'   => 21,    // local hour sends must stop (0-23)
        'spintax'      => false, // expand {a|b|c} for per-message variety
    ];

    /** Merge a device's stored config over the defaults. */
    public function config(Device|WaProviderConfig $device): array
    {
        $raw = $device->warmer_config;
        if (is_string($raw)) $raw = json_decode($raw, true);
        $cfg = array_merge(self::DEFAULTS, is_array($raw) ? $raw : []);

        // Clamp to sane ranges so a bad save can't break sends.
        $cfg['daily_base']   = max(1, (int) $cfg['daily_base']);
        $cfg['step_pct']     = max(0, min(200, (int) $cfg['step_pct']));
        $cfg['step_days']    = max(1, (int) $cfg['step_days']);
        $cfg['max_daily']    = max($cfg['daily_base'], (int) $cfg['max_daily']);
        $cfg['gap_min']      = max(0, (int) $cfg['gap_min']);
        $cfg['gap_max']      = max($cfg['gap_min'], (int) $cfg['gap_max']);
        $cfg['active_start'] = max(0, min(23, (int) $cfg['active_start']));
        $cfg['active_end']   = max(0, min(24, (int) $cfg['active_end']));
        $cfg['enabled']      = (bool) $cfg['enabled'];
        $cfg['spintax']      = (bool) $cfg['spintax'];
        return $cfg;
    }

    public function enabled(Device|WaProviderConfig $device): bool
    {
        return $this->config($device)['enabled'];
    }

    /** The local timezone for this number's active-hours window. */
    private function tz(Device|WaProviderConfig $device): string
    {
        try {
            $userTz = optional($device->user)->timezone;
            if (is_string($userTz) && $userTz !== '') return $userTz;
        } catch (\Throwable $e) {}
        return (string) config('app.timezone', 'UTC');
    }

    /**
     * Today's ramped budget. Grows by step_pct every step_days since the
     * warm-up started, capped at max_daily.
     */
    public function dailyBudget(Device|WaProviderConfig $device, ?array $cfg = null): int
    {
        return $this->dailyBudgetFor($device, null, $cfg);
    }

    /**
     * The ramped budget for a SPECIFIC calendar day (defaults to today). Grows
     * by step_pct every step_days since the warm-up started, capped at max_daily.
     * Lets the warmer reason about future-scheduled sends — a send next week gets
     * next week's (larger) budget.
     */
    public function dailyBudgetFor(Device|WaProviderConfig $device, ?string $date = null, ?array $cfg = null): int
    {
        $cfg = $cfg ?: $this->config($device);
        $startRaw = $cfg['started_at'] ?? null;
        try {
            $start = $startRaw ? Carbon::parse($startRaw) : ($device->created_at ?? now());
        } catch (\Throwable $e) {
            $start = now();
        }
        try {
            $target = $date ? Carbon::parse($date) : now($this->tz($device));
        } catch (\Throwable $e) {
            $target = now();
        }
        $daysIn = max(0, (int) $start->copy()->startOfDay()->diffInDays($target->copy()->startOfDay()));
        $steps  = intdiv($daysIn, max(1, (int) $cfg['step_days']));
        $budget = (float) $cfg['daily_base'] * pow(1 + ($cfg['step_pct'] / 100), $steps);
        return (int) min($cfg['max_daily'], max($cfg['daily_base'], floor($budget)));
    }

    /** Is the current local time inside the active-hours window? */
    public function withinActiveHours(Device|WaProviderConfig $device, ?array $cfg = null): bool
    {
        $cfg = $cfg ?: $this->config($device);
        $start = (int) $cfg['active_start'];
        $end   = (int) $cfg['active_end'];
        if ($start === $end) return true; // 24h window
        try {
            $hour = (int) now($this->tz($device))->format('G');
        } catch (\Throwable $e) {
            $hour = (int) now()->format('G');
        }
        // Overnight window (e.g. 21 → 6) wraps past midnight.
        return $start < $end
            ? ($hour >= $start && $hour < $end)
            : ($hour >= $start || $hour < $end);
    }

    /** A randomised human-like delay (seconds) for the next send. */
    public function gapSeconds(Device|WaProviderConfig $device, ?array $cfg = null): int
    {
        $cfg = $cfg ?: $this->config($device);
        $min = (int) $cfg['gap_min'];
        $max = (int) $cfg['gap_max'];
        return $max > $min ? random_int($min, $max) : $min;
    }

    /** How many sends remain in TODAY's ramped budget. */
    public function remainingToday(Device|WaProviderConfig $device, ?array $cfg = null): int
    {
        return $this->remainingFor($device, null, $cfg);
    }

    /** Calendar date used for the per-date ledger (number's local tz). */
    private function ledgerDate(Device|WaProviderConfig $device, ?string $date = null): string
    {
        if ($date) return $date;
        try { return now($this->tz($device))->toDateString(); }
        catch (\Throwable $e) { return now()->toDateString(); }
    }

    /**
     * Ledger engine key. A Device id and a WaProviderConfig id can both be 5,
     * so every counter is scoped by engine to keep them independent.
     */
    private function engineOf(Device|WaProviderConfig $device): string
    {
        return $device instanceof WaProviderConfig ? (string) $device->provider : 'baileys';
    }

    /** Sends recorded/reserved for this number on a given day (warmer_daily_sends). */
    public function sentOn(Device|WaProviderConfig $device, ?string $date = null): int
    {
        try {
            return (int) \Illuminate\Support\Facades\DB::table('warmer_daily_sends')
                ->where('engine', $this->engineOf($device))
                ->where('device_id', $device->id)
                ->where('day', $this->ledgerDate($device, $date))
                ->value('count');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Budget left on a given day (defaults to today). */
    public function remainingFor(Device|WaProviderConfig $device, ?string $date = null, ?array $cfg = null): int
    {
        $cfg  = $cfg ?: $this->config($device);
        $date = $this->ledgerDate($device, $date);
        return max(0, $this->dailyBudgetFor($device, $date, $cfg) - $this->sentOn($device, $date));
    }

    /**
     * Gate ONE bulk send. Returns ['ok'=>bool, 'reason'=>?string, 'gap'=>int].
     * When the warmer is off for this number it always allows (ok=true).
     */
    public function canSend(Device|WaProviderConfig $device): array
    {
        $cfg = $this->config($device);
        if (!$cfg['enabled']) return ['ok' => true, 'reason' => null, 'gap' => 0];

        if (!$this->withinActiveHours($device, $cfg)) {
            return ['ok' => false, 'reason' => 'outside_active_hours', 'gap' => 0];
        }
        if ($this->remainingToday($device, $cfg) <= 0) {
            return ['ok' => false, 'reason' => 'daily_budget_reached', 'gap' => 0];
        }
        return ['ok' => true, 'reason' => null, 'gap' => $this->gapSeconds($device, $cfg)];
    }

    /** Count one send against TODAY's budget. */
    public function recordSend(Device|WaProviderConfig $device): void
    {
        $this->recordSendsFor($device, 1, null);
    }

    /** Count N sends at once against TODAY's budget (bulk paths). */
    public function recordSends(Device|WaProviderConfig $device, int $n): void
    {
        $this->recordSendsFor($device, $n, null);
    }

    /**
     * Count/RESERVE N sends against a specific day's budget (defaults to today).
     * Future-scheduled sends reserve against the date they will actually go out.
     * Atomic upsert on the (device_id, day) unique row.
     */
    public function recordSendsFor(Device|WaProviderConfig $device, int $n, ?string $date = null): void
    {
        if ($n <= 0) return;
        $day    = $this->ledgerDate($device, $date);
        $engine = $this->engineOf($device);
        try {
            $updated = \Illuminate\Support\Facades\DB::table('warmer_daily_sends')
                ->where('engine', $engine)->where('device_id', $device->id)->where('day', $day)
                ->increment('count', $n, ['updated_at' => now()]);
            if (!$updated) {
                \Illuminate\Support\Facades\DB::table('warmer_daily_sends')->insert([
                    'engine' => $engine, 'device_id' => $device->id, 'day' => $day, 'count' => $n,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Lost the insert race on the unique key → the row exists now; retry the increment.
            try {
                \Illuminate\Support\Facades\DB::table('warmer_daily_sends')
                    ->where('engine', $engine)->where('device_id', $device->id)->where('day', $day)
                    ->increment('count', $n, ['updated_at' => now()]);
            } catch (\Throwable $e2) { /* never block a send on counter write */ }
        }
    }

    /**
     * Batch pre-flight gate for bulk sends. Returns how many of $requested may
     * go out NOW given the number's active hours + remaining daily budget.
     *
     * @return array{ok: bool, allowed: int, reason: ?string, gap: int}
     */
    public function gateBatch(Device|WaProviderConfig $device, int $requested): array
    {
        $cfg = $this->config($device);
        if (!$cfg['enabled']) return ['ok' => true, 'allowed' => $requested, 'reason' => null, 'gap' => 0];

        if (!$this->withinActiveHours($device, $cfg)) {
            return ['ok' => false, 'allowed' => 0, 'reason' => 'outside_active_hours', 'gap' => 0];
        }
        $remaining = $this->remainingToday($device, $cfg);
        if ($remaining <= 0) {
            return ['ok' => false, 'allowed' => 0, 'reason' => 'daily_budget_reached', 'gap' => 0];
        }
        return [
            'ok'      => true,
            'allowed' => min($requested, $remaining),
            'reason'  => $remaining < $requested ? 'budget_capped' : null,
            'gap'     => $this->gapSeconds($device, $cfg),
        ];
    }

    /**
     * Reservation gate for a FUTURE-scheduled batch on a given send date.
     * Active hours don't apply (the send fires later) — we only check whether the
     * batch fits that day's ramped budget after existing reservations. Caller
     * reserves with recordSendsFor($device, $n, $date) on success.
     *
     * @return array{ok: bool, reason: ?string, budget: int, remaining: int}
     */
    public function gateBatchFor(Device|WaProviderConfig $device, int $requested, string $date): array
    {
        $cfg = $this->config($device);
        if (!$cfg['enabled']) return ['ok' => true, 'reason' => null, 'budget' => 0, 'remaining' => 0];
        $budget    = $this->dailyBudgetFor($device, $date, $cfg);
        $remaining = $this->remainingFor($device, $date, $cfg);
        if ($remaining <= 0) {
            return ['ok' => false, 'reason' => 'daily_budget_reached', 'budget' => $budget, 'remaining' => 0];
        }
        if ($requested > $remaining) {
            return ['ok' => false, 'reason' => 'budget_capped', 'budget' => $budget, 'remaining' => $remaining];
        }
        return ['ok' => true, 'reason' => null, 'budget' => $budget, 'remaining' => $remaining];
    }

    /**
     * 0-100 health score from connection + age + failure ratio + volume.
     * Higher = safer to ramp. Purely advisory.
     */
    public function healthScore(Device|WaProviderConfig $device): int
    {
        $score = 50;
        // Connection — a live, recently-seen number is healthier.
        $status = strtolower((string) ($device->status ?? ''));
        if (in_array($status, ['connected', 'open', 'online', 'active'], true)) $score += 20;
        elseif (in_array($status, ['disconnected', 'logged_out', 'banned'], true)) $score -= 25;

        // Age — older numbers look more organic.
        try {
            $ageDays = (int) ($device->created_at?->diffInDays(now()) ?? 0);
            $score += min(20, intdiv($ageDays, 3));
        } catch (\Throwable $e) {}

        // Failure ratio over the last 24h.
        $sent = (int) ($device->sent_24h ?? 0);
        $fail = (int) ($device->failed_24h ?? 0);
        if ($sent + $fail > 0) {
            $failRate = $fail / ($sent + $fail);
            $score -= (int) round($failRate * 40);
        }

        // Over-volume penalty — sending far above the ramped budget is risky.
        $budget = $this->dailyBudget($device);
        if ($budget > 0 && $sent > $budget * 2) $score -= 15;

        return max(0, min(100, $score));
    }

    /**
     * Expand spintax: {a|b|c} → one random choice. Nested braces supported.
     */
    public function spin(string $text): string
    {
        $guard = 0;
        while (preg_match('/\{([^{}]*)\}/', $text) && $guard++ < 50) {
            $text = preg_replace_callback('/\{([^{}]*)\}/', function ($m) {
                $parts = explode('|', $m[1]);
                return $parts[random_int(0, count($parts) - 1)];
            }, $text, 1);
        }
        return $text;
    }

    /** Spin only when this number opted into spintax variety. */
    public function applySpin(Device|WaProviderConfig $device, string $text): string
    {
        return $this->config($device)['spintax'] ? $this->spin($text) : $text;
    }
}
