<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    public $timestamps = false;
    protected $fillable = ['ticket_id', 'author_user_id', 'author_role', 'body', 'attachments', 'is_internal_note', 'created_at'];
    protected $casts = [
        'attachments'      => 'array',
        'is_internal_note' => 'boolean',
        'created_at'       => 'datetime',
    ];

    public function ticket() { return $this->belongsTo(SupportTicket::class, 'ticket_id'); }
    public function author() { return $this->belongsTo(User::class, 'author_user_id'); }
}
