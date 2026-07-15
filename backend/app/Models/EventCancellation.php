<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventCancellation extends Model
{
    protected $fillable = [
        'event_id', 'cancelled_by_user_id', 'reason_code', 'reason_text',
        'bookings_affected', 'people_affected', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return ['cancelled_at' => 'datetime'];
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
