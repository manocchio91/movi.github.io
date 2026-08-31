<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityReview extends Model
{
    protected $fillable = ['ulid', 'activity_id', 'reservation_id', 'user_id', 'rating', 'comment', 'would_recommend', 'status', 'reviewed_at'];

    protected $hidden = ['id', 'activity_id', 'reservation_id', 'user_id'];

    protected function casts(): array
    {
        return ['would_recommend' => 'boolean', 'reviewed_at' => 'datetime'];
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

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
