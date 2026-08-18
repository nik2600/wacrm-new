<?php

namespace App\Services;

use App\Models\WaOrder;
use App\Models\WaProduct;

/**
 * Parses inbound WhatsApp messages that came via the storefront's
 * "Order on WhatsApp" wa.me deep link. The shared cart JS produces
 * a deterministic format:
 *
 *   Hi! I would like to order from <Store Name>:
 *
 *   • <Product Name> × <qty>  (₹ <subtotal>)
 *   • ...
 *
 *   TOTAL: ₹ <total>
 *
 *   My name: <maybe>
 *   Address: <maybe>
 *
 * If we recognise the shape, we extract items and write a wa_orders
 * row. Returns the WaOrder model (or null if the message wasn't a
 * recognisable cart). Best-effort — operators can always still
 * convert manually from the chat.
 */
class StorefrontOrderParser
{
    public function parse(int $workspaceId, string $body, string $fromPhone, ?string $name = null): ?WaOrder
    {
        if (!str_contains($body, 'I would like to order from')) {
            return null;
        }

        $lines = preg_split('/\r?\n/', $body);
        $items = [];
        $totalMinor = 0;
        $extractedName = null;
        $address = null;

        foreach ($lines as $line) {
            $line = trim($line);
            // Bullet lines: "• Spring Tee × 2  (₹ 1998.00)"
            if (preg_match('/^[•\-\*]\s+(.+?)\s+×\s+(\d+)\s*\(₹\s*([\d,\.]+)\)/u', $line, $m)) {
                $name = trim($m[1]);
                $qty  = (int) $m[2];
                $sub  = (int) round(((float) str_replace(',', '', $m[3])) * 100);
                $perUnit = $qty > 0 ? (int) round($sub / $qty) : $sub;
                $product = WaProduct::forWorkspace($workspaceId)->where('name', $name)->first();
                $items[] = [
                    'product_id'  => $product?->id,
                    'name'        => $name,
                    'qty'         => $qty,
                    'price_minor' => $perUnit,
                    'image'       => $product?->image_url,
                ];
                $totalMinor += $sub;
            }
            if (preg_match('/^TOTAL:\s*₹\s*([\d,\.]+)/iu', $line, $m)) {
                $totalMinor = (int) round(((float) str_replace(',', '', $m[1])) * 100) ?: $totalMinor;
            }
            if (preg_match('/^My name:\s*(.+)$/iu', $line, $m)) $extractedName = trim($m[1]) ?: null;
            if (preg_match('/^Address:\s*(.+)$/iu', $line, $m)) $address = trim($m[1]) ?: null;
        }

        if (empty($items)) return null;

        $sf = \App\Models\WaStorefront::where('workspace_id', $workspaceId)->first();
        return WaOrder::create([
            'workspace_id'   => $workspaceId,
            'source'         => 'storefront',
            'customer_phone' => $fromPhone,
            'customer_name'  => $extractedName ?: $name,
            'items_json'     => $items,
            'total_minor'    => $totalMinor,
            'currency_code'  => 'INR',
            'status'         => 'new',
            'storefront_id'  => $sf?->id,
            'notes'          => $address ? "Address: {$address}" : null,
            'meta_json'      => ['raw_message' => mb_substr($body, 0, 1000)],
        ]);
    }
}
