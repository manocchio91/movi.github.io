<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property-read int $reviews_count
 * @property-read int $favorites_count
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = ['ulid', 'name', 'email', 'phone', 'password', 'role', 'status'];

    protected $hidden = ['password', 'remember_token'];

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if ($user->getAttribute('ulid') === null) {
                $user->setAttribute('ulid', (string) Str::ulid());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /** @return HasOne<OrganizerProfile, $this> */
    public function organizerProfile(): HasOne
    {
        return $this->hasOne(OrganizerProfile::class, 'owner_user_id');
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function preferredCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'user_event_preferences')->withTimestamps();
    }

    /**
     * @return HasOne<NotificationPreference, $this>
     */
    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * @return BelongsToMany<OrganizerProfile, $this>
     */
    public function followedOrganizers(): BelongsToMany
    {
        return $this->belongsToMany(OrganizerProfile::class, 'organizer_followers')->withTimestamps();
    }

    /** @return BelongsToMany<Event, $this> */
    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'favorites')->withTimestamps();
    }

    /** @return HasMany<CustomerEventFormatInterest, $this> */
    public function eventFormatInterests(): HasMany
    {
        return $this->hasMany(CustomerEventFormatInterest::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isOrganizer(): bool
    {
        return $this->role === UserRole::Organizer;
    }
}
