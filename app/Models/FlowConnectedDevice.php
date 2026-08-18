<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlowConnectedDevice extends Model
{
    use HasFactory;

    protected $table = 'flow_connected_devices';

    protected $fillable = [
        'flow_id', 'device_number', 'device_name',
        'status', 'connected_at', 'last_active_at',
    ];

    protected $casts = [
        'device_number'  => 'encrypted',
        'device_name'    => 'encrypted',
        'connected_at'   => 'datetime',
        'last_active_at' => 'datetime',
    ];

    public function flow()
    {
        return $this->belongsTo(Flow::class);
    }
}
