<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id', 'internal_enabled', 'email_enabled', 'promotional_enabled',
        'frequency', 'preferred_days',
    ];

    protected function casts(): array
    {
        return [
            'internal_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'promotional_enabled' => 'boolean',
            'preferred_days' => 'array',
        ];
    }
}
