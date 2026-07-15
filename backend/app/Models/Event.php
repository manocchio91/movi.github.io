<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    protected $fillable = [
        'ulid', 'organizer_profile_id', 'venue_id', 'municipality_id', 'title', 'slug',
        'summary', 'description', 'status', 'starts_at', 'ends_at', 'booking_closes_at',
        'capacity_total', 'available_total', 'is_free', 'price_notes', 'family_friendly',
        'indoor_outdoor', 'minimum_child_age', 'maximum_child_age', 'published_at',
        'submitted_at', 'approved_at', 'approved_by',
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
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function organizerProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizerProfile::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'event_categories');
    }

    public function ticketTypes()
    {
        return $this->hasMany(TicketType::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}
