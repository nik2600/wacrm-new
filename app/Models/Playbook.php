<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Playbook extends Model
{
    protected $fillable = ['workspace_id', 'name', 'slug', 'trigger_type', 'trigger_value', 'steps', 'is_active', 'use_count'];
    protected $casts = [
        'steps'     => 'array',
        'is_active' => 'boolean',
        'use_count' => 'integer',
    ];
}
