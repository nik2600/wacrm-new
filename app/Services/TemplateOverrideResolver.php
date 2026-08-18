<?php

namespace App\Services;

use App\Models\Attribute;
use Illuminate\Support\Facades\Log;

/**
 * Send-time template overrides.
 *
 * A template's `variable_map` decides which contact attribute fills each
 * {{N}} slot — a property of the TEMPLATE. Changing what one campaign
 * puts in {{1}} used to mean editing the template, which then changed
 * every other send using it. This resolver applies a per-send override
 * stored on the sending row (`template_overrides`) instead.
 *
 * Contract — overlay semantics, LITERAL WINS PER SLOT:
 *
 *   - A slot the operator filled in wins for that slot, for every
 *     recipient of that send.
 *   - A slot left blank falls through to the template's variable_map
 *     result, unchanged.
 *   - No override at all → returns $vars byte-identical. This is the
 *     no-regression guarantee for every pre-existing row.
 *
 * Each override value is a TOKEN STRING: a literal ("SAVE20"), an
 * attribute token ("{{first_name}}"), or a mix ("Hi {{first_name}},
 * your code is SAVE20"). Tokens resolve against, in order:
 *
 *   1. the contact row  (name / phone / email / company / …)
 *   2. the contact's custom_attributes JSON
 *   3. the workspace's static Attribute rows
 *   4. system tokens    ({{today}}, {{company_name}})
 *
 * An unresolvable token renders EMPTY, not as its literal braces —
 * shipping "{{first_name}}" to a customer is worse than shipping "".
 * TemplatePayloadBuilder then back-fills blanks with the template's own
 * example so Meta never sees an empty text parameter (#131008).
 *
 * @see \App\Http\Controllers\BroadcastsController::varsForRecipient()
 * @see \App\Services\SendAttributes  — the catalog offered in the UI
 */
class TemplateOverrideResolver
{
    /**
     * The ONE placeholder pattern for the whole send stack.
     *
     * Deliberately accepts anything that isn't a brace — real templates
     * carry `{{Phone Number}}` and `{{Last Name}}`, with spaces and
     * capitals. The old `[\w_]+` pattern silently skipped those, so they
     * were never counted as variables, never offered for editing, and
     * SHIPPED LITERALLY to the customer as the text "{{Phone Number}}".
     */
    public const TOKEN_RE = '/\{\{\s*([^{}]+?)\s*\}\}/u';

    /**
     * Attribute-name aliases. Operators name a placeholder however reads
     * best in the message ("Phone Number", "Mobile No"); the catalog has
     * one canonical key. Both must land on the same value or the feature
     * looks broken to the person using it.
     */
    private const ALIASES = [
        'phone_number'   => 'phone',
        'phone_no'       => 'phone',
        'mobile_number'  => 'mobile',
        'mobile_no'      => 'mobile',
        'whatsapp_number'=> 'phone',
        'number'         => 'phone',
        'contact_number' => 'phone',
        'full_name'      => 'name',
        'customer_name'  => 'name',
        'contact_name'   => 'name',
        'first'          => 'first_name',
        'last'           => 'last_name',
        'surname'        => 'last_name',
        'mail'           => 'email',
        'email_address'  => 'email',
        'e_mail'         => 'email',
        'company_name'   => 'company_name',
        'city'           => 'city',
        // The sender's own name. Operators write this a dozen ways; they must
        // all land on the one canonical key or the token looks broken.
        'sender_name'    => 'business_name',
        'sender'         => 'business_name',
        'business'       => 'business_name',
        'brand'          => 'business_name',
        'brand_name'     => 'business_name',
        'whatsapp_name'  => 'business_name',
        'store_name'     => 'business_name',
        'shop_name'      => 'business_name',
    ];

    /** Cache of workspace static attributes, keyed by workspace id. */
    private array $wsAttrCache = [];

