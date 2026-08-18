<?php

namespace App\Services;

use App\Models\KeywordReply;
use App\Models\KeywordReplyContent;
use App\Models\Workspace;

/**
 * Top-level façade for the auto-reply multilingual flow. Hides the
 * detector / translator / model wiring from controllers.
 *
 * Called from AutoReplyController on:
 *   - store / update     → translateForKeyword($reply, $targets)
 *   - inbound lookup     → resolveForInbound($reply, $detectedLang)
 *
 * Translator failures degrade gracefully — the row stays valid with
 * just the canonical-language form. A subsequent edit retries.
 */
class KeywordTranslationManager
{
    /**
     * Universal target list — every language Google Translate's public
     * API supports (~80, covering essentially every spoken language
     * with meaningful messaging-app presence). Translator::fanOut()
     * batches these in parallel via Http::pool() so the save stays
     * ~15-25s even with 80 calls.
     *
     * If your workspace's customer base is regional you can override
     * with `workspaces.auto_translate_languages` and we'll use just
     * those — same JSON-array shape.
     *
     * Any language NOT in this list still works at runtime via the
     * lazy-translate fallback (AutoReplyController::lookup translates
     * the inbound message → canonical English at match time, then
     * translates the canonical reply → customer's language live, all
     * cached for 24h so repeat hits are instant).
     */
    public const DEFAULT_TARGETS = [
        // Indo-European · Indic
        'hi', 'bn', 'ur', 'pa', 'gu', 'mr', 'or', 'ne', 'si', 'as', 'sd', 'sa',
        // Indo-European · Iranian
        'fa', 'ps', 'tg', 'ku',
        // Dravidian
        'ta', 'te', 'kn', 'ml',
        // East Asian
        'zh', 'zh-tw', 'ja', 'ko', 'mn',
        // SE Asian
        'vi', 'th', 'lo', 'km', 'my', 'id', 'ms', 'tl', 'jw', 'su', 'ceb',
        // Semitic / Middle East
        'ar', 'he', 'am', 'ti', 'so', 'mt',
        // Turkic
        'tr', 'az', 'kk', 'ky', 'uz', 'tt', 'tk',
        // Romance
        'es', 'pt', 'fr', 'it', 'ro', 'ca', 'gl', 'co',
        // Germanic
        'en', 'de', 'nl', 'sv', 'no', 'da', 'is', 'af', 'fy', 'lb', 'yi',
        // Slavic
        'ru', 'uk', 'pl', 'cs', 'sk', 'sr', 'hr', 'bs', 'sl', 'mk', 'bg', 'be',
        // Other European
        'el', 'sq', 'hu', 'fi', 'et', 'lt', 'lv', 'ga', 'gd', 'cy', 'eu',
        // African
        'sw', 'ha', 'yo', 'ig', 'zu', 'xh', 'st', 'sn', 'ny', 'mg', 'sm',
        // Caucasus
        'ka', 'hy',
        // Misc
        'eo', 'la', 'ht', 'haw', 'mi', 'hmn', 'ug',
    ];

    /**
     * Fan-out the keyword AND every selected text-reply content to
     * the workspace's configured language list. Persists the
     * generated maps back onto the row.
     */
    public static function translateForKeyword(KeywordReply $reply, ?array $targets = null): void
    {
        $ws       = $reply->workspace;
        $from     = $reply->canonical_language ?: ($ws?->default_language ?: 'en');
        $targets ??= self::targetsFor($ws);

        // 1. Keyword string → translations[]
        $reply->forceFill([
            'keyword_translations' => self::buildKeywordMap($reply->keyword, $from, $targets),
            'canonical_language'   => $from,
        ])->save();

        // 2. Each text-only content variant → its own translations[]
        //    (image/video/document variants don't need text translation;
        //    the same media file is sent regardless of language.)
        foreach ($reply->contents()->where('content_type', 'text')->get() as $variant) {
            if (empty(trim((string) $variant->content))) continue;
            $map = self::buildContentMap($variant->content, $from, $targets);
            $variant->forceFill(['content_translations' => $map])->save();
        }
    }

    /**
     * Pick the right reply text for a customer based on the language
     * we detected.
     *
     *   1. If the variant's pre-stored translations map has $lang →
     *      return it (instant, the common case).
     *   2. If not, translate the canonical text → $lang live via the
     *      Translator (cached 24h, so repeat customers in the same
     *      tail-language are also instant after the first hit).
     *   3. If the live translate fails → fall back to the canonical
     *      text. Customer still gets a reply, just in English.
     */
    public static function pickContent(KeywordReplyContent $variant, ?string $lang): string
    {
        $original = (string) $variant->content;
        if (!$lang || $original === '') return $original;

        $map = $variant->content_translations ?? [];
        if (isset($map[$lang])) return $map[$lang];

        // Resolve the canonical language for this variant's parent row.
        $canonical = $variant->keywordReply?->canonical_language ?: 'en';
        if (strtolower($lang) === strtolower($canonical)) return $original;

        // Lazy translate. Cached for 24h, so the second customer in
        // the same language pays zero latency.
        $live = Translator::translate($original, $canonical, $lang);
        return $live !== null ? $live : $original;
    }

    /**
     * What target languages does this workspace fan keywords into?
     * Order matters — the UI shows chips in this order.
     */
    public static function targetsFor(?Workspace $ws): array
    {
        if (!$ws) return self::DEFAULT_TARGETS;
        $raw = $ws->auto_translate_languages;
        if (is_array($raw) && !empty($raw)) return array_values(array_unique($raw));
        return self::DEFAULT_TARGETS;
    }

    /**
     * Build the keyword translations map. The CANONICAL language is
     * always recorded so the matcher can short-circuit on it.
     */
    private static function buildKeywordMap(string $keyword, string $from, array $targets): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') return [];
        $map = [$from => $keyword];
        foreach (Translator::fanOut($keyword, $from, $targets) as $lang => $translated) {
            $map[$lang] = $translated;
        }
        return $map;
    }

    /**
     * Same shape as the keyword map but for the longer reply text.
     */
    private static function buildContentMap(string $content, string $from, array $targets): array
    {
        $content = trim($content);
        if ($content === '') return [];
        $map = [$from => $content];
        foreach (Translator::fanOut($content, $from, $targets) as $lang => $translated) {
            $map[$lang] = $translated;
        }
        return $map;
    }
}
