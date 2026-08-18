<?php

namespace App\Services\WhatsAppCatalog;

use App\Exceptions\WhatsAppCatalogException;
use App\Models\Device;
use App\Models\WaProduct;
use Illuminate\Support\Facades\Http;

/**
 * Baileys can't talk to Meta's Commerce Catalog API — but we still
 * want a "send catalog" experience for non-WABA workspaces. This
 * service POSTs to the existing Node bridge's
 * /api/send-product-catalog/:phoneNumber endpoint, which has both
 * WABA and Baileys paths internally; the Baileys path sends a
 * native carousel (image + title + body + buttons per product).
 *
 * Same JSON shape Laravel uses for WABA so the controller can call
 * either with similar payloads.
 */
class BaileysCatalogService
{
    public function __construct(
        private string $nodeBaseUrl,
    ) {
    }

    public static function make(): self
    {
        // Same resolution order the rest of the app uses (InboxDispatcher,
        // WhatsAppDispatcher): admin-configured baileys_server_url first,
        // then SERVER_URL env. Default port is 8888, NOT 3000 — I had the
        // wrong default originally and Send failed with curl error 7.
        $url = (string) (\App\Models\SystemSetting::get('baileys_server_url') ?: env('SERVER_URL', 'http://localhost:8888'));
        return new self(rtrim($url, '/'));
    }

    /**
     * Send a multi-product carousel to a buyer.
     *
     * @param  Device $device   The Baileys device sending the message
     * @param  string $toWaId   Recipient phone (digits only)
     * @param  iterable<WaProduct> $products
     * @return array{success:bool, messageId?:string, error?:string}
     */
    public function sendCarousel(Device $device, string $toWaId, iterable $products, array $opts = []): array
    {
        $cards = [];
        foreach ($products as $p) {
            if (!($p instanceof WaProduct)) continue;
            $cards[] = [
                'retailer_id' => $p->meta_retailer_id ?: ($p->sku ?: ('wsn-' . $p->id)),
                'id'          => $p->id,
                'name'        => $p->name,
                'description' => $p->description,
                'image_url'   => $p->image_url,
                'price'       => $p->price_minor / 100,
                'currency'    => $p->currency_code,
                'category'    => $p->category,
                'availability'=> $p->effective_availability,
                'url'         => $p->product_url,
            ];
        }
        if (empty($cards)) {
            throw new WhatsAppCatalogException('No products to send.');
        }

        $payload = [
            'targetPhoneNumber' => $toWaId,
            'products'          => $cards,
            'type'              => count($cards) === 1 ? 'single' : 'multi',
            'header_text'       => $opts['header'] ?? 'Our catalog',
            'body_text'         => $opts['body']   ?? 'Tap a product to learn more',
            'footer_text'       => $opts['footer'] ?? '',
            'section_title'     => $opts['section_title'] ?? 'Products',
        ];

        $senderPhone = trim(($device->country_code ?? '') . ($device->phone_number ?? ''));
        $senderPhone = preg_replace('/\D+/', '', $senderPhone);
        if (!$senderPhone) {
            throw new WhatsAppCatalogException('Sending device has no phone number.');
        }

        $res = Http::timeout(60)
            ->acceptJson()
            ->post($this->nodeBaseUrl . '/api/send-product-catalog/' . $senderPhone, [
                'json' => $payload,
            ]);

        if ($res->failed()) {
            $body = $res->json() ?: ['raw' => $res->body()];
            throw new WhatsAppCatalogException(
                'Baileys carousel failed: ' . ($body['error'] ?? $res->status()),
                $res->status(),
                $body
            );
        }
        return $res->json() ?: [];
    }

    /**
     * Catalog-link "message" for Baileys — there's no real catalog
     * on the WhatsApp side, so we just send a text linking to our
     * own /s/{slug} storefront. Free, works outside the 24-h window.
     */
    public function sendStorefrontLink(Device $device, string $toWaId, string $publicUrl, string $body = ''): array
    {
        $senderPhone = preg_replace('/\D+/', '', trim(($device->country_code ?? '') . ($device->phone_number ?? '')));
        $text = trim(($body ?: 'Browse our shop:') . "\n" . $publicUrl);
        $res = Http::timeout(30)->acceptJson()
            ->post($this->nodeBaseUrl . '/api/send-message/' . $senderPhone, [
                'targetPhoneNumber' => $toWaId,
                'message'           => $text,
            ]);
        if ($res->failed()) {
            $body = $res->json() ?: ['raw' => $res->body()];
            throw new WhatsAppCatalogException(
                'Baileys link send failed: ' . ($body['error'] ?? $res->status()),
                $res->status(),
                $body,
            );
        }
        return $res->json() ?: [];
    }
}
