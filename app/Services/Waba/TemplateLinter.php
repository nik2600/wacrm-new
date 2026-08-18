<?php

namespace App\Services\Waba;

use App\Models\WaTemplate;

/**
 * Lint a `WaTemplate` BEFORE submitting it to Meta.
 *
 * The goal is to never let a submit hit Meta if we can predict it
 * will be rejected — every rejection drags the WABA's quality score
 * down, and that throttles every other template on the same number.
 *
 * Two severity tiers:
 *   - errors  → block submit. Hard Meta rule violations.
 *   - warnings → allow submit but show a banner. Practices that
 *                often (but not always) cause rejection or low
 *                quality scores.
 *
 * Reference: research/web sources cited in
 *   D:\Vault\kapil\WaDesk - WABA Templates phase plan - 2026-05-23.md
 */
class TemplateLinter
{
    /** Block-list of unique phrases Meta has historically flagged as promotional/manipulative. */
    private const TRIGGER_PHRASES = [
        'guaranteed', '100%', 'act now', 'click here', 'limited time only',
        'winner', 'congratulations you have won', 'free money', 'claim now',
        'risk free', 'no obligation', 'lifetime deal', 'don\'t miss',
    ];

    /**
     * @return array{errors: array<int,string>, warnings: array<int,string>}
     */
    public function check(WaTemplate $t): array
    {
        $errors   = [];
        $warnings = [];

        $this->checkName($t, $errors);
        $this->checkLanguage($t, $errors);
        $this->checkBody($t, $errors, $warnings);
        $this->checkHeader($t, $errors);
        $this->checkFooter($t, $errors);
        $this->checkPlaceholders($t, $errors, $warnings);
        $this->checkButtons($t, $errors);

        if ($t->template_type === 'carousel') $this->checkCarousel($t, $errors);
        if ($t->template_type === 'auth')     $this->checkAuth($t, $errors);

        $this->checkTriggerPhrases($t, $warnings);

        return ['errors' => array_values($errors), 'warnings' => array_values($warnings)];
    }

    public function passes(WaTemplate $t): bool
    {
        return empty($this->check($t)['errors']);
    }

    // -----------------------------------------------------------------
    // Individual rules
    // -----------------------------------------------------------------

    private function checkName(WaTemplate $t, array &$errors): void
    {
        $raw = trim((string) $t->template_name);
        if ($raw === '') { $errors[] = 'Template name is required.'; return; }

        $slug = mb_strtolower(preg_replace('/[^a-z0-9_]+/u', '_', $raw));
        if (mb_strlen($slug) > 512) {
            $errors[] = 'Template name is longer than 512 characters after normalization.';
        }
        if ($slug === '' || $slug === '_') {
            $errors[] = 'Template name must contain at least one alphanumeric character.';
        }
    }

    private function checkLanguage(WaTemplate $t, array &$errors): void
    {
        $lang = (string) ($t->language ?: '');
        // Meta accepts BCP47-like codes such as en_US, hi_IN, pt_BR.
        if (!preg_match('/^[a-z]{2,3}(_[A-Z]{2})?$/', $lang)) {
            $errors[] = 'Language must be a BCP-47 code like en_US, hi_IN, en, pt_BR.';
        }
    }

