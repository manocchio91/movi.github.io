<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    protected $fillable = [
        'organizer_profile_id', 'event_id', 'activity_id', 'reservation_id', 'campaign_id', 'user_id',
        'type', 'metadata', 'deduplication_key', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
