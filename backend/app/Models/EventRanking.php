<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRanking extends Model
{
    protected $fillable = [
        'event_id', 'creativity_score', 'participation_score', 'review_score',
        'final_score', 'ranking_period', 'calculated_at', 'formula_version',
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
