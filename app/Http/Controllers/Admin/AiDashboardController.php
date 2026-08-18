<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * AI usage / token-burn dashboard (platform admin).
 *
 * Reads the `ai_token_usage` ledger (every AI call records provider, model,
 * prompt/completion/total tokens, workspace, and billed_against = 'admin'
 * [platform key] vs 'workspace' [BYOK]). Surfaces: total burn, provider mix,
 * daily trend, model mix, top-burning workspaces, platform-vs-BYOK split, an
 * estimated $ cost, plus the live state of every configured AI key.
 */
class AiDashboardController extends Controller
{
    /** Rough blended $/1K-token rates per provider — for an ESTIMATE only. */
    private const RATE_PER_1K = [
        'openai'    => 0.005,
        'anthropic' => 0.006,
        'gemini'    => 0.001,
        'google'    => 0.001,
        'deepseek'  => 0.0008,
        'grok'      => 0.005,
        'mistral'   => 0.002,
    ];

    public function index(Request $request): View
    {
        $window = in_array($request->query('window'), ['7d', '30d', '90d', '1y'], true) ? $request->query('window') : '30d';
        $days   = ['7d' => 7, '30d' => 30, '90d' => 90, '1y' => 365][$window];
        $from   = now()->subDays($days - 1)->startOfDay();

        $has = Schema::hasTable('ai_token_usage');

        return view('admin.ai-dashboard.index', [
            'window'        => $window,
            'kpis'          => $this->kpis($has, $from),
            'byProvider'    => $this->byProvider($has, $from),
            'daily'         => $this->daily($has, $from, $days),
            'byModel'       => $this->byModel($has, $from),
            'topWorkspaces' => $this->topWorkspaces($has, $from),
            'sourceSplit'   => $this->sourceSplit($has, $from),
            'keys'          => $this->keyStatus(),
            'voice'         => $this->voiceUsage($from),
        ]);
    }

    private function base(bool $has, Carbon $from)
    {
        return DB::table('ai_token_usage')->where('created_at', '>=', $from);
    }

    private function kpis(bool $has, Carbon $from): array
    {
        if (!$has) {
            return ['tokens' => 0, 'calls' => 0, 'admin_tokens' => 0, 'byok_tokens' => 0, 'cost' => 0.0, 'providers' => 0, 'workspaces' => 0];
        }
        $rows = $this->base($has, $from);
        $tokens = (int) (clone $rows)->sum('total_tokens');
        $admin  = (int) (clone $rows)->where('billed_against', 'admin')->sum('total_tokens');
        $byok   = (int) (clone $rows)->where('billed_against', 'workspace')->sum('total_tokens');
        return [
            'tokens'     => $tokens,
            'calls'      => (int) (clone $rows)->count(),
            'admin_tokens' => $admin,
            'byok_tokens'  => $byok,
            'cost'       => $this->estimateCost($from),
            'providers'  => (int) (clone $rows)->distinct()->count('provider'),
            'workspaces' => (int) (clone $rows)->distinct()->count('workspace_id'),
        ];
    }

    /** Estimated $ spend, summed per provider at its blended rate. */
    private function estimateCost(Carbon $from): float
    {
        $cost = 0.0;
        $rows = DB::table('ai_token_usage')->where('created_at', '>=', $from)
            ->select('provider', DB::raw('SUM(total_tokens) as t'))->groupBy('provider')->get();
        foreach ($rows as $r) {
            $rate = self::RATE_PER_1K[strtolower((string) $r->provider)] ?? 0.004;
            $cost += ((int) $r->t / 1000) * $rate;
        }
        return round($cost, 2);
    }

    private function byProvider(bool $has, Carbon $from): array
    {
        if (!$has) return [];
        return $this->base($has, $from)
            ->select('provider', DB::raw('SUM(total_tokens) as tokens'), DB::raw('COUNT(*) as calls'))
            ->groupBy('provider')->orderByDesc('tokens')->get()
            ->map(fn ($r) => ['provider' => $r->provider ?: 'unknown', 'tokens' => (int) $r->tokens, 'calls' => (int) $r->calls])
            ->all();
    }

