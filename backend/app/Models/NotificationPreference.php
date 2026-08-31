<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id', 'internal_enabled', 'email_enabled', 'promotional_enabled',
        'operational_enabled', 'followed_organizer_events_enabled', 'frequency', 'preferred_days',
    ];

    protected function casts(): array
    {
        return [
            'internal_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'promotional_enabled' => 'boolean',
            'operational_enabled' => 'boolean',
            'followed_organizer_events_enabled' => 'boolean',
            'preferred_days' => 'array',
        ];
    }
}
