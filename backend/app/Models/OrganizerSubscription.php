<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $organizer_profile_id
 * @property int $subscription_plan_id
 * @property string $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_starts_at
 * @property Carbon|null $current_period_ends_at
 * @property Carbon|null $cancelled_at
 * @property string|null $external_subscription_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $agreement_starts_at
 * @property Carbon|null $agreement_ends_at
 * @property string|null $agreement_notes
 * @property int|null $booking_fee_cents
 */
class OrganizerSubscription extends Model
{
    protected $fillable = [
        'ulid', 'organizer_profile_id', 'subscription_plan_id', 'status',
        'trial_ends_at', 'current_period_starts_at', 'current_period_ends_at',
        'cancelled_at', 'external_subscription_id',
        'agreement_starts_at', 'agreement_ends_at', 'agreement_notes',
        'booking_fee_cents',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'agreement_starts_at' => 'date',
            'agreement_ends_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * @return BelongsTo<OrganizerProfile, $this>
     */
    public function organizerProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizerProfile::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrganizerSubscriptionHistory::class)->latest('created_at');
    }
}