    /**
     * Flatten ANY variable_map shape to `{slot => attribute_key}`.
     *
     * This existed in three places, written three different ways, and each
     * one got it subtly wrong:
     *   - the campaign body builder rebuilt it inline per send
     *   - the broadcast button loop indexed `variable_map['body'][$slot]`
     *     directly — but that is a LIST of {num,key} objects, so it read the
     *     wrong entry (off by one) and returned an array, throwing
     *     "Array to string conversion" and killing button URL resolution
     *   - the Meta payload builder walks its own copy again
     *
     * Both stored shapes are accepted:
     *   nested (templates) ['header'=>[{num,key}], 'body'=>[{num,key}]]
     *   flat   (composer)  {"1":"first_name","2":"email"}
     *
     * `num` is trusted when present; otherwise position+1 is used, which is
     * what every writer of this column actually means.
     *
     * @param  array|string|null $map
     * @param  string[]          $sections Which sections to fold in.
     */
    public static function flattenMap($map, array $sections = ['header', 'body']): array
    {
        if (is_string($map)) {
            $map = json_decode($map, true);
        }
        if (! is_array($map) || ! $map) {
            return [];
        }

        // Flat composer shape — no section keys present.
        $hasSection = false;
        foreach ($sections as $s) {
            if (isset($map[$s])) { $hasSection = true; break; }
        }
        if (! $hasSection) {
            $flat = [];
            foreach ($map as $slot => $key) {
                if (is_string($key) || is_numeric($key)) {
                    $flat[(string) $slot] = (string) $key;
                }
            }
            return $flat;
        }

        $flat = [];
        foreach ($sections as $section) {
            foreach ((array) ($map[$section] ?? []) as $i => $entry) {
                if (is_array($entry)) {
                    $key = (string) ($entry['key'] ?? '');
                    $num = isset($entry['num']) ? (int) $entry['num'] : ((int) $i + 1);
                } else {
                    $key = (string) $entry;
                    $num = (int) $i + 1;
                }
                if ($key !== '') $flat[(string) $num] = $key;
            }
        }
        return $flat;
    }

    /**
     * Canonical lookup key for a placeholder name.
     * "Phone Number" → "phone_number" → alias → "phone".
     */
    public static function normalizeKey(string $raw): string
    {
        $k = strtolower(trim($raw));
        $k = preg_replace('/[\s\-.]+/u', '_', $k);
        $k = preg_replace('/[^a-z0-9_]/u', '', (string) $k);
        $k = trim((string) $k, '_');
        return self::ALIASES[$k] ?? $k;
    }

    /**
     * Overlay a send's overrides onto the vars the template's
     * variable_map already produced.
     *
     * @param  array      $vars       Output of varsForRecipient()
     * @param  array|null $overrides  The row's template_overrides
     * @param  array      $contact    Recipient row (+ custom_attributes)
     * @param  int        $workspaceId
     * @return array                  Same shape as $vars
     */
    public function apply(array $vars, ?array $overrides, array $contact, int $workspaceId): array
    {
        if (empty($overrides) || ! is_array($overrides)) {
            return $vars;                       // no override → untouched
        }

        // ---- HEADER ---------------------------------------------------
        $header = $overrides['header'] ?? null;
        if (is_array($header)) {
            $mode = strtolower((string) ($header['mode'] ?? 'text'));

            if ($mode === 'media') {
                // Operator swapped the header image/video/document for
                // this send only. A URL beats an id — the id path is for
                // media already uploaded to Meta (TemplateSender resolves
                // and caches that itself).
                $url = trim((string) ($header['media_url'] ?? ''));
                if ($url !== '') {
                    $vars['header_media_url'] = $url;
                    unset($vars['header_media_id']);   // stale id would win otherwise
                }
            } else {
                $text = (string) ($header['text'] ?? '');
                if (trim($text) !== '') {
                    $vars['header'] = $this->render($text, $contact, $workspaceId);
                }
            }
        }

        // ---- BODY (positional) ----------------------------------------
        $vars['body'] = $this->overlaySlots(
            (array) ($vars['body'] ?? []),
            $overrides['body'] ?? null,
            $contact,
            $workspaceId
        );

        // ---- FOOTER (positional) --------------------------------------
        // Meta templates don't take footer parameters today, but Unofficial
        // sends do substitute footer text, so carry it through rather than
        // silently dropping what the operator typed.
        if (! empty($overrides['footer'])) {
            $vars['footer'] = $this->overlaySlots(
                (array) ($vars['footer'] ?? []),
                $overrides['footer'],
                $contact,
                $workspaceId
            );
        }

        // ---- BUTTONS ---------------------------------------------------
        // Matched by button INDEX, not array position — the override list
        // is sparse (only the buttons the operator actually edited) while
        // $vars['buttons'] carries every parameterised button.
        $btnOverrides = $overrides['buttons'] ?? null;
        if (is_array($btnOverrides) && ! empty($btnOverrides)) {
            $byIndex = [];
            foreach ($btnOverrides as $bo) {
                if (! is_array($bo) || ! isset($bo['index'])) continue;
                $val = (string) ($bo['value'] ?? '');
                if (trim($val) === '') continue;               // blank = keep template value
                $byIndex[(int) $bo['index']] = $val;
            }

            if ($byIndex) {
                $buttons = (array) ($vars['buttons'] ?? []);
                foreach ($buttons as $i => $btn) {
                    if (! is_array($btn)) continue;
                    $idx = (int) ($btn['index'] ?? $i);
                    if (! array_key_exists($idx, $byIndex)) continue;
                    $buttons[$i]['value'] = $this->render($byIndex[$idx], $contact, $workspaceId);
                    unset($byIndex[$idx]);
                }
                // Buttons the operator edited that the template didn't
                // expose as parameterised. Meta rejects a parameter for a
                // static button (#132000), so we drop them — but loudly,
                // because it means the UI offered a slot the template
                // can't take and that's a bug worth seeing.
                if ($byIndex) {
                    Log::warning('[TPL-OVERRIDE] button overrides ignored — not parameterised in template', [
                        'workspace_id'  => $workspaceId,
                        'button_indexes' => array_keys($byIndex),
                    ]);
                }
                $vars['buttons'] = array_values($buttons);
            }
        }

        return $vars;
    }

