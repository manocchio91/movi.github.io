<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventStatistic extends Model
{
    protected $fillable = [
        'event_id', 'views', 'bookings_count', 'participants_total',
        'fill_rate', 'average_rating', 'calculated_at',
    ];

    protected function casts(): array
    {
        return ['calculated_at' => 'datetime'];
    }
}
