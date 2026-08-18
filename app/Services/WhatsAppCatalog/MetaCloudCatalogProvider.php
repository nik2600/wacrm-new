<?php

namespace App\Services\WhatsAppCatalog;

/**
 * Meta Cloud API direct. /messages goes to:
 *   https://graph.facebook.com/v22.0/{PHONE_NUMBER_ID}
 *
 * Catalog CRUD lives on graph.facebook.com (handled in the base
 * class via graphEndpoint()).
 */
class MetaCloudCatalogProvider extends AbstractCloudCatalogProvider
{
    protected function messagesEndpoint(): string
    {
        $pnid = $this->bound->phone_number_id;
        if (!$pnid) {
            throw new \App\Exceptions\WhatsAppCatalogException(
                'Catalog has no phone_number_id — Meta Cloud requires it on the /messages path.'
            );
        }
        $apiVersion = (string) \App\Models\SystemSetting::get('catalog_graph_api_version', 'v23.0');
        return 'https://graph.facebook.com/' . ltrim($apiVersion, '/') . '/' . $pnid;
    }
}
