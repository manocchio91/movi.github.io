<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
