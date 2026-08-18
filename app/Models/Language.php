<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide language registry. Admin manages this list at
 * /admin/languages. Used as the source of truth for the default-language
 * select on /admin/settings/general and the per-user language switcher.
 *
 * `code` is the BCP-47 / ISO-639 tag Laravel expects in app.locale.
 */
class Language extends Model
{
    protected $fillable = [
        'name', 'code', 'native_name', 'direction', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];
}
