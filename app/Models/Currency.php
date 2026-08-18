<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-managed currency. Ported from SnapNest's pattern.
 *
 * `exchange_rate` is value of 1 unit in USD (system base). Updated
 * either manually in the admin form or via the Fetch Rates button
 * which pulls from https://open.er-api.com/v6/latest/USD (free).
 */
class Currency extends Model
{
    protected $fillable = [
        'name', 'code', 'symbol', 'precision', 'exchange_rate', 'is_active',
    ];

    protected $casts = [
        'precision'     => 'integer',
        'exchange_rate' => 'decimal:6',
        'is_active'     => 'boolean',
    ];

    public function scopeActive(Builder $q): Builder { return $q->where('is_active', true); }

    /**
     * Display symbol for a currency CODE, resolved DYNAMICALLY from this table
     * (admin-managed) — never a hardcoded per-currency map. Cached 5 min. Falls
     * back to "CODE " only when the row has no symbol, so a currency the admin
     * hasn't given a symbol still renders sensibly. One source of truth for
     * every money render (deals, reports, …).
     */
    public static function symbolFor(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') return '';
        $map = \Illuminate\Support\Facades\Cache::remember('currency_symbol_map', 300, fn () =>
            static::query()->pluck('symbol', 'code')->mapWithKeys(fn ($s, $c) => [strtoupper($c) => $s])->all());
        $sym = (string) ($map[$code] ?? '');
        return $sym !== '' ? $sym : $code . ' ';
    }

    /**
     * Normalize the code to uppercase before save — ISO codes are
     * conventionally uppercase ("USD" not "usd").
     */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }
}