    /**
     * Positional overlay: override slot N replaces base slot N when the
     * operator typed something there; blank slots keep the base value.
     * The result is as long as the longer of the two — a template can
     * legitimately have more slots than the operator chose to edit.
     */
    private function overlaySlots(array $base, $overrides, array $contact, int $workspaceId): array
    {
        if (! is_array($overrides) || empty($overrides)) {
            return $base;
        }
        $overrides = array_values($overrides);
        $count     = max(count($base), count($overrides));
        $out       = [];

        for ($i = 0; $i < $count; $i++) {
            $raw = $overrides[$i] ?? null;
            $out[$i] = (is_string($raw) && trim($raw) !== '')
                ? $this->render($raw, $contact, $workspaceId)
                : (string) ($base[$i] ?? '');
        }

        return $out;
    }

    /**
     * Resolve {{token}}s in an operator-typed string against this
     * recipient. Plain literals pass through untouched.
     *
     * Deliberately NOT reusing AttributeResolver::resolve() — that one
     * resolves positional {{1}} through a variable_map and leaves
     * unknown tokens as literal braces (correct for its composer, where
     * the operator should notice). Here the string IS the final message
     * text, so an unknown token must render empty.
     */
    public function render(string $token, array $contact, int $workspaceId): string
    {
        if ($token === '' || ! str_contains($token, '{{')) {
            return $token;                       // fast path — pure literal
        }

        return (string) preg_replace_callback(
            self::TOKEN_RE,
            fn ($m) => $this->lookup((string) $m[1], $contact, $workspaceId),
            $token
        );
    }

