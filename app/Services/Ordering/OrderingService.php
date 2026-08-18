<?php

namespace App\Services\Ordering;

use App\Models\WaOrder;
use App\Models\WaOrderItem;
use App\Models\WaPendingOrder;
use App\Models\WaProduct;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Natural-language ordering brain (Jessica customization, P3). Ties the AI
 * extractor (P0), the product matcher + anti-sellout inventory (P2) together:
 *
 *   parseAndHold()  customer free-text → AI items → match → HOLD stock → cart
 *   confirm()       held cart → real WaOrder + commit holds
 *   cancel()        release holds + drop the cart
 *
 * Stock is held the moment we understand the order so two customers racing for
 * the last unit can't both "confirm" it. Holds auto-release on timeout (P2 sweep).
 */
class OrderingService
{
    public function __construct(
        private AiAgentService $ai,
        private ProductMatcher $matcher,
        private InventoryService $inventory,
        private OrderMediaResolver $media,
    ) {}

    /**
     * Parse a customer message into a held cart.
     *
     * @return array{ref:string, has_items:bool, items:array, unavailable:array,
     *               total_minor:int, currency:?string, summary:string}
     */
    public function parseAndHold(int $wsId, string $customerPhone, string $text, ?string $groupCode = null, string $model = 'gpt-4o-mini'): array
    {
        $digits = preg_replace('/\D+/', '', $customerPhone);
        $ref    = WaPendingOrder::refFor($wsId, $digits);

        // A new order replaces any prior in-flight cart — free its holds first.
        $this->inventory->releaseByRef($wsId, $ref);

        // Jessica #3 — voice + picture ordering. The flow only hands us the
        // customer's typed text; when they instead sent a VOICE note or a PHOTO
        // the inbound pipeline already saved it on disk. Pull it in so the AI can
        // hear / see the order. Text orders skip all of this (effectiveText=$text,
        // no image) so the proven typed path is byte-for-byte unchanged.
        $effectiveText = $text;
        $image         = null;
        $isPlaceholder = $this->looksLikePlaceholder($text);
        $media         = $this->media->resolve($wsId, $customerPhone, $isPlaceholder);

        // Keep the ORIGINAL voice/photo with the order so the merchant can replay
        // it later from the order view (set on the pending cart below).
        $orderMediaPath = $media['path'] ?? null;
        $orderMediaType = null;            // 'voice' | 'image'
        $orderMediaText = null;            // transcript (voice only)

        if ($media['kind'] === 'voice' && !empty($media['voice_text'])) {
            // Heard: the transcript IS the order. Replace a placeholder ("[voice
            // note]" / bare message id); otherwise append to a real caption.
            $effectiveText = $isPlaceholder
                ? $media['voice_text']
                : trim($text . "\n" . $media['voice_text']);
            $orderMediaType = 'voice';
            $orderMediaText = $media['voice_text'];
            Log::info('[ORDER-FLOW] 2 · voice → order text', ['chars' => mb_strlen($effectiveText)]);
        } elseif ($media['kind'] === 'image' && !empty($media['image'])) {
            // Seen: attach the photo to the vision model. Keep any caption as text.
            $image = $media['image'];
            $orderMediaType = 'image';
            Log::info('[ORDER-FLOW] 2 · image attached to vision');
        }

        $parsed      = $this->aiParseItems($wsId, $effectiveText, $model, $image);
        $lineItems   = [];
        $unavailable = [];
        $totalMinor  = 0;
        $currency    = null;

        foreach ($parsed as $it) {
            $name = trim((string) ($it['name'] ?? ''));
            $qty  = max(1, (int) ($it['qty'] ?? 1));
            if ($name === '') continue;

            $m = $this->matcher->resolve($wsId, $name);
            /** @var ?WaProduct $p */
            $p = $m['product'];
            if (!$p) {
                $unavailable[] = ['name' => $name, 'qty' => $qty, 'reason' => 'not_found'];
                continue;
            }

            $res = $this->inventory->hold($p, $qty, $ref);
            if (!$res) {
                $unavailable[] = ['name' => $p->name, 'qty' => $qty, 'reason' => 'out_of_stock', 'available' => $p->availableQty()];
                continue;
            }

            $currency = $currency ?: $p->currency_code;
            $totalMinor += (int) $p->price_minor * $qty;
            $lineItems[] = [
                'product_id'  => $p->id,
                'retailer_id' => $p->sku,
                'name'        => $p->name,
                'image_url'   => $p->image_url,
                'qty'         => $qty,
                'price_minor' => (int) $p->price_minor,
                'currency'    => $p->currency_code,
            ];
        }

        Log::info('[FLOWTRACE] order parseAndHold', [
            'workspace_id' => $wsId,
            'parsed_items' => count($parsed),
            'matched'      => count($lineItems),
            'unavailable'  => array_map(fn ($u) => ($u['name'] ?? '') . ' (' . ($u['reason'] ?? '') . ')', $unavailable),
            'total_minor'  => $totalMinor,
        ]);

        // Detect the customer's language ONCE (from what they wrote/said) so the
        // order confirmation AND the group @mention can be sent in their language
        // (Jessica #1). For a voice order this is the transcript (effectiveText).
        $custLang = $this->detectCustomerLang($effectiveText);

        if (empty($lineItems)) {
            WaPendingOrder::where('workspace_id', $wsId)->where('customer_phone', $digits)->delete();
        } else {
            $po = WaPendingOrder::updateOrCreate(
                ['workspace_id' => $wsId, 'customer_phone' => $digits],
                [
                    'ref'              => $ref,
                    'items_json'       => $lineItems,
                    'unavailable_json' => $unavailable,
                    'total_minor'      => $totalMinor,
                    'currency_code'    => $currency,
                    'group_code'       => $groupCode,
                    'status'           => 'pending',
                    'order_id'         => null,
                    'expires_at'       => now()->addSeconds(InventoryService::DEFAULT_TTL_SECONDS),
                ]
            );
            if ($custLang !== '') $po->forceFill(['customer_lang' => $custLang])->save();
            // Stash the original voice/photo on the cart → copied onto the order
            // at confirm() so the merchant can replay it from the order view.
            if ($orderMediaPath || $orderMediaType) {
                $po->forceFill([
                    'order_media_path'       => $orderMediaPath,
                    'order_media_type'       => $orderMediaType,
                    'order_media_transcript' => $orderMediaText,
                ])->save();
            }
        }

        // Localized "send your delivery details" prompt for the flow's Ask node —
        // shown via {{parse.ask_shipping}} so the question lands in the CUSTOMER's
        // language (the flow engine can't translate; we localize here, like summary).
        // ONE clear step: ask for the delivery address. The reply IS the order
        // confirmation. When we already have the customer's address on file
        // (pre-set in the dashboard, or from a past order) we SHOW it and let
        // them just reply YES — so known customers never re-type it.
        $saved = $this->shippingFor($wsId, $customerPhone);
        if ($saved && !empty($saved['text'])) {
            $askShipping = $this->localizeForCustomer('Please confirm your delivery address:', $effectiveText)
                . "\n\n" . $saved['text'] . "\n\n"
                . $this->localizeForCustomer('Reply YES to ship here, or send a new address.', $effectiveText);
        } else {
            $askShipping = $this->localizeForCustomer('To place your order, reply with your delivery address: your Name, Company (optional), and Full address.', $effectiveText);
        }

        // Customer-facing summary. When NOTHING matched, append a short "what's
        // available" list (name + price + stock) so the customer can re-order a
        // REAL product instead of guessing. Localized off effectiveText so a
        // voice/photo order still replies in the customer's language.
        $summary = $this->localizeForCustomer($this->buildSummary($lineItems, $unavailable, $totalMinor, $currency), $effectiveText);
        if (empty($lineItems)) {
            $avail = $this->availableProductsList($wsId);
            if ($avail !== '') {
                // Localize the WHOLE block (header + every product line) so the menu
                // appears in the CUSTOMER's language, not just the header.
                $summary .= "\n\n" . $this->localizeForCustomer("Here is what we have available right now:\n" . $avail, $effectiveText);
            }
        }

        return [
            'ref'         => $ref,
            'has_items'   => !empty($lineItems),
            // Simple yes/no flag for a flow Condition node — only ask for the
            // address + confirm when the order actually has items.
            'order_ok'    => !empty($lineItems) ? 'yes' : 'no',
            'items'       => $lineItems,
            'unavailable' => $unavailable,
            'total_minor' => $totalMinor,
            'currency'    => $currency,
            // Jessica #2 — the customer's last-used ship-to (for "use saved address?").
            'saved_shipping' => $saved,
            // Reply in the customer's own language (translated from English).
            'summary'     => $summary,
            // Localized address prompt — drop {{parse.ask_shipping}} into the Ask node.
            'ask_shipping' => $askShipping,
        ];
    }

