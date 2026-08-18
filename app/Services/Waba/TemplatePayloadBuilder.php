<?php

namespace App\Services\Waba;

use App\Models\WaTemplate;

/**
 * Builds the Meta-Cloud `POST /{WABA_ID}/message_templates`
 * request body from a local `WaTemplate` row.
 *
 * Pure — no I/O, no DB writes, no Meta calls. Easy to unit-test
 * and reuse from CLI / queue jobs / preview endpoints.
 *
 * Local shape → Meta shape mapping:
 *
 *   template_type=standard       → BODY (+ optional HEADER text/media, FOOTER, BUTTONS)
 *   template_type=media          → BODY + HEADER format=IMAGE|VIDEO|DOCUMENT
 *   template_type=carousel       → BODY + CAROUSEL with 2..10 cards
 *   template_type=auth           → AUTHENTICATION with OTP button
 *
 *   buttons[].type:
 *     visit_website  → URL          (with `url` + `example` for variables)
 *     call_phone     → PHONE_NUMBER (with `phone_number`)
 *     copy_code      → COPY_CODE    (with `example`)
 *     quick_reply    → QUICK_REPLY  (text only)
 *     otp_one_tap    → OTP one-tap autofill (auth templates only)
 *     otp_copy       → OTP copy-code only (auth templates only)
 *
 * `variable_map` shape (local):
 *   [
 *     'body'   => [['var1'], ['var2']],   // each entry is the example for {{N}}
 *     'header' => ['example value'],
 *     'url_0'  => ['example-slug'],       // example values for URL button N
 *   ]
 */
class TemplatePayloadBuilder
{
    /**
     * Build the full CREATE-template payload. Caller is responsible
     * for header media upload (resumable handle) — pass the handle
     * in via $mediaHandles keyed by 'header' or 'card_<index>'.
     *
     * @param  WaTemplate  $t
     * @param  array<string,string>  $mediaHandles  e.g. ['header' => '4::aW...', 'card_0' => '...']
     * @return array
     */
    public function build(WaTemplate $t, array $mediaHandles = []): array
    {
        $name  = $this->normalizeName((string) $t->template_name);
        $lang  = $t->language ?: 'en_US';
        $meta  = strtoupper((string) ($t->meta_category ?: $this->guessMetaCategory($t)));

        $payload = [
            'name'                  => $name,
            'category'              => $meta,
            'language'              => $lang,
            'allow_category_change' => true,
            'parameter_format'      => $t->parameter_format ?: 'POSITIONAL',
            'components'            => $this->buildComponents($t, $mediaHandles),
        ];

        // Auth templates require message_send_ttl_seconds on Meta's side.
        if ($t->template_type === 'auth') {
            $payload['message_send_ttl_seconds'] = 60;
        }

        return $payload;
    }