    /**
     * One token → one value. Mirrors the $pull() semantics in
     * varsForRecipient so the same key means the same thing whether it
     * came from a variable_map or from an operator-typed override.
     */
    public function lookup(string $key, array $contact, int $workspaceId): string
    {
        // Try the name exactly as written first (a custom attribute may
        // legitimately be keyed "Phone Number"), then the canonical form.
        // Without the second pass "{{Phone Number}}" resolves to nothing
        // and the customer receives the raw braces.
        $candidates = array_unique([$key, self::normalizeKey($key)]);

        foreach ($candidates as $k) {
            if ($k === '') continue;

            // 1) contact column
            if (isset($contact[$k]) && $contact[$k] !== null && $contact[$k] !== '') {
                return $this->scalarize($contact[$k]);
            }

            // 2) contact custom attribute — match case-insensitively so a
            // key stored as "City" still answers "{{city}}".
            $custom = $contact['custom_attributes'] ?? [];
            if (is_array($custom)) {
                foreach ($custom as $ck => $cv) {
                    if ($cv === '' || $cv === null) continue;
                    if (self::normalizeKey((string) $ck) === self::normalizeKey($k)) {
                        return $this->scalarize($cv);
                    }
                }
            }

            // 3) workspace static attribute
            foreach ($this->workspaceAttributes($workspaceId) as $ak => $av) {
                if ($av !== '' && self::normalizeKey((string) $ak) === self::normalizeKey($k)) {
                    return $av;
                }
            }

            // 4) system tokens
            $sys = match ($k) {
                'today' => now()->toDateString(),

                // The name the CUSTOMER sees the message coming from. Meta
                // hands us the WhatsApp number's verified business name at
                // connect time and DevicesController stores it on the config
                // (display_label + meta_json.verified_name), so a header like
                // "Welcome to {{1}}" can be mapped straight to this.
                'business_name' => $this->businessName($workspaceId),

                // Previously hard-wired to brand_name() — the PLATFORM brand.
                // On a white-label install that put "WaDesk" into the client's
                // own customer messages, which is both wrong and a branding
                // leak. Prefer the sending number's business name, then the
                // workspace, and only fall back to the platform brand when we
                // genuinely know nothing else. (A workspace Attribute named
                // company_name still wins — step 3 runs before this.)
                'company_name' => $this->businessName($workspaceId)
                    ?: (string) (function_exists('brand_name') ? brand_name() : config('app.name')),

                default => null,
            };
            if ($sys !== null && $sys !== '') return $sys;
        }

        return '';
    }

    /** Cache of resolved business names, keyed by workspace id. */
    private array $businessNameCache = [];

    /**
     * The sender's own business name — what the customer sees the message
     * coming from.
     *
     * Meta returns `verified_name` for a WhatsApp Business number and
     * DevicesController stores it in two places at connect time, so both are
     * checked. A CONNECTED number wins over a merely-configured one, since
     * that is the number actually sending.
     *
     * Workspace-level, not per-send: the resolver is handed a workspace id,
     * not the sending number. For the common single-number workspace this is
     * exact. A workspace running several WABA numbers with DIFFERENT verified
     * names gets the connected one — mapping the slot to a workspace Attribute
     * (which takes precedence) is the way to pin a specific value there.
     *
     * Falls back to the workspace name so the token still resolves for
     * Unofficial-API and Twilio senders, which have no Meta verified name.
     */
    private function businessName(int $workspaceId): string
    {
        if ($workspaceId <= 0) return '';
        if (isset($this->businessNameCache[$workspaceId])) {
            return $this->businessNameCache[$workspaceId];
        }

        $name = '';

        try {
            $cfgs = \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $workspaceId)
                ->where('provider', 'waba')
                ->get(['id', 'status', 'display_label', 'meta_json']);

            // Connected first — that is the number actually sending.
            $ordered = $cfgs->sortByDesc(fn ($c) => (string) $c->status === 'connected' ? 1 : 0);

            foreach ($ordered as $cfg) {
                $label = trim((string) $cfg->display_label);
                if ($label !== '') { $name = $label; break; }

                $meta = is_array($cfg->meta_json)
                    ? $cfg->meta_json
                    : (json_decode((string) $cfg->meta_json, true) ?: []);
                $verified = trim((string) ($meta['verified_name'] ?? ''));
                if ($verified !== '') { $name = $verified; break; }
            }
        } catch (\Throwable $e) {
            // Never let a token lookup break a send.
        }

        if ($name === '') {
            try {
                $name = trim((string) (\App\Models\Workspace::whereKey($workspaceId)->value('name') ?? ''));
            } catch (\Throwable $e) {
                $name = '';
            }
        }

