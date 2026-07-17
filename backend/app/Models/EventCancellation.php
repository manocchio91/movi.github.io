<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
