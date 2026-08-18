<?php

namespace App\Services\WhatsAppCatalog;

use App\Models\WaCatalog;
use App\Models\WaProduct;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Catalog concierge — turns a free-text inbound ("got any red shoes under
 * 2000?") into an instant Multi-Product Message of the best matches. The
 * customer browses and adds to cart without ever leaving the chat, and the
 * merchant never lifts a finger.
 *
 * Intent is parsed deterministically (price range + keywords) so it works
 * with zero AI spend and is fully testable offline; when a workspace has an
 * AI key configured this is where an LLM refiner would slot in (the parser
 * returns the same shape either way).
 *
 * Hard-gated for safety:
 *   • OFF by default — only runs when WaCatalog.meta_json.concierge_enabled
 *     is explicitly true. A live inbox is never auto-answered by surprise.
 *   • Only fires when the message actually looks like a product query.
 *   • 30s per-sender cooldown so a chatty customer can't trigger a flood.
 *   • Stays silent on no match unless the merchant opts into a reply.
 *   • Every failure is swallowed — the concierge must never break inbound.
 *
 * Config on WaCatalog.meta_json:
 *   concierge_enabled        bool   (default false)
 *   concierge_max            int    (default 10, capped at 30 = MPM limit)
 *   concierge_header         string (default "Here's what I found")
 *   concierge_footer         string (optional)
 *   concierge_reply_on_empty bool   (default false)
 *   concierge_empty_text     string (sent when enabled + no match)
 */
class CatalogConciergeService
{
    /** Words that carry no product meaning — stripped before matching. */
    private const STOPWORDS = [
        'show', 'me', 'want', 'need', 'looking', 'look', 'for', 'do', 'you', 'have', 'has',
        'any', 'the', 'a', 'an', 'is', 'are', 'got', 'get', 'some', 'please', 'pls', 'hi',
        'hello', 'hey', 'and', 'or', 'to', 'with', 'i', 'my', 'we', 'us', 'price', 'priced',
        'cost', 'budget', 'under', 'below', 'above', 'over', 'less', 'more', 'than', 'between',
        'around', 'near', 'about', 'upto', 'rs', 'inr', 'usd', 'send', 'share', 'catalog',
        'product', 'products', 'item', 'items', 'buy', 'purchase', 'order', 'available',
    ];

    public function handleInbound(int $workspaceId, string $phone, string $text): bool
    {
        $phone = preg_replace('/\D+/', '', $phone);
        $text  = trim($text);
        if ($phone === '' || mb_strlen($text) < 2) return false;

        try {
            $catalog = WaCatalog::where('workspace_id', $workspaceId)->first();
            if (!$catalog || !$catalog->catalog_id) return false;

            $meta = is_array($catalog->meta_json) ? $catalog->meta_json : [];
            if (($meta['concierge_enabled'] ?? false) !== true) return false;

            $intent = $this->extractIntent($text);
            // Not a product query → let the message fall through to normal handling.
            if (empty($intent['keywords']) && $intent['price_min'] === null && $intent['price_max'] === null) {
                return false;
            }

            // Anti-flood: one auto-answer per sender per 30s.
            if (!Cache::add("catalog_concierge:{$workspaceId}:{$phone}", 1, 30)) {
                return false;
            }

            $max      = max(1, min(30, (int) ($meta['concierge_max'] ?? 10)));
            $products = $this->search($workspaceId, $intent, $max);

            if ($products->isEmpty()) {
                if (($meta['concierge_reply_on_empty'] ?? false) === true) {
                    $this->sendText($workspaceId, $phone, (string) ($meta['concierge_empty_text']
                        ?? "Sorry, I couldn't find a match for that. Try a different keyword or budget."));
                    return true;
                }
                return false;
            }

            $retailerIds = $products
                ->map(fn ($p) => $p->meta_retailer_id ?: ($p->sku ?: 'wsn-' . $p->id))
                ->values()->all();

            $header = (string) ($meta['concierge_header'] ?? "Here's what I found");
            $body   = $this->bodyLine($products->count(), $text);
            $footer = !empty($meta['concierge_footer']) ? (string) $meta['concierge_footer'] : null;

            WhatsAppCatalogFactory::forWorkspace($workspaceId)->sendMPM(
                $phone,
                $header,
                $body,
                [['title' => 'Top matches', 'product_retailer_ids' => $retailerIds]],
                $footer,
            );

            return true;
        } catch (Throwable $e) {
            Log::warning('[CATALOG-CONCIERGE] handleInbound failed (ws ' . $workspaceId . '): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Parse a natural-language product query into keywords + a price band.
     * Currency-agnostic: a bare number after "under/below/over/between" is
     * treated as major units (× 100 to compare against price_minor).
     *
     * @return array{keywords:string[],price_min:?int,price_max:?int}
     */
    public function extractIntent(string $text): array
    {
        $low = Str::lower($text);

        $priceMin = null;
        $priceMax = null;

        // "between 1000 and 2000" / "1000 to 2000" / "1000-2000"
        if (preg_match('/(\d[\d,]*)\s*(?:-|to|and)\s*(\d[\d,]*)/', $low, $m)) {
            $a = (int) str_replace(',', '', $m[1]);
            $b = (int) str_replace(',', '', $m[2]);
            $priceMin = min($a, $b);
            $priceMax = max($a, $b);
        } else {
            if (preg_match('/(?:under|below|less than|upto|up to|max|<=?)\s*(?:rs\.?|₹|\$|inr|usd)?\s*(\d[\d,]*)/', $low, $m)) {
                $priceMax = (int) str_replace(',', '', $m[1]);
            }
            if (preg_match('/(?:above|over|more than|from|min|>=?)\s*(?:rs\.?|₹|\$|inr|usd)?\s*(\d[\d,]*)/', $low, $m)) {
                $priceMin = (int) str_replace(',', '', $m[1]);
            }
        }

        // Keywords: drop punctuation, stopwords, pure numbers, currency tokens.
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $low, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keywords = [];
        foreach ($tokens as $tok) {
            if (mb_strlen($tok) < 2) continue;
            if (ctype_digit($tok)) continue;
            if (in_array($tok, self::STOPWORDS, true)) continue;
            $keywords[] = $tok;
        }
        $keywords = array_values(array_unique($keywords));

        return ['keywords' => $keywords, 'price_min' => $priceMin, 'price_max' => $priceMax];
    }

    /**
     * Find the best-matching active products for an intent. Matches any
     * keyword across name/description/category/brand, applies the price
     * band, then ranks by how many keywords hit the name/category (strong
     * signal) over the description (weak).
     *
     * @param array{keywords:string[],price_min:?int,price_max:?int} $intent
     */
    public function search(int $workspaceId, array $intent, int $limit = 10): \Illuminate\Support\Collection
    {
        $q = WaProduct::where('workspace_id', $workspaceId)->where('status', 'active');

        if ($intent['price_min'] !== null) $q->where('price_minor', '>=', $intent['price_min'] * 100);
        if ($intent['price_max'] !== null) $q->where('price_minor', '<=', $intent['price_max'] * 100);

        $keywords = $intent['keywords'];
        if (!empty($keywords)) {
            $q->where(function ($outer) use ($keywords) {
                foreach ($keywords as $kw) {
                    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $kw) . '%';
                    $outer->orWhere('name', 'like', $like)
                          ->orWhere('description', 'like', $like)
                          ->orWhere('category', 'like', $like)
                          ->orWhere('brand', 'like', $like);
                }
            });
        }

        // Pull a generous candidate set, then rank in PHP for relevance.
        $candidates = $q->limit(max($limit * 3, 60))->get();

        if (empty($keywords)) {
            return $candidates->sortByDesc('updated_at')->take($limit)->values();
        }

        return $candidates
            ->map(function ($p) use ($keywords) {
                $strong = Str::lower(($p->name ?? '') . ' ' . ($p->category ?? '') . ' ' . ($p->brand ?? ''));
                $weak   = Str::lower((string) ($p->description ?? ''));
                $score  = 0;
                foreach ($keywords as $kw) {
                    if (str_contains($strong, $kw)) $score += 3;
                    elseif (str_contains($weak, $kw)) $score += 1;
                }
                $p->setAttribute('_score', $score);
                return $p;
            })
            ->sortByDesc('_score')
            ->take($limit)
            ->values();
    }

    private function bodyLine(int $count, string $query): string
    {
        $q = Str::limit(trim($query), 60);
        return $count === 1
            ? "I found 1 match for \"{$q}\":"
            : "I found {$count} matches for \"{$q}\":";
    }

    private function sendText(int $workspaceId, string $phone, string $text): void
    {
        try {
            app(\App\Services\WhatsAppDispatcher::class)->sendRaw(
                ['to_number' => $phone, 'body' => $text, 'workspace_id' => $workspaceId],
                null, 'W',
            );
        } catch (Throwable $e) {
            Log::warning('[CATALOG-CONCIERGE] empty-reply send failed: ' . $e->getMessage());
        }
    }
}
