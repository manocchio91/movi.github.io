<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organizer_profile_id
 * @property int $events_created
 * @property int $events_completed
 * @property int $events_cancelled
 * @property int $sold_out_events
 * @property numeric-string $average_fill_rate
 * @property numeric-string $average_rating
 * @property int $total_participants
 * @property int $follower_count
 * @property string $ranking_period
 * @property Carbon $calculated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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

    /** @return BelongsTo<OrganizerProfile, $this> */
    public function organizerProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizerProfile::class);
    }
}
