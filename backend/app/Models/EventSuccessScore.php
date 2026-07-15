<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
