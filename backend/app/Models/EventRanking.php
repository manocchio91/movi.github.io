<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
