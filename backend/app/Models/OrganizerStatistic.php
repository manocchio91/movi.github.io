<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizerStatistic extends Model
{
    protected $fillable = [
        'organizer_profile_id', 'period_start', 'period_end', 'published_events',
        'future_events', 'completed_events', 'cancelled_events', 'sold_out_events',
        'bookings_count', 'participants_total', 'unique_participants',
        'returning_participants', 'returning_rate', 'average_rating',
        'followers_total', 'new_followers', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'calculated_at' => 'datetime',
        ];
    }
}