    private function checkBody(WaTemplate $t, array &$errors, array &$warnings): void
    {
        $body = (string) $t->template_body;
        if (trim($body) === '') {
            $errors[] = 'Body text is required — Meta rejects templates with empty bodies.';
            return;
        }

        $len = mb_strlen($body);
        if ($len > 1024) {
            $errors[] = "Body is {$len} characters; Meta hard limit is 1024.";
        } elseif ($len > 800) {
            $warnings[] = "Body is {$len} chars. Templates above ~800 chars are harder to read on mobile and tend to score lower on quality.";
        }

        // More than 2 consecutive newlines is a common rejection trigger.
        if (preg_match('/\n{3,}/', $body)) {
            $warnings[] = 'Body contains 3+ consecutive line breaks. Meta penalises long blank gaps — keep paragraphs tight.';
        }

        // All-caps words longer than 4 chars look like shouting.
        if (preg_match('/\b[A-Z]{5,}\b/', $body)) {
            $warnings[] = 'Body contains ALL-CAPS words; Meta\'s reviewers often flag this as shouting / promotional.';
        }

        // No placeholders in a MARKETING template usually scores LOW quality.
        if (strtoupper((string) $t->meta_category) === 'MARKETING' && $this->countPlaceholders($body) === 0) {
            $warnings[] = 'Marketing template has no placeholders. Personalised messages score higher and are less likely to be marked as spam.';
        }

        // Start/end-with-placeholder: a STRONG recommendation, not a hard block.
        // Meta approves many templates that end with a variable (utility "…confirmed
        // for {{1}}", OTP-style, etc.), so this is advisory — warn, but let the user
        // submit and let Meta be the final judge instead of blocking a template Meta
        // might well accept.
        $trimmed = trim($body);
        if (preg_match('/^\{\{\s*[\w_]+\s*\}\}/', $trimmed)) {
            $warnings[] = 'Body starts with a placeholder. Meta may reject this — safer to add text before {{1}} (e.g. "Hi {{1}}, …"). You can still submit and let Meta decide.';
        }
        if (preg_match('/\{\{\s*[\w_]+\s*\}\}\s*$/', $trimmed)) {
            $warnings[] = 'Body ends with a placeholder. Meta may reject this — safer to add closing text after the last {{N}}. You can still submit and let Meta decide.';
        }
        // Consecutive placeholders, run-on spaces, and tabs ARE reliable
        // automatic-rejection triggers, so these stay hard errors.
        if (preg_match('/\{\{\s*[\w_]+\s*\}\}\s*\{\{\s*[\w_]+\s*\}\}/', $body)) {
            $errors[] = 'Consecutive placeholders ({{1}}{{2}} or {{1}} {{2}}) are not allowed. Put descriptive text between every placeholder.';
        }
        if (preg_match('/ {4,}/', $body)) {
            $errors[] = 'Body contains 4 or more consecutive spaces. Meta rejects templates with run-on whitespace — use a single space.';
        }

        // Tab characters are explicitly disallowed by Meta — they
        // render unpredictably across iOS/Android/Web clients and
        // trip the auto-reject filter. Replace with single spaces.
        if (str_contains($body, "\t")) {
            $errors[] = 'Body contains TAB characters. Meta rejects tabs — replace with single spaces.';
        }

        // Emoji cap: MARKETING templates with >10 emojis are flagged
        // as spammy. Other categories are tolerant but still scored.
        // Match Unicode emoji (variation selectors + symbols).
        // Cover all common emoji blocks: emoticons, transport, symbols,
        // dingbats, misc-symbols-and-arrows. Without 2B00-2BFF the ⭐
        // and other "starred" emojis slip through the count.
        if (preg_match_all('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}]/u', $body, $em) && count($em[0]) > 10) {
            $cat = strtoupper((string) $t->meta_category);
            $msg = 'Body contains ' . count($em[0]) . ' emojis; Meta caps MARKETING templates at 10 and quality-scores others on emoji density.';
            if ($cat === 'MARKETING') $errors[]   = $msg . ' This is a hard reject in MARKETING — trim to 10 or fewer.';
            else                      $warnings[] = $msg;
        }
    }

    private function checkHeader(WaTemplate $t, array &$errors): void
    {
        if ($t->attachment_type && $t->attachment_type !== 'none') {
            if (empty($t->attachment_file)) {
                $errors[] = "Header is set to {$t->attachment_type} but no file was attached.";
            }
        }
        $header = (string) $t->header;
        if (mb_strlen($header) > 60) {
            $errors[] = 'Text header is longer than 60 characters; Meta hard limit.';
        }
        // Header supports AT MOST one placeholder (Meta hard rule).
        // Multiple placeholders in a header is a guaranteed rejection.
        if (preg_match_all('/\{\{\s*[\w_]+\s*\}\}/', $header, $hm) && count($hm[0]) > 1) {
            $errors[] = 'Header contains ' . count($hm[0]) . ' placeholders. Meta allows at most ONE {{1}} in a text header — move the rest to the body.';
        }
    }

