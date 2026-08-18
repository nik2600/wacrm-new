<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageFeature extends Model
{
    use HasFactory;

    protected $table = 'package_features';

    protected $fillable = ['name', 'icon', 'status'];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('status', 1);
    }
}
