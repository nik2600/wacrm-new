<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportAgent extends Model
{
    protected $fillable = ['user_id', 'workspace_id', 'is_active', 'specialty', 'current_load'];
    protected $casts = ['is_active' => 'boolean', 'current_load' => 'integer'];

    public function user()      { return $this->belongsTo(User::class); }
    public function workspace() { return $this->belongsTo(Workspace::class); }
}
