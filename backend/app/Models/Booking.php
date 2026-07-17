<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $fillable = [
        'ulid', 'user_id', 'event_id', 'status', 'total_people', 'adults_count',
        'children_count', 'total_price_cents', 'children_age_ranges', 'dietary_needs',
        'accessibility_needs', 'notes', 'booking_code', 'qr_token_hash', 'booked_at',
        'cancelled_at', 'cancellation_reason', 'cancelled_by_user_id',
        'idempotency_key', 'event_terms_accepted',
    ];

    protected $hidden = ['qr_token_hash', 'idempotency_key'];

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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<BookingItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /** @return HasOne<Review, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /** @return HasMany<CheckIn, $this> */
    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }
}
