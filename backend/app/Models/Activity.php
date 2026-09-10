<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $organizer_profile_id
 * @property int|null $municipality_id
 * @property string $name
 * @property string $slug
 * @property ActivityType $type
 * @property string|null $description
 * @property string|null $address
 * @property string|null $postal_code
 * @property string|null $phone
 * @property string|null $public_email
 * @property ActivityStatus $status
 * @property bool $booking_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Activity extends Model
{
    protected $fillable = ['ulid', 'organizer_profile_id', 'municipality_id', 'name', 'slug', 'type', 'description', 'address', 'postal_code', 'phone', 'public_email', 'status', 'booking_enabled'];

    protected function casts(): array
    {
        return ['type' => ActivityType::class, 'status' => ActivityStatus::class, 'booking_enabled' => 'boolean'];
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

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** @return HasMany<ActivityService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(ActivityService::class);
    }

    /** @return HasMany<Reservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    /** @return HasMany<ActivityReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(ActivityReview::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'activity_followers')->withTimestamps();
    }
}
