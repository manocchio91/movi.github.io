<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $owner_user_id
 * @property string $name
 * @property string $type
 * @property string|null $description
 * @property int|null $municipality_id
 * @property string|null $address
 * @property string $email
 * @property string|null $phone
 * @property string|null $website_url
 * @property string $verification_status
 * @property Carbon|null $verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $logo_path
 * @property string|null $cover_path
 * @property array<mixed>|null $social_links
 * @property array<mixed>|null $gallery
 * @property Carbon|null $suspended_at
 * @property string|null $street_number
 * @property string|null $postal_code
 * @property string|null $province
 * @property string $country
 * @property string|null $vat_number
 * @property string|null $pec
 * @property string|null $sdi_code
 * @property string|null $public_email
 * @property string|null $instagram_url
 * @property string|null $facebook_url
 * @property string|null $tiktok_url
 * @property Carbon|null $submitted_at
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_notes
 * @property string|null $youtube_url
 * @property string|null $profile_change_status
 * @property array<mixed>|null $pending_changes
 * @property string|null $profile_change_request_ulid
 * @property Carbon|null $profile_change_submitted_at
 * @property int|null $profile_change_reviewed_by
 * @property Carbon|null $profile_change_reviewed_at
 * @property string|null $profile_change_review_notes
 * @property-read int $followers_count
 * @property-read Municipality|null $municipality
 * @property-read ReviewStatistic|null $reviewStatistic
 * @property-read OrganizerSubscription|null $subscription
 */
class OrganizerProfile extends Model
{
    protected $hidden = ['pending_changes'];

    protected $fillable = [
        'ulid', 'owner_user_id', 'name', 'type', 'description', 'email', 'phone',
        'website_url', 'municipality_id', 'address', 'street_number', 'postal_code',
        'province', 'country', 'vat_number', 'pec', 'sdi_code', 'public_email',
        'instagram_url', 'facebook_url', 'tiktok_url', 'youtube_url', 'logo_path', 'cover_path',
        'social_links', 'gallery', 'verification_status',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'social_links' => 'array',
            'gallery' => 'array',
            'pending_changes' => 'array',
            'profile_change_submitted_at' => 'datetime',
            'profile_change_reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function profileChangeReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profile_change_reviewed_by');
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

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
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
