<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardCache extends Model
{
    protected $table = 'dashboard_cache';

    protected $fillable = [
        'organizer_profile_id', 'cache_key', 'payload', 'calculated_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'calculated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
