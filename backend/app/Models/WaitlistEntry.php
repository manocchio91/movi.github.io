<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    protected $fillable = [
        'ulid', 'event_id', 'user_id', 'booking_id', 'requested_places', 'booking_payload',
        'status', 'reserved_until', 'offered_at', 'accepted_at', 'expired_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'booking_payload' => 'array', 'reserved_until' => 'datetime', 'offered_at' => 'datetime',
            'accepted_at' => 'datetime', 'expired_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
