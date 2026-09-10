<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property numeric-string $fill_rate
 * @property numeric-string $attendance_rate
 * @property numeric-string $no_show_rate
 * @property numeric-string $average_rating
 * @property int $review_count
 * @property numeric-string $recommendation_rate
 * @property int $favorite_count
 * @property numeric-string $success_score
 * @property Carbon $calculated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EventSuccessScore extends Model
{
    protected $fillable = [
        'event_id', 'fill_rate', 'attendance_rate', 'no_show_rate',
        'average_rating', 'review_count', 'recommendation_rate',
        'favorite_count', 'success_score', 'calculated_at',
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
}
