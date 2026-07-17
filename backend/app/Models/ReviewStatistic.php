<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<OrganizerProfile, $this> */
    public function organizerProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizerProfile::class);
    }
}