        return $this->businessNameCache[$workspaceId] = $name;
    }

    /** Workspace static attributes, one query per workspace per request. */
    private function workspaceAttributes(int $workspaceId): array
    {
        if (isset($this->wsAttrCache[$workspaceId])) {
            return $this->wsAttrCache[$workspaceId];
        }

        return $this->wsAttrCache[$workspaceId] = Attribute::query()
            ->forWorkspace($workspaceId)
            ->get(['attribute_key', 'attribute_value'])
            ->mapWithKeys(fn ($a) => [$a->attribute_key => (string) $a->attribute_value])
            ->all();
    }

    /**
     * A custom-attribute value can be a nested array (JSON). Casting that
     * to string throws "Array to string conversion", which is exactly how
     * a whole campaign died before — flatten instead.
     */
    private function scalarize($v): string
    {
        if (is_array($v)) {
            return implode(', ', array_map('strval', array_filter($v, 'is_scalar')));
        }
        return is_scalar($v) ? (string) $v : '';
    }

    /**
     * Normalise + validate what the send form posted, before it is
     * persisted. Returns null when nothing usable was supplied so the
     * column stays NULL and the row keeps template-driven behaviour.
     *
     * Anything unrecognised is dropped rather than stored — the column
     * is read back on every send, so it must never carry shapes the
     * resolver can't walk.
     */
    /**
     * Flatten the resolver's section-keyed result into the positional
     * `{1: 'Liam', 2: 'WaDesk'}` map that message meta carries.
     *
     * varsForRecipient() returns `['body' => ['Liam', 'WaDesk']]` because the
     * Meta payload builder wants components grouped by section. The 1:1 send
     * paths (team inbox, chat) instead put `meta.template_vars` on the wire as
     * a flat positional map, which is what the dispatchers and Twilio's
     * ContentSid variables read. Converting in one place keeps the two shapes
     * from being re-derived — and mis-derived — per call site.
     *
     * Non-numeric top-level keys (header_media_url, header) pass through
     * untouched; they are not slots.
     */
    public static function positional(array $vars): array
    {
        $out = [];
        foreach ($vars as $key => $value) {
            if (in_array($key, ['header', 'body', 'footer'], true) && is_array($value)) {
                // Section lists are 0-indexed; WhatsApp slots start at {{1}}.
                foreach (array_values($value) as $i => $v) {
                    if ($key === 'body') $out[(string) ($i + 1)] = (string) $v;
                }
                continue;
            }
            if (!is_array($value)) $out[(string) $key] = (string) $value;
        }
        return $out;
    }

    public static function sanitize($input): ?array
    {
        if (is_string($input)) {
            $input = json_decode($input, true);
        }
        if (! is_array($input)) {
            return null;
        }

        $out = [];

        $header = $input['header'] ?? null;
        if (is_array($header)) {
            $mode = strtolower((string) ($header['mode'] ?? 'text')) === 'media' ? 'media' : 'text';
            if ($mode === 'media') {
                $url = trim((string) ($header['media_url'] ?? ''));
                // Accept any valid http(s) URL. HTTPS is a WABA/Meta requirement
                // (Meta fetches header media server-side over HTTPS) and is
                // enforced at the WABA send layer — but demanding it HERE silently
                // DROPPED the override on HTTP installs (e.g. a LAN box on
                // http://192.168.x.x), so a freshly-uploaded image vanished and
                // the send fell back to the template's default. The Unofficial
                // (Baileys) engine fetches HTTP media fine; let it save.
                if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url)) {
                    $out['header'] = ['mode' => 'media', 'media_url' => $url];
                }
            } else {
                $text = trim((string) ($header['text'] ?? ''));
                if ($text !== '') {
                    $out['header'] = ['mode' => 'text', 'text' => mb_substr($text, 0, 1024)];
                }
            }
        }

        foreach (['body', 'footer'] as $section) {
            $slots = $input[$section] ?? null;
            if (! is_array($slots)) continue;
            $clean = array_map(
                fn ($v) => is_scalar($v) ? mb_substr(trim((string) $v), 0, 1024) : '',
                array_values($slots)
            );
            // All-blank means "operator opened the panel and typed nothing".
            if (implode('', $clean) !== '') {
                $out[$section] = $clean;
            }
        }

        $buttons = $input['buttons'] ?? null;
        if (is_array($buttons)) {
            $clean = [];
            foreach ($buttons as $b) {
                if (! is_array($b) || ! isset($b['index'])) continue;
                $val = trim((string) ($b['value'] ?? ''));
                if ($val === '') continue;
                $sub = strtolower((string) ($b['sub_type'] ?? 'url'));
                if (! in_array($sub, ['url', 'quick_reply', 'copy_code'], true)) {
                    $sub = 'url';
                }
                $clean[] = [
                    'index'    => (int) $b['index'],
                    'sub_type' => $sub,
                    'value'    => mb_substr($val, 0, 2048),
                ];
            }
            if ($clean) {
                $out['buttons'] = $clean;
            }
        }

        return $out ?: null;
    }
}