    private function checkFooter(WaTemplate $t, array &$errors): void
    {
        $f = (string) $t->footer;
        if ($f === '') return;

        if (mb_strlen($f) > 60) {
            $errors[] = 'Footer is longer than 60 characters; Meta hard limit.';
        }
        if (preg_match('/\{\{\s*[\w_]+\s*\}\}/', $f)) {
            $errors[] = 'Footer cannot contain {{placeholders}}. Move dynamic data to the body or a button.';
        }
    }

    /**
     * Placeholders must be sequentially numbered starting from 1, with
     * no gaps: {{1}}, {{2}}, {{3}}. Meta rejects gapped numbering and
     * out-of-order placeholders.
     */
    private function checkPlaceholders(WaTemplate $t, array &$errors, array &$warnings): void
    {
        $haystack = (string) $t->template_body . "\n" . (string) $t->header;
        if (preg_match_all('/\{\{\s*([\w_]+)\s*\}\}/', $haystack, $m) === false) return;

        $tokens = $m[1] ?? [];
        if (empty($tokens)) return;

        $format = strtoupper((string) ($t->parameter_format ?: 'POSITIONAL'));

        if ($format === 'POSITIONAL') {
            $nums = array_filter(array_map(fn ($x) => ctype_digit($x) ? (int) $x : null, $tokens), fn ($x) => $x !== null);
            $named = count($tokens) - count($nums);
            if ($named > 0) {
                $errors[] = "Parameter format is POSITIONAL but $named named placeholders (like {{first_name}}) were found. Use {{1}}, {{2}}, … or switch parameter_format to NAMED.";
                return;
            }
            $unique = array_values(array_unique($nums));
            sort($unique);
            $expected = range(1, count($unique));
            if ($unique !== $expected) {
                $errors[] = 'Placeholders must start at {{1}} and run consecutively (no gaps). Found: {{' . implode('}}, {{', $unique) . '}}.';
            }
            // Meta REQUIRES `example.body_text` with one value per
            // placeholder — submissions without it get auto-rejected.
            // TemplatePayloadBuilder.bodyExample() pads with 'example'
            // strings so the create call still succeeds, but real
            // examples score much better in approval review.
            // Count real per-placeholder body examples. Each entry can be a
            // SCALAR *or* a nested array (variable_map 'body' is often
            // `[['ex1'], ['ex2']]`, or structured `[{example:..}]`). Casting an
            // array with `(string)` throws "Array to string conversion", which
            // 500'd the whole /templates/{id}/submit BEFORE it reached Meta — the
            // real reason created templates never got a meta_template_id. Flatten
            // arrays the same way TemplatePayloadBuilder does, then count.
            $bodyExamples = $t->variable_map['body'] ?? [];
            $exCount = 0;
            if (is_array($bodyExamples)) {
                foreach ($bodyExamples as $v) {
                    if (is_array($v)) {
                        $flat = array_filter($v, 'is_scalar');
                        $v = $flat ? reset($flat) : '';
                    }
                    if (is_scalar($v) && trim((string) $v) !== '') $exCount++;
                }
            }
            if ($exCount < count($unique)) {
                $missing    = count($unique) - $exCount;
                $warnings[] = "You have " . count($unique) . " placeholder(s) but only {$exCount} real example value(s). Meta auto-rejects templates without per-placeholder examples — fill in {$missing} more in the variable map before submitting.";
            }
        } else { // NAMED
            $invalid = array_filter($tokens, fn ($n) => !preg_match('/^[a-z][a-z0-9_]*$/', $n));
            if ($invalid) {
                $errors[] = 'Named placeholders must be lowercase letters/digits/underscores (e.g. {{first_name}}). Invalid: {{' . implode('}}, {{', array_unique($invalid)) . '}}.';
            }
        }
    }