    /**
     * Convert the held cart into a real order + commit the stock.
     *
     * @return array{ok:bool, order_id?:int, total_minor?:int, currency?:?string, error?:string}
     */
    public function confirm(int $wsId, string $customerPhone): array
    {
        $digits  = preg_replace('/\D+/', '', $customerPhone);
        $pending = WaPendingOrder::where('workspace_id', $wsId)
            ->where('customer_phone', $digits)->where('status', 'pending')->first();

        if (!$pending || empty($pending->items_json)) {
            return ['ok' => false, 'error' => 'no_pending_order'];
        }

        try {
            return DB::transaction(function () use ($wsId, $digits, $pending) {
                $order = WaOrder::create([
                    'workspace_id'  => $wsId,
                    'source'        => 'whatsapp_ai',
                    'customer_phone'=> $digits,
                    // Jessica #2 — persist captured ship-to (company rides in meta).
                    'customer_name'    => $pending->ship_name ?: null,
                    'customer_address' => $pending->ship_address ?: null,
                    'items_json'    => $pending->items_json,
                    'total_minor'   => (int) $pending->total_minor,
                    'currency_code' => $pending->currency_code,
                    'status'        => 'pending',   // awaiting merchant action (P6 dashboard)
                    'meta_json'     => array_filter([
                        'group_code'    => $pending->group_code,
                        'customer_lang' => $pending->customer_lang,
                        'ship_company'  => $pending->ship_company,
                        // Jessica #3 — original voice note / photo + transcript,
                        // so the merchant can replay it on the order view.
                        'order_media_path'       => $pending->order_media_path,
                        'order_media_type'       => $pending->order_media_type,
                        'order_media_transcript' => $pending->order_media_transcript,
                    ]),
                ]);

                foreach ($pending->items_json as $li) {
                    WaOrderItem::create([
                        'order_id'      => $order->id,
                        'product_id'    => $li['product_id'] ?? null,
                        'retailer_id'   => $li['retailer_id'] ?? null,
                        'name'          => $li['name'] ?? '',
                        'image_url'     => $li['image_url'] ?? null,
                        'quantity'      => (int) ($li['qty'] ?? 1),
                        'price_minor'   => (int) ($li['price_minor'] ?? 0),
                        'currency_code' => $li['currency'] ?? $pending->currency_code,
                    ]);
                }

                // Settle the holds against this order.
                $this->inventory->commitByRef($wsId, $pending->ref, (int) $order->id);

                $pending->forceFill(['status' => 'confirmed', 'order_id' => $order->id])->save();

                return [
                    'ok'          => true,
                    'order_id'    => (int) $order->id,
                    'total_minor' => (int) $order->total_minor,
                    'currency'    => $order->currency_code,
                    'group_code'    => $pending->group_code,
                    'customer_lang' => $pending->customer_lang,
                    'summary'       => $this->buildConfirmedText((int) $order->id, $pending->items_json ?? [], (int) $order->total_minor, $order->currency_code, $pending->ship_name, $pending->ship_company, $pending->ship_address),
                ];
            });
        } catch (\Throwable $e) {
            Log::error('[ORDERING] confirm failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'confirm_failed'];
        }
    }

    /** Abandon the in-flight cart and free its held stock. */
    public function cancel(int $wsId, string $customerPhone): array
    {
        $digits = preg_replace('/\D+/', '', $customerPhone);
        $ref    = WaPendingOrder::refFor($wsId, $digits);
        $released = $this->inventory->releaseByRef($wsId, $ref);
        WaPendingOrder::where('workspace_id', $wsId)->where('customer_phone', $digits)
            ->where('status', 'pending')->update(['status' => 'cancelled']);
        return ['ok' => true, 'released' => $released];
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /**
     * Ask the LLM to turn the message into {items:[{name,qty}]}.
     *
     * When $image is set (the customer sent a PHOTO of what they want), it is
     * attached so a vision model reads the picture — extracting the product
     * names visible in it. Voice notes arrive here already transcribed into
     * $text by parseAndHold, so they need no special-casing.
     */
    private function aiParseItems(int $wsId, string $text, string $model, ?array $image = null): array
    {
        $ml = strtolower($model);
        $provider = str_starts_with($ml, 'claude') ? 'anthropic'
                  : (str_starts_with($ml, 'gemini') ? 'gemini'
                  : ((str_starts_with($ml, 'mistral') || str_starts_with($ml, 'ministral') || str_starts_with($ml, 'open-mistral') || str_starts_with($ml, 'open-mixtral')) ? 'mistral'
                  : 'openai'));

        $system = 'You convert a customer\'s WhatsApp message into a product order. '
                . 'Respond with ONLY a single valid JSON object of the form '
                . '{"items":[{"name":"<product as the customer wrote it, any language>","qty":<number>}]}. '
                . 'Default qty to 1 when unspecified. If there is no order, return {"items":[]}. '
                . 'No markdown, no commentary — JSON only.';

        if ($image) {
            // Vision: the customer attached a photo. Route through a vision-capable
            // model. Mistral can't take images at all; legacy OpenAI text models
            // (gpt-3.5 / base gpt-4) would 400 on an image — swap those for the
            // vision-capable default. Modern gpt-4o/4.1/5, Claude 3+, and Gemini
            // are all multimodal, so the user's chosen model passes through.
            $blindOpenAi = $provider === 'openai' && (bool) preg_match('/gpt-3\.5|^gpt-4(-0|$)/', $ml);
            if ($provider === 'mistral' || $blindOpenAi) {
                $provider = 'openai';
                $model    = 'gpt-4o-mini';
            }
            $system .= ' The customer also attached a PHOTO. Identify the product(s) '
                     . 'shown in the image (a product, a menu item, or a handwritten/printed '
                     . 'note listing items + quantities) and include them in "items". '
                     . 'Use the photo as the primary source when the text is empty.';
            if (trim($text) === '') {
                $text = 'See the attached photo and extract the product order.';
            }
        }

        $reply = $this->ai->callProvider(
            provider:     $provider,
            model:        $model,
            workspaceId:  $wsId,
            systemPrompt: $system,
            userPrompt:   $text,
            maxTokens:    600,
            temperature:  0.1,
            image:        $image,
            jsonMode:     true,
        );

        Log::info('[FLOWTRACE] order aiParse', [
            'workspace_id' => $wsId,
            'provider'     => $provider,
            'has_image'    => $image ? true : false,
            'text'         => mb_substr($text, 0, 200),
            'reply'        => is_string($reply) ? mb_substr($reply, 0, 400) : '(null — provider failed / no AI key)',
        ]);
        if (!is_string($reply) || $reply === '') return [];
        $json = $this->extractJson($reply);
        $items = is_array($json['items'] ?? null) ? $json['items'] : [];
        // Normalise → [['name'=>, 'qty'=>], ...]
        $out = array_values(array_filter(array_map(function ($i) {
            if (!is_array($i)) return null;
            $name = trim((string) ($i['name'] ?? $i['product'] ?? $i['item'] ?? ''));
            if ($name === '') return null;
            return ['name' => $name, 'qty' => max(1, (int) ($i['qty'] ?? $i['quantity'] ?? 1))];
        }, $items)));
        Log::info('[FLOWTRACE] order aiParse → ' . count($out) . ' item(s): '
            . implode(', ', array_map(fn ($i) => $i['qty'] . '× ' . $i['name'], $out)));
        return $out;
    }

    /** Short order recap for the GROUP post (P5) — "New order #N … Total …". */
    private function buildConfirmedText(int $orderId, array $items, int $totalMinor, ?string $currency, ?string $shipName = null, ?string $shipCompany = null, ?string $shipAddress = null): string
    {
        $lines = ['*New order #' . $orderId . '*'];
        foreach ($items as $li) {
            $lines[] = '• ' . (int) ($li['qty'] ?? 1) . ' × ' . ($li['name'] ?? '');
        }
        $lines[] = '*Total: ' . WaProduct::formatCurrency($totalMinor, $currency) . '*';

        // Jessica #2 — include the captured ship-to so the group post (and the
        // customer confirmation) show WHERE the order goes, not just what was
        // ordered. Whole block is localized to the customer's language upstream.
        $ship = trim(implode(', ', array_filter([
            trim((string) $shipName),
            trim((string) $shipCompany),
            trim((string) $shipAddress),
        ], fn ($v) => $v !== '')));
        if ($ship !== '') {
            $lines[] = '*Ship to:* ' . $ship;
        }

        return implode("\n", $lines);
    }

    /** Tolerant JSON extractor — strips fences / leading prose if any slipped in. */
    private function extractJson(string $s): array
    {
        $s = trim($s);
        $decoded = json_decode($s, true);
        if (is_array($decoded)) return $decoded;
        if (preg_match('/\{.*\}/s', $s, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) return $decoded;
        }
        return [];
    }

    /** Build the WhatsApp-friendly order summary text (always English; the
     *  caller localizes it into the customer's language). */
    private function buildSummary(array $items, array $unavailable, int $totalMinor, ?string $currency): string
    {
        // Nothing could be added → just say sorry + WHY, with NO confirm/cancel
        // prompt (there's nothing to confirm).
        if (empty($items)) {
            $lines = ["Sorry, we can't take that order right now:"];
            foreach ($unavailable as $u) {
                $lines[] = '• ' . ($u['qty'] ?? 1) . ' × ' . ($u['name'] ?? '') . ' — ' . $this->reasonText($u);
            }
            $lines[] = '';
            $lines[] = 'Please send your order again any time.';
            return implode("\n", $lines);
        }

        $lines = ['*Your order*'];
        foreach ($items as $li) {
            $lineTotal = WaProduct::formatCurrency((int) $li['price_minor'] * (int) $li['qty'], $li['currency'] ?? $currency);
            $lines[] = '• ' . $li['qty'] . ' × ' . $li['name'] . ' — ' . $lineTotal;
        }
        $lines[] = '';
        $lines[] = '*Total: ' . WaProduct::formatCurrency($totalMinor, $currency) . '*';

        if (!empty($unavailable)) {
            $lines[] = '';
            $lines[] = '_Not available:_';
            foreach ($unavailable as $u) {
                $lines[] = '• ' . ($u['qty'] ?? 1) . ' × ' . ($u['name'] ?? '') . ' — ' . $this->reasonText($u);
            }
        }

        // NO "reply CONFIRM" line — the DELIVERY ADDRESS the customer sends next
        // IS the confirmation. Asking for both "CONFIRM" and the address in two
        // messages confused customers (they didn't know which to answer). The
        // address prompt (ask_shipping) is the single, clear next step.
        return implode("\n", $lines);
    }

    /** A short "what's in stock" list to show when the customer's items didn't
     *  match — so they can re-order something that actually exists. Product names
     *  stay as the operator stored them (not translated); prices via formatCurrency. */
    public function availableProductsList(int $wsId, int $limit = 15): string
    {
        $rows = WaProduct::query()
            ->where('workspace_id', $wsId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('stock_qty')->orWhere('stock_qty', '>', 0);
            })
            ->orderBy('sort_order')->orderBy('name')
            ->limit($limit)->get();

        $lines = [];
        foreach ($rows as $p) {
            $price = WaProduct::formatCurrency((int) $p->price_minor, $p->currency_code);
            $qty   = ($p->stock_qty !== null) ? ' (' . (int) $p->stock_qty . ' left)' : '';
            $lines[] = '• ' . $p->name . ' — ' . $price . $qty;
        }
        return implode("\n", $lines);
    }

    /** Human reason for an unavailable line. "only N in stock" is clearer than
     *  the old "out of stock (N left)" (which read as a contradiction). */
    private function reasonText(array $u): string
    {
        if (($u['reason'] ?? '') !== 'out_of_stock') return 'not found';
        $left = (int) ($u['available'] ?? 0);
        return $left > 0 ? ('only ' . $left . ' in stock') : 'out of stock';
    }

    // ── multi-language (Google Translate, no API key) ─────────────────────────

    /**
     * Decide what a customer's reply means, in ANY language: translate to
     * English, then match the universal order words. "确认" / "sí confirmar" /
     * "はい" → confirm; "取消" / "no" → cancel.
     * @return array{intent:string, english:string}  intent = confirm|cancel|unclear
     */
    public function decideReply(string $text): array
    {
        $en = mb_strtolower($this->gTranslate(trim($text), 'auto', 'en')['text']);
        $confirm = (bool) preg_match('/\b(confirm|confirmed|confirming|yes|yeah|yep|yup|ok|okay|sure|correct|proceed|place\s+(the\s+)?order|go\s+ahead)\b/u', $en);
        $cancel  = (bool) preg_match('/\b(cancel|canceled|cancelled|no|nope|stop|discard|abort)\b/u', $en);
        $intent  = $confirm ? 'confirm' : ($cancel ? 'cancel' : 'unclear'); // confirm wins ties
        Log::info('[ORDERING] decideReply', ['en' => mb_substr($en, 0, 40), 'intent' => $intent]);
        return ['intent' => $intent, 'english' => $en];
    }

    /**
     * Translate an English bot message INTO the customer's language — detected
     * from what THEY wrote ($customerText). Best-effort: returns English on any
     * failure, so it can never block an order.
     */
    public function localizeForCustomer(string $message, string $customerText): string
    {
        $message = trim($message);
        if ($message === '') return $message;

        // Detect the customer's language from what THEY wrote, then translate the
        // English bot message into it. We must NOT short-circuit on "pure ASCII =
        // English": Malay / Indonesian / Spanish / etc. are written in Latin
        // (ASCII) script yet are NOT English, and that old shortcut wrongly left
        // their order summaries in English (only non-ASCII langs like Chinese got
        // translated). gTranslate returns the detected source lang; on any
        // failure it is ''/und/auto → leave the message in English (best-effort,
        // never blocks an order).
        $customerText = trim($customerText);
        if ($customerText === '') return $message;

        $lang = $this->confidentSrcLang($customerText);
        if ($lang === '') return $message;

        $out = $this->gTranslate($message, 'en', $lang)['text'];
        return trim($out) !== '' ? $out : $message;
    }

    /** Detected customer language code from their text ('' = English/unknown). */
    public function detectCustomerLang(string $customerText): string
    {
        return $this->confidentSrcLang($customerText);
    }

    /**
     * Detect the customer's source language, GUARDED against gTranslate's worst
     * failure mode: a short English order (product names like "Mc Queen Toys",
     * "2 wings") mis-tagged as a Latin-script language (es/pt/it…) — which then
     * translated the whole CONFIRM/CANCEL prompt into e.g. Spanish (Jessica bug).
     * The auto→en call already hands back the English translation for free, so:
     * if translating the ASCII text to English barely changed it, the text WAS
     * English → ignore the mis-detection. Non-Latin scripts (zh/ja/ar/hi/th…) are
     * distinctive even when short, so they're always trusted — and a genuine
     * Malay/Spanish order (which DOES change when translated) still localizes.
     * Returns '' = English/unknown.
     */
    private function confidentSrcLang(string $customerText): string
    {
        $customerText = trim($customerText);
        if ($customerText === '') return '';
        $res  = $this->gTranslate($customerText, 'auto', 'en');
        $lang = (string) ($res['src'] ?? '');
        if (in_array($lang, ['', 'en', 'und', 'auto'], true)) return '';

        // Non-Latin script → distinctive, trust the detection.
        if (!preg_match('/^[\x00-\x7F]*$/', $customerText)) return $lang;

        // Latin-script + ASCII: verify it isn't English mis-tagged. If auto→en
        // left the text essentially unchanged, it was already English.
        $norm = static fn ($s) => preg_replace('/[^a-z0-9]+/i', '', mb_strtolower(trim((string) $s)));
        $orig = $norm($customerText);
        $en   = $norm($res['text'] ?? '');
        if ($orig !== '' && $orig === $en) return '';

        return $lang;
    }

    /** Translate an English message INTO a known language code (skip if en/empty). */
    public function localizeTo(string $message, ?string $lang): string
    {
        $message = trim($message);
        $lang = (string) $lang;
        if ($message === '' || $lang === '' || in_array($lang, ['en', 'und', 'auto'], true)) return $message;
        $out = $this->gTranslate($message, 'en', $lang)['text'];
        return trim($out) !== '' ? $out : $message;
    }

    /**
     * Jessica #2 — the customer's last-used ship-to (name/company/address) so the
     * flow can offer "use your saved address?" on repeat orders. Null = first order.
     */
    public function shippingFor(int $wsId, string $customerPhone): ?array
    {
        $digits = preg_replace('/\D+/', '', $customerPhone);
        if ($digits === '') return null;

        // 1. Merchant-PRE-SET profile (shop dashboard → Customers) wins, so a
        //    known customer's saved address auto-fills even on their FIRST order.
        $profile = \App\Models\WaCustomerProfile::where('workspace_id', $wsId)
            ->where('phone', $digits)->first();
        if ($profile && (trim((string) $profile->address) !== '' || trim((string) $profile->name) !== '')) {
            $name    = (string) $profile->name;
            $company = (string) $profile->company;
            $addr    = (string) $profile->address;
            $text = trim(($name !== '' ? $name . "\n" : '') . ($company !== '' ? $company . "\n" : '') . $addr);
            return ['name' => $name, 'company' => $company, 'address' => $addr, 'text' => $text];
        }

        // 2. Otherwise reuse the address from their most recent order.
        $last = WaOrder::where('workspace_id', $wsId)->where('customer_phone', $digits)
            ->whereNotNull('customer_address')->where('customer_address', '!=', '')
            ->latest('id')->first();
        if (!$last) return null;
        $company = is_array($last->meta_json) ? (string) ($last->meta_json['ship_company'] ?? '') : '';
        $text = trim(
            ($last->customer_name ? $last->customer_name . "\n" : '')
            . ($company !== '' ? $company . "\n" : '')
            . (string) $last->customer_address
        );
        return ['name' => (string) $last->customer_name, 'company' => $company, 'address' => (string) $last->customer_address, 'text' => $text];
    }

    /** Save ship-to onto the in-flight cart so confirm() persists it to the order. */
    public function setPendingShipping(int $wsId, string $customerPhone, string $name = '', string $company = '', string $address = ''): bool
    {
        $digits  = preg_replace('/\D+/', '', $customerPhone);
        $pending = WaPendingOrder::where('workspace_id', $wsId)->where('customer_phone', $digits)
            ->where('status', 'pending')->first();
        if (!$pending) return false;
        $pending->forceFill([
            'ship_name'    => trim($name) ?: null,
            'ship_company' => trim($company) ?: null,
            'ship_address' => trim($address) ?: null,
        ])->save();
        return true;
    }

    /**
     * Jessica #1/#2/#3 — capture a delivery address from a free-text OR voice
     * reply and store it SPLIT into Name / Company / Address.
     *
     * Pipeline:
     *   1. If the reply text is empty/a placeholder, the customer likely sent
     *      the address as a VOICE note → transcribe it (OrderMediaResolver).
     *   2. AI-split the raw text into {name, company, address} so the admin
     *      order page shows clean separate fields.
     *   3. Safety net: if the AI gives no usable address, fall back to storing
     *      the WHOLE raw text verbatim as the address (the proven old behavior)
     *      — so a bad parse can never lose the customer's address.
     *
     * @return array{ok:bool, name:string, company:string, address:string, text:string}
     */
    public function setPendingShippingFromText(int $wsId, string $customerPhone, string $rawText, string $model = 'gpt-4o-mini'): array
    {
        $rawText = trim($rawText);

        // 1. Voice address — placeholder reply means the address came as audio.
        if ($rawText === '' || $this->looksLikePlaceholder($rawText)) {
            $m = $this->media->resolve($wsId, $customerPhone, true);
            if ($m['kind'] === 'voice' && !empty($m['voice_text'])) {
                $rawText = trim($m['voice_text']);
                Log::info('[ORDER-FLOW] 3 · voice → address', ['chars' => mb_strlen($rawText)]);
            }
        }
        if ($rawText === '') {
            return ['ok' => false, 'used_saved' => false, 'name' => '', 'company' => '', 'address' => '', 'text' => ''];
        }

        // 2. ONE AI call interprets the reply (any language, no word lists):
        //    confirm the saved address, or a new address split into name/company/address.
        $saved = $this->shippingFor($wsId, $customerPhone);
        $parts = $this->parseShippingText($rawText, $wsId, $model);

        // The AI says they confirmed → reuse the address we already have on file.
        if (!empty($parts['use_saved']) && $saved) {
            $this->setPendingShipping($wsId, $customerPhone, $saved['name'], $saved['company'], $saved['address']);
            Log::info('[ORDER-FLOW] 3 · AI: confirmed saved address');
            return ['ok' => true, 'used_saved' => true, 'name' => $saved['name'], 'company' => $saved['company'], 'address' => $saved['address'], 'text' => $saved['text']];
        }

        // Otherwise a NEW address. Safety net — never lose it to a thin/failed parse.
        $name    = trim((string) ($parts['name'] ?? ''));
        $company = trim((string) ($parts['company'] ?? ''));
        $address = trim((string) ($parts['address'] ?? '')) ?: $rawText;

        $this->setPendingShipping($wsId, $customerPhone, $name, $company, $address);

        $text = trim(($name !== '' ? $name . "\n" : '') . ($company !== '' ? $company . "\n" : '') . $address);
        return ['ok' => true, 'used_saved' => false, 'name' => $name, 'company' => $company, 'address' => $address, 'text' => $text];
    }

    /**
     * AI interpretation of a shipping reply — in ANY language, with NO hardcoded
     * word lists. The model decides whether the customer:
     *   • CONFIRMED the address on file ("use_saved": true — any affirmative, any
     *     language: yes / ok / 是的 / sí / ya / betul / sim / …), OR
     *   • gave a NEW address (use_saved=false + split into name/company/address).
     * Best-effort: returns use_saved=false + empty parts on any failure, so the
     * caller falls back to storing the reply verbatim (never loses an address).
     *
     * @return array{use_saved:bool, name:string, company:string, address:string}
     */
    public function parseShippingText(string $text, int $wsId = 0, string $model = 'gpt-4o-mini'): array
    {
        $text = trim($text);
        $empty = ['use_saved' => false, 'name' => '', 'company' => '', 'address' => ''];
        if ($text === '') return $empty;

        // Use the SAME provider the order parse uses, so a workspace running
        // Claude/Gemini (no OpenAI key) still gets a structured result.
        $ml = strtolower($model);
        $provider = str_starts_with($ml, 'claude') ? 'anthropic'
                  : (str_starts_with($ml, 'gemini') ? 'gemini'
                  : ((str_starts_with($ml, 'mistral') || str_starts_with($ml, 'ministral') || str_starts_with($ml, 'open-mistral') || str_starts_with($ml, 'open-mixtral')) ? 'mistral'
                  : 'openai'));

        $system = 'A customer was shown their saved delivery address and asked to either CONFIRM it '
                . '(by replying with ANY affirmative, in ANY language) or send a NEW delivery address. '
                . 'Read their reply and respond with ONLY a single JSON object: '
                . '{"use_saved": true|false, "name":"<recipient name or empty>", "company":"<company or empty>", "address":"<full street/postal address, or empty>"}. '
                . 'Set use_saved=true ONLY when the reply is a pure confirmation/agreement with no new address. '
                . 'When they provided an address, set use_saved=false and fill the fields (keep the address in the customer\'s original language/script; if unsure put the whole reply in address). '
                . 'No markdown, JSON only.';

        try {
            $reply = $this->ai->callProvider(
                provider:     $provider,
                model:        $model,
                workspaceId:  $wsId,
                systemPrompt: $system,
                userPrompt:   $text,
                maxTokens:    300,
                temperature:  0.0,
                jsonMode:     true,
            );
            if (!is_string($reply) || $reply === '') return $empty;
            $j = $this->extractJson($reply);
            return [
                'use_saved' => filter_var($j['use_saved'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'name'      => trim((string) ($j['name'] ?? '')),
                'company'   => trim((string) ($j['company'] ?? '')),
                'address'   => trim((string) ($j['address'] ?? '')),
            ];
        } catch (\Throwable $e) {
            Log::warning('[ORDER-FLOW] 3 · shipping interpret failed: ' . $e->getMessage());
            return $empty;
        }
    }

    /**
     * Is this "reply" actually a placeholder for media (no real typed text)?
     * Covers blanks, the "[voice note]" / "[image]" markers WhatsApp/our bridge
     * use, and a bare message id (the extractor falls back to reply.id when a
     * media message carries no caption).
     */
    private function looksLikePlaceholder(string $text): bool
    {
        $t = trim($text);
        if ($t === '') return true;
        $low = mb_strtolower($t);
        foreach (['[voice note]', '[voice]', '[audio]', '[image]', '[photo]', '[media]', '[document]', '[sticker]'] as $marker) {
            if ($low === $marker) return true;
        }
        // A bare WhatsApp message id (e.g. "3EB0...", "wamid....", long hex) with
        // no spaces is not a real order/address — treat as a media placeholder.
        if (!str_contains($t, ' ') && preg_match('/^(wamid[\.:]|3eb0|[A-F0-9]{20,})/i', $t)) return true;
        return false;
    }

    /**
     * One Google-Translate call. Returns ['text'=>translated, 'src'=>detected
     * source-lang]. Free endpoint, no key. Falls back to the input text.
     */
    private function gTranslate(string $text, string $sl, string $tl): array
    {
        $text = trim($text);
        if ($text === '') return ['text' => '', 'src' => $sl];
        try {
            $r = \Illuminate\Support\Facades\Http::timeout(6)
                ->get('https://translate.googleapis.com/translate_a/single', [
                    'client' => 'gtx', 'sl' => $sl, 'tl' => $tl, 'dt' => 't', 'q' => $text,
                ]);
            if ($r->ok()) {
                $d   = $r->json();
                $out = '';
                foreach ((array) ($d[0] ?? []) as $seg) { $out .= (string) ($seg[0] ?? ''); }
                $out = trim($out);
                if ($out !== '') return ['text' => $out, 'src' => (string) ($d[2] ?? $sl)];
            }
        } catch (\Throwable $e) {
            Log::warning('[ORDERING] gTranslate failed: ' . $e->getMessage());
        }

        // Offline safety net for the most common confirm/cancel words → English.
        if ($tl === 'en') {
            $low = mb_strtolower($text);
            $map = [
                '确认'=>'confirm','確認'=>'confirm','确定'=>'confirm','下单'=>'confirm','是'=>'yes','好'=>'ok','可以'=>'ok',
                'はい'=>'yes','確定'=>'confirm','확인'=>'confirm','네'=>'yes','نعم'=>'yes','تأكيد'=>'confirm','हाँ'=>'yes',
                '取消'=>'cancel','不要'=>'cancel','不'=>'no','キャンセル'=>'cancel','いいえ'=>'no','취소'=>'cancel',
                'لا'=>'no','إلغاء'=>'cancel','नहीं'=>'no','batal'=>'cancel','ยกเลิก'=>'cancel','hủy'=>'cancel',
            ];
            foreach ($map as $k => $v) { if (str_contains($low, mb_strtolower($k))) return ['text' => $v, 'src' => 'und']; }
        }
        return ['text' => $text, 'src' => $sl];
    }
}
