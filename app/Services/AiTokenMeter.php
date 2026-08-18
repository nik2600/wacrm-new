<?php

namespace App\Services;

use App\Exceptions\PlanLimitReachedException;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * AI token usage tracker + monthly cap enforcer.
 *
 * Same pattern as the message-cap path: every AI call records its
 * token consumption to `ai_token_usage`. Before kicking off a new
 * call, callers ask `guard($workspace)` to check whether the
 * workspace is still under their plan's `ai_token_limit_monthly`.
 *
 * Only calls billed against `admin` keys count toward the cap.
 * Workspaces using their own BYOK keys can spend whatever their
 * own provider account allows.
 */
class AiTokenMeter
{
    /**
     * Log a single AI call's token usage.
     *
     *   $billedAgainst ∈ {'admin', 'workspace'} — matches the
     *   `source` field returned by AiKeyResolver::resolve().
     */
    public static function record(
        Workspace $workspace,
        string $provider,
        ?string $model,
        int $promptTokens,
        int $completionTokens,
        string $billedAgainst = 'admin',
    ): void {
        DB::table('ai_token_usage')->insert([
            'workspace_id'      => $workspace->id,
            'provider'          => $provider,
            'model'             => $model,
            'prompt_tokens'     => max(0, $promptTokens),
            'completion_tokens' => max(0, $completionTokens),
            'total_tokens'      => max(0, $promptTokens + $completionTokens),
            'billed_against'    => $billedAgainst,
            'created_at'        => now(),
        ]);
    }

    /**
     * Tokens this workspace has spent against ADMIN keys this calendar
     * month. Used both for the cap check and the /settings?tab=aikeys
     * usage badge.
     */
    public static function usedThisMonth(Workspace $workspace): int
    {
        return (int) DB::table('ai_token_usage')
            ->where('workspace_id', $workspace->id)
            ->where('billed_against', 'admin')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total_tokens');
    }

    /**
     * Throw PlanLimitReachedException if the workspace has burnt
     * through their plan's monthly AI token cap (admin keys only).
     *
     * Caller pattern (in any AI-using flow):
     *   AiTokenMeter::guard($workspace);
     *   $result = $client->complete(...);
     *   AiTokenMeter::record($workspace, ..., $promptT, $completionT);
     */
    public static function guard(Workspace $workspace): void
    {
        $limit = $workspace->effectiveLimit('ai_token_limit_monthly', null);
        if ($limit === null) return;

        $used = self::usedThisMonth($workspace);
        if ($used < (int) $limit) return;

        throw new PlanLimitReachedException(
            limitKey: 'ai_token_limit_monthly',
            used:     $used,
            limit:    (int) $limit,
            message:  "You've hit your plan's monthly AI token cap ({$limit}). Upgrade your plan, or enable BYOK and add your own keys to keep using AI features.",
        );
    }
}
