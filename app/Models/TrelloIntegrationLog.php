<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrelloIntegrationLog extends Model
{
    protected $fillable = [
        'integration_id', 'workspace_id', 'event', 'detail', 'status',
    ];
}