    private function daily(bool $has, Carbon $from, int $days): array
    {
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $series[now()->subDays($days - 1 - $i)->toDateString()] = 0;
        }
        if ($has) {
            DB::table('ai_token_usage')->where('created_at', '>=', $from)
                ->select(DB::raw('DATE(created_at) as d'), DB::raw('SUM(total_tokens) as t'))
                ->groupBy('d')->get()
                ->each(function ($r) use (&$series) { if (isset($series[$r->d])) $series[$r->d] = (int) $r->t; });
        }
        return $series;
    }

    private function byModel(bool $has, Carbon $from): array
    {
        if (!$has) return [];
        return $this->base($has, $from)
            ->select('model', 'provider', DB::raw('SUM(total_tokens) as tokens'), DB::raw('COUNT(*) as calls'))
            ->groupBy('model', 'provider')->orderByDesc('tokens')->limit(10)->get()
            ->map(fn ($r) => ['model' => $r->model ?: '—', 'provider' => $r->provider, 'tokens' => (int) $r->tokens, 'calls' => (int) $r->calls])
            ->all();
    }

    private function topWorkspaces(bool $has, Carbon $from): array
    {
        if (!$has) return [];
        $rows = $this->base($has, $from)
            ->select('workspace_id', DB::raw('SUM(total_tokens) as tokens'), DB::raw('COUNT(*) as calls'),
                DB::raw("SUM(CASE WHEN billed_against='workspace' THEN total_tokens ELSE 0 END) as byok"))
            ->groupBy('workspace_id')->orderByDesc('tokens')->limit(12)->get();
        $names = \App\Models\Workspace::whereIn('id', $rows->pluck('workspace_id'))->pluck('name', 'id');
        return $rows->map(fn ($r) => [
            'workspace_id' => (int) $r->workspace_id,
            'name'   => $names[$r->workspace_id] ?? ('Workspace #' . $r->workspace_id),
            'tokens' => (int) $r->tokens,
            'calls'  => (int) $r->calls,
            'byok'   => (int) $r->byok,
        ])->all();
    }

    private function sourceSplit(bool $has, Carbon $from): array
    {
        if (!$has) return ['admin' => 0, 'workspace' => 0];
        $rows = $this->base($has, $from)
            ->select('billed_against', DB::raw('SUM(total_tokens) as t'))->groupBy('billed_against')->pluck('t', 'billed_against');
        return ['admin' => (int) ($rows['admin'] ?? 0), 'workspace' => (int) ($rows['workspace'] ?? 0)];
    }

    /** Live state of every configured AI key — platform (AdminAiKey) + BYOK. */
    private function keyStatus(): array
    {
        $platform = [];
        try {
            $platform = \App\Models\AdminAiKey::query()->orderBy('provider')->get()
                ->map(fn ($k) => [
                    'provider' => $k->provider,
                    'model'    => $k->default_model,
                    'active'   => (bool) $k->is_active && !empty($k->api_key),
                ])->all();
        } catch (\Throwable $e) {}

        $byok = ['count' => 0, 'workspaces' => 0, 'byProvider' => []];
        try {
            if (Schema::hasTable('ai_provider_keys')) {
                $byok['count']      = (int) DB::table('ai_provider_keys')->where('is_active', true)->count();
                $byok['workspaces'] = (int) DB::table('ai_provider_keys')->where('is_active', true)->distinct()->count('workspace_id');
                $byok['byProvider'] = DB::table('ai_provider_keys')->where('is_active', true)
                    ->select('provider', DB::raw('COUNT(*) as c'))->groupBy('provider')->pluck('c', 'provider')->all();
            }
        } catch (\Throwable $e) {}

        return ['platform' => $platform, 'byok' => $byok];
    }

    /** Voice (TTS/STT) usage rollup, if tracked. */
    private function voiceUsage(Carbon $from): array
    {
        $out = ['rows' => 0, 'calls' => 0];
        try {
            if (Schema::hasTable('ai_voice_usage_daily')) {
                $q = DB::table('ai_voice_usage_daily');
                $out['rows'] = (int) $q->count();
            }
            if (Schema::hasTable('ai_call_logs')) {
                $out['calls'] = (int) DB::table('ai_call_logs')->where('created_at', '>=', $from)->count();
            }
        } catch (\Throwable $e) {}
        return $out;
    }
}
