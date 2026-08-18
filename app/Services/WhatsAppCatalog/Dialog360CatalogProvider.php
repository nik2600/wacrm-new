<?php

namespace App\Services\WhatsAppCatalog;

/**
 * 360dialog as BSP. /messages goes to:
 *   https://waba-v2.360dialog.io/messages
 *
 * 360dialog routes by D360-API-KEY header so the path has NO phone
 * number ID. Catalog CRUD still goes to graph.facebook.com (Meta
 * doesn't proxy catalog operations through 360dialog) — handled by
 * the base class via graphEndpoint().
 */
class Dialog360CatalogProvider extends AbstractCloudCatalogProvider
{
    protected function messagesEndpoint(): string
    {
        return 'https://waba-v2.360dialog.io';
    }
}