    /**
     * Build the SEND-template payload — the `template` object inside
     *   POST /{PHONE_ID}/messages { type: 'template', template: { ... } }
     *
     * $vars is the per-recipient parameter map:
     *   [
     *     'header'    => 'A12345',
     *     'header_media_id' => 'MEDIA_ID',   // for IMAGE/VIDEO/DOCUMENT headers
     *     'body'      => ['Sudhir', '1 × Hoodie', 'May 25'],
     *     'buttons'   => [['index' => 1, 'sub_type' => 'url', 'value' => 'INV-001']],
     *     'cards'     => [
     *       0 => ['header_media_id' => '...', 'body' => ['30'], 'buttons' => [...]],
     *       1 => [...],
     *     ],
     *   ]
     */
    public function buildSend(WaTemplate $t, array $vars = []): array
    {
        // Authentication templates have a single body parameter (the OTP
        // code) which ALSO populates the OTP button's parameter. The
        // caller may pass it as $vars['otp'] OR as $vars['body'][0];
        // normalize to both so downstream code is consistent.
        if ($t->template_type === 'auth') {
            $code = $this->scalarize($vars['otp'] ?? $vars['body'][0] ?? '');
            $vars['body']    = [$code];
            $vars['buttons'] = [['index' => 0, 'sub_type' => 'url', 'value' => $code]];
        }

        $send = [
            'name'       => $this->normalizeName((string) $t->template_name),
            'language'   => ['code' => $t->language ?: 'en_US'],
            'components' => [],
        ];

        $headerComp = $this->buildSendHeader($t, $vars);
        if ($headerComp) $send['components'][] = $headerComp;

        // BODY — pad params to match the placeholder count in the approved
        // template body. Meta rejects with #132000 when the count differs.
        //
        // CRITICAL: Meta does NOT accept an EMPTY text value — a parameter
        // sent as {type:text, text:""} is rejected with #131008 "Parameter of
        // type text is missing text value", which fails the whole send (the
        // "Autopost chat" campaign hit this on every recipient). So any slot
        // whose runtime value is blank — because the variable didn't resolve
        // (e.g. a contact with no name) or we're padding to reach the count —
        // must be back-filled with a NON-EMPTY fallback, in order:
        //   1) the template's own body example for that index (variable_map),
        //   2) the placeholder's name if it's a word ({{name}} → "name"),
        //   3) a neutral "-" so delivery still succeeds.
        $bodyVars      = (array) ($vars['body'] ?? []);
        $bodyPlaceholders = $this->countPlaceholders((string) $t->template_body);
        $isAuth        = ($t->template_type === 'auth');

        // Match the parameter count to the template EXACTLY. Meta rejects with
        // #132000 "number of parameters does not match" when the count differs,
        // so we neither over- nor under-supply:
        //   - auth templates carry exactly one body param (the OTP), even when
        //     template_body has no {{n}} — never drop it;
        //   - every other template gets EXACTLY $bodyPlaceholders params: pad
        //     short callers up, and CAP over-supplying callers down (the old
        //     `max(...)` kept extras → #132000);
        //   - zero placeholders → emit NO body component at all, even if the
        //     caller sent stray values.
        if ($isAuth) {
            $bodyVars = array_slice(array_values($bodyVars), 0, 1);
            if (empty($bodyVars)) $bodyVars = [''];
        } else {
            while (count($bodyVars) < $bodyPlaceholders) $bodyVars[] = '';
            $bodyVars = array_slice(array_values($bodyVars), 0, $bodyPlaceholders);
        }

        if (!empty($bodyVars)) {
            $bodyExamples = (array) ($t->variable_map['body'] ?? []);
            $bodyNames    = $this->extractPlaceholderNames((string) $t->template_body);
            // Coerce any value to a scalar string. Both the caller's value AND a
            // variable_map example entry can be an ARRAY — the example is often a
            // structured {key, example, ...} object, and a value can be a nested
            // custom attribute. Casting those straight to string threw
            // "Array to string conversion" (TemplatePayloadBuilder:148) and 500'd
            // every template send. Prefer a labelled scalar, else join scalars.
            // NAMED-format templates store their body with literal {{name}}
            // placeholders and Meta matches send-time parameters BY NAME, not by
            // position. A positional {type:text,text:…} param leaves the named
            // placeholder unfilled, so the recipient receives the RAW "{{name}}"
            // (the reported campaign bug). When the template is NAMED we tag each
            // parameter with its placeholder name; POSITIONAL is unchanged.
            $isNamed = strtoupper((string) ($t->parameter_format ?: 'POSITIONAL')) === 'NAMED';
            $scal = fn ($x): string => $this->scalarize($x);
            $toParam = function ($v, $i) use ($bodyExamples, $bodyNames, $scal, $isNamed) {
                $val = trim($scal($v));
                if ($val === '') {
                    $ex = trim($scal($bodyExamples[$i] ?? ''));
                    $nm = (string) ($bodyNames[$i] ?? '');
                    $val = $ex !== ''
                        ? $ex
                        : (($nm !== '' && !is_numeric($nm)) ? $nm : '-');
                }
                $param = ['type' => 'text', 'text' => $val];
                if ($isNamed) {
                    $pname = (string) ($bodyNames[$i] ?? '');
                    if ($pname !== '' && !is_numeric($pname)) {
                        $param['parameter_name'] = $pname;
                    }
                }
                return $param;
            };

            $params = [];
            $seenNames = [];
            foreach (array_values($bodyVars) as $i => $v) {
                $p = $toParam($v, $i);
                // Meta rejects a NAMED body with two parameters sharing a
                // parameter_name — a placeholder reused twice ({{name}} … {{name}})
                // needs ONE parameter. Keep the first, drop repeats.
                if ($isNamed && isset($p['parameter_name'])) {
                    if (isset($seenNames[$p['parameter_name']])) continue;
                    $seenNames[$p['parameter_name']] = true;
                }
                $params[] = $p;
            }
            $send['components'][] = [
                'type'       => 'body',
                'parameters' => $params,
            ];
        }

        // BUTTONS — emit a send-time param ONLY for buttons that need
        // one. Meta rejects with #132000 if you send params for a
        // static URL button (no placeholder in URL) or a phone_number
        // button. The rule is:
        //   - URL button: only if the template URL has `{{N}}`
        //   - quick_reply / copy_code / voice_call: always
        //   - phone_number: never
        $tplButtons = is_array($t->buttons) ? $t->buttons : [];
        foreach (($vars['buttons'] ?? []) as $btn) {
            if (!$this->shouldEmitButtonParam($btn, $tplButtons)) continue;
            $send['components'][] = $this->buildSendButton($btn);
        }

        if ($t->template_type === 'carousel') {
            // Re-index keys to 0..N-1 — if the source array has gaps
            // (e.g. operator deleted card 2 in the UI but the JSON
            // kept keys [0,1,3]), Meta rejects with #132000
            // "card_index must be sequential". array_values defends
            // against that without changing the underlying data.
            $cards = array_values($vars['cards'] ?? []);
            // Template-side card button definitions, re-indexed the same way,
            // so each card's send buttons can be filtered against whether the
            // approved button actually accepts a parameter (mirrors the
            // top-level shouldEmitButtonParam gate — a static URL or phone
            // button must NOT carry a send param or Meta rejects with #132000).
            $tplCards = array_values(is_array($t->carousel_data) ? $t->carousel_data : []);
            $cardComps = [];
            foreach ($cards as $idx => $cardVars) {
                $tplCardButtons = is_array($tplCards[$idx]['buttons'] ?? null) ? $tplCards[$idx]['buttons'] : [];
                $cardComps[] = $this->buildSendCard((int) $idx, $cardVars, $tplCardButtons);
            }
            if ($cardComps) {
                $send['components'][] = ['type' => 'carousel', 'cards' => $cardComps];
            }
        }

        return $send;
    }

