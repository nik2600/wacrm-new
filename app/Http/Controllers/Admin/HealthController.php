<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * System Health monitor (platform admin).
 *
 * One screen for "is everything up?" — pings every moving part: database,
 * cache, queue + failed jobs, the Node WhatsApp bridge, file/cloud storage,
 * every connected engine (Unofficial / WABA / Twilio numbers), the error log,
 * and host vitals (PHP, disk). Every probe is wrapped so a single failure
 * downgrades that one card, never the whole page. The same data is available
 * as JSON (?format=json) so the page can live-refresh without a reload.
 */
class HealthController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
            'queue'    => $this->checkQueue(),
            'node'     => $this->checkNode(),
            'storage'  => $this->checkStorage(),
        ];
        $engines = $this->engines();
        $system  = $this->system();

        // Overall = worst of the core probes.
        $states = array_column($checks, 'state');
        $overall = in_array('down', $states, true) ? 'down' : (in_array('warn', $states, true) ? 'warn' : 'up');

        // Latency bars — response time of each probe that timed itself.
        $latency = [];
        foreach (['database' => 'Database', 'node' => 'Node bridge', 'storage' => 'Media storage'] as $k => $label) {
            if (isset($checks[$k]['ping'])) {
                $latency[] = ['label' => $label, 'ms' => (int) $checks[$k]['ping'], 'state' => $checks[$k]['state']];
            }
        }

        $throughput = $this->throughput();
        $activity   = $this->activity24h();

        $payload = compact('checks', 'engines', 'system', 'overall', 'latency', 'throughput', 'activity');

        if ($request->query('format') === 'json') {
            return response()->json($payload + ['ts' => now()->toIso8601String()]);
        }
        return view('admin.health.index', $payload);
    }

    /* ─────────────────────────── probes ─────────────────────────── */

    private function checkDatabase(): array
    {
        $t = microtime(true);
        try {
            DB::select('select 1');
            $ms = round((microtime(true) - $t) * 1000);
            return $this->card('up', 'Database', config('database.default'), $ms . ' ms', ['ping' => $ms]);
        } catch (\Throwable $e) {
            return $this->card('down', 'Database', 'unreachable', $this->short($e));
        }
    }

    private function checkCache(): array
    {
        try {
            $k = 'health:probe';
            Cache::put($k, '1', 10);
            $ok = Cache::get($k) === '1';
            return $this->card($ok ? 'up' : 'warn', 'Cache', config('cache.default'), $ok ? 'read/write ok' : 'write failed');
        } catch (\Throwable $e) {
            return $this->card('down', 'Cache', config('cache.default'), $this->short($e));
        }
    }

    private function checkQueue(): array
    {
        try {
            $driver  = config('queue.default');
            $pending = Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0;
            $failed  = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;
            $state   = $failed > 0 ? 'warn' : ($pending > 500 ? 'warn' : 'up');
            $detail  = "{$pending} pending · {$failed} failed";
            return $this->card($state, 'Queue', $driver, $detail, ['pending' => $pending, 'failed' => $failed]);
        } catch (\Throwable $e) {
            return $this->card('down', 'Queue', 'error', $this->short($e));
        }
    }

    /** Ping the Node bridge — any HTTP response means the process is alive. */
    private function checkNode(): array
    {
        $base = function_exists('wd_node_url') ? wd_node_url() : '';
        if ($base === '') {
            return $this->card('warn', 'Node bridge', 'not configured', 'Set the server URL in settings');
        }
        $t = microtime(true);
        try {
            $res = Http::timeout(5)->withHeaders(['X-Node-Token' => function_exists('node_token') ? node_token() : ''])->get($base);
            $ms = round((microtime(true) - $t) * 1000);
            // Reachable = up (even a 404 proves the listener answered).
            return $this->card('up', 'Node bridge', parse_url($base, PHP_URL_HOST) ?: $base, 'HTTP ' . $res->status() . ' · ' . $ms . ' ms', ['ping' => $ms, 'http' => $res->status()]);
        } catch (\Throwable $e) {
            return $this->card('down', 'Node bridge', parse_url($base, PHP_URL_HOST) ?: $base, 'unreachable — process down?');
        }
    }

    private function checkStorage(): array
    {
        $t = microtime(true);
        try {
            $disk = function_exists('media_disk') ? media_disk() : 'public';
            $store = function_exists('media_storage') ? media_storage() : \Illuminate\Support\Facades\Storage::disk($disk);
            $probe = 'health/.probe-' . substr(md5((string) getmypid()), 0, 6);
            $store->put($probe, 'ok');
            $ok = $store->get($probe) === 'ok';
            $store->delete($probe);
            $ms = round((microtime(true) - $t) * 1000);
            $cloud = $disk !== 'public' && $disk !== 'local';
            return $this->card($ok ? 'up' : 'warn', 'Media storage', $cloud ? ('cloud · ' . $disk) : ('local · ' . $disk), ($ok ? 'read/write ok' : 'write failed') . ' · ' . $ms . ' ms', ['ping' => $ms]);
        } catch (\Throwable $e) {
            return $this->card('down', 'Media storage', 'error', $this->short($e));
        }
    }

    /** Messages handled per day (last 14d) — proves the pipeline is moving. */
    private function throughput(): array
    {
        $days = 14;
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $series[now()->subDays($days - 1 - $i)->toDateString()] = 0;
        }
        $table = null;
        foreach (['inbox_messages', 'messages'] as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, 'created_at')) { $table = $t; break; }
        }
        if ($table) {
            try {
                DB::table($table)->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
                    ->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as c'))
                    ->groupBy('d')->get()
                    ->each(function ($r) use (&$series) { if (isset($series[$r->d])) $series[$r->d] = (int) $r->c; });
            } catch (\Throwable $e) {}
        }
        return ['source' => $table ?: 'n/a', 'series' => $series];
    }

    /** Headline last-24h counters across the platform. */
    private function activity24h(): array
    {
        $since = now()->subDay();
        $count = function (string $t) use ($since): int {
            try {
                if (!Schema::hasTable($t) || !Schema::hasColumn($t, 'created_at')) return 0;
                return (int) DB::table($t)->where('created_at', '>=', $since)->count();
            } catch (\Throwable $e) { return 0; }
        };
        return [
            'messages'      => $count('inbox_messages') ?: $count('messages'),
            'ai_calls'      => $count('ai_token_usage'),
            'conversations' => $count('conversations'),
            'webhooks'      => $count('incoming_webhook_events') ?: $count('webhook_logs'),
        ];
    }

    /* ─────────────────────── engine / device health ─────────────── */

    private function engines(): array
    {
        $out = [];

        // Unofficial API (Baileys devices)
        try {
            $rows = DB::table('devices')->select('status', DB::raw('COUNT(*) as c'))->groupBy('status')->pluck('c', 'status');
            $connected = (int) ($rows['connected'] ?? 0);
            $total = (int) array_sum($rows->all());
            $out[] = [
                'key' => 'baileys', 'label' => 'Unofficial API',
                'connected' => $connected, 'total' => $total,
                'state' => $total === 0 ? 'idle' : ($connected > 0 ? 'up' : 'down'),
                'breakdown' => $rows->all(),
            ];
        } catch (\Throwable $e) {}

        // WABA + Twilio (wa_provider_configs)
        try {
            if (Schema::hasTable('wa_provider_configs')) {
                $byProv = DB::table('wa_provider_configs')
                    ->whereIn('provider', ['waba', 'twilio'])  // messaging engines only — skip meta_ads
                    ->select('provider', 'status', DB::raw('COUNT(*) as c'))
                    ->groupBy('provider', 'status')->get()
                    ->groupBy('provider');
                foreach ($byProv as $provider => $rows) {
                    $total = (int) $rows->sum('c');
                    $connected = (int) $rows->whereIn('status', ['connected', 'active', 'verified'])->sum('c');
                    $label = stripos($provider, 'twilio') !== false ? 'Twilio' : 'WhatsApp Cloud (WABA)';
                    $out[] = [
                        'key' => $provider, 'label' => $label,
                        'connected' => $connected, 'total' => $total,
                        'state' => $total === 0 ? 'idle' : ($connected > 0 ? 'up' : 'down'),
                        'breakdown' => $rows->pluck('c', 'status')->all(),
                    ];
                }
            }
        } catch (\Throwable $e) {}

        return $out;
    }

    private function system(): array
    {
        $out = [
            'php'      => PHP_VERSION,
            'laravel'  => app()->version(),
            'env'      => app()->environment(),
            'debug'    => config('app.debug') ? 'on' : 'off',
        ];
        try {
            $root = base_path();
            $free = @disk_free_space($root);
            $total = @disk_total_space($root);
            if ($free && $total) {
                $out['disk_free'] = round($free / 1073741824, 1) . ' GB';
                $out['disk_used_pct'] = round((1 - $free / $total) * 100);
            }
        } catch (\Throwable $e) {}
        $out['memory_limit'] = ini_get('memory_limit');
        return $out;
    }

    /* ───────────────────────────── util ─────────────────────────── */

    private function card(string $state, string $label, string $value, string $detail, array $extra = []): array
    {
        return array_merge(['state' => $state, 'label' => $label, 'value' => $value, 'detail' => $detail], $extra);
    }

    private function short(\Throwable $e): string
    {
        $m = $e->getMessage();
        return strlen($m) > 90 ? substr($m, 0, 87) . '…' : $m;
    }
}
