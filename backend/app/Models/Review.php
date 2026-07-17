<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    protected $fillable = [
        'ulid', 'event_id', 'organizer_profile_id', 'user_id', 'booking_id',
        'rating', 'experience_quality_rating', 'organization_rating',
        'location_rating', 'value_rating', 'schedule_respect_rating',
        'staff_rating', 'suitable_for_children', 'would_recommend',
        'comment', 'status', 'reviewed_at', 'hidden_at',
    ];

    protected function casts(): array
    {
        return [
            'suitable_for_children' => 'boolean',
            'would_recommend' => 'boolean',
            'reviewed_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<OrganizerProfile, $this>
     */
    public function organizerProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizerProfile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return HasMany<ReviewImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ReviewImage::class);
    }

    /** @return HasMany<ReviewReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class);
    }
}