    /**
     * Returns true if a send-time button param is needed for this
     * button. Cross-references the template's BUTTONS definition by
     * index so we know whether the URL has `{{N}}` in it.
     *
     * Special cases:
     *   - quick_reply / copy_code / voice_call: ALWAYS need params
     *   - URL with no placeholder: NEVER (Meta error 132000)
     *   - OTP button (otp_one_tap / otp_copy): ALWAYS — the OTP code
     *     is the parameter, even though the create-time URL has no
     *     placeholder in the conventional sense
     */
    private function shouldEmitButtonParam(array $btn, array $tplButtons): bool
    {
        $sub = strtolower((string) ($btn['sub_type'] ?? ''));
        if (in_array($sub, ['quick_reply', 'copy_code', 'voice_call'], true)) return true;
        if ($sub !== 'url') return false;
        $idx = (int) ($btn['index'] ?? 0);
        $tplBtn = $tplButtons[$idx] ?? null;
        if (!$tplBtn) return false;
        // OTP buttons (auth templates) always need the OTP code as a
        // parameter at send time.
        $type = (string) ($tplBtn['type'] ?? '');
        if (in_array($type, ['otp_one_tap', 'otp_copy', 'otp'], true)) return true;
        $url = (string) ($tplBtn['value'] ?? $tplBtn['url'] ?? '');
        return $this->countPlaceholders($url) > 0;
    }

    // -----------------------------------------------------------------
    // CREATE — components builder
    // -----------------------------------------------------------------

    private function buildComponents(WaTemplate $t, array $mediaHandles): array
    {
        return match ($t->template_type) {
            'auth'     => $this->buildAuthComponents($t),
            'carousel' => $this->buildCarouselComponents($t, $mediaHandles),
            default    => $this->buildStandardComponents($t, $mediaHandles),
        };
    }

    private function buildStandardComponents(WaTemplate $t, array $mediaHandles): array
    {
        $components = [];

        if ($t->header || ($t->attachment_type && $t->attachment_type !== 'none')) {
            $components[] = $this->buildHeader($t, $mediaHandles['header'] ?? null);
        }

        $components[] = $this->buildBody($t);

        if ($t->footer) {
            $components[] = ['type' => 'FOOTER', 'text' => (string) $t->footer];
        }

        $buttons = $this->buildButtons($t->buttons ?? []);
        if ($buttons) $components[] = $buttons;

        return array_values(array_filter($components));
    }

