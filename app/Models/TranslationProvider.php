<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Catalog row for one translation backend (MyMemory, Google, DeepL, …).
 * Same shape as PaymentGateway — encrypted JSON credentials, JSON
 * extra_config, is_active + is_default flags.
 *
 * The admin form at /admin/translation-providers drives the credentials
 * column; runtime resolution happens in TranslationProviderManager.
 */
class TranslationProvider extends Model
{
    protected $fillable = [
        'slug', 'name', 'description', 'is_active', 'is_default',
        'credentials', 'extra_config', 'sort_order',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'is_default'   => 'boolean',
        'extra_config' => 'array',
        'sort_order'   => 'integer',
    ];

    protected $hidden = ['credentials'];

    public function scopeActive(Builder $q): Builder { return $q->where('is_active', true); }

    public function getDecryptedCredentials(): array
    {
        if (empty($this->credentials)) return [];
        try {
            $arr = json_decode(Crypt::decryptString($this->credentials), true);
            return is_array($arr) ? $arr : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function setEncryptedCredentials(array $values): void
    {
        $this->credentials = Crypt::encryptString(json_encode($values, JSON_UNESCAPED_UNICODE));
    }
}
