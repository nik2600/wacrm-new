<?php

namespace App\Services\Translation;

use App\Models\TranslationProvider;
use Illuminate\Support\Facades\Log;

/**
 * Registry + resolver for translation providers. Same pattern as
 * PaymentGatewayManager — slug → driver class, plus a fallback chain
 * so a single provider going down doesn't kill auto-replies entirely.
 *
 * Resolution order at runtime:
 *   1. The is_default=true active row, if any.
 *   2. The is_active=true row with lowest sort_order.
 *   3. Hard-coded MyMemory fallback driver (zero-config, always works
 *      as long as MyMemory's free tier is up).
 *
 * If the active driver fails on a call, the manager retries the next
 * is_active=true driver in sort order. After exhausting that chain it
 * gives up and returns null — caller falls back to canonical text.
 */
class TranslationProviderManager
{
    /** Slug → driver class registry. */
    public const DRIVER_MAP = [
        'mymemory'      => \App\Services\Translation\Drivers\MyMemoryDriver::class,
        'libretranslate'=> \App\Services\Translation\Drivers\LibreTranslateDriver::class,
        'google_gtx'    => \App\Services\Translation\Drivers\GoogleGtxDriver::class,
        'deepl'         => \App\Services\Translation\Drivers\DeepLDriver::class,
        'google_cloud'  => \App\Services\Translation\Drivers\GoogleCloudDriver::class,
    ];

    /**
     * Slugs the official-only lockdown toggle filters out. Free
     * community-supported / unofficial endpoints. Compliance teams
     * at large customers want these gone before they sign off.
     */
    public const UNOFFICIAL_SLUGS = ['google_gtx', 'mymemory', 'libretranslate'];

    /** Human-friendly metadata for the admin UI catalog. */
    public const PROVIDER_META = [
        'mymemory'       => ['name' => 'MyMemory',         'desc' => 'Free, no key — out-of-box default. 50k req/day per IP with admin email.'],
        'libretranslate' => ['name' => 'LibreTranslate',   'desc' => 'Open-source, self-hostable via Docker. No third-party data leak.'],
        'google_gtx'     => ['name' => 'Google (free)',    'desc' => 'Best quality, free, but unofficial endpoint — may break without notice.'],
        'deepl'          => ['name' => 'DeepL',            'desc' => 'Premium quality for European languages. Free tier 500k chars/month.'],
        'google_cloud'   => ['name' => 'Google Cloud',     'desc' => 'Official Google Translate API. Paid: ~$20 per 1M characters.'],
    ];

    /**
     * Return the active driver instance, instantiated against its
     * TranslationProvider row. Null when no provider is configured
     * AND the hard-coded fallback can't load either.
     */
    public function activeDriver(): ?AbstractTranslatorDriver
    {
        $row = $this->activeRow();
        if (!$row) return $this->hardcodedFallback();

        $cls = self::DRIVER_MAP[$row->slug] ?? null;
        if (!$cls) return $this->hardcodedFallback();
        return new $cls($row);
    }

    /**
     * Yield each is_active=true driver in sort order so the façade
     * can retry the next one when the current call fails.
     *
     * The official-only lockdown (sprint 9.2) filters unofficial /
     * free community drivers out at the top of this iterator so a
     * compliance-locked install can NEVER accidentally route
     * customer data through MyMemory or the unofficial Google GTX
     * endpoint, even if an admin re-activates them by mistake.
     *
     * @return iterable<AbstractTranslatorDriver>
     */
    public function fallbackChain(): iterable
    {
        $officialOnly  = self::isOfficialOnlyMode();
        $residencyOK   = $this->residencyAllowedSlugs();   // null = no restriction

        $rows = TranslationProvider::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $cls = self::DRIVER_MAP[$row->slug] ?? null;
            if (!$cls) continue;
            if ($officialOnly && in_array($row->slug, self::UNOFFICIAL_SLUGS, true)) continue;
            if ($residencyOK !== null && !in_array($row->slug, $residencyOK, true)) continue;
            yield new $cls($row);
        }

        // Hard-coded MyMemory fallback only when BOTH lockdown is off
        // AND residency mode allows MyMemory. Strict workspaces never
        // silently fall through to the public free endpoint.
        if (!$officialOnly && ($residencyOK === null || in_array('mymemory', $residencyOK, true))) {
            $fallback = $this->hardcodedFallback();
            if ($fallback) yield $fallback;
        }
    }

    /**
     * Returns the array of allowed slugs based on the CURRENT
     * authenticated user's workspace residency setting. null means
     * "no restriction".
     *
     * This runs at the chain-iteration point so the residency check
     * is per-call — a single WaDesk install can host EU-strict and
     * unrestricted workspaces side-by-side and each gets the right
     * routing.
     */
    private function residencyAllowedSlugs(): ?array
    {
        try {
            $ws = optional(auth()->user())->currentWorkspace;
            if (!$ws) return null;
            $mode = $ws->data_residency ?: 'any';
            return \App\Models\Workspace::RESIDENCY_ALLOWED_SLUGS[$mode] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** True when the admin has flipped "official providers only" on. */
    public static function isOfficialOnlyMode(): bool
    {
        return (bool) \App\Models\SystemSetting::get('translation.official_only', false);
    }

    /** Active row, preferring is_default. */
    private function activeRow(): ?TranslationProvider
    {
        return TranslationProvider::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    /**
     * Synthetic MyMemory driver with no credentials — used as the
     * always-on last resort when nothing's been configured yet.
     */
    private function hardcodedFallback(): ?AbstractTranslatorDriver
    {
        try {
            $stub = new TranslationProvider([
                'slug'       => 'mymemory',
                'name'       => 'MyMemory (fallback)',
                'is_active'  => true,
                'is_default' => false,
            ]);
            return new \App\Services\Translation\Drivers\MyMemoryDriver($stub);
        } catch (\Throwable $e) {
            Log::warning('Translation fallback driver init failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Field schema for a given slug — used by the admin form renderer. */
    public function credentialFieldsFor(string $slug): array
    {
        $cls = self::DRIVER_MAP[$slug] ?? null;
        if (!$cls) return [];
        return $cls::credentialFields();
    }
}