    private function checkButtons(WaTemplate $t, array &$errors): void
    {
        $btns = is_array($t->buttons) ? $t->buttons : [];
        if (count($btns) > 10) {
            $errors[] = 'Meta allows at most 10 buttons per template.';
        }

        // Subtype caps — Meta enforces per-kind limits even when total ≤10.
        // URL buttons: max 2 across the whole template.
        // Phone-number buttons: max 1.
        // Copy-code buttons: max 1.
        $urlCount   = 0;
        $phoneCount = 0;
        $copyCount  = 0;
        foreach ($btns as $b) {
            $t2 = (string) ($b['type'] ?? '');
            if (in_array($t2, ['visit_website', 'url'], true))         $urlCount++;
            if (in_array($t2, ['call_phone', 'phone_number'], true))   $phoneCount++;
            if (in_array($t2, ['copy_code'], true))                    $copyCount++;
        }
        if ($urlCount   > 2) $errors[] = "Too many URL buttons ({$urlCount}). Meta allows at most 2 URL/visit-website buttons per template.";
        if ($phoneCount > 1) $errors[] = "Too many phone-number buttons ({$phoneCount}). Meta allows at most 1.";
        if ($copyCount  > 1) $errors[] = "Too many copy-code buttons ({$copyCount}). Meta allows at most 1.";

        // Same-type buttons must appear consecutively — Meta rejects
        // interleaved orderings like [URL, QuickReply, URL]. Walk the
        // type sequence and flag when a type reappears after a break.
        $seen = [];
        $lastType = null;
        foreach ($btns as $i => $b) {
            $kind = match ((string) ($b['type'] ?? '')) {
                'visit_website', 'url'          => 'url',
                'call_phone', 'phone_number'    => 'phone',
                'copy_code'                     => 'copy',
                'quick_reply'                   => 'reply',
                default                         => 'other',
            };
            if ($kind !== $lastType) {
                if (isset($seen[$kind])) {
                    $errors[] = 'Button ' . ($i + 1) . " (type {$kind}) breaks the grouping rule — same-type buttons must be consecutive. Reorder so all URL/phone/quick-reply buttons sit together.";
                    break;
                }
                $seen[$kind] = true;
            }
            $lastType = $kind;
        }

        foreach ($btns as $i => $b) {
            $type = (string) ($b['type'] ?? '');
            $text = (string) ($b['text'] ?? '');
            $val  = (string) ($b['value'] ?? '');

            if ($text === '') {
                $errors[] = 'Button ' . ($i + 1) . ' has no label text.';
            } elseif (mb_strlen($text) > 25) {
                $errors[] = 'Button ' . ($i + 1) . ' label exceeds 25 characters (Meta hard limit).';
            }

            if (in_array($type, ['visit_website', 'url'], true)) {
                if (trim($val) === '') {
                    $errors[] = 'URL button ' . ($i + 1) . ' has no URL.';
                } elseif (mb_strlen($val) > 2000) {
                    $errors[] = 'URL button ' . ($i + 1) . " URL is " . mb_strlen($val) . " chars. Meta hard limit is 2000.";
                } elseif (preg_match('/\{\{\s*[\w_]+\s*\}\}/', $val)) {
                    // Dynamic URL — Meta requires an `example` value for
                    // the placeholder portion so reviewers can render the
                    // final URL. We stash these in variable_map.url_<i>.
                    $exKey = 'url_' . $i;
                    $ex    = $t->variable_map[$exKey] ?? null;
                    if (!is_array($ex) || empty($ex[0])) {
                        $errors[] = "URL button " . ($i + 1) . " contains a {{placeholder}} but no example value was supplied. Meta requires `variable_map.{$exKey}` with a sample URL slug.";
                    }
                }
            }
            if (in_array($type, ['call_phone', 'phone_number'], true)) {
                if (!preg_match('/^\+?\d{6,15}$/', $val)) {
                    $errors[] = 'Phone-number button ' . ($i + 1) . ' must be a digits-only number (with optional leading +), 6–15 digits.';
                }
            }
        }
    }

