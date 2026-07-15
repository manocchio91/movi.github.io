<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function organizerProfile()
    {
        return $this->belongsTo(OrganizerProfile::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function images()
    {
        return $this->hasMany(ReviewImage::class);
    }

    public function reports()
    {
        return $this->hasMany(ReviewReport::class);
    }
}
