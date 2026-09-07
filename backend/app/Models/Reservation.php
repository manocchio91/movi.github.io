<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    protected $fillable = ['ulid', 'user_id', 'activity_id', 'service_id', 'starts_at', 'party_size', 'status', 'contact_phone', 'notes', 'idempotency_key', 'cancelled_at', 'cancellation_reason', 'cancelled_by_user_id'];

    protected $hidden = ['id', 'user_id', 'activity_id', 'service_id', 'idempotency_key'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'cancelled_at' => 'datetime', 'status' => ReservationStatus::class];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return BelongsTo<ActivityService, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(ActivityService::class, 'service_id');
    }

    /** @return HasOne<ActivityReview, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(ActivityReview::class);
    }

    public function preorder(): HasOne
    {
        return $this->hasOne(PreOrder::class);
    }
}
