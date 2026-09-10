<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $event_id
 * @property int|null $organizer_profile_id
 * @property int $reviews_count
 * @property numeric-string $average_rating
 * @property numeric-string $average_quality
 * @property numeric-string $average_organization
 * @property numeric-string $average_location
 * @property numeric-string $average_value
 * @property numeric-string $average_schedule_respect
 * @property numeric-string $average_staff
 * @property int $one_star
 * @property int $two_star
 * @property int $three_star
 * @property int $four_star
 * @property int $five_star
 * @property numeric-string $satisfied_rate
 * @property numeric-string $recommendation_rate
 * @property Carbon|null $calculated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
