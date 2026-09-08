<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read int $participants_count
 * @property-read int $customer_cancellations_count
 * @property-read ReviewStatistic|null $reviewStatistic
 * @property-read EventSuccessScore|null $successScore
 */
class Event extends Model
{
    protected $fillable = [
        'ulid', 'organizer_profile_id', 'event_series_id', 'venue_id', 'municipality_id', 'title', 'slug', 'image_path',
        'summary', 'description', 'status', 'starts_at', 'ends_at', 'booking_closes_at',
        'capacity_total', 'available_total', 'is_free', 'price_notes', 'family_friendly',
        'indoor_outdoor', 'minimum_child_age', 'maximum_child_age', 'published_at',
        'submitted_at', 'approved_at', 'approved_by', 'program', 'included',
        'not_included', 'important_information', 'requirements',
        'recommended_age_range', 'equipment_notes', 'allergy_notes',
        'cancellation_policy', 'organizer_contact', 'show_in_inspiration_feed',
        'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason',
        'cancellation_notes', 'admin_creativity_rating',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'booking_closes_at' => 'datetime',
            'published_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_free' => 'boolean',
            'family_friendly' => 'boolean',
            'show_in_inspiration_feed' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @return BelongsTo<OrganizerProfile, $this> */
    public function organizerProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizerProfile::class);
    }

    /** @return BelongsTo<EventSeries, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(EventSeries::class, 'event_series_id');
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'event_categories');
    }

    /**
     * @return HasMany<TicketType, $this>
     */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasOne<ReviewStatistic, $this> */
    public function reviewStatistic(): HasOne
    {
        return $this->hasOne(ReviewStatistic::class);
    }

    /** @return HasOne<EventSuccessScore, $this> */
    public function successScore(): HasOne
    {
        return $this->hasOne(EventSuccessScore::class);
    }

    /** @return HasOne<EventCancellation, $this> */
    public function cancellation(): HasOne
    {
        return $this->hasOne(EventCancellation::class);
    }

    /** @return HasOne<EventRanking, $this> */
    public function ranking(): HasOne
    {
        return $this->hasOne(EventRanking::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return HasMany<ExperienceView, $this> */
    public function views(): HasMany
    {
        return $this->hasMany(ExperienceView::class);
    }

    /** @return HasMany<WaitlistEntry, $this> */
    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }
}
