<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerEventFormatInterest extends Model
{
    protected $fillable = [
        'user_id', 'event_id', 'event_series_id', 'category_id',
        'organizer_profile_id', 'municipality_id', 'notify_similar',
    ];

    protected function casts(): array
    {
        return ['notify_similar' => 'boolean'];
    }
}