    private function checkCarousel(WaTemplate $t, array &$errors): void
    {
        $cards = is_array($t->carousel_data) ? $t->carousel_data : [];
        $n = count($cards);
        if ($n < 2 || $n > 10) {
            $errors[] = "Carousel must have 2–10 cards (you have $n). Meta hard limit for media-card carousels.";
            return;
        }

        // Top-level body is mandatory for carousel templates.
        if (trim((string) $t->template_body) === '') {
            $errors[] = 'Carousel templates require a top-level body (the message text above the cards).';
        }

        $firstButtonShape = null;
        $firstMediaFormat = null;
        foreach ($cards as $idx => $card) {
            $cardIdx = $idx + 1;
            if (empty($card['body'])) {
                $errors[] = "Carousel card $cardIdx has no body text.";
            }
            if (mb_strlen((string) ($card['body'] ?? '')) > 160) {
                $errors[] = "Carousel card $cardIdx body exceeds 160 characters (Meta hard limit for card bodies).";
            }
            if (empty($card['image'])) {
                $errors[] = "Carousel card $cardIdx has no media (image or video). Every card needs one.";
            }

            // All cards must use the same media format.
            $thisFormat = $this->inferCardMediaFormat((string) ($card['image'] ?? ''));
            if ($firstMediaFormat === null) {
                $firstMediaFormat = $thisFormat;
            } elseif ($thisFormat !== $firstMediaFormat) {
                $errors[] = "Carousel card $cardIdx uses $thisFormat media but card 1 uses $firstMediaFormat. All cards must share the same media format.";
            }

            // All cards must have identical button SHAPES (same types in same order, same count).
            $btns  = is_array($card['buttons'] ?? null) ? $card['buttons'] : [];
            $shape = array_map(fn ($b) => (string) ($b['type'] ?? ''), $btns);
            if (empty($btns)) {
                $errors[] = "Carousel card $cardIdx has no buttons. Every card must have at least one button.";
            }
            if (count($btns) > 2) {
                $errors[] = "Carousel card $cardIdx has " . count($btns) . ' buttons; Meta allows at most 2 per carousel card.';
            }

            if ($firstButtonShape === null) {
                $firstButtonShape = $shape;
            } elseif ($shape !== $firstButtonShape) {
                $errors[] = "Carousel card $cardIdx button shape (" . implode(', ', $shape) . ') differs from card 1 (' . implode(', ', $firstButtonShape) . '). Carousel cards must have identical button counts and types in the same order.';
            }
        }
    }

    private function checkAuth(WaTemplate $t, array &$errors): void
    {
        if (strtoupper((string) $t->meta_category) !== 'AUTHENTICATION') {
            $errors[] = 'Auth-type templates must be filed under the AUTHENTICATION Meta category.';
        }
        $btns = is_array($t->buttons) ? $t->buttons : [];
        $otp  = collect($btns)->first(fn ($b) => in_array($b['type'] ?? '', ['otp_one_tap', 'otp_copy'], true));
        if (!$otp) {
            $errors[] = 'Auth templates must have one OTP button (otp_one_tap or otp_copy).';
            return;
        }
        if (($otp['type'] ?? '') === 'otp_one_tap') {
            if (empty($otp['package_name']))   $errors[] = 'One-tap OTP requires an Android package_name (e.g. com.example.app).';
            if (empty($otp['signature_hash'])) $errors[] = 'One-tap OTP requires a signature_hash (your APK\'s signing-key hash).';
        }
    }

    private function checkTriggerPhrases(WaTemplate $t, array &$warnings): void
    {
        $hay = mb_strtolower((string) $t->template_body . ' ' . (string) $t->header . ' ' . (string) $t->footer);
        foreach (self::TRIGGER_PHRASES as $phrase) {
            if (str_contains($hay, $phrase)) {
                $warnings[] = "Trigger phrase \"$phrase\" found. Meta often flags this as promotional/manipulative — consider rewording.";
            }
        }
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function countPlaceholders(string $s): int
    {
        preg_match_all('/\{\{\s*[\w_]+\s*\}\}/', $s, $m);
        return count($m[0]);
    }

    private function inferCardMediaFormat(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'mp4', 'mov', '3gp' => 'VIDEO',
            default              => 'IMAGE',
        };
    }
}
