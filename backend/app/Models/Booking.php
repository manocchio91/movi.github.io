<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property int $event_id
 * @property string $status
 * @property int $total_people
 * @property int $adults_count
 * @property int $children_count
 * @property int $total_price_cents
 * @property array<mixed>|null $children_age_ranges
 * @property string|null $dietary_needs
 * @property string|null $accessibility_needs
 * @property string|null $notes
 * @property string $booking_code
 * @property string $qr_token_hash
 * @property Carbon $booked_at
 * @property Carbon|null $cancelled_at
 * @property string|null $idempotency_key
 * @property bool $event_terms_accepted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $cancellation_reason
 * @property int|null $cancelled_by_user_id
 * @property string|null $qr_token_encrypted
 * @property string|null $contact_phone
 */
class Booking extends Model
{
    protected $fillable = [
        'ulid', 'user_id', 'event_id', 'status', 'total_people', 'adults_count',
        'children_count', 'total_price_cents', 'children_age_ranges', 'dietary_needs',
        'accessibility_needs', 'notes', 'booking_code', 'contact_phone', 'qr_token_hash', 'booked_at',
        'qr_token_encrypted',
        'cancelled_at', 'cancellation_reason', 'cancelled_by_user_id',
        'idempotency_key', 'event_terms_accepted',
    ];

    protected $hidden = ['contact_phone', 'qr_token_hash', 'qr_token_encrypted', 'idempotency_key'];

    protected function casts(): array
    {
        return [
            'children_age_ranges' => 'array',
            'booked_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'event_terms_accepted' => 'boolean',
            'qr_token_encrypted' => 'encrypted',
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
