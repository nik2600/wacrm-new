<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One per-country × category message credit rate. country_code '' = any
 * country; category '' = any category. See MessageCreditRate for the
 * most-specific-wins resolution order.
 */
class MessageRate extends Model
{
    protected $fillable = ['country_code', 'category', 'credits', 'is_active'];

    protected $casts = [
        'credits'   => 'integer',
        'is_active' => 'boolean',
    ];

    /** Categories we recognise (mirrors Meta's message categories + free service window). */
    public const CATEGORIES = ['marketing', 'utility', 'authentication', 'service'];
}
