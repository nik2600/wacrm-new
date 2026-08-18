<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Single entry-point every outbound-send pipeline must call before
 * dispatching anything.
 *
 * Two layers:
 *   1. Emergency halt — platform.emergency_send_halt. Always enforced.
 *      guard() throws when engaged.
 *   2. WhatsApp guardrails — admin's rate caps + content filters from
 *      /admin/security. Gated by ONE master mode (security.wa_guardrails_mode):
 *        off      → no checks run at all (default; nothing changes)
 *        monitor  → checks run, a would-be block is LOGGED but the send is allowed
 *        enforce  → a violation blocks the send (throws)
 *
 * Everything in layer 2 is FAIL-OPEN: if a check itself errors, the send
 * is allowed and the error logged. A security convenience must never take
 * down delivery for a paying customer.
 *
 *   SendGate::guard();                       // halt only (throws on halt)
 *   SendGate::screen($wsId, $body, $ctx);    // halt + guardrails for a real send
 *   if (SendGate::halted()) ...              // soft halt check
 */
class SendGate
{
    private static ?bool $halted = null;

    // Shortener domains (abuse_block_short_links).
    private const SHORTLINK_RE = '~\b(bit\.ly|tinyurl\.com|t\.co|goo\.gl|ow\.ly|is\.gd|buff\.ly|rebrand\.ly|cutt\.ly|rb\.gy|shorturl\.at|tiny\.cc|bit\.do|soo\.gd|clck\.ru|t2m\.io|shorte\.st|adf\.ly|short\.io)\b~i';

    // Common scam phrasing (wa_hold_on_scam_pattern).
    private const SCAM_RE = '~(you[\' ]?ve? won|claim your (prize|reward)|congratulations.{0,24}(won|selected|winner)|verify your account|account (has been )?(suspended|locked|blocked|deactivated)|click (here )?to (confirm|verify|claim|reactivate)|urgent.{0,24}action required|kyc.{0,24}(required|pending|update)|double your (money|investment)|risk[- ]free (profit|return))~i';

    // High-risk financial-spam phrasing (abuse_block_finance_terms) — phrase
    // based, not single words, to avoid flagging legitimate fintech copy.
    private const FINANCE_RE = '~(guaranteed (returns?|profit|income)|binary options|forex (signal|trading|tips)|crypto(currency)? (investment|doubling|signal)|loan approved|instant (personal )?loan|interest[- ]free loan|earn .{0,18}(per day|daily|weekly) (from home|online)|investment opportunity|get rich quick)~i';

    public static function halted(): bool
    {
        if (self::$halted === null) {
            self::$halted = (bool) SystemSetting::get('platform.emergency_send_halt', false);
        }
        return self::$halted;
    }

    /**
     * Throw if a global halt is engaged. Audit row is written by the
     * SecurityController when the halt is flipped — we don't re-audit
     * every blocked send to avoid log spam.
     */
    public static function guard(): void
    {
        if (self::halted()) {
            throw new \RuntimeException('Outbound sends are paused (platform emergency halt is engaged). Resume sends from /admin/security → Danger zone.');
        }
    }

    /**
     * Full pre-send gate: emergency halt, then the WhatsApp guardrails.
     * Call once per outbound message with the workspace id + the message
     * body. $ctx is optional metadata for the audit row (msg id, to, etc).
     *
     * Throws \RuntimeException when a send must be blocked (halt, or a
     * guardrail violation while mode=enforce). Returns silently otherwise.
     */
    public static function screen(?int $workspaceId, ?string $body, array $ctx = []): void
    {
        self::guard(); // emergency halt — always on, separate from guardrails

        $mode = (string) SystemSetting::get('security.wa_guardrails_mode', 'off');
        if ($mode === 'off') return;

        try {
            $violation = self::inspect($workspaceId, $body);
        } catch (\Throwable $e) {
            // FAIL-OPEN — a broken check must never block a real send.
            Log::warning('[SENDGATE] guardrail check errored, allowing send: ' . $e->getMessage());
            return;
        }
        if ($violation === null) return;

        if ($mode === 'monitor') {
            Log::info('[SENDGATE][monitor] would block send: ' . $violation, $ctx);
            self::audit('security.send_flagged', $violation, $ctx);
            return; // allow — monitor only watches
        }

        // enforce
        self::audit('security.send_blocked', $violation, $ctx);
        throw new \RuntimeException('Send blocked by security policy (' . $violation . ').');
    }

