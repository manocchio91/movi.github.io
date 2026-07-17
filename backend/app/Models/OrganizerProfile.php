<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read int $followers_count
 * @property-read Municipality|null $municipality
 * @property-read ReviewStatistic|null $reviewStatistic
 * @property-read OrganizerSubscription|null $subscription
 */
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

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasOne<ReviewStatistic, $this>
     */
    public function reviewStatistic(): HasOne
    {
        return $this->hasOne(ReviewStatistic::class);
    }

    /** @return HasOne<OrganizerRanking, $this> */
    public function ranking(): HasOne
    {
        return $this->hasOne(OrganizerRanking::class);
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organizer_followers')->withTimestamps();
    }

    /** @return HasOne<OrganizerSubscription, $this> */
    public function subscription(): HasOne
    {
        return $this->hasOne(OrganizerSubscription::class);
    }
}
