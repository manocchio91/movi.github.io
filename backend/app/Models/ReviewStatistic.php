<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewStatistic extends Model
{
    protected $fillable = [
        'event_id', 'organizer_profile_id', 'reviews_count', 'average_rating',
        'average_quality', 'average_organization', 'average_location',
        'average_value', 'average_schedule_respect', 'average_staff',
        'one_star', 'two_star', 'three_star', 'four_star', 'five_star',
        'satisfied_rate', 'recommendation_rate', 'calculated_at',
    ];

    protected function casts(): array
    {
        return ['calculated_at' => 'datetime'];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function organizerProfile()
    {
        return $this->belongsTo(OrganizerProfile::class);
    }
}