    /**
     * Content-only screen for the bulk paths (campaigns / broadcasts), where
     * one body fans out to many recipients — we check the body ONCE before
     * handing the batch to the Node engine, not per recipient. Same mode
     * semantics + fail-open as screen(); no rate cap (bulk has its own pacing).
     */
    public static function screenBody(?string $body, array $ctx = []): void
    {
        $mode = (string) SystemSetting::get('security.wa_guardrails_mode', 'off');
        if ($mode === 'off') return;

        try {
            $violation = self::inspectContent((string) $body);
        } catch (\Throwable $e) {
            Log::warning('[SENDGATE] body check errored, allowing: ' . $e->getMessage());
            return;
        }
        if ($violation === null) return;

        if ($mode === 'monitor') {
            Log::info('[SENDGATE][monitor] would block batch: ' . $violation, $ctx);
            self::audit('security.send_flagged', $violation, $ctx);
            return;
        }
        self::audit('security.send_blocked', $violation, $ctx);
        throw new \RuntimeException('This message was blocked by security policy (' . $violation . ').');
    }

    /** @return string|null the first violation reason, or null if clean. */
    private static function inspect(?int $workspaceId, ?string $body): ?string
    {
        if (($reason = self::inspectContent((string) $body)) !== null) {
            return $reason;
        }
        return self::inspectRate($workspaceId);
    }

    private static function inspectContent(string $body): ?string
    {
        $text = trim($body);
        if ($text === '') return null;
        $low = mb_strtolower($text);

        $maxLinks = SecurityPolicy::int('wa_hold_on_links_count', 0);
        if ($maxLinks > 0) {
            $links = (int) preg_match_all('~https?://~i', $text);
            if ($links > $maxLinks) return 'message has ' . $links . ' links (limit ' . $maxLinks . ')';
        }
        if (SecurityPolicy::bool('wa_hold_on_scam_pattern', false) && preg_match(self::SCAM_RE, $low)) {
            return 'matches a scam pattern';
        }
        if (SecurityPolicy::bool('abuse_block_finance_terms', false) && preg_match(self::FINANCE_RE, $low)) {
            return 'contains a restricted financial term';
        }
        if (SecurityPolicy::bool('abuse_block_short_links', false) && preg_match(self::SHORTLINK_RE, $low)) {
            return 'contains a shortened link';
        }
        foreach (SecurityPolicy::arr('abuse_block_keyword_list', []) as $kw) {
            $kw = mb_strtolower(trim((string) $kw));
            if ($kw !== '' && str_contains($low, $kw)) {
                return 'contains a blocked keyword';
            }
        }
        return null;
    }

    /** Per-workspace rate caps (0 = unlimited). Atomic-ish via cache. */
    private static function inspectRate(?int $workspaceId): ?string
    {
        if (!$workspaceId) return null;

        $perMin = SecurityPolicy::int('wa_max_sends_per_minute', 0);
        if ($perMin > 0) {
            $k = 'wa-rl-min:' . $workspaceId . ':' . now()->format('YmdHi');
            $n = (int) Cache::get($k, 0);
            if ($n >= $perMin) return $perMin . ' sends/minute cap reached for this workspace';
            Cache::put($k, $n + 1, 120);
        }
        $perDay = SecurityPolicy::int('wa_max_sends_per_day', 0);
        if ($perDay > 0) {
            $k = 'wa-rl-day:' . $workspaceId . ':' . now()->format('Ymd');
            $n = (int) Cache::get($k, 0);
            if ($n >= $perDay) return $perDay . ' sends/day cap reached for this workspace';
            Cache::put($k, $n + 1, 90000);
        }
        return null;
    }

    private static function audit(string $action, string $reason, array $ctx): void
    {
        try {
            \App\Support\Audit::log($action, [
                'layer'  => 'platform',
                'result' => 'warning',
                'meta'   => array_merge(['reason' => $reason], $ctx),
            ]);
        } catch (\Throwable $e) {
            // audit must never break a send either
        }
    }

    /** Allow tests / admin tools to reset memoisation after toggling. */
    public static function reset(): void
    {
        self::$halted = null;
        SecurityPolicy::reset();
    }
}
