<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizerProfile extends Model
{
    protected $fillable = [
        'ulid', 'owner_user_id', 'name', 'type', 'description', 'email', 'phone',
        'website_url', 'municipality_id', 'address', 'logo_path', 'cover_path',
        'social_links', 'gallery', 'verification_status', 'verified_at', 'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'social_links' => 'array',
            'gallery' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function reviewStatistic()
    {
        return $this->hasOne(ReviewStatistic::class);
    }

    public function ranking()
    {
        return $this->hasOne(OrganizerRanking::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'organizer_followers')->withTimestamps();
    }

    public function subscription()
    {
        return $this->hasOne(OrganizerSubscription::class);
    }
}
