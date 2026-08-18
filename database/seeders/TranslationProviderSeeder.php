<?php

namespace Database\Seeders;

use App\Models\TranslationProvider;
use App\Services\Translation\TranslationProviderManager;
use Illuminate\Database\Seeder;

/**
 * Pre-populates the translation_providers table with one row per
 * slug in TranslationProviderManager::DRIVER_MAP. Admin sees the
 * 5 cards immediately at /admin/translation-providers — no install
 * step, same SnapNest-style UX as payment-gateways and api-keys.
 *
 *   default + active : mymemory   (free, no key needed)
 *   active           : libretranslate is left inactive — admin
 *                       must configure its endpoint URL first.
 */
class TranslationProviderSeeder extends Seeder
{
    public function run(): void
    {
        // Default ships TWO drivers active so the fallback chain can
        // rescue throttled / failed calls. MyMemory's 5k/day-per-IP
        // limit gets eaten fast on shared hosts; Google GTX picks up
        // when MyMemory 429s. Buyers who don't want any Google traffic
        // can disable GTX from the admin UI.
        $rows = [
            ['slug' => 'mymemory',       'sort_order' => 1, 'is_active' => true,  'is_default' => true],
            ['slug' => 'google_gtx',     'sort_order' => 2, 'is_active' => true,  'is_default' => false],
            ['slug' => 'libretranslate', 'sort_order' => 3, 'is_active' => false, 'is_default' => false],
            ['slug' => 'deepl',          'sort_order' => 4, 'is_active' => false, 'is_default' => false],
            ['slug' => 'google_cloud',   'sort_order' => 5, 'is_active' => false, 'is_default' => false],
        ];

        foreach ($rows as $row) {
            $meta = TranslationProviderManager::PROVIDER_META[$row['slug']] ?? ['name' => ucfirst($row['slug']), 'desc' => null];
            TranslationProvider::firstOrCreate(
                ['slug' => $row['slug']],
                [
                    'name'        => $meta['name'],
                    'description' => $meta['desc'],
                    'is_active'   => $row['is_active'],
                    'is_default'  => $row['is_default'],
                    'sort_order'  => $row['sort_order'],
                ]
            );
        }
    }
}
