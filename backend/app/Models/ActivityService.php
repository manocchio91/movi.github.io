<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $activity_id
 * @property string $name
 * @property string $type
 * @property bool $active
 * @property bool $booking_enabled
 * @property int $min_party_size
 * @property int $max_party_size
 * @property int $minimum_advance_minutes
 * @property int $indicative_duration_minutes
 * @property string $capacity_mode
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $preorder_enabled
 * @property int $preorder_edit_deadline_minutes
 * @property int|null $preorder_menu_id
 */
class ActivityService extends Model
{
    protected $fillable = ['ulid', 'activity_id', 'name', 'type', 'active', 'booking_enabled', 'min_party_size', 'max_party_size', 'minimum_advance_minutes', 'indicative_duration_minutes', 'capacity_mode', 'preorder_enabled', 'preorder_edit_deadline_minutes', 'preorder_menu_id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'booking_enabled' => 'boolean', 'preorder_enabled' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return HasMany<ServiceAvailabilityRule, $this> */
    public function availabilityRules(): HasMany
    {
        return $this->hasMany(ServiceAvailabilityRule::class, 'service_id');
    }

    /** @return HasMany<ServiceAvailabilityException, $this> */
    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(ServiceAvailabilityException::class, 'service_id');
    }

    /** @return HasMany<Reservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'service_id');
    }

    /** @return BelongsTo<Menu, $this> */
    public function preorderMenu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'preorder_menu_id');
    }
}
