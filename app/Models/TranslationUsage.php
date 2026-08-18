<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per translation API call. Inserted by Translator::translate
 * at the bottom of the driver-chain loop — dictionary and cache hits
 * do NOT log here (they're free).
 *
 * cost_micros is millionths of a USD; convert with cost_micros / 1e6
 * for display. Stored as integer so SUM() over the dashboard's
 * month-to-date chart stays exact.
 */
class TranslationUsage extends Model
{
    public $timestamps = false;
    protected $table = 'translation_usage';

    protected $fillable = [
        'workspace_id', 'provider_slug', 'source_lang', 'target_lang',
        'chars_in', 'chars_out', 'cost_micros', 'was_fallback', 'called_at',
    ];

    protected $casts = [
        'chars_in'     => 'integer',
        'chars_out'    => 'integer',
        'cost_micros'  => 'integer',
        'was_fallback' => 'boolean',
        'called_at'    => 'datetime',
    ];

    /**
     * Cost-per-char in micros for each provider. Driven by the
     * published pricing of the paid APIs as of 2026-05; free drivers
     * are 0 so the dashboard column shows $0 for them honestly.
     *
     *   DeepL Pro:   $20/1M chars = 20 micros/char
     *   Google Cloud: $20/1M chars = 20 micros/char
     *   MyMemory:     free
     *   Libre:        free (self-hosted)
     *   Google GTX:   free (unofficial)
     */
    public const PROVIDER_COST_MICROS_PER_CHAR = [
        'deepl'         => 20,
        'google_cloud'  => 20,
        'mymemory'      => 0,
        'libretranslate'=> 0,
        'google_gtx'    => 0,
    ];

    public static function estimateCostMicros(string $providerSlug, int $charsIn): int
    {
        $rate = self::PROVIDER_COST_MICROS_PER_CHAR[$providerSlug] ?? 0;
        return $rate * $charsIn;
    }
}