    private function buildCarouselComponents(WaTemplate $t, array $mediaHandles): array
    {
        $components = [];

        // Carousel REQUIRES a top-level BODY message bubble — that's the
        // text that shows above the carousel. Falls back to a sensible
        // default if the user left it blank (Meta rejects empty body).
        $components[] = $this->buildBody($t);

        $cards = [];
        foreach (($t->carousel_data ?? []) as $idx => $card) {
            $cardComps = [];

            // Card header — must be IMAGE or VIDEO; format inferred from upload.
            // Only emit when we actually have a usable media handle — emitting
            // `header_handle:[""]` triggers Meta error code 100 "invalid
            // header_handle" and the whole carousel submission gets rejected.
            $cardHandle = (string) ($mediaHandles['card_' . $idx] ?? '');
            if ($cardHandle !== '') {
                $cardComps[] = [
                    'type'    => 'HEADER',
                    'format'  => $this->inferCardMediaFormat($card),
                    'example' => ['header_handle' => [$cardHandle]],
                ];
            }

            if (!empty($card['body'])) {
                $cardComps[] = [
                    'type' => 'BODY',
                    'text' => (string) $card['body'],
                ] + $this->bodyExample((string) $card['body'], $t->variable_map['cards'][$idx]['body'] ?? null);
            }

            $cardButtons = $this->buildButtons($card['buttons'] ?? []);
            if ($cardButtons) $cardComps[] = $cardButtons;

            $cards[] = ['components' => $cardComps];
        }

        $components[] = ['type' => 'CAROUSEL', 'cards' => $cards];

        return $components;
    }

    private function buildAuthComponents(WaTemplate $t): array
    {
        $buttons = $t->buttons ?? [];
        $otp     = collect($buttons)->first(fn ($b) => in_array($b['type'] ?? '', ['otp_one_tap', 'otp_copy'], true));

        // Component `type` must be uppercase to match Meta's canonical
        // spec — `BODY` / `FOOTER` / `BUTTONS` (not `body` / `footer` /
        // `buttons`). The button entries inside `buttons[]` keep their
        // existing lowercase keys (`type:"otp"`, `otp_type:"copy_code"`)
        // which is what Meta's authentication-template docs show.
        // Code validity — per-template, Meta allows 1–90 minutes. Falls back to
        // 10 when the template didn't set one (was hard-coded to 10 before).
        $expiry = (int) ($t->code_expiration_minutes ?: 10);
        $expiry = max(1, min(90, $expiry));

        $components = [
            ['type' => 'BODY',   'add_security_recommendation' => true],
            ['type' => 'FOOTER', 'code_expiration_minutes'     => $expiry],
        ];

        if ($otp) {
            $btn = [
                'type'     => 'OTP',
                'otp_type' => ($otp['type'] === 'otp_one_tap') ? 'ONE_TAP' : 'COPY_CODE',
                'text'     => (string) ($otp['text'] ?: 'Copy code'),
            ];
            if ($otp['type'] === 'otp_one_tap') {
                $btn['autofill_text']  = (string) ($otp['autofill_text']  ?? 'Autofill');
                $btn['package_name']   = (string) ($otp['package_name']   ?? '');
                $btn['signature_hash'] = (string) ($otp['signature_hash'] ?? '');
            }
            $components[] = ['type' => 'BUTTONS', 'buttons' => [$btn]];
        }

        return $components;
    }

    private function buildHeader(WaTemplate $t, ?string $mediaHandle): array
    {
        $format = strtoupper((string) ($t->attachment_type ?: 'TEXT'));
        if (!in_array($format, ['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT', 'LOCATION'], true)) {
            $format = 'TEXT';
        }
        if ($format === 'TEXT') {
            $headerText = (string) $t->header;
            $node = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $headerText];
            if ($this->countPlaceholders($headerText) > 0) {
                $hExamples = (array) ($t->variable_map['header'] ?? []);
                if (empty($hExamples)) {
                    // Use the placeholder name from the header text itself
                    // instead of the literal token "example" — Meta's review
                    // queue auto-rejects "example" as filler-not-replaced.
                    $names = $this->extractPlaceholderNames($headerText);
                    $hExamples = $names ?: ['Sample'];
                }
                // Coerce each to a string — same #100 guard as body_text.
                $node['example'] = ['header_text' => array_values(array_map(
                    fn ($x) => $this->exampleToString($x) ?: 'Sample',
                    $hExamples
                ))];
            }
            return $node;
        }

