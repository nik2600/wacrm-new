<?php

/**
 * Storefront subdomain + custom-domain config.
 *
 * - subdomain_host  is the parent domain that <slug>.<host> resolves
 *   against. Set in .env (STOREFRONT_HOST) when deploying so a buyer
 *   can run the SaaS at e.g. wadesk.app while the public storefronts
 *   live at <slug>.shops.wadesk.app.
 * - cname_target is the hostname we ask buyers to point custom
 *   domain CNAMEs at. The DNS verifier compares CNAME records
 *   against this value.
 */
return [
    'subdomain_host' => env('STOREFRONT_HOST', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),
    'cname_target'   => env('STOREFRONT_CNAME_TARGET', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),
];
