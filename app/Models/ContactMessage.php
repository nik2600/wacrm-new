<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A submission from the public /contact form. Stored here and an email
 * notification is sent to the site's support inbox on create.
 */
class ContactMessage extends Model
{
    protected $fillable = [
        'name', 'email', 'company', 'topic', 'message', 'ip', 'user_agent', 'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];
}
