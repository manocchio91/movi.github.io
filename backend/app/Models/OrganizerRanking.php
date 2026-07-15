<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizerRanking extends Model
{
    protected $fillable = [
        'organizer_profile_id', 'events_created', 'events_completed',
        'events_cancelled', 'sold_out_events', 'average_fill_rate',
        'average_rating', 'total_participants', 'follower_count',
        'ranking_period', 'calculated_at',
    ];

    protected function casts(): array
    {
        return ['calculated_at' => 'datetime'];
    }

    public function organizerProfile()
    {
        return $this->belongsTo(OrganizerProfile::class);
    }
}