        return [
            'type'    => 'HEADER',
            'format'  => $format,
            'example' => ['header_handle' => [(string) ($mediaHandle ?? '')]],
        ];
    }

    private function buildBody(WaTemplate $t): array
    {
        $text = (string) $t->template_body;
        $node = ['type' => 'BODY', 'text' => $text];
        return $node + $this->bodyExample($text, $t->variable_map['body'] ?? null);
    }

    /**
     * `example.body_text` is a 2-D array: outer = number of placeholder
     * sets, inner = one entry per placeholder. We always send a single
     * set, so wrap once.
     */
    private function bodyExample(string $text, $examples): array
    {
        $n = $this->countPlaceholders($text);
        if ($n === 0) return [];

        $examples = is_array($examples) ? array_values($examples) : [];
        $names    = $this->extractPlaceholderNames($text);

        // Every slot MUST be a non-empty plain STRING. A variable_map body entry
        // can be a nested array (`[['ex1']]`) or a structured `{example:..}`
        // object; leaving it un-flattened made body_text[i][j] an array, which
        // Meta rejects with "(#100) … must be a string" — the exact submit error.
        // Empty slots fall back to the placeholder NAME (or "Sample N"); the
        // literal token "example" is avoided as Meta auto-rejects that filler.
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $val = trim($this->exampleToString($examples[$i] ?? ''));
            if ($val === '') {
                $tag = $names[$i] ?? null;
                $val = ($tag !== null && $tag !== '' && !is_numeric($tag))
                    ? $tag
                    : ('Sample ' . ($i + 1));
            }
            $out[] = $val;
        }

        return ['example' => ['body_text' => [$out]]];
    }

    /**
     * Coerce ANY variable_map example value to a plain scalar string — scalar as
     * is, structured object by its labelled key, else the first scalar it holds.
     * Prevents "Array to string conversion" AND Meta's #100 non-string rejection.
     */
    private function exampleToString($x): string
    {
        if (is_array($x)) {
            foreach (['example', 'text', 'value', 'key'] as $k) {
                if (isset($x[$k]) && is_scalar($x[$k])) return (string) $x[$k];
            }
            $flat = array_filter($x, 'is_scalar');
            return $flat ? (string) reset($flat) : '';
        }
        return is_scalar($x) ? (string) $x : '';
    }

    /**
     * Pull the raw placeholder names out of body text so we can use them
     * as Meta `example` filler instead of the literal word "example".
     */
    private function extractPlaceholderNames(string $text): array
    {
        if (!preg_match_all('/\{\{\s*([^\s{}]+?)\s*\}\}/', $text, $m)) {
            return [];
        }
        return $m[1] ?? [];
    }

    private function buildButtons(array $btns): ?array
    {
        if (empty($btns)) return null;

        $out = [];
        foreach ($btns as $b) {
            if (!is_array($b)) continue;
            $type = (string) ($b['type'] ?? '');
            $text = (string) ($b['text'] ?? '');
            $val  = (string) ($b['value'] ?? '');

            $node = ['text' => $text];
            switch ($type) {
                case 'visit_website':
                case 'url':
                    $node['type'] = 'URL';
                    $node['url']  = $val;
                    if ($this->countPlaceholders($val) > 0) {
                        // Positional array — `array_values` defends against
                        // accidental associative arrays from user input.
                        // If no example provided, derive one from the URL's
                        // own placeholder name rather than shipping the
                        // literal word "example" which Meta auto-rejects.
                        $userExample = $b['example'] ?? null;
                        if (!empty($userExample)) {
                            $node['example'] = array_values(array_map(
                                fn ($x) => $this->exampleToString($x),
                                (array) $userExample
                            ));
                        } else {
                            $names = $this->extractPlaceholderNames($val);
                            $node['example'] = array_values($names ?: ['sample']);
                        }
                    }
                    break;
                case 'call_phone':
                case 'phone_number':
                    $node['type']         = 'PHONE_NUMBER';
                    $node['phone_number'] = $val;
                    break;
                case 'copy_code':
                    $node['type']    = 'COPY_CODE';
                    // Meta requires `example` as a positional array of strings.
                    // Cast through array_values so an assoc map can't leak in.
                    $node['example'] = array_values(array_map(
                        fn ($x) => $this->exampleToString($x),
                        (array) ($b['example'] ?? [$val ?: 'ABC123'])
                    ));
                    break;
                case 'quick_reply':
                default:
                    $node['type'] = 'QUICK_REPLY';
            }
            $out[] = $node;
        }

        if (empty($out)) return null;
        return ['type' => 'BUTTONS', 'buttons' => array_slice($out, 0, 10)];
    }

    // -----------------------------------------------------------------
    // SEND — per-recipient parameter builder
    // -----------------------------------------------------------------

    /**
     * Coerce ANY value to a scalar string for a Meta parameter. A $vars value
     * can be an ARRAY (a contact custom attribute stored as JSON, or a
     * variable_map {key,example} object) — casting that straight to string
     * threw "Array to string conversion" and failed the whole send. Prefer a
     * labelled scalar (example/text/value/key), else join the scalar members.
     */
    private function scalarize($x): string
    {
        if (is_array($x)) {
            foreach (['example', 'text', 'value', 'key'] as $k) {
                if (isset($x[$k]) && is_scalar($x[$k])) return (string) $x[$k];
            }
            $flat = array_filter($x, 'is_scalar');
            return $flat ? implode(', ', array_map('strval', $flat)) : '';
        }
        return is_scalar($x) ? (string) $x : '';
    }

    private function buildSendHeader(WaTemplate $t, array $vars): ?array
    {
        $type = strtoupper((string) ($t->attachment_type ?: 'TEXT'));

        // `attachment_type` names the header's MEDIA kind, not "is there a
        // header". A plain TEXT header is stored as 'none' — that is what
        // TemplateImporter defaults to and what every existing row holds.
        // Testing `$type === 'TEXT'` therefore NEVER matched real data: a text
        // header fell through to the media branch below, found no media, and
        // returned null, so the header component was dropped from every send.
        // For a header containing {{1}} that is an automatic Meta #132000
        // ("number of localizable_params (0) does not match ... (1)").
        //
        // Only the real media kinds take the media path; everything else
        // ('none', 'text', '', null) is a text header.
        $isMedia = in_array($type, ['IMAGE', 'VIDEO', 'DOCUMENT'], true);

        if (!$isMedia && $type !== 'LOCATION') {
            // The header must carry EXACTLY as many params as the approved
            // template declares — same rule the body follows below.
            //
            // This used to be `if (empty($vars['header'])) return null;`, which
            // dropped the whole header component whenever the value resolved
            // blank. For a header containing {{1}} that is an automatic Meta
            // rejection: "header: number of localizable_params (0) does not
            // match the expected number of params (1)" (#132000). It hit every
            // recipient of a template whose header slot was imported from Meta
            // (TemplateImporter defaults the key to the slot NUMBER, e.g. '1')
            // and never mapped to a real contact attribute in the composer —
            // $pull('1') finds nothing and returns ''.
            //
            // `empty()` was doubly wrong: it also treats the string "0" as
            // empty, so a legitimate header value of "0" was dropped too.
            $headerPlaceholders = $this->countPlaceholders((string) $t->header);
            $value              = trim($this->scalarize($vars['header'] ?? ''));

            // No placeholder in the header → Meta wants NO header component,
            // even if a caller passed a stray value. Sending one here is its
            // own #132000 (expected 0, got 1).
            if ($headerPlaceholders < 1) {
                return null;
            }

            // Meta also rejects an empty text param (#131008), so a blank slot
            // is back-filled with a NON-EMPTY fallback, in the same order the
            // body uses: the template's own example → the placeholder's name
            // when it's a word → a neutral "-" so delivery still succeeds.
            if ($value === '') {
                // A variable_map entry can be a real sample ("Acme Corp") OR —
                // for a template imported from Meta — the positional MAPPING
                // ['num'=>1,'key'=>'1'], which scalarizes to the bare slot
                // number. Sending that would put "Hello 1" in front of the
                // customer, so anything purely numeric is rejected as an
                // example, exactly as it is for the placeholder name.
                $example = trim($this->scalarize(($t->variable_map['header'] ?? [])[0] ?? ''));
                $name    = (string) ($this->extractPlaceholderNames((string) $t->header)[0] ?? '');
                $value   = ($example !== '' && !is_numeric($example))
                    ? $example
                    : (($name !== '' && !is_numeric($name)) ? $name : '-');
            }

            // A TEXT header takes exactly one param in practice (Meta allows a
            // single variable in a header), but pad defensively so the count
            // always matches what the template declares.
            // NAMED templates need the header param tagged with its placeholder
            // name too — same reason as the body (see buildSend); otherwise a
            // NAMED template with a header variable ships the raw {{name}}.
            $isNamed    = strtoupper((string) ($t->parameter_format ?: 'POSITIONAL')) === 'NAMED';
            $headerName = $isNamed ? (string) ($this->extractPlaceholderNames((string) $t->header)[0] ?? '') : '';
            $parameters = [];
            for ($i = 0; $i < $headerPlaceholders; $i++) {
                $p = ['type' => 'text', 'text' => $value];
                if ($isNamed && $headerName !== '' && !is_numeric($headerName)) {
                    $p['parameter_name'] = $headerName;
                }
                $parameters[] = $p;
            }

            return ['type' => 'header', 'parameters' => $parameters];
        }

        // LOCATION header — Meta needs latitude + longitude + name +
        // address inside a `location` object, NOT the `link|id` shape
        // used for media. Without this branch a LOCATION-header
        // template would either silently strip the header or fail
        // with #132012.
        if ($type === 'LOCATION') {
            $loc = $vars['header_location'] ?? null;
            if (!is_array($loc) || empty($loc['latitude']) || empty($loc['longitude'])) {
                return null;
            }
            return [
                'type'       => 'header',
                'parameters' => [[
                    'type'     => 'location',
                    'location' => [
                        'latitude'  => (string) $loc['latitude'],
                        'longitude' => (string) $loc['longitude'],
                        'name'      => (string) ($loc['name']    ?? ''),
                        'address'   => (string) ($loc['address'] ?? ''),
                    ],
                ]],
            ];
        }

        // Media header (IMAGE / VIDEO / DOCUMENT). If the caller supplied neither
        // a media id nor a URL, fall back to the template's OWN stored sample
        // media (attachment_file) so a plain {to, body} send still carries the
        // required header — the single-send API otherwise omitted the header and
        // Meta rejected the send. Mirrors the campaign/broadcast fallback.
        $mediaId  = $this->scalarize($vars['header_media_id'] ?? '');
        $mediaUrl = $this->scalarize($vars['header_media_url'] ?? '');
        if ($mediaId === '' && $mediaUrl === '' && !empty($t->attachment_file)) {
            $mediaUrl = (string) media_url($t->attachment_file);
        }
        if ($mediaId === '' && $mediaUrl === '') return null;

        $mediaKey = strtolower($type); // image / video / document
        $media = $mediaId !== '' ? ['id' => $mediaId] : ['link' => $mediaUrl];

        // A DOCUMENT header sent WITHOUT a filename renders as "Untitled" in
        // WhatsApp (and hides the .pdf type). Always attach a display filename —
        // from the caller (the send API forwards the uploaded file's own name),
        // else a meaningful URL basename, else the template name + extension.
        if ($mediaKey === 'document') {
            $media['filename'] = $this->documentFilename($vars, $mediaUrl, $t);
        }

        return [
            'type'       => 'header',
            'parameters' => [['type' => $mediaKey, $mediaKey => $media]],
        ];
    }

    /**
     * Display filename for a DOCUMENT header so WhatsApp shows a real name, not
     * "Untitled". Priority: explicit header_filename → a meaningful URL basename
     * (never our randomised storage name) → the template name + the source
     * extension. Sanitised to a safe display string with a guaranteed extension.
     */
    private function documentFilename(array $vars, string $mediaUrl, WaTemplate $t): string
    {
        $name = trim($this->scalarize($vars['header_filename'] ?? ''));

        if ($name === '') {
            $base = $mediaUrl !== '' ? basename((string) parse_url($mediaUrl, PHP_URL_PATH)) : '';
            // Skip our own upload name (time_random.ext) — it reads as garbage.
            if ($base !== '' && !preg_match('/^\d{9,}_[A-Za-z0-9]{20,}\./', $base)) {
                $name = $base;
            }
        }

        if ($name === '') {
            $src = $mediaUrl !== '' ? (string) parse_url($mediaUrl, PHP_URL_PATH) : (string) ($t->attachment_file ?? '');
            $ext = strtolower((string) pathinfo((string) $src, PATHINFO_EXTENSION)) ?: 'pdf';
            $name = (trim((string) $t->template_name) ?: 'document') . '.' . $ext;
        }

        // Sanitise to a safe display name + guarantee an extension.
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^\w .()\-]+/u', '', $name)));
        if ($name === '') $name = 'document.pdf';
        if (!str_contains($name, '.')) $name .= '.pdf';

        return mb_substr($name, 0, 240);
    }

    /**
     * Build the SEND-time button component. Meta uses different
     * parameter-object shapes per sub_type:
     *
     *   quick_reply → { type: "payload",     payload:     "..." }
     *   url         → { type: "text",        text:        "..." }
     *   copy_code   → { type: "coupon_code", coupon_code: "..." }
     *   voice_call  → no parameters
     *
     * `index` is sent as a 0-based INTEGER — the canonical form in
     * Meta's current Cloud API examples (the API also tolerates the
     * quoted-string "0", but integer is the documented primary).
     */
    private function buildSendButton(array $btn): array
    {
        $sub   = strtolower((string) ($btn['sub_type'] ?? 'url'));
        $value = $this->scalarize($btn['value'] ?? '');

        $params = match ($sub) {
            'quick_reply' => [['type' => 'payload',     'payload'     => $value]],
            'copy_code'   => [['type' => 'coupon_code', 'coupon_code' => $value]],
            'voice_call'  => [],
            default       => [['type' => 'text',        'text'        => $value]], // url + fallback
        };

        return [
            'type'       => 'button',
            'sub_type'   => $sub,
            // Meta's 2025-2026 Cloud API send spec shows `index` as an
            // integer in the canonical example. Older docs accepted a
            // string ("0", "1"), but the latest reference is integer
            // only — send it as int to stay forward-compatible.
            'index'      => (int) ($btn['index'] ?? 0),
            'parameters' => $params,
        ];
    }

    private function buildSendCard(int $cardIdx, array $cardVars, array $tplCardButtons = []): array
    {
        $comps = [];

        if (!empty($cardVars['header_media_id']) || !empty($cardVars['header_media_url'])) {
            $media = isset($cardVars['header_media_id'])
                ? ['id'   => $this->scalarize($cardVars['header_media_id'])]
                : ['link' => $this->scalarize($cardVars['header_media_url'])];
            $type = strtolower((string) ($cardVars['header_format'] ?? 'image'));
            $comps[] = [
                'type'       => 'header',
                'parameters' => [['type' => $type, $type => $media]],
            ];
        }

        if (!empty($cardVars['body'])) {
            $comps[] = [
                'type'       => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], (array) $cardVars['body']),
            ];
        }

        // Same rule as the top-level buttons: a send-time button parameter is
        // ONLY valid when the approved button actually expects one (quick_reply /
        // copy_code always; URL only when its template URL has a {{N}} suffix).
        // Emitting a param for a static URL/phone card button → Meta #132000.
        foreach (($cardVars['buttons'] ?? []) as $btn) {
            if (!$this->shouldEmitButtonParam($btn, $tplCardButtons)) continue;
            $comps[] = $this->buildSendButton($btn);
        }

        return ['card_index' => $cardIdx, 'components' => $comps];
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Meta accepts template names ^[a-z0-9_]{1,512}$ — slugify whatever
     * the user typed so we don't reject silently. Mirrors Meta's own
     * normalization shown in Manager.
     */
    public function normalizeName(string $name): string
    {
        $n = mb_strtolower(trim($name));
        $n = preg_replace('/[^a-z0-9]+/u', '_', $n);
        $n = trim($n, '_');
        return mb_substr($n, 0, 512) ?: 'untitled_template';
    }

    /** Count distinct `{{N}}` placeholders in a string. */
    public function countPlaceholders(string $s): int
    {
        preg_match_all('/\{\{\s*[\w_]+\s*\}\}/', $s, $m);
        return count($m[0]);
    }

    /**
     * Maps the local industry-category to Meta's three-bucket category
     * when the user didn't explicitly pick one. Conservative — defaults
     * to UTILITY (Meta's strictest) rather than MARKETING to avoid
     * accidental promotional misclassification.
     */
    private function guessMetaCategory(WaTemplate $t): string
    {
        if ($t->template_type === 'auth') return 'AUTHENTICATION';
        return match ($t->category) {
            'ecommerce', 'festival', 'travel' => 'MARKETING',
            default                            => 'UTILITY',
        };
    }

    private function inferCardMediaFormat(array $card): string
    {
        $path = (string) ($card['image'] ?? '');
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'mp4', 'mov', '3gp' => 'VIDEO',
            default              => 'IMAGE',
        };
    }
}
