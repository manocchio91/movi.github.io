<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'ulid', 'user_id', 'event_id', 'status', 'total_people', 'adults_count',
        'children_count', 'total_price_cents', 'children_age_ranges', 'dietary_needs',
        'accessibility_needs', 'notes', 'booking_code', 'qr_token_hash', 'booked_at',
        'cancelled_at', 'idempotency_key', 'event_terms_accepted',
    ];

    protected function casts(): array
    {
        return [
            'children_age_ranges' => 'array',
            'booked_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'event_terms_accepted' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }
}
