<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Post-mortem tracer for requests that die on a PHP fatal — most usefully
 * "Allowed memory size of N bytes exhausted".
 *
 * Why this exists: a try/catch cannot see an OOM. The process dies mid
 * statement, so the frame Laravel reports is wherever the *final* allocation
 * was requested — almost always Connection.php, because running a query is
 * what asks for the next chunk of memory. That frame tells you where the last
 * byte went, not who ate the first gigabyte, which makes it worse than
 * useless: it points at innocent code.
 *
 * register_shutdown_function still fires on a fatal, so the evidence is
 * gathered there instead: how many queries ran, the last few statements, and
 * memory at each checkpoint the action passed. Raising memory_limit as the
 * first act inside the handler buys the headroom to write the log line.
 *
 * Reading the output:
 *   queries_run in the thousands  -> unbounded loop / N+1 runaway
 *   queries_run small, peak high  -> one query returned a huge set (join?)
 *   last_mark = 'view-built'      -> the controller finished; blade or the
 *                                    layout is what died
 *
 * Usage: FatalTrace::arm('some-tag') at the top of a suspect action, then
 * FatalTrace::mark('label') between stages. No-op unless diagnostics.fatal_trace
 * is on, so it is safe to leave in place.
 */
class FatalTrace
{
    private static bool $armed = false;
    private static string $tag = '';
    private static int $queries = 0;
    private static array $recent = [];
    private static array $marks = [];
    private static float $startedAt = 0.0;

    public static function arm(string $tag): void
    {
        if (self::$armed || !config('diagnostics.fatal_trace', false)) {
            return;
        }

        self::$armed     = true;
        self::$tag       = $tag;
        self::$startedAt = microtime(true);
        self::mark('armed');

        // Ring buffer, not a full log: keeping every statement would itself
        // grow without bound and muddy the very measurement we're taking.
        DB::listen(function ($q) {
            self::$queries++;
            self::$recent[] = mb_substr((string) $q->sql, 0, 300);
            if (count(self::$recent) > 5) {
                array_shift(self::$recent);
            }
        });

        register_shutdown_function([self::class, 'report']);
    }

    public static function mark(string $label): void
    {
        if (!self::$armed) {
            return;
        }

        self::$marks[] = [
            'at'      => $label,
            'mem_mb'  => self::mb(memory_get_usage(true)),
            'queries' => self::$queries,
        ];
    }

    public static function report(): void
    {
        $err = error_get_last();
        $isFatal = $err && in_array(
            $err['type'],
            [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR],
            true
        );

        if (!$isFatal) {
            return;
        }

        // The handler runs with the same exhausted limit that killed us;
        // without this the log write dies too and we learn nothing.
        @ini_set('memory_limit', '-1');

        $last = self::$marks ? self::$marks[count(self::$marks) - 1] : null;

        Log::error('FatalTrace: request died on a PHP fatal', [
            'tag'         => self::$tag,
            'error'       => $err['message'] ?? '',
            'died_at'     => ($err['file'] ?? '?') . ':' . ($err['line'] ?? '?'),
            'queries_run' => self::$queries,
            'last_mark'   => $last['at'] ?? null,
            'marks'       => self::$marks,
            'recent_sql'  => self::$recent,
            'peak_mb'     => self::mb(memory_get_peak_usage(true)),
            'elapsed_s'   => round(microtime(true) - self::$startedAt, 2),
            'url'         => request()?->fullUrl(),
        ]);
    }

    private static function mb(int $bytes): float
    {
        return round($bytes / 1048576, 1);
    }
}
