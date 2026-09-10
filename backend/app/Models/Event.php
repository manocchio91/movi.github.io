<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $organizer_profile_id
 * @property int|null $venue_id
 * @property int $municipality_id
 * @property string $title
 * @property string $slug
 * @property string $summary
 * @property string $description
 * @property EventStatus $status
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $booking_closes_at
 * @property int $capacity_total
 * @property int $available_total
 * @property bool $is_free
 * @property string|null $price_notes
 * @property bool $family_friendly
 * @property string $indoor_outdoor
 * @property int|null $minimum_child_age
 * @property int|null $maximum_child_age
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $program
 * @property string|null $included
 * @property string|null $not_included
 * @property string|null $important_information
 * @property string|null $requirements
 * @property string|null $recommended_age_range
 * @property string|null $equipment_notes
 * @property string|null $allergy_notes
 * @property string|null $cancellation_policy
 * @property string|null $organizer_contact
 * @property bool $show_in_inspiration_feed
 * @property int|null $event_series_id
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by_user_id
 * @property string|null $cancellation_reason
 * @property string|null $cancellation_notes
 * @property int|null $admin_creativity_rating
 * @property string|null $image_path
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
